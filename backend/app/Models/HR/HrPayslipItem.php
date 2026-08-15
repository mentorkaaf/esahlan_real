<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPayslipItem extends Model
{
    protected $table = 'hr_payslip_items';

    protected $fillable = [
        'payslip_id', 'label', 'type', 'source', 'amount', 'note', 'sort_order',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(HrPayslip::class, 'payslip_id');
    }
}
