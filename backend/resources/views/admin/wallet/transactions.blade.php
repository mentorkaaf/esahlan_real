@extends('admin.layouts.app')
@section('title', 'ePay Transactions')

@push('styles')
<style>
.ep { background:#F4F6FB; min-height:100vh; padding:24px 28px; }
.ep-card { background:#fff; border-radius:16px; border:1px solid #EEF0F6; overflow:hidden; }
.ep-card-hdr { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid #F1F5F9; }
.ep-card-title { font-size:14px; font-weight:800; color:#0F172A; }
.ep-table { width:100%; border-collapse:collapse; font-size:13px; }
.ep-table th { padding:10px 16px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#94A3B8; background:#F8FAFC; border-bottom:1px solid #F1F5F9; text-align:left; }
.ep-table td { padding:12px 16px; border-bottom:1px solid #F8FAFC; vertical-align:middle; }
.ep-table tr:last-child td { border-bottom:none; }
.ep-table tr:hover td { background:#FAFBFD; cursor:pointer; }
.pill { padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700; display:inline-block; }
.pill-credit { background:#DCFCE7; color:#15803D; }
.pill-debit  { background:#FEE2E2; color:#B91C1C; }
.ep-input { padding:8px 11px; border:1.5px solid #E2E8F0; border-radius:8px; font-size:12px; outline:none; }
.ep-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; border:1.5px solid #E2E8F0; background:#fff; color:#374151; text-decoration:none; }
.ep-btn-primary { background:#07003B; color:#fff; border-color:#07003B; }
.ep-btn-success { background:#22C55E; color:#fff; border-color:#22C55E; }
.ep-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center; }
.ep-modal-box { background:#fff; border-radius:20px; padding:28px; width:90%; max-width:520px; max-height:85vh; overflow-y:auto; }
.kpi-strip { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:16px; }
.kpi-mini { background:#fff; border-radius:12px; border:1px solid #EEF0F6; padding:14px 16px; }
.kpi-mini-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#94A3B8; margin-bottom:4px; }
.kpi-mini-value { font-size:20px; font-weight:900; color:#0F172A; }
</style>
@endpush

@section('content')
<div class="ep">

{{-- Header --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:2px">
      <a href="{{ route('admin.wallet.index') }}" style="color:#94A3B8;font-size:12px;text-decoration:none">ePay</a>
      <span style="color:#CBD5E1;font-size:12px">/</span>
      @if(isset($filterUser))<a href="{{ route('admin.wallet.transactions') }}" style="color:#94A3B8;font-size:12px;text-decoration:none">Transactions</a><span style="color:#CBD5E1;font-size:12px">/</span><span style="font-size:12px;color:#374151;font-weight:700">{{ $filterUser->name }}</span>
      @else<span style="font-size:12px;color:#374151;font-weight:700">All Transactions</span>@endif
    </div>
    <h1 style="font-size:20px;font-weight:900;color:#0F172A;margin:0">ePay Transactions</h1>
  </div>
  <div style="display:flex;gap:8px">
    <a href="{{ request()->fullUrlWithQuery(['export'=>'csv']) }}" class="ep-btn ep-btn-success"><i class="fas fa-download"></i> Export CSV</a>
    <a href="{{ route('admin.wallet.index') }}" class="ep-btn"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

@if(isset($filterUser))
<div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between">
  <div style="display:flex;align-items:center;gap:10px">
    <i class="fas fa-user-circle" style="color:#3B82F6;font-size:18px"></i>
    <div>
      <div style="font-weight:800;color:#1E40AF;font-size:14px">{{ $filterUser->name }}</div>
      <div style="font-size:11px;color:#60A5FA">{{ $filterUser->email }} · {{ $filterUser->phone ?? '' }}</div>
    </div>
  </div>
  <div style="display:flex;gap:8px">
    <a href="{{ route('admin.wallet.user-detail', $filterUser->id) }}" class="ep-btn" style="font-size:11px"><i class="fas fa-chart-line"></i> Profile</a>
    <a href="{{ route('admin.wallet.transactions') }}" class="ep-btn" style="font-size:11px">Clear Filter</a>
  </div>
</div>
@endif

{{-- Summary KPIs --}}
<div class="kpi-strip">
  <div class="kpi-mini">
    <div class="kpi-mini-label">Total Transactions</div>
    <div class="kpi-mini-value">{{ number_format($summary->total_count ?? 0) }}</div>
  </div>
  <div class="kpi-mini">
    <div class="kpi-mini-label">Total Credits</div>
    <div class="kpi-mini-value" style="color:#15803D">+${{ number_format($summary->total_credit ?? 0, 2) }}</div>
  </div>
  <div class="kpi-mini">
    <div class="kpi-mini-label">Total Debits</div>
    <div class="kpi-mini-value" style="color:#B91C1C">-${{ number_format($summary->total_debit ?? 0, 2) }}</div>
  </div>
</div>

{{-- Filters --}}
<div class="ep-card" style="margin-bottom:16px">
  <form method="GET" style="padding:14px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    @if(request('user_id'))<input type="hidden" name="user_id" value="{{ request('user_id') }}">@endif
    <div>
      <div style="font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;margin-bottom:3px">Search</div>
      <input name="search" value="{{ request('search') }}" placeholder="Name, phone, note, reference..." class="ep-input" style="width:220px">
    </div>
    <div>
      <div style="font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;margin-bottom:3px">Type</div>
      <select name="type" class="ep-input">
        <option value="">All types</option>
        <option value="credit" @selected(request('type')==='credit')>Credit</option>
        <option value="debit"  @selected(request('type')==='debit')>Debit</option>
      </select>
    </div>
    <div>
      <div style="font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;margin-bottom:3px">Channel</div>
      <select name="method" class="ep-input">
        <option value="">All channels</option>
        @foreach($methods as $m)<option value="{{ $m }}" @selected(request('method')===$m)>{{ ucfirst(str_replace('_',' ',$m)) }}</option>@endforeach
      </select>
    </div>
    <div>
      <div style="font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;margin-bottom:3px">From</div>
      <input type="date" name="date_from" value="{{ request('date_from') }}" class="ep-input">
    </div>
    <div>
      <div style="font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;margin-bottom:3px">To</div>
      <input type="date" name="date_to" value="{{ request('date_to') }}" class="ep-input">
    </div>
    <button type="submit" class="ep-btn ep-btn-primary"><i class="fas fa-filter"></i> Filter</button>
    <a href="{{ request('user_id') ? route('admin.wallet.transactions',['user_id'=>request('user_id')]) : route('admin.wallet.transactions') }}" class="ep-btn">Clear</a>
  </form>
</div>

{{-- Table --}}
<div class="ep-card">
  <div class="ep-card-hdr">
    <div class="ep-card-title">Transactions <span style="font-size:12px;font-weight:400;color:#94A3B8">{{ $transactions->total() }} total</span></div>
    <div style="font-size:12px;color:#94A3B8">Page {{ $transactions->currentPage() }} of {{ $transactions->lastPage() }}</div>
  </div>
  <div style="overflow-x:auto">
    <table class="ep-table">
      <thead>
        <tr>
          <th>#</th>
          <th>User</th>
          <th>Note</th>
          <th style="text-align:center">Type</th>
          <th style="text-align:center">Channel</th>
          <th style="text-align:right">Amount</th>
          <th style="text-align:right">Balance After</th>
          <th style="text-align:right">Date</th>
        </tr>
      </thead>
      <tbody>
        @foreach($transactions as $tx)
        <tr onclick="openTxDetail({{ json_encode($tx) }})">
          <td style="font-size:11px;color:#94A3B8;font-variant-numeric:tabular-nums">{{ $tx->id }}</td>
          <td>
            <div style="font-weight:700;color:#0F172A">{{ $tx->user_name }}</div>
            <div style="font-size:10px;color:#94A3B8">{{ $tx->user_phone }}</div>
          </td>
          <td style="max-width:240px;color:#475569">
            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px">{{ $tx->note }}</div>
          </td>
          <td style="text-align:center"><span class="pill pill-{{ $tx->type }}">{{ strtoupper($tx->type) }}</span></td>
          <td style="text-align:center">
            <span style="font-size:11px;color:#64748B;background:#F8FAFC;padding:2px 8px;border-radius:6px;font-weight:600">{{ ucfirst(str_replace('_',' ',$tx->payment_method??'—')) }}</span>
          </td>
          <td style="text-align:right;font-weight:800;font-variant-numeric:tabular-nums;color:{{ $tx->type==='credit'?'#15803D':'#B91C1C' }}">
            {{ $tx->type==='credit'?'+':'-' }}${{ number_format($tx->amount,2) }}
          </td>
          <td style="text-align:right;color:#3B82F6;font-weight:700;font-variant-numeric:tabular-nums">${{ number_format($tx->balance_after??0,2) }}</td>
          <td style="text-align:right;white-space:nowrap;color:#94A3B8;font-size:11px">{{ \Carbon\Carbon::parse($tx->created_at)->format('M d, Y') }}<br>{{ \Carbon\Carbon::parse($tx->created_at)->format('H:i') }}</td>
        </tr>
        @endforeach
        @if($transactions->isEmpty())
        <tr><td colspan="8" style="text-align:center;padding:40px;color:#94A3B8">No transactions found</td></tr>
        @endif
      </tbody>
    </table>
    <div style="padding:16px">{{ $transactions->links() }}</div>
  </div>
</div>

{{-- Transaction Detail Modal --}}
<div id="txModal" class="ep-modal">
  <div class="ep-modal-box">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
      <div>
        <div id="txTitle" style="font-size:17px;font-weight:900;color:#0F172A"></div>
        <div id="txSub" style="font-size:12px;color:#94A3B8"></div>
      </div>
      <button onclick="document.getElementById('txModal').style.display='none'" style="background:none;border:none;font-size:20px;color:#94A3B8;cursor:pointer">&times;</button>
    </div>
    <div id="txBody" style="display:grid;grid-template-columns:1fr 1fr;gap:12px"></div>
  </div>
</div>

</div>
@endsection

@push('scripts')
<script>
function openTxDetail(tx) {
  const fmt = v => v ? '$'+parseFloat(v).toFixed(2) : '—';
  document.getElementById('txTitle').textContent = (tx.type === 'credit' ? '+' : '-') + fmt(tx.amount) + ' ' + tx.type.toUpperCase();
  document.getElementById('txSub').textContent = 'Transaction #' + tx.id + ' · ' + tx.created_at;
  const fields = [
    ['User', tx.user_name + (tx.user_phone?' · '+tx.user_phone:'')],
    ['Type', tx.type.toUpperCase()],
    ['Amount', (tx.type==='credit'?'+':'-') + fmt(tx.amount)],
    ['Balance Before', fmt(tx.balance_before)],
    ['Balance After', fmt(tx.balance_after)],
    ['Channel', tx.payment_method || '—'],
    ['Reference', tx.payment_reference || '—'],
    ['Status', (tx.status||'—').toUpperCase()],
    ['Note', tx.note || '—'],
    ['Date', tx.created_at],
  ];
  document.getElementById('txBody').innerHTML = fields.map(([k,v]) =>
    `<div style="background:#F8FAFC;border-radius:10px;padding:12px">
       <div style="font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;margin-bottom:3px">${k}</div>
       <div style="font-size:13px;font-weight:700;color:#0F172A;word-break:break-all">${v}</div>
     </div>`
  ).join('');
  document.getElementById('txModal').style.display = 'flex';
}
document.getElementById('txModal').addEventListener('click', e => { if(e.target.id==='txModal') e.target.style.display='none'; });
</script>
@endpush
