@extends('admin.layouts.app')
@section('title', 'Global Analytics')

@section('content')
<div class="page-header">
    <div><h1 class="page-title">📊 Global Analytics</h1><p class="page-subtitle">Last 30 days — USA & Europe store insights</p></div>
</div>

{{-- KPI Row --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px">
    @php
    $kpis = [
        ['Total Users',$totalUsers,'#6366f1','fa-users','registered'],
        ['Conversion Rate',$conversionRate.'%','#10b981','fa-percent','users with orders'],
        ['Avg. Order Value','$'.number_format($aov,2),'#f59e0b','fa-shopping-bag','AOV'],
        ['Ordered Users',$usersWithOrders,'#3b82f6','fa-check','placed ≥1 order'],
    ];
    @endphp
    @foreach($kpis as [$l,$v,$c,$i,$sub])
    <div style="background:#fff;border-radius:10px;padding:18px;border:1px solid #e5e7eb;display:flex;align-items:flex-start;gap:12px">
        <div style="width:40px;height:40px;border-radius:9px;background:{{ $c }}18;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas {{ $i }}" style="color:{{ $c }};font-size:16px"></i></div>
        <div><div style="font-size:20px;font-weight:800;color:#111;line-height:1.1">{{ $v }}</div><div style="font-size:12px;font-weight:600;color:#374151;margin-top:2px">{{ $l }}</div><div style="font-size:11px;color:#9ca3af">{{ $sub }}</div></div>
    </div>
    @endforeach
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

{{-- Orders & Revenue Chart --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:20px">
    <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">Orders & Revenue — Last 30 Days</h3>
    @php $maxRev = $dailyOrders->max('revenue') ?: 1; @endphp
    <div style="display:flex;align-items:flex-end;gap:3px;height:120px;margin-bottom:10px">
        @foreach($dailyOrders as $d)
        @php $h = max(2, ($d->revenue/$maxRev)*110); @endphp
        <div style="flex:1;background:linear-gradient(to top,#6366f1,#818cf8);border-radius:2px 2px 0 0;height:{{ $h }}px;min-height:2px;cursor:pointer" title="${{ number_format($d->revenue,2) }} · {{ $d->orders }} orders · {{ $d->date }}"></div>
        @endforeach
    </div>
    <div style="display:flex;justify-content:space-between;font-size:10px;color:#9ca3af">
        <span>{{ $dailyOrders->first()?->date }}</span>
        <span>{{ $dailyOrders->last()?->date }}</span>
    </div>
</div>

{{-- New Users Chart --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:20px">
    <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">New Users — Last 30 Days</h3>
    @php $maxU = $newUsers->max('users') ?: 1; @endphp
    <div style="display:flex;align-items:flex-end;gap:3px;height:120px;margin-bottom:10px">
        @foreach($newUsers as $d)
        @php $h = max(2, ($d->users/$maxU)*110); @endphp
        <div style="flex:1;background:linear-gradient(to top,#10b981,#34d399);border-radius:2px 2px 0 0;height:{{ $h }}px;min-height:2px" title="{{ $d->users }} users · {{ $d->date }}"></div>
        @endforeach
    </div>
</div>

</div>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px">

{{-- Top Products --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><h3 style="font-size:13px;font-weight:700;color:#111">🏆 Top Products by Revenue</h3></div>
    @foreach($topProducts as $i => $p)
    <div style="padding:10px 18px;border-top:1px solid #f3f4f6;display:flex;align-items:center;gap:10px">
        <div style="width:24px;height:24px;border-radius:50%;background:{{ ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6'][$i] ?? '#9ca3af' }};color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0">{{ $i+1 }}</div>
        @if($p->thumbnail)<img src="{{ $p->thumbnail }}" style="width:30px;height:30px;border-radius:5px;object-fit:cover">@endif
        <div style="flex:1;min-width:0">
            <div style="font-size:12px;font-weight:600;color:#111;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $p->name }}</div>
            <div style="font-size:10px;color:#9ca3af">{{ $p->units }} units</div>
        </div>
        <div style="font-size:13px;font-weight:700;color:#111;flex-shrink:0">${{ number_format($p->revenue,0) }}</div>
    </div>
    @endforeach
</div>

{{-- Orders by Country --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><h3 style="font-size:13px;font-weight:700;color:#111">🌍 Orders by Country</h3></div>
    @foreach($byCountry as $c)
    <div style="padding:10px 18px;border-top:1px solid #f3f4f6;display:flex;align-items:center;gap:10px">
        <div style="font-size:13px;font-weight:700;color:#111;width:30px">{{ $c->shipping_country }}</div>
        <div style="flex:1">
            <div style="height:6px;background:#f3f4f6;border-radius:3px;overflow:hidden">
                <div style="height:100%;background:#6366f1;border-radius:3px;width:{{ $byCountry->max('orders') > 0 ? ($c->orders/$byCountry->max('orders')*100) : 0 }}%"></div>
            </div>
        </div>
        <div style="font-size:12px;color:#374151;width:50px;text-align:right">{{ $c->orders }} orders</div>
        <div style="font-size:12px;font-weight:700;color:#111;width:60px;text-align:right">${{ number_format($c->revenue,0) }}</div>
    </div>
    @endforeach
</div>

{{-- Order Status Breakdown --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><h3 style="font-size:13px;font-weight:700;color:#111">📋 Order Status</h3></div>
    @php
    $statusColors=['pending'=>'#f59e0b','paid'=>'#10b981','processing'=>'#6366f1','shipped'=>'#3b82f6','delivered'=>'#10b981','cancelled'=>'#ef4444','refunded'=>'#8b5cf6','on_hold'=>'#f97316'];
    $total = $statusBreakdown->sum();
    @endphp
    @foreach($statusBreakdown as $status => $count)
    @php $pct = $total > 0 ? round($count/$total*100) : 0; @endphp
    <div style="padding:9px 18px;border-top:1px solid #f3f4f6;display:flex;align-items:center;gap:10px">
        <div style="width:8px;height:8px;border-radius:50%;background:{{ $statusColors[$status]??'#9ca3af' }};flex-shrink:0"></div>
        <div style="flex:1;font-size:12px;color:#374151;text-transform:capitalize">{{ str_replace('_',' ',$status) }}</div>
        <div style="font-size:12px;font-weight:700;color:#111">{{ $count }}</div>
        <div style="font-size:11px;color:#9ca3af;width:32px;text-align:right">{{ $pct }}%</div>
    </div>
    @endforeach
</div>

</div>
@endsection
