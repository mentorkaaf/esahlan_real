@extends('admin.layouts.app')
@section('title', isset($product) ? 'Edit Product' : 'Add Product')
@section('content')
@php $editing = isset($product); @endphp
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-{{ $editing ? 'pen' : 'plus' }}" style="color:#FF8A00"></i> {{ $editing ? 'Edit: '.$product->name : 'Add Product' }}</h2>
        <ol class="breadcrumb"><li><a href="{{ route('admin.module-data.egrocery.products') }}">Products</a></li><li>{{ $editing?'Edit':'Create' }}</li></ol>
    </div>
    <a href="{{ route('admin.module-data.egrocery.products') }}" class="btn" style="background:#f1f5f9;color:#374151;"><i class="fas fa-arrow-left"></i> Back</a>
</div>

@include('admin.egrocery._subnav')

@if($errors->any())
<div class="alert alert-danger mb-4">{{ $errors->first() }}</div>
@endif

<form method="POST"
      action="{{ $editing ? route('admin.module-data.egrocery.product.update', $product->id) : route('admin.module-data.egrocery.product.store') }}"
      enctype="multipart/form-data">
    @csrf

<div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;align-items:start;">
    {{-- Left column --}}
    <div style="display:flex;flex-direction:column;gap:18px;">
        <div class="card">
            <div class="card-header"><div class="card-header-title"><i class="fas fa-info-circle" style="color:#3b82f6"></i> Basic Info</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:14px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div>
                        <label class="form-label">Name (English) *</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $product->name ?? '') }}" required>
                    </div>
                    <div>
                        <label class="form-label">Name (Somali)</label>
                        <input type="text" name="name_so" class="form-control" value="{{ old('name_so', $product->name_so ?? '') }}">
                    </div>
                </div>
                <div>
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $product->description ?? '') }}</textarea>
                </div>
                <div>
                    <label class="form-label">Tags (comma-separated)</label>
                    <input type="text" name="tags" class="form-control" placeholder="rice, staple, basmati" value="{{ old('tags', is_array($product->tags ?? null) ? implode(', ', $product->tags) : '') }}">
                </div>
            </div>
        </div>

        {{-- Variants --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-title"><i class="fas fa-layer-group" style="color:#8b5cf6"></i> Variants (SKUs)</div>
                <button type="button" onclick="addVariant()" class="btn btn-sm" style="background:#f5f3ff;color:#7c3aed;"><i class="fas fa-plus"></i> Add Variant</button>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead style="background:#fafbff;">
                        <tr>
                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;border-bottom:1px solid #f1f5f9;">Label</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;border-bottom:1px solid #f1f5f9;">Unit</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;border-bottom:1px solid #f1f5f9;">Qty</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;border-bottom:1px solid #f1f5f9;">Price $</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;border-bottom:1px solid #f1f5f9;">Compare $</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;border-bottom:1px solid #f1f5f9;">SKU</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;border-bottom:1px solid #f1f5f9;">Stock</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;border-bottom:1px solid #f1f5f9;">Low⚠️</th>
                            <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;border-bottom:1px solid #f1f5f9;">Default</th>
                            <th style="padding:10px 12px;border-bottom:1px solid #f1f5f9;"></th>
                        </tr>
                    </thead>
                    <tbody id="variantRows">
                        @if($editing && $product->activeVariants->count())
                            @foreach($product->activeVariants->sortBy('sort_order') as $i => $v)
                            <tr class="variant-row" data-idx="{{ $i }}">
                                <input type="hidden" name="variants[{{ $i }}][id]" value="{{ $v->id }}">
                                <td style="padding:8px 12px;"><input type="text" name="variants[{{ $i }}][label]" value="{{ $v->label }}" class="form-control" style="min-width:100px;" required></td>
                                <td style="padding:8px 12px;">
                                    <select name="variants[{{ $i }}][unit_id]" class="form-control" style="min-width:80px;">
                                        @foreach($units as $u)<option value="{{ $u->id }}" {{ $v->unit_id==$u->id?'selected':'' }}>{{ $u->symbol ?: $u->name }}</option>@endforeach
                                    </select>
                                </td>
                                <td style="padding:8px 12px;"><input type="number" name="variants[{{ $i }}][unit_qty]" value="{{ $v->unit_qty }}" class="form-control" style="width:65px;" step="0.001" min="0"></td>
                                <td style="padding:8px 12px;"><input type="number" name="variants[{{ $i }}][price]" value="{{ $v->price }}" class="form-control" style="width:80px;" step="0.01" min="0" required></td>
                                <td style="padding:8px 12px;"><input type="number" name="variants[{{ $i }}][compare_price]" value="{{ $v->compare_price }}" class="form-control" style="width:80px;" step="0.01" min="0"></td>
                                <td style="padding:8px 12px;"><input type="text" name="variants[{{ $i }}][sku]" value="{{ $v->sku }}" class="form-control" style="width:90px;"></td>
                                <td style="padding:8px 12px;"><input type="number" name="variants[{{ $i }}][stock_qty]" value="{{ $v->stock_qty }}" class="form-control" style="width:70px;" min="0" required></td>
                                <td style="padding:8px 12px;"><input type="number" name="variants[{{ $i }}][low_stock_threshold]" value="{{ $v->low_stock_threshold }}" class="form-control" style="width:60px;" min="0"></td>
                                <td style="padding:8px 12px;text-align:center;"><input type="radio" name="default_variant_idx" value="{{ $i }}" {{ $v->is_default?'checked':'' }} style="width:16px;height:16px;" onchange="markDefault({{ $i }})"></td>
                                <td style="padding:8px 12px;"><button type="button" onclick="removeVariant(this)" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:16px;padding:4px;">&times;</button></td>
                            </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #f1f5f9;color:#9ca3af;font-size:12px;">
                <i class="fas fa-info-circle"></i> Select the radio button for the default variant shown to customers.
            </div>
        </div>
    </div>

    {{-- Right column --}}
    <div style="display:flex;flex-direction:column;gap:18px;">
        <div class="card">
            <div class="card-header"><div class="card-header-title"><i class="fas fa-cog" style="color:#64748b"></i> Settings</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label class="form-label">Category *</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">— Select —</option>
                        @foreach($categories->whereNull('parent_id') as $c)
                        <option value="{{ $c->id }}" {{ old('category_id',$product->category_id??'') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @foreach($categories->where('parent_id',$c->id) as $sub)
                        <option value="{{ $sub->id }}" {{ old('category_id',$product->category_id??'') == $sub->id ? 'selected' : '' }}>&nbsp;&nbsp;↳ {{ $sub->name }}</option>
                        @endforeach
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Brand</label>
                    <select name="brand_id" class="form-control">
                        <option value="">None</option>
                        @foreach($brands as $b)<option value="{{ $b->id }}" {{ old('brand_id',$product->brand_id??'') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Base Unit *</label>
                    <select name="base_unit_id" class="form-control" required>
                        @foreach($units as $u)<option value="{{ $u->id }}" {{ old('base_unit_id',$product->base_unit_id??'') == $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->symbol }})</option>@endforeach
                    </select>
                </div>
                <div style="padding:12px;background:#f8fafc;border-radius:8px;display:flex;flex-direction:column;gap:10px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_weight_based" value="1" {{ old('is_weight_based',$product->is_weight_based??false)?'checked':'' }} style="width:16px;height:16px;accent-color:#FF8A00;">
                        <span style="font-size:13px;font-weight:500;">Weight-based (sold by kg/g)</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_featured" value="1" {{ old('is_featured',$product->is_featured??false)?'checked':'' }} style="width:16px;height:16px;accent-color:#FF8A00;">
                        <span style="font-size:13px;font-weight:500;">Featured product</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active',$product->is_active??true)?'checked':'' }} style="width:16px;height:16px;accent-color:#10b981;">
                        <span style="font-size:13px;font-weight:500;">Active (visible in app)</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-header-title"><i class="fas fa-images" style="color:#ec4899"></i> Images</div></div>
            <div class="card-body">
                @if($editing && !empty($product->images))
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                    @foreach((array)$product->images as $img)
                    <img src="{{ asset('storage/'.$img) }}" style="width:72px;height:72px;border-radius:8px;object-fit:cover;border:2px solid #e8edf5;" onerror="this.style.opacity=0.3">
                    @endforeach
                </div>
                @endif
                <input type="file" name="images[]" multiple accept="image/*" class="form-control">
                <p style="font-size:11px;color:#9ca3af;margin-top:6px;">Upload multiple images. First image = main thumbnail.</p>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;padding:14px;font-size:15px;">
            <i class="fas fa-{{ $editing ? 'save' : 'plus' }}"></i> {{ $editing ? 'Save Changes' : 'Create Product' }}
        </button>
    </div>
</div>
</form>

<script>
let variantIdx = {{ $editing ? $product->activeVariants->count() : 0 }};
const units = @json($units);

function addVariant() {
    const i = variantIdx++;
    const unitOpts = units.map(u => `<option value="${u.id}">${u.symbol || u.name}</option>`).join('');
    const row = document.createElement('tr');
    row.className = 'variant-row';
    row.dataset.idx = i;
    row.innerHTML = `
        <td style="padding:8px 12px;"><input type="text" name="variants[${i}][label]" placeholder="e.g. 1 kg" class="form-control" style="min-width:100px;" required></td>
        <td style="padding:8px 12px;"><select name="variants[${i}][unit_id]" class="form-control" style="min-width:80px;">${unitOpts}</select></td>
        <td style="padding:8px 12px;"><input type="number" name="variants[${i}][unit_qty]" value="1" class="form-control" style="width:65px;" step="0.001" min="0"></td>
        <td style="padding:8px 12px;"><input type="number" name="variants[${i}][price]" placeholder="0.00" class="form-control" style="width:80px;" step="0.01" min="0" required></td>
        <td style="padding:8px 12px;"><input type="number" name="variants[${i}][compare_price]" class="form-control" style="width:80px;" step="0.01" min="0"></td>
        <td style="padding:8px 12px;"><input type="text" name="variants[${i}][sku]" class="form-control" style="width:90px;"></td>
        <td style="padding:8px 12px;"><input type="number" name="variants[${i}][stock_qty]" value="0" class="form-control" style="width:70px;" min="0" required></td>
        <td style="padding:8px 12px;"><input type="number" name="variants[${i}][low_stock_threshold]" value="10" class="form-control" style="width:60px;" min="0"></td>
        <td style="padding:8px 12px;text-align:center;"><input type="radio" name="default_variant_idx" value="${i}" style="width:16px;height:16px;" onchange="markDefault(${i})"></td>
        <td style="padding:8px 12px;"><button type="button" onclick="removeVariant(this)" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:16px;padding:4px;">&times;</button></td>
    `;
    document.getElementById('variantRows').appendChild(row);
}

function removeVariant(btn) {
    if (document.querySelectorAll('.variant-row').length <= 1) { alert('Need at least 1 variant.'); return; }
    btn.closest('tr').remove();
}

function markDefault(idx) {
    document.querySelectorAll('.variant-row').forEach(row => {
        const i = row.dataset.idx;
        const hidden = row.querySelector(`input[name="variants[${i}][is_default]"]`);
        if (hidden) hidden.remove();
        const newHidden = document.createElement('input');
        newHidden.type = 'hidden';
        newHidden.name = `variants[${i}][is_default]`;
        newHidden.value = (i == idx) ? '1' : '0';
        row.appendChild(newHidden);
    });
}

// Auto-add one empty variant if creating new product
@if(!$editing)
addVariant();
@endif
</script>
@endsection
