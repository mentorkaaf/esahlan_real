@extends('admin.layouts.app')
@section('title', 'eParcel — Types & Zone Pricing')
@section('content')

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-box" style="color:var(--primary)"></i> eParcel Management</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">eParcel</li>
        </ol>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" style="padding:12px 16px;border-radius:8px;background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;margin-bottom:16px;">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
@endif

<div style="display:flex;gap:0;border-bottom:2px solid #eee;margin-bottom:20px;">
    <button onclick="showTab('types')" id="tab-types" class="tab-btn active"
        style="padding:10px 24px;border:none;background:none;font-weight:600;cursor:pointer;border-bottom:3px solid var(--primary);color:var(--primary)">
        <i class="fas fa-tags"></i> Package Types
    </button>
    <button onclick="showTab('zones')" id="tab-zones" class="tab-btn"
        style="padding:10px 24px;border:none;background:none;font-weight:600;cursor:pointer;color:#888">
        <i class="fas fa-map"></i> Zone Pricing
    </button>
</div>

{{-- ══════════════════════════════════════════════════════════════
     PACKAGE TYPES
══════════════════════════════════════════════════════════════ --}}
<div id="section-types">
    <div class="page-header" style="margin-bottom:12px;">
        <p class="text-muted" style="font-size:13px;margin:0;">Define the types of parcels customers can send (Documents, Electronics, etc.).</p>
        <button class="btn btn-primary btn-sm" onclick="openModal('addTypeModal')">
            <i class="fas fa-plus"></i> Add Type
        </button>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($types as $t)
                    <tr>
                        <td><strong>{{ $t->name }}</strong></td>
                        <td class="text-muted" style="font-size:12px;">{{ $t->description ?? '-' }}</td>
                        <td>
                            <span class="badge {{ $t->is_active ? 'badge-success' : 'badge-danger' }}">
                                {{ $t->is_active ? 'Active' : 'Off' }}
                            </span>
                        </td>
                        <td class="d-flex gap-2">
                            <button class="btn btn-sm btn-secondary"
                                onclick="openEditType({{ $t->id }}, '{{ addslashes($t->name) }}', '{{ addslashes($t->description ?? '') }}', {{ $t->is_active ? 1 : 0 }})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form action="{{ route('admin.module-data.parcel.type.destroy', $t->id) }}" method="POST" onsubmit="return confirm('Delete this type?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="text-align:center;padding:30px;color:#888;">No parcel types yet. Add one above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     ZONE PRICING MATRIX
══════════════════════════════════════════════════════════════ --}}
<div id="section-zones" style="display:none;">

    {{-- Build a pricing map for quick lookup --}}
    @php
        $priceMap = [];
        foreach($zones as $z) {
            $priceMap[$z->fromDistrict->id . '_' . $z->toDistrict->id] = $z->base_price;
        }
    @endphp

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <div>
            <h3 style="margin:0;font-size:16px;font-weight:700;">District-to-District Pricing</h3>
            <p class="text-muted" style="font-size:12px;margin:4px 0 0;">
                Set the flat delivery price for each route. Leave empty = route not available. Rows = Pickup district, Columns = Delivery district.
            </p>
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn btn-sm btn-secondary" onclick="fillSamePrice()">
                <i class="fas fa-fill-drip"></i> Fill All Empty
            </button>
            <button class="btn btn-primary btn-sm" form="zoneBulkForm" type="submit">
                <i class="fas fa-save"></i> Save All Prices
            </button>
        </div>
    </div>

    {{-- Quick Add Single Zone --}}
    <details style="margin-bottom:16px;">
        <summary style="cursor:pointer;font-weight:600;color:var(--primary);font-size:13px;padding:8px 0;">
            <i class="fas fa-plus-circle"></i> Quick add / update single route
        </summary>
        <div style="background:#f8fafc;border-radius:8px;padding:16px;margin-top:8px;max-width:500px;">
            <form action="{{ route('admin.module-data.parcel.zone.store') }}" method="POST">
                @csrf
                <div class="grid-3" style="gap:10px;">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">From District</label>
                        <select name="from_district_id" class="form-control" required>
                            <option value="">Select...</option>
                            @foreach($districts as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">To District</label>
                        <select name="to_district_id" class="form-control" required>
                            <option value="">Select...</option>
                            @foreach($districts as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Price ($)</label>
                        <input type="number" name="base_price" class="form-control" step="0.01" min="0" required placeholder="3.00">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:10px;">
                    <i class="fas fa-save"></i> Save Route
                </button>
            </form>
        </div>
    </details>

    {{-- MATRIX GRID --}}
    <form id="zoneBulkForm" action="{{ route('admin.module-data.parcel.zone.bulk') }}" method="POST">
        @csrf
        <div style="overflow-x:auto;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.07);">
            <table style="border-collapse:collapse;min-width:900px;width:100%;background:#fff;">
                <thead>
                    <tr>
                        <th style="background:#1e1b4b;color:#fff;padding:10px 14px;font-size:12px;font-weight:700;position:sticky;left:0;z-index:2;min-width:130px;text-align:left;">
                            From ↓ / To →
                        </th>
                        @foreach($districts as $col)
                        <th style="background:#1e1b4b;color:#fff;padding:8px 6px;font-size:10px;font-weight:600;text-align:center;min-width:80px;white-space:nowrap;">
                            {{ $col->name }}
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($districts as $row)
                    <tr style="border-bottom:1px solid #f0f0f0;">
                        <td style="background:#f8f9ff;font-weight:700;font-size:12px;padding:8px 14px;position:sticky;left:0;z-index:1;border-right:2px solid #e5e7eb;white-space:nowrap;color:#1e1b4b;">
                            {{ $row->name }}
                        </td>
                        @foreach($districts as $col)
                        @php
                            $key   = $row->id . '_' . $col->id;
                            $price = $priceMap[$key] ?? null;
                            $isSame = $row->id === $col->id;
                        @endphp
                        <td style="padding:4px;text-align:center;{{ $isSame ? 'background:#f3f4f6;' : '' }}">
                            @if($isSame)
                                <span style="color:#ccc;font-size:16px;">—</span>
                            @else
                                <input
                                    type="number"
                                    name="prices[{{ $key }}]"
                                    value="{{ $price !== null ? number_format($price, 2, '.', '') : '' }}"
                                    step="0.01"
                                    min="0"
                                    placeholder="–"
                                    style="width:68px;border:1px solid {{ $price !== null ? '#a7f3d0' : '#e5e7eb' }};border-radius:6px;padding:5px 6px;font-size:12px;font-weight:600;text-align:center;color:#111;background:{{ $price !== null ? '#f0fdf4' : '#fff' }};outline:none;"
                                    onfocus="this.style.borderColor='var(--primary)';this.style.background='#fff';"
                                    onblur="this.style.borderColor=this.value?'#a7f3d0':'#e5e7eb';this.style.background=this.value?'#f0fdf4':'#fff';"
                                >
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px;display:flex;align-items:center;gap:16px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save All Prices
            </button>
            <span style="font-size:12px;color:#888;">
                <span style="display:inline-block;width:12px;height:12px;background:#f0fdf4;border:1px solid #a7f3d0;border-radius:3px;margin-right:4px;"></span>
                Green = price set &nbsp;
                <span style="display:inline-block;width:12px;height:12px;background:#fff;border:1px solid #e5e7eb;border-radius:3px;margin-right:4px;"></span>
                White = not available
            </span>
        </div>
    </form>

    {{-- Summary stats --}}
    <div style="margin-top:16px;padding:12px 16px;background:#fff;border-radius:8px;border:1px solid #e5e7eb;font-size:13px;">
        <strong>{{ count($zones) }}</strong> routes configured out of
        <strong>{{ count($districts) * (count($districts) - 1) }}</strong> possible routes
        ({{ count($districts) }} districts).
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     MODALS
══════════════════════════════════════════════════════════════ --}}

