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
        <a href="{{ route('admin.qr.show', ['driver', $deliveryman->id]) }}" target="_blank"
           class="btn btn-outline"><i class="fas fa-qrcode"></i> QR Code</a>
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
        <div class="card-header">
            <div class="card-header-title"><div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:#3B82F6;"><i class="fas fa-folder"></i></div> Documents</div>
            @if($deliveryman->documents->where('status','pending')->count() > 0)
            <div style="display:flex;gap:8px;margin-left:auto;">
                <form action="{{ route('admin.deliverymen.documents.bulk-approve', $deliveryman->id) }}" method="POST" style="margin:0;"
                      onsubmit="return confirm('Approve all pending documents?')">
                    @csrf
                    <button class="btn btn-sm btn-success"><i class="fas fa-check-double"></i> Approve All</button>
                </form>
                <form action="{{ route('admin.deliverymen.documents.bulk-reject', $deliveryman->id) }}" method="POST" style="margin:0;"
                      onsubmit="return confirm('Reject all pending documents?')">
                    @csrf
                    <button class="btn btn-sm btn-danger"><i class="fas fa-times-circle"></i> Reject All</button>
                </form>
            </div>
            @endif
        </div>
        <div class="card-body">
            @forelse($deliveryman->documents ?? [] as $doc)
            <div style="display:flex;align-items:center;gap:12px;padding:12px;border:1.5px solid #f0f1f5;border-radius:12px;margin-bottom:10px;">
                @php $docUrl = asset('storage/' . $doc->file_path); @endphp
                {{-- Thumbnail — click opens lightbox --}}
                <div onclick="openDocPreview('{{ $docUrl }}', '{{ ucwords(str_replace('_',' ',$doc->type)) }}')"
                     style="flex-shrink:0;cursor:zoom-in;position:relative;width:70px;height:70px;">
                    <img src="{{ $docUrl }}"
                         style="width:70px;height:70px;object-fit:cover;border-radius:10px;border:2px solid #e0e0e0;transition:border-color .2s;"
                         onmouseover="this.style.borderColor='#3B82F6'" onmouseout="this.style.borderColor='#e0e0e0'"
                         onerror="this.parentElement.innerHTML='<div style=\'width:70px;height:70px;border-radius:10px;border:2px dashed #e0e0e0;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:22px;cursor:pointer;\'>📄</div>'">
                    <div style="position:absolute;bottom:3px;right:3px;background:rgba(0,0,0,0.55);border-radius:4px;padding:1px 4px;font-size:9px;color:#fff;">
                        <i class="fas fa-search-plus"></i>
                    </div>
                </div>
                <div style="flex:1;">
                    <div style="font-weight:700;font-size:13px;color:#07003B;">{{ ucwords(str_replace('_',' ',$doc->type)) }}</div>
                    <div style="font-size:11px;color:#8A8A9A;">Uploaded {{ $doc->created_at->diffForHumans() }}</div>
                    @php $dsc = ['pending'=>'badge-warning','approved'=>'badge-success','rejected'=>'badge-danger'][$doc->status] ?? 'badge-secondary'; @endphp
                    <span class="badge {{ $dsc }}" style="margin-top:4px;">{{ ucfirst($doc->status) }}</span>
                    <div style="margin-top:6px;">
                        <a href="{{ $docUrl }}" target="_blank" style="font-size:11px;color:#3B82F6;text-decoration:none;">
                            <i class="fas fa-external-link-alt"></i> Open full size
                        </a>
                    </div>
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

    {{-- Document Preview Lightbox --}}
    <div id="docLightbox" onclick="closeDocPreview()"
         style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;align-items:center;justify-content:center;flex-direction:column;gap:14px;">
        <div onclick="event.stopPropagation()" style="position:relative;max-width:90vw;max-height:85vh;">
            <img id="docLightboxImg" src="" alt=""
                 style="max-width:90vw;max-height:80vh;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,0.6);object-fit:contain;">
            <button onclick="closeDocPreview()"
                    style="position:absolute;top:-14px;right:-14px;width:36px;height:36px;border-radius:50%;border:none;background:#fff;color:#222;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,0.3);">✕</button>
        </div>
        <div id="docLightboxTitle" style="color:#fff;font-size:14px;font-weight:700;letter-spacing:.5px;"></div>
        <a id="docLightboxOpen" href="#" target="_blank"
           style="color:#93c5fd;font-size:12px;text-decoration:none;" onclick="event.stopPropagation()">
            <i class="fas fa-external-link-alt"></i> Open full size
        </a>
    </div>

    @push('scripts')
    <script>
    function openDocPreview(url, title) {
        document.getElementById('docLightboxImg').src    = url;
        document.getElementById('docLightboxTitle').textContent = title;
        document.getElementById('docLightboxOpen').href  = url;
        const lb = document.getElementById('docLightbox');
        lb.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function closeDocPreview() {
        document.getElementById('docLightbox').style.display = 'none';
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDocPreview(); });
    </script>
    @endpush
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
