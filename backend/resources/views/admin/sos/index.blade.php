@extends('admin.layouts.app')
@section('title', 'SOS Emergency Alerts')

@push('css')
<style>
/* ── SOS Enterprise Design ──────────────────────────────────────── */
:root {
    --sos-red:    #FF2D55;
    --sos-orange: #FF9500;
    --sos-green:  #34C759;
    --sos-blue:   #007AFF;
}

/* Header */
.sos-command-bar {
    display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
    background: linear-gradient(135deg, #1a0a0f 0%, #2d0f1a 100%);
    border: 1px solid rgba(255,45,85,.25);
    border-radius: 14px; padding: 18px 22px; margin-bottom: 22px;
    position: relative; overflow: hidden;
}
.sos-command-bar::before {
    content: ''; position: absolute; inset: 0;
    background: repeating-linear-gradient(90deg, transparent, transparent 40px, rgba(255,45,85,.03) 40px, rgba(255,45,85,.03) 41px);
}
.sos-live-dot {
    width: 12px; height: 12px; border-radius: 50%;
    background: var(--sos-red); flex-shrink: 0;
    box-shadow: 0 0 0 0 rgba(255,45,85,.5);
    animation: sos-ping 1.6s ease-out infinite;
}
@keyframes sos-ping {
    0%   { box-shadow: 0 0 0 0 rgba(255,45,85,.6); }
    70%  { box-shadow: 0 0 0 10px rgba(255,45,85,0); }
    100% { box-shadow: 0 0 0 0 rgba(255,45,85,0); }
}
.sos-title-block { flex: 1; min-width: 0; }
.sos-title-block h1 { margin: 0; font-size: 18px; font-weight: 800; color: #fff; }
.sos-title-block p  { margin: 2px 0 0; font-size: 12px; color: rgba(255,255,255,.5); }
.sos-status-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;
    border: 1px solid rgba(255,255,255,.15); color: rgba(255,255,255,.8);
}

/* Stats bar */
.sos-stats {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px;
}
.sos-stat {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 12px; padding: 16px 18px; text-align: center;
    position: relative; overflow: hidden; transition: transform .15s;
}
.sos-stat:hover { transform: translateY(-2px); }
.sos-stat::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
}
.sos-stat.s-active::before  { background: var(--sos-red); }
.sos-stat.s-total::before   { background: var(--sos-blue); }
.sos-stat.s-resolved::before{ background: var(--sos-green); }
.sos-stat.s-location::before{ background: var(--sos-orange); }
.sos-stat-val { font-size: 30px; font-weight: 900; line-height: 1; }
.sos-stat-lbl { font-size: 11px; color: var(--text-muted); margin-top: 4px; text-transform: uppercase; letter-spacing: .05em; }
.sos-stat-icon { font-size: 22px; opacity: .12; position: absolute; bottom: 10px; right: 14px; }

/* Alert cards */
.alert-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 12px; padding: 0; margin-bottom: 12px;
    transition: box-shadow .2s, transform .15s;
    overflow: hidden; position: relative;
}
.alert-card:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(0,0,0,.1); }
.alert-card.is-active { border-color: rgba(255,45,85,.4); box-shadow: 0 0 0 1px rgba(255,45,85,.15); }
.alert-card.is-resolved { opacity: .65; }
.alert-accent {
    position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
}
.alert-accent.active   { background: var(--sos-red); }
.alert-accent.pending  { background: var(--sos-orange); }
.alert-accent.resolved { background: var(--sos-green); }

.alert-body { padding: 16px 18px 16px 22px; display: flex; gap: 16px; align-items: flex-start; flex-wrap: wrap; }
.alert-icon-wrap {
    width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 18px;
}
.alert-icon-wrap.active  { background: rgba(255,45,85,.12); color: var(--sos-red); }
.alert-icon-wrap.resolved{ background: rgba(52,199,89,.12); color: var(--sos-green); }

