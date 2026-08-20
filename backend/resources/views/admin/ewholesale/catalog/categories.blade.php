@extends('admin.layouts.app')
@section('title', 'eWholesale — Categories')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <h2 style="margin:0;font-size:20px;font-weight:700;color:#1B1444">Category Tree</h2>
    <button onclick="togglePanel('addCat')" style="padding:8px 16px;background:#F7941D;color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer">+ Add Category</button>
</div>

@if(session('success'))<div style="background:#d1fae5;color:#065f46;padding:10px 16px;border-radius:6px;margin-bottom:16px">{{ session('success') }}</div>@endif

{{-- Add form --}}
<div id="addCat" style="display:none;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-bottom:20px">
    <form method="POST" action="{{ route('admin.module-data.wholesale.catalog.categories.store') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        @csrf
        <div>
            <label style="font-size:12px;font-weight:500;color:#374151;display:block;margin-bottom:4px">Name (EN)</label>
            <input name="name" required style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
        </div>
        <div>
            <label style="font-size:12px;font-weight:500;color:#374151;display:block;margin-bottom:4px">Name (SO)</label>
            <input name="name_so" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
        </div>
        <div>
            <label style="font-size:12px;font-weight:500;color:#374151;display:block;margin-bottom:4px">Parent</label>
            <select name="parent_id" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
                <option value="">Root</option>
                @foreach($cats->whereNull('parent_id') as $rc)
                <option value="{{ $rc->id }}">{{ $rc->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:12px;font-weight:500;color:#374151;display:block;margin-bottom:4px">Icon</label>
            <input name="icon" maxlength="10" placeholder="🛒" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;width:60px">
        </div>
        <div>
            <label style="font-size:12px;font-weight:500;color:#374151;display:block;margin-bottom:4px">Sort</label>
            <input name="sort_order" type="number" value="0" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;width:60px">
        </div>
        <button type="submit" style="padding:8px 20px;background:#1B1444;color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer">Create</button>
    </form>
</div>

{{-- Category tree --}}
@php $roots = $cats->whereNull('parent_id'); @endphp
@foreach($roots as $root)
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:12px;overflow:hidden">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#f9fafb;border-bottom:1px solid #e5e7eb">
        <div style="display:flex;align-items:center;gap:10px">
            <span style="font-size:20px">{{ $root->icon }}</span>
            <div>
                <div style="font-weight:600;color:#1B1444">{{ $root->name }}</div>
                @if($root->name_so) <div style="font-size:11px;color:#9ca3af">{{ $root->name_so }}</div> @endif
            </div>
            <span style="font-size:11px;color:#6b7280;background:#f3f4f6;padding:2px 8px;border-radius:10px">{{ $root->products_count }} products</span>
            @if(!$root->is_active) <span style="font-size:11px;color:#ef4444;background:#fee2e2;padding:2px 8px;border-radius:10px">Inactive</span> @endif
        </div>
        <button onclick="togglePanel('editCat-{{ $root->id }}')" style="padding:5px 12px;background:#1B1444;color:#fff;border:none;border-radius:5px;font-size:12px;cursor:pointer">Edit</button>
    </div>
    {{-- Edit form --}}
    <div id="editCat-{{ $root->id }}" style="display:none;padding:14px 16px;background:#fafafa;border-bottom:1px solid #e5e7eb">
        <form method="POST" action="{{ route('admin.module-data.wholesale.catalog.categories.update', $root) }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            @csrf @method('PATCH')
            <input name="name" value="{{ $root->name }}" required style="padding:7px 10px;border:1px solid #d1d5db;border-radius:5px;font-size:12px">
            <input name="name_so" value="{{ $root->name_so }}" placeholder="Somali name" style="padding:7px 10px;border:1px solid #d1d5db;border-radius:5px;font-size:12px">
            <input name="icon" value="{{ $root->icon }}" maxlength="10" style="padding:7px 10px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;width:60px">
            <input name="sort_order" type="number" value="{{ $root->sort_order }}" style="padding:7px 10px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;width:60px">
            <label style="font-size:12px;display:flex;align-items:center;gap:4px"><input type="checkbox" name="is_active" value="1" @checked($root->is_active)> Active</label>
            <button style="padding:7px 14px;background:#F7941D;color:#fff;border:none;border-radius:5px;font-size:12px;cursor:pointer">Save</button>
        </form>
    </div>
    {{-- Children --}}
    @foreach($cats->where('parent_id', $root->id) as $child)
    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px 10px 40px;border-bottom:1px solid #f3f4f6">
        <div style="display:flex;align-items:center;gap:8px">
            <span style="color:#9ca3af">└</span>
            <span style="font-size:16px">{{ $child->icon }}</span>
            <span style="color:#374151;font-size:13px">{{ $child->name }}</span>
            <span style="font-size:11px;color:#6b7280;background:#f3f4f6;padding:2px 8px;border-radius:10px">{{ $child->products_count }} products</span>
            @if(!$child->is_active) <span style="font-size:11px;color:#ef4444;background:#fee2e2;padding:2px 8px;border-radius:10px">Inactive</span> @endif
        </div>
        <button onclick="togglePanel('editCat-{{ $child->id }}')" style="padding:4px 10px;background:#6b7280;color:#fff;border:none;border-radius:5px;font-size:11px;cursor:pointer">Edit</button>
    </div>
    <div id="editCat-{{ $child->id }}" style="display:none;padding:12px 16px 12px 40px;background:#fafafa;border-bottom:1px solid #e5e7eb">
        <form method="POST" action="{{ route('admin.module-data.wholesale.catalog.categories.update', $child) }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            @csrf @method('PATCH')
            <input name="name" value="{{ $child->name }}" required style="padding:6px 10px;border:1px solid #d1d5db;border-radius:5px;font-size:12px">
            <input name="name_so" value="{{ $child->name_so }}" placeholder="Somali name" style="padding:6px 10px;border:1px solid #d1d5db;border-radius:5px;font-size:12px">
            <input name="icon" value="{{ $child->icon }}" maxlength="10" style="padding:6px 10px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;width:60px">
            <label style="font-size:12px;display:flex;align-items:center;gap:4px"><input type="checkbox" name="is_active" value="1" @checked($child->is_active)> Active</label>
            <button style="padding:6px 12px;background:#F7941D;color:#fff;border:none;border-radius:5px;font-size:12px;cursor:pointer">Save</button>
        </form>
    </div>
    @endforeach
</div>
@endforeach
</div>

<script>
function togglePanel(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? '' : 'none';
}
</script>
@endsection
