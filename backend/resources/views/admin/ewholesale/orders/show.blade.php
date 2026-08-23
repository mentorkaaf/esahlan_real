@extends('admin.layouts.app')
@section('title', 'Order — ' . $order->order_no)

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
    <a href="{{ route('admin.module-data.wholesale.orders') }}" style="color:#6b7280;text-decoration:none;font-size:13px">← Orders</a>
    <h2 style="margin:0;font-size:18px;font-weight:700;color:#1B1444;font-family:monospace">{{ $order->order_no }}</h2>
    <span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:#f3f4f6;color:#374151">{{ str_replace('_',' ',strtoupper($order->status)) }}</span>
    <div style="margin-left:auto;display:flex;align-items:center;gap:10px;">
        <span style="font-size:13px;color:#6b7280">{{ $order->created_at->format('M d, Y H:i') }}</span>
        <a href="{{ route('admin.module-data.wholesale.orders.print', $order) }}" target="_blank"
           style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:#1B1444;
                  color:#fff;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700;">
            <i class="fas fa-print"></i> Picking Slip
        </a>
    </div>
</div>

@if(session('success'))<div style="background:#d1fae5;color:#065f46;padding:10px 16px;border-radius:6px;margin-bottom:16px">{{ session('success') }}</div>@endif
@if(session('error'))<div style="background:#fee2e2;color:#991b1b;padding:10px 16px;border-radius:6px;margin-bottom:16px">{{ session('error') }}</div>@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

