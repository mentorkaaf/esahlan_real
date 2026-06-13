@extends('admin.layouts.app')
@section('title', 'eLaundry — Items Management')
@section('content')

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-tshirt" style="color:var(--primary)"></i> eLaundry Items</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">eLaundry Items</li>
        </ol>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')">
        <i class="fas fa-plus"></i> Add Item
    </button>
</div>

<div class="card">
    <div class="card-header">
        <span>Laundry Items ({{ $items->count() }})</span>
        <small class="text-muted">Normal = $1/item default · Express = $2/item default (admin sets actual prices)</small>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Name</th>
                    <th>Normal Price</th>
                    <th>Normal (days)</th>
                    <th>Express Price</th>
                    <th>Express (hours)</th>
                    <th>Sort</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                <tr>
                    <td>{{ $item->id }}</td>
                    <td><strong>{{ $item->name }}</strong></td>
                    <td><strong class="text-success">${{ number_format($item->normal_price, 2) }}</strong></td>
                    <td>{{ $item->normal_days }} day{{ $item->normal_days > 1 ? 's' : '' }}</td>
                    <td><strong class="text-danger">${{ number_format($item->express_price, 2) }}</strong></td>
                    <td>{{ $item->express_hours }}h</td>
                    <td>{{ $item->sort_order }}</td>
                    <td>
                        <span class="badge {{ $item->is_active ? 'badge-success' : 'badge-danger' }}">
                            {{ $item->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="d-flex gap-2">
                        <button class="btn btn-sm btn-secondary" onclick="openEdit({{ $item->id }}, '{{ addslashes($item->name) }}', {{ $item->normal_price }}, {{ $item->express_price }}, {{ $item->normal_days }}, {{ $item->express_hours }}, {{ $item->sort_order ?? 0 }}, {{ $item->is_active ? 1 : 0 }})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form action="{{ route('admin.module-data.laundry.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Delete this item?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center;padding:30px;color:#888;">No items yet. Add your first laundry item.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Add Modal --}}
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Add Laundry Item</h3>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <form action="{{ route('admin.module-data.laundry.store') }}" method="POST">
            @csrf
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Item Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Shirt, Trouser">
                </div>
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Normal Price ($) *</label>
                    <input type="number" name="normal_price" class="form-control" step="0.01" required placeholder="1.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Normal Duration (days) *</label>
                    <input type="number" name="normal_days" class="form-control" required value="2">
                </div>
                <div class="form-group">
                    <label class="form-label">Express Price ($) *</label>
                    <input type="number" name="express_price" class="form-control" step="0.01" required placeholder="2.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Express Duration (hours) *</label>
                    <input type="number" name="express_hours" class="form-control" required value="24">
                </div>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="is_active" value="1" checked> Active
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Add Item</button>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Edit Laundry Item</h3>
            <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>
        <form id="editForm" method="POST">
            @csrf @method('PATCH')
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Item Name *</label>
                    <input type="text" name="name" id="editName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" id="editSort" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Normal Price ($) *</label>
                    <input type="number" name="normal_price" id="editNormal" class="form-control" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Normal Duration (days) *</label>
                    <input type="number" name="normal_days" id="editNormalDays" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Express Price ($) *</label>
                    <input type="number" name="express_price" id="editExpress" class="form-control" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Express Duration (hours) *</label>
                    <input type="number" name="express_hours" id="editExpressHours" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="is_active" id="editActive" value="1"> Active
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Save Changes</button>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openEdit(id, name, normalPrice, expressPrice, normalDays, expressHours, sort, isActive) {
    document.getElementById('editForm').action = `/admin/module-data/laundry/items/${id}`;
    document.getElementById('editName').value = name;
    document.getElementById('editNormal').value = normalPrice;
    document.getElementById('editExpress').value = expressPrice;
    document.getElementById('editNormalDays').value = normalDays;
    document.getElementById('editExpressHours').value = expressHours;
    document.getElementById('editSort').value = sort;
    document.getElementById('editActive').checked = isActive == 1;
    openModal('editModal');
}
</script>
@endpush
@endsection
