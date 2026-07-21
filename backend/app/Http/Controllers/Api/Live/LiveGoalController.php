<?php
namespace App\Http\Controllers\Api\Live;

use App\Http\Controllers\Controller;
use App\Models\LiveGoal;
use App\Models\LiveRoom;
use App\Services\RealtimeService;
use Illuminate\Http\Request;

class LiveGoalController extends Controller
{
    // ── GET /live/rooms/{id}/goal ─────────────────────────────────────────────
    public function get(int $roomId)
    {
        $goal = LiveGoal::where('room_id', $roomId)->where('status', 'active')->latest()->first();
        return response()->json(['status' => 'success', 'data' => $goal?->toArray()]);
    }

    // ── POST /live/rooms/{id}/goal ────────────────────────────────────────────
    public function set(Request $request, int $roomId)
    {
        LiveRoom::where('id', $roomId)->where('host_id', auth()->id())->firstOrFail();

        $request->validate([
            'type'   => 'required|in:coins,gifts,likes,followers',
            'title'  => 'required|string|max:80',
            'target' => 'required|integer|min:1',
        ]);

        // Cancel any previous active goal
        LiveGoal::where('room_id', $roomId)->where('status', 'active')
            ->update(['status' => 'cancelled']);

        $goal = LiveGoal::create([
            'room_id' => $roomId,
            'type'    => $request->type,
            'title'   => $request->title,
            'target'  => $request->target,
            'current' => 0,
            'status'  => 'active',
        ]);

        RealtimeService::toPublic("live.{$roomId}", 'goal.updated', $goal->toArray());

        return response()->json(['status' => 'success', 'data' => $goal->toArray()]);
    }

    // ── DELETE /live/rooms/{id}/goal ──────────────────────────────────────────
    public function cancel(int $roomId)
    {
        LiveRoom::where('id', $roomId)->where('host_id', auth()->id())->firstOrFail();
        LiveGoal::where('room_id', $roomId)->where('status', 'active')
            ->update(['status' => 'cancelled']);
        RealtimeService::toPublic("live.{$roomId}", 'goal.cancelled', []);
        return response()->json(['status' => 'success']);
    }

    // ── Internal: called by GiftController / LikeController ──────────────────
    public static function increment(int $roomId, string $type, int $amount = 1): void
    {
        $goal = LiveGoal::where('room_id', $roomId)
            ->where('type', $type)
            ->where('status', 'active')
            ->first();

        if (!$goal) return;

        $goal->increment('current', $amount);
        $goal->refresh();

        if ($goal->current >= $goal->target) {
            $goal->update(['status' => 'completed']);
            RealtimeService::toPublic("live.{$roomId}", 'goal.completed', $goal->toArray());
        } else {
            RealtimeService::toPublic("live.{$roomId}", 'goal.updated', $goal->toArray());
        }
    }
}
