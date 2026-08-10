@extends('admin.layouts.app')
@section('title', 'Global Categories')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">🗂 Global Categories</h1>
        <p class="page-subtitle">Manage product categories for the Global Store</p>
    </div>
    <button onclick="document.getElementById('add-modal').style.display='flex'"
        class="btn-primary" style="display:inline-flex;align-items:center;gap:8px">
        <i class="fas fa-plus"></i> Add Category
    </button>
</div>

@if(session('success'))
<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">
    ✅ {{ session('success') }}
</div>
@endif
@if(session('error'))
<div style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">
    ⚠️ {{ session('error') }}
</div>
@endif

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px">
    <div style="background:#fff;border-radius:12px;padding:16px 20px;border:1px solid #e5e7eb;text-align:center">
        <div style="font-size:28px;font-weight:900;color:#1A1A2E">{{ $categories->count() }}</div>
        <div style="font-size:11px;color:#6b7280;margin-top:2px">Total Categories</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:16px 20px;border:1px solid #e5e7eb;text-align:center">
        <div style="font-size:28px;font-weight:900;color:#059669">{{ $categories->where('is_active',true)->count() }}</div>
        <div style="font-size:11px;color:#6b7280;margin-top:2px">Active</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:16px 20px;border:1px solid #e5e7eb;text-align:center">
        <div style="font-size:28px;font-weight:900;color:#F59E0B">{{ $categories->sum('products_count') }}</div>
        <div style="font-size:11px;color:#6b7280;margin-top:2px">Total Products</div>
    </div>
</div>

