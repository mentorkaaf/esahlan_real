<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class GlobalShippingZone extends Model
{
    protected $fillable = [
        'name','countries','flat_rate','free_shipping_over',
        'estimated_days_min','estimated_days_max','is_active',
    ];
    protected $casts = [
        'countries'          => 'array',
        'flat_rate'          => 'float',
        'free_shipping_over' => 'float',
        'is_active'          => 'boolean',
    ];

    public static function forCountry(string $countryCode): ?self
    {
        return static::where('is_active', true)->get()->first(function ($zone) use ($countryCode) {
            return in_array($countryCode, $zone->countries) || in_array('*', $zone->countries);
        });
    }
}
