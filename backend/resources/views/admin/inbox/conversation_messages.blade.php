@extends('admin.layouts.app')
@section('title', 'Ticket — ' . Str::limit($conversation->subject, 40))
@section('content')

<style>
/* ── Layout ── */
.chat-wrap { display:grid; grid-template-columns:1fr 280px; gap:0; height:calc(100vh - 120px); min-height:500px; background:#fff; border-radius:16px; overflow:hidden; border:1px solid #e8ecf2; box-shadow:0 4px 24px rgba(7,0,59,.07); }

/* ── Left: chat column ── */
.chat-col { display:flex; flex-direction:column; min-height:0; }

/* header */
.chat-header { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(135deg,#07003B,#1a0070); flex-shrink:0; }
.chat-header-avatar { width:40px; height:40px; border-radius:12px; background:rgba(255,255,255,.15); color:#fff; font-size:14px; font-weight:900; display:flex; align-items:center; justify-content:center; flex-shrink:0; border:1.5px solid rgba(255,255,255,.2); }
.chat-header-name { font-size:14px; font-weight:800; color:#fff; }
.chat-header-sub { font-size:11px; color:rgba(255,255,255,.55); margin-top:1px; }
.chat-header-badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:10px; font-weight:800; margin-left:auto; flex-shrink:0; }
.chat-header-badge.open     { background:rgba(96,165,250,.25); color:#93c5fd; }
.chat-header-badge.assigned { background:rgba(74,222,128,.25); color:#86efac; }
.chat-header-badge.resolved { background:rgba(148,163,184,.2); color:#cbd5e1; }
.chat-header-badge.closed   { background:rgba(248,113,113,.2); color:#fca5a5; }
.chat-header-mod { background:rgba(255,255,255,.1); color:rgba(255,255,255,.7); border-radius:8px; padding:3px 9px; font-size:10px; font-weight:700; }

/* messages */
.chat-messages { flex:1; overflow-y:auto; padding:18px 16px; background:#f0f2f8; display:flex; flex-direction:column; gap:0; }
.chat-messages::-webkit-scrollbar { width:4px; }
.chat-messages::-webkit-scrollbar-thumb { background:rgba(7,0,59,.12); border-radius:4px; }

.msg-group { margin-bottom:14px; }
.msg-row { display:flex; align-items:flex-end; gap:8px; }
.msg-row.agent { flex-direction:row-reverse; }
.msg-avatar { width:28px; height:28px; border-radius:8px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:900; }
.msg-avatar.user  { background:linear-gradient(135deg,#6c63ff,#3a36d4); color:#fff; }
.msg-avatar.agent { background:linear-gradient(135deg,#FF8A00,#e65c00); color:#fff; }
.msg-bubble { max-width:72%; min-width:0; padding:10px 14px; border-radius:16px; font-size:13px; line-height:1.55; position:relative; word-break:break-word; }
.msg-bubble.user  { background:#fff; color:#1a1d2e; border-radius:4px 16px 16px 16px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
.msg-bubble.agent { background:linear-gradient(135deg,#07003B,#1a0070); color:#fff; border-radius:16px 4px 16px 16px; }
.msg-bubble.deleted { font-style:italic; opacity:.5; }
.msg-meta { font-size:10px; margin-top:4px; opacity:.45; text-align:right; }
.msg-name { font-size:10px; font-weight:700; margin-bottom:3px; color:#6c63ff; }
/* image */
.msg-image { max-width:220px; border-radius:12px; display:block; cursor:pointer; }
/* audio — consistent width across user/agent */
.msg-bubble.has-audio { padding:10px 12px; min-width:220px; }
.msg-audio { display:flex; align-items:center; gap:10px; width:100%; }
.msg-audio-btn { width:34px; height:34px; border-radius:50%; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:13px; flex-shrink:0; transition:opacity .15s; }
.msg-audio-btn:hover { opacity:.8; }
.msg-audio-btn.user  { background:#6c63ff; color:#fff; }
.msg-audio-btn.agent { background:rgba(255,255,255,.25); color:#fff; }
.msg-audio-track { flex:1; display:flex; flex-direction:column; gap:5px; min-width:0; }
.msg-audio-bar { height:4px; background:rgba(0,0,0,.12); border-radius:2px; overflow:hidden; }
.msg-audio-progress { height:100%; background:#6c63ff; width:0; transition:width .1s; border-radius:2px; }
.msg-bubble.agent .msg-audio-bar { background:rgba(255,255,255,.2); }
.msg-bubble.agent .msg-audio-progress { background:rgba(255,255,255,.8); }
.msg-audio-dur { font-size:10px; opacity:.65; font-weight:600; white-space:nowrap; }

/* date divider */
.chat-date { text-align:center; margin:10px 0 6px; }
.chat-date span { background:#d1d5e8; color:#4b5563; font-size:10px; font-weight:700; border-radius:20px; padding:3px 10px; }

/* typing */
.typing-indicator { display:none; align-items:center; gap:8px; margin-bottom:10px; }
.typing-indicator.show { display:flex; }
.typing-dots { display:flex; gap:3px; background:#fff; padding:8px 12px; border-radius:12px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
.typing-dot { width:6px; height:6px; border-radius:50%; background:#6c63ff; animation:td 1.2s infinite; }
.typing-dot:nth-child(2) { animation-delay:.2s; }
.typing-dot:nth-child(3) { animation-delay:.4s; }
@keyframes td { 0%,60%,100%{opacity:.2;transform:scale(1)}30%{opacity:1;transform:scale(1.3)} }

/* new message indicator */
.new-msg-banner { display:none; background:#6c63ff; color:#fff; font-size:12px; font-weight:700; text-align:center; padding:7px; cursor:pointer; flex-shrink:0; }
.new-msg-banner.show { display:block; }

/* input */
.chat-input-row { display:flex; align-items:center; gap:10px; padding:12px 14px; border-top:1px solid #eef0f8; background:#fff; flex-shrink:0; }
.chat-input { flex:1; border:1.5px solid #e2e8f0; border-radius:24px; padding:9px 16px; font-size:13px; resize:none; font-family:inherit; line-height:1.4; max-height:120px; outline:none; transition:border-color .2s; }
.chat-input:focus { border-color:#07003B; }
.chat-send-btn { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,#07003B,#1a0070); color:#fff; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; transition:transform .1s; }
.chat-send-btn:hover { transform:scale(1.08); }
.chat-send-btn:disabled { opacity:.5; cursor:not-allowed; transform:none; }

/* ── Right: sidebar ── */
.chat-sidebar { background:#fafbfd; border-left:1px solid #e8ecf2; display:flex; flex-direction:column; overflow-y:auto; }
.chat-sidebar::-webkit-scrollbar { width:3px; }
.chat-sidebar::-webkit-scrollbar-thumb { background:rgba(7,0,59,.1); border-radius:3px; }

.sb-section { padding:14px 16px; border-bottom:1px solid #f0f2f6; }
.sb-title { font-size:10px; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:.8px; margin-bottom:10px; }
.sb-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; font-size:12px; }
.sb-row:last-child { margin-bottom:0; }
.sb-label { color:#94a3b8; font-weight:600; }
.sb-val { font-weight:700; color:#1a1d2e; }
.sb-avatar { width:44px; height:44px; border-radius:12px; background:linear-gradient(135deg,#6c63ff,#3a36d4); color:#fff; font-size:15px; font-weight:900; display:flex; align-items:center; justify-content:center; margin-bottom:8px; }
.sb-user-name { font-size:14px; font-weight:800; color:#1a1d2e; }
.sb-user-sub  { font-size:11px; color:#94a3b8; margin-top:2px; }
.sb-badge { display:inline-flex; align-items:center; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; }
.sb-badge.open     { background:#eff6ff; color:#2563eb; }
.sb-badge.assigned { background:#f0fdf4; color:#16a34a; }
.sb-badge.resolved { background:#f8fafc; color:#94a3b8; }
.sb-badge.closed   { background:#fef2f2; color:#dc2626; }
.sb-badge.normal   { background:#f1f5f9; color:#64748b; }
.sb-badge.high     { background:#fffbeb; color:#d97706; }
.sb-badge.urgent   { background:#fef2f2; color:#dc2626; }
.sb-select { width:100%; padding:7px 10px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:12px; color:#1a1d2e; background:#fff; margin-top:6px; }
.sb-select:focus { outline:none; border-color:#07003B; }
.sb-action-btn { width:100%; padding:9px; border-radius:10px; font-size:12px; font-weight:700; cursor:pointer; border:1.5px solid; transition:all .15s; margin-bottom:7px; }
.sb-action-btn:last-child { margin-bottom:0; }
.sb-resolve { border-color:#16a34a; color:#16a34a; background:#f0fdf4; }
.sb-resolve:hover { background:#16a34a; color:#fff; }
.sb-close   { border-color:#dc2626; color:#dc2626; background:#fef2f2; }
.sb-close:hover   { background:#dc2626; color:#fff; }
.sb-reopen  { border-color:#2563eb; color:#2563eb; background:#eff6ff; }
.sb-reopen:hover  { background:#2563eb; color:#fff; }

/* ── Call notification ── */
.call-banner { display:none; background:#16a34a; color:#fff; padding:10px 16px; align-items:center; gap:10px; flex-shrink:0; }
.call-banner.show { display:flex; }
.call-banner-text { flex:1; font-size:13px; font-weight:700; }
.call-btn { padding:7px 16px; border-radius:20px; font-size:12px; font-weight:700; cursor:pointer; border:none; }
.call-answer { background:#fff; color:#16a34a; }
.call-decline { background:rgba(255,255,255,.2); color:#fff; }

/* ── Connecting overlay ── */
.call-active-bar { display:none; background:#07003B; color:#fff; padding:8px 16px; align-items:center; gap:10px; flex-shrink:0; }
.call-active-bar.show { display:flex; }
.call-timer { font-size:13px; font-weight:700; letter-spacing:1px; }
.call-mute-btn { background:rgba(255,255,255,.15); border:none; color:#fff; border-radius:8px; padding:5px 12px; font-size:12px; cursor:pointer; }
.call-end-btn { background:#ef4444; border:none; color:#fff; border-radius:8px; padding:5px 14px; font-size:12px; font-weight:700; cursor:pointer; margin-left:auto; }

/* ── Back button ── */
.chat-back { display:flex; align-items:center; gap:8px; padding:12px 16px 0; }
.chat-back a { color:#64748b; text-decoration:none; font-size:13px; font-weight:600; display:flex; align-items:center; gap:6px; transition:color .15s; }
.chat-back a:hover { color:#07003B; }

/* alert */
.chat-closed-bar { background:#fef3c7; border:1px solid #fcd34d; border-radius:8px; padding:9px 14px; font-size:12px; color:#92400e; font-weight:600; margin:10px 16px 0; flex-shrink:0; }

@media(max-width:768px) { .chat-wrap { grid-template-columns:1fr; } .chat-sidebar { display:none; } }
</style>

<div class="chat-back">
    <a href="{{ route('admin.inbox.conversations') }}"><i class="fas fa-arrow-left"></i> Support Tickets</a>
</div>

<div style="padding:10px 0">
<div class="chat-wrap" id="chatWrap">

    {{-- ── Main chat column ── --}}
    <div class="chat-col">

        {{-- Header --}}
        <div class="chat-header">
            <div class="chat-header-avatar">{{ strtoupper(substr($conversation->user->name ?? '?', 0, 2)) }}</div>
            <div>
                <div class="chat-header-name">{{ $conversation->user->name ?? 'Customer' }}</div>
                <div class="chat-header-sub">{{ $conversation->user->phone ?? $conversation->user->email ?? 'No contact' }}</div>
            </div>
            <span class="chat-header-mod">{{ $conversation->module ?? 'general' }}</span>
            <span class="chat-header-badge {{ $conversation->status }}" id="statusBadge">{{ ucfirst($conversation->status) }}</span>
        </div>

        {{-- Call incoming banner --}}
        <div class="call-banner" id="callBanner">
            <i class="fas fa-phone-alt" style="font-size:18px;animation:pulse 1s infinite"></i>
            <span class="call-banner-text">📞 Incoming audio call from customer</span>
            <button class="call-btn call-decline" onclick="declineCall()">Decline</button>
            <button class="call-btn call-answer" onclick="answerCall()">Answer</button>
        </div>

        {{-- Active call bar --}}
        <div class="call-active-bar" id="callActiveBar">
            <i class="fas fa-microphone"></i>
            <span class="call-timer" id="callTimer">00:00</span>
            <button class="call-mute-btn" id="muteBtn" onclick="toggleMute()"><i class="fas fa-microphone"></i> Mute</button>
            <button class="call-end-btn" onclick="endCall()"><i class="fas fa-phone-slash"></i> End</button>
        </div>

        @if($conversation->status === 'closed')
        <div class="chat-closed-bar"><i class="fas fa-lock" style="margin-right:6px"></i>This ticket is closed — replies are disabled.</div>
        @endif

        {{-- New message banner --}}
        <div class="new-msg-banner" id="newMsgBanner" onclick="scrollToBottom()">
            ↓ New messages — click to scroll
        </div>

        {{-- Messages --}}
        <div class="chat-messages" id="msgBox">
            @foreach($messages as $msg)
            @php $isAgent = $msg->sender_type !== 'user'; @endphp
            <div class="msg-group" data-uuid="{{ $msg->uuid }}">
                <div class="msg-row {{ $isAgent ? 'agent' : 'user' }}">
                    <div class="msg-avatar {{ $msg->sender_type }}">
                        {{ strtoupper(substr($msg->sender?->name ?? ($isAgent ? 'A' : 'U'), 0, 1)) }}
                    </div>
                    <div>
                        @if(!$isAgent)
                        <div class="msg-name">{{ $msg->sender?->name ?? 'Customer' }}</div>
                        @endif
                        @php $audioClass = ($msg->type === 'audio' && $msg->media_url) ? ' has-audio' : ''; @endphp
                        <div class="msg-bubble {{ $msg->sender_type }}{{ $audioClass }} {{ $msg->is_deleted ? 'deleted' : '' }}">
                            @if($msg->is_deleted)
                                <i class="fas fa-ban" style="margin-right:5px;opacity:.5"></i>Message deleted
                            @elseif($msg->type === 'image' && $msg->media_url)
                                <img src="{{ $msg->media_url }}" class="msg-image" onclick="window.open(this.src)">
                            @elseif($msg->type === 'audio' && $msg->media_url)
                                <div class="msg-audio">
                                    <button class="msg-audio-btn {{ $isAgent ? 'agent' : 'user' }}" onclick="toggleAudio(this,'{{ $msg->media_url }}','{{ $msg->uuid }}')">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <div class="msg-audio-track">
                                        <div class="msg-audio-bar"><div class="msg-audio-progress" id="prog_{{ $msg->uuid }}"></div></div>
                                        <div class="msg-audio-dur" id="dur_{{ $msg->uuid }}">{{ $msg->media_duration ? gmdate('i:s', $msg->media_duration) : '0:00' }}</div>
                                    </div>
                                </div>
                            @else
                                {{ $msg->content }}
                            @endif
                        </div>
                        <div class="msg-meta">{{ $msg->created_at->format('H:i') }}</div>
                    </div>
                </div>
            </div>
            @endforeach

            {{-- Typing indicator --}}
            <div class="typing-indicator" id="typingIndicator">
                <div class="msg-avatar user">U</div>
                <div class="typing-dots">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>
        </div>

        {{-- Input --}}
        @if($conversation->status !== 'closed')
        <form id="replyForm" class="chat-input-row" onsubmit="sendReply(event)">
            @csrf
            <textarea class="chat-input" id="msgInput" rows="1" placeholder="Type a reply…" onkeydown="handleKey(event)" oninput="autoResize(this)"></textarea>
            <button type="submit" class="chat-send-btn" id="sendBtn">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
        @endif
    </div>

    {{-- ── Sidebar ── --}}
    <div class="chat-sidebar">
        {{-- Customer --}}
        <div class="sb-section">
            <div class="sb-title">Customer</div>
            <div class="sb-avatar">{{ strtoupper(substr($conversation->user->name ?? '?', 0, 2)) }}</div>
            <div class="sb-user-name">{{ $conversation->user->name ?? 'Unknown' }}</div>
            <div class="sb-user-sub">{{ $conversation->user->phone ?? '' }}</div>
            <div class="sb-user-sub">{{ $conversation->user->email ?? '' }}</div>
        </div>

        {{-- Ticket info --}}
        <div class="sb-section">
            <div class="sb-title">Ticket</div>
            <div class="sb-row"><span class="sb-label">Status</span><span class="sb-badge {{ $conversation->status }}" id="sbStatus">{{ ucfirst($conversation->status) }}</span></div>
            <div class="sb-row"><span class="sb-label">Priority</span><span class="sb-badge {{ $conversation->priority }}">{{ ucfirst($conversation->priority) }}</span></div>
            <div class="sb-row"><span class="sb-label">Module</span><span class="sb-val">{{ $conversation->module ?? 'general' }}</span></div>
            <div class="sb-row"><span class="sb-label">Subject</span><span class="sb-val" style="max-width:130px;text-align:right;font-size:11px;line-height:1.3">{{ $conversation->subject }}</span></div>
            <div class="sb-row"><span class="sb-label">Created</span><span class="sb-val">{{ $conversation->created_at->format('M d, H:i') }}</span></div>
        </div>

        {{-- Assign agent --}}
        <div class="sb-section">
            <div class="sb-title">Agent</div>
            @if($conversation->agent)
            <div style="font-size:12px;font-weight:700;color:#1a1d2e;margin-bottom:6px">{{ $conversation->agent->name }}</div>
            @endif
            <form method="POST" action="{{ route('admin.inbox.conversation.assign', $conversation->uuid) }}">
                @csrf
                <select name="agent_id" class="sb-select" onchange="this.form.submit()">
                    <option value="">Unassigned</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}" {{ $conversation->agent_id == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        {{-- Actions --}}
        <div class="sb-section">
            <div class="sb-title">Actions</div>
            @if(!in_array($conversation->status, ['resolved','closed']))
            <form method="POST" action="{{ route('admin.inbox.conversation.status', $conversation->uuid) }}" style="margin-bottom:0">
                @csrf <input type="hidden" name="status" value="resolved">
                <button type="submit" class="sb-action-btn sb-resolve"><i class="fas fa-check"></i> Mark Resolved</button>
            </form>
            @endif
            @if($conversation->status !== 'closed')
            <form method="POST" action="{{ route('admin.inbox.conversation.status', $conversation->uuid) }}" style="margin-bottom:0;margin-top:7px">
                @csrf <input type="hidden" name="status" value="closed">
                <button type="submit" class="sb-action-btn sb-close"><i class="fas fa-times"></i> Close Ticket</button>
            </form>
            @endif
            @if(in_array($conversation->status, ['resolved','closed']))
            <form method="POST" action="{{ route('admin.inbox.conversation.status', $conversation->uuid) }}" style="margin-bottom:0">
                @csrf <input type="hidden" name="status" value="open">
                <button type="submit" class="sb-action-btn sb-reopen"><i class="fas fa-redo"></i> Reopen</button>
            </form>
            @endif
        </div>
    </div>
</div>
</div>

{{-- Hidden audio element for notifications --}}
<audio id="notifSound" preload="auto">
    <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJiVkHBfc5mVlHJic5GPjnBkdpmVkHJnaZWTkXBpaZSTkXBqaZSTknBqaZSTknBqaZOSknBqaZKSknBqaZKSknBqaZKSknBqaZGSkXBqaZCSknBqaZCSkXBraI+RknBsaI+Rk3BtZ46Rk3BuZo2Qk3BvZo2QlHBwZYyPlHBxZIuPlXBzY4qOlXB0YouOlnB2Y4qNlnB3YouNl3B5YouNmHB7YouMmHB9i4uMmXB/i4uMmnCBiouLmnCDiouLm3CFi4uLnHCHjIuKnXCJjYuKnnCLjYuJnnCNjouJn3CPjouIoHCRjouIoXCTj4uIonCVj4uHo3CXkIuHpHCZkIuGpXCbkYuGpnCdk4uGp3Cfk4uFqHChlIuFqXCjlIuEqnCllYuEq3CnlYuDrHCplYuDrXCrl4uDrnCtl4uCsHCvl4uCsXCxmIuBsnCzmIuBs3C1mYuAtnC3mYuAt3C5mYuAuHC7moqAuXC9mouAunC/m4p/u3DBm4p/vHDDm4p+vXDFm4p+vnDHm4p9v3DJnIp9wHDLnIp8wXDNnIp8wnDPnIp7w3DRnYp7xHDTnYp6xXDVnYp6xnDXnYp5x3DZnop5yHDbnop4yXDdnop4ynDfnoB3y3Dhnop3zHDjnop2zXDlnop2znDnnop1z3DpnY" type="audio/wav">
</audio>

{{-- Call audio player --}}
<audio id="callPlayer"></audio>

<script>
const CONV_UUID = '{{ $conversation->uuid }}';
const POLL_URL  = '{{ route("admin.inbox.conversation.poll", $conversation->uuid) }}';
const REPLY_URL = '{{ route("admin.inbox.conversation.reply", $conversation->uuid) }}';
const CSRF      = '{{ csrf_token() }}';

let lastMsgTime = '{{ $messages->last()?->created_at->toISOString() ?? now()->toISOString() }}';
let isAtBottom  = true;
let ringingCallUuid = null;
let callInterval = null;
let callSeconds  = 0;
let peerConn     = null;
let localStream  = null;
let isMuted      = false;

// ── Scroll tracking ──────────────────────────────────────────────────────────
const msgBox = document.getElementById('msgBox');
msgBox.addEventListener('scroll', () => {
    isAtBottom = msgBox.scrollTop + msgBox.clientHeight >= msgBox.scrollHeight - 50;
    if (isAtBottom) document.getElementById('newMsgBanner').classList.remove('show');
});

function scrollToBottom(force = false) {
    if (isAtBottom || force) {
        msgBox.scrollTop = msgBox.scrollHeight;
        document.getElementById('newMsgBanner').classList.remove('show');
    } else {
        document.getElementById('newMsgBanner').classList.add('show');
    }
}

scrollToBottom(true);

// ── Send reply ───────────────────────────────────────────────────────────────
async function sendReply(e) {
    e.preventDefault();
    const input = document.getElementById('msgInput');
    const text  = input.value.trim();
    if (!text) return;

    const btn = document.getElementById('sendBtn');
    btn.disabled = true;
    input.value = '';
    autoResize(input);

    try {
        const fd = new FormData();
        fd.append('content', text);
        fd.append('_token', CSRF);
        const r = await fetch(REPLY_URL, { method:'POST', body:fd });
        const data = await r.json();
        if (data.success) {
            appendMessage(data.message);
            lastMsgTime = data.message.created_at;
            scrollToBottom(true);
        }
    } catch (_) {}
    btn.disabled = false;
    input.focus();
}

function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('replyForm')?.dispatchEvent(new Event('submit'));
    }
}

function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

// ── Append new message ───────────────────────────────────────────────────────
function appendMessage(msg) {
    if (document.querySelector(`[data-uuid="${msg.uuid}"]`)) return;

    const isAgent = msg.sender_type !== 'user';
    const row  = document.createElement('div');
    row.className = 'msg-group';
    row.dataset.uuid = msg.uuid;

    let content = '';
    if (msg.is_deleted) {
        content = '<em style="opacity:.5"><i class="fas fa-ban"></i> Message deleted</em>';
    } else if (msg.type === 'image' && msg.media_url) {
        content = `<img src="${msg.media_url}" class="msg-image" onclick="window.open(this.src)">`;
    } else if (msg.type === 'audio' && msg.media_url) {
        content = `<div class="msg-audio">
            <button class="msg-audio-btn ${msg.sender_type}" onclick="toggleAudio(this,'${msg.media_url}','${msg.uuid}')"><i class="fas fa-play"></i></button>
            <div class="msg-audio-track">
                <div class="msg-audio-bar"><div class="msg-audio-progress" id="prog_${msg.uuid}"></div></div>
                <div class="msg-audio-dur" id="dur_${msg.uuid}">0:00</div>
            </div>
        </div>`;
    } else {
        content = escHtml(msg.content ?? '');
    }

    const audioClass = (msg.type === 'audio' && msg.media_url) ? ' has-audio' : '';
    row.innerHTML = `<div class="msg-row ${isAgent ? 'agent' : 'user'}">
        <div class="msg-avatar ${msg.sender_type}">${(msg.sender_name ?? (isAgent?'A':'U')).charAt(0).toUpperCase()}</div>
        <div>
            ${!isAgent ? `<div class="msg-name">${escHtml(msg.sender_name ?? 'Customer')}</div>` : ''}
            <div class="msg-bubble ${msg.sender_type}${audioClass}">${content}</div>
            <div class="msg-meta">${msg.time ?? ''}</div>
        </div>
    </div>`;

    const typing = document.getElementById('typingIndicator');
    msgBox.insertBefore(row, typing);
    scrollToBottom();

    if (!isAgent) playNotifSound();
}

function escHtml(t) {
    const d = document.createElement('div'); d.textContent = t; return d.innerHTML;
}

// ── Polling ──────────────────────────────────────────────────────────────────
async function poll() {
    try {
        const r = await fetch(`${POLL_URL}?since=${encodeURIComponent(lastMsgTime)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await r.json();

        if (data.messages?.length) {
            data.messages.forEach(m => {
                appendMessage(m);
                lastMsgTime = m.created_at;
            });
        }

        // Ringing call
        if (data.ringing_call && !ringingCallUuid) {
            ringingCallUuid = data.ringing_call.uuid;
            showIncomingCall();
        } else if (!data.ringing_call) {
            ringingCallUuid = null;
        }

        // Update status badge
        if (data.conv_status) updateStatusBadge(data.conv_status);

    } catch (_) {}
}

setInterval(poll, 3000);

// ── Notification sound ───────────────────────────────────────────────────────
function playNotifSound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.25);
    } catch(_) {}
}

// ── Status badge ─────────────────────────────────────────────────────────────
function updateStatusBadge(s) {
    const map = {open:'open',assigned:'assigned',resolved:'resolved',closed:'closed'};
    ['statusBadge','sbStatus'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.className = `chat-header-badge ${map[s]||''}`.trim(); el.textContent = s.charAt(0).toUpperCase()+s.slice(1); }
    });
}

// ── Audio player ─────────────────────────────────────────────────────────────
let currentAudio = null;
function toggleAudio(btn, url, uuid) {
    if (currentAudio && currentAudio.url !== url) {
        currentAudio.audio.pause();
        if (currentAudio.btn) currentAudio.btn.innerHTML = '<i class="fas fa-play"></i>';
        currentAudio = null;
    }
    if (!currentAudio) {
        const audio = new Audio(url);
        audio.ontimeupdate = () => {
            const pct = audio.duration ? audio.currentTime / audio.duration * 100 : 0;
            const prog = document.getElementById('prog_'+uuid);
            const dur  = document.getElementById('dur_'+uuid);
            if (prog) prog.style.width = pct + '%';
            if (dur) { const t = Math.floor(audio.currentTime); dur.textContent = Math.floor(t/60)+':'+(t%60+'').padStart(2,'0'); }
        };
        audio.onended = () => { btn.innerHTML = '<i class="fas fa-play"></i>'; currentAudio = null; };
        currentAudio = { audio, url, btn };
        audio.play();
        btn.innerHTML = '<i class="fas fa-pause"></i>';
    } else {
        if (currentAudio.audio.paused) { currentAudio.audio.play(); btn.innerHTML = '<i class="fas fa-pause"></i>'; }
        else { currentAudio.audio.pause(); btn.innerHTML = '<i class="fas fa-play"></i>'; }
    }
}

// ── Call handling ────────────────────────────────────────────────────────────
function showIncomingCall() {
    document.getElementById('callBanner').classList.add('show');
    playRingTone();
}

let ringInterval = null;
function playRingTone() {
    ringInterval = setInterval(() => {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            [0, 0.15].forEach(offset => {
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.frequency.value = 1200;
                g.gain.setValueAtTime(0.2, ctx.currentTime + offset);
                g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + offset + 0.12);
                o.start(ctx.currentTime + offset);
                o.stop(ctx.currentTime + offset + 0.12);
            });
        } catch(_) {}
    }, 1800);
}

function declineCall() {
    clearInterval(ringInterval);
    ringInterval = null;
    document.getElementById('callBanner').classList.remove('show');
    // Mark call as declined in DB BEFORE clearing uuid
    if (ringingCallUuid) {
        fetch(`/admin/inbox/calls/${ringingCallUuid}/decline`, {
            method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'}
        }).catch(()=>{});
    }
    ringingCallUuid = null;
}

async function answerCall() {
    clearInterval(ringInterval);
    document.getElementById('callBanner').classList.remove('show');
    // For now, show "call connected" bar — real WebRTC would need LiveKit SDK
    startCallTimer();
    document.getElementById('callActiveBar').classList.add('show');
}

function startCallTimer() {
    callSeconds = 0;
    callInterval = setInterval(() => {
        callSeconds++;
        const m = String(Math.floor(callSeconds/60)).padStart(2,'0');
        const s = String(callSeconds%60).padStart(2,'0');
        document.getElementById('callTimer').textContent = `${m}:${s}`;
    }, 1000);
}

function endCall() {
    clearInterval(callInterval);
    clearInterval(ringInterval);
    ringInterval = null;
    document.getElementById('callActiveBar').classList.remove('show');
    document.getElementById('callBanner').classList.remove('show');
    const uuid = ringingCallUuid;
    ringingCallUuid = null;
    if (uuid) {
        fetch(`/admin/inbox/calls/${uuid}/decline`, {
            method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'}
        }).catch(()=>{});
    }
}

function toggleMute() {
    isMuted = !isMuted;
    document.getElementById('muteBtn').innerHTML = isMuted
        ? '<i class="fas fa-microphone-slash"></i> Unmute'
        : '<i class="fas fa-microphone"></i> Mute';
}

// Request browser notification permission
if (Notification.permission === 'default') Notification.requestPermission();

function showBrowserNotif(title, body) {
    if (Notification.permission === 'granted') {
        new Notification(title, { body, icon: '/favicon.ico' });
    }
}
</script>
@endsection
