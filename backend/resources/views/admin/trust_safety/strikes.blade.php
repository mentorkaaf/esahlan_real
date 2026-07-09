@extends('admin.layouts.app')
@section('title','Strikes')
@section('content')
<div class="page-header" style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;">
    <div>
        <h1 style="font-size:20px;font-weight:900;color:#111827;">Strike History</h1>
        <p style="font-size:13px;color:#6b7280;">User violation strikes</p>
    </div>
    <a href="{{ route('admin.trust-safety.dashboard') }}" style="font-size:12px;color:#FF8A00;text-decoration:none;">← Dashboard</a>
</div>
<div style="background:#fff;border:1px solid #eef0f6;border-radius:14px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;">
        <thead style="background:#f9fafb;">
            <tr style="font-size:11px;color:#9ca3af;font-weight:700;text-align:left;">
                <th style="padding:12px 16px;">User</th>
                <th style="padding:12px 16px;">Violation</th>
                <th style="padding:12px 16px;">Severity</th>
                <th style="padding:12px 16px;">Points</th>
                <th style="padding:12px 16px;">Reason</th>
                <th style="padding:12px 16px;">Date</th>
                <th style="padding:12px 16px;">Expires</th>
            </tr>
        </thead>
        <tbody>
            @forelse($strikes as $s)
            @php
            $sevColor = ['low'=>['#dcfce7','#16a34a'],'medium'=>['#fef3c7','#d97706'],'high'=>['#fee2e2','#dc2626'],'critical'=>['#7f1d1d','#fff']][$s->severity] ?? ['#f3f4f6','#6b7280'];
            @endphp
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:12px 16px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#FF8A00,#ff5f00);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:800;flex-shrink:0;">
                            {{ strtoupper(substr($s->name,0,1)) }}
                        </div>
                        <div>
                            <div style="font-size:13px;font-weight:700;color:#111827;">{{ $s->name }}</div>
                            <div style="font-size:11px;color:#9ca3af;">{{ $s->email }}</div>
                        </div>
                    </div>
                </td>
                <td style="padding:12px 16px;font-size:12px;font-weight:600;color:#374151;">{{ ucfirst(str_replace('_',' ',$s->violation_type)) }}</td>
                <td style="padding:12px 16px;">
                    <span style="background:{{ $sevColor[0] }};color:{{ $sevColor[1] }};padding:3px 10px;border-radius:20px;font-size:11px;font-weight:800;">
                        {{ ucfirst($s->severity) }}
                    </span>
                </td>
                <td style="padding:12px 16px;font-size:14px;font-weight:800;color:#dc2626;">+{{ $s->points }}</td>
                <td style="padding:12px 16px;font-size:12px;color:#6b7280;">{{ Str::limit($s->reason, 50) }}</td>
                <td style="padding:12px 16px;font-size:12px;color:#9ca3af;">{{ \Carbon\Carbon::parse($s->created_at)->format('M j, Y') }}</td>
                <td style="padding:12px 16px;font-size:12px;color:#9ca3af;">
                    {{ $s->expires_at ? \Carbon\Carbon::parse($s->expires_at)->format('M j, Y') : 'Never' }}
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af;">No strikes recorded</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div style="margin-top:16px;">{{ $strikes->links() }}</div>
@endsection
