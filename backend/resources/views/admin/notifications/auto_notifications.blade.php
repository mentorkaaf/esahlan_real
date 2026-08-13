@extends('admin.layouts.app')
@section('title', 'Auto Notifications')
@section('content')

<style>
.an-head      { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px; }
.an-title     { font-size:20px;font-weight:900;color:var(--text); }
.an-sub       { font-size:13px;color:var(--text-muted);margin-top:3px; }
.an-stats     { display:flex;gap:14px;flex-wrap:wrap;margin-bottom:22px; }
.an-stat      { background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 20px;min-width:160px; }
.an-stat-val  { font-size:22px;font-weight:800;color:var(--text); }
.an-stat-lbl  { font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-top:2px; }

/* Section separator */
.an-section   { margin-bottom:32px; }
.an-sec-head  { display:flex;align-items:center;gap:10px;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid var(--border); }
.an-sec-icon  { width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px; }
.an-sec-title { font-size:16px;font-weight:800;color:var(--text); }
.an-sec-badge { font-size:11px;padding:3px 10px;border-radius:20px;font-weight:700; }

/* Template card */
.an-card      { background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-bottom:16px;box-shadow:0 2px 12px rgba(0,0,0,.05); }
.an-card-head { display:flex;align-items:center;gap:14px;padding:18px 20px;border-bottom:1px solid var(--border); }
.an-card-body { padding:20px; }
.an-card-foot { padding:14px 20px;border-top:1px solid var(--border);background:var(--bg);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px; }

/* Toggle */
.an-toggle-wrap   { display:flex;align-items:center;gap:10px; }
.an-toggle        { position:relative;display:inline-block;width:46px;height:26px; }
.an-toggle input  { opacity:0;width:0;height:0; }
.an-toggle-slider { position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:26px;transition:.3s; }
.an-toggle-slider:before { content:'';position:absolute;height:20px;width:20px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s; }
.an-toggle input:checked + .an-toggle-slider { background:var(--brand); }
.an-toggle input:checked + .an-toggle-slider:before { transform:translateX(20px); }

