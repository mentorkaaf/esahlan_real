@extends('admin.layouts.app')
@section('title', 'Crypto Withdrawals')

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
.pill-pending{background:#FEF3C7;color:#B45309}
.pill-processing{background:#DBEAFE;color:#1D4ED8}
.pill-completed{background:#DCFCE7;color:#15803D}
.pill-rejected{background:#FEE2E2;color:#B91C1C}
.filter-bar{background:#fff;border-radius:12px;border:1px solid #EEF0F6;padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center}
.modal.open{display:flex}
.modal-box{background:#fff;border-radius:20px;padding:28px;width:480px;max-height:90vh;overflow-y:auto}
</style>
@endpush

@section('content')
<div class="cx">
<div style="margin-bottom:20px">
  <div style="font-size:12px;color:#94A3B8;margin-bottom:2px">Finance / Exchange</div>
  <h1 style="font-size:22px;font-weight:900;color:#0F172A;margin:0">Crypto Withdrawals</h1>
</div>

@if(session('success'))<div class="alert alert-success mb-3">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger mb-3">{{ session('error') }}</div>@endif

<form method="GET" class="filter-bar">
  <div>
    <label style="font-size:10px;font-weight:700;color:#64748B;display:block;margin-bottom:4px">STATUS</label>
    <select name="status" class="form-control form-control-sm">
      <option value="">All</option>
      @foreach(['pending','processing','completed','rejected'] as $s)
      <option value="{{ $s }}" {{ request('status')==$s?'selected':'' }}>{{ ucfirst($s) }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label style="font-size:10px;font-weight:700;color:#64748B;display:block;margin-bottom:4px">COIN</label>
    <select name="coin_id" class="form-control form-control-sm">
      <option value="">All</option>
      @foreach($coins as $c)<option value="{{ $c->id }}" {{ request('coin_id')==$c->id?'selected':'' }}>{{ $c->symbol }}</option>@endforeach
    </select>
  </div>
  <div>
    <label style="font-size:10px;font-weight:700;color:#64748B;display:block;margin-bottom:4px">SEARCH</label>
    <input type="text" name="q" value="{{ request('q') }}" placeholder="User / TxHash" class="form-control form-control-sm" style="width:200px">
  </div>
  <button type="submit" class="btn btn-sm btn-primary" style="align-self:flex-end">Filter</button>
  <a href="{{ route('admin.crypto.withdrawals') }}" class="btn btn-sm btn-light" style="align-self:flex-end">Clear</a>
</form>

@if($pendingCount > 0)
<div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:12px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
  <i class="fas fa-clock" style="color:#B45309;font-size:18px"></i>
  <div style="font-weight:700;color:#92400E">{{ $pendingCount }} pending withdrawal(s) require processing</div>
</div>
@endif

<div class="cx-card">
  <div style="overflow-x:auto">
    <table class="cx-table">
      <thead>
        <tr>
          <th>#</th><th>User</th><th>Coin</th><th>Network</th>
          <th style="text-align:right">Amount</th><th style="text-align:right">Fee</th>
          <th>Address</th><th>TxHash</th>
          <th style="text-align:center">Status</th><th style="text-align:right">Date</th><th></th>
        </tr>
      </thead>
      <tbody>
        @foreach($withdrawals as $w)
        <tr>
          <td style="font-size:10px;color:#94A3B8">{{ $w->id }}</td>
          <td style="font-weight:600">{{ $w->user->name ?? '-' }}<div style="font-size:10px;color:#94A3B8">{{ $w->user->phone ?? '' }}</div></td>
          <td style="font-weight:800">{{ $w->coin->symbol ?? '-' }}</td>
          <td style="font-size:11px">{{ $w->network->name ?? '-' }}</td>
          <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700">{{ number_format($w->amount,8) }}</td>
          <td style="text-align:right;font-size:11px;color:#94A3B8">{{ number_format($w->fee??0,8) }}</td>
          <td style="font-size:10px;font-family:monospace;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $w->to_address }}">{{ $w->to_address }}</td>
          <td style="font-size:10px;font-family:monospace">
            @if($w->txhash)<span title="{{ $w->txhash }}">{{ substr($w->txhash,0,10) }}…</span>@else<span style="color:#CBD5E1">—</span>@endif
          </td>
          <td style="text-align:center"><span class="pill pill-{{ $w->status }}">{{ strtoupper($w->status) }}</span></td>
          <td style="text-align:right;font-size:11px;color:#94A3B8">{{ $w->created_at->format('M d H:i') }}</td>
          <td>
            @if($w->status === 'pending')
            <div style="display:flex;gap:4px">
              <button onclick="openProcess({{ $w->id }},'{{ $w->user->name }}','{{ $w->coin->symbol }}',{{ $w->amount }})" class="btn btn-xs btn-success" style="font-size:10px;padding:2px 8px">Process</button>
              <button onclick="openReject({{ $w->id }})" class="btn btn-xs btn-danger" style="font-size:10px;padding:2px 8px">Reject</button>
            </div>
            @endif
          </td>
        </tr>
        @endforeach
        @if($withdrawals->isEmpty())
        <tr><td colspan="11" style="text-align:center;padding:40px;color:#94A3B8">No withdrawals found</td></tr>
        @endif
      </tbody>
    </table>
  </div>
  <div style="padding:16px 20px;border-top:1px solid #F1F5F9">{{ $withdrawals->withQueryString()->links() }}</div>
</div>
</div>

{{-- Process Modal --}}
<div class="modal" id="processModal">
  <div class="modal-box">
    <h3 style="font-size:17px;font-weight:800;margin-bottom:16px">Process Withdrawal</h3>
    <div id="processInfo" style="background:#F8FAFC;border-radius:10px;padding:14px;margin-bottom:16px;font-size:13px"></div>
    <form method="POST" id="processForm">@csrf @method('PATCH')
      <div class="mb-3">
        <label class="form-label" style="font-weight:700">Transaction Hash *</label>
        <input type="text" name="txhash" class="form-control" placeholder="0x..." required>
      </div>
      <div class="mb-3">
        <label class="form-label" style="font-weight:700">Admin Note</label>
        <input type="text" name="admin_note" class="form-control" placeholder="Optional note">
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-success flex-fill">Confirm Processed</button>
        <button type="button" onclick="closeModals()" class="btn btn-light">Cancel</button>
      </div>
    </form>
  </div>
</div>

{{-- Reject Modal --}}
<div class="modal" id="rejectModal">
  <div class="modal-box">
    <h3 style="font-size:17px;font-weight:800;margin-bottom:16px">Reject Withdrawal</h3>
    <form method="POST" id="rejectForm">@csrf @method('PATCH')
      <div class="mb-3">
        <label class="form-label" style="font-weight:700">Reason *</label>
        <textarea name="admin_note" class="form-control" rows="3" placeholder="Reason for rejection..." required></textarea>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-danger flex-fill">Reject & Refund</button>
        <button type="button" onclick="closeModals()" class="btn btn-light">Cancel</button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
function openProcess(id, name, symbol, amount){
  document.getElementById('processInfo').innerHTML = `<strong>${name}</strong> withdrawing <strong>${amount} ${symbol}</strong>`;
  document.getElementById('processForm').action = `/admin/crypto/withdrawals/${id}/process`;
  document.getElementById('processModal').classList.add('open');
}
function openReject(id){
  document.getElementById('rejectForm').action = `/admin/crypto/withdrawals/${id}/reject`;
  document.getElementById('rejectModal').classList.add('open');
}
function closeModals(){
  document.querySelectorAll('.modal').forEach(m=>m.classList.remove('open'));
}
document.querySelectorAll('.modal').forEach(m=>m.addEventListener('click',e=>{if(e.target===e.currentTarget)closeModals()}));
</script>
@endpush
