<?php
namespace App\Http\Controllers\Api\Live;

use App\Http\Controllers\Controller;
use App\Jobs\EndBattleJob;
use App\Models\LiveBattle;
use App\Models\LiveBattleInvite;
use App\Models\LiveBattleParticipant;
use App\Models\LiveRoom;
use App\Services\LiveKitService;
use App\Services\RealtimeService;
use Illuminate\Http\Request;

class LiveBattleController extends Controller
{
    public function __construct(private LiveKitService $liveKit) {}

    // ── GET /live/rooms/{roomId}/battle/hosts ─────────────────────────────────
    /** List other live hosts available to invite */
    public function availableHosts(int $roomId)
    {
        LiveRoom::where('id', $roomId)->where('host_id', auth()->id())->firstOrFail();

        $hosts = LiveRoom::with('host.communityProfile')
            ->where('status', 'live')
            ->where('id', '!=', $roomId)
            ->whereDoesntHave('battleParticipants', fn($q) => $q->whereHas('battle', fn($bq) => $bq->where('status', 'active')))
            ->orderByDesc('viewer_count')
            ->limit(20)
            ->get()
            ->map(fn($r) => [
                'room_id'      => $r->id,
                'host_id'      => $r->host_id,
                'host_name'    => $r->host?->name ?? '',
                'host_username' => $r->host?->communityProfile?->username ?? '',
                'host_avatar'  => $r->host?->communityProfile?->avatar ?? '',
                'viewer_count' => $r->viewer_count,
            ]);

        return response()->json(['status' => 'success', 'data' => $hosts]);
    }

    // ── POST /live/rooms/{roomId}/battle/invite ───────────────────────────────
    /** Host A invites Host B to PK Battle */
    public function invite(Request $request, int $roomId)
    {
        $request->validate(['to_room_id' => 'required|integer']);

        $myRoom = LiveRoom::where('id', $roomId)
            ->where('host_id', auth()->id())
            ->where('status', 'live')
            ->firstOrFail();

        $targetRoom = LiveRoom::where('id', $request->to_room_id)
            ->where('status', 'live')
            ->with('host.communityProfile')
            ->firstOrFail();

        if ($targetRoom->host_id === auth()->id()) {
            return response()->json(['status' => 'error', 'message' => 'Cannot invite yourself'], 422);
        }

        // Check no existing active battle
        if (LiveBattle::activeForRoom($roomId)) {
            return response()->json(['status' => 'error', 'message' => 'Already in a battle'], 422);
        }

        // Expire old pending invites from this host
        LiveBattleInvite::where('from_host_id', auth()->id())
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        $invite = LiveBattleInvite::create([
            'from_host_id' => auth()->id(),
            'to_host_id'   => $targetRoom->host_id,
            'from_room_id' => $roomId,
            'status'       => 'pending',
        ]);

        $me = auth()->user();
        $myProfile = $me->communityProfile;

        // Notify the target host via their private channel
        RealtimeService::toUser($targetRoom->host_id, 'live.battle_invite', [
            'invite_id'    => $invite->id,
            'from_room_id' => $roomId,
            'from_host_id' => auth()->id(),
            'from_name'    => $me->name,
            'from_avatar'  => $myProfile?->avatar ?? '',
            'from_username' => $myProfile?->username ?? '',
            'expires_in'   => 30, // seconds
        ]);

        return response()->json(['status' => 'success', 'data' => ['invite_id' => $invite->id]]);
    }

