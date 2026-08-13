@extends('admin.layouts.app')
@section('title', 'Auto Notifications')
@section('content')

<style>
/* ── Layout ────────────────────────────────────────────────── */
.an-head      { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px; }
.an-title     { font-size:20px;font-weight:900;color:var(--text); }
.an-sub       { font-size:13px;color:var(--text-muted);margin-top:3px; }
.an-stats     { display:flex;gap:14px;flex-wrap:wrap;margin-bottom:22px; }
.an-stat      { background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 20px;min-width:160px; }
.an-stat-val  { font-size:22px;font-weight:800;color:var(--text); }
.an-stat-lbl  { font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-top:2px; }

/* ── Section ───────────────────────────────────────────────── */
.an-section   { margin-bottom:32px; }
.an-sec-head  { display:flex;align-items:center;gap:10px;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid var(--border); }
.an-sec-icon  { width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0; }
.an-sec-title { font-size:16px;font-weight:800;color:var(--text); }

/* ── Template card ─────────────────────────────────────────── */
.an-card      { background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-bottom:16px;box-shadow:0 2px 12px rgba(0,0,0,.05); }
.an-card-head { display:flex;align-items:center;gap:14px;padding:16px 20px;border-bottom:1px solid var(--border); }
.an-card-body { padding:20px; }
.an-card-foot { padding:12px 20px;border-top:1px solid var(--border);background:var(--bg);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px; }

