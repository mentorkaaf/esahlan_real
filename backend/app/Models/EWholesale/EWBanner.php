<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;

class EWBanner extends Model
{
    protected $table = 'ewholesale_banners';

    protected $fillable = [
        'title','subtitle','image','cta_label','cta_url','bg_color',
        'sort_order','is_active','starts_at','ends_at',
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
