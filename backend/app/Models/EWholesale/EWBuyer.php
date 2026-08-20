<?php

namespace App\Models\EWholesale;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class EWBuyer extends Model
{
    protected $table = 'ewholesale_buyers';

    protected $fillable = [
        'user_id','business_name','business_type',
        'license_no','license_doc','tax_id',
        'kyb_status','approved_at','default_payment_term_id',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo              { return $this->belongsTo(User::class); }
    public function creditAccount(): HasOne        { return $this->hasOne(EWCreditAccount::class, 'buyer_id'); }
    public function priceListLink(): HasOne        { return $this->hasOne(EWBuyerPriceList::class, 'buyer_id'); }
    public function orders(): HasMany              { return $this->hasMany(EWOrder::class, 'buyer_id'); }
    public function rfqs(): HasMany                { return $this->hasMany(EWRfq::class, 'buyer_id'); }

    public function isApproved(): bool             { return $this->kyb_status === 'approved'; }
    public function hasCreditActive(): bool        { return $this->creditAccount?->status === 'active'; }

    public function priceList(): ?EWPriceList
    {
        return $this->priceListLink?->priceList;
    }
}
