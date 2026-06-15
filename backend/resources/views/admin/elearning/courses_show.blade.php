@extends('admin.layouts.app')
@section('title', 'Course: ' . $course->title)

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('admin.elearning.courses') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    <h2 class="fw-bold mb-0" style="font-size:20px">Course Detail</h2>
  </div>

  @if(session('success'))
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
  @endif

  <div class="grid-2 mb-4">
    {{-- Course Info --}}
    <div class="card p-4">
      @if($course->thumbnail)
        <img src="{{ asset('storage/'.$course->thumbnail) }}" style="width:100%;height:180px;object-fit:cover;border-radius:10px;margin-bottom:16px">
      @endif
      <h3 class="fw-bold mb-1">{{ $course->title }}</h3>
      @if($course->subtitle)
        <p class="text-muted text-sm mb-3">{{ $course->subtitle }}</p>
      @endif

      <table class="info-table mb-4">
        <tr><td>Instructor</td><td>{{ $course->instructor?->user?->name ?? '—' }}</td></tr>
        <tr><td>Category</td><td>{{ $course->category?->name ?? '—' }}</td></tr>
        <tr><td>Level</td><td>{{ ucfirst($course->level) }}</td></tr>
        <tr><td>Language</td><td>{{ strtoupper($course->language) }}</td></tr>
        <tr><td>Price</td><td>{{ $course->is_free ? 'Free' : '$'.number_format($course->price,2) }}</td></tr>
        @if($course->discount_price)
        <tr><td>Discount</td><td>${{ number_format($course->discount_price,2) }}</td></tr>
        @endif
        <tr><td>Duration</td><td>{{ $course->duration_hours }} hrs</td></tr>
        <tr><td>Sections</td><td>{{ $course->total_sections }}</td></tr>
        <tr><td>Lessons</td><td>{{ $course->total_lessons }}</td></tr>
        <tr><td>Students</td><td>{{ number_format($course->total_students) }}</td></tr>
        <tr><td>Rating</td><td><i class="fas fa-star text-warning"></i> {{ number_format($course->rating,1) }} ({{ $course->total_reviews }} reviews)</td></tr>
        <tr><td>Commission</td><td>{{ $course->commission_rate }}%</td></tr>
        <tr><td>Status</td><td>
          @php $sc = match($course->status){
            'published'=>'badge-success','pending'=>'badge-warning',
            'draft'=>'badge-secondary','rejected'=>'badge-danger',default=>'badge-secondary'};
          @endphp
          <span class="badge {{ $sc }}">{{ ucfirst($course->status) }}</span>
        </td></tr>
      </table>

      <div class="d-flex gap-2 flex-wrap">
        @if($course->status !== 'published')
          <form method="POST" action="{{ route('admin.elearning.courses.approve', $course->id) }}">
            @csrf <button class="btn btn-success">Approve & Publish</button>
          </form>
        @endif
        @if($course->status !== 'rejected')
          <form method="POST" action="{{ route('admin.elearning.courses.reject', $course->id) }}">
            @csrf <button class="btn btn-danger" onclick="return confirm('Reject this course?')">Reject</button>
          </form>
        @endif
        <form method="POST" action="{{ route('admin.elearning.courses.destroy', $course->id) }}">
          @csrf @method('DELETE')
          <button class="btn" style="background:#7f1d1d;color:#fff;border:none"
            onclick="return confirm('Delete this course permanently? This cannot be undone.')">
            <i class="fas fa-trash me-1"></i> Delete Course
          </button>
        </form>
      </div>
    </div>

    {{-- Curriculum --}}
    <div class="card p-4">
      <h3 class="fw-bold mb-3" style="font-size:15px">Curriculum ({{ $course->sections->count() }} sections)</h3>
      @forelse($course->sections as $section)
      <div class="mb-3">
        <div class="fw-bold text-sm mb-2" style="color:var(--navy)">
          <i class="fas fa-folder text-warning"></i> {{ $section->title }}
        </div>
        @foreach($section->lessons as $lesson)
        <div class="d-flex align-items-center gap-2 mb-1 ps-3">
          @php $ic = match($lesson->type){
            'video'=>'fas fa-play-circle text-info',
            'pdf'=>'fas fa-file-pdf text-danger',
            'quiz'=>'fas fa-question-circle text-purple',
            'assignment'=>'fas fa-tasks text-warning',
            'live'=>'fas fa-video text-success',
            default=>'fas fa-file text-muted'};
          @endphp
          <i class="{{ $ic }}" style="font-size:12px"></i>
          <span class="text-sm">{{ $lesson->title }}</span>
          @if($lesson->is_free_preview)
            <span class="badge badge-teal" style="font-size:9px">Preview</span>
          @endif
          @if($lesson->video_duration_seconds > 0)
            <span class="text-muted text-sm">{{ gmdate('i:s', $lesson->video_duration_seconds) }}</span>
          @endif
        </div>
        @endforeach
      </div>
      @empty
      <div class="empty-state"><i class="fas fa-list"></i><p>No sections added yet</p></div>
      @endforelse

      @if($course->description)
      <div class="mt-4 pt-3" style="border-top:1px solid var(--border)">
        <div class="fw-bold mb-2 text-sm">Description</div>
        <p class="text-sm" style="color:var(--text-muted)">{{ Str::limit($course->description, 400) }}</p>
      </div>
      @endif
    </div>
  </div>
</div>
@endsection
