@extends('admin.layouts.app')

@section('title', 'SOC Security Dashboard')

@push('styles')
<style>
    /* ── SOC-specific overrides ── */
    .soc-kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
    @media (max-width: 1100px) { .soc-kpi-grid { grid-template-columns: repeat(2,1fr); } }
    @media (max-width: 600px)  { .soc-kpi-grid { grid-template-columns: 1fr; } }

    .kpi-card { background:#fff; border-radius: 14px; padding: 20px 22px; border: 1.5px solid #f1f5f9; position: relative; overflow: hidden; }
    .kpi-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background: var(--kpi-accent, #FF8A00); }
    .kpi-label { font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #9ca3af; margin-bottom: 8px; }
    .kpi-value { font-size: 32px; font-weight: 800; color: #111827; line-height: 1; font-variant-numeric: tabular-nums; }
    .kpi-sub   { font-size: 12px; color: #9ca3af; margin-top: 6px; }
    .kpi-icon  { position: absolute; top: 18px; right: 18px; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 17px; }

    .kpi-danger  { --kpi-accent: #ef4444; } .kpi-danger .kpi-icon { background: rgba(239,68,68,.1); color: #ef4444; }
    .kpi-warn    { --kpi-accent: #f59e0b; } .kpi-warn .kpi-icon  { background: rgba(245,158,11,.1); color: #f59e0b; }
    .kpi-ok      { --kpi-accent: #10b981; } .kpi-ok .kpi-icon    { background: rgba(16,185,129,.1); color: #10b981; }
    .kpi-blue    { --kpi-accent: #3b82f6; } .kpi-blue .kpi-icon  { background: rgba(59,130,246,.1); color: #3b82f6; }

    .soc-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    .soc-grid3 { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px; }
    @media (max-width: 960px) { .soc-grid2, .soc-grid3 { grid-template-columns: 1fr; } }

    .soc-card { background: #fff; border-radius: 14px; border: 1.5px solid #f1f5f9; overflow: hidden; }
    .soc-card-head { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
    .soc-card-title { font-size: 13.5px; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 8px; }
    .soc-card-title i { color: #FF8A00; }
    .soc-card-body { padding: 20px; }

    /* Sparkline chart */
    .chart-wrap { position: relative; height: 120px; }
    canvas.sparkline { width: 100% !important; height: 100% !important; display: block; }

    /* Severity badge */
    .sev { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .sev-critical { background: rgba(239,68,68,.1); color: #dc2626; }
    .sev-warning  { background: rgba(245,158,11,.1); color: #d97706; }
    .sev-info     { background: rgba(59,130,246,.1); color: #2563eb; }
    .sev-debug    { background: rgba(156,163,175,.1); color: #6b7280; }

    /* IP table */
    .ip-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f8fafc; }
    .ip-row:last-child { border-bottom: none; }
    .ip-bar-wrap { flex: 1; height: 6px; background: #f1f5f9; border-radius: 3px; overflow: hidden; }
    .ip-bar { height: 100%; background: linear-gradient(90deg, #ef4444, #f97316); border-radius: 3px; }
    .ip-addr { font-size: 12.5px; font-weight: 600; color: #374151; min-width: 120px; font-family: monospace; }
    .ip-count { font-size: 12px; font-weight: 700; color: #ef4444; min-width: 32px; text-align: right; font-variant-numeric: tabular-nums; }

    /* Audit log table */
    .audit-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .audit-table th { text-align: left; padding: 8px 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #9ca3af; border-bottom: 1.5px solid #f1f5f9; background: #fafbff; }
    .audit-table td { padding: 9px 10px; border-bottom: 1px solid #f8fafc; vertical-align: top; }
    .audit-table tr:last-child td { border-bottom: none; }
    .audit-table tr:hover td { background: #fafbff; }
    .event-pill { display: inline-block; padding: 2px 8px; border-radius: 5px; font-size: 11px; font-weight: 600; background: #f1f5f9; color: #374151; font-family: monospace; }

    /* Event breakdown bar */
    .evt-row { margin-bottom: 10px; }
    .evt-label { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; }
    .evt-name { font-weight: 600; color: #374151; font-family: monospace; }
    .evt-count { font-weight: 700; color: #9ca3af; font-variant-numeric: tabular-nums; }
    .evt-bar-wrap { height: 7px; background: #f1f5f9; border-radius: 4px; overflow: hidden; }
    .evt-bar { height: 100%; border-radius: 4px; background: linear-gradient(90deg, #FF8A00, #ff5f00); }

    /* Live badge */
    .live-dot { width: 8px; height: 8px; background: #10b981; border-radius: 50%; display: inline-block; animation: blink 1.4s ease-in-out infinite; }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

    /* Refresh timer */
    .refresh-info { font-size: 11.5px; color: #9ca3af; display: flex; align-items: center; gap: 6px; }

    /* Scrollable table wrapper */
    .table-scroll { overflow-x: auto; }
</style>
@endpush

@section('content')
<div class="page-header" style="margin-bottom:24px;">
    <div>
        <h1 class="page-title" style="font-size:22px;font-weight:800;color:#111827;margin:0 0 4px;">
            <i class="fas fa-shield-halved" style="color:#FF8A00;margin-right:8px;"></i>SOC Security Dashboard
        </h1>
        <p style="font-size:13px;color:#9ca3af;margin:0;">Real-time platform security monitoring · Auto-refreshes every 60s</p>
    </div>
    <div class="refresh-info">
        <span class="live-dot"></span>
        <span id="refreshCountdown">Refreshing in <b id="cdSec">60</b>s</span>
    </div>
</div>

{{-- ── KPI Row ── --}}
<div class="soc-kpi-grid">
    <div class="kpi-card kpi-danger">
        <div class="kpi-icon"><i class="fas fa-triangle-exclamation"></i></div>
        <div class="kpi-label">Auth Failures (1h)</div>
        <div class="kpi-value" id="kpiFailures">{{ $authFailures }}</div>
        <div class="kpi-sub">Failed login attempts</div>
    </div>
    <div class="kpi-card kpi-warn">
        <div class="kpi-icon"><i class="fas fa-ban"></i></div>
        <div class="kpi-label">Blocked IPs (24h)</div>
        <div class="kpi-value" id="kpiBlocked">{{ $blockedIps }}</div>
        <div class="kpi-sub">Unique IPs locked out</div>
    </div>
    <div class="kpi-card kpi-ok">
        <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
        <div class="kpi-label">Auth Success Rate</div>
        <div class="kpi-value" id="kpiSuccessRate">{{ $successRate }}%</div>
        <div class="kpi-sub">Last 60 minutes</div>
    </div>
    <div class="kpi-card kpi-blue">
        <div class="kpi-icon"><i class="fas fa-users"></i></div>
        <div class="kpi-label">Active Sessions</div>
        <div class="kpi-value" id="kpiSessions">{{ $activeSessions }}</div>
        <div class="kpi-sub">Users active in last 30m</div>
    </div>
</div>

{{-- ── Hourly Failures Chart + Event Breakdown ── --}}
<div class="soc-grid3">
    <div class="soc-card">
        <div class="soc-card-head">
            <div class="soc-card-title"><i class="fas fa-chart-area"></i> Auth Failures — Last 24 Hours</div>
        </div>
        <div class="soc-card-body">
            <div class="chart-wrap">
                <canvas id="hourlyChart"></canvas>
            </div>
        </div>
    </div>

    <div class="soc-card">
        <div class="soc-card-head">
            <div class="soc-card-title"><i class="fas fa-list-ul"></i> Event Breakdown (24h)</div>
        </div>
        <div class="soc-card-body">
            @php $maxEvt = $eventBreakdown->max('count') ?: 1; @endphp
            @forelse($eventBreakdown as $evt)
            <div class="evt-row">
                <div class="evt-label">
                    <span class="evt-name">{{ $evt->event }}</span>
                    <span class="evt-count">{{ number_format($evt->count) }}</span>
                </div>
                <div class="evt-bar-wrap">
                    <div class="evt-bar" style="width:{{ round(($evt->count / $maxEvt) * 100) }}%"></div>
                </div>
            </div>
            @empty
            <p class="text-muted" style="font-size:13px;text-align:center;padding:20px 0;">No events in last 24h</p>
            @endforelse
        </div>
    </div>
</div>

{{-- ── Top Attacking IPs + Recent Criticals ── --}}
<div class="soc-grid2">
    <div class="soc-card">
        <div class="soc-card-head">
            <div class="soc-card-title"><i class="fas fa-crosshairs"></i> Top Attacker IPs (24h)</div>
        </div>
        <div class="soc-card-body">
            @php $maxIp = $topIps->max('attempts') ?: 1; @endphp
            @forelse($topIps as $ip)
            <div class="ip-row">
                <span class="ip-addr">{{ $ip->ip_address }}</span>
                <div class="ip-bar-wrap">
                    <div class="ip-bar" style="width:{{ round(($ip->attempts / $maxIp) * 100) }}%"></div>
                </div>
                <span class="ip-count">{{ $ip->attempts }}</span>
            </div>
            @empty
            <p class="text-muted" style="font-size:13px;text-align:center;padding:20px 0;">No attacks detected</p>
            @endforelse
        </div>
    </div>

    <div class="soc-card">
        <div class="soc-card-head">
            <div class="soc-card-title"><i class="fas fa-fire"></i> Recent Critical Events</div>
            <span class="sev sev-critical">{{ $criticalEvents->count() }} events</span>
        </div>
        <div class="soc-card-body" style="padding:0 20px;">
            @forelse($criticalEvents as $evt)
            <div style="padding:10px 0;border-bottom:1px solid #f8fafc;display:flex;flex-direction:column;gap:3px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                    <span class="event-pill">{{ $evt->event }}</span>
                    <span style="font-size:11px;color:#9ca3af;white-space:nowrap;">{{ $evt->created_at->diffForHumans() }}</span>
                </div>
                <div style="font-size:11.5px;color:#6b7280;font-family:monospace;">
                    {{ $evt->ip_address }} @if($evt->user_identifier)· {{ $evt->user_identifier }}@endif
                </div>
            </div>
            @empty
            <p class="text-muted" style="font-size:13px;text-align:center;padding:20px 0;">No critical events</p>
            @endforelse
        </div>
    </div>
</div>

{{-- ── Full Audit Log ── --}}
<div class="soc-card" style="margin-bottom:24px;">
    <div class="soc-card-head">
        <div class="soc-card-title"><i class="fas fa-scroll"></i> Security Audit Log <span style="font-weight:400;color:#9ca3af;font-size:12px;">(last 50 events)</span></div>
        <div style="display:flex;gap:8px;align-items:center;">
            <input type="text" id="logSearch" placeholder="Filter events..." class="filter-input" style="width:180px;" oninput="filterLog(this.value)">
        </div>
    </div>
    <div class="table-scroll">
        <table class="audit-table" id="auditTable">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Event</th>
                    <th>Severity</th>
                    <th>IP Address</th>
                    <th>User / Identifier</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody id="auditBody">
                @forelse($auditLog as $log)
                <tr>
                    <td style="white-space:nowrap;color:#6b7280;font-size:12px;">
                        {{ $log->created_at->format('M d H:i:s') }}
                    </td>
                    <td><span class="event-pill">{{ $log->event }}</span></td>
                    <td>
                        @php $sev = strtolower($log->severity); @endphp
                        <span class="sev sev-{{ in_array($sev, ['critical','warning','info','debug']) ? $sev : 'info' }}">
                            {{ $log->severity }}
                        </span>
                    </td>
                    <td style="font-family:monospace;font-size:12px;">{{ $log->ip_address ?? '—' }}</td>
                    <td style="font-size:12px;color:#374151;">
                        @if($log->user_identifier){{ $log->user_identifier }}@else—@endif
                    </td>
                    <td style="font-size:11.5px;color:#9ca3af;max-width:200px;">
                        @if($log->metadata)
                            @php $meta = is_array($log->metadata) ? $log->metadata : []; @endphp
                            {{ collect($meta)->except(['ip','ua'])->map(fn($v,$k) => "$k: $v")->implode(' · ') ?: '—' }}
                        @else—@endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-muted" style="text-align:center;padding:30px;">No security events recorded yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
// ── Hourly sparkline chart ──────────────────────────────────────────────────
(function () {
    const raw = @json($hourlyData);
    const labels = raw.map(r => r.hour + ':00');
    const data   = raw.map(r => r.count);
    const max    = Math.max(...data, 1);

    const canvas = document.getElementById('hourlyChart');
    const ctx    = canvas.getContext('2d');

    function draw() {
        const W = canvas.offsetWidth;
        const H = canvas.offsetHeight;
        canvas.width  = W * devicePixelRatio;
        canvas.height = H * devicePixelRatio;
        ctx.scale(devicePixelRatio, devicePixelRatio);

        const pad = { top: 10, right: 10, bottom: 28, left: 36 };
        const cw  = W - pad.left - pad.right;
        const ch  = H - pad.top  - pad.bottom;
        const step = cw / (data.length - 1);

        // grid lines
        ctx.strokeStyle = '#f1f5f9';
        ctx.lineWidth   = 1;
        [0.25, 0.5, 0.75, 1].forEach(f => {
            const y = pad.top + ch * (1 - f);
            ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(pad.left + cw, y); ctx.stroke();
        });

        // area fill
        const grad = ctx.createLinearGradient(0, pad.top, 0, pad.top + ch);
        grad.addColorStop(0, 'rgba(239,68,68,0.25)');
        grad.addColorStop(1, 'rgba(239,68,68,0.02)');

        ctx.beginPath();
        data.forEach((v, i) => {
            const x = pad.left + i * step;
            const y = pad.top  + ch * (1 - v / max);
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.lineTo(pad.left + (data.length - 1) * step, pad.top + ch);
        ctx.lineTo(pad.left, pad.top + ch);
        ctx.closePath();
        ctx.fillStyle = grad;
        ctx.fill();

        // line
        ctx.beginPath();
        ctx.strokeStyle = '#ef4444';
        ctx.lineWidth   = 2;
        ctx.lineJoin    = 'round';
        data.forEach((v, i) => {
            const x = pad.left + i * step;
            const y = pad.top  + ch * (1 - v / max);
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.stroke();

        // x-axis labels (every 6h)
        ctx.fillStyle  = '#9ca3af';
        ctx.font       = `10px system-ui`;
        ctx.textAlign  = 'center';
        [0, 6, 12, 18, 23].forEach(i => {
            const x = pad.left + i * step;
            ctx.fillText(i + 'h', x, H - 6);
        });

        // y-axis labels
        ctx.textAlign = 'right';
        [0, Math.ceil(max / 2), max].forEach(v => {
            const y = pad.top + ch * (1 - v / max);
            ctx.fillText(v, pad.left - 6, y + 4);
        });
    }

    draw();
    window.addEventListener('resize', draw);
})();

// ── Log table filter ────────────────────────────────────────────────────────
function filterLog(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#auditBody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ── Auto-refresh every 60 seconds ──────────────────────────────────────────
let remaining = 60;
const cdEl = document.getElementById('cdSec');

setInterval(() => {
    remaining--;
    if (cdEl) cdEl.textContent = remaining;
    if (remaining <= 0) {
        window.location.reload();
    }
}, 1000);
</script>
@endpush
