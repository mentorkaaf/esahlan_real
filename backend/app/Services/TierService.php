<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TierService
{
    public const TIERS = ['bronze', 'silver', 'gold', 'platinum'];

    private static function cfg(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:{$key}", 600, fn() =>
            DB::table('settings')->where('key', $key)->value('value') ?? $default
        );
    }

    /** Calculate which tier a given lifetime-earned pts count qualifies for */
    public static function tierForPoints(int $totalEarned): string
    {
        $platinum = (int) self::cfg('tier_platinum_pts', 20000);
        $gold     = (int) self::cfg('tier_gold_pts',      5000);
        $silver   = (int) self::cfg('tier_silver_pts',    1000);

        return match (true) {
            $totalEarned >= $platinum => 'platinum',
            $totalEarned >= $gold     => 'gold',
            $totalEarned >= $silver   => 'silver',
            default                   => 'bronze',
        };
    }

    /** Points needed to reach next tier from current total */
    public static function nextTierInfo(int $totalEarned): array
    {
        $thresholds = [
            'silver'   => (int) self::cfg('tier_silver_pts',   1000),
            'gold'     => (int) self::cfg('tier_gold_pts',     5000),
            'platinum' => (int) self::cfg('tier_platinum_pts', 20000),
        ];

        foreach ($thresholds as $tier => $threshold) {
            if ($totalEarned < $threshold) {
                return [
                    'next_tier'       => $tier,
                    'pts_to_next'     => $threshold - $totalEarned,
                    'next_threshold'  => $threshold,
                ];
            }
        }

        return ['next_tier' => null, 'pts_to_next' => 0, 'next_threshold' => 0];
    }

    /** Earn multiplier from tier bonus (e.g. 1.25 for gold with +25% bonus) */
    public static function earnMultiplier(int $userId): float
    {
        $tier  = DB::table('users')->where('id', $userId)->value('tier') ?? 'bronze';
        $bonus = (int) self::cfg("tier_{$tier}_bonus", 0); // e.g. 25 = +25%
        return 1.0 + ($bonus / 100.0);
    }

    /** Extra redeem % from tier (added on top of base max_redeem_percent) */
    public static function redeemExtra(int $userId): int
    {
        $tier = DB::table('users')->where('id', $userId)->value('tier') ?? 'bronze';
        return (int) self::cfg("tier_{$tier}_redeem_extra", 0);
    }

    /** Check if user should be upgraded; fire notification if upgraded. */
    public static function checkAndUpgrade(int $userId): void
    {
        $user = DB::table('users')->where('id', $userId)->select('tier', 'total_points_earned', 'fcm_token', 'name')->first();
        if (!$user) return;

        $newTier = self::tierForPoints((int) $user->total_points_earned);
        if ($newTier === $user->tier) return;

        // Only upgrade (never downgrade — tier is for life)
        $currentIdx = array_search($user->tier, self::TIERS);
        $newIdx     = array_search($newTier, self::TIERS);
        if ($newIdx <= $currentIdx) return;

        DB::table('users')->where('id', $userId)->update(['tier' => $newTier]);

        // Award tier badges
        try { \App\Services\GamificationService::checkAllBadges($userId); } catch (\Throwable) {}

        RewardNotificationService::tierUpgrade($userId, $user->tier ?? 'bronze', $newTier);
    }

    /** All tier thresholds + bonus info for display/API */
    public static function allTiersInfo(): array
    {
        return [
            ['key' => 'bronze',   'label' => 'Bronze',   'min_pts' => 0,                                              'earn_bonus_pct' => (int) self::cfg('tier_bronze_bonus',   0),  'redeem_extra' => (int) self::cfg('tier_bronze_redeem_extra',   0)],
            ['key' => 'silver',   'label' => 'Silver',   'min_pts' => (int) self::cfg('tier_silver_pts',   1000),     'earn_bonus_pct' => (int) self::cfg('tier_silver_bonus',   10), 'redeem_extra' => (int) self::cfg('tier_silver_redeem_extra',   5)],
            ['key' => 'gold',     'label' => 'Gold',     'min_pts' => (int) self::cfg('tier_gold_pts',     5000),     'earn_bonus_pct' => (int) self::cfg('tier_gold_bonus',     25), 'redeem_extra' => (int) self::cfg('tier_gold_redeem_extra',    10)],
            ['key' => 'platinum', 'label' => 'Platinum', 'min_pts' => (int) self::cfg('tier_platinum_pts', 20000),    'earn_bonus_pct' => (int) self::cfg('tier_platinum_bonus', 50), 'redeem_extra' => (int) self::cfg('tier_platinum_redeem_extra', 20)],
        ];
    }
}
