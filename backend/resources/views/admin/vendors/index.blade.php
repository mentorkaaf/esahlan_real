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

{{-- Filter bar --}}
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
            @if($isFiltering)
            <a href="{{ route('admin.vendors.index') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            @endif
        </form>
    </div>
</div>

@if($isFiltering)
{{-- ══════════════════════════════════════════════════════════════════ --}}
{{-- FILTERED VIEW — flat table with pagination                        --}}
{{-- ══════════════════════════════════════════════════════════════════ --}}
<form id="bulk-form" method="POST" action="{{ route('admin.vendors.bulk-destroy') }}">
    @csrf @method('DELETE')
    <div id="bulk-toolbar" style="display:none;align-items:center;gap:10px;padding:10px 16px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:12px;margin-bottom:12px;">
        <span id="bulk-count" style="font-weight:700;color:#ef4444;font-size:13px;"></span>
        <span style="color:var(--text-muted);font-size:13px;">vendors selected</span>
        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete selected vendors?')">
            <i class="fas fa-trash me-1"></i> Delete Selected
        </button>
        <button type="button" class="btn btn-outline btn-sm" onclick="clearSelection()">Cancel</button>
    </div>
    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <div class="card-header-title">
                <div class="card-header-icon" style="background:rgba(139,92,246,0.1);color:var(--purple);"><i class="fas fa-store"></i></div>
                Results <span class="badge badge-purple" style="margin-left:4px;">{{ $vendors->total() }}</span>
            </div>
        </div>
        @include('admin.vendors._table', ['vendorList' => $vendors, 'showCheckbox' => true])
        @if($vendors->hasPages())
        <div class="card-footer" style="display:flex;justify-content:center;">{{ $vendors->withQueryString()->links() }}</div>
        @endif
    </div>
</form>

@else
{{-- ══════════════════════════════════════════════════════════════════ --}}
{{-- GROUPED VIEW — one card per module                                --}}
{{-- ══════════════════════════════════════════════════════════════════ --}}

@php
$moduleColors = [
    'eFood'     => ['bg'=>'rgba(255,138,0,0.12)', 'color'=>'#FF8A00', 'icon'=>'fa-utensils'],
    'eGrocery'  => ['bg'=>'rgba(16,185,129,0.12)', 'color'=>'#10B981', 'icon'=>'fa-shopping-basket'],
    'eShop'     => ['bg'=>'rgba(59,130,246,0.12)', 'color'=>'#3B82F6', 'icon'=>'fa-shopping-bag'],
    'eParcel'   => ['bg'=>'rgba(139,92,246,0.12)', 'color'=>'#8B5CF6', 'icon'=>'fa-box'],
    'eMoving'   => ['bg'=>'rgba(236,72,153,0.12)', 'color'=>'#EC4899', 'icon'=>'fa-truck-moving'],
    'eLearning' => ['bg'=>'rgba(245,158,11,0.12)', 'color'=>'#F59E0B', 'icon'=>'fa-graduation-cap'],
    'eExchange' => ['bg'=>'rgba(20,184,166,0.12)', 'color'=>'#14B8A6', 'icon'=>'fa-exchange-alt'],
    'eRent'     => ['bg'=>'rgba(99,102,241,0.12)', 'color'=>'#6366F1', 'icon'=>'fa-home'],
    'eLaundry'  => ['bg'=>'rgba(6,182,212,0.12)',  'color'=>'#06B6D4', 'icon'=>'fa-tshirt'],
];
$defaultStyle = ['bg'=>'rgba(148,163,184,0.12)', 'color'=>'#94A3B8', 'icon'=>'fa-store'];
@endphp

@forelse($vendorsByModule as $moduleName => $moduleVendors)
@php
    $style  = $moduleColors[$moduleName] ?? $defaultStyle;
    $active = $moduleVendors->where('is_active', true)->count();
    $total  = $moduleVendors->count();
@endphp

<div class="card" style="margin-bottom:20px;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;"
         onclick="toggleSection('mod-{{ Str::slug($moduleName) }}', this)">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:{{ $style['bg'] }};color:{{ $style['color'] }};">
                <i class="fas {{ $style['icon'] }}"></i>
            </div>
            <span style="font-weight:800;font-size:15px;">{{ $moduleName }}</span>
            <span class="badge" style="margin-left:8px;background:{{ $style['color'] }};color:#fff;font-size:11px;">{{ $total }}</span>
            @if($active < $total)
            <span class="badge badge-warning" style="margin-left:4px;font-size:11px;">{{ $total - $active }} inactive</span>
            @endif
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <a href="{{ route('admin.vendors.index', ['module_id' => $moduleVendors->first()?->module_id]) }}"
               class="btn btn-outline btn-xs" onclick="event.stopPropagation()">
                <i class="fas fa-filter"></i> Filter
            </a>
            <i class="fas fa-chevron-down toggle-icon" style="color:var(--text-muted);transition:transform .25s;"></i>
        </div>
    </div>

    <div id="mod-{{ Str::slug($moduleName) }}">
        @include('admin.vendors._table', ['vendorList' => $moduleVendors, 'showCheckbox' => false])
    </div>
</div>
@empty
<div class="card"><div class="card-body"><div class="empty-state"><i class="fas fa-store"></i><h3>No vendors found</h3></div></div></div>
@endforelse

@endif

<script>
// ── Collapse/expand module sections ─────────────────────────────────────────
function toggleSection(id, headerEl) {
    const section = document.getElementById(id);
    const icon    = headerEl.querySelector('.toggle-icon');
    if (!section) return;
    const isOpen  = section.style.display !== 'none';
    section.style.display = isOpen ? 'none' : '';
    icon.style.transform  = isOpen ? 'rotate(-90deg)' : '';
}

// ── Bulk select (filtered view only) ────────────────────────────────────────
function toggleAll(master) {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = master.checked);
    updateBulkBar();
}
function updateBulkBar() {
    const checked = document.querySelectorAll('.row-check:checked');
    const toolbar = document.getElementById('bulk-toolbar');
    if (!toolbar) return;
    const countEl = document.getElementById('bulk-count');
    const master  = document.getElementById('check-all');
    const total   = document.querySelectorAll('.row-check').length;
    if (checked.length > 0) {
        toolbar.style.display = 'flex';
        countEl.textContent   = checked.length;
        master.indeterminate  = checked.length > 0 && checked.length < total;
        master.checked        = checked.length === total;
    } else {
        toolbar.style.display = 'none';
        master.indeterminate  = false;
        master.checked        = false;
    }
}
function clearSelection() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
    const master = document.getElementById('check-all');
    if (master) { master.checked = false; master.indeterminate = false; }
    const toolbar = document.getElementById('bulk-toolbar');
    if (toolbar) toolbar.style.display = 'none';
}
</script>
@endsection
