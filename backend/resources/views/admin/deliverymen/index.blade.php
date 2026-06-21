@extends('admin.layouts.app')
@section('title', 'Deliverymen')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Deliverymen</h1>
        <ul class="breadcrumb"><li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li>Deliverymen</li></ul>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('admin.deliverymen.earnings') }}" class="btn btn-outline-success"><i class="fas fa-coins"></i> Earnings</a>
        <button class="btn btn-outline" onclick="openModal('settingsModal')"><i class="fas fa-cog"></i> Settings</button>
        <button class="btn btn-primary" onclick="openModal('createModal')"><i class="fas fa-plus"></i> Add Driver</button>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

{{-- Stats --}}
@php
    $all = $deliverymen->getCollection();
    $avail = $all->where('status','available')->count();
    $busy  = $all->where('status','busy')->count();
    $pend  = $all->where('is_approved',false)->count();
    $normal = $all->where('driver_type','normal')->count();
    $truck  = $all->where('driver_type','truck')->count();
@endphp
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:16px;">
    <div style="background:#fff;border-radius:12px;padding:14px 16px;border-left:4px solid #10B981;">
        <div style="font-size:22px;font-weight:900;">{{ $avail }}</div><div style="font-size:11px;color:#8A8A9A;">Available</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px 16px;border-left:4px solid #F59E0B;">
        <div style="font-size:22px;font-weight:900;">{{ $busy }}</div><div style="font-size:11px;color:#8A8A9A;">On Delivery</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px 16px;border-left:4px solid #FF8A00;">
        <div style="font-size:22px;font-weight:900;">{{ $pend }}</div><div style="font-size:11px;color:#8A8A9A;">Pending</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px 16px;border-left:4px solid #3B82F6;">
        <div style="font-size:22px;font-weight:900;">{{ $normal }}</div><div style="font-size:11px;color:#8A8A9A;">🏍️ Normal</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px 16px;border-left:4px solid #8B5CF6;">
        <div style="font-size:22px;font-weight:900;">{{ $truck }}</div><div style="font-size:11px;color:#8A8A9A;">🚛 Truck</div>
    </div>
</div>

{{-- Filters --}}
<div class="card" style="padding:12px 16px;margin-bottom:12px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <input type="text" name="search" class="form-control" style="width:200px;" value="{{ request('search') }}" placeholder="Search name or phone...">
        <select name="status" class="form-control" style="width:140px;" onchange="this.form.submit()">
            <option value="">All Status</option>
            @foreach(['pending','available','busy','offline','banned'] as $st)
            <option value="{{ $st }}" {{ request('status')===$st?'selected':'' }}>{{ ucfirst($st) }}</option>
            @endforeach
        </select>
        <select name="driver_type" class="form-control" style="width:140px;" onchange="this.form.submit()">
            <option value="">All Types</option>
            <option value="normal" {{ request('driver_type')==='normal'?'selected':'' }}>🏍️ Normal</option>
            <option value="truck" {{ request('driver_type')==='truck'?'selected':'' }}>🚛 Truck</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
    </form>
</div>

