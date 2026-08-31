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

@php
$moduleColors = [
    'eFood'     => ['color'=>'#FF8A00', 'icon'=>'fa-utensils'],
    'eGrocery'  => ['color'=>'#10B981', 'icon'=>'fa-shopping-basket'],
    'eShop'     => ['color'=>'#3B82F6', 'icon'=>'fa-shopping-bag'],
    'eParcel'   => ['color'=>'#8B5CF6', 'icon'=>'fa-box'],
    'eMoving'   => ['color'=>'#EC4899', 'icon'=>'fa-truck-moving'],
    'eLearning' => ['color'=>'#F59E0B', 'icon'=>'fa-graduation-cap'],
    'eExchange' => ['color'=>'#14B8A6', 'icon'=>'fa-exchange-alt'],
    'eRent'     => ['color'=>'#6366F1', 'icon'=>'fa-home'],
    'eLaundry'  => ['color'=>'#06B6D4', 'icon'=>'fa-tshirt'],
];
$defaultStyle = ['color'=>'#94A3B8', 'icon'=>'fa-store'];
@endphp

@if($isFiltering)
{{-- ── FILTERED: flat table ── --}}
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
            <a href="{{ route('admin.vendors.index') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
        </form>
    </div>
</div>

<form id="bulk-form" method="POST" action="{{ route('admin.vendors.bulk-destroy') }}">
    @csrf @method('DELETE')
    <div id="bulk-toolbar" style="display:none;align-items:center;gap:10px;padding:10px 16px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:12px;margin-bottom:12px;">
        <span id="bulk-count" style="font-weight:700;color:#ef4444;font-size:13px;"></span>
        <span style="color:var(--text-muted);font-size:13px;">vendors selected</span>
        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete selected vendors?')"><i class="fas fa-trash me-1"></i> Delete Selected</button>
        <button type="button" class="btn btn-outline btn-sm" onclick="clearSelection()">Cancel</button>
    </div>
    <div class="card">
        <div class="card-header">
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
{{-- ── GROUPED: tabs ── --}}

{{-- Search bar (above tabs) --}}
<div class="card" style="margin-bottom:0;border-bottom-left-radius:0;border-bottom-right-radius:0;border-bottom:none;">
    <div class="filter-bar" style="padding:12px 16px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;align-items:center;">
            <div style="flex:1;min-width:200px;">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search vendors…" value="">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <select name="status" class="form-control" style="width:140px;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </form>
    </div>
</div>

{{-- Tab strip --}}
<div style="background:var(--surface);border-left:1px solid var(--border);border-right:1px solid var(--border);overflow-x:auto;white-space:nowrap;-webkit-overflow-scrolling:touch;">
    <div style="display:inline-flex;padding:0 16px;gap:2px;min-width:100%;">
        @foreach($vendorsByModule as $moduleName => $moduleVendors)
        @php $s = $moduleColors[$moduleName] ?? $defaultStyle; $slug = Str::slug($moduleName); @endphp
        <button class="vendor-tab" data-target="tab-{{ $slug }}" onclick="switchTab('{{ $slug }}')"
                style="display:inline-flex;align-items:center;gap:7px;padding:14px 18px;border:none;background:transparent;
                       border-bottom:3px solid transparent;cursor:pointer;font-weight:700;font-size:13px;
                       color:var(--text-muted);white-space:nowrap;transition:color .18s,border-color .18s;">
            <i class="fas {{ $s['icon'] }}" style="font-size:13px;"></i>
            {{ $moduleName }}
            <span class="tab-badge" style="background:var(--border);color:var(--text-muted);
                  padding:2px 7px;border-radius:10px;font-size:11px;font-weight:800;
                  transition:background .18s,color .18s;">{{ $moduleVendors->count() }}</span>
        </button>
        @endforeach
    </div>
</div>

{{-- Tab panels --}}
<div style="border:1px solid var(--border);border-top:none;border-radius:0 0 12px 12px;overflow:hidden;background:var(--surface);">
    @foreach($vendorsByModule as $moduleName => $moduleVendors)
    @php $slug = Str::slug($moduleName); @endphp
    <div id="tab-{{ $slug }}" class="tab-panel" style="display:none;">
        @include('admin.vendors._table', ['vendorList' => $moduleVendors, 'showCheckbox' => false])
    </div>
    @endforeach
</div>

@endif

<script>
// ── Tab switching ────────────────────────────────────────────────────────────
const MODULE_COLORS = @json($moduleColors ?? []);
const DEFAULT_COLOR = '#94A3B8';

function switchTab(slug) {
    // Hide all panels
    document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
    // Deactivate all tabs
    document.querySelectorAll('.vendor-tab').forEach(btn => {
        btn.style.borderBottomColor = 'transparent';
        btn.style.color = 'var(--text-muted)';
        const badge = btn.querySelector('.tab-badge');
        if (badge) { badge.style.background = 'var(--border)'; badge.style.color = 'var(--text-muted)'; }
    });
    // Show target
    const panel = document.getElementById('tab-' + slug);
    if (panel) panel.style.display = '';
    // Activate tab
    const activeBtn = document.querySelector('[data-target="tab-' + slug + '"]');
    if (activeBtn) {
        const name  = activeBtn.textContent.trim().split('\n')[0].trim();
        // Find color
        let color = DEFAULT_COLOR;
        for (const [k, v] of Object.entries(MODULE_COLORS)) {
            if (k.toLowerCase() === name.toLowerCase()) { color = v.color; break; }
        }
        activeBtn.style.borderBottomColor = color;
        activeBtn.style.color = color;
        const badge = activeBtn.querySelector('.tab-badge');
        if (badge) { badge.style.background = color; badge.style.color = '#fff'; }
    }
    // Persist
    try { localStorage.setItem('vendors_tab', slug); } catch(e) {}
}

// Auto-open first tab (or saved)
document.addEventListener('DOMContentLoaded', () => {
    @if(!$isFiltering && $vendorsByModule && $vendorsByModule->count())
    let saved = null;
    try { saved = localStorage.getItem('vendors_tab'); } catch(e) {}
    const firstSlug = '{{ Str::slug($vendorsByModule->keys()->first()) }}';
    switchTab(saved || firstSlug);
    @endif
});

// ── Bulk select (filtered view only) ────────────────────────────────────────
function toggleAll(master) {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = master.checked);
    updateBulkBar();
}
function updateBulkBar() {
    const checked = document.querySelectorAll('.row-check:checked');
    const toolbar = document.getElementById('bulk-toolbar');
    if (!toolbar) return;
    const total = document.querySelectorAll('.row-check').length;
    const master = document.getElementById('check-all');
    if (checked.length > 0) {
        toolbar.style.display = 'flex';
        document.getElementById('bulk-count').textContent = checked.length;
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
    const m = document.getElementById('check-all');
    if (m) { m.checked = false; m.indeterminate = false; }
    const t = document.getElementById('bulk-toolbar');
    if (t) t.style.display = 'none';
}
</script>
@endsection
