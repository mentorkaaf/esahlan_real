@extends('admin.layouts.app')
@section('title', 'Commissions')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Commission Records</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('admin.finance.index') }}">Finance</a></li>
            <li>Commissions</li>
        </ul>
    </div>
    <a href="{{ route('admin.finance.index') }}" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Finance
    </a>
</div>

{{-- Summary --}}
@php
    $totalCommission  = $commissions->sum('platform_amount');
    $pendingAmount    = $commissions->where('status','pending')->sum('platform_amount');
    $paidAmount       = $commissions->where('status','paid')->sum('platform_amount');
@endphp
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
    <div class="stat-card orange">
        <div class="stat-icon-wrap orange"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($totalCommission,2) }}</div>
            <div class="stat-label">Page Total Commission</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon-wrap green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($paidAmount,2) }}</div>
            <div class="stat-label">Paid</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon-wrap red"><i class="fas fa-clock"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($pendingAmount,2) }}</div>
            <div class="stat-label">Pending</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;width:100%;">
            <select name="status" class="form-control" style="width:160px;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="pending" {{ request('status')==='pending'?'selected':'' }}>Pending</option>
                <option value="paid"    {{ request('status')==='paid'   ?'selected':'' }}>Paid</option>
            </select>
            <input type="text" name="vendor_id" class="form-control" style="width:160px;"
                   placeholder="Vendor ID…" value="{{ request('vendor_id') }}">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            @if(request()->hasAny(['status','vendor_id']))
            <a href="{{ route('admin.finance.commissions') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);">
                <i class="fas fa-percentage"></i>
            </div>
            Commissions
            <span class="badge badge-orange" style="margin-left:4px;">{{ $commissions->total() }}</span>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Vendor</th>
                    <th>Order Amount</th>
                    <th>Commission Rate</th>
                    <th>Platform Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($commissions as $comm)
                <tr>
                    <td>
                        @if($comm->order)
                        <a href="{{ route('admin.orders.show', $comm->order) }}" style="font-weight:700;color:var(--navy);text-decoration:none;font-size:13px;">
                            {{ $comm->order->order_number }}
                        </a>
                        @else
                        <span class="text-muted">Order #{{ $comm->order_id }}</span>
                        @endif
                    </td>
                    <td>
                        @if($comm->vendor)
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="avatar avatar-sm avatar-purple">{{ strtoupper(substr($comm->vendor->name,0,1)) }}</div>
                            <span style="font-weight:600;font-size:13px;">{{ $comm->vendor->name }}</span>
                        </div>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td style="font-weight:700;">${{ number_format($comm->order_amount ?? 0, 2) }}</td>
                    <td>
                        <span class="badge badge-info">{{ $comm->commission_rate ?? 0 }}%</span>
                    </td>
                    <td>
                        <span style="font-weight:800;font-size:14px;color:var(--brand);">
                            ${{ number_format($comm->platform_amount ?? 0, 2) }}
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ ($comm->status??'pending')==='paid'?'badge-success':'badge-warning' }} badge-dot">
                            {{ ucfirst($comm->status ?? 'pending') }}
                        </span>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);">
                        {{ \Carbon\Carbon::parse($comm->created_at)->format('d M Y') }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-percentage"></i>
                            <h3>No commissions found</h3>
                            <p>Commissions will appear here as orders are processed</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($commissions->hasPages())
    <div class="card-footer" style="display:flex;justify-content:center;">
        {{ $commissions->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
