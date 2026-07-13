@extends('admin.layouts.app')
@section('title', 'Community Users')
@section('content')

<style>
:root {
  --accent: #6C63FF;
  --accent2: #EC4899;
  --green: #10B981;
  --amber: #F59E0B;
  --blue: #3B82F6;
  --red: #EF4444;
  --surface: #fff;
  --bg: #F1F5F9;
  --border: #E8ECF4;
  --text: #0F172A;
  --muted: #64748B;
  --faint: #F8FAFC;
  --radius: 16px;
  --shadow: 0 2px 12px rgba(0,0,0,.07);
  --shadow-lg: 0 8px 32px rgba(0,0,0,.12);
}

/* ═══ Page Layout ═══════════════════════════════════════════════════════════ */
.cu-page { padding: 0; background: var(--bg); min-height: 100vh; }

/* ═══ Top Hero Bar ══════════════════════════════════════════════════════════ */
.cu-hero {
  background: linear-gradient(135deg, #0a0040 0%, #1a0070 50%, #0d004d 100%);
  padding: 28px 32px 80px;
  position: relative;
  overflow: hidden;
}
.cu-hero::before {
  content: '';
  position: absolute;
  width: 400px; height: 400px;
  background: radial-gradient(circle, rgba(108,99,255,.3) 0%, transparent 70%);
  top: -100px; right: -80px; pointer-events: none;
}
.cu-hero::after {
  content: '';
  position: absolute;
  width: 300px; height: 300px;
  background: radial-gradient(circle, rgba(236,72,153,.2) 0%, transparent 70%);
  bottom: -80px; left: 100px; pointer-events: none;
}
.cu-hero h1 { font-size: 26px; font-weight: 800; color: #fff; margin: 0 0 4px; letter-spacing: -.3px; }
.cu-hero p { font-size: 13px; color: rgba(255,255,255,.55); margin: 0; }

/* ═══ Stats Float ════════════════════════════════════════════════════════════ */
.stats-float {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 14px;
  margin: -44px 32px 24px;
  position: relative;
  z-index: 10;
}
.sf-card {
  background: #fff;
  border-radius: 14px;
  padding: 16px 18px;
  box-shadow: 0 4px 20px rgba(0,0,0,.1);
  display: flex;
  align-items: center;
  gap: 12px;
  border: 1px solid rgba(255,255,255,.8);
  transition: transform .2s, box-shadow .2s;
}
.sf-card:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,0,0,.13); }
.sf-icon {
  width: 44px; height: 44px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; flex-shrink: 0;
}
.sf-card .label { font-size: 10px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .6px; }
.sf-card .val { font-size: 22px; font-weight: 800; color: var(--text); line-height: 1.1; }
.sf-card .sub { font-size: 10px; color: var(--muted); margin-top: 1px; }

/* ═══ Tabs ════════════════════════════════════════════════════════════════════ */
.cu-tabs {
  display: flex;
  gap: 0;
  padding: 0 32px;
  margin-bottom: 24px;
  border-bottom: 2px solid var(--border);
}
.cu-tab {
  padding: 11px 22px;
  font-size: 13px;
  font-weight: 600;
  color: var(--muted);
  border: none;
  background: none;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
  display: flex;
  align-items: center;
  gap: 7px;
  transition: color .15s;
}
.cu-tab.active { color: var(--accent); border-bottom-color: var(--accent); }
.cu-tab:hover:not(.active) { color: var(--text); }
.tab-badge {
  background: var(--accent);
  color: #fff;
  font-size: 9px;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 20px;
  line-height: 1.4;
}

/* ═══ Content Wrapper ════════════════════════════════════════════════════════ */
.cu-body { padding: 0 32px 32px; }

