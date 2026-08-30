<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DeliveryBonusService
{
    /**
     * Return the active bonus amount if bonus is currently active, otherwise 0.
     */
    public static function getActiveBonusAmount(): float
    {
        try {
            $bonus = DB::table('delivery_bonus_settings')
                ->where('is_active', true)
                ->first();

            if (!$bonus) {
                return 0.0;
            }

            $now         = now();
            $currentHour = (int) $now->format('G');  // 0-23
            $currentDay  = (int) $now->format('N');  // 1=Mon, 7=Sun

            $days  = array_filter(array_map('intval', explode(',', $bonus->days_of_week ?? '')));
            $inDay = in_array($currentDay, $days);
            $inTime = $currentHour >= (int) $bonus->start_hour && $currentHour < (int) $bonus->end_hour;

            if ($inDay && $inTime) {
                return (float) $bonus->bonus_amount;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[DeliveryBonusService] ' . $e->getMessage());
        }

        return 0.0;
    }

    /**
     * Return the full active bonus row (or null).
     */
    public static function getActiveBonusRow(): ?\stdClass
    {
        try {
            $bonus = DB::table('delivery_bonus_settings')
                ->where('is_active', true)
                ->first();

            if (!$bonus) return null;

            $now         = now();
            $currentHour = (int) $now->format('G');
            $currentDay  = (int) $now->format('N');

            $days   = array_filter(array_map('intval', explode(',', $bonus->days_of_week ?? '')));
            $inDay  = in_array($currentDay, $days);
            $inTime = $currentHour >= (int) $bonus->start_hour && $currentHour < (int) $bonus->end_hour;

            return ($inDay && $inTime) ? $bonus : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
