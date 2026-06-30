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
        $post = CommunityPost::findOrFail($id);
        $post->media()->delete();
        $post->reactions()->delete();
        $post->comments()->delete();
        $post->reports()->delete();
        $post->delete();

        return back()->with('success', 'Post deleted successfully.');
    }

    public function bulkDeletePosts(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) return back()->with('success', 'No posts selected.');
        CommunityPost::whereIn('id', $ids)->each(function ($post) {
            $post->media()->delete();
            $post->reactions()->delete();
            $post->comments()->delete();
            $post->delete();
        });
        return back()->with('success', count($ids) . ' posts deleted.');
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
        $group->posts()->delete();
        $group->delete();

        return back()->with('success', 'Group deleted.');
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
            'enabled'          => $request->boolean('enabled'),
            'keyword_filter'   => $request->boolean('keyword_filter'),
            'image_scan'       => $request->boolean('image_scan'),
            'auto_block'       => $request->boolean('auto_block'),
            'review_all_media' => $request->boolean('review_all_media'),
            'block_threshold'  => (float) ($request->block_threshold ?? 0.85),
            'review_threshold' => (float) ($request->review_threshold ?? 0.60),
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
        if ($report->reportable) {
            $report->reportable->delete();
        }
        return back()->with('success', 'Post removed.');
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
}
