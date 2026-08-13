@extends('vendor.layouts.app')
@section('title', 'Store Profile')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Store Profile</h1>
        <ul class="breadcrumb"><li><a href="{{ route('vendor.dashboard') }}">Dashboard</a></li><li>Store Profile</li></ul>
    </div>
    <form action="{{ route('vendor.store.toggle-open') }}" method="POST" style="margin:0;">
        @csrf
        <button type="submit" class="btn {{ $vendor->temporarily_closed ? 'btn-success' : 'btn-danger' }}">
            <i class="fa-solid {{ $vendor->temporarily_closed ? 'fa-store' : 'fa-store-slash' }}"></i>
            {{ $vendor->temporarily_closed ? 'Open Store' : 'Temporarily Close' }}
        </button>
    </form>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start;">
    {{-- Profile form --}}
    <form action="{{ route('vendor.store.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="card">
            <div class="card-header">
                <div class="card-header-title"><div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);"><i class="fa-solid fa-store"></i></div> Store Information</div>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Store Name <span style="color:var(--danger);">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name',$vendor->name) }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description',$vendor->description) }}</textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone',$vendor->phone) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email',$vendor->email) }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address',$vendor->address) }}">
                </div>

                {{-- ── Map Location Picker ───────────────────────────────────── --}}
                <div class="form-group">
                    <label class="form-label" style="display:flex;align-items:center;gap:8px;">
                        <i class="fa-solid fa-location-dot" style="color:var(--brand);"></i>
                        Store Location on Map
                        <span style="font-size:11px;color:#94a3b8;font-weight:400;">(drag the pin to your exact location)</span>
                    </label>

                    {{-- Lat/Lng display + manual inputs --}}
                    <div style="display:flex;gap:10px;margin-bottom:10px;">
                        <div style="flex:1;">
                            <label style="font-size:11px;color:#64748b;font-weight:600;display:block;margin-bottom:4px;">LATITUDE</label>
                            <input type="number" id="map_lat" name="latitude" step="0.000001"
                                class="form-control" style="font-size:13px;"
                                value="{{ old('latitude', $vendor->latitude) }}"
                                placeholder="e.g. 9.5594">
                        </div>
                        <div style="flex:1;">
                            <label style="font-size:11px;color:#64748b;font-weight:600;display:block;margin-bottom:4px;">LONGITUDE</label>
                            <input type="number" id="map_lng" name="longitude" step="0.000001"
                                class="form-control" style="font-size:13px;"
                                value="{{ old('longitude', $vendor->longitude) }}"
                                placeholder="e.g. 44.0650">
                        </div>
                        <div style="display:flex;align-items:flex-end;">
                            <button type="button" id="detect_location_btn"
                                style="padding:9px 14px;background:var(--brand);color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;">
                                <i class="fa-solid fa-crosshairs"></i> My Location
                            </button>
                        </div>
                    </div>

                    {{-- Map container --}}
                    <div id="vendor_map" style="width:100%;height:300px;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;background:#f0f4f8;"></div>
                    <p style="font-size:11px;color:#94a3b8;margin-top:6px;"><i class="fa-solid fa-circle-info"></i> Click on the map or drag the marker to set your exact store location.</p>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Minimum Order ($)</label>
                        <input type="number" name="minimum_order" class="form-control" value="{{ old('minimum_order',$vendor->minimum_order) }}" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Delivery Fee ($)</label>
                        <input type="number" name="delivery_fee" class="form-control" value="{{ old('delivery_fee',$vendor->delivery_fee) }}" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Delivery Time</label>
                        <input type="text" name="delivery_time" class="form-control" value="{{ old('delivery_time',$vendor->delivery_time) }}" placeholder="e.g. 30-45 mins">
                    </div>
                </div>
            </div>
            <div class="card-footer" style="display:flex;justify-content:flex-end;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
            </div>
        </div>
    </form>

    <div>
        {{-- Logo & Cover --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-title"><div class="card-header-icon" style="background:rgba(139,92,246,0.1);color:var(--purple);"><i class="fa-solid fa-image"></i></div> Images</div>
            </div>
            <div class="card-body">
                <form action="{{ route('vendor.store.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    {{-- Keep other fields with current values to avoid wiping them --}}
                    <input type="hidden" name="name" value="{{ $vendor->name }}">
                    <div class="form-group">
                        <label class="form-label">Logo</label>
                        @if($vendor->logo_url)
                        <img src="{{ $vendor->logo_url }}" style="width:70px;height:70px;border-radius:12px;object-fit:cover;margin-bottom:10px;display:block;border:2px solid var(--border);">
                        @endif
                        <input type="file" name="logo" class="form-control" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cover Image</label>
                        @if($vendor->cover_image_url)
                        <img src="{{ $vendor->cover_image_url }}" style="width:100%;height:80px;border-radius:9px;object-fit:cover;margin-bottom:10px;display:block;border:1px solid var(--border);">
                        @endif
                        <input type="file" name="cover_image" class="form-control" accept="image/*">
                    </div>
                    <button type="submit" class="btn btn-outline btn-sm" style="width:100%;"><i class="fa-solid fa-upload"></i> Upload Images</button>
                </form>
            </div>
        </div>

        {{-- Status --}}
        <div class="card">
            <div class="card-body">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                    <div>
                        <div style="font-weight:700;font-size:14px;">Store Status</div>
                        <div style="font-size:12px;color:var(--text-muted);">Is your store accepting orders?</div>
                    </div>
                    <span class="{{ $vendor->temporarily_closed ? 'store-status closed' : 'store-status open' }}">
                        <span class="store-status-dot"></span>
                        {{ $vendor->temporarily_closed ? 'Closed' : 'Open' }}
                    </span>
                </div>
                <div style="font-size:12.5px;color:var(--text-muted);">
                    Module: <strong>{{ $vendor->module?->name ?? ucfirst($vendor->module_slug ?? '—') }}</strong><br>
                    Rating: <strong>{{ round($vendor->rating??0,1) }} ⭐</strong> ({{ $vendor->review_count ?? 0 }} reviews)
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Schedule --}}
<div class="card">
    <div class="card-header">
        <div class="card-header-title"><div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);"><i class="fa-solid fa-calendar-week"></i></div> Working Hours</div>
    </div>
    <form action="{{ route('vendor.store.schedule') }}" method="POST">
        @csrf
        <div class="card-body">
            @php
            $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            @endphp
            <div style="display:flex;flex-direction:column;gap:12px;">
                @foreach($days as $dayNum => $dayName)
                @php $sch = $schedules->get($dayNum); @endphp
                <div style="display:flex;align-items:center;gap:16px;padding:12px 14px;border:1.5px solid var(--border);border-radius:10px;">
                    <div style="width:100px;font-weight:700;font-size:13.5px;">{{ $dayName }}</div>
                    <label class="toggle">
                        <input type="checkbox" name="schedule[{{ $dayNum }}][is_open]" value="1" {{ $sch?->is_open ? 'checked' : '' }}>
                        <span class="toggle-slider"></span>
                    </label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="time" name="schedule[{{ $dayNum }}][open_time]" class="filter-input" value="{{ $sch ? substr($sch->open_time, 0, 5) : '08:00' }}" style="width:130px;">
                        <span style="color:var(--text-muted);">–</span>
                        <input type="time" name="schedule[{{ $dayNum }}][close_time]" class="filter-input" value="{{ $sch ? substr($sch->close_time, 0, 5) : '22:00' }}" style="width:130px;">
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        <div class="card-footer" style="display:flex;justify-content:flex-end;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Schedule</button>
        </div>
    </form>
