<?php
namespace App\Services;

use App\Models\CommunityPost;
use App\Models\CommunityFollow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * Feed Ranking Service — personalized post scoring engine.
 *
 * Scoring formula (multiplicative):
 *   final = baseVelocity × trendingBoost × qualityMultiplier
 *         × personalization × recencyBoost × timeDecay
 *         × negativeSignalPenalty
 *
 * Key design goals:
 *   - A post must NOT stay #1 for 3+ days without new engagement.
 *   - Velocity (engagement per hour) matters more than total counts.
 *   - Fresh posts get a temporary boost while they gather initial engagement.
 *   - Posts with accelerating engagement get a trending multiplier.
 *   - High impressions + low engagement = fatigue penalty.
 */
class FeedRankingService
{
    // ── Engagement weights (per-interaction type) ─────────────────────────────
    private const W_FOLLOW     = 5.0;  // from someone the user follows
    private const W_INTEREST   = 3.0;  // hashtag interest match
    private const W_LIKE       = 1.0;
    private const W_COMMENT    = 3.0;  // raised: comments signal real engagement
    private const W_SHARE      = 4.0;  // raised: shares = highest intent
    private const W_SAVE       = 3.5;
    private const W_VIEW       = 0.05; // lowered: view inflation prevention

    // ── Time decay — phase boundaries (hours) ────────────────────────────────
    // Phase 1 (0–3h):   no decay — posts need time to gather initial engagement
    // Phase 2 (3–24h):  half-life 18h — gradual, post still has runway
    // Phase 3 (24–72h): half-life 12h — faster; 48h post has ~11% of max score
    // Phase 4 (72h+):   half-life  8h — very aggressive; post must still earn
    private const DECAY_PHASE1_END  = 3;    // hours
    private const DECAY_PHASE2_END  = 24;
    private const DECAY_PHASE3_END  = 72;
    private const DECAY_HL_PHASE2   = 18.0; // half-life hours in phase 2
    private const DECAY_HL_PHASE3   = 12.0;
    private const DECAY_HL_PHASE4   = 8.0;

    // ── Recency boost (temporary multiplier for brand-new posts) ─────────────
    private const RECENCY_BOOST_1H  = 1.8;  // 0–1h
    private const RECENCY_BOOST_3H  = 1.4;  // 1–3h
    private const RECENCY_BOOST_6H  = 1.15; // 3–6h

    // ── Trending boost (from viral_score acceleration in post_scores) ─────────
    private const TREND_BOOST_LOW   = 1.3;  // viral_score ≥ 3
    private const TREND_BOOST_MID   = 1.6;  // viral_score ≥ 8
    private const TREND_BOOST_HIGH  = 2.0;  // viral_score ≥ 15

    // ── Quality multipliers (from engagement_rate in post_scores) ─────────────
    private const QUALITY_RATE_HIGH = 0.15; // 15%+ engaged viewers
    private const QUALITY_RATE_MED  = 0.05;
    private const QUALITY_MULT_HIGH = 1.4;
    private const QUALITY_MULT_MED  = 1.15;

    // ── Fatigue penalties (high impressions, nobody engages) ──────────────────
    private const FATIGUE_IMP_HIGH  = 200;  // impression threshold
    private const FATIGUE_IMP_MED   = 50;
    private const FATIGUE_RATE_HIGH = 0.01; // if engagement_rate below this
    private const FATIGUE_RATE_MED  = 0.02;
    private const FATIGUE_MULT_HIGH = 0.2;
    private const FATIGUE_MULT_MED  = 0.6;

    // ── Watch completion quality signal ───────────────────────────────────────
    private const WATCH_COMPL_HIGH  = 0.80; // 80%+ average completion
    private const WATCH_COMPL_MED   = 0.50;
    private const WATCH_COMPL_MULT_HIGH = 1.3;
    private const WATCH_COMPL_MULT_MED  = 1.1;
    private const WATCH_COMPL_LOW   = 0.20; // below 20% = poor quality video
    private const WATCH_COMPL_MULT_LOW  = 0.8;

    // ── Feed composition targets (pool percentages) ───────────────────────────
    private const MIX_FOLLOWING      = 40;
    private const MIX_RECOMMENDED    = 30;
    private const MIX_TRENDING       = 15;
    private const MIX_NEW_CREATORS   = 10;
    private const MIX_RANDOM         = 5;

