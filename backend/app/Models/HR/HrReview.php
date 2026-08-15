<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;

class HrReview extends Model
{
    protected $table = 'hr_reviews';

    protected $fillable = [
        'cycle_id', 'employee_id', 'reviewer_id',
        'scores', 'competency_scores', 'overall_score',
        'status', 'notes', 'submitted_at',
    ];

    protected $casts = [
        'scores'            => 'array',
        'competency_scores' => 'array',
        'overall_score'     => 'decimal:2',
        'submitted_at'      => 'datetime',
    ];

    public function cycle()    { return $this->belongsTo(HrPerformanceCycle::class, 'cycle_id'); }
    public function employee() { return $this->belongsTo(HrEmployee::class); }
    public function reviewer() { return $this->belongsTo(HrStaff::class, 'reviewer_id'); }

    /**
     * Compute overall_score = weighted average from goals + competency avg (20% weight)
     */
    public static function computeOverall(array $scores, array $goalWeights, array $competencyScores): float
    {
        $goalScore = 0.0;
        $totalWeight = 0.0;
        foreach ($goalWeights as $goalId => $weight) {
            $s = $scores[$goalId] ?? null;
            if ($s !== null) {
                $goalScore   += ($s / 5) * $weight;
                $totalWeight += $weight;
            }
        }
        $goalPct = $totalWeight > 0 ? ($goalScore / $totalWeight) * 5 : 0;

        if (count($competencyScores) > 0) {
            $compAvg = array_sum($competencyScores) / count($competencyScores);
            // 80% goals, 20% competencies
            return round($goalPct * 0.8 + $compAvg * 0.2, 2);
        }

        return round($goalPct, 2);
    }

    public function getScoreBadgeClass(): string
    {
        $s = (float) $this->overall_score;
        if ($s >= 4)    return 'bg-green-100 text-green-700';
        if ($s >= 3)    return 'bg-yellow-100 text-yellow-700';
        if ($s >= 2)    return 'bg-orange-100 text-orange-700';
        return 'bg-red-100 text-red-600';
    }
}
