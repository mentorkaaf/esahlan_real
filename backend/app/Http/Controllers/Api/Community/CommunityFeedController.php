<?php
namespace App\Http\Controllers\Api\Community;
use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\CommunityFollow;
use App\Models\CommunityProfile;
use App\Services\FeedRankingService;
use App\Services\InteractionTracker;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\Community\CommunityAdController;
use Illuminate\Support\Facades\Redis;

class CommunityFeedController extends Controller
{
    private function trackActiveFeedUser(): void
    {
        $userId = auth()->id();
        if (!$userId) return;
        $now = now()->timestamp;
        Redis::zadd('feed:active_users', $now, $userId);
        // Remove users older than 2 minutes
        Redis::zremrangebyscore('feed:active_users', '-inf', now()->subMinutes(2)->timestamp);
    }

    // ─── Personalized For You feed ─────────────────────────────────────
    public function following(Request $request)
    {
        $this->trackActiveFeedUser();
        $userId = auth()->id();
        $page = (int) $request->get('page', 1);

        // Build personalized feed using ranking algorithm
        $ranker = new FeedRankingService($userId);
        $ranked = $ranker->buildFeed($page, 30);

        if (empty($ranked)) {
            // Fallback: if no ranked results (new user / cold start), use chronological
            return $this->coldStartFeed($userId, $page);
        }

        // Fetch full post data for ranked IDs (preserving rank order)
        $rankedIds = array_column($ranked, 'post_id');
        $posts = CommunityPost::with(['user.communityProfile', 'media', 'userReaction', 'page'])
            ->whereIn('id', $rankedIds)
            ->get()
            ->keyBy('id');

        // Batch-load follow + saved state once to avoid N+1 (one query each)
        $followingIds = \DB::table('community_follows')
            ->where('follower_id', $userId)->pluck('following_id')->toArray();
        $savedPostIds = \DB::table('community_saved_posts')
            ->where('user_id', $userId)->whereIn('post_id', $rankedIds)->pluck('post_id')->toArray();

        $transformed = [];
        foreach ($rankedIds as $pid) {
            if ($posts->has($pid)) {
                $transformed[] = $this->transformPost($posts[$pid], $userId, $followingIds, $savedPostIds);
            }
        }

        // Track impressions for these posts
        InteractionTracker::trackImpressions($userId, $rankedIds);

        // Inject ads
        $transformed = $this->injectFeedAds($transformed, $userId);

        $total = CommunityPost::whereNull('group_id')
            ->where('privacy', '!=', 'private')
            ->where('created_at', '>', now()->subDays(90))
            ->count();

        return response()->json([
            'status' => 'success',
            'data'   => $transformed,
            'meta'   => [
                'current_page' => $page,
                'last_page'    => max(1, ceil($total / 30)),
                'total'        => $total,
            ],
        ]);
    }

    // ─── Cold start feed for new users with no interaction history ──────
    private function coldStartFeed(int $userId, int $page): \Illuminate\Http\JsonResponse
    {
        $followingIds = CommunityFollow::where('follower_id', $userId)->pluck('following_id');

        $query = CommunityPost::with(['user.communityProfile', 'media', 'userReaction', 'page'])
            ->whereNull('group_id')
            ->where('privacy', '!=', 'private')
            ->where(fn ($q) => $q->where('video_ready', true)->orWhere('user_id', $userId));

        if ($followingIds->isNotEmpty()) {
            // Mix: 70% following, 30% popular
            $followingPosts = (clone $query)
                ->whereIn('user_id', $followingIds)
                ->latest()
                ->limit(10)
                ->get();

            $popularPosts = (clone $query)
                ->whereNotIn('user_id', $followingIds)
                ->where('created_at', '>', now()->subDays(7))
                ->orderByRaw('(likes_count + comments_count * 2 + shares_count * 3) DESC')
                ->limit(5)
                ->get();

            $merged = $followingPosts->concat($popularPosts)->unique('id');
        } else {
            // No follows: show popular + recent
            $merged = $query
                ->where('created_at', '>', now()->subDays(7))
                ->orderByRaw('(likes_count + comments_count * 2 + shares_count * 3 + 1) * (1.0 / GREATEST(1, TIMESTAMPDIFF(HOUR, created_at, NOW()))) DESC')
                ->limit(15)
                ->get();
        }

        $postIds = $merged->pluck('id')->toArray();
        $followingIdsArr = $followingIds->toArray();
        $savedPostIds = \DB::table('community_saved_posts')
            ->where('user_id', $userId)->whereIn('post_id', $postIds)->pluck('post_id')->toArray();

        $transformed = $merged->map(fn ($p) => $this->transformPost($p, $userId, $followingIdsArr, $savedPostIds))->toArray();
        $transformed = $this->injectFeedAds($transformed, $userId);

        InteractionTracker::trackImpressions($userId, $merged->pluck('id')->toArray());

        $total = $merged->count();
        return response()->json([
            'status' => 'success',
            'data'   => $transformed,
            'meta'   => ['current_page' => $page, 'last_page' => 1, 'total' => $total],
        ]);
    }

