@extends('admin.layouts.app')
@section('title', 'Platform Analytics')

@push('styles')
<style>
:root {
    --brand: #FF8A00;
    --brand-soft: rgba(255,138,0,0.10);
    --navy: #07003B;
    --surface: #fff;
    --bg: #f1f5f9;
    --border: #e2e8f0;
    --text: #1a1a2e;
    --text-muted: #64748b;
    --green: #16a34a;
    --green-soft: rgba(22,163,74,0.10);
    --blue: #3b82f6;
    --blue-soft: rgba(59,130,246,0.10);
    --purple: #8b5cf6;
    --purple-soft: rgba(139,92,246,0.10);
    --pink: #ec4899;
    --pink-soft: rgba(236,72,153,0.10);
    --teal: #0d9488;
    --teal-soft: rgba(13,148,136,0.10);
    --red: #ef4444;
    --red-soft: rgba(239,68,68,0.10);
    --amber: #f59e0b;
    --amber-soft: rgba(245,158,11,0.10);
}

* { box-sizing: border-box; }

.analytics-wrap { padding: 24px 28px; max-width: 1440px; }

/* ── Header ── */
.an-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    margin-bottom: 28px; gap: 16px; flex-wrap: wrap;
}
.an-title { font-size: 24px; font-weight: 900; color: var(--text); letter-spacing: -.3px; }
.an-subtitle { font-size: 13px; color: var(--text-muted); margin-top: 3px; }
.an-meta { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 6px; }
.an-badge {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--bg); border: 1px solid var(--border);
    border-radius: 20px; padding: 4px 12px;
    font-size: 11px; font-weight: 700; color: var(--text-muted);
}
.an-badge .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--green); }
.an-refresh-btn {
    display: flex; align-items: center; gap: 7px;
    background: var(--brand); color: #fff; border: none;
    border-radius: 10px; padding: 10px 20px; font-size: 13px;
    font-weight: 700; cursor: pointer; transition: opacity .2s; white-space: nowrap;
}
.an-refresh-btn:hover { opacity: .88; }

/* ── Section ── */
.an-section { margin-bottom: 32px; }
.an-section-title {
    font-size: 11px; font-weight: 800; text-transform: uppercase;
    letter-spacing: .10em; color: var(--text-muted);
    margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
}
.an-section-title .icon { font-size: 14px; }
.an-section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* ── Grids ── */
.g1 { display: grid; grid-template-columns: 1fr; gap: 14px; }
.g2 { display: grid; grid-template-columns: repeat(2,1fr); gap: 14px; }
.g3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; }
.g4 { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; }
.g5 { display: grid; grid-template-columns: repeat(5,1fr); gap: 14px; }
.g6 { display: grid; grid-template-columns: repeat(6,1fr); gap: 14px; }
@media(max-width:1200px){
    .g5{grid-template-columns:repeat(3,1fr)}
    .g6{grid-template-columns:repeat(3,1fr)}
}
@media(max-width:900px){
    .g4,.g3{grid-template-columns:repeat(2,1fr)}
    .g5,.g6{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:600px){
    .g2,.g3,.g4,.g5,.g6{grid-template-columns:1fr}
}

/* ── Stat Card ── */
.stat-card {
    background: var(--surface); border-radius: 16px;
    border: 1.5px solid var(--border); padding: 18px 20px;
    display: flex; flex-direction: column; gap: 3px;
    position: relative; overflow: hidden;
    transition: transform .18s, box-shadow .18s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,0,0,.08); }
.stat-card-icon {
    width: 38px; height: 38px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; margin-bottom: 8px; flex-shrink: 0;
}
.stat-card-val {
    font-size: 26px; font-weight: 900; color: var(--text);
    font-variant-numeric: tabular-nums; line-height: 1;
}
.stat-card-label { font-size: 12px; color: var(--text-muted); font-weight: 600; margin-top: 2px; }
.stat-card-sub { font-size: 11px; color: var(--text-muted); margin-top: 5px; }
.badge-up { color: var(--green); font-weight: 700; }
.badge-down { color: var(--red); font-weight: 700; }
.badge-neutral { color: var(--text-muted); font-weight: 600; }

