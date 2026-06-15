@extends('admin.layouts.app')
@section('title', 'eLearning Certificates')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold" style="font-size:22px">Certificates</h2>
      <p class="text-muted mb-0">Issued completion certificates</p>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
  @endif

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Certificate #</th>
            <th>Student</th>
            <th>Course</th>
            <th>Issued</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($certs as $cert)
          <tr>
            <td>
              <span class="badge badge-purple">{{ $cert->certificate_number }}</span>
            </td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar avatar-sm avatar-green">{{ substr($cert->user?->name ?? '?', 0, 1) }}</div>
                <div>
                  <div class="fw-bold text-sm">{{ $cert->user?->name }}</div>
                  <div class="text-muted text-xs">{{ $cert->user?->email }}</div>
                </div>
              </div>
            </td>
            <td class="text-sm">{{ Str::limit($cert->course?->title ?? '—', 50) }}</td>
            <td class="text-sm text-muted">{{ $cert->issued_at?->format('d M Y') }}</td>
            <td>
              <form method="POST" action="{{ route('admin.elearning.certificates.revoke', $cert->id) }}" style="display:inline">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-sm" onclick="return confirm('Revoke this certificate?')">
                  <i class="fas fa-ban"></i> Revoke
                </button>
              </form>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="5">
              <div class="empty-state"><i class="fas fa-certificate"></i><h3>No certificates yet</h3></div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($certs->hasPages())
    <div class="p-4">{{ $certs->links() }}</div>
    @endif
  </div>
</div>
@endsection
