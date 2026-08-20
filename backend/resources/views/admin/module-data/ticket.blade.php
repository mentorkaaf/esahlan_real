@extends('admin.layouts.app')
@section('title', 'eTicket — Flight Management')
@section('content')

<style>
:root{--tkt:#1565C0;--tkt2:#0D47A1;--tkt-light:rgba(21,101,192,.1);}
/* HERO */
.tkt-hero{background:linear-gradient(135deg,#07003B 0%,#0D47A1 55%,#1976D2 100%);border-radius:16px;padding:26px 32px;margin-bottom:24px;color:#fff;display:flex;align-items:center;justify-content:space-between;position:relative;overflow:hidden;}
.tkt-hero::before{content:'';position:absolute;right:-60px;top:-60px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.05);pointer-events:none;}
.tkt-hero::after{content:'';position:absolute;left:-30px;bottom:-50px;width:160px;height:160px;border-radius:50%;background:rgba(255,138,0,.1);pointer-events:none;}
.tkt-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;margin-right:6px;margin-top:6px;background:rgba(255,138,0,.2);border:1px solid rgba(255,138,0,.4);color:#fff;}
/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.stat-card{background:#fff;border-radius:14px;padding:20px 22px;box-shadow:0 1px 8px rgba(0,0,0,.06);display:flex;align-items:center;gap:16px;border:1px solid #f0f1f5;}
.stat-icon{width:50px;height:50px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.stat-icon.blue{background:rgba(21,101,192,.1);color:#1565C0;}
.stat-icon.orange{background:rgba(255,138,0,.1);color:#FF8A00;}
.stat-icon.green{background:rgba(16,185,129,.1);color:#10b981;}
.stat-icon.purple{background:rgba(124,58,237,.1);color:#7c3aed;}
.stat-num{font-size:26px;font-weight:800;color:#1A1A2E;line-height:1;}
.stat-lbl{font-size:12px;color:#8A8A9A;margin-top:2px;}
/* TABS */
.module-tabs{display:flex;gap:4px;background:#f4f5fa;border-radius:12px;padding:4px;margin-bottom:24px;}
.module-tab{padding:9px 18px;border-radius:9px;border:none;background:transparent;cursor:pointer;font-size:13px;font-weight:600;color:#8A8A9A;transition:all .2s;display:flex;align-items:center;gap:7px;font-family:inherit;}
.module-tab.active{background:#fff;color:#1565C0;box-shadow:0 1px 6px rgba(0,0,0,.08);}
.module-tab:hover:not(.active){color:#1A1A2E;background:rgba(255,255,255,.6);}
.tab-pane{display:none;}.tab-pane.active{display:block;}
/* SECTION CARD */
.sc{background:#fff;border-radius:14px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;margin-bottom:20px;border:1px solid #f0f1f5;}
.sc-head{padding:18px 22px;border-bottom:1px solid #f0f1f5;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
.sc-title{font-size:15px;font-weight:700;color:#1A1A2E;display:flex;align-items:center;gap:8px;}
/* TABLE */
.dtbl{width:100%;border-collapse:collapse;}
.dtbl th{padding:11px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#8A8A9A;border-bottom:1px solid #f0f1f5;background:#fafbff;white-space:nowrap;}
.dtbl td{padding:12px 16px;font-size:13px;color:#1A1A2E;border-bottom:1px solid #f0f1f5;vertical-align:middle;}
.dtbl tr:last-child td{border-bottom:none;}
.dtbl tr:hover td{background:rgba(21,101,192,.02);}
.empty-row td{text-align:center;padding:40px;color:#8A8A9A;}
/* AIRLINE AVATAR */
.airline-avatar{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:12px;flex-shrink:0;overflow:hidden;}
/* ROUTE CHIP */
.route-pair{display:flex;align-items:center;gap:8px;}
.iata-code{font-size:16px;font-weight:800;color:#1A1A2E;}
.city-name{font-size:11px;color:#8A8A9A;}
/* BADGE */
.badge-xs{display:inline-block;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;}
.badge-active{background:rgba(16,185,129,.1);color:#10b981;}
.badge-inactive{background:rgba(239,68,68,.1);color:#ef4444;}
.badge-scheduled{background:rgba(21,101,192,.1);color:#1565C0;}
.badge-boarding{background:rgba(245,158,11,.1);color:#f59e0b;}
.badge-departed{background:rgba(124,58,237,.1);color:#7c3aed;}
.badge-landed{background:rgba(16,185,129,.1);color:#10b981;}
.badge-cancelled{background:rgba(239,68,68,.1);color:#ef4444;}
.badge-domestic{background:rgba(21,101,192,.1);color:#1565C0;}
.badge-international{background:rgba(124,58,237,.1);color:#7c3aed;}
/* PRICE PILLS */
.price-eco{background:rgba(16,185,129,.1);color:#10b981;}
.price-biz{background:rgba(21,101,192,.1);color:#1565C0;}
.price-chd{background:rgba(245,158,11,.1);color:#f59e0b;}
.price-inf{background:rgba(239,68,68,.08);color:#ef4444;}
.price-pill{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;}
/* FORMS */
.form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;}
.form-grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;}
.fgroup label{font-size:12px;font-weight:600;color:#1A1A2E;margin-bottom:5px;display:block;}
.fgroup input,.fgroup select,.fgroup textarea{width:100%;padding:9px 12px;border:1.5px solid #EEEEEE;border-radius:10px;font-size:13px;color:#1A1A2E;background:#fff;font-family:inherit;transition:border-color .15s;box-sizing:border-box;}
.fgroup input:focus,.fgroup select:focus,.fgroup textarea:focus{outline:none;border-color:#1565C0;}
.prices-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;}
.price-input-wrap{border:1.5px solid #EEEEEE;border-radius:10px;padding:10px 12px;}
.price-input-wrap label{font-size:11px;font-weight:700;margin-bottom:5px;display:block;}
.price-input-wrap input{width:100%;border:none;font-size:14px;font-weight:800;color:#1A1A2E;outline:none;background:transparent;}
.sec-title{font-size:13px;font-weight:700;color:#8A8A9A;text-transform:uppercase;letter-spacing:.5px;margin:0 0 14px;padding-bottom:8px;border-bottom:1px solid #f0f1f5;}
/* BTNS */
.btn-icon{width:32px;height:32px;border-radius:8px;border:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:13px;transition:all .15s;}
.btn-icon.edit{background:rgba(59,130,246,.1);color:#3b82f6;}
.btn-icon.del{background:rgba(239,68,68,.1);color:#ef4444;}
.btn-icon:hover{transform:scale(1.08);}
.btn-add{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:#1565C0;color:#fff;border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:opacity .15s;}
.btn-add:hover{opacity:.9;}
.btn-outline{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;background:transparent;color:#1565C0;border:1.5px solid #1565C0;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s;}
.btn-outline:hover{background:#1565C0;color:#fff;}
/* MODAL */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;display:none;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal-box{background:#fff;border-radius:18px;width:min(700px,95vw);max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.18);}
.modal-box.sm{width:min(500px,95vw);}
.modal-head{padding:22px 26px;border-bottom:1px solid #f0f1f5;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1;}
.modal-head h3{font-size:17px;font-weight:800;color:#1A1A2E;margin:0;}
.modal-close{width:32px;height:32px;border-radius:8px;border:none;background:#f4f5fa;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;}
.modal-body{padding:24px 26px;}
/* ALERTS */
.alert-success{background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.3);color:#065f46;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13px;font-weight:600;}
.alert-error{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);color:#7f1d1d;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13px;font-weight:600;}
/* CITY PRESETS */
.city-presets{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;}
.city-preset{padding:5px 12px;background:#f4f5fa;border:1.5px solid #EEEEEE;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;color:#1A1A2E;}
.city-preset:hover{background:#1565C0;color:#fff;border-color:#1565C0;}
.city-preset.intl{border-color:rgba(124,58,237,.3);color:#7c3aed;}
.city-preset.intl:hover{background:#7c3aed;color:#fff;border-color:#7c3aed;}
/* SEARCH INPUT */
.search-input{padding:7px 12px 7px 36px;border:1.5px solid #EEEEEE;border-radius:10px;font-size:13px;width:200px;position:relative;}
.search-wrap{position:relative;}
.search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#8A8A9A;font-size:12px;}
</style>

@if(session('success'))
<div class="alert-success"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="alert-error"><i class="fas fa-exclamation-circle me-2"></i>{{ $errors->first() }}</div>
@endif

{{-- HERO --}}
<div class="tkt-hero">
    <div style="position:relative;z-index:1">
        <h2 style="font-size:22px;font-weight:800;margin:0 0 4px"><i class="fas fa-plane me-2"></i>eTicket — Flight Management</h2>
        <p style="margin:0;opacity:.8;font-size:13px">Manage airlines, routes and flight schedules</p>
        <div style="margin-top:8px">
            <span class="tkt-badge"><i class="fas fa-plane me-1"></i>Economy</span>
            <span class="tkt-badge"><i class="fas fa-briefcase me-1"></i>Business</span>
            <span class="tkt-badge"><i class="fas fa-baby me-1"></i>Child / Infant</span>
        </div>
    </div>
    <div style="font-size:56px;opacity:.18;position:relative;z-index:1"><i class="fas fa-plane-departure"></i></div>
</div>

{{-- STATS --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-building"></i></div>
        <div><div class="stat-num">{{ $airlines->count() }}</div><div class="stat-lbl">Airlines</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-route"></i></div>
        <div><div class="stat-num">{{ $routes->count() }}</div><div class="stat-lbl">Routes</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-calendar-alt"></i></div>
        <div><div class="stat-num">{{ $flights->total() }}</div><div class="stat-lbl">Schedules</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-ticket-alt"></i></div>
        <div><div class="stat-num">{{ $bookings->count() }}</div><div class="stat-lbl">Bookings</div></div>
    </div>
</div>

{{-- TABS --}}
<div class="module-tabs">
    <button class="module-tab active" onclick="switchTab('airlines',this)"><i class="fas fa-building"></i> Airlines</button>
    <button class="module-tab" onclick="switchTab('routes',this)"><i class="fas fa-route"></i> Routes</button>
    <button class="module-tab" onclick="switchTab('schedules',this)"><i class="fas fa-calendar-alt"></i> Schedules</button>
    <button class="module-tab" onclick="switchTab('bookings',this)"><i class="fas fa-ticket-alt"></i> Bookings</button>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TAB 1: AIRLINES --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="tab-airlines" class="tab-pane active">
    <div class="sc">
        <div class="sc-head">
            <div class="sc-title"><i class="fas fa-building" style="color:#1565C0"></i> Airlines ({{ $airlines->count() }})</div>
            <button class="btn-add" onclick="document.getElementById('addAirlineModal').classList.add('open')">
                <i class="fas fa-plus"></i> Add Airline
            </button>
        </div>
        <table class="dtbl">
            <thead><tr><th>Logo</th><th>Name</th><th>Code</th><th>Brand Colour</th><th>Sort</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($airlines as $a)
            <tr>
                <td>
                    @if($a->logo)
                        <img src="{{ $a->logo }}" style="width:52px;height:38px;border-radius:8px;object-fit:contain;background:#f4f5fa;padding:4px;border:1px solid #f0f1f5">
                    @else
                        <div class="airline-avatar" style="background:{{ $a->color ?? '#1565C0' }}">{{ strtoupper(substr($a->name,0,2)) }}</div>
                    @endif
                </td>
                <td><strong>{{ $a->name }}</strong></td>
                <td><span class="badge-xs badge-scheduled" style="letter-spacing:1px">{{ $a->code ?? '—' }}</span></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px">
                        <div style="width:26px;height:26px;border-radius:6px;background:{{ $a->color ?? '#1565C0' }};border:1px solid rgba(0,0,0,.1)"></div>
                        <code style="font-size:11px;color:#8A8A9A">{{ $a->color ?? '#1565C0' }}</code>
                    </div>
                </td>
                <td style="color:#8A8A9A">{{ $a->sort_order ?? 0 }}</td>
                <td><span class="badge-xs {{ ($a->is_active ?? true) ? 'badge-active' : 'badge-inactive' }}">{{ ($a->is_active ?? true) ? 'Active' : 'Inactive' }}</span></td>
                <td>
                    <div style="display:flex;gap:6px">
                        <button class="btn-icon edit" onclick="editAirline({{ json_encode($a) }})"><i class="fas fa-pen"></i></button>
                        <form action="{{ route('admin.module-data.ticket.airline.destroy',$a->id) }}" method="POST" onsubmit="return confirm('Delete airline?')" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-icon del"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr class="empty-row"><td colspan="7"><i class="fas fa-building" style="font-size:36px;color:#EEEEEE;display:block;margin-bottom:10px"></i>No airlines yet — add your first airline</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TAB 2: ROUTES --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="tab-routes" class="tab-pane">
    <div class="sc">
        <div class="sc-head">
            <div class="sc-title"><i class="fas fa-route" style="color:#1565C0"></i> Routes ({{ $routes->count() }})</div>
            <div style="display:flex;gap:10px;align-items:center">
                <select id="routeTypeFilter" onchange="filterRoutes()" style="padding:7px 12px;border:1.5px solid #EEEEEE;border-radius:10px;font-size:13px">
                    <option value="">All Routes</option>
                    <option value="domestic">Domestic</option>
                    <option value="international">International</option>
                </select>
                <button class="btn-add" onclick="document.getElementById('addRouteModal').classList.add('open')">
                    <i class="fas fa-plus"></i> Add Route
                </button>
            </div>
        </div>
        <table class="dtbl" id="routeTable">
            <thead><tr><th>From City</th><th>IATA</th><th>To City</th><th>IATA</th><th>Type</th><th>Distance</th><th>Duration</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($routes as $r)
            <tr data-type="{{ $r->route_type ?? 'domestic' }}">
                <td>
                    <div><strong>{{ $r->from_city }}</strong></div>
                    <div style="font-size:11px;color:#8A8A9A">{{ $r->from_country ?? '' }}</div>
                </td>
                <td><span class="badge-xs badge-scheduled" style="letter-spacing:2px;font-size:12px">{{ $r->from_code }}</span></td>
                <td>
                    <div><strong>{{ $r->to_city }}</strong></div>
                    <div style="font-size:11px;color:#8A8A9A">{{ $r->to_country ?? '' }}</div>
                </td>
                <td><span class="badge-xs badge-scheduled" style="letter-spacing:2px;font-size:12px">{{ $r->to_code }}</span></td>
                <td><span class="badge-xs {{ ($r->route_type ?? 'domestic') === 'international' ? 'badge-international' : 'badge-domestic' }}">{{ ucfirst($r->route_type ?? 'domestic') }}</span></td>
                <td style="color:#8A8A9A;font-size:12px">{{ ($r->distance_km ?? null) ? number_format($r->distance_km).' km' : '—' }}</td>
                <td style="color:#8A8A9A;font-size:12px">{{ ($r->default_duration ?? null) ? floor($r->default_duration/60).'h '.($r->default_duration%60).'m' : '—' }}</td>
                <td><span class="badge-xs {{ ($r->is_active ?? true) ? 'badge-active' : 'badge-inactive' }}">{{ ($r->is_active ?? true) ? 'Active' : 'Inactive' }}</span></td>
                <td>
                    <div style="display:flex;gap:6px">
                        <button class="btn-icon edit" onclick="editRoute({{ json_encode($r) }})"><i class="fas fa-pen"></i></button>
                        <form action="{{ route('admin.module-data.ticket.route.destroy',$r->id) }}" method="POST" onsubmit="return confirm('Delete route?')" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-icon del"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr class="empty-row"><td colspan="9"><i class="fas fa-route" style="font-size:36px;color:#EEEEEE;display:block;margin-bottom:10px"></i>No routes yet</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TAB 3: SCHEDULES --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="tab-schedules" class="tab-pane">
    <div class="sc">
        <div class="sc-head">
            <div class="sc-title"><i class="fas fa-calendar-alt" style="color:#1565C0"></i> Flight Schedules ({{ $flights->total() }})</div>
            <div style="display:flex;gap:10px;align-items:center">
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input class="search-input" type="text" placeholder="Search schedules…" oninput="filterTable('scheduleTable',this.value)">
                </div>
                <div style="display:flex;gap:8px;">
                    <button class="btn-outline" onclick="document.getElementById('bulkScheduleModal').classList.add('open')">
                        <i class="fas fa-layer-group"></i> Bulk Add
                    </button>
                    <button class="btn-add" onclick="document.getElementById('addScheduleModal').classList.add('open')">
                        <i class="fas fa-plus"></i> Add Schedule
                    </button>
                </div>
            </div>
        </div>
        <table class="dtbl" id="scheduleTable">
            <thead>
                <tr><th>Airline</th><th>Route</th><th>Flight #</th><th>Departure</th><th>Arrival</th><th>Duration</th><th>Seats</th><th>Prices</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            @forelse($flights as $f)
            @php
                $classes = is_string($f->seat_classes) ? (json_decode($f->seat_classes, true) ?? []) : ($f->seat_classes ?? []);
                $dep = \Carbon\Carbon::parse($f->departure_at);
                $arr = \Carbon\Carbon::parse($f->arrival_at);
                $dur = ($f->duration_minutes ?? null) ? floor($f->duration_minutes/60).'h '.($f->duration_minutes%60).'m' : '—';
                $stMap = ['scheduled'=>'badge-scheduled','boarding'=>'badge-boarding','departed'=>'badge-departed','landed'=>'badge-landed','cancelled'=>'badge-cancelled'];
            @endphp
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="airline-avatar" style="background:{{ $f->airline_color ?? '#1565C0' }}">
                            @if(!empty($f->airline_logo_img))
                                <img src="{{ $f->airline_logo_img }}" style="width:100%;height:100%;object-fit:contain">
                            @else
                                {{ strtoupper(substr($f->airline_name ?? $f->airline ?? 'A',0,2)) }}
                            @endif
                        </div>
                        <div style="font-weight:700;font-size:13px">{{ $f->airline_name ?? $f->airline }}</div>
                    </div>
                </td>
                <td>
                    <div class="route-pair">
                        <div style="text-align:center">
                            <div class="iata-code">{{ $f->from_code ?? strtoupper(substr($f->from_city??'',0,3)) }}</div>
                            <div class="city-name">{{ $f->from_city }}</div>
                        </div>
                        <i class="fas fa-long-arrow-alt-right" style="color:#8A8A9A"></i>
                        <div style="text-align:center">
                            <div class="iata-code">{{ $f->to_code ?? strtoupper(substr($f->to_city??'',0,3)) }}</div>
                            <div class="city-name">{{ $f->to_city }}</div>
                        </div>
                    </div>
                </td>
                <td><span class="badge-xs badge-scheduled" style="letter-spacing:.5px">{{ $f->flight_number }}</span></td>
                <td>
                    <div style="font-weight:700">{{ $dep->format('H:i') }}</div>
                    <div style="font-size:11px;color:#8A8A9A">{{ $dep->format('d M Y') }}</div>
                </td>
                <td>
                    <div style="font-weight:700">{{ $arr->format('H:i') }}</div>
                    <div style="font-size:11px;color:#8A8A9A">{{ $arr->format('d M Y') }}</div>
                </td>
                <td><span style="font-size:12px;color:#8A8A9A"><i class="fas fa-clock me-1"></i>{{ $dur }}</span></td>
                <td>
                    <span class="badge-xs {{ ($f->available_seats ?? 0) > 0 ? 'badge-active' : 'badge-inactive' }}">
                        {{ $f->available_seats ?? 0 }}/{{ $f->total_seats ?? '?' }}
                    </span>
                </td>
                <td>
                    <div style="display:flex;flex-direction:column;gap:3px">
                        @if(!empty($classes['economy']))<span class="price-pill price-eco">ECO ${{ number_format($classes['economy'],0) }}</span>@endif
                        @if(!empty($classes['business']))<span class="price-pill price-biz">BIZ ${{ number_format($classes['business'],0) }}</span>@endif
                        @if(!empty($classes['child']))<span class="price-pill price-chd">CHD ${{ number_format($classes['child'],0) }}</span>@endif
                        @if(!empty($classes['infant']))<span class="price-pill price-inf">INF ${{ number_format($classes['infant'],0) }}</span>@endif
                    </div>
                </td>
                <td><span class="badge-xs {{ $stMap[$f->status ?? 'scheduled'] ?? 'badge-scheduled' }}">{{ ucfirst($f->status ?? 'scheduled') }}</span></td>
                <td>
                    <div style="display:flex;gap:6px">
                        <button class="btn-icon edit" onclick="editSchedule({{ json_encode($f) }})"><i class="fas fa-pen"></i></button>
                        <form action="{{ route('admin.module-data.ticket.flight.destroy',$f->id) }}" method="POST" onsubmit="return confirm('Delete this schedule?')" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-icon del"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr class="empty-row"><td colspan="10"><i class="fas fa-calendar-alt" style="font-size:36px;color:#EEEEEE;display:block;margin-bottom:10px"></i>No schedules yet — add your first flight</td></tr>
            @endforelse
            </tbody>
        </table>
        @if($flights->hasPages())
        <div style="padding:14px 18px;border-top:1px solid #f0f1f5;display:flex;justify-content:flex-end">{{ $flights->links('pagination::simple-bootstrap-5') }}</div>
        @endif
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TAB 4: BOOKINGS --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="tab-bookings" class="tab-pane">
    <div class="sc">
        <div class="sc-head">
            <div class="sc-title"><i class="fas fa-ticket-alt" style="color:#1565C0"></i> Bookings ({{ $bookings->count() }})</div>
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input class="search-input" type="text" placeholder="Search bookings…" oninput="filterTable('bookingTable',this.value)">
            </div>
        </div>
        <table class="dtbl" id="bookingTable">
            <thead><tr><th>Order #</th><th>Passenger</th><th>Flight</th><th>Route</th><th>Class</th><th>Date</th><th>Pax</th><th>Amount</th><th>Payment</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($bookings as $bk)
            @php $stMap2=['pending'=>'badge-boarding','confirmed'=>'badge-active','cancelled'=>'badge-cancelled','boarded'=>'badge-landed']; @endphp
            <tr>
                <td><strong>#{{ $bk->order_number }}</strong></td>
                <td>
                    <div style="font-weight:600">{{ $bk->passenger_name ?? $bk->user_name }}</div>
                    @if(!empty($bk->user_phone))<div style="font-size:11px;color:#8A8A9A">{{ $bk->user_phone }}</div>@endif
                </td>
                <td><span class="badge-xs badge-scheduled">{{ $bk->flight_number }}</span></td>
                <td style="font-size:12px;font-weight:700">
                    {{ $bk->from_city ?? '—' }}<i class="fas fa-arrow-right" style="color:#8A8A9A;margin:0 4px;font-size:9px"></i>{{ $bk->to_city ?? '—' }}
                </td>
                <td>
                    @php $cls = $bk->seat_class ?? 'economy'; @endphp
                    <span class="price-pill {{ $cls==='economy'?'price-eco':($cls==='business'?'price-biz':'price-chd') }}">{{ ucfirst($cls) }}</span>
                </td>
                <td style="font-size:12px;color:#8A8A9A">{{ $bk->departure_at ? \Carbon\Carbon::parse($bk->departure_at)->format('d M Y') : '—' }}</td>
                <td style="font-weight:700">{{ $bk->total_passengers ?? 1 }}</td>
                <td><strong>${{ number_format($bk->total_amount ?? 0) }}</strong></td>
                <td>
                    @php $bkpm = $bk->payment_method ?? 'cod'; @endphp
                    <span style="font-size:11px;font-weight:700;color:{{ $bkpm==='mobile_pay'?'#3949AB':($bkpm==='wallet'?'#2E7D32':'#555') }};display:block;">{{ ucwords(str_replace('_',' ',$bkpm)) }}</span>
                    <span class="badge-xs {{ ($bk->payment_status ?? 'pending')==='paid' ? 'badge-active' : 'badge-boarding' }}">{{ ucfirst($bk->payment_status ?? 'pending') }}</span>
                    @if($bkpm==='mobile_pay')<a href="{{ route('admin.orders.show', $bk->id) }}" style="font-size:10px;color:#3949AB;display:block;margin-top:2px;"><i class="fas fa-image"></i> Proof</a>@endif
                </td>
                <td><span class="badge-xs {{ $stMap2[$bk->order_status ?? 'confirmed'] ?? 'badge-scheduled' }}">{{ ucfirst($bk->order_status ?? 'confirmed') }}</span></td>
            </tr>
            @empty
            <tr class="empty-row"><td colspan="10"><i class="fas fa-ticket-alt" style="font-size:36px;color:#EEEEEE;display:block;margin-bottom:10px"></i>No bookings yet</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ══════════════════════════ MODALS ══════════════════════════ --}}

{{-- ADD AIRLINE --}}
<div class="modal-overlay" id="addAirlineModal">
    <div class="modal-box sm">
        <div class="modal-head">
            <h3><i class="fas fa-building me-2" style="color:#1565C0"></i>Add Airline</h3>
            <button class="modal-close" onclick="closeModal('addAirlineModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('admin.module-data.ticket.airline.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Airline Name *</label><input type="text" name="name" required placeholder="e.g. Daallo Airlines"></div>
                    <div class="fgroup"><label>IATA Code</label><input type="text" name="code" maxlength="3" placeholder="e.g. D3" style="text-transform:uppercase"></div>
                </div>
                <div class="fgroup" style="margin-bottom:14px"><label>Logo URL</label><input type="url" name="logo" placeholder="https://…/logo.png"><small style="color:#8A8A9A;font-size:11px">Paste direct image URL (PNG/SVG recommended)</small></div>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Brand Colour</label><input type="color" name="color" value="#1565C0" style="height:42px;padding:4px"></div>
                    <div class="fgroup"><label>Sort Order</label><input type="number" name="sort_order" value="0" min="0"></div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="button" class="btn-outline" onclick="closeModal('addAirlineModal')">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Add Airline</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- EDIT AIRLINE --}}
<div class="modal-overlay" id="editAirlineModal">
    <div class="modal-box sm">
        <div class="modal-head">
            <h3><i class="fas fa-pen me-2" style="color:#1565C0"></i>Edit Airline</h3>
            <button class="modal-close" onclick="closeModal('editAirlineModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="editAirlineForm" method="POST" enctype="multipart/form-data">
                @csrf @method('PATCH')
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Name</label><input type="text" name="name" id="ea_name" required></div>
                    <div class="fgroup"><label>Code</label><input type="text" name="code" id="ea_code" maxlength="3"></div>
                </div>
                <div class="fgroup" style="margin-bottom:14px"><label>Logo URL</label><input type="url" name="logo" id="ea_logo"></div>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Brand Colour</label><input type="color" name="color" id="ea_color" style="height:42px;padding:4px"></div>
                    <div class="fgroup"><label>Sort Order</label><input type="number" name="sort_order" id="ea_sort" min="0"></div>
                </div>
                <div class="fgroup" style="margin-bottom:14px">
                    <label>Status</label>
                    <select name="is_active" id="ea_active"><option value="1">Active</option><option value="0">Inactive</option></select>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="button" class="btn-outline" onclick="closeModal('editAirlineModal')">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ADD ROUTE --}}
<div class="modal-overlay" id="addRouteModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3><i class="fas fa-route me-2" style="color:#1565C0"></i>Add Route</h3>
            <button class="modal-close" onclick="closeModal('addRouteModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p class="sec-title" style="margin-bottom:10px">Quick Add — Somali Domestic Cities</p>
            <div class="city-presets" id="domesticPresets">
                <span class="city-preset" onclick="setCity('from','Mogadishu','MGQ','Somalia')">🇸🇴 Mogadishu (MGQ)</span>
                <span class="city-preset" onclick="setCity('from','Hargeysa','HGA','Somalia')">🇸🇴 Hargeysa (HGA)</span>
                <span class="city-preset" onclick="setCity('from','Kismaayo','KMU','Somalia')">🇸🇴 Kismaayo (KMU)</span>
                <span class="city-preset" onclick="setCity('from','Baydhabo','BYD','Somalia')">🇸🇴 Baydhabo (BYD)</span>
                <span class="city-preset" onclick="setCity('from','Cadaado','CXN','Somalia')">🇸🇴 Cadaado (CXN)</span>
                <span class="city-preset" onclick="setCity('from','Kaalgacyo','GLK','Somalia')">🇸🇴 Kaalgacyo (GLK)</span>
                <span class="city-preset" onclick="setCity('from','Garowe','GGR','Somalia')">🇸🇴 Garowe (GGR)</span>
                <span class="city-preset" onclick="setCity('from','Guriceel','GUR','Somalia')">🇸🇴 Guriceel (GUR)</span>
                <span class="city-preset" onclick="setCity('from','Doolow','DOG','Somalia')">🇸🇴 Doolow (DOG)</span>
                <span class="city-preset" onclick="setCity('from','Dhuusamareeb','DMO','Somalia')">🇸🇴 Dhuusamareeb (DMO)</span>
                <span class="city-preset" onclick="setCity('from','Laascaanood','LAS','Somalia')">🇸🇴 Laascaanood (LAS)</span>
            </div>
            <p class="sec-title" style="margin:14px 0 10px">International Cities</p>
            <div class="city-presets">
                <span class="city-preset intl" onclick="setCity('from','Nairobi','NBO','Kenya')">🇰🇪 Nairobi (NBO)</span>
                <span class="city-preset intl" onclick="setCity('from','Kampala','EBB','Uganda')">🇺🇬 Kampala (EBB)</span>
                <span class="city-preset intl" onclick="setCity('from','Juba','JUB','South Sudan')">🇸🇸 Juba (JUB)</span>
                <span class="city-preset intl" onclick="setCity('from','Istanbul','IST','Turkey')">🇹🇷 Istanbul (IST)</span>
                <span class="city-preset intl" onclick="setCity('from','Doha','DOH','Qatar')">🇶🇦 Doha (DOH)</span>
                <span class="city-preset intl" onclick="setCity('from','Dubai','DXB','UAE')">🇦🇪 Dubai (DXB)</span>
                <span class="city-preset intl" onclick="setCity('from','Addis Ababa','ADD','Ethiopia')">🇪🇹 Addis Ababa (ADD)</span>
                <span class="city-preset intl" onclick="setCity('from','Djibouti','JIB','Djibouti')">🇩🇯 Djibouti (JIB)</span>
                <span class="city-preset intl" onclick="setCity('from','Riyadh','RUH','Saudi Arabia')">🇸🇦 Riyadh (RUH)</span>
            </div>
            <p style="font-size:12px;color:#8A8A9A;margin:4px 0 14px">💡 Tip: Click a city above to set it as the <strong>From</strong> city, then type or click again for <strong>To</strong></p>
            <form method="POST" action="{{ route('admin.module-data.ticket.route.store') }}">
                @csrf
                <div style="display:grid;grid-template-columns:1fr auto 1fr;align-items:end;gap:12px;margin-bottom:14px">
                    <div>
                        <p class="sec-title">From</p>
                        <div class="fgroup" style="margin-bottom:10px"><label>City Name *</label><input type="text" name="from_city" id="add_from_city" required placeholder="e.g. Mogadishu"></div>
                        <div class="form-grid-2">
                            <div class="fgroup"><label>IATA Code *</label><input type="text" name="from_code" id="add_from_code" maxlength="5" required placeholder="MGQ" style="text-transform:uppercase"></div>
                            <div class="fgroup"><label>Country</label><input type="text" name="from_country" id="add_from_country" placeholder="Somalia"></div>
                        </div>
                    </div>
                    <div style="text-align:center;padding-bottom:12px;font-size:24px;color:#8A8A9A">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div>
                        <p class="sec-title">To</p>
                        <div class="fgroup" style="margin-bottom:10px"><label>City Name *</label><input type="text" name="to_city" id="add_to_city" required placeholder="e.g. Nairobi"></div>
                        <div class="form-grid-2">
                            <div class="fgroup"><label>IATA Code *</label><input type="text" name="to_code" id="add_to_code" maxlength="5" required placeholder="NBO" style="text-transform:uppercase"></div>
                            <div class="fgroup"><label>Country</label><input type="text" name="to_country" id="add_to_country" placeholder="Kenya"></div>
                        </div>
                    </div>
                </div>
                <div class="form-grid-3" style="margin-bottom:14px">
                    <div class="fgroup">
                        <label>Route Type</label>
                        <select name="route_type" id="add_route_type">
                            <option value="domestic">Domestic</option>
                            <option value="international">International</option>
                        </select>
                    </div>
                    <div class="fgroup"><label>Distance (km)</label><input type="number" name="distance_km" min="0" placeholder="e.g. 450"></div>
                    <div class="fgroup"><label>Est. Duration (min)</label><input type="number" name="default_duration" min="0" placeholder="e.g. 70"></div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="button" class="btn-outline" onclick="closeModal('addRouteModal')">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Add Route</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- EDIT ROUTE --}}
<div class="modal-overlay" id="editRouteModal">
    <div class="modal-box sm">
        <div class="modal-head">
            <h3><i class="fas fa-pen me-2" style="color:#1565C0"></i>Edit Route</h3>
            <button class="modal-close" onclick="closeModal('editRouteModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="editRouteForm" method="POST">
                @csrf @method('PATCH')
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>From City</label><input type="text" name="from_city" id="er_fcity" required></div>
                    <div class="fgroup"><label>From Code</label><input type="text" name="from_code" id="er_fcode" maxlength="5"></div>
                </div>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>To City</label><input type="text" name="to_city" id="er_tcity" required></div>
                    <div class="fgroup"><label>To Code</label><input type="text" name="to_code" id="er_tcode" maxlength="5"></div>
                </div>
                <div class="form-grid-3" style="margin-bottom:14px">
                    <div class="fgroup"><label>Type</label><select name="route_type" id="er_type"><option value="domestic">Domestic</option><option value="international">International</option></select></div>
                    <div class="fgroup"><label>Distance (km)</label><input type="number" name="distance_km" id="er_dist" min="0"></div>
                    <div class="fgroup"><label>Duration (min)</label><input type="number" name="default_duration" id="er_dur" min="0"></div>
                </div>
                <div class="fgroup" style="margin-bottom:14px"><label>Status</label><select name="is_active" id="er_active"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="button" class="btn-outline" onclick="closeModal('editRouteModal')">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ADD SCHEDULE --}}
<div class="modal-overlay" id="addScheduleModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3><i class="fas fa-calendar-plus me-2" style="color:#1565C0"></i>Add Flight Schedule</h3>
            <button class="modal-close" onclick="closeModal('addScheduleModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('admin.module-data.ticket.flight.store') }}">
                @csrf
                {{-- Flight Info --}}
                <p class="sec-title">Flight Info</p>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup">
                        <label>Airline *</label>
                        <select name="airline_id" id="as_airline_id" required onchange="autoFlightNumber(this,'as_flight_number')">
                            <option value="">Select airline</option>
                            @foreach($airlines as $a)
                            <option value="{{ $a->id }}" data-code="{{ strtoupper(substr($a->code ?? $a->name, 0, 2)) }}">{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fgroup">
                        <label>Flight Number <span style="font-size:10px;color:#8A8A9A;font-weight:400">(auto-generated)</span></label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <input type="text" name="flight_number" id="as_flight_number" required placeholder="Select airline first" style="text-transform:uppercase;flex:1">
                            <button type="button" onclick="autoFlightNumber(document.getElementById('as_airline_id'),'as_flight_number')" style="padding:8px 12px;background:#f4f5fa;border:1px solid #e8e9f0;border-radius:8px;cursor:pointer;font-size:12px;color:#07003B;white-space:nowrap"><i class="fas fa-sync-alt"></i></button>
                        </div>
                    </div>
                </div>
                {{-- Route --}}
                <p class="sec-title">Route</p>
                <div class="fgroup" style="margin-bottom:14px">
                    <label>Select Route (auto-fills cities) *</label>
                    <select id="as_route_select" onchange="fillRouteFields(this,'as')">
                        <option value="">Select route or fill manually below</option>
                        @foreach($routes as $r)
                        <option value="{{ $r->id }}"
                            data-fc="{{ $r->from_city }}" data-fcode="{{ $r->from_code }}"
                            data-tc="{{ $r->to_city }}"  data-tcode="{{ $r->to_code }}">
                            {{ $r->from_city }} ({{ $r->from_code }}) → {{ $r->to_city }} ({{ $r->to_code }})
                        </option>
                        @endforeach
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:12px;margin-bottom:14px;align-items:start">
                    <div>
                        <div class="form-grid-2">
                            <div class="fgroup"><label>From City *</label><input type="text" name="from_city" id="as_from_city" required></div>
                            <div class="fgroup"><label>From Code</label><input type="text" name="from_code" id="as_from_code" maxlength="5" style="text-transform:uppercase"></div>
                        </div>
                    </div>
                    <div style="text-align:center;padding-top:28px;color:#8A8A9A"><i class="fas fa-long-arrow-alt-right"></i></div>
                    <div>
                        <div class="form-grid-2">
                            <div class="fgroup"><label>To City *</label><input type="text" name="to_city" id="as_to_city" required></div>
                            <div class="fgroup"><label>To Code</label><input type="text" name="to_code" id="as_to_code" maxlength="5" style="text-transform:uppercase"></div>
                        </div>
                    </div>
                </div>
                {{-- Date & Time --}}
                <p class="sec-title">Date & Time</p>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Departure Date & Time *</label><input type="datetime-local" name="departure_at" required></div>
                    <div class="fgroup"><label>Arrival Date & Time *</label><input type="datetime-local" name="arrival_at" required></div>
                </div>
                {{-- Seats --}}
                <p class="sec-title">Seats</p>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Total Seats *</label><input type="number" name="total_seats" required min="1" placeholder="e.g. 150"></div>
                    <div class="fgroup"><label>Available Seats *</label><input type="number" name="available_seats" required min="0" placeholder="e.g. 150"></div>
                </div>
                {{-- Prices --}}
                <p class="sec-title">Ticket Prices (USD)</p>
                <div class="prices-grid" style="margin-bottom:20px">
                    <div class="price-input-wrap" style="border-color:rgba(16,185,129,.3)">
                        <label style="color:#10b981">Economy *</label>
                        <input type="number" name="economy_price" required min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="price-input-wrap" style="border-color:rgba(21,101,192,.3)">
                        <label style="color:#1565C0">Business</label>
                        <input type="number" name="business_price" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="price-input-wrap" style="border-color:rgba(245,158,11,.3)">
                        <label style="color:#f59e0b">Child (2–11 yrs)</label>
                        <input type="number" name="child_price" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="price-input-wrap" style="border-color:rgba(239,68,68,.3)">
                        <label style="color:#ef4444">Infant (&lt;2 yrs)</label>
                        <input type="number" name="infant_price" min="0" step="0.01" placeholder="0.00">
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="reset" class="btn-outline">Reset</button>
                    <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Add Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- EDIT SCHEDULE --}}
<div class="modal-overlay" id="editScheduleModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3><i class="fas fa-pen me-2" style="color:#1565C0"></i>Edit Flight Schedule</h3>
            <button class="modal-close" onclick="closeModal('editScheduleModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="editScheduleForm" method="POST">
                @csrf @method('PATCH')
                <p class="sec-title">Flight Info</p>
                <div class="form-grid-3" style="margin-bottom:14px">
                    <div class="fgroup">
                        <label>Airline</label>
                        <select name="airline_id" id="es_airline">
                            @foreach($airlines as $a)<option value="{{ $a->id }}" data-code="{{ strtoupper(substr($a->code ?? $a->name, 0, 2)) }}">{{ $a->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="fgroup"><label>Flight Number</label><input type="text" name="flight_number" id="es_num" required style="text-transform:uppercase"></div>
                    <div class="fgroup"><label>Status</label>
                        <select name="status" id="es_status">
                            <option value="scheduled">Scheduled</option>
                            <option value="boarding">Boarding</option>
                            <option value="departed">Departed</option>
                            <option value="landed">Landed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <p class="sec-title">Route</p>
                <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:12px;margin-bottom:14px;align-items:start">
                    <div class="form-grid-2">
                        <div class="fgroup"><label>From City</label><input type="text" name="from_city" id="es_fc" required></div>
                        <div class="fgroup"><label>From Code</label><input type="text" name="from_code" id="es_fcode" maxlength="5"></div>
                    </div>
                    <div style="text-align:center;padding-top:28px;color:#8A8A9A"><i class="fas fa-long-arrow-alt-right"></i></div>
                    <div class="form-grid-2">
                        <div class="fgroup"><label>To City</label><input type="text" name="to_city" id="es_tc" required></div>
                        <div class="fgroup"><label>To Code</label><input type="text" name="to_code" id="es_tcode" maxlength="5"></div>
                    </div>
                </div>
                <p class="sec-title">Date & Time</p>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Departure</label><input type="datetime-local" name="departure_at" id="es_dep"></div>
                    <div class="fgroup"><label>Arrival</label><input type="datetime-local" name="arrival_at" id="es_arr"></div>
                </div>
                <p class="sec-title">Seats</p>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Total Seats</label><input type="number" name="total_seats" id="es_total" min="1"></div>
                    <div class="fgroup"><label>Available Seats</label><input type="number" name="available_seats" id="es_avail" min="0"></div>
                </div>
                <p class="sec-title">Prices (USD)</p>
                <div class="prices-grid" style="margin-bottom:20px">
                    <div class="price-input-wrap" style="border-color:rgba(16,185,129,.3)">
                        <label style="color:#10b981">Economy *</label>
                        <input type="number" name="economy_price" id="es_eco" min="0" step="0.01">
                    </div>
                    <div class="price-input-wrap" style="border-color:rgba(21,101,192,.3)">
                        <label style="color:#1565C0">Business</label>
                        <input type="number" name="business_price" id="es_biz" min="0" step="0.01">
                    </div>
                    <div class="price-input-wrap" style="border-color:rgba(245,158,11,.3)">
                        <label style="color:#f59e0b">Child</label>
                        <input type="number" name="child_price" id="es_chd" min="0" step="0.01">
                    </div>
                    <div class="price-input-wrap" style="border-color:rgba(239,68,68,.3)">
                        <label style="color:#ef4444">Infant</label>
                        <input type="number" name="infant_price" id="es_inf" min="0" step="0.01">
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="button" class="btn-outline" onclick="closeModal('editScheduleModal')">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- BULK ADD SCHEDULES MODAL --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<style>
.bulk-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;display:none;align-items:flex-start;justify-content:center;padding:16px 0;overflow-y:auto;}
.bulk-modal-overlay.open{display:flex;}
.bulk-modal-box{background:#fff;border-radius:18px;width:min(780px,98vw);box-shadow:0 24px 80px rgba(0,0,0,.18);margin:auto;position:relative;}
.bulk-modal-head{padding:20px 24px 16px;border-bottom:2px solid #f0f1f5;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:2;border-radius:18px 18px 0 0;}
.bulk-modal-body{padding:0 24px 24px;}
/* Template strip */
.tmpl-strip{background:linear-gradient(135deg,#07003B 0%,#0D47A1 100%);border-radius:12px;padding:16px 18px;margin:16px 0;}
.tmpl-strip-title{font-size:11px;font-weight:800;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.8px;margin-bottom:12px;}
.tmpl-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;}
.tmpl-row.prices{grid-template-columns:repeat(4,1fr);}
.tmpl-field label{font-size:11px;font-weight:600;color:rgba(255,255,255,.7);display:block;margin-bottom:4px;}
.tmpl-field select,.tmpl-field input{width:100%;padding:8px 10px;border:1.5px solid rgba(255,255,255,.25);border-radius:8px;font-size:12px;background:rgba(255,255,255,.12);color:#fff;box-sizing:border-box;font-family:inherit;}
.tmpl-field select option{color:#1A1A2E;background:#fff;}
.tmpl-field select::placeholder,.tmpl-field input::placeholder{color:rgba(255,255,255,.45);}
.tmpl-apply-btn{width:100%;padding:10px;background:rgba(255,255,255,.2);border:1.5px solid rgba(255,255,255,.35);border-radius:9px;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s;}
.tmpl-apply-btn:hover{background:rgba(255,255,255,.3);}
/* Schedule cards */
.bulk-cards{display:flex;flex-direction:column;gap:12px;max-height:52vh;overflow-y:auto;padding:2px 2px 4px;}
.bulk-cards::-webkit-scrollbar{width:5px;}
.bulk-cards::-webkit-scrollbar-track{background:#f4f5fa;border-radius:4px;}
.bulk-cards::-webkit-scrollbar-thumb{background:#ccd;border-radius:4px;}
.sched-card{border:1.5px solid #e8e9f0;border-radius:12px;overflow:hidden;transition:border-color .15s;}
.sched-card:hover{border-color:#c5cae9;}
.sched-card-head{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#fafbff;border-bottom:1px solid #f0f1f5;}
.sched-card-num{width:26px;height:26px;border-radius:7px;background:#1565C0;color:#fff;font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;}
.sched-card-body{padding:14px;}
.sched-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;}
.sched-grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;}
.sf label{font-size:11px;font-weight:700;color:#8A8A9A;display:block;margin-bottom:4px;}
.sf input,.sf select{width:100%;padding:8px 10px;border:1.5px solid #c8cce8;border-radius:8px;font-size:13px;color:#1A1A2E;background:#fff;box-sizing:border-box;font-family:inherit;}
.sf input:focus,.sf select:focus{outline:none;border-color:#1565C0;background:#f5f8ff;}
.price-sf label{font-size:11px;font-weight:800;display:block;margin-bottom:4px;}
.price-sf input{width:100%;border:2px solid;border-radius:8px;padding:8px 10px;font-size:13px;font-weight:700;box-sizing:border-box;font-family:inherit;background:#fafeff;}
.eco-sf label{color:#059669;} .eco-sf input{border-color:#10b981;color:#065f46;background:#f0fdf4;}
.biz-sf label{color:#1565C0;} .biz-sf input{border-color:#1565C0;color:#1565C0;background:#eff4ff;}
.chd-sf label{color:#d97706;} .chd-sf input{border-color:#f59e0b;color:#92400e;background:#fffbeb;}
.inf-sf label{color:#dc2626;} .inf-sf input{border-color:#ef4444;color:#991b1b;background:#fff5f5;}
.del-card-btn{width:30px;height:30px;border-radius:8px;border:none;background:rgba(239,68,68,.1);color:#ef4444;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;}
.del-card-btn:hover{background:rgba(239,68,68,.2);}
/* Bulk footer */
.bulk-footer{display:flex;align-items:center;justify-content:space-between;padding:16px 24px;border-top:1px solid #f0f1f5;gap:10px;}
.bulk-add-btns{display:flex;gap:8px;}
.bulk-add-btn{padding:9px 16px;background:#f4f5fa;border:1.5px solid #e0e2f0;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;color:#1A1A2E;font-family:inherit;display:flex;align-items:center;gap:6px;}
.bulk-add-btn:hover{background:#e8eaf6;border-color:#c5cae9;}
.bulk-clone-btn{color:#1565C0;}
.bulk-count{font-size:12px;color:#8A8A9A;font-weight:600;}
</style>

<div class="bulk-modal-overlay" id="bulkScheduleModal">
    <div class="bulk-modal-box">
        {{-- Header --}}
        <div class="bulk-modal-head">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(21,101,192,.1);display:flex;align-items:center;justify-content:center;color:#1565C0;font-size:16px;">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div>
                    <h3 style="margin:0;font-size:17px;font-weight:800;color:#1A1A2E;">Bulk Add Flight Schedules</h3>
                    <p style="margin:0;font-size:12px;color:#8A8A9A;">Create multiple flights at once — set template then adjust dates per row</p>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('bulkScheduleModal')" style="width:34px;height:34px;border-radius:9px;border:none;background:#f4f5fa;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-times"></i></button>
        </div>

        <div class="bulk-modal-body">
            {{-- Template Strip --}}
            <div class="tmpl-strip">
                <div class="tmpl-strip-title"><i class="fas fa-magic" style="margin-right:6px;"></i>Quick Template — fill once, apply to all rows</div>
                <div class="tmpl-row">
                    <div class="tmpl-field">
                        <label>Airline</label>
                        <select id="bulk_tmpl_airline">
                            <option value="">Select airline…</option>
                            @foreach($airlines as $a)
                            <option value="{{ $a->id }}" data-code="{{ strtoupper(substr($a->code ?? $a->name,0,2)) }}" data-name="{{ $a->name }}">{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="tmpl-field">
                        <label>Route</label>
                        <select id="bulk_tmpl_route">
                            <option value="">Select route…</option>
                            @foreach($routes as $r)
                            <option value="{{ $r->id }}" data-fc="{{ $r->from_city }}" data-fcode="{{ $r->from_code }}" data-tc="{{ $r->to_city }}" data-tcode="{{ $r->to_code }}">
                                {{ $r->from_city }} ({{ $r->from_code }}) → {{ $r->to_city }} ({{ $r->to_code }})
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="tmpl-row" style="grid-template-columns:1fr repeat(4,1fr);gap:10px;margin-bottom:12px;">
                    <div class="tmpl-field">
                        <label>Total Seats</label>
                        <input id="bulk_tmpl_seats" type="number" placeholder="e.g. 150" min="1">
                    </div>
                    <div class="tmpl-field">
                        <label style="color:rgba(16,185,129,.9);">Economy $</label>
                        <input id="bulk_tmpl_eco" type="number" placeholder="0.00" min="0" step="0.01">
                    </div>
                    <div class="tmpl-field">
                        <label style="color:rgba(100,160,255,.9);">Business $</label>
                        <input id="bulk_tmpl_biz" type="number" placeholder="0.00" min="0" step="0.01">
                    </div>
                    <div class="tmpl-field">
                        <label style="color:rgba(255,200,60,.9);">Child $</label>
                        <input id="bulk_tmpl_chd" type="number" placeholder="0.00" min="0" step="0.01">
                    </div>
                    <div class="tmpl-field">
                        <label style="color:rgba(255,120,120,.9);">Infant $</label>
                        <input id="bulk_tmpl_inf" type="number" placeholder="0.00" min="0" step="0.01">
                    </div>
                </div>
                <button type="button" class="tmpl-apply-btn" onclick="bulkApplyTemplate()">
                    <i class="fas fa-check-double" style="margin-right:6px;"></i> Apply Template to All Rows
                </button>
            </div>

            {{-- Schedule Cards --}}
            <form method="POST" action="{{ route('admin.module-data.ticket.flight.bulk') }}" id="bulkScheduleForm">
                @csrf
                <div class="bulk-cards" id="bulkRows">
                    {{-- cards injected by JS --}}
                </div>
            </form>
        </div>

        {{-- Footer --}}
        <div class="bulk-footer">
            <div class="bulk-add-btns">
                <button type="button" class="bulk-add-btn" onclick="bulkAddRow()">
                    <i class="fas fa-plus"></i> Add Schedule
                </button>
                <button type="button" class="bulk-add-btn bulk-clone-btn" onclick="bulkCloneLastRow()">
                    <i class="fas fa-clone"></i> Clone Last
                </button>
                <span class="bulk-count" id="bulkRowCount"></span>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="button" class="btn-outline" onclick="closeModal('bulkScheduleModal')">Cancel</button>
                <button type="submit" form="bulkScheduleForm" class="btn-add" id="bulkSubmitBtn" style="min-width:160px;">
                    <i class="fas fa-paper-plane me-1"></i> Create Schedules
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// TAB SWITCHING
function switchTab(name,el){
    document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.module-tab').forEach(t=>t.classList.remove('active'));
    document.getElementById('tab-'+name).classList.add('active');
    el.classList.add('active');
}
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m=>{
    m.addEventListener('click',function(e){ if(e.target===this)this.classList.remove('open'); });
});
// TABLE FILTER
function filterTable(id,q){
    q=q.toLowerCase();
    document.querySelectorAll('#'+id+' tbody tr:not(.empty-row)').forEach(r=>{
        r.style.display=r.textContent.toLowerCase().includes(q)?'':'none';
    });
}
// ROUTE TYPE FILTER
function filterRoutes(){
    const v=document.getElementById('routeTypeFilter').value;
    document.querySelectorAll('#routeTable tbody tr:not(.empty-row)').forEach(r=>{
        r.style.display=(!v||r.dataset.type===v)?'':'none';
    });
}
// CITY PRESETS - fills "from" first, then "to"
let cityStep='from';
function setCity(target,city,code,country){
    document.getElementById('add_'+cityStep+'_city').value=city;
    document.getElementById('add_'+cityStep+'_code').value=code;
    document.getElementById('add_'+cityStep+'_country').value=country;
    // auto-detect route type
    const intlCountries=['Kenya','Uganda','South Sudan','Turkey','Qatar','UAE','Ethiopia','Djibouti','Saudi Arabia'];
    const fromCountry=document.getElementById('add_from_country').value;
    const toCountry=document.getElementById('add_to_country').value;
    if(intlCountries.includes(fromCountry)||intlCountries.includes(toCountry)){
        document.getElementById('add_route_type').value='international';
    } else if(fromCountry==='Somalia'&&toCountry==='Somalia'){
        document.getElementById('add_route_type').value='domestic';
    }
    cityStep=cityStep==='from'?'to':'from';
}
// AUTO-GENERATE FLIGHT NUMBER
function autoFlightNumber(sel, targetId){
    const opt = sel.options[sel.selectedIndex];
    if(!opt || !opt.value) return;
    const code = (opt.dataset.code || opt.text.substring(0,2)).toUpperCase().replace(/[^A-Z0-9]/g,'');
    const num  = String(Math.floor(100 + Math.random() * 900));
    document.getElementById(targetId).value = code + num;
}
// FILL ROUTE FIELDS from dropdown
function fillRouteFields(sel,prefix){
    const opt=sel.options[sel.selectedIndex];
    if(!opt||!opt.value)return;
    document.getElementById(prefix+'_from_city').value=opt.dataset.fc||'';
    document.getElementById(prefix+'_from_code').value=opt.dataset.fcode||'';
    document.getElementById(prefix+'_to_city').value=opt.dataset.tc||'';
    document.getElementById(prefix+'_to_code').value=opt.dataset.tcode||'';
}
// EDIT AIRLINE
function editAirline(a){
    document.getElementById('editAirlineForm').action=`/admin/module-data/ticket/airlines/${a.id}`;
    document.getElementById('ea_name').value=a.name||'';
    document.getElementById('ea_code').value=a.code||'';
    document.getElementById('ea_logo').value=a.logo||'';
    document.getElementById('ea_color').value=a.color||'#1565C0';
    document.getElementById('ea_sort').value=a.sort_order||0;
    document.getElementById('ea_active').value=a.is_active?'1':'0';
    document.getElementById('editAirlineModal').classList.add('open');
}
// EDIT ROUTE
function editRoute(r){
    document.getElementById('editRouteForm').action=`/admin/module-data/ticket/routes/${r.id}`;
    document.getElementById('er_fcity').value=r.from_city||'';
    document.getElementById('er_fcode').value=r.from_code||'';
    document.getElementById('er_tcity').value=r.to_city||'';
    document.getElementById('er_tcode').value=r.to_code||'';
    document.getElementById('er_type').value=r.route_type||'domestic';
    document.getElementById('er_dist').value=r.distance_km||'';
    document.getElementById('er_dur').value=r.default_duration||'';
    document.getElementById('er_active').value=(r.is_active??true)?'1':'0';
    document.getElementById('editRouteModal').classList.add('open');
}
// ── BULK SCHEDULE LOGIC ──────────────────────────────────────────────────────

@php
$_bulkAirlines = $airlines->map(function($a){
    return ['id'=>$a->id,'name'=>$a->name,'code'=>strtoupper(substr($a->code??$a->name,0,2))];
})->values();
$_bulkRoutes = $routes->map(function($r){
    return ['id'=>$r->id,'label'=>$r->from_city.' ('.$r->from_code.') → '.$r->to_city.' ('.$r->to_code.')','fc'=>$r->from_city,'fcode'=>$r->from_code,'tc'=>$r->to_city,'tcode'=>$r->to_code];
})->values();
@endphp
const bulkAirlines = {!! json_encode($_bulkAirlines) !!};
const bulkRoutes   = {!! json_encode($_bulkRoutes) !!};

let bulkRowIndex = 0;

var IS = 'width:100%;padding:8px 10px;border:1.5px solid #b0b8d8;border-radius:8px;font-size:13px;color:#1A1A2E;background:#fff;box-sizing:border-box;font-family:inherit;display:block;height:36px;';
var SS = 'width:100%;padding:8px 10px;border:1.5px solid #b0b8d8;border-radius:8px;font-size:13px;color:#1A1A2E;background:#fff;box-sizing:border-box;font-family:inherit;display:block;height:36px;';
var LS = 'font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px;';

function bulkCardHtml(idx){
    const n = `flights[${idx}]`;
    const airlineOpts = bulkAirlines.map(a=>`<option value="${a.id}" data-code="${a.code}">${a.name}</option>`).join('');
    const routeOpts   = bulkRoutes.map(r=>`<option value="${r.id}" data-fc="${r.fc}" data-fcode="${r.fcode}" data-tc="${r.tc}" data-tcode="${r.tcode}">${r.label}</option>`).join('');
    return '<div class="sched-card" id="bulk-row-'+idx+'">'
        +'<div class="sched-card-head">'
            +'<div style="display:flex;align-items:center;gap:10px;">'
                +'<div class="sched-card-num">'+(idx+1)+'</div>'
                +'<span style="font-size:13px;font-weight:700;color:#1A1A2E;">Flight Schedule</span>'
                +'<span id="bulk-fn-label-'+idx+'" style="font-size:12px;color:#8A8A9A;font-family:monospace;"></span>'
            +'</div>'
            +'<button type="button" class="del-card-btn" onclick="bulkRemoveRow('+idx+')" title="Remove"><i class="fas fa-trash-alt"></i></button>'
        +'</div>'
        +'<div style="padding:14px;">'
            +'<input type="hidden" name="'+n+'[flight_number]" id="bulk-fn-'+idx+'">'
            +'<input type="hidden" name="'+n+'[from_city]" id="bulk-fc-'+idx+'">'
            +'<input type="hidden" name="'+n+'[from_code]" id="bulk-fcode-'+idx+'">'
            +'<input type="hidden" name="'+n+'[to_city]" id="bulk-tc-'+idx+'">'
            +'<input type="hidden" name="'+n+'[to_code]" id="bulk-tcode-'+idx+'">'
            +'<input type="hidden" name="'+n+'[available_seats]" id="bulk-avail-'+idx+'">'
            /* Row 1: Airline + Route */
            +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">'
                +'<div><label style="'+LS+'">✈ Airline *</label>'
                    +'<select name="'+n+'[airline_id]" onchange="bulkAutoFlightNum(this,'+idx+')" style="'+SS+'">'
                        +'<option value="">Select airline…</option>'+airlineOpts
                    +'</select></div>'
                +'<div><label style="'+LS+'">🛫 Route *</label>'
                    +'<select onchange="bulkFillRoute(this,'+idx+')" style="'+SS+'">'
                        +'<option value="">Select route…</option>'+routeOpts
                    +'</select></div>'
            +'</div>'
            /* Row 2: Departure + Arrival + Seats */
            +'<div style="display:grid;grid-template-columns:1fr 1fr 120px;gap:10px;margin-bottom:10px;">'
                +'<div><label style="'+LS+'">📅 Departure *</label>'
                    +'<input type="datetime-local" name="'+n+'[departure_at]" id="bulk-dep-'+idx+'" required style="'+IS+'"></div>'
                +'<div><label style="'+LS+'">🛬 Arrival *</label>'
                    +'<input type="datetime-local" name="'+n+'[arrival_at]" id="bulk-arr-'+idx+'" required style="'+IS+'"></div>'
                +'<div><label style="'+LS+'">💺 Seats</label>'
                    +'<input type="number" name="'+n+'[total_seats]" id="bulk-seats-'+idx+'" min="1" placeholder="150" value="150" style="'+IS+'"></div>'
            +'</div>'
            /* Row 3: Prices */
            +'<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;">'
                +'<div><label style="'+LS+'color:#059669;">Economy $</label>'
                    +'<input type="number" name="'+n+'[economy_price]" id="bulk-eco-'+idx+'" min="0" step="0.01" placeholder="0.00" style="'+IS+'border-color:#10b981;background:#f0fdf4;color:#065f46;font-weight:700;"></div>'
                +'<div><label style="'+LS+'color:#1565C0;">Business $</label>'
                    +'<input type="number" name="'+n+'[business_price]" id="bulk-biz-'+idx+'" min="0" step="0.01" placeholder="0.00" style="'+IS+'border-color:#1565C0;background:#eff4ff;color:#1565C0;font-weight:700;"></div>'
                +'<div><label style="'+LS+'color:#d97706;">Child $</label>'
                    +'<input type="number" name="'+n+'[child_price]" id="bulk-chd-'+idx+'" min="0" step="0.01" placeholder="0.00" style="'+IS+'border-color:#f59e0b;background:#fffbeb;color:#92400e;font-weight:700;"></div>'
                +'<div><label style="'+LS+'color:#dc2626;">Infant $</label>'
                    +'<input type="number" name="'+n+'[infant_price]" id="bulk-inf-'+idx+'" min="0" step="0.01" placeholder="0.00" style="'+IS+'border-color:#ef4444;background:#fff5f5;color:#991b1b;font-weight:700;"></div>'
            +'</div>'
        +'</div>'
    +'</div>';
}

function bulkAddRow(cloneFrom){
    const container = document.getElementById('bulkRows');
    const idx = bulkRowIndex++;
    container.insertAdjacentHTML('beforeend', bulkCardHtml(idx));
    if(cloneFrom !== undefined){
        const src = document.getElementById('bulk-row-'+cloneFrom);
        if(src){
            // clone airline select
            const srcAirline = src.querySelector(`select[name*="airline_id"]`);
            const tgtAirline = document.querySelector(`#bulk-row-${idx} select[name*="airline_id"]`);
            if(srcAirline && tgtAirline){ tgtAirline.value = srcAirline.value; bulkAutoFlightNum(tgtAirline,idx); }
            // clone route select + hidden fields
            const srcRoute = src.querySelectorAll('select')[1];
            const tgtRoute = document.querySelectorAll(`#bulk-row-${idx} select`)[1];
            if(srcRoute && tgtRoute){
                tgtRoute.value = srcRoute.value;
                ['fc','fcode','tc','tcode'].forEach(k=>{
                    const s=document.getElementById(`bulk-${k}-${cloneFrom}`);
                    const t=document.getElementById(`bulk-${k}-${idx}`);
                    if(s&&t) t.value=s.value;
                });
            }
            // clone seats + prices (NOT dates)
            ['seats','eco','biz','chd','inf'].forEach(k=>{
                const s=document.getElementById(`bulk-${k}-${cloneFrom}`);
                const t=document.getElementById(`bulk-${k}-${idx}`);
                if(s&&t) t.value=s.value;
            });
        }
    }
    bulkUpdateCount();
    setTimeout(()=>document.getElementById('bulk-row-'+idx)?.scrollIntoView({block:'nearest',behavior:'smooth'}),50);
}

function bulkRemoveRow(idx){
    const card = document.getElementById('bulk-row-'+idx);
    if(card){ card.style.opacity='0'; card.style.transform='scale(.97)'; card.style.transition='all .15s'; setTimeout(()=>{ card.remove(); bulkUpdateCount(); },150); }
}

function bulkCloneLastRow(){
    const cards = document.querySelectorAll('#bulkRows .sched-card');
    if(!cards.length){ bulkAddRow(); return; }
    const lastId = parseInt(cards[cards.length-1].id.replace('bulk-row-',''));
    bulkAddRow(lastId);
}

function bulkAutoFlightNum(sel, idx){
    const opt = sel.options[sel.selectedIndex];
    if(!opt||!opt.value) return;
    const code = (opt.dataset.code||opt.text.substring(0,2)).toUpperCase().replace(/[^A-Z0-9]/g,'');
    const fn = code + (100+Math.floor(Math.random()*900));
    document.getElementById('bulk-fn-'+idx).value = fn;
    const lbl = document.getElementById('bulk-fn-label-'+idx);
    if(lbl) lbl.textContent = fn;
}

function bulkFillRoute(sel, idx){
    const opt = sel.options[sel.selectedIndex];
    if(!opt||!opt.value) return;
    document.getElementById('bulk-fc-'+idx).value    = opt.dataset.fc||'';
    document.getElementById('bulk-fcode-'+idx).value = opt.dataset.fcode||'';
    document.getElementById('bulk-tc-'+idx).value    = opt.dataset.tc||'';
    document.getElementById('bulk-tcode-'+idx).value = opt.dataset.tcode||'';
}

function bulkApplyTemplate(){
    const tmplAirline = document.getElementById('bulk_tmpl_airline');
    const tmplRoute   = document.getElementById('bulk_tmpl_route');
    const tmplSeats   = document.getElementById('bulk_tmpl_seats').value;
    const tmplEco     = document.getElementById('bulk_tmpl_eco').value;
    const tmplBiz     = document.getElementById('bulk_tmpl_biz').value;
    const tmplChd     = document.getElementById('bulk_tmpl_chd').value;
    const tmplInf     = document.getElementById('bulk_tmpl_inf').value;
    const routeOpt    = tmplRoute.options[tmplRoute.selectedIndex];

    document.querySelectorAll('#bulkRows .sched-card').forEach(card=>{
        const idx = parseInt(card.id.replace('bulk-row-',''));
        if(isNaN(idx)) return;
        if(tmplAirline.value){
            const sel = card.querySelector(`select[name*="airline_id"]`);
            if(sel){ sel.value = tmplAirline.value; bulkAutoFlightNum(sel,idx); }
        }
        if(tmplRoute.value && routeOpt){
            const rsel = card.querySelectorAll('select')[1];
            if(rsel) rsel.value = tmplRoute.value;
            document.getElementById('bulk-fc-'+idx).value    = routeOpt.dataset.fc||'';
            document.getElementById('bulk-fcode-'+idx).value = routeOpt.dataset.fcode||'';
            document.getElementById('bulk-tc-'+idx).value    = routeOpt.dataset.tc||'';
            document.getElementById('bulk-tcode-'+idx).value = routeOpt.dataset.tcode||'';
        }
        if(tmplSeats){ const el=document.getElementById('bulk-seats-'+idx); if(el) el.value=tmplSeats; }
        if(tmplEco)  { const el=document.getElementById('bulk-eco-'+idx); if(el) el.value=tmplEco; }
        if(tmplBiz)  { const el=document.getElementById('bulk-biz-'+idx); if(el) el.value=tmplBiz; }
        if(tmplChd)  { const el=document.getElementById('bulk-chd-'+idx); if(el) el.value=tmplChd; }
        if(tmplInf)  { const el=document.getElementById('bulk-inf-'+idx); if(el) el.value=tmplInf; }
    });
}

function bulkUpdateCount(){
    const n = document.querySelectorAll('#bulkRows .sched-card').length;
    const count = document.getElementById('bulkRowCount');
    if(count) count.textContent = n > 0 ? n+' schedule'+(n!==1?'s':'')+' ready' : '';
    const btn = document.getElementById('bulkSubmitBtn');
    if(btn) btn.innerHTML = n > 0
        ? `<i class="fas fa-paper-plane" style="margin-right:6px;"></i>Create ${n} Schedule${n!==1?'s':''}`
        : `<i class="fas fa-paper-plane" style="margin-right:6px;"></i>Create Schedules`;
}

// init 3 cards on first open
document.getElementById('bulkScheduleModal').addEventListener('click',function(e){
    if(e.target===this) closeModal('bulkScheduleModal');
});
(function(){
    const btn = document.querySelector('[onclick*="bulkScheduleModal"]');
    if(btn) btn.addEventListener('click',function(){
        if(!document.querySelectorAll('#bulkRows .sched-card').length){
            bulkAddRow(); bulkAddRow(); bulkAddRow(); bulkUpdateCount();
        }
    });
})();

// submit: sync available_seats
document.getElementById('bulkScheduleForm').addEventListener('submit',function(e){
    const cards = document.querySelectorAll('#bulkRows .sched-card');
    if(!cards.length){ e.preventDefault(); alert('Please add at least one flight schedule.'); return; }
    cards.forEach(card=>{
        const idx = parseInt(card.id.replace('bulk-row-',''));
        if(isNaN(idx)) return;
        const seats = document.getElementById('bulk-seats-'+idx);
        const avail = document.getElementById('bulk-avail-'+idx);
        if(seats && avail) avail.value = seats.value || 150;
    });
});

// ── EDIT SCHEDULE ──────────────────────────────────────────────────────────
function editSchedule(f){
    const cls=typeof f.seat_classes==='string'?JSON.parse(f.seat_classes||'{}'):(f.seat_classes||{});
    document.getElementById('editScheduleForm').action=`/admin/module-data/ticket/flights/${f.id}`;
    document.getElementById('es_airline').value=f.airline_id||'';
    document.getElementById('es_num').value=f.flight_number||'';
    document.getElementById('es_status').value=f.status||'scheduled';
    document.getElementById('es_fc').value=f.from_city||'';
    document.getElementById('es_fcode').value=f.from_code||'';
    document.getElementById('es_tc').value=f.to_city||'';
    document.getElementById('es_tcode').value=f.to_code||'';
    document.getElementById('es_dep').value=f.departure_at?f.departure_at.slice(0,16):'';
    document.getElementById('es_arr').value=f.arrival_at?f.arrival_at.slice(0,16):'';
    document.getElementById('es_total').value=f.total_seats||'';
    document.getElementById('es_avail').value=f.available_seats||'';
    document.getElementById('es_eco').value=cls.economy||0;
    document.getElementById('es_biz').value=cls.business||0;
    document.getElementById('es_chd').value=cls.child||0;
    document.getElementById('es_inf').value=cls.infant||0;
    document.getElementById('editScheduleModal').classList.add('open');
}
</script>
@endsection
