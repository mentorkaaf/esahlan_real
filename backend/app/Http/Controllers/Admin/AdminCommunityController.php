<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\CommunityGroup;
use App\Models\CommunityProfile;
use App\Models\CommunityReport;
use App\Models\CommunityStory;
use App\Models\CommunityMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class AdminCommunityController extends Controller
{
    public function index()
    {
        $stats = [
            'profiles'      => CommunityProfile::count(),
            'posts'         => CommunityPost::count(),
            'groups'        => CommunityGroup::count(),
            'reports'       => CommunityReport::where('status', 'pending')->count(),
            'stories_today' => CommunityStory::whereDate('created_at', today())->count(),
            'messages_today'=> CommunityMessage::whereDate('created_at', today())->count(),
        ];

        $recentPosts = CommunityPost::with(['user', 'media'])
            ->withCount('reactions as likes_count', 'comments', 'reports')
            ->latest()
            ->take(10)
            ->get();

        $pendingReports = CommunityReport::with('reporter')
            ->where('status', 'pending')
            ->latest()
            ->take(8)
            ->get();

        return view('admin.community.index', compact('stats', 'recentPosts', 'pendingReports'));
    }

    // ── Posts ──────────────────────────────────────────────────────────────────

    public function posts(Request $request)
    {
        $query = CommunityPost::with(['user', 'media'])
            ->withCount('reactions as likes_count', 'comments', 'reports as reports_count');

        if ($request->search) {
            $query->where('content', 'like', "%{$request->search}%");
        }
        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->status === 'reported') {
            $query->has('reports');
        }

        $posts = $query->latest()->paginate(20);
        return view('admin.community.posts', compact('posts'));
    }

    public function deletePost($id)
    {
        $post = CommunityPost::with('media')->findOrFail($id);
        $this->permanentlyDeletePost($post);

        return back()->with('success', 'Post permanently deleted.');
    }

    public function bulkDeletePosts(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) return back()->with('success', 'No posts selected.');
        CommunityPost::with('media')->whereIn('id', $ids)->get()->each(
            fn ($post) => $this->permanentlyDeletePost($post)
        );
        return back()->with('success', count($ids) . ' posts permanently deleted.');
    }

    /**
     * Hard-delete a post and everything attached to it — DB rows and the
     * actual files on disk. Admin deletes must not leave anything recoverable
     * or lingering: not a soft-deleted row, not an orphaned upload.
     *
     * Most child rows (media, reactions, comments + their replies/reactions,
     * saved_posts, hashtag pivot, poll_votes, feed_interactions,
     * feed_seen_posts, post_scores) are cleaned up automatically by the
     * cascadeOnDelete() foreign keys already defined on those tables — but
     * only on a REAL delete. CommunityPost/CommunityComment use SoftDeletes,
     * so the cascade only fires here because we call forceDelete(), not
     * delete() (a soft-delete is just an UPDATE setting deleted_at — no FK
     * cascade ever fires for it, and the row never actually leaves the table).
     *
     * Reports are polymorphic (reportable_type/reportable_id) and can't have
     * a real foreign key, so they're cleaned up explicitly. Files have no
     * database representation at all, so cascades never touch them — handled
     * explicitly here too, best-effort (a missing/already-gone file never
     * blocks the actual deletion).
     */
    private function permanentlyDeletePost(CommunityPost $post): void
    {
        foreach ($post->media as $m) {
            $this->deleteFileIfLocal($m->getRawOriginal('url'));
            $this->deleteFileIfLocal($m->getRawOriginal('thumbnail'));
            $this->deleteFileIfLocal($m->getRawOriginal('hls_url'));
        }
        foreach ($post->comments()->withTrashed()->get() as $c) {
            $this->deleteFileIfLocal($c->getRawOriginal('media_url'));
        }

        $post->reports()->delete();
        $post->forceDelete();
    }

    /**
     * Delete a file from the public disk given a stored media value — which
     * isn't stored consistently across models. CommunityPostMedia stores the
     * raw relative path and only wraps it into a proxy URL via a read-time
     * accessor (cdn_url() never touches what's written to the DB there), but
     * CommunityComment stores the already-cdn_url()-wrapped proxy URL
     * directly (`{APP_URL}/api/v1/media?f={encoded path}`). This handles
     * both: a bare relative path is used as-is; a proxy URL has its `f`
     * query param decoded back to the relative path; any other external URL
     * (YouTube, third-party CDNs) is left alone. Any failure (missing file,
     * bad path, disk error) is logged and swallowed — file cleanup is
     * best-effort and must never be the reason a database deletion fails.
     */
    private function deleteFileIfLocal(?string $value): void
    {
        if (!$value) return;
        try {
            $path = $value;
            if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                $query = parse_url($value, PHP_URL_QUERY);
                if (!$query) return; // external URL with no ?f= — not one of ours
                parse_str($query, $params);
                $path = $params['f'] ?? null;
                if (!$path) return;
            }
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        } catch (\Throwable $e) {
            Log::warning("Admin delete: failed to remove file {$value} — " . $e->getMessage());
        }
    }

    // ── Reports ────────────────────────────────────────────────────────────────

    public function reports(Request $request)
    {
        $status = $request->status ?? 'pending';

        $reports = CommunityReport::with('reporter')
            ->where('status', $status)
            ->latest()
            ->paginate(20);

        $counts = [
            'pending'   => CommunityReport::where('status', 'pending')->count(),
            'reviewed'  => CommunityReport::where('status', 'reviewed')->count(),
            'dismissed' => CommunityReport::where('status', 'dismissed')->count(),
        ];

        return view('admin.community.reports', compact('reports', 'counts'));
    }

    public function actionReport(Request $request, $id)
    {
        $report = CommunityReport::findOrFail($id);
        $report->update([
            'status'      => $request->action,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Report updated.');
    }

    // ── Groups ─────────────────────────────────────────────────────────────────

    public function groups(Request $request)
    {
        $query = CommunityGroup::with('owner')
            ->withCount('members', 'posts');

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $groups = $query->latest()->paginate(20);
        return view('admin.community.groups', compact('groups'));
    }

    public function deleteGroup($id)
    {
        $group = CommunityGroup::findOrFail($id);
        $group->members()->delete();
        $group->posts()->with('media')->get()->each(
            fn ($post) => $this->permanentlyDeletePost($post)
        );
        $group->delete();

        return back()->with('success', 'Group permanently deleted.');
    }

    // ── Users ──────────────────────────────────────────────────────────────────

    public function users(Request $request)
    {
        $query = CommunityProfile::with('user')
            ->withCount('posts', 'followers');

        if ($request->search) {
            $query->whereHas('user', fn($q) =>
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
            );
        }
        if ($request->verified !== null && $request->verified !== '') {
            $query->where('is_verified', (bool)$request->verified);
        }

        $users = $query->latest()->paginate(20);
        return view('admin.community.users', compact('users'));
    }

    public function toggleVerify($id)
    {
        $profile = CommunityProfile::findOrFail($id);
        $profile->update(['is_verified' => !$profile->is_verified]);

        return back()->with('success', $profile->is_verified ? 'User verified.' : 'Verification removed.');
    }

    // ── Content Moderation ────────────────────────────────────────────────────

    public function moderation()
    {
        $settings = \App\Services\ContentModerationService::getSettings();
        $keywords = implode(', ', \App\Services\ContentModerationService::getBlockedKeywords());
        $flaggedPosts = CommunityReport::with(['reportable', 'reporter'])
            ->where('reason', 'auto_moderation')
            ->where('status', 'pending')
            ->latest()
            ->paginate(20);

        return view('admin.community.moderation', compact('settings', 'keywords', 'flaggedPosts'));
    }

    public function updateModeration(Request $request)
    {
        $settings = [
            'enabled'             => $request->boolean('enabled'),
            'keyword_filter'      => $request->boolean('keyword_filter'),
            'image_scan'          => $request->boolean('image_scan'),
            'auto_block'          => $request->boolean('auto_block'),
            'review_all_media'    => $request->boolean('review_all_media'),
            'block_threshold'     => (float) ($request->block_threshold ?? 0.85),
            'review_threshold'    => (float) ($request->review_threshold ?? 0.60),
            'sightengine_user'    => $request->sightengine_user   ?? '',
            'sightengine_secret'  => $request->sightengine_secret ?? '',
        ];
        \App\Services\ContentModerationService::saveSettings($settings);

        if ($request->has('keywords')) {
            \App\Services\ContentModerationService::saveBlockedKeywords($request->keywords);
        }

        return back()->with('success', 'Moderation settings updated.');
    }

    public function approvePost($id)
    {
        $report = CommunityReport::findOrFail($id);
        $report->update(['status' => 'resolved', 'reviewed_at' => now()]);
        if ($report->reportable) {
            $report->reportable->update([
                'moderation_status' => 'approved',
                'privacy'           => 'public',
            ]);
        }
        return back()->with('success', 'Post approved.');
    }

    public function rejectPost($id)
    {
        $report = CommunityReport::findOrFail($id);
        $report->update(['status' => 'resolved', 'reviewed_at' => now()]);

        // Reportable is polymorphic — a reported post or a reported comment —
        // route each to a real permanent delete instead of the soft-delete
        // that ->delete() would otherwise leave behind.
        if ($report->reportable instanceof CommunityPost) {
            $report->reportable->load('media');
            $this->permanentlyDeletePost($report->reportable);
        } elseif ($report->reportable) {
            $this->deleteFileIfLocal($report->reportable->getRawOriginal('media_url'));
            $report->reportable->forceDelete();
        }

        return back()->with('success', 'Content permanently removed.');
    }

    public function engagement()
    {
        $posts = \App\Models\CommunityPost::with('user')->latest()->take(20)->get();
        $bots = \App\Models\User::where('email', 'like', '%@esahlan_bot.local')->get();
        return view('admin.community.engagement', ['posts' => $posts, 'bots' => $bots, 'botCount' => $bots->count()]);
    }

    public function generateEngagement(\Illuminate\Http\Request $request)
    {
        $request->validate(['post_id' => 'required|exists:community_posts,id']);
        
        $ctrl = new \App\Http\Controllers\Api\Admin\EngagementGeneratorController();
        $genReq = new \Illuminate\Http\Request([
            'post_id' => $request->post_id,
            'likes' => (int)$request->likes,
            'views' => (int)$request->views,
            'comments' => (int)$request->comments,
        ]);
        
        $result = $ctrl->generateAll($genReq);
        $data = json_decode($result->getContent(), true);

        return back()->with('success', 'Generated: ' . json_encode($data['data'] ?? []));
    }

    public function resetEngagement(\Illuminate\Http\Request $request)
    {
        $request->validate(['post_id' => 'required|exists:community_posts,id']);

        $ctrl = new \App\Http\Controllers\Api\Admin\EngagementGeneratorController();
        $genReq = new \Illuminate\Http\Request(['post_id' => $request->post_id]);
        $result = $ctrl->resetEngagement($genReq);
        $data = json_decode($result->getContent(), true);

        return back()->with('success', $data['message'] ?? 'Engagement reset.');
    }

    public function resetAllEngagement()
    {
        $ctrl = new \App\Http\Controllers\Api\Admin\EngagementGeneratorController();
        $result = $ctrl->resetAllEngagement();
        $data = json_decode($result->getContent(), true);

        return back()->with('success', $data['message'] ?? 'All engagement reset.');
    }

    // ── Algorithm Dashboard ────────────────────────────────────────────────────

    public function algorithm()
    {
        return view('admin.community.algorithm');
    }

    public function algorithmData()
    {
        // ── Top scored posts ──────────────────────────────────────────────────
        $topPosts = DB::table('community_posts')
            ->leftJoin('post_scores', 'community_posts.id', '=', 'post_scores.post_id')
            ->leftJoin('users', 'community_posts.user_id', '=', 'users.id')
            ->where('community_posts.privacy', '!=', 'private')
            ->whereNull('community_posts.deleted_at')
            ->where('community_posts.video_ready', true)
            ->orderByDesc(DB::raw('COALESCE(post_scores.final_score, 0)'))
            ->limit(20)
            ->get([
                'community_posts.id',
                'community_posts.type',
                'community_posts.content',
                'community_posts.views_count',
                'community_posts.likes_count',
                'community_posts.comments_count',
                'community_posts.shares_count',
                'community_posts.created_at',
                'users.name as author',
                DB::raw('COALESCE(post_scores.final_score, 0) as final_score'),
                DB::raw('COALESCE(post_scores.engagement_score, 0) as engagement_score'),
                DB::raw('COALESCE(post_scores.velocity_score, 0) as velocity_score'),
                DB::raw('COALESCE(post_scores.quality_score, 0) as quality_score'),
                DB::raw('COALESCE(post_scores.viral_score, 0) as viral_score'),
                DB::raw('COALESCE(post_scores.engagement_rate, 0) as engagement_rate'),
                DB::raw('COALESCE(post_scores.impression_count, 0) as impression_count'),
                DB::raw('COALESCE(post_scores.engaged_count, 0) as engaged_count'),
            ]);

        // ── Top users by activity ─────────────────────────────────────────────
        $topUsers = DB::table('users')
            ->leftJoin('community_profiles', 'users.id', '=', 'community_profiles.user_id')
            ->leftJoin(DB::raw('(SELECT user_id, COUNT(*) as post_count, SUM(likes_count + comments_count * 2 + shares_count * 3) as total_engagement FROM community_posts WHERE deleted_at IS NULL GROUP BY user_id) as ps'), 'users.id', '=', 'ps.user_id')
            ->leftJoin(DB::raw('(SELECT user_id, COUNT(*) as interaction_count FROM feed_interactions WHERE created_at > NOW() - INTERVAL 7 DAY GROUP BY user_id) as fi'), 'users.id', '=', 'fi.user_id')
            ->orderByDesc(DB::raw('COALESCE(ps.total_engagement, 0) + COALESCE(fi.interaction_count, 0)'))
            ->limit(15)
            ->get([
                'users.id',
                'users.name',
                'users.email',
                DB::raw('COALESCE(community_profiles.followers_count, 0) as followers_count'),
                DB::raw('COALESCE(community_profiles.posts_count, 0) as posts_count'),
                DB::raw('COALESCE(ps.post_count, 0) as post_count'),
                DB::raw('COALESCE(ps.total_engagement, 0) as total_engagement'),
                DB::raw('COALESCE(fi.interaction_count, 0) as interactions_7d'),
                DB::raw('COALESCE(ps.total_engagement, 0) + COALESCE(fi.interaction_count, 0) as total_score'),
            ]);

        // ── Feed composition stats ────────────────────────────────────────────
        $totalPosts    = CommunityPost::where('video_ready', true)->whereNull('deleted_at')->count();
        $postsWithScore = DB::table('post_scores')->count();
        $avgScore      = DB::table('post_scores')->avg('final_score') ?? 0;
        $maxScore      = DB::table('post_scores')->max('final_score') ?? 0;

        // Interactions in last 24h
        $interactions24h = DB::table('feed_interactions')
            ->where('created_at', '>', now()->subHours(24))
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        // Posts by type
        $postsByType = CommunityPost::whereNull('deleted_at')
            ->where('video_ready', true)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        // Seen posts in last hour (active sessions)
        $seenLastHour = DB::table('feed_seen_posts')
            ->where('seen_at', '>', now()->subHour())
            ->distinct('user_id')
            ->count('user_id');

        // Score distribution buckets
        $scoreDistribution = DB::table('post_scores')
            ->selectRaw("
                SUM(CASE WHEN final_score >= 80 THEN 1 ELSE 0 END) as hot,
                SUM(CASE WHEN final_score >= 50 AND final_score < 80 THEN 1 ELSE 0 END) as warm,
                SUM(CASE WHEN final_score >= 20 AND final_score < 50 THEN 1 ELSE 0 END) as cool,
                SUM(CASE WHEN final_score < 20 THEN 1 ELSE 0 END) as cold
            ")
            ->first();

        // Top hashtags
        $topHashtags = DB::table('community_hashtags')
            ->orderByDesc('posts_count')
            ->limit(10)
            ->get(['name', 'posts_count']);

        // Real-time active feed users (Redis sorted set, 2-minute window)
        $activeFeedUsers = Redis::zcount('feed:active_users', now()->subMinutes(2)->timestamp, '+inf');

        return response()->json([
            'top_posts'           => $topPosts,
            'top_users'           => $topUsers,
            'total_posts'         => $totalPosts,
            'posts_with_score'    => $postsWithScore,
            'avg_score'           => round($avgScore, 2),
            'max_score'           => round($maxScore, 2),
            'interactions_24h'    => $interactions24h,
            'posts_by_type'       => $postsByType,
            'seen_last_hour'      => $seenLastHour,
            'score_distribution'  => $scoreDistribution,
            'top_hashtags'        => $topHashtags,
            'active_feed_users'   => $activeFeedUsers,
            'generated_at'        => now()->toDateTimeString(),
        ]);
    }
}
