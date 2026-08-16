<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\EmployeeModuleMetric;
use App\Models\HR\HrEmployee;
use App\Models\HR\ModulePerformanceMetric;
use App\Models\HR\WorkforceAssignment;
use App\Models\Module;
use App\Services\HR\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HrModulePerformanceController extends Controller
{
    // ── Cross-module analytics (Ahmed Hassan view) ────────────────────────────

    public function analytics(Request $request)
    {
        $period = $request->period ?? now()->format('Y-m');
        $periods = $this->recentPeriods(6);

        // Employees with at least one metric record this period
        $employeeIds = EmployeeModuleMetric::where('period', $period)
            ->distinct('employee_id')
            ->pluck('employee_id');

        $employees = HrEmployee::whereIn('id', $employeeIds)
            ->with('activeWorkforceAssignments.module')
            ->orderBy('first_name')
            ->get();

        // For each employee: compute score per module
        $employeeScores = $employees->map(function (HrEmployee $emp) use ($period) {
            $moduleScores = EmployeeModuleMetric::scoresByModule($emp->id, $period);
            $overall = count($moduleScores) ? round(array_sum($moduleScores) / count($moduleScores), 1) : null;
            return [
                'employee'     => $emp,
                'moduleScores' => $moduleScores,
                'overall'      => $overall,
                'modules'      => $emp->activeWorkforceAssignments->map(fn($a) => $a->module)->filter()->keyBy('slug'),
            ];
        })->sortByDesc('overall');

        // Top performer per module
        $modules = Module::where('is_active', true)->orderBy('sort_order')->get();
        $topPerModule = [];
        foreach ($modules as $mod) {
            $best = $this->topForModule($mod->id, $period, 1)->first();
            if ($best) $topPerModule[$mod->slug] = $best;
        }

        // Global best this period
        $globalTop = $employeeScores->take(5);

        // Module averages
        $moduleAverages = $modules->map(function ($mod) use ($period) {
            $avg = EmployeeModuleMetric::where('module_id', $mod->id)
                ->where('period', $period)
                ->whereNotNull('score')
                ->avg('score');
            return [
                'module'  => $mod,
                'average' => $avg ? round($avg, 1) : null,
                'count'   => EmployeeModuleMetric::where('module_id', $mod->id)
                    ->where('period', $period)->distinct('employee_id')->count('employee_id'),
            ];
        })->filter(fn($r) => $r['average'] !== null)->sortByDesc('average');

        return view('hr.performance.module-analytics', compact(
            'period', 'periods', 'employees', 'employeeScores',
            'modules', 'topPerModule', 'globalTop', 'moduleAverages',
        ));
    }

    // ── Module performance (one module, all employees) ────────────────────────

    public function moduleView(string $slug, Request $request)
    {
        $module  = Module::where('slug', $slug)->firstOrFail();
        $period  = $request->period ?? now()->format('Y-m');
        $periods = $this->recentPeriods(6);

        $metrics = ModulePerformanceMetric::where('module_id', $module->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // All employees actively assigned to this module
        $assignedIds = WorkforceAssignment::where('module_id', $module->id)
            ->where('status', 'active')
            ->pluck('employee_id');

        $employees = HrEmployee::whereIn('id', $assignedIds)
            ->orderBy('first_name')->get();

        // Measurements for this period
        $measurements = EmployeeModuleMetric::where('module_id', $module->id)
            ->where('period', $period)
            ->with('metric')
            ->get()
            ->groupBy('employee_id');

        // Team averages per metric
        $teamAvg = EmployeeModuleMetric::teamAverages($module->id, $period);

        // Composite scores per employee
        $compositeScores = [];
        foreach ($assignedIds as $eid) {
            $compositeScores[$eid] = EmployeeModuleMetric::compositeScore($eid, $module->id, $period);
        }
        arsort($compositeScores);

        // Trend: last 6 months team average
        $trend = $this->moduleTrend($module->id, 6);

        return view('hr.performance.module-view', compact(
            'module', 'period', 'periods', 'metrics', 'employees',
            'measurements', 'teamAvg', 'compositeScores', 'trend',
        ));
    }

    // ── Employee performance in a module (detail + entry form) ───────────────

    public function employeeView(string $slug, HrEmployee $employee, Request $request)
    {
        $module  = Module::where('slug', $slug)->firstOrFail();
        $period  = $request->period ?? now()->format('Y-m');
        $periods = $this->recentPeriods(6);

        $metrics = ModulePerformanceMetric::where('module_id', $module->id)
            ->where('is_active', true)->orderBy('sort_order')->get();

        $existing = EmployeeModuleMetric::where('employee_id', $employee->id)
            ->where('module_id', $module->id)->where('period', $period)
            ->with('metric')->get()->keyBy('metric_id');

        $composite = EmployeeModuleMetric::compositeScore($employee->id, $module->id, $period);

        // History: last 6 periods
        $history = EmployeeModuleMetric::where('employee_id', $employee->id)
            ->where('module_id', $module->id)
            ->select('period', DB::raw('AVG(score) as avg_score'))
            ->groupBy('period')->orderBy('period')->limit(6)->get();

        // Cross-module scores this period (for the "Ahmed Hassan" profile view)
        $allModuleScores = EmployeeModuleMetric::scoresByModule($employee->id, $period);

        // Link to existing HR performance records (non-destructive integration)
        $performanceCycles = null;
        if (class_exists(\App\Models\HR\HrPerformanceCycle::class)) {
            try {
                $performanceCycles = \App\Models\HR\HrPerformanceCycle::where('is_active', true)
                    ->latest()->limit(3)->get();
            } catch (\Throwable) {}
        }

        return view('hr.performance.employee-module', compact(
            'module', 'employee', 'period', 'periods', 'metrics',
            'existing', 'composite', 'history', 'allModuleScores', 'performanceCycles',
        ));
    }

    // ── Record / update metrics (POST) ────────────────────────────────────────

    public function record(Request $request, string $slug, HrEmployee $employee)
    {
        $module = Module::where('slug', $slug)->firstOrFail();

        $request->validate([
            'period'          => ['required', 'date_format:Y-m'],
            'metrics'         => ['required', 'array'],
            'metrics.*.value' => ['required', 'numeric', 'min:0'],
            'metrics.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $period  = $request->period;
        $metrics = ModulePerformanceMetric::where('module_id', $module->id)
            ->where('is_active', true)->get()->keyBy('id');

        DB::transaction(function () use ($request, $employee, $module, $period, $metrics) {
            foreach ($request->metrics as $metricId => $data) {
                $metric = $metrics->get($metricId);
                if (!$metric) continue;

                $actual = (float) $data['value'];
                $score  = $metric->computeScore($actual);

                EmployeeModuleMetric::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'module_id'   => $module->id,
                        'metric_id'   => $metricId,
                        'period'      => $period,
                    ],
                    [
                        'period_type'  => 'monthly',
                        'actual_value' => $actual,
                        'target_value' => $metric->target_value,
                        'score'        => $score,
                        'recorded_by'  => Auth::guard('hr')->id(),
                        'notes'        => $data['notes'] ?? null,
                    ]
                );
            }
        });

        AuditService::log('performance.metrics_recorded', $employee, null, [
            'module' => $module->slug, 'period' => $period,
        ]);

        $composite = EmployeeModuleMetric::compositeScore($employee->id, $module->id, $period);

        return back()->with('success',
            "{$employee->full_name}'s {$module->name} performance recorded. Composite score: {$composite}%"
        );
    }

    // ── API: employee multi-module scores (for show page widget) ─────────────

    public function employeeScores(HrEmployee $employee, Request $request)
    {
        $period = $request->period ?? now()->format('Y-m');
        $scores = EmployeeModuleMetric::scoresByModule($employee->id, $period);

        // Enrich with module metadata
        $modules = Module::whereIn('slug', array_keys($scores))->get()->keyBy('slug');
        $result  = [];
        foreach ($scores as $slug => $score) {
            $mod = $modules->get($slug);
            $result[] = [
                'module_slug'  => $slug,
                'module_name'  => $mod?->name ?? $slug,
                'module_color' => $mod?->color ?? '#1B1444',
                'score'        => $score,
                'grade'        => $this->grade($score),
            ];
        }

        return response()->json(['period' => $period, 'scores' => $result]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function recentPeriods(int $n): array
    {
        $periods = [];
        for ($i = 0; $i < $n; $i++) {
            $d = now()->subMonths($i);
            $periods[$d->format('Y-m')] = $d->format('F Y');
        }
        return $periods;
    }

    private function moduleTrend(int $moduleId, int $months): array
    {
        $trend = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $period = now()->subMonths($i)->format('Y-m');
            $avg    = EmployeeModuleMetric::where('module_id', $moduleId)
                ->where('period', $period)->whereNotNull('score')->avg('score');
            $trend[$period] = $avg ? round($avg, 1) : null;
        }
        return $trend;
    }

    private function topForModule(int $moduleId, string $period, int $limit): \Illuminate\Support\Collection
    {
        return EmployeeModuleMetric::where('module_id', $moduleId)
            ->where('period', $period)
            ->whereNotNull('score')
            ->select('employee_id', DB::raw('AVG(score) as avg_score'))
            ->groupBy('employee_id')
            ->orderByDesc('avg_score')
            ->limit($limit)
            ->with('employee:id,first_name,last_name,photo')
            ->get();
    }

    private function grade(float $score): string
    {
        return match(true) {
            $score >= 95 => 'A+',
            $score >= 90 => 'A',
            $score >= 85 => 'B+',
            $score >= 80 => 'B',
            $score >= 75 => 'C+',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default      => 'F',
        };
    }
}
