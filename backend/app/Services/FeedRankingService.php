<?php
namespace App\Services;

use App\Models\CommunityPost;
use App\Models\CommunityFollow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class FeedRankingService
{
    // Scoring weights
    private const W_FOLLOW     = 5.0;   // from someone user follows
    private const W_INTEREST   = 3.0;   // matches user interests
    private const W_LIKE       = 1.0;
    private const W_COMMENT    = 2.0;
    private const W_SHARE      = 3.0;
    private const W_SAVE       = 2.5;
    private const W_VIEW       = 0.1;
    private const W_WATCH_TIME = 0.005; // per second of watch time

    // Time decay: posts lose relevance over time
    private const DECAY_HALF_LIFE_HOURS = 12;

    // Feed composition targets (percentages)
    private const MIX_FOLLOWING      = 40;
    private const MIX_RECOMMENDED    = 30;
    private const MIX_TRENDING       = 15;
    private const MIX_NEW_CREATORS   = 10;
    private const MIX_RANDOM         = 5;

    // Anti-flood: max posts from single creator per feed page
    private const MAX_PER_CREATOR = 2;

    // Content diversity: avoid the same hashtag/content-type dominating a page
    private const MAX_PER_TOPIC = 3;
    private const MAX_CONSECUTIVE_SAME_TYPE = 2;

    private int $userId;
    private array $followingIds;
    private array $userInterests;
    private array $seenPostIds;
    private array $interactionHistory;
    private array $negativeSignals;
    private array $sessionSignals;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->followingIds = $this->getFollowingIds();
        $this->userInterests = $this->getUserInterests();
        $this->seenPostIds = $this->getRecentSeenPosts();
        $this->interactionHistory = $this->getRecentInteractions();
        $this->negativeSignals = $this->getNegativeSignals();
        $this->sessionSignals = $this->getSessionSignals();
    }

    /**
     * Build a personalized feed page.
     * Returns post IDs in ranked order.
     */
    public function buildFeed(int $page = 1, int $perPage = 15): array
    {
        $cacheKey = "feed:v2:{$this->userId}:p{$page}";
        $cached = Cache::get($cacheKey);
        if ($cached && $page > 1) return $cached;

        // Gather candidate posts from different pools
        $candidates = $this->gatherCandidates($page, $perPage);

        // Score each candidate
        $scored = $this->scorePostsBatch($candidates);

        // Apply diversity rules (anti-flood, content mix)
        $diversified = $this->applyDiversity($scored, $perPage);

        // Take the page's worth
        $result = array_slice($diversified, 0, $perPage);

        // Mark as seen
        $this->markSeen(array_column($result, 'post_id'));

        // Cache for 2 minutes (short TTL since feed is dynamic)
        Cache::put($cacheKey, $result, 120);

        return $result;
    }

    /**
     * Gather candidate posts from multiple pools.
     */
    private function gatherCandidates(int $page, int $perPage): Collection
    {
        $candidateCount = $perPage * 5; // Over-fetch 5x for ranking
        $offset = ($page - 1) * $perPage;

        // Pools 3/4/5 (trending/new-creator/random) get fixed, small shares
        // of the candidate budget (20%/10%/5%) on the assumption that pools
        // 1/2 (following/recommended) carry most of the page. For a fresh
        // user with no follows and no interaction history, pools 1/2
        // contribute nothing — audited: a brand-new user got only 12/15
        // posts back because 3/4/5's combined ~35% budget wasn't enough
        // candidates on its own. When 1/2 are both empty, redistribute their
        // share across 3/4/5 proportionally so cold-start users get a full
        // breadth of candidates instead of a thin, mostly-random page.
        $hasFollowing = !empty($this->followingIds);
        $hasInteractions = !empty($this->getInteractedCreators());
        $discoveryBoost = (!$hasFollowing && !$hasInteractions) ? 2.0 : 1.0;

        $query = CommunityPost::query()
            ->whereNull('group_id')
            ->where('privacy', '!=', 'private')
            ->whereNull('deleted_at')
            ->where('created_at', '>', now()->subDays(14)); // 2 week window

        // Exclude posts seen in the last 2 hours, on every page — not just
        // page 1. Originally page-1-only (page 1 fresh, deeper pages more
        // lenient), but that let a heavily-penalized-yet-still-decent post
        // resurface on page 2+ whenever the candidate pool was small (the
        // 0.1x score penalty alone isn't enough to push it out of the top N
        // when there aren't many alternatives). A 2-hour window still lets
        // content resurface across separate sessions, just not while
        // someone is actively scrolling through pages in one sitting.
        if (!empty($this->seenPostIds)) {
            $recentSeen = DB::table('feed_seen_posts')
                ->where('user_id', $this->userId)
                ->where('seen_at', '>', now()->subHours(2))
                ->pluck('post_id')->toArray();
            if (!empty($recentSeen)) {
                $query->whereNotIn('id', $recentSeen);
            }
        }

        // Pool 1: Following posts (prioritized)
        $followingPosts = (clone $query)
            ->whereIn('user_id', $this->followingIds)
            ->latest()
            ->limit($candidateCount)
            ->offset($offset)
            ->get(['id', 'user_id', 'type', 'content', 'location',
                   'views_count', 'likes_count', 'comments_count',
                   'shares_count', 'saves_count', 'created_at']);

        // Pool 2: Recommended (from creators user interacted with but doesn't follow)
        $interactedCreators = $this->getInteractedCreators();
        $recommendedPosts = collect();
        if (!empty($interactedCreators)) {
            $recommendedPosts = (clone $query)
                ->whereIn('user_id', $interactedCreators)
                ->whereNotIn('user_id', $this->followingIds)
                ->latest()
                ->limit(round($candidateCount * 0.3))
                ->offset(round($offset * 0.3))
                ->get(['id', 'user_id', 'type', 'content', 'location',
                       'views_count', 'likes_count', 'comments_count',
                       'shares_count', 'saves_count', 'created_at']);
        }

        // Pool 3: Trending posts (high engagement velocity)
        $trendingPosts = (clone $query)
            ->whereNotIn('user_id', $this->followingIds)
            ->where('created_at', '>', now()->subDays(3))
            ->whereRaw('(likes_count + comments_count * 2 + shares_count * 3) > ?', [10])
            ->orderByRaw('(likes_count + comments_count * 2 + shares_count * 3) / GREATEST(1, TIMESTAMPDIFF(HOUR, created_at, NOW())) DESC')
            ->limit((int) round($candidateCount * 0.2 * $discoveryBoost))
            ->offset(round($offset * 0.2))
            ->get(['id', 'user_id', 'type', 'content', 'location',
                   'views_count', 'likes_count', 'comments_count',
                   'shares_count', 'saves_count', 'created_at']);

        // Pool 4: New creators (users with < 100 followers, posted recently).
        // Include users with no community_profile row yet — they're new by definition.
        // No offset here — deliberately re-sampled (inRandomOrder) each page so
        // discovery content stays fresh rather than paginating predictably.
        $newCreatorPosts = (clone $query)
            ->whereNotIn('user_id', $this->followingIds)
            ->where('created_at', '>', now()->subDays(2))
            ->where(function ($q) {
                $q->whereHas('user.communityProfile', fn ($p) => $p->where('followers_count', '<', 100))
                  ->orWhereDoesntHave('user.communityProfile');
            })
            ->inRandomOrder()
            ->limit((int) round($candidateCount * 0.1 * $discoveryBoost))
            ->get(['id', 'user_id', 'type', 'content', 'location',
                   'views_count', 'likes_count', 'comments_count',
                   'shares_count', 'saves_count', 'created_at']);

        // Pool 5: Random discovery (serendipity) — same reasoning, no offset.
        $randomPosts = (clone $query)
            ->whereNotIn('user_id', $this->followingIds)
            ->where('created_at', '>', now()->subDays(7))
            ->inRandomOrder()
            ->limit((int) round($candidateCount * 0.05 * $discoveryBoost))
            ->get(['id', 'user_id', 'type', 'content', 'location',
                   'views_count', 'likes_count', 'comments_count',
                   'shares_count', 'saves_count', 'created_at']);

        // Merge all pools, deduplicate by post ID
        return $followingPosts
            ->concat($recommendedPosts)
            ->concat($trendingPosts)
            ->concat($newCreatorPosts)
            ->concat($randomPosts)
            ->unique('id');
    }

    /**
     * Score a batch of candidate posts.
     */
    private function scorePostsBatch(Collection $candidates): array
    {
        if ($candidates->isEmpty()) return [];

        // Fetch pre-computed scores if available
        $postIds = $candidates->pluck('id')->toArray();
        $precomputed = DB::table('post_scores')
            ->whereIn('post_id', $postIds)
            ->get()
            ->keyBy('post_id');

        // Fetch hashtags for interest matching
        $postHashtags = DB::table('community_post_hashtags')
            ->join('community_hashtags', 'community_hashtags.id', '=', 'community_post_hashtags.hashtag_id')
            ->whereIn('post_id', $postIds)
            ->get(['post_id', 'community_hashtags.name'])
            ->groupBy('post_id')
            ->map(fn ($tags) => $tags->pluck('name')->toArray());

        $scored = [];
        foreach ($candidates as $post) {
            $score = $this->scorePost($post, $precomputed, $postHashtags);
            $tags = $postHashtags[$post->id] ?? [];
            $scored[] = [
                'post_id'  => $post->id,
                'user_id'  => $post->user_id,
                'type'     => $post->type,
                'topic'    => $tags[0] ?? null, // primary hashtag, for topic diversity
                'score'    => $score,
                'pool'     => $this->classifyPool($post),
            ];
        }

        // Sort by score descending
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
    }

    /**
     * Score a single post for this user.
     */
    private function scorePost($post, $precomputed, $postHashtags): float
    {
        $score = 0.0;

        // 1. Relationship score — is this from someone the user follows?
        if (in_array($post->user_id, $this->followingIds)) {
            $score += self::W_FOLLOW;

            // Bonus for close friends (users they interact with most)
            $closeness = $this->interactionHistory[$post->user_id] ?? 0;
            $score += min($closeness * 0.5, 5.0);
        }

        // 2. Engagement score — raw post engagement metrics
        $engScore = ($post->likes_count * self::W_LIKE)
                  + ($post->comments_count * self::W_COMMENT)
                  + ($post->shares_count * self::W_SHARE)
                  + ($post->saves_count * self::W_SAVE)
                  + ($post->views_count * self::W_VIEW);
        $score += log1p($engScore) * 2;

        // 3. Velocity score — engagement rate (how fast engagement is growing)
        $hoursOld = max(1, now()->diffInMinutes($post->created_at) / 60);
        $velocity = $engScore / $hoursOld;
        $score += log1p($velocity) * 3;

        // 4. Interest matching — does this post match user's interests?
        $tags = $postHashtags[$post->id] ?? [];
        foreach ($tags as $tag) {
            $interestScore = $this->userInterests["hashtag:{$tag}"] ?? 0;
            $score += $interestScore * self::W_INTEREST;
        }

        // Match post type interest
        $typeInterest = $this->userInterests["type:{$post->type}"] ?? 0;
        $score += $typeInterest * 1.5;

        // Match creator interest
        $creatorInterest = $this->userInterests["creator:{$post->user_id}"] ?? 0;
        $score += $creatorInterest * 2.0;

        // 5. Pre-computed viral score (if available)
        $pre = $precomputed[$post->id] ?? null;
        if ($pre) {
            $score += $pre->viral_score * 2;
            // Penalize posts with high impressions but low engagement (user fatigue)
            if ($pre->impression_count > 100 && $pre->engagement_rate < 0.01) {
                $score *= 0.5;
            }
        }

        // 6. Time decay — exponential decay based on age
        $decayFactor = pow(0.5, $hoursOld / self::DECAY_HALF_LIFE_HOURS);
        $score *= $decayFactor;

        // 7. Content type bonus — media-rich posts get slight boost
        if (in_array($post->type, ['video', 'reel', 'image'])) {
            $score *= 1.15;
        }

        // 8. Negative signals
        // Penalize if user already interacted (they don't need to see it again)
        if (in_array($post->id, $this->seenPostIds)) {
            $score *= 0.1;
        }

        // Penalize creators/topics/types the user explicitly marked "not interested"
        if (!empty($this->negativeSignals)) {
            $penalty = 0.0;
            $penalty += ($this->negativeSignals["creator:{$post->user_id}"] ?? 0) * 2.0;
            $penalty += ($this->negativeSignals["type:{$post->type}"] ?? 0) * 1.0;
            foreach ($tags as $tag) {
                $penalty += ($this->negativeSignals["hashtag:{$tag}"] ?? 0) * 1.5;
            }
            if ($penalty > 0) {
                // Diminishing penalty so one skip doesn't permanently nuke a creator,
                // but repeated skips push the score toward zero.
                $score *= 1 / (1 + $penalty * 0.3);
            }
        }

        // Session-scoped signals (Redis, ~30 min TTL): heavier per-skip weight
        // than the persisted penalty since it's self-correcting and expires —
        // it's fine to react strongly to "user just skipped 3 of these."
        if (!empty($this->sessionSignals)) {
            $sessionPenalty = 0.0;
            $sessionPenalty += (int) ($this->sessionSignals["creator:{$post->user_id}"] ?? 0) * 1.0;
            $sessionPenalty += (int) ($this->sessionSignals["type:{$post->type}"] ?? 0) * 0.5;
            foreach ($tags as $tag) {
                $sessionPenalty += (int) ($this->sessionSignals["hashtag:{$tag}"] ?? 0) * 0.8;
            }
            if ($sessionPenalty > 0) {
                $score *= 1 / (1 + $sessionPenalty * 0.6);
            }
        }

        return max(0, $score);
    }

    /**
     * Apply diversity rules to prevent monotonous feed.
     */
    private function applyDiversity(array $scored, int $perPage): array
    {
        $result = [];
        $creatorCounts = [];
        $topicCounts = [];
        $lastType = null;
        $consecutiveType = 0;
        $typeCounts = ['following' => 0, 'recommended' => 0, 'trending' => 0, 'new_creator' => 0, 'random' => 0];
        $typeTargets = [
            'following'    => ceil($perPage * self::MIX_FOLLOWING / 100),
            'recommended'  => ceil($perPage * self::MIX_RECOMMENDED / 100),
            'trending'     => ceil($perPage * self::MIX_TRENDING / 100),
            'new_creator'  => ceil($perPage * self::MIX_NEW_CREATORS / 100),
            'random'       => ceil($perPage * self::MIX_RANDOM / 100),
        ];

        foreach ($scored as $item) {
            if (count($result) >= $perPage) break;

            $creatorId = $item['user_id'];
            $pool = $item['pool'];
            $topic = $item['topic'];
            $contentType = $item['type'];

            // Anti-flood: max N posts per creator (check only, don't commit yet)
            if (($creatorCounts[$creatorId] ?? 0) >= self::MAX_PER_CREATOR) continue;

            // Topic diversity: don't let one hashtag dominate the page
            if ($topic !== null && ($topicCounts[$topic] ?? 0) >= self::MAX_PER_TOPIC) continue;

            // Content-type diversity: avoid runs of the same media type
            // (e.g. video, video, video back to back)
            if ($contentType === $lastType && $consecutiveType >= self::MAX_CONSECUTIVE_SAME_TYPE) continue;

            // Soft cap on pool distribution (allow overflow rather than skip good content)
            $poolCount = $typeCounts[$pool] ?? 0;
            $poolTarget = $typeTargets[$pool] ?? $perPage;
            if ($poolCount >= $poolTarget * 1.5) continue;

            // All checks passed — commit the item and its counters together
            $result[] = $item;
            $creatorCounts[$creatorId] = ($creatorCounts[$creatorId] ?? 0) + 1;
            if ($topic !== null) $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
            $typeCounts[$pool] = $poolCount + 1;
            $consecutiveType = ($contentType === $lastType) ? $consecutiveType + 1 : 1;
            $lastType = $contentType;
        }

        // Backfill if the strict pass didn't fill the page — but in two
        // tiers, not one unconditional free-for-all. The old version ignored
        // every rule including the per-creator cap, which let a single
        // creator end up with 3+ posts on a page (audited: cap=2 but actual
        // max=3) whenever the candidate pool was thin (small datasets, or a
        // fresh user with only random/trending pools available). Topic/type/
        // pool-mix caps are still fully relaxed here since those are softer
        // diversity goals; the creator cap gets one extra slot instead of
        // none, which is enough to fill most pages without letting any one
        // creator dominate.
        if (count($result) < $perPage) {
            $usedIds = array_column($result, 'post_id');
            $relaxedCreatorCap = self::MAX_PER_CREATOR + 1;
            foreach ($scored as $item) {
                if (count($result) >= $perPage) break;
                if (in_array($item['post_id'], $usedIds)) continue;
                if (($creatorCounts[$item['user_id']] ?? 0) >= $relaxedCreatorCap) continue;
                $result[] = $item;
                $creatorCounts[$item['user_id']] = ($creatorCounts[$item['user_id']] ?? 0) + 1;
            }
        }

        // Last resort: candidate pool is so small that even the relaxed cap
        // can't fill the page. Ignore all rules rather than return an
        // under-filled page when more content technically exists.
        if (count($result) < $perPage) {
            $usedIds = array_column($result, 'post_id');
            foreach ($scored as $item) {
                if (count($result) >= $perPage) break;
                if (!in_array($item['post_id'], $usedIds)) {
                    $result[] = $item;
                }
            }
        }

        return $result;
    }

    /**
     * Classify which pool a post belongs to.
     */
    private function classifyPool($post): string
    {
        if (in_array($post->user_id, $this->followingIds)) return 'following';

        $interactedCreators = $this->getInteractedCreators();
        if (in_array($post->user_id, $interactedCreators)) return 'recommended';

        $hoursOld = max(1, now()->diffInMinutes($post->created_at) / 60);
        $engScore = $post->likes_count + $post->comments_count * 2 + $post->shares_count * 3;
        if ($engScore > 10 && $hoursOld < 72) return 'trending';

        return 'random';
    }

    // ─── Data loaders ──────────────────────────────────────────────

    private function getFollowingIds(): array
    {
        return Cache::remember("user:{$this->userId}:following", 300,
            fn () => CommunityFollow::where('follower_id', $this->userId)
                ->pluck('following_id')->toArray()
        );
    }

    private function getUserInterests(): array
    {
        return Cache::remember("user:{$this->userId}:interests", 300,
            fn () => DB::table('user_interests')
                ->where('user_id', $this->userId)
                ->where('score', '>', 0.1)
                ->orderByDesc('score')
                ->limit(100)
                ->get()
                ->mapWithKeys(fn ($i) => ["{$i->category}:{$i->value}" => $i->score])
                ->toArray()
        );
    }

    private function getRecentSeenPosts(): array
    {
        return DB::table('feed_seen_posts')
            ->where('user_id', $this->userId)
            ->where('seen_at', '>', now()->subHours(6))
            ->pluck('post_id')->toArray();
    }

    private function getRecentInteractions(): array
    {
        return Cache::remember("user:{$this->userId}:interactions", 300,
            fn () => DB::table('feed_interactions')
                ->join('community_posts', 'community_posts.id', '=', 'feed_interactions.post_id')
                ->where('feed_interactions.user_id', $this->userId)
                ->where('feed_interactions.created_at', '>', now()->subDays(14))
                ->selectRaw('community_posts.user_id as creator_id, COUNT(*) as cnt')
                ->groupBy('community_posts.user_id')
                ->pluck('cnt', 'creator_id')
                ->toArray()
        );
    }

    private function getInteractedCreators(): array
    {
        return array_keys($this->interactionHistory);
    }

    /**
     * Build a penalty map from explicit "not interested" / hide actions
     * (feed_interactions.type = 'skip'). Kept separate from user_interests
     * (positive scores only) rather than mixing signs into that table —
     * avoids touching the well-tested positive-interest normalization logic.
     */
    private function getNegativeSignals(): array
    {
        return Cache::remember("user:{$this->userId}:negative", 300, function () {
            $skips = DB::table('feed_interactions')
                ->join('community_posts', 'community_posts.id', '=', 'feed_interactions.post_id')
                ->where('feed_interactions.user_id', $this->userId)
                ->where('feed_interactions.type', 'skip')
                ->where('feed_interactions.created_at', '>', now()->subDays(30))
                ->select('feed_interactions.post_id', 'community_posts.user_id as creator_id',
                         'community_posts.type', 'feed_interactions.created_at')
                ->get();

            if ($skips->isEmpty()) return [];

            $penalties = [];
            $postIds = $skips->pluck('post_id')->toArray();
            $tagsByPost = DB::table('community_post_hashtags')
                ->join('community_hashtags', 'community_hashtags.id', '=', 'community_post_hashtags.hashtag_id')
                ->whereIn('post_id', $postIds)
                ->get(['post_id', 'community_hashtags.name'])
                ->groupBy('post_id')
                ->map(fn ($t) => $t->pluck('name')->toArray());

            foreach ($skips as $s) {
                $daysAgo = now()->diffInDays($s->created_at);
                $weight = pow(0.9, $daysAgo); // same decay curve as positive interests

                $key = "creator:{$s->creator_id}";
                $penalties[$key] = ($penalties[$key] ?? 0) + $weight;

                $key = "type:{$s->type}";
                $penalties[$key] = ($penalties[$key] ?? 0) + ($weight * 0.5);

                foreach ($tagsByPost[$s->post_id] ?? [] as $tag) {
                    $key = "hashtag:{$tag}";
                    $penalties[$key] = ($penalties[$key] ?? 0) + $weight;
                }
            }

            return $penalties;
        });
    }

    /**
     * Read within-session negative signals from Redis (see
     * InteractionTracker::recordSessionSkip). Best-effort — returns empty
     * on any Redis failure so feed ranking never breaks because of it.
     */
    private function getSessionSignals(): array
    {
        try {
            $raw = Redis::hgetall("session:{$this->userId}:negative");
            return is_array($raw) ? $raw : [];
        } catch (\Throwable $e) {
            Log::warning('Session signal read failed (non-fatal): ' . $e->getMessage());
            return [];
        }
    }

    private function getRecentSeenPostIds(): array
    {
        return $this->seenPostIds;
    }

    /**
     * Mark posts as seen by this user.
     */
    private function markSeen(array $postIds): void
    {
        if (empty($postIds)) return;

        $rows = array_map(fn ($pid) => [
            'user_id' => $this->userId,
            'post_id' => $pid,
            'seen_at' => now(),
        ], $postIds);

        DB::table('feed_seen_posts')->insertOrIgnore($rows);
    }

    // ─── Static scoring jobs ──────────────────────────────────────

    /**
     * Recompute post scores (run via scheduler every 5 min).
     */
    public static function recomputePostScores(): void
    {
        $posts = CommunityPost::where('created_at', '>', now()->subDays(14))
            ->whereNull('deleted_at')
            ->get(['id', 'user_id', 'type', 'views_count', 'likes_count',
                   'comments_count', 'shares_count', 'saves_count', 'created_at']);

        foreach ($posts->chunk(200) as $chunk) {
            $chunkIds = $chunk->pluck('id')->toArray();

            // Batch the 4 metrics that used to run as one query PER post —
            // now 4 grouped queries cover the whole chunk regardless of size.
            $recentCounts = DB::table('feed_interactions')
                ->whereIn('post_id', $chunkIds)
                ->where('created_at', '>', now()->subHours(2))
                ->selectRaw('post_id, COUNT(*) as cnt')
                ->groupBy('post_id')->pluck('cnt', 'post_id');

            $olderCounts = DB::table('feed_interactions')
                ->whereIn('post_id', $chunkIds)
                ->whereBetween('created_at', [now()->subHours(4), now()->subHours(2)])
                ->selectRaw('post_id, COUNT(*) as cnt')
                ->groupBy('post_id')->pluck('cnt', 'post_id');

            $impressionCounts = DB::table('feed_seen_posts')
                ->whereIn('post_id', $chunkIds)
                ->selectRaw('post_id, COUNT(*) as cnt')
                ->groupBy('post_id')->pluck('cnt', 'post_id');

            $engagedCounts = DB::table('feed_interactions')
                ->whereIn('post_id', $chunkIds)
                ->where('type', '!=', 'view')
                ->selectRaw('post_id, COUNT(DISTINCT user_id) as cnt')
                ->groupBy('post_id')->pluck('cnt', 'post_id');

            $rows = [];
            foreach ($chunk as $post) {
                $hoursOld = max(1, now()->diffInMinutes($post->created_at) / 60);

                $engScore = ($post->likes_count * 1) + ($post->comments_count * 2)
                          + ($post->shares_count * 3) + ($post->saves_count * 2.5)
                          + ($post->views_count * 0.1);

                $velocity = $engScore / $hoursOld;

                // Quality: ratio of deep engagement (comments, shares, saves) to shallow (likes, views)
                $deep = $post->comments_count + $post->shares_count + $post->saves_count;
                $shallow = $post->likes_count + $post->views_count;
                $quality = $shallow > 0 ? ($deep / $shallow) * 10 : 0;

                // Viral: recent (last 2h) engagement velocity vs the prior 2h window,
                // normalized so a 1k-follower account and a 10-follower account are
                // compared on acceleration rather than raw volume.
                $recentEngagement = (int) ($recentCounts[$post->id] ?? 0);
                $olderEngagement = (int) ($olderCounts[$post->id] ?? 0);
                $viral = ($olderEngagement > 0)
                    ? ($recentEngagement / $olderEngagement) * min($recentEngagement, 20)
                    : $recentEngagement * 0.5;

                $impressions = (int) ($impressionCounts[$post->id] ?? 0);
                $engaged = (int) ($engagedCounts[$post->id] ?? 0);
                $engRate = $impressions > 0 ? $engaged / $impressions : 0;

                $final = ($engScore * 0.3) + ($velocity * 0.25) + ($quality * 0.15) + ($viral * 0.3);
                $final *= pow(0.5, $hoursOld / 12); // time decay

                $rows[] = [
                    'post_id'          => $post->id,
                    'engagement_score' => round($engScore, 4),
                    'velocity_score'   => round($velocity, 4),
                    'quality_score'    => round($quality, 4),
                    'viral_score'      => round($viral, 4),
                    'final_score'      => round($final, 4),
                    'impression_count' => $impressions,
                    'engaged_count'    => $engaged,
                    'engagement_rate'  => round($engRate, 4),
                    'last_scored_at'   => now(),
                    'updated_at'       => now(),
                    'created_at'       => now(),
                ];
            }

            DB::table('post_scores')->upsert($rows, ['post_id'], [
                'engagement_score', 'velocity_score', 'quality_score',
                'viral_score', 'final_score', 'impression_count',
                'engaged_count', 'engagement_rate', 'last_scored_at', 'updated_at',
            ]);
        }
    }

    /**
     * Update user interests based on recent interactions (run periodically).
     */
    public static function updateUserInterests(int $userId): void
    {
        $interactions = DB::table('feed_interactions')
            ->join('community_posts', 'community_posts.id', '=', 'feed_interactions.post_id')
            ->where('feed_interactions.user_id', $userId)
            ->where('feed_interactions.created_at', '>', now()->subDays(30))
            ->select('community_posts.id as post_id', 'community_posts.user_id as creator_id',
                     'community_posts.type', 'feed_interactions.type as action',
                     'feed_interactions.duration_ms', 'feed_interactions.created_at')
            ->get();

        if ($interactions->isEmpty()) return;

        $interests = [];

        foreach ($interactions as $i) {
            // Weight by action type
            $weight = match ($i->action) {
                'like'    => 1.0,
                'comment' => 3.0,
                'share'   => 4.0,
                'save'    => 3.5,
                'watch'   => min(($i->duration_ms ?? 0) / 10000, 3.0),
                'click'   => 0.5,
                default   => 0.1,
            };

            // Time decay: recent interactions worth more
            $daysAgo = now()->diffInDays($i->created_at);
            $decay = pow(0.9, $daysAgo);
            $weight *= $decay;

            // Creator interest
            $key = "creator:{$i->creator_id}";
            $interests[$key] = ($interests[$key] ?? 0) + $weight;

            // Post type interest
            $key = "type:{$i->type}";
            $interests[$key] = ($interests[$key] ?? 0) + $weight;

            // Hashtag interests
            $tags = DB::table('community_post_hashtags')
                ->join('community_hashtags', 'community_hashtags.id', '=', 'community_post_hashtags.hashtag_id')
                ->where('post_id', $i->post_id)
                ->pluck('community_hashtags.name');
            foreach ($tags as $tag) {
                $key = "hashtag:{$tag}";
                $interests[$key] = ($interests[$key] ?? 0) + $weight;
            }
        }

        // Normalize scores (0–10 scale)
        $maxScore = max(1, max($interests));
        $rows = [];
        foreach ($interests as $key => $score) {
            [$category, $value] = explode(':', $key, 2);
            $normalized = ($score / $maxScore) * 10;
            if ($normalized < 0.1) continue;

            $rows[] = [
                'user_id'              => $userId,
                'category'             => $category,
                'value'                => $value,
                'score'                => round($normalized, 4),
                'last_interaction_at'  => now(),
                'updated_at'           => now(),
                'created_at'           => now(),
            ];
        }

        if (!empty($rows)) {
            DB::table('user_interests')->upsert($rows,
                ['user_id', 'category', 'value'],
                ['score', 'last_interaction_at', 'updated_at']
            );
        }

        // Decay old interests not refreshed
        DB::table('user_interests')
            ->where('user_id', $userId)
            ->where('last_interaction_at', '<', now()->subDays(7))
            ->update(['score' => DB::raw('score * 0.5')]);

        // Remove dead interests
        DB::table('user_interests')
            ->where('user_id', $userId)
            ->where('score', '<', 0.05)
            ->delete();

        Cache::forget("user:{$userId}:interests");
    }

    /**
     * Decay interest scores for ALL users, not just ones who triggered an
     * update via a fresh interaction. Without this, a user who goes inactive
     * keeps their old interests pinned at full strength forever, since the
     * per-user decay inside updateUserInterests() only runs when that same
     * user generates a new interaction. Run daily via scheduler.
     */
    public static function decayAllUserInterests(): void
    {
        // Halve scores for interests not refreshed in 7+ days
        DB::table('user_interests')
            ->where('last_interaction_at', '<', now()->subDays(7))
            ->where('score', '>', 0.05)
            ->update(['score' => DB::raw('score * 0.5'), 'updated_at' => now()]);

        // Drop interests that have decayed below the noise floor
        $deleted = DB::table('user_interests')
            ->where('score', '<', 0.05)
            ->delete();

        // Invalidate cached interests for affected users so the next feed
        // request picks up the decayed scores instead of a stale cache hit.
        $affectedUsers = DB::table('user_interests')
            ->where('last_interaction_at', '<', now()->subDays(7))
            ->distinct()->pluck('user_id');
        foreach ($affectedUsers as $uid) {
            Cache::forget("user:{$uid}:interests");
        }
    }

    /**
     * Clean up old seen-posts data (run daily).
     */
    public static function cleanupSeenPosts(): void
    {
        DB::table('feed_seen_posts')
            ->where('seen_at', '<', now()->subDays(3))
            ->delete();
    }

    /**
     * Clean up old interaction data (run weekly).
     */
    public static function cleanupOldInteractions(): void
    {
        DB::table('feed_interactions')
            ->where('created_at', '<', now()->subDays(60))
            ->delete();
    }
}
