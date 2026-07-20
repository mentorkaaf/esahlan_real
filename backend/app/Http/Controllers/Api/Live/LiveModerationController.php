<?php
namespace App\Http\Controllers\Api\Live;

use App\Http\Controllers\Controller;
use App\Models\LiveChatMute;
use App\Models\LiveChatPin;
use App\Models\LiveRoom;
use App\Models\LiveRoomMessage;
use App\Models\LiveRoomSetting;
use App\Services\RealtimeService;
use Illuminate\Http\Request;

class LiveModerationController extends Controller
{
    /** GET /live/rooms/{id}/settings */
    public function settings(int $id)
    {
        $settings = LiveRoomSetting::forRoom($id);
        return response()->json(['status' => 'success', 'data' => $this->transformSettings($settings)]);
    }

    /** PATCH /live/rooms/{id}/settings — host updates moderation settings */
    public function updateSettings(Request $request, int $id)
    {
        $room = LiveRoom::where('id', $id)->where('host_id', auth()->id())->firstOrFail();

        $data = $request->validate([
            'slow_mode'          => 'nullable|boolean',
            'slow_mode_seconds'  => 'nullable|integer|min:5|max:300',
            'followers_only'     => 'nullable|boolean',
            'min_follow_seconds' => 'nullable|integer|min:0',
            'subscribers_only'   => 'nullable|boolean',
            'comments_disabled'  => 'nullable|boolean',
            'blocked_words'      => 'nullable|array',
            'blocked_words.*'    => 'string|max:50',
        ]);

        $settings = LiveRoomSetting::forRoom($id);
        $settings->update(array_filter($data, fn($v) => $v !== null));

        // Broadcast updated settings to all clients
        RealtimeService::toPublic("live.{$id}", 'live.settings_updated', $this->transformSettings($settings));

        return response()->json(['status' => 'success', 'data' => $this->transformSettings($settings)]);
    }

    /** POST /live/rooms/{id}/chat/{userId}/mute — host mutes user from chat */
    public function muteUser(Request $request, int $id, int $userId)
    {
        LiveRoom::where('id', $id)->where('host_id', auth()->id())->firstOrFail();

        $minutes = $request->integer('minutes', 0); // 0 = rest of stream
        $until   = $minutes > 0 ? now()->addMinutes($minutes) : null;

        LiveChatMute::updateOrCreate(
            ['live_room_id' => $id, 'user_id' => $userId],
            ['muted_until' => $until]
        );

        RealtimeService::toUser($userId, 'live.chat_muted', [
            'room_id'      => $id,
            'muted_until'  => $until?->toISOString(),
            'minutes'      => $minutes,
        ]);

        RealtimeService::toPublic("live.{$id}", 'live.user_muted', [
            'user_id' => $userId,
            'muted'   => true,
        ]);

        return response()->json(['status' => 'success']);
    }

    /** DELETE /live/rooms/{id}/chat/{userId}/mute — host unmutes user */
    public function unmuteUser(int $id, int $userId)
    {
        LiveRoom::where('id', $id)->where('host_id', auth()->id())->firstOrFail();

        LiveChatMute::where('live_room_id', $id)->where('user_id', $userId)->delete();

        RealtimeService::toPublic("live.{$id}", 'live.user_muted', [
            'user_id' => $userId,
            'muted'   => false,
        ]);

        return response()->json(['status' => 'success']);
    }

    /** POST /live/rooms/{id}/chat/pin/{messageId} — host pins a message */
    public function pinMessage(int $id, int $messageId)
    {
        LiveRoom::where('id', $id)->where('host_id', auth()->id())->firstOrFail();

        $msg = LiveRoomMessage::where('id', $messageId)
            ->where('live_room_id', $id)
            ->firstOrFail();

        // One pin per room — upsert
        \DB::table('live_chat_pins')->updateOrInsert(
            ['live_room_id' => $id],
            ['message_id' => $messageId, 'updated_at' => now(), 'created_at' => now()]
        );

        $user    = $msg->user;
        $profile = $user?->communityProfile;

        RealtimeService::toPublic("live.{$id}", 'live.chat_pinned', [
            'message_id' => $messageId,
            'message'    => $msg->message,
            'username'   => $profile?->username ?? $user?->name ?? '',
        ]);

        return response()->json(['status' => 'success']);
    }

    /** DELETE /live/rooms/{id}/chat/pin — host unpins */
    public function unpinMessage(int $id)
    {
        LiveRoom::where('id', $id)->where('host_id', auth()->id())->firstOrFail();

        \DB::table('live_chat_pins')->where('live_room_id', $id)->delete();

        RealtimeService::toPublic("live.{$id}", 'live.chat_pinned', ['message_id' => null]);

        return response()->json(['status' => 'success']);
    }

    private function transformSettings(LiveRoomSetting $s): array
    {
        return [
            'slow_mode'          => $s->slow_mode,
            'slow_mode_seconds'  => $s->slow_mode_seconds,
            'followers_only'     => $s->followers_only,
            'min_follow_seconds' => $s->min_follow_seconds,
            'subscribers_only'   => $s->subscribers_only,
            'comments_disabled'  => $s->comments_disabled,
            'blocked_words'      => $s->blocked_words ?? [],
        ];
    }
}
