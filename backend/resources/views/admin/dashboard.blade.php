@extends('admin.layouts.app')
@section('title', 'Dashboard Analytics')

@push('styles')
<style>
/* ═══════════════════════════════════════════════
   DASHBOARD PREMIUM STYLES
═══════════════════════════════════════════════ */

/* Hero Banner */
.dash-hero {
    background: linear-gradient(135deg, #0c0148 0%, #1a0570 40%, #2d0ea8 70%, #0c0148 100%);
    border-radius: 20px;
    padding: 28px 32px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}
.dash-hero::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 260px; height: 260px;
    border-radius: 50%;
    background: rgba(255,138,0,0.12);
}
.dash-hero::after {
    content: '';
    position: absolute;
    bottom: -80px; left: 30%;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: rgba(255,255,255,0.04);
}
.dash-hero-title {
    font-size: 24px;
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
    position: relative;
    z-index: 1;
}
.dash-hero-sub {
    font-size: 13px;
    color: rgba(255,255,255,0.65);
    margin-top: 4px;
    position: relative;
    z-index: 1;
}
.dash-hero-right {
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    z-index: 1;
    flex-shrink: 0;
}
.dash-live-badge {
    display: flex;
    align-items: center;
    gap: 7px;
    background: rgba(16,185,129,0.2);
    border: 1.5px solid rgba(16,185,129,0.4);
    border-radius: 30px;
    padding: 6px 14px;
    font-size: 12px;
    color: #4ade80;
    font-weight: 700;
}
.dash-live-dot {
    width: 7px;
    height: 7px;
    background: #4ade80;
    border-radius: 50%;
    animation: pulse-green 1.5s infinite;
}
@keyframes pulse-green {
    0%,100% { opacity:1; transform:scale(1); }
    50%      { opacity:.5; transform:scale(1.4); }
}
.dash-clock {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 10px;
    padding: 8px 16px;
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    font-variant-numeric: tabular-nums;
    letter-spacing: .5px;
}
.dash-date {
    font-size: 11px;
    color: rgba(255,255,255,0.55);
    font-weight: 500;
}

/* ── Hero Metric Mini Cards (inside hero) ── */
.dash-hero-metrics {
    display: flex;
    gap: 10px;
    position: relative;
    z-index: 1;
}
.dash-hero-metric {
    background: rgba(255,255,255,0.09);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 12px;
    padding: 12px 18px;
    text-align: center;
    min-width: 90px;
}
.dash-hero-metric-val {
    font-size: 22px;
    font-weight: 800;
    color: #fff;
    line-height: 1;
}
.dash-hero-metric-label {
    font-size: 10px;
    color: rgba(255,255,255,0.55);
    margin-top: 4px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
}

/* ── KPI Grid ── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}
.kpi-card {
    background: #fff;
    border-radius: 16px;
    border: 1.5px solid var(--border);
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: transform .2s, box-shadow .2s;
    position: relative;
    overflow: hidden;
    cursor: default;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.09);
}
.kpi-card::after {
    content: '';
    position: absolute;
    top: 0; right: 0;
    width: 80px; height: 80px;
    border-radius: 50%;
    transform: translate(30%, -30%);
    opacity: .08;
}
.kpi-card.orange::after { background: #FF8A00; }
.kpi-card.green::after  { background: #10b981; }
.kpi-card.blue::after   { background: #3b82f6; }
.kpi-card.purple::after { background: #8b5cf6; }
.kpi-card.red::after    { background: #ef4444; }
.kpi-card.teal::after   { background: #14b8a6; }
.kpi-card.indigo::after { background: #6366f1; }
.kpi-card.pink::after   { background: #ec4899; }

.kpi-icon {
    width: 48px; height: 48px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.kpi-icon.orange { background: rgba(255,138,0,0.12); color: #FF8A00; }
.kpi-icon.green  { background: rgba(16,185,129,0.12); color: #10b981; }
.kpi-icon.blue   { background: rgba(59,130,246,0.12); color: #3b82f6; }
.kpi-icon.purple { background: rgba(139,92,246,0.12); color: #8b5cf6; }
.kpi-icon.red    { background: rgba(239,68,68,0.12); color: #ef4444; }
.kpi-icon.teal   { background: rgba(20,184,166,0.12); color: #14b8a6; }
.kpi-icon.indigo { background: rgba(99,102,241,0.12); color: #6366f1; }
.kpi-icon.pink   { background: rgba(236,72,153,0.12); color: #ec4899; }

.kpi-body { flex: 1; min-width: 0; }
.kpi-val  { font-size: 22px; font-weight: 800; color: var(--navy); line-height: 1.1; }
.kpi-label { font-size: 11.5px; color: var(--text-muted); font-weight: 600; margin-top: 2px; }
.kpi-sub   { font-size: 11px; margin-top: 5px; display: flex; align-items: center; gap: 4px; }
.kpi-sub.up     { color: #10b981; }
.kpi-sub.warn   { color: #f59e0b; }
.kpi-sub.muted  { color: var(--text-muted); }
.kpi-sub.down   { color: #ef4444; }

/* ── Chart Section ── */
.chart-section {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 16px;
    margin-bottom: 20px;
}
.dash-card {
    background: #fff;
    border-radius: 16px;
    border: 1.5px solid var(--border);
    overflow: hidden;
}
.dash-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1.5px solid var(--border);
}
.dash-card-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 700;
    color: var(--navy);
}
.dash-card-icon {
    width: 32px; height: 32px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}