/* ── Live indicator ── */
.live-badge {
    position: absolute; top: 14px; right: 14px;
    background: var(--red); color: #fff;
    border-radius: 20px; padding: 2px 8px;
    font-size: 10px; font-weight: 800; letter-spacing: .05em;
    animation: livePulse 1.8s ease-in-out infinite;
}
@keyframes livePulse { 0%,100%{opacity:1} 50%{opacity:.6} }

/* ── Chart Card ── */
.chart-card {
    background: var(--surface); border-radius: 16px;
    border: 1.5px solid var(--border); padding: 22px;
}
.chart-card-title { font-size: 14px; font-weight: 800; color: var(--text); margin-bottom: 2px; }
.chart-card-sub { font-size: 12px; color: var(--text-muted); margin-bottom: 16px; }
.chart-container { position: relative; }

/* ── Module bars ── */
.module-bar-list { display: flex; flex-direction: column; gap: 11px; }
.module-bar-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.module-bar-name { font-size: 12px; font-weight: 700; color: var(--text); }
.module-bar-val { font-size: 12px; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.module-bar-track { height: 7px; background: var(--bg); border-radius: 99px; overflow: hidden; }
.module-bar-fill { height: 100%; border-radius: 99px; background: var(--brand); transition: width .6s ease; }

/* ── Funnel ── */
.funnel-list { display: flex; flex-direction: column; gap: 7px; }
.funnel-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 9px 13px; border-radius: 10px; background: var(--bg); font-size: 13px;
}
.funnel-status { font-weight: 700; text-transform: capitalize; }
.funnel-count { font-weight: 800; font-variant-numeric: tabular-nums; }
.status-pending   { color: var(--amber); }
.status-accepted,.status-delivered,.status-approved,.status-completed { color: var(--green); }
.status-cancelled,.status-rejected,.status-failed { color: var(--red); }
.status-processing,.status-confirmed { color: var(--blue); }
.status-picked_up { color: var(--purple); }

/* ── Pill grid ── */
.pill-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 9px; }
.pill {
    background: var(--bg); border-radius: 12px; padding: 13px 10px;
    text-align: center;
}
.pill-val { font-size: 20px; font-weight: 900; color: var(--text); font-variant-numeric: tabular-nums; }
.pill-label { font-size: 10px; color: var(--text-muted); font-weight: 700; margin-top: 2px; text-transform: uppercase; letter-spacing: .05em; }

/* ── Vendor table ── */
.vendor-table { width: 100%; border-collapse: collapse; }
.vendor-table th {
    text-align: left; font-size: 10px; font-weight: 800;
    text-transform: uppercase; letter-spacing: .07em;
    color: var(--text-muted); padding: 0 10px 9px; border-bottom: 1.5px solid var(--border);
}
.vendor-table td { padding: 9px 10px; font-size: 13px; border-bottom: 1px solid var(--border); }
.vendor-table tr:last-child td { border-bottom: none; }
.vendor-rank {
    width: 22px; height: 22px; border-radius: 50%;
    background: var(--bg); display: inline-flex; align-items: center;
    justify-content: center; font-size: 10px; font-weight: 900; color: var(--text-muted);
}
.vendor-rank.gold   { background: #fef3c7; color: #d97706; }
.vendor-rank.silver { background: #f1f5f9; color: #64748b; }
.vendor-rank.bronze { background: #fef2ee; color: #c2410c; }
.rev-amount { font-weight: 800; color: var(--text); font-variant-numeric: tabular-nums; }

/* ── Retention ring ── */
.retention-ring { display: flex; align-items: center; gap: 22px; padding: 16px 0; }
.ring-wrap { position: relative; width: 110px; height: 110px; flex-shrink: 0; }
.ring-wrap svg { transform: rotate(-90deg); }
.ring-pct {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
    font-size: 20px; font-weight: 900; color: var(--text);
}
.retention-desc { font-size: 13px; color: var(--text-muted); line-height: 1.6; }
.retention-desc strong { color: var(--text); }

/* ── Module card (feature modules overview) ── */
.module-card {
    background: var(--surface); border-radius: 16px;
    border: 1.5px solid var(--border); padding: 16px;
    display: flex; flex-direction: column; gap: 10px;
    transition: transform .18s, box-shadow .18s;
}
.module-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.07); }
.module-card-head { display: flex; align-items: center; gap: 10px; }
.module-card-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;
}
.module-card-name { font-size: 13px; font-weight: 800; color: var(--text); }
.module-card-stats { display: flex; gap: 16px; flex-wrap: wrap; }
.mstat { display: flex; flex-direction: column; }
.mstat-val { font-size: 18px; font-weight: 900; color: var(--text); font-variant-numeric: tabular-nums; }
.mstat-lbl { font-size: 10px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }

