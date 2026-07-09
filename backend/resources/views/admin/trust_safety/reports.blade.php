@extends('admin.layouts.app')
@section('title','Reports')
@section('content')
<div class="page-header" style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;">
    <div>
        <h1 style="font-size:20px;font-weight:900;color:#111827;">User Reports</h1>
        <p style="font-size:13px;color:#6b7280;">All reported content</p>
    </div>
    <a href="{{ route('admin.trust-safety.dashboard') }}" style="font-size:12px;color:#FF8A00;text-decoration:none;">← Dashboard</a>
</div>

@if(session('success'))
<div style="background:#dcfce7;color:#16a34a;padding:10px 16px;border-radius:10px;margin-bottom:16px;font-weight:700;">✓ {{ session('success') }}</div>
@endif

{{-- Filter bar --}}
<div style="background:#fff;border:1px solid #eef0f6;border-radius:12px;padding:14px 18px;margin-bottom:16px;display:flex;gap:12px;">
    <form method="GET" style="display:flex;gap:10px;flex:1;">
        <select name="status" style="border:1.5px solid #eef0f6;border-radius:8px;padding:6px 10px;font-size:12px;">
            <option value="">All Status</option>
            <option value="pending" {{ request('status')==='pending'?'selected':'' }}>Pending</option>
            <option value="resolved" {{ request('status')==='resolved'?'selected':'' }}>Resolved</option>
            <option value="dismissed" {{ request('status')==='dismissed'?'selected':'' }}>Dismissed</option>
        </select>
        <select name="reason" style="border:1.5px solid #eef0f6;border-radius:8px;padding:6px 10px;font-size:12px;">
            <option value="">All Reasons</option>
            @foreach(['spam','violence','fake_news','scam','harassment','pornography','child_abuse','copyright','impersonation','drugs','terrorism','self_harm','other','auto_moderation'] as $r)
            <option value="{{ $r }}" {{ request('reason')===$r?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$r)) }}</option>
            @endforeach
        </select>
        <button style="background:#FF8A00;color:#fff;border:none;padding:6px 16px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">Filter</button>
    </form>
</div>

<div style="background:#fff;border:1px solid #eef0f6;border-radius:14px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;">
        <thead style="background:#f9fafb;">
            <tr style="font-size:11px;color:#9ca3af;font-weight:700;text-align:left;">
                <th style="padding:12px 16px;">#</th>
                <th style="padding:12px 16px;">Reporter</th>
                <th style="padding:12px 16px;">Reason</th>
                <th style="padding:12px 16px;">Content</th>
                <th style="padding:12px 16px;">Status</th>
                <th style="padding:12px 16px;">Date</th>
                <th style="padding:12px 16px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reports as $r)
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:12px 16px;font-size:12px;color:#9ca3af;">#{{ $r->id }}</td>
                <td style="padding:12px 16px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#FF8A00,#ff5f00);display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;font-weight:800;flex-shrink:0;">
                            {{ strtoupper(substr($r->reporter_name,0,1)) }}
                        </div>
                        <span style="font-size:13px;font-weight:600;color:#111827;">{{ $r->reporter_name }}</span>
                    </div>
                </td>
                <td style="padding:12px 16px;">
                    <span style="background:#eff6ff;color:#3b82f6;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">
                        {{ ucfirst(str_replace('_',' ',$r->reason)) }}
                    </span>
                </td>
                <td style="padding:12px 16px;font-size:12px;color:#6b7280;">
                    {{ class_basename($r->reportable_type) }} #{{ $r->reportable_id }}
                    @if($r->description)
                    <div style="font-size:11px;color:#9ca3af;margin-top:2px;">{{ Str::limit($r->description, 50) }}</div>
                    @endif
                </td>
                <td style="padding:12px 16px;">
                    @php $sc = $r->status ?? 'pending'; @endphp
                    <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;
                        background:{{ $sc==='pending'?'#fef3c7':($sc==='resolved'?'#dcfce7':'#f3f4f6') }};
                        color:{{ $sc==='pending'?'#d97706':($sc==='resolved'?'#16a34a':'#6b7280') }};">
                        {{ ucfirst($sc) }}
                    </span>
                </td>
                <td style="padding:12px 16px;font-size:12px;color:#9ca3af;">
                    {{ \Carbon\Carbon::parse($r->created_at)->format('M j, Y') }}
                </td>
                <td style="padding:12px 16px;">
                    @if($sc === 'pending')
                    <div style="display:flex;gap:6px;">
                        <form method="POST" action="{{ route('admin.trust-safety.reports.resolve', $r->id) }}">
                            @csrf
                            <input type="hidden" name="action" value="resolved">
                            <button style="background:#dcfce7;color:#16a34a;border:none;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;">Resolve</button>
                        </form>
                        <form method="POST" action="{{ route('admin.trust-safety.reports.resolve', $r->id) }}">
                            @csrf
                            <input type="hidden" name="action" value="dismissed">
                            <button style="background:#f3f4f6;color:#6b7280;border:none;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;">Dismiss</button>
                        </form>
                    </div>
                    @else
                    <span style="color:#9ca3af;font-size:12px;">Done</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af;">No reports found</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div style="margin-top:16px;">{{ $reports->links() }}</div>
@endsection
