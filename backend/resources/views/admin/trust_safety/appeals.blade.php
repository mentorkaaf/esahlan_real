@extends('admin.layouts.app')
@section('title','Appeals')
@section('content')
<div class="page-header" style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;">
    <div>
        <h1 style="font-size:20px;font-weight:900;color:#111827;">Appeals</h1>
        <p style="font-size:13px;color:#6b7280;">User appeals for moderation decisions</p>
    </div>
    <a href="{{ route('admin.trust-safety.dashboard') }}" style="font-size:12px;color:#FF8A00;text-decoration:none;">← Dashboard</a>
</div>

@if(session('success'))
<div style="background:#dcfce7;color:#16a34a;padding:10px 16px;border-radius:10px;margin-bottom:16px;font-weight:700;">✓ {{ session('success') }}</div>
@endif

@forelse($appeals as $a)
<div style="background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:18px 20px;margin-bottom:12px;">
    <div style="display:flex;gap:14px;align-items:flex-start;">
        <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#FF8A00,#ff5f00);display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;font-weight:800;flex-shrink:0;">
            {{ strtoupper(substr($a->name,0,1)) }}
        </div>
        <div style="flex:1;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                <span style="font-size:14px;font-weight:800;color:#111827;">{{ $a->name }}</span>
                <span style="background:#eff6ff;color:#3b82f6;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;">{{ ucfirst($a->action_type) }}</span>
                @php $sc=$a->status; @endphp
                <span style="padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;
                    background:{{ $sc==='pending'?'#fef3c7':($sc==='approved'?'#dcfce7':($sc==='rejected'?'#fee2e2':'#eff6ff')) }};
                    color:{{ $sc==='pending'?'#d97706':($sc==='approved'?'#16a34a':($sc==='rejected'?'#dc2626':'#3b82f6')) }};">
                    {{ ucfirst($sc) }}
                </span>
                <span style="font-size:11px;color:#9ca3af;margin-left:auto;">{{ \Carbon\Carbon::parse($a->created_at)->diffForHumans() }}</span>
            </div>
            <p style="font-size:13px;color:#374151;line-height:1.5;margin-bottom:8px;">{{ $a->reason }}</p>
            @if($a->evidence)
            <div style="background:#f9fafb;border-radius:8px;padding:10px 12px;font-size:12px;color:#6b7280;margin-bottom:8px;">
                <strong>Evidence:</strong> {{ $a->evidence }}
            </div>
            @endif
            @if($a->moderator_note)
            <div style="background:#fef3c7;border-radius:8px;padding:10px 12px;font-size:12px;color:#92400e;">
                <strong>Moderator note:</strong> {{ $a->moderator_note }}
            </div>
            @endif
        </div>
        @if($sc === 'pending')
        <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
            <form method="POST" action="{{ route('admin.trust-safety.appeals.resolve', $a->id) }}">
                @csrf
                <input type="hidden" name="decision" value="approved">
                <button style="background:#dcfce7;color:#16a34a;border:none;padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;width:100%;">
                    <i class="fas fa-check" style="margin-right:4px;"></i>Approve
                </button>
            </form>
            <form method="POST" action="{{ route('admin.trust-safety.appeals.resolve', $a->id) }}">
                @csrf
                <input type="hidden" name="decision" value="rejected">
                <button style="background:#fee2e2;color:#dc2626;border:none;padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;width:100%;">
                    <i class="fas fa-times" style="margin-right:4px;"></i>Reject
                </button>
            </form>
        </div>
        @endif
    </div>
</div>
@empty
<div style="text-align:center;padding:60px;background:#fff;border-radius:14px;border:1px solid #eef0f6;">
    <i class="fas fa-scale-balanced" style="font-size:40px;color:#a855f7;margin-bottom:12px;display:block;"></i>
    <div style="font-size:16px;font-weight:800;color:#111827;margin-bottom:6px;">No appeals pending</div>
    <div style="font-size:13px;color:#6b7280;">All appeals have been reviewed.</div>
</div>
@endforelse
<div style="margin-top:16px;">{{ $appeals->links() }}</div>
@endsection
