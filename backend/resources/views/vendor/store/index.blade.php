@extends('vendor.layouts.app')
@section('title', 'Store Profile')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Store Profile</h1>
        <ul class="breadcrumb"><li><a href="{{ route('vendor.dashboard') }}">Dashboard</a></li><li>Store Profile</li></ul>
    </div>
    <form action="{{ route('vendor.store.toggle-open') }}" method="POST" style="margin:0;">
        @csrf
        <button type="submit" class="btn {{ $vendor->temporarily_closed ? 'btn-success' : 'btn-danger' }}">
            <i class="fa-solid {{ $vendor->temporarily_closed ? 'fa-store' : 'fa-store-slash' }}"></i>
            {{ $vendor->temporarily_closed ? 'Open Store' : 'Temporarily Close' }}
        </button>
    </form>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start;">
    {{-- Profile form --}}
    <form action="{{ route('vendor.store.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="card">
            <div class="card-header">
                <div class="card-header-title"><div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);"><i class="fa-solid fa-store"></i></div> Store Information</div>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Store Name <span style="color:var(--danger);">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name',$vendor->name) }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description',$vendor->description) }}</textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone',$vendor->phone) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email',$vendor->email) }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address',$vendor->address) }}">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Minimum Order ($)</label>
                        <input type="number" name="minimum_order" class="form-control" value="{{ old('minimum_order',$vendor->minimum_order) }}" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Delivery Fee ($)</label>
                        <input type="number" name="delivery_fee" class="form-control" value="{{ old('delivery_fee',$vendor->delivery_fee) }}" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Delivery Time</label>
                        <input type="text" name="delivery_time" class="form-control" value="{{ old('delivery_time',$vendor->delivery_time) }}" placeholder="e.g. 30-45 mins">
                    </div>
                </div>
            </div>
            <div class="card-footer" style="display:flex;justify-content:flex-end;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
            </div>
        </div>
    </form>

    <div>
        {{-- Logo & Cover --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-title"><div class="card-header-icon" style="background:rgba(139,92,246,0.1);color:var(--purple);"><i class="fa-solid fa-image"></i></div> Images</div>
            </div>
            <div class="card-body">
                <form action="{{ route('vendor.store.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    {{-- Keep other fields with current values to avoid wiping them --}}
                    <input type="hidden" name="name" value="{{ $vendor->name }}">
                    <div class="form-group">
                        <label class="form-label">Logo</label>
                        @if($vendor->logo_url)
                        <img src="{{ $vendor->logo_url }}" style="width:70px;height:70px;border-radius:12px;object-fit:cover;margin-bottom:10px;display:block;border:2px solid var(--border);">
                        @endif
                        <input type="file" name="logo" class="form-control" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cover Image</label>
                        @if($vendor->cover_image_url)
                        <img src="{{ $vendor->cover_image_url }}" style="width:100%;height:80px;border-radius:9px;object-fit:cover;margin-bottom:10px;display:block;border:1px solid var(--border);">
                        @endif
                        <input type="file" name="cover_image" class="form-control" accept="image/*">
                    </div>
                    <button type="submit" class="btn btn-outline btn-sm" style="width:100%;"><i class="fa-solid fa-upload"></i> Upload Images</button>
                </form>
            </div>
        </div>

        {{-- Status --}}
        <div class="card">
            <div class="card-body">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                    <div>
                        <div style="font-weight:700;font-size:14px;">Store Status</div>
                        <div style="font-size:12px;color:var(--text-muted);">Is your store accepting orders?</div>
                    </div>
                    <span class="{{ $vendor->temporarily_closed ? 'store-status closed' : 'store-status open' }}">
                        <span class="store-status-dot"></span>
                        {{ $vendor->temporarily_closed ? 'Closed' : 'Open' }}
                    </span>
                </div>
                <div style="font-size:12.5px;color:var(--text-muted);">
                    Module: <strong>{{ $vendor->module?->name ?? ucfirst($vendor->module_slug ?? '—') }}</strong><br>
                    Rating: <strong>{{ round($vendor->rating??0,1) }} ⭐</strong> ({{ $vendor->review_count ?? 0 }} reviews)
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Schedule --}}
<div class="card">
    <div class="card-header">
        <div class="card-header-title"><div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);"><i class="fa-solid fa-calendar-week"></i></div> Working Hours</div>
    </div>
    <form action="{{ route('vendor.store.schedule') }}" method="POST">
        @csrf
        <div class="card-body">
            @php
            $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            @endphp
            <div style="display:flex;flex-direction:column;gap:12px;">
                @foreach($days as $dayNum => $dayName)
                @php $sch = $schedules->get($dayNum); @endphp
                <div style="display:flex;align-items:center;gap:16px;padding:12px 14px;border:1.5px solid var(--border);border-radius:10px;">
                    <div style="width:100px;font-weight:700;font-size:13.5px;">{{ $dayName }}</div>
                    <label class="toggle">
                        <input type="checkbox" name="schedule[{{ $dayNum }}][is_open]" value="1" {{ $sch?->is_open ? 'checked' : '' }}>
                        <span class="toggle-slider"></span>
                    </label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="time" name="schedule[{{ $dayNum }}][open_time]" class="filter-input" value="{{ $sch ? substr($sch->open_time, 0, 5) : '08:00' }}" style="width:130px;">
                        <span style="color:var(--text-muted);">–</span>
                        <input type="time" name="schedule[{{ $dayNum }}][close_time]" class="filter-input" value="{{ $sch ? substr($sch->close_time, 0, 5) : '22:00' }}" style="width:130px;">
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        <div class="card-footer" style="display:flex;justify-content:flex-end;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Schedule</button>
        </div>
    </form>
</div>
@endsection
