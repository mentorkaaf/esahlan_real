@extends('admin.layouts.app')
@section('title', $deliveryman->user?->name ?? 'Driver Details')
@section('content')
@php $vehicleEmoji = ['motorcycle'=>'🏍️','bajaj'=>'🛺','car'=>'🚗','van'=>'🚐','truck'=>'🚛','bicycle'=>'🚲','pickup'=>'🚛'][$deliveryman->vehicle_type] ?? '🚗'; @endphp

<div class="page-header">
    <div>
        <h2 class="page-title">{{ $deliveryman->user?->name ?? 'Driver' }}</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.deliverymen.index') }}">Deliverymen</a></li>
            <li class="breadcrumb-item active">{{ $deliveryman->user?->name }}</li>
        </ol>
    </div>
    <div style="display:flex;gap:8px;">
        @if(!$deliveryman->is_approved)
        <form action="{{ route('admin.deliverymen.approve', $deliveryman->id) }}" method="POST" style="margin:0;">@csrf
            <button class="btn btn-success"><i class="fas fa-check"></i> Approve</button>
        </form>
        @endif
        <form action="{{ route('admin.deliverymen.toggle-block', $deliveryman->id) }}" method="POST" style="margin:0;">@csrf
            <button class="btn {{ $deliveryman->user?->status === 'banned' ? 'btn-outline' : 'btn-danger' }}">
                <i class="fas {{ $deliveryman->user?->status === 'banned' ? 'fa-unlock' : 'fa-ban' }}"></i>
                {{ $deliveryman->user?->status === 'banned' ? 'Unblock' : 'Block' }}
            </button>
        </form>
        <a href="{{ route('admin.deliverymen.index') }}" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    {{-- Profile Card --}}
    <div class="card">
        <div class="card-header"><div class="card-header-title"><div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:#FF8A00;"><i class="fas fa-user"></i></div> Profile</div></div>
        <div class="card-body">
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
                <div style="width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#FF8A00,#FF6B00);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;color:#fff;">
                    {{ strtoupper(substr($deliveryman->user?->name ?? 'D', 0, 1)) }}
                </div>
                <div>
                    <div style="font-size:18px;font-weight:800;color:#07003B;">{{ $deliveryman->user?->name }}</div>
                    <div style="font-size:13px;color:#8A8A9A;">{{ $deliveryman->user?->phone }}</div>
                    @if($deliveryman->driver_type === 'truck')
                    <span class="badge" style="background:#f3e8ff;color:#7c3aed;margin-top:4px;">🚛 Truck Driver</span>
                    @else
                    <span class="badge" style="background:#e0f2fe;color:#0284c7;margin-top:4px;">🏍️ Normal Driver</span>
                    @endif
                </div>
            </div>
            <table style="width:100%;">
                <tr><td style="padding:8px 0;color:#8A8A9A;width:130px;">Vehicle</td><td style="font-weight:600;">{{ $vehicleEmoji }} {{ ucfirst($deliveryman->vehicle_type) }}</td></tr>
                <tr><td style="padding:8px 0;color:#8A8A9A;">Plate</td><td style="font-weight:600;">{{ $deliveryman->vehicle_plate ?? '—' }}</td></tr>
                <tr><td style="padding:8px 0;color:#8A8A9A;">Status</td><td>
                    @php $sc = ['available'=>'badge-success','busy'=>'badge-warning','offline'=>'badge-secondary','pending'=>'badge-warning','banned'=>'badge-danger'][$deliveryman->status] ?? 'badge-secondary'; @endphp
                    <span class="badge {{ $sc }}">{{ ucfirst($deliveryman->status) }}</span>
                </td></tr>
                <tr><td style="padding:8px 0;color:#8A8A9A;">Approved</td><td>{{ $deliveryman->is_approved ? '✅ Yes' : '❌ No' }}</td></tr>
                <tr><td style="padding:8px 0;color:#8A8A9A;">Rating</td><td><span style="color:#f59e0b;">★</span> {{ number_format($deliveryman->rating ?? 5, 1) }} · {{ $deliveryman->total_deliveries }} trips</td></tr>
                <tr><td style="padding:8px 0;color:#8A8A9A;">Online</td><td>{{ $deliveryman->is_online ? '🟢 Yes' : '⚫ No' }}</td></tr>
                <tr><td style="padding:8px 0;color:#8A8A9A;">Joined</td><td>{{ $deliveryman->created_at->format('d M Y, H:i') }}</td></tr>
            </table>
        </div>
    </div>

    {{-- Documents Card --}}
    <div class="card">
        <div class="card-header"><div class="card-header-title"><div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:#3B82F6;"><i class="fas fa-folder"></i></div> Documents</div></div>
        <div class="card-body">
            @forelse($deliveryman->documents ?? [] as $doc)
            <div style="display:flex;align-items:center;gap:12px;padding:12px;border:1.5px solid #f0f1f5;border-radius:12px;margin-bottom:10px;">
                @php $docUrl = url('/api/v1/img/' . $doc->file_path); @endphp
                <a href="{{ $docUrl }}" target="_blank" style="flex-shrink:0;">
                    <img src="{{ $docUrl }}" style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:1px solid #e0e0e0;" onerror="this.style.display='none'">
                </a>
                <div style="flex:1;">
                    <div style="font-weight:700;font-size:13px;color:#07003B;">{{ ucwords(str_replace('_',' ',$doc->type)) }}</div>
                    <div style="font-size:11px;color:#8A8A9A;">Uploaded {{ $doc->created_at->diffForHumans() }}</div>
                    @php $dsc = ['pending'=>'badge-warning','approved'=>'badge-success','rejected'=>'badge-danger'][$doc->status] ?? 'badge-secondary'; @endphp
                    <span class="badge {{ $dsc }}" style="margin-top:4px;">{{ ucfirst($doc->status) }}</span>
                </div>
                @if($doc->status === 'pending')
                <div style="display:flex;gap:4px;flex-direction:column;">
                    <form action="{{ route('admin.deliverymen.document.approve', $doc->id) }}" method="POST" style="margin:0;">@csrf
                        <button class="btn btn-xs btn-success" style="width:100%;"><i class="fas fa-check"></i> Approve</button>
                    </form>
                    <form action="{{ route('admin.deliverymen.document.reject', $doc->id) }}" method="POST" style="margin:0;">@csrf
                        <button class="btn btn-xs btn-danger" style="width:100%;"><i class="fas fa-times"></i> Reject</button>
                    </form>
                </div>
                @endif
            </div>
            @empty
            <div style="text-align:center;padding:30px;color:#8A8A9A;">
                <i class="fas fa-file-alt" style="font-size:28px;display:block;margin-bottom:8px;opacity:0.3;"></i>
                No documents uploaded yet
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Recent Deliveries --}}
<div class="card" style="margin-top:16px;">
    <div class="card-header"><div class="card-header-title"><div class="card-header-icon" style="background:rgba(16,185,129,0.1);color:#10B981;"><i class="fas fa-receipt"></i></div> Recent Deliveries</div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Order #</th><th>Module</th><th>Total</th><th>Delivery Fee</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                @forelse($deliveryman->orders ?? [] as $order)
                <tr>
                    <td><a href="{{ route('admin.orders.show', $order->id) }}" style="font-weight:700;color:#FF8A00;">#{{ $order->order_number }}</a></td>
                    <td><span class="badge badge-info">{{ $order->module_slug }}</span></td>
                    <td style="font-weight:700;">${{ number_format($order->total_amount, 2) }}</td>
                    <td>${{ number_format($order->delivery_fee, 2) }}</td>
                    <td>
                        @php $osc = ['delivered'=>'badge-success','pending'=>'badge-warning','cancelled'=>'badge-danger'][$order->status] ?? 'badge-secondary'; @endphp
                        <span class="badge {{ $osc }}">{{ ucfirst($order->status) }}</span>
                    </td>
                    <td style="font-size:12px;color:#8A8A9A;">{{ $order->created_at->format('d M Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;padding:20px;color:#8A8A9A;">No deliveries yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
