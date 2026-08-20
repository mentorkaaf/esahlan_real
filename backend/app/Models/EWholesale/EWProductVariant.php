<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class EWProductVariant extends Model
{
    protected $table = 'ewholesale_product_variants';

    protected $fillable = [
        'product_id','attributes','sku','stock_qty','weight_kg','volume_cbm','is_default','is_active',
    ];

    protected $casts = [
        'attributes' => 'array',
        'stock_qty'  => 'decimal:2',
        'weight_kg'  => 'decimal:3',
        'volume_cbm' => 'decimal:4',
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function product(): BelongsTo     { return $this->belongsTo(EWProduct::class, 'product_id'); }

    /** Price tiers specific to this variant (overrides product-level tiers when present) */
    public function priceTiers(): HasMany
    {
        return $this->hasMany(EWPriceTier::class, 'variant_id')->orderBy('min_qty');
    }

    public function isInStock(): bool        { return $this->stock_qty > 0; }

    /** Human-readable attribute string: "L / Blue" */
    public function attributeLabel(): string
    {
        if (empty($this->attributes)) return 'Default';
        return implode(' / ', array_values($this->attributes));
    }
}
