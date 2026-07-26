@extends('admin.layouts.app')
@section('title', 'Affiliate Payout Requests')
@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
  <h4 class="fw-bold mb-0">💸 Affiliate Payout Requests</h4>
  <a href="{{ route('admin.affiliates.index') }}" class="btn btn-outline-secondary btn-sm">← Affiliates</a>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr><th>Affiliate</th><th>Code</th><th class="text-end">Pts</th><th class="text-end">$ Value</th><th>Status</th><th>Requested</th><th>Actions</th></tr>
        </thead>
        <tbody>
          @forelse($payouts as $p)
          <tr>
            <td>
              <div class="fw-semibold">{{ $p->name }}</div>
              <small class="text-muted">{{ $p->phone }}</small>
            </td>
            <td><code>{{ $p->code }}</code></td>
            <td class="text-end fw-bold">{{ number_format($p->points_requested) }}</td>
            <td class="text-end fw-bold text-success">${{ number_format($p->dollar_value, 2) }}</td>
            <td>
              @php $pc = ['pending'=>'warning','approved'=>'info','paid'=>'success','rejected'=>'danger']; @endphp
              <span class="badge bg-{{ $pc[$p->status] ?? 'secondary' }}">{{ ucfirst($p->status) }}</span>
            </td>
            <td><small class="text-muted">{{ \Carbon\Carbon::parse($p->created_at)->format('M d, Y') }}</small></td>
            <td>
              @if($p->status === 'pending')
              <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modal-{{ $p->id }}" data-action="approved">Approve</button>
              <button class="btn btn-danger btn-sm"  data-bs-toggle="modal" data-bs-target="#modal-{{ $p->id }}" data-action="rejected">Reject</button>

              {{-- Modal --}}
              <div class="modal fade" id="modal-{{ $p->id }}" tabindex="-1">
                <div class="modal-dialog">
                  <form method="POST" action="{{ route('admin.affiliates.payout.process', $p->id) }}">
                    @csrf
                    <div class="modal-content">
                      <div class="modal-header"><h5 class="modal-title">Process Payout #{{ $p->id }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                      <div class="modal-body">
                        <p>Affiliate: <strong>{{ $p->name }}</strong> — <strong>{{ number_format($p->points_requested) }} pts (${{ number_format($p->dollar_value, 2) }})</strong></p>
                        <div class="mb-3">
                          <label class="form-label fw-semibold">Action</label>
                          <select name="action" class="form-select">
                            <option value="approved">Approve (credit wallet)</option>
                            <option value="rejected">Reject</option>
                          </select>
                        </div>
                        <div class="mb-3">
                          <label class="form-label fw-semibold">Admin Note (optional)</label>
                          <textarea name="admin_note" class="form-control" rows="2"></textarea>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit</button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
              @else
              <small class="text-muted">{{ $p->admin_note ?? '—' }}</small>
              @endif
            </td>
          </tr>
          @empty
          <tr><td colspan="7" class="text-center text-muted py-5">No payout requests</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if($payouts->hasPages())
  <div class="card-footer">{{ $payouts->links() }}</div>
  @endif
</div>
@endsection
