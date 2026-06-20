@extends('vendor.layouts.app')
@section('title', 'Add Product')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Add Product</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('vendor.dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('vendor.products.index') }}">Products</a></li>
            <li>Add</li>
        </ul>
    </div>
    <a href="{{ route('vendor.products.index') }}" class="btn btn-outline"><i class="fa-solid fa-arrow-left"></i> Back</a>
</div>

<form action="{{ route('vendor.products.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">
        <div>
            <div class="card">
                <div class="card-header">
                    <div class="card-header-title"><div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);"><i class="fa-solid fa-box"></i></div> Product Info</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Product Name <span style="color:var(--danger);">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required placeholder="Enter product name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Product description...">{{ old('description') }}</textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Price ($) <span style="color:var(--danger);">*</span></label>
                            <input type="number" name="price" class="form-control" value="{{ old('price') }}" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sale Price ($)</label>
                            <input type="number" name="sale_price" class="form-control" value="{{ old('sale_price') }}" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">SKU</label>
                            <input type="text" name="sku" class="form-control" value="{{ old('sku') }}" placeholder="e.g. PRD-001">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" name="stock_quantity" class="form-control" value="{{ old('stock_quantity') }}" min="0" placeholder="Leave empty for unlimited">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Time Availability Window --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-header-title"><div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);"><i class="fa-solid fa-clock"></i></div> Time Availability</div>
                </div>
                <div class="card-body">
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 12px">Set a time window if this item is only available during specific hours (e.g. breakfast 6AM–11AM). Leave empty for all-day availability.</p>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Available From</label>
                            <input type="time" name="available_from" class="form-control" value="{{ old('available_from') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Available Until</label>
                            <input type="time" name="available_until" class="form-control" value="{{ old('available_until') }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-header">
                    <div class="card-header-title"><div class="card-header-icon" style="background:rgba(16,185,129,0.1);color:var(--success);"><i class="fa-solid fa-image"></i></div> Thumbnail</div>
                </div>
                <div class="card-body">
                    <div id="thumb-preview" style="width:100%;height:160px;border-radius:10px;border:2px dashed var(--border);display:flex;align-items:center;justify-content:center;margin-bottom:12px;overflow:hidden;cursor:pointer;background:var(--bg);" onclick="document.getElementById('thumb-input').click()">
                        <div id="thumb-placeholder" style="text-align:center;color:var(--text-muted);">
                            <i class="fa-solid fa-cloud-arrow-up" style="font-size:28px;margin-bottom:6px;"></i>
                            <div style="font-size:13px;">Click to upload image</div>
                        </div>
                        <img id="thumb-img" src="" style="display:none;width:100%;height:100%;object-fit:cover;">
                    </div>
                    <input type="file" id="thumb-input" name="thumbnail" accept="image/*" style="display:none;" onchange="previewThumb(this)">
                    <button type="button" class="btn btn-outline btn-sm" style="width:100%;" onclick="document.getElementById('thumb-input').click()">
                        <i class="fa-solid fa-upload"></i> Choose Image
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-header-title"><div class="card-header-icon" style="background:rgba(139,92,246,0.1);color:var(--purple);"><i class="fa-solid fa-tag"></i></div> Category & Status</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-control">
                            <option value="">— Select Category —</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id')==$cat->id?'selected':'' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="toggle-wrap">
                        <label class="toggle">
                            <input type="checkbox" name="is_available" value="1" {{ old('is_available', '1') ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                        <span style="font-size:13.5px;font-weight:600;">Available for sale</span>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;padding:12px;font-size:15px;">
                <i class="fa-solid fa-floppy-disk"></i> Save Product
            </button>
        </div>
    </div>
</form>

@push('scripts')
<script>
function previewThumb(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('thumb-placeholder').style.display = 'none';
            document.getElementById('thumb-img').style.display = 'block';
            document.getElementById('thumb-img').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endpush
@endsection