/* ═══ Filter Bar ═════════════════════════════════════════════════════════════ */
.filter-strip {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 12px 16px;
  margin-bottom: 20px;
  box-shadow: var(--shadow);
}
.filter-strip input[type=text] {
  flex: 1;
  min-width: 200px;
  border: none;
  outline: none;
  font-size: 13px;
  color: var(--text);
  background: transparent;
}
.filter-strip select {
  border: 1.5px solid var(--border);
  border-radius: 9px;
  padding: 6px 10px;
  font-size: 12px;
  color: var(--text);
  background: var(--faint);
  outline: none;
  cursor: pointer;
}
.filter-strip select:focus { border-color: var(--accent); }
.fs-divider { width: 1px; height: 24px; background: var(--border); flex-shrink: 0; }
.btn-apply {
  background: linear-gradient(135deg, var(--accent), #8B5CF6);
  color: #fff;
  border: none;
  border-radius: 10px;
  padding: 8px 18px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 6px;
}
.btn-clear { font-size: 12px; color: var(--muted); text-decoration: none; white-space: nowrap; }
.btn-clear:hover { color: var(--red); }

/* ═══ Results Meta ═══════════════════════════════════════════════════════════ */
.results-meta { font-size: 12px; color: var(--muted); margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; }

/* ═══ User Cards ═════════════════════════════════════════════════════════════ */
.ug-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
  gap: 16px;
}
.ug-card {
  background: #fff;
  border-radius: 18px;
  overflow: hidden;
  border: 1.5px solid var(--border);
  box-shadow: var(--shadow);
  transition: border-color .2s, box-shadow .2s, transform .2s;
  cursor: pointer;
  position: relative;
}
.ug-card:hover {
  border-color: var(--accent);
  box-shadow: 0 8px 32px rgba(108,99,255,.15);
  transform: translateY(-3px);
}
.ug-cover {
  height: 72px;
  position: relative;
}
.ug-cover-img { width: 100%; height: 100%; object-fit: cover; }
.ug-av-ring {
  position: absolute;
  bottom: -24px;
  left: 18px;
  width: 52px; height: 52px;
  border-radius: 50%;
  border: 3px solid #fff;
  box-shadow: 0 2px 8px rgba(0,0,0,.15);
  overflow: hidden;
  background: #fff;
}
.ug-av-ring img { width: 100%; height: 100%; object-fit: cover; }
.ug-av-init {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; font-weight: 800; color: #fff;
}
.verified-crown {
  position: absolute;
  top: 8px; right: 10px;
  background: rgba(255,255,255,.2);
  backdrop-filter: blur(8px);
  border-radius: 20px;
  padding: 3px 8px;
  font-size: 10px;
  font-weight: 700;
  color: #fff;
  display: flex;
  align-items: center;
  gap: 3px;
  border: 1px solid rgba(255,255,255,.3);
}
.ug-body { padding: 32px 18px 12px; }
.ug-name {
  font-size: 15px; font-weight: 800; color: var(--text);
  display: flex; align-items: center; gap: 6px;
  margin-bottom: 2px;
}
.ug-name .verify-dot { width: 14px; height: 14px; background: var(--blue); border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.ug-name .verify-dot svg { width: 8px; height: 8px; }
.ug-handle { font-size: 11px; color: var(--muted); margin-bottom: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ug-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 12px; }
.chip {
  font-size: 10px; font-weight: 600;
  padding: 3px 9px; border-radius: 20px;
  display: flex; align-items: center; gap: 3px;
}
.chip-purple { background: #EDE9FE; color: #5B21B6; }
.chip-pink   { background: #FCE7F3; color: #9D174D; }
.chip-green  { background: #D1FAE5; color: #065F46; }
.chip-blue   { background: #DBEAFE; color: #1E40AF; }
.chip-amber  { background: #FEF3C7; color: #92400E; }
.chip-gray   { background: #F1F5F9; color: #64748B; }
.ug-stats {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 1px;
  background: var(--border);
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 12px;
}
.ug-stat {
  background: var(--faint);
  padding: 8px 4px;
  text-align: center;
}
.ug-stat .n { font-size: 15px; font-weight: 800; color: var(--text); }
.ug-stat .l { font-size: 9px; text-transform: uppercase; letter-spacing: .4px; color: var(--muted); font-weight: 600; }
.ug-actions { display: flex; gap: 6px; }
.ua-btn {
  flex: 1;
  border: none;
  border-radius: 9px;
  padding: 7px 6px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  transition: filter .15s;
}
.ua-btn:hover { filter: brightness(.92); }
.ua-view   { background: #EDE9FE; color: #5B21B6; }
.ua-verify { background: #D1FAE5; color: #065F46; }
.ua-verify.on { background: #FEE2E2; color: #991B1B; }
.ua-chat   { background: #DBEAFE; color: #1E40AF; }

/* ═══ Analytics ══════════════════════════════════════════════════════════════ */
.analytics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.an-card {
  background: #fff;
  border-radius: 18px;
  padding: 24px;
  border: 1px solid var(--border);
  box-shadow: var(--shadow);
}
.an-card h3 { font-size: 13px; font-weight: 700; color: var(--text); margin: 0 0 20px; display: flex; align-items: center; gap: 8px; }
.an-card h3 .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.donut-wrap { display: flex; align-items: center; gap: 28px; }
.donut-legend { display: flex; flex-direction: column; gap: 10px; flex: 1; }
.legend-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.legend-dot { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
.legend-label { font-size: 12px; color: var(--muted); flex: 1; }
.legend-val { font-size: 13px; font-weight: 700; color: var(--text); }
.legend-pct { font-size: 10px; color: var(--muted); min-width: 28px; text-align: right; }
.cbar-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.cbar-label { font-size: 12px; color: var(--text); font-weight: 600; min-width: 72px; }
.cbar-track { flex: 1; height: 8px; background: var(--faint); border-radius: 4px; overflow: hidden; }
.cbar-fill { height: 100%; border-radius: 4px; background: linear-gradient(90deg, var(--accent), #8B5CF6); }
.cbar-cnt { font-size: 11px; font-weight: 700; color: var(--muted); min-width: 24px; text-align: right; }
.kpi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.kpi-box { background: var(--faint); border-radius: 12px; padding: 14px; border: 1px solid var(--border); }
.kpi-box .kv { font-size: 24px; font-weight: 800; color: var(--text); }
.kpi-box .kl { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); font-weight: 600; margin-top: 2px; }

/* ═══ Chat Monitor ════════════════════════════════════════════════════════════ */
.chat-shell {
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 0;
  background: #fff;
  border-radius: 18px;
  border: 1px solid var(--border);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
  height: calc(100vh - 280px);
  min-height: 500px;
}
/* Left sidebar */
.chat-sidebar {
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.chat-sidebar-head {
  padding: 16px;
  border-bottom: 1px solid var(--border);
  background: var(--faint);
}
.chat-sidebar-head h3 { font-size: 13px; font-weight: 800; color: var(--text); margin: 0 0 10px; }
.chat-search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fff;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  padding: 7px 12px;
}
.chat-search-box i { color: var(--muted); font-size: 12px; }
.chat-search-box input { border: none; outline: none; font-size: 12px; flex: 1; color: var(--text); background: transparent; }
.chat-list-el { overflow-y: auto; flex: 1; }
.chat-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  cursor: pointer;
  border-bottom: 1px solid rgba(0,0,0,.04);
  transition: background .1s;
  position: relative;
}
.chat-item:hover { background: var(--faint); }
.chat-item.selected { background: #F0EEFF; border-left: 3px solid var(--accent); }
.chat-item.selected .chat-item-name { color: var(--accent); }
.chat-avs { display: flex; flex-shrink: 0; }
.chat-av {
  width: 38px; height: 38px; border-radius: 50%;
  border: 2px solid #fff;
  background: #EDE9FE;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; color: var(--accent);
  margin-right: -10px;
  overflow: hidden;
}
.chat-av img { width: 100%; height: 100%; object-fit: cover; }
.chat-item-body { flex: 1; min-width: 0; }
.chat-item-name { font-size: 12px; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-item-last { font-size: 11px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
.chat-item-right { flex-shrink: 0; text-align: right; }
.chat-item-cnt { font-size: 11px; font-weight: 700; color: var(--accent); }
.chat-item-time { font-size: 10px; color: var(--muted); margin-top: 2px; }
.chat-empty { text-align: center; padding: 60px 20px; }
.chat-empty i { font-size: 32px; color: #CBD5E1; display: block; margin-bottom: 10px; }
.chat-empty p { font-size: 12px; color: var(--muted); }

/* Right thread */
.chat-main { display: flex; flex-direction: column; overflow: hidden; }
.thread-topbar {
  padding: 14px 20px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: 12px;
  background: var(--faint);
  flex-shrink: 0;
}
.thread-topbar .tt-info { flex: 1; }
.thread-topbar .tt-name { font-size: 14px; font-weight: 800; color: var(--text); }
.thread-topbar .tt-sub { font-size: 11px; color: var(--muted); }
.thread-topbar .tt-badge { background: var(--faint); border: 1px solid var(--border); color: var(--muted); font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
.thread-area { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 12px; background: #F8F9FC; }
.thread-placeholder {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--muted);
  gap: 12px;
}
.thread-placeholder i { font-size: 40px; color: #CBD5E1; }
.thread-placeholder h4 { font-size: 14px; font-weight: 700; color: #94A3B8; }
.thread-placeholder p { font-size: 12px; color: #CBD5E1; }
/* Messages */
.msg-row { display: flex; gap: 8px; align-items: flex-end; }
.msg-row.right { flex-direction: row-reverse; }
.msg-av2 {
  width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
  background: #EDE9FE; display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700; color: var(--accent); overflow: hidden;
}
.msg-av2 img { width: 100%; height: 100%; object-fit: cover; }
.msg-col { max-width: 68%; display: flex; flex-direction: column; gap: 2px; }
.msg-name { font-size: 9px; font-weight: 700; color: var(--muted); padding: 0 4px; }
.msg-row.right .msg-name { text-align: right; }
.bubble {
  padding: 9px 13px;
  border-radius: 16px;
  font-size: 12px;
  line-height: 1.55;
  position: relative;
}
.bubble.left  { background: #fff; color: var(--text); border-bottom-left-radius: 4px; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
.bubble.right { background: linear-gradient(135deg, var(--accent), #8B5CF6); color: #fff; border-bottom-right-radius: 4px; }
.bubble .btime { font-size: 9px; opacity: .5; margin-top: 3px; text-align: right; }
.bubble.deleted { background: #F1F5F9; color: #94A3B8; font-style: italic; }
.msg-del-btn {
  background: #FEE2E2;
  color: var(--red);
  border: none;
  border-radius: 6px;
  padding: 4px 8px;
  font-size: 9px;
  font-weight: 700;
  cursor: pointer;
  align-self: center;
  opacity: 0;
  transition: opacity .15s;
  flex-shrink: 0;
}
.msg-row:hover .msg-del-btn { opacity: 1; }
/* Date separator */
.date-sep {
  display: flex; align-items: center; gap: 10px;
  font-size: 10px; font-weight: 600; color: var(--muted);
  text-transform: uppercase; letter-spacing: .5px;
  margin: 8px 0;
}
.date-sep::before, .date-sep::after { content:''; flex:1; height:1px; background:var(--border); }
/* Thread footer */
.thread-footer {
  padding: 12px 20px;
  border-top: 1px solid var(--border);
  background: #fff;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 11px;
  color: var(--muted);
  flex-shrink: 0;
}
.thread-footer .live-pulse { display: flex; align-items: center; gap: 5px; }
.live-dot { width: 7px; height: 7px; background: var(--green); border-radius: 50%; animation: lp 1.4s infinite; }
@keyframes lp { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:.3;transform:scale(.7);} }

/* ═══ Slide Panel ═════════════════════════════════════════════════════════════ */
.sp-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.5); backdrop-filter:blur(4px); z-index:9000; }
.sp-overlay.open { display:block; }
.sp {
  position: fixed; top: 0; right: -560px;
  width: 540px; height: 100vh;
  background: #fff;
  z-index: 9001;
  box-shadow: -8px 0 40px rgba(0,0,0,.18);
  transition: right .28s cubic-bezier(.4,0,.2,1);
  display: flex; flex-direction: column;
  overflow: hidden;
}
.sp.open { right: 0; }
.sp-head {
  background: linear-gradient(135deg, #0a0040, #1a0070);
  padding: 20px;
  display: flex; align-items: center; gap: 12px;
  flex-shrink: 0;
}
.sp-head h3 { font-size: 16px; font-weight: 800; color: #fff; flex: 1; margin: 0; }
.sp-close { background: rgba(255,255,255,.15); border: none; color: #fff; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; }
.sp-close:hover { background: rgba(255,255,255,.25); }
.sp-hero { background: linear-gradient(135deg, #0a0040 0%, #1a0070 100%); padding: 0 20px 24px; display: flex; align-items: flex-end; gap: 16px; flex-shrink: 0; }
.sp-av { width: 72px; height: 72px; border-radius: 50%; border: 4px solid rgba(255,255,255,.3); overflow: hidden; background: #EDE9FE; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 800; color: var(--accent); flex-shrink: 0; }
.sp-av img { width: 100%; height: 100%; object-fit: cover; }
.sp-av-info .sp-name { font-size: 18px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 6px; }
.sp-av-info .sp-handle { font-size: 12px; color: rgba(255,255,255,.55); }
.sp-body { flex: 1; overflow-y: auto; padding: 20px; }
.sp-section { margin-bottom: 20px; }
.sp-section-title { font-size: 10px; text-transform: uppercase; letter-spacing: .7px; font-weight: 700; color: var(--muted); margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.sp-section-title::after { content:''; flex:1; height:1px; background:var(--border); }
.ig2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.ig3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
.info-box { background: var(--faint); border: 1px solid var(--border); border-radius: 10px; padding: 10px 12px; }
.info-box .ib-k { font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); font-weight: 600; }
.info-box .ib-v { font-size: 13px; font-weight: 700; color: var(--text); margin-top: 2px; }
.stat-big { background: linear-gradient(135deg, var(--faint), #EDE9FE20); border: 1px solid var(--border); border-radius: 12px; padding: 14px; text-align: center; }
.stat-big .sbv { font-size: 22px; font-weight: 800; color: var(--text); }
.stat-big .sbl { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.ppost { display: flex; gap: 10px; align-items: flex-start; background: var(--faint); border: 1px solid var(--border); border-radius: 10px; padding: 10px; margin-bottom: 6px; }
.ppost .ptype { background: #EDE9FE; color: var(--accent); font-size: 9px; font-weight: 700; padding: 2px 8px; border-radius: 20px; white-space: nowrap; flex-shrink: 0; }
.ppost .ptxt { font-size: 12px; color: var(--text); font-weight: 500; line-height: 1.4; flex: 1; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.ppost .pmeta { font-size: 10px; color: var(--muted); margin-top: 4px; }
.interest-chip { background: linear-gradient(135deg, #EDE9FE, #FCE7F3); color: var(--accent); font-size: 10px; font-weight: 600; padding: 3px 10px; border-radius: 20px; display: inline-block; margin: 2px; }
.sp-actions { padding: 16px 20px; border-top: 1px solid var(--border); display: flex; gap: 8px; flex-shrink: 0; }
.sp-act-btn { flex: 1; padding: 10px; border: none; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: filter .15s; }
.sp-act-btn:hover { filter: brightness(.9); }
.sp-act-chat  { background: #DBEAFE; color: #1E40AF; }
.sp-act-verify { background: #D1FAE5; color: #065F46; }
.sp-act-verify.on { background: #FEE2E2; color: #991B1B; }

/* ═══ Pagination ═════════════════════════════════════════════════════════════ */
.pag-wrap { display: flex; justify-content: center; margin-top: 28px; }
.pag-wrap nav { display: flex; gap: 4px; align-items: center; }
.pag-wrap a, .pag-wrap span {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 34px; height: 34px;
  border: 1.5px solid var(--border); border-radius: 9px;
  font-size: 12px; font-weight: 600; color: var(--muted);
  text-decoration: none; padding: 0 8px;
  transition: all .15s;
}
.pag-wrap a:hover { border-color: var(--accent); color: var(--accent); }
.pag-wrap span[aria-current] { background: var(--accent); color: #fff; border-color: var(--accent); }

/* ═══ Spinner ════════════════════════════════════════════════════════════════ */
.spin { border: 2px solid var(--border); border-top-color: var(--accent); border-radius: 50%; width: 22px; height: 22px; animation: sp .6s linear infinite; }
@keyframes sp { to { transform: rotate(360deg); } }
@keyframes fadeInUp { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);} }

/* ═══ Ban Badge / Button ═════════════════════════════════════════════════════ */
.ua-ban   { background: #FEE2E2; color: #991B1B; }
.ua-unban { background: #D1FAE5; color: #065F46; }
.banned-overlay {
  position: absolute; inset: 0; background: rgba(0,0,0,.55);
  display: flex; align-items: center; justify-content: center;
  z-index: 2; backdrop-filter: blur(2px); border-radius: 18px;
}
.banned-badge {
  background: #EF4444; color: #fff; font-size: 11px; font-weight: 800;
  padding: 5px 14px; border-radius: 20px; letter-spacing: .5px;
  display: flex; align-items: center; gap: 6px;
  box-shadow: 0 2px 12px rgba(239,68,68,.4);
}
.sp-act-ban   { background: #FEE2E2; color: #991B1B; }
.sp-act-unban { background: #D1FAE5; color: #065F46; }

/* Ban Modal */
.ban-modal-bg { display:none; position:fixed; inset:0; background:rgba(15,23,42,.6); backdrop-filter:blur(6px); z-index:10000; align-items:center; justify-content:center; }
.ban-modal-bg.open { display:flex; }
.ban-modal { background:#fff; border-radius:20px; padding:28px; width:400px; box-shadow:0 20px 60px rgba(0,0,0,.25); }
.ban-modal h3 { font-size:16px; font-weight:800; color:var(--text); margin:0 0 6px; }
.ban-modal p { font-size:12px; color:var(--muted); margin:0 0 18px; }
.ban-modal textarea { width:100%; border:1.5px solid var(--border); border-radius:10px; padding:10px 12px; font-size:13px; color:var(--text); outline:none; resize:none; height:80px; font-family:inherit; }
.ban-modal textarea:focus { border-color:var(--red); }
.ban-modal-actions { display:flex; gap:8px; margin-top:16px; }
.ban-modal-actions button { flex:1; padding:10px; border:none; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer; }
.btn-ban-confirm { background:#EF4444; color:#fff; }
.btn-ban-confirm:hover { background:#DC2626; }
.btn-ban-cancel  { background:var(--faint); color:var(--muted); border:1.5px solid var(--border); }
</style>

<div class="cu-page">

{{-- ══ HERO ════════════════════════════════════════════════════════════════════ --}}
<div class="cu-hero">
    <div style="position:relative;z-index:1;">
        <h1><i class="fas fa-users" style="font-size:22px;margin-right:10px;opacity:.8;"></i>Community Users</h1>
        <p>Full analytics dashboard &amp; real-time DM monitoring for {{ number_format($stats['total']) }} members</p>
    </div>
</div>

{{-- ══ STATS ════════════════════════════════════════════════════════════════════ --}}
<div class="stats-float">
    <div class="sf-card">
        <div class="sf-icon" style="background:linear-gradient(135deg,#EDE9FE,#DDD6FE);">
            <i class="fas fa-users" style="color:#7C3AED;"></i>
        </div>
        <div>
            <div class="label">Total Members</div>
            <div class="val">{{ number_format($stats['total']) }}</div>
        </div>
    </div>
    <div class="sf-card">
        <div class="sf-icon" style="background:linear-gradient(135deg,#DBEAFE,#BFDBFE);">
            <i class="fas fa-shield-check" style="color:#2563EB;"></i>
        </div>
        <div>
            <div class="label">Verified</div>
            <div class="val">{{ number_format($stats['verified']) }}</div>
            <div class="sub">{{ $stats['total'] > 0 ? round($stats['verified']/$stats['total']*100,1) : 0 }}% verified</div>
        </div>
    </div>
    <div class="sf-card">
        <div class="sf-icon" style="background:linear-gradient(135deg,#D1FAE5,#A7F3D0);">
            <i class="fas fa-user-plus" style="color:#059669;"></i>
        </div>
        <div>
            <div class="label">New This Week</div>
            <div class="val">{{ number_format($stats['new_week']) }}</div>
            <div class="sub">Last 7 days</div>
        </div>
    </div>
    <div class="sf-card">
        <div class="sf-icon" style="background:linear-gradient(135deg,#FEF3C7,#FDE68A);">
            <i class="fas fa-rocket" style="color:#D97706;"></i>
        </div>
        <div>
            <div class="label">Onboarded</div>
            <div class="val">{{ number_format($stats['onboarded']) }}</div>
            <div class="sub">{{ $stats['total'] > 0 ? round($stats['onboarded']/$stats['total']*100,1) : 0 }}% complete</div>
        </div>
    </div>
    <div class="sf-card">
        <div class="sf-icon" style="background:linear-gradient(135deg,#FCE7F3,#FBCFE8);">
            <i class="fas fa-venus-mars" style="color:#BE185D;"></i>
        </div>
        <div>
            <div class="label">Gender Split</div>
            <div class="val" style="font-size:15px;font-weight:800;">
                <span style="color:#3B82F6;">M {{ $stats['male'] }}</span> / <span style="color:#EC4899;">F {{ $stats['female'] }}</span>
            </div>
            <div class="sub">Other: {{ $stats['other_gender'] }}</div>
        </div>
    </div>
</div>

{{-- ══ TABS ══════════════════════════════════════════════════════════════════════ --}}
<div class="cu-tabs">
    <button class="cu-tab active" id="tab-users" onclick="switchTab('users')">
        <i class="fas fa-th-large"></i> Users Grid
    </button>
    <button class="cu-tab" id="tab-analytics" onclick="switchTab('analytics')">
        <i class="fas fa-chart-pie"></i> Analytics
    </button>
    <button class="cu-tab" id="tab-chat" onclick="switchTab('chat')">
        <i class="fas fa-comment-dots"></i> Chat Monitor
        <span class="tab-badge" style="background:var(--green);">LIVE</span>
    </button>
</div>

<div class="cu-body">

{{-- ══════════════════ TAB: USERS ══════════════════════════════════════════════ --}}
<div id="pane-users">
    <form method="GET" action="{{ route('admin.community.users') }}" class="filter-strip">
        <i class="fas fa-search" style="color:var(--muted);font-size:13px;flex-shrink:0;"></i>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, email, username...">
        <div class="fs-divider"></div>
        <select name="verified">
            <option value="">All Verified</option>
            <option value="1" @selected(request('verified')==='1')>✓ Verified</option>
            <option value="0" @selected(request('verified')==='0')>Not Verified</option>
        </select>
        <select name="gender">
            <option value="">All Genders</option>
            <option value="male"   @selected(request('gender')==='male')>Male</option>
            <option value="female" @selected(request('gender')==='female')>Female</option>
            <option value="other"  @selected(request('gender')==='other')>Other</option>
        </select>
        <select name="country">
            <option value="">All Countries</option>
            @foreach($countries as $c => $cnt)
            <option value="{{ $c }}" @selected(request('country')===$c)>{{ $c }} ({{ $cnt }})</option>
            @endforeach
        </select>
        <select name="onboarded">
            <option value="">All Users</option>
            <option value="1" @selected(request('onboarded')==='1')>Onboarded</option>
            <option value="0" @selected(request('onboarded')==='0')>Pending</option>
        </select>
        <div class="fs-divider"></div>
        <button type="submit" class="btn-apply"><i class="fas fa-filter"></i> Filter</button>
        <a href="{{ route('admin.community.users') }}" class="btn-clear">Clear</a>
    </form>

    <div class="results-meta">
        <span>Showing <strong>{{ $users->firstItem() }}–{{ $users->lastItem() }}</strong> of <strong>{{ $users->total() }}</strong> members</span>
        <span style="color:var(--muted);">{{ $users->perPage() }} per page</span>
    </div>

    <div class="ug-grid">
        @forelse($users as $profile)
        @php
            $u = $profile->user;
            $init = strtoupper(substr($u->name ?? 'U', 0, 1));
            $age  = $profile->date_of_birth ? (int)\Carbon\Carbon::parse($profile->date_of_birth)->diffInYears(now()) : null;
            $palettes = [
                ['#6C63FF','#8B5CF6'],['#EC4899','#F472B6'],['#0EA5E9','#38BDF8'],
                ['#10B981','#34D399'],['#F59E0B','#FCD34D'],['#EF4444','#F87171'],
            ];
            $pal = $palettes[abs(crc32(($u->name ?? '').'x')) % 6];
        @endphp
        <div class="ug-card" id="ucard-{{ $profile->id }}" onclick="openPanel({{ $profile->id }}, {{ $profile->user_id }})">
            <div class="ug-cover" style="background:linear-gradient(135deg,{{ $pal[0] }},{{ $pal[1] }});">
                @if($profile->cover_photo)
                <img class="ug-cover-img" src="{{ asset('storage/'.$profile->cover_photo) }}" alt="">
                @endif
                @if($u && $u->status === 'banned')
                <div class="banned-overlay"><div class="banned-badge"><i class="fas fa-ban"></i> BANNED</div></div>
                @endif
                @if($profile->is_verified)
                <div class="verified-crown"><i class="fas fa-check-circle"></i> Verified</div>
                @endif
                <div class="ug-av-ring">
                    @if($u && $u->avatar)
                    <img src="{{ $u->avatar }}" alt="">
                    @else
                    <div class="ug-av-init" style="background:{{ $pal[0] }};">{{ $init }}</div>
                    @endif
                </div>
            </div>

            <div class="ug-body">
                <div class="ug-name">
                    {{ $u->name ?? 'Unknown' }}
                    @if($profile->is_verified)
                    <div class="verify-dot"><svg viewBox="0 0 8 8" fill="none"><path d="M1.5 4l2 2 3-3" stroke="#fff" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                    @endif
                </div>
                <div class="ug-handle">{{ $profile->username ? '@'.$profile->username.' · ' : '' }}{{ $u->email ?? '' }}</div>

                <div class="ug-chips">
                    @if($profile->gender)  <span class="chip chip-purple"><i class="fas fa-venus-mars" style="font-size:8px;"></i> {{ ucfirst($profile->gender) }}{{ $age ? ', '.$age.'y' : '' }}</span> @endif
                    @if($profile->country) <span class="chip chip-green"><i class="fas fa-map-pin" style="font-size:8px;"></i> {{ $profile->country }}{{ $profile->city ? ', '.$profile->city : '' }}</span> @endif
                    @if($profile->is_business) <span class="chip chip-amber"><i class="fas fa-briefcase" style="font-size:8px;"></i> Business</span> @endif
                    @if(!$profile->onboarding_completed) <span class="chip chip-gray">Not Onboarded</span> @endif
                </div>

                <div class="ug-stats">
                    <div class="ug-stat">
                        <div class="n">{{ number_format($profile->posts_count) }}</div>
                        <div class="l">Posts</div>
                    </div>
                    <div class="ug-stat">
                        <div class="n">{{ number_format($profile->followers_count ?? 0) }}</div>
                        <div class="l">Followers</div>
                    </div>
                    <div class="ug-stat">
                        <div class="n">{{ number_format($profile->following_count ?? 0) }}</div>
                        <div class="l">Following</div>
                    </div>
                </div>

                <div class="ug-actions">
                    <button class="ua-btn ua-view" onclick="event.stopPropagation();openPanel({{ $profile->id }},{{ $profile->user_id }})">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button class="ua-btn ua-verify {{ $profile->is_verified ? 'on' : '' }}" id="vb-{{ $profile->id }}" onclick="event.stopPropagation();doVerify({{ $profile->id }},this)">
                        <i class="fas fa-{{ $profile->is_verified ? 'times' : 'check' }}"></i> {{ $profile->is_verified ? 'Unverify' : 'Verify' }}
                    </button>
                    @if($u && $u->status === 'banned')
                    <button class="ua-btn ua-unban" id="bb-{{ $profile->id }}" onclick="event.stopPropagation();doUnban({{ $profile->user_id }},{{ $profile->id }},this)">
                        <i class="fas fa-unlock"></i> Unban
                    </button>
                    @else
                    <button class="ua-btn ua-ban" id="bb-{{ $profile->id }}" onclick="event.stopPropagation();openBanModal({{ $profile->user_id }},{{ $profile->id }})">
                        <i class="fas fa-ban"></i> Ban
                    </button>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div style="grid-column:1/-1;text-align:center;padding:80px 0;color:var(--muted);">
            <i class="fas fa-search" style="font-size:36px;color:#CBD5E1;display:block;margin-bottom:14px;"></i>
            <p style="font-size:14px;font-weight:600;">No users found</p>
            <p style="font-size:12px;margin-top:4px;">Try adjusting your filters</p>
        </div>
        @endforelse
    </div>

    <div class="pag-wrap">{{ $users->withQueryString()->links() }}</div>
</div>

{{-- ══════════════════ TAB: ANALYTICS ══════════════════════════════════════════ --}}
<div id="pane-analytics" style="display:none;">
    <div class="analytics-grid">

        {{-- Gender Donut --}}
        <div class="an-card">
            <h3><span class="dot" style="background:var(--accent);"></span> Gender Distribution</h3>
            <div class="donut-wrap">
                <canvas id="gc" width="140" height="140" style="flex-shrink:0;"></canvas>
                <div class="donut-legend">
                    <div class="legend-row">
                        <div class="legend-dot" style="background:#3B82F6;"></div>
                        <span class="legend-label">Male</span>
                        <span class="legend-val">{{ $stats['male'] }}</span>
                        <span class="legend-pct">{{ $stats['total'] > 0 ? round($stats['male']/$stats['total']*100) : 0 }}%</span>
                    </div>
                    <div class="legend-row">
                        <div class="legend-dot" style="background:#EC4899;"></div>
                        <span class="legend-label">Female</span>
                        <span class="legend-val">{{ $stats['female'] }}</span>
                        <span class="legend-pct">{{ $stats['total'] > 0 ? round($stats['female']/$stats['total']*100) : 0 }}%</span>
                    </div>
                    <div class="legend-row">
                        <div class="legend-dot" style="background:#A78BFA;"></div>
                        <span class="legend-label">Other</span>
                        <span class="legend-val">{{ $stats['other_gender'] }}</span>
                        <span class="legend-pct">{{ $stats['total'] > 0 ? round($stats['other_gender']/$stats['total']*100) : 0 }}%</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Verification --}}
        <div class="an-card">
            <h3><span class="dot" style="background:var(--green);"></span> Verification Status</h3>
            <div class="donut-wrap">
                <canvas id="vc" width="140" height="140" style="flex-shrink:0;"></canvas>
                <div class="donut-legend">
                    <div class="legend-row">
                        <div class="legend-dot" style="background:#10B981;"></div>
                        <span class="legend-label">Verified</span>
                        <span class="legend-val">{{ $stats['verified'] }}</span>
                        <span class="legend-pct">{{ $stats['total'] > 0 ? round($stats['verified']/$stats['total']*100) : 0 }}%</span>
                    </div>
                    <div class="legend-row">
                        <div class="legend-dot" style="background:#E2E8F0;"></div>
                        <span class="legend-label">Unverified</span>
                        <span class="legend-val">{{ $stats['total'] - $stats['verified'] }}</span>
                        <span class="legend-pct">{{ $stats['total'] > 0 ? round(($stats['total']-$stats['verified'])/$stats['total']*100) : 0 }}%</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Countries --}}
        <div class="an-card">
            <h3><span class="dot" style="background:var(--amber);"></span> Top Countries by Members</h3>
            @php $maxC = $countries->max() ?: 1; @endphp
            @foreach($countries as $country => $cnt)
            <div class="cbar-row">
                <span class="cbar-label">{{ $country }}</span>
                <div class="cbar-track"><div class="cbar-fill" style="width:{{ round($cnt/$maxC*100) }}%;"></div></div>
                <span class="cbar-cnt">{{ $cnt }}</span>
            </div>
            @endforeach
            @if($countries->isEmpty())
            <p style="font-size:12px;color:var(--muted);text-align:center;padding:20px;">No location data</p>
            @endif
        </div>

        {{-- KPIs --}}
        <div class="an-card">
            <h3><span class="dot" style="background:var(--blue);"></span> Key Metrics</h3>
            <div class="kpi-grid">
                <div class="kpi-box">
                    <div class="kv">{{ $stats['total'] > 0 ? round($stats['onboarded']/$stats['total']*100) : 0 }}%</div>
                    <div class="kl">Onboarding Rate</div>
                </div>
                <div class="kpi-box">
                    <div class="kv">{{ $stats['total'] > 0 ? round($stats['verified']/$stats['total']*100,1) : 0 }}%</div>
                    <div class="kl">Verification Rate</div>
                </div>
                <div class="kpi-box">
                    <div class="kv">{{ number_format($stats['new_week']) }}</div>
                    <div class="kl">Joined This Week</div>
                </div>
                <div class="kpi-box">
                    <div class="kv">{{ $stats['total'] > 0 ? round($stats['male']/$stats['total']*100) : 0 }}%</div>
                    <div class="kl">Male Ratio</div>
                </div>
                <div class="kpi-box">
                    <div class="kv" style="font-size:18px;">{{ number_format($stats['onboarded']) }}</div>
                    <div class="kl">Onboarded Users</div>
                </div>
                <div class="kpi-box">
                    <div class="kv" style="font-size:18px;">{{ number_format($stats['total'] - $stats['onboarded']) }}</div>
                    <div class="kl">Pending Onboard</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════ TAB: CHAT MONITOR ═══════════════════════════════════════ --}}
<div id="pane-chat" style="display:none;">
    <div class="chat-shell">
        {{-- Left: Conversation List --}}
        <div class="chat-sidebar">
            <div class="chat-sidebar-head">
                <h3>All Conversations</h3>
                <div class="chat-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="chat-search-inp" placeholder="Search users..." oninput="loadMonitor()">
                </div>
            </div>
            <div class="chat-list-el" id="chat-list-el">
                <div style="display:flex;align-items:center;justify-content:center;padding:60px 0;"><div class="spin"></div></div>
            </div>
        </div>

        {{-- Right: Thread View --}}
        <div class="chat-main">
            <div class="thread-topbar" id="thread-topbar">
                <div style="width:36px;height:36px;border-radius:50%;background:#EDE9FE;display:flex;align-items:center;justify-content:center;color:var(--accent);flex-shrink:0;">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <div class="tt-info">
                    <div class="tt-name">Select a conversation</div>
                    <div class="tt-sub">Click any chat on the left to view messages</div>
                </div>
            </div>

            <div class="thread-area" id="thread-area">
                <div class="thread-placeholder">
                    <i class="fas fa-comments"></i>
                    <h4>No conversation selected</h4>
                    <p>Select a chat from the sidebar to monitor messages</p>
                </div>
            </div>

            <div class="thread-footer">
                <div class="live-pulse">
                    <div class="live-dot"></div>
                    <span>Real-time monitoring active</span>
                </div>
                <span style="margin-left:auto;font-size:10px;" id="last-refresh"></span>
            </div>
        </div>
    </div>
</div>

</div>{{-- /cu-body --}}
</div>{{-- /cu-page --}}

{{-- ══ SLIDE PANEL: User Detail ════════════════════════════════════════════════ --}}
<div class="sp-overlay" id="spOverlay" onclick="closePanel()"></div>
<div class="sp" id="spPanel">
    <div class="sp-head">
        <button class="sp-close" onclick="closePanel()"><i class="fas fa-times"></i></button>
        <h3 id="spTitle">User Profile</h3>
    </div>
    <div class="sp-hero" id="spHero">
        <div class="sp-av" id="spAv">U</div>
        <div class="sp-av-info">
            <div class="sp-name" id="spName">—</div>
            <div class="sp-handle" id="spHandle">—</div>
        </div>
    </div>
    <div class="sp-body" id="spBody">
        <div style="display:flex;align-items:center;justify-content:center;padding:80px;"><div class="spin"></div></div>
    </div>
    <div class="sp-actions" id="spActions" style="display:none;">
        <button class="sp-act-btn sp-act-chat" onclick="viewChatsFromPanel()"><i class="fas fa-comments"></i> Chats</button>
        <button class="sp-act-btn sp-act-verify" id="spVerifyBtn" onclick="doVerifyPanel()"><i class="fas fa-check"></i> Verify</button>
        <button class="sp-act-btn sp-act-ban" id="spBanBtn" onclick="spBanAction()"><i class="fas fa-ban"></i> Ban</button>
    </div>
</div>

{{-- Ban Modal --}}
<div class="ban-modal-bg" id="banModal" onclick="if(event.target===this)closeBanModal()">
    <div class="ban-modal">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
            <div style="width:44px;height:44px;border-radius:50%;background:#FEE2E2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-ban" style="color:#EF4444;font-size:18px;"></i>
            </div>
            <div>
                <h3>Ban User</h3>
                <p style="margin:0;">This will immediately log out the user from all devices.</p>
            </div>
        </div>
        <label style="font-size:12px;font-weight:700;color:var(--text);display:block;margin-bottom:6px;">Ban Reason</label>
        <textarea id="banReasonTxt" placeholder="e.g. Spamming, harassment, violating community guidelines..."></textarea>
        <div class="ban-modal-actions">
            <button class="btn-ban-cancel" onclick="closeBanModal()">Cancel</button>
            <button class="btn-ban-confirm" onclick="confirmBan()"><i class="fas fa-ban"></i> Confirm Ban</button>
        </div>
    </div>
</div>

<script>
const B = '/admin/community';
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
let activeChatId = null, panelUserId = null, panelProfileId = null;

// ─── Tabs ─────────────────────────────────────────────────────────────────────
function switchTab(t) {
    ['users','analytics','chat'].forEach(x => {
        document.getElementById('pane-'+x).style.display = 'none';
        document.getElementById('tab-'+x).classList.remove('active');
    });
    document.getElementById('pane-'+t).style.display = 'block';
    document.getElementById('tab-'+t).classList.add('active');
    if (t === 'analytics') drawAll();
    if (t === 'chat') loadMonitor();
}

// ─── Donut Canvas ─────────────────────────────────────────────────────────────
let drew = false;
function drawAll() {
    if (drew) return; drew = true;
    donut('gc',  [{{ $stats['male'] }}, {{ $stats['female'] }}, {{ $stats['other_gender'] }}], ['#3B82F6','#EC4899','#A78BFA']);
    donut('vc',  [{{ $stats['verified'] }}, {{ $stats['total'] - $stats['verified'] }}], ['#10B981','#E2E8F0']);
}
function donut(id, data, cols) {
    const c = document.getElementById(id);
    if (!c) return;
    const ctx = c.getContext('2d');
    const total = data.reduce((a,b)=>a+b,0) || 1;
    const cx = c.width/2, cy = c.height/2, r = c.width*.44, ri = c.width*.27;
    let a = -Math.PI/2;
    ctx.clearRect(0, 0, c.width, c.height);
    // shadow
    ctx.save();
    data.forEach((v, i) => {
        const sw = (v / total) * Math.PI * 2;
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, r, a, a + sw);
        ctx.closePath();
        ctx.fillStyle = cols[i];
        ctx.fill();
        a += sw;
    });
    ctx.restore();
    // inner hole
    ctx.beginPath(); ctx.arc(cx, cy, ri, 0, Math.PI*2);
    ctx.fillStyle = '#fff'; ctx.fill();
    // center text
    ctx.fillStyle = '#0F172A';
    ctx.font = `800 ${Math.round(c.width*.13)}px "Segoe UI",sans-serif`;
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(total.toLocaleString(), cx, cy);
}

// ─── User Slide Panel ─────────────────────────────────────────────────────────
function openPanel(profileId, userId) {
    panelProfileId = profileId;
    panelUserId = userId;
    document.getElementById('spOverlay').classList.add('open');
    document.getElementById('spPanel').classList.add('open');
    document.getElementById('spTitle').textContent = 'Loading profile...';
    document.getElementById('spName').textContent = '—';
    document.getElementById('spHandle').textContent = '—';
    document.getElementById('spAv').innerHTML = '<div class="spin" style="border-top-color:#fff;border-color:rgba(255,255,255,.2);"></div>';
    document.getElementById('spBody').innerHTML = '<div style="display:flex;align-items:center;justify-content:center;padding:80px;"><div class="spin"></div></div>';
    document.getElementById('spActions').style.display = 'none';
    fetch(`${B}/users/${profileId}/detail`).then(r=>r.json()).then(renderPanel);
}
function renderPanel(d) {
    const p = d.profile, u = p.user || {};
    const age = p.date_of_birth ? Math.floor((Date.now()-new Date(p.date_of_birth))/(365.25*86400000)) : null;
    document.getElementById('spTitle').textContent = 'User Profile';
    document.getElementById('spName').innerHTML = `${u.name||'Unknown'} ${p.is_verified?'<i class="fas fa-check-circle" style="color:#6EE7B7;font-size:14px;"></i>':''}`;
    document.getElementById('spHandle').textContent = `@${p.username||''} · ${u.email||''}`;
    const avEl = document.getElementById('spAv');
    if (u.avatar) { avEl.innerHTML = `<img src="${u.avatar}" alt="" style="width:100%;height:100%;object-fit:cover;">`; }
    else { avEl.textContent = (u.name||'U')[0].toUpperCase(); }

    const interests = (() => {
        if (!p.interests) return '';
        const arr = Array.isArray(p.interests) ? p.interests : String(p.interests).split(',');
        return arr.map(i=>`<span class="interest-chip">${i.trim()}</span>`).join('');
    })();

    const posts = (d.recent_posts||[]).map(post => `
        <div class="ppost">
            <span class="ptype">${post.type}</span>
            <div style="flex:1;min-width:0;">
                <div class="ptxt">${post.content||'<em style="color:var(--muted);">Media only</em>'}</div>
                <div class="pmeta">❤️ ${post.likes_count} · 👁 ${post.views_count||0} · 💬 ${post.comments_count}</div>
            </div>
        </div>`).join('');

    document.getElementById('spBody').innerHTML = `
        <div class="sp-section">
            <div class="sp-section-title">Profile Information</div>
            <div class="ig2">
                <div class="info-box"><div class="ib-k">Gender</div><div class="ib-v">${p.gender ? p.gender.charAt(0).toUpperCase()+p.gender.slice(1) : '—'}</div></div>
                <div class="info-box"><div class="ib-k">Age</div><div class="ib-v">${age ? age+' years old' : '—'}</div></div>
                <div class="info-box"><div class="ib-k">Country</div><div class="ib-v">${p.country||'—'}</div></div>
                <div class="info-box"><div class="ib-k">City</div><div class="ib-v">${p.city||'—'}</div></div>
                <div class="info-box"><div class="ib-k">Privacy</div><div class="ib-v">${p.privacy||'—'}</div></div>
                <div class="info-box"><div class="ib-k">Joined</div><div class="ib-v">${u.created_at?new Date(u.created_at).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}):'—'}</div></div>
            </div>
        </div>
        ${p.bio ? `<div class="sp-section"><div class="sp-section-title">Bio</div><div style="background:var(--faint);border:1px solid var(--border);border-radius:10px;padding:12px;font-size:12px;line-height:1.6;color:var(--text);">${p.bio}</div></div>` : ''}
        <div class="sp-section">
            <div class="sp-section-title">Social Stats</div>
            <div class="ig3">
                <div class="stat-big"><div class="sbv">${(p.posts_count||0).toLocaleString()}</div><div class="sbl">Posts</div></div>
                <div class="stat-big"><div class="sbv">${(p.followers_count||0).toLocaleString()}</div><div class="sbl">Followers</div></div>
                <div class="stat-big"><div class="sbv">${(p.following_count||0).toLocaleString()}</div><div class="sbl">Following</div></div>
            </div>
        </div>
        <div class="sp-section">
            <div class="sp-section-title">Account Status</div>
            <div class="ig2">
                <div class="info-box"><div class="ib-k">Verified</div><div class="ib-v" style="color:${p.is_verified?'var(--green)':'var(--muted);'};">${p.is_verified?'✓ Yes':'No'}</div></div>
                <div class="info-box"><div class="ib-k">Business</div><div class="ib-v">${p.is_business?'Yes':'No'}</div></div>
                <div class="info-box"><div class="ib-k">Onboarded</div><div class="ib-v" style="color:${p.onboarding_completed?'var(--green)':'var(--amber);'};">${p.onboarding_completed?'✓ Complete':'Pending'}</div></div>
                <div class="info-box"><div class="ib-k">Privacy</div><div class="ib-v">${(p.privacy||'public').charAt(0).toUpperCase()+(p.privacy||'public').slice(1)}</div></div>
            </div>
        </div>
        ${interests ? `<div class="sp-section"><div class="sp-section-title">Interests</div><div style="line-height:2;">${interests}</div></div>` : ''}
        ${posts ? `<div class="sp-section"><div class="sp-section-title">Recent Posts</div>${posts}</div>` : ''}
    `;

    const vBtn = document.getElementById('spVerifyBtn');
    vBtn.className = `sp-act-btn sp-act-verify${p.is_verified?' on':''}`;
    vBtn.innerHTML = `<i class="fas fa-${p.is_verified?'times':'check'}"></i> ${p.is_verified?'Unverify':'Verify'}`;
    vBtn.dataset.verified = p.is_verified ? '1' : '0';

    const isBanned = (p.user?.status || 'active') === 'banned';
    const spBan = document.getElementById('spBanBtn');
    spBan.className = `sp-act-btn sp-act-${isBanned?'unban':'ban'}`;
    spBan.innerHTML = `<i class="fas fa-${isBanned?'unlock':'ban'}"></i> ${isBanned?'Unban':'Ban'}`;

    document.getElementById('spActions').style.display = 'flex';
}
function closePanel() {
    document.getElementById('spOverlay').classList.remove('open');
    document.getElementById('spPanel').classList.remove('open');
}
function doVerifyPanel() {
    if (!panelProfileId) return;
    doVerify(panelProfileId, document.getElementById('spVerifyBtn'));
}
function viewChatsFromPanel() {
    if (panelUserId) { closePanel(); viewChats(panelUserId); }
}

// ─── Verify ────────────────────────────────────────────────────────────────────
function doVerify(profileId, btn) {
    fetch(`${B}/users/${profileId}/verify`, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF} })
        .then(r => {
            const isOn = btn.classList.contains('on') || btn.dataset.verified === '1';
            btn.classList.toggle('on', !isOn);
            btn.innerHTML = !isOn
                ? '<i class="fas fa-times"></i> Unverify'
                : '<i class="fas fa-check"></i> Verify';
            btn.dataset.verified = !isOn ? '1' : '0';
            // Also update card button
            const cardBtn = document.getElementById('vb-'+profileId);
            if (cardBtn) {
                cardBtn.classList.toggle('on', !isOn);
                cardBtn.innerHTML = !isOn
                    ? '<i class="fas fa-times"></i> Unverify'
                    : '<i class="fas fa-check"></i> Verify';
            }
        });
}

// ─── Chat Monitor ─────────────────────────────────────────────────────────────
function loadMonitor() {
    const q = document.getElementById('chat-search-inp')?.value || '';
    fetch(`${B}/chat-monitor${q?'?search='+encodeURIComponent(q):''}`)
        .then(r=>r.json())
        .then(d => {
            renderConvList(d.chats||[]);
            document.getElementById('last-refresh').textContent = 'Updated ' + new Date().toLocaleTimeString();
        });
}
function renderConvList(chats) {
    const el = document.getElementById('chat-list-el');
    if (!chats.length) {
        el.innerHTML = '<div class="chat-empty"><i class="fas fa-comment-slash"></i><p>No conversations found</p></div>';
        return;
    }
    el.innerHTML = chats.map(chat => {
        const mems = chat.members||[];
        const avHtml = mems.slice(0,2).map(m =>
            m.avatar ? `<div class="chat-av"><img src="${m.avatar}" alt=""></div>`
                     : `<div class="chat-av">${(m.name||'?')[0].toUpperCase()}</div>`
        ).join('');
        const last = chat.last_message ||(chat.last_message_type==='image'?'📷 Photo':chat.last_message_type==='audio'?'🎵 Audio':null);
        const t = chat.last_message_at ? new Date(chat.last_message_at).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}) : '';
        return `<div class="chat-item${chat.id===activeChatId?' selected':''}" onclick="openThread(${chat.id},'${(chat.name||'Chat').replace(/'/g,"\\'").replace(/"/g,"&quot;")}',this)">
            <div class="chat-avs" style="width:58px;flex-shrink:0;">${avHtml}</div>
            <div class="chat-item-body">
                <div class="chat-item-name">${chat.name||'Unknown'}</div>
                <div class="chat-item-last">${last||'<em>No messages</em>'}</div>
            </div>
            <div class="chat-item-right">
                <div class="chat-item-cnt">${chat.messages_count||0}</div>
                <div class="chat-item-time">${t}</div>
            </div>
        </div>`;
    }).join('');
}

function viewChats(userId) {
    switchTab('chat');
    fetch(`${B}/users/${userId}/chats`).then(r=>r.json()).then(d=>renderConvList(d.chats||[]));
}

function openThread(chatId, name, rowEl) {
    activeChatId = chatId;
    document.querySelectorAll('.chat-item').forEach(r=>r.classList.remove('selected'));
    rowEl.classList.add('selected');

    // Update topbar
    document.getElementById('thread-topbar').innerHTML = `
        <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#8B5CF6);display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0;font-weight:700;font-size:14px;">
            ${name.charAt(0).toUpperCase()}
        </div>
        <div class="tt-info">
            <div class="tt-name">${name}</div>
            <div class="tt-sub">Chat #${chatId} · Viewing all messages</div>
        </div>
        <span class="tt-badge">MONITORING</span>`;

    document.getElementById('thread-area').innerHTML = '<div style="display:flex;justify-content:center;align-items:center;flex:1;height:100%;"><div class="spin"></div></div>';

    fetch(`${B}/chats/${chatId}/messages`).then(r=>r.json()).then(d=>renderThread(d.messages||[], d.members||[]));
}

let lastMsgCount = 0;
function renderThread(messages, members, silent = false) {
    const ta = document.getElementById('thread-area');
    if (!messages.length) {
        ta.innerHTML = '<div class="thread-placeholder"><i class="fas fa-comment-slash"></i><h4>No messages</h4><p>This conversation has no messages yet</p></div>';
        lastMsgCount = 0;
        return;
    }
    // silent refresh: only re-render when new messages arrive
    if (silent && messages.length === lastMsgCount) return;
    const wasAtBottom = ta.scrollHeight - ta.scrollTop - ta.clientHeight < 60;
    lastMsgCount = messages.length;
    const firstId = messages[0]?.user_id;
    let lastDate = null;
    ta.innerHTML = messages.map(msg => {
        const right = msg.user_id === firstId;
        const d = msg.created_at ? new Date(msg.created_at) : null;
        const dateStr = d ? d.toLocaleDateString('en-GB',{weekday:'long',day:'numeric',month:'long'}) : null;
        let sep = '';
        if (dateStr && dateStr !== lastDate) { lastDate = dateStr; sep = `<div class="date-sep">${dateStr}</div>`; }

        const av = msg.avatar
            ? `<div class="msg-av2"><img src="${msg.avatar}" alt=""></div>`
            : `<div class="msg-av2">${(msg.name||'?')[0].toUpperCase()}</div>`;
        const t = d ? d.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}) : '';
        const content = msg.is_deleted
            ? `<span style="font-style:italic;opacity:.6;">Message deleted</span>`
            : (msg.type==='image'&&msg.media_url ? `<img src="${msg.media_url}" style="max-width:100%;border-radius:8px;display:block;margin-top:4px;" alt="">`
            : msg.type==='audio'&&msg.media_url ? `<span>🎵 Audio message</span>`
            : escHtml(msg.content||''));

        const delBtn = !msg.is_deleted ? `<button class="msg-del-btn" onclick="delMsg(${msg.id},this)" title="Delete"><i class="fas fa-trash"></i></button>` : '';

        return `${sep}<div class="msg-row${right?' right':''}">
            ${right?delBtn:''}${av}
            <div class="msg-col">
                <div class="msg-name">${msg.name||''}</div>
                <div class="bubble${right?' right':' left'}${msg.is_deleted?' deleted':''}">
                    ${content}
                    <div class="btime">${t}</div>
                </div>
            </div>
            ${!right?delBtn:''}
        </div>`;
    }).join('');
    if (!silent || wasAtBottom) ta.scrollTop = ta.scrollHeight;
}

function delMsg(id, btn) {
    if (!confirm('Permanently delete this message?')) return;
    fetch(`${B}/messages/${id}`, {method:'DELETE',headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}})
        .then(r=>r.json()).then(d => {
            if (d.success) {
                const row = btn.closest('.msg-row');
                const bubble = row.querySelector('.bubble');
                bubble.classList.add('deleted');
                bubble.innerHTML = '<span style="font-style:italic;opacity:.6;">Message deleted</span>';
                btn.remove();
            }
        });
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─── Ban / Unban ──────────────────────────────────────────────────────────────
let banTargetUserId = null, banTargetProfileId = null;

function openBanModal(userId, profileId) {
    banTargetUserId = userId;
    banTargetProfileId = profileId;
    document.getElementById('banReasonTxt').value = '';
    document.getElementById('banModal').classList.add('open');
}
function closeBanModal() {
    document.getElementById('banModal').classList.remove('open');
}
function confirmBan() {
    const reason = document.getElementById('banReasonTxt').value.trim() || 'Violated community guidelines';
    fetch(`${B}/users/${banTargetUserId}/ban`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ reason })
    }).then(r=>r.json()).then(d => {
        if (d.success) {
            closeBanModal();
            applyBanUI(banTargetProfileId, true);
            showToast('🚫 User banned and immediately logged out from all devices.', 'red');
        }
    });
}
function doUnban(userId, profileId, btn) {
    if (!confirm('Unban this user? They will be able to log in again.')) return;
    fetch(`${B}/users/${userId}/unban`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    }).then(r=>r.json()).then(d => {
        if (d.success) {
            applyBanUI(profileId, false);
            showToast('✓ User unbanned successfully.', 'green');
        }
    });
}
function applyBanUI(profileId, isBanned) {
    // Update card overlay
    const card = document.getElementById('ucard-'+profileId);
    if (card) {
        const cover = card.querySelector('.ug-cover');
        const existing = cover.querySelector('.banned-overlay');
        if (isBanned && !existing) {
            const ov = document.createElement('div');
            ov.className = 'banned-overlay';
            ov.innerHTML = '<div class="banned-badge"><i class="fas fa-ban"></i> BANNED</div>';
            cover.prepend(ov);
        } else if (!isBanned && existing) {
            existing.remove();
        }
        // Swap ban button
        const bb = document.getElementById('bb-'+profileId);
        if (bb) {
            if (isBanned) {
                bb.className = 'ua-btn ua-unban';
                bb.innerHTML = '<i class="fas fa-unlock"></i> Unban';
                bb.onclick = function(e) { e.stopPropagation(); doUnban(banTargetUserId||panelUserId, profileId, this); };
            } else {
                bb.className = 'ua-btn ua-ban';
                bb.innerHTML = '<i class="fas fa-ban"></i> Ban';
                bb.onclick = function(e) { e.stopPropagation(); openBanModal(panelUserId, profileId); };
            }
        }
    }
    // Update slide panel ban btn
    const spBan = document.getElementById('spBanBtn');
    if (spBan) {
        if (isBanned) {
            spBan.className = 'sp-act-btn sp-act-unban';
            spBan.innerHTML = '<i class="fas fa-unlock"></i> Unban';
        } else {
            spBan.className = 'sp-act-btn sp-act-ban';
            spBan.innerHTML = '<i class="fas fa-ban"></i> Ban';
        }
    }
}
function spBanAction() {
    const spBan = document.getElementById('spBanBtn');
    if (spBan.classList.contains('sp-act-unban')) {
        doUnban(panelUserId, panelProfileId, spBan);
    } else {
        openBanModal(panelUserId, panelProfileId);
    }
}

// ─── Toast ─────────────────────────────────────────────────────────────────────
function showToast(msg, color='green') {
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:28px;right:28px;z-index:99999;background:${color==='red'?'#EF4444':'#10B981'};color:#fff;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.2);animation:fadeInUp .3s ease;`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

// ─── Auto-refresh ─────────────────────────────────────────────────────────────
setInterval(() => {
    if (document.getElementById('pane-chat').style.display === 'none') return;
    // Refresh conversation list
    loadMonitor();
    // Refresh active thread silently (only re-render if new messages)
    if (activeChatId !== null) {
        fetch(`${B}/chats/${activeChatId}/messages`)
            .then(r=>r.json())
            .then(d => renderThread(d.messages||[], d.members||[], true));
    }
}, 3000);
</script>

@endsection
