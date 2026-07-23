@extends('admin.layouts.app')
@section('title', 'Inbox Stats')
@section('content')

<style>
.st-head { margin-bottom:24px; }
.st-head h1 { margin:0; font-size:20px; font-weight:800; color:#1a1d2e; }
.st-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px; }
.st-card { background:#fff; border:1px solid #e8ecf2; border-radius:14px; padding:18px 20px; }
.st-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; margin-bottom:12px; }
.st-val { font-size:30px; font-weight:900; color:#1a1d2e; }
.st-lbl { font-size:12px; color:#94a3b8; margin-top:4px; font-weight:600; }

.st-links { display:flex; gap:12px; flex-wrap:wrap; }
.st-link-card { flex:1; min-width:200px; background:#fff; border:1px solid #e8ecf2; border-radius:14px; padding:16px; text-decoration:none; display:flex; align-items:center; gap:14px; transition:all .15s; }
.st-link-card:hover { border-color:#07003B; transform:translateY(-2px); box-shadow:0 6px 20px rgba(7,0,59,.1); }
.st-link-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.st-link-title { font-size:14px; font-weight:800; color:#1a1d2e; }
.st-link-sub { font-size:12px; color:#94a3b8; margin-top:2px; }
</style>

<div class="st-head">
    <h1><i class="fas fa-chart-bar" style="color:#6c63ff;margin-right:8px"></i>Inbox Stats</h1>
</div>

<div class="st-grid">
    <div class="st-card">
        <div class="st-icon" style="background:#eff6ff;color:#2563eb"><i class="fas fa-inbox"></i></div>
        <div class="st-val">{{ $stats['open_tickets'] }}</div>
        <div class="st-lbl">Open Tickets</div>
    </div>
    <div class="st-card">
        <div class="st-icon" style="background:#fffbeb;color:#d97706"><i class="fas fa-user-slash"></i></div>
        <div class="st-val">{{ $stats['unassigned'] }}</div>
        <div class="st-lbl">Unassigned</div>
    </div>
    <div class="st-card">
        <div class="st-icon" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-check-circle"></i></div>
        <div class="st-val">{{ $stats['resolved_today'] }}</div>
        <div class="st-lbl">Resolved Today</div>
    </div>
    <div class="st-card">
        <div class="st-icon" style="background:#fdf4ff;color:#9333ea"><i class="fas fa-bullhorn"></i></div>
        <div class="st-val">{{ $stats['total_broadcasts'] }}</div>
        <div class="st-lbl">Total Broadcasts</div>
    </div>
</div>

<div class="st-links">
    <a href="{{ route('admin.inbox.conversations') }}" class="st-link-card">
        <div class="st-link-icon" style="background:#eff6ff;color:#2563eb"><i class="fas fa-comments"></i></div>
        <div>
            <div class="st-link-title">Support Tickets</div>
            <div class="st-link-sub">View & reply to customer tickets</div>
        </div>
    </a>
    <a href="{{ route('admin.inbox.broadcasts.index') }}" class="st-link-card">
        <div class="st-link-icon" style="background:#fffbeb;color:#d97706"><i class="fas fa-bullhorn"></i></div>
        <div>
            <div class="st-link-title">Marketing Broadcasts</div>
            <div class="st-link-sub">Send messages to all users</div>
        </div>
    </a>
</div>
@endsection
