@extends('admin.layouts.app')
@section('title', $supplier ? 'Edit Supplier' : 'New Supplier')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px;max-width:680px">
<div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
    <a href="{{ route('admin.module-data.wholesale.suppliers') }}" style="color:#6b7280;text-decoration:none;font-size:13px">← Suppliers</a>
    <h2 style="margin:0;font-size:20px;font-weight:700;color:#1B1444">{{ $supplier ? 'Edit Supplier' : 'Add Supplier' }}</h2>
</div>

@if($errors->any())
<div style="background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:6px;margin-bottom:16px">
    <ul style="margin:0;padding-left:16px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="POST"
      action="{{ $supplier ? route('admin.module-data.wholesale.suppliers.update', $supplier) : route('admin.module-data.wholesale.suppliers.store') }}"
      enctype="multipart/form-data"
      style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:24px">
    @csrf
    @if($supplier) @method('PUT') @endif

    {{-- Vendor link (only on create) --}}
    @if(!$supplier)
    <div style="margin-bottom:16px">
        <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">
            Link to Vendor Account <span style="color:#ef4444">*</span>
        </label>
        <select name="vendor_id" required style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
            <option value="">— Select vendor —</option>
            @foreach($vendors as $v)
                <option value="{{ $v->id }}" {{ old('vendor_id') == $v->id ? 'selected' : '' }}>{{ $v->name }} (#{{ $v->id }})</option>
            @endforeach
        </select>
        <p style="font-size:11px;color:#9ca3af;margin:4px 0 0">The vendor who will manage this wholesale store.</p>
    </div>
    @else
    <div style="margin-bottom:16px;padding:10px 14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;font-size:13px">
        <span style="color:#6b7280">Vendor: </span>
        <strong>{{ $supplier->vendor?->name ?? '—' }}</strong>
        <span style="color:#9ca3af;font-size:11px">(cannot change after creation)</span>
    </div>
    @endif

    {{-- Logo --}}
    <div style="margin-bottom:20px">
        <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">Logo</label>
        @if($supplier?->logo)
            <img src="/storage/{{ $supplier->logo }}" style="width:80px;height:80px;object-fit:cover;border-radius:8px;margin-bottom:8px;border:1px solid #e5e7eb">
            <p style="font-size:11px;color:#9ca3af;margin:0 0 6px">Upload a new logo to replace.</p>
        @endif
        <input type="file" name="logo" accept="image/*" style="font-size:13px;color:#374151">
        <p style="font-size:11px;color:#9ca3af;margin:4px 0 0">Square image, min 200×200px, max 2MB.</p>
    </div>

    {{-- Display name --}}
    <div style="margin-bottom:16px">
        <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">Display Name <span style="color:#ef4444">*</span></label>
        <input type="text" name="display_name" value="{{ old('display_name', $supplier?->display_name) }}" required
               style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px"
               placeholder="e.g. Banadir Import & Export">
    </div>

    {{-- About --}}
    <div style="margin-bottom:16px">
        <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">About / Business Description</label>
        <textarea name="about" rows="3"
                  style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px"
                  placeholder="Brief description of the supplier's business, products, and expertise...">{{ old('about', $supplier?->about) }}</textarea>
    </div>

    {{-- Warehouse address --}}
    <div style="margin-bottom:16px">
        <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">Warehouse / Main Address</label>
        <input type="text" name="warehouse_address" value="{{ old('warehouse_address', $supplier?->warehouse_address) }}"
               style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px"
               placeholder="e.g. Hamarweyne, Mogadishu">
    </div>

    {{-- Verification + Fee --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px">
        <div>
            <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">Verification Status</label>
            <select name="verification" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
                @foreach(['unverified'=>'Unverified','pending'=>'Pending Review','verified'=>'Verified ✓','gold'=>'Gold ★'] as $val => $label)
                    <option value="{{ $val }}" {{ old('verification', $supplier?->verification ?? 'unverified') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if($supplier)
        <div>
            <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px">Platform Fee Override (%)</label>
            <input type="number" name="platform_fee_percent" value="{{ old('platform_fee_percent', $supplier?->platform_fee_percent) }}"
                   min="0" max="100" step="0.1"
                   style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px"
                   placeholder="Leave empty to use global setting">
        </div>
        @endif
    </div>

    <div style="display:flex;gap:10px">
        <button type="submit" style="padding:10px 24px;background:#1B1444;color:#fff;border:none;border-radius:7px;font-size:14px;font-weight:600;cursor:pointer">
            {{ $supplier ? 'Save Changes' : 'Create Supplier' }}
        </button>
        <a href="{{ route('admin.module-data.wholesale.suppliers') }}"
           style="padding:10px 20px;border:1px solid #d1d5db;border-radius:7px;font-size:14px;color:#374151;text-decoration:none">Cancel</a>
    </div>
</form>
</div>
@endsection
