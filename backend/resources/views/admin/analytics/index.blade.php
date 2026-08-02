@extends('admin.layouts.app')
@section('title', 'Platform Analytics')

@push('styles')
<style>
/* ══════════════════════════════════════════════════════
   ENTERPRISE ANALYTICS — eSahlan Platform Intelligence
   ══════════════════════════════════════════════════════ */

:root {
    --brand:   #FF8A00;
    --b-soft:  rgba(255,138,0,.12);
    --green:   #10b981;
    --g-soft:  rgba(16,185,129,.12);
    --blue:    #3b82f6;
    --bl-soft: rgba(59,130,246,.12);
    --purple:  #8b5cf6;
    --p-soft:  rgba(139,92,246,.12);
    --pink:    #ec4899;
    --pk-soft: rgba(236,72,153,.12);
    --teal:    #14b8a6;
    --t-soft:  rgba(20,184,166,.12);
    --amber:   #f59e0b;
    --a-soft:  rgba(245,158,11,.12);
    --red:     #ef4444;
    --r-soft:  rgba(239,68,68,.12);
    --cyan:    #06b6d4;
    --c-soft:  rgba(6,182,212,.12);

    --card-bg:  #fff;
    --card-bg2: #f8fafc;
    --border:   #e4e9f0;
    --text:     #111827;
    --muted:    #6b7280;
    --bg:       #f0f4f8;
}

*{box-sizing:border-box;margin:0;padding:0}

/* ── Wrap ── */
.an{padding:18px 22px;width:100%;font-family:'Segoe UI',system-ui,sans-serif}

/* ── Header ── */
.an-hdr{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:18px;gap:12px;flex-wrap:wrap}
.an-hdr-left{}
.an-htitle{font-size:19px;font-weight:900;color:var(--text);letter-spacing:-.4px;display:flex;align-items:center;gap:8px}
.an-hsub{font-size:11.5px;color:var(--muted);margin-top:2px}
.an-hmeta{display:flex;gap:7px;flex-wrap:wrap;margin-top:8px}
.chip{display:inline-flex;align-items:center;gap:5px;background:var(--card-bg2);border:1px solid var(--border);border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;color:var(--muted)}
.chip .dot{width:6px;height:6px;border-radius:50%;background:var(--green);animation:pulse 2s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.8)}}
.chip.live-chip{border-color:rgba(239,68,68,.3);color:var(--red)}
.chip.live-chip .dot{background:var(--red)}
.btn-refresh{display:flex;align-items:center;gap:6px;background:var(--brand);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:12px;font-weight:800;cursor:pointer;transition:opacity .18s;letter-spacing:.02em;white-space:nowrap}
.btn-refresh:hover{opacity:.85}

/* ── Section ── */
.sec{margin-bottom:20px}
.sec-title{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);margin-bottom:10px;display:flex;align-items:center;gap:7px}
.sec-title::after{content:'';flex:1;height:1px;background:var(--border)}
.sec-title .ico{font-size:12px}

