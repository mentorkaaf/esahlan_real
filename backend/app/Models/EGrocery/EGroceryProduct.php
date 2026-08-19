<?php
namespace App\Models\EGrocery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne, BelongsToMany};

class EGroceryProduct extends Model
{
    protected $table = 'egrocery_products';

    protected $fillable = [
        'category_id','brand_id','name','name_so','slug','description',
        'images','base_unit_id','is_weight_based','tags','barcode',
        'is_active','is_featured','avg_rating','orders_count',
    ];

    protected $casts = [
        'images'          => 'array',
        'tags'            => 'array',
        'is_weight_based' => 'boolean',
        'is_active'       => 'boolean',
        'is_featured'     => 'boolean',
        'avg_rating'      => 'decimal:2',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(EGroceryCategory::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(EGroceryBrand::class, 'brand_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(EGroceryUnit::class, 'base_unit_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(EGroceryProductVariant::class, 'product_id')
                    ->orderBy('sort_order');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(EGroceryProductVariant::class, 'product_id')
                    ->where('is_default', true);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(EGroceryReview::class, 'product_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(EGroceryFavorite::class, 'product_id');
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(EGrocerySection::class, 'egrocery_section_products', 'product_id', 'section_id')
                    ->withPivot('sort_order');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($q)     { return $q->where('is_active', true); }
    public function scopeFeatured($q)   { return $q->where('is_featured', true); }
    public function scopeInStock($q)
    {
        return $q->whereHas('variants', fn($v) => $v->where('stock_qty', '>', 0)->where('is_active', true));
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function getFirstImageAttribute(): ?string
    {
        if (empty($this->images)) return null;
        $path = $this->images[0];
        if (str_starts_with($path, 'http')) return $path;
        return url('/api/v1/media?f=' . ltrim($path, '/'));
    }
}
