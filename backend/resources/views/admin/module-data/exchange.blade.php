@extends('admin.layouts.app')
@section('title', 'eExchange — Management')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <div>
        <h1 class="page-title">⇄ eExchange Management</h1>
        <p class="page-subtitle">Rates, crypto settings & order management — all in one place</p>
    </div>
    <a href="{{ route('admin.exchange.users') }}" style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:var(--brand,#f59e0b);color:#fff;border-radius:9px;font-weight:700;font-size:13px;text-decoration:none">
        <i class="fas fa-users"></i> User Accounts
    </a>
</div>

{{-- ── Crypto Toggle ── --}}
@php $cryptoOn = $cryptoOn ?? true; @endphp
<div id="cryptoToggleWrap" style="
    display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;
    padding:16px 22px;border-radius:14px;margin-bottom:22px;
    background:{{ $cryptoOn ? 'linear-gradient(135deg,#4f46e5,#7c3aed)' : 'linear-gradient(135deg,#374151,#1f2937)' }};
    box-shadow:0 4px 18px {{ $cryptoOn ? 'rgba(99,102,241,.3)' : 'rgba(0,0,0,.15)' }};
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
            <span id="cryptoThumb" style="position:absolute;top:3px;left:{{ $cryptoOn ? '31px' : '3px' }};width:24px;height:24px;border-radius:50%;transition:.3s;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.25)"></span>
        </label>
        <span id="cryptoSaving" style="font-size:11px;color:rgba(255,255,255,.6);display:none">Saving…</span>
    </div>
</div>
<script>
function toggleCrypto(enabled) {
    const wrap=document.getElementById('cryptoToggleWrap'),icon=document.getElementById('cryptoIcon'),
          status=document.getElementById('cryptoStatus'),badge=document.getElementById('cryptoBadge'),
          thumb=document.getElementById('cryptoThumb'),saving=document.getElementById('cryptoSaving');
    saving.style.display='inline';
    if(enabled){
        wrap.style.background='linear-gradient(135deg,#4f46e5,#7c3aed)';wrap.style.boxShadow='0 4px 18px rgba(99,102,241,.3)';
        icon.className='fas fa-bitcoin';status.textContent='🟢 Enabled — users can buy/sell crypto in eExchange';
        badge.textContent='ENABLED';thumb.style.left='31px';
    }else{
        wrap.style.background='linear-gradient(135deg,#374151,#1f2937)';wrap.style.boxShadow='0 4px 18px rgba(0,0,0,.15)';
        icon.className='fas fa-ban';status.textContent='🔴 Disabled — crypto tab hidden from all users';
        badge.textContent='DISABLED';thumb.style.left='3px';
    }
    fetch('{{ route('admin.global.settings.toggle-crypto') }}',{
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
        body:JSON.stringify({enabled}),
    })
    .then(r=>r.json())
    .then(()=>{saving.textContent=enabled?'✅ Enabled':'✅ Disabled';setTimeout(()=>{saving.style.display='none';saving.textContent='Saving…';},2000);})
    .catch(()=>{saving.textContent='❌ Error';setTimeout(()=>{saving.style.display='none';saving.textContent='Saving…';},2000);});
}
</script>

{{-- ── KPI Cards ── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:22px">
@php
$kpis = [
    ['label'=>'Total Orders',   'value'=>number_format($stats['total']),              'sub'=>$stats['today'].' today',                               'icon'=>'fa-exchange-alt',       'color'=>'#6366f1'],
    ['label'=>'Pending',        'value'=>number_format($stats['pending']),             'sub'=>'awaiting processing',                                  'icon'=>'fa-clock',              'color'=>'#f59e0b'],
    ['label'=>'Completed',      'value'=>number_format($stats['completed']),           'sub'=>'successful',                                           'icon'=>'fa-check-circle',       'color'=>'#10b981'],
    ['label'=>'Volume Today',   'value'=>'$'.number_format($stats['volume_today'],2), 'sub'=>'$'.number_format($stats['volume'],2).' all time',       'icon'=>'fa-dollar-sign',        'color'=>'#3b82f6'],
    ['label'=>'Fees Collected', 'value'=>'$'.number_format($stats['fees'],2),         'sub'=>'$'.number_format($stats['fees_today'],2).' today',      'icon'=>'fa-hand-holding-usd',   'color'=>'#10b981'],
    ['label'=>'⚠️ Large Orders','value'=>number_format($stats['large_orders']),       'sub'=>'≥$500 today',                                          'icon'=>'fa-exclamation-triangle','color'=>'#ef4444'],
];
@endphp
@foreach($kpis as $k)
<div style="background:#fff;border-radius:11px;padding:16px 18px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;display:flex;align-items:flex-start;gap:12px">
    <div style="width:38px;height:38px;border-radius:9px;background:{{ $k['color'] }}18;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="fas {{ $k['icon'] }}" style="color:{{ $k['color'] }};font-size:16px"></i>
    </div>
    <div>
        <div style="font-size:20px;font-weight:800;color:#111;line-height:1.1">{{ $k['value'] }}</div>
        <div style="font-size:11px;font-weight:600;color:#374151;margin-top:2px">{{ $k['label'] }}</div>
        <div style="font-size:10px;color:#9ca3af;margin-top:1px">{{ $k['sub'] }}</div>
    </div>
</div>
@endforeach
</div>

{{-- ── Chart + Pair Volume ── --}}
<div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;margin-bottom:22px">
    <div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6">
            <h3 style="font-size:13px;font-weight:700;color:#111;margin:0">Orders — Last 7 Days</h3>
        </div>
        <div style="padding:16px 18px">
            @php $maxC = max($chart->pluck('count')->toArray() ?: [1]); @endphp
            <div style="display:flex;align-items:flex-end;gap:8px;height:100px">
                @foreach($chart as $day)
                @php $h = $maxC > 0 ? max(4, ($day['count']/$maxC)*80) : 4; @endphp
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
                    <div style="font-size:9px;font-weight:700;color:#374151">{{ $day['count'] }}</div>
                    <div style="width:100%;background:linear-gradient(to top,#6366f1,#818cf8);border-radius:3px 3px 0 0;height:{{ $h }}px;min-height:4px"></div>
                    <div style="font-size:9px;color:#9ca3af">{{\Carbon\Carbon::parse($day['date'])->format('M d')}}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    <div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6">
            <h3 style="font-size:13px;font-weight:700;color:#111;margin:0">Top Wallet Pairs</h3>
        </div>
        <div style="padding:10px 0">
            @forelse($pairVolume as $pair)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 18px;border-bottom:1px solid #f9fafb">
                <span style="font-size:12px;font-weight:700;color:#374151">{{ $pair->from_wallet }} → {{ $pair->to_wallet }}</span>
                <div style="text-align:right">
                    <div style="font-size:12px;font-weight:700;color:#111">{{ $pair->cnt }}x</div>
                    <div style="font-size:10px;color:#9ca3af">${{ number_format($pair->vol,0) }}</div>
                </div>
            </div>
            @empty
            <p style="text-align:center;color:#9ca3af;font-size:12px;padding:20px">No data yet</p>
            @endforelse
        </div>
    </div>
</div>

{{-- ── Section Tabs ── --}}
<div style="display:flex;gap:0;margin-bottom:0;border-bottom:2px solid #e5e7eb">
    <button onclick="showTab('orders')" id="tab-orders"
        style="padding:10px 22px;font-size:13px;font-weight:700;cursor:pointer;border:none;background:transparent;color:#6366f1;border-bottom:3px solid #6366f1;margin-bottom:-2px">
        📋 Orders ({{ $orders->total() }})
    </button>
    <button onclick="showTab('rates')" id="tab-rates"
        style="padding:10px 22px;font-size:13px;font-weight:700;cursor:pointer;border:none;background:transparent;color:#9ca3af;border-bottom:3px solid transparent;margin-bottom:-2px">
        ⇄ Rates
    </button>
</div>
<script>
function showTab(t) {
    ['orders','rates'].forEach(function(id){
        var btn=document.getElementById('tab-'+id);
        var sec=document.getElementById('sec-'+id);
        if(id===t){btn.style.color='#6366f1';btn.style.borderBottomColor='#6366f1';sec.style.display='block';}
        else{btn.style.color='#9ca3af';btn.style.borderBottomColor='transparent';sec.style.display='none';}
    });
}
// Show correct tab on page load (respect ?tab= or default to orders)
window.addEventListener('DOMContentLoaded',function(){
    var t = new URLSearchParams(window.location.search).get('tab') || 'orders';
    showTab(t);
});
</script>

{{-- ══════════════════════════════════════════════════════════ --}}
{{-- SECTION: ORDERS                                           --}}
{{-- ══════════════════════════════════════════════════════════ --}}
<div id="sec-orders" style="padding-top:20px">
    {{-- Filters --}}
    <div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;padding:14px 18px;margin-bottom:16px">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <input type="hidden" name="tab" value="orders">
            <div style="flex:2;min-width:180px">
                <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Reference, phone, user..." style="width:100%;padding:7px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;box-sizing:border-box">
            </div>
            <div style="min-width:110px">
                <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px">From</label>
                <select name="from_wallet" style="width:100%;padding:7px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
                    <option value="">All</option>
                    @foreach(['EVC','EDAHAB','JEEP','PREMIER','EBESA'] as $w)
                    <option value="{{ $w }}" {{ request('from_wallet')==$w?'selected':'' }}>{{ $w }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:110px">
                <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px">To</label>
                <select name="to_wallet" style="width:100%;padding:7px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
                    <option value="">All</option>
                    @foreach(['EVC','EDAHAB','JEEP','PREMIER','EBESA'] as $w)
                    <option value="{{ $w }}" {{ request('to_wallet')==$w?'selected':'' }}>{{ $w }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:110px">
                <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px">Status</label>
                <select name="status" style="width:100%;padding:7px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
                    <option value="">All</option>
                    @foreach(['pending','processing','completed','failed'] as $s)
                    <option value="{{ $s }}" {{ request('status')==$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:120px">
                <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px">Date From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" style="width:100%;padding:7px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
            </div>
            <div style="min-width:120px">
                <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px">Date To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" style="width:100%;padding:7px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
            </div>
            <div style="display:flex;gap:8px;align-items:flex-end">
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#ef4444;cursor:pointer">
                    <input type="checkbox" name="fraud_only" value="1" {{ request('fraud_only')?'checked':'' }}>
                    ⚠️ Fraud Only
                </label>
            </div>
            <div style="display:flex;gap:8px">
                <button type="submit" style="padding:8px 18px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer">Filter</button>
                <a href="{{ route('admin.module-data.exchange') }}" style="padding:8px 16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;font-weight:600;font-size:13px;color:#374151;text-decoration:none">Clear</a>
            </div>
        </form>
    </div>

    {{-- Orders Table --}}
    <div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center">
            <h3 style="font-size:13px;font-weight:700;color:#111;margin:0">Exchange Orders ({{ $orders->total() }})</h3>
            <form method="POST" action="{{ route('admin.exchange.bulk-destroy') }}" id="bulkForm">
                @csrf @method('DELETE')
                <input type="hidden" name="ids" id="bulkIds">
                <button type="button" onclick="bulkDelete()" style="padding:6px 14px;background:#ef4444;color:#fff;border:none;border-radius:7px;font-weight:700;font-size:12px;cursor:pointer">
                    🗑 Delete Selected
                </button>
            </form>
        </div>
        <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="background:#f9fafb">
                    <th style="padding:9px 14px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase"><input type="checkbox" id="selectAll" onchange="toggleAll(this)"></th>
                    <th style="padding:9px 14px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Reference</th>
                    <th style="padding:9px 14px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">User</th>
                    <th style="padding:9px 14px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Pair</th>
                    <th style="padding:9px 14px;text-align:right;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Amount</th>
                    <th style="padding:9px 14px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Recipient</th>
                    <th style="padding:9px 14px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Status</th>
                    <th style="padding:9px 14px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Date</th>
                    <th style="padding:9px 14px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                @php
                $sc=['pending'=>['#f59e0b','#fffbeb'],'processing'=>['#6366f1','#eef2ff'],'completed'=>['#10b981','#ecfdf5'],'failed'=>['#ef4444','#fef2f2']];
                $c=$sc[$o->status]??['#9ca3af','#f9fafb'];
                $isFraud=$o->sent_amount>=500;
                @endphp
                <tr style="border-top:1px solid #f3f4f6{{ $isFraud?';background:#fff7ed':'' }}">
                    <td style="padding:9px 14px"><input type="checkbox" class="row-check" value="{{ $o->id }}"></td>
                    <td style="padding:9px 14px">
                        <a href="{{ route('admin.exchange.show', $o->id) }}" style="font-size:12px;font-weight:700;color:#6366f1;text-decoration:none">{{ $o->reference }}</a>
                        @if($isFraud)<span style="margin-left:6px;font-size:10px;background:#fef3c7;color:#92400e;padding:2px 6px;border-radius:4px;font-weight:700">⚠️ LARGE</span>@endif
                    </td>
                    <td style="padding:9px 14px">
                        <div style="font-size:12px;font-weight:600;color:#111">{{ $o->user_name }}</div>
                        <div style="font-size:10px;color:#9ca3af">{{ $o->user_phone }}</div>
                    </td>
                    <td style="padding:9px 14px;text-align:center">
                        <span style="font-size:11px;font-weight:800;color:#374151;background:#f3f4f6;padding:3px 8px;border-radius:6px">
                            {{ $o->from_wallet }} → {{ $o->to_wallet }}
                        </span>
                    </td>
                    <td style="padding:9px 14px;text-align:right;font-size:13px;font-weight:800;color:#111">${{ number_format($o->sent_amount,2) }}</td>
                    <td style="padding:9px 14px;font-size:11px;color:#374151">{{ $o->recipient_phone }}</td>
                    <td style="padding:9px 14px;text-align:center">
                        <span style="padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;color:{{ $c[0] }};background:{{ $c[1] }}">{{ strtoupper($o->status) }}</span>
                    </td>
                    <td style="padding:9px 14px;font-size:11px;color:#9ca3af;white-space:nowrap">{{\Carbon\Carbon::parse($o->created_at)->format('d M y H:i')}}</td>
                    <td style="padding:9px 14px;text-align:center">
                        <div style="display:flex;gap:6px;justify-content:center">
                            <a href="{{ route('admin.exchange.show', $o->id) }}" style="padding:4px 10px;background:#f3f4f6;border-radius:6px;font-size:11px;font-weight:700;color:#374151;text-decoration:none">View</a>
                            <form method="POST" action="{{ route('admin.exchange.update-status', $o->id) }}" style="display:inline">
                                @csrf @method('PATCH')
                                <select name="status" onchange="this.form.submit()" style="padding:4px 6px;border:1px solid #e5e7eb;border-radius:6px;font-size:11px;font-weight:600;color:#374151">
                                    @foreach(['pending','processing','completed','failed'] as $st)
                                    <option value="{{ $st }}" {{ $o->status==$st?'selected':'' }}>{{ ucfirst($st) }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" style="padding:32px;text-align:center;color:#9ca3af;font-size:13px">No exchange orders found</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @if($orders->hasPages())
        <div style="padding:14px 18px;border-top:1px solid #f3f4f6">{{ $orders->appends(['tab'=>'orders'])->links() }}</div>
        @endif
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════ --}}
{{-- SECTION: RATES                                            --}}
{{-- ══════════════════════════════════════════════════════════ --}}
<div id="sec-rates" style="padding-top:20px;display:none">

    @if(session('success'))
    <div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:#065f46;font-weight:600;font-size:13px">
        ✅ {{ session('success') }}
    </div>
    @endif

    {{-- Info Banner --}}
    <div style="background:#fefce8;border:1px solid #fde047;border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:10px">
        <i class="fas fa-info-circle" style="color:#ca8a04;flex-shrink:0"></i>
        <div style="font-size:13px;color:#713f12">
            <strong>Fee Policy:</strong> Formula: <code style="background:#fef9c3;padding:2px 6px;border-radius:4px">received = sent × rate × (1 − fee%/100)</code>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:3fr 2fr;gap:20px;margin-bottom:22px">
        {{-- Rates Table --}}
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
                    @php $wColors=['evc'=>'#E74C3C','edahab'=>'#27AE60','jeep'=>'#2980B9','premier'=>'#8E44AD','ebesa'=>'#D35400']; @endphp
                    @forelse($rates as $rate)
                    @php $fc=$wColors[$rate->from_wallet]??'#6b7280';$tc=$wColors[$rate->to_wallet]??'#6b7280'; @endphp
                    <tr style="border-top:1px solid #f3f4f6">
                        <td style="padding:10px 16px">
                            <span style="display:inline-block;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:800;color:#fff;background:{{ $fc }}">{{ strtoupper($rate->from_wallet) }}</span>
                        </td>
                        <td style="padding:10px 16px">
                            <span style="display:inline-block;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:800;color:#fff;background:{{ $tc }}">{{ strtoupper($rate->to_wallet) }}</span>
                        </td>
                        <td style="padding:10px 16px;text-align:center;font-size:14px;font-weight:800;color:#111">{{ $rate->rate }}</td>
                        <td style="padding:10px 16px;text-align:center">
                            <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#fef3c7;color:#92400e">{{ $rate->fee_percentage ?? 1 }}%</span>
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
                    <tr><td colspan="5" style="padding:32px;text-align:center;color:#9ca3af;font-size:13px">No rates configured yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        {{-- Add Rate Form --}}
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
                        <input type="number" name="rate" required step="0.0001" placeholder="e.g. 1.0000"
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

    {{-- Wallet Overview --}}
    <div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6">
            <h3 style="font-size:14px;font-weight:700;color:#111;margin:0">Wallet Overview</h3>
        </div>
        <div style="padding:18px">
            <p style="font-size:12px;color:#9ca3af;margin:0 0 14px">Supported wallets and their rate count.</p>
            <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px">
                @foreach(['evc'=>['EVC Plus','#E74C3C'],'edahab'=>['eDahab','#27AE60'],'jeep'=>['Jeep Money','#2980B9'],'premier'=>['Premier','#8E44AD'],'ebesa'=>['eBesa','#D35400']] as $key => $info)
                @php $rateCount = $rates->where('from_wallet', $key)->count() + $rates->where('to_wallet', $key)->count(); @endphp
                <div style="border:2px solid {{ $info[1] }};border-radius:12px;padding:16px;text-align:center;background:{{ $info[1] }}08">
                    <div style="font-size:18px;font-weight:900;color:{{ $info[1] }}">{{ strtoupper($key) }}</div>
                    <div style="font-size:12px;font-weight:600;color:#374151;margin-top:3px">{{ $info[0] }}</div>
                    <div style="font-size:11px;color:#9ca3af;margin-top:4px">{{ $rateCount }} rate{{ $rateCount!=1?'s':'' }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── Wallet Logos ── --}}
    @php
        $walletDefs = [
            'evc'     => ['EVC Plus',             '#00A8E8'],
            'edahab'  => ['eDahab Merchant',      '#2E7D32'],
            'jeep'    => ['Jeep Money',            '#0D47A1'],
            'premier' => ['Premier Wallet',        '#1A237E'],
            'ebesa'   => ['eBesa',                 '#6A1B9A'],
        ];
    @endphp
    <div style="background:#fff;border-radius:14px;box-shadow:0 1px 4px rgba(0,0,0,.08);border:1px solid #e5e7eb;overflow:hidden;margin-top:24px">
        <div style="padding:16px 22px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:10px">
            <div style="width:34px;height:34px;background:#f0f4ff;border-radius:9px;display:flex;align-items:center;justify-content:center">
                <i class="fas fa-image" style="color:#4f46e5;font-size:14px"></i>
            </div>
            <div>
                <h3 style="font-size:14px;font-weight:800;color:#111;margin:0">Wallet Logos</h3>
                <p style="font-size:11px;color:#9ca3af;margin:0">Upload logo images shown in the Flutter app</p>
            </div>
        </div>
        <div style="padding:22px">
            @if(session('success'))
                <div style="background:#ecfdf5;border:1px solid #6ee7b7;border-radius:10px;padding:10px 16px;margin-bottom:16px;font-size:13px;color:#065f46;font-weight:600">
                    ✅ {{ session('success') }}
                </div>
            @endif
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px">
                @foreach($walletDefs as $wKey => $wInfo)
                @php
                    $currentLogo = \App\Models\Global\GlobalSetting::get("wallet_logo_{$wKey}");
                @endphp
                <div style="border:1.5px solid #e5e7eb;border-radius:12px;overflow:hidden">
                    {{-- Logo preview --}}
                    <div style="height:80px;background:#f9fafb;display:flex;align-items:center;justify-content:center;position:relative">
                        @if($currentLogo)
                            <img src="{{ $currentLogo }}" alt="{{ $wKey }}" style="max-height:60px;max-width:130px;object-fit:contain">
                            <span style="position:absolute;top:6px;right:6px;background:#10b981;color:#fff;font-size:9px;font-weight:800;padding:2px 6px;border-radius:4px">✓ SET</span>
                        @else
                            <div style="text-align:center">
                                <div style="font-size:20px;font-weight:900;color:{{ $wInfo[1] }}">{{ strtoupper($wKey) }}</div>
                                <div style="font-size:10px;color:#9ca3af">No logo yet</div>
                            </div>
                        @endif
                    </div>
                    {{-- Info + upload --}}
                    <div style="padding:12px">
                        <div style="font-size:12px;font-weight:800;color:#111;margin-bottom:2px">{{ strtoupper($wKey) }}</div>
                        <div style="font-size:10px;color:#9ca3af;margin-bottom:10px">{{ $wInfo[0] }}</div>
                        <form action="{{ route('admin.module-data.exchange.logo.upload') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="wallet" value="{{ $wKey }}">
                            <label style="display:block;width:100%;cursor:pointer">
                                <input type="file" name="logo" accept="image/*" style="display:none"
                                    onchange="this.closest('form').submit()">
                                <div style="text-align:center;padding:7px 10px;background:{{ $wInfo[1] }}12;border:1.5px dashed {{ $wInfo[1] }}55;border-radius:8px;font-size:11px;font-weight:700;color:{{ $wInfo[1] }};transition:.15s">
                                    <i class="fas fa-upload" style="margin-right:4px"></i>
                                    {{ $currentLogo ? 'Replace' : 'Upload' }} Logo
                                </div>
                            </label>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
            <p style="font-size:11px;color:#9ca3af;margin-top:14px;text-align:center">
                PNG / JPG / WebP · Max 2MB · Recommended: square 256×256px or higher
            </p>
        </div>
    </div>
</div>

<script>
function toggleAll(cb){document.querySelectorAll('.row-check').forEach(c=>c.checked=cb.checked);}
function bulkDelete(){
    const ids=[...document.querySelectorAll('.row-check:checked')].map(c=>c.value);
    if(!ids.length){alert('Select orders first');return;}
    if(!confirm('Delete '+ids.length+' order(s)?'))return;
    document.getElementById('bulkIds').value=ids.join(',');
    document.getElementById('bulkForm').submit();
}
</script>

@endsection
