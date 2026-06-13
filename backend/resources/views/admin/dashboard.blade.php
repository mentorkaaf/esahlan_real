@extends('admin.layouts.app')
@section('title', 'Dashboard')

@section('content')

{{-- Page Header --}}
<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <ul class="breadcrumb"><li><span>Overview</span></li></ul>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="display:flex;align-items:center;gap:7px;background:#fff;border:1.5px solid var(--border);border-radius:9px;padding:7px 13px;font-size:12.5px;color:var(--text-muted);">
            <i class="fas fa-calendar-alt" style="color:var(--brand);"></i>
            {{ now()->format('l, d M Y') }}
        </div>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Order
        </a>
    </div>
</div>

{{-- ── KPI Stats ───────────────────────────────────────────────────── --}}
<div class="stats-grid">
    {{-- Users --}}
    <div class="stat-card orange">
        <div class="stat-icon-wrap orange"><i class="fas fa-users"></i></div>
        <div class="stat-body">
            <div class="stat-value">{{ number_format($stats['users']['total']) }}</div>
            <div class="stat-label">Total Users</div>
            <div class="stat-sub up">
                <i class="fas fa-arrow-up"></i> {{ $stats['users']['today'] }} today
            </div>
        </div>
    </div>

    {{-- Orders --}}
    <div class="stat-card green">
        <div class="stat-icon-wrap green"><i class="fas fa-shopping-bag"></i></div>
        <div class="stat-body">
            <div class="stat-value">{{ number_format($stats['orders']['total']) }}</div>
            <div class="stat-label">Total Orders</div>
            <div class="stat-sub warn">
                <i class="fas fa-clock"></i> {{ $stats['orders']['pending'] }} pending
            </div>
        </div>
    </div>

    {{-- Revenue --}}
    <div class="stat-card blue">
        <div class="stat-icon-wrap blue"><i class="fas fa-dollar-sign"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($stats['revenue']['total'], 0) }}</div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-sub muted">
                <i class="fas fa-calendar-day"></i> ${{ number_format($stats['revenue']['today'], 2) }} today
            </div>
        </div>
    </div>

    {{-- Commission --}}
    <div class="stat-card red">
        <div class="stat-icon-wrap red"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($stats['commission']['total'], 0) }}</div>
            <div class="stat-label">Commission Earned</div>
            <div class="stat-sub muted">
                <i class="fas fa-calendar-week"></i> ${{ number_format($stats['commission']['monthly'], 2) }} this month
            </div>
        </div>
    </div>

    {{-- Vendors --}}
    <div class="stat-card purple">
        <div class="stat-icon-wrap purple"><i class="fas fa-store"></i></div>
        <div class="stat-body">
            <div class="stat-value">{{ $stats['vendors']['total'] }}</div>
            <div class="stat-label">Active Vendors</div>
            <div class="stat-sub warn">
                <i class="fas fa-hourglass-half"></i> {{ $stats['vendors']['pending'] }} awaiting approval
            </div>
        </div>
    </div>

    {{-- Deliverymen --}}
    <div class="stat-card teal">
        <div class="stat-icon-wrap teal"><i class="fas fa-motorcycle"></i></div>
        <div class="stat-body">
            <div class="stat-value">{{ $stats['deliverymen']['total'] }}</div>
            <div class="stat-label">Deliverymen</div>
            <div class="stat-sub up">
                <i class="fas fa-circle" style="font-size:7px;"></i> {{ $stats['deliverymen']['available'] }} online
            </div>
        </div>
    </div>
</div>

