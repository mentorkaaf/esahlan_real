@extends('admin.layouts.app')

@section('title', 'Security Operations Center')

@push('styles')
<style>
/* ══ SOC Theme System ═══════════════════════════════════════════════ */
/* Default (light) tokens */
body{
  --ora:#E87200;--grn:#00a862;--red:#e53e3e;--ylw:#d69e00;--blu:#2b7de9;--pur:#7c3aed;
  --bg:#f0f4f8;--bg2:#ffffff;--bg3:#e8edf3;
  --gls:rgba(255,255,255,.88);--brd:rgba(0,0,0,.1);
  --txt:#1a202c;--txt2:#4a5568;--txt3:#a0aec0;
  --sb-bg:rgba(255,255,255,.97);--card-shadow:0 2px 12px rgba(0,0,0,.08);
  --tab-bg:#f0f4f8;--prog-track:rgba(0,0,0,.07);--scrl:rgba(0,0,0,.15);
}
/* Dark tokens */
body[data-soc-theme="dark"]{
  --ora:#FF8A00;--grn:#00d97e;--red:#ff4757;--ylw:#ffc800;--blu:#4d9fff;--pur:#a855f7;
  --bg:#06091a;--bg2:#0d1226;--bg3:#111830;
  --gls:rgba(255,255,255,.05);--brd:rgba(255,255,255,.09);
  --txt:rgba(255,255,255,.92);--txt2:rgba(255,255,255,.5);--txt3:rgba(255,255,255,.25);
  --sb-bg:rgba(6,9,26,.97);--card-shadow:0 4px 24px rgba(0,0,0,.35);
  --tab-bg:#06091a;--prog-track:rgba(255,255,255,.08);--scrl:rgba(255,255,255,.12);
}

/* Admin layout overrides — light */
.main-wrapper,.content-wrapper,.content{background:var(--bg)!important;color:var(--txt)!important}
.topbar{background:var(--sb-bg)!important;border-bottom:1px solid var(--brd)!important;box-shadow:none!important}
.topbar *{color:var(--txt)!important}
.breadcrumb,.breadcrumb-item,.breadcrumb-item a{color:var(--txt2)!important}
.breadcrumb-item.active{color:var(--txt3)!important}

/* ── Base ──────────────────────────────────────────────────────────── */
*{box-sizing:border-box;margin:0;padding:0}
#soc{font-family:'Inter',system-ui,sans-serif;font-size:14px;color:var(--txt);background:var(--bg);padding:0 0 60px;min-height:100vh;transition:background .3s,color .3s}

/* ── Glass card ─────────────────────────────────────────────────────── */
.g{background:var(--gls);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid var(--brd);border-radius:16px;box-shadow:var(--card-shadow)}
body[data-soc-theme="dark"] .g{background:linear-gradient(135deg,rgba(255,255,255,.065),rgba(255,255,255,.02));box-shadow:0 4px 24px rgba(0,0,0,.35),inset 0 1px 0 rgba(255,255,255,.06)}

/* ── Status bar ─────────────────────────────────────────────────────── */
#statusBar{position:sticky;top:0;z-index:200;background:var(--sb-bg);border-bottom:1px solid var(--brd);backdrop-filter:blur(16px);padding:0 24px;display:flex;align-items:center;gap:0;height:44px;overflow-x:auto}
.sb-item{display:flex;align-items:center;gap:6px;padding:0 14px;border-right:1px solid var(--brd);white-space:nowrap;font-size:12px;color:var(--txt2)}
.sb-item:last-child{border-right:none;margin-left:auto;font-size:11px;color:var(--txt3)}
.dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.dot-operational{background:var(--grn);box-shadow:0 0 6px var(--grn)}
.dot-degraded{background:var(--ylw);box-shadow:0 0 6px var(--ylw)}
.dot-outage{background:var(--red);box-shadow:0 0 6px var(--red)}
.dot-unknown{background:var(--txt3)}
.sb-platform{padding:0 14px;display:flex;align-items:center;gap:6px;font-size:11px;font-weight:600}
.sb-platform.operational{color:var(--grn)}.sb-platform.partial_outage{color:var(--ylw)}.sb-platform.major_incident{color:var(--red)}

/* ── Header ──────────────────────────────────────────────────────────── */
#socHead{padding:24px 24px 0;display:flex;align-items:flex-start;gap:24px;flex-wrap:wrap}
.soc-score-wrap{position:relative;width:120px;height:120px;flex-shrink:0}
.soc-score-wrap canvas{position:absolute;top:0;left:0}
.score-val{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px}
.score-num{font-size:30px;font-weight:700;letter-spacing:-1px;line-height:1}
.score-lbl{font-size:10px;color:var(--txt2);text-transform:uppercase;letter-spacing:1px}
.soc-meta{flex:1;min-width:200px}
.soc-title{font-size:22px;font-weight:700;letter-spacing:-.5px;display:flex;align-items:center;gap:10px}
.soc-badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.8px}
.badge-healthy{background:rgba(0,217,126,.15);color:var(--grn);border:1px solid rgba(0,217,126,.3)}
.badge-warning{background:rgba(255,200,0,.15);color:var(--ylw);border:1px solid rgba(255,200,0,.3)}
.badge-high_risk{background:rgba(255,100,0,.15);color:#ff7c00;border:1px solid rgba(255,100,0,.3)}
.badge-critical{background:rgba(255,71,87,.15);color:var(--red);border:1px solid rgba(255,71,87,.3)}
.soc-sub{margin-top:6px;color:var(--txt2);font-size:13px}
.soc-stats{display:flex;gap:24px;margin-top:12px;flex-wrap:wrap}
.soc-stat span:first-child{font-size:18px;font-weight:700;color:var(--txt)}
.soc-stat span:last-child{font-size:11px;color:var(--txt2);display:block}
.soc-actions{margin-left:auto;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.btn-soc{padding:9px 18px;border-radius:10px;font-size:13px;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:.2s;text-decoration:none}
.btn-ora{background:var(--ora);color:#fff}.btn-ora:hover{background:#e67800}
.btn-glass{background:var(--bg3);color:var(--txt);border:1px solid var(--brd)}.btn-glass:hover{background:var(--gls)}

/* ── Alert banner ────────────────────────────────────────────────────── */
#alertBanner{margin:16px 24px 0;padding:12px 18px;border-radius:12px;background:rgba(255,71,87,.1);border:1px solid rgba(255,71,87,.3);color:#ff6b7a;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:500}
#alertBanner i{font-size:16px;color:var(--red);animation:pulse 1.2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

/* ── Tabs ────────────────────────────────────────────────────────────── */
#tabNav{display:flex;gap:2px;padding:20px 24px 0;overflow-x:auto;border-bottom:1px solid var(--brd);position:sticky;top:44px;z-index:100;background:var(--tab-bg);padding-bottom:0;transition:background .3s}
.tab-btn{padding:10px 16px;border:none;background:none;color:var(--txt2);cursor:pointer;font-size:13px;font-weight:500;border-bottom:2px solid transparent;transition:.2s;white-space:nowrap;display:flex;align-items:center;gap:6px}
.tab-btn:hover{color:var(--txt)}
.tab-btn.active{color:var(--ora);border-bottom-color:var(--ora)}
.tab-badge{background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:1px 5px;border-radius:10px}
.tab-pane{display:none;padding:20px 24px;animation:fadeIn .2s ease}
.tab-pane.active{display:block}
@keyframes fadeIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}

/* ── KPI grid ────────────────────────────────────────────────────────── */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:20px}
.kpi{padding:18px 18px 14px;position:relative;overflow:hidden;transition:.2s}.kpi:hover{transform:translateY(-2px)}
.kpi-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;margin-bottom:12px}
.kpi-val{font-size:28px;font-weight:700;letter-spacing:-1px;line-height:1;margin-bottom:4px}
.kpi-lbl{font-size:11px;color:var(--txt2);text-transform:uppercase;letter-spacing:.8px}
.kpi-sub{font-size:11px;margin-top:6px}
.kpi-ok .kpi-icon{background:rgba(0,217,126,.15);color:var(--grn)}.kpi-ok .kpi-val{color:var(--grn)}
.kpi-ora .kpi-icon{background:rgba(255,138,0,.15);color:var(--ora)}.kpi-ora .kpi-val{color:var(--ora)}
.kpi-red .kpi-icon{background:rgba(255,71,87,.15);color:var(--red)}.kpi-red .kpi-val{color:var(--red)}
.kpi-ylw .kpi-icon{background:rgba(255,200,0,.15);color:var(--ylw)}.kpi-ylw .kpi-val{color:var(--ylw)}
.kpi-blu .kpi-icon{background:rgba(77,159,255,.15);color:var(--blu)}.kpi-blu .kpi-val{color:var(--blu)}
.kpi-pur .kpi-icon{background:rgba(168,85,247,.15);color:var(--pur)}.kpi-pur .kpi-val{color:var(--pur)}

/* ── Grid helpers ────────────────────────────────────────────────────── */
.g2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
.g4{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.gc{display:grid;grid-template-columns:2fr 1fr;gap:16px}
@media(max-width:900px){.g2,.g3,.g4,.gc{grid-template-columns:1fr}}

/* ── Section label ──────────────────────────────────────────────────── */
.s-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.2px;color:var(--txt3);margin-bottom:10px}
.s-head{font-size:14px;font-weight:600;color:var(--txt);margin-bottom:4px;display:flex;align-items:center;gap:8px}
.s-sub{font-size:12px;color:var(--txt2);margin-bottom:16px}

/* ── Map ─────────────────────────────────────────────────────────────── */
#mapWrap{position:relative;border-radius:14px;overflow:hidden;border:1px solid var(--brd);display:flex;flex-direction:column;flex:1}
#gmap{width:100%;flex:1;min-height:320px}
.map-legend{position:absolute;bottom:12px;left:12px;display:flex;gap:8px;flex-wrap:wrap;z-index:10}
.map-leg-dot{width:8px;height:8px;border-radius:50%}

