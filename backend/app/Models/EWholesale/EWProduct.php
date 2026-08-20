<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class EWProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ewholesale_products';

    protected $fillable = [
        'supplier_id','category_id','name','name_so','slug','description',
        'images','video_url','unit','units_per_pack','moq','lead_time_days',
        'origin_country','brand','specs','status','is_featured',
        'orders_count','min_price','max_price',
    ];

    protected $casts = [
        'images'       => 'array',
        'specs'        => 'array',
        'moq'          => 'decimal:2',
        'min_price'    => 'decimal:2',
        'max_price'    => 'decimal:2',
        'is_featured'  => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────────────────────

    public function supplier(): BelongsTo         { return $this->belongsTo(EWSupplier::class, 'supplier_id'); }
    public function category(): BelongsTo         { return $this->belongsTo(EWCategory::class, 'category_id'); }
    public function variants(): HasMany           { return $this->hasMany(EWProductVariant::class, 'product_id')->orderBy('id'); }
    public function activeVariants(): HasMany     { return $this->variants()->where('is_active', true); }
    public function defaultVariant(): HasOne      { return $this->hasOne(EWProductVariant::class, 'product_id')->where('is_default', true); }
    public function priceTiers(): HasMany         { return $this->hasMany(EWPriceTier::class, 'product_id')->orderBy('min_qty'); }
    public function deals(): HasMany              { return $this->hasMany(EWDeal::class, 'product_id'); }

    public function activeDeals(): HasMany
    {
        return $this->hasMany(EWDeal::class, 'product_id')
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive($q)               { return $q->where('status', 'active'); }
    public function scopeFeatured($q)             { return $q->where('is_featured', true); }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function firstImage(): ?string
    {
        $imgs = $this->images ?? [];
        return !empty($imgs) ? (str_starts_with($imgs[0], 'http') ? $imgs[0] : url('/api/v1/media?f=' . ltrim($imgs[0], '/'))) : null;
    }

    /** Sync min_price / max_price from attached price tiers */
    public function syncPriceRange(): void
    {
        $tiers = $this->priceTiers()->pluck('unit_price');
        if ($tiers->isNotEmpty()) {
            $this->update(['min_price' => $tiers->min(), 'max_price' => $tiers->max()]);
        }
    }
}
