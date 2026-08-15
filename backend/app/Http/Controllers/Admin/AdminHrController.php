<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HR\HrAttendance;
use App\Models\HR\HrAuditLog;
use App\Models\HR\HrDisciplinaryCase;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrJobPosting;
use App\Models\HR\HrApplicant;
use App\Models\HR\HrLeaveRequest;
use App\Models\HR\HrPayrollRun;
use App\Models\HR\HrPerformanceCycle;
use App\Services\HR\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminHrController extends Controller
{
    /** Admin HR Dashboard v2 — KPI + activity feed */
    public function dashboard()
    {
        $activeCount   = HrEmployee::whereIn('status',['active','probation'])->count();
        $openCases     = HrDisciplinaryCase::where('status','!=','closed')->count();
        $openPositions = \App\Models\HR\HrJobPosting::where('status','open')->sum('openings');

        // Monthly payroll cost (latest approved/paid run)
        $latestRun = HrPayrollRun::whereIn('status',['approved','paid'])
            ->orderByDesc('period')->first();
        $payrollCost = $latestRun?->total_net ?? 0;

        // Attendance % today
        $todayTotal   = HrEmployee::whereIn('status',['active','probation'])->count();
        $todayPresent = \App\Models\HR\HrAttendance::where('date', today())
            ->whereIn('status',['present','late'])->count();
        $attendancePct = $todayTotal > 0 ? round($todayPresent / $todayTotal * 100) : 0;

        // Recent audit events (activity feed)
        $feed = HrAuditLog::orderByDesc('created_at')->limit(25)->get();

        return view('admin.hr.dashboard', compact(
            'activeCount','openCases','openPositions',
            'payrollCost','attendancePct','latestRun','feed'
        ));
    }

    /** Today's attendance summary — read-only */
    public function attendance(Request $request)
    {
        $date = $request->input('date', today()->format('Y-m-d'));

        $records = HrAttendance::with('employee.department')
            ->where('date', $date)
            ->get();

        $allActive = HrEmployee::whereIn('status', ['active','probation'])->count();
        $markedIds = $records->pluck('employee_id');

        $summary = [
            'present'  => $records->whereIn('status', ['present'])->count(),
            'late'     => $records->whereIn('status', ['late'])->count(),
            'leave'    => $records->whereIn('status', ['leave'])->count(),
            'absent'   => $allActive - $records->whereNotIn('status', ['weekend','holiday'])->count(),
        ];

        $absentees = HrEmployee::whereIn('status', ['active','probation'])
            ->whereNotIn('id', $markedIds)
            ->with('department')
            ->get();

        return view('admin.hr.attendance', compact('date', 'records', 'summary', 'absentees'));
    }

    /** Leave requests — read-only */
    public function leaves(Request $request)
    {
        $pending = HrLeaveRequest::with(['employee.department', 'leaveType', 'decider'])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        $recent = HrLeaveRequest::with(['employee.department', 'leaveType', 'decider'])
            ->whereIn('status', ['approved','rejected'])
            ->orderByDesc('decided_at')
            ->limit(30)
            ->get();

        return view('admin.hr.leaves', compact('pending', 'recent'));
    }

    /** Payroll runs — read-only with approvals timeline */
    public function payroll(Request $request)
    {
        $runs = HrPayrollRun::with(['generator', 'submitter', 'approver'])
            ->orderByDesc('period')
            ->get();

        // Approvals timeline from audit log
        $timeline = HrAuditLog::where('subject_type', HrPayrollRun::class)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.hr.payroll', compact('runs', 'timeline'));
    }

    /** Recruitment overview */
    public function recruitment()
    {
        $postings = HrJobPosting::with('department')
            ->withCount('applicants')
            ->orderByDesc('created_at')
            ->get();

        // Pipeline funnel per posting
        $stages = \App\Models\HR\HrApplicant::STAGES;
        $funnelData = HrApplicant::selectRaw('job_posting_id, stage, count(*) as cnt')
            ->groupBy('job_posting_id', 'stage')
            ->get()
            ->groupBy('job_posting_id');

        // Time-in-stage averages (days since stage_changed_at for non-final)
        $avgTimeInStage = HrApplicant::whereNotIn('stage', ['hired', 'rejected'])
            ->whereNotNull('stage_changed_at')
            ->selectRaw('stage, ROUND(AVG(DATEDIFF(NOW(), stage_changed_at)), 1) as avg_days')
            ->groupBy('stage')
            ->pluck('avg_days', 'stage');

        return view('admin.hr.recruitment', compact('postings', 'funnelData', 'stages', 'avgTimeInStage'));
    }

    /** Performance overview */
    public function performance()
    {
        $cycles = HrPerformanceCycle::withCount(['goals', 'reviews'])
            ->orderByDesc('period_start')
            ->get();

        $deptAverages = [];
        foreach ($cycles->where('status', 'closed') as $cycle) {
            $deptAverages[$cycle->id] = \App\Models\HR\HrReview::where('cycle_id', $cycle->id)
                ->where('status', 'submitted')
                ->with('employee.department')
                ->get()
                ->groupBy('employee.department.name')
                ->map(fn($g) => round($g->avg('overall_score'), 2));
        }

        return view('admin.hr.performance', compact('cycles', 'deptAverages'));
    }

    /** Payslip drill-down — super admin sees amounts */
    public function payslip(\App\Models\HR\HrPayslip $payslip)
    {
        $payslip->load(['employee.department', 'employee.position', 'items', 'run']);

        return view('admin.hr.payslip', compact('payslip'));
    }

    /** Discipline overview — all cases */
    public function discipline(Request $request)
    {
        $cases = HrDisciplinaryCase::with(['employee.department','openedBy'])
            ->when($request->status, fn($q,$v) => $q->where('status',$v))
            ->orderByRaw("FIELD(status,'open','investigating','closed')")
            ->orderByDesc('created_at')
            ->paginate(30);

        $stats = [
            'open'         => HrDisciplinaryCase::where('status','open')->count(),
            'investigating'=> HrDisciplinaryCase::where('status','investigating')->count(),
            'closed'       => HrDisciplinaryCase::where('status','closed')->count(),
        ];

        return view('admin.hr.discipline', compact('cases','stats'));
    }

    /** Full audit explorer */
    public function audit(Request $request)
    {
        $q = HrAuditLog::with(['actor'])->orderByDesc('created_at');

        if ($request->filled('action')) {
            $q->where('action', 'like', $request->action . '%');
        }
        if ($request->filled('actor_id')) {
            $q->where('actor_id', $request->actor_id);
        }
        if ($request->filled('subject')) {
            $q->where('subject_type', 'like', '%' . $request->subject . '%');
        }
        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->to);
        }

        $logs    = $q->paginate(50)->withQueryString();
        $actors  = \App\Models\HR\HrStaff::orderBy('name')->get();
        $actions = HrAuditLog::selectRaw("SUBSTRING_INDEX(action, '.', 1) as module")
            ->distinct()->pluck('module');

        return view('admin.hr.audit', compact('logs','actors','actions'));
    }

    // ── Override actions (super_admin only) ───────────────────────────────

    /** Unlock a paid payroll run back to approved so payslips can be re-marked */
    public function unlockPayroll(Request $request, HrPayrollRun $payroll)
    {
        $this->requireSuperAdmin();
        $request->validate(['reason' => 'required|string|max:500']);

        $before = ['status' => $payroll->status];
        $payroll->update(['status' => 'approved']);

        AuditService::logAdmin('payroll.unlocked', $payroll, $before, [
            'status' => 'approved', 'reason' => $request->reason,
        ]);

        return back()->with('success', "Payroll run {$payroll->period} unlocked.");
    }

    /** Reactivate a terminated employee */
    public function reactivateEmployee(Request $request, HrEmployee $employee)
    {
        $this->requireSuperAdmin();
        $request->validate(['reason' => 'required|string|max:500']);

        $before = ['status' => $employee->status];
        $employee->update(['status' => 'active', 'end_date' => null]);

        AuditService::logAdmin('employee.reactivated', $employee, $before, [
            'status' => 'active', 'reason' => $request->reason,
        ]);

        return back()->with('success', "{$employee->full_name} reactivated.");
    }

    /** Force-close a disciplinary case */
    public function forceCloseCase(Request $request, HrDisciplinaryCase $case)
    {
        $this->requireSuperAdmin();
        $request->validate([
            'outcome' => 'required|in:verbal_warning,written_warning,suspension,termination,dismissed',
            'reason'  => 'required|string|max:500',
        ]);

        $before = ['status' => $case->status, 'outcome' => $case->outcome];
        $case->update([
            'status'     => 'closed',
            'outcome'    => $request->outcome,
            'closed_by'  => null,
            'closed_at'  => now(),
        ]);

        AuditService::logAdmin('discipline.force_closed', $case, $before, [
            'outcome' => $request->outcome, 'reason' => $request->reason,
        ]);

        return back()->with('success', 'Case force-closed.');
    }

    private function requireSuperAdmin(): void
    {
        if (Auth::user()?->role !== 'super_admin') {
            abort(403, 'Super admin only.');
        }
    }
}
