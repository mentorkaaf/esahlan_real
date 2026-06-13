@extends('admin.layouts.app')
@section('title', 'Reports')
@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Sales Report</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li>Reports</li>
        </ul>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('admin.reports.vendors') }}" class="btn btn-outline btn-sm"><i class="fas fa-store"></i> Vendor Report</a>
        <a href="{{ route('admin.reports.commissions') }}" class="btn btn-outline btn-sm"><i class="fas fa-percent"></i> Commissions</a>
        <a href="{{ route('admin.reports.sales.export') }}" class="btn btn-primary btn-sm"><i class="fas fa-download"></i> Export CSV</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card orange">
        <div class="stat-icon-wrap orange"><i class="fas fa-dollar-sign"></i></div>
        <div class="stat-body"><div class="stat-value">${{ number_format($summary['total_revenue'] ?? 0, 0) }}</div><div class="stat-label">Total Revenue</div></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon-wrap green"><i class="fas fa-shopping-bag"></i></div>
        <div class="stat-body"><div class="stat-value">{{ number_format($summary['total_orders'] ?? 0) }}</div><div class="stat-label">Total Orders</div></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon-wrap blue"><i class="fas fa-percentage"></i></div>
        <div class="stat-body"><div class="stat-value">${{ number_format($summary['total_commission'] ?? 0, 0) }}</div><div class="stat-label">Total Commission</div></div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon-wrap purple"><i class="fas fa-chart-line"></i></div>
        <div class="stat-body"><div class="stat-value">${{ number_format($summary['avg_order_value'] ?? 0, 2) }}</div><div class="stat-label">Avg Order Value</div></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);">
                <i class="fas fa-chart-bar"></i>
            </div>
            Sales by Module
        </div>
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <input type="date" name="from" class="form-control" style="width:140px;" value="{{ request('from', now()->subDays(30)->format('Y-m-d')) }}">
            <span style="font-size:12px;color:var(--text-muted);">to</span>
            <input type="date" name="to"   class="form-control" style="width:140px;" value="{{ request('to', now()->format('Y-m-d')) }}">
            <button class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
        </form>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr><th>Module</th><th>Orders</th><th>Revenue</th><th>Commission</th><th>Avg Value</th></tr>
            </thead>
            <tbody>
                @forelse($byModule ?? [] as $row)
                <tr>
                    <td class="fw-bold">{{ strtoupper($row->module_slug ?? '—') }}</td>
                    <td>{{ $row->orders }}</td>
                    <td>${{ number_format($row->revenue, 2) }}</td>
                    <td>${{ number_format($row->commission, 2) }}</td>
                    <td>${{ $row->orders > 0 ? number_format($row->revenue / $row->orders, 2) : '0.00' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;padding:30px;color:#888;">No data found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