    // ─── Inject ads with proper spacing ────────────────────────────────
    private function injectFeedAds(array $transformed, int $userId): array
    {
        $adSettings = json_decode(\DB::table('settings')->where('key', 'ad_display_settings')->value('value') ?? '{}', true) ?? [];
        $feedEnabled = $adSettings['feed_ads_enabled'] ?? true;
        $frequency   = max(3, (int) ($adSettings['feed_ad_frequency'] ?? 5));
        $maxAds      = min(10, (int) ($adSettings['feed_max_ads'] ?? 3));

        if (!$feedEnabled) return $transformed;

        $ads = CommunityAdController::getAdsForPlacement('feed', $userId, $maxAds);
        $totalPosts = count($transformed);
        if ($totalPosts < $frequency || empty($ads)) return $transformed;

        // Calculate insertion positions BEFORE touching the array so the math
        // never drifts as items are spliced in. Positions are based on the
        // original post count, ensuring:
        //   • First ad appears after `$frequency` real posts (not at the top).
        //   • Last ad appears at most at (totalPosts - frequency) so the
        //     final screen of posts is always ad-free (no bunching at the end).
        //   • Minimum gap of `$frequency` posts between every two ads.
        $positions = [];
        for ($i = 1; $i <= $maxAds; $i++) {
            $pos = $i * $frequency;
            if ($pos > $totalPosts - $frequency) break; // ad-free zone at end
            $positions[] = $pos;
        }

        $ads = array_slice($ads, 0, count($positions));

        // Insert ads back-to-front so earlier offsets stay valid.
        $pairs = array_reverse(array_map(null, $positions, $ads));
        foreach ($pairs as [$pos, $ad]) {
            if ($ad === null) continue;
            array_splice($transformed, $pos, 0, [$ad]);
        }

        return $transformed;
    }

    // ─── Track interaction endpoint ────────────────────────────────────
    public function trackInteraction(Request $request)
    {
        $request->validate([
            'post_id'     => 'required|integer',
            'type'        => 'required|in:view,like,comment,share,save,watch,click,skip',
            'duration_ms' => 'nullable|integer',
        ]);

        InteractionTracker::track(
            auth()->id(),
            $request->post_id,
            $request->type,
            ['duration_ms' => $request->duration_ms]
        );

        return response()->json(['status' => 'success']);
    }

    // ─── Feed presence: heartbeat (POST) and leave (DELETE) ───────────
    // Flutter sends heartbeat every 30 s while feed screen is open.
    // Key: feed:online — sorted set, score = Unix timestamp.
    // Members older than 90 s are pruned on each heartbeat.
    public function heartbeat()
    {
        $userId = auth()->id();
        if (!$userId) return response()->json(['ok' => false], 401);
        $now = now()->timestamp;
        Redis::zadd('feed:online', $now, $userId);
        Redis::zremrangebyscore('feed:online', '-inf', $now - 90);
        return response()->json(['ok' => true, 'online' => Redis::zcard('feed:online')]);
    }

    public function leave()
    {
        $userId = auth()->id();
        if ($userId) Redis::zrem('feed:online', $userId);
        return response()->json(['ok' => true]);
    }

