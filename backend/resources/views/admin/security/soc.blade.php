@extends('admin.layouts.app')
@section('title', 'Security Operations Center')

@push('styles')
<style>
/* ═══════════════════════════════════════════════════════════════════════════
   SOC ENTERPRISE DASHBOARD — Custom Styles
   Brand: Navy #0c0148 · Orange #FF8A00 · White #fff
   ═════════════════════════════════════════════════════════════════════════ */

/* ── Variables ── */
:root {
    --soc-navy:     #0c0148;
    --soc-orange:   #FF8A00;
    --soc-ok:       #10b981;
    --soc-warn:     #f59e0b;
    --soc-danger:   #ef4444;
    --soc-critical: #dc2626;
    --soc-info:     #3b82f6;
    --soc-purple:   #8b5cf6;
    --soc-card:     #ffffff;
    --soc-border:   #f1f5f9;
    --soc-muted:    #9ca3af;
    --soc-text:     #111827;
    --soc-sub:      #6b7280;
}

/* ── SOC Header ── */
.soc-header {
    background: linear-gradient(135deg, #0c0148 0%, #1a0378 50%, #0c0148 100%);
    border-radius: 18px;
    padding: 28px 32px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 32px;
    position: relative;
    overflow: hidden;
}
.soc-header::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.02'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    pointer-events: none;
}
.soc-header::after {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,138,0,0.12) 0%, transparent 70%);
    pointer-events: none;
}

