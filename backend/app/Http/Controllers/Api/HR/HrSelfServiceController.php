<?php

namespace App\Http\Controllers\Api\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrAnnouncement;
use App\Models\HR\HrAttendance;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrLeaveRequest;
use App\Models\HR\HrLeaveType;
use App\Models\HR\HrPayslip;
use App\Services\HR\AttendanceService;
use App\Services\HR\LeaveService;
use App\Services\HR\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class HrSelfServiceController extends Controller
{
    // ── Resolve linked employee ────────────────────────────────────────────

    private function employee(): HrEmployee
    {
        $employee = HrEmployee::where('user_id', Auth::id())
            ->whereIn('status', ['active', 'probation'])
            ->first();

        if (!$employee) {
            abort(response()->json([
                'success' => false,
                'code'    => 'HR_NOT_LINKED',
                'message' => 'Your account is not linked to an employee record.',
            ], 403));
        }

        return $employee;
    }

    // ── Profile ───────────────────────────────────────────────────────────

    /**
     * GET /api/v1/hr/me
     */
    public function profile(): JsonResponse
    {
        $emp = $this->employee();
        $emp->load(['department', 'position']);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'              => $emp->id,
                'employee_no'     => $emp->employee_no,
                'full_name'       => $emp->full_name,
                'email'           => $emp->email,
                'phone'           => $emp->phone,
                'department'      => $emp->department?->name,
                'position'        => $emp->position?->title,
                'employment_type' => $emp->employment_type,
                'status'          => $emp->status,
                'hire_date'       => $emp->hire_date?->format('Y-m-d'),
                'base_salary'     => (float) $emp->base_salary,
                'probation_end'   => $emp->probation_end?->format('Y-m-d'),
            ],
        ]);
    }

    // ── Attendance ─────────────────────────────────────────────────────────

    /**
     * GET /api/v1/hr/me/attendance?month=2026-08
     */
    public function attendance(Request $request): JsonResponse
    {
        $emp   = $this->employee();
        $month = $request->input('month', now()->format('Y-m'));

        [$year, $mon] = explode('-', $month);

        $records = HrAttendance::where('employee_id', $emp->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $mon)
            ->orderBy('date')
            ->get(['date','check_in','check_out','status','late_minutes','overtime_minutes','note','source','check_in_lat','check_in_lng']);

        return response()->json([
            'success' => true,
            'data'    => [
                'month'   => $month,
                'records' => $records,
                'summary' => [
                    'present'  => $records->whereIn('status', ['present','late'])->count(),
                    'absent'   => $records->where('status', 'absent')->count(),
                    'leave'    => $records->where('status', 'leave')->count(),
                    'late'     => $records->where('status', 'late')->count(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/hr/me/attendance/check-in
     */
    public function checkIn(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $emp  = $this->employee();
        $today = today()->format('Y-m-d');
        $now   = now()->format('H:i');

        // Check shift hours ± 2h
        $error = $this->validateCheckInTime($emp, $today);
        if ($error) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        // Prevent duplicate check-in
        $existing = HrAttendance::where('employee_id', $emp->id)->where('date', $today)->first();
        if ($existing && $existing->check_in) {
            return response()->json([
                'success' => false,
                'message' => "Already checked in at {$existing->check_in}.",
            ], 422);
        }

        $attendance = AttendanceService::markAttendance(
            $emp, $today, $now, null, null, false
        );

        // Stamp geo + source
        $attendance->update([
            'source'        => 'api',
            'check_in_lat'  => $request->lat,
            'check_in_lng'  => $request->lng,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checked in successfully.',
            'data'    => [
                'date'         => $today,
                'check_in'     => $now,
                'status'       => $attendance->status,
                'late_minutes' => $attendance->late_minutes,
            ],
        ]);
    }

    /**
     * POST /api/v1/hr/me/attendance/check-out
     */
    public function checkOut(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $emp   = $this->employee();
        $today = today()->format('Y-m-d');
        $now   = now()->format('H:i');

        $existing = HrAttendance::where('employee_id', $emp->id)->where('date', $today)->first();
        if (!$existing || !$existing->check_in) {
            return response()->json(['success' => false, 'message' => 'No check-in found for today.'], 422);
        }
        if ($existing->check_out) {
            return response()->json([
                'success' => false,
                'message' => "Already checked out at {$existing->check_out}.",
            ], 422);
        }

        $attendance = AttendanceService::markAttendance(
            $emp, $today, $existing->check_in, $now, null, false
        );

        $attendance->update([
            'source'         => 'api',
            'check_out_lat'  => $request->lat,
            'check_out_lng'  => $request->lng,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checked out successfully.',
            'data'    => [
                'date'             => $today,
                'check_in'         => $existing->check_in,
                'check_out'        => $now,
                'status'           => $attendance->status,
                'overtime_minutes' => $attendance->overtime_minutes,
            ],
        ]);
    }

    private function validateCheckInTime(HrEmployee $employee, string $date): ?string
    {
        // Load shift — use AttendanceService resolution via reflection or direct query
        $shift = \App\Models\HR\HrEmployeeShift::where('employee_id', $employee->id)
            ->where('effective_from', '<=', $date)
            ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->orderByDesc('effective_from')
            ->with('shift')
            ->first()
            ?->shift;

        if (!$shift) return null; // no shift assigned → allow check-in any time

        $shiftStart = Carbon::parse($date . ' ' . $shift->start_time);
        $earliest   = $shiftStart->copy()->subHours(2);
        $latest     = $shiftStart->copy()->addHours(2);
        $now        = now();

        if ($now->lt($earliest) || $now->gt($latest)) {
            return "Check-in is only allowed between {$earliest->format('H:i')} and {$latest->format('H:i')} (±2h of your shift).";
        }

        return null;
    }

    // ── Leave ──────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/hr/me/leave/balances
     */
    public function leaveBalances(): JsonResponse
    {
        $emp  = $this->employee();
        $year = now()->year;

        $balances = \App\Models\HR\HrLeaveBalance::with('leaveType')
            ->where('employee_id', $emp->id)
            ->where('year', $year)
            ->get()
            ->map(fn($b) => [
                'leave_type'  => $b->leaveType?->name,
                'allocated'   => (float) $b->allocated,
                'used'        => (float) $b->used,
                'pending'     => (float) $b->pending,
                'available'   => (float) $b->available,
            ]);

        return response()->json(['success' => true, 'data' => $balances]);
    }

    /**
     * GET /api/v1/hr/me/leave/requests
     */
    public function leaveRequests(): JsonResponse
    {
        $emp = $this->employee();

        $requests = HrLeaveRequest::with('leaveType')
            ->where('employee_id', $emp->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($r) => [
                'id'           => $r->id,
                'leave_type'   => $r->leaveType?->name,
                'start_date'   => $r->start_date,
                'end_date'     => $r->end_date,
                'days'         => $r->days_requested,
                'reason'       => $r->reason,
                'status'       => $r->status,
                'decided_at'   => $r->decided_at?->format('Y-m-d'),
                'rejection_note' => $r->rejection_note,
            ]);

        return response()->json(['success' => true, 'data' => $requests]);
    }

    /**
     * POST /api/v1/hr/me/leave/requests
     */
    public function submitLeave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'leave_type_id' => 'required|exists:hr_leave_types,id',
            'start_date'    => 'required|date|after_or_equal:today',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'reason'        => 'nullable|string|max:1000',
        ]);

        $emp       = $this->employee();
        $leaveType = HrLeaveType::findOrFail($data['leave_type_id']);

        $result = LeaveService::submitRequest(
            $emp,
            $leaveType,
            $data['start_date'],
            $data['end_date'],
            $data['reason'] ?? null
        );

        if ($result['error']) {
            return response()->json(['success' => false, 'message' => $result['error']], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave request submitted successfully.',
            'data'    => [
                'id'         => $result['request']->id,
                'leave_type' => $leaveType->name,
                'start_date' => $data['start_date'],
                'end_date'   => $data['end_date'],
                'days'       => $result['request']->days_requested,
                'status'     => 'pending',
            ],
        ], 201);
    }

    // ── Payslips ───────────────────────────────────────────────────────────

    /**
     * GET /api/v1/hr/me/payslips
     */
    public function payslips(): JsonResponse
    {
        $emp = $this->employee();

        $slips = HrPayslip::with('run')
            ->where('employee_id', $emp->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($s) => [
                'id'             => $s->id,
                'period'         => $s->run?->period,
                'total_gross'    => (float) $s->total_gross,
                'total_deductions' => (float) $s->total_deductions,
                'total_net'      => (float) $s->total_net,
                'payment_status' => $s->payment_status,
                'paid_at'        => $s->paid_at?->format('Y-m-d'),
            ]);

        return response()->json(['success' => true, 'data' => $slips]);
    }

    /**
     * GET /api/v1/hr/me/payslips/{id}/pdf
     */
    public function payslipPdf(int $id): mixed
    {
        $emp     = $this->employee();
        $payslip = HrPayslip::findOrFail($id);

        // Policy: only own payslip
        if ($payslip->employee_id !== $emp->id) {
            return response()->json([
                'success' => false,
                'code'    => 'PAYSLIP_FORBIDDEN',
                'message' => 'You are not authorized to access this payslip.',
            ], 403);
        }

        // generatePdf() stores to disk and returns path
        if (!$payslip->pdf_path || !\Illuminate\Support\Facades\Storage::disk('public')->exists($payslip->pdf_path)) {
            PayrollService::generatePdf($payslip);
            $payslip->refresh();
        }

        $content = \Illuminate\Support\Facades\Storage::disk('public')->get($payslip->pdf_path);

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="payslip-' . $payslip->id . '.pdf"',
        ]);
    }

    // ── Announcements ──────────────────────────────────────────────────────

    /**
     * GET /api/v1/hr/me/announcements
     */
    public function announcements(): JsonResponse
    {
        $emp = $this->employee();

        $announcements = HrAnnouncement::where('published_at', '<=', now())
            ->where(fn($q) =>
                $q->where('audience', 'all')
                  ->orWhere(fn($q2) =>
                      $q2->where('audience', 'department')
                         ->where('department_id', $emp->department_id)
                  )
            )
            ->orderByDesc('published_at')
            ->limit(30)
            ->get()
            ->map(fn($a) => [
                'id'           => $a->id,
                'title'        => $a->title,
                'body'         => $a->body,
                'audience'     => $a->audience,
                'published_at' => $a->published_at->format('Y-m-d H:i'),
            ]);

        return response()->json(['success' => true, 'data' => $announcements]);
    }
}
