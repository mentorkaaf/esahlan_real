<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HR\HrAttendance;
use App\Models\HR\HrAuditLog;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrJobPosting;
use App\Models\HR\HrApplicant;
use App\Models\HR\HrLeaveRequest;
use App\Models\HR\HrPayrollRun;
use App\Models\HR\HrPerformanceCycle;
use Illuminate\Http\Request;

class AdminHrController extends Controller
{
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
}
