@extends('admin.layouts.app')
@section('title', 'Support Tickets')
@section('content')

<style>
.page-hd { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
.page-title { font-size:20px; font-weight:900; color:#1a1d2e; }
.page-sub   { font-size:12px; color:#94a3b8; margin-top:2px; }
.live-dot   { display:inline-block; width:7px; height:7px; border-radius:50%; background:#16a34a; margin-right:5px; animation:livepulse 1.4s infinite; }
@keyframes livepulse { 0%,100%{opacity:1}50%{opacity:.3} }

.stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px; }
.stat-card { background:#fff; border:1px solid #e8ecf2; border-radius:14px; padding:16px 18px; display:flex; align-items:center; gap:14px; }
.stat-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.stat-icon.blue   { background:#eff6ff; color:#2563eb; }
.stat-icon.amber  { background:#fffbeb; color:#d97706; }
.stat-icon.green  { background:#f0fdf4; color:#16a34a; }
.stat-icon.purple { background:#f5f3ff; color:#7c3aed; }
.stat-val { font-size:24px; font-weight:900; color:#1a1d2e; line-height:1; }
.stat-lbl { font-size:11px; color:#94a3b8; font-weight:600; margin-top:3px; }

.filter-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
.filter-tab { padding:7px 16px; border-radius:20px; font-size:12px; font-weight:700; cursor:pointer; border:1.5px solid transparent; background:#f1f5f9; color:#64748b; transition:all .15s; }
.filter-tab.active { background:#07003B; color:#fff; }
.filter-search { flex:1; min-width:200px; padding:8px 14px; border:1.5px solid #e2e8f0; border-radius:20px; font-size:13px; outline:none; }
.filter-search:focus { border-color:#07003B; }

.conv-table-wrap { background:#fff; border:1px solid #e8ecf2; border-radius:16px; overflow:hidden; }
.conv-table { width:100%; border-collapse:collapse; }
.conv-table th { background:#f8fafc; padding:10px 14px; text-align:left; font-size:10px; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:.6px; border-bottom:1px solid #e8ecf2; }
.conv-table td { padding:12px 14px; border-bottom:1px solid #f0f2f6; vertical-align:middle; font-size:13px; }
.conv-table tr:last-child td { border-bottom:none; }
.conv-row { cursor:pointer; }
.conv-row:hover td { background:#fafbfd; }

.user-cell { display:flex; align-items:center; gap:10px; }
.user-av { width:34px; height:34px; border-radius:10px; background:linear-gradient(135deg,#6c63ff,#3a36d4); color:#fff; font-size:12px; font-weight:900; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.user-name { font-weight:700; color:#1a1d2e; }
.user-phone { font-size:11px; color:#94a3b8; margin-top:1px; }
.subj-text { font-weight:600; color:#374151; max-width:220px; }
.subj-mod  { font-size:10px; color:#7c3aed; font-weight:700; background:#f5f3ff; padding:2px 7px; border-radius:20px; margin-top:3px; display:inline-block; }

.badge { display:inline-flex; align-items:center; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; }
.badge.open     { background:#eff6ff; color:#2563eb; }
.badge.assigned { background:#f0fdf4; color:#16a34a; }
.badge.resolved { background:#f8fafc; color:#94a3b8; }
.badge.closed   { background:#fef2f2; color:#dc2626; }
.badge.normal   { background:#f1f5f9; color:#64748b; }
.badge.high     { background:#fffbeb; color:#d97706; }
.badge.urgent   { background:#fef2f2; color:#dc2626; }

.unread-dot { display:inline-block; width:18px; height:18px; border-radius:50%; background:#ef4444; color:#fff; font-size:10px; font-weight:900; text-align:center; line-height:18px; }
.no-unread  { color:#cbd5e1; }
.agent-text { font-size:12px; font-weight:600; color:#374151; }
.no-agent   { font-size:11px; color:#94a3b8; }
.time-text  { font-size:11px; color:#94a3b8; }

.notif-toast { position:fixed; bottom:24px; right:24px; background:#07003B; color:#fff; border-radius:14px; padding:14px 18px; display:flex; align-items:center; gap:12px; z-index:9999; box-shadow:0 8px 32px rgba(7,0,59,.25); transform:translateY(80px); opacity:0; transition:all .3s; max-width:320px; }
.notif-toast.show { transform:translateY(0); opacity:1; }
.notif-toast-icon { width:36px; height:36px; border-radius:10px; background:rgba(255,255,255,.12); display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.notif-toast-title { font-weight:800; font-size:13px; }
.notif-toast-body  { font-size:11px; color:rgba(255,255,255,.6); margin-top:2px; }
.notif-close { background:rgba(255,255,255,.1); border:none; color:#fff; border-radius:6px; padding:3px 8px; cursor:pointer; font-size:12px; margin-left:auto; }

.empty-state { text-align:center; padding:50px 20px; }
.empty-state i { font-size:40px; color:#cbd5e1; margin-bottom:12px; }
.empty-state p { color:#94a3b8; font-size:13px; }
.pag-wrap { padding:12px 16px; border-top:1px solid #f0f2f6; display:flex; justify-content:center; }

@media(max-width:900px) { .stats-row { grid-template-columns:1fr 1fr; } .conv-table th:nth-child(n+5),.conv-table td:nth-child(n+5) { display:none; } }
@media(max-width:600px) { .stats-row { grid-template-columns:1fr; } }
</style>

<div class="notif-toast" id="notifToast">
    <div class="notif-toast-icon"><i class="fas fa-headset"></i></div>
    <div>
        <div class="notif-toast-title">New Support Ticket</div>
        <div class="notif-toast-body">A customer just submitted a support request</div>
    </div>
    <button class="notif-close" onclick="this.closest('.notif-toast').classList.remove('show')">✕</button>
</div>

<div class="page-hd">
    <div>
        <div class="page-title"><span class="live-dot"></span>Support Tickets</div>
        <div class="page-sub">Customer support conversations — updates every 5 seconds</div>
    </div>
</div>

<div class="stats-row">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-ticket-alt"></i></div>
        <div><div class="stat-val" id="statOpen">{{ $stats['open_tickets'] }}</div><div class="stat-lbl">Open &amp; Assigned</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-user-clock"></i></div>
        <div><div class="stat-val" id="statUnassigned">{{ $stats['unassigned'] }}</div><div class="stat-lbl">Awaiting Agent</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div><div class="stat-val">{{ $stats['resolved_today'] }}</div><div class="stat-lbl">Resolved Today</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-broadcast-tower"></i></div>
        <div><div class="stat-val">{{ $stats['total_broadcasts'] }}</div><div class="stat-lbl">Broadcasts Sent</div></div>
    </div>
</div>

<div class="filter-row">
    <button class="filter-tab {{ !request('status') ? 'active' : '' }}" onclick="filterStatus('')">All</button>
    <button class="filter-tab {{ request('status')==='open' ? 'active' : '' }}" onclick="filterStatus('open')">Open</button>
    <button class="filter-tab {{ request('status')==='assigned' ? 'active' : '' }}" onclick="filterStatus('assigned')">Assigned</button>
    <button class="filter-tab {{ request('status')==='resolved' ? 'active' : '' }}" onclick="filterStatus('resolved')">Resolved</button>
    <button class="filter-tab {{ request('status')==='closed' ? 'active' : '' }}" onclick="filterStatus('closed')">Closed</button>
    <input class="filter-search" type="text" placeholder="Search by name, subject…" id="searchInput" value="{{ request('q') }}" oninput="debounceSearch(this.value)">
</div>

<div class="conv-table-wrap">
    @if($convs->isEmpty())
    <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <p>No support tickets yet.</p>
    </div>
    @else
    <table class="conv-table">
        <thead>
            <tr>
                <th>Customer</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Agent</th>
                <th>Unread</th>
                <th>Last Activity</th>
            </tr>
        </thead>
        <tbody>
            @foreach($convs as $conv)
            <tr class="conv-row" onclick="window.location='{{ route('admin.inbox.conversation.messages', $conv->uuid) }}'">
                <td>
                    <div class="user-cell">
                        <div class="user-av">{{ strtoupper(substr($conv->user?->name ?? '?', 0, 2)) }}</div>
                        <div>
                            <div class="user-name">{{ $conv->user?->name ?? 'Unknown' }}</div>
                            <div class="user-phone">{{ $conv->user?->phone ?? $conv->user?->email ?? '—' }}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="subj-text">{{ Str::limit($conv->subject, 45) }}</div>
                    @if($conv->module)<span class="subj-mod">{{ $conv->module }}</span>@endif
                </td>
                <td><span class="badge {{ $conv->status }}">{{ ucfirst($conv->status) }}</span></td>
                <td><span class="badge {{ $conv->priority }}">{{ ucfirst($conv->priority) }}</span></td>
                <td>
                    @if($conv->agent)
                        <span class="agent-text">{{ $conv->agent->name }}</span>
                    @else
                        <span class="no-agent">Unassigned</span>
                    @endif
                </td>
                <td>
                    @if($conv->unread_agent > 0)
                        <span class="unread-dot">{{ $conv->unread_agent }}</span>
                    @else
                        <span class="no-unread">—</span>
                    @endif
                </td>
                <td><span class="time-text">{{ $conv->last_message_at ? $conv->last_message_at->diffForHumans() : $conv->created_at->diffForHumans() }}</span></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if($convs->hasPages())
    <div class="pag-wrap">{{ $convs->links() }}</div>
    @endif
    @endif
</div>

<script>
let lastNewTickets = 0;
const GLOBAL_POLL = '{{ route("admin.inbox.global-poll") }}';

async function globalPoll() {
    try {
        const r = await fetch(GLOBAL_POLL, { headers:{'X-Requested-With':'XMLHttpRequest'} });
        const d = await r.json();
        if (d.new_tickets > lastNewTickets && lastNewTickets !== 0) showNewTicketToast();
        lastNewTickets = d.new_tickets;
    } catch(_) {}
}

function showNewTicketToast() {
    document.getElementById('notifToast').classList.add('show');
    playNotif();
    setTimeout(() => document.getElementById('notifToast').classList.remove('show'), 8000);
    if (Notification.permission === 'granted') {
        new Notification('New Support Ticket', { body:'A customer submitted a support request', icon:'/favicon.ico' });
    }
}

function playNotif() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        [880, 1100].forEach((freq, i) => {
            const o = ctx.createOscillator(), g = ctx.createGain();
            o.connect(g); g.connect(ctx.destination);
            o.frequency.value = freq;
            const t = ctx.currentTime + i * 0.18;
            g.gain.setValueAtTime(0.25, t);
            g.gain.exponentialRampToValueAtTime(0.001, t + 0.18);
            o.start(t); o.stop(t + 0.18);
        });
    } catch(_) {}
}

function filterStatus(s) {
    const u = new URL(window.location);
    s ? u.searchParams.set('status', s) : u.searchParams.delete('status');
    window.location = u;
}

let searchTimer;
function debounceSearch(v) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const u = new URL(window.location);
        v ? u.searchParams.set('q', v) : u.searchParams.delete('q');
        window.location = u;
    }, 500);
}

setInterval(globalPoll, 5000);
globalPoll();
if (Notification.permission === 'default') Notification.requestPermission();
</script>
@endsection
