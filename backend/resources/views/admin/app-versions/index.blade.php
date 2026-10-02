@extends('admin.layouts.app')
@section('title', 'App Version Control')

@push('styles')
<style>
.av-hero {
    background: linear-gradient(135deg, #0c0148 0%, #1a0570 50%, #0c0148 100%);
    border-radius: 20px;
    padding: 28px 32px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.av-hero::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: rgba(255,138,0,0.1);
}
.av-hero-title { font-size: 22px; font-weight: 800; color: #fff; }
.av-hero-sub   { font-size: 13px; color: rgba(255,255,255,0.6); margin-top: 4px; }

.app-card {
    background: var(--surface, #fff);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 20px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    transition: box-shadow .2s;
}
.app-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.1); }

.app-card-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border, #e5e7eb);
}
.app-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.app-icon.customer { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.app-icon.driver   { background: linear-gradient(135deg, #f97316, #ea580c); }
.app-icon.vendor   { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }

.app-name  { font-size: 16px; font-weight: 700; color: var(--text, #111); }
.app-label { font-size: 12px; color: var(--text-muted, #6b7280); margin-top: 1px; }

.force-badge {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 8px;
}
.badge-on  { background:#dcfce7; color:#166534; padding:4px 12px; border-radius:999px; font-size:12px; font-weight:700; }
.badge-off { background:#f3f4f6; color:#6b7280; padding:4px 12px; border-radius:999px; font-size:12px; font-weight:600; }

.app-card-body { padding: 24px; }

.form-row   { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: 12px; font-weight: 600; color: var(--text-muted, #6b7280); text-transform: uppercase; letter-spacing: .04em; }
.form-input {
    padding: 10px 14px;
    border: 1.5px solid var(--border, #e5e7eb);
    border-radius: 10px;
    font-size: 14px;
    color: var(--text, #111);
    background: var(--bg, #f9fafb);
    transition: border-color .2s;
    width: 100%;
}
.form-input:focus { outline: none; border-color: #FF8A00; background: var(--surface, #fff); }

.force-toggle-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    background: var(--bg, #f9fafb);
    border-radius: 12px;
    margin-bottom: 16px;
    border: 1.5px solid var(--border, #e5e7eb);
}
.toggle-switch {
    position: relative;
    width: 48px; height: 26px;
    flex-shrink: 0;
}
.toggle-switch input { display: none; }
.toggle-track {
    position: absolute; inset: 0;
    background: #d1d5db;
    border-radius: 13px;
    cursor: pointer;
    transition: background .2s;
}
.toggle-track::after {
    content: '';
    position: absolute;
    top: 3px; left: 3px;
    width: 20px; height: 20px;
    background: #fff;
    border-radius: 50%;
    transition: transform .2s;
    box-shadow: 0 1px 4px rgba(0,0,0,.2);
}
.toggle-switch input:checked + .toggle-track { background: #22c55e; }
.toggle-switch input:checked + .toggle-track::after { transform: translateX(22px); }

.toggle-info .toggle-title { font-size: 14px; font-weight: 700; color: var(--text, #111); }
.toggle-info .toggle-sub   { font-size: 12px; color: var(--text-muted, #6b7280); margin-top: 2px; }

.save-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: #FF8A00;
    color: #fff;
    padding: 10px 22px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: background .2s;
}
.save-btn:hover { background: #e07500; }

.alert-success {
    background: #dcfce7;
    border: 1px solid #bbf7d0;
    color: #15803d;
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.store-link-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }

/* Warning box when force update is ON */
.force-warning {
    background: #fef3c7;
    border: 1.5px solid #fcd34d;
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 16px;
    font-size: 13px;
    color: #92400e;
    display: none;
    align-items: center;
    gap: 10px;
}
.force-warning.visible { display: flex; }
</style>
@endpush

@section('content')
<div class="av-hero">
    <div>
        <div class="av-hero-title">📱 App Version Control</div>
        <div class="av-hero-sub">Manage force update settings for Customer, Driver & Vendor apps</div>
    </div>
    <div style="text-align:right; color:rgba(255,255,255,0.5); font-size:12px; position:relative; z-index:1;">
        <div style="font-size:28px;">🔄</div>
        <div style="margin-top:4px;">Force Update Manager</div>
    </div>
</div>

@if(session('success'))
<div class="alert-success">
    <i class="fas fa-check-circle"></i>
    {{ session('success') }}
</div>
@endif

@php
$apps = [
    'customer' => ['label' => 'Customer App', 'icon' => '🛍️', 'class' => 'customer', 'desc' => 'eSahlan shopping & food delivery'],
    'driver'   => ['label' => 'Driver App',   'icon' => '🚴', 'class' => 'driver',   'desc' => 'Delivery driver companion'],
    'vendor'   => ['label' => 'Vendor App',   'icon' => '🏪', 'class' => 'vendor',   'desc' => 'Restaurant & store management'],
];
@endphp

@foreach($apps as $type => $meta)
@php $v = $versions[$type]; @endphp
<div class="app-card">
    <div class="app-card-header">
        <div class="app-icon {{ $meta['class'] }}">{{ $meta['icon'] }}</div>
        <div>
            <div class="app-name">{{ $meta['label'] }}</div>
            <div class="app-label">{{ $meta['desc'] }}</div>
        </div>
        <div class="force-badge">
            @if($v->force_update)
                <span class="badge-on">✓ Force Update ON</span>
            @else
                <span class="badge-off">Force Update OFF</span>
            @endif
            <span style="font-size:12px; color:var(--text-muted,#6b7280);">
                Min: <strong>{{ $v->min_version }}</strong> · Latest: <strong>{{ $v->latest_version }}</strong>
            </span>
        </div>
    </div>

    <div class="app-card-body">
        <form method="POST" action="{{ route('admin.app-versions.update', $type) }}">
            @csrf
            @method('PATCH')

            {{-- Force Update Toggle --}}
            <div class="force-toggle-row">
                <label class="toggle-switch">
                    <input type="checkbox" name="force_update" value="1"
                        {{ $v->force_update ? 'checked' : '' }}
                        onchange="toggleWarning('{{ $type }}', this.checked)">
                    <div class="toggle-track"></div>
                </label>
                <div class="toggle-info">
                    <div class="toggle-title">Force Update</div>
                    <div class="toggle-sub">When ON, users with version below the minimum will be forced to update and cannot use the app</div>
                </div>
            </div>

            {{-- Warning when force update is on --}}
            <div class="force-warning {{ $v->force_update ? 'visible' : '' }}" id="warn-{{ $type }}">
                <i class="fas fa-triangle-exclamation" style="font-size:16px; flex-shrink:0;"></i>
                <span>Force Update is <strong>ACTIVE</strong>. All users with app version below <strong>{{ $v->min_version }}</strong> will see a blocking update screen.</span>
            </div>

            {{-- Version numbers --}}
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Minimum Required Version</label>
                    <input class="form-input" type="text" name="min_version"
                        value="{{ old('min_version', $v->min_version) }}"
                        placeholder="e.g. 1.2.0" pattern="\d+\.\d+\.\d+"
                        title="Semver format: 1.2.3" required>
                    <span style="font-size:11px; color:var(--text-muted,#6b7280);">Users below this version will be forced to update</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Latest Published Version</label>
                    <input class="form-input" type="text" name="latest_version"
                        value="{{ old('latest_version', $v->latest_version) }}"
                        placeholder="e.g. 1.2.0" pattern="\d+\.\d+\.\d+"
                        title="Semver format: 1.2.3" required>
                    <span style="font-size:11px; color:var(--text-muted,#6b7280);">The current version available in the stores</span>
                </div>
            </div>

            {{-- Store URLs --}}
            <div class="store-link-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fab fa-google-play" style="color:#34a853;"></i> Google Play URL
                    </label>
                    <input class="form-input" type="url" name="android_url"
                        value="{{ old('android_url', $v->android_url) }}"
                        placeholder="https://play.google.com/store/apps/details?id=...">
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <i class="fab fa-app-store-ios" style="color:#007aff;"></i> Apple App Store URL
                    </label>
                    <input class="form-input" type="url" name="ios_url"
                        value="{{ old('ios_url', $v->ios_url) }}"
                        placeholder="https://apps.apple.com/app/...">
                </div>
            </div>

            {{-- Update Message --}}
            <div class="form-group" style="margin-bottom:20px;">
                <label class="form-label">Update Message (shown to users)</label>
                <textarea class="form-input" name="update_message" rows="2"
                    placeholder="e.g. A new version is available with important security fixes. Please update to continue.">{{ old('update_message', $v->update_message) }}</textarea>
            </div>

            <button type="submit" class="save-btn">
                <i class="fas fa-save"></i> Save {{ $meta['label'] }} Settings
            </button>
        </form>
    </div>
</div>
@endforeach

{{-- ═══ Maintenance Mode ═══════════════════════════════════════════════════ --}}
@php
    $maintEnabled = \App\Models\Setting::get('maintenance_mode','0') === '1';
    $maintMessage = \App\Models\Setting::get('maintenance_message','The app is currently under maintenance. Please try again later.');
@endphp
<div class="app-card" style="border:2px solid {{ $maintEnabled ? '#e74c3c' : 'var(--border)' }};">
    <div class="app-card-header" style="background:{{ $maintEnabled ? 'rgba(231,76,60,0.08)' : '' }}">
        <div style="width:52px;height:52px;border-radius:14px;background:{{ $maintEnabled ? '#e74c3c' : '#7f8c8d' }};display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-tools" style="color:#fff;font-size:22px;"></i>
        </div>
        <div style="flex:1;">
            <div style="font-weight:800;font-size:16px;color:var(--text);">Maintenance Mode</div>
            <div style="font-size:12px;color:var(--text-muted);">When ON, all apps (Customer, Driver, Vendor) show a maintenance screen in real-time</div>
        </div>
        <span id="maintBadge" style="padding:6px 16px;border-radius:20px;font-size:13px;font-weight:700;background:{{ $maintEnabled ? '#e74c3c' : '#27ae60' }};color:#fff;">
            {{ $maintEnabled ? '🔴 ACTIVE' : '🟢 OFF' }}
        </span>
    </div>
    <div style="padding:24px;">
        @if($maintEnabled)
        <div style="background:#ffeaea;border:1px solid #e74c3c;border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#c0392b;font-size:13px;font-weight:600;">
            <i class="fas fa-exclamation-triangle"></i> Maintenance Mode is ACTIVE. All users see a maintenance screen right now.
        </div>
        @endif

        <div style="margin-bottom:16px;">
            <label style="font-weight:700;font-size:13px;color:var(--text);display:block;margin-bottom:8px;">Maintenance Message (shown to users)</label>
            <textarea id="maintMsg" class="form-input" rows="2" style="width:100%;">{{ $maintMessage }}</textarea>
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <button onclick="setMaintenance(true)"  class="save-btn" style="background:#e74c3c;flex:1;min-width:140px;">
                <i class="fas fa-tools"></i> Enable Maintenance
            </button>
            <button onclick="setMaintenance(false)" class="save-btn" style="background:#27ae60;flex:1;min-width:140px;">
                <i class="fas fa-check-circle"></i> Disable Maintenance
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function toggleWarning(type, checked) {
    const el = document.getElementById('warn-' + type);
    if (checked) {
        el.classList.add('visible');
    } else {
        el.classList.remove('visible');
    }
}

async function setMaintenance(enable) {
    const msg = document.getElementById('maintMsg').value;
    const label = enable ? 'Enable' : 'Disable';
    if (!confirm(`${label} maintenance mode?`)) return;

    try {
        const res = await fetch('{{ route("admin.maintenance.toggle") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ enabled: enable, message: msg }),
        });
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Unknown error'));
        }
    } catch(e) {
        alert('Network error. Please try again.');
    }
}
</script>
@endpush
@endsection
