<?php

namespace App\Models\HR;

use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Model;

class HrPerformanceCycle extends Model
{
    use HrAuditable;

    protected $table = 'hr_performance_cycles';

    protected $fillable = ['name', 'period_start', 'period_end', 'status', 'created_by'];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
    ];

    public function creator() { return $this->belongsTo(HrStaff::class, 'created_by'); }
    public function goals()   { return $this->hasMany(HrGoal::class, 'cycle_id'); }
    public function reviews() { return $this->hasMany(HrReview::class, 'cycle_id'); }

    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            'active' => 'bg-green-100 text-green-700',
            'closed' => 'bg-gray-100 text-gray-500',
            default  => 'bg-blue-100 text-blue-700',
        };
    }
}
