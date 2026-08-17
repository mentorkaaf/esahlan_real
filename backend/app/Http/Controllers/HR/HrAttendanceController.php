<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrAttendance;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrShift;
use App\Services\HR\AttendanceService;
use App\Services\HR\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrAttendanceController extends Controller
{
    /** Daily attendance sheet */
    public function daily(Request $request)
    {
        $date      = $request->input('date', today()->format('Y-m-d'));
        $d         = Carbon::parse($date);
        $employees = HrEmployee::with(['department', 'attendance' => fn($q) => $q->where('date', $date)])
            ->whereIn('status', ['active', 'probation'])
            ->orderBy('first_name')
            ->get();

        $shifts = HrShift::where('is_active', true)->get();

        // Summary — eager load already filtered by date, so use ->first() not ->where('date',...)
        $summary = [
            'present' => $employees->filter(fn($e) => $e->attendance->first()?->status === 'present')->count(),
            'late'    => $employees->filter(fn($e) => $e->attendance->first()?->status === 'late')->count(),
            'absent'  => $employees->filter(fn($e) => ($e->attendance->first()?->status ?? 'absent') === 'absent' || !$e->attendance->count())->count(),
            'leave'   => $employees->filter(fn($e) => $e->attendance->first()?->status === 'leave')->count(),
        ];

        return view('hr.attendance.daily', compact('employees', 'date', 'd', 'shifts', 'summary'));
    }

    /** Quick mark from daily sheet (AJAX-friendly) */
    public function mark(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hr_employees,id',
            'date'        => 'required|date',
            'status'      => 'required|in:present,late,absent,half_day,leave',
            'check_in'    => 'nullable|date_format:H:i',
            'check_out'   => 'nullable|date_format:H:i',
            'shift_id'    => 'nullable|exists:hr_shifts,id',
            'note'        => 'nullable|string|max:500',
        ]);

        $employee = HrEmployee::findOrFail($data['employee_id']);

        // 48h correction rule: only managers can correct older records
        $recordDate = Carbon::parse($data['date']);
        if ($recordDate->lt(now()->subHours(48)) && !Auth::guard('hr')->user()->isManager()) {
            return response()->json(['error' => 'Corrections after 48 hours require an HR Manager.'], 403);
        }

        $attendance = AttendanceService::markAttendance(
            $employee,
            $data['date'],
            $data['check_in'] ?? null,
            $data['check_out'] ?? null,
            $data['note'] ?? null,
            true,
            $data['status']
        );

        if (!empty($data['shift_id'])) {
            $attendance->shift_id = $data['shift_id'];
            $attendance->save();
        }

        return response()->json(['success' => true, 'status' => $attendance->status]);
    }

    /** Bulk-mark all present */
    public function bulkMarkPresent(Request $request)
    {
        $data = $request->validate([
            'date'     => 'required|date',
            'shift_id' => 'required|exists:hr_shifts,id',
        ]);

        $shift = HrShift::findOrFail($data['shift_id']);
        $count = AttendanceService::bulkMarkPresent($data['date'], $shift);

        AuditService::log('attendance.bulk_present', null, null, ['date' => $data['date'], 'count' => $count]);

        return back()->with('success', "{$count} employees marked present for {$data['date']}.");
    }

    /** Monthly grid for one employee */
    public function monthly(Request $request, HrEmployee $employee)
    {
        $month = $request->input('month', now()->format('Y-m'));
        [$year, $mon] = explode('-', $month);

        $start      = Carbon::parse("$year-$mon-01");
        $end        = $start->copy()->endOfMonth();
        $attendance = HrAttendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$start, $end])
            ->get()
            ->keyBy(fn($a) => $a->date->format('Y-m-d'));

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days[$d->format('Y-m-d')] = $attendance->get($d->format('Y-m-d'));
        }

        return view('hr.attendance.monthly', compact('employee', 'month', 'start', 'days'));
    }

    /** CSV import form */
    public function importForm()
    {
        return view('hr.attendance.import');
    }

    /** CSV import process */
    public function importSubmit(Request $request)
    {
        $request->validate(['csv' => 'required|file|mimes:csv,txt']);

        $path  = $request->file('csv')->getRealPath();
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $rows  = [];
        $header = null;

        foreach ($lines as $i => $line) {
            $cols = str_getcsv($line);
            if ($i === 0) {
                $header = array_map('trim', $cols);
                continue;
            }
            $rows[] = array_combine($header, array_map('trim', $cols));
        }

        $result = AttendanceService::importCsv($rows);

        return view('hr.attendance.import_result', $result);
    }

    /** Correction form (>48h) */
    public function correct(Request $request, HrAttendance $attendance)
    {
        $this->requireManager();

        $data = $request->validate([
            'check_in'  => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'status'    => 'required|in:present,late,absent,half_day,leave',
            'note'      => 'required|string|max:1000',
        ]);

        $before = $attendance->toArray();

        $attendance->update(array_merge($data, [
            'is_manual'    => true,
            'corrected_by' => Auth::guard('hr')->id(),
        ]));

        AuditService::log('attendance.corrected', $attendance, $before, $attendance->fresh()->toArray());

        return back()->with('success', 'Attendance record corrected.');
    }

    private function requireManager(): void
    {
        if (!Auth::guard('hr')->user()->isManager()) abort(403);
    }
}
