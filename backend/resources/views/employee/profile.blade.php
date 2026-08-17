@extends('employee.layouts.app')
@section('title', 'Profile-kayga')

@push('head')
<style>
.profile-hero {
  background: linear-gradient(135deg, var(--navy) 0%, var(--navy3) 100%);
  border-radius: var(--radius-lg);
  padding: 28px 32px;
  color: #fff;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 24px;
  flex-wrap: wrap;
  box-shadow: 0 8px 32px rgba(15,11,46,.25);
  position: relative;
  overflow: hidden;
}
.profile-hero::before {
  content: '';
  position: absolute;
  right: -30px; top: -30px;
  width: 180px; height: 180px;
  border-radius: 50%;
  background: rgba(247,148,29,.08);
  pointer-events: none;
}
.profile-avatar {
  width: 80px;
  height: 80px;
  border-radius: 22px;
  background: linear-gradient(135deg, var(--brand), var(--brand-dark));
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 30px;
  font-weight: 900;
  flex-shrink: 0;
  box-shadow: 0 4px 16px rgba(247,148,29,.3);
  position: relative;
  z-index: 1;
}
.profile-info { flex: 1; position: relative; z-index: 1; }
.profile-name { font-size: 22px; font-weight: 900; color: #fff; }
.profile-meta { font-size: 13px; color: rgba(255,255,255,.55); margin-top: 5px; }
.profile-badges { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
.profile-pill {
  font-size: 10px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 20px;
  text-transform: uppercase;
  letter-spacing: .06em;
}
.profile-pill-green  { background: rgba(5,150,105,.2);  color: #34d399; border: 1px solid rgba(5,150,105,.3); }
.profile-pill-yellow { background: rgba(217,119,6,.2);  color: #fbbf24; border: 1px solid rgba(217,119,6,.3); }
.profile-pill-blue   { background: rgba(37,99,235,.2);  color: #60a5fa; border: 1px solid rgba(37,99,235,.3); }
.profile-pill-gray   { background: rgba(255,255,255,.08); color: rgba(255,255,255,.5); border: 1px solid rgba(255,255,255,.1); }

.assign-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: #f8f9fc;
  border-radius: 10px;
  margin-bottom: 8px;
  border: 1.5px solid var(--border-soft);
}
.assign-row:last-child { margin-bottom: 0; }
.assign-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
</style>
@endpush

@section('content')
<div style="max-width:720px;">

  {{-- Hero --}}
  <div class="profile-hero">
    <div class="profile-avatar">
      {{ strtoupper(substr($employee->first_name,0,1).substr($employee->last_name,0,1)) }}
    </div>
    <div class="profile-info">
      <div class="profile-name">{{ $employee->full_name }}</div>
      <div class="profile-meta">
        {{ $employee->employee_no }}
        &nbsp;·&nbsp;{{ $employee->position?->title ?? '—' }}
        &nbsp;·&nbsp;{{ $employee->department?->name ?? '—' }}
      </div>
      <div class="profile-badges">
        @php
          $pillClass = match($employee->status) {
            'active'    => 'profile-pill-green',
            'suspended' => 'profile-pill-yellow',
            'probation' => 'profile-pill-blue',
            default     => 'profile-pill-gray'
          };
        @endphp
        <span class="profile-pill {{ $pillClass }}">{{ ucfirst($employee->status) }}</span>
        @if($employee->employment_type)
          <span class="profile-pill profile-pill-gray">{{ ucfirst(str_replace('_',' ',$employee->employment_type)) }}</span>
        @endif
        @if($employee->hire_date)
          <span class="profile-pill profile-pill-blue"><i class="fas fa-calendar" style="margin-right:4px;font-size:8px;"></i>{{ $employee->hire_date->format('d M Y') }}</span>
        @endif
      </div>
    </div>
  </div>

  {{-- Module assignments --}}
  @if($employee->workforceAssignments->count())
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-th-large"></i> Module Assignments-kayga</div>
      <span style="font-size:12px;color:var(--muted);">{{ $employee->workforceAssignments->count() }} active</span>
    </div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:14px;">
      @foreach($employee->workforceAssignments as $a)
      @php
        $modColor = $a->module?->color ?? '#1B1444';
        $typeColor = match($a->assignment_type) {
          'primary'   => 'orange', 'secondary' => 'blue',
          'temporary' => 'yellow', 'acting'    => 'purple',
          default     => 'gray'
        };
        $accessColor = match($a->access_level ?? 'standard') {
          'admin'    => '#dc2626', 'elevated' => '#d97706',
          'standard' => '#2563eb', default    => '#6b7280'
        };
      @endphp
      <div style="border:1.5px solid var(--border-soft);border-radius:14px;overflow:hidden;">

        {{-- Header strip --}}
        <div style="background:{{ $modColor }};padding:12px 16px;display:flex;align-items:center;gap:12px;">
          <div style="width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-layer-group" style="color:#fff;font-size:15px;"></i>
          </div>
          <div style="flex:1;">
            <div style="font-weight:800;font-size:15px;color:#fff;">{{ $a->module?->name ?? '—' }}</div>
            @if($a->moduleRole)
              <div style="font-size:11px;color:rgba(255,255,255,.65);margin-top:2px;">{{ $a->moduleRole->name }}</div>
            @endif
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
            <span class="badge badge-{{ $typeColor }}">{{ $a->assignment_type_label }}</span>
            @if($a->access_level)
            <span style="font-size:10px;font-weight:700;color:{{ $accessColor }};background:rgba(255,255,255,.12);padding:2px 8px;border-radius:20px;text-transform:uppercase;letter-spacing:.05em;">
              {{ $a->access_level_label }}
            </span>
            @endif
          </div>
        </div>

        {{-- Details grid --}}
        <div style="padding:14px 16px;background:var(--surface);display:grid;grid-template-columns:1fr 1fr;gap:10px;">

          @if($a->moduleDepartment)
          <div style="display:flex;flex-direction:column;gap:2px;">
            <span style="font-size:10px;font-weight:700;color:var(--muted2);text-transform:uppercase;letter-spacing:.06em;">Qaybta</span>
            <span style="font-size:13px;font-weight:600;color:var(--text);">{{ $a->moduleDepartment->name }}</span>
          </div>
          @endif

          @if($a->modulePosition)
          <div style="display:flex;flex-direction:column;gap:2px;">
            <span style="font-size:10px;font-weight:700;color:var(--muted2);text-transform:uppercase;letter-spacing:.06em;">Xilka</span>
            <span style="font-size:13px;font-weight:600;color:var(--text);">{{ $a->modulePosition->name }}</span>
          </div>
          @endif

          @if($a->reportingManager)
          <div style="display:flex;flex-direction:column;gap:2px;">
            <span style="font-size:10px;font-weight:700;color:var(--muted2);text-transform:uppercase;letter-spacing:.06em;">Warbixinta u qaadaha</span>
            <span style="font-size:13px;font-weight:600;color:var(--text);">{{ $a->reportingManager->full_name }}</span>
          </div>
          @endif

          @if($a->start_date)
          <div style="display:flex;flex-direction:column;gap:2px;">
            <span style="font-size:10px;font-weight:700;color:var(--muted2);text-transform:uppercase;letter-spacing:.06em;">Laga bilaabay</span>
            <span style="font-size:13px;font-weight:600;color:var(--text);">{{ $a->start_date->format('d M Y') }}</span>
          </div>
          @endif

        </div>

      </div>
      @endforeach
    </div>
  </div>
  @endif

  {{-- Edit contact info --}}
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-address-card"></i> Macluumaadka Xiriirka</div>
    </div>
    <div class="card-body">
      <form action="{{ route('employee.profile.update') }}" method="POST">
        @csrf @method('PATCH')
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
          @foreach([
            ['phone',    'Telefon',  'text',  $employee->phone],
            ['email',    'Email',    'email', $employee->email],
            ['address',  'Cinwaan',  'text',  $employee->address],
            ['district', 'Degmada', 'text',  $employee->district],
          ] as [$name, $label, $type, $val])
          <div>
            <label class="form-label">{{ $label }}</label>
            <input type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $val) }}"
              class="form-input" placeholder="{{ $label }}...">
            @error($name)<div style="color:var(--red);font-size:11px;margin-top:4px;"><i class="fas fa-exclamation-circle"></i> {{ $message }}</div>@enderror
          </div>
          @endforeach
        </div>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fas fa-save"></i> Keydi Isbedelada
        </button>
      </form>
    </div>
  </div>

  {{-- Change password --}}
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-lock"></i> Beddel Password-ka</div>
    </div>
    <div class="card-body">
      <form action="{{ route('employee.profile.password') }}" method="POST">
        @csrf @method('PATCH')
        <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:20px;">
          @foreach([
            ['current_password',      'Password-ka Hadda'],
            ['password',              'Password Cusub (ugu yaraan 6 xaraf)'],
            ['password_confirmation', 'Xaqiiji Password Cusub'],
          ] as [$name, $label])
          <div>
            <label class="form-label">{{ $label }}</label>
            <input type="password" name="{{ $name }}" class="form-input" required
              placeholder="••••••••">
            @error($name)<div style="color:var(--red);font-size:11px;margin-top:4px;"><i class="fas fa-exclamation-circle"></i> {{ $message }}</div>@enderror
          </div>
          @endforeach
        </div>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fas fa-lock"></i> Beddel Password-ka
        </button>
      </form>
    </div>
  </div>

  {{-- Last login --}}
  @if($employee->login_at)
  <div style="text-align:center;font-size:12px;color:var(--muted2);padding:8px;">
    <i class="fas fa-shield-alt" style="margin-right:4px;"></i>
    Gelitaankii u dambeeyay: {{ $employee->login_at->format('d M Y H:i') }}
    @if($employee->last_login_ip) · IP: {{ $employee->last_login_ip }} @endif
  </div>
  @endif

</div>
@endsection
