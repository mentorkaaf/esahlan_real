@extends('admin.layouts.app')
@section('title', 'Community Users')
@section('content')

<style>
.stat-card { border-radius:14px; padding:20px 24px; display:flex; align-items:center; gap:16px; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.08); }
.stat-card .icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.stat-card .label { font-size:12px; color:#6b7280; font-weight:500; text-transform:uppercase; letter-spacing:.5px; }
.stat-card .value { font-size:26px; font-weight:700; color:#111; line-height:1.1; }
.stat-card .sub { font-size:11px; color:#9ca3af; margin-top:2px; }
.tab-bar { display:flex; gap:4px; background:#f3f4f6; border-radius:12px; padding:4px; margin-bottom:24px; max-width:440px; }
.tab-bar button { flex:1; padding:8px 16px; border:none; border-radius:9px; font-size:14px; font-weight:500; cursor:pointer; background:transparent; color:#6b7280; transition:all .15s; }
.tab-bar button.active { background:#fff; color:#111; box-shadow:0 1px 3px rgba(0,0,0,.12); }
.filter-bar { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:20px; }
.filter-bar input, .filter-bar select { border:1.5px solid #e5e7eb; border-radius:9px; padding:8px 12px; font-size:13px; color:#111; background:#fff; outline:none; }
.filter-bar input:focus, .filter-bar select:focus { border-color:#6366f1; }
.filter-bar .btn-filter { background:#6366f1; color:#fff; border:none; border-radius:9px; padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer; }
.users-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:16px; }
.user-card { background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.07); cursor:pointer; transition:box-shadow .15s, transform .15s; border:1.5px solid transparent; }
.user-card:hover { box-shadow:0 6px 20px rgba(0,0,0,.12); transform:translateY(-2px); border-color:#e0e7ff; }
.user-card .cover { height:60px; position:relative; }
.user-card .avatar-wrap { position:absolute; bottom:-22px; left:16px; }
.user-card .avatar-wrap img, .user-card .av-init { width:44px; height:44px; border-radius:50%; border:3px solid #fff; object-fit:cover; }
.user-card .av-init { background:#6366f1; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; }
.user-card .card-body { padding:30px 16px 14px; }
.user-card .name { font-weight:700; font-size:15px; color:#111; display:flex; align-items:center; gap:5px; }
.user-card .username { font-size:12px; color:#6b7280; margin-bottom:8px; }
.user-card .badges { display:flex; gap:4px; flex-wrap:wrap; margin-bottom:10px; }
.badge-sm { font-size:10px; padding:2px 8px; border-radius:20px; font-weight:600; }
.badge-verified { background:#dbeafe; color:#1d4ed8; }
.badge-business { background:#fef3c7; color:#92400e; }
.badge-gender { background:#f3e8ff; color:#7c3aed; }
.badge-country { background:#dcfce7; color:#166534; }
.user-card .stats-row { display:flex; gap:12px; border-top:1px solid #f3f4f6; padding-top:10px; margin-top:4px; }
.user-card .stat { text-align:center; flex:1; }
.user-card .stat .n { font-size:14px; font-weight:700; color:#111; }
.user-card .stat .l { font-size:10px; color:#9ca3af; }
.user-card .card-actions { padding:0 16px 14px; display:flex; gap:6px; }
.btn-xs { flex:1; padding:6px; font-size:11px; font-weight:600; border:none; border-radius:8px; cursor:pointer; }
.btn-xs.btn-view { background:#ede9fe; color:#6d28d9; }
.btn-xs.btn-verify { background:#dcfce7; color:#15803d; }
.btn-xs.btn-verify.is-verified { background:#fef2f2; color:#b91c1c; }
.btn-xs.btn-chat { background:#dbeafe; color:#1d4ed8; }
.slide-panel { position:fixed; top:0; right:-500px; width:480px; height:100vh; background:#fff; z-index:9999; box-shadow:-4px 0 30px rgba(0,0,0,.15); transition:right .25s ease; display:flex; flex-direction:column; }
.slide-panel.open { right:0; }
.panel-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:9998; }
.panel-overlay.open { display:block; }
.panel-header { padding:20px; border-bottom:1px solid #f3f4f6; display:flex; align-items:center; gap:12px; }
.panel-header h3 { font-size:16px; font-weight:700; margin:0; flex:1; }
.panel-close { background:none; border:none; font-size:20px; cursor:pointer; color:#6b7280; }
.panel-body { flex:1; overflow-y:auto; padding:20px; }
.panel-section { margin-bottom:20px; }
.panel-section h4 { font-size:12px; text-transform:uppercase; letter-spacing:.6px; color:#9ca3af; font-weight:600; margin-bottom:10px; }
.info-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.info-item { background:#f9fafb; border-radius:8px; padding:8px 12px; }
.info-item .key { font-size:10px; color:#9ca3af; text-transform:uppercase; letter-spacing:.4px; }
.info-item .val { font-size:13px; font-weight:600; color:#111; margin-top:2px; }
.mini-post { display:flex; gap:10px; align-items:center; padding:8px; background:#f9fafb; border-radius:8px; margin-bottom:6px; }
.mini-post .meta { font-size:11px; color:#6b7280; }
.mini-post .content-text { font-size:12px; color:#374151; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:240px; }
.chat-row { background:#fff; border-radius:12px; border:1.5px solid #e5e7eb; padding:12px 16px; display:flex; align-items:center; gap:12px; cursor:pointer; transition:border-color .15s; margin-bottom:8px; }
.chat-row:hover, .chat-row.selected { border-color:#6366f1; background:#f5f3ff; }
.chat-avatars { display:flex; }
.chat-av { width:36px; height:36px; border-radius:50%; border:2px solid #fff; object-fit:cover; margin-right:-10px; background:#e0e7ff; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#6366f1; }
.chat-row .chat-meta { flex:1; min-width:0; }
.chat-row .chat-names { font-size:13px; font-weight:600; color:#111; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.chat-row .chat-last { font-size:11px; color:#9ca3af; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px; }
.chat-row .chat-right { text-align:right; flex-shrink:0; }
.chat-row .chat-cnt { font-size:11px; color:#6b7280; }
.chat-row .chat-time { font-size:10px; color:#d1d5db; margin-top:2px; }
.msg-thread { display:flex; flex-direction:column; gap:8px; overflow-y:auto; padding:12px; background:#f8f9fa; border-radius:12px; flex:1; }
.msg-wrap { display:flex; gap:8px; align-items:flex-start; }
.msg-wrap.right { flex-direction:row-reverse; }
.msg-av { width:28px; height:28px; border-radius:50%; flex-shrink:0; background:#e0e7ff; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; color:#6366f1; overflow:hidden; }
.msg-av img { width:100%; height:100%; object-fit:cover; }
.msg-bubble { max-width:72%; padding:8px 12px; border-radius:12px; font-size:12px; line-height:1.5; }
.msg-bubble.left { background:#fff; color:#111; border-bottom-left-radius:4px; box-shadow:0 1px 3px rgba(0,0,0,.08); }
.msg-bubble.right-b { background:#6366f1; color:#fff; border-bottom-right-radius:4px; }
.msg-sender { font-size:9px; font-weight:600; color:#6b7280; margin-bottom:2px; }
.msg-bubble.right-b .msg-sender { color:rgba(255,255,255,.7); }
.msg-time { font-size:9px; color:#d1d5db; margin-top:3px; text-align:right; }
.msg-deleted-text { font-style:italic; color:#9ca3af !important; }
.msg-del-btn { background:#fee2e2; color:#b91c1c; border:none; border-radius:6px; padding:3px 7px; font-size:10px; cursor:pointer; align-self:center; display:none; }
.msg-wrap:hover .msg-del-btn { display:block; }
.country-bar { margin-bottom:8px; }
.country-bar .lrow { display:flex; justify-content:space-between; font-size:11px; color:#6b7280; margin-bottom:2px; }
.country-bar .track { background:#f3f4f6; border-radius:4px; height:6px; }
.country-bar .fill { background:#6366f1; border-radius:4px; height:6px; }
.realtime-dot { width:8px; height:8px; background:#10b981; border-radius:50%; display:inline-block; animation:pulse2 1.5s infinite; }
@keyframes pulse2 { 0%,100%{opacity:1;} 50%{opacity:.35;} }
.spinner2 { border:2px solid #e5e7eb; border-top:2px solid #6366f1; border-radius:50%; width:20px; height:20px; animation:spin2 .6s linear infinite; display:inline-block; }
@keyframes spin2 { to { transform:rotate(360deg); } }
</style>

<div style="padding:24px;">

{{-- Header --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
    <div>
        <h1 style="font-size:22px;font-weight:800;color:#111;margin:0;">Community Users</h1>
        <p style="font-size:13px;color:#6b7280;margin:4px 0 0;">Full analytics &amp; real-time chat monitoring</p>
    </div>
</div>

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:12px;margin-bottom:24px;">
    <div class="stat-card">
        <div class="icon" style="background:#ede9fe;"><i class="fas fa-users" style="color:#7c3aed;"></i></div>
        <div><div class="label">Total</div><div class="value">{{ number_format($stats['total']) }}</div></div>
    </div>
    <div class="stat-card">
        <div class="icon" style="background:#dbeafe;"><i class="fas fa-check-circle" style="color:#2563eb;"></i></div>
        <div><div class="label">Verified</div><div class="value">{{ number_format($stats['verified']) }}</div>
        <div class="sub">{{ $stats['total'] > 0 ? round($stats['verified']/$stats['total']*100,1) : 0 }}%</div></div>
    </div>
    <div class="stat-card">
        <div class="icon" style="background:#dcfce7;"><i class="fas fa-user-plus" style="color:#16a34a;"></i></div>
        <div><div class="label">New Week</div><div class="value">{{ number_format($stats['new_week']) }}</div></div>
    </div>
    <div class="stat-card">
        <div class="icon" style="background:#fef3c7;"><i class="fas fa-flag" style="color:#d97706;"></i></div>
        <div><div class="label">Onboarded</div><div class="value">{{ number_format($stats['onboarded']) }}</div></div>
    </div>
    <div class="stat-card">
        <div class="icon" style="background:#fce7f3;"><i class="fas fa-venus-mars" style="color:#be185d;"></i></div>
        <div><div class="label">Gender</div>
        <div class="value" style="font-size:14px;"><span style="color:#3b82f6;">M {{ $stats['male'] }}</span> / <span style="color:#ec4899;">F {{ $stats['female'] }}</span></div>
        <div class="sub">Other: {{ $stats['other_gender'] }}</div></div>
    </div>
</div>

{{-- Tabs --}}
<div class="tab-bar">
    <button class="active" id="tab-users" onclick="switchTab('users')"><i class="fas fa-users"></i> Users</button>
    <button id="tab-analytics" onclick="switchTab('analytics')"><i class="fas fa-chart-pie"></i> Analytics</button>
    <button id="tab-chat" onclick="switchTab('chat')"><i class="fas fa-comments"></i> Chat Monitor <span class="realtime-dot" style="margin-left:4px;vertical-align:middle;"></span></button>
</div>

{{-- ══ USERS TAB ══ --}}
<div id="pane-users">
    <form method="GET" action="{{ route('admin.community.users') }}" class="filter-bar">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, email, username...">
        <select name="verified">
            <option value="">All Verified</option>
            <option value="1" @selected(request('verified')==='1')>Verified</option>
            <option value="0" @selected(request('verified')==='0')>Not Verified</option>
        </select>
        <select name="gender">
            <option value="">All Genders</option>
            <option value="male" @selected(request('gender')==='male')>Male</option>
            <option value="female" @selected(request('gender')==='female')>Female</option>
            <option value="other" @selected(request('gender')==='other')>Other</option>
        </select>
        <select name="country">
            <option value="">All Countries</option>
            @foreach($countries as $c => $cnt)
                <option value="{{ $c }}" @selected(request('country')===$c)>{{ $c }} ({{ $cnt }})</option>
            @endforeach
        </select>
        <select name="onboarded">
            <option value="">All</option>
            <option value="1" @selected(request('onboarded')==='1')>Onboarded</option>
            <option value="0" @selected(request('onboarded')==='0')>Not Onboarded</option>
        </select>
        <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
        <a href="{{ route('admin.community.users') }}" style="font-size:12px;color:#6b7280;text-decoration:none;">Clear</a>
    </form>

    <p style="font-size:12px;color:#9ca3af;margin-bottom:16px;">
        Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }} users
    </p>

    <div class="users-grid">
        @forelse($users as $profile)
        @php
            $u = $profile->user;
            $initials = strtoupper(substr($u->name ?? 'U', 0, 1));
            $age = $profile->date_of_birth ? (int)\Carbon\Carbon::parse($profile->date_of_birth)->diffInYears(now()) : null;
            $colors = ['#6366f1','#8b5cf6','#0ea5e9','#10b981','#f59e0b','#ef4444'];
            $colors2 = ['#8b5cf6','#ec4899','#0284c7','#059669','#d97706','#dc2626'];
            $c1 = $colors[abs(crc32($u->name ?? '')) % 6];
            $c2 = $colors2[abs(crc32($u->email ?? '')) % 6];
        @endphp
        <div class="user-card" onclick="openUserPanel({{ $profile->id }}, {{ $profile->user_id }})">
            <div class="cover" style="background:linear-gradient(135deg,{{ $c1 }},{{ $c2 }});">
                @if($profile->cover_photo)
                <img src="{{ asset('storage/'.$profile->cover_photo) }}" style="width:100%;height:100%;object-fit:cover;" alt="">
                @endif
                <div class="avatar-wrap">
                    @if($u && $u->avatar)
                    <img src="{{ $u->avatar }}" alt="">
                    @else
                    <div class="av-init">{{ $initials }}</div>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="name">
                    {{ $u->name ?? 'Unknown' }}
                    @if($profile->is_verified)<i class="fas fa-check-circle" style="color:#2563eb;font-size:12px;"></i>@endif
                </div>
                <div class="username">
                    @if($profile->username)@{{ $profile->username }} · @endif
                    {{ $u->email ?? '' }}
                </div>
                <div class="badges">
                    @if($profile->is_verified)<span class="badge-sm badge-verified">Verified</span>@endif
                    @if($profile->is_business)<span class="badge-sm badge-business">Business</span>@endif
                    @if($profile->gender)<span class="badge-sm badge-gender">{{ ucfirst($profile->gender) }}{{ $age ? ' · '.$age.'y' : '' }}</span>@endif
                    @if($profile->country)<span class="badge-sm badge-country">{{ $profile->country }}{{ $profile->city ? ', '.$profile->city : '' }}</span>@endif
                    @if(!$profile->onboarding_completed)<span class="badge-sm" style="background:#f3f4f6;color:#9ca3af;">Not Onboarded</span>@endif
                </div>
                <div class="stats-row">
                    <div class="stat"><div class="n">{{ number_format($profile->posts_count) }}</div><div class="l">Posts</div></div>
                    <div class="stat"><div class="n">{{ number_format($profile->followers_count ?? 0) }}</div><div class="l">Followers</div></div>
                    <div class="stat"><div class="n">{{ number_format($profile->following_count ?? 0) }}</div><div class="l">Following</div></div>
                </div>
            </div>
            <div class="card-actions">
                <button class="btn-xs btn-view" onclick="event.stopPropagation();openUserPanel({{ $profile->id }},{{ $profile->user_id }})"><i class="fas fa-eye"></i> View</button>
                <button class="btn-xs btn-verify {{ $profile->is_verified ? 'is-verified' : '' }}" id="vbtn-{{ $profile->id }}" onclick="event.stopPropagation();toggleVerify({{ $profile->id }}, this)">
                    <i class="fas fa-{{ $profile->is_verified ? 'times' : 'check' }}"></i> {{ $profile->is_verified ? 'Unverify' : 'Verify' }}
                </button>
                <button class="btn-xs btn-chat" onclick="event.stopPropagation();openUserChats({{ $profile->user_id }})"><i class="fas fa-comment"></i> Chats</button>
            </div>
        </div>
        @empty
        <div style="grid-column:1/-1;text-align:center;padding:60px 0;color:#9ca3af;">
            <i class="fas fa-users" style="font-size:32px;display:block;margin-bottom:12px;"></i>No users found
        </div>
        @endforelse
    </div>

    <div style="display:flex;justify-content:center;margin-top:24px;">
        {{ $users->withQueryString()->links() }}
    </div>
</div>

{{-- ══ ANALYTICS TAB ══ --}}
<div id="pane-analytics" style="display:none;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <div style="background:#fff;border-radius:16px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.07);">
            <h3 style="font-size:14px;font-weight:700;margin:0 0 16px;">Gender Distribution</h3>
            <canvas id="genderChart" width="180" height="180" style="display:block;margin:0 auto;"></canvas>
            <div style="display:flex;justify-content:center;gap:16px;margin-top:12px;font-size:12px;flex-wrap:wrap;">
                <span><span style="display:inline-block;width:10px;height:10px;background:#3b82f6;border-radius:2px;margin-right:4px;"></span>Male ({{ $stats['male'] }})</span>
                <span><span style="display:inline-block;width:10px;height:10px;background:#ec4899;border-radius:2px;margin-right:4px;"></span>Female ({{ $stats['female'] }})</span>
                <span><span style="display:inline-block;width:10px;height:10px;background:#a78bfa;border-radius:2px;margin-right:4px;"></span>Other ({{ $stats['other_gender'] }})</span>
            </div>
        </div>
        <div style="background:#fff;border-radius:16px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.07);">
            <h3 style="font-size:14px;font-weight:700;margin:0 0 16px;">Verification Status</h3>
            <canvas id="verifyChart" width="180" height="180" style="display:block;margin:0 auto;"></canvas>
            <div style="display:flex;justify-content:center;gap:16px;margin-top:12px;font-size:12px;">
                <span><span style="display:inline-block;width:10px;height:10px;background:#10b981;border-radius:2px;margin-right:4px;"></span>Verified ({{ $stats['verified'] }})</span>
                <span><span style="display:inline-block;width:10px;height:10px;background:#e5e7eb;border-radius:2px;margin-right:4px;"></span>Unverified ({{ $stats['total'] - $stats['verified'] }})</span>
            </div>
        </div>
        <div style="background:#fff;border-radius:16px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.07);">
            <h3 style="font-size:14px;font-weight:700;margin:0 0 16px;">Top Countries</h3>
            @php $maxCnt = $countries->max() ?: 1; @endphp
            @foreach($countries as $country => $cnt)
            <div class="country-bar">
                <div class="lrow"><span>{{ $country }}</span><span>{{ $cnt }}</span></div>
                <div class="track"><div class="fill" style="width:{{ round($cnt/$maxCnt*100) }}%"></div></div>
            </div>
            @endforeach
        </div>
        <div style="background:#fff;border-radius:16px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.07);">
            <h3 style="font-size:14px;font-weight:700;margin:0 0 16px;">Onboarding</h3>
            <canvas id="onboardChart" width="180" height="180" style="display:block;margin:0 auto;"></canvas>
            <div style="display:flex;justify-content:center;gap:16px;margin-top:12px;font-size:12px;">
                <span><span style="display:inline-block;width:10px;height:10px;background:#f59e0b;border-radius:2px;margin-right:4px;"></span>Onboarded ({{ $stats['onboarded'] }})</span>
                <span><span style="display:inline-block;width:10px;height:10px;background:#e5e7eb;border-radius:2px;margin-right:4px;"></span>Pending ({{ $stats['total'] - $stats['onboarded'] }})</span>
            </div>
        </div>
    </div>
</div>

{{-- ══ CHAT MONITOR TAB ══ --}}
<div id="pane-chat" style="display:none;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:6px;font-size:13px;color:#10b981;font-weight:600;">
            <span class="realtime-dot"></span> Real-time monitoring
        </div>
        <input type="text" id="chat-search" placeholder="Search by user name..." style="border:1.5px solid #e5e7eb;border-radius:9px;padding:7px 12px;font-size:13px;flex:1;outline:none;" oninput="loadChatMonitor()">
        <span style="font-size:11px;color:#9ca3af;" id="chat-refresh-time"></span>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;height:calc(100vh - 340px);">
        <div style="background:#fff;border-radius:16px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.07);display:flex;flex-direction:column;overflow:hidden;">
            <h3 style="font-size:13px;font-weight:700;margin:0 0 12px;color:#374151;">Conversations</h3>
            <div id="chat-list-el" style="overflow-y:auto;flex:1;">
                <div style="text-align:center;padding:40px 0;"><div class="spinner2"></div></div>
            </div>
        </div>
        <div style="background:#fff;border-radius:16px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.07);display:flex;flex-direction:column;overflow:hidden;">
            <div id="thread-header" style="margin-bottom:12px;">
                <h3 style="font-size:13px;font-weight:700;margin:0;color:#374151;">Select a conversation</h3>
                <p style="font-size:11px;color:#9ca3af;margin:2px 0 0;">Click a chat to view messages</p>
            </div>
            <div id="msg-thread-el" class="msg-thread">
                <div style="text-align:center;padding:60px 0;color:#d1d5db;font-size:13px;"><i class="fas fa-comment-slash" style="font-size:28px;display:block;margin-bottom:8px;"></i>No conversation selected</div>
            </div>
        </div>
    </div>
</div>

</div>{{-- /padding --}}

{{-- Slide Panel --}}
<div class="panel-overlay" id="panelOverlay" onclick="closePanel()"></div>
<div class="slide-panel" id="userPanel">
    <div class="panel-header">
        <button class="panel-close" onclick="closePanel()">✕</button>
        <h3 id="panelTitle">User Detail</h3>
        <button id="panelChatBtn" style="background:#dbeafe;color:#1d4ed8;border:none;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer;display:none;" onclick="openUserChatsFromPanel()">
            <i class="fas fa-comments"></i> Chats
        </button>
    </div>
    <div class="panel-body" id="panelBody">
        <div style="text-align:center;padding:60px 0;"><div class="spinner2" style="margin:0 auto;"></div></div>
    </div>
</div>

<script>
const BASE = '/admin/community';
let activeChatId = null;
let currentPanelUserId = null;

// ── Tabs
function switchTab(tab) {
    ['users','analytics','chat'].forEach(t => {
        document.getElementById('pane-'+t).style.display = 'none';
        document.getElementById('tab-'+t).classList.remove('active');
    });
    document.getElementById('pane-'+tab).style.display = 'block';
    document.getElementById('tab-'+tab).classList.add('active');
    if (tab === 'analytics') initCharts();
    if (tab === 'chat') loadChatMonitor();
}

// ── Donut charts
let chartsInited = false;
function initCharts() {
    if (chartsInited) return; chartsInited = true;
    drawDonut('genderChart', [{{ $stats['male'] }}, {{ $stats['female'] }}, {{ $stats['other_gender'] }}], ['#3b82f6','#ec4899','#a78bfa']);
    drawDonut('verifyChart', [{{ $stats['verified'] }}, {{ $stats['total'] - $stats['verified'] }}], ['#10b981','#e5e7eb']);
    drawDonut('onboardChart', [{{ $stats['onboarded'] }}, {{ $stats['total'] - $stats['onboarded'] }}], ['#f59e0b','#e5e7eb']);
}
function drawDonut(id, data, colors) {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const total = data.reduce((a,b)=>a+b,0)||1;
    const cx = canvas.width/2, cy = canvas.height/2, r = 68, inner = 44;
    let angle = -Math.PI/2;
    ctx.clearRect(0,0,canvas.width,canvas.height);
    data.forEach((v,i) => {
        const sweep = (v/total)*2*Math.PI;
        ctx.beginPath(); ctx.moveTo(cx,cy);
        ctx.arc(cx,cy,r,angle,angle+sweep);
        ctx.closePath(); ctx.fillStyle = colors[i]; ctx.fill();
        angle += sweep;
    });
    ctx.beginPath(); ctx.arc(cx,cy,inner,0,2*Math.PI);
    ctx.fillStyle = '#fff'; ctx.fill();
    ctx.fillStyle = '#111'; ctx.font = 'bold 16px sans-serif';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(total.toLocaleString(), cx, cy);
}

// ── User Panel
function openUserPanel(profileId, userId) {
    currentPanelUserId = userId;
    document.getElementById('userPanel').classList.add('open');
    document.getElementById('panelOverlay').classList.add('open');
    document.getElementById('panelTitle').textContent = 'Loading...';
    document.getElementById('panelChatBtn').style.display = 'none';
    document.getElementById('panelBody').innerHTML = '<div style="text-align:center;padding:60px 0;"><div class="spinner2" style="margin:0 auto;"></div></div>';
    fetch(`${BASE}/users/${profileId}/detail`).then(r=>r.json()).then(renderPanel);
}
function renderPanel(data) {
    const p = data.profile, u = p.user || {};
    const age = p.date_of_birth ? Math.floor((Date.now()-new Date(p.date_of_birth))/(365.25*86400000)) : null;
    document.getElementById('panelTitle').textContent = u.name || 'User';
    document.getElementById('panelChatBtn').style.display = 'inline-flex';
    let interests = '';
    if (p.interests) {
        const arr = Array.isArray(p.interests) ? p.interests : String(p.interests).split(',');
        interests = arr.map(i=>`<span style="background:#ede9fe;color:#7c3aed;font-size:10px;padding:2px 8px;border-radius:20px;font-weight:600;">${i.trim()}</span>`).join(' ');
    }
    const posts = (data.recent_posts||[]).map(post=>`
        <div class="mini-post">
            <span style="background:#f3f4f6;border-radius:6px;padding:4px 8px;font-size:10px;color:#6b7280;">${post.type}</span>
            <div style="flex:1;min-width:0;">
                <div class="content-text">${post.content||'(media only)'}</div>
                <div class="meta">${post.likes_count} likes · ${post.views_count||0} views · ${post.comments_count} comments</div>
            </div>
        </div>`).join('');
    const avHtml = u.avatar
        ? `<img src="${u.avatar}" style="width:56px;height:56px;border-radius:50%;object-fit:cover;">`
        : `<div style="width:56px;height:56px;border-radius:50%;background:#6366f1;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#fff;">${(u.name||'U')[0].toUpperCase()}</div>`;
    document.getElementById('panelBody').innerHTML = `
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">${avHtml}
            <div>
                <div style="font-weight:700;font-size:16px;">${u.name||''} ${p.is_verified?'<i class="fas fa-check-circle" style="color:#2563eb;font-size:13px;"></i>':''}</div>
                <div style="font-size:12px;color:#6b7280;">@${p.username||''}${p.is_business?' · Business':''}</div>
                <div style="font-size:11px;color:#9ca3af;">${u.email||''}</div>
            </div>
        </div>
        ${p.bio?`<div style="background:#f9fafb;border-radius:10px;padding:10px 12px;font-size:12px;color:#374151;margin-bottom:16px;">${p.bio}</div>`:''}
        <div class="panel-section"><h4>Profile Info</h4>
            <div class="info-grid">
                <div class="info-item"><div class="key">Gender</div><div class="val">${p.gender||'—'}</div></div>
                <div class="info-item"><div class="key">Age</div><div class="val">${age?age+' yrs':'—'}</div></div>
                <div class="info-item"><div class="key">Country</div><div class="val">${p.country||'—'}</div></div>
                <div class="info-item"><div class="key">City</div><div class="val">${p.city||'—'}</div></div>
                <div class="info-item"><div class="key">Privacy</div><div class="val">${p.privacy||'—'}</div></div>
                <div class="info-item"><div class="key">Joined</div><div class="val">${u.created_at?new Date(u.created_at).toLocaleDateString():'—'}</div></div>
            </div>
        </div>
        <div class="panel-section"><h4>Social Stats</h4>
            <div class="info-grid">
                <div class="info-item"><div class="key">Posts</div><div class="val">${(p.posts_count||0).toLocaleString()}</div></div>
                <div class="info-item"><div class="key">Followers</div><div class="val">${(p.followers_count||0).toLocaleString()}</div></div>
                <div class="info-item"><div class="key">Following</div><div class="val">${(p.following_count||0).toLocaleString()}</div></div>
                <div class="info-item"><div class="key">Onboarded</div><div class="val">${p.onboarding_completed?'Yes':'No'}</div></div>
            </div>
        </div>
        ${interests?`<div class="panel-section"><h4>Interests</h4><div style="display:flex;flex-wrap:wrap;gap:4px;">${interests}</div></div>`:''}
        ${posts?`<div class="panel-section"><h4>Recent Posts</h4>${posts}</div>`:''}
    `;
}
function closePanel() {
    document.getElementById('userPanel').classList.remove('open');
    document.getElementById('panelOverlay').classList.remove('open');
}
function openUserChatsFromPanel() {
    if (currentPanelUserId) { closePanel(); openUserChats(currentPanelUserId); }
}

// ── Chat Monitor
function loadChatMonitor() {
    const q = document.getElementById('chat-search')?.value||'';
    fetch(`${BASE}/chat-monitor${q?'?search='+encodeURIComponent(q):''}`)
        .then(r=>r.json()).then(data=>renderChatList(data.chats||[]));
    document.getElementById('chat-refresh-time').textContent = 'Updated '+new Date().toLocaleTimeString();
}
function renderChatList(chats) {
    const el = document.getElementById('chat-list-el');
    if (!chats.length) { el.innerHTML='<div style="text-align:center;padding:40px 0;color:#9ca3af;font-size:13px;">No conversations</div>'; return; }
    el.innerHTML = chats.map(chat=>{
        const members = chat.members||[];
        const avs = members.slice(0,2).map(m=>m.avatar
            ?`<img src="${m.avatar}" class="chat-av" style="object-fit:cover;" alt="">`
            :`<div class="chat-av">${(m.name||'?')[0].toUpperCase()}</div>`).join('');
        const lastMsg = chat.last_message || (chat.last_message_type==='image'?'📷 Image':chat.last_message_type==='audio'?'🎵 Audio':'');
        const t = chat.last_message_at ? new Date(chat.last_message_at).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}) : '';
        return `<div class="chat-row${chat.id===activeChatId?' selected':''}" onclick="loadMessages(${chat.id},'${(chat.name||'Chat').replace(/'/g,"\\'")}')">
            <div class="chat-avatars">${avs}</div>
            <div class="chat-meta">
                <div class="chat-names">${chat.name||'Unknown'}</div>
                <div class="chat-last">${lastMsg||'<em style="color:#d1d5db;">No messages</em>'}</div>
            </div>
            <div class="chat-right">
                <div class="chat-cnt">${(chat.messages_count||0)} msgs</div>
                <div class="chat-time">${t}</div>
            </div>
        </div>`;
    }).join('');
}
function loadMessages(chatId, chatName) {
    activeChatId = chatId;
    document.querySelectorAll('.chat-row').forEach(r=>r.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    document.getElementById('thread-header').innerHTML = `<h3 style="font-size:13px;font-weight:700;margin:0;color:#374151;">${chatName}</h3><p style="font-size:11px;color:#9ca3af;margin:2px 0 0;">Chat #${chatId}</p>`;
    document.getElementById('msg-thread-el').innerHTML = '<div style="text-align:center;padding:40px;"><div class="spinner2" style="margin:0 auto;"></div></div>';
    fetch(`${BASE}/chats/${chatId}/messages`).then(r=>r.json()).then(data=>renderMessages(data.messages||[], data.members||[]));
}
function renderMessages(messages, members) {
    const memberMap = {};
    members.forEach(m=>memberMap[m.user_id]=m);
    const firstId = messages[0]?.user_id;
    const thread = document.getElementById('msg-thread-el');
    if (!messages.length) { thread.innerHTML='<div style="text-align:center;padding:40px;color:#9ca3af;font-size:12px;">No messages</div>'; return; }
    thread.innerHTML = messages.map(msg=>{
        const isRight = msg.user_id===firstId;
        const av = msg.avatar
            ?`<div class="msg-av"><img src="${msg.avatar}" alt=""></div>`
            :`<div class="msg-av">${(msg.name||'?')[0].toUpperCase()}</div>`;
        const t = msg.created_at ? new Date(msg.created_at).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}) : '';
        const content = msg.is_deleted
            ?`<span class="msg-deleted-text">[Deleted]</span>`
            :(msg.type==='image'&&msg.media_url?`<img src="${msg.media_url}" style="max-width:100%;border-radius:8px;margin-top:4px;">`
            :(msg.type==='audio'&&msg.media_url?`🎵 Audio`:msg.content||''));
        const delBtn = !msg.is_deleted?`<button class="msg-del-btn" onclick="deleteMsg(${msg.id},this)">Delete</button>`:'';
        return `<div class="msg-wrap${isRight?' right':''}">
            ${av}
            <div class="msg-bubble${isRight?' right-b':' left'}">
                <div class="msg-sender">${msg.name||''}</div>
                ${content}
                <div class="msg-time">${t}</div>
            </div>
            ${delBtn}
        </div>`;
    }).join('');
    thread.scrollTop = thread.scrollHeight;
}
function deleteMsg(id, btn) {
    if (!confirm('Delete this message?')) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content||'';
    fetch(`${BASE}/messages/${id}`, {method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}})
        .then(r=>r.json()).then(d=>{
            if (d.success) {
                const wrap = btn.closest('.msg-wrap');
                wrap.querySelector('.msg-bubble').innerHTML += '<div class="msg-deleted-text" style="margin-top:4px;">[Deleted]</div>';
                btn.remove();
            }
        });
}
function openUserChats(userId) {
    switchTab('chat');
    fetch(`${BASE}/users/${userId}/chats`).then(r=>r.json()).then(data=>renderChatList(data.chats||[]));
}

// ── Verify Toggle
function toggleVerify(profileId, btn) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content||'';
    const isVerified = btn.classList.contains('is-verified');
    fetch(`${BASE}/users/${profileId}/verify`, {method:'POST',headers:{'X-CSRF-TOKEN':csrf}})
        .then(r=>{ if(r.ok||r.redirected) {
            btn.classList.toggle('is-verified', !isVerified);
            btn.innerHTML = !isVerified ? '<i class="fas fa-times"></i> Unverify' : '<i class="fas fa-check"></i> Verify';
        }});
}

// ── Auto-poll chat monitor every 5s when visible
setInterval(()=>{ if(document.getElementById('pane-chat').style.display!=='none') loadChatMonitor(); }, 5000);
</script>

@endsection
