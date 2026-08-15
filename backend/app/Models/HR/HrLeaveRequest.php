<?php

namespace App\Models\HR;

use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrLeaveRequest extends Model
{
    use HrAuditable, SoftDeletes;

    protected $table = 'hr_leave_requests';

    protected $fillable = [
        'employee_id', 'leave_type_id',
        'start_date', 'end_date', 'working_days',
        'reason', 'document_path', 'status',
        'decided_by', 'decision_note', 'decided_at',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'decided_at'   => 'datetime',
        'working_days' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(HrLeaveType::class, 'leave_type_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(HrStaff::class, 'decided_by');
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'approved'  => 'bg-green-100 text-green-700',
            'rejected'  => 'bg-red-100 text-red-700',
            'cancelled' => 'bg-gray-100 text-gray-600',
            default     => 'bg-yellow-100 text-yellow-700',  // pending
        };
    }
}