.dash-card-body { padding: 18px 20px; }

/* ── Module Cards ── */
.module-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}
.module-card {
    border-radius: 16px;
    padding: 20px;
    position: relative;
    overflow: hidden;
    transition: transform .2s;
}
.module-card:hover { transform: translateY(-3px); }
.module-card.efood  { background: linear-gradient(135deg, #ff6b35, #ff8a00); }
.module-card.eshop  { background: linear-gradient(135deg, #667eea, #764ba2); }
.module-card.erent  { background: linear-gradient(135deg, #11998e, #38ef7d); }
.module-card.comm   { background: linear-gradient(135deg, #f093fb, #f5576c); }
.module-card-icon {
    width: 44px; height: 44px;
    background: rgba(255,255,255,0.25);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    color: #fff;
    margin-bottom: 14px;
}
.module-card-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgba(255,255,255,0.8);
}
.module-card-val {
    font-size: 28px;
    font-weight: 800;
    color: #fff;
    line-height: 1.1;
    margin-top: 2px;
}
.module-card-sub {
    font-size: 11px;
    color: rgba(255,255,255,0.7);
    margin-top: 6px;
}
.module-card-orders {
    position: absolute;
    top: 16px; right: 16px;
    background: rgba(255,255,255,0.2);
    border-radius: 20px;
    padding: 3px 10px;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
}

/* ── Bottom Section ── */
.bottom-section {
    display: grid;
    grid-template-columns: 1fr 1fr 320px;
    gap: 16px;
    margin-bottom: 20px;
}

/* ── Community Stats ── */
.comm-stat-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 11px 0;
    border-bottom: 1px solid var(--border);
}
.comm-stat-row:last-child { border-bottom: none; }
.comm-stat-label {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
}
.comm-stat-icon {
    width: 28px; height: 28px;
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px;
}
.comm-stat-val {
    font-size: 16px;
    font-weight: 800;
    color: var(--navy);
}

/* ── Top Vendors ── */
.vendor-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}
.vendor-row:last-child { border-bottom: none; }
.vendor-rank {
    width: 22px;
    font-size: 12px;
    font-weight: 800;
    color: var(--text-muted);
    text-align: center;
    flex-shrink: 0;
}
.vendor-rank.gold   { color: #f59e0b; }
.vendor-rank.silver { color: #9ca3af; }
.vendor-rank.bronze { color: #b45309; }
.vendor-avatar-sm {
    width: 36px; height: 36px;
    border-radius: 10px;
    object-fit: cover;
    background: var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700;
    color: var(--text-muted);
    flex-shrink: 0;
    overflow: hidden;
}
.vendor-info { flex: 1; min-width: 0; }
.vendor-name {
    font-size: 13px;
    font-weight: 700;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.vendor-orders { font-size: 11px; color: var(--text-muted); margin-top: 1px; }
.vendor-rev {
    font-size: 13px;
    font-weight: 800;
    color: var(--navy);
    flex-shrink: 0;
}

/* ── System Health ── */
.health-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}
.health-row:last-child { border-bottom: none; }
.health-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.health-dot.green  { background: #10b981; box-shadow: 0 0 6px rgba(16,185,129,.5); }
.health-dot.yellow { background: #f59e0b; box-shadow: 0 0 6px rgba(245,158,11,.5); }
.health-dot.red    { background: #ef4444; box-shadow: 0 0 6px rgba(239,68,68,.5); }
.health-label  { flex: 1; font-size: 12.5px; font-weight: 600; color: var(--text); }
.health-val    { font-size: 12.5px; font-weight: 700; color: var(--navy); }

/* ── Recent Orders ── */
.orders-section { margin-bottom: 20px; }
.status-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 5px;
}

/* ── Donut center label ── */
.donut-wrapper { position: relative; }
.donut-center {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    pointer-events: none;
}
.donut-center-val  { font-size: 22px; font-weight: 800; color: var(--navy); }
.donut-center-label { font-size: 10px; color: var(--text-muted); font-weight: 600; }

/* ── Responsive ── */
@media (max-width: 1300px) {
    .kpi-grid      { grid-template-columns: repeat(4,1fr); }
    .bottom-section{ grid-template-columns: 1fr 1fr; }
    .bottom-section > *:last-child { grid-column: 1 / -1; }
}
@media (max-width: 900px) {
    .kpi-grid      { grid-template-columns: repeat(2,1fr); }
    .module-grid   { grid-template-columns: repeat(2,1fr); }
    .chart-section { grid-template-columns: 1fr; }
    .bottom-section{ grid-template-columns: 1fr; }
    .dash-hero     { flex-direction: column; align-items: flex-start; }
    .dash-hero-metrics { flex-wrap: wrap; }
}
</style>
@endpush

@section('content')

{{-- ═══════════════════════════════════════════════════════
     HERO BANNER
═══════════════════════════════════════════════════════ --}}
<div class="dash-hero">
    <div style="position:relative;z-index:1;">
        <div class="dash-hero-title">Analytics Dashboard</div>
        <div class="dash-hero-sub">Full system overview — real-time data across all modules</div>
        <div style="display:flex;align-items:center;gap:10px;margin-top:14px;">
            <div class="dash-live-badge"><span class="dash-live-dot"></span> Live Data</div>
            <div style="font-size:12px;color:rgba(255,255,255,0.5);">{{ now()->format('l, d M Y') }}</div>
        </div>
    </div>

    <div class="dash-hero-metrics">
        <div class="dash-hero-metric">
            <div class="dash-hero-metric-val">{{ number_format($totalUsers) }}</div>
            <div class="dash-hero-metric-label">Users</div>
        </div>
        <div class="dash-hero-metric">
            <div class="dash-hero-metric-val">{{ number_format($totalOrders) }}</div>
            <div class="dash-hero-metric-label">Orders</div>
        </div>
        <div class="dash-hero-metric">
            <div class="dash-hero-metric-val">${{ number_format($totalRevenue,0) }}</div>
            <div class="dash-hero-metric-label">Revenue</div>
        </div>
        <div class="dash-hero-metric">
            <div class="dash-hero-metric-val">{{ number_format($totalVendors) }}</div>
            <div class="dash-hero-metric-label">Vendors</div>
        </div>
    </div>

    <div class="dash-hero-right">
        <div style="text-align:right;">
            <div class="dash-clock" id="liveClock">--:--:--</div>
            <div class="dash-date" style="text-align:center;margin-top:4px;">{{ now()->format('M Y') }}</div>
        </div>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-primary" style="white-space:nowrap;">
            <i class="fas fa-plus"></i> New Order
        </a>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     ROW 1 — 8 KPI CARDS
═══════════════════════════════════════════════════════ --}}
<div class="kpi-grid">
    {{-- Users --}}
    <div class="kpi-card orange">
        <div class="kpi-icon orange"><i class="fas fa-users"></i></div>
        <div class="kpi-body">
            <div class="kpi-val">{{ number_format($totalUsers) }}</div>
            <div class="kpi-label">Total Users</div>
            <div class="kpi-sub up"><i class="fas fa-arrow-up"></i> +{{ $todayUsers }} today</div>
        </div>
    </div>

    {{-- Orders --}}
    <div class="kpi-card green">
        <div class="kpi-icon green"><i class="fas fa-shopping-bag"></i></div>
        <div class="kpi-body">
            <div class="kpi-val">{{ number_format($totalOrders) }}</div>
            <div class="kpi-label">Total Orders</div>
            <div class="kpi-sub warn"><i class="fas fa-clock"></i> {{ $pendingOrders }} pending</div>
        </div>
    </div>

    {{-- Revenue --}}
    <div class="kpi-card blue">
        <div class="kpi-icon blue"><i class="fas fa-dollar-sign"></i></div>
        <div class="kpi-body">
            <div class="kpi-val">${{ number_format($totalRevenue,0) }}</div>
            <div class="kpi-label">Total Revenue</div>
            <div class="kpi-sub muted"><i class="fas fa-calendar-day"></i> ${{ number_format($todayRevenue,2) }} today</div>
        </div>
    </div>

    {{-- Commission --}}
    <div class="kpi-card red">
        <div class="kpi-icon red"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="kpi-body">
            <div class="kpi-val">${{ number_format($totalCommission,0) }}</div>
            <div class="kpi-label">Commission</div>
            <div class="kpi-sub muted"><i class="fas fa-calendar-week"></i> ${{ number_format($monthlyCommission,0) }} this month</div>
        </div>
    </div>

    {{-- Vendors --}}
    <div class="kpi-card purple">
        <div class="kpi-icon purple"><i class="fas fa-store"></i></div>
        <div class="kpi-body">
            <div class="kpi-val">{{ $totalVendors }}</div>
            <div class="kpi-label">Vendors</div>
            <div class="kpi-sub warn"><i class="fas fa-hourglass-half"></i> {{ $pendingVendors }} awaiting</div>
        </div>
    </div>

    {{-- Deliverymen --}}
    <div class="kpi-card teal">
        <div class="kpi-icon teal"><i class="fas fa-motorcycle"></i></div>
        <div class="kpi-body">
            <div class="kpi-val">{{ $totalDelivery }}</div>
            <div class="kpi-label">Deliverymen</div>
            <div class="kpi-sub up"><i class="fas fa-circle" style="font-size:7px;"></i> {{ $availableDelivery }} online</div>
        </div>
    </div>

    {{-- Wallet --}}
    <div class="kpi-card indigo">
        <div class="kpi-icon indigo"><i class="fas fa-wallet"></i></div>
        <div class="kpi-body">
            <div class="kpi-val">${{ number_format($totalWalletBalance,0) }}</div>
            <div class="kpi-label">Wallet Balance</div>
            <div class="kpi-sub muted"><i class="fas fa-users"></i> {{ $walletCount }} active wallets</div>
        </div>
    </div>

    {{-- Products --}}
    <div class="kpi-card pink">
        <div class="kpi-icon pink"><i class="fas fa-box-open"></i></div>
        <div class="kpi-body">
            <div class="kpi-val">{{ number_format($totalProducts) }}</div>
            <div class="kpi-label">Products</div>
            <div class="kpi-sub up"><i class="fas fa-check-circle"></i> {{ $activeProducts }} active</div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     ROW 2 — MODULE PERFORMANCE CARDS
═══════════════════════════════════════════════════════ --}}
@php
$mRevMap = $moduleRevenue->keyBy('module');
$foodRev = $mRevMap->get('efood') ?? (object)['revenue'=>0,'count'=>0];
$shopRev = $mRevMap->get('eshop') ?? (object)['revenue'=>0,'count'=>0];
$rentRev = $mRevMap->get('erent') ?? (object)['revenue'=>0,'count'=>0];
@endphp
<div class="module-grid">
    {{-- eFood --}}
    <div class="module-card efood">
        <div class="module-card-orders">{{ number_format($foodRev->count ?? 0) }} orders</div>
        <div class="module-card-icon"><i class="fas fa-utensils"></i></div>
        <div class="module-card-title">eFood</div>
        <div class="module-card-val">${{ number_format($foodRev->revenue ?? 0, 0) }}</div>
        <div class="module-card-sub">Revenue from food delivery</div>
    </div>

    {{-- eShop --}}
    <div class="module-card eshop">
        <div class="module-card-orders">{{ number_format($shopRev->count ?? 0) }} orders</div>
        <div class="module-card-icon"><i class="fas fa-shopping-cart"></i></div>
        <div class="module-card-title">eShop</div>
        <div class="module-card-val">${{ number_format($shopRev->revenue ?? 0, 0) }}</div>
        <div class="module-card-sub">Revenue from online store</div>
    </div>

    {{-- eRent --}}
    <div class="module-card erent">
        <div class="module-card-orders">{{ $communityMembers }} members</div>
        <div class="module-card-icon"><i class="fas fa-home"></i></div>
        <div class="module-card-title">eRent</div>
        <div class="module-card-val">${{ number_format($rentRev->revenue ?? 0, 0) }}</div>
        <div class="module-card-sub">Revenue from rentals</div>
    </div>

    {{-- Community --}}
    <div class="module-card comm">
        <div class="module-card-orders">{{ $communityPosts }} posts</div>
        <div class="module-card-icon"><i class="fas fa-globe"></i></div>
        <div class="module-card-title">Community</div>
        <div class="module-card-val">{{ number_format($communityMembers) }}</div>
        <div class="module-card-sub">Active community members</div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     ROW 3 — CHARTS
═══════════════════════════════════════════════════════ --}}
<div class="chart-section">

    {{-- Main Revenue + Orders Chart --}}
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <div class="dash-card-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);"><i class="fas fa-chart-area"></i></div>
                Revenue & Orders — Last 30 Days
            </div>
            <div style="display:flex;gap:14px;font-size:11.5px;color:var(--text-muted);">
                <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:3px;background:#FF8A00;border-radius:2px;display:inline-block;"></span> Orders</span>
                <span style="display:flex;align-items:center;gap:5px;"><span style="width:14px;height:3px;background:#3b82f6;border-radius:2px;display:inline-block;"></span> Revenue</span>
            </div>
        </div>
        <div class="dash-card-body">
            <canvas id="mainChart" height="160"></canvas>
        </div>
    </div>

    {{-- Order Status Donut --}}
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <div class="dash-card-icon" style="background:rgba(139,92,246,0.1);color:#8b5cf6;"><i class="fas fa-chart-pie"></i></div>
                Order Status
            </div>
        </div>
        <div class="dash-card-body">
            <div class="donut-wrapper" style="position:relative;max-width:200px;margin:0 auto;">
                <canvas id="statusDonut" height="200"></canvas>
                <div class="donut-center">
                    <div class="donut-center-val">{{ number_format($totalOrders) }}</div>
                    <div class="donut-center-label">TOTAL</div>
                </div>
            </div>
            <div style="margin-top:16px;display:flex;flex-direction:column;gap:7px;">
                @php
                $statusColors = ['pending'=>'#f59e0b','confirmed'=>'#3b82f6','preparing'=>'#6366f1','ready'=>'#14b8a6','picked_up'=>'#8b5cf6','delivered'=>'#10b981','cancelled'=>'#ef4444','failed'=>'#f43f5e'];
                @endphp
                @foreach($orderStatusBreakdown as $status => $count)
                <div style="display:flex;align-items:center;justify-content:space-between;font-size:12px;">
                    <span style="display:flex;align-items:center;gap:7px;">
                        <span style="width:10px;height:10px;border-radius:3px;background:{{ $statusColors[$status] ?? '#94a3b8' }};display:inline-block;flex-shrink:0;"></span>
                        <span style="font-weight:600;color:var(--text);text-transform:capitalize;">{{ str_replace('_',' ',$status) }}</span>
                    </span>
                    <span style="font-weight:700;color:var(--navy);">{{ number_format($count) }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     ROW 4 — MONTHLY CHART + TOP VENDORS + SYSTEM HEALTH
═══════════════════════════════════════════════════════ --}}
<div class="bottom-section">

    {{-- Monthly Revenue Chart (last 6 months) --}}
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <div class="dash-card-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="fas fa-chart-bar"></i></div>
                Monthly Revenue — Last 6 Months
            </div>
        </div>
        <div class="dash-card-body">
            <canvas id="monthlyChart" height="180"></canvas>
        </div>
    </div>

    {{-- Top Vendors --}}
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <div class="dash-card-icon" style="background:rgba(245,158,11,0.1);color:#f59e0b;"><i class="fas fa-trophy"></i></div>
                Top Vendors
            </div>
            <a href="{{ route('admin.vendors.index') }}" style="font-size:11px;color:var(--brand);font-weight:600;">View all</a>
        </div>
        <div class="dash-card-body">
            @forelse($topVendors as $i => $vendor)
            @php
            $rankClass = $i===0?'gold':($i===1?'silver':($i===2?'bronze':''));
            $initial   = strtoupper(substr($vendor->name,0,1));
            @endphp
            <div class="vendor-row">
                <div class="vendor-rank {{ $rankClass }}">
                    @if($i<3) <i class="fas fa-medal"></i> @else {{ $i+1 }} @endif
                </div>
                <div class="vendor-avatar-sm">{{ $initial }}</div>
                <div class="vendor-info">
                    <div class="vendor-name">{{ $vendor->name }}</div>
                    <div class="vendor-orders">{{ number_format($vendor->order_count) }} orders</div>
                </div>
                <div class="vendor-rev">${{ number_format($vendor->total_revenue,0) }}</div>
            </div>
            @empty
            <div style="text-align:center;color:var(--text-muted);font-size:13px;padding:20px 0;"><i class="fas fa-store" style="font-size:24px;opacity:.3;display:block;margin-bottom:8px;"></i>No vendors yet</div>
            @endforelse
        </div>
    </div>

    {{-- System Health + Quick Stats --}}
    <div style="display:flex;flex-direction:column;gap:14px;">

        {{-- Community Stats --}}
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <div class="dash-card-icon" style="background:rgba(236,72,153,0.1);color:#ec4899;"><i class="fas fa-globe"></i></div>
                    Community
                </div>
                @if($communityReports > 0)
                <span class="badge badge-danger" style="font-size:10px;">{{ $communityReports }} reports</span>
                @endif
            </div>
            <div class="dash-card-body" style="padding:12px 18px;">
                <div class="comm-stat-row">
                    <div class="comm-stat-label"><div class="comm-stat-icon" style="background:rgba(99,102,241,0.1);color:#6366f1;"><i class="fas fa-edit"></i></div>Posts</div>
                    <div class="comm-stat-val">{{ number_format($communityPosts) }}</div>
                </div>
                <div class="comm-stat-row">
                    <div class="comm-stat-label"><div class="comm-stat-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="fas fa-users"></i></div>Members</div>
                    <div class="comm-stat-val">{{ number_format($communityMembers) }}</div>
                </div>
                <div class="comm-stat-row">
                    <div class="comm-stat-label"><div class="comm-stat-icon" style="background:rgba(245,158,11,0.1);color:#f59e0b;"><i class="fas fa-layer-group"></i></div>Groups</div>
                    <div class="comm-stat-val">{{ number_format($communityGroups) }}</div>
                </div>
                <div class="comm-stat-row">
                    <div class="comm-stat-label"><div class="comm-stat-icon" style="background:rgba(139,92,246,0.1);color:#8b5cf6;"><i class="fas fa-circle-notch"></i></div>Stories</div>
                    <div class="comm-stat-val">{{ number_format($communityStories) }}</div>
                </div>
                <div class="comm-stat-row">
                    <div class="comm-stat-label"><div class="comm-stat-icon" style="background:rgba(20,184,166,0.1);color:#14b8a6;"><i class="fas fa-comment"></i></div>Comments</div>
                    <div class="comm-stat-val">{{ number_format($communityComments) }}</div>
                </div>
                <div class="comm-stat-row">
                    <div class="comm-stat-label"><div class="comm-stat-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);"><i class="fas fa-heart"></i></div>Follows</div>
                    <div class="comm-stat-val">{{ number_format($communityFollows) }}</div>
                </div>
            </div>
        </div>

        {{-- System Health --}}
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <div class="dash-card-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="fas fa-heartbeat"></i></div>
                    System Health
                </div>
            </div>
            <div class="dash-card-body" style="padding:12px 18px;">
                <div class="health-row">
                    <div class="health-dot {{ $pendingOrders > 50 ? 'yellow' : 'green' }}"></div>
                    <div class="health-label">Pending Orders</div>
                    <div class="health-val">{{ $pendingOrders }}</div>
                </div>
                <div class="health-row">
                    <div class="health-dot {{ $pendingWithdrawals > 10 ? 'yellow' : 'green' }}"></div>
                    <div class="health-label">Pending Withdrawals</div>
                    <div class="health-val">{{ $pendingWithdrawals }}</div>
                </div>
                <div class="health-row">
                    <div class="health-dot {{ $pendingVendors > 0 ? 'yellow' : 'green' }}"></div>
                    <div class="health-label">Vendor Approvals</div>
                    <div class="health-val">{{ $pendingVendors }}</div>
                </div>
                <div class="health-row">
                    <div class="health-dot {{ $communityReports > 0 ? 'red' : 'green' }}"></div>
                    <div class="health-label">Community Reports</div>
                    <div class="health-val">{{ $communityReports }}</div>
                </div>
                <div class="health-row">
                    <div class="health-dot green"></div>
                    <div class="health-label">Delivery Online</div>
                    <div class="health-val">{{ $availableDelivery }}/{{ $totalDelivery }}</div>
                </div>
                <div class="health-row">
                    <div class="health-dot green"></div>
                    <div class="health-label">Notifications Sent</div>
                    <div class="health-val">{{ number_format($sentNotifications) }}</div>
                </div>
                <div class="health-row">
                    <div class="health-dot green"></div>
                    <div class="health-label">Avg Rating</div>
                    <div class="health-val" style="color:#f59e0b;"><i class="fas fa-star" style="font-size:11px;"></i> {{ $avgRating }}/5</div>
                </div>
                <div class="health-row">
                    <div class="health-dot green"></div>
                    <div class="health-label">Coupon Usages</div>
                    <div class="health-val">{{ number_format($couponUsages) }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     ROW 5 — RECENT ORDERS TABLE
═══════════════════════════════════════════════════════ --}}
<div class="dash-card orders-section">
    <div class="dash-card-header">
        <div class="dash-card-title">
            <div class="dash-card-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);"><i class="fas fa-receipt"></i></div>
            Recent Orders
        </div>
        <div style="display:flex;gap:10px;align-items:center;">
            <span style="font-size:12px;color:var(--text-muted);">{{ $todayOrders }} orders today</span>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline btn-sm">View All <i class="fas fa-arrow-right"></i></a>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Module</th>
                    <th>Amount</th>
                    <th>Commission</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentOrders as $order)
                @php
                $statusMap = [
                    'pending'   => ['#f59e0b','clock','Pending'],
                    'confirmed' => ['#3b82f6','check','Confirmed'],
                    'preparing' => ['#6366f1','fire','Preparing'],
                    'ready'     => ['#14b8a6','box','Ready'],
                    'picked_up' => ['#8b5cf6','motorcycle','Picked Up'],
                    'delivered' => ['#10b981','check-circle','Delivered'],
                    'cancelled' => ['#ef4444','times-circle','Cancelled'],
                    'failed'    => ['#f43f5e','exclamation-circle','Failed'],
                ];
                [$sColor, $sIcon, $sLabel] = $statusMap[$order->status] ?? ['#94a3b8','circle','Unknown'];
                @endphp
                <tr>
                    <td><span style="font-size:13px;font-weight:700;color:var(--navy);">{{ $order->order_number }}</span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:9px;">
                            <div class="avatar avatar-sm avatar-orange">{{ strtoupper(substr($order->user?->name ?? 'U',0,1)) }}</div>
                            <span style="font-weight:600;font-size:13px;">{{ $order->user?->name ?? 'Guest' }}</span>
                        </div>
                    </td>
                    <td>
                        @if($order->module_slug)
                        <span class="badge badge-dark" style="text-transform:uppercase;font-size:10px;">{{ $order->module_slug }}</span>
                        @else <span class="text-muted text-xs">—</span> @endif
                    </td>
                    <td><span style="font-weight:700;color:var(--text);">${{ number_format($order->total_amount,2) }}</span></td>
                    <td><span style="font-size:12px;color:#10b981;font-weight:600;">${{ number_format($order->commission ?? 0,2) }}</span></td>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;color:{{ $sColor }};">
                            <span class="status-dot" style="background:{{ $sColor }};"></span>
                            {{ $sLabel }}
                        </span>
                    </td>
                    <td style="color:var(--text-muted);font-size:12px;">{{ \Carbon\Carbon::parse($order->created_at)->diffForHumans() }}</td>
                    <td>
                        <a href="{{ route('admin.orders.show',$order) }}" class="btn btn-ghost btn-xs">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8"><div class="empty-state" style="padding:30px;"><i class="fas fa-inbox"></i><p>No orders yet</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     ROW 6 — QUICK ACTIONS + THIS MONTH SUMMARY
═══════════════════════════════════════════════════════ --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">

    {{-- Quick Actions --}}
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <div class="dash-card-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);"><i class="fas fa-bolt"></i></div>
                Quick Actions
            </div>
        </div>
        <div class="dash-card-body" style="display:flex;flex-direction:column;gap:8px;">
            <a href="{{ route('admin.orders.index') }}?status=pending"
               style="display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;background:rgba(245,158,11,0.06);border:1.5px solid rgba(245,158,11,0.2);text-decoration:none;transition:.15s;"
               onmouseover="this.style.background='rgba(245,158,11,0.12)'" onmouseout="this.style.background='rgba(245,158,11,0.06)'">
                <div style="width:36px;height:36px;border-radius:9px;background:rgba(245,158,11,0.15);color:#f59e0b;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-clock"></i></div>
                <div style="flex:1;"><div style="font-size:13px;font-weight:700;color:var(--text);">Pending Orders</div><div style="font-size:11px;color:var(--text-muted);">{{ $pendingOrders }} waiting</div></div>
                <span style="background:#f59e0b;color:#fff;border-radius:20px;padding:2px 9px;font-size:11px;font-weight:700;">{{ $pendingOrders }}</span>
            </a>
            <a href="{{ route('admin.vendors.index') }}?approved=0"
               style="display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;background:rgba(139,92,246,0.05);border:1.5px solid rgba(139,92,246,0.15);text-decoration:none;transition:.15s;"
               onmouseover="this.style.background='rgba(139,92,246,0.1)'" onmouseout="this.style.background='rgba(139,92,246,0.05)'">
                <div style="width:36px;height:36px;border-radius:9px;background:rgba(139,92,246,0.15);color:#8b5cf6;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-store"></i></div>
                <div style="flex:1;"><div style="font-size:13px;font-weight:700;color:var(--text);">Vendor Approvals</div><div style="font-size:11px;color:var(--text-muted);">{{ $pendingVendors }} pending</div></div>
                <span style="background:#8b5cf6;color:#fff;border-radius:20px;padding:2px 9px;font-size:11px;font-weight:700;">{{ $pendingVendors }}</span>
            </a>
            <a href="{{ route('admin.finance.index') }}"
               style="display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;background:rgba(59,130,246,0.05);border:1.5px solid rgba(59,130,246,0.15);text-decoration:none;transition:.15s;"
               onmouseover="this.style.background='rgba(59,130,246,0.1)'" onmouseout="this.style.background='rgba(59,130,246,0.05)'">
                <div style="width:36px;height:36px;border-radius:9px;background:rgba(59,130,246,0.15);color:#3b82f6;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-money-bill-wave"></i></div>
                <div style="flex:1;"><div style="font-size:13px;font-weight:700;color:var(--text);">Withdrawals</div><div style="font-size:11px;color:var(--text-muted);">{{ $pendingWithdrawals }} pending</div></div>
                <span style="background:#3b82f6;color:#fff;border-radius:20px;padding:2px 9px;font-size:11px;font-weight:700;">{{ $pendingWithdrawals }}</span>
            </a>
            <a href="{{ route('admin.dispatch') }}"
               style="display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;background:rgba(16,185,129,0.05);border:1.5px solid rgba(16,185,129,0.15);text-decoration:none;transition:.15s;"
               onmouseover="this.style.background='rgba(16,185,129,0.1)'" onmouseout="this.style.background='rgba(16,185,129,0.05)'">
                <div style="width:36px;height:36px;border-radius:9px;background:rgba(16,185,129,0.15);color:#10b981;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-map-marked-alt"></i></div>
                <div style="flex:1;"><div style="font-size:13px;font-weight:700;color:var(--text);">Dispatch Center</div><div style="font-size:11px;color:var(--text-muted);">{{ $availableDelivery }} riders online</div></div>
                <i class="fas fa-arrow-right" style="color:var(--text-muted);font-size:11px;"></i>
            </a>
            <a href="{{ route('admin.notifications.index') }}"
               style="display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:10px;background:rgba(99,102,241,0.05);border:1.5px solid rgba(99,102,241,0.15);text-decoration:none;transition:.15s;"
               onmouseover="this.style.background='rgba(99,102,241,0.1)'" onmouseout="this.style.background='rgba(99,102,241,0.05)'">
                <div style="width:36px;height:36px;border-radius:9px;background:rgba(99,102,241,0.15);color:#6366f1;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-bell"></i></div>
                <div style="flex:1;"><div style="font-size:13px;font-weight:700;color:var(--text);">Notifications</div><div style="font-size:11px;color:var(--text-muted);">{{ number_format($totalNotifications) }} sent total</div></div>
                <i class="fas fa-arrow-right" style="color:var(--text-muted);font-size:11px;"></i>
            </a>
        </div>
    </div>

    {{-- This Month Summary --}}
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <div class="dash-card-icon" style="background:rgba(59,130,246,0.1);color:#3b82f6;"><i class="fas fa-calendar-check"></i></div>
                This Month Summary
            </div>
            <span style="font-size:11px;color:var(--text-muted);">{{ now()->format('F Y') }}</span>
        </div>
        <div class="dash-card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div style="text-align:center;padding:16px 12px;background:rgba(255,138,0,0.05);border-radius:12px;border:1.5px solid rgba(255,138,0,0.15);">
                    <div style="font-size:26px;font-weight:800;color:#FF8A00;">{{ $monthlyUsers }}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;font-weight:600;"><i class="fas fa-user-plus" style="margin-right:3px;"></i>New Users</div>
                </div>
                <div style="text-align:center;padding:16px 12px;background:rgba(16,185,129,0.05);border-radius:12px;border:1.5px solid rgba(16,185,129,0.15);">
                    <div style="font-size:26px;font-weight:800;color:#10b981;">${{ number_format($monthlyRevenue,0) }}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;font-weight:600;"><i class="fas fa-chart-line" style="margin-right:3px;"></i>Revenue</div>
                </div>
                <div style="text-align:center;padding:16px 12px;background:rgba(59,130,246,0.05);border-radius:12px;border:1.5px solid rgba(59,130,246,0.15);">
                    <div style="font-size:26px;font-weight:800;color:#3b82f6;">{{ $todayOrders }}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;font-weight:600;"><i class="fas fa-shopping-bag" style="margin-right:3px;"></i>Today Orders</div>
                </div>
                <div style="text-align:center;padding:16px 12px;background:rgba(139,92,246,0.05);border-radius:12px;border:1.5px solid rgba(139,92,246,0.15);">
                    <div style="font-size:26px;font-weight:800;color:#8b5cf6;">${{ number_format($monthlyCommission,0) }}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;font-weight:600;"><i class="fas fa-hand-holding-usd" style="margin-right:3px;"></i>Commission</div>
                </div>
                <div style="text-align:center;padding:16px 12px;background:rgba(20,184,166,0.05);border-radius:12px;border:1.5px solid rgba(20,184,166,0.15);">
                    <div style="font-size:26px;font-weight:800;color:#14b8a6;">{{ $deliveredOrders }}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;font-weight:600;"><i class="fas fa-check-circle" style="margin-right:3px;"></i>Delivered</div>
                </div>
                <div style="text-align:center;padding:16px 12px;background:rgba(239,68,68,0.05);border-radius:12px;border:1.5px solid rgba(239,68,68,0.15);">
                    <div style="font-size:26px;font-weight:800;color:#ef4444;">{{ $cancelledOrders }}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;font-weight:600;"><i class="fas fa-times-circle" style="margin-right:3px;"></i>Cancelled</div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
// ── Live Clock ────────────────────────────────────────────
function updateClock() {
    const now = new Date();
    document.getElementById('liveClock').textContent =
        now.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
updateClock();
setInterval(updateClock, 1000);

// ── Chart.js Defaults ─────────────────────────────────────
Chart.defaults.font.family = "'Inter','Segoe UI',sans-serif";

// ── 1. Main Line Chart (Orders & Revenue) ─────────────────
(function() {
    const raw = @json($dailyOrders);
    const labels  = raw.map(d => { const dt=new Date(d.date); return dt.toLocaleDateString('en-US',{month:'short',day:'numeric'}); });
    const counts  = raw.map(d => d.count);
    const revenue = raw.map(d => parseFloat(d.revenue||0));

    const ctx = document.getElementById('mainChart').getContext('2d');
    const g1 = ctx.createLinearGradient(0,0,0,280);
    g1.addColorStop(0,'rgba(255,138,0,0.22)'); g1.addColorStop(1,'rgba(255,138,0,0)');
    const g2 = ctx.createLinearGradient(0,0,0,280);
    g2.addColorStop(0,'rgba(59,130,246,0.15)'); g2.addColorStop(1,'rgba(59,130,246,0)');

    new Chart(ctx, {
        type: 'line',
        data: { labels, datasets: [
            { label:'Orders', data:counts, borderColor:'#FF8A00', backgroundColor:g1,
              tension:.45, fill:true, borderWidth:2.5, pointRadius:2, pointHoverRadius:6,
              pointBackgroundColor:'#FF8A00', yAxisID:'y' },
            { label:'Revenue ($)', data:revenue, borderColor:'#3b82f6', backgroundColor:g2,
              tension:.45, fill:true, borderWidth:2.5, pointRadius:2, pointHoverRadius:6,
              pointBackgroundColor:'#3b82f6', yAxisID:'y1' },
        ]},
        options: {
            responsive:true,
            interaction:{ mode:'index', intersect:false },
            plugins: {
                legend:{ display:false },
                tooltip: { backgroundColor:'#0c0148', titleColor:'rgba(255,255,255,0.7)',
                    bodyColor:'#fff', padding:12, cornerRadius:10,
                    titleFont:{size:12}, bodyFont:{size:13,weight:'700'} }
            },
            scales: {
                x: { grid:{color:'#f0f2f8',drawBorder:false}, ticks:{color:'#7b7fa8',font:{size:10},maxTicksLimit:10} },
                y:  { type:'linear',position:'left',beginAtZero:true,
                      grid:{color:'#f0f2f8',drawBorder:false}, ticks:{color:'#7b7fa8',font:{size:10}} },
                y1: { type:'linear',position:'right',beginAtZero:true,
                      grid:{drawOnChartArea:false}, ticks:{color:'#3b82f6',font:{size:10},callback:v=>'$'+v} },
            },
        }
    });
})();

// ── 2. Order Status Donut ─────────────────────────────────
(function() {
    const statusData = @json($orderStatusBreakdown);
    const colorMap = { pending:'#f59e0b',confirmed:'#3b82f6',preparing:'#6366f1',
        ready:'#14b8a6',picked_up:'#8b5cf6',delivered:'#10b981',cancelled:'#ef4444',failed:'#f43f5e' };

    const labels = Object.keys(statusData);
    const data   = Object.values(statusData);
    const colors = labels.map(l => colorMap[l] || '#94a3b8');

    new Chart(document.getElementById('statusDonut'), {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor:colors, borderWidth:0, hoverOffset:6 }] },
        options: {
            cutout: '72%',
            responsive: true,
            plugins: {
                legend: { display:false },
                tooltip: { backgroundColor:'#0c0148', titleColor:'rgba(255,255,255,0.7)',
                    bodyColor:'#fff', padding:10, cornerRadius:8, bodyFont:{size:12,weight:'700'} }
            }
        }
    });
})();