    // ─── Batch track impressions ───────────────────────────────────────
    public function trackImpressions(Request $request)
    {
        $request->validate(['post_ids' => 'required|array', 'post_ids.*' => 'integer']);
        InteractionTracker::trackImpressions(auth()->id(), $request->post_ids);
        return response()->json(['status' => 'success']);
    }

    // ─── Trending feed — high engagement posts ─────────────────────────
    // Optional ?hashtag=xyz scopes trending to one category instead of a
    // single global list — lets the explore screen offer per-topic trending.
    public function explore(Request $request)
    {
        $userId = auth()->id();
        $hashtag = $request->get('hashtag');

        // Use post_scores table if available, fallback to raw calculation
        $hasScores = \DB::table('post_scores')->exists();

        if ($hasScores) {
            $query = \DB::table('post_scores')
                ->join('community_posts', 'community_posts.id', '=', 'post_scores.post_id')
                ->where('community_posts.privacy', 'public')
                ->whereNull('community_posts.group_id')
                ->whereNull('community_posts.deleted_at')
                ->where('community_posts.video_ready', true)
                ->where('community_posts.created_at', '>', now()->subDays(7));

            if ($hashtag) {
                $query->join('community_post_hashtags', 'community_post_hashtags.post_id', '=', 'community_posts.id')
                    ->join('community_hashtags', 'community_hashtags.id', '=', 'community_post_hashtags.hashtag_id')
                    ->where('community_hashtags.name', $hashtag);
            }

            $postIds = $query
                ->orderByDesc('post_scores.final_score')
                ->paginate(15, ['community_posts.id']);

            $fetchedIds = $postIds->pluck('id')->toArray();
            $posts = CommunityPost::with(['user.communityProfile', 'media', 'userReaction', 'page'])
                ->whereIn('id', $fetchedIds)
                ->get()
                ->keyBy('id');

            $exploreFollowingIds = \DB::table('community_follows')
                ->where('follower_id', $userId)->pluck('following_id')->toArray();
            $exploreSavedIds = \DB::table('community_saved_posts')
                ->where('user_id', $userId)->whereIn('post_id', $fetchedIds)->pluck('post_id')->toArray();

            $transformed = [];
            foreach ($postIds as $row) {
                if ($posts->has($row->id)) {
                    $transformed[] = $this->transformPost($posts[$row->id], $userId, $exploreFollowingIds, $exploreSavedIds);
                }
            }
            $meta = ['current_page' => $postIds->currentPage(), 'last_page' => $postIds->lastPage()];
        } else {
            $fallbackQuery = CommunityPost::with(['user.communityProfile', 'media', 'userReaction', 'page'])
                ->where('privacy', 'public')
                ->whereNull('group_id')
                ->where('video_ready', true)
                ->whereRaw('(likes_count + comments_count + views_count + shares_count) >= 20');

            if ($hashtag) {
                $fallbackQuery->whereHas('hashtags', fn ($q) => $q->where('name', $hashtag));
            }

            $posts = $fallbackQuery
                ->orderByRaw('(likes_count * 3 + comments_count * 2 + shares_count * 4 + views_count) DESC')
                ->paginate(15);
            $fallbackIds = $posts->pluck('id')->toArray();
            $fbFollowingIds = \DB::table('community_follows')
                ->where('follower_id', $userId)->pluck('following_id')->toArray();
            $fbSavedIds = \DB::table('community_saved_posts')
                ->where('user_id', $userId)->whereIn('post_id', $fallbackIds)->pluck('post_id')->toArray();
            $transformed = $posts->map(fn ($p) => $this->transformPost($p, $userId, $fbFollowingIds, $fbSavedIds))->toArray();
            $meta = ['current_page' => $posts->currentPage(), 'last_page' => $posts->lastPage()];
        }

        $hashtags = \App\Models\CommunityHashtag::where('posts_count', '>', 0)
            ->orderByDesc('posts_count')->take(20)->get(['id', 'name', 'posts_count']);

        // Inject explore ads
        $adSettings = json_decode(\DB::table('settings')->where('key', 'ad_display_settings')->value('value') ?? '{}', true) ?? [];
        $freq = $adSettings['feed_ad_frequency'] ?? 5;
        $ads = CommunityAdController::getAdsForPlacement('feed', $userId, 2);
        $inserted = 0;
        foreach ($ads as $ad) {
            $pos = ($inserted + 1) * $freq + $inserted;
            if ($pos > count($transformed)) break;
            array_splice($transformed, $pos, 0, [$ad]);
            $inserted++;
        }

        // Ads share the same array but their "id" comes from the community_ads
        // table — a separate sequence that can collide with a real post id.
        // Only count impressions for items that actually came from transformPost().
        $realPostIds = array_values(array_map(
            fn ($item) => $item['id'],
            array_filter($transformed, fn ($item) => array_key_exists('views_count', $item))
        ));
        InteractionTracker::trackImpressions($userId, $realPostIds);

        return response()->json(['status' => 'success', 'data' => $transformed, 'hashtags' => $hashtags, 'meta' => $meta]);
    }

