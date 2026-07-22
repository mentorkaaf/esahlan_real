@extends('admin.layouts.app')
@section('title', 'Exchange Dashboard')

@push('styles')
<style>
.cx { background:#F4F6FB; min-height:100vh; padding:24px 28px; }
.cx-card { background:#fff; border-radius:16px; border:1px solid #EEF0F6; overflow:hidden; margin-bottom:20px; }
.cx-hdr { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid #F1F5F9; }
.cx-title { font-size:14px; font-weight:800; color:#0F172A; }
.kpi4 { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:20px; }
.kpi { background:#fff; border-radius:14px; border:1px solid #EEF0F6; padding:18px 20px; }
.kpi-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#94A3B8; margin-bottom:6px; }
.kpi-value { font-size:22px; font-weight:900; color:#0F172A; letter-spacing:-.5px; }
.kpi-sub { font-size:11px; color:#94A3B8; margin-top:3px; }
.cx-table { width:100%; border-collapse:collapse; font-size:13px; }
.cx-table th { padding:9px 16px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#94A3B8; background:#F8FAFC; border-bottom:1px solid #F1F5F9; text-align:left; }
.cx-table td { padding:11px 16px; border-bottom:1px solid #F8FAFC; vertical-align:middle; }
.pill { padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700; display:inline-block; }
.pill-buy { background:#DCFCE7; color:#15803D; }
.pill-sell { background:#FEE2E2; color:#B91C1C; }
.pill-completed { background:#DCFCE7; color:#15803D; }
.pill-pending { background:#FEF3C7; color:#B45309; }
.two-col { display:grid; grid-template-columns:2fr 1fr; gap:16px; }
</style>
@endpush

@section('content')
<div class="cx">

{{-- Header --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <div style="font-size:12px;color:#94A3B8;margin-bottom:2px">Finance / Exchange</div>
    <h1 style="font-size:22px;font-weight:900;color:#0F172A;margin:0">Exchange Dashboard</h1>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <form method="POST" action="{{ route('admin.crypto.prices.refresh') }}" style="margin:0">@csrf
      <button class="btn btn-sm btn-secondary"><i class="fas fa-sync"></i> Refresh Prices</button>
    </form>
    <a href="{{ route('admin.crypto.withdrawals') }}" class="btn btn-sm btn-primary">Withdrawals</a>
    <a href="{{ route('admin.crypto.p2p') }}" class="btn btn-sm btn-warning">P2P / Escrow</a>
  </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

{{-- KPIs --}}
<div class="kpi4">
  <div class="kpi">
    <div class="kpi-label">Total Holdings</div>
    <div class="kpi-value">${{ number_format($stats['total_holdings'],2) }}</div>
    <div class="kpi-sub">All user wallets</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">24h Volume</div>
    <div class="kpi-value" style="color:#3B82F6">${{ number_format($stats['volume_24h'],2) }}</div>
    <div class="kpi-sub">Buy ${{ number_format($stats['buy_volume'],2) }} · Sell ${{ number_format($stats['sell_volume'],2) }}</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">P2P Volume</div>
    <div class="kpi-value" style="color:#7C3AED">${{ number_format($stats['p2p_volume'],2) }}</div>
    <div class="kpi-sub">{{ $stats['p2p_in_escrow'] }} in escrow</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Pending Withdrawals</div>
    <div class="kpi-value" style="color:{{ $stats['pending_wd']>0?'#EF4444':'#94A3B8' }}">{{ $stats['pending_wd'] }}</div>
    <div class="kpi-sub">${{ number_format($stats['pending_wd_amount'],2) }} total</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Revenue Today</div>
    <div class="kpi-value" style="color:#15803D">${{ number_format($stats['revenue_today'],2) }}</div>
    <div class="kpi-sub">Month: ${{ number_format($stats['revenue_month'],2) }}</div>
  </div>
</div>

<div class="two-col">
  {{-- Daily Volume Chart --}}
  <div class="cx-card">
    <div class="cx-hdr"><div class="cx-title">Volume — Last 7 Days</div></div>
    <div style="padding:20px"><canvas id="volChart" height="220"></canvas></div>
  </div>

  {{-- Top Coins --}}
  <div class="cx-card">
    <div class="cx-hdr"><div class="cx-title">Top Coins by Volume</div></div>
    <div style="overflow-x:auto">
      <table class="cx-table">
        <thead><tr><th>Coin</th><th style="text-align:right">Volume</th><th style="text-align:right">Orders</th></tr></thead>
        <tbody>
          @foreach($topCoins as $tc)
          <tr>
            <td style="font-weight:700">{{ $tc->symbol }}<span style="font-size:11px;color:#94A3B8;margin-left:6px">{{ $tc->name }}</span></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums">${{ number_format($tc->volume,2) }}</td>
            <td style="text-align:right;color:#94A3B8">{{ $tc->orders }}</td>
          </tr>
          @endforeach
          @if($topCoins->isEmpty())<tr><td colspan="3" style="text-align:center;padding:20px;color:#94A3B8">No data yet</td></tr>@endif
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Live Coin Prices --}}
<div class="cx-card">
  <div class="cx-hdr">
    <div class="cx-title">Live Prices</div>
    <a href="{{ route('admin.crypto.coins') }}" style="font-size:12px;color:#3B82F6;text-decoration:none">Manage Coins →</a>
  </div>
  <div style="overflow-x:auto">
    <table class="cx-table">
      <thead><tr><th>Coin</th><th style="text-align:right">Price</th><th style="text-align:right">24h %</th><th style="text-align:right">Volume</th><th style="text-align:center">Status</th></tr></thead>
      <tbody>
        @foreach($coins as $coin)
        <tr>
          <td style="font-weight:800">{{ $coin->symbol }}<span style="font-size:11px;color:#94A3B8;margin-left:6px">{{ $coin->name }}</span></td>
          <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700">${{ number_format($coin->price?->price_usd??0,($coin->price?->price_usd??0)>10?2:6) }}</td>
          <td style="text-align:right;font-weight:700;color:{{ ($coin->price?->change_24h??0)>=0?'#15803D':'#EF4444' }}">
            {{ ($coin->price?->change_24h??0)>=0?'+':'' }}{{ number_format($coin->price?->change_24h??0,2) }}%
          </td>
          <td style="text-align:right;font-size:12px;color:#64748B">${{ number_format(($coin->price?->volume_24h??0)/1000000,1) }}M</td>
          <td style="text-align:center">
            <span class="pill" style="background:{{ $coin->is_active?'#DCFCE7':'#FEE2E2' }};color:{{ $coin->is_active?'#15803D':'#B91C1C' }}">
              {{ $coin->is_active?'Active':'Disabled' }}
            </span>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

{{-- Recent Transactions --}}
<div class="cx-card">
  <div class="cx-hdr">
    <div class="cx-title">Recent Orders</div>
    <a href="{{ route('admin.crypto.orders') }}" style="font-size:12px;color:#3B82F6;text-decoration:none">View All →</a>
  </div>
  <div style="overflow-x:auto">
    <table class="cx-table">
      <thead><tr><th>#</th><th>User</th><th>Coin</th><th style="text-align:center">Side</th><th style="text-align:right">Amount</th><th style="text-align:center">Status</th><th style="text-align:right">Date</th></tr></thead>
      <tbody>
        @foreach($recentTx as $tx)
        <tr>
          <td style="font-size:11px;color:#94A3B8;font-family:monospace">{{ substr($tx->uuid,0,8) }}</td>
          <td style="font-weight:600">{{ $tx->user_name }}</td>
          <td style="font-weight:800">{{ $tx->symbol }}</td>
          <td style="text-align:center"><span class="pill pill-{{ $tx->side }}">{{ strtoupper($tx->side) }}</span></td>
          <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700">${{ number_format($tx->total_usd,2) }}</td>
          <td style="text-align:center"><span class="pill pill-{{ $tx->status }}">{{ strtoupper($tx->status) }}</span></td>
          <td style="text-align:right;font-size:11px;color:#94A3B8">{{ \Carbon\Carbon::parse($tx->created_at)->format('M d H:i') }}</td>
        </tr>
        @endforeach
        @if($recentTx->isEmpty())<tr><td colspan="7" style="text-align:center;padding:30px;color:#94A3B8">No transactions yet</td></tr>@endif
      </tbody>
    </table>
  </div>
</div>

{{-- Disputes Alert --}}
@if($stats['open_disputes'] > 0)
<div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:12px;padding:14px 18px;display:flex;align-items:center;gap:12px">
  <i class="fas fa-exclamation-triangle" style="color:#B45309;font-size:20px"></i>
  <div>
    <div style="font-weight:800;color:#92400E">{{ $stats['open_disputes'] }} Open Dispute(s)</div>
    <div style="font-size:12px;color:#B45309">Requires admin attention → <a href="{{ route('admin.crypto.p2p',['status'=>'disputed']) }}" style="color:#92400E;font-weight:700">Review Now</a></div>
  </div>
</div>
@endif

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const daily = @json($dailyVolume);
new Chart(document.getElementById('volChart'), {
  type:'bar',
  data:{
    labels: daily.map(d=>d.date),
    datasets:[
      { label:'Buy', data:daily.map(d=>parseFloat(d.buy_vol)||0), backgroundColor:'rgba(34,197,94,.7)', borderRadius:6 },
      { label:'Sell',data:daily.map(d=>parseFloat(d.sell_vol)||0), backgroundColor:'rgba(239,68,68,.7)',  borderRadius:6 },
    ]
  },
  options:{
    responsive:true,maintainAspectRatio:false,
    plugins:{ legend:{position:'top'} },
    scales:{
      x:{ grid:{display:false}, ticks:{font:{size:10}} },
      y:{ grid:{color:'#F1F5F9'}, ticks:{font:{size:10},callback:v=>'$'+v} },
      stacked:true,
    }
  }
});
</script>
@endpush
