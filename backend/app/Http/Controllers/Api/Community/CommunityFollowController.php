<?php
namespace App\Http\Controllers\Api\Community;
use App\Http\Controllers\Controller;
use App\Models\CommunityFollow;
use App\Models\CommunityProfile;
use App\Models\CommunityNotification;
use App\Models\User;
use App\Services\FcmService;
use App\Services\PrivacyService;
use App\Services\RealtimeService;
use Illuminate\Support\Facades\DB;

class CommunityFollowController extends Controller
{
    // POST /community/follow/{userId}
    public function toggle(int $userId)
    {
        $me = auth()->id();
        if ($me === $userId) return response()->json(['status'=>'error','message'=>'Cannot follow yourself'],422);

        $check = PrivacyService::canFollow($me, $userId);
        if (!$check['allowed']) {
            return response()->json(['status'=>'error','message'=>$check['reason']],403);
        }

        // Is the target account private?
        $row = DB::table('user_settings')->where('user_id', $userId)->first();
        $privacy = json_decode($row?->privacy ?? '{}', true);
        $isPrivate = $privacy['private_account'] ?? false;

        $result = DB::transaction(function () use ($me, $userId, $isPrivate) {
            $existing = CommunityFollow::where('follower_id', $me)->where('following_id', $userId)->first();

            if ($existing) {
                // Cancel request or unfollow
                $wasPending = $existing->status === 'pending';
                $existing->delete();
                if (!$wasPending) {
                    CommunityProfile::where('user_id', $me)->decrement('following_count');
                    CommunityProfile::where('user_id', $userId)->decrement('followers_count');
                }
                return ['action' => $wasPending ? 'request_cancelled' : 'unfollowed'];
            }

            if ($isPrivate) {
                // Send follow request — pending until accepted
                CommunityFollow::create(['follower_id' => $me, 'following_id' => $userId, 'status' => 'pending']);
                CommunityNotification::create([
                    'user_id' => $userId, 'actor_id' => $me,
                    'type' => 'follow_request', 'notifiable_type' => 'user', 'notifiable_id' => $userId,
                ]);
                return ['action' => 'requested', 'fcm_token' => User::where('id', $userId)->value('fcm_token')];
            }

            // Public account — follow immediately
            CommunityFollow::create(['follower_id' => $me, 'following_id' => $userId, 'status' => 'accepted']);
            CommunityProfile::firstOrCreate(['user_id' => $me])->increment('following_count');
            CommunityProfile::firstOrCreate(['user_id' => $userId])->increment('followers_count');
            CommunityNotification::create([
                'user_id' => $userId, 'actor_id' => $me,
                'type' => 'follow', 'notifiable_type' => 'user', 'notifiable_id' => $userId,
            ]);
            return ['action' => 'followed', 'fcm_token' => User::where('id', $userId)->value('fcm_token')];
        });

        $actor = auth()->user();
        $action = $result['action'];
        $fcmToken = $result['fcm_token'] ?? null;

        if ($action === 'followed') {
            $this->broadcastFollowCounts($me, $userId);
            RealtimeService::toUser($userId, 'profile.new_follower', ['follower_id' => $me, 'follower_name' => $actor->name]);
            RealtimeService::toUser($userId, 'notification.new', ['type' => 'follow']);
            if ($fcmToken) {
                FcmService::sendToToken($fcmToken, 'New Follower', "{$actor->name} started following you",
                    ['type' => 'follow', 'user_id' => (string) $me, 'screen' => 'notifications']);
            }
        } elseif ($action === 'requested') {
            RealtimeService::toUser($userId, 'notification.new', ['type' => 'follow_request']);
            if ($fcmToken) {
                FcmService::sendToToken($fcmToken, 'Follow Request', "{$actor->name} wants to follow you",
                    ['type' => 'follow_request', 'user_id' => (string) $me, 'screen' => 'notifications']);
            }
        }

        return response()->json([
            'status'    => 'success',
            'action'    => $action,
            'following' => $action === 'followed',
            'requested' => $action === 'requested',
        ]);
    }

    // GET /community/follow-requests
    public function requests()
    {
        $me = auth()->id();
        $reqs = CommunityFollow::with('follower.communityProfile')
            ->where('following_id', $me)
            ->where('status', 'pending')
            ->latest()
            ->get();

        $feed = new CommunityFeedController();
        return response()->json([
            'status' => 'success',
            'data'   => $reqs->map(fn ($r) => [
                'id'   => $r->id,
                'user' => $feed->transformUser($r->follower, $me),
            ])->values(),
        ]);
    }

    // POST /community/follow-requests/{id}/accept  (id = follow row ID OR follower user_id)
    public function accept(int $id)
    {
        $me = auth()->id();
        $req = CommunityFollow::where('following_id', $me)->where('status', 'pending')
            ->where(fn ($q) => $q->where('id', $id)->orWhere('follower_id', $id))
            ->firstOrFail();

        DB::transaction(function () use ($req, $me) {
            $req->update(['status' => 'accepted']);
            CommunityProfile::firstOrCreate(['user_id' => $req->follower_id])->increment('following_count');
            CommunityProfile::firstOrCreate(['user_id' => $me])->increment('followers_count');
            // Delete the follow_request notification
            CommunityNotification::where('user_id', $me)->where('actor_id', $req->follower_id)->where('type', 'follow_request')->delete();
            // Notify the requester that their request was accepted
            CommunityNotification::create([
                'user_id' => $req->follower_id, 'actor_id' => $me,
                'type' => 'follow_request_accepted', 'notifiable_type' => 'user', 'notifiable_id' => $req->follower_id,
            ]);
        });

        $this->broadcastFollowCounts($req->follower_id, $me);
        $actor = auth()->user();
        RealtimeService::toUser($req->follower_id, 'notification.new', ['type' => 'follow_request_accepted']);
        $fcmToken = User::where('id', $req->follower_id)->value('fcm_token');
        if ($fcmToken) {
            FcmService::sendToToken($fcmToken, 'Follow Request Accepted', "{$actor->name} accepted your follow request",
                ['type' => 'follow_request_accepted', 'user_id' => (string) $me, 'screen' => 'notifications']);
        }

        return response()->json(['status' => 'success']);
    }

    // POST /community/follow-requests/{id}/reject  (id = follow row ID OR follower user_id)
    public function reject(int $id)
    {
        $me = auth()->id();
        $req = CommunityFollow::where('following_id', $me)->where('status', 'pending')
            ->where(fn ($q) => $q->where('id', $id)->orWhere('follower_id', $id))
            ->firstOrFail();
        $req->delete();
        CommunityNotification::where('user_id', $me)->where('actor_id', $req->follower_id)->where('type', 'follow_request')->delete();

        return response()->json(['status' => 'success']);
    }

    private function broadcastFollowCounts(int $followerId, int $followingId): void
    {
        $followerProfile = CommunityProfile::where('user_id', $followerId)->first();
        $followingProfile = CommunityProfile::where('user_id', $followingId)->first();
        RealtimeService::toPublic("community.profile.{$followerId}", 'profile.stats_changed', ['user_id' => $followerId, 'following_count' => $followerProfile?->following_count ?? 0]);
        RealtimeService::toPublic("community.profile.{$followingId}", 'profile.stats_changed', ['user_id' => $followingId, 'followers_count' => $followingProfile?->followers_count ?? 0]);
    }
}
