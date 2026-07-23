@extends('admin.layouts.app')
@section('title', 'Broadcast Stats')
@section('content')

<style>
.bs-head { display:flex; align-items:center; gap:14px; margin-bottom:22px; }
.bs-back { color:#64748b; text-decoration:none; font-size:13px; font-weight:600; display:flex; align-items:center; gap:6px; }
.bs-back:hover { color:#07003B; }
.bs-title { font-size:18px; font-weight:900; color:#1a1d2e; }
.bs-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; }
.bs-card { background:#fff; border:1px solid #e8ecf2; border-radius:14px; padding:20px 22px; text-align:center; }
.bs-val { font-size:36px; font-weight:900; color:#1a1d2e; }
.bs-lbl { font-size:12px; color:#94a3b8; font-weight:600; margin-top:6px; }
.bs-pct { font-size:13px; font-weight:700; margin-top:4px; }
</style>

<div class="bs-head">
    <a href="{{ route('admin.inbox.broadcasts.index') }}" class="bs-back">
        <i class="fas fa-arrow-left"></i> Broadcasts
    </a>
    <div class="bs-title">{{ $broadcast->title }}</div>
</div>

<div class="bs-grid">
    <div class="bs-card">
        <div class="bs-val">{{ number_format($stats['sent']) }}</div>
        <div class="bs-lbl">Total Sent</div>
    </div>
    <div class="bs-card">
        <div class="bs-val" style="color:#2563eb">{{ number_format($stats['read']) }}</div>
        <div class="bs-lbl">Read</div>
        @if($stats['sent'] > 0)
        <div class="bs-pct" style="color:#2563eb">{{ round($stats['read'] / $stats['sent'] * 100) }}%</div>
        @endif
    </div>
    <div class="bs-card">
        <div class="bs-val" style="color:#16a34a">{{ number_format($stats['cta_clicked']) }}</div>
        <div class="bs-lbl">CTA Clicked</div>
        @if($stats['sent'] > 0)
        <div class="bs-pct" style="color:#16a34a">{{ round($stats['cta_clicked'] / $stats['sent'] * 100) }}%</div>
        @endif
    </div>
</div>
@endsection
