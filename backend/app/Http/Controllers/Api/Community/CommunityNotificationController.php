<?php
namespace App\Http\Controllers\Api\Community;
use App\Http\Controllers\Controller;
use App\Models\CommunityNotification;
use App\Models\CommunityFollow;
use Illuminate\Http\Request;

class CommunityNotificationController extends Controller
{
    public function index()
    {
        $userId = auth()->id();
        $notifs = CommunityNotification::with('actor.communityProfile')
            ->where('user_id', $userId)
            ->latest()
            ->paginate(30);

        $data = $notifs->map(fn($n) => $this->transform($n, $userId));

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'unread_count' => CommunityNotification::where('user_id', $userId)->where('is_read', false)->count(),
        ]);
    }

    public function markRead(int $id)
    {
        CommunityNotification::where('user_id', auth()->id())->findOrFail($id)->update(['is_read' => true]);
        return response()->json(['status' => 'success']);
    }

    public function markAllRead()
    {
        CommunityNotification::where('user_id', auth()->id())->update(['is_read' => true]);
        return response()->json(['status' => 'success']);
    }

    public function unreadCount()
    {
        $count = CommunityNotification::where('user_id', auth()->id())->where('is_read', false)->count();
        return response()->json(['status' => 'success', 'count' => $count]);
    }

    private function transform(CommunityNotification $n, int $myId): array
    {
        $actor = $n->actor;
        $profile = $actor?->communityProfile;
        return [
            'id' => $n->id,
            'type' => $n->type,
            'notifiable_type' => $n->notifiable_type,
            'notifiable_id' => $n->notifiable_id,
            'is_read' => $n->is_read,
            'created_at' => $n->created_at,
            'actor' => $actor ? [
                'id' => $actor->id,
                'name' => $actor->name,
                'username' => $profile?->username,
                'avatar' => $actor->avatar ?? $profile?->cover_photo,
                'bio' => $profile?->bio,
                'is_verified' => $profile?->is_verified ?? false,
                'is_business' => $profile?->is_business ?? false,
                'followers_count' => $profile?->followers_count ?? 0,
                'following_count' => $profile?->following_count ?? 0,
                'posts_count' => $profile?->posts_count ?? 0,
                'is_following' => CommunityFollow::where('follower_id', $myId)->where('following_id', $actor->id)->exists(),
                'is_me' => $actor->id === $myId,
            ] : null,
        ];
    }
}
