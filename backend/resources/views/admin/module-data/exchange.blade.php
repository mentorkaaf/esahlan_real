@extends('admin.layouts.app')
@section('title', 'eExchange — Rates Management')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
        <h1 class="page-title">⇄ eExchange Rates</h1>
        <p class="page-subtitle">Wallet exchange rates & crypto tab control</p>
    </div>
    <a href="{{ route('admin.exchange.index') }}" style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:#6366f1;color:#fff;border-radius:9px;font-weight:700;font-size:13px;text-decoration:none">
        <i class="fas fa-list"></i> View All Orders
    </a>
</div>

{{-- ── Crypto Toggle ── --}}
@php $cryptoOn = $cryptoOn ?? true; @endphp
<div id="cryptoToggleWrap" style="
    display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;
    padding:16px 22px;border-radius:14px;margin-bottom:22px;
    background:{{ $cryptoOn ? 'linear-gradient(135deg,#4f46e5,#7c3aed)' : 'linear-gradient(135deg,#374151,#1f2937)' }};
    box-shadow:0 4px 18px {{ $cryptoOn ? 'rgba(99,102,241,.3)' : 'rgba(0,0,0,.15)' }};
    transition:all .4s;
">
    <div style="display:flex;align-items:center;gap:14px">
        <div style="width:46px;height:46px;background:rgba(255,255,255,.15);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i id="cryptoIcon" class="fas {{ $cryptoOn ? 'fa-bitcoin' : 'fa-ban' }}" style="font-size:20px;color:#fff"></i>
        </div>
        <div>
            <div style="font-size:15px;font-weight:800;color:#fff">Crypto Exchange Tab</div>
            <div id="cryptoStatus" style="font-size:12px;color:rgba(255,255,255,.65);margin-top:2px">
                {{ $cryptoOn ? '🟢 Enabled — users can buy/sell crypto in eExchange' : '🔴 Disabled — crypto tab hidden from all users' }}
            </div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:14px">
        <span id="cryptoBadge" style="font-size:11px;font-weight:800;padding:5px 14px;border-radius:20px;background:rgba(255,255,255,.2);color:#fff;letter-spacing:.05em">
            {{ $cryptoOn ? 'ENABLED' : 'DISABLED' }}
        </span>
        <label style="position:relative;display:inline-block;width:58px;height:30px;cursor:pointer">
            <input type="checkbox" id="cryptoInput" {{ $cryptoOn ? 'checked' : '' }}
                style="opacity:0;width:0;height:0" onchange="toggleCrypto(this.checked)">
            <span style="position:absolute;inset:0;border-radius:30px;background:rgba(255,255,255,.25);transition:.3s"></span>
            <span id="cryptoThumb" style="
                position:absolute;top:3px;left:{{ $cryptoOn ? '31px' : '3px' }};
                width:24px;height:24px;border-radius:50%;transition:.3s;
                background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.25);
            "></span>
        </label>
        <span id="cryptoSaving" style="font-size:11px;color:rgba(255,255,255,.6);display:none">Saving…</span>
    </div>
</div>
<script>
function toggleCrypto(enabled) {
    const wrap   = document.getElementById('cryptoToggleWrap');
    const icon   = document.getElementById('cryptoIcon');
    const status = document.getElementById('cryptoStatus');
    const badge  = document.getElementById('cryptoBadge');
    const thumb  = document.getElementById('cryptoThumb');
    const saving = document.getElementById('cryptoSaving');
    saving.style.display = 'inline';
    if (enabled) {
        wrap.style.background  = 'linear-gradient(135deg,#4f46e5,#7c3aed)';
        wrap.style.boxShadow   = '0 4px 18px rgba(99,102,241,.3)';
        icon.className         = 'fas fa-bitcoin';
        status.textContent     = '🟢 Enabled — users can buy/sell crypto in eExchange';
        badge.textContent      = 'ENABLED';
        thumb.style.left       = '31px';
    } else {
        wrap.style.background  = 'linear-gradient(135deg,#374151,#1f2937)';
        wrap.style.boxShadow   = '0 4px 18px rgba(0,0,0,.15)';
        icon.className         = 'fas fa-ban';
        status.textContent     = '🔴 Disabled — crypto tab hidden from all users';
        badge.textContent      = 'DISABLED';
        thumb.style.left       = '3px';
    }
    fetch('{{ route('admin.global.settings.toggle-crypto') }}', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
        body: JSON.stringify({ enabled }),
    })
    .then(r => r.json())
    .then(() => { saving.textContent = enabled ? '✅ Enabled' : '✅ Disabled'; setTimeout(() => { saving.style.display='none'; saving.textContent='Saving…'; }, 2000); })
    .catch(() => { saving.textContent = '❌ Error'; setTimeout(() => { saving.style.display='none'; saving.textContent='Saving…'; }, 2000); });
}
</script>

