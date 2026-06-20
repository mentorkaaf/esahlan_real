@extends('vendor.layouts.app')
@section('title', 'Dashboard')

@push('topbar-actions')
<form action="{{ route('vendor.toggle-store') }}" method="POST" style="margin:0;">
    @csrf
    <button type="submit" class="btn {{ $vendor->temporarily_closed ? 'btn-success' : 'btn-danger' }} btn-sm">
        <i class="fa-solid {{ $vendor->temporarily_closed ? 'fa-store' : 'fa-store-slash' }}"></i>
        {{ $vendor->temporarily_closed ? 'Open Store' : 'Close Store' }}
    </button>
</form>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <ul class="breadcrumb"><li>eSahlan</li><li>Dashboard</li></ul>
    </div>
</div>

{{-- Stats --}}
<div class="stats-grid">
    <div class="stat-card orange">
        <div class="stat-icon-wrap orange"><i class="fa-solid fa-bag-shopping"></i></div>
        <div>
            <div class="stat-value">{{ $stats['today_orders'] }}</div>
            <div class="stat-label">Today's Orders</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon-wrap green"><i class="fa-solid fa-dollar-sign"></i></div>
        <div>
            <div class="stat-value">${{ number_format($stats['today_earning'], 2) }}</div>
            <div class="stat-label">Today's Earning</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon-wrap red"><i class="fa-solid fa-clock"></i></div>
        <div>
            <div class="stat-value">{{ $stats['pending_orders'] }}</div>
            <div class="stat-label">Pending Orders</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon-wrap blue"><i class="fa-solid fa-chart-simple"></i></div>
        <div>
            <div class="stat-value">{{ $stats['total_orders'] }}</div>
            <div class="stat-label">Total Orders</div>
        </div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon-wrap purple"><i class="fa-solid fa-calendar"></i></div>
        <div>
            <div class="stat-value">${{ number_format($stats['this_month'], 2) }}</div>
            <div class="stat-label">This Month Earning</div>
        </div>
    </div>
    <div class="stat-card teal">
        <div class="stat-icon-wrap teal"><i class="fa-solid fa-star"></i></div>
        <div>
            <div class="stat-value">{{ $stats['rating'] }}</div>
            <div class="stat-label">Rating ({{ $stats['total_reviews'] }} reviews)</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">
    {{-- Revenue chart --}}
    <div class="card">
        <div class="card-header">
            <div class="card-header-title">
                <div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);"><i class="fa-solid fa-chart-line"></i></div>
                Last 7 Days Revenue
            </div>
        </div>
        <div class="card-body" style="padding:16px 20px 12px;">
            <canvas id="revenueChart" height="90"></canvas>
        </div>
    </div>

    {{-- Store info --}}
    <div class="card">
        <div class="card-header">
            <div class="card-header-title">
                <div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);"><i class="fa-solid fa-store"></i></div>
                Store Info
            </div>
            <a href="{{ route('vendor.store.index') }}" class="btn btn-outline btn-xs"><i class="fa-solid fa-pen"></i> Edit</a>
        </div>
        <div class="card-body">
            @if($vendor->logo_url)
            <img src="{{ $vendor->logo_url }}" alt="logo" style="width:60px;height:60px;border-radius:12px;object-fit:cover;margin-bottom:12px;">
            @endif
            <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:4px;">{{ $vendor->name }}</div>
            <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:12px;">{{ $vendor->module?->name ?? ucfirst($vendor->module_slug ?? '') }}</div>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <div style="font-size:12.5px;"><i class="fa-solid fa-location-dot" style="color:var(--text-muted);width:16px;"></i> {{ $vendor->address ?? '—' }}</div>
                <div style="font-size:12.5px;"><i class="fa-solid fa-phone" style="color:var(--text-muted);width:16px;"></i> {{ $vendor->phone ?? '—' }}</div>
                <div style="font-size:12.5px;"><i class="fa-solid fa-envelope" style="color:var(--text-muted);width:16px;"></i> {{ $vendor->email ?? '—' }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Earnings Breakdown --}}