/* ── Divider inside chart card ── */
.card-divider { border: none; border-top: 1px solid var(--border); margin: 16px 0; }

/* ── Scrollable table wrapper ── */
.table-scroll { overflow-x: auto; }
</style>
@endpush

@section('content')
<div class="analytics-wrap">

{{-- ════ HEADER ════ --}}
<div class="an-header">
    <div>
        <div class="an-title">📊 Platform Analytics</div>
        <div class="an-subtitle">Full-platform intelligence — updated on page load</div>
        <div class="an-meta">
            <span class="an-badge"><span class="dot"></span> {{ $liveOnline }} Live now</span>
            <span class="an-badge">🕐 {{ now()->format('d M Y · H:i') }}</span>
            <span class="an-badge">👥 {{ number_format($dau) }} users active today</span>
        </div>
    </div>
    <button class="an-refresh-btn" onclick="location.reload()">
        <i class="fas fa-sync-alt"></i> Refresh
    </button>
</div>

{{-- ════ USER METRICS ════ --}}
<div class="an-section">
    <div class="an-section-title"><span class="icon">👥</span> User Metrics</div>
    <div class="g4" style="margin-bottom:14px">
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--brand-soft);color:var(--brand)">👥</div>
            <div class="stat-card-val">{{ number_format($totalUsers) }}</div>
            <div class="stat-card-label">Total Registered Users</div>
            <div class="stat-card-sub badge-up">+{{ number_format($newLast30) }} this month</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--green-soft);color:var(--green)">📅</div>
            <div class="stat-card-val">{{ number_format($dau) }}</div>
            <div class="stat-card-label">DAU (Daily Active)</div>
            <div class="stat-card-sub badge-neutral">{{ $totalUsers > 0 ? round($dau/$totalUsers*100,1) : 0 }}% of total</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--blue-soft);color:var(--blue)">📆</div>
            <div class="stat-card-val">{{ number_format($wau) }}</div>
            <div class="stat-card-label">WAU (Weekly Active)</div>
            <div class="stat-card-sub badge-neutral">Last 7 days</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--purple-soft);color:var(--purple)">🗓️</div>
            <div class="stat-card-val">{{ number_format($mau) }}</div>
            <div class="stat-card-label">MAU (Monthly Active)</div>
            <div class="stat-card-sub badge-neutral">Last 30 days</div>
        </div>
    </div>
    <div class="g4">
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--teal-soft);color:var(--teal)">🆕</div>
            <div class="stat-card-val">{{ number_format($newLast7) }}</div>
            <div class="stat-card-label">New Users (7 Days)</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--brand-soft);color:var(--brand)">📈</div>
            <div class="stat-card-val">{{ number_format($newLast90) }}</div>
            <div class="stat-card-label">New Users (90 Days)</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--amber-soft);color:var(--amber)">🏪</div>
            <div class="stat-card-val">{{ number_format($totalVendors) }}</div>
            <div class="stat-card-label">Total Vendors</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--blue-soft);color:var(--blue)">🚗</div>
            <div class="stat-card-val">{{ number_format($totalDrivers) }}</div>
            <div class="stat-card-label">Total Delivery Drivers</div>
        </div>
    </div>
