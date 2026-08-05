@extends('admin.layouts.app')
@section('title', 'Currency Management')

@section('content')
<div class="page-header">
    <div><h1 class="page-title">💱 Currency Management</h1><p class="page-subtitle">Exchange rates relative to {{ $base }}</p></div>
    <div style="display:flex;gap:10px">
        <form method="POST" action="{{ route('admin.global.currency.sync-rates') }}">@csrf
            <button type="submit" style="padding:9px 18px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px">
                <i class="fas fa-sync-alt"></i> Sync Live Rates
            </button>
        </form>
    </div>
</div>

@if(session('success'))<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>@endif
@if(session('error'))<div style="background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">❌ {{ session('error') }}</div>@endif

@if($lastSync)
<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:12px;color:#1d4ed8">
    <i class="fas fa-clock"></i> Last synced: {{ \Carbon\Carbon::parse($lastSync)->format('M d, Y g:i A') }}
    · Source: open.er-api.com (free, no API key needed)
</div>
@endif

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start">

{{-- Rates Table --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6">
        <h3 style="font-size:14px;font-weight:700;color:#111">Exchange Rates (Base: {{ $base }})</h3>
    </div>
    @php
    $flags = ['USD'=>'🇺🇸','EUR'=>'🇪🇺','GBP'=>'🇬🇧','CAD'=>'🇨🇦','AUD'=>'🇦🇺','NOK'=>'🇳🇴','SEK'=>'🇸🇪','DKK'=>'🇩🇰','CHF'=>'🇨🇭','JPY'=>'🇯🇵','SAR'=>'🇸🇦','AED'=>'🇦🇪'];
    $currencies = ['USD'=>'US Dollar','EUR'=>'Euro','GBP'=>'British Pound','CAD'=>'Canadian Dollar','AUD'=>'Australian Dollar','NOK'=>'Norwegian Krone','SEK'=>'Swedish Krona','DKK'=>'Danish Krone','CHF'=>'Swiss Franc','JPY'=>'Japanese Yen','SAR'=>'Saudi Riyal','AED'=>'UAE Dirham'];
    @endphp
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
                <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Currency</th>
                <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Rate (per 1 {{ $base }})</th>
                <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Manual Override</th>
            </tr>
        </thead>
        <tbody>
            @foreach($currencies as $code => $name)
            @php $rate = $rates[$code] ?? null; @endphp
            <tr style="border-top:1px solid #f3f4f6">
                <td style="padding:10px 16px">
                    <div style="display:flex;align-items:center;gap:10px">
                        <span style="font-size:22px">{{ $flags[$code] ?? '🏳' }}</span>
                        <div>
                            <div style="font-size:13px;font-weight:700;color:#111">{{ $code }}</div>
                            <div style="font-size:11px;color:#9ca3af">{{ $name }}</div>
                        </div>
                    </div>
                </td>
                <td style="padding:10px 16px;text-align:right;font-size:14px;font-weight:700;color:{{ $rate ? '#111' : '#9ca3af' }}">
                    {{ $rate ? number_format($rate, 4) : 'Not set' }}
                </td>
                <td style="padding:10px 16px;text-align:right">
                    <form method="POST" action="{{ route('admin.global.currency.update-rate') }}" style="display:flex;gap:6px;justify-content:flex-end">
                        @csrf
                        <input type="hidden" name="currency" value="{{ $code }}">
                        <input type="number" name="rate" step="0.0001" value="{{ $rate }}" placeholder="Rate" style="width:100px;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;text-align:right">
                        <button type="submit" style="padding:6px 12px;background:#6366f1;color:#fff;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer">Set</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Info Panel --}}
<div style="display:flex;flex-direction:column;gap:14px">
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:20px">
        <h3 style="font-size:13px;font-weight:700;color:#111;margin-bottom:14px">ℹ️ How it works</h3>
        <div style="font-size:12px;color:#374151;line-height:1.7">
            <p style="margin-bottom:8px">• Default currency: <strong>{{ $base }}</strong></p>
            <p style="margin-bottom:8px">• All prices stored in {{ $base }}</p>
            <p style="margin-bottom:8px">• Rates auto-convert at checkout</p>
            <p style="margin-bottom:8px">• "Sync Live Rates" fetches from open.er-api.com (free, no API key)</p>
            <p>• Manual rates override auto-sync</p>
        </div>
    </div>

    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:20px">
        <h3 style="font-size:13px;font-weight:700;color:#92400e;margin-bottom:10px">⚠️ Important</h3>
        <div style="font-size:12px;color:#78350f;line-height:1.6">
            <p>Currency conversion is applied at display time only. All payments are charged in {{ $base }} via Stripe/PayPal.</p>
        </div>
    </div>

    <div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:12px;padding:20px">
        <h3 style="font-size:13px;font-weight:700;color:#5b21b6;margin-bottom:14px">🔄 Auto-Sync</h3>
        <div style="font-size:12px;color:#374151;line-height:1.6;margin-bottom:12px">Schedule automatic rate sync via cron. Add to Laravel scheduler:</div>
        <code style="display:block;background:#1e1b4b;color:#a5b4fc;padding:10px 12px;border-radius:8px;font-size:11px">$schedule->command('global:sync-rates')->daily();</code>
    </div>
</div>

</div>
@endsection
