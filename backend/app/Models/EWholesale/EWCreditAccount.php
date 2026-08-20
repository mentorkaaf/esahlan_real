<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class EWCreditAccount extends Model
{
    protected $table = 'ewholesale_credit_accounts';

    protected $fillable = [
        'buyer_id','credit_limit','balance_used','term','status','approved_by',
    ];

    protected $casts = [
        'credit_limit'  => 'decimal:2',
        'balance_used'  => 'decimal:2',
    ];

    public function buyer(): BelongsTo    { return $this->belongsTo(EWBuyer::class, 'buyer_id'); }
    public function ledger(): HasMany     { return $this->hasMany(EWCreditLedger::class, 'credit_account_id'); }

    public function availableCredit(): float
    {
        return max(0, (float)$this->credit_limit - (float)$this->balance_used);
    }

    public function isActive(): bool     { return $this->status === 'active'; }
}
