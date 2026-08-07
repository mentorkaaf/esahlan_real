<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $data['is_reminder'] ? 'Reminder: Leave a Review' : 'How was your order?' }}</title>
<style>
  body { margin: 0; padding: 0; background: #f0f2f5; font-family: 'Segoe UI', Arial, sans-serif; }
  .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
  .header { background: #0d1b2a; padding: 36px 40px; text-align: center; }
  .header .logo { color: #ff6b35; font-size: 28px; font-weight: 800; letter-spacing: -0.5px; }
  .header .logo span { color: #ffffff; }
  .body { padding: 40px; }
  .greeting { font-size: 22px; font-weight: 700; color: #0d1b2a; margin-bottom: 12px; }
  .text { font-size: 15px; color: #4b5563; line-height: 1.7; margin-bottom: 20px; }
  .product-box { background: #f8f9fa; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px 24px; margin-bottom: 28px; }
  .product-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; margin-bottom: 6px; }
  .product-name { font-size: 17px; font-weight: 600; color: #0d1b2a; }
  .order-number { font-size: 13px; color: #6b7280; margin-top: 4px; }
  .stars { text-align: center; margin-bottom: 28px; }
  .stars span { font-size: 36px; letter-spacing: 4px; }
  .cta { text-align: center; margin-bottom: 32px; }
  .cta a { display: inline-block; background: #ff6b35; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 700; padding: 16px 40px; border-radius: 50px; letter-spacing: 0.3px; }
  .divider { border: none; border-top: 1px solid #e5e7eb; margin: 28px 0; }
  .footer { padding: 24px 40px; background: #0d1b2a; text-align: center; }
  .footer p { color: #6b7280; font-size: 12px; margin: 4px 0; }
  .footer a { color: #ff6b35; text-decoration: none; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <div class="logo">e<span>Sahlan</span></div>
  </div>
  <div class="body">
    <div class="greeting">
      {{ $data['is_reminder'] ? 'Still thinking about it? 😊' : 'Your order has arrived! 📦' }}
    </div>
    <p class="text">
      Hi {{ $data['name'] }}, {{ $data['is_reminder'] ? 'we noticed you haven\'t left a review yet.' : 'we hope you\'re loving your purchase!' }}
      Your feedback helps other shoppers and helps us improve. It only takes a minute!
    </p>

    <div class="product-box">
      <div class="product-label">You purchased</div>
      <div class="product-name">{{ $data['product_name'] }}</div>
      <div class="order-number">Order #{{ $data['order_number'] }}</div>
    </div>

    <div class="stars">
      <span>⭐⭐⭐⭐⭐</span>
    </div>

    <div class="cta">
      <a href="{{ config('app.url') }}/global/orders/{{ $data['order_id'] }}">Write a Review</a>
    </div>

    <hr class="divider">

    <p class="text" style="font-size:13px;color:#9ca3af;text-align:center;">
      This email was sent because you placed an order on eSahlan.<br>
      If you have already reviewed, please ignore this message.
    </p>
  </div>
  <div class="footer">
    <p>© {{ date('Y') }} eSahlan. All rights reserved.</p>
    <p><a href="{{ config('app.url') }}">esahlan.com</a></p>
  </div>
</div>
</body>
</html>
