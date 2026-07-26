<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AffiliateService
{
    private static function cfg(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:{$key}", 600, fn() =>
            DB::table('settings')->where('key', $key)->value('value') ?? $default
        );
    }

    public static function isEnabled(): bool
    {
        return (bool) self::cfg('affiliate_enabled', '1');
    }

    // ── Apply ─────────────────────────────────────────────────────────────────

    /**
     * User applies to become an affiliate.
     * Returns the affiliate record or throws on duplicate.
     */
    public static function apply(int $userId): object
    {
        $existing = DB::table('affiliates')->where('user_id', $userId)->first();
        if ($existing) return $existing;

        $code = strtoupper(Str::random(8));
        while (DB::table('affiliates')->where('code', $code)->exists()) {
            $code = strtoupper(Str::random(8));
        }

        $autoApprove = (bool) self::cfg('affiliate_auto_approve', '0');
        $status = $autoApprove ? 'active' : 'pending';

        $id = DB::table('affiliates')->insertGetId([
            'user_id'    => $userId,
            'code'       => $code,
            'status'     => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('affiliates')->where('id', $id)->first();
    }

    // ── Attribution ───────────────────────────────────────────────────────────

    /**
     * When a new user registers with an affiliate code in the request,
     * store the affiliate_id on the user record.
     */
    public static function attributeUser(int $userId, string $affiliateCode): void
    {
        $affiliate = DB::table('affiliates')
            ->where('code', strtoupper($affiliateCode))
            ->where('status', 'active')
            ->first();

        if (!$affiliate) return;

        DB::table('users')->where('id', $userId)->update(['affiliate_id' => $affiliate->id]);
        DB::table('affiliates')->where('id', $affiliate->id)->increment('total_clicks');
    }

    // ── Commission on order ───────────────────────────────────────────────────

    /**
     * Called after order delivered/completed/boarded.
     * Credits commission pts to the affiliate who brought this user.
     */
    public static function processOrderCommission(int $orderId): void
    {
        if (!self::isEnabled()) return;

        $order = DB::table('orders')->where('id', $orderId)->first();
        if (!$order || !$order->user_id) return;

        // Already processed?
        if (DB::table('affiliate_conversions')->where('order_id', $orderId)->exists()) return;

        // Find which affiliate brought this user
        $user = DB::table('users')->where('id', $order->user_id)->first();
        if (!$user || !$user->affiliate_id) return;

        $affiliate = DB::table('affiliates')->where('id', $user->affiliate_id)->where('status', 'active')->first();
        if (!$affiliate) return;

        // Calculate commission
        $commissionPct = $affiliate->commission_pct ?? (int) self::cfg('affiliate_commission_pct', 3);
        $orderAmount   = max(0, (float) $order->total_amount - (float) ($order->points_discount ?? 0));
        $ptPerDollar   = (int) LoyaltyService::cfg('points_per_dollar', 10);
        $commissionPts = (int) round($orderAmount * $commissionPct / 100 * $ptPerDollar);

        if ($commissionPts <= 0) return;

        DB::transaction(function () use ($affiliate, $order, $commissionPts, $orderAmount) {
            DB::table('affiliate_conversions')->insert([
                'affiliate_id'   => $affiliate->id,
                'order_id'       => $order->id,
                'user_id'        => $order->user_id,
                'order_amount'   => $orderAmount,
                'commission_pts' => $commissionPts,
                'status'         => 'credited',
                'credited_at'    => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            DB::table('affiliates')->where('id', $affiliate->id)->update([
                'total_conversions' => DB::raw('total_conversions + 1'),
                'total_earned_pts'  => DB::raw("total_earned_pts + {$commissionPts}"),
                'pending_payout_pts'=> DB::raw("pending_payout_pts + {$commissionPts}"),
            ]);
        });

        // Credit points to affiliate's loyalty balance
        LoyaltyService::earn(
            $affiliate->user_id,
            $commissionPts,
            "Affiliate commission — order #{$order->order_number}",
            'affiliate',
            $affiliate->id
        );

        // Push notification
        try {
            $affUser = DB::table('users')->where('id', $affiliate->user_id)->first();
            if ($affUser?->fcm_token) {
                \App\Services\FcmService::send(
                    $affUser->fcm_token,
                    '💰 Affiliate Commission!',
                    "You earned {$commissionPts} pts from an order by your referral.",
                    ['type' => 'affiliate_commission', 'pts' => $commissionPts]
                );
            }
        } catch (\Throwable) {}
    }

    // ── Payout request ────────────────────────────────────────────────────────

    public static function requestPayout(int $affiliateId, int $pointsRequested): array
    {
        $affiliate = DB::table('affiliates')->where('id', $affiliateId)->first();
        if (!$affiliate || $affiliate->status !== 'active') {
            return ['success' => false, 'message' => 'Affiliate account not active.'];
        }

        $minPts = (int) self::cfg('affiliate_payout_min_pts', 1000);
        if ($pointsRequested < $minPts) {
            return ['success' => false, 'message' => "Minimum payout is {$minPts} points."];
        }

        $balance = (int) LoyaltyService::balance($affiliate->user_id);
        if ($pointsRequested > $balance) {
            return ['success' => false, 'message' => 'Insufficient points balance.'];
        }

        // Block if pending payout exists
        if (DB::table('affiliate_payouts')->where('affiliate_id', $affiliateId)->where('status', 'pending')->exists()) {
            return ['success' => false, 'message' => 'You already have a pending payout request.'];
        }

        $dollarValue = LoyaltyService::pointsToDollarValue($pointsRequested);

        DB::table('affiliate_payouts')->insert([
            'affiliate_id'     => $affiliateId,
            'points_requested' => $pointsRequested,
            'dollar_value'     => $dollarValue,
            'status'           => 'pending',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return ['success' => true, 'message' => 'Payout request submitted.', 'dollar_value' => $dollarValue];
    }

    // ── Stats for Flutter dashboard ────────────────────────────────────────────

    public static function dashboard(int $userId): array
    {
        $affiliate = DB::table('affiliates')->where('user_id', $userId)->first();
        if (!$affiliate) return ['has_affiliate' => false];

        $conversions = DB::table('affiliate_conversions')
            ->where('affiliate_id', $affiliate->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $payouts = DB::table('affiliate_payouts')
            ->where('affiliate_id', $affiliate->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $minPts     = (int) self::cfg('affiliate_payout_min_pts', 1000);
        $balance    = LoyaltyService::balance($userId);
        $hasPending = DB::table('affiliate_payouts')->where('affiliate_id', $affiliate->id)->where('status', 'pending')->exists();

        return [
            'has_affiliate'      => true,
            'code'               => $affiliate->code,
            'status'             => $affiliate->status,
            'commission_pct'     => $affiliate->commission_pct ?? (int) self::cfg('affiliate_commission_pct', 3),
            'total_clicks'       => $affiliate->total_clicks,
            'total_conversions'  => $affiliate->total_conversions,
            'total_earned_pts'   => (int) $affiliate->total_earned_pts,
            'pending_payout_pts' => (int) $affiliate->pending_payout_pts,
            'points_balance'     => $balance,
            'min_payout_pts'     => $minPts,
            'can_request_payout' => $balance >= $minPts && !$hasPending && $affiliate->status === 'active',
            'has_pending_payout' => $hasPending,
            'conversions'        => $conversions,
            'payouts'            => $payouts,
        ];
    }
}