{{-- Category Grid --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px">
    @forelse($categories as $cat)
    <div style="background:#fff;border-radius:16px;border:1px solid #e5e7eb;overflow:hidden;transition:box-shadow .2s"
         onmouseover="this.style.boxShadow='0 4px 20px rgba(0,0,0,0.08)'"
         onmouseout="this.style.boxShadow='none'">

        {{-- Header --}}
        <div style="background:linear-gradient(135deg,#1A1A2E,#16213E);padding:20px 18px;display:flex;align-items:center;gap:14px">
            <div style="width:50px;height:50px;border-radius:14px;background:rgba(245,158,11,0.15);
                        border:2px solid rgba(245,158,11,0.3);display:flex;align-items:center;
                        justify-content:center;font-size:24px;flex-shrink:0;overflow:hidden">
                @if($cat->image)
                    <img src="{{ $cat->image }}" alt="{{ $cat->name }}"
                         style="width:100%;height:100%;object-fit:cover">
                @else
                    {{ $cat->icon ?: '📦' }}
                @endif
            </div>
            <div style="flex:1;min-width:0">
                <div style="color:#fff;font-weight:800;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    {{ $cat->name }}
                </div>
                <div style="color:rgba(255,255,255,.45);font-size:11px;margin-top:2px">
                    Sort: {{ $cat->sort_order }} · {{ $cat->products_count }} products
                </div>
            </div>
            {{-- Status badge --}}
            <span style="padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;flex-shrink:0;
                          {{ $cat->is_active ? 'background:#d1fae5;color:#065f46' : 'background:#fee2e2;color:#991b1b' }}">
                {{ $cat->is_active ? 'Active' : 'Hidden' }}
            </span>
        </div>

        {{-- Actions --}}
        <div style="padding:14px 16px;display:flex;gap:8px;align-items:center">
            {{-- Edit --}}
            <button onclick="openEdit({{ $cat->id }}, '{{ addslashes($cat->name) }}', '{{ $cat->icon }}', {{ $cat->sort_order }}, {{ $cat->is_active ? 1 : 0 }})"
                style="flex:1;padding:8px;border-radius:8px;border:1px solid #e5e7eb;background:#f9fafb;
                       font-size:12px;font-weight:600;cursor:pointer;color:#374151">
                ✏️ Edit
            </button>

            {{-- Toggle active --}}
            <form method="POST" action="{{ route('admin.global.categories.toggle', $cat) }}" style="flex:1">
                @csrf @method('PATCH')
                <button type="submit"
                    style="width:100%;padding:8px;border-radius:8px;border:1px solid;font-size:12px;font-weight:600;cursor:pointer;
                           {{ $cat->is_active
                               ? 'border-color:#fca5a5;background:#fef2f2;color:#991b1b'
                               : 'border-color:#6ee7b7;background:#ecfdf5;color:#065f46' }}">
                    {{ $cat->is_active ? '🔴 Deactivate' : '🟢 Activate' }}
                </button>
            </form>

            {{-- Delete --}}
            <form method="POST" action="{{ route('admin.global.categories.destroy', $cat) }}"
                  onsubmit="return confirm('Delete category: {{ addslashes($cat->name) }}?')">
                @csrf @method('DELETE')
                <button type="submit"
                    style="padding:8px 12px;border-radius:8px;border:1px solid #fca5a5;
                           background:#fef2f2;color:#991b1b;font-size:12px;cursor:pointer">
                    🗑
                </button>
            </form>
        </div>
    </div>
    @empty
    <div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:#9ca3af">
        <div style="font-size:40px;margin-bottom:12px">📦</div>
        <div style="font-size:16px;font-weight:600;margin-bottom:6px">No categories yet</div>
        <div style="font-size:13px">Add your first product category to get started</div>
    </div>
    @endforelse
</div>


{{-- ══ ADD MODAL ════════════════════════════════════════════════════════════ --}}
<div id="add-modal" style="display:none;position:fixed;inset:0;z-index:1000;
     background:rgba(0,0,0,.5);align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:20px;padding:28px;width:100%;max-width:440px;margin:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3 style="font-size:17px;font-weight:800;color:#1A1A2E">Add Category</h3>
            <button onclick="document.getElementById('add-modal').style.display='none'"
                style="background:none;border:none;font-size:20px;cursor:pointer;color:#9ca3af">×</button>
        </div>
        <form method="POST" action="{{ route('admin.global.categories.store') }}" enctype="multipart/form-data">
            @csrf
            @include('admin.global.categories._form')
            <div style="display:flex;gap:10px;margin-top:20px">
                <button type="button" onclick="document.getElementById('add-modal').style.display='none'"
                    style="flex:1;padding:12px;border-radius:10px;border:1px solid #e5e7eb;
                           background:#f9fafb;font-weight:600;cursor:pointer">Cancel</button>
                <button type="submit"
                    style="flex:2;padding:12px;border-radius:10px;border:none;
                           background:#1A1A2E;color:#F59E0B;font-weight:800;cursor:pointer">
                    Create Category
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ══ EDIT MODAL ═══════════════════════════════════════════════════════════ --}}
<div id="edit-modal" style="display:none;position:fixed;inset:0;z-index:1000;
     background:rgba(0,0,0,.5);align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:20px;padding:28px;width:100%;max-width:440px;margin:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3 style="font-size:17px;font-weight:800;color:#1A1A2E">Edit Category</h3>
            <button onclick="document.getElementById('edit-modal').style.display='none'"
                style="background:none;border:none;font-size:20px;cursor:pointer;color:#9ca3af">×</button>
        </div>
        <form id="edit-form" method="POST" action="" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.global.categories._form', ['editing' => true])
            <div style="display:flex;gap:10px;margin-top:20px">
                <button type="button" onclick="document.getElementById('edit-modal').style.display='none'"
                    style="flex:1;padding:12px;border-radius:10px;border:1px solid #e5e7eb;
                           background:#f9fafb;font-weight:600;cursor:pointer">Cancel</button>
                <button type="submit"
                    style="flex:2;padding:12px;border-radius:10px;border:none;
                           background:#1A1A2E;color:#F59E0B;font-weight:800;cursor:pointer">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id, name, icon, sort, active) {
    var form = document.getElementById('edit-form');
    form.action = '/admin/global/categories/' + id;
    form.querySelector('[name=name]').value = name;
    form.querySelector('[name=icon]').value = icon || '';
    form.querySelector('[name=sort_order]').value = sort;
    form.querySelector('[name=is_active]').checked = active == 1;
    document.getElementById('edit-modal').style.display = 'flex';
}
</script>
@endsection