{{-- Summary --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444;border-bottom:1px solid #f3f4f6;padding-bottom:10px">Order Summary</h3>
    @foreach([
        ['Buyer',         $order->buyer?->business_name.' ('.$order->buyer?->user?->name.')'],
        ['Supplier',      $order->supplier?->display_name ?? '—'],
        ['Source',        strtoupper($order->source)],
        ['Payment Plan',  strtoupper($order->payment_plan)],
        ['Subtotal',      '$'.number_format($order->subtotal,2)],
        ['Delivery Fee',  '$'.number_format($order->delivery_fee,2)],
        ['Platform Fee',  '$'.number_format($order->platform_fee,2)],
        ['Total',         '$'.number_format($order->total,2)],
        ['Paid',          '$'.number_format($order->paid_total,2)],
        ['Balance Due',   '$'.number_format($order->balanceDue(),2)],
        ['Expected',      $order->expected_at?->format('M d, Y') ?? '—'],
    ] as [$k,$v])
    <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:13px;border-bottom:1px solid #f9fafb">
        <span style="color:#6b7280">{{ $k }}</span>
        <span style="color:#1B1444;font-weight:{{ in_array($k,['Total','Balance Due'])?'700':'500' }};
            color:{{ $k==='Balance Due'&&$order->balanceDue()>0?'#ef4444':'#1B1444' }}">{{ $v }}</span>
    </div>
    @endforeach
</div>

{{-- State machine transitions --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444;border-bottom:1px solid #f3f4f6;padding-bottom:10px">Update Status</h3>
    @php
    $allowed = [
        'pending_confirmation' => ['confirmed','cancelled'],
        'confirmed'            => ['awaiting_payment','cancelled'],
        'awaiting_payment'     => ['processing','cancelled'],
        'processing'           => ['ready'],
        'ready'                => ['partially_shipped','shipped'],
        'partially_shipped'    => ['shipped'],
        'shipped'              => ['delivered'],
        'delivered'            => ['completed','disputed'],
    ];
    $transitions = $allowed[$order->status] ?? [];
    @endphp
    @if($transitions)
    <form method="POST" action="{{ route('admin.module-data.wholesale.orders.status', $order) }}">
        @csrf @method('PATCH')
        <div style="display:flex;flex-direction:column;gap:8px">
            @foreach($transitions as $t)
            <button name="status" value="{{ $t }}" style="padding:9px;background:{{ $t==='cancelled'?'#ef4444':($t==='completed'?'#10b981':'#1B1444') }};color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer;text-align:left">→ {{ str_replace('_',' ',ucfirst($t)) }}</button>
            @endforeach
        </div>
    </form>
    @else
    <p style="font-size:13px;color:#9ca3af;margin:0">No further transitions available for status <strong>{{ $order->status }}</strong>.</p>
    @endif
</div>
</div>

{{-- Items --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-bottom:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444">Order Items</h3>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead><tr style="border-bottom:2px solid #f3f4f6">
        <th style="padding:8px;text-align:left;color:#6b7280;font-weight:500">Product</th>
        <th style="padding:8px;text-align:right;color:#6b7280;font-weight:500">Unit Price</th>
        <th style="padding:8px;text-align:right;color:#6b7280;font-weight:500">Qty</th>
        <th style="padding:8px;text-align:right;color:#6b7280;font-weight:500">Shipped</th>
        <th style="padding:8px;text-align:right;color:#6b7280;font-weight:500">Line Total</th>
    </tr></thead>
    <tbody>
    @foreach($order->items as $item)
    <tr style="border-bottom:1px solid #f9fafb">
        <td style="padding:8px;font-weight:500;color:#1B1444">{{ $item->name_snapshot }}</td>
        <td style="padding:8px;text-align:right">${{ $item->unit_price_snapshot }}</td>
        <td style="padding:8px;text-align:right">{{ $item->qty }} {{ $item->unit_snapshot }}</td>
        <td style="padding:8px;text-align:right;color:{{ $item->shipped_qty>=$item->qty?'#10b981':'#f59e0b' }}">{{ $item->shipped_qty }}</td>
        <td style="padding:8px;text-align:right;font-weight:600">${{ number_format($item->line_total,2) }}</td>
    </tr>
    @endforeach
    </tbody>
    </table>
    </div>
</div>

{{-- Payments + Record Payment --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444">Payments</h3>
    @forelse($order->payments as $pay)
    <div style="display:flex;justify-content:space-between;padding:8px 12px;background:#f9fafb;border-radius:6px;margin-bottom:6px;font-size:12px">
        <div>
            <div style="font-weight:600;color:#374151">{{ strtoupper($pay->type) }} · {{ strtoupper($pay->method) }}</div>
            @if($pay->ref) <div style="color:#9ca3af">Ref: {{ $pay->ref }}</div> @endif
            @if($pay->note) <div style="color:#6b7280">{{ $pay->note }}</div> @endif
        </div>
        <div style="text-align:right">
            <div style="font-weight:700;color:#10b981;font-size:14px">${{ number_format($pay->amount,2) }}</div>
            <div style="color:#9ca3af">{{ $pay->paid_at?->format('M d') }}</div>
        </div>
    </div>
    @empty
    <p style="font-size:13px;color:#9ca3af;margin:0">No payments recorded.</p>
    @endforelse
</div>

<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444">Record Payment</h3>
    <form method="POST" action="{{ route('admin.module-data.wholesale.orders.payment', $order) }}">
        @csrf
        <div style="display:flex;gap:8px;margin-bottom:8px">
            <select name="type" style="flex:1;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:12px">
                @foreach(['deposit','balance','full','credit_settlement','refund'] as $t)
                <option value="{{ $t }}">{{ ucfirst(str_replace('_',' ',$t)) }}</option>
                @endforeach
            </select>
            <select name="method" style="flex:1;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:12px">
                @foreach(['wallet','evc','cash','bank','credit'] as $m)
                <option value="{{ $m }}">{{ ucfirst($m) }}</option>
                @endforeach
            </select>
        </div>
        <input name="amount" type="number" step="0.01" min="0.01" placeholder="Amount $" required style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;margin-bottom:8px;box-sizing:border-box">
        <input name="ref" placeholder="Reference (optional)" style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;margin-bottom:8px;box-sizing:border-box">
        <input name="note" placeholder="Note (optional)" style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;margin-bottom:10px;box-sizing:border-box">
        <button style="width:100%;padding:8px;background:#10b981;color:#fff;border:none;border-radius:5px;font-size:13px;cursor:pointer;font-weight:600">Record Payment</button>
    </form>
</div>
</div>

{{-- Shipments + Create Shipment --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444">Shipments</h3>
    @forelse($order->shipments as $ship)
    <div style="padding:10px 12px;background:#f9fafb;border-radius:6px;margin-bottom:6px;font-size:12px">
        <div style="display:flex;justify-content:space-between">
            <span style="font-weight:600;font-family:monospace">{{ $ship->shipment_no }}</span>
            <span style="padding:2px 8px;border-radius:10px;background:{{ $ship->status==='delivered'?'#d1fae5':'#f3f4f6' }};color:{{ $ship->status==='delivered'?'#065f46':'#374151' }};font-size:10px">{{ strtoupper($ship->status) }}</span>
        </div>
        @if($ship->note) <div style="color:#6b7280;margin-top:4px">{{ $ship->note }}</div> @endif
    </div>
    @empty
    <p style="font-size:13px;color:#9ca3af;margin:0">No shipments yet.</p>
    @endforelse
</div>

@if(in_array($order->status, ['confirmed','processing','ready','partially_shipped']))
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444">Create Shipment</h3>
    <form method="POST" action="{{ route('admin.module-data.wholesale.orders.shipment', $order) }}">
        @csrf
        <div style="margin-bottom:12px">
            @foreach($order->items as $idx => $item)
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ $item->id }}">
                <span style="font-size:12px;flex:1;color:#374151">{{ $item->name_snapshot }}</span>
                <span style="font-size:11px;color:#9ca3af">Total: {{ $item->qty }}</span>
                <input name="items[{{ $idx }}][qty]" type="number" step="0.01" min="0" max="{{ $item->qty - $item->shipped_qty }}" placeholder="Ship qty" value="{{ $item->qty - $item->shipped_qty }}" style="width:80px;padding:5px;border:1px solid #d1d5db;border-radius:4px;font-size:12px">
            </div>
            @endforeach
        </div>
        <input name="note" placeholder="Shipment note" style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;margin-bottom:10px;box-sizing:border-box">
        <button style="width:100%;padding:8px;background:#8b5cf6;color:#fff;border:none;border-radius:5px;font-size:13px;cursor:pointer;font-weight:600">Create Shipment</button>
    </form>
</div>
@endif
</div>

{{-- Disputes --}}
@if($order->disputes->isNotEmpty())
<div style="background:#fff;border:1px solid #fecaca;border-radius:10px;padding:20px;margin-bottom:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#991b1b">Disputes</h3>
    @foreach($order->disputes as $d)
    <div style="padding:12px;background:#fef2f2;border-radius:8px;margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;margin-bottom:8px">
            <span style="font-weight:600;color:#374151">{{ $d->reason }}</span>
            <span style="font-size:11px;padding:2px 8px;border-radius:10px;background:#fee2e2;color:#991b1b">{{ strtoupper($d->status) }}</span>
        </div>
        <p style="font-size:13px;color:#6b7280;margin:0 0 10px">{{ $d->description }}</p>
        @if(in_array($d->status, ['open','supplier_responded']))
        <form method="POST" action="{{ route('admin.module-data.wholesale.disputes.resolve', $d) }}" style="display:flex;gap:8px;flex-wrap:wrap">
            @csrf
            <select name="action" style="padding:6px 10px;border:1px solid #d1d5db;border-radius:5px;font-size:12px">
                <option value="resolved_refund">Resolve: Refund</option>
                <option value="resolved_release">Resolve: Release</option>
                <option value="closed">Close</option>
            </select>
            <input name="note" placeholder="Resolution note" required style="flex:1;padding:6px 10px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;min-width:180px">
            <button style="padding:6px 14px;background:#ef4444;color:#fff;border:none;border-radius:5px;font-size:12px;cursor:pointer">Resolve</button>
        </form>
        @endif
    </div>
    @endforeach
</div>
@endif

</div>
@endsection