/* ── Grids ── */
.g2{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.g3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.g4{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.g5{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}
.g6{display:grid;grid-template-columns:repeat(6,1fr);gap:12px}
.g8{display:grid;grid-template-columns:repeat(8,1fr);gap:10px}
.col2{grid-column:span 2}
.col3{grid-column:span 3}

@media(max-width:1300px){.g8{grid-template-columns:repeat(4,1fr)}.g6{grid-template-columns:repeat(3,1fr)}}
@media(max-width:960px){.g5,.g4{grid-template-columns:repeat(2,1fr)}.g3{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.g2,.g3,.g4,.g5,.g6,.g8{grid-template-columns:1fr}}

/* ── KPI card (tiny, compact) ── */
.kpi{background:var(--card-bg);border:1px solid var(--border);border-radius:10px;padding:12px 14px;display:flex;align-items:center;gap:11px;transition:box-shadow .18s}
.kpi:hover{box-shadow:0 4px 16px rgba(0,0,0,.07)}
.kpi-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.kpi-body{min-width:0}
.kpi-val{font-size:20px;font-weight:900;color:var(--text);font-variant-numeric:tabular-nums;line-height:1;white-space:nowrap}
.kpi-lbl{font-size:10px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.kpi-delta{font-size:10px;font-weight:700;margin-top:3px}
.delta-up{color:var(--green)}
.delta-dn{color:var(--red)}
.delta-nt{color:var(--muted)}

/* ── Stat card (medium) ── */
.sc{background:var(--card-bg);border:1px solid var(--border);border-radius:10px;padding:14px 16px;transition:box-shadow .18s;position:relative;overflow:hidden}
.sc:hover{box-shadow:0 4px 16px rgba(0,0,0,.07)}
.sc-accent{position:absolute;left:0;top:0;bottom:0;width:3px;border-radius:3px 0 0 3px}
.sc-icon{width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:14px;margin-bottom:8px}
.sc-val{font-size:22px;font-weight:900;color:var(--text);font-variant-numeric:tabular-nums;line-height:1}
.sc-lbl{font-size:11px;color:var(--muted);font-weight:600;margin-top:3px}
.sc-sub{font-size:10px;color:var(--muted);margin-top:4px}

/* ── Chart card ── */
.cc{background:var(--card-bg);border:1px solid var(--border);border-radius:10px;padding:16px}
.cc-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px;gap:8px}
.cc-title{font-size:12px;font-weight:800;color:var(--text)}
.cc-sub{font-size:10px;color:var(--muted);margin-top:1px}
.cc-badge{font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;white-space:nowrap}
.canvas-wrap{position:relative}

/* ── Module card ── */
.mc{background:var(--card-bg);border:1px solid var(--border);border-radius:10px;padding:13px 14px;position:relative;overflow:hidden;transition:box-shadow .18s}
.mc:hover{box-shadow:0 4px 16px rgba(0,0,0,.07)}
.mc-stripe{position:absolute;top:0;left:0;right:0;height:2px}
.mc-head{display:flex;align-items:center;gap:8px;margin-bottom:10px}
.mc-icon{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:13px}
.mc-name{font-size:11px;font-weight:800;color:var(--text);text-transform:uppercase;letter-spacing:.04em}
.mc-stats{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.ms{display:flex;flex-direction:column}
.ms-val{font-size:16px;font-weight:900;color:var(--text);font-variant-numeric:tabular-nums;line-height:1}
.ms-lbl{font-size:9px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-top:1px}

/* ── Module bar ── */
.mbar-list{display:flex;flex-direction:column;gap:9px}
.mbar-row{display:flex;flex-direction:column;gap:3px}
.mbar-top{display:flex;justify-content:space-between;align-items:center}
.mbar-name{font-size:11px;font-weight:700;color:var(--text)}
.mbar-val{font-size:11px;color:var(--muted);font-variant-numeric:tabular-nums}
.mbar-track{height:5px;background:var(--bg);border-radius:99px;overflow:hidden}
.mbar-fill{height:100%;border-radius:99px;background:var(--brand);transition:width .7s cubic-bezier(.4,0,.2,1)}

/* ── Funnel ── */
.fl{display:flex;flex-direction:column;gap:5px}
.fl-row{display:flex;align-items:center;justify-content:space-between;padding:7px 10px;border-radius:7px;background:var(--card-bg2);font-size:12px}
.fl-status{font-weight:700;text-transform:capitalize}
.fl-count{font-weight:900;font-variant-numeric:tabular-nums;font-size:13px}
.s-pending{color:var(--amber)}
.s-confirmed,.s-delivered,.s-approved,.s-completed{color:var(--green)}
.s-cancelled,.s-rejected,.s-failed{color:var(--red)}
.s-processing,.s-preparing,.s-picked_up{color:var(--blue)}

/* ── Vendor table ── */
.vtbl{width:100%;border-collapse:collapse}
.vtbl th{text-align:left;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);padding:0 8px 7px;border-bottom:1.5px solid var(--border)}
.vtbl td{padding:7px 8px;font-size:12px;border-bottom:1px solid var(--border)}
.vtbl tr:last-child td{border-bottom:none}
.vrank{width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:9px;font-weight:900}
.vrank.g{background:#fef3c7;color:#d97706}
.vrank.s{background:#f1f5f9;color:#64748b}
.vrank.b{background:#fef2ee;color:#c2410c}
.vrank.n{background:var(--bg);color:var(--muted)}

/* ── Pills row ── */
.pills{display:flex;gap:8px;flex-wrap:wrap}
.pill{background:var(--card-bg2);border:1px solid var(--border);border-radius:8px;padding:9px 12px;text-align:center;flex:1;min-width:70px}
.pill-val{font-size:16px;font-weight:900;color:var(--text);font-variant-numeric:tabular-nums}
.pill-lbl{font-size:9px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-top:2px}

/* ── Retention ring ── */
.ret-ring{display:flex;align-items:center;gap:18px}
.ring-wrap{position:relative;width:90px;height:90px;flex-shrink:0}
.ring-wrap svg{transform:rotate(-90deg)}
.ring-num{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:17px;font-weight:900;color:var(--text)}
.ret-desc{font-size:11px;color:var(--muted);line-height:1.6}
.ret-desc strong{color:var(--text)}

/* ── Snap table ── */
.snap-row{display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border)}
.snap-row:last-child{border-bottom:none}
.snap-lbl{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:5px}
.snap-val{font-size:13px;font-weight:800;color:var(--text);font-variant-numeric:tabular-nums}

/* ── LIVE badge ── */
.live-tag{position:absolute;top:10px;right:10px;background:var(--red);color:#fff;border-radius:20px;padding:2px 7px;font-size:9px;font-weight:900;letter-spacing:.06em;animation:liveblink 1.6s ease-in-out infinite}
@keyframes liveblink{0%,100%{opacity:1}50%{opacity:.5}}

/* ── Divider ── */
.div{border:none;border-top:1px solid var(--border);margin:12px 0}

/* ── Scroll x ── */
.overx{overflow-x:auto}

/* ── Layout helpers ── */
.flex-gap{display:flex;gap:12px;align-items:flex-start}
.flex-gap > *{flex:1;min-width:0}

/* ── Trend spark bar ── */
.sparkbars{display:flex;align-items:flex-end;gap:2px;height:28px;margin-top:6px}
.sparkbar{flex:1;border-radius:2px 2px 0 0;min-height:3px;background:var(--brand);opacity:.5;transition:opacity .2s}
.sparkbar:last-child{opacity:1}
</style>
@endpush

@section('content')
<div class="an">

{{-- ══ HEADER ══ --}}
<div class="an-hdr">
    <div class="an-hdr-left">
        <div class="an-htitle">📊 Platform Analytics</div>
        <div class="an-hsub">eSahlan · Full-platform business intelligence · {{ now()->format('d M Y') }}</div>
        <div class="an-hmeta">
            @if($liveOnline > 0)
            <span class="chip live-chip"><span class="dot"></span>{{ $liveOnline }} Live now</span>
            @else
            <span class="chip"><span class="dot" style="background:var(--muted)"></span>No rooms live</span>
            @endif
            <span class="chip">⏱ {{ now()->format('H:i') }} UTC+3</span>
            <span class="chip">👤 {{ number_format($dau) }} active today</span>
            <span class="chip">📦 {{ number_format($pendingOrders) }} orders pending</span>
            <span class="chip" id="last-updated">🔄 Just loaded</span>
        </div>
    </div>
    <button class="btn-refresh" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Refresh</button>
</div>

{{-- ══ KPI STRIP ══ --}}
<div class="sec">
    <div class="g8">
        <div class="kpi">
            <div class="kpi-icon" style="background:var(--b-soft);color:var(--brand)">👥</div>
            <div class="kpi-body">
                <div class="kpi-val">{{ number_format($totalUsers) }}</div>
                <div class="kpi-lbl">Total Users</div>
                <div class="kpi-delta delta-up">+{{ number_format($newLast7) }} this week</div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon" style="background:var(--g-soft);color:var(--green)">📅</div>
            <div class="kpi-body">
                <div class="kpi-val" id="kpi-dau">{{ number_format($dau) }}</div>
                <div class="kpi-lbl">DAU · Today</div>
                <div class="kpi-delta delta-nt">{{ number_format($wau) }} WAU · {{ number_format($mau) }} MAU</div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon" style="background:var(--p-soft);color:var(--purple)">💰</div>
            <div class="kpi-body">
                <div class="kpi-val">${{ number_format($revenueTotal,0) }}</div>
                <div class="kpi-lbl">Total Revenue</div>
                <div class="kpi-delta delta-up">+${{ number_format($revenueMonth,0) }} MTD</div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon" style="background:var(--b-soft);color:var(--brand)">📦</div>
            <div class="kpi-body">
                <div class="kpi-val">{{ number_format($totalOrders) }}</div>
                <div class="kpi-lbl">Total Orders</div>
                <div class="kpi-delta delta-nt">{{ $totalOrders>0?round($orderFunnel->get('delivered',0)/$totalOrders*100,1):0 }}% delivered</div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon" style="background:var(--t-soft);color:var(--teal)">📝</div>
            <div class="kpi-body">
                <div class="kpi-val">{{ number_format($totalPosts) }}</div>
                <div class="kpi-lbl">Posts</div>
                <div class="kpi-delta delta-up">+{{ number_format($postsThisWeek) }} this week</div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon" style="background:var(--r-soft);color:var(--red)">📺</div>
            <div class="kpi-body">
                <div class="kpi-val">{{ number_format($liveRoomsTotal) }}</div>
                <div class="kpi-lbl">Live Rooms</div>
                <div class="kpi-delta {{ $liveOnline>0?'delta-up':'delta-nt' }}">{{ $liveOnline }} active</div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon" style="background:var(--a-soft);color:var(--amber)">🏪</div>
            <div class="kpi-body">
                <div class="kpi-val">{{ number_format($totalVendors) }}</div>
                <div class="kpi-lbl">Vendors</div>
                <div class="kpi-delta delta-nt">{{ number_format($totalDrivers) }} drivers</div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon" style="background:var(--g-soft);color:var(--green)">🔄</div>
            <div class="kpi-body">
                <div class="kpi-val">{{ $retentionRate }}%</div>
                <div class="kpi-lbl">Retention</div>
                <div class="kpi-delta delta-nt">{{ number_format($repeatCustomers) }} repeat</div>
            </div>
        </div>
    </div>
</div>

{{-- ══ REVENUE KPIs — labelled by exact period ══ --}}
<div class="sec">
    <div class="sec-title"><span class="ico">💰</span> Revenue Breakdown</div>
    @php
        $avgOrderVal = $orderFunnel->get('delivered',0) > 0
            ? round($revenueTotal / $orderFunnel->get('delivered',0), 2) : 0;
        $monthLabel = now()->format('M Y');
    @endphp
    <div class="g5" style="margin-bottom:12px">
        <div class="sc"><div class="sc-accent" style="background:var(--brand)"></div>
            <div class="sc-icon" style="background:var(--b-soft);color:var(--brand)">📅</div>
            <div class="sc-val">${{ number_format($revenueToday,0) }}</div>
            <div class="sc-lbl">Today · {{ now()->format('d M') }}</div>
        </div>
        <div class="sc"><div class="sc-accent" style="background:var(--blue)"></div>
            <div class="sc-icon" style="background:var(--bl-soft);color:var(--blue)">📊</div>
            <div class="sc-val">${{ number_format($revenueWeek,0) }}</div>
            <div class="sc-lbl">Rolling 7 Days</div>
            <div class="sc-sub delta-nt">{{ now()->subDays(7)->format('d M') }} → {{ now()->format('d M') }}</div>
        </div>
        <div class="sc"><div class="sc-accent" style="background:var(--green)"></div>
            <div class="sc-icon" style="background:var(--g-soft);color:var(--green)">🗓️</div>
            <div class="sc-val">${{ number_format($revenueMonth,0) }}</div>
            <div class="sc-lbl">{{ $monthLabel }} (MTD)</div>
            <div class="sc-sub delta-nt">Commission: ${{ number_format($commissionMonth,0) }}</div>
        </div>
        <div class="sc"><div class="sc-accent" style="background:var(--purple)"></div>
            <div class="sc-icon" style="background:var(--p-soft);color:var(--purple)">🏆</div>
            <div class="sc-val">${{ number_format($revenueTotal,0) }}</div>
            <div class="sc-lbl">All-Time Revenue</div>
            <div class="sc-sub delta-up">Commission: ${{ number_format($commissionTotal,0) }}</div>
        </div>
        <div class="sc"><div class="sc-accent" style="background:var(--teal)"></div>
            <div class="sc-icon" style="background:var(--t-soft);color:var(--teal)">🧮</div>
            <div class="sc-val">${{ number_format($avgOrderVal,2) }}</div>
            <div class="sc-lbl">Avg Order Value</div>
            <div class="sc-sub delta-nt">per delivered order</div>
        </div>
    </div>
</div>

{{-- ══ CHARTS ROW — equal height 3 columns ══ --}}
<div class="sec">
    <div class="sec-title"><span class="ico">📈</span> Revenue & Growth Trends</div>
    <div class="g3" style="margin-bottom:12px">
        <div class="cc" style="display:flex;flex-direction:column">
            <div class="cc-top">
                <div><div class="cc-title">Daily Revenue</div><div class="cc-sub">Last 30 days · delivered orders</div></div>
                <span class="cc-badge" style="background:var(--b-soft);color:var(--brand)">${{ number_format($revenueMonth,0) }} {{ now()->format('M') }}</span>
            </div>
            <div class="canvas-wrap" style="height:140px;flex:1"><canvas id="cDailyRev"></canvas></div>
        </div>
        <div class="cc" style="display:flex;flex-direction:column">
            <div class="cc-top">
                <div><div class="cc-title">New User Signups</div><div class="cc-sub">Last 30 days · registrations</div></div>
                <span class="cc-badge" style="background:var(--g-soft);color:var(--green)">+{{ number_format($newLast30) }}</span>
            </div>
            <div class="canvas-wrap" style="height:140px;flex:1"><canvas id="cUserGrowth"></canvas></div>
        </div>
        <div class="cc" style="display:flex;flex-direction:column">
            <div class="cc-top">
                <div><div class="cc-title">Revenue by Module</div><div class="cc-sub">All-time · Chart.js horizontal</div></div>
            </div>
            <div class="canvas-wrap" style="flex:1;min-height:140px"><canvas id="cModuleRev"></canvas></div>
        </div>
    </div>

    {{-- Monthly full-width ── --}}
    <div class="cc">
        <div class="cc-top">
            <div><div class="cc-title">Monthly Revenue & Orders — Last 12 Months</div><div class="cc-sub">Delivered orders only</div></div>
        </div>
        <div class="canvas-wrap" style="height:170px"><canvas id="cMonthly"></canvas></div>
    </div>
</div>

{{-- ══ ORDERS ══ --}}
<div class="sec">
    <div class="sec-title"><span class="ico">📦</span> Orders & Vendors</div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1.6fr;gap:12px">

        {{-- Order Funnel ─────────────────── --}}
        <div class="cc">
            <div class="cc-top">
                <div><div class="cc-title">Order Status Funnel</div><div class="cc-sub">All-time distribution</div></div>
                <span class="cc-badge" style="background:var(--b-soft);color:var(--brand)">{{ number_format($totalOrders) }} total</span>
            </div>
            <div class="fl">
                @foreach($orderFunnel as $status => $count)
                <div class="fl-row">
                    <span class="fl-status s-{{ $status }}">{{ ucfirst(str_replace('_',' ',$status)) }}</span>
                    <div style="display:flex;align-items:center;gap:8px">
                        <div style="width:60px;height:4px;background:var(--bg);border-radius:99px;overflow:hidden">
                            <div style="width:{{ $totalOrders>0?round($count/$totalOrders*100):0 }}%;height:100%;background:currentColor;border-radius:99px"></div>
                        </div>
                        <span class="fl-count">{{ number_format($count) }}</span>
                    </div>
                </div>
                @endforeach
                @if($orderFunnel->isEmpty())
                <p style="font-size:11px;color:var(--muted);text-align:center;padding:12px">No orders yet</p>
                @endif
            </div>
        </div>

        {{-- Order Stats ──────────────────── --}}
        <div class="cc">
            <div class="cc-top">
                <div><div class="cc-title">Order Performance</div><div class="cc-sub">All-time metrics</div></div>
            </div>
            <div style="display:flex;flex-direction:column;gap:10px">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:var(--g-soft);border-radius:8px">
                    <span style="font-size:11px;font-weight:700;color:var(--green)">✅ Delivered</span>
                    <span style="font-size:14px;font-weight:900;color:var(--green);font-variant-numeric:tabular-nums">{{ number_format($orderFunnel->get('delivered',0)) }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:var(--a-soft);border-radius:8px">
                    <span style="font-size:11px;font-weight:700;color:var(--amber)">⏳ Pending</span>
                    <span style="font-size:14px;font-weight:900;color:var(--amber);font-variant-numeric:tabular-nums">{{ number_format($orderFunnel->get('pending',0)) }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:var(--r-soft);border-radius:8px">
                    <span style="font-size:11px;font-weight:700;color:var(--red)">❌ Cancelled</span>
                    <span style="font-size:14px;font-weight:900;color:var(--red);font-variant-numeric:tabular-nums">{{ number_format($orderFunnel->get('cancelled',0)) }}</span>
                </div>
                <hr class="div" style="margin:4px 0">
                <div style="display:flex;justify-content:space-between">
                    <span style="font-size:11px;color:var(--muted)">Delivery Rate</span>
                    <span style="font-size:13px;font-weight:900;color:var(--text)">{{ $totalOrders>0?round($orderFunnel->get('delivered',0)/$totalOrders*100,1):0 }}%</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="font-size:11px;color:var(--muted)">Avg Order Value</span>
                    <span style="font-size:13px;font-weight:900;color:var(--text)">${{ number_format($avgOrderVal,2) }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span style="font-size:11px;color:var(--muted)">Orders / User</span>
                    <span style="font-size:13px;font-weight:900;color:var(--text)">{{ $totalOrderingUsers>0?round($totalOrders/$totalOrderingUsers,1):0 }}x</span>
                </div>
            </div>
        </div>

        {{-- Top Vendors ──────────────────── --}}
        <div class="cc">
            <div class="cc-top">
                <div><div class="cc-title">Top Vendors by Revenue</div><div class="cc-sub">All-time · delivered orders</div></div>
            </div>
            <div class="overx">
            <table class="vtbl">
                <thead><tr><th>#</th><th>Vendor</th><th>Orders</th><th>Revenue</th><th>Share</th></tr></thead>
                <tbody>
                    @forelse($topVendors as $i => $v)
                    <tr>
                        <td><span class="vrank {{ $i==0?'g':($i==1?'s':($i==2?'b':'n')) }}">{{ $i+1 }}</span></td>
                        <td style="font-weight:700;font-size:11px">{{ $v->name }}</td>
                        <td style="font-size:11px;color:var(--muted)">{{ number_format($v->orders) }}</td>
                        <td style="font-weight:800;font-size:11px">${{ number_format($v->revenue,0) }}</td>
                        <td style="font-size:10px;color:var(--muted)">{{ $revenueTotal>0?round($v->revenue/$revenueTotal*100,1):0 }}%</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:14px;font-size:11px">No vendor data yet</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

{{-- ══ ESOCIAL ══ --}}
<div class="sec">
    <div class="sec-title"><span class="ico">🌐</span> eSocial — Community Platform</div>
    <div style="display:grid;grid-template-columns:repeat(6,1fr) 1.4fr;gap:12px;align-items:start">
        <div class="sc"><div class="sc-accent" style="background:var(--brand)"></div><div class="sc-icon" style="background:var(--b-soft);color:var(--brand)">📝</div><div class="sc-val">{{ number_format($totalPosts) }}</div><div class="sc-lbl">Posts</div><div class="sc-sub delta-up">+{{ $postsThisWeek }} this week</div></div>
        <div class="sc"><div class="sc-accent" style="background:var(--pink)"></div><div class="sc-icon" style="background:var(--pk-soft);color:var(--pink)">❤️</div><div class="sc-val">{{ number_format($totalReactions) }}</div><div class="sc-lbl">Reactions</div></div>
        <div class="sc"><div class="sc-accent" style="background:var(--blue)"></div><div class="sc-icon" style="background:var(--bl-soft);color:var(--blue)">💬</div><div class="sc-val">{{ number_format($totalComments) }}</div><div class="sc-lbl">Comments</div></div>
        <div class="sc"><div class="sc-accent" style="background:var(--purple)"></div><div class="sc-icon" style="background:var(--p-soft);color:var(--purple)">👥</div><div class="sc-val">{{ number_format($totalFollows) }}</div><div class="sc-lbl">Follows</div></div>
        <div class="sc"><div class="sc-accent" style="background:var(--teal)"></div><div class="sc-icon" style="background:var(--t-soft);color:var(--teal)">✉️</div><div class="sc-val">{{ number_format($totalMessages) }}</div><div class="sc-lbl">Messages</div></div>
        <div class="sc"><div class="sc-accent" style="background:var(--amber)"></div><div class="sc-icon" style="background:var(--a-soft);color:var(--amber)">📖</div><div class="sc-val">{{ number_format($totalStories) }}</div><div class="sc-lbl">Stories</div></div>
        <div class="cc">
            <div class="cc-title" style="margin-bottom:8px">Community Activity</div>
            <div class="canvas-wrap" style="height:90px"><canvas id="cComm"></canvas></div>
        </div>
    </div>
</div>

{{-- ══ MODULE CARDS ══ --}}
<div class="sec">
    <div class="sec-title"><span class="ico">🗂️</span> Platform Modules</div>
    <div class="g5">

        {{-- Live ─────────── --}}
        <div class="mc">
            <div class="mc-stripe" style="background:linear-gradient(90deg,var(--red),var(--pink))"></div>
            <div class="mc-head">
                <div class="mc-icon" style="background:var(--r-soft);color:var(--red)">🎙</div>
                <div class="mc-name">eSpace · Live</div>
                @if($liveOnline>0)<span class="live-tag">LIVE</span>@endif
            </div>
            <div class="mc-stats">
                <div class="ms"><div class="ms-val">{{ number_format($liveRoomsTotal) }}</div><div class="ms-lbl">Rooms</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($liveViewersTotal) }}</div><div class="ms-lbl">Viewers</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($giftsSent) }}</div><div class="ms-lbl">Gifts</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($coinsIssued) }}</div><div class="ms-lbl">Coins</div></div>
            </div>
        </div>

        {{-- eLearning ──────── --}}
        <div class="mc">
            <div class="mc-stripe" style="background:linear-gradient(90deg,var(--blue),var(--cyan))"></div>
            <div class="mc-head">
                <div class="mc-icon" style="background:var(--bl-soft);color:var(--blue)">🎓</div>
                <div class="mc-name">eLearning</div>
            </div>
            <div class="mc-stats">
                <div class="ms"><div class="ms-val">{{ number_format($elCourses) }}</div><div class="ms-lbl">Courses</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($elEnrollments) }}</div><div class="ms-lbl">Enrolled</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($elInstructors) }}</div><div class="ms-lbl">Instructors</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($elCertificates) }}</div><div class="ms-lbl">Certs</div></div>
            </div>
        </div>

        {{-- Podcast ─────────── --}}
        <div class="mc">
            <div class="mc-stripe" style="background:linear-gradient(90deg,var(--purple),var(--pink))"></div>
            <div class="mc-head">
                <div class="mc-icon" style="background:var(--p-soft);color:var(--purple)">🎧</div>
                <div class="mc-name">Podcast</div>
            </div>
            <div class="mc-stats">
                <div class="ms"><div class="ms-val">{{ number_format($podcastShows) }}</div><div class="ms-lbl">Shows</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($podcastEpisodes) }}</div><div class="ms-lbl">Episodes</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($podcastPlays) }}</div><div class="ms-lbl">Plays</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($podcastFollows) }}</div><div class="ms-lbl">Followers</div></div>
            </div>
        </div>

        {{-- eMarry ────────── --}}
        <div class="mc">
            <div class="mc-stripe" style="background:linear-gradient(90deg,var(--pink),var(--red))"></div>
            <div class="mc-head">
                <div class="mc-icon" style="background:var(--pk-soft);color:var(--pink)">💑</div>
                <div class="mc-name">eMarry</div>
            </div>
            <div class="mc-stats">
                <div class="ms"><div class="ms-val">{{ number_format($emarryProfiles) }}</div><div class="ms-lbl">Profiles</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($emarryMatches) }}</div><div class="ms-lbl">Matches</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($emarryApproved) }}</div><div class="ms-lbl">Approved</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($emarryLikes) }}</div><div class="ms-lbl">Likes</div></div>
            </div>
        </div>

        {{-- eRent ──────────── --}}
        <div class="mc">
            <div class="mc-stripe" style="background:linear-gradient(90deg,var(--teal),var(--green))"></div>
            <div class="mc-head">
                <div class="mc-icon" style="background:var(--t-soft);color:var(--teal)">🏠</div>
                <div class="mc-name">eRent</div>
            </div>
            <div class="mc-stats">
                <div class="ms"><div class="ms-val">{{ number_format($houseRequests) }}</div><div class="ms-lbl">Requests</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($rentAgents) }}</div><div class="ms-lbl">Agents</div></div>
            </div>
        </div>
    </div>

    <div class="g5" style="margin-top:12px">
        {{-- Crypto ─────────── --}}
        <div class="mc">
            <div class="mc-stripe" style="background:linear-gradient(90deg,var(--amber),var(--brand))"></div>
            <div class="mc-head">
                <div class="mc-icon" style="background:var(--a-soft);color:var(--amber)">₿</div>
                <div class="mc-name">Crypto / P2P</div>
            </div>
            <div class="mc-stats">
                <div class="ms"><div class="ms-val">{{ number_format($cryptoOrders) }}</div><div class="ms-lbl">Crypto Orders</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($p2pAds) }}</div><div class="ms-lbl">P2P Ads</div></div>
            </div>
        </div>

        {{-- Wallet ─────────── --}}
        <div class="mc">
            <div class="mc-stripe" style="background:linear-gradient(90deg,var(--green),var(--teal))"></div>
            <div class="mc-head">
                <div class="mc-icon" style="background:var(--g-soft);color:var(--green)">💳</div>
                <div class="mc-name">ePay Wallet</div>
            </div>
            <div class="mc-stats">
                <div class="ms"><div class="ms-val">{{ number_format($walletTxns) }}</div><div class="ms-lbl">Transactions</div></div>
                <div class="ms"><div class="ms-val">${{ number_format($walletVolume,0) }}</div><div class="ms-lbl">Volume</div></div>
            </div>
        </div>

        {{-- Gamification ────── --}}
        <div class="mc">
            <div class="mc-stripe" style="background:linear-gradient(90deg,var(--purple),var(--blue))"></div>
            <div class="mc-head">
                <div class="mc-icon" style="background:var(--p-soft);color:var(--purple)">🏆</div>
                <div class="mc-name">Gamification</div>
            </div>
            <div class="mc-stats">
                <div class="ms"><div class="ms-val">{{ number_format($totalBadges) }}</div><div class="ms-lbl">Badges</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($totalReferrals) }}</div><div class="ms-lbl">Referrals</div></div>
            </div>
        </div>

        {{-- Users Detail ────── --}}
        <div class="mc">
            <div class="mc-stripe" style="background:linear-gradient(90deg,var(--brand),var(--amber))"></div>
            <div class="mc-head">
                <div class="mc-icon" style="background:var(--b-soft);color:var(--brand)">👥</div>
                <div class="mc-name">User Growth</div>
            </div>
            <div class="mc-stats">
                <div class="ms"><div class="ms-val">{{ number_format($newLast7) }}</div><div class="ms-lbl">Last 7d</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($newLast30) }}</div><div class="ms-lbl">Last 30d</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($newLast90) }}</div><div class="ms-lbl">Last 90d</div></div>
                <div class="ms"><div class="ms-val">{{ number_format($wau) }}</div><div class="ms-lbl">WAU</div></div>
            </div>
        </div>

        {{-- Revenue Summary ─── --}}
        <div class="mc">
            <div class="mc-stripe" style="background:linear-gradient(90deg,var(--green),var(--blue))"></div>
            <div class="mc-head">
                <div class="mc-icon" style="background:var(--g-soft);color:var(--green)">💵</div>
                <div class="mc-name">Revenue Summary</div>
            </div>
            <div class="mc-stats">
                <div class="ms"><div class="ms-val">${{ number_format($revenueToday,0) }}</div><div class="ms-lbl">Today</div></div>
                <div class="ms"><div class="ms-val">${{ number_format($revenueWeek,0) }}</div><div class="ms-lbl">7 Days</div></div>
                <div class="ms"><div class="ms-val">${{ number_format($commissionMonth,0) }}</div><div class="ms-lbl">Commission</div></div>
                <div class="ms"><div class="ms-val">${{ number_format($commissionTotal,0) }}</div><div class="ms-lbl">Total Comm.</div></div>
            </div>
        </div>
    </div>
