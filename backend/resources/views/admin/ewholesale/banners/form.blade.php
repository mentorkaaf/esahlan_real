@extends('admin.layouts.app')
@section('title', $banner ? 'Edit Banner' : 'New Banner')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px;max-width:680px">
<div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
    <a href="{{ route('admin.module-data.wholesale.banners') }}" style="color:#6b7280;text-decoration:none;font-size:13px">← Banners</a>
    <h2 style="margin:0;font-size:20px;font-weight:700;color:#1B1444">{{ $banner ? 'Edit Banner' : 'New Banner' }}</h2>
</div>

@if($errors->any())
<div style="background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:6px;margin-bottom:16px">
    <ul style="margin:0;padding-left:16px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="POST"
      action="{{ $banner ? route('admin.module-data.wholesale.banners.update', $banner) : route('admin.module-data.wholesale.banners.store') }}"
      enctype="multipart/form-data"
      style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:24px">
    @csrf
    @if($banner) @method('PUT') @endif

    {{-- Image Upload --}}
    <div style="margin-bottom:20px">
        <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">Banner Image</label>
        @if($banner?->image)
            <img src="/storage/{{ $banner->image }}" style="width:100%;max-height:180px;object-fit:cover;border-radius:6px;margin-bottom:8px" alt="Current">
            <p style="font-size:11px;color:#9ca3af;margin:0 0 6px">Upload a new image to replace the current one.</p>
        @endif
        <input type="file" name="image" accept="image/*"
               style="display:block;font-size:13px;color:#374151">
        <p style="font-size:11px;color:#9ca3af;margin:4px 0 0">Recommended: 1200×400px, max 4MB. JPG or PNG.</p>
    </div>

    {{-- Title --}}
    <div style="margin-bottom:16px">
        <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">Title <span style="color:#ef4444">*</span></label>
        <input type="text" name="title" value="{{ old('title', $banner?->title) }}" required
               style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px"
               placeholder="e.g. Ramadan Deals — Up to 40% Off">
    </div>

    {{-- Subtitle --}}
    <div style="margin-bottom:16px">
        <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">Subtitle</label>
        <input type="text" name="subtitle" value="{{ old('subtitle', $banner?->subtitle) }}"
               style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px"
               placeholder="Short description shown under the title">
    </div>

    {{-- CTA --}}
    <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;margin-bottom:16px">
        <div>
            <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">Button Label</label>
            <input type="text" name="cta_label" value="{{ old('cta_label', $banner?->cta_label) }}"
                   style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px"
                   placeholder="Shop Now">
        </div>
        <div>
            <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">Button URL / Deep Link</label>
            <input type="text" name="cta_url" value="{{ old('cta_url', $banner?->cta_url) }}"
                   style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px"
                   placeholder="/ewholesale/products?sort=deals">
        </div>
    </div>

    {{-- Sort Order --}}
    <div style="margin-bottom:16px">
        <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">Sort Order (lower = first)</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $banner?->sort_order ?? 0) }}" min="0"
               style="width:160px;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
    </div>

    {{-- Active toggle --}}
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" id="is_active"
               {{ old('is_active', $banner?->is_active ?? true) ? 'checked' : '' }}
               style="width:16px;height:16px;cursor:pointer">
        <label for="is_active" style="font-size:13px;font-weight:600;color:#374151;cursor:pointer">Active (show on home screen)</label>
    </div>

    <div style="display:flex;gap:10px">
        <button type="submit" style="padding:10px 24px;background:#F7941D;color:#fff;border:none;border-radius:7px;font-size:14px;font-weight:600;cursor:pointer">
            {{ $banner ? 'Save Changes' : 'Create Banner' }}
        </button>
        <a href="{{ route('admin.module-data.wholesale.banners') }}"
           style="padding:10px 20px;border:1px solid #d1d5db;border-radius:7px;font-size:14px;color:#374151;text-decoration:none">Cancel</a>
    </div>
</form>
</div>
@endsection
