<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GamificationService
{
    private static function cfg(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:{$key}", 600, fn() =>
            DB::table('settings')->where('key', $key)->value('value') ?? $default
        );
    }

    // ── Streaks ───────────────────────────────────────────────────────────────

    /**
     * Called after an order is delivered/completed.
     * Returns bonus pts awarded (0 if no milestone hit).
     */
    public static function recordOrderAndCheckStreak(int $userId, int $orderId): int
    {
        if (!(bool) self::cfg('streak_enabled', '1')) return 0;

        $today  = now()->toDateString();
        $graceH = (int) self::cfg('streak_grace_hours', 48);
        $cutoff = now()->subHours($graceH)->toDateString();

        $streak = DB::table('user_streaks')->where('user_id', $userId)->first();

        if (!$streak) {
            DB::table('user_streaks')->insert([
                'user_id'         => $userId,
                'current_streak'  => 1,
                'longest_streak'  => 1,
                'last_order_date' => $today,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            $newStreak = 1;
        } else {
            $last = $streak->last_order_date;

            if ($last === $today) {
                return 0; // Already counted today
            } elseif ($last >= $cutoff) {
                $newStreak = $streak->current_streak + 1;
            } else {
                $newStreak = 1; // Streak broken
            }

            $longest = max($streak->longest_streak, $newStreak);
            DB::table('user_streaks')->where('user_id', $userId)->update([
                'current_streak'  => $newStreak,
                'longest_streak'  => $longest,
                'last_order_date' => $today,
                'updated_at'      => now(),
            ]);
        }

        // Milestone bonuses
        $bonusPts = match (true) {
            $newStreak === 7   => 200,
            $newStreak === 30  => 1000,
            $newStreak === 100 => 5000,
            $newStreak % 10 === 0 && $newStreak > 0 => 100, // every 10 days
            default => 0,
        };

        if ($bonusPts > 0) {
            LoyaltyService::earn($userId, $bonusPts, "🔥 {$newStreak}-day streak bonus!", 'streak', $userId);
            DB::table('orders')->where('id', $orderId)->update(['streak_bonus_pts' => $bonusPts]);

            try {
                $user = DB::table('users')->where('id', $userId)->first();
                if ($user?->fcm_token) {
                    \App\Services\FcmService::sendToToken(
                        $user->fcm_token,
                        "🔥 {$newStreak}-Day Streak!",
                        "You earned {$bonusPts} bonus pts for your {$newStreak}-day ordering streak!",
                        ['type' => 'streak_milestone', 'streak' => $newStreak, 'pts' => $bonusPts]
                    );
                }
            } catch (\Throwable) {}
        }

        // Check streak badges
        self::awardBadgeIfMissing($userId, 'streak_7',  fn() => $newStreak >= 7);
        self::awardBadgeIfMissing($userId, 'streak_30', fn() => $newStreak >= 30);
        self::awardBadgeIfMissing($userId, 'streak_100',fn() => $newStreak >= 100);

        return $bonusPts;
    }

    // ── Badges ────────────────────────────────────────────────────────────────

    /**
     * Run all badge checks for a user. Call after any qualifying event.
     * Idempotent — already-earned badges are skipped.
     */
    public static function checkAllBadges(int $userId): void
    {
        if (!(bool) self::cfg('badges_enabled', '1')) return;

        // Order count badges
        $orderCount = DB::table('orders')
            ->where('user_id', $userId)
            ->whereIn('status', ['delivered', 'completed', 'boarded'])
            ->count();

        self::awardBadgeIfMissing($userId, 'first_order', fn() => $orderCount >= 1);
        self::awardBadgeIfMissing($userId, 'orders_5',    fn() => $orderCount >= 5);
        self::awardBadgeIfMissing($userId, 'orders_25',   fn() => $orderCount >= 25);
        self::awardBadgeIfMissing($userId, 'orders_100',  fn() => $orderCount >= 100);

        // Spending badges (total_amount - points_discount)
        $totalSpent = (float) DB::table('orders')
            ->where('user_id', $userId)
            ->whereIn('status', ['delivered', 'completed', 'boarded'])
            ->selectRaw('SUM(total_amount - COALESCE(points_discount,0)) as spent')
            ->value('spent');

        self::awardBadgeIfMissing($userId, 'spend_50',   fn() => $totalSpent >= 50);
        self::awardBadgeIfMissing($userId, 'spend_250',  fn() => $totalSpent >= 250);
        self::awardBadgeIfMissing($userId, 'spend_1000', fn() => $totalSpent >= 1000);

        // Referral badges
        $referralCount = DB::table('referrals')
            ->where('referrer_id', $userId)
            ->where('status', 'rewarded')
            ->count();

        self::awardBadgeIfMissing($userId, 'first_referral', fn() => $referralCount >= 1);
        self::awardBadgeIfMissing($userId, 'referrals_5',    fn() => $referralCount >= 5);

        // Tier badges
        $tier = DB::table('users')->where('id', $userId)->value('tier') ?? 'bronze';
        $tierRank = array_search($tier, TierService::TIERS);
        self::awardBadgeIfMissing($userId, 'tier_silver',   fn() => $tierRank >= 1);
        self::awardBadgeIfMissing($userId, 'tier_gold',     fn() => $tierRank >= 2);
        self::awardBadgeIfMissing($userId, 'tier_platinum', fn() => $tierRank >= 3);

        // Explorer badge — used 5+ distinct module slugs
        $moduleCount = DB::table('orders')
            ->where('user_id', $userId)
            ->whereIn('status', ['delivered', 'completed', 'boarded'])
            ->whereNotNull('module_slug')
            ->distinct('module_slug')
            ->count('module_slug');

        self::awardBadgeIfMissing($userId, 'explorer', fn() => $moduleCount >= 5);
    }

    private static function awardBadgeIfMissing(int $userId, string $badgeKey, callable $condition): void
    {
        // Already earned?
        $badge = DB::table('badges')->where('key', $badgeKey)->first();
        if (!$badge) return;

        $alreadyEarned = DB::table('user_badges')
            ->where('user_id', $userId)
            ->where('badge_id', $badge->id)
            ->exists();

        if ($alreadyEarned || !$condition()) return;

        DB::table('user_badges')->insert([
            'user_id'   => $userId,
            'badge_id'  => $badge->id,
            'earned_at' => now(),
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        // Award pts if any
        if ($badge->pts_reward > 0) {
            LoyaltyService::earn($userId, $badge->pts_reward, "Badge: {$badge->name}", 'badge', $badge->id);
        }

        // Push notification
        try {
            $user = DB::table('users')->where('id', $userId)->first();
            if ($user?->fcm_token) {
                \App\Services\FcmService::sendToToken(
                    $user->fcm_token,
                    "{$badge->icon} Badge Earned: {$badge->name}!",
                    $badge->description . ($badge->pts_reward > 0 ? " (+{$badge->pts_reward} pts)" : ''),
                    ['type' => 'badge_earned', 'badge_key' => $badge->key]
                );
            }
        } catch (\Throwable) {}
    }

    // ── Leaderboard ───────────────────────────────────────────────────────────

    public static function leaderboard(string $period = 'weekly', int $limit = 50): array
    {
        if (!(bool) self::cfg('leaderboard_enabled', '1')) return [];

        $query = DB::table('loyalty_points as lp')
            ->join('users', 'users.id', '=', 'lp.user_id')
            ->where('lp.type', 'earned')
            ->where('lp.points', '>', 0)
            ->selectRaw('lp.user_id, users.name, users.tier, SUM(lp.points) as pts_earned');

        if ($period === 'weekly') {
            $query->where('lp.created_at', '>=', now()->startOfWeek());
        } elseif ($period === 'monthly') {
            $query->where('lp.created_at', '>=', now()->startOfMonth());
        }

        return $query->groupBy('lp.user_id', 'users.name', 'users.tier')
            ->orderByDesc('pts_earned')
            ->limit($limit)
            ->get()
            ->values()
            ->map(fn($r, $i) => [
                'rank'       => $i + 1,
                '_user_id'   => $r->user_id,
                'name'       => substr($r->name, 0, 1) . str_repeat('*', max(0, strlen($r->name) - 2)) . substr($r->name, -1),
                'tier'       => $r->tier ?? 'bronze',
                'pts_earned' => (int) $r->pts_earned,
                'is_me'      => false,
            ])
            ->toArray();
    }

    // ── Profile data ──────────────────────────────────────────────────────────

    public static function profileData(int $userId): array
    {
        $streak = DB::table('user_streaks')->where('user_id', $userId)->first();
        $earnedBadges = DB::table('user_badges as ub')
            ->join('badges', 'badges.id', '=', 'ub.badge_id')
            ->where('ub.user_id', $userId)
            ->select('badges.*', 'ub.earned_at')
            ->orderBy('ub.earned_at')
            ->get();

        $allBadges = DB::table('badges')->where('is_active', true)->orderBy('category')->get();

        return [
            'streak' => [
                'current' => $streak?->current_streak ?? 0,
                'longest' => $streak?->longest_streak ?? 0,
                'last_order_date' => $streak?->last_order_date,
            ],
            'badges_earned' => $earnedBadges->count(),
            'badges_total'  => $allBadges->count(),
            'earned_badge_keys' => $earnedBadges->pluck('key')->toArray(),
            'all_badges'    => $allBadges->map(fn($b) => [
                'key'         => $b->key,
                'name'        => $b->name,
                'description' => $b->description,
                'icon'        => $b->icon,
                'category'    => $b->category,
                'pts_reward'  => $b->pts_reward,
                'earned'      => $earnedBadges->contains('key', $b->key),
                'earned_at'   => $earnedBadges->firstWhere('key', $b->key)?->earned_at ?? null,
            ])->toArray(),
        ];
    }
}
