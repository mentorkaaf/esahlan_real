@extends('admin.layouts.app')
@section('title', 'eWholesale — Orders')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<h2 style="margin:0 0 16px;font-size:20px;font-weight:700;color:#1B1444">Orders Pipeline</h2>

{{-- Tabs: Orders / Disputes --}}
<div style="display:flex;gap:4px;margin-bottom:20px">
    <a href="{{ route('admin.module-data.wholesale.orders') }}" style="padding:8px 20px;border-radius:6px;font-size:13px;font-weight:500;text-decoration:none;background:{{ $tab!='disputes'?'#1B1444':'#f3f4f6' }};color:{{ $tab!='disputes'?'#fff':'#374151' }}">Orders</a>
    <a href="{{ route('admin.module-data.wholesale.orders', ['tab'=>'disputes']) }}" style="padding:8px 20px;border-radius:6px;font-size:13px;font-weight:500;text-decoration:none;background:{{ $tab==='disputes'?'#ef4444':'#f3f4f6' }};color:{{ $tab==='disputes'?'#fff':'#374151' }}">⚠️ Disputes</a>
</div>

@if(session('success'))<div style="background:#d1fae5;color:#065f46;padding:10px 16px;border-radius:6px;margin-bottom:16px">{{ session('success') }}</div>@endif
@if(session('error'))<div style="background:#fee2e2;color:#991b1b;padding:10px 16px;border-radius:6px;margin-bottom:16px">{{ session('error') }}</div>@endif