</div>

{{-- ══ E-COMMERCE MODULES ══ --}}
<div class="sec">
    <div class="sec-title"><span class="ico">🏪</span> E-Commerce Modules</div>
    @php
    $moduleIcons = [
        'efood'      => ['🍔','#FF8A00','rgba(255,138,0,.12)'],
        'egrocery'   => ['🛒','#10b981','rgba(16,185,129,.12)'],
        'eshop'      => ['🛍️','#3b82f6','rgba(59,130,246,.12)'],
        'eparcel'    => ['📦','#8b5cf6','rgba(139,92,246,.12)'],
        'emoving'    => ['🚚','#f59e0b','rgba(245,158,11,.12)'],
        'elearning'  => ['📚','#14b8a6','rgba(20,184,166,.12)'],
        'eexchange'  => ['🔄','#ec4899','rgba(236,72,153,.12)'],
        'erent'      => ['🏠','#06b6d4','rgba(6,182,212,.12)'],
        'elaundry'   => ['👕','#6366f1','rgba(99,102,241,.12)'],
        'ewholesale' => ['🏭','#84cc16','rgba(132,204,22,.12)'],
        'edata'      => ['📊','#ef4444','rgba(239,68,68,.12)'],
        'eticket'    => ['🎟️','#f97316','rgba(249,115,22,.12)'],
        'ehealth'    => ['❤️','#10b981','rgba(16,185,129,.12)'],
    ];
    @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px">
        @foreach($moduleStats as $mod)
        @php
        $icon = $moduleIcons[$mod->slug] ?? ['📦','#6b7280','rgba(107,114,128,.12)'];
        $prodCount = $productsByModule[$mod->slug] ?? 0;
        $deliveryRate = $mod->total_orders > 0 ? round($mod->delivered_orders / $mod->total_orders * 100) : 0;
        @endphp
        <div class="cc" style="position:relative;overflow:hidden;padding:14px 16px">
            <div style="position:absolute;top:-10px;right:-10px;width:70px;height:70px;border-radius:50%;background:{{ $icon[2] }};opacity:.6"></div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                <div style="width:34px;height:34px;border-radius:8px;background:{{ $icon[2] }};display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">{{ $icon[0] }}</div>
                <div>
                    <div style="font-size:12.5px;font-weight:800;color:var(--text)">{{ $mod->name }}</div>
                    <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">{{ $mod->slug }}</div>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
                <div style="background:var(--card-bg2);border-radius:6px;padding:6px 8px">
                    <div style="font-size:14px;font-weight:800;color:{{ $icon[1] }};font-variant-numeric:tabular-nums">{{ number_format($mod->total_orders) }}</div>
                    <div style="font-size:9.5px;color:var(--muted)">Orders</div>
                </div>
                <div style="background:var(--card-bg2);border-radius:6px;padding:6px 8px">
                    <div style="font-size:14px;font-weight:800;color:var(--green);font-variant-numeric:tabular-nums">{{ number_format($mod->delivered_revenue, 0) }}</div>
                    <div style="font-size:9.5px;color:var(--muted)">Revenue</div>
                </div>
                <div style="background:var(--card-bg2);border-radius:6px;padding:6px 8px">
                    <div style="font-size:14px;font-weight:800;color:var(--blue);font-variant-numeric:tabular-nums">{{ number_format($mod->vendor_count) }}</div>
                    <div style="font-size:9.5px;color:var(--muted)">Vendors</div>
                </div>
                <div style="background:var(--card-bg2);border-radius:6px;padding:6px 8px">
                    <div style="font-size:14px;font-weight:800;color:var(--purple);font-variant-numeric:tabular-nums">{{ $deliveryRate }}%</div>
                    <div style="font-size:9.5px;color:var(--muted)">Delivery Rate</div>
                </div>
            </div>
            @if($prodCount > 0)
            <div style="margin-top:8px;font-size:10px;color:var(--muted)">
                <span style="color:{{ $icon[1] }};font-weight:700">{{ number_format($prodCount) }}</span> active products
            </div>
            @endif
        </div>
        @endforeach
    </div>
