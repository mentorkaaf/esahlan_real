<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrLeaveBalance extends Model
{
    protected $table = 'hr_leave_balances';

    protected $fillable = [
        'employee_id', 'leave_type_id', 'year',
        'allocated', 'used', 'pending',
    ];

    protected $casts = [
        'allocated' => 'float',
        'used'      => 'float',
        'pending'   => 'float',
        'year'      => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(HrLeaveType::class, 'leave_type_id');
    }

    public function getAvailableAttribute(): float
    {
        return max(0, $this->allocated - $this->used - $this->pending);
    }
}
