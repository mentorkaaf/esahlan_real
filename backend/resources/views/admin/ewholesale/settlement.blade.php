@extends('admin.layouts.app')
@section('title', 'eWholesale — Settlement Report')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px">
    <div>
        <h2 style="margin:0;font-size:20px;font-weight:700;color:#1B1444">Settlement Report</h2>
        <p style="margin:4px 0 0;color:#6b7280;font-size:13px">GMV − platform fees = net payable to each supplier</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <form method="GET">
            <select name="period" onchange="this.form.submit()" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
                @foreach([7=>'Last 7 days', 30=>'Last 30 days', 90=>'Last 90 days'] as $d => $label)
                <option value="{{ $d }}" @selected($period==$d)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('admin.module-data.wholesale.settlement', ['period'=>$period,'export'=>'csv']) }}"
           style="padding:8px 14px;background:#1B1444;color:#fff;border-radius:6px;font-size:13px;text-decoration:none">⬇ Export CSV</a>
    </div>
</div>

{{-- Summary totals --}}
@php
    $totalGmv   = $rows->sum('gmv');
    $totalFees  = $rows->sum('total_fees');
    $totalNet   = $rows->sum('net_payable');
    $totalRef   = $rows->sum('refunds');
@endphp
<div style="display:flex;gap:14px;margin-bottom:24px;flex-wrap:wrap">
    @foreach([
        ['Total GMV',       '$'.number_format($totalGmv,2),  '#3b82f6'],
        ['Platform Fees',   '$'.number_format($totalFees,2), '#f59e0b'],
        ['Refunds',         '$'.number_format($totalRef,2),  '#ef4444'],
        ['Net Payable',     '$'.number_format($totalNet,2),  '#10b981'],
    ] as [$label,$val,$color])
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px 20px;border-top:3px solid {{ $color }};min-width:160px">
        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em">{{ $label }}</div>
        <div style="font-size:22px;font-weight:800;color:#111;margin-top:4px">{{ $val }}</div>
    </div>
    @endforeach
</div>

<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead>
<tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
    <th style="padding:10px 14px;text-align:left;font-weight:600;color:#374151">Supplier</th>
    <th style="padding:10px 14px;text-align:right;font-weight:600;color:#374151">Orders</th>
    <th style="padding:10px 14px;text-align:right;font-weight:600;color:#374151">GMV</th>
    <th style="padding:10px 14px;text-align:right;font-weight:600;color:#374151">Platform Fee</th>
    <th style="padding:10px 14px;text-align:right;font-weight:600;color:#374151">Refunds</th>
    <th style="padding:10px 14px;text-align:right;font-weight:600;color:#10b981">Net Payable</th>
    <th style="padding:10px 14px;text-align:center;font-weight:600;color:#374151">Fee %</th>
</tr>
</thead>
<tbody>
@forelse($rows as $row)
@php $feeRate = $row->gmv > 0 ? round($row->total_fees / $row->gmv * 100, 1) : 0; @endphp
<tr style="border-bottom:1px solid #f3f4f6">
    <td style="padding:10px 14px">
        <div style="font-weight:600;color:#111">{{ $row->supplier->display_name ?? 'Supplier #'.$row->supplier_id }}</div>
        @if($row->supplier)
        <div style="font-size:11px;margin-top:2px">
            <span style="padding:1px 6px;border-radius:9999px;font-size:10px;background:{{ match($row->supplier->verification) { 'gold'=>'#fef3c7', 'verified'=>'#dbeafe', default=>'#f3f4f6' } }};color:{{ match($row->supplier->verification) { 'gold'=>'#92400e', 'verified'=>'#1d4ed8', default=>'#6b7280' } }}">
                {{ ucfirst($row->supplier->verification) }}
            </span>
        </div>
        @endif
    </td>
    <td style="padding:10px 14px;text-align:right;color:#374151">{{ number_format($row->order_count) }}</td>
    <td style="padding:10px 14px;text-align:right;color:#374151">${{ number_format($row->gmv, 2) }}</td>
    <td style="padding:10px 14px;text-align:right;color:#f59e0b">${{ number_format($row->total_fees, 2) }}</td>
    <td style="padding:10px 14px;text-align:right;color:#ef4444">${{ number_format($row->refunds, 2) }}</td>
    <td style="padding:10px 14px;text-align:right;font-weight:700;color:#10b981">${{ number_format($row->net_payable, 2) }}</td>
    <td style="padding:10px 14px;text-align:center;color:#6b7280">{{ $feeRate }}%</td>
</tr>
@empty
<tr><td colspan="7" style="padding:32px;text-align:center;color:#6b7280">No orders in this period.</td></tr>
@endforelse
</tbody>
<tfoot>
<tr style="background:#f9fafb;border-top:2px solid #e5e7eb;font-weight:700">
    <td style="padding:10px 14px;color:#111">TOTAL</td>
    <td style="padding:10px 14px;text-align:right">{{ $rows->sum('order_count') }}</td>
    <td style="padding:10px 14px;text-align:right">${{ number_format($totalGmv,2) }}</td>
    <td style="padding:10px 14px;text-align:right;color:#f59e0b">${{ number_format($totalFees,2) }}</td>
    <td style="padding:10px 14px;text-align:right;color:#ef4444">${{ number_format($totalRef,2) }}</td>
    <td style="padding:10px 14px;text-align:right;color:#10b981">${{ number_format($totalNet,2) }}</td>
    <td></td>
</tr>
</tfoot>
</table>
</div>

<p style="margin-top:12px;font-size:12px;color:#9ca3af">
    Period: {{ $from->format('M d, Y') }} → {{ $to->format('M d, Y') }} · Net Payable = GMV − Platform Fees (refunded orders excluded)
</p>
</div>
@endsection
