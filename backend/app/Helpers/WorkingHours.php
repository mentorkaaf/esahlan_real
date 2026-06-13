<?php

namespace App\Helpers;

use Carbon\Carbon;

class WorkingHours
{
    /**
     * Determine if a vendor is currently open given its working_hours JSON.
     *
     * @param  string|null  $workingHoursJson  JSON stored in vendors.working_hours
     * @param  bool         $fallback          Return value when no schedule is set
     * @return bool
     */
    public static function isOpen(?string $workingHoursJson, bool $fallback = true): bool
    {
        if (empty($workingHoursJson)) return $fallback;

        $schedule = json_decode($workingHoursJson, true);
        if (!is_array($schedule) || empty($schedule)) return $fallback;

        $now     = AppSettings::now();            // Carbon in app timezone
        $today   = (int) $now->format('N') % 7;  // 0=Sun … 6=Sat  (ISO N: 1=Mon…7=Sun)
        // Convert ISO weekday to 0=Sun…6=Sat
        $today   = $now->dayOfWeek;               // Carbon: 0=Sun, 1=Mon, … 6=Sat

        // Find today's schedule entry
        $entry = collect($schedule)->firstWhere('day', $today);
        if (!$entry) return $fallback;

        if (!empty($entry['is_closed'])) return false;

        $openTime  = $entry['open']  ?? null;
        $closeTime = $entry['close'] ?? null;
        if (!$openTime || !$closeTime) return $fallback;

        $currentMinutes = (int) $now->format('H') * 60 + (int) $now->format('i');
        $openMinutes    = self::timeToMinutes($openTime);
        $closeMinutes   = self::timeToMinutes($closeTime);

        // Handle overnight schedules (e.g. 22:00 – 02:00)
        if ($closeMinutes < $openMinutes) {
            return $currentMinutes >= $openMinutes || $currentMinutes < $closeMinutes;
        }

        return $currentMinutes >= $openMinutes && $currentMinutes < $closeMinutes;
    }

    /**
     * Check if a product is currently available based on its time window.
     */
    public static function isItemAvailable(?string $availableFrom, ?string $availableUntil): bool
    {
        if (!$availableFrom && !$availableUntil) return true; // no restriction

        $now            = AppSettings::now();
        $currentMinutes = (int) $now->format('H') * 60 + (int) $now->format('i');

        $fromMinutes  = $availableFrom  ? self::timeToMinutes($availableFrom)  : 0;
        $untilMinutes = $availableUntil ? self::timeToMinutes($availableUntil) : 1439;

        if ($untilMinutes < $fromMinutes) {
            // Overnight window
            return $currentMinutes >= $fromMinutes || $currentMinutes < $untilMinutes;
        }

        return $currentMinutes >= $fromMinutes && $currentMinutes < $untilMinutes;
    }

    private static function timeToMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));
        return $h * 60 + $m;
    }
}
