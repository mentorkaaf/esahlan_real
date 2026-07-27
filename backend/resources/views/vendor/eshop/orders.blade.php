@extends('vendor.layouts.app')
@section('title', 'My Orders')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">eShop Orders ({{ $orders->total() }})</h1>
        <ul class="breadcrumb"><li><a href="{{ route('vendor.eshop.dashboard') }}">Dashboard</a></li><li>Orders</li></ul>
    </div>
</div>

@if(session('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif

{{-- Filters --}}
<form method="GET" class="card" style="padding:14px 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
    <select name="status" class="form-control" style="width:150px;border:1.5px solid #e5e7eb;border-radius:8px;padding:8px 12px;font-size:13px;">
        <option value="">All Status</option>
        @foreach(['pending','confirmed','processing','shipped','delivered','cancelled'] as $s)
        <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    <button class="btn btn-brand btn-sm" type="submit">Filter</button>
    <a href="{{ route('vendor.eshop.orders') }}" class="btn btn-sm" style="background:#f3f4f6;color:#555;">Clear</a>
</form>

<div class="card">
<div class="table-responsive">
<table class="data-table">
    <thead><tr><th>Order #</th><th>Customer</th><th>Your Items</th><th>Order Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
    @forelse($orders as $o)
    @php $items = $orderItemsMap[$o->id] ?? collect(); @endphp
    <tr>
        <td><code style="font-weight:700;font-size:12px;">{{ $o->order_number }}</code></td>
        <td>
            <div style="font-weight:600">{{ $o->customer_name ?? '—' }}</div>
            <div style="font-size:11px;color:#aaa">{{ $o->customer_phone ?? '' }}</div>
        </td>
        <td>
            @foreach($items as $item)
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                <img src="{{ cdn_url($item->thumbnail) ?? '' }}" style="width:28px;height:28px;border-radius:5px;object-fit:cover;background:#f3f4f6;" onerror="this.style.display='none'">
                <span style="font-size:12px;font-weight:600">{{ $item->product_name }} <span style="color:#aaa;font-weight:400">×{{ $item->quantity }}</span></span>
                <span style="font-size:12px;font-weight:700;color:var(--brand)">${{ number_format($item->total,2) }}</span>
            </div>
            @endforeach
        </td>
        <td style="font-weight:800;color:var(--brand)">${{ number_format($o->total_amount,2) }}</td>
        <td>
            <span style="background:#f3f4f6;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:700;">
                {{ ucwords(str_replace('_',' ',$o->payment_method ?? 'cod')) }}
            </span>
        </td>
        <td>
            @php
            $colors = ['pending'=>'#fef9c3::#92400e','confirmed'=>'#dbeafe::#1e40af','processing'=>'#ede9fe::#6d28d9','shipped'=>'#cffafe::#0e7490','delivered'=>'#dcfce7::#15803d','cancelled'=>'#fee2e2::#991b1b'];
            [$bg, $fg] = explode('::', $colors[$o->status] ?? '#f3f4f6::#555');
            @endphp
            <span style="background:{{ $bg }};color:{{ $fg }};padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">{{ ucfirst($o->status) }}</span>
        </td>
        <td style="font-size:12px;color:#666">{{ \Carbon\Carbon::parse($o->created_at)->format('d M Y H:i') }}</td>
    </tr>
    @empty
    <tr><td colspan="7" style="text-align:center;color:#aaa;padding:40px">No orders yet.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div style="padding:12px 16px">{{ $orders->links() }}</div>
</div>

@endsection
