<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrLeaveBalance;
use App\Models\HR\HrLeaveRequest;
use App\Models\HR\HrLeaveType;
use App\Services\HR\EmployeeNotifier;
use App\Services\HR\LeaveService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class HrLeaveController extends Controller
{
    /** Pending-first requests queue */
    public function index(Request $request)
    {
        $query = HrLeaveRequest::with(['employee.department', 'leaveType', 'decider'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->employee_id, fn($q, $id) => $q->where('employee_id', $id))
            ->orderByRaw("FIELD(status,'pending','approved','rejected','cancelled')")
            ->orderByDesc('created_at');

        $requests  = $query->paginate(30)->withQueryString();
        $employees = HrEmployee::whereIn('status', ['active','probation'])->orderBy('first_name')->get();

        return view('hr.leaves.index', compact('requests', 'employees'));
    }

    /** Submit leave request form */
    public function create(Request $request)
    {
        $employees  = HrEmployee::whereIn('status', ['active','probation'])->orderBy('first_name')->get();
        $leaveTypes = HrLeaveType::where('is_active', true)->get();
        $selected   = $request->employee_id ? HrEmployee::find($request->employee_id) : null;

        return view('hr.leaves.create', compact('employees', 'leaveTypes', 'selected'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'   => 'required|exists:hr_employees,id',
            'leave_type_id' => 'required|exists:hr_leave_types,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'reason'        => 'nullable|string|max:1000',
            'document'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $employee  = HrEmployee::findOrFail($data['employee_id']);
        $leaveType = HrLeaveType::findOrFail($data['leave_type_id']);
        $docPath   = null;

        if ($request->hasFile('document')) {
            $docPath = $request->file('document')->store('hr/leave_docs', 'public');
        }

        $result = LeaveService::submitRequest(
            $employee, $leaveType,
            $data['start_date'], $data['end_date'],
            $data['reason'] ?? null, $docPath
        );

        if ($result['error']) {
            return back()->with('error', $result['error'])->withInput();
        }

        return redirect()->route('hr.leaves.index')
            ->with('success', "Leave request submitted for {$employee->full_name}.");
    }

    public function show(HrLeaveRequest $leave)
    {
        $leave->load(['employee', 'leaveType', 'decider']);

        return view('hr.leaves.show', compact('leave'));
    }

    /** Approve modal submit */
    public function approve(Request $request, HrLeaveRequest $leave)
    {
        if ($leave->status !== 'pending') {
            return back()->with('error', 'Only pending requests can be approved.');
        }

        $request->validate(['decision_note' => 'nullable|string|max:1000']);

        LeaveService::approve($leave, $request->decision_note);

        // Notify employee in real-time
        EmployeeNotifier::send(
            $leave->employee,
            'leave_approved',
            'Fasaхa la Ansixiyay ✓',
            "Codsigaaga fasaxa ({$leave->leaveType?->name}) ee " .
                \Carbon\Carbon::parse($leave->start_date)->format('d M') . ' — ' .
                \Carbon\Carbon::parse($leave->end_date)->format('d M Y') .
                ' la ansixiyay.',
            ['leave_id' => $leave->id],
            route('employee.leaves'),
        );

        return back()->with('success', 'Leave request approved.');
    }

    /** Reject modal submit */
    public function reject(Request $request, HrLeaveRequest $leave)
    {
        if ($leave->status !== 'pending') {
            return back()->with('error', 'Only pending requests can be rejected.');
        }

        $request->validate(['decision_note' => 'required|string|max:1000']);

        LeaveService::reject($leave, $request->decision_note);

        // Notify employee in real-time
        EmployeeNotifier::send(
            $leave->employee,
            'leave_rejected',
            'Fasaхa la Diidday ✗',
            "Codsigaaga fasaxa ({$leave->leaveType?->name}) la diidday." .
                ($request->decision_note ? " Sababta: {$request->decision_note}" : ''),
            ['leave_id' => $leave->id],
            route('employee.leaves'),
        );

        return back()->with('success', 'Leave request rejected.');
    }

    /** Leave calendar (month view) */
    public function calendar(Request $request)
    {
        $month  = $request->input('month', now()->format('Y-m'));
        [$y, $m] = explode('-', $month);
        $start  = Carbon::parse("$y-$m-01");
        $end    = $start->copy()->endOfMonth();

        $leaves = HrLeaveRequest::with(['employee', 'leaveType'])
            ->where('status', 'approved')
            ->where(fn($q) =>
                $q->whereBetween('start_date', [$start, $end])
                  ->orWhereBetween('end_date', [$start, $end])
                  ->orWhere(fn($q2) => $q2->where('start_date', '<=', $start)->where('end_date', '>=', $end))
            )
            ->get();

        return view('hr.leaves.calendar', compact('leaves', 'month', 'start'));
    }

    /** Balances per employee */
    public function balances(Request $request)
    {
        $year      = $request->input('year', now()->year);
        $employees = HrEmployee::with(['leaveBalances' => fn($q) => $q->where('year', $year)->with('leaveType')])
            ->whereIn('status', ['active','probation'])
            ->orderBy('first_name')
            ->get();

        $leaveTypes = HrLeaveType::where('is_active', true)->get();

        return view('hr.leaves.balances', compact('employees', 'leaveTypes', 'year'));
    }
}
