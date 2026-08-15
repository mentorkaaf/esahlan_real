<?php

namespace App\Services\HR;

use App\Models\HR\HrGoal;
use App\Models\HR\HrPerformanceCycle;
use App\Models\HR\HrReview;
use App\Models\HR\HrCommission;

class PerformanceService
{
    /**
     * Validate that an employee's goals for a cycle sum to 100.
     */
    public static function validateWeights(int $cycleId, int $employeeId, ?int $excludeGoalId = null): bool
    {
        $query = HrGoal::where('cycle_id', $cycleId)->where('employee_id', $employeeId);
        if ($excludeGoalId) $query->where('id', '!=', $excludeGoalId);

        $total = $query->sum('weight');
        return $total <= 100;
    }

    /**
     * Compute and save overall_score for a review.
     */
    public static function computeAndSaveScore(HrReview $review): void
    {
        $goals = HrGoal::where('cycle_id', $review->cycle_id)
            ->where('employee_id', $review->employee_id)
            ->pluck('weight', 'id')
            ->toArray();

        $scores     = $review->scores ?? [];
        $compScores = $review->competency_scores ?? [];

        $overall = HrReview::computeOverall($scores, $goals, $compScores);
        $review->update(['overall_score' => $overall]);
    }

    /**
     * Import achieved values from commissions for a cycle's period.
     * Matches Marketing goals by type 'customer_signups' or 'vendor_signups'.
     */
    public static function importFromCommissions(HrPerformanceCycle $cycle): int
    {
        $updated = 0;

        $goals = HrGoal::where('cycle_id', $cycle->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->get();

        foreach ($goals as $goal) {
            // Find commission matching employee + period within cycle dates
            $commissions = HrCommission::where('employee_id', $goal->employee_id)
                ->where(function ($q) use ($cycle) {
                    // period format YYYY-MM, check within cycle range
                    $q->whereRaw("STR_TO_DATE(CONCAT(period, '-01'), '%Y-%m-%d') >= ?", [$cycle->period_start])
                      ->whereRaw("STR_TO_DATE(CONCAT(period, '-01'), '%Y-%m-%d') <= ?", [$cycle->period_end]);
                })
                ->whereIn('status', ['approved', 'included'])
                ->get();

            $totalAchieved = $commissions->sum('achieved');

            if ($totalAchieved > 0) {
                $goal->update([
                    'achieved_value' => $totalAchieved,
                    'status'         => 'in_progress',
                ]);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Build cycle ranking: employees ordered by overall_score desc.
     */
    public static function cycleRanking(HrPerformanceCycle $cycle): \Illuminate\Support\Collection
    {
        return HrReview::where('cycle_id', $cycle->id)
            ->where('status', 'submitted')
            ->with(['employee.department'])
            ->orderByDesc('overall_score')
            ->get();
    }
}
