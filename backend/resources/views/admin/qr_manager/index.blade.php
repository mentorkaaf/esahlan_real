@extends('admin.layouts.app')
@section('title', 'QR Code Manager')
@section('content')
<style>
.qm-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;}
.qm-title{font-size:22px;font-weight:800;color:#0f172a;}
.qm-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border-radius:10px;font-size:14px;font-weight:700;text-decoration:none;border:none;cursor:pointer;transition:all .15s;}
.qm-btn-primary{background:#FF8A00;color:#fff;}
.qm-btn-primary:hover{background:#e07800;color:#fff;}
.qm-btn-sm{padding:6px 14px;font-size:13px;}
.qm-btn-ghost{background:#f8fafc;color:#374151;border:1.5px solid #e2e8f0;}
.qm-btn-ghost:hover{background:#f1f5f9;}
.qm-btn-danger{background:#fff5f5;color:#ef4444;border:1.5px solid #fecaca;}
.qm-btn-danger:hover{background:#fee2e2;}

.filter-bar{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;}
.filter-bar input,.filter-bar select{padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;color:#374151;background:#fff;outline:none;}
.filter-bar input:focus,.filter-bar select:focus{border-color:#FF8A00;}

.qr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;}
.qr-card{background:#fff;border-radius:16px;border:1.5px solid #e2e8f0;overflow:hidden;transition:box-shadow .15s;}
.qr-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.08);}
.qr-card-top{height:6px;}
.qr-card-body{padding:18px;}
.qr-type-badge{display:inline-flex;align-items:center;gap:5px;background:#f8fafc;border-radius:99px;padding:4px 10px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:10px;}
.qr-card-title{font-size:15px;font-weight:700;color:#0f172a;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.qr-card-token{font-size:12px;color:#94a3b8;font-family:monospace;margin-bottom:12px;}
.qr-card-stats{display:flex;gap:16px;font-size:12px;color:#64748b;margin-bottom:14px;}
.qr-card-stats span{display:flex;align-items:center;gap:4px;}
.qr-card-actions{display:flex;gap:8px;}
.inactive-badge{display:inline-block;background:#fef3c7;color:#92400e;border-radius:99px;padding:2px 8px;font-size:11px;font-weight:700;margin-left:6px;}

.empty{text-align:center;padding:60px 20px;color:#94a3b8;}
.empty-icon{font-size:48px;margin-bottom:12px;}
</style>

<div class="qm-header">
    <div>
        <div class="qm-title">📱 QR Code Manager</div>
        <div style="color:#64748b;font-size:13px;margin-top:2px;">Create and manage QR codes for any eSahlan service</div>
    </div>
    <a href="{{ route('admin.qr-manager.create') }}" class="qm-btn qm-btn-primary">
        <i class="fas fa-plus"></i> New QR Code
    </a>
</div>

@if(session('success'))
<div style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;border-radius:10px;padding:12px 16px;margin-bottom:18px;font-weight:600;">
    ✅ {{ session('success') }}
</div>
@endif

<form method="GET" class="filter-bar">
    <input type="text" name="search" placeholder="🔍 Search QR codes..." value="{{ request('search') }}" style="flex:1;min-width:200px;">
    <select name="type">
        <option value="">All Types</option>
        @foreach($types as $key => $t)
        <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>{{ $t['icon'] }} {{ $t['label'] }}</option>
        @endforeach
    </select>
    <button type="submit" class="qm-btn qm-btn-ghost">Filter</button>
    @if(request()->hasAny(['search','type']))
    <a href="{{ route('admin.qr-manager.index') }}" class="qm-btn qm-btn-ghost">✕ Clear</a>
    @endif
</form>

@if($qrCodes->isEmpty())
<div class="empty">
    <div class="empty-icon">📱</div>
    <div style="font-size:16px;font-weight:700;color:#374151;margin-bottom:6px;">No QR Codes yet</div>
    <div style="margin-bottom:18px;">Create your first QR code for any eSahlan service</div>
    <a href="{{ route('admin.qr-manager.create') }}" class="qm-btn qm-btn-primary">+ Create QR Code</a>
</div>
@else
<div class="qr-grid">
    @foreach($qrCodes as $qr)
    @php $typeInfo = $types[$qr->type] ?? $types['custom']; @endphp
    <div class="qr-card">
        <div class="qr-card-top" style="background:{{ $qr->color }};"></div>
        <div class="qr-card-body">
            <div class="qr-type-badge">{{ $typeInfo['icon'] }} {{ $typeInfo['label'] }}</div>
            <div class="qr-card-title">
                {{ $qr->title }}
                @if(!$qr->is_active)<span class="inactive-badge">Inactive</span>@endif
            </div>
            <div class="qr-card-token">TOKEN: {{ $qr->token }}</div>
            <div class="qr-card-stats">
                <span><i class="fas fa-qrcode"></i> {{ number_format($qr->scan_count) }} scans</span>
                <span><i class="fas fa-clock"></i> {{ $qr->created_at->diffForHumans() }}</span>
            </div>
            <div class="qr-card-actions">
                <a href="{{ route('admin.qr-manager.show', $qr) }}" class="qm-btn qm-btn-ghost qm-btn-sm" style="flex:1;justify-content:center;">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="{{ route('admin.qr-manager.edit', $qr) }}" class="qm-btn qm-btn-ghost qm-btn-sm">
                    <i class="fas fa-edit"></i>
                </a>
                <form action="{{ route('admin.qr-manager.destroy', $qr) }}" method="POST"
                      onsubmit="return confirm('Delete this QR code?')">
                    @csrf @method('DELETE')
                    <button class="qm-btn qm-btn-danger qm-btn-sm"><i class="fas fa-trash"></i></button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div style="margin-top:24px;">{{ $qrCodes->links() }}</div>
@endif
@endsection
