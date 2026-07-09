<?php
namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunityModerationController extends Controller
{
    // GET /community/my/moderation
    public function myStatus()
    {
        $userId = auth()->id();

        $activePoints = (int) DB::table('ts_strikes')
            ->where('user_id', $userId)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->sum('points');

        $totalStrikes = DB::table('ts_strikes')->where('user_id', $userId)->count();

        $activeStrikes = DB::table('ts_strikes')
            ->where('user_id', $userId)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('created_at')
            ->get(['violation_type', 'severity', 'points', 'reason', 'expires_at', 'created_at'])
            ->toArray();

        $restrictions = DB::table('ts_restrictions')
            ->where('user_id', $userId)
            ->where('active', true)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get(['type', 'reason', 'expires_at'])
            ->toArray();

        $pendingPosts = DB::table('community_posts')
            ->where('user_id', $userId)
            ->where('moderation_status', 'pending')
            ->count();

        $blockedPosts = DB::table('community_posts')
            ->where('user_id', $userId)
            ->where('moderation_status', 'blocked')
            ->count();

        $accountStatus = match(true) {
            count($restrictions) > 0  => 'restricted',
            $activePoints >= 7        => 'at_risk',
            $activePoints >= 3        => 'warned',
            default                   => 'good',
        };

        return response()->json([
            'account_status'  => $accountStatus,
            'strike_points'   => $activePoints,
            'total_strikes'   => $totalStrikes,
            'active_strikes'  => $activeStrikes,
            'restrictions'    => $restrictions,
            'pending_posts'   => $pendingPosts,
            'blocked_posts'   => $blockedPosts,
        ]);
    }

    // GET /community/my/appeals
    public function myAppeals()
    {
        $appeals = DB::table('ts_appeals')
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'action_type', 'reason', 'status', 'moderator_note', 'created_at', 'reviewed_at'])
            ->toArray();

        return response()->json(['data' => $appeals]);
    }

    // POST /community/my/appeals
    public function submitAppeal(Request $request)
    {
        $request->validate([
            'post_id' => 'required|integer|exists:community_posts,id',
            'reason'  => 'required|string|min:10|max:1000',
            'evidence' => 'nullable|string|max:2000',
        ]);

        $userId = auth()->id();

        // Prevent duplicate pending appeal for same post
        $exists = DB::table('ts_appeals')
            ->where('user_id', $userId)
            ->where('appealable_type', 'App\\Models\\CommunityPost')
            ->where('appealable_id', $request->post_id)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You already have a pending appeal for this post.'], 422);
        }

        DB::table('ts_appeals')->insert([
            'user_id'        => $userId,
            'appealable_type'=> 'App\\Models\\CommunityPost',
            'appealable_id'  => $request->post_id,
            'action_type'    => 'content_removal',
            'reason'         => $request->reason,
            'evidence'       => $request->evidence,
            'status'         => 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return response()->json(['message' => 'Appeal submitted successfully.']);
    }

    // POST /community/posts/{id}/report
    public function reportPost(Request $request, int $postId)
    {
        $request->validate([
            'reason'      => 'required|in:spam,violence,fake_news,scam,harassment,pornography,copyright,other',
            'description' => 'nullable|string|max:500',
        ]);

        $userId = auth()->id();

        // Prevent duplicate report from same user
        $exists = DB::table('community_reports')
            ->where('reporter_id', $userId)
            ->where('reportable_type', 'App\\Models\\CommunityPost')
            ->where('reportable_id', $postId)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already reported.'], 422);
        }

        DB::table('community_reports')->insert([
            'reporter_id'     => $userId,
            'reportable_type' => 'App\\Models\\CommunityPost',
            'reportable_id'   => $postId,
            'reason'          => $request->reason,
            'description'     => $request->description,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json(['message' => 'Report submitted. Thank you.']);
    }
}