</div>

{{-- ══ RETENTION + SNAPSHOT ══ --}}
<div class="sec">
    <div class="sec-title"><span class="ico">🔁</span> Retention & Platform Snapshot</div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">

        {{-- Retention ──────── --}}
        <div class="cc">
            <div class="cc-title" style="margin-bottom:10px">Customer Retention</div>
            <div class="ret-ring">
                <div class="ring-wrap">
                    <svg width="90" height="90" viewBox="0 0 90 90">
                        <circle cx="45" cy="45" r="36" fill="none" stroke="#e4e9f0" stroke-width="9"/>
                        <circle cx="45" cy="45" r="36" fill="none" stroke="#FF8A00" stroke-width="9"
                            stroke-dasharray="{{ round($retentionRate*2.262) }} 226.2"
                            stroke-linecap="round"/>
                    </svg>
                    <div class="ring-num">{{ $retentionRate }}%</div>
                </div>
                <div class="ret-desc">
                    <strong>{{ number_format($repeatCustomers) }}</strong> repeat out of <strong>{{ number_format($totalOrderingUsers) }}</strong> ordering users.<br><br>
                    Avg <strong>{{ $totalOrderingUsers>0?round($totalOrders/$totalOrderingUsers,1):0 }}</strong> orders/user
                </div>
            </div>
            <hr class="div">
            <div class="pills">
                <div class="pill"><div class="pill-val">{{ number_format($totalBadges) }}</div><div class="pill-lbl">Badges</div></div>
                <div class="pill"><div class="pill-val">{{ number_format($totalReferrals) }}</div><div class="pill-lbl">Referrals</div></div>
                <div class="pill"><div class="pill-val">{{ number_format($mau) }}</div><div class="pill-lbl">MAU</div></div>
            </div>
        </div>

        {{-- Snapshot ────────── --}}
        <div class="cc">
            <div class="cc-title" style="margin-bottom:10px">Cross-Module Snapshot</div>
            @php
            $snaps = [
                ['🌐','eSocial Posts',number_format($totalPosts)],
                ['❤️','Reactions',number_format($totalReactions)],
                ['🎙','Live Rooms',number_format($liveRoomsTotal)],
                ['🎧','Podcast Plays',number_format($podcastPlays)],
                ['💑','eMarry Matches',number_format($emarryMatches)],
                ['🏠','House Requests',number_format($houseRequests)],
                ['₿','Crypto Orders',number_format($cryptoOrders)],
                ['💳','Wallet Txns',number_format($walletTxns)],
                ['📚','eLearning Enroll',number_format($elEnrollments)],
                ['🏆','Badges Earned',number_format($totalBadges)],
            ];
            @endphp
            @foreach($snaps as $r)
            <div class="snap-row">
                <span class="snap-lbl">{{ $r[0] }} {{ $r[1] }}</span>
                <span class="snap-val">{{ $r[2] }}</span>
            </div>
            @endforeach
        </div>

        {{-- Community chart ─── --}}
        <div class="cc">
            <div class="cc-title" style="margin-bottom:2px">Community & User Activity</div>
            <div class="cc-sub" style="margin-bottom:10px">Posts · 14 days</div>
            <div class="canvas-wrap" style="height:130px"><canvas id="cComm2"></canvas></div>
            <hr class="div">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:4px">
                <div class="ms"><div class="ms-val" style="font-size:18px">{{ number_format($totalChats) }}</div><div class="ms-lbl">Chat Rooms</div></div>
                <div class="ms"><div class="ms-val" style="font-size:18px">{{ number_format($totalMessages) }}</div><div class="ms-lbl">Messages</div></div>
                <div class="ms"><div class="ms-val" style="font-size:18px">{{ number_format($giftsSent) }}</div><div class="ms-lbl">Gifts Sent</div></div>
                <div class="ms"><div class="ms-val" style="font-size:18px">{{ number_format($liveViewersTotal) }}</div><div class="ms-lbl">Live Viewers</div></div>
            </div>
        </div>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const BR='#FF8A00',BRa='rgba(255,138,0,.13)';
