@extends('layouts.admin')
@section('title', 'Community Posts')
@section('content')
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0 fw-bold">Community Posts</h2>
      <p class="text-muted mb-0">Moderate and manage all community posts</p>
    </div>
    <a href="{{ route('admin.community.index') }}" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i> Back
    </a>
  </div>

  {{-- Filters --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label small fw-semibold">Search</label>
          <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search posts...">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Type</label>
          <select name="type" class="form-select">
            <option value="">All Types</option>
            @foreach(['text','image','video','reel','poll'] as $t)
              <option value="{{ $t }}" @selected(request('type') === $t)>{{ ucfirst($t) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">Status</label>
          <select name="status" class="form-select">
            <option value="">All</option>
            <option value="reported" @selected(request('status')==='reported')>Reported</option>
            <option value="active" @selected(request('status')==='active')>Active</option>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Author</th>
              <th>Content</th>
              <th>Type</th>
              <th>Privacy</th>
              <th>Likes</th>
              <th>Comments</th>
              <th>Reports</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($posts as $post)
            <tr>
              <td class="text-muted small">{{ $post->id }}</td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  @if($post->user?->profile_photo_path)
                    <img src="{{ $post->user->profile_photo_path }}" class="rounded-circle" width="36" height="36" style="object-fit:cover">
                  @else
                    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:36px;height:36px;font-weight:700">
                      {{ strtoupper(substr($post->user?->name ?? '?', 0, 1)) }}
                    </div>
                  @endif
                  <div>
                    <div class="fw-semibold small">{{ $post->user?->name }}</div>
                    <div class="text-muted" style="font-size:11px">{{ $post->user?->email }}</div>
                  </div>
                </div>
              </td>
              <td style="max-width:220px">
                <p class="mb-0 small text-truncate">{{ $post->content ?? '—' }}</p>
                @if($post->media->count())
                  <span class="badge bg-info bg-opacity-10 text-info small">{{ $post->media->count() }} media</span>
                @endif
              </td>
              <td><span class="badge bg-secondary bg-opacity-15 text-secondary">{{ $post->type }}</span></td>
              <td><span class="badge bg-light text-dark border">{{ $post->privacy }}</span></td>
              <td class="fw-semibold">{{ $post->likes_count }}</td>
              <td class="fw-semibold">{{ $post->comments_count }}</td>
              <td>
                @if($post->reports_count > 0)
                  <span class="badge bg-danger">{{ $post->reports_count }}</span>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="small text-muted">{{ $post->created_at->format('d M Y') }}</td>
              <td>
                <div class="d-flex gap-1">
                  <button class="btn btn-sm btn-outline-secondary py-0" onclick="viewPost({{ $post->id }})">
                    <i class="fas fa-eye"></i>
                  </button>
                  <form method="POST" action="{{ route('admin.community.posts.delete', $post->id) }}" onsubmit="return confirm('Delete this post?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger py-0"><i class="fas fa-trash"></i></button>
                  </form>
                </div>
              </td>
            </tr>
            @empty
            <tr><td colspan="10" class="text-center text-muted py-5">No posts found</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($posts->hasPages())
    <div class="card-footer bg-white border-0">
      {{ $posts->withQueryString()->links() }}
    </div>
    @endif
  </div>
</div>
@endsection
