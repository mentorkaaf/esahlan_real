@extends('admin.layouts.app')
@section('title', 'Withdrawal Requests')

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
.ep-table tr:hover td { background:#FAFBFD; }
.pill { padding:4px 12px; border-radius:20px; font-size:10px; font-weight:700; display:inline-block; }
.pill-pending   { background:#FEF3C7; color:#B45309; }
.pill-approved  { background:#DCFCE7; color:#15803D; }
.pill-rejected  { background:#FEE2E2; color:#B91C1C; }
.pill-processed { background:#EFF6FF; color:#1D4ED8; }
.ep-input { padding:8px 11px; border:1.5px solid #E2E8F0; border-radius:8px; font-size:12px; outline:none; }
.ep-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; border:1.5px solid #E2E8F0; background:#fff; color:#374151; text-decoration:none; }
.ep-btn-primary { background:#07003B; color:#fff; border-color:#07003B; }
.ep-btn-success { background:#22C55E; color:#fff; border-color:#22C55E; }
.ep-btn-danger  { background:#EF4444; color:#fff; border-color:#EF4444; }
.ep-btn-blue    { background:#3B82F6; color:#fff; border-color:#3B82F6; }
.ep-btn-sm { padding:5px 10px; font-size:11px; border-radius:7px; }
.ep-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center; }
.ep-modal-box { background:#fff; border-radius:20px; padding:28px; width:90%; max-width:480px; }
.kpi-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px; }
.kpi-mini { background:#fff; border-radius:12px; border:1px solid #EEF0F6; padding:14px 16px; }
.kpi-mini-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#94A3B8; margin-bottom:4px; }
.kpi-mini-value { font-size:20px; font-weight:900; color:#0F172A; }
.ep-tabs { display:flex; gap:2px; background:#F1F5F9; padding:3px; border-radius:10px; margin-bottom:0; }
.ep-tab { padding:7px 14px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; border:none; background:none; color:#64748B; text-decoration:none; }
.ep-tab.active { background:#fff; color:#07003B; font-weight:800; box-shadow:0 1px 3px rgba(0,0,0,.08); }
</style>
@endpush

@section('content')
<div class="ep">

{{-- Header --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <div style="font-size:12px;color:#94A3B8;margin-bottom:2px"><a href="{{ route('admin.wallet.index') }}" style="color:#94A3B8;text-decoration:none">ePay</a> / Withdrawals</div>
    <h1 style="font-size:20px;font-weight:900;color:#0F172A;margin:0">Withdrawal Requests</h1>
  </div>
  <div style="display:flex;gap:8px">
    <a href="{{ request()->fullUrlWithQuery(['export'=>'csv']) }}" class="ep-btn ep-btn-success"><i class="fas fa-download"></i> Export CSV</a>
    <a href="{{ route('admin.wallet.index') }}" class="ep-btn"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

@if(session('success'))<div style="background:#DCFCE7;border:1px solid #86EFAC;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#15803D"><i class="fas fa-check-circle" style="margin-right:6px"></i>{{ session('success') }}</div>@endif
@if(session('error'))<div style="background:#FEE2E2;border:1px solid #FCA5A5;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#B91C1C"><i class="fas fa-exclamation-circle" style="margin-right:6px"></i>{{ session('error') }}</div>@endif

{{-- KPI Strip --}}
<div class="kpi-strip">
  <div class="kpi-mini">
    <div class="kpi-mini-label">Pending</div>
    <div class="kpi-mini-value" style="color:#B45309">${{ number_format($summary->pending_amount??0,2) }}</div>
    <div style="font-size:10px;color:#94A3B8">{{ $summary->pending_count??0 }} requests</div>
  </div>
  <div class="kpi-mini">
    <div class="kpi-mini-label">Approved</div>
    <div class="kpi-mini-value" style="color:#15803D">${{ number_format($summary->approved_amount??0,2) }}</div>
  </div>
  <div class="kpi-mini">
    <div class="kpi-mini-label">Processed</div>
    <div class="kpi-mini-value" style="color:#1D4ED8">${{ number_format($summary->processed_amount??0,2) }}</div>
  </div>
  <div class="kpi-mini">
    <div class="kpi-mini-label">Total Volume</div>
    <div class="kpi-mini-value">${{ number_format($summary->total_amount??0,2) }}</div>
    <div style="font-size:10px;color:#94A3B8">{{ $summary->total_count??0 }} all-time</div>
  </div>
</div>

{{-- Filters + Status Tabs --}}
<div class="ep-card" style="margin-bottom:16px">
  <div style="padding:14px 16px;border-bottom:1px solid #F1F5F9">
    <div class="ep-tabs">
      @foreach(['all'=>'All','pending'=>'Pending','approved'=>'Approved','processed'=>'Processed','rejected'=>'Rejected'] as $val=>$label)
      <a href="{{ request()->fullUrlWithQuery(['status'=>$val,'page'=>1]) }}" class="ep-tab {{ (request('status',$val==='all'?'':request('status'))===$val || ($val==='all'&&!request('status')))?'active':'' }}">{{ $label }}</a>
      @endforeach
    </div>
  </div>
  <form method="GET" style="padding:14px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="status" value="{{ request('status') }}">
    <div>
      <div style="font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;margin-bottom:3px">Search</div>
      <input name="search" value="{{ request('search') }}" placeholder="Name, phone, reference..." class="ep-input" style="width:200px">
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
    <a href="{{ route('admin.wallet.withdrawals') }}" class="ep-btn">Clear</a>
  </form>
</div>

{{-- Table --}}
<div class="ep-card">
  <div class="ep-card-hdr">
    <div class="ep-card-title">Requests <span style="font-size:12px;font-weight:400;color:#94A3B8">{{ $requests->total() }} total</span></div>
  </div>
  <div style="overflow-x:auto">
    <table class="ep-table">
      <thead>
        <tr>
          <th>#</th>
          <th>User</th>
          <th style="text-align:right">Amount</th>
          <th>Method</th>
          <th>Account</th>
          <th style="text-align:center">Status</th>
          <th>Admin Note</th>
          <th style="text-align:right">Date</th>
          <th style="text-align:center">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($requests as $req)
        <tr>
          <td style="font-size:11px;color:#94A3B8">{{ $req->id }}</td>
          <td>
            <div style="font-weight:700;color:#0F172A">{{ $req->user_name }}</div>
            <div style="font-size:10px;color:#94A3B8">{{ $req->user_phone }}</div>
          </td>
          <td style="text-align:right;font-weight:900;color:#B91C1C;font-variant-numeric:tabular-nums">-${{ number_format($req->amount,2) }}</td>
          <td><span style="font-size:11px;font-weight:700;background:#F8FAFC;padding:3px 8px;border-radius:6px">{{ strtoupper($req->method) }}</span></td>
          <td>
            <div style="font-size:12px;font-weight:700">{{ $req->account_name }}</div>
            <div style="font-size:11px;color:#94A3B8;font-variant-numeric:tabular-nums">{{ $req->account_number }}</div>
          </td>
          <td style="text-align:center"><span class="pill pill-{{ $req->status }}">{{ strtoupper($req->status) }}</span></td>
          <td style="font-size:11px;color:#64748B;max-width:160px">
            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px">{{ $req->admin_note ?? '—' }}</div>
            @if($req->transaction_reference)<div style="font-size:10px;color:#94A3B8">Ref: {{ $req->transaction_reference }}</div>@endif
          </td>
          <td style="text-align:right;white-space:nowrap;font-size:11px;color:#94A3B8">
            {{ \Carbon\Carbon::parse($req->created_at)->format('M d, Y') }}<br>
            {{ \Carbon\Carbon::parse($req->created_at)->format('H:i') }}
          </td>
          <td style="text-align:center">
            <div style="display:flex;gap:5px;justify-content:center;flex-wrap:wrap">
              @if($req->status === 'pending')
                <button class="ep-btn ep-btn-success ep-btn-sm" onclick="openApproveModal({{ $req->id }},'{{ addslashes($req->user_name) }}',{{ $req->amount }})">
                  <i class="fas fa-check"></i> Approve
                </button>
                <button class="ep-btn ep-btn-danger ep-btn-sm" onclick="openRejectModal({{ $req->id }},'{{ addslashes($req->user_name) }}',{{ $req->amount }})">
                  <i class="fas fa-times"></i> Reject
                </button>
              @elseif($req->status === 'approved')
                <button class="ep-btn ep-btn-blue ep-btn-sm" onclick="openProcessModal({{ $req->id }},'{{ addslashes($req->user_name) }}',{{ $req->amount }})">
                  <i class="fas fa-paper-plane"></i> Mark Processed
                </button>
              @else
                <span style="font-size:11px;color:#94A3B8">{{ $req->reviewer_name ? 'By '.$req->reviewer_name : '—' }}</span>
              @endif
            </div>
          </td>
        </tr>
        @endforeach
        @if($requests->isEmpty())
        <tr><td colspan="9" style="text-align:center;padding:60px;color:#94A3B8">
          <i class="fas fa-inbox" style="font-size:32px;margin-bottom:12px;display:block;opacity:.3"></i>
          No withdrawal requests found
        </td></tr>
        @endif
      </tbody>
    </table>
    <div style="padding:16px">{{ $requests->links() }}</div>
  </div>
</div>

{{-- Approve Modal --}}
<div id="approveModal" class="ep-modal">
  <div class="ep-modal-box">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
      <div style="width:44px;height:44px;background:#DCFCE7;border-radius:12px;display:flex;align-items:center;justify-content:center"><i class="fas fa-check" style="color:#22C55E;font-size:18px"></i></div>
      <div>
        <div style="font-size:17px;font-weight:900;color:#0F172A">Approve Withdrawal</div>
        <div id="approveSubTitle" style="font-size:12px;color:#94A3B8"></div>
      </div>
    </div>
    <form id="approveForm" method="POST">@csrf
      <div style="margin-bottom:16px">
        <label style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px">Admin Note (optional)</label>
        <input type="text" name="admin_note" placeholder="e.g. Verified via Waafi dashboard" class="ep-input" style="width:100%">
      </div>
      <div style="background:#F0FDF4;border-radius:10px;padding:12px;margin-bottom:16px;font-size:12px;color:#15803D"><i class="fas fa-info-circle" style="margin-right:6px"></i>Funds will be held. User notified via push notification.</div>
      <div style="display:flex;gap:10px">
        <button type="button" onclick="document.getElementById('approveModal').style.display='none'" class="ep-btn" style="flex:1;justify-content:center">Cancel</button>
        <button type="submit" class="ep-btn ep-btn-success" style="flex:1;justify-content:center"><i class="fas fa-check"></i> Approve</button>
      </div>
    </form>
  </div>
</div>

{{-- Reject Modal --}}
<div id="rejectModal" class="ep-modal">
  <div class="ep-modal-box">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
      <div style="width:44px;height:44px;background:#FEE2E2;border-radius:12px;display:flex;align-items:center;justify-content:center"><i class="fas fa-times" style="color:#EF4444;font-size:18px"></i></div>
      <div>
        <div style="font-size:17px;font-weight:900;color:#0F172A">Reject & Refund</div>
        <div id="rejectSubTitle" style="font-size:12px;color:#94A3B8"></div>
      </div>
    </div>
    <form id="rejectForm" method="POST">@csrf
      <div style="margin-bottom:16px">
        <label style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px">Reason (shown to user)</label>
        <input type="text" name="admin_note" required placeholder="e.g. Account verification required" class="ep-input" style="width:100%">
      </div>
      <div style="background:#FEF3C7;border-radius:10px;padding:12px;margin-bottom:16px;font-size:12px;color:#B45309"><i class="fas fa-rotate-left" style="margin-right:6px"></i>Amount will be automatically refunded to the user's ePay wallet.</div>
      <div style="display:flex;gap:10px">
        <button type="button" onclick="document.getElementById('rejectModal').style.display='none'" class="ep-btn" style="flex:1;justify-content:center">Cancel</button>
        <button type="submit" class="ep-btn ep-btn-danger" style="flex:1;justify-content:center"><i class="fas fa-times"></i> Reject & Refund</button>
      </div>
    </form>
  </div>
</div>

{{-- Process Modal --}}
<div id="processModal" class="ep-modal">
  <div class="ep-modal-box">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
      <div style="width:44px;height:44px;background:#EFF6FF;border-radius:12px;display:flex;align-items:center;justify-content:center"><i class="fas fa-paper-plane" style="color:#3B82F6;font-size:18px"></i></div>
      <div>
        <div style="font-size:17px;font-weight:900;color:#0F172A">Mark as Processed</div>
        <div id="processSubTitle" style="font-size:12px;color:#94A3B8"></div>
      </div>
    </div>
    <form id="processForm" method="POST">@csrf
      <div style="margin-bottom:12px">
        <label style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px">Payment Reference <span style="color:#EF4444">*</span></label>
        <input type="text" name="transaction_reference" required placeholder="e.g. WAF2026071912345" class="ep-input" style="width:100%">
      </div>
      <div style="margin-bottom:16px">
        <label style="font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px">Processing Note</label>
        <input type="text" name="admin_note" placeholder="e.g. Sent via Waafi Pay portal" class="ep-input" style="width:100%">
      </div>
      <div style="background:#EFF6FF;border-radius:10px;padding:12px;margin-bottom:16px;font-size:12px;color:#1D4ED8"><i class="fas fa-info-circle" style="margin-right:6px"></i>This confirms the payout was sent externally. Reference stored for audit.</div>
      <div style="display:flex;gap:10px">
        <button type="button" onclick="document.getElementById('processModal').style.display='none'" class="ep-btn" style="flex:1;justify-content:center">Cancel</button>
        <button type="submit" class="ep-btn ep-btn-blue" style="flex:1;justify-content:center"><i class="fas fa-paper-plane"></i> Confirm Processed</button>
      </div>
    </form>
  </div>
</div>

</div>
@endsection

@push('scripts')
<script>
function openApproveModal(id, name, amount) {
  document.getElementById('approveForm').action = '/admin/wallet/withdrawals/' + id + '/approve';
  document.getElementById('approveSubTitle').textContent = name + ' · $' + parseFloat(amount).toFixed(2);
  document.getElementById('approveModal').style.display = 'flex';
}
function openRejectModal(id, name, amount) {
  document.getElementById('rejectForm').action = '/admin/wallet/withdrawals/' + id + '/reject';
  document.getElementById('rejectSubTitle').textContent = name + ' · $' + parseFloat(amount).toFixed(2) + ' will be refunded';
  document.getElementById('rejectModal').style.display = 'flex';
}
function openProcessModal(id, name, amount) {
  document.getElementById('processForm').action = '/admin/wallet/withdrawals/' + id + '/process';
  document.getElementById('processSubTitle').textContent = name + ' · $' + parseFloat(amount).toFixed(2);
  document.getElementById('processModal').style.display = 'flex';
}
document.querySelectorAll('.ep-modal').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.style.display='none'; }));
</script>
@endpush
