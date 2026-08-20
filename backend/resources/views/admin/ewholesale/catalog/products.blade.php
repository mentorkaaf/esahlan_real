@extends('admin.layouts.app')
@section('title', 'eWholesale — Products')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h2 style="margin:0;font-size:20px;font-weight:700;color:#1B1444">Products</h2>
    <div style="display:flex;gap:8px">
        <a href="{{ route('admin.module-data.wholesale.catalog.categories') }}" style="padding:8px 14px;background:#6b7280;color:#fff;border-radius:6px;font-size:13px;text-decoration:none">🗂 Categories</a>
        <a href="{{ route('admin.module-data.wholesale.products.create') }}" style="padding:8px 16px;background:#F7941D;color:#fff;border-radius:6px;font-size:13px;text-decoration:none">+ Add Product</a>
    </div>
</div>

{{-- Status tabs --}}
<div style="display:flex;gap:4px;margin-bottom:16px">
    @foreach([null,'pending_review','active','rejected','archived'] as $s)
    <a href="{{ route('admin.module-data.wholesale.products', array_merge(request()->except('status','page'), $s ? ['status'=>$s] : [])) }}"
       style="padding:6px 14px;border-radius:6px;font-size:12px;font-weight:500;text-decoration:none;
           background:{{ request('status')===$s ? '#1B1444' : '#f3f4f6' }};
           color:{{ request('status')===$s ? '#fff' : '#374151' }}">
        {{ $s ? ucfirst(str_replace('_',' ',$s)) : 'All' }}
    </a>
    @endforeach
</div>

<form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
    @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
    <input name="search" value="{{ request('search') }}" placeholder="Search products..." style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:180px">
    <select name="supplier_id" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
        <option value="">All Suppliers</option>
        @foreach($suppliers as $s)
        <option value="{{ $s->id }}" @selected(request('supplier_id')==$s->id)>{{ $s->display_name }}</option>
        @endforeach
    </select>
    <select name="category_id" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
        <option value="">All Categories</option>
        @foreach($categories->whereNull('parent_id') as $cat)
        <option value="{{ $cat->id }}" @selected(request('category_id')==$cat->id)>{{ $cat->name }}</option>
        @foreach($categories->where('parent_id',$cat->id) as $sub)
        <option value="{{ $sub->id }}" @selected(request('category_id')==$sub->id)>&nbsp;&nbsp;└ {{ $sub->name }}</option>
        @endforeach
        @endforeach
    </select>
    <button style="padding:8px 16px;background:#F7941D;color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer">Filter</button>
</form>

@if(session('success'))<div style="background:#d1fae5;color:#065f46;padding:10px 16px;border-radius:6px;margin-bottom:16px">{{ session('success') }}</div>@endif

<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead><tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Product</th>
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Supplier</th>
    <th style="padding:10px 14px;text-align:left;color:#374151;font-weight:600">Category</th>
    <th style="padding:10px 14px;text-align:right;color:#374151;font-weight:600">MOQ</th>
    <th style="padding:10px 14px;text-align:right;color:#374151;font-weight:600">Price Range</th>
    <th style="padding:10px 14px;text-align:center;color:#374151;font-weight:600">Status</th>
    <th style="padding:10px 14px;text-align:center;color:#374151;font-weight:600">Actions</th>
</tr></thead>
<tbody>
@forelse($products as $p)
<tr style="border-bottom:1px solid #f3f4f6" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background=''">
    <td style="padding:10px 14px">
        <div style="font-weight:600;color:#1B1444">{{ $p->name }}</div>
        @if($p->name_so) <div style="font-size:11px;color:#9ca3af">{{ $p->name_so }}</div> @endif
        @if($p->is_featured) <span style="font-size:10px;background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:8px">⭐ Featured</span> @endif
    </td>
    <td style="padding:10px 14px;color:#6b7280">{{ $p->supplier?->display_name }}</td>
    <td style="padding:10px 14px;color:#6b7280">{{ $p->category?->name }}</td>
    <td style="padding:10px 14px;text-align:right">{{ $p->moq }} {{ $p->unit }}</td>
    <td style="padding:10px 14px;text-align:right;color:#10b981;font-weight:600">${{ $p->min_price }} – ${{ $p->max_price }}</td>
    <td style="padding:10px 14px;text-align:center">
        @php $sc = match($p->status){ 'active'=>['#d1fae5','#065f46'], 'pending_review'=>['#fffbeb','#92400e'], 'rejected'=>['#fee2e2','#991b1b'], default=>['#f3f4f6','#6b7280'] }; @endphp
        <form method="POST" action="{{ route('admin.module-data.wholesale.products.status', $p) }}">
            @csrf @method('PATCH')
            <select name="status" onchange="this.form.submit()" style="padding:3px 8px;border:1px solid #d1d5db;border-radius:10px;font-size:11px;background:{{ $sc[0] }};color:{{ $sc[1] }};font-weight:600">
                @foreach(['draft','pending_review','active','rejected','archived'] as $st)
                <option value="{{ $st }}" @selected($p->status===$st)>{{ ucfirst(str_replace('_',' ',$st)) }}</option>
                @endforeach
            </select>
        </form>
    </td>
    <td style="padding:10px 14px;text-align:center;white-space:nowrap">
        <a href="{{ route('admin.module-data.wholesale.products.show', $p) }}" style="padding:4px 10px;background:#1B1444;color:#fff;border-radius:4px;font-size:11px;text-decoration:none;margin-right:4px">View</a>
        <a href="{{ route('admin.module-data.wholesale.products.edit', $p) }}" style="padding:4px 10px;background:#F7941D;color:#fff;border-radius:4px;font-size:11px;text-decoration:none">Edit</a>
    </td>
</tr>
@empty
<tr><td colspan="7" style="padding:32px;text-align:center;color:#9ca3af">No products found.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
<div style="margin-top:16px">{{ $products->links() }}</div>
</div>
@endsection
