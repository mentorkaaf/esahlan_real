@extends('admin.layouts.app')
@section('title', 'Revenue Reports')

@section('content')
<div class="page-header">
    <div><h1 class="page-title">💰 Revenue Reports</h1><p class="page-subtitle">Financial performance &amp; payment breakdown</p></div>
    <div style="display:flex;gap:10px">
        <a href="{{ route('admin.global.revenue.export', request()->all()) }}" style="padding:9px 16px;background:#10b981;color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:6px">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>
</div>

{{-- Period Filters --}}
<form method="GET" style="background:#fff;border-radius:10px;padding:14px 18px;margin-bottom:18px;border:1px solid #e5e7eb;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    @foreach([7=>'Last 7 days',30=>'Last 30 days',90=>'Last 90 days'] as $d => $l)
    <a href="?period={{ $d }}" style="padding:7px 14px;border-radius:7px;font-size:12px;font-weight:700;text-decoration:none;{{ request('period')==$d ? 'background:#6366f1;color:#fff' : 'background:#f3f4f6;color:#374151' }}">{{ $l }}</a>
    @endforeach
    <div style="display:flex;gap:8px;align-items:center;margin-left:auto">
        <input type="date" name="from" value="{{ is_string($from) ? $from : $from->format('Y-m-d') }}" style="padding:7px 10px;border:1px solid #d1d5db;border-radius:7px;font-size:12px">
        <span style="font-size:12px;color:#6b7280">to</span>
        <input type="date" name="to" value="{{ is_string($to) ? substr($to,0,10) : $to->format('Y-m-d') }}" style="padding:7px 10px;border:1px solid #d1d5db;border-radius:7px;font-size:12px">
        <button type="submit" style="padding:7px 16px;background:#6366f1;color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer">Apply</button>
    </div>
</form>

{{-- KPI Cards --}}
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:20px">
    @php
    $kpis=[
        ['Gross Revenue','$'.number_format($totals['gross'],2),'#10b981','fa-arrow-trend-up'],
        ['Refunds','$'.number_format($totals['refunded'],2),'#ef4444','fa-rotate-left'],
        ['Net Revenue','$'.number_format($totals['net'],2),'#6366f1','fa-dollar-sign'],
        ['Transactions',number_format($totals['txns']),'#f59e0b','fa-credit-card'],
        ['Growth',$growth.'%',($growth>=0?'#10b981':'#ef4444'),'fa-chart-line'],
    ];
    @endphp
    @foreach($kpis as [$l,$v,$c,$i])
    <div style="background:#fff;border-radius:10px;padding:16px;border:1px solid #e5e7eb">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
            <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px">{{ $l }}</div>
            <i class="fas {{ $i }}" style="color:{{ $c }};font-size:14px"></i>
        </div>
        <div style="font-size:22px;font-weight:800;color:{{ $c }}">{{ $v }}</div>
        @if($l === 'Growth')<div style="font-size:10px;color:#9ca3af;margin-top:2px">vs previous period</div>@endif
    </div>
    @endforeach
</div>

<div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:18px">

{{-- Revenue Chart --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:20px">
    <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">Daily Revenue</h3>
    @php $maxR = $revenueByDay->max('revenue') ?: 1; @endphp
    <div style="display:flex;align-items:flex-end;gap:4px;height:130px;margin-bottom:8px">
        @foreach($revenueByDay as $d)
        @php $h = max(2, ($d->revenue/$maxR)*120); @endphp
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px">
            <div style="width:100%;background:linear-gradient(to top,#6366f1,#818cf8);border-radius:2px 2px 0 0;height:{{ $h }}px;min-height:2px" title="${{ number_format($d->revenue,2) }} · {{ $d->date }}"></div>
        </div>
        @endforeach
    </div>
    <div style="display:flex;justify-content:space-between;font-size:10px;color:#9ca3af">
        <span>{{ $revenueByDay->first()?->date }}</span>
        <span>{{ $revenueByDay->last()?->date }}</span>
    </div>
</div>

{{-- By Payment Method --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><h3 style="font-size:13px;font-weight:700;color:#111">By Method</h3></div>
    @php $methodColors=['stripe'=>'#635bff','paypal'=>'#003087']; @endphp
    @foreach($byMethod as $m)
    <div style="padding:12px 18px;border-top:1px solid #f3f4f6">
        <div style="display:flex;justify-content:space-between;margin-bottom:5px">
            <span style="font-size:12px;font-weight:700;color:#111;text-transform:capitalize">
                {{ $m->method === 'stripe' ? '💳 Stripe' : '🅿 PayPal' }}
            </span>
            <span style="font-size:13px;font-weight:800;color:#111">${{ number_format($m->revenue,2) }}</span>
        </div>
        <div style="height:5px;background:#f3f4f6;border-radius:3px;overflow:hidden">
            <div style="height:100%;background:{{ $methodColors[$m->method]??'#9ca3af' }};border-radius:3px;width:{{ $byMethod->max('revenue') > 0 ? ($m->revenue/$byMethod->max('revenue')*100) : 0 }}%"></div>
        </div>
        <div style="font-size:10px;color:#9ca3af;margin-top:3px">{{ $m->count }} transactions</div>
    </div>
    @endforeach
</div>

{{-- By Country --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><h3 style="font-size:13px;font-weight:700;color:#111">By Country</h3></div>
    @foreach($byCountry->take(8) as $c)
    <div style="padding:9px 18px;border-top:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center">
        <div style="font-size:12px;color:#374151;font-weight:600">{{ $c->shipping_country }}</div>
        <div style="text-align:right">
            <div style="font-size:12px;font-weight:700;color:#111">${{ number_format($c->revenue,0) }}</div>
            <div style="font-size:10px;color:#9ca3af">{{ $c->orders }} orders</div>
        </div>
    </div>
    @endforeach
</div>

</div>
@endsection
