<?php
namespace App\Models\EGrocery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class EGroceryProductVariant extends Model
{
    protected $table = 'egrocery_product_variants';

    protected $fillable = [
        'product_id','label','unit_id','unit_qty','price','compare_price',
        'cost','sku','stock_qty','low_stock_threshold','is_default','sort_order','is_active',
    ];

    protected $casts = [
        'unit_qty'            => 'decimal:3',
        'price'               => 'decimal:2',
        'compare_price'       => 'decimal:2',
        'cost'                => 'decimal:2',
        'stock_qty'           => 'decimal:3',
        'low_stock_threshold' => 'decimal:3',
        'is_default'          => 'boolean',
        'is_active'           => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(EGroceryProduct::class, 'product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(EGroceryUnit::class, 'unit_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(EGroceryStockMovement::class, 'variant_id');
    }

    public function flashDeal(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EGroceryFlashDeal::class, 'variant_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isLowStock(): bool
    {
        return $this->stock_qty <= $this->low_stock_threshold;
    }

    public function isInStock(): bool
    {
        return $this->stock_qty > 0;
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeInStock($q){ return $q->where('stock_qty', '>', 0); }
}
