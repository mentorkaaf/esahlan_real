@extends('admin.layouts.app')
@section('title', 'eLearning Categories')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold" style="font-size:22px">Categories</h2>
      <p class="text-muted mb-0">Manage eLearning course categories</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('addCatModal').classList.add('open')">
      <i class="fas fa-plus"></i> Add Category
    </button>
  </div>

  @if(session('success'))
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> {{ session('error') }}</div>
  @endif

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Slug</th>
            <th>Icon</th>
            <th>Courses</th>
            <th>Sort</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($categories as $cat)
          <tr>
            <td>
              <div class="fw-bold text-sm">{{ $cat->name }}</div>
              @if($cat->description)
                <div class="text-muted text-xs truncate" style="max-width:200px">{{ $cat->description }}</div>
              @endif
            </td>
            <td class="text-sm text-muted">{{ $cat->slug }}</td>
            <td><i class="{{ $cat->icon ?? 'fas fa-tag' }}"></i></td>
            <td>{{ $cat->courses_count }}</td>
            <td>{{ $cat->sort_order }}</td>
            <td>
              @if($cat->is_active)
                <span class="badge badge-success">Active</span>
              @else
                <span class="badge badge-secondary">Inactive</span>
              @endif
            </td>
            <td>
              <div class="d-flex gap-2">
                <button class="btn btn-ghost btn-sm" onclick="openEditCat({{ $cat->id }}, '{{ addslashes($cat->name) }}', '{{ addslashes($cat->icon) }}', '{{ addslashes($cat->description ?? '') }}', {{ $cat->sort_order }}, {{ $cat->is_active ? 1 : 0 }})">
                  <i class="fas fa-edit"></i>
                </button>
                <form method="POST" action="{{ route('admin.elearning.categories.destroy', $cat->id) }}" style="display:inline">
                  @csrf @method('DELETE')
                  <button class="btn btn-danger btn-sm" onclick="return confirm('Delete this category?')">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7">
              <div class="empty-state"><i class="fas fa-tags"></i><h3>No categories yet</h3><p>Add your first category to get started.</p></div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Add Category Modal --}}
<div class="modal-overlay" id="addCatModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">Add Category</div>
      <button class="modal-close" onclick="document.getElementById('addCatModal').classList.remove('open')">&times;</button>
    </div>
    <form method="POST" action="{{ route('admin.elearning.categories.store') }}">
      @csrf
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Name *</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Icon (FA class)</label>
          <input type="text" name="icon" class="form-control" placeholder="fas fa-book">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Sort Order</label>
          <input type="number" name="sort_order" class="form-control" value="0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addCatModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Category</button>
      </div>
    </form>
  </div>
</div>

{{-- Edit Category Modal --}}
<div class="modal-overlay" id="editCatModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">Edit Category</div>
      <button class="modal-close" onclick="document.getElementById('editCatModal').classList.remove('open')">&times;</button>
    </div>
    <form method="POST" id="editCatForm">
      @csrf @method('PUT')
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Name *</label>
          <input type="text" name="name" id="editCatName" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Icon (FA class)</label>
          <input type="text" name="icon" id="editCatIcon" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" id="editCatDesc" class="form-control"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Sort Order</label>
          <input type="number" name="sort_order" id="editCatSort" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">
            <input type="checkbox" name="is_active" value="1" id="editCatActive"> Active
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('editCatModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditCat(id, name, icon, desc, sort, active) {
  document.getElementById('editCatForm').action = '/admin/elearning/categories/' + id;
  document.getElementById('editCatName').value = name;
  document.getElementById('editCatIcon').value = icon;
  document.getElementById('editCatDesc').value = desc;
  document.getElementById('editCatSort').value = sort;
  document.getElementById('editCatActive').checked = active === 1;
  document.getElementById('editCatModal').classList.add('open');
}
</script>
@endsection
