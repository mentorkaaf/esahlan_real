<?php
namespace App\Models\EGrocery;

use Illuminate\Database\Eloquent\Model;

class EGroceryBanner extends Model
{
    protected $table = 'egrocery_banners';
    protected $fillable = [
        'title','image','placement','link_type','link_value','sort_order','starts_at','ends_at','is_active',
    ];
    protected $casts = [
        'is_active'  => 'boolean',
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true)
                 ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                 ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
