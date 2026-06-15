@extends('admin.layouts.app')
@section('title', 'Vendor: ' . $vendor->name)
@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">{{ $vendor->name }}</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.vendors.index') }}">Vendors</a></li>
            <li class="breadcrumb-item active">{{ $vendor->name }}</li>
        </ol>
    </div>
    <div class="d-flex gap-2">
        @if(!$vendor->is_approved)
        <form action="{{ route('admin.vendors.approve', $vendor->id) }}" method="POST" style="display:inline;">
            @csrf
            <button class="btn btn-success"><i class="fas fa-check"></i> Approve</button>
        </form>
        <form action="{{ route('admin.vendors.reject', $vendor->id) }}" method="POST" style="display:inline;">
            @csrf
            <button class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
        </form>
        @endif
        <form action="{{ route('admin.vendors.toggle-featured', $vendor->id) }}" method="POST">
            @csrf
            <button class="btn {{ $vendor->is_featured ? 'btn-secondary' : 'btn-primary' }}">
                <i class="fas fa-star"></i> {{ $vendor->is_featured ? 'Remove Featured' : 'Make Featured' }}
            </button>
        </form>
    </div>
</div>

<div class="grid-2">
    <div>
        <div class="card">
            <div class="card-header"><span>Vendor Info</span>
                <span class="badge {{ $vendor->is_approved ? 'badge-success' : 'badge-warning' }}">
                    {{ $vendor->is_approved ? 'Approved' : 'Pending' }}
                </span>
            </div>
            <div class="card-body">
                <table>
                    <tr><td class="text-muted" style="width:130px;padding:8px 12px 8px 0;">Name</td><td class="fw-bold">{{ $vendor->name }}</td></tr>
                    <tr><td class="text-muted">Owner</td><td>{{ $vendor->user?->name ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Phone</td><td>{{ $vendor->user?->phone ?? $vendor->phone ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Email</td><td>{{ $vendor->user?->email ?? $vendor->email ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Module</td><td>{{ $vendor->module?->name ?? strtoupper($vendor->module_slug ?? '—') }}</td></tr>
                    <tr><td class="text-muted">District</td><td>{{ $vendor->district?->name ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Rating</td><td>{{ $vendor->rating ?? '—' }} ⭐</td></tr>
                    <tr><td class="text-muted">Featured</td><td>{{ $vendor->is_featured ? 'Yes' : 'No' }}</td></tr>
                    <tr><td class="text-muted">Status</td>
                        <td><span class="badge {{ $vendor->is_active ? 'badge-success' : 'badge-danger' }}">{{ $vendor->is_active ? 'Active' : 'Inactive' }}</span></td>
                    </tr>
                    <tr><td class="text-muted">Joined</td><td>{{ $vendor->created_at->format('d M Y') }}</td></tr>
                    <tr>
                        <td class="text-muted" style="vertical-align:top;padding-top:10px;">Business License</td>
                        <td style="padding:8px 0;">
                            @if($vendor->business_license)
                                @php $ext = strtolower(pathinfo($vendor->business_license, PATHINFO_EXTENSION)); @endphp
                                @if(in_array($ext, ['jpg','jpeg','png','webp']))
                                    <a href="{{ asset('storage/' . $vendor->business_license) }}" target="_blank">
                                        <img src="{{ asset('storage/' . $vendor->business_license) }}"
                                             alt="Business License"
                                             style="max-width:220px;max-height:160px;border-radius:8px;border:1.5px solid #e8eaf0;cursor:pointer;">
                                    </a>
                                    <div style="margin-top:6px;">
                                        <a href="{{ asset('storage/' . $vendor->business_license) }}" target="_blank"
                                           style="font-size:12px;color:#FF8A00;font-weight:600;text-decoration:none;">
                                            <i class="fas fa-external-link-alt"></i> View Full Image
                                        </a>
                                    </div>
                                @elseif($ext === 'pdf')
                                    <a href="{{ asset('storage/' . $vendor->business_license) }}" target="_blank"
                                       style="display:inline-flex;align-items:center;gap:8px;padding:10px 16px;background:#fff5f5;border:1.5px solid #fecaca;border-radius:9px;color:#b91c1c;font-weight:700;font-size:13px;text-decoration:none;">
                                        <i class="fas fa-file-pdf" style="font-size:18px;"></i> View PDF Document
                                    </a>
                                @endif
                            @else
                                <span style="color:#9ca3af;font-style:italic;">No document uploaded</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header">Recent Orders</div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Order #</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        @forelse($vendor->orders ?? [] as $order)
                        <tr>
                            <td><a href="{{ route('admin.orders.show', $order->id) }}" class="text-primary">{{ $order->order_number }}</a></td>
                            <td>${{ number_format($order->total_amount, 2) }}</td>
                            <td><span class="badge badge-secondary">{{ ucfirst(str_replace('_',' ',$order->status)) }}</span></td>
                            <td>{{ $order->created_at->format('d M') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="text-align:center;padding:20px;color:#888;">No orders yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
