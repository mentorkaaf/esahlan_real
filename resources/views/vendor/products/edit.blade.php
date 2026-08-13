@extends('vendor.layouts.app')
@section('title', 'Edit Product')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Edit Product</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('vendor.dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('vendor.products.index') }}">Products</a></li>
            <li>Edit</li>
        </ul>
    </div>
    <div style="display:flex;gap:8px;">
        <form action="{{ route('vendor.products.destroy',$product) }}" method="POST" style="margin:0;" onsubmit="return confirm('Delete this product?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i> Delete</button>
        </form>
        <a href="{{ route('vendor.products.index') }}" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>
</div>

<form action="{{ route('vendor.products.update',$product) }}" method="POST" enctype="multipart/form-data">
    @csrf @method('PATCH')
    <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">
        <div>
            <div class="card">
                <div class="card-header">
                    <div class="card-header-title"><div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);"><i class="fa-solid fa-box"></i></div> Product Info</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Product Name <span style="color:var(--danger);">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name',$product->name) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4">{{ old('description',$product->description) }}</textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Price ($) <span style="color:var(--danger);">*</span></label>
                            <input type="number" name="price" class="form-control" value="{{ old('price',$product->price) }}" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sale Price ($)</label>
                            <input type="number" name="sale_price" class="form-control" value="{{ old('sale_price',$product->sale_price) }}" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" name="stock_quantity" class="form-control" value="{{ old('stock_quantity',$product->stock_quantity) }}" min="0" placeholder="Leave empty for unlimited">
                    </div>
                </div>
            </div>

            {{-- Time Availability Window --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-header-title"><div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);"><i class="fa-solid fa-clock"></i></div> Time Availability</div>
                </div>
                <div class="card-body">
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 12px">Set a time window if this item is only available during specific hours. Leave empty for all-day.</p>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Available From</label>
                            <input type="time" name="available_from" class="form-control" value="{{ old('available_from', $product->available_from ? substr($product->available_from, 0, 5) : '') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Available Until</label>
                            <input type="time" name="available_until" class="form-control" value="{{ old('available_until', $product->available_until ? substr($product->available_until, 0, 5) : '') }}">
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
                        @if($product->thumbnail)
                        <img id="thumb-img" src="{{ asset('storage/'.$product->thumbnail) }}" style="width:100%;height:100%;object-fit:cover;">
                        @else
                        <div id="thumb-placeholder" style="text-align:center;color:var(--text-muted);">
                            <i class="fa-solid fa-cloud-arrow-up" style="font-size:28px;margin-bottom:6px;"></i>
                            <div style="font-size:13px;">Click to upload</div>
                        </div>
                        <img id="thumb-img" src="" style="display:none;width:100%;height:100%;object-fit:cover;">
                        @endif
                    </div>
                    <input type="file" id="thumb-input" name="thumbnail" accept="image/*" style="display:none;" onchange="previewThumb(this)">
                    <button type="button" class="btn btn-outline btn-sm" style="width:100%;" onclick="document.getElementById('thumb-input').click()">
                        <i class="fa-solid fa-upload"></i> Change Image
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
                            <option value="{{ $cat->id }}" {{ old('category_id',$product->category_id)==$cat->id?'selected':'' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="toggle-wrap">
                        <label class="toggle">
                            <input type="checkbox" name="is_available" value="1" {{ old('is_available',$product->is_available) ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                        <span style="font-size:13.5px;font-weight:600;">Available for sale</span>
                    </div>
                </div>
            </div>

            @if($addons->isNotEmpty())
            <div class="card">
                <div class="card-header">
                    <div class="card-header-title"><div class="card-header-icon" style="background:rgba(236,72,153,0.1);color:#ec4899;"><i class="fa-solid fa-puzzle-piece"></i></div> Addons</div>
                </div>
                <div class="card-body" style="display:flex;flex-direction:column;gap:8px;">
                    @php $selectedAddonIds = $product->addons->pluck('id')->toArray(); @endphp
                    @foreach($addons as $a)
                    <label style="display:flex;align-items:center;gap:10px;padding:8px 10px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer">
                        <input type="checkbox" name="addon_ids[]" value="{{ $a->id }}" {{ in_array($a->id, $selectedAddonIds) ? 'checked' : '' }}>
                        @if($a->image)
                        <img src="{{ $a->image }}" style="width:32px;height:32px;object-fit:cover;border-radius:6px">
                        @endif
                        <span style="flex:1;font-weight:600;font-size:13px">{{ $a->name }}</span>
                        <span style="color:var(--success);font-weight:700;font-size:12px">+${{ number_format($a->price, 2) }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            @endif

            <button type="submit" class="btn btn-primary" style="width:100%;padding:12px;font-size:15px;">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
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
            const ph = document.getElementById('thumb-placeholder');
            if (ph) ph.style.display = 'none';
            const img = document.getElementById('thumb-img');
            img.style.display = 'block';
            img.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endpush
@endsection