.alert-info { flex: 1; min-width: 200px; }
.alert-name { font-weight: 800; font-size: 15px; margin-bottom: 2px; }
.alert-meta { font-size: 12px; color: var(--text-muted); display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 6px; }
.alert-message {
    font-size: 13px; font-style: italic; color: var(--text);
    background: rgba(255,45,85,.06); border-left: 3px solid rgba(255,45,85,.3);
    padding: 6px 10px; border-radius: 0 6px 6px 0; margin-bottom: 8px;
}
.alert-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; flex-shrink: 0; }

/* Buttons */
.btn-call {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 8px; font-size: 12px; font-weight: 700;
    background: rgba(52,199,89,.12); color: var(--sos-green);
    border: 1px solid rgba(52,199,89,.3); text-decoration: none; cursor: pointer;
    transition: background .15s;
}
.btn-call:hover { background: rgba(52,199,89,.2); }
.btn-resolve {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 8px; font-size: 12px; font-weight: 700;
    background: var(--sos-red); color: #fff; border: none; cursor: pointer;
    transition: opacity .15s;
}
.btn-resolve:hover { opacity: .88; }
.btn-resolve:disabled { background: #888; cursor: default; opacity: 1; }
.btn-map {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600;
    background: rgba(0,122,255,.1); color: var(--sos-blue);
    text-decoration: none; border: 1px solid rgba(0,122,255,.2);
}
.badge {
    display: inline-block; padding: 2px 8px; border-radius: 20px;
    font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em;
}
.badge-active   { background: rgba(255,45,85,.12); color: var(--sos-red); }
.badge-pending  { background: rgba(255,149,0,.12);  color: var(--sos-orange); }
.badge-resolved { background: rgba(52,199,89,.12);  color: var(--sos-green); }

/* Resolve form */
.resolve-form {
    display: none; padding: 14px 22px 14px;
    border-top: 1px solid var(--border); background: rgba(0,0,0,.03);
}
.resolve-form textarea {
    width: 100%; border: 1px solid var(--border); border-radius: 8px;
    padding: 10px 12px; background: var(--bg); color: var(--text);
    font-size: 13px; resize: vertical; min-height: 60px; box-sizing: border-box;
}
.resolve-form textarea:focus { outline: 2px solid var(--sos-green); }

/* Filter tabs */
.filter-strip { display: flex; gap: 8px; margin-bottom: 18px; align-items: center; flex-wrap: wrap; }
.ftab {
    padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600;
    border: 1px solid var(--border); background: var(--surface); cursor: pointer;
    transition: all .15s; color: var(--text);
}
.ftab.on { background: var(--sos-red); color: #fff; border-color: var(--sos-red); }
.ftab-count { font-size: 10px; font-weight: 800; margin-left: 4px; opacity: .7; }

/* New-SOS flash banner */
.sos-banner {
    display: none; position: fixed; top: 0; left: 0; right: 0; z-index: 9999;
    background: linear-gradient(90deg, var(--sos-red), #c20035);
    color: #fff; padding: 14px 24px;
    font-size: 15px; font-weight: 800;
    justify-content: space-between; align-items: center;
    animation: flash .5s ease infinite alternate;
    box-shadow: 0 4px 20px rgba(255,45,85,.5);
}
.sos-banner.show { display: flex; }
@keyframes flash { from { opacity:1; } to { opacity:.8; } }

@media (max-width: 700px) {
    .sos-stats { grid-template-columns: repeat(2,1fr); }
    .alert-body { flex-direction: column; gap: 10px; }
    .alert-actions { flex-direction: row; align-items: center; }
}
</style>
@endpush

@section('content')

{{-- Flash banner (new alert while watching) --}}
<div id="sosBanner" class="sos-banner">
    <span><i class="fas fa-exclamation-triangle" style="margin-right:8px;animation:flash .5s infinite;"></i><span id="bannerText">🆘 NEW SOS EMERGENCY</span></span>
    <button onclick="document.getElementById('sosBanner').classList.remove('show')" style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);color:#fff;padding:4px 12px;border-radius:6px;font-weight:700;cursor:pointer;">Dismiss</button>
</div>

{{-- Command bar --}}
<div class="sos-command-bar">
    <div class="sos-live-dot" id="liveDot"></div>
    <div class="sos-title-block">
        <h1><i class="fas fa-shield-alt" style="margin-right:6px;color:var(--sos-red);"></i>SOS Emergency Center</h1>
        <p>Real-time driver emergency monitoring · Auto-refreshes every 10 seconds</p>
    </div>
    <div class="sos-status-chip" id="pollStatus">
        <i class="fas fa-circle" style="color:var(--sos-green);font-size:8px;"></i>
        Watching live...
    </div>
    <a href="{{ route('admin.sos.index') }}" class="btn btn-sm btn-outline-secondary" style="color:#fff;border-color:rgba(255,255,255,.3);">
        <i class="fas fa-sync-alt"></i> Refresh
    </a>
</div>

{{-- Stats --}}
<div class="sos-stats">
    <div class="sos-stat s-active">
        <div class="sos-stat-val" id="statActive" style="color:var(--sos-red);">{{ $activeCount }}</div>
        <div class="sos-stat-lbl">Active SOS</div>
        <i class="fas fa-bell sos-stat-icon"></i>
    </div>
    <div class="sos-stat s-total">
        <div class="sos-stat-val" id="statTotal" style="color:var(--sos-blue);">{{ $alerts->count() }}</div>
        <div class="sos-stat-lbl">Total Alerts</div>
        <i class="fas fa-list sos-stat-icon"></i>
    </div>
    <div class="sos-stat s-resolved">
        <div class="sos-stat-val" id="statResolved" style="color:var(--sos-green);">{{ $alerts->where('status','resolved')->count() }}</div>
        <div class="sos-stat-lbl">Resolved</div>
        <i class="fas fa-check-circle sos-stat-icon"></i>
    </div>
    <div class="sos-stat s-location">
        <div class="sos-stat-val" style="color:var(--sos-orange);">{{ $alerts->whereIn('status',['active','pending'])->where('latitude','!=',null)->count() }}</div>
        <div class="sos-stat-lbl">With Location</div>
        <i class="fas fa-map-marker-alt sos-stat-icon"></i>
    </div>
</div>

{{-- Filter strip --}}
<div class="filter-strip">
    <button class="ftab on" onclick="filterAlerts('all',this)">All <span class="ftab-count" id="cAll">{{ $alerts->count() }}</span></button>
    <button class="ftab"    onclick="filterAlerts('active',this)">Active <span class="ftab-count" id="cActive">{{ $activeCount }}</span></button>
    <button class="ftab"    onclick="filterAlerts('resolved',this)">Resolved <span class="ftab-count" id="cResolved">{{ $alerts->where('status','resolved')->count() }}</span></button>
    <span style="margin-left:auto;font-size:12px;color:var(--text-muted);" id="lastUpdate"></span>
</div>

{{-- Alerts list --}}
<div id="alertsList">
@forelse($alerts as $a)
@php $isActive = in_array($a->status, ['active','pending']); @endphp
<div class="alert-card {{ $isActive ? 'is-active' : 'is-resolved' }}"
     data-status="{{ $a->status }}" id="alert-{{ $a->id }}">
    <div class="alert-accent {{ $a->status }}"></div>
    <div class="alert-body">
        {{-- Icon --}}
        <div class="alert-icon-wrap {{ $isActive ? 'active' : 'resolved' }}">
            @if($isActive)
                <i class="fas fa-exclamation-triangle"></i>
            @else
                <i class="fas fa-check-circle"></i>
            @endif
        </div>

        {{-- Info --}}
        <div class="alert-info">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                <span class="alert-name">{{ $a->driver_name ?? 'Driver #'.$a->deliveryman_id }}</span>
                <span class="badge badge-{{ $a->status }}">{{ strtoupper($a->status) }}</span>
                <span style="font-size:11px;color:var(--text-muted);">Alert #{{ $a->id }}</span>
            </div>
            <div class="alert-meta">
                @if($a->driver_phone)
                <span><i class="fas fa-phone" style="width:12px;"></i> {{ $a->driver_phone }}</span>
                @endif
                @if($a->order_number)
                <span><i class="fas fa-box" style="width:12px;"></i> Order #{{ $a->order_number }}</span>
                @endif
                <span><i class="fas fa-clock" style="width:12px;"></i> {{ \Carbon\Carbon::parse($a->created_at)->diffForHumans() }}</span>
                @if($a->latitude && $a->longitude)
                <a href="https://maps.google.com/?q={{ $a->latitude }},{{ $a->longitude }}" target="_blank" class="btn-map">
                    <i class="fas fa-map-marker-alt"></i> View on Map
                </a>
                @endif
            </div>
            @if($a->message)
            <div class="alert-message">"{{ $a->message }}"</div>
            @endif
            @if($a->resolved_by_name)
            <div style="font-size:11px;color:var(--sos-green);"><i class="fas fa-user-check"></i> Resolved by {{ $a->resolved_by_name }}</div>
            @endif
        </div>

        {{-- Actions --}}
        @if($isActive)
        <div class="alert-actions">
            @if($a->driver_phone)
            <a href="tel:{{ $a->driver_phone }}" class="btn-call">
                <i class="fas fa-phone"></i> Call Driver
            </a>
            @endif
            <button class="btn-resolve" onclick="openResolve({{ $a->id }}, this)">
                <i class="fas fa-check"></i> Resolve
            </button>
        </div>
        @endif
    </div>

    {{-- Resolve form --}}
    @if($isActive)
    <div class="resolve-form" id="resolveBox-{{ $a->id }}">
        <textarea id="noteInput-{{ $a->id }}" placeholder="Admin notes (optional)..." rows="2"></textarea>
        <div style="display:flex;gap:8px;margin-top:10px;">
            <button onclick="confirmResolve({{ $a->id }}, this)"
                style="background:var(--sos-green);border:none;color:#fff;padding:7px 16px;border-radius:8px;font-weight:700;cursor:pointer;font-size:13px;">
                <i class="fas fa-check"></i> Confirm Resolve
            </button>
            <button onclick="document.getElementById('resolveBox-{{ $a->id }}').style.display='none'"
                style="background:var(--border);border:none;color:var(--text);padding:7px 14px;border-radius:8px;cursor:pointer;font-size:13px;">
                Cancel
            </button>
        </div>
    </div>
    @endif
</div>
@empty
<div style="text-align:center;padding:80px 20px;color:var(--text-muted);">
    <i class="fas fa-shield-alt" style="font-size:52px;opacity:.2;display:block;margin-bottom:16px;"></i>
    <div style="font-size:16px;font-weight:700;">All Clear — No SOS Alerts</div>
    <div style="font-size:13px;margin-top:4px;opacity:.6;">All drivers are safe. System is monitoring.</div>
</div>
@endforelse
</div>

@endsection

@push('js')
<script>
(function(){
'use strict';
const CSRF      = '{{ csrf_token() }}';
const LIVE_URL  = '{{ route("admin.sos.live") }}';
let knownIds    = new Set([{{ $alerts->pluck('id')->implode(',') }}]);
let alarmPlaying= false;
let currentFilter = 'all';
let pollInterval;

// ── Polling ──────────────────────────────────────────────────────
function pollLive() {
    fetch(LIVE_URL, { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(data){
            const now = new Date().toLocaleTimeString('en-GB');
            document.getElementById('pollStatus').innerHTML =
                '<i class="fas fa-circle" style="color:var(--sos-green);font-size:8px;"></i> Live · ' + now;
            document.getElementById('lastUpdate').textContent = 'Last update: ' + now;

            // Update active count
            const activeEl = document.getElementById('statActive');
            if (activeEl) activeEl.textContent = data.count;

            // Animate live dot red when active
            const dot = document.getElementById('liveDot');
            if (dot) dot.style.background = data.count > 0 ? 'var(--sos-red)' : 'var(--sos-green)';

            // Check for new alerts
            (data.data || []).forEach(function(alert){
                if (!knownIds.has(alert.id)) {
                    knownIds.add(alert.id);
                    injectAlert(alert);
                    showBanner(alert.driver_name, alert.driver_phone);
                    playAlarm();
                    // Update counters
                    const t = document.getElementById('statTotal');
                    if (t) t.textContent = parseInt(t.textContent||0) + 1;
                    const ca = document.getElementById('cActive');
                    if (ca) ca.textContent = parseInt(ca.textContent||0) + 1;
                    const cAll = document.getElementById('cAll');
                    if (cAll) cAll.textContent = parseInt(cAll.textContent||0) + 1;
                }
            });
        })
        .catch(function(){
            const s = document.getElementById('pollStatus');
            if (s) s.innerHTML = '<i class="fas fa-circle" style="color:var(--sos-orange);font-size:8px;"></i> Reconnecting...';
        });
}

function injectAlert(a) {
    const list = document.getElementById('alertsList');
    // Remove "no alerts" placeholder if present
    const empty = list.querySelector('[data-empty]');
    if (empty) empty.remove();

    const div = document.createElement('div');
    div.className = 'alert-card is-active';
    div.id = 'alert-' + a.id;
    div.setAttribute('data-status', 'active');
    div.innerHTML =
        '<div class="alert-accent active"></div>' +
        '<div class="alert-body">' +
            '<div class="alert-icon-wrap active"><i class="fas fa-exclamation-triangle"></i></div>' +
            '<div class="alert-info">' +
                '<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">' +
                    '<span class="alert-name">' + (a.driver_name || 'Driver #' + a.deliveryman_id) + '</span>' +
                    '<span class="badge badge-active">ACTIVE</span>' +
                    '<span style="font-size:11px;color:var(--text-muted);">Alert #' + a.id + '</span>' +
                '</div>' +
                '<div class="alert-meta">' +
                    (a.driver_phone ? '<span><i class="fas fa-phone" style="width:12px;"></i> ' + a.driver_phone + '</span>' : '') +
                    '<span><i class="fas fa-clock" style="width:12px;"></i> Just now</span>' +
                    (a.latitude && a.longitude ? '<a href="https://maps.google.com/?q=' + a.latitude + ',' + a.longitude + '" target="_blank" class="btn-map"><i class="fas fa-map-marker-alt"></i> View on Map</a>' : '') +
                '</div>' +
                (a.message ? '<div class="alert-message">"' + a.message + '"</div>' : '') +
            '</div>' +
            '<div class="alert-actions">' +
                (a.driver_phone ? '<a href="tel:' + a.driver_phone + '" class="btn-call"><i class="fas fa-phone"></i> Call Driver</a>' : '') +
                '<button class="btn-resolve" onclick="openResolve(' + a.id + ', this)"><i class="fas fa-check"></i> Resolve</button>' +
            '</div>' +
        '</div>' +
        '<div class="resolve-form" id="resolveBox-' + a.id + '">' +
            '<textarea id="noteInput-' + a.id + '" placeholder="Admin notes (optional)..." rows="2"></textarea>' +
            '<div style="display:flex;gap:8px;margin-top:10px;">' +
                '<button onclick="confirmResolve(' + a.id + ', this)" style="background:var(--sos-green);border:none;color:#fff;padding:7px 16px;border-radius:8px;font-weight:700;cursor:pointer;font-size:13px;"><i class="fas fa-check"></i> Confirm Resolve</button>' +
                '<button onclick="document.getElementById(\'resolveBox-' + a.id + '\').style.display=\'none\'" style="background:var(--border);border:none;color:var(--text);padding:7px 14px;border-radius:8px;cursor:pointer;font-size:13px;">Cancel</button>' +
            '</div>' +
        '</div>';
    list.prepend(div);
}

// ── Banner & Alarm ───────────────────────────────────────────────
function showBanner(name, phone) {
    document.getElementById('bannerText').textContent =
        '🆘 NEW SOS — ' + (name || 'Driver') + (phone ? ' · ' + phone : '');
    const b = document.getElementById('sosBanner');
    b.classList.add('show');
    setTimeout(function(){ b.classList.remove('show'); }, 18000);
}

function playAlarm() {
    if (alarmPlaying) return;
    alarmPlaying = true;
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        function beep(freq, delay) {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.connect(g); g.connect(ctx.destination);
            o.type = 'square';
            o.frequency.setValueAtTime(freq, ctx.currentTime + delay);
            g.gain.setValueAtTime(0.25, ctx.currentTime + delay);
            g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + 0.35);
            o.start(ctx.currentTime + delay);
            o.stop(ctx.currentTime + delay + 0.35);
        }
        beep(900, 0); beep(700, 0.4); beep(900, 0.8);
        beep(900, 1.5); beep(700, 1.9); beep(900, 2.3);
        setTimeout(function(){ alarmPlaying = false; }, 4000);
    } catch(e) { alarmPlaying = false; }
}

// ── Filter ───────────────────────────────────────────────────────
window.filterAlerts = function(f, el) {
    currentFilter = f;
    document.querySelectorAll('.ftab').forEach(function(t){ t.classList.remove('on'); });
    el.classList.add('on');
    document.querySelectorAll('.alert-card').forEach(function(card){
        const s = card.getAttribute('data-status');
        if (f === 'all') card.style.display = '';
        else if (f === 'active')   card.style.display = ['active','pending'].includes(s) ? '' : 'none';
        else if (f === 'resolved') card.style.display = s === 'resolved' ? '' : 'none';
    });
};

// ── Resolve flow ─────────────────────────────────────────────────
window.openResolve = function(id, btn) {
    const box = document.getElementById('resolveBox-' + id);
    if (box) { box.style.display = 'block'; }
    btn.style.display = 'none';
};

window.confirmResolve = function(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resolving...';
    const notes = (document.getElementById('noteInput-' + id) || {}).value || '';
    fetch('/admin/sos/' + id + '/resolve', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ notes: notes })
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if (!data.success) { btn.disabled = false; btn.innerHTML = 'Retry'; return; }
        const card = document.getElementById('alert-' + id);
        if (!card) return;
        card.classList.remove('is-active');
        card.classList.add('is-resolved');
        card.setAttribute('data-status', 'resolved');
        // Update accent + badge
        const accent = card.querySelector('.alert-accent');
        if (accent) { accent.className = 'alert-accent resolved'; }
        const badge = card.querySelector('.badge');
        if (badge) { badge.className = 'badge badge-resolved'; badge.textContent = 'RESOLVED'; }
        // Remove action area & resolve box
        const actionsDiv = card.querySelector('.alert-actions');
        if (actionsDiv) actionsDiv.remove();
        const resolveBox = document.getElementById('resolveBox-' + id);
        if (resolveBox) resolveBox.remove();
        const iconWrap = card.querySelector('.alert-icon-wrap');
        if (iconWrap) { iconWrap.className = 'alert-icon-wrap resolved'; iconWrap.innerHTML = '<i class="fas fa-check-circle"></i>'; }
        // Update counters
        const active = document.getElementById('statActive');
        if (active) active.textContent = Math.max(0, parseInt(active.textContent) - 1);
        const res = document.getElementById('statResolved');
        if (res) res.textContent = parseInt(res.textContent||0) + 1;
        const cA = document.getElementById('cActive');
        if (cA) cA.textContent = Math.max(0, parseInt(cA.textContent) - 1);
        const cR = document.getElementById('cResolved');
        if (cR) cR.textContent = parseInt(cR.textContent||0) + 1;
        // Re-apply current filter
        const activeTab = document.querySelector('.ftab.on');
        if (activeTab) filterAlerts(currentFilter, activeTab);
    })
    .catch(function(){ btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Retry'; });
};

// ── Start polling ─────────────────────────────────────────────────
pollInterval = setInterval(pollLive, 10000);
// Initial poll after 2s (page just loaded, give server a moment)
setTimeout(pollLive, 2000);

})();
</script>
@endpush
