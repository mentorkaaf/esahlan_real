@extends('admin.layouts.app')
@section('title', 'Add Global Product')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Add Product</h1>
        <p class="page-subtitle">Add a new product to the Global Store catalog</p>
    </div>
    <a href="{{ route('admin.global.products.index') }}" style="display:inline-flex;align-items:center;gap:6px;color:#6b7280;text-decoration:none;font-size:13px">
        ← Back to Products
    </a>
</div>

@if($errors->any())
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:12px 16px;margin-bottom:16px">
    @foreach($errors->all() as $e)
    <div style="font-size:13px;color:#dc2626">• {{ $e }}</div>
    @endforeach
</div>
@endif

<form method="POST" action="{{ route('admin.global.products.store') }}">
@csrf
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start">

{{-- Left --}}
<div style="display:flex;flex-direction:column;gap:16px">
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px">
        <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">Product Info</h3>
        <div style="display:flex;flex-direction:column;gap:14px">
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Product Name *</label>
                <input name="name" value="{{ old('name') }}" required style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Short Description</label>
                <input name="short_description" value="{{ old('short_description') }}" placeholder="One-line summary" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Full Description</label>
                <textarea name="description" rows="5" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;resize:vertical">{{ old('description') }}</textarea>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Tags (comma separated)</label>
                <input name="tags" value="{{ old('tags') }}" placeholder="electronics, gadget, usa" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
        </div>
    </div>

    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px">
        <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">Pricing &amp; Stock</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Price (USD) *</label>
                <input name="price" type="number" step="0.01" value="{{ old('price') }}" required style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Compare Price</label>
                <input name="compare_price" type="number" step="0.01" value="{{ old('compare_price') }}" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Cost Price</label>
                <input name="cost_price" type="number" step="0.01" value="{{ old('cost_price') }}" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Stock Qty *</label>
                <input name="stock" type="number" value="{{ old('stock', 0) }}" required style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">SKU</label>
                <input name="sku" value="{{ old('sku') }}" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Weight (kg)</label>
                <input name="weight_kg" type="number" step="0.001" value="{{ old('weight_kg') }}" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
        </div>
    </div>

    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px">
        <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">Images</h3>
        <div>
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Thumbnail URL</label>
            <input name="thumbnail" type="url" value="{{ old('thumbnail') }}" placeholder="https://..." style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;margin-bottom:10px">
        </div>
        <div id="imageInputs">
            <div style="display:flex;gap:8px;margin-bottom:6px">
                <input name="images[]" type="url" placeholder="Image URL 1" style="flex:1;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
        </div>
        <button type="button" onclick="addImageField()" style="margin-top:4px;font-size:12px;color:#6366f1;background:none;border:none;cursor:pointer">+ Add another image</button>
    </div>
</div>

{{-- Right --}}
<div style="display:flex;flex-direction:column;gap:16px">
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px">
        <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">Organization</h3>
        <div style="display:flex;flex-direction:column;gap:12px">
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Category</label>
                <select name="category_id" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
                    <option value="">Uncategorized</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ old('category_id')==$cat->id?'selected':'' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Product Type *</label>
                <select name="type" required style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px" onchange="toggleDropship(this.value)">
                    <option value="physical" {{ old('type','physical')==='physical'?'selected':'' }}>Physical (own inventory)</option>
                    <option value="dropship" {{ old('type')==='dropship'?'selected':'' }}>Dropship</option>
                </select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Origin Country (ISO)</label>
                <input name="origin_country" value="{{ old('origin_country','US') }}" maxlength="2" placeholder="US" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
        </div>
    </div>

    <div id="dropshipFields" style="background:#f5f3ff;border-radius:12px;border:1px solid #ddd6fe;padding:24px;display:{{ old('type')==='dropship'?'block':'none' }}">
        <h3 style="font-size:14px;font-weight:700;color:#7c3aed;margin-bottom:16px">🚚 Dropship Info</h3>
        <div style="display:flex;flex-direction:column;gap:10px">
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Supplier Name</label>
                <input name="supplier_name" value="{{ old('supplier_name') }}" placeholder="CJDropshipping, AliExpress..." style="width:100%;padding:9px 12px;border:1px solid #ddd6fe;border-radius:8px;font-size:13px;background:#fff">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Supplier Product ID</label>
                <input name="supplier_product_id" value="{{ old('supplier_product_id') }}" placeholder="External product/SKU ID" style="width:100%;padding:9px 12px;border:1px solid #ddd6fe;border-radius:8px;font-size:13px;background:#fff">
            </div>
        </div>
    </div>

    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px">
        <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">Badges</h3>
        @foreach(['is_active'=>'Active (visible in store)','is_featured'=>'Featured','is_new_arrival'=>'New Arrival','is_bestseller'=>'Bestseller','track_stock'=>'Track Stock'] as $field=>$label)
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:10px">
            <input type="checkbox" name="{{ $field }}" value="1" {{ old($field, in_array($field,['is_active','track_stock']))?'checked':'' }} style="width:16px;height:16px;accent-color:#6366f1">
            <span style="font-size:13px;color:#374151">{{ $label }}</span>
        </label>
        @endforeach
    </div>

    <button type="submit" style="width:100%;padding:13px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer">
        Create Product
    </button>
</div>

</div>
</form>

<script>
function toggleDropship(val) {
    document.getElementById('dropshipFields').style.display = val === 'dropship' ? 'block' : 'none';
}
function addImageField() {
    const d = document.getElementById('imageInputs');
    const n = d.children.length + 1;
    const div = document.createElement('div');
    div.style.cssText = 'display:flex;gap:8px;margin-bottom:6px';
    div.innerHTML = `<input name="images[]" type="url" placeholder="Image URL ${n}" style="flex:1;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
        <button type="button" onclick="this.parentNode.remove()" style="padding:9px 12px;background:#fee2e2;color:#dc2626;border:none;border-radius:8px;cursor:pointer;font-size:12px">✕</button>`;
    d.appendChild(div);
}
</script>
@endsection
