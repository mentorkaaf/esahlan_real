@extends('vendor.layouts.app')
@section('title', 'Addons')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Addons</h1>
        <p style="color:var(--text-muted);font-size:13px;margin:4px 0 0">Extra toppings, drinks, sides — add-ons customers can pick with their order</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Add Addon</button>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="card">
    <div class="card-header"><div class="card-header-title">Addons ({{ $addons->count() }})</div></div>
    <div class="card-body" style="padding:0">
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="background:var(--bg-muted);font-size:12px;color:var(--text-muted);font-weight:700;text-transform:uppercase">
                    <th style="padding:12px 16px;text-align:left">Image</th>
                    <th style="padding:12px 16px;text-align:left">Name</th>
                    <th style="padding:12px 16px;text-align:right">Price</th>
                    <th style="padding:12px 16px;text-align:center">Status</th>
                    <th style="padding:12px 16px;text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($addons as $a)
                <tr style="border-top:1px solid var(--border)">
                    <td style="padding:10px 16px">
                        @if($a->image)
                        <img src="{{ $a->image }}" style="width:44px;height:44px;object-fit:cover;border-radius:10px">
                        @else
                        <div style="width:44px;height:44px;background:var(--bg-muted);border-radius:10px;display:flex;align-items:center;justify-content:center">
                            <i class="fas fa-puzzle-piece" style="color:var(--text-muted)"></i>
                        </div>
                        @endif
                    </td>
                    <td style="padding:10px 16px;font-weight:700">{{ $a->name }}</td>
                    <td style="padding:10px 16px;text-align:right;font-weight:700;color:var(--success)">${{ number_format($a->price, 2) }}</td>
                    <td style="padding:10px 16px;text-align:center">
                        <span class="badge {{ $a->is_active ? 'badge-success' : 'badge-danger' }}">{{ $a->is_active ? 'Active' : 'Inactive' }}</span>
                    </td>
                    <td style="padding:10px 16px;text-align:center">
                        <div style="display:flex;gap:6px;justify-content:center">
                            <button class="btn btn-sm btn-secondary" onclick="openEdit({{ $a->id }}, '{{ addslashes($a->name) }}', {{ $a->price }}, {{ $a->is_active ? 1 : 0 }}, '{{ $a->image ?? '' }}')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form action="{{ route('vendor.addons.destroy', $a->id) }}" method="POST" onsubmit="return confirm('Delete this addon?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted)">
                    <i class="fas fa-puzzle-piece" style="font-size:28px;display:block;margin-bottom:10px;opacity:0.3"></i>
                    No addons yet. Create your first addon.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Add Modal --}}
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Add Addon</h3>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <form action="{{ route('vendor.addons.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Image</label>
                    <input type="file" name="image_file" accept="image/*" class="form-control">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Extra Cheese">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price ($) *</label>
                        <input type="number" name="price" class="form-control" step="0.01" min="0" required placeholder="0.50">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Addon</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Edit Addon</h3>
            <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>
        <form id="editForm" method="POST" enctype="multipart/form-data">
            @csrf @method('PATCH')
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Image</label>
                    <div id="editPreviewWrap" style="margin-bottom:8px;display:none">
                        <img id="editPreview" src="" style="width:60px;height:60px;object-fit:cover;border-radius:10px">
                    </div>
                    <input type="file" name="image_file" accept="image/*" class="form-control">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price ($) *</label>
                        <input type="number" name="price" id="editPrice" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="is_active" id="editActive" value="1"> Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id, name, price, isActive, image) {
    document.getElementById('editForm').action = '/vendor/addons/' + id;
    document.getElementById('editName').value = name;
    document.getElementById('editPrice').value = price;
    document.getElementById('editActive').checked = isActive == 1;
    var wrap = document.getElementById('editPreviewWrap');
    var img  = document.getElementById('editPreview');
    if (image) { img.src = image; wrap.style.display = 'block'; } else { wrap.style.display = 'none'; }
    openModal('editModal');
}
</script>
@endsection
