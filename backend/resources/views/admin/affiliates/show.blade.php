@extends('admin.layouts.app')
@section('title', 'Affiliate: ' . $affiliate->name)
@section('content')
<div class="d-flex align-items-center gap-3 mb-4">
  <a href="{{ route('admin.affiliates.index') }}" class="btn btn-outline-secondary btn-sm">← Back</a>
  <h4 class="mb-0 fw-bold">{{ $affiliate->name }}</h4>
  @php $colors = ['active'=>'success','pending'=>'warning','suspended'=>'danger']; @endphp
  <span class="badge bg-{{ $colors[$affiliate->status] ?? 'secondary' }} fs-6">{{ ucfirst($affiliate->status) }}</span>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-3 mb-4">
  @foreach([
    ['Code', '<code class="fw-bold fs-5">'.$affiliate->code.'</code>'],
    ['Commission', ($affiliate->commission_pct ?? '(global)').'%'],
    ['Clicks', number_format($affiliate->total_clicks)],
    ['Conversions', number_format($affiliate->total_conversions)],
    ['Total Earned', number_format($affiliate->total_earned_pts).' pts'],
    ['Pending Payout', number_format($affiliate->pending_payout_pts).' pts'],
    ['Pts Balance', number_format($affiliate->points_balance ?? 0).' pts'],
    ['Phone', $affiliate->phone],
  ] as [$label, $val])
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm p-3">
      <div class="text-muted small">{{ $label }}</div>
      <div class="fw-bold">{!! $val !!}</div>
    </div>
  </div>
  @endforeach
</div>

{{-- Status change --}}
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-transparent fw-bold">Update Status</div>
  <div class="card-body">
    <form method="POST" action="{{ route('admin.affiliates.status', $affiliate->id) }}" class="d-flex align-items-center gap-3">
      @csrf @method('PATCH')
      <select name="status" class="form-select" style="width:auto">
        @foreach(['pending','active','suspended'] as $s)
          <option value="{{ $s }}" {{ $affiliate->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
        @endforeach
      </select>
      <button type="submit" class="btn btn-primary">Update</button>
    </form>
  </div>
</div>

{{-- Conversions --}}
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-transparent fw-bold">Conversions (Orders)</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr><th>Order</th><th>Buyer</th><th class="text-end">Order Amount</th><th class="text-end">Commission Pts</th><th>Status</th><th>Date</th></tr>
        </thead>
        <tbody>
          @forelse($conversions as $c)
          <tr>
            <td><small class="text-muted">{{ $c->order_number }}</small></td>
            <td>{{ $c->buyer_name }}</td>
            <td class="text-end">${{ number_format($c->order_amount, 2) }}</td>
            <td class="text-end fw-bold text-success">+{{ number_format($c->commission_pts) }}</td>
            <td><span class="badge bg-{{ $c->status === 'credited' ? 'success' : 'secondary' }}">{{ $c->status }}</span></td>
            <td><small class="text-muted">{{ \Carbon\Carbon::parse($c->created_at)->format('M d, Y') }}</small></td>
          </tr>
          @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No conversions yet</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if($conversions->hasPages())
  <div class="card-footer">{{ $conversions->links() }}</div>
  @endif
</div>

{{-- Payouts --}}
<div class="card border-0 shadow-sm">
  <div class="card-header bg-transparent fw-bold">Payout History</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead class="table-light">
          <tr><th>Pts Requested</th><th>$ Value</th><th>Status</th><th>Note</th><th>Date</th></tr>
        </thead>
        <tbody>
          @forelse($payouts as $p)
          <tr>
            <td>{{ number_format($p->points_requested) }} pts</td>
            <td>${{ number_format($p->dollar_value, 2) }}</td>
            <td>
              @php $pc = ['pending'=>'warning','approved'=>'info','paid'=>'success','rejected'=>'danger']; @endphp
              <span class="badge bg-{{ $pc[$p->status] ?? 'secondary' }}">{{ ucfirst($p->status) }}</span>
            </td>
            <td><small>{{ $p->admin_note ?? '—' }}</small></td>
            <td><small class="text-muted">{{ \Carbon\Carbon::parse($p->created_at)->format('M d, Y') }}</small></td>
          </tr>
          @empty
          <tr><td colspan="5" class="text-center text-muted py-4">No payouts yet</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
