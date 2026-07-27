@extends('vendor.layouts.app')
@section('title', 'eShop Dashboard')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-store" style="color:var(--brand)"></i> eShop Dashboard</h1>
        <ul class="breadcrumb"><li>eSahlan</li><li>eShop Vendor</li><li>Dashboard</li></ul>
    </div>
</div>

@if(session('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif

{{-- Stats Grid --}}
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
            <div class="stat-value">${{ number_format($stats['today_revenue'], 2) }}</div>
            <div class="stat-label">Today's Earnings</div>
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
        <div class="stat-icon-wrap blue"><i class="fa-solid fa-box"></i></div>
        <div>
            <div class="stat-value">{{ $stats['active_products'] }}</div>
            <div class="stat-label">Active Products</div>
        </div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon-wrap purple"><i class="fa-solid fa-wallet"></i></div>
        <div>
            <div class="stat-value">${{ number_format($stats['pending_payout'], 2) }}</div>
            <div class="stat-label">Pending Payout</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon-wrap green"><i class="fa-solid fa-star"></i></div>
        <div>
            <div class="stat-value">{{ $stats['rating'] }} <span style="font-size:14px;font-weight:500">({{ $stats['review_count'] }})</span></div>
            <div class="stat-label">Store Rating</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;margin-top:8px;">
    {{-- Recent Orders --}}
    <div class="card">
        <div class="card-header"><h3 class="card-title">Recent Orders</h3> <a href="{{ route('vendor.eshop.orders') }}" class="btn btn-sm btn-brand">View All</a></div>
        <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Order #</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            @forelse($recentOrders as $o)
            <tr>
                <td><code style="font-weight:700">{{ $o->order_number }}</code></td>
                <td>{{ $o->customer_name ?? '—' }}</td>
                <td style="font-weight:700;color:var(--brand)">${{ number_format($o->total_amount, 2) }}</td>
                <td><span class="badge badge-{{ $o->status }}">{{ ucfirst($o->status) }}</span></td>
                <td style="font-size:11px;color:#888">{{ \Carbon\Carbon::parse($o->created_at)->diffForHumans() }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;color:#aaa;padding:24px">No orders yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- Top Products --}}
    <div class="card">
        <div class="card-header"><h3 class="card-title">Top Products</h3></div>
        <div style="padding:0 16px;">
        @forelse($topProducts as $i => $p)
        <div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #f0f0f0;">
            <span style="font-size:15px;font-weight:900;color:#ddd;min-width:18px;">{{ $i+1 }}</span>
            <div style="width:38px;height:38px;border-radius:8px;overflow:hidden;flex-shrink:0;background:#f3f4f6;">
                <img src="{{ cdn_url($p->thumbnail) ?? '' }}" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:700;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $p->name }}</div>
                <div style="font-size:11px;color:#888">{{ $p->total_qty }} sold · ${{ number_format($p->total_revenue,2) }}</div>
            </div>
        </div>
        @empty
        <div style="text-align:center;color:#aaa;padding:32px 0;font-size:13px;">No sales data yet.</div>
        @endforelse
        </div>
    </div>
</div>

@endsection
