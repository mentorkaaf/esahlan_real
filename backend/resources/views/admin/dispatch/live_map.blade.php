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
// Motorcycle/bike icon — circular badge, no pin shape
function makeBikeMarkerIcon(color, opacity) {
    opacity = (typeof opacity === 'number') ? opacity : 1;
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 44 44">'
        + '<circle cx="22" cy="22" r="20" fill="' + color + '" fill-opacity="' + opacity + '" stroke="#ffffff" stroke-width="2.5"/>'
        + '<path fill="#ffffff" fill-opacity="' + opacity + '" transform="translate(6,6) scale(1.33)" d="M19 8c-.6 0-1.1.1-1.6.3L15.5 6H17V4h-3l-1.5-2H9L7.5 4H5v1.5L3.3 7.7C2.5 8.1 2 9 2 10c0 1.7 1.3 3 3 3s3-1.3 3-3c0-.4-.1-.8-.2-1.1L9.2 8h5.6l1 1.3C15.3 9.8 15 10.4 15 11c0 1.7 1.3 3 3 3s3-1.3 3-3-1.3-3-2-3zm-14 3.5c-.8 0-1.5-.7-1.5-1.5S4.2 8.5 5 8.5c.6 0 1.1.3 1.3.8L5.5 10H5v1h.5c-.2.3-.3.5-.5.5zm13 0c-.8 0-1.5-.7-1.5-1.5s.7-1.5 1.5-1.5 1.5.7 1.5 1.5-.7 1.5-1.5 1.5z"/>'
        + '</svg>';
    return {
        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
        scaledSize: new google.maps.Size(44, 44),
        anchor: new google.maps.Point(22, 22),
    };
}

function initMap() {
    map = new google.maps.Map(document.getElementById('live-map'), {
        zoom: 13,
        center: { lat: 2.0469, lng: 45.3182 },  // Mogadishu default
        mapTypeId: 'roadmap',
        styles: [
            { featureType:'transit', stylers:[{visibility:'off'}] }
        ],
    });
    infoWindow = new google.maps.InfoWindow();
    fetchDrivers();
    setInterval(fetchDrivers, 5000);
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
        if (isBusy) busy++; else if (d.is_online && !d.is_stale) online++;

        var isStale = d.is_stale || false;
        // Green=online+fresh, Orange=busy, Grey=stale/offline
        var fillColor = isBusy ? '#f97316' : isStale ? '#9ca3af' : '#22c55e';
        var icon = makeBikeMarkerIcon(fillColor, isStale ? 0.75 : 1);

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

                var content = '<div style="font-family:sans-serif;min-width:210px;">' +
                    '<div style="font-weight:800;font-size:14px;margin-bottom:6px;">' + dd.name + '</div>' +
                    '<div style="font-size:12px;color:#666;margin-bottom:4px;"><i class="fas fa-phone"></i> ' + (dd.phone || '—') + '</div>' +
                    '<div style="font-size:12px;margin-bottom:4px;"><b>Status:</b> <span style="color:' + (iB ? '#f97316' : '#22c55e') + '">' + dd.status.toUpperCase() + '</span></div>' +
                    '<div style="font-size:12px;color:#666;"><b>Vehicle:</b> ' + (dd.vehicle_type ? vehicleIcon(dd.vehicle_type) : '—') + '</div>' +
                    (dd.order ? '<div style="font-size:12px;margin-top:6px;padding:6px;background:#fef9c3;border-radius:6px;"><b>Order:</b> #' + dd.order.order_number + ' (' + dd.order.status + ')</div>' : '') +
                    '<div style="font-size:10px;color:#9ca3af;margin-top:6px;">Last seen: ' + dd.last_seen + '</div>' +
                    '<button onclick="requestLocation(' + dd.id + ', this)" style="margin-top:8px;width:100%;padding:6px;border:none;border-radius:6px;background:#1e40af;color:#fff;font-size:12px;font-weight:700;cursor:pointer;">📍 Request Location</button>' +
                    (hasPick && hasDeliv ?
                        '<button onclick="drawDriverRoute(' + dd.latitude + ',' + dd.longitude + ',' + dd.order.pickup_lat + ',' + dd.order.pickup_lng + ',' + dd.order.delivery_lat + ',' + dd.order.delivery_lng + ')" style="margin-top:6px;width:100%;padding:6px;border:none;border-radius:6px;background:#f97316;color:#fff;font-size:12px;font-weight:700;cursor:pointer;">🗺 Show Route</button>' +
                        '<button onclick="clearRoute()" style="margin-top:4px;width:100%;padding:5px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;color:#374151;font-size:11px;cursor:pointer;">✕ Clear Route</button>'
                    : '') +
                    '</div>';
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
        var isBusy = d.status === 'busy';
        var ordersBadge = (isBusy && d.active_orders_count > 0)
            ? '<span style="margin-left:5px;background:#f97316;color:#fff;border-radius:20px;padding:1px 7px;font-size:10px;font-weight:800;">' + d.active_orders_count + ' order' + (d.active_orders_count > 1 ? 's' : '') + '</span>'
            : '';
        return '<div class="dp-item" onclick="panTo(' + d.latitude + ',' + d.longitude + ',' + d.id + ')">' +
            '<div style="display:flex;align-items:center;justify-content:space-between;">' +
            '<div class="dp-name">' + stripEmoji(d.name) + ordersBadge + '</div>' +
            '<span class="dp-badge ' + d.status + '">' + d.status + '</span>' +
            '</div>' +
            '<div class="dp-meta">' +
            (d.order ? '📦 #' + d.order.order_number + ' &nbsp;·&nbsp; ' : '') +
            (d.vehicle_type ? vehicleIcon(d.vehicle_type) : '') + ' &nbsp;·&nbsp; ' +
            (d.is_stale ? '<span style="color:#ef4444;font-weight:700;">⚠ Last seen ' + d.last_seen + '</span>' : '<span style="color:#22c55e;">● ' + d.last_seen + '</span>') +
            '</div>' +
        '</div>';
    }).join('');
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

                    // Build / merge driver record
                    var existing = driversData[e.deliveryman_id] || {};
                    var updated  = Object.assign({}, existing, {
                        id:           e.deliveryman_id,
                        name:         e.name || existing.name || 'Driver',
                        latitude:     parseFloat(e.lat),
                        longitude:    parseFloat(e.lng),
                        status:       e.status || existing.status || 'available',
                        last_seen:    'just now',
                        last_seen_at: e.updated_at || new Date().toISOString(),
                    });
                    driversData[e.deliveryman_id] = updated;
                    renderDrivers(Object.values(driversData));
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
