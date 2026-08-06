<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Confirmed</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #F0F2F8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1A1A2E; }
  .wrap { max-width: 600px; margin: 0 auto; padding: 24px 16px; }
  .card { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.07); }
  .header { background: #1A1A2E; padding: 32px 28px; text-align: center; }
  .header h1 { color: #fff; font-size: 22px; font-weight: 800; }
  .header .badge { display: inline-block; background: #F59E0B; color: #1A1A2E; font-size: 13px; font-weight: 800; padding: 6px 16px; border-radius: 20px; margin-top: 12px; }
  .body { padding: 28px; }
  .greeting { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
  .sub { color: #6B7280; font-size: 13px; margin-bottom: 24px; line-height: 1.6; }
  .info-box { background: #F8F9FE; border-radius: 10px; padding: 16px 18px; margin-bottom: 20px; }
  .info-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #E5E7EB; font-size: 13px; }
  .info-row:last-child { border-bottom: none; }
  .info-row .label { color: #6B7280; }
  .info-row .val { font-weight: 700; }
  .section-title { font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #6B7280; margin-bottom: 12px; }
  .item-row { display: flex; gap: 14px; align-items: center; padding: 12px 0; border-bottom: 1px solid #F0F2F5; }
  .item-row:last-child { border-bottom: none; }
  .item-thumb { width: 56px; height: 56px; border-radius: 8px; object-fit: cover; background: #F0F2F5; flex-shrink: 0; }
  .item-name { font-size: 13px; font-weight: 600; }
  .item-meta { font-size: 11px; color: #9CA3AF; margin-top: 2px; }
  .item-price { margin-left: auto; font-weight: 800; font-size: 14px; white-space: nowrap; }
  .totals { margin-top: 20px; background: #F8F9FE; border-radius: 10px; padding: 16px 18px; }
  .total-row { display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0; }
  .total-row.final { font-size: 16px; font-weight: 800; border-top: 2px solid #E5E7EB; padding-top: 12px; margin-top: 8px; }
  .addr-box { background: #F8F9FE; border-radius: 10px; padding: 14px 18px; margin-top: 20px; }
  .addr-box p { font-size: 13px; color: #4B5563; line-height: 1.65; }
  .cta { text-align: center; margin-top: 28px; }
  .cta a { display: inline-block; background: #1A1A2E; color: #fff; font-weight: 800; font-size: 14px; padding: 14px 32px; border-radius: 12px; text-decoration: none; }
  .footer { padding: 20px 28px 24px; text-align: center; color: #9CA3AF; font-size: 11px; }
  .footer a { color: #F59E0B; text-decoration: none; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <!-- Header -->
    <div class="header">
      <h1>eSahlan Global</h1>
      <div class="badge">✓ &nbsp;Order Confirmed</div>
    </div>

    <!-- Body -->
    <div class="body">
      <div class="greeting">Hi {{ $order['customer_name'] ?? 'Customer' }},</div>
      <p class="sub">Thank you for your order! We've received it and will begin processing it right away. You'll receive another email when your order ships.</p>

      <!-- Order info -->
      <div class="info-box">
        <div class="info-row">
          <span class="label">Order Number</span>
          <span class="val">{{ $order['order_number'] }}</span>
        </div>
        <div class="info-row">
          <span class="label">Payment</span>
          <span class="val" style="color:#10B981">{{ ucfirst($order['payment_method'] ?? 'Card') }} · Paid</span>
        </div>
        <div class="info-row">
          <span class="label">Date</span>
          <span class="val">{{ now()->format('M j, Y') }}</span>
        </div>
      </div>

      <!-- Items -->
      <div class="section-title">Items Ordered</div>
      @foreach ($items as $item)
      <div class="item-row">
        @if ($item['thumbnail'])
          <img class="item-thumb" src="{{ $item['thumbnail'] }}" alt="{{ $item['name'] }}">
        @else
          <div class="item-thumb" style="display:flex;align-items:center;justify-content:center;color:#D1D5DB;font-size:22px;">📦</div>
        @endif
        <div>
          <div class="item-name">{{ $item['name'] }}</div>
          <div class="item-meta">
            @if (!empty($item['variant'])) {{ $item['variant'] }} · @endif
            Qty: {{ $item['quantity'] }}
          </div>
        </div>
        <div class="item-price">${{ number_format($item['total'], 2) }}</div>
      </div>
      @endforeach

      <!-- Totals -->
      <div class="totals">
        <div class="total-row">
          <span>Subtotal</span>
          <span>${{ number_format($order['subtotal'], 2) }}</span>
        </div>
        <div class="total-row">
          <span>Shipping</span>
          <span>{{ $order['shipping'] > 0 ? '$' . number_format($order['shipping'], 2) : 'Free' }}</span>
        </div>
        <div class="total-row final">
          <span>Total</span>
          <span>${{ number_format($order['total'], 2) }} USD</span>
        </div>
      </div>

      <!-- Shipping address -->
      @if (!empty($order['shipping_address']))
      <div class="addr-box">
        <div class="section-title" style="margin-bottom:6px">Shipping To</div>
        <p>{{ $order['shipping_address'] }}</p>
      </div>
      @endif

      <!-- CTA -->
      <div class="cta">
        <a href="{{ config('app.url') }}/global/order/{{ $order['id'] ?? '' }}">View Order Details →</a>
      </div>
    </div>

    <!-- Footer -->
    <div class="footer">
      <p>Questions? <a href="mailto:support@esahlan.com">support@esahlan.com</a></p>
      <p style="margin-top:6px">© {{ date('Y') }} eSahlan Global · <a href="https://global.esahlan.com/privacy">Privacy Policy</a></p>
    </div>
  </div>
</div>
</body>
</html>
