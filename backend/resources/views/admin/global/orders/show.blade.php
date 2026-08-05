@extends('admin.layouts.app')
@section('title', 'Order '.$order->order_number)

@section('content')
@php
$statusColors = [
    'pending'=>['#f59e0b','#fffbeb'],'paid'=>['#10b981','#ecfdf5'],
    'processing'=>['#6366f1','#eef2ff'],'shipped'=>['#3b82f6','#eff6ff'],
    'delivered'=>['#10b981','#ecfdf5'],'cancelled'=>['#ef4444','#fef2f2'],
    'refunded'=>['#8b5cf6','#f5f3ff'],'on_hold'=>['#f97316','#fff7ed'],
];
$sc = $statusColors[$order->status] ?? ['#9ca3af','#f9fafb'];
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">Order {{ $order->order_number }}</h1>
        <p class="page-subtitle">{{ $order->created_at->format('F d, Y g:i A') }}</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
        <span style="padding:4px 14px;border-radius:20px;font-size:11px;font-weight:800;color:{{ $sc[0] }};background:{{ $sc[1] }}">{{ strtoupper($order->status) }}</span>
        <a href="{{ route('admin.global.orders.index') }}" style="color:#6b7280;text-decoration:none;font-size:13px">← Orders</a>
    </div>
</div>

@if(session('success'))
<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">❌ {{ session('error') }}</div>
@endif

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start">

