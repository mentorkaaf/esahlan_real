@extends('admin.layouts.app')
@section('title', 'Affiliate Settings')
@section('content')
<div class="d-flex align-items-center gap-3 mb-4">
  <a href="{{ route('admin.affiliates.index') }}" class="btn btn-outline-secondary btn-sm">← Affiliates</a>
  <h4 class="mb-0 fw-bold">Affiliate Settings</h4>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<form method="POST" action="{{ route('admin.affiliates.settings.save') }}">
  @csrf
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-bold">General</div>
    <div class="card-body">
      <div class="form-check form-switch fs-5 mb-3">
        <input class="form-check-input" type="checkbox" name="affiliate_enabled" id="affEnabled" value="1"
          {{ ($settings['affiliate_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
        <label class="form-check-label fw-semibold" for="affEnabled">Affiliate Program Enabled</label>
      </div>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" name="affiliate_auto_approve" id="autoApprove" value="1"
          {{ ($settings['affiliate_auto_approve'] ?? '0') == '1' ? 'checked' : '' }}>
        <label class="form-check-label" for="autoApprove">Auto-approve applications (no manual review)</label>
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-semibold">Commission (%)</label>
          <input type="number" name="affiliate_commission_pct" class="form-control" value="{{ $settings['affiliate_commission_pct'] ?? 3 }}" min="1" max="50">
          <div class="form-text">% of each order amount credited as pts to the affiliate</div>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Min Payout (pts)</label>
          <input type="number" name="affiliate_payout_min_pts" class="form-control" value="{{ $settings['affiliate_payout_min_pts'] ?? 1000 }}" min="100">
          <div class="form-text">Minimum pts to request a payout</div>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Attribution window (days)</label>
          <input type="number" name="affiliate_cookie_days" class="form-control" value="{{ $settings['affiliate_cookie_days'] ?? 30 }}" min="1" max="365">
          <div class="form-text">How long the affiliate attribution lasts after click</div>
        </div>
      </div>
    </div>
  </div>
  <button type="submit" class="btn btn-primary px-4 fw-bold">Save Settings</button>
</form>
@endsection
