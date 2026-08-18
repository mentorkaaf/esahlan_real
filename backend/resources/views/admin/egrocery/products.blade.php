@extends('admin.layouts.app')
@section('title', 'eGrocery Products')
@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-carrot" style="color:#10B981"></i> Products</h2>
        <ol class="breadcrumb"><li><a href="{{ route('admin.module-data.egrocery.index') }}">eGrocery</a></li><li>Products</li></ol>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('admin.module-data.egrocery.product.template') }}" class="btn" style="background:#f1f5f9;color:#374151;font-size:13px;"><i class="fas fa-download"></i> CSV Template</a>
        <a href="{{ route('admin.module-data.egrocery.product.export') }}" class="btn" style="background:#f1f5f9;color:#374151;font-size:13px;"><i class="fas fa-file-csv"></i> Export</a>
        <button onclick="document.getElementById('importModal').style.display='flex'" class="btn" style="background:#eff6ff;color:#3b82f6;font-size:13px;"><i class="fas fa-upload"></i> Import</button>
        <a href="{{ route('admin.module-data.egrocery.product.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Add Product</a>
    </div>
</div>

@include('admin.egrocery._subnav')

@if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif

{{-- Filters --}}
<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:14px 20px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <div>
                <label class="form-label" style="font-size:11px;">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name..." style="width:200px;">
            </div>
            <div>
                <label class="form-label" style="font-size:11px;">Category</label>
                <select name="category" class="form-control" style="width:160px;">
                    <option value="">All Categories</option>
                    @foreach($categories as $c)<option value="{{ $c->id }}" {{ request('category')==$c->id?'selected':'' }}>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="form-label" style="font-size:11px;">Brand</label>
                <select name="brand" class="form-control" style="width:140px;">
                    <option value="">All Brands</option>
                    @foreach($brands as $b)<option value="{{ $b->id }}" {{ request('brand')==$b->id?'selected':'' }}>{{ $b->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="form-label" style="font-size:11px;">Status</label>
                <select name="status" class="form-control" style="width:130px;">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status')=='active'?'selected':'' }}>Active</option>
                    <option value="inactive" {{ request('status')=='inactive'?'selected':'' }}>Inactive</option>
                    <option value="featured" {{ request('status')=='featured'?'selected':'' }}>Featured</option>
                </select>
            </div>
            <div>
                <label class="form-label" style="font-size:11px;">Stock</label>
                <select name="stock" class="form-control" style="width:130px;">
                    <option value="">All Stock</option>
                    <option value="low" {{ request('stock')=='low'?'selected':'' }}>Low Stock</option>
                    <option value="out" {{ request('stock')=='out'?'selected':'' }}>Out of Stock</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('admin.module-data.egrocery.products') }}" class="btn" style="background:#f1f5f9;color:#374151;">Clear</a>
        </form>
    </div>
</div>

{{-- Bulk actions --}}
<div id="bulkBar" style="display:none;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:10px 16px;margin-bottom:12px;display:none;align-items:center;gap:12px;">
    <span id="bulkCount" style="font-size:13px;font-weight:600;color:#1d4ed8;"></span>
    <form id="bulkForm" method="POST" action="{{ route('admin.module-data.egrocery.product.bulk') }}">
        @csrf
        <div id="bulkIds"></div>
        <input type="hidden" name="action" id="bulkAction">
        <div style="display:flex;gap:8px;">
            <button type="button" onclick="doBulk('activate')" class="btn btn-sm" style="background:#dcfce7;color:#16a34a;">Activate</button>
            <button type="button" onclick="doBulk('deactivate')" class="btn btn-sm" style="background:#fef3c7;color:#d97706;">Deactivate</button>
            <button type="button" onclick="doBulk('delete')" class="btn btn-sm btn-danger">Delete</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-title"><i class="fas fa-boxes-stacked" style="color:#FF8A00"></i> {{ $products->total() }} Products</div>
        <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;">
            <input type="checkbox" id="selectAll" style="width:16px;height:16px;accent-color:#FF8A00;"> Select All
        </label>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th width="36"></th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Variants</th>
                    <th>Price Range</th>
                    <th>Total Stock</th>
                    <th>Orders</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $p)
                @php
                    $defVar   = $p->defaultVariant;
                    $minPrice = $p->activeVariants->min('price') ?? 0;
                    $maxPrice = $p->activeVariants->max('price') ?? 0;
                    $stock    = (float)($p->total_stock ?? 0);
                @endphp
                <tr>
                    <td><input type="checkbox" class="row-check" value="{{ $p->id }}" style="width:16px;height:16px;accent-color:#FF8A00;" onchange="updateBulk()"></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            @if(!empty($p->images))
                            <img src="{{ asset('storage/'.(is_array($p->images)?$p->images[0]:$p->images)) }}" style="width:40px;height:40px;border-radius:8px;object-fit:cover;background:#f1f5f9;" onerror="this.style.display='none'">
                            @else
                            <div style="width:40px;height:40px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;color:#d1d5db;"><i class="fas fa-image"></i></div>
                            @endif
                            <div>
                                <div style="font-weight:700;font-size:13px;">{{ $p->name }}</div>
                                @if($p->name_so)<div style="font-size:11px;color:#9ca3af;">{{ $p->name_so }}</div>@endif
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-secondary" style="font-size:11px;">{{ $p->category?->name }}</span></td>
                    <td style="text-align:center;">
                        <span class="badge badge-info">{{ $p->variant_count }}</span>
                    </td>
                    <td style="font-weight:700;">
                        @if($minPrice == $maxPrice)
                            ${{ number_format($minPrice, 2) }}
                        @else
                            ${{ number_format($minPrice, 2) }} – ${{ number_format($maxPrice, 2) }}
                        @endif
                    </td>
                    <td>
                        @if($stock <= 0)
                            <span class="badge badge-danger">Out of Stock</span>
                        @elseif($stock <= 20)
                            <span class="badge badge-warning">{{ $stock }} (Low)</span>
                        @else
                            <span style="font-weight:600;color:#374151;">{{ number_format($stock, 1) }}</span>
                        @endif
                    </td>
                    <td style="font-weight:600;">{{ number_format($p->orders_count) }}</td>
                    <td>
                        <div style="display:flex;flex-direction:column;gap:3px;">
                            @if($p->is_active)
                                <span class="badge badge-success badge-dot">Active</span>
                            @else
                                <span class="badge badge-secondary badge-dot">Inactive</span>
                            @endif
                            @if($p->is_featured)<span class="badge badge-orange badge-dot" style="font-size:10px;">Featured</span>@endif
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;gap:5px;">
                            <a href="{{ route('admin.module-data.egrocery.product.edit', $p->id) }}" class="btn btn-sm" style="background:#f1f5f9;color:#374151;" title="Edit"><i class="fas fa-pen"></i></a>
                            <button onclick="openQuickEdit({{ $p->id }}, {{ $defVar?->id ?? 'null' }}, {{ $defVar?->price ?? 0 }}, {{ $defVar?->stock_qty ?? 0 }})" class="btn btn-sm" style="background:#eff6ff;color:#3b82f6;" title="Quick Edit"><i class="fas fa-bolt"></i></button>
                            <form method="POST" action="{{ route('admin.module-data.egrocery.product.toggle', $p->id) }}" style="display:inline;">
                                @csrf
                                <button class="btn btn-sm" style="background:{{ $p->is_active ? '#fef3c7' : '#dcfce7' }};color:{{ $p->is_active ? '#d97706' : '#16a34a' }};" title="{{ $p->is_active ? 'Deactivate' : 'Activate' }}">
                                    <i class="fas fa-{{ $p->is_active ? 'eye-slash' : 'eye' }}"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.module-data.egrocery.product.destroy', $p->id) }}" onsubmit="return confirm('Delete product?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center;color:#9ca3af;padding:40px;">No products found. <a href="{{ route('admin.module-data.egrocery.product.create') }}">Add your first product</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($products->hasPages())
    <div class="card-footer">{{ $products->links() }}</div>
    @endif
