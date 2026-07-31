@extends('admin.layouts.app')
@section('title', 'eRent — Property Management')
@section('content')

<style>
:root { --rent:#7c3aed; --rent-light:rgba(124,58,237,.1); }
.rent-hero {
    background: linear-gradient(135deg, #07003B 0%, #2D1B69 50%, #7c3aed 100%);
    border-radius: 16px; padding: 28px 32px; margin-bottom: 24px;
    color: #fff; display: flex; align-items: center; justify-content: space-between;
    position: relative; overflow: hidden;
}
.rent-hero::before {
    content:''; position:absolute; right:-60px; top:-60px;
    width:240px; height:240px; border-radius:50%;
    background:rgba(255,255,255,.05); pointer-events:none;
}
.rent-hero::after {
    content:''; position:absolute; left:-30px; bottom:-50px;
    width:180px; height:180px; border-radius:50%;
    background:rgba(255,138,0,.1); pointer-events:none;
}
.rent-hero-title  { font-size:22px; font-weight:800; margin:0 0 4px; }
.rent-hero-sub    { margin:0; opacity:.8; font-size:13px; }
.rent-hero-icon   { font-size:56px; opacity:.18; position:relative; z-index:1; }
.rent-badge {
    display:inline-block; padding:3px 10px; border-radius:20px;
    font-size:11px; font-weight:700; margin-right:6px; margin-top:6px;
    background:rgba(255,138,0,.2); border:1px solid rgba(255,138,0,.4); color:#fff;
}

/* STATS */
.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
.stat-card {
    background:#fff; border-radius:14px; padding:20px 22px;
    box-shadow:0 1px 8px rgba(0,0,0,.06); display:flex; align-items:center; gap:16px;
    border:1px solid #f0f1f5;
}
.stat-icon { width:50px; height:50px; border-radius:13px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.stat-icon.purple { background:rgba(124,58,237,.1); color:#7c3aed; }
.stat-icon.green  { background:rgba(16,185,129,.1); color:#10b981; }
.stat-icon.orange { background:rgba(255,138,0,.1); color:#FF8A00; }
.stat-icon.blue   { background:rgba(59,130,246,.1); color:#3b82f6; }
.stat-num  { font-size:26px; font-weight:800; color:#1A1A2E; line-height:1; }
.stat-lbl  { font-size:12px; color:#8A8A9A; margin-top:2px; }
.stat-trend{ font-size:11px; font-weight:600; margin-top:4px; }
.trend-up  { color:#10b981; } .trend-dn { color:#ef4444; }

/* TABS */
.module-tabs { display:flex; gap:4px; background:#f4f5fa; border-radius:12px; padding:4px; margin-bottom:24px; }
.module-tab  {
    padding:9px 18px; border-radius:9px; border:none; background:transparent;
    cursor:pointer; font-size:13px; font-weight:600; color:#8A8A9A;
    transition:all .2s; display:flex; align-items:center; gap:7px; font-family:inherit;
}
.module-tab.active { background:#fff; color:#7c3aed; box-shadow:0 1px 6px rgba(0,0,0,.08); }
.module-tab:hover:not(.active) { color:#1A1A2E; background:rgba(255,255,255,.6); }
.tab-pane { display:none; } .tab-pane.active { display:block; }

/* SECTION CARD */
.sc { background:#fff; border-radius:14px; box-shadow:0 1px 8px rgba(0,0,0,.06); overflow:hidden; margin-bottom:20px; border:1px solid #f0f1f5; }
.sc-head { padding:18px 22px; border-bottom:1px solid #f0f1f5; display:flex; align-items:center; justify-content:space-between; }
.sc-title { font-size:15px; font-weight:700; color:#1A1A2E; display:flex; align-items:center; gap:8px; }
.sc-body { padding:0; }

/* TABLE */
.dtbl { width:100%; border-collapse:collapse; }
.dtbl th { padding:11px 16px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#8A8A9A; border-bottom:1px solid #f0f1f5; background:#fafbff; white-space:nowrap; }
.dtbl td { padding:12px 16px; font-size:13px; color:#1A1A2E; border-bottom:1px solid #f0f1f5; vertical-align:middle; }
.dtbl tr:last-child td { border-bottom:none; }
.dtbl tr:hover td { background:rgba(124,58,237,.02); }
.dtbl .empty-row td { text-align:center; padding:40px; color:#8A8A9A; }

/* PROPERTY CARD */
.prop-img { width:80px; height:56px; border-radius:10px; object-fit:cover; flex-shrink:0; }
.prop-img-ph { width:80px; height:56px; border-radius:10px; background:#f4f5fa; display:flex; align-items:center; justify-content:center; color:#8A8A9A; font-size:22px; flex-shrink:0; }
.prop-info h4 { font-size:14px; font-weight:700; color:#1A1A2E; margin:0 0 3px; }
.prop-info p  { font-size:12px; color:#8A8A9A; margin:0; }

/* CHIPS */
.chips { display:flex; flex-wrap:wrap; gap:4px; }
.chip { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; background:#f4f5fa; border-radius:20px; font-size:11px; color:#1A1A2E; }

/* BADGES */
.badge-xs { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-available  { background:rgba(16,185,129,.1); color:#10b981; }
.badge-booked     { background:rgba(239,68,68,.1); color:#ef4444; }
.badge-pending    { background:rgba(245,158,11,.1); color:#f59e0b; }
.badge-confirmed  { background:rgba(59,130,246,.1); color:#3b82f6; }
.badge-active     { background:rgba(16,185,129,.1); color:#10b981; }
.badge-cancelled  { background:rgba(239,68,68,.1); color:#ef4444; }
.badge-completed  { background:rgba(124,58,237,.1); color:#7c3aed; }
.badge-type { background:rgba(124,58,237,.08); color:#7c3aed; }

/* PRICE */
.price-tag { font-size:15px; font-weight:800; color:#7c3aed; }
.price-sub { font-size:11px; color:#8A8A9A; }

/* FORMS */
.form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; }
.form-grid-4 { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:14px; }
.fgroup label { font-size:12px; font-weight:600; color:#1A1A2E; margin-bottom:5px; display:block; }
.fgroup input, .fgroup select, .fgroup textarea {
    width:100%; padding:9px 12px; border:1.5px solid #EEEEEE; border-radius:10px;
    font-size:13px; color:#1A1A2E; background:#fff; font-family:inherit;
    transition:border-color .15s;
}
.fgroup input:focus, .fgroup select:focus, .fgroup textarea:focus { outline:none; border-color:#7c3aed; }
.fgroup textarea { resize:vertical; min-height:80px; }

/* BOOKING CARD */
.booking-card {
    background:#fff; border-radius:12px; border:1px solid #f0f1f5;
    padding:16px 18px; margin-bottom:10px;
    display:flex; align-items:center; gap:16px;
    box-shadow:0 1px 4px rgba(0,0,0,.04);
}
.booking-avatar { width:42px; height:42px; border-radius:10px; background:var(--rent-light); display:flex; align-items:center; justify-content:center; color:var(--rent); font-size:16px; flex-shrink:0; }

/* ACTION BTNS */
.btn-icon { width:32px; height:32px; border-radius:8px; border:none; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; font-size:13px; transition:all .15s; }
.btn-icon.edit   { background:rgba(59,130,246,.1); color:#3b82f6; }
.btn-icon.del    { background:rgba(239,68,68,.1); color:#ef4444; }
.btn-icon.view   { background:rgba(124,58,237,.1); color:#7c3aed; }
.btn-icon:hover  { transform:scale(1.08); }
.btn-add {
    display:inline-flex; align-items:center; gap:7px; padding:9px 18px;
    background:#7c3aed; color:#fff; border:none; border-radius:10px;
    font-size:13px; font-weight:700; cursor:pointer; font-family:inherit; transition:opacity .15s;
}
.btn-add:hover { opacity:.9; }
.btn-outline {
    display:inline-flex; align-items:center; gap:7px; padding:8px 16px;
    background:transparent; color:#7c3aed; border:1.5px solid #7c3aed; border-radius:10px;
    font-size:13px; font-weight:600; cursor:pointer; font-family:inherit; transition:all .15s;
}
.btn-outline:hover { background:#7c3aed; color:#fff; }

/* MODAL */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; display:none; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal-box { background:#fff; border-radius:18px; width:min(680px,95vw); max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.18); }
.modal-head { padding:22px 26px; border-bottom:1px solid #f0f1f5; display:flex; align-items:center; justify-content:space-between; }
.modal-head h3 { font-size:17px; font-weight:800; color:#1A1A2E; margin:0; }
.modal-close { width:32px; height:32px; border-radius:8px; border:none; background:#f4f5fa; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; }
.modal-body { padding:24px 26px; }
.modal-foot { padding:16px 26px; border-top:1px solid #f0f1f5; display:flex; justify-content:flex-end; gap:10px; }

/* PAGINATION */
.pag { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-top:1px solid #f0f1f5; font-size:13px; color:#8A8A9A; }
.pag a, .pag span { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; border:1px solid #f0f1f5; color:#8A8A9A; }
.pag a:hover { background:#7c3aed; color:#fff; border-color:#7c3aed; }
.pag .active-page { background:#7c3aed; color:#fff; border-color:#7c3aed; }

/* DETAIL MODAL TABLE */
.detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.detail-item { background:#fafbff; border-radius:10px; padding:12px 14px; }
.detail-item-lbl { font-size:11px; color:#8A8A9A; font-weight:600; text-transform:uppercase; letter-spacing:.4px; margin-bottom:4px; }
.detail-item-val { font-size:14px; font-weight:700; color:#1A1A2E; }

/* ALERTS */
.alert-success { background:rgba(16,185,129,.08); border:1px solid rgba(16,185,129,.3); color:#065f46; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13px; font-weight:600; }
.alert-error   { background:rgba(239,68,68,.08);  border:1px solid rgba(239,68,68,.3);  color:#7f1d1d; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13px; font-weight:600; }

/* FILTER BAR */
.filter-bar { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
.filter-bar input, .filter-bar select { padding:8px 12px; border:1.5px solid #EEEEEE; border-radius:10px; font-size:13px; color:#1A1A2E; background:#fff; font-family:inherit; }
.filter-bar input:focus, .filter-bar select:focus { outline:none; border-color:#7c3aed; }

/* IMAGE GALLERY PREVIEW */
.img-preview-row { display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; }
.img-preview-item { width:72px; height:56px; border-radius:8px; object-fit:cover; border:2px solid #f0f1f5; }
@keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.6;} }
</style>

{{-- ALERTS --}}
@if(session('success'))
<div class="alert-success"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="alert-error"><i class="fas fa-exclamation-circle me-2"></i>{{ $errors->first() }}</div>
@endif

{{-- HERO --}}
<div class="rent-hero">
    <div style="position:relative;z-index:1">
        <h2 class="rent-hero-title"><i class="fas fa-building me-2"></i>eRent — Property Management</h2>
        <p class="rent-hero-sub">Manage districts, properties, reservations and rental bookings</p>
        <div style="margin-top:8px">
            <span class="rent-badge"><i class="fas fa-home me-1"></i>Full Rent</span>
            <span class="rent-badge"><i class="fas fa-percent me-1"></i>Carbuun 30%</span>
        </div>
    </div>
    <div class="rent-hero-icon"><i class="fas fa-city"></i></div>
</div>

{{-- STATS --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-building"></i></div>
        <div>
            <div class="stat-num">{{ $properties->total() }}</div>
            <div class="stat-lbl">Total Properties</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-num">{{ $properties->filter(fn($p) => $p->is_available)->count() ?? '—' }}</div>
            <div class="stat-lbl">Available</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-handshake"></i></div>
        <div>
            <div class="stat-num">{{ $bookings->count() }}</div>
            <div class="stat-lbl">Total Bookings</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-map-marker-alt"></i></div>
        <div>
            <div class="stat-num">{{ $districts->count() }}</div>
            <div class="stat-lbl">Districts</div>
        </div>
    </div>
</div>

{{-- TABS --}}
<div class="module-tabs">
    <button class="module-tab active" onclick="switchTab('properties',this)"><i class="fas fa-building"></i> Properties</button>
    <button class="module-tab" onclick="switchTab('bookings',this)"><i class="fas fa-calendar-check"></i> Bookings</button>
    <button class="module-tab" onclick="switchTab('add-property',this)"><i class="fas fa-plus-circle"></i> Add Property</button>
    <button class="module-tab" onclick="switchTab('districts',this)"><i class="fas fa-map-marker-alt"></i> Districts</button>
    <button class="module-tab" onclick="switchTab('agents',this)"><i class="fas fa-user-tie"></i> Agents <span id="pending-badge" style="background:#ef4444;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px;">{{ $agents->where('status', 'pending')->count() }}</span></button>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TAB: PROPERTIES --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="tab-properties" class="tab-pane active">

    {{-- Booked properties quick-action banner --}}
    @php $bookedProps = $properties->filter(fn($p) => !$p->is_available); @endphp
    @if($bookedProps->count())
    <div style="background:linear-gradient(135deg,#1a237e,#283593);border-radius:14px;padding:16px 20px;margin-bottom:18px;color:#fff;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <div style="background:rgba(255,255,255,.15);border-radius:10px;padding:10px 14px;font-size:22px;line-height:1;">🏠</div>
        <div style="flex:1;">
            <div style="font-size:15px;font-weight:800;">{{ $bookedProps->count() }} Booked {{ Str::plural('Property', $bookedProps->count()) }}</div>
            <div style="font-size:12px;opacity:.75;margin-top:2px;">Use the <strong style="background:rgba(255,255,255,.2);padding:1px 6px;border-radius:4px;">Free Up</strong> button on any row to mark a property available again after tenant moves out.</div>
        </div>
        <div style="font-size:12px;opacity:.7;text-align:right;">
            <div>{{ $properties->filter(fn($p)=>$p->is_available)->count() }} available</div>
            <div>{{ $bookedProps->count() }} occupied</div>
        </div>
    </div>
    @endif

    <div class="sc">
        <div class="sc-head">
            <div class="sc-title"><i class="fas fa-building" style="color:#7c3aed"></i> Properties</div>
            <div class="filter-bar" style="margin:0">
                <input type="text" id="propSearch" placeholder="Search properties…" oninput="filterProps()" style="width:200px">
                <select id="propType" onchange="filterProps()">
                    <option value="">All Types</option>
                    <option>apartment</option><option>house</option><option>villa</option>
                    <option>room</option><option>office</option><option>shop</option>
                </select>
                <select id="propStatus" onchange="filterProps()">
                    <option value="">All Status</option>
                    <option value="1">Available</option>
                    <option value="0">Booked</option>
                </select>
            </div>
        </div>
        <div class="sc-body">
            <table class="dtbl" id="propTable">
                <thead>
                    <tr>
                        <th>Property</th>
                        <th>Type</th>
                        <th>District</th>
                        <th>Rooms</th>
                        <th>Monthly Rent</th>
                        <th>Deposit</th>
                        <th>Status</th>
                        <th style="width:100px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($properties as $prop)
                    <tr class="prop-row"
                        data-title="{{ strtolower($prop->title) }}"
                        data-type="{{ $prop->type }}"
                        data-available="{{ $prop->is_available ? '1' : '0' }}">
                        <td>
                            <div style="display:flex;align-items:center;gap:12px">
                                @php $imgs = json_decode($prop->images ?? '[]', true); @endphp
                                @if(!empty($imgs[0]))
                                    <img src="{{ asset('storage/'.$imgs[0]) }}" class="prop-img" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                    <div class="prop-img-ph" style="display:none"><i class="fas fa-building"></i></div>
                                @else
                                    <div class="prop-img-ph"><i class="fas fa-building"></i></div>
                                @endif
                                <div class="prop-info">
                                    <h4>{{ $prop->title }}</h4>
                                    <p>{{ $prop->address ?? $prop->district_name }}</p>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge-xs badge-type">{{ ucfirst($prop->type) }}</span></td>
                        <td>{{ $prop->district_name }}</td>
                        <td>
                            <div class="chips">
                                @if($prop->bedrooms) <span class="chip"><i class="fas fa-bed" style="font-size:9px"></i>{{ $prop->bedrooms }}</span> @endif
                                @if($prop->bathrooms) <span class="chip"><i class="fas fa-bath" style="font-size:9px"></i>{{ $prop->bathrooms }}</span> @endif
                                @if($prop->kitchens) <span class="chip"><i class="fas fa-utensils" style="font-size:9px"></i>{{ $prop->kitchens }}</span> @endif
                            </div>
                        </td>
                        <td><div class="price-tag">${{ number_format($prop->monthly_rent) }}</div><div class="price-sub">/month</div></td>
                        <td><span style="font-weight:600">${{ number_format($prop->deposit ?? 0) }}</span></td>
                        <td>
                            @if($prop->is_available)
                                <span class="badge-xs badge-available"><i class="fas fa-circle" style="font-size:7px"></i> Available</span>
                            @else
                                <span class="badge-xs badge-booked"><i class="fas fa-circle" style="font-size:7px"></i> Booked</span>
                            @endif
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                <button class="btn-icon view" title="View" onclick="openPropDetail({{ json_encode($prop) }})"><i class="fas fa-eye"></i></button>
                                <button class="btn-icon edit" title="Edit" onclick="openEditProp({{ json_encode($prop) }})"><i class="fas fa-pen"></i></button>
                                <form method="POST" action="{{ route('admin.module-data.rent.property.destroy', $prop->id) }}" onsubmit="return confirm('Delete this property?')" style="display:inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-icon del" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                                @if(!$prop->is_available)
                                <form method="POST" action="{{ route('admin.module-data.rent.property.mark_available', $prop->id) }}"
                                      onsubmit="return confirm('Mark \'{{ addslashes($prop->title) }}\' as available again?\n\nThis means the tenant has moved out and the property is ready for new renters.')"
                                      style="display:inline">
                                    @csrf
                                    <button type="submit"
                                        title="Tenant moved out — mark available"
                                        style="background:linear-gradient(135deg,#1B5E20,#2E7D32);color:#fff;border:none;border-radius:7px;padding:5px 10px;font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:4px;white-space:nowrap;">
                                        <i class="fas fa-home"></i> Free Up
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr class="empty-row"><td colspan="8"><i class="fas fa-building" style="font-size:32px;color:#EEEEEE;display:block;margin-bottom:10px"></i>No properties yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($properties->hasPages())
        <div class="pag">
            <span>Showing {{ $properties->firstItem() }}–{{ $properties->lastItem() }} of {{ $properties->total() }}</span>
            <div style="display:flex;gap:4px">{{ $properties->links('pagination::simple-bootstrap-5') }}</div>
        </div>
        @endif
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TAB: BOOKINGS --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="tab-bookings" class="tab-pane">

    {{-- Refund Requests Banner --}}
    @php $refundBookings = $bookings->filter(fn($b) => $b->status === 'refund_requested'); @endphp
    @if($refundBookings->count())
    <div style="background:linear-gradient(135deg,#FF6B35,#FF8A00);border-radius:14px;padding:18px 22px;margin-bottom:20px;color:#fff;box-shadow:0 4px 20px rgba(255,107,53,.3);">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
            <div style="background:rgba(255,255,255,.2);border-radius:10px;padding:10px 12px;font-size:20px;">🔔</div>
            <div>
                <div style="font-size:16px;font-weight:900;">{{ $refundBookings->count() }} Refund Request{{ $refundBookings->count() > 1 ? 's' : '' }} Pending</div>
                <div style="font-size:12px;opacity:.8;margin-top:2px;">Review and respond to refund requests below</div>
            </div>
        </div>
        @foreach($refundBookings as $rb)
        <div style="background:rgba(255,255,255,.15);border-radius:10px;padding:14px 16px;margin-bottom:10px;backdrop-filter:blur(4px);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                <div>
                    <div style="font-size:14px;font-weight:800;">#{{ $rb->order_number }} — {{ Str::limit($rb->property_title, 30) }}</div>
                    <div style="font-size:12px;opacity:.85;margin-top:3px;">👤 {{ $rb->tenant_name }} &nbsp;|&nbsp; 💰 Paid ${{ number_format($rb->amount_paid ?? 0) }}</div>
                    @if($rb->refund_reason)
                    <div style="margin-top:6px;font-size:12px;background:rgba(0,0,0,.15);border-radius:6px;padding:6px 10px;font-style:italic;">"{{ $rb->refund_reason }}"</div>
                    @endif
                </div>
                <div style="display:flex;gap:8px;flex-shrink:0;align-items:center;">
                    <form method="POST" action="{{ route('admin.module-data.rent.booking.approve_refund', $rb->id) }}" onsubmit="return confirm('Approve refund for {{ addslashes($rb->tenant_name) }}? Property will become available again.')">
                        @csrf
                        <button type="submit" style="background:#fff;color:#E65100;border:none;border-radius:8px;padding:8px 16px;font-size:12px;font-weight:800;cursor:pointer;white-space:nowrap;">✅ Approve</button>
                    </form>
                    <form method="POST" action="{{ route('admin.module-data.rent.booking.deny_refund', $rb->id) }}" onsubmit="return confirm('Deny refund request for {{ addslashes($rb->tenant_name) }}?')">
                        @csrf
                        <button type="submit" style="background:rgba(0,0,0,.25);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:12px;font-weight:800;cursor:pointer;white-space:nowrap;">❌ Deny</button>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <div class="sc">
        <div class="sc-head">
            <div class="sc-title"><i class="fas fa-calendar-check" style="color:#7c3aed"></i> Rental Bookings</div>
            <input type="text" placeholder="Search bookings…" oninput="filterBookings(this.value)" style="padding:7px 12px;border:1.5px solid #EEEEEE;border-radius:10px;font-size:13px;width:220px">
        </div>
        <div class="sc-body">
            <table class="dtbl" id="bookingTable">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Property</th>
                        <th>Tenant</th>
                        <th>Type</th>
                        <th>Move-In</th>
                        <th>Amount Paid</th>
                        <th>Remaining</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bookings as $bk)
                    @php $bkStatus = $bk->status ?? 'pending'; @endphp
                    <tr style="{{ $bkStatus === 'refund_requested' ? 'background:linear-gradient(90deg,#FFF3E0,#FFF9F5);' : '' }}">
                        <td><strong>#{{ $bk->order_number }}</strong></td>
                        <td>
                            <div style="font-weight:600;font-size:13px">{{ Str::limit($bk->property_title, 28) }}</div>
                        </td>
                        <td>
                            <div style="font-weight:600">{{ $bk->tenant_name }}</div>
                            <div style="font-size:11px;color:#8A8A9A">{{ $bk->tenant_phone }}</div>
                        </td>
                        <td>
                            @if($bk->booking_type === 'carbuun')
                                <span class="badge-xs" style="background:rgba(245,158,11,.1);color:#f59e0b">Carbuun 30%</span>
                            @else
                                <span class="badge-xs" style="background:rgba(16,185,129,.1);color:#10b981">Full Rent</span>
                            @endif
                        </td>
                        <td>{{ $bk->move_in_date ?? '—' }}</td>
                        <td><strong>${{ number_format($bk->amount_paid ?? 0) }}</strong></td>
                        <td>
                            @php $rem = ($bk->amount_remaining ?? 0); @endphp
                            @if($rem > 0)
                                <span style="color:#ef4444;font-weight:700">${{ number_format($rem) }}</span>
                            @else
                                <span style="color:#10b981;font-weight:700">Fully Paid</span>
                            @endif
                        </td>
                        <td>
                            @php $ps = $bk->payment_status ?? 'pending'; $bkpm = $bk->payment_method ?? 'cod'; @endphp
                            <span style="font-size:11px;font-weight:700;color:{{ $bkpm==='mobile_pay'?'#3949AB':($bkpm==='wallet'?'#2E7D32':'#555') }};display:block;">{{ ucwords(str_replace('_',' ',$bkpm)) }}</span>
                            <span class="badge-xs badge-{{ $ps }}">{{ ucfirst($ps) }}</span>
                            @if($bkpm==='mobile_pay')<a href="{{ route('admin.orders.show', $bk->id) }}" style="font-size:10px;color:#3949AB;display:block;margin-top:2px;"><i class="fas fa-image"></i> Proof</a>@endif
                        </td>
                        <td>
                            @if($bkStatus === 'refund_requested')
                                <span class="badge-xs" style="background:#FFF3E0;color:#E65100;border:1px solid #FF8A00;animation:pulse 2s infinite;">🔔 Refund Req</span>
                            @else
                                <span class="badge-xs badge-{{ $bkStatus }}">{{ ucfirst(str_replace('_',' ',$bkStatus)) }}</span>
                            @endif
                        </td>
                        <td>
                            @if($bkStatus === 'refund_requested')
                            <div style="display:flex;gap:4px;">
                                <form method="POST" action="{{ route('admin.module-data.rent.booking.approve_refund', $bk->id) }}" onsubmit="return confirm('Approve refund?')">
                                    @csrf
                                    <button type="submit" class="btn-xs" style="background:#10b981;color:#fff;border:none;border-radius:6px;padding:4px 8px;font-size:11px;cursor:pointer;">✅ Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.module-data.rent.booking.deny_refund', $bk->id) }}" onsubmit="return confirm('Deny refund?')">
                                    @csrf
                                    <button type="submit" class="btn-xs" style="background:#ef4444;color:#fff;border:none;border-radius:6px;padding:4px 8px;font-size:11px;cursor:pointer;">❌ Deny</button>
                                </form>
                            </div>
                            @else
                            <span style="color:#ccc;font-size:12px;">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr class="empty-row"><td colspan="10"><i class="fas fa-calendar-times" style="font-size:32px;color:#EEEEEE;display:block;margin-bottom:10px"></i>No bookings yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TAB: ADD PROPERTY --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="tab-add-property" class="tab-pane">
    <div class="sc">
        <div class="sc-head">
            <div class="sc-title"><i class="fas fa-plus-circle" style="color:#7c3aed"></i> Add New Property</div>
        </div>
        <div class="sc-body" style="padding:24px">
            <form method="POST" action="{{ route('admin.module-data.rent.property.store') }}" enctype="multipart/form-data">
                @csrf
                <div style="margin-bottom:20px">
                    <h4 style="font-size:14px;font-weight:700;color:#1A1A2E;margin:0 0 14px;padding-bottom:8px;border-bottom:1px solid #f0f1f5">Basic Information</h4>
                    <div class="form-grid-2" style="margin-bottom:14px">
                        <div class="fgroup">
                            <label>Property Title <span style="color:#ef4444">*</span></label>
                            <input type="text" name="title" required placeholder="e.g. Modern 2BR Apartment in Hodan" value="{{ old('title') }}">
                        </div>
                        <div class="fgroup">
                            <label>District <span style="color:#ef4444">*</span></label>
                            <select name="district_id" required>
                                <option value="">Select district</option>
                                @foreach($districts as $d)
                                <option value="{{ $d->id }}" {{ old('district_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-grid-2" style="margin-bottom:14px">
                        <div class="fgroup">
                            <label>Property Type <span style="color:#ef4444">*</span></label>
                            <select name="type" required>
                                <option value="">Select type</option>
                                <option value="apartment">Apartment</option>
                                <option value="house">House</option>
                                <option value="villa">Villa</option>
                                <option value="room">Room</option>
                                <option value="office">Office</option>
                                <option value="shop">Shop</option>
                            </select>
                        </div>
                        <div class="fgroup">
                            <label>Address</label>
                            <input type="text" name="address" placeholder="Street address / landmark" value="{{ old('address') }}">
                        </div>
                    </div>
                    <div class="fgroup">
                        <label>Description <span style="color:#ef4444">*</span></label>
                        <textarea name="description" required placeholder="Describe the property…">{{ old('description') }}</textarea>
                    </div>
                </div>

                <div style="margin-bottom:20px">
                    <h4 style="font-size:14px;font-weight:700;color:#1A1A2E;margin:0 0 14px;padding-bottom:8px;border-bottom:1px solid #f0f1f5">Rooms & Details</h4>
                    <div class="form-grid-4" style="margin-bottom:14px">
                        <div class="fgroup">
                            <label>Bedrooms <span style="color:#ef4444">*</span></label>
                            <input type="number" name="bedrooms" min="0" required value="{{ old('bedrooms',0) }}">
                        </div>
                        <div class="fgroup">
                            <label>Bathrooms <span style="color:#ef4444">*</span></label>
                            <input type="number" name="bathrooms" min="0" required value="{{ old('bathrooms',0) }}">
                        </div>
                        <div class="fgroup">
                            <label>Kitchens</label>
                            <input type="number" name="kitchens" min="0" value="{{ old('kitchens',0) }}">
                        </div>
                        <div class="fgroup">
                            <label>Living Rooms</label>
                            <input type="number" name="living_rooms" min="0" value="{{ old('living_rooms',0) }}">
                        </div>
                    </div>
                    <div class="form-grid-3">
                        <div class="fgroup">
                            <label>Floor</label>
                            <input type="number" name="floor" value="{{ old('floor') }}">
                        </div>
                        <div class="fgroup">
                            <label>Year Built</label>
                            <input type="number" name="year_built" min="1900" max="2099" placeholder="e.g. 2020" value="{{ old('year_built') }}">
                        </div>
                        <div class="fgroup">
                            <label>Furnishing</label>
                            <select name="furnishing">
                                <option value="unfurnished">Unfurnished</option>
                                <option value="semi">Semi-Furnished</option>
                                <option value="furnished">Fully Furnished</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom:20px">
                    <h4 style="font-size:14px;font-weight:700;color:#1A1A2E;margin:0 0 14px;padding-bottom:8px;border-bottom:1px solid #f0f1f5">Pricing</h4>
                    <div class="form-grid-3">
                        <div class="fgroup">
                            <label>Monthly Rent ($) <span style="color:#ef4444">*</span></label>
                            <input type="number" name="monthly_rent" min="0" step="0.01" required value="{{ old('monthly_rent') }}">
                        </div>
                        <div class="fgroup">
                            <label>Security Deposit ($)</label>
                            <input type="number" name="deposit" min="0" step="0.01" value="{{ old('deposit',0) }}">
                        </div>
                        <div class="fgroup">
                            <label>Brokerage Fee ($)</label>
                            <input type="number" name="brokerage_fee" min="0" step="0.01" value="{{ old('brokerage_fee',0) }}">
                        </div>
                    </div>
                </div>

                <div style="margin-bottom:20px">
                    <h4 style="font-size:14px;font-weight:700;color:#1A1A2E;margin:0 0 14px;padding-bottom:8px;border-bottom:1px solid #f0f1f5">Amenities & Images</h4>
                    <div class="form-grid-2">
                        <div class="fgroup">
                            <label>Amenities <span style="font-size:11px;color:#8A8A9A">(comma-separated)</span></label>
                            <input type="text" name="amenities" placeholder="WiFi, Parking, Generator, Security…" value="{{ old('amenities') }}">
                        </div>
                        <div class="fgroup">
                            <label>Property Images</label>
                            <input type="file" name="images[]" multiple accept="image/*">
                        </div>
                        <div class="fgroup">
                            <label>🎬 Property Reels <span style="font-size:11px;color:#8A8A9A">(MP4/WebM, max 50MB each)</span></label>
                            <input type="file" name="reels[]" multiple accept="video/mp4,video/webm,video/quicktime"
                                   onchange="previewReels(this, 'add-reel-preview')">
                            <div id="add-reel-preview" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;"></div>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="reset" class="btn-outline">Reset</button>
                    <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Add Property</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════ MODALS ══════════════════════════ --}}

{{-- VIEW PROPERTY DETAIL MODAL --}}
<div class="modal-overlay" id="propDetailModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="modalPropTitle">Property Details</h3>
            <button class="modal-close" onclick="closePropDetail()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="propDetailBody"></div>
    </div>
</div>

{{-- EDIT PROPERTY MODAL --}}
<div class="modal-overlay" id="editPropModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Edit Property</h3>
            <button class="modal-close" onclick="document.getElementById('editPropModal').classList.remove('open')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="editPropForm" method="POST" enctype="multipart/form-data">
                @csrf @method('PATCH')
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup">
                        <label>Title</label>
                        <input type="text" name="title" id="ep_title" required>
                    </div>
                    <div class="fgroup">
                        <label>District</label>
                        <select name="district_id" id="ep_district">
                            @foreach($districts as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup">
                        <label>Type</label>
                        <select name="type" id="ep_type">
                            <option value="apartment">Apartment</option>
                            <option value="house">House</option>
                            <option value="villa">Villa</option>
                            <option value="room">Room</option>
                            <option value="office">Office</option>
                            <option value="shop">Shop</option>
                        </select>
                    </div>
                    <div class="fgroup">
                        <label>Monthly Rent ($)</label>
                        <input type="number" name="monthly_rent" id="ep_rent" min="0" step="0.01">
                    </div>
                </div>
                <div class="form-grid-3" style="margin-bottom:14px">
                    <div class="fgroup"><label>Bedrooms</label><input type="number" name="bedrooms" id="ep_bed" min="0"></div>
                    <div class="fgroup"><label>Bathrooms</label><input type="number" name="bathrooms" id="ep_bath" min="0"></div>
                    <div class="fgroup"><label>Deposit ($)</label><input type="number" name="deposit" id="ep_deposit" min="0" step="0.01"></div>
                </div>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Floor</label><input type="number" name="floor" id="ep_floor"></div>
                    <div class="fgroup"><label>Year Built</label><input type="number" name="year_built" id="ep_year_built" min="1900" max="2099" placeholder="e.g. 2020"></div>
                </div>
                <div class="fgroup" style="margin-bottom:14px">
                    <label>Description</label>
                    <textarea name="description" id="ep_desc"></textarea>
                </div>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup">
                        <label>Amenities</label>
                        <input type="text" name="amenities" id="ep_amenities">
                    </div>
                    <div class="fgroup">
                        <label>Status</label>
                        <select name="is_available" id="ep_status">
                            <option value="1">Available</option>
                            <option value="0">Booked</option>
                        </select>
                    </div>
                </div>
                <div class="fgroup">
                    <label>New Images (optional)</label>
                    <input type="file" name="images[]" multiple accept="image/*">
                </div>
                <div class="fgroup" style="margin-top:12px">
                    <label>🎬 Reels — Existing</label>
                    <div id="ep_reels_existing" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;"></div>
                    <label style="margin-top:8px;display:block;">Add New Reels <span style="font-size:11px;color:#8A8A9A">(MP4/WebM, max 50MB)</span></label>
                    <input type="file" name="reels[]" multiple accept="video/mp4,video/webm,video/quicktime"
                           onchange="previewReels(this, 'edit-reel-preview')">
                    <div id="edit-reel-preview" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;"></div>
                    <input type="hidden" name="remove_reels" id="ep_remove_reels" value="[]">
                </div>
                <div class="modal-foot" style="padding:16px 0 0;margin-top:16px;border-top:1px solid #f0f1f5">
                    <button type="button" class="btn-outline" onclick="document.getElementById('editPropModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TAB: DISTRICTS --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="tab-districts" class="tab-pane">
    <div class="sc">
        <div class="sc-head">
            <span class="sc-title"><i class="fas fa-map-marker-alt" style="color:#7c3aed"></i> Districts</span>
            <button class="btn-add" onclick="document.getElementById('addDistrictModal').classList.add('open')">
                <i class="fas fa-plus"></i> Add District
            </button>
        </div>
        <div class="sc-body">
            <table class="dtbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Somali Name</th>
                        <th>Properties</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($districts as $dist)
                    <tr>
                        <td style="color:#8A8A9A;font-size:12px;">{{ $dist->id }}</td>
                        <td style="font-weight:700;">{{ $dist->name }}</td>
                        <td style="color:#8A8A9A;">{{ $dist->name_so ?? '—' }}</td>
                        <td>
                            <span style="background:rgba(124,58,237,.08);color:#7c3aed;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;">
                                {{ $dist->property_count ?? 0 }} props
                            </span>
                        </td>
                        <td>
                            <span class="badge-xs {{ $dist->status === 'active' ? 'badge-active' : 'badge-cancelled' }}">
                                {{ ucfirst($dist->status) }}
                            </span>
                        </td>
                        <td>
                            <button class="btn-icon edit" title="Edit"
                                onclick='openEditDistrict({{ json_encode(["id"=>$dist->id,"name"=>$dist->name,"name_so"=>$dist->name_so,"description"=>$dist->description,"status"=>$dist->status]) }})'
                            ><i class="fas fa-pen"></i></button>
                            <form method="POST" action="{{ route('admin.module-data.rent.district.destroy', $dist->id) }}" style="display:inline"
                                onsubmit="return confirm('Delete district {{ addslashes($dist->name) }}? This will fail if it has properties.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-icon del" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr class="empty-row"><td colspan="6">No districts found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ADD DISTRICT MODAL --}}
<div class="modal-overlay" id="addDistrictModal">
    <div class="modal-box" style="width:min(480px,95vw)">
        <div class="modal-head">
            <h3>Add District</h3>
            <button class="modal-close" onclick="document.getElementById('addDistrictModal').classList.remove('open')">✕</button>
        </div>
        <form method="POST" action="{{ route('admin.module-data.rent.district.store') }}">
            @csrf
            <div class="modal-body">
                <div class="form-grid-2">
                    <div class="fgroup">
                        <label>Name (English) *</label>
                        <input name="name" required placeholder="e.g. Hodan">
                    </div>
                    <div class="fgroup">
                        <label>Name (Somali)</label>
                        <input name="name_so" placeholder="e.g. Xodan">
                    </div>
                </div>
                <div class="fgroup" style="margin-top:12px">
                    <label>Description</label>
                    <textarea name="description" rows="2" placeholder="Optional description"></textarea>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-outline" onclick="document.getElementById('addDistrictModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Add District</button>
            </div>
        </form>
    </div>
</div>

{{-- EDIT DISTRICT MODAL --}}
<div class="modal-overlay" id="editDistrictModal">
    <div class="modal-box" style="width:min(480px,95vw)">
        <div class="modal-head">
            <h3>Edit District</h3>
            <button class="modal-close" onclick="document.getElementById('editDistrictModal').classList.remove('open')">✕</button>
        </div>
        <form method="POST" id="editDistrictForm">
            @csrf @method('PATCH')
            <div class="modal-body">
                <div class="form-grid-2">
                    <div class="fgroup">
                        <label>Name (English) *</label>
                        <input name="name" id="ed_name" required>
                    </div>
                    <div class="fgroup">
                        <label>Name (Somali)</label>
                        <input name="name_so" id="ed_name_so">
                    </div>
                </div>
                <div class="fgroup" style="margin-top:12px">
                    <label>Description</label>
                    <textarea name="description" id="ed_description" rows="2"></textarea>
                </div>
                <div class="fgroup" style="margin-top:12px">
                    <label>Status</label>
                    <select name="status" id="ed_status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-outline" onclick="document.getElementById('editDistrictModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn-add"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(name, el) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.module-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    el.classList.add('active');
}

function filterProps() {
    const q     = document.getElementById('propSearch').value.toLowerCase();
    const type  = document.getElementById('propType').value;
    const status= document.getElementById('propStatus').value;
    document.querySelectorAll('#propTable .prop-row').forEach(row => {
        const title = row.dataset.title || '';
        const t     = row.dataset.type || '';
        const avail = row.dataset.available || '';
        const show  = (!q || title.includes(q))
                   && (!type || t === type)
                   && (!status || avail === status);
        row.style.display = show ? '' : 'none';
    });
}

function filterBookings(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#bookingTable tbody tr:not(.empty-row)').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

function openPropDetail(prop) {
    const imgs = JSON.parse(prop.images || '[]');
    const amenities = JSON.parse(prop.amenities || '[]');
    document.getElementById('modalPropTitle').textContent = prop.title;
    document.getElementById('propDetailBody').innerHTML = `
        <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
            ${imgs.length ? imgs.map(i => `<img src="/storage/${i}" style="width:120px;height:80px;border-radius:10px;object-fit:cover">`).join('') : '<div style="background:#f4f5fa;width:120px;height:80px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#8A8A9A;font-size:28px"><i class="fas fa-building"></i></div>'}
        </div>
        <div class="detail-grid">
            <div class="detail-item"><div class="detail-item-lbl">Type</div><div class="detail-item-val">${prop.type ? prop.type.charAt(0).toUpperCase()+prop.type.slice(1) : '—'}</div></div>
            <div class="detail-item"><div class="detail-item-lbl">District</div><div class="detail-item-val">${prop.district_name || '—'}</div></div>
            <div class="detail-item"><div class="detail-item-lbl">Monthly Rent</div><div class="detail-item-val" style="color:#7c3aed">$${Number(prop.monthly_rent||0).toLocaleString()}</div></div>
            <div class="detail-item"><div class="detail-item-lbl">Deposit</div><div class="detail-item-val">$${Number(prop.deposit||0).toLocaleString()}</div></div>
            <div class="detail-item"><div class="detail-item-lbl">Bedrooms / Bathrooms</div><div class="detail-item-val">${prop.bedrooms||0} / ${prop.bathrooms||0}</div></div>
            <div class="detail-item"><div class="detail-item-lbl">Floor</div><div class="detail-item-val">${prop.floor ?? '—'}</div></div>
            <div class="detail-item"><div class="detail-item-lbl">Year Built</div><div class="detail-item-val">${prop.year_built ?? '—'}</div></div>
            <div class="detail-item"><div class="detail-item-lbl">Furnishing</div><div class="detail-item-val">${prop.furnishing ? prop.furnishing.charAt(0).toUpperCase()+prop.furnishing.slice(1) : '—'}</div></div>
            <div class="detail-item"><div class="detail-item-lbl">Status</div><div class="detail-item-val">${prop.is_available ? '<span style="color:#10b981;font-weight:700">Available</span>' : '<span style="color:#ef4444;font-weight:700">Booked</span>'}</div></div>
        </div>
        ${amenities.length ? `<div style="margin-top:14px"><div style="font-size:12px;font-weight:700;color:#8A8A9A;text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px">Amenities</div><div style="display:flex;gap:6px;flex-wrap:wrap">${amenities.map(a=>`<span style="background:#f4f5fa;padding:4px 10px;border-radius:20px;font-size:12px;color:#1A1A2E">${a}</span>`).join('')}</div></div>` : ''}
        ${prop.description ? `<div style="margin-top:14px"><div style="font-size:12px;font-weight:700;color:#8A8A9A;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px">Description</div><p style="font-size:13px;color:#1A1A2E;margin:0;line-height:1.6">${prop.description}</p></div>` : ''}
    `;
    document.getElementById('propDetailModal').classList.add('open');
}

function closePropDetail() {
    document.getElementById('propDetailModal').classList.remove('open');
}

function openEditProp(prop) {
    const amenities = JSON.parse(prop.amenities || '[]');
    document.getElementById('editPropForm').action = `/admin/module-data/rent/properties/${prop.id}`;
    document.getElementById('ep_title').value       = prop.title || '';
    document.getElementById('ep_district').value    = prop.district_id || '';
    document.getElementById('ep_type').value        = prop.type || 'apartment';
    document.getElementById('ep_rent').value        = prop.monthly_rent || '';
    document.getElementById('ep_bed').value         = prop.bedrooms || 0;
    document.getElementById('ep_bath').value        = prop.bathrooms || 0;
    document.getElementById('ep_deposit').value     = prop.deposit || 0;
    document.getElementById('ep_floor').value       = prop.floor || '';
    document.getElementById('ep_year_built').value  = prop.year_built || '';
    document.getElementById('ep_desc').value        = prop.description || '';
    document.getElementById('ep_amenities').value   = amenities.join(', ');
    document.getElementById('ep_status').value      = prop.is_available ? '1' : '0';

    // Populate existing reels
    const reels = JSON.parse(prop.reels || '[]');
    const reelContainer = document.getElementById('ep_reels_existing');
    reelContainer.innerHTML = '';
    document.getElementById('ep_remove_reels').value = '[]';
    let removeList = [];
    reels.forEach((url, i) => {
        const wrap = document.createElement('div');
        wrap.style = 'position:relative;width:80px;';
        const vid = document.createElement('video');
        vid.src = url; vid.style = 'width:80px;height:60px;object-fit:cover;border-radius:6px;border:2px solid #e0e0e0;';
        vid.muted = true; vid.preload = 'metadata';
        const del = document.createElement('button');
        del.type = 'button';
        del.innerHTML = '×';
        del.style = 'position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;border:none;border-radius:50%;width:18px;height:18px;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;line-height:1;';
        del.onclick = () => {
            removeList.push(url);
            document.getElementById('ep_remove_reels').value = JSON.stringify(removeList);
            wrap.remove();
        };
        wrap.appendChild(vid); wrap.appendChild(del);
        reelContainer.appendChild(wrap);
    });

    document.getElementById('editPropModal').classList.add('open');
}

function previewReels(input, containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    Array.from(input.files).forEach(file => {
        const url = URL.createObjectURL(file);
        const wrap = document.createElement('div');
        wrap.style = 'position:relative;';
        const vid = document.createElement('video');
        vid.src = url; vid.style = 'width:80px;height:60px;object-fit:cover;border-radius:6px;border:2px solid #7c3aed;';
        vid.muted = true; vid.preload = 'metadata';
        const lbl = document.createElement('div');
        lbl.textContent = (file.size/1024/1024).toFixed(1)+'MB';
        lbl.style = 'font-size:10px;color:#888;text-align:center;margin-top:2px;';
        wrap.appendChild(vid); wrap.appendChild(lbl);
        container.appendChild(wrap);
    });
}

function openEditDistrict(d) {
    const base = '{{ url("/admin/module-data/rent/districts") }}/';
    document.getElementById('editDistrictForm').action = base + d.id;
    document.getElementById('ed_name').value        = d.name || '';
    document.getElementById('ed_name_so').value     = d.name_so || '';
    document.getElementById('ed_description').value = d.description || '';
    document.getElementById('ed_status').value      = d.status || 'active';
    document.getElementById('editDistrictModal').classList.add('open');
}

// Close modal on backdrop click
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', function(e){ if(e.target === this) this.classList.remove('open'); });
});
</script>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TAB: AGENTS --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="tab-agents" class="tab-pane">

<style>
.agent-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 12px;
    transition: box-shadow .15s;
}
.agent-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.07); }
.agent-avatar {
    width: 48px; height: 48px; border-radius: 50%;
    background: linear-gradient(135deg, #0369A1, #0EA5E9);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 20px; flex-shrink: 0;
}
.agent-info { flex: 1; min-width: 0; }
.agent-name { font-weight: 700; font-size: 15px; color: var(--text); margin-bottom: 3px; }
.agent-meta { font-size: 12px; color: var(--text-muted); }
.agent-district { display:inline-block; background: rgba(14,165,233,.1); color:#0EA5E9; border-radius:8px; padding:2px 9px; font-size:11px; font-weight:600; margin-top:4px; }
.agent-status-badge { display:inline-block; padding:3px 10px; border-radius:10px; font-size:11px; font-weight:700; margin-bottom:6px; }
.badge-pending  { background:#fef3c7; color:#92400e; }
.badge-active   { background:#d1fae5; color:#065f46; }
.badge-inactive { background:#fee2e2; color:#991b1b; }
.agent-actions { display:flex; gap:8px; flex-shrink:0; flex-wrap:wrap; justify-content:flex-end; }
.btn-approve { background:#10b981; color:#fff; border:none; border-radius:8px; padding:7px 14px; font-size:12px; font-weight:700; cursor:pointer; }
.btn-approve:hover { background:#059669; }
.btn-reject  { background:#ef4444; color:#fff; border:none; border-radius:8px; padding:7px 14px; font-size:12px; font-weight:700; cursor:pointer; }
.btn-reject:hover { background:#dc2626; }
.btn-toggle-on  { background:#f59e0b; color:#fff; border:none; border-radius:8px; padding:7px 14px; font-size:12px; font-weight:700; cursor:pointer; }
.btn-toggle-off { background:#6366f1; color:#fff; border:none; border-radius:8px; padding:7px 14px; font-size:12px; font-weight:700; cursor:pointer; }
.agents-section-title { font-size: 13px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; margin: 20px 0 10px; }
.agent-stats-bar { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:24px; }
.agent-stat { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:14px 20px; flex:1; min-width:120px; }
.agent-stat-num { font-size:24px; font-weight:900; color:var(--brand); line-height:1; }
.agent-stat-lbl { font-size:12px; color:var(--text-muted); margin-top:3px; }
</style>

@php
    $pendingAgents  = $agents->where('status', 'pending');
    $activeAgents   = $agents->whereIn('status', ['active', 'inactive']);
@endphp

{{-- Stats --}}
<div class="agent-stats-bar">
    <div class="agent-stat">
        <div class="agent-stat-num">{{ $agents->count() }}</div>
        <div class="agent-stat-lbl">Total Agents</div>
    </div>
    <div class="agent-stat" style="border-color:#10b981;">
        <div class="agent-stat-num" style="color:#10b981;">{{ $activeAgents->count() }}</div>
        <div class="agent-stat-lbl">Active</div>
    </div>
    <div class="agent-stat" style="border-color:#f59e0b;">
        <div class="agent-stat-num" style="color:#f59e0b;">{{ $pendingAgents->count() }}</div>
        <div class="agent-stat-lbl">Pending Approval</div>
    </div>
</div>

{{-- Pending --}}
@if($pendingAgents->count())
<div class="agents-section-title" style="color:#92400e;"><i class="fas fa-clock" style="margin-right:6px;"></i>Pending Approval ({{ $pendingAgents->count() }})</div>
@foreach($pendingAgents as $agent)
<div class="agent-card" style="border-color:#fde68a;">
    <div class="agent-avatar"><i class="fas fa-user-tie"></i></div>
    <div class="agent-info">
        <div class="agent-name">{{ $agent->name }}</div>
        <div class="agent-meta">
            {{ $agent->phone ?? $agent->email ?? '—' }}
            &nbsp;·&nbsp; Registered {{ \Carbon\Carbon::parse($agent->created_at)->diffForHumans() }}
        </div>
        @if($agent->district_name)
        <span class="agent-district"><i class="fas fa-map-marker-alt" style="font-size:10px;"></i> {{ $agent->district_name }}</span>
        @endif
        <div style="margin-top:6px;"><span class="agent-status-badge badge-pending">Pending</span></div>
    </div>
    <div class="agent-actions">
        <form method="POST" action="{{ route('admin.module-data.rent.agent.approve', $agent->id) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn-approve" onclick="return confirm('Approve {{ addslashes($agent->name) }}?')">
                <i class="fas fa-check"></i> Approve
            </button>
        </form>
        <form method="POST" action="{{ route('admin.module-data.rent.agent.reject', $agent->id) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn-reject" onclick="return confirm('Reject and remove {{ addslashes($agent->name) }}?')">
                <i class="fas fa-times"></i> Reject
            </button>
        </form>
    </div>
</div>
@endforeach
@endif

{{-- Active / Inactive --}}
<div class="agents-section-title"><i class="fas fa-users" style="margin-right:6px;"></i>All Approved Agents ({{ $activeAgents->count() }})</div>
@forelse($activeAgents as $agent)
@php
    $propCount = \Illuminate\Support\Facades\DB::table('properties')->where('agent_user_id', $agent->id)->count();
    $walletId  = \Illuminate\Support\Facades\DB::table('wallets')
        ->where('owner_type', 'App\\Models\\User')->where('owner_id', $agent->id)->value('id');
    $earned    = $walletId
        ? \Illuminate\Support\Facades\DB::table('wallet_transactions')
            ->where('wallet_id', $walletId)->where('type', 'credit')->sum('amount')
        : 0;
@endphp
<div class="agent-card">
    <div class="agent-avatar"><i class="fas fa-user-tie"></i></div>
    <div class="agent-info">
        <div class="agent-name">{{ $agent->name }}</div>
        <div class="agent-meta">
            {{ $agent->phone ?? $agent->email ?? '—' }}
            &nbsp;·&nbsp; {{ $propCount }} propert{{ $propCount==1?'y':'ies' }}
            &nbsp;·&nbsp; ${{ number_format($earned, 2) }} earned
        </div>
        @if($agent->district_name)
        <span class="agent-district"><i class="fas fa-map-marker-alt" style="font-size:10px;"></i> {{ $agent->district_name }}</span>
        @endif
        <div style="margin-top:6px;">
            <span class="agent-status-badge {{ $agent->status === 'active' ? 'badge-active' : 'badge-inactive' }}">
                {{ $agent->status === 'active' ? 'Active' : 'Suspended' }}
            </span>
        </div>
    </div>
    <div class="agent-actions">
        <form method="POST" action="{{ route('admin.module-data.rent.agent.toggle', $agent->id) }}" style="display:inline;">
            @csrf
            <button type="submit" class="{{ $agent->status === 'active' ? 'btn-toggle-on' : 'btn-toggle-off' }}" onclick="return confirm('{{ $agent->status === 'active' ? 'Suspend' : 'Reactivate' }} this agent?')">
                <i class="fas fa-{{ $agent->status === 'active' ? 'ban' : 'check-circle' }}"></i>
                {{ $agent->status === 'active' ? 'Suspend' : 'Reactivate' }}
            </button>
        </form>
        <form method="POST" action="{{ route('admin.module-data.rent.agent.reject', $agent->id) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn-reject" onclick="return confirm('Permanently delete {{ addslashes($agent->name) }}?')">
                <i class="fas fa-trash"></i>
            </button>
        </form>
    </div>
</div>
@empty
<div style="text-align:center;padding:40px;color:var(--text-muted);">
    <i class="fas fa-user-tie" style="font-size:40px;opacity:.3;margin-bottom:12px;display:block;"></i>
    No approved agents yet.
</div>
@endforelse

</div>{{-- /tab-agents --}}

@endsection
