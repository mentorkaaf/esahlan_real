@extends('admin.layouts.app')
@section('title', 'eLearning Reviews')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold" style="font-size:22px">Course Reviews</h2>
      <p class="text-muted mb-0">Monitor and moderate student reviews</p>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
  @endif

  <div class="card">
    <div class="filter-bar">
      <form method="GET" class="d-flex gap-2">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search by course…" class="form-control" style="max-width:260px">
        <button class="btn btn-primary">Search</button>
        @if($search) <a href="{{ route('admin.elearning.reviews') }}" class="btn btn-outline">Reset</a> @endif
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Student</th>
            <th>Course</th>
            <th>Rating</th>
            <th>Comment</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($reviews as $r)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar avatar-sm avatar-orange">{{ substr($r->user?->name ?? '?', 0, 1) }}</div>
                <div class="fw-bold text-sm">{{ $r->user?->name }}</div>
              </div>
            </td>
            <td class="text-sm">{{ Str::limit($r->course?->title ?? '—', 40) }}</td>
            <td>
              @for($i = 1; $i <= 5; $i++)
                <i class="fas fa-star {{ $i <= $r->rating ? 'text-warning' : 'text-muted' }}" style="font-size:11px"></i>
              @endfor
              <span class="text-sm fw-bold">{{ $r->rating }}/5</span>
            </td>
            <td class="text-sm text-muted" style="max-width:200px">{{ Str::limit($r->comment ?? '—', 80) }}</td>
            <td class="text-sm text-muted">{{ $r->created_at->format('d M Y') }}</td>
            <td>
              <form method="POST" action="{{ route('admin.elearning.reviews.destroy', $r->id) }}" style="display:inline">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-sm" onclick="return confirm('Delete this review?')">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6">
              <div class="empty-state"><i class="fas fa-star"></i><h3>No reviews yet</h3></div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($reviews->hasPages())
    <div class="p-4">{{ $reviews->links() }}</div>
    @endif
  </div>
</div>
@endsection
