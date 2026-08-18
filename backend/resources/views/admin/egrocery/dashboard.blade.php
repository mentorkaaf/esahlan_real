@extends('admin.layouts.app')
@section('title', 'eGrocery Dashboard')
@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-carrot" style="color:#10B981"></i> eGrocery Management</h2>
        <ol class="breadcrumb"><li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li>eGrocery</li></ol>
    </div>
</div>

@include('admin.egrocery._subnav')

@if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif

{{-- Stat Cards --}}
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
    <div class="stat-card orange">
        <div class="stat-icon-wrap orange"><i class="fas fa-shopping-bag"></i></div>
        <div class="stat-body">
            <div class="stat-value">{{ $stats['today_orders'] }}</div>
            <div class="stat-label">Today's Orders</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon-wrap green"><i class="fas fa-dollar-sign"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($stats['today_revenue'], 2) }}</div>
            <div class="stat-label">Today's Revenue</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon-wrap blue"><i class="fas fa-clock"></i></div>
        <div class="stat-body">
            <div class="stat-value">{{ $stats['pending'] }}</div>
            <div class="stat-label">Pending Orders</div>
            <div class="stat-sub {{ $stats['pending'] > 5 ? 'warn' : 'muted' }}">
                <i class="fas fa-circle-dot"></i> awaiting confirmation
            </div>
        </div>
    </div>
    <div class="stat-card teal">
        <div class="stat-icon-wrap teal"><i class="fas fa-truck"></i></div>
        <div class="stat-body">
            <div class="stat-value">{{ $stats['out_delivery'] }}</div>
            <div class="stat-label">Out for Delivery</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon-wrap red"><i class="fas fa-triangle-exclamation"></i></div>
        <div class="stat-body">
            <div class="stat-value">{{ $stats['low_stock'] }}</div>
            <div class="stat-label">Low Stock Items</div>
            <div class="stat-sub {{ $stats['low_stock'] > 0 ? 'warn' : 'muted' }}">
                <i class="fas fa-arrow-right"></i> <a href="{{ route('admin.module-data.egrocery.inventory') }}" style="color:inherit;">View inventory</a>
            </div>
        </div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon-wrap purple"><i class="fas fa-bolt"></i></div>
        <div class="stat-body">
            <div class="stat-value">{{ $stats['flash_deals'] }}</div>
            <div class="stat-label">Active Flash Deals</div>
        </div>
    </div>
</div>

{{-- Charts row --}}
<div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;margin-bottom:18px;">
    <div class="card">
        <div class="card-header">
            <div class="card-header-title"><i class="fas fa-chart-line" style="color:#10b981"></i> Orders (14 days)</div>
        </div>
        <div class="card-body" style="padding:16px 20px;">
            <canvas id="ordersChart" height="90"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-header-title"><i class="fas fa-chart-pie" style="color:#3b82f6"></i> Category Revenue (30d)</div>
        </div>
        <div class="card-body" style="padding:16px 20px;">
            <canvas id="catChart" height="130"></canvas>
        </div>
    </div>
</div>

