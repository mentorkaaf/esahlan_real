@extends('vendor.layouts.app')
@section('title', 'Wallet')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Wallet</h1>
        <ul class="breadcrumb"><li><a href="{{ route('vendor.dashboard') }}">Dashboard</a></li><li>Wallet</li></ul>
    </div>
    <button onclick="openModal('withdraw-modal')" class="btn btn-primary">
        <i class="fa-solid fa-money-bill-transfer"></i> Request Withdrawal
    </button>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

{{-- Balance + Earnings Cards --}}
<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px;margin-bottom:24px;">
    <div style="background:linear-gradient(135deg,#07003B,#1a0874);border-radius:16px;padding:24px;color:#fff;">
        <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,0.5);margin-bottom:6px;">Wallet Balance</div>
        <div style="font-size:32px;font-weight:900;">${{ number_format($wallet->balance ?? 0, 2) }}</div>
        <div style="font-size:11px;color:rgba(255,255,255,0.4);margin-top:4px;">Available for withdrawal</div>
    </div>
    <div style="background:#fff;border-radius:16px;padding:24px;border:1.5px solid #e2e8f0;">
        <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8A8A9A;margin-bottom:6px;">Total Earning</div>
        <div style="font-size:28px;font-weight:900;color:#10b981;">${{ number_format($totalEarning, 2) }}</div>
        <div style="font-size:11px;color:#8A8A9A;margin-top:4px;">After commission</div>
    </div>
    <div style="background:#fff;border-radius:16px;padding:24px;border:1.5px solid #e2e8f0;">
        <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8A8A9A;margin-bottom:6px;">Commission Paid</div>
        <div style="font-size:28px;font-weight:900;color:#ef4444;">${{ number_format($totalCommission, 2) }}</div>
        <div style="font-size:11px;color:#8A8A9A;margin-top:4px;">Platform fee</div>
    </div>
    <div style="background:#fff;border-radius:16px;padding:24px;border:1.5px solid #e2e8f0;">
        <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8A8A9A;margin-bottom:6px;">Pending Withdrawal</div>
        <div style="font-size:28px;font-weight:900;color:#f59e0b;">${{ number_format($pendingWithdrawal, 2) }}</div>
        <div style="font-size:11px;color:#8A8A9A;margin-top:4px;">Awaiting approval</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start;">
    {{-- Transactions --}}
    <div class="card">
        <div class="card-header">
            <div class="card-header-title"><div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);"><i class="fa-solid fa-clock-rotate-left"></i></div> Transaction History</div>
        </div>
        @if($transactions->isEmpty())
        <div class="empty-state"><i class="fa-solid fa-receipt"></i><p>No transactions yet</p></div>
        @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Date</th><th>Description</th><th>Type</th><th style="text-align:right">Amount</th><th style="text-align:right">Balance</th></tr>
                </thead>
                <tbody>
                    @foreach($transactions as $tx)
                    <tr>
                        <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">{{ \Carbon\Carbon::parse($tx->created_at)->format('d M, H:i') }}</td>
                        <td style="max-width:220px;font-size:13px;">{{ $tx->note ?? '—' }}</td>
                        <td><span class="badge {{ $tx->type === 'credit' ? 'badge-success' : 'badge-danger' }}">{{ ucfirst($tx->type) }}</span></td>
                        <td style="text-align:right;font-weight:700;color:{{ $tx->type === 'credit' ? '#10b981' : '#ef4444' }};">
                            {{ $tx->type === 'credit' ? '+' : '-' }}${{ number_format($tx->amount, 2) }}
                        </td>
                        <td style="text-align:right;">${{ number_format($tx->balance_after ?? 0, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($transactions->hasPages())
        <div style="padding:12px 16px;">{{ $transactions->links() }}</div>
        @endif
        @endif
    </div>

    {{-- Withdrawal requests --}}
    <div class="card">
        <div class="card-header">
            <div class="card-header-title"><div class="card-header-icon" style="background:rgba(245,158,11,0.1);color:#f59e0b;"><i class="fa-solid fa-money-bill-wave"></i></div> Withdrawals</div>
        </div>
        @if($withdrawals->isEmpty())
        <div class="empty-state" style="padding:30px 20px;"><i class="fa-solid fa-money-bill-wave"></i><p>No withdrawal requests</p></div>
        @else
        <div style="display:flex;flex-direction:column;">
            @foreach($withdrawals as $wr)
            <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <div style="font-weight:700;font-size:15px;">${{ number_format($wr->amount,2) }}</div>
                    <div style="font-size:11px;color:var(--text-muted);">{{ ucfirst($wr->payment_method ?? $wr->method) }} · {{ $wr->account_number }}</div>
                    <div style="font-size:10px;color:var(--text-muted);">{{ \Carbon\Carbon::parse($wr->created_at)->format('d M Y, H:i') }}</div>
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
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Request Withdrawal</h3>
            <button class="modal-close" onclick="closeModal('withdraw-modal')">✕</button>
        </div>
        <form action="{{ route('vendor.wallet.withdraw') }}" method="POST">
            @csrf
            <div class="form-group">
                <label class="form-label">Amount ($) *</label>
                <input type="number" name="amount" class="form-control" step="0.01" min="1" max="{{ $wallet->balance ?? 0 }}" required placeholder="0.00">
                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Available: ${{ number_format($wallet->balance ?? 0, 2) }}</div>
            </div>
            <div class="form-group">
                <label class="form-label">Payment Method *</label>
                <select name="payment_method" class="form-control" required>
                    <option value="">— Select —</option>
                    <option value="evc">EVC Plus</option>
                    <option value="waafi">Waafi</option>
                    <option value="others">Bank Transfer</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Account Name *</label>
                <input type="text" name="account_name" class="form-control" required placeholder="Full name">
            </div>
            <div class="form-group">
                <label class="form-label">Account Number / Phone *</label>
                <input type="text" name="account_number" class="form-control" required placeholder="Phone or account number">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Submit Withdrawal Request</button>
        </form>
    </div>
</div>
@endsection
