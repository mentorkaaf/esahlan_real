@extends('admin.layouts.app')
@section('title', 'Support Tickets')
@section('content')

<style>
.ib-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
.ib-head h1 { margin:0; font-size:20px; font-weight:800; color:#1a1d2e; }
.ib-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px; }
.ib-stat { background:#fff; border:1px solid #e8ecf2; border-radius:12px; padding:14px 16px; display:flex; align-items:center; gap:12px; }
.ib-stat-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.ib-stat-val { font-size:22px; font-weight:800; color:#1a1d2e; }
.ib-stat-lbl { font-size:11px; color:#94a3b8; margin-top:1px; }

.ib-filters { background:#fff; border:1px solid #e8ecf2; border-radius:12px; padding:14px 16px; margin-bottom:16px; display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
.ib-filter-btn { padding:6px 14px; border:1.5px solid #e2e8f0; border-radius:20px; background:#fff; font-size:12px; font-weight:600; color:#64748b; cursor:pointer; transition:all .15s; }
.ib-filter-btn.active, .ib-filter-btn:hover { border-color:#07003B; color:#07003B; background:rgba(7,0,59,.06); }
.ib-search { flex:1; min-width:180px; padding:7px 12px; border:1.5px solid #e2e8f0; border-radius:9px; font-size:13px; color:#1a1d2e; outline:none; }
.ib-search:focus { border-color:#07003B; }

.ib-table-wrap { background:#fff; border:1px solid #e8ecf2; border-radius:14px; overflow:hidden; }
.ib-table { width:100%; border-collapse:collapse; font-size:13px; }
.ib-table th { padding:11px 16px; background:#fafbfd; border-bottom:1px solid #f0f2f6; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.5px; text-align:left; }
.ib-table td { padding:13px 16px; border-bottom:1px solid #f7f8fa; vertical-align:middle; color:#1a1d2e; }
.ib-table tr:last-child td { border-bottom:none; }
.ib-table tr:hover td { background:#f8f9fe; }

.ib-badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; white-space:nowrap; }
.ib-badge.open     { background:#eff6ff; color:#2563eb; }
.ib-badge.assigned { background:#f0fdf4; color:#16a34a; }
.ib-badge.resolved { background:#f8fafc; color:#94a3b8; }
.ib-badge.closed   { background:#fef2f2; color:#dc2626; }
.ib-badge.normal   { background:#f8fafc; color:#64748b; }
.ib-badge.high     { background:#fffbeb; color:#d97706; }
.ib-badge.urgent   { background:#fef2f2; color:#dc2626; }

.ib-module-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; background:#f1f5f9; border-radius:6px; font-size:11px; font-weight:600; color:#475569; }
.ib-btn { padding:5px 12px; border-radius:7px; font-size:12px; font-weight:600; cursor:pointer; border:none; transition:all .15s; }
.ib-btn-primary { background:#07003B; color:#fff; }
.ib-btn-primary:hover { background:#140465; }
.ib-avatar { width:32px; height:32px; border-radius:8px; background:linear-gradient(135deg,#6c63ff,#3a36d4); color:#fff; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; flex-shrink:0; }
.ib-user { display:flex; align-items:center; gap:9px; }
.ib-user-name { font-weight:700; font-size:13px; }
.ib-user-sub { font-size:11px; color:#94a3b8; margin-top:1px; }
.ib-subject { font-weight:600; max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ib-last-msg { font-size:12px; color:#64748b; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ib-unread { background:#FF8A00; color:#fff; border-radius:50%; width:18px; height:18px; font-size:10px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; }
.ib-empty { text-align:center; padding:60px 20px; color:#94a3b8; }
.ib-empty i { font-size:40px; margin-bottom:12px; display:block; }
</style>

<div class="ib-head">
    <h1><i class="fas fa-headset" style="color:#6c63ff;margin-right:8px"></i>Support Tickets</h1>
</div>

{{-- Stats --}}
<div class="ib-stats">
    <div class="ib-stat">
        <div class="ib-stat-icon" style="background:#eff6ff;color:#2563eb"><i class="fas fa-inbox"></i></div>
        <div>
            <div class="ib-stat-val">{{ $stats['open_tickets'] }}</div>
            <div class="ib-stat-lbl">Open</div>
        </div>
    </div>
    <div class="ib-stat">
        <div class="ib-stat-icon" style="background:#fffbeb;color:#d97706"><i class="fas fa-clock"></i></div>
        <div>
            <div class="ib-stat-val">{{ $stats['unassigned'] }}</div>
            <div class="ib-stat-lbl">Unassigned</div>
        </div>
    </div>
    <div class="ib-stat">
        <div class="ib-stat-icon" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="ib-stat-val">{{ $stats['resolved_today'] }}</div>
            <div class="ib-stat-lbl">Resolved Today</div>
        </div>
    </div>
    <div class="ib-stat">
        <div class="ib-stat-icon" style="background:#fdf4ff;color:#9333ea"><i class="fas fa-bullhorn"></i></div>
        <div>
            <div class="ib-stat-val">{{ $stats['total_broadcasts'] }}</div>
            <div class="ib-stat-lbl">Broadcasts Sent</div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="ib-filters">
    <input type="text" class="ib-search" id="searchInput" placeholder="Search by user or subject…" oninput="filterTable()">
    <button class="ib-filter-btn active" data-status="all"     onclick="setStatus(this)">All</button>
    <button class="ib-filter-btn"        data-status="open"    onclick="setStatus(this)">Open</button>
    <button class="ib-filter-btn"        data-status="assigned" onclick="setStatus(this)">Assigned</button>
    <button class="ib-filter-btn"        data-status="resolved" onclick="setStatus(this)">Resolved</button>
    <button class="ib-filter-btn"        data-status="closed"  onclick="setStatus(this)">Closed</button>
</div>

{{-- Table --}}
<div class="ib-table-wrap">
    <table class="ib-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Subject</th>
                <th>Module</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Agent</th>
                <th>Last Activity</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="convTable">
            @forelse($conversations as $conv)
            <tr data-status="{{ $conv->status }}" data-search="{{ strtolower($conv->user->name ?? '') }} {{ strtolower($conv->subject ?? '') }}">
                <td>
                    <div class="ib-user">
                        <div class="ib-avatar">{{ strtoupper(substr($conv->user->name ?? '?', 0, 2)) }}</div>
                        <div>
                            <div class="ib-user-name">{{ $conv->user->name ?? 'Unknown' }}</div>
                            <div class="ib-user-sub">{{ $conv->user->phone ?? $conv->user->email ?? '' }}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="ib-subject">{{ $conv->subject ?? '(no subject)' }}</div>
                    @if($conv->unread_agent > 0)
                        <span class="ib-unread">{{ $conv->unread_agent }}</span>
                    @endif
                    <div class="ib-last-msg">{{ $conv->last_message }}</div>
                </td>
                <td><span class="ib-module-pill">{{ $conv->module ?? 'general' }}</span></td>
                <td><span class="ib-badge {{ $conv->status }}">{{ ucfirst($conv->status) }}</span></td>
                <td><span class="ib-badge {{ $conv->priority }}">{{ ucfirst($conv->priority) }}</span></td>
                <td>
                    @if($conv->agent)
                        <div class="ib-user-name" style="font-size:12px">{{ $conv->agent->name }}</div>
                    @else
                        <span style="color:#94a3b8;font-size:12px">Unassigned</span>
                    @endif
                </td>
                <td style="font-size:12px;color:#94a3b8;white-space:nowrap">
                    {{ $conv->updated_at?->diffForHumans() }}
                </td>
                <td>
                    <a href="{{ route('admin.inbox.conversation.messages', $conv->uuid) }}" class="ib-btn ib-btn-primary">
                        <i class="fas fa-comment"></i> Reply
                    </a>
                </td>
            </tr>
            @empty
            <tr><td colspan="8">
                <div class="ib-empty">
                    <i class="fas fa-inbox"></i>
                    No support tickets yet
                </div>
            </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $conversations->links() }}

<script>
let activeStatus = 'all';
function setStatus(btn) {
    document.querySelectorAll('.ib-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    activeStatus = btn.dataset.status;
    filterTable();
}
function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#convTable tr[data-status]').forEach(row => {
        const matchStatus = activeStatus === 'all' || row.dataset.status === activeStatus;
        const matchSearch = !q || row.dataset.search.includes(q);
        row.style.display = matchStatus && matchSearch ? '' : 'none';
    });
}
</script>
@endsection