/* ── Type badges ───────────────────────────────────────────── */
.badge-reengagement { background:rgba(139,92,246,.15);color:#7c3aed;border:1px solid rgba(139,92,246,.3); }
.badge-time_based   { background:rgba(59,130,246,.15);color:#1d4ed8;border:1px solid rgba(59,130,246,.3); }
.badge-vendor       { background:rgba(16,185,129,.15);color:#047857;border:1px solid rgba(16,185,129,.3); }
.badge-loyalty      { background:rgba(245,158,11,.15);color:#b45309;border:1px solid rgba(245,158,11,.3); }
.badge-eticket      { background:rgba(20,184,166,.15);color:#0f766e;border:1px solid rgba(20,184,166,.3); }
.an-type-badge      { font-size:10px;padding:2px 9px;border-radius:20px;font-weight:700;text-transform:uppercase;letter-spacing:.4px; }

/* ── Toggle ────────────────────────────────────────────────── */
.an-toggle-wrap   { display:flex;align-items:center;gap:8px; }
.an-toggle        { position:relative;display:inline-block;width:44px;height:24px; }
.an-toggle input  { opacity:0;width:0;height:0; }
.an-toggle-slider { position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:26px;transition:.3s; }
.an-toggle-slider:before { content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s; }
.an-toggle input:checked + .an-toggle-slider { background:var(--brand); }
.an-toggle input:checked + .an-toggle-slider:before { transform:translateX(20px); }

/* ── Language tabs ─────────────────────────────────────────── */
.lang-tabs     { display:flex;gap:0;border-radius:8px;overflow:hidden;border:1px solid var(--border);margin-bottom:14px;width:fit-content; }
.lang-tab      { padding:6px 14px;font-size:12px;font-weight:700;cursor:pointer;background:var(--bg);color:var(--text-muted);border:none;transition:.2s; }
.lang-tab.active { background:var(--brand);color:#fff; }

/* ── Variable chips ────────────────────────────────────────── */
.an-vars      { display:flex;flex-wrap:wrap;gap:5px;margin-top:8px; }
.an-var-chip  { font-size:11px;padding:2px 8px;background:rgba(99,102,241,.1);color:#6366f1;border-radius:5px;font-family:monospace;cursor:pointer;border:1px solid rgba(99,102,241,.2); }
.an-var-chip:hover { background:rgba(99,102,241,.2); }

/* ── Log table ─────────────────────────────────────────────── */
.an-log-table { width:100%;border-collapse:collapse;font-size:12px; }
.an-log-table th { background:var(--bg);color:var(--text-muted);font-weight:700;padding:8px 12px;text-align:left;text-transform:uppercase;font-size:10px;letter-spacing:.5px; }
.an-log-table td { padding:10px 12px;border-bottom:1px solid var(--border);color:var(--text); }
.an-log-table tr:last-child td { border:none; }

/* ── Alerts ────────────────────────────────────────────────── */
.an-alert     { padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:16px;font-weight:600; }
.an-alert-s   { background:#d1fae5;color:#065f46;border:1px solid #6ee7b7; }
.an-alert-e   { background:#fee2e2;color:#991b1b;border:1px solid #fca5a5; }
.an-alert-i   { background:#eff6ff;color:#1e40af;border:1px solid #93c5fd; }

/* ── Bilingual columns ─────────────────────────────────────── */
.lang-col-grid { display:grid;grid-template-columns:1fr 1fr;gap:20px; }
@media(max-width:768px){ .lang-col-grid { grid-template-columns:1fr; } }
.lang-col-label { font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;padding:3px 8px;border-radius:5px;margin-bottom:8px;display:inline-block; }
.lang-en-label  { background:rgba(59,130,246,.1);color:#1d4ed8; }
.lang-so-label  { background:rgba(139,92,246,.1);color:#7c3aed; }

/* ── Preview ───────────────────────────────────────────────── */
.an-preview      { background:linear-gradient(135deg,#1a1d2e,#2d3161);border-radius:12px;padding:14px;margin-top:10px; }
.an-preview-notif { background:#fff;border-radius:9px;padding:11px 13px;display:flex;gap:10px;align-items:flex-start; }
.an-preview-icon  { width:32px;height:32px;border-radius:8px;background:#f97316;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0; }
.an-preview-title { font-size:12px;font-weight:700;color:#1a1d2e;margin-bottom:2px; }
.an-preview-body  { font-size:11px;color:#4b5563;line-height:1.4; }

/* ── Flight mini ───────────────────────────────────────────── */
.flight-mini       { display:flex;align-items:center;gap:10px;padding:9px 11px;background:var(--bg);border-radius:9px;margin-bottom:7px;border:1px solid var(--border); }
.flight-mini-codes { font-weight:900;font-size:13px;color:var(--text); }
.flight-mini-meta  { font-size:11px;color:var(--text-muted); }
</style>

{{-- ── Header ──────────────────────────────────────────────────────────── --}}
<div class="an-head">
    <div>
        <div class="an-title">🤖 Auto Notifications</div>
        <div class="an-sub">Manage automated push notification templates — re-engagement, time-based promos, new vendors, loyalty &amp; flights</div>
    </div>
    <a href="{{ route('admin.notifications.index') }}" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;background:var(--bg);color:var(--text);font-size:13px;font-weight:700;text-decoration:none;border:1px solid var(--border);">
        ← Back to Notifications
    </a>
</div>

@if(session('success'))
    <div class="an-alert an-alert-s">✓ {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="an-alert an-alert-e">✗ {{ session('error') }}</div>
@endif

{{-- ── Stats ─────────────────────────────────────────────────────────────── --}}
<div class="an-stats">
    <div class="an-stat">
        <div class="an-stat-val">{{ number_format($stats['users_with_fcm']) }}</div>
        <div class="an-stat-lbl">Users with FCM token</div>
    </div>
    <div class="an-stat">
        <div class="an-stat-val">{{ $templates->count() }}</div>
        <div class="an-stat-lbl">Total templates</div>
    </div>
    <div class="an-stat">
        <div class="an-stat-val">{{ $templates->where('is_active', true)->count() }}</div>
        <div class="an-stat-lbl">Active templates</div>
    </div>
    <div class="an-stat">
        <div class="an-stat-val">
            {{ collect($logs)->flatten(1)->filter(fn($l) => \Carbon\Carbon::parse($l->created_at)->isToday())->sum('sent_count') }}
        </div>
        <div class="an-stat-lbl">Sent today</div>
    </div>
</div>

@php
    /* Helpers */
    $typeColors = [
        'reengagement' => ['icon_bg'=>'rgba(139,92,246,.12)','icon_color'=>'#7c3aed','emoji'=>'🔄'],
        'time_based'   => ['icon_bg'=>'rgba(59,130,246,.12)', 'icon_color'=>'#1d4ed8','emoji'=>'⏰'],
        'vendor'       => ['icon_bg'=>'rgba(16,185,129,.12)', 'icon_color'=>'#047857','emoji'=>'🏪'],
        'loyalty'      => ['icon_bg'=>'rgba(245,158,11,.12)', 'icon_color'=>'#b45309','emoji'=>'⭐'],
        'eticket'      => ['icon_bg'=>'rgba(20,184,166,.12)', 'icon_color'=>'#0f766e','emoji'=>'✈️'],
    ];

    $typeLabels = [
        'reengagement' => 'Re-engagement — Win Back Inactive Users',
        'time_based'   => 'Time-Based Promotions',
        'vendor'       => 'Vendor Alerts',
        'loyalty'      => 'Loyalty & Wallet',
        'eticket'      => 'eTicket — Upcoming Flights',
    ];

    $sectionDescs = [
        'reengagement' => 'Automatically send notifications to users who haven\'t ordered in 3, 7, 14, or 30 days.',
        'time_based'   => 'Send scheduled promos at the right time — lunch, evening deals, and weekend specials.',
        'vendor'       => 'Notify nearby users when a new vendor opens in their district.',
        'loyalty'      => 'Remind users about expiring reward points and low ePay wallet balance.',
        'eticket'      => 'Notify all users about upcoming available flights (runs every 12 hours).',
    ];

    $templateVars = [
        'reengagement' => [],
        'time_based'   => [],
        'vendor'       => ['{store_name}','{module}','{district}'],
        'loyalty'      => ['{points}'],
        'eticket'      => ['{from}','{to}','{from_code}','{to_code}','{flight_no}','{date}','{price}','{seats}','{airline}'],
    ];

    $grouped = $templates->groupBy('type');
    $typeOrder = ['reengagement','time_based','vendor','loyalty','eticket'];
@endphp

@foreach($typeOrder as $type)
@php
    $group = $grouped->get($type, collect());
    $tc    = $typeColors[$type] ?? ['icon_bg'=>'rgba(100,116,139,.1)','icon_color'=>'#64748b','emoji'=>'📢'];
@endphp

<div class="an-section">
    <div class="an-sec-head">
        <div class="an-sec-icon" style="background:{{ $tc['icon_bg'] }};color:{{ $tc['icon_color'] }};">{{ $tc['emoji'] }}</div>
        <div>
            <div class="an-sec-title">{{ $typeLabels[$type] ?? ucfirst($type) }}</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">{{ $sectionDescs[$type] ?? '' }}</div>
        </div>
    </div>

    {{-- ── eTicket section: custom layout with flight preview ─────────── --}}
    @if($type === 'eticket')
        @php $flightTpl = $group->firstWhere('slug','eticket_upcoming_flight'); @endphp
        @if($flightTpl)
        <div class="an-card">
            <div class="an-card-head">
                <span class="an-type-badge badge-eticket">eTicket</span>
                <div style="flex:1;">
                    <div style="font-size:14px;font-weight:800;color:var(--text);">{{ $flightTpl->label }}</div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
                        Every <strong>{{ $flightTpl->interval_hours }}h</strong> ·
                        Last sent: <strong>{{ $flightTpl->last_sent_at ? \Carbon\Carbon::parse($flightTpl->last_sent_at)->diffForHumans() : 'Never' }}</strong>
                    </div>
                </div>
                <div class="an-toggle-wrap">
                    <span style="font-size:11px;color:var(--text-muted);font-weight:600;">{{ $flightTpl->is_active ? 'ACTIVE' : 'PAUSED' }}</span>
                    <label class="an-toggle">
                        <input type="checkbox" {{ $flightTpl->is_active ? 'checked' : '' }}
                            onchange="toggleTemplate('eticket_upcoming_flight', this)">
                        <span class="an-toggle-slider"></span>
                    </label>
                </div>
            </div>
            <div class="an-card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                    <div>
                        <form action="{{ route('admin.notifications.auto.update', 'eticket_upcoming_flight') }}" method="POST">
                            @csrf
                            {{-- Language selector --}}
                            <div style="margin-bottom:14px;">
                                <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:5px;">LANGUAGE</label>
                                <select name="language" class="form-control" style="max-width:200px;">
                                    <option value="en" {{ ($flightTpl->language ?? 'en') === 'en' ? 'selected' : '' }}>🇬🇧 English only</option>
                                    <option value="so" {{ ($flightTpl->language ?? '') === 'so' ? 'selected' : '' }}>🇸🇴 Somali only</option>
                                    <option value="both" {{ ($flightTpl->language ?? '') === 'both' ? 'selected' : '' }}>🌐 Both (sends Somali)</option>
                                </select>
                            </div>
                            <div class="lang-col-grid" style="gap:14px;margin-bottom:12px;">
                                <div>
                                    <span class="lang-col-label lang-en-label">🇬🇧 English</span>
                                    <div style="margin-bottom:8px;">
                                        <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px;">TITLE</label>
                                        <input type="text" name="title_template" class="form-control" value="{{ $flightTpl->title_template }}">
                                    </div>
                                    <div>
                                        <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px;">BODY</label>
                                        <textarea name="body_template" class="form-control" rows="3">{{ $flightTpl->body_template }}</textarea>
                                    </div>
                                </div>
                                <div>
                                    <span class="lang-col-label lang-so-label">🇸🇴 Somali</span>
                                    <div style="margin-bottom:8px;">
                                        <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px;">CINWAAN</label>
                                        <input type="text" name="title_so" class="form-control" value="{{ $flightTpl->title_so ?? '' }}" placeholder="Cinwaanka Soomaaliga…">
                                    </div>
                                    <div>
                                        <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px;">QORAALKA</label>
                                        <textarea name="body_so" class="form-control" rows="3" placeholder="Qoraalka Soomaaliga…">{{ $flightTpl->body_so ?? '' }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="an-vars">
                                <span style="font-size:11px;color:var(--text-muted);font-weight:600;align-self:center;">Variables:</span>
                                @foreach($templateVars['eticket'] as $v)
                                    <span class="an-var-chip" onclick="copyVar('{{ $v }}')">{{ $v }}</span>
                                @endforeach
                            </div>
                            <div style="margin-top:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                <div>
                                    <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px;">INTERVAL (hours)</label>
                                    <input type="number" name="interval_hours" class="form-control" style="width:100px;" value="{{ $flightTpl->interval_hours }}" min="1" max="168">
                                </div>
                                <div style="padding-top:18px;">
                                    <button type="submit" class="btn btn-primary" style="font-size:12px;">
                                        <i class="fa-solid fa-floppy-disk"></i> Save Template
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div>
                        <div style="font-size:11px;font-weight:700;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.4px;">Live Preview</div>
                        <div class="an-preview">
                            <div class="an-preview-notif">
                                <div class="an-preview-icon">✈️</div>
                                <div>
                                    <div class="an-preview-title" id="preview_eticket_title">{{ $flightTpl->title_template }}</div>
                                    <div class="an-preview-body" id="preview_eticket_body">{{ $flightTpl->body_template }}</div>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top:14px;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px;">Upcoming Flights</div>
                        @forelse($upcomingFlights as $fl)
                            @php $cls = json_decode($fl->seat_classes ?? '{}', true) ?? []; $price = array_values($cls)[0] ?? 0; @endphp
                            <div class="flight-mini">
                                <div style="width:28px;height:28px;background:var(--brand);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:900;flex-shrink:0;">✈</div>
                                <div>
                                    <div class="flight-mini-codes">{{ strtoupper($fl->from_code) }} → {{ strtoupper($fl->to_code) }}</div>
                                    <div class="flight-mini-meta">{{ $fl->flight_number }} · {{ \Carbon\Carbon::parse($fl->departure_at)->format('M j, g:ia') }} · {{ $fl->available_seats }} seats</div>
                                </div>
                            </div>
                        @empty
                            <div style="font-size:12px;color:var(--text-muted);padding:10px;text-align:center;background:var(--bg);border-radius:9px;">No upcoming flights</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="an-card-foot">
                <div style="font-size:12px;color:var(--text-muted);">
                    📡 Targets <strong>{{ number_format($stats['users_with_fcm']) }}</strong> users ·
                    📋 Sent today: <strong>{{ ($logs['eticket_upcoming_flight'] ?? collect())->filter(fn($l) => \Carbon\Carbon::parse($l->created_at)->isToday())->sum('sent_count') }}</strong>
                </div>
                <form action="{{ route('admin.notifications.auto.send-now', 'eticket_upcoming_flight') }}" method="POST" style="display:inline;"
                    onsubmit="return confirm('Send flight notifications to all {{ number_format($stats["users_with_fcm"]) }} users NOW?')">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="font-size:12px;">
                        <i class="fa-solid fa-paper-plane"></i> Send Now
                    </button>
                </form>
            </div>
        </div>

        @if(($logs['eticket_upcoming_flight'] ?? collect())->count() > 0)
        <div class="an-card" style="margin-top:10px;">
            <div class="an-card-head"><div style="font-size:13px;font-weight:800;color:var(--text);">📋 Recent Send History</div></div>
            <div style="overflow-x:auto;">
                <table class="an-log-table">
                    <thead><tr><th>Sent At</th><th>Reference</th><th>Title</th><th>Recipients</th></tr></thead>
                    <tbody>
                    @foreach(($logs['eticket_upcoming_flight'] ?? collect())->take(8) as $log)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($log->created_at)->format('M j, g:ia') }}</td>
                            <td>{{ $log->reference_type }} #{{ $log->reference_id }}</td>
                            <td>{{ \Str::limit($log->title, 55) }}</td>
                            <td><strong>{{ number_format($log->sent_count) }}</strong></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        @else
            <div class="an-alert an-alert-i">eTicket template not found. Run <code>php artisan migrate</code>.</div>
        @endif

    @else
    {{-- ── Generic section: loop through templates ──────────────────────── --}}
        @forelse($group as $tpl)
        @php
            $slug = $tpl->slug;
            $todaySent = ($logs[$slug] ?? collect())->filter(fn($l) => \Carbon\Carbon::parse($l->created_at)->isToday())->sum('sent_count');
        @endphp
        <div class="an-card">
            <div class="an-card-head">
                <span class="an-type-badge badge-{{ $type }}">{{ str_replace('_',' ', $type) }}</span>
                <div style="flex:1;">
                    <div style="font-size:14px;font-weight:800;color:var(--text);">{{ $tpl->label }}</div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
                        @if($tpl->send_time) ⏰ {{ $tpl->send_time }} · @endif
                        Every <strong>{{ $tpl->interval_hours }}h</strong> ·
                        Target: <strong>{{ $tpl->target_audience ?? 'all' }}</strong> ·
                        Last sent: <strong>{{ $tpl->last_sent_at ? \Carbon\Carbon::parse($tpl->last_sent_at)->diffForHumans() : 'Never' }}</strong>
                    </div>
                </div>
                <div class="an-toggle-wrap">
                    <span style="font-size:11px;color:var(--text-muted);font-weight:600;">{{ $tpl->is_active ? 'ACTIVE' : 'PAUSED' }}</span>
                    <label class="an-toggle">
                        <input type="checkbox" {{ $tpl->is_active ? 'checked' : '' }}
                            onchange="toggleTemplate('{{ $slug }}', this)">
                        <span class="an-toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="an-card-body">
                <form action="{{ route('admin.notifications.auto.update', $slug) }}" method="POST">
                    @csrf
                    {{-- Language selector --}}
                    <div style="margin-bottom:14px;">
                        <label style="font-size:12px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:6px;">LANGUAGE</label>
                        <select name="language" class="form-control" style="max-width:200px;">
                            <option value="en" {{ ($tpl->language ?? 'en') === 'en' ? 'selected' : '' }}>🇬🇧 English only</option>
                            <option value="so" {{ ($tpl->language ?? '') === 'so' ? 'selected' : '' }}>🇸🇴 Somali only</option>
                            <option value="both" {{ ($tpl->language ?? '') === 'both' ? 'selected' : '' }}>🌐 Both (sends Somali)</option>
                        </select>
                    </div>

                    <div class="lang-col-grid">
                        {{-- English --}}
                        <div>
                            <span class="lang-col-label lang-en-label">🇬🇧 English</span>
                            <div style="margin-bottom:10px;">
                                <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:5px;">TITLE</label>
                                <input type="text" name="title_template" class="form-control"
                                    value="{{ old('title_template', $tpl->title_template) }}"
                                    placeholder="Notification title…">
                            </div>
                            <div>
                                <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:5px;">BODY</label>
                                <textarea name="body_template" class="form-control" rows="3"
                                    placeholder="Notification body…">{{ old('body_template', $tpl->body_template) }}</textarea>
                            </div>
                        </div>

                        {{-- Somali --}}
                        <div>
                            <span class="lang-col-label lang-so-label">🇸🇴 Somali</span>
                            <div style="margin-bottom:10px;">
                                <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:5px;">CINWAAN</label>
                                <input type="text" name="title_so" class="form-control"
                                    value="{{ old('title_so', $tpl->title_so) }}"
                                    placeholder="Cinwaanka ogeysiiska…">
                            </div>
                            <div>
                                <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:5px;">QORAALKA</label>
                                <textarea name="body_so" class="form-control" rows="3"
                                    placeholder="Qoraalka ogeysiiska…">{{ old('body_so', $tpl->body_so) }}</textarea>
                            </div>
                        </div>
                    </div>

                    @if(!empty($templateVars[$type]))
                    <div class="an-vars" style="margin-top:12px;">
                        <span style="font-size:11px;color:var(--text-muted);font-weight:600;align-self:center;">Variables:</span>
                        @foreach($templateVars[$type] as $v)
                            <span class="an-var-chip" onclick="copyVar('{{ $v }}')">{{ $v }}</span>
                        @endforeach
                    </div>
                    @endif

                    <div style="margin-top:14px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                        <div>
                            <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:5px;">INTERVAL (hours)</label>
                            <input type="number" name="interval_hours" class="form-control" style="width:100px;"
                                value="{{ $tpl->interval_hours }}" min="1" max="720">
                        </div>
                        <div style="padding-top:18px;">
                            <button type="submit" class="btn btn-primary" style="font-size:12px;">
                                <i class="fa-solid fa-floppy-disk"></i> Save Template
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="an-card-foot">
                <div style="font-size:12px;color:var(--text-muted);">
                    📡 <strong>{{ number_format($stats['users_with_fcm']) }}</strong> users with FCM ·
                    📋 Sent today: <strong>{{ $todaySent }}</strong>
                    @if($tpl->last_sent_at)
                        · Last: <strong>{{ \Carbon\Carbon::parse($tpl->last_sent_at)->format('M j, g:ia') }}</strong>
                    @endif
                </div>
                <form action="{{ route('admin.notifications.auto.send-now', $slug) }}" method="POST" style="display:inline;"
                    onsubmit="return confirm('Send \'{{ addslashes($tpl->label) }}\' to all users NOW?')">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="font-size:12px;background:linear-gradient(135deg,#f97316,#ea580c);">
                        <i class="fa-solid fa-paper-plane"></i> Send Now
                    </button>
                </form>
            </div>
        </div>

        @if(($logs[$slug] ?? collect())->count() > 0)
        <div class="an-card" style="margin-top:10px;">
            <div class="an-card-head"><div style="font-size:13px;font-weight:800;color:var(--text);">📋 Recent Send History</div></div>
            <div style="overflow-x:auto;">
                <table class="an-log-table">
                    <thead><tr><th>Sent At</th><th>Type</th><th>Title</th><th>Recipients</th></tr></thead>
                    <tbody>
                    @foreach(($logs[$slug] ?? collect())->take(8) as $log)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($log->created_at)->format('M j, g:ia') }}</td>
                            <td>{{ $log->reference_type }}</td>
                            <td>{{ \Str::limit($log->title, 55) }}</td>
                            <td><strong>{{ number_format($log->sent_count) }}</strong></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @empty
            <div style="background:var(--bg);border:2px dashed var(--border);border-radius:14px;padding:24px;text-align:center;color:var(--text-muted);font-size:13px;">
                No templates in this category yet.
            </div>
        @endforelse
    @endif
</div>
@endforeach

{{-- ── Discount Campaigns link ─────────────────────────────────────────── --}}
<div class="an-section">
    <div class="an-sec-head">
        <div class="an-sec-icon" style="background:rgba(16,185,129,.12);color:#10b981;">🔥</div>
        <div>
            <div class="an-sec-title">eFood — Discount Campaign Alerts</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">Per-campaign notification settings — normal every 2h, urgent every 30 min.</div>
        </div>
    </div>
    <div class="an-card">
        <div class="an-card-body" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <div>
                <div style="font-size:14px;font-weight:700;color:var(--text);">Managed per campaign</div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Each discount campaign has its own notification settings (title, body, pause, interval).</div>
            </div>
            <a href="{{ route('admin.notifications.discount-campaigns') }}" class="btn btn-primary" style="font-size:13px;white-space:nowrap;">
                <i class="fa-solid fa-fire"></i> Manage Discount Campaigns
            </a>
        </div>
    </div>
</div>

<script>
// Toggle via AJAX
function toggleTemplate(slug, checkbox) {
    const url = "{{ route('admin.notifications.auto.toggle', '__slug__') }}".replace('__slug__', slug);
    fetch(url, { method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json'} })
        .then(r => r.json())
        .then(data => {
            const label = checkbox.closest('.an-toggle-wrap').querySelector('span');
            if (label) label.textContent = data.is_active ? 'ACTIVE' : 'PAUSED';
        })
        .catch(() => { checkbox.checked = !checkbox.checked; alert('Failed to update.'); });
}

// Copy variable to clipboard + show tooltip
function copyVar(v) {
    navigator.clipboard?.writeText(v).catch(() => {});
    const el = event.target;
    const orig = el.textContent;
    el.textContent = 'Copied!';
    el.style.background = 'rgba(99,102,241,.3)';
    setTimeout(() => { el.textContent = orig; el.style.background = ''; }, 900);
}

// eTicket live preview
@php $flightTpl = $templates->firstWhere('slug','eticket_upcoming_flight'); @endphp
@if($flightTpl)
(function() {
    const EXAMPLE = {
        '{from}':'Hargeysa', '{to}':'Muqdisho', '{from_code}':'HGA', '{to_code}':'MGQ',
        '{flight_no}':'DA991', '{date}':'Aug 15, 2026', '{price}':'200', '{seats}':'10', '{airline}':'Daallo Airlines'
    };
    const form = document.querySelector('[data-preview="eticket"]') || document.getElementById('eticket-form');
    const titleIn = document.querySelector('input[name="title_template"]');
    const bodyIn  = document.querySelector('textarea[name="body_template"]');
    const pTitle  = document.getElementById('preview_eticket_title');
    const pBody   = document.getElementById('preview_eticket_body');
    if (!pTitle || !titleIn) return;

    function updatePreview() {
        let t = titleIn.value, b = bodyIn.value;
        for (const [k,v] of Object.entries(EXAMPLE)) { t = t.replaceAll(k,v); b = b.replaceAll(k,v); }
        pTitle.textContent = t;
        pBody.textContent  = b;
    }
    titleIn.addEventListener('input', updatePreview);
    bodyIn.addEventListener('input', updatePreview);
    updatePreview();
})();
@endif
</script>

@endsection