const GR='#10b981',GRa='rgba(16,185,129,.13)';
const BL='#3b82f6',BLa='rgba(59,130,246,.13)';
const PR='#8b5cf6',PRa='rgba(139,92,246,.13)';
const grid='rgba(0,0,0,0.04)',muted='#9ca3af';

Chart.defaults.font.family="'Segoe UI',system-ui,sans-serif";
Chart.defaults.font.size=10;
Chart.defaults.color=muted;

const base={
    responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false},tooltip:{mode:'index',intersect:false,callbacks:{}}},
    scales:{x:{grid:{color:grid},ticks:{maxTicksLimit:8}},y:{grid:{color:grid},beginAtZero:true}}
};

// Module Revenue — horizontal bar
const modData=@json($revenueByModule);
if(document.getElementById('cModuleRev')&&modData.length){
    new Chart(document.getElementById('cModuleRev'),{
        type:'bar',
        data:{
            labels:modData.map(d=>d.module),
            datasets:[{
                data:modData.map(d=>d.revenue),
                backgroundColor:[BR,BL,GR,PR,'#14b8a6','#f59e0b','#ec4899','#06b6d4'].slice(0,modData.length),
                borderRadius:4,borderSkipped:false
            }]
        },
        options:{
            responsive:true,maintainAspectRatio:false,
            indexAxis:'y',
            plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>'$'+ctx.raw.toLocaleString()}}},
            scales:{
                x:{grid:{color:grid},beginAtZero:true,ticks:{callback:v=>'$'+v.toLocaleString()}},
                y:{grid:{display:false}}
            }
        }
    });
}

