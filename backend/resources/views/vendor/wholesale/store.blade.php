@extends('vendor.layouts.app')
@section('title', 'Store Profile')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-building" style="color:var(--brand)"></i> Store Profile</h1>
        <ul class="breadcrumb"><li>eSahlan</li><li>eWholesale</li><li>Store Profile</li></ul>
    </div>
</div>

@if(session('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif

<div class="card" style="max-width:720px;">
    <div class="card-header"><h2 class="card-title">Supplier Information</h2></div>
    <div class="card-body">
        <form method="POST" action="{{ route('vendor.wholesale.store.update') }}" enctype="multipart/form-data">
            @csrf

            {{-- Logo --}}
            <div style="margin-bottom:20px;">
                <label class="form-label">Store Logo</label>
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:8px;">
                    @if($supplier->logo)
                    <img src="/api/v1/media?f={{ urlencode($supplier->logo) }}" width="72" height="72" style="object-fit:cover;border-radius:12px;border:1px solid var(--border);">
                    @else
                    <div style="width:72px;height:72px;background:var(--surface);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:24px;border:1px solid var(--border);">
                        <i class="fas fa-building"></i>
                    </div>
                    @endif
                    <input type="file" name="logo" accept="image/*" class="form-input" style="flex:1;">
                </div>
                <small style="color:var(--text-muted);">Recommended: 200×200px, JPG or PNG</small>
            </div>

            {{-- Display Name --}}
            <div style="margin-bottom:16px;">
                <label class="form-label">Display Name <span style="color:red;">*</span></label>
                <input type="text" name="display_name" value="{{ old('display_name', $supplier->display_name) }}" class="form-input" required>
            </div>

            {{-- Tagline --}}
            <div style="margin-bottom:16px;">
                <label class="form-label">Tagline</label>
                <input type="text" name="tagline" value="{{ old('tagline', $supplier->tagline) }}" class="form-input" placeholder="Short description (shown in app)">
            </div>

            {{-- Description --}}
            <div style="margin-bottom:16px;">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-input" rows="4" placeholder="About your business...">{{ old('description', $supplier->description) }}</textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <div>
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Min Order Value ($)</label>
                    <input type="number" name="min_order_value" value="{{ old('min_order_value', $supplier->min_order_value) }}" class="form-input" step="0.01" min="0">
                </div>
            </div>

            {{-- Address --}}
            <div style="margin-bottom:24px;">
                <label class="form-label">Business Address</label>
                <input type="text" name="address" value="{{ old('address', $supplier->address) }}" class="form-input">
            </div>

            {{-- Read-only info --}}
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:14px 18px;margin-bottom:24px;font-size:13px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div><span style="color:var(--text-muted);">Verification:</span> <strong>{{ ucfirst($supplier->verification ?? 'unverified') }}</strong></div>
                    <div><span style="color:var(--text-muted);">Rating:</span> <strong>{{ number_format($supplier->rating ?? 0, 1) }} ★</strong></div>
                    <div><span style="color:var(--text-muted);">Total Orders:</span> <strong>{{ $supplier->total_orders ?? 0 }}</strong></div>
                    <div><span style="color:var(--text-muted);">Member Since:</span> <strong>{{ $supplier->created_at->format('M Y') }}</strong></div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

@endsection
