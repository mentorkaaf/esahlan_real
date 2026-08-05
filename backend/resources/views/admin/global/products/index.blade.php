@extends('admin.layouts.app')
@section('title', 'Global Products')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">🛍 Global Products</h1>
        <p class="page-subtitle">Manage your physical &amp; dropship product catalog</p>
    </div>
    <a href="{{ route('admin.global.products.create') }}" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px">
        <i class="fas fa-plus"></i> Add Product
    </a>
</div>

@if(session('success'))
<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">
    ✅ {{ session('success') }}
</div>
@endif

{{-- Filters --}}
<form method="GET" style="background:#fff;border-radius:12px;padding:16px 20px;margin-bottom:16px;border:1px solid #e5e7eb;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:2;min-width:180px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Search</label>
        <input name="search" value="{{ request('search') }}" placeholder="Name or SKU…" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
    </div>
    <div style="flex:1;min-width:140px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Category</label>
        <select name="category" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            <option value="">All</option>
            @foreach($categories as $cat)
            <option value="{{ $cat->id }}" {{ request('category')==$cat->id?'selected':'' }}>{{ $cat->name }}</option>
            @endforeach
        </select>
    </div>
    <div style="flex:1;min-width:120px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Type</label>
        <select name="type" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            <option value="">All</option>
            <option value="physical" {{ request('type')=='physical'?'selected':'' }}>Physical</option>
            <option value="dropship" {{ request('type')=='dropship'?'selected':'' }}>Dropship</option>
        </select>
    </div>
    <div style="flex:1;min-width:120px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Status</label>
        <select name="status" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            <option value="">All</option>
            <option value="active" {{ request('status')=='active'?'selected':'' }}>Active</option>
            <option value="inactive" {{ request('status')=='inactive'?'selected':'' }}>Inactive</option>
        </select>
    </div>
    <button type="submit" style="padding:8px 20px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Filter</button>
    @if(request()->hasAny(['search','category','type','status']))
    <a href="{{ route('admin.global.products.index') }}" style="padding:8px 16px;background:#f3f4f6;color:#374151;border-radius:8px;font-size:13px;text-decoration:none">Clear</a>
    @endif
</form>

{{-- Table --}}
<div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);border:1px solid #e5e7eb;overflow:hidden">
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
                <th style="padding:11px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Product</th>
                <th style="padding:11px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Category</th>
                <th style="padding:11px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Type</th>
                <th style="padding:11px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Price</th>
                <th style="padding:11px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Stock</th>
                <th style="padding:11px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Status</th>
                <th style="padding:11px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
            <tr style="border-top:1px solid #f3f4f6;transition:background .15s" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background=''">
                <td style="padding:12px 16px">
                    <div style="display:flex;align-items:center;gap:12px">
                        @if($product->thumbnail)
                        <img src="{{ $product->thumbnail }}" alt="" style="width:44px;height:44px;border-radius:8px;object-fit:cover;border:1px solid #e5e7eb">
                        @else
                        <div style="width:44px;height:44px;border-radius:8px;background:#f3f4f6;display:flex;align-items:center;justify-content:center">
                            <i class="fas fa-image" style="color:#d1d5db"></i>
                        </div>
                        @endif
                        <div>
                            <div style="font-size:13px;font-weight:600;color:#111">{{ Str::limit($product->name, 40) }}</div>
                            <div style="font-size:11px;color:#9ca3af">{{ $product->sku ?: '—' }}</div>
                        </div>
                    </div>
                </td>
                <td style="padding:12px 16px;font-size:12px;color:#374151">{{ $product->category?->name ?: '—' }}</td>
                <td style="padding:12px 16px;text-align:center">
                    <span style="padding:2px 10px;border-radius:20px;font-size:10px;font-weight:700;{{ $product->type==='dropship' ? 'color:#7c3aed;background:#f5f3ff' : 'color:#0369a1;background:#e0f2fe' }}">
                        {{ strtoupper($product->type) }}
                    </span>
                </td>
                <td style="padding:12px 16px;text-align:right;font-size:13px;font-weight:700;color:#111">
                    ${{ number_format($product->price, 2) }}
                    @if($product->compare_price > $product->price)
                    <div style="font-size:11px;color:#9ca3af;text-decoration:line-through">${{ number_format($product->compare_price,2) }}</div>
                    @endif
                </td>
                <td style="padding:12px 16px;text-align:center;font-size:13px;font-weight:600;color:{{ $product->stock <= 5 ? '#ef4444' : '#111' }}">
                    {{ $product->stock }}
                </td>
                <td style="padding:12px 16px;text-align:center">
                    <button onclick="toggleStatus({{ $product->id }}, this)"
                        data-active="{{ $product->is_active ? '1' : '0' }}"
                        style="padding:3px 12px;border-radius:20px;font-size:10px;font-weight:700;border:none;cursor:pointer;
                            {{ $product->is_active ? 'color:#065f46;background:#d1fae5' : 'color:#991b1b;background:#fee2e2' }}">
                        {{ $product->is_active ? 'ACTIVE' : 'INACTIVE' }}
                    </button>
                </td>
                <td style="padding:12px 16px;text-align:right">
                    <div style="display:flex;gap:6px;justify-content:flex-end">
                        <a href="{{ route('admin.global.products.edit', $product) }}" style="padding:5px 12px;background:#6366f1;color:#fff;border-radius:6px;font-size:11px;font-weight:600;text-decoration:none">Edit</a>
                        <form method="POST" action="{{ route('admin.global.products.destroy', $product) }}" onsubmit="return confirm('Delete this product?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="padding:5px 12px;background:#fee2e2;color:#dc2626;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="padding:48px;text-align:center;color:#9ca3af">
                    <div style="font-size:40px;margin-bottom:12px">📦</div>
                    <div style="font-size:15px;font-weight:600;margin-bottom:6px">No products yet</div>
                    <a href="{{ route('admin.global.products.create') }}" style="color:#6366f1">Add your first product →</a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    </div>
    @if($products->hasPages())
    <div style="padding:16px 20px;border-top:1px solid #f3f4f6">
        {{ $products->links() }}
    </div>
    @endif
</div>

<script>
function toggleStatus(id, btn) {
    const active = btn.dataset.active === '1';
    fetch(`/admin/global/products/${id}/toggle`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}
    })
    .then(r => r.json())
    .then(data => {
        btn.dataset.active = data.is_active ? '1' : '0';
        btn.textContent = data.is_active ? 'ACTIVE' : 'INACTIVE';
        btn.style.color = data.is_active ? '#065f46' : '#991b1b';
        btn.style.background = data.is_active ? '#d1fae5' : '#fee2e2';
    });
}
</script>
@endsection
