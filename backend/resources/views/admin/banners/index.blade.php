@extends('admin.layouts.app')
@section('title', 'Banners')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Banners</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li>Banners</li>
        </ul>
    </div>
    <button class="btn btn-primary" onclick="openModal('addBannerModal')">
        <i class="fas fa-plus"></i> Add Banner
    </button>
</div>

{{-- Banner Grid --}}
@php $bannerList = $banners ?? []; @endphp
@if(count($bannerList) > 0)
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:20px;">
    @foreach($bannerList as $banner)
    <div class="card" style="margin-bottom:0;overflow:hidden;">
        <div style="position:relative;aspect-ratio:16/7;background:#f0f2f8;overflow:hidden;">
            @if($banner->image)
                <img src="{{ asset('storage/'.$banner->image) }}" style="width:100%;height:100%;object-fit:cover;">
            @else
                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#c7cce0;">
                    <i class="fas fa-image" style="font-size:32px;"></i>
                </div>
            @endif
            <div style="position:absolute;top:8px;right:8px;">
                <span class="badge {{ $banner->is_active?'badge-success':'badge-secondary' }} badge-dot">
                    {{ $banner->is_active?'Active':'Hidden' }}
                </span>
            </div>
        </div>
        <div class="card-body" style="padding:14px;">
            <div style="font-weight:700;font-size:13px;margin-bottom:4px;">{{ $banner->title ?? 'Untitled Banner' }}</div>
            @if($banner->link)
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <i class="fas fa-link" style="margin-right:4px;"></i>{{ $banner->link }}
            </div>
            @endif
            <div style="display:flex;gap:6px;margin-top:10px;">
                <form action="{{ route('admin.banners.toggle',$banner->id) }}" method="POST" style="flex:1;">
                    @csrf
                    <button type="submit" class="btn btn-xs w-100 {{ $banner->is_active?'btn-outline':'btn-success' }}">
                        {{ $banner->is_active?'Hide':'Show' }}
                    </button>
                </form>
                <form action="{{ route('admin.banners.destroy',$banner->id) }}" method="POST"
                      onsubmit="return confirm('Delete this banner?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-xs" style="background:#fff5f5;color:var(--danger);border:1.5px solid #fecaca;">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
@else
<div class="card">
    <div class="empty-state">
        <i class="fas fa-image"></i>
        <h3>No banners yet</h3>
        <p>Add your first banner to display in the app</p>
        <button class="btn btn-primary" style="margin-top:12px;" onclick="openModal('addBannerModal')">
            <i class="fas fa-plus"></i> Add Banner
        </button>
    </div>
</div>
@endif

{{-- Add Banner Modal --}}
<div id="addBannerModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Add New Banner</div>
            <button class="modal-close" onclick="closeModal('addBannerModal')"><i class="fas fa-times"></i></button>
        </div>
        <form action="{{ route('admin.banners.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Banner Image <span style="color:var(--danger);">*</span></label>
                    <input type="file" name="image" class="form-control" accept="image/*" required>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Recommended: 1200×450px, JPG/PNG, max 2MB</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Title <span style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
                    <input type="text" name="title" class="form-control" placeholder="Banner title">
                </div>
                <div class="form-group">
                    <label class="form-label">Link Type <span style="color:var(--danger);">*</span></label>
                    <select name="link_type" class="form-control" id="bannerLinkType" onchange="updateLinkField(this.value)" required>
                        <option value="none">None — no link</option>
                        <option value="module">Module (open a module in app)</option>
                        <option value="vendor">Vendor (open a vendor page)</option>
                        <option value="url">External URL</option>
                    </select>
                </div>
                {{-- Module dropdown (shown when link_type = module) --}}
                <div class="form-group" id="moduleSelectGroup" style="display:none;">
                    <label class="form-label">Select Module</label>
                    <select name="link_value" class="form-control" id="moduleSelect">
                        <option value="">— Choose a module —</option>
                        @foreach($modules ?? [] as $mod)
                        <option value="{{ $mod->slug }}">{{ $mod->name }} ({{ $mod->slug }})</option>
                        @endforeach
                    </select>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                        Tapping the banner will open this module in the app.
                    </div>
                </div>

                {{-- Text input for vendor ID or URL --}}
                <div class="form-group" id="linkValueGroup" style="display:none;">
                    <label class="form-label" id="linkValueLabel">Link Value</label>
                    <input type="text" name="link_value" class="form-control" id="linkValueInput" placeholder="">
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;" id="linkValueHint"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Position / Screen <span style="color:var(--danger);">*</span></label>
                    <select name="position" class="form-control" required>
                        <option value="home_top">🏠 Home Page — Top Slider</option>
                        <option value="home_middle">🏠 Home Page — Middle Section</option>
                        <option value="module_top">📦 Module Page — Top (eFood, eShop…)</option>
                        <option value="popup">💬 Popup Banner</option>
                    </select>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                        Home Top/Middle = shown on home screen. Module Top = shown inside specific module pages.
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="0" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('addBannerModal')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload"></i> Upload Banner</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function updateLinkField(type) {
    const moduleGroup = document.getElementById('moduleSelectGroup');
    const textGroup   = document.getElementById('linkValueGroup');
    const label       = document.getElementById('linkValueLabel');
    const input       = document.getElementById('linkValueInput');
    const hint        = document.getElementById('linkValueHint');
    const moduleSelect = document.getElementById('moduleSelect');

    // Reset — disable all inputs that are hidden so they don't submit
    moduleGroup.style.display = 'none';
    textGroup.style.display   = 'none';
    moduleSelect.disabled     = true;
    input.disabled            = true;

    if (type === 'none') return;

    if (type === 'module') {
        moduleGroup.style.display = 'block';
        moduleSelect.disabled     = false;
        input.disabled            = true;
    } else if (type === 'vendor') {
        textGroup.style.display = 'block';
        input.disabled          = false;
        label.textContent       = 'Vendor ID';
        input.placeholder       = 'e.g. 12';
        hint.textContent        = 'Enter the numeric ID of the vendor to open their page.';
    } else if (type === 'url') {
        textGroup.style.display = 'block';
        input.disabled          = false;
        label.textContent       = 'External URL';
        input.placeholder       = 'https://example.com';
        hint.textContent        = 'Full URL including https://';
    }
}

// Init on page load in case of validation error re-open
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('bannerLinkType');
    if (sel) updateLinkField(sel.value);
});
</script>
@endpush
@endsection
