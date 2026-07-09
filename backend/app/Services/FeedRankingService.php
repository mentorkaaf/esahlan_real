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
 * Feed Ranking Service — production-grade personalized scoring engine.
 *
 * Scoring formula (multiplicative):
 *   final = impressionCTR × dwellBoost × trendingBoost × qualityMultiplier
 *         + personalization
 *         × contentTypeBonus × recencyBoost
 *         × adaptiveTimeDecay(hoursOld, velocity24h)
 *         × distributionStageFactor × negativeSignalPenalty
 *
 * Five advanced features layered onto the base algorithm:
 *   1. Impression-based normalized rates (CTR, like/comment/save/share rate)
 *   2. Dwell time & watch behaviour (avg_dwell_ms, completion, rewatches)
 *   3. Progressive distribution (staged rollout: 100 → 1k → 10k → 100k → viral)
 *   4. Extended diversity engine (content-type mix targets + pool caps)
 *   5. Adaptive time decay (living posts decay slower; dead posts decay faster)
 */
class FeedRankingService
{
    // ── Engagement weights ────────────────────────────────────────────────────
    private const W_FOLLOW    = 5.0;
    private const W_INTEREST  = 3.0;
    private const W_LIKE      = 1.0;
    private const W_COMMENT   = 3.0;
    private const W_SHARE     = 4.0;
    private const W_SAVE      = 3.5;
    private const W_VIEW      = 0.05;

    // ── Feature 1: Impression-normalized CTR scoring ──────────────────────────
    // Used when impression_count >= MIN_IMP_FOR_RATES (post has enough signal)
    private const MIN_IMP_FOR_RATES   = 10;   // minimum impressions to trust rates
    private const W_CTR_LIKE          = 1.0;
    private const W_CTR_COMMENT       = 3.0;
    private const W_CTR_SAVE          = 3.5;
    private const W_CTR_SHARE         = 4.0;
    private const CTR_SCALE           = 100.0; // scale rates into log1p-friendly range

    // ── Feature 2: Dwell time thresholds (milliseconds) ──────────────────────
    private const DWELL_HIGH_MS   = 10_000; // 10s+ → strong signal
    private const DWELL_MED_MS    = 4_000;  // 4-10s → mild signal
    private const DWELL_LOW_MS    = 800;    // <0.8s → quick scroll-away
    private const DWELL_MULT_HIGH = 1.45;
    private const DWELL_MULT_MED  = 1.20;
    private const DWELL_MULT_LOW  = 0.75;   // penalty for scroll-aways
    private const REWATCH_MULT    = 1.25;   // bonus when same user rewatches

    // ── Feature 3: Progressive distribution stage gates ───────────────────────
    // Stage 0 (<100 imp): seed audience only — non-followers see reduced score
    // Stage 1 (<1k imp) : limited — small penalty for non-followers
    // Stage 2+           : open distribution
    private const DIST_STAGE0_FACTOR = 0.20; // non-follower score × 0.20 at stage 0
    private const DIST_STAGE1_FACTOR = 0.60; // non-follower score × 0.60 at stage 1

    // Stage promotion thresholds [min_impressions, min_engagement_rate, new_cap]
    private const DIST_THRESHOLDS = [
        0 => [20,    0.05, 1_000],
        1 => [200,   0.04, 10_000],
        2 => [2_000, 0.03, 100_000],
        3 => [20_000, 0.02, 9_999_999],
    ];

    // ── Feature 4: Diversity engine — content-type mix targets (%) ───────────
    private const MIX_TYPE_VIDEO  = 30;
    private const MIX_TYPE_REEL   = 15;
    private const MIX_TYPE_IMAGE  = 25;
    private const MIX_TYPE_TEXT   = 20;
    private const MIX_TYPE_AUDIO  = 5;
    private const MIX_TYPE_OTHER  = 5;  // poll, service, document, share…
    // Any single content group is capped at this fraction of the page
    private const MIX_TYPE_HARD_CAP = 0.50;

    // ── Feature 5: Adaptive time decay — phase boundaries & half-lives ────────
    private const DECAY_PHASE1_END  = 3;    // hours — no decay zone
    private const DECAY_PHASE2_END  = 24;
    private const DECAY_PHASE3_END  = 72;
    private const DECAY_HL_PHASE2   = 18.0; // half-life in phase 2 (3-24h)
    private const DECAY_HL_PHASE3   = 12.0; // half-life in phase 3 (24-72h)
    private const DECAY_HL_PHASE4   = 8.0;  // half-life in phase 4 (72h+)
    // Adaptive decay modifiers based on velocity_24h (interactions/hour)
    private const DECAY_ADAPT_STRONG_V  = 10.0; // velocity above this → slow decay
    private const DECAY_ADAPT_MED_V     = 3.0;
    private const DECAY_ADAPT_DEAD_V    = 0.1;  // velocity below this → fast decay
    private const DECAY_ADAPT_STRONG    = 0.60; // exponent < 1 slows the decay
    private const DECAY_ADAPT_MED       = 0.80;
    private const DECAY_ADAPT_DEAD      = 1.50; // exponent > 1 accelerates decay

