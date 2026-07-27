@extends('vendor.layouts.app')
@section('title', 'My Products')
@section('content')
<style>
.prod-thumb { width:44px;height:44px;border-radius:8px;object-fit:cover;background:#f3f4f6; }
.modal-backdrop { position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;display:none;align-items:center;justify-content:center; }
.modal-backdrop.open { display:flex; }
.modal-box { background:#fff;border-radius:18px;padding:28px;width:90%;max-width:520px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2); }
.modal-box h3 { font-size:17px;font-weight:800;margin-bottom:18px; }
.form-group { margin-bottom:14px; }
.form-group label { display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:5px; }
.form-control { border:1.5px solid #e5e7eb;border-radius:8px;padding:9px 12px;font-size:13px;width:100%;outline:none; }
.form-control:focus { border-color:#FF8A00; }
.form-row { display:grid;grid-template-columns:1fr 1fr;gap:12px; }
.btn-cancel { background:#f3f4f6;color:#374151;padding:9px 20px;border-radius:9px;border:none;font-weight:600;cursor:pointer; }
.btn-save { background:#FF8A00;color:#fff;padding:9px 24px;border-radius:9px;border:none;font-weight:700;cursor:pointer; }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">My Products ({{ $products->total() }})</h1>
        <ul class="breadcrumb"><li><a href="{{ route('vendor.eshop.dashboard') }}">Dashboard</a></li><li>Products</li></ul>
    </div>
    <div class="page-header-actions">
        <button onclick="openModal('modal-add')" class="btn btn-brand"><i class="fas fa-plus"></i> Add Product</button>
    </div>
</div>

@if(session('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif

{{-- Filters --}}
<form method="GET" class="card" style="padding:14px 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
    <input name="search" value="{{ request('search') }}" placeholder="Search products…" class="form-control" style="width:200px;">
    <select name="category_id" class="form-control" style="width:160px;">
        <option value="">All Categories</option>
        @foreach($categories as $c)
        <option value="{{ $c->id }}" {{ request('category_id')==$c->id?'selected':'' }}>{{ $c->name }}</option>
        @endforeach
    </select>
    <select name="status" class="form-control" style="width:130px;">
        <option value="">All Status</option>
        <option value="active" {{ request('status')==='active'?'selected':'' }}>Active</option>
        <option value="inactive" {{ request('status')==='inactive'?'selected':'' }}>Inactive</option>
    </select>
    <button class="btn btn-brand btn-sm" type="submit">Filter</button>
    <a href="{{ route('vendor.eshop.products') }}" class="btn btn-sm" style="background:#f3f4f6;color:#555;">Clear</a>
</form>

<div class="card">
<div class="table-responsive">
<table class="data-table">
    <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse($products as $p)
    <tr>
        <td>
            <div style="display:flex;align-items:center;gap:10px;">
                <img src="{{ cdn_url($p->thumbnail) ?? '' }}" class="prod-thumb" onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($p->name) }}&size=44&background=f3f4f6&color=999'">
                <div>
                    <div style="font-weight:700">{{ $p->name }}</div>
                    @if($p->sku)<div style="font-size:10px;color:#aaa">SKU: {{ $p->sku }}</div>@endif
                </div>
            </div>
        </td>
        <td>{{ $p->category?->name ?? '—' }}</td>
        <td>
            <div style="font-weight:800;color:var(--brand)">${{ number_format($p->sale_price ?? $p->price, 2) }}</div>
            @if($p->sale_price && $p->sale_price < $p->price)
            <div style="text-decoration:line-through;font-size:11px;color:#aaa">${{ number_format($p->price,2) }}</div>
            @endif
        </td>
        <td style="font-weight:700">{{ $p->stock_quantity }}</td>
        <td>
            <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:{{ $p->is_available?'#dcfce7':'#fee2e2' }};color:{{ $p->is_available?'#16a34a':'#dc2626' }}">
                {{ $p->is_available ? 'Active' : 'Inactive' }}
            </span>
        </td>
        <td style="white-space:nowrap;">
            <div style="display:flex;gap:5px;">
                <button onclick='editProduct({{ $p->id }}, @json(['name'=>$p->name,'price'=>$p->price,'sale_price'=>$p->sale_price,'category_id'=>$p->category_id,'description'=>$p->description,'sku'=>$p->sku,'stock_quantity'=>$p->stock_quantity,'is_available'=>$p->is_available]))'
                    style="background:#eff6ff;color:#2563eb;border:none;padding:4px 10px;border-radius:7px;font-size:11px;font-weight:600;cursor:pointer;">Edit</button>
                <form method="POST" action="{{ route('vendor.eshop.products.toggle', $p->id) }}" style="display:inline">
                    @csrf
                    <button style="background:#f0fdf4;color:#16a34a;border:none;padding:4px 10px;border-radius:7px;font-size:11px;font-weight:600;cursor:pointer;">{{ $p->is_available ? 'Deactivate' : 'Activate' }}</button>
                </form>
                <form method="POST" action="{{ route('vendor.eshop.products.delete', $p->id) }}" style="display:inline" onsubmit="return confirm('Delete this product?')">
                    @csrf @method('DELETE')
                    <button style="background:#fef2f2;color:#dc2626;border:none;padding:4px 10px;border-radius:7px;font-size:11px;font-weight:600;cursor:pointer;">Delete</button>
                </form>
            </div>
        </td>
    </tr>
    @empty
    <tr><td colspan="6" style="text-align:center;color:#aaa;padding:40px">No products yet. Add your first product!</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div style="padding:12px 16px">{{ $products->links() }}</div>
</div>

{{-- Add Product Modal --}}
<div class="modal-backdrop" id="modal-add">
<div class="modal-box">
    <h3><i class="fas fa-plus-circle" style="color:var(--brand)"></i> Add Product</h3>
    <form method="POST" action="{{ route('vendor.eshop.products.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-row">
            <div class="form-group" style="grid-column:span 2"><label>Product Name *</label><input name="name" class="form-control" required placeholder="e.g. Nike Air Max 2024"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Price *</label><input name="price" type="number" step="0.01" class="form-control" required placeholder="0.00"></div>
            <div class="form-group"><label>Sale Price</label><input name="sale_price" type="number" step="0.01" class="form-control" placeholder="0.00"></div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="form-control">
                    <option value="">— Select —</option>
                    @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="form-group"><label>SKU</label><input name="sku" class="form-control" placeholder="Optional"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Stock Quantity</label><input name="stock_quantity" type="number" class="form-control" value="0" min="0"></div>
            <div class="form-group" style="justify-content:flex-end;padding-top:20px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="is_available" value="1" checked style="width:16px;height:16px;"> Active
                </label>
            </div>
        </div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3" placeholder="Product description…"></textarea></div>
        <div class="form-group"><label>Product Image</label><input type="file" name="image_file" accept="image/*" class="form-control"></div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-add')">Cancel</button>
            <button type="submit" class="btn-save">Add Product</button>
        </div>
    </form>
</div>
</div>

{{-- Edit Product Modal --}}
<div class="modal-backdrop" id="modal-edit">
<div class="modal-box">
    <h3><i class="fas fa-edit" style="color:var(--brand)"></i> Edit Product</h3>
    <form method="POST" id="form-edit" enctype="multipart/form-data">
        @csrf @method('PATCH')
        <div class="form-row">
            <div class="form-group" style="grid-column:span 2"><label>Product Name *</label><input id="ep-name" name="name" class="form-control" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Price *</label><input id="ep-price" name="price" type="number" step="0.01" class="form-control" required></div>
            <div class="form-group"><label>Sale Price</label><input id="ep-sale" name="sale_price" type="number" step="0.01" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Category</label>
                <select id="ep-cat" name="category_id" class="form-control">
                    <option value="">— Select —</option>
                    @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="form-group"><label>SKU</label><input id="ep-sku" name="sku" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Stock Quantity</label><input id="ep-stock" name="stock_quantity" type="number" class="form-control" min="0"></div>
            <div class="form-group" style="justify-content:flex-end;padding-top:20px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" id="ep-avail" name="is_available" value="1" style="width:16px;height:16px;"> Active
                </label>
            </div>
        </div>
        <div class="form-group"><label>Description</label><textarea id="ep-desc" name="description" class="form-control" rows="3"></textarea></div>
        <div class="form-group"><label>Replace Image (optional)</label><input type="file" name="image_file" accept="image/*" class="form-control"></div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-edit')">Cancel</button>
            <button type="submit" class="btn-save">Save Changes</button>
        </div>
    </form>
</div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-backdrop').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); }));

function editProduct(id, data) {
    document.getElementById('form-edit').action = '/vendor/eshop/products/' + id;
    document.getElementById('ep-name').value  = data.name;
    document.getElementById('ep-price').value = data.price;
    document.getElementById('ep-sale').value  = data.sale_price ?? '';
    document.getElementById('ep-cat').value   = data.category_id ?? '';
    document.getElementById('ep-sku').value   = data.sku ?? '';
    document.getElementById('ep-stock').value = data.stock_quantity ?? 0;
    document.getElementById('ep-desc').value  = data.description ?? '';
    document.getElementById('ep-avail').checked = !!data.is_available;
    openModal('modal-edit');
}
</script>
@endsection
