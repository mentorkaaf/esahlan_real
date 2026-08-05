<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GlobalProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id','name','slug','description','short_description',
        'price','compare_price','cost_price','sku','stock','track_stock',
        'type','supplier_name','supplier_product_id','weight_kg','origin_country',
        'thumbnail','is_active','is_featured','is_new_arrival','is_bestseller',
        'sold_count','rating','review_count','tags','shipping_info',
    ];

    protected $casts = [
        'price'          => 'float',
        'compare_price'  => 'float',
        'cost_price'     => 'float',
        'weight_kg'      => 'float',
        'is_active'      => 'boolean',
        'is_featured'    => 'boolean',
        'is_new_arrival' => 'boolean',
        'is_bestseller'  => 'boolean',
        'track_stock'    => 'boolean',
        'tags'           => 'array',
        'shipping_info'  => 'array',
    ];

    public function category()    { return $this->belongsTo(GlobalCategory::class); }
    public function images()      { return $this->hasMany(GlobalProductImage::class, 'product_id')->orderBy('sort_order'); }
    public function variants()    { return $this->hasMany(GlobalProductVariant::class, 'product_id'); }
    public function reviews()     { return $this->hasMany(GlobalReview::class, 'global_product_id'); }

    public function getDiscountPercentAttribute(): int
    {
        if (!$this->compare_price || $this->compare_price <= $this->price) return 0;
        return (int) round((($this->compare_price - $this->price) / $this->compare_price) * 100);
    }

    public function getInStockAttribute(): bool
    {
        return !$this->track_stock || $this->stock > 0;
    }
}
