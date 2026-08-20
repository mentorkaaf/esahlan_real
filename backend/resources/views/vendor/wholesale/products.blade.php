@extends('vendor.layouts.app')
@section('title', 'My Products')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-boxes-stacked" style="color:var(--brand)"></i> My Products</h1>
        <ul class="breadcrumb"><li>eSahlan</li><li>eWholesale</li><li>Products</li></ul>
    </div>
</div>

@if(session('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif

<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
        <form method="GET" style="display:flex;gap:8px;">
            <input type="text" name="q" value="{{ $q }}" placeholder="Search products..." class="form-input" style="width:220px;">
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
            @if($q)<a href="{{ route('vendor.wholesale.products') }}" class="btn btn-sm btn-outline">Clear</a>@endif
        </form>
        <div style="color:var(--text-muted);font-size:13px;">
            Products are managed by admin. Contact support to add or edit products.
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Price</th>
                    <th>MOQ</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                @php
                    $prices = $product->prices ?? [];
                    $minPrice = collect($prices)->pluck('unit_price')->min();
                @endphp
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            @php $imgs = $product->images ?? []; @endphp
                            @if(count($imgs) > 0)
                            <img src="/api/v1/media?f={{ urlencode($imgs[0]) }}" width="40" height="40" style="object-fit:cover;border-radius:6px;border:1px solid var(--border);">
                            @else
                            <div style="width:40px;height:40px;background:var(--surface);border-radius:6px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);">
                                <i class="fas fa-image"></i>
                            </div>
                            @endif
                            <div>
                                <div style="font-weight:600;">{{ $product->name }}</div>
                                <div style="font-size:12px;color:var(--text-muted);">{{ $product->category?->name }}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $product->sku ?? '-' }}</td>
                    <td>{{ $minPrice ? '$'.number_format($minPrice, 2) : '-' }}</td>
                    <td>{{ $product->moq }} {{ $product->unit }}</td>
                    <td>{{ $product->stock_qty ?? '∞' }}</td>
                    <td>
                        <span class="badge badge-{{ $product->is_active ? 'green' : 'gray' }}">
                            {{ $product->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('vendor.wholesale.products.toggle', $product->id) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-xs btn-outline">
                                {{ $product->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted">No products found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($products->hasPages())
    <div class="card-footer">{{ $products->withQueryString()->links() }}</div>
    @endif
</div>

@endsection
