@extends('admin.layouts.app')
@section('title', 'Affiliates')
@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 class="mb-0 fw-bold">🤝 Affiliate Program</h4>
    <small class="text-muted">Manage affiliate partners and track performance</small>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('admin.affiliates.payouts') }}" class="btn btn-outline-warning btn-sm">Payout Requests</a>
    <a href="{{ route('admin.affiliates.settings') }}" class="btn btn-outline-secondary btn-sm">Settings</a>
  </div>
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
  @foreach([
    ['Total Affiliates', $stats['total'], 'people-fill', 'primary'],
    ['Active',           $stats['active'],   'check-circle-fill', 'success'],
    ['Pending Review',   $stats['pending'],  'clock-fill',        'warning'],
    ['Total Pts Paid',   number_format($stats['total_pts']), 'star-fill', 'info'],
  ] as [$label, $val, $icon, $color])
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle bg-{{ $color }} bg-opacity-10 p-3">
          <i class="bi bi-{{ $icon }} text-{{ $color }} fs-5"></i>
        </div>
        <div>
          <div class="fw-bold fs-5">{{ $val }}</div>
          <div class="text-muted small">{{ $label }}</div>
        </div>
      </div>
    </div>
  </div>
  @endforeach
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Affiliate</th>
            <th>Code</th>
            <th>Status</th>
            <th class="text-end">Clicks</th>
            <th class="text-end">Conversions</th>
            <th class="text-end">Pts Earned</th>
            <th>Joined</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($affiliates as $a)
          <tr>
            <td>
              <div class="fw-semibold">{{ $a->name }}</div>
              <small class="text-muted">{{ $a->phone }}</small>
            </td>
            <td><code class="text-primary fw-bold">{{ $a->code }}</code></td>
            <td>
              @php $colors = ['active'=>'success','pending'=>'warning','suspended'=>'danger']; @endphp
              <span class="badge bg-{{ $colors[$a->status] ?? 'secondary' }}">{{ ucfirst($a->status) }}</span>
            </td>
            <td class="text-end">{{ number_format($a->total_clicks) }}</td>
            <td class="text-end">{{ number_format($a->total_conversions) }}</td>
            <td class="text-end fw-bold text-primary">{{ number_format($a->total_earned_pts) }}</td>
            <td><small class="text-muted">{{ \Carbon\Carbon::parse($a->created_at)->format('M d, Y') }}</small></td>
            <td><a href="{{ route('admin.affiliates.show', $a->id) }}" class="btn btn-outline-primary btn-sm">View</a></td>
          </tr>
          @empty
          <tr><td colspan="8" class="text-center text-muted py-5">No affiliates yet</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if($affiliates->hasPages())
  <div class="card-footer">{{ $affiliates->links() }}</div>
  @endif
</div>
@endsection