    // ── Trending boost (from viral_score acceleration) ────────────────────────
    private const TREND_BOOST_LOW   = 1.3;
    private const TREND_BOOST_MID   = 1.6;
    private const TREND_BOOST_HIGH  = 2.0;

    // ── Quality signals ────────────────────────────────────────────────────────
    private const QUALITY_RATE_HIGH  = 0.15;
    private const QUALITY_RATE_MED   = 0.05;
    private const QUALITY_MULT_HIGH  = 1.40;
    private const QUALITY_MULT_MED   = 1.15;
    private const WATCH_COMPL_HIGH   = 0.80;
    private const WATCH_COMPL_MED    = 0.50;
    private const WATCH_COMPL_LOW    = 0.20;
    private const WATCH_COMPL_MULT_H = 1.30;
    private const WATCH_COMPL_MULT_M = 1.10;
    private const WATCH_COMPL_MULT_L = 0.80;

    // ── Fatigue penalty ────────────────────────────────────────────────────────
    private const FATIGUE_IMP_HIGH   = 200;
    private const FATIGUE_IMP_MED    = 50;
    private const FATIGUE_RATE_HIGH  = 0.01;
    private const FATIGUE_RATE_MED   = 0.02;
    private const FATIGUE_MULT_HIGH  = 0.20;
    private const FATIGUE_MULT_MED   = 0.60;

    // ── Feed pool composition ─────────────────────────────────────────────────
    private const MIX_FOLLOWING    = 40;
    private const MIX_RECOMMENDED  = 30;
    private const MIX_TRENDING     = 15;
    private const MIX_NEW_CREATORS = 10;
    private const MIX_RANDOM       = 5;

    // ── Anti-flood ─────────────────────────────────────────────────────────────
    private const MAX_PER_CREATOR           = 2;
    private const MAX_PER_TOPIC             = 3;
    private const MAX_CONSECUTIVE_SAME_TYPE = 2;

    // ── Recency boost ─────────────────────────────────────────────────────────
    private const RECENCY_BOOST_1H  = 1.80;
    private const RECENCY_BOOST_3H  = 1.40;
    private const RECENCY_BOOST_6H  = 1.15;

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

    public function buildFeed(int $page = 1, int $perPage = 15): array
    {
        $cacheKey = "feed:v4:{$this->userId}:p{$page}";
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

    // ─── Candidate Gathering ──────────────────────────────────────────────────

    private function gatherCandidates(int $page, int $perPage): Collection
    {
        $candidateCount = $perPage * 5;
        $offset         = ($page - 1) * $perPage;

        $hasFollowing    = !empty($this->followingIds);
        $hasInteractions = !empty($this->getInteractedCreators());
        $discoveryBoost  = (!$hasFollowing && !$hasInteractions) ? 2.0 : 1.0;

        $base = CommunityPost::query()
            ->whereNull('community_posts.group_id')
            ->where('community_posts.privacy', '!=', 'private')
            ->whereNull('community_posts.deleted_at')
            ->where('community_posts.moderation_status', 'approved')
            ->where('community_posts.created_at', '>', now()->subDays(14))
            ->where(fn ($q) => $q->where('community_posts.video_ready', true)->orWhere('community_posts.user_id', $this->userId));

        if (!empty($this->seenPostIds)) {
            $recentSeen      = DB::table('feed_seen_posts')
                ->where('user_id', $this->userId)
                ->where('seen_at', '>', now()->subHours(24))
                ->pluck('post_id')->toArray();
            $totalAvailable  = (clone $base)->count();
            $unseenAvailable = $totalAvailable - count($recentSeen);
            if (!empty($recentSeen) && $unseenAvailable >= $perPage) {
                $base->whereNotIn('community_posts.id', $recentSeen);
            }
        }

        $cols = ['id', 'user_id', 'type', 'content', 'location',
                 'views_count', 'likes_count', 'comments_count',
                 'shares_count', 'saves_count', 'created_at'];

        // Pool 1: Following (no distribution cap — followers always see their feed)
        $followingPosts = (clone $base)
            ->whereIn('community_posts.user_id', $this->followingIds)
            ->latest('community_posts.created_at')->limit($candidateCount)->offset($offset)->get($cols);

        // Pool 2: Recommended (interacted creators not followed)
        $interactedCreators = $this->getInteractedCreators();
        $recommendedPosts   = collect();
        if (!empty($interactedCreators)) {
            $recommendedPosts = (clone $base)
                ->whereIn('community_posts.user_id', $interactedCreators)
                ->whereNotIn('community_posts.user_id', $this->followingIds)
                ->latest('community_posts.created_at')->limit(round($candidateCount * 0.3))->offset(round($offset * 0.3))
                ->get($cols);
        }

        // Feature 3: Pools 3/4/5 respect the distribution cap.
        // Uses whereNotExists subquery (not JOIN) to avoid ambiguous column names.
        $distributionFilter = function ($q) {
            $q->where(function ($outer) {
                $outer->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('post_scores')
                        ->whereColumn('post_scores.post_id', 'community_posts.id')
                        ->whereRaw('post_scores.impression_count > post_scores.distribution_cap');
                })
                ->orWhere('community_posts.user_id', $this->userId);
            });
        };