{{-- Table --}}
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Driver</th>
                    <th>Phone</th>
                    <th>Type</th>
                    <th>Vehicle</th>
                    <th>Status</th>
                    <th>Docs</th>
                    <th>Rating</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deliverymen as $dm)
                @php
                    $sc = ['available'=>'badge-success','busy'=>'badge-warning','offline'=>'badge-secondary','pending'=>'badge-warning','banned'=>'badge-danger'][$dm->status] ?? 'badge-secondary';
                    $vehicleEmoji = ['motorcycle'=>'🏍️','bajaj'=>'🛺','car'=>'🚗','van'=>'🚐','truck'=>'🚛','bicycle'=>'🚲','pickup'=>'🚛'][$dm->vehicle_type] ?? '🚗';
                @endphp
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="avatar avatar-sm avatar-green">{{ strtoupper(substr($dm->user->name??'D',0,1)) }}</div>
                            <div>
                                <span style="font-weight:700;font-size:13px;">{{ $dm->user->name ?? 'N/A' }}</span>
                                @if(!$dm->is_approved)
                                <span style="display:block;font-size:10px;color:#FF8A00;font-weight:600;">⏳ Pending Approval</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td style="font-size:13px;color:#8A8A9A;">{{ $dm->user->phone ?? '—' }}</td>
                    <td>
                        @if($dm->driver_type === 'truck')
                        <span class="badge" style="background:#f3e8ff;color:#7c3aed;">🚛 Truck</span>
                        @else
                        <span class="badge" style="background:#e0f2fe;color:#0284c7;">🏍️ Normal</span>
                        @endif
                    </td>
                    <td><span class="badge badge-info">{{ $vehicleEmoji }} {{ ucfirst($dm->vehicle_type ?? '—') }}</span></td>
                    <td><span class="badge {{ $sc }}">{{ ucfirst($dm->status) }}</span></td>
                    <td>
                        <a href="{{ route('admin.deliverymen.show', $dm->id) }}" style="font-size:11px;color:#3B82F6;font-weight:600;">
                            📄 View
                        </a>
                    </td>
                    <td>
                        <span style="color:#f59e0b;">★</span>
                        <span style="font-weight:700;">{{ number_format($dm->rating ?? 5, 1) }}</span>
                    </td>
                    <td style="font-size:12px;color:#8A8A9A;">{{ $dm->created_at->format('d M Y') }}</td>
                    <td>
                        <div style="display:flex;gap:4px;flex-wrap:wrap;">
                            @if(!$dm->is_approved)
                            <form action="{{ route('admin.deliverymen.approve', $dm->id) }}" method="POST" style="margin:0;">
                                @csrf
                                <button class="btn btn-xs btn-success"><i class="fas fa-check"></i></button>
                            </form>
                            <form action="{{ route('admin.deliverymen.reject', $dm->id) }}" method="POST" style="margin:0;">
                                @csrf
                                <button class="btn btn-xs btn-warning"><i class="fas fa-times"></i></button>
                            </form>
                            @endif
                            <form action="{{ route('admin.deliverymen.toggle-block', $dm->id) }}" method="POST" style="margin:0;">
                                @csrf
                                <button class="btn btn-xs {{ $dm->user->status === 'banned' ? 'btn-outline' : 'btn-danger' }}" title="{{ $dm->user->status === 'banned' ? 'Unblock' : 'Block' }}">
                                    <i class="fas {{ $dm->user->status === 'banned' ? 'fa-unlock' : 'fa-ban' }}"></i>
                                </button>
                            </form>
                            <form action="{{ route('admin.deliverymen.destroy', $dm->id) }}" method="POST" style="margin:0;" onsubmit="return confirm('Delete this driver permanently?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-xs btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9"><div class="empty-state"><i class="fas fa-motorcycle"></i><h3>No drivers found</h3></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($deliverymen->hasPages())
    <div style="padding:16px;display:flex;justify-content:center;">{{ $deliverymen->withQueryString()->links() }}</div>
    @endif
</div>

{{-- Create Driver Modal --}}
<div class="modal-overlay" id="createModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Add New Driver</h3>
            <button class="modal-close" onclick="closeModal('createModal')">✕</button>
        </div>
        <form action="{{ route('admin.deliverymen.store') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="Driver name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone *</label>
                        <input type="text" name="phone" class="form-control" required placeholder="+252...">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="text" name="password" class="form-control" required placeholder="Min 4 characters">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Plate Number</label>
                        <input type="text" name="plate_number" class="form-control" placeholder="MG-1234">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Driver Type *</label>
                        <select name="driver_type" class="form-control" required>
                            <option value="normal">🏍️ Normal Delivery</option>
                            <option value="truck">🚛 Truck / Moving</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Vehicle Type *</label>
                        <select name="vehicle_type" class="form-control" required>
                            <option value="motorcycle">🏍️ Motorcycle</option>
                            <option value="bajaj">🛺 Bajaj</option>
                            <option value="car">🚗 Car</option>
                            <option value="truck">🚛 Truck</option>
                            <option value="van">🚐 Van</option>
                            <option value="bicycle">🚲 Bicycle</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px">
                        <input type="checkbox" name="auto_approve" value="1"> Auto-approve this driver
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Driver</button>
            </div>
        </form>
    </div>
</div>
{{-- Settings Modal --}}
<div class="modal-overlay" id="settingsModal">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-cog" style="color:#FF8A00;margin-right:8px;"></i> Delivery Settings</h3>
            <button class="modal-close" onclick="closeModal('settingsModal')">✕</button>
        </div>
        <form action="{{ route('admin.deliverymen.settings') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Max Orders Per Driver</label>
                    <p style="font-size:11px;color:#8A8A9A;margin:0 0 8px;">Maximum number of active orders a single driver can accept at once.</p>
                    <div style="display:flex;gap:8px;align-items:center;">
                        @php $currentMax = (int) \App\Helpers\AppSettings::get('max_orders_per_driver', 5); @endphp
                        @foreach([1, 3, 5, 10, 15, 20] as $v)
                        <label style="display:flex;align-items:center;gap:4px;padding:8px 14px;border-radius:10px;cursor:pointer;font-weight:700;font-size:14px;
                            {{ $currentMax == $v ? 'background:#FF8A00;color:#fff;' : 'background:#f0f1f5;color:#07003B;' }}">
                            <input type="radio" name="max_orders_per_driver" value="{{ $v }}" {{ $currentMax == $v ? 'checked' : '' }} style="display:none;">
                            {{ $v }}
                        </label>
                        @endforeach
                    </div>
                </div>
                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label">Or enter custom value</label>
                    <input type="number" name="max_orders_custom" class="form-control" min="1" max="50" placeholder="e.g. 7" style="width:120px;">
                    <p style="font-size:11px;color:#8A8A9A;margin:4px 0 0;">Leave empty to use the selected value above.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('settingsModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
            </div>
        </form>
    </div>
</div>
@endsection