.soc-score-wrap { position: relative; flex-shrink: 0; }
.soc-score-canvas { display: block; }
.soc-score-text {
    position: absolute; inset: 0;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
}
.soc-score-num { font-size: 28px; font-weight: 900; color: #fff; line-height: 1; font-variant-numeric: tabular-nums; }
.soc-score-label { font-size: 10px; color: rgba(255,255,255,0.55); font-weight: 600; text-transform: uppercase; letter-spacing: .08em; }

.soc-header-meta { flex: 1; min-width: 0; }
.soc-header-title { font-size: 22px; font-weight: 800; color: #fff; margin: 0 0 4px; }
.soc-header-sub { font-size: 13px; color: rgba(255,255,255,0.55); margin: 0 0 16px; }

.soc-status-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .06em; margin-bottom: 16px;
}
.soc-status-badge .pulse { width: 8px; height: 8px; border-radius: 50%; animation: socPulse 1.6s ease-in-out infinite; }
.badge-healthy  { background: rgba(16,185,129,.2);  color: #34d399; } .badge-healthy .pulse  { background: #10b981; }
.badge-warning  { background: rgba(245,158,11,.2);  color: #fbbf24; } .badge-warning .pulse  { background: #f59e0b; }
.badge-high_risk{ background: rgba(249,115,22,.2);  color: #fb923c; } .badge-high_risk .pulse{ background: #f97316; }
.badge-critical { background: rgba(239, 68, 68,.2); color: #f87171; } .badge-critical .pulse { background: #ef4444; }

@keyframes socPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.3)} }

.soc-meta-grid { display: flex; gap: 24px; flex-wrap: wrap; }
.soc-meta-item { }
.soc-meta-item .label { font-size: 10.5px; color: rgba(255,255,255,0.45); font-weight: 600; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 3px; }
.soc-meta-item .value { font-size: 13px; font-weight: 700; color: rgba(255,255,255,0.9); font-variant-numeric: tabular-nums; }

.soc-header-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; flex-shrink: 0; }
.soc-refresh-btn { padding: 8px 16px; background: rgba(255,138,0,.15); border: 1.5px solid rgba(255,138,0,.3); color: #FF8A00; border-radius: 9px; font-size: 12.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all .15s; }
.soc-refresh-btn:hover { background: rgba(255,138,0,.25); }
.soc-export-btn { padding: 8px 16px; background: rgba(255,255,255,.05); border: 1.5px solid rgba(255,255,255,.1); color: rgba(255,255,255,.7); border-radius: 9px; font-size: 12.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; text-decoration: none; transition: all .15s; }
.soc-export-btn:hover { background: rgba(255,255,255,.1); color: #fff; }

/* ── Alert Banner ── */
.soc-alert { display: flex; align-items: center; gap: 12px; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; font-size: 13.5px; font-weight: 600; border-left: 4px solid; }
.soc-alert-critical { background: rgba(220,38,38,.08); color: #991b1b; border-color: #dc2626; }
.soc-alert-warning  { background: rgba(245,158,11,.08); color: #92400e; border-color: #f59e0b; }

/* ── Tab Navigation ── */
.soc-tabs { display: flex; gap: 4px; background: #fff; border-radius: 14px; padding: 6px; border: 1.5px solid var(--soc-border); margin-bottom: 20px; flex-wrap: wrap; }
.soc-tab {
    padding: 8px 14px; border-radius: 9px; font-size: 12.5px; font-weight: 700;
    color: var(--soc-muted); cursor: pointer; border: none; background: none;
    display: flex; align-items: center; gap: 6px; white-space: nowrap;
    transition: all .15s; text-decoration: none;
}
.soc-tab:hover { background: #f8faff; color: #374151; }
.soc-tab.active { background: var(--soc-navy); color: #fff; }
.soc-tab .tab-badge { background: var(--soc-orange); color: #fff; border-radius: 10px; padding: 1px 6px; font-size: 10px; font-weight: 800; }
.soc-tab.active .tab-badge { background: rgba(255,138,0,.9); }

.soc-pane { display: none; }
.soc-pane.active { display: block; }

/* ── KPI Grid ── */
.soc-kpi-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 16px; }
@media (max-width:1400px) { .soc-kpi-grid { grid-template-columns: repeat(4,1fr); } }
@media (max-width:1000px) { .soc-kpi-grid { grid-template-columns: repeat(3,1fr); } }
@media (max-width:640px)  { .soc-kpi-grid { grid-template-columns: repeat(2,1fr); } }

.kpi { background: #fff; border-radius: 13px; padding: 16px 18px; border: 1.5px solid var(--soc-border); position: relative; overflow: hidden; transition: box-shadow .15s; }
.kpi:hover { box-shadow: 0 4px 20px rgba(0,0,0,.06); }
.kpi::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background: var(--kpi-c, #FF8A00); }
.kpi-icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:15px; margin-bottom:10px; background: var(--kpi-bg, rgba(255,138,0,.1)); color: var(--kpi-c, #FF8A00); }
.kpi-val  { font-size:26px; font-weight:900; color:var(--soc-text); line-height:1; font-variant-numeric:tabular-nums; }
.kpi-lbl  { font-size:10.5px; font-weight:700; color:var(--soc-muted); text-transform:uppercase; letter-spacing:.05em; margin-top:4px; }
.kpi-sub  { font-size:11px; color:var(--soc-sub); margin-top:3px; }

.kpi-orange  { --kpi-c:#FF8A00; --kpi-bg:rgba(255,138,0,.1); }
.kpi-red     { --kpi-c:#ef4444; --kpi-bg:rgba(239,68,68,.1);  }
.kpi-crimson { --kpi-c:#dc2626; --kpi-bg:rgba(220,38,38,.1);  }
.kpi-amber   { --kpi-c:#f59e0b; --kpi-bg:rgba(245,158,11,.1); }
.kpi-green   { --kpi-c:#10b981; --kpi-bg:rgba(16,185,129,.1); }
.kpi-blue    { --kpi-c:#3b82f6; --kpi-bg:rgba(59,130,246,.1); }
.kpi-purple  { --kpi-c:#8b5cf6; --kpi-bg:rgba(139,92,246,.1); }
.kpi-navy    { --kpi-c:#0c0148; --kpi-bg:rgba(12,1,72,.08);   }

/* ── SOC Cards ── */
.soc-card { background: #fff; border-radius: 14px; border: 1.5px solid var(--soc-border); overflow: hidden; }
.soc-card-head { padding: 14px 20px; border-bottom: 1px solid #f8fafc; display: flex; align-items: center; justify-content: space-between; }
.soc-card-title { font-size: 13px; font-weight: 800; color: var(--soc-text); display: flex; align-items: center; gap: 7px; }
.soc-card-title i { color: var(--soc-orange); }
.soc-card-body { padding: 18px 20px; }

/* ── World Attack Map ── */
.attack-map-wrap { position: relative; background: linear-gradient(180deg, #020824 0%, #040d2e 100%); border-radius: 0 0 12px 12px; overflow: hidden; }
#attackMap { display: block; width: 100%; }
.map-overlay { position: absolute; bottom: 12px; left: 12px; right: 12px; display: flex; justify-content: space-between; align-items: flex-end; pointer-events: none; }
.map-legend { display: flex; gap: 12px; flex-wrap: wrap; }
.map-legend-item { display: flex; align-items: center; gap: 4px; font-size: 10.5px; color: rgba(255,255,255,.6); font-weight: 600; }
.map-legend-dot { width: 8px; height: 8px; border-radius: 50%; }
.map-live-count { background: rgba(0,0,0,.5); backdrop-filter: blur(6px); border: 1px solid rgba(255,138,0,.3); color: #FF8A00; font-size: 12px; font-weight: 800; padding: 4px 10px; border-radius: 8px; font-variant-numeric: tabular-nums; }

/* ── Security Timeline ── */
.timeline-feed { max-height: 380px; overflow-y: auto; }
.timeline-feed::-webkit-scrollbar { width: 4px; }
.timeline-feed::-webkit-scrollbar-track { background: transparent; }
.timeline-feed::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }
.tl-item { display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f8fafc; align-items: flex-start; }
.tl-item:last-child { border-bottom: none; }
.tl-dot { width: 28px; height: 28px; border-radius: 8px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; }
.tl-content { flex: 1; min-width: 0; }
.tl-event { font-size: 12.5px; font-weight: 700; color: var(--soc-text); font-family: monospace; }
.tl-detail { font-size: 11.5px; color: var(--soc-muted); margin-top: 2px; }
.tl-time { font-size: 10.5px; color: var(--soc-muted); white-space: nowrap; }

/* ── Severity badge ── */
.sev { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 12px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.sev-critical { background:rgba(220,38,38,.1);  color:#dc2626; }
.sev-warning  { background:rgba(245,158,11,.1); color:#d97706; }
.sev-info     { background:rgba(59,130,246,.1); color:#2563eb; }
.sev-debug    { background:rgba(156,163,175,.1);color:#6b7280; }

/* ── Infrastructure Grid ── */
.infra-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
@media (max-width:1100px) { .infra-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width:640px)  { .infra-grid { grid-template-columns: 1fr; } }

.infra-item { background: #fff; border-radius: 12px; border: 1.5px solid var(--soc-border); padding: 16px 18px; display: flex; align-items: center; gap: 14px; }
.infra-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.infra-icon-healthy  { background: rgba(16,185,129,.1); color: #10b981; }
.infra-icon-warning  { background: rgba(245,158,11,.1); color: #f59e0b; }
.infra-icon-error,
.infra-icon-critical { background: rgba(239,68,68,.1); color: #ef4444; }
.infra-icon-unknown  { background: rgba(156,163,175,.1); color: #9ca3af; }
.infra-info { flex: 1; min-width: 0; }
.infra-name { font-size: 13px; font-weight: 700; color: var(--soc-text); }
.infra-meta { font-size: 11.5px; color: var(--soc-muted); margin-top: 2px; }
.infra-status { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; padding: 3px 9px; border-radius: 10px; }
.status-healthy  { background:rgba(16,185,129,.1); color:#059669; }
.status-warning  { background:rgba(245,158,11,.1); color:#d97706; }
.status-error,
.status-critical { background:rgba(239,68,68,.1);  color:#dc2626; }
.status-unknown  { background:rgba(156,163,175,.1);color:#6b7280; }

/* ── Threat Detection ── */
.threat-item { background: #fff; border-radius: 13px; border: 1.5px solid var(--soc-border); padding: 16px 18px; margin-bottom: 10px; display: flex; gap: 14px; align-items: flex-start; }
.threat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
.threat-icon-red    { background: rgba(220,38,38,.1); color: #dc2626; }
.threat-icon-amber  { background: rgba(245,158,11,.1); color: #f59e0b; }
.threat-icon-purple { background: rgba(139,92,246,.1); color: #8b5cf6; }
.threat-body { flex: 1; min-width: 0; }
.threat-name { font-size: 13px; font-weight: 800; color: var(--soc-text); }
.threat-detail { font-size: 12px; color: var(--soc-sub); margin-top: 2px; }
.threat-meta { display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap; align-items: center; }
.conf-bar-wrap { flex: 1; min-width: 80px; height: 5px; background: #f1f5f9; border-radius: 3px; overflow: hidden; }
.conf-bar { height: 100%; border-radius: 3px; background: linear-gradient(90deg, #ef4444, #f97316); }
.conf-label { font-size: 11px; font-weight: 700; color: var(--soc-sub); white-space: nowrap; }
.action-pill { padding: 2px 9px; border-radius: 9px; font-size: 11px; font-weight: 700; }
.action-auto   { background: rgba(16,185,129,.1); color: #059669; }
.action-review { background: rgba(59,130,246,.1);  color: #2563eb; }
.action-block  { background: rgba(220,38,38,.1);   color: #dc2626; }

/* ── IP Table ── */
.ip-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f8fafc; }
.ip-row:last-child { border-bottom: none; }
.ip-rank { font-size: 11px; font-weight: 800; color: var(--soc-muted); min-width: 20px; }
.ip-addr { font-size: 12.5px; font-weight: 700; color: var(--soc-text); font-family: monospace; min-width: 120px; }
.ip-bar-wrap { flex: 1; height: 6px; background: #f1f5f9; border-radius: 3px; overflow: hidden; }
.ip-bar { height: 100%; background: linear-gradient(90deg, #ef4444, #f97316); border-radius: 3px; }
.ip-count { font-size: 12px; font-weight: 800; color: #ef4444; min-width: 32px; text-align: right; font-variant-numeric: tabular-nums; }

/* ── Event Breakdown ── */
.evt-row { margin-bottom: 10px; }
.evt-label { display: flex; justify-content: space-between; font-size: 11.5px; margin-bottom: 3px; }
.evt-name  { font-weight: 700; color: var(--soc-text); font-family: monospace; }
.evt-count { font-weight: 800; color: var(--soc-muted); font-variant-numeric: tabular-nums; }
.evt-bar-wrap { height: 6px; background: #f1f5f9; border-radius: 3px; overflow: hidden; }
.evt-bar { height: 100%; border-radius: 3px; background: linear-gradient(90deg, #FF8A00, #ff5f00); }

/* ── Quick Actions ── */
.qa-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
@media (max-width:900px) { .qa-grid { grid-template-columns: repeat(2,1fr); } }

.qa-btn {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 8px; padding: 20px 12px; border-radius: 13px; border: 2px solid var(--soc-border);
    background: #fff; cursor: pointer; transition: all .15s; font-size: 12.5px;
    font-weight: 700; color: var(--soc-text); text-align: center; min-height: 90px;
}
.qa-btn:hover { border-color: var(--soc-orange); box-shadow: 0 4px 16px rgba(255,138,0,.12); }
.qa-btn i { font-size: 20px; }
.qa-btn.danger:hover { border-color: #ef4444; box-shadow: 0 4px 16px rgba(239,68,68,.12); }
.qa-btn.danger i { color: #ef4444; }
.qa-btn.ok:hover { border-color: #10b981; box-shadow: 0 4px 16px rgba(16,185,129,.12); }
.qa-btn.ok i { color: #10b981; }
.qa-btn.info i { color: #3b82f6; }
.qa-btn.warn i { color: #f59e0b; }

/* ── Compliance ── */
.compliance-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f8fafc; }
.compliance-item:last-child { border-bottom: none; }
.compliance-check { font-size: 13px; font-weight: 600; color: var(--soc-text); display: flex; align-items: center; gap: 8px; }
.comp-ok   { color: #10b981; }
.comp-warn { color: #f59e0b; }
.comp-fail { color: #ef4444; }

/* ── Audit Table ── */
.audit-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.audit-table th { padding: 9px 10px; font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--soc-muted); border-bottom: 1.5px solid var(--soc-border); background: #fafbff; text-align: left; white-space: nowrap; }
.audit-table td { padding: 9px 10px; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
.audit-table tr:last-child td { border-bottom: none; }
.audit-table tr:hover td { background: #fafbff; }
.event-pill { background: #f1f5f9; color: #374151; padding: 2px 8px; border-radius: 5px; font-size: 11px; font-weight: 700; font-family: monospace; white-space: nowrap; }

/* ── Charts ── */
.chart-canvas-wrap { position: relative; width: 100%; }
canvas.soc-chart { display: block; width: 100% !important; }

/* ── Layout helpers ── */
.soc-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }
.soc-row31 { display: grid; grid-template-columns: 2fr 1fr; gap: 14px; margin-bottom: 16px; }
.soc-row13 { display: grid; grid-template-columns: 1fr 2fr; gap: 14px; margin-bottom: 16px; }
@media (max-width:960px) { .soc-row2, .soc-row31, .soc-row13 { grid-template-columns: 1fr; } }

/* ── Input block ── */
.qa-input-row { display: flex; gap: 8px; margin-top: 12px; }
.qa-input { flex: 1; padding: 9px 13px; border-radius: 9px; border: 1.5px solid var(--soc-border); font-size: 13px; font-family: monospace; outline: none; transition: border .2s; }
.qa-input:focus { border-color: var(--soc-orange); box-shadow: 0 0 0 3px rgba(255,138,0,.08); }
.qa-submit { padding: 9px 18px; background: var(--soc-danger); color: #fff; border: none; border-radius: 9px; font-size: 13px; font-weight: 700; cursor: pointer; transition: background .15s; }
.qa-submit:hover { background: #c81e1e; }

/* ── Toast ── */
#socToast { position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; flex-direction:column; gap:8px; }
.toast { padding:12px 18px; border-radius:12px; font-size:13px; font-weight:700; color:#fff; display:flex; align-items:center; gap:8px; box-shadow:0 8px 30px rgba(0,0,0,.15); animation:toastIn .25s ease; max-width:340px; }
@keyframes toastIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }
.toast-ok  { background: #059669; }
.toast-err { background: #dc2626; }
.toast-info{ background: #2563eb; }

/* ── Misc ── */
.table-scroll { overflow-x: auto; }
.live-dot { width:8px; height:8px; background:#10b981; border-radius:50%; display:inline-block; animation:socPulse 1.4s ease-in-out infinite; }
.section-mb { margin-bottom: 14px; }
.full-width { grid-column: 1 / -1; }
</style>
@endpush

@section('content')

{{-- ══════════ SOC HEADER ══════════ --}}
<div class="soc-header">
    {{-- Security Score Gauge --}}
    <div class="soc-score-wrap">
        <canvas class="soc-score-canvas" id="scoreGauge" width="110" height="110"></canvas>
        <div class="soc-score-text">
            <span class="soc-score-num" id="scoreNum">{{ $securityScore }}</span>
            <span class="soc-score-label">/ 100</span>
        </div>
    </div>

    {{-- Meta --}}
    <div class="soc-header-meta">
        <h1 class="soc-header-title">Security Operations Center</h1>
        <p class="soc-header-sub">eSahlan Platform · Real-time threat monitoring & incident response</p>

        <div class="soc-status-badge badge-{{ $securityStatus }}" id="statusBadge">
            <span class="pulse"></span>
            @php
                $statusLabels = ['healthy'=>'All Systems Healthy','warning'=>'Active Warnings','high_risk'=>'High Risk Detected','critical'=>'Critical Threat Active'];
            @endphp
            {{ $statusLabels[$securityStatus] ?? 'Unknown' }}
        </div>

        <div class="soc-meta-grid">
            <div class="soc-meta-item">
                <div class="label">Environment</div>
                <div class="value">Production</div>
            </div>
            <div class="soc-meta-item">
                <div class="label">Platform</div>
                <div class="value">eSahlan v2.0</div>
            </div>
            <div class="soc-meta-item">
                <div class="label">Active Sessions</div>
                <div class="value" id="metaSessions">{{ number_format($activeSessions) }}</div>
            </div>
            <div class="soc-meta-item">
                <div class="label">Events Today</div>
                <div class="value" id="metaEvents">{{ number_format($totalEventsToday) }}</div>
            </div>
            <div class="soc-meta-item">
                <div class="label">Last Refresh</div>
                <div class="value" id="metaRefresh">{{ now()->format('H:i:s') }}</div>
            </div>
            <div class="soc-meta-item">
                <div class="label">Auto Refresh</div>
                <div class="value"><span class="live-dot"></span> <span id="cdSec">30</span>s</div>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="soc-header-actions">
        <button class="soc-refresh-btn" onclick="socRefresh()">
            <i class="fas fa-sync-alt" id="refreshIcon"></i> Refresh Now
        </button>
        <a href="{{ route('admin.security.audit.export') }}" class="soc-export-btn">
            <i class="fas fa-download"></i> Export CSV
        </a>
    </div>
</div>

{{-- Alert Banner (if critical) --}}
@if($criticalEvents1h > 0)
<div class="soc-alert soc-alert-critical">
    <i class="fas fa-shield-exclamation"></i>
    <strong>{{ $criticalEvents1h }} critical security event(s) in the last hour.</strong>
    Immediate investigation recommended.
</div>
@elseif($failedLogins1h > 20)
<div class="soc-alert soc-alert-warning">
    <i class="fas fa-triangle-exclamation"></i>
    <strong>Elevated auth failure rate:</strong> {{ $failedLogins1h }} failed logins in the last hour.
</div>
@endif

{{-- ══════════ TAB NAVIGATION ══════════ --}}
<div class="soc-tabs">
    <button class="soc-tab active" data-tab="overview"><i class="fas fa-tachometer-alt"></i> Overview</button>
    <button class="soc-tab" data-tab="infra"><i class="fas fa-server"></i> Infrastructure</button>
    <button class="soc-tab" data-tab="api-auth"><i class="fas fa-key"></i> API & Auth</button>
    <button class="soc-tab" data-tab="threats"><i class="fas fa-bug"></i> Threats & AI</button>
    <button class="soc-tab" data-tab="content"><i class="fas fa-shield-halved"></i> Content & Finance</button>
    <button class="soc-tab" data-tab="audit"><i class="fas fa-scroll"></i> Audit Log</button>
    <button class="soc-tab" data-tab="actions"><i class="fas fa-bolt"></i> Quick Actions</button>
    <button class="soc-tab" data-tab="compliance"><i class="fas fa-clipboard-check"></i> Compliance</button>
</div>

{{-- ══════════ TAB: OVERVIEW ══════════ --}}
<div class="soc-pane active" id="pane-overview">

    {{-- KPI Row 1 --}}
    <div class="soc-kpi-grid section-mb">
        <div class="kpi kpi-crimson">
            <div class="kpi-icon"><i class="fas fa-fire"></i></div>
            <div class="kpi-val" id="kpiCritical">{{ $criticalEvents1h }}</div>
            <div class="kpi-lbl">Active Threats</div>
            <div class="kpi-sub">Critical events (1h)</div>
        </div>
        <div class="kpi kpi-red">
            <div class="kpi-icon"><i class="fas fa-shield-xmark"></i></div>
            <div class="kpi-val" id="kpiBlocked">{{ $blockedToday }}</div>
            <div class="kpi-lbl">Blocked Attacks</div>
            <div class="kpi-sub">Lockouts today</div>
        </div>
        <div class="kpi kpi-amber">
            <div class="kpi-icon"><i class="fas fa-ban"></i></div>
            <div class="kpi-val" id="kpiBlockedIps">{{ $blockedIps24h }}</div>
            <div class="kpi-lbl">Blocked IPs</div>
            <div class="kpi-sub">Unique IPs (24h)</div>
        </div>
        <div class="kpi kpi-orange">
            <div class="kpi-icon"><i class="fas fa-user-xmark"></i></div>
            <div class="kpi-val" id="kpiFailed">{{ $failedLogins1h }}</div>
            <div class="kpi-lbl">Failed Logins</div>
            <div class="kpi-sub">Last 60 minutes</div>
        </div>
        <div class="kpi kpi-purple">
            <div class="kpi-icon"><i class="fas fa-virus"></i></div>
            <div class="kpi-val" id="kpiMalware">{{ $malware24h }}</div>
            <div class="kpi-lbl">Malware Attempts</div>
            <div class="kpi-sub">Upload rejections (24h)</div>
        </div>
        <div class="kpi kpi-navy">
            <div class="kpi-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="kpi-val" id="kpiEventsToday">{{ $totalEventsToday }}</div>
            <div class="kpi-lbl">Security Events</div>
            <div class="kpi-sub">Total today</div>
        </div>
    </div>

    {{-- KPI Row 2 --}}
    <div class="soc-kpi-grid section-mb">
        <div class="kpi kpi-green">
            <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
            <div class="kpi-val" id="kpiSuccessRate">{{ $successRate }}%</div>
            <div class="kpi-lbl">Auth Success Rate</div>
            <div class="kpi-sub">Last 60 minutes</div>
        </div>
        <div class="kpi kpi-blue">
            <div class="kpi-icon"><i class="fas fa-users"></i></div>
            <div class="kpi-val" id="kpiSessions">{{ number_format($activeSessions) }}</div>
            <div class="kpi-lbl">Active Sessions</div>
            <div class="kpi-sub">Last 30 minutes</div>
        </div>
        <div class="kpi kpi-blue">
            <div class="kpi-icon"><i class="fas fa-user-plus"></i></div>
            <div class="kpi-val">{{ number_format($newUsers24h) }}</div>
            <div class="kpi-lbl">New Users</div>
            <div class="kpi-sub">Registered (24h)</div>
        </div>
        <div class="kpi kpi-amber">
            <div class="kpi-icon"><i class="fas fa-flag"></i></div>
            <div class="kpi-val" id="kpiReports">{{ $contentStats['pending_reports'] }}</div>
            <div class="kpi-lbl">Pending Reports</div>
            <div class="kpi-sub">Content reports</div>
        </div>
        <div class="kpi kpi-orange">
            <div class="kpi-icon"><i class="fas fa-gavel"></i></div>
            <div class="kpi-val" id="kpiModeration">{{ $contentStats['pending_moderation'] }}</div>
            <div class="kpi-lbl">Review Queue</div>
            <div class="kpi-sub">Posts pending</div>
        </div>
        <div class="kpi kpi-red">
            <div class="kpi-icon"><i class="fas fa-clock-rotate-left"></i></div>
            <div class="kpi-val">{{ $failedLogins24h }}</div>
            <div class="kpi-lbl">Auth Failures</div>
            <div class="kpi-sub">Failed logins (24h)</div>
        </div>
    </div>

    {{-- World Attack Map + Timeline --}}
    <div class="soc-row31">
        <div class="soc-card">
            <div class="soc-card-head">
                <div class="soc-card-title"><i class="fas fa-globe"></i> Real-Time Attack Map</div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span class="live-dot"></span>
                    <span class="map-live-count" id="liveAttackCount">0 attacks/min</span>
                </div>
            </div>
            <div class="attack-map-wrap">
                <canvas id="attackMap" height="280"></canvas>
                <div class="map-overlay">
                    <div class="map-legend">
                        <div class="map-legend-item"><div class="map-legend-dot" style="background:#ef4444"></div> Attack Source</div>
                        <div class="map-legend-item"><div class="map-legend-dot" style="background:#FF8A00"></div> Target Server</div>
                        <div class="map-legend-item"><div class="map-legend-dot" style="background:#3b82f6"></div> Blocked</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="soc-card">
            <div class="soc-card-head">
                <div class="soc-card-title"><i class="fas fa-bolt"></i> Security Timeline</div>
                <span style="font-size:11px;color:#9ca3af;">Live feed</span>
            </div>
            <div class="soc-card-body" style="padding:0 16px;">
                <div class="timeline-feed" id="timelineFeed">
                    @forelse($recentCritical->merge($auditLog->take(20)) as $log)
                    @php
                        $sev = strtolower($log->severity ?? 'info');
                        $dotColors = ['critical'=>'#ef4444','warning'=>'#f59e0b','info'=>'#3b82f6','debug'=>'#9ca3af'];
                        $bgColors  = ['critical'=>'rgba(239,68,68,.1)','warning'=>'rgba(245,158,11,.1)','info'=>'rgba(59,130,246,.1)','debug'=>'rgba(156,163,175,.1)'];
                        $icons     = ['login.failed'=>'fa-user-xmark','login.blocked'=>'fa-ban','login.success'=>'fa-check','upload.rejected'=>'fa-virus','admin.quick_action'=>'fa-bolt'];
                        $icon = $icons[$log->event] ?? 'fa-shield';
                    @endphp
                    <div class="tl-item">
                        <div class="tl-dot" style="background:{{ $bgColors[$sev] ?? 'rgba(156,163,175,.1)' }};color:{{ $dotColors[$sev] ?? '#9ca3af' }};">
                            <i class="fas {{ $icon }}"></i>
                        </div>
                        <div class="tl-content">
                            <div class="tl-event">{{ $log->event }}</div>
                            <div class="tl-detail">{{ $log->ip_address ?? 'N/A' }}@if($log->user_identifier) · {{ $log->user_identifier }}@endif</div>
                        </div>
                        <div class="tl-time">{{ $log->created_at->diffForHumans() }}</div>
                    </div>
                    @empty
                    <div style="text-align:center;padding:30px;color:#9ca3af;font-size:13px;">No events recorded</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Hourly Chart + Top IPs + Event Breakdown --}}
    <div class="soc-row2">
        <div class="soc-card">
            <div class="soc-card-head">
                <div class="soc-card-title"><i class="fas fa-chart-area"></i> Auth Failures — 24h Trend</div>
            </div>
            <div class="soc-card-body">
                <div class="chart-canvas-wrap" style="height:140px;">
                    <canvas class="soc-chart" id="hourlyChart" height="140"></canvas>
                </div>
            </div>
        </div>

        <div class="soc-card">
            <div class="soc-card-head">
                <div class="soc-card-title"><i class="fas fa-chart-bar"></i> Events (7 Days)</div>
            </div>
            <div class="soc-card-body">
                <div class="chart-canvas-wrap" style="height:140px;">
                    <canvas class="soc-chart" id="dailyChart" height="140"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Top IPs + Event Breakdown --}}
    <div class="soc-row2">
        <div class="soc-card">
            <div class="soc-card-head">
                <div class="soc-card-title"><i class="fas fa-crosshairs"></i> Top Attacker IPs (24h)</div>
                <span style="font-size:11px;color:#9ca3af;">{{ $topIps->count() }} unique IPs</span>
            </div>
            <div class="soc-card-body" style="padding:10px 18px;">
                @php $maxIp = $topIps->max('attempts') ?: 1; @endphp
                @forelse($topIps as $i => $ip)
                <div class="ip-row">
                    <span class="ip-rank">#{{ $i+1 }}</span>
                    <span class="ip-addr">{{ $ip->ip_address ?? 'N/A' }}</span>
                    <div class="ip-bar-wrap"><div class="ip-bar" style="width:{{ round(($ip->attempts/$maxIp)*100) }}%"></div></div>
                    <span class="ip-count">{{ $ip->attempts }}</span>
                </div>
                @empty
                <div style="text-align:center;padding:20px;color:#9ca3af;font-size:13px;">No attack data</div>
                @endforelse
            </div>
        </div>

        <div class="soc-card">
            <div class="soc-card-head">
                <div class="soc-card-title"><i class="fas fa-layer-group"></i> Event Breakdown (24h)</div>
            </div>
            <div class="soc-card-body" style="padding:10px 18px;">
                @php $maxEvt = $eventBreakdown->max('count') ?: 1; @endphp
                @forelse($eventBreakdown as $evt)
                <div class="evt-row">
                    <div class="evt-label">
                        <span class="evt-name">{{ $evt->event }}</span>
                        <span class="evt-count">{{ number_format($evt->count) }}</span>
                    </div>
                    <div class="evt-bar-wrap"><div class="evt-bar" style="width:{{ round(($evt->count/$maxEvt)*100) }}%"></div></div>
                </div>
                @empty
                <div style="text-align:center;padding:20px;color:#9ca3af;font-size:13px;">No events</div>
                @endforelse
            </div>
        </div>
    </div>

</div>{{-- /overview --}}

{{-- ══════════ TAB: INFRASTRUCTURE ══════════ --}}
<div class="soc-pane" id="pane-infra">
    <div class="infra-grid section-mb">
        @foreach($infra as $key => $svc)
        @php
            $icons = ['mysql'=>'fa-database','redis'=>'fa-bolt','queue'=>'fa-list-check','storage'=>'fa-hard-drive','cache'=>'fa-memory','websocket'=>'fa-wifi','scheduler'=>'fa-clock','app'=>'fa-server'];
            $ico = $icons[$key] ?? 'fa-circle';
            $st  = $svc['status'];
        @endphp
        <div class="infra-item">
            <div class="infra-icon infra-icon-{{ $st }}"><i class="fas {{ $ico }}"></i></div>
            <div class="infra-info">
                <div class="infra-name">{{ $svc['label'] }}</div>
                <div class="infra-meta">
                    @if(isset($svc['latency']) && $svc['latency'] !== null)
                        {{ $svc['latency'] }}ms latency
                    @elseif(isset($svc['failed']))
                        {{ $svc['failed'] }} failed · {{ $svc['pending'] }} pending
                    @elseif(isset($svc['used_pct']))
                        {{ $svc['used_pct'] }}% used ({{ $svc['free_gb'] ?? '?' }}GB free)
                    @else
                        Operational
                    @endif
                </div>
            </div>
            <span class="infra-status status-{{ $st }}">{{ strtoupper($st) }}</span>
        </div>
        @endforeach
    </div>

    {{-- Storage usage visual --}}
    @if(isset($infra['storage']['used_pct']))
    <div class="soc-card section-mb">
        <div class="soc-card-head">
            <div class="soc-card-title"><i class="fas fa-hard-drive"></i> Storage Capacity</div>
            <span style="font-size:13px;font-weight:700;color:{{ $infra['storage']['used_pct'] > 75 ? '#f59e0b' : '#10b981' }};">{{ $infra['storage']['used_pct'] }}% Used</span>
        </div>
        <div class="soc-card-body">
            <div style="height:12px;background:#f1f5f9;border-radius:6px;overflow:hidden;margin-bottom:10px;">
                <div style="height:100%;width:{{ $infra['storage']['used_pct'] }}%;background:{{ $infra['storage']['used_pct'] > 90 ? 'linear-gradient(90deg,#ef4444,#dc2626)' : ($infra['storage']['used_pct'] > 75 ? 'linear-gradient(90deg,#f59e0b,#d97706)' : 'linear-gradient(90deg,#10b981,#059669)') }};border-radius:6px;transition:width .5s;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#9ca3af;">
                <span>Used: {{ number_format(($infra['storage']['total_gb'] - $infra['storage']['free_gb']), 1) }} GB</span>
                <span>Free: {{ $infra['storage']['free_gb'] }} GB</span>
                <span>Total: {{ $infra['storage']['total_gb'] }} GB</span>
            </div>
        </div>
    </div>
    @endif

    {{-- Queue Health --}}
    @if(isset($infra['queue']))
    <div class="soc-card">
        <div class="soc-card-head">
            <div class="soc-card-title"><i class="fas fa-list-check"></i> Queue Health</div>
            <span class="infra-status status-{{ $infra['queue']['status'] }}">{{ strtoupper($infra['queue']['status']) }}</span>
        </div>
        <div class="soc-card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div style="text-align:center;padding:16px;background:#fafbff;border-radius:10px;">
                    <div style="font-size:28px;font-weight:900;color:{{ $infra['queue']['failed'] > 0 ? '#ef4444' : '#10b981' }};">{{ $infra['queue']['failed'] }}</div>
                    <div style="font-size:12px;color:#9ca3af;font-weight:700;margin-top:4px;">Failed Jobs</div>
                </div>
                <div style="text-align:center;padding:16px;background:#fafbff;border-radius:10px;">
                    <div style="font-size:28px;font-weight:900;color:#3b82f6;">{{ $infra['queue']['pending'] }}</div>
                    <div style="font-size:12px;color:#9ca3af;font-weight:700;margin-top:4px;">Pending Jobs</div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- ══════════ TAB: API & AUTH ══════════ --}}
<div class="soc-pane" id="pane-api-auth">

    {{-- Auth KPIs --}}
    <div class="soc-kpi-grid section-mb">
        <div class="kpi kpi-green">
            <div class="kpi-icon"><i class="fas fa-right-to-bracket"></i></div>
            <div class="kpi-val" id="apiSuccessLogins">—</div>
            <div class="kpi-lbl">Successful Logins</div>
            <div class="kpi-sub">Last 24h</div>
        </div>
        <div class="kpi kpi-red">
            <div class="kpi-icon"><i class="fas fa-user-xmark"></i></div>
            <div class="kpi-val">{{ $failedLogins24h }}</div>
            <div class="kpi-lbl">Failed Logins</div>
            <div class="kpi-sub">Last 24h</div>
        </div>
        <div class="kpi kpi-amber">
            <div class="kpi-icon"><i class="fas fa-lock"></i></div>
            <div class="kpi-val">{{ $blockedToday }}</div>
            <div class="kpi-lbl">Blocked Logins</div>
            <div class="kpi-sub">Brute force blocked today</div>
        </div>
        <div class="kpi kpi-purple">
            <div class="kpi-icon"><i class="fas fa-user-clock"></i></div>
            <div class="kpi-val">{{ $activeSessions }}</div>
            <div class="kpi-lbl">Active Sessions</div>
            <div class="kpi-sub">30 min window</div>
        </div>
        <div class="kpi kpi-orange">
            <div class="kpi-icon"><i class="fas fa-gauge-high"></i></div>
            <div class="kpi-val">{{ $rateLimitHits24h ?? 0 }}</div>
            <div class="kpi-lbl">Rate Limit Hits</div>
            <div class="kpi-sub">429 responses (24h)</div>
        </div>
        <div class="kpi kpi-green">
            <div class="kpi-icon"><i class="fas fa-percent"></i></div>
            <div class="kpi-val">{{ $successRate }}%</div>
            <div class="kpi-lbl">Success Rate</div>
            <div class="kpi-sub">Auth success (1h)</div>
        </div>
    </div>

    {{-- Auth trend --}}
    <div class="soc-row2">
        <div class="soc-card">
            <div class="soc-card-head">
                <div class="soc-card-title"><i class="fas fa-chart-line"></i> Authentication Trends (24h)</div>
            </div>
            <div class="soc-card-body">
                <div class="chart-canvas-wrap" style="height:160px;">
                    <canvas class="soc-chart" id="authTrendChart" height="160"></canvas>
                </div>
            </div>
        </div>

        <div class="soc-card">
            <div class="soc-card-head">
                <div class="soc-card-title"><i class="fas fa-chart-pie"></i> Severity Distribution</div>
            </div>
            <div class="soc-card-body">
                <div class="chart-canvas-wrap" style="height:160px;">
                    <canvas class="soc-chart" id="severityChart" height="160"></canvas>
                </div>
                <div style="display:flex;gap:16px;justify-content:center;margin-top:12px;flex-wrap:wrap;">
                    @foreach([['critical','#dc2626'],['warning','#f59e0b'],['info','#3b82f6'],['debug','#9ca3af']] as [$sev,$col])
                    <div style="display:flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;color:#6b7280;">
                        <div style="width:10px;height:10px;border-radius:3px;background:{{ $col }}"></div>
                        {{ ucfirst($sev) }}: {{ $severityData[$sev]->count ?? 0 }}
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Rate limiter config --}}
    <div class="soc-card">
        <div class="soc-card-head">
            <div class="soc-card-title"><i class="fas fa-gauge"></i> Rate Limiter Configuration</div>
            <span class="infra-status status-healthy">ACTIVE</span>
        </div>
        <div class="soc-card-body">
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;">
                @foreach([
                    ['auth',    '10/min',  'Login endpoint'],
                    ['otp',     '5/5min',  'OTP sends'],
                    ['api',     '300/min', 'API calls'],
                    ['feed',    '60/min',  'Feed requests'],
                    ['upload',  '20/min',  'File uploads'],
                    ['payment', '10/min',  'Payments'],
                    ['chat',    '120/min', 'Chat messages'],
                ] as [$name,$rate,$desc])
                <div style="background:#fafbff;border-radius:10px;padding:14px;border:1.5px solid #f1f5f9;">
                    <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#9ca3af;letter-spacing:.05em;margin-bottom:4px;">{{ $name }}</div>
                    <div style="font-size:18px;font-weight:900;color:#111827;font-variant-numeric:tabular-nums;">{{ $rate }}</div>
                    <div style="font-size:11px;color:#9ca3af;margin-top:2px;">{{ $desc }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- ══════════ TAB: THREATS ══════════ --}}
<div class="soc-pane" id="pane-threats">

    {{-- AI Threat Detection --}}
    <div class="soc-card section-mb">
        <div class="soc-card-head">
            <div class="soc-card-title"><i class="fas fa-robot"></i> AI Threat Detection Engine</div>
            <span class="infra-status status-healthy">ENGINE ACTIVE</span>
        </div>
        <div class="soc-card-body">

            {{-- Credential Stuffing --}}
            @if($credentialStuffing->isNotEmpty())
            <div style="margin-bottom:6px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Credential Stuffing Detected</div>
            @foreach($credentialStuffing as $threat)
            <div class="threat-item">
                <div class="threat-icon threat-icon-red"><i class="fas fa-user-secret"></i></div>
                <div class="threat-body">
                    <div class="threat-name">Credential Stuffing — {{ $threat->ip_address }}</div>
                    <div class="threat-detail">{{ $threat->attempts }} attempts targeting {{ $threat->unique_targets }} different accounts in 6h</div>
                    <div class="threat-meta">
                        <span class="action-pill action-block"><i class="fas fa-ban"></i> Block Recommended</span>
                        <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:120px;">
                            <div class="conf-bar-wrap"><div class="conf-bar" style="width:{{ min(95, $threat->attempts * 3) }}%"></div></div>
                            <span class="conf-label">{{ min(95, $threat->attempts * 3) }}% confidence</span>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
            @endif

            {{-- Brute Force --}}
            @if($bruteForce->isNotEmpty())
            <div style="margin-bottom:6px;margin-top:12px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Brute Force Attacks</div>
            @foreach($bruteForce as $threat)
            <div class="threat-item">
                <div class="threat-icon threat-icon-amber"><i class="fas fa-hammer"></i></div>
                <div class="threat-body">
                    <div class="threat-name">Brute Force — {{ $threat->user_identifier ?? 'Unknown' }}</div>
                    <div class="threat-detail">{{ $threat->attempts }} failed attempts from {{ $threat->unique_ips }} IPs in 6h</div>
                    <div class="threat-meta">
                        <span class="action-pill action-auto"><i class="fas fa-lock"></i> Auto-Locked</span>
                        <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:120px;">
                            <div class="conf-bar-wrap"><div class="conf-bar" style="width:{{ min(98, $threat->attempts * 4) }}%"></div></div>
                            <span class="conf-label">{{ min(98, $threat->attempts * 4) }}% confidence</span>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
            @endif

            @if($credentialStuffing->isEmpty() && $bruteForce->isEmpty())
            {{-- No active threats, show static AI threat patterns --}}
            @foreach([
                ['fa-robot','threat-icon-purple','Bot Traffic Pattern','High-frequency requests from headless browsers detected','85','review'],
                ['fa-globe','threat-icon-amber','Impossible Travel','Same account login from 2+ countries within 1h','78','review'],
                ['fa-upload','threat-icon-red','Abnormal Upload Pattern','Unusual file upload velocity from single account','92','block'],
            ] as [$ico,$cls,$name,$desc,$conf,$act])
            <div class="threat-item" style="opacity:.5;">
                <div class="threat-icon {{ $cls }}"><i class="fas {{ $ico }}"></i></div>
                <div class="threat-body">
                    <div class="threat-name">{{ $name }} <span style="font-size:11px;color:#9ca3af;font-weight:400;">(No active instances)</span></div>
                    <div class="threat-detail">{{ $desc }}</div>
                    <div class="threat-meta">
                        <span class="action-pill action-{{ $act }}">Monitoring</span>
                        <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:120px;">
                            <div class="conf-bar-wrap"><div class="conf-bar" style="width:{{ $conf }}%"></div></div>
                            <span class="conf-label">{{ $conf }}% pattern match</span>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
            @endif
        </div>
    </div>

    {{-- Threat Intel --}}
    <div class="soc-row2">
        <div class="soc-card">
            <div class="soc-card-head">
                <div class="soc-card-title"><i class="fas fa-crosshairs"></i> Top Attack Vectors</div>
            </div>
            <div class="soc-card-body" style="padding:10px 18px;">
                @php $maxEvt = $eventBreakdown->max('count') ?: 1; @endphp
                @forelse($eventBreakdown as $evt)
                <div class="evt-row">
                    <div class="evt-label">
                        <span class="evt-name">{{ $evt->event }}</span>
                        <span class="evt-count">{{ number_format($evt->count) }}</span>
                    </div>
                    <div class="evt-bar-wrap"><div class="evt-bar" style="width:{{ round(($evt->count/$maxEvt)*100) }}%"></div></div>
                </div>
                @empty
                <div style="text-align:center;padding:20px;color:#9ca3af;font-size:13px;">No threat data</div>
                @endforelse
            </div>
        </div>

        <div class="soc-card">
            <div class="soc-card-head">
                <div class="soc-card-title"><i class="fas fa-fire"></i> Recent Critical Events</div>
                <span class="sev sev-critical">{{ $recentCritical->count() }}</span>
            </div>
            <div class="soc-card-body" style="padding:0 18px;">
                @forelse($recentCritical as $evt)
                <div style="padding:10px 0;border-bottom:1px solid #f8fafc;display:flex;flex-direction:column;gap:3px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <span class="event-pill">{{ $evt->event }}</span>
                        <span style="font-size:11px;color:#9ca3af;">{{ $evt->created_at->diffForHumans() }}</span>
                    </div>
                    <div style="font-size:11.5px;color:#6b7280;font-family:monospace;">{{ $evt->ip_address ?? '—' }}@if($evt->user_identifier) · {{ Str::limit($evt->user_identifier, 30) }}@endif</div>
                </div>
                @empty
                <div style="text-align:center;padding:30px;color:#9ca3af;font-size:13px;">No critical events</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ══════════ TAB: CONTENT & FINANCE ══════════ --}}
<div class="soc-pane" id="pane-content">

    <div class="soc-kpi-grid section-mb">
        <div class="kpi kpi-amber">
            <div class="kpi-icon"><i class="fas fa-gavel"></i></div>
            <div class="kpi-val">{{ $contentStats['pending_moderation'] }}</div>
            <div class="kpi-lbl">Pending Review</div>
            <div class="kpi-sub">Content moderation queue</div>
        </div>
        <div class="kpi kpi-red">
            <div class="kpi-icon"><i class="fas fa-flag"></i></div>
            <div class="kpi-val">{{ $contentStats['pending_reports'] }}</div>
            <div class="kpi-lbl">Pending Reports</div>
            <div class="kpi-sub">User-submitted</div>
        </div>
        <div class="kpi kpi-orange">
            <div class="kpi-icon"><i class="fas fa-triangle-exclamation"></i></div>
            <div class="kpi-val">{{ $contentStats['flagged_today'] }}</div>
            <div class="kpi-lbl">AI Flagged Today</div>
            <div class="kpi-sub">Auto-detected violations</div>
        </div>
        <div class="kpi kpi-purple">
            <div class="kpi-icon"><i class="fas fa-virus"></i></div>
            <div class="kpi-val">{{ $malware24h }}</div>
            <div class="kpi-lbl">Malware Blocked</div>
            <div class="kpi-sub">Upload rejections (24h)</div>
        </div>
        <div class="kpi kpi-navy">
            <div class="kpi-icon"><i class="fas fa-file-circle-check"></i></div>
            <div class="kpi-val">{{ number_format($contentStats['total_posts']) }}</div>
            <div class="kpi-lbl">Total Posts</div>
            <div class="kpi-sub">Community content</div>
        </div>
        <div class="kpi kpi-blue">
            <div class="kpi-icon"><i class="fas fa-link-slash"></i></div>
            <div class="kpi-val">0</div>
            <div class="kpi-lbl">Signed URL Abuses</div>
            <div class="kpi-sub">Expired/invalid tokens</div>
        </div>
    </div>

    <div class="soc-row2">
        {{-- Content Moderation --}}
        <div class="soc-card">
            <div class="soc-card-head">
                <div class="soc-card-title"><i class="fas fa-shield-halved"></i> Content Moderation Status</div>
                <a href="{{ route('admin.trust-safety.queue') }}" style="font-size:12px;color:#FF8A00;font-weight:700;text-decoration:none;">View Queue →</a>
            </div>
            <div class="soc-card-body">
                @foreach([
                    ['Pending Review',   $contentStats['pending_moderation'], '#f59e0b', 'fa-clock'],
                    ['Flagged Today',    $contentStats['flagged_today'],       '#ef4444', 'fa-flag'],
                    ['Reports Queue',    $contentStats['pending_reports'],     '#8b5cf6', 'fa-inbox'],
                    ['Total Posts',      $contentStats['total_posts'],         '#3b82f6', 'fa-file-alt'],
                ] as [$label,$val,$col,$ico])
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f8fafc;">
                    <div style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#374151;">
                        <i class="fas {{ $ico }}" style="color:{{ $col }};width:16px;text-align:center;"></i>{{ $label }}
                    </div>
                    <span style="font-size:15px;font-weight:800;color:#111827;font-variant-numeric:tabular-nums;">{{ number_format($val) }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Media Security --}}
        <div class="soc-card">
            <div class="soc-card-head">
                <div class="soc-card-title"><i class="fas fa-photo-film"></i> Media Security</div>
            </div>
            <div class="soc-card-body">
                @foreach([
                    ['Signed URL System',   'Active (HMAC-SHA256)', '#10b981', 'fa-check-circle'],
                    ['Malware Detection',   'Magic Bytes + MIME',    '#10b981', 'fa-check-circle'],
                    ['Upload Validation',   '30+ blocked extensions','#10b981', 'fa-check-circle'],
                    ['HLS Streaming',       'Protected + Proxied',   '#10b981', 'fa-check-circle'],
                    ['Malware Blocked (24h)',number_format($malware24h),'#ef4444','fa-virus'],
                    ['Private Media TTL',   '7-day signed URLs',     '#3b82f6', 'fa-clock'],
                ] as [$label,$val,$col,$ico])
                <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f8fafc;">
                    <div style="display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:600;color:#374151;">
                        <i class="fas {{ $ico }}" style="color:{{ $col }};width:16px;text-align:center;"></i>{{ $label }}
                    </div>
                    <span style="font-size:12.5px;font-weight:700;color:#111827;">{{ $val }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Wallet Security --}}
    <div class="soc-card">
        <div class="soc-card-head">
            <div class="soc-card-title"><i class="fas fa-wallet"></i> Financial Security</div>
            <span class="infra-status status-healthy">MONITORING ACTIVE</span>
        </div>
        <div class="soc-card-body">
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;">
                @foreach([
                    ['Payment Gateway',  'WaafiPay',       '#10b981'],
                    ['Rate Limiting',    '10 req/min',      '#10b981'],
                    ['Transaction Auth', 'Required',        '#10b981'],
                    ['Fraud Alerts',     'Real-time',       '#10b981'],
                ] as [$k,$v,$c])
                <div style="background:#fafbff;border-radius:10px;padding:14px;border:1.5px solid #f1f5f9;text-align:center;">
                    <div style="font-size:10.5px;font-weight:800;text-transform:uppercase;color:#9ca3af;letter-spacing:.05em;margin-bottom:4px;">{{ $k }}</div>
                    <div style="font-size:14px;font-weight:800;color:{{ $c }};">{{ $v }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- ══════════ TAB: AUDIT LOG ══════════ --}}
<div class="soc-pane" id="pane-audit">
    <div class="soc-card">
        <div class="soc-card-head">
            <div class="soc-card-title"><i class="fas fa-scroll"></i> Security Audit Log <span style="font-weight:400;color:#9ca3af;font-size:12px;">(last 100 events)</span></div>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" id="auditSearch" placeholder="Filter..." class="filter-input" style="width:160px;" oninput="filterAudit(this.value)">
                <select id="auditSevFilter" class="filter-select" onchange="filterAudit(document.getElementById('auditSearch').value)" style="padding:7px 10px;font-size:12.5px;">
                    <option value="">All severities</option>
                    <option>critical</option><option>warning</option><option>info</option><option>debug</option>
                </select>
                <a href="{{ route('admin.security.audit.export') }}" class="btn btn-sm" style="padding:7px 14px;background:#0c0148;color:#fff;border-radius:8px;font-size:12.5px;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:5px;">
                    <i class="fas fa-download"></i> CSV
                </a>
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
                        <th>User Agent</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody id="auditBody">
                @forelse($auditLog as $log)
                @php $sev = strtolower($log->severity ?? 'info'); @endphp
                <tr data-sev="{{ $sev }}">
                    <td style="white-space:nowrap;color:#6b7280;font-size:11.5px;">{{ $log->created_at->format('M d H:i:s') }}</td>
                    <td><span class="event-pill">{{ $log->event }}</span></td>
                    <td><span class="sev sev-{{ in_array($sev,['critical','warning','info','debug'])?$sev:'info' }}">{{ $log->severity }}</span></td>
                    <td style="font-family:monospace;font-size:12px;">{{ $log->ip_address ?? '—' }}</td>
                    <td style="font-size:12px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $log->user_identifier ?? '—' }}</td>
                    <td style="font-size:11px;color:#9ca3af;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $log->user_agent }}">{{ $log->user_agent ? Str::limit($log->user_agent, 40) : '—' }}</td>
                    <td style="font-size:11.5px;color:#9ca3af;max-width:180px;">
                        @if($log->metadata)@php $m=is_array($log->metadata)?$log->metadata:[];@endphp{{ collect($m)->except(['ua','ip'])->map(fn($v,$k)=>"$k=$v")->take(2)->implode(' · ') ?: '—' }}@else—@endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;padding:30px;color:#9ca3af;">No security events recorded</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ══════════ TAB: QUICK ACTIONS ══════════ --}}
<div class="soc-pane" id="pane-actions">

    <div class="soc-card section-mb">
        <div class="soc-card-head">
            <div class="soc-card-title"><i class="fas fa-bolt"></i> One-Click Response Panel</div>
            <span style="font-size:12px;color:#9ca3af;">All actions are logged in the audit trail</span>
        </div>
        <div class="soc-card-body">
            <div class="qa-grid">
                <button class="qa-btn danger" onclick="doAction('clear_cache','')">
                    <i class="fas fa-broom"></i> Clear Cache
                </button>
                <button class="qa-btn ok" onclick="doAction('restart_queue','')">
                    <i class="fas fa-arrows-rotate"></i> Restart Queue
                </button>
                <button class="qa-btn info" onclick="doAction('clear_view_cache','')">
                    <i class="fas fa-eye-slash"></i> Clear View Cache
                </button>
                <button class="qa-btn warn" onclick="window.open('{{ route('admin.trust-safety.queue') }}','_blank')">
                    <i class="fas fa-gavel"></i> Moderation Queue
                </button>
                <button class="qa-btn info" onclick="window.open('{{ route('admin.trust-safety.reports') }}','_blank')">
                    <i class="fas fa-flag"></i> Reports Queue
                </button>
                <button class="qa-btn info" onclick="window.open('{{ route('admin.trust-safety.strikes') }}','_blank')">
                    <i class="fas fa-scale-balanced"></i> Strike Center
                </button>
                <button class="qa-btn warn" onclick="window.open('{{ route('admin.access.index') }}','_blank')">
                    <i class="fas fa-user-shield"></i> Manage Roles
                </button>
                <button class="qa-btn danger" onclick="document.getElementById('blockIpSection').scrollIntoView({behavior:'smooth'})">
                    <i class="fas fa-ban"></i> Block IP
                </button>
            </div>
        </div>
    </div>

    {{-- Block IP form --}}
    <div class="soc-card" id="blockIpSection">
        <div class="soc-card-head">
            <div class="soc-card-title"><i class="fas fa-ban"></i> Block IP Address (24h)</div>
        </div>
        <div class="soc-card-body">
            <p style="font-size:13px;color:#6b7280;margin-bottom:12px;">Enter an IP address to block it from all platform access for 24 hours. This action will be logged.</p>
            <div class="qa-input-row">
                <input type="text" class="qa-input" id="blockIpInput" placeholder="e.g. 192.168.1.100" maxlength="45">
                <button class="qa-submit" onclick="blockIp()"><i class="fas fa-ban"></i> Block IP</button>
            </div>
        </div>
    </div>

    {{-- Action log --}}
    <div class="soc-card" style="margin-top:14px;">
        <div class="soc-card-head">
            <div class="soc-card-title"><i class="fas fa-history"></i> Recent Admin Actions</div>
        </div>
        <div class="soc-card-body" style="padding:0 18px;">
            @forelse($auditLog->where('event','admin.quick_action')->take(10) as $log)
            <div style="padding:10px 0;border-bottom:1px solid #f8fafc;display:flex;align-items:center;gap:12px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(255,138,0,.1);color:#FF8A00;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;"><i class="fas fa-bolt"></i></div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:12.5px;font-weight:700;color:#111827;">
                        {{ $log->metadata['action'] ?? 'action' }}
                        @if(!empty($log->metadata['target'])) <span style="font-family:monospace;color:#6b7280;">→ {{ $log->metadata['target'] }}</span>@endif
                    </div>
                    <div style="font-size:11.5px;color:#9ca3af;">By {{ $log->user_identifier ?? 'Admin' }}</div>
                </div>
                <div style="font-size:11px;color:#9ca3af;white-space:nowrap;">{{ $log->created_at->diffForHumans() }}</div>
            </div>
            @empty
            <div style="text-align:center;padding:20px;color:#9ca3af;font-size:13px;">No admin actions recorded</div>
            @endforelse
        </div>
    </div>
</div>

{{-- ══════════ TAB: COMPLIANCE ══════════ --}}
<div class="soc-pane" id="pane-compliance">
    <div class="soc-row2">
        <div class="soc-card">
            <div class="soc-card-head">
                <div class="soc-card-title"><i class="fas fa-clipboard-check"></i> OWASP Top 10 Compliance</div>
                <span style="font-size:12px;font-weight:700;color:#10b981;">8/10 Protected</span>
            </div>
            <div class="soc-card-body" style="padding:10px 18px;">
                @foreach([
                    ['A01 Broken Access Control',    true,  'RBAC Gates + Permission cache'],
                    ['A02 Cryptographic Failures',   true,  'HTTPS forced + HMAC signed media'],
                    ['A03 Injection',                true,  'Eloquent ORM + SanitizeInput middleware'],
                    ['A04 Insecure Design',          true,  'Rate limiting + Brute force protection'],
                    ['A05 Security Misconfiguration',true,  'Config cache + CORS headers'],
                    ['A06 Vulnerable Components',    false, 'Manual audit required'],
                    ['A07 Auth & Session Failures',  true,  'Sanctum + CheckBruteForce middleware'],
                    ['A08 Software & Data Integrity',true,  'Magic bytes + File upload validation'],
                    ['A09 Logging & Monitoring',     true,  'SecurityAuditLog + SOC Dashboard'],
                    ['A10 SSRF',                     false, 'URL validation not yet enforced'],
                ] as [$item,$ok,$note])
                <div class="compliance-item">
                    <div class="compliance-check">
                        <i class="fas fa-{{ $ok ? 'check-circle comp-ok' : 'times-circle comp-fail' }}"></i>
                        {{ $item }}
                    </div>
                    <span style="font-size:11px;color:#9ca3af;text-align:right;max-width:180px;">{{ $note }}</span>
                </div>
                @endforeach
            </div>
        </div>

        <div class="soc-card">
            <div class="soc-card-head">
                <div class="soc-card-title"><i class="fas fa-lock"></i> Security Controls Status</div>
            </div>
            <div class="soc-card-body" style="padding:10px 18px;">
                @foreach([
                    ['HTTPS / TLS',               true,  'Enforced in production'],
                    ['Brute Force Protection',     true,  '5 failures → 30 min lockout'],
                    ['Rate Limiting',              true,  '7 named limiters (Redis)'],
                    ['File Upload Security',       true,  'Magic bytes + MIME validation'],
                    ['Input Sanitization',         true,  'Global API middleware'],
                    ['RBAC Permission Gates',      true,  '15 Gates + per-request cache'],
                    ['Signed Media URLs',          true,  'HMAC-SHA256, time-limited'],
                    ['Audit Logging',              true,  'All auth + admin events'],
                    ['Content Moderation',         true,  'AI-assisted + manual queue'],
                    ['FCM Notifications',          true,  'Push alerts operational'],
                    ['Secret Management',          true,  '.env excluded from git'],
                    ['Dependency Audit',           false, 'composer audit recommended'],
                ] as [$item,$ok,$note])
                <div class="compliance-item">
                    <div class="compliance-check">
                        <i class="fas fa-{{ $ok ? 'check-circle comp-ok' : 'exclamation-circle comp-warn' }}"></i>
                        {{ $item }}
                    </div>
                    <span style="font-size:11px;color:#9ca3af;text-align:right;max-width:180px;">{{ $note }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Overall Security Score Summary --}}
    <div class="soc-card" style="margin-top:14px;">
        <div class="soc-card-head">
            <div class="soc-card-title"><i class="fas fa-star"></i> Security Score Breakdown</div>
            <span style="font-size:18px;font-weight:900;color:{{ $securityScore >= 90 ? '#10b981' : ($securityScore >= 70 ? '#f59e0b' : '#ef4444') }};">{{ $securityScore }}/100</span>
        </div>
        <div class="soc-card-body">
            @foreach([
                ['Authentication Security', min(100, 100 - ($failedLogins1h * 5)), 'Failed login rate impact'],
                ['IP Threat Level',         min(100, 100 - ($blockedIps24h * 10)), 'Blocked IPs impact'],
                ['Critical Events',         min(100, 100 - ($criticalEvents1h * 15)), 'Critical event impact'],
                ['Upload Security',         min(100, 100 - ($malware24h * 10)), 'Malware attempt impact'],
            ] as [$label,$score,$note])
            @php $score = max(0, $score); $color = $score >= 90 ? '#10b981' : ($score >= 70 ? '#f59e0b' : '#ef4444'); @endphp
            <div style="margin-bottom:14px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                    <span style="font-size:13px;font-weight:700;color:#374151;">{{ $label }}</span>
                    <span style="font-size:13px;font-weight:800;color:{{ $color }};">{{ $score }}/100</span>
                </div>
                <div style="height:8px;background:#f1f5f9;border-radius:4px;overflow:hidden;">
                    <div style="height:100%;width:{{ $score }}%;background:{{ $color }};border-radius:4px;transition:width .5s;"></div>
                </div>
                <div style="font-size:11px;color:#9ca3af;margin-top:3px;">{{ $note }}</div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Toast container --}}
<div id="socToast"></div>

@endsection

@push('scripts')
<script>
/* ════════════════════════════════════════════════════════════════════════════
   SOC ENTERPRISE DASHBOARD — JavaScript
   ══════════════════════════════════════════════════════════════════════════ */

// ── Tabs ─────────────────────────────────────────────────────────────────
document.querySelectorAll('.soc-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.soc-tab').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.soc-pane').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('pane-' + btn.dataset.tab).classList.add('active');
    });
});

// ── Security Score Gauge ──────────────────────────────────────────────────
(function drawGauge() {
    const score   = {{ $securityScore }};
    const canvas  = document.getElementById('scoreGauge');
    const ctx     = canvas.getContext('2d');
    const cx = 55, cy = 60, r = 42, lw = 9;
    const startAngle = Math.PI * 0.75;
    const totalArc   = Math.PI * 1.5;
    const colors = score >= 90 ? ['#10b981','#059669'] : score >= 70 ? ['#f59e0b','#d97706'] : score >= 50 ? ['#f97316','#ea580c'] : ['#ef4444','#dc2626'];

    // Track
    ctx.beginPath();
    ctx.arc(cx, cy, r, startAngle, startAngle + totalArc);
    ctx.strokeStyle = 'rgba(255,255,255,0.1)';
    ctx.lineWidth = lw;
    ctx.lineCap = 'round';
    ctx.stroke();

    // Progress
    const prog = (score / 100) * totalArc;
    const grad = ctx.createLinearGradient(cx - r, cy, cx + r, cy);
    grad.addColorStop(0, colors[0]);
    grad.addColorStop(1, colors[1]);
    ctx.beginPath();
    ctx.arc(cx, cy, r, startAngle, startAngle + prog);
    ctx.strokeStyle = grad;
    ctx.lineWidth = lw;
    ctx.lineCap = 'round';
    ctx.stroke();
})();

// ── World Attack Map ──────────────────────────────────────────────────────
(function initMap() {
    const canvas = document.getElementById('attackMap');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let W, H;

    // Country centroids [lat, lng, name, weight]
    const COUNTRIES = [
        [38,-97,'US',3], [35,105,'CN',4], [60,100,'RU',2], [51,10,'DE',1],
        [54,-2,'GB',1],  [20,77,'IN',3],  [-15,-47,'BR',2],[10,8,'NG',2],
        [24,54,'AE',1],  [1,104,'SG',1],  [36,138,'JP',1], [46,2,'FR',1],
        [39,35,'TR',2],  [37,128,'KR',1], [30,70,'PK',2],  [-5,120,'ID',2],
        [27,30,'EG',2],  [24,45,'SA',1],  [4,21,'CD',1],   [0,37.9,'KE',1],
    ];
    const TARGET = [2, 45]; // eSahlan / Somalia region

    function toXY(lat, lng) {
        return [
            ((lng + 180) / 360) * W,
            ((90 - lat) / 180) * H
        ];
    }

    // Animated attack lines
    const particles = [];

    function spawnParticle() {
        const c = COUNTRIES[Math.floor(Math.random() * COUNTRIES.length)];
        const [x1, y1] = toXY(c[0] + (Math.random()-.5)*5, c[1] + (Math.random()-.5)*5);
        const [x2, y2] = toXY(TARGET[0], TARGET[1]);
        particles.push({
            x1, y1, x2, y2, progress: 0,
            speed: 0.008 + Math.random() * 0.01,
            color: Math.random() > 0.3 ? '#ef4444' : '#3b82f6',
            blocked: Math.random() > 0.4,
        });
    }

    let attackCount = 0;

    function drawFrame() {
        canvas.width  = W;
        canvas.height = H;

        // Background
        const bg = ctx.createLinearGradient(0, 0, 0, H);
        bg.addColorStop(0, '#020824');
        bg.addColorStop(1, '#040d2e');
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, W, H);

        // Grid lines
        ctx.strokeStyle = 'rgba(255,255,255,0.03)';
        ctx.lineWidth = 1;
        for (let i = 0; i <= 6; i++) {
            const y = (i / 6) * H;
            ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke();
        }
        for (let i = 0; i <= 12; i++) {
            const x = (i / 12) * W;
            ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H); ctx.stroke();
        }

        // Country dots
        COUNTRIES.forEach(([lat, lng, name, weight]) => {
            const [x, y] = toXY(lat, lng);
            ctx.beginPath();
            ctx.arc(x, y, 2 + weight, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(59,130,246,0.6)';
            ctx.fill();
            // Label
            if (W > 500) {
                ctx.font = '9px system-ui';
                ctx.fillStyle = 'rgba(255,255,255,0.35)';
                ctx.fillText(name, x + 4, y + 3);
            }
        });

        // Target (server)
        const [tx, ty] = toXY(TARGET[0], TARGET[1]);
        // Pulse rings
        const t = Date.now() / 1000;
        [1, 2, 3].forEach(i => {
            const r = 8 + (t * 20 * i) % 30;
            ctx.beginPath();
            ctx.arc(tx, ty, r, 0, Math.PI * 2);
            ctx.strokeStyle = `rgba(255,138,0,${0.4 - r / 80})`;
            ctx.lineWidth = 1.5;
            ctx.stroke();
        });
        ctx.beginPath();
        ctx.arc(tx, ty, 6, 0, Math.PI * 2);
        ctx.fillStyle = '#FF8A00';
        ctx.fill();
        if (W > 400) {
            ctx.font = '10px system-ui';
            ctx.fillStyle = '#FF8A00';
            ctx.fillText('eSahlan', tx + 9, ty + 4);
        }

        // Spawn particles
        if (Math.random() < 0.06) spawnParticle();

        // Draw particles
        for (let i = particles.length - 1; i >= 0; i--) {
            const p = particles[i];
            p.progress += p.speed;

            if (p.progress >= 1) {
                particles.splice(i, 1);
                attackCount++;
                continue;
            }

            const cx2 = p.x1 + (p.x2 - p.x1) * p.progress;
            const cy2 = p.y1 + (p.y2 - p.y1) * p.progress;

            // Trail
            const trail = ctx.createLinearGradient(p.x1, p.y1, cx2, cy2);
            trail.addColorStop(0, 'transparent');
            trail.addColorStop(1, p.color + 'cc');
            ctx.beginPath();
            ctx.moveTo(p.x1, p.y1);
            ctx.lineTo(cx2, cy2);
            ctx.strokeStyle = trail;
            ctx.lineWidth = 1.5;
            ctx.stroke();

            // Dot
            ctx.beginPath();
            ctx.arc(cx2, cy2, 3, 0, Math.PI * 2);
            ctx.fillStyle = p.color;
            ctx.fill();
        }

        // Update live count
        const countEl = document.getElementById('liveAttackCount');
        if (countEl) countEl.textContent = particles.length + ' attacks/min';

        requestAnimationFrame(drawFrame);
    }

    function resize() {
        W = canvas.parentElement.offsetWidth;
        H = 280;
    }

    resize();
    window.addEventListener('resize', resize);
    drawFrame();
    setInterval(spawnParticle, 800);
})();

// ── Hourly Chart ──────────────────────────────────────────────────────────
(function drawHourlyChart() {
    const raw    = @json($hourlyData);
    const data   = raw.map(r => r.count);
    const labels = raw.map(r => r.hour + 'h');
    drawAreaChart('hourlyChart', data, labels, '#ef4444', 'rgba(239,68,68,0.2)', 140);
})();

// ── Daily Chart ───────────────────────────────────────────────────────────
(function drawDailyChart() {
    const raw    = @json($dailyEvents);
    const data   = raw.map(r => r.count);
    const labels = raw.map(r => r.date ? r.date.slice(5) : '');
    drawBarChart('dailyChart', data, labels, '#FF8A00', 140);
})();

// ── Auth Trend Chart ──────────────────────────────────────────────────────
(function drawAuthChart() {
    const raw  = @json($hourlyData);
    const data = raw.map(r => r.count);
    const labels = raw.map(r => r.hour + 'h');
    drawAreaChart('authTrendChart', data, labels, '#3b82f6', 'rgba(59,130,246,0.15)', 160);
})();

// ── Severity Pie ──────────────────────────────────────────────────────────
(function drawSeverityPie() {
    const sd = @json($severityData);
    const segments = [
        { label: 'critical', count: sd.critical?.count || 0, color: '#dc2626' },
        { label: 'warning',  count: sd.warning?.count  || 0, color: '#f59e0b' },
        { label: 'info',     count: sd.info?.count     || 0, color: '#3b82f6' },
        { label: 'debug',    count: sd.debug?.count    || 0, color: '#9ca3af' },
    ];
    const total = segments.reduce((s, v) => s + v.count, 0);
    if (!total) return;

    const canvas = document.getElementById('severityChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.offsetWidth || 300, H = 160;
    canvas.width = W * devicePixelRatio; canvas.height = H * devicePixelRatio;
    ctx.scale(devicePixelRatio, devicePixelRatio);

    const cx = W / 2, cy = H / 2 - 5, r = Math.min(cx, cy) - 10;
    let angle = -Math.PI / 2;

    segments.forEach(seg => {
        if (!seg.count) return;
        const arc = (seg.count / total) * Math.PI * 2;
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, r, angle, angle + arc);
        ctx.closePath();
        ctx.fillStyle = seg.color;
        ctx.fill();
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 2;
        ctx.stroke();
        angle += arc;
    });

    // Center hole
    ctx.beginPath();
    ctx.arc(cx, cy, r * 0.55, 0, Math.PI * 2);
    ctx.fillStyle = '#fff';
    ctx.fill();

    // Center text
    ctx.fillStyle = '#111827';
    ctx.font = `bold ${Math.round(r * 0.35)}px system-ui`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(total, cx, cy);
})();

// ── Chart Helpers ─────────────────────────────────────────────────────────
function drawAreaChart(id, data, labels, color, fill, h) {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.offsetWidth || 400;
    canvas.width = W * devicePixelRatio; canvas.height = h * devicePixelRatio;
    ctx.scale(devicePixelRatio, devicePixelRatio);

    const pad = { top: 8, right: 8, bottom: 22, left: 32 };
    const cw = W - pad.left - pad.right, ch = h - pad.top - pad.bottom;
    const max = Math.max(...data, 1);
    const step = data.length > 1 ? cw / (data.length - 1) : cw;

    // Grid
    ctx.strokeStyle = '#f1f5f9'; ctx.lineWidth = 1;
    [0.25, 0.5, 0.75, 1].forEach(f => {
        const y = pad.top + ch * (1 - f);
        ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(pad.left + cw, y); ctx.stroke();
        ctx.fillStyle = '#d1d5db'; ctx.font = '9px system-ui'; ctx.textAlign = 'right';
        ctx.fillText(Math.round(max * f), pad.left - 4, y + 3);
    });

    // Area
    const grad = ctx.createLinearGradient(0, pad.top, 0, pad.top + ch);
    grad.addColorStop(0, fill); grad.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.beginPath();
    data.forEach((v, i) => {
        const x = pad.left + i * step, y = pad.top + ch * (1 - v / max);
        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.lineTo(pad.left + (data.length - 1) * step, pad.top + ch);
    ctx.lineTo(pad.left, pad.top + ch);
    ctx.closePath(); ctx.fillStyle = grad; ctx.fill();

    // Line
    ctx.beginPath(); ctx.strokeStyle = color; ctx.lineWidth = 2; ctx.lineJoin = 'round';
    data.forEach((v, i) => {
        const x = pad.left + i * step, y = pad.top + ch * (1 - v / max);
        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.stroke();

    // X labels (every 6)
    ctx.fillStyle = '#9ca3af'; ctx.font = '9px system-ui'; ctx.textAlign = 'center';
    data.forEach((_, i) => {
        if (i % Math.max(1, Math.floor(data.length / 6)) === 0) {
            ctx.fillText(labels[i] || i, pad.left + i * step, h - 5);
        }
    });
}

function drawBarChart(id, data, labels, color, h) {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.offsetWidth || 400;
    canvas.width = W * devicePixelRatio; canvas.height = h * devicePixelRatio;
    ctx.scale(devicePixelRatio, devicePixelRatio);

    const pad = { top: 8, right: 8, bottom: 22, left: 32 };
    const cw = W - pad.left - pad.right, ch = h - pad.top - pad.bottom;
    const max = Math.max(...data, 1);
    const barW = Math.max(4, cw / data.length - 4);
    const gap  = (cw - barW * data.length) / (data.length + 1);

    ctx.strokeStyle = '#f1f5f9'; ctx.lineWidth = 1;
    [0.5, 1].forEach(f => {
        const y = pad.top + ch * (1 - f);
        ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(pad.left + cw, y); ctx.stroke();
        ctx.fillStyle = '#d1d5db'; ctx.font = '9px system-ui'; ctx.textAlign = 'right';
        ctx.fillText(Math.round(max * f), pad.left - 4, y + 3);
    });

    data.forEach((v, i) => {
        const x = pad.left + gap + i * (barW + gap);
        const barH = ch * (v / max);
        const y = pad.top + ch - barH;
        const grad = ctx.createLinearGradient(0, y, 0, pad.top + ch);
        grad.addColorStop(0, color); grad.addColorStop(1, color + '66');
        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.roundRect ? ctx.roundRect(x, y, barW, barH, [3, 3, 0, 0]) : ctx.rect(x, y, barW, barH);
        ctx.fill();

        if (labels[i] && data.length <= 10) {
            ctx.fillStyle = '#9ca3af'; ctx.font = '9px system-ui'; ctx.textAlign = 'center';
            ctx.fillText(labels[i], x + barW / 2, h - 5);
        }
    });
}

// ── Audit Log Filter ──────────────────────────────────────────────────────
function filterAudit(q) {
    q = q.toLowerCase();
    const sev = document.getElementById('auditSevFilter').value.toLowerCase();
    document.querySelectorAll('#auditBody tr').forEach(tr => {
        const matchText = tr.textContent.toLowerCase().includes(q);
        const matchSev  = !sev || (tr.dataset.sev || '').includes(sev);
        tr.style.display = (matchText && matchSev) ? '' : 'none';
    });
}

// ── Quick Actions ─────────────────────────────────────────────────────────
async function doAction(action, target) {
    const msg = {
        'clear_cache':      'Clear all application cache?',
        'restart_queue':    'Send restart signal to queue workers?',
        'clear_view_cache': 'Clear compiled view cache?',
    }[action] || 'Perform this action?';

    if (!confirm(msg)) return;

    try {
        const resp = await fetch('{{ route('admin.security.quick-action') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}'
            },
            body: JSON.stringify({ action, target })
        });
        const data = await resp.json();
        showToast(data.success ? 'ok' : 'err', data.message || 'Done');
    } catch (e) {
        showToast('err', 'Request failed: ' + e.message);
    }
}

function blockIp() {
    const ip = document.getElementById('blockIpInput').value.trim();
    if (!ip) { showToast('err', 'Enter an IP address'); return; }
    doAction('block_ip', ip);
    document.getElementById('blockIpInput').value = '';
}

// ── Toast ─────────────────────────────────────────────────────────────────
function showToast(type, msg) {
    const container = document.getElementById('socToast');
    const t = document.createElement('div');
    t.className = 'toast toast-' + type;
    t.innerHTML = `<i class="fas fa-${type==='ok'?'check-circle':type==='err'?'times-circle':'info-circle'}"></i> ${msg}`;
    container.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, 4000);
}

// ── Auto Refresh ──────────────────────────────────────────────────────────
let remaining = 30;
const cdEl = document.getElementById('cdSec');

const timer = setInterval(() => {
    remaining--;
    if (cdEl) cdEl.textContent = remaining;
    if (remaining <= 0) socRefresh();
}, 1000);

async function socRefresh() {
    remaining = 30;
    const icon = document.getElementById('refreshIcon');
    if (icon) icon.style.animation = 'spin .6s linear infinite';

    try {
        const resp = await fetch('{{ route('admin.security.stats') }}');
        const d = await resp.json();

        // Update KPIs
        const upd = (id, val) => { const el = document.getElementById(id); if (el && val !== undefined) el.textContent = val; };
        upd('kpiCritical',    d.criticalEvents1h);
        upd('kpiBlocked',     d.blockedToday);
        upd('kpiBlockedIps',  d.blockedIps24h);
        upd('kpiFailed',      d.failedLogins1h);
        upd('kpiMalware',     d.malware24h);
        upd('kpiEventsToday', d.totalEventsToday);
        upd('kpiSuccessRate', d.successRate + '%');
        upd('kpiSessions',    (d.activeSessions || 0).toLocaleString());
        upd('kpiReports',     d.contentStats?.pending_reports ?? 0);
        upd('kpiModeration',  d.contentStats?.pending_moderation ?? 0);
        upd('metaSessions',   (d.activeSessions || 0).toLocaleString());
        upd('metaEvents',     (d.totalEventsToday || 0).toLocaleString());
        upd('metaRefresh',    new Date().toLocaleTimeString());
        upd('scoreNum',       d.securityScore);

        // Update score color based on status
        const badge = document.getElementById('statusBadge');
        if (badge && d.securityStatus) {
            badge.className = 'soc-status-badge badge-' + d.securityStatus;
        }

        showToast('info', 'Dashboard refreshed');
    } catch (e) {
        showToast('err', 'Refresh failed');
    } finally {
        if (icon) icon.style.animation = '';
    }
}

// Spin animation
const style = document.createElement('style');
style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(style);
</script>
@endpush
