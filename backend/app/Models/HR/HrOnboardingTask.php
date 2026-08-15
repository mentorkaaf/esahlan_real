<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;

class HrOnboardingTask extends Model
{
    protected $table = 'hr_onboarding_tasks';

    protected $fillable = [
        'employee_id', 'title', 'description', 'due_date',
        'assigned_to', 'status', 'completed_at', 'sort_order',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'completed_at' => 'datetime',
    ];

    public function employee()  { return $this->belongsTo(HrEmployee::class); }
    public function assignee()  { return $this->belongsTo(HrStaff::class, 'assigned_to'); }
}
