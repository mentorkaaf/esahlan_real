@extends('admin.layouts.app')
@section('title', 'Credit Ledger — ' . $buyer->business_name)

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
    <a href="{{ route('admin.module-data.wholesale.buyers') }}" style="color:#6b7280;text-decoration:none;font-size:13px">← Buyers</a>
    <h2 style="margin:0;font-size:18px;font-weight:700;color:#1B1444">Credit Ledger — {{ $buyer->business_name }}</h2>
</div>

<div style="display:flex;gap:16px;margin-bottom:20px">
    @foreach([
        ['Limit', '$'.number_format($acct?->credit_limit??0,2), '#6366f1'],
        ['Used',  '$'.number_format($acct?->balance_used??0,2), '#ef4444'],
        ['Available', '$'.number_format(($acct?->credit_limit??0)-($acct?->balance_used??0),2), '#10b981'],
        ['Term', strtoupper($acct?->term??'—'), '#f59e0b'],
    ] as [$k,$v,$c])
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px 20px;border-top:3px solid {{ $c }}">
        <div style="font-size:18px;font-weight:700;color:#1B1444">{{ $v }}</div>
        <div style="font-size:11px;color:#6b7280">{{ $k }}</div>
    </div>
    @endforeach
</div>

<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead><tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Date</th>
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Type</th>
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Note</th>
    <th style="padding:10px 14px;text-align:right;color:#374151;font-weight:600">Amount</th>
    <th style="padding:10px 14px;text-align:right;color:#374151;font-weight:600">Balance After</th>
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Due Date</th>
</tr></thead>
<tbody>
@forelse($ledger as $row)
<tr style="border-bottom:1px solid #f9fafb">
    <td style="padding:10px 14px;color:#6b7280">{{ $row->created_at->format('M d, Y H:i') }}</td>
    <td style="padding:10px 14px">
        <span style="padding:2px 8px;border-radius:10px;font-size:11px;
            background:{{ $row->type==='charge'?'#fee2e2':($row->type==='settle'?'#d1fae5':'#f3f4f6') }};
            color:{{ $row->type==='charge'?'#991b1b':($row->type==='settle'?'#065f46':'#374151') }}">
            {{ strtoupper($row->type) }}
        </span>
    </td>
    <td style="padding:10px 14px;color:#6b7280">{{ $row->note ?? '—' }}</td>
    <td style="padding:10px 14px;text-align:right;font-weight:600;color:{{ $row->amount>0?'#ef4444':'#10b981' }}">${{ number_format(abs($row->amount),2) }}</td>
    <td style="padding:10px 14px;text-align:right">${{ number_format($row->balance_after,2) }}</td>
    <td style="padding:10px 14px;color:{{ $row->due_date && $row->due_date->isPast()?'#ef4444':'#6b7280' }}">{{ $row->due_date?->format('M d, Y') ?? '—' }}</td>
</tr>
@empty
<tr><td colspan="6" style="padding:24px;text-align:center;color:#9ca3af">No ledger entries.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
<div style="margin-top:16px">{{ $ledger->links() }}</div>
</div>
@endsection
