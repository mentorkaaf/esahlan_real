@extends('admin.layouts.app')
@section('title', 'Inventory Management')

@section('content')
<div class="page-header">
    <div><h1 class="page-title">📦 Inventory Management</h1><p class="page-subtitle">Track and manage stock levels across all products</p></div>
</div>

@if(session('success'))<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>@endif

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:20px">
    @php $kpis=[['Total Tracked',$stats['total'],'#6366f1','fa-cubes'],['In Stock',$stats['ok'],'#10b981','fa-check-circle'],['Low Stock',$stats['low'],'#f59e0b','fa-exclamation-triangle'],['Out of Stock',$stats['out'],'#ef4444','fa-times-circle'],['Inventory Value','$'.number_format($stats['total_val'],2),'#8b5cf6','fa-dollar-sign']]; @endphp
    @foreach($kpis as [$l,$v,$c,$i])
    <div style="background:#fff;border-radius:10px;padding:16px;border:1px solid #e5e7eb;display:flex;align-items:center;gap:12px">
        <div style="width:38px;height:38px;border-radius:9px;background:{{ $c }}18;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas {{ $i }}" style="color:{{ $c }}"></i></div>
        <div><div style="font-size:18px;font-weight:800;color:#111">{{ $v }}</div><div style="font-size:11px;color:#6b7280;margin-top:1px">{{ $l }}</div></div>
    </div>
    @endforeach
</div>

{{-- Filters --}}
<form method="GET" style="background:#fff;border-radius:10px;padding:14px 18px;margin-bottom:14px;border:1px solid #e5e7eb;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:2;min-width:160px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Search</label>
        <input name="search" value="{{ request('search') }}" placeholder="Product name or SKU" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
    </div>
    <div style="flex:1;min-width:120px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Stock Status</label>
        <select name="filter" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            <option value="">All</option>
            <option value="out" {{ request('filter')==='out'?'selected':'' }}>Out of Stock</option>
            <option value="low" {{ request('filter')==='low'?'selected':'' }}>Low (≤10)</option>
            <option value="ok" {{ request('filter')==='ok'?'selected':'' }}>In Stock</option>
        </select>
    </div>
    <div style="flex:1;min-width:110px">
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Type</label>
        <select name="type" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
            <option value="">All</option>
            <option value="physical" {{ request('type')==='physical'?'selected':'' }}>Physical</option>
            <option value="dropship" {{ request('type')==='dropship'?'selected':'' }}>Dropship</option>
        </select>
    </div>
    <button type="submit" style="padding:8px 18px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Filter</button>
</form>

{{-- Bulk Update Form --}}
<form method="POST" action="{{ route('admin.global.inventory.bulk-update') }}" id="bulkForm">
@csrf
<div style="background:#fff;border-radius:10px;border:1px solid #e5e7eb;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center">
        <h3 style="font-size:14px;font-weight:700;color:#111">Stock Levels (edit inline → Save All)</h3>
        <button type="submit" style="padding:7px 18px;background:#10b981;color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer">💾 Save All Changes</button>
    </div>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
                <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Product</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Type</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">SKU</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Current Stock</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">New Stock</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Quick Adjust</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $p)
            @php
            $color = $p->stock === 0 ? '#ef4444' : ($p->stock <= 10 ? '#f59e0b' : '#10b981');
            $bg    = $p->stock === 0 ? '#fef2f2' : ($p->stock <= 10 ? '#fffbeb' : '#ecfdf5');
            @endphp
            <tr style="border-top:1px solid #f3f4f6">
                <td style="padding:10px 16px">
                    <div style="display:flex;align-items:center;gap:10px">
                        @if($p->thumbnail)<img src="{{ $p->thumbnail }}" style="width:36px;height:36px;border-radius:6px;object-fit:cover">@endif
                        <div>
                            <div style="font-size:13px;font-weight:600;color:#111">{{ Str::limit($p->name,40) }}</div>
                            <div style="font-size:11px;color:#9ca3af">{{ $p->category?->name }}</div>
                        </div>
                    </div>
                </td>
                <td style="padding:10px 16px;text-align:center"><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;{{ $p->type==='dropship'?'color:#7c3aed;background:#f5f3ff':'color:#0369a1;background:#e0f2fe' }}">{{ strtoupper($p->type) }}</span></td>
                <td style="padding:10px 16px;text-align:center;font-size:12px;color:#6b7280;font-family:monospace">{{ $p->sku ?: '—' }}</td>
                <td style="padding:10px 16px;text-align:center">
                    <span style="font-size:15px;font-weight:800;color:{{ $color }};background:{{ $bg }};padding:3px 12px;border-radius:20px">{{ $p->stock }}</span>
                </td>
                <td style="padding:10px 16px;text-align:center">
                    <input type="number" name="updates[{{ $p->id }}]" value="{{ $p->stock }}" min="0" style="width:80px;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;text-align:center">
                </td>
                <td style="padding:10px 16px;text-align:center">
                    <div style="display:flex;gap:5px;justify-content:center">
                        @foreach([-10,-1,'+1','+10','+50'] as $adj)
                        <form method="POST" action="{{ route('admin.global.inventory.adjust', $p) }}">
                            @csrf
                            <input type="hidden" name="adjustment" value="{{ $adj }}">
                            <button type="submit" style="padding:3px 8px;font-size:11px;font-weight:600;border:1px solid #e5e7eb;background:#f9fafb;border-radius:5px;cursor:pointer;{{ str_starts_with((string)$adj,'-')?'color:#ef4444':'color:#10b981' }}">{{ $adj }}</button>
                        </form>
                        @endforeach
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:40px;text-align:center;color:#9ca3af">No products found</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
    @if($products->hasPages())
    <div style="padding:14px 18px;border-top:1px solid #f3f4f6">{{ $products->links() }}</div>
    @endif
</div>
</form>
@endsection
