@extends('admin.layouts.app')
@section('title', 'eWholesale — Dashboard')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">

{{-- Attention bar --}}
@if($attentionItems->isNotEmpty())
<div style="margin-bottom:20px">
    @foreach($attentionItems as $item)
    <a href="{{ $item['url'] }}" style="display:flex;align-items:center;gap:10px;padding:10px 16px;margin-bottom:6px;border-radius:8px;text-decoration:none;
        background:{{ $item['type']==='danger'?'#fff1f1':($item['type']==='warning'?'#fffbeb':'#eff6ff') }};
        border-left:4px solid {{ $item['type']==='danger'?'#ef4444':($item['type']==='warning'?'#f59e0b':'#3b82f6') }};
        color:{{ $item['type']==='danger'?'#991b1b':($item['type']==='warning'?'#92400e':'#1e40af') }}">
        <span>{{ $item['type']==='danger'?'🔴':($item['type']==='warning'?'🟡':'🔵') }}</span>
        <span style="font-size:13px;font-weight:500">{{ $item['msg'] }}</span>
        <span style="margin-left:auto;font-size:12px">View →</span>
    </a>
    @endforeach
</div>
@endif

{{-- KPI cards --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:28px">
    @php
    $cards = [
        ['label'=>'Total GMV',          'value'=>'$'.number_format($gmvTotal,2), 'icon'=>'💰', 'color'=>'#10b981'],
        ['label'=>'GMV (30d)',           'value'=>'$'.number_format($gmv30,2),    'icon'=>'📅', 'color'=>'#3b82f6'],
        ['label'=>'Total Orders',        'value'=>number_format($ordersCount),    'icon'=>'🛒', 'color'=>'#8b5cf6'],
        ['label'=>'Awaiting Confirm.',   'value'=>$awaitingConf,                  'icon'=>'⏳', 'color'=>'#f59e0b'],
        ['label'=>'Open RFQs',           'value'=>$openRfqs,                      'icon'=>'📋', 'color'=>'#06b6d4'],
        ['label'=>'Pending Verify',      'value'=>$pendingVerif,                  'icon'=>'✅', 'color'=>'#f97316'],
        ['label'=>'Pending KYB',         'value'=>$pendingKyb,                    'icon'=>'🪪', 'color'=>'#ec4899'],
        ['label'=>'Open Disputes',       'value'=>$openDisp,                      'icon'=>'⚠️', 'color'=>'#ef4444'],
        ['label'=>'Credit Exposure',     'value'=>'$'.number_format($creditExp,2),'icon'=>'💳', 'color'=>'#6366f1'],
    ];
    @endphp
    @foreach($cards as $c)
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px 18px;border-top:3px solid {{ $c['color'] }}">
        <div style="font-size:24px;margin-bottom:6px">{{ $c['icon'] }}</div>
        <div style="font-size:22px;font-weight:700;color:#1B1444">{{ $c['value'] }}</div>
        <div style="font-size:12px;color:#6b7280;margin-top:2px">{{ $c['label'] }}</div>
    </div>
    @endforeach
</div>

{{-- GMV Chart + Top Suppliers --}}
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
        <h3 style="margin:0 0 16px;font-size:15px;font-weight:600;color:#1B1444">GMV – last 12 weeks</h3>
        @php $maxGmv = $gmvChart->max('gmv') ?: 1; @endphp
        <div style="display:flex;align-items:flex-end;gap:4px;height:120px">
            @foreach($gmvChart as $wk)
            @php $h = max(4, round($wk['gmv']/$maxGmv*110)); @endphp
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
                <div title="${{ number_format($wk['gmv'],2) }}" style="width:100%;height:{{ $h }}px;background:#F7941D;border-radius:3px 3px 0 0;opacity:.85"></div>
                <div style="font-size:9px;color:#9ca3af;white-space:nowrap">{{ $wk['week'] }}</div>
            </div>
            @endforeach
        </div>
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
        <h3 style="margin:0 0 14px;font-size:15px;font-weight:600;color:#1B1444">Top Suppliers (30d)</h3>
        @foreach($topSuppliers as $s)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f3f4f6">
            <span style="font-size:13px;font-weight:500;color:#1B1444">{{ $s->supplier?->display_name ?? 'Unknown' }}</span>
            <span style="font-size:12px;font-weight:600;color:#10b981">${{ number_format($s->gmv,0) }}</span>
        </div>
        @endforeach
        @if($topSuppliers->isEmpty())
        <p style="font-size:13px;color:#9ca3af;margin:0">No data yet.</p>
        @endif
    </div>
</div>

{{-- Top Categories --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:15px;font-weight:600;color:#1B1444">Top Categories (30d)</h3>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead><tr style="border-bottom:2px solid #f3f4f6">
            <th style="padding:8px 12px;text-align:left;color:#6b7280;font-weight:500">Category</th>
            <th style="padding:8px 12px;text-align:right;color:#6b7280;font-weight:500">Orders</th>
            <th style="padding:8px 12px;text-align:right;color:#6b7280;font-weight:500">Revenue</th>
        </tr></thead>
        <tbody>
        @foreach($topCategories as $cat)
        <tr style="border-bottom:1px solid #f9fafb">
            <td style="padding:8px 12px;color:#1B1444">{{ $cat['category']?->name ?? 'Unknown' }}</td>
            <td style="padding:8px 12px;text-align:right">{{ $cat['cnt'] }}</td>
            <td style="padding:8px 12px;text-align:right;font-weight:600;color:#10b981">${{ number_format($cat['revenue'],2) }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>

</div>
@endsection
