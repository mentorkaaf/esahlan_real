@extends('admin.layouts.app')
@section('title','Review Queue')
@section('content')
<style>
.q-card{background:#fff;border:1px solid #eef0f6;border-radius:14px;overflow:hidden;margin-bottom:12px;display:flex;gap:0;}
.q-media{width:100px;min-height:100px;background:#f3f4f6;flex-shrink:0;position:relative;overflow:hidden;}
.q-media img{width:100%;height:100%;object-fit:cover;}
.q-body{flex:1;padding:14px 16px;}
.q-score-bar{height:6px;border-radius:3px;background:#f3f4f6;margin-top:4px;margin-bottom:8px;}
.badge-risk{display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:800;}
.badge-high{background:#fee2e2;color:#dc2626;} .badge-medium{background:#fef3c7;color:#d97706;}
.badge-low{background:#dcfce7;color:#16a34a;} .badge-critical{background:#7f1d1d;color:#fff;}
.q-actions{display:flex;align-items:center;gap:8px;padding:14px 16px;border-left:1px solid #f3f4f6;flex-shrink:0;}
.btn-approve{background:#dcfce7;color:#16a34a;border:none;padding:8px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;}
.btn-reject{background:#fee2e2;color:#dc2626;border:none;padding:8px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;}
.btn-escalate{background:#f3f4f6;color:#374151;border:none;padding:8px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;}
</style>

<div class="page-header" style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;">
    <div>
        <h1 style="font-size:20px;font-weight:900;color:#111827;">Review Queue</h1>
        <p style="font-size:13px;color:#6b7280;">Posts pending AI review</p>
    </div>
    <a href="{{ route('admin.trust-safety.dashboard') }}" style="font-size:12px;color:#FF8A00;text-decoration:none;">← Dashboard</a>
</div>

@if(session('success'))
<div style="background:#dcfce7;color:#16a34a;padding:10px 16px;border-radius:10px;margin-bottom:16px;font-weight:700;">
    ✓ {{ session('success') }}
</div>
@endif

@forelse($posts as $post)
@php
$score = $post->moderation_score ?? 0;
$risk = $score >= 0.9 ? 'critical' : ($score >= 0.7 ? 'high' : ($score >= 0.4 ? 'medium' : 'low'));
$pct = round($score * 100);
$barColor = $score >= 0.9 ? '#dc2626' : ($score >= 0.7 ? '#f97316' : ($score >= 0.4 ? '#f59e0b' : '#22c55e'));
@endphp
<div class="q-card">
    <div class="q-media">
        @if($post->media_url)
            <img src="{{ $post->media_url }}" onerror="this.parentElement.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;color:#9ca3af;font-size:24px;\'><i class=\'fas fa-image\'></i></div>'">
        @else
            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#9ca3af;font-size:24px;min-height:100px;">
                <i class="fas fa-file-alt"></i>
            </div>
        @endif
    </div>
    <div class="q-body">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
            <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#FF8A00,#ff5f00);display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;font-weight:800;">
                {{ strtoupper(substr($post->author,0,1)) }}
            </div>
            <span style="font-weight:700;font-size:13px;color:#111827;">{{ $post->author }}</span>
            <span style="font-size:11px;color:#9ca3af;">{{ \Carbon\Carbon::parse($post->created_at)->diffForHumans() }}</span>
            <span class="badge-risk badge-{{ strtolower($post->type ?? 'text') }}" style="background:#eff6ff;color:#3b82f6;">{{ ucfirst($post->type) }}</span>
        </div>
        @if($post->content)
        <p style="font-size:13px;color:#374151;margin-bottom:8px;line-height:1.5;">{{ Str::limit($post->content, 200) }}</p>
        @endif
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:11px;color:#6b7280;font-weight:600;">AI Score:</span>
            <span style="font-size:13px;font-weight:800;color:{{ $barColor }};">{{ $pct }}%</span>
            <span class="badge-risk badge-{{ $risk }}">{{ ucfirst($risk) }} Risk</span>
        </div>
        <div class="q-score-bar">
            <div style="width:{{ $pct }}%;height:100%;border-radius:3px;background:{{ $barColor }};"></div>
        </div>
        <div style="font-size:11px;color:#9ca3af;">Post #{{ $post->id }}</div>
    </div>
    <div class="q-actions" style="flex-direction:column;justify-content:center;">
        <form method="POST" action="{{ route('admin.trust-safety.queue.moderate', $post->id) }}" style="width:100%;">
            @csrf
            <input type="hidden" name="action" value="approve">
            <button class="btn-approve" style="width:100%;margin-bottom:6px;"><i class="fas fa-check" style="margin-right:4px;"></i>Approve</button>
        </form>
        <form method="POST" action="{{ route('admin.trust-safety.queue.moderate', $post->id) }}" style="width:100%;">
            @csrf
            <input type="hidden" name="action" value="reject">
            <button class="btn-reject" style="width:100%;margin-bottom:6px;"><i class="fas fa-times" style="margin-right:4px;"></i>Remove</button>
        </form>
        <form method="POST" action="{{ route('admin.trust-safety.queue.moderate', $post->id) }}" style="width:100%;">
            @csrf
            <input type="hidden" name="action" value="escalate">
            <button class="btn-escalate" style="width:100%;"><i class="fas fa-arrow-up" style="margin-right:4px;"></i>Escalate</button>
        </form>
    </div>
</div>
@empty
<div style="text-align:center;padding:60px;background:#fff;border-radius:14px;border:1px solid #eef0f6;">
    <i class="fas fa-check-circle" style="font-size:48px;color:#22c55e;margin-bottom:16px;display:block;"></i>
    <div style="font-size:18px;font-weight:800;color:#111827;margin-bottom:8px;">Queue is clear!</div>
    <div style="font-size:13px;color:#6b7280;">All content has been reviewed.</div>
</div>
@endforelse

<div style="margin-top:16px;">{{ $posts->links() }}</div>
@endsection
