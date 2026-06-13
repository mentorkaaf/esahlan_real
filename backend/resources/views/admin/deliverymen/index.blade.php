@extends('admin.layouts.app')
@section('title', 'Deliverymen')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Deliverymen</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li>Deliverymen</li>
        </ul>
    </div>
</div>

{{-- Quick stats --}}
@php
    $dmAll       = $deliverymen ?? collect();
    $dmAvailable = $dmAll->where('status','available')->count();
    $dmBusy      = $dmAll->where('status','busy')->count();
    $dmPending   = $dmAll->where('is_approved',false)->count();
@endphp
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px;">
    <div style="background:#fff;border-radius:10px;border:1px solid var(--border);padding:14px 16px;display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:9px;background:rgba(16,185,129,0.1);color:var(--success);display:flex;align-items:center;justify-content:center;"><i class="fas fa-circle" style="font-size:9px;"></i></div>
        <div><div style="font-size:20px;font-weight:800;">{{ $dmAvailable }}</div><div style="font-size:11px;color:var(--text-muted);">Available</div></div>
    </div>
    <div style="background:#fff;border-radius:10px;border:1px solid var(--border);padding:14px 16px;display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:9px;background:rgba(245,158,11,0.1);color:var(--warning);display:flex;align-items:center;justify-content:center;"><i class="fas fa-motorcycle"></i></div>
        <div><div style="font-size:20px;font-weight:800;">{{ $dmBusy }}</div><div style="font-size:11px;color:var(--text-muted);">On Delivery</div></div>
    </div>
    <div style="background:#fff;border-radius:10px;border:1px solid var(--border);padding:14px 16px;display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:9px;background:rgba(255,138,0,0.1);color:var(--brand);display:flex;align-items:center;justify-content:center;"><i class="fas fa-hourglass-half"></i></div>
        <div><div style="font-size:20px;font-weight:800;">{{ $dmPending }}</div><div style="font-size:11px;color:var(--text-muted);">Pending Approval</div></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(20,184,166,0.1);color:#14b8a6;">
                <i class="fas fa-motorcycle"></i>
            </div>
            All Deliverymen
        </div>
        <form method="GET" style="display:flex;gap:8px;">
            <select name="status" class="form-control" style="width:150px;" onchange="this.form.submit()">
                <option value="">All Status</option>
                @foreach(['pending','available','busy','offline','banned'] as $st)
                <option value="{{ $st }}" {{ request('status')===$st?'selected':'' }}>{{ ucfirst($st) }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Deliveryman</th>
                    <th>Phone</th>
                    <th>Vehicle</th>
                    <th>Status</th>
                    <th>Approval</th>
                    <th>Rating</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deliverymen ?? [] as $dm)
                @php
                    $sc = ['available'=>'badge-success','busy'=>'badge-warning','offline'=>'badge-secondary','pending'=>'badge-warning','banned'=>'badge-danger'][$dm->status] ?? 'badge-secondary';
                @endphp
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="avatar avatar-sm avatar-green">{{ strtoupper(substr($dm->user->name??'D',0,1)) }}</div>
                            <span style="font-weight:700;font-size:13px;">{{ $dm->user->name ?? 'N/A' }}</span>
                        </div>
                    </td>
                    <td style="font-size:13px;color:var(--text-muted);">{{ $dm->user->phone ?? '—' }}</td>
                    <td><span class="badge badge-info">{{ ucfirst($dm->vehicle_type ?? '—') }}</span></td>
                    <td><span class="badge {{ $sc }} badge-dot">{{ ucfirst($dm->status) }}</span></td>
                    <td>
                        @if($dm->is_approved)
                            <span class="badge badge-success"><i class="fas fa-check"></i> Approved</span>
                        @else
                            <span class="badge badge-warning">Pending</span>
                        @endif
                    </td>
                    <td>
                        @if($dm->rating)
                        <span style="color:#f59e0b;">★</span>
                        <span style="font-weight:700;">{{ number_format($dm->rating,1) }}</span>
                        @else
                        <span style="color:var(--text-muted);">—</span>
                        @endif
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);">{{ $dm->created_at->format('d M Y') }}</td>
                    <td>
                        <div style="display:flex;gap:5px;flex-wrap:wrap;">
                            @if(!$dm->is_approved)
                            <form action="{{ route('admin.deliverymen.approve',$dm->id) }}" method="POST">
                                @csrf
                                <button class="btn btn-xs btn-success"><i class="fas fa-check"></i> Approve</button>
                            </form>
                            @endif
                            <form action="{{ route('admin.deliverymen.toggle-block',$dm->id) }}" method="POST">
                                @csrf
                                @if($dm->status === 'banned')
                                <button class="btn btn-xs btn-outline"><i class="fas fa-unlock"></i> Unblock</button>
                                @else
                                <button class="btn btn-xs" style="background:#fff5f5;color:var(--danger);border:1.5px solid #fecaca;"><i class="fas fa-ban"></i> Block</button>
                                @endif
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state"><i class="fas fa-motorcycle"></i><h3>No deliverymen found</h3></div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(isset($deliverymen) && method_exists($deliverymen,'links') && $deliverymen->hasPages())
    <div class="card-footer" style="display:flex;justify-content:center;">
        {{ $deliverymen->links() }}
    </div>
    @endif
</div>
@endsection
