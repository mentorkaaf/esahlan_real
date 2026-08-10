@extends('admin.layouts.app')
@section('title', 'Global Orders')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">🛒 Global Orders</h1>
        <p class="page-subtitle">USA &amp; Europe eCommerce orders</p>
    </div>
</div>

@if(session('success'))
<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">❌ {{ session('error') }}</div>
@endif

{{-- Filters --}}
<form method="GET" style="background:#fff;border-radius:12px;padding:16px 20px;margin-bottom:16px;border:1px solid #e5e7eb;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:2;min-width:160px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Search</label>
        <input name="search" value="{{ request('search') }}" placeholder="Order# or customer…" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
    </div>
    <div style="flex:1;min-width:130px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Status</label>
        <select name="status" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            <option value="">All</option>
            @foreach(['pending','paid','processing','shipped','delivered','cancelled','refunded','on_hold'] as $s)
            <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>
    <div style="flex:1;min-width:130px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Payment</label>
        <select name="payment" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            <option value="">All</option>
            <option value="paid" {{ request('payment')==='paid'?'selected':'' }}>Paid</option>
            <option value="pending" {{ request('payment')==='pending'?'selected':'' }}>Pending</option>
            <option value="refunded" {{ request('payment')==='refunded'?'selected':'' }}>Refunded</option>
        </select>
    </div>
    <div style="flex:1;min-width:120px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">From</label>
        <input name="from" type="date" value="{{ request('from') }}" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
    </div>
    <div style="flex:1;min-width:120px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">To</label>
        <input name="to" type="date" value="{{ request('to') }}" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
    </div>
    <button type="submit" style="padding:8px 20px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Filter</button>
    @if(request()->hasAny(['search','status','payment','from','to']))
    <a href="{{ route('admin.global.orders.index') }}" style="padding:8px 16px;background:#f3f4f6;color:#374151;border-radius:8px;font-size:13px;text-decoration:none">Clear</a>
    @endif
</form>

@php
$statusColors = [
    'pending'   =>['#f59e0b','#fffbeb'],'paid'      =>['#10b981','#ecfdf5'],
    'processing'=>['#6366f1','#eef2ff'],'shipped'   =>['#3b82f6','#eff6ff'],
    'delivered' =>['#10b981','#ecfdf5'],'cancelled' =>['#ef4444','#fef2f2'],
    'refunded'  =>['#8b5cf6','#f5f3ff'],'on_hold'   =>['#f97316','#fff7ed'],
];
$statuses = ['pending','processing','shipped','delivered','cancelled','refunded','on_hold'];
@endphp

{{-- Bulk toolbar (hidden until rows selected) --}}
<div id="bulkBar" style="display:none;background:#1e1b4b;border-radius:10px;padding:10px 16px;margin-bottom:12px;display:none;align-items:center;gap:12px;flex-wrap:wrap">
    <span id="bulkCount" style="color:#c7d2fe;font-size:13px;font-weight:600"></span>

    {{-- Bulk status change --}}
    <form id="bulkStatusForm" method="POST" action="{{ route('admin.global.orders.bulk') }}" style="display:flex;gap:8px;align-items:center">
        @csrf
        <input type="hidden" name="action" value="status">
        <div id="bulkIdsStatus"></div>
        <select name="status" style="padding:6px 10px;border-radius:7px;border:1px solid #4f46e5;background:#312e81;color:#fff;font-size:12px;cursor:pointer">
            @foreach($statuses as $s)
            <option value="{{ $s }}">Set → {{ ucfirst(str_replace('_',' ',$s)) }}</option>
            @endforeach
        </select>
        <button type="submit" style="padding:6px 14px;background:#6366f1;color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer">Apply Status</button>
    </form>

    {{-- Bulk delete --}}
    <form id="bulkDeleteForm" method="POST" action="{{ route('admin.global.orders.bulk') }}" style="display:flex">
        @csrf
        <input type="hidden" name="action" value="delete">
        <div id="bulkIdsDelete"></div>
        <button type="submit" onclick="return confirm('Delete selected orders? This cannot be undone.')"
            style="padding:6px 14px;background:#dc2626;color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer">🗑 Delete Selected</button>
    </form>
</div>