// Daily Revenue
const dr=@json($dailyRevenue);
new Chart(document.getElementById('cDailyRev'),{
    type:'line',
    data:{labels:dr.map(d=>d.date.slice(5)),datasets:[{
        data:dr.map(d=>d.revenue),borderColor:BR,backgroundColor:BRa,
        borderWidth:2,fill:true,tension:.45,pointRadius:0,pointHoverRadius:4
    }]},
    options:{...base}
});

// User Growth
const ug=@json($userGrowth);
new Chart(document.getElementById('cUserGrowth'),{
    type:'bar',
    data:{labels:ug.map(d=>d.date.slice(5)),datasets:[{
        data:ug.map(d=>d.new_users),backgroundColor:BRa,borderColor:BR,
        borderWidth:1.5,borderRadius:3
    }]},
    options:{...base}
});

// Monthly
const mr=@json($monthlyRevenue);
new Chart(document.getElementById('cMonthly'),{
    type:'bar',
    data:{labels:mr.map(d=>d.month),datasets:[
        {label:'Revenue ($)',data:mr.map(d=>d.revenue),backgroundColor:BR,borderRadius:4,yAxisID:'y'},
        {label:'Orders',data:mr.map(d=>d.orders),type:'line',borderColor:BL,backgroundColor:BLa,
         borderWidth:1.5,fill:false,tension:.4,pointRadius:2,yAxisID:'y1'}
    ]},
    options:{...base,
        plugins:{legend:{display:true,position:'top',labels:{boxWidth:10,font:{size:10}}},tooltip:{mode:'index',intersect:false}},
        scales:{
            x:{grid:{color:grid}},
            y:{grid:{color:grid},beginAtZero:true,position:'left'},
            y1:{grid:{display:false},beginAtZero:true,position:'right'}
        }
    }
});

