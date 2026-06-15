@extends('admin.layouts.app')
@section('title', 'Instructor: ' . ($instructor->user?->name ?? ''))

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('admin.elearning.instructors') }}" class="btn btn-outline btn-sm">
      <i class="fas fa-arrow-left"></i> Back
    </a>
    <h2 class="fw-bold mb-0" style="font-size:20px">Instructor Profile</h2>
  </div>

  @if(session('success'))
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
  @endif

  <div class="grid-2 mb-4">
    <div class="card p-4">
      <div class="d-flex align-items-center gap-4 mb-4">
        @if($instructor->profile_photo)
          <img src="{{ asset('storage/'.$instructor->profile_photo) }}" style="width:72px;height:72px;border-radius:14px;object-fit:cover">
        @else
          <div class="avatar avatar-lg avatar-purple">{{ substr($instructor->user?->name ?? '?', 0, 1) }}</div>
        @endif
        <div>
          <h3 class="fw-bold mb-1">{{ $instructor->user?->name }}</h3>
          <div class="text-muted text-sm">{{ $instructor->user?->email }}</div>
          <div class="mt-2">
            @if($instructor->verification_status === 'approved')
              <span class="badge badge-success"><i class="fas fa-check"></i> Approved</span>
            @elseif($instructor->verification_status === 'pending')
              <span class="badge badge-warning"><i class="fas fa-clock"></i> Pending</span>
            @else
              <span class="badge badge-danger">Rejected</span>
            @endif
          </div>
        </div>
      </div>

      <table class="info-table">
        <tr><td>Expertise</td><td>{{ $instructor->expertise ?? '—' }}</td></tr>
        <tr><td>Experience</td><td>{{ $instructor->experience_years }} years</td></tr>
        <tr><td>Total Courses</td><td>{{ $instructor->total_courses }}</td></tr>
        <tr><td>Total Students</td><td>{{ number_format($instructor->total_students) }}</td></tr>
        <tr><td>Rating</td><td><i class="fas fa-star text-warning"></i> {{ number_format($instructor->rating, 1) }}</td></tr>
        <tr><td>Total Earnings</td><td>${{ number_format($instructor->total_earnings, 2) }}</td></tr>
        <tr><td>Member Since</td><td>{{ $instructor->created_at->format('d M Y') }}</td></tr>
      </table>

      @if($instructor->bio)
      <div class="mt-4">
        <div class="fw-bold mb-2 text-sm">Bio</div>
        <p class="text-sm" style="color:var(--text-muted)">{{ $instructor->bio }}</p>
      </div>
      @endif

      <div class="d-flex gap-2 mt-4">
        @if($instructor->verification_status !== 'approved')
          <form method="POST" action="{{ route('admin.elearning.instructors.approve', $instructor->id) }}">
            @csrf
            <button class="btn btn-success">Approve Instructor</button>
          </form>
        @endif
        @if($instructor->verification_status !== 'rejected')
          <form method="POST" action="{{ route('admin.elearning.instructors.reject', $instructor->id) }}">
            @csrf
            <button class="btn btn-danger" onclick="return confirm('Reject this instructor?')">Reject</button>
          </form>
        @endif
      </div>
    </div>

    <div class="card p-4">
      <h3 class="fw-bold mb-3" style="font-size:15px">Their Courses</h3>
      @forelse($instructor->courses as $c)
      <div class="d-flex align-items-center gap-3 mb-3 pb-3" style="border-bottom:1px solid var(--border)">
        @if($c->thumbnail)
          <img src="{{ asset('storage/'.$c->thumbnail) }}" style="width:48px;height:48px;border-radius:8px;object-fit:cover">
        @else
          <div class="avatar avatar-md avatar-blue">{{ substr($c->title,0,1) }}</div>
        @endif
        <div class="flex-1 min-width-0">
          <div class="fw-bold truncate text-sm">{{ $c->title }}</div>
          <div class="text-muted text-sm">{{ $c->total_students }} students · ${{ number_format($c->price, 2) }}</div>
        </div>
        <div>
          @if($c->status === 'published')
            <span class="badge badge-success">Published</span>
          @elseif($c->status === 'pending')
            <span class="badge badge-warning">Pending</span>
          @else
            <span class="badge badge-secondary">{{ ucfirst($c->status) }}</span>
          @endif
        </div>
      </div>
      @empty
      <div class="empty-state"><i class="fas fa-book-open"></i><p>No courses yet</p></div>
      @endforelse
    </div>
  </div>
</div>
@endsection
