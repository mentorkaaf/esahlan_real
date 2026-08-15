<?php

namespace App\Models\HR;

use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrCommission extends Model
{
    use HrAuditable, SoftDeletes;

    protected $table = 'hr_commissions';

    protected $fillable = [
        'employee_id', 'period', 'type', 'description',
        'target', 'achieved', 'rate', 'amount',
        'status', 'approved_by', 'approved_at', 'payslip_id', 'note',
    ];

    protected $casts = [
        'rate'        => 'decimal:4',
        'amount'      => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(HrStaff::class, 'approved_by');
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(HrPayslip::class, 'payslip_id');
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'approved'  => 'bg-green-100 text-green-700',
            'rejected'  => 'bg-red-100 text-red-700',
            'included'  => 'bg-blue-100 text-blue-700',
            default     => 'bg-yellow-100 text-yellow-700',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'customer_signups' => 'Customer Sign-ups',
            'vendor_signups'   => 'Vendor Sign-ups',
            default            => ucwords(str_replace('_', ' ', $this->type)),
        };
    }
}
