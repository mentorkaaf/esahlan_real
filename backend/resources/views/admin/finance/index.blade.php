@extends('admin.layouts.app')
@section('title', 'Finance & Payouts')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Finance & Payouts</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li>Finance</li>
        </ul>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('admin.finance.transactions') }}" class="btn btn-outline btn-sm"><i class="fas fa-list"></i> Transactions</a>
        <a href="{{ route('admin.finance.commissions') }}"  class="btn btn-outline btn-sm"><i class="fas fa-percent"></i> Commissions</a>
    </div>
</div>

{{-- ═══ ROW 1: Top KPIs ═══ --}}
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card orange">
        <div class="stat-icon-wrap orange"><i class="fas fa-dollar-sign"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($stats['gmvTotal'],2) }}</div>
            <div class="stat-label">Gross GMV (All Time)</div>
            <div class="stat-sub" style="color:var(--text-muted);font-size:11px;">Total delivered orders</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon-wrap green"><i class="fas fa-chart-pie"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($stats['commissionEarned'],2) }}</div>
            <div class="stat-label">Platform Revenue</div>
            <div class="stat-sub" style="color:var(--text-muted);font-size:11px;">Commissions earned</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon-wrap blue"><i class="fas fa-truck"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($stats['deliveryFeesTotal'],2) }}</div>
            <div class="stat-label">Delivery Fees Collected</div>
            <div class="stat-sub" style="color:var(--text-muted);font-size:11px;">incl. Peak Pay bonus ${{ number_format($stats['bonusPaidTotal'],2) }}</div>
        </div>
    </div>
    <div class="stat-card {{ $stats['netRevenue'] >= 0 ? 'green' : 'red' }}">
        <div class="stat-icon-wrap {{ $stats['netRevenue'] >= 0 ? 'green' : 'red' }}"><i class="fas fa-balance-scale"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format(abs($stats['netRevenue']),2) }}</div>
            <div class="stat-label">Net After Payouts</div>
            <div class="stat-sub" style="color:var(--text-muted);font-size:11px;">Revenue − processed payouts</div>
        </div>
    </div>
</div>

{{-- ═══ ROW 2: Withdrawal + Wallet KPIs ═══ --}}
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-top:-8px;">
    <div class="stat-card red">
        <div class="stat-icon-wrap red"><i class="fas fa-clock"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($stats['pendingAmt'],2) }}</div>
            <div class="stat-label">Pending Withdrawals</div>
            @if($stats['pendingCount'] > 0)
            <div class="stat-sub warn"><i class="fas fa-exclamation-circle"></i> {{ $stats['pendingCount'] }} request(s) need action</div>
            @else
            <div class="stat-sub" style="color:var(--success);font-size:11px;"><i class="fas fa-check-circle"></i> All clear</div>
            @endif
        </div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon-wrap purple"><i class="fas fa-paper-plane"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($stats['processedAmt'],2) }}</div>
            <div class="stat-label">Total Paid Out</div>
            <div class="stat-sub" style="color:var(--text-muted);font-size:11px;">Approved + processed</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon-wrap orange"><i class="fas fa-wallet"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($stats['walletTotal'],2) }}</div>
            <div class="stat-label">Total Wallet Balances</div>
            <div class="stat-sub" style="color:var(--text-muted);font-size:11px;">Across all users</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon-wrap blue"><i class="fas fa-gift"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($stats['discountsTotal'],2) }}</div>
            <div class="stat-label">Total Discounts Given</div>
            <div class="stat-sub" style="color:var(--text-muted);font-size:11px;">Coupons + manual discounts</div>
        </div>
    </div>
</div>