/* Variable chips */
.an-vars      { display:flex;flex-wrap:wrap;gap:6px;margin-top:8px; }
.an-var-chip  { font-size:11px;padding:2px 8px;background:rgba(99,102,241,.1);color:#6366f1;border-radius:5px;font-family:monospace;cursor:pointer;border:1px solid rgba(99,102,241,.2); }
.an-var-chip:hover { background:rgba(99,102,241,.2); }

/* Log table */
.an-log-table { width:100%;border-collapse:collapse;font-size:12px; }
.an-log-table th { background:var(--bg);color:var(--text-muted);font-weight:700;padding:8px 12px;text-align:left;text-transform:uppercase;font-size:10px;letter-spacing:.5px; }
.an-log-table td { padding:10px 12px;border-bottom:1px solid var(--border);color:var(--text); }
.an-log-table tr:last-child td { border:none; }

/* Alert */
.an-alert     { padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:16px;font-weight:600; }
.an-alert-s   { background:#d1fae5;color:#065f46;border:1px solid #6ee7b7; }
.an-alert-e   { background:#fee2e2;color:#991b1b;border:1px solid #fca5a5; }
.an-alert-i   { background:#eff6ff;color:#1e40af;border:1px solid #93c5fd; }

/* Preview */
.an-preview   { background:linear-gradient(135deg,#1a1d2e,#2d3161);border-radius:14px;padding:16px;margin-top:12px; }
.an-preview-notif { background:#fff;border-radius:10px;padding:12px 14px;display:flex;gap:12px;align-items:flex-start; }
.an-preview-icon  { width:36px;height:36px;border-radius:9px;background:#f97316;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0; }
.an-preview-title { font-size:13px;font-weight:700;color:#1a1d2e;margin-bottom:3px; }
.an-preview-body  { font-size:11px;color:#4b5563;line-height:1.4; }

/* Flight mini cards */
.flight-mini  { display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--bg);border-radius:10px;margin-bottom:8px;border:1px solid var(--border); }
.flight-mini-codes { font-weight:900;font-size:14px;color:var(--text); }
.flight-mini-meta  { font-size:11px;color:var(--text-muted); }
</style>

<div class="an-head">
    <div>
        <div class="an-title">🤖 Auto Notifications</div>
        <div class="an-sub">Manage automated push notification templates, timing, and send history</div>
    </div>
    <a href="{{ route('admin.notifications.index') }}" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;background:var(--bg);color:var(--text);font-size:13px;font-weight:700;text-decoration:none;border:1px solid var(--border);">
        ← Back to Notifications
    </a>
</div>

@if(session('success'))
    <div class="an-alert an-alert-s">✓ {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="an-alert an-alert-e">✗ {{ session('error') }}</div>
@endif

{{-- Stats --}}
<div class="an-stats">
    <div class="an-stat">
        <div class="an-stat-val">{{ number_format($stats['users_with_fcm']) }}</div>
        <div class="an-stat-lbl">Users with FCM token</div>
    </div>
    <div class="an-stat">
        <div class="an-stat-val">{{ $templates->count() }}</div>
        <div class="an-stat-lbl">Active template types</div>
    </div>
    <div class="an-stat">
        <div class="an-stat-val">{{ $upcomingFlights->count() }}</div>
        <div class="an-stat-lbl">Upcoming flights</div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     SECTION: eTicket — Upcoming Flights
     ════════════════════════════════════════════════════════ --}}
<div class="an-section">
    <div class="an-sec-head">
        <div class="an-sec-icon" style="background:rgba(249,115,22,.12);color:#f97316;">✈️</div>
        <div>
            <div class="an-sec-title">eTicket — Upcoming Flight Alerts</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">Sent every 12 hours (configurable). Deep link opens passenger selection directly.</div>
        </div>
    </div>

    @php
        $flightTpl = $templates->firstWhere('slug', 'eticket_upcoming_flight');
    @endphp

    @if($flightTpl)
    <div class="an-card">
        <div class="an-card-head">
            <div style="flex:1;">
                <div style="font-size:15px;font-weight:800;color:var(--text);">{{ $flightTpl->label }}</div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:3px;">
                    Sends every <strong>{{ $flightTpl->interval_hours }}h</strong> ·
                    Last sent: <strong>{{ $flightTpl->last_sent_at ? \Carbon\Carbon::parse($flightTpl->last_sent_at)->diffForHumans() : 'Never' }}</strong>
                </div>
            </div>
            {{-- On/Off toggle --}}
            <div class="an-toggle-wrap">
                <span style="font-size:12px;color:var(--text-muted);font-weight:600;">{{ $flightTpl->is_active ? 'ACTIVE' : 'PAUSED' }}</span>
                <label class="an-toggle">
                    <input type="checkbox" {{ $flightTpl->is_active ? 'checked' : '' }}
                        onchange="toggleAutoTemplate('eticket_upcoming_flight', this)">
                    <span class="an-toggle-slider"></span>
                </label>
            </div>
        </div>

        <div class="an-card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                {{-- Template editor --}}
                <div>
                    <form action="{{ route('admin.notifications.auto.update', 'eticket_upcoming_flight') }}" method="POST">
                        @csrf
                        <div style="margin-bottom:14px;">
                            <label style="font-size:12px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:6px;">NOTIFICATION TITLE</label>
                            <input type="text" name="title_template" class="form-control"
                                value="{{ old('title_template', $flightTpl->title_template) }}"
                                id="flight_title_input" oninput="updatePreview()">
                        </div>
                        <div style="margin-bottom:14px;">
                            <label style="font-size:12px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:6px;">NOTIFICATION BODY</label>
                            <textarea name="body_template" class="form-control" rows="3"
                                id="flight_body_input" oninput="updatePreview()">{{ old('body_template', $flightTpl->body_template) }}</textarea>
                        </div>
                        <div class="an-vars">
                            <span style="font-size:11px;color:var(--text-muted);font-weight:600;align-self:center;">Variables:</span>
                            @foreach(['{from}','{to}','{from_code}','{to_code}','{flight_no}','{date}','{price}','{seats}','{airline}'] as $v)
                                <span class="an-var-chip" onclick="insertVar('{{ $v }}')">{{ $v }}</span>
                            @endforeach
                        </div>
                        <div style="margin-top:14px;">
                            <label style="font-size:12px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:6px;">SEND INTERVAL (hours)</label>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <input type="number" name="interval_hours" class="form-control" style="width:100px;"
                                    value="{{ old('interval_hours', $flightTpl->interval_hours) }}" min="1" max="168">
                                <span style="font-size:12px;color:var(--text-muted);">1 = every hour · 12 = twice/day · 24 = daily</span>
                            </div>
                        </div>
                        <div style="margin-top:16px;">
                            <button type="submit" class="btn btn-primary" style="font-size:13px;">
                                <i class="fa-solid fa-floppy-disk"></i> Save Template
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Preview + upcoming flights --}}
                <div>
                    <div style="font-size:12px;font-weight:700;color:var(--text-muted);margin-bottom:10px;">LIVE PREVIEW</div>
                    <div class="an-preview">
                        <div class="an-preview-notif">
                            <div class="an-preview-icon">✈️</div>
                            <div>
                                <div class="an-preview-title" id="preview_title">{{ $flightTpl->title_template }}</div>
                                <div class="an-preview-body" id="preview_body">{{ $flightTpl->body_template }}</div>
                            </div>
                        </div>
                        <div style="font-size:10px;color:rgba(255,255,255,.4);margin-top:8px;text-align:right;">
                            Deep link: /eticket/flight/{id} → Passenger Selection
                        </div>
                    </div>

                    <div style="margin-top:16px;font-size:12px;font-weight:700;color:var(--text-muted);margin-bottom:8px;">UPCOMING FLIGHTS (next to notify)</div>
                    @forelse($upcomingFlights as $fl)
                        @php
                            $cls = json_decode($fl->seat_classes ?? '{}', true) ?? [];
                            $price = array_values($cls)[0] ?? 0;
                        @endphp
                        <div class="flight-mini">
                            <div style="width:32px;height:32px;background:var(--brand);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;font-weight:900;flex-shrink:0;">✈</div>
                            <div style="flex:1;">
                                <div class="flight-mini-codes">{{ strtoupper($fl->from_code) }} → {{ strtoupper($fl->to_code) }}</div>
                                <div class="flight-mini-meta">{{ $fl->flight_number }} · {{ \Carbon\Carbon::parse($fl->departure_at)->format('M j, g:ia') }} · {{ $fl->available_seats }} seats · ${{ number_format((float)$price, 0) }}</div>
                            </div>
                        </div>
                    @empty
                        <div style="font-size:13px;color:var(--text-muted);padding:12px;text-align:center;background:var(--bg);border-radius:10px;">No upcoming flights</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="an-card-foot">
            <div style="font-size:12px;color:var(--text-muted);">
                📡 Targets: <strong>{{ number_format($stats['users_with_fcm']) }}</strong> users with FCM ·
                🕐 Every <strong>{{ $flightTpl->interval_hours }}h</strong> via scheduler
            </div>
            <form action="{{ route('admin.notifications.auto.send-now', 'eticket_upcoming_flight') }}" method="POST" style="display:inline;"
                onsubmit="return confirm('Send flight notifications to all {{ number_format($stats["users_with_fcm"]) }} users NOW?')">
                @csrf
                <button type="submit" class="btn btn-primary" style="font-size:13px;background:linear-gradient(135deg,#f97316,#ea580c);">
                    <i class="fa-solid fa-paper-plane"></i> Send Now
                </button>
            </form>
        </div>
    </div>

    {{-- Send log for this template --}}
    @if(($logs['eticket_upcoming_flight'] ?? collect())->count() > 0)
    <div class="an-card" style="margin-top:12px;">
        <div class="an-card-head">
            <div style="font-size:14px;font-weight:800;color:var(--text);">📋 Recent Send History</div>
        </div>
        <div style="overflow-x:auto;">
            <table class="an-log-table">
                <thead><tr>
                    <th>Sent At</th><th>Flight</th><th>Title</th><th>Recipients</th>
                </tr></thead>
                <tbody>
                @foreach(($logs['eticket_upcoming_flight'] ?? collect())->take(10) as $log)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($log->created_at)->format('M j, g:ia') }}</td>
                        <td>{{ $log->reference_type === 'flight' ? 'Flight #'.$log->reference_id : '—' }}</td>
                        <td>{{ \Str::limit($log->title, 50) }}</td>
                        <td><strong>{{ number_format($log->sent_count) }}</strong></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @else
    <div class="an-alert an-alert-i">Template not found in database. Run <code>php artisan migrate</code> to create it.</div>
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════
     SECTION: eFood Discount Campaigns (existing — link)
     ════════════════════════════════════════════════════════ --}}
<div class="an-section">
    <div class="an-sec-head">
        <div class="an-sec-icon" style="background:rgba(16,185,129,.12);color:#10b981;">🔥</div>
        <div>
            <div class="an-sec-title">eFood — Discount Campaign Alerts</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">Sends every 2 hours (normal) · every 30 min (urgent, last 2h of campaign).</div>
        </div>
    </div>
    <div class="an-card">
        <div class="an-card-body" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <div>
                <div style="font-size:14px;font-weight:700;color:var(--text);">Managed per campaign</div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Each discount campaign has its own notification settings (title, body, pause, interval).</div>
            </div>
            <a href="{{ route('admin.notifications.discount-campaigns') }}" class="btn btn-primary" style="font-size:13px;white-space:nowrap;">
                <i class="fa-solid fa-fire"></i> Manage Discount Campaigns
            </a>
        </div>
    </div>
</div>

{{-- Future sections placeholder --}}
<div class="an-section">
    <div class="an-sec-head">
        <div class="an-sec-icon" style="background:rgba(100,116,139,.1);color:#64748b;">🔮</div>
        <div>
            <div class="an-sec-title" style="color:var(--text-muted);">Future Auto Notifications</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">New types (eRent listings, eShop sales, etc.) will appear here when added.</div>
        </div>
    </div>
    <div style="background:var(--bg);border:2px dashed var(--border);border-radius:14px;padding:28px;text-align:center;color:var(--text-muted);font-size:13px;">
        <div style="font-size:28px;margin-bottom:8px;">➕</div>
        New notification types added via migrations will appear here automatically.
    </div>
</div>

<script>
// Variable insertion
let lastFocused = null;
document.getElementById('flight_title_input').addEventListener('focus', () => lastFocused = 'flight_title_input');
document.getElementById('flight_body_input').addEventListener('focus',  () => lastFocused = 'flight_body_input');

function insertVar(v) {
    const el = document.getElementById(lastFocused || 'flight_body_input');
    if (!el) return;
    const s = el.selectionStart, e = el.selectionEnd;
    el.value = el.value.substring(0, s) + v + el.value.substring(e);
    el.selectionStart = el.selectionEnd = s + v.length;
    el.focus();
    updatePreview();
}

// Live preview with example flight data
const EXAMPLE = {
    '{from}': 'Hargeysa', '{to}': 'Muqdisho', '{from_code}': 'HGA', '{to_code}': 'MGQ',
    '{flight_no}': 'DA991', '{date}': 'Aug 15, 2026', '{price}': '200', '{seats}': '10', '{airline}': 'Daallo Airlines'
};
function updatePreview() {
    let title = document.getElementById('flight_title_input').value;
    let body  = document.getElementById('flight_body_input').value;
    for (const [k,v] of Object.entries(EXAMPLE)) {
        title = title.replaceAll(k, v);
        body  = body.replaceAll(k, v);
    }
    document.getElementById('preview_title').textContent = title;
    document.getElementById('preview_body').textContent  = body;
}
updatePreview();

// Toggle via AJAX
function toggleAutoTemplate(slug, checkbox) {
    const url = "{{ route('admin.notifications.auto.toggle', '__slug__') }}".replace('__slug__', slug);
    fetch(url, { method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json'} })
        .then(r => r.json())
        .then(data => {
            const label = checkbox.closest('.an-toggle-wrap').querySelector('span');
            if (label) label.textContent = data.is_active ? 'ACTIVE' : 'PAUSED';
        })
        .catch(() => { checkbox.checked = !checkbox.checked; alert('Failed to update.'); });
}
</script>

@endsection
