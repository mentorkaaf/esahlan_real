@extends('admin.layouts.app')
@section('title', 'Wallet Transactions')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Wallet Transactions</h1>
        <ul class="breadcrumb"><li><a href="{{ route('admin.wallet.index') }}">Wallets</a></li><li><span>Transactions</span></li></ul>
    </div>
    <form method="GET" style="display:flex;gap:8px">
        <input name="search" value="{{ request('search') }}" placeholder="Search user or note..." style="padding:8px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:13px;width:220px">
        <select name="type" style="padding:8px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:13px">
            <option value="">All types</option>
            <option value="credit" @selected(request('type')=='credit')>Credit</option>
            <option value="debit" @selected(request('type')=='debit')>Debit</option>
        </select>
        <button class="btn btn-primary" style="padding:8px 16px">Filter</button>
    </form>
</div>

<div style="background:#fff;border-radius:14px;border:1.5px solid #f0f1f5;overflow:hidden">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
            <tr style="background:#f8f9fa;font-size:11px;color:#8A8A9A;font-weight:700;text-transform:uppercase">
                <th style="padding:12px 16px;text-align:left">User</th>
                <th style="padding:12px 16px;text-align:left">Note</th>
                <th style="padding:12px 16px;text-align:center">Type</th>
                <th style="padding:12px 16px;text-align:right">Amount</th>
                <th style="padding:12px 16px;text-align:right">Balance After</th>
                <th style="padding:12px 16px;text-align:right">Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $tx)
            <tr style="border-top:1px solid #f0f1f5">
                <td style="padding:12px 16px">
                    <div style="font-weight:700;color:#07003B">{{ $tx->user_name }}</div>
                    <div style="font-size:11px;color:#8A8A9A">{{ $tx->user_email }}</div>
                </td>
                <td style="padding:12px 16px;color:#555;max-width:240px">{{ $tx->note }}</td>
                <td style="padding:12px 16px;text-align:center">
                    <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;
                        background:{{ $tx->type=='credit' ? '#E8F5E9' : '#FFEBEE' }};
                        color:{{ $tx->type=='credit' ? '#27AE60' : '#E74C3C' }}">
                        {{ strtoupper($tx->type) }}
                    </span>
                </td>
                <td style="padding:12px 16px;text-align:right;font-weight:800;color:{{ $tx->type=='credit' ? '#27AE60' : '#E74C3C' }}">
                    {{ $tx->type=='credit' ? '+' : '-' }}${{ number_format($tx->amount, 2) }}
                </td>
                <td style="padding:12px 16px;text-align:right;color:#1565C0;font-weight:700">${{ number_format($tx->balance_after ?? 0, 2) }}</td>
                <td style="padding:12px 16px;text-align:right;color:#8A8A9A">{{ \Carbon\Carbon::parse($tx->created_at)->format('M d, Y H:i') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:16px">{{ $transactions->links() }}</div>
</div>
@endsection
