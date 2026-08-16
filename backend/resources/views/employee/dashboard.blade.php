@extends('employee.layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="space" style="display:flex;flex-direction:column;gap:24px;">

  {{-- Header --}}
  <div>
    <h1 style="font-size:22px;font-weight:800;color:#111827;">
      Salaam, {{ $employee->first_name }}! 👋
    </h1>
    <p style="color:#6b7280;font-size:14px;margin-top:4px;">
      {{ now()->format('l, d F Y') }} ·
      @if($workspace)
        Active: <strong style="color:var(--brand)">{{ $workspace->name }}</strong>
      @else
        Wali workspace la'aad — HR la xiriir
      @endif
    </p>
  </div>

  {{-- KPI row --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;">

    {{-- Active workspaces --}}
    <div class="card" style="padding:20px;">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
        <div style="width:36px;height:36px;border-radius:10px;background:#fff5e6;display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-th-large" style="color:var(--brand)"></i>
        </div>
        <span style="font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Workspaces</span>
      </div>
      <div style="font-size:32px;font-weight:900;color:#111827;">{{ $activeAssignments->count() }}</div>
      <div style="font-size:12px;color:#6b7280;margin-top:4px;">Module xilsaaran</div>
    </div>

    {{-- Today attendance --}}
    <div class="card" style="padding:20px;">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
        <div style="width:36px;height:36px;border-radius:10px;background:{{ $todayAttendance?->status === 'present' ? '#dcfce7' : '#fef9c3' }};display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-clock" style="color:{{ $todayAttendance?->status === 'present' ? 'var(--green)' : '#d97706' }}"></i>
        </div>
        <span style="font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Maanta</span>
      </div>
      @if($todayAttendance)
        <div style="font-size:20px;font-weight:800;color:#111827;text-transform:capitalize;">{{ $todayAttendance->status }}</div>
        <div style="font-size:12px;color:#6b7280;margin-top:4px;">
          {{ $todayAttendance->check_in ? 'Galay: '.date('H:i', strtotime($todayAttendance->check_in)) : '—' }}
        </div>
      @else
        <div style="font-size:20px;font-weight:800;color:#9ca3af;">—</div>
        <div style="font-size:12px;color:#6b7280;margin-top:4px;">La diiwaangelinyin</div>
      @endif
    </div>

    {{-- Leave pending --}}
    <div class="card" style="padding:20px;">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
        <div style="width:36px;height:36px;border-radius:10px;background:#fef2f2;display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-umbrella-beach" style="color:#dc2626"></i>
        </div>
        <span style="font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Leave Pending</span>
      </div>
      <div style="font-size:32px;font-weight:900;color:#111827;">{{ $pendingLeaves }}</div>
      <div style="font-size:12px;margin-top:4px;">
        @if($pendingLeaves > 0)
          <a href="{{ route('employee.leaves') }}" style="color:var(--brand);text-decoration:none;font-weight:600;">Arag →</a>
        @else
          <span style="color:#6b7280;">Codsiyaan la'aan</span>
        @endif
      </div>
    </div>

    {{-- Overall performance --}}
    @php
      $overallScore = count($perfScores) ? round(array_sum($perfScores) / count($perfScores)) : 0;
      $scoreColor   = $overallScore >= 90 ? '#16a34a' : ($overallScore >= 75 ? '#2563eb' : ($overallScore >= 60 ? '#d97706' : '#dc2626'));
    @endphp
    <div class="card" style="padding:20px;">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
        <div style="width:36px;height:36px;border-radius:10px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-chart-line" style="color:var(--green)"></i>
        </div>
        <span style="font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Performance</span>
      </div>
      <div style="font-size:32px;font-weight:900;color:{{ $overallScore > 0 ? $scoreColor : '#9ca3af' }};">
        {{ $overallScore > 0 ? $overallScore.'%' : '—' }}
      </div>
      <div style="font-size:12px;color:#6b7280;margin-top:4px;">{{ now()->format('M Y') }}</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start;">

    {{-- My Workspaces --}}
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="fas fa-th-large" style="color:var(--brand);margin-right:6px"></i> Workspaces-kayga</span>
        <span style="font-size:12px;color:#6b7280;">{{ $activeAssignments->count() }} active</span>
      </div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
        @forelse($activeAssignments as $a)
        @php $mod = $a->module; @endphp
        <div style="display:flex;align-items:center;gap:14px;padding:14px;border-radius:12px;border:1.5px solid #e5e7eb;transition:all .15s;background:#fafafa;">
          <div style="width:44px;height:44px;border-radius:12px;background:{{ $mod?->color ?? '#1B1444' }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-layer-group" style="color:#fff;font-size:18px;"></i>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:700;font-size:14px;color:#111827;">{{ $mod?->name ?? 'Unknown' }}</div>
            <div style="font-size:12px;color:#6b7280;margin-top:2px;">
              {{ $a->modulePosition?->name ?? $a->moduleDepartment?->name ?? 'No position' }}
              · <span class="badge badge-{{ match($a->assignment_type){ 'primary'=>'orange','secondary'=>'blue','temporary'=>'yellow',default=>'gray' } }}">{{ $a->assignment_type_label }}</span>
            </div>
          </div>
          @if(count($perfScores) && isset($perfScores[$mod?->slug]))
          @php $sc = $perfScores[$mod->slug]; $c = $sc>=90?'var(--green)':($sc>=75?'var(--blue)':($sc>=60?'var(--yellow)':'var(--red)')); @endphp
          <div style="text-align:right;flex-shrink:0;">
            <div style="font-size:20px;font-weight:900;color:{{ $c }}">{{ $sc }}%</div>
            <div style="font-size:10px;color:#9ca3af;">{{ now()->format('M') }}</div>
          </div>
          @endif
          <a href="{{ route('employee.workspace', $mod?->slug) }}"
            class="btn btn-primary btn-sm" style="flex-shrink:0;">
            <i class="fas fa-arrow-right"></i>
          </a>
        </div>
        @empty
        <div style="text-align:center;padding:32px;color:#9ca3af;">
          <i class="fas fa-inbox" style="font-size:28px;display:block;margin-bottom:10px;"></i>
          Wali module lagugu xilsaarin. HR la xiriir.
        </div>
        @endforelse
      </div>
    </div>

    {{-- Sidebar: Announcements + Quick links --}}
    <div style="display:flex;flex-direction:column;gap:16px;">

      {{-- Announcements --}}
      @if(count($announcements))
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fas fa-bullhorn" style="color:var(--brand);margin-right:6px"></i> Xayeysiisyada</span>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
          @foreach($announcements as $ann)
          <div style="padding:10px;background:#fafafa;border-radius:8px;border-left:3px solid var(--brand);">
            <div style="font-size:13px;font-weight:600;color:#111827;">{{ $ann->title }}</div>
            <div style="font-size:12px;color:#6b7280;margin-top:3px;">{{ Str::limit($ann->content ?? $ann->body ?? '', 80) }}</div>
            <div style="font-size:11px;color:#9ca3af;margin-top:4px;">{{ $ann->created_at->diffForHumans() }}</div>
          </div>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Quick actions --}}
      <div class="card">
        <div class="card-header">
          <span class="card-title">Xididdada Degdega ah</span>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:8px;">
          <a href="{{ route('employee.leaves.create') }}" class="btn btn-outline" style="justify-content:flex-start;">
            <i class="fas fa-plus" style="color:var(--brand)"></i> Codso Leave
          </a>
          <a href="{{ route('employee.attendance') }}" class="btn btn-outline" style="justify-content:flex-start;">
            <i class="fas fa-calendar-check" style="color:var(--green)"></i> Attendance-kayga
          </a>
          <a href="{{ route('employee.performance') }}" class="btn btn-outline" style="justify-content:flex-start;">
            <i class="fas fa-chart-bar" style="color:var(--blue)"></i> Performance-kayga
          </a>
          <a href="{{ route('employee.profile') }}" class="btn btn-outline" style="justify-content:flex-start;">
            <i class="fas fa-user-edit" style="color:var(--muted)"></i> Naftayda Wax ka beddel
          </a>
        </div>
      </div>

      {{-- Employment info --}}
      <div class="card">
        <div class="card-header">
          <span class="card-title">Macluumaad Shaqada</span>
        </div>
        <div class="card-body" style="font-size:13px;display:flex;flex-direction:column;gap:8px;">
          @php
            $infoRows = [
              ['Nooca Shaqada', ucfirst(str_replace('_',' ',$employee->employment_type ?? '—'))],
              ['Taariikhda Shaqada', $employee->hire_date?->format('d M Y') ?? '—'],
              ['Qaybta', $employee->department?->name ?? '—'],
              ['Xilka', $employee->position?->title ?? '—'],
            ];
          @endphp
          @foreach($infoRows as [$label, $val])
          <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f3f4f6;">
            <span style="color:#6b7280;">{{ $label }}</span>
            <span style="font-weight:600;color:#111827;">{{ $val }}</span>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
