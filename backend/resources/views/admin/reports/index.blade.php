@extends('admin.layouts.app')
@section('title', 'Sales Report')
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
        <a href="{{ route('admin.reports.vendors') }}"     class="btn btn-outline btn-sm"><i class="fas fa-store"></i> Vendor Report</a>
        <a href="{{ route('admin.reports.commissions') }}" class="btn btn-outline btn-sm"><i class="fas fa-percent"></i> Commissions</a>
        <a href="{{ route('admin.reports.sales.export', ['from'=>$dateFrom,'to'=>$dateTo]) }}" class="btn btn-primary btn-sm"><i class="fas fa-download"></i> Export CSV</a>
    </div>
</div>

{{-- Date Filter --}}
<div class="card" style="margin-bottom:16px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;padding:16px;">
        <label style="font-weight:600;font-size:13px;white-space:nowrap;">Date Range:</label>
        <input type="date" name="from" class="form-control" style="width:150px;" value="{{ $dateFrom }}">
        <span style="color:var(--text-muted);">to</span>
        <input type="date" name="to" class="form-control" style="width:150px;" value="{{ $dateTo }}">
        <button class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
        <a href="{{ route('admin.reports.sales') }}" class="btn btn-outline btn-sm">Reset</a>
        <span style="margin-left:auto;font-size:12px;color:var(--text-muted);">
            <i class="fas fa-calendar-alt"></i> {{ \Carbon\Carbon::parse($dateFrom)->format('d M') }} — {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
        </span>
    </form>
</div>

@if($summary)
{{-- ═══ ROW 1: Core KPIs ═══ --}}
@php
    $cancelRate = $summary->total_orders > 0
        ? round($summary->cancelled_orders / $summary->total_orders * 100, 1) : 0;
    $netRevenue = ($summary->total_revenue ?? 0) - ($summary->total_discounts ?? 0);
@endphp
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="stat-card orange">
        <div class="stat-icon-wrap orange"><i class="fas fa-dollar-sign"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($summary->total_revenue ?? 0,2) }}</div>
            <div class="stat-label">Gross Revenue (GMV)</div>
            <div class="stat-sub" style="font-size:11px;color:var(--text-muted);">Products + Delivery fees</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon-wrap green"><i class="fas fa-shopping-bag"></i></div>
        <div class="stat-body">
            <div class="stat-value">{{ number_format($summary->total_orders ?? 0) }}</div>
            <div class="stat-label">Total Orders</div>
            <div class="stat-sub" style="font-size:11px;color:{{ $cancelRate > 10 ? 'var(--danger)' : 'var(--text-muted)' }};">
                {{ $summary->cancelled_orders ?? 0 }} cancelled ({{ $cancelRate }}%)
            </div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon-wrap blue"><i class="fas fa-percentage"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($summary->total_commission ?? 0,2) }}</div>
            <div class="stat-label">Platform Commission</div>
            <div class="stat-sub" style="font-size:11px;color:var(--text-muted);">
                @if($summary->total_revenue > 0)
                {{ round($summary->total_commission / $summary->total_revenue * 100, 1) }}% of GMV
                @endif
            </div>
        </div>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-top:-8px;">
    <div class="stat-card purple">
        <div class="stat-icon-wrap purple"><i class="fas fa-box"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($summary->total_subtotal ?? 0,2) }}</div>
            <div class="stat-label">Product Sales</div>
            <div class="stat-sub" style="font-size:11px;color:var(--text-muted);">Before delivery & fees</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon-wrap blue"><i class="fas fa-truck"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($summary->total_delivery ?? 0,2) }}</div>
            <div class="stat-label">Delivery Fees</div>
            <div class="stat-sub" style="font-size:11px;color:var(--text-muted);">incl. bonus ${{ number_format($summary->total_bonus ?? 0,2) }}</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon-wrap red"><i class="fas fa-tag"></i></div>
        <div class="stat-body">
            <div class="stat-value">-${{ number_format($summary->total_discounts ?? 0,2) }}</div>
            <div class="stat-label">Discounts Given</div>
            <div class="stat-sub" style="font-size:11px;color:var(--text-muted);">Coupons + manual</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon-wrap green"><i class="fas fa-chart-line"></i></div>
        <div class="stat-body">
            <div class="stat-value">${{ number_format($summary->avg_order_value ?? 0,2) }}</div>
            <div class="stat-label">Avg Order Value</div>
            <div class="stat-sub" style="font-size:11px;color:var(--text-muted);">Delivered orders only</div>
        </div>
    </div>
