@extends('admin.layouts.app')
@section('title', 'Exchange — User Accounts')
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between">
    <div>
        <h1 class="page-title">👛 Exchange User Accounts</h1>
        <p class="page-subtitle">Users who have saved wallet accounts for fast exchange</p>
    </div>
    <a href="{{ route('admin.exchange.index') }}" style="padding:9px 16px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:9px;font-weight:700;font-size:13px;color:#374151;text-decoration:none">← Back to Orders</a>
</div>

{{-- Search --}}
<div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;padding:14px 18px;margin-bottom:16px">
    <form method="GET" style="display:flex;gap:10px;align-items:flex-end">
        <div style="flex:1">
            <label style="font-size:11px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or phone..." style="width:100%;padding:7px 10px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
        </div>
        <button type="submit" style="padding:8px 18px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer">Search</button>
        <a href="{{ route('admin.exchange.users') }}" style="padding:8px 16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;font-weight:600;font-size:13px;color:#374151;text-decoration:none">Clear</a>
    </form>
</div>

<div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6">
        <h3 style="font-size:13px;font-weight:700;color:#111;margin:0">Users with Saved Accounts ({{ $users->total() }})</h3>
    </div>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="background:#f9fafb">
                <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">User</th>
                <th style="padding:10px 16px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Wallets Saved</th>
                <th style="padding:10px 16px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Total Orders</th>
                <th style="padding:10px 16px;text-align:right;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Total Volume</th>
                <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Joined</th>
                <th style="padding:10px 16px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $u)
            @php $isSuspect = ($u->total_volume >= 2000 || $u->order_count >= 50); @endphp
            <tr style="border-top:1px solid #f3f4f6{{ $isSuspect ? ';background:#fff7ed' : '' }}">
                <td style="padding:10px 16px">
                    <div style="font-size:13px;font-weight:700;color:#111">{{ $u->name }} @if($isSuspect)<span style="margin-left:6px;font-size:10px;background:#fef3c7;color:#92400e;padding:2px 6px;border-radius:4px;font-weight:700">⚠️ HIGH VOLUME</span>@endif</div>
                    <div style="font-size:11px;color:#9ca3af">{{ $u->phone }}</div>
                </td>
                <td style="padding:10px 16px;text-align:center">
                    <span style="font-size:14px;font-weight:800;color:#6366f1">{{ $u->account_count }}</span>
                </td>
                <td style="padding:10px 16px;text-align:center;font-size:13px;font-weight:700;color:#374151">{{ number_format($u->order_count) }}</td>
                <td style="padding:10px 16px;text-align:right;font-size:13px;font-weight:800;color:#111">${{ number_format($u->total_volume ?? 0, 2) }}</td>
                <td style="padding:10px 16px;font-size:11px;color:#9ca3af">{{ \Carbon\Carbon::parse($u->joined)->format('d M Y') }}</td>
                <td style="padding:10px 16px;text-align:center">
                    <a href="{{ route('admin.exchange.user-accounts', $u->id) }}" style="padding:5px 14px;background:#6366f1;color:#fff;border-radius:7px;font-size:12px;font-weight:700;text-decoration:none">View Accounts</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:32px;text-align:center;color:#9ca3af;font-size:13px">No users with saved accounts yet</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
    @if($users->hasPages())
    <div style="padding:14px 18px;border-top:1px solid #f3f4f6">{{ $users->links() }}</div>
    @endif
</div>
@endsection
