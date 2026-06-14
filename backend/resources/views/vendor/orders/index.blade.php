@extends('vendor.layouts.app')
@section('title', 'Orders')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Orders</h1>
        <ul class="breadcrumb"><li><a href="{{ route('vendor.dashboard') }}">Dashboard</a></li><li>Orders</li></ul>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;width:100%;">
            <input type="text" name="search" class="filter-input" placeholder="Search order #..." value="{{ request('search') }}">
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="pending" {{ request('status')=='pending'?'selected':'' }}>Pending</option>
                <option value="confirmed" {{ request('status')=='confirmed'?'selected':'' }}>Confirmed</option>
                <option value="ready_for_pickup" {{ request('status')=='ready_for_pickup'?'selected':'' }}>Ready</option>
                <option value="delivered" {{ request('status')=='delivered'?'selected':'' }}>Delivered</option>
                <option value="cancelled" {{ request('status')=='cancelled'?'selected':'' }}>Cancelled</option>
            </select>
            <input type="date" name="date" class="filter-input" value="{{ request('date') }}" onchange="this.form.submit()">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            @if(request()->anyFilled(['search','status','date']))
            <a href="{{ route('vendor.orders.index') }}" class="btn btn-outline btn-sm">Clear</a>
            @endif
        </form>
    </div>

    @if($orders->isEmpty())
    <div class="empty-state"><i class="fa-solid fa-bag-shopping"></i><p>No orders found</p></div>
    @else
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
                @foreach($orders as $order)
                <tr>
                    <td><a href="{{ route('vendor.orders.show',$order) }}" style="font-weight:700;color:var(--brand);text-decoration:none;">#{{ $order->order_number }}</a></td>
                    <td>{{ $order->user?->name ?? '—' }}</td>
                    <td>{{ $order->items->count() }}</td>
                    <td><strong>${{ number_format($order->total,2) }}</strong></td>
                    <td><span class="badge badge-neutral">{{ $order->payment_method ?? '—' }}</span></td>
                    <td>
                        @php
                        $cls = match($order->status) {
                            'pending'          => 'warning',
                            'confirmed'        => 'info',
                            'ready_for_pickup' => 'purple',
                            'delivered'        => 'success',
                            'cancelled'        => 'danger',
                            default            => 'neutral'
                        };
                        @endphp
                        <span class="badge badge-{{ $cls }}">{{ ucfirst(str_replace('_',' ',$order->status)) }}</span>
                    </td>
                    <td style="color:var(--text-muted);font-size:12px;">{{ $order->created_at->format('d M, H:i') }}</td>
                    <td>
                        <div style="display:flex;gap:5px;">
                            <a href="{{ route('vendor.orders.show',$order) }}" class="btn btn-outline btn-xs">View</a>
                            @if($order->status === 'pending')
                            <form action="{{ route('vendor.orders.accept',$order) }}" method="POST" style="margin:0;">
                                @csrf
                                <button type="submit" class="btn btn-success btn-xs">Accept</button>
                            </form>
                            @endif
                            @if($order->status === 'confirmed')
                            <button onclick="openModal('reject-{{ $order->id }}')" class="btn btn-danger btn-xs">Reject</button>
                            <form action="{{ route('vendor.orders.ready',$order) }}" method="POST" style="margin:0;">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-xs">Ready</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>

                {{-- Reject modal --}}
                @if(in_array($order->status, ['pending','confirmed']))
                <div class="modal-overlay" id="reject-{{ $order->id }}">
                    <div class="modal">
                        <div class="modal-header">
                            <div class="modal-title">Reject Order #{{ $order->order_number }}</div>
                            <button class="modal-close" onclick="closeModal('reject-{{ $order->id }}')"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <form action="{{ route('vendor.orders.reject',$order) }}" method="POST">
                            @csrf
                            <div class="modal-body">
                                <div class="form-group">
                                    <label class="form-label">Reason for rejection</label>
                                    <textarea name="reason" class="form-control" placeholder="Enter reason..." required></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline" onclick="closeModal('reject-{{ $order->id }}')">Cancel</button>
                                <button type="submit" class="btn btn-danger">Reject Order</button>
                            </div>
                        </form>
                    </div>
                </div>
                @endif
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="pagination-wrap">
        <div class="pagination-info">Showing {{ $orders->firstItem() }}–{{ $orders->lastItem() }} of {{ $orders->total() }} orders</div>
        {{ $orders->links('vendor.partials.pagination') }}
    </div>
    @endif
</div>
@endsection