</div>
@push('scripts')
{{-- Leaflet.js — open-source map, no API key needed --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function() {
    const DEFAULT_LAT = 9.5594, DEFAULT_LNG = 44.0650; // Hargeysa center
    const initLat = parseFloat(document.getElementById('map_lat').value) || DEFAULT_LAT;
    const initLng = parseFloat(document.getElementById('map_lng').value) || DEFAULT_LNG;
    const hasPin  = !!document.getElementById('map_lat').value;

    const map = L.map('vendor_map').setView([initLat, initLng], hasPin ? 15 : 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Custom marker icon
    const icon = L.divIcon({
        html: '<div style="width:34px;height:34px;background:var(--brand,#f97316);border:3px solid #fff;border-radius:50% 50% 50% 0;transform:rotate(-45deg);box-shadow:0 3px 12px rgba(249,115,22,.5);"></div>',
        iconSize:[34,34], iconAnchor:[17,34], className:''
    });

    let marker = null;

    function setMarker(lat, lng) {
        if (marker) map.removeLayer(marker);
        marker = L.marker([lat, lng], { icon, draggable: true }).addTo(map);
        document.getElementById('map_lat').value = lat.toFixed(6);
        document.getElementById('map_lng').value = lng.toFixed(6);
        marker.on('dragend', function(e) {
            const p = e.target.getLatLng();
            document.getElementById('map_lat').value = p.lat.toFixed(6);
            document.getElementById('map_lng').value = p.lng.toFixed(6);
        });
    }

    if (hasPin) setMarker(initLat, initLng);

    map.on('click', function(e) {
        setMarker(e.latlng.lat, e.latlng.lng);
        map.setView(e.latlng, Math.max(map.getZoom(), 16));
    });

    // Sync manual lat/lng inputs → move map
    ['map_lat','map_lng'].forEach(id => {
        document.getElementById(id).addEventListener('change', function() {
            const lat = parseFloat(document.getElementById('map_lat').value);
            const lng = parseFloat(document.getElementById('map_lng').value);
            if (!isNaN(lat) && !isNaN(lng)) {
                map.setView([lat, lng], 16);
                setMarker(lat, lng);
            }
        });
    });

    // Detect current location
    document.getElementById('detect_location_btn').addEventListener('click', function() {
        this.disabled = true;
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Detecting...';
        const btn = this;
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser.');
            btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-crosshairs"></i> My Location';
            return;
        }
        navigator.geolocation.getCurrentPosition(function(pos) {
            const lat = pos.coords.latitude, lng = pos.coords.longitude;
            map.setView([lat, lng], 17);
            setMarker(lat, lng);
            btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-crosshairs"></i> My Location';
        }, function() {
            alert('Could not get your location. Please allow location access or enter coordinates manually.');
            btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-crosshairs"></i> My Location';
        });
    });
})();
</script>
@endpush
@endsection
