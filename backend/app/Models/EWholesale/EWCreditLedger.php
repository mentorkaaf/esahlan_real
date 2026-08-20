<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EWCreditLedger extends Model
{
    protected $table = 'ewholesale_credit_ledger';

    protected $fillable = [
        'credit_account_id','order_id','type','amount','balance_after','due_date','note',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'balance_after'=> 'decimal:2',
        'due_date'     => 'date',
    ];

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(EWCreditAccount::class, 'credit_account_id');
    }
}
