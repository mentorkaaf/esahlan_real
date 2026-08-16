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
    </div>
    <div class="card-body">
      @foreach($employee->workforceAssignments as $a)
      <div class="assign-row">
        <div class="assign-icon" style="background:{{ $a->module?->color ?? 'var(--navy2)' }};">
          <i class="fas fa-layer-group" style="color:#fff;font-size:12px;"></i>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-weight:700;font-size:13px;color:var(--text);">{{ $a->module?->name ?? '—' }}</div>
          <div style="font-size:11px;color:var(--muted);margin-top:1px;">{{ $a->modulePosition?->name ?? $a->moduleDepartment?->name ?? 'No position' }}</div>
        </div>
        <span class="badge badge-{{ match($a->assignment_type){ 'primary'=>'orange','secondary'=>'blue','temporary'=>'yellow',default=>'gray' } }}">
          {{ $a->assignment_type_label }}
        </span>
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
