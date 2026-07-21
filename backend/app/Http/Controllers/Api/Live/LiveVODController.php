<?php
namespace App\Http\Controllers\Api\Live;

use App\Http\Controllers\Controller;
use App\Models\LiveRecording;
use App\Models\LiveRoom;
use Illuminate\Http\Request;

class LiveVODController extends Controller
{
    // ── GET /live/vod ─────────────────────────────────────────────────────────
    /** List available VOD recordings */
    public function index(Request $request)
    {
        $recordings = LiveRecording::with('room.host.communityProfile')
            ->where('status', 'ready')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn($r) => $this->transform($r));

        return response()->json(['status' => 'success', 'data' => $recordings]);
    }

    // ── GET /live/vod/host/{hostId} ───────────────────────────────────────────
    public function byHost(int $hostId)
    {
        $recordings = LiveRecording::with('room.host.communityProfile')
            ->whereHas('room', fn($q) => $q->where('host_id', $hostId))
            ->where('status', 'ready')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn($r) => $this->transform($r));

        return response()->json(['status' => 'success', 'data' => $recordings]);
    }

    // ── POST /live/vod/{id}/view ──────────────────────────────────────────────
    public function view(int $id)
    {
        LiveRecording::where('id', $id)->increment('view_count');
        return response()->json(['status' => 'success']);
    }

    // ── Internal: called by LiveRoomController when room ends ─────────────────
    public static function startRecording(int $roomId): void
    {
        // Create placeholder — actual URL filled when egress completes
        LiveRecording::updateOrCreate(
            ['room_id' => $roomId],
            ['status' => 'processing']
        );
    }

    public static function finishRecording(int $roomId, string $url, string $thumbnail, int $duration): void
    {
        LiveRecording::where('room_id', $roomId)->update([
            'recording_url'  => $url,
            'thumbnail_url'  => $thumbnail,
            'duration_seconds' => $duration,
            'status'         => 'ready',
        ]);
    }

    private function transform(LiveRecording $r): array
    {
        return [
            'id'               => $r->id,
            'room_id'          => $r->room_id,
            'title'            => $r->room?->title ?? '',
            'recording_url'    => $r->recording_url,
            'thumbnail_url'    => $r->thumbnail_url,
            'duration_seconds' => $r->duration_seconds,
            'view_count'       => $r->view_count,
            'host_name'        => $r->room?->host?->name ?? '',
            'host_avatar'      => $r->room?->host?->communityProfile?->avatar ?? '',
            'created_at'       => $r->created_at?->toIso8601String(),
        ];
    }
}
