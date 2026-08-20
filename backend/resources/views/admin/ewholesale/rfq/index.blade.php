@extends('admin.layouts.app')
@section('title', 'eWholesale — RFQ Center')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<h2 style="margin:0 0 20px;font-size:20px;font-weight:700;color:#1B1444">RFQ Center</h2>

{{-- Metrics --}}
<div style="display:flex;gap:14px;margin-bottom:20px">
    @foreach([
        ['Open RFQs',    $metrics['open'],   '#06b6d4'],
        ['Quotes Sent',  $metrics['quotes'],  '#8b5cf6'],
        ['Awarded',      $metrics['awarded'], '#10b981'],
    ] as [$label,$val,$color])
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px 20px;border-top:3px solid {{ $color }}">
        <div style="font-size:22px;font-weight:700;color:#1B1444">{{ $val }}</div>
        <div style="font-size:12px;color:#6b7280">{{ $label }}</div>
    </div>
    @endforeach
</div>

<form method="GET" style="display:flex;gap:10px;margin-bottom:16px">
    <select name="status" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
        <option value="">All Statuses</option>
        @foreach(['open','closed','awarded','expired'] as $s)
        <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    <button style="padding:8px 16px;background:#F7941D;color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer">Filter</button>
</form>

<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead><tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Title</th>
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Buyer</th>
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Category</th>
    <th style="padding:10px 14px;text-align:right;color:#374151;font-weight:600">Qty</th>
    <th style="padding:10px 14px;text-align:right;color:#374151;font-weight:600">Target $</th>
    <th style="padding:10px 14px;text-align:center;color:#374151;font-weight:600">Quotes</th>
    <th style="padding:10px 14px;text-align:center;color:#374151;font-weight:600">Status</th>
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Expires</th>
    <th style="padding:10px 14px;text-align:center;color:#374151;font-weight:600">Action</th>
</tr></thead>
<tbody>
@forelse($rfqs as $rfq)
<tr style="border-bottom:1px solid #f3f4f6" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background=''">
    <td style="padding:10px 14px">
        <a href="{{ route('admin.module-data.wholesale.rfq.show', $rfq) }}" style="color:#1B1444;font-weight:600;text-decoration:none">{{ $rfq->title }}</a>
    </td>
    <td style="padding:10px 14px;color:#6b7280">{{ $rfq->buyer?->user?->name ?? '—' }}</td>
    <td style="padding:10px 14px;color:#6b7280">{{ $rfq->category?->name ?? 'General' }}</td>
    <td style="padding:10px 14px;text-align:right">{{ $rfq->qty }} {{ $rfq->unit }}</td>
    <td style="padding:10px 14px;text-align:right;color:#10b981">{{ $rfq->target_price ? '$'.$rfq->target_price : '—' }}</td>
    <td style="padding:10px 14px;text-align:center;font-weight:600">{{ $rfq->quotes_count }}</td>
    <td style="padding:10px 14px;text-align:center">
        @php $rc = match($rfq->status){ 'open'=>['#d1fae5','#065f46'], 'awarded'=>['#ddd6fe','#4c1d95'], 'expired'=>['#fee2e2','#991b1b'], default=>['#f3f4f6','#6b7280'] }; @endphp
        <span style="background:{{ $rc[0] }};color:{{ $rc[1] }};padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600">{{ strtoupper($rfq->status) }}</span>
    </td>
    <td style="padding:10px 14px;color:#6b7280;font-size:12px">{{ $rfq->expires_at?->format('M d, Y') ?? '—' }}</td>
    <td style="padding:10px 14px;text-align:center">
        <a href="{{ route('admin.module-data.wholesale.rfq.show', $rfq) }}" style="padding:4px 10px;background:#1B1444;color:#fff;border-radius:4px;font-size:11px;text-decoration:none">View</a>
    </td>
</tr>
@empty
<tr><td colspan="9" style="padding:32px;text-align:center;color:#9ca3af">No RFQs found.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
<div style="margin-top:16px">{{ $rfqs->links() }}</div>
</div>
@endsection
