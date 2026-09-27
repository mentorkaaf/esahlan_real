@extends('admin.layouts.app')
@section('title', 'Live Driver Map')

@push('styles')
<style>
/* ── Enterprise Dispatch Center ───────────────────────────────────────────── */
#live-map { width:100%;height:calc(100vh - 170px);min-height:560px;border-radius:0;overflow:hidden; }
.dispatch-wrap { position:relative;background:#0f172a;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.35); }

/* Stats bar */
.dispatch-stats { display:flex;align-items:stretch;gap:0;background:#0f172a;border-bottom:1px solid rgba(255,255,255,.06);padding:0; }
.ds-stat { display:flex;flex-direction:column;align-items:center;justify-content:center;padding:12px 24px;border-right:1px solid rgba(255,255,255,.06);min-width:120px;cursor:default; }
.ds-stat:last-child { border-right:none;margin-left:auto; }
/* Acceptance badges */
.acc-pending  { background:rgba(249,115,22,.18);color:#fb923c;border:1px solid rgba(249,115,22,.35);padding:1px 7px;border-radius:20px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em; }
.acc-accepted { background:rgba(34,197,94,.18);color:#4ade80;border:1px solid rgba(34,197,94,.35);padding:1px 7px;border-radius:20px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em; }
.acc-declined { background:rgba(239,68,68,.18);color:#f87171;border:1px solid rgba(239,68,68,.35);padding:1px 7px;border-radius:20px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em; }
/* Force Online button */
.btn-force-online { padding:2px 8px;border:none;border-radius:6px;background:rgba(34,197,94,.2);color:#4ade80;font-size:10px;font-weight:700;cursor:pointer;transition:background .15s;border:1px solid rgba(34,197,94,.35); }
.btn-force-online:hover { background:rgba(34,197,94,.35); }
/* Offline section separator */
.dp-section-hdr { padding:6px 14px;font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#475569;background:rgba(255,255,255,.02);border-bottom:1px solid rgba(255,255,255,.04);border-top:1px solid rgba(255,255,255,.04);margin-top:4px; }
.ds-val { font-size:22px;font-weight:900;color:#fff;line-height:1; }
.ds-lbl { font-size:10px;color:#64748b;font-weight:600;letter-spacing:.06em;text-transform:uppercase;margin-top:3px; }
.ds-dot { width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:5px;vertical-align:middle; }
.dot-live { background:#22c55e;box-shadow:0 0 8px #22c55e; }
.dot-busy { background:#f97316; }
.dot-gray { background:#475569; }
.pulse { animation:pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:0.3;} }
#last-updated { font-size:10px;color:#475569;font-weight:500; }
#ws-status { display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;background:rgba(34,197,94,.12);color:#22c55e;transition:all .3s; }
#ws-status.offline { background:rgba(239,68,68,.12);color:#ef4444; }

/* Driver panel */
.driver-panel { position:absolute;right:12px;top:12px;width:290px;background:#0f172a;border:1px solid rgba(255,255,255,.08);border-radius:14px;z-index:10;overflow:hidden;max-height:calc(100% - 24px);display:flex;flex-direction:column;box-shadow:0 12px 40px rgba(0,0,0,.4); }
.dp-header { padding:12px 14px;background:rgba(255,255,255,.04);color:#e2e8f0;font-weight:800;font-size:13px;display:flex;align-items:center;gap:8px;border-bottom:1px solid rgba(255,255,255,.06); }
.dp-search { width:100%;padding:8px 12px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:8px;color:#e2e8f0;font-size:12px;margin:8px 0 4px;outline:none; }
.dp-search::placeholder { color:#475569; }
.dp-list { overflow-y:auto;flex:1;scrollbar-width:thin;scrollbar-color:#1e293b transparent; }
.dp-item { padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.04);cursor:pointer;transition:background .15s; }
.dp-item:hover { background:rgba(255,255,255,.05); }
.dp-item.active { background:rgba(59,130,246,.12);border-left:3px solid #3b82f6; }
.dp-name { font-weight:700;font-size:12px;color:#e2e8f0;margin-bottom:4px;display:flex;align-items:center;justify-content:space-between; }
.dp-meta { font-size:10px;color:#64748b;display:flex;align-items:center;gap:6px;flex-wrap:wrap; }
.dp-badge { display:inline-block;padding:1px 7px;border-radius:20px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.04em; }
.dp-badge.available { background:rgba(34,197,94,.15);color:#4ade80; }
.dp-badge.busy { background:rgba(249,115,22,.15);color:#fb923c; }
.dp-badge.offline { background:rgba(100,116,139,.15);color:#94a3b8; }

/* Speed / Battery chips */
.chip { display:inline-flex;align-items:center;gap:3px;padding:1px 5px;border-radius:4px;font-size:9px;font-weight:700; }
.chip-spd { background:rgba(59,130,246,.18);color:#93c5fd; }
.chip-spd.fast { background:rgba(249,115,22,.18);color:#fb923c; }
.chip-spd.vfast { background:rgba(239,68,68,.18);color:#f87171; }
.chip-bat { background:rgba(34,197,94,.18);color:#4ade80; }
.chip-bat.low { background:rgba(239,68,68,.18);color:#f87171; }
.chip-bat.mid { background:rgba(249,115,22,.18);color:#fb923c; }
.chip-rel { background:rgba(99,102,241,.18);color:#a5b4fc; }

/* Offline alert banner */
.alert-bar { display:none;position:absolute;top:0;left:0;right:0;z-index:20;padding:8px 16px;background:rgba(239,68,68,.9);color:#fff;font-size:12px;font-weight:700;text-align:center;backdrop-filter:blur(8px); }
.alert-bar.show { display:block;animation:slideDown .3s ease; }
@keyframes slideDown { from{transform:translateY(-100%)} to{transform:translateY(0)} }

/* Map wrap */
.map-wrap { position:relative; }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Live Driver Tracking</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('admin.dispatch') }}">Dispatch</a></li>
            <li>Live Map</li>
        </ul>
    </div>
    <a href="{{ route('admin.dispatch') }}" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Dispatch
    </a>
</div>

<div class="dispatch-wrap">
    <!-- Stats bar -->
    <div class="dispatch-stats">
        <div class="ds-stat">
            <div class="ds-val"><span class="ds-dot dot-live pulse"></span><span id="cnt-online">0</span></div>
            <div class="ds-lbl">Live Online</div>
        </div>
        <div class="ds-stat">
            <div class="ds-val"><span class="ds-dot dot-busy"></span><span id="cnt-busy">0</span></div>
            <div class="ds-lbl">On Delivery</div>
        </div>
        <div class="ds-stat">
            <div class="ds-val"><span class="ds-dot dot-gray"></span><span id="cnt-total">0</span></div>
            <div class="ds-lbl">Total Active</div>
        </div>
        <div class="ds-stat">
            <div class="ds-val" style="font-size:13px;"><span id="cnt-offline" style="color:#94a3b8;">0</span></div>
            <div class="ds-lbl">Offline</div>
        </div>
        <div class="ds-stat" style="border-right:none;">
            <div class="ds-val" style="font-size:13px;"><span id="cnt-bat-warn" style="color:#f87171;">0</span> <span style="color:#64748b;font-size:11px;">low battery</span></div>
            <div class="ds-lbl">Driver Alerts</div>
        </div>
        <div class="ds-stat" style="margin-left:auto;border-right:none;padding:8px 16px;">
            <div id="ws-status"><span>● LIVE</span></div>
            <div id="last-updated" style="margin-top:3px;">Updating...</div>
        </div>
    </div>

    <!-- Map + panel -->
    <div class="map-wrap">
        <div id="alert-bar" class="alert-bar"></div>
        <div id="live-map"></div>
        <div class="driver-panel">
            <div class="dp-header">
                <i class="fas fa-satellite-dish" style="color:#22c55e;"></i>
                Dispatch Center
                <span id="dp-count" style="margin-left:auto;background:rgba(34,197,94,.15);color:#4ade80;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:900;">0</span>
            </div>
            <div style="padding:0 10px;">
                <input id="driver-search" class="dp-search" type="text" placeholder="🔍  Search driver..." oninput="filterDrivers(this.value)">
            </div>
            <div class="dp-list" id="driver-list"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// ── State ─────────────────────────────────────────────────────────────────
var map, infoWindow;
var driverMarkers  = {};   // keyed by driver_id
var driversData    = {};   // latest data per driver
var trailPolylines = {};   // keyed by driver_id — GPS history polylines
var activeTrailId  = null; // which driver's trail is currently shown
var trailMinutes   = 60;   // default: last 1 hour

// ── Map style: default Google Maps (no custom style) ─────────────────────

// ── Marker: rotating arrow + speed-based color ring ───────────────────────
function makeDriverMarker(color, heading, speed, isStale, batteryLow) {
    var rot    = (heading != null && !isNaN(heading)) ? heading : 0;
    var kmh    = speed || 0;
    var ringC  = isStale ? '#475569'
               : kmh > 60 ? '#ef4444'
               : kmh > 30 ? '#f97316'
               : '#22c55e';
    var batWarn = (batteryLow && !isStale)
        ? '<rect x="34" y="4" width="8" height="4" rx="1" fill="#ef4444"/><rect x="35" y="5" width="6" height="2" fill="#ef4444" opacity=".5"/>'
        : '';
    // Outer ring pulses when live
    var pulse = isStale ? '' : '<circle cx="22" cy="22" r="21" fill="none" stroke="' + ringC + '" stroke-width="1.5" opacity=".3"><animate attributeName="r" values="21;24;21" dur="2s" repeatCount="indefinite"/><animate attributeName="opacity" values=".3;0;.3" dur="2s" repeatCount="indefinite"/></circle>';

    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 44 44">'
        // Direction arrow (rotated around center 22,22)
        + '<g transform="rotate(' + rot + ' 22 22)">'
        // Shadow
        + '<ellipse cx="22" cy="26" rx="10" ry="4" fill="#000" opacity=".25"/>'
        // Body circle
        + '<circle cx="22" cy="22" r="16" fill="' + (isStale ? '#1e293b' : color) + '" stroke="' + ringC + '" stroke-width="2.5"/>'
        // Motorcycle icon
        + '<path fill="#fff" opacity="' + (isStale ? '.4' : '.95') + '" transform="translate(8,10) scale(1.15)" d="M19 8c-.6 0-1.1.1-1.6.3L15.5 6H17V4h-3l-1.5-2H9L7.5 4H5v1.5L3.3 7.7C2.5 8.1 2 9 2 10c0 1.7 1.3 3 3 3s3-1.3 3-3c0-.4-.1-.8-.2-1.1L9.2 8h5.6l1 1.3C15.3 9.8 15 10.4 15 11c0 1.7 1.3 3 3 3s3-1.3 3-3-1.3-3-2-3zm-14 3.5c-.8 0-1.5-.7-1.5-1.5S4.2 8.5 5 8.5c.6 0 1.1.3 1.3.8L5.5 10H5v1h.5c-.2.3-.3.5-.5.5zm13 0c-.8 0-1.5-.7-1.5-1.5s.7-1.5 1.5-1.5 1.5.7 1.5 1.5-.7 1.5-1.5 1.5z"/>'
        // Heading arrow tip (when moving)
        + (kmh > 3 ? '<polygon points="22,3 18,10 22,8 26,10" fill="' + ringC + '" opacity=".95"/>' : '')
        + '</g>'
        + pulse
        + batWarn
        + '</svg>';

    return {
        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
        scaledSize: new google.maps.Size(48, 48),
        anchor: new google.maps.Point(24, 24),
    };
}

function initMap() {
    map = new google.maps.Map(document.getElementById('live-map'), {
        zoom: 13,
        center: { lat: 2.0469, lng: 45.3182 },
        mapTypeId: 'roadmap',
        disableDefaultUI: false,
        zoomControl: true,
        mapTypeControl: true,
        streetViewControl: true,
        fullscreenControl: true,
    });
    infoWindow = new google.maps.InfoWindow();
    fetchDrivers();
    setInterval(fetchDrivers, 5000);
    // Check offline alerts every 30s
    setInterval(checkOfflineAlerts, 30000);
}

// ── Offline alert: flash banner when any driver unreachable >15 min ───────
function checkOfflineAlerts() {
    var bar = document.getElementById('alert-bar');
    var stale = Object.values(driversData).filter(function(d) {
        if (!d.wants_tracking && !d.is_online) return false;
        var sec = d.last_seen_at
            ? Math.floor((Date.now() - new Date(d.last_seen_at).getTime()) / 1000)
            : 9999;
        return sec > 900; // >15 min
    });
    if (stale.length > 0) {
        var names = stale.map(function(d) { return d.name; }).join(', ');
        bar.textContent = '⚠ UNREACHABLE: ' + names + ' — Last seen >' + Math.round(stale[0].last_seen_at ? (Date.now() - new Date(stale[0].last_seen_at).getTime()) / 60000 : 99) + ' min ago';
        bar.classList.add('show');
    } else {
        bar.classList.remove('show');
    }
}

// ── Strip emoji / non-BMP characters (fixes mojibake from DB) ────────────
function stripEmoji(str) {
    if (!str) return '';
    // Remove emoji and other non-BMP chars; keep ASCII + extended Latin
    return str.replace(/[\u{1F000}-\u{1FFFF}]|[☀-⟿][️]?|[\uD800-\uDBFF][\uDC00-\uDFFF]/gu, '').trim();
}

// ── Vehicle type → Font Awesome icon + label ──────────────────────────────
function vehicleIcon(type) {
    var clean = stripEmoji(type);
    var t = (clean || '').toLowerCase();
    if (t.includes('motorcycle') || t.includes('motorbike') || t.includes('scooter') || t.includes('bike'))
        return '<i class="fas fa-motorcycle" style="margin-right:4px;"></i>' + (clean || 'Motorcycle');
    if (t.includes('bicycle') || t.includes('cycle'))
        return '<i class="fas fa-bicycle" style="margin-right:4px;"></i>' + (clean || 'Bicycle');
    if (t.includes('truck') || t.includes('van'))
        return '<i class="fas fa-truck" style="margin-right:4px;"></i>' + (clean || 'Truck');
    if (t.includes('car') || t.includes('auto'))
        return '<i class="fas fa-car" style="margin-right:4px;"></i>' + (clean || 'Car');
    return '<i class="fas fa-shipping-fast" style="margin-right:4px;"></i>' + (clean || type || 'Vehicle');
}

// ── Fetch & Render ────────────────────────────────────────────────────────
function fetchDrivers() {
    fetch('{{ route('admin.dispatch.live-drivers') }}')
        .then(r => r.json())
        .then(json => {
            document.getElementById('last-updated').textContent = 'Updated: ' + new Date().toLocaleTimeString();
            renderDrivers(json.data || []);
        })
        .catch(() => {});
}

// ── Driver search filter ──────────────────────────────────────────────────
var _searchQuery = '';
function filterDrivers(q) {
    _searchQuery = (q || '').toLowerCase();
    renderDrivers(Object.values(driversData));
}

function renderDrivers(drivers) {
    var online = 0, busy = 0, batWarn = 0, offlineCnt = 0;

    // Update state cache
    drivers.forEach(function(d) { driversData[d.id] = d; });

    // Remove markers for drivers no longer in list
    var activeIds = drivers.map(function(d) { return d.id; });
    Object.keys(driverMarkers).forEach(function(id) {
        if (!activeIds.includes(parseInt(id))) {
            driverMarkers[id].setMap(null);
            delete driverMarkers[id];
        }
    });

    drivers.forEach(function(d) {
        driversData[d.id] = d;

        var isOffline = !d.is_online || d.status === 'offline';
        var isBusy    = d.status === 'busy';
        var freshSec  = d.last_seen_at
            ? Math.floor((Date.now() - new Date(d.last_seen_at).getTime()) / 1000)
            : 9999;
        var isFresh   = freshSec <= 660;
        var isStale   = !isFresh || isOffline;
        var batLow    = d.battery_level != null && d.battery_level < 20;
        var kmh       = d.speed || 0;

        if (isOffline) offlineCnt++;
        else if (isBusy) busy++;
        else online++;
        if (batLow && !isOffline) batWarn++;

        // Skip map marker if no coordinates
        if (!d.latitude || !d.longitude) return;

        // Marker color
        var pos = new google.maps.LatLng(d.latitude, d.longitude);
        var markerColor = isOffline ? '#374151'
                        : isBusy   ? '#f97316'
                        : isStale  ? '#1e293b'
                        : kmh > 60 ? '#ef4444'
                        : kmh > 30 ? '#f97316'
                        : '#0ea5e9';
        var icon = makeDriverMarker(markerColor, d.heading, kmh, isStale || isOffline, batLow);

        if (driverMarkers[d.id]) {
            driverMarkers[d.id].setPosition(pos);
            driverMarkers[d.id].setIcon(icon);
        } else {
            var marker = new google.maps.Marker({
                position: pos,
                map: map,
                icon: icon,
                title: stripEmoji(d.name),
                animation: google.maps.Animation.DROP,
            });
            marker.addListener('click', (function(driver) { return function() {
                var dd = driversData[driver.id] || driver;
                var iB = dd.status === 'busy';
                var hasPick = dd.order && dd.order.pickup_lat && dd.order.pickup_lng;
                var hasDeliv = dd.order && dd.order.delivery_lat && dd.order.delivery_lng;

                var rel   = dd.missed_pings > 0 ? Math.max(0, 100 - dd.missed_pings * 8) : 100;
                var relColor = rel >= 80 ? '#22c55e' : rel >= 50 ? '#f97316' : '#ef4444';
                var speedStr = (dd.speed != null) ? Math.round(dd.speed) + ' km/h' : '—';
                var batStr   = (dd.battery_level != null) ? dd.battery_level + '%' : '—';
                var batColor = (dd.battery_level != null && dd.battery_level < 20) ? '#ef4444' : '#22c55e';
                var content = '<div style="font-family:sans-serif;min-width:230px;background:#0f172a;color:#e2e8f0;border-radius:10px;overflow:hidden;">' +
                    '<div style="background:linear-gradient(135deg,#1e293b,#0f172a);padding:12px 14px;border-bottom:1px solid #334155;">' +
                    '<div style="font-weight:800;font-size:15px;margin-bottom:3px;">' + dd.name + '</div>' +
                    '<div style="font-size:11px;color:#64748b;"><i class="fas fa-phone"></i> ' + (dd.phone || '—') + ' &nbsp;·&nbsp; ' + (dd.vehicle_type ? vehicleIcon(dd.vehicle_type) : '—') + '</div>' +
                    '</div>' +
                    '<div style="padding:10px 14px;">' +
                    '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;">' +
                    '<span style="background:#1e40af22;color:#60a5fa;border:1px solid #1e40af44;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">⚡ ' + speedStr + '</span>' +
                    '<span style="background:#' + (dd.battery_level < 20 ? 'ef444422' : '22c55e22') + ';color:' + batColor + ';border:1px solid ' + batColor + '44;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">🔋 ' + batStr + '</span>' +
                    '<span style="background:' + relColor + '22;color:' + relColor + ';border:1px solid ' + relColor + '44;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">📡 ' + rel + '%</span>' +
                    '</div>' +
                    '<div style="font-size:12px;margin-bottom:4px;"><b>Status:</b> <span style="color:' + (iB ? '#f97316' : '#22c55e') + '">' + dd.status.toUpperCase() + '</span></div>' +
                    (dd.order ? '<div style="font-size:12px;margin-bottom:6px;padding:6px;background:#1e293b;border-radius:6px;border:1px solid #334155;"><b>Order:</b> #' + dd.order.order_number + ' (' + dd.order.status + ')</div>' : '') +
                    '<div style="font-size:10px;color:#64748b;margin-bottom:8px;">Last seen: ' + dd.last_seen + '</div>' +
                    '<div style="display:flex;gap:6px;margin-top:8px;">' +
                    '<button onclick="requestLocation(' + dd.id + ', this)" style="flex:1;padding:6px;border:none;border-radius:6px;background:#1e40af;color:#fff;font-size:12px;font-weight:700;cursor:pointer;">📍 Location</button>' +
                    (!dd.is_online ? '<button onclick="forceOnline(' + dd.id + ', this)" style="flex:1;padding:6px;border:none;border-radius:6px;background:#166534;color:#4ade80;font-size:12px;font-weight:700;cursor:pointer;">⚡ Set Online</button>' : '') +
                    '</div>' +
                    // GPS trail controls
                    '<div style="margin-top:8px;background:#f8fafc;border-radius:8px;padding:8px;">' +
                    '<div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:5px;">🛤 GPS Trail</div>' +
                    '<div style="display:flex;gap:4px;margin-bottom:6px;">' +
                    '<button class="trail-btn" onclick="setTrailMinutes(30,this)" style="flex:1;padding:4px;border:1px solid #e5e7eb;border-radius:5px;background:#fff;font-size:10px;cursor:pointer;">30m</button>' +
                    '<button class="trail-btn" onclick="setTrailMinutes(60,this)" style="flex:1;padding:4px;border:1px solid #e5e7eb;border-radius:5px;background:#fff;font-size:10px;font-weight:800;cursor:pointer;">1h</button>' +
                    '<button class="trail-btn" onclick="setTrailMinutes(240,this)" style="flex:1;padding:4px;border:1px solid #e5e7eb;border-radius:5px;background:#fff;font-size:10px;cursor:pointer;">4h</button>' +
                    '<button class="trail-btn" onclick="setTrailMinutes(480,this)" style="flex:1;padding:4px;border:1px solid #e5e7eb;border-radius:5px;background:#fff;font-size:10px;cursor:pointer;">8h</button>' +
                    '</div>' +
                    '<div style="display:flex;gap:4px;">' +
                    '<button data-trail-btn="' + dd.id + '" onclick="showTrail(' + dd.id + ')" style="flex:1;padding:5px;border:none;border-radius:5px;background:#22c55e;color:#fff;font-size:11px;font-weight:700;cursor:pointer;">▶ Show Trail</button>' +
                    '<button onclick="hideTrail(' + dd.id + ')" style="flex:1;padding:5px;border:1px solid #e5e7eb;border-radius:5px;background:#fff;color:#374151;font-size:11px;cursor:pointer;">✕ Hide</button>' +
                    '</div>' +
                    '</div>' +
                    (hasPick && hasDeliv ?
                        '<button onclick="drawDriverRoute(' + dd.latitude + ',' + dd.longitude + ',' + dd.order.pickup_lat + ',' + dd.order.pickup_lng + ',' + dd.order.delivery_lat + ',' + dd.order.delivery_lng + ')" style="margin-top:6px;width:100%;padding:6px;border:none;border-radius:6px;background:#f97316;color:#fff;font-size:12px;font-weight:700;cursor:pointer;">🗺 Show Delivery Route</button>' +
                        '<button onclick="clearRoute()" style="margin-top:4px;width:100%;padding:5px;border:1px solid #334155;border-radius:6px;background:#1e293b;color:#94a3b8;font-size:11px;cursor:pointer;">✕ Clear Route</button>'
                    : '') +
                    '</div></div>';
                infoWindow.setContent(content);
                infoWindow.open(map, marker);

                // Auto-draw route if driver has an active order with coordinates
                if (hasPick && hasDeliv) {
                    drawDriverRoute(dd.latitude, dd.longitude, dd.order.pickup_lat, dd.order.pickup_lng, dd.order.delivery_lat, dd.order.delivery_lng);
                }
            }; })(d));
            driverMarkers[d.id] = marker;
        }
    });

    // Stats
    var total = online + busy;
    document.getElementById('cnt-online').textContent  = online;
    document.getElementById('cnt-busy').textContent    = busy;
    document.getElementById('cnt-total').textContent   = total;
    document.getElementById('dp-count').textContent    = total;
    document.getElementById('cnt-offline').textContent = offlineCnt;
    var batEl = document.getElementById('cnt-bat-warn');
    if (batEl) batEl.textContent = batWarn;

    // Side panel — apply search filter
    var filtered = _searchQuery
        ? drivers.filter(function(d) { return stripEmoji(d.name).toLowerCase().indexOf(_searchQuery) !== -1; })
        : drivers;

    var list = document.getElementById('driver-list');
    if (filtered.length === 0) {
        list.innerHTML = '<div style="padding:24px;text-align:center;color:#64748b;font-size:13px;"><i class="fas fa-motorcycle" style="font-size:24px;margin-bottom:8px;display:block;opacity:.4;"></i>' + (_searchQuery ? 'No match found' : 'No drivers') + '</div>';
        return;
    }

    // Split online vs offline
    var onlineDrivers  = filtered.filter(function(d) { return d.is_online && d.status !== 'offline'; });
    var offlineDrivers = filtered.filter(function(d) { return !d.is_online || d.status === 'offline'; });

    function buildDriverCard(d) {
        var isOff   = !d.is_online || d.status === 'offline';
        var isBusy2 = d.status === 'busy';
        var freshSec2 = d.last_seen_at ? Math.floor((Date.now() - new Date(d.last_seen_at).getTime())/1000) : 9999;
        var isStale2  = freshSec2 > 660;
        var kmh2      = d.speed || 0;
        var batLow2   = d.battery_level != null && d.battery_level < 20;
        var rel2      = d.missed_pings > 0 ? Math.max(0, 100 - d.missed_pings * 8) : 100;
        var relColor2 = rel2 >= 80 ? '#22c55e' : rel2 >= 50 ? '#f59e0b' : '#ef4444';
        var liveChip  = isOff
            ? '<span style="color:#475569;font-size:10px;">⭘ Offline &nbsp;·&nbsp; ' + d.last_seen + '</span>'
            : isStale2
                ? '<span style="color:#ef4444;font-size:10px;font-weight:700;">⚠ ' + d.last_seen + '</span>'
                : freshSec2 <= 30
                    ? '<span style="color:#22c55e;font-size:10px;font-weight:700;">● Live</span>'
                    : '<span style="color:#22c55e;font-size:10px;">● ' + d.last_seen + '</span>';
        var ordersBadge = (isBusy2 && d.active_orders_count > 0)
            ? '<span style="margin-left:5px;background:#f97316;color:#fff;border-radius:20px;padding:1px 6px;font-size:10px;font-weight:800;">' + d.active_orders_count + '×</span>'
            : '';
        var chips = '';
        if (!isOff && kmh2 > 0) chips += '<span style="background:#1e3a5f;color:#60a5fa;padding:1px 6px;border-radius:10px;font-size:10px;">⚡' + Math.round(kmh2) + 'km/h</span> ';
        if (!isOff && d.battery_level != null) chips += '<span style="background:' + (batLow2 ? '#3f1515' : '#152b1e') + ';color:' + (batLow2 ? '#f87171' : '#4ade80') + ';padding:1px 6px;border-radius:10px;font-size:10px;">🔋' + d.battery_level + '%</span> ';
        if (!isOff) chips += '<span style="background:' + relColor2 + '22;color:' + relColor2 + ';padding:1px 6px;border-radius:10px;font-size:10px;">📡' + rel2 + '%</span>';

        var badgeClass = isOff ? 'offline' : (isBusy2 ? 'busy' : 'available');
        var badgeText  = isOff ? 'offline' : d.status;

        var forceOnlineBtn = isOff
            ? '<button class="btn-force-online" id="fob-' + d.id + '" onclick="event.stopPropagation();forceOnline(' + d.id + ',this)">⚡ Set Online</button>'
            : '';

        var clickFn = (d.latitude && d.longitude)
            ? 'panTo(' + d.latitude + ',' + d.longitude + ',' + d.id + ')'
            : '';

        return '<div class="dp-item" ' + (clickFn ? 'onclick="' + clickFn + '"' : '') + ' style="' + (isOff ? 'opacity:.7;' : '') + '">' +
            '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">' +
            '<div class="dp-name">' + stripEmoji(d.name) + ordersBadge + '</div>' +
            '<span class="dp-badge ' + badgeClass + '">' + badgeText + '</span>' +
            '</div>' +
            (chips ? '<div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:4px;">' + chips + '</div>' : '') +
            '<div class="dp-meta" style="justify-content:space-between;">' +
            '<span>' + (d.vehicle_type ? vehicleIcon(d.vehicle_type) : '') + ' &nbsp;·&nbsp; ' + liveChip + '</span>' +
            forceOnlineBtn +
            '</div>' +
        '</div>';
    }

    var html = '';
    if (onlineDrivers.length > 0) {
        html += '<div class="dp-section-hdr">🟢 ONLINE (' + onlineDrivers.length + ')</div>';
        html += onlineDrivers.map(buildDriverCard).join('');
    }
    if (offlineDrivers.length > 0) {
        html += '<div class="dp-section-hdr">⭘ OFFLINE (' + offlineDrivers.length + ')</div>';
        html += offlineDrivers.map(buildDriverCard).join('');
    }
    list.innerHTML = html;
}

// ── Force Driver Online ───────────────────────────────────────────────────
function forceOnline(driverId, btn) {
    btn.disabled = true;
    btn.textContent = '⏳';
    fetch('/admin/dispatch/drivers/' + driverId + '/force-online', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(function(d) {
        if (d.success) {
            btn.textContent = '✓ Set Online';
            btn.style.color = '#4ade80';
            showToast('✓ ' + d.driver_name + ' set online' + (d.message.includes('FCM') ? ' + reminder sent' : ''), '#22c55e');
            // Refresh data
            setTimeout(fetchDrivers, 1500);
        } else {
            btn.disabled = false;
            btn.textContent = '⚡ Set Online';
            showToast('✗ Failed: ' + (d.message || 'Error'), '#ef4444');
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.textContent = '⚡ Set Online';
        showToast('✗ Network error', '#ef4444');
    });
}

function requestLocation(driverId, btn) {
    btn.disabled = true;
    btn.textContent = '⏳';
    fetch('/admin/dispatch/drivers/' + driverId + '/request-location', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(d => {
        btn.textContent = d.success ? '✓ Sent' : '✗ Failed';
        setTimeout(() => { btn.disabled = false; btn.textContent = '📍 Request Location'; }, 3000);
    })
    .catch(() => { btn.textContent = '✗ Error'; setTimeout(() => { btn.disabled = false; btn.textContent = '📍 Request Location'; }, 3000); });
}

function panTo(lat, lng, driverId) {
    map.panTo({ lat: lat, lng: lng });
    map.setZoom(16);
    if (driverMarkers[driverId]) {
        google.maps.event.trigger(driverMarkers[driverId], 'click');
    }
    // Show this driver's trail automatically on panel click
    showTrail(driverId);
}

// ── GPS Trail — Road-Snapped via DirectionsService ───────────────────────
var trailColors   = ['#3b82f6','#8b5cf6','#ec4899','#14b8a6','#f59e0b','#ef4444'];
var trailColorMap = {};
var trailRenderers  = {};  // { driverId: [DirectionsRenderer | Polyline, ...] }

function driverTrailColor(driverId) {
    if (!trailColorMap[driverId]) {
        trailColorMap[driverId] = trailColors[Object.keys(trailColorMap).length % trailColors.length];
    }
    return trailColorMap[driverId];
}

// Clear all trail layers for a driver
function clearDriverTrail(driverId) {
    (trailRenderers[driverId] || []).forEach(function(r) { if (r && r.setMap) r.setMap(null); });
    delete trailRenderers[driverId];
    if (trailPolylines[driverId]) { trailPolylines[driverId].setMap(null); delete trailPolylines[driverId]; }
}

function showTrail(driverId) {
    if (activeTrailId && activeTrailId !== driverId) clearDriverTrail(activeTrailId);
    activeTrailId = driverId;

    var btn = document.querySelector('[data-trail-btn="' + driverId + '"]');
    if (btn) { btn.textContent = '⏳ Loading...'; btn.disabled = true; }

    fetch('/admin/dispatch/drivers/' + driverId + '/route?minutes=' + trailMinutes, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (btn) { btn.textContent = '▶ Show Trail'; btn.disabled = false; }
            if (!data.success || !data.points || data.points.length < 2) {
                showToast('📍 No trail yet for this window.', '#f59e0b');
                return;
            }
            var color = driverTrailColor(driverId);
            showToast('🛤 ' + data.points.length + ' pts — snapping to roads...', color);
            _drawRoadSnappedTrail(driverId, data.points, color);
        })
        .catch(function(err) {
            if (btn) { btn.textContent = '▶ Show Trail'; btn.disabled = false; }
            showToast('Trail load failed', '#ef4444');
            console.error('[Trail]', err);
        });
}


function _drawRoadSnappedTrail(driverId, points, color) {
    clearDriverTrail(driverId);
    trailRenderers[driverId] = [];

    // Points are already road-snapped by backend (server-side Roads API call)
    var fullPath = points.map(function(p) {
        return { lat: parseFloat(p.lat), lng: parseFloat(p.lng) };
    });

    // Draw as 6 gradient segments: oldest=faint, newest=bright
    var SEGS    = 6;
    var total   = fullPath.length;
    var segSize = Math.ceil(total / SEGS);
    for (var s = 0; s < SEGS; s++) {
        var st  = s * segSize;
        var en  = Math.min(st + segSize + 1, total);
        if (st >= total - 1) break;
        var opacity = 0.20 + (s / (SEGS - 1)) * 0.75;
        var weight  = (s === SEGS - 1) ? 7 : 5;
        var poly = new google.maps.Polyline({
            map: map, path: fullPath.slice(st, en),
            strokeColor: color, strokeWeight: weight,
            strokeOpacity: opacity, geodesic: true, zIndex: s + 1,
        });
        trailRenderers[driverId].push(poly);
    }

    // Fit map to trail bounds
    var bounds = new google.maps.LatLngBounds();
    fullPath.forEach(function(p) { bounds.extend(p); });
    map.fitBounds(bounds, { padding: 80 });
    showToast('✅ Trail · ' + fullPath.length + ' pts · ' + trailMinutes + 'min', color);
}

// Small toast notification on the map
var _toastTimer = null;
function showToast(msg, color) {
    var t = document.getElementById('map-toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'map-toast';
        t.style.cssText = 'position:absolute;bottom:24px;left:50%;transform:translateX(-50%);padding:10px 18px;border-radius:10px;color:#fff;font-size:13px;font-weight:600;z-index:999;box-shadow:0 4px 16px rgba(0,0,0,.25);transition:opacity .3s;pointer-events:none;white-space:nowrap;max-width:420px;text-align:center;';
        document.querySelector('.map-wrap').appendChild(t);
    }
    t.style.background = color || '#1e293b';
    t.textContent = msg;
    t.style.opacity = '1';
    if (_toastTimer) clearTimeout(_toastTimer);
    _toastTimer = setTimeout(function() { t.style.opacity = '0'; }, 5000);
}

function hideTrail(driverId) {
    clearDriverTrail(driverId);
    if (activeTrailId === driverId) activeTrailId = null;
}

function setTrailMinutes(mins, btn) {
    trailMinutes = mins;
    document.querySelectorAll('.trail-btn').forEach(function(b) { b.style.fontWeight = '400'; });
    if (btn) btn.style.fontWeight = '800';
    if (activeTrailId) showTrail(activeTrailId);
}

// Real-time location arrives — trail reloads only on explicit user request
function extendTrail(driverId, lat, lng) {
    // No live tail; driver marker position is updated by the main location handler
}

// ── Route drawing — Directions API ────────────────────────────────────────
var activeRouteRenderer = null;

function drawDriverRoute(driverLat, driverLng, pickupLat, pickupLng, delivLat, delivLng) {
    if (activeRouteRenderer) {
        activeRouteRenderer.setMap(null);
        activeRouteRenderer = null;
    }
    if (!pickupLat || !delivLat) return;

    var svc      = new google.maps.DirectionsService();
    var renderer = new google.maps.DirectionsRenderer({
        map:              map,
        suppressMarkers:  true,
        polylineOptions: {
            strokeColor:   '#f97316',
            strokeWeight:  5,
            strokeOpacity: 0.85,
        },
    });

    // Waypoint: pickup (vendor)
    svc.route({
        origin:      { lat: parseFloat(driverLat),  lng: parseFloat(driverLng) },
        destination: { lat: parseFloat(delivLat),   lng: parseFloat(delivLng)  },
        waypoints:   [{ location: { lat: parseFloat(pickupLat), lng: parseFloat(pickupLng) }, stopover: true }],
        travelMode:  google.maps.TravelMode.DRIVING,
    }, function(result, status) {
        if (status === 'OK') {
            renderer.setDirections(result);
            activeRouteRenderer = renderer;
        }
    });
}

function clearRoute() {
    if (activeRouteRenderer) {
        activeRouteRenderer.setMap(null);
        activeRouteRenderer = null;
    }
}

// ── Reverb real-time updates ───────────────────────────────────────────────
(function() {
    // Load Laravel Echo + Pusher-compatible client (Reverb uses Pusher protocol)
    function loadScript(src, cb) {
        var s = document.createElement('script');
        s.src = src; s.onload = cb; document.head.appendChild(s);
    }

    loadScript('https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js', function() {
        loadScript('https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js', function() {
            try {
                var echo = new LaravelEcho.default({
                    broadcaster: 'reverb',
                    key: '{{ config('broadcasting.connections.reverb.key') }}',
                    wsHost: '{{ config('broadcasting.connections.reverb.host') }}',
                    wsPort: {{ config('broadcasting.connections.reverb.port', 6001) }},
                    wssPort: {{ config('broadcasting.connections.reverb.port', 6001) }},
                    forceTLS: false,
                    enabledTransports: ['ws', 'wss'],
                    disableStats: true,
                });

                // Channel: admin.dispatch | Event: .driver_location (broadcastAs name)
                echo.channel('admin.dispatch').listen('.driver_location', function(e) {
                    console.log('[LiveMap] WS update', e);
                    var now = new Date().toISOString();
                    var existing = driversData[e.deliveryman_id] || {};
                    var updated  = Object.assign({}, existing, {
                        id:            e.deliveryman_id,
                        name:          e.name || existing.name || 'Driver',
                        latitude:      parseFloat(e.lat),
                        longitude:     parseFloat(e.lng),
                        status:        e.status || existing.status || 'available',
                        is_online:     true,
                        is_stale:      false,
                        last_seen:     'just now',
                        last_seen_at:  e.updated_at || now,
                        speed:         e.speed     != null ? parseFloat(e.speed)     : existing.speed,
                        heading:       e.heading   != null ? parseFloat(e.heading)   : existing.heading,
                        battery_level: e.battery_level != null ? parseInt(e.battery_level) : existing.battery_level,
                        missed_pings:  e.missed_pings  != null ? parseInt(e.missed_pings)  : 0,
                    });
                    driversData[e.deliveryman_id] = updated;
                    renderDrivers(Object.values(driversData));
                    // Extend the active trail polyline in real-time (no fetch needed)
                    extendTrail(e.deliveryman_id, e.lat, e.lng);
                });

                echo.connector.pusher.connection.bind('state_change', function(states) {
                    console.log('[LiveMap] WS state:', states.current);
                });
            } catch(err) {
                console.warn('[LiveMap] Echo init error:', err);
            }
        });
    });
})();
</script>
<script async defer
    src="https://maps.googleapis.com/maps/api/js?key={{ \App\Models\Setting::get('google_maps_api_key', config('services.maps_api_key', env('GOOGLE_MAPS_API_KEY'))) }}&libraries=directions&callback=initMap">
</script>
@endpush
