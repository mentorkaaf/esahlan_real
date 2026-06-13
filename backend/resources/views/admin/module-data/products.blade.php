@extends('admin.layouts.app')
@section('title', $title)
@section('content')

<div class="page-header">
    <div>
        <h2 class="page-title">
            @if($moduleId === 'eshop') <i class="fas fa-shopping-bag" style="color:var(--primary)"></i>
            @elseif($moduleId === 'ewholesale') <i class="fas fa-warehouse" style="color:var(--primary)"></i>
            @elseif($moduleId === 'efood') <i class="fas fa-utensils" style="color:var(--primary)"></i>
            @else <i class="fas fa-carrot" style="color:var(--primary)"></i>
            @endif
            {{ $title }}
        </h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">{{ $title }}</li>
        </ol>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')">
        <i class="fas fa-plus"></i> Add Product
    </button>
</div>

<div class="card">
    <div class="card-header">
        Products ({{ $products->total() }})
        <div class="d-flex gap-2">
            @if($moduleId === 'efood')     <a href="{{ route('admin.module-data.food') }}"      class="btn btn-sm btn-secondary">eFood</a> @endif
            @if($moduleId === 'eshop')     <a href="{{ route('admin.module-data.shop') }}"      class="btn btn-sm btn-secondary">eShop</a> @endif
            @if($moduleId === 'ewholesale')<a href="{{ route('admin.module-data.wholesale') }}"  class="btn btn-sm btn-secondary">Wholesale</a> @endif
            @if($moduleId === 'egrocery')  <a href="{{ route('admin.module-data.grocery') }}"   class="btn btn-sm btn-secondary">eGrocery</a> @endif
        </div>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    @if($moduleId === 'eshop')     <th>Original</th> @endif
                    @if($moduleId === 'ewholesale') <th>Min Qty</th> @endif
                    @if($moduleId === 'egrocery')  <th>Unit</th>   @endif
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                <tr>
                    <td>
                        @if($product->image)
                            <img src="{{ $product->image }}" style="width:48px;height:42px;object-fit:cover;border-radius:6px;" alt="">
                        @else
                            <div style="width:48px;height:42px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-image" style="color:#ccc;"></i>
                            </div>
                        @endif
                    </td>
                    <td><strong>{{ Str::limit($product->name, 40) }}</strong></td>
                    <td>{{ $product->category?->name ?? '-' }}</td>
                    <td><strong class="text-success">${{ number_format($product->price, 2) }}</strong></td>
                    @if($moduleId === 'eshop')
                        <td>{{ $product->original_price ? '$' . number_format($product->original_price, 2) : '-' }}</td>
                    @endif
                    @if($moduleId === 'ewholesale')
                        <td><span class="badge badge-warning">{{ $product->min_qty ?? 1 }} units min</span></td>
                    @endif
                    @if($moduleId === 'egrocery')
                        <td>{{ $product->unit ?? '-' }}</td>
                    @endif
                    <td>{{ $product->stock ?? '∞' }}</td>
                    <td>
                        <span class="badge {{ $product->is_active ? 'badge-success' : 'badge-danger' }}">
                            {{ $product->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="d-flex gap-2">
                        <button class="btn btn-sm btn-secondary" onclick='openEdit({{ json_encode($product) }})'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <form action="{{ route('admin.module-data.product.destroy', $product->id) }}" method="POST" onsubmit="return confirm('Delete?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" style="text-align:center;padding:30px;color:#888;">No products yet. Add your first product.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($products->hasPages())
    <div class="card-body">{{ $products->links() }}</div>
    @endif
</div>

{{-- Add Modal --}}
<div class="modal-overlay" id="addModal">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-header">
            <h3 class="modal-title">Add Product</h3>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <form action="{{ route('admin.module-data.product.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="module_id" value="{{ $moduleId }}">
            <div class="form-group">
                <label class="form-label">Product Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="Product name">
            </div>
            <div class="form-group">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-control">
                    <option value="">No category</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Price ($) *</label>
                    <input type="number" name="price" class="form-control" step="0.01" required>
                </div>
                @if($moduleId === 'eshop')
                <div class="form-group">
                    <label class="form-label">Original Price ($)</label>
                    <input type="number" name="original_price" class="form-control" step="0.01" placeholder="For showing discount">
                </div>
                @endif
                @if($moduleId === 'ewholesale')
                <div class="form-group">
                    <label class="form-label">Min Order Qty</label>
                    <input type="number" name="min_qty" class="form-control" value="10" min="1">
                </div>
                @endif
                @if($moduleId === 'egrocery')
                <div class="form-group">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" class="form-control" placeholder="e.g. per kg, per piece">
                </div>
                @endif
                <div class="form-group">
                    <label class="form-label">Stock</label>
                    <input type="number" name="stock" class="form-control" placeholder="Leave empty for unlimited">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Product Image</label>
                <div onclick="document.getElementById('addPr_imgFile').click()" style="border:2px dashed #FF8A00;border-radius:10px;padding:10px;text-align:center;cursor:pointer;background:#fff9f2;min-height:80px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:6px;">
                    <img id="addPr_imgPreview" src="" style="display:none;max-height:70px;border-radius:8px;object-fit:cover;">
                    <span id="addPr_imgPlaceholder" style="color:#FF8A00;font-size:12px;">📦 Upload Image</span>
                </div>
                <input type="file" id="addPr_imgFile" name="image_file" accept="image/*" style="display:none" onchange="previewImage(this,'addPr_imgPreview','addPr_imgPlaceholder')">
                <input type="text" name="image" id="prImage" class="form-control" placeholder="Or paste image URL..." style="margin-top:6px;font-size:12px;" oninput="previewFromUrl(this.value,'addPr_imgPreview','addPr_imgPlaceholder')">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="is_active" value="1" checked> Active
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Add Product</button>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div class="modal-overlay" id="editModal">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Product</h3>
            <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>
        <form id="editForm" method="POST" enctype="multipart/form-data">
            @csrf @method('PATCH')
            <div class="form-group">
                <label class="form-label">Product Name *</label>
                <input type="text" name="name" id="prName" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Category</label>
                <select name="category_id" id="prCat" class="form-control">
                    <option value="">No category</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Price ($) *</label>
                    <input type="number" name="price" id="prPrice" class="form-control" step="0.01" required>
                </div>
                @if($moduleId === 'eshop')
                <div class="form-group">
                    <label class="form-label">Original Price ($)</label>
                    <input type="number" name="original_price" id="prOrig" class="form-control" step="0.01">
                </div>
                @endif
                @if($moduleId === 'ewholesale')
                <div class="form-group">
                    <label class="form-label">Min Order Qty</label>
                    <input type="number" name="min_qty" id="prMinQty" class="form-control" min="1">
                </div>
                @endif
                @if($moduleId === 'egrocery')
                <div class="form-group">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" id="prUnit" class="form-control">
                </div>
                @endif
                <div class="form-group">
                    <label class="form-label">Stock</label>
                    <input type="number" name="stock" id="prStock" class="form-control">
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;margin-top:24px;">
                        <input type="checkbox" name="is_active" id="prActive" value="1"> Active
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Product Image</label>
                <div onclick="document.getElementById('editPr_imgFile').click()" style="border:2px dashed #FF8A00;border-radius:10px;padding:10px;text-align:center;cursor:pointer;background:#fff9f2;min-height:80px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:6px;">
                    <img id="editPr_imgPreview" src="" style="display:none;max-height:70px;border-radius:8px;object-fit:cover;">
                    <span id="editPr_imgPlaceholder" style="color:#FF8A00;font-size:12px;">📦 Click to change image</span>
                </div>
                <input type="file" id="editPr_imgFile" name="image_file" accept="image/*" style="display:none" onchange="previewImage(this,'editPr_imgPreview','editPr_imgPlaceholder')">
                <input type="text" name="image" id="prImage" class="form-control" placeholder="Or paste image URL..." style="margin-top:6px;font-size:12px;" oninput="previewFromUrl(this.value,'editPr_imgPreview','editPr_imgPlaceholder')">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="prDesc" class="form-control" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Save Changes</button>
        </form>
    </div>
</div>

@push('scripts')
<script>
const moduleId = '{{ $moduleId }}';
function openEdit(p) {
    document.getElementById('editForm').action = `/admin/module-data/products/${p.id}`;
    document.getElementById('prName').value  = p.name;
    document.getElementById('prCat').value   = p.category_id || '';
    document.getElementById('prPrice').value = p.price;
    document.getElementById('prStock').value = p.stock || '';
    document.getElementById('prImage').value = p.image || '';
    document.getElementById('prDesc').value  = p.description || '';
    document.getElementById('prActive').checked = p.is_active == 1;
    if (moduleId === 'eshop' && document.getElementById('prOrig')) document.getElementById('prOrig').value = p.original_price || '';
    if (moduleId === 'ewholesale' && document.getElementById('prMinQty')) document.getElementById('prMinQty').value = p.min_qty || '';
    if (moduleId === 'egrocery' && document.getElementById('prUnit')) document.getElementById('prUnit').value = p.unit || '';
    if (p.image) previewFromUrl(p.image, 'editPr_imgPreview', 'editPr_imgPlaceholder');
    else { document.getElementById('editPr_imgPreview').style.display='none'; document.getElementById('editPr_imgPlaceholder').style.display='block'; }
    openModal('editModal');
}
function previewImage(input, prevId, phId) {
    const file = input.files[0]; if (!file) return;
    const reader = new FileReader();
    reader.onload = e => { const p=document.getElementById(prevId); p.src=e.target.result; p.style.display='block'; const ph=document.getElementById(phId); if(ph) ph.style.display='none'; };
    reader.readAsDataURL(file);
}
function previewFromUrl(url, prevId, phId) {
    const p=document.getElementById(prevId); const ph=document.getElementById(phId);
    if (url && url.startsWith('http')) { p.src=url; p.style.display='block'; if(ph) ph.style.display='none'; }
    else { p.style.display='none'; if(ph) ph.style.display='block'; }
}
</script>
@endpush
@endsection
