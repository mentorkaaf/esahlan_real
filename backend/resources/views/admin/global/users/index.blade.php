@extends('admin.layouts.app')
@section('title', 'Global Users')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">👥 Global Users</h1>
        <p class="page-subtitle">Manage global eCommerce customers</p>
    </div>
</div>

@if(session('success'))
<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">❌ {{ session('error') }}</div>
@endif

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px">
    @foreach([
        ['Total Users','👤',$stats['total'],'#6366f1','#eef2ff'],
        ['Active','✅',$stats['active'],'#10b981','#ecfdf5'],
        ['Banned','🚫',$stats['banned'],'#ef4444','#fef2f2'],
        ['With FCM','🔔',$stats['with_fcm'],'#f59e0b','#fffbeb'],
    ] as [$label,$icon,$val,$color,$bg])
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:16px 20px">
        <div style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">{{ $icon }} {{ $label }}</div>
        <div style="font-size:26px;font-weight:800;color:{{ $color }}">{{ number_format($val) }}</div>
    </div>
    @endforeach
</div>

{{-- Filters --}}
<form method="GET" style="background:#fff;border-radius:12px;padding:16px 20px;margin-bottom:16px;border:1px solid #e5e7eb;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:2;min-width:160px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Search</label>
        <input name="search" value="{{ request('search') }}" placeholder="Name, email or phone…" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
    </div>
    <div style="flex:1;min-width:130px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Status</label>
        <select name="status" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            <option value="">All</option>
            <option value="active" {{ request('status')==='active'?'selected':'' }}>Active</option>
            <option value="inactive" {{ request('status')==='inactive'?'selected':'' }}>Inactive</option>
            <option value="banned" {{ request('status')==='banned'?'selected':'' }}>Banned</option>
        </select>
    </div>
    <div style="flex:1;min-width:130px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Country</label>
        <input name="country" value="{{ request('country') }}" placeholder="e.g. US" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
    </div>
    <button type="submit" style="padding:8px 20px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Filter</button>
    @if(request()->hasAny(['search','status','country']))
    <a href="{{ route('admin.global.users.index') }}" style="padding:8px 16px;background:#f3f4f6;color:#374151;border-radius:8px;font-size:13px;text-decoration:none">Clear</a>
    @endif
</form>

{{-- Table --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
            <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
                <th style="padding:11px 16px;text-align:left;font-weight:700;color:#374151">User</th>
                <th style="padding:11px 16px;text-align:left;font-weight:700;color:#374151">Country</th>
                <th style="padding:11px 16px;text-align:center;font-weight:700;color:#374151">Orders</th>
                <th style="padding:11px 16px;text-align:left;font-weight:700;color:#374151">Joined</th>
                <th style="padding:11px 16px;text-align:center;font-weight:700;color:#374151">Status</th>
                <th style="padding:11px 16px;text-align:right;font-weight:700;color:#374151">Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($users as $user)
        <tr style="border-bottom:1px solid #f3f4f6">
            <td style="padding:12px 16px">
                <div style="display:flex;align-items:center;gap:10px">
                    @if($user->avatar)
                    <img src="{{ $user->avatar }}" style="width:36px;height:36px;border-radius:50%;object-fit:cover">
                    @else
                    <div style="width:36px;height:36px;border-radius:50%;background:#e0e7ff;display:flex;align-items:center;justify-content:center;font-weight:700;color:#6366f1;font-size:14px">{{ strtoupper(substr($user->name,0,1)) }}</div>
                    @endif
                    <div>
                        <div style="font-weight:600;color:#111827">{{ $user->name }}</div>
                        <div style="color:#6b7280;font-size:12px">{{ $user->email }}</div>
                    </div>
                </div>
            </td>
            <td style="padding:12px 16px;color:#374151">{{ $user->country_code ?? '—' }}</td>
            <td style="padding:12px 16px;text-align:center;color:#374151">{{ $user->orders_count }}</td>
            <td style="padding:12px 16px;color:#6b7280">{{ $user->created_at->format('M d, Y') }}</td>
            <td style="padding:12px 16px;text-align:center">
                @if($user->is_banned)
                <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#fef2f2;color:#dc2626">BANNED</span>
                @elseif($user->is_active)
                <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#ecfdf5;color:#059669">ACTIVE</span>
                @else
                <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#f3f4f6;color:#6b7280">INACTIVE</span>
                @endif
            </td>
            <td style="padding:12px 16px;text-align:right">
                <div style="display:flex;gap:6px;justify-content:flex-end">
                    <a href="{{ route('admin.global.users.show', $user) }}" style="padding:5px 12px;background:#eef2ff;color:#6366f1;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none">View</a>
                    @if($user->is_banned)
                    <form method="POST" action="{{ route('admin.global.users.unban', $user) }}" style="display:inline">@csrf
                        <button type="submit" style="padding:5px 12px;background:#ecfdf5;color:#059669;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer">Unban</button>
                    </form>
                    @else
                    <button onclick="openBanModal({{ $user->id }})" style="padding:5px 12px;background:#fef2f2;color:#dc2626;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer">Ban</button>
                    @endif
                </div>
            </td>
        </tr>
        @empty
        <tr><td colspan="6" style="padding:40px;text-align:center;color:#9ca3af">No users found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{-- Pagination --}}
<div style="margin-top:16px">{{ $users->links() }}</div>

{{-- Ban Modal --}}
<div id="ban-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:16px;padding:28px;width:420px;max-width:95vw">
        <h3 style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827">Ban User</h3>
        <form id="ban-form" method="POST">
            @csrf
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px">Reason *</label>
            <textarea name="reason" rows="3" required style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;resize:vertical;box-sizing:border-box" placeholder="Enter ban reason…"></textarea>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
                <button type="button" onclick="closeBanModal()" style="padding:8px 18px;background:#f3f4f6;color:#374151;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Cancel</button>
                <button type="submit" style="padding:8px 18px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Ban User</button>
            </div>
        </form>
    </div>
</div>

<script>
function openBanModal(id) {
    document.getElementById('ban-form').action = '/admin/global/users/' + id + '/ban';
    document.getElementById('ban-modal').style.display = 'flex';
}
function closeBanModal() {
    document.getElementById('ban-modal').style.display = 'none';
}
</script>
@endsection