    // ─── Personalized Reels feed ───────────────────────────────────────
    // Was hard-capped at exactly 15 reels total, forever — the $page param
    // was never read, so every "page" request returned the identical top-15
    // set regardless of how far the user scrolled, permanently hiding any
    // reel past whatever the 3 pools happened to surface. Fixed to paginate
    // properly: offset scaled per pool, already-seen reels excluded (reusing
    // feed_seen_posts, same as the main feed), and a per-creator cap so one
    // prolific poster can't fill an entire page.
    public function reels(Request $request)
    {
        $userId = auth()->id();
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        // Get user interests for reels ordering
        $interests = \DB::table('user_interests')
            ->where('user_id', $userId)
            ->where('category', 'creator')
            ->orderByDesc('score')
            ->limit(20)
            ->pluck('score', 'value')
            ->toArray();

        $followingIds = CommunityFollow::where('follower_id', $userId)->pluck('following_id')->toArray();
        $interestedCreators = array_keys($interests);

        // Exclude reels seen in the last 2 hours so scrolling doesn't repeat
        // the same clips — mirrors FeedRankingService's approach for the main
        // feed. But on a small/young content pool this can over-exclude: if
        // someone has already scrolled through everything available, the
        // exclusion list covers 100% of candidates and the page comes back
        // empty even though content technically exists (audited: 17/17 reels
        // marked seen for one real account, reels() returned zero). Showing
        // nothing is worse than showing a repeat, so the exclusion only
        // applies when there's enough OTHER content left to still fill a
        // page — otherwise it's dropped entirely for this request.
        $recentlySeen = \DB::table('feed_seen_posts')
            ->where('user_id', $userId)
            ->where('seen_at', '>', now()->subHours(2))
            ->pluck('post_id')->toArray();

        $totalAvailable = CommunityPost::whereIn('type', ['reel', 'video'])->where('privacy', 'public')->count();
        $unseenAvailable = $totalAvailable - count($recentlySeen);
        $applySeenExclusion = !empty($recentlySeen) && $unseenAvailable >= $perPage;

        $baseQuery = fn () => CommunityPost::with(['user.communityProfile', 'media', 'userReaction'])
            ->whereIn('type', ['reel', 'video'])
            ->where('privacy', 'public')
            // Mirror FeedRankingService: owner can see their own transcoding video in reels
            ->where(fn ($q) => $q->where('video_ready', true)->orWhere('user_id', $userId))
            ->when($applySeenExclusion, fn ($q) => $q->whereNotIn('id', $recentlySeen));

        // Same fix as the main feed: when following/recommended pools have
        // nothing to draw from (fresh user, or just thin interest history),
        // their unused budget needs to go somewhere or the page comes back
        // under-filled (audited: 8/15 with all three pools at their default
        // small fixed limits). Boost recommended when there's no following,
        // and boost trending — the only pool with zero personalization
        // dependency — when there's neither following nor interest data.
        $recommendedBoost = empty($followingIds) ? 1.6 : 1.0;
        $trendingBoost = (empty($followingIds) && empty($interestedCreators)) ? 2.5 : 1.0;

        // Pool 1: Following reels — ~50% of the page
        $followingLimit = (int) ceil($perPage * 0.5);
        $followingReels = $baseQuery()
            ->whereIn('user_id', $followingIds)
            ->latest()
            ->limit($followingLimit)
            ->offset((int) round($offset * 0.5))
            ->get();

        // Pool 2: Recommended reels (from interested creators) — ~30%
        $recommendedLimit = (int) ceil($perPage * 0.3 * $recommendedBoost);
        $recommendedReels = $baseQuery()
            ->whereNotIn('user_id', $followingIds)
            ->when(!empty($interestedCreators), fn ($q) =>
                $q->orderByRaw('FIELD(user_id, ' . implode(',', array_map('intval', $interestedCreators)) . ') DESC'))
            ->latest()
            ->limit($recommendedLimit)
            ->offset((int) round($offset * 0.3))
            ->get();

        // Pool 3: Trending reels — ~20%, no offset (re-ranked by current
        // engagement each request, so position naturally shifts over time)
        $trendingLimit = (int) ceil($perPage * 0.2 * $trendingBoost);
        $trendingReels = $baseQuery()
            ->whereNotIn('user_id', $followingIds)
            ->where('created_at', '>', now()->subDays(7))
            ->orderByRaw('(likes_count * 2 + comments_count * 3 + shares_count * 4) DESC')
            ->limit($trendingLimit)
            ->get();

        // Interleave pools: following, recommended, trending, repeat
        $pools = [$followingReels->values(), $recommendedReels->values(), $trendingReels->values()];
        $maxLen = max(array_map(fn ($p) => $p->count(), $pools));
        $merged = collect();
        for ($i = 0; $i < $maxLen; $i++) {
            foreach ($pools as $pool) {
                if (isset($pool[$i])) $merged->push($pool[$i]);
            }
        }
        $merged = $merged->unique('id');

        // Anti-flood: cap how many reels from a single creator land on one
        // page. Strict pass at cap=3; if that under-fills the page (thin
        // candidate pool — same issue audited on the main feed), backfill
        // with a relaxed cap=5 rather than returning fewer reels than requested.
        $perCreatorCount = [];
        $diversified = $merged->filter(function ($post) use (&$perCreatorCount) {
            $count = $perCreatorCount[$post->user_id] ?? 0;
            if ($count >= 3) return false;
            $perCreatorCount[$post->user_id] = $count + 1;
            return true;
        });

        if ($diversified->count() < $perPage) {
            $usedIds = $diversified->pluck('id')->toArray();
            $backfill = $merged->filter(function ($post) use (&$perCreatorCount, $usedIds) {
                if (in_array($post->id, $usedIds)) return false;
                $count = $perCreatorCount[$post->user_id] ?? 0;
                if ($count >= 5) return false;
                $perCreatorCount[$post->user_id] = $count + 1;
                return true;
            });
            $diversified = $diversified->concat($backfill);
        }

        $diversified = $diversified->take($perPage);

        $reelIds = $diversified->pluck('id')->toArray();
        $reelFollowingIds = \DB::table('community_follows')
            ->where('follower_id', $userId)->pluck('following_id')->toArray();
        $reelSavedIds = \DB::table('community_saved_posts')
            ->where('user_id', $userId)->whereIn('post_id', $reelIds)->pluck('post_id')->toArray();

        $transformed = $diversified->map(fn ($p) => $this->transformPost($p, $userId, $reelFollowingIds, $reelSavedIds))->values()->toArray();

        InteractionTracker::trackImpressions($userId, $diversified->pluck('id')->toArray());

        // Mark these reels seen so the next page's exclusion (above) actually
        // has something to exclude — trackImpressions() doesn't touch this
        // table, it's a separate mechanism shared with the main feed.
        $seenRows = $diversified->pluck('id')->map(fn ($id) => [
            'user_id' => $userId, 'post_id' => $id, 'seen_at' => now(),
        ])->toArray();
        if (!empty($seenRows)) {
            \DB::table('feed_seen_posts')->insertOrIgnore($seenRows);
        }

        // Inject reel ads
        $ads = CommunityAdController::getAdsForPlacement('reels', $userId, 1);
        foreach ($ads as $ad) {
            $pos = min(3, count($transformed));
            array_splice($transformed, $pos, 0, [$ad]);
        }

        $totalReels = CommunityPost::whereIn('type', ['reel', 'video'])->where('privacy', 'public')->count();

        return response()->json([
            'status' => 'success',
            'data'   => $transformed,
            'meta'   => ['current_page' => $page, 'last_page' => max(1, (int) ceil($totalReels / $perPage))],
        ]);
    }