</div>

{{-- ════ REVENUE ════ --}}
<div class="an-section">
    <div class="an-section-title"><span class="icon">💰</span> Revenue</div>
    <div class="g4">
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--brand-soft);color:var(--brand)">💵</div>
            <div class="stat-card-val">${{ number_format($revenueToday, 0) }}</div>
            <div class="stat-card-label">Revenue Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--blue-soft);color:var(--blue)">📊</div>
            <div class="stat-card-val">${{ number_format($revenueWeek, 0) }}</div>
            <div class="stat-card-label">Revenue Last 7 Days</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--green-soft);color:var(--green)">📈</div>
            <div class="stat-card-val">${{ number_format($revenueMonth, 0) }}</div>
            <div class="stat-card-label">Revenue This Month</div>
            <div class="stat-card-sub badge-neutral">Commission: ${{ number_format($commissionMonth, 0) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--purple-soft);color:var(--purple)">🏆</div>
            <div class="stat-card-val">${{ number_format($revenueTotal, 0) }}</div>
            <div class="stat-card-label">Total Revenue (All Time)</div>
            <div class="stat-card-sub badge-up">Commission: ${{ number_format($commissionTotal, 0) }}</div>
        </div>
    </div>
</div>

{{-- ════ TRENDS ════ --}}
<div class="an-section">
    <div class="an-section-title"><span class="icon">📉</span> Trends (Last 30 Days)</div>
    <div class="g2">
        <div class="chart-card">
            <div class="chart-card-title">Daily Revenue</div>
            <div class="chart-card-sub">Delivered orders · last 30 days</div>
            <div class="chart-container" style="height:200px"><canvas id="dailyRevenueChart"></canvas></div>
        </div>
        <div class="chart-card">
            <div class="chart-card-title">New User Signups</div>
            <div class="chart-card-sub">Registrations · last 30 days</div>
            <div class="chart-container" style="height:200px"><canvas id="userGrowthChart"></canvas></div>
        </div>
    </div>
</div>

{{-- ════ MONTHLY REVENUE ════ --}}
<div class="an-section">
    <div class="an-section-title"><span class="icon">📅</span> Monthly Revenue (Last 12 Months)</div>
    <div class="chart-card">
        <div class="chart-card-title">Monthly Revenue & Orders</div>
        <div class="chart-card-sub">Delivered orders only</div>
        <div class="chart-container" style="height:220px"><canvas id="monthlyRevenueChart"></canvas></div>
    </div>
</div>

{{-- ════ ORDERS & MODULES ════ --}}
<div class="an-section">
    <div class="an-section-title"><span class="icon">🗂️</span> Orders & Module Performance</div>
    <div class="g4" style="margin-bottom:14px">
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--brand-soft);color:var(--brand)">📦</div>
            <div class="stat-card-val">{{ number_format($totalOrders) }}</div>
            <div class="stat-card-label">Total Orders (All Time)</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--green-soft);color:var(--green)">✅</div>
            <div class="stat-card-val">{{ number_format($orderFunnel->get('delivered', 0)) }}</div>
            <div class="stat-card-label">Delivered Orders</div>
            <div class="stat-card-sub badge-up">
                {{ $totalOrders > 0 ? round($orderFunnel->get('delivered',0)/$totalOrders*100,1) : 0 }}% completion
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--amber-soft);color:var(--amber)">⏳</div>
            <div class="stat-card-val">{{ number_format($orderFunnel->get('pending', 0)) }}</div>
            <div class="stat-card-label">Pending Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--red-soft);color:var(--red)">❌</div>
            <div class="stat-card-val">{{ number_format($orderFunnel->get('cancelled', 0)) }}</div>
            <div class="stat-card-label">Cancelled Orders</div>
        </div>
    </div>
    <div class="g2">
        <div class="chart-card">
            <div class="chart-card-title">Revenue by Module</div>
            <div class="chart-card-sub">All-time delivered orders breakdown</div>
            <div class="module-bar-list">
                @php $maxRev = $revenueByModule->max('revenue') ?: 1; @endphp
                @forelse($revenueByModule as $mod)
                <div>
                    <div class="module-bar-header">
                        <span class="module-bar-name">{{ $mod['module'] }}</span>
                        <span class="module-bar-val">${{ number_format($mod['revenue'],0) }} · {{ number_format($mod['orders']) }} orders</span>
                    </div>
                    <div class="module-bar-track">
                        <div class="module-bar-fill" style="width:{{ round($mod['revenue']/$maxRev*100) }}%"></div>
                    </div>
                </div>
                @empty
                <div style="color:var(--text-muted);font-size:13px;text-align:center;padding:20px">No order data yet</div>
                @endforelse
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-card-title">Order Status Funnel</div>
            <div class="chart-card-sub">All-time distribution</div>
            <div class="funnel-list">
                @foreach($orderFunnel as $status => $count)
                <div class="funnel-item">
                    <span class="funnel-status status-{{ $status }}">{{ ucfirst(str_replace('_',' ',$status)) }}</span>
                    <span class="funnel-count">{{ number_format($count) }}</span>
                </div>
                @endforeach
                @if($orderFunnel->isEmpty())
                <div style="color:var(--text-muted);font-size:13px;text-align:center;padding:20px">No orders yet</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ════ COMMUNITY (eSocial) ════ --}}