<div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);border:1px solid #e5e7eb;overflow:hidden">
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
                <th style="padding:11px 12px;width:36px">
                    <input type="checkbox" id="checkAll" title="Select all" style="cursor:pointer;width:15px;height:15px">
                </th>
                <th style="padding:11px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Order</th>
                <th style="padding:11px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Customer</th>
                <th style="padding:11px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Ship to</th>
                <th style="padding:11px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Items</th>
                <th style="padding:11px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Total</th>
                <th style="padding:11px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Status</th>
                <th style="padding:11px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Date</th>
                <th style="padding:11px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            @php $sc = $statusColors[$order->status] ?? ['#9ca3af','#f9fafb']; @endphp
            <tr style="border-top:1px solid #f3f4f6" class="order-row">
                <td style="padding:11px 12px">
                    <input type="checkbox" class="row-check" value="{{ $order->id }}" style="cursor:pointer;width:15px;height:15px">
                </td>
                <td style="padding:11px 16px;font-size:12px;font-weight:700">
                    <a href="{{ route('admin.global.orders.show',$order) }}" style="color:#6366f1;text-decoration:none">{{ $order->order_number }}</a>
                </td>
                <td style="padding:11px 16px">
                    <div style="font-size:12px;font-weight:600;color:#111">{{ $order->user?->name ?? '—' }}</div>
                    <div style="font-size:11px;color:#9ca3af">{{ $order->user?->email }}</div>
                </td>
                <td style="padding:11px 16px;font-size:12px;color:#374151">
                    {{ $order->ship_city }}{{ $order->ship_city && $order->ship_country_name ? ', ' : '' }}{{ $order->ship_country_name }}
                </td>
                <td style="padding:11px 16px;text-align:center;font-size:12px;color:#374151">{{ $order->items->count() }}</td>
                <td style="padding:11px 16px;text-align:right;font-size:13px;font-weight:700;color:#111">${{ number_format($order->total,2) }}</td>
                <td style="padding:11px 16px;text-align:center">
                    <span style="padding:2px 10px;border-radius:20px;font-size:10px;font-weight:700;color:{{ $sc[0] }};background:{{ $sc[1] }}">{{ strtoupper($order->status) }}</span>
                </td>
                <td style="padding:11px 16px;text-align:center;font-size:11px;color:#9ca3af">{{ $order->created_at->format('M d, Y') }}</td>
                <td style="padding:11px 16px;text-align:right;white-space:nowrap">
                    <a href="{{ route('admin.global.orders.show',$order) }}"
                       style="padding:5px 10px;background:#f3f4f6;color:#374151;border-radius:6px;font-size:11px;font-weight:600;text-decoration:none;margin-right:4px">View</a>
                    {{-- Single delete --}}
                    <form method="POST" action="{{ route('admin.global.orders.destroy',$order) }}" style="display:inline"
                          onsubmit="return confirm('Delete order {{ $order->order_number }}?')">
                        @csrf @method('DELETE')
                        <button type="submit"
                            style="padding:5px 10px;background:#fee2e2;color:#dc2626;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer">🗑</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="9" style="padding:48px;text-align:center;color:#9ca3af">
                <div style="font-size:40px;margin-bottom:12px">🛒</div>
                <div style="font-size:15px;font-weight:600">No orders yet</div>
            </td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
    @if($orders->hasPages())
    <div style="padding:16px 20px;border-top:1px solid #f3f4f6">{{ $orders->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
(function(){
    const checkAll = document.getElementById('checkAll');
    const bulkBar  = document.getElementById('bulkBar');
    const bulkCount= document.getElementById('bulkCount');

    function getChecked() {
        return [...document.querySelectorAll('.row-check:checked')].map(c => c.value);
    }

    function updateBar() {
        const ids = getChecked();
        if (ids.length > 0) {
            bulkBar.style.display = 'flex';
            bulkCount.textContent = ids.length + ' order(s) selected';
            // Populate hidden inputs for status form
            document.getElementById('bulkIdsStatus').innerHTML =
                ids.map(id => `<input type="hidden" name="ids[]" value="${id}">`).join('');
            // Populate hidden inputs for delete form
            document.getElementById('bulkIdsDelete').innerHTML =
                ids.map(id => `<input type="hidden" name="ids[]" value="${id}">`).join('');
        } else {
            bulkBar.style.display = 'none';
        }
    }

    checkAll.addEventListener('change', function() {
        document.querySelectorAll('.row-check').forEach(c => c.checked = this.checked);
        updateBar();
    });

    document.querySelectorAll('.row-check').forEach(c => {
        c.addEventListener('change', function() {
            const all = document.querySelectorAll('.row-check');
            checkAll.checked = [...all].every(x => x.checked);
            updateBar();
        });
    });
})();
</script>
@endpush
@endsection
