<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;

class HrGoal extends Model
{
    protected $table = 'hr_goals';

    protected $fillable = [
        'cycle_id', 'employee_id', 'title', 'description',
        'weight', 'target_value', 'achieved_value', 'status',
    ];

    protected $casts = ['weight' => 'decimal:2'];

    public function cycle()    { return $this->belongsTo(HrPerformanceCycle::class, 'cycle_id'); }
    public function employee() { return $this->belongsTo(HrEmployee::class); }
}
