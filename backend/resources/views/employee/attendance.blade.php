@extends('employee.layouts.app')
@section('title', 'Attendance-kayga')

@push('head')
<style>
.att-stat-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px;
  margin-bottom: 24px;
}
@media(max-width:900px){ .att-stat-grid { grid-template-columns: repeat(3,1fr); } }
.att-stat-card {
  background: #fff;
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  padding: 18px 16px;
  text-align: center;
  box-shadow: var(--shadow-sm);
  transition: box-shadow .2s, transform .2s;
}
.att-stat-card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }
.att-stat-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  margin: 0 auto 10px;
}
.att-stat-val { font-size: 26px; font-weight: 900; line-height: 1; }
.att-stat-lbl { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); margin-top: 5px; }
</style>
@endpush

@section('content')

<div class="page-header">
  <div>
    <h1 class="page-title">
      <div class="page-title-icon"><i class="fas fa-calendar-check"></i></div>
      Attendance-kayga
    </h1>
    <div class="page-sub">Diiwaanka xضurka iyo maqnaanshaha</div>
  </div>
  <form method="GET">
    <select name="month" onchange="this.form.submit()" class="form-input form-select" style="width:auto;min-width:160px;">
      @foreach($months as $val => $label)
        <option value="{{ $val }}" {{ $month === $val ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
  </form>
</div>

{{-- Summary stats --}}
<div class="att-stat-grid">
  @foreach([
    ['Joogay',  $summary['present'],  '#059669', 'rgba(5,150,105,.1)',  'fas fa-check-circle'],
    ['Maqnaa',  $summary['absent'],   '#dc2626', 'rgba(220,38,38,.1)',  'fas fa-times-circle'],
    ['Dambe',   $summary['late'],     '#d97706', 'rgba(217,119,6,.1)',  'fas fa-clock'],
    ['Hadhow',  $summary['half_day'], '#2563eb', 'rgba(37,99,235,.1)',  'fas fa-adjust'],
    ['Leave',   $summary['leave'],    '#7c3aed', 'rgba(124,58,237,.1)', 'fas fa-umbrella-beach'],
  ] as [$label, $count, $color, $bg, $icon])
  <div class="att-stat-card">
    <div class="att-stat-icon" style="background:{{ $bg }};">
      <i class="{{ $icon }}" style="color:{{ $color }};"></i>
    </div>
    <div class="att-stat-val" style="color:{{ $color }};">{{ $count }}</div>
    <div class="att-stat-lbl">{{ $label }}</div>
  </div>
  @endforeach
</div>

{{-- Records table --}}
<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-table"></i> Diiwaanka Xضurka</div>
    <span style="font-size:12px;color:var(--muted);">{{ $records->count() }} maalmood la diiwaangeliyay</span>
  </div>
  @if($records->count())
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Taariikhda</th>
          <th>Xaalad</th>
          <th>Galitaanka</th>
          <th>Bixitaanka</th>
          <th>Saacadaha</th>
          <th>Remarks</th>
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
            'on_leave' => ['badge-purple','Leave'],
            'weekend'  => ['badge-gray','Weekend'],
            'holiday'  => ['badge-gray','Holiday'],
          ];
          [$badgeClass, $badgeLabel] = $statusMap[$rec->status] ?? ['badge-gray', $rec->status];
        @endphp
        <tr>
          <td style="font-weight:700;white-space:nowrap;">
            {{ is_string($rec->date) ? $rec->date : $rec->date->format('EEE, d M') }}
          </td>
          <td><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
          <td style="font-variant-numeric:tabular-nums;">
            {{ $rec->check_in ? date('H:i', strtotime($rec->check_in)) : '—' }}
          </td>
          <td style="font-variant-numeric:tabular-nums;">
            {{ $rec->check_out ? date('H:i', strtotime($rec->check_out)) : '—' }}
          </td>
          <td style="font-variant-numeric:tabular-nums;font-weight:600;">
            @if($rec->check_in && $rec->check_out)
              @php $hrs = round((strtotime($rec->check_out) - strtotime($rec->check_in)) / 3600, 1); @endphp
              {{ $hrs }}h
            @else —
            @endif
          </td>
          <td style="color:var(--muted);font-size:12px;">{{ $rec->remarks ?? '—' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @else
  <div class="empty-state">
    <i class="fas fa-calendar-times empty-state-icon"></i>
    <div class="empty-state-title">Xog la'aan</div>
    <div class="empty-state-sub">{{ $months[$month] ?? $month }} — xog diiwaangelinta ah la'aan.</div>
  </div>
  @endif
</div>

@endsection
