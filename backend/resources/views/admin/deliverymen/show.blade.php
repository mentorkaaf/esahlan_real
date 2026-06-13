@extends('admin.layouts.app')
@section('title', 'Deliveryman Details')
@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">{{ $deliveryman->user?->name ?? 'Deliveryman' }}</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.deliverymen.index') }}">Deliverymen</a></li>
            <li class="breadcrumb-item active">{{ $deliveryman->user?->name }}</li>
        </ol>
    </div>
    <div class="d-flex gap-2">
        @if(!$deliveryman->is_approved)
        <form action="{{ route('admin.deliverymen.approve', $deliveryman->id) }}" method="POST">
            @csrf <button class="btn btn-success">Approve</button>
        </form>
        @endif
        <form action="{{ route('admin.deliverymen.toggle-block', $deliveryman->id) }}" method="POST">
            @csrf
            <button class="btn {{ $deliveryman->status === 'banned' ? 'btn-secondary' : 'btn-danger' }}">
                {{ $deliveryman->status === 'banned' ? 'Unblock' : 'Block' }}
            </button>
        </form>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">Profile</div>
        <div class="card-body">
            <table>
                <tr><td class="text-muted" style="width:130px;padding:8px 12px 8px 0;">Name</td><td class="fw-bold">{{ $deliveryman->user?->name }}</td></tr>
                <tr><td class="text-muted">Phone</td><td>{{ $deliveryman->user?->phone }}</td></tr>
                <tr><td class="text-muted">Vehicle</td><td>{{ ucfirst($deliveryman->vehicle_type) }}</td></tr>
                <tr><td class="text-muted">Plate</td><td>{{ $deliveryman->plate_number ?? '—' }}</td></tr>
                <tr><td class="text-muted">Status</td><td><span class="badge badge-info">{{ ucfirst($deliveryman->status) }}</span></td></tr>
                <tr><td class="text-muted">Approved</td><td>{{ $deliveryman->is_approved ? '✅ Yes' : '❌ No' }}</td></tr>
                <tr><td class="text-muted">Rating</td><td>{{ $deliveryman->rating ?? '—' }} ⭐</td></tr>
                <tr><td class="text-muted">Joined</td><td>{{ $deliveryman->created_at->format('d M Y') }}</td></tr>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header">Recent Deliveries</div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Order #</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse($deliveryman->orders ?? [] as $order)
                    <tr>
                        <td>{{ $order->order_number }}</td>
                        <td>${{ number_format($order->total_amount, 2) }}</td>
                        <td><span class="badge badge-secondary">{{ ucfirst($order->status) }}</span></td>
                        <td>{{ $order->created_at->format('d M') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="text-align:center;padding:20px;color:#888;">No deliveries yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