    // ── POST /live/battle/accept/{inviteId} ───────────────────────────────────
    /** Host B accepts the battle invite */
    public function accept(int $inviteId)
    {
        $invite = LiveBattleInvite::where('id', $inviteId)
            ->where('to_host_id', auth()->id())
            ->where('status', 'pending')
            ->with('fromRoom')
            ->firstOrFail();

        // Find Host B's active room
        $myRoom = LiveRoom::where('host_id', auth()->id())
            ->where('status', 'live')
            ->firstOrFail();

        $invite->update(['status' => 'accepted']);

        // Create shared LiveKit battle room
        $battleRoomName = $this->liveKit->newRoomName('battle');
        $duration = 180; // 3 minutes
        $endsAt = now()->addSeconds($duration);

        $battle = LiveBattle::create([
            'battle_room_name' => $battleRoomName,
            'status'           => 'active',
            'duration_seconds' => $duration,
            'ends_at'          => $endsAt,
            'invite_id'        => $invite->id,
        ]);

        // Create participants
        LiveBattleParticipant::create([
            'battle_id'   => $battle->id,
            'host_id'     => $invite->from_host_id,
            'live_room_id' => $invite->from_room_id,
            'score'       => 0,
            'rank'        => 0,
        ]);
        LiveBattleParticipant::create([
            'battle_id'   => $battle->id,
            'host_id'     => auth()->id(),
            'live_room_id' => $myRoom->id,
            'score'       => 0,
            'rank'        => 0,
        ]);

        // Generate host tokens for the battle room
        $tokenA = $this->liveKit->generateToken($battleRoomName, "host_{$invite->from_host_id}", [
            'canPublish' => true, 'canSubscribe' => true,
        ]);
        $myId   = auth()->id();
        $tokenB = $this->liveKit->generateToken($battleRoomName, "host_{$myId}", [
            'canPublish' => true, 'canSubscribe' => true,
        ]);

        // Generate a generic viewer token template
        $viewerToken = $this->liveKit->generateToken($battleRoomName, 'viewer_battle', [
            'canPublish' => false, 'canSubscribe' => true,
        ]);

        $battleData = $this->transformBattle($battle->fresh(['participants.host.communityProfile']));

        // Notify host A (via private channel) — with their new battle room token
        RealtimeService::toUser($invite->from_host_id, 'live.battle_accepted', [
            'battle'       => $battleData,
            'livekit_url'  => $this->liveKit->serverUrl(),
            'host_token'   => $tokenA,
            'viewer_token' => $viewerToken,
        ]);

        // Broadcast to host A's room viewers
        RealtimeService::toPublic("live.{$invite->from_room_id}", 'live.battle_started', [
            'battle'       => $battleData,
            'livekit_url'  => $this->liveKit->serverUrl(),
            'viewer_token' => $viewerToken,
        ]);

        // Broadcast to host B's room viewers
        RealtimeService::toPublic("live.{$myRoom->id}", 'live.battle_started', [
            'battle'       => $battleData,
            'livekit_url'  => $this->liveKit->serverUrl(),
            'viewer_token' => $viewerToken,
        ]);

        // Schedule auto-end
        EndBattleJob::dispatch($battle->id)->delay($endsAt);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'battle'      => $battleData,
                'livekit_url' => $this->liveKit->serverUrl(),
                'host_token'  => $tokenB,
            ],
        ]);
    }

    // ── POST /live/battle/reject/{inviteId} ───────────────────────────────────
    public function reject(int $inviteId)
    {
        $invite = LiveBattleInvite::where('id', $inviteId)
            ->where('to_host_id', auth()->id())
            ->firstOrFail();

        $invite->update(['status' => 'rejected']);

        RealtimeService::toUser($invite->from_host_id, 'live.battle_rejected', [
            'invite_id' => $inviteId,
        ]);

        return response()->json(['status' => 'success']);
    }

    // ── GET /live/battle/{battleId}/viewer-token ──────────────────────────────
    /** Viewer gets their personal token to join the shared battle LiveKit room */
    public function viewerToken(int $battleId)
    {
        $battle = LiveBattle::where('id', $battleId)->where('status', 'active')->firstOrFail();
        $userId = auth()->id();

        $token = $this->liveKit->generateToken($battle->battle_room_name, "viewer_{$userId}", [
            'canPublish' => false, 'canSubscribe' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => ['token' => $token, 'livekit_url' => $this->liveKit->serverUrl()],
        ]);
    }

    // ── GET /live/rooms/{roomId}/battle/status ────────────────────────────────
    public function status(int $roomId)
    {
        $battle = LiveBattle::activeForRoom($roomId);
        if (!$battle) {
            return response()->json(['status' => 'success', 'data' => null]);
        }
        return response()->json(['status' => 'success', 'data' => $this->transformBattle($battle)]);
    }

    // ── POST /live/battle/{battleId}/end ──────────────────────────────────────
    /** Host manually ends the battle early */
    public function end(int $battleId)
    {
        $battle = LiveBattle::where('id', $battleId)->where('status', 'active')
            ->whereHas('participants', fn($q) => $q->where('host_id', auth()->id()))
            ->firstOrFail();

        EndBattleJob::dispatch($battle->id);

        return response()->json(['status' => 'success']);
    }

    // ── Transform helpers ─────────────────────────────────────────────────────
    private function transformBattle(LiveBattle $battle): array
    {
        $participants = $battle->participants->sortBy('rank')->values()->map(fn($p) => [
            'host_id'      => $p->host_id,
            'live_room_id' => $p->live_room_id,
            'name'         => $p->host?->name ?? '',
            'username'     => $p->host?->communityProfile?->username ?? '',
            'avatar'       => $p->host?->communityProfile?->avatar ?? '',
            'score'        => $p->score,
            'rank'         => $p->rank,
        ]);

        return [
            'id'               => $battle->id,
            'status'           => $battle->status,
            'duration_seconds' => $battle->duration_seconds,
            'ends_at'          => $battle->ends_at?->toIso8601String(),
            'winner_host_id'   => $battle->winner_host_id,
            'participants'     => $participants,
        ];
    }
}