</div>
@endif

{{-- ═══ Revenue Breakdown by Module ═══ --}}
@if(isset($byModule) && $byModule->count())
<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);">
                <i class="fas fa-chart-bar"></i>
            </div>
            Revenue Breakdown by Module
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Module</th>
                    <th style="text-align:right;">Orders</th>
                    <th style="text-align:right;">Product Sales</th>
                    <th style="text-align:right;">Delivery Fees</th>
                    <th style="text-align:right;">Peak Bonus</th>
                    <th style="text-align:right;">Discounts</th>
                    <th style="text-align:right;">Total GMV</th>
                    <th style="text-align:right;">Commission</th>
                    <th style="text-align:right;">Avg Order</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $colors = ['efood'=>'#f97316','egrocery'=>'#22c55e','eshop'=>'#3b82f6','eparcel'=>'#8b5cf6','elaundry'=>'#06b6d4','emoving'=>'#f59e0b','erent'=>'#ec4899'];
                @endphp
                @foreach($byModule as $row)
                @php $col = $colors[strtolower($row->module_slug)] ?? '#6b7280'; @endphp
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:{{$col}};flex-shrink:0;"></span>
                            <span style="font-weight:700;">{{ strtoupper($row->module_slug) }}</span>
                        </div>
                    </td>
                    <td style="text-align:right;font-weight:600;">{{ number_format($row->orders) }}</td>
                    <td style="text-align:right;">${{ number_format($row->subtotal ?? 0,2) }}</td>
                    <td style="text-align:right;color:var(--info);">${{ number_format($row->delivery_fees ?? 0,2) }}</td>
                    <td style="text-align:right;color:#f97316;">${{ number_format($row->bonus ?? 0,2) }}</td>
                    <td style="text-align:right;color:var(--danger);">-${{ number_format($row->discounts ?? 0,2) }}</td>
                    <td style="text-align:right;font-weight:800;color:var(--brand);">${{ number_format($row->revenue,2) }}</td>
                    <td style="text-align:right;font-weight:700;color:var(--success);">${{ number_format($row->commission,2) }}</td>
                    <td style="text-align:right;color:var(--text-muted);">${{ $row->orders > 0 ? number_format($row->revenue / $row->orders,2) : '0.00' }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight:900;background:rgba(249,115,22,0.05);">
                    <td>TOTAL</td>
                    <td style="text-align:right;">{{ number_format($byModule->sum('orders')) }}</td>
                    <td style="text-align:right;">${{ number_format($byModule->sum('subtotal'),2) }}</td>
                    <td style="text-align:right;color:var(--info);">${{ number_format($byModule->sum('delivery_fees'),2) }}</td>
                    <td style="text-align:right;color:#f97316;">${{ number_format($byModule->sum('bonus'),2) }}</td>
                    <td style="text-align:right;color:var(--danger);">-${{ number_format($byModule->sum('discounts'),2) }}</td>
                    <td style="text-align:right;color:var(--brand);">${{ number_format($byModule->sum('revenue'),2) }}</td>
                    <td style="text-align:right;color:var(--success);">${{ number_format($byModule->sum('commission'),2) }}</td>
                    <td style="text-align:right;">—</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endif

{{-- ═══ Two-column: Daily Trend + Top Vendors ═══ --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

    {{-- Daily Trend --}}
    @if(isset($daily) && $daily->count())
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div class="card-header-title">
                <div class="card-header-icon" style="background:rgba(34,197,94,0.1);color:var(--success);">
                    <i class="fas fa-chart-line"></i>
                </div>
                Daily Revenue Trend
            </div>
        </div>
        <div class="table-wrap" style="max-height:340px;overflow-y:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th style="text-align:right;">Orders</th>
                        <th style="text-align:right;">Revenue</th>
                        <th style="text-align:right;">Delivery</th>
                        <th>Bar</th>
                    </tr>
                </thead>
                <tbody>
                    @php $maxRev = $daily->max('revenue') ?: 1; @endphp
                    @foreach($daily as $d)
                    @php $barW = max(round($d->revenue / $maxRev * 100), 2); @endphp
                    <tr>
                        <td style="font-size:12px;white-space:nowrap;font-weight:600;">{{ \Carbon\Carbon::parse($d->date)->format('d M') }}</td>
                        <td style="text-align:right;font-size:12px;">{{ $d->orders }}</td>
                        <td style="text-align:right;font-weight:700;font-size:12px;color:var(--brand);">${{ number_format($d->revenue,2) }}</td>
                        <td style="text-align:right;font-size:11px;color:var(--text-muted);">${{ number_format($d->delivery_fees ?? 0,2) }}</td>
                        <td style="width:80px;">
                            <div style="height:8px;width:{{$barW}}%;background:linear-gradient(90deg,#f97316,#fb923c);border-radius:4px;"></div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Top Vendors --}}
    @if(isset($topVendors) && $topVendors->count())
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div class="card-header-title">
                <div class="card-header-icon" style="background:rgba(139,92,246,0.1);color:#8b5cf6;">
                    <i class="fas fa-store"></i>
                </div>
                Top Vendors by Revenue
            </div>
        </div>
        <div class="table-wrap" style="max-height:340px;overflow-y:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Vendor</th>
                        <th>Module</th>
                        <th style="text-align:right;">Orders</th>
                        <th style="text-align:right;">Revenue</th>
                        <th style="text-align:right;">Commission</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topVendors as $i => $v)
                    <tr>
                        <td style="font-weight:900;color:{{ $i < 3 ? 'var(--brand)' : 'var(--text-muted)' }};font-size:14px;">{{ $i+1 }}</td>
                        <td style="font-weight:700;font-size:13px;">{{ $v->vendor_name }}</td>
                        <td><span class="badge badge-info" style="font-size:10px;">{{ strtoupper($v->module_slug) }}</span></td>
                        <td style="text-align:right;">{{ number_format($v->orders) }}</td>
                        <td style="text-align:right;font-weight:800;color:var(--brand);">${{ number_format($v->revenue,2) }}</td>
                        <td style="text-align:right;color:var(--success);">${{ number_format($v->commission,2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Vendor paginated list (from vendorReport route) --}}
    @if(isset($vendors) && $vendors)
    <div class="card" style="margin-bottom:0;grid-column:1/-1;">
        <div class="card-header">
            <div class="card-header-title">
                <div class="card-header-icon" style="background:rgba(139,92,246,0.1);color:#8b5cf6;">
                    <i class="fas fa-store"></i>
                </div>
                Vendor Report — {{ $dateFrom }} to {{ $dateTo }}
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th style="text-align:right;">Orders</th>
                        <th style="text-align:right;">Revenue</th>
                        <th style="text-align:right;">Commission</th>
                        <th style="text-align:right;">Avg Order</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendors as $v)
                    <tr>
                        <td style="font-weight:700;">{{ $v->name }}</td>
                        <td style="text-align:right;">{{ number_format($v->orders_count ?? 0) }}</td>
                        <td style="text-align:right;font-weight:800;color:var(--brand);">${{ number_format($v->orders_revenue ?? 0,2) }}</td>
                        <td style="text-align:right;color:var(--success);">${{ number_format($v->orders_commission ?? 0,2) }}</td>
                        <td style="text-align:right;color:var(--text-muted);">
                            ${{ ($v->orders_count ?? 0) > 0 ? number_format(($v->orders_revenue ?? 0) / $v->orders_count, 2) : '0.00' }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5"><div class="empty-state"><i class="fas fa-store-slash"></i><h3>No vendor data</h3></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($vendors->hasPages())
        <div class="card-footer" style="display:flex;justify-content:center;">{{ $vendors->links() }}</div>
        @endif
    </div>
    @endif
</div>

@endsection
