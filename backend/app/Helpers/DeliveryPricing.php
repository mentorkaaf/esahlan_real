<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class DeliveryPricing
{
    /**
     * Calculate delivery fee from zone pricing.
     * For eShop/eLaundry: base = Hamarweyne (district 4) → customer district.
     * For eFood: base = vendor district → customer district.
     * Falls back to $default if no zone pricing found.
     */
    public static function calculate(?int $fromDistrictId, ?int $toDistrictId, float $default = 0): float
    {
        if (!$fromDistrictId || !$toDistrictId) return $default;

        $zone = DB::table('delivery_zone_pricing')
            ->where('from_district_id', $fromDistrictId)
            ->where('to_district_id', $toDistrictId)
            ->where('is_active', true)
            ->first();

        return $zone ? (float) $zone->base_price : $default;
    }

    /**
     * Get delivery fee for eShop/eLaundry orders.
     * Base district = Hamarweyne (ID 4).
     */
    public static function forShopOrLaundry(?int $customerDistrictId, float $default = 0): float
    {
        $hamarwayneId = (int) AppSettings::get('eshop_base_district_id', 4);
        return self::calculate($hamarwayneId, $customerDistrictId, $default);
    }

    /**
     * Get delivery fee for eFood orders.
     * Base district = vendor's district.
     */
    public static function forFood(?int $vendorDistrictId, ?int $customerDistrictId, float $default = 0): float
    {
        return self::calculate($vendorDistrictId, $customerDistrictId, $default);
    }
}
