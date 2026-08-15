<?php

namespace App\Models\HR;

use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrAttendance extends Model
{
    use HrAuditable;

    protected $table = 'hr_attendance';

    protected $fillable = [
        'employee_id', 'shift_id', 'date',
        'check_in', 'check_out', 'status',
        'late_minutes', 'overtime_minutes',
        'note', 'is_manual', 'corrected_by',
    ];

    protected $casts = [
        'date'             => 'date',
        'is_manual'        => 'boolean',
        'late_minutes'     => 'integer',
        'overtime_minutes' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(HrShift::class, 'shift_id');
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(HrStaff::class, 'corrected_by');
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'present'  => 'green',
            'late'     => 'yellow',
            'absent'   => 'red',
            'half_day' => 'orange',
            'leave'    => 'blue',
            'holiday'  => 'purple',
            'weekend'  => 'gray',
            default    => 'gray',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status_color) {
            'green'  => 'bg-green-100 text-green-700',
            'yellow' => 'bg-yellow-100 text-yellow-700',
            'red'    => 'bg-red-100 text-red-700',
            'orange' => 'bg-orange-100 text-orange-700',
            'blue'   => 'bg-blue-100 text-blue-700',
            'purple' => 'bg-purple-100 text-purple-700',
            default  => 'bg-gray-100 text-gray-600',
        };
    }
}
