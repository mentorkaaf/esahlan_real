@extends('admin.layouts.app')
@section('title', 'Global Orders')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">🛒 Global Orders</h1>
        <p class="page-subtitle">USA &amp; Europe eCommerce orders</p>
    </div>
</div>

@if(session('success'))
<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>
@endif

{{-- Filters --}}
<form method="GET" style="background:#fff;border-radius:12px;padding:16px 20px;margin-bottom:16px;border:1px solid #e5e7eb;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:2;min-width:160px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Search</label>
        <input name="search" value="{{ request('search') }}" placeholder="Order# or customer…" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
    </div>
    <div style="flex:1;min-width:130px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Status</label>
        <select name="status" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            <option value="">All</option>
            @foreach(['pending','paid','processing','shipped','delivered','cancelled','refunded','on_hold'] as $s)
            <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>
    <div style="flex:1;min-width:130px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Payment</label>
        <select name="payment" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            <option value="">All</option>
            <option value="paid" {{ request('payment')==='paid'?'selected':'' }}>Paid</option>
            <option value="pending" {{ request('payment')==='pending'?'selected':'' }}>Pending</option>
            <option value="refunded" {{ request('payment')==='refunded'?'selected':'' }}>Refunded</option>
        </select>
    </div>
    <div style="flex:1;min-width:120px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">From</label>
        <input name="from" type="date" value="{{ request('from') }}" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
    </div>
    <div style="flex:1;min-width:120px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">To</label>
        <input name="to" type="date" value="{{ request('to') }}" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
    </div>
    <button type="submit" style="padding:8px 20px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Filter</button>
    @if(request()->hasAny(['search','status','payment','from','to']))
    <a href="{{ route('admin.global.orders.index') }}" style="padding:8px 16px;background:#f3f4f6;color:#374151;border-radius:8px;font-size:13px;text-decoration:none">Clear</a>
    @endif
</form>

@php
$statusColors = [
    'pending'   =>['#f59e0b','#fffbeb'],'paid'      =>['#10b981','#ecfdf5'],
    'processing'=>['#6366f1','#eef2ff'],'shipped'   =>['#3b82f6','#eff6ff'],
    'delivered' =>['#10b981','#ecfdf5'],'cancelled' =>['#ef4444','#fef2f2'],
    'refunded'  =>['#8b5cf6','#f5f3ff'],'on_hold'   =>['#f97316','#fff7ed'],
];
@endphp

<div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);border:1px solid #e5e7eb;overflow:hidden">
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
                <th style="padding:11px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Order</th>
                <th style="padding:11px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Customer</th>
                <th style="padding:11px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Ship to</th>
                <th style="padding:11px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Items</th>
                <th style="padding:11px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Total</th>
                <th style="padding:11px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Status</th>
                <th style="padding:11px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Date</th>
                <th style="padding:11px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            @php $sc = $statusColors[$order->status] ?? ['#9ca3af','#f9fafb']; @endphp
            <tr style="border-top:1px solid #f3f4f6">
                <td style="padding:11px 16px;font-size:12px;font-weight:700;color:#6366f1">
                    <a href="{{ route('admin.global.orders.show',$order) }}" style="color:#6366f1;text-decoration:none">{{ $order->order_number }}</a>
                </td>
                <td style="padding:11px 16px">
                    <div style="font-size:12px;font-weight:600;color:#111">{{ $order->user?->name ?? $order->shipping_name }}</div>
                    <div style="font-size:11px;color:#9ca3af">{{ $order->user?->email }}</div>
                </td>
                <td style="padding:11px 16px;font-size:12px;color:#374151">{{ $order->shipping_city }}, {{ $order->shipping_country }}</td>
                <td style="padding:11px 16px;text-align:center;font-size:12px;color:#374151">{{ $order->items->count() }}</td>
                <td style="padding:11px 16px;text-align:right;font-size:13px;font-weight:700;color:#111">${{ number_format($order->total,2) }}</td>
                <td style="padding:11px 16px;text-align:center">
                    <span style="padding:2px 10px;border-radius:20px;font-size:10px;font-weight:700;color:{{ $sc[0] }};background:{{ $sc[1] }}">{{ strtoupper($order->status) }}</span>
                </td>
                <td style="padding:11px 16px;text-align:center;font-size:11px;color:#9ca3af">{{ $order->created_at->format('M d, Y') }}</td>
                <td style="padding:11px 16px;text-align:right">
                    <a href="{{ route('admin.global.orders.show',$order) }}" style="padding:5px 12px;background:#f3f4f6;color:#374151;border-radius:6px;font-size:11px;font-weight:600;text-decoration:none">View</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="padding:48px;text-align:center;color:#9ca3af">
                <div style="font-size:40px;margin-bottom:12px">🛒</div>
                <div style="font-size:15px;font-weight:600">No orders yet</div>
            </td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
    @if($orders->hasPages())
    <div style="padding:16px 20px;border-top:1px solid #f3f4f6">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
