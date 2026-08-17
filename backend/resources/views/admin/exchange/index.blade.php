@extends('admin.layouts.app')
@section('title', 'eExchange — Orders & Analytics')

@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
        <h1 class="page-title">💱 eExchange Dashboard</h1>
        <p class="page-subtitle">Local wallet swaps — real-time monitoring & fraud prevention</p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="{{ route('admin.exchange.users') }}" style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:var(--brand,#f59e0b);color:#fff;border-radius:9px;font-weight:700;font-size:13px;text-decoration:none">
            <i class="fas fa-users"></i> User Accounts
        </a>
        <a href="{{ route('admin.crypto.index') }}" style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border-radius:9px;font-weight:700;font-size:13px;text-decoration:none">
            <i class="fas fa-bitcoin"></i> Crypto
        </a>
    </div>
</div>

{{-- ── Crypto Toggle ── --}}
@php $cryptoOn = \App\Models\Global\GlobalSetting::getBool('crypto_exchange_enabled', true); @endphp
<div id="cryptoToggleWrap" style="
    display:flex;align-items:center;justify-content:space-between;
    padding:14px 20px;border-radius:12px;margin-bottom:20px;
    background:{{ $cryptoOn ? 'linear-gradient(135deg,#4f46e5,#7c3aed)' : 'linear-gradient(135deg,#374151,#1f2937)' }};
    box-shadow:0 4px 16px {{ $cryptoOn ? 'rgba(99,102,241,.3)' : 'rgba(0,0,0,.2)' }};
">
    <div style="display:flex;align-items:center;gap:12px">
        <div style="width:40px;height:40px;background:rgba(255,255,255,.15);border-radius:10px;display:flex;align-items:center;justify-content:center">
            <i id="cryptoIcon" class="fas {{ $cryptoOn ? 'fa-bitcoin' : 'fa-ban' }}" style="font-size:18px;color:#fff"></i>
        </div>
        <div>
            <div style="font-size:14px;font-weight:800;color:#fff">Crypto Exchange Tab</div>
            <div id="cryptoStatus" style="font-size:11px;color:rgba(255,255,255,.65);margin-top:2px">
                {{ $cryptoOn ? '🟢 Enabled — users can see & use crypto tab' : '🔴 Disabled — crypto tab hidden from all users' }}
            </div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:12px">
        <span id="cryptoBadge" style="font-size:11px;font-weight:800;padding:4px 12px;border-radius:20px;background:rgba(255,255,255,.2);color:#fff">
            {{ $cryptoOn ? 'ENABLED' : 'DISABLED' }}
        </span>
        <label style="position:relative;display:inline-block;width:54px;height:28px;cursor:pointer">
            <input type="checkbox" id="cryptoInput" {{ $cryptoOn ? 'checked' : '' }}
                style="opacity:0;width:0;height:0" onchange="toggleCrypto(this.checked)">
            <span style="position:absolute;inset:0;border-radius:28px;background:rgba(255,255,255,.25)"></span>
            <span id="cryptoThumb" style="position:absolute;top:3px;left:{{ $cryptoOn ? '29px' : '3px' }};width:22px;height:22px;border-radius:50%;background:#fff;box-shadow:0 2px 5px rgba(0,0,0,.3);transition:.3s"></span>
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
        wrap.style.background   = 'linear-gradient(135deg,#4f46e5,#7c3aed)';
        wrap.style.boxShadow    = '0 4px 16px rgba(99,102,241,.3)';
        icon.className          = 'fas fa-bitcoin';
        status.textContent      = '🟢 Enabled — users can see & use crypto tab';
        badge.textContent       = 'ENABLED';
        thumb.style.left        = '29px';
    } else {
        wrap.style.background   = 'linear-gradient(135deg,#374151,#1f2937)';
        wrap.style.boxShadow    = '0 4px 16px rgba(0,0,0,.2)';
        icon.className          = 'fas fa-ban';
        status.textContent      = '🔴 Disabled — crypto tab hidden from all users';
        badge.textContent       = 'DISABLED';
        thumb.style.left        = '3px';
    }
    fetch('{{ route('admin.global.settings.toggle-crypto') }}', {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
        body:JSON.stringify({enabled}),
    })
    .then(r=>r.json())
    .then(()=>{ saving.textContent = enabled ? '✅ Enabled' : '✅ Disabled'; setTimeout(()=>{ saving.style.display='none'; saving.textContent='Saving…'; },2000); })
    .catch(()=>{ saving.textContent = '❌ Error'; setTimeout(()=>{ saving.style.display='none'; saving.textContent='Saving…'; },2000); });
}
</script>

