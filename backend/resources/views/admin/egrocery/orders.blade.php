@extends('admin.layouts.app')
@section('title', 'eGrocery Orders')
@section('content')
@php
$statusColors = [
    'pending'          => ['badge-warning',     '🕐 Pending'],
    'confirmed'        => ['badge-info',         '✅ Confirmed'],
    'picking'          => ['badge-purple',        '🛒 Picking'],
    'ready'            => ['badge-teal',          '📦 Ready'],
    'out_for_delivery' => ['badge-orange',        '🚚 Delivering'],
    'delivered'        => ['badge-success',       '🎉 Delivered'],
    'cancelled'        => ['badge-danger',        '❌ Cancelled'],
];
@endphp
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-shopping-bag" style="color:#f59e0b"></i> Orders</h2>
        <ol class="breadcrumb"><li><a href="{{ route('admin.module-data.egrocery.index') }}">eGrocery</a></li><li>Orders</li></ol>
    </div>
</div>

@include('admin.egrocery._subnav')

@if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif

{{-- Pipeline counts --}}
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
    @foreach($statusColors as $status => [$cls, $label])
    <a href="{{ route('admin.module-data.egrocery.orders', ['status'=>$status]) }}"
       style="display:flex;align-items:center;gap:8px;padding:8px 16px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;border:1.5px solid {{ request('status')===$status ? '#FF8A00' : '#e8edf5' }};background:{{ request('status')===$status ? '#fff8f0' : '#fff' }};color:{{ request('status')===$status ? '#c05800' : '#374151' }};">
        {{ $label }}
        <span style="background:{{ request('status')===$status ? '#FF8A00' : '#f1f5f9' }};color:{{ request('status')===$status ? '#fff' : '#64748b' }};padding:2px 8px;border-radius:20px;font-size:11px;">{{ $counts[$status] }}</span>
    </a>
    @endforeach
    <a href="{{ route('admin.module-data.egrocery.orders') }}"
       style="display:flex;align-items:center;gap:8px;padding:8px 16px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;border:1.5px solid {{ !request('status') ? '#FF8A00' : '#e8edf5' }};background:{{ !request('status') ? '#fff8f0' : '#fff' }};color:{{ !request('status') ? '#c05800' : '#374151' }};">
        All <span style="background:{{ !request('status') ? '#FF8A00' : '#f1f5f9' }};color:{{ !request('status') ? '#fff' : '#64748b' }};padding:2px 8px;border-radius:20px;font-size:11px;">{{ $counts['all'] }}</span>
    </a>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:12px 18px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <div><label class="form-label" style="font-size:11px;">Search</label><input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Order # or customer..." style="width:220px;"></div>
            <div><label class="form-label" style="font-size:11px;">Date</label><input type="date" name="date" value="{{ request('date') }}" class="form-control"></div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('admin.module-data.egrocery.orders') }}" class="btn" style="background:#f1f5f9;color:#374151;">Clear</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-title"><i class="fas fa-list" style="color:#f59e0b"></i> {{ $orders->total() }} Orders</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                @php [$badgeCls, $statusLabel] = $statusColors[$o->status] ?? ['badge-secondary', $o->status]; @endphp
                <tr>
                    <td>
                        <a href="{{ route('admin.module-data.egrocery.order.show', $o->id) }}" style="font-weight:800;color:#FF8A00;text-decoration:none;">{{ $o->order_no }}</a>
                    </td>
                    <td>
                        <div style="font-weight:600;">{{ $o->user?->name ?? '—' }}</div>
                        <div style="font-size:11px;color:#9ca3af;">{{ $o->user?->phone }}</div>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge badge-secondary">{{ DB::table('egrocery_order_items')->where('order_id',$o->id)->count() }}</span>
                    </td>
                    <td style="font-weight:800;color:#111827;">${{ number_format($o->total, 2) }}</td>
                    <td>
                        @if($o->payment_status === 'paid')
                            <span class="badge badge-success">Paid</span>
                        @elseif($o->payment_status === 'refunded')
                            <span class="badge badge-purple">Refunded</span>
                        @else
                            <span class="badge badge-warning">{{ ucfirst($o->payment_status ?? 'pending') }}</span>
                        @endif
                        <div style="font-size:10px;color:#9ca3af;margin-top:2px;">{{ strtoupper($o->payment_method ?? '') }}</div>
                    </td>
                    <td><span class="badge {{ $badgeCls }} badge-dot">{{ $statusLabel }}</span></td>
                    <td style="font-size:12px;color:#9ca3af;white-space:nowrap;">
                        {{ $o->created_at->format('M j, H:i') }}<br>
                        <span style="font-size:10px;">{{ $o->created_at->diffForHumans() }}</span>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a href="{{ route('admin.module-data.egrocery.order.show', $o->id) }}" class="btn btn-sm" style="background:#eff6ff;color:#2563eb;" title="View"><i class="fas fa-eye"></i></a>
                            @if(in_array($o->status, ['pending','confirmed','picking','ready']))
                            <button onclick="quickStatus('{{ $o->id }}', '{{ json_encode($o->toArray()) }}')" class="btn btn-sm" style="background:#f0fdf4;color:#16a34a;" title="Update Status"><i class="fas fa-circle-arrow-right"></i></button>
                            @endif
                            <a href="{{ route('admin.module-data.egrocery.order.print', $o->id) }}" target="_blank" class="btn btn-sm" style="background:#f1f5f9;color:#374151;" title="Print Slip"><i class="fas fa-print"></i></a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:40px;">No orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
    <div class="card-footer">{{ $orders->links() }}</div>
    @endif
