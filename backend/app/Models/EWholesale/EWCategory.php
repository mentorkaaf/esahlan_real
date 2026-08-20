<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class EWCategory extends Model
{
    use HasFactory;
    protected $table = 'ewholesale_categories';

    protected $fillable = [
        'parent_id','name','name_so','slug','icon','image','sort_order','is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function parent(): BelongsTo    { return $this->belongsTo(EWCategory::class, 'parent_id'); }
    public function children(): HasMany    { return $this->hasMany(EWCategory::class, 'parent_id'); }
    public function products(): HasMany    { return $this->hasMany(EWProduct::class, 'category_id'); }

    public function scopeActive($q)        { return $q->where('is_active', true); }
    public function scopeRoots($q)         { return $q->whereNull('parent_id'); }

    /** Recursive IDs for category + all descendants (for product filtering) */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->descendantIds());
        }
        return $ids;
    }
}
