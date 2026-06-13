@extends('admin.layouts.app')
@section('title', 'Settings')
@section('content')

@php
    $flat = $settings->flatten()->keyBy('key');
    $tz   = $flat->get('timezone')?->value ?? 'Africa/Nairobi';
    $allTimezones = DateTimeZone::listIdentifiers();
    // Group timezones by continent
    $tzGroups = [];
    foreach ($allTimezones as $tzId) {
        $parts = explode('/', $tzId, 2);
        $continent = $parts[0];
        $tzGroups[$continent][] = $tzId;
    }
    // Maps & Firebase values
    $mapsApiKey      = $flat->get('google_maps_api_key')?->value      ?? '';
    $mapsLat         = $flat->get('google_maps_default_lat')?->value   ?? '2.0469';
    $mapsLng         = $flat->get('google_maps_default_lng')?->value   ?? '45.3182';
    $mapsZoom        = $flat->get('google_maps_default_zoom')?->value  ?? '13';
    $fbProjectId     = $flat->get('firebase_project_id')?->value       ?? '';
    $fbApiKey        = $flat->get('firebase_api_key')?->value          ?? '';
    $fbAuthDomain    = $flat->get('firebase_auth_domain')?->value      ?? '';
    $fbStorageBucket = $flat->get('firebase_storage_bucket')?->value   ?? '';
    $fbSenderId      = $flat->get('firebase_sender_id')?->value        ?? '';
    $fbAppIdWeb      = $flat->get('firebase_app_id_web')?->value       ?? '';
    $fcmEnabled      = $flat->get('fcm_enabled')?->value               ?? '1';
    $fcmOrders       = $flat->get('fcm_order_notifications')?->value   ?? '1';
    $fbJsonStored    = $flat->get('firebase_service_account_json')?->value ?? '';
@endphp

