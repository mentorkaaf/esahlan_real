@extends('admin.layouts.app')
@section('title', 'Notification Stats')
@section('content')
<div class="container-fluid py-4">

    {{-- Header --}}
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('admin.notifications.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <div>
            <h4 class="mb-0 fw-bold">📊 Notification Analytics</h4>
            <small class="text-muted">{{ $pushLog->title }}</small>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div style="font-size:2rem;font-weight:900;color:#1a1a2e;">{{ $stats['sent'] }}</div>
                <div class="text-muted small">Total Sent</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div style="font-size:2rem;font-weight:900;color:#22c55e;">{{ $stats['opened'] }}</div>
                <div class="text-muted small">Opened ✅</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div style="font-size:2rem;font-weight:900;color:#ef4444;">{{ $stats['unopened'] }}</div>
                <div class="text-muted small">Not Opened ❌</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div style="font-size:2rem;font-weight:900;color:#f97316;">{{ $stats['open_rate'] }}%</div>
                <div class="text-muted small">Open Rate</div>
            </div>
        </div>
    </div>

    {{-- Open Rate Bar --}}
    <div class="card border-0 shadow-sm mb-4 p-3">
        <div class="d-flex justify-content-between mb-1">
            <span class="small fw-bold">Open Rate</span>
            <span class="small text-muted">{{ $stats['opened'] }} / {{ $stats['sent'] }}</span>
        </div>
        <div class="progress" style="height:12px;border-radius:8px;">
            <div class="progress-bar bg-success" style="width:{{ $stats['open_rate'] }}%;border-radius:8px;"></div>
        </div>

        {{-- Resend button --}}
        @if($stats['unopened'] > 0)
        <div class="mt-3">
            <form action="{{ route('admin.notifications.resend', $pushLog->id) }}" method="POST"
                  onsubmit="return confirm('Re-send to {{ $stats['unopened'] }} user(s) who did not open?')">
                @csrf
                <button type="submit" class="btn btn-warning btn-sm fw-bold">
                    🔁 Re-send to {{ $stats['unopened'] }} who didn't open
                </button>
            </form>
        </div>
        @else
        <div class="mt-3 text-success small fw-bold">✅ Everyone opened this notification!</div>
        @endif
    </div>

    {{-- Per-user table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold py-3">Delivery Details</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Sent At</th>
                        <th>Opened At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr>
                        <td>
                            @if($row->user)
                                <span class="fw-bold">{{ $row->user->name }}</span>
                                <br><small class="text-muted">{{ $row->user->phone ?? $row->user->email }}</small>
                            @else
                                <span class="text-muted">ID #{{ $row->user_id }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $row->user_type === 'vendor' ? 'warning text-dark' : ($row->user_type === 'driver' ? 'info text-dark' : 'primary') }}">
                                {{ ucfirst($row->user_type) }}
                            </span>
                        </td>
                        <td>
                            @if($row->status === 'opened')
                                <span class="badge bg-success">✅ Opened</span>
                            @elseif($row->status === 'failed')
                                <span class="badge bg-danger">❌ Failed</span>
                            @else
                                <span class="badge bg-secondary">📨 Sent</span>
                            @endif
                        </td>
                        <td><small>{{ $row->sent_at?->format('M d, H:i') ?? '-' }}</small></td>
                        <td><small>{{ $row->opened_at?->format('M d, H:i') ?? '-' }}</small></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No delivery records yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