        // Pool 3: Trending
        $trendingPosts = (clone $base)
            ->whereNotIn('user_id', $this->followingIds)
            ->where('community_posts.created_at', '>', now()->subDays(3))
            ->whereRaw('(likes_count + comments_count * 2 + shares_count * 3) > ?', [10])
            ->tap($distributionFilter)
            ->orderByRaw('(likes_count + comments_count * 2 + shares_count * 3) / GREATEST(1, TIMESTAMPDIFF(HOUR, community_posts.created_at, NOW())) DESC')
            ->limit((int) round($candidateCount * 0.2 * $discoveryBoost))
            ->offset(round($offset * 0.2))
            ->get($cols);

        // Pool 4: New creators
        $newCreatorPosts = (clone $base)
            ->whereNotIn('user_id', $this->followingIds)
            ->where('community_posts.created_at', '>', now()->subDays(2))
            ->where(function ($q) {
                $q->whereHas('user.communityProfile', fn ($p) => $p->where('followers_count', '<', 100))
                  ->orWhereDoesntHave('user.communityProfile');
            })
            ->tap($distributionFilter)
            ->inRandomOrder()
            ->limit((int) round($candidateCount * 0.1 * $discoveryBoost))
            ->get($cols);

        // Pool 5: Random discovery
        $randomPosts = (clone $base)
            ->whereNotIn('user_id', $this->followingIds)
            ->where('community_posts.created_at', '>', now()->subDays(7))
            ->tap($distributionFilter)
            ->inRandomOrder()
            ->limit((int) round($candidateCount * 0.05 * $discoveryBoost))
            ->get($cols);

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
            $scored[] = [
                'post_id' => $post->id,
                'user_id' => $post->user_id,
                'type'    => $post->type,
                'topic'   => ($postHashtags[$post->id] ?? [])[0] ?? null,
                'score'   => $this->scorePost($post, $precomputed, $postHashtags),
                'pool'    => $this->classifyPool($post),
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
    }

