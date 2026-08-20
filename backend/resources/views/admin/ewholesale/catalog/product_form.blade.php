@extends('admin.layouts.app')
@section('title', isset($product) ? 'Edit Product' : 'New Product')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
    <a href="{{ route('admin.module-data.wholesale.products') }}" style="color:#6b7280;text-decoration:none;font-size:13px">← Products</a>
    <h2 style="margin:0;font-size:18px;font-weight:700;color:#1B1444">{{ isset($product) ? 'Edit: '.$product->name : 'New Product' }}</h2>
</div>

@if($errors->any())
<div style="background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:6px;margin-bottom:16px">
    <strong>Errors:</strong> <ul style="margin:4px 0 0 16px">@foreach($errors->all() as $e)<li style="font-size:13px">{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ isset($product) ? route('admin.module-data.wholesale.products.update', $product) : route('admin.module-data.wholesale.products.store') }}">
@csrf @if(isset($product)) @method('PUT') @endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

{{-- Basic info --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 16px;font-size:14px;font-weight:600;color:#1B1444;border-bottom:1px solid #f3f4f6;padding-bottom:10px">Basic Info</h3>

    <div style="margin-bottom:12px">
        <label style="font-size:12px;font-weight:500;color:#374151;display:block;margin-bottom:4px">Supplier *</label>
        <select name="supplier_id" required style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box">
            @foreach($suppliers as $s)
            <option value="{{ $s->id }}" @selected((old('supplier_id',$product?->supplier_id))==$s->id)>{{ $s->display_name }}</option>
            @endforeach
        </select>
    </div>

    <div style="margin-bottom:12px">
        <label style="font-size:12px;font-weight:500;color:#374151;display:block;margin-bottom:4px">Category *</label>
        <select name="category_id" required style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box">
            @foreach($categories->whereNull('parent_id') as $cat)
            <option value="{{ $cat->id }}" @selected((old('category_id',$product?->category_id))==$cat->id)>{{ $cat->name }}</option>
            @foreach($categories->where('parent_id',$cat->id) as $sub)
            <option value="{{ $sub->id }}" @selected((old('category_id',$product?->category_id))==$sub->id)>&nbsp;&nbsp;└ {{ $sub->name }}</option>
            @endforeach
            @endforeach
        </select>
    </div>

    <div style="margin-bottom:12px">
        <label style="font-size:12px;font-weight:500;color:#374151;display:block;margin-bottom:4px">Name (EN) *</label>
        <input name="name" value="{{ old('name',$product?->name) }}" required style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box">
    </div>

    <div style="margin-bottom:12px">
        <label style="font-size:12px;font-weight:500;color:#374151;display:block;margin-bottom:4px">Name (Somali)</label>
        <input name="name_so" value="{{ old('name_so',$product?->name_so) }}" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:12px">
        <div>
            <label style="font-size:12px;font-weight:500;color:#374151;display:block;margin-bottom:4px">Unit *</label>
            <select name="unit" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px">
                @foreach(['piece','dozen','carton','bag','sack','pallet','drum','box','bundle','set','roll'] as $u)
                <option value="{{ $u }}" @selected(old('unit',$product?->unit)===$u)>{{ $u }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:12px;font-weight:500;color:#374151;display:block;margin-bottom:4px">MOQ *</label>
            <input name="moq" type="number" step="1" min="1" value="{{ old('moq',$product?->moq??1) }}" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;box-sizing:border-box">
        </div>
        <div>
            <label style="font-size:12px;font-weight:500;color:#374151;display:block;margin-bottom:4px">Lead (days)</label>
            <input name="lead_time_days" type="number" min="0" value="{{ old('lead_time_days',$product?->lead_time_days??0) }}" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;box-sizing:border-box">
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <div>
            <label style="font-size:12px;font-weight:500;color:#374151;display:block;margin-bottom:4px">Origin Country</label>
            <input name="origin_country" value="{{ old('origin_country',$product?->origin_country) }}" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;box-sizing:border-box">
        </div>
        <div>
            <label style="font-size:12px;font-weight:500;color:#374151;display:block;margin-bottom:4px">Brand</label>
            <input name="brand" value="{{ old('brand',$product?->brand) }}" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;box-sizing:border-box">
        </div>
    </div>

    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
        <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured',$product?->is_featured))> Featured product
    </label>
</div>

{{-- Price Tiers --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 16px;font-size:14px;font-weight:600;color:#1B1444;border-bottom:1px solid #f3f4f6;padding-bottom:10px">Price Tiers *</h3>
    <p style="font-size:12px;color:#6b7280;margin:0 0 12px">Add at least 1 tier. Leave Max Qty blank for unlimited.</p>

    <div id="tiersList">
        @php $existingTiers = old('tiers', isset($product) ? $product->priceTiers->map(fn($t)=>['min_qty'=>$t->min_qty,'max_qty'=>$t->max_qty,'unit_price'=>$t->unit_price])->toArray() : [['min_qty'=>1,'max_qty'=>'','unit_price'=>'']]); @endphp
        @foreach($existingTiers as $ti => $tier)
        <div class="tier-row" style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:8px;align-items:flex-end;margin-bottom:10px">
            <div>
                <label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Min Qty</label>
                <input name="tiers[{{ $ti }}][min_qty]" type="number" step="1" min="1" value="{{ $tier['min_qty'] }}" required style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:13px;box-sizing:border-box">
            </div>
            <div>
                <label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Max Qty</label>
                <input name="tiers[{{ $ti }}][max_qty]" type="number" step="1" min="1" value="{{ $tier['max_qty'] }}" placeholder="Unlimited" style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:13px;box-sizing:border-box">
            </div>
            <div>
                <label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Unit Price $</label>
                <input name="tiers[{{ $ti }}][unit_price]" type="number" step="0.01" min="0.01" value="{{ $tier['unit_price'] }}" required style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:13px;box-sizing:border-box">
            </div>
            <button type="button" onclick="this.closest('.tier-row').remove()" style="padding:7px 10px;background:#fee2e2;color:#991b1b;border:none;border-radius:5px;cursor:pointer;font-size:13px">✕</button>
        </div>
        @endforeach
    </div>

    <button type="button" id="addTier" style="padding:7px 14px;background:#f3f4f6;color:#374151;border:1px solid #d1d5db;border-radius:5px;font-size:12px;cursor:pointer">+ Add Tier</button>

    {{-- Live ladder preview --}}
    <div style="margin-top:16px;padding:12px;background:#f9fafb;border-radius:8px">
        <div style="font-size:11px;font-weight:600;color:#374151;margin-bottom:8px">Live Ladder Preview</div>
        <div id="tierPreview" style="font-size:12px;color:#6b7280">Fill in tiers above to preview.</div>
    </div>
</div>
</div>

{{-- Description --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-bottom:20px">
    <label style="font-size:12px;font-weight:500;color:#374151;display:block;margin-bottom:6px">Description</label>
    <textarea name="description" rows="4" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box">{{ old('description',$product?->description) }}</textarea>
</div>

<div style="display:flex;gap:12px">
    <button type="submit" style="padding:10px 28px;background:#F7941D;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer">{{ isset($product) ? 'Update Product' : 'Create Product' }}</button>
    <a href="{{ route('admin.module-data.wholesale.products') }}" style="padding:10px 20px;background:#6b7280;color:#fff;border-radius:6px;font-size:14px;text-decoration:none">Cancel</a>
</div>
</form>
</div>

<script>
let tierCount = {{ count($existingTiers) }};
document.getElementById('addTier').addEventListener('click', function() {
    const html = `<div class="tier-row" style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:8px;align-items:flex-end;margin-bottom:10px">
        <div><label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Min Qty</label>
            <input name="tiers[${tierCount}][min_qty]" type="number" step="1" min="1" required style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:13px;box-sizing:border-box"></div>
        <div><label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Max Qty</label>
            <input name="tiers[${tierCount}][max_qty]" type="number" step="1" min="1" placeholder="Unlimited" style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:13px;box-sizing:border-box"></div>
        <div><label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Unit Price $</label>
            <input name="tiers[${tierCount}][unit_price]" type="number" step="0.01" min="0.01" required style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:13px;box-sizing:border-box"></div>
        <button type="button" onclick="this.closest('.tier-row').remove();updatePreview()" style="padding:7px 10px;background:#fee2e2;color:#991b1b;border:none;border-radius:5px;cursor:pointer">✕</button>
    </div>`;
    document.getElementById('tiersList').insertAdjacentHTML('beforeend', html);
    tierCount++;
    updatePreview();
});

document.getElementById('tiersList').addEventListener('input', updatePreview);

function updatePreview() {
    const rows = document.querySelectorAll('.tier-row');
    let html = '';
    const tiers = [];
    rows.forEach(row => {
        const min = row.querySelector('[name*="min_qty"]')?.value;
        const max = row.querySelector('[name*="max_qty"]')?.value;
        const price = row.querySelector('[name*="unit_price"]')?.value;
        if (min && price) tiers.push({min, max, price});
    });
    tiers.sort((a,b) => parseFloat(a.min)-parseFloat(b.min));
    if (!tiers.length) { document.getElementById('tierPreview').textContent = 'Fill in tiers above to preview.'; return; }
    html = tiers.map(t => `<div style="display:flex;justify-content:space-between;padding:4px 8px;background:#fff;border-radius:4px;margin-bottom:4px">
        <span style="color:#374151">${t.min}${t.max?'–'+t.max:'+'} units</span>
        <span style="color:#10b981;font-weight:600">$${parseFloat(t.price).toFixed(2)}/unit</span>
    </div>`).join('');
    // overlap check
    for(let i=1;i<tiers.length;i++) {
        if(tiers[i-1].max && parseFloat(tiers[i].min) <= parseFloat(tiers[i-1].max)) {
            html += `<div style="color:#ef4444;font-size:11px;margin-top:6px">⚠️ Overlap detected between tier ${i} and ${i+1}</div>`;
        }
    }
    document.getElementById('tierPreview').innerHTML = html;
}
updatePreview();
</script>
@endsection
