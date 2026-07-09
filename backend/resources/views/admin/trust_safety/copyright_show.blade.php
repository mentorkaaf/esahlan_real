@extends('admin.layouts.app')
@section('title','Copyright Claim #' . $claim->id)
@section('content')
<div style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;">
    <div>
        <h1 style="font-size:20px;font-weight:900;color:#111827;">Copyright Claim #{{ $claim->id }}</h1>
        <p style="font-size:13px;color:#6b7280;">Filed {{ \Carbon\Carbon::parse($claim->created_at)->diffForHumans() }}</p>
    </div>
    <a href="{{ route('admin.copyright.index') }}" style="font-size:12px;color:#FF8A00;text-decoration:none;">← All Claims</a>
</div>

@if(session('success'))
<div style="background:#dcfce7;color:#16a34a;padding:10px 16px;border-radius:10px;margin-bottom:16px;font-weight:700;">✓ {{ session('success') }}</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

    {{-- Claimant Info --}}
    <div style="background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:20px;">
        <h3 style="font-size:14px;font-weight:800;color:#111827;margin-bottom:14px;">Claimant</h3>
        <div style="font-size:14px;font-weight:700;color:#111827;">{{ $claim->claimant_name }}</div>
        <div style="font-size:12px;color:#6b7280;margin-top:2px;">{{ $claim->claimant_email }}</div>
        @if($claim->original_url)
        <div style="margin-top:10px;">
            <span style="font-size:11px;color:#9ca3af;font-weight:700;">Original Work URL</span>
            <a href="{{ $claim->original_url }}" target="_blank" style="display:block;font-size:12px;color:#3b82f6;margin-top:2px;word-break:break-all;">{{ Str::limit($claim->original_url, 60) }}</a>
        </div>
        @endif
        <div style="margin-top:12px;background:#f9fafb;border-radius:10px;padding:12px;">
            <div style="font-size:11px;color:#9ca3af;font-weight:700;margin-bottom:6px;">WORK DESCRIPTION</div>
            <p style="font-size:13px;color:#374151;margin:0;line-height:1.6;">{{ $claim->work_description }}</p>
        </div>
    </div>

    {{-- Reported Content --}}
    <div style="background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:20px;">
        <h3 style="font-size:14px;font-weight:800;color:#111827;margin-bottom:14px;">Reported Content</h3>
        <div style="background:#f9fafb;border-radius:10px;padding:12px;margin-bottom:12px;">
            <span style="font-size:11px;color:#9ca3af;font-weight:700;">TYPE</span>
            <div style="font-size:13px;font-weight:700;color:#374151;margin-top:2px;">{{ ucfirst($claim->reported_type) }} #{{ $claim->reported_id }}</div>
        </div>
        @if($content)
        <div style="background:#f9fafb;border-radius:10px;padding:12px;margin-bottom:12px;">
            <span style="font-size:11px;color:#9ca3af;font-weight:700;">AUTHOR</span>
            <div style="font-size:13px;font-weight:700;color:#374151;margin-top:2px;">{{ $content->author ?? 'Unknown' }}</div>
        </div>
        @if(isset($content->content) && $content->content)
        <div style="background:#f9fafb;border-radius:10px;padding:12px;">
            <span style="font-size:11px;color:#9ca3af;font-weight:700;">CONTENT</span>
            <p style="font-size:13px;color:#374151;margin:4px 0 0;line-height:1.6;">{{ Str::limit($content->content, 200) }}</p>
        </div>
        @endif
        @endif
        @php
            $disabled = $claim->content_disabled;
        @endphp
        <div style="margin-top:10px;display:flex;align-items:center;gap:8px;">
            <span style="width:8px;height:8px;border-radius:50%;background:{{ $disabled ? '#dc2626' : '#16a34a' }};display:inline-block;"></span>
            <span style="font-size:12px;color:{{ $disabled ? '#dc2626' : '#16a34a' }};font-weight:700;">
                {{ $disabled ? 'Content Disabled' : 'Content Active' }}
            </span>
        </div>
    </div>
</div>

