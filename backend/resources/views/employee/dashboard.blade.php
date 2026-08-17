@extends('employee.layouts.app')
@section('title', 'Dashboard')

@push('head')
<style>
.dash-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 16px; margin-bottom: 24px; }

.kpi-card-brand {
  background: linear-gradient(135deg, var(--navy) 0%, var(--navy3) 100%);
  color: #fff;
  border: none;
}
.kpi-card-brand .kpi-label { color: rgba(255,255,255,.5); }
.kpi-card-brand .kpi-value { color: #fff; }
.kpi-card-brand .kpi-sub   { color: rgba(255,255,255,.45); }

.ws-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 18px;
  border: 1.5px solid var(--border);
  border-radius: 12px;
  background: #fafbff;
  transition: all .2s;
}
.ws-item:hover { border-color: var(--brand); background: var(--brand-light); box-shadow: var(--shadow-sm); }
.ws-mod-icon {
  width: 46px;
  height: 46px;
  border-radius: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 18px;
  color: #fff;
}

.ann-item {
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--brand-light);
  border-left: 3px solid var(--brand);
  margin-bottom: 8px;
}
.ann-item:last-child { margin-bottom: 0; }

.quick-btn {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 11px 14px;
  border-radius: 10px;
  background: #f8f9fc;
  border: 1.5px solid var(--border-soft);
  color: var(--text2);
  text-decoration: none;
  font-size: 13px;
  font-weight: 600;
  transition: all .15s;
  margin-bottom: 6px;
}
.quick-btn:last-child { margin-bottom: 0; }
.quick-btn:hover { background: var(--brand-light); border-color: var(--brand); color: var(--brand); }
.quick-btn-icon {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  flex-shrink: 0;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 9px 0;
  border-bottom: 1px solid var(--border-soft);
  font-size: 13px;
}
.info-row:last-child { border-bottom: none; }
.info-row-label { color: var(--muted); }
.info-row-val   { font-weight: 700; color: var(--text); }
</style>
@endpush

@section('content')

{{-- Page header --}}
<div style="margin-bottom:24px;">
  <h1 style="font-size:22px;font-weight:900;color:var(--navy);display:flex;align-items:center;gap:10px;">
    Salaam, {{ $employee->first_name }}! 👋
  </h1>
  <p style="color:var(--muted);font-size:13px;margin-top:4px;">
    {{ now()->translatedFormat('l, d F Y') }}
    &nbsp;·&nbsp;
    @if($workspace)
      Workspace: <strong style="color:var(--brand)">{{ $workspace->name }}</strong>
    @else
      <span style="color:#9ca3af;">Wali workspace la'aad — HR la xiriir</span>
    @endif
  </p>
</div>

{{-- KPI row --}}
@php
  $overallScore = count($perfScores) ? round(array_sum($perfScores)/count($perfScores)) : 0;
  $scoreColor   = $overallScore >= 90 ? '#059669' : ($overallScore >= 75 ? '#2563eb' : ($overallScore >= 60 ? '#d97706' : '#dc2626'));