{{-- Top products + Needs Attention --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;">
    <div class="card">
        <div class="card-header">
            <div class="card-header-title"><i class="fas fa-fire" style="color:#FF8A00"></i> Top 10 Products (30d)</div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Revenue</th></tr></thead>
                <tbody>
                    @forelse($topProducts as $i => $p)
                    <tr>
                        <td style="color:#9ca3af;font-size:12px;">{{ $i+1 }}</td>
                        <td style="font-weight:600;">{{ $p->name }}</td>
                        <td>{{ number_format($p->total_qty, 1) }}</td>
                        <td style="color:#10b981;font-weight:700;">${{ number_format($p->total_rev, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:24px;">No orders yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div>
        {{-- Needs Attention --}}
        @if($stalePending->count() > 0)
        <div class="card" style="border-left:3px solid #f59e0b;">
            <div class="card-header" style="background:#fffbeb;">
                <div class="card-header-title" style="color:#d97706;"><i class="fas fa-triangle-exclamation"></i> Stale Pending Orders</div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Order</th><th>Customer</th><th>Age</th></tr></thead>
                    <tbody>
                        @foreach($stalePending as $o)
                        <tr>
                            <td><a href="{{ route('admin.module-data.egrocery.order.show', $o->id) }}" style="color:#FF8A00;font-weight:700;">{{ $o->order_no }}</a></td>
                            <td>{{ $o->user?->name }}</td>
                            <td style="color:#ef4444;">{{ $o->created_at->diffForHumans() }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if($lowStockItems->count() > 0)
        <div class="card" style="border-left:3px solid #ef4444;">
            <div class="card-header" style="background:#fef2f2;">
                <div class="card-header-title" style="color:#dc2626;"><i class="fas fa-box-open"></i> Low Stock</div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Product</th><th>Variant</th><th>Stock</th></tr></thead>
                    <tbody>
                        @foreach($lowStockItems as $v)
                        <tr>
                            <td style="font-weight:600;">{{ $v->product?->name }}</td>
                            <td style="color:#64748b;">{{ $v->label }}</td>
                            <td><span class="badge badge-danger">{{ $v->stock_qty }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if($flashEndingToday->count() > 0)
        <div class="card" style="border-left:3px solid #8b5cf6;">
            <div class="card-header" style="background:#f5f3ff;">
                <div class="card-header-title" style="color:#7c3aed;"><i class="fas fa-bolt"></i> Flash Deals Ending Today</div>
            </div>
            <div class="card-body" style="padding:12px 16px;">
                @foreach($flashEndingToday as $s)
                <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;">{{ $s->title }}</div>
                @endforeach
            </div>
        </div>
        @endif

        @if($stalePending->isEmpty() && $lowStockItems->isEmpty() && $flashEndingToday->isEmpty())
        <div class="card">
            <div class="card-body" style="text-align:center;padding:32px 20px;color:#10b981;">
                <i class="fas fa-circle-check" style="font-size:32px;margin-bottom:10px;display:block;"></i>
                <div style="font-weight:700;">All clear!</div>
                <div style="font-size:12px;color:#9ca3af;margin-top:4px;">No items need attention right now.</div>
            </div>
        </div>
        @endif
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
@php
$chartLabels = $chartDays->pluck('day')->map(fn($d) => \Carbon\Carbon::parse($d)->format('M j'))->toJson();
$chartOrders = $chartDays->pluck('cnt')->toJson();
$chartRev    = $chartDays->pluck('revenue')->toJson();
$catLabels   = $catRevenue->pluck('name')->toJson();
$catData     = $catRevenue->pluck('revenue')->toJson();
@endphp
(function(){
    const labels = {!! $chartLabels !!};
    const orders = {!! $chartOrders !!};
    const rev    = {!! $chartRev !!};
    new Chart(document.getElementById('ordersChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Orders', data: orders, backgroundColor: 'rgba(16,185,129,0.2)', borderColor: '#10b981', borderWidth: 2, borderRadius: 6, yAxisID: 'y' },
                { label: 'Revenue ($)', data: rev, type: 'line', borderColor: '#FF8A00', backgroundColor: 'rgba(255,138,0,0.08)', pointRadius: 3, tension: 0.4, yAxisID: 'y1' }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { font: { size: 11 } } } },
            scales: {
                y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 } } },
                y1: { position: 'right', grid: { drawOnChartArea: false }, ticks: { font: { size: 10 }, callback: v => '$'+v } }
            }
        }
    });
    new Chart(document.getElementById('catChart'), {
        type: 'doughnut',
        data: {
            labels: {!! $catLabels !!},
            datasets: [{ data: {!! $catData !!}, backgroundColor: ['#10b981','#3b82f6','#FF8A00','#8b5cf6','#f59e0b','#ec4899'], borderWidth: 2 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 }, padding: 8 } } } }
    });
})();
</script>
@endsection
