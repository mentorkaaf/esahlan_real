<?php
namespace App\Models\EGrocery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

class EGrocerySection extends Model
{
    protected $table = 'egrocery_sections';
    protected $fillable = [
        'title','title_so','type','layout','category_id','sort_order','is_active','starts_at','ends_at',
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(EGroceryCategory::class, 'category_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(EGroceryProduct::class, 'egrocery_section_products', 'section_id', 'product_id')
                    ->withPivot('sort_order')
                    ->orderBy('egrocery_section_products.sort_order');
    }

    public function flashDeals(): HasMany
    {
        return $this->hasMany(EGroceryFlashDeal::class, 'section_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)
                 ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                 ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
