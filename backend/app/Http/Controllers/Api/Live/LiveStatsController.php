<?php
namespace App\Http\Controllers\Api\Live;

use App\Http\Controllers\Controller;
use App\Models\CommunityFollow;
use App\Models\GiftTransaction;
use App\Models\LiveRoom;
use App\Models\LiveRoomLike;
use App\Models\LiveRoomMessage;
use App\Models\LiveRoomSetting;
use App\Services\RealtimeService;
use Illuminate\Http\Request;

class LiveStatsController extends Controller
{
    /** GET /live/rooms/{id}/stats — host real-time dashboard */
    public function stats(int $id)
    {
        $room = LiveRoom::where('id', $id)
            ->where('host_id', auth()->id())
            ->firstOrFail();

        $settings = LiveRoomSetting::forRoom($id);

        $coinsEarned = GiftTransaction::where('live_room_id', $id)->sum('coins_spent');
        $giftCount   = GiftTransaction::where('live_room_id', $id)->sum('quantity');
        $messageCount = LiveRoomMessage::where('live_room_id', $id)->count();
        $likeCount   = LiveRoomLike::where('live_room_id', $id)->count();

        // New followers gained since stream started
        $newFollowers = CommunityFollow::where('following_id', auth()->id())
            ->where('created_at', '>=', $room->created_at)
            ->count();

        $durationSeconds = (int) $room->created_at->diffInSeconds(now());

        return response()->json([
            'status' => 'success',
            'data'   => [
                'room_id'          => $id,
                'viewer_count'     => $room->viewer_count,
                'peak_viewers'     => $room->peak_viewers,
                'coins_earned'     => (int) $coinsEarned,
                'gifts_received'   => (int) $giftCount,
                'likes'            => $likeCount,
                'messages'         => $messageCount,
                'new_followers'    => $newFollowers,
                'duration_seconds' => $durationSeconds,
                'settings'         => [
                    'slow_mode'         => $settings->slow_mode,
                    'slow_mode_seconds' => $settings->slow_mode_seconds,
                    'followers_only'    => $settings->followers_only,
                    'comments_disabled' => $settings->comments_disabled,
                ],
            ],
        ]);
    }

    /** POST /live/rooms/{id}/like — viewer likes the stream */
    public function like(int $id)
    {
        $room = LiveRoom::where('id', $id)->where('status', 'live')->firstOrFail();

        $like = LiveRoomLike::firstOrCreate([
            'live_room_id' => $id,
            'user_id'      => auth()->id(),
        ]);

        $total = LiveRoomLike::where('live_room_id', $id)->count();

        // Broadcast like event so host dashboard updates in real-time
        RealtimeService::toPublic("live.{$id}", 'live.liked', [
            'total_likes' => $total,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => ['total_likes' => $total, 'already_liked' => !$like->wasRecentlyCreated],
        ]);
    }
}