{{-- ── Charts + Actions ────────────────────────────────────────────── --}}
<div class="grid-2" style="align-items:start;">

    {{-- Chart Card --}}
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div class="card-header-title">
                <div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);">
                    <i class="fas fa-chart-line"></i>
                </div>
                Orders & Revenue — Last 30 Days
            </div>
            <div style="display:flex;gap:12px;font-size:12px;color:var(--text-muted);">
                <span style="display:flex;align-items:center;gap:5px;">
                    <span style="width:12px;height:3px;background:var(--brand);border-radius:2px;display:inline-block;"></span> Orders
                </span>
                <span style="display:flex;align-items:center;gap:5px;">
                    <span style="width:12px;height:3px;background:var(--info);border-radius:2px;display:inline-block;"></span> Revenue
                </span>
            </div>
        </div>
        <div class="card-body">
            <canvas id="ordersChart" height="150"></canvas>
        </div>
    </div>

    {{-- Right column --}}
    <div style="display:flex;flex-direction:column;gap:16px;">

        {{-- Quick Actions --}}
        <div class="card" style="margin-bottom:0;">
            <div class="card-header">
                <div class="card-header-title">
                    <div class="card-header-icon" style="background:rgba(16,185,129,0.1);color:var(--success);">
                        <i class="fas fa-bolt"></i>
                    </div>
                    Quick Actions
                </div>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px;padding:14px;">
                <a href="{{ route('admin.orders.index') }}?status=pending"
                   style="display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:9px;background:rgba(255,138,0,0.06);border:1.5px solid rgba(255,138,0,0.15);text-decoration:none;transition:background .15s;"
                   onmouseover="this.style.background='rgba(255,138,0,0.12)'" onmouseout="this.style.background='rgba(255,138,0,0.06)'">
                    <div style="width:34px;height:34px;border-radius:8px;background:rgba(255,138,0,0.15);color:var(--brand);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-clock"></i></div>
                    <div style="flex:1;"><div style="font-size:13px;font-weight:700;color:var(--text);">Pending Orders</div><div style="font-size:11px;color:var(--text-muted);">{{ $stats['orders']['pending'] }} orders waiting</div></div>
                    <span class="badge badge-warning">{{ $stats['orders']['pending'] }}</span>
                </a>
                <a href="{{ route('admin.vendors.index') }}?approved=0"
                   style="display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:9px;background:rgba(139,92,246,0.05);border:1.5px solid rgba(139,92,246,0.15);text-decoration:none;transition:background .15s;"
                   onmouseover="this.style.background='rgba(139,92,246,0.1)'" onmouseout="this.style.background='rgba(139,92,246,0.05)'">
                    <div style="width:34px;height:34px;border-radius:8px;background:rgba(139,92,246,0.15);color:var(--purple);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-store"></i></div>
                    <div style="flex:1;"><div style="font-size:13px;font-weight:700;color:var(--text);">Vendor Approvals</div><div style="font-size:11px;color:var(--text-muted);">{{ $stats['vendors']['pending'] }} vendors pending</div></div>
                    <span class="badge badge-purple">{{ $stats['vendors']['pending'] }}</span>
                </a>
                <a href="{{ route('admin.finance.index') }}"
                   style="display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:9px;background:rgba(59,130,246,0.05);border:1.5px solid rgba(59,130,246,0.15);text-decoration:none;transition:background .15s;"
                   onmouseover="this.style.background='rgba(59,130,246,0.1)'" onmouseout="this.style.background='rgba(59,130,246,0.05)'">
                    <div style="width:34px;height:34px;border-radius:8px;background:rgba(59,130,246,0.15);color:var(--info);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-money-bill-wave"></i></div>
                    <div style="flex:1;"><div style="font-size:13px;font-weight:700;color:var(--text);">Withdrawals</div><div style="font-size:11px;color:var(--text-muted);">{{ $stats['withdrawals']['pending'] }} pending</div></div>
                    <span class="badge badge-info">{{ $stats['withdrawals']['pending'] }}</span>
                </a>
                <a href="{{ route('admin.dispatch') }}"
                   style="display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:9px;background:rgba(16,185,129,0.05);border:1.5px solid rgba(16,185,129,0.15);text-decoration:none;transition:background .15s;"
                   onmouseover="this.style.background='rgba(16,185,129,0.1)'" onmouseout="this.style.background='rgba(16,185,129,0.05)'">
                    <div style="width:34px;height:34px;border-radius:8px;background:rgba(16,185,129,0.15);color:var(--success);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-map-marked-alt"></i></div>
                    <div style="flex:1;"><div style="font-size:13px;font-weight:700;color:var(--text);">Dispatch Center</div><div style="font-size:11px;color:var(--text-muted);">{{ $stats['deliverymen']['available'] }} riders online</div></div>
                    <i class="fas fa-arrow-right" style="color:var(--text-muted);font-size:11px;"></i>
                </a>
            </div>
        </div>

        {{-- This Month Summary --}}
        <div class="card" style="margin-bottom:0;">
            <div class="card-header">
                <div class="card-header-title">
                    <div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    This Month
                </div>
                <span style="font-size:11px;color:var(--text-muted);">{{ now()->format('F Y') }}</span>
            </div>
            <div class="card-body" style="padding:16px 20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div style="text-align:center;padding:14px;background:#fafbff;border-radius:10px;border:1px solid var(--border);">
                        <div style="font-size:22px;font-weight:800;color:var(--text);">{{ $stats['users']['monthly'] }}</div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:3px;font-weight:600;">New Users</div>
                    </div>
                    <div style="text-align:center;padding:14px;background:#fafbff;border-radius:10px;border:1px solid var(--border);">
                        <div style="font-size:22px;font-weight:800;color:var(--text);">{{ $stats['orders']['today'] }}</div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:3px;font-weight:600;">Today's Orders</div>
                    </div>
                    <div style="text-align:center;padding:14px;background:rgba(16,185,129,0.05);border-radius:10px;border:1px solid rgba(16,185,129,0.15);">
                        <div style="font-size:22px;font-weight:800;color:var(--success);">${{ number_format($stats['revenue']['monthly'], 0) }}</div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:3px;font-weight:600;">Revenue</div>
                    </div>
                    <div style="text-align:center;padding:14px;background:rgba(255,138,0,0.05);border-radius:10px;border:1px solid rgba(255,138,0,0.15);">
                        <div style="font-size:22px;font-weight:800;color:var(--brand);">${{ number_format($stats['commission']['monthly'], 0) }}</div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:3px;font-weight:600;">Commission</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Recent Orders ────────────────────────────────────────────────── --}}
