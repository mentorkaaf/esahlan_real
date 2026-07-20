<?php
namespace App\Http\Controllers\Api\Live;

use App\Http\Controllers\Controller;
use App\Models\LiveGuestRequest;
use App\Models\LiveRoom;
use App\Models\LiveRoomGuest;
use App\Services\LiveKitService;
use App\Services\RealtimeService;
use Illuminate\Http\Request;

class LiveGuestController extends Controller
{
    public function __construct(private LiveKitService $liveKit) {}

    /** POST /live/rooms/{id}/guest/request — viewer sends join request */
    public function request(int $id)
    {
        $room = LiveRoom::where('id', $id)->where('status', 'live')->firstOrFail();
        $userId = auth()->id();

        if ($room->host_id === $userId) {
            return response()->json(['status' => 'error', 'message' => 'Host cannot request to join'], 422);
        }

        // Max 6 active guests
        $activeCount = LiveRoomGuest::where('live_room_id', $id)->where('status', 'active')->count();
        if ($activeCount >= 6) {
            return response()->json(['status' => 'error', 'message' => 'Stage is full'], 422);
        }

        $req = LiveGuestRequest::updateOrCreate(
            ['live_room_id' => $id, 'user_id' => $userId],
            ['status' => 'pending']
        );

        $user = auth()->user();
        $profile = $user->communityProfile;

        // Notify host via Reverb
        RealtimeService::toUser($room->host_id, 'live.guest_request', [
            'request_id' => $req->id,
            'room_id'    => $id,
            'user_id'    => $userId,
            'name'       => $user->name,
            'username'   => $profile?->username ?? $user->name,
            'avatar'     => $profile?->avatar ?? '',
        ]);

        return response()->json(['status' => 'success', 'data' => ['request_id' => $req->id]]);
    }

    /** POST /live/rooms/{id}/guest/accept/{requestId} — host accepts */
    public function accept(int $id, int $requestId)
    {
        $room = LiveRoom::where('id', $id)
            ->where('host_id', auth()->id())
            ->where('status', 'live')
            ->firstOrFail();

        $req = LiveGuestRequest::where('id', $requestId)
            ->where('live_room_id', $id)
            ->where('status', 'pending')
            ->firstOrFail();

        $req->update(['status' => 'accepted']);

        // Add to active guests
        LiveRoomGuest::updateOrCreate(
            ['live_room_id' => $id, 'user_id' => $req->user_id],
            ['status' => 'active', 'joined_at' => now(), 'left_at' => null]
        );

        // Generate LiveKit token for the guest
        $token = $this->liveKit->generateToken($room->room_name, "guest_{$req->user_id}", [
            'canPublish'   => true,
            'canSubscribe' => true,
        ]);

        // Notify the guest
        RealtimeService::toUser($req->user_id, 'live.guest_accepted', [
            'room_id'     => $id,
            'room_name'   => $room->room_name,
            'token'       => $token,
            'livekit_url' => $this->liveKit->serverUrl(),
        ]);

        // Broadcast updated guest list to room
        $this->broadcastGuestList($id);

        return response()->json(['status' => 'success']);
    }

    /** POST /live/rooms/{id}/guest/reject/{requestId} — host rejects */
    public function reject(int $id, int $requestId)
    {
        LiveRoom::where('id', $id)->where('host_id', auth()->id())->firstOrFail();

        $req = LiveGuestRequest::where('id', $requestId)
            ->where('live_room_id', $id)
            ->firstOrFail();

        $req->update(['status' => 'rejected']);

        RealtimeService::toUser($req->user_id, 'live.guest_rejected', ['room_id' => $id]);

        return response()->json(['status' => 'success']);
    }

    /** POST /live/rooms/{id}/guest/{userId}/remove — host removes guest */
    public function remove(int $id, int $userId)
    {
        LiveRoom::where('id', $id)->where('host_id', auth()->id())->firstOrFail();

        LiveRoomGuest::where('live_room_id', $id)
            ->where('user_id', $userId)
            ->update(['status' => 'removed', 'left_at' => now()]);

        RealtimeService::toUser($userId, 'live.guest_removed', ['room_id' => $id]);
        $this->broadcastGuestList($id);

        return response()->json(['status' => 'success']);
    }

    /** POST /live/rooms/{id}/guest/{userId}/mute — host mutes/unmutes guest */
    public function mute(Request $request, int $id, int $userId)
    {
        LiveRoom::where('id', $id)->where('host_id', auth()->id())->firstOrFail();

        $muted = $request->boolean('muted', true);

        LiveRoomGuest::where('live_room_id', $id)
            ->where('user_id', $userId)
            ->update(['is_muted' => $muted]);

        RealtimeService::toUser($userId, 'live.guest_muted', ['room_id' => $id, 'muted' => $muted]);
        $this->broadcastGuestList($id);

        return response()->json(['status' => 'success']);
    }

    /** POST /live/rooms/{id}/guest/cancel — viewer cancels own request */
    public function cancel(int $id)
    {
        LiveGuestRequest::where('live_room_id', $id)
            ->where('user_id', auth()->id())
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        return response()->json(['status' => 'success']);
    }

    /** GET /live/rooms/{id}/guests — list active guests */
    public function index(int $id)
    {
        $guests = LiveRoomGuest::with('user.communityProfile')
            ->where('live_room_id', $id)
            ->where('status', 'active')
            ->get()
            ->map(fn($g) => $this->transformGuest($g));

        return response()->json(['status' => 'success', 'data' => $guests]);
    }

    private function broadcastGuestList(int $roomId): void
    {
        $guests = LiveRoomGuest::with('user.communityProfile')
            ->where('live_room_id', $roomId)
            ->where('status', 'active')
            ->get()
            ->map(fn($g) => $this->transformGuest($g));

        RealtimeService::toPublic("live.{$roomId}", 'live.guests_updated', ['guests' => $guests]);
    }

    private function transformGuest(LiveRoomGuest $g): array
    {
        $user = $g->user;
        $profile = $user?->communityProfile;
        return [
            'user_id'        => $g->user_id,
            'name'           => $user?->name ?? '',
            'username'       => $profile?->username ?? '',
            'avatar'         => $profile?->avatar ?? '',
            'is_muted'       => $g->is_muted,
            'camera_disabled' => $g->camera_disabled,
        ];
    }
}
