<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class LoyaltyService
{
    // ── Config helpers ────────────────────────────────────────────────────────

    public static function cfg(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:{$key}", 600, fn() =>
            DB::table('settings')->where('key', $key)->value('value') ?? $default
        );
    }

    public static function isEnabled(): bool
    {
        return (bool) self::cfg('reward_enabled', '1');
    }

    /** Points earned per $1 spent, adjusted by module multiplier (×10 scale) */
    public static function earnRate(string $moduleSlug = ''): float
    {
        $base = (int) self::cfg('points_per_dollar', 10);
        $multKey = 'pts_mult_' . strtolower(trim($moduleSlug, '/'));
        $mult = (int) self::cfg($multKey, 10); // 10 = 1x, 20 = 2x, 5 = 0.5x
        return $base * ($mult / 10.0);
    }

    /** Points needed for $1 discount */
    public static function pointsToDollar(): int
    {
        return max(1, (int) self::cfg('points_to_dollar', 100));
    }

    /** Convert dollar amount → points earned */
    public static function dollarToPoints(float $amount, string $moduleSlug = ''): int
    {
        return (int) floor($amount * self::earnRate($moduleSlug));
    }

    /** Convert points → dollar discount */
    public static function pointsToDollarValue(int $points): float
    {
        return round($points / self::pointsToDollar(), 2);
    }

    // ── Balance ───────────────────────────────────────────────────────────────

    public static function balance(int $userId): int
    {
        return (int) DB::table('users')->where('id', $userId)->value('points_balance') ?? 0;
    }

    // ── Earn ──────────────────────────────────────────────────────────────────

    public static function earn(int $userId, int $points, string $note = '', string $refType = '', int $refId = 0): void
    {
        if ($points <= 0 || !self::isEnabled()) return;

        $expireDays = (int) self::cfg('points_expire_days', 365);

        DB::transaction(function () use ($userId, $points, $note, $refType, $refId, $expireDays) {
            DB::table('loyalty_points')->insert([
                'user_id'        => $userId,
                'points'         => $points,
                'type'           => 'earned',
                'reference_type' => $refType ?: null,
                'reference_id'   => $refId ?: null,
                'note'           => $note,
                'expires_at'     => now()->addDays($expireDays),
                'created_at'     => now(),
            ]);
            DB::table('users')->where('id', $userId)->increment('points_balance', $points);
        });
    }

    // ── Redeem ────────────────────────────────────────────────────────────────

    /**
     * Deduct points; returns actual dollar discount applied.
     * $maxDiscount: maximum dollar amount allowed (e.g. 50% of order)
     */
    public static function redeem(int $userId, int $requestedPoints, float $maxDiscount, string $note = '', string $refType = '', int $refId = 0): array
    {
        if (!self::isEnabled()) return ['points' => 0, 'discount' => 0.0];

        $balance = self::balance($userId);
        $points  = min($requestedPoints, $balance);

        // Cap by max discount
        $maxPoints = (int) ceil($maxDiscount * self::pointsToDollar());
        $points    = min($points, $maxPoints);

        if ($points <= 0) return ['points' => 0, 'discount' => 0.0];

        $discount = self::pointsToDollarValue($points);

        DB::transaction(function () use ($userId, $points, $note, $refType, $refId) {
            DB::table('loyalty_points')->insert([
                'user_id'        => $userId,
                'points'         => -$points,
                'type'           => 'redeemed',
                'reference_type' => $refType ?: null,
                'reference_id'   => $refId ?: null,
                'note'           => $note,
                'expires_at'     => null,
                'created_at'     => now(),
            ]);
            DB::table('users')->where('id', $userId)->decrement('points_balance', $points);
        });

        return ['points' => $points, 'discount' => $discount];
    }

    // ── Order hook (call after order delivered/completed) ─────────────────────

    public static function creditOrderPoints(int $orderId): void
    {
        if (!self::isEnabled()) return;

        $order = DB::table('orders')->where('id', $orderId)->first();
        if (!$order || !$order->user_id) return;

        // Already credited?
        if ((int) ($order->points_earned ?? 0) > 0) return;

        $minOrder = (float) self::cfg('min_order_for_points', 2);
        $billable = max(0, (float) $order->total_amount - (float) ($order->points_discount ?? 0));
        if ($billable < $minOrder) return;

        $pts = self::dollarToPoints($billable, $order->module_slug ?? '');
        if ($pts <= 0) return;

        DB::table('orders')->where('id', $orderId)->update(['points_earned' => $pts]);

        self::earn(
            $order->user_id,
            $pts,
            "Order #{$order->order_number} reward",
            'App\\Models\\Order',
            $orderId
        );

        // Push notification
        try {
            $user = DB::table('users')->where('id', $order->user_id)->first();
            if ($user?->fcm_token) {
                \App\Services\FcmService::send(
                    $user->fcm_token,
                    '🌟 Points Earned!',
                    "You earned {$pts} eSahlan Points for order #{$order->order_number}",
                    ['type' => 'points_earned', 'points' => $pts]
                );
            }
        } catch (\Throwable) {}
    }

    /**
     * One-call helper for order controllers.
     * Reads 'points_to_redeem' from request, validates, deducts, returns order fields.
     * Returns ['points_used'=>0,'points_discount'=>0.0] if nothing to apply.
     */
    public static function processOrderRequest(\Illuminate\Http\Request $request, int $userId, float $total, string $moduleSlug = ''): array
    {
        $requested = (int) $request->input('points_to_redeem', 0);
        if ($requested <= 0 || !self::isEnabled()) return ['points_used' => 0, 'points_discount' => 0.0];

        $maxPct      = (int) self::cfg('max_redeem_percent', 50);
        $maxDiscount = $total * ($maxPct / 100);

        $result = self::redeem($userId, $requested, $maxDiscount, "Order points redeem [{$moduleSlug}]", 'App\\Models\\Order', 0);
        return ['points_used' => $result['points'], 'points_discount' => $result['discount']];
    }

    // ── History ───────────────────────────────────────────────────────────────

    public static function history(int $userId, int $perPage = 20): object
    {
        return DB::table('loyalty_points')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    // ── Validate redeem request (for checkout) ────────────────────────────────

    public static function validateRedeem(int $userId, int $points, float $orderTotal): array
    {
        if (!self::isEnabled()) {
            return ['ok' => false, 'message' => 'Rewards not enabled'];
        }
        $balance = self::balance($userId);
        if ($points > $balance) {
            return ['ok' => false, 'message' => "You only have {$balance} points available"];
        }
        $maxPct     = (int) self::cfg('max_redeem_percent', 50);
        $maxDiscount = $orderTotal * ($maxPct / 100);
        $discount   = self::pointsToDollarValue($points);
        if ($discount > $maxDiscount) {
            $maxPts = (int) ceil($maxDiscount * self::pointsToDollar());
            return ['ok' => false, 'message' => "Max redeemable is {$maxPts} pts (\${$maxDiscount}) for this order"];
        }
        return ['ok' => true, 'discount' => $discount, 'message' => "Apply \${$discount} discount"];
    }
}
