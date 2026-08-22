@extends('admin.layouts.app')
@section('title', 'Users')

@push('styles')
<style>
#users-map { height: 480px; border-radius: 16px; overflow: hidden; }
.gm-popup { padding: 12px 16px; font-family: inherit; min-width: 190px; }
.gm-popup .name  { font-weight: 800; font-size: 14px; color: #07003B; }
.gm-popup .phone { font-size: 12px; color: #888; margin-top: 2px; }
.gm-popup .act-badge { display:inline-flex;align-items:center;gap:4px;margin-top:6px;font-size:11px;font-weight:700;padding:3px 8px;border-radius:6px; }
.gm-popup .act-daily    { background:#DCFCE7;color:#16A34A; }
.gm-popup .act-semi     { background:#FEF9C3;color:#CA8A04; }
.gm-popup .act-inactive { background:#F3F4F6;color:#6B7280; }
.gm-popup a      { display:block; margin-top:8px; text-align:center; background:#07003B; color:#fff; text-decoration:none; padding:5px 10px; border-radius:8px; font-size:12px; font-weight:700; }
.gm-popup .ts    { font-size:10px; color:#bbb; margin-top:4px; }
/* map filter chips */
.map-filter-bar { display:flex;gap:8px;flex-wrap:wrap;align-items:center;padding:12px 16px 0; }
.map-chip { display:inline-flex;align-items:center;gap:6px;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700;cursor:pointer;border:2px solid transparent;transition:all .15s; }
.map-chip .dot { width:10px;height:10px;border-radius:50%;display:inline-block; }
.map-chip.active  { border-color:#07003B; }
.map-chip-all     { background:#EEF2FF;color:#3949AB; }
.map-chip-daily   { background:#DCFCE7;color:#16A34A; }
.map-chip-daily .dot   { background:#22C55E; }
.map-chip-semi    { background:#FEF9C3;color:#CA8A04; }
.map-chip-semi .dot    { background:#EAB308; }
.map-chip-inactive{ background:#F3F4F6;color:#6B7280; }
.map-chip-inactive .dot{ background:#9CA3AF; }
.map-stats { margin-left:auto;display:flex;gap:12px;font-size:12px;color:#888; }
.map-stats b { color:#07003B; }
</style>
@endpush

@section('content')

@php
// counts come from controller — all GPS users across all pages
// $gpsTotal, $gpsDaily, $gpsSemi, $gpsInactive are passed from AdminUserController
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">Users</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li>Users</li>
        </ul>
    </div>
</div>

{{-- â"€â"€ Live User Map â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€ --}}
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:#FF8A00;">
                <i class="fas fa-map-marked-alt"></i>
            </div>
            Real-Time User Locations
            <span class="badge badge-warning" style="margin-left:4px;">{{ $gpsTotal }} GPS users</span>
        </div>
        <div style="font-size:11px;color:#888;display:flex;gap:14px;align-items:center;">
            <span><span style="color:#22C55E;font-weight:800;">●</span> Daily active: <b>{{ $gpsDaily }}</b></span>
            <span><span style="color:#EAB308;font-weight:800;">●</span> Semi-active: <b>{{ $gpsSemi }}</b></span>
            <span><span style="color:#9CA3AF;font-weight:800;">●</span> Inactive: <b>{{ $gpsInactive }}</b></span>
        </div>
    </div>

    {{-- Filter chips --}}
    <div class="map-filter-bar">
        <button class="map-chip map-chip-all active" id="filterAll" onclick="mapFilter('all')">
            All ({{ $gpsTotal }})
        </button>
        <button class="map-chip map-chip-daily" id="filterDaily" onclick="mapFilter('daily')">
            <span class="dot"></span> Daily Active ({{ $gpsDaily }})
        </button>
        <button class="map-chip map-chip-semi" id="filterSemi" onclick="mapFilter('semi')">
            <span class="dot"></span> Semi-Active ({{ $gpsSemi }})
        </button>
        <button class="map-chip map-chip-inactive" id="filterInactive" onclick="mapFilter('inactive')">
            <span class="dot"></span> Inactive ({{ $gpsInactive }})
        </button>
        <div style="margin-left:auto;font-size:11px;color:#aaa;">
            <i class="fas fa-sync-alt" style="margin-right:4px;"></i>Refreshes every 30s
        </div>
    </div>

    <div style="padding:12px 16px 16px;">
        <div id="users-map"></div>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;align-items:center;">
            <div style="flex:1;min-width:200px;">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search name, phone, emailâ€¦" value="{{ request('search') }}">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <select name="role" class="form-control" style="width:160px;">
                <option value="">All Roles</option>
                <option value="customer"     {{ request('role')==='customer'     ?'selected':'' }}>Customer</option>
                <option value="vendor_owner" {{ request('role')==='vendor_owner' ?'selected':'' }}>Vendor Owner</option>
                <option value="deliveryman"  {{ request('role')==='deliveryman'  ?'selected':'' }}>Deliveryman</option>
            </select>
            <select name="status" class="form-control" style="width:140px;">
                <option value="">All Status</option>
                <option value="active"   {{ request('status')==='active'   ?'selected':'' }}>Active</option>
                <option value="inactive" {{ request('status')==='inactive' ?'selected':'' }}>Inactive</option>
                <option value="banned"   {{ request('status')==='banned'   ?'selected':'' }}>Banned</option>
            </select>
            @if(request()->hasAny(['search','role','status']))
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);">
                <i class="fas fa-users"></i>
            </div>
            Users
            <span class="badge badge-info" style="margin-left:4px;">{{ $users->total() }}</span>
        </div>
        <div id="bulkActions" style="display:none;gap:8px;align-items:center;">
            <span id="selectedCount" style="font-size:13px;color:#888;"></span>
            <button onclick="confirmBulkDelete()" class="btn btn-danger btn-sm">
                <i class="fas fa-trash"></i> Delete Selected
            </button>
        </div>
    </div>
    <div class="table-wrap">
        <form id="bulkForm" action="{{ route('admin.users.bulk-destroy') }}" method="POST">
            @csrf @method('DELETE')
        </form>
        <table>
            <thead>
                <tr>
                    <th style="width:36px;"><input type="checkbox" id="selectAll" title="Select all" style="cursor:pointer;width:16px;height:16px;"></th>
                    <th>User</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Verified</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                @php
                    $colors = ['customer'=>'blue','vendor_owner'=>'purple','deliveryman'=>'green'];
                    $c = $colors[$user->role?->name ?? ''] ?? 'orange';
                @endphp
                <tr>
                    <td>
                        @if($user->role?->slug !== 'super_admin')
                        <input type="checkbox" class="row-check" value="{{ $user->id }}" style="cursor:pointer;width:16px;height:16px;">
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            @if(!empty($user->avatar_url) && !str_contains($user->avatar_url,'null'))
                                <img src="{{ $user->avatar_url }}" style="width:36px;height:36px;border-radius:9px;object-fit:cover;flex-shrink:0;">
                            @else
                                <div class="avatar avatar-sm avatar-{{ $c }}">{{ strtoupper(substr($user->name,0,1)) }}</div>
                            @endif
                            <div>
                                <div style="font-weight:700;font-size:13px;">{{ $user->name }}</div>
                                <div style="font-size:11px;color:var(--text-muted);">{{ $user->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:13px;color:var(--text-muted);">{{ $user->phone }}</td>
                    <td>
                        @php $rn = $user->role?->name ?? 'N/A'; @endphp
                        <span class="badge badge-{{ $colors[$rn] ?? 'secondary' }}">{{ ucwords(str_replace('_',' ',$rn)) }}</span>
                    </td>
                    <td>
                        @php $st = $user->status ?? 'active'; @endphp
                        <span class="badge {{ $st==='active'?'badge-success':($st==='banned'?'badge-danger':'badge-warning') }} badge-dot">
                            {{ ucfirst($st) }}
                        </span>
                    </td>
                    <td>
                        @if($user->phone_verified_at)
                            <span class="badge badge-success"><i class="fas fa-check"></i> Yes</span>
                        @else
                            <span class="badge badge-danger"><i class="fas fa-times"></i> No</span>
                        @endif
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);">{{ $user->created_at->format('d M Y') }}</td>
                    <td>
                        <div style="display:flex;gap:5px;align-items:center;">
                            <a href="{{ route('admin.users.show',$user) }}" class="btn btn-outline btn-xs"><i class="fas fa-eye"></i> View</a>
                            <form method="POST" action="{{ route('admin.users.status',$user) }}">
                                @csrf @method('PATCH')
                                @if(($user->status ?? 'active') === 'active')
                                    <input type="hidden" name="status" value="banned">
                                    <button type="submit" class="btn btn-xs" style="background:#fff5f5;color:var(--danger);border:1.5px solid #fecaca;" title="Ban">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                @else
                                    <input type="hidden" name="status" value="active">
                                    <button type="submit" class="btn btn-xs" style="background:rgba(16,185,129,0.1);color:var(--success);border:1.5px solid rgba(16,185,129,0.3);" title="Activate">
                                        <i class="fas fa-check"></i>
                                    </button>
                                @endif
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h3>No users found</h3>
                            <p>Try adjusting your filters</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
    (function(){
        const selectAll = document.getElementById('selectAll');
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');

        function updateBulkBar(){
            const checked = document.querySelectorAll('.row-check:checked');
            if(checked.length > 0){
                bulkActions.style.display = 'flex';
                selectedCount.textContent = checked.length + ' selected';
            } else {
                bulkActions.style.display = 'none';
            }
        }

        selectAll.addEventListener('change', function(){
            document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
            updateBulkBar();
        });

        document.querySelectorAll('.row-check').forEach(cb => {
            cb.addEventListener('change', updateBulkBar);
        });

        window.confirmBulkDelete = function(){
            const checked = document.querySelectorAll('.row-check:checked');
            if(!checked.length) return;
            if(!confirm('Delete ' + checked.length + ' user(s)? They will lose all app access immediately. This cannot be undone.')) return;
            const form = document.getElementById('bulkForm');
            document.querySelectorAll('.bulk-id-input').forEach(el => el.remove());
            checked.forEach(cb => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = cb.value;
                inp.className = 'bulk-id-input';
                form.appendChild(inp);
            });
            form.submit();
        };
    })();
    </script>
    @if($users->hasPages())
    <div class="card-footer" style="display:flex;justify-content:center;">
        {{ $users->withQueryString()->links() }}
    </div>
    @endif
</div>
<script>
// Activity color map
var ACTIVITY_COLORS = {
    daily:    { fill: '#22C55E', stroke: '#15803D', scale: 11 }, // green
    semi:     { fill: '#EAB308', stroke: '#A16207', scale: 10 }, // yellow
    inactive: { fill: '#9CA3AF', stroke: '#4B5563', scale: 8  }, // gray
};

var ACTIVITY_LABELS = {
    daily:    'Daily Active (last 3 days)',
    semi:     'Semi-Active (4–14 days)',
    inactive: 'Inactive (>14 days)',
};

// Initial data loaded via API (all GPS users, not paginated subset)
var __usersMapData = [];

var __usersMap, __usersMarkers = {}, __usersInfoWindow;
var __currentFilter = 'all';

function activityIcon(activity) {
    var c = ACTIVITY_COLORS[activity] || ACTIVITY_COLORS.inactive;
    return {
        path: google.maps.SymbolPath.CIRCLE,
        scale: c.scale,
        fillColor: c.fill,
        fillOpacity: 1,
        strokeColor: c.stroke,
        strokeWeight: 2.5,
    };
}

function markerVisible(u) {
    return __currentFilter === 'all' || u.activity === __currentFilter;
}

function addOrUpdateMarker(u) {
    var pos = { lat: u.lat, lng: u.lng };
    if (__usersMarkers[u.id]) {
        __usersMarkers[u.id].setPosition(pos);
        __usersMarkers[u.id].setIcon(activityIcon(u.activity));
        __usersMarkers[u.id].__data = u;
        __usersMarkers[u.id].setVisible(markerVisible(u));
    } else {
        var marker = new google.maps.Marker({
            position: pos,
            map: __usersMap,
            title: u.name,
            icon: activityIcon(u.activity),
            visible: markerVisible(u),
        });
        marker.__data = u;
        marker.addListener('click', function() {
            var d = this.__data;
            var actLabel = ACTIVITY_LABELS[d.activity] || d.activity;
            var badgeClass = 'act-' + d.activity;
            __usersInfoWindow.setContent(
                '<div class="gm-popup">' +
                '<div class="name">' + d.name + '</div>' +
                '<div class="phone">' + d.phone + '</div>' +
                '<div class="act-badge ' + badgeClass + '">● ' + actLabel + '</div>' +
                '<div class="ts">📍 GPS · Updated: ' + d.updated + '</div>' +
                '<a href="' + d.url + '">View Profile</a>' +
                '</div>'
            );
            __usersInfoWindow.open(__usersMap, this);
        });
        __usersMarkers[u.id] = marker;
    }
}

function mapFilter(filter) {
    __currentFilter = filter;
    // Update chip styles
    ['filterAll','filterDaily','filterSemi','filterInactive'].forEach(function(id) {
        document.getElementById(id).classList.remove('active');
    });
    var idMap = { all:'filterAll', daily:'filterDaily', semi:'filterSemi', inactive:'filterInactive' };
    if (idMap[filter]) document.getElementById(idMap[filter]).classList.add('active');

    // Show/hide markers and re-fit bounds
    var bounds = new google.maps.LatLngBounds();
    var visible = 0;
    Object.values(__usersMarkers).forEach(function(m) {
        var show = filter === 'all' || m.__data.activity === filter;
        m.setVisible(show);
        if (show) { bounds.extend(m.getPosition()); visible++; }
    });
    if (visible > 0) {
        if (visible === 1) {
            var pos = Object.values(__usersMarkers).find(function(m){ return m.getVisible(); }).getPosition();
            __usersMap.setCenter(pos);
            __usersMap.setZoom(15);
        } else {
            __usersMap.fitBounds(bounds);
        }
    }
}

function initUsersMap() {
    var defaultCenter = { lat: 2.0469, lng: 45.3182 };
    __usersMap = new google.maps.Map(document.getElementById('users-map'), {
        zoom: 12,
        center: defaultCenter,
        mapTypeControl: true,
        mapTypeControlOptions: {
            style: google.maps.MapTypeControlStyle.HORIZONTAL_BAR,
            position: google.maps.ControlPosition.TOP_RIGHT,
            mapTypeIds: ['roadmap', 'satellite', 'hybrid'],
        },
        streetViewControl: false,
        fullscreenControl: true,
        styles: [{ featureType: 'poi', stylers: [{ visibility: 'off' }] }]
    });

    __usersInfoWindow = new google.maps.InfoWindow();

    // Load ALL GPS users immediately (not paginated) + refresh every 30s
    function loadAllMarkers() {
        fetch('{{ route("admin.users.live-locations") }}')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                __usersMapData = data;
                var bounds = new google.maps.LatLngBounds();
                var count = 0;
                data.forEach(function(u) {
                    addOrUpdateMarker(u);
                    if (markerVisible(u)) { bounds.extend({ lat: u.lat, lng: u.lng }); count++; }
                });
                if (count > 0) {
                    if (count === 1) {
                        var vis = Object.values(__usersMarkers).find(function(m){ return m.getVisible(); });
                        if (vis) { __usersMap.setCenter(vis.getPosition()); __usersMap.setZoom(15); }
                    } else {
                        __usersMap.fitBounds(bounds);
                    }
                }
            })
            .catch(function() {});
    }

    loadAllMarkers();
    setInterval(loadAllMarkers, 30000);
}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC1pxwcaFZxDXwqDpxK_gDfPAdpFM8bTnc&callback=initUsersMap" async defer></script>

@endsection
