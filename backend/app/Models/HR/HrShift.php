<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrShift extends Model
{
    protected $table = 'hr_shifts';

    protected $fillable = [
        'name', 'start_time', 'end_time',
        'grace_minutes', 'break_minutes', 'is_active', 'notes',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'grace_minutes' => 'integer',
        'break_minutes' => 'integer',
    ];

    public function employeeShifts(): HasMany
    {
        return $this->hasMany(HrEmployeeShift::class, 'shift_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(HrAttendance::class, 'shift_id');
    }

    /** Worked hours in this shift (excl. break) */
    public function getShiftHoursAttribute(): float
    {
        [$sh, $sm] = explode(':', $this->start_time);
        [$eh, $em] = explode(':', $this->end_time);
        $mins = ($eh * 60 + $em) - ($sh * 60 + $sm) - $this->break_minutes;
        return round($mins / 60, 2);
    }
}
