@extends('vendor.layouts.app')
@section('title', 'Order ' . $order->order_no)
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-receipt" style="color:var(--brand)"></i> Order {{ $order->order_no }}</h1>
        <ul class="breadcrumb"><li>eSahlan</li><li>eWholesale</li><li><a href="{{ route('vendor.wholesale.orders') }}">Orders</a></li><li>{{ $order->order_no }}</li></ul>
    </div>
</div>

@if(session('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;">

    {{-- Left --}}
    <div>
        {{-- Order Items --}}
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><h2 class="card-title">Order Items</h2></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                        <tr>
                            <td>{{ $item->product?->name ?? 'Product #' . $item->product_id }}</td>
                            <td>{{ $item->quantity }} {{ $item->unit }}</td>
                            <td>${{ number_format($item->unit_price, 2) }}</td>
                            <td>${{ number_format($item->subtotal, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align:right;font-weight:600;">Subtotal</td>
                            <td>${{ number_format($order->subtotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" style="text-align:right;font-weight:600;">Delivery Fee</td>
                            <td>${{ number_format($order->delivery_fee, 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" style="text-align:right;font-weight:700;font-size:15px;">Total</td>
                            <td style="font-weight:700;font-size:15px;">${{ number_format($order->total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Buyer Note --}}
        @if($order->buyer_note)
        <div class="card">
            <div class="card-header"><h2 class="card-title">Buyer Note</h2></div>
            <div class="card-body">
                <p style="color:var(--text-muted);">{{ $order->buyer_note }}</p>
            </div>
        </div>
        @endif
    </div>

    {{-- Right --}}
    <div>
        {{-- Status Card --}}
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><h2 class="card-title">Order Status</h2></div>
            <div class="card-body">
                <div style="margin-bottom:12px;">
                    <span class="badge badge-{{ \App\Models\EWholesale\EWOrder::statusColor($order->status) }}" style="font-size:14px;padding:6px 12px;">
                        {{ ucwords(str_replace('_', ' ', $order->status)) }}
                    </span>
                </div>

                @php
                    $nextStatuses = match($order->status) {
                        'pending_confirmation' => ['confirmed', 'cancelled'],
                        'confirmed'            => ['processing', 'cancelled'],
                        'processing'           => ['ready'],
                        'ready'                => ['shipped'],
                        'shipped'              => ['delivered'],
                        'delivered'            => ['completed'],
                        default                => [],
                    };
                @endphp

                @if(count($nextStatuses) > 0)
                <form method="POST" action="{{ route('vendor.wholesale.orders.status', $order->id) }}">
                    @csrf
                    <div style="margin-bottom:10px;">
                        <label class="form-label">Update Status</label>
                        <select name="status" class="form-select">
                            @foreach($nextStatuses as $s)
                            <option value="{{ $s }}">{{ ucwords(str_replace('_', ' ', $s)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="width:100%;">Update Status</button>
                </form>
                @else
                <p style="color:var(--text-muted);font-size:13px;">No further status updates available.</p>
                @endif
            </div>
        </div>

        {{-- Order Info --}}
        <div class="card">
            <div class="card-header"><h2 class="card-title">Order Info</h2></div>
            <div class="card-body">
                <table style="width:100%;font-size:13px;">
                    <tr><td style="color:var(--text-muted);padding:4px 0;">Buyer</td><td style="font-weight:600;">{{ $order->buyer?->business_name ?? 'N/A' }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:4px 0;">Payment</td><td>{{ strtoupper($order->payment_method ?? 'waafi') }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:4px 0;">Plan</td><td>{{ ucfirst($order->payment_plan ?? 'prepaid') }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:4px 0;">Fulfillment</td><td>{{ ucfirst($order->fulfillment ?? 'delivery') }}</td></tr>
                    <tr><td style="color:var(--text-muted);padding:4px 0;">Placed</td><td>{{ $order->created_at->format('M d, Y H:i') }}</td></tr>
                    @if($order->confirmed_at)
                    <tr><td style="color:var(--text-muted);padding:4px 0;">Confirmed</td><td>{{ $order->confirmed_at->format('M d, Y H:i') }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