@endphp
<div class="dash-grid">

  {{-- Workspaces --}}
  <div class="kpi-card">
    <div class="kpi-icon-wrap" style="background:rgba(247,148,29,.12);">
      <i class="fas fa-th-large" style="color:var(--brand);"></i>
    </div>
    <div class="kpi-label">Workspaces</div>
    <div class="kpi-value">{{ $activeAssignments->count() }}</div>
    <div class="kpi-sub">Module xilsaaran</div>
    <div class="kpi-accent" style="background:var(--brand);width:80px;height:80px;"></div>
  </div>

  {{-- Today attendance --}}
  @php
    $attColor  = $todayAttendance?->status === 'present' ? '#059669' : '#d97706';
    $attBg     = $todayAttendance?->status === 'present' ? 'rgba(5,150,105,.1)' : 'rgba(217,119,6,.1)';
    $attIcon   = $todayAttendance?->status === 'present' ? 'fa-check-circle' : 'fa-clock';
  @endphp
  <div class="kpi-card">
    <div class="kpi-icon-wrap" style="background:{{ $attBg }};">
      <i class="fas {{ $attIcon }}" style="color:{{ $attColor }};"></i>
    </div>
    <div class="kpi-label">Maanta</div>
    @if($todayAttendance)
      <div class="kpi-value" style="font-size:20px;text-transform:capitalize;color:{{ $attColor }};">
        {{ $todayAttendance->status }}
      </div>
      <div class="kpi-sub">
        {{ $todayAttendance->check_in ? 'Galitaan: '.date('H:i', strtotime($todayAttendance->check_in)) : 'Check-in la\'aan' }}
      </div>
    @else
      <div class="kpi-value" style="font-size:22px;color:var(--muted2);">—</div>
      <div class="kpi-sub">La diiwaangelinyin</div>
    @endif
    <div class="kpi-accent" style="background:{{ $attColor }};width:80px;height:80px;"></div>
  </div>

  {{-- Leave pending --}}
  <div class="kpi-card">
    <div class="kpi-icon-wrap" style="background:rgba(220,38,38,.1);">
      <i class="fas fa-umbrella-beach" style="color:#dc2626;"></i>
    </div>
    <div class="kpi-label">Leave Pending</div>
    <div class="kpi-value" style="{{ $pendingLeaves > 0 ? 'color:#dc2626;' : '' }}">{{ $pendingLeaves }}</div>
    <div class="kpi-sub">
      @if($pendingLeaves > 0)
        <a href="{{ route('employee.leaves') }}" style="color:var(--brand);font-weight:700;text-decoration:none;">Arag →</a>
      @else
        Codsiyaan la'aan
      @endif
    </div>
    <div class="kpi-accent" style="background:#dc2626;width:80px;height:80px;"></div>
  </div>

  {{-- Performance --}}
  <div class="kpi-card {{ $overallScore > 0 ? '' : '' }}">
    <div class="kpi-icon-wrap" style="background:rgba(5,150,105,.1);">
      <i class="fas fa-chart-line" style="color:#059669;"></i>
    </div>
    <div class="kpi-label">Performance</div>
    <div class="kpi-value" style="color:{{ $overallScore > 0 ? $scoreColor : 'var(--muted2)' }};">
      {{ $overallScore > 0 ? $overallScore.'%' : '—' }}
    </div>
    <div class="kpi-sub">{{ now()->format('M Y') }}</div>
    <div class="kpi-accent" style="background:#059669;width:80px;height:80px;"></div>
  </div>

</div>

