@extends('admin.layouts.app')
@section('title', 'Global Push Notifications')

@section('content')
<div class="page-header">
    <div><h1 class="page-title">🔔 Global Push Notifications</h1><p class="page-subtitle">Send push notifications to Global Store users</p></div>
</div>

@if(session('success'))<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>@endif
@if(session('error'))<div style="background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">❌ {{ session('error') }}</div>@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

{{-- Send Form --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px">
    <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:6px">📤 Send Notification</h3>
    <div style="display:flex;gap:8px;margin-bottom:16px;padding:10px;background:#f9fafb;border-radius:8px">
        <div style="text-align:center;flex:1"><div style="font-size:18px;font-weight:800;color:#6366f1">{{ number_format($stats['total_users']) }}</div><div style="font-size:10px;color:#9ca3af">Total Users</div></div>
        <div style="width:1px;background:#e5e7eb"></div>
        <div style="text-align:center;flex:1"><div style="font-size:18px;font-weight:800;color:#10b981">{{ number_format($stats['with_token']) }}</div><div style="font-size:10px;color:#9ca3af">With Token</div></div>
        <div style="width:1px;background:#e5e7eb"></div>
        <div style="text-align:center;flex:1"><div style="font-size:18px;font-weight:800;color:#f59e0b">{{ number_format($stats['sent_today']) }}</div><div style="font-size:10px;color:#9ca3af">Sent Today</div></div>
    </div>

    <form method="POST" action="{{ route('admin.global.push-notifications.send') }}">
        @csrf
        <div style="display:flex;flex-direction:column;gap:14px">
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Title *</label>
                <input name="title" required maxlength="100" placeholder="New Arrival! 🎉" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Message *</label>
                <textarea name="body" required maxlength="500" rows="3" placeholder="Check out our latest products..." style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;resize:vertical"></textarea>
                <div style="font-size:10px;color:#9ca3af;margin-top:3px">Max 500 characters</div>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Image URL (optional)</label>
                <input name="image_url" type="url" placeholder="https://..." style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Click URL (optional)</label>
                <input name="click_url" type="url" placeholder="https://esahlan.com/global/..." style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:8px">Target Audience</label>
                <div style="display:flex;gap:10px">
                    @foreach(['all'=>'All Users','segment'=>'By Country'] as $v => $l)
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;flex:1;{{ request('target')===$v?'border-color:#6366f1;background:#eef2ff':'' }}">
                        <input type="radio" name="target" value="{{ $v }}" {{ $v==='all'?'checked':'' }} style="accent-color:#6366f1">
                        <span style="font-size:13px;font-weight:600">{{ $l }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            <button type="submit" style="padding:12px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer">
                🚀 Send Push Notification
            </button>
        </div>
    </form>
</div>

{{-- Sent History --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6">
        <h3 style="font-size:14px;font-weight:700;color:#111">📋 Sent History</h3>
    </div>
    @forelse($logs as $log)
    <div style="padding:14px 18px;border-top:1px solid #f3f4f6">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px">
            <div style="font-size:13px;font-weight:700;color:#111">{{ $log->title }}</div>
            <span style="font-size:11px;font-weight:700;color:#6366f1;background:#eef2ff;padding:2px 8px;border-radius:10px">{{ number_format($log->sent_count) }} sent</span>
        </div>
        <div style="font-size:12px;color:#6b7280;margin-bottom:4px">{{ Str::limit($log->body, 80) }}</div>
        <div style="font-size:11px;color:#9ca3af">{{ \Carbon\Carbon::parse($log->created_at)->format('M d, Y g:i A') }} · {{ ucfirst($log->target) }}</div>
    </div>
    @empty
    <div style="padding:32px;text-align:center;color:#9ca3af">
        <i class="fas fa-bell-slash" style="font-size:32px;margin-bottom:10px"></i>
        <p>No notifications sent yet</p>
    </div>
    @endforelse
</div>

</div>
@endsection
