@extends('admin.layouts.app')
@section('title', 'RFQ — ' . $rfq->title)

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
    <a href="{{ route('admin.module-data.wholesale.rfq') }}" style="color:#6b7280;text-decoration:none;font-size:13px">← RFQs</a>
    <h2 style="margin:0;font-size:18px;font-weight:700;color:#1B1444">{{ $rfq->title }}</h2>
    <span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;
        background:{{ $rfq->status==='open'?'#d1fae5':'#f3f4f6' }};
        color:{{ $rfq->status==='open'?'#065f46':'#6b7280' }}">{{ strtoupper($rfq->status) }}</span>
</div>

@if(session('success'))<div style="background:#d1fae5;color:#065f46;padding:10px 16px;border-radius:6px;margin-bottom:16px">{{ session('success') }}</div>@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444">Details</h3>
    @foreach([
        ['Buyer',         $rfq->buyer?->business_name.' ('.$rfq->buyer?->user?->name.')'],
        ['Category',      $rfq->category?->name ?? 'General'],
        ['Quantity',      $rfq->qty.' '.$rfq->unit],
        ['Target Price',  $rfq->target_price ? '$'.$rfq->target_price : 'Not specified'],
        ['Needed By',     $rfq->needed_by?->format('M d, Y') ?? '—'],
        ['Expires',       $rfq->expires_at?->format('M d, Y H:i') ?? '—'],
    ] as [$k,$v])
    <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:13px;border-bottom:1px solid #f9fafb">
        <span style="color:#6b7280">{{ $k }}</span>
        <span style="color:#1B1444;font-weight:500">{{ $v }}</span>
    </div>
    @endforeach
    <div style="margin-top:12px">
        <div style="font-size:12px;color:#6b7280;margin-bottom:4px">Description</div>
        <p style="font-size:13px;color:#374151;margin:0;background:#f9fafb;padding:10px;border-radius:6px">{{ $rfq->description }}</p>
    </div>
</div>

<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444">Moderate</h3>
    <form method="POST" action="{{ route('admin.module-data.wholesale.rfq.moderate', $rfq) }}">
        @csrf
        <div style="display:flex;flex-direction:column;gap:8px">
            @foreach(['open'=>['Open','#10b981'],'closed'=>['Close','#6b7280'],'awarded'=>['Mark Awarded','#8b5cf6'],'expired'=>['Expire','#ef4444']] as $s=>[$label,$color])
            <button name="status" value="{{ $s }}" style="padding:9px;background:{{ $color }};color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer;text-align:left">{{ $label }} RFQ</button>
            @endforeach
        </div>
    </form>
</div>
</div>

{{-- Quotes --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444">Supplier Quotes ({{ $rfq->quotes->count() }})</h3>
    @if($rfq->quotes->isEmpty())
    <p style="color:#9ca3af;font-size:13px;margin:0">No quotes received yet.</p>
    @else
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead><tr style="border-bottom:2px solid #f3f4f6">
        <th style="padding:8px;text-align:left;color:#6b7280;font-weight:500">Supplier</th>
        <th style="padding:8px;text-align:right;color:#6b7280;font-weight:500">Unit Price</th>
        <th style="padding:8px;text-align:right;color:#6b7280;font-weight:500">Qty Offered</th>
        <th style="padding:8px;text-align:right;color:#6b7280;font-weight:500">Lead (days)</th>
        <th style="padding:8px;text-align:left;color:#6b7280;font-weight:500">Valid Until</th>
        <th style="padding:8px;text-align:center;color:#6b7280;font-weight:500">Status</th>
    </tr></thead>
    <tbody>
    @foreach($rfq->quotes as $q)
    <tr style="border-bottom:1px solid #f9fafb" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background=''">
        <td style="padding:8px;font-weight:500;color:#1B1444">{{ $q->supplier?->display_name }}</td>
        <td style="padding:8px;text-align:right;color:#10b981;font-weight:600">${{ $q->unit_price }}</td>
        <td style="padding:8px;text-align:right">{{ $q->qty_offered }}</td>
        <td style="padding:8px;text-align:right">{{ $q->lead_time_days }}</td>
        <td style="padding:8px;color:#6b7280">{{ $q->valid_until?->format('M d') ?? '—' }}</td>
        <td style="padding:8px;text-align:center">
            <span style="font-size:11px;padding:2px 8px;border-radius:10px;background:{{ $q->status==='accepted'?'#d1fae5':'#f3f4f6' }};color:{{ $q->status==='accepted'?'#065f46':'#374151' }}">{{ ucfirst($q->status) }}</span>
        </td>
    </tr>
    @endforeach
    </tbody>
    </table>
    </div>
    @endif
</div>
</div>
@endsection
