<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    private static function cfg(string $key, mixed $default): mixed
    {
        return Cache::remember("setting:{$key}", 600, fn() =>
            DB::table('settings')->where('key', $key)->value('value') ?? $default
        );
    }

    // Called after an order is delivered/completed for the first time.
    // Rewards: level-1 referrer gets base pts + commission, level-2 gets smaller commission.
    public static function processFirstOrderReward(int $userId, int $orderId, float $orderAmount): void
    {
        // Check if this order already triggered referral rewards
        if (DB::table('referrals')->where('referred_id', $userId)->where('status', 'rewarded')->exists()) {
            return; // Already rewarded on a previous order
        }

        $referral = DB::table('referrals')
            ->where('referred_id', $userId)
            ->where('status', 'pending')
            ->first();

        if (!$referral) {
            return;
        }

        $minOrder = (float) static::cfg('referral_min_order', 5);
        if ($orderAmount < $minOrder) {
            return;
        }

        $baseRewardPts  = (int) static::cfg('referral_reward_pts', 500);
        $commissionPct  = (float) static::cfg('referral_commission_pct', 5);
        $l2Pct          = (float) static::cfg('referral_l2_pct', 2);

        $commissionPts = (int) round($orderAmount * $commissionPct / 100 * (int) static::cfg('points_per_dollar', 10));

        // Level-1 reward
        $l1Total = $baseRewardPts + $commissionPts;
        LoyaltyService::earn($referral->referrer_id, $l1Total, "Referral reward (order #{$orderId})", 'referral', $referral->referrer_id);

        // Level-2 reward — find if referrer was also referred by someone
        $l2Referral = DB::table('referrals')
            ->where('referred_id', $referral->referrer_id)
            ->where('status', 'rewarded')
            ->first();

        if ($l2Referral) {
            $l2Pts = (int) round($orderAmount * $l2Pct / 100 * (int) static::cfg('points_per_dollar', 10));
            if ($l2Pts > 0) {
                LoyaltyService::earn($l2Referral->referrer_id, $l2Pts, "L2 referral commission (order #{$orderId})", 'referral_l2', $l2Referral->referrer_id);
            }
        }

        // Mark referral as rewarded
        DB::table('referrals')->where('id', $referral->id)->update([
            'status'      => 'rewarded',
            'reward_amount' => $l1Total,
            'rewarded_at' => now(),
        ]);
    }

    public static function getReferralStats(int $userId): array
    {
        $rows = DB::table('referrals')
            ->where('referrer_id', $userId)
            ->join('users', 'users.id', '=', 'referrals.referred_id')
            ->select('users.name', 'referrals.status', 'referrals.reward_amount', 'referrals.created_at')
            ->latest('referrals.created_at')
            ->get();

        return [
            'total'    => $rows->count(),
            'rewarded' => $rows->where('status', 'rewarded')->count(),
            'pending'  => $rows->where('status', 'pending')->count(),
            'earned'   => $rows->where('status', 'rewarded')->sum('reward_amount'),
            'list'     => $rows,
        ];
    }
}
