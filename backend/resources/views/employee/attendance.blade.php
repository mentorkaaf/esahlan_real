@extends('employee.layouts.app')
@section('title', 'Attendance-kayga')

@section('content')
<div style="display:flex;flex-direction:column;gap:24px;">

  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <h1 style="font-size:20px;font-weight:800;color:#111827;">📅 Attendance-kayga</h1>
    <form method="GET" style="display:flex;gap:8px;">
      <select name="month" onchange="this.form.submit()"
        style="font-size:13px;border:1.5px solid #e5e7eb;border-radius:10px;padding:8px 14px;background:#fff;cursor:pointer;">
        @foreach($months as $val => $label)
          <option value="{{ $val }}" {{ $month === $val ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
    </form>
  </div>

  {{-- Summary --}}
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;">
    @foreach([
      ['Joogay',   $summary['present'],  '#16a34a', '#f0fdf4', 'fas fa-check'],
      ['Maqnaa',   $summary['absent'],   '#dc2626', '#fef2f2', 'fas fa-times'],
      ['Dambe',    $summary['late'],     '#d97706', '#fffbeb', 'fas fa-clock'],
      ['Hadhow',   $summary['half_day'], '#2563eb', '#eff6ff', 'fas fa-adjust'],
      ['Leave',    $summary['leave'],    '#7c3aed', '#f5f3ff', 'fas fa-umbrella-beach'],
    ] as [$label, $count, $color, $bg, $icon])
    <div class="card" style="padding:16px;text-align:center;">
      <div style="width:36px;height:36px;border-radius:10px;background:{{ $bg }};display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
        <i class="{{ $icon }}" style="color:{{ $color }}"></i>
      </div>
      <div style="font-size:24px;font-weight:900;color:{{ $color }}">{{ $count }}</div>
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin-top:4px;">{{ $label }}</div>
    </div>
    @endforeach
  </div>

  {{-- Records table --}}
  <div class="card">
    <div class="card-header">
      <span class="card-title">Diiwaanka Xضurka</span>
      <span style="font-size:12px;color:#6b7280;">{{ $records->count() }} maalmood la diiwaangeliyay</span>
    </div>
    @if($records->count())
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
          <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
            @foreach(['Taariikhda','Xaalad','Galitaanka','Bixitaanka','Saacadaha','Remarks'] as $col)
            <th style="text-align:left;padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;">{{ $col }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($records as $rec)
          @php
            $statusMap = [
              'present'  => ['badge-green','Joogay'],
              'absent'   => ['badge-red','Maqnaa'],
              'late'     => ['badge-yellow','Dambe'],
              'half_day' => ['badge-blue','Hadhow'],
              'on_leave' => ['badge-gray','Leave'],
            ];
            [$badgeClass, $label] = $statusMap[$rec->status] ?? ['badge-gray', $rec->status];
          @endphp
          <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:12px 16px;font-weight:600;">{{ $rec->date->format('EEE, d M') ?? $rec->date }}</td>
            <td style="padding:12px 16px;"><span class="badge {{ $badgeClass }}">{{ $label }}</span></td>
            <td style="padding:12px 16px;color:#374151;">{{ $rec->check_in ? date('H:i', strtotime($rec->check_in)) : '—' }}</td>
            <td style="padding:12px 16px;color:#374151;">{{ $rec->check_out ? date('H:i', strtotime($rec->check_out)) : '—' }}</td>
            <td style="padding:12px 16px;color:#374151;">
              @if($rec->check_in && $rec->check_out)
                @php $hrs = round((strtotime($rec->check_out) - strtotime($rec->check_in)) / 3600, 1); @endphp
                {{ $hrs }}h
              @else —
              @endif
            </td>
            <td style="padding:12px 16px;color:#6b7280;font-size:12px;">{{ $rec->remarks ?? '—' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @else
    <div style="padding:48px;text-align:center;color:#9ca3af;">
      <i class="fas fa-calendar-times" style="font-size:32px;display:block;margin-bottom:12px;opacity:.3;"></i>
      {{ $months[$month] ?? $month }} — xog la'aan
    </div>
    @endif
  </div>

</div>
@endsection
