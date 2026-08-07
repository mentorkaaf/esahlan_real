@extends('admin.layouts.app')
@section('title', 'Edit Product')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Edit Product</h1>
        <p class="page-subtitle">{{ $product->name }}</p>
    </div>
    <a href="{{ route('admin.global.products.index') }}" style="display:inline-flex;align-items:center;gap:6px;color:#6b7280;text-decoration:none;font-size:13px">← Back</a>
</div>

@if(session('success'))
<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>
@endif
@if($errors->any())
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:12px 16px;margin-bottom:16px">
    @foreach($errors->all() as $e)<div style="font-size:13px;color:#dc2626">• {{ $e }}</div>@endforeach
</div>
@endif

<form method="POST" action="{{ route('admin.global.products.update', $product) }}" enctype="multipart/form-data">
@csrf @method('PUT')
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start">

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px">
        <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">Product Info</h3>
        <div style="display:flex;flex-direction:column;gap:14px">
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Name *</label>
                <input name="name" value="{{ old('name',$product->name) }}" required style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Short Description</label>
                <input name="short_description" value="{{ old('short_description',$product->short_description) }}" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Full Description</label>
                <textarea name="description" rows="5" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;resize:vertical">{{ old('description',$product->description) }}</textarea>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Tags</label>
                <input name="tags" value="{{ old('tags', is_array($product->tags) ? implode(', ',$product->tags) : $product->tags) }}" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
        </div>
    </div>

    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px">
        <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">Pricing &amp; Stock</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            @foreach([['price','Price (USD) *','number','0.01'],['compare_price','Compare Price','number','0.01'],['cost_price','Cost Price','number','0.01'],['stock','Stock Qty *','number','1'],['sku','SKU','text',null],['weight_kg','Weight (kg)','number','0.001']] as [$n,$l,$t,$s])
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">{{ $l }}</label>
                <input name="{{ $n }}" type="{{ $t }}" value="{{ old($n, $product->$n) }}" {{ $s ? "step=$s" : '' }} style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
            @endforeach
        </div>
    </div>

    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px">
        <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">Images</h3>
        <div>
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Thumbnail URL</label>
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Upload New Thumbnail</label>
            <input name="image_file" type="file" accept="image/*" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;margin-bottom:10px">
            @if($product->thumbnail)
            <div style="margin-bottom:8px"><img src="{{ $product->thumbnail }}" style="max-height:80px;border-radius:6px;border:1px solid #e5e7eb"></div>
            @endif
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Or Thumbnail URL</label>
            <input name="thumbnail" type="url" value="{{ old('thumbnail', $product->thumbnail) }}" placeholder="https://... (used if no file uploaded)" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;margin-bottom:12px">
        </div>
        <div id="imageInputs">
            @forelse($product->images as $img)
            <div style="display:flex;gap:8px;margin-bottom:6px">
                <input name="images[]" type="url" value="{{ $img->url }}" style="flex:1;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
                <button type="button" onclick="this.parentNode.remove()" style="padding:9px 12px;background:#fee2e2;color:#dc2626;border:none;border-radius:8px;cursor:pointer;font-size:12px">✕</button>
            </div>
            @empty
            <div style="display:flex;gap:8px;margin-bottom:6px">
                <input name="images[]" type="url" placeholder="Image URL" style="flex:1;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
            @endforelse
        </div>
        <button type="button" onclick="addImg()" style="margin-top:4px;font-size:12px;color:#6366f1;background:none;border:none;cursor:pointer">+ Add image</button>
    </div>
</div>

<div style="display:flex;flex-direction:column;gap:16px">
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px">
        <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">Organization</h3>
        <div style="display:flex;flex-direction:column;gap:12px">
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Category</label>
                <select name="category_id" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
                    <option value="">Uncategorized</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ old('category_id',$product->category_id)==$cat->id?'selected':'' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Type *</label>
                <select name="type" required style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px" onchange="toggleDropship(this.value)">
                    <option value="physical" {{ old('type',$product->type)==='physical'?'selected':'' }}>Physical</option>
                    <option value="dropship" {{ old('type',$product->type)==='dropship'?'selected':'' }}>Dropship</option>
                </select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Origin Country</label>
                <input name="origin_country" value="{{ old('origin_country',$product->origin_country) }}" maxlength="2" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
        </div>
    </div>

    <div id="dropshipFields" style="background:#f5f3ff;border-radius:12px;border:1px solid #ddd6fe;padding:24px;display:{{ old('type',$product->type)==='dropship'?'block':'none' }}">
        <h3 style="font-size:14px;font-weight:700;color:#7c3aed;margin-bottom:16px">🚚 Dropship Info</h3>
        <div style="display:flex;flex-direction:column;gap:10px">
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Supplier Name</label>
                <input name="supplier_name" value="{{ old('supplier_name',$product->supplier_name) }}" style="width:100%;padding:9px 12px;border:1px solid #ddd6fe;border-radius:8px;font-size:13px;background:#fff">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Supplier Product ID</label>
                <input name="supplier_product_id" value="{{ old('supplier_product_id',$product->supplier_product_id) }}" style="width:100%;padding:9px 12px;border:1px solid #ddd6fe;border-radius:8px;font-size:13px;background:#fff">
            </div>
        </div>
    </div>

    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px">
        <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">Badges</h3>
        @foreach(['is_active'=>'Active','is_featured'=>'Featured','is_new_arrival'=>'New Arrival','is_bestseller'=>'Bestseller','track_stock'=>'Track Stock'] as $field=>$label)
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:10px">
            <input type="checkbox" name="{{ $field }}" value="1" {{ old($field, $product->$field)?'checked':'' }} style="width:16px;height:16px;accent-color:#6366f1">
            <span style="font-size:13px;color:#374151">{{ $label }}</span>
        </label>
        @endforeach
    </div>

    <button type="submit" style="width:100%;padding:13px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer">
        Save Changes
    </button>
</div>

</div>
</form>

<script>
function toggleDropship(v){document.getElementById('dropshipFields').style.display=v==='dropship'?'block':'none'}
function addImg(){const d=document.getElementById('imageInputs');const div=document.createElement('div');div.style.cssText='display:flex;gap:8px;margin-bottom:6px';div.innerHTML=`<input name="images[]" type="url" placeholder="Image URL" style="flex:1;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"><button type="button" onclick="this.parentNode.remove()" style="padding:9px 12px;background:#fee2e2;color:#dc2626;border:none;border-radius:8px;cursor:pointer;font-size:12px">✕</button>`;d.appendChild(div)}
</script>
@endsection