    /**
     * Score a single post for this user.
     *
     * Layer order (applied sequentially so each layer can inspect the same $score):
     *   A. Base velocity (per-hour rates)
     *   B. Feature 1: Impression-normalized CTR (replaces velocity when data is sufficient)
     *   C. Feature 5: Trending boost + quality multiplier
     *   D. Feature 2: Dwell time & watch behaviour multiplier
     *   E. Feature 3: Fatigue + watch completion + negative rate
     *   F. Personalization (follow bonus + interest match) — added, not multiplied
     *   G. Content type bonus
     *   H. Recency boost
     *   I. Feature 5: Adaptive time decay
     *   J. Feature 3: Distribution stage gate
     *   K. Seen penalty + negative signals
     */
    private function scorePost($post, $precomputed, $postHashtags): float
    {
        $hoursOld = max(0.1, now()->diffInMinutes($post->created_at) / 60);
        $tags     = $postHashtags[$post->id] ?? [];
        $pre      = $precomputed[$post->id] ?? null;

        // ── A. Base velocity score (fallback when impressions are sparse) ──────
        $rawVelocity = (($post->likes_count    * self::W_LIKE)
                      + ($post->comments_count * self::W_COMMENT)
                      + ($post->shares_count   * self::W_SHARE)
                      + ($post->saves_count    * self::W_SAVE)
                      + ($post->views_count    * self::W_VIEW))
                      / $hoursOld;

        // Blend with actual 24h velocity when available
        if ($pre && isset($pre->velocity_24h) && $pre->velocity_24h > 0) {
            $rawVelocity = ($pre->velocity_24h * 0.6) + ($rawVelocity * 0.4);
        }

        $score = log1p($rawVelocity) * 4.0;

        // ── B. Feature 1: Impression-normalized CTR (higher confidence signal) ─
        // Replaces raw-velocity base when the post has enough impressions to
        // trust the rate statistics. 100 likes / 500 imps >> 100 likes / 50k imps.
        $impressions = $pre ? (int) ($pre->impression_count ?? 0) : 0;
        if ($pre && $impressions >= self::MIN_IMP_FOR_RATES) {
            $ctr = (((float) ($pre->like_rate    ?? 0)) * self::W_CTR_LIKE)
                 + (((float) ($pre->comment_rate ?? 0)) * self::W_CTR_COMMENT)
                 + (((float) ($pre->save_rate    ?? 0)) * self::W_CTR_SAVE)
                 + (((float) ($pre->share_rate   ?? 0)) * self::W_CTR_SHARE);

            $ctrScore = log1p($ctr * self::CTR_SCALE) * 3.5;
            // Weight CTR heavily once impressions are reliable; keep velocity as
            // a secondary signal so freshly-popular posts with few impressions
            // still get credit.
            $score = ($ctrScore * 0.70) + ($score * 0.30);
        }

        // ── C. Trending boost + quality multiplier ────────────────────────────
        if ($pre) {
            // Trending (viral_score = engagement acceleration)
            $viralScore = (float) ($pre->viral_score ?? 0);
            if ($viralScore >= 15) $score *= self::TREND_BOOST_HIGH;
            elseif ($viralScore >= 8)  $score *= self::TREND_BOOST_MID;
            elseif ($viralScore >= 3)  $score *= self::TREND_BOOST_LOW;

            // Quality: % of viewers who meaningfully engaged
            $engRate = (float) ($pre->engagement_rate ?? 0);
            if ($engRate >= self::QUALITY_RATE_HIGH) $score *= self::QUALITY_MULT_HIGH;
            elseif ($engRate >= self::QUALITY_RATE_MED) $score *= self::QUALITY_MULT_MED;

            // Fatigue: many impressions, almost no engagement → boring/spam
            if ($impressions >= self::FATIGUE_IMP_HIGH && $engRate < self::FATIGUE_RATE_HIGH) {
                $score *= self::FATIGUE_MULT_HIGH;
            } elseif ($impressions >= self::FATIGUE_IMP_MED && $engRate < self::FATIGUE_RATE_MED) {
                $score *= self::FATIGUE_MULT_MED;
            }
        }

        // ── D. Feature 2: Dwell time & watch behaviour ────────────────────────
        if ($pre) {
            $avgDwell = (float) ($pre->avg_dwell_ms ?? 0);
            if ($avgDwell >= self::DWELL_HIGH_MS) {
                $score *= self::DWELL_MULT_HIGH;  // users linger → strong signal
            } elseif ($avgDwell >= self::DWELL_MED_MS) {
                $score *= self::DWELL_MULT_MED;
            } elseif ($avgDwell > 0 && $avgDwell < self::DWELL_LOW_MS) {
                $score *= self::DWELL_MULT_LOW;   // quick scroll-away → demote
            }

            // Rewatches: same user replayed the video — very strong intent signal
            if (($pre->rewatch_count ?? 0) > 0) {
                $score *= self::REWATCH_MULT;
            }

            // Watch completion (video quality signal — already in post_scores)
            $watchCompl = isset($pre->watch_completion) ? (float) $pre->watch_completion : null;
            if ($watchCompl !== null && $watchCompl > 0) {
                if ($watchCompl >= self::WATCH_COMPL_HIGH) $score *= self::WATCH_COMPL_MULT_H;
                elseif ($watchCompl >= self::WATCH_COMPL_MED) $score *= self::WATCH_COMPL_MULT_M;
                elseif ($watchCompl < self::WATCH_COMPL_LOW)  $score *= self::WATCH_COMPL_MULT_L;
            }

            // Negative interaction rate (hides, reports)
            $negCount = (int) ($pre->negative_count ?? 0);
            if ($negCount > 0 && $impressions > 0) {
                $negRate = $negCount / $impressions;
                if ($negRate > 0.05) $score *= 0.40;
                elseif ($negRate > 0.02) $score *= 0.70;
            }
        }

        // ── E. Personalization ─────────────────────────────────────────────────
        // Added (not multiplied) so the multipliers above don't reduce follow bonus.
        if (in_array($post->user_id, $this->followingIds)) {
            $score += self::W_FOLLOW;
            $closeness = $this->interactionHistory[$post->user_id] ?? 0;
            $score += min($closeness * 0.5, 5.0);
        }
        foreach ($tags as $tag) {
            $score += ($this->userInterests["hashtag:{$tag}"] ?? 0) * self::W_INTEREST;
        }
        $score += ($this->userInterests["type:{$post->type}"]       ?? 0) * 1.5;
        $score += ($this->userInterests["creator:{$post->user_id}"] ?? 0) * 2.0;

        // ── F. Content type bonus ──────────────────────────────────────────────
        if (in_array($post->type, ['video', 'reel', 'image'])) $score *= 1.10;

        // ── G. Recency boost ───────────────────────────────────────────────────
        if      ($hoursOld <= 1) $score *= self::RECENCY_BOOST_1H;
        elseif  ($hoursOld <= 3) $score *= self::RECENCY_BOOST_3H;
        elseif  ($hoursOld <= 6) $score *= self::RECENCY_BOOST_6H;

        // ── H. Feature 5: Adaptive time decay ─────────────────────────────────
        $velocity24h = $pre ? (float) ($pre->velocity_24h ?? 0) : 0;
        $score *= $this->adaptiveTimeDecay($hoursOld, $velocity24h);

        // ── I. Feature 3: Distribution stage gate ─────────────────────────────
        // Non-followers see a score penalty for early-stage posts to limit spread
        // until the post proves it deserves a wider audience.
        if ($pre && isset($pre->distribution_stage) && !in_array($post->user_id, $this->followingIds)) {
            $stage = (int) $pre->distribution_stage;
            if ($stage === 0) $score *= self::DIST_STAGE0_FACTOR;
            elseif ($stage === 1) $score *= self::DIST_STAGE1_FACTOR;
            // Stage 2+: no penalty — post has earned broader distribution
        }

        // ── J. Seen penalty ────────────────────────────────────────────────────
        if (in_array($post->id, $this->seenPostIds)) $score *= 0.10;

        // ── K. Persistent negative signals ────────────────────────────────────
        if (!empty($this->negativeSignals)) {
            $penalty  = ($this->negativeSignals["creator:{$post->user_id}"] ?? 0) * 2.0;
            $penalty += ($this->negativeSignals["type:{$post->type}"]       ?? 0) * 1.0;
            foreach ($tags as $tag) {
                $penalty += ($this->negativeSignals["hashtag:{$tag}"] ?? 0) * 1.5;
            }
            if ($penalty > 0) $score *= 1 / (1 + $penalty * 0.3);
        }

        // ── L. Session-scoped signals (heavy, short-lived) ────────────────────
        if (!empty($this->sessionSignals)) {
            $sp  = (int) ($this->sessionSignals["creator:{$post->user_id}"] ?? 0) * 1.0;
            $sp += (int) ($this->sessionSignals["type:{$post->type}"]       ?? 0) * 0.5;
            foreach ($tags as $tag) {
                $sp += (int) ($this->sessionSignals["hashtag:{$tag}"] ?? 0) * 0.8;
            }
            if ($sp > 0) $score *= 1 / (1 + $sp * 0.6);
        }

        return max(0.001, $score);
    }