{{-- ── Quick Stats ── --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px">
    @php
    $qs = [
        ['label'=>'Total Orders', 'value'=>number_format($stats['total_orders']), 'icon'=>'fa-exchange-alt', 'color'=>'#6366f1',
         'link'=>route('admin.exchange.index')],
        ['label'=>'Pending',      'value'=>number_format($stats['pending']),       'icon'=>'fa-clock',       'color'=>'#f59e0b',
         'link'=>route('admin.exchange.index').'?status=pending'],
        ['label'=>'Volume Today', 'value'=>'$'.number_format($stats['volume_today'],2), 'icon'=>'fa-dollar-sign', 'color'=>'#10b981',
         'link'=>route('admin.exchange.index')],
    ];
    @endphp
    @foreach($qs as $q)
    <a href="{{ $q['link'] }}" style="background:#fff;border-radius:11px;padding:16px 18px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;display:flex;align-items:center;gap:12px;text-decoration:none">
        <div style="width:42px;height:42px;border-radius:10px;background:{{ $q['color'] }}18;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fas {{ $q['icon'] }}" style="color:{{ $q['color'] }};font-size:17px"></i>
        </div>
        <div>
            <div style="font-size:22px;font-weight:800;color:#111;line-height:1.1">{{ $q['value'] }}</div>
            <div style="font-size:11px;font-weight:600;color:#6b7280;margin-top:2px">{{ $q['label'] }}</div>
        </div>
    </a>
    @endforeach
</div>

{{-- ── Info Banner ── --}}
<div style="background:#fefce8;border:1px solid #fde047;border-radius:10px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px">
    <i class="fas fa-info-circle" style="color:#ca8a04;flex-shrink:0"></i>
    <div style="font-size:13px;color:#713f12">
        <strong>Fee Policy:</strong> A service fee is charged on every exchange.
        Formula: <code style="background:#fef9c3;padding:2px 6px;border-radius:4px">received = sent × rate × (1 − fee%/100)</code>
    </div>
</div>

@if(session('success'))
<div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:#065f46;font-weight:600;font-size:13px">
    ✅ {{ session('success') }}
</div>
@endif

<div style="display:grid;grid-template-columns:3fr 2fr;gap:20px;margin-bottom:22px">

    {{-- ── Rates Table ── --}}
    <div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6">
            <h3 style="font-size:14px;font-weight:700;color:#111;margin:0">Exchange Rate Matrix</h3>
        </div>
        <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="background:#f9fafb">
                    <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">From</th>
                    <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">To</th>
                    <th style="padding:10px 16px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Rate</th>
                    <th style="padding:10px 16px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Fee %</th>
                    <th style="padding:10px 16px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Action</th>
                </tr>
            </thead>
            <tbody>
                @php
                $wColors = ['evc'=>'#E74C3C','edahab'=>'#27AE60','jeep'=>'#2980B9','premier'=>'#8E44AD','ebesa'=>'#D35400'];
                @endphp
                @forelse($rates as $rate)
                @php
                $fc = $wColors[$rate->from_wallet] ?? '#6b7280';
                $tc = $wColors[$rate->to_wallet]   ?? '#6b7280';
                @endphp
                <tr style="border-top:1px solid #f3f4f6">
                    <td style="padding:10px 16px">
                        <span style="display:inline-block;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:800;color:#fff;background:{{ $fc }}">
                            {{ strtoupper($rate->from_wallet) }}
                        </span>
                    </td>
                    <td style="padding:10px 16px">
                        <span style="display:inline-block;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:800;color:#fff;background:{{ $tc }}">
                            {{ strtoupper($rate->to_wallet) }}
                        </span>
                    </td>
                    <td style="padding:10px 16px;text-align:center;font-size:14px;font-weight:800;color:#111">{{ $rate->rate }}</td>
                    <td style="padding:10px 16px;text-align:center">
                        <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#fef3c7;color:#92400e">
                            {{ $rate->fee_percentage ?? 1 }}%
                        </span>
                    </td>
                    <td style="padding:10px 16px;text-align:center">
                        <form action="{{ route('admin.module-data.exchange.destroy', $rate->id) }}" method="POST" onsubmit="return confirm('Delete this rate?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="padding:5px 12px;background:#fef2f2;color:#ef4444;border:1px solid #fecaca;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" style="padding:32px;text-align:center;color:#9ca3af;font-size:13px">No rates configured yet. Add your first rate →</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- ── Add Rate Form ── --}}
    <div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6">
            <h3 style="font-size:14px;font-weight:700;color:#111;margin:0">Add / Update Rate</h3>
        </div>
        <div style="padding:18px">
            <form action="{{ route('admin.module-data.exchange.store') }}" method="POST">
                @csrf
                <div style="margin-bottom:14px">
                    <label style="font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:6px">From Wallet *</label>
                    <select name="from_wallet" required style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:9px;font-size:13px;font-weight:600;color:#111;background:#fff">
                        @foreach($wallets as $w)
                        <option value="{{ $w }}">{{ strtoupper($w) }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="margin-bottom:14px">
                    <label style="font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:6px">To Wallet *</label>
                    <select name="to_wallet" required style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:9px;font-size:13px;font-weight:600;color:#111;background:#fff">
                        @foreach($wallets as $w)
                        <option value="{{ $w }}">{{ strtoupper($w) }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="margin-bottom:14px">
                    <label style="font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:6px">Exchange Rate *</label>
                    <input type="number" name="rate" required step="0.0001" placeholder="e.g. 1.0000 for 1:1"
                        style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:9px;font-size:13px;color:#111;box-sizing:border-box">
                    <p style="font-size:11px;color:#9ca3af;margin:4px 0 0">Units of TO_WALLET per 1 unit of FROM_WALLET</p>
                </div>
                <div style="margin-bottom:18px">
                    <label style="font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:6px">Service Fee (%)</label>
                    <input type="number" name="fee_percentage" step="0.01" value="1.00" min="0" max="100"
                        style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:9px;font-size:13px;color:#111;box-sizing:border-box">
                </div>
                <button type="submit" style="width:100%;padding:12px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:800;cursor:pointer">
                    <i class="fas fa-save"></i> Save Rate
                </button>
            </form>
        </div>
    </div>
</div>

{{-- ── Wallet Overview ── --}}
<div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6">
        <h3 style="font-size:14px;font-weight:700;color:#111;margin:0">Wallet Overview</h3>
    </div>
    <div style="padding:18px">
        <p style="font-size:12px;color:#9ca3af;margin:0 0 14px">Supported wallets. Each pair needs a separate rate entry (and its reverse if needed).</p>
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px">
            @foreach(['evc'=>['EVC Plus','#E74C3C'],'edahab'=>['eDahab','#27AE60'],'jeep'=>['Jeep Money','#2980B9'],'premier'=>['Premier','#8E44AD'],'ebesa'=>['eBesa','#D35400']] as $key => $info)
            @php
            $rateCount = $rates->where('from_wallet', $key)->count() + $rates->where('to_wallet', $key)->count();
            @endphp
            <div style="border:2px solid {{ $info[1] }};border-radius:12px;padding:16px;text-align:center;background:{{ $info[1] }}08">
                <div style="font-size:18px;font-weight:900;color:{{ $info[1] }}">{{ strtoupper($key) }}</div>
                <div style="font-size:12px;font-weight:600;color:#374151;margin-top:3px">{{ $info[0] }}</div>
                <div style="font-size:11px;color:#9ca3af;margin-top:4px">{{ $rateCount }} rate{{ $rateCount != 1 ? 's' : '' }}</div>
            </div>
            @endforeach
        </div>
    </div>
</div>

@endsection
