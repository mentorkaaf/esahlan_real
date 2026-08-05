@extends('admin.layouts.app')
@section('title', 'Refunds')

@section('content')
<div class="page-header">
    <div><h1 class="page-title">↩️ Refund Management</h1><p class="page-subtitle">Process and track customer refunds</p></div>
</div>

@if(session('success'))<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>@endif
@if(session('error'))<div style="background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">❌ {{ session('error') }}</div>@endif

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px">
    <div style="background:#fff;border-radius:10px;padding:16px;border:1px solid #e5e7eb;display:flex;align-items:center;gap:12px">
        <div style="width:40px;height:40px;border-radius:9px;background:#fef2f218;display:flex;align-items:center;justify-content:center"><i class="fas fa-dollar-sign" style="color:#ef4444;font-size:18px"></i></div>
        <div><div style="font-size:20px;font-weight:800;color:#ef4444">${{ number_format($stats['total_refunded'],2) }}</div><div style="font-size:12px;color:#6b7280">Total Refunded</div></div>
    </div>
    <div style="background:#fff;border-radius:10px;padding:16px;border:1px solid #e5e7eb;display:flex;align-items:center;gap:12px">
        <div style="width:40px;height:40px;border-radius:9px;background:#fffbeb18;display:flex;align-items:center;justify-content:center"><i class="fas fa-clock" style="color:#f59e0b;font-size:18px"></i></div>
        <div><div style="font-size:20px;font-weight:800;color:#f59e0b">{{ $stats['pending_refund'] }}</div><div style="font-size:12px;color:#6b7280">Pending Refunds</div></div>
    </div>
    <div style="background:#fff;border-radius:10px;padding:16px;border:1px solid #e5e7eb;display:flex;align-items:center;gap:12px">
        <div style="width:40px;height:40px;border-radius:9px;background:#ecfdf518;display:flex;align-items:center;justify-content:center"><i class="fas fa-calendar" style="color:#10b981;font-size:18px"></i></div>
        <div><div style="font-size:20px;font-weight:800;color:#10b981">${{ number_format($stats['this_month'],2) }}</div><div style="font-size:12px;color:#6b7280">This Month</div></div>
    </div>
</div>

{{-- Filters --}}
<form method="GET" style="background:#fff;border-radius:10px;padding:14px 18px;margin-bottom:14px;border:1px solid #e5e7eb;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:2;min-width:150px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Search</label>
        <input name="search" value="{{ request('search') }}" placeholder="Order# or email" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
    </div>
    <div style="flex:1;min-width:120px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Status</label>
        <select name="status" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            <option value="">All eligible</option>
            <option value="paid" {{ request('status')==='paid'?'selected':'' }}>Paid (refundable)</option>
            <option value="refunded" {{ request('status')==='refunded'?'selected':'' }}>Refunded</option>
        </select>
    </div>
    <div style="flex:1;min-width:110px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Method</label>
        <select name="method" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            <option value="">All</option>
            <option value="stripe" {{ request('method')==='stripe'?'selected':'' }}>Stripe</option>
            <option value="paypal" {{ request('method')==='paypal'?'selected':'' }}>PayPal</option>
        </select>
    </div>
    <button type="submit" style="padding:8px 18px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Filter</button>
</form>

<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
                <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Order</th>
                <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Customer</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Method</th>
                <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Amount</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Status</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Date</th>
                <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $p)
            <tr style="border-top:1px solid #f3f4f6">
                <td style="padding:10px 16px">
                    @if($p->order)
                    <a href="{{ route('admin.global.orders.show',$p->order) }}" style="font-size:12px;font-weight:600;color:#6366f1;text-decoration:none">{{ $p->order->order_number }}</a>
                    @else<span style="font-size:12px;color:#9ca3af">—</span>@endif
                </td>
                <td style="padding:10px 16px">
                    <div style="font-size:12px;font-weight:600;color:#111">{{ $p->order?->user?->name ?? $p->order?->shipping_name ?? '—' }}</div>
                    <div style="font-size:11px;color:#9ca3af">{{ $p->order?->user?->email }}</div>
                </td>
                <td style="padding:10px 16px;text-align:center">
                    <span style="{{ $p->method==='stripe' ? 'color:#635bff;background:#f5f3ff' : 'color:#003087;background:#eff6ff' }};padding:2px 9px;border-radius:10px;font-size:10px;font-weight:700">{{ strtoupper($p->method) }}</span>
                </td>
                <td style="padding:10px 16px;text-align:right;font-size:13px;font-weight:800;color:#111">${{ number_format($p->amount,2) }}</td>
                <td style="padding:10px 16px;text-align:center">
                    <span style="padding:2px 9px;border-radius:10px;font-size:10px;font-weight:700;{{ $p->status==='paid'?'color:#065f46;background:#d1fae5':($p->status==='refunded'?'color:#6d28d9;background:#ede9fe':'color:#92400e;background:#fef3c7') }}">
                        {{ strtoupper($p->status) }}
                    </span>
                </td>
                <td style="padding:10px 16px;text-align:center;font-size:11px;color:#9ca3af">{{ $p->created_at->format('M d, Y') }}</td>
                <td style="padding:10px 16px;text-align:right">
                    @if($p->status === 'paid')
                    <button onclick="document.getElementById('refund-{{ $p->id }}').classList.toggle('hidden')" style="padding:5px 12px;background:#fee2e2;color:#dc2626;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer">
                        Refund
                    </button>
                    <div id="refund-{{ $p->id }}" class="hidden" style="margin-top:6px;padding:10px;background:#fef2f2;border-radius:8px;border:1px solid #fca5a5">
                        <form method="POST" action="{{ route('admin.global.refunds.process', $p) }}">
                            @csrf
                            <div style="display:flex;gap:6px;align-items:center">
                                <input type="number" name="amount" step="0.01" min="0.01" max="{{ $p->amount }}" placeholder="${{ $p->amount }}" style="width:90px;padding:5px 8px;border:1px solid #fca5a5;border-radius:6px;font-size:11px">
                                <button type="submit" style="padding:5px 10px;background:#dc2626;color:#fff;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer">Confirm</button>
                            </div>
                            <div style="font-size:10px;color:#9ca3af;margin-top:3px">Leave blank = full refund</div>
                        </form>
                    </div>
                    @else
                    <span style="font-size:11px;color:#9ca3af">—</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="padding:40px;text-align:center;color:#9ca3af">No payments found</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
    @if($payments->hasPages())
    <div style="padding:14px 18px;border-top:1px solid #f3f4f6">{{ $payments->links() }}</div>
    @endif
</div>
<style>.hidden{display:none!important}</style>
@endsection
