@extends('admin.layouts.app')
@section('title', 'eWholesale — Reports')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px">
    <h2 style="margin:0;font-size:20px;font-weight:700;color:#1B1444">Reports</h2>
    <div style="display:flex;gap:8px;align-items:center">
        <form method="GET">
            <select name="period" onchange="this.form.submit()" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
                @foreach([7=>'Last 7 days', 30=>'Last 30 days', 90=>'Last 90 days'] as $d => $label)
                <option value="{{ $d }}" @selected($period==$d)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('admin.module-data.wholesale.reports.export', ['period'=>$period,'type'=>'orders']) }}" style="padding:8px 14px;background:#1B1444;color:#fff;border-radius:6px;font-size:13px;text-decoration:none">⬇ CSV</a>
    </div>
</div>

{{-- Summary cards --}}
<div style="display:flex;gap:14px;margin-bottom:24px;flex-wrap:wrap">
    @foreach([
        ['Total Orders',       $totalOrders,                    '#3b82f6'],
        ['RFQ Conversion',     $rfqConvRate.'%',                '#8b5cf6'],
        ['Dispute Rate',       $dispRate.'%',                   '#ef4444'],
        ['RFQs Awarded',       $rfqAwarded.' / '.$rfqTotal,    '#10b981'],
    ] as [$label,$val,$color])
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px 20px;border-top:3px solid {{ $color }}">
        <div style="font-size:22px;font-weight:700;color:#1B1444">{{ $val }}</div>
        <div style="font-size:12px;color:#6b7280">{{ $label }}</div>
    </div>
    @endforeach
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

{{-- GMV by Supplier --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 16px;font-size:14px;font-weight:600;color:#1B1444">GMV by Supplier</h3>
    @php $maxGmv = $gmvBySupplier->max('gmv') ?: 1; @endphp
    @forelse($gmvBySupplier as $row)
    <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px">
            <span style="color:#374151;font-weight:500">{{ $row->supplier?->display_name ?? 'Unknown' }}</span>
            <span style="color:#10b981;font-weight:600">${{ number_format($row->gmv,0) }}</span>
        </div>
        <div style="height:6px;background:#f3f4f6;border-radius:3px;overflow:hidden">
            <div style="height:100%;width:{{ round($row->gmv/$maxGmv*100) }}%;background:#F7941D;border-radius:3px"></div>
        </div>
        <div style="font-size:11px;color:#9ca3af;margin-top:2px">{{ $row->orders }} orders</div>
    </div>
    @empty
    <p style="color:#9ca3af;font-size:13px;margin:0">No data.</p>
    @endforelse
</div>

{{-- GMV by Category --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 16px;font-size:14px;font-weight:600;color:#1B1444">GMV by Category</h3>
    @php $maxRev = $gmvByCategory->max('revenue') ?: 1; @endphp
    @forelse($gmvByCategory as $row)
    <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px">
            <span style="color:#374151;font-weight:500">{{ $row['category']?->name ?? 'Unknown' }}</span>
            <span style="color:#10b981;font-weight:600">${{ number_format($row['revenue'],0) }}</span>
        </div>
        <div style="height:6px;background:#f3f4f6;border-radius:3px;overflow:hidden">
            <div style="height:100%;width:{{ round($row['revenue']/$maxRev*100) }}%;background:#8b5cf6;border-radius:3px"></div>
        </div>
        <div style="font-size:11px;color:#9ca3af;margin-top:2px">{{ $row['orders'] }} orders</div>
    </div>
    @empty
    <p style="color:#9ca3af;font-size:13px;margin:0">No data.</p>
    @endforelse
</div>
</div>
</div>
@endsection
