@extends('employee.layouts.app')
@section('title', 'Profile-kayga')

@section('content')
<div style="display:flex;flex-direction:column;gap:24px;max-width:720px;">

  <h1 style="font-size:20px;font-weight:800;color:#111827;">👤 Profile-kayga</h1>

  {{-- Profile header card --}}
  <div class="card" style="padding:24px;">
    <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
      <div style="width:72px;height:72px;border-radius:20px;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:900;flex-shrink:0;">
        {{ strtoupper(substr($employee->first_name,0,1).substr($employee->last_name,0,1)) }}
      </div>
      <div style="flex:1;">
        <div style="font-size:20px;font-weight:800;color:#111827;">{{ $employee->full_name }}</div>
        <div style="font-size:13px;color:#6b7280;margin-top:4px;">
          {{ $employee->employee_no }}
          · {{ $employee->position?->title ?? '—' }}
          · {{ $employee->department?->name ?? '—' }}
        </div>
        <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
          @php $sc = match($employee->status){ 'active'=>'badge-green','suspended'=>'badge-yellow','probation'=>'badge-blue',default=>'badge-gray' }; @endphp
          <span class="badge {{ $sc }}">{{ ucfirst($employee->status) }}</span>
          <span class="badge badge-gray">{{ ucfirst(str_replace('_',' ',$employee->employment_type ?? '')) }}</span>
          @if($employee->hire_date)
          <span class="badge badge-blue"><i class="fas fa-calendar" style="font-size:9px;"></i> {{ $employee->hire_date->format('d M Y') }}</span>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Active module assignments --}}
  @if($employee->workforceAssignments->count())
  <div class="card">
    <div class="card-header"><span class="card-title">Module Assignments-kayga</span></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
      @foreach($employee->workforceAssignments as $a)
      <div style="display:flex;align-items:center;gap:12px;padding:10px;background:#fafafa;border-radius:10px;">
        <div style="width:32px;height:32px;border-radius:8px;background:{{ $a->module?->color ?? '#1B1444' }};display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-layer-group" style="color:#fff;font-size:12px;"></i>
        </div>
        <div style="flex:1;">
          <div style="font-weight:600;font-size:13px;color:#111827;">{{ $a->module?->name ?? '—' }}</div>
          <div style="font-size:11px;color:#6b7280;">{{ $a->modulePosition?->name ?? $a->moduleDepartment?->name ?? 'No position' }}</div>
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
  <div class="card">
    <div class="card-header"><span class="card-title">Macluumaadka Xiriirka</span></div>
    <div class="card-body">
      <form action="{{ route('employee.profile.update') }}" method="POST" style="display:flex;flex-direction:column;gap:16px;">
        @csrf @method('PATCH')
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
          @foreach([
            ['phone',    'Telefon',   'text',  $employee->phone],
            ['email',    'Email',     'email', $employee->email],
            ['address',  'Cinwaan',   'text',  $employee->address],
            ['district', 'Degmada',   'text',  $employee->district],
          ] as [$name, $label, $type, $val])
          <div>
            <label style="display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin-bottom:7px;">{{ $label }}</label>
            <input type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $val) }}"
              style="width:100%;padding:10px 13px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;">
            @error($name)<div style="color:#dc2626;font-size:11px;margin-top:3px;">{{ $message }}</div>@enderror
          </div>
          @endforeach
        </div>
        <div>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save"></i> Keydi Isbedelada
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Change password --}}
  <div class="card">
    <div class="card-header"><span class="card-title">🔒 Beddel Password-ka</span></div>
    <div class="card-body">
      <form action="{{ route('employee.profile.password') }}" method="POST" style="display:flex;flex-direction:column;gap:14px;">
        @csrf @method('PATCH')
        @foreach([
          ['current_password', 'Password-ka Hadda'],
          ['password',         'Password Cusub (ugu yaraan 6 xaraf)'],
          ['password_confirmation', 'Xaqiiji Password Cusub'],
        ] as [$name, $label])
        <div>
          <label style="display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin-bottom:7px;">{{ $label }}</label>
          <input type="password" name="{{ $name }}"
            style="width:100%;padding:10px 13px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;" required>
          @error($name)<div style="color:#dc2626;font-size:11px;margin-top:3px;">{{ $message }}</div>@enderror
        </div>
        @endforeach
        <div>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-lock"></i> Beddel Password-ka
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Last login --}}
  @if($employee->login_at)
  <div style="text-align:center;font-size:12px;color:#9ca3af;">
    <i class="fas fa-shield-alt"></i>
    Gelitaankii u dambeeyay: {{ $employee->login_at->format('d M Y H:i') }}
    @if($employee->last_login_ip) · IP: {{ $employee->last_login_ip }} @endif
  </div>
  @endif

</div>
@endsection