{{-- Main content --}}
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">

  {{-- Left: Workspaces --}}
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <i class="fas fa-th-large"></i>
        Workspaces-kayga
      </div>
      <span style="font-size:12px;color:var(--muted);">{{ $activeAssignments->count() }} active</span>
    </div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
      @forelse($activeAssignments as $a)
        @php $mod = $a->module; @endphp
        <div class="ws-item">
          <div class="ws-mod-icon" style="background:{{ $mod?->color ?? 'var(--navy2)' }};">
            <i class="fas fa-layer-group"></i>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:700;font-size:14px;color:var(--text);">{{ $mod?->name ?? 'Unknown' }}</div>
            <div style="font-size:12px;color:var(--muted);margin-top:3px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
              @if($a->modulePosition)
                <span style="font-weight:600;color:var(--text-soft);">{{ $a->modulePosition->name }}</span>
                <span style="color:var(--border);">·</span>
              @endif
              @if($a->moduleDepartment)
                <span>{{ $a->moduleDepartment->name }}</span>
                <span style="color:var(--border);">·</span>
              @endif
              <span class="badge badge-{{ match($a->assignment_type){ 'primary'=>'orange','secondary'=>'blue','temporary'=>'yellow',default=>'gray' } }}">
                {{ $a->assignment_type_label }}
              </span>
              @if($a->reportingManager)
                <span style="color:var(--border);">·</span>
                <span style="color:var(--muted);font-size:11px;"><i class="fas fa-user-tie" style="font-size:9px;"></i> {{ $a->reportingManager->full_name }}</span>
              @endif
            </div>
          </div>
          @if(count($perfScores) && isset($perfScores[$mod?->slug]))
            @php $sc=$perfScores[$mod->slug]; $sc_c=$sc>=90?'#059669':($sc>=75?'#2563eb':($sc>=60?'#d97706':'#dc2626')); @endphp
            <div style="text-align:right;flex-shrink:0;min-width:56px;">
              <div style="font-size:20px;font-weight:900;color:{{ $sc_c }};line-height:1;">{{ $sc }}%</div>
              <div style="font-size:10px;color:var(--muted2);">{{ now()->format('M') }}</div>
            </div>
          @endif
          <a href="{{ route('employee.workspace', $mod?->slug) }}" class="btn btn-primary btn-sm" style="flex-shrink:0;">
            <i class="fas fa-arrow-right"></i>
          </a>
        </div>
      @empty
        <div class="empty-state">
          <i class="fas fa-inbox empty-state-icon"></i>
          <div class="empty-state-title">Workspace la'aan</div>
          <div class="empty-state-sub">Wali module lagugu xilsaarin. HR la xiriir.</div>
        </div>
      @endforelse
    </div>
  </div>

  {{-- Right column --}}
  <div style="display:flex;flex-direction:column;gap:16px;">

    {{-- Announcements --}}
    @if(count($announcements))
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-bullhorn"></i> Xayeysiisyada</div>
        <a href="{{ route('employee.announcements') }}" style="font-size:11px;color:var(--brand);text-decoration:none;font-weight:700;">Dhammaan →</a>
      </div>
      <div class="card-body">
        @foreach($announcements as $ann)
        <div class="ann-item">
          <div style="font-size:13px;font-weight:700;color:var(--navy);">{{ $ann->title }}</div>
          <div style="font-size:12px;color:var(--muted);margin-top:3px;">{{ Str::limit($ann->content ?? $ann->body ?? '', 90) }}</div>
          <div style="font-size:11px;color:var(--muted2);margin-top:5px;">{{ $ann->published_at?->diffForHumans() }}</div>
        </div>
        @endforeach
      </div>
    </div>
    @endif

    {{-- Quick actions --}}
    <div class="card">
      <div class="card-header">
        <div class="card-title">⚡ Xididdada Degdega ah</div>
      </div>
      <div class="card-body">
        <a href="{{ route('employee.leaves.create') }}" class="quick-btn">
          <div class="quick-btn-icon" style="background:rgba(247,148,29,.12);color:var(--brand);"><i class="fas fa-plus"></i></div>
          Codso Leave
        </a>
        <a href="{{ route('employee.attendance') }}" class="quick-btn">
          <div class="quick-btn-icon" style="background:rgba(5,150,105,.1);color:#059669;"><i class="fas fa-calendar-check"></i></div>
          Attendance-kayga
        </a>
        <a href="{{ route('employee.performance') }}" class="quick-btn">
          <div class="quick-btn-icon" style="background:rgba(37,99,235,.1);color:#2563eb;"><i class="fas fa-chart-bar"></i></div>
          Performance-kayga
        </a>
        <a href="{{ route('employee.payslips') }}" class="quick-btn">
          <div class="quick-btn-icon" style="background:rgba(124,58,237,.1);color:#7c3aed;"><i class="fas fa-file-invoice-dollar"></i></div>
          Payslips-kayga
        </a>
        <a href="{{ route('employee.profile') }}" class="quick-btn">
          <div class="quick-btn-icon" style="background:#f3f4f6;color:var(--muted);"><i class="fas fa-user-edit"></i></div>
          Naftayda Wax ka beddel
        </a>
      </div>
    </div>

    {{-- Employment info --}}
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-id-badge"></i> Macluumaad Shaqada</div>
      </div>
      <div class="card-body">
        @php
          $infoRows = [
            ['Nooca Shaqada', ucfirst(str_replace('_',' ',$employee->employment_type ?? '—'))],
            ['Taariikhda Shaqada', $employee->hire_date?->format('d M Y') ?? '—'],
            ['Qaybta', $employee->department?->name ?? '—'],
            ['Xilka', $employee->position?->title ?? '—'],
          ];
        @endphp
        @foreach($infoRows as [$label, $val])
        <div class="info-row">
          <span class="info-row-label">{{ $label }}</span>
          <span class="info-row-val">{{ $val }}</span>
        </div>
        @endforeach
      </div>
    </div>

  </div>
</div>

@endsection
