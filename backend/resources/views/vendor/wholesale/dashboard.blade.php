@extends('vendor.layouts.app')
@section('title', 'Supplier Dashboard')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-warehouse" style="color:var(--brand)"></i> Supplier Dashboard</h1>
        <ul class="breadcrumb"><li>eSahlan</li><li>eWholesale</li><li>Dashboard</li></ul>
    </div>
</div>

@if(session('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif

{{-- Stats Grid --}}
<div class="stats-grid">
    <div class="stat-card orange">
        <div class="stat-icon-wrap orange"><i class="fa-solid fa-receipt"></i></div>
        <div>
            <div class="stat-value">{{ $stats['today_orders'] }}</div>
            <div class="stat-label">Today's Orders</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon-wrap green"><i class="fa-solid fa-dollar-sign"></i></div>
        <div>
            <div class="stat-value">${{ number_format($stats['today_revenue'], 2) }}</div>
            <div class="stat-label">Today's Revenue</div>
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
        <div class="stat-icon-wrap blue"><i class="fa-solid fa-boxes-stacked"></i></div>
        <div>
            <div class="stat-value">{{ $stats['active_products'] }}</div>
            <div class="stat-label">Active Products</div>
        </div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon-wrap purple"><i class="fa-solid fa-file-invoice"></i></div>
        <div>
            <div class="stat-value">{{ $stats['pending_rfqs'] }}</div>
            <div class="stat-label">Pending RFQs</div>
        </div>
    </div>
    <div class="stat-card teal">
        <div class="stat-icon-wrap teal"><i class="fa-solid fa-coins"></i></div>
        <div>
            <div class="stat-value">${{ number_format($stats['total_revenue'], 2) }}</div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>
</div>

{{-- Recent Orders --}}
<div class="card" style="margin-top:24px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h2 class="card-title">Recent Orders</h2>
        <a href="{{ route('vendor.wholesale.orders') }}" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Buyer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentOrders as $order)
                <tr>
                    <td><strong>{{ $order->order_no }}</strong></td>
                    <td>{{ $order->buyer?->business_name ?? 'N/A' }}</td>
                    <td>${{ number_format($order->total, 2) }}</td>
                    <td>
                        <span class="badge badge-{{ \App\Models\EWholesale\EWOrder::statusColor($order->status) }}">
                            {{ ucwords(str_replace('_', ' ', $order->status)) }}
                        </span>
                    </td>
                    <td>{{ $order->created_at->format('M d, Y') }}</td>
                    <td>
                        <a href="{{ route('vendor.wholesale.orders.show', $order->id) }}" class="btn btn-xs btn-outline">View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