<div class="an-section">
    <div class="an-section-title"><span class="icon">🌐</span> eSocial — Community Platform</div>
    <div class="g4" style="margin-bottom:14px">
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--brand-soft);color:var(--brand)">📝</div>
            <div class="stat-card-val">{{ number_format($totalPosts) }}</div>
            <div class="stat-card-label">Total Posts</div>
            <div class="stat-card-sub badge-up">+{{ number_format($postsThisWeek) }} this week</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--pink-soft);color:var(--pink)">❤️</div>
            <div class="stat-card-val">{{ number_format($totalReactions) }}</div>
            <div class="stat-card-label">Total Reactions</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--blue-soft);color:var(--blue)">💬</div>
            <div class="stat-card-val">{{ number_format($totalComments) }}</div>
            <div class="stat-card-label">Total Comments</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--purple-soft);color:var(--purple)">👥</div>
            <div class="stat-card-val">{{ number_format($totalFollows) }}</div>
            <div class="stat-card-label">Total Follows</div>
        </div>
    </div>
    <div class="g2">
        <div class="chart-card">
            <div class="chart-card-title">Community Activity</div>
            <div class="chart-card-sub">Posts per day — last 14 days</div>
            <div class="chart-container" style="height:160px"><canvas id="communityChart"></canvas></div>
            <hr class="card-divider">
            <div class="pill-grid">
                <div class="pill">
                    <div class="pill-val">{{ number_format($totalStories) }}</div>
                    <div class="pill-label">Stories</div>
                </div>
                <div class="pill">
                    <div class="pill-val">{{ number_format($totalChats) }}</div>
                    <div class="pill-label">Chats</div>
                </div>
                <div class="pill">
                    <div class="pill-val">{{ number_format($totalMessages) }}</div>
                    <div class="pill-label">Messages</div>
                </div>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-card-title">Top Vendors by Revenue</div>
            <div class="chart-card-sub">All-time delivered orders</div>
            <div class="table-scroll">
            <table class="vendor-table">
                <thead>
                    <tr><th>#</th><th>Vendor</th><th>Orders</th><th>Revenue</th></tr>
                </thead>
                <tbody>
                    @forelse($topVendors as $i => $v)
                    <tr>
                        <td><span class="vendor-rank {{ $i==0?'gold':($i==1?'silver':($i==2?'bronze':'')) }}">{{ $i+1 }}</span></td>
                        <td style="font-weight:700">{{ $v->name }}</td>
                        <td>{{ number_format($v->orders) }}</td>
                        <td class="rev-amount">${{ number_format($v->revenue,0) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:20px">No vendor data yet</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

{{-- ════ LIVE PLATFORM ════ --}}
<div class="an-section">
    <div class="an-section-title"><span class="icon">🎙</span> eSpace — Live Platform</div>
    <div class="g4">
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--red-soft);color:var(--red)">🔴</div>
            <div class="stat-card-val">{{ number_format($liveOnline) }}</div>
            <div class="stat-card-label">Live Rooms Active Now</div>
            <div class="live-badge">LIVE</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--brand-soft);color:var(--brand)">📺</div>
            <div class="stat-card-val">{{ number_format($liveRoomsTotal) }}</div>
            <div class="stat-card-label">Total Live Rooms</div>
            <div class="stat-card-sub badge-neutral">+{{ number_format($liveRoomsToday) }} today</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--blue-soft);color:var(--blue)">👁️</div>
            <div class="stat-card-val">{{ number_format($liveViewersTotal) }}</div>
            <div class="stat-card-label">Total Viewers (All Time)</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--pink-soft);color:var(--pink)">🎁</div>
            <div class="stat-card-val">{{ number_format($giftsSent) }}</div>
            <div class="stat-card-label">Gifts Sent</div>
            <div class="stat-card-sub badge-neutral">{{ number_format($coinsIssued) }} coins in circulation</div>
        </div>
    </div>
