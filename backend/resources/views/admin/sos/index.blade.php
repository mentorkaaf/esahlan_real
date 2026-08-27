@extends('admin.layouts.app')
@section('title', 'SOS Emergency Alerts')

@push('css')
<style>
:root {
    --sos-red: #FF2D55;
    --sos-orange: #FF9500;
    --sos-green: #34C759;
    --sos-card: rgba(255,45,85,0.08);
}
.sos-header {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 20px;
    background: linear-gradient(135deg, #FF2D55 0%, #C2185B 100%);
    border-radius: 12px; color: #fff; margin-bottom: 20px;
}
.sos-header .pulse-ring {
    width: 14px; height: 14px; border-radius: 50%;
    background: #fff;
    box-shadow: 0 0 0 0 rgba(255,255,255,0.7);
    animation: pulse 1.4s ease infinite;
}
@keyframes pulse {
    0%   { box-shadow: 0 0 0 0 rgba(255,255,255,0.7); }
    70%  { box-shadow: 0 0 0 10px rgba(255,255,255,0); }
    100% { box-shadow: 0 0 0 0 rgba(255,255,255,0); }
}
.sos-banner {
    display: none;
    position: fixed; top: 0; left: 0; right: 0; z-index: 9999;
    background: var(--sos-red);
    color: #fff; padding: 14px 20px;
    font-size: 16px; font-weight: 700;
    justify-content: space-between; align-items: center;
    animation: flash 0.5s ease infinite alternate;
}
@keyframes flash {
    from { opacity: 1; }
    to   { opacity: 0.85; }
}
.sos-banner.show { display: flex; }
.alert-card {
    border: 1px solid rgba(255,45,85,0.3);
    border-radius: 10px; padding: 16px 18px;
    margin-bottom: 12px;
    background: var(--surface);
    transition: box-shadow .2s;
    position: relative; overflow: hidden;
}
.alert-card.active {
    border-color: var(--sos-red);
    box-shadow: 0 0 0 2px rgba(255,45,85,0.15);
}
.alert-card.active::before {
    content: '';
    position: absolute; left: 0; top: 0; bottom: 0;
    width: 4px; background: var(--sos-red);
}
.alert-card.resolved { opacity: .6; }
.tier-badge {
    display: inline-block; padding: 2px 8px;
    border-radius: 20px; font-size: 11px; font-weight: 700;
}
.tier-active { background: rgba(255,45,85,.15); color: var(--sos-red); }
.tier-resolved { background: rgba(52,199,89,.15); color: var(--sos-green); }
.resolve-btn {
    border: none; padding: 6px 16px; border-radius: 6px;
    font-weight: 600; cursor: pointer;
    background: var(--sos-red); color: #fff;
    transition: opacity .2s;
}
.resolve-btn:hover { opacity: .85; }
.resolve-btn:disabled { background: #888; cursor: default; }
.stat-box {
    border-radius: 10px; padding: 14px 18px;
    text-align: center; background: var(--surface);
    border: 1px solid var(--border);
}
.stat-box .val { font-size: 28px; font-weight: 800; }
.stat-box .lbl { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
.map-link {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(0,122,255,.1); color: #007AFF;
    padding: 4px 10px; border-radius: 6px;
    text-decoration: none; font-size: 12px; font-weight: 600;
}
.filter-tabs { display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap; }
.filter-tab {
    padding: 6px 16px; border-radius: 20px; border: 1px solid var(--border);
    background: var(--surface); cursor: pointer; font-size: 13px;
    transition: all .15s;
}
.filter-tab.active {
    background: var(--sos-red); color: #fff; border-color: var(--sos-red);
}
</style>
@endpush

@section('content')

{{-- New SOS Flash Banner --}}
<div id="sosBanner" class="sos-banner">
    <span><i class="fas fa-exclamation-triangle"></i> &nbsp;<span id="bannerText">NEW SOS EMERGENCY</span></span>
    <button onclick="document.getElementById('sosBanner').classList.remove('show')" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">&times;</button>
</div>

{{-- Header --}}
<div class="sos-header">
    <div class="pulse-ring" id="statusRing"></div>
    <div>
        <div style="font-size:18px;font-weight:800;">SOS Emergency Center</div>
        <div style="font-size:12px;opacity:.85;">Real-time driver emergency alerts</div>
    </div>
    <div style="margin-left:auto;font-size:13px;opacity:.85;" id="lastPoll">Watching live...</div>
</div>

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
    <div class="stat-box">
        <div class="val" id="statActive" style="color:var(--sos-red)">{{ $activeCount }}</div>
        <div class="lbl">Active SOS</div>
    </div>
    <div class="stat-box">
        <div class="val" id="statTotal">{{ $alerts->count() }}</div>
        <div class="lbl">Total Alerts</div>
    </div>
    <div class="stat-box">
        <div class="val" id="statResolved" style="color:var(--sos-green)">{{ $alerts->where('status','resolved')->count() }}</div>
        <div class="lbl">Resolved</div>
    </div>
    <div class="stat-box">
        <div class="val" style="color:var(--sos-orange)">{{ $alerts->whereIn('status',['active','pending'])->where('latitude','!=',null)->count() }}</div>
        <div class="lbl">With Location</div>
    </div>
</div>

{{-- Filter Tabs --}}
<div class="filter-tabs">
    <div class="filter-tab active" onclick="filterAlerts('all',this)">All</div>
    <div class="filter-tab" onclick="filterAlerts('active',this)">Active</div>
    <div class="filter-tab" onclick="filterAlerts('resolved',this)">Resolved</div>
</div>

{{-- Alerts List --}}
<div id="alertsList">
@forelse($alerts as $a)
<div class="alert-card {{ in_array($a->status,['active','pending']) ? 'active' : 'resolved' }}" data-status="{{ $a->status }}" id="alert-{{ $a->id }}">
    <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;">
        {{-- Icon --}}
        <div style="width:44px;height:44px;border-radius:50%;background:{{ in_array($a->status,['active','pending']) ? 'rgba(255,45,85,.15)' : 'rgba(134,134,139,.1)' }};display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
            @if(in_array($a->status,['active','pending']))
                <i class="fas fa-exclamation-triangle" style="color:var(--sos-red)"></i>
            @else
                <i class="fas fa-check-circle" style="color:var(--sos-green)"></i>
            @endif
        </div>

        {{-- Info --}}
        <div style="flex:1;min-width:200px;">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                <span style="font-weight:700;font-size:15px;">{{ $a->driver_name ?? 'Driver #'.$a->deliveryman_id }}</span>
                <span class="tier-badge {{ in_array($a->status,['active','pending']) ? 'tier-active' : 'tier-resolved' }}">
                    {{ strtoupper($a->status) }}
                </span>
                <span style="font-size:11px;color:var(--text-muted);">Alert #{{ $a->id }}</span>
            </div>
            <div style="font-size:13px;color:var(--text-muted);margin-bottom:6px;">
                <i class="fas fa-phone" style="width:14px;"></i> {{ $a->driver_phone ?? '—' }}
                @if($a->order_number)
                &nbsp;&bull;&nbsp;<i class="fas fa-box"></i> Order #{{ $a->order_number }}
                @endif
            </div>
            @if($a->message)
            <div style="font-size:13px;background:rgba(255,45,85,.07);padding:8px 12px;border-radius:6px;margin-bottom:8px;color:var(--text);">
                "{{ $a->message }}"
            </div>
            @endif
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span style="font-size:11px;color:var(--text-muted);"><i class="fas fa-clock"></i> {{ \Carbon\Carbon::parse($a->created_at)->diffForHumans() }}</span>
                @if($a->latitude && $a->longitude)
                <a href="https://maps.google.com/?q={{ $a->latitude }},{{ $a->longitude }}" target="_blank" class="map-link">
                    <i class="fas fa-map-marker-alt"></i> View Location
                </a>
                @endif
                @if($a->resolved_by_name)
                <span style="font-size:11px;color:var(--sos-green);"><i class="fas fa-user-check"></i> Resolved by {{ $a->resolved_by_name }}</span>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        @if(in_array($a->status,['active','pending']))
        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
            @if($a->driver_phone)
            <a href="tel:{{ $a->driver_phone }}" class="btn btn-sm btn-success" style="font-size:12px;">
                <i class="fas fa-phone"></i> Call Driver
            </a>
            @endif
            <button class="resolve-btn" onclick="resolveAlert({{ $a->id }}, this)">
                <i class="fas fa-check"></i> Resolve
            </button>
        </div>
        @endif
    </div>

    {{-- Resolve Notes (hidden) --}}
    <div id="resolveBox-{{ $a->id }}" style="display:none;margin-top:12px;border-top:1px solid var(--border);padding-top:12px;">
        <textarea id="noteInput-{{ $a->id }}" placeholder="Admin notes (optional)..." rows="2"
            style="width:100%;border:1px solid var(--border);border-radius:6px;padding:8px;background:var(--bg);color:var(--text);font-size:13px;resize:none;"></textarea>
        <div style="display:flex;gap:8px;margin-top:8px;">
            <button onclick="confirmResolve({{ $a->id }}, this)" style="background:var(--sos-green);border:none;color:#fff;padding:6px 14px;border-radius:6px;font-weight:600;cursor:pointer;">
                <i class="fas fa-check"></i> Confirm Resolve
            </button>
            <button onclick="document.getElementById('resolveBox-{{ $a->id }}').style.display='none'"
                style="background:var(--border);border:none;color:var(--text);padding:6px 14px;border-radius:6px;cursor:pointer;">
                Cancel
            </button>
        </div>
    </div>
</div>
@empty
<div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
    <i class="fas fa-shield-alt" style="font-size:48px;margin-bottom:16px;display:block;opacity:.3;"></i>
    <div style="font-size:16px;font-weight:600;">No SOS Alerts</div>
    <div style="font-size:13px;margin-top:4px;">All drivers are safe.</div>
</div>
@endforelse
</div>

{{-- Alarm Audio --}}
<audio id="sosAudio" loop preload="auto">
    <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbwr1hKiVZlMjhzqxgHxpPgrzXzK1ZHBBEcK/MyKpSFQg5YKXIxqZLEAA0UJe/w6JBCwAtPo61v55sCAAoLXOsub9kBAAkIWejtrthAgAhGlucsbVaAAAfE1CYrLFUAAAcDUaWqKxOAAAaB0CVpKhJAAAXBDyToaVEAAAUA0CSpqFAAAASAj+PpJ89AAAQAj2PpJs5AAAOATuNopg2AAAMAjiMoZU0AAAKADeMoJIxAAAIADSLoI8vAAAGADKLn4wsAAAEAC+Kn4kqAAADAC2Kn4cnAAABACyKnYUlAAAAKomchCMAACGInIMiAAAgII
" type="audio/wav">
</audio>
@endsection

@push('js')
<script>
const CSRF = '{{ csrf_token() }}';
let knownIds = new Set([{{ $alerts->pluck('id')->implode(',') }}]);
let alarmPlaying = false;
let currentFilter = 'all';

// Live polling
function pollLive() {
    fetch('{{ route("admin.sos.live") }}')
        .then(r => r.json())
        .then(data => {
            const now = new Date().toLocaleTimeString();
            document.getElementById('lastPoll').textContent = 'Updated: ' + now;
            document.getElementById('statActive').textContent = data.count;

            if (data.count > 0) {
                document.getElementById('statusRing').style.background = '#fff';
            }

            // New alerts?
            data.data.forEach(alert => {
                if (!knownIds.has(alert.id)) {
                    knownIds.add(alert.id);
                    showBanner(alert.driver_name, alert.driver_phone);
                    playAlarm();
                    prependAlert(alert);
                }
            });
        })
        .catch(() => {});
}

function prependAlert(a) {
    const list = document.getElementById('alertsList');
    const div = document.createElement('div');
    div.className = 'alert-card active';
    div.id = 'alert-' + a.id;
    div.setAttribute('data-status', 'active');
    div.innerHTML = `
        <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;">
            <div style="width:44px;height:44px;border-radius:50%;background:rgba(255,45,85,.15);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
                <i class="fas fa-exclamation-triangle" style="color:var(--sos-red)"></i>
            </div>
            <div style="flex:1;min-width:200px;">
                <div style="font-weight:700;font-size:15px;">${a.driver_name || 'Driver #'+a.deliveryman_id}</div>
                <div style="font-size:13px;color:var(--text-muted);margin-bottom:6px;">${a.driver_phone || '—'}</div>
                ${a.message ? `<div style="font-size:13px;background:rgba(255,45,85,.07);padding:8px 12px;border-radius:6px;margin-bottom:8px;">"${a.message}"</div>` : ''}
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <span style="font-size:11px;color:var(--text-muted);">Just now</span>
                    ${a.latitude && a.longitude ? `<a href="https://maps.google.com/?q=${a.latitude},${a.longitude}" target="_blank" class="map-link"><i class="fas fa-map-marker-alt"></i> View Location</a>` : ''}
                </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                ${a.driver_phone ? `<a href="tel:${a.driver_phone}" class="btn btn-sm btn-success" style="font-size:12px;"><i class="fas fa-phone"></i> Call Driver</a>` : ''}
                <button class="resolve-btn" onclick="resolveAlert(${a.id}, this)"><i class="fas fa-check"></i> Resolve</button>
            </div>
        </div>
        <div id="resolveBox-${a.id}" style="display:none;margin-top:12px;border-top:1px solid var(--border);padding-top:12px;">
            <textarea id="noteInput-${a.id}" placeholder="Admin notes (optional)..." rows="2" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:8px;background:var(--bg);color:var(--text);font-size:13px;resize:none;"></textarea>
            <div style="display:flex;gap:8px;margin-top:8px;">
                <button onclick="confirmResolve(${a.id}, this)" style="background:var(--sos-green);border:none;color:#fff;padding:6px 14px;border-radius:6px;font-weight:600;cursor:pointer;"><i class="fas fa-check"></i> Confirm Resolve</button>
                <button onclick="document.getElementById('resolveBox-${a.id}').style.display='none'" style="background:var(--border);border:none;color:var(--text);padding:6px 14px;border-radius:6px;cursor:pointer;">Cancel</button>
            </div>
        </div>
    `;
    list.prepend(div);
}

function showBanner(name, phone) {
    const banner = document.getElementById('sosBanner');
    document.getElementById('bannerText').textContent = '🆘 NEW SOS — ' + (name || 'Driver') + ' · ' + (phone || '');
    banner.classList.add('show');
    setTimeout(() => banner.classList.remove('show'), 15000);
}

function playAlarm() {
    if (alarmPlaying) return;
    alarmPlaying = true;
    // Use Web Audio API beep since embedded audio may not play
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        function beep() {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'square';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.4);
        }
        beep();
        setTimeout(beep, 600);
        setTimeout(beep, 1200);
        setTimeout(() => { alarmPlaying = false; }, 5000);
    } catch(e) { alarmPlaying = false; }
}

function filterAlerts(f, el) {
    currentFilter = f;
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('.alert-card').forEach(card => {
        const status = card.getAttribute('data-status');
        if (f === 'all') card.style.display = '';
        else if (f === 'active') card.style.display = ['active','pending'].includes(status) ? '' : 'none';
        else if (f === 'resolved') card.style.display = status === 'resolved' ? '' : 'none';
    });
}

function resolveAlert(id, btn) {
    document.getElementById('resolveBox-' + id).style.display = 'block';
    btn.style.display = 'none';
}

function confirmResolve(id, btn) {
    btn.disabled = true;
    btn.textContent = 'Resolving...';
    const notes = document.getElementById('noteInput-' + id)?.value || '';
    fetch('{{ url("admin/sos") }}/' + id + '/resolve', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF},
        body: JSON.stringify({notes})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById('alert-' + id);
            card.classList.remove('active');
            card.classList.add('resolved');
            card.setAttribute('data-status', 'resolved');
            card.querySelector('.tier-badge').className = 'tier-badge tier-resolved';
            card.querySelector('.tier-badge').textContent = 'RESOLVED';
            const actionDiv = card.querySelector('.resolve-btn')?.closest('div');
            if (actionDiv) actionDiv.remove();
            document.getElementById('resolveBox-' + id).style.display = 'none';
            const stat = parseInt(document.getElementById('statActive').textContent) - 1;
            document.getElementById('statActive').textContent = Math.max(0, stat);
            const res = parseInt(document.getElementById('statResolved').textContent) + 1;
            document.getElementById('statResolved').textContent = res;
        }
    })
    .catch(() => { btn.disabled = false; btn.textContent = 'Retry'; });
}

// Poll every 10 seconds
setInterval(pollLive, 10000);
</script>
@endpush
