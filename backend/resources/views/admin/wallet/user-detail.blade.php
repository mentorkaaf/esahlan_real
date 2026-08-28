@extends('admin.layouts.app')
@section('title', 'ePay — ' . $user->name)

@push('styles')
<style>
.ep { background:#F4F6FB; min-height:100vh; padding:24px 28px; }
.ep-card { background:#fff; border-radius:16px; border:1px solid #EEF0F6; overflow:hidden; margin-bottom:20px; }
.ep-card-hdr { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid #F1F5F9; }
.ep-card-title { font-size:14px; font-weight:800; color:#0F172A; }
.ep-table { width:100%; border-collapse:collapse; font-size:13px; }
.ep-table th { padding:9px 16px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#94A3B8; background:#F8FAFC; border-bottom:1px solid #F1F5F9; text-align:left; }
.ep-table td { padding:11px 16px; border-bottom:1px solid #F8FAFC; vertical-align:middle; }
.ep-table tr:last-child td { border-bottom:none; }
.pill { padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700; display:inline-block; }
.pill-credit { background:#DCFCE7; color:#15803D; }
.pill-debit  { background:#FEE2E2; color:#B91C1C; }
.pill-pending   { background:#FEF3C7; color:#B45309; }
.pill-approved  { background:#DCFCE7; color:#15803D; }
.pill-rejected  { background:#FEE2E2; color:#B91C1C; }
.pill-processed { background:#EFF6FF; color:#1D4ED8; }
.kpi-grid4 { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
.kpi { background:#fff; border-radius:14px; border:1px solid #EEF0F6; padding:18px 20px; }
.kpi-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#94A3B8; margin-bottom:6px; }
.kpi-value { font-size:24px; font-weight:900; color:#0F172A; letter-spacing:-.5px; }
.kpi-sub { font-size:11px; color:#94A3B8; margin-top:4px; }
.two-col { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.ep-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; border:1.5px solid #E2E8F0; background:#fff; color:#374151; text-decoration:none; }
.ep-btn-primary { background:#07003B; color:#fff; border-color:#07003B; }
[x-cloak] { display: none !important; }
</style>
@endpush

@section('content')
<div class="ep">

{{-- Header --}}
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div style="display:flex;align-items:center;gap:16px">
    <div style="width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,#07003B,#3B82F6);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:900;color:#fff">
      {{ strtoupper(substr($user->name,0,1)) }}
    </div>
    <div>
      <div style="font-size:12px;color:#94A3B8;margin-bottom:2px"><a href="{{ route('admin.wallet.index') }}" style="color:#94A3B8;text-decoration:none">ePay</a> / Financial Profile</div>
      <h1 style="font-size:22px;font-weight:900;color:#0F172A;margin:0;letter-spacing:-.5px">{{ $user->name }}</h1>
      <div style="font-size:12px;color:#94A3B8">{{ $user->email }} · {{ $user->phone }}</div>
    </div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <a href="{{ route('admin.wallet.transactions', ['user_id' => $user->id]) }}" class="ep-btn"><i class="fas fa-list"></i> All Transactions</a>

    {{-- Statement Download --}}
    <div style="position:relative;display:inline-block" x-data="{ open: false }">
      <button @click="open = !open" class="ep-btn" style="background:#07003B;color:#fff;border-color:#07003B;gap:6px">
        <i class="fas fa-file-download"></i> Download Statement <i class="fas fa-chevron-down" style="font-size:9px"></i>
      </button>
      <div x-show="open" @click.outside="open=false" x-cloak
           style="position:absolute;right:0;top:calc(100% + 6px);background:#fff;border:1px solid #E2E8F0;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:99;min-width:230px;overflow:hidden">

        {{-- Period selector --}}
        <div style="padding:12px 16px;border-bottom:1px solid #F1F5F9">
          <div style="font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px">Period</div>
          <form id="stmtForm" method="GET" action="{{ route('admin.wallet.user-statement', $user->id) }}" style="display:flex;gap:6px;flex-direction:column">
            <div style="display:flex;gap:6px">
              <input type="date" name="from" id="stmtFrom" style="flex:1;padding:5px 8px;border:1px solid #E2E8F0;border-radius:7px;font-size:11px;color:#374151">
              <input type="date" name="to"   id="stmtTo"   style="flex:1;padding:5px 8px;border:1px solid #E2E8F0;border-radius:7px;font-size:11px;color:#374151">
            </div>
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:2px">
              @foreach(['This Month' => [now()->startOfMonth()->format('Y-m-d'), now()->format('Y-m-d')], 'Last 3 Months' => [now()->subMonths(3)->format('Y-m-d'), now()->format('Y-m-d')], 'This Year' => [now()->startOfYear()->format('Y-m-d'), now()->format('Y-m-d')], 'All Time' => ['','']] as $label => $range)
              <button type="button" onclick="setRange('{{ $range[0] }}','{{ $range[1] }}')"
                style="font-size:9px;font-weight:700;padding:3px 8px;border-radius:6px;border:1px solid #E2E8F0;background:#F8FAFC;color:#374151;cursor:pointer">
                {{ $label }}
              </button>
              @endforeach
            </div>
          </form>
        </div>

        {{-- Download buttons --}}
        <div style="padding:10px 12px;display:flex;flex-direction:column;gap:6px">
          <button onclick="downloadStmt('pdf')"
            style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:9px;border:none;background:#FEF2F2;color:#B91C1C;font-weight:700;font-size:12px;cursor:pointer;text-align:left">
            <i class="fas fa-file-pdf" style="font-size:16px"></i>
            <div>
              <div>Download PDF</div>
              <div style="font-size:9px;font-weight:400;color:#94A3B8">Bank-style statement</div>
            </div>
          </button>
          <button onclick="downloadStmt('excel')"
            style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:9px;border:none;background:#F0FDF4;color:#15803D;font-weight:700;font-size:12px;cursor:pointer;text-align:left">
            <i class="fas fa-file-excel" style="font-size:16px"></i>
            <div>
              <div>Download Excel</div>
              <div style="font-size:9px;font-weight:400;color:#94A3B8">Full data with 2 sheets</div>
            </div>
          </button>
        </div>
      </div>
    </div>

    @if($wallet?->is_frozen)
      <form method="POST" action="{{ route('admin.wallet.unfreeze', $user->id) }}" style="margin:0">@csrf<button type="submit" class="ep-btn ep-btn-primary" onclick="return confirm('Unfreeze?')"><i class="fas fa-unlock"></i> Unfreeze</button></form>
    @endif
    <a href="{{ route('admin.wallet.index') }}" class="ep-btn"><i class="fas fa-arrow-left"></i></a>
  </div>
</div>

{{-- Status Banner (if frozen) --}}
@if($wallet?->is_frozen)
<div style="background:#EDE9FE;border:1px solid #C4B5FD;border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
  <i class="fas fa-snowflake" style="color:#7C3AED;font-size:20px"></i>
  <div>
    <div style="font-weight:800;color:#6D28D9;font-size:14px">Wallet Frozen</div>
    <div style="font-size:12px;color:#7C3AED">Reason: {{ $wallet->frozen_reason }} · Since {{ \Carbon\Carbon::parse($wallet->frozen_at)->format('M d, Y H:i') }}</div>
  </div>
</div>
@endif

{{-- KPIs --}}
<div class="kpi-grid4">
  <div class="kpi">
    <div class="kpi-label">Current Balance</div>
    <div class="kpi-value" style="color:{{ ($wallet?->balance??0)>0?'#15803D':'#94A3B8' }}">${{ number_format($wallet?->balance??0,2) }}</div>
    <div class="kpi-sub">{{ $wallet ? 'Active wallet' : 'No wallet yet' }}</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Total Earned</div>
    <div class="kpi-value" style="color:#3B82F6">${{ number_format($wallet?->total_earned??0,2) }}</div>
    <div class="kpi-sub">All-time credits</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Total Withdrawn</div>
    <div class="kpi-value" style="color:#EF4444">${{ number_format($wallet?->total_withdrawn??0,2) }}</div>
    <div class="kpi-sub">All-time debits</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">This Month</div>
    <div class="kpi-value">${{ number_format($monthStats?->month_credit??0,2) }}</div>
    <div class="kpi-sub">in · ${{ number_format($monthStats?->month_debit??0,2) }} out · {{ $monthStats?->month_count??0 }}tx</div>
  </div>
</div>

{{-- Chart + Withdrawals --}}
<div class="two-col" style="margin-bottom:20px">
  <div class="ep-card">
    <div class="ep-card-hdr">
      <div class="ep-card-title">Balance Trend — Last 30 Days</div>
    </div>
    <div style="padding:20px">
      <canvas id="trendChart" height="180"></canvas>
    </div>
  </div>

  <div class="ep-card">
    <div class="ep-card-hdr">
      <div class="ep-card-title">Withdrawal History</div>
    </div>
    <div style="overflow-y:auto;max-height:280px">
      <table class="ep-table">
        <thead><tr><th>Amount</th><th>Method</th><th style="text-align:center">Status</th><th style="text-align:right">Date</th></tr></thead>
        <tbody>
          @foreach($withdrawals as $w)
          <tr>
            <td style="font-weight:800;color:#B91C1C">-${{ number_format($w->amount,2) }}</td>
            <td style="font-size:11px">{{ strtoupper($w->method) }}</td>
            <td style="text-align:center"><span class="pill pill-{{ $w->status }}">{{ strtoupper($w->status) }}</span></td>
            <td style="text-align:right;font-size:11px;color:#94A3B8">{{ \Carbon\Carbon::parse($w->created_at)->format('M d') }}</td>
          </tr>
          @endforeach
          @if($withdrawals->isEmpty())<tr><td colspan="4" style="text-align:center;padding:20px;color:#94A3B8">No withdrawals</td></tr>@endif
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Recent Transactions --}}
<div class="ep-card">
  <div class="ep-card-hdr">
    <div class="ep-card-title">Recent Transactions <span style="font-size:12px;font-weight:400;color:#94A3B8">Last 50</span></div>
    <a href="{{ route('admin.wallet.transactions', ['user_id'=>$user->id]) }}" class="ep-btn" style="font-size:11px"><i class="fas fa-external-link-alt"></i> View All</a>
  </div>
  <div style="overflow-x:auto">
    <table class="ep-table">
      <thead>
        <tr>
          <th style="text-align:center">Type</th>
          <th style="text-align:right">Amount</th>
          <th style="text-align:right">Balance After</th>
          <th>Channel</th>
          <th>Note</th>
          <th style="text-align:right">Date</th>
        </tr>
      </thead>
      <tbody>
        @foreach($transactions as $tx)
        <tr>
          <td style="text-align:center"><span class="pill pill-{{ $tx->type }}">{{ strtoupper($tx->type) }}</span></td>
          <td style="text-align:right;font-weight:800;font-variant-numeric:tabular-nums;color:{{ $tx->type==='credit'?'#15803D':'#B91C1C' }}">
            {{ $tx->type==='credit'?'+':'-' }}${{ number_format($tx->amount,2) }}
          </td>
          <td style="text-align:right;color:#3B82F6;font-variant-numeric:tabular-nums">${{ number_format($tx->balance_after??0,2) }}</td>
          <td style="font-size:11px;color:#64748B">{{ ucfirst(str_replace('_',' ',$tx->payment_method??'—')) }}</td>
          <td style="font-size:12px;color:#475569;max-width:200px">
            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:190px">{{ $tx->note }}</div>
          </td>
          <td style="text-align:right;font-size:11px;color:#94A3B8;white-space:nowrap">
            {{ \Carbon\Carbon::parse($tx->created_at)->format('M d, Y H:i') }}
          </td>
        </tr>
        @endforeach
        @if($transactions->isEmpty())<tr><td colspan="6" style="text-align:center;padding:30px;color:#94A3B8">No transactions</td></tr>@endif
      </tbody>
    </table>
  </div>
</div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const trend = @json($trend);
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: trend.map(d => d.date),
    datasets: [{
      label: 'Balance',
      data: trend.map(d => parseFloat(d.eod_balance)||0),
      borderColor: '#3B82F6',
      backgroundColor: 'rgba(59,130,246,.08)',
      borderWidth: 2.5,
      fill: true,
      tension: 0.4,
      pointRadius: 3,
      pointBackgroundColor: '#3B82F6',
    }]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{display:false} },
    scales:{
      x:{ grid:{display:false}, ticks:{font:{size:10}, maxTicksLimit:7} },
      y:{ grid:{color:'#F1F5F9'}, ticks:{font:{size:10}, callback:v=>'$'+v } }
    }
  }
});
</script>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function setRange(from, to) {
  document.getElementById('stmtFrom').value = from;
  document.getElementById('stmtTo').value   = to;
}
function downloadStmt(format) {
  const from   = document.getElementById('stmtFrom').value;
  const to     = document.getElementById('stmtTo').value;
  const base   = '{{ route("admin.wallet.user-statement", $user->id) }}';
  const params = new URLSearchParams({ format });
  if (from) params.append('from', from);
  if (to)   params.append('to', to);
  window.open(base + '?' + params.toString(), '_blank');
}
</script>
@endpush
