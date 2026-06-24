@extends('admin.layouts.app')
@section('title', 'eGrocery Management')
@section('content')

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-carrot" style="color:#10B981"></i> eGrocery Management</h2>
        <ol class="breadcrumb"><li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li>eGrocery</li></ol>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-bottom:20px;">
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #10B981;">
        <div style="font-size:22px;font-weight:900;">{{ $stats['total_products'] }}</div>
        <div style="font-size:11px;color:#8A8A9A;">Products</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #3B82F6;">
        <div style="font-size:22px;font-weight:900;">{{ $stats['active_products'] }}</div>
        <div style="font-size:11px;color:#8A8A9A;">Active</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #FF8A00;">
        <div style="font-size:22px;font-weight:900;">{{ $stats['total_orders'] }}</div>
        <div style="font-size:11px;color:#8A8A9A;">Orders</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #F59E0B;">
        <div style="font-size:22px;font-weight:900;">{{ $stats['pending_orders'] }}</div>
        <div style="font-size:11px;color:#8A8A9A;">Pending</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #10B981;">
        <div style="font-size:22px;font-weight:900;">${{ number_format($stats['revenue'], 2) }}</div>
        <div style="font-size:11px;color:#8A8A9A;">Revenue</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #8B5CF6;">
        <div style="font-size:22px;font-weight:900;">{{ $stats['today_orders'] }}</div>
        <div style="font-size:11px;color:#8A8A9A;">Today</div>
    </div>
</div>

{{-- Tabs --}}
@php $tab = request('tab', 'categories'); @endphp
<div style="display:flex;gap:0;margin-bottom:16px;border-bottom:2px solid #f0f1f5;">
    @foreach(['categories'=>'Categories','products'=>'Products','orders'=>'Orders'] as $k=>$v)
    <a href="?tab={{ $k }}" style="padding:10px 20px;font-weight:700;font-size:13px;color:{{ $tab===$k?'#FF8A00':'#8A8A9A' }};border-bottom:{{ $tab===$k?'3px solid #FF8A00':'none' }};text-decoration:none;">{{ $v }}</a>
    @endforeach
</div>

{{-- ═══ CATEGORIES TAB ═══ --}}
@if($tab === 'categories')
<div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
    <button class="btn btn-primary" onclick="openModal('addCatModal')"><i class="fas fa-plus"></i> Add Category</button>
</div>
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Image</th><th>Name</th><th>Somali</th><th>Sort</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($categories as $c)
            <tr>
                <td>@if($c->image)<img src="{{ $c->image }}" style="width:40px;height:40px;border-radius:8px;object-fit:cover;">@else<div style="width:40px;height:40px;border-radius:8px;background:#f0f1f5;display:flex;align-items:center;justify-content:center;">🥬</div>@endif</td>
                <td style="font-weight:700;">{{ $c->name }}</td>
                <td style="color:#8A8A9A;">{{ $c->name_so ?? '—' }}</td>
                <td>{{ $c->sort_order }}</td>
                <td><span class="badge {{ $c->is_active ? 'badge-success' : 'badge-danger' }}">{{ $c->is_active ? 'Active' : 'Off' }}</span></td>
                <td class="d-flex gap-2">
                    <button class="btn btn-sm btn-secondary" onclick="openEditCat({{ json_encode($c) }})"><i class="fas fa-edit"></i></button>
                    <form action="{{ route('admin.module-data.egrocery.category.destroy', $c->id) }}" method="POST" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button></form>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;padding:30px;color:#8A8A9A;">No categories. Add your first grocery category.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>

