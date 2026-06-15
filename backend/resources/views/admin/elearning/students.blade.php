@extends('admin.layouts.app')
@section('title', 'eLearning Students')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold" style="font-size:22px">Students</h2>
      <p class="text-muted mb-0">All course enrollments</p>
    </div>
  </div>

  <div class="card">
    <div class="filter-bar">
      <form method="GET" class="d-flex gap-2 flex-wrap">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search student…" class="form-control" style="max-width:260px">
        <button class="btn btn-primary">Search</button>
        @if($search) <a href="{{ route('admin.elearning.students') }}" class="btn btn-outline">Reset</a> @endif
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Student</th>
            <th>Course</th>
            <th>Amount Paid</th>
            <th>Status</th>
            <th>Enrolled</th>
            <th>Completed</th>
          </tr>
        </thead>
        <tbody>
          @forelse($enrollments as $e)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar avatar-sm avatar-blue">{{ substr($e->user?->name ?? '?', 0, 1) }}</div>
                <div>
                  <div class="fw-bold text-sm">{{ $e->user?->name }}</div>
                  <div class="text-muted text-xs">{{ $e->user?->email }}</div>
                </div>
              </div>
            </td>
            <td class="text-sm">{{ Str::limit($e->course?->title ?? '—', 45) }}</td>
            <td>${{ number_format($e->amount_paid, 2) }}</td>
            <td>
              @if($e->status === 'completed')
                <span class="badge badge-success">Completed</span>
              @elseif($e->status === 'active')
                <span class="badge badge-info">Active</span>
              @else
                <span class="badge badge-danger">{{ ucfirst($e->status) }}</span>
              @endif
            </td>
            <td class="text-sm text-muted">{{ $e->created_at->format('d M Y') }}</td>
            <td class="text-sm text-muted">{{ $e->completed_at?->format('d M Y') ?? '—' }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="6">
              <div class="empty-state"><i class="fas fa-user-graduate"></i><h3>No students yet</h3></div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($enrollments->hasPages())
    <div class="p-4">{{ $enrollments->links() }}</div>
    @endif
  </div>
</div>
@endsection
