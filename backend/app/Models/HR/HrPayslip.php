<?php

namespace App\Models\HR;

use App\Traits\HR\HrAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrPayslip extends Model
{
    use HrAuditable;

    protected $table = 'hr_payslips';

    protected $fillable = [
        'run_id', 'employee_id',
        'base_salary', 'working_days', 'present_days', 'absent_days', 'leave_days',
        'gross_earnings', 'total_deductions', 'attendance_deduction',
        'commission_total', 'net_pay',
        'payment_status', 'payment_method', 'payment_ref', 'paid_at',
        'pdf_path',
    ];

    protected $casts = [
        'base_salary'          => 'decimal:2',
        'gross_earnings'       => 'decimal:2',
        'total_deductions'     => 'decimal:2',
        'attendance_deduction' => 'decimal:2',
        'commission_total'     => 'decimal:2',
        'net_pay'              => 'decimal:2',
        'paid_at'              => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(HrPayrollRun::class, 'run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HrPayslipItem::class, 'payslip_id')->orderBy('sort_order');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(HrCommission::class, 'payslip_id');
    }

    public function earnings(): HasMany
    {
        return $this->items()->where('type', 'earning');
    }

    public function deductions(): HasMany
    {
        return $this->items()->where('type', 'deduction');
    }

    public function getPaymentStatusBadgeClassAttribute(): string
    {
        return $this->payment_status === 'paid'
            ? 'bg-green-100 text-green-700'
            : 'bg-gray-100 text-gray-600';
    }
}
