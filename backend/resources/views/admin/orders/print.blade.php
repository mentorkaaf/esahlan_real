<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Picking Slip — {{ $order->order_number }}</title>
<style>
@media print { body { margin: 0; } .no-print { display: none !important; } }
body { font-family: Arial, sans-serif; font-size: 13px; color: #111; max-width: 720px; margin: 20px auto; }
h1  { font-size: 20px; margin: 0; }
.header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 16px; border-bottom: 2px solid #111; margin-bottom: 16px; }
.badge-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; background: #FFF8E7; color: #C05800; border: 1px solid #FED7AA; }
table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
thead th { background: #111; color: #fff; padding: 8px 12px; text-align: left; font-size: 12px; }
tbody td { padding: 9px 12px; border-bottom: 1px solid #e5e5e5; }
tbody tr:nth-child(even) td { background: #f9f9f9; }
.check { width: 18px; height: 18px; border: 1.5px solid #999; display: inline-block; border-radius: 3px; }
.totals { float: right; text-align: right; line-height: 1.8; }
.totals-label { color: #666; margin-right: 24px; }
.totals-value { font-weight: 700; min-width: 80px; display: inline-block; text-align: right; }
.bold { font-weight: 800; font-size: 15px; }
.footer { margin-top: 24px; padding-top: 12px; border-top: 1px solid #ddd; font-size: 11px; color: #999; text-align: center; }
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin-bottom: 16px; border: 1px solid #e5e5e5; border-radius: 6px; overflow: hidden; }
.detail-row { display: contents; }
.detail-label { padding: 8px 12px; background: #f9f9f9; color: #666; font-size: 12px; font-weight: 600; border-bottom: 1px solid #e5e5e5; }
.detail-value { padding: 8px 12px; color: #111; font-size: 13px; border-bottom: 1px solid #e5e5e5; }
.section-title { font-size: 11px; font-weight: 800; color: #999; text-transform: uppercase; letter-spacing: 1px; margin: 16px 0 8px; }
</style>
</head>
<body>

@php
    $slug = strtolower($order->module_slug ?? '');
    $notes = [];
    if ($order->notes || $order->note) {
        $raw = $order->notes ?? $order->note ?? '';
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $notes = $decoded;
            }
        } elseif (is_array($raw)) {
            $notes = $raw;
        }
    }
    // Service-based modules: items come from notes JSON, not order_items table
    $serviceModules = ['eparcel','edata','erent','emoving','elaundry','elearning'];
    $isService = in_array($slug, $serviceModules);
@endphp

<div class="no-print" style="background:#fff;padding:10px;margin-bottom:16px;border:1px solid #ddd;border-radius:6px;display:flex;gap:10px;">
    <button onclick="window.print()" style="background:#111;color:#fff;border:none;padding:8px 18px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:700;">🖨 Print</button>
    <a href="{{ route('admin.orders.show', $order) }}" style="color:#374151;text-decoration:none;font-size:13px;padding:8px 14px;background:#f1f5f9;border-radius:6px;">← Back to Order</a>
</div>

<div class="header">
    <div>
        <div style="font-size:11px;color:#999;font-weight:700;letter-spacing:1px;margin-bottom:4px;">PICKING SLIP</div>
        <h1>{{ $order->order_number }}</h1>
        <div style="margin-top:6px;font-size:13px;color:#555;">{{ \Carbon\Carbon::parse($order->placed_at ?? $order->created_at)->format('l, F j, Y — H:i') }}</div>
    </div>
    <div style="text-align:right;">
        <div style="font-size:22px;font-weight:900;letter-spacing:-1px;">eSahlan</div>
        <div style="font-size:11px;color:#999;">{{ ucfirst($slug ?: 'Order') }}</div>
        <div style="margin-top:8px;"><span class="badge-status">{{ strtoupper(str_replace('_',' ',$order->status)) }}</span></div>
    </div>
</div>

{{-- Customer & Payment info --}}
<div style="display:flex;gap:40px;margin-bottom:20px;flex-wrap:wrap;">
    <div>
        <div style="font-size:10px;font-weight:800;color:#999;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Customer</div>
        <div style="font-weight:700;font-size:14px;">{{ $order->user?->name }}</div>
        <div style="color:#555;">{{ $order->user?->phone }}</div>
    </div>
    @if($order->vendor)
    <div>
        <div style="font-size:10px;font-weight:800;color:#999;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Vendor</div>
        <div style="font-weight:700;">{{ $order->vendor?->name }}</div>
    </div>
    @endif
    <div>
        <div style="font-size:10px;font-weight:800;color:#999;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Payment</div>
        <div style="font-weight:700;">{{ strtoupper($order->payment_method ?? '—') }}</div>
        <div style="color:#555;">{{ ucfirst($order->payment_status ?? 'pending') }}</div>
    </div>
</div>

{{-- ===== MODULE-SPECIFIC DETAILS ===== --}}

@if($slug === 'eparcel' && !empty($notes))
<div class="section-title">Parcel Details</div>
<div class="detail-grid">
    <div class="detail-label">Sender Name</div>
    <div class="detail-value">{{ $notes['pickup']['name'] ?? '—' }}</div>
    <div class="detail-label">Sender Phone</div>
    <div class="detail-value">{{ $notes['pickup']['phone'] ?? '—' }}</div>
    <div class="detail-label">Pickup District</div>
    <div class="detail-value">{{ $notes['pickup']['name'] ?? ($notes['pickup']['district_id'] ?? '—') }}</div>
    <div class="detail-label">Recipient Name</div>
    <div class="detail-value">{{ $notes['recipient'] ?? '—' }}</div>
    <div class="detail-label">Recipient Phone</div>
    <div class="detail-value">{{ $notes['recipient_phone'] ?? '—' }}</div>
    @if(!empty($notes['description']))
    <div class="detail-label">Description</div>
    <div class="detail-value">{{ $notes['description'] }}</div>
    @endif
</div>

@elseif($slug === 'edata' && !empty($notes))
<div class="section-title">Data Bundle Details</div>
<div class="detail-grid">
    <div class="detail-label">Bundle Name</div>
    <div class="detail-value" style="font-weight:700;">{{ $notes['item_name'] ?? '—' }}</div>
    <div class="detail-label">Data Amount</div>
    <div class="detail-value">{{ $notes['data_amount'] ?? '—' }}</div>
    <div class="detail-label">Validity</div>
    <div class="detail-value">{{ isset($notes['validity_days']) ? $notes['validity_days'].' days' : '—' }}</div>
    <div class="detail-label">Phone Number</div>
    <div class="detail-value" style="font-weight:700;font-size:15px;">{{ $notes['phone_number'] ?? '—' }}</div>
    <div class="detail-label">Type</div>
    <div class="detail-value">{{ ucfirst($notes['type'] ?? '—') }}</div>
    <div class="detail-label">Price</div>
    <div class="detail-value">${{ number_format($notes['price'] ?? 0, 2) }}</div>
</div>

@elseif($slug === 'erent' && !empty($notes))
<div class="section-title">Rental Details</div>
<div class="detail-grid">
    <div class="detail-label">Property</div>
    <div class="detail-value" style="font-weight:700;">{{ $notes['property_title'] ?? '—' }}</div>
    <div class="detail-label">Type</div>
    <div class="detail-value">{{ ucfirst($notes['property_type'] ?? '—') }}</div>
    <div class="detail-label">Booking Type</div>
    <div class="detail-value">{{ ucfirst($notes['booking_type'] ?? '—') }}</div>
    <div class="detail-label">Move-in Date</div>
    <div class="detail-value">{{ $notes['move_in_date'] ?? '—' }}</div>
    <div class="detail-label">Duration</div>
    <div class="detail-value">{{ isset($notes['duration_months']) ? $notes['duration_months'].' month(s)' : '—' }}</div>
    <div class="detail-label">Monthly Rent</div>
    <div class="detail-value">${{ number_format($notes['monthly_rent'] ?? 0, 2) }}</div>
    <div class="detail-label">Deposit</div>
    <div class="detail-value">${{ number_format($notes['deposit'] ?? 0, 2) }}</div>
    <div class="detail-label">Brokerage Fee</div>
    <div class="detail-value">${{ number_format($notes['brokerage_fee'] ?? 0, 2) }}</div>
    <div class="detail-label">Total Value</div>
    <div class="detail-value" style="font-weight:700;">${{ number_format($notes['total_value'] ?? 0, 2) }}</div>
    <div class="detail-label">Amount Paid</div>
    <div class="detail-value" style="color:#10b981;font-weight:700;">${{ number_format($notes['amount_paid'] ?? 0, 2) }}</div>
    <div class="detail-label">Balance Due</div>
    <div class="detail-value" style="color:#ef4444;font-weight:700;">${{ number_format($notes['amount_remaining'] ?? 0, 2) }}</div>
    @if(!empty($notes['note']))
    <div class="detail-label">Note</div>
    <div class="detail-value">{{ $notes['note'] }}</div>
    @endif
</div>

@elseif($slug === 'emoving' && !empty($notes))
<div class="section-title">Moving Details</div>
<div class="detail-grid">
    <div class="detail-label">Move Type</div>
    <div class="detail-value" style="font-weight:700;">{{ ucfirst($notes['move_type'] ?? '—') }}</div>
    <div class="detail-label">Rooms</div>
    <div class="detail-value">{{ $notes['room_count'] ?? '—' }}</div>
    <div class="detail-label">From District</div>
    <div class="detail-value">{{ $notes['from_district'] ?? '—' }}</div>
    <div class="detail-label">To District</div>
    <div class="detail-value">{{ $notes['to_district'] ?? '—' }}</div>
    <div class="detail-label">Scheduled Date</div>
    <div class="detail-value">{{ $notes['scheduled_date'] ?? '—' }}</div>
    @if(!empty($notes['extra_services']) && is_array($notes['extra_services']))
    <div class="detail-label">Extra Services</div>
    <div class="detail-value">{{ implode(', ', $notes['extra_services']) }}</div>
    @endif
    @if(!empty($notes['user_note']))
    <div class="detail-label">Customer Note</div>
    <div class="detail-value">{{ $notes['user_note'] }}</div>
    @endif
</div>
{{-- Moving price breakdown --}}
<table style="margin-top:8px;">
    <thead>
        <tr>
            <th>Item</th>
            <th style="text-align:right;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @if(isset($notes['base_price']) && $notes['base_price'] > 0)
        <tr><td>Base Price</td><td style="text-align:right;">${{ number_format($notes['base_price'],2) }}</td></tr>
        @endif
        @if(isset($notes['room_price']) && $notes['room_price'] > 0)
        <tr><td>Room Price</td><td style="text-align:right;">${{ number_format($notes['room_price'],2) }}</td></tr>
        @endif
        @if(isset($notes['extra_fee']) && $notes['extra_fee'] > 0)
        <tr><td>Extra Services Fee</td><td style="text-align:right;">${{ number_format($notes['extra_fee'],2) }}</td></tr>
        @endif
        @if(isset($notes['distance_fee']) && $notes['distance_fee'] > 0)
        <tr><td>Distance Fee</td><td style="text-align:right;">${{ number_format($notes['distance_fee'],2) }}</td></tr>
        @endif
    </tbody>
</table>

@elseif($slug === 'elaundry' && !empty($notes))
<div class="section-title">Laundry Details</div>
<div style="display:flex;gap:30px;margin-bottom:12px;flex-wrap:wrap;">
    <div><span style="color:#999;font-size:11px;font-weight:700;text-transform:uppercase;">Service Type</span><br><b>{{ ucfirst($notes['service_type'] ?? '—') }}</b></div>
    <div><span style="color:#999;font-size:11px;font-weight:700;text-transform:uppercase;">District</span><br><b>{{ $notes['district'] ?? '—' }}</b></div>
    <div><span style="color:#999;font-size:11px;font-weight:700;text-transform:uppercase;">ETA</span><br><b>{{ $notes['eta'] ?? '—' }}</b></div>
</div>
{{-- Laundry items from notes --}}
@if(!empty($notes['items']) && is_array($notes['items']))
<table>
    <thead>
        <tr>
            <th width="30">✓</th>
            <th>Item</th>
            <th style="text-align:center;">Qty</th>
            <th style="text-align:center;">Picked</th>
            <th style="text-align:right;">Unit Price</th>
            <th style="text-align:right;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach($notes['items'] as $item)
        <tr>
            <td><span class="check"></span></td>
            <td style="font-weight:600;">{{ $item['name'] ?? '—' }}</td>
            <td style="text-align:center;font-weight:700;">{{ $item['qty'] ?? 1 }}</td>
            <td style="text-align:center;border:1px solid #ccc;min-width:50px;">&nbsp;</td>
            <td style="text-align:right;">${{ number_format($item['price'] ?? 0, 2) }}</td>
            <td style="text-align:right;font-weight:700;">${{ number_format($item['sub'] ?? (($item['price'] ?? 0) * ($item['qty'] ?? 1)), 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@elseif(!$isService)
{{-- ===== PRODUCT MODULES (eFood, eGrocery, eShop, etc.) — use order_items ===== --}}
<table>
    <thead>
        <tr>
            <th width="30">✓</th>
            <th>Product</th>
            <th style="text-align:center;">Qty</th>
            <th style="text-align:center;">Picked</th>
            <th style="text-align:right;">Unit Price</th>
            <th style="text-align:right;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @forelse($order->items as $item)
        <tr>
            <td><span class="check"></span></td>
            <td style="font-weight:600;">{{ $item->name ?? $item->product?->name }}</td>
            <td style="text-align:center;font-weight:700;">{{ $item->quantity ?? $item->qty ?? 1 }}</td>
            <td style="text-align:center;border:1px solid #ccc;min-width:50px;">&nbsp;</td>
            <td style="text-align:right;">${{ number_format($item->price ?? 0, 2) }}</td>
            <td style="text-align:right;font-weight:700;">${{ number_format($item->total ?? (($item->price ?? 0) * ($item->quantity ?? 1)), 2) }}</td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:#999;padding:20px;">No items found</td></tr>
        @endforelse
    </tbody>
</table>
@endif

{{-- ===== TOTALS ===== --}}
<div class="totals" style="margin-top:16px;">
    @if($slug === 'erent' && !empty($notes))
        <div><span class="totals-label">Monthly Rent:</span><span class="totals-value">${{ number_format($notes['monthly_rent'] ?? 0, 2) }}</span></div>
        <div><span class="totals-label">Deposit:</span><span class="totals-value">${{ number_format($notes['deposit'] ?? 0, 2) }}</span></div>
        <div style="border-top:2px solid #111;margin-top:8px;padding-top:8px;"><span class="totals-label bold">TOTAL VALUE:</span><span class="totals-value bold">${{ number_format($notes['total_value'] ?? 0, 2) }}</span></div>
        <div><span class="totals-label" style="color:#10b981;">PAID:</span><span class="totals-value" style="color:#10b981;">${{ number_format($notes['amount_paid'] ?? 0, 2) }}</span></div>
        <div><span class="totals-label" style="color:#ef4444;">BALANCE DUE:</span><span class="totals-value" style="color:#ef4444;">${{ number_format($notes['amount_remaining'] ?? 0, 2) }}</span></div>
    @elseif($slug === 'emoving' && !empty($notes))
        <div style="border-top:2px solid #111;margin-top:8px;padding-top:8px;"><span class="totals-label bold">TOTAL:</span><span class="totals-value bold">${{ number_format($notes['total'] ?? $order->total_amount ?? $order->total ?? 0, 2) }}</span></div>
    @elseif($slug === 'edata' && !empty($notes))
        <div style="border-top:2px solid #111;margin-top:8px;padding-top:8px;"><span class="totals-label bold">TOTAL:</span><span class="totals-value bold">${{ number_format($notes['price'] ?? $order->total_amount ?? 0, 2) }}</span></div>
    @else
        <div><span class="totals-label">Subtotal:</span><span class="totals-value">${{ number_format($order->subtotal ?? 0, 2) }}</span></div>
        @if(($order->discount_amount ?? 0) > 0 || ($order->discount ?? 0) > 0)
        <div><span class="totals-label">Discount:</span><span class="totals-value" style="color:#10b981;">-${{ number_format($order->discount_amount ?? $order->discount ?? 0, 2) }}</span></div>
        @endif
        @if(($order->delivery_fee ?? 0) > 0)
        <div><span class="totals-label">Delivery Fee:</span><span class="totals-value">${{ number_format($order->delivery_fee, 2) }}</span></div>
        @endif
        <div style="border-top:2px solid #111;margin-top:8px;padding-top:8px;"><span class="totals-label bold">TOTAL:</span><span class="totals-value bold">${{ number_format($order->total_amount ?? $order->total ?? 0, 2) }}</span></div>
    @endif
</div>

<div style="clear:both;margin-top:40px;display:flex;gap:40px;">
    <div style="flex:1;border-top:1px solid #999;padding-top:8px;text-align:center;font-size:12px;color:#999;">Staff Signature</div>
    <div style="flex:1;border-top:1px solid #999;padding-top:8px;text-align:center;font-size:12px;color:#999;">Driver Signature</div>
    <div style="flex:1;border-top:1px solid #999;padding-top:8px;text-align:center;font-size:12px;color:#999;">Customer Signature</div>
</div>

<div class="footer">Printed {{ now()->format('M j, Y H:i') }} · eSahlan {{ ucfirst($slug) }}</div>

</body>
</html>