    // ─── Trending hashtags ─────────────────────────────────────────────
    public function trending()
    {
        $hashtags = \App\Models\CommunityHashtag::orderByDesc('posts_count')->take(20)->get();
        return response()->json(['status' => 'success', 'data' => $hashtags]);
    }

    // ─── Search ────────────────────────────────────────────────────────
    public function search(Request $request)
    {
        $q = $request->get('q', '');
        $type = $request->get('type', 'posts');
        $userId = auth()->id();

        if ($type === 'users') {
            $results = \App\Models\User::with('communityProfile')
                ->where('name', 'like', "%$q%")
                ->orWhere('phone', 'like', "%$q%")
                ->limit(20)->get()
                ->map(fn ($u) => $this->transformUser($u, $userId));
            return response()->json(['status' => 'success', 'data' => $results]);
        }

        if ($type === 'groups') {
            $results = \App\Models\CommunityGroup::where('name', 'like', "%$q%")->where('privacy', 'public')->limit(20)->get();
            return response()->json(['status' => 'success', 'data' => $results]);
        }

        if ($type === 'hashtags') {
            $results = \App\Models\CommunityHashtag::where('name', 'like', "%$q%")->orderByDesc('posts_count')->limit(20)->get();
            return response()->json(['status' => 'success', 'data' => $results]);
        }

        $posts = CommunityPost::with(['user.communityProfile', 'media', 'userReaction'])
            ->where('content', 'like', "%$q%")
            ->where('privacy', 'public')
            ->latest()->paginate(15);
        return response()->json(['status' => 'success', 'data' => $this->transformPosts($posts, $userId)]);
    }

