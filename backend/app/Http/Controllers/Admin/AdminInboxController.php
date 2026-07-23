<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{InboxConversation, InboxMessage, MarketingBroadcast, MarketingBroadcastUser, User};
use App\Services\FcmService;
use App\Events\InboxMessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminInboxController extends Controller
{
    // ── Live Chat ─────────────────────────────────────────────────────────────

    public function conversations(Request $request)
    {
        $q = InboxConversation::with(['user', 'agent', 'lastMsg'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->module, fn($q) => $q->where('module', $request->module))
            ->when($request->search, fn($q) => $q->whereHas('user', fn($u) =>
                $u->where('name', 'like', "%{$request->search}%")))
            ->orderByDesc('last_message_at');

        return response()->json(['success' => true, 'data' => $q->paginate(20)->through(fn($c) => [
            'uuid'           => $c->uuid,
            'user'           => ['id' => $c->user_id, 'name' => $c->user?->name, 'phone' => $c->user?->phone],
            'agent'          => $c->agent ? ['id' => $c->agent_id, 'name' => $c->agent->name] : null,
            'module'         => $c->module,
            'subject'        => $c->subject,
            'status'         => $c->status,
            'priority'       => $c->priority,
            'last_message'   => $c->last_message,
            'last_message_at'=> $c->last_message_at,
            'unread'         => $c->unread_agent,
            'created_at'     => $c->created_at,
        ])]);
    }

    public function conversationMessages(Request $request, string $uuid)
    {
        $conv = InboxConversation::with(['user', 'agent'])->where('uuid', $uuid)->firstOrFail();

        // Mark as read by agent
        InboxMessage::where('conversation_id', $conv->id)
            ->where('sender_type', 'user')
            ->whereNull('delivered_at')
            ->update(['status' => 'delivered', 'delivered_at' => now()]);
        $conv->update(['unread_agent' => 0]);

        // Broadcast delivered
        try {
            broadcast(new InboxMessageSent($conv->uuid, ['event' => 'delivered']))->toOthers();
        } catch (\Throwable) {}

        $msgs = InboxMessage::where('conversation_id', $conv->id)
            ->orderBy('created_at')->paginate(50);

        return response()->json(['success' => true, 'data' => [
            'conversation' => [
                'uuid'     => $conv->uuid,
                'user'     => ['id' => $conv->user_id, 'name' => $conv->user?->name, 'phone' => $conv->user?->phone],
                'module'   => $conv->module,
                'subject'  => $conv->subject,
                'status'   => $conv->status,
                'priority' => $conv->priority,
                'agent'    => $conv->agent ? ['id' => $conv->agent_id, 'name' => $conv->agent->name] : null,
            ],
            'messages' => $msgs->through(fn($m) => [
                'uuid'        => $m->uuid,
                'sender_id'   => $m->sender_id,
                'sender_type' => $m->sender_type,
                'type'        => $m->type,
                'content'     => $m->is_deleted ? null : $m->content,
                'media_url'   => $m->is_deleted ? null : $m->media_url,
                'duration'    => $m->media_duration,
                'status'      => $m->status,
                'is_deleted'  => $m->is_deleted,
                'created_at'  => $m->created_at,
            ]),
        ]]);
    }

    public function reply(Request $request, string $uuid)
    {
        $request->validate([
            'type'    => 'required|in:text,image,audio',
            'content' => 'nullable|string|max:5000',
            'media'   => 'nullable|file|max:20480',
        ]);

        $admin = $request->user();
        $conv  = InboxConversation::where('uuid', $uuid)->firstOrFail();

        $mediaUrl = null;
        if ($request->hasFile('media')) {
            $path     = $request->file('media')->store('inbox/media', 'public');
            $mediaUrl = Storage::url($path);
        }

        $msg = InboxMessage::create([
            'uuid'            => (string) Str::uuid(),
            'conversation_id' => $conv->id,
            'sender_id'       => $admin->id,
            'sender_type'     => 'agent',
            'type'            => $request->type,
            'content'         => $request->content,
            'media_url'       => $mediaUrl,
            'status'          => 'sent',
        ]);

        $conv->update([
            'status'          => 'assigned',
            'agent_id'        => $admin->id,
            'last_message'    => $request->type === 'text' ? $request->content : "[{$request->type}]",
            'last_message_at' => now(),
            'unread_user'     => $conv->unread_user + 1,
        ]);

        // Real-time broadcast to user
        try {
            broadcast(new InboxMessageSent($conv->uuid, [
                'uuid'        => $msg->uuid,
                'sender_id'   => $admin->id,
                'sender_type' => 'agent',
                'sender_name' => $admin->name,
                'type'        => $msg->type,
                'content'     => $msg->content,
                'media_url'   => $msg->media_url,
                'status'      => 'sent',
                'created_at'  => $msg->created_at,
            ]))->toOthers();
        } catch (\Throwable) {}

        // FCM to user
        $user = User::find($conv->user_id);
        try {
            if ($user?->fcm_token) {
                FcmService::sendToToken($user->fcm_token, 'Support', $request->content ?? '[media]', [
                    'type'      => 'inbox_message',
                    'conv_uuid' => $conv->uuid,
                ]);
            }
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'data' => $msg]);
    }

    public function assignAgent(Request $request, string $uuid)
    {
        $request->validate(['agent_id' => 'required|integer|exists:users,id']);
        $conv = InboxConversation::where('uuid', $uuid)->firstOrFail();
        $conv->update(['agent_id' => $request->agent_id, 'status' => 'assigned']);
        return response()->json(['success' => true]);
    }

    public function updateStatus(Request $request, string $uuid)
    {
        $request->validate(['status' => 'required|in:open,assigned,resolved,closed']);
        $conv = InboxConversation::where('uuid', $uuid)->firstOrFail();
        $conv->update([
            'status'      => $request->status,
            'resolved_at' => $request->status === 'resolved' ? now() : null,
        ]);

        // Notify user of resolution
        if ($request->status === 'resolved') {
            $user = User::find($conv->user_id);
            try {
                if ($user?->fcm_token) {
                    FcmService::sendToToken($user->fcm_token, 'Support Resolved',
                        'Your support request has been resolved.', ['type' => 'support_resolved', 'conv_uuid' => $conv->uuid]);
                }
            } catch (\Throwable) {}
        }

        return response()->json(['success' => true]);
    }

    public function typingIndicator(Request $request, string $uuid)
    {
        $conv = InboxConversation::where('uuid', $uuid)->firstOrFail();
        try {
            broadcast(new \App\Events\InboxTyping($conv->uuid, $request->user()->id, 'agent'))->toOthers();
        } catch (\Throwable) {}
        return response()->json(['success' => true]);
    }

    // ── Marketing Broadcasts ──────────────────────────────────────────────────

    public function broadcasts(Request $request)
    {
        $broadcasts = MarketingBroadcast::with('reads')
            ->orderByDesc('created_at')->paginate(20);
        return response()->json(['success' => true, 'data' => $broadcasts]);
    }

    public function createBroadcast(Request $request)
    {
        $request->validate([
            'title'     => 'required|string|max:200',
            'body'      => 'required|string|max:5000',
            'image'     => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'video_url' => 'nullable|string|max:500',
            'module'    => 'nullable|string|max:50',
            'cta_label' => 'nullable|string|max:100',
            'cta_route' => 'nullable|string|max:200',
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path     = $request->file('image')->store('marketing/images', 'public');
            $imageUrl = Storage::url($path);
        }

        $broadcast = MarketingBroadcast::create([
            'uuid'       => (string) Str::uuid(),
            'created_by' => $request->user()->id,
            'title'      => $request->title,
            'body'       => $request->body,
            'image_url'  => $imageUrl,
            'video_url'  => $request->video_url,
            'module'     => $request->module,
            'cta_label'  => $request->cta_label,
            'cta_route'  => $request->cta_route,
            'status'     => 'draft',
        ]);

        return response()->json(['success' => true, 'data' => $broadcast], 201);
    }

    public function sendBroadcast(Request $request, string $uuid)
    {
        $broadcast = MarketingBroadcast::where('uuid', $uuid)->where('status', 'draft')->firstOrFail();
        $broadcast->update(['status' => 'sending']);

        // Dispatch background job to send FCM to all users
        \App\Jobs\SendMarketingBroadcast::dispatch($broadcast->id);

        return response()->json(['success' => true, 'message' => 'Broadcast sending in background']);
    }

    public function broadcastStats(Request $request, string $uuid)
    {
        $broadcast = MarketingBroadcast::where('uuid', $uuid)->firstOrFail();
        return response()->json(['success' => true, 'data' => [
            'sent'        => $broadcast->sent_count,
            'read'        => $broadcast->reads()->where('is_read', true)->count(),
            'cta_clicked' => $broadcast->reads()->where('cta_clicked', true)->count(),
        ]]);
    }

    // ── Dashboard stats ───────────────────────────────────────────────────────

    public function stats()
    {
        return response()->json(['success' => true, 'data' => [
            'open_tickets'     => InboxConversation::where('status', 'open')->count(),
            'unassigned'       => InboxConversation::where('status', 'open')->whereNull('agent_id')->count(),
            'resolved_today'   => InboxConversation::where('status', 'resolved')
                ->whereDate('resolved_at', today())->count(),
            'total_broadcasts' => MarketingBroadcast::where('status', 'sent')->count(),
        ]]);
    }
}