    /**
     * Feature 5: Adaptive time decay.
     *
     * Base decay (4-phase):
     *   ≤3h  → 1.000   12h  → 0.671
     *   24h  → 0.444   48h  → 0.111
     *   72h  → 0.028   96h  → 0.004
     *
     * Adaptation by velocity_24h (interactions/hour):
     *   Strong (≥10/h)  → pow(base, 0.60) — much slower decay (post still alive)
     *   Mild   (≥3/h)   → pow(base, 0.80) — slightly slower
     *   Dead   (<0.1/h) → pow(base, 1.50) — faster decay (post is stale)
     *   Normal           → base (unchanged)
     *
     * Example at 48h (base=0.111):
     *   Strong engagement → pow(0.111, 0.60) ≈ 0.236  (21% of score retained)
     *   Dead post         → pow(0.111, 1.50) ≈ 0.037  (3.7% retained)
     */
    private function adaptiveTimeDecay(float $hoursOld, float $velocity24h): float
    {
        $base = $this->baseTimeDecay($hoursOld);

        // Don't adapt during the no-decay window — every new post gets fair start
        if ($hoursOld <= self::DECAY_PHASE1_END) return $base;

        if ($velocity24h >= self::DECAY_ADAPT_STRONG_V) {
            return pow($base, self::DECAY_ADAPT_STRONG);
        }
        if ($velocity24h >= self::DECAY_ADAPT_MED_V) {
            return pow($base, self::DECAY_ADAPT_MED);
        }
        if ($velocity24h < self::DECAY_ADAPT_DEAD_V) {
            return pow($base, self::DECAY_ADAPT_DEAD);
        }

        return $base;
    }

    /**
     * Fixed 4-phase base decay curve (used by adaptiveTimeDecay).
     */
    private function baseTimeDecay(float $hoursOld): float
    {
        if ($hoursOld <= self::DECAY_PHASE1_END) return 1.0;

        if ($hoursOld <= self::DECAY_PHASE2_END) {
            return pow(0.5, ($hoursOld - self::DECAY_PHASE1_END) / self::DECAY_HL_PHASE2);
        }

        $atP2 = pow(0.5, (self::DECAY_PHASE2_END - self::DECAY_PHASE1_END) / self::DECAY_HL_PHASE2);

        if ($hoursOld <= self::DECAY_PHASE3_END) {
            return $atP2 * pow(0.5, ($hoursOld - self::DECAY_PHASE2_END) / self::DECAY_HL_PHASE3);
        }

        $atP3 = $atP2 * pow(0.5, (self::DECAY_PHASE3_END - self::DECAY_PHASE2_END) / self::DECAY_HL_PHASE3);
        return $atP3 * pow(0.5, ($hoursOld - self::DECAY_PHASE3_END) / self::DECAY_HL_PHASE4);
    }

