@extends('admin.layouts.app')
@section('title', isset($ad) ? 'Edit eSpace Ad' : 'Create eSpace Ad')
@section('content')

@php
$isEdit = isset($ad);
$action = $isEdit ? route('admin.espace-ads.update', $ad) : route('admin.espace-ads.store');
$moduleColors = ['efood'=>'#FF6B35','egrocery'=>'#22C55E','eshop'=>'#8B5CF6','eparcel'=>'#F59E0B','emoving'=>'#3B82F6','elearning'=>'#06B6D4','eexchange'=>'#EC4899','erent'=>'#14B8A6'];
$moduleIcons  = ['efood'=>'🍕','egrocery'=>'🛒','eshop'=>'🛍️','eparcel'=>'📦','emoving'=>'🚛','elearning'=>'🎓','eexchange'=>'💱','erent'=>'🏠'];
@endphp

<style>
.form-card { background:#fff; border-radius:16px; padding:28px; box-shadow:0 2px 12px rgba(0,0,0,.07); margin-bottom:20px; }
.form-card h6 { font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:#FF8A00; margin-bottom:16px; border-bottom:2px solid #FFF0E0; padding-bottom:8px; }
.form-group { margin-bottom:16px; }
.form-group label { display:block; font-size:12px; font-weight:700; color:#374151; margin-bottom:5px; letter-spacing:.3px; }
.form-control { width:100%; padding:10px 14px; border:1.5px solid #E5E7EB; border-radius:10px; font-size:13px; outline:none; transition:border-color .2s; box-sizing:border-box; }
.form-control:focus { border-color:#FF8A00; }
.module-picker { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
.module-opt { position:relative; cursor:pointer; }
.module-opt input { position:absolute; opacity:0; }
.module-opt .card { border-radius:12px; padding:14px 10px; text-align:center; border:2px solid #E5E7EB; transition:all .2s; }
.module-opt input:checked + .card { border-color:var(--mc); box-shadow:0 0 0 3px var(--mc)22; }
.module-opt .card .emoji { font-size:24px; }
.module-opt .card .name { font-size:12px; font-weight:700; margin-top:6px; }
.placement-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; }
.placement-opt { position:relative; cursor:pointer; }
.placement-opt input { position:absolute; opacity:0; }
.placement-opt .lbl { display:flex; align-items:center; justify-content:center; gap:6px; padding:10px; border-radius:10px; border:2px solid #E5E7EB; font-size:12px; font-weight:700; transition:all .2s; }
.placement-opt input:checked + .lbl { border-color:#FF8A00; background:#FFF8F0; color:#FF8A00; }
.preview-card { border-radius:20px; overflow:hidden; max-width:340px; margin:0 auto; }
.priority-slider { -webkit-appearance:none; height:6px; border-radius:3px; background:#E5E7EB; outline:none; }
.priority-slider::-webkit-slider-thumb { -webkit-appearance:none; width:18px; height:18px; border-radius:50%; background:#FF8A00; cursor:pointer; }
</style>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-{{ $isEdit ? 'edit' : 'plus-circle' }}" style="color:#FF8A00"></i> {{ $isEdit ? 'Edit' : 'Create' }} eSpace Ad</h2>
        <ol class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('admin.espace-ads.index') }}">eSpace Ads</a></li>
            <li>{{ $isEdit ? 'Edit' : 'Create' }}</li>
        </ol>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger">
    <ul style="margin:0;padding-left:16px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if($isEdit) @method('PUT') @endif

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

<div>
{{-- Module Picker --}}
<div class="form-card">
    <h6><i class="fas fa-th"></i> Select Module</h6>
    <div class="module-picker">
        @foreach($modules as $key => $m)
        <label class="module-opt">
            <input type="radio" name="module" value="{{ $key }}"
                {{ ($isEdit && $ad->module === $key) || (!$isEdit && old('module') === $key) ? 'checked' : '' }}
                onchange="updatePreview()">
            <div class="card" style="--mc:{{ $m['color'] }}">
                <div class="emoji">{{ ['efood'=>'🍕','egrocery'=>'🛒','eshop'=>'🛍️','eparcel'=>'📦','emoving'=>'🚛','elearning'=>'🎓','eexchange'=>'💱','erent'=>'🏠'][$key] }}</div>
                <div class="name" style="color:{{ $m['color'] }}">{{ $m['label'] }}</div>
            </div>
        </label>
        @endforeach
    </div>
</div>

{{-- Ad Content --}}
<div class="form-card">
    <h6><i class="fas fa-pen"></i> Ad Content</h6>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-group" style="grid-column:1/-1;">
            <label>Title *</label>
            <input type="text" name="title" class="form-control" maxlength="100" placeholder="e.g. Order Delicious Food" value="{{ old('title', $ad->title ?? '') }}" oninput="updatePreview()" required>
        </div>
        <div class="form-group" style="grid-column:1/-1;">
            <label>Subtitle</label>
            <input type="text" name="subtitle" class="form-control" maxlength="200" placeholder="e.g. 50+ restaurants in your city" value="{{ old('subtitle', $ad->subtitle ?? '') }}" oninput="updatePreview()">
        </div>
        <div class="form-group" style="grid-column:1/-1;">
            <label>Description <span style="color:#8A8A9A;font-weight:400;">(optional — shown below card)</span></label>
            <textarea name="description" class="form-control" rows="2" maxlength="500" placeholder="Short promo text">{{ old('description', $ad->description ?? '') }}</textarea>
        </div>
        <div class="form-group">
            <label>CTA Button Text *</label>
            <input type="text" name="cta_text" class="form-control" maxlength="50" placeholder="Order Now" value="{{ old('cta_text', $ad->cta_text ?? 'Explore Now') }}" oninput="updatePreview()" required>
        </div>
        <div class="form-group">
            <label>Deep Link *</label>
            <input type="text" name="deep_link" class="form-control" maxlength="100" placeholder="/efood" value="{{ old('deep_link', $ad->deep_link ?? '') }}" required>
            <small style="color:#8A8A9A;font-size:11px;">App route to open on click</small>
        </div>
    </div>
</div>

{{-- Image --}}
<div class="form-card">
    <h6><i class="fas fa-image"></i> Ad Image <span style="font-weight:400;color:#8A8A9A;">(optional — auto-uses module gradient if empty)</span></h6>
    @if($isEdit && $ad->image_url)
        <img src="{{ $ad->image_url }}" style="height:80px;border-radius:10px;margin-bottom:10px;object-fit:cover;">
        <div style="font-size:11px;color:#8A8A9A;margin-bottom:8px;">Upload new to replace</div>
    @endif
    <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(this)">
    <small style="color:#8A8A9A;font-size:11px;">Max 3MB · JPG/PNG/WebP · Recommended: 800×400px</small>
</div>

{{-- Placement & Settings --}}
<div class="form-card">
    <h6><i class="fas fa-map-marker-alt"></i> Placement</h6>
    <div class="placement-grid">
        @foreach($placements as $p)
        @php $icons = ['feed'=>'📰','reels'=>'🎬','comments'=>'💬','podcast'=>'🎙️']; @endphp
        <label class="placement-opt">
            <input type="checkbox" name="placement[]" value="{{ $p }}"
                {{ ($isEdit && str_contains($ad->placement, $p)) || (!$isEdit && in_array($p, (array)old('placement', ['feed']))) ? 'checked' : '' }}>
            <div class="lbl">{{ $icons[$p] }} {{ ucfirst($p) }}</div>
        </label>
        @endforeach
    </div>

    <div style="margin-top:20px;">
        <label style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">
            Priority: <span id="priVal">{{ old('priority', $ad->priority ?? 5) }}</span>/10
        </label>
        <input type="range" name="priority" class="priority-slider" min="1" max="10" value="{{ old('priority', $ad->priority ?? 5) }}" style="width:100%"
            oninput="document.getElementById('priVal').textContent=this.value">
        <div style="display:flex;justify-content:space-between;font-size:10px;color:#8A8A9A;margin-top:2px;"><span>Low</span><span>High</span></div>
    </div>

    <div style="margin-top:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
            <label>Start Date</label>
            <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', $ad->starts_at?->format('Y-m-d\TH:i') ?? '') }}">
        </div>
        <div class="form-group">
            <label>End Date</label>
            <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', $ad->ends_at?->format('Y-m-d\TH:i') ?? '') }}">
        </div>
    </div>

    <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" id="isActive"
            {{ old('is_active', $ad->is_active ?? true) ? 'checked' : '' }}
            style="width:16px;height:16px;accent-color:#FF8A00;">
        <label for="isActive" style="font-size:13px;font-weight:600;cursor:pointer;margin:0;">Active (show to users immediately)</label>
    </div>
</div>

<div style="display:flex;gap:12px;">
    <button type="submit" class="btn" style="background:#FF8A00;color:#fff;border-radius:12px;font-weight:700;padding:12px 28px;font-size:14px;border:none;cursor:pointer;flex:1;">
        <i class="fas fa-{{ $isEdit ? 'save' : 'plus' }}"></i> {{ $isEdit ? 'Save Changes' : 'Create Ad' }}
    </button>
    <a href="{{ route('admin.espace-ads.index') }}" class="btn" style="background:#F3F4F6;color:#374151;border-radius:12px;font-weight:600;padding:12px 20px;text-decoration:none;">
        Cancel
    </a>
</div>
</div>

{{-- Live Preview --}}
<div style="position:sticky;top:20px;">
    <div class="form-card" style="padding:20px;">
        <h6><i class="fas fa-eye"></i> Live Preview</h6>
        <div id="adPreview" class="preview-card" style="box-shadow:0 8px 32px rgba(0,0,0,.15);">
            <!-- preview injected by JS -->
        </div>
        <div style="margin-top:10px;text-align:center;font-size:11px;color:#8A8A9A;">Preview as it appears in the feed</div>
    </div>
</div>

</div>
</form>

<script>
const MODULE_META = @json($modules);
const MODULE_ICONS = {efood:'🍕',egrocery:'🛒',eshop:'🛍️',eparcel:'📦',emoving:'🚛',elearning:'🎓',eexchange:'💱',erent:'🏠'};

function updatePreview() {
    const mod   = document.querySelector('input[name=module]:checked')?.value || 'efood';
    const meta  = MODULE_META[mod] || {color:'#FF8A00',label:'eModule'};
    const title = document.querySelector('[name=title]')?.value || 'Module Title';
    const sub   = document.querySelector('[name=subtitle]')?.value || 'Short description here';
    const cta   = document.querySelector('[name=cta_text]')?.value || 'Explore Now';
    const icon  = MODULE_ICONS[mod] || '📢';
    const c     = meta.color;

    document.getElementById('adPreview').innerHTML = `
    <div style="background:linear-gradient(145deg,${c}ff,${c}bb);padding:22px 20px 20px;position:relative;overflow:hidden;">
        <div style="position:absolute;right:-10px;top:-10px;font-size:80px;opacity:.15;transform:rotate(15deg)">${icon}</div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
            <div style="width:32px;height:32px;background:rgba(255,255,255,.25);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;">${icon}</div>
            <div>
                <div style="color:rgba(255,255,255,.7);font-size:9px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;">eSahlan Services</div>
                <div style="color:#fff;font-size:11px;font-weight:700;">${meta.label}</div>
            </div>
            <div style="margin-left:auto;color:rgba(255,255,255,.5);font-size:16px;cursor:pointer;">×</div>
        </div>
        <div style="color:#fff;font-size:19px;font-weight:900;line-height:1.2;margin-bottom:6px;">${title}</div>
        <div style="color:rgba(255,255,255,.8);font-size:12px;margin-bottom:18px;line-height:1.4;">${sub}</div>
        <button style="background:#fff;color:${c};border:none;border-radius:25px;padding:9px 22px;font-size:13px;font-weight:800;cursor:pointer;letter-spacing:.2px;">
            ${cta} →
        </button>
    </div>
    <div style="background:#fff;padding:10px 14px;display:flex;align-items:center;gap:6px;">
        <div style="width:6px;height:6px;border-radius:50%;background:${c}"></div>
        <span style="font-size:10px;color:#8A8A9A;font-weight:600;text-transform:uppercase;letter-spacing:.4px;">Sponsored · eSahlan</span>
    </div>`;
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(input.files[0]);
        img.style = 'width:100%;height:120px;object-fit:cover;border-radius:10px;margin-top:8px;';
        const prev = document.getElementById('adPreview');
        prev.insertBefore(img, prev.firstChild);
    }
}

// Init on load
updatePreview();
</script>
@endsection
