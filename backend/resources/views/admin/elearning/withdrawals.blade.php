@extends('admin.layouts.app')
@section('title', 'Instructor Withdrawals')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold" style="font-size:22px">Instructor Withdrawals</h2>
      <p class="text-muted mb-0">Manage payout requests from instructors</p>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
  @endif

  <div class="card">
    <div class="filter-bar">
      <form method="GET" class="d-flex gap-2">
        <select name="filter" class="form-control" style="width:auto" onchange="this.form.submit()">
          <option value="all"      {{ $filter==='all'      ? 'selected' : '' }}>All Status</option>
          <option value="pending"  {{ $filter==='pending'  ? 'selected' : '' }}>Pending</option>
          <option value="approved" {{ $filter==='approved' ? 'selected' : '' }}>Approved</option>
          <option value="rejected" {{ $filter==='rejected' ? 'selected' : '' }}>Rejected</option>
          <option value="paid"     {{ $filter==='paid'     ? 'selected' : '' }}>Paid</option>
        </select>
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Instructor</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Requested</th>
            <th>Processed</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($withdrawals as $w)
          <tr>
            <td>
              <div class="fw-bold text-sm">{{ $w->instructor?->user?->name ?? '—' }}</div>
              <div class="text-muted text-xs">{{ $w->instructor?->user?->email }}</div>
            </td>
            <td class="fw-bold">${{ number_format($w->amount, 2) }}</td>
            <td>
              @php $bc = match($w->status){
                'pending'=>'badge-warning','approved'=>'badge-info',
                'paid'=>'badge-success','rejected'=>'badge-danger',default=>'badge-secondary'};
              @endphp
              <span class="badge {{ $bc }}">{{ ucfirst($w->status) }}</span>
            </td>
            <td class="text-sm text-muted">{{ $w->created_at->format('d M Y') }}</td>
            <td class="text-sm text-muted">{{ $w->processed_at?->format('d M Y') ?? '—' }}</td>
            <td>
              @if($w->status === 'pending')
              <div class="d-flex gap-2">
                <form method="POST" action="{{ route('admin.elearning.withdrawals.approve', $w->id) }}" style="display:inline">
                  @csrf <button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</button>
                </form>
                <form method="POST" action="{{ route('admin.elearning.withdrawals.reject', $w->id) }}" style="display:inline">
                  @csrf <button class="btn btn-danger btn-sm" onclick="return confirm('Reject this withdrawal?')"><i class="fas fa-times"></i> Reject</button>
                </form>
              </div>
              @else
              <span class="text-muted text-sm">—</span>
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6">
              <div class="empty-state"><i class="fas fa-money-bill-wave"></i><h3>No withdrawal requests</h3></div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($withdrawals->hasPages())
    <div class="p-4">{{ $withdrawals->links() }}</div>
    @endif
  </div>
</div>
@endsection
