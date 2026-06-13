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
}
