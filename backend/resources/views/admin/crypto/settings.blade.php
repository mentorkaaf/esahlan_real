@extends('admin.layouts.app')
@section('title', 'Crypto Exchange Settings')

@push('styles')
<style>
.cx{background:#F4F6FB;min-height:100vh;padding:24px 28px}
.cx-card{background:#fff;border-radius:16px;border:1px solid #EEF0F6;overflow:hidden;margin-bottom:20px}
.cx-hdr{padding:18px 22px;border-bottom:1px solid #F1F5F9}
.cx-title{font-size:14px;font-weight:800;color:#0F172A}
.cx-body{padding:22px}
.form-label{font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px}
.section-head{font-size:13px;font-weight:800;color:#0F172A;margin-bottom:12px;padding-bottom:8px;border-bottom:2px solid #F1F5F9}
</style>
@endpush

@section('content')
<div class="cx">
<div style="margin-bottom:20px">
  <div style="font-size:12px;color:#94A3B8;margin-bottom:2px">Crypto Exchange</div>
  <h1 style="font-size:22px;font-weight:900;color:#0F172A;margin:0">Exchange Settings</h1>
</div>

@if(session('success'))<div class="alert alert-success mb-3">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger mb-3">{{ session('error') }}</div>@endif

<form method="POST" action="{{ route('admin.crypto.settings.update') }}">
@csrf @method('PATCH')

<div class="cx-card">
  <div class="cx-hdr"><div class="cx-title">General Settings</div></div>
  <div class="cx-body">
    <div class="section-head">Trading Limits</div>
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <label class="form-label">Min Buy (USD)</label>
        <input type="number" name="min_buy_usd" value="{{ $settings['min_buy_usd'] ?? 1 }}" step="0.01" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Max Buy (USD) — per order</label>
        <input type="number" name="max_buy_usd" value="{{ $settings['max_buy_usd'] ?? 10000 }}" step="1" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Daily Buy Limit (USD)</label>
        <input type="number" name="daily_buy_limit" value="{{ $settings['daily_buy_limit'] ?? 50000 }}" step="1" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Daily Withdraw Limit (USD)</label>
        <input type="number" name="daily_withdraw_limit" value="{{ $settings['daily_withdraw_limit'] ?? 10000 }}" step="1" class="form-control">
      </div>
    </div>

    <div class="section-head">P2P Settings</div>
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <label class="form-label">P2P Platform Fee (%)</label>
        <input type="number" name="p2p_fee_pct" value="{{ $settings['p2p_fee_pct'] ?? 0.5 }}" step="0.01" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">P2P Order Expiry (minutes)</label>
        <input type="number" name="p2p_order_expiry_mins" value="{{ $settings['p2p_order_expiry_mins'] ?? 30 }}" step="1" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Min P2P Order (USD)</label>
        <input type="number" name="p2p_min_order_usd" value="{{ $settings['p2p_min_order_usd'] ?? 5 }}" step="0.01" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Max P2P Order (USD)</label>
        <input type="number" name="p2p_max_order_usd" value="{{ $settings['p2p_max_order_usd'] ?? 5000 }}" step="1" class="form-control">
      </div>
    </div>

    <div class="section-head">Maintenance</div>
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="trading_enabled" id="tradingEnabled"
            {{ ($settings['trading_enabled'] ?? true) ? 'checked' : '' }}>
          <label class="form-check-label" for="tradingEnabled" style="font-weight:700">Trading Enabled</label>
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="p2p_enabled" id="p2pEnabled"
            {{ ($settings['p2p_enabled'] ?? true) ? 'checked' : '' }}>
          <label class="form-check-label" for="p2pEnabled" style="font-weight:700">P2P Enabled</label>
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="withdrawals_enabled" id="withdrawalsEnabled"
            {{ ($settings['withdrawals_enabled'] ?? true) ? 'checked' : '' }}>
          <label class="form-check-label" for="withdrawalsEnabled" style="font-weight:700">Withdrawals Enabled</label>
        </div>
      </div>
    </div>

    <div class="section-head">Global Default Fees (%)</div>
    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Default Buy Fee (%)</label>
        <input type="number" name="default_buy_fee" value="{{ $settings['default_buy_fee'] ?? 0.5 }}" step="0.01" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Default Sell Fee (%)</label>
        <input type="number" name="default_sell_fee" value="{{ $settings['default_sell_fee'] ?? 0.5 }}" step="0.01" class="form-control">
      </div>
    </div>
  </div>
</div>

<div style="display:flex;justify-content:flex-end">
  <button type="submit" class="btn btn-primary px-5" style="font-weight:800">Save Settings</button>
</div>
</form>
</div>
@endsection
