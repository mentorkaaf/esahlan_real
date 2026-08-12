@extends('admin.layouts.app')
@section('title', 'Discount Campaign Notifications')
@section('content')

<style>
/* ── Layout ── */
.dc-page-head { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
.dc-page-title { font-size:20px; font-weight:900; color:#1a1d2e; }
.dc-page-sub   { font-size:13px; color:#94a3b8; margin-top:3px; }
.dc-back-btn   { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:9px; background:#f0f2f6; color:#374151; font-size:13px; font-weight:700; text-decoration:none; border:1px solid #e2e8f0; }
.dc-back-btn:hover { background:#e4e7f0; }

/* ── Stats ── */
.dc-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
.dc-stat  { background:#fff; border:1px solid #e8ecf2; border-radius:12px; padding:16px 18px; }
.dc-stat-val { font-size:22px; font-weight:800; }
.dc-stat-lbl { font-size:11px; color:#94a3b8; margin-top:2px; font-weight:600; text-transform:uppercase; letter-spacing:.4px; }

/* ── Info box ── */
.dc-info { background:#f0f4ff; border:1px solid #c7d2fe; border-radius:10px; padding:12px 16px; margin-bottom:20px; font-size:12px; color:#3730a3; line-height:1.7; }

/* ── Alert ── */
.dc-alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; font-weight:600; }
.dc-alert-success { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
.dc-alert-error   { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

/* ── Campaign card ── */
.dc-card { background:#fff; border:1px solid #e8ecf2; border-radius:16px; overflow:hidden; margin-bottom:16px; box-shadow:0 2px 12px rgba(0,0,0,.05); transition:box-shadow .2s; }
.dc-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.08); }
.dc-card.paused { opacity:.75; border-color:#fde68a; }

/* Card header */
.dc-card-head { display:flex; align-items:center; gap:14px; padding:18px 20px; border-bottom:1px solid #f0f4f8; }
.dc-logo { width:50px; height:50px; border-radius:12px; object-fit:cover; background:#f0f2f6; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:22px; overflow:hidden; }
.dc-logo img { width:50px; height:50px; object-fit:cover; }
.dc-vendor-name  { font-size:16px; font-weight:800; color:#1a1d2e; }
.dc-campaign-meta{ font-size:12px; color:#64748b; margin-top:2px; }

/* Status badges */
.dc-badge { display:inline-flex; align-items:center; gap:4px; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700; }
.dc-badge.active    { background:#d1fae5; color:#065f46; }
.dc-badge.scheduled { background:#fef3c7; color:#92400e; }
.dc-badge.expired   { background:#f1f5f9; color:#94a3b8; }
.dc-badge.inactive  { background:#fee2e2; color:#991b1b; }
.dc-badge.paused-badge { background:#fef3c7; color:#b45309; }
.dc-badge.urgent    { background:#fee2e2; color:#991b1b; }

/* Progress bar */
.dc-progress { width:100%; height:5px; background:#f0f2f6; border-radius:3px; margin-top:8px; overflow:hidden; }
.dc-progress-fill { height:100%; border-radius:3px; transition:width .4s; }

/* Card sections */
.dc-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:0; border-bottom:1px solid #f0f4f8; }
.dc-info-cell { padding:14px 20px; border-right:1px solid #f0f4f8; }
.dc-info-cell:last-child { border-right:none; }
.dc-info-lbl { font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.6px; margin-bottom:4px; }
.dc-info-val { font-size:14px; font-weight:700; color:#1a1d2e; }

/* Action sections */
.dc-actions { padding:16px 20px; display:flex; flex-direction:column; gap:16px; }

/* Section label */
.dc-section-label { font-size:11px; font-weight:800; color:#374151; text-transform:uppercase; letter-spacing:.6px; margin-bottom:8px; display:flex; align-items:center; gap:6px; }
.dc-section-label span { color:#94a3b8; font-weight:500; text-transform:none; letter-spacing:0; }

/* Inputs */
.dc-input { width:100%; padding:9px 13px; border:1.5px solid #e2e8f0; border-radius:9px; font-size:13px; color:#1a1d2e; box-sizing:border-box; transition:border-color .2s; font-family:inherit; }
.dc-input:focus { outline:none; border-color:#140465; box-shadow:0 0 0 3px rgba(20,4,101,.07); }
textarea.dc-input { resize:vertical; min-height:60px; }
.dc-input-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.dc-input-group { display:flex; flex-direction:column; gap:4px; }
.dc-input-lbl { font-size:11px; font-weight:600; color:#374151; }

/* Buttons */
.dc-btn { padding:9px 18px; border-radius:9px; font-size:13px; font-weight:700; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all .15s; }
.dc-btn-primary { background:#140465; color:#fff; }
.dc-btn-primary:hover { background:#1a0580; }
.dc-btn-danger  { background:#ef4444; color:#fff; }
.dc-btn-danger:hover { background:#dc2626; }
.dc-btn-success { background:#10b981; color:#fff; }
.dc-btn-success:hover { background:#059669; }
.dc-btn-orange  { background:#f97316; color:#fff; }
.dc-btn-orange:hover { background:#ea580c; }
.dc-btn-outline { background:#fff; color:#374151; border:1.5px solid #e2e8f0; }
.dc-btn-outline:hover { background:#f8f9fb; border-color:#cbd5e1; }
.dc-btn-sm { font-size:12px; padding:7px 14px; }
.dc-btn-xs { font-size:11px; padding:5px 10px; border-radius:7px; }

/* Pause toggle */
.dc-pause-btn-paused  { background:#fef3c7; color:#92400e; border:1.5px solid #fcd34d; }
.dc-pause-btn-paused:hover { background:#fde68a; }
.dc-pause-btn-active  { background:#f0f2f6; color:#374151; border:1.5px solid #e2e8f0; }
.dc-pause-btn-active:hover { background:#e4e7f0; }

/* Footer row */
.dc-footer { padding:14px 20px; background:#fafbfd; border-top:1px solid #f0f4f8; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
.dc-footer-hint { font-size:11px; color:#94a3b8; }

/* Send form inline */
.dc-send-row { display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; }
.dc-send-inp { flex:1; min-width:160px; }

/* Divider */
.dc-divider { height:1px; background:#f0f4f8; margin:0 -20px; }

/* Empty state */
.dc-empty { background:#fff; border:1px solid #e8ecf2; border-radius:16px; padding:48px; text-align:center; }

@media(max-width:900px) {
    .dc-stats { grid-template-columns:1fr 1fr; }
    .dc-grid-3 { grid-template-columns:1fr 1fr; }
    .dc-input-row { grid-template-columns:1fr; }
    .dc-grid-3 .dc-info-cell:nth-child(2) { border-right:none; }
}
</style>

{{-- Page header --}}
<div class="dc-page-head">
    <div>
        <div class="dc-page-title">📢 Discount Campaign Notifications</div>
        <div class="dc-page-sub">Manage templates, schedules, and send push notifications for discount campaigns</div>
    </div>
    <a href="{{ route('admin.notifications.index') }}" class="dc-back-btn">← Back to Notifications</a>
</div>

@if(session('success'))
    <div class="dc-alert dc-alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="dc-alert dc-alert-error">{{ session('error') }}</div>
@endif

{{-- Stats --}}
@php
    $allCampaigns  = $campaigns->getCollection();
    $activeCount   = $allCampaigns->filter(fn($c) => $c->status === 'active')->count();
    $pausedCount   = $allCampaigns->filter(fn($c) => $c->notif_paused)->count();
    $urgentCount   = $allCampaigns->filter(fn($c) => $c->isLive() && $now->diffInMinutes($c->ends_at, false) <= 120)->count();
@endphp
<div class="dc-stats">
    <div class="dc-stat">
        <div class="dc-stat-val" style="color:#1a1d2e;">{{ $campaigns->total() }}</div>
        <div class="dc-stat-lbl">Total Campaigns</div>
    </div>
    <div class="dc-stat">
        <div class="dc-stat-val" style="color:#059669;">{{ $activeCount }}</div>
        <div class="dc-stat-lbl">Currently Active</div>
    </div>
    <div class="dc-stat">
        <div class="dc-stat-val" style="color:#ef4444;">{{ $urgentCount }}</div>
        <div class="dc-stat-lbl">⚡ Ending &lt;2h</div>
    </div>
    <div class="dc-stat">
        <div class="dc-stat-val" style="color:#140465;">{{ number_format($userCount) }}</div>
        <div class="dc-stat-lbl">Devices with Token</div>
    </div>
</div>

{{-- Info --}}
<div class="dc-info">
    <strong>🤖 Auto-send:</strong> Scheduler sends notifications automatically based on each campaign's interval (default 2h, urgent last-2h = 30min).
    <strong>⏸ Pause:</strong> Pausing stops auto-sends but you can still send manually.
    <strong>✏️ Template:</strong> Custom title/body overrides the auto-generated text for all sends.
    <strong>📤 Manual send:</strong> Sends immediately to ~{{ number_format($userCount) }} devices. Duplicates blocked per interval window.
</div>

{{-- Campaign cards --}}
@forelse($campaigns as $campaign)
@php
    $status   = $campaign->status;
    $isLive   = $campaign->isLive();
    $isPaused = (bool) $campaign->notif_paused;
    $vendor   = $campaign->vendor;
    $cat      = $campaign->category;
    $endsAt   = $campaign->ends_at;
    $startsAt = $campaign->starts_at;
    $minLeft  = $isLive ? (int) $now->diffInMinutes($endsAt, false) : null;
    $urgent   = $isLive && $minLeft !== null && $minLeft <= 120;
    $discount = $campaign->discount_type === 'percentage'
        ? "{$campaign->discount_value}% OFF"
        : '$' . number_format($campaign->discount_value, 0) . ' OFF';
    $deepLink    = '/vendor/' . $campaign->vendor_id;
    $totalMin    = $isLive ? (int) $startsAt->diffInMinutes($endsAt) : 0;
    $elapsedMin  = $isLive ? (int) $startsAt->diffInMinutes($now) : 0;
    $progress    = $totalMin > 0 ? min(100, round($elapsedMin / $totalMin * 100)) : 0;
    $barColor    = $progress > 75 ? '#ef4444' : ($progress > 50 ? '#f59e0b' : '#10b981');
    $intervalHrs = $campaign->notif_interval_hours ?? 2;
    $hasCustom   = !empty($campaign->notif_title);
@endphp

<div class="dc-card {{ $isPaused ? 'paused' : '' }}">

    {{-- ── Header ── --}}
    <div class="dc-card-head">
        <div class="dc-logo">
            @if($vendor?->logo)
                <img src="{{ cdn_url($vendor->logo) }}" alt="{{ $vendor->name }}">
            @else
                🍽️
            @endif
        </div>
        <div style="flex:1;min-width:0;">
            <div class="dc-vendor-name">{{ $vendor?->name ?? 'Unknown Vendor' }}</div>
            <div class="dc-campaign-meta">
                {{ $campaign->name }}
                @if($cat) · {{ $cat->name }}@endif
                · Campaign #{{ $campaign->id }}
            </div>
            @if($isLive)
                <div class="dc-progress" style="margin-top:6px;">
                    <div class="dc-progress-fill" style="width:{{ $progress }}%;background:{{ $barColor }};"></div>
                </div>
            @endif
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0;">
            <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;">
                <span class="dc-badge {{ $status }}">
                    @if($status==='active') ✅ Active
                    @elseif($status==='scheduled') 🕐 Scheduled
                    @elseif($status==='expired') ⏹ Expired
                    @else ❌ Inactive @endif
                </span>
                @if($isPaused)
                    <span class="dc-badge paused-badge">⏸ Notif Paused</span>
                @endif
                @if($urgent && !$isPaused)
                    <span class="dc-badge urgent">⚡ URGENT</span>
                @endif
                @if($hasCustom)
                    <span class="dc-badge" style="background:#ede9fe;color:#5b21b6;">✏️ Custom Text</span>
                @endif
            </div>
            {{-- Pause/Resume toggle --}}
            @if($isLive || $status==='scheduled')
            <form method="POST" action="{{ route('admin.notifications.discount-campaigns.pause', $campaign->id) }}" style="margin:0;">
                @csrf
                <button type="submit" class="dc-btn dc-btn-xs {{ $isPaused ? 'dc-pause-btn-paused' : 'dc-pause-btn-active' }}">
                    {{ $isPaused ? '▶️ Resume Auto-send' : '⏸ Pause Auto-send' }}
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- ── Info grid ── --}}
    <div class="dc-grid-3">
        <div class="dc-info-cell">
            <div class="dc-info-lbl">Discount</div>
            <div class="dc-info-val" style="color:#140465;font-size:16px;">{{ $discount }}</div>
        </div>
        <div class="dc-info-cell">
            <div class="dc-info-lbl">Campaign Duration</div>
            <div class="dc-info-val" style="font-size:12px;">
                {{ $startsAt->format('M j, g:ia') }}<br>
                <span style="color:#94a3b8;">→ {{ $endsAt->format('M j, g:ia') }}</span>
            </div>
        </div>
        <div class="dc-info-cell">
            <div class="dc-info-lbl">Time Left / Status</div>
            <div class="dc-info-val" style="color:{{ $urgent ? '#ef4444' : '#1a1d2e' }}">
                @if($isLive)
                    {{ $minLeft !== null && $minLeft <= 60 ? $minLeft . ' min' : $endsAt->diffForHumans($now) }}
                @elseif($status === 'scheduled')
                    Starts {{ $startsAt->diffForHumans() }}
                @else —
                @endif
            </div>
        </div>
    </div>

    {{-- ── Action sections ── --}}
    <div class="dc-actions">

        {{-- 1. NOTIFICATION TEMPLATE --}}
        <div>
            <div class="dc-section-label">
                ✏️ Notification Template
                <span>— Customize the text sent to users (leave blank to auto-generate)</span>
            </div>
            <form method="POST" action="{{ route('admin.notifications.discount-campaigns.template', $campaign->id) }}">
                @csrf
                <div class="dc-input-row" style="margin-bottom:10px;">
                    <div class="dc-input-group">
                        <label class="dc-input-lbl">Title (max 255 chars)</label>
                        <input type="text" name="notif_title" class="dc-input"
                            value="{{ $campaign->notif_title }}"
                            placeholder="{{ $urgent ? '⏰ Hurry! '.$discount.' at '.($vendor->name??'') : '🔥 '.$discount.' at '.($vendor->name??'') }}">
                    </div>
                    <div class="dc-input-group">
                        <label class="dc-input-lbl">Body / Message</label>
                        <input type="text" name="notif_body" class="dc-input"
                            value="{{ $campaign->notif_body }}"
                            placeholder="Auto-generate if empty">
                    </div>
                </div>

                {{-- Schedule / interval --}}
                <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                    <div class="dc-input-group" style="flex-shrink:0;">
                        <label class="dc-input-lbl">Auto-send interval</label>
                        <select name="notif_interval_hours" class="dc-input" style="width:auto;padding:8px 12px;">
                            @foreach([1,2,3,4,6,8,12,24] as $h)
                                <option value="{{ $h }}" {{ $intervalHrs == $h ? 'selected' : '' }}>
                                    Every {{ $h }}h
                                    @if($h==2) (default) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div style="flex:1;"></div>
                    <button type="submit" class="dc-btn dc-btn-primary dc-btn-sm" style="align-self:flex-end;">
                        💾 Save Template
                    </button>
                    @if($hasCustom)
                        <button type="submit" name="notif_title" value="" class="dc-btn dc-btn-outline dc-btn-sm" style="align-self:flex-end;"
                            onclick="this.form.notif_title.value=''; this.form.notif_body.value='';"
                            title="Clear custom text, revert to auto-generate">
                            🔄 Reset to Auto
                        </button>
                    @endif
                </div>

                {{-- Preview of what will be sent --}}
                <div style="margin-top:10px;background:#f8f9fb;border:1px solid #e8ecf2;border-radius:9px;padding:12px;font-size:12px;">
                    <div style="font-weight:700;color:#374151;margin-bottom:4px;">📱 Preview:</div>
                    <div id="preview-title-{{ $campaign->id }}" style="font-weight:700;color:#1a1d2e;">
                        {{ $campaign->notif_title ?: ($urgent ? '⏰ Hurry! '.$discount.' at '.($vendor->name??'').' — '.($minLeft ?? '?').' min left!' : '🔥 '.$discount.' at '.($vendor->name??'').'!') }}
                    </div>
                    <div id="preview-body-{{ $campaign->id }}" style="color:#64748b;margin-top:2px;">
                        {{ $campaign->notif_body ?: ($cat ? 'Get '.$discount.' on '.$cat->name.' at '.($vendor->name??'').'. Ends '.$endsAt->format('M j, g:ia').'!' : 'Get '.$discount.' at '.($vendor->name??'').'. Ends '.$endsAt->format('M j, g:ia').'!') }}
                    </div>
                    <div style="margin-top:4px;color:#94a3b8;">Deep link: <code style="font-size:11px;background:#eef0f6;padding:1px 6px;border-radius:4px;">{{ $deepLink }}</code></div>
                </div>
            </form>
        </div>

        <div class="dc-divider" style="margin:0;"></div>

        {{-- 2. SEND NOW --}}
        @if($isLive)
        <div>
            <div class="dc-section-label">
                📤 Send Now
                <span>— Immediately notify all {{ number_format($userCount) }} devices (2h duplicate prevention)</span>
            </div>
            <form method="POST" action="{{ route('admin.notifications.discount-campaigns.send', $campaign->id) }}"
                  onsubmit="return confirm('Send notification to ~{{ number_format($userCount) }} devices for {{ addslashes($vendor->name??'this campaign') }}?');">
                @csrf
                <div class="dc-send-row">
                    <input type="text" name="title" class="dc-input dc-send-inp"
                        placeholder="Override title (blank = use template/auto)">
                    <input type="text" name="body" class="dc-input dc-send-inp"
                        placeholder="Override body (blank = use template/auto)">
                    <button type="submit" class="dc-btn {{ $urgent ? 'dc-btn-danger' : 'dc-btn-orange' }}">
                        {{ $urgent ? '⚡ Send Urgent' : '📤 Send Now' }}
                    </button>
                </div>
            </form>
        </div>
        @else
        <div style="font-size:12px;color:#94a3b8;padding:4px 0;">
            @if($status==='scheduled') ⏳ Campaign starts {{ $startsAt->format('M j, g:ia') }} — manual send available then.
            @else ⏹ Campaign ended. No notifications can be sent.
            @endif
        </div>
        @endif

    </div>
</div>
@empty
<div class="dc-empty">
    <div style="font-size:48px;margin-bottom:12px;">📭</div>
    <div style="font-size:16px;font-weight:800;color:#1a1d2e;margin-bottom:6px;">No campaigns found</div>
    <div style="font-size:13px;color:#94a3b8;">Create discount campaigns in the eFood section to manage their notifications here.</div>
</div>
@endforelse

{{-- Pagination --}}
<div style="margin-top:20px;display:flex;justify-content:center;">
    {{ $campaigns->links() }}
</div>

@endsection
