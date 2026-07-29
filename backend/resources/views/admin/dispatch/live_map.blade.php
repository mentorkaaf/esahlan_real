@extends('admin.layouts.app')
@section('title', 'Live Driver Map')

@push('styles')
<style>
#live-map { width:100%;height:calc(100vh - 200px);min-height:500px;border-radius:16px;overflow:hidden; }
.map-toolbar { display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap; }
.map-stat { background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:10px 16px;display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700; }
.dot { width:10px;height:10px;border-radius:50%;display:inline-block; }
.dot-green { background:#22c55e; }
.dot-orange { background:#f97316; }
.dot-gray { background:#9ca3af; }
.pulse { animation:pulse 1.5s infinite; }
@keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:0.4;} }
#last-updated { font-size:11px;color:#9ca3af;margin-left:auto; }
.driver-panel { position:absolute;right:16px;top:80px;width:280px;background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.12);z-index:10;overflow:hidden;max-height:calc(100% - 100px);display:flex;flex-direction:column; }
.dp-header { padding:14px 16px;background:#1e293b;color:#fff;font-weight:800;font-size:14px;display:flex;align-items:center;gap:8px; }
.dp-list { overflow-y:auto;flex:1; }
.dp-item { padding:12px 14px;border-bottom:1px solid #f3f4f6;cursor:pointer;transition:background .15s; }
.dp-item:hover { background:#f9fafb; }
.dp-name { font-weight:700;font-size:13px;color:#111;margin-bottom:3px; }
.dp-meta { font-size:11px;color:#6b7280; }
.dp-badge { display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700; }
.dp-badge.available { background:#dcfce7;color:#15803d; }
.dp-badge.busy { background:#fef9c3;color:#92400e; }
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

<div class="map-toolbar">
    <div class="map-stat">
        <span class="dot dot-green pulse"></span>
        <span id="cnt-online">0</span> Online
    </div>
    <div class="map-stat">
        <span class="dot dot-orange"></span>
        <span id="cnt-busy">0</span> On Delivery
    </div>
    <div class="map-stat">
        <span class="dot dot-gray"></span>
        <span id="cnt-total">0</span> Total Active
    </div>
    <div id="last-updated">Updating...</div>
</div>

<div class="map-wrap">
    <div id="live-map"></div>
    <div class="driver-panel">
        <div class="dp-header">
            <i class="fas fa-motorcycle"></i> Active Drivers
            <span id="dp-count" style="margin-left:auto;background:rgba(255,255,255,.2);padding:2px 8px;border-radius:20px;font-size:11px;">0</span>
        </div>
        <div class="dp-list" id="driver-list"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// ── State ─────────────────────────────────────────────────────────────────
var map, infoWindow;
var driverMarkers = {};   // keyed by driver_id
var driversData   = {};   // latest data per driver

// ── Init Map ──────────────────────────────────────────────────────────────
function initMap() {
    map = new google.maps.Map(document.getElementById('live-map'), {
        zoom: 13,
        center: { lat: 2.0469, lng: 45.3182 },  // Mogadishu default
        mapTypeId: 'roadmap',
        styles: [
            { featureType:'poi', stylers:[{visibility:'off'}] },
            { featureType:'transit', stylers:[{visibility:'off'}] }
        ],
    });
    infoWindow = new google.maps.InfoWindow();
    fetchDrivers();
    setInterval(fetchDrivers, 5000);
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

function renderDrivers(drivers) {
    var online = 0, busy = 0;

    // Remove markers for drivers no longer active
    var activeIds = drivers.map(d => d.id);
    Object.keys(driverMarkers).forEach(id => {
        if (!activeIds.includes(parseInt(id))) {
            driverMarkers[id].setMap(null);
            delete driverMarkers[id];
            delete driversData[id];
        }
    });

    drivers.forEach(function(d) {
        if (!d.latitude || !d.longitude) return;
        driversData[d.id] = d;

        var pos = { lat: d.latitude, lng: d.longitude };
        var isBusy = d.status === 'busy';
        if (isBusy) busy++; else online++;

        var icon = {
            path: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z',
            fillColor: isBusy ? '#f97316' : '#22c55e',
            fillOpacity: 1,
            strokeColor: '#fff',
            strokeWeight: 2,
            scale: 1.6,
            anchor: new google.maps.Point(12, 22),
        };

        if (driverMarkers[d.id]) {
            driverMarkers[d.id].setPosition(pos);
            driverMarkers[d.id].setIcon(icon);
        } else {
            var marker = new google.maps.Marker({
                position: pos,
                map: map,
                icon: icon,
                title: d.name,
                animation: google.maps.Animation.DROP,
            });
            marker.addListener('click', function() {
                var content = '<div style="font-family:sans-serif;min-width:200px;">' +
                    '<div style="font-weight:800;font-size:14px;margin-bottom:6px;">' + d.name + '</div>' +
                    '<div style="font-size:12px;color:#666;margin-bottom:4px;"><i class="fas fa-phone"></i> ' + (d.phone || '—') + '</div>' +
                    '<div style="font-size:12px;margin-bottom:4px;"><b>Status:</b> <span style="color:' + (isBusy ? '#f97316' : '#22c55e') + '">' + d.status.toUpperCase() + '</span></div>' +
                    '<div style="font-size:12px;color:#666;"><b>Vehicle:</b> ' + (d.vehicle_type || '—') + '</div>' +
                    (d.order ? '<div style="font-size:12px;margin-top:6px;padding:6px;background:#fef9c3;border-radius:6px;"><b>Order:</b> #' + d.order.order_number + ' (' + d.order.status + ')</div>' : '') +
                    '<div style="font-size:10px;color:#9ca3af;margin-top:6px;">Last seen: ' + d.last_seen + '</div>' +
                '</div>';
                infoWindow.setContent(content);
                infoWindow.open(map, marker);
            });
            driverMarkers[d.id] = marker;
        }
    });

    // Stats
    var total = online + busy;
    document.getElementById('cnt-online').textContent = online;
    document.getElementById('cnt-busy').textContent   = busy;
    document.getElementById('cnt-total').textContent  = total;
    document.getElementById('dp-count').textContent   = total;

    // Side panel
    var list = document.getElementById('driver-list');
    if (drivers.length === 0) {
        list.innerHTML = '<div style="padding:24px;text-align:center;color:#9ca3af;font-size:13px;"><i class="fas fa-motorcycle" style="font-size:24px;margin-bottom:8px;display:block;"></i>No active drivers</div>';
        return;
    }
    list.innerHTML = drivers.map(function(d) {
        return '<div class="dp-item" onclick="panTo(' + d.latitude + ',' + d.longitude + ',' + d.id + ')">' +
            '<div style="display:flex;align-items:center;justify-content:space-between;">' +
            '<div class="dp-name">' + d.name + '</div>' +
            '<span class="dp-badge ' + d.status + '">' + d.status + '</span>' +
            '</div>' +
            '<div class="dp-meta">' +
            (d.order ? '📦 #' + d.order.order_number + ' &nbsp;·&nbsp; ' : '') +
            (d.vehicle_type || '') + ' &nbsp;·&nbsp; ' + d.last_seen +
            '</div>' +
        '</div>';
    }).join('');
}

function panTo(lat, lng, driverId) {
    map.panTo({ lat: lat, lng: lng });
    map.setZoom(16);
    if (driverMarkers[driverId]) {
        google.maps.event.trigger(driverMarkers[driverId], 'click');
    }
}

// ── Reverb real-time updates ───────────────────────────────────────────────
@if(config('broadcasting.default') === 'reverb')
(function() {
    var script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js';
    script.onload = function() {
        var PusherClient = window.Pusher || window.pusher;
        // Use native WebSocket via reverb
        var echo = new LaravelEcho.default({
            broadcaster: 'reverb',
            key: '{{ config('broadcasting.connections.reverb.key') }}',
            wsHost: '{{ config('broadcasting.connections.reverb.host') }}',
            wsPort: {{ config('broadcasting.connections.reverb.port', 443) }},
            wssPort: {{ config('broadcasting.connections.reverb.port', 443) }},
            forceTLS: '{{ config('broadcasting.connections.reverb.scheme', 'https') }}' === 'https',
            enabledTransports: ['ws', 'wss'],
        });
        echo.channel('admin.drivers').listen('.location.updated', function(e) {
            // Merge real-time update with existing data
            var existing = driversData[e.driver_id] || {};
            var updated = Object.assign({}, existing, {
                id: e.driver_id,
                name: e.driver_name || existing.name || 'Driver',
                latitude: e.latitude,
                longitude: e.longitude,
                status: e.status,
                last_seen: 'just now',
            });
            driversData[e.driver_id] = updated;
            renderDrivers(Object.values(driversData));
        });
    };
    document.head.appendChild(script);
})();
@endif
</script>
<script async defer
    src="https://maps.googleapis.com/maps/api/js?key={{ \App\Models\Setting::get('google_maps_api_key', config('services.maps_api_key', env('GOOGLE_MAPS_API_KEY'))) }}&callback=initMap">
</script>
@endpush