{{-- ═══ Revenue Waterfall + Wallet Breakdown ═══ --}}
<div style="display:grid;grid-template-columns:1fr 340px;gap:16px;margin-bottom:16px;">

    {{-- Revenue Waterfall --}}
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div class="card-header-title">
                <div class="card-header-icon" style="background:rgba(249,115,22,0.1);color:var(--brand);">
                    <i class="fas fa-funnel-dollar"></i>
                </div>
                Revenue by Module — All Time
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Module</th>
                        <th style="text-align:right;">Orders</th>
                        <th style="text-align:right;">GMV</th>
                        <th style="text-align:right;">Delivery Fees</th>
                        <th style="text-align:right;">Bonus</th>
                        <th style="text-align:right;">Discounts</th>
                        <th style="text-align:right;">Commission</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totalGmv = $revenueByModule->sum('gmv') ?: 1; @endphp
                    @forelse($revenueByModule as $row)
                    @php
                        $pct = $totalGmv > 0 ? round($row->gmv / $totalGmv * 100) : 0;
                        $colors = ['efood'=>'#f97316','egrocery'=>'#22c55e','eshop'=>'#3b82f6','eparcel'=>'#8b5cf6','elaundry'=>'#06b6d4','emoving'=>'#f59e0b','erent'=>'#ec4899'];
                        $col = $colors[strtolower($row->module_slug)] ?? '#6b7280';
                    @endphp
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:{{$col}};flex-shrink:0;"></span>
                                <div>
                                    <div style="font-weight:700;font-size:13px;">{{ strtoupper($row->module_slug) }}</div>
                                    <div style="height:4px;width:{{max($pct,2)}}px;background:{{$col}};border-radius:2px;margin-top:3px;max-width:120px;"></div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align:right;font-weight:600;">{{ number_format($row->orders) }}</td>
                        <td style="text-align:right;font-weight:800;color:var(--brand);">${{ number_format($row->gmv,2) }}</td>
                        <td style="text-align:right;color:var(--info);">${{ number_format($row->delivery_fees,2) }}</td>
                        <td style="text-align:right;color:#f97316;">${{ number_format($row->bonus,2) }}</td>
                        <td style="text-align:right;color:var(--danger);">-${{ number_format($row->discounts,2) }}</td>
                        <td style="text-align:right;font-weight:700;color:var(--success);">${{ number_format($row->commission,2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7"><div class="empty-state"><i class="fas fa-inbox"></i><h3>No revenue data</h3></div></td></tr>
                    @endforelse
                </tbody>
                @if($revenueByModule->count())
                <tfoot>
                    <tr style="background:rgba(var(--brand-rgb,249,115,22),0.05);font-weight:800;">
                        <td>TOTAL</td>
                        <td style="text-align:right;">{{ number_format($revenueByModule->sum('orders')) }}</td>
                        <td style="text-align:right;color:var(--brand);">${{ number_format($revenueByModule->sum('gmv'),2) }}</td>
                        <td style="text-align:right;color:var(--info);">${{ number_format($revenueByModule->sum('delivery_fees'),2) }}</td>
                        <td style="text-align:right;color:#f97316;">${{ number_format($revenueByModule->sum('bonus'),2) }}</td>
                        <td style="text-align:right;color:var(--danger);">-${{ number_format($revenueByModule->sum('discounts'),2) }}</td>
                        <td style="text-align:right;color:var(--success);">${{ number_format($revenueByModule->sum('commission'),2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Wallet Breakdown --}}
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div class="card-header-title">
                <div class="card-header-icon" style="background:rgba(139,92,246,0.1);color:#8b5cf6;">
                    <i class="fas fa-wallet"></i>
                </div>
                Wallet Balances
            </div>
        </div>
        <div style="padding:16px;">
            @php
                $walletColors = ['User'=>'#3b82f6','Deliveryman'=>'#22c55e','Vendor'=>'#f97316'];
                $walletIcons  = ['User'=>'fa-user','Deliveryman'=>'fa-motorcycle','Vendor'=>'fa-store'];
                $walletLabels = ['User'=>'Customer Wallets','Deliveryman'=>'Driver Wallets','Vendor'=>'Vendor Wallets'];
            @endphp
            @forelse($stats['walletByType'] as $type => $row)
            @php $wc = $walletColors[$type] ?? '#6b7280'; $wi = $walletIcons[$type] ?? 'fa-wallet'; @endphp
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;border-radius:10px;background:{{ $wc }}15;margin-bottom:10px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:34px;height:34px;border-radius:8px;background:{{$wc}};display:flex;align-items:center;justify-content:center;">
                        <i class="fas {{ $wi }}" style="color:#fff;font-size:14px;"></i>
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:13px;">{{ $walletLabels[$type] ?? $type }}</div>
                        <div style="font-size:11px;color:var(--text-muted);">{{ number_format($row->cnt) }} wallets</div>
                    </div>
                </div>
                <div style="font-size:18px;font-weight:900;color:{{$wc}};">${{ number_format($row->total,2) }}</div>
            </div>
            @empty
            <div style="text-align:center;padding:30px;color:var(--text-muted);">No wallet data</div>
            @endforelse

            <div style="border-top:2px solid var(--border);margin-top:8px;padding-top:12px;display:flex;justify-content:space-between;align-items:center;">
                <span style="font-weight:700;font-size:13px;">Total Locked in Wallets</span>
                <span style="font-size:20px;font-weight:900;color:var(--brand);">${{ number_format($stats['walletTotal'],2) }}</span>
            </div>
        </div>
    </div>
</div>

{{-- ═══ Pending Withdrawals ═══ --}}
<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(239,68,68,0.1);color:var(--danger);">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            Pending Withdrawal Requests
            @if($stats['pendingCount'] > 0)
            <span class="badge badge-danger" style="margin-left:6px;">{{ $stats['pendingCount'] }}</span>
            @endif
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Owner</th>
                    <th>Type</th>
                    <th style="text-align:right;">Amount</th>
                    <th>Method</th>
                    <th>Account Details</th>
                    <th>Requested</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pendingWithdrawals as $w)
                <tr>
                    <td>
                        @php $ownerName = $w->owner?->name ?? ($w->owner?->full_name ?? null); @endphp
                        <div style="font-weight:700;font-size:13px;">{{ $ownerName ?? 'ID: '.$w->owner_id }}</div>
                        <div style="font-size:11px;color:var(--text-muted);">{{ class_basename($w->owner_type) }}</div>
                    </td>
                    <td>
                        @php $typeColor = match(class_basename($w->owner_type)) {'User'=>'blue','Deliveryman'=>'green','Vendor'=>'orange',default=>''}; @endphp
                        <span class="badge badge-{{ $typeColor ?: 'info' }}">{{ class_basename($w->owner_type) }}</span>
                    </td>
                    <td style="text-align:right;"><span style="font-size:16px;font-weight:900;color:var(--success);">${{ number_format($w->amount,2) }}</span></td>
                    <td><span class="badge badge-info">{{ ucwords(str_replace('_',' ',$w->payment_method)) }}</span></td>
                    <td>
                        <div style="font-weight:600;font-size:13px;">{{ $w->account_name }}</div>
                        <div style="font-size:11px;color:var(--text-muted);font-family:monospace;">{{ $w->account_number }}</div>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);">{{ $w->created_at->format('d M Y H:i') }}</td>
                    <td>
                        <div style="display:flex;gap:5px;">
                            <button class="btn btn-xs btn-success" onclick="openModal('approveModal{{ $w->id }}')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <form method="POST" action="{{ route('admin.finance.withdrawals.reject',$w) }}">
                                @csrf
                                <button class="btn btn-xs" style="background:#fff5f5;color:var(--danger);border:1.5px solid #fecaca;">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                        </div>

                        <div class="modal-overlay" id="approveModal{{ $w->id }}">
                            <div class="modal-box">
                                <div class="modal-header">
                                    <div class="modal-title">Approve Withdrawal — ${{ number_format($w->amount,2) }}</div>
                                    <button class="modal-close" onclick="closeModal('approveModal{{ $w->id }}')"><i class="fas fa-times"></i></button>
                                </div>
                                <form method="POST" action="{{ route('admin.finance.withdrawals.approve',$w) }}">
                                    @csrf
                                    <div class="modal-body">
                                        <div style="background:rgba(16,185,129,0.08);border-radius:10px;padding:14px;margin-bottom:16px;">
                                            <div style="display:flex;justify-content:space-between;font-size:13px;">
                                                <span style="color:var(--text-muted);">Owner</span>
                                                <span style="font-weight:700;">{{ $ownerName ?? class_basename($w->owner_type).' #'.$w->owner_id }}</span>
                                            </div>
                                            <div style="display:flex;justify-content:space-between;font-size:13px;margin-top:6px;">
                                                <span style="color:var(--text-muted);">Amount</span>
                                                <span style="font-weight:900;color:var(--success);font-size:18px;">${{ number_format($w->amount,2) }}</span>
                                            </div>
                                            <div style="display:flex;justify-content:space-between;font-size:13px;margin-top:6px;">
                                                <span style="color:var(--text-muted);">Method</span>
                                                <span style="font-weight:600;">{{ $w->payment_method }}</span>
                                            </div>
                                            <div style="display:flex;justify-content:space-between;font-size:13px;margin-top:6px;">
                                                <span style="color:var(--text-muted);">Account</span>
                                                <span style="font-weight:600;font-family:monospace;">{{ $w->account_number }}</span>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Transaction Reference <span style="color:var(--danger);">*</span></label>
                                            <input type="text" name="transaction_reference" class="form-control" required placeholder="Enter WaafiPay / EVC reference…">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('approveModal{{ $w->id }}')">Cancel</button>
                                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Confirm Approval</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-check-circle" style="color:var(--success);font-size:36px;"></i>
                            <h3>No pending withdrawals</h3>
                            <p>All withdrawal requests have been processed</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($pendingWithdrawals->hasPages())
    <div class="card-footer" style="display:flex;justify-content:center;">{{ $pendingWithdrawals->links() }}</div>
    @endif
</div>

{{-- ═══ Recent Processed Withdrawals ═══ --}}
@if($recentProcessed->count())
<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(107,114,128,0.1);color:var(--text-muted);">
                <i class="fas fa-history"></i>
            </div>
            Recent Processed / Rejected Withdrawals
        </div>
        <a href="{{ route('admin.finance.transactions') }}" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Owner</th>
                    <th style="text-align:right;">Amount</th>
                    <th>Status</th>
                    <th>Reference</th>
                    <th>Processed</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentProcessed as $w)
                @php $ownerN = $w->owner?->name ?? ($w->owner?->full_name ?? 'ID:'.$w->owner_id); @endphp
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:13px;">{{ $ownerN }}</div>
                        <div style="font-size:11px;color:var(--text-muted);">{{ class_basename($w->owner_type) }}</div>
                    </td>
                    <td style="text-align:right;font-weight:800;">${{ number_format($w->amount,2) }}</td>
                    <td>
                        @if($w->status === 'processed' || $w->status === 'completed')
                        <span class="badge badge-success">Processed</span>
                        @else
                        <span class="badge badge-danger">Rejected</span>
                        @endif
                    </td>
                    <td style="font-size:12px;font-family:monospace;color:var(--text-muted);">{{ $w->transaction_reference ?? '—' }}</td>
                    <td style="font-size:12px;color:var(--text-muted);">{{ $w->processed_at?->format('d M Y H:i') ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
