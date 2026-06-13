@extends('admin.layouts.app')
@section('title', 'Orders')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Orders</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li>Orders</li>
        </ul>
    </div>
    <div style="font-size:13px;color:var(--text-muted);">
        <i class="fas fa-sync-alt" style="color:var(--brand);margin-right:6px;"></i>
        Updated {{ \Carbon\Carbon::now(\App\Helpers\AppSettings::timezone())->format('H:i') }}
    </div>
</div>

{{-- Status Tabs --}}
@php
$statuses = [
    ''          => ['All', 'secondary'],
    'pending'   => ['Pending', 'warning'],
    'confirmed' => ['Confirmed', 'info'],
    'preparing' => ['Preparing', 'info'],
    'ready'     => ['Ready', 'teal'],
    'picked_up' => ['Picked Up', 'purple'],
    'delivered' => ['Delivered', 'success'],
    'cancelled' => ['Cancelled', 'danger'],
];
@endphp
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px;">
    @foreach($statuses as $st => [$label, $color])
    <a href="{{ route('admin.orders.index', ['status' => $st] + request()->except('status','page')) }}"
       style="padding:7px 16px;border-radius:20px;font-size:12.5px;font-weight:600;text-decoration:none;border:1.5px solid;transition:all .15s;
              {{ request('status') === $st
                 ? 'background:var(--brand);color:#fff;border-color:var(--brand);'
                 : 'background:#fff;color:var(--text-muted);border-color:var(--border);' }}">
        {{ $label }}
    </a>
    @endforeach
</div>

{{-- Filter Bar --}}
<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;width:100%;">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <div style="flex:1;min-width:200px;">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search order number, customer…" value="{{ request('search') }}">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" style="width:160px;">
            <span style="color:var(--text-muted);font-size:12px;">to</span>
            <input type="date" name="date_to"   class="form-control" value="{{ request('date_to') }}"   style="width:160px;">
            @if(request()->hasAny(['search','date_from','date_to']))
            <a href="{{ route('admin.orders.index',['status'=>request('status')]) }}" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
            @endif
        </form>
    </div>
</div>

{{-- Orders Table --}}
<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);">
                <i class="fas fa-shopping-bag"></i>
            </div>
            Orders
            <span class="badge badge-orange" style="margin-left:4px;">{{ $orders->total() }}</span>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Module / Vendor</th>
                    <th>Deliveryman</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                @php
                    $smap = [
                        'pending'   => 'badge-warning',
                        'confirmed' => 'badge-info',
                        'preparing' => 'badge-info',
                        'ready'     => 'badge-teal',
                        'picked_up' => 'badge-purple',
                        'delivered' => 'badge-success',
                        'cancelled' => 'badge-danger',
                        'failed'    => 'badge-danger',
                    ];
                    $bc = $smap[$order->status] ?? 'badge-secondary';
                    $total = $order->total_amount ?? $order->total ?? 0;
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('admin.orders.show',$order) }}" style="font-weight:700;color:var(--navy);text-decoration:none;font-size:13px;">
                            {{ $order->order_number }}
                        </a>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="avatar avatar-sm avatar-orange">{{ strtoupper(substr($order->user?->name ?? 'U',0,1)) }}</div>
                            <div>
                                <div style="font-weight:600;font-size:13px;line-height:1.2;">{{ $order->user?->name ?? '—' }}</div>
                                <div style="font-size:11px;color:var(--text-muted);">{{ $order->user?->phone ?? '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($order->module_slug)
                            <span class="badge badge-dark" style="margin-bottom:3px;">{{ strtoupper($order->module_slug) }}</span><br>
                        @endif
                        <span style="font-size:12px;color:var(--text-muted);">{{ $order->vendor?->name ?? '' }}</span>
                    </td>
                    <td>
                        @if($order->deliveryman?->user?->name)
                            <div style="display:flex;align-items:center;gap:7px;">
                                <div class="avatar avatar-sm avatar-green">{{ strtoupper(substr($order->deliveryman->user->name,0,1)) }}</div>
                                <span style="font-size:12.5px;font-weight:600;">{{ $order->deliveryman->user->name }}</span>
                            </div>
                        @else
                            <span class="badge badge-secondary">Unassigned</span>
                        @endif
                    </td>
                    <td><span style="font-weight:700;color:var(--text);">${{ number_format($total,2) }}</span></td>
                    <td>
                        @php $ps = $order->payment_status ?? 'pending'; @endphp
                        <span class="badge {{ $ps==='paid'?'badge-success':($ps==='refunded'?'badge-info':'badge-warning') }}">
                            {{ ucfirst($ps) }}
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ $bc }} badge-dot">
                            {{ ucwords(str_replace('_',' ',$order->status)) }}
                        </span>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
                        {{ $order->created_at->setTimezone(\App\Helpers\AppSettings::timezone())->format('d M') }}<br>
                        <span style="font-size:11px;">{{ $order->created_at->setTimezone(\App\Helpers\AppSettings::timezone())->format('H:i') }}</span>
                    </td>
                    <td>
                        <a href="{{ route('admin.orders.show',$order) }}" class="btn btn-outline btn-xs">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <i class="fas fa-shopping-bag"></i>
                            <h3>No orders found</h3>
                            <p>Try adjusting your filters</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
    <div class="card-footer" style="display:flex;justify-content:center;">
        {{ $orders->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
