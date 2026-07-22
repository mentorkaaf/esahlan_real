@extends('admin.layouts.app')
@section('title', 'Crypto Deposits')

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
.pill-confirming{background:#DBEAFE;color:#1D4ED8}
.pill-confirmed{background:#DCFCE7;color:#15803D}
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
  <h1 style="font-size:22px;font-weight:900;color:#0F172A;margin:0">Crypto Deposits</h1>
</div>

@if(session('success'))<div class="alert alert-success mb-3">{{ session('success') }}</div>@endif

<form method="GET" class="filter-bar">
  <div>
    <label style="font-size:10px;font-weight:700;color:#64748B;display:block;margin-bottom:4px">STATUS</label>
    <select name="status" class="form-control form-control-sm">
      <option value="">All</option>
      @foreach(['pending','confirming','confirmed','rejected'] as $s)
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
    <input type="text" name="q" value="{{ request('q') }}" placeholder="TxHash / User" class="form-control form-control-sm" style="width:200px">
  </div>
  <button type="submit" class="btn btn-sm btn-primary" style="align-self:flex-end">Filter</button>
  <a href="{{ route('admin.crypto.deposits') }}" class="btn btn-sm btn-light" style="align-self:flex-end">Clear</a>
</form>

<div class="cx-card">
  <div class="cx-hdr">
    <div class="cx-title">All Deposits</div>
    <div style="font-size:12px;color:#94A3B8">{{ $deposits->total() }} total</div>
  </div>
  <div style="overflow-x:auto">
    <table class="cx-table">
      <thead>
        <tr>
          <th>#</th><th>User</th><th>Coin</th><th>Network</th>
          <th style="text-align:right">Amount</th><th>TxHash</th>
          <th style="text-align:right">Confirmations</th>
          <th style="text-align:center">Status</th><th style="text-align:right">Date</th><th></th>
        </tr>
      </thead>
      <tbody>
        @foreach($deposits as $d)
        <tr>
          <td style="font-size:10px;color:#94A3B8">{{ $d->id }}</td>
          <td style="font-weight:600">{{ $d->user->name ?? '-' }}<div style="font-size:10px;color:#94A3B8">{{ $d->user->phone ?? '' }}</div></td>
          <td style="font-weight:800">{{ $d->coin->symbol ?? '-' }}</td>
          <td style="font-size:11px">{{ $d->network->network_name ?? '-' }}</td>
          <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700">{{ number_format($d->amount,8) }}</td>
          <td style="font-size:10px;font-family:monospace;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $d->txhash }}">{{ $d->txhash ?? '—' }}</td>
          <td style="text-align:right">{{ $d->confirmations ?? 0 }} / {{ $d->required_confirmations ?? 3 }}</td>
          <td style="text-align:center"><span class="pill pill-{{ $d->status }}">{{ strtoupper($d->status) }}</span></td>
          <td style="text-align:right;font-size:11px;color:#94A3B8">{{ $d->created_at->format('M d H:i') }}</td>
          <td>
            @if($d->status === 'pending' || $d->status === 'confirming')
            <div style="display:flex;gap:4px">
              <form method="POST" action="{{ route('admin.crypto.deposits.approve',$d->id) }}" style="display:inline">@csrf @method('PATCH')
                <button type="submit" class="btn btn-xs btn-success" style="font-size:10px;padding:2px 8px" onclick="return confirm('Approve this deposit?')">Approve</button>
              </form>
              <button onclick="openReject({{ $d->id }})" class="btn btn-xs btn-danger" style="font-size:10px;padding:2px 8px">Reject</button>
            </div>
            @endif
          </td>
        </tr>
        @endforeach
        @if($deposits->isEmpty())
        <tr><td colspan="10" style="text-align:center;padding:40px;color:#94A3B8">No deposits found</td></tr>
        @endif
      </tbody>
    </table>
  </div>
  <div style="padding:16px 20px;border-top:1px solid #F1F5F9">{{ $deposits->withQueryString()->links() }}</div>
</div>
</div>

<div class="modal" id="rejectModal">
  <div class="modal-box">
    <h3 style="font-size:17px;font-weight:800;margin-bottom:16px">Reject Deposit</h3>
    <form method="POST" id="rejectForm">@csrf @method('PATCH')
      <div class="mb-3">
        <label class="form-label" style="font-weight:700">Reason *</label>
        <textarea name="admin_note" class="form-control" rows="3" placeholder="Reason for rejection..." required></textarea>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-danger flex-fill">Reject Deposit</button>
        <button type="button" onclick="closeModal()" class="btn btn-light">Cancel</button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
function openReject(id){
  document.getElementById('rejectForm').action = `/admin/crypto/deposits/${id}/reject`;
  document.getElementById('rejectModal').classList.add('open');
}
function closeModal(){document.getElementById('rejectModal').classList.remove('open')}
document.getElementById('rejectModal').addEventListener('click',e=>{if(e.target===e.currentTarget)closeModal()});
</script>
@endpush
