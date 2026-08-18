<?php
namespace App\Models\EGrocery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class EGroceryCategory extends Model
{
    protected $table = 'egrocery_categories';

    protected $fillable = [
        'parent_id','name','name_so','slug','icon','image','sort_order','is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(EGroceryProduct::class, 'category_id');
    }

    /** Active children with product count */
    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true);
    }
}