</div>

{{-- ════ eLEARNING ════ --}}
<div class="an-section">
    <div class="an-section-title"><span class="icon">🎓</span> eLearning Platform</div>
    <div class="g4">
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--blue-soft);color:var(--blue)">📚</div>
            <div class="stat-card-val">{{ number_format($elCourses) }}</div>
            <div class="stat-card-label">Total Courses</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--green-soft);color:var(--green)">🎯</div>
            <div class="stat-card-val">{{ number_format($elEnrollments) }}</div>
            <div class="stat-card-label">Total Enrollments</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--purple-soft);color:var(--purple)">👨‍🏫</div>
            <div class="stat-card-val">{{ number_format($elInstructors) }}</div>
            <div class="stat-card-label">Instructors</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--amber-soft);color:var(--amber)">🏅</div>
            <div class="stat-card-val">{{ number_format($elCertificates) }}</div>
            <div class="stat-card-label">Certificates Issued</div>
        </div>
    </div>
</div>

{{-- ════ PODCAST ════ --}}
<div class="an-section">
    <div class="an-section-title"><span class="icon">🎧</span> Podcast Platform</div>
    <div class="g4">
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--purple-soft);color:var(--purple)">🎙</div>
            <div class="stat-card-val">{{ number_format($podcastShows) }}</div>
            <div class="stat-card-label">Podcast Shows</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--blue-soft);color:var(--blue)">🎵</div>
            <div class="stat-card-val">{{ number_format($podcastEpisodes) }}</div>
            <div class="stat-card-label">Episodes Published</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--green-soft);color:var(--green)">▶️</div>
            <div class="stat-card-val">{{ number_format($podcastPlays) }}</div>
            <div class="stat-card-label">Total Plays</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--pink-soft);color:var(--pink)">💛</div>
            <div class="stat-card-val">{{ number_format($podcastFollows) }}</div>
            <div class="stat-card-label">Show Followers</div>
        </div>
    </div>
</div>

{{-- ════ ERENT & EMARRY ════ --}}
<div class="an-section">
    <div class="an-section-title"><span class="icon">🏠</span> eRent & eMarry</div>
    <div class="g4">
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--teal-soft);color:var(--teal)">🏠</div>
            <div class="stat-card-val">{{ number_format($houseRequests) }}</div>
            <div class="stat-card-label">House Requests</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--blue-soft);color:var(--blue)">🤝</div>
            <div class="stat-card-val">{{ number_format($rentAgents) }}</div>
            <div class="stat-card-label">Property Agents</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--pink-soft);color:var(--pink)">💑</div>
            <div class="stat-card-val">{{ number_format($emarryProfiles) }}</div>
            <div class="stat-card-label">eMarry Profiles</div>
            <div class="stat-card-sub badge-neutral">{{ number_format($emarryApproved) }} approved · {{ number_format($emarryPending) }} pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--red-soft);color:var(--red)">💞</div>
            <div class="stat-card-val">{{ number_format($emarryMatches) }}</div>
            <div class="stat-card-label">Successful Matches</div>
            <div class="stat-card-sub badge-neutral">{{ number_format($emarryLikes) }} total likes sent</div>
        </div>
    </div>