    // ── Anti-flood / diversity ─────────────────────────────────────────────────
    private const MAX_PER_CREATOR          = 2;
    private const MAX_PER_TOPIC            = 3;
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
        $this->userId             = $userId;
        $this->followingIds       = $this->getFollowingIds();
        $this->userInterests      = $this->getUserInterests();
        $this->seenPostIds        = $this->getRecentSeenPosts();
        $this->interactionHistory = $this->getRecentInteractions();
        $this->negativeSignals    = $this->getNegativeSignals();
        $this->sessionSignals     = $this->getSessionSignals();
    }

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Build a personalized feed page. Returns post IDs in ranked order.
     */
    public function buildFeed(int $page = 1, int $perPage = 15): array
    {
        $cacheKey = "feed:v3:{$this->userId}:p{$page}";
        $cached = Cache::get($cacheKey);
        if ($cached && $page > 1) return $cached;

        $candidates  = $this->gatherCandidates($page, $perPage);
        $scored      = $this->scorePostsBatch($candidates);
        $diversified = $this->applyDiversity($scored, $perPage);
        $result      = array_slice($diversified, 0, $perPage);

        $this->markSeen(array_column($result, 'post_id'));
        Cache::put($cacheKey, $result, 120);

        return $result;
    }

    // ─── Candidate gathering ──────────────────────────────────────────────────

    private function gatherCandidates(int $page, int $perPage): Collection
    {
        $candidateCount = $perPage * 5;
        $offset = ($page - 1) * $perPage;

        $hasFollowing    = !empty($this->followingIds);
        $hasInteractions = !empty($this->getInteractedCreators());
        $discoveryBoost  = (!$hasFollowing && !$hasInteractions) ? 2.0 : 1.0;

        $base = CommunityPost::query()
            ->whereNull('group_id')
            ->where('privacy', '!=', 'private')
            ->whereNull('deleted_at')
            ->where('created_at', '>', now()->subDays(14))
            ->where(fn ($q) => $q->where('video_ready', true)->orWhere('user_id', $this->userId));

        // Exclude recently seen posts — only when enough unseen content exists
        if (!empty($this->seenPostIds)) {
            $recentSeen   = DB::table('feed_seen_posts')
                ->where('user_id', $this->userId)
                ->where('seen_at', '>', now()->subHours(24))
                ->pluck('post_id')->toArray();
            $totalAvailable  = (clone $base)->count();
            $unseenAvailable = $totalAvailable - count($recentSeen);
            if (!empty($recentSeen) && $unseenAvailable >= $perPage) {
                $base->whereNotIn('id', $recentSeen);
            }
        }

        $cols = ['id', 'user_id', 'type', 'content', 'location',
                 'views_count', 'likes_count', 'comments_count',
                 'shares_count', 'saves_count', 'created_at'];

        // Pool 1: Following
        $followingPosts = (clone $base)
            ->whereIn('user_id', $this->followingIds)
            ->latest()->limit($candidateCount)->offset($offset)->get($cols);

        // Pool 2: Recommended (interacted-with creators not followed)
        $interactedCreators = $this->getInteractedCreators();
        $recommendedPosts = collect();
        if (!empty($interactedCreators)) {
            $recommendedPosts = (clone $base)
                ->whereIn('user_id', $interactedCreators)
                ->whereNotIn('user_id', $this->followingIds)
                ->latest()->limit(round($candidateCount * 0.3))->offset(round($offset * 0.3))
                ->get($cols);
        }

        // Pool 3: Trending (high per-hour velocity, last 3 days)
        $trendingPosts = (clone $base)
            ->whereNotIn('user_id', $this->followingIds)
            ->where('created_at', '>', now()->subDays(3))
            ->whereRaw('(likes_count + comments_count * 2 + shares_count * 3) > ?', [10])
            ->orderByRaw('(likes_count + comments_count * 2 + shares_count * 3) / GREATEST(1, TIMESTAMPDIFF(HOUR, created_at, NOW())) DESC')
            ->limit((int) round($candidateCount * 0.2 * $discoveryBoost))->offset(round($offset * 0.2))
            ->get($cols);

        // Pool 4: New creators (< 100 followers, last 2 days) — re-sampled each page
        $newCreatorPosts = (clone $base)
            ->whereNotIn('user_id', $this->followingIds)
            ->where('created_at', '>', now()->subDays(2))
            ->where(function ($q) {
                $q->whereHas('user.communityProfile', fn ($p) => $p->where('followers_count', '<', 100))
                  ->orWhereDoesntHave('user.communityProfile');
            })
            ->inRandomOrder()->limit((int) round($candidateCount * 0.1 * $discoveryBoost))->get($cols);

        // Pool 5: Random discovery
        $randomPosts = (clone $base)
            ->whereNotIn('user_id', $this->followingIds)
            ->where('created_at', '>', now()->subDays(7))
            ->inRandomOrder()->limit((int) round($candidateCount * 0.05 * $discoveryBoost))->get($cols);

        return $followingPosts->concat($recommendedPosts)->concat($trendingPosts)
            ->concat($newCreatorPosts)->concat($randomPosts)->unique('id');
    }

    // ─── Scoring ──────────────────────────────────────────────────────────────

    private function scorePostsBatch(Collection $candidates): array
    {
        if ($candidates->isEmpty()) return [];

        $postIds = $candidates->pluck('id')->toArray();

        $precomputed = DB::table('post_scores')
            ->whereIn('post_id', $postIds)->get()->keyBy('post_id');

        $postHashtags = DB::table('community_post_hashtags')
            ->join('community_hashtags', 'community_hashtags.id', '=', 'community_post_hashtags.hashtag_id')
            ->whereIn('post_id', $postIds)
            ->get(['post_id', 'community_hashtags.name'])
            ->groupBy('post_id')
            ->map(fn ($tags) => $tags->pluck('name')->toArray());

        $scored = [];
        foreach ($candidates as $post) {
            $score = $this->scorePost($post, $precomputed, $postHashtags);
            $tags  = $postHashtags[$post->id] ?? [];
            $scored[] = [
                'post_id' => $post->id,
                'user_id' => $post->user_id,
                'type'    => $post->type,
                'topic'   => $tags[0] ?? null,
                'score'   => $score,
                'pool'    => $this->classifyPool($post),
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
    }

    /**
     * Score a single post for this user.
     *
     * Formula:
     *   score = baseVelocity (log-scaled hourly velocity)
     *         × trendingBoost (from viral_score acceleration)
     *         × qualityMultiplier (engagement_rate + watch completion)
     *         + personalization (follow bonus + interest match)
     *         × contentTypeBonus
     *         × recencyBoost (temporary: fades after 6h)
     *         × timeDecay (aggressive 4-phase)
     *         × negativeSignalPenalty
     */
    private function scorePost($post, $precomputed, $postHashtags): float
    {
        $hoursOld = max(0.1, now()->diffInMinutes($post->created_at) / 60);
        $tags     = $postHashtags[$post->id] ?? [];
        $pre      = $precomputed[$post->id] ?? null;

        // ── 1. Engagement velocity (per-hour, not raw totals) ─────────────────
        // Dividing by hoursOld penalizes old posts with large historical counts
        // — a post with 1000 likes at 72h (≈13.9/h) scores much lower than one
        // with 50 likes in 2h (25/h) BEFORE decay is even applied.
        $rawVelocity = (($post->likes_count    * self::W_LIKE)
                      + ($post->comments_count * self::W_COMMENT)
                      + ($post->shares_count   * self::W_SHARE)
                      + ($post->saves_count    * self::W_SAVE)
                      + ($post->views_count    * self::W_VIEW))
                      / $hoursOld;

        // Use actual 24h velocity from post_scores when available — more
        // accurate than simply dividing lifetime totals by age.
        if ($pre && isset($pre->velocity_24h) && $pre->velocity_24h > 0) {
            // Blend: 60% real-24h, 40% lifetime — real-24h more responsive,
            // lifetime provides stability for posts with short history.
            $rawVelocity = ($pre->velocity_24h * 0.6) + ($rawVelocity * 0.4);
        }

        $score = log1p($rawVelocity) * 4.0;

        // ── 2. Trending boost (from pre-computed viral/acceleration score) ─────
        if ($pre) {
            $viralScore = (float) ($pre->viral_score ?? 0);
            if ($viralScore >= 15) {
                $score *= self::TREND_BOOST_HIGH;
            } elseif ($viralScore >= 8) {
                $score *= self::TREND_BOOST_MID;
            } elseif ($viralScore >= 3) {
                $score *= self::TREND_BOOST_LOW;
            }

            // ── 3. Quality multiplier (engagement rate) ───────────────────────
            $engRate = (float) ($pre->engagement_rate ?? 0);
            if ($engRate >= self::QUALITY_RATE_HIGH) {
                $score *= self::QUALITY_MULT_HIGH;
            } elseif ($engRate >= self::QUALITY_RATE_MED) {
                $score *= self::QUALITY_MULT_MED;
            }

            // ── 4. Watch completion quality (video posts) ─────────────────────
            $watchCompl = isset($pre->watch_completion) ? (float) $pre->watch_completion : null;
            if ($watchCompl !== null && $watchCompl > 0) {
                if ($watchCompl >= self::WATCH_COMPL_HIGH) {
                    $score *= self::WATCH_COMPL_MULT_HIGH;
                } elseif ($watchCompl >= self::WATCH_COMPL_MED) {
                    $score *= self::WATCH_COMPL_MULT_MED;
                } elseif ($watchCompl < self::WATCH_COMPL_LOW) {
                    $score *= self::WATCH_COMPL_MULT_LOW;
                }
            }

            // ── 5. Fatigue penalty (everyone saw it, nobody engaged) ───────────
            $impressions = (int) ($pre->impression_count ?? 0);
            if ($impressions >= self::FATIGUE_IMP_HIGH && $engRate < self::FATIGUE_RATE_HIGH) {
                $score *= self::FATIGUE_MULT_HIGH;
            } elseif ($impressions >= self::FATIGUE_IMP_MED && $engRate < self::FATIGUE_RATE_MED) {
                $score *= self::FATIGUE_MULT_MED;
            }

            // ── 6. Negative interaction penalty from post_scores ──────────────
            $negCount = (int) ($pre->negative_count ?? 0);
            if ($negCount > 0 && $impressions > 0) {
                $negRate = $negCount / $impressions;
                if ($negRate > 0.05) $score *= 0.4; // >5% hide/report rate
                elseif ($negRate > 0.02) $score *= 0.7;
            }
        }

        // ── 7. Personalization: relationship + interest matching ───────────────
        // Added AFTER multipliers so follow bonus isn't reduced by quality/fatigue.
        if (in_array($post->user_id, $this->followingIds)) {
            $score += self::W_FOLLOW;
            $closeness = $this->interactionHistory[$post->user_id] ?? 0;
            $score += min($closeness * 0.5, 5.0); // closeness bonus, capped at 5
        }

        foreach ($tags as $tag) {
            $score += ($this->userInterests["hashtag:{$tag}"] ?? 0) * self::W_INTEREST;
        }
        $score += ($this->userInterests["type:{$post->type}"]          ?? 0) * 1.5;
        $score += ($this->userInterests["creator:{$post->user_id}"]    ?? 0) * 2.0;

        // ── 8. Content type bonus (media posts slightly preferred) ─────────────
        if (in_array($post->type, ['video', 'reel', 'image'])) {
            $score *= 1.1;
        }

        // ── 9. Recency boost — temporary multiplier for brand-new posts ────────
        // This helps new posts surface while they gather engagement, then burns
        // off naturally as time decay takes over.
        if ($hoursOld <= 1) {
            $score *= self::RECENCY_BOOST_1H;
        } elseif ($hoursOld <= 3) {
            $score *= self::RECENCY_BOOST_3H;
        } elseif ($hoursOld <= 6) {
            $score *= self::RECENCY_BOOST_6H;
        }

        // ── 10. Time decay (aggressive 4-phase) ────────────────────────────────
        $score *= $this->timeDecay($hoursOld);

        // ── 11. Seen penalty ───────────────────────────────────────────────────
        if (in_array($post->id, $this->seenPostIds)) {
            $score *= 0.1;
        }

        // ── 12. Persistent negative signals (skips, "not interested") ──────────
        if (!empty($this->negativeSignals)) {
            $penalty = 0.0;
            $penalty += ($this->negativeSignals["creator:{$post->user_id}"] ?? 0) * 2.0;
            $penalty += ($this->negativeSignals["type:{$post->type}"]       ?? 0) * 1.0;
            foreach ($tags as $tag) {
                $penalty += ($this->negativeSignals["hashtag:{$tag}"] ?? 0) * 1.5;
            }
            if ($penalty > 0) {
                $score *= 1 / (1 + $penalty * 0.3);
            }
        }

        // ── 13. Session-scoped signals (heavier weight, short-lived) ───────────
        if (!empty($this->sessionSignals)) {
            $sessionPenalty = 0.0;
            $sessionPenalty += (int) ($this->sessionSignals["creator:{$post->user_id}"] ?? 0) * 1.0;
            $sessionPenalty += (int) ($this->sessionSignals["type:{$post->type}"]       ?? 0) * 0.5;
            foreach ($tags as $tag) {
                $sessionPenalty += (int) ($this->sessionSignals["hashtag:{$tag}"] ?? 0) * 0.8;
            }
            if ($sessionPenalty > 0) {
                $score *= 1 / (1 + $sessionPenalty * 0.6);
            }
        }

        return max(0.001, $score);
    }

    /**
     * Aggressive 4-phase time decay.
     *
     * Decay values at key ages:
     *   3h  → 1.000 (no decay)
     *   12h → 0.671
     *   24h → 0.444
     *   48h → 0.111
     *   72h → 0.028
     *   96h → 0.004
     *
     * A post that was viral 3 days ago (72h) retains only ~2.8% of its score.
     * A fresh post (< 3h) keeps 100%. This ensures new content can surface
     * even with few likes, as long as it has decent per-hour velocity.
     */
    private function timeDecay(float $hoursOld): float
    {
        if ($hoursOld <= self::DECAY_PHASE1_END) {
            return 1.0;
        }

        if ($hoursOld <= self::DECAY_PHASE2_END) {
            // Phase 2: half-life DECAY_HL_PHASE2
            return pow(0.5, ($hoursOld - self::DECAY_PHASE1_END) / self::DECAY_HL_PHASE2);
        }

        // Value at end of phase 2 (start of phase 3)
        $atPhase2End = pow(0.5, (self::DECAY_PHASE2_END - self::DECAY_PHASE1_END) / self::DECAY_HL_PHASE2);

        if ($hoursOld <= self::DECAY_PHASE3_END) {
            // Phase 3: faster half-life
            return $atPhase2End * pow(0.5, ($hoursOld - self::DECAY_PHASE2_END) / self::DECAY_HL_PHASE3);
        }

        // Value at end of phase 3 (start of phase 4)
        $atPhase3End = $atPhase2End * pow(0.5, (self::DECAY_PHASE3_END - self::DECAY_PHASE2_END) / self::DECAY_HL_PHASE3);

        // Phase 4: very aggressive half-life
        return $atPhase3End * pow(0.5, ($hoursOld - self::DECAY_PHASE3_END) / self::DECAY_HL_PHASE4);
    }

    // ─── Diversity ────────────────────────────────────────────────────────────

    private function applyDiversity(array $scored, int $perPage): array
    {
        $result       = [];
        $creatorCounts = [];
        $topicCounts  = [];
        $lastType     = null;
        $consecutiveType = 0;
        $typeCounts   = ['following' => 0, 'recommended' => 0, 'trending' => 0, 'new_creator' => 0, 'random' => 0];
        $typeTargets  = [
            'following'   => ceil($perPage * self::MIX_FOLLOWING / 100),
            'recommended' => ceil($perPage * self::MIX_RECOMMENDED / 100),
            'trending'    => ceil($perPage * self::MIX_TRENDING / 100),
            'new_creator' => ceil($perPage * self::MIX_NEW_CREATORS / 100),
            'random'      => ceil($perPage * self::MIX_RANDOM / 100),
        ];

        foreach ($scored as $item) {
            if (count($result) >= $perPage) break;

            $creatorId   = $item['user_id'];
            $pool        = $item['pool'];
            $topic       = $item['topic'];
            $contentType = $item['type'];

            if (($creatorCounts[$creatorId] ?? 0) >= self::MAX_PER_CREATOR) continue;
            if ($topic !== null && ($topicCounts[$topic] ?? 0) >= self::MAX_PER_TOPIC) continue;
            if ($contentType === $lastType && $consecutiveType >= self::MAX_CONSECUTIVE_SAME_TYPE) continue;
            if (($typeCounts[$pool] ?? 0) >= ($typeTargets[$pool] ?? $perPage) * 1.5) continue;

            $result[] = $item;
            $creatorCounts[$creatorId]       = ($creatorCounts[$creatorId] ?? 0) + 1;
            if ($topic !== null) $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
            $typeCounts[$pool]               = ($typeCounts[$pool] ?? 0) + 1;
            $consecutiveType = ($contentType === $lastType) ? $consecutiveType + 1 : 1;
            $lastType = $contentType;
        }

        // Backfill pass 1: relax topic/type/pool limits, allow one extra per creator
        if (count($result) < $perPage) {
            $usedIds         = array_column($result, 'post_id');
            $relaxedCreator  = self::MAX_PER_CREATOR + 1;
            foreach ($scored as $item) {
                if (count($result) >= $perPage) break;
                if (in_array($item['post_id'], $usedIds)) continue;
                if (($creatorCounts[$item['user_id']] ?? 0) >= $relaxedCreator) continue;
                $result[] = $item;
                $creatorCounts[$item['user_id']] = ($creatorCounts[$item['user_id']] ?? 0) + 1;
            }
        }

        // Backfill pass 2: ignore all rules (tiny pool edge case)
        if (count($result) < $perPage) {
            $usedIds = array_column($result, 'post_id');
            foreach ($scored as $item) {
                if (count($result) >= $perPage) break;
                if (!in_array($item['post_id'], $usedIds)) $result[] = $item;
            }
        }

        return $result;
    }

    private function classifyPool($post): string
    {
        if (in_array($post->user_id, $this->followingIds)) return 'following';

        if (in_array($post->user_id, $this->getInteractedCreators())) return 'recommended';

        $hoursOld = max(1, now()->diffInMinutes($post->created_at) / 60);
        $engScore = $post->likes_count + $post->comments_count * 2 + $post->shares_count * 3;
        if ($engScore > 10 && $hoursOld < 72) return 'trending';

        return 'random';
    }

    // ─── Data loaders ─────────────────────────────────────────────────────────

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
                ->orderByDesc('score')->limit(100)->get()
                ->mapWithKeys(fn ($i) => ["{$i->category}:{$i->value}" => $i->score])
                ->toArray()
        );
    }

    private function getRecentSeenPosts(): array
    {
        return DB::table('feed_seen_posts')
            ->where('user_id', $this->userId)
            ->where('seen_at', '>', now()->subHours(24))
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
                ->pluck('cnt', 'creator_id')->toArray()
        );
    }

    private function getInteractedCreators(): array
    {
        return array_keys($this->interactionHistory);
    }

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

            $postIds    = $skips->pluck('post_id')->toArray();
            $tagsByPost = DB::table('community_post_hashtags')
                ->join('community_hashtags', 'community_hashtags.id', '=', 'community_post_hashtags.hashtag_id')
                ->whereIn('post_id', $postIds)
                ->get(['post_id', 'community_hashtags.name'])
                ->groupBy('post_id')
                ->map(fn ($t) => $t->pluck('name')->toArray());

            $penalties = [];
            foreach ($skips as $s) {
                $weight  = pow(0.9, now()->diffInDays($s->created_at));
                $penalties["creator:{$s->creator_id}"] = ($penalties["creator:{$s->creator_id}"] ?? 0) + $weight;
                $penalties["type:{$s->type}"]          = ($penalties["type:{$s->type}"] ?? 0) + ($weight * 0.5);
                foreach ($tagsByPost[$s->post_id] ?? [] as $tag) {
                    $penalties["hashtag:{$tag}"] = ($penalties["hashtag:{$tag}"] ?? 0) + $weight;
                }
            }
            return $penalties;
        });
    }

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

    // ─── Static scoring jobs ──────────────────────────────────────────────────

    /**
     * Recompute post scores every 5 minutes (called by scheduler).
     *
     * Pre-computed metrics stored in post_scores:
     *   - engagement_score  : weighted lifetime engagement total
     *   - velocity_score    : lifetime engScore / hoursOld (blended reference)
     *   - velocity_24h      : actual interactions-per-hour over last 24h
     *   - quality_score     : deep/shallow engagement ratio
     *   - viral_score       : acceleration — recent (last 2h) vs prior (2–4h) window
     *   - watch_completion  : avg video completion fraction (0.0–1.0)
     *   - negative_count    : total skip + report actions on this post
     *   - impression_count  : how many unique users have seen it
     *   - engaged_count     : how many unique users have interacted
     *   - engagement_rate   : engaged_count / impression_count
     *   - final_score       : composite (for admin dashboards, not feed ranking)
     */
    public static function recomputePostScores(): void
    {
        $posts = CommunityPost::where('created_at', '>', now()->subDays(14))
            ->whereNull('deleted_at')
            ->get(['id', 'user_id', 'type', 'views_count', 'likes_count',
                   'comments_count', 'shares_count', 'saves_count', 'created_at']);

        foreach ($posts->chunk(200) as $chunk) {
            $chunkIds = $chunk->pluck('id')->toArray();

            // ── Batch queries (one query per metric, no N+1) ──────────────────

            // Viral: recent (last 2h) vs prior (2–4h) — DISTINCT user_id prevents
            // one user's rapid-fire views inflating the acceleration metric.
            $recentCounts = DB::table('feed_interactions')
                ->whereIn('post_id', $chunkIds)
                ->where('created_at', '>', now()->subHours(2))
                ->selectRaw('post_id, COUNT(DISTINCT user_id) as cnt')
                ->groupBy('post_id')->pluck('cnt', 'post_id');

            $olderCounts = DB::table('feed_interactions')
                ->whereIn('post_id', $chunkIds)
                ->whereBetween('created_at', [now()->subHours(4), now()->subHours(2)])
                ->selectRaw('post_id, COUNT(DISTINCT user_id) as cnt')
                ->groupBy('post_id')->pluck('cnt', 'post_id');

            // velocity_24h: raw interaction count per hour over last 24h
            $velocity24hCounts = DB::table('feed_interactions')
                ->whereIn('post_id', $chunkIds)
                ->where('created_at', '>', now()->subHours(24))
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

            // Watch completion: avg fraction of video watched.
            // duration_ms from feed_interactions is total ms watched;
            // we join community_post_media to get the reference duration.
            $watchData = DB::table('feed_interactions as fi')
                ->join('community_post_media as m', 'm.post_id', '=', 'fi.post_id')
                ->whereIn('fi.post_id', $chunkIds)
                ->where('fi.type', 'watch')
                ->whereNotNull('fi.duration_ms')
                ->whereRaw('m.duration_ms > 0')
                ->selectRaw('fi.post_id, AVG(LEAST(fi.duration_ms / m.duration_ms, 1.0)) as avg_completion')
                ->groupBy('fi.post_id')->pluck('avg_completion', 'post_id');

            // Negative interactions: skips + reports
            $negativeCounts = DB::table('feed_interactions')
                ->whereIn('post_id', $chunkIds)
                ->whereIn('type', ['skip', 'report'])
                ->selectRaw('post_id, COUNT(*) as cnt')
                ->groupBy('post_id')->pluck('cnt', 'post_id');

            $rows = [];
            foreach ($chunk as $post) {
                $hoursOld = max(1, now()->diffInMinutes($post->created_at) / 60);

                $engScore = ($post->likes_count * 1)   + ($post->comments_count * 2)
                          + ($post->shares_count * 3)  + ($post->saves_count * 2.5)
                          + ($post->views_count * 0.1);

                $velocity = $engScore / $hoursOld;

                // velocity_24h: real interaction rate (interactions / 24h capped by age)
                $raw24hCount = (int) ($velocity24hCounts[$post->id] ?? 0);
                $window24h   = min($hoursOld, 24);
                $velocity24h = $raw24hCount / max(1, $window24h);

                // Quality: deep-to-shallow engagement ratio
                $deep    = $post->comments_count + $post->shares_count + $post->saves_count;
                $shallow = $post->likes_count + $post->views_count;
                $quality = $shallow > 0 ? ($deep / $shallow) * 10 : 0;

                // Viral: acceleration — recent/older × min(recent, 20)
                $recentEng = (int) ($recentCounts[$post->id] ?? 0);
                $olderEng  = (int) ($olderCounts[$post->id] ?? 0);
                $viral = ($olderEng > 0)
                    ? ($recentEng / $olderEng) * min($recentEng, 20)
                    : $recentEng * 0.5;

                $impressions    = (int) ($impressionCounts[$post->id] ?? 0);
                $engaged        = (int) ($engagedCounts[$post->id] ?? 0);
                $engRate        = $impressions > 0 ? $engaged / $impressions : 0;
                $watchCompletion = isset($watchData[$post->id]) ? (float) $watchData[$post->id] : null;
                $negativeCount  = (int) ($negativeCounts[$post->id] ?? 0);

                // final_score: composite for admin dashboards (not used in feed ranking)
                $final = ($engScore * 0.25) + ($velocity24h * 0.30) + ($quality * 0.15) + ($viral * 0.30);
                $final *= pow(0.5, $hoursOld / 12); // simple decay for dashboard display

                $rows[] = [
                    'post_id'          => $post->id,
                    'engagement_score' => round($engScore, 4),
                    'velocity_score'   => round($velocity, 4),
                    'velocity_24h'     => round($velocity24h, 4),
                    'quality_score'    => round($quality, 4),
                    'viral_score'      => round($viral, 4),
                    'final_score'      => round($final, 4),
                    'watch_completion' => $watchCompletion !== null ? round($watchCompletion, 4) : null,
                    'negative_count'   => $negativeCount,
                    'impression_count' => $impressions,
                    'engaged_count'    => $engaged,
                    'engagement_rate'  => round($engRate, 4),
                    'last_scored_at'   => now(),
                    'updated_at'       => now(),
                    'created_at'       => now(),
                ];
            }

            DB::table('post_scores')->upsert($rows, ['post_id'], [
                'engagement_score', 'velocity_score', 'velocity_24h',
                'quality_score', 'viral_score', 'final_score',
                'watch_completion', 'negative_count',
                'impression_count', 'engaged_count', 'engagement_rate',
                'last_scored_at', 'updated_at',
            ]);
        }
    }

    // ─── Interest maintenance ─────────────────────────────────────────────────

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

        $postIds = $interactions->pluck('post_id')->unique()->values();
        $hashtagsByPost = DB::table('community_post_hashtags')
            ->join('community_hashtags', 'community_hashtags.id', '=', 'community_post_hashtags.hashtag_id')
            ->whereIn('post_id', $postIds)
            ->select('post_id', 'community_hashtags.name')
            ->get()->groupBy('post_id')->map(fn ($rows) => $rows->pluck('name'));

        $interests = [];
        foreach ($interactions as $i) {
            $weight = match ($i->action) {
                'like'    => 1.0,
                'comment' => 3.0,
                'share'   => 4.0,
                'save'    => 3.5,
                'watch'   => min(($i->duration_ms ?? 0) / 10000, 3.0),
                'click'   => 0.5,
                default   => 0.1,
            };
            $weight *= pow(0.9, now()->diffInDays($i->created_at));

            $interests["creator:{$i->creator_id}"] = ($interests["creator:{$i->creator_id}"] ?? 0) + $weight;
            $interests["type:{$i->type}"]          = ($interests["type:{$i->type}"] ?? 0) + $weight;
            foreach ($hashtagsByPost->get($i->post_id, collect()) as $tag) {
                $interests["hashtag:{$tag}"] = ($interests["hashtag:{$tag}"] ?? 0) + $weight;
            }
        }

        $maxScore = max(1, max($interests));
        $rows = [];
        foreach ($interests as $key => $score) {
            [$category, $value] = explode(':', $key, 2);
            $normalized = ($score / $maxScore) * 10;
            if ($normalized < 0.1) continue;
            $rows[] = [
                'user_id'             => $userId,
                'category'            => $category,
                'value'               => $value,
                'score'               => round($normalized, 4),
                'last_interaction_at' => now(),
                'updated_at'          => now(),
                'created_at'          => now(),
            ];
        }

        if (!empty($rows)) {
            DB::table('user_interests')->upsert($rows,
                ['user_id', 'category', 'value'],
                ['score', 'last_interaction_at', 'updated_at']
            );
        }

        DB::table('user_interests')
            ->where('user_id', $userId)
            ->where('last_interaction_at', '<', now()->subDays(7))
            ->update(['score' => DB::raw('score * 0.5')]);

        DB::table('user_interests')
            ->where('user_id', $userId)->where('score', '<', 0.05)->delete();

        Cache::forget("user:{$userId}:interests");
    }

    public static function decayAllUserInterests(): void
    {
        DB::table('user_interests')
            ->where('last_interaction_at', '<', now()->subDays(7))
            ->where('score', '>', 0.05)
            ->update(['score' => DB::raw('score * 0.5'), 'updated_at' => now()]);

        DB::table('user_interests')->where('score', '<', 0.05)->delete();

        $affectedUsers = DB::table('user_interests')
            ->where('last_interaction_at', '<', now()->subDays(7))
            ->distinct()->pluck('user_id');
        foreach ($affectedUsers as $uid) Cache::forget("user:{$uid}:interests");
    }

    public static function cleanupSeenPosts(): void
    {
        DB::table('feed_seen_posts')->where('seen_at', '<', now()->subDays(3))->delete();
    }

    public static function cleanupOldInteractions(): void
    {
        DB::table('feed_interactions')->where('created_at', '<', now()->subDays(60))->delete();
    }
}
