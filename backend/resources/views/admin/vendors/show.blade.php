@extends('admin.layouts.app')
@section('title', 'Vendor: ' . $vendor->name)
@section('content')
<style>
:root{--navy:#07003B;--brand:#FF8A00;--brand-light:rgba(255,138,0,.1);--green:#10b981;--red:#ef4444;--blue:#3b82f6;--purple:#8b5cf6;--card:#fff;--border:#e8eaf0;--text:#1a1a2e;--muted:#6b7280;--bg:#f5f6fa;}
*{box-sizing:border-box;}
.vd-wrap{padding:0 0 48px;}
/* Header */
.vd-hero{background:linear-gradient(135deg,#07003B 0%,#1a0066 60%,#07003B 100%);border-radius:16px;padding:28px 32px;margin-bottom:24px;display:flex;align-items:center;gap:24px;position:relative;overflow:hidden;}
.vd-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");}
.vd-avatar{width:80px;height:80px;border-radius:16px;object-fit:cover;border:3px solid rgba(255,255,255,.2);flex-shrink:0;}
.vd-avatar-placeholder{width:80px;height:80px;border-radius:16px;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;color:rgba(255,255,255,.7);flex-shrink:0;border:3px solid rgba(255,255,255,.15);}
.vd-hero-info{flex:1;min-width:0;}
.vd-hero-name{font-size:26px;font-weight:800;color:#fff;margin:0 0 6px;letter-spacing:-.3px;}
.vd-hero-meta{display:flex;flex-wrap:wrap;gap:10px;align-items:center;}
.vd-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:11.5px;font-weight:700;letter-spacing:.3px;}
.vd-badge-module{background:rgba(255,138,0,.2);color:#FF8A00;border:1px solid rgba(255,138,0,.3);}
.vd-badge-active{background:rgba(16,185,129,.2);color:#6ee7b7;border:1px solid rgba(16,185,129,.25);}
.vd-badge-inactive{background:rgba(239,68,68,.2);color:#fca5a5;border:1px solid rgba(239,68,68,.25);}
.vd-badge-approved{background:rgba(59,130,246,.2);color:#93c5fd;border:1px solid rgba(59,130,246,.25);}
.vd-badge-pending{background:rgba(245,158,11,.2);color:#fcd34d;border:1px solid rgba(245,158,11,.25);}
.vd-hero-rating{color:#fbbf24;font-size:13px;font-weight:700;}
.vd-hero-actions{display:flex;flex-direction:column;gap:8px;flex-shrink:0;}
.vd-btn{padding:8px 18px;border-radius:9px;border:none;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px;text-decoration:none;transition:all .15s;}
.vd-btn-primary{background:#FF8A00;color:#fff;}
.vd-btn-primary:hover{background:#e07500;color:#fff;}
.vd-btn-ghost{background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.2);}
.vd-btn-ghost:hover{background:rgba(255,255,255,.2);color:#fff;}
.vd-btn-danger{background:rgba(239,68,68,.15);color:#fca5a5;border:1px solid rgba(239,68,68,.25);}
.vd-btn-danger:hover{background:rgba(239,68,68,.3);color:#fff;}
.vd-btn-success{background:rgba(16,185,129,.15);color:#6ee7b7;border:1px solid rgba(16,185,129,.25);}
.vd-btn-success:hover{background:rgba(16,185,129,.3);color:#fff;}
/* KPI row */
.vd-kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;}
.vd-kpi{background:#fff;border-radius:14px;border:1px solid var(--border);padding:18px 20px;display:flex;align-items:center;gap:14px;}
.vd-kpi-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.vd-kpi-label{font-size:11.5px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;}
.vd-kpi-value{font-size:22px;font-weight:800;color:var(--text);letter-spacing:-.5px;line-height:1;}
.vd-kpi-sub{font-size:11px;color:var(--muted);margin-top:3px;}
.vd-kpi-trend{font-size:11.5px;font-weight:700;margin-top:3px;}
.trend-up{color:var(--green);}
.trend-down{color:var(--red);}
/* Grid layouts */
.vd-grid-3-1{display:grid;grid-template-columns:1fr 340px;gap:16px;margin-bottom:16px;align-items:start;}
.vd-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;align-items:start;}
/* Cards */
.vd-card{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;}
.vd-card-head{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.vd-card-title{font-size:14px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px;}
.vd-card-body{padding:20px;}
/* Info table */
.vd-info-table{width:100%;border-collapse:collapse;}
.vd-info-table tr td{padding:10px 0;border-bottom:1px solid var(--border);vertical-align:top;}
.vd-info-table tr:last-child td{border-bottom:none;}
.vd-info-table td:first-child{color:var(--muted);font-size:12.5px;font-weight:600;width:140px;padding-right:12px;padding-top:12px;}
.vd-info-table td:last-child{color:var(--text);font-size:13.5px;font-weight:600;}
/* Chart */
.chart-container{padding:16px 20px 8px;position:relative;}
canvas{max-width:100%;}
/* Status donut */
.donut-wrap{display:flex;align-items:center;gap:20px;padding:16px 20px;}
.donut-legend{flex:1;}
.donut-legend-item{display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:12.5px;}
.donut-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
/* Top products */
.vd-product-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);}
.vd-product-row:last-child{border-bottom:none;}
.vd-product-img{width:40px;height:40px;border-radius:8px;object-fit:cover;background:#f3f4f6;flex-shrink:0;}
.vd-product-name{font-size:13px;font-weight:700;color:var(--text);flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.vd-product-stat{font-size:12px;color:var(--muted);}
.vd-product-rev{font-size:13px;font-weight:800;color:var(--brand);white-space:nowrap;}
/* Orders table */
.vd-table{width:100%;border-collapse:collapse;font-size:13px;}
.vd-table th{padding:10px 14px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);border-bottom:2px solid var(--border);white-space:nowrap;}
.vd-table td{padding:11px 14px;border-bottom:1px solid var(--border);color:var(--text);}
.vd-table tr:last-child td{border-bottom:none;}
.vd-table tr:hover td{background:#fafafa;}
/* Status pills */
.st{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;}
.st::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor;opacity:.7;}
.st-delivered{background:#d1fae5;color:#065f46;}
.st-pending{background:#fff3cd;color:#92400e;}
.st-confirmed{background:#dbeafe;color:#1e40af;}
.st-preparing{background:#ede9fe;color:#4c1d95;}
.st-cancelled{background:#fee2e2;color:#7f1d1d;}
.st-out_for_delivery,.st-ready_for_pickup,.st-picked_up{background:#e0f2fe;color:#0c4a6e;}
/* Month compare */
.vd-month-grid{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--border);}
.vd-month-cell{background:#fff;padding:18px 20px;}
.vd-month-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:6px;}
.vd-month-val{font-size:20px;font-weight:800;color:var(--text);}
.vd-month-sub{font-size:12px;color:var(--muted);margin-top:2px;}
/* License */
.vd-license-img{width:100%;max-height:180px;object-fit:contain;border-radius:10px;border:1.5px solid var(--border);cursor:pointer;}
@media(max-width:900px){.vd-kpi-row{grid-template-columns:1fr 1fr;}.vd-grid-3-1,.vd-grid-2{grid-template-columns:1fr;}}
</style>

@php
$rev      = floatval($orderStats->revenue ?? 0);
$orders   = intval($orderStats->total ?? 0);
$avgOrd   = floatval($orderStats->avg_order ?? 0);
$uniqCust = intval($orderStats->unique_customers ?? 0);
$delivered= intval($orderStats->delivered ?? 0);
$cancelled= intval($orderStats->cancelled ?? 0);
$pending  = intval($orderStats->pending ?? 0);

$tmRev    = floatval($thisMonth->revenue ?? 0);
$lmRev    = floatval($lastMonth->revenue ?? 0);
$tmOrd    = intval($thisMonth->orders ?? 0);
$lmOrd    = intval($lastMonth->orders ?? 0);
$revTrend = $lmRev > 0 ? round(($tmRev - $lmRev) / $lmRev * 100, 1) : null;
$ordTrend = $lmOrd > 0 ? round(($tmOrd - $lmOrd) / $lmOrd * 100, 1) : null;

$commission    = floatval($commissionStats->total_commission ?? 0);
$vendorEarning = floatval($commissionStats->total_earning ?? 0);

$chartDates = $chart->pluck('date')->map(fn($d)=>date('d M',strtotime($d)))->toArray();
$chartRevs  = $chart->pluck('revenue')->map(fn($v)=>round(floatval($v),2))->toArray();
$chartOrds  = $chart->pluck('orders')->toArray();

$statusColors = ['pending'=>'#f59e0b','confirmed'=>'#3b82f6','preparing'=>'#8b5cf6',
    'ready_for_pickup'=>'#06b6d4','out_for_delivery'=>'#0ea5e9','delivered'=>'#10b981','cancelled'=>'#ef4444'];
$deliveredPct = $orders > 0 ? round($delivered / $orders * 100) : 0;
@endphp

<div class="vd-wrap">

{{-- ═══ HERO ═══ --}}
<div class="vd-hero">
    @if($vendor->logo)
        <img src="{{ asset('storage/'.$vendor->logo) }}" class="vd-avatar" alt="{{ $vendor->name }}">
    @else
        <div class="vd-avatar-placeholder">{{ strtoupper(substr($vendor->name,0,1)) }}</div>
    @endif
    <div class="vd-hero-info">
        <h1 class="vd-hero-name">{{ $vendor->name }}</h1>
        <div class="vd-hero-meta">
            <span class="vd-badge vd-badge-module">{{ $vendor->module?->name ?? strtoupper($vendor->module_slug ?? 'N/A') }}</span>
            <span class="vd-badge {{ $vendor->is_active ? 'vd-badge-active' : 'vd-badge-inactive' }}">
                <i class="fas fa-circle" style="font-size:7px;"></i> {{ $vendor->is_active ? 'Active' : 'Inactive' }}
            </span>
            <span class="vd-badge {{ $vendor->is_approved ? 'vd-badge-approved' : 'vd-badge-pending' }}">
                <i class="fas fa-{{ $vendor->is_approved ? 'check' : 'clock' }}"></i> {{ $vendor->is_approved ? 'Approved' : 'Pending Approval' }}
            </span>
            @if($vendor->is_featured)
            <span class="vd-badge" style="background:rgba(251,191,36,.2);color:#fbbf24;border:1px solid rgba(251,191,36,.25);"><i class="fas fa-star"></i> Featured</span>
            @endif
            @if($vendor->rating)
            <span class="vd-hero-rating"><i class="fas fa-star"></i> {{ number_format($vendor->rating,1) }} ({{ $vendor->review_count ?? 0 }} reviews)</span>
            @endif
        </div>
        <div style="margin-top:10px;font-size:12.5px;color:rgba(255,255,255,.5);">
            Joined {{ $vendor->created_at->format('d M Y') }} &nbsp;·&nbsp;
            {{ $vendor->district?->name ?? '—' }} &nbsp;·&nbsp;
            {{ $vendor->user?->email ?? $vendor->email ?? '—' }}
        </div>
    </div>
    <div class="vd-hero-actions">
        @if(!$vendor->is_approved)
        <form action="{{ route('admin.vendors.approve', $vendor->id) }}" method="POST" style="display:contents;">
            @csrf <button class="vd-btn vd-btn-success"><i class="fas fa-check"></i> Approve</button>
        </form>
        <form action="{{ route('admin.vendors.reject', $vendor->id) }}" method="POST" style="display:contents;">
            @csrf <button class="vd-btn vd-btn-danger"><i class="fas fa-times"></i> Reject</button>
        </form>
        @endif
        <form action="{{ route('admin.vendors.toggle-featured', $vendor->id) }}" method="POST" style="display:contents;">
            @csrf
            <button class="vd-btn {{ $vendor->is_featured ? 'vd-btn-ghost' : 'vd-btn-primary' }}">
                <i class="fas fa-star"></i> {{ $vendor->is_featured ? 'Unfeature' : 'Make Featured' }}
            </button>
        </form>
    </div>
</div>

{{-- ═══ KPI ROW ═══ --}}
<div class="vd-kpi-row">
    <div class="vd-kpi">
        <div class="vd-kpi-icon" style="background:rgba(255,138,0,.12);color:#FF8A00;"><i class="fas fa-dollar-sign"></i></div>
        <div>
            <div class="vd-kpi-label">Total Revenue</div>
            <div class="vd-kpi-value">${{ number_format($rev, 2) }}</div>
            @if($revTrend !== null)
            <div class="vd-kpi-trend {{ $revTrend >= 0 ? 'trend-up' : 'trend-down' }}">
                <i class="fas fa-arrow-{{ $revTrend >= 0 ? 'up' : 'down' }}"></i> {{ abs($revTrend) }}% vs last month
            </div>
            @endif
        </div>
    </div>
    <div class="vd-kpi">
        <div class="vd-kpi-icon" style="background:rgba(59,130,246,.12);color:#3b82f6;"><i class="fas fa-shopping-bag"></i></div>
        <div>
            <div class="vd-kpi-label">Total Orders</div>
            <div class="vd-kpi-value">{{ number_format($orders) }}</div>
            @if($ordTrend !== null)
            <div class="vd-kpi-trend {{ $ordTrend >= 0 ? 'trend-up' : 'trend-down' }}">
                <i class="fas fa-arrow-{{ $ordTrend >= 0 ? 'up' : 'down' }}"></i> {{ abs($ordTrend) }}% vs last month
            </div>
            @endif
        </div>
    </div>
    <div class="vd-kpi">
        <div class="vd-kpi-icon" style="background:rgba(16,185,129,.12);color:#10b981;"><i class="fas fa-users"></i></div>
        <div>
            <div class="vd-kpi-label">Unique Customers</div>
            <div class="vd-kpi-value">{{ number_format($uniqCust) }}</div>
            <div class="vd-kpi-sub">{{ $deliveredPct }}% delivery rate</div>
        </div>
    </div>
    <div class="vd-kpi">
        <div class="vd-kpi-icon" style="background:rgba(139,92,246,.12);color:#8b5cf6;"><i class="fas fa-receipt"></i></div>
        <div>
            <div class="vd-kpi-label">Avg Order Value</div>
            <div class="vd-kpi-value">${{ number_format($avgOrd, 2) }}</div>
            <div class="vd-kpi-sub">{{ number_format($productCount) }} products listed</div>
        </div>
    </div>
</div>

{{-- ═══ CHART + INFO ═══ --}}
<div class="vd-grid-3-1">
    {{-- Revenue chart --}}
    <div class="vd-card">
        <div class="vd-card-head">
            <span class="vd-card-title"><i class="fas fa-chart-area" style="color:#FF8A00;"></i> Revenue — Last 30 Days</span>
        </div>
        <div class="chart-container" style="height:220px;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    {{-- Vendor Info --}}
    <div class="vd-card">
        <div class="vd-card-head">
            <span class="vd-card-title"><i class="fas fa-store" style="color:#FF8A00;"></i> Vendor Info</span>
        </div>
        <div class="vd-card-body" style="padding:10px 20px;">
            <table class="vd-info-table">
                <tr><td>Owner</td><td>{{ $vendor->user?->name ?? '—' }}</td></tr>
                <tr><td>Phone</td><td>{{ $vendor->user?->phone ?? $vendor->phone ?? '—' }}</td></tr>
                <tr><td>Email</td><td style="font-size:12px;">{{ $vendor->user?->email ?? $vendor->email ?? '—' }}</td></tr>
                <tr><td>District</td><td>{{ $vendor->district?->name ?? '—' }}</td></tr>
                <tr><td>Module</td><td>{{ $vendor->module?->name ?? strtoupper($vendor->module_slug ?? '—') }}</td></tr>
                <tr><td>Min Order</td><td>${{ number_format($vendor->minimum_order ?? 0, 2) }}</td></tr>
                <tr><td>Delivery Fee</td><td>${{ number_format($vendor->delivery_fee ?? 0, 2) }}</td></tr>
                <tr><td>Commission</td><td>{{ $vendor->commission_value ?? 10 }}%</td></tr>
                <tr><td>Store Status</td><td>
                    <span class="st {{ ($vendor->is_open ?? false) && !($vendor->temporarily_closed ?? false) ? 'st-delivered' : 'st-cancelled' }}">
                        {{ ($vendor->temporarily_closed ?? false) ? 'Temp. Closed' : (($vendor->is_open ?? false) ? 'Open' : 'Closed') }}
                    </span>
                </td></tr>
            </table>
        </div>
    </div>
</div>

{{-- ═══ WALLET + WITHDRAWAL SUMMARY ═══ --}}
<div class="vd-kpi-row" style="margin-bottom:16px;">
    <div class="vd-kpi">
        <div class="vd-kpi-icon" style="background:rgba(16,185,129,.12);color:#10b981;"><i class="fas fa-wallet"></i></div>
        <div>
            <div class="vd-kpi-label">Wallet Balance</div>
            <div class="vd-kpi-value">${{ number_format($wallet->balance ?? 0, 2) }}</div>
            <div class="vd-kpi-sub">Current ePay balance</div>
        </div>
    </div>
    <div class="vd-kpi">
        <div class="vd-kpi-icon" style="background:rgba(59,130,246,.12);color:#3b82f6;"><i class="fas fa-money-bill-wave"></i></div>
        <div>
            <div class="vd-kpi-label">Total Withdrawn</div>
            <div class="vd-kpi-value">${{ number_format($withdrawalStats->total_paid ?? 0, 2) }}</div>
            <div class="vd-kpi-sub">{{ $withdrawalStats->total_requests ?? 0 }} total requests</div>
        </div>
    </div>
    <div class="vd-kpi">
        <div class="vd-kpi-icon" style="background:rgba(245,158,11,.12);color:#f59e0b;"><i class="fas fa-clock"></i></div>
        <div>
            <div class="vd-kpi-label">Pending Withdrawal</div>
            <div class="vd-kpi-value">${{ number_format($withdrawalStats->pending_amount ?? 0, 2) }}</div>
            <div class="vd-kpi-sub">Awaiting approval</div>
        </div>
    </div>
    <div class="vd-kpi">
        <div class="vd-kpi-icon" style="background:rgba(139,92,246,.12);color:#8b5cf6;"><i class="fas fa-percentage"></i></div>
        <div>
            <div class="vd-kpi-label">Net Vendor Earnings</div>
            <div class="vd-kpi-value">${{ number_format($vendorEarning, 2) }}</div>
            <div class="vd-kpi-sub">After {{ $vendor->commission_value ?? 10 }}% commission</div>
        </div>
    </div>
</div>

{{-- ═══ WITHDRAWAL HISTORY ═══ --}}
<div class="vd-card" style="margin-bottom:16px;">
    <div class="vd-card-head">
        <span class="vd-card-title"><i class="fas fa-history" style="color:#3b82f6;"></i> Withdrawal History</span>
        @if($wallet)
        <span style="font-size:12px;font-weight:700;color:#10b981;">Balance: ${{ number_format($wallet->balance ?? 0,2) }}</span>
        @endif
    </div>
    <div style="overflow-x:auto;">
        <table class="vd-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Account</th>
                    <th>Status</th>
                    <th>Reference</th>
                    <th>Requested</th>
                    <th>Processed</th>
                </tr>
            </thead>
            <tbody>
                @forelse($withdrawals as $wd)
                @php
                $wdColors = ['pending'=>['#fff3cd','#92400e'],'approved'=>['#dbeafe','#1e40af'],'processed'=>['#d1fae5','#065f46'],'rejected'=>['#fee2e2','#7f1d1d']];
                [$wdBg,$wdFg] = $wdColors[$wd->status] ?? ['#f3f4f6','#374151'];
                @endphp
                <tr>
                    <td style="color:var(--muted);font-size:12px;">{{ $wd->id }}</td>
                    <td style="font-weight:800;font-size:14px;">${{ number_format($wd->amount,2) }}</td>
                    <td style="font-size:12.5px;">{{ ucfirst($wd->method ?? $wd->payment_method ?? '—') }}</td>
                    <td>
                        <div style="font-size:12.5px;font-weight:600;">{{ $wd->account_name ?? '—' }}</div>
                        <div style="font-size:11px;color:var(--muted);">{{ $wd->account_number ?? '' }}</div>
                    </td>
                    <td>
                        <span style="padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;background:{{ $wdBg }};color:{{ $wdFg }};">
                            {{ ucfirst($wd->status) }}
                        </span>
                    </td>
                    <td style="font-size:12px;color:var(--muted);">{{ $wd->transaction_reference ?? '—' }}</td>
                    <td style="font-size:11.5px;color:var(--muted);white-space:nowrap;">{{ \Carbon\Carbon::parse($wd->created_at)->format('d M y') }}</td>
                    <td style="font-size:11.5px;color:var(--muted);white-space:nowrap;">{{ $wd->processed_at ? \Carbon\Carbon::parse($wd->processed_at)->format('d M y') : '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;padding:28px;color:#9ca3af;">
                    <i class="fas fa-money-bill-wave" style="font-size:24px;opacity:.3;display:block;margin-bottom:6px;"></i>
                    No withdrawal requests yet
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ═══ MONTH COMPARE + STATUS + TOP PRODUCTS ═══ --}}
<div class="vd-grid-2">
    <div style="display:flex;flex-direction:column;gap:16px;">
        {{-- Month comparison --}}
        <div class="vd-card">
            <div class="vd-card-head">
                <span class="vd-card-title"><i class="fas fa-calendar-alt" style="color:#3b82f6;"></i> Monthly Comparison</span>
            </div>
            <div class="vd-month-grid">
                <div class="vd-month-cell">
                    <div class="vd-month-label">This Month</div>
                    <div class="vd-month-val">${{ number_format($tmRev, 2) }}</div>
                    <div class="vd-month-sub">{{ $tmOrd }} orders</div>
                </div>
                <div class="vd-month-cell">
                    <div class="vd-month-label">Last Month</div>
                    <div class="vd-month-val">${{ number_format($lmRev, 2) }}</div>
                    <div class="vd-month-sub">{{ $lmOrd }} orders</div>
                </div>
                <div class="vd-month-cell" style="border-top:1px solid var(--border);">
                    <div class="vd-month-label">Platform Commission</div>
                    <div class="vd-month-val" style="color:#ef4444;">${{ number_format($commission, 2) }}</div>
                    <div class="vd-month-sub">All time</div>
                </div>
                <div class="vd-month-cell" style="border-top:1px solid var(--border);">
                    <div class="vd-month-label">Vendor Earnings</div>
                    <div class="vd-month-val" style="color:#10b981;">${{ number_format($vendorEarning, 2) }}</div>
                    <div class="vd-month-sub">After commission</div>
                </div>
            </div>
        </div>

        {{-- Order status donut --}}
        <div class="vd-card">
            <div class="vd-card-head">
                <span class="vd-card-title"><i class="fas fa-chart-pie" style="color:#8b5cf6;"></i> Order Status Breakdown</span>
            </div>
            <div class="donut-wrap">
                <canvas id="statusChart" width="120" height="120" style="flex-shrink:0;"></canvas>
                <div class="donut-legend">
                    @foreach($statusBreakdown as $row)
                    @php $col = $statusColors[$row->status] ?? '#9ca3af'; @endphp
                    <div class="donut-legend-item">
                        <div class="donut-dot" style="background:{{ $col }};"></div>
                        <span style="flex:1;color:#374151;">{{ ucfirst(str_replace('_',' ',$row->status)) }}</span>
                        <strong style="color:#111;">{{ $row->cnt }}</strong>
                    </div>
                    @endforeach
                    @if($statusBreakdown->isEmpty())
                    <div style="color:#9ca3af;font-size:13px;">No orders yet</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Top products + License --}}
    <div style="display:flex;flex-direction:column;gap:16px;">
        <div class="vd-card">
            <div class="vd-card-head">
                <span class="vd-card-title"><i class="fas fa-fire" style="color:#f59e0b;"></i> Top Products by Sales</span>
                <span style="font-size:12px;color:var(--muted);">{{ $productCount }} total</span>
            </div>
            <div class="vd-card-body" style="padding:10px 20px;">
                @forelse($topProducts as $i => $p)
                @php $rankColors = ['#FF8A00','#f59e0b','#10b981','#3b82f6','#8b5cf6']; @endphp
                <div class="vd-product-row">
                    <div style="width:22px;height:22px;border-radius:50%;background:{{ $rankColors[$i] ?? '#9ca3af' }};color:#fff;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;">{{ $i+1 }}</div>
                    @if($p->thumbnail)
                    <img src="{{ asset('storage/'.$p->thumbnail) }}" class="vd-product-img" alt="">
                    @else
                    <div class="vd-product-img" style="display:flex;align-items:center;justify-content:center;font-size:18px;">🛍</div>
                    @endif
                    <div style="flex:1;min-width:0;">
                        <div class="vd-product-name">{{ $p->name }}</div>
                        <div class="vd-product-stat">{{ $p->sold }} sold</div>
                    </div>
                    <div class="vd-product-rev">${{ number_format($p->revenue,2) }}</div>
                </div>
                @empty
                <div style="text-align:center;padding:24px;color:#9ca3af;">
                    <i class="fas fa-box-open" style="font-size:28px;margin-bottom:8px;display:block;opacity:.3;"></i>
                    No sales data yet
                </div>
                @endforelse
            </div>
        </div>

        @if($vendor->business_license)
        <div class="vd-card">
            <div class="vd-card-head">
                <span class="vd-card-title"><i class="fas fa-id-card" style="color:#06b6d4;"></i> Business License</span>
                <a href="{{ asset('storage/'.$vendor->business_license) }}" target="_blank" style="font-size:12px;color:#FF8A00;font-weight:700;text-decoration:none;">
                    <i class="fas fa-external-link-alt"></i> View Full
                </a>
            </div>
            <div class="vd-card-body">
                @php $ext = strtolower(pathinfo($vendor->business_license, PATHINFO_EXTENSION)); @endphp
                @if(in_array($ext, ['jpg','jpeg','png','webp']))
                <a href="{{ asset('storage/'.$vendor->business_license) }}" target="_blank">
                    <img src="{{ asset('storage/'.$vendor->business_license) }}" class="vd-license-img" alt="License">
                </a>
                @elseif($ext === 'pdf')
                <a href="{{ asset('storage/'.$vendor->business_license) }}" target="_blank"
                   style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:#fff5f5;border:1.5px solid #fecaca;border-radius:10px;color:#b91c1c;font-weight:700;text-decoration:none;">
                    <i class="fas fa-file-pdf" style="font-size:22px;"></i> View PDF License
                </a>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

{{-- ═══ RECENT ORDERS ═══ --}}
<div class="vd-card">
    <div class="vd-card-head">
        <span class="vd-card-title"><i class="fas fa-list-alt" style="color:#FF8A00;"></i> Recent Orders</span>
        <a href="{{ route('admin.orders.index', ['vendor_id' => $vendor->id]) }}" style="font-size:12px;color:#FF8A00;font-weight:700;text-decoration:none;">View All <i class="fas fa-arrow-right"></i></a>
    </div>
    <div style="overflow-x:auto;">
        <table class="vd-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentOrders as $order)
                <tr>
                    <td><a href="{{ route('admin.orders.show', $order->id) }}" style="color:#FF8A00;font-weight:700;text-decoration:none;">{{ $order->order_number }}</a></td>
                    <td><div style="font-weight:600;font-size:13px;">{{ $order->customer_name }}</div></td>
                    <td style="font-weight:800;">${{ number_format($order->total_amount, 2) }}</td>
                    <td>
                        <span style="font-size:12px;padding:2px 8px;border-radius:6px;background:{{ ($order->payment_status ?? '') === 'paid' ? '#d1fae5' : '#fff3cd' }};color:{{ ($order->payment_status ?? '') === 'paid' ? '#065f46' : '#92400e' }};font-weight:700;">
                            {{ ucfirst($order->payment_status ?? 'unpaid') }}
                        </span>
                    </td>
                    <td>
                        <span class="st st-{{ str_replace(' ','_',strtolower($order->status)) }}">
                            {{ ucfirst(str_replace('_',' ',$order->status)) }}
                        </span>
                    </td>
                    <td style="color:var(--muted);font-size:12px;">{{ \Carbon\Carbon::parse($order->created_at)->format('d M y, H:i') }}</td>
                    <td><a href="{{ route('admin.orders.show', $order->id) }}" style="font-size:12px;color:#6b7280;font-weight:600;text-decoration:none;">View</a></td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;padding:32px;color:#9ca3af;">
                    <i class="fas fa-inbox" style="font-size:28px;margin-bottom:8px;display:block;opacity:.3;"></i>
                    No orders yet
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>{{-- /vd-wrap --}}

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
    const labels = @json($chartDates);
    const revs   = @json($chartRevs);
    const ords   = @json($chartOrds);
    const ctx = document.getElementById('revenueChart');
    if(!ctx||!labels.length) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Revenue ($)',
                data: revs,
                borderColor: '#FF8A00',
                backgroundColor: 'rgba(255,138,0,.08)',
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 5,
                borderWidth: 2.5,
                yAxisID: 'y',
            },{
                label: 'Orders',
                data: ords,
                borderColor: '#3b82f6',
                backgroundColor: 'transparent',
                fill: false,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 5,
                borderWidth: 2,
                borderDash: [4,4],
                yAxisID: 'y1',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {mode:'index',intersect:false},
            plugins: {legend:{display:true,position:'top',labels:{boxWidth:12,font:{size:11}}}},
            scales: {
                x:{grid:{display:false},ticks:{maxTicksLimit:8,font:{size:10}}},
                y:{position:'left',grid:{color:'#f0f0f0'},ticks:{callback:v=>'$'+v,font:{size:10}}},
                y1:{position:'right',grid:{display:false},ticks:{font:{size:10}}},
            }
        }
    });
})();

(function(){
    const breakdown = @json($statusBreakdown->pluck('cnt','status'));
    const colorMap  = @json($statusColors);
    const labels    = Object.keys(breakdown);
    if(!labels.length) return;
    const data   = labels.map(k=>breakdown[k]);
    const colors = labels.map(k=>colorMap[k]||'#9ca3af');
    const ctx = document.getElementById('statusChart');
    if(!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {labels: labels.map(l=>l.replace(/_/g,' ')), datasets:[{data,backgroundColor:colors,borderWidth:0,spacing:2}]},
        options: {
            responsive:false,
            cutout:'68%',
            plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return ' '+c.label+': '+c.raw;}}}}
        }
    });
})();
</script>
@endsection