{{-- Left --}}
<div style="display:flex;flex-direction:column;gap:16px">

    {{-- Items --}}
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6">
            <h3 style="font-size:14px;font-weight:700;color:#111">Order Items ({{ $order->items->count() }})</h3>
        </div>
        @foreach($order->items as $item)
        <div style="display:flex;gap:14px;padding:14px 20px;border-top:1px solid #f3f4f6;align-items:center">
            @if($item->product?->thumbnail)
            <img src="{{ $item->product->thumbnail }}" style="width:52px;height:52px;border-radius:8px;object-fit:cover;border:1px solid #e5e7eb" alt="">
            @else
            <div style="width:52px;height:52px;border-radius:8px;background:#f3f4f6;display:flex;align-items:center;justify-content:center"><i class="fas fa-box" style="color:#d1d5db"></i></div>
            @endif
            <div style="flex:1">
                <div style="font-size:13px;font-weight:600;color:#111">{{ $item->product_name }}</div>
                @if($item->variant_label)<div style="font-size:11px;color:#9ca3af">{{ $item->variant_label }}</div>@endif
                <div style="font-size:12px;color:#374151;margin-top:2px">Qty: {{ $item->quantity }} × ${{ number_format($item->unit_price,2) }}</div>
            </div>
            <div style="font-size:14px;font-weight:700;color:#111">${{ number_format($item->total_price,2) }}</div>
        </div>
        @endforeach
        {{-- Totals --}}
        <div style="padding:16px 20px;background:#f9fafb;border-top:2px solid #e5e7eb">
            @foreach([['Subtotal','subtotal'],['Shipping','shipping_cost'],['Tax','tax_amount'],['Discount','discount_amount']] as [$l,$f])
            @if($order->$f > 0 || $f === 'subtotal')
            <div style="display:flex;justify-content:space-between;font-size:13px;color:#374151;margin-bottom:6px">
                <span>{{ $l }}</span><span{{ $f==='discount_amount'?' style="color:#10b981"':'' }}>${{ number_format($order->$f,2) }}</span>
            </div>
            @endif
            @endforeach
            <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:800;color:#111;border-top:1px solid #e5e7eb;padding-top:10px;margin-top:6px">
                <span>Total</span><span>${{ number_format($order->total,2) }}</span>
            </div>
        </div>
    </div>

    {{-- Update Status --}}
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px">
        <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">Update Order</h3>
        <form method="POST" action="{{ route('admin.global.orders.status', $order) }}">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Status</label>
                    <select name="status" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
                        @foreach(['pending','paid','processing','shipped','delivered','cancelled','refunded','on_hold'] as $s)
                        <option value="{{ $s }}" {{ $order->status===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Fulfillment</label>
                    <select name="fulfillment_status" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
                        @foreach(['unfulfilled','partial','fulfilled','shipped','delivered'] as $s)
                        <option value="{{ $s }}" {{ $order->fulfillment_status===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Tracking Number</label>
                    <input name="tracking_number" value="{{ $order->tracking_number }}" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Carrier</label>
                    <input name="shipping_carrier" value="{{ $order->shipping_carrier }}" placeholder="USPS, FedEx, DHL…" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
                </div>
                <div style="grid-column:span 2">
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Tracking URL</label>
                    <input name="tracking_url" type="url" value="{{ $order->tracking_url }}" placeholder="https://track…" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
                </div>
                <div style="grid-column:span 2">
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Admin Notes</label>
                    <textarea name="admin_notes" rows="2" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;resize:vertical">{{ $order->admin_notes }}</textarea>
                </div>
            </div>
            <button type="submit" style="margin-top:14px;padding:10px 24px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">Save Changes</button>
        </form>
    </div>

    {{-- Refund --}}
    @if($order->payment && $order->payment->status !== 'refunded')
    <div style="background:#fff;border-radius:12px;border:1px solid #fca5a5;padding:24px">
        <h3 style="font-size:14px;font-weight:700;color:#dc2626;margin-bottom:16px">💸 Process Refund</h3>
        <form method="POST" action="{{ route('admin.global.orders.refund', $order) }}" onsubmit="return confirm('Process refund?')">
            @csrf
            <div style="display:flex;gap:12px;align-items:flex-end">
                <div style="flex:1">
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Amount (leave blank for full refund)</label>
                    <input name="amount" type="number" step="0.01" min="0.01" max="{{ $order->total }}" placeholder="{{ $order->total }}" style="width:100%;padding:9px 12px;border:1px solid #fca5a5;border-radius:8px;font-size:13px">
                </div>
                <button type="submit" style="padding:9px 20px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap">Refund</button>
            </div>
            <p style="margin-top:8px;font-size:11px;color:#9ca3af">Payment via {{ strtoupper($order->payment->method ?? '—') }} • {{ strtoupper($order->payment->status) }}</p>
        </form>
    </div>
    @endif
</div>

{{-- Right --}}
<div style="display:flex;flex-direction:column;gap:16px">
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:20px">
        <h3 style="font-size:13px;font-weight:700;color:#111;margin-bottom:14px">👤 Customer</h3>
        <div style="font-size:13px;font-weight:600;color:#111">{{ $order->user?->name ?? $order->shipping_name }}</div>
        <div style="font-size:12px;color:#6b7280">{{ $order->user?->email }}</div>
        @if($order->user)
        <a href="#" style="display:inline-block;margin-top:10px;font-size:12px;color:#6366f1">View profile →</a>
        @endif
    </div>

    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:20px">
        <h3 style="font-size:13px;font-weight:700;color:#111;margin-bottom:14px">📦 Shipping Address</h3>
        <div style="font-size:13px;color:#374151;line-height:1.6">
            <div style="font-weight:600">{{ $order->shipping_name }}</div>
            @if($order->shipping_company)<div>{{ $order->shipping_company }}</div>@endif
            <div>{{ $order->shipping_address_line1 }}</div>
            @if($order->shipping_address_line2)<div>{{ $order->shipping_address_line2 }}</div>@endif
            <div>{{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}</div>
            <div>{{ $order->shipping_country }}</div>
            @if($order->shipping_phone)<div style="color:#9ca3af;margin-top:4px">{{ $order->shipping_phone }}</div>@endif
        </div>
    </div>

    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:20px">
        <h3 style="font-size:13px;font-weight:700;color:#111;margin-bottom:14px">💳 Payment</h3>
        @if($order->payment)
        @php $p=$order->payment; @endphp
        <div style="display:flex;flex-direction:column;gap:6px;font-size:12px;color:#374151">
            <div style="display:flex;justify-content:space-between"><span>Method</span><strong>{{ strtoupper($p->method) }}</strong></div>
            <div style="display:flex;justify-content:space-between"><span>Status</span>
                <span style="font-weight:700;color:{{ $p->status==='paid'?'#10b981':($p->status==='refunded'?'#8b5cf6':'#f59e0b') }}">{{ strtoupper($p->status) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between"><span>Amount</span><strong>${{ number_format($p->amount,2) }}</strong></div>
            @if($p->paid_at)<div style="display:flex;justify-content:space-between"><span>Paid at</span><span>{{ \Carbon\Carbon::parse($p->paid_at)->format('M d, g:i A') }}</span></div>@endif
        </div>
        @else
        <p style="font-size:12px;color:#9ca3af">No payment recorded</p>
        @endif
    </div>

    @if($order->tracking_number)
    <div style="background:#eff6ff;border-radius:12px;border:1px solid #bfdbfe;padding:20px">
        <h3 style="font-size:13px;font-weight:700;color:#1d4ed8;margin-bottom:10px">🚚 Tracking</h3>
        <div style="font-size:12px;color:#374151">
            <div><strong>{{ $order->shipping_carrier ?? 'Carrier' }}:</strong> {{ $order->tracking_number }}</div>
            @if($order->tracking_url)<a href="{{ $order->tracking_url }}" target="_blank" style="color:#3b82f6;font-size:12px">Track shipment →</a>@endif
            @if($order->shipped_at)<div style="color:#9ca3af;margin-top:4px">Shipped: {{ \Carbon\Carbon::parse($order->shipped_at)->format('M d, Y') }}</div>@endif
        </div>
    </div>
    @endif
</div>

</div>
@endsection
