@extends('admin.layouts.app')
@section('title', 'eGrocery Inventory')
@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-warehouse" style="color:#8b5cf6"></i> Inventory</h2>
        <ol class="breadcrumb"><li><a href="{{ route('admin.module-data.egrocery.index') }}">eGrocery</a></li><li>Inventory</li></ol>
    </div>
    <button onclick="document.getElementById('adjustModal').style.display='flex'" class="btn btn-primary"><i class="fas fa-plus-minus"></i> Adjust Stock</button>
</div>

@include('admin.egrocery._subnav')

@if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif

{{-- Quick stats --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;">
    {{-- Low Stock --}}
    <div class="card" style="{{ $lowStock->count() > 0 ? 'border-left:3px solid #f59e0b;' : '' }}">
        <div class="card-header" style="{{ $lowStock->count() > 0 ? 'background:#fffbeb;' : '' }}">
            <div class="card-header-title" style="{{ $lowStock->count() > 0 ? 'color:#d97706;' : '' }}">
                <i class="fas fa-triangle-exclamation"></i> Low Stock ({{ $lowStock->count() }} variants)
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Product</th><th>Variant</th><th>Stock</th><th>Threshold</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($lowStock as $v)
                    <tr>
                        <td style="font-weight:600;">{{ $v->product?->name }}</td>
                        <td style="color:#64748b;">{{ $v->label }}</td>
                        <td><span class="badge badge-warning">{{ $v->stock_qty }}</span></td>
                        <td style="color:#9ca3af;">{{ $v->low_stock_threshold }}</td>
                        <td>
                            <button onclick="prefillAdjust({{ $v->id }}, '{{ addslashes($v->product?->name) }} — {{ addslashes($v->label) }}')"
                                    class="btn btn-sm" style="background:#dcfce7;color:#16a34a;font-size:11px;"><i class="fas fa-plus"></i> Restock</button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:20px;">All stocked ✓</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Out of Stock --}}
    <div class="card" style="{{ $outOfStock->count() > 0 ? 'border-left:3px solid #ef4444;' : '' }}">
        <div class="card-header" style="{{ $outOfStock->count() > 0 ? 'background:#fef2f2;' : '' }}">
            <div class="card-header-title" style="{{ $outOfStock->count() > 0 ? 'color:#dc2626;' : '' }}">
                <i class="fas fa-box-open"></i> Out of Stock ({{ $outOfStock->count() }})
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Product</th><th>Variant</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($outOfStock as $v)
                    <tr>
                        <td style="font-weight:600;">{{ $v->product?->name }}</td>
                        <td style="color:#64748b;">{{ $v->label }}</td>
                        <td>
                            <button onclick="prefillAdjust({{ $v->id }}, '{{ addslashes($v->product?->name) }} — {{ addslashes($v->label) }}')"
                                    class="btn btn-sm" style="background:#dcfce7;color:#16a34a;font-size:11px;"><i class="fas fa-plus"></i> Add Stock</button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="text-align:center;color:#9ca3af;padding:20px;">No out-of-stock items ✓</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Movements Log --}}
