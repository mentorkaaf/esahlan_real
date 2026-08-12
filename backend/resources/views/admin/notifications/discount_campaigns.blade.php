@extends('admin.layouts.app')
@section('title', 'Discount Campaign Notifications')
@section('content')

<style>
/* ── Layout ── */
.dc-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; }
.dc-title   { font-size:20px; font-weight:800; color:#1a1d2e; }
.dc-subtitle{ font-size:13px; color:#94a3b8; margin-top:2px; }
.dc-back    { font-size:12px; color:#140465; text-decoration:none; display:flex; align-items:center; gap:5px; font-weight:600; }
.dc-back:hover { opacity:.8; }

/* ── Stats row ── */
.dc-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
.dc-stat  { background:#fff; border:1px solid #e8ecf2; border-radius:12px; padding:16px 18px; }
.dc-stat-val { font-size:22px; font-weight:800; color:#1a1d2e; }
.dc-stat-lbl { font-size:12px; color:#94a3b8; margin-top:2px; }

/* ── Campaign cards ── */
.dc-list { display:flex; flex-direction:column; gap:14px; }
.dc-card { background:#fff; border:1px solid #e8ecf2; border-radius:14px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.04); }
.dc-card-head { display:flex; align-items:center; gap:14px; padding:16px 20px; border-bottom:1px solid #f0f4f8; }
.dc-logo { width:48px; height:48px; border-radius:10px; object-fit:cover; background:#f0f2f6; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:20px; }
.dc-logo img { width:48px; height:48px; border-radius:10px; object-fit:cover; }
.dc-name  { font-size:15px; font-weight:800; color:#1a1d2e; }
.dc-meta  { font-size:12px; color:#64748b; margin-top:2px; }

.dc-badge { display:inline-flex; align-items:center; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.dc-badge.active   { background:#d1fae5; color:#065f46; }
.dc-badge.scheduled{ background:#fef3c7; color:#92400e; }
.dc-badge.expired  { background:#f1f5f9; color:#94a3b8; }
.dc-badge.inactive { background:#fee2e2; color:#991b1b; }

.dc-card-body { padding:16px 20px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }
.dc-field-lbl { font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; margin-bottom:3px; }
.dc-field-val { font-size:13px; font-weight:600; color:#1a1d2e; }

.dc-card-foot { padding:14px 20px; background:#fafbfd; border-top:1px solid #f0f4f8; display:flex; align-items:center; gap:10px; }

/* Send form */
.dc-send-form { display:flex; flex-direction:column; gap:10px; width:100%; }
.dc-send-row  { display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; }
.dc-inp { flex:1; min-width:180px; padding:8px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:13px; color:#1a1d2e; }
.dc-inp:focus { outline:none; border-color:#140465; box-shadow:0 0 0 3px rgba(20,4,101,.07); }
.dc-btn { padding:8px 18px; border-radius:8px; font-size:13px; font-weight:700; border:none; cursor:pointer; white-space:nowrap; }
.dc-btn-primary  { background:#140465; color:#fff; }
.dc-btn-primary:hover { background:#1a0580; }
.dc-btn-urgent   { background:#ef4444; color:#fff; }
.dc-btn-urgent:hover { background:#dc2626; }
.dc-btn-sm { font-size:11px; padding:5px 12px; border-radius:6px; }

.dc-hint { font-size:11px; color:#94a3b8; }

/* Progress bar for time left */
.dc-progress { width:100%; height:4px; background:#f0f2f6; border-radius:2px; margin-top:6px; overflow:hidden; }
.dc-progress-inner { height:100%; border-radius:2px; }

/* Alert */
.dc-alert { padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:16px; }
.dc-alert-success { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
.dc-alert-error   { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }

/* Pagination */
.dc-pagination { margin-top:20px; display:flex; justify-content:center; }

@media (max-width:768px) {
    .dc-stats { grid-template-columns:1fr 1fr; }
    .dc-card-body { grid-template-columns:1fr 1fr; }
}
</style>

<div class="dc-header">
    <div>
        <div class="dc-title">📢 Discount Campaign Notifications</div>
        <div class="dc-subtitle">Manage and send push notifications for active discount campaigns</div>
    </div>
    <a href="{{ route('notifications.index') }}" class="dc-back">← Back to Notifications</a>
</div>

@if(session('success'))
    <div class="dc-alert dc-alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="dc-alert dc-alert-error">{{ session('error') }}</div>
@endif

{{-- Stats row --}}
@php
    $totalCampaigns  = $campaigns->total();
    $activeCampaigns = $campaigns->getCollection()->filter(fn($c) => $c->status === 'active')->count();
    $urgentCampaigns = $campaigns->getCollection()->filter(fn($c) => $c->isLive() && $c->ends_at->diffInMinutes(now()) <= 120)->count();
@endphp
<div class="dc-stats">
    <div class="dc-stat">
        <div class="dc-stat-val">{{ $totalCampaigns }}</div>
        <div class="dc-stat-lbl">Total Campaigns</div>
    </div>
    <div class="dc-stat">
        <div class="dc-stat-val" style="color:#065f46">{{ $activeCampaigns }}</div>
        <div class="dc-stat-lbl">Currently Active</div>
    </div>
    <div class="dc-stat">
        <div class="dc-stat-val" style="color:#ef4444">{{ $urgentCampaigns }}</div>
        <div class="dc-stat-lbl">⚡ Ending Soon (&lt;2h)</div>
    </div>
    <div class="dc-stat">
        <div class="dc-stat-val" style="color:#140465">{{ number_format($userCount) }}</div>
        <div class="dc-stat-lbl">Users with FCM Token</div>
    </div>
</div>

{{-- Info box --}}
<div style="background:#f0f4ff;border:1px solid #c7d2fe;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:12px;color:#3730a3;line-height:1.6;">
    <strong>🤖 Auto-send schedule:</strong> Normal notifications every 2h (via scheduler). Urgent (≤2h left) every 30min.
    <strong>Manual send:</strong> Use the "Send Now" button below to immediately notify all users — duplicates blocked for 2h per user per campaign.
</div>

<div class="dc-list">
@forelse($campaigns as $campaign)
    @php
        $status      = $campaign->status;
        $isLive      = $campaign->isLive();
        $vendor      = $campaign->vendor;
        $cat         = $campaign->category;
        $endsAt      = $campaign->ends_at;
        $startsAt    = $campaign->starts_at;
        $minLeft     = $isLive ? (int) $now->diffInMinutes($endsAt, false) : null;
        $urgent      = $isLive && $minLeft <= 120;
        $discount    = $campaign->discount_type === 'percentage'
            ? "{$campaign->discount_value}% OFF"
            : '$' . number_format($campaign->discount_value, 0) . ' OFF';
        $deepLink    = '/vendor/' . $campaign->vendor_id;
        // Progress: how much time has passed of total campaign duration
        $totalMin    = $isLive ? (int) $startsAt->diffInMinutes($endsAt) : 0;
        $elapsedMin  = $isLive ? (int) $startsAt->diffInMinutes($now) : 0;
        $progress    = $totalMin > 0 ? min(100, round($elapsedMin / $totalMin * 100)) : 0;
        $barColor    = $progress > 75 ? '#ef4444' : ($progress > 50 ? '#f59e0b' : '#10b981');
    @endphp

    <div class="dc-card">
        {{-- Card header --}}
        <div class="dc-card-head">
            <div class="dc-logo">
                @if($vendor?->logo)
                    <img src="{{ cdn_url($vendor->logo) }}" alt="{{ $vendor->name }}">
                @else
                    🍽️
                @endif
            </div>
            <div style="flex:1;">
                <div class="dc-name">{{ $vendor?->name ?? 'Unknown Vendor' }}</div>
                <div class="dc-meta">
                    {{ $campaign->name }}
                    @if($cat) · {{ $cat->name }}@endif
                    · Campaign #{{ $campaign->id }}
                </div>
                @if($isLive)
                    <div class="dc-progress" style="margin-top:6px;">
                        <div class="dc-progress-inner" style="width:{{ $progress }}%;background:{{ $barColor }};"></div>
                    </div>
                @endif
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
                <span class="dc-badge {{ $status }}">
                    @if($status==='active') ✅ Active
                    @elseif($status==='scheduled') 🕐 Scheduled
                    @elseif($status==='expired') ⏹ Expired
                    @else ❌ Inactive
                    @endif
                </span>
                @if($urgent)
                    <span class="dc-badge" style="background:#fee2e2;color:#991b1b;">⚡ URGENT</span>
                @endif
            </div>
        </div>

        {{-- Card body --}}
        <div class="dc-card-body">
            <div>
                <div class="dc-field-lbl">Discount</div>
                <div class="dc-field-val" style="color:#140465;font-size:16px;">{{ $discount }}</div>
            </div>
            <div>
                <div class="dc-field-lbl">Duration</div>
                <div class="dc-field-val">
                    {{ $startsAt->format('M j, g:ia') }}<br>
                    <span style="color:#94a3b8;">→ {{ $endsAt->format('M j, g:ia') }}</span>
                </div>
            </div>
            <div>
                <div class="dc-field-lbl">Time Left</div>
                <div class="dc-field-val" style="color:{{ $urgent ? '#ef4444' : '#1a1d2e' }}">
                    @if($isLive)
                        {{ $minLeft <= 60 ? $minLeft . ' min' : $endsAt->diffForHumans($now) }}
                    @elseif($status === 'scheduled')
                        Starts {{ $startsAt->diffForHumans() }}
                    @else
                        —
                    @endif
                </div>
            </div>
        </div>

        {{-- Card footer: send form --}}
        @if($isLive)
        <div class="dc-card-foot">
            <form method="POST" action="{{ route('notifications.discount-campaigns.send', $campaign->id) }}" style="width:100%;">
                @csrf
                <div class="dc-send-form">
                    <div class="dc-field-lbl" style="margin-bottom:4px;">
                        📩 Send notification now — deep link: <code style="font-size:11px;background:#f0f4f8;padding:1px 6px;border-radius:4px;">{{ $deepLink }}</code>
                    </div>
                    <div class="dc-send-row">
                        <input type="text" name="title" class="dc-inp"
                            placeholder="{{ $urgent ? '⏰ Hurry! '.$discount.' at '.($vendor->name??'') : '🔥 '.$discount.' at '.($vendor->name??'') }}"
                            style="max-width:260px;">
                        <input type="text" name="body" class="dc-inp"
                            placeholder="Custom message (optional — leave blank to auto-generate)">
                        <button type="submit" class="dc-btn {{ $urgent ? 'dc-btn-urgent' : 'dc-btn-primary' }}">
                            {{ $urgent ? '⚡ Send Urgent Now' : '📤 Send Now' }}
                        </button>
                    </div>
                    <div class="dc-hint">
                        Leave title/body empty to use auto-generated text.
                        Duplicate prevention: same user won't get the same campaign notification again for 2h.
                        Will send to ~{{ number_format($userCount) }} devices.
                    </div>
                </div>
            </form>
        </div>
        @else
        <div class="dc-card-foot" style="padding:10px 20px;">
            <span style="font-size:12px;color:#94a3b8;">
                @if($status==='scheduled')
                    ⏳ Campaign hasn't started yet — notifications will auto-send after {{ $startsAt->format('M j, g:ia') }}
                @else
                    This campaign has ended. No notifications can be sent.
                @endif
            </span>
        </div>
        @endif
    </div>
@empty
    <div style="background:#fff;border:1px solid #e8ecf2;border-radius:14px;padding:40px;text-align:center;color:#94a3b8;">
        <div style="font-size:40px;margin-bottom:12px;">📭</div>
        <div style="font-weight:700;color:#1a1d2e;margin-bottom:4px;">No campaigns found</div>
        <div style="font-size:13px;">Create discount campaigns in the eFood section to see them here.</div>
    </div>
@endforelse
</div>

{{-- Pagination --}}
<div class="dc-pagination">
    {{ $campaigns->links() }}
</div>

@endsection
