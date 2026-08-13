<?php

namespace App\Http\Controllers\Admin;

use App\Events\CommunityStatusChanged;
use App\Helpers\AppSettings;
use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\CommunityGroup;
use App\Models\CommunityProfile;
use App\Models\CommunityReport;
use App\Models\CommunityStory;
use App\Models\CommunityMessage;
use App\Models\CommunityChat;
use App\Models\CommunityChatMember;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminCommunityController extends Controller
{
    public function index()
    {
        $now   = Carbon::now();
        $today = Carbon::today();
        $week  = Carbon::now()->subDays(7);
        $month = Carbon::now()->subDays(30);

        // ── Core counts ──────────────────────────────────────────────────────
        $stats = [
            'members'          => CommunityProfile::count(),
            'members_today'    => CommunityProfile::whereDate('created_at', $today)->count(),
            'members_week'     => CommunityProfile::where('created_at', '>=', $week)->count(),

            'posts'            => CommunityPost::count(),
            'posts_today'      => CommunityPost::whereDate('created_at', $today)->count(),
            'posts_week'       => CommunityPost::where('created_at', '>=', $week)->count(),

            'stories_today'    => CommunityStory::whereDate('created_at', $today)->count(),
            'stories_week'     => CommunityStory::where('created_at', '>=', $week)->count(),

            'messages_today'   => CommunityMessage::whereDate('created_at', $today)->count(),
            'messages_week'    => CommunityMessage::where('created_at', '>=', $week)->count(),

            'reports_pending'  => CommunityReport::where('status', 'pending')->count(),
            'reports_total'    => CommunityReport::count(),

            'groups'           => CommunityGroup::count(),
            'active_chats'     => CommunityChat::where('updated_at', '>=', Carbon::now()->subHours(24))->count(),

            'total_views'      => CommunityPost::sum('views_count'),
            'total_reactions'  => \Illuminate\Support\Facades\DB::table('community_post_reactions')->count(),
            'total_comments'   => \Illuminate\Support\Facades\DB::table('community_comments')->count(),
        ];

        // ── Post type breakdown ──────────────────────────────────────────────
        $postTypes = CommunityPost::selectRaw('type, count(*) as cnt')
            ->groupBy('type')->pluck('cnt', 'type');

        // ── Daily posts last 7 days ──────────────────────────────────────────
        $dailyPosts = CommunityPost::selectRaw('DATE(created_at) as date, COUNT(*) as cnt')
            ->where('created_at', '>=', $week)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('cnt', 'date');

        // ── Top active users ─────────────────────────────────────────────────
        $topUsers = CommunityProfile::with('user:id,name,avatar')
            ->orderByDesc('followers_count')
            ->take(5)->get();

        // ── Recent posts ─────────────────────────────────────────────────────
        $recentPosts = CommunityPost::with(['user', 'media'])
            ->withCount('reactions as likes_count', 'comments')
            ->latest()->take(8)->get();

        // ── Pending reports ──────────────────────────────────────────────────
        $pendingReports = CommunityReport::with(['reporter'])
            ->where('status', 'pending')
            ->latest()->take(6)->get();

        return view('admin.community.index',
            compact('stats', 'postTypes', 'dailyPosts', 'topUsers', 'recentPosts', 'pendingReports'));
    }

    // ── Posts ──────────────────────────────────────────────────────────────────

    public function posts(Request $request)
    {
        $query = CommunityPost::with(['user', 'media'])
            ->withCount('reactions as likes_count', 'comments', 'reports as reports_count');

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('content', 'like', "%{$request->search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$request->search}%"));
            });
        }
        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->privacy) {
            $query->where('privacy', $request->privacy);
        }
        if ($request->status === 'reported') {
            $query->has('reports');
        }
        if ($request->video_status === 'pending') {
            $query->where('type', 'video')->where('video_ready', false);
        }

        $posts = $query->latest()->paginate(20);

        // Stats for header cards
        $typeCounts = CommunityPost::selectRaw('type, count(*) as cnt')->groupBy('type')->pluck('cnt', 'type');
        $totalViews = CommunityPost::sum('views_count');
        return view('admin.community.posts', compact('posts', 'typeCounts', 'totalViews'));
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

    public function togglePostPrivacy($id)
    {
        $post = CommunityPost::findOrFail($id);
        $post->privacy = $post->privacy === 'private' ? 'public' : 'private';
        $post->save();
        return back()->with('success', 'Post #' . $id . ' set to ' . $post->privacy . '.');
    }

    public function bulkPrivacyPosts(Request $request)
    {
        $ids     = $request->input('ids', []);
        $privacy = $request->input('privacy', 'private');
        if (!in_array($privacy, ['public', 'private', 'friends'])) $privacy = 'private';
        if (empty($ids)) return back()->with('success', 'No posts selected.');
        CommunityPost::whereIn('id', $ids)->update(['privacy' => $privacy]);
        return back()->with('success', count($ids) . ' posts set to ' . $privacy . '.');
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
            // For video posts: delete the entire HLS folder (contains .ts segments,
            // master.m3u8, optimized.mp4, thumb.jpg). The folder lives at
            // community/posts/{hash}/ — extract it from hls_url or url.
            $hlsUrl = $m->getRawOriginal('hls_url');
            if ($hlsUrl) {
                $this->deleteVideoFolder($hlsUrl);
            } else {
                // Image/non-video: delete individual files
                $this->deleteFileIfLocal($m->getRawOriginal('url'));
                $this->deleteFileIfLocal($m->getRawOriginal('thumbnail'));
            }
        }
        foreach ($post->comments()->withTrashed()->get() as $c) {
            $this->deleteFileIfLocal($c->getRawOriginal('media_url'));
        }

        $post->reports()->delete();
        $post->forceDelete();
    }

    /**
     * Delete the entire video folder for a post. Video posts store all their
     * files (HLS segments, optimized.mp4, thumb.jpg) inside a single hashed
     * folder: community/posts/{hash}/. Deleting the folder in one shot is both
     * faster and guaranteed to leave no orphaned files behind.
     *
     * Accepts hls_url in any of these formats:
     *   - community/posts/{hash}/hls/master.m3u8  (raw relative path)
     *   - https://esahlan.com/api/v1/media?f=community/posts/{hash}/hls/master.m3u8
     */
    private function deleteVideoFolder(?string $hlsUrl): void
    {
        if (!$hlsUrl) return;
        try {
            $path = $hlsUrl;
            if (str_starts_with($hlsUrl, 'http://') || str_starts_with($hlsUrl, 'https://')) {
                $query = parse_url($hlsUrl, PHP_URL_QUERY);
                if (!$query) return;
                parse_str($query, $params);
                $path = $params['f'] ?? null;
                if (!$path) return;
            }
            // path is like: community/posts/{hash}/hls/master.m3u8
            // Walk up two levels to get community/posts/{hash}/
            $folder = dirname(dirname($path)); // removes /hls/master.m3u8
            if (!str_starts_with($folder, 'community/posts/') || $folder === 'community/posts') {
                // Safety guard: never delete the root posts folder
                Log::warning("Admin delete: suspicious HLS folder path '{$folder}' — skipped.");
                return;
            }
            if (Storage::disk('public')->exists($folder)) {
                Storage::disk('public')->deleteDirectory($folder);
                Log::info("Admin delete: removed video folder {$folder}");
            }
        } catch (\Throwable $e) {
            Log::warning("Admin delete: failed to remove video folder for {$hlsUrl} — " . $e->getMessage());
        }
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
        $query = CommunityProfile::with('user')->withCount('posts', 'followers');

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->whereHas('user', fn($u) =>
                    $u->where('name', 'like', "%{$request->search}%")
                      ->orWhere('email', 'like', "%{$request->search}%")
                )->orWhere('username', 'like', "%{$request->search}%")
                 ->orWhere('display_name', 'like', "%{$request->search}%");
            });
        }
        if ($request->verified !== null && $request->verified !== '') {
            $query->where('is_verified', (bool)$request->verified);
        }
        if ($request->gender) {
            $query->where('gender', $request->gender);
        }
        if ($request->country) {
            $query->where('country', $request->country);
        }
        if ($request->onboarded !== null && $request->onboarded !== '') {
            $query->where('onboarding_completed', (bool)$request->onboarded);
        }

        if ($request->status) {
            $query->whereHas('user', fn($u) => $u->where('status', $request->status));
        }

        $users = $query->latest()->paginate(24);

        // Attach strike counts
        $userIds = $users->pluck('user_id')->toArray();
        $strikeCounts = \DB::table('user_strikes')
            ->whereIn('user_id', $userIds)
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $users->each(function($profile) use ($strikeCounts) {
            $profile->strike_count = $strikeCounts[$profile->user_id] ?? 0;
        });

        $stats = [
            'total'        => CommunityProfile::count(),
            'verified'     => CommunityProfile::where('is_verified', true)->count(),
            'new_week'     => CommunityProfile::where('created_at', '>=', now()->subWeek())->count(),
            'onboarded'    => CommunityProfile::where('onboarding_completed', true)->count(),
            'male'         => CommunityProfile::where('gender', 'male')->count(),
            'female'       => CommunityProfile::where('gender', 'female')->count(),
            'other_gender' => CommunityProfile::whereNotIn('gender', ['male', 'female'])->whereNotNull('gender')->count(),
            'restricted'   => \App\Models\User::where('status', 'restricted')->count(),
            'struck'       => \DB::table('user_strikes')->distinct('user_id')->count('user_id'),
        ];

        $countries = CommunityProfile::whereNotNull('country')
            ->selectRaw('country, COUNT(*) as cnt')
            ->groupBy('country')
            ->orderByDesc('cnt')
            ->limit(10)
            ->pluck('cnt', 'country');

        return view('admin.community.users', compact('users', 'stats', 'countries'));
    }

    public function toggleVerify($id)
    {
        $profile = CommunityProfile::findOrFail($id);
        $profile->update(['is_verified' => !$profile->is_verified]);

        return back()->with('success', $profile->is_verified ? 'User verified.' : 'Verification removed.');
    }

    public function userDetail($id)
    {
        $profile = CommunityProfile::with('user')->findOrFail($id);
        $recentPosts = CommunityPost::where('user_id', $profile->user_id)
            ->withCount('reactions as likes_count', 'comments')
            ->latest()->take(6)->get(['id','content','type','created_at','likes_count','views_count','comments_count']);

        $strikes = \DB::table('user_strikes')
            ->where('user_id', $profile->user_id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'profile'      => $profile,
            'recent_posts' => $recentPosts,
            'strikes'      => $strikes,
            'strike_count' => $strikes->count(),
        ]);
    }

    public function userChats($userId)
    {
        $profile = CommunityProfile::where('user_id', $userId)->firstOrFail();
        $chatIds = CommunityChatMember::where('user_id', $userId)->pluck('chat_id');

        $chats = CommunityChat::whereIn('id', $chatIds)
            ->with(['members.user.communityProfile', 'messages' => fn($q) => $q->latest()->limit(1)])
            ->withCount('messages')
            ->latest('updated_at')
            ->take(20)
            ->get()
            ->map(function($chat) use ($userId) {
                $other = $chat->members->firstWhere('user_id', '!=', $userId);
                return [
                    'id' => $chat->id,
                    'type' => $chat->type,
                    'name' => $chat->name ?? optional(optional($other)->user)->name,
                    'avatar' => optional(optional(optional($other)->user)->communityProfile)->avatar,
                    'last_message' => optional($chat->messages->first())->content,
                    'last_message_at' => optional($chat->messages->first())->created_at,
                    'messages_count' => $chat->messages_count,
                ];
            });

        return response()->json(['chats' => $chats]);
    }

    public function chatMessages($chatId)
    {
        $chat = CommunityChat::with('members.user.communityProfile')->findOrFail($chatId);
        $messages = CommunityMessage::where('chat_id', $chatId)
            ->with('user.communityProfile')
            ->orderBy('created_at')
            ->take(100)
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => optional($m->user)->name,
                'avatar' => optional(optional($m->user)->communityProfile)->avatar,
                'type' => $m->type,
                'content' => $m->is_deleted ? '[deleted]' : $m->content,
                'media_url' => $m->is_deleted ? null : $m->media_url,
                'is_deleted' => $m->is_deleted,
                'created_at' => $m->created_at,
            ]);

        $members = $chat->members->map(fn($cm) => [
            'user_id' => $cm->user_id,
            'name' => optional($cm->user)->name,
            'avatar' => optional(optional($cm->user)->communityProfile)->avatar,
        ]);

        return response()->json(['messages' => $messages, 'members' => $members]);
    }

    public function chatMonitor(Request $request)
    {
        $search = $request->search;
        $chats = CommunityChat::with(['members.user.communityProfile', 'messages' => fn($q) => $q->latest()->limit(1)])
            ->withCount('messages')
            ->when($search, fn($q) => $q->whereHas('members.user', fn($u) => $u->where('name', 'like', "%{$search}%")))
            ->orderBy('updated_at', 'desc')
            ->take(50)
            ->get()
            ->map(function($chat) {
                $memberNames = $chat->members->map(fn($cm) => optional($cm->user)->name)->filter()->implode(', ');
                $lastMsg = $chat->messages->first();
                return [
                    'id' => $chat->id,
                    'type' => $chat->type,
                    'name' => $chat->name ?? $memberNames,
                    'members' => $chat->members->map(fn($cm) => [
                        'user_id' => $cm->user_id,
                        'name' => optional($cm->user)->name,
                        'avatar' => optional(optional($cm->user)->communityProfile)->avatar,
                    ])->values(),
                    'last_message' => $lastMsg && !$lastMsg->is_deleted ? $lastMsg->content : null,
                    'last_message_type' => optional($lastMsg)->type,
                    'last_message_at' => optional($lastMsg)->created_at,
                    'messages_count' => $chat->messages_count,
                    'updated_at' => $chat->updated_at,
                ];
            });

        return response()->json(['chats' => $chats]);
    }

    public function banUser(Request $request, $userId)
    {
        $user = \App\Models\User::findOrFail($userId);
        $reason = $request->input('reason', 'Violated community guidelines');
        $user->update(['status' => 'banned']);
        // Revoke ALL tokens — forces immediate logout on every device
        $user->tokens()->delete();
        \App\Services\SecurityAuditService::log('admin.user_banned', 'warn', [
            'banned_user_id' => $userId,
            'reason'         => $reason,
            'admin_id'       => auth()->id(),
        ]);
        return response()->json(['success' => true, 'message' => "User #{$userId} banned and logged out."]);
    }

    public function unbanUser($userId)
    {
        $user = \App\Models\User::findOrFail($userId);
        $user->update(['status' => 'active']);
        \App\Services\SecurityAuditService::log('admin.user_unbanned', 'info', [
            'unbanned_user_id' => $userId,
            'admin_id'         => auth()->id(),
        ]);
        return response()->json(['success' => true, 'message' => "User #{$userId} unbanned."]);
    }

    public function unrestrictUser($userId)
    {
        $user = \App\Models\User::findOrFail($userId);
        $user->update(['status' => 'active']);
        // Clear strikes so they get a fresh start
        \DB::table('user_strikes')->where('user_id', $userId)->delete();
        \App\Services\SecurityAuditService::log('admin.user_unrestricted', 'info', [
            'user_id'  => $userId,
            'admin_id' => auth()->id(),
        ]);
        return response()->json(['success' => true, 'message' => "User #{$userId} unrestricted and strikes cleared."]);
    }

    public function clearStrikes($userId)
    {
        \DB::table('user_strikes')->where('user_id', $userId)->delete();
        return response()->json(['success' => true, 'message' => "Strikes cleared for user #{$userId}."]);
    }

    public function deleteMessage($id)
    {
        $msg = CommunityMessage::findOrFail($id);
        $msg->update(['is_deleted' => true, 'content' => null]);
        return response()->json(['success' => true]);
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
            'enabled'          => $request->boolean('enabled'),
            'keyword_filter'   => $request->boolean('keyword_filter'),
            'image_scan'       => $request->boolean('image_scan'),
            'video_scan'       => $request->boolean('video_scan'),
            'auto_block'       => $request->boolean('auto_block'),
            'review_all_media' => $request->boolean('review_all_media'),
            'block_threshold'  => (float) ($request->block_threshold ?? 0.80),
            'review_threshold' => (float) ($request->review_threshold ?? 0.50),
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
            $report->reportable->update(['privacy' => 'public']);
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

    public function algorithm()
    {
        return view('admin.community.algorithm');
    }

    public function algorithmData()
    {
        $totalPosts = \App\Models\CommunityPost::whereNull('deleted_at')
            ->where('created_at', '>', now()->subDays(90))->count();

        $scoreStats = \DB::table('post_scores')
            ->selectRaw('COUNT(*) as posts_with_score, AVG(final_score) as avg_score, MAX(final_score) as max_score')
            ->first();

        $scoreDist = \DB::table('post_scores')
            ->selectRaw("
                SUM(CASE WHEN final_score >= 80 THEN 1 ELSE 0 END) as hot,
                SUM(CASE WHEN final_score >= 50 AND final_score < 80 THEN 1 ELSE 0 END) as warm,
                SUM(CASE WHEN final_score >= 20 AND final_score < 50 THEN 1 ELSE 0 END) as cool,
                SUM(CASE WHEN final_score < 20 THEN 1 ELSE 0 END) as cold
            ")->first();

        $topPosts = \DB::table('post_scores')
            ->join('community_posts', 'post_scores.post_id', '=', 'community_posts.id')
            ->join('users', 'community_posts.user_id', '=', 'users.id')
            ->select(
                'post_scores.post_id', 'post_scores.final_score', 'post_scores.engagement_score',
                'post_scores.velocity_score', 'post_scores.velocity_24h',
                'post_scores.viral_score', 'post_scores.quality_score',
                'post_scores.impression_count', 'post_scores.engaged_count', 'post_scores.engagement_rate',
                'post_scores.like_rate', 'post_scores.comment_rate',
                'post_scores.save_rate', 'post_scores.share_rate',
                'post_scores.avg_dwell_ms', 'post_scores.rewatch_count',
                'post_scores.distribution_stage', 'post_scores.distribution_cap',
                'post_scores.negative_count', 'post_scores.watch_completion',
                'community_posts.content', 'community_posts.type', 'community_posts.created_at',
                'community_posts.likes_count', 'community_posts.comments_count',
                'community_posts.shares_count', 'community_posts.views_count',
                'users.name as author'
            )
            ->whereNull('community_posts.deleted_at')
            ->orderByDesc('post_scores.final_score')
            ->limit(20)
            ->get();

        $interactions = \DB::table('feed_interactions')
            ->where('created_at', '>', now()->subDay())
            ->selectRaw('type as interaction_type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'interaction_type');

        $topHashtags = \DB::table('community_hashtags')
            ->orderByDesc('posts_count')->limit(10)->get(['name', 'posts_count']);

        // Real-time: users actively on the feed screen right now (heartbeat within 90s)
        $now = now()->timestamp;
        \Illuminate\Support\Facades\Redis::zremrangebyscore('feed:online', '-inf', $now - 90);
        $activeUsers = (int) \Illuminate\Support\Facades\Redis::zcard('feed:online');

        // Most active users: top by interaction count (last 7 days)
        $topUsers = \DB::table('feed_interactions')
            ->join('users', 'feed_interactions.user_id', '=', 'users.id')
            ->leftJoin('community_profiles', 'community_profiles.user_id', '=', 'users.id')
            ->where('feed_interactions.created_at', '>', now()->subDays(7))
            ->selectRaw('
                users.id, users.name,
                COALESCE(users.avatar, "") as avatar,
                COALESCE(community_profiles.username, "") as username,
                COALESCE(community_profiles.followers_count, 0) as followers_count,
                COALESCE(community_profiles.posts_count, 0) as post_count,
                COUNT(feed_interactions.id) as interactions_7d,
                COUNT(feed_interactions.id) as total_score
            ')
            ->groupBy('users.id', 'users.name', 'users.avatar',
                      'community_profiles.username', 'community_profiles.followers_count',
                      'community_profiles.posts_count')
            ->orderByDesc('interactions_7d')
            ->limit(10)
            ->get();

        // Posts by type
        $postsByType = \App\Models\CommunityPost::whereNull('deleted_at')
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        return response()->json([
            'total_posts'        => $totalPosts,
            'posts_with_score'   => $scoreStats->posts_with_score ?? 0,
            'avg_score'          => round($scoreStats->avg_score ?? 0, 1),
            'max_score'          => round($scoreStats->max_score ?? 0, 1),
            'score_distribution' => [
                'hot'  => $scoreDist->hot  ?? 0,
                'warm' => $scoreDist->warm ?? 0,
                'cool' => $scoreDist->cool ?? 0,
                'cold' => $scoreDist->cold ?? 0,
            ],
            'top_posts'          => $topPosts,
            'interactions_24h'   => $interactions,
            'top_hashtags'       => $topHashtags,
            'active_feed_users'  => $activeUsers,
            'top_users'          => $topUsers,
            'posts_by_type'      => $postsByType,
        ]);
    }

    public function toggleEnabled()
    {
        $current = AppSettings::get('community_enabled', true);
        $enabled = !$current;
        AppSettings::set('community_enabled', $enabled ? '1' : '0', 'boolean');
        broadcast(new CommunityStatusChanged($enabled));
        return back()->with('success', 'eSpace ' . ($enabled ? 'enabled' : 'disabled'));
    }
}
