@extends('vendor.layouts.app')
@section('title', 'Earnings')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Earnings</h1>
        <ul class="breadcrumb"><li><a href="{{ route('vendor.dashboard') }}">Dashboard</a></li><li>Earnings</li></ul>
    </div>
</div>

{{-- Summary cards --}}
<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card green">
        <div class="stat-icon-wrap green"><i class="fa-solid fa-coins"></i></div>
        <div>
            <div class="stat-value">${{ number_format($summary['total_earning'], 2) }}</div>
            <div class="stat-label">Total Earning</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon-wrap blue"><i class="fa-solid fa-receipt"></i></div>
        <div>
            <div class="stat-value">${{ number_format($summary['total_subtotal'], 2) }}</div>
            <div class="stat-label">Total Sales</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon-wrap red"><i class="fa-solid fa-percent"></i></div>
        <div>
            <div class="stat-value">${{ number_format($summary['total_commission'], 2) }}</div>
            <div class="stat-label">Commission Paid</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon-wrap orange"><i class="fa-solid fa-bag-shopping"></i></div>
        <div>
            <div class="stat-value">{{ $summary['order_count'] }}</div>
            <div class="stat-label">Orders</div>
        </div>
    </div>
</div>

{{-- Date filter --}}
<div class="card" style="padding:12px 16px;margin-bottom:16px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <label style="font-size:12px;font-weight:600;color:var(--text-muted);">From</label>
        <input type="date" name="from" class="form-control" style="width:160px;" value="{{ request('from') }}">
        <label style="font-size:12px;font-weight:600;color:var(--text-muted);">To</label>
        <input type="date" name="to" class="form-control" style="width:160px;" value="{{ request('to') }}">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
        @if(request('from') || request('to'))
        <a href="{{ route('vendor.earnings') }}" class="btn btn-outline btn-sm">Clear</a>
        @endif
    </form>
</div>

{{-- Transactions table --}}
<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="fa-solid fa-list"></i></div>
            Order Earnings
        </div>
    </div>
    @if($orders->isEmpty())
    <div class="empty-state"><i class="fa-solid fa-coins"></i><p>No orders in this period</p></div>
    @else
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th style="text-align:right">Customer Paid</th>
                    <th style="text-align:right">Subtotal</th>
                    <th style="text-align:right">Delivery Fee</th>
                    <th style="text-align:right">Commission</th>
                    <th style="text-align:right">Your Earning</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orders as $o)
                @php
                    $comm = (float)($o->commission ?? 0);
                    $earn = (float)$o->subtotal - $comm;
                    $cls = match($o->status) { 'pending'=>'warning','confirmed'=>'info','delivered'=>'success','cancelled'=>'danger',default=>'neutral' };
                @endphp
                <tr>
                    <td><a href="{{ route('vendor.orders.show', $o->id) }}" style="font-weight:700;color:var(--brand);">#{{ $o->order_number }}</a></td>
                    <td style="text-align:right;color:var(--text-muted);">${{ number_format($o->total_amount, 2) }}</td>
                    <td style="text-align:right;">${{ number_format($o->subtotal, 2) }}</td>
                    <td style="text-align:right;color:var(--text-muted);">${{ number_format($o->delivery_fee, 2) }}</td>
                    <td style="text-align:right;color:#ef4444;font-weight:600;">-${{ number_format($comm, 2) }}</td>
                    <td style="text-align:right;color:#10b981;font-weight:700;font-size:14px;">${{ number_format($earn, 2) }}</td>
                    <td><span class="badge badge-{{ $cls }}">{{ ucfirst(str_replace('_',' ',$o->status)) }}</span></td>
                    <td style="color:var(--text-muted);font-size:12px;">{{ $o->created_at->format('d M Y H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