{{-- Counter Notice --}}
@if($counter)
<div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:14px;padding:20px;margin-bottom:16px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <h3 style="font-size:14px;font-weight:800;color:#5b21b6;margin:0;">Counter-Notice from {{ $counter->user_name }}</h3>
        @php $cs=$counter->status; @endphp
        <span style="padding:3px 10px;border-radius:20px;font-size:10px;font-weight:800;
            background:{{ $cs==='pending'?'#fef3c7':($cs==='accepted'?'#dcfce7':'#fee2e2') }};
            color:{{ $cs==='pending'?'#d97706':($cs==='accepted'?'#16a34a':'#dc2626') }};">
            {{ ucfirst($cs) }}
        </span>
    </div>
    <p style="font-size:13px;color:#374151;line-height:1.6;margin-bottom:14px;">{{ $counter->statement }}</p>
    @if($counter->jurisdiction)
    <div style="font-size:12px;color:#7c3aed;">Jurisdiction: {{ $counter->jurisdiction }}</div>
    @endif
    @if($counter->status === 'pending')
    <div style="margin-top:14px;display:flex;gap:10px;">
        <form method="POST" action="{{ route('admin.copyright.counter.resolve', $counter->id) }}">
            @csrf
            <input type="hidden" name="decision" value="accepted">
            <button type="submit" style="background:#16a34a;color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:12px;font-weight:700;cursor:pointer;"
                onclick="return confirm('Accept counter-notice and restore content?')">
                Accept Counter (Restore Content)
            </button>
        </form>
        <form method="POST" action="{{ route('admin.copyright.counter.resolve', $counter->id) }}">
            @csrf
            <input type="hidden" name="decision" value="rejected">
            <button type="submit" style="background:#dc2626;color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:12px;font-weight:700;cursor:pointer;"
                onclick="return confirm('Reject counter-notice? Content stays disabled.')">
                Reject Counter
            </button>
        </form>
    </div>
    @endif
</div>
@endif

{{-- Resolve Form --}}
@if(!in_array($claim->status, ['upheld','dismissed']))
<div style="background:#fff;border:1px solid #eef0f6;border-radius:14px;padding:20px;">
    <h3 style="font-size:14px;font-weight:800;color:#111827;margin-bottom:14px;">Review Decision</h3>
    <form method="POST" action="{{ route('admin.copyright.resolve', $claim->id) }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
            @foreach([
                ['under_review','Under Review','#3b82f6','#eff6ff','Mark as under review'],
                ['upheld','Uphold Claim','#dc2626','#fee2e2','Disable infringing content'],
                ['dismissed','Dismiss Claim','#16a34a','#dcfce7','Content stays active'],
            ] as [$val,$label,$color,$bg,$desc])
            <label style="display:flex;align-items:flex-start;gap:10px;background:{{ $bg }};border:2px solid {{ $color }}20;border-radius:10px;padding:12px;cursor:pointer;">
                <input type="radio" name="decision" value="{{ $val }}" style="margin-top:2px;" required>
                <div>
                    <div style="font-size:13px;font-weight:800;color:{{ $color }};">{{ $label }}</div>
                    <div style="font-size:11px;color:#6b7280;">{{ $desc }}</div>
                </div>
            </label>
            @endforeach
        </div>
        <div style="margin-bottom:14px;">
            <label style="font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:6px;">Admin Note (optional)</label>
            <textarea name="admin_note" rows="3" style="width:100%;border:1px solid #eef0f6;border-radius:8px;padding:10px;font-size:13px;resize:vertical;">{{ $claim->admin_note }}</textarea>
        </div>
        <button type="submit" style="background:#FF8A00;color:#fff;border:none;border-radius:8px;padding:10px 24px;font-size:13px;font-weight:700;cursor:pointer;">
            Submit Decision
        </button>
    </form>
</div>
@else
<div style="background:#f9fafb;border:1px solid #eef0f6;border-radius:14px;padding:20px;text-align:center;">
    <div style="font-size:14px;font-weight:800;color:#374151;">Claim {{ ucfirst($claim->status) }}</div>
    @if($claim->admin_note)
    <p style="font-size:13px;color:#6b7280;margin-top:6px;">{{ $claim->admin_note }}</p>
    @endif
    @if($claim->reviewed_at)
    <div style="font-size:12px;color:#9ca3af;margin-top:4px;">Reviewed {{ \Carbon\Carbon::parse($claim->reviewed_at)->diffForHumans() }}</div>
    @endif
</div>
@endif
@endsection