<div class="card">
    <div class="card-header">
        <div class="card-header-title"><i class="fas fa-list-check" style="color:#3b82f6"></i> Stock Movements</div>
        <form method="GET" style="display:flex;gap:8px;">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Product name..." class="form-control" style="width:180px;">
            <select name="type" class="form-control" style="width:130px;">
                <option value="">All Types</option>
                @foreach(['purchase','sale','adjustment','return','waste'] as $t)
                <option value="{{ $t }}" {{ request('type')===$t?'selected':'' }}>{{ ucfirst($t) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn" style="background:#f1f5f9;color:#374151;">Filter</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Variant</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>Before</th>
                    <th>After</th>
                    <th>Reference</th>
                    <th>Note</th>
                    <th>By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movements as $m)
                @php
                    $badges = ['purchase'=>'badge-success','sale'=>'badge-info','adjustment'=>'badge-warning','return'=>'badge-teal','waste'=>'badge-danger'];
                @endphp
                <tr>
                    <td style="font-weight:600;">{{ $m->product_name }}</td>
                    <td style="color:#64748b;">{{ $m->variant_label }}</td>
                    <td><span class="badge {{ $badges[$m->type] ?? 'badge-secondary' }}">{{ ucfirst($m->type) }}</span></td>
                    <td style="font-weight:700;color:{{ $m->qty > 0 ? '#10b981' : '#ef4444' }};">{{ $m->qty > 0 ? '+' : '' }}{{ $m->qty }}</td>
                    <td style="color:#9ca3af;">{{ $m->stock_before }}</td>
                    <td style="font-weight:600;">{{ $m->stock_after }}</td>
                    <td style="font-size:11px;color:#9ca3af;">{{ $m->reference }}</td>
                    <td style="font-size:12px;color:#64748b;max-width:180px;">{{ $m->note }}</td>
                    <td style="font-size:12px;color:#9ca3af;">{{ $m->actor_name }}</td>
                    <td style="font-size:12px;color:#9ca3af;white-space:nowrap;">{{ \Carbon\Carbon::parse($m->created_at)->format('M j H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="10" style="text-align:center;color:#9ca3af;padding:32px;">No stock movements yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($movements->hasPages())
    <div class="card-footer">{{ $movements->links() }}</div>
    @endif
</div>

{{-- Adjust Modal --}}
<div id="adjustModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:440px;box-shadow:0 25px 60px rgba(0,0,0,.2);">
        <div style="padding:20px;border-bottom:1px solid #f1f5f9;font-weight:800;font-size:15px;display:flex;justify-content:space-between;align-items:center;">
            <span><i class="fas fa-plus-minus" style="color:#8b5cf6;margin-right:8px;"></i>Stock Adjustment</span>
            <button onclick="document.getElementById('adjustModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:18px;color:#9ca3af;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.module-data.egrocery.inventory.adjust') }}" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
            @csrf
            <div>
                <label class="form-label">Search Variant</label>
                <input type="text" id="varSearch" class="form-control" placeholder="Type product name..." autocomplete="off">
                <div id="varResults" style="background:#fff;border:1px solid #e8edf5;border-radius:8px;margin-top:4px;display:none;max-height:200px;overflow-y:auto;box-shadow:0 8px 20px rgba(0,0,0,.1);"></div>
                <input type="hidden" name="variant_id" id="variantId" required>
                <div id="selectedVariant" style="margin-top:8px;font-size:13px;color:#64748b;display:none;background:#f1f5f9;padding:8px 12px;border-radius:8px;"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label class="form-label">Quantity (+/-)</label>
                    <input type="number" name="qty" class="form-control" placeholder="e.g. 50 or -5" step="0.001" required>
                </div>
                <div>
                    <label class="form-label">Type</label>
                    <select name="type" class="form-control">
                        <option value="purchase">Purchase</option>
                        <option value="adjustment">Adjustment</option>
                        <option value="return">Return</option>
                        <option value="waste">Waste</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="form-label">Note</label>
                <input type="text" name="note" class="form-control" placeholder="Optional note">
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Confirm Adjustment</button>
                <button type="button" onclick="document.getElementById('adjustModal').style.display='none'" class="btn" style="background:#f1f5f9;color:#374151;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const searchUrl = '{{ route("admin.module-data.egrocery.inventory.variant-search") }}';

let searchTimer;
document.getElementById('varSearch').addEventListener('input', function() {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 2) { document.getElementById('varResults').style.display='none'; return; }
    searchTimer = setTimeout(() => {
        fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
            .then(r => r.json()).then(data => {
                const box = document.getElementById('varResults');
                if (!data.length) { box.style.display='none'; return; }
                box.innerHTML = data.map(v =>
                    `<div onclick="selectVariant(${v.id},'${v.label.replace(/'/g,"\\'")}',${v.stock})"
                         style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;font-size:13px;"
                         onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background=''"
                    >${v.label} <span style="float:right;color:#9ca3af;">Stock: ${v.stock}</span></div>`
                ).join('');
                box.style.display = 'block';
            });
    }, 300);
});

function selectVariant(id, label, stock) {
    document.getElementById('variantId').value = id;
    document.getElementById('varSearch').value = '';
    document.getElementById('varResults').style.display = 'none';
    const sel = document.getElementById('selectedVariant');
    sel.textContent = '✓ ' + label + ' (current stock: ' + stock + ')';
    sel.style.display = 'block';
}

function prefillAdjust(id, label) {
    document.getElementById('adjustModal').style.display = 'flex';
    selectVariant(id, label, '?');
}
</script>
@endsection