</div>

{{-- Quick Edit Modal --}}
<div id="qeModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;width:380px;box-shadow:0 25px 60px rgba(0,0,0,.2);">
        <div style="padding:18px 20px;border-bottom:1px solid #f1f5f9;font-weight:800;"><i class="fas fa-bolt" style="color:#3b82f6;margin-right:8px;"></i>Quick Edit</div>
        <form id="qeForm" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
            @csrf
            <input type="hidden" name="variant_id" id="qeVarId">
            <div><label class="form-label">Price ($)</label><input type="number" name="price" id="qePrice" class="form-control" step="0.01" min="0"></div>
            <div><label class="form-label">Stock Qty</label><input type="number" name="stock_qty" id="qeStock" class="form-control" min="0"></div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Save</button>
                <button type="button" onclick="document.getElementById('qeModal').style.display='none'" class="btn" style="background:#f1f5f9;color:#374151;">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- CSV Import Modal --}}
<div id="importModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;width:420px;box-shadow:0 25px 60px rgba(0,0,0,.2);">
        <div style="padding:18px 20px;border-bottom:1px solid #f1f5f9;font-weight:800;display:flex;justify-content:space-between;align-items:center;">
            <span><i class="fas fa-upload" style="color:#3b82f6;margin-right:8px;"></i>Import CSV</span>
            <button onclick="document.getElementById('importModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:18px;color:#9ca3af;">&times;</button>
        </div>
        <div style="padding:20px;">
            <p style="font-size:13px;color:#64748b;margin-bottom:14px;">Download the template first, fill in your products, then upload here.</p>
            <form method="POST" action="{{ route('admin.module-data.egrocery.product.import') }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="csv_file" class="form-control" accept=".csv,.txt" required style="margin-bottom:14px;">
                <div style="display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Import</button>
                    <a href="{{ route('admin.module-data.egrocery.product.template') }}" class="btn" style="background:#f1f5f9;color:#374151;">Template</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const qeBaseUrl = '{{ url("admin/module-data/egrocery/products") }}';

document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(c => { c.checked = this.checked; });
    updateBulk();
});

function updateBulk() {
    const checked = document.querySelectorAll('.row-check:checked');
    const bar = document.getElementById('bulkBar');
    if (checked.length > 0) {
        bar.style.display = 'flex';
        document.getElementById('bulkCount').textContent = checked.length + ' selected';
    } else {
        bar.style.display = 'none';
    }
}

function doBulk(action) {
    if (!confirm('Are you sure?')) return;
    const ids = [...document.querySelectorAll('.row-check:checked')].map(c => c.value);
    document.getElementById('bulkAction').value = action;
    const container = document.getElementById('bulkIds');
    container.innerHTML = ids.map(id => `<input type="hidden" name="ids[]" value="${id}">`).join('');
    document.getElementById('bulkForm').submit();
}

function openQuickEdit(productId, variantId, price, stock) {
    document.getElementById('qeVarId').value  = variantId;
    document.getElementById('qePrice').value  = price;
    document.getElementById('qeStock').value  = stock;
    document.getElementById('qeForm').onsubmit = function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fetch(qeBaseUrl + '/' + productId + '/quick-edit', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: fd
        }).then(r => r.json()).then(() => { document.getElementById('qeModal').style.display='none'; location.reload(); });
    };
    document.getElementById('qeModal').style.display = 'flex';
}
</script>
@endsection
