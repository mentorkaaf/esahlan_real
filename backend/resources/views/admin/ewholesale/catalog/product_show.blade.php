@extends('admin.layouts.app')
@section('title', 'Product — ' . $product->name)

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
    <a href="{{ route('admin.module-data.wholesale.products') }}" style="color:#6b7280;text-decoration:none;font-size:13px">← Products</a>
    <h2 style="margin:0;font-size:18px;font-weight:700;color:#1B1444">{{ $product->name }}</h2>
    @if($product->is_featured) <span style="background:#fef3c7;color:#92400e;padding:3px 8px;border-radius:10px;font-size:11px">⭐ Featured</span> @endif
    <a href="{{ route('admin.module-data.wholesale.products.edit', $product) }}" style="margin-left:auto;padding:7px 16px;background:#F7941D;color:#fff;border-radius:6px;font-size:13px;text-decoration:none">Edit</a>
</div>

@if(session('success'))<div style="background:#d1fae5;color:#065f46;padding:10px 16px;border-radius:6px;margin-bottom:16px">{{ session('success') }}</div>@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

{{-- Core details --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444;border-bottom:1px solid #f3f4f6;padding-bottom:10px">Details</h3>
    @foreach([
        ['Supplier'     , $product->supplier?->display_name ?? '—'],
        ['Category'     , $product->category?->name ?? '—'],
        ['Unit'         , $product->unit],
        ['MOQ'          , $product->moq . ' ' . $product->unit],
        ['Lead Time'    , $product->lead_time_days . ' days'],
        ['Origin'       , $product->origin_country ?? '—'],
        ['Brand'        , $product->brand ?? '—'],
        ['Orders Count' , $product->orders_count],
        ['Status'       , $product->status],
    ] as [$k,$v])
    <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:13px;border-bottom:1px solid #f9fafb">
        <span style="color:#6b7280">{{ $k }}</span>
        <span style="color:#1B1444;font-weight:500">{{ $v }}</span>
    </div>
    @endforeach
</div>

{{-- Price tiers --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444;border-bottom:1px solid #f3f4f6;padding-bottom:10px">Price Tiers</h3>
    @forelse($product->priceTiers as $tier)
    <div style="display:flex;justify-content:space-between;padding:8px 12px;margin-bottom:6px;background:#f9fafb;border-radius:6px;font-size:13px">
        <span style="color:#374151;font-weight:500">
            {{ $tier->min_qty }}{{ $tier->max_qty ? '–'.$tier->max_qty : '+' }} {{ $product->unit }}
        </span>
        <span style="color:#10b981;font-weight:700">${{ $tier->unit_price }}/unit</span>
    </div>
    @empty
    <p style="color:#9ca3af;font-size:13px">No tiers defined.</p>
    @endforelse

    @if($product->priceTiers->count() > 1)
    <div style="margin-top:12px;padding:10px;background:#eff6ff;border-radius:6px;font-size:12px;color:#1e40af">
        <strong>Savings ladder:</strong> highest tier ${{ $product->max_price }} → lowest tier ${{ $product->min_price }}.
        Savings at max tier: {{ $product->max_price > 0 ? round((1-$product->min_price/$product->max_price)*100,1) : 0 }}% off
    </div>
    @endif
</div>

{{-- Variants --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444;border-bottom:1px solid #f3f4f6;padding-bottom:10px">Variants ({{ $product->variants->count() }})</h3>
    @forelse($product->variants as $v)
    <div style="display:flex;justify-content:space-between;padding:8px 12px;margin-bottom:6px;background:#f9fafb;border-radius:6px;font-size:13px">
        <span>{{ $v->sku ?? 'Default' }} @if($v->is_default) <span style="color:#F7941D">★ Default</span> @endif</span>
        <span style="color:#6b7280">Qty: {{ $v->stock_qty }}</span>
    </div>
    @empty
    <p style="color:#9ca3af;font-size:13px">No variants.</p>
    @endforelse
</div>

{{-- Active deals --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444;border-bottom:1px solid #f3f4f6;padding-bottom:10px">Active Deals</h3>
    @forelse($product->activeDeals as $deal)
    <div style="display:flex;justify-content:space-between;padding:8px 12px;background:#fff7ed;border-radius:6px;margin-bottom:6px;font-size:13px">
        <span style="color:#374151">{{ $deal->deal_price_percent_off }}% off for qty ≥ {{ $deal->min_qty }}</span>
        <span style="color:#9ca3af">Until {{ $deal->ends_at->format('M d') }}</span>
    </div>
    @empty
    <p style="color:#9ca3af;font-size:13px">No active deals.</p>
    @endforelse
</div>
</div>

{{-- Specs --}}
@if($product->specs)
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-top:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444">Specifications</h3>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
        @foreach($product->specs as $spec)
        <div style="background:#f3f4f6;padding:6px 12px;border-radius:6px;font-size:12px">
            <span style="color:#6b7280">{{ $spec['key'] ?? '' }}:</span>
            <span style="color:#1B1444;font-weight:500;margin-left:4px">{{ $spec['value'] ?? '' }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif
</div>
@endsection
