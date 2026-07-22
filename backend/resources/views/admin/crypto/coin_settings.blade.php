@extends('admin.layouts.app')
@section('title', 'Coin Settings — {{ $coin->symbol }}')

@push('styles')
<style>
.cx{background:#F4F6FB;min-height:100vh;padding:24px 28px}
.cx-card{background:#fff;border-radius:16px;border:1px solid #EEF0F6;overflow:hidden;margin-bottom:20px}
.cx-hdr{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #F1F5F9}
.cx-title{font-size:14px;font-weight:800;color:#0F172A}
.cx-body{padding:24px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #F1F5F9}
.toggle-row:last-child{border-bottom:none}
.toggle-label{font-size:13px;font-weight:700;color:#0F172A}
.toggle-sub{font-size:11px;color:#94A3B8;margin-top:2px}
.tog{position:relative;width:44px;height:24px;cursor:pointer}
.tog input{opacity:0;width:0;height:0}
.tog-slider{position:absolute;inset:0;background:#CBD5E1;border-radius:24px;transition:.3s}
.tog-slider:before{content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s}
.tog input:checked+.tog-slider{background:#22C55E}
.tog input:checked+.tog-slider:before{transform:translateX(20px)}
.pill-net{background:#EEF2FF;color:#4338CA;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;display:inline-block;margin:2px}
</style>
@endpush

@section('content')
<div class="cx">
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
  <a href="{{ route('admin.crypto.coins') }}" style="color:#94A3B8;text-decoration:none;font-size:13px">← Coins</a>
  <span style="color:#CBD5E1">/</span>
  <h1 style="font-size:20px;font-weight:900;color:#0F172A;margin:0">{{ $coin->symbol }} Settings</h1>
  <span class="pill" style="background:{{ $coin->is_active?'#DCFCE7':'#FEE2E2' }};color:{{ $coin->is_active?'#15803D':'#B91C1C' }};padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700">
    {{ $coin->is_active?'Active':'Disabled' }}
  </span>
</div>

@if(session('success'))<div class="alert alert-success mb-3">{{ session('success') }}</div>@endif

<form method="POST" action="{{ route('admin.crypto.coins.update',$coin->id) }}">
@csrf

{{-- Basic Info --}}
<div class="cx-card">
  <div class="cx-hdr"><div class="cx-title">Basic Info</div></div>
  <div class="cx-body">
    <div class="form-grid">
      <div><label class="form-label fw-bold">Symbol</label><input type="text" name="symbol" class="form-control" value="{{ $coin->symbol }}" readonly style="background:#F8FAFC"></div>
      <div><label class="form-label fw-bold">Name</label><input type="text" name="name" class="form-control" value="{{ $coin->name }}"></div>
      <div><label class="form-label fw-bold">CoinGecko ID</label><input type="text" name="coingecko_id" class="form-control" value="{{ $coin->coingecko_id }}"></div>
      <div><label class="form-label fw-bold">Decimal Places</label><input type="number" name="decimals" class="form-control" value="{{ $coin->decimals }}"></div>
    </div>
  </div>
</div>

{{-- Fees & Limits --}}
<div class="cx-card">
  <div class="cx-hdr"><div class="cx-title">Fees & Limits</div></div>
  <div class="cx-body">
    <div class="form-grid-3">
      <div><label class="form-label fw-bold">Buy Fee %</label><input type="number" name="buy_fee_pct" step=".01" class="form-control" value="{{ $coin->buy_fee_pct }}"></div>
      <div><label class="form-label fw-bold">Sell Fee %</label><input type="number" name="sell_fee_pct" step=".01" class="form-control" value="{{ $coin->sell_fee_pct }}"></div>
      <div><label class="form-label fw-bold">Withdrawal Fee</label><input type="number" name="withdrawal_fee" step=".00001" class="form-control" value="{{ $coin->withdrawal_fee }}"></div>
      <div><label class="form-label fw-bold">Min Deposit</label><input type="number" name="min_deposit" step=".00001" class="form-control" value="{{ $coin->min_deposit }}"></div>
      <div><label class="form-label fw-bold">Min Withdrawal</label><input type="number" name="min_withdrawal" step=".00001" class="form-control" value="{{ $coin->min_withdrawal }}"></div>
      <div><label class="form-label fw-bold">Max Withdrawal</label><input type="number" name="max_withdrawal" step=".00001" class="form-control" value="{{ $coin->max_withdrawal }}"></div>
    </div>
  </div>
</div>

{{-- Feature Toggles --}}
<div class="cx-card">
  <div class="cx-hdr"><div class="cx-title">Feature Toggles</div></div>
  <div class="cx-body" style="padding:16px 24px">
    @foreach([
      ['is_active',          'Active',              'Coin visible to users'],
      ['deposit_enabled',    'Deposits',            'Allow incoming deposits'],
      ['withdrawal_enabled', 'Withdrawals',         'Allow outgoing withdrawals'],
      ['buy_enabled',        'Buy',                 'Allow buy orders'],
      ['sell_enabled',       'Sell',                'Allow sell orders'],
      ['p2p_enabled',        'P2P Trading',         'Allow P2P ads and orders'],
    ] as [$key,$label,$sub])
    <div class="toggle-row">
      <div>
        <div class="toggle-label">{{ $label }}</div>
        <div class="toggle-sub">{{ $sub }}</div>
      </div>
      <label class="tog">
        <input type="hidden" name="{{ $key }}" value="0">
        <input type="checkbox" name="{{ $key }}" value="1" {{ $coin->$key ? 'checked' : '' }}>
        <span class="tog-slider"></span>
      </label>
    </div>
    @endforeach
  </div>
</div>

<button type="submit" class="btn btn-primary px-5">Save Changes</button>
</form>

{{-- Networks --}}
<div class="cx-card mt-4">
  <div class="cx-hdr">
    <div class="cx-title">Networks</div>
    <button onclick="document.getElementById('addNet').style.display='block'" class="btn btn-sm btn-outline-primary">+ Add Network</button>
  </div>
  <div style="padding:16px">
    @forelse($coin->networks as $net)
    <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #F1F5F9">
      <span class="pill-net">{{ $net->name }}</span>
      <span style="font-size:12px;color:#64748B">{{ $net->chain }}</span>
      <span style="font-size:12px;color:#64748B">Fee: {{ $net->withdrawal_fee }}</span>
      <span style="font-size:11px;background:{{ $net->is_active?'#DCFCE7':'#FEE2E2' }};color:{{ $net->is_active?'#15803D':'#B91C1C' }};padding:2px 8px;border-radius:10px;font-weight:700">{{ $net->is_active?'Active':'Off' }}</span>
    </div>
    @empty
    <p style="color:#94A3B8;font-size:13px">No networks configured.</p>
    @endforelse

    {{-- Add network inline --}}
    <div id="addNet" style="display:none;margin-top:16px;background:#F8FAFC;border-radius:12px;padding:16px">
      <form method="POST" action="{{ route('admin.crypto.coins.update',$coin->id) }}">@csrf
        <input type="hidden" name="_action" value="add_network">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:10px;align-items:end">
          <div><label class="form-label" style="font-size:11px;font-weight:700">Network Name</label><input type="text" name="net_name" class="form-control form-control-sm" placeholder="TRC20" required></div>
          <div><label class="form-label" style="font-size:11px;font-weight:700">Symbol</label><input type="text" name="net_symbol" class="form-control form-control-sm" placeholder="TRON"></div>
          <div><label class="form-label" style="font-size:11px;font-weight:700">Withdrawal Fee</label><input type="number" name="net_fee" step=".00001" value="1" class="form-control form-control-sm"></div>
          <button type="submit" class="btn btn-sm btn-primary">Add</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Price --}}
<div class="cx-card">
  <div class="cx-hdr"><div class="cx-title">Current Price</div></div>
  <div class="cx-body">
    <div style="display:flex;gap:24px;flex-wrap:wrap">
      <div>
        <div style="font-size:10px;color:#94A3B8;font-weight:700;letter-spacing:.6px">USD PRICE</div>
        <div style="font-size:28px;font-weight:900;color:#0F172A">${{ number_format($coin->price?->price_usd??0,$coin->price?->price_usd>10?2:6) }}</div>
      </div>
      <div>
        <div style="font-size:10px;color:#94A3B8;font-weight:700;letter-spacing:.6px">24H CHANGE</div>
        <div style="font-size:20px;font-weight:900;color:{{ ($coin->price?->change_24h??0)>=0?'#15803D':'#EF4444' }}">
          {{ ($coin->price?->change_24h??0)>=0?'+':'' }}{{ number_format($coin->price?->change_24h??0,2) }}%
        </div>
      </div>
      <div>
        <div style="font-size:10px;color:#94A3B8;font-weight:700;letter-spacing:.6px">LAST UPDATED</div>
        <div style="font-size:13px;font-weight:700;color:#64748B">{{ $coin->price?->fetched_at?->diffForHumans() ?? 'Never' }}</div>
      </div>
    </div>
    <div style="margin-top:16px">
      <form method="POST" action="{{ route('admin.crypto.prices.override') }}" style="display:flex;gap:10px;align-items:flex-end">
        @csrf
        <input type="hidden" name="coin_id" value="{{ $coin->id }}">
        <div><label class="form-label" style="font-size:11px;font-weight:700">Override Price (USD)</label><input type="number" name="price" step="0.000001" class="form-control" style="width:180px" placeholder="0.00"></div>
        <button type="submit" class="btn btn-warning">Override 1h</button>
      </form>
    </div>
  </div>
</div>

</div>
@endsection