@if($tab === 'disputes')
{{-- DISPUTES view --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead><tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Order #</th>
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Buyer</th>
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Supplier</th>
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Reason</th>
    <th style="padding:10px 14px;text-align:center;color:#374151;font-weight:600">Status</th>
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Opened</th>
    <th style="padding:10px 14px;text-align:center;color:#374151;font-weight:600">Resolve</th>
</tr></thead>
<tbody>
@forelse($disputes as $d)
<tr style="border-bottom:1px solid #f3f4f6">
    <td style="padding:10px 14px"><a href="{{ route('admin.module-data.wholesale.orders.show', $d->order) }}" style="color:#1B1444;font-family:monospace;text-decoration:none">{{ $d->order?->order_no }}</a></td>
    <td style="padding:10px 14px;color:#6b7280">{{ $d->order?->buyer?->user?->name }}</td>
    <td style="padding:10px 14px;color:#6b7280">{{ $d->order?->supplier?->display_name }}</td>
    <td style="padding:10px 14px;color:#374151">{{ Str::limit($d->reason, 40) }}</td>
    <td style="padding:10px 14px;text-align:center"><span style="font-size:11px;padding:2px 8px;border-radius:10px;background:#fee2e2;color:#991b1b;font-weight:600">{{ strtoupper($d->status) }}</span></td>
    <td style="padding:10px 14px;color:#6b7280;font-size:12px">{{ $d->created_at->format('M d, Y') }}</td>
    <td style="padding:10px 14px;text-align:center">
        <button onclick="togglePanel('disp-{{ $d->id }}')" style="padding:4px 10px;background:#ef4444;color:#fff;border:none;border-radius:4px;font-size:11px;cursor:pointer">Resolve</button>
    </td>
</tr>
<tr id="disp-{{ $d->id }}" style="display:none;background:#fef2f2">
    <td colspan="7" style="padding:14px 24px">
        <form method="POST" action="{{ route('admin.module-data.wholesale.disputes.resolve', $d) }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            @csrf
            <select name="action" style="padding:7px 12px;border:1px solid #d1d5db;border-radius:5px;font-size:12px">
                <option value="resolved_refund">Resolve: Refund buyer</option>
                <option value="resolved_release">Resolve: Release to supplier</option>
                <option value="closed">Close</option>
            </select>
            <textarea name="note" placeholder="Resolution note (required)" required style="padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;width:300px;height:40px"></textarea>
            <button style="padding:7px 16px;background:#ef4444;color:#fff;border:none;border-radius:5px;font-size:12px;cursor:pointer">Submit</button>
        </form>
    </td>
</tr>
@empty
<tr><td colspan="7" style="padding:32px;text-align:center;color:#9ca3af">No open disputes. 🎉</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>

@else
{{-- ORDERS view --}}

{{-- Status filter chips --}}
<div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:14px">
    <a href="{{ route('admin.module-data.wholesale.orders') }}" style="padding:5px 12px;border-radius:20px;font-size:11px;font-weight:500;text-decoration:none;background:{{ !request('status')?'#1B1444':'#f3f4f6' }};color:{{ !request('status')?'#fff':'#374151' }}">All</a>
    @foreach([
        'pending_confirmation','confirmed','awaiting_payment','processing',
        'ready','partially_shipped','shipped','delivered','completed','cancelled','disputed'
    ] as $s)
    @php $cnt = $statusCounts[$s] ?? 0; @endphp
    <a href="{{ route('admin.module-data.wholesale.orders', ['status'=>$s]) }}"
       style="padding:5px 12px;border-radius:20px;font-size:11px;font-weight:500;text-decoration:none;
           background:{{ request('status')===$s?'#1B1444':'#f3f4f6' }};
           color:{{ request('status')===$s?'#fff':'#374151' }}">
        {{ ucwords(str_replace('_',' ',$s)) }} @if($cnt) <span style="opacity:.75">({{ $cnt }})</span> @endif
    </a>
    @endforeach
</div>

<form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
    @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
    <input name="search" value="{{ request('search') }}" placeholder="Order #..." style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:150px">
    <select name="supplier_id" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
        <option value="">All Suppliers</option>
        @foreach($suppliers as $s)
        <option value="{{ $s->id }}" @selected(request('supplier_id')==$s->id)>{{ $s->display_name }}</option>
        @endforeach
    </select>
    <button style="padding:8px 16px;background:#F7941D;color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer">Filter</button>
</form>

<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead><tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Order #</th>
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Buyer</th>
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Supplier</th>
    <th style="padding:10px 14px;text-align:right;color:#374151;font-weight:600">Total</th>
    <th style="padding:10px 14px;text-align:right;color:#374151;font-weight:600">Paid</th>
    <th style="padding:10px 14px;text-align:center;color:#374151;font-weight:600">Status</th>
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Date</th>
    <th style="padding:10px 14px;text-align:center;color:#374151;font-weight:600">Actions</th>
</tr></thead>
<tbody>
@forelse($orders as $o)
@php
$colors = match($o->status) {
    'pending_confirmation' => ['#fffbeb','#92400e'],
    'confirmed'            => ['#dbeafe','#1e40af'],
    'awaiting_payment'     => ['#fff7ed','#9a3412'],
    'completed'            => ['#d1fae5','#065f46'],
    'cancelled'            => ['#fee2e2','#991b1b'],
    'disputed'             => ['#fce7f3','#9d174d'],
    default                => ['#f3f4f6','#374151'],
};
@endphp
<tr style="border-bottom:1px solid #f3f4f6" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background=''">
    <td style="padding:10px 14px"><a href="{{ route('admin.module-data.wholesale.orders.show', $o) }}" style="color:#1B1444;font-family:monospace;font-weight:600;text-decoration:none">{{ $o->order_no }}</a></td>
    <td style="padding:10px 14px;color:#6b7280">{{ $o->buyer?->user?->name ?? '—' }}</td>
    <td style="padding:10px 14px;color:#6b7280">{{ $o->supplier?->display_name ?? '—' }}</td>
    <td style="padding:10px 14px;text-align:right;font-weight:700">${{ number_format($o->total,2) }}</td>
    <td style="padding:10px 14px;text-align:right;color:{{ $o->paid_total>=$o->total?'#10b981':'#ef4444' }}">${{ number_format($o->paid_total,2) }}</td>
    <td style="padding:10px 14px;text-align:center">
        <span style="background:{{ $colors[0] }};color:{{ $colors[1] }};padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;white-space:nowrap">{{ str_replace('_',' ',strtoupper($o->status)) }}</span>
    </td>
    <td style="padding:10px 14px;color:#6b7280;font-size:12px">{{ $o->created_at->format('M d, Y') }}</td>
    <td style="padding:10px 14px;text-align:center">
        <a href="{{ route('admin.module-data.wholesale.orders.show', $o) }}" style="padding:4px 12px;background:#1B1444;color:#fff;border-radius:4px;font-size:11px;text-decoration:none">Manage</a>
    </td>
</tr>
@empty
<tr><td colspan="8" style="padding:32px;text-align:center;color:#9ca3af">No orders found.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
<div style="margin-top:16px">{{ $orders->links() }}</div>
@endif
</div>

<script>
function togglePanel(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? '' : 'none';
}
</script>
@endsection
