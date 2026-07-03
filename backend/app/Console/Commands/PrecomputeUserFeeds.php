<?php
namespace App\Console\Commands;

use App\Services\FeedRankingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class PrecomputeUserFeeds extends Command
{
    protected $signature   = 'feed:precompute {--limit=500}';
    protected $description = 'Pre-compute and cache feeds for recently-active users';

    public function handle(): void
    {
        $limit = (int) $this->option('limit');

        // Users active in the last 30 minutes (seen a feed page recently)
        $activeUserIds = DB::table('feed_seen_posts')
            ->select('user_id')
            ->where('seen_at', '>=', now()->subMinutes(30))
            ->groupBy('user_id')
            ->orderByRaw('MAX(seen_at) DESC')
            ->limit($limit)
            ->pluck('user_id')
            ->toArray();

        // Also include users active in the Redis sliding window (real-time)
        $redisActive = Redis::zrangebyscore(
            'feed:active_users',
            now()->subMinutes(10)->timestamp,
            '+inf'
        );
        $userIds = array_unique(array_merge($activeUserIds, $redisActive));

        if (empty($userIds)) {
            $this->info('No active users — nothing to precompute.');
            return;
        }

        $this->info('Precomputing feeds for ' . count($userIds) . ' users…');
        $ok = 0;
        $fail = 0;

        foreach ($userIds as $userId) {
            try {
                $svc = new FeedRankingService((int) $userId);
                // Page 1 is never cached (always fresh), so only pre-warm page 2+
                $svc->buildFeed(2, 30);   // page 2 (pre-warm next scroll)
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                Log::warning('feed:precompute failed for user ' . $userId, ['error' => $e->getMessage()]);
            }
        }

        $this->info("Done. ok={$ok} fail={$fail}");
    }
}
