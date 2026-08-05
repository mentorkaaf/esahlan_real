@extends('admin.layouts.app')
@section('title', 'Global Security')

@section('content')
<div class="page-header">
    <div><h1 class="page-title">🔒 Security Center</h1><p class="page-subtitle">Fraud detection, IP blocking, and user security</p></div>
</div>

@if(session('success'))<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>@endif

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px">
    @foreach([['Blocked IPs',$stats['blocked_ips'],'#ef4444','fa-ban'],['Banned Users',$stats['banned_users'],'#dc2626','fa-user-slash'],['High Alerts (7d)',$stats['high_severity'],'#f59e0b','fa-triangle-exclamation'],['Events Today',$stats['events_today'],'#6366f1','fa-clock']] as [$l,$v,$c,$i])
    <div style="background:#fff;border-radius:10px;padding:16px;border:1px solid #e5e7eb;display:flex;align-items:center;gap:12px">
        <div style="width:40px;height:40px;border-radius:9px;background:{{ $c }}18;display:flex;align-items:center;justify-content:center"><i class="fas {{ $i }}" style="color:{{ $c }};font-size:16px"></i></div>
        <div><div style="font-size:22px;font-weight:800;color:#111">{{ $v }}</div><div style="font-size:11px;color:#6b7280">{{ $l }}</div></div>
    </div>
    @endforeach
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start">

<div style="display:flex;flex-direction:column;gap:16px">

{{-- Security Logs --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6"><h3 style="font-size:14px;font-weight:700;color:#111">🕵️ Security Events (Last 50)</h3></div>
    @php $sevColors=['low'=>['#6b7280','#f3f4f6'],'medium'=>['#f59e0b','#fffbeb'],'high'=>['#ef4444','#fef2f2'],'critical'=>['#dc2626','#fee2e2']]; @endphp
    @forelse($logs as $log)
    @php $sc=$sevColors[$log->severity]??['#9ca3af','#f9fafb']; @endphp
    <div style="padding:11px 18px;border-top:1px solid #f3f4f6;display:flex;align-items:flex-start;gap:12px">
        <span style="padding:2px 8px;border-radius:10px;font-size:9px;font-weight:800;color:{{ $sc[0] }};background:{{ $sc[1] }};flex-shrink:0;margin-top:1px;text-transform:uppercase">{{ $log->severity }}</span>
        <div style="flex:1">
            <div style="font-size:12px;font-weight:700;color:#111">{{ str_replace('_',' ',ucfirst($log->event)) }}</div>
            @if($log->user_name)<div style="font-size:11px;color:#6b7280">{{ $log->user_name }} · {{ $log->user_email }}</div>@endif
            <div style="font-size:10px;color:#9ca3af">{{ $log->ip_address }}{{ $log->country ? ' · '.$log->country : '' }}</div>
        </div>
        <div style="font-size:10px;color:#9ca3af;flex-shrink:0">{{ \Carbon\Carbon::parse($log->created_at)->diffForHumans() }}</div>
    </div>
    @empty
    <div style="padding:32px;text-align:center;color:#9ca3af"><i class="fas fa-shield-check" style="font-size:32px;margin-bottom:10px"></i><p>No security events</p></div>
    @endforelse
</div>

{{-- Banned Users --}}
@if($suspiciousUsers->count())
<div style="background:#fff;border-radius:12px;border:1px solid #fca5a5;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #fca5a5;background:#fef2f2"><h3 style="font-size:14px;font-weight:700;color:#dc2626">🚫 Banned Users</h3></div>
    @foreach($suspiciousUsers as $u)
    <div style="padding:12px 18px;border-top:1px solid #fee2e2;display:flex;align-items:center;gap:12px">
        <div style="flex:1">
            <div style="font-size:12px;font-weight:700;color:#111">{{ $u->name }}</div>
            <div style="font-size:11px;color:#6b7280">{{ $u->email }}</div>
            @if($u->ban_reason)<div style="font-size:10px;color:#9ca3af">{{ $u->ban_reason }}</div>@endif
        </div>
        <form method="POST" action="{{ route('admin.global.security.unblock-user', $u) }}">
            @csrf
            <button type="submit" style="padding:5px 12px;background:#ecfdf5;color:#065f46;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer">Unban</button>
        </form>
    </div>
    @endforeach
</div>
@endif
</div>

<div style="display:flex;flex-direction:column;gap:16px">
{{-- Block IP --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:20px">
    <h3 style="font-size:13px;font-weight:700;color:#111;margin-bottom:14px">🛡️ Block IP Address</h3>
    <form method="POST" action="{{ route('admin.global.security.block-ip') }}">
        @csrf
        <div style="display:flex;flex-direction:column;gap:10px">
            <div><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">IP Address</label><input name="ip_address" required placeholder="192.168.1.1" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;font-family:monospace"></div>
            <div><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Reason</label><input name="reason" placeholder="Fraud attempt, spam..." style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"></div>
            <div><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Block Until (optional)</label><input name="blocked_until" type="datetime-local" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"></div>
            <button type="submit" style="padding:9px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">🚫 Block IP</button>
        </div>
    </form>
</div>

{{-- Blocked IPs --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;max-height:300px;overflow-y:auto">
    <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;position:sticky;top:0;background:#fff"><h3 style="font-size:13px;font-weight:700;color:#111">Blocked IPs ({{ $blockedIps->count() }})</h3></div>
    @forelse($blockedIps as $ip)
    <div style="padding:10px 18px;border-top:1px solid #f3f4f6;display:flex;align-items:center;gap:10px">
        <div style="flex:1">
            <div style="font-size:12px;font-weight:700;color:#dc2626;font-family:monospace">{{ $ip->ip_address }}</div>
            @if($ip->reason)<div style="font-size:11px;color:#6b7280">{{ $ip->reason }}</div>@endif
            @if($ip->blocked_until)<div style="font-size:10px;color:#9ca3af">Until: {{ \Carbon\Carbon::parse($ip->blocked_until)->format('M d, Y') }}</div>@endif
        </div>
    </div>
    @empty
    <div style="padding:20px;text-align:center;font-size:12px;color:#9ca3af">No IPs blocked</div>
    @endforelse
</div>

{{-- Security Tips --}}
<div style="background:#f5f3ff;border-radius:12px;border:1px solid #ddd6fe;padding:16px">
    <h3 style="font-size:12px;font-weight:700;color:#5b21b6;margin-bottom:10px">🔐 Security Checklist</h3>
    @foreach(['Stripe webhooks use signature verification','PayPal uses OAuth2 tokens (no passwords)','All payments logged with IP + country','Rate limiting on checkout API (todo)','CSP headers on admin panel (check Nginx)'] as $i => $tip)
    <div style="font-size:11px;color:#374151;margin-bottom:5px;display:flex;align-items:flex-start;gap:6px">
        <span style="color:#7c3aed;flex-shrink:0">{{ $i < 3 ? '✅' : '⚠️' }}</span> {{ $tip }}
    </div>
    @endforeach
</div>
</div>

</div>
@endsection
