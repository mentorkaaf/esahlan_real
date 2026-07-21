<?php
namespace App\Http\Controllers\Api\Live;

use App\Http\Controllers\Controller;
use App\Models\LiveRoom;
use App\Services\RealtimeService;
use Illuminate\Http\Request;

class LiveRaidController extends Controller
{
    // ── GET /live/rooms/{id}/raid/targets ─────────────────────────────────────
    /** List other live rooms to raid */
    public function targets(int $roomId)
    {
        LiveRoom::where('id', $roomId)->where('host_id', auth()->id())->firstOrFail();

        $targets = LiveRoom::with('host.communityProfile')
            ->where('status', 'live')
            ->where('id', '!=', $roomId)
            ->orderByDesc('viewer_count')
            ->limit(20)
            ->get()
            ->map(fn($r) => [
                'room_id'      => $r->id,
                'title'        => $r->title,
                'host_name'    => $r->host?->name ?? '',
                'host_avatar'  => $r->host?->communityProfile?->avatar ?? '',
                'viewer_count' => $r->viewer_count,
                'thumbnail'    => $r->thumbnail,
            ]);

        return response()->json(['status' => 'success', 'data' => $targets]);
    }

    // ── POST /live/rooms/{id}/raid ────────────────────────────────────────────
    /** Host sends viewers to another room */
    public function raid(Request $request, int $roomId)
    {
        $request->validate(['target_room_id' => 'required|integer']);

        $myRoom = LiveRoom::where('id', $roomId)
            ->where('host_id', auth()->id())
            ->where('status', 'live')
            ->with('host.communityProfile')
            ->firstOrFail();

        $targetRoom = LiveRoom::where('id', $request->target_room_id)
            ->where('status', 'live')
            ->firstOrFail();

        $me = auth()->user();
        $profile = $me?->communityProfile;

        // Tell my viewers: RAID! go to target room
        RealtimeService::toPublic("live.{$roomId}", 'live.raid_started', [
            'target_room_id'  => $targetRoom->id,
            'target_title'    => $targetRoom->title,
            'raider_count'    => $myRoom->viewer_count,
            'from_host_name'  => $me?->name ?? '',
        ]);

        // Tell the target host: incoming raid
        RealtimeService::toUser($targetRoom->host_id, 'live.incoming_raid', [
            'from_room_id'   => $roomId,
            'from_host_name' => $me?->name ?? '',
            'from_host_avatar' => $profile?->avatar ?? '',
            'raider_count'   => $myRoom->viewer_count,
        ]);

        return response()->json(['status' => 'success']);
    }
}
