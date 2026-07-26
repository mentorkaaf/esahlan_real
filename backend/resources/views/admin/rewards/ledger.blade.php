@extends('admin.layouts.app')
@section('title', 'Points Ledger')
@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
  <h4 class="fw-bold mb-0">⭐ Points Ledger</h4>
  <a href="{{ route('admin.rewards.index') }}" class="btn btn-outline-secondary btn-sm">← Settings</a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>User</th>
            <th>Type</th>
            <th class="text-end">Points</th>
            <th>Note</th>
            <th>Expires</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $row)
          @php
            $color = $row->points > 0 ? 'success' : 'danger';
            $sign  = $row->points > 0 ? '+' : '';
          @endphp
          <tr>
            <td>
              <div class="fw-semibold">{{ $row->name }}</div>
              <small class="text-muted">{{ $row->phone }}</small>
            </td>
            <td><span class="badge bg-secondary">{{ $row->type }}</span></td>
            <td class="text-end fw-bold text-{{ $color }}">{{ $sign }}{{ number_format($row->points) }}</td>
            <td><small>{{ $row->note }}</small></td>
            <td><small class="text-muted">{{ $row->expires_at ? \Carbon\Carbon::parse($row->expires_at)->format('M d, Y') : '—' }}</small></td>
            <td><small class="text-muted">{{ \Carbon\Carbon::parse($row->created_at)->format('M d, Y H:i') }}</small></td>
          </tr>
          @empty
          <tr><td colspan="6" class="text-center text-muted py-5">No transactions yet</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if($rows->hasPages())
  <div class="card-footer">{{ $rows->links() }}</div>
  @endif
</div>
@endsection
