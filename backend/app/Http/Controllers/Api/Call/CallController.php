<?php
namespace App\Http\Controllers\Api\Call;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\User;
use App\Services\FcmService;
use App\Services\LiveKitService;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function __construct(private LiveKitService $liveKit) {}

    /** Initiate a new call */
    public function initiate(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|integer|exists:users,id',
            'type'        => 'required|in:audio,video',
        ]);

        $callerId   = auth()->id();
        $receiverId = $request->receiver_id;

        if ($callerId === $receiverId) {
            return response()->json(['status' => 'error', 'message' => 'Cannot call yourself'], 422);
        }

        // End any existing ringing call from this caller
        Call::where('caller_id', $callerId)
            ->where('status', 'ringing')
            ->update(['status' => 'missed', 'ended_at' => now()]);

        $roomName    = $this->liveKit->newRoomName('call');
        $call        = Call::create([
            'caller_id'   => $callerId,
            'receiver_id' => $receiverId,
            'type'        => $request->type,
            'status'      => 'ringing',
            'room_name'   => $roomName,
        ]);

        $caller   = User::with('communityProfile')->find($callerId);
        $receiver = User::with('communityProfile')->find($receiverId);

        // FCM push — data-only message so Flutter handles it as incoming call screen
        if ($receiver?->fcm_token) {
            FcmService::sendToToken(
                $receiver->fcm_token,
                'Incoming ' . ucfirst($request->type) . ' Call',
                $caller?->name ?? 'Someone is calling...',
                [
                    'type'          => 'incoming_call',
                    'call_id'       => (string) $call->id,
                    'room_name'     => $roomName,
                    'call_type'     => $request->type,
                    'caller_id'     => (string) $callerId,
                    'caller_name'   => $caller?->name ?? '',
                    'caller_avatar' => $caller?->communityProfile?->avatar ?? '',
                    'livekit_url'   => $this->liveKit->serverUrl(),
                ]
            );
        }

        $token = $this->liveKit->generateToken($roomName, "user_{$callerId}", [
            'canPublish' => true, 'canSubscribe' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'call'        => $this->transformCall($call->load('caller.communityProfile', 'receiver.communityProfile')),
                'token'       => $token,
                'livekit_url' => $this->liveKit->serverUrl(),
            ],
        ]);
    }

    /** Receiver accepts the call */
    public function accept(int $id)
    {
        $call = Call::where('id', $id)
            ->where('receiver_id', auth()->id())
            ->where('status', 'ringing')
            ->firstOrFail();

        $call->update(['status' => 'accepted', 'accepted_at' => now()]);

        $caller = User::find($call->caller_id);
        if ($caller?->fcm_token) {
            FcmService::sendToToken($caller->fcm_token, 'Call Accepted', '', [
                'type'    => 'call_accepted',
                'call_id' => (string) $call->id,
            ]);
        }

        $token = $this->liveKit->generateToken($call->room_name, "user_{$call->receiver_id}", [
            'canPublish' => true, 'canSubscribe' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'call'        => $this->transformCall($call->load('caller.communityProfile', 'receiver.communityProfile')),
                'token'       => $token,
                'livekit_url' => $this->liveKit->serverUrl(),
            ],
        ]);
    }

    /** Reject incoming call */
    public function reject(int $id)
    {
        $call = Call::where('id', $id)
            ->where('receiver_id', auth()->id())
            ->where('status', 'ringing')
            ->firstOrFail();

        $call->update(['status' => 'rejected', 'ended_at' => now()]);

        $caller = User::find($call->caller_id);
        if ($caller?->fcm_token) {
            FcmService::sendToToken($caller->fcm_token, 'Call Declined', '', [
                'type'    => 'call_rejected',
                'call_id' => (string) $call->id,
            ]);
        }

        return response()->json(['status' => 'success']);
    }

    /** End an active call (either party) */
    public function end(int $id)
    {
        $me   = auth()->id();
        $call = Call::where('id', $id)
            ->where(fn($q) => $q->where('caller_id', $me)->orWhere('receiver_id', $me))
            ->whereIn('status', ['ringing', 'accepted'])
            ->firstOrFail();

        $duration = $call->accepted_at ? now()->diffInSeconds($call->accepted_at) : 0;
        $call->update(['status' => 'ended', 'ended_at' => now(), 'duration' => $duration]);

        $otherId = $me === $call->caller_id ? $call->receiver_id : $call->caller_id;
        $other   = User::find($otherId);
        if ($other?->fcm_token) {
            FcmService::sendToToken($other->fcm_token, 'Call Ended', '', [
                'type'    => 'call_ended',
                'call_id' => (string) $call->id,
            ]);
        }

        return response()->json(['status' => 'success', 'data' => ['duration' => $duration]]);
    }

    /** Call history */
    public function history()
    {
        $me    = auth()->id();
        $calls = Call::with(['caller.communityProfile', 'receiver.communityProfile'])
            ->where(fn($q) => $q->where('caller_id', $me)->orWhere('receiver_id', $me))
            ->latest()
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data'   => $calls->map(fn($c) => $this->transformCall($c)),
        ]);
    }

    private function transformCall(Call $call): array
    {
        $me = auth()->id();
        return [
            'id'          => $call->id,
            'type'        => $call->type,
            'status'      => $call->status,
            'room_name'   => $call->room_name,
            'duration'    => $call->duration,
            'is_outgoing' => $call->caller_id === $me,
            'caller'      => $this->txUser($call->caller),
            'receiver'    => $this->txUser($call->receiver),
            'created_at'  => $call->created_at,
        ];
    }

    private function txUser(?User $user): ?array
    {
        if (!$user) return null;
        $p = $user->communityProfile;
        return [
            'id'       => $user->id,
            'name'     => $user->name,
            'username' => $p?->username ?? '',
            'avatar'   => $p?->avatar ?? '',
        ];
    }
}