</div>

{{-- Quick status modal --}}
<div id="quickStatusModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:380px;box-shadow:0 25px 60px rgba(0,0,0,.2);">
        <div style="padding:18px 20px;border-bottom:1px solid #f1f5f9;font-weight:800;display:flex;justify-content:space-between;align-items:center;">
            <span><i class="fas fa-circle-arrow-right" style="color:#10b981;margin-right:8px;"></i>Update Status</span>
            <button onclick="document.getElementById('quickStatusModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:18px;color:#9ca3af;">&times;</button>
        </div>
        <form id="quickStatusForm" method="POST" style="padding:18px 20px;display:flex;flex-direction:column;gap:14px;">
            @csrf
            <div id="qsTransitions" style="display:flex;flex-direction:column;gap:8px;"></div>
            <div id="qsCancelReason" style="display:none;">
                <label class="form-label">Cancellation Reason</label>
                <input type="text" name="cancelled_reason" class="form-control" placeholder="Reason for cancellation">
            </div>
            <button type="button" onclick="document.getElementById('quickStatusModal').style.display='none'" class="btn" style="background:#f1f5f9;color:#374151;">Close</button>
        </form>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const orderBase = '{{ url("admin/module-data/egrocery/orders") }}';
const transitions = {
    pending:          ['confirmed','cancelled'],
    confirmed:        ['picking','cancelled'],
    picking:          ['ready','cancelled'],
    ready:            ['out_for_delivery'],
    out_for_delivery: ['delivered'],
};
const statusLabels = {
    confirmed:'✅ Confirm','picking':'🛒 Start Picking','ready':'📦 Mark Ready',
    out_for_delivery:'🚚 Send for Delivery','delivered':'🎉 Mark Delivered','cancelled':'❌ Cancel'
};
const statusColors2 = {
    confirmed:'#eff6ff;color:#2563eb',picking:'#f5f3ff;color:#7c3aed',ready:'#f0fdfa;color:#0d9488',
    out_for_delivery:'#fff8f0;color:#c05800',delivered:'#f0fdf4;color:#16a34a',cancelled:'#fef2f2;color:#dc2626'
};

function quickStatus(orderId, orderJson) {
    const order = typeof orderJson === 'string' ? JSON.parse(orderJson) : orderJson;
    const nextStatuses = transitions[order.status] || [];
    const container = document.getElementById('qsTransitions');
    container.innerHTML = '';
    if (!nextStatuses.length) { container.innerHTML = '<p style="color:#9ca3af;text-align:center;">No transitions available.</p>'; }
    nextStatuses.forEach(status => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.style = `width:100%;padding:12px;border-radius:10px;border:1.5px solid #e8edf5;background:${statusColors2[status]||'#f1f5f9'};font-size:14px;font-weight:700;cursor:pointer;text-align:left;`;
        btn.textContent = statusLabels[status] || status;
        btn.onclick = () => submitStatus(orderId, status);
        container.appendChild(btn);
    });
    document.getElementById('quickStatusModal').style.display = 'flex';
}

function submitStatus(orderId, status) {
    const reason = status === 'cancelled' ? prompt('Cancellation reason (optional):') : null;
    const fd = new FormData();
    fd.append('_token', csrfToken);
    fd.append('status', status);
    if (reason) fd.append('cancelled_reason', reason);
    fetch(orderBase + '/' + orderId + '/status', { method:'POST', body: fd })
        .then(() => { document.getElementById('quickStatusModal').style.display='none'; location.reload(); });
}
</script>
@endsection
