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

{{-- Edit Modal --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#07003B">
                <h5 class="modal-title text-white fw-bold" id="editModalTitle">Edit Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" id="editMethod" name="_method" value="PUT">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editName" name="name" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Icon <small class="text-muted">(Font Awesome name)</small></label>
                            <div class="input-group">
                                <span class="input-group-text"><i id="iconPreview" class="fas fa-podcast"></i></span>
                                <input type="text" class="form-control" id="editIcon" name="icon"
                                       placeholder="e.g. microphone"
                                       oninput="document.getElementById('iconPreview').className='fas fa-'+this.value">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Color</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="editColorPicker"
                                       oninput="document.getElementById('editColor').value=this.value">
                                <input type="text" class="form-control" id="editColor" name="color"
                                       placeholder="#FF8A00" maxlength="7"
                                       oninput="document.getElementById('editColorPicker').value=this.value">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Cover Image <small class="text-muted">(optional — replaces icon)</small></label>
                        <input type="file" class="form-control" id="editCoverImage" name="cover_image" accept="image/*">
                        <div id="currentCover" class="mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white fw-bold" style="background:#FF8A00">
                        <i class="fas fa-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
const CSRF = '{{ csrf_token() }}';
let editingId = null;
let isCreate  = false;

const editModal = new bootstrap.Modal(document.getElementById('editModal'));

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
            ? `<img src="${cover}" height="40" class="rounded me-2"><small class="text-muted">current image</small>`
            : '';
        editModal.show();
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
    editModal.show();
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
        if (d.success) {
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
            location.reload();
        } else {
            alert(d.message || 'Error saving');
        }
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
