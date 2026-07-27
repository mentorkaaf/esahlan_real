@extends('admin.layouts.app')
@section('title', 'eShop Withdrawals')

@push('styles')
<style>
.wd-badge { display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700; }
.wd-badge.pending  { background:#fef9c3;color:#92400e; }
.wd-badge.approved { background:#dbeafe;color:#1d4ed8; }
.wd-badge.processed{ background:#dcfce7;color:#15803d; }
.wd-badge.rejected { background:#fee2e2;color:#991b1b; }
.action-btn { border:none;padding:6px 14px;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer; }
.approve-btn { background:#dcfce7;color:#15803d; }
.reject-btn  { background:#fee2e2;color:#991b1b; }
.modal-backdrop { display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center; }
.modal-backdrop.open { display:flex; }
.modal-box { background:#fff;border-radius:16px;padding:28px;width:440px;max-width:95vw; }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">eShop Vendor Withdrawals</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.eshop.index') }}">eShop</a></li>
            <li>Withdrawals</li>
        </ul>
    </div>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:#15803d;font-weight:600;font-size:13px;">
    <i class="fa-solid fa-check-circle" style="margin-right:6px;"></i>{{ session('success') }}
</div>
@endif

{{-- Status Tabs --}}
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    @foreach(['pending'=>'Pending','approved'=>'Approved','processed'=>'Paid Out','rejected'=>'Rejected','all'=>'All'] as $s => $label)
    <a href="{{ route('admin.eshop.withdrawals', ['status' => $s]) }}"
       style="padding:6px 16px;border-radius:20px;font-size:12px;font-weight:700;text-decoration:none;
              background:{{ $status===$s ? '#FF8A00' : '#f3f4f6' }};
              color:{{ $status===$s ? '#fff' : '#555' }};">
        {{ $label }}
        @if(isset($counts[$s]) && $counts[$s] > 0)
        <span style="margin-left:4px;background:{{ $status===$s?'rgba(255,255,255,.3)':'#e5e7eb' }};border-radius:10px;padding:1px 6px;">{{ $counts[$s] }}</span>
        @endif
    </a>
    @endforeach
</div>

<div class="card">
    <div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>Vendor</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Account</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($withdrawals as $w)
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:8px;">
                    @if($w->vendor_logo)
                    <img src="{{ $w->vendor_logo }}" style="width:32px;height:32px;border-radius:8px;object-fit:cover;">
                    @else
                    <div style="width:32px;height:32px;border-radius:8px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-shop" style="color:#aaa;font-size:14px;"></i></div>
                    @endif
                    <span style="font-weight:700;font-size:13px;">{{ $w->vendor_name }}</span>
                </div>
            </td>
            <td style="font-weight:900;font-size:15px;color:#FF8A00">${{ number_format($w->amount, 2) }}</td>
            <td style="font-size:12px;">{{ ucwords(str_replace('_',' ',$w->method ?? 'bank')) }}</td>
            <td>
                <div style="font-size:12px;font-weight:600;">{{ $w->account_number }}</div>
                <div style="font-size:11px;color:#aaa;">{{ $w->account_name }}</div>
            </td>
            <td><span class="wd-badge {{ $w->status }}">{{ $w->status === 'processed' ? 'Paid' : ucfirst($w->status) }}</span></td>
            <td style="font-size:12px;color:#888;">{{ \Carbon\Carbon::parse($w->created_at)->format('d M Y') }}</td>
            <td>
                @if($w->status === 'pending')
                <div style="display:flex;gap:6px;">
                    <button onclick="openApprove({{ $w->id }}, '{{ $w->vendor_name }}', {{ $w->amount }})"
                            class="action-btn approve-btn">
                        <i class="fa-solid fa-check"></i> Approve
                    </button>
                    <button onclick="openReject({{ $w->id }}, '{{ $w->vendor_name }}')"
                            class="action-btn reject-btn">
                        <i class="fa-solid fa-xmark"></i> Reject
                    </button>
                </div>
                @elseif($w->status === 'processed')
                <div style="font-size:11px;color:#22c55e;">
                    <i class="fa-solid fa-check-double"></i> Paid<br>
                    @if($w->transaction_reference)
                    <span style="color:#888;">Ref: {{ $w->transaction_reference }}</span>
                    @endif
                </div>
                @elseif($w->status === 'rejected')
                <div style="font-size:11px;color:#ef4444;">
                    <i class="fa-solid fa-xmark"></i> Rejected
                    @if($w->admin_note)<br><span style="color:#888;">{{ $w->admin_note }}</span>@endif
                </div>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:#aaa;padding:50px;">No withdrawal requests found.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div style="padding:12px 16px;">{{ $withdrawals->links() }}</div>
</div>

{{-- Approve Modal --}}
<div class="modal-backdrop" id="approveModal">
    <div class="modal-box">
        <h3 style="font-weight:900;margin-bottom:4px;color:#111;">Approve Withdrawal</h3>
        <p id="approveDesc" style="font-size:13px;color:#888;margin-bottom:20px;"></p>
        <form method="POST" id="approveForm">
            @csrf
            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Transaction Reference *</label>
                <input type="text" name="transaction_reference" placeholder="e.g. TXN-123456"
                       style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;" required>
                <div style="font-size:11px;color:#aaa;margin-top:3px;">Enter the payment confirmation number / reference.</div>
            </div>
            <div style="margin-bottom:20px;">
                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Note (optional)</label>
                <textarea name="admin_note" rows="2"
                          style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;resize:none;"
                          placeholder="Optional message to vendor"></textarea>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit"
                        style="flex:1;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;border:none;padding:10px;border-radius:10px;font-weight:800;cursor:pointer;">
                    <i class="fa-solid fa-check" style="margin-right:4px;"></i>Confirm Payment
                </button>
                <button type="button" onclick="closeModals()"
                        style="flex:1;background:#f3f4f6;color:#555;border:none;padding:10px;border-radius:10px;font-weight:700;cursor:pointer;">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- Reject Modal --}}
<div class="modal-backdrop" id="rejectModal">
    <div class="modal-box">
        <h3 style="font-weight:900;margin-bottom:4px;color:#111;">Reject Withdrawal</h3>
        <p id="rejectDesc" style="font-size:13px;color:#888;margin-bottom:20px;"></p>
        <form method="POST" id="rejectForm">
            @csrf
            <div style="margin-bottom:20px;">
                <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Reason *</label>
                <textarea name="admin_note" rows="3" required
                          style="width:100%;padding:9px 12px;border:1.5px solid #fca5a5;border-radius:8px;font-size:13px;resize:none;"
                          placeholder="Explain why this request is being rejected..."></textarea>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit"
                        style="flex:1;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border:none;padding:10px;border-radius:10px;font-weight:800;cursor:pointer;">
                    <i class="fa-solid fa-xmark" style="margin-right:4px;"></i>Reject Request
                </button>
                <button type="button" onclick="closeModals()"
                        style="flex:1;background:#f3f4f6;color:#555;border:none;padding:10px;border-radius:10px;font-weight:700;cursor:pointer;">Cancel</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openApprove(id, vendor, amount) {
    document.getElementById('approveDesc').textContent =
        'Approving $' + amount.toFixed(2) + ' withdrawal for ' + vendor;
    document.getElementById('approveForm').action =
        '{{ url("admin/eshop/withdrawals") }}/' + id + '/approve';
    document.getElementById('approveModal').classList.add('open');
}
function openReject(id, vendor) {
    document.getElementById('rejectDesc').textContent =
        'Rejecting withdrawal request from ' + vendor;
    document.getElementById('rejectForm').action =
        '{{ url("admin/eshop/withdrawals") }}/' + id + '/reject';
    document.getElementById('rejectModal').classList.add('open');
}
function closeModals() {
    document.querySelectorAll('.modal-backdrop').forEach(m => m.classList.remove('open'));
}
document.querySelectorAll('.modal-backdrop').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) closeModals(); });
});
</script>
@endpush
@endsection