// ── 3. Monthly Revenue Bar Chart ──────────────────────────
(function() {
    const raw = @json($monthlyRevenueChart);
    const labels  = raw.map(d => { const [y,m]=d.month.split('-'); return new Date(y,m-1).toLocaleDateString('en-US',{month:'short',year:'2-digit'}); });
    const revenue = raw.map(d => parseFloat(d.revenue||0));
    const orders  = raw.map(d => d.orders);

    const ctx = document.getElementById('monthlyChart').getContext('2d');
    const g = ctx.createLinearGradient(0,0,0,200);
    g.addColorStop(0,'rgba(16,185,129,0.85)'); g.addColorStop(1,'rgba(16,185,129,0.3)');

    new Chart(ctx, {
        type:'bar',
        data:{ labels, datasets:[
            { label:'Revenue', data:revenue, backgroundColor:g, borderRadius:8, borderSkipped:false, yAxisID:'y' },
            { label:'Orders', data:orders, type:'line', borderColor:'#FF8A00', backgroundColor:'transparent',
              tension:.4, borderWidth:2.5, pointRadius:4, pointBackgroundColor:'#FF8A00', yAxisID:'y1' }
        ]},
        options:{
            responsive:true,
            interaction:{ mode:'index', intersect:false },
            plugins:{
                legend:{ display:false },
                tooltip:{ backgroundColor:'#0c0148', titleColor:'rgba(255,255,255,0.7)',
                    bodyColor:'#fff', padding:10, cornerRadius:8 }
            },
            scales:{
                x:{ grid:{display:false}, ticks:{color:'#7b7fa8',font:{size:10}} },
                y:{ position:'left',beginAtZero:true, grid:{color:'#f0f2f8',drawBorder:false},
                    ticks:{color:'#10b981',font:{size:10},callback:v=>'$'+v} },
                y1:{ position:'right',beginAtZero:true, grid:{drawOnChartArea:false},
                    ticks:{color:'#FF8A00',font:{size:10}} }
            }
        }
    });
})();
</script>
@endpush
@endsection
