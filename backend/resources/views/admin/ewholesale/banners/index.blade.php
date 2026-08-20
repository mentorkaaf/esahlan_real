@extends('admin.layouts.app')
@section('title', 'eWholesale — Banners')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <h2 style="margin:0;font-size:20px;font-weight:700;color:#1B1444">Banners</h2>
    <a href="{{ route('admin.module-data.wholesale.banners.create') }}"
       style="padding:9px 18px;background:#F7941D;color:#fff;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none">+ Add Banner</a>
</div>

@if(session('success'))<div style="background:#d1fae5;color:#065f46;padding:10px 16px;border-radius:6px;margin-bottom:16px">{{ session('success') }}</div>@endif

@if($banners->isEmpty())
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:48px;text-align:center">
    <div style="font-size:40px;margin-bottom:12px">🖼️</div>
    <p style="color:#6b7280;margin:0">No banners yet. Add your first banner to showcase on the eWholesale home screen.</p>
    <a href="{{ route('admin.module-data.wholesale.banners.create') }}" style="display:inline-block;margin-top:16px;padding:9px 20px;background:#F7941D;color:#fff;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none">+ Add First Banner</a>
</div>
@else
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
@foreach($banners as $banner)
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;position:relative">
    {{-- Image --}}
    <div style="height:140px;background:{{ $banner->bg_color ?: '#1B1444' }};display:flex;align-items:center;justify-content:center;position:relative">
        @if($banner->image)
            <img src="/storage/{{ $banner->image }}" style="width:100%;height:100%;object-fit:cover" alt="{{ $banner->title }}">
        @else
            <span style="color:rgba(255,255,255,.4);font-size:36px">🖼️</span>
        @endif
        {{-- Active badge --}}
        <span style="position:absolute;top:8px;right:8px;padding:3px 8px;border-radius:9999px;font-size:11px;font-weight:600;
            background:{{ $banner->is_active ? '#d1fae5' : '#f3f4f6' }};
            color:{{ $banner->is_active ? '#065f46' : '#6b7280' }}">
            {{ $banner->is_active ? 'Active' : 'Inactive' }}
        </span>
        {{-- Sort order --}}
        <span style="position:absolute;top:8px;left:8px;padding:3px 8px;border-radius:9999px;font-size:11px;font-weight:600;background:rgba(0,0,0,.5);color:#fff">#{{ $banner->sort_order }}</span>
    </div>

    <div style="padding:14px">
        <div style="font-weight:700;font-size:14px;color:#1B1444;margin-bottom:4px">{{ $banner->title }}</div>
        @if($banner->subtitle)
            <div style="font-size:12px;color:#6b7280;margin-bottom:8px">{{ Str::limit($banner->subtitle, 60) }}</div>
        @endif
        @if($banner->cta_label)
            <div style="font-size:11px;color:#F7941D">CTA: {{ $banner->cta_label }} → {{ $banner->cta_url }}</div>
        @endif

        <div style="display:flex;gap:8px;margin-top:12px">
            <a href="{{ route('admin.module-data.wholesale.banners.edit', $banner) }}"
               style="flex:1;padding:6px;text-align:center;border:1px solid #d1d5db;border-radius:5px;font-size:12px;text-decoration:none;color:#374151">✏️ Edit</a>

            <form method="POST" action="{{ route('admin.module-data.wholesale.banners.toggle', $banner) }}" style="flex:1">
                @csrf
                <button style="width:100%;padding:6px;border:1px solid {{ $banner->is_active ? '#f59e0b' : '#10b981' }};border-radius:5px;font-size:12px;background:transparent;cursor:pointer;color:{{ $banner->is_active ? '#f59e0b' : '#10b981' }}">
                    {{ $banner->is_active ? '⏸ Disable' : '▶ Enable' }}
                </button>
            </form>

            <form method="POST" action="{{ route('admin.module-data.wholesale.banners.delete', $banner) }}"
                  onsubmit="return confirm('Delete this banner?')" style="flex:0">
                @csrf @method('DELETE')
                <button style="padding:6px 10px;border:1px solid #ef4444;border-radius:5px;font-size:12px;background:transparent;cursor:pointer;color:#ef4444">🗑</button>
            </form>
        </div>
    </div>
</div>
@endforeach
</div>
@endif
</div>
@endsection
