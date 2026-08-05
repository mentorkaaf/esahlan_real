@extends('admin.layouts.app')
@section('title', 'Dropshipping Management')

@section('content')
<div class="page-header">
    <div><h1 class="page-title">📦 Dropshipping Management</h1><p class="page-subtitle">CJDropshipping, AliExpress & supplier management</p></div>
</div>

@if(session('success'))<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>@endif
@if(session('error'))<div style="background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">❌ {{ session('error') }}</div>@endif
@if(session('info'))<div style="background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">ℹ️ {{ session('info') }}</div>@endif

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px">
    @foreach([['Total Dropship',$stats['total'],'#6366f1','fa-box'],['Active',$stats['active'],'#10b981','fa-check'],['Out of Stock',$stats['pending'],'#ef4444','fa-times'],['Suppliers',$stats['suppliers'],'#f59e0b','fa-building']] as [$l,$v,$c,$i])
    <div style="background:#fff;border-radius:10px;padding:16px;border:1px solid #e5e7eb;display:flex;align-items:center;gap:12px">
        <div style="width:38px;height:38px;border-radius:9px;background:{{ $c }}18;display:flex;align-items:center;justify-content:center"><i class="fas {{ $i }}" style="color:{{ $c }}"></i></div>
        <div><div style="font-size:20px;font-weight:800;color:#111">{{ $v }}</div><div style="font-size:11px;color:#6b7280">{{ $l }}</div></div>
    </div>
    @endforeach
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start">

<div>
{{-- Filters --}}
<form method="GET" style="background:#fff;border-radius:10px;padding:13px 18px;margin-bottom:14px;border:1px solid #e5e7eb;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:2;min-width:150px"><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Search</label><input name="search" value="{{ request('search') }}" placeholder="Product or supplier ID" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"></div>
    <div style="flex:1;min-width:120px"><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Supplier</label>
        <select name="supplier" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"><option value="">All</option>
            @foreach($suppliers as $s)<option value="{{ $s }}" {{ request('supplier')===$s?'selected':'' }}>{{ $s }}</option>@endforeach
        </select></div>
    <button type="submit" style="padding:8px 18px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Filter</button>
</form>

{{-- Products Table --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
                <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Product</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Supplier</th>
                <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Price</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Stock</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Status</th>
                <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $p)
            <tr style="border-top:1px solid #f3f4f6">
                <td style="padding:10px 16px">
                    <div style="display:flex;align-items:center;gap:10px">
                        @if($p->thumbnail)<img src="{{ $p->thumbnail }}" style="width:36px;height:36px;border-radius:6px;object-fit:cover">@endif
                        <div>
                            <div style="font-size:12px;font-weight:600;color:#111">{{ Str::limit($p->name,35) }}</div>
                            @if($p->supplier_product_id)<div style="font-size:10px;color:#9ca3af;font-family:monospace">ID: {{ $p->supplier_product_id }}</div>@endif
                        </div>
                    </div>
                </td>
                <td style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#7c3aed">{{ $p->supplier_name ?: '—' }}</td>
                <td style="padding:10px 16px;text-align:right;font-size:13px;font-weight:700;color:#111">${{ number_format($p->price,2) }}</td>
                <td style="padding:10px 16px;text-align:center"><span style="font-size:12px;font-weight:700;color:{{ $p->stock<=0?'#ef4444':($p->stock<=10?'#f59e0b':'#111') }}">{{ $p->track_stock ? $p->stock : '∞' }}</span></td>
                <td style="padding:10px 16px;text-align:center"><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;{{ $p->is_active?'color:#065f46;background:#d1fae5':'color:#991b1b;background:#fee2e2' }}">{{ $p->is_active?'ACTIVE':'INACTIVE' }}</span></td>
                <td style="padding:10px 16px;text-align:right">
                    <div style="display:flex;gap:5px;justify-content:flex-end">
                        <a href="{{ route('admin.global.products.edit', $p) }}" style="padding:4px 10px;background:#f3f4f6;color:#374151;border-radius:5px;font-size:10px;font-weight:700;text-decoration:none">Edit</a>
                        <form method="POST" action="{{ route('admin.global.dropshipping.sync', $p) }}">
                            @csrf
                            <button type="submit" style="padding:4px 10px;background:#f5f3ff;color:#7c3aed;border:none;border-radius:5px;font-size:10px;font-weight:700;cursor:pointer">Sync</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:40px;text-align:center;color:#9ca3af"><div style="font-size:32px;margin-bottom:10px">📦</div><div>No dropship products yet</div></td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
    @if($products->hasPages())<div style="padding:14px 18px;border-top:1px solid #f3f4f6">{{ $products->links() }}</div>@endif
</div>
</div>

{{-- Import Panel --}}
<div style="display:flex;flex-direction:column;gap:16px">
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:20px">
        <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:14px">➕ Import Product</h3>
        <form method="POST" action="{{ route('admin.global.dropshipping.import') }}">
            @csrf
            <div style="display:flex;flex-direction:column;gap:11px">
                <div>
                    <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Supplier</label>
                    <select name="supplier" onchange="toggleManual(this.value)" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px">
                        <option value="cj">CJDropshipping (API — coming soon)</option>
                        <option value="aliexpress">AliExpress (API — coming soon)</option>
                        <option value="manual" selected>Manual Import</option>
                    </select>
                </div>
                <div id="manualFields">
                    <div style="margin-bottom:8px"><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Product Name *</label><input name="name" required placeholder="e.g. Wireless Earbuds" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
                        <div><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Price ($) *</label><input name="price" type="number" step="0.01" required placeholder="29.99" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"></div>
                        <div><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Stock</label><input name="stock" type="number" value="999" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"></div>
                    </div>
                    <div><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Supplier Product ID</label><input name="product_id" placeholder="CJ-XXXXX" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"></div>
                </div>
                <button type="submit" style="padding:9px;background:#7c3aed;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">Import Product</button>
            </div>
        </form>
    </div>

    <div style="background:#f5f3ff;border-radius:12px;border:1px solid #ddd6fe;padding:16px">
        <h3 style="font-size:12px;font-weight:700;color:#5b21b6;margin-bottom:10px">🔌 API Integration Status</h3>
        @foreach(['CJDropshipping'=>'Pending API key configuration','AliExpress'=>'Pending API approval','Zendrop'=>'Not configured','Spocket'=>'Not configured'] as $sup => $status)
        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #ddd6fe;font-size:12px">
            <span style="color:#374151;font-weight:600">{{ $sup }}</span>
            <span style="color:#9ca3af">{{ $status }}</span>
        </div>
        @endforeach
        <div style="margin-top:10px;font-size:11px;color:#7c3aed">Add API keys in Settings → Integrations</div>
    </div>
</div>

</div>
<script>function toggleManual(v){document.getElementById('manualFields').style.display=v==='manual'?'block':'none'}</script>
@endsection
