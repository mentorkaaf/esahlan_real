@extends('employee.layouts.app')
@section('title', 'Codso Leave')

@section('content')
<div style="max-width:560px;">
  <div style="margin-bottom:20px;display:flex;align-items:center;gap:12px;">
    <a href="{{ route('employee.leaves') }}" style="color:#6b7280;text-decoration:none;"><i class="fas fa-arrow-left"></i></a>
    <h1 style="font-size:20px;font-weight:800;color:#111827;">Codso Leave Cusub</h1>
  </div>

  <div class="card">
    <div class="card-body">
      <form action="{{ route('employee.leaves.store') }}" method="POST" style="display:flex;flex-direction:column;gap:20px;">
        @csrf

        <div>
          <label style="display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin-bottom:7px;">Nooca Leave</label>
          <select name="leave_type" required
            style="width:100%;padding:11px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;background:#fff;cursor:pointer;">
            <option value="">Dooro nooca...</option>
            @foreach($leaveTypes as $type)
              <option value="{{ $type }}" {{ old('leave_type') === $type ? 'selected' : '' }}>
                {{ ucfirst(str_replace('_',' ',$type)) }}
              </option>
            @endforeach
          </select>
          @error('leave_type')<div style="color:#dc2626;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
          <div>
            <label style="display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin-bottom:7px;">Taariikhda Bilawga</label>
            <input type="date" name="start_date" value="{{ old('start_date') }}" min="{{ today()->format('Y-m-d') }}"
              style="width:100%;padding:11px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;background:#fff;" required>
            @error('start_date')<div style="color:#dc2626;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin-bottom:7px;">Taariikhda Dhamaadka</label>
            <input type="date" name="end_date" value="{{ old('end_date') }}" min="{{ today()->format('Y-m-d') }}"
              style="width:100%;padding:11px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;background:#fff;" required>
            @error('end_date')<div style="color:#dc2626;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
          </div>
        </div>

        <div>
          <label style="display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin-bottom:7px;">Sababta</label>
          <textarea name="reason" rows="4" required placeholder="Sharax sababta codsiga..."
            style="width:100%;padding:11px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;resize:vertical;">{{ old('reason') }}</textarea>
          @error('reason')<div style="color:#dc2626;font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div style="display:flex;gap:12px;">
          <button type="submit" class="btn btn-primary" style="flex:1;">
            <i class="fas fa-paper-plane"></i> Dir Codsiga
          </button>
          <a href="{{ route('employee.leaves') }}" class="btn btn-outline">Jooji</a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
