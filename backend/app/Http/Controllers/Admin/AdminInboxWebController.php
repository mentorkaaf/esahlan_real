<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{InboxConversation, InboxMessage, MarketingBroadcast, MarketingBroadcastUser, User};
use App\Services\FcmService;
use App\Events\InboxMessageSent;
use App\Jobs\SendMarketingBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminInboxWebController extends Controller
{
    // ── Support Tickets ───────────────────────────────────────────────────────

    public function conversations(Request $request)
    {
        $convs = InboxConversation::with(['user', 'agent'])
            ->orderByDesc('last_message_at')
            ->paginate(25);

        $stats = [
            'open_tickets'     => InboxConversation::whereIn('status', ['open', 'assigned'])->count(),
            'unassigned'       => InboxConversation::whereNull('agent_id')->whereIn('status', ['open', 'assigned'])->count(),
            'resolved_today'   => InboxConversation::where('status', 'resolved')->whereDate('updated_at', today())->count(),
            'total_broadcasts' => MarketingBroadcast::where('status', 'sent')->count(),
        ];

        return view('admin.inbox.conversations', compact('convs', 'stats'))
            ->with('conversations', $convs);
    }

    public function conversationMessages(Request $request, string $uuid)
    {
        $conv = InboxConversation::with(['user', 'agent'])->where('uuid', $uuid)->firstOrFail();

        // Mark messages as delivered
        InboxMessage::where('conversation_id', $conv->id)
            ->where('sender_type', 'user')->whereNull('delivered_at')
            ->update(['status' => 'delivered', 'delivered_at' => now()]);
        $conv->update(['unread_agent' => 0]);

        try {
            broadcast(new InboxMessageSent($conv->uuid, ['event' => 'delivered']))->toOthers();
        } catch (\Throwable) {}

        $messages = InboxMessage::with('sender')
            ->where('conversation_id', $conv->id)
            ->orderBy('created_at')
            ->paginate(100);

        $agents = User::whereHas('role', fn($q) => $q->whereIn('slug', ['super_admin', 'admin', 'support_agent']))->get(['id', 'name']);

        return view('admin.inbox.conversation_messages', compact('conv', 'messages', 'agents'))
            ->with('conversation', $conv);
    }

    public function reply(Request $request, string $uuid)
    {
        $request->validate(['content' => 'required|string|max:5000']);

        $admin = $request->user();
        $conv  = InboxConversation::where('uuid', $uuid)->firstOrFail();

        $msg = InboxMessage::create([
            'uuid'            => (string) Str::uuid(),
            'conversation_id' => $conv->id,
            'sender_id'       => $admin->id,
            'sender_type'     => 'agent',
            'type'            => 'text',
            'content'         => $request->content,
            'status'          => 'sent',
        ]);

        $conv->update([
            'status'          => 'assigned',
            'agent_id'        => $admin->id,
            'last_message'    => $request->content,
            'last_message_at' => now(),
            'unread_user'     => $conv->unread_user + 1,
        ]);

        try {
            broadcast(new InboxMessageSent($conv->uuid, [
                'uuid'        => $msg->uuid,
                'sender_id'   => $admin->id,
                'sender_type' => 'agent',
                'sender_name' => $admin->name,
                'type'        => 'text',
                'content'     => $msg->content,
                'media_url'   => null,
                'status'      => 'sent',
                'is_deleted'  => false,
                'created_at'  => $msg->created_at,
            ]))->toOthers();
        } catch (\Throwable) {}

        $user = User::find($conv->user_id);
        try {
            if ($user?->fcm_token) {
                FcmService::sendToToken($user->fcm_token, 'eSahlan Support', $request->content, [
                    'type' => 'inbox_reply', 'conv_uuid' => $conv->uuid,
                ]);
            }
        } catch (\Throwable) {}

        return redirect()->route('admin.inbox.conversation.messages', $uuid)
            ->with('success', 'Reply sent.');
    }

    public function assignAgent(Request $request, string $uuid)
    {
        $conv = InboxConversation::where('uuid', $uuid)->firstOrFail();
        $conv->update(['agent_id' => $request->agent_id ?: null]);
        return redirect()->back()->with('success', 'Agent updated.');
    }

    public function updateStatus(Request $request, string $uuid)
    {
        $request->validate(['status' => 'required|in:open,assigned,resolved,closed']);
        $conv = InboxConversation::where('uuid', $uuid)->firstOrFail();
        $conv->update(['status' => $request->status]);

        if ($request->status === 'resolved') {
            $user = User::find($conv->user_id);
            try {
                if ($user?->fcm_token) {
                    FcmService::sendToToken($user->fcm_token, 'Ticket Resolved', 'Your support ticket has been resolved.', [
                        'type' => 'inbox_resolved', 'conv_uuid' => $conv->uuid,
                    ]);
                }
            } catch (\Throwable) {}
        }

        return redirect()->back()->with('success', 'Status updated.');
    }

    // ── Marketing Broadcasts ──────────────────────────────────────────────────

    public function broadcasts(Request $request)
    {
        $broadcasts = MarketingBroadcast::orderByDesc('created_at')->paginate(20);
        return view('admin.inbox.broadcasts', compact('broadcasts'));
    }

    public function createBroadcast(Request $request)
    {
        $request->validate([
            'title'  => 'required|string|max:255',
            'body'   => 'required|string',
            'module' => 'nullable|string|max:50',
            'target' => 'nullable|in:all,active_30d',
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path     = $request->file('image')->store('inbox/broadcast', 'public');
            $imageUrl = Storage::url($path);
        }

        MarketingBroadcast::create([
            'uuid'       => (string) Str::uuid(),
            'created_by' => $request->user()->id,
            'title'      => $request->title,
            'body'       => $request->body,
            'image_url'  => $imageUrl,
            'module'     => $request->module,
            'cta_label'  => $request->cta_label,
            'cta_route'  => $request->cta_route,
            'target'     => $request->target ?? 'all',
            'status'     => 'draft',
        ]);

        return redirect()->route('admin.inbox.broadcasts.index')
            ->with('success', 'Broadcast created as draft.');
    }

    public function sendBroadcast(Request $request, string $uuid)
    {
        $broadcast = MarketingBroadcast::where('uuid', $uuid)->where('status', 'draft')->firstOrFail();
        $broadcast->update(['status' => 'sending']);
        dispatch(new SendMarketingBroadcast($broadcast->id));
        return redirect()->back()->with('success', 'Broadcast queued for sending.');
    }

    public function broadcastStats(Request $request, string $uuid)
    {
        $broadcast = MarketingBroadcast::where('uuid', $uuid)->firstOrFail();
        $stats = [
            'sent'        => MarketingBroadcastUser::where('broadcast_id', $broadcast->id)->count(),
            'read'        => MarketingBroadcastUser::where('broadcast_id', $broadcast->id)->where('is_read', true)->count(),
            'cta_clicked' => MarketingBroadcastUser::where('broadcast_id', $broadcast->id)->where('cta_clicked', true)->count(),
        ];
        return view('admin.inbox.broadcast_stats', compact('broadcast', 'stats'));
    }

    public function stats()
    {
        $stats = [
            'open_tickets'     => InboxConversation::whereIn('status', ['open', 'assigned'])->count(),
            'unassigned'       => InboxConversation::whereNull('agent_id')->whereIn('status', ['open', 'assigned'])->count(),
            'resolved_today'   => InboxConversation::where('status', 'resolved')->whereDate('updated_at', today())->count(),
            'total_broadcasts' => MarketingBroadcast::where('status', 'sent')->count(),
        ];
        return view('admin.inbox.stats', compact('stats'));
    }
}
