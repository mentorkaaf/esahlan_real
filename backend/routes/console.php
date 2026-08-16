<?php

use Illuminate\Support\Facades\Schedule;
use App\Services\FeedRankingService;
use App\Services\CryptoMarketService;
use App\Jobs\ScanCryptoDeposits;

// Recompute post quality scores every 5 minutes
Schedule::call(fn () => FeedRankingService::recomputePostScores())
    ->everyFiveMinutes()
    ->name('feed:recompute-scores')
    ->withoutOverlapping();

// Feature 3: Advance posts through distribution stages every 5 minutes.
// Posts that earned engagement get promoted (100 → 1k → 10k → 100k → viral);
// posts that plateau are left at their current cap and stop spreading.
Schedule::call(fn () => FeedRankingService::progressDistribution())
    ->everyFiveMinutes()
    ->name('feed:progress-distribution')
    ->withoutOverlapping();

// Decay stale interests for all users (independent of per-user activity)
Schedule::call(fn () => FeedRankingService::decayAllUserInterests())
    ->daily()
    ->name('feed:decay-interests')
    ->withoutOverlapping();

// Pre-compute feeds for recently-active users every 5 minutes
Schedule::command('feed:precompute --limit=500')
    ->everyFiveMinutes()
    ->name('feed:precompute')
    ->withoutOverlapping()
    ->runInBackground();

// Clean up old seen-posts data daily
Schedule::call(fn () => FeedRankingService::cleanupSeenPosts())
    ->daily()
    ->name('feed:cleanup-seen');

// Refresh crypto prices from CoinGecko every 2 minutes
Schedule::call(fn () => CryptoMarketService::refreshPrices())
    ->everyTwoMinutes()
    ->name('crypto:refresh-prices')
    ->withoutOverlapping();

// Scan blockchain for new crypto deposits every minute
Schedule::job(new ScanCryptoDeposits)
    ->everyMinute()
    ->name('crypto:scan-deposits')
    ->withoutOverlapping();

// Auto-cancel expired P2P orders every 5 minutes
Schedule::call(function () {
    \App\Models\P2pOrder::whereIn('status', ['payment_waiting'])
        ->where('expires_at', '<', now())
        ->each(function ($order) {
            $order->update(['status' => 'cancelled']);
            // Unlock escrow
            if ($escrow = $order->escrow) {
                $escrow->update(['status' => 'released']);
                $sellerWallet = \App\Models\CryptoWallet::where('user_id', $order->seller_id)
                    ->where('coin_id', $order->ad->coin_id)->first();
                if ($sellerWallet) {
                    $sellerWallet->increment('balance', $order->crypto_amount);
                    $sellerWallet->decrement('locked_balance', $order->crypto_amount);
                }
            }
        });
})->everyFiveMinutes()->name('p2p:auto-cancel-expired')->withoutOverlapping();

// Clean up old interaction data weekly
Schedule::call(fn () => FeedRankingService::cleanupOldInteractions())
    ->weekly()
    ->name('feed:cleanup-interactions');

// Collaborative filtering — recompute user similarities weekly
Schedule::job(new \App\Jobs\ComputeUserSimilaritiesJob)
    ->weekly()
    ->name('feed:collab-similarities')
    ->withoutOverlapping();

// Send review reminders daily at 10:00
Schedule::command('global:review-reminders')
    ->dailyAt('10:00')
    ->name('global:review-reminders')
    ->withoutOverlapping();

// Cart abandonment notifications: every 15 minutes
Schedule::command('cart:notify-abandoned')
    ->everyFifteenMinutes()
    ->name('cart:abandonment-notifications')
    ->withoutOverlapping()
    ->runInBackground();

// eFood campaign notifications — normal mode: every 2 hours
// Sends to all users without purchases. Deep link → /vendor/:id in app.
Schedule::command('efood:send-campaign-notifications')
    ->everyTwoHours()
    ->name('efood:campaign-notifications')
    ->withoutOverlapping()
    ->runInBackground();

// eFood campaign notifications — URGENT mode: every 30 min
// Only campaigns ending in ≤2h. Sends 2× per hour for last-chance urgency.
// Uses separate cache key so it doesn't conflict with normal sends.
Schedule::command('efood:send-campaign-notifications --urgent')
    ->everyThirtyMinutes()
    ->name('efood:campaign-notifications-urgent')
    ->withoutOverlapping()
    ->runInBackground();

// ── Admin server health check ─────────────────────────────────────────────
// Every 5 minutes: CPU, RAM, disk, failed queue jobs, slow DB, brute force.
// Sends admin alert emails when thresholds exceeded (rate-limited per alert).
Schedule::command('admin:server-health-check')
    ->everyFiveMinutes()
    ->name('admin:server-health-check')
    ->withoutOverlapping()
    ->runInBackground();

// ── FCM token validation — daily at 03:00 ───────────────────────────────
// Sends a silent ping to every stored FCM token.
// Invalid tokens (403 SenderId mismatch, 404 UNREGISTERED) are auto-cleared.
// Users re-register their token on next app open.
Schedule::command('fcm:validate-tokens')
    ->dailyAt('03:00')
    ->name('fcm:validate-tokens')
    ->withoutOverlapping()
    ->runInBackground();

// ── Marketing: Re-engagement notifications ────────────────────────────────
// Daily — processes all 4 inactive periods (3d, 7d, 14d, 30d) in one run.
Schedule::command('marketing:reengagement')
    ->daily()
    ->name('marketing:reengagement')
    ->withoutOverlapping()
    ->runInBackground();

// ── Marketing: Time-based notifications ───────────────────────────────────
// Every 30 minutes — each slug checks its own time window internally.
// lunch_time=11:30, evening_deals=18:00, weekend_promo=Friday 10:00
Schedule::command('marketing:time-based')
    ->everyThirtyMinutes()
    ->name('marketing:time-based')
    ->withoutOverlapping()
    ->runInBackground();

// ── Marketing: New vendor district notifications ───────────────────────────
// Hourly — finds vendors approved in the last hour, notifies nearby users.
Schedule::command('marketing:new-vendor')
    ->hourly()
    ->name('marketing:new-vendor')
    ->withoutOverlapping()
    ->runInBackground();

// ── Marketing: Loyalty notifications ─────────────────────────────────────
// Daily at 09:00 — points expiry (3-day warning) + low wallet balance.
Schedule::command('marketing:loyalty')
    ->dailyAt('09:00')
    ->name('marketing:loyalty')
    ->withoutOverlapping()
    ->runInBackground();

// ── eTicket: upcoming flight notifications ────────────────────────────────
// Every 12 hours (twice/day). Sends to all users with FCM.
// Template & timing controlled from Admin → Notifications → Auto Notifications.
// Deep link: /eticket/flight/:id → opens passenger selection screen.
Schedule::command('eticket:send-flight-notifications')
    ->twiceDaily(8, 20)   // 08:00 and 20:00 — twice per day, 12h apart
    ->name('eticket:flight-notifications')
    ->withoutOverlapping()
    ->runInBackground();

// ── HR: Auto-expire workforce assignments ─────────────────────────────────
// Daily at 01:00 — ends assignments whose planned_end_date has passed.
// Revokes module access and user_modules entries. Logs to workforce audit.
Schedule::command('hr:expire-assignments')
    ->dailyAt('01:00')
    ->name('hr:expire-assignments')
    ->withoutOverlapping();
