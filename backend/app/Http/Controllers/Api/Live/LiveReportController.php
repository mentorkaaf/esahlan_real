<?php
namespace App\Http\Controllers\Api\Live;

use App\Http\Controllers\Controller;
use App\Models\LiveRoom;
use App\Models\LiveRoomReport;
use App\Services\FcmService;
use App\Services\RealtimeService;
use Illuminate\Http\Request;

class LiveReportController extends Controller
{
    private const AUTO_BAN_THRESHOLD = 5;   // unique reporters → auto force-end
    private const AUTO_BAN_HOSTS_AT  = 15;  // total reports → ban host from live

    /** POST /v1/live/rooms/{id}/report */
    public function report(Request $request, int $id)
    {
        $room = LiveRoom::where('id', $id)->where('status', 'live')->firstOrFail();
        $userId = auth()->id();

        $request->validate([
            'reason'      => 'required|in:spam,nudity,hate_speech,violence,other',
            'description' => 'nullable|string|max:500',
        ]);

        // One report per user per room
        $report = LiveRoomReport::firstOrCreate(
            ['live_room_id' => $id, 'reporter_id' => $userId],
            ['reason' => $request->reason, 'description' => $request->description]
        );

        if (!$report->wasRecentlyCreated) {
            return response()->json(['status' => 'error', 'message' => 'You already reported this stream'], 409);
        }

        $reportCount = LiveRoomReport::where('live_room_id', $id)->count();

        // Auto force-end if threshold reached
        if ($reportCount >= self::AUTO_BAN_THRESHOLD && $room->status === 'live') {
            $room->update(['status' => 'ended', 'ended_at' => now()]);
            RealtimeService::toPublic("live.{$id}", 'live.ended', [
                'room_id' => $id,
                'reason'  => 'removed_by_reports',
            ]);

            if ($room->host?->fcm_token) {
                FcmService::sendToToken(
                    $room->host->fcm_token,
                    'Your live was removed',
                    'Your live stream was removed due to multiple reports from viewers.',
                    ['type' => 'live_removed_reports', 'room_id' => (string) $id]
                );
            }
        }

        // Count total reports across all rooms for this host
        $hostTotalReports = LiveRoomReport::whereHas('room', fn($q) =>
            $q->where('host_id', $room->host_id)
        )->count();

        if ($hostTotalReports >= self::AUTO_BAN_HOSTS_AT && !$room->host?->banned_from_live) {
            $room->host?->update(['banned_from_live' => true]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Report submitted. Thank you for helping keep the community safe.',
        ]);
    }
}
