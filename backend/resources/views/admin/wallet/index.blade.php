@extends('admin.layouts.app')
@section('title', 'Wallet Management')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Wallet Management</h1>
        <ul class="breadcrumb"><li><span>Finance</span></li><li><span>Wallets</span></li></ul>
    </div>
    <div style="display:flex;gap:10px">
        <a href="{{ route('admin.wallet.transactions') }}" class="btn btn-outline-primary"><i class="fas fa-list"></i> All Transactions</a>
        <a href="{{ route('admin.wallet.withdrawals') }}" class="btn btn-outline-warning"><i class="fas fa-money-bill-wave"></i> Withdrawals</a>
        <a href="{{ route('admin.wallet.settings') }}" class="btn btn-outline-secondary"><i class="fas fa-cog"></i> Payment Settings</a>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

{{-- Stats --}}
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
    <div class="stat-card" style="background:#fff;border:1.5px solid #f0f1f5;border-radius:14px;padding:20px">
        <div style="font-size:12px;color:#8A8A9A;margin-bottom:6px">Total Balance (All Users)</div>
        <div style="font-size:26px;font-weight:900;color:#07003B">${{ number_format($stats['total_balance'],2) }}</div>
    </div>
    <div class="stat-card" style="background:#fff;border:1.5px solid #f0f1f5;border-radius:14px;padding:20px">
        <div style="font-size:12px;color:#8A8A9A;margin-bottom:6px">Total Topups (Waafi Pay)</div>
        <div style="font-size:26px;font-weight:900;color:#27AE60">${{ number_format($stats['total_topups'],2) }}</div>
    </div>
    <div class="stat-card" style="background:#fff;border:1.5px solid #f0f1f5;border-radius:14px;padding:20px">
        <div style="font-size:12px;color:#8A8A9A;margin-bottom:6px">Pending Withdrawals</div>
        <div style="font-size:26px;font-weight:900;color:#E74C3C">${{ number_format($stats['pending_withdrawals'],2) }}</div>
    </div>
    <div class="stat-card" style="background:#fff;border:1.5px solid #f0f1f5;border-radius:14px;padding:20px">
        <div style="font-size:12px;color:#8A8A9A;margin-bottom:6px">Users with Wallets</div>
        <div style="font-size:26px;font-weight:900;color:#1565C0">{{ number_format($stats['user_count']) }}</div>
    </div>
</div>

{{-- Manual Credit Form --}}
<div style="background:#fff;border-radius:14px;border:1.5px solid #f0f1f5;padding:20px;margin-bottom:24px">
    <h3 style="margin:0 0 16px;font-size:16px;font-weight:800;color:#07003B"><i class="fas fa-plus-circle" style="color:#FF8A00;margin-right:8px"></i>Manual Wallet Credit</h3>
    <form method="POST" action="{{ route('admin.wallet.credit') }}" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        @csrf
        <div style="flex:2;min-width:200px">
            <label style="font-size:12px;font-weight:600;color:#8A8A9A;display:block;margin-bottom:4px">User</label>
            <select name="user_id" required style="width:100%;padding:10px;border:1.5px solid #f0f1f5;border-radius:8px;font-size:14px">
                <option value="">Select user...</option>
                @foreach($users as $u)
                    @if($u->wallet_id)
                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }}) — ${{ number_format($u->balance,2) }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div style="flex:1;min-width:120px">
            <label style="font-size:12px;font-weight:600;color:#8A8A9A;display:block;margin-bottom:4px">Amount ($)</label>
            <input type="number" name="amount" min="0.01" step="0.01" required placeholder="0.00" style="width:100%;padding:10px;border:1.5px solid #f0f1f5;border-radius:8px;font-size:14px">
        </div>
        <div style="flex:2;min-width:200px">
            <label style="font-size:12px;font-weight:600;color:#8A8A9A;display:block;margin-bottom:4px">Note / Reason</label>
            <input type="text" name="note" required placeholder="e.g. Refund for order #123" style="width:100%;padding:10px;border:1.5px solid #f0f1f5;border-radius:8px;font-size:14px">
        </div>
        <button type="submit" class="btn btn-primary" style="height:42px;padding:0 20px;white-space:nowrap"><i class="fas fa-plus"></i> Add Credit</button>
    </form>
</div>

{{-- Users Table --}}
<div style="background:#fff;border-radius:14px;border:1.5px solid #f0f1f5;overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #f0f1f5;font-weight:800;font-size:15px;color:#07003B">
        User Wallets <span style="font-size:12px;font-weight:400;color:#8A8A9A;margin-left:8px">{{ $users->total() }} users</span>
    </div>
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="background:#f8f9fa;font-size:12px;color:#8A8A9A;font-weight:700;text-transform:uppercase">
                <th style="padding:12px 16px;text-align:left">User</th>
                <th style="padding:12px 16px;text-align:right">Balance</th>
                <th style="padding:12px 16px;text-align:right">Total Earned</th>
                <th style="padding:12px 16px;text-align:right">Withdrawn</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $u)
            <tr style="border-top:1px solid #f0f1f5;font-size:13px">
                <td style="padding:12px 16px">
                    <div style="font-weight:700;color:#07003B">{{ $u->name }}</div>
                    <div style="font-size:11px;color:#8A8A9A">{{ $u->email }} · {{ $u->phone }}</div>
                </td>
                <td style="padding:12px 16px;text-align:right;font-weight:800;color:{{ $u->balance > 0 ? '#27AE60' : '#8A8A9A' }}">${{ number_format($u->balance ?? 0, 2) }}</td>
                <td style="padding:12px 16px;text-align:right;color:#1565C0">${{ number_format($u->total_earned ?? 0, 2) }}</td>
                <td style="padding:12px 16px;text-align:right;color:#E74C3C">${{ number_format($u->total_withdrawn ?? 0, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:16px">{{ $users->links() }}</div>
</div>
@endsection
