@extends('vendor.layouts.app')
@section('title', 'Store Profile')
@section('content')
<style>
.form-group { margin-bottom:16px; }
.form-group label { display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:6px; }
.form-control { border:1.5px solid #e5e7eb;border-radius:8px;padding:9px 12px;font-size:13px;width:100%;outline:none; }
.form-control:focus { border-color:#FF8A00;box-shadow:0 0 0 3px rgba(255,138,0,.1); }
.form-row { display:grid;grid-template-columns:1fr 1fr;gap:16px; }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">Store Profile</h1>
        <ul class="breadcrumb"><li><a href="{{ route('vendor.eshop.dashboard') }}">Dashboard</a></li><li>Store</li></ul>
    </div>
</div>

@if(session('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;">
<div class="card" style="padding:24px;">
    <h3 style="font-size:16px;font-weight:800;margin-bottom:20px;color:var(--navy)">Store Information</h3>
    <form method="POST" action="{{ route('vendor.eshop.store.update') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label>Store Name *</label>
            <input name="name" class="form-control" value="{{ $vendor->name }}" required>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" class="form-control" rows="3">{{ $vendor->description }}</textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Phone</label>
                <input name="phone" class="form-control" value="{{ $vendor->phone }}">
            </div>
            <div class="form-group">
                <label>Address</label>
                <input name="address" class="form-control" value="{{ $vendor->address }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Delivery Fee ($)</label>
                <input name="delivery_fee" type="number" step="0.01" class="form-control" value="{{ $vendor->delivery_fee ?? 0 }}">
            </div>
            <div class="form-group">
                <label>Delivery Time</label>
                <input name="delivery_time" class="form-control" value="{{ $vendor->delivery_time }}" placeholder="e.g. 30-45 min">
            </div>
        </div>
        <div class="form-group">
            <label>Minimum Order ($)</label>
            <input name="minimum_order" type="number" step="0.01" class="form-control" value="{{ $vendor->minimum_order ?? 0 }}" style="max-width:200px;">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Store Logo</label>
                <input type="file" name="logo_file" accept="image/*" class="form-control">
            </div>
            <div class="form-group">
                <label>Cover Image</label>
                <input type="file" name="cover_file" accept="image/*" class="form-control">
            </div>
        </div>
        <button type="submit" class="btn btn-brand">Save Profile</button>
    </form>
</div>

<div>
    {{-- Store Preview --}}
    <div class="card" style="overflow:hidden;margin-bottom:16px;">
        @if($vendor->cover_image)
        <img src="{{ cdn_url($vendor->cover_image) }}" style="width:100%;height:120px;object-fit:cover;">
        @else
        <div style="width:100%;height:120px;background:linear-gradient(135deg,#FF8A00,#ff4e00);display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-store" style="font-size:40px;color:rgba(255,255,255,.5)"></i>
        </div>
        @endif
        <div style="padding:16px;text-align:center;margin-top:-24px;">
            <div style="width:60px;height:60px;border-radius:50%;overflow:hidden;border:3px solid #fff;margin:0 auto;background:#f3f4f6;">
                @if($vendor->logo)
                <img src="{{ cdn_url($vendor->logo) }}" style="width:100%;height:100%;object-fit:cover;">
                @else
                <div style="width:100%;height:100%;background:#eef2ff;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-store" style="color:#6366f1;font-size:22px;"></i>
                </div>
                @endif
            </div>
            <h4 style="font-weight:800;margin-top:10px;font-size:15px;">{{ $vendor->name }}</h4>
            <p style="color:#888;font-size:12px;margin-top:4px;">{{ $vendor->description ?? 'No description set.' }}</p>
            <div style="display:flex;gap:8px;justify-content:center;margin-top:10px;font-size:12px;">
                @if($vendor->delivery_fee)<span style="background:#f3f4f6;padding:3px 10px;border-radius:20px;">Delivery: ${{ $vendor->delivery_fee }}</span>@endif
                @if($vendor->delivery_time)<span style="background:#f3f4f6;padding:3px 10px;border-radius:20px;">{{ $vendor->delivery_time }}</span>@endif
            </div>
        </div>
    </div>

    {{-- Status --}}
    <div class="card" style="padding:16px;">
        <h4 style="font-weight:700;font-size:13px;margin-bottom:12px;">Store Status</h4>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
            <div style="width:10px;height:10px;border-radius:50%;background:{{ $vendor->is_approved?'#22c55e':'#f59e0b' }};"></div>
            <span style="font-size:13px;font-weight:600">{{ $vendor->is_approved ? 'Approved & Live' : 'Pending Approval' }}</span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:10px;height:10px;border-radius:50%;background:{{ $vendor->is_open?'#22c55e':'#aaa' }};"></div>
            <span style="font-size:13px;font-weight:600">{{ $vendor->is_open ? 'Open' : 'Closed' }}</span>
        </div>
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid #f0f0f0;font-size:12px;color:#888;">
            Commission: <strong style="color:var(--navy)">
                {{ $vendor->commission_type === 'percentage' ? $vendor->commission_value.'%' : '$'.$vendor->commission_value.' fixed' }}
            </strong> per order
        </div>
        <div style="font-size:12px;color:#888;margin-top:6px;">
            Rating: <strong style="color:#f59e0b">★ {{ number_format($vendor->rating,1) }}</strong> ({{ $vendor->review_count }} reviews)
        </div>
    </div>
</div>
</div>

@endsection