    // ─── Stories feed ──────────────────────────────────────────────────
    public function stories()
    {
        $userId = auth()->id();
        $followingIds = CommunityFollow::where('follower_id', $userId)->pluck('following_id');
        $followingIds->push($userId);

        $users = \App\Models\User::with(['communityProfile', 'stories' => function ($q) {
            $q->where('expires_at', '>', now())->latest();
        }])
        ->whereIn('id', $followingIds)
        ->whereHas('stories', fn ($q) => $q->where('expires_at', '>', now()))
        ->get()
        ->map(function ($u) use ($userId) {
            $stories = $u->stories->map(fn ($s) => array_merge($s->toArray(), ['is_viewed' => $s->isViewedBy($userId)]));
            $allViewed = $stories->every(fn ($s) => $s['is_viewed']);
            return ['user' => $this->transformUser($u, $userId), 'stories' => $stories, 'all_viewed' => $allViewed];
        });

        // Inject story ads
        $adSettings = json_decode(\DB::table('settings')->where('key', 'ad_display_settings')->value('value') ?? '{}', true) ?? [];
        $storyAdsEnabled = $adSettings['story_ads_enabled'] ?? true;
        $result = $users->values()->toArray();
        if ($storyAdsEnabled) {
            $storyAds = CommunityAdController::getAdsForPlacement('stories', $userId, 2);
            foreach ($storyAds as $i => $ad) {
                $pos = min(($i + 1) * 2, count($result));
                $adStoryGroup = [
                    'user'    => ['id' => 0, 'name' => $ad['page']['name'] ?? 'Sponsored', 'avatar' => $ad['page']['avatar'] ?? null, 'is_verified' => false, 'is_business' => true, 'is_me' => false, 'followers_count' => 0, 'following_count' => 0, 'posts_count' => 0, 'is_following' => false],
                    'stories' => [['id' => $ad['id'] ?? 0, 'type' => $ad['media_type'] ?? 'image', 'media_url' => $ad['media_url'] ?? null, 'thumbnail' => $ad['thumbnail_url'] ?? null, 'text_content' => null, 'bg_color' => null, 'views_count' => 0, 'expires_at' => now()->addDay()->toIso8601String(), 'created_at' => now()->toIso8601String(), 'is_viewed' => false, 'is_ad' => true, 'ad_cta_url' => $ad['cta_url'] ?? null, 'ad_cta_text' => $ad['cta_text'] ?? null, 'ad_title' => $ad['title'] ?? null]],
                    'all_viewed' => false,
                    'is_ad'      => true,
                ];
                array_splice($result, $pos, 0, [$adStoryGroup]);
            }
        }

        return response()->json(['status' => 'success', 'data' => $result]);
    }

