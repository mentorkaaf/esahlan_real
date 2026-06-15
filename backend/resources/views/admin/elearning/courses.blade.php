@extends('admin.layouts.app')
@section('title', 'eLearning Courses')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold" style="font-size:22px">Courses</h2>
      <p class="text-muted mb-0">Manage and review submitted courses</p>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
  @endif

  <div class="card mb-4">
    <div class="filter-bar">
      <form method="GET" class="d-flex gap-2 flex-wrap" style="width:100%">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search courses…" class="form-control" style="max-width:260px">
        <select name="filter" class="form-control" style="width:auto" onchange="this.form.submit()">
          <option value="all" {{ $filter==='all' ? 'selected' : '' }}>All Status</option>
          <option value="pending"   {{ $filter==='pending'   ? 'selected' : '' }}>Pending Review</option>
          <option value="published" {{ $filter==='published' ? 'selected' : '' }}>Published</option>
          <option value="draft"     {{ $filter==='draft'     ? 'selected' : '' }}>Draft</option>
          <option value="rejected"  {{ $filter==='rejected'  ? 'selected' : '' }}>Rejected</option>
          <option value="archived"  {{ $filter==='archived'  ? 'selected' : '' }}>Archived</option>
        </select>
        <button class="btn btn-primary">Search</button>
        @if($search || $filter!=='all')
          <a href="{{ route('admin.elearning.courses') }}" class="btn btn-outline">Reset</a>
        @endif
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Course</th>
            <th>Instructor</th>
            <th>Category</th>
            <th>Price</th>
            <th>Students</th>
            <th>Rating</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($courses as $c)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                @if($c->thumbnail)
                  <img src="{{ asset('storage/'.$c->thumbnail) }}" style="width:44px;height:44px;border-radius:8px;object-fit:cover">
                @else
                  <div class="avatar avatar-sm avatar-blue">{{ substr($c->title,0,1) }}</div>
                @endif
                <div>
                  <div class="fw-bold" style="font-size:13px;max-width:180px" class="truncate">{{ Str::limit($c->title, 40) }}</div>
                  <div class="text-muted text-sm">{{ $c->level }}</div>
                </div>
              </div>
            </td>
            <td class="text-sm">{{ $c->instructor?->user?->name ?? '—' }}</td>
            <td class="text-sm">{{ $c->category?->name ?? '—' }}</td>
            <td>
              @if($c->is_free)
                <span class="badge badge-success">Free</span>
              @else
                ${{ number_format($c->price, 2) }}
                @if($c->discount_price)
                  <br><span class="text-sm text-muted line-through">${{ number_format($c->discount_price, 2) }}</span>
                @endif
              @endif
            </td>
            <td>{{ number_format($c->total_students) }}</td>
            <td><i class="fas fa-star text-warning"></i> {{ number_format($c->rating, 1) }}</td>
            <td>
              @php
                $sc = match($c->status) {
                  'published' => 'badge-success',
                  'pending'   => 'badge-warning',
                  'draft'     => 'badge-secondary',
                  'rejected'  => 'badge-danger',
                  'archived'  => 'badge-dark',
                  default     => 'badge-secondary',
                };
              @endphp
              <span class="badge {{ $sc }}">{{ ucfirst($c->status) }}</span>
            </td>
            <td>
              <div class="d-flex gap-2">
                <a href="{{ route('admin.elearning.courses.show', $c->id) }}" class="btn btn-ghost btn-sm">
                  <i class="fas fa-eye"></i>
                </a>
                @if($c->status !== 'published')
                  <form method="POST" action="{{ route('admin.elearning.courses.approve', $c->id) }}" style="display:inline">
                    @csrf
                    <button class="btn btn-success btn-sm" title="Approve"><i class="fas fa-check"></i></button>
                  </form>
                @endif
                @if($c->status !== 'rejected')
                  <form method="POST" action="{{ route('admin.elearning.courses.reject', $c->id) }}" style="display:inline">
                    @csrf
                    <button class="btn btn-danger btn-sm" title="Reject" onclick="return confirm('Reject this course?')"><i class="fas fa-times"></i></button>
                  </form>
                @endif
                <form method="POST" action="{{ route('admin.elearning.courses.destroy', $c->id) }}" style="display:inline">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm" style="background:#7f1d1d;color:#fff;border:none" title="Delete permanently"
                    onclick="return confirm('Delete this course permanently? This cannot be undone.')">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="8">
              <div class="empty-state"><i class="fas fa-book-open"></i><h3>No courses found</h3></div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($courses->hasPages())
    <div class="p-4">{{ $courses->links() }}</div>
    @endif
  </div>
</div>
@endsection