{{-- Add Category Modal --}}
<div class="modal-overlay" id="addCatModal"><div class="modal-box"><div class="modal-header"><h3 class="modal-title">Add Category</h3><button class="modal-close" onclick="closeModal('addCatModal')">✕</button></div>
<form action="{{ route('admin.module-data.egrocery.category.store') }}" method="POST" enctype="multipart/form-data"><@csrf
<div class="modal-body">
    <div class="grid-2"><div class="form-group"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" required placeholder="e.g. Vegetables"></div>
    <div class="form-group"><label class="form-label">Somali Name</label><input type="text" name="name_so" class="form-control" placeholder="e.g. Khudaar"></div></div>
    <div class="form-group"><label class="form-label">Image</label><input type="file" name="image_file" accept="image/*" class="form-control"></div>
    <div class="grid-2"><div class="form-group"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="0"></div>
    <div class="form-group" style="padding-top:28px;"><label><input type="checkbox" name="is_active" value="1" checked> Active</label></div></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('addCatModal')">Cancel</button><button type="submit" class="btn btn-primary">Add</button></div>
</form></div></div>

{{-- Edit Category Modal --}}
<div class="modal-overlay" id="editCatModal"><div class="modal-box"><div class="modal-header"><h3 class="modal-title">Edit Category</h3><button class="modal-close" onclick="closeModal('editCatModal')">✕</button></div>
<form id="editCatForm" method="POST" enctype="multipart/form-data">@csrf
<div class="modal-body">
    <div class="grid-2"><div class="form-group"><label class="form-label">Name *</label><input type="text" name="name" id="ec_name" class="form-control" required></div>
    <div class="form-group"><label class="form-label">Somali Name</label><input type="text" name="name_so" id="ec_name_so" class="form-control"></div></div>
    <div class="form-group"><label class="form-label">Image</label><input type="file" name="image_file" accept="image/*" class="form-control"></div>
    <div class="grid-2"><div class="form-group"><label class="form-label">Sort</label><input type="number" name="sort_order" id="ec_sort" class="form-control"></div>
    <div class="form-group" style="padding-top:28px;"><label><input type="checkbox" name="is_active" id="ec_active" value="1"> Active</label></div></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('editCatModal')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
</form></div></div>
@endif

{{-- ═══ PRODUCTS TAB ═══ --}}
@if($tab === 'products')
<div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
    <button class="btn btn-primary" onclick="openModal('addProdModal')"><i class="fas fa-plus"></i> Add Product</button>
</div>
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Image</th><th>Product</th><th>Category</th><th>Price</th><th>Sale</th><th>Unit</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($products as $p)
            <tr>
                <td>@if($p->thumbnail)<img src="{{ $p->thumbnail }}" style="width:40px;height:40px;border-radius:8px;object-fit:cover;" onerror="this.style.display='none'">@else<div style="width:40px;height:40px;border-radius:8px;background:#f0f1f5;display:flex;align-items:center;justify-content:center;">🥕</div>@endif</td>
                <td><strong>{{ $p->name }}</strong>@if($p->is_featured)<span class="badge badge-warning" style="margin-left:4px;">⭐</span>@endif</td>
                <td style="color:#8A8A9A;">{{ $p->category?->name ?? '—' }}</td>
                <td style="font-weight:700;">${{ number_format($p->price, 2) }}</td>
                <td>@if($p->sale_price)<span style="color:#10B981;font-weight:700;">${{ number_format($p->sale_price, 2) }}</span>@else —@endif</td>
                <td>{{ $p->unit ?? '—' }}</td>
                <td>{{ $p->stock_quantity ?? '∞' }}</td>
                <td><span class="badge {{ $p->is_available ? 'badge-success' : 'badge-danger' }}">{{ $p->is_available ? 'Active' : 'Off' }}</span></td>
                <td class="d-flex gap-2">
                    <form action="{{ route('admin.module-data.egrocery.product.toggle', $p->id) }}" method="POST">@csrf<button class="btn btn-sm {{ $p->is_available ? 'btn-warning' : 'btn-success' }}" title="{{ $p->is_available ? 'Disable' : 'Enable' }}"><i class="fas {{ $p->is_available ? 'fa-eye-slash' : 'fa-eye' }}"></i></button></form>
                    <form action="{{ route('admin.module-data.egrocery.product.destroy', $p->id) }}" method="POST" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button></form>
                </td>
            </tr>
            @empty
            <tr><td colspan="9" style="text-align:center;padding:30px;color:#8A8A9A;">No products yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @if($products->hasPages())<div style="padding:16px;display:flex;justify-content:center;">{{ $products->withQueryString()->links() }}</div>@endif