// Community (used twice — different canvas)
const ca=@json($communityActivity);
[document.getElementById('cComm'),document.getElementById('cComm2')].forEach(el=>{
    if(!el)return;
    new Chart(el,{
        type:'line',
        data:{labels:ca.map(d=>d.date.slice(5)),datasets:[{
            data:ca.map(d=>d.posts),borderColor:GR,backgroundColor:GRa,
            borderWidth:1.5,fill:true,tension:.45,pointRadius:0,pointHoverRadius:3
        }]},
        options:{...base}
    });
});

// ── "Last updated" timer ───────────────────────────────────────────────────
const loadedAt = Date.now();
const chip = document.getElementById('last-updated');
setInterval(() => {
    const s = Math.floor((Date.now() - loadedAt) / 1000);
    if (s < 60) chip.textContent = '🔄 ' + s + 's ago';
    else chip.textContent = '🔄 ' + Math.floor(s/60) + 'm ago';
}, 5000);

// ── Auto-refresh every 90 seconds (live data: DAU, orders, live rooms) ─────
// Only refreshes the /analytics/api endpoint and patches live numbers
const LIVE_FIELDS = ['dau','wau','mau','liveOnline','liveRoomsToday','pendingOrders'];
setInterval(async () => {
    try {
        const r = await fetch('/admin/analytics/api');
        if (!r.ok) return;
        const {data} = await r.json();

        // Patch KPI strip values
        const patches = {
            'kpi-dau':    data.dau + ' / ' + data.wau + 'W / ' + data.mau + 'M',
            'kpi-live':   data.liveOnline + ' active',
            'kpi-pending': data.pendingOrders + ' pending',
        };
        Object.entries(patches).forEach(([id, val]) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        });

        // Patch header chip
        document.getElementById('last-updated').textContent = '🔄 Just now';
        setTimeout(() => {
            const s = Math.floor((Date.now() - loadedAt) / 1000);
            chip.textContent = '🔄 ' + Math.floor(s/60) + 'm ago';
        }, 3000);
    } catch(e) {}
}, 90000);
</script>
@endpush
