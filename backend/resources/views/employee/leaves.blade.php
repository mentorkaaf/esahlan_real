@extends('employee.layouts.app')
@section('title', 'Daawooyinkayga')

@section('content')
<div style="display:flex;flex-direction:column;gap:24px;">

  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <h1 style="font-size:20px;font-weight:800;color:#111827;">🏖️ Codsiyada Daawooyinka</h1>
    <a href="{{ route('employee.leaves.create') }}" class="btn btn-primary">
      <i class="fas fa-plus"></i> Codso Leave Cusub
    </a>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">Dhamaan Codsiyada</span>
      <span style="font-size:12px;color:#6b7280;">{{ $leaves->total() }} codsi</span>
    </div>

    @if($leaves->count())
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
          <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
            @foreach(['Nooca','Taariikhda','Dhamaadka','Maalmood','Xaalad','Sababta'] as $col)
            <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;">{{ $col }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($leaves as $leave)
          @php
            $statusMap = [
              'pending'  => ['badge-yellow','Sugaya'],
              'approved' => ['badge-green','La ansixiyay'],
              'rejected' => ['badge-red','La diidday'],
            ];
            [$badgeClass, $statusLabel] = $statusMap[$leave->status] ?? ['badge-gray', $leave->status];
          @endphp
          <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:12px 16px;font-weight:600;text-transform:capitalize;">{{ str_replace('_',' ',$leave->leave_type) }}</td>
            <td style="padding:12px 16px;">{{ $leave->start_date->format('d M Y') }}</td>
            <td style="padding:12px 16px;">{{ $leave->end_date->format('d M Y') }}</td>
            <td style="padding:12px 16px;font-weight:700;color:#374151;">{{ $leave->days ?? '—' }}</td>
            <td style="padding:12px 16px;"><span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span></td>
            <td style="padding:12px 16px;color:#6b7280;max-width:200px;">{{ Str::limit($leave->reason, 50) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div style="padding:16px;border-top:1px solid #e5e7eb;">
      {{ $leaves->links() }}
    </div>
    @else
    <div style="padding:48px;text-align:center;color:#9ca3af;">
      <i class="fas fa-umbrella-beach" style="font-size:32px;display:block;margin-bottom:12px;opacity:.3;"></i>
      Wali codsi la sameynin.
      <div style="margin-top:12px;">
        <a href="{{ route('employee.leaves.create') }}" class="btn btn-primary btn-sm">Codso Leave</a>
      </div>
    </div>
    @endif
  </div>

</div>
@endsection
