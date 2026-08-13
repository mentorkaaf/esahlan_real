<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $alertSubject }}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f0f4f8; color:#1a202c; }
  .wrapper { max-width:600px; margin:0 auto; padding:24px 16px; }
  .card { background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.08); }

  /* Header */
  .header { padding:28px 32px 24px; display:flex; align-items:center; gap:16px; }
  .header.orders    { background:linear-gradient(135deg,#FF8A00,#FF6B00); }
  .header.vendors   { background:linear-gradient(135deg,#7C3AED,#5B21B6); }
  .header.users     { background:linear-gradient(135deg,#059669,#047857); }
  .header.security  { background:linear-gradient(135deg,#DC2626,#991B1B); }
  .header.server    { background:linear-gradient(135deg,#0369A1,#1E40AF); }
  .header.default   { background:linear-gradient(135deg,#374151,#1F2937); }

  .header-icon { width:52px; height:52px; background:rgba(255,255,255,0.2); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:26px; flex-shrink:0; }
  .header-text h1 { font-size:20px; font-weight:700; color:#fff; line-height:1.2; }
  .header-text p  { font-size:13px; color:rgba(255,255,255,0.8); margin-top:3px; }

  /* Badge */
  .badge-row { padding:16px 32px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; gap:8px; }
  .badge { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700; letter-spacing:0.5px; text-transform:uppercase; }
  .badge.orders   { background:#fff3e0; color:#e65100; }
  .badge.vendors  { background:#f3e8ff; color:#7c3aed; }
  .badge.users    { background:#ecfdf5; color:#059669; }
  .badge.security { background:#fef2f2; color:#dc2626; }
  .badge.server   { background:#eff6ff; color:#1d4ed8; }
  .badge.default  { background:#f1f5f9; color:#475569; }
  .badge-dot { width:7px; height:7px; border-radius:50%; background:currentColor; }

  /* Body */
  .body { padding:28px 32px; }
  .body h2 { font-size:17px; font-weight:700; color:#0f172a; margin-bottom:16px; }

  /* Data table */
  .data-table { width:100%; border-collapse:collapse; margin-bottom:20px; }
  .data-table tr { border-bottom:1px solid #f1f5f9; }
  .data-table tr:last-child { border-bottom:none; }
  .data-table td { padding:10px 0; font-size:14px; vertical-align:top; }
  .data-table td:first-child { color:#64748b; font-weight:600; width:40%; padding-right:16px; }
  .data-table td:last-child { color:#0f172a; font-weight:500; }

  /* CTA */
  .cta-wrap { text-align:center; margin:24px 0 8px; }
  .cta { display:inline-block; padding:12px 32px; background:linear-gradient(135deg,#FF8A00,#FF6B00); color:#fff; font-weight:700; font-size:15px; border-radius:10px; text-decoration:none; }

  /* Footer */
  .footer { padding:20px 32px; background:#f8fafc; border-top:1px solid #e2e8f0; text-align:center; }
  .footer p { font-size:12px; color:#94a3b8; line-height:1.6; }
  .footer strong { color:#64748b; }

  /* Severity stripe for security/server */
  .severity-stripe { height:4px; }
  .severity-stripe.security { background:linear-gradient(90deg,#DC2626,#F87171); }
  .severity-stripe.server   { background:linear-gradient(90deg,#1D4ED8,#60A5FA); }
  .severity-stripe.orders   { background:linear-gradient(90deg,#FF8A00,#FCD34D); }
  .severity-stripe.vendors  { background:linear-gradient(90deg,#7C3AED,#A78BFA); }
  .severity-stripe.users    { background:linear-gradient(90deg,#059669,#6EE7B7); }
  .severity-stripe.default  { background:linear-gradient(90deg,#374151,#9CA3AF); }

  @media (max-width:480px) {
    .header, .body, .footer, .badge-row { padding-left:20px; padding-right:20px; }
    .header-text h1 { font-size:17px; }
  }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">

    {{-- Severity stripe --}}
    @php
      $cat = match($alertKey) {
        'new_order','order_cancelled','order_refund'                     => 'orders',
        'new_vendor','vendor_approved','new_product','low_stock'         => 'vendors',
        'new_agent','new_customer'                                       => 'users',
        'failed_login_spike','admin_login','suspicious_activity'         => 'security',
        'high_cpu','high_memory','storage_warning','storage_critical','queue_failed','slow_db' => 'server',
        default => 'default',
      };
      $icon = match($alertKey) {
        'new_order'              => '🛒',
        'order_cancelled'        => '❌',
        'order_refund'           => '💸',
        'new_vendor'             => '🏪',
        'vendor_approved'        => '✅',
        'new_product'            => '📦',
        'low_stock'              => '⚠️',
        'new_agent'              => '🚴',
        'new_customer'           => '👤',
        'failed_login_spike'     => '🚨',
        'admin_login'            => '🔐',
        'suspicious_activity'    => '🕵️',
        'high_cpu'               => '🔥',
        'high_memory'            => '💾',
        'storage_warning'        => '💿',
        'storage_critical'       => '🆘',
        'queue_failed'           => '⚙️',
        'slow_db'                => '🐢',
        default                  => '🔔',
      };
    @endphp

    <div class="severity-stripe {{ $cat }}"></div>

    {{-- Header --}}
    <div class="header {{ $cat }}">
      <div class="header-icon">{{ $icon }}</div>
      <div class="header-text">
        <h1>{{ $alertSubject }}</h1>
        <p>eSahlan Admin Alert • {{ now()->format('D, d M Y · H:i') }} UTC</p>
      </div>
    </div>

    {{-- Category badge --}}
    <div class="badge-row">
      <span class="badge {{ $cat }}">
        <span class="badge-dot"></span>
        {{ strtoupper($cat) }}
      </span>
      <span style="font-size:13px;color:#64748b;">{{ $alertLabel }}</span>
    </div>

    {{-- Body --}}
    <div class="body">
      <h2>Alert Details</h2>

      @if(!empty($alertData))
      <table class="data-table">
        @foreach($alertData as $label => $value)
        <tr>
          <td>{{ $label }}</td>
          <td>{{ $value }}</td>
        </tr>
        @endforeach
      </table>
      @endif

      {{-- CTA button based on category --}}
      @php
        $ctaUrl = match($cat) {
          'orders'   => config('app.url') . '/admin/orders',
          'vendors'  => config('app.url') . '/admin/vendors',
          'users'    => config('app.url') . '/admin/users',
          'security' => config('app.url') . '/admin/security/soc',
          'server'   => config('app.url') . '/admin/security/soc',
          default    => config('app.url') . '/admin',
        };
        $ctaText = match($cat) {
          'orders'   => 'View Orders →',
          'vendors'  => 'Manage Vendors →',
          'users'    => 'View Users →',
          'security' => 'Open Security Center →',
          'server'   => 'View Server Status →',
          default    => 'Open Admin Panel →',
        };
      @endphp
      <div class="cta-wrap">
        <a href="{{ $ctaUrl }}" class="cta">{{ $ctaText }}</a>
      </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
      <p>
        This is an automated alert from <strong>eSahlan Admin</strong>.<br>
        Manage alert preferences at <a href="{{ config('app.url') }}/admin/alerts" style="color:#FF8A00;text-decoration:none;">Admin → Alerts Settings</a>.<br>
        <span style="color:#cbd5e1;">Sent at {{ now()->toDateTimeString() }} UTC</span>
      </p>
    </div>

  </div>
</div>
</body>
</html>
