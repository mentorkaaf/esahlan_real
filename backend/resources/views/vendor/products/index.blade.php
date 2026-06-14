@extends('vendor.layouts.app')
@section('title', 'Products')

@push('topbar-actions')
<a href="{{ route('vendor.products.create') }}" class="btn btn-primary btn-sm">
    <i class="fa-solid fa-plus"></i> Add Product
</a>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Products</h1>
        <ul class="breadcrumb"><li><a href="{{ route('vendor.dashboard') }}">Dashboard</a></li><li>Products</li></ul>
    </div>
    <a href="{{ route('vendor.products.create') }}" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i> Add Product
    </a>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;width:100%;">
            <input type="text" name="search" class="filter-input" placeholder="Search products..." value="{{ request('search') }}">
            <select name="category_id" class="filter-select" onchange="this.form.submit()">
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ request('category_id')==$cat->id?'selected':'' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            @if(request()->anyFilled(['search','category_id']))
            <a href="{{ route('vendor.products.index') }}" class="btn btn-outline btn-sm">Clear</a>
            @endif
        </form>
    </div>

    @if($products->isEmpty())
    <div class="empty-state">
        <i class="fa-solid fa-box-open"></i>
        <p>No products yet. <a href="{{ route('vendor.products.create') }}" style="color:var(--brand);">Add your first product</a></p>
    </div>
    @else
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Available</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $product)
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            @if($product->thumbnail)
                            <img src="{{ asset('storage/'.$product->thumbnail) }}" alt="" style="width:40px;height:40px;border-radius:9px;object-fit:cover;border:1px solid var(--border);">
                            @else
                            <div style="width:40px;height:40px;border-radius:9px;background:var(--bg);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:16px;border:1px solid var(--border);"><i class="fa-solid fa-image"></i></div>
                            @endif
                            <div>
                                <div style="font-weight:700;">{{ $product->name }}</div>
                                @if($product->sku)<div style="font-size:11px;color:var(--text-muted);">SKU: {{ $product->sku }}</div>@endif
                            </div>
                        </div>
                    </td>
                    <td>{{ $product->category?->name ?? '—' }}</td>
                    <td>
                        <div style="font-weight:700;">${{ number_format($product->price,2) }}</div>
                        @if($product->sale_price)
                        <div style="font-size:11px;color:var(--success);">Sale: ${{ number_format($product->sale_price,2) }}</div>
                        @endif
                    </td>
                    <td>{{ $product->stock_quantity ?? '∞' }}</td>
                    <td>
                        <form action="{{ route('vendor.products.toggle',$product) }}" method="POST" style="margin:0;">
                            @csrf
                            <label class="toggle" title="{{ $product->is_available ? 'Click to hide' : 'Click to show' }}">
                                <input type="checkbox" {{ $product->is_available ? 'checked' : '' }} onchange="this.form.submit()">
                                <span class="toggle-slider"></span>
                            </label>
                        </form>
                    </td>
                    <td>
                        <div style="display:flex;gap:5px;">
                            <a href="{{ route('vendor.products.edit',$product) }}" class="btn btn-outline btn-xs"><i class="fa-solid fa-pen"></i></a>
                            <form action="{{ route('vendor.products.destroy',$product) }}" method="POST" style="margin:0;" onsubmit="return confirm('Delete this product?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-xs"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap">
        <div class="pagination-info">Showing {{ $products->firstItem() }}–{{ $products->lastItem() }} of {{ $products->total() }}</div>
        {{ $products->links('vendor.partials.pagination') }}
    </div>
    @endif
</div>
@endsection
