@extends('vendor.layouts.app')
@section('title', 'Order #'.$order->order_number)

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Order #{{ $order->order_number }}</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('vendor.dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('vendor.orders.index') }}">Orders</a></li>
            <li>#{{ $order->order_number }}</li>
        </ul>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @if($order->status === 'pending')
        <form action="{{ route('vendor.orders.accept',$order) }}" method="POST" style="margin:0;">
            @csrf
            <button type="submit" class="btn btn-success"><i class="fa-solid fa-check"></i> Accept Order</button>
        </form>
        <button onclick="openModal('reject-modal')" class="btn btn-danger"><i class="fa-solid fa-xmark"></i> Reject</button>
        @elseif($order->status === 'confirmed')
        <form action="{{ route('vendor.orders.ready',$order) }}" method="POST" style="margin:0;">
            @csrf
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check-double"></i> Mark as Ready</button>
        </form>
        <button onclick="openModal('reject-modal')" class="btn btn-danger"><i class="fa-solid fa-xmark"></i> Cancel</button>
        @endif
        <a href="{{ route('vendor.orders.index') }}" class="btn btn-outline"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">
    <div>
        {{-- Order Items --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-title"><div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);"><i class="fa-solid fa-list"></i></div> Order Items</div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
                    <tbody>
                        @foreach($order->items as $item)
                        <tr>
                            <td>
                                <div style="font-weight:600;">{{ $item->product_name ?? $item->name }}</div>
                                @if($item->variant_label ?? false)
                                <div style="font-size:11.5px;color:var(--text-muted);">{{ $item->variant_label }}</div>
                                @endif
                            </td>
                            <td>${{ number_format($item->price,2) }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td><strong>${{ number_format($item->price * $item->quantity,2) }}</strong></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <div style="display:flex;flex-direction:column;gap:6px;max-width:280px;margin-left:auto;">
                    <div style="display:flex;justify-content:space-between;font-size:13px;"><span style="color:var(--text-muted);">Subtotal</span><span>${{ number_format($order->subtotal ?? $order->total_amount ?? 0,2) }}</span></div>
                    @if(($order->delivery_fee ?? 0) > 0)
                    <div style="display:flex;justify-content:space-between;font-size:13px;"><span style="color:var(--text-muted);">Delivery Fee</span><span>${{ number_format($order->delivery_fee,2) }}</span></div>
                    @endif
                    @if(($order->discount_amount ?? 0) > 0)
                    <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--success);"><span>Discount</span><span>-${{ number_format($order->discount_amount,2) }}</span></div>
                    @endif
                    <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:800;border-top:1px solid var(--border);padding-top:8px;margin-top:4px;">
                        <span>Total</span><span style="color:var(--brand);">${{ number_format($order->total_amount,2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Status History --}}
        @if($order->statusHistory && $order->statusHistory->count())
        <div class="card">
            <div class="card-header">
                <div class="card-header-title"><div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);"><i class="fa-solid fa-clock-rotate-left"></i></div> Status History</div>
            </div>
            <div class="card-body" style="padding:16px 20px;">
                <div style="display:flex;flex-direction:column;gap:10px;">
                    @foreach($order->statusHistory->sortByDesc('created_at') as $h)
                    <div style="display:flex;gap:12px;align-items:flex-start;">
                        <div style="width:8px;height:8px;border-radius:50%;background:var(--brand);margin-top:5px;flex-shrink:0;"></div>
                        <div>
                            <div style="font-weight:700;font-size:13px;">{{ ucfirst(str_replace('_',' ',$h->status)) }}</div>
                            @if($h->note)<div style="font-size:12px;color:var(--text-muted);">{{ $h->note }}</div>@endif
                            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $h->created_at->format('d M Y, H:i') }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>

    <div>
        {{-- Order summary --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-title"><div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);"><i class="fa-solid fa-info-circle"></i></div> Order Details</div>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:14px;">
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:4px;">Status</div>
                    @php $cls = match($order->status) { 'pending'=>'warning','confirmed'=>'info','ready_for_pickup'=>'purple','delivered'=>'success','cancelled'=>'danger',default=>'neutral' }; @endphp
                    <span class="badge badge-{{ $cls }}">{{ ucfirst(str_replace('_',' ',$order->status)) }}</span>
                </div>
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:4px;">Payment</div>
                    <span class="badge badge-neutral">{{ $order->payment_method ?? '—' }}</span>
                    <span class="badge {{ $order->payment_status === 'paid' ? 'badge-success' : 'badge-warning' }}" style="margin-left:4px;">{{ ucfirst($order->payment_status ?? 'pending') }}</span>
                </div>
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:4px;">Order Date</div>
                    <div style="font-size:13px;">{{ $order->created_at->format('d M Y, H:i') }}</div>
                </div>
                @if($order->delivery_address)
                @php
                    $addr = $order->delivery_address;
                    if (is_string($addr)) { $addr = json_decode($addr, true) ?? ['address' => $addr]; }
                    $addrStr = is_array($addr) ? implode(', ', array_filter([$addr['district'] ?? $addr['city'] ?? null, $addr['address'] ?? null, $addr['name'] ?? null])) : (string) $addr;
                @endphp
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:4px;">Delivery Address</div>
                    <div style="font-size:13px;">{{ $addrStr ?: '—' }}</div>
                </div>
                @endif
                @if($order->note || $order->notes)
                @php $noteStr = $order->notes ?? $order->note; if (is_array($noteStr)) $noteStr = json_encode($noteStr); @endphp
                <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:4px;">Note</div>
                    <div style="font-size:13px;color:var(--text-muted);">{{ is_string($noteStr) ? $noteStr : '—' }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Customer --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-title"><div class="card-header-icon" style="background:rgba(139,92,246,0.1);color:var(--purple);"><i class="fa-solid fa-user"></i></div> Customer</div>
            </div>
            <div class="card-body">
                <div style="font-weight:700;font-size:15px;margin-bottom:4px;">{{ $order->user?->name ?? '—' }}</div>
                <div style="font-size:13px;color:var(--text-muted);">{{ $order->user?->phone ?? '' }}</div>
                <div style="font-size:13px;color:var(--text-muted);">{{ $order->user?->email ?? '' }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Reject modal --}}
<div class="modal-overlay" id="reject-modal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Reject / Cancel Order</div>
            <button class="modal-close" onclick="closeModal('reject-modal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form action="{{ route('vendor.orders.reject',$order) }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control" rows="3" placeholder="Enter reason for rejection..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('reject-modal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>
@endsection