@php
$recentOrders = \App\Models\Order::with(['user:id,name','vendor:id,name'])
    ->latest()->limit(8)->get();
@endphp
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);">
                <i class="fas fa-receipt"></i>
            </div>
            Recent Orders
        </div>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline btn-sm">
            View All <i class="fas fa-arrow-right"></i>
        </a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Module</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentOrders as $order)
                @php
                    $statusMap = [
                        'pending'   => ['badge-warning','clock','Pending'],
                        'confirmed' => ['badge-info','check','Confirmed'],
                        'preparing' => ['badge-info','fire','Preparing'],
                        'ready'     => ['badge-teal','box','Ready'],
                        'picked_up' => ['badge-purple','motorcycle','Picked Up'],
                        'delivered' => ['badge-success','check-circle','Delivered'],
                        'cancelled' => ['badge-danger','times-circle','Cancelled'],
                        'failed'    => ['badge-danger','exclamation-circle','Failed'],
                    ];
                    [$badgeClass, $icon, $label] = $statusMap[$order->status] ?? ['badge-secondary','circle','Unknown'];
                @endphp
                <tr>
                    <td>
                        <span style="font-size:13px;font-weight:700;color:var(--navy);">{{ $order->order_number }}</span>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:9px;">
                            <div class="avatar avatar-sm avatar-orange">{{ strtoupper(substr($order->user?->name ?? 'U',0,1)) }}</div>
                            <span style="font-weight:600;font-size:13px;">{{ $order->user?->name ?? 'Guest' }}</span>
                        </div>
                    </td>
                    <td>
                        @if($order->module_slug)
                            <span class="badge badge-dark">{{ strtoupper($order->module_slug) }}</span>
                        @else
                            <span class="text-muted text-xs">—</span>
                        @endif
                    </td>
                    <td><span style="font-weight:700;color:var(--text);">${{ number_format($order->total_amount,2) }}</span></td>
                    <td>
                        <span class="badge {{ $badgeClass }} badge-dot">
                            {{ $label }}
                        </span>
                    </td>
                    <td style="color:var(--text-muted);font-size:12px;">{{ \Carbon\Carbon::parse($order->created_at)->diffForHumans() }}</td>
                    <td>
                        <a href="{{ route('admin.orders.show',$order) }}" class="btn btn-ghost btn-xs">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7"><div class="empty-state" style="padding:30px;"><i class="fas fa-inbox"></i><p>No orders yet</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const chartData = @json($stats['chart']['daily_orders']);
const labels   = chartData.map(d => {
    const dt = new Date(d.date);
    return dt.toLocaleDateString('en-US',{month:'short',day:'numeric'});
});
const counts  = chartData.map(d => d.count);
const revenue = chartData.map(d => parseFloat(d.revenue));

const ctx = document.getElementById('ordersChart').getContext('2d');

// Gradient fills
const gradOrders = ctx.createLinearGradient(0,0,0,300);
gradOrders.addColorStop(0,'rgba(255,138,0,0.25)');
gradOrders.addColorStop(1,'rgba(255,138,0,0)');

const gradRevenue = ctx.createLinearGradient(0,0,0,300);
gradRevenue.addColorStop(0,'rgba(59,130,246,0.18)');
gradRevenue.addColorStop(1,'rgba(59,130,246,0)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels,
        datasets: [
            {
                label: 'Orders',
                data: counts,
                borderColor: '#FF8A00',
                backgroundColor: gradOrders,
                tension: 0.45, fill: true,
                borderWidth: 2.5,
                pointRadius: 3, pointHoverRadius: 6,
                pointBackgroundColor: '#FF8A00',
                yAxisID: 'y',
            },
            {
                label: 'Revenue ($)',
                data: revenue,
                borderColor: '#3b82f6',
                backgroundColor: gradRevenue,
                tension: 0.45, fill: true,
                borderWidth: 2.5,
                pointRadius: 3, pointHoverRadius: 6,
                pointBackgroundColor: '#3b82f6',
                yAxisID: 'y1',
            },
        ],
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0c0148',
                titleColor: 'rgba(255,255,255,0.7)',
                bodyColor: '#fff',
                padding: 12,
                cornerRadius: 10,
                titleFont: { size: 12 },
                bodyFont: { size: 13, weight: '700' },
            },
        },
        scales: {
            x: {
                grid: { color: '#f0f2f8', drawBorder: false },
                ticks: { color: '#7b7fa8', font: { size: 11 }, maxTicksLimit: 10 },
            },
            y:  {
                type: 'linear', position: 'left', beginAtZero: true,
                grid: { color: '#f0f2f8', drawBorder: false },
                ticks: { color: '#7b7fa8', font: { size: 11 } },
            },
            y1: {
                type: 'linear', position: 'right', beginAtZero: true,
                grid: { drawOnChartArea: false },
                ticks: { color: '#3b82f6', font: { size: 11 }, callback: v => '$'+v },
            },
        },
    },
});
</script>
@endpush
@endsection
