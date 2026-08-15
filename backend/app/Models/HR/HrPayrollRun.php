<?php

namespace App\Models\HR;

use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrPayrollRun extends Model
{
    use HrAuditable;

    protected $table = 'hr_payroll_runs';

    protected $fillable = [
        'period', 'label', 'status',
        'total_gross', 'total_deductions', 'total_net', 'employee_count',
        'generated_by', 'submitted_by', 'approved_by',
        'submitted_at', 'approved_at', 'paid_at', 'rejection_note',
    ];

    protected $casts = [
        'submitted_at'     => 'datetime',
        'approved_at'      => 'datetime',
        'paid_at'          => 'datetime',
        'total_gross'      => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net'        => 'decimal:2',
    ];

    public function payslips(): HasMany
    {
        return $this->hasMany(HrPayslip::class, 'run_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(HrStaff::class, 'generated_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(HrStaff::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(HrStaff::class, 'approved_by');
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'draft'            => 'bg-gray-100 text-gray-600',
            'pending_approval' => 'bg-yellow-100 text-yellow-700',
            'approved'         => 'bg-blue-100 text-blue-700',
            'paid'             => 'bg-green-100 text-green-700',
            default            => 'bg-gray-100 text-gray-500',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft'            => 'Draft',
            'pending_approval' => 'Pending Approval',
            'approved'         => 'Approved',
            'paid'             => 'Paid',
            default            => ucfirst($this->status),
        };
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function canSubmit(): bool
    {
        return $this->status === 'draft';
    }

    public function canApprove(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function canMarkPaid(): bool
    {
        return $this->status === 'approved';
    }
}
