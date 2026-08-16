@extends('employee.layouts.app')
@section('title', 'Codso Leave')

@push('head')
<style>
.leave-form-card {
  max-width: 580px;
}
</style>
@endpush

@section('content')
<div class="leave-form-card">

  <div class="page-header" style="margin-bottom:20px;">
    <div style="display:flex;align-items:center;gap:12px;">
      <a href="{{ route('employee.leaves') }}" class="btn btn-ghost btn-sm">
        <i class="fas fa-arrow-left"></i>
      </a>
      <div>
        <h1 class="page-title" style="font-size:18px;">
          <div class="page-title-icon" style="width:32px;height:32px;border-radius:9px;font-size:13px;">
            <i class="fas fa-umbrella-beach"></i>
          </div>
          Codso Leave Cusub
        </h1>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <form action="{{ route('employee.leaves.store') }}" method="POST" style="display:flex;flex-direction:column;gap:20px;">
        @csrf

        {{-- Leave type --}}
        <div>
          <label class="form-label">Nooca Leave</label>
          <select name="leave_type_id" required class="form-input form-select">
            <option value="">— Dooro nooca —</option>
            @foreach($leaveTypes as $type)
              <option value="{{ $type->id }}" {{ old('leave_type_id') == $type->id ? 'selected' : '' }}>
                {{ $type->name }}
                @if($type->days_per_year) ({{ $type->days_per_year }} maalin/sanad) @endif
                @if(!$type->is_paid) · Lacag la'aan @endif
              </option>
            @endforeach
          </select>
          @error('leave_type_id')
            <div style="color:var(--red);font-size:11px;margin-top:4px;"><i class="fas fa-exclamation-circle"></i> {{ $message }}</div>
          @enderror
        </div>

        {{-- Dates --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
          <div>
            <label class="form-label">Taariikhda Bilawga</label>
            <input type="date" name="start_date" value="{{ old('start_date') }}"
              min="{{ today()->format('Y-m-d') }}" required class="form-input">
            @error('start_date')
              <div style="color:var(--red);font-size:11px;margin-top:4px;">{{ $message }}</div>
            @enderror
          </div>
          <div>
            <label class="form-label">Taariikhda Dhamaadka</label>
            <input type="date" name="end_date" value="{{ old('end_date') }}"
              min="{{ today()->format('Y-m-d') }}" required class="form-input">
            @error('end_date')
              <div style="color:var(--red);font-size:11px;margin-top:4px;">{{ $message }}</div>
            @enderror
          </div>
        </div>

        {{-- Reason --}}
        <div>
          <label class="form-label">Sababta <span style="color:var(--muted2);font-weight:400;">(ikhtiyaari)</span></label>
          <textarea name="reason" rows="4" placeholder="Sharax sababta codsiga..."
            class="form-input">{{ old('reason') }}</textarea>
          @error('reason')
            <div style="color:var(--red);font-size:11px;margin-top:4px;">{{ $message }}</div>
          @enderror
        </div>

        {{-- Info box --}}
        <div style="background:var(--brand-light);border:1px solid rgba(247,148,29,.25);border-radius:10px;padding:12px 14px;font-size:12px;color:#9a3412;display:flex;gap:9px;align-items:flex-start;">
          <i class="fas fa-info-circle" style="margin-top:1px;flex-shrink:0;"></i>
          <span>Codsiga waxaa u diri doona HR si loogu ansixiyo. Xaaladda waxaad ka arki kartaa <strong>Leave-kayga</strong> bogga.</span>
        </div>

        {{-- Actions --}}
        <div style="display:flex;gap:12px;">
          <button type="submit" class="btn btn-primary" style="flex:1;">
            <i class="fas fa-paper-plane"></i> Dir Codsiga
          </button>
          <a href="{{ route('employee.leaves') }}" class="btn btn-outline">
            <i class="fas fa-times"></i> Jooji
          </a>
        </div>
      </form>
    </div>
  </div>

</div>
@endsection
