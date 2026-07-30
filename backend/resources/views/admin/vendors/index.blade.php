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

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show mb-3">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;align-items:center;">
            <div style="flex:1;min-width:200px;">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search vendors…" value="{{ request('search') }}">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <select name="module_id" class="form-control" style="width:170px;" onchange="this.form.submit()">
                <option value="">All Modules</option>
                @foreach($modules as $module)
                    <option value="{{ $module->id }}" {{ request('module_id')==$module->id?'selected':'' }}>{{ $module->name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-control" style="width:140px;" onchange="this.form.submit()">
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

{{-- Bulk action form --}}
<form id="bulk-form" method="POST" action="{{ route('admin.vendors.bulk-destroy') }}">
    @csrf @method('DELETE')
    <div id="bulk-toolbar" style="display:none;align-items:center;gap:10px;padding:10px 16px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:12px;margin-bottom:12px;">
        <span id="bulk-count" style="font-weight:700;color:#ef4444;font-size:13px;"></span>
        <span style="color:var(--text-muted);font-size:13px;">vendors selected</span>
        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete selected vendors? This cannot be undone.')">
            <i class="fas fa-trash me-1"></i> Delete Selected
        </button>
        <button type="button" class="btn btn-outline btn-sm" onclick="clearSelection()">Cancel</button>
    </div>

    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
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
                        <th style="width:36px;">
                            <input type="checkbox" id="check-all" onchange="toggleAll(this)" style="width:16px;height:16px;cursor:pointer;">
                        </th>
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
                    <tr id="row-{{ $vendor->id }}">
                        <td>
                            <input type="checkbox" name="ids[]" value="{{ $vendor->id }}" class="row-check" onchange="updateBulkBar()" style="width:16px;height:16px;cursor:pointer;">
                        </td>
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
                        <td><span class="badge badge-dark">{{ $vendor->module?->name ?? '—' }}</span></td>
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
                            <div style="display:flex;gap:5px;flex-wrap:wrap;">
                                <a href="{{ route('admin.vendors.show',$vendor) }}" class="btn btn-outline btn-xs"><i class="fas fa-eye"></i> View</a>

                                @if(!$vendor->is_active)
                                <form method="POST" action="{{ route('admin.vendors.approve',$vendor) }}">
                                    @csrf
                                    <button class="btn btn-xs btn-success"><i class="fas fa-check"></i> Approve</button>
                                </form>
                                @endif

                                @if($vendor->is_active)
                                <form method="POST" action="{{ route('admin.vendors.reject',$vendor) }}">
                                    @csrf
                                    <button class="btn btn-xs btn-warning" onclick="return confirm('Reject {{ addslashes($vendor->name) }}?')">
                                        <i class="fas fa-ban"></i> Reject
                                    </button>
                                </form>
                                @endif

                                <form method="POST" action="{{ route('admin.vendors.destroy',$vendor) }}" onsubmit="return confirm('Delete {{ addslashes($vendor->name) }}? This cannot be undone.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9">
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
</form>

<script>
function toggleAll(master) {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = master.checked);
    updateBulkBar();
}
function updateBulkBar() {
    const checked = document.querySelectorAll('.row-check:checked');
    const toolbar = document.getElementById('bulk-toolbar');
    const countEl = document.getElementById('bulk-count');
    const master  = document.getElementById('check-all');
    const total   = document.querySelectorAll('.row-check').length;
    if (checked.length > 0) {
        toolbar.style.display = 'flex';
        countEl.textContent = checked.length;
        master.indeterminate = checked.length > 0 && checked.length < total;
        master.checked = checked.length === total;
    } else {
        toolbar.style.display = 'none';
        master.indeterminate = false;
        master.checked = false;
    }
}
function clearSelection() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
    document.getElementById('check-all').checked = false;
    document.getElementById('check-all').indeterminate = false;
    document.getElementById('bulk-toolbar').style.display = 'none';
}
</script>
@endsection
