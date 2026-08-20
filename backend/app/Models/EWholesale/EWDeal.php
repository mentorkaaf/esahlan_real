<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EWDeal extends Model
{
    protected $table = 'ewholesale_deals';

    protected $fillable = [
        'product_id','deal_price_percent_off','min_qty','starts_at','ends_at','qty_limit','qty_sold','is_active',
    ];

    protected $casts = [
        'deal_price_percent_off' => 'decimal:2',
        'min_qty'                => 'decimal:2',
        'starts_at'              => 'datetime',
        'ends_at'                => 'datetime',
        'is_active'              => 'boolean',
    ];

    public function product(): BelongsTo { return $this->belongsTo(EWProduct::class, 'product_id'); }

    public function isActive(): bool
    {
        return $this->is_active
            && now()->between($this->starts_at, $this->ends_at)
            && ($this->qty_limit === null || $this->qty_sold < $this->qty_limit);
    }

    /** Apply percent-off to a base price */
    public function applyTo(float $basePrice): float
    {
        return round($basePrice * (1 - $this->deal_price_percent_off / 100), 2);
    }
}
