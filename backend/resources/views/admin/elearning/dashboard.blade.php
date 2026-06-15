@extends('admin.layouts.app')
@section('title', 'eLearning Dashboard')

@section('content')
<div class="container-fluid py-4">

  {{-- Header --}}
  <div class="d-flex justify-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold" style="font-size:22px">eLearning Dashboard</h2>
      <p class="text-muted mb-0">Overview of courses, instructors and student activity</p>
    </div>
    <a href="{{ route('admin.elearning.reports') }}" class="btn btn-outline">
      <i class="fas fa-chart-line"></i> View Reports
    </a>
  </div>

  @if(session('success'))
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
  @endif

  {{-- Stat Cards --}}
  <div class="grid-4 mb-4">
    <div class="stat-card orange">
      <div class="stat-icon-wrap orange"><i class="fas fa-chalkboard-teacher"></i></div>
      <div class="stat-body">
        <div class="stat-value">{{ number_format($stats['total_instructors']) }}</div>
        <div class="stat-label">Active Instructors</div>
        @if($stats['pending_instructors'] > 0)
          <div class="stat-sub warn"><i class="fas fa-clock"></i> {{ $stats['pending_instructors'] }} pending</div>
        @endif
      </div>
    </div>
    <div class="stat-card blue">
      <div class="stat-icon-wrap blue"><i class="fas fa-book-open"></i></div>
      <div class="stat-body">
        <div class="stat-value">{{ number_format($stats['total_courses']) }}</div>
        <div class="stat-label">Published Courses</div>
        @if($stats['pending_courses'] > 0)
          <div class="stat-sub warn"><i class="fas fa-clock"></i> {{ $stats['pending_courses'] }} pending review</div>
        @endif
      </div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon-wrap green"><i class="fas fa-user-graduate"></i></div>
      <div class="stat-body">
        <div class="stat-value">{{ number_format($stats['total_students']) }}</div>
        <div class="stat-label">Total Students</div>
        <div class="stat-sub muted"><i class="fas fa-ticket-alt"></i> {{ number_format($stats['total_enrollments']) }} enrollments</div>
      </div>
    </div>
    <div class="stat-card purple">
      <div class="stat-icon-wrap purple"><i class="fas fa-dollar-sign"></i></div>
      <div class="stat-body">
        <div class="stat-value">${{ number_format($stats['total_revenue'], 2) }}</div>
        <div class="stat-label">Total Revenue</div>
        @if($stats['pending_withdrawals'] > 0)
          <div class="stat-sub warn"><i class="fas fa-money-bill"></i> {{ $stats['pending_withdrawals'] }} pending withdrawals</div>
        @endif
      </div>
    </div>
  </div>

  <div class="grid-2">
    {{-- Recent Enrollments --}}
    <div class="card p-4">
      <div class="d-flex justify-between align-items-center mb-3">
        <h3 style="font-size:15px;font-weight:700">Recent Enrollments</h3>
        <a href="{{ route('admin.elearning.students') }}" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Student</th>
              <th>Course</th>
              <th>Amount</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            @forelse($recentEnrollments as $e)
            <tr>
              <td>{{ $e->user?->name ?? '—' }}</td>
              <td class="truncate" style="max-width:160px">{{ $e->course?->title ?? '—' }}</td>
              <td>${{ number_format($e->amount_paid, 2) }}</td>
              <td class="text-muted text-sm">{{ $e->created_at->diffForHumans() }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted py-4">No enrollments yet</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Top Courses --}}
    <div class="card p-4">
      <div class="d-flex justify-between align-items-center mb-3">
        <h3 style="font-size:15px;font-weight:700">Top Courses by Students</h3>
        <a href="{{ route('admin.elearning.courses') }}" class="btn btn-ghost btn-sm">View All</a>
      </div>
      @forelse($topCourses as $c)
      <div class="d-flex align-items-center gap-3 mb-3 pb-3" style="border-bottom:1px solid var(--border)">
        @if($c->thumbnail)
          <img src="{{ asset('storage/'.$c->thumbnail) }}" style="width:48px;height:48px;border-radius:8px;object-fit:cover">
        @else
          <div class="avatar avatar-md avatar-orange">{{ substr($c->title,0,1) }}</div>
        @endif
        <div class="flex-1 min-width-0">
          <div class="fw-bold truncate" style="font-size:13px">{{ $c->title }}</div>
          <div class="text-muted text-sm">{{ $c->instructor?->user?->name ?? '—' }}</div>
        </div>
        <div class="text-right">
          <div class="fw-bold text-sm">{{ number_format($c->total_students) }}</div>
          <div class="text-muted" style="font-size:11px">students</div>
        </div>
      </div>
      @empty
      <div class="empty-state"><i class="fas fa-book-open"></i><p>No courses yet</p></div>
      @endforelse
    </div>
  </div>

</div>
@endsection