</div>

{{-- ════ CRYPTO & WALLET ════ --}}
<div class="an-section">
    <div class="an-section-title"><span class="icon">₿</span> Crypto & Wallet</div>
    <div class="g4">
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--amber-soft);color:var(--amber)">₿</div>
            <div class="stat-card-val">{{ number_format($cryptoOrders) }}</div>
            <div class="stat-card-label">Crypto Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--green-soft);color:var(--green)">🔄</div>
            <div class="stat-card-val">{{ number_format($p2pAds) }}</div>
            <div class="stat-card-label">P2P Trade Ads</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--blue-soft);color:var(--blue)">💳</div>
            <div class="stat-card-val">{{ number_format($walletTxns) }}</div>
            <div class="stat-card-label">Wallet Transactions</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon" style="background:var(--purple-soft);color:var(--purple)">💰</div>
            <div class="stat-card-val">${{ number_format($walletVolume, 0) }}</div>
            <div class="stat-card-label">Wallet Volume (Credits)</div>
        </div>
    </div>
</div>

{{-- ════ RETENTION & GAMIFICATION ════ --}}
<div class="an-section">
    <div class="an-section-title"><span class="icon">🔄</span> Retention & Gamification</div>
    <div class="g2">
        <div class="chart-card">
            <div class="chart-card-title">Customer Retention Rate</div>
            <div class="chart-card-sub">Users who placed more than one order</div>
            <div class="retention-ring">
                <div class="ring-wrap">
                    <svg width="110" height="110" viewBox="0 0 110 110">
                        <circle cx="55" cy="55" r="46" fill="none" stroke="#e2e8f0" stroke-width="11"/>
                        <circle cx="55" cy="55" r="46" fill="none" stroke="#FF8A00" stroke-width="11"
                            stroke-dasharray="{{ round($retentionRate * 2.89) }} 289"
                            stroke-linecap="round"/>
                    </svg>
                    <div class="ring-pct">{{ $retentionRate }}%</div>
                </div>
                <div class="retention-desc">
                    <strong>{{ number_format($repeatCustomers) }} repeat customers</strong> out of
                    {{ number_format($totalOrderingUsers) }} total ordering users.<br><br>
                    A retention rate above 30% signals strong product-market fit.
                </div>
            </div>
            <hr class="card-divider">
            <div class="pill-grid">
                <div class="pill">
                    <div class="pill-val">{{ number_format($totalBadges) }}</div>
                    <div class="pill-label">Badges Earned</div>
                </div>
                <div class="pill">
                    <div class="pill-val">{{ number_format($totalReferrals) }}</div>
                    <div class="pill-label">Referrals</div>
                </div>
                <div class="pill">
                    <div class="pill-val">{{ $totalOrderingUsers > 0 ? round($totalOrders/$totalOrderingUsers,1) : 0 }}</div>
                    <div class="pill-label">Orders / User</div>
                </div>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-card-title">Platform Snapshot</div>
            <div class="chart-card-sub">Cross-module summary</div>
            <div style="display:flex;flex-direction:column;gap:8px">
                @php
                $snapshot = [
                    ['icon'=>'🌐','label'=>'eSocial Posts','val'=>number_format($totalPosts)],
                    ['icon'=>'🎙','label'=>'Live Rooms Total','val'=>number_format($liveRoomsTotal)],
                    ['icon'=>'📚','label'=>'eLearning Enrollments','val'=>number_format($elEnrollments)],
                    ['icon'=>'🎧','label'=>'Podcast Plays','val'=>number_format($podcastPlays)],
                    ['icon'=>'💑','label'=>'eMarry Matches','val'=>number_format($emarryMatches)],
                    ['icon'=>'🏠','label'=>'House Requests','val'=>number_format($houseRequests)],
                    ['icon'=>'₿','label'=>'Crypto Orders','val'=>number_format($cryptoOrders)],
                    ['icon'=>'💳','label'=>'Wallet Transactions','val'=>number_format($walletTxns)],
                ];
                @endphp
                @foreach($snapshot as $row)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
                    <span style="font-size:13px;color:var(--text-muted)">{{ $row['icon'] }} {{ $row['label'] }}</span>
                    <span style="font-size:14px;font-weight:800;color:var(--text);font-variant-numeric:tabular-nums">{{ $row['val'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const brand   = '#FF8A00';
const brandBg = 'rgba(255,138,0,0.12)';
const blue    = '#3b82f6';
const blueBg  = 'rgba(59,130,246,0.12)';
const green   = '#16a34a';
const greenBg = 'rgba(22,163,74,0.12)';
const grid    = 'rgba(0,0,0,0.04)';
const muted   = '#94a3b8';

Chart.defaults.font.family = "'Segoe UI',system-ui,sans-serif";
Chart.defaults.font.size   = 11;
Chart.defaults.color       = muted;

const base = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend:{ display:false }, tooltip:{ mode:'index', intersect:false } },
    scales: {
        x:{ grid:{ color:grid }, ticks:{ maxTicksLimit:10 } },
        y:{ grid:{ color:grid }, beginAtZero:true }
    }
};

// Daily Revenue
const dr = @json($dailyRevenue);
new Chart(document.getElementById('dailyRevenueChart'),{
    type:'line',
    data:{
        labels: dr.map(d=>d.date.slice(5)),
        datasets:[{ label:'Revenue ($)', data:dr.map(d=>d.revenue),
            borderColor:brand, backgroundColor:brandBg,
            borderWidth:2.5, fill:true, tension:.4, pointRadius:2 }]
    },
    options:{...base}
});

// User Growth
const ug = @json($userGrowth);
new Chart(document.getElementById('userGrowthChart'),{
    type:'bar',
    data:{
        labels: ug.map(d=>d.date.slice(5)),
        datasets:[{ label:'New Users', data:ug.map(d=>d.new_users),
            backgroundColor:brandBg, borderColor:brand,
            borderWidth:1.5, borderRadius:5 }]
    },
    options:{...base}
});

// Monthly Revenue
const mr = @json($monthlyRevenue);
new Chart(document.getElementById('monthlyRevenueChart'),{
    type:'bar',
    data:{
        labels: mr.map(d=>d.month),
        datasets:[
            { label:'Revenue ($)', data:mr.map(d=>d.revenue),
              backgroundColor:brand, borderRadius:6, yAxisID:'y' },
            { label:'Orders', data:mr.map(d=>d.orders),
              type:'line', borderColor:blue, backgroundColor:blueBg,
              borderWidth:2, fill:false, tension:.4, pointRadius:3, yAxisID:'y1' }
        ]
    },
    options:{
        ...base,
        plugins:{ legend:{ display:true, position:'top' }, tooltip:{ mode:'index', intersect:false } },
        scales:{
            x:{ grid:{ color:grid } },
            y:{ grid:{ color:grid }, beginAtZero:true, position:'left' },
            y1:{ grid:{ display:false }, beginAtZero:true, position:'right' }
        }
    }
});

// Community Posts
const ca = @json($communityActivity);
new Chart(document.getElementById('communityChart'),{
    type:'line',
    data:{
        labels: ca.map(d=>d.date.slice(5)),
        datasets:[{ label:'Posts', data:ca.map(d=>d.posts),
            borderColor:green, backgroundColor:greenBg,
            borderWidth:2, fill:true, tension:.4, pointRadius:2 }]
    },
    options:{...base}
});
</script>
@endpush
