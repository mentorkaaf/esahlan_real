@extends('admin.layouts.app')
@section('title', 'Orders')
@section('content')

<style>
.bulk-bar {
    display:none;align-items:center;gap:12px;flex-wrap:wrap;
    background:#07003B;color:#fff;
    padding:10px 20px;border-radius:10px;margin-bottom:16px;
    box-shadow:0 4px 16px rgba(7,0,59,.35);
    position:sticky;top:72px;z-index:50;
    animation:slideDown .2s ease;
}
.bulk-bar.visible{display:flex;}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}
.bulk-count{font-weight:700;font-size:13px;white-space:nowrap;}
.bulk-count span{background:#FF8A00;color:#fff;padding:2px 8px;border-radius:20px;margin-right:4px;}
.bulk-sep{width:1px;height:22px;background:rgba(255,255,255,.15);}
.bulk-dd{position:relative;user-select:none;}
.bulk-dd-btn{padding:7px 30px 7px 12px;border-radius:8px;border:1.5px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);color:#fff;font-size:13px;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:8px;min-width:180px;}
.bulk-dd-btn::after{content:'â–¾';position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:11px;opacity:.7;}
.bulk-dd-menu{display:none;position:absolute;top:calc(100% + 4px);left:0;min-width:100%;background:#1e293b;border:1px solid rgba(255,255,255,.15);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.4);z-index:9999;overflow:hidden;}
.bulk-dd-menu.open{display:block;}
.bulk-dd-item{padding:9px 14px;color:#e2e8f0;font-size:13px;cursor:pointer;transition:background .12s;}
.bulk-dd-item:hover{background:rgba(255,255,255,.1);color:#fff;}
.bulk-dd-item.placeholder{color:rgba(255,255,255,.4);cursor:default;}
.bulk-dd-item.placeholder:hover{background:none;}
.btn-bulk-apply{padding:7px 14px;border-radius:8px;border:none;background:#FF8A00;color:#fff;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;transition:background .15s;}
.btn-bulk-apply:hover{background:#e07500;}
.btn-bulk-delete{padding:7px 14px;border-radius:8px;border:1.5px solid rgba(239,68,68,.5);background:rgba(239,68,68,.12);color:#fca5a5;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all .15s;}
.btn-bulk-delete:hover{background:rgba(239,68,68,.25);border-color:rgba(239,68,68,.8);}
.btn-bulk-cancel{margin-left:auto;padding:5px 12px;border-radius:8px;border:1.5px solid rgba(255,255,255,.15);background:transparent;color:rgba(255,255,255,.5);font-size:12px;cursor:pointer;transition:all .15s;}
.btn-bulk-cancel:hover{color:#fff;border-color:rgba(255,255,255,.4);}
.row-cb{width:16px;height:16px;cursor:pointer;accent-color:var(--brand);}
th.cb-col,td.cb-col{width:44px;padding-left:16px!important;}
tbody tr.selected td{background:rgba(255,138,0,.04);}

/* Module section header */
.module-section{margin-bottom:28px;}
.module-section-header{display:flex;align-items:center;gap:12px;margin-bottom:12px;}
.module-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.module-title{font-size:16px;font-weight:800;color:var(--navy);}
.module-count{font-size:12px;font-weight:700;padding:3px 10px;border-radius:20px;}
.section-card{border-radius:14px;overflow:hidden;border:1.5px solid var(--border);background:#fff;}
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">Orders</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li>Orders</li>
        </ul>
    </div>
    <div style="font-size:13px;color:var(--text-muted);">
        <i class="fas fa-sync-alt" style="color:var(--brand);margin-right:6px;"></i>
        Updated {{ \Carbon\Carbon::now(\App\Helpers\AppSettings::timezone())->format('H:i') }}
    </div>
</div>

{{-- Live Drivers Map --}}
<div style="margin-bottom:16px;">
    <div onclick="document.getElementById('driversMapWrap').style.display = document.getElementById('driversMapWrap').style.display === 'none' ? 'block' : 'none'"
         style="background:#fff;border-radius:14px;padding:14px 20px;cursor:pointer;border:1.5px solid #f0f1f5;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-map-marked-alt" style="color:#FF8A00;font-size:18px;"></i>
        <span style="font-weight:700;font-size:14px;color:#07003B;">Live Drivers Map</span>
        <span style="font-size:12px;color:#8A8A9A;margin-left:4px;">{{ $availableDrivers->count() }} drivers</span>
        <i class="fas fa-chevron-down" style="margin-left:auto;color:#8A8A9A;font-size:12px;"></i>
    </div>
    <div id="driversMapWrap" style="display:none;margin-top:8px;">
        <div id="driversMap" style="height:350px;border-radius:14px;border:1.5px solid #f0f1f5;overflow:hidden;"></div>
    </div>
</div>

@push('scripts')
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC1pxwcaFZxDXwqDpxK_gDfPAdpFM8bTnc&callback=initDriversMap" async defer></script>
<script>
var __driversMapInit = false;
function initDriversMap() {
    if (__driversMapInit) return;
    __driversMapInit = true;

    var center = {lat: 2.0469, lng: 45.3182};
    var map = new google.maps.Map(document.getElementById('driversMap'), {
        zoom: 13, center: center,
        mapTypeControl: true,
        mapTypeControlOptions: { style: google.maps.MapTypeControlStyle.HORIZONTAL_BAR, position: google.maps.ControlPosition.TOP_RIGHT, mapTypeIds: ['roadmap','satellite','hybrid'] },
        streetViewControl: false,
    });

    var vehicleIcons = {
        motorcycle: 'ðŸï¸', bajaj: 'ðŸ›º', car: 'ðŸš—', van: 'ðŸš', truck: 'ðŸš›', bicycle: 'ðŸš²', pickup: 'ðŸš›'
    };

    var drivers = {{ Illuminate\Support\Js::from($availableDrivers->map(function($d) {
        return [
            'id' => $d->id,
            'name' => $d->user ? $d->user->name : 'Driver',
            'phone' => $d->user ? $d->user->phone : '',
            'vehicle' => $d->vehicle_type,
            'status' => $d->status,
            'lat' => (float) $d->latitude,
            'lng' => (float) $d->longitude,
            'rating' => $d->rating,
        ];
    })->values()) }};

    var iw = new google.maps.InfoWindow();
    var bounds = new google.maps.LatLngBounds();

    drivers.forEach(function(d) {
        if (!d.lat || !d.lng) return;
        var emoji = vehicleIcons[d.vehicle] || 'ðŸš—';
        var statusColor = d.status === 'available' ? '#10B981' : '#F59E0B';
        var marker = new google.maps.Marker({
            position: {lat: d.lat, lng: d.lng},
            map: map,
            label: {text: emoji, fontSize: '22px'},
            title: d.name + ' (' + d.vehicle + ')',
        });
        marker.addListener('click', function() {
            iw.setContent(
                '<div style="padding:4px;min-width:150px;">' +
                '<div style="font-weight:800;font-size:14px;">' + d.name + '</div>' +
                '<div style="font-size:12px;color:#666;">' + d.phone + '</div>' +
                '<div style="margin-top:4px;">' +
                '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:' + statusColor + '20;color:' + statusColor + ';">' + d.status + '</span>' +
                ' <span style="color:#f59e0b;">â˜… ' + (d.rating||5) + '</span>' +
                '</div>' +
                '<div style="font-size:11px;color:#888;margin-top:2px;">' + emoji + ' ' + d.vehicle + '</div>' +
                '</div>'
            );
            iw.open(map, marker);
        });
        bounds.extend({lat: d.lat, lng: d.lng});
    });

    if (drivers.length > 0) {
        if (drivers.length === 1) { map.setCenter({lat: drivers[0].lat, lng: drivers[0].lng}); map.setZoom(15); }
        else map.fitBounds(bounds);
    }
}

// Re-init map when expanded
document.getElementById('driversMapWrap')?.addEventListener('transitionend', function() {
    if (this.style.display !== 'none') google.maps.event.trigger(document.getElementById('driversMap'), 'resize');
});
</script>
@endpush

{{-- Bulk Bar --}}
<div class="bulk-bar" id="bulkBar">
    <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:13px;font-weight:700;white-space:nowrap;">
        <input type="checkbox" id="selectAllGlobal" onchange="globalToggleAll(this)" style="width:15px;height:15px;cursor:pointer;">
        All
    </label>
    <div class="bulk-sep"></div>
    <div class="bulk-count"><span id="bulkCount">0</span> selected</div>
    <div class="bulk-sep"></div>
    <div class="bulk-dd" id="bulkDd">
        <div class="bulk-dd-btn" id="bulkDdBtn" onclick="toggleBulkDd()">
            <span id="bulkDdLabel">â€” Change status to â€”</span>
        </div>
        <div class="bulk-dd-menu" id="bulkDdMenu">
            <div class="bulk-dd-item placeholder" onclick="setBulkStatus('','â€” Change status to â€”')">â€” Change status to â€”</div>
            <div class="bulk-dd-item" onclick="setBulkStatus('pending','Pending')">Pending</div>
            <div class="bulk-dd-item" onclick="setBulkStatus('confirmed','Confirmed')">Confirmed</div>
            <div class="bulk-dd-item" onclick="setBulkStatus('preparing','Preparing')">Preparing</div>
            <div class="bulk-dd-item" onclick="setBulkStatus('ready_for_pickup','Ready for Pickup')">Ready for Pickup</div>
            <div class="bulk-dd-item" onclick="setBulkStatus('out_for_delivery','Out for Delivery')">Out for Delivery</div>
            <div class="bulk-dd-item" onclick="setBulkStatus('delivered','Delivered')">Delivered</div>
            <div class="bulk-dd-item" onclick="setBulkStatus('cancelled','Cancelled')">Cancelled</div>
        </div>
    </div>
    <input type="hidden" id="bulkStatusSel" value="">
    <button class="btn-bulk-apply" onclick="bulkApply()"><i class="fas fa-check"></i> Apply</button>
    <div class="bulk-sep"></div>
    <button class="btn-bulk-delete" onclick="bulkDelete()"><i class="fas fa-trash"></i> Delete</button>
    <button class="btn-bulk-cancel" onclick="clearSelection()"><i class="fas fa-times"></i> Cancel</button>
</div>

{{-- Status Tabs --}}
@php
$statuses=[''=>['All','secondary'],'pending'=>['Pending','warning'],'confirmed'=>['Confirmed','info'],'preparing'=>['Preparing','info'],'ready'=>['Ready','teal'],'picked_up'=>['Picked Up','purple'],'delivered'=>['Delivered','success'],'cancelled'=>['Cancelled','danger']];
@endphp
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;">
    @foreach($statuses as $st=>[$label,$color])
    <a href="{{ route('admin.orders.index',['status'=>$st]+request()->except('status','page')) }}"
       style="padding:7px 16px;border-radius:20px;font-size:12.5px;font-weight:600;text-decoration:none;border:1.5px solid;transition:all .15s;
              {{ request('status')===$st?'background:var(--brand);color:#fff;border-color:var(--brand);':'background:#fff;color:var(--text-muted);border-color:var(--border);' }}">
        {{ $label }}
    </a>
    @endforeach
</div>

{{-- Filter Bar --}}
<div class="card" style="margin-bottom:20px;">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;width:100%;">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <div style="flex:1;min-width:200px;">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search order #, customer, referenceâ€¦" value="{{ request('search') }}">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" style="width:160px;">
            <span style="color:var(--text-muted);font-size:12px;">to</span>
            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" style="width:160px;">
            @if(request()->hasAny(['search','date_from','date_to']))
            <a href="{{ route('admin.orders.index',['status'=>request('status')]) }}" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
            @endif
        </form>
    </div>
</div>

{{-- Bulk form (hidden) --}}
<form id="bulkForm" method="POST" action="{{ route('admin.orders.bulk') }}">
    @csrf
    <input type="hidden" name="action" id="bulkAction">
    <input type="hidden" name="status" id="bulkStatusInput">
</form>

@php
// Helper: status badge class
function orderBadge($status) {
    return ['pending'=>'badge-warning','confirmed'=>'badge-info','preparing'=>'badge-info',
            'ready'=>'badge-teal','ready_for_pickup'=>'badge-teal','picked_up'=>'badge-purple',
            'out_for_delivery'=>'badge-purple','delivered'=>'badge-success',
            'cancelled'=>'badge-danger','failed'=>'badge-danger'][$status] ?? 'badge-secondary';
}
@endphp

{{-- â•â•â• MODULE SECTIONS â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
@if($moduleGroups)

@php
// Sort module groups by the most recent order's created_at (newest module first)
$sortedGroups = $moduleGroups->sortByDesc(fn($grp) => $grp->max('created_at'));
@endphp

@foreach($sortedGroups as $slug => $grpOrders)
@php
$meta  = $moduleMeta[$slug] ?? ['label' => strtoupper($slug), 'icon' => 'fa-shopping-bag', 'color' => '#64748b'];
$color = $meta['color'];
$label = $meta['label'];
$icon  = $meta['icon'];
$count = $grpOrders->count();
@endphp

<div class="module-section">
    {{-- Section Header --}}
    <div class="module-section-header">
        <div class="module-icon" style="background:{{ $color }}18;color:{{ $color }};">
            <i class="fas {{ $icon }}"></i>
        </div>
        <span class="module-title">{{ $label }}</span>
        <span class="module-count" style="background:{{ $color }}18;color:{{ $color }};border:1px solid {{ $color }}30;">
            {{ $count }} {{ Str::plural('order', $count) }}
        </span>
        <div style="flex:1;height:1px;background:var(--border);margin-left:6px;"></div>
    </div>

    {{-- Orders Table --}}
    <div class="section-card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="cb-col">
                            <input type="checkbox" class="row-cb select-all-module" data-module="{{ $slug }}" title="Select all">
                        </th>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Vendor</th>
                        <th>Deliveryman</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($grpOrders as $order)
                    @php
                        $total = $order->total_amount ?? $order->total ?? 0;
                        $bc    = orderBadge($order->status);
                        $ps    = $order->payment_status ?? 'pending';
                    @endphp
                    <tr data-id="{{ $order->id }}" data-module="{{ $slug }}">
                        <td class="cb-col">
                            <input type="checkbox" class="row-cb order-cb" value="{{ $order->id }}" data-module="{{ $slug }}">
                        </td>
                        <td>
                            <a href="{{ route('admin.orders.show',$order) }}"
                               style="font-weight:700;color:var(--navy);text-decoration:none;font-size:13px;">
                                {{ $order->order_number }}
                            </a>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="avatar avatar-sm" style="background:{{ $color }}22;color:{{ $color }};font-weight:700;">
                                    {{ strtoupper(substr($order->user?->name ?? 'U',0,1)) }}
                                </div>
                                <div>
                                    <div style="font-weight:600;font-size:13px;">{{ $order->user?->name ?? 'â€”' }}</div>
                                    <div style="font-size:11px;color:var(--text-muted);">{{ $order->user?->phone ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="font-size:12px;color:var(--text-muted);">{{ $order->vendor?->name ?? 'â€”' }}</td>
                        <td>
                            @if($order->deliveryman?->user?->name)
                                <div style="display:flex;align-items:center;gap:7px;">
                                    <div class="avatar avatar-sm avatar-green">{{ strtoupper(substr($order->deliveryman->user->name,0,1)) }}</div>
                                    <span style="font-size:12.5px;font-weight:600;">{{ $order->deliveryman->user->name }}</span>
                                </div>
                            @else
                                <span class="badge badge-secondary">Unassigned</span>
                            @endif
                        </td>
                        <td><span style="font-weight:700;">${{ number_format($total,2) }}</span></td>
                        <td>
                            <span class="badge {{ $ps==='paid'?'badge-success':($ps==='refunded'?'badge-info':'badge-warning') }}">
                                {{ ucfirst($ps) }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $bc }} badge-dot">{{ ucwords(str_replace('_',' ',$order->status)) }}</span>
                        </td>
                        <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
                            {{ $order->created_at->setTimezone(\App\Helpers\AppSettings::timezone())->format('d M') }}<br>
                            <span style="font-size:11px;">{{ $order->created_at->setTimezone(\App\Helpers\AppSettings::timezone())->format('H:i') }}</span>
                        </td>
                        <td>
                            <a href="{{ route('admin.orders.show',$order) }}" class="btn btn-outline btn-xs">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="10">
                        <div class="empty-state"><i class="fas {{ $icon }}"></i><p>No {{ $label }} orders</p></div>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endforeach

@else
{{-- Single module filtered view (paginated) --}}
<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(255,138,0,.1);color:var(--brand);">
                <i class="fas fa-shopping-bag"></i>
            </div>
            Orders
            <span class="badge badge-orange" style="margin-left:4px;">{{ $orders->total() }}</span>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th class="cb-col"><input type="checkbox" class="row-cb" id="selectAll"></th>
                    <th>Order #</th><th>Customer</th><th>Module / Vendor</th>
                    <th>Deliveryman</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                @php $total=$order->total_amount??$order->total??0; $bc=orderBadge($order->status); $ps=$order->payment_status??'pending'; @endphp
                <tr data-id="{{ $order->id }}">
                    <td class="cb-col"><input type="checkbox" class="row-cb order-cb" value="{{ $order->id }}"></td>
                    <td><a href="{{ route('admin.orders.show',$order) }}" style="font-weight:700;color:var(--navy);text-decoration:none;font-size:13px;">{{ $order->order_number }}</a></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="avatar avatar-sm avatar-orange">{{ strtoupper(substr($order->user?->name??'U',0,1)) }}</div>
                            <div>
                                <div style="font-weight:600;font-size:13px;">{{ $order->user?->name??'â€”' }}</div>
                                <div style="font-size:11px;color:var(--text-muted);">{{ $order->user?->phone??'' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($order->module_slug)<span class="badge badge-dark" style="margin-bottom:3px;">{{ strtoupper($order->module_slug) }}</span><br>@endif
                        <span style="font-size:12px;color:var(--text-muted);">{{ $order->vendor?->name??'' }}</span>
                    </td>
                    <td>
                        @if($order->deliveryman?->user?->name)
                            <div style="display:flex;align-items:center;gap:7px;">
                                <div class="avatar avatar-sm avatar-green">{{ strtoupper(substr($order->deliveryman->user->name,0,1)) }}</div>
                                <span style="font-size:12.5px;font-weight:600;">{{ $order->deliveryman->user->name }}</span>
                            </div>
                        @else<span class="badge badge-secondary">Unassigned</span>@endif
                    </td>
                    <td><span style="font-weight:700;">${{ number_format($total,2) }}</span></td>
                    <td>
                        @php $opm = $order->payment_method ?? 'cod'; @endphp
                        <span style="font-size:11px;font-weight:700;color:{{ $opm==='mobile_pay'?'#3949AB':($opm==='wallet'?'#2E7D32':($opm==='waafi_pay'?'#7B1FA2':'#555')) }};">
                            {{ ucwords(str_replace('_',' ',$opm)) }}
                        </span>
                        <br><span class="badge {{ $ps==='paid'?'badge-success':($ps==='refunded'?'badge-info':'badge-warning') }}" style="font-size:10px;">{{ ucfirst($ps) }}</span>
                    </td>
                    <td><span class="badge {{ $bc }} badge-dot">{{ ucwords(str_replace('_',' ',$order->status)) }}</span></td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
                        {{ $order->created_at->setTimezone(\App\Helpers\AppSettings::timezone())->format('d M') }}<br>
                        <span style="font-size:11px;">{{ $order->created_at->setTimezone(\App\Helpers\AppSettings::timezone())->format('H:i') }}</span>
                    </td>
                    <td><a href="{{ route('admin.orders.show',$order) }}" class="btn btn-outline btn-xs"><i class="fas fa-eye"></i> View</a></td>
                </tr>
                @empty
                <tr><td colspan="10"><div class="empty-state"><i class="fas fa-shopping-bag"></i><h3>No orders found</h3><p>Try adjusting your filters</p></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
    <div class="card-footer" style="display:flex;justify-content:center;">{{ $orders->withQueryString()->links() }}</div>
    @endif
</div>
@endif

{{-- â•â•â• EXCHANGE ORDERS SECTION â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
@if($exchangeOrders->count())
<div class="module-section" style="margin-top:8px;">
    <div class="module-section-header">
        <div class="module-icon" style="background:rgba(59,130,246,.12);color:#3b82f6;">
            <i class="fas fa-exchange-alt"></i>
        </div>
        <span class="module-title">eExchange</span>
        <span class="module-count" style="background:rgba(59,130,246,.1);color:#3b82f6;border:1px solid rgba(59,130,246,.2);">
            {{ $exchangeOrders->count() }} {{ Str::plural('order',$exchangeOrders->count()) }}
        </span>
        <div style="flex:1;height:1px;background:var(--border);margin-left:6px;"></div>
        <a href="{{ route('admin.exchange.index') }}" style="font-size:12px;color:#3b82f6;font-weight:700;text-decoration:none;white-space:nowrap;">
            View All <i class="fas fa-arrow-right" style="margin-left:3px;"></i>
        </a>
    </div>

    {{-- Bulk-delete bar --}}
    <div id="excBulkBar" style="display:none;align-items:center;gap:10px;background:var(--navy);color:#fff;
        padding:10px 16px;border-radius:10px;margin-bottom:8px;">
        <span style="font-weight:700;font-size:13px;">
            <span id="excBulkCount" style="background:var(--brand);color:#fff;padding:2px 9px;border-radius:20px;margin-right:6px;">0</span> selected
        </span>
        <button onclick="excBulkDelete()" style="padding:7px 14px;border-radius:8px;border:1.5px solid rgba(239,68,68,.5);
            background:rgba(239,68,68,.15);color:#fca5a5;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-trash"></i> Delete Selected
        </button>
        <button onclick="excClearSelection()" style="margin-left:auto;padding:5px 12px;border-radius:8px;
            border:1.5px solid rgba(255,255,255,.15);background:transparent;color:rgba(255,255,255,.5);font-size:12px;cursor:pointer;">
            <i class="fas fa-times"></i> Cancel
        </button>
    </div>

    <form id="excBulkForm" method="POST" action="{{ route('admin.exchange.bulk-destroy') }}">
        @csrf
        <div class="section-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="excSelectAll" onchange="excToggleAll(this)"></th>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th>Exchange</th>
                            <th>Sent</th>
                            <th>Fee</th>
                            <th>Received</th>
                            <th>Recipient Phone</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($exchangeOrders as $ex)
                        @php
                            $exBadge=['pending'=>'badge-warning','processing'=>'badge-info','completed'=>'badge-success','failed'=>'badge-danger'][$ex->status]??'badge-secondary';
                            $wc=['EVC'=>'#e74c3c','EDAHAB'=>'#27ae60','JEEP'=>'#2980b9','PREMIER'=>'#8e44ad'];
                            $fc=$wc[$ex->from_wallet]??'#64748b';
                            $tc=$wc[$ex->to_wallet]??'#64748b';
                        @endphp
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $ex->id }}" class="exc-cb" onchange="excUpdateBar()"></td>
                            <td style="font-weight:700;font-size:13px;font-family:monospace;color:var(--navy);">{{ $ex->reference }}</td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div class="avatar avatar-sm" style="background:rgba(59,130,246,.15);color:#3b82f6;font-weight:700;">{{ strtoupper(substr($ex->user_name,0,1)) }}</div>
                                    <div>
                                        <div style="font-weight:600;font-size:13px;">{{ $ex->user_name }}</div>
                                        <div style="font-size:11px;color:var(--text-muted);">{{ $ex->user_phone }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <span style="background:{{ $fc }}18;color:{{ $fc }};border:1px solid {{ $fc }}30;border-radius:6px;padding:3px 8px;font-size:11px;font-weight:700;">{{ $ex->from_wallet }}</span>
                                    <i class="fas fa-arrow-right" style="color:var(--text-muted);font-size:10px;"></i>
                                    <span style="background:{{ $tc }}18;color:{{ $tc }};border:1px solid {{ $tc }}30;border-radius:6px;padding:3px 8px;font-size:11px;font-weight:700;">{{ $ex->to_wallet }}</span>
                                </div>
                            </td>
                            <td style="font-weight:700;">${{ number_format($ex->sent_amount,2) }}</td>
                            <td style="color:#ef4444;font-weight:600;">-${{ number_format($ex->fee_amount,2) }}</td>
                            <td style="color:#10b981;font-weight:700;">${{ number_format($ex->converted_amount,2) }}</td>
                            <td style="font-size:12.5px;font-weight:600;">
                                <i class="fas fa-phone" style="color:var(--text-muted);font-size:10px;margin-right:4px;"></i>{{ $ex->recipient_phone }}
                            </td>
                            <td><span class="badge {{ $exBadge }}">{{ ucfirst($ex->status) }}</span></td>
                            <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
                                {{ \Carbon\Carbon::parse($ex->created_at)->timezone('Africa/Mogadishu')->format('d M') }}<br>
                                <span style="font-size:11px;">{{ \Carbon\Carbon::parse($ex->created_at)->timezone('Africa/Mogadishu')->format('H:i') }}</span>
                            </td>
                            <td>
                                <a href="{{ route('admin.exchange.show',$ex->id) }}" class="btn btn-outline btn-xs">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<script>
function excUpdateBar() {
    const cbs  = document.querySelectorAll('.exc-cb:checked');
    const bar  = document.getElementById('excBulkBar');
    const cnt  = document.getElementById('excBulkCount');
    cnt.textContent = cbs.length;
    bar.style.display = cbs.length > 0 ? 'flex' : 'none';
}
function excToggleAll(el) {
    document.querySelectorAll('.exc-cb').forEach(cb => cb.checked = el.checked);
    excUpdateBar();
}
function excClearSelection() {
    document.querySelectorAll('.exc-cb, #excSelectAll').forEach(cb => cb.checked = false);
    excUpdateBar();
}
function excBulkDelete() {
    const n = document.querySelectorAll('.exc-cb:checked').length;
    if (!n) return;
    if (!confirm('Delete ' + n + ' exchange order(s)?')) return;
    document.getElementById('excBulkForm').submit();
}
</script>
@endif

@push('scripts')
<script>
function getChecked() {
    return [...document.querySelectorAll('.order-cb:checked')];
}
function updateBar() {
    const checked = getChecked();
    document.getElementById('bulkCount').textContent = checked.length;
    document.getElementById('bulkBar').classList.toggle('visible', checked.length > 0);
    document.querySelectorAll('.order-cb').forEach(cb => cb.closest('tr').classList.toggle('selected', cb.checked));
}
// Per-module select-all checkboxes
document.querySelectorAll('.select-all-module').forEach(sa => {
    sa.addEventListener('change', () => {
        const mod = sa.dataset.module;
        document.querySelectorAll(`.order-cb[data-module="${mod}"]`).forEach(cb => cb.checked = sa.checked);
        updateBar();
    });
});
document.querySelectorAll('.order-cb').forEach(cb => cb.addEventListener('change', updateBar));

function globalToggleAll(el) {
    document.querySelectorAll('.order-cb').forEach(cb => cb.checked = el.checked);
    document.querySelectorAll('.select-all-module').forEach(sa => sa.checked = el.checked);
    document.querySelectorAll('.exc-cb').forEach(cb => cb.checked = el.checked);
    const excSa = document.getElementById('excSelectAll');
    if (excSa) excSa.checked = el.checked;
    updateBar();
    excUpdateBar();
}
function clearSelection() {
    document.querySelectorAll('.order-cb, .select-all-module').forEach(cb => cb.checked = false);
    const ga = document.getElementById('selectAllGlobal');
    if (ga) ga.checked = false;
    updateBar();
}
let _bulkStatus = '';
function toggleBulkDd() {
    document.getElementById('bulkDdMenu').classList.toggle('open');
}
function setBulkStatus(val, label) {
    _bulkStatus = val;
    document.getElementById('bulkStatusSel').value = val;
    document.getElementById('bulkDdLabel').textContent = label;
    document.getElementById('bulkDdMenu').classList.remove('open');
}
document.addEventListener('click', function(e) {
    if (!document.getElementById('bulkDd')?.contains(e.target)) {
        document.getElementById('bulkDdMenu')?.classList.remove('open');
    }
});
function bulkApply() {
    const status = document.getElementById('bulkStatusSel').value;
    if (!status) { alert('Please select a status.'); return; }
    const ids = getChecked().map(cb => cb.value);
    if (!ids.length) return;
    if (!confirm(`Update ${ids.length} order(s) to "${status}"?`)) return;
    submitBulk('status', status, ids);
}
function bulkDelete() {
    const ids = getChecked().map(cb => cb.value);
    if (!ids.length) return;
    if (!confirm(`Permanently delete ${ids.length} order(s)?`)) return;
    submitBulk('delete', '', ids);
}
function submitBulk(action, status, ids) {
    const form = document.getElementById('bulkForm');
    document.getElementById('bulkAction').value = action;
    document.getElementById('bulkStatusInput').value = status;
    form.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
    ids.forEach(id => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
        form.appendChild(inp);
    });
    form.submit();
}
</script>
@endpush
@endsection
