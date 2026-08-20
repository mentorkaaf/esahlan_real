<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EWPriceTier extends Model
{
    protected $table = 'ewholesale_price_tiers';

    protected $fillable = ['product_id','variant_id','min_qty','max_qty','unit_price'];

    protected $casts = [
        'min_qty'    => 'decimal:2',
        'max_qty'    => 'decimal:2',
        'unit_price' => 'decimal:2',
    ];

    public function product(): BelongsTo { return $this->belongsTo(EWProduct::class, 'product_id'); }
    public function variant(): BelongsTo { return $this->belongsTo(EWProductVariant::class, 'variant_id'); }

    /** Formatted range label: "10–49 cartons @ $22.00" */
    public function rangeLabel(string $unit = 'units'): string
    {
        $range = $this->max_qty
            ? "{$this->min_qty}–{$this->max_qty}"
            : "{$this->min_qty}+";
        return "{$range} {$unit} @ \${$this->unit_price}";
    }
}
