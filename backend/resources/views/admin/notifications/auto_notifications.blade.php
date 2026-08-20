@extends('admin.layouts.app')
@section('title', 'Auto Notifications')
@section('content')

<style>
/* ══════════════════════════════════════════════════════════════
   AUTO NOTIFICATIONS — Professional SaaS Dashboard
══════════════════════════════════════════════════════════════ */

/* ── Page header ──────────────────────────────────────────── */
.an-page-header {
    display:flex;align-items:flex-start;justify-content:space-between;
    margin-bottom:28px;flex-wrap:wrap;gap:14px;
}
.an-page-title { font-size:22px;font-weight:900;color:var(--text);letter-spacing:-.3px; }
.an-page-sub   { font-size:13px;color:var(--text-muted);margin-top:4px;max-width:520px;line-height:1.5; }

/* ── Stats row ────────────────────────────────────────────── */
.an-stats-row  { display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:32px; }
@media(max-width:900px){ .an-stats-row { grid-template-columns:repeat(2,1fr); } }
@media(max-width:500px){ .an-stats-row { grid-template-columns:1fr; } }
.an-stat-card  {
    background:var(--surface);border:1px solid var(--border);border-radius:14px;
    padding:16px 20px;display:flex;align-items:center;gap:14px;
}
.an-stat-icon  {
    width:42px;height:42px;border-radius:11px;display:flex;
    align-items:center;justify-content:center;font-size:18px;flex-shrink:0;
}
.an-stat-val   { font-size:24px;font-weight:900;color:var(--text);line-height:1; }
.an-stat-lbl   { font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-top:3px; }