    // ─── Diversity (Feature 4 — extended content-type mixing) ────────────────

    private function applyDiversity(array $scored, int $perPage): array
    {
        $result          = [];
        $creatorCounts   = [];
        $topicCounts     = [];
        $lastType        = null;
        $consecutiveType = 0;

        // Pool-source mix targets
        $poolCounts  = ['following' => 0, 'recommended' => 0, 'trending' => 0, 'new_creator' => 0, 'random' => 0];
        $poolTargets = [
            'following'   => ceil($perPage * self::MIX_FOLLOWING / 100),
            'recommended' => ceil($perPage * self::MIX_RECOMMENDED / 100),
            'trending'    => ceil($perPage * self::MIX_TRENDING / 100),
            'new_creator' => ceil($perPage * self::MIX_NEW_CREATORS / 100),
            'random'      => ceil($perPage * self::MIX_RANDOM / 100),
        ];

        // Feature 4: Content-type group targets
        $typeCounts  = ['video' => 0, 'reel' => 0, 'image' => 0, 'text' => 0, 'audio' => 0, 'other' => 0];
        $typeTargets = [
            'video' => ceil($perPage * self::MIX_TYPE_VIDEO / 100),
            'reel'  => ceil($perPage * self::MIX_TYPE_REEL  / 100),
            'image' => ceil($perPage * self::MIX_TYPE_IMAGE / 100),
            'text'  => ceil($perPage * self::MIX_TYPE_TEXT  / 100),
            'audio' => ceil($perPage * self::MIX_TYPE_AUDIO / 100),
            'other' => ceil($perPage * self::MIX_TYPE_OTHER / 100),
        ];
        $hardCap = (int) ceil($perPage * self::MIX_TYPE_HARD_CAP);

        foreach ($scored as $item) {
            if (count($result) >= $perPage) break;

            $creatorId   = $item['user_id'];
            $pool        = $item['pool'];
            $topic       = $item['topic'];
            $contentType = $item['type'];
            $typeGroup   = $this->contentTypeGroup($contentType);

            // Anti-flood per creator
            if (($creatorCounts[$creatorId] ?? 0) >= self::MAX_PER_CREATOR) continue;
            // Topic diversity
            if ($topic !== null && ($topicCounts[$topic] ?? 0) >= self::MAX_PER_TOPIC) continue;
            // Consecutive same media type
            if ($contentType === $lastType && $consecutiveType >= self::MAX_CONSECUTIVE_SAME_TYPE) continue;
            // Pool source soft cap
            if (($poolCounts[$pool] ?? 0) >= ($poolTargets[$pool] ?? $perPage) * 1.5) continue;
            // Feature 4: content-type group target + hard cap
            $groupCount = $typeCounts[$typeGroup] ?? 0;
            if ($groupCount >= ($typeTargets[$typeGroup] ?? $perPage) && $groupCount >= $hardCap) continue;

            $result[] = $item;
            $creatorCounts[$creatorId] = ($creatorCounts[$creatorId] ?? 0) + 1;
            if ($topic !== null) $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
            $poolCounts[$pool]         = ($poolCounts[$pool] ?? 0) + 1;
            $typeCounts[$typeGroup]    = ($typeCounts[$typeGroup] ?? 0) + 1;
            $consecutiveType = ($contentType === $lastType) ? $consecutiveType + 1 : 1;
            $lastType        = $contentType;
        }

        // Backfill pass 1: relax type/topic/pool limits, allow +1 per creator
        if (count($result) < $perPage) {
            $usedIds       = array_column($result, 'post_id');
            $relaxedCreator = self::MAX_PER_CREATOR + 1;
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

    /** Map post type to diversity group. */
    private function contentTypeGroup(string $type): string
    {
        return match ($type) {
            'video'                   => 'video',
            'reel'                    => 'reel',
            'image'                   => 'image',
            'text', 'poll'            => 'text',
            'audio'                   => 'audio',
            default                   => 'other', // service, document, share, …
        };
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
                $weight = pow(0.9, now()->diffInDays($s->created_at));
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
     * Recompute all post_scores metrics (runs every 5 min via scheduler).
     *
     * Columns updated per post:
     *   engagement_score  — weighted lifetime totals
     *   velocity_score    — lifetime engScore / hoursOld
     *   velocity_24h      — actual interactions/hour over last 24h
     *   quality_score     — deep/shallow engagement ratio
     *   viral_score       — recent-2h vs prior-2h acceleration
     *   watch_completion  — avg video completion fraction (0-1)
     *   avg_dwell_ms      — avg ms spent viewing (all content types)
     *   rewatch_count     — replays by same user
     *   negative_count    — skip + report actions
     *   like_rate         — likes / impressions
     *   comment_rate      — comments / impressions
     *   save_rate         — saves / impressions
     *   share_rate        — shares / impressions
     *   impression_count  — total unique viewers
     *   engaged_count     — unique users who interacted
     *   engagement_rate   — engaged / impression
     *   final_score       — composite (admin dashboards only)
     */
    public static function recomputePostScores(): void
    {
        $posts = CommunityPost::where('created_at', '>', now()->subDays(14))
            ->whereNull('deleted_at')
            ->where('moderation_status', 'approved')
            ->get(['id', 'user_id', 'type', 'views_count', 'likes_count',
                   'comments_count', 'shares_count', 'saves_count', 'created_at']);

        foreach ($posts->chunk(200) as $chunk) {
            $chunkIds = $chunk->pluck('id')->toArray();

            // Viral: recent (last 2h) vs prior (2-4h) — DISTINCT prevents single-user inflation
            $recentCounts = DB::table('feed_interactions')
                ->whereIn('post_id', $chunkIds)->where('created_at', '>', now()->subHours(2))
                ->selectRaw('post_id, COUNT(DISTINCT user_id) as cnt')
                ->groupBy('post_id')->pluck('cnt', 'post_id');

            $olderCounts = DB::table('feed_interactions')
                ->whereIn('post_id', $chunkIds)
                ->whereBetween('created_at', [now()->subHours(4), now()->subHours(2)])
                ->selectRaw('post_id, COUNT(DISTINCT user_id) as cnt')
                ->groupBy('post_id')->pluck('cnt', 'post_id');

            // velocity_24h: raw interaction count in last 24h
            $velocity24hCounts = DB::table('feed_interactions')
                ->whereIn('post_id', $chunkIds)->where('created_at', '>', now()->subHours(24))
                ->selectRaw('post_id, COUNT(*) as cnt')
                ->groupBy('post_id')->pluck('cnt', 'post_id');

            $impressionCounts = DB::table('feed_seen_posts')
                ->whereIn('post_id', $chunkIds)
                ->selectRaw('post_id, COUNT(*) as cnt')
                ->groupBy('post_id')->pluck('cnt', 'post_id');

            $engagedCounts = DB::table('feed_interactions')
                ->whereIn('post_id', $chunkIds)->where('type', '!=', 'view')
                ->selectRaw('post_id, COUNT(DISTINCT user_id) as cnt')
                ->groupBy('post_id')->pluck('cnt', 'post_id');

            // Feature 2: watch completion (video) — avg fraction of video watched
            $watchCompletionData = DB::table('feed_interactions as fi')
                ->join('community_post_media as m', 'm.post_id', '=', 'fi.post_id')
                ->whereIn('fi.post_id', $chunkIds)->where('fi.type', 'watch')
                ->whereNotNull('fi.duration_ms')->whereRaw('m.duration > 0')
                ->selectRaw('fi.post_id, AVG(LEAST(fi.duration_ms / (m.duration * 1000), 1.0)) as avg_completion')
                ->groupBy('fi.post_id')->pluck('avg_completion', 'post_id');

            // Feature 2: avg dwell time (all types — view + watch interactions)
            $dwellData = DB::table('feed_interactions')
                ->whereIn('post_id', $chunkIds)->whereIn('type', ['view', 'watch'])
                ->whereNotNull('duration_ms')->where('duration_ms', '>', 0)
                ->selectRaw('post_id, AVG(duration_ms) as avg_dwell, COUNT(CASE WHEN type = "watch" THEN 1 END) as rewatches')
                ->groupBy('post_id')->get()->keyBy('post_id');

            // Negative interactions: skips + reports
            $negativeCounts = DB::table('feed_interactions')
                ->whereIn('post_id', $chunkIds)->whereIn('type', ['skip', 'report'])
                ->selectRaw('post_id, COUNT(*) as cnt')
                ->groupBy('post_id')->pluck('cnt', 'post_id');

            $rows = [];
            foreach ($chunk as $post) {
                $hoursOld = max(1, now()->diffInMinutes($post->created_at) / 60);

                $engScore = ($post->likes_count * 1)  + ($post->comments_count * 2)
                          + ($post->shares_count * 3) + ($post->saves_count * 2.5)
                          + ($post->views_count * 0.1);

                $velocity = $engScore / $hoursOld;

                $raw24h      = (int) ($velocity24hCounts[$post->id] ?? 0);
                $window24h   = min($hoursOld, 24);
                $velocity24h = $raw24h / max(1, $window24h);

                $deep    = $post->comments_count + $post->shares_count + $post->saves_count;
                $shallow = $post->likes_count + $post->views_count;
                $quality = $shallow > 0 ? ($deep / $shallow) * 10 : 0;

                $recentEng = (int) ($recentCounts[$post->id] ?? 0);
                $olderEng  = (int) ($olderCounts[$post->id] ?? 0);
                $viral     = ($olderEng > 0)
                    ? ($recentEng / $olderEng) * min($recentEng, 20)
                    : $recentEng * 0.5;

                $impressions     = (int) ($impressionCounts[$post->id] ?? 0);
                $engaged         = (int) ($engagedCounts[$post->id] ?? 0);
                $engRate         = $impressions > 0 ? $engaged / $impressions : 0;

                // Feature 1: normalized rates
                $likeRate    = $impressions > 0 ? $post->likes_count    / $impressions : 0;
                $commentRate = $impressions > 0 ? $post->comments_count / $impressions : 0;
                $saveRate    = $impressions > 0 ? $post->saves_count    / $impressions : 0;
                $shareRate   = $impressions > 0 ? $post->shares_count   / $impressions : 0;

                // Feature 2: dwell + rewatch
                $dwellRow    = $dwellData[$post->id] ?? null;
                $avgDwell    = $dwellRow ? (float) $dwellRow->avg_dwell : null;
                $rewatchCount = $dwellRow ? (int) $dwellRow->rewatches : 0;
                $watchCompl  = isset($watchCompletionData[$post->id])
                    ? (float) $watchCompletionData[$post->id] : null;

                $negativeCount = (int) ($negativeCounts[$post->id] ?? 0);

                $final  = ($engScore * 0.20) + ($velocity24h * 0.30)
                        + ($quality * 0.15) + ($viral * 0.25) + ($engRate * 100 * 0.10);
                $final *= pow(0.5, $hoursOld / 12);

                $rows[] = [
                    'post_id'            => $post->id,
                    'engagement_score'   => round($engScore, 4),
                    'velocity_score'     => round($velocity, 4),
                    'velocity_24h'       => round($velocity24h, 4),
                    'quality_score'      => round($quality, 4),
                    'viral_score'        => round($viral, 4),
                    'final_score'        => round($final, 4),
                    'watch_completion'   => $watchCompl !== null ? round($watchCompl, 4) : null,
                    'avg_dwell_ms'       => $avgDwell !== null ? round($avgDwell, 2) : null,
                    'rewatch_count'      => $rewatchCount,
                    'negative_count'     => $negativeCount,
                    'like_rate'          => round($likeRate, 6),
                    'comment_rate'       => round($commentRate, 6),
                    'save_rate'          => round($saveRate, 6),
                    'share_rate'         => round($shareRate, 6),
                    'impression_count'   => $impressions,
                    'engaged_count'      => $engaged,
                    'engagement_rate'    => round($engRate, 4),
                    'last_scored_at'     => now(),
                    'updated_at'         => now(),
                    'created_at'         => now(),
                ];
            }

            DB::table('post_scores')->upsert($rows, ['post_id'], [
                'engagement_score', 'velocity_score', 'velocity_24h',
                'quality_score', 'viral_score', 'final_score',
                'watch_completion', 'avg_dwell_ms', 'rewatch_count', 'negative_count',
                'like_rate', 'comment_rate', 'save_rate', 'share_rate',
                'impression_count', 'engaged_count', 'engagement_rate',
                'last_scored_at', 'updated_at',
            ]);
        }
    }

    /**
     * Feature 3: Progressive distribution — promote posts that earned a wider audience.
     *
     * Stage ladder:
     *   0 → seed (≤100 imp)   : shown only to followers + small random sample
     *   1 → small (≤1k imp)   : limited spread, mild score penalty for non-followers
     *   2 → medium (≤10k imp) : open, no penalty
     *   3 → large (≤100k imp) : viral-eligible
     *   4 → viral (unlimited) : no cap
     *
     * A post is promoted when it meets both the minimum impression count AND
     * the minimum engagement rate for its current stage. Posts that plateau
     * below the threshold are not promoted — their distribution simply stops
     * growing, preventing weak content from flooding the feed.
     */
    public static function progressDistribution(): void
    {
        foreach (self::DIST_THRESHOLDS as $stage => [$minImp, $minRate, $newCap]) {
            DB::table('post_scores')
                ->where('distribution_stage', $stage)
                ->where('impression_count', '>=', $minImp)
                ->where('engagement_rate', '>=', $minRate)
                ->where(fn ($q) => $q->whereNull('stage_promoted_at')
                                     ->orWhere('stage_promoted_at', '<', now()->subHour()))
                ->update([
                    'distribution_stage' => $stage + 1,
                    'distribution_cap'   => $newCap,
                    'stage_promoted_at'  => now(),
                    'updated_at'         => now(),
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
