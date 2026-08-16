@extends('employee.layouts.app')
@section('title', 'Daawooyinkayga')

@push('head')
<style>
.leave-card {
  background: #fff;
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  padding: 18px 20px;
  box-shadow: var(--shadow-sm);
  display: flex;
  align-items: flex-start;
  gap: 16px;
  transition: box-shadow .2s;
  margin-bottom: 10px;
}
.leave-card:last-child { margin-bottom: 0; }
.leave-card:hover { box-shadow: var(--shadow); }

.leave-type-icon {
  width: 46px;
  height: 46px;
  border-radius: 13px;
  background: var(--brand-light);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: var(--brand);
  flex-shrink: 0;
}
</style>
@endpush

@section('content')

<div class="page-header">
  <div>
    <h1 class="page-title">
      <div class="page-title-icon"><i class="fas fa-umbrella-beach"></i></div>
      Codsiyada Daawooyinka
    </h1>
    <div class="page-sub">Codsigaaga daawooyinka iyo xaaladooda</div>
  </div>
  <a href="{{ route('employee.leaves.create') }}" class="btn btn-primary">
    <i class="fas fa-plus"></i> Codso Leave Cusub
  </a>
</div>

@if($leaves->count())

{{-- Summary mini cards --}}
@php
  $totalLeaves    = $leaves->total();
  $approvedLeaves = $leaves->getCollection()->where('status','approved')->count();
  $pendingLeaves  = $leaves->getCollection()->where('status','pending')->count();
  $rejectedLeaves = $leaves->getCollection()->where('status','rejected')->count();
@endphp
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:20px;">
  @foreach([
    [$totalLeaves,    'Codsi guud',   'var(--navy)',  '#f0f2f8'],
    [$approvedLeaves, 'La ansixiyay', '#059669',     'rgba(5,150,105,.08)'],
    [$pendingLeaves,  'Sugaya',       '#d97706',     'rgba(217,119,6,.08)'],
    [$rejectedLeaves, 'La diidday',   '#dc2626',     'rgba(220,38,38,.08)'],
  ] as [$count, $label, $color, $bg])
  <div style="background:{{ $bg }};border:1.5px solid var(--border);border-radius:12px;padding:14px 16px;text-align:center;box-shadow:var(--shadow-sm);">
    <div style="font-size:24px;font-weight:900;color:{{ $color }};line-height:1;">{{ $count }}</div>
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-top:5px;">{{ $label }}</div>
  </div>
  @endforeach
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-list"></i> Dhamaan Codsiyada</div>
    <span style="font-size:12px;color:var(--muted);">{{ $leaves->total() }} codsi</span>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Nooca</th>
          <th>Bilawga</th>
          <th>Dhamaadka</th>
          <th>Maalmood</th>
          <th>Xaalad</th>
          <th>Sababta</th>
        </tr>
      </thead>
      <tbody>
        @foreach($leaves as $leave)
        @php
          $statusMap = [
            'pending'  => ['badge-yellow', 'fas fa-clock',        'Sugaya'],
            'approved' => ['badge-green',  'fas fa-check-circle', 'La ansixiyay'],
            'rejected' => ['badge-red',    'fas fa-times-circle', 'La diidday'],
          ];
          [$badgeClass, $icon, $statusLabel] = $statusMap[$leave->status] ?? ['badge-gray', 'fas fa-circle', $leave->status];
        @endphp
        <tr>
          <td>
            <div style="font-weight:700;text-transform:capitalize;color:var(--text);">
              {{ str_replace('_',' ',$leave->leave_type) }}
            </div>
          </td>
          <td style="white-space:nowrap;">{{ $leave->start_date->format('d M Y') }}</td>
          <td style="white-space:nowrap;">{{ $leave->end_date->format('d M Y') }}</td>
          <td>
            <span style="font-weight:800;font-size:15px;color:var(--navy);">{{ $leave->days ?? '—' }}</span>
            <span style="font-size:11px;color:var(--muted);">maalin</span>
          </td>
          <td><span class="badge {{ $badgeClass }}"><i class="{{ $icon }}" style="font-size:9px;"></i> {{ $statusLabel }}</span></td>
          <td style="color:var(--muted);font-size:12px;max-width:220px;">{{ Str::limit($leave->reason, 60) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @if($leaves->hasPages())
  <div style="padding:16px;border-top:1px solid var(--border-soft);">{{ $leaves->links() }}</div>
  @endif
</div>

@else
<div class="card">
  <div class="empty-state">
    <i class="fas fa-umbrella-beach empty-state-icon"></i>
    <div class="empty-state-title">Wali codsi la sameynin</div>
    <div class="empty-state-sub">Marka aad daawooyinka u baahato, halkan ka codso.</div>
    <div style="margin-top:16px;">
      <a href="{{ route('employee.leaves.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Codso Leave
      </a>
    </div>
  </div>
</div>
@endif

@endsection
