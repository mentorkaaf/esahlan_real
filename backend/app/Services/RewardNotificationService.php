<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Central hub for all rewards-related in-app + push notifications.
 * Writes to Laravel's `notifications` table AND sends FCM push.
 */
class RewardNotificationService
{
    // ── Generic sender ────────────────────────────────────────────────────────

    public static function send(
        int    $userId,
        string $type,
        string $title,
        string $body,
        array  $extra = []
    ): void {
        try {
            // 1. Persist in-app notification
            DB::table('notifications')->insert([
                'id'              => (string) Str::uuid(),
                'type'            => $type,
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id'   => $userId,
                'data'            => json_encode(array_merge([
                    'title' => $title,
                    'body'  => $body,
                ], $extra)),
                'read_at'    => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. FCM push notification
            $user = DB::table('users')->where('id', $userId)->value('fcm_token');
            if ($user) {
                FcmService::sendToToken(
                    $user,
                    $title,
                    $body,
                    array_merge(['notification_type' => $type], $extra)
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('RewardNotificationService error: ' . $e->getMessage());
        }
    }

    // ── Specific notification types ───────────────────────────────────────────

    public static function pointsEarned(int $userId, int $pts, string $reason): void
    {
        self::send($userId, 'points_earned',
            '⭐ Points Earned!',
            "You earned {$pts} points — {$reason}",
            ['points' => $pts, 'screen' => 'rewards']
        );
    }

    public static function referralRewarded(int $referrerId, string $friendName, int $pts): void
    {
        self::send($referrerId, 'referral_rewarded',
            '🎉 Referral Reward!',
            "Your friend {$friendName} placed their first order. You earned {$pts} pts!",
            ['points' => $pts, 'screen' => 'referral']
        );
    }

    public static function affiliateCommission(int $userId, int $pts, float $orderAmount): void
    {
        self::send($userId, 'affiliate_commission',
            '💰 Affiliate Commission!',
            "You earned {$pts} pts from a \${$orderAmount} order by your referral.",
            ['points' => $pts, 'screen' => 'affiliate']
        );
    }

    public static function tierUpgrade(int $userId, string $oldTier, string $newTier): void
    {
        $emojis = ['bronze' => '🥉', 'silver' => '🥈', 'gold' => '🥇', 'platinum' => '💎'];
        $emoji  = $emojis[$newTier] ?? '🏆';
        self::send($userId, 'tier_upgrade',
            "{$emoji} Tier Upgraded!",
            "Congratulations! You've reached " . ucfirst($newTier) . " tier. Enjoy better rewards!",
            ['old_tier' => $oldTier, 'new_tier' => $newTier, 'screen' => 'rewards']
        );
    }

    public static function pointsRedeemed(int $userId, int $pts, float $dollarValue): void
    {
        self::send($userId, 'points_redeemed',
            '💳 Points Converted!',
            "You converted {$pts} pts to \${$dollarValue} wallet credit.",
            ['points' => $pts, 'dollar_value' => $dollarValue, 'screen' => 'wallet']
        );
    }

    public static function payoutApproved(int $userId, int $pts, float $dollarValue): void
    {
        self::send($userId, 'payout_approved',
            '✅ Payout Approved!',
            "Your {$pts} pts payout of \${$dollarValue} has been approved and credited to your wallet.",
            ['points' => $pts, 'dollar_value' => $dollarValue, 'screen' => 'wallet']
        );
    }

    public static function payoutRejected(int $userId, int $pts, string $reason = ''): void
    {
        self::send($userId, 'payout_rejected',
            '❌ Payout Rejected',
            "Your {$pts} pts payout was rejected." . ($reason ? " Reason: {$reason}" : ''),
            ['points' => $pts, 'screen' => 'affiliate']
        );
    }

    public static function pointsExpiringSoon(int $userId, int $pts, int $daysLeft): void
    {
        self::send($userId, 'points_expiring',
            '⏰ Points Expiring Soon!',
            "{$pts} of your points will expire in {$daysLeft} days. Use them before they're gone!",
            ['points' => $pts, 'days_left' => $daysLeft, 'screen' => 'rewards']
        );
    }
}
