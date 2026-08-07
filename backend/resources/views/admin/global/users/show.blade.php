@extends('admin.layouts.app')
@section('title', 'User: '.$user->name)

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">👤 {{ $user->name }}</h1>
        <p class="page-subtitle">Global User Profile</p>
    </div>
    <a href="{{ route('admin.global.users.index') }}" style="color:#6b7280;text-decoration:none;font-size:13px">← Users</a>
</div>

@if(session('success'))
<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">❌ {{ session('error') }}</div>
@endif

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;align-items:start">

{{-- Left: Profile Card --}}
<div style="display:flex;flex-direction:column;gap:16px">
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px;text-align:center">
        @if($user->avatar)
        <img src="{{ $user->avatar }}" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin-bottom:12px">
        @else
        <div style="width:80px;height:80px;border-radius:50%;background:#e0e7ff;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:#6366f1;margin:0 auto 12px">{{ strtoupper(substr($user->name,0,1)) }}</div>
        @endif
        <div style="font-size:18px;font-weight:700;color:#111827;margin-bottom:4px">{{ $user->name }}</div>
        <div style="font-size:13px;color:#6b7280;margin-bottom:12px">{{ $user->email }}</div>
        @if($user->is_banned)
        <span style="padding:4px 14px;border-radius:20px;font-size:11px;font-weight:800;background:#fef2f2;color:#dc2626">🚫 BANNED</span>
        @elseif($user->is_active)
        <span style="padding:4px 14px;border-radius:20px;font-size:11px;font-weight:800;background:#ecfdf5;color:#059669">✅ ACTIVE</span>
        @else
        <span style="padding:4px 14px;border-radius:20px;font-size:11px;font-weight:800;background:#f3f4f6;color:#6b7280">INACTIVE</span>
        @endif
    </div>

    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:20px">
        <div style="font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:12px">Details</div>
        @foreach([
            ['Phone',$user->phone ?? '—'],
            ['Country',$user->country_code ?? '—'],
            ['Joined',$user->created_at->format('M d, Y')],
            ['Last Login',$user->last_login_at ? \Carbon\Carbon::parse($user->last_login_at)->format('M d, Y g:i A') : '—'],
            ['Orders',$user->orders_count],
            ['Total Spent','$'.number_format($totalSpent,2)],
            ['FCM Token',$user->fcm_token ? '✅ Yes' : '—'],
        ] as [$label,$val])
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f3f4f6;font-size:13px">
            <span style="color:#6b7280;font-weight:600">{{ $label }}</span>
            <span style="color:#111827">{{ $val }}</span>
        </div>
        @endforeach
        @if($user->is_banned)
        <div style="margin-top:10px;padding:10px;background:#fef2f2;border-radius:8px;font-size:12px;color:#dc2626">
            <strong>Ban Reason:</strong> {{ $user->ban_reason }}<br>
            <strong>Banned At:</strong> {{ \Carbon\Carbon::parse($user->banned_at)->format('M d, Y g:i A') }}
        </div>
        @endif
    </div>

    {{-- Actions --}}
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:20px;display:flex;flex-direction:column;gap:8px">
        <div style="font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:4px">Actions</div>
        @if($user->is_banned)
        <form method="POST" action="{{ route('admin.global.users.unban', $user) }}">@csrf
            <button type="submit" style="width:100%;padding:9px;background:#ecfdf5;color:#059669;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">✅ Unban User</button>
        </form>
        @else
        <button onclick="document.getElementById('ban-modal').style.display='flex'" style="width:100%;padding:9px;background:#fef2f2;color:#dc2626;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">🚫 Ban User</button>
        @endif
        <button onclick="document.getElementById('notify-modal').style.display='flex'" style="width:100%;padding:9px;background:#eef2ff;color:#6366f1;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">🔔 Send Notification</button>
        <form method="POST" action="{{ route('admin.global.users.destroy', $user) }}" onsubmit="return confirm('Delete this user permanently?')">
            @csrf @method('DELETE')
            <button type="submit" style="width:100%;padding:9px;background:#f3f4f6;color:#374151;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">🗑 Delete User</button>
        </form>
    </div>
</div>

{{-- Right: Orders --}}
<div>
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6">
            <div style="font-size:15px;font-weight:700;color:#111827">Recent Orders</div>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
                <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
                    <th style="padding:10px 16px;text-align:left;font-weight:700;color:#374151">Order #</th>
                    <th style="padding:10px 16px;text-align:right;font-weight:700;color:#374151">Total</th>
                    <th style="padding:10px 16px;text-align:center;font-weight:700;color:#374151">Status</th>
                    <th style="padding:10px 16px;text-align:left;font-weight:700;color:#374151">Date</th>
                </tr>
            </thead>
            <tbody>
            @forelse($orders as $order)
            @php
            $statusColors = ['pending'=>['#f59e0b','#fffbeb'],'paid'=>['#10b981','#ecfdf5'],'processing'=>['#6366f1','#eef2ff'],'shipped'=>['#3b82f6','#eff6ff'],'delivered'=>['#10b981','#ecfdf5'],'cancelled'=>['#ef4444','#fef2f2'],'refunded'=>['#8b5cf6','#f5f3ff'],'on_hold'=>['#f97316','#fff7ed']];
            $sc = $statusColors[$order->status] ?? ['#9ca3af','#f9fafb'];
            @endphp
            <tr style="border-bottom:1px solid #f3f4f6">
                <td style="padding:11px 16px;font-weight:600;color:#111827">{{ $order->order_number }}</td>
                <td style="padding:11px 16px;text-align:right;color:#374151">${{ number_format($order->total,2) }}</td>
                <td style="padding:11px 16px;text-align:center"><span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;color:{{ $sc[0] }};background:{{ $sc[1] }}">{{ strtoupper($order->status) }}</span></td>
                <td style="padding:11px 16px;color:#6b7280">{{ $order->created_at->format('M d, Y') }}</td>
            </tr>
            @empty
            <tr><td colspan="4" style="padding:30px;text-align:center;color:#9ca3af">No orders yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>

{{-- Ban Modal --}}
<div id="ban-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:16px;padding:28px;width:420px;max-width:95vw">
        <h3 style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827">Ban User</h3>
        <form method="POST" action="{{ route('admin.global.users.ban', $user) }}">
            @csrf
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px">Reason *</label>
            <textarea name="reason" rows="3" required style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;resize:vertical;box-sizing:border-box" placeholder="Enter ban reason…"></textarea>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
                <button type="button" onclick="document.getElementById('ban-modal').style.display='none'" style="padding:8px 18px;background:#f3f4f6;color:#374151;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Cancel</button>
                <button type="submit" style="padding:8px 18px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Ban User</button>
            </div>
        </form>
    </div>
</div>

{{-- Notify Modal --}}
<div id="notify-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:16px;padding:28px;width:420px;max-width:95vw">
        <h3 style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827">Send Notification</h3>
        <form method="POST" action="{{ route('admin.global.users.notify', $user) }}">
            @csrf
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px">Title *</label>
            <input name="title" required style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box;margin-bottom:12px" placeholder="Notification title…">
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px">Body *</label>
            <textarea name="body" rows="3" required style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;resize:vertical;box-sizing:border-box" placeholder="Notification body…"></textarea>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
                <button type="button" onclick="document.getElementById('notify-modal').style.display='none'" style="padding:8px 18px;background:#f3f4f6;color:#374151;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Cancel</button>
                <button type="submit" style="padding:8px 18px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Send</button>
            </div>
        </form>
    </div>
</div>
@endsection
