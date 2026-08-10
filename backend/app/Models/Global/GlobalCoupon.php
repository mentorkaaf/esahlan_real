<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class GlobalCoupon extends Model
{
    protected $fillable = [
        'name', 'description', 'label',
        'code', 'type', 'value',
        'minimum_order', 'maximum_discount',
        'usage_limit', 'per_user_limit', 'used_count',
        'is_active', 'is_new_user_only',
        'starts_at', 'expires_at',
    ];

    protected $casts = [
        'value'            => 'float',
        'minimum_order'    => 'float',
        'maximum_discount' => 'float',
        'is_active'        => 'boolean',
        'is_new_user_only' => 'boolean',
        'starts_at'        => 'datetime',
        'expires_at'       => 'datetime',
    ];

    public function userCoupons() { return $this->hasMany(GlobalUserCoupon::class); }

    /** Calculate discount amount for a given order total */
    public function calculateDiscount(float $orderTotal): float
    {
        if ($orderTotal < $this->minimum_order) return 0;

        $discount = $this->type === 'percentage'
            ? $orderTotal * ($this->value / 100)
            : $this->value;

        if ($this->maximum_discount) {
            $discount = min($discount, $this->maximum_discount);
        }

        return round(min($discount, $orderTotal), 2);
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->starts_at && $this->starts_at->isFuture()) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) return false;
        return true;
    }
}