{{-- ── KPI Cards ── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px">
@php
$kpis = [
    ['label'=>'Total Orders',    'value'=>number_format($stats['total']),          'sub'=>$stats['today'].' today',               'icon'=>'fa-exchange-alt',   'color'=>'#6366f1'],
    ['label'=>'Pending',         'value'=>number_format($stats['pending']),         'sub'=>'awaiting processing',                  'icon'=>'fa-clock',          'color'=>'#f59e0b'],
    ['label'=>'Completed',       'value'=>number_format($stats['completed']),       'sub'=>'successful',                           'icon'=>'fa-check-circle',   'color'=>'#10b981'],
    ['label'=>'Volume Today',    'value'=>'$'.number_format($stats['volume_today'],2),'sub'=>'$'.number_format($stats['volume'],2).' all time','icon'=>'fa-dollar-sign','color'=>'#3b82f6'],
    ['label'=>'Fees Collected',  'value'=>'$'.number_format($stats['fees'],2),     'sub'=>'$'.number_format($stats['fees_today'],2).' today','icon'=>'fa-hand-holding-usd','color'=>'#10b981'],
    ['label'=>'⚠️ Large Orders', 'value'=>number_format($stats['large_orders']),   'sub'=>'≥$500 today',                          'icon'=>'fa-exclamation-triangle','color'=>'#ef4444'],
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
    {{-- 7-day chart --}}
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
    {{-- Top pairs --}}
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

{{-- ── Filters ── --}}
<div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;padding:14px 18px;margin-bottom:16px">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div style="flex:2;min-width:180px">
            <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Reference, phone, user..." style="width:100%;padding:7px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
        </div>
        <div style="min-width:120px">
            <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px">From Wallet</label>
            <select name="from_wallet" style="width:100%;padding:7px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
                <option value="">All</option>
                @foreach(['EVC','EDAHAB','JEEP','PREMIER','EBESA'] as $w)
                <option value="{{ $w }}" {{ request('from_wallet')==$w?'selected':'' }}>{{ $w }}</option>
                @endforeach
            </select>
        </div>
        <div style="min-width:120px">
            <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px">To Wallet</label>
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
            <a href="{{ route('admin.exchange.index') }}" style="padding:8px 16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;font-weight:600;font-size:13px;color:#374151;text-decoration:none">Clear</a>
        </div>
    </form>
</div>

{{-- ── Orders Table ── --}}
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
                <th style="padding:9px 14px;text-align:right;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Fee</th>
                <th style="padding:9px 14px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Recipient</th>
                <th style="padding:9px 14px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Status</th>
                <th style="padding:9px 14px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Date</th>
                <th style="padding:9px 14px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $o)
            @php
            $sc = ['pending'=>['#f59e0b','#fffbeb'],'processing'=>['#6366f1','#eef2ff'],'completed'=>['#10b981','#ecfdf5'],'failed'=>['#ef4444','#fef2f2']];
            $c = $sc[$o->status] ?? ['#9ca3af','#f9fafb'];
            $isFraud = $o->sent_amount >= 500;
            @endphp
            <tr style="border-top:1px solid #f3f4f6{{ $isFraud ? ';background:#fff7ed' : '' }}">
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
                <td style="padding:9px 14px;text-align:right;font-size:12px;color:#6b7280">${{ number_format($o->fee_amount,2) }}</td>
                <td style="padding:9px 14px;font-size:11px;color:#374151">{{ $o->recipient_phone }}</td>
                <td style="padding:9px 14px;text-align:center">
                    <span style="padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;color:{{ $c[0] }};background:{{ $c[1] }}">{{ strtoupper($o->status) }}</span>
                </td>
                <td style="padding:9px 14px;font-size:11px;color:#9ca3af;white-space:nowrap">{{ \Carbon\Carbon::parse($o->created_at)->format('d M y H:i') }}</td>
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
            <tr><td colspan="10" style="padding:32px;text-align:center;color:#9ca3af;font-size:13px">No exchange orders found</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
    @if($orders->hasPages())
    <div style="padding:14px 18px;border-top:1px solid #f3f4f6">{{ $orders->links() }}</div>
    @endif
</div>

<script>
function toggleAll(cb) {
    document.querySelectorAll('.row-check').forEach(c => c.checked = cb.checked);
}
function bulkDelete() {
    const ids = [...document.querySelectorAll('.row-check:checked')].map(c => c.value);
    if (!ids.length) { alert('Select orders first'); return; }
    if (!confirm('Delete ' + ids.length + ' order(s)?')) return;
    document.getElementById('bulkIds').value = ids.join(',');
    document.getElementById('bulkForm').submit();
}
</script>
@endsection
