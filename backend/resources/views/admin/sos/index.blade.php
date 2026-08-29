@extends('admin.layouts.app')
@section('title', 'SOS Emergency Alerts')

@push('styles')
<style>
/* ── SOS Enterprise ─────────────────────────────────────── */
:root {
    --sos-red:    #FF2D55;
    --sos-orange: #FF9500;
    --sos-green:  #34C759;
    --sos-blue:   #007AFF;
}

/* Command bar */
.sos-bar {
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    background: linear-gradient(135deg, #1a0a0f 0%, #2d0f1a 100%);
    border: 1px solid rgba(255,45,85,.3);
    border-radius: 14px; padding: 16px 20px; margin-bottom: 20px;
    position: relative; overflow: hidden;
}
.sos-bar::before {
    content:''; position:absolute; inset:0;
    background: repeating-linear-gradient(90deg,transparent,transparent 40px,rgba(255,45,85,.025) 40px,rgba(255,45,85,.025) 41px);
    pointer-events:none;
}
.sos-pulse {
    width:12px; height:12px; border-radius:50%; flex-shrink:0;
    background: var(--sos-red);
    animation: sos-ping 1.5s ease-out infinite;
}
@keyframes sos-ping {
    0%   { box-shadow: 0 0 0 0 rgba(255,45,85,.7); }
    70%  { box-shadow: 0 0 0 10px rgba(255,45,85,0); }
    100% { box-shadow: 0 0 0 0 rgba(255,45,85,0); }
}
.sos-bar h1 { margin:0; font-size:17px; font-weight:800; color:#fff; }
.sos-bar p  { margin:2px 0 0; font-size:11px; color:rgba(255,255,255,.5); }
.ws-chip {
    display:inline-flex; align-items:center; gap:6px;
    padding:5px 12px; border-radius:20px; font-size:11px; font-weight:700;
    border:1px solid rgba(255,255,255,.15); color:rgba(255,255,255,.8);
    transition: all .3s;
}
.ws-chip.connected { border-color:rgba(52,199,89,.4); color:#34C759; }
.ws-chip.error     { border-color:rgba(255,45,85,.4); color:var(--sos-red); }

/* Stats */
.sos-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:18px; }
.sos-stat {
    background:var(--surface); border:1px solid var(--border);
    border-radius:12px; padding:14px 16px; text-align:center;
    position:relative; overflow:hidden; transition:transform .15s;
}
.sos-stat:hover { transform:translateY(-2px); }
.sos-stat::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
.sos-stat.s-active::before   { background:var(--sos-red); }
.sos-stat.s-total::before    { background:var(--sos-blue); }
.sos-stat.s-resolved::before { background:var(--sos-green); }
.sos-stat.s-location::before { background:var(--sos-orange); }
.sos-val { font-size:28px; font-weight:900; line-height:1; }
.sos-lbl { font-size:10px; color:var(--text-muted); margin-top:3px; text-transform:uppercase; letter-spacing:.05em; }

/* Layout: split — list left, map right */
.sos-split {
    display:grid; grid-template-columns:1fr 420px; gap:16px; align-items:start;
}
@media (max-width:1100px) {
    .sos-split { grid-template-columns:1fr; }
    .sos-map-panel { order:-1; }
}

/* Alert cards */
.alert-card {
    background:var(--surface); border:1px solid var(--border);
    border-radius:12px; overflow:hidden; position:relative;
    margin-bottom:10px; transition:box-shadow .2s, transform .15s;
}
.alert-card:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(0,0,0,.1); }
.alert-card.is-active { border-color:rgba(255,45,85,.4); box-shadow:0 0 0 1px rgba(255,45,85,.12); }
.alert-card.is-resolved { opacity:.62; }
.a-accent { position:absolute; left:0; top:0; bottom:0; width:4px; }
.a-accent.active   { background:var(--sos-red); }
.a-accent.pending  { background:var(--sos-orange); }
.a-accent.resolved { background:var(--sos-green); }
.a-body { padding:14px 16px 14px 20px; display:flex; gap:14px; align-items:flex-start; flex-wrap:wrap; }
.a-icon {
    width:42px; height:42px; border-radius:50%; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; font-size:17px;
}
.a-icon.active   { background:rgba(255,45,85,.12); color:var(--sos-red); }
.a-icon.resolved { background:rgba(52,199,89,.12);  color:var(--sos-green); }
.a-info { flex:1; min-width:180px; }
.a-name { font-weight:800; font-size:14px; margin-bottom:2px; }
.a-meta { font-size:11px; color:var(--text-muted); display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:5px; }
.a-msg {
    font-size:12px; font-style:italic; color:var(--text);
    background:rgba(255,45,85,.06); border-left:3px solid rgba(255,45,85,.3);
    padding:5px 9px; border-radius:0 6px 6px 0; margin-bottom:6px;
}
.a-actions { display:flex; flex-direction:column; gap:7px; align-items:flex-end; flex-shrink:0; }
.badge {
    display:inline-block; padding:2px 7px; border-radius:20px;
    font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.04em;
}
.badge-active   { background:rgba(255,45,85,.12); color:var(--sos-red); }
.badge-pending  { background:rgba(255,149,0,.12);  color:var(--sos-orange); }
.badge-resolved { background:rgba(52,199,89,.12);  color:var(--sos-green); }
.btn-call {
    display:inline-flex; align-items:center; gap:5px;
    padding:6px 12px; border-radius:7px; font-size:11px; font-weight:700;
    background:rgba(52,199,89,.12); color:var(--sos-green);
    border:1px solid rgba(52,199,89,.3); text-decoration:none; cursor:pointer;
}
.btn-resolve {
    display:inline-flex; align-items:center; gap:5px;
    padding:6px 12px; border-radius:7px; font-size:11px; font-weight:700;
    background:var(--sos-red); color:#fff; border:none; cursor:pointer;
    transition:opacity .15s;
}
.btn-resolve:disabled { background:#888; cursor:default; }
.btn-map-link {
    display:inline-flex; align-items:center; gap:4px;
    padding:3px 8px; border-radius:5px; font-size:11px; font-weight:600;
    background:rgba(0,122,255,.1); color:var(--sos-blue);
    text-decoration:none; border:1px solid rgba(0,122,255,.2);
}
.btn-focus-map {
    display:inline-flex; align-items:center; gap:4px;
    padding:3px 8px; border-radius:5px; font-size:11px; font-weight:600;
    background:rgba(255,45,85,.1); color:var(--sos-red);
    border:1px solid rgba(255,45,85,.2); cursor:pointer;
}
.resolve-form {
    display:none; padding:12px 20px;
    border-top:1px solid var(--border); background:rgba(0,0,0,.03);
}
.resolve-form textarea {
    width:100%; border:1px solid var(--border); border-radius:7px;
    padding:8px 10px; background:var(--bg); color:var(--text);
    font-size:12px; resize:vertical; min-height:50px; box-sizing:border-box;
}
.resolve-form textarea:focus { outline:2px solid var(--sos-green); }

/* Filter */
.ftabs { display:flex; gap:8px; margin-bottom:14px; align-items:center; flex-wrap:wrap; }
.ftab {
    padding:5px 14px; border-radius:20px; font-size:12px; font-weight:600;
    border:1px solid var(--border); background:var(--surface); cursor:pointer;
    transition:all .15s; color:var(--text);
}
.ftab.on { background:var(--sos-red); color:#fff; border-color:var(--sos-red); }
.ftab-cnt { font-size:9px; font-weight:800; margin-left:3px; opacity:.7; }

/* MAP PANEL */
.sos-map-panel {
    background:var(--surface); border:1px solid var(--border);
    border-radius:14px; overflow:hidden; position:sticky; top:80px;
}
.map-header {
    padding:12px 16px; border-bottom:1px solid var(--border);
    display:flex; align-items:center; justify-content:space-between;
}
.map-header-title { font-weight:800; font-size:14px; display:flex; align-items:center; gap:7px; }
.map-pulsing { width:8px; height:8px; border-radius:50%; background:var(--sos-red); animation:sos-ping 1.5s ease-out infinite; }
#sosMap { width:100%; height:420px; }
.map-legend {
    padding:10px 14px; border-top:1px solid var(--border);
    font-size:11px; color:var(--text-muted); display:flex; gap:14px; flex-wrap:wrap;
}
.map-legend span { display:flex; align-items:center; gap:5px; }
.map-no-location {
    height:420px; display:flex; align-items:center; justify-content:center;
    flex-direction:column; gap:10px; color:var(--text-muted);
    font-size:13px; text-align:center; padding:20px;
}

/* Flash banner */
.sos-banner {
    display:none; position:fixed; top:0; left:0; right:0; z-index:9999;
    background:linear-gradient(90deg, var(--sos-red), #c20035);
    color:#fff; padding:12px 22px; font-size:15px; font-weight:800;
    justify-content:space-between; align-items:center;
    animation:flash .5s ease infinite alternate;
    box-shadow:0 4px 20px rgba(255,45,85,.5);
}
.sos-banner.show { display:flex; }
@keyframes flash { from { opacity:1; } to { opacity:.8; } }

@media (max-width:700px) {
    .sos-stats { grid-template-columns:repeat(2,1fr); }
    .a-body { flex-direction:column; gap:8px; }
    .a-actions { flex-direction:row; align-items:center; }
}
</style>
@endpush

@section('content')

{{-- Flash banner --}}
<div id="sosBanner" class="sos-banner">
    <span><i class="fas fa-exclamation-triangle" style="margin-right:8px;"></i><span id="bannerText">🆘 NEW SOS EMERGENCY</span></span>
    <button onclick="document.getElementById('sosBanner').classList.remove('show')"
        style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);color:#fff;padding:4px 12px;border-radius:6px;font-weight:700;cursor:pointer;">
        Dismiss
    </button>
</div>

{{-- Command bar --}}
<div class="sos-bar">
    <div class="sos-pulse" id="liveDot"></div>
    <div style="flex:1;min-width:0;">
        <h1><i class="fas fa-shield-alt" style="color:var(--sos-red);margin-right:6px;"></i>SOS Emergency Center</h1>
        <p>Real-time driver emergency monitoring · WebSocket + polling fallback</p>
    </div>
    <span class="ws-chip" id="wsStatus">
        <i class="fas fa-circle" style="font-size:7px;"></i> Connecting...
    </span>
    <a href="{{ route('admin.sos.index') }}" class="btn btn-sm"
        style="background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.2);">
        <i class="fas fa-sync-alt"></i> Refresh
    </a>
</div>

{{-- Stats --}}
<div class="sos-stats">
    <div class="sos-stat s-active">
        <div class="sos-val" id="statActive" style="color:var(--sos-red);">{{ $activeCount }}</div>
        <div class="sos-lbl">Active SOS</div>
    </div>
    <div class="sos-stat s-total">
        <div class="sos-val" id="statTotal" style="color:var(--sos-blue);">{{ $alerts->count() }}</div>
        <div class="sos-lbl">Total Alerts</div>
    </div>
    <div class="sos-stat s-resolved">
        <div class="sos-val" id="statResolved" style="color:var(--sos-green);">{{ $alerts->where('status','resolved')->count() }}</div>
        <div class="sos-lbl">Resolved</div>
    </div>
    <div class="sos-stat s-location">
        <div class="sos-val" style="color:var(--sos-orange);">{{ $alerts->whereIn('status',['active','pending'])->where('latitude','!=',null)->count() }}</div>
        <div class="sos-lbl">With Location</div>
    </div>
</div>

{{-- Split layout: alerts + map --}}
<div class="sos-split">

    {{-- LEFT: Alerts list --}}
    <div>
        {{-- Filter tabs --}}
        <div class="ftabs">
            <button class="ftab on" onclick="filterAlerts('all',this)">All <span class="ftab-cnt" id="cAll">{{ $alerts->count() }}</span></button>
            <button class="ftab"    onclick="filterAlerts('active',this)">Active <span class="ftab-cnt" id="cActive">{{ $activeCount }}</span></button>
            <button class="ftab"    onclick="filterAlerts('resolved',this)">Resolved <span class="ftab-cnt" id="cResolved">{{ $alerts->where('status','resolved')->count() }}</span></button>
            <span style="margin-left:auto;font-size:11px;color:var(--text-muted);" id="lastUpdate"></span>
        </div>

        <div id="alertsList">
        @forelse($alerts as $a)
        @php $isActive = in_array($a->status, ['active','pending']); @endphp
        <div class="alert-card {{ $isActive ? 'is-active' : 'is-resolved' }}"
             data-status="{{ $a->status }}" id="alert-{{ $a->id }}">
            <div class="a-accent {{ $a->status }}"></div>
            <div class="a-body">
                <div class="a-icon {{ $isActive ? 'active' : 'resolved' }}">
                    @if($isActive)
                        <i class="fas fa-exclamation-triangle"></i>
                    @else
                        <i class="fas fa-check-circle"></i>
                    @endif
                </div>
                <div class="a-info">
                    <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:3px;">
                        <span class="a-name">{{ $a->driver_name ?? 'Driver #'.$a->deliveryman_id }}</span>
                        <span class="badge badge-{{ $a->status }}">{{ strtoupper($a->status) }}</span>
                        <span style="font-size:10px;color:var(--text-muted);">Alert #{{ $a->id }}</span>
                    </div>
                    <div class="a-meta">
                        @if($a->driver_phone)
                        <span><i class="fas fa-phone"></i> {{ $a->driver_phone }}</span>
                        @endif
                        @if($a->order_number)
                        <span><i class="fas fa-box"></i> Order #{{ $a->order_number }}</span>
                        @endif
                        <span><i class="fas fa-clock"></i> {{ \Carbon\Carbon::parse($a->created_at)->diffForHumans() }}</span>
                        @if($a->latitude && $a->longitude)
                        <a href="https://maps.google.com/?q={{ $a->latitude }},{{ $a->longitude }}" target="_blank" class="btn-map-link">
                            <i class="fas fa-external-link-alt"></i> Google Maps
                        </a>
                        @if($isActive)
                        <button class="btn-focus-map" onclick="focusMap({{ $a->latitude }}, {{ $a->longitude }}, '{{ addslashes($a->driver_name ?? 'Driver') }}')">
                            <i class="fas fa-map-marker-alt"></i> Show on map
                        </button>
                        @endif
                        @endif
                    </div>
                    @if($a->message)
                    <div class="a-msg">"{{ $a->message }}"</div>
                    @endif
                    @if($a->resolved_by_name)
                    <div style="font-size:11px;color:var(--sos-green);"><i class="fas fa-user-check"></i> Resolved by {{ $a->resolved_by_name }}</div>
                    @endif
                </div>
                @if($isActive)
                <div class="a-actions">
                    @if($a->driver_phone)
                    <a href="tel:{{ $a->driver_phone }}" class="btn-call"><i class="fas fa-phone"></i> Call Driver</a>
                    @endif
                    <button class="btn-resolve" onclick="openResolve({{ $a->id }}, this)">
                        <i class="fas fa-check"></i> Resolve
                    </button>
                </div>
                @endif
            </div>
            @if($isActive)
            <div class="resolve-form" id="resolveBox-{{ $a->id }}">
                <textarea id="noteInput-{{ $a->id }}" placeholder="Admin notes (optional)..." rows="2"></textarea>
                <div style="display:flex;gap:8px;margin-top:8px;">
                    <button onclick="confirmResolve({{ $a->id }}, this)"
                        style="background:var(--sos-green);border:none;color:#fff;padding:6px 14px;border-radius:7px;font-weight:700;cursor:pointer;font-size:12px;">
                        <i class="fas fa-check"></i> Confirm Resolve
                    </button>
                    <button onclick="document.getElementById('resolveBox-{{ $a->id }}').style.display='none'"
                        style="background:var(--border);border:none;color:var(--text);padding:6px 12px;border-radius:7px;cursor:pointer;font-size:12px;">
                        Cancel
                    </button>
                </div>
            </div>
            @endif
        </div>
        @empty
        <div id="emptyState" style="text-align:center;padding:70px 20px;color:var(--text-muted);">
            <i class="fas fa-shield-alt" style="font-size:48px;opacity:.18;display:block;margin-bottom:14px;"></i>
            <div style="font-size:15px;font-weight:700;">All Clear — No SOS Alerts</div>
            <div style="font-size:12px;margin-top:4px;opacity:.6;">System is monitoring drivers in real-time.</div>
        </div>
        @endforelse
    </div>
    </div>

    {{-- RIGHT: Map panel --}}
    <div class="sos-map-panel">
        <div class="map-header">
            <div class="map-header-title">
                <div class="map-pulsing" id="mapPulseDot"></div>
                <span>Live Emergency Map</span>
            </div>
            <span style="font-size:11px;color:var(--text-muted);" id="mapAlertCount">
                {{ $activeCount }} active
            </span>
        </div>

        @if($alerts->whereIn('status',['active','pending'])->where('latitude','!=',null)->count() > 0 || true)
        <div id="sosMap"></div>
        @endif

        <div class="map-legend">
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--sos-red);"></span> Active SOS</span>
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--sos-green);"></span> Resolved</span>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
(function(){
'use strict';

const CSRF    = '{{ csrf_token() }}';
const LIVE_URL= '{{ route("admin.sos.live") }}';
const MAPS_KEY= '{{ env("GOOGLE_MAPS_API_KEY") }}';

// Track known alert IDs (from server-rendered list)
let knownIds    = new Set([{{ $alerts->pluck('id')->implode(',') }}]);
let alarmPlaying= false;
let currentFilter= 'all';
let sosMap, markers= {};

// ── Google Maps init (called by Maps JS API callback) ────────────
window.initSosMap = function() {
    sosMap = new google.maps.Map(document.getElementById('sosMap'), {
        zoom: 13,
        center: { lat: 2.0469, lng: 45.3182 }, // Mogadishu default
        mapTypeId: 'roadmap',
        styles: [
            { elementType:'geometry', stylers:[{color:'#1d2c4d'}] },
            { elementType:'labels.text.fill', stylers:[{color:'#8ec3b9'}] },
            { elementType:'labels.text.stroke', stylers:[{color:'#1a3646'}] },
            { featureType:'road', elementType:'geometry', stylers:[{color:'#304a7d'}] },
            { featureType:'water', elementType:'geometry', stylers:[{color:'#0e1626'}] },
        ],
        disableDefaultUI: false,
        zoomControl: true,
        streetViewControl: false,
        mapTypeControl: false,
    });

    // Plot existing active alerts
    @foreach($alerts->whereIn('status',['active','pending'])->where('latitude','!=',null) as $a)
    addMarker({{ $a->id }}, {{ $a->latitude }}, {{ $a->longitude }},
        '{{ addslashes($a->driver_name ?? "Driver #".$a->deliveryman_id) }}',
        '{{ addslashes($a->driver_phone ?? "—") }}',
        '{{ addslashes($a->message ?? "") }}',
        true);
    @endforeach

    // Fit map to markers if any
    fitMapToMarkers();
};

function addMarker(id, lat, lng, name, phone, message, isActive) {
    if (!sosMap) return;
    if (markers[id]) { markers[id].setMap(null); }

    var icon = {
        path: google.maps.SymbolPath.CIRCLE,
        fillColor: isActive ? '#FF2D55' : '#34C759',
        fillOpacity: 1,
        strokeColor: '#fff',
        strokeWeight: 3,
        scale: isActive ? 14 : 9,
    };

    var marker = new google.maps.Marker({
        position: { lat: parseFloat(lat), lng: parseFloat(lng) },
        map: sosMap,
        icon: icon,
        title: name,
        animation: isActive ? google.maps.Animation.BOUNCE : null,
    });

    // Stop bounce after 3s for active markers
    if (isActive) {
        setTimeout(function(){ marker.setAnimation(null); }, 3000);
    }

    var infoContent =
        '<div style="font-family:sans-serif;min-width:200px;padding:4px;">' +
        '<div style="font-weight:800;font-size:14px;color:#FF2D55;margin-bottom:6px;">🆘 SOS Alert #' + id + '</div>' +
        '<div style="margin-bottom:3px;"><strong>' + name + '</strong></div>' +
        '<div style="font-size:12px;color:#666;margin-bottom:3px;">📞 ' + phone + '</div>' +
        (message ? '<div style="font-size:12px;font-style:italic;color:#444;border-top:1px solid #eee;padding-top:6px;margin-top:6px;">"' + message + '"</div>' : '') +
        (isActive ? '<div style="margin-top:8px;"><a href="tel:' + phone + '" style="background:#34C759;color:#fff;padding:5px 12px;border-radius:6px;text-decoration:none;font-size:11px;font-weight:700;">📞 Call Driver</a></div>' : '') +
        '</div>';

    var info = new google.maps.InfoWindow({ content: infoContent });
    marker.addListener('click', function(){ info.open(sosMap, marker); });

    // Auto-open for new active alerts
    if (isActive) { info.open(sosMap, marker); }

    markers[id] = marker;
}

function fitMapToMarkers() {
    if (!sosMap) return;
    var keys = Object.keys(markers);
    if (!keys.length) return;
    if (keys.length === 1) {
        var pos = markers[keys[0]].getPosition();
        sosMap.setCenter(pos);
        sosMap.setZoom(15);
        return;
    }
    var bounds = new google.maps.LatLngBounds();
    keys.forEach(function(k){ bounds.extend(markers[k].getPosition()); });
    sosMap.fitBounds(bounds);
}

window.focusMap = function(lat, lng, name) {
    if (!sosMap) return;
    sosMap.panTo({ lat: parseFloat(lat), lng: parseFloat(lng) });
    sosMap.setZoom(16);
};

// ── Reverb WebSocket ─────────────────────────────────────────────
function loadScript(src, cb) {
    var s = document.createElement('script');
    s.src = src;
    s.onload = cb;
    s.onerror = function(){ cb && cb(true); };
    document.head.appendChild(s);
}

function setWsStatus(state) {
    var chip = document.getElementById('wsStatus');
    var dot  = document.getElementById('liveDot');
    if (!chip) return;
    if (state === 'connected') {
        chip.className = 'ws-chip connected';
        chip.innerHTML = '<i class="fas fa-circle" style="font-size:7px;"></i> WebSocket Live';
        if (dot) dot.style.background = '#34C759';
    } else if (state === 'error') {
        chip.className = 'ws-chip error';
        chip.innerHTML = '<i class="fas fa-circle" style="font-size:7px;"></i> WS offline · Polling';
        if (dot) dot.style.background = 'var(--sos-orange)';
    } else {
        chip.className = 'ws-chip';
        chip.innerHTML = '<i class="fas fa-circle" style="font-size:7px;"></i> Connecting...';
    }
}

loadScript('https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js', function(err) {
    if (err) { setWsStatus('error'); return; }
    loadScript('https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js', function(err2) {
        if (err2) { setWsStatus('error'); return; }
        try {
            var echo = new LaravelEcho.default({
                broadcaster:       'reverb',
                key:               '{{ config("broadcasting.connections.reverb.key") }}',
                wsHost:            '{{ config("broadcasting.connections.reverb.host") }}',
                wsPort:            {{ config('broadcasting.connections.reverb.port', 443) }},
                wssPort:           {{ config('broadcasting.connections.reverb.port', 443) }},
                forceTLS:          {{ config('broadcasting.connections.reverb.scheme', 'https') === 'https' ? 'true' : 'false' }},
                enabledTransports: ['ws', 'wss'],
                disableStats:      true,
            });

            echo.channel('admin.sos').listen('.sos.alert', function(e) {
                console.log('[SOS] Real-time alert:', e);
                handleNewAlert({
                    id:           e.alert_id,
                    deliveryman_id: e.driver_id,
                    driver_name:  e.driver_name,
                    driver_phone: e.driver_phone,
                    latitude:     e.lat,
                    longitude:    e.lng,
                    message:      e.message,
                    order_id:     e.order_id,
                    status:       'active',
                });
            });

            echo.connector.pusher.connection.bind('state_change', function(s) {
                if (s.current === 'connected')    setWsStatus('connected');
                else if (s.current === 'failed' || s.current === 'disconnected') setWsStatus('error');
            });
            echo.connector.pusher.connection.bind('connected', function(){ setWsStatus('connected'); });
        } catch(e) {
            console.warn('[SOS] Echo init error:', e);
            setWsStatus('error');
        }
    });
});

// ── New alert handler (called from both WS and poll) ─────────────
function handleNewAlert(alert) {
    if (knownIds.has(alert.id)) return;
    knownIds.add(alert.id);

    // Inject card
    injectCard(alert);

    // Map marker
    if (alert.latitude && alert.longitude) {
        addMarker(alert.id, alert.latitude, alert.longitude,
            alert.driver_name || 'Driver #' + alert.deliveryman_id,
            alert.driver_phone || '—',
            alert.message || '',
            true);
        focusMap(alert.latitude, alert.longitude, alert.driver_name);
    }

    // Alarm + banner
    showBanner(alert.driver_name, alert.driver_phone);
    playAlarm();

    // Update counters
    bump('statActive',  +1);
    bump('statTotal',   +1);
    bump('cActive',     +1);
    bump('cAll',        +1);
    var mac = document.getElementById('mapAlertCount');
    if (mac) mac.textContent = parseInt(document.getElementById('statActive').textContent) + ' active';
}

function bump(id, delta) {
    var el = document.getElementById(id);
    if (el) el.textContent = Math.max(0, parseInt(el.textContent || '0') + delta);
}

function injectCard(a) {
    var list = document.getElementById('alertsList');
    var empty = document.getElementById('emptyState');
    if (empty) empty.remove();

    var div = document.createElement('div');
    div.className = 'alert-card is-active';
    div.id = 'alert-' + a.id;
    div.setAttribute('data-status', 'active');
    div.innerHTML =
        '<div class="a-accent active"></div>' +
        '<div class="a-body">' +
            '<div class="a-icon active"><i class="fas fa-exclamation-triangle"></i></div>' +
            '<div class="a-info">' +
                '<div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:3px;">' +
                    '<span class="a-name">' + (a.driver_name || 'Driver #' + a.deliveryman_id) + '</span>' +
                    '<span class="badge badge-active">ACTIVE</span>' +
                    '<span style="font-size:10px;color:var(--text-muted);">Alert #' + a.id + ' · Just now</span>' +
                '</div>' +
                '<div class="a-meta">' +
                    (a.driver_phone ? '<span><i class="fas fa-phone"></i> ' + a.driver_phone + '</span>' : '') +
                    '<span><i class="fas fa-clock"></i> Just now</span>' +
                    (a.latitude && a.longitude ? '<a href="https://maps.google.com/?q=' + a.latitude + ',' + a.longitude + '" target="_blank" class="btn-map-link"><i class="fas fa-external-link-alt"></i> Google Maps</a>' : '') +
                    (a.latitude && a.longitude ? '<button class="btn-focus-map" onclick="focusMap(' + a.latitude + ',' + a.longitude + ',\'' + (a.driver_name||'Driver').replace(/'/g,'') + '\')"><i class="fas fa-map-marker-alt"></i> Show on map</button>' : '') +
                '</div>' +
                (a.message ? '<div class="a-msg">"' + a.message + '"</div>' : '') +
            '</div>' +
            '<div class="a-actions">' +
                (a.driver_phone ? '<a href="tel:' + a.driver_phone + '" class="btn-call"><i class="fas fa-phone"></i> Call Driver</a>' : '') +
                '<button class="btn-resolve" onclick="openResolve(' + a.id + ',this)"><i class="fas fa-check"></i> Resolve</button>' +
            '</div>' +
        '</div>' +
        '<div class="resolve-form" id="resolveBox-' + a.id + '">' +
            '<textarea id="noteInput-' + a.id + '" placeholder="Admin notes (optional)..." rows="2"></textarea>' +
            '<div style="display:flex;gap:8px;margin-top:8px;">' +
                '<button onclick="confirmResolve(' + a.id + ',this)" style="background:var(--sos-green);border:none;color:#fff;padding:6px 14px;border-radius:7px;font-weight:700;cursor:pointer;font-size:12px;"><i class="fas fa-check"></i> Confirm Resolve</button>' +
                '<button onclick="document.getElementById(\'resolveBox-' + a.id + '\').style.display=\'none\'" style="background:var(--border);border:none;color:var(--text);padding:6px 12px;border-radius:7px;cursor:pointer;font-size:12px;">Cancel</button>' +
            '</div>' +
        '</div>';
    list.prepend(div);
}

// ── Poll fallback (every 10s) ─────────────────────────────────────
function pollLive() {
    fetch(LIVE_URL, { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(data){
            var now = new Date().toLocaleTimeString('en-GB');
            var lu = document.getElementById('lastUpdate');
            if (lu) lu.textContent = 'Updated: ' + now;
            var sa = document.getElementById('statActive');
            if (sa) sa.textContent = data.count;
            (data.data || []).forEach(function(alert){ handleNewAlert(alert); });
        })
        .catch(function(){});
}
setInterval(pollLive, 10000);
setTimeout(pollLive, 3000);

// ── Banner & Alarm ────────────────────────────────────────────────
function showBanner(name, phone) {
    document.getElementById('bannerText').textContent =
        '🆘 NEW SOS — ' + (name || 'Driver') + (phone ? ' · ' + phone : '');
    var b = document.getElementById('sosBanner');
    b.classList.add('show');
    setTimeout(function(){ b.classList.remove('show'); }, 20000);
}

function playAlarm() {
    if (alarmPlaying) return;
    alarmPlaying = true;
    try {
        var ctx = new (window.AudioContext || window.webkitAudioContext)();
        function beep(freq, t) {
            var o = ctx.createOscillator(), g = ctx.createGain();
            o.connect(g); g.connect(ctx.destination);
            o.type = 'square';
            o.frequency.setValueAtTime(freq, ctx.currentTime + t);
            g.gain.setValueAtTime(0.25, ctx.currentTime + t);
            g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + t + 0.3);
            o.start(ctx.currentTime + t);
            o.stop(ctx.currentTime + t + 0.3);
        }
        beep(900,0); beep(700,0.35); beep(900,0.7);
        beep(900,1.4); beep(700,1.75); beep(900,2.1);
        setTimeout(function(){ alarmPlaying=false; }, 4000);
    } catch(e){ alarmPlaying=false; }
}

// ── Filter ────────────────────────────────────────────────────────
window.filterAlerts = function(f, el) {
    currentFilter = f;
    document.querySelectorAll('.ftab').forEach(function(t){ t.classList.remove('on'); });
    el.classList.add('on');
    document.querySelectorAll('.alert-card').forEach(function(c){
        var s = c.getAttribute('data-status');
        c.style.display = f==='all' ? '' : f==='active' ? (['active','pending'].includes(s)?'':'none') : (s==='resolved'?'':'none');
    });
};

// ── Resolve ───────────────────────────────────────────────────────
window.openResolve = function(id, btn) {
    var box = document.getElementById('resolveBox-'+id);
    if (box) box.style.display='block';
    btn.style.display='none';
};

window.confirmResolve = function(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    var notes = (document.getElementById('noteInput-'+id)||{}).value||'';
    fetch('/admin/sos/'+id+'/resolve', {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body:JSON.stringify({notes:notes})
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if (!data.success) { btn.disabled=false; btn.innerHTML='Retry'; return; }
        var card = document.getElementById('alert-'+id);
        if (!card) return;
        card.classList.remove('is-active');
        card.classList.add('is-resolved');
        card.setAttribute('data-status','resolved');
        var acc = card.querySelector('.a-accent');
        if (acc) acc.className='a-accent resolved';
        var badge = card.querySelector('.badge');
        if (badge){ badge.className='badge badge-resolved'; badge.textContent='RESOLVED'; }
        var ico = card.querySelector('.a-icon');
        if (ico){ ico.className='a-icon resolved'; ico.innerHTML='<i class="fas fa-check-circle"></i>'; }
        var acts = card.querySelector('.a-actions');
        if (acts) acts.remove();
        var rbox = document.getElementById('resolveBox-'+id);
        if (rbox) rbox.remove();
        // Update map marker
        if (markers[id]) {
            markers[id].setIcon({ path:google.maps.SymbolPath.CIRCLE, fillColor:'#34C759', fillOpacity:1, strokeColor:'#fff', strokeWeight:2, scale:9 });
            markers[id].setAnimation(null);
        }
        // Update counters
        bump('statActive', -1);
        bump('statResolved', +1);
        bump('cActive', -1);
        bump('cResolved', +1);
        var mac = document.getElementById('mapAlertCount');
        if (mac) mac.textContent = parseInt(document.getElementById('statActive').textContent||'0') + ' active';
        // Re-apply filter
        var onTab = document.querySelector('.ftab.on');
        if (onTab) filterAlerts(currentFilter, onTab);
    })
    .catch(function(){ btn.disabled=false; btn.innerHTML='<i class="fas fa-check"></i> Retry'; });
};

})();
</script>

{{-- Load Google Maps with callback --}}
<script async defer
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&callback=initSosMap">
</script>
@endpush
