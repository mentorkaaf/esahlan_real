@extends('admin.layouts.app')
@section('title', 'eGrocery Categories')
@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-carrot" style="color:#10B981"></i> eGrocery Categories</h2>
        <ol class="breadcrumb"><li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li><a href="{{ route('admin.module-data.egrocery.index') }}">eGrocery</a></li><li>Categories</li></ol>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('addCatModal').style.display='flex'"><i class="fas fa-plus"></i> Add Category</button>
</div>

@include('admin.egrocery._subnav')

@if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger mb-4">{{ session('error') }}</div>@endif

<div class="card">
    <div class="card-header">
        <div class="card-header-title"><i class="fas fa-folder-tree" style="color:#3b82f6"></i> Category Tree</div>
        <span style="font-size:12px;color:#9ca3af;">{{ $parents->count() }} parent categories</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th width="36"></th><th>Icon</th><th>Name</th><th>Somali Name</th><th>Products</th><th>Sort</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody id="catSortable">
                @forelse($parents as $parent)
                <tr data-id="{{ $parent->id }}" style="background:#fafbff;">
                    <td style="cursor:grab;color:#d1d5db;"><i class="fas fa-grip-vertical"></i></td>
                    <td style="font-size:20px;">{{ $parent->icon }}</td>
                    <td style="font-weight:700;">{{ $parent->name }}</td>
                    <td style="color:#64748b;">{{ $parent->name_so }}</td>
                    <td><span class="badge badge-info">{{ $parent->products_count }}</span></td>
                    <td style="color:#9ca3af;">{{ $parent->sort_order }}</td>
                    <td>
                        <label style="cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                            <input type="checkbox" onchange="toggleCat({{ $parent->id }},this)" {{ $parent->is_active?'checked':'' }} style="width:16px;height:16px;accent-color:#10b981;">
                            <span style="font-size:12px;color:{{ $parent->is_active?'#10b981':'#9ca3af' }};">{{ $parent->is_active?'Active':'Inactive' }}</span>
                        </label>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <button onclick="editCat({{ $parent->toJson() }})" class="btn btn-sm" style="background:#f1f5f9;color:#374151;"><i class="fas fa-pen"></i></button>
                            <form method="POST" action="{{ route('admin.module-data.egrocery.category.destroy',$parent->id) }}" onsubmit="return confirm('Delete this category?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                            <button onclick="addSubCat({{ $parent->id }}, '{{ addslashes($parent->name) }}')" class="btn btn-sm" style="background:#eff6ff;color:#3b82f6;" title="Add Sub-category"><i class="fas fa-folder-plus"></i></button>
                        </div>
                    </td>
                </tr>
                @foreach($parent->children as $child)
                <tr data-id="{{ $child->id }}" style="background:#fff;">
                    <td></td>
                    <td style="padding-left:24px;font-size:16px;color:#9ca3af;"><i class="fas fa-corner-down-right" style="font-size:10px;margin-right:4px;"></i>{{ $child->icon }}</td>
                    <td style="padding-left:28px;color:#64748b;">{{ $child->name }}</td>
                    <td style="color:#9ca3af;">{{ $child->name_so }}</td>
                    <td><span class="badge badge-secondary">{{ $child->products_count }}</span></td>
                    <td style="color:#9ca3af;">{{ $child->sort_order }}</td>
                    <td>
                        <label style="cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                            <input type="checkbox" onchange="toggleCat({{ $child->id }},this)" {{ $child->is_active?'checked':'' }} style="width:16px;height:16px;accent-color:#10b981;">
                            <span style="font-size:12px;color:{{ $child->is_active?'#10b981':'#9ca3af' }};">{{ $child->is_active?'Active':'Inactive' }}</span>
                        </label>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <button onclick="editCat({{ $child->toJson() }})" class="btn btn-sm" style="background:#f1f5f9;color:#374151;"><i class="fas fa-pen"></i></button>
                            <form method="POST" action="{{ route('admin.module-data.egrocery.category.destroy',$child->id) }}" onsubmit="return confirm('Delete?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
                @empty
                <tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:32px;">No categories yet. <a href="#" onclick="document.getElementById('addCatModal').style.display='flex'">Add one</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Add Modal --}}
