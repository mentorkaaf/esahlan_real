@extends('admin.layouts.app')
@section('title', 'eSpace Ads Manager')
@section('content')

<style>
.espace-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
.espace-stat-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:12px; margin-bottom:24px; }
.espace-stat { background:#fff; border-radius:14px; padding:16px 18px; border-top:4px solid var(--c,#FF8A00); box-shadow:0 2px 8px rgba(0,0,0,.06); }
.espace-stat .val { font-size:26px; font-weight:900; color:#1a1a2e; }
.espace-stat .lbl { font-size:11px; color:#8A8A9A; margin-top:2px; font-weight:600; letter-spacing:.5px; text-transform:uppercase; }
.module-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:28px; }
.module-chip { border-radius:12px; padding:14px; color:#fff; position:relative; overflow:hidden; }
.module-chip .name { font-size:13px; font-weight:700; }
.module-chip .stats { font-size:11px; opacity:.85; margin-top:4px; }
.module-chip .icon { position:absolute; right:10px; top:50%; transform:translateY(-50%); font-size:28px; opacity:.3; }
.ad-table { width:100%; border-collapse:collapse; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.06); }
.ad-table th { background:#f8f9ff; padding:12px 14px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#6B7280; text-align:left; border-bottom:1px solid #E5E7EB; }
.ad-table td { padding:12px 14px; border-bottom:1px solid #F3F4F6; font-size:13px; vertical-align:middle; }
.ad-table tr:last-child td { border-bottom:none; }
.ad-table tr:hover td { background:#fafbff; }
.badge-active { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; background:#D1FAE5; color:#065F46; }
.badge-inactive { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; background:#FEE2E2; color:#991B1B; }
.module-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; }
.btn-sm-o { padding:5px 12px; border-radius:8px; font-size:12px; font-weight:600; border:none; cursor:pointer; }
.placement-tag { display:inline-block; padding:2px 8px; border-radius:6px; font-size:10px; font-weight:700; background:#EFF6FF; color:#1D4ED8; margin:1px; }
</style>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-rocket" style="color:#FF8A00"></i> eSpace Ads Manager</h2>
        <ol class="breadcrumb"><li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li>eSpace Ads</li></ol>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

{{-- Stats Row --}}
<div class="espace-stat-grid">
    <div class="espace-stat" style="--c:#FF8A00">
        <div class="val">{{ $stats['total'] }}</div>
        <div class="lbl">Total Ads</div>
    </div>
    <div class="espace-stat" style="--c:#22C55E">
        <div class="val">{{ $stats['active'] }}</div>
        <div class="lbl">Active</div>
    </div>
    <div class="espace-stat" style="--c:#3B82F6">
        <div class="val">{{ number_format($stats['impressions']) }}</div>
        <div class="lbl">Total Impressions</div>
    </div>
    <div class="espace-stat" style="--c:#8B5CF6">
        <div class="val">{{ number_format($stats['clicks']) }}</div>
        <div class="lbl">Total Clicks</div>
    </div>
    <div class="espace-stat" style="--c:#EC4899">
        <div class="val">{{ $stats['ctr'] }}%</div>
        <div class="lbl">Overall CTR</div>
    </div>
</div>

{{-- Per-module mini cards --}}
@php
$moduleColors = ['efood'=>'#FF6B35','egrocery'=>'#22C55E','eshop'=>'#8B5CF6','eparcel'=>'#F59E0B','emoving'=>'#3B82F6','elearning'=>'#06B6D4','eexchange'=>'#EC4899','erent'=>'#14B8A6'];
$moduleIcons  = ['efood'=>'🍕','egrocery'=>'🛒','eshop'=>'🛍️','eparcel'=>'📦','emoving'=>'🚛','elearning'=>'🎓','eexchange'=>'💱','erent'=>'🏠'];
$moduleLabels = ['efood'=>'eFood','egrocery'=>'eGrocery','eshop'=>'eShop','eparcel'=>'eParcel','emoving'=>'eMoving','elearning'=>'eLearning','eexchange'=>'eExchange','erent'=>'eRent'];
@endphp
<div class="module-grid">
@foreach($moduleColors as $mod => $color)
@php $ms = $moduleStats[$mod] ?? null; $ctr = ($ms && $ms->imps > 0) ? round($ms->clicks/$ms->imps*100,1) : 0; @endphp
<div class="module-chip" style="background:linear-gradient(135deg,{{ $color }},{{ $color }}cc)">
    <div class="name">{{ $moduleIcons[$mod] }} {{ $moduleLabels[$mod] }}</div>
    <div class="stats">
        {{ $ms ? number_format($ms->imps) : 0 }} imp · {{ $ms ? number_format($ms->clicks) : 0 }} clicks · {{ $ctr }}% CTR
    </div>
    <div class="icon">{{ $moduleIcons[$mod] }}</div>
</div>
@endforeach
</div>

{{-- Header + Create button --}}
<div class="espace-header">
    <h5 style="margin:0;font-weight:800;font-size:16px;">All eSpace Ads</h5>
    <a href="{{ route('admin.espace-ads.create') }}" class="btn btn-sm" style="background:#FF8A00;color:#fff;border-radius:10px;font-weight:700;padding:8px 18px;">
        <i class="fas fa-plus"></i> New Ad
    </a>
</div>

<table class="ad-table">
    <thead>
        <tr>
            <th>Ad</th>
            <th>Module</th>
            <th>Placement</th>
            <th>Priority</th>
            <th>Performance</th>
            <th>Status</th>
            <th>Schedule</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    @forelse($ads as $ad)
    <tr>
        <td>
            <div style="display:flex;align-items:center;gap:10px;">
                @if($ad->image_url)
                    <img src="{{ $ad->image_url }}" style="width:44px;height:44px;border-radius:10px;object-fit:cover;">
                @else
                    <div style="width:44px;height:44px;border-radius:10px;background:linear-gradient(135deg,{{ $moduleColors[$ad->module] ?? '#FF8A00' }},{{ $moduleColors[$ad->module] ?? '#FF8A00' }}99);display:flex;align-items:center;justify-content:center;font-size:20px;">
                        {{ $moduleIcons[$ad->module] ?? '📢' }}
                    </div>
                @endif
                <div>
                    <div style="font-weight:700;font-size:13px;">{{ $ad->title }}</div>
                    <div style="font-size:11px;color:#8A8A9A;margin-top:1px;">{{ Str::limit($ad->subtitle, 40) }}</div>
                </div>
            </div>
        </td>
        <td>
            <span style="display:inline-flex;align-items:center;gap:5px;background:{{ $moduleColors[$ad->module] ?? '#FF8A00' }}20;color:{{ $moduleColors[$ad->module] ?? '#FF8A00' }};padding:4px 10px;border-radius:8px;font-size:12px;font-weight:700;">
                <span class="module-dot" style="background:{{ $moduleColors[$ad->module] ?? '#FF8A00' }}"></span>
                {{ $moduleLabels[$ad->module] ?? $ad->module }}
            </span>
        </td>
        <td>
            @foreach(explode(',', $ad->placement) as $p)
                <span class="placement-tag">{{ $p }}</span>
            @endforeach
        </td>
        <td>
            <div style="display:flex;align-items:center;gap:6px;">
                <div style="width:60px;height:6px;border-radius:3px;background:#E5E7EB;overflow:hidden;">
                    <div style="width:{{ $ad->priority * 10 }}%;height:100%;background:#FF8A00;border-radius:3px;"></div>
                </div>
                <span style="font-size:12px;font-weight:700;">{{ $ad->priority }}/10</span>
            </div>
        </td>
        <td>
            <div style="font-size:12px;">
                <span style="color:#6B7280;">{{ number_format($ad->impressions_count) }} imp</span> ·
                <span style="color:#3B82F6;font-weight:700;">{{ number_format($ad->clicks_count) }} clicks</span>
            </div>
            <div style="font-size:11px;color:#8B5CF6;font-weight:700;">CTR: {{ $ad->ctr }}%</div>
        </td>
        <td>
            <form method="POST" action="{{ route('admin.espace-ads.toggle', $ad) }}" style="display:inline">
                @csrf
                <button type="submit" class="badge-{{ $ad->is_active ? 'active' : 'inactive' }}" style="border:none;cursor:pointer;">
                    {{ $ad->is_active ? 'Active' : 'Paused' }}
                </button>
            </form>
        </td>
        <td style="font-size:11px;color:#8A8A9A;">
            @if($ad->starts_at || $ad->ends_at)
                {{ $ad->starts_at?->format('M d') ?? '∞' }} → {{ $ad->ends_at?->format('M d') ?? '∞' }}
            @else
                <span style="color:#22C55E;font-weight:600;">Always on</span>
            @endif
        </td>
        <td>
            <div style="display:flex;gap:6px;">
                <a href="{{ route('admin.espace-ads.edit', $ad) }}" class="btn-sm-o" style="background:#EFF6FF;color:#2563EB;" title="Edit"><i class="fas fa-edit"></i></a>
                <form method="POST" action="{{ route('admin.espace-ads.destroy', $ad) }}" onsubmit="return confirm('Delete this ad?')" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-sm-o" style="background:#FEF2F2;color:#DC2626;" title="Delete"><i class="fas fa-trash"></i></button>
                </form>
            </div>
        </td>
    </tr>
    @empty
    <tr><td colspan="8" style="text-align:center;padding:40px;color:#8A8A9A;">
        <div style="font-size:40px;margin-bottom:10px;">📢</div>
        <div style="font-weight:700;">No eSpace Ads yet</div>
        <div style="font-size:12px;margin-top:4px;">Create your first module promotion ad</div>
        <a href="{{ route('admin.espace-ads.create') }}" style="display:inline-block;margin-top:12px;padding:8px 20px;background:#FF8A00;color:#fff;border-radius:10px;font-weight:700;text-decoration:none;">Create Ad</a>
    </td></tr>
    @endforelse
    </tbody>
</table>

<div style="margin-top:16px;">{{ $ads->links() }}</div>

@endsection
