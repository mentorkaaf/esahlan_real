@extends('admin.layouts.app')
@section('title', 'Reward Settings')
@section('content')
<div class="page-header d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 class="mb-0 fw-bold">⭐ Reward &amp; Points System</h4>
    <small class="text-muted">Configure how customers earn and redeem eSahlan Points</small>
  </div>
  <a href="{{ route('admin.rewards.ledger') }}" class="btn btn-outline-primary btn-sm">View Ledger</a>
</div>

{{-- Stats row --}}
<div class="row g-3 mb-4">
  @foreach([
    ['Users with Points', number_format($totalUsers), 'people-fill', 'primary'],
    ['Total Points in Circulation', number_format($totalPts), 'star-fill', 'warning'],
    ['Total Earned (all time)', number_format($totalEarned), 'arrow-up-circle-fill', 'success'],
    ['Total Redeemed (all time)', number_format($totalRedeemed), 'arrow-down-circle-fill', 'info'],
  ] as [$label, $val, $icon, $color])
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle bg-{{ $color }} bg-opacity-10 p-3">
          <i class="bi bi-{{ $icon }} text-{{ $color }} fs-5"></i>
        </div>
        <div>
          <div class="fw-bold fs-5">{{ $val }}</div>
          <div class="text-muted small">{{ $label }}</div>
        </div>
      </div>
    </div>
  </div>
  @endforeach
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<form method="POST" action="{{ route('admin.rewards.update') }}">
  @csrf @method('PATCH')

  {{-- Master toggle --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-bold">System Status</div>
    <div class="card-body d-flex align-items-center gap-3">
      <div class="form-check form-switch fs-5 mb-0">
        <input class="form-check-input" type="checkbox" name="reward_enabled" id="rewardEnabled" value="1"
          {{ ($settings['reward_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
        <label class="form-check-label fw-semibold" for="rewardEnabled">Rewards Enabled</label>
      </div>
      <small class="text-muted">When disabled, no points are earned or redeemed across all modules.</small>
    </div>
  </div>

  {{-- Core rules --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-bold">Points Rules</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-semibold">Points per $1 spent</label>
          <input type="number" name="points_per_dollar" class="form-control" value="{{ $settings['points_per_dollar'] ?? 10 }}" min="1" max="1000">
          <div class="form-text">e.g. 10 = earn 10 pts per dollar</div>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Points needed for $1 discount</label>
          <input type="number" name="points_to_dollar" class="form-control" value="{{ $settings['points_to_dollar'] ?? 100 }}" min="1" max="10000">
          <div class="form-text">e.g. 100 = 100 pts = $1 off</div>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Points expiry (days)</label>
          <input type="number" name="points_expire_days" class="form-control" value="{{ $settings['points_expire_days'] ?? 365 }}" min="30" max="3650">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Min order to earn ($)</label>
          <input type="number" name="min_order_for_points" class="form-control" step="0.01" value="{{ $settings['min_order_for_points'] ?? 2 }}" min="0">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Max redeem per order (%)</label>
          <input type="number" name="max_redeem_percent" class="form-control" value="{{ $settings['max_redeem_percent'] ?? 50 }}" min="1" max="100">
          <div class="form-text">e.g. 50 = user can pay max 50% of order with points</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Module multipliers --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-bold">Module Earn Multipliers</div>
    <div class="card-body">
      <p class="text-muted small mb-3">Value ÷ 10 = multiplier. 10 = 1× (normal), 20 = 2× (double), 5 = 0.5× (half)</p>
      <div class="row g-3">
        @foreach([
          ['efood','eFood'],['egrocery','eGrocery'],['eshop','eShop'],['eparcel','eParcel'],
          ['emoving','eMoving'],['erent','eRent'],['eticket','eTicket'],['elearning','eLearning'],
          ['eexchange','eExchange'],['elaundry','eLaundry'],['edata','eData'],['ehealth','eHealth'],
        ] as [$slug, $label])
        <div class="col-6 col-md-3">
          <label class="form-label fw-semibold small">{{ $label }}</label>
          @php $val = $settings["pts_mult_{$slug}"] ?? 10; $mult = $val / 10; @endphp
          <div class="input-group">
            <input type="number" name="pts_mult_{{ $slug }}" class="form-control mult-input" data-target="mult-{{ $slug }}" value="{{ $val }}" min="1" max="100">
            <span class="input-group-text text-primary fw-bold mult-label" id="mult-{{ $slug }}">{{ $mult }}×</span>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Referral Program --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-bold d-flex align-items-center justify-content-between">
      <span>🔗 Referral Program</span>
      <div class="d-flex gap-3 small text-muted">
        <span>Total referrals: <strong>{{ number_format($totalReferrals) }}</strong></span>
        <span>Rewarded: <strong class="text-success">{{ number_format($rewardedReferrals) }}</strong></span>
      </div>
    </div>
    <div class="card-body">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="form-check form-switch fs-5 mb-0">
          <input class="form-check-input" type="checkbox" name="referral_enabled" id="referralEnabled" value="1"
            {{ ($settings['referral_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
          <label class="form-check-label fw-semibold" for="referralEnabled">Referral Rewards Enabled</label>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label fw-semibold">Base reward (pts)</label>
          <input type="number" name="referral_reward_pts" class="form-control" value="{{ $settings['referral_reward_pts'] ?? 500 }}" min="0" max="10000">
          <div class="form-text">Fixed pts given to referrer on friend's first order</div>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Commission — Level 1 (%)</label>
          <input type="number" name="referral_commission_pct" class="form-control" value="{{ $settings['referral_commission_pct'] ?? 5 }}" min="0" max="50">
          <div class="form-text">% of friend's order value as pts (in addition to base)</div>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Commission — Level 2 (%)</label>
          <input type="number" name="referral_l2_pct" class="form-control" value="{{ $settings['referral_l2_pct'] ?? 2 }}" min="0" max="20">
          <div class="form-text">% to the person who referred the referrer</div>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Min order to trigger ($)</label>
          <input type="number" name="referral_min_order" class="form-control" value="{{ $settings['referral_min_order'] ?? 5 }}" min="0" max="1000">
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary px-4 fw-bold">Save Settings</button>
    <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.mult-input').forEach(input => {
  const update = () => {
    const label = document.getElementById(input.dataset.target);
    if (label) label.textContent = (parseFloat(input.value) / 10).toFixed(1) + '×';
  };
  input.addEventListener('input', update);
  update();
});
</script>
@endpush
