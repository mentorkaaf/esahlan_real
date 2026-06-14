@extends('vendor.layouts.app')
@section('title', 'Wallet')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Wallet</h1>
        <ul class="breadcrumb"><li><a href="{{ route('vendor.dashboard') }}">Dashboard</a></li><li>Wallet</li></ul>
    </div>
    @if($wallet)
    <button onclick="openModal('withdraw-modal')" class="btn btn-primary">
        <i class="fa-solid fa-money-bill-transfer"></i> Request Withdrawal
    </button>
    @endif
</div>

{{-- Balance card --}}
<div style="margin-bottom:24px;">
    <div style="background:linear-gradient(135deg,var(--navy) 0%,#1a0874 60%,#0c0148 100%);border-radius:20px;padding:32px;color:#fff;position:relative;overflow:hidden;">
        <div style="position:absolute;top:-30px;right:-30px;width:160px;height:160px;border-radius:50%;background:rgba(255,138,0,0.1);pointer-events:none;"></div>
        <div style="position:absolute;bottom:-50px;left:60px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,0.03);pointer-events:none;"></div>
        <div style="font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,0.5);margin-bottom:8px;">Available Balance</div>
        <div style="font-size:44px;font-weight:900;letter-spacing:-2px;margin-bottom:4px;">
            ${{ number_format($wallet?->balance ?? 0, 2) }}
        </div>
        <div style="font-size:13px;color:rgba(255,255,255,0.45);">{{ $vendor->name }}</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">
    {{-- Transactions --}}
    <div class="card">
        <div class="card-header">
            <div class="card-header-title"><div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);"><i class="fa-solid fa-clock-rotate-left"></i></div> Transaction History</div>
        </div>
        @if(!$wallet || !($transactions instanceof \Illuminate\Pagination\LengthAwarePaginator) || $transactions->isEmpty())
        <div class="empty-state"><i class="fa-solid fa-receipt"></i><p>No transactions yet</p></div>
        @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Date</th><th>Description</th><th>Type</th><th>Amount</th><th>Balance After</th></tr>
                </thead>
                <tbody>
                    @foreach($transactions as $tx)
                    <tr>
                        <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">{{ \Carbon\Carbon::parse($tx->created_at)->format('d M, H:i') }}</td>
                        <td style="max-width:220px;">{{ $tx->note ?? '—' }}</td>
                        <td><span class="badge {{ $tx->type === 'credit' ? 'badge-success' : 'badge-danger' }}">{{ ucfirst($tx->type) }}</span></td>
                        <td style="font-weight:700;color:{{ $tx->type === 'credit' ? 'var(--success)' : 'var(--danger)' }};">
                            {{ $tx->type === 'credit' ? '+' : '-' }}${{ number_format($tx->amount, 2) }}
                        </td>
                        <td>${{ number_format($tx->balance_after ?? 0, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">
            <div class="pagination-info">Showing {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }} of {{ $transactions->total() }}</div>
            {{ $transactions->links('vendor.partials.pagination') }}
        </div>
        @endif
    </div>

    {{-- Withdrawal requests --}}
    <div class="card">
        <div class="card-header">
            <div class="card-header-title"><div class="card-header-icon" style="background:rgba(245,158,11,0.1);color:var(--warning);"><i class="fa-solid fa-money-bill-wave"></i></div> Withdrawal Requests</div>
        </div>
        @if($withdrawals->isEmpty())
        <div class="empty-state" style="padding:30px 20px;"><i class="fa-solid fa-money-bill-wave"></i><p>No withdrawal requests</p></div>
        @else
        <div style="display:flex;flex-direction:column;gap:0;">
            @foreach($withdrawals as $wr)
            <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;">
                <div>
                    <div style="font-weight:700;">${{ number_format($wr->amount,2) }}</div>
                    <div style="font-size:11.5px;color:var(--text-muted);">{{ $wr->payment_method }} · {{ $wr->account_number }}</div>
                    <div style="font-size:11px;color:var(--text-muted);">{{ \Carbon\Carbon::parse($wr->created_at)->format('d M Y') }}</div>
                </div>
                @php $cls = match($wr->status) { 'pending'=>'warning','processed'=>'success','approved'=>'info','rejected'=>'danger',default=>'neutral' }; @endphp
                <span class="badge badge-{{ $cls }}">{{ ucfirst($wr->status) }}</span>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- Withdraw modal --}}
<div class="modal-overlay" id="withdraw-modal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Request Withdrawal</div>
            <button class="modal-close" onclick="closeModal('withdraw-modal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form action="{{ route('vendor.wallet.withdraw') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Amount ($) <span style="color:var(--danger);">*</span></label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="1" max="{{ $wallet?->balance ?? 0 }}" required placeholder="0.00">
                    <div class="form-hint">Available: ${{ number_format($wallet?->balance ?? 0, 2) }}</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Method <span style="color:var(--danger);">*</span></label>
                    <select name="payment_method" id="method-select" class="form-control" onchange="updateMethodFields(this.value)" required>
                        <option value="">— Select —</option>
                        <option value="evc">EVC Plus</option>
                        <option value="waafi">Waafi</option>
                        <option value="others">Others (Bank Transfer)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Account Name <span style="color:var(--danger);">*</span></label>
                    <input type="text" name="account_name" class="form-control" required placeholder="Full name">
                </div>
                <div class="form-group">
                    <label class="form-label">Account Number / Phone <span style="color:var(--danger);">*</span></label>
                    <input type="text" name="account_number" class="form-control" required placeholder="Phone or account number">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('withdraw-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>
@endsection
