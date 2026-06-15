@extends('admin.layouts.app')
@section('title', 'eLearning Settings')

@section('content')
<div class="container-fluid py-4">

  <div class="mb-4">
    <h2 class="fw-bold" style="font-size:22px">eLearning Settings</h2>
    <p class="text-muted mb-0">Configure platform commission and feature settings</p>
  </div>

  @if(session('success'))
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
  @endif

  <div class="card p-4" style="max-width:560px">
    <form method="POST" action="{{ route('admin.elearning.settings.update') }}">
      @csrf @method('PUT')

      <div class="form-group">
        <label class="form-label">Platform Commission Rate (%)</label>
        <input type="number" name="commission_rate" class="form-control" value="{{ $settings['commission_rate'] }}" min="0" max="100" step="0.5" required>
        <div class="text-muted text-xs mt-1">Percentage deducted from each sale. Instructor receives (100 - rate)%.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Max Instructor Payout Per Request ($)</label>
        <input type="number" name="max_instructor_payout" class="form-control" value="{{ $settings['max_instructor_payout'] }}" min="0" step="0.01">
      </div>

      <div class="form-group">
        <label class="form-label d-flex align-items-center gap-2">
          <input type="checkbox" name="certificate_enabled" value="1" {{ $settings['certificate_enabled'] ? 'checked' : '' }}>
          Enable Auto-Certificates on Completion
        </label>
      </div>

      <div class="form-group">
        <label class="form-label d-flex align-items-center gap-2">
          <input type="checkbox" name="quiz_enabled" value="1" {{ $settings['quiz_enabled'] ? 'checked' : '' }}>
          Enable Quiz Feature
        </label>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">Save Settings</button>
      </div>
    </form>
  </div>
</div>
@endsection
