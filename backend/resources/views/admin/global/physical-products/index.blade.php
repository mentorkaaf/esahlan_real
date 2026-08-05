@extends('admin.layouts.app')
@section('title', 'Physical Products / Warehouse')

@section('content')
<div class="page-header">
    <div><h1 class="page-title">🏭 Physical Products & Warehouse</h1><p class="page-subtitle">Own inventory management and restocking</p></div>
    <a href="{{ route('admin.global.products.create') }}" style="padding:9px 16px;background:#6366f1;color:#fff;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:6px"><i class="fas fa-plus"></i> Add Product</a>
</div>

@if(session('success'))<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>@endif

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:20px">
    @php $kpis=[
        ['Total',$stats['total'],'#6366f1'],['Active',$stats['active'],'#10b981'],
        ['Out of Stock',$stats['out_stock'],'#ef4444'],['Low Stock',$stats['low_stock'],'#f59e0b'],
        ['Total Units',number_format($stats['total_units']),'#3b82f6'],['Inventory Value','$'.number_format($stats['inventory_value'],0),'#8b5cf6'],
    ]; @endphp
    @foreach($kpis as [$l,$v,$c])
    <div style="background:#fff;border-radius:10px;padding:14px;border:1px solid #e5e7eb;text-align:center">
        <div style="font-size:18px;font-weight:800;color:{{ $c }}">{{ $v }}</div>
        <div style="font-size:11px;color:#6b7280;margin-top:2px">{{ $l }}</div>
    </div>
    @endforeach
</div>

{{-- Filters --}}
<form method="GET" style="background:#fff;border-radius:10px;padding:13px 18px;margin-bottom:14px;border:1px solid #e5e7eb;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:2;min-width:150px"><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Search</label><input name="search" value="{{ request('search') }}" placeholder="Name or SKU" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"></div>
    <div style="flex:1;min-width:120px"><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Stock Status</label>
        <select name="stock_status" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"><option value="">All</option><option value="out" {{ request('stock_status')==='out'?'selected':'' }}>Out of Stock</option><option value="low" {{ request('stock_status')==='low'?'selected':'' }}>Low</option><option value="ok" {{ request('stock_status')==='ok'?'selected':'' }}>In Stock</option></select></div>
    <button type="submit" style="padding:8px 18px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Filter</button>
</form>

<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
                <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Product</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">SKU</th>
                <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Price</th>
                <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Cost</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Stock</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Status</th>
                <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Restock</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $p)
            @php $sc = $p->stock<=0?'#ef4444':($p->stock<=10?'#f59e0b':'#10b981'); @endphp
            <tr style="border-top:1px solid #f3f4f6">
                <td style="padding:10px 16px">
                    <div style="display:flex;align-items:center;gap:10px">
                        @if($p->thumbnail)<img src="{{ $p->thumbnail }}" style="width:36px;height:36px;border-radius:6px;object-fit:cover">@endif
                        <div>
                            <div style="font-size:12px;font-weight:600;color:#111">{{ Str::limit($p->name,35) }}</div>
                            <div style="font-size:11px;color:#9ca3af">{{ $p->category?->name }}</div>
                        </div>
                    </div>
                </td>
                <td style="padding:10px 16px;text-align:center;font-size:11px;color:#6b7280;font-family:monospace">{{ $p->sku ?: '—' }}</td>
                <td style="padding:10px 16px;text-align:right;font-size:13px;font-weight:700;color:#111">${{ number_format($p->price,2) }}</td>
                <td style="padding:10px 16px;text-align:right;font-size:12px;color:#6b7280">${{ $p->cost_price ? number_format($p->cost_price,2) : '—' }}</td>
                <td style="padding:10px 16px;text-align:center"><span style="font-size:14px;font-weight:800;color:{{ $sc }}">{{ $p->stock }}</span></td>
                <td style="padding:10px 16px;text-align:center"><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;{{ $p->is_active?'color:#065f46;background:#d1fae5':'color:#991b1b;background:#fee2e2' }}">{{ $p->is_active?'ACTIVE':'INACTIVE' }}</span></td>
                <td style="padding:10px 16px;text-align:right">
                    <button onclick="toggleRestock({{ $p->id }})" style="padding:4px 10px;background:#f3f4f6;color:#374151;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer">+ Restock</button>
                    <div id="restock-{{ $p->id }}" class="hidden" style="margin-top:6px">
                        <form method="POST" action="{{ route('admin.global.physical-products.restock', $p) }}">
                            @csrf
                            <div style="display:flex;gap:5px">
                                <input name="quantity" type="number" min="1" value="50" style="width:60px;padding:5px 8px;border:1px solid #d1d5db;border-radius:5px;font-size:11px">
                                <button type="submit" style="padding:5px 10px;background:#10b981;color:#fff;border:none;border-radius:5px;font-size:11px;font-weight:700;cursor:pointer">Add</button>
                            </div>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="padding:40px;text-align:center;color:#9ca3af"><div style="font-size:32px;margin-bottom:10px">🏭</div><div>No physical products yet</div></td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
    @if($products->hasPages())<div style="padding:14px 18px;border-top:1px solid #f3f4f6">{{ $products->links() }}</div>@endif
</div>

<style>.hidden{display:none!important}</style>
<script>function toggleRestock(id){document.getElementById('restock-'+id).classList.toggle('hidden')}</script>
@endsection
