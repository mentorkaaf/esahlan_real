<?php
namespace App\Services;

use App\Jobs\UpdateUserInterestsJob;
use App\Services\RealtimeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class InteractionTracker
{
    // Session signal TTL — short-lived, separate from the persisted
    // (DB-backed) interest scores so within-session behavior can adapt
    // the feed without writing anything to MySQL.
    private const SESSION_TTL_SECONDS = 1800; // 30 min

    /**
     * Track a user interaction with a post.
     * Called from API endpoints when users interact with content.
     */
    public static function track(int $userId, int $postId, string $type, array $meta = []): void
    {
        DB::table('feed_interactions')->insert([
            'user_id'      => $userId,
            'post_id'      => $postId,
            'type'         => $type,
            'duration_ms'  => $meta['duration_ms'] ?? null,
            'scroll_depth' => $meta['scroll_depth'] ?? null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Update post_scores engagement counter
        DB::table('post_scores')->updateOrInsert(
            ['post_id' => $postId],
            ['engaged_count' => DB::raw('engaged_count + 1'), 'updated_at' => now()]
        );

        // Invalidate user interaction cache
        Cache::forget("user:{$userId}:interactions");

        // Trigger interest update if significant action — queued so the
        // request thread doesn't pay for the hashtag recompute inline.
        if (in_array($type, ['like', 'comment', 'share', 'save'])) {
            UpdateUserInterestsJob::dispatch($userId);
        }

        // Session signals: negative for skip/not_interested, positive for strong engagement
        if (in_array($type, ['skip', 'not_interested'])) {
            self::recordSessionSignal($userId, $postId, 'negative', $type === 'not_interested' ? 3 : 1);
        } elseif (in_array($type, ['like', 'save', 'share', 'comment'])) {
            self::recordSessionSignal($userId, $postId, 'positive', 1);
        } elseif ($type === 'watch' && isset($meta['duration_ms']) && $meta['duration_ms'] > 10000) {
            // Long watch (>10s) = positive signal
            self::recordSessionSignal($userId, $postId, 'positive', 1);
        }

        // not_interested = permanent penalty (stronger than skip)
        if ($type === 'not_interested') {
            self::recordPermanentNotInterested($userId, $postId);
        }
    }

    private static function recordSessionSignal(int $userId, int $postId, string $dir, int $weight = 1): void
    {
        try {
            $post = DB::table('community_posts')->where('id', $postId)->first(['user_id', 'type']);
            if (!$post) return;

            $key = "session:{$userId}:{$dir}";
            Redis::hincrby($key, "creator:{$post->user_id}", $weight);
            Redis::hincrby($key, "type:{$post->type}", $weight);

            $tags = DB::table('community_post_hashtags')
                ->join('community_hashtags', 'community_hashtags.id', '=', 'community_post_hashtags.hashtag_id')
                ->where('post_id', $postId)->pluck('community_hashtags.name');
            foreach ($tags as $tag) {
                Redis::hincrby($key, "hashtag:{$tag}", $weight);
            }
            Redis::expire($key, self::SESSION_TTL_SECONDS);
        } catch (\Throwable $e) {
            Log::warning("Session {$dir} signal failed (non-fatal): " . $e->getMessage());
        }
    }

    private static function recordPermanentNotInterested(int $userId, int $postId): void
    {
        try {
            $post = DB::table('community_posts')->where('id', $postId)->first(['user_id', 'type']);
            if (!$post) return;

            $key = "user:{$userId}:not_interested";
            Redis::hincrby($key, "creator:{$post->user_id}", 5);
            Redis::hincrby($key, "type:{$post->type}", 2);
            Redis::expire($key, 86400 * 30); // 30 days
        } catch (\Throwable $e) {
            Log::warning('Permanent not-interested signal failed (non-fatal): ' . $e->getMessage());
        }
    }

    /**
     * Track a batch of view impressions (called when feed is loaded).
     */
    public static function trackImpressions(int $userId, array $postIds): void
    {
        if (empty($postIds)) return;

        $rows = array_map(fn ($pid) => [
            'user_id'    => $userId,
            'post_id'    => $pid,
            'type'       => 'view',
            'created_at' => now(),
            'updated_at' => now(),
        ], $postIds);

        DB::table('feed_interactions')->insert($rows);

        // Update impression counts in post_scores (ranking-internal, not user-facing)
        DB::table('post_scores')
            ->whereIn('post_id', $postIds)
            ->increment('impression_count');

        // Update the user-facing views_count column too — this is what the
        // feed/reels UI actually displays. The old (pre-ranking-algorithm)
        // feed controller incremented this directly on every transform; that
        // call was dropped when the personalized feed replaced it with
        // impression tracking, but nothing else picked up updating this
        // column, so displayed view counts silently stopped moving.
        // Only count views for fully-ready posts — processing videos must not
        // accumulate view counts before the owner has even published them.
        $readyIds = DB::table('community_posts')
            ->whereIn('id', $postIds)
            ->where('video_ready', true)
            ->pluck('id')
            ->toArray();

        if (!empty($readyIds)) {
            DB::table('community_posts')
                ->whereIn('id', $readyIds)
                ->increment('views_count');
        }

        // Broadcast live counts so feed/reels update without a refresh —
        // one lightweight public event per post, just the new count.
        $freshCounts = DB::table('community_posts')
            ->whereIn('id', $postIds)
            ->where('video_ready', true)
            ->pluck('views_count', 'id');
        foreach ($freshCounts as $postId => $count) {
            RealtimeService::toPublic("community.post.{$postId}", 'post.views_changed', [
                'post_id'     => $postId,
                'views_count' => $count,
            ]);
        }
    }

    /**
     * Track video watch time.
     */
    public static function trackWatchTime(int $userId, int $postId, int $durationMs): void
    {
        self::track($userId, $postId, 'watch', ['duration_ms' => $durationMs]);
    }
}
