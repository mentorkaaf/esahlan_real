<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrAttendance;
use App\Models\HR\HrApplicant;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrLeaveBalance;
use App\Models\HR\HrLeaveRequest;
use App\Models\HR\HrPayrollRun;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrReportController extends Controller
{
    public function index(Request $request)
    {
        $report = $request->input('report', 'headcount');

        $data = match($report) {
            'headcount'   => $this->headcount(),
            'attendance'  => $this->attendanceSummary($request),
            'leave'       => $this->leaveUtilization(),
            'payroll'     => $this->payrollTrend(),
            'turnover'    => $this->turnover(),
            'recruitment' => $this->recruitmentFunnel(),
            default       => [],
        };

        if ($request->has('csv')) {
            return $this->exportCsv($report, $data);
        }

        return view('hr.reports.index', compact('report', 'data'));
    }

    // ── Report builders ────────────────────────────────────────────────────

    private function headcount(): array
    {
        $byDept = HrEmployee::with('department')
            ->selectRaw('department_id, status, count(*) as cnt')
            ->groupBy('department_id', 'status')
            ->get()
            ->groupBy('department_id');

        $rows = [];
        $depts = \App\Models\HR\HrDepartment::all()->keyBy('id');
        $unassigned = HrEmployee::whereNull('department_id')
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt','status');

        if ($unassigned->sum()) {
            $rows[] = ['department' => 'No Department', 'active' => $unassigned['active'] ?? 0, 'probation' => $unassigned['probation'] ?? 0, 'terminated' => $unassigned['terminated'] ?? 0, 'total' => $unassigned->sum()];
        }

        foreach ($byDept as $deptId => $items) {
            $statuses = $items->pluck('cnt','status');
            $rows[] = [
                'department' => $depts[$deptId]?->name ?? 'Unknown',
                'active'     => $statuses['active'] ?? 0,
                'probation'  => $statuses['probation'] ?? 0,
                'terminated' => $statuses['terminated'] ?? 0,
                'total'      => $statuses->sum(),
            ];
        }

        usort($rows, fn($a,$b) => $b['total'] <=> $a['total']);

        return ['rows' => $rows, 'total' => array_sum(array_column($rows,'total'))];
    }

    private function attendanceSummary(Request $request): array
    {
        $month = $request->input('month', now()->format('Y-m'));
        [$year, $mon] = explode('-', $month);

        $records = HrAttendance::whereYear('date', $year)
            ->whereMonth('date', $mon)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt','status');

        $total   = $records->sum();
        $present = ($records['present'] ?? 0) + ($records['late'] ?? 0);
        $absent  = $records['absent'] ?? 0;
        $leave   = $records['leave']   ?? 0;
        $late    = $records['late']    ?? 0;

        return compact('month','total','present','absent','leave','late','records');
    }

    private function leaveUtilization(): array
    {
        $rows = HrLeaveBalance::with('leaveType')
            ->selectRaw('leave_type_id, SUM(allocated) as allocated, SUM(used) as used, SUM(pending) as pending')
            ->groupBy('leave_type_id')
            ->get()
            ->map(fn($b) => [
                'type'      => $b->leaveType?->name ?? '—',
                'allocated' => (float)$b->allocated,
                'used'      => (float)$b->used,
                'pending'   => (float)$b->pending,
                'pct_used'  => $b->allocated > 0 ? round($b->used / $b->allocated * 100, 1) : 0,
            ]);

        return ['rows' => $rows->toArray()];
    }

    private function payrollTrend(): array
    {
        $runs = HrPayrollRun::whereNotIn('status',['draft'])
            ->orderByDesc('period')
            ->limit(12)
            ->get(['period','total_gross','total_deductions','total_net','employee_count','status']);

        return ['rows' => $runs->sortBy('period')->values()->toArray()];
    }

    private function turnover(): array
    {
        $rows = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $label = $month->format('M Y');

            $hires = HrEmployee::whereYear('join_date', $month->year)
                ->whereMonth('join_date', $month->month)
                ->count();

            $exits = HrEmployee::whereYear('end_date', $month->year)
                ->whereMonth('end_date', $month->month)
                ->count();

            $rows[] = compact('label','hires','exits');
        }

        return ['rows' => $rows];
    }

    private function recruitmentFunnel(): array
    {
        $stages = \App\Models\HR\HrApplicant::STAGES;
        $counts = HrApplicant::selectRaw('stage, count(*) as cnt')
            ->groupBy('stage')
            ->pluck('cnt','stage');

        $rows = [];
        foreach ($stages as $s) {
            $rows[] = ['stage' => ucfirst($s), 'count' => $counts[$s] ?? 0];
        }

        return ['rows' => $rows];
    }

    // ── CSV export ─────────────────────────────────────────────────────────
    private function exportCsv(string $report, array $data): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $rows  = $data['rows'] ?? [];
        $filename = "hr-report-{$report}-" . now()->format('Y-m-d') . ".csv";

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if (!empty($rows)) {
                fputcsv($out, array_keys((array) $rows[0]));
                foreach ($rows as $row) {
                    fputcsv($out, (array) $row);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
