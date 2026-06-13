@extends('admin.layouts.app')
@section('title', 'Vendors')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Vendors</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li>Vendors</li>
        </ul>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;align-items:center;">
            <div style="flex:1;min-width:200px;">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search vendors…" value="{{ request('search') }}">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <select name="module_id" class="form-control" style="width:170px;">
                <option value="">All Modules</option>
                @foreach($modules as $module)
                    <option value="{{ $module->id }}" {{ request('module_id')==$module->id?'selected':'' }}>{{ $module->name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-control" style="width:140px;">
                <option value="">All Status</option>
                <option value="active"   {{ request('status')==='active'   ?'selected':'' }}>Active</option>
                <option value="inactive" {{ request('status')==='inactive' ?'selected':'' }}>Inactive</option>
            </select>
            @if(request()->hasAny(['search','module_id','status']))
            <a href="{{ route('admin.vendors.index') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(139,92,246,0.1);color:var(--purple);">
                <i class="fas fa-store"></i>
            </div>
            Vendors
            <span class="badge badge-purple" style="margin-left:4px;">{{ $vendors->total() }}</span>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Vendor</th>
                    <th>Module</th>
                    <th>District</th>
                    <th>Rating</th>
                    <th>Status</th>
                    <th>Featured</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vendors as $vendor)
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            @if(!empty($vendor->logo_url) && !str_contains(($vendor->logo_url??''),'null'))
                                <img src="{{ $vendor->logo_url }}" style="width:38px;height:38px;border-radius:9px;object-fit:cover;flex-shrink:0;border:1px solid var(--border);">
                            @else
                                <div class="avatar avatar-sm avatar-purple">{{ strtoupper(substr($vendor->name,0,1)) }}</div>
                            @endif
                            <div>
                                <div style="font-weight:700;font-size:13px;">{{ $vendor->name }}</div>
                                <div style="font-size:11px;color:var(--text-muted);">{{ $vendor->phone }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-dark">{{ $vendor->module?->name ?? '—' }}</span>
                    </td>
                    <td style="font-size:12.5px;color:var(--text-muted);">{{ $vendor->district?->name ?? '—' }}</td>
                    <td>
                        <span style="color:#f59e0b;font-size:13px;">★</span>
                        <span style="font-weight:700;font-size:13px;">{{ number_format($vendor->rating??0,1) }}</span>
                        <span style="font-size:11px;color:var(--text-muted);">({{ $vendor->total_reviews??0 }})</span>
                    </td>
                    <td>
                        <span class="badge {{ $vendor->is_active?'badge-success':'badge-danger' }} badge-dot">
                            {{ $vendor->is_active?'Active':'Inactive' }}
                        </span>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.vendors.toggle-featured',$vendor) }}">
                            @csrf
                            <button type="submit" class="btn btn-xs {{ $vendor->is_featured?'btn-primary':'btn-outline' }}" title="Toggle Featured">
                                <i class="fas fa-star"></i>
                            </button>
                        </form>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);">{{ $vendor->created_at->format('d M Y') }}</td>
                    <td>
                        <div style="display:flex;gap:5px;">
                            <a href="{{ route('admin.vendors.show',$vendor) }}" class="btn btn-outline btn-xs"><i class="fas fa-eye"></i> View</a>
                            @if(!$vendor->is_active)
                            <form method="POST" action="{{ route('admin.vendors.approve',$vendor) }}">
                                @csrf
                                <button class="btn btn-xs btn-success"><i class="fas fa-check"></i> Approve</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state"><i class="fas fa-store"></i><h3>No vendors found</h3><p>Try adjusting your filters</p></div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($vendors->hasPages())
    <div class="card-footer" style="display:flex;justify-content:center;">
        {{ $vendors->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
