@extends('admin.layouts.app')
@section('title', 'Podcast Categories')

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('admin.podcast.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back
            </a>
            <h4 class="mb-0 fw-bold">Podcast Categories</h4>
            <span class="badge bg-primary">{{ $categories->count() }}</span>
        </div>
        <button class="btn btn-sm text-white" style="background:#FF8A00"
                onclick="openCreateModal()">
            <i class="fas fa-plus me-1"></i>New Category
        </button>
    </div>

    {{-- Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px">#</th>
                            <th style="width:70px">Icon</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Color</th>
                            <th>Podcasts</th>
                            <th>Sort</th>
                            <th style="width:80px">Status</th>
                            <th style="width:140px">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="catTableBody">
                        @foreach($categories as $cat)
                        <tr id="cat-row-{{ $cat->id }}" class="{{ $cat->is_active ? '' : 'opacity-50' }}">
                            <td class="text-muted small">{{ $cat->id }}</td>
                            <td>
                                @if($cat->cover_image)
                                    <img src="{{ asset('storage/'.$cat->cover_image) }}"
                                         width="36" height="36" class="rounded"
                                         style="object-fit:cover">
                                @else
                                    <div class="rounded d-flex align-items-center justify-content-center"
                                         style="width:36px;height:36px;background:{{ $cat->color ?? '#FF8A00' }}20">
                                        <i class="fas fa-{{ $cat->icon ?? 'podcast' }}"
                                           style="color:{{ $cat->color ?? '#FF8A00' }}"></i>
                                    </div>
                                @endif
                            </td>
                            <td><span class="fw-semibold">{{ $cat->name }}</span></td>
                            <td><code class="small">{{ $cat->slug }}</code></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle border"
                                         style="width:18px;height:18px;background:{{ $cat->color ?? '#FF8A00' }}"></div>
                                    <code class="small">{{ $cat->color ?? '—' }}</code>
                                </div>
                            </td>
                            <td>{{ $cat->podcasts_count ?? 0 }}</td>
                            <td>{{ $cat->sort_order }}</td>
                            <td>
                                @if($cat->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary btn-edit" title="Edit"
                                            data-id="{{ $cat->id }}"
                                            data-name="{{ e($cat->name) }}"
                                            data-icon="{{ e($cat->icon) }}"
                                            data-color="{{ e($cat->color) }}"
                                            data-cover="{{ $cat->cover_image ? asset('storage/'.$cat->cover_image) : '' }}">
                                        <i class="fas fa-pencil"></i>
                                    </button>
                                    <button class="btn {{ $cat->is_active ? "btn-outline-warning" : "btn-outline-success" }}"
                                            title="{{ $cat->is_active ? 'Disable' : 'Enable' }}"
                                            onclick="toggleStatus({{ $cat->id }}, this)">
                                        <i class="fas fa-{{ $cat->is_active ? 'eye-slash' : 'eye' }}"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" title="Delete"
                                            onclick="deleteCategory({{ $cat->id }}, '{{ addslashes($cat->name) }}')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Custom Popup Modal --}}
<div id="catModalOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;width:100%;max-width:480px;margin:0 16px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;">
        <div style="background:#07003B;padding:18px 22px;display:flex;align-items:center;justify-content:space-between;">
            <h5 id="editModalTitle" style="color:#fff;margin:0;font-weight:800;font-size:16px;">Edit Category</h5>
            <button onclick="closeModal()" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;">&times;</button>
        </div>
        <form id="editForm" enctype="multipart/form-data" style="padding:22px;">
            @csrf
            <input type="hidden" id="editMethod" name="_method" value="PUT">

            <div style="margin-bottom:16px;">
                <label style="display:block;font-weight:700;font-size:13px;color:#07003B;margin-bottom:6px;">Name <span style="color:red">*</span></label>
                <input type="text" id="editName" name="name" required
                       style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                <div>
                    <label style="display:block;font-weight:700;font-size:13px;color:#07003B;margin-bottom:6px;">Icon <span style="font-weight:400;color:#888;">(FA name)</span></label>
                    <div style="display:flex;align-items:center;border:1.5px solid #e0e0e0;border-radius:8px;overflow:hidden;">
                        <span style="padding:0 12px;background:#f5f5f5;height:40px;display:flex;align-items:center;">
                            <i id="iconPreview" class="fas fa-podcast" style="color:#FF8A00;"></i>
                        </span>
                        <input type="text" id="editIcon" name="icon" placeholder="e.g. microphone"
                               style="flex:1;padding:10px 12px;border:none;font-size:13px;outline:none;"
                               oninput="document.getElementById('iconPreview').className='fas fa-'+this.value">
                    </div>
                </div>
                <div>
                    <label style="display:block;font-weight:700;font-size:13px;color:#07003B;margin-bottom:6px;">Color</label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="color" id="editColorPicker" value="#FF8A00"
                               style="width:40px;height:40px;border:none;border-radius:6px;cursor:pointer;padding:2px;"
                               oninput="document.getElementById('editColor').value=this.value">
                        <input type="text" id="editColor" name="color" placeholder="#FF8A00" maxlength="7"
                               style="flex:1;padding:10px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:13px;outline:none;"
                               oninput="if(this.value.length===7)document.getElementById('editColorPicker').value=this.value">
                    </div>
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block;font-weight:700;font-size:13px;color:#07003B;margin-bottom:6px;">Cover Image <span style="font-weight:400;color:#888;">(optional)</span></label>
                <input type="file" id="editCoverImage" name="cover_image" accept="image/*"
                       style="width:100%;padding:8px;border:1.5px dashed #e0e0e0;border-radius:8px;font-size:13px;box-sizing:border-box;">
                <div id="currentCover" style="margin-top:8px;"></div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="closeModal()"
                        style="padding:10px 22px;border:1.5px solid #ccc;background:#fff;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;">
                    Cancel
                </button>
                <button type="submit"
                        style="padding:10px 24px;background:#FF8A00;color:#fff;border:none;border-radius:8px;font-weight:800;cursor:pointer;font-size:14px;">
                    <i class="fas fa-save" style="margin-right:6px;"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
const CSRF = '{{ csrf_token() }}';
let editingId = null;
let isCreate  = false;

const overlay = document.getElementById('catModalOverlay');

function showModal() { overlay.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
function closeModal() { overlay.style.display = 'none'; document.body.style.overflow = ''; }
overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });

document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function() {
        isCreate  = false;
        editingId = this.dataset.id;
        document.getElementById('editModalTitle').textContent = 'Edit Category';
        document.getElementById('editName').value        = this.dataset.name  || '';
        document.getElementById('editIcon').value        = this.dataset.icon  || '';
        document.getElementById('editColor').value       = this.dataset.color || '';
        document.getElementById('editColorPicker').value = this.dataset.color || '#FF8A00';
        document.getElementById('iconPreview').className = 'fas fa-' + (this.dataset.icon || 'podcast');
        document.getElementById('editMethod').value      = 'PUT';
        const cover = this.dataset.cover;
        document.getElementById('currentCover').innerHTML = cover
            ? `<img src="${cover}" height="40" style="border-radius:6px;margin-right:8px;"><small style="color:#888;">current image</small>`
            : '';
        showModal();
    });
});