<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="fa-solid fa-coins"></i></div>
            Earnings Breakdown
        </div>
    </div>
    @if($earnings->isEmpty())
    <div class="empty-state"><i class="fa-solid fa-coins"></i><p>No earnings yet</p></div>
    @else
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th style="text-align:right">Order Total</th>
                    <th style="text-align:right">Subtotal</th>
                    <th style="text-align:right">Commission</th>
                    <th style="text-align:right">Your Earning</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($earnings as $e)
                @php
                    $comm = (float)($e->commission ?? 0);
                    $earn = (float)$e->subtotal - $comm;
                    $cls = match($e->status) { 'pending'=>'warning','confirmed'=>'info','delivered'=>'success','cancelled'=>'danger',default=>'neutral' };
                @endphp
                <tr>
                    <td><a href="{{ route('vendor.orders.show', $e->id) }}" style="font-weight:700;color:var(--brand);">#{{ $e->order_number }}</a></td>
                    <td style="text-align:right;color:var(--text-muted);">${{ number_format($e->total_amount, 2) }}</td>
                    <td style="text-align:right;">${{ number_format($e->subtotal, 2) }}</td>
                    <td style="text-align:right;color:#ef4444;font-weight:600;">-${{ number_format($comm, 2) }}</td>
                    <td style="text-align:right;color:#10b981;font-weight:700;">${{ number_format($earn, 2) }}</td>
                    <td><span class="badge badge-{{ $cls }}">{{ ucfirst(str_replace('_',' ',$e->status)) }}</span></td>
                    <td style="color:var(--text-muted);font-size:12px;">{{ $e->created_at->format('d M H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                @php
                    $totalSub = $earnings->sum('subtotal');
                    $totalComm = $earnings->sum('commission');
                    $totalEarn = $totalSub - $totalComm;
                @endphp
                <tr style="background:var(--bg-muted);font-weight:800;">
                    <td colspan="2" style="text-align:right;">Totals</td>
                    <td style="text-align:right;">${{ number_format($totalSub, 2) }}</td>
                    <td style="text-align:right;color:#ef4444;">-${{ number_format($totalComm, 2) }}</td>
                    <td style="text-align:right;color:#10b981;">${{ number_format($totalEarn, 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif
</div>

{{-- Recent orders --}}
<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);"><i class="fa-solid fa-receipt"></i></div>
            Recent Orders
        </div>
        <a href="{{ route('vendor.orders.index') }}" class="btn btn-outline btn-sm">View All</a>
    </div>
    @if($recentOrders->isEmpty())
    <div class="empty-state"><i class="fa-solid fa-bag-shopping"></i><p>No orders yet</p></div>
    @else
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentOrders as $order)
                <tr>
                    <td><strong>#{{ $order->order_number }}</strong></td>
                    <td>{{ $order->user?->name ?? '—' }}</td>
                    <td>{{ $order->items->count() }} items</td>
                    <td><strong>${{ number_format($order->total_amount, 2) }}</strong></td>
                    <td>
                        @php
                        $cls = match($order->status) {
                            'pending'          => 'warning',
                            'confirmed'        => 'info',
                            'ready_for_pickup' => 'purple',
                            'picked_up','out_for_delivery' => 'orange',
                            'delivered'        => 'success',
                            'cancelled'        => 'danger',
                            default            => 'neutral'
                        };
                        @endphp
                        <span class="badge badge-{{ $cls }}">{{ ucfirst(str_replace('_',' ',$order->status)) }}</span>
                    </td>
                    <td style="color:var(--text-muted);font-size:12px;">{{ $order->created_at->diffForHumans() }}</td>
                    <td><a href="{{ route('vendor.orders.show', $order) }}" class="btn btn-outline btn-xs">View</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const chartData = @json($chartData);
const labels  = chartData.map(d => d.date);
const revenue = chartData.map(d => parseFloat(d.revenue || 0));
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels,
        datasets: [{
            label: 'Revenue ($)',
            data: revenue,
            borderColor: '#FF8A00',
            backgroundColor: 'rgba(255,138,0,0.08)',
            borderWidth: 2.5,
            pointBackgroundColor: '#FF8A00',
            pointRadius: 4,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 11 } } },
            y: { grid: { color: '#f0f2f8' }, ticks: { font: { size: 11 }, callback: v => '$'+v } }
        }
    }
});
</script>
@endpush
