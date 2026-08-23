<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Picking Slip — {{ $order->order_no }}</title>
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
</style>
</head>
<body>

<div class="no-print" style="background:#fff;padding:10px;margin-bottom:16px;border:1px solid #ddd;border-radius:6px;display:flex;gap:10px;">
    <button onclick="window.print()" style="background:#111;color:#fff;border:none;padding:8px 18px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:700;">🖨 Print</button>
    <a href="{{ route('admin.module-data.wholesale.orders.show', $order) }}" style="color:#374151;text-decoration:none;font-size:13px;padding:8px 14px;background:#f1f5f9;border-radius:6px;">← Back to Order</a>
</div>

<div class="header">
    <div>
        <div style="font-size:11px;color:#999;font-weight:700;letter-spacing:1px;margin-bottom:4px;">PICKING SLIP</div>
        <h1>{{ $order->order_no }}</h1>
        <div style="margin-top:6px;font-size:13px;color:#555;">{{ $order->created_at->format('l, F j, Y — H:i') }}</div>
    </div>
    <div style="text-align:right;">
        <div style="font-size:22px;font-weight:900;letter-spacing:-1px;">eSahlan</div>
        <div style="font-size:11px;color:#999;">eWholesale</div>
        <div style="margin-top:8px;"><span class="badge-status">{{ strtoupper(str_replace('_',' ',$order->status)) }}</span></div>
    </div>
</div>

<div style="display:flex;gap:40px;margin-bottom:20px;flex-wrap:wrap;">
    <div>
        <div style="font-size:10px;font-weight:800;color:#999;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Buyer</div>
        <div style="font-weight:700;font-size:14px;">{{ $order->buyer?->user?->name }}</div>
        <div style="color:#555;">{{ $order->buyer?->user?->phone }}</div>
    </div>
    @if($order->supplier)
    <div>
        <div style="font-size:10px;font-weight:800;color:#999;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Supplier</div>
        <div style="font-weight:700;">{{ $order->supplier?->name }}</div>
    </div>
    @endif
    <div>
        <div style="font-size:10px;font-weight:800;color:#999;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Payment</div>
        <div style="font-weight:700;">{{ strtoupper($order->payment_method ?? '—') }}</div>
        <div style="color:#555;">{{ ucfirst($order->payment_status ?? 'pending') }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th width="30">✓</th>
            <th>Product</th>
            <th>Variant</th>
            <th style="text-align:center;">Ordered</th>
            <th style="text-align:center;">Picked</th>
            <th style="text-align:right;">Unit Price</th>
            <th style="text-align:right;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @forelse($order->items as $item)
        <tr>
            <td><span class="check"></span></td>
            <td style="font-weight:600;">{{ $item->product?->name ?? $item->name_snapshot ?? '—' }}</td>
            <td style="color:#555;">{{ $item->variant?->name ?? $item->variant_snapshot ?? '' }}</td>
            <td style="text-align:center;font-weight:700;">{{ $item->qty }}</td>
            <td style="text-align:center;border:1px solid #ccc;min-width:50px;">&nbsp;</td>
            <td style="text-align:right;">${{ number_format($item->unit_price, 2) }}</td>
            <td style="text-align:right;font-weight:700;">${{ number_format($item->total, 2) }}</td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:#999;padding:20px;">No items found</td></tr>
        @endforelse
    </tbody>
</table>

<div class="totals">
    <div><span class="totals-label">Subtotal:</span><span class="totals-value">${{ number_format($order->subtotal ?? 0, 2) }}</span></div>
    @if(($order->discount ?? 0) > 0)
    <div><span class="totals-label">Discount:</span><span class="totals-value" style="color:#10b981;">-${{ number_format($order->discount, 2) }}</span></div>
    @endif
    @if(($order->shipping_fee ?? 0) > 0)
    <div><span class="totals-label">Shipping Fee:</span><span class="totals-value">${{ number_format($order->shipping_fee, 2) }}</span></div>
    @endif
    <div style="border-top:2px solid #111;margin-top:8px;padding-top:8px;"><span class="totals-label bold">TOTAL:</span><span class="totals-value bold">${{ number_format($order->total, 2) }}</span></div>
</div>

<div style="clear:both;margin-top:40px;display:flex;gap:40px;">
    <div style="flex:1;border-top:1px solid #999;padding-top:8px;text-align:center;font-size:12px;color:#999;">Picker Signature</div>
    <div style="flex:1;border-top:1px solid #999;padding-top:8px;text-align:center;font-size:12px;color:#999;">Driver Signature</div>
    <div style="flex:1;border-top:1px solid #999;padding-top:8px;text-align:center;font-size:12px;color:#999;">Customer Signature</div>
</div>

<div class="footer">Printed {{ now()->format('M j, Y H:i') }} · eSahlan eWholesale</div>

</body>
</html>
