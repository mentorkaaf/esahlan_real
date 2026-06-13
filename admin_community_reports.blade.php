@extends('layouts.admin')
@section('title', 'Community Reports')
@section('content')
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0 fw-bold text-danger"><i class="fas fa-flag me-2"></i>Reports</h2>
      <p class="text-muted mb-0">Review and action reported content</p>
    </div>
    <a href="{{ route('admin.community.index') }}" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i> Back
    </a>
  </div>

  {{-- Filter tabs --}}
  <ul class="nav nav-pills mb-3">
    @foreach(['pending'=>'Pending','reviewed'=>'Reviewed','dismissed'=>'Dismissed'] as $k=>$v)
    <li class="nav-item">
      <a class="nav-link @if(request('status',$k==='pending'?'pending':null)===$k) active @endif"
         href="{{ request()->fullUrlWithQuery(['status'=>$k]) }}">
        {{ $v }}
        @if($k==='pending' && ($counts['pending']??0) > 0)
          <span class="badge bg-danger ms-1">{{ $counts['pending'] }}</span>
        @endif
      </a>
    </li>
    @endforeach
  </ul>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Reporter</th>
              <th>Content Type</th>
              <th>Reason</th>
              <th>Description</th>
              <th>Reported</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($reports as $report)
            <tr>
              <td>
                <div class="fw-semibold small">{{ $report->reporter?->name ?? 'User' }}</div>
                <div class="text-muted" style="font-size:11px">{{ $report->reporter?->email }}</div>
              </td>
              <td>
                <span class="badge bg-secondary bg-opacity-15 text-secondary">
                  {{ class_basename($report->reportable_type) }} #{{ $report->reportable_id }}
                </span>
              </td>
              <td>
                <span class="badge bg-danger bg-opacity-10 text-danger">{{ ucfirst($report->reason) }}</span>
              </td>
              <td class="small text-muted" style="max-width:180px">
                <span class="text-truncate d-block">{{ $report->description ?? '—' }}</span>
              </td>
              <td class="small text-muted">{{ $report->created_at->diffForHumans() }}</td>
              <td>
                @php $sc = ['pending'=>'warning','reviewed'=>'success','dismissed'=>'secondary'][$report->status ?? 'pending'] @endphp
                <span class="badge bg-{{ $sc }}">{{ ucfirst($report->status ?? 'pending') }}</span>
              </td>
              <td>
                <div class="d-flex gap-1">
                  <form method="POST" action="{{ route('admin.community.reports.action', $report->id) }}">
                    @csrf
                    <input type="hidden" name="action" value="reviewed">
                    <button class="btn btn-sm btn-outline-success py-0" title="Mark Reviewed">✓</button>
                  </form>
                  <form method="POST" action="{{ route('admin.community.reports.action', $report->id) }}">
                    @csrf
                    <input type="hidden" name="action" value="dismissed">
                    <button class="btn btn-sm btn-outline-secondary py-0" title="Dismiss">✗</button>
                  </form>
                  @if($report->reportable_type === 'post')
                  <form method="POST" action="{{ route('admin.community.posts.delete', $report->reportable_id) }}" onsubmit="return confirm('Delete reported content?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger py-0" title="Delete Content"><i class="fas fa-trash" style="font-size:11px"></i></button>
                  </form>
                  @endif
                </div>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-5">
                <i class="fas fa-check-circle text-success fs-3 d-block mb-2"></i>
                No reports found
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($reports->hasPages())
    <div class="card-footer bg-white border-0">{{ $reports->withQueryString()->links() }}</div>
    @endif
  </div>
</div>
@endsection
