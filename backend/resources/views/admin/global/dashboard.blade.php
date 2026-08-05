@extends('admin.layouts.app')
@section('title', 'Global Store Dashboard')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">🌍 Global Store Dashboard</h1>
        <p class="page-subtitle">USA &amp; Europe eCommerce — Real-time overview</p>
    </div>
</div>

{{-- KPI Cards --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px">
    @php
    $kpis = [
        ['label'=>'Total Orders',    'value'=>number_format($stats['orders_total']),   'icon'=>'fa-shopping-cart','color'=>'#6366f1','sub'=>$stats['orders_today'].' today'],
        ['label'=>'This Month',      'value'=>number_format($stats['orders_month']),   'icon'=>'fa-calendar',     'color'=>'#10b981','sub'=>'orders'],
        ['label'=>'Total Revenue',   'value'=>'$'.number_format($stats['revenue'],2),  'icon'=>'fa-dollar-sign',  'color'=>'#f59e0b','sub'=>'all time'],
        ['label'=>'Global Users',    'value'=>number_format($stats['users']),           'icon'=>'fa-globe',        'color'=>'#3b82f6','sub'=>'registered'],
        ['label'=>'Total Products',  'value'=>number_format($stats['products']),        'icon'=>'fa-box',          'color'=>'#8b5cf6','sub'=>'in catalog'],
        ['label'=>'Low Stock',       'value'=>number_format($stats['low_stock']),       'icon'=>'fa-exclamation-triangle','color'=>'#ef4444','sub'=>'need restock'],
    ];
    @endphp
    @foreach($kpis as $k)
    <div class="stat-card" style="background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.08);border:1px solid #e5e7eb;display:flex;align-items:flex-start;gap:14px">
        <div style="width:44px;height:44px;border-radius:10px;background:{{ $k['color'] }}18;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fas {{ $k['icon'] }}" style="color:{{ $k['color'] }};font-size:18px"></i>
        </div>
        <div>
            <div style="font-size:22px;font-weight:800;color:#111;line-height:1.1">{{ $k['value'] }}</div>
            <div style="font-size:12px;font-weight:600;color:#374151;margin-top:2px">{{ $k['label'] }}</div>
            <div style="font-size:11px;color:#9ca3af;margin-top:2px">{{ $k['sub'] }}</div>
        </div>
    </div>
    @endforeach
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    {{-- Recent Orders --}}
    <div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);border:1px solid #e5e7eb;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center">
            <h3 style="font-size:14px;font-weight:700;color:#111">Recent Orders</h3>
            <a href="{{ route('admin.global.orders.index') }}" style="font-size:12px;color:#6366f1;text-decoration:none">View all →</a>
        </div>
        <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="background:#f9fafb">
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Order</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Customer</th>
                    <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Total</th>
                    <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentOrders as $order)
                @php
                $statusColors = [
                    'pending'=>['#f59e0b','#fffbeb'],'paid'=>['#10b981','#ecfdf5'],
                    'processing'=>['#6366f1','#eef2ff'],'shipped'=>['#3b82f6','#eff6ff'],
                    'delivered'=>['#10b981','#ecfdf5'],'cancelled'=>['#ef4444','#fef2f2'],
                    'refunded'=>['#8b5cf6','#f5f3ff'],'on_hold'=>['#f97316','#fff7ed'],
                ];
                $sc = $statusColors[$order->status] ?? ['#9ca3af','#f9fafb'];
                @endphp
                <tr style="border-top:1px solid #f3f4f6">
                    <td style="padding:10px 16px;font-size:12px;font-weight:600;color:#111">
                        <a href="{{ route('admin.global.orders.show', $order) }}" style="color:#6366f1;text-decoration:none">{{ $order->order_number }}</a>
                    </td>
                    <td style="padding:10px 16px;font-size:12px;color:#374151">{{ $order->user?->name ?? $order->shipping_name }}</td>
                    <td style="padding:10px 16px;font-size:12px;font-weight:700;color:#111;text-align:right">${{ number_format($order->total, 2) }}</td>
                    <td style="padding:10px 16px;text-align:center">
                        <span style="padding:2px 10px;border-radius:20px;font-size:10px;font-weight:700;color:{{ $sc[0] }};background:{{ $sc[1] }}">{{ strtoupper($order->status) }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" style="padding:24px;text-align:center;color:#9ca3af;font-size:13px">No orders yet</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- Revenue Chart --}}
    <div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);border:1px solid #e5e7eb;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6">
            <h3 style="font-size:14px;font-weight:700;color:#111">Revenue — Last 7 Days</h3>
        </div>
        <div style="padding:20px">
            @php
            $maxRev = max(array_column($revenueChart, 'revenue') ?: [1]);
            @endphp
            <div style="display:flex;align-items:flex-end;gap:10px;height:140px">
                @foreach($revenueChart as $day)
                @php $h = $maxRev > 0 ? max(4, ($day['revenue']/$maxRev)*120) : 4; @endphp
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px">
                    <div style="font-size:10px;font-weight:700;color:#374151">${{ number_format($day['revenue'],0) }}</div>
                    <div style="width:100%;background:linear-gradient(to top,#6366f1,#818cf8);border-radius:4px 4px 0 0;height:{{ $h }}px;min-height:4px"></div>
                    <div style="font-size:10px;color:#9ca3af">{{ \Carbon\Carbon::parse($day['date'])->format('M d') }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
