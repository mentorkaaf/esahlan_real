@extends('admin.layouts.app')
@section('title', 'eMoving — Moving Management')
@section('content')

<style>
:root { --mov:#FF8A00; --mov-dark:#07003B; --mov-light:rgba(255,138,0,.1); }
.mov-hero {
    background: linear-gradient(135deg, #07003B 0%, #1a0a5e 50%, #FF8A00 130%);
    border-radius:16px; padding:28px 32px; margin-bottom:24px;
    color:#fff; display:flex; align-items:center; justify-content:space-between;
    position:relative; overflow:hidden;
}
.mov-hero::before { content:''; position:absolute; right:-60px; top:-60px; width:240px; height:240px; border-radius:50%; background:rgba(255,255,255,.05); pointer-events:none; }
.mov-hero::after  { content:''; position:absolute; left:-30px; bottom:-50px; width:160px; height:160px; border-radius:50%; background:rgba(255,138,0,.12); pointer-events:none; }
.mov-hero-title   { font-size:22px; font-weight:800; margin:0 0 4px; }
.mov-hero-sub     { margin:0; opacity:.8; font-size:13px; }
.mov-hero-icon    { font-size:56px; opacity:.18; position:relative; z-index:1; }
.mov-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; margin-right:6px; margin-top:6px; background:rgba(255,138,0,.2); border:1px solid rgba(255,138,0,.4); color:#fff; }

/* STATS */
.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
.stat-card  { background:#fff; border-radius:14px; padding:20px 22px; box-shadow:0 1px 8px rgba(0,0,0,.06); display:flex; align-items:center; gap:16px; border:1px solid #f0f1f5; }
.stat-icon  { width:50px; height:50px; border-radius:13px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.stat-icon.orange { background:rgba(255,138,0,.1); color:#FF8A00; }
.stat-icon.navy   { background:rgba(7,0,59,.1); color:#07003B; }
.stat-icon.green  { background:rgba(16,185,129,.1); color:#10b981; }
.stat-icon.blue   { background:rgba(59,130,246,.1); color:#3b82f6; }
.stat-num { font-size:26px; font-weight:800; color:#1A1A2E; line-height:1; }
.stat-lbl { font-size:12px; color:#8A8A9A; margin-top:2px; }

/* TABS */
.module-tabs { display:flex; gap:4px; background:#f4f5fa; border-radius:12px; padding:4px; margin-bottom:24px; }
.module-tab  { padding:9px 18px; border-radius:9px; border:none; background:transparent; cursor:pointer; font-size:13px; font-weight:600; color:#8A8A9A; transition:all .2s; display:flex; align-items:center; gap:7px; font-family:inherit; }
.module-tab.active { background:#fff; color:#FF8A00; box-shadow:0 1px 6px rgba(0,0,0,.08); }
.module-tab:hover:not(.active) { color:#1A1A2E; background:rgba(255,255,255,.6); }
.tab-pane { display:none; } .tab-pane.active { display:block; }

/* SECTION CARD */
.sc { background:#fff; border-radius:14px; box-shadow:0 1px 8px rgba(0,0,0,.06); overflow:hidden; margin-bottom:20px; border:1px solid #f0f1f5; }
.sc-head { padding:18px 22px; border-bottom:1px solid #f0f1f5; display:flex; align-items:center; justify-content:space-between; }
.sc-title { font-size:15px; font-weight:700; color:#1A1A2E; display:flex; align-items:center; gap:8px; }

/* TABLE */
.dtbl { width:100%; border-collapse:collapse; }
.dtbl th { padding:11px 16px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#8A8A9A; border-bottom:1px solid #f0f1f5; background:#fafbff; white-space:nowrap; }
.dtbl td { padding:12px 16px; font-size:13px; color:#1A1A2E; border-bottom:1px solid #f0f1f5; vertical-align:middle; }
.dtbl tr:last-child td { border-bottom:none; }
.dtbl tr:hover td { background:rgba(255,138,0,.02); }
.dtbl .empty-row td { text-align:center; padding:40px; color:#8A8A9A; }

/* TYPE GROUP */
.type-group-header { padding:10px 18px; background:linear-gradient(90deg,rgba(255,138,0,.07),transparent); border-bottom:1px solid #f0f1f5; font-weight:700; font-size:13px; color:#FF8A00; display:flex; align-items:center; gap:8px; }

/* BADGES */
.badge-xs { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-active   { background:rgba(16,185,129,.1); color:#10b981; }
.badge-inactive { background:rgba(239,68,68,.1); color:#ef4444; }
.badge-type     { background:rgba(255,138,0,.1); color:#FF8A00; }

/* PRICE */
.price-tag { font-size:14px; font-weight:800; color:#FF8A00; }

/* PACKAGE CARD */
.pkg-card { border:1.5px solid #f0f1f5; border-radius:14px; padding:18px 20px; margin-bottom:12px; transition:all .2s; position:relative; }
.pkg-card:hover { border-color:#FF8A00; box-shadow:0 4px 16px rgba(255,138,0,.1); }
.pkg-price-badge { position:absolute; top:14px; right:16px; background:linear-gradient(135deg,#FF8A00,#e67900); color:#fff; padding:4px 12px; border-radius:20px; font-size:13px; font-weight:800; }

/* FORMS */
.form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; }
.form-grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }
.fgroup label { font-size:12px; font-weight:600; color:#1A1A2E; margin-bottom:5px; display:block; }
.fgroup input, .fgroup select, .fgroup textarea { width:100%; padding:9px 12px; border:1.5px solid #EEEEEE; border-radius:10px; font-size:13px; color:#1A1A2E; background:#fff; font-family:inherit; transition:border-color .15s; }
.fgroup input:focus, .fgroup select:focus, .fgroup textarea:focus { outline:none; border-color:#FF8A00; }
.fgroup textarea { resize:vertical; min-height:70px; }

/* ROUTE DISPLAY */
.route-display { display:flex; align-items:center; gap:8px; }
.route-city    { font-size:13px; font-weight:700; color:#1A1A2E; }
.route-arrow   { color:#8A8A9A; font-size:12px; }

/* ACTION BTNS */
.btn-icon { width:32px; height:32px; border-radius:8px; border:none; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; font-size:13px; transition:all .15s; }
.btn-icon.edit  { background:rgba(59,130,246,.1); color:#3b82f6; }
.btn-icon.del   { background:rgba(239,68,68,.1); color:#ef4444; }
.btn-icon:hover { transform:scale(1.08); }
.btn-add { display:inline-flex; align-items:center; gap:7px; padding:9px 18px; background:#FF8A00; color:#fff; border:none; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer; font-family:inherit; transition:opacity .15s; }
.btn-add:hover { opacity:.9; }
.btn-outline { display:inline-flex; align-items:center; gap:7px; padding:8px 16px; background:transparent; color:#FF8A00; border:1.5px solid #FF8A00; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer; font-family:inherit; transition:all .15s; }
.btn-outline:hover { background:#FF8A00; color:#fff; }

/* MODAL */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; display:none; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal-box { background:#fff; border-radius:18px; width:min(560px,95vw); max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.18); }
.modal-head { padding:22px 26px; border-bottom:1px solid #f0f1f5; display:flex; align-items:center; justify-content:space-between; }
.modal-head h3 { font-size:17px; font-weight:800; color:#1A1A2E; margin:0; }
.modal-close { width:32px; height:32px; border-radius:8px; border:none; background:#f4f5fa; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; }
.modal-body { padding:24px 26px; }

/* ALERTS */
.alert-success { background:rgba(16,185,129,.08); border:1px solid rgba(16,185,129,.3); color:#065f46; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13px; font-weight:600; }
.alert-error   { background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.3); color:#7f1d1d; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13px; font-weight:600; }

/* EXTRAS GRID */
.extras-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:14px; padding:20px; }
.extra-card { border:1.5px solid #f0f1f5; border-radius:12px; padding:14px 16px; display:flex; align-items:center; justify-content:space-between; transition:all .2s; }
.extra-card:hover { border-color:#FF8A00; }

/* TYPE FILTER BUTTONS */
.type-filter-btn { padding:6px 14px; border-radius:20px; border:1.5px solid #EEEEEE; background:#fff; cursor:pointer; font-size:12px; font-weight:600; color:#8A8A9A; display:inline-flex; align-items:center; gap:6px; font-family:inherit; transition:all .15s; }
.type-filter-btn:hover { border-color:#FF8A00; color:#FF8A00; }
.type-filter-btn.active { background:#FF8A00; border-color:#FF8A00; color:#fff; }

/* TYPE GROUP SECTION */
.type-group-section { transition:all .2s; }
.type-group-section.hidden { display:none; }
</style>

{{-- ALERTS --}}
@if(session('success'))
<div class="alert-success"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="alert-error"><i class="fas fa-exclamation-circle me-2"></i>{{ $errors->first() }}</div>
@endif

{{-- HERO --}}
<div class="mov-hero">
    <div style="position:relative;z-index:1">
        <h2 class="mov-hero-title"><i class="fas fa-truck-moving me-2"></i>eMoving — Moving Management</h2>
        <p class="mov-hero-sub">Manage route pricing, packages, extra services and fleet</p>
        <div style="margin-top:8px">
            <span class="mov-badge"><i class="fas fa-home me-1"></i>House Moving</span>
            <span class="mov-badge"><i class="fas fa-building me-1"></i>Office Moving</span>
            <span class="mov-badge"><i class="fas fa-box me-1"></i>Single Item</span>
        </div>
    </div>
    <div class="mov-hero-icon"><i class="fas fa-shipping-fast"></i></div>
</div>

{{-- STATS --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-num">{{ isset($pendingOrders) ? $pendingOrders : '—' }}</div>
            <div class="stat-lbl">Pending Orders</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon navy"><i class="fas fa-route"></i></div>
        <div>
            <div class="stat-num">{{ $pricings->count() }}</div>
            <div class="stat-lbl">Route Pricings</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-boxes"></i></div>
        <div>
            <div class="stat-num">{{ $packages->count() }}</div>
            <div class="stat-lbl">Packages</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-plus-circle"></i></div>
        <div>
            <div class="stat-num">{{ $extras->count() }}</div>
            <div class="stat-lbl">Extra Services</div>
        </div>
    </div>
</div>

{{-- TABS --}}
<div class="module-tabs">
    <button class="module-tab active" onclick="switchTab('pricing',this)"><i class="fas fa-route"></i> Route Pricing</button>
    <button class="module-tab" onclick="switchTab('packages',this)"><i class="fas fa-boxes"></i> Packages</button>
    <button class="module-tab" onclick="switchTab('extras',this)"><i class="fas fa-plus-circle"></i> Extra Services</button>
    <button class="module-tab" onclick="switchTab('add-pricing',this)"><i class="fas fa-plus"></i> Add Pricing</button>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TAB: ROUTE PRICING --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="tab-pricing" class="tab-pane active">
    <div class="sc">
        <div class="sc-head">
            <div class="sc-title"><i class="fas fa-route" style="color:#FF8A00"></i> Route Pricing Matrix</div>
            <div style="display:flex;align-items:center;gap:10px">
                <input type="text" placeholder="Search routes…" oninput="filterPricing(this.value)" style="padding:7px 12px;border:1.5px solid #EEEEEE;border-radius:10px;font-size:13px;width:180px">
                <button class="btn-add" onclick="switchTab('add-pricing',document.querySelector('[onclick*=add-pricing]'))"><i class="fas fa-plus"></i> Add Pricing</button>
            </div>
        </div>
        {{-- Move type filter pills --}}
        <div style="padding:12px 20px;border-bottom:1px solid #f0f1f5;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <span style="font-size:12px;font-weight:600;color:#8A8A9A;margin-right:4px">Filter:</span>
            <button class="type-filter-btn active" data-type="all" onclick="filterByType('all',this)">
                <i class="fas fa-th-large"></i> All Types
            </button>
            <button class="type-filter-btn" data-type="house" onclick="filterByType('house',this)">
                <i class="fas fa-home"></i> House
            </button>
            <button class="type-filter-btn" data-type="office" onclick="filterByType('office',this)">
                <i class="fas fa-building"></i> Office
            </button>
            <button class="type-filter-btn" data-type="commercial" onclick="filterByType('commercial',this)">
                <i class="fas fa-store"></i> Commercial
            </button>
            <button class="type-filter-btn" data-type="single_item" onclick="filterByType('single_item',this)">
                <i class="fas fa-box"></i> Single Item
            </button>
        </div>
        @php $grouped = $pricings->groupBy('move_type'); @endphp
        @foreach(['house','office','commercial','single_item'] as $mtype)
            @if($grouped->has($mtype))
            <div class="type-group-section" data-type="{{ $mtype }}">
                <div class="type-group-header">
                    <i class="fas fa-{{ $mtype === 'house' ? 'home' : ($mtype === 'office' ? 'building' : ($mtype === 'commercial' ? 'store' : 'box')) }}"></i>
                    {{ ucfirst(str_replace('_',' ',$mtype)) }} Moving
                    <span style="margin-left:auto;background:rgba(255,138,0,.15);color:#FF8A00;padding:2px 10px;border-radius:20px;font-size:11px">{{ $grouped[$mtype]->count() }} routes</span>
                </div>
                <table class="dtbl pricing-table" data-type="{{ $mtype }}">
                    <thead>
                        <tr>
                            <th>From District</th>
                            <th>To District</th>
                            <th>Vehicle</th>
                            <th>Base Price</th>
                            <th>Per Room</th>
                            <th style="color:#2e7d32;">Distance Price</th>
                            <th>Status</th>
                            <th style="text-align:center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($grouped[$mtype] as $pr)
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <span style="width:8px;height:8px;border-radius:50%;background:#FF8A00;display:inline-block;flex-shrink:0"></span>
                                    <strong>{{ $pr->fromDistrict?->name ?? '—' }}</strong>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <span style="width:8px;height:8px;border-radius:50%;background:#10b981;display:inline-block;flex-shrink:0"></span>
                                    <strong>{{ $pr->toDistrict?->name ?? '—' }}</strong>
                                </div>
                            </td>
                            <td><span style="background:#f4f5fa;padding:3px 9px;border-radius:6px;font-size:12px">{{ $pr->vehicle_type ?? '—' }}</span></td>
                            <td><span class="price-tag">${{ number_format($pr->base_price) }}</span></td>
                            <td>${{ number_format($pr->price_per_room ?? 0) }}<span style="font-size:10px;color:#8A8A9A">/room</span></td>
                            <td><span class="price-tag" style="background:#e8f5e9;color:#2e7d32;">${{ number_format($pr->distance_price ?? 20) }}</span></td>
                            <td>
                                <span class="badge-xs {{ $pr->is_active ? 'badge-active' : 'badge-inactive' }}">
                                    {{ $pr->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;justify-content:center">
                                    <button class="btn-icon edit" onclick="openEditPricing({{ json_encode($pr) }})" title="Edit"><i class="fas fa-pen"></i></button>
                                    <form method="POST" action="{{ route('admin.module-data.moving.pricing.destroy', $pr->id) }}" onsubmit="return confirm('Delete this pricing?')" style="display:inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-icon del" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        @endforeach
        @if($pricings->isEmpty())
        <div style="text-align:center;padding:50px;color:#8A8A9A">
            <i class="fas fa-route" style="font-size:40px;color:#EEEEEE;display:block;margin-bottom:12px"></i>
            <div style="font-size:15px;font-weight:600;margin-bottom:6px">No Route Pricings Yet</div>
            <div style="font-size:13px">Click "Add Pricing" to set up route prices for any move type</div>
        </div>
        @endif
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TAB: PACKAGES --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="tab-packages" class="tab-pane">
    <div class="sc">
        <div class="sc-head">
            <div class="sc-title"><i class="fas fa-boxes" style="color:#FF8A00"></i> Moving Packages</div>
            <button class="btn-add" onclick="document.getElementById('addPkgModal').classList.add('open')"><i class="fas fa-plus"></i> Add Package</button>
        </div>
        <div style="padding:0 20px 8px;border-bottom:1px solid #f0f1f5;display:flex;align-items:center;gap:6px;flex-wrap:wrap;padding-top:14px">
            <span style="font-size:12px;font-weight:600;color:#8A8A9A">Supported types:</span>
            @foreach(['house'=>'Home','office'=>'Office','commercial'=>'Commercial','single_item'=>'Single Item'] as $t => $tl)
            <span style="background:rgba(255,138,0,.1);color:#FF8A00;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;border:1px solid rgba(255,138,0,.25)">
                <i class="fas fa-{{ $t === 'house' ? 'home' : ($t === 'office' ? 'building' : ($t === 'commercial' ? 'store' : 'box')) }}"></i>
                {{ $tl }}
            </span>
            @endforeach
        </div>
        <div style="padding:20px">
            @php $pkgGrouped = collect($packages)->groupBy('move_type'); @endphp
            @php $hasAny = false; @endphp
            @foreach(['house','office','commercial','single_item'] as $mtype)
                @if(isset($pkgGrouped[$mtype]) && $pkgGrouped[$mtype]->count())
                @php $hasAny = true; @endphp
                <div style="margin-bottom:24px">
                    <div style="font-size:13px;font-weight:700;color:#FF8A00;margin-bottom:12px;display:flex;align-items:center;gap:8px;padding-bottom:8px;border-bottom:1.5px solid rgba(255,138,0,.15)">
                        <i class="fas fa-{{ $mtype === 'house' ? 'home' : ($mtype === 'office' ? 'building' : ($mtype === 'commercial' ? 'store' : 'box')) }}"></i>
                        {{ ucfirst(str_replace('_',' ',$mtype)) }} Packages
                        <span style="margin-left:auto;background:rgba(255,138,0,.1);color:#FF8A00;padding:1px 9px;border-radius:20px;font-size:11px;font-weight:600">{{ $pkgGrouped[$mtype]->count() }}</span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                        @foreach($pkgGrouped[$mtype] as $pkg)
                        <div class="pkg-card">
                            <div class="pkg-price-badge">${{ number_format($pkg->price) }}</div>
                            <div style="font-size:15px;font-weight:800;color:#1A1A2E;margin-bottom:4px;padding-right:80px">{{ $pkg->name }}</div>
                            <div style="font-size:12px;color:#8A8A9A;margin-bottom:10px">{{ $pkg->description ?? '' }}</div>
                            @php
                                $includes = json_decode($pkg->includes ?? $pkg->features ?? '[]', true) ?? [];
                            @endphp
                            @if(!empty($includes))
                            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px">
                                @foreach($includes as $f)
                                <span style="background:rgba(255,138,0,.1);color:#FF8A00;padding:2px 8px;border-radius:20px;font-size:11px;border:1px solid rgba(255,138,0,.2)">{{ $f }}</span>
                                @endforeach
                            </div>
                            @endif
                            <div style="display:flex;gap:6px;align-items:center">
                                <span class="badge-xs {{ $pkg->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $pkg->is_active ? 'Active' : 'Inactive' }}</span>
                                <div style="margin-left:auto;display:flex;gap:6px">
                                    <button class="btn-icon edit" onclick="openEditPkg({{ json_encode($pkg) }})"><i class="fas fa-pen"></i></button>
                                    <form method="POST" action="{{ route('admin.module-data.moving.package.destroy', $pkg->id) }}" onsubmit="return confirm('Delete?')" style="display:inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-icon del"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            @endforeach
            @if(!$hasAny)
            <div style="text-align:center;padding:50px;color:#8A8A9A">
                <i class="fas fa-boxes" style="font-size:40px;color:#EEEEEE;display:block;margin-bottom:12px"></i>
                <div style="font-size:15px;font-weight:600;margin-bottom:6px">No Packages Yet</div>
                <div style="font-size:13px">Add packages for House, Office, Commercial, or Single Item moves</div>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TAB: EXTRA SERVICES --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="tab-extras" class="tab-pane">
    <div class="sc">
        <div class="sc-head">
            <div class="sc-title"><i class="fas fa-plus-circle" style="color:#FF8A00"></i> Extra Services</div>
            <button class="btn-add" onclick="document.getElementById('addExtraModal').classList.add('open')"><i class="fas fa-plus"></i> Add Service</button>
        </div>
        <div class="extras-grid">
            @forelse($extras as $ex)
            <div class="extra-card">
                <div>
                    <div style="font-size:14px;font-weight:700;color:#1A1A2E;margin-bottom:3px">{{ $ex->name }}</div>
                    <div style="font-size:12px;color:#8A8A9A">{{ $ex->description ?? '' }}</div>
                    <div style="font-size:16px;font-weight:800;color:#FF8A00;margin-top:6px">${{ number_format($ex->price) }}</div>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end">
                    <span class="badge-xs {{ $ex->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $ex->is_active ? 'Active' : 'Off' }}</span>
                    <div style="display:flex;gap:4px">
                        <button class="btn-icon edit" onclick="openEditExtra({{ json_encode($ex) }})"><i class="fas fa-pen"></i></button>
                        <form method="POST" action="{{ route('admin.module-data.moving.extra.destroy', $ex->id) }}" onsubmit="return confirm('Delete?')" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-icon del"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
            @empty
            <div style="text-align:center;padding:40px;color:#8A8A9A;grid-column:1/-1">
                <i class="fas fa-plus-circle" style="font-size:36px;color:#EEEEEE;display:block;margin-bottom:10px"></i>
                No extra services yet
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TAB: ADD PRICING --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="tab-add-pricing" class="tab-pane">
    <div class="sc">
        <div class="sc-head">
            <div class="sc-title"><i class="fas fa-plus" style="color:#FF8A00"></i> Add Route Pricing</div>
        </div>
        <div style="padding:24px">
            <form method="POST" action="{{ route('admin.module-data.moving.pricing.store') }}">
                @csrf
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup">
                        <label>From District <span style="color:#ef4444">*</span></label>
                        <select name="from_district_id" required>
                            <option value="">Select district</option>
                            @foreach($districts as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fgroup">
                        <label>To District <span style="color:#ef4444">*</span></label>
                        <select name="to_district_id" required>
                            <option value="">Select district</option>
                            @foreach($districts as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup">
                        <label>Move Type <span style="color:#ef4444">*</span></label>
                        <select name="move_type" required>
                            <option value="">Select type</option>
                            <option value="house">House Moving</option>
                            <option value="office">Office Moving</option>
                            <option value="commercial">Commercial Moving</option>
                            <option value="single_item">Single Item</option>
                        </select>
                    </div>
                    <div class="fgroup">
                        <label>Vehicle Type <span style="color:#ef4444">*</span></label>
                        <input type="text" name="vehicle_type" required placeholder="e.g. Small Truck, Large Truck, Van">
                    </div>
                </div>
                <div class="form-grid-3" style="margin-bottom:14px">
                    <div class="fgroup">
                        <label>Base Price ($) <span style="color:#ef4444">*</span></label>
                        <input type="number" name="base_price" min="0" step="0.01" required>
                    </div>
                    <div class="fgroup">
                        <label>Price Per Room ($)</label>
                        <input type="number" name="price_per_room" min="0" step="0.01" value="0">
                    </div>
                    <div class="fgroup">
                        <label>Distance Price ($) <small style="color:#2e7d32;">Driver Earning</small></label>
                        <input type="number" name="distance_price" min="0" step="0.01" value="20">
                    </div>
                    <div class="fgroup">
                        <label>Notes</label>
                        <input type="text" name="notes" placeholder="Optional notes">
                    </div>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="reset" class="btn-outline">Reset</button>
                    <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Add Pricing</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════ MODALS ══════════════════════════ --}}

{{-- ADD PACKAGE MODAL --}}
<div class="modal-overlay" id="addPkgModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Add Package</h3>
            <button class="modal-close" onclick="document.getElementById('addPkgModal').classList.remove('open')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('admin.module-data.moving.package.store') }}">
                @csrf
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Package Name *</label><input type="text" name="name" required placeholder="e.g. Basic House Move"></div>
                    <div class="fgroup">
                        <label>Move Type *</label>
                        <select name="move_type" required>
                            <option value="house">House</option>
                            <option value="office">Office</option>
                            <option value="commercial">Commercial</option>
                            <option value="single_item">Single Item</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Price ($) *</label><input type="number" name="price" min="0" step="0.01" required></div>
                    <div class="fgroup"><label>Sort Order</label><input type="number" name="sort_order" value="0" min="0"></div>
                </div>
                <div class="fgroup" style="margin-bottom:14px">
                    <label>Description</label>
                    <textarea name="description" placeholder="Package description…"></textarea>
                </div>
                <div class="fgroup" style="margin-bottom:14px">
                    <label>Features <span style="font-size:11px;color:#8A8A9A">(comma-separated)</span></label>
                    <input type="text" name="features" placeholder="Up to 2 rooms, 1 truck, loading & unloading…">
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="button" class="btn-outline" onclick="document.getElementById('addPkgModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Add Package</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- EDIT PACKAGE MODAL --}}
<div class="modal-overlay" id="editPkgModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Edit Package</h3>
            <button class="modal-close" onclick="document.getElementById('editPkgModal').classList.remove('open')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="editPkgForm" method="POST">
                @csrf @method('PUT')
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Package Name</label><input type="text" name="name" id="ep_name" required></div>
                    <div class="fgroup">
                        <label>Move Type</label>
                        <select name="move_type" id="ep_mtype">
                            <option value="house">House</option><option value="office">Office</option>
                            <option value="commercial">Commercial</option><option value="single_item">Single Item</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Price ($)</label><input type="number" name="price" id="ep_price" min="0" step="0.01"></div>
                    <div class="fgroup"><label>Sort Order</label><input type="number" name="sort_order" id="ep_sort" min="0"></div>
                </div>
                <div class="fgroup" style="margin-bottom:14px"><label>Description</label><textarea name="description" id="ep_desc"></textarea></div>
                <div class="fgroup" style="margin-bottom:14px"><label>Features (comma-separated)</label><input type="text" name="features" id="ep_features"></div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="button" class="btn-outline" onclick="document.getElementById('editPkgModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ADD EXTRA MODAL --}}
<div class="modal-overlay" id="addExtraModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Add Extra Service</h3>
            <button class="modal-close" onclick="document.getElementById('addExtraModal').classList.remove('open')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('admin.module-data.moving.extra.store') }}">
                @csrf
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Service Name *</label><input type="text" name="name" required placeholder="e.g. Packing Service"></div>
                    <div class="fgroup"><label>Price ($) *</label><input type="number" name="price" min="0" step="0.01" required></div>
                </div>
                <div class="fgroup" style="margin-bottom:14px"><label>Description</label><textarea name="description" placeholder="Service description…"></textarea></div>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Icon (FA class)</label><input type="text" name="icon" placeholder="fas fa-box"></div>
                    <div class="fgroup"><label>Sort Order</label><input type="number" name="sort_order" value="0" min="0"></div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="button" class="btn-outline" onclick="document.getElementById('addExtraModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Add Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- EDIT EXTRA MODAL --}}
<div class="modal-overlay" id="editExtraModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Edit Extra Service</h3>
            <button class="modal-close" onclick="document.getElementById('editExtraModal').classList.remove('open')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="editExtraForm" method="POST">
                @csrf @method('PUT')
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Name</label><input type="text" name="name" id="ee_name" required></div>
                    <div class="fgroup"><label>Price ($)</label><input type="number" name="price" id="ee_price" min="0" step="0.01"></div>
                </div>
                <div class="fgroup" style="margin-bottom:14px"><label>Description</label><textarea name="description" id="ee_desc"></textarea></div>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Is Active</label><select name="is_active" id="ee_active"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                    <div class="fgroup"><label>Sort Order</label><input type="number" name="sort_order" id="ee_sort" min="0"></div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="button" class="btn-outline" onclick="document.getElementById('editExtraModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- EDIT PRICING MODAL --}}
<div class="modal-overlay" id="editPricingModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Edit Route Pricing</h3>
            <button class="modal-close" onclick="document.getElementById('editPricingModal').classList.remove('open')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="editPricingForm" method="POST">
                @csrf
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>From District</label><select name="from_district_id" id="epr_from">@foreach($districts as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
                    <div class="fgroup"><label>To District</label><select name="to_district_id" id="epr_to">@foreach($districts as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
                </div>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Move Type</label><select name="move_type" id="epr_type"><option value="house">House</option><option value="office">Office</option><option value="commercial">Commercial</option><option value="single_item">Single Item</option></select></div>
                    <div class="fgroup"><label>Vehicle Type</label><input type="text" name="vehicle_type" id="epr_vehicle"></div>
                </div>
                <div class="form-grid-3" style="margin-bottom:14px">
                    <div class="fgroup"><label>Base Price ($)</label><input type="number" name="base_price" id="epr_base" min="0" step="0.01"></div>
                    <div class="fgroup"><label>Per Room ($)</label><input type="number" name="price_per_room" id="epr_room" min="0" step="0.01"></div>
                    <div class="fgroup"><label>Distance Price ($) <small style="color:#2e7d32;">Driver</small></label><input type="number" name="distance_price" id="epr_dist" min="0" step="0.01"></div>
                    <div class="fgroup"><label>Active</label><select name="is_active" id="epr_active"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="button" class="btn-outline" onclick="document.getElementById('editPricingModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function switchTab(name, el) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.module-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    el.classList.add('active');
}
function filterPricing(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.pricing-table tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
function filterByType(type, btn) {
    document.querySelectorAll('.type-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.type-group-section').forEach(section => {
        if (type === 'all' || section.dataset.type === type) {
            section.classList.remove('hidden');
        } else {
            section.classList.add('hidden');
        }
    });
}
function openEditPkg(pkg) {
    let rawIncludes = pkg.includes || pkg.features || '[]';
    if (typeof rawIncludes === 'string') {
        try { rawIncludes = JSON.parse(rawIncludes); } catch(e) { rawIncludes = []; }
    }
    if (!Array.isArray(rawIncludes)) rawIncludes = [];
    document.getElementById('editPkgForm').action = `/admin/module-data/moving/package/${pkg.id}`;
    document.getElementById('ep_name').value    = pkg.name || '';
    document.getElementById('ep_mtype').value   = pkg.move_type || 'house';
    document.getElementById('ep_price').value   = pkg.price || 0;
    document.getElementById('ep_sort').value    = pkg.sort_order || 0;
    document.getElementById('ep_desc').value    = pkg.description || '';
    document.getElementById('ep_features').value= rawIncludes.join(', ');
    document.getElementById('editPkgModal').classList.add('open');
}
function openEditExtra(ex) {
    document.getElementById('editExtraForm').action = `/admin/module-data/moving/extra/${ex.id}`;
    document.getElementById('ee_name').value   = ex.name || '';
    document.getElementById('ee_price').value  = ex.price || 0;
    document.getElementById('ee_desc').value   = ex.description || '';
    document.getElementById('ee_active').value = ex.is_active ? '1' : '0';
    document.getElementById('ee_sort').value   = ex.sort_order || 0;
    document.getElementById('editExtraModal').classList.add('open');
}
function openEditPricing(pr) {
    document.getElementById('editPricingForm').action = `/admin/module-data/moving/pricing/${pr.id}`;
    document.getElementById('epr_from').value    = pr.from_district_id || '';
    document.getElementById('epr_to').value      = pr.to_district_id || '';
    document.getElementById('epr_type').value    = pr.move_type || 'house';
    document.getElementById('epr_vehicle').value = pr.vehicle_type || '';
    document.getElementById('epr_base').value    = pr.base_price || 0;
    document.getElementById('epr_room').value    = pr.price_per_room || 0;
    document.getElementById('epr_dist').value    = pr.distance_price || 20;
    document.getElementById('epr_active').value  = pr.is_active ? '1' : '0';
    document.getElementById('editPricingModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', function(e){ if(e.target === this) this.classList.remove('open'); });
});
</script>

@endsection