    // ─── People suggestions (personalized) ─────────────────────────────
    public function suggestions()
    {
        $userId = auth()->id();
        $followingIds = CommunityFollow::where('follower_id', $userId)->pluck('following_id')->push($userId)->toArray();

        // Get users that the people you follow also follow (mutual connections)
        $mutualSuggestions = CommunityFollow::whereIn('follower_id', $followingIds)
            ->whereNotIn('following_id', $followingIds)
            ->select('following_id')
            ->selectRaw('COUNT(*) as mutual_count')
            ->groupBy('following_id')
            ->orderByDesc('mutual_count')
            ->limit(20)
            ->pluck('mutual_count', 'following_id');

        // Get users from creators the user interacts with
        $interactedCreators = \DB::table('user_interests')
            ->where('user_id', $userId)
            ->where('category', 'creator')
            ->orderByDesc('score')
            ->limit(10)
            ->pluck('value');

        // Combine: mutual suggestions + popular users + random discovery.
        // "Popular" used to require >10 followers, which is realistic for a
        // mature platform but starves a young one — audited: 0 users on this
        // platform currently have more than 10 followers, so that filter
        // alone made suggestions() return nothing for every single user,
        // mutuals or not. Same class of bug as the feed/reels cold-start
        // issue fixed earlier today. Order by followers_count instead of
        // gating on it, and always backfill with a no-criteria pool of
        // not-yet-followed users so this is never empty as long as anyone
        // else exists on the platform.
        $suggestedIds = $mutualSuggestions->keys()->toArray();
        $popularIds = \App\Models\User::with('communityProfile')
            ->whereNotIn('id', $followingIds)
            ->whereHas('communityProfile')
            ->orderByDesc(
                \App\Models\CommunityProfile::select('followers_count')
                    ->whereColumn('user_id', 'users.id')
            )
            ->limit(20)
            ->pluck('id')->toArray();

        $allIds = array_unique(array_merge($suggestedIds, $interactedCreators->toArray(), $popularIds));
        $allIds = array_diff($allIds, $followingIds);

        // Backfill: brand-new platform / brand-new user — no mutuals, no
        // interaction history, and few-to-no profiles yet. Fall back to any
        // other user at all rather than showing an empty People tab.
        if (count($allIds) < 10) {
            $fallbackIds = \App\Models\User::whereNotIn('id', $followingIds)
                ->whereNotIn('id', $allIds)
                ->orderByDesc('id')
                ->limit(20)
                ->pluck('id')->toArray();
            $allIds = array_unique(array_merge($allIds, $fallbackIds));
        }

        $users = \App\Models\User::with('communityProfile')
            ->whereIn('id', $allIds)
            ->limit(50)
            ->get()
            ->map(function ($u) use ($userId, $mutualSuggestions) {
                $data = $this->transformUser($u, $userId);
                $data['mutual_count'] = $mutualSuggestions[$u->id] ?? 0;
                return $data;
            })
            ->sortByDesc('mutual_count')
            ->values();

        return response()->json(['status' => 'success', 'data' => $users]);
    }

    // ─── Transform helpers ─────────────────────────────────────────────

    private function transformPosts($posts, int $userId): array
    {
        $postIds = $posts->pluck('id')->toArray();
        $followingIds = \DB::table('community_follows')
            ->where('follower_id', $userId)->pluck('following_id')->toArray();
        $savedIds = \DB::table('community_saved_posts')
            ->where('user_id', $userId)->whereIn('post_id', $postIds)->pluck('post_id')->toArray();
        return $posts->map(fn ($p) => $this->transformPost($p, $userId, $followingIds, $savedIds))->toArray();
    }

