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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunityFollowController extends Controller
{
    public function toggle(int $userId)
    {
        $me = auth()->id();
        if ($me === $userId) return response()->json(['status'=>'error','message'=>'Cannot follow yourself'],422);

        // Check privacy: who_can_follow
        $check = PrivacyService::canFollow($me, $userId);
        if (!$check['allowed']) {
            return response()->json(['status'=>'error','message'=>$check['reason']],403);
        }

        $result = DB::transaction(function () use ($me, $userId) {
            $existing = CommunityFollow::where('follower_id', $me)->where('following_id', $userId)->first();
            if ($existing) {
                $existing->delete();
                CommunityProfile::where('user_id', $me)->decrement('following_count');
                CommunityProfile::where('user_id', $userId)->decrement('followers_count');
                return ['following' => false];
            }
            CommunityFollow::create(['follower_id' => $me, 'following_id' => $userId]);
            CommunityProfile::firstOrCreate(['user_id' => $me])->increment('following_count');
            CommunityProfile::firstOrCreate(['user_id' => $userId])->increment('followers_count');
            CommunityNotification::create(['user_id' => $userId, 'actor_id' => $me, 'type' => 'follow', 'notifiable_type' => 'user', 'notifiable_id' => $userId]);
            return ['following' => true, 'fcm_token' => User::where('id', $userId)->value('fcm_token')];
        });

        // Broadcasts fire after transaction commits
        $this->broadcastFollowCounts($me, $userId);
        if ($result['following']) {
            $actor = auth()->user();
            RealtimeService::toUser($userId, 'profile.new_follower', [
                'follower_id'   => $me,
                'follower_name' => $actor->name,
            ]);
            // Push notification to the person being followed
            $fcmToken = $result['fcm_token'] ?? null;
            if ($fcmToken) {
                FcmService::sendToToken(
                    $fcmToken,
                    'New Follower',
                    "{$actor->name} started following you",
                    ['type' => 'follow', 'user_id' => (string) $me, 'screen' => 'notifications']
                );
            }
        }

        return response()->json(['status' => 'success', 'following' => $result['following']]);
    }

    /** Public, non-sensitive follower/following counts — anyone viewing either profile needs these live. */
    private function broadcastFollowCounts(int $followerId, int $followingId): void
    {
        $followerProfile = CommunityProfile::where('user_id', $followerId)->first();
        $followingProfile = CommunityProfile::where('user_id', $followingId)->first();

        RealtimeService::toPublic("community.profile.{$followerId}", 'profile.stats_changed', [
            'user_id' => $followerId,
            'following_count' => $followerProfile?->following_count ?? 0,
        ]);
        RealtimeService::toPublic("community.profile.{$followingId}", 'profile.stats_changed', [
            'user_id' => $followingId,
            'followers_count' => $followingProfile?->followers_count ?? 0,
        ]);
    }
}