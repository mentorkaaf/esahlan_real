<!DOCTYPE html>
<html lang="so" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'My Portal') — eSahlan Staff</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --brand:    #F7941D;
      --navy:     #1B1444;
      --navy2:    #251c5c;
      --surface:  #f4f5f9;
      --white:    #ffffff;
      --border:   #e5e7eb;
      --text:     #111827;
      --muted:    #6b7280;
      --green:    #16a34a;
      --red:      #dc2626;
      --yellow:   #d97706;
      --blue:     #2563eb;
    }
    html, body { height: 100%; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--surface); color: var(--text); display: flex; flex-direction: column; }

    /* ── Topbar ── */
    .topbar {
      background: var(--navy); color: #fff; height: 60px;
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 24px; position: sticky; top: 0; z-index: 100;
      box-shadow: 0 2px 12px rgba(0,0,0,.3);
    }
    .topbar-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
    .topbar-brand .logo {
      width: 36px; height: 36px; border-radius: 10px;
      background: linear-gradient(135deg, var(--brand), #e07000);
      display: flex; align-items: center; justify-content: center;
      font-weight: 900; font-size: 16px; color: #fff;
    }
    .topbar-brand span { font-weight: 800; font-size: 17px; color: #fff; }
    .topbar-brand span b { color: var(--brand); }
    .topbar-right { display: flex; align-items: center; gap: 16px; }
    .topbar-emp { display: flex; align-items: center; gap: 10px; font-size: 13px; }
    .topbar-avatar {
      width: 34px; height: 34px; border-radius: 50%;
      background: var(--brand); color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 13px; flex-shrink: 0;
    }
    .topbar-name { color: #e5e7eb; font-size: 13px; }
    .topbar-no   { color: #9ca3af; font-size: 11px; }
    .logout-btn {
      background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.18);
      color: #e5e7eb; padding: 7px 14px; border-radius: 8px;
      font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none;
      display: inline-flex; align-items: center; gap: 6px;
      transition: background .15s;
    }
    .logout-btn:hover { background: rgba(255,255,255,.15); color: #fff; }

    /* ── Bell / Notifications ── */
    .bell-wrap { position: relative; }
    .bell-btn {
      background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.18);
      color: #e5e7eb; width: 36px; height: 36px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; position: relative; transition: background .15s;
    }
    .bell-btn:hover { background: rgba(255,255,255,.2); }
    .bell-badge {
      position: absolute; top: -4px; right: -4px;
      background: #dc2626; color: #fff;
      font-size: 9px; font-weight: 800; min-width: 16px; height: 16px;
      border-radius: 99px; display: none; align-items: center; justify-content: center;
      padding: 0 4px; border: 2px solid var(--navy);
    }
    .bell-badge.show { display: flex; }

    .notif-dropdown {
      position: absolute; right: 0; top: calc(100% + 10px);
      width: 340px; background: #fff; border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0,0,0,.25); border: 1px solid #e5e7eb;
      z-index: 999; display: none; overflow: hidden;
    }
    .notif-dropdown.open { display: block; }
    .notif-header {
      padding: 14px 16px; border-bottom: 1px solid #f3f4f6;
      display: flex; align-items: center; justify-content: space-between;
    }
    .notif-header-title { font-size: 13px; font-weight: 800; color: #111827; }
    .notif-read-all {
      font-size: 11px; font-weight: 700; color: var(--brand);
      background: none; border: none; cursor: pointer; padding: 0;
    }
    .notif-read-all:hover { text-decoration: underline; }
    .notif-list { max-height: 340px; overflow-y: auto; }
    .notif-item {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 12px 16px; border-bottom: 1px solid #f9fafb;
      text-decoration: none; cursor: pointer; transition: background .1s;
    }
    .notif-item:hover { background: #f9fafb; }
    .notif-item.unread { background: #fefce8; }
    .notif-item.unread:hover { background: #fef9c3; }
    .notif-icon {
      width: 32px; height: 32px; border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
      font-size: 13px; flex-shrink: 0;
    }
    .notif-title  { font-size: 12px; font-weight: 700; color: #111827; line-height: 1.3; }
    .notif-body   { font-size: 11px; color: #6b7280; margin-top: 2px; line-height: 1.4; }
    .notif-time   { font-size: 10px; color: #9ca3af; margin-top: 3px; }
    .notif-empty  { padding: 28px; text-align: center; font-size: 12px; color: #9ca3af; }

    /* ── Toast ── */
    .toast-stack {
      position: fixed; bottom: 24px; right: 24px; z-index: 9999;
      display: flex; flex-direction: column; gap: 10px; pointer-events: none;
    }
    .toast {
      background: #fff; border-radius: 14px; box-shadow: 0 8px 32px rgba(0,0,0,.18);
      border: 1px solid #e5e7eb; padding: 14px 16px;
      display: flex; align-items: flex-start; gap: 12px;
      min-width: 280px; max-width: 340px; pointer-events: all;
      animation: toastIn .25s ease;
    }
    .toast-icon {
      width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center; font-size: 14px;
    }
    .toast-title { font-size: 12px; font-weight: 800; color: #111827; }
    .toast-body  { font-size: 11px; color: #6b7280; margin-top: 2px; line-height: 1.4; }
    .toast-close {
      margin-left: auto; background: none; border: none; cursor: pointer;
      color: #9ca3af; font-size: 12px; padding: 0 0 0 8px; flex-shrink: 0;
    }
    @keyframes toastIn { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:none; } }

    /* ── Layout ── */
    .layout { display: flex; flex: 1; overflow: hidden; }

    /* ── Sidebar ── */
    .sidebar {
      width: 220px; flex-shrink: 0; background: var(--white);
      border-right: 1px solid var(--border); overflow-y: auto;
      display: flex; flex-direction: column;
    }
    @media (max-width: 768px) {
      .sidebar { display: none; }
      .layout { flex-direction: column; }
      .main { padding: 16px; }
    }

    .sidebar-section { padding: 16px 12px 6px; }
    .sidebar-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); padding: 0 10px; margin-bottom: 4px; }
    .nav-link {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 12px; border-radius: 9px; font-size: 13px; font-weight: 500;
      color: var(--muted); text-decoration: none; transition: all .15s; cursor: pointer;
      margin-bottom: 2px;
    }
    .nav-link i { width: 16px; text-align: center; font-size: 13px; }
    .nav-link:hover  { background: #f3f4f6; color: var(--text); }
    .nav-link.active { background: #fff5e6; color: var(--brand); font-weight: 600; }
    .nav-link.active i { color: var(--brand); }

    .workspace-switcher { padding: 12px; }
    .ws-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); margin-bottom: 8px; }
    .ws-chip {
      display: flex; align-items: center; gap: 8px; padding: 8px 10px;
      border-radius: 9px; font-size: 12px; font-weight: 600; margin-bottom: 4px;
      text-decoration: none; border: 1.5px solid transparent; transition: all .15s;
      color: var(--text);
    }
    .ws-chip:hover   { border-color: var(--brand); color: var(--brand); }
    .ws-chip.current { background: #fff5e6; border-color: var(--brand); color: var(--brand); }
    .ws-chip .dot    { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }

    /* ── Main ── */
    .main { flex: 1; overflow-y: auto; padding: 28px 32px; }

    /* ── Alert/Flash ── */
    .flash { padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
    .flash-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
    .flash-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
    .flash-warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }

    /* ── Card ── */
    .card { background: var(--white); border-radius: 14px; border: 1px solid var(--border); }
    .card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
    .card-title  { font-size: 13px; font-weight: 700; color: var(--text); text-transform: uppercase; letter-spacing: .05em; }
    .card-body   { padding: 20px; }

    /* ── Status badges ── */
    .badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    .badge-green  { background: #dcfce7; color: #166534; }
    .badge-red    { background: #fee2e2; color: #991b1b; }
    .badge-yellow { background: #fef9c3; color: #854d0e; }
    .badge-blue   { background: #dbeafe; color: #1e40af; }
    .badge-gray   { background: #f3f4f6; color: #374151; }
    .badge-orange { background: #fff5e6; color: #c2410c; }

    /* ── Button ── */
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all .15s; }
    .btn-primary { background: var(--brand); color: #fff; }
    .btn-primary:hover { background: #e07000; }
    .btn-outline { background: transparent; color: var(--muted); border: 1.5px solid var(--border); }
    .btn-outline:hover { border-color: var(--brand); color: var(--brand); }
    .btn-sm { padding: 6px 12px; font-size: 12px; }

    /* ── Suspended banner ── */
    .suspended-banner {
      background: #fef3c7; border-bottom: 1px solid #fde68a;
      padding: 10px 24px; font-size: 13px; color: #92400e;
      display: flex; align-items: center; gap: 8px;
    }

    @yield('extra-styles')
  </style>
  @yield('head')
</head>
<body>
@php $emp = auth('employee')->user(); @endphp

{{-- Topbar --}}
<div class="topbar">
  <a href="{{ route('employee.dashboard') }}" class="topbar-brand">
    <div class="logo">e</div>
    <span>e<b>Sahlan</b> Staff</span>
  </a>
  <div class="topbar-right">
    {{-- Bell notification button --}}
    <div class="bell-wrap" id="bellWrap">
      <button class="bell-btn" id="bellBtn" onclick="toggleNotifDropdown()" aria-label="Notifications">
        <i class="fas fa-bell" style="font-size:14px;"></i>
        <span class="bell-badge" id="bellBadge"></span>
      </button>
      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-header">
          <span class="notif-header-title">Notifications</span>
          <button class="notif-read-all" onclick="markAllRead()">Dhammaan akhri</button>
        </div>
        <div class="notif-list" id="notifList">
          <div class="notif-empty"><i class="fas fa-bell-slash" style="display:block;font-size:24px;opacity:.3;margin-bottom:8px;"></i>Weli wax la'aan</div>
        </div>
      </div>
    </div>

    <div class="topbar-emp">
      <div class="topbar-avatar">
        {{ strtoupper(substr($emp->first_name,0,1).substr($emp->last_name,0,1)) }}
      </div>
      <div>
        <div class="topbar-name">{{ $emp->full_name }}</div>
        <div class="topbar-no">{{ $emp->employee_no }}</div>
      </div>
    </div>
    <form action="{{ route('employee.logout') }}" method="POST">
      @csrf
      <button type="submit" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Bax
      </button>
    </form>
  </div>
</div>

@if($emp->status === 'suspended')
<div class="suspended-banner">
  <i class="fas fa-exclamation-triangle"></i>
  <strong>Akoon-kaagu waa la dhigay hakad.</strong> Waxaad arki kartaa macluumaadkaaga laakiin waxyaabaha aad samayn karto ayaa xaddidnaa. Xiriir HR wixii faahfaahin ah.
</div>
@endif

<div class="layout">
  {{-- Sidebar --}}
  <nav class="sidebar">

    {{-- My Workspaces --}}
    @php
      $myAssignments = $emp->workforceAssignments()
        ->where('status','active')->with('module')->get();
      $currentSlug = request()->route('slug') ?? '';
    @endphp
    @if($myAssignments->isNotEmpty())
    <div class="workspace-switcher">
      <div class="ws-label"><i class="fas fa-th-large"></i> Workspaces-kayga</div>
      @foreach($myAssignments as $a)
      @php $mod = $a->module; if(!$mod) continue; @endphp
      <a href="{{ route('employee.workspace', $mod->slug) }}"
         class="ws-chip {{ $currentSlug === $mod->slug ? 'current' : '' }}">
        <i class="fas fa-circle dot" style="font-size:8px; color:{{ $mod->color ?? 'var(--brand)' }}"></i>
        {{ $mod->name }}
        @if($a->assignment_type === 'primary')
          <span style="font-size:9px;color:var(--muted);margin-left:auto">Primary</span>
        @endif
      </a>
      @endforeach
    </div>
    <hr style="margin:0 12px;border:none;border-top:1px solid var(--border);">
    @endif

    {{-- Navigation --}}
    <div class="sidebar-section">
      <div class="sidebar-label">Portal</div>
      <a href="{{ route('employee.dashboard') }}"
         class="nav-link {{ request()->routeIs('employee.dashboard') ? 'active' : '' }}">
        <i class="fas fa-home"></i> Dashboard
      </a>
      <a href="{{ route('employee.performance') }}"
         class="nav-link {{ request()->routeIs('employee.performance') ? 'active' : '' }}">
        <i class="fas fa-chart-line"></i> Performance
      </a>
      <a href="{{ route('employee.attendance') }}"
         class="nav-link {{ request()->routeIs('employee.attendance') ? 'active' : '' }}">
        <i class="fas fa-calendar-check"></i> Attendance
      </a>
      <a href="{{ route('employee.leaves') }}"
         class="nav-link {{ request()->routeIs('employee.leaves*') ? 'active' : '' }}">
        <i class="fas fa-umbrella-beach"></i> Leave
        @php $pending = \App\Models\HR\HrLeave::where('employee_id',$emp->id)->where('status','pending')->count(); @endphp
        @if($pending > 0)
          <span style="margin-left:auto;background:#fee2e2;color:#991b1b;font-size:10px;font-weight:700;padding:1px 7px;border-radius:20px;">{{ $pending }}</span>
        @endif
      </a>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-label">Self-Service</div>
      <a href="{{ route('employee.payslips') }}"
         class="nav-link {{ request()->routeIs('employee.payslips*') ? 'active' : '' }}">
        <i class="fas fa-file-invoice-dollar"></i> Payslips
      </a>
      <a href="{{ route('employee.documents') }}"
         class="nav-link {{ request()->routeIs('employee.documents*') ? 'active' : '' }}">
        <i class="fas fa-folder-open"></i> Documents
      </a>
      <a href="{{ route('employee.announcements') }}"
         class="nav-link {{ request()->routeIs('employee.announcements*') ? 'active' : '' }}">
        <i class="fas fa-bullhorn"></i> Announcements
        @php
          $unread = \App\Models\HR\HrAnnouncement::query()
            ->where(fn($q) => $q->where('audience','all')
              ->orWhere(fn($q2) => $q2->where('audience','department')->where('department_id',$emp->department_id)))
            ->whereNotNull('published_at')->where('published_at','<=',now())
            ->where('created_at','>=',now()->subDays(3))->count();
        @endphp
        @if($unread)
          <span style="margin-left:auto;background:#dbeafe;color:#1e40af;font-size:10px;font-weight:700;padding:1px 7px;border-radius:20px;">{{ $unread }}</span>
        @endif
      </a>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-label">Account</div>
      <a href="{{ route('employee.profile') }}"
         class="nav-link {{ request()->routeIs('employee.profile*') ? 'active' : '' }}">
        <i class="fas fa-user-circle"></i> Profile
      </a>
    </div>

    {{-- Bottom: employee info --}}
    <div style="margin-top:auto;padding:16px;border-top:1px solid var(--border);font-size:11px;color:var(--muted);">
      @php $dept = $emp->department?->name ?? '—'; $pos = $emp->position?->title ?? '—'; @endphp
      <div style="font-weight:600;color:var(--text);margin-bottom:4px;">{{ $pos }}</div>
      <div>{{ $dept }}</div>
      <div style="margin-top:6px;">
        @php
          $statusColor = match($emp->status) {
            'active' => 'badge-green', 'suspended' => 'badge-yellow',
            'probation' => 'badge-blue', default => 'badge-gray'
          };
        @endphp
        <span class="badge {{ $statusColor }}">{{ ucfirst($emp->status) }}</span>
      </div>
    </div>
  </nav>

  {{-- Main content --}}
  <main class="main">
    @if(session('success'))
      <div class="flash flash-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
    @endif
    @if(session('error') || $errors->any())
      <div class="flash flash-error">
        <i class="fas fa-exclamation-circle"></i>
        {{ session('error') ?? $errors->first() }}
      </div>
    @endif

    @yield('content')
  </main>
</div>

{{-- Toast stack --}}
<div class="toast-stack" id="toastStack"></div>

@yield('scripts')
@stack('scripts')

<script>
// ── Notification helpers ──────────────────────────────────────────────
const NOTIF_URL      = '{{ route("employee.notifications") }}';
const MARK_READ_URL  = '{{ route("employee.notifications.read-all") }}';
const CSRF           = '{{ csrf_token() }}';

let notifOpen = false;
let notifLoaded = false;

function toggleNotifDropdown() {
  const drop = document.getElementById('notifDropdown');
  notifOpen = !notifOpen;
  drop.classList.toggle('open', notifOpen);
  if (notifOpen && !notifLoaded) { fetchNotifications(); notifLoaded = true; }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
  if (notifOpen && !document.getElementById('bellWrap').contains(e.target)) {
    notifOpen = false;
    document.getElementById('notifDropdown').classList.remove('open');
  }
});

function fetchNotifications() {
  fetch(NOTIF_URL, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } })
    .then(r => r.json())
    .then(data => {
      renderNotifications(data.notifications, data.unread);
      setBadge(data.unread);
    })
    .catch(() => {});
}

function renderNotifications(items, unread) {
  const list = document.getElementById('notifList');
  if (!items || !items.length) {
    list.innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash" style="display:block;font-size:24px;opacity:.3;margin-bottom:8px;"></i>Weli wax la\'aan</div>';
    return;
  }
  list.innerHTML = items.map(n => `
    <div class="notif-item ${n.read ? '' : 'unread'}"
         onclick="onNotifClick(${n.id}, '${escapeJs(n.url || '')}')">
      <div class="notif-icon" style="background:${lighten(n.color)};color:${n.color};">
        <i class="${escapeJs(n.icon || 'fas fa-bell')}"></i>
      </div>
      <div style="flex:1;min-width:0;">
        <div class="notif-title">${escapeHtml(n.title)}</div>
        ${n.body ? `<div class="notif-body">${escapeHtml(n.body)}</div>` : ''}
        <div class="notif-time">${escapeHtml(n.time)}</div>
      </div>
    </div>
  `).join('');
}

function onNotifClick(id, url) {
  fetch(`/employee/notifications/${id}/read`, {
    method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
  }).catch(() => {});
  if (url) { window.location.href = url; }
}

function markAllRead() {
  fetch(MARK_READ_URL, {
    method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
  }).then(() => {
    setBadge(0);
    document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
  }).catch(() => {});
}

function setBadge(count) {
  const badge = document.getElementById('bellBadge');
  if (count > 0) {
    badge.textContent = count > 99 ? '99+' : count;
    badge.classList.add('show');
  } else {
    badge.classList.remove('show');
  }
}

// Poll unread count every 60s (fallback for when WS isn't connected)
function pollBadge() {
  fetch('{{ route("employee.notifications.count") }}', { headers: { 'Accept': 'application/json' } })
    .then(r => r.json()).then(d => setBadge(d.count)).catch(() => {});
}
pollBadge();
setInterval(pollBadge, 60000);

// ── Toast system ──────────────────────────────────────────────────────
function showToast(notif) {
  const stack  = document.getElementById('toastStack');
  const id     = 'toast-' + Date.now();
  const div    = document.createElement('div');
  div.className = 'toast';
  div.id        = id;
  div.innerHTML = `
    <div class="toast-icon" style="background:${lighten(notif.color)};color:${notif.color};">
      <i class="${escapeJs(notif.icon || 'fas fa-bell')}"></i>
    </div>
    <div style="flex:1;">
      <div class="toast-title">${escapeHtml(notif.title)}</div>
      ${notif.body ? `<div class="toast-body">${escapeHtml(notif.body)}</div>` : ''}
    </div>
    <button class="toast-close" onclick="removeToast('${id}')">&times;</button>
  `;
  if (notif.url) { div.style.cursor = 'pointer'; div.onclick = (e) => { if (!e.target.classList.contains('toast-close')) window.location.href = notif.url; }; }
  stack.appendChild(div);
  setTimeout(() => removeToast(id), 6000);

  // Also refresh the badge + dropdown list
  setBadge((parseInt(document.getElementById('bellBadge').textContent) || 0) + 1);
  notifLoaded = false; // force reload next time dropdown opens
}

function removeToast(id) {
  const el = document.getElementById(id);
  if (el) el.remove();
}

// ── Reverb / Echo setup ───────────────────────────────────────────────
@php
  $reverbKey    = config('broadcasting.connections.reverb.key');
  $reverbHost   = config('broadcasting.connections.reverb.options.host', '127.0.0.1');
  $reverbPort   = config('broadcasting.connections.reverb.options.port', 8080);
  $reverbScheme = config('broadcasting.connections.reverb.options.scheme', 'http');
  $empId        = auth('employee')->id();
@endphp

if (typeof Echo !== 'undefined' && {{ $empId ?? 0 }} > 0) {
  try {
    const echo = new Echo({
      broadcaster:     'reverb',
      key:             '{{ $reverbKey }}',
      wsHost:          '{{ $reverbHost }}',
      wsPort:           {{ $reverbPort }},
      wssPort:          {{ $reverbPort }},
      forceTLS:         {{ $reverbScheme === 'https' ? 'true' : 'false' }},
      enabledTransports: ['ws', 'wss'],
      authEndpoint:    '/employee/broadcasting/auth',
      auth: {
        headers: { 'X-CSRF-TOKEN': CSRF },
      },
    });

    echo.private('employee.{{ $empId }}')
      .listen('.employee.notification', function(data) {
        showToast(data);
      });
  } catch(e) {
    // Reverb may not be available in all environments — fail silently
    console.warn('[eSahlan] Realtime connect failed:', e.message);
  }
}

// ── Utilities ─────────────────────────────────────────────────────────
function escapeHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escapeJs(s) { return String(s).replace(/'/g,"\\'").replace(/\n/g,''); }
function lighten(hex) {
  // Produce a 15%-opacity tint of the hex color as rgba
  const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
  return `rgba(${r},${g},${b},.15)`;
}
</script>
</body>
</html>
