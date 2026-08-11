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

// eFood campaign discount notifications: every 2 hours
// Sends FCM to users who haven't purchased during the active campaign.
// Stops sending to users once they place an order (purchased = excluded).
Schedule::command('efood:send-campaign-notifications')
    ->everyTwoHours()
    ->name('efood:campaign-notifications')
    ->withoutOverlapping()
    ->runInBackground();