/* ── Section block ────────────────────────────────────────── */
.an-section    { margin-bottom:36px; }
.an-section-header {
    display:flex;align-items:center;gap:12px;
    padding:14px 20px;border-radius:14px 14px 0 0;
    margin-bottom:0;
}
.an-sec-emoji  { font-size:22px;line-height:1; }
.an-sec-title  { font-size:15px;font-weight:800;color:#fff;letter-spacing:-.1px; }
.an-sec-desc   { font-size:12px;color:rgba(255,255,255,.7);margin-top:2px; }
.an-sec-count  {
    margin-left:auto;background:rgba(255,255,255,.2);color:#fff;
    font-size:11px;font-weight:800;padding:3px 10px;border-radius:20px;
}

/* Section colors */
.an-sec-reengagement { background:linear-gradient(135deg,#7c3aed,#6d28d9); }
.an-sec-time_based   { background:linear-gradient(135deg,#2563eb,#1d4ed8); }
.an-sec-vendor       { background:linear-gradient(135deg,#059669,#047857); }
.an-sec-loyalty      { background:linear-gradient(135deg,#d97706,#b45309); }
.an-sec-eticket      { background:linear-gradient(135deg,#0d9488,#0f766e); }
.an-sec-campaigns    { background:linear-gradient(135deg,#dc2626,#b91c1c); }

/* ── Template accordion card ──────────────────────────────── */
.an-card {
    background:var(--surface);
    border:1px solid var(--border);
    border-top:none;
    overflow:hidden;
}
.an-card:last-child { border-radius:0 0 14px 14px; }
.an-card + .an-card { border-top:1px solid var(--border); }

/* Left accent per type */
.an-card-inner { border-left:4px solid transparent; }
.an-card-inner.type-reengagement { border-left-color:#7c3aed; }
.an-card-inner.type-time_based   { border-left-color:#2563eb; }
.an-card-inner.type-vendor       { border-left-color:#059669; }
.an-card-inner.type-loyalty      { border-left-color:#d97706; }
.an-card-inner.type-eticket      { border-left-color:#0d9488; }

/* Accordion trigger (collapsed by default) */
.an-acc-trigger {
    display:flex;align-items:center;gap:14px;padding:16px 20px;
    cursor:pointer;user-select:none;transition:background .15s;
    width:100%;background:none;border:none;text-align:left;
}
.an-acc-trigger:hover { background:rgba(0,0,0,.03); }
.an-acc-chevron {
    margin-left:auto;font-size:12px;color:var(--text-muted);
    transition:transform .25s;flex-shrink:0;
}
.an-card.open .an-acc-chevron { transform:rotate(180deg); }

/* Trigger left: status dot */
.an-status-dot {
    width:9px;height:9px;border-radius:50%;flex-shrink:0;
    box-shadow:0 0 0 3px rgba(0,0,0,.08);
}
.an-status-dot.active  { background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.2); }
.an-status-dot.paused  { background:#94a3b8;box-shadow:0 0 0 3px rgba(148,163,184,.2); }

.an-tpl-label { font-size:14px;font-weight:800;color:var(--text); }
.an-tpl-meta  { font-size:11px;color:var(--text-muted);margin-top:2px;display:flex;flex-wrap:wrap;gap:8px; }
.an-meta-chip {
    display:inline-flex;align-items:center;gap:3px;
    background:var(--bg);border:1px solid var(--border);
    border-radius:6px;padding:1px 7px;font-size:10px;font-weight:700;
    color:var(--text-muted);
}

/* Status pill */
.an-status-pill {
    font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;
    padding:3px 10px;border-radius:20px;flex-shrink:0;
}
.an-status-pill.active { background:rgba(34,197,94,.15);color:#16a34a;border:1px solid rgba(34,197,94,.3); }
.an-status-pill.paused { background:rgba(148,163,184,.12);color:#64748b;border:1px solid rgba(148,163,184,.3); }

/* Toggle */
.an-toggle-wrap   { display:flex;align-items:center;gap:7px;flex-shrink:0; }
.an-toggle        { position:relative;display:inline-block;width:42px;height:23px;flex-shrink:0; }
.an-toggle input  { opacity:0;width:0;height:0; }
.an-toggle-slider { position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:26px;transition:.25s; }
.an-toggle-slider:before { content:'';position:absolute;height:17px;width:17px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.25s;box-shadow:0 1px 3px rgba(0,0,0,.2); }
.an-toggle input:checked + .an-toggle-slider { background:#22c55e; }
.an-toggle input:checked + .an-toggle-slider:before { transform:translateX(19px); }

/* ── Accordion body ────────────────────────────────────────── */
.an-acc-body { display:none; }
.an-card.open .an-acc-body { display:block; }

.an-form-wrap {
    padding:0 20px 0 24px;
    border-top:1px solid var(--border);
    background:var(--bg);
}

/* ── Bilingual grid ─────────────────────────────────────────── */
.an-lang-grid {
    display:grid;grid-template-columns:1fr 1fr;gap:0;
}
@media(max-width:768px){ .an-lang-grid { grid-template-columns:1fr; } }

.an-lang-col {
    padding:20px 16px;
}
.an-lang-col + .an-lang-col {
    border-left:1px solid var(--border);
}
.an-lang-header {
    display:flex;align-items:center;gap:8px;margin-bottom:14px;
    padding-bottom:10px;border-bottom:1px solid var(--border);
}
.an-lang-flag { font-size:18px;line-height:1; }
.an-lang-name { font-size:12px;font-weight:800;color:var(--text);letter-spacing:.3px; }
.an-lang-native { font-size:11px;color:var(--text-muted);margin-left:auto; }

.an-field { margin-bottom:12px; }
.an-field label {
    display:block;font-size:10px;font-weight:800;text-transform:uppercase;
    letter-spacing:.6px;color:var(--text-muted);margin-bottom:5px;
}
.an-field .form-control {
    font-size:13px;border-radius:8px;
}
.an-field textarea.form-control { resize:vertical;min-height:80px; }

/* ── Form footer ────────────────────────────────────────────── */
.an-form-footer {
    display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;
    padding:14px 20px 14px 24px;border-top:1px solid var(--border);
    background:var(--surface);
}
.an-form-footer-left { display:flex;align-items:center;gap:20px;flex-wrap:wrap; }
.an-interval-wrap    { display:flex;align-items:center;gap:10px; }
.an-interval-wrap label { font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);white-space:nowrap; }
.an-interval-wrap .form-control { width:80px;font-size:13px;border-radius:8px; }

/* ── Language selector ─────────────────────────────────────── */
.an-lang-selector-row {
    padding:12px 20px 12px 24px;border-top:1px solid var(--border);
    display:flex;align-items:center;gap:12px;background:var(--surface);
}
.an-lang-selector-row label { font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted); }
.an-lang-selector-row select { font-size:12px;border-radius:8px;padding:5px 10px;max-width:200px; }

/* ── Send footer ────────────────────────────────────────────── */
.an-send-footer {
    padding:12px 20px 12px 24px;border-top:1px solid var(--border);
    background:var(--bg);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;
}
.an-send-meta { font-size:12px;color:var(--text-muted);display:flex;flex-wrap:wrap;gap:12px; }
.an-send-meta strong { color:var(--text); }

/* ── Variable chips ─────────────────────────────────────────── */
.an-vars-row { padding:10px 20px 10px 24px;background:var(--surface);border-top:1px dashed var(--border);display:flex;flex-wrap:wrap;gap:6px;align-items:center; }
.an-var-label { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted); }
.an-var-chip {
    font-size:11px;padding:3px 9px;background:rgba(99,102,241,.1);
    color:#6366f1;border-radius:6px;font-family:monospace;cursor:pointer;
    border:1px solid rgba(99,102,241,.2);transition:.15s;
}
.an-var-chip:hover { background:rgba(99,102,241,.2);transform:translateY(-1px); }

/* ── Alerts ─────────────────────────────────────────────────── */
.an-alert { padding:12px 18px;border-radius:10px;font-size:13px;margin-bottom:18px;font-weight:600;display:flex;align-items:center;gap:9px; }
.an-alert-s { background:#d1fae5;color:#065f46;border:1px solid #6ee7b7; }
.an-alert-e { background:#fee2e2;color:#991b1b;border:1px solid #fca5a5; }

/* ── Log mini ───────────────────────────────────────────────── */
.an-log-wrap { padding:0 20px 16px 24px;background:var(--bg); }
.an-log-mini { border-radius:10px;overflow:hidden;border:1px solid var(--border); }
.an-log-head { background:var(--surface);padding:8px 14px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);display:flex;gap:0; }
.an-log-head > div { flex:1; }
.an-log-row  { display:flex;padding:9px 14px;border-top:1px solid var(--border);font-size:12px;color:var(--text);gap:0; }
.an-log-row > div { flex:1; }
.an-log-row:hover { background:var(--surface); }
.an-log-sent { font-weight:800;color:var(--brand); }

/* ── Empty state ────────────────────────────────────────────── */
.an-empty {
    padding:32px;text-align:center;color:var(--text-muted);font-size:13px;
    border:2px dashed var(--border);border-top:none;border-radius:0 0 14px 14px;
    background:var(--bg);
}

/* ── Flight preview ─────────────────────────────────────────── */
.an-phone-preview {
    background:#1a1d2e;border-radius:16px;padding:16px;
}
.an-notif-bubble {
    background:#fff;border-radius:10px;padding:12px 14px;
    display:flex;gap:11px;align-items:flex-start;
}
.an-notif-app-icon {
    width:34px;height:34px;border-radius:9px;background:#f97316;
    display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;
}
.an-notif-title { font-size:12px;font-weight:700;color:#111;margin-bottom:2px; }
.an-notif-body  { font-size:11px;color:#555;line-height:1.4; }

/* ── Btn styles ─────────────────────────────────────────────── */
.btn-save-tpl {
    display:inline-flex;align-items:center;gap:6px;padding:8px 18px;
    background:var(--brand);color:#fff;border:none;border-radius:9px;
    font-size:12px;font-weight:700;cursor:pointer;transition:.15s;
}
.btn-save-tpl:hover { filter:brightness(1.1); }
.btn-send-now {
    display:inline-flex;align-items:center;gap:6px;padding:8px 18px;
    background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;border:none;
    border-radius:9px;font-size:12px;font-weight:700;cursor:pointer;transition:.15s;
    text-decoration:none;
}
.btn-send-now:hover { filter:brightness(1.1);color:#fff; }
.btn-back {
    display:inline-flex;align-items:center;gap:6px;padding:8px 16px;
    background:var(--bg);color:var(--text);border:1px solid var(--border);
    border-radius:9px;font-size:13px;font-weight:700;text-decoration:none;transition:.15s;
}
.btn-back:hover { background:var(--surface); }
</style>

{{-- ── Page Header ──────────────────────────────────────────────────────── --}}
<div class="an-page-header">
    <div>
        <div class="an-page-title">🤖 Auto Notifications</div>
        <div class="an-page-sub">Manage automated marketing push notifications — re-engagement, time-based promos, vendor alerts, loyalty & flights.</div>
    </div>
    <a href="{{ route('admin.notifications.index') }}" class="btn-back">← Back</a>
</div>

@if(session('success'))
    <div class="an-alert an-alert-s">✓ {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="an-alert an-alert-e">✗ {{ session('error') }}</div>
@endif

{{-- ── Stats Row ────────────────────────────────────────────────────────── --}}
<div class="an-stats-row">
    <div class="an-stat-card">
        <div class="an-stat-icon" style="background:rgba(249,115,22,.12);font-size:20px;">📱</div>
        <div>
            <div class="an-stat-val">{{ number_format($stats['users_with_fcm']) }}</div>
            <div class="an-stat-lbl">Users with FCM</div>
        </div>
    </div>
    <div class="an-stat-card">
        <div class="an-stat-icon" style="background:rgba(99,102,241,.12);font-size:20px;">📋</div>
        <div>
            <div class="an-stat-val">{{ $templates->count() }}</div>
            <div class="an-stat-lbl">Total Templates</div>
        </div>
    </div>
    <div class="an-stat-card">
        <div class="an-stat-icon" style="background:rgba(34,197,94,.12);font-size:20px;">✅</div>
        <div>
            <div class="an-stat-val">{{ $templates->where('is_active', true)->count() }}</div>
            <div class="an-stat-lbl">Active</div>
        </div>
    </div>
    <div class="an-stat-card">
        <div class="an-stat-icon" style="background:rgba(249,115,22,.12);font-size:20px;">📤</div>
        <div>
            <div class="an-stat-val">{{ collect($logs)->flatten(1)->filter(fn($l) => \Carbon\Carbon::parse($l->created_at)->isToday())->sum('sent_count') }}</div>
            <div class="an-stat-lbl">Sent Today</div>
        </div>
    </div>
</div>

@php
$typeConfig = [
    'reengagement' => ['emoji'=>'🔄','label'=>'Re-engagement — Win Back Inactive Users','desc'=>'Send to users who haven\'t ordered in 3, 7, 14, or 30 days.','css'=>'reengagement'],
    'time_based'   => ['emoji'=>'⏰','label'=>'Time-Based Promotions','desc'=>'Send at the right moment — lunch, evening deals, weekend specials.','css'=>'time_based'],
    'vendor'       => ['emoji'=>'🏪','label'=>'Vendor Alerts','desc'=>'Notify district users when a new store opens near them.','css'=>'vendor'],
    'loyalty'      => ['emoji'=>'⭐','label'=>'Loyalty & Wallet','desc'=>'Remind users about expiring reward points and low ePay balance.','css'=>'loyalty'],
    'eticket'      => ['emoji'=>'✈️','label'=>'eTicket — Upcoming Flights','desc'=>'Notify all users about upcoming available flights (every 12 hours).','css'=>'eticket'],
];

$templateVars = [
    'reengagement' => [],
    'time_based'   => [],
    'vendor'       => ['{store_name}','{module}','{district}'],
    'loyalty'      => ['{points}'],
    'eticket'      => ['{from}','{to}','{from_code}','{to_code}','{flight_no}','{date}','{price}','{seats}','{airline}'],
];

$grouped   = $templates->groupBy('type');
$typeOrder = ['reengagement','time_based','vendor','loyalty','eticket'];
@endphp

@foreach($typeOrder as $type)
@php
    $group  = $grouped->get($type, collect());
    $cfg    = $typeConfig[$type];
@endphp

<div class="an-section">

    {{-- Section header -------------------------------------------------- --}}
    <div class="an-section-header an-sec-{{ $cfg['css'] }}">
        <span class="an-sec-emoji">{{ $cfg['emoji'] }}</span>
        <div>
            <div class="an-sec-title">{{ $cfg['label'] }}</div>
            <div class="an-sec-desc">{{ $cfg['desc'] }}</div>
        </div>
        <span class="an-sec-count">{{ $group->count() }} template{{ $group->count() !== 1 ? 's' : '' }}</span>
    </div>

    {{-- ── eTicket: custom layout ──────────────────────────────────────── --}}
    @if($type === 'eticket')
    @php $flightTpl = $group->firstWhere('slug','eticket_upcoming_flight'); @endphp
    @if($flightTpl)

    <div class="an-card {{ true ? 'open' : '' }}" id="card-eticket_upcoming_flight">
        <div class="an-card-inner type-eticket">
            {{-- Trigger row --}}
            <div class="an-acc-trigger" onclick="toggleCard('eticket_upcoming_flight')">
                <span class="an-status-dot {{ $flightTpl->is_active ? 'active' : 'paused' }}" id="dot-eticket_upcoming_flight"></span>
                <div style="flex:1;">
                    <div class="an-tpl-label">{{ $flightTpl->label }}</div>
                    <div class="an-tpl-meta">
                        <span class="an-meta-chip">⏱ Every {{ $flightTpl->interval_hours }}h</span>
                        <span class="an-meta-chip">🎯 All users</span>
                        <span class="an-meta-chip">📅 Last: {{ $flightTpl->last_sent_at ? \Carbon\Carbon::parse($flightTpl->last_sent_at)->diffForHumans() : 'Never' }}</span>
                    </div>
                </div>
                <span class="an-status-pill {{ $flightTpl->is_active ? 'active' : 'paused' }}" id="pill-eticket_upcoming_flight">
                    {{ $flightTpl->is_active ? 'Active' : 'Paused' }}
                </span>
                <div class="an-toggle-wrap" onclick="event.stopPropagation()">
                    <label class="an-toggle">
                        <input type="checkbox" {{ $flightTpl->is_active ? 'checked' : '' }}
                            onchange="toggleTemplate('eticket_upcoming_flight', this)">
                        <span class="an-toggle-slider"></span>
                    </label>
                </div>
                <span class="an-acc-chevron">▼</span>
            </div>

            {{-- Accordion body --}}
            <div class="an-acc-body">
                <form action="{{ route('admin.notifications.auto.update', 'eticket_upcoming_flight') }}" method="POST">
                    @csrf
                    {{-- Language selector --}}
                    <div class="an-lang-selector-row">
                        <label>🌐 Send Language</label>
                        <select name="language" class="form-control">
                            <option value="en"   {{ ($flightTpl->language ?? 'en') === 'en'   ? 'selected' : '' }}>🇬🇧 English only</option>
                            <option value="so"   {{ ($flightTpl->language ?? '') === 'so'   ? 'selected' : '' }}>🇸🇴 Somali only</option>
                            <option value="both" {{ ($flightTpl->language ?? '') === 'both' ? 'selected' : '' }}>🌐 Both languages (sends Somali)</option>
                        </select>
                    </div>

                    {{-- Bilingual content + phone preview --}}
                    <div style="display:grid;grid-template-columns:1fr 1fr 280px;gap:0;">
                        {{-- EN --}}
                        <div class="an-lang-col">
                            <div class="an-lang-header">
                                <span class="an-lang-flag">🇬🇧</span>
                                <span class="an-lang-name">English</span>
                            </div>
                            <div class="an-field">
                                <label>Title</label>
                                <input type="text" name="title_template" class="form-control" id="ft_title_en" value="{{ $flightTpl->title_template }}">
                            </div>
                            <div class="an-field">
                                <label>Body</label>
                                <textarea name="body_template" class="form-control" rows="4" id="ft_body_en">{{ $flightTpl->body_template }}</textarea>
                            </div>
                        </div>

                        {{-- SO --}}
                        <div class="an-lang-col" style="border-left:1px solid var(--border);">
                            <div class="an-lang-header">
                                <span class="an-lang-flag">🇸🇴</span>
                                <span class="an-lang-name">Somali</span>
                                <span class="an-lang-native">Af Soomaali</span>
                            </div>
                            <div class="an-field">
                                <label>Cinwaan (Title)</label>
                                <input type="text" name="title_so" class="form-control" value="{{ $flightTpl->title_so ?? '' }}" placeholder="Cinwaanka ogeysiiska…">
                            </div>
                            <div class="an-field">
                                <label>Qoraalka (Body)</label>
                                <textarea name="body_so" class="form-control" rows="4" placeholder="Qoraalka ogeysiiska…">{{ $flightTpl->body_so ?? '' }}</textarea>
                            </div>
                        </div>

                        {{-- Live preview --}}
                        <div style="border-left:1px solid var(--border);padding:20px 16px;background:var(--bg);">
                            <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:10px;">📱 Live Preview</div>
                            <div class="an-phone-preview">
                                <div class="an-notif-bubble">
                                    <div class="an-notif-app-icon">✈️</div>
                                    <div>
                                        <div class="an-notif-title" id="preview_ft_title">{{ $flightTpl->title_template }}</div>
                                        <div class="an-notif-body"  id="preview_ft_body">{{ $flightTpl->body_template }}</div>
                                    </div>
                                </div>
                            </div>
                            @if($upcomingFlights->count())
                            <div style="margin-top:14px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:8px;">Next Flights</div>
                            @foreach($upcomingFlights->take(3) as $fl)
                            @php $cls = json_decode($fl->seat_classes ?? '{}', true) ?? []; $ep = array_values($cls)[0] ?? 0; @endphp
                            <div style="display:flex;align-items:center;gap:9px;padding:8px 10px;background:var(--surface);border-radius:9px;margin-bottom:6px;border:1px solid var(--border);">
                                <div style="width:26px;height:26px;background:var(--brand);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:900;flex-shrink:0;">✈</div>
                                <div>
                                    <div style="font-weight:900;font-size:12px;color:var(--text);">{{ strtoupper($fl->from_code) }} → {{ strtoupper($fl->to_code) }}</div>
                                    <div style="font-size:10px;color:var(--text-muted);">{{ $fl->flight_number }} · {{ \Carbon\Carbon::parse($fl->departure_at)->format('M j') }} · {{ $fl->available_seats }} seats</div>
                                </div>
                            </div>
                            @endforeach
                            @endif
                        </div>
                    </div>

                    {{-- Variables --}}
                    <div class="an-vars-row">
                        <span class="an-var-label">Variables:</span>
                        @foreach($templateVars['eticket'] as $v)
                            <span class="an-var-chip" onclick="copyVar('{{ $v }}')">{{ $v }}</span>
                        @endforeach
                    </div>

                    {{-- Form footer --}}
                    <div class="an-form-footer">
                        <div class="an-form-footer-left">
                            <div class="an-interval-wrap">
                                <label>Interval</label>
                                <input type="number" name="interval_hours" class="form-control" value="{{ $flightTpl->interval_hours }}" min="1" max="168">
                                <span style="font-size:12px;color:var(--text-muted);">hours</span>
                            </div>
                        </div>
                        <button type="submit" class="btn-save-tpl">
                            <i class="fa-solid fa-floppy-disk"></i> Save Template
                        </button>
                    </div>
                </form>

                {{-- Send footer --}}
                <div class="an-send-footer">
                    <div class="an-send-meta">
                        <span>📡 Reach: <strong>{{ number_format($stats['users_with_fcm']) }} users</strong></span>
                        <span>📤 Sent today: <strong>{{ ($logs['eticket_upcoming_flight'] ?? collect())->filter(fn($l) => \Carbon\Carbon::parse($l->created_at)->isToday())->sum('sent_count') }}</strong></span>
                    </div>
                    <form action="{{ route('admin.notifications.auto.send-now', 'eticket_upcoming_flight') }}" method="POST" style="display:inline;"
                        onsubmit="return confirm('Send flight notifications to all {{ number_format($stats['users_with_fcm']) }} users now?')">
                        @csrf
                        <button type="submit" class="btn-send-now"><i class="fa-solid fa-paper-plane"></i> Send Now</button>
                    </form>
                </div>

                {{-- Recent logs --}}
                @if(($logs['eticket_upcoming_flight'] ?? collect())->count() > 0)
                <div class="an-log-wrap">
                    <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:8px;padding-top:16px;">Recent Send History</div>
                    <div class="an-log-mini">
                        <div class="an-log-head">
                            <div>Sent At</div><div>Reference</div><div>Title</div><div>Recipients</div>
                        </div>
                        @foreach(($logs['eticket_upcoming_flight'] ?? collect())->take(5) as $log)
                        <div class="an-log-row">
                            <div>{{ \Carbon\Carbon::parse($log->created_at)->format('M j, g:ia') }}</div>
                            <div>{{ $log->reference_type }} #{{ $log->reference_id }}</div>
                            <div style="color:var(--text-muted);">{{ \Str::limit($log->title, 40) }}</div>
                            <div class="an-log-sent">{{ number_format($log->sent_count) }}</div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>{{-- /acc-body --}}
        </div>
    </div>

    @else
        <div class="an-empty">eTicket template not found. Run <code>php artisan migrate</code>.</div>
    @endif

    @else
    {{-- ── Generic types: accordion loop ───────────────────────────────── --}}
    @forelse($group as $tpl)
    @php
        $slug      = $tpl->slug;
        $todaySent = ($logs[$slug] ?? collect())->filter(fn($l) => \Carbon\Carbon::parse($l->created_at)->isToday())->sum('sent_count');
        $hasLogs   = ($logs[$slug] ?? collect())->count() > 0;
        $vars      = $templateVars[$type] ?? [];
    @endphp

    <div class="an-card" id="card-{{ $slug }}">
        <div class="an-card-inner type-{{ $type }}">
            {{-- Trigger row --}}
            <div class="an-acc-trigger" onclick="toggleCard('{{ $slug }}')">
                <span class="an-status-dot {{ $tpl->is_active ? 'active' : 'paused' }}" id="dot-{{ $slug }}"></span>
                <div style="flex:1;min-width:0;">
                    <div class="an-tpl-label">{{ $tpl->label }}</div>
                    <div class="an-tpl-meta">
                        @if($tpl->send_time)<span class="an-meta-chip">⏰ {{ $tpl->send_time }}</span>@endif
                        <span class="an-meta-chip">⏱ Every {{ $tpl->interval_hours }}h</span>
                        <span class="an-meta-chip">🎯 {{ $tpl->target_audience ?? 'All users' }}</span>
                        <span class="an-meta-chip">📅 {{ $tpl->last_sent_at ? \Carbon\Carbon::parse($tpl->last_sent_at)->diffForHumans() : 'Never sent' }}</span>
                    </div>
                </div>
                <span class="an-status-pill {{ $tpl->is_active ? 'active' : 'paused' }}" id="pill-{{ $slug }}">
                    {{ $tpl->is_active ? 'Active' : 'Paused' }}
                </span>
                <div class="an-toggle-wrap" onclick="event.stopPropagation()">
                    <label class="an-toggle">
                        <input type="checkbox" {{ $tpl->is_active ? 'checked' : '' }}
                            onchange="toggleTemplate('{{ $slug }}', this)">
                        <span class="an-toggle-slider"></span>
                    </label>
                </div>
                <span class="an-acc-chevron">▼</span>
            </div>

            {{-- Accordion body --}}
            <div class="an-acc-body">
                <form action="{{ route('admin.notifications.auto.update', $slug) }}" method="POST">
                    @csrf
                    {{-- Language selector --}}
                    <div class="an-lang-selector-row">
                        <label>🌐 Send Language</label>
                        <select name="language" class="form-control">
                            <option value="en"   {{ ($tpl->language ?? 'en') === 'en'   ? 'selected' : '' }}>🇬🇧 English only</option>
                            <option value="so"   {{ ($tpl->language ?? '') === 'so'   ? 'selected' : '' }}>🇸🇴 Somali only</option>
                            <option value="both" {{ ($tpl->language ?? '') === 'both' ? 'selected' : '' }}>🌐 Both languages (sends Somali)</option>
                        </select>
                    </div>

                    {{-- Bilingual content --}}
                    <div class="an-form-wrap" style="padding:0;">
                        <div class="an-lang-grid">
                            {{-- English --}}
                            <div class="an-lang-col">
                                <div class="an-lang-header">
                                    <span class="an-lang-flag">🇬🇧</span>
                                    <span class="an-lang-name">English</span>
                                </div>
                                <div class="an-field">
                                    <label>Title</label>
                                    <input type="text" name="title_template" class="form-control"
                                        value="{{ $tpl->title_template }}" placeholder="Notification title…">
                                </div>
                                <div class="an-field">
                                    <label>Body</label>
                                    <textarea name="body_template" class="form-control" rows="3"
                                        placeholder="Notification body…">{{ $tpl->body_template }}</textarea>
                                </div>
                            </div>
                            {{-- Somali --}}
                            <div class="an-lang-col">
                                <div class="an-lang-header">
                                    <span class="an-lang-flag">🇸🇴</span>
                                    <span class="an-lang-name">Somali</span>
                                    <span class="an-lang-native">Af Soomaali</span>
                                </div>
                                <div class="an-field">
                                    <label>Cinwaan (Title)</label>
                                    <input type="text" name="title_so" class="form-control"
                                        value="{{ $tpl->title_so ?? '' }}" placeholder="Cinwaanka ogeysiiska…">
                                </div>
                                <div class="an-field">
                                    <label>Qoraalka (Body)</label>
                                    <textarea name="body_so" class="form-control" rows="3"
                                        placeholder="Qoraalka ogeysiiska…">{{ $tpl->body_so ?? '' }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Variables row --}}
                    @if(!empty($vars))
                    <div class="an-vars-row">
                        <span class="an-var-label">Variables:</span>
                        @foreach($vars as $v)
                            <span class="an-var-chip" onclick="copyVar('{{ $v }}')">{{ $v }}</span>
                        @endforeach
                    </div>
                    @endif

                    {{-- Form footer --}}
                    <div class="an-form-footer">
                        <div class="an-form-footer-left">
                            <div class="an-interval-wrap">
                                <label>Interval</label>
                                <input type="number" name="interval_hours" class="form-control"
                                    value="{{ $tpl->interval_hours }}" min="1" max="720">
                                <span style="font-size:12px;color:var(--text-muted);">hours</span>
                            </div>
                            <div class="an-interval-wrap" style="margin-left:16px;">
                                <label>⏰ Send Time</label>
                                <input type="time" name="send_time" class="form-control"
                                    value="{{ $tpl->send_time ?? '' }}" style="width:120px;">
                                <span style="font-size:12px;color:var(--text-muted);">(server time)</span>
                            </div>
                        </div>
                        <button type="submit" class="btn-save-tpl">
                            <i class="fa-solid fa-floppy-disk"></i> Save Template
                        </button>
                    </div>
                </form>

                {{-- Send footer --}}
                <div class="an-send-footer">
                    <div class="an-send-meta">
                        <span>📡 Reach: <strong>{{ number_format($stats['users_with_fcm']) }} users</strong></span>
                        <span>📤 Sent today: <strong>{{ $todaySent }}</strong></span>
                        @if($tpl->last_sent_at)
                            <span>🕐 Last: <strong>{{ \Carbon\Carbon::parse($tpl->last_sent_at)->format('M j, g:ia') }}</strong></span>
                        @endif
                    </div>
                    <form action="{{ route('admin.notifications.auto.send-now', $slug) }}" method="POST" style="display:inline;"
                        onsubmit="return confirm('Send \'{{ addslashes($tpl->label) }}\' to all eligible users now?')">
                        @csrf
                        <button type="submit" class="btn-send-now"><i class="fa-solid fa-paper-plane"></i> Send Now</button>
                    </form>
                </div>

                {{-- Recent logs --}}
                @if($hasLogs)
                <div class="an-log-wrap">
                    <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:8px;padding-top:16px;">Recent Send History</div>
                    <div class="an-log-mini">
                        <div class="an-log-head">
                            <div>Sent At</div><div>Title</div><div>Recipients</div>
                        </div>
                        @foreach(($logs[$slug] ?? collect())->take(5) as $log)
                        <div class="an-log-row">
                            <div>{{ \Carbon\Carbon::parse($log->created_at)->format('M j, g:ia') }}</div>
                            <div style="color:var(--text-muted);">{{ \Str::limit($log->title, 50) }}</div>
                            <div class="an-log-sent">{{ number_format($log->sent_count) }}</div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>{{-- /acc-body --}}
        </div>
    </div>

    @empty
    <div class="an-empty">No templates in this category yet.</div>
    @endforelse
    @endif

</div>{{-- /an-section --}}
@endforeach

{{-- ── Discount Campaigns ───────────────────────────────────────────────── --}}
<div class="an-section">
    <div class="an-section-header an-sec-campaigns">
        <span class="an-sec-emoji">🔥</span>
        <div>
            <div class="an-sec-title">eFood — Discount Campaign Alerts</div>
            <div class="an-sec-desc">Per-campaign notification settings — normal every 2h, urgent (last 2h) every 30 min.</div>
        </div>
    </div>
    <div class="an-card" style="border-radius:0 0 14px 14px;">
        <div class="an-card-inner" style="border-left:4px solid #dc2626;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 20px 20px 24px;flex-wrap:wrap;gap:14px;">
                <div>
                    <div style="font-size:14px;font-weight:800;color:var(--text);">Managed per campaign</div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Each discount campaign has its own notification title, body, and pause control.</div>
                </div>
                <a href="{{ route('admin.notifications.discount-campaigns') }}" class="btn-send-now" style="background:linear-gradient(135deg,#dc2626,#b91c1c);">
                    <i class="fa-solid fa-fire"></i> Manage Campaigns
                </a>
            </div>
        </div>
    </div>
</div>

<script>
/* ── Accordion ─────────────────────────────────────────────── */
function toggleCard(slug) {
    const card = document.getElementById('card-' + slug);
    if (!card) return;
    card.classList.toggle('open');
}

/* ── Toggle via AJAX ─────────────────────────────────────────── */
function toggleTemplate(slug, checkbox) {
    const url = "{{ route('admin.notifications.auto.toggle', '__slug__') }}".replace('__slug__', slug);
    fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        const pill = document.getElementById('pill-' + slug);
        const dot  = document.getElementById('dot-' + slug);
        if (pill) { pill.textContent = data.is_active ? 'Active' : 'Paused'; pill.className = 'an-status-pill ' + (data.is_active ? 'active' : 'paused'); }
        if (dot)  { dot.className   = 'an-status-dot ' + (data.is_active ? 'active' : 'paused'); }
    })
    .catch(() => { checkbox.checked = !checkbox.checked; alert('Failed to update.'); });
}

/* ── Copy variable ───────────────────────────────────────────── */
function copyVar(v) {
    navigator.clipboard?.writeText(v).catch(() => {});
    const el   = event.target;
    const orig = el.textContent;
    el.textContent = '✓ Copied';
    el.style.background = 'rgba(99,102,241,.25)';
    setTimeout(() => { el.textContent = orig; el.style.background = ''; }, 900);
}

/* ── eTicket live preview ────────────────────────────────────── */
(function() {
    const EX = {
        '{from}':'Hargeysa','{to}':'Muqdisho','{from_code}':'HGA','{to_code}':'MGQ',
        '{flight_no}':'DA991','{date}':'Aug 15, 2026','{price}':'200','{seats}':'10','{airline}':'Daallo Airlines'
    };
    const tIn = document.getElementById('ft_title_en');
    const bIn = document.getElementById('ft_body_en');
    const pT  = document.getElementById('preview_ft_title');
    const pB  = document.getElementById('preview_ft_body');
    if (!tIn || !pT) return;
    function upd() {
        let t = tIn.value, b = bIn.value;
        for (const [k,v] of Object.entries(EX)) { t = t.replaceAll(k,v); b = b.replaceAll(k,v); }
        pT.textContent = t; pB.textContent = b;
    }
    tIn.addEventListener('input', upd); bIn.addEventListener('input', upd); upd();
})();
</script>

@endsection