{{-- Add Type Modal --}}
<div class="modal-overlay" id="addTypeModal">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-header">
            <h3 class="modal-title">Add Package Type</h3>
            <button class="modal-close" onclick="closeModal('addTypeModal')">✕</button>
        </div>
        <form action="{{ route('admin.module-data.parcel.type.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label class="form-label">Type Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Documents, Electronics, Fragile">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control" placeholder="Short description for customers">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px;">Add Type</button>
        </form>
    </div>
</div>

{{-- Edit Type Modal --}}
<div class="modal-overlay" id="editTypeModal">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Package Type</h3>
            <button class="modal-close" onclick="closeModal('editTypeModal')">✕</button>
        </div>
        <form id="editTypeForm" method="POST">
            @csrf @method('PATCH')
            <div class="form-group">
                <label class="form-label">Type Name *</label>
                <input type="text" name="name" id="tName" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <input type="text" name="description" id="tDesc" class="form-control">
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="is_active" id="tActive" value="1"> Active
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px;">Save Changes</button>
        </form>
    </div>
</div>

@push('scripts')
<script>
function showTab(tab) {
    ['types','zones'].forEach(t => {
        document.getElementById('section-'+t).style.display = t === tab ? 'block' : 'none';
        const btn = document.getElementById('tab-'+t);
        btn.style.borderBottom = t === tab ? '3px solid var(--primary)' : 'none';
        btn.style.color = t === tab ? 'var(--primary)' : '#888';
    });
}

function openEditType(id, name, desc, isActive) {
    document.getElementById('editTypeForm').action = `/admin/module-data/parcel/types/${id}`;
    document.getElementById('tName').value    = name;
    document.getElementById('tDesc').value    = desc;
    document.getElementById('tActive').checked = isActive == 1;
    openModal('editTypeModal');
}

// Fill all empty inputs with a given price
function fillSamePrice() {
    const price = prompt('Enter price to fill all EMPTY cells ($):', '2.00');
    if (!price || isNaN(price)) return;
    document.querySelectorAll('#zoneBulkForm input[type=number]').forEach(inp => {
        if (!inp.value) {
            inp.value = parseFloat(price).toFixed(2);
            inp.style.borderColor = '#a7f3d0';
            inp.style.background  = '#f0fdf4';
        }
    });
}

// Open zones tab if hash is #zones
if (window.location.hash === '#zones') showTab('zones');
</script>
@endpush
@endsection
