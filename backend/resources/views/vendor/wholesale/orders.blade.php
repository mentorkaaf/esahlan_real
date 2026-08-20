@extends('vendor.layouts.app')
@section('title', 'Orders')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-receipt" style="color:var(--brand)"></i> Orders</h1>
        <ul class="breadcrumb"><li>eSahlan</li><li>eWholesale</li><li>Orders</li></ul>
    </div>
</div>

@if(session('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif

{{-- Status Filter Tabs --}}
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
    @foreach(['all' => 'All', 'pending_confirmation' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label)
    <a href="{{ route('vendor.wholesale.orders', ['status' => $key]) }}"
       class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline' }}">
        {{ $label }}
        @if($key !== 'all' && isset($statusCounts[$key]) && $statusCounts[$key] > 0)
        <span style="background:rgba(255,255,255,0.3);padding:0 5px;border-radius:10px;margin-left:4px;font-size:11px;">{{ $statusCounts[$key] }}</span>
        @endif
    </a>
    @endforeach
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Buyer</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                <tr>
                    <td><strong>{{ $order->order_no }}</strong></td>
                    <td>{{ $order->buyer?->business_name ?? 'N/A' }}</td>
                    <td>{{ $order->items_count ?? '-' }}</td>
                    <td>${{ number_format($order->total, 2) }}</td>
                    <td>{{ strtoupper($order->payment_method ?? 'waafi') }}</td>
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
                <tr><td colspan="8" class="text-center text-muted">No orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
    <div class="card-footer">{{ $orders->withQueryString()->links() }}</div>
    @endif
</div>

@endsection