/* ── Timeline ────────────────────────────────────────────────────────── */
.tl{display:flex;flex-direction:column;max-height:420px;overflow-y:auto}
.tl::-webkit-scrollbar{width:3px}.tl::-webkit-scrollbar-track{background:transparent}.tl::-webkit-scrollbar-thumb{background:var(--brd);border-radius:2px}
.tl-item{display:flex;align-items:stretch;gap:0;padding:0;border-bottom:1px solid var(--brd);position:relative;transition:background .15s}
.tl-item:hover{background:var(--bg3)}
.tl-item.tl-new{animation:tlFlash .6s ease}
@keyframes tlFlash{0%{background:rgba(255,138,0,.18)}100%{background:transparent}}
.tl-bar{width:3px;flex-shrink:0;border-radius:0}
.tl-inner{display:flex;align-items:flex-start;gap:10px;padding:10px 12px;flex:1;min-width:0}
.tl-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;margin-top:1px}
.tl-body{flex:1;min-width:0}
.tl-top{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.tl-event{font-size:11.5px;font-weight:600;color:var(--txt);font-family:monospace;letter-spacing:.2px}
.tl-sev{font-size:9px;font-weight:700;padding:1px 6px;border-radius:4px;letter-spacing:.5px;text-transform:uppercase}
.tl-details{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:4px}
.tl-chip{display:flex;align-items:center;gap:4px;font-size:10.5px;color:var(--txt2);background:var(--bg3);border:1px solid var(--brd);border-radius:5px;padding:1px 7px;white-space:nowrap;max-width:140px;overflow:hidden;text-overflow:ellipsis}
.tl-chip i{font-size:9px;color:var(--txt3)}
.tl-time{font-size:10px;color:var(--txt3);margin-left:auto;white-space:nowrap;flex-shrink:0}
.tl-live-bar{display:flex;align-items:center;gap:8px;padding:6px 12px;border-bottom:1px solid var(--brd);background:var(--bg3)}
.tl-live-dot{width:6px;height:6px;border-radius:50%;background:var(--grn);box-shadow:0 0 6px var(--grn);animation:pulse 1.4s infinite}
.sev-critical{background:rgba(229,62,62,.18);color:#f87171}
.sev-high{background:rgba(255,138,0,.18);color:#fb923c}
.sev-medium{background:rgba(234,179,8,.18);color:#facc15}
.sev-low{background:rgba(59,130,246,.18);color:#60a5fa}
.sev-info{background:var(--bg3);color:var(--txt2)}
.sev-ok{background:rgba(0,217,126,.15);color:#34d399}
.icon-critical{background:rgba(229,62,62,.15);color:#f87171}
.icon-high{background:rgba(255,138,0,.15);color:#fb923c}
.icon-medium{background:rgba(234,179,8,.15);color:#facc15}
.icon-low{background:rgba(59,130,246,.15);color:#60a5fa}
.icon-info{background:var(--bg3);color:var(--txt2)}
.icon-ok{background:rgba(0,217,126,.12);color:#34d399}
/* bar colours */
.bar-critical{background:#ef4444}.bar-high{background:#f97316}.bar-medium{background:#eab308}
.bar-low{background:#3b82f6}.bar-info{background:var(--txt3)}.bar-ok{background:#10b981}
/* ── Severity Panel ──────────────────────────────────────────────────── */
.sev-panel{display:flex;flex-direction:column;gap:0;height:100%}
.sev-donut-wrap{display:flex;align-items:center;justify-content:center;gap:24px;padding:16px 16px 8px;flex-shrink:0}
.sev-donut-center{text-align:center}
.sev-donut-total{font-size:26px;font-weight:800;color:var(--txt);line-height:1}
.sev-donut-sub{font-size:10px;color:var(--txt3);margin-top:2px;text-transform:uppercase;letter-spacing:.5px}
.sev-rows{flex:1;display:flex;flex-direction:column;gap:0;border-top:1px solid var(--brd);overflow-y:auto}
.sev-row{display:flex;align-items:center;gap:10px;padding:9px 16px;border-bottom:1px solid var(--brd);transition:background .15s}
.sev-row:hover{background:var(--bg3)}
.sev-row-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.sev-row-label{font-size:11.5px;font-weight:600;color:var(--txt);width:64px;flex-shrink:0}
.sev-row-bar-wrap{flex:1;height:6px;background:var(--bg3);border-radius:3px;overflow:hidden}
.sev-row-bar{height:100%;border-radius:3px;transition:width .6s ease}
.sev-row-count{font-size:12px;font-weight:700;color:var(--txt);width:30px;text-align:right;flex-shrink:0}
.sev-row-pct{font-size:10px;color:var(--txt3);width:34px;text-align:right;flex-shrink:0}
.sev-row-trend{font-size:10px;width:20px;text-align:center;flex-shrink:0}

/* ── Infra grid ──────────────────────────────────────────────────────── */
.infra-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
.infra-card{padding:16px;border-radius:14px;border:1px solid var(--brd);background:var(--bg2)}
.infra-card-head{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.infra-indicator{width:10px;height:10px;border-radius:50%}
.ic-healthy,.ic-operational{background:var(--grn);box-shadow:0 0 8px rgba(0,217,126,.5)}
.ic-warning{background:var(--ylw);box-shadow:0 0 8px rgba(255,200,0,.5)}
.ic-critical,.ic-error,.ic-outage{background:var(--red);box-shadow:0 0 8px rgba(255,71,87,.5)}
.ic-unknown{background:var(--txt3)}
.infra-name{font-size:13px;font-weight:600;color:var(--txt)}
.infra-val{font-size:20px;font-weight:700;line-height:1;margin-bottom:2px}
.infra-meta{font-size:11px;color:var(--txt2);margin-top:4px}
.val-ok{color:var(--grn)}.val-warn{color:var(--ylw)}.val-crit{color:var(--red)}.val-neu{color:var(--blu)}

/* ── Progress bar ────────────────────────────────────────────────────── */
.prog-wrap{margin-top:10px}
.prog-label{display:flex;justify-content:space-between;font-size:11px;color:var(--txt2);margin-bottom:4px}
.prog-bar{height:5px;border-radius:3px;background:var(--prog-track);overflow:hidden}
.prog-fill{height:100%;border-radius:3px;transition:width 1s ease}
.fill-grn{background:linear-gradient(90deg,#00b866,var(--grn))}
.fill-ylw{background:linear-gradient(90deg,#e6a800,var(--ylw))}
.fill-red{background:linear-gradient(90deg,#cc2233,var(--red))}
.fill-blu{background:linear-gradient(90deg,#1a6dcc,var(--blu))}
.fill-ora{background:linear-gradient(90deg,#cc6600,var(--ora))}
.fill-pur{background:linear-gradient(90deg,#7c2dcc,var(--pur))}

/* ── User table ──────────────────────────────────────────────────────── */
.soc-table{width:100%;border-collapse:collapse;font-size:12px}
.soc-table th{padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--txt3);border-bottom:1px solid var(--brd);font-weight:600}
.soc-table td{padding:9px 12px;border-bottom:1px solid var(--brd);color:var(--txt2)}
.soc-table tr:hover td{background:rgba(0,0,0,.03)}
body[data-soc-theme="dark"] .soc-table tr:hover td{background:rgba(255,255,255,.03)}
.role-chip{padding:2px 8px;border-radius:6px;font-size:10px;font-weight:600;text-transform:uppercase}
.role-customer{background:rgba(77,159,255,.15);color:var(--blu)}
.role-vendor{background:rgba(0,217,126,.15);color:var(--grn)}
.role-driver{background:rgba(168,85,247,.15);color:var(--pur)}
.role-admin,.role-super_admin{background:rgba(255,71,87,.15);color:var(--red)}
.role-user{background:rgba(255,138,0,.15);color:var(--ora)}

/* ── AI Threat cards ─────────────────────────────────────────────────── */
.threat-list{display:flex;flex-direction:column;gap:10px}
.threat-card{padding:14px 16px;border-radius:14px;border-left:3px solid;border-top:1px solid var(--brd);border-right:1px solid var(--brd);border-bottom:1px solid var(--brd);background:var(--bg2);position:relative}
.threat-card.risk-critical{border-left-color:var(--red)}
.threat-card.risk-high{border-left-color:var(--ora)}
.threat-card.risk-medium{border-left-color:var(--ylw)}
.threat-card.risk-low{border-left-color:var(--blu)}
.tc-head{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.tc-icon{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:14px}
.tc-type{font-size:13px;font-weight:600;color:var(--txt)}
.tc-conf{font-size:12px;font-weight:700;padding:2px 8px;border-radius:6px}
.conf-critical{background:rgba(255,71,87,.2);color:var(--red)}
.conf-high{background:rgba(255,138,0,.2);color:var(--ora)}
.conf-medium,.conf-low{background:rgba(255,200,0,.2);color:var(--ylw)}
.tc-detail{font-size:12px;color:var(--txt2);line-height:1.5;margin-bottom:8px}
.tc-foot{display:flex;align-items:center;gap:8px}
.tc-src{font-size:11px;color:var(--txt3);font-family:monospace}
.tc-action{margin-left:auto;font-size:11px;font-weight:600;padding:4px 10px;border-radius:7px;border:1px solid var(--brd);background:var(--bg3);color:var(--txt2);cursor:pointer;transition:.15s}
.tc-action:hover{background:var(--gls)}

/* ── Compliance ──────────────────────────────────────────────────────── */
.comp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px}
.comp-card{padding:16px}
.comp-head{font-size:13px;font-weight:700;color:var(--txt);margin-bottom:12px;display:flex;align-items:center;gap:8px}
.comp-item{display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--brd);font-size:12px;color:var(--txt2)}
.comp-item:last-child{border-bottom:none}
.comp-tick{width:18px;height:18px;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0}
.tick-ok{background:rgba(0,217,126,.2);color:var(--grn)}
.tick-warn{background:rgba(255,200,0,.2);color:var(--ylw)}
.tick-fail{background:rgba(255,71,87,.2);color:var(--red)}

/* ── Quick action form ───────────────────────────────────────────────── */
.qa-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
.qa-card{padding:20px}
.qa-title{font-size:13px;font-weight:600;color:var(--txt);margin-bottom:6px}
.qa-desc{font-size:12px;color:var(--txt2);margin-bottom:14px;line-height:1.5}
.soc-input{width:100%;background:var(--bg3);border:1px solid var(--brd);border-radius:9px;padding:9px 12px;color:var(--txt);font-size:13px;outline:none;margin-bottom:10px}
.soc-input:focus{border-color:var(--ora)}
.btn-action{width:100%;padding:10px;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:.2s;display:flex;align-items:center;justify-content:center;gap:7px}
.btn-danger{background:rgba(255,71,87,.2);color:var(--red);border:1px solid rgba(255,71,87,.3)}.btn-danger:hover{background:rgba(255,71,87,.35)}
.btn-warn{background:rgba(255,200,0,.2);color:var(--ylw);border:1px solid rgba(255,200,0,.3)}.btn-warn:hover{background:rgba(255,200,0,.35)}
.btn-info{background:rgba(77,159,255,.2);color:var(--blu);border:1px solid rgba(77,159,255,.3)}.btn-info:hover{background:rgba(77,159,255,.35)}
.btn-success{background:rgba(0,217,126,.2);color:var(--grn);border:1px solid rgba(0,217,126,.3)}.btn-success:hover{background:rgba(0,217,126,.35)}

/* ── Audit table ─────────────────────────────────────────────────────── */
.audit-wrap{max-height:480px;overflow-y:auto;border-radius:14px;border:1px solid var(--brd)}
.audit-wrap::-webkit-scrollbar{width:4px}.audit-wrap::-webkit-scrollbar-thumb{background:var(--scrl);border-radius:2px}

/* ── Performance ─────────────────────────────────────────────────────── */
.perf-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}
.perf-card{padding:16px;position:relative}
.perf-card-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px}
.perf-val{font-size:26px;font-weight:700;letter-spacing:-1px;line-height:1}
.perf-unit{font-size:12px;color:var(--txt2);margin-left:4px}

/* ── Refresh timer ───────────────────────────────────────────────────── */
#refreshBar{position:fixed;bottom:0;left:0;right:0;height:2px;background:var(--brd);z-index:1000;overflow:hidden}
#refreshFill{height:100%;background:linear-gradient(90deg,var(--ora),#ff6600);width:100%;transition:width .9s linear}

/* ── Toast ───────────────────────────────────────────────────────────── */
#toast{position:fixed;bottom:24px;right:24px;padding:12px 18px;border-radius:12px;font-size:13px;font-weight:500;z-index:9999;opacity:0;transform:translateY(10px);transition:.25s;pointer-events:none;max-width:340px}
#toast.show{opacity:1;transform:translateY(0)}
#toast.ok{background:rgba(0,217,126,.15);border:1px solid rgba(0,217,126,.3);color:var(--grn)}
#toast.err{background:rgba(255,71,87,.15);border:1px solid rgba(255,71,87,.3);color:var(--red)}

::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--scrl);border-radius:3px}

/* ── Theme toggle button ─────────────────────────────────────────── */
#themeToggle{display:flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;border:1px solid var(--brd);background:var(--gls);color:var(--txt2);font-size:12px;font-weight:600;cursor:pointer;transition:.2s;white-space:nowrap;flex-shrink:0}
#themeToggle:hover{background:var(--bg3);color:var(--txt)}
#themeToggle .t-icon{font-size:13px;transition:transform .4s}
body[data-soc-theme="dark"] #themeToggle .t-icon{transform:rotate(180deg)}

/* ── Map stats (light-friendly) ─────────────────────────────────── */
.map-stats{position:absolute;top:10px;right:10px;z-index:10;background:var(--bg2);border:1px solid var(--brd);border-radius:10px;padding:8px 14px;font-size:11px;backdrop-filter:blur(8px)}
.map-stats strong{color:var(--red);font-size:18px;display:block;line-height:1}
.map-leg-item{display:flex;align-items:center;gap:5px;font-size:10px;color:var(--txt2);background:var(--bg2);padding:3px 8px;border-radius:6px;border:1px solid var(--brd)}
</style>
@endpush

@section('content')
<div id="soc">

{{-- ── Global Status Bar ────────────────────────────────────────────── --}}
<div id="statusBar">
    <div class="sb-platform {{ $platformStatus }}">
        <i class="fas fa-shield-halved"></i>
        @if($platformStatus === 'operational') All Systems Operational
        @elseif($platformStatus === 'partial_outage') Partial Outage
        @else Major Incident
        @endif
    </div>
    @foreach($services as $key => $svc)
    <div class="sb-item">
        <span class="dot dot-{{ $svc['status'] }}"></span>
        <span>{{ $svc['name'] }}
            @if(!empty($svc['latency']))
                <span style="color:var(--txt3);font-size:10px"> {{ $svc['latency'] }}ms</span>
            @endif
            @if(!empty($svc['extra']))
                <span style="color:var(--txt3);font-size:10px"> · {{ $svc['extra'] }}</span>
            @endif
        </span>
    </div>
    @endforeach
    <div class="sb-item" style="border-right:none">
        <i class="fas fa-sync-alt" style="font-size:10px"></i>
        <span>Refresh in <span id="countdown">30</span>s</span>
    </div>
    <div style="margin-left:auto;padding:0 0 0 14px;display:flex;align-items:center">
        <button id="themeToggle" onclick="toggleTheme()" title="Toggle Light / Dark">
            <span class="t-icon">☀️</span>
            <span id="themeLabel">Light</span>
        </button>
    </div>
</div>

{{-- ── SOC Header ───────────────────────────────────────────────────── --}}
<div id="socHead">
    <div class="soc-score-wrap">
        <canvas id="scoreGauge" width="120" height="120"></canvas>
        <div class="score-val">
            <div class="score-num" id="scoreNum">{{ $securityScore }}</div>
            <div class="score-lbl">Score</div>
        </div>
    </div>
    <div class="soc-meta">
        <div class="soc-title">
            Security Operations Center
            <span class="soc-badge badge-{{ $securityStatus }}">{{ ucfirst(str_replace('_',' ',$securityStatus)) }}</span>
        </div>
        <div class="soc-sub">eSahlan Platform &nbsp;·&nbsp; {{ now()->format('D, d M Y H:i') }} &nbsp;·&nbsp; Laravel + Redis + Reverb</div>
        <div class="soc-stats">
            <div class="soc-stat"><span id="hdrThreats">{{ $threatCount }}</span><span>AI Threats</span></div>
            <div class="soc-stat"><span id="hdrSessions">{{ $activeSessions }}</span><span>Active Sessions</span></div>
            <div class="soc-stat"><span id="hdrUsers">{{ $totalUsers }}</span><span>Total Users</span></div>
            <div class="soc-stat"><span id="hdrFailed">{{ $failedLogins1h }}</span><span>Failed Logins/h</span></div>
        </div>
    </div>
    <div class="soc-actions">
        <a href="{{ route('admin.security.audit.export') }}" class="btn-soc btn-glass">
            <i class="fas fa-download"></i> Export CSV
        </a>
        <button class="btn-soc btn-ora" onclick="socRefresh()">
            <i class="fas fa-sync-alt"></i> Refresh Now
        </button>
    </div>
</div>

@if($recentCritical->count() > 0)
<div id="alertBanner">
    <i class="fas fa-triangle-exclamation"></i>
    <strong>{{ $recentCritical->count() }} critical security event(s) in the last 24 hours.</strong>
    &nbsp;Most recent: <em>{{ $recentCritical->first()->event }}</em> — {{ $recentCritical->first()->created_at->diffForHumans() }}
</div>
@endif

{{-- ── Tabs ──────────────────────────────────────────────────────────── --}}
<div id="tabNav">
    <button class="tab-btn active" data-tab="overview"><i class="fas fa-chart-line"></i> Overview</button>
    <button class="tab-btn" data-tab="infra"><i class="fas fa-server"></i> Infrastructure</button>
    <button class="tab-btn" data-tab="users"><i class="fas fa-users"></i> Live Users</button>
    <button class="tab-btn" data-tab="threats">
        <i class="fas fa-robot"></i> AI Threats
        @if($threatCount > 0)<span class="tab-badge">{{ $threatCount }}</span>@endif
    </button>
    <button class="tab-btn" data-tab="api"><i class="fas fa-plug"></i> API</button>
    <button class="tab-btn" data-tab="storage"><i class="fas fa-database"></i> Storage &amp; Media</button>
    <button class="tab-btn" data-tab="moderation">
        <i class="fas fa-flag"></i> Moderation
        @if(($contentStats['pending_moderation'] ?? 0) > 0)<span class="tab-badge">{{ $contentStats['pending_moderation'] }}</span>@endif
    </button>
    <button class="tab-btn" data-tab="performance"><i class="fas fa-gauge-high"></i> Performance</button>
    <button class="tab-btn" data-tab="audit"><i class="fas fa-scroll"></i> Audit Log</button>
    <button class="tab-btn" data-tab="actions"><i class="fas fa-bolt"></i> Quick Actions</button>
    <button class="tab-btn" data-tab="compliance"><i class="fas fa-shield-check"></i> Compliance</button>
</div>

{{-- ═══════════════════════════════ OVERVIEW ════════════════════════════ --}}
<div id="tab-overview" class="tab-pane active">

    <div class="kpi-grid">
        <div class="kpi g kpi-red">
            <div class="kpi-icon"><i class="fas fa-fire"></i></div>
            <div class="kpi-val" id="kCritical">{{ $criticalEvents1h }}</div>
            <div class="kpi-lbl">Critical Events / hr</div>
            <div class="kpi-sub" style="color:var(--txt2)">{{ $totalEventsToday }} total today</div>
        </div>
        <div class="kpi g kpi-ora">
            <div class="kpi-icon"><i class="fas fa-user-xmark"></i></div>
            <div class="kpi-val" id="kFailed">{{ $failedLogins1h }}</div>
            <div class="kpi-lbl">Failed Logins / hr</div>
            <div class="kpi-sub" style="color:var(--txt2)">{{ $failedLogins24h }} in 24h</div>
        </div>
        <div class="kpi g kpi-red">
            <div class="kpi-icon"><i class="fas fa-ban"></i></div>
            <div class="kpi-val" id="kBlocked">{{ $blockedIps24h }}</div>
            <div class="kpi-lbl">Blocked IPs (24h)</div>
            <div class="kpi-sub" style="color:var(--txt2)">{{ $blockedToday }} blocked today</div>
        </div>
        <div class="kpi g kpi-pur">
            <div class="kpi-icon"><i class="fas fa-virus"></i></div>
            <div class="kpi-val" id="kMalware">{{ $malware24h }}</div>
            <div class="kpi-lbl">Upload Rejections</div>
            <div class="kpi-sub" style="color:var(--txt2)">Malware / bad files</div>
        </div>
        <div class="kpi g kpi-blu">
            <div class="kpi-icon"><i class="fas fa-right-to-bracket"></i></div>
            <div class="kpi-val" id="kSessions">{{ $activeSessions }}</div>
            <div class="kpi-lbl">Active Sessions</div>
            <div class="kpi-sub" style="color:var(--txt2)">Last 30 minutes</div>
        </div>
        <div class="kpi g kpi-ok">
            <div class="kpi-icon"><i class="fas fa-check-double"></i></div>
            <div class="kpi-val" id="kSuccessRate">{{ $successRate }}%</div>
            <div class="kpi-lbl">Auth Success Rate</div>
            <div class="kpi-sub" style="color:var(--txt2)">Last hour</div>
        </div>
        <div class="kpi g kpi-ylw">
            <div class="kpi-icon"><i class="fas fa-gauge-high"></i></div>
            <div class="kpi-val" id="kRateLimit">{{ $rateLimitHits24h }}</div>
            <div class="kpi-lbl">Rate Limit Hits</div>
            <div class="kpi-sub" style="color:var(--txt2)">24h window</div>
        </div>
        <div class="kpi g kpi-blu">
            <div class="kpi-icon"><i class="fas fa-user-plus"></i></div>
            <div class="kpi-val" id="kNewUsers">{{ $newUsers24h }}</div>
            <div class="kpi-lbl">New Users (24h)</div>
            <div class="kpi-sub" style="color:var(--txt2)">{{ $totalUsers }} total</div>
        </div>
    </div>

    <div class="gc" style="margin-bottom:20px;align-items:stretch">
        <div style="display:flex;flex-direction:column">
            <div class="s-label">Live Attack Map — Real Attacker Locations</div>
            <div id="mapWrap">
                <div id="gmap"></div>
                <div class="map-stats">
                    <strong id="mapAttackerCount">{{ count($attackerGeo) }}</strong>
                    <span style="color:var(--txt2)">Attackers (48h)</span>
                </div>
                <div class="map-legend">
                    <div class="map-leg-item"><div class="map-leg-dot" style="background:#ff4757"></div> Brute Force</div>
                    <div class="map-leg-item"><div class="map-leg-dot" style="background:#ffc800"></div> Credential Stuffing</div>
                    <div class="map-leg-item"><div class="map-leg-dot" style="background:#a855f7"></div> Malware Upload</div>
                    <div class="map-leg-item"><div class="map-leg-dot" style="background:#4d9fff"></div> API Abuse</div>
                    <div class="map-leg-item"><div class="map-leg-dot" style="background:#00d97e"></div> eSahlan Server</div>
                </div>
            </div>
        </div>
        <div style="display:flex;flex-direction:column;min-width:0">
            <div class="s-label" style="display:flex;align-items:center;gap:10px">
                Security Timeline
                <span id="tlCount" style="font-size:10px;font-weight:700;background:var(--red);color:#fff;padding:1px 7px;border-radius:20px;letter-spacing:.3px">{{ count($auditLog) }}</span>
            </div>
            <div class="g" style="padding:0;overflow:hidden;display:flex;flex-direction:column;flex:1">
                <div class="tl-live-bar">
                    <div class="tl-live-dot"></div>
                    <span style="font-size:10px;font-weight:600;color:var(--txt2);text-transform:uppercase;letter-spacing:.5px">Live Feed</span>
                    <span style="font-size:10px;color:var(--txt3);margin-left:auto">Auto-refresh 30s</span>
                </div>
                <div class="tl" id="tlFeed">
                    @forelse($auditLog as $log)
                    @php
                        $sev = $log->severity ?? 'info';
                        $iconMap = [
                            'admin.login.failed'       => 'fa-user-xmark',
                            'admin.login.success'      => 'fa-user-check',
                            'admin.login.blocked'      => 'fa-ban',
                            'admin.logout'             => 'fa-right-from-bracket',
                            'admin.route.probe'        => 'fa-radar',
                            'admin.route.blocked'      => 'fa-shield-halved',
                            'admin.privilege.escalation'=> 'fa-user-shield',
                            'upload.rejected'          => 'fa-virus',
                            'admin.quick_action'       => 'fa-bolt',
                            'register'                 => 'fa-user-plus',
                            'login.failed'             => 'fa-user-xmark',
                            'login.success'            => 'fa-user-check',
                            'login.blocked'            => 'fa-ban',
                        ];
                        $icon = $iconMap[$log->event] ?? 'fa-circle-dot';
                        $meta = is_string($log->meta) ? json_decode($log->meta, true) : (array)($log->meta ?? []);
                        $attempts = $meta['attempts'] ?? null;
                        $reason   = $meta['reason'] ?? null;
                        $retryMin = isset($meta['retry_after']) ? ceil($meta['retry_after']/60) : null;
                    @endphp
                    <div class="tl-item">
                        <div class="tl-bar bar-{{ $sev }}"></div>
                        <div class="tl-inner">
                            <div class="tl-icon icon-{{ $sev }}"><i class="fas {{ $icon }}"></i></div>
                            <div class="tl-body">
                                <div class="tl-top">
                                    <span class="tl-event">{{ $log->event }}</span>
                                    <span class="tl-sev sev-{{ $sev }}">{{ strtoupper($sev) }}</span>
                                    <span class="tl-time" title="{{ $log->created_at->format('Y-m-d H:i:s') }}"><i class="fas fa-clock" style="font-size:9px;margin-right:3px"></i>{{ $log->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="tl-details">
                                    @if($log->ip_address)
                                    <span class="tl-chip"><i class="fas fa-globe"></i>{{ $log->ip_address }}</span>
                                    @endif
                                    @if($log->user_identifier && $log->user_identifier !== 'anonymous')
                                    <span class="tl-chip"><i class="fas fa-user"></i>{{ Str::limit($log->user_identifier, 22) }}</span>
                                    @endif
                                    @if($attempts)
                                    <span class="tl-chip"><i class="fas fa-repeat"></i>{{ $attempts }} attempts</span>
                                    @endif
                                    @if($reason)
                                    <span class="tl-chip"><i class="fas fa-tag"></i>{{ str_replace('_',' ',$reason) }}</span>
                                    @endif
                                    @if($retryMin)
                                    <span class="tl-chip" style="color:var(--red)"><i class="fas fa-lock"></i>locked {{ $retryMin }}min</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div style="color:var(--txt3);font-size:12px;padding:24px;text-align:center"><i class="fas fa-shield-check" style="font-size:20px;margin-bottom:8px;display:block;color:var(--grn)"></i>No security events yet</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="g2">
        <div class="g" style="padding:20px">
            <div class="s-head">Failed Logins — Last 24 Hours</div>
            <div class="s-sub">Hourly breakdown of authentication failures</div>
            <canvas id="hourlyChart" height="160"></canvas>
        </div>
        <div class="g" style="padding:0;overflow:hidden">
            <div style="padding:16px 16px 8px;border-bottom:1px solid var(--brd)">
                <div class="s-head" style="margin:0 0 2px">Severity Distribution</div>
                <div class="s-sub" style="margin:0">Event breakdown — last 24h</div>
            </div>
            <div class="sev-panel">
                <div class="sev-donut-wrap">
                    <div style="position:relative;flex-shrink:0">
                        <canvas id="sevDonut" width="140" height="140"></canvas>
                        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none">
                            <div id="sevTotalNum" class="sev-donut-total">0</div>
                            <div class="sev-donut-sub">events</div>
                        </div>
                    </div>
                    <div id="sevQuickStats" style="display:flex;flex-direction:column;gap:6px;flex:1;min-width:0"></div>
                </div>
                <div class="sev-rows" id="sevRows"></div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════ INFRASTRUCTURE ══════════════════════════ --}}
<div id="tab-infra" class="tab-pane">
    <div class="s-label">Service Health</div>
    <div class="infra-grid" style="margin-bottom:20px">
        @foreach($infra as $key => $svc)
        @php $s = $svc['status']; $cls = match($s){ 'healthy','operational'=>'val-ok','warning'=>'val-warn','critical','error','outage'=>'val-crit',default=>'val-neu' }; @endphp
        <div class="infra-card">
            <div class="infra-card-head">
                <div class="infra-indicator ic-{{ $s }}"></div>
                <div class="infra-name">{{ $svc['label'] }}</div>
            </div>
            <div class="infra-val {{ $cls }}">{{ ucfirst($s) }}</div>
            <div class="infra-meta">
                @if(isset($svc['latency']) && $svc['latency'] !== null)
                    {{ $svc['latency'] }}ms response time
                @elseif(isset($svc['failed']))
                    {{ $svc['failed'] }} failed &nbsp;·&nbsp; {{ $svc['pending'] }} pending
                @elseif(isset($svc['used_pct']))
                    {{ $svc['used_pct'] }}% used ({{ $svc['free_gb'] ?? '?' }} GB free)
                @else
                    Operational
                @endif
            </div>
            @if(isset($svc['used_pct']))
            <div class="prog-wrap">
                <div class="prog-bar">
                    <div class="prog-fill {{ $svc['used_pct']>90?'fill-red':($svc['used_pct']>75?'fill-ylw':'fill-grn') }}" style="width:{{ $svc['used_pct'] }}%"></div>
                </div>
            </div>
            @endif
        </div>
        @endforeach
    </div>

    <div class="s-label">Top Attacking IPs (24h)</div>
    <div class="g" style="overflow:auto;border-radius:14px">
        <table class="soc-table">
            <thead><tr><th>#</th><th>IP Address</th><th>Failed Attempts</th><th>Risk Level</th></tr></thead>
            <tbody>
                @forelse($topIps as $i => $ip)
                <tr>
                    <td style="color:var(--txt3)">{{ $i+1 }}</td>
                    <td style="font-family:monospace;color:var(--txt)">{{ $ip->ip_address }}</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <span style="font-weight:700;color:var(--red)">{{ $ip->attempts }}</span>
                            <div class="prog-bar" style="flex:1;height:4px">
                                <div class="prog-fill fill-red" style="width:{{ $topIps->max('attempts')>0?round($ip->attempts/$topIps->max('attempts')*100):0 }}%"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @php $r = $ip->attempts>50?'critical':($ip->attempts>20?'high':($ip->attempts>5?'medium':'low')); @endphp
                        <span class="tl-sev sev-{{ $r }}">{{ strtoupper($r) }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" style="text-align:center;color:var(--txt3);padding:20px">No attack data in 24h</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ═════════════════════════════ LIVE USERS ════════════════════════════ --}}
<div id="tab-users" class="tab-pane">
    <div class="g4" style="margin-bottom:20px">
        <div class="g" style="padding:18px;text-align:center">
            <div style="font-size:36px;font-weight:700;color:var(--blu)" id="liveTotal">{{ $totalSessions }}</div>
            <div style="font-size:11px;color:var(--txt2);text-transform:uppercase;letter-spacing:.8px;margin-top:4px">Active Sessions</div>
            <div style="font-size:11px;color:var(--txt3);margin-top:2px">Last 30 minutes</div>
        </div>
        @foreach(['customer'=>['blu','Customers'],'vendor'=>['grn','Vendors'],'driver'=>['pur','Drivers']] as $role => $info)
        <div class="g" style="padding:18px;text-align:center">
            <div style="font-size:30px;font-weight:700;color:var(--{{ $info[0] }})">{{ $byRole->get($role)?->count ?? 0 }}</div>
            <div style="font-size:11px;color:var(--txt2);text-transform:uppercase;letter-spacing:.8px;margin-top:4px">{{ $info[1] }}</div>
        </div>
        @endforeach
    </div>

    <div class="g" style="overflow:auto;border-radius:14px">
        <table class="soc-table">
            <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Last Active</th></tr></thead>
            <tbody>
                @forelse($recentSessions as $sess)
                @php $roleClass = strtolower(str_replace([' ','_'],['_','_'],$sess->role_name??'user')); @endphp
                <tr>
                    <td style="color:var(--txt);font-weight:500">{{ $sess->name ?? '—' }}</td>
                    <td style="font-size:11px">{{ $sess->email ?? '—' }}</td>
                    <td><span class="role-chip role-{{ $roleClass }}">{{ $sess->role_name ?? 'Customer' }}</span></td>
                    <td>{{ $sess->last_used_at ? \Carbon\Carbon::parse($sess->last_used_at)->diffForHumans() : '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="4" style="text-align:center;color:var(--txt3);padding:20px">No active sessions in last 30 minutes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ═══════════════════════════ AI THREATS ═════════════════════════════ --}}
<div id="tab-threats" class="tab-pane">
@php
    $appThreats   = array_values(array_filter($aiThreats, fn($t) => ($t['vector'] ?? 'app') !== 'admin_panel'));
    $adminThreats = array_values(array_filter($aiThreats, fn($t) => ($t['vector'] ?? '') === 'admin_panel'));
@endphp

{{-- ── Summary row ── --}}
<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap">
    <div class="g" style="padding:14px 20px;flex:1;min-width:160px;display:flex;align-items:center;gap:12px">
        <div style="width:38px;height:38px;border-radius:10px;background:rgba(77,159,255,.15);display:flex;align-items:center;justify-content:center;color:var(--blu);font-size:16px"><i class="fas fa-mobile-screen"></i></div>
        <div>
            <div style="font-size:22px;font-weight:700;color:var(--blu);line-height:1">{{ count($appThreats) }}</div>
            <div style="font-size:11px;color:var(--txt2);text-transform:uppercase;letter-spacing:.8px">App Threats</div>
        </div>
    </div>
    <div class="g" style="padding:14px 20px;flex:1;min-width:160px;display:flex;align-items:center;gap:12px">
        <div style="width:38px;height:38px;border-radius:10px;background:rgba(255,71,87,.15);display:flex;align-items:center;justify-content:center;color:var(--red);font-size:16px"><i class="fas fa-shield-halved"></i></div>
        <div>
            <div style="font-size:22px;font-weight:700;color:var(--red);line-height:1">{{ count($adminThreats) }}</div>
            <div style="font-size:11px;color:var(--txt2);text-transform:uppercase;letter-spacing:.8px">Admin Panel Attacks</div>
        </div>
    </div>
    <div class="g" style="padding:14px 20px;flex:1;min-width:160px;display:flex;align-items:center;gap:12px">
        <div style="width:38px;height:38px;border-radius:10px;background:rgba(0,217,126,.15);display:flex;align-items:center;justify-content:center;color:var(--grn);font-size:16px"><i class="fas fa-clock"></i></div>
        <div>
            <div style="font-size:12px;color:var(--txt);font-weight:600">Live Detection</div>
            <div style="font-size:11px;color:var(--txt2)">Real-time — all surfaces</div>
        </div>
    </div>
</div>

@if(count($aiThreats) === 0)
<div class="g" style="padding:40px;text-align:center">
    <i class="fas fa-shield-check" style="font-size:48px;color:var(--grn);margin-bottom:16px;display:block"></i>
    <div style="font-size:16px;font-weight:600;color:var(--grn);margin-bottom:8px">No Active Threats Detected</div>
    <div style="font-size:13px;color:var(--txt2)">All monitored patterns are within normal thresholds.</div>
</div>
@else

{{-- ── Admin Panel Attacks ── --}}
@if(count($adminThreats) > 0)
<div class="s-label" style="margin-bottom:10px;display:flex;align-items:center;gap:8px">
    <i class="fas fa-shield-halved" style="color:var(--red)"></i>
    Admin Panel Attacks
    <span style="background:rgba(255,71,87,.2);color:var(--red);font-size:10px;font-weight:700;padding:1px 7px;border-radius:10px">{{ count($adminThreats) }} active</span>
</div>
<div class="threat-list" style="margin-bottom:24px">
    @foreach($adminThreats as $t)
    @php
        $rc = match($t['risk']){ 'critical'=>'var(--red)','high'=>'var(--ora)','medium'=>'var(--ylw)',default=>'var(--blu)' };
        $rb = match($t['risk']){ 'critical'=>'rgba(255,71,87,.2)','high'=>'rgba(255,138,0,.2)','medium'=>'rgba(255,200,0,.2)',default=>'rgba(77,159,255,.2)' };
    @endphp
    <div class="threat-card risk-{{ $t['risk'] }}" style="border-left-color:var(--red)">
        <div style="position:absolute;top:10px;right:10px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;background:rgba(255,71,87,.15);color:var(--red);padding:2px 8px;border-radius:6px;border:1px solid rgba(255,71,87,.3)">ADMIN PANEL</div>
        <div class="tc-head">
            <div class="tc-icon" style="background:{{ $rb }};color:{{ $rc }}"><i class="fas {{ $t['icon'] }}"></i></div>
            <div>
                <div class="tc-type">{{ $t['type'] }}</div>
                <div style="font-size:11px;color:{{ $rc }};text-transform:uppercase;font-weight:600">{{ $t['risk'] }}</div>
            </div>
            <div class="tc-conf conf-{{ $t['risk'] }}" style="margin-left:auto;margin-right:70px">{{ $t['confidence'] }}% confidence</div>
        </div>
        <div class="tc-detail">{{ $t['detail'] }}</div>
        <div class="tc-foot">
            <span class="tc-src"><i class="fas fa-location-dot" style="margin-right:4px;opacity:.5"></i>{{ $t['source'] }}</span>
            <button class="tc-action" onclick="quickAct('block_ip','{{ addslashes($t['source']) }}',this)">
                <i class="fas fa-ban" style="margin-right:4px"></i>{{ $t['action'] }}
            </button>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- ── App Threats ── --}}
@if(count($appThreats) > 0)
<div class="s-label" style="margin-bottom:10px;display:flex;align-items:center;gap:8px">
    <i class="fas fa-mobile-screen" style="color:var(--blu)"></i>
    Mobile App Threats
</div>
<div class="threat-list">
    @foreach($appThreats as $t)
    @php
        $rc = match($t['risk']){ 'critical'=>'var(--red)','high'=>'var(--ora)','medium'=>'var(--ylw)',default=>'var(--blu)' };
        $rb = match($t['risk']){ 'critical'=>'rgba(255,71,87,.2)','high'=>'rgba(255,138,0,.2)','medium'=>'rgba(255,200,0,.2)',default=>'rgba(77,159,255,.2)' };
    @endphp
    <div class="threat-card risk-{{ $t['risk'] }}">
        <div class="tc-head">
            <div class="tc-icon" style="background:{{ $rb }};color:{{ $rc }}"><i class="fas {{ $t['icon'] }}"></i></div>
            <div>
                <div class="tc-type">{{ $t['type'] }}</div>
                <div style="font-size:11px;color:{{ $rc }};text-transform:uppercase;font-weight:600">{{ $t['risk'] }}</div>
            </div>
            <div class="tc-conf conf-{{ $t['risk'] }}" style="margin-left:auto">{{ $t['confidence'] }}% confidence</div>
            @if($t['auto'])
            <span style="font-size:10px;background:rgba(0,217,126,.15);color:var(--grn);padding:2px 7px;border-radius:5px;font-weight:600">AUTO-MITIGATED</span>
            @endif
        </div>
        <div class="tc-detail">{{ $t['detail'] }}</div>
        <div class="tc-foot">
            <span class="tc-src"><i class="fas fa-location-dot" style="margin-right:4px;opacity:.5"></i>{{ $t['source'] }}</span>
            <button class="tc-action" onclick="quickAct('block_ip','{{ addslashes($t['source']) }}',this)">
                <i class="fas fa-ban" style="margin-right:4px"></i>{{ $t['action'] }}
            </button>
        </div>
    </div>
    @endforeach
</div>
@endif

@endif
</div>

{{-- ═══════════════════════════ API SECURITY ════════════════════════════ --}}
<div id="tab-api" class="tab-pane">
    <div class="g4" style="margin-bottom:20px">
        <div class="g" style="padding:18px;text-align:center">
            <div style="font-size:30px;font-weight:700;color:var(--blu)">{{ $rateLimitHits24h }}</div>
            <div style="font-size:11px;color:var(--txt2);text-transform:uppercase;letter-spacing:.8px;margin-top:4px">Rate Limit Hits</div>
            <div style="font-size:11px;color:var(--txt3)">24h total</div>
        </div>
        <div class="g" style="padding:18px;text-align:center">
            <div style="font-size:30px;font-weight:700;color:var(--ora)">{{ $failedLogins24h }}</div>
            <div style="font-size:11px;color:var(--txt2);text-transform:uppercase;letter-spacing:.8px;margin-top:4px">Auth Failures</div>
            <div style="font-size:11px;color:var(--txt3)">24h window</div>
        </div>
        <div class="g" style="padding:18px;text-align:center">
            <div style="font-size:30px;font-weight:700;color:var(--red)">{{ $blockedIps24h }}</div>
            <div style="font-size:11px;color:var(--txt2);text-transform:uppercase;letter-spacing:.8px;margin-top:4px">Blocked IPs</div>
            <div style="font-size:11px;color:var(--txt3)">Actively blocked</div>
        </div>
        <div class="g" style="padding:18px;text-align:center">
            <div style="font-size:30px;font-weight:700;color:var(--grn)">{{ $successRate }}%</div>
            <div style="font-size:11px;color:var(--txt2);text-transform:uppercase;letter-spacing:.8px;margin-top:4px">Success Rate</div>
            <div style="font-size:11px;color:var(--txt3)">Auth ratio / hr</div>
        </div>
    </div>

    <div class="g" style="padding:20px">
        <div class="s-head" style="margin-bottom:16px">Top Events by Type (24h)</div>
        @forelse($eventBreakdown as $eb)
        @php $maxEb = $eventBreakdown->max('count'); @endphp
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
            <div style="font-family:monospace;font-size:12px;color:var(--txt);min-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $eb->event }}</div>
            <div class="prog-bar" style="flex:1;height:8px;border-radius:4px">
                <div class="prog-fill fill-blu" style="width:{{ $maxEb>0?round($eb->count/$maxEb*100):0 }}%"></div>
            </div>
            <div style="font-size:13px;font-weight:700;color:var(--blu);min-width:40px;text-align:right">{{ $eb->count }}</div>
        </div>
        @empty
        <div style="color:var(--txt3);font-size:12px">No events in 24h.</div>
        @endforelse
    </div>
</div>

{{-- ══════════════════════════ STORAGE & MEDIA ══════════════════════════ --}}
<div id="tab-storage" class="tab-pane">
    <div class="g3" style="margin-bottom:16px">
        <div class="g" style="padding:20px">
            <div class="s-head"><i class="fas fa-hard-drive" style="color:var(--blu)"></i> Disk Usage</div>
            @php $diskColor = $diskUsedPct>90?'var(--red)':($diskUsedPct>75?'var(--ylw)':'var(--grn)'); @endphp
            <div style="font-size:36px;font-weight:700;color:{{ $diskColor }};margin:12px 0 4px">{{ $diskUsedPct }}%</div>
            <div style="font-size:12px;color:var(--txt2);margin-bottom:12px">{{ $diskUsedGb }} GB used of {{ $diskTotalGb }} GB</div>
            <div class="prog-bar" style="height:8px">
                <div class="prog-fill {{ $diskUsedPct>90?'fill-red':($diskUsedPct>75?'fill-ylw':'fill-grn') }}" style="width:{{ $diskUsedPct }}%"></div>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:11px;color:var(--txt3)">
                <span>{{ $diskFreeGb }} GB free</span><span>{{ $diskTotalGb }} GB total</span>
            </div>
        </div>
        <div class="g" style="padding:20px">
            <div class="s-head"><i class="fas fa-virus" style="color:var(--red)"></i> Malware Rejections</div>
            <div style="font-size:36px;font-weight:700;color:var(--red);margin:12px 0 4px">{{ $malware24h }}</div>
            <div style="font-size:12px;color:var(--txt2)">Blocked uploads (24h)</div>
            <div style="margin-top:14px;font-size:12px;color:var(--txt2);line-height:1.6">MIME type + extension blocklist enforcement. All rejections logged to security audit trail.</div>
        </div>
        <div class="g" style="padding:20px">
            <div class="s-head"><i class="fas fa-circle-nodes" style="color:var(--pur)"></i> Pipeline Health</div>
            <div style="display:flex;flex-direction:column;gap:12px;margin-top:14px">
                <div style="display:flex;justify-content:space-between;font-size:13px">
                    <span style="color:var(--txt2)">Pending Jobs</span>
                    <span style="font-weight:700;color:var(--ylw)">{{ $pendingJobs }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13px">
                    <span style="color:var(--txt2)">Failed Jobs</span>
                    <span style="font-weight:700;color:{{ $failedJobs>5?'var(--red)':'var(--grn)' }}">{{ $failedJobs }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13px">
                    <span style="color:var(--txt2)">Redis Keys</span>
                    <span style="font-weight:700;color:var(--blu)">{{ $redisKeys }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13px">
                    <span style="color:var(--txt2)">Redis Memory</span>
                    <span style="font-weight:700;color:var(--blu)">{{ $redisMemUsed }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════ MODERATION ═══════════════════════════════ --}}
<div id="tab-moderation" class="tab-pane">
    <div class="g4" style="margin-bottom:20px">
        <div class="g" style="padding:18px;text-align:center">
            <div style="font-size:36px;font-weight:700;color:var(--ylw)">{{ $contentStats['pending_moderation'] ?? 0 }}</div>
            <div style="font-size:11px;color:var(--txt2);text-transform:uppercase;letter-spacing:.8px;margin-top:4px">Pending Review</div>
        </div>
        <div class="g" style="padding:18px;text-align:center">
            <div style="font-size:36px;font-weight:700;color:var(--red)">{{ $contentStats['flagged_today'] ?? 0 }}</div>
            <div style="font-size:11px;color:var(--txt2);text-transform:uppercase;letter-spacing:.8px;margin-top:4px">Flagged Today</div>
        </div>
        <div class="g" style="padding:18px;text-align:center">
            <div style="font-size:36px;font-weight:700;color:var(--ora)">{{ $contentStats['pending_reports'] ?? 0 }}</div>
            <div style="font-size:11px;color:var(--txt2);text-transform:uppercase;letter-spacing:.8px;margin-top:4px">Open Reports</div>
        </div>
        <div class="g" style="padding:18px;text-align:center">
            <div style="font-size:36px;font-weight:700;color:var(--blu)">{{ $contentStats['total_posts'] ?? 0 }}</div>
            <div style="font-size:11px;color:var(--txt2);text-transform:uppercase;letter-spacing:.8px;margin-top:4px">Total Posts</div>
        </div>
    </div>
    <div class="g" style="padding:24px">
        <div class="s-head" style="margin-bottom:14px">Moderation Pipeline</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            @foreach([['Auto-scan on upload','fa-robot','grn'],['Keyword filtering','fa-filter','blu'],['Admin review queue','fa-eye','ora'],['User report system','fa-flag','ylw']] as $f)
            <div style="display:flex;align-items:center;gap:10px;padding:12px;background:var(--gls);border-radius:10px;border:1px solid var(--brd)">
                <i class="fas {{ $f[1] }}" style="color:var(--{{ $f[2] }});width:18px;text-align:center"></i>
                <span style="font-size:13px;color:var(--txt)">{{ $f[0] }}</span>
                <span style="margin-left:auto;font-size:10px;background:rgba(0,217,126,.15);color:var(--grn);padding:2px 7px;border-radius:5px;font-weight:600">ACTIVE</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ══════════════════════════ PERFORMANCE ═════════════════════════════ --}}
<div id="tab-performance" class="tab-pane">
    <div class="perf-grid">
        @php $cpuColor = $cpuPct>80?'val-crit':($cpuPct>60?'val-warn':'val-ok'); @endphp
        <div class="g perf-card">
            <div class="perf-card-head">
                <div><div class="s-label" style="margin:0">CPU Load</div><div class="perf-val {{ $cpuColor }}" id="perfCpu">{{ $cpuPct }}<span class="perf-unit">%</span></div></div>
                <div style="text-align:right;font-size:11px;color:var(--txt2)">1m: {{ round($cpuLoad[0],2) }}<br>5m: {{ round($cpuLoad[1],2) }}<br>15m: {{ round($cpuLoad[2],2) }}</div>
            </div>
            <div class="prog-bar" style="height:8px;margin-bottom:8px"><div class="prog-fill {{ $cpuPct>80?'fill-red':($cpuPct>60?'fill-ylw':'fill-grn') }}" style="width:{{ $cpuPct }}%"></div></div>
            <div style="font-size:11px;color:var(--txt2)">Load average (1/5/15 min)</div>
        </div>
        @php $ramColor = $ramUsedPct>85?'val-crit':($ramUsedPct>70?'val-warn':'val-ok'); @endphp
        <div class="g perf-card">
            <div class="perf-card-head">
                <div><div class="s-label" style="margin:0">RAM Usage</div><div class="perf-val {{ $ramColor }}" id="perfRam">{{ $ramUsedPct }}<span class="perf-unit">%</span></div></div>
                <div style="text-align:right;font-size:11px;color:var(--txt2)">{{ $ramUsedGb }}GB<br>/ {{ $ramTotalGb }}GB</div>
            </div>
            <div class="prog-bar" style="height:8px;margin-bottom:8px"><div class="prog-fill {{ $ramUsedPct>85?'fill-red':($ramUsedPct>70?'fill-ylw':'fill-grn') }}" style="width:{{ $ramUsedPct }}%"></div></div>
            <div style="font-size:11px;color:var(--txt2)">from /proc/meminfo</div>
        </div>
        @php $dskColor = $diskUsedPct>90?'val-crit':($diskUsedPct>75?'val-warn':'val-ok'); @endphp
        <div class="g perf-card">
            <div class="perf-card-head">
                <div><div class="s-label" style="margin:0">Disk</div><div class="perf-val {{ $dskColor }}" id="perfDisk">{{ $diskUsedPct }}<span class="perf-unit">%</span></div></div>
                <div style="text-align:right;font-size:11px;color:var(--txt2)">{{ $diskFreeGb }}GB free</div>
            </div>
            <div class="prog-bar" style="height:8px;margin-bottom:8px"><div class="prog-fill {{ $diskUsedPct>90?'fill-red':($diskUsedPct>75?'fill-ylw':'fill-grn') }}" style="width:{{ $diskUsedPct }}%"></div></div>
            <div style="font-size:11px;color:var(--txt2)">{{ $diskTotalGb }}GB total</div>
        </div>
        <div class="g perf-card">
            <div class="perf-card-head">
                <div><div class="s-label" style="margin:0">MySQL Threads</div><div class="perf-val val-neu" id="perfDb">{{ $mysqlThreads }}</div></div>
            </div>
            <div style="font-size:12px;color:var(--txt2);margin-top:6px">Total queries: <strong style="color:var(--blu)">{{ number_format($mysqlQPS) }}</strong></div>
            <div style="font-size:12px;color:var(--txt2);margin-top:4px">Slow queries: <strong style="color:{{ $mysqlSlowQ>10?'var(--red)':'var(--grn)' }}">{{ $mysqlSlowQ }}</strong></div>
        </div>
        <div class="g perf-card">
            <div class="perf-card-head">
                <div><div class="s-label" style="margin:0">Queue Jobs</div><div class="perf-val {{ $failedJobs>5?'val-crit':'val-ok' }}" id="perfQueue">{{ $failedJobs }}</div></div>
                <div style="font-size:11px;color:var(--txt2)">failed</div>
            </div>
            <div style="font-size:12px;color:var(--txt2);margin-top:6px">Pending: <strong style="color:var(--ylw)">{{ $pendingJobs }}</strong></div>
        </div>
        <div class="g perf-card">
            <div class="perf-card-head">
                <div><div class="s-label" style="margin:0">Redis</div><div class="perf-val val-neu">{{ $redisKeys }}</div></div>
                <div style="font-size:11px;color:var(--txt2)">keys</div>
            </div>
            <div style="font-size:12px;color:var(--txt2);margin-top:6px">Memory: <strong style="color:var(--blu)">{{ $redisMemUsed }}</strong></div>
        </div>
    </div>

    <div class="g" style="padding:20px;margin-top:16px">
        <div class="s-head" style="margin-bottom:16px">7-Day Security Events Trend</div>
        <canvas id="trendChart" height="120"></canvas>
    </div>
</div>

{{-- ══════════════════════════ AUDIT LOG ═══════════════════════════════ --}}
<div id="tab-audit" class="tab-pane">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
        <div class="s-label" style="margin:0">Complete Audit Trail (last 100 events)</div>
        <a href="{{ route('admin.security.audit.export') }}" class="btn-soc btn-glass" style="font-size:12px;padding:7px 14px">
            <i class="fas fa-download"></i> Export CSV (5000)
        </a>
    </div>
    <div class="audit-wrap">
        <table class="soc-table">
            <thead><tr><th>Time</th><th>Event</th><th>Severity</th><th>IP</th><th>User</th><th>Details</th></tr></thead>
            <tbody>
                @forelse($auditLog as $log)
                <tr>
                    <td style="white-space:nowrap;color:var(--txt3);font-size:11px">{{ $log->created_at->format('m-d H:i:s') }}</td>
                    <td style="font-family:monospace;font-size:11px;color:var(--txt)">{{ $log->event }}</td>
                    <td><span class="tl-sev sev-{{ $log->severity ?? 'info' }}">{{ strtoupper($log->severity ?? 'info') }}</span></td>
                    <td style="font-family:monospace;font-size:11px">{{ $log->ip_address ?? '—' }}</td>
                    <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $log->user_identifier ?? '—' }}</td>
                    <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:var(--txt3)">
                        @if(is_array($log->metadata))
                            {{ collect($log->metadata)->take(2)->map(fn($v,$k)=>"$k=$v")->implode(', ') }}
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;color:var(--txt3);padding:30px">No audit events.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ════════════════════════ QUICK ACTIONS ═════════════════════════════ --}}
<div id="tab-actions" class="tab-pane">
    <div class="qa-grid">
        <div class="g qa-card">
            <div class="qa-title"><i class="fas fa-ban" style="color:var(--red);margin-right:8px"></i>Block IP Address</div>
            <div class="qa-desc">Block an IP for 24 hours across all platform endpoints. Cached in Redis, enforced at middleware level.</div>
            <input class="soc-input" id="blockIpInput" type="text" placeholder="e.g. 192.168.1.100">
            <button class="btn-action btn-danger" onclick="quickAct('block_ip',document.getElementById('blockIpInput').value,this)">
                <i class="fas fa-ban"></i> Block IP for 24h
            </button>
        </div>
        <div class="g qa-card">
            <div class="qa-title"><i class="fas fa-broom" style="color:var(--ylw);margin-right:8px"></i>Clear Application Cache</div>
            <div class="qa-desc">Flush all cached data — feed cache, SOC data, Laravel cache. Active sessions are preserved.</div>
            <div style="height:42px"></div>
            <button class="btn-action btn-warn" onclick="quickAct('clear_cache','',this)">
                <i class="fas fa-broom"></i> Clear Cache
            </button>
        </div>
        <div class="g qa-card">
            <div class="qa-title"><i class="fas fa-rotate" style="color:var(--blu);margin-right:8px"></i>Restart Queue Workers</div>
            <div class="qa-desc">Sends graceful restart signal to all Supervisor queue workers. Workers finish current jobs then restart.</div>
            <div style="height:42px"></div>
            <button class="btn-action btn-info" onclick="quickAct('restart_queue','',this)">
                <i class="fas fa-rotate"></i> Restart Queue
            </button>
        </div>
        <div class="g qa-card">
            <div class="qa-title"><i class="fas fa-file-code" style="color:var(--pur);margin-right:8px"></i>Clear View Cache</div>
            <div class="qa-desc">Clears compiled Blade templates. Use after template changes if views aren't updating.</div>
            <div style="height:42px"></div>
            <button class="btn-action" style="background:rgba(168,85,247,.2);color:var(--pur);border:1px solid rgba(168,85,247,.3)" onclick="quickAct('clear_view_cache','',this)">
                <i class="fas fa-file-code"></i> Clear Views
            </button>
        </div>
        <div class="g qa-card">
            <div class="qa-title"><i class="fas fa-shield-halved" style="color:var(--grn);margin-right:8px"></i>Full Cache Reset</div>
            <div class="qa-desc">Clears all caches + view cache, then re-caches config. Most thorough reset — use after deployments.</div>
            <div style="height:42px"></div>
            <button class="btn-action btn-success" onclick="quickAct('clear_all_cache','',this)">
                <i class="fas fa-shield-halved"></i> Full Reset + Recache
            </button>
        </div>
    </div>
</div>

{{-- ════════════════════════ COMPLIANCE ════════════════════════════════ --}}
<div id="tab-compliance" class="tab-pane">
    <div class="comp-grid">
        <div class="g comp-card">
            <div class="comp-head"><i class="fas fa-shield-halved" style="color:var(--ora)"></i> OWASP Top 10</div>
            @foreach([
                ['Broken Access Control','Gate-based RBAC on all admin routes','tick-ok'],
                ['Cryptographic Failures','HTTPS everywhere + encrypted secrets','tick-ok'],
                ['Injection','Eloquent ORM + parameterized queries','tick-ok'],
                ['Insecure Design','Auth + RBAC + rate limiting','tick-ok'],
                ['Security Misconfiguration','CORS, headers, rate limiting set','tick-warn'],
                ['Vulnerable Components','Composer audit recommended','tick-warn'],
                ['Auth & Session Failures','OTP + Sanctum token rotation','tick-ok'],
                ['Software Integrity Failures','No external CDN scripts in prod','tick-ok'],
                ['Security Logging & Monitoring','Full audit log active (this dashboard)','tick-ok'],
                ['SSRF','Internal URL request filtering','tick-warn'],
            ] as $c)
            <div class="comp-item">
                <div class="comp-tick {{ $c[2] }}"><i class="fas {{ $c[2]==='tick-ok'?'fa-check':($c[2]==='tick-warn'?'fa-minus':'fa-xmark') }}"></i></div>
                <span>{{ $c[0] }}<span style="color:var(--txt3);font-size:10px;display:block">{{ $c[1] }}</span></span>
            </div>
            @endforeach
        </div>
        <div class="g comp-card">
            <div class="comp-head"><i class="fas fa-plug" style="color:var(--blu)"></i> API Security Top 10</div>
            @foreach([
                ['Broken Object Level Auth','Resource ownership enforced','tick-ok'],
                ['Broken Authentication','OTP + Sanctum token auth','tick-ok'],
                ['Broken Object Property Auth','Mass assignment protection ($fillable)','tick-ok'],
                ['Unrestricted Resource Consumption','Rate limiting active (Laravel Limiter)','tick-ok'],
                ['Broken Function Level Auth','Gate-based RBAC on all functions','tick-ok'],
                ['Unrestricted Sensitive API Access','Admin middleware guard + Gates','tick-ok'],
                ['Server Side Request Forgery','URL validation in place','tick-warn'],
                ['Security Misconfiguration','CORS + security headers set','tick-warn'],
                ['Improper Inventory Management','API versioned: /api/v1/','tick-ok'],
                ['Unsafe API Consumption','WaafiPay validated + signed requests','tick-ok'],
            ] as $c)
            <div class="comp-item">
                <div class="comp-tick {{ $c[2] }}"><i class="fas {{ $c[2]==='tick-ok'?'fa-check':($c[2]==='tick-warn'?'fa-minus':'fa-xmark') }}"></i></div>
                <span>{{ $c[0] }}<span style="color:var(--txt3);font-size:10px;display:block">{{ $c[1] }}</span></span>
            </div>
            @endforeach
        </div>
        <div class="g comp-card">
            <div class="comp-head"><i class="fas fa-user-shield" style="color:var(--pur)"></i> GDPR Readiness</div>
            @foreach([
                ['Data Minimization','Only required user fields collected','tick-ok'],
                ['Consent Mechanism','OTP-based explicit consent at signup','tick-ok'],
                ['Right to Deletion','Soft delete available (hard delete needed)','tick-warn'],
                ['Data Portability','Export not yet implemented','tick-fail'],
                ['Breach Notification','Admin audit log + alerts in place','tick-warn'],
                ['Encryption at Rest','Managed by DigitalOcean host','tick-warn'],
                ['Privacy by Design','No unnecessary tracking or profiling','tick-ok'],
            ] as $c)
            <div class="comp-item">
                <div class="comp-tick {{ $c[2] }}"><i class="fas {{ $c[2]==='tick-ok'?'fa-check':($c[2]==='tick-warn'?'fa-minus':'fa-xmark') }}"></i></div>
                <span>{{ $c[0] }}<span style="color:var(--txt3);font-size:10px;display:block">{{ $c[1] }}</span></span>
            </div>
            @endforeach
        </div>
        <div class="g comp-card">
            <div class="comp-head"><i class="fas fa-credit-card" style="color:var(--grn)"></i> PCI DSS (Baseline)</div>
            @foreach([
                ['No Card Data Stored','WaafiPay handles all tokenization','tick-ok'],
                ['HTTPS Everywhere','TLS 1.2+ enforced via Nginx','tick-ok'],
                ['Access Control','RBAC + admin middleware gates','tick-ok'],
                ['Vulnerability Scanning','Composer audit recommended','tick-warn'],
                ['Security Policy','Admin audit trail active','tick-ok'],
                ['Patch Management','Tracked via git + deploy pipeline','tick-ok'],
            ] as $c)
            <div class="comp-item">
                <div class="comp-tick {{ $c[2] }}"><i class="fas {{ $c[2]==='tick-ok'?'fa-check':($c[2]==='tick-warn'?'fa-minus':'fa-xmark') }}"></i></div>
                <span>{{ $c[0] }}<span style="color:var(--txt3);font-size:10px;display:block">{{ $c[1] }}</span></span>
            </div>
            @endforeach
        </div>
    </div>

    <div class="g" style="padding:20px;margin-top:16px">
        <div class="s-head" style="margin-bottom:16px">Compliance Score Summary</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px">
            @foreach([['OWASP Top 10',70,'fill-ylw'],['API Security',90,'fill-grn'],['GDPR',57,'fill-ylw'],['PCI DSS (Basic)',83,'fill-grn']] as $cs)
            <div>
                <div class="prog-label"><span>{{ $cs[0] }}</span><span style="font-weight:700;color:var(--txt)">{{ $cs[1] }}%</span></div>
                <div class="prog-bar" style="height:10px;border-radius:5px"><div class="prog-fill {{ $cs[2] }}" style="width:{{ $cs[1] }}%"></div></div>
            </div>
            @endforeach
        </div>
    </div>
</div>

</div>{{-- /soc --}}

<div id="refreshBar"><div id="refreshFill"></div></div>
<div id="toast"></div>

@push('scripts')
<script>
'use strict';
const CSRF       = '{{ csrf_token() }}';
const STATS_URL  = '{{ route("admin.security.stats") }}';
const ACTION_URL = '{{ route("admin.security.quick-action") }}';

// ── Theme toggle (default: light) ─────────────────────────────────────────
(function initTheme() {
    const saved = localStorage.getItem('soc_theme') || 'light';
    applyTheme(saved, false);
})();

function applyTheme(theme, save) {
    if (theme === 'dark') {
        document.body.setAttribute('data-soc-theme', 'dark');
        const lbl = document.getElementById('themeLabel');
        const ico = document.querySelector('#themeToggle .t-icon');
        if (lbl) lbl.textContent = 'Dark';
        if (ico) ico.textContent = '🌙';
    } else {
        document.body.removeAttribute('data-soc-theme');
        const lbl = document.getElementById('themeLabel');
        const ico = document.querySelector('#themeToggle .t-icon');
        if (lbl) lbl.textContent = 'Light';
        if (ico) ico.textContent = '☀️';
    }
    if (save !== false) localStorage.setItem('soc_theme', theme);
}

function toggleTheme() {
    const cur = document.body.getAttribute('data-soc-theme') === 'dark' ? 'dark' : 'light';
    applyTheme(cur === 'dark' ? 'light' : 'dark');
    // Redraw all canvas charts + update map style
    setTimeout(() => {
        drawGauge({{ $securityScore }});
        drawHourlyChart();
        drawDonutChart();
        drawTrendChart();
        if (_gmap) _gmap.setOptions({ styles: currentMapStyle() });
    }, 50);
}

// ── Tabs ───────────────────────────────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
});

// ── Theme helpers ─────────────────────────────────────────────────────────
function isDark() { return document.body.getAttribute('data-soc-theme') === 'dark'; }
function cssVar(v) { return getComputedStyle(document.body).getPropertyValue(v).trim(); }
function gridColor() { return isDark() ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.07)'; }
function labelColor() { return isDark() ? 'rgba(255,255,255,.35)' : '#94a3b8'; }

// ── Security Score Gauge ──────────────────────────────────────────────────
function drawGauge(score) {
    const c = document.getElementById('scoreGauge');
    if (!c) return;
    const ctx = c.getContext('2d');
    const color = score >= 90 ? '#00d97e' : score >= 70 ? '#ffc800' : score >= 50 ? '#ff7c00' : '#ff4757';
    const start = Math.PI * 0.75, end = Math.PI * 2.25;
    ctx.clearRect(0, 0, 120, 120);
    ctx.beginPath(); ctx.arc(60, 60, 50, start, end);
    ctx.strokeStyle = isDark() ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.1)';
    ctx.lineWidth = 10; ctx.lineCap = 'round'; ctx.stroke();
    ctx.beginPath(); ctx.arc(60, 60, 50, start, start + (end - start) * (score / 100));
    ctx.strokeStyle = color; ctx.lineWidth = 10; ctx.lineCap = 'round'; ctx.stroke();
    const num = document.getElementById('scoreNum');
    if (num) num.style.color = color;
}
drawGauge({{ $securityScore }});

// ── Hourly Bar Chart ───────────────────────────────────────────────────────
const _hourlyData = @json(array_column($hourlyData, 'count'));
function drawHourlyChart() {
    const c = document.getElementById('hourlyChart');
    if (!c) return;
    const ctx = c.getContext('2d');
    const data = _hourlyData;
    const W = c.offsetWidth || c.parentElement.offsetWidth, H = 160;
    c.width = W; c.height = H;
    const max = Math.max(...data, 1);
    const pad = { t: 10, r: 10, b: 28, l: 30 };
    const cw = W - pad.l - pad.r, ch = H - pad.t - pad.b;
    ctx.clearRect(0, 0, W, H);
    ctx.strokeStyle = gridColor(); ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
        const y = pad.t + ch - (i / 4) * ch;
        ctx.beginPath(); ctx.moveTo(pad.l, y); ctx.lineTo(pad.l + cw, y); ctx.stroke();
    }
    data.forEach((v, i) => {
        const bw = Math.max(2, cw / data.length - 3);
        const x = pad.l + (i / data.length) * cw + (cw / data.length - bw) / 2;
        const bh = (v / max) * ch || 2;
        const grd = ctx.createLinearGradient(0, pad.t + ch - bh, 0, pad.t + ch);
        grd.addColorStop(0, 'rgba(229,62,62,.9)'); grd.addColorStop(1, 'rgba(229,62,62,.15)');
        ctx.fillStyle = grd;
        ctx.beginPath(); ctx.roundRect(x, pad.t + ch - bh, bw, bh, [3, 3, 0, 0]); ctx.fill();
    });
    ctx.fillStyle = labelColor(); ctx.font = '10px system-ui'; ctx.textAlign = 'center';
    for (let i = 0; i < 24; i += 4) {
        const x = pad.l + (i / data.length) * cw + cw / (data.length * 2);
        ctx.fillText(i + ':00', x, H - 6);
    }
}
drawHourlyChart();

// ── Severity Distribution (Donut + Rows) ───────────────────────────────────
const _sevRaw = @json($severityData);
const _sevItems = [
    { label:'Critical', key:'critical', color:'#ef4444', icon:'fa-skull-crossbones' },
    { label:'High',     key:'high',     color:'#f97316', icon:'fa-triangle-exclamation' },
    { label:'Medium',   key:'medium',   color:'#eab308', icon:'fa-exclamation-circle' },
    { label:'Low',      key:'low',      color:'#3b82f6', icon:'fa-info-circle' },
    { label:'Info',     key:'info',     color:'#94a3b8', icon:'fa-circle-dot' },
    { label:'OK',       key:'ok',       color:'#10b981', icon:'fa-circle-check' },
];

function drawDonutChart() {
    const c = document.getElementById('sevDonut');
    if (!c) return;
    const ctx = c.getContext('2d');
    const W = 140, R = 56, r = 34, cx = 70, cy = 70;
    const vals  = _sevItems.map(it => _sevRaw[it.key]?.count || 0);
    const total = vals.reduce((a,b)=>a+b,0) || 1;
    const bg2   = cssVar('--bg2') || (isDark() ? '#0d1226' : '#ffffff');

    ctx.clearRect(0, 0, W, W);

    // Gap between slices
    let angle = -Math.PI / 2;
    const gap = 0.025;
    vals.forEach((v, i) => {
        const slice = (v / total) * Math.PI * 2;
        if (slice < 0.01) { angle += slice; return; }
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, R, angle + gap/2, angle + slice - gap/2);
        ctx.closePath();
        ctx.fillStyle = _sevItems[i].color;
        ctx.fill();
        angle += slice;
    });

    // Center hole
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI*2);
    ctx.fillStyle = bg2; ctx.fill();

    // Update total count
    const numEl = document.getElementById('sevTotalNum');
    if (numEl) numEl.textContent = vals.reduce((a,b)=>a+b,0);

    // Quick stats (right of donut — top 3 non-zero)
    const qEl = document.getElementById('sevQuickStats');
    if (qEl) {
        const top3 = _sevItems.map((it,i)=>({...it,v:vals[i]})).filter(x=>x.v>0).slice(0,3);
        qEl.innerHTML = top3.map(it => `
            <div style="display:flex;align-items:center;gap:8px">
                <div style="width:3px;height:28px;border-radius:2px;background:${it.color};flex-shrink:0"></div>
                <div>
                    <div style="font-size:16px;font-weight:800;color:var(--txt);line-height:1">${it.v}</div>
                    <div style="font-size:10px;color:var(--txt3);text-transform:uppercase;letter-spacing:.4px">${it.label}</div>
                </div>
            </div>
        `).join('');
    }

    // Rows
    const rowsEl = document.getElementById('sevRows');
    if (rowsEl) {
        const maxVal = Math.max(...vals, 1);
        rowsEl.innerHTML = _sevItems.map((it,i) => {
            const v   = vals[i];
            const pct = Math.round((v / total) * 100);
            const bar = Math.round((v / maxVal) * 100);
            const trend = v > 0 ? (v > (total/6) ? '↑' : '↓') : '—';
            const trendColor = trend==='↑' ? 'var(--red)' : (trend==='↓' ? 'var(--grn)' : 'var(--txt3)');
            return `<div class="sev-row">
                <div class="sev-row-dot" style="background:${it.color}"></div>
                <div class="sev-row-label">${it.label}</div>
                <div class="sev-row-bar-wrap">
                    <div class="sev-row-bar" style="width:${bar}%;background:${it.color};opacity:.85"></div>
                </div>
                <div class="sev-row-count">${v}</div>
                <div class="sev-row-pct">${pct}%</div>
                <div class="sev-row-trend" style="color:${trendColor}">${trend}</div>
            </div>`;
        }).join('');
    }
}
drawDonutChart();

// ── 7-Day Trend Chart ──────────────────────────────────────────────────────
const _trendRaw = @json($dailyEvents);
function drawTrendChart() {
    const c = document.getElementById('trendChart');
    if (!c) return;
    const ctx = c.getContext('2d');
    const W = c.offsetWidth || c.parentElement.offsetWidth, H = 120;
    c.width = W; c.height = H;
    if (!_trendRaw.length) return;
    const data = _trendRaw.map(r => r.count);
    const labels = _trendRaw.map(r => (r.date || '').slice(5));
    const max = Math.max(...data, 1);
    const pad = { t: 10, r: 10, b: 28, l: 40 };
    const cw = W - pad.l - pad.r, ch = H - pad.t - pad.b;
    const n = data.length;
    ctx.clearRect(0, 0, W, H);
    ctx.strokeStyle = gridColor(); ctx.lineWidth = 1;
    for (let i = 0; i <= 3; i++) {
        const y = pad.t + ch - (i / 3) * ch;
        ctx.beginPath(); ctx.moveTo(pad.l, y); ctx.lineTo(pad.l + cw, y); ctx.stroke();
    }
    const ora = isDark() ? '#FF8A00' : '#E87200';
    ctx.beginPath(); ctx.moveTo(pad.l, pad.t + ch);
    data.forEach((v, i) => ctx.lineTo(pad.l + (i / (n - 1 || 1)) * cw, pad.t + ch - (v / max) * ch));
    ctx.lineTo(pad.l + cw, pad.t + ch); ctx.closePath();
    const grd = ctx.createLinearGradient(0, pad.t, 0, pad.t + ch);
    grd.addColorStop(0, isDark() ? 'rgba(255,138,0,.3)' : 'rgba(232,114,0,.2)');
    grd.addColorStop(1, 'rgba(255,138,0,.02)');
    ctx.fillStyle = grd; ctx.fill();
    ctx.beginPath(); ctx.strokeStyle = ora; ctx.lineWidth = 2.5; ctx.lineJoin = 'round';
    data.forEach((v, i) => ctx[i === 0 ? 'moveTo' : 'lineTo'](pad.l + (i / (n - 1 || 1)) * cw, pad.t + ch - (v / max) * ch));
    ctx.stroke();
    ctx.fillStyle = labelColor(); ctx.font = '10px system-ui'; ctx.textAlign = 'center';
    labels.forEach((l, i) => ctx.fillText(l, pad.l + (i / (n - 1 || 1)) * cw, H - 6));
}
drawTrendChart();

// ── Real Attack Map ───────────────────────────────────────────────────────
const ATTACKER_GEO  = @json($attackerGeo);
const GMAPS_KEY     = '{{ $gmapsKey }}';
const SERVER_POS    = { lat: 2.0469, lng: 45.3182 };
const ATTACK_COLORS_DARK  = { brute_force:'#ff4757', credential_stuffing:'#ffc800', malware_upload:'#a855f7', api_abuse:'#4d9fff' };
const ATTACK_COLORS_LIGHT = { brute_force:'#dc2626', credential_stuffing:'#b45309', malware_upload:'#7c3aed', api_abuse:'#1d4ed8' };
function attackColor(type) { return (isDark() ? ATTACK_COLORS_DARK : ATTACK_COLORS_LIGHT)[type] || (isDark() ? '#ff4757' : '#dc2626'); }

const DARK_MAP_STYLE = [
    {elementType:'geometry',stylers:[{color:'#0a0f1e'}]},
    {elementType:'labels',stylers:[{visibility:'off'}]},
    {featureType:'water',stylers:[{color:'#06152e'}]},
    {featureType:'road',stylers:[{visibility:'off'}]},
    {featureType:'poi',stylers:[{visibility:'off'}]},
    {featureType:'transit',stylers:[{visibility:'off'}]},
    {featureType:'administrative.country',elementType:'geometry.stroke',stylers:[{color:'#1e2d4a'}]},
    {featureType:'administrative.province',stylers:[{visibility:'off'}]},
    {featureType:'landscape',stylers:[{color:'#0d1226'}]},
];

const LIGHT_MAP_STYLE = [
    {elementType:'geometry',stylers:[{color:'#e8edf3'}]},
    {elementType:'labels',stylers:[{visibility:'off'}]},
    {featureType:'water',stylers:[{color:'#b8d4ea'}]},
    {featureType:'road',stylers:[{visibility:'off'}]},
    {featureType:'poi',stylers:[{visibility:'off'}]},
    {featureType:'transit',stylers:[{visibility:'off'}]},
    {featureType:'administrative.country',elementType:'geometry.stroke',stylers:[{color:'#94a3b8'}]},
    {featureType:'administrative.province',stylers:[{visibility:'off'}]},
    {featureType:'landscape',stylers:[{color:'#dce4ee'}]},
];

function currentMapStyle() { return isDark() ? DARK_MAP_STYLE : LIGHT_MAP_STYLE; }
function currentMapBg()    { return isDark() ? '#0a0f1e' : '#e8edf3'; }

let _gmap;

function initMap() {
    const el = document.getElementById('gmap');
    if (!el || !window.google) return;

    _gmap = new google.maps.Map(el, {
        center: { lat: 25, lng: 15 },
        zoom: 2,
        styles: currentMapStyle(),
        disableDefaultUI: true,
        gestureHandling: 'none',
        backgroundColor: currentMapBg(),
    });

    const bounds = new google.maps.LatLngBounds();
    const srvLL  = new google.maps.LatLng(SERVER_POS.lat, SERVER_POS.lng);
    bounds.extend(srvLL);

    // ── Server marker
    new google.maps.Marker({
        position: srvLL, map: _gmap, zIndex: 200,
        title: 'eSahlan Server — Mogadishu',
        icon: { path: google.maps.SymbolPath.CIRCLE, scale: 11,
                fillColor: '#00d97e', fillOpacity: 1,
                strokeColor: '#ffffff', strokeWeight: 2.5 },
    });

    // Server pulse rings (static circles)
    [80000, 180000, 320000].forEach((r, i) => {
        new google.maps.Circle({
            center: srvLL, radius: r, map: _gmap,
            strokeColor: '#00d97e', strokeOpacity: 0.35 - i * 0.08, strokeWeight: 1,
            fillColor: '#00d97e',   fillOpacity: 0.04 - i * 0.01,
        });
    });

    if (ATTACKER_GEO.length === 0) {
        // No attackers yet — keep world view
        return;
    }

    ATTACKER_GEO.forEach(a => {
        const col  = attackColor(a.type);
        const aLL  = new google.maps.LatLng(a.lat, a.lng);
        const sz   = Math.min(13, 7 + Math.sqrt(a.cnt));
        bounds.extend(aLL);

        // Attacker marker
        const mk = new google.maps.Marker({
            position: aLL, map: _gmap, zIndex: 100,
            title: `${a.ip} (${a.cnt} attempts)`,
            icon: { path: google.maps.SymbolPath.CIRCLE, scale: sz,
                    fillColor: col, fillOpacity: 0.95,
                    strokeColor: '#fff', strokeWeight: 2 },
        });

        // Glow ring around attacker
        new google.maps.Circle({
            center: aLL, radius: 60000 * Math.min(4, a.cnt), map: _gmap,
            strokeColor: col, strokeOpacity: 0.4, strokeWeight: 1.5,
            fillColor: col,   fillOpacity: 0.1,
        });

        // Info window on click — theme-aware
        const dark = isDark();
        const iwBg  = dark ? '#0d1226' : '#ffffff';
        const iwTxt = dark ? '#fff'    : '#1a202c';
        const iwSub = dark ? 'rgba(255,255,255,.55)' : '#4a5568';
        const iw = new google.maps.InfoWindow({
            content: `<div style="background:${iwBg};color:${iwTxt};padding:12px 16px;border-radius:10px;font-size:12px;min-width:200px;border:1px solid ${col};line-height:1.8;box-shadow:0 4px 16px rgba(0,0,0,.15)">
                <strong style="color:${col};text-transform:uppercase;letter-spacing:.5px">${a.type.replace(/_/g,' ')}</strong><br>
                <span style="font-family:monospace;font-size:13px;color:${iwTxt}">${a.ip}</span><br>
                <span style="color:${iwSub}">📍 ${[a.city, a.country].filter(Boolean).join(', ')}</span><br>
                <span style="color:${iwSub}">🔁 <strong style="color:${iwTxt}">${a.cnt}</strong> attempts in 48h</span>
            </div>`,
        });
        mk.addListener('click', () => iw.open(_gmap, mk));

        // Geodesic attack line
        new google.maps.Polyline({
            path: [aLL, srvLL], geodesic: true, map: _gmap,
            strokeColor: col, strokeOpacity: isDark() ? 0.22 : 0.5, strokeWeight: 2,
        });

        // Animated moving dot along the attack path
        spawnAttackDot(a.lat, a.lng, col);
    });

    // Auto-zoom to fit all attackers + server
    _gmap.fitBounds(bounds, { top: 40, right: 40, bottom: 60, left: 40 });
}

// Animates a dot from attacker → server using bezier arc
function spawnAttackDot(srcLat, srcLng, col) {
    let t = Math.random();
    let alive = true;

    const dot = new google.maps.Marker({
        map: _gmap, zIndex: 150,
        icon: { path: google.maps.SymbolPath.CIRCLE, scale: 4,
                fillColor: col, fillOpacity: 1,
                strokeColor: '#fff', strokeWeight: 0.8 },
    });
    _mapMarkers.push(dot);

    const midLat = (srcLat + SERVER_POS.lat) / 2 + 18;
    const midLng = (srcLng + SERVER_POS.lng) / 2;

    let last = null;
    function step(ts) {
        if (!alive) { dot.setMap(null); return; }
        if (!last) last = ts;
        t += (ts - last) / 3200;
        last = ts;
        if (t >= 1) t = 0;
        const u = 1 - t;
        dot.setPosition({
            lat: u*u*srcLat + 2*u*t*midLat + t*t*SERVER_POS.lat,
            lng: u*u*srcLng + 2*u*t*midLng + t*t*SERVER_POS.lng,
        });
        const id = requestAnimationFrame(step);
        _animFrames.push(id);
    }
    const id = requestAnimationFrame(step);
    _animFrames.push(id);

    // Return stop fn so refreshMap can kill it
    return () => { alive = false; };
}

// Bootstrap Google Maps
if (GMAPS_KEY) {
    window.initMap = initMap;
    const s = document.createElement('script');
    s.src   = `https://maps.googleapis.com/maps/api/js?key=${GMAPS_KEY}&callback=initMap`;
    s.async = true; s.defer = true;
    document.head.appendChild(s);
}

// ── Auto-refresh ──────────────────────────────────────────────────────────
let countdown = 30;
const fillEl = document.getElementById('refreshFill');
const countEl = document.getElementById('countdown');

setInterval(() => {
    countdown--;
    if (countEl) countEl.textContent = countdown;
    if (fillEl) fillEl.style.width = ((countdown / 30) * 100) + '%';
    if (countdown <= 0) { socRefresh(); countdown = 30; }
}, 1000);

async function socRefresh() {
    try {
        const resp = await fetch(STATS_URL, { headers: { 'Accept': 'application/json' } });
        if (!resp.ok) return;
        const d = await resp.json();
        const up = (id, val) => { const el = document.getElementById(id); if (el && val !== undefined) el.textContent = val; };
        up('kCritical',    d.criticalEvents1h);
        up('kFailed',      d.failedLogins1h);
        up('kBlocked',     d.blockedIps24h);
        up('kMalware',     d.malware24h);
        up('kSessions',    d.activeSessions);
        up('kSuccessRate', (d.successRate ?? '') + '%');
        up('kRateLimit',   d.rateLimitHits24h);
        up('kNewUsers',    d.newUsers24h);
        up('hdrThreats',   d.threatCount);
        up('hdrSessions',  d.activeSessions);
        up('hdrFailed',    d.failedLogins1h);
        up('liveTotal',    d.totalSessions);
        up('scoreNum',     d.securityScore);
        up('perfCpu',      (d.cpuPct ?? '') + '%');
        up('perfRam',      (d.ramUsedPct ?? '') + '%');
        up('perfDisk',     (d.diskUsedPct ?? '') + '%');
        up('perfDb',       d.mysqlThreads);
        up('perfQueue',    d.failedJobs);

        // ── Live map update ─────────────────────────────────────────────
        if (d.attackerGeo && _gmap && window.google) {
            refreshMap(d.attackerGeo);
        }
    } catch (e) {}
}

// Clears & re-plots all markers/lines/dots on the map with fresh geo data
let _mapMarkers = [], _mapCircles = [], _mapLines = [], _animFrames = [];

function refreshMap(geoData) {
    // Stop old animation frames
    _animFrames.forEach(id => cancelAnimationFrame(id));
    _animFrames = [];

    // Remove old overlays
    _mapMarkers.forEach(m => m.setMap(null));
    _mapCircles.forEach(c => c.setMap(null));
    _mapLines.forEach(l => l.setMap(null));
    _mapMarkers = []; _mapCircles = []; _mapLines = [];

    const bounds = new google.maps.LatLngBounds();
    const srvLL  = new google.maps.LatLng(SERVER_POS.lat, SERVER_POS.lng);
    bounds.extend(srvLL);

    // Update badge count
    const badge = document.getElementById('mapAttackerCount');
    if (badge) badge.textContent = geoData.length;

    if (geoData.length === 0) return;

    geoData.forEach(a => {
        const col = attackColor(a.type);
        const aLL = new google.maps.LatLng(a.lat, a.lng);
        const sz  = Math.min(13, 7 + Math.sqrt(a.cnt));
        bounds.extend(aLL);

        const mk = new google.maps.Marker({
            position: aLL, map: _gmap, zIndex: 100,
            title: `${a.ip} (${a.cnt} attempts)`,
            icon: { path: google.maps.SymbolPath.CIRCLE, scale: sz,
                    fillColor: col, fillOpacity: 0.95,
                    strokeColor: '#fff', strokeWeight: 2 },
        });
        _mapMarkers.push(mk);

        const ring = new google.maps.Circle({
            center: aLL, radius: 60000 * Math.min(4, a.cnt), map: _gmap,
            strokeColor: col, strokeOpacity: 0.4, strokeWeight: 1.5,
            fillColor: col, fillOpacity: 0.1,
        });
        _mapCircles.push(ring);

        const dark = isDark();
        const iwBg = dark ? '#0d1226' : '#ffffff', iwTxt = dark ? '#fff' : '#1a202c', iwSub = dark ? 'rgba(255,255,255,.55)' : '#4a5568';
        const iw = new google.maps.InfoWindow({
            content: `<div style="background:${iwBg};color:${iwTxt};padding:12px 16px;border-radius:10px;font-size:12px;min-width:200px;border:1px solid ${col};line-height:1.8;box-shadow:0 4px 16px rgba(0,0,0,.15)">
                <strong style="color:${col};text-transform:uppercase;letter-spacing:.5px">${a.type.replace(/_/g,' ')}</strong><br>
                <span style="font-family:monospace;font-size:13px;color:${iwTxt}">${a.ip}</span><br>
                <span style="color:${iwSub}">📍 ${[a.city, a.country].filter(Boolean).join(', ')}</span><br>
                <span style="color:${iwSub}">🔁 <strong style="color:${iwTxt}">${a.cnt}</strong> attempts in 48h</span>
            </div>`,
        });
        mk.addListener('click', () => iw.open(_gmap, mk));

        const line = new google.maps.Polyline({
            path: [aLL, srvLL], geodesic: true, map: _gmap,
            strokeColor: col, strokeOpacity: isDark() ? 0.22 : 0.5, strokeWeight: 2,
        });
        _mapLines.push(line);

        spawnAttackDot(a.lat, a.lng, col);
    });

    _gmap.fitBounds(bounds, { top: 40, right: 40, bottom: 60, left: 40 });
}

// ── Quick Actions ─────────────────────────────────────────────────────────
async function quickAct(action, target, btn) {
    if (!target && action === 'block_ip') { showToast('Enter an IP address', 'err'); return; }
    const orig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Working…'; }
    try {
        const res = await fetch(ACTION_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ action, target })
        });
        const d = await res.json();
        showToast(d.message || 'Done', d.success ? 'ok' : 'err');
    } catch (e) {
        showToast('Request failed', 'err');
    }
    if (btn) { btn.disabled = false; btn.innerHTML = orig; }
}

function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'show ' + type;
    clearTimeout(t._tmr);
    t._tmr = setTimeout(() => { t.className = ''; }, 3500);
}
</script>
@endpush
@endsection
