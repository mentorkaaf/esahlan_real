@extends('vendor.layouts.app')
@section('title', 'My Orders')

@push('styles')
<style>
.status-pill { display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700; }
.status-dropdown { position:relative;display:inline-block; }
.status-dropdown-menu { display:none;position:absolute;right:0;top:100%;margin-top:4px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:100;min-width:170px;overflow:hidden; }
.status-dropdown:hover .status-dropdown-menu,
.status-dropdown.open .status-dropdown-menu { display:block; }
.status-option { display:block;padding:9px 14px;font-size:12px;font-weight:600;cursor:pointer;border:none;background:none;width:100%;text-align:left;transition:background .15s; }
.status-option:hover { background:#f3f4f6; }
</style>
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">eShop Orders ({{ $orders->total() }})</h1>
        <ul class="breadcrumb"><li><a href="{{ route('vendor.eshop.dashboard') }}">Dashboard</a></li><li>Orders</li></ul>
    </div>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:#15803d;font-weight:600;font-size:13px;">
    <i class="fa-solid fa-check-circle" style="margin-right:6px;"></i>{{ session('success') }}
</div>
@endif

{{-- Filters --}}
<form method="GET" class="card" style="padding:14px 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
    <select name="status" style="width:160px;border:1.5px solid #e5e7eb;border-radius:8px;padding:8px 12px;font-size:13px;">
        <option value="">All Status</option>
        @foreach(['pending','confirmed','preparing','ready_for_pickup','out_for_delivery','delivered','cancelled'] as $s)
        <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucwords(str_replace('_',' ',$s)) }}</option>
        @endforeach
    </select>
    <button class="btn btn-brand btn-sm" type="submit">Filter</button>
    <a href="{{ route('vendor.eshop.orders') }}" class="btn btn-sm" style="background:#f3f4f6;color:#555;">Clear</a>
</form>

<div class="card">
<div class="table-responsive">
<table class="data-table">
    <thead>
        <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Your Items</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Date</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    @forelse($orders as $o)
    @php
        $items = $orderItemsMap[$o->id] ?? collect();
        $statusColors = [
            'pending'          => ['#fef9c3','#92400e'],
            'confirmed'        => ['#dbeafe','#1e40af'],
            'preparing'        => ['#ede9fe','#6d28d9'],
            'ready_for_pickup' => ['#cffafe','#0e7490'],
            'out_for_delivery' => ['#ffedd5','#c2410c'],
            'delivered'        => ['#dcfce7','#15803d'],
            'cancelled'        => ['#fee2e2','#991b1b'],
        ];
        [$bg, $fg] = $statusColors[$o->status] ?? ['#f3f4f6','#555'];

        // Next possible statuses vendor can set
        $nextStatuses = match($o->status) {
            'pending'          => ['confirmed','cancelled'],
            'confirmed'        => ['preparing','cancelled'],
            'preparing'        => ['ready_for_pickup','cancelled'],
            'ready_for_pickup' => ['out_for_delivery','delivered'],
            'out_for_delivery' => ['delivered'],
            default            => [],
        };
        $statusLabels = [
            'confirmed'        => ['✅','Confirm Order'],
            'preparing'        => ['🔧','Mark Preparing'],
            'ready_for_pickup' => ['📦','Ready for Pickup'],
            'out_for_delivery' => ['🚗','Out for Delivery'],
            'delivered'        => ['🎉','Mark Delivered'],
            'cancelled'        => ['❌','Cancel Order'],
        ];
    @endphp
    <tr>
        <td><code style="font-weight:700;font-size:12px;">{{ $o->order_number }}</code></td>
        <td>
            <div style="font-weight:600;font-size:13px;">{{ $o->customer_name ?? '—' }}</div>
            <div style="font-size:11px;color:#aaa;">{{ $o->customer_phone ?? '' }}</div>
        </td>
        <td>
            @foreach($items as $item)
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                <img src="{{ cdn_url($item->thumbnail) ?? '' }}" style="width:26px;height:26px;border-radius:5px;object-fit:cover;background:#f3f4f6;" onerror="this.style.display='none'">
                <span style="font-size:12px;font-weight:600;">{{ $item->product_name }}</span>
                <span style="color:#aaa;font-size:11px;">×{{ $item->quantity }}</span>
            </div>
            @endforeach
        </td>
        <td style="font-weight:800;color:var(--brand)">${{ number_format($o->total_amount,2) }}</td>
        <td>
            <span style="background:#f3f4f6;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:700;">
                {{ ucwords(str_replace('_',' ',$o->payment_method ?? 'cod')) }}
            </span>
            <div style="font-size:10px;color:{{ $o->payment_status==='paid'?'#22c55e':'#f59e0b' }};font-weight:700;margin-top:2px;">
                {{ ucfirst($o->payment_status ?? 'pending') }}
            </div>
        </td>
        <td>
            <span class="status-pill" style="background:{{ $bg }};color:{{ $fg }};">
                {{ ucwords(str_replace('_',' ',$o->status)) }}
            </span>
        </td>
        <td style="font-size:12px;color:#666;">{{ \Carbon\Carbon::parse($o->created_at)->format('d M Y') }}<br><span style="color:#aaa;font-size:11px;">{{ \Carbon\Carbon::parse($o->created_at)->format('H:i') }}</span></td>
        <td>
            @if(count($nextStatuses) > 0)
            <div class="status-dropdown" onclick="this.classList.toggle('open')">
                <button type="button" style="background:var(--brand);color:#fff;border:none;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px;">
                    Update <i class="fa-solid fa-chevron-down" style="font-size:10px;"></i>
                </button>
                <div class="status-dropdown-menu">
                    @foreach($nextStatuses as $nextStatus)
                    @php [$icon, $label] = $statusLabels[$nextStatus] ?? ['→', ucfirst($nextStatus)]; @endphp
                    <form method="POST" action="{{ route('vendor.eshop.orders.status', $o->id) }}" style="margin:0;">
                        @csrf
                        <input type="hidden" name="status" value="{{ $nextStatus }}">
                        <button type="submit" class="status-option"
                                style="color:{{ $nextStatus==='cancelled'?'#dc2626':($nextStatus==='delivered'?'#15803d':'#111') }};"
                                onclick="event.stopPropagation()">
                            {{ $icon }} {{ $label }}
                        </button>
                    </form>
                    @endforeach
                </div>
            </div>
            @else
            <span style="font-size:11px;color:#aaa;">—</span>
            @endif
        </td>
    </tr>
    @empty
    <tr><td colspan="8" style="text-align:center;color:#aaa;padding:40px">No orders yet.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div style="padding:12px 16px;">{{ $orders->links() }}</div>
</div>

@push('scripts')
<script>
// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.status-dropdown')) {
        document.querySelectorAll('.status-dropdown.open').forEach(d => d.classList.remove('open'));
    }
});
</script>
@endpush

@endsection