<style>
/* ─── Settings page styles ─────────────────────────────────────────── */
.settings-hero {
    background: linear-gradient(135deg, #140465 0%, #2d1478 60%, #4a2080 100%);
    border-radius: 16px;
    padding: 28px 32px;
    margin-bottom: 28px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 20px;
}
.settings-hero-icon {
    width: 60px; height: 60px;
    background: rgba(255,255,255,0.15);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 26px;
    flex-shrink: 0;
}
.settings-hero h1 { margin:0; font-size:22px; font-weight:700; }
.settings-hero p  { margin:4px 0 0; opacity:.75; font-size:14px; }
.settings-hero-actions { margin-left:auto; }
.settings-hero-actions .btn {
    background: rgba(255,255,255,0.2);
    color:#fff; border:1px solid rgba(255,255,255,0.35);
    padding: 10px 24px; border-radius:10px; font-weight:600;
    cursor:pointer; transition:background .2s;
    display:flex; align-items:center; gap:8px;
}
.settings-hero-actions .btn:hover { background: rgba(255,255,255,0.3); }

/* Section cards */
.settings-section {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e8ecf2;
    margin-bottom: 20px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,.04);
}
.settings-section-header {
    display: flex; align-items: center; gap: 14px;
    padding: 18px 24px;
    border-bottom: 1px solid #f0f2f6;
    background: #fafbfd;
}
.settings-section-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; flex-shrink: 0;
}
.settings-section-title { font-size:15px; font-weight:700; color:#1a1d2e; margin:0; }
.settings-section-sub   { font-size:12px; color:#8892a4; margin-top:2px; }
.settings-section-body  { padding: 24px; }

/* Form rows */
.setting-row {
    display: flex; align-items: flex-start; gap: 20px;
    padding: 16px 0;
    border-bottom: 1px solid #f4f5f8;
}
.setting-row:last-child { border-bottom: none; padding-bottom: 0; }
.setting-row:first-child { padding-top: 0; }
.setting-label-col { flex: 0 0 220px; }
.setting-label { font-size: 13px; font-weight: 600; color: #2d3748; margin-bottom: 3px; }
.setting-hint  { font-size: 12px; color: #94a3b8; }
.setting-input-col { flex: 1; }

/* Inputs */
.setting-input {
    width: 100%; padding: 10px 14px;
    border: 1.5px solid #e2e8f0; border-radius: 9px;
    font-size: 14px; color: #1a1d2e; background: #fff;
    transition: border-color .2s, box-shadow .2s;
    box-sizing: border-box;
}
.setting-input:focus {
    outline: none; border-color: #140465;
    box-shadow: 0 0 0 3px rgba(20,4,101,.08);
}
select.setting-input { cursor: pointer; }

/* Password toggle */
.input-with-toggle { position: relative; }
.input-with-toggle .setting-input { padding-right: 44px; }
.pwd-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%);
    background:none; border:none; color:#94a3b8; cursor:pointer; padding:4px; }
.pwd-toggle:hover { color:#140465; }

/* Badge */
.tz-badge {
    display:inline-flex; align-items:center; gap:6px;
    background: rgba(20,4,101,.08); color:#140465;
    border-radius:6px; padding:4px 10px; font-size:12px; font-weight:600;
    margin-top: 8px;
}
/* Alert */
.settings-alert {
    background: rgba(20,4,101,.06);
    border-left: 4px solid #140465;
    border-radius: 8px; padding: 14px 18px;
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 24px; font-size: 14px; color: #2d3748;
}
/* Logo preview */
.logo-preview-wrap { display:flex; align-items:center; gap:12px; margin-top:8px; }
.logo-preview { height:44px; border-radius:8px; border:1px solid #e2e8f0; padding:4px; background:#f8fafc; }

/* Responsive */
@media (max-width: 640px) {
    .setting-row { flex-direction: column; gap: 8px; }
    .setting-label-col { flex: none; width: 100%; }
}

/* API key input with copy button */
.input-with-action { position: relative; }
.input-with-action .setting-input { padding-right: 44px; }
.copy-btn {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px;
    transition: color .15s;
}
.copy-btn:hover { color: #140465; }

/* Status badges */
.status-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600;
}
.status-badge.ok  { background: rgba(16,185,129,.1); color: #059669; }
.status-badge.err { background: rgba(239,68,68,.1);  color: #dc2626; }
.status-badge.warn{ background: rgba(245,158,11,.1); color: #d97706; }

/* Toggle switch */
.toggle-wrap { display: flex; align-items: center; gap: 10px; }
.toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute; cursor: pointer; inset: 0;
    background: #cbd5e1; border-radius: 24px; transition: .2s;
}
.toggle-slider:before {
    content: ''; position: absolute;
    width: 18px; height: 18px; left: 3px; bottom: 3px;
    background: #fff; border-radius: 50%; transition: .2s;
}
.toggle-switch input:checked + .toggle-slider { background: #140465; }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }
.toggle-label { font-size: 13px; color: #64748b; }

/* JSON textarea */
.json-textarea {
    width: 100%; min-height: 180px;
    padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 9px;
    font-size: 12px; font-family: 'Courier New', monospace; color: #1a1d2e;
    background: #f8fafc; resize: vertical; box-sizing: border-box;
    transition: border-color .2s;
}
.json-textarea:focus { outline: none; border-color: #140465; background: #fff; }

/* Test button */
.btn-test {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 9px 18px; border-radius: 9px; font-size: 13px; font-weight: 600;
    cursor: pointer; border: 1.5px solid; transition: all .2s;
}
.btn-test-maps { background: rgba(66,133,244,.08); color: #4285F4; border-color: #4285F4; }
.btn-test-maps:hover { background: rgba(66,133,244,.15); }
.btn-test-firebase { background: rgba(255,160,0,.08); color: #FFA000; border-color: #FFA000; }
.btn-test-firebase:hover { background: rgba(255,160,0,.15); }

/* Map preview */
#mapPreview {
    width:100%; height:200px; border-radius:10px; border:1px solid #e2e8f0;
    overflow:hidden; margin-top:10px; display:none;
}
</style>

<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" id="settingsForm">
@csrf

{{-- Hero Header --}}
<div class="settings-hero">
    <div class="settings-hero-icon"><i class="fas fa-sliders-h"></i></div>
    <div>
        <h1>System Settings</h1>
        <p>Configure your application, timezone, payments and delivery options</p>
    </div>
    <div class="settings-hero-actions">
        <button type="submit" class="btn">
            <i class="fas fa-save"></i> Save All Settings
        </button>
    </div>
</div>

@if(session('success'))
<div class="settings-alert">
    <i class="fas fa-check-circle" style="color:#10b981;font-size:18px;"></i>
    {{ session('success') }}
</div>
@endif

{{-- ── 1. General / App Identity ─────────────────────────────────── --}}
<div class="settings-section">
    <div class="settings-section-header">
        <div class="settings-section-icon" style="background:rgba(20,4,101,.08);color:#140465;">
            <i class="fas fa-mobile-alt"></i>
        </div>
        <div>
            <div class="settings-section-title">App Identity</div>
            <div class="settings-section-sub">Application name, logo and contact info</div>
        </div>
    </div>
    <div class="settings-section-body">
        @foreach($settings->get('general', collect()) as $s)
        @if($s->key === 'timezone') @continue @endif {{-- shown in System & Timezone section --}}
        @php
            $label = ucwords(str_replace('_', ' ', str_replace('app_', '', $s->key)));
            $isFile = str_contains($s->key,'logo') || str_contains($s->key,'image');
            $isPass = str_contains($s->key,'secret') || str_contains($s->key,'password');
        @endphp
        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">{{ $label }}</div>
                <div class="setting-hint">{{ $s->key }}</div>
            </div>
            <div class="setting-input-col">
                @if($isFile)
                    <input type="file" name="{{ $s->key }}" class="setting-input" accept="image/*">
                    @if($s->value)
                    <div class="logo-preview-wrap">
                        <img src="{{ asset('storage/'.$s->value) }}" class="logo-preview">
                        <span style="font-size:12px;color:#94a3b8;">Current logo</span>
                    </div>
                    @endif
                @else
                    <input type="text" name="{{ $s->key }}" class="setting-input" value="{{ $s->value }}">
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- ── 2. System / Timezone ──────────────────────────────────────── --}}
<div class="settings-section">
    <div class="settings-section-header">
        <div class="settings-section-icon" style="background:rgba(20,4,101,.08);color:#140465;">
            <i class="fas fa-globe"></i>
        </div>
        <div>
            <div class="settings-section-title">System & Timezone</div>
            <div class="settings-section-sub">Controls working hours, item availability schedules and all time comparisons</div>
        </div>
    </div>
    <div class="settings-section-body">
        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">Application Timezone</div>
                <div class="setting-hint">Used for restaurant open/close and item availability windows</div>
            </div>
            <div class="setting-input-col">
                <select name="timezone" class="setting-input" id="tzSelect">
                    @foreach($tzGroups as $continent => $tzList)
                    <optgroup label="{{ $continent }}">
                        @foreach($tzList as $tzId)
                        <option value="{{ $tzId }}" {{ $tzId === $tz ? 'selected' : '' }}>
                            {{ str_replace('_', ' ', $tzId) }}
                        </option>
                        @endforeach
                    </optgroup>
                    @endforeach
                </select>
                <div class="tz-badge" id="tzNow">
                    <i class="fas fa-clock"></i>
                    <span>Current time in <strong>{{ $tz }}</strong>:
                    {{ \Carbon\Carbon::now($tz)->format('D, d M Y  H:i:s') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── 3. Commission ─────────────────────────────────────────────── --}}
@if($settings->get('commission', collect())->isNotEmpty())
<div class="settings-section">
    <div class="settings-section-header">
        <div class="settings-section-icon" style="background:rgba(16,185,129,.1);color:#10b981;">
            <i class="fas fa-percentage"></i>
        </div>
        <div>
            <div class="settings-section-title">Commission Settings</div>
            <div class="settings-section-sub">Platform commission rates for restaurants and drivers</div>
        </div>
    </div>
    <div class="settings-section-body">
        @foreach($settings->get('commission', collect()) as $s)
        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">{{ ucwords(str_replace('_',' ',$s->key)) }}</div>
                <div class="setting-hint">{{ $s->key }}</div>
            </div>
            <div class="setting-input-col">
                <input type="text" name="{{ $s->key }}" class="setting-input" value="{{ $s->value }}">
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── 4. Delivery ───────────────────────────────────────────────── --}}
@if($settings->get('delivery', collect())->isNotEmpty())
<div class="settings-section">
    <div class="settings-section-header">
        <div class="settings-section-icon" style="background:rgba(20,184,166,.1);color:#14b8a6;">
            <i class="fas fa-motorcycle"></i>
        </div>
        <div>
            <div class="settings-section-title">Delivery Settings</div>
            <div class="settings-section-sub">Default delivery fees and radius</div>
        </div>
    </div>
    <div class="settings-section-body">
        @foreach($settings->get('delivery', collect()) as $s)
        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">{{ ucwords(str_replace('_',' ',$s->key)) }}</div>
                <div class="setting-hint">{{ $s->key }}</div>
            </div>
            <div class="setting-input-col">
                <input type="text" name="{{ $s->key }}" class="setting-input" value="{{ $s->value }}">
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── 5. Payment ────────────────────────────────────────────────── --}}
@if($settings->get('payment', collect())->isNotEmpty())
<div class="settings-section">
    <div class="settings-section-header">
        <div class="settings-section-icon" style="background:rgba(239,68,68,.1);color:#ef4444;">
            <i class="fas fa-credit-card"></i>
        </div>
        <div>
            <div class="settings-section-title">Payment Settings</div>
            <div class="settings-section-sub">API keys, secrets and payment gateway configuration</div>
        </div>
    </div>
    <div class="settings-section-body">
        @foreach($settings->get('payment', collect()) as $s)
        @php $isPass = str_contains($s->key,'key')||str_contains($s->key,'secret')||str_contains($s->key,'password'); @endphp
        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">{{ ucwords(str_replace('_',' ',$s->key)) }}</div>
                <div class="setting-hint">{{ $s->key }}</div>
            </div>
            <div class="setting-input-col">
                @if($isPass)
                <div class="input-with-toggle">
                    <input type="password" name="{{ $s->key }}" class="setting-input" value="{{ $s->value }}" id="pass_{{ $loop->index }}">
                    <button type="button" class="pwd-toggle" onclick="togglePass('pass_{{ $loop->index }}',this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                @else
                <input type="text" name="{{ $s->key }}" class="setting-input" value="{{ $s->value }}">
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── 6. Loyalty & Referral ────────────────────────────────────── --}}
@php $loyaltyGroup = $settings->get('loyalty', collect())->merge($settings->get('referral', collect())); @endphp
@if($loyaltyGroup->isNotEmpty())
<div class="settings-section">
    <div class="settings-section-header">
        <div class="settings-section-icon" style="background:rgba(245,158,11,.1);color:#f59e0b;">
            <i class="fas fa-gift"></i>
        </div>
        <div>
            <div class="settings-section-title">Loyalty & Referral</div>
            <div class="settings-section-sub">Points, rewards and referral bonus configuration</div>
        </div>
    </div>
    <div class="settings-section-body">
        @foreach($loyaltyGroup as $s)
        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">{{ ucwords(str_replace('_',' ',$s->key)) }}</div>
                <div class="setting-hint">{{ $s->key }}</div>
            </div>
            <div class="setting-input-col">
                <input type="text" name="{{ $s->key }}" class="setting-input" value="{{ $s->value }}">
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── 7. Google Maps ────────────────────────────────────────────── --}}
<div class="settings-section" id="section-maps">
    <div class="settings-section-header">
        <div class="settings-section-icon" style="background:rgba(66,133,244,.1);color:#4285F4;">
            <i class="fas fa-map-marked-alt"></i>
        </div>
        <div>
            <div class="settings-section-title">Google Maps</div>
            <div class="settings-section-sub">API key and default map center for the customer app</div>
        </div>
        <div style="margin-left:auto;display:flex;align-items:center;gap:10px;">
            @if($mapsApiKey)
                <span class="status-badge ok"><i class="fas fa-check-circle"></i> API Key Set</span>
            @else
                <span class="status-badge warn"><i class="fas fa-exclamation-circle"></i> API Key Missing</span>
            @endif
        </div>
    </div>
    <div class="settings-section-body">

        {{-- API Key --}}
        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">Maps API Key</div>
                <div class="setting-hint">Used for Android, iOS & geocoding. Enable Maps SDK + Places API</div>
            </div>
            <div class="setting-input-col">
                <div class="input-with-toggle input-with-action">
                    <input type="password" name="google_maps_api_key" id="mapsApiKey"
                        class="setting-input" value="{{ $mapsApiKey }}"
                        placeholder="AIzaSy...">
                    <button type="button" class="pwd-toggle" onclick="togglePass('mapsApiKey',this)" title="Show/hide">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div style="margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <button type="button" class="btn-test btn-test-maps" onclick="testMapsKey()">
                        <i class="fas fa-map"></i> Test Map
                    </button>
                    <a href="https://console.cloud.google.com/google/maps-apis/credentials"
                       target="_blank" style="font-size:12px;color:#4285F4;display:flex;align-items:center;gap:4px;">
                        <i class="fas fa-external-link-alt"></i> Get API Key from Google Cloud Console
                    </a>
                </div>
                <div id="mapPreview"></div>
                <div id="mapsTestResult" style="margin-top:8px;font-size:13px;display:none;"></div>
            </div>
        </div>

        {{-- Default coordinates --}}
        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">Default Map Center</div>
                <div class="setting-hint">Latitude & Longitude shown when user location is unavailable</div>
            </div>
            <div class="setting-input-col">
                <div style="display:grid;grid-template-columns:1fr 1fr 120px;gap:10px;">
                    <div>
                        <label style="font-size:11px;color:#94a3b8;display:block;margin-bottom:4px;">Latitude</label>
                        <input type="text" name="google_maps_default_lat" class="setting-input"
                            value="{{ $mapsLat }}" placeholder="e.g. 2.0469">
                    </div>
                    <div>
                        <label style="font-size:11px;color:#94a3b8;display:block;margin-bottom:4px;">Longitude</label>
                        <input type="text" name="google_maps_default_lng" class="setting-input"
                            value="{{ $mapsLng }}" placeholder="e.g. 45.3182">
                    </div>
                    <div>
                        <label style="font-size:11px;color:#94a3b8;display:block;margin-bottom:4px;">Default Zoom</label>
                        <input type="number" name="google_maps_default_zoom" class="setting-input"
                            value="{{ $mapsZoom }}" min="1" max="20" placeholder="13">
                    </div>
                </div>
                <div style="margin-top:8px;">
                    <a href="https://www.latlong.net/" target="_blank"
                       style="font-size:12px;color:#4285F4;display:inline-flex;align-items:center;gap:4px;">
                        <i class="fas fa-crosshairs"></i> Find coordinates on latlong.net
                    </a>
                </div>
            </div>
        </div>

        {{-- How to get Maps key --}}
        <div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 18px;margin-top:4px;">
            <div style="font-size:13px;font-weight:700;color:#1d4ed8;margin-bottom:8px;">
                <i class="fas fa-info-circle"></i> How to get your Google Maps API Key
            </div>
            <ol style="margin:0;padding-left:18px;font-size:12px;color:#1e40af;line-height:1.8;">
                <li>Go to <a href="https://console.cloud.google.com" target="_blank" style="color:#2563eb;">console.cloud.google.com</a></li>
                <li>Create a project or select an existing one</li>
                <li>Go to <strong>APIs & Services → Library</strong></li>
                <li>Enable: <strong>Maps SDK for Android</strong>, <strong>Maps SDK for iOS</strong>, <strong>Geocoding API</strong>, <strong>Places API</strong></li>
                <li>Go to <strong>APIs & Services → Credentials → Create API Key</strong></li>
                <li>Paste the key above and save</li>
            </ol>
        </div>
    </div>
</div>

{{-- ── 8. Firebase / Push Notifications ─────────────────────────── --}}
<div class="settings-section" id="section-firebase">
    <div class="settings-section-header">
        <div class="settings-section-icon" style="background:rgba(255,160,0,.1);color:#FFA000;">
            <i class="fas fa-bell"></i>
        </div>
        <div>
            <div class="settings-section-title">Firebase & Push Notifications</div>
            <div class="settings-section-sub">FCM configuration for order status push notifications to the customer app</div>
        </div>
        <div style="margin-left:auto;display:flex;align-items:center;gap:10px;">
            @if($firebaseFileExists && $fbProjectId)
                <span class="status-badge ok"><i class="fas fa-check-circle"></i> Configured</span>
            @elseif($fbProjectId || $firebaseFileExists)
                <span class="status-badge warn"><i class="fas fa-exclamation-circle"></i> Incomplete</span>
            @else
                <span class="status-badge err"><i class="fas fa-times-circle"></i> Not Configured</span>
            @endif
        </div>
    </div>
    <div class="settings-section-body">

        {{-- Enable toggle --}}
        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">Enable Push Notifications</div>
                <div class="setting-hint">Send FCM push notifications to customer devices</div>
            </div>
            <div class="setting-input-col">
                <div class="toggle-wrap">
                    <label class="toggle-switch">
                        <input type="hidden" name="fcm_enabled" value="0">
                        <input type="checkbox" name="fcm_enabled" value="1" {{ $fcmEnabled == '1' ? 'checked' : '' }}>
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="toggle-label">Send notifications when order status changes</span>
                </div>
            </div>
        </div>

        {{-- Order notifications toggle --}}
        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">Order Status Notifications</div>
                <div class="setting-hint">Notify customer at every order step (confirmed, preparing, on the way, delivered)</div>
            </div>
            <div class="setting-input-col">
                <div class="toggle-wrap">
                    <label class="toggle-switch">
                        <input type="hidden" name="fcm_order_notifications" value="0">
                        <input type="checkbox" name="fcm_order_notifications" value="1" {{ $fcmOrders == '1' ? 'checked' : '' }}>
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="toggle-label">Enabled for all order modules</span>
                </div>
            </div>
        </div>

        {{-- Firebase Web Config fields --}}
        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">Firebase Project ID</div>
                <div class="setting-hint">Project Settings → General → Project ID</div>
            </div>
            <div class="setting-input-col">
                <div class="input-with-action">
                    <input type="text" name="firebase_project_id" class="setting-input"
                        value="{{ $fbProjectId }}" id="fbProjectId"
                        placeholder="e.g. esahlan-817dc">
                    <button type="button" class="copy-btn" onclick="copyText('fbProjectId')" title="Copy">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                @if($fbProjectId)
                <div class="tz-badge" style="margin-top:8px;">
                    <i class="fas fa-fire"></i>
                    <span>Project: <strong>{{ $fbProjectId }}</strong></span>
                </div>
                @endif
            </div>
        </div>

        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">API Key</div>
                <div class="setting-hint">Web API key — used for client-side Firebase SDK</div>
            </div>
            <div class="setting-input-col">
                <div class="input-with-toggle">
                    <input type="password" name="firebase_api_key" id="fbApiKey"
                        class="setting-input" value="{{ $fbApiKey }}"
                        placeholder="AIzaSy...">
                    <button type="button" class="pwd-toggle" onclick="togglePass('fbApiKey',this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">Messaging Sender ID</div>
                <div class="setting-hint">Cloud Messaging → Sender ID</div>
            </div>
            <div class="setting-input-col">
                <input type="text" name="firebase_sender_id" class="setting-input"
                    value="{{ $fbSenderId }}" placeholder="e.g. 30724696826">
            </div>
        </div>

        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">Web App ID</div>
                <div class="setting-hint">Your Web app ID from Project Settings → General</div>
            </div>
            <div class="setting-input-col">
                <input type="text" name="firebase_app_id_web" class="setting-input"
                    value="{{ $fbAppIdWeb }}" placeholder="1:XXXXX:web:XXXXX">
            </div>
        </div>

        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">Auth Domain</div>
                <div class="setting-hint">Auto-generated: project-id.firebaseapp.com</div>
            </div>
            <div class="setting-input-col">
                <input type="text" name="firebase_auth_domain" class="setting-input"
                    value="{{ $fbAuthDomain }}" placeholder="esahlan-817dc.firebaseapp.com">
            </div>
        </div>

        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">Storage Bucket</div>
                <div class="setting-hint">Auto-generated: project-id.firebasestorage.app</div>
            </div>
            <div class="setting-input-col">
                <input type="text" name="firebase_storage_bucket" class="setting-input"
                    value="{{ $fbStorageBucket }}" placeholder="esahlan-817dc.firebasestorage.app">
            </div>
        </div>

        {{-- Service Account JSON --}}
        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">Service Account JSON</div>
                <div class="setting-hint">
                    Paste the content of your <code style="font-size:11px;background:#f1f5f9;padding:1px 4px;border-radius:3px;">firebase-service-account.json</code> here.<br>
                    Used for sending push notifications from the server.
                </div>
            </div>
            <div class="setting-input-col">
                @if($firebaseFileExists)
                <div class="status-badge ok" style="margin-bottom:10px;">
                    <i class="fas fa-file-check"></i> Service account file is saved on server
                </div>
                @endif
                <textarea name="firebase_service_account_json"
                    class="json-textarea"
                    placeholder='Paste your Firebase service account JSON here:
{
  "type": "service_account",
  "project_id": "your-project-id",
  "private_key_id": "...",
  "private_key": "-----BEGIN RSA PRIVATE KEY-----\n...",
  "client_email": "firebase-adminsdk-...@your-project.iam.gserviceaccount.com",
  ...
}'>{{ $fbJsonStored }}</textarea>
                <div style="margin-top:8px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                    <span style="font-size:12px;color:#94a3b8;">
                        <i class="fas fa-shield-alt"></i> This JSON is stored securely on the server and never exposed via API
                    </span>
                    <button type="button" class="btn-test btn-test-firebase" onclick="validateJson()">
                        <i class="fas fa-check-double"></i> Validate JSON
                    </button>
                </div>
                <div id="jsonValidResult" style="margin-top:8px;font-size:13px;display:none;"></div>
            </div>
        </div>

        {{-- Test notification --}}
        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">Test Notification</div>
                <div class="setting-hint">Send a test push to a specific FCM device token to verify the connection</div>
            </div>
            <div class="setting-input-col">
                <div style="display:flex;gap:10px;">
                    <input type="text" id="testFcmToken" class="setting-input"
                        placeholder="Paste FCM device token here…" style="flex:1;">
                    <button type="button" class="btn-test btn-test-firebase" onclick="sendTestNotification()">
                        <i class="fas fa-paper-plane"></i> Send Test
                    </button>
                </div>
                <div id="fcmTestResult" style="margin-top:8px;font-size:13px;display:none;"></div>
            </div>
        </div>

        {{-- How to setup Firebase --}}
        <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:14px 18px;margin-top:4px;">
            <div style="font-size:13px;font-weight:700;color:#92400e;margin-bottom:8px;">
                <i class="fas fa-fire-alt"></i> How to setup Firebase
            </div>
            <ol style="margin:0;padding-left:18px;font-size:12px;color:#78350f;line-height:1.9;">
                <li>Go to <a href="https://console.firebase.google.com" target="_blank" style="color:#b45309;">console.firebase.google.com</a> → Create/Select project</li>
                <li>In <strong>Project Settings → General</strong>, copy the <strong>Project ID</strong> and paste above</li>
                <li>Go to <strong>Project Settings → Service Accounts</strong></li>
                <li>Click <strong>"Generate new private key"</strong> → Download the JSON file</li>
                <li>Open the downloaded JSON file, copy all contents, paste above in the textarea</li>
                <li>In <strong>Project Settings → General</strong>, add an Android app with package: <code style="font-size:11px;background:#fef3c7;padding:1px 4px;border-radius:3px;">com.esahlan.esahlan_customer</code></li>
                <li>Download <strong>google-services.json</strong> → place in <code style="font-size:11px;background:#fef3c7;padding:1px 4px;border-radius:3px;">customer_app/android/app/</code></li>
                <li>Save settings and click <strong>Send Test</strong> to verify</li>
            </ol>
        </div>
    </div>
</div>

{{-- ── Other ungrouped settings ─────────────────────────────────── --}}
@php
    $knownGroups = ['general','commission','delivery','payment','loyalty','referral','maps','firebase'];
    $otherSettings = $settings->filter(fn($group, $key) => !in_array($key, $knownGroups))->flatten();
@endphp
@if($otherSettings->isNotEmpty())
<div class="settings-section">
    <div class="settings-section-header">
        <div class="settings-section-icon" style="background:#f4f5f8;color:#64748b;">
            <i class="fas fa-cog"></i>
        </div>
        <div>
            <div class="settings-section-title">Other Settings</div>
            <div class="settings-section-sub">Additional configuration options</div>
        </div>
    </div>
    <div class="settings-section-body">
        @foreach($otherSettings as $s)
        @if($s->key !== 'timezone')  {{-- timezone already shown above --}}
        <div class="setting-row">
            <div class="setting-label-col">
                <div class="setting-label">{{ ucwords(str_replace('_',' ',$s->key)) }}</div>
                <div class="setting-hint">{{ $s->key }}</div>
            </div>
            <div class="setting-input-col">
                <input type="text" name="{{ $s->key }}" class="setting-input" value="{{ $s->value }}">
            </div>
        </div>
        @endif
        @endforeach
    </div>
</div>
@endif

<div style="display:flex;justify-content:flex-end;gap:12px;margin-bottom:32px;">
    <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
        <i class="fas fa-times"></i> Cancel
    </a>
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save"></i> Save All Settings
    </button>
</div>

</form>

<script>
function togglePass(id, btn) {
    const inp = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        inp.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// Update timezone badge when dropdown changes
document.getElementById('tzSelect')?.addEventListener('change', function () {
    const badge = document.getElementById('tzNow');
    const sel = this.value;
    badge.innerHTML = `<i class="fas fa-clock"></i> <span>Selected: <strong>${sel.replace(/_/g,' ')}</strong> — Save to apply</span>`;
});

// ── Copy text to clipboard ────────────────────────────────────────────────
function copyText(inputId) {
    const el = document.getElementById(inputId);
    navigator.clipboard.writeText(el.value).then(() => {
        showToast('Copied to clipboard!');
    });
}

function showToast(msg) {
    const t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#140465;color:#fff;padding:10px 20px;border-radius:10px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.2);';
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2500);
}

// ── Test Google Maps key ─────────────────────────────────────────────────
function testMapsKey() {
    const key = document.getElementById('mapsApiKey').value;
    if (!key) {
        showResult('mapsTestResult', false, 'Please enter your Google Maps API key first.');
        return;
    }
    const preview = document.getElementById('mapPreview');
    const lat = document.querySelector('[name="google_maps_default_lat"]').value || '2.0469';
    const lng = document.querySelector('[name="google_maps_default_lng"]').value || '45.3182';
    const zoom = document.querySelector('[name="google_maps_default_zoom"]').value || '13';

    // Use Google Static Maps API to test the key
    preview.style.display = 'block';
    preview.innerHTML = `<img src="https://maps.googleapis.com/maps/api/staticmap?center=${lat},${lng}&zoom=${zoom}&size=600x200&maptype=roadmap&markers=color:red|${lat},${lng}&key=${key}"
        style="width:100%;height:200px;object-fit:cover;border-radius:10px;"
        onload="showResult('mapsTestResult', true, 'API key is valid! Map loaded successfully.')"
        onerror="showResult('mapsTestResult', false, 'Invalid API key or Maps SDK not enabled.')">`;
}

// ── Validate Firebase JSON ────────────────────────────────────────────────
function validateJson() {
    const textarea = document.querySelector('[name="firebase_service_account_json"]');
    const val = textarea.value.trim();
    if (!val) {
        showResult('jsonValidResult', false, 'Paste your service account JSON first.');
        return;
    }
    try {
        const parsed = JSON.parse(val);
        const required = ['type','project_id','private_key','client_email'];
        const missing = required.filter(k => !parsed[k]);
        if (missing.length > 0) {
            showResult('jsonValidResult', false, `JSON is valid but missing fields: ${missing.join(', ')}`);
        } else {
            showResult('jsonValidResult', true, `✅ Valid service account JSON for project: <strong>${parsed.project_id}</strong> — Ready to save!`);
        }
    } catch(e) {
        showResult('jsonValidResult', false, 'Invalid JSON: ' + e.message);
    }
}

// ── Send test FCM notification ────────────────────────────────────────────
function sendTestNotification() {
    const token = document.getElementById('testFcmToken').value.trim();
    if (!token) {
        showResult('fcmTestResult', false, 'Paste an FCM device token first. You can find it in the app logs.');
        return;
    }
    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';

    fetch('{{ route("admin.settings.test-firebase") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ test_token: token })
    })
    .then(r => r.json())
    .then(data => {
        showResult('fcmTestResult', data.success, data.message);
    })
    .catch(() => {
        showResult('fcmTestResult', false, 'Network error. Check server logs.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Test';
    });
}

// ── Result helper ─────────────────────────────────────────────────────────
function showResult(id, success, message) {
    const el = document.getElementById(id);
    el.style.display = 'block';
    el.innerHTML = `<div class="status-badge ${success ? 'ok' : 'err'}" style="display:inline-flex;padding:8px 14px;">
        <i class="fas fa-${success ? 'check-circle' : 'times-circle'}"></i>
        <span>${message}</span>
    </div>`;
}

// ── Scroll to section from URL hash ──────────────────────────────────────
if (window.location.hash) {
    const el = document.querySelector(window.location.hash);
    if (el) setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 300);
}
</script>
@endsection
