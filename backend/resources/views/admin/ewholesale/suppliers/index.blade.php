@extends('admin.layouts.app')
@section('title', 'eWholesale — Suppliers')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <h2 style="margin:0;font-size:20px;font-weight:700;color:#1B1444">Suppliers</h2>
    <div style="display:flex;gap:8px;align-items:center;">
        <button id="bulkDelBtn" onclick="bulkDeleteSuppliers()" style="display:none;padding:8px 14px;background:#ef4444;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">
            🗑 Delete Selected (<span id="selCount">0</span>)
        </button>
        <a href="{{ route('admin.module-data.wholesale.suppliers.create') }}"
           style="padding:9px 18px;background:#1B1444;color:#fff;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none">+ Add Supplier</a>
    </div>
</div>

{{-- Filters --}}
<form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
    <input name="search" value="{{ request('search') }}" placeholder="Search name..." style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:200px">
    <select name="verification" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
        <option value="">All verifications</option>
        @foreach(['unverified','pending','verified','gold'] as $v)
        <option value="{{ $v }}" @selected(request('verification')===$v)>{{ ucfirst($v) }}</option>
        @endforeach
    </select>
    <button style="padding:8px 16px;background:#F7941D;color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer">Filter</button>
    <a href="{{ route('admin.module-data.wholesale.suppliers') }}" style="padding:8px 16px;background:#6b7280;color:#fff;border-radius:6px;font-size:13px;text-decoration:none">Reset</a>
</form>

@if(session('success'))<div style="background:#d1fae5;color:#065f46;padding:10px 16px;border-radius:6px;margin-bottom:16px">{{ session('success') }}</div>@endif

<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead><tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
    <th style="padding:12px 10px;width:36px;"><input type="checkbox" id="checkAll" onchange="toggleAll(this)" style="cursor:pointer;width:15px;height:15px;"></th>
    <th style="padding:12px 16px;text-align:left;color:#374151;font-weight:600">Supplier</th>
    <th style="padding:12px 16px;text-align:left;color:#374151;font-weight:600">Vendor</th>
    <th style="padding:12px 16px;text-align:center;color:#374151;font-weight:600">Verification</th>
    <th style="padding:12px 16px;text-align:center;color:#374151;font-weight:600">Products</th>
    <th style="padding:12px 16px;text-align:center;color:#374151;font-weight:600">Active</th>
    <th style="padding:12px 16px;text-align:left;color:#374151;font-weight:600">Joined</th>
    <th style="padding:12px 16px;text-align:center;color:#374151;font-weight:600">Actions</th>
</tr></thead>
<tbody>
@forelse($suppliers as $s)
<tr style="border-bottom:1px solid #f3f4f6;transition:background .1s" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background=''">
    <td style="padding:12px 10px;"><input type="checkbox" class="row-check" value="{{ $s->id }}" onchange="updateBulkBtn()" style="cursor:pointer;width:15px;height:15px;"></td>
    <td style="padding:12px 16px">
        <a href="{{ route('admin.module-data.wholesale.suppliers.show', $s) }}" style="font-weight:600;color:#1B1444;text-decoration:none">{{ $s->display_name }}</a>
        @if($s->rating) <span style="font-size:11px;color:#f59e0b;margin-left:4px">★ {{ number_format($s->rating,1) }}</span> @endif
    </td>
    <td style="padding:12px 16px;color:#6b7280">{{ $s->vendor?->name ?? '—' }}</td>
    <td style="padding:12px 16px;text-align:center">
        @php $vc = match($s->verification){
            'gold'=>['#fef3c7','#92400e','🥇'],
            'verified'=>['#d1fae5','#065f46','✅'],
            'pending'=>['#fffbeb','#92400e','⏳'],
            default=>['#f3f4f6','#6b7280','—']
        }; @endphp
        <span style="background:{{ $vc[0] }};color:{{ $vc[1] }};padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600">{{ $vc[2] }} {{ ucfirst($s->verification) }}</span>
    </td>
    <td style="padding:12px 16px;text-align:center;font-weight:600">{{ $s->products_count }}</td>
    <td style="padding:12px 16px;text-align:center">
        <span style="width:8px;height:8px;border-radius:50%;background:{{ $s->is_active?'#10b981':'#ef4444' }};display:inline-block"></span>
    </td>
    <td style="padding:12px 16px;color:#6b7280">{{ $s->created_at->format('M d, Y') }}</td>
    <td style="padding:12px 16px;text-align:center;white-space:nowrap">
        <a href="{{ route('admin.module-data.wholesale.suppliers.show', $s) }}" style="padding:5px 10px;background:#1B1444;color:#fff;border-radius:5px;font-size:12px;text-decoration:none;margin-right:4px">View</a>
        <form method="POST" action="{{ route('admin.module-data.wholesale.suppliers.delete', $s) }}" style="display:inline" onsubmit="return confirm('Delete {{ addslashes($s->display_name) }} and all its products?')">
            @csrf @method('DELETE')
            <button type="submit" style="padding:5px 10px;background:#ef4444;color:#fff;border:none;border-radius:5px;font-size:12px;cursor:pointer;">Delete</button>
        </form>
    </td>
</tr>
@empty
<tr><td colspan="8" style="padding:32px;text-align:center;color:#9ca3af">No suppliers found.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>

<div style="margin-top:16px">{{ $suppliers->links() }}</div>
</div>

<form id="bulkDelForm" method="POST" action="{{ route('admin.module-data.wholesale.suppliers.bulk-delete') }}" style="display:none">
    @csrf @method('DELETE')
    <div id="bulkDelInputs"></div>
</form>

@push('scripts')
<script>
const CSRF_S = '{{ csrf_token() }}';
function toggleAll(cb) {
    document.querySelectorAll('.row-check').forEach(c => { c.checked = cb.checked; });
    updateBulkBtn();
}
function updateBulkBtn() {
    const checked = document.querySelectorAll('.row-check:checked');
    const btn = document.getElementById('bulkDelBtn');
    document.getElementById('selCount').textContent = checked.length;
    btn.style.display = checked.length > 0 ? 'inline-block' : 'none';
    document.getElementById('checkAll').indeterminate = checked.length > 0 && checked.length < document.querySelectorAll('.row-check').length;
}
function bulkDeleteSuppliers() {
    const ids = [...document.querySelectorAll('.row-check:checked')].map(c => c.value);
    if (!ids.length) return;
    if (!confirm(`Delete ${ids.length} supplier(s) and all their products? This cannot be undone.`)) return;
    const form = document.getElementById('bulkDelForm');
    const inp = document.getElementById('bulkDelInputs');
    inp.innerHTML = ids.map(id => `<input type="hidden" name="ids[]" value="${id}">`).join('');
    form.submit();
}
</script>
@endpush
@endsection
