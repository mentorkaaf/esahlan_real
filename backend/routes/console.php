<?php

use Illuminate\Support\Facades\Schedule;
use App\Services\FeedRankingService;

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

// Clean up old interaction data weekly
Schedule::call(fn () => FeedRankingService::cleanupOldInteractions())
    ->weekly()
    ->name('feed:cleanup-interactions');