<div id="addCatModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:480px;max-height:90vh;overflow-y:auto;box-shadow:0 25px 60px rgba(0,0,0,.2);">
        <div style="padding:20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
            <div style="font-weight:800;font-size:15px;"><i class="fas fa-folder-plus" style="color:#3b82f6;margin-right:8px;"></i>Add Category</div>
            <button onclick="document.getElementById('addCatModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:18px;color:#9ca3af;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.module-data.egrocery.category.store') }}" enctype="multipart/form-data" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
            @csrf
            <input type="hidden" name="parent_id" id="addParentId" value="">
            <div id="parentLabel" style="background:#eff6ff;padding:8px 12px;border-radius:8px;font-size:12px;color:#3b82f6;display:none;"><i class="fas fa-corner-down-right"></i> Sub-category of: <strong id="parentName"></strong></div>
            <div>
                <label class="form-label">Name (English) *</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div>
                <label class="form-label">Name (Somali)</label>
                <input type="text" name="name_so" class="form-control">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label class="form-label">Icon (emoji)</label>
                    <input type="text" name="icon" class="form-control" placeholder="🥦" maxlength="4">
                </div>
                <div>
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
            </div>
            <div>
                <label class="form-label">Image (optional)</label>
                <input type="file" name="image" class="form-control" accept="image/*">
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="is_active" value="1" checked id="catActive" style="width:16px;height:16px;accent-color:#10b981;">
                <label for="catActive" style="font-size:13px;font-weight:500;">Active</label>
            </div>
            <div style="display:flex;gap:10px;padding-top:4px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Create Category</button>
                <button type="button" onclick="document.getElementById('addCatModal').style.display='none'" class="btn" style="background:#f1f5f9;color:#374151;">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div id="editCatModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:480px;max-height:90vh;overflow-y:auto;box-shadow:0 25px 60px rgba(0,0,0,.2);">
        <div style="padding:20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
            <div style="font-weight:800;font-size:15px;"><i class="fas fa-pen" style="color:#FF8A00;margin-right:8px;"></i>Edit Category</div>
            <button onclick="document.getElementById('editCatModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:18px;color:#9ca3af;">&times;</button>
        </div>
        <form id="editCatForm" method="POST" enctype="multipart/form-data" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
            @csrf
            <div>
                <label class="form-label">Name (English) *</label>
                <input type="text" name="name" id="ecName" class="form-control" required>
            </div>
            <div>
                <label class="form-label">Name (Somali)</label>
                <input type="text" name="name_so" id="ecNameSo" class="form-control">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label class="form-label">Icon (emoji)</label>
                    <input type="text" name="icon" id="ecIcon" class="form-control" maxlength="4">
                </div>
                <div>
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" id="ecSort" class="form-control">
                </div>
            </div>
            <div>
                <label class="form-label">New Image (optional)</label>
                <input type="file" name="image" class="form-control" accept="image/*">
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="is_active" value="1" id="ecActive" style="width:16px;height:16px;accent-color:#10b981;">
                <label for="ecActive" style="font-size:13px;font-weight:500;">Active</label>
            </div>
            <div style="display:flex;gap:10px;padding-top:4px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Save Changes</button>
                <button type="button" onclick="document.getElementById('editCatModal').style.display='none'" class="btn" style="background:#f1f5f9;color:#374151;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const baseUrl   = '{{ route("admin.module-data.egrocery.category.store") }}'.replace('/categories','');

function toggleCat(id, el) {
    fetch(baseUrl + '/categories/' + id + '/toggle', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } })
        .then(r => r.json()).then(d => {
            const span = el.nextElementSibling;
            span.textContent = d.active ? 'Active' : 'Inactive';
            span.style.color = d.active ? '#10b981' : '#9ca3af';
        });
}

function editCat(cat) {
    document.getElementById('ecName').value   = cat.name;
    document.getElementById('ecNameSo').value = cat.name_so || '';
    document.getElementById('ecIcon').value   = cat.icon || '';
    document.getElementById('ecSort').value   = cat.sort_order || 0;
    document.getElementById('ecActive').checked = !!cat.is_active;
    document.getElementById('editCatForm').action = baseUrl + '/categories/' + cat.id;
    document.getElementById('editCatModal').style.display = 'flex';
}

function addSubCat(parentId, parentName) {
    document.getElementById('addParentId').value = parentId;
    document.getElementById('parentLabel').style.display = 'block';
    document.getElementById('parentName').textContent = parentName;
    document.getElementById('addCatModal').style.display = 'flex';
}
</script>
@endsection
