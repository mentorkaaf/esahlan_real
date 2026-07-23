@extends('admin.layouts.app')
@section('title', 'Ticket #' . $conversation->uuid)
@section('content')

<style>
.cm-wrap { display:grid; grid-template-columns:1fr 300px; gap:16px; align-items:start; }
.cm-card { background:#fff; border:1px solid #e8ecf2; border-radius:14px; overflow:hidden; }
.cm-head { display:flex; align-items:center; gap:12px; padding:14px 18px; border-bottom:1px solid #f0f2f6; background:#fafbfd; }
.cm-back { color:#64748b; text-decoration:none; font-size:13px; font-weight:600; display:flex; align-items:center; gap:6px; }
.cm-back:hover { color:#07003B; }
.cm-title { font-size:15px; font-weight:800; color:#1a1d2e; flex:1; }
.cm-badge { display:inline-flex; align-items:center; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700; }
.cm-badge.open     { background:#eff6ff; color:#2563eb; }
.cm-badge.assigned { background:#f0fdf4; color:#16a34a; }
.cm-badge.resolved { background:#f8fafc; color:#94a3b8; }
.cm-badge.closed   { background:#fef2f2; color:#dc2626; }

.cm-messages { height:480px; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:12px; background:#f8f9fe; }
.cm-bubble { max-width:72%; padding:10px 14px; border-radius:14px; font-size:13px; line-height:1.5; }
.cm-bubble.user  { background:#fff; border:1px solid #e8ecf2; border-radius:14px 14px 14px 4px; align-self:flex-start; }
.cm-bubble.agent { background:#07003B; color:#fff; border-radius:14px 14px 4px 14px; align-self:flex-end; }
.cm-bubble.bot   { background:#fff3cd; border:1px solid #ffc107; border-radius:14px; align-self:flex-start; }
.cm-sender { font-size:10px; font-weight:700; margin-bottom:4px; }
.cm-sender.user  { color:#6c63ff; }
.cm-sender.agent { color:#FF8A00; }
.cm-time { font-size:10px; margin-top:5px; opacity:.5; }
.cm-deleted { font-style:italic; opacity:.5; }
.cm-image { max-width:220px; border-radius:10px; display:block; }

.cm-reply { display:flex; gap:10px; padding:14px 16px; border-top:1px solid #f0f2f6; align-items:flex-end; }
.cm-reply textarea { flex:1; border:1.5px solid #e2e8f0; border-radius:10px; padding:9px 12px; font-size:13px; resize:none; min-height:60px; font-family:inherit; }
.cm-reply textarea:focus { outline:none; border-color:#07003B; }
.cm-send-btn { background:#07003B; color:#fff; border:none; border-radius:10px; padding:10px 18px; font-size:13px; font-weight:700; cursor:pointer; }
.cm-send-btn:hover { background:#140465; }

/* Sidebar */
.cm-info-card { background:#fff; border:1px solid #e8ecf2; border-radius:14px; overflow:hidden; margin-bottom:14px; }
.cm-info-head { padding:12px 16px; border-bottom:1px solid #f0f2f6; font-size:12px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:.5px; background:#fafbfd; }
.cm-info-body { padding:14px 16px; }
.cm-info-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; font-size:12px; }
.cm-info-row:last-child { margin-bottom:0; }
.cm-info-label { color:#94a3b8; font-weight:600; }
.cm-info-value { font-weight:700; color:#1a1d2e; }
.cm-action-btn { width:100%; padding:8px 14px; border-radius:9px; font-size:12px; font-weight:700; cursor:pointer; border:1.5px solid; margin-bottom:8px; transition:all .15s; }
.cm-action-btn:last-child { margin-bottom:0; }
.cm-action-resolve { border-color:#16a34a; color:#16a34a; background:#f0fdf4; }
.cm-action-resolve:hover { background:#16a34a; color:#fff; }
.cm-action-close { border-color:#dc2626; color:#dc2626; background:#fef2f2; }
.cm-action-close:hover { background:#dc2626; color:#fff; }
.cm-avatar { width:40px; height:40px; border-radius:10px; background:linear-gradient(135deg,#6c63ff,#3a36d4); color:#fff; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:900; margin-bottom:10px; }
.cm-user-name { font-size:14px; font-weight:800; color:#1a1d2e; }
.cm-user-sub { font-size:11px; color:#94a3b8; margin-top:2px; }
.cm-select { width:100%; padding:7px 10px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:12px; color:#1a1d2e; margin-top:6px; }
.cm-select:focus { outline:none; border-color:#07003B; }
</style>

<div style="margin-bottom:16px">
    <a href="{{ route('admin.inbox.conversations') }}" class="cm-back">
        <i class="fas fa-arrow-left"></i> Support Tickets
    </a>
</div>

<div class="cm-wrap">

    {{-- Chat column --}}
    <div>
        <div class="cm-card">
            <div class="cm-head">
                <div class="cm-title">{{ $conversation->subject ?? '(no subject)' }}</div>
                <span class="cm-badge {{ $conversation->status }}">{{ ucfirst($conversation->status) }}</span>
            </div>

            <div class="cm-messages" id="msgBox">
                @foreach($messages as $msg)
                <div>
                    <div class="cm-sender {{ $msg->sender_type }}">
                        @if($msg->sender_type === 'user') {{ $msg->sender->name ?? 'Customer' }}
                        @elseif($msg->sender_type === 'agent') {{ $msg->sender->name ?? 'Agent' }} (Agent)
                        @else Bot
                        @endif
                    </div>
                    <div class="cm-bubble {{ $msg->sender_type }}">
                        @if($msg->is_deleted)
                            <span class="cm-deleted">Message deleted</span>
                        @elseif($msg->type === 'image' && $msg->media_url)
                            <img src="{{ $msg->media_url }}" class="cm-image" alt="image">
                        @elseif($msg->type === 'audio' && $msg->media_url)
                            <audio controls src="{{ $msg->media_url }}" style="max-width:200px"></audio>
                        @else
                            {{ $msg->content }}
                        @endif
                    </div>
                    <div class="cm-time">{{ $msg->created_at->format('M d, H:i') }}</div>
                </div>
                @endforeach
            </div>

            @if(!in_array($conversation->status, ['closed']))
            <form method="POST" action="{{ route('admin.inbox.conversation.reply', $conversation->uuid) }}" class="cm-reply" id="replyForm">
                @csrf
                <textarea name="content" placeholder="Type your reply…" required></textarea>
                <button type="submit" class="cm-send-btn"><i class="fas fa-paper-plane"></i></button>
            </form>
            @endif
        </div>
    </div>

    {{-- Sidebar --}}
    <div>
        {{-- Customer info --}}
        <div class="cm-info-card">
            <div class="cm-info-head">Customer</div>
            <div class="cm-info-body">
                <div class="cm-avatar">{{ strtoupper(substr($conversation->user->name ?? '?', 0, 2)) }}</div>
                <div class="cm-user-name">{{ $conversation->user->name ?? 'Unknown' }}</div>
                <div class="cm-user-sub">{{ $conversation->user->phone ?? $conversation->user->email ?? '' }}</div>
            </div>
        </div>

        {{-- Ticket info --}}
        <div class="cm-info-card">
            <div class="cm-info-head">Ticket Info</div>
            <div class="cm-info-body">
                <div class="cm-info-row">
                    <span class="cm-info-label">Module</span>
                    <span class="cm-info-value">{{ $conversation->module ?? 'general' }}</span>
                </div>
                <div class="cm-info-row">
                    <span class="cm-info-label">Priority</span>
                    <span class="cm-info-value">{{ ucfirst($conversation->priority) }}</span>
                </div>
                <div class="cm-info-row">
                    <span class="cm-info-label">Created</span>
                    <span class="cm-info-value">{{ $conversation->created_at->format('M d, H:i') }}</span>
                </div>
                <div class="cm-info-row">
                    <span class="cm-info-label">Messages</span>
                    <span class="cm-info-value">{{ $messages->total() }}</span>
                </div>
            </div>
        </div>

        {{-- Assign agent --}}
        <div class="cm-info-card">
            <div class="cm-info-head">Assigned Agent</div>
            <div class="cm-info-body">
                <form method="POST" action="{{ route('admin.inbox.conversation.assign', $conversation->uuid) }}">
                    @csrf
                    <select name="agent_id" class="cm-select" onchange="this.form.submit()">
                        <option value="">Unassigned</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" {{ $conversation->agent_id == $agent->id ? 'selected' : '' }}>
                                {{ $agent->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        {{-- Actions --}}
        <div class="cm-info-card">
            <div class="cm-info-head">Actions</div>
            <div class="cm-info-body">
                @if(!in_array($conversation->status, ['resolved', 'closed']))
                <form method="POST" action="{{ route('admin.inbox.conversation.status', $conversation->uuid) }}" style="margin-bottom:8px">
                    @csrf
                    <input type="hidden" name="status" value="resolved">
                    <button type="submit" class="cm-action-btn cm-action-resolve">
                        <i class="fas fa-check"></i> Mark Resolved
                    </button>
                </form>
                @endif
                @if($conversation->status !== 'closed')
                <form method="POST" action="{{ route('admin.inbox.conversation.status', $conversation->uuid) }}">
                    @csrf
                    <input type="hidden" name="status" value="closed">
                    <button type="submit" class="cm-action-btn cm-action-close">
                        <i class="fas fa-times"></i> Close Ticket
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
// Scroll to bottom on load
const box = document.getElementById('msgBox');
if (box) box.scrollTop = box.scrollHeight;
</script>
@endsection
