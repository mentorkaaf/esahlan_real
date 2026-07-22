@extends('admin.layouts.app')
@section('title', 'Coin Management')

@push('styles')
<style>
.cx{background:#F4F6FB;min-height:100vh;padding:24px 28px}
.cx-card{background:#fff;border-radius:16px;border:1px solid #EEF0F6;overflow:hidden;margin-bottom:20px}
.cx-hdr{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #F1F5F9}
.cx-title{font-size:14px;font-weight:800;color:#0F172A}
.cx-table{width:100%;border-collapse:collapse;font-size:13px}
.cx-table th{padding:9px 16px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#94A3B8;background:#F8FAFC;border-bottom:1px solid #F1F5F9;text-align:left}
.cx-table td{padding:11px 16px;border-bottom:1px solid #F8FAFC;vertical-align:middle}
.pill{padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;display:inline-block}
.pill-on{background:#DCFCE7;color:#15803D}
.pill-off{background:#FEE2E2;color:#B91C1C}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center}
.modal.open{display:flex}
.modal-box{background:#fff;border-radius:20px;padding:28px;width:480px;max-height:90vh;overflow-y:auto}
</style>
@endpush

@section('content')
<div class="cx">
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
  <div>
    <div style="font-size:12px;color:#94A3B8;margin-bottom:2px">Finance / Exchange</div>
    <h1 style="font-size:22px;font-weight:900;color:#0F172A;margin:0">Coin Management</h1>
  </div>
  <button onclick="openModal()" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Add Coin</button>
</div>

@if(session('success'))<div class="alert alert-success mb-3">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger mb-3">{{ session('error') }}</div>@endif

<div class="cx-card">
  <div style="overflow-x:auto">
    <table class="cx-table">
      <thead>
        <tr>
          <th>#</th><th>Symbol</th><th>Name</th><th>Networks</th>
          <th style="text-align:right">Price (USD)</th>
          <th style="text-align:right">Buy Spread</th>
          <th style="text-align:right">Sell Spread</th>
          <th style="text-align:center">Status</th>
          <th style="text-align:center">Buy/Sell</th>
          <th style="text-align:center">P2P</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach($coins as $coin)
        <tr>
          <td style="font-size:11px;color:#94A3B8">{{ $coin->id }}</td>
          <td style="font-weight:900;font-size:15px">{{ $coin->symbol }}</td>
          <td style="font-weight:600">{{ $coin->name }}<div style="font-size:11px;color:#94A3B8;font-family:monospace">{{ $coin->coingecko_id }}</div></td>
          <td>
            @foreach($coin->networks as $n)
            <span style="font-size:10px;background:#EEF2FF;color:#4338CA;padding:2px 7px;border-radius:20px;margin:1px;display:inline-block">{{ $n->name }}</span>
            @endforeach
          </td>
          <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700">
            ${{ number_format($coin->price?->price_usd??0,($coin->price?->price_usd??0)>10?2:6) }}
          </td>
          <td style="text-align:right">{{ $coin->buy_fee_pct }}%</td>
          <td style="text-align:right">{{ $coin->sell_fee_pct }}%</td>
          <td style="text-align:center">
            <form method="POST" action="{{ route('admin.crypto.coins.toggle',$coin->id) }}" style="display:inline">@csrf @method('PATCH')
              <button type="submit" class="pill {{ $coin->is_active?'pill-on':'pill-off' }}" style="cursor:pointer;border:none">
                {{ $coin->is_active?'Active':'Disabled' }}
              </button>
            </form>
          </td>
          <td style="text-align:center">
            <span class="pill {{ $coin->buy_enabled?'pill-on':'pill-off' }}">{{ $coin->buy_enabled?'Yes':'No' }}</span>
          </td>
          <td style="text-align:center">
            <span class="pill {{ $coin->p2p_enabled?'pill-on':'pill-off' }}">{{ $coin->p2p_enabled?'Yes':'No' }}</span>
          </td>
          <td>
            <a href="{{ route('admin.crypto.coins.settings',$coin->id) }}" class="btn btn-sm btn-outline-secondary" style="font-size:11px;padding:3px 8px">Settings</a>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

{{-- Price Override Section --}}
<div class="cx-card">
  <div class="cx-hdr"><div class="cx-title">Manual Price Override</div><span style="font-size:11px;color:#94A3B8">Overrides CoinGecko for 1 hour</span></div>
  <form method="POST" action="{{ route('admin.crypto.prices.override') }}" style="padding:20px;display:flex;gap:12px;flex-wrap:wrap">
    @csrf
    <select name="coin_id" class="form-control" style="width:180px">
      @foreach($coins as $c)<option value="{{ $c->id }}">{{ $c->symbol }} - {{ $c->name }}</option>@endforeach
    </select>
    <input type="number" name="price" step="0.000001" placeholder="Price in USD" class="form-control" style="width:200px" required>
    <button type="submit" class="btn btn-warning">Override Price</button>
  </form>
</div>

{{-- Spread Config --}}
<div class="cx-card">
  <div class="cx-hdr"><div class="cx-title">Spread Configuration</div></div>
  <form method="POST" action="{{ route('admin.crypto.coins.spread') }}" style="padding:20px">
    @csrf
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px">
      @foreach($coins as $c)
      <div style="background:#F8FAFC;border-radius:10px;padding:14px">
        <div style="font-weight:800;margin-bottom:10px">{{ $c->symbol }}</div>
        <input type="hidden" name="coins[]" value="{{ $c->id }}">
        <div style="display:flex;gap:8px">
          <div>
            <label style="font-size:10px;color:#94A3B8;font-weight:700;display:block">Buy %</label>
            <input type="number" name="buy_spread[{{ $c->id }}]" step=".01" value="{{ $c->buy_fee_pct }}" class="form-control form-control-sm" style="width:80px">
          </div>
          <div>
            <label style="font-size:10px;color:#94A3B8;font-weight:700;display:block">Sell %</label>
            <input type="number" name="sell_spread[{{ $c->id }}]" step=".01" value="{{ $c->sell_fee_pct }}" class="form-control form-control-sm" style="width:80px">
          </div>
        </div>
      </div>
      @endforeach
    </div>
    <button type="submit" class="btn btn-primary mt-3">Save Spreads</button>
  </form>
</div>
</div>

{{-- Add Coin Modal --}}
<div class="modal" id="addModal">
  <div class="modal-box">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <h3 style="margin:0;font-size:17px;font-weight:800">Add New Coin</h3>
      <button onclick="closeModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#94A3B8">×</button>
    </div>
    <form method="POST" action="{{ route('admin.crypto.coins.store') }}">
      @csrf
      <div class="mb-3"><label class="form-label">Symbol *</label><input type="text" name="symbol" class="form-control" placeholder="BTC" required></div>
      <div class="mb-3"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" placeholder="Bitcoin" required></div>
      <div class="mb-3"><label class="form-label">CoinGecko ID *</label><input type="text" name="coingecko_id" class="form-control" placeholder="bitcoin" required></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px" class="mb-3">
        <div><label class="form-label">Buy Spread %</label><input type="number" name="buy_spread" class="form-control" value="1.5" step=".01"></div>
        <div><label class="form-label">Sell Spread %</label><input type="number" name="sell_spread" class="form-control" value="1.5" step=".01"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px" class="mb-3">
        <div><label class="form-label">Min Buy</label><input type="number" name="min_trade_amount" class="form-control" value="5" step=".01"></div>
        <div><label class="form-label">Max Buy</label><input type="number" name="max_trade_amount" class="form-control" value="10000" step=".01"></div>
        <div><label class="form-label">Decimals</label><input type="number" name="decimal_places" class="form-control" value="8"></div>
      </div>
      <div class="mb-3">
        <label class="form-label">Networks (one per line: NAME,SYMBOL,FEE)</label>
        <textarea name="networks_raw" class="form-control" rows="3" placeholder="TRC20,TRON,1.00&#10;ERC20,ETH,5.00"></textarea>
      </div>
      <div style="display:flex;gap:12px" class="mb-3">
        <label><input type="checkbox" name="is_active" checked> Active</label>
        <label><input type="checkbox" name="is_tradable" checked> Tradable</label>
        <label><input type="checkbox" name="is_p2p_enabled" checked> P2P</label>
      </div>
      <button type="submit" class="btn btn-primary w-100">Add Coin</button>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
function openModal(){document.getElementById('addModal').classList.add('open')}
function closeModal(){document.getElementById('addModal').classList.remove('open')}
document.getElementById('addModal').addEventListener('click',e=>{if(e.target===e.currentTarget)closeModal()})
</script>
@endpush