</div>

{{-- Add Product Modal --}}
<div class="modal-overlay" id="addProdModal"><div class="modal-box"><div class="modal-header"><h3 class="modal-title">Add Grocery Product</h3><button class="modal-close" onclick="closeModal('addProdModal')">✕</button></div>
<form action="{{ route('admin.module-data.egrocery.product.store') }}" method="POST" enctype="multipart/form-data">@csrf
<div class="modal-body">
    <div class="form-group"><label class="form-label">Product Name *</label><input type="text" name="name" class="form-control" required placeholder="e.g. Basmati Rice 5kg"></div>
    <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
    <div class="form-group"><label class="form-label">Category</label>
        <select name="category_id" class="form-control"><option value="">— Select —</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
    <div class="grid-2">
        <div class="form-group"><label class="form-label">Price ($) *</label><input type="number" name="price" class="form-control" step="0.01" required></div>
        <div class="form-group"><label class="form-label">Sale Price ($)</label><input type="number" name="sale_price" class="form-control" step="0.01"></div>
    </div>
    <div class="grid-2">
        <div class="form-group"><label class="form-label">Unit</label>
            <select name="unit" class="form-control"><option value="piece">Piece</option><option value="kg">KG</option><option value="gram">Gram</option><option value="liter">Liter</option><option value="box">Box</option><option value="packet">Packet</option><option value="bottle">Bottle</option></select></div>
        <div class="form-group"><label class="form-label">Stock</label><input type="number" name="stock_quantity" class="form-control" value="100"></div>
    </div>
    <div class="form-group"><label class="form-label">Image</label><input type="file" name="image_file" accept="image/*" class="form-control"></div>
    <div class="form-group"><label><input type="checkbox" name="is_featured" value="1"> Featured Product</label></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('addProdModal')">Cancel</button><button type="submit" class="btn btn-primary">Add Product</button></div>
</form></div></div>
@endif

{{-- ═══ ORDERS TAB ═══ --}}
@if($tab === 'orders')
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Order #</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($orders as $o)
            @php $sc = ['pending'=>'badge-warning','confirmed'=>'badge-info','delivered'=>'badge-success','cancelled'=>'badge-danger'][$o->status] ?? 'badge-secondary'; @endphp
            <tr>
                <td><a href="{{ route('admin.orders.show', $o->id) }}" style="font-weight:700;color:#FF8A00;">#{{ $o->order_number }}</a></td>
                <td>{{ $o->user?->name ?? '—' }}</td>
                <td>{{ $o->items->count() }} items</td>
                <td style="font-weight:700;">${{ number_format($o->total_amount, 2) }}</td>
                <td><span class="badge {{ $sc }}">{{ ucfirst($o->status) }}</span></td>
                <td style="font-size:12px;color:#8A8A9A;">{{ $o->created_at->format('d M Y H:i') }}</td>
                <td>
                    <form action="{{ route('admin.module-data.egrocery.order.status', $o->id) }}" method="POST" style="display:flex;gap:4px;">
                        @csrf @method('PATCH')
                        <select name="status" class="form-control" style="width:130px;font-size:12px;">
                            @foreach(['pending','confirmed','preparing','ready_for_pickup','out_for_delivery','delivered','cancelled'] as $s)
                            <option value="{{ $s }}" {{ $o->status===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-primary"><i class="fas fa-check"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;padding:30px;color:#8A8A9A;">No orders yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @if($orders->hasPages())<div style="padding:16px;display:flex;justify-content:center;">{{ $orders->withQueryString()->links() }}</div>@endif
</div>
@endif

@push('scripts')
<script>
function openEditCat(c) {
    document.getElementById('editCatForm').action = '/admin/module-data/egrocery/categories/' + c.id;
    document.getElementById('ec_name').value = c.name || '';
    document.getElementById('ec_name_so').value = c.name_so || '';
    document.getElementById('ec_sort').value = c.sort_order || 0;
    document.getElementById('ec_active').checked = c.is_active == 1;
    openModal('editCatModal');
}
</script>
@endpush
@endsection
