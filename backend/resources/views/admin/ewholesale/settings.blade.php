@extends('admin.layouts.app')
@section('title', 'eWholesale — Settings')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<h2 style="margin:0 0 24px;font-size:20px;font-weight:700;color:#1B1444">Settings</h2>

@if(session('success'))<div style="background:#d1fae5;color:#065f46;padding:10px 16px;border-radius:6px;margin-bottom:16px">{{ session('success') }}</div>@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

{{-- Platform Settings --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 16px;font-size:14px;font-weight:600;color:#1B1444;border-bottom:1px solid #f3f4f6;padding-bottom:10px">Platform Settings</h3>
    <form method="POST" action="{{ route('admin.module-data.wholesale.settings.update') }}">
        @csrf
        @foreach($settings as $s)
        <div style="margin-bottom:14px">
            <label style="font-size:12px;font-weight:500;color:#374151;display:block;margin-bottom:4px">{{ $s->label ?? $s->key }}</label>
            @if($s->type === 'boolean')
            <select name="settings[{{ $s->key }}]" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
                <option value="1" @selected($s->value)>Yes</option>
                <option value="0" @selected(!$s->value)>No</option>
            </select>
            @else
            <input name="settings[{{ $s->key }}]" value="{{ $s->value }}" type="{{ in_array($s->type,['integer','decimal'])?'number':'text' }}" step="any"
                style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;box-sizing:border-box">
            @endif
        </div>
        @endforeach
        <button style="padding:9px 24px;background:#F7941D;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer">Save Settings</button>
    </form>
</div>

<div style="display:flex;flex-direction:column;gap:16px">

{{-- Platform Shipping --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444;border-bottom:1px solid #f3f4f6;padding-bottom:10px">Platform Shipping Rule</h3>
    <form method="POST" action="{{ route('admin.module-data.wholesale.settings.platform-shipping') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:12px">
            <div>
                <label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Basis</label>
                <select name="basis" style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:12px">
                    @foreach(['flat_by_zone','per_carton','per_kg','per_cbm'] as $b)
                    <option value="{{ $b }}" @selected(($platformRule?->basis??'flat_by_zone')===$b)>{{ $b }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Rate $</label>
                <input name="rate" type="number" step="0.01" value="{{ $platformRule?->rate ?? 3.00 }}" style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;box-sizing:border-box">
            </div>
            <div>
                <label style="font-size:11px;color:#6b7280;display:block;margin-bottom:3px">Free over $</label>
                <input name="free_over" type="number" step="0.01" value="{{ $platformRule?->free_over ?? 500 }}" style="width:100%;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;box-sizing:border-box">
            </div>
        </div>
        <button style="padding:7px 18px;background:#1B1444;color:#fff;border:none;border-radius:5px;font-size:13px;cursor:pointer">Save Shipping</button>
    </form>
</div>

{{-- Price Lists --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444;border-bottom:1px solid #f3f4f6;padding-bottom:10px">Buyer Price Lists</h3>

    @foreach($priceLists as $pl)
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:#f9fafb;border-radius:6px;margin-bottom:6px;font-size:13px">
        <span style="font-weight:500;color:#1B1444">{{ $pl->name }}</span>
        <span style="color:#10b981;font-weight:600">{{ $pl->discount_percent }}% off</span>
    </div>
    @endforeach

    <form method="POST" action="{{ route('admin.module-data.wholesale.settings.price-list.store') }}" style="display:flex;gap:8px;margin-top:12px;align-items:flex-end">
        @csrf
        <input name="name" placeholder="Price list name" required style="flex:1;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:12px">
        <input name="discount_percent" type="number" step="0.01" min="0" max="100" placeholder="%" required style="width:60px;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:12px">
        <button style="padding:7px 14px;background:#F7941D;color:#fff;border:none;border-radius:5px;font-size:12px;cursor:pointer">Add</button>
    </form>
</div>

</div>
</div>
</div>
@endsection
