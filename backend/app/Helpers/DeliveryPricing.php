<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

/**
 * DeliveryPricing — Zone-based delivery fee calculator.
 *
 * All shopping modules (eFood, eShop, eGrocery, eLaundry…) use the
 * delivery_zone_pricing table (originally set up for eParcel).
 * The table has NO module_id filter in queries here — any active row
 * for (from_district → to_district) is valid for all modules.
 *
 * Logic:
 *   from_district = vendor's district (or base district if vendor has none)
 *   to_district   = customer's district
 */
class DeliveryPricing
{
    /** Hamarweyne is the default base district when vendor has no district set */
    private const BASE_DISTRICT_SETTING = 'eshop_base_district_id';
    private const BASE_DISTRICT_DEFAULT  = 4; // Hamarweyne

    /**
     * Core lookup: from_district → to_district in delivery_zone_pricing.
     * Returns $default if no active row found.
     */
    public static function calculate(?int $fromDistrictId, ?int $toDistrictId, float $default = 0): float
    {
        if (!$fromDistrictId || !$toDistrictId) return $default;

        $zone = DB::table('delivery_zone_pricing')
            ->where('from_district_id', $fromDistrictId)
            ->where('to_district_id', $toDistrictId)
            ->where('is_active', true)
            ->first();

        $price = $zone ? (float) $zone->base_price : $default;

        // Same-district deliveries are capped at $1.00
        if ($fromDistrictId === $toDistrictId) {
            $price = min($price, 1.00);
        }

        return $price;
    }

    /**
     * Base district ID used as "from" when vendor has no district set.
     */
    public static function baseDistrictId(): int
    {
        return (int) AppSettings::get(self::BASE_DISTRICT_SETTING, self::BASE_DISTRICT_DEFAULT);
    }

    /**
     * Delivery fee for any shopping module (eFood, eShop, eGrocery…).
     *
     * @param int|null $vendorDistrictId  Vendor's district_id (null = use base district)
     * @param int|null $customerDistrictId Customer's delivery district
     * @param float    $default            Fallback if no zone pricing row exists
     */
    public static function forVendorToCustomer(?int $vendorDistrictId, ?int $customerDistrictId, float $default = 0): float
    {
        // If vendor has no district, use the configured base (Hamarweyne)
        $fromDistrict = $vendorDistrictId ?: self::baseDistrictId();
        return self::calculate($fromDistrict, $customerDistrictId, $default);
    }

    /**
     * Alias: eFood orders — vendor district → customer district.
     */
    public static function forFood(?int $vendorDistrictId, ?int $customerDistrictId, float $default = 0): float
    {
        return self::forVendorToCustomer($vendorDistrictId, $customerDistrictId, $default);
    }

    /**
     * Alias: eShop / eLaundry — vendor district → customer district.
     * If no vendor district, uses base district (Hamarweyne).
     */
    public static function forShopOrLaundry(?int $customerDistrictId, float $default = 0, ?int $vendorDistrictId = null): float
    {
        return self::forVendorToCustomer($vendorDistrictId, $customerDistrictId, $default);
    }
}