function openCreateModal() {
    isCreate = true;
    editingId = null;
    document.getElementById('editModalTitle').textContent = 'New Category';
    document.getElementById('editForm').reset();
    document.getElementById('iconPreview').className = 'fas fa-podcast';
    document.getElementById('currentCover').innerHTML = '';
    document.getElementById('editMethod').value = 'POST';
    showModal();
}

document.getElementById('editForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd  = new FormData(this);
    const url = isCreate
        ? '/admin/podcast/categories'
        : `/admin/podcast/categories/${editingId}`;

    if (!isCreate) fd.append('_method', 'PUT');

    try {
        const r = await fetch(url, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'},
            body: fd,
        });
        const d = await r.json();
        if (d.success) { closeModal(); location.reload(); }
        else alert(d.message || 'Error saving');
    } catch (err) { alert('Network error'); }
});

async function toggleStatus(id, btn) {
    const r = await fetch(`/admin/podcast/categories/${id}/toggle`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'}
    });
    const d = await r.json();
    if (!d.success) return;
    const row = document.getElementById(`cat-row-${id}`);
    const badge = row.querySelector('.badge');
    if (d.is_active) {
        row.classList.remove('opacity-50');
        badge.className = 'badge bg-success';
        badge.textContent = 'Active';
        btn.className = 'btn btn-outline-warning btn-sm';
        btn.title = 'Disable';
        btn.querySelector('i').className = 'fas fa-eye-slash';
    } else {
        row.classList.add('opacity-50');
        badge.className = 'badge bg-secondary';
        badge.textContent = 'Inactive';
        btn.className = 'btn btn-outline-success btn-sm';
        btn.title = 'Enable';
        btn.querySelector('i').className = 'fas fa-eye';
    }
}

async function deleteCategory(id, name) {
    if (!confirm(`Delete category "${name}"?\nThis will fail if it has podcasts.`)) return;
    const r = await fetch(`/admin/podcast/categories/${id}`, {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'}
    });
    const d = await r.json();
    if (d.success) {
        document.getElementById(`cat-row-${id}`).remove();
    } else {
        alert(d.message || 'Cannot delete this category');
    }
}
</script>
@endpush
