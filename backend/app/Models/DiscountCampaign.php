<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountCampaign extends Model
{
    protected $fillable = [
        'vendor_id', 'name', 'description',
        'discount_type', 'discount_value',
        'per_vendor_discounts',
        'starts_at', 'ends_at',
        'badge_text', 'badge_color',
        'apply_to_all', 'category_id', 'is_active',
        'internal_notes', 'created_by',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'apply_to_all'         => 'boolean',
        'starts_at'            => 'datetime',
        'ends_at'              => 'datetime',
        'discount_value'       => 'float',
        'per_vendor_discounts' => 'array',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class);
    }

    /** Is the campaign currently live? */
    public function isLive(): bool
    {
        $now = now();
        return $this->is_active
            && $this->starts_at <= $now
            && $this->ends_at   >= $now;
    }

    /** Status label: active | scheduled | expired */
    public function getStatusAttribute(): string
    {
        $now = now();
        if (!$this->is_active)          return 'inactive';
        if ($this->starts_at > $now)    return 'scheduled';
        if ($this->ends_at   < $now)    return 'expired';
        return 'active';
    }
}
