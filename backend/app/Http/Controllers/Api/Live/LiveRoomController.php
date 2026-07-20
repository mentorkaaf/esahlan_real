<?php
namespace App\Http\Controllers\Api\Live;

use App\Http\Controllers\Controller;
use App\Jobs\SendLiveNotificationJob;
use App\Models\GiftTransaction;
use App\Models\LiveChatMute;
use App\Models\LiveRoom;
use App\Models\LiveRoomMessage;
use App\Models\LiveRoomSetting;
use App\Models\LiveRoomViewer;
use App\Services\LiveKitService;
use App\Services\RealtimeService;
use Illuminate\Http\Request;

class LiveRoomController extends Controller
{
    public function __construct(private LiveKitService $liveKit) {}

    /** List active live rooms */
    public function index()
    {
        $rooms = LiveRoom::with('host.communityProfile')
            ->where('status', 'live')
            ->orderByDesc('viewer_count')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data'   => $rooms->map(fn($r) => $this->transformRoom($r)),
        ]);
    }

    /** Start a new live room */
    public function create(Request $request)
    {
        $request->validate([
            'title'     => 'required|string|max:120',
            'thumbnail' => 'nullable|string',
            'category'  => 'nullable|string|max:40',
            'tags'      => 'nullable|array',
            'tags.*'    => 'string|max:30',
        ]);

        $hostId = auth()->id();
        $user   = auth()->user();

        if ($user?->banned_from_live) {
            return response()->json(['status' => 'error', 'message' => 'Your live streaming access has been restricted.'], 403);
        }

        // End any previous live room by this host
        LiveRoom::where('host_id', $hostId)
            ->where('status', 'live')
            ->update(['status' => 'ended', 'ended_at' => now()]);

        $roomName = $this->liveKit->newRoomName('live');

        $room = LiveRoom::create([
            'host_id'   => $hostId,
            'title'     => $request->title,
            'room_name' => $roomName,
            'thumbnail' => $request->thumbnail,
            'category'  => $request->input('category', 'general'),
            'tags'      => $request->input('tags', []),
        ]);

        $token = $this->liveKit->generateToken($roomName, "host_{$hostId}", [
            'roomCreate' => true,
            'canPublish' => true,
            'canSubscribe' => true,
        ]);

        // Notify followers in background
        SendLiveNotificationJob::dispatch($room->id)->onQueue('default');

        return response()->json([
            'status' => 'success',
            'data'   => [
                'room'       => $this->transformRoom($room->load('host.communityProfile')),
                'token'      => $token,
                'livekit_url' => $this->liveKit->serverUrl(),
            ],
        ]);
    }

    /** List past (ended) live rooms */
    public function past()
    {
        $rooms = LiveRoom::with('host.communityProfile')
            ->where('status', 'ended')
            ->whereNotNull('ended_at')
            ->orderByDesc('ended_at')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data'   => $rooms->map(fn($r) => $this->transformRoom($r, withStats: true)),
        ]);
    }

    /** Viewer joins a room */
    public function join(int $id)
    {
        $room = LiveRoom::where('id', $id)->where('status', 'live')->firstOrFail();
        $userId = auth()->id();

        LiveRoomViewer::updateOrCreate(
            ['live_room_id' => $id, 'user_id' => $userId],
            ['joined_at' => now(), 'left_at' => null]
        );

        $count = LiveRoomViewer::where('live_room_id', $id)->whereNull('left_at')->count();
        $room->update([
            'viewer_count' => $count,
            'peak_viewers' => max($room->peak_viewers, $count),
        ]);

        // Broadcast viewer joined
        RealtimeService::toPublic("live.{$id}", 'viewer.joined', [
            'user_id' => $userId,
            'viewer_count' => $count,
        ]);

        $token = $this->liveKit->generateToken($room->room_name, "viewer_{$userId}", [
            'canPublish'   => false,
            'canSubscribe' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'user_id'     => $userId,
                'room'        => $this->transformRoom($room),
                'token'       => $token,
                'livekit_url' => $this->liveKit->serverUrl(),
            ],
        ]);
    }

    /** Viewer leaves or host ends */
    public function leave(int $id)
    {
        $userId = auth()->id();
        $room   = LiveRoom::findOrFail($id);

        LiveRoomViewer::where('live_room_id', $id)
            ->where('user_id', $userId)
            ->update(['left_at' => now()]);

        $count = LiveRoomViewer::where('live_room_id', $id)->whereNull('left_at')->count();
        $room->update(['viewer_count' => $count]);

        RealtimeService::toPublic("live.{$id}", 'viewer.left', [
            'user_id'      => $userId,
            'viewer_count' => $count,
        ]);

        return response()->json(['status' => 'success']);
    }

    /** Send a chat message in a live room */
    public function message(Request $request, int $id)
    {
        $room = LiveRoom::where('id', $id)->where('status', 'live')->firstOrFail();
        $user = auth()->user();

        // Moderation checks
        $settings = LiveRoomSetting::forRoom($id);

        if ($settings->comments_disabled && $room->host_id !== $user->id) {
            return response()->json(['status' => 'error', 'message' => 'Comments are disabled'], 403);
        }

        // Chat-muted check
        $mute = LiveChatMute::where('live_room_id', $id)->where('user_id', $user->id)->first();
        if ($mute && $mute->isActive()) {
            return response()->json(['status' => 'error', 'message' => 'You are muted in this stream'], 403);
        }

        $request->validate(['message' => 'required|string|max:300']);

        // Blocked words filter
        $msg = $request->message;
        if (!empty($settings->blocked_words)) {
            foreach ($settings->blocked_words as $word) {
                $msg = str_ireplace($word, str_repeat('*', strlen($word)), $msg);
            }
        }

        $p    = $user->communityProfile;

        $msg = LiveRoomMessage::create([
            'live_room_id' => $id,
            'user_id'      => $user->id,
            'message'      => $msg,
        ]);

        RealtimeService::toPublic("live.{$id}", 'live.chat', [
            'id'         => $msg->id,
            'user_id'    => $user->id,
            'username'   => $p?->username ?? $user->name,
            'avatar'     => $p?->avatar ?? '',
            'message'    => $msg->message,
            'is_host'    => $room->host_id === $user->id,
            'created_at' => $msg->created_at->toISOString(),
        ]);

        return response()->json(['status' => 'success']);
    }

    /** Host ends the live room */
    public function end(int $id)
    {
        $room = LiveRoom::where('id', $id)
            ->where('host_id', auth()->id())
            ->where('status', 'live')
            ->firstOrFail();

        $room->update(['status' => 'ended', 'ended_at' => now()]);

        RealtimeService::toPublic("live.{$id}", 'live.ended', [
            'room_id' => $id,
        ]);

        return response()->json(['status' => 'success']);
    }

    private function transformRoom(LiveRoom $room, bool $withStats = false): array
    {
        $host = $room->host;
        $p    = $host?->communityProfile;
        $data = [
            'id'           => $room->id,
            'title'        => $room->title,
            'room_name'    => $room->room_name,
            'thumbnail'    => $room->thumbnail,
            'category'     => $room->category ?? 'general',
            'tags'         => $room->tags ?? [],
            'status'       => $room->status,
            'viewer_count' => $room->viewer_count,
            'peak_viewers' => $room->peak_viewers,
            'host' => [
                'id'       => $host?->id,
                'name'     => $host?->name,
                'username' => $p?->username ?? '',
                'avatar'   => $p?->avatar ?? '',
            ],
            'created_at' => $room->created_at,
            'ended_at'   => $room->ended_at,
        ];

        if ($withStats && $room->ended_at && $room->created_at) {
            $data['duration_seconds'] = (int) $room->created_at->diffInSeconds($room->ended_at);
            $data['total_gifts']      = GiftTransaction::where('live_room_id', $room->id)->sum('coins_spent');
        }

        return $data;
    }
}
