<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{InboxConversation, InboxMessage, MarketingBroadcast, MarketingBroadcastUser, InboxCallSession};
use App\Services\FcmService;
use App\Events\InboxMessageSent;
use App\Events\InboxTyping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InboxController extends Controller
{
    // ── Marketing Broadcasts ──────────────────────────────────────────────────

    public function broadcasts(Request $request)
    {
        $user = $request->user();
        $broadcasts = MarketingBroadcast::where('status', 'sent')
            ->orderByDesc('sent_at')
            ->paginate(20);

        $data = $broadcasts->through(function ($b) use ($user) {
            $read = MarketingBroadcastUser::where('broadcast_id', $b->id)
                ->where('user_id', $user->id)->first();
            return $this->_formatBroadcast($b, $read);
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function markBroadcastRead(Request $request, string $uuid)
    {
        $user = $request->user();
        $broadcast = MarketingBroadcast::where('uuid', $uuid)->where('status', 'sent')->firstOrFail();

        MarketingBroadcastUser::updateOrCreate(
            ['broadcast_id' => $broadcast->id, 'user_id' => $user->id],
            ['is_read' => true, 'read_at' => now()]
        );

        return response()->json(['success' => true]);
    }

    public function trackCtaClick(Request $request, string $uuid)
    {
        $user = $request->user();
        $broadcast = MarketingBroadcast::where('uuid', $uuid)->firstOrFail();

        MarketingBroadcastUser::updateOrCreate(
            ['broadcast_id' => $broadcast->id, 'user_id' => $user->id],
            ['cta_clicked' => true, 'clicked_at' => now(), 'is_read' => true, 'read_at' => now()]
        );

        return response()->json(['success' => true, 'data' => ['route' => $broadcast->cta_route]]);
    }

    // ── Conversations ─────────────────────────────────────────────────────────

    public function conversations(Request $request)
    {
        $user = $request->user();
        $convs = InboxConversation::with(['lastMsg'])
            ->where('user_id', $user->id)
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $convs->through(fn($c) => $this->_formatConv($c))]);
    }

    public function createConversation(Request $request)
    {
        $request->validate([
            'module'  => 'required|string|max:50',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:2000',
        ]);

        $user = $request->user();
        $conv = InboxConversation::create([
            'uuid'            => (string) Str::uuid(),
            'user_id'         => $user->id,
            'module'          => $request->module,
            'subject'         => $request->subject,
            'status'          => 'open',
            'last_message'    => $request->message,
            'last_message_at' => now(),
            'unread_agent'    => 1,
        ]);

        $msg = $this->_createMessage($conv, $user->id, 'user', 'text', $request->message);

        // Notify admins via FCM (no specific token — use topic)
        try {
            FcmService::sendToTopic('admin_inbox', 'New Support Request', "{$user->name}: {$request->subject}", [
                'type' => 'support_new', 'conv_uuid' => $conv->uuid,
            ]);
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'data' => $this->_formatConv($conv->fresh(['lastMsg']))], 201);
    }

    public function messages(Request $request, string $uuid)
    {
        $user = $request->user();
        $conv = InboxConversation::where('uuid', $uuid)->where('user_id', $user->id)->firstOrFail();

        // Mark messages as seen
        InboxMessage::where('conversation_id', $conv->id)
            ->where('sender_type', '!=', 'user')
            ->whereNull('seen_at')
            ->update(['status' => 'seen', 'seen_at' => now()]);

        $conv->update(['unread_user' => 0]);

        $msgs = InboxMessage::where('conversation_id', $conv->id)
            ->where('is_deleted', false)
            ->orderBy('created_at')
            ->paginate(50);

        // Broadcast seen to agent
        try {
            broadcast(new InboxMessageSent($conv->uuid, ['event' => 'seen', 'by' => 'user']))->toOthers();
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'data' => [
            'conversation' => $this->_formatConv($conv),
            'messages'     => $msgs->through(fn($m) => $this->_formatMsg($m)),
        ]]);
    }

    public function sendMessage(Request $request, string $uuid)
    {
        $request->validate([
            'type'    => 'required|in:text,image,audio,video,file',
            'content' => 'nullable|string|max:5000',
            'media'   => 'nullable|file|max:51200', // 50MB
        ]);

        $user = $request->user();
        $conv = InboxConversation::where('uuid', $uuid)->where('user_id', $user->id)
            ->whereNotIn('status', ['closed'])->firstOrFail();

        $mediaUrl = null;
        $duration = null;

        if ($request->hasFile('media')) {
            $path = $request->file('media')->store('inbox/media', 'public');
            $mediaUrl = Storage::url($path);
            if ($request->type === 'audio') {
                $duration = (int) ($request->duration ?? 0);
            }
        }

        $msg = $this->_createMessage(
            $conv, $user->id, 'user',
            $request->type, $request->content, $mediaUrl, $duration
        );

        $conv->update([
            'last_message'    => $request->type === 'text' ? $request->content : "[{$request->type}]",
            'last_message_at' => now(),
            'unread_agent'    => $conv->unread_agent + 1,
        ]);

        // Broadcast real-time
        try {
            broadcast(new InboxMessageSent($conv->uuid, $this->_formatMsg($msg)))->toOthers();
        } catch (\Throwable) {}

        // Notify agent if assigned
        if ($conv->agent_id) {
            $agent = \App\Models\User::find($conv->agent_id);
            try {
                if ($agent?->fcm_token) {
                    FcmService::sendToToken($agent->fcm_token, $user->name, $request->content ?? '[media]', [
                        'type' => 'inbox_message', 'conv_uuid' => $conv->uuid,
                    ]);
                }
            } catch (\Throwable) {}
        } else {
            // Notify all admins
            try {
                FcmService::sendToTopic('admin_inbox', $user->name, $request->content ?? '[media]', [
                    'type' => 'inbox_message', 'conv_uuid' => $conv->uuid,
                ]);
            } catch (\Throwable) {}
        }

        return response()->json(['success' => true, 'data' => $this->_formatMsg($msg)], 201);
    }

    public function typing(Request $request, string $uuid)
    {
        $user = $request->user();
        $conv = InboxConversation::where('uuid', $uuid)->where('user_id', $user->id)->firstOrFail();

        try {
            broadcast(new InboxTyping($conv->uuid, $user->id, 'user'))->toOthers();
        } catch (\Throwable) {}

        return response()->json(['success' => true]);
    }

    public function reopen(Request $request, string $uuid)
    {
        $user = $request->user();
        $conv = InboxConversation::where('uuid', $uuid)->where('user_id', $user->id)->firstOrFail();

        if (!in_array($conv->status, ['resolved', 'closed'])) {
            return response()->json(['success' => false, 'message' => 'Ticket is not closed'], 422);
        }

        $conv->update(['status' => 'open', 'last_message_at' => now()]);

        return response()->json(['success' => true]);
    }

    // ── Audio Calls ───────────────────────────────────────────────────────────

    public function initiateCall(Request $request, string $uuid)
    {
        $user = $request->user();
        $conv = InboxConversation::where('uuid', $uuid)->where('user_id', $user->id)->firstOrFail();

        $roomName = 'inbox-' . $conv->uuid . '-' . now()->timestamp;
        $session  = InboxCallSession::create([
            'uuid'           => (string) Str::uuid(),
            'conversation_id'=> $conv->id,
            'initiated_by'   => $user->id,
            'room_name'      => $roomName,
            'status'         => 'ringing',
        ]);

        $token = $this->_livekitToken($roomName, (string) $user->id, $user->name);

        // Notify agent
        try {
            broadcast(new InboxMessageSent($conv->uuid, [
                'event'     => 'call_initiated',
                'room_name' => $roomName,
                'call_uuid' => $session->uuid,
            ]))->toOthers();
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'data' => [
            'call_uuid'  => $session->uuid,
            'room_name'  => $roomName,
            'livekit_url'=> config('services.livekit.url', env('LIVEKIT_URL', '')),
            'token'      => $token,
        ]]);
    }

    public function joinCall(Request $request, string $callUuid)
    {
        $user    = $request->user();
        $session = InboxCallSession::where('uuid', $callUuid)->firstOrFail();
        $session->update(['status' => 'active', 'answered_at' => now()]);

        $token = $this->_livekitToken($session->room_name, (string) $user->id, $user->name);

        return response()->json(['success' => true, 'data' => [
            'room_name'   => $session->room_name,
            'livekit_url' => config('services.livekit.url', env('LIVEKIT_URL', '')),
            'token'       => $token,
        ]]);
    }

    public function endCall(Request $request, string $callUuid)
    {
        $session = InboxCallSession::where('uuid', $callUuid)->firstOrFail();
        $duration = $session->answered_at ? now()->diffInSeconds($session->answered_at) : 0;
        $session->update(['status' => 'ended', 'ended_at' => now(), 'duration_seconds' => $duration]);

        try {
            $convUuid = $session->conversation?->uuid ?? '';
            broadcast(new InboxMessageSent($convUuid, [
                'event' => 'call_ended', 'call_uuid' => $callUuid, 'duration' => $duration,
            ]))->toOthers();
        } catch (\Throwable) {}

        return response()->json(['success' => true]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function _createMessage(
        InboxConversation $conv, int $senderId, string $senderType,
        string $type, ?string $content, ?string $mediaUrl = null, ?int $duration = null
    ): InboxMessage {
        return InboxMessage::create([
            'uuid'            => (string) Str::uuid(),
            'conversation_id' => $conv->id,
            'sender_id'       => $senderId,
            'sender_type'     => $senderType,
            'type'            => $type,
            'content'         => $content,
            'media_url'       => $mediaUrl,
            'media_duration'  => $duration,
            'status'          => 'sent',
        ]);
    }

    private function _livekitToken(string $room, string $identity, string $name): string
    {
        try {
            $apiKey    = env('LIVEKIT_API_KEY', '');
            $apiSecret = env('LIVEKIT_API_SECRET', '');
            if (!$apiKey || !$apiSecret) return '';

            // JWT for LiveKit
            $now    = time();
            $exp    = $now + 3600;
            $grants = ['roomJoin' => true, 'room' => $room, 'canPublish' => true, 'canSubscribe' => true];
            $payload = [
                'iss'   => $apiKey,
                'sub'   => $identity,
                'iat'   => $now,
                'exp'   => $exp,
                'name'  => $name,
                'video' => $grants,
            ];
            $header    = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
            $body      = base64_encode(json_encode($payload));
            $signature = base64_encode(hash_hmac('sha256', "$header.$body", $apiSecret, true));
            return "$header.$body.$signature";
        } catch (\Throwable) {
            return '';
        }
    }

    private function _formatConv(InboxConversation $c): array
    {
        return [
            'uuid'             => $c->uuid,
            'module'           => $c->module,
            'subject'          => $c->subject,
            'status'           => $c->status,
            'priority'         => $c->priority,
            'last_message'     => $c->last_message,
            'last_message_at'  => $c->last_message_at,
            'unread'           => $c->unread_user,
            'agent'            => $c->agent ? ['name' => $c->agent->name, 'avatar' => $c->agent->profile_photo_path] : null,
            'created_at'       => $c->created_at,
        ];
    }

    private function _formatMsg(InboxMessage $m): array
    {
        return [
            'uuid'        => $m->uuid,
            'sender_id'   => $m->sender_id,
            'sender_type' => $m->sender_type,
            'sender_name' => $m->sender?->name,
            'type'        => $m->type,
            'content'     => $m->is_deleted ? null : $m->content,
            'media_url'   => $m->is_deleted ? null : $m->media_url,
            'duration'    => $m->media_duration,
            'status'      => $m->status,
            'is_deleted'  => $m->is_deleted,
            'created_at'  => $m->created_at,
        ];
    }

    private function _formatBroadcast(MarketingBroadcast $b, ?MarketingBroadcastUser $read): array
    {
        return [
            'uuid'       => $b->uuid,
            'title'      => $b->title,
            'body'       => $b->body,
            'image_url'  => $b->image_url,
            'video_url'  => $b->video_url,
            'module'     => $b->module,
            'cta_label'  => $b->cta_label,
            'cta_route'  => $b->cta_route,
            'is_read'    => $read?->is_read ?? false,
            'sent_at'    => $b->sent_at,
        ];
    }
}
