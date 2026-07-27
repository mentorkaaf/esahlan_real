@extends('vendor.layouts.app')
@section('title', 'Earnings')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Earnings & Payouts</h1>
        <ul class="breadcrumb"><li><a href="{{ route('vendor.eshop.dashboard') }}">Dashboard</a></li><li>Earnings</li></ul>
    </div>
</div>

{{-- Summary Cards --}}
<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card green">
        <div class="stat-icon-wrap green"><i class="fa-solid fa-check-circle"></i></div>
        <div>
            <div class="stat-value">${{ number_format($summary->paid ?? 0, 2) }}</div>
            <div class="stat-label">Total Paid Out</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon-wrap orange"><i class="fa-solid fa-clock"></i></div>
        <div>
            <div class="stat-value">${{ number_format($summary->pending ?? 0, 2) }}</div>
            <div class="stat-label">Pending Payout</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon-wrap red"><i class="fa-solid fa-percentage"></i></div>
        <div>
            <div class="stat-value">${{ number_format($summary->total_commission ?? 0, 2) }}</div>
            <div class="stat-label">Total Commission Paid</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;">
{{-- Commission History --}}
<div class="card">
    <div class="card-header"><h3 class="card-title">Commission History</h3></div>
    <div class="table-responsive">
    <table class="data-table">
        <thead><tr><th>Order #</th><th>Order Total</th><th>Commission</th><th>Your Earning</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        @forelse($commissions as $c)
        <tr>
            <td><code style="font-weight:700">{{ $c->order_number }}</code></td>
            <td>${{ number_format($c->order_amount, 2) }}</td>
            <td style="color:#ef4444;font-weight:700">-${{ number_format($c->commission_amount ?? 0, 2) }}</td>
            <td style="color:#22c55e;font-weight:800">${{ number_format($c->vendor_earning ?? 0, 2) }}</td>
            <td>
                <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:{{ $c->status==='paid'?'#dcfce7':'#fef9c3' }};color:{{ $c->status==='paid'?'#16a34a':'#92400e' }}">
                    {{ ucfirst($c->status) }}
                </span>
            </td>
            <td style="font-size:12px;color:#888">{{ \Carbon\Carbon::parse($c->order_date ?? $c->created_at)->format('d M Y') }}</td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:#aaa;padding:40px">No earnings yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div style="padding:12px 16px">{{ $commissions->links() }}</div>
</div>

{{-- Withdrawal Requests --}}
<div>
<div class="card">
    <div class="card-header"><h3 class="card-title">Withdrawal History</h3></div>
    <div style="padding:0 16px 12px;">
        @forelse($withdrawals as $w)
        <div style="padding:12px 0;border-bottom:1px solid #f0f0f0;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-weight:700;font-size:14px">${{ number_format($w->amount, 2) }}</div>
                    <div style="font-size:11px;color:#aaa">{{ ucwords(str_replace('_',' ',$w->method ?? 'bank')) }}</div>
                    <div style="font-size:11px;color:#888">{{ \Carbon\Carbon::parse($w->created_at)->format('d M Y') }}</div>
                </div>
                <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:{{ $w->status==='approved'?'#dcfce7':($w->status==='rejected'?'#fee2e2':'#fef9c3') }};color:{{ $w->status==='approved'?'#16a34a':($w->status==='rejected'?'#991b1b':'#92400e') }}">
                    {{ ucfirst($w->status) }}
                </span>
            </div>
            @if($w->admin_note)<div style="font-size:11px;color:#888;margin-top:4px;">Note: {{ $w->admin_note }}</div>@endif
        </div>
        @empty
        <div style="text-align:center;color:#aaa;padding:32px 0;font-size:13px;">No withdrawal requests yet.</div>
        @endforelse
    </div>
</div>

<div class="card" style="padding:20px;margin-top:0;">
    <h4 style="font-weight:800;font-size:14px;margin-bottom:14px;color:var(--navy)">Request Payout</h4>
    <p style="font-size:12px;color:#888;margin-bottom:12px;">Available balance: <strong style="color:#22c55e">${{ number_format($summary->pending ?? 0, 2) }}</strong></p>
    <p style="font-size:12px;color:#aaa;">Contact admin through the admin panel or your account manager to request a payout.</p>
</div>
</div>
</div>

@endsection
