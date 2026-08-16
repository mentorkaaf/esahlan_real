<?php

namespace App\Models\HR;

use App\Models\Module;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EmployeeModuleMetric extends Model
{
    protected $table = 'employee_module_metrics';

    protected $fillable = [
        'employee_id', 'module_id', 'metric_id', 'period', 'period_type',
        'actual_value', 'target_value', 'score', 'recorded_by', 'notes',
    ];

    protected $casts = [
        'actual_value'  => 'decimal:2',
        'target_value'  => 'decimal:2',
        'score'         => 'decimal:2',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function employee()   { return $this->belongsTo(HrEmployee::class, 'employee_id'); }
    public function module()     { return $this->belongsTo(Module::class); }
    public function metric()     { return $this->belongsTo(ModulePerformanceMetric::class, 'metric_id'); }
    public function recordedBy() { return $this->belongsTo(\App\Models\HR\HrStaff::class, 'recorded_by'); }

    // ── Statics ───────────────────────────────────────────────────────────────

    /**
     * Composite module performance score for an employee in a given module+period.
     * Returns 0–100 weighted average.
     */
    public static function compositeScore(int $employeeId, int $moduleId, string $period): float
    {
        $rows = static::where('employee_id', $employeeId)
            ->where('module_id', $moduleId)
            ->where('period', $period)
            ->whereNotNull('score')
            ->with('metric:id,weight')
            ->get();

        if ($rows->isEmpty()) return 0;

        $totalWeight = $rows->sum(fn($r) => $r->metric?->weight ?? 20);
        if ($totalWeight == 0) return 0;

        $weighted = $rows->sum(fn($r) => ($r->score ?? 0) * ($r->metric?->weight ?? 20));
        return round($weighted / $totalWeight, 1);
    }

    /**
     * Get all metric scores for an employee grouped by module for a period.
     * Returns ['efood' => 92.0, 'ewholesale' => 84.0, ...]
     */
    public static function scoresByModule(int $employeeId, string $period): array
    {
        $rows = static::where('employee_id', $employeeId)
            ->where('period', $period)
            ->whereNotNull('score')
            ->with(['metric:id,weight,module_id', 'module:id,slug'])
            ->get();

        $byModule = [];
        foreach ($rows as $r) {
            $slug = $r->module?->slug ?? 'unknown';
            $byModule[$slug][] = ['score' => $r->score, 'weight' => $r->metric?->weight ?? 20];
        }

        $result = [];
        foreach ($byModule as $slug => $items) {
            $totalW   = array_sum(array_column($items, 'weight'));
            $weighted = array_sum(array_map(fn($i) => $i['score'] * $i['weight'], $items));
            $result[$slug] = $totalW > 0 ? round($weighted / $totalW, 1) : 0;
        }

        return $result;
    }

    /**
     * Team average score per metric for a module+period.
     */
    public static function teamAverages(int $moduleId, string $period): array
    {
        return static::where('module_id', $moduleId)
            ->where('period', $period)
            ->select('metric_id',
                DB::raw('AVG(score) as avg_score'),
                DB::raw('MIN(score) as min_score'),
                DB::raw('MAX(score) as max_score'),
                DB::raw('COUNT(DISTINCT employee_id) as employee_count'))
            ->groupBy('metric_id')
            ->get()
            ->keyBy('metric_id')
            ->toArray();
    }

    /**
     * Top N performers in a module for a period (by composite score).
     */
    public static function topPerformers(int $moduleId, string $period, int $limit = 5): \Illuminate\Support\Collection
    {
        return static::where('module_id', $moduleId)
            ->where('period', $period)
            ->whereNotNull('score')
            ->select('employee_id',
                DB::raw('SUM(score * (SELECT weight FROM module_performance_metrics WHERE id = metric_id)) / '.
                        'NULLIF(SUM(SELECT weight FROM module_performance_metrics WHERE id = metric_id), 0) as composite'))
            ->groupBy('employee_id')
            ->orderByDesc('composite')
            ->limit($limit)
            ->with('employee:id,first_name,last_name,photo')
            ->get();
    }
}
