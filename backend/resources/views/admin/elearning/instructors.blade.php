@extends('admin.layouts.app')
@section('title', 'eLearning Instructors')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold" style="font-size:22px">Instructors</h2>
      <p class="text-muted mb-0">Manage instructor accounts and verifications</p>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
  @endif

  {{-- Filter Bar --}}
  <div class="card mb-4">
    <div class="filter-bar">
      <form method="GET" class="d-flex gap-2 flex-wrap" style="width:100%">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search by name or email…" class="form-control" style="max-width:260px">
        <select name="filter" class="form-control" style="width:auto" onchange="this.form.submit()">
          <option value="all" {{ $filter==='all' ? 'selected' : '' }}>All Status</option>
          <option value="pending"  {{ $filter==='pending'  ? 'selected' : '' }}>Pending</option>
          <option value="approved" {{ $filter==='approved' ? 'selected' : '' }}>Approved</option>
          <option value="rejected" {{ $filter==='rejected' ? 'selected' : '' }}>Rejected</option>
        </select>
        <button class="btn btn-primary">Search</button>
        @if($search || $filter!=='all')
          <a href="{{ route('admin.elearning.instructors') }}" class="btn btn-outline">Reset</a>
        @endif
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Instructor</th>
            <th>Expertise</th>
            <th>Courses</th>
            <th>Students</th>
            <th>Rating</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($instructors as $inst)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                @if($inst->profile_photo)
                  <img src="{{ asset('storage/'.$inst->profile_photo) }}" style="width:36px;height:36px;border-radius:9px;object-fit:cover">
                @else
                  <div class="avatar avatar-sm avatar-purple">{{ substr($inst->user?->name ?? '?', 0, 1) }}</div>
                @endif
                <div>
                  <div class="fw-bold" style="font-size:13px">{{ $inst->user?->name }}</div>
                  <div class="text-muted text-sm">{{ $inst->user?->email }}</div>
                </div>
              </div>
            </td>
            <td class="text-sm">{{ $inst->expertise ?? '—' }}</td>
            <td>{{ $inst->total_courses }}</td>
            <td>{{ number_format($inst->total_students) }}</td>
            <td>
              <span class="text-warning"><i class="fas fa-star"></i></span>
              {{ number_format($inst->rating, 1) }}
            </td>
            <td>
              @if($inst->verification_status === 'approved')
                <span class="badge badge-success"><i class="fas fa-check"></i> Approved</span>
              @elseif($inst->verification_status === 'pending')
                <span class="badge badge-warning"><i class="fas fa-clock"></i> Pending</span>
              @else
                <span class="badge badge-danger"><i class="fas fa-times"></i> Rejected</span>
              @endif
            </td>
            <td class="text-sm text-muted">{{ $inst->created_at->format('d M Y') }}</td>
            <td>
              <div class="d-flex gap-2">
                <a href="{{ route('admin.elearning.instructors.show', $inst->id) }}" class="btn btn-ghost btn-sm">
                  <i class="fas fa-eye"></i>
                </a>
                @if($inst->verification_status !== 'approved')
                  <form method="POST" action="{{ route('admin.elearning.instructors.approve', $inst->id) }}" style="display:inline">
                    @csrf
                    <button class="btn btn-success btn-sm" onclick="return confirm('Approve this instructor?')">
                      <i class="fas fa-check"></i>
                    </button>
                  </form>
                @endif
                @if($inst->verification_status !== 'rejected')
                  <form method="POST" action="{{ route('admin.elearning.instructors.reject', $inst->id) }}" style="display:inline">
                    @csrf
                    <button class="btn btn-danger btn-sm" onclick="return confirm('Reject this instructor?')">
                      <i class="fas fa-times"></i>
                    </button>
                  </form>
                @endif
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="8">
              <div class="empty-state"><i class="fas fa-chalkboard-teacher"></i><h3>No instructors found</h3><p>No instructors match your filters.</p></div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($instructors->hasPages())
    <div class="p-4">{{ $instructors->links() }}</div>
    @endif
  </div>
</div>
@endsection
