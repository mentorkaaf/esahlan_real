<?php
namespace App\Http\Controllers\Api\Live;

use App\Http\Controllers\Controller;
use App\Models\LiveQuestion;
use App\Models\LiveRoom;
use App\Services\RealtimeService;
use Illuminate\Http\Request;

class LiveQAController extends Controller
{
    // ── POST /live/rooms/{id}/questions ───────────────────────────────────────
    public function submit(Request $request, int $roomId)
    {
        LiveRoom::where('id', $roomId)->where('status', 'live')->firstOrFail();
        $request->validate(['question' => 'required|string|max:300']);

        $user    = auth()->user();
        $profile = $user?->communityProfile;

        $q = LiveQuestion::create([
            'room_id'  => $roomId,
            'user_id'  => auth()->id(),
            'username' => $profile?->username ?? $user?->name ?? '',
            'avatar'   => $profile?->avatar ?? '',
            'question' => $request->question,
            'status'   => 'pending',
        ]);

        // Notify host via private channel
        $room = LiveRoom::find($roomId);
        RealtimeService::toUser($room->host_id, 'live.new_question', [
            'id'       => $q->id,
            'username' => $q->username,
            'avatar'   => $q->avatar,
            'question' => $q->question,
        ]);

        return response()->json(['status' => 'success', 'data' => ['id' => $q->id]]);
    }

    // ── GET /live/rooms/{id}/questions ────────────────────────────────────────
    /** Host views pending questions */
    public function list(int $roomId)
    {
        LiveRoom::where('id', $roomId)->where('host_id', auth()->id())->firstOrFail();

        $questions = LiveQuestion::where('room_id', $roomId)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit(50)
            ->get()
            ->map(fn($q) => [
                'id'       => $q->id,
                'username' => $q->username,
                'avatar'   => $q->avatar,
                'question' => $q->question,
            ]);

        return response()->json(['status' => 'success', 'data' => $questions]);
    }

    // ── POST /live/rooms/{id}/questions/{qId}/activate ────────────────────────
    /** Host picks a question to display to all viewers */
    public function activate(int $roomId, int $qId)
    {
        LiveRoom::where('id', $roomId)->where('host_id', auth()->id())->firstOrFail();

        // Deactivate any currently active question
        LiveQuestion::where('room_id', $roomId)->where('status', 'active')
            ->update(['status' => 'answered']);

        $q = LiveQuestion::where('id', $qId)->where('room_id', $roomId)->firstOrFail();
        $q->update(['status' => 'active']);

        // Broadcast to all viewers
        RealtimeService::toPublic("live.{$roomId}", 'qa.question_active', [
            'id'       => $q->id,
            'username' => $q->username,
            'avatar'   => $q->avatar,
            'question' => $q->question,
        ]);

        return response()->json(['status' => 'success']);
    }

    // ── POST /live/rooms/{id}/questions/{qId}/dismiss ─────────────────────────
    public function dismiss(int $roomId, int $qId)
    {
        LiveRoom::where('id', $roomId)->where('host_id', auth()->id())->firstOrFail();
        LiveQuestion::where('id', $qId)->where('room_id', $roomId)
            ->update(['status' => 'dismissed']);
        RealtimeService::toPublic("live.{$roomId}", 'qa.question_dismissed', ['id' => $qId]);
        return response()->json(['status' => 'success']);
    }
}
