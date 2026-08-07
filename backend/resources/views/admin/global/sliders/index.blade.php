@extends('admin.layouts.app')
@section('title', 'Global Sliders')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">🖼 Global Sliders</h1>
        <p class="page-subtitle">Manage homepage banner sliders</p>
    </div>
    <button onclick="document.getElementById('add-modal').style.display='flex'" style="padding:9px 18px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">+ Add Slider</button>
</div>

@if(session('success'))
<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">❌ {{ session('error') }}</div>
@endif

{{-- Sliders Grid --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
    @forelse($sliders as $slider)
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
        {{-- Preview --}}
        <div style="height:140px;background:{{ $slider->bg_color }};display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden">
            @if($slider->image_url)
            <img src="{{ $slider->image_url }}" style="width:100%;height:100%;object-fit:cover">
            @else
            <div style="text-align:center;color:rgba(255,255,255,.6)">
                <div style="font-size:36px;margin-bottom:4px">🖼</div>
                <div style="font-size:12px">No image</div>
            </div>
            @endif
            {{-- Active badge --}}
            <span style="position:absolute;top:10px;right:10px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:{{ $slider->is_active ? '#ecfdf5' : '#f3f4f6' }};color:{{ $slider->is_active ? '#059669' : '#6b7280' }}">
                {{ $slider->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>
        {{-- Info --}}
        <div style="padding:14px 16px">
            <div style="font-size:15px;font-weight:700;color:#111827;margin-bottom:2px">{{ $slider->title }}</div>
            @if($slider->subtitle)
            <div style="font-size:12px;color:#6b7280;margin-bottom:8px">{{ $slider->subtitle }}</div>
            @endif
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
                <div style="width:20px;height:20px;border-radius:4px;background:{{ $slider->bg_color }};border:1px solid #e5e7eb"></div>
                <span style="font-size:11px;color:#9ca3af">{{ $slider->bg_color }}</span>
                <span style="font-size:11px;color:#9ca3af;margin-left:auto">Sort: {{ $slider->sort_order }}</span>
            </div>
            <div style="display:flex;gap:8px">
                <button onclick="openEditModal({{ $slider->id }}, {{ json_encode($slider->title) }}, {{ json_encode($slider->subtitle) }}, {{ json_encode($slider->image_url) }}, {{ json_encode($slider->link_url) }}, {{ json_encode($slider->bg_color) }}, {{ json_encode($slider->button_text) }}, {{ $slider->sort_order }}, {{ $slider->is_active ? 'true' : 'false' }})"
                    style="flex:1;padding:7px;background:#eef2ff;color:#6366f1;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer">Edit</button>
                <form method="POST" action="{{ route('admin.global.sliders.destroy', $slider) }}" onsubmit="return confirm('Delete this slider?')">
                    @csrf @method('DELETE')
                    <button type="submit" style="padding:7px 12px;background:#fef2f2;color:#dc2626;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer">Delete</button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div style="grid-column:1/-1;padding:60px;text-align:center;color:#9ca3af;background:#fff;border-radius:12px;border:1px solid #e5e7eb">No sliders yet. Click "Add Slider" to create one.</div>
    @endforelse
</div>

{{-- Add Modal --}}
<div id="add-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:16px">
    <div style="background:#fff;border-radius:16px;padding:28px;width:500px;max-width:95vw;max-height:90vh;overflow-y:auto">
        <h3 style="margin:0 0 20px;font-size:16px;font-weight:700;color:#111827">Add Slider</h3>
        <form method="POST" action="{{ route('admin.global.sliders.store') }}" enctype="multipart/form-data">
            @csrf
            @include('admin.global.sliders._form', ['s' => null])
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
                <button type="button" onclick="document.getElementById('add-modal').style.display='none'" style="padding:9px 20px;background:#f3f4f6;color:#374151;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Cancel</button>
                <button type="submit" style="padding:9px 20px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Create Slider</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:16px">
    <div style="background:#fff;border-radius:16px;padding:28px;width:500px;max-width:95vw;max-height:90vh;overflow-y:auto">
        <h3 style="margin:0 0 20px;font-size:16px;font-weight:700;color:#111827">Edit Slider</h3>
        <form id="edit-form" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div style="margin-bottom:14px">
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Title *</label>
                <input id="e_title" name="title" required style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box">
            </div>
            <div style="margin-bottom:14px">
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Subtitle</label>
                <input id="e_subtitle" name="subtitle" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box">
            </div>
            <div style="margin-bottom:14px">
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Image URL</label>
                <input id="e_image_url" name="image_url" type="url" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box" placeholder="https://…">
            </div>
            <div style="margin-bottom:14px">
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Upload Image</label>
                <input name="image_file" type="file" accept="image/*" style="font-size:13px">
            </div>
            <div style="margin-bottom:14px">
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Link URL</label>
                <input id="e_link_url" name="link_url" type="url" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box" placeholder="https://…">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Background Color</label>
                    <input id="e_bg_color" name="bg_color" type="color" value="#1A1A2E" style="width:100%;height:38px;padding:2px;border:1px solid #d1d5db;border-radius:8px;cursor:pointer">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Button Text</label>
                    <input id="e_button_text" name="button_text" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Sort Order</label>
                    <input id="e_sort_order" name="sort_order" type="number" value="0" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box">
                </div>
                <div style="display:flex;align-items:flex-end;padding-bottom:2px">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600;color:#374151">
                        <input id="e_is_active" name="is_active" type="checkbox" value="1" style="width:16px;height:16px"> Active
                    </label>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
                <button type="button" onclick="document.getElementById('edit-modal').style.display='none'" style="padding:9px 20px;background:#f3f4f6;color:#374151;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Cancel</button>
                <button type="submit" style="padding:9px 20px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, title, subtitle, imageUrl, linkUrl, bgColor, buttonText, sortOrder, isActive) {
    document.getElementById('edit-form').action = '/admin/global/sliders/' + id;
    document.getElementById('e_title').value = title || '';
    document.getElementById('e_subtitle').value = subtitle || '';
    document.getElementById('e_image_url').value = imageUrl || '';
    document.getElementById('e_link_url').value = linkUrl || '';
    document.getElementById('e_bg_color').value = bgColor || '#1A1A2E';
    document.getElementById('e_button_text').value = buttonText || 'Shop Now';
    document.getElementById('e_sort_order').value = sortOrder || 0;
    document.getElementById('e_is_active').checked = isActive;
    document.getElementById('edit-modal').style.display = 'flex';
}
</script>
@endsection