    public function transformPost($post, int $userId, array $followingIds = [], array $savedPostIds = []): array
    {
        $displayUser = $this->transformUser($post->user, $userId, $followingIds);
        if ($post->page_id && $post->page) {
            $displayUser['name'] = $post->page->name;
            $displayUser['avatar'] = cdn_url($post->page->avatar) ?? $displayUser['avatar'];
            $displayUser['is_business'] = true;
        }

        $sharedPost = null;
        if ($post->shared_post_id && $post->sharedPost) {
            $sp = $post->sharedPost;
            $sharedPost = [
                'id'         => $sp->id,
                'content'    => $sp->content,
                'type'       => $sp->type,
                'media'      => $sp->media->map(fn ($m) => ['id' => $m->id, 'type' => $m->type, 'url' => $m->url, 'thumbnail' => $m->thumbnail])->toArray(),
                'user'       => $this->transformUser($sp->user, $userId, $followingIds),
                'created_at' => $sp->created_at,
            ];
        }

        return [
            'id'                => $post->id,
            'type'              => $post->type,
            'content'           => $post->content,
            'location'          => $post->location,
            'feeling'           => $post->feeling,
            'privacy'           => $post->privacy,
            'is_pinned'         => $post->is_pinned,
            'comments_disabled' => $post->comments_disabled,
            'views_count'       => $post->views_count,
            'likes_count'       => $post->likes_count,
            'comments_count'    => $post->comments_count,
            'shares_count'      => $post->shares_count,
            'saves_count'       => $post->saves_count,
            'poll_options'      => $post->poll_options,
            'created_at'        => $post->created_at,
            'media'             => $post->media->map(fn ($m) => [
                'id'                   => $m->id,
                'type'                 => $m->type,
                'url'                  => cdn_url($m->getRawOriginal('url')),
                'hls_url'              => $m->getRawOriginal('hls_url') ? cdn_url($m->getRawOriginal('hls_url')) : null,
                'thumbnail'            => cdn_url($m->getRawOriginal('thumbnail')),
                'duration'             => $m->duration,
                'width'                => $m->width,
                'height'               => $m->height,
                'transcoding_status'   => $m->getRawOriginal('transcoding_status') ?? 'none',
                'transcoding_progress' => $m->transcoding_progress ?? 0,
            ])->toArray(),
            'user'              => $displayUser,
            'user_reaction'     => $post->userReaction?->type,
            'is_saved'          => in_array($post->id, $savedPostIds),
            'moderation_status' => $post->moderation_status ?? 'approved',
            'shared_post'       => $sharedPost,
            'page_id'           => $post->page_id,
            'page'              => $post->page_id ? ['id' => $post->page?->id, 'name' => $post->page?->name, 'avatar' => cdn_url($post->page?->avatar)] : null,
        ];
    }

    public function transformUser($user, int $userId, array $followingIds = []): array
    {
        if (!$user) return ['id' => 0, 'name' => 'Unknown', 'username' => null, 'avatar' => null, 'bio' => null, 'is_verified' => false, 'is_business' => false, 'followers_count' => 0, 'following_count' => 0, 'posts_count' => 0, 'is_following' => false, 'is_me' => false, 'cover_photo' => null, 'location' => null, 'website' => null];
        $profile = $user->communityProfile;
        return [
            'id'              => $user->id,
            'name'            => $user->name ?? 'User',
            'username'        => $profile?->username,
            'avatar'          => $user->avatar ?? null,
            'cover_photo'     => $profile ? cdn_url($profile->getRawOriginal('cover_photo')) : null,
            'bio'             => $profile?->bio,
            'location'        => null,
            'website'         => $profile?->website,
            'is_verified'     => $profile?->is_verified ?? false,
            'is_business'     => $profile?->is_business ?? false,
            'followers_count' => $profile?->followers_count ?? 0,
            'following_count' => $profile?->following_count ?? 0,
            'posts_count'     => $profile?->posts_count ?? 0,
            'is_following'    => in_array($user->id, $followingIds),
            'is_me'           => $user->id === $userId,
        ];
    }
}
