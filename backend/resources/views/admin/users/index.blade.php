@extends('admin.layouts.app')
@section('title', 'Users')

@push('styles')
<style>
#users-map { height: 420px; border-radius: 16px; overflow: hidden; }
.gm-popup { padding: 12px 16px; font-family: inherit; min-width: 180px; }
.gm-popup .name  { font-weight: 800; font-size: 14px; color: #07003B; }
.gm-popup .phone { font-size: 12px; color: #888; margin-top: 2px; }
.gm-popup .role  { display:inline-block; margin-top:6px; background:#EEF2FF; color:#3949AB; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; }
.gm-popup a      { display:block; margin-top:8px; text-align:center; background:#07003B; color:#fff; text-decoration:none; padding:5px 10px; border-radius:8px; font-size:12px; font-weight:700; }
.gm-popup .ts    { font-size:10px; color:#bbb; margin-top:4px; }
</style>
@endpush

@section('content')

@php
// Use GPS location if available, otherwise fall back to district center
$mappableUsers = $users->getCollection()->filter(function($u) {
    return ($u->latitude && $u->longitude) || ($u->district?->latitude && $u->district?->longitude);
});
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

{{-- ── Live User Map ─────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:#FF8A00;">
                <i class="fas fa-map-marked-alt"></i>
            </div>
            Live User Locations
            <span class="badge badge-warning" style="margin-left:4px;">{{ $mappableUsers->count() }} on map</span>
        </div>
    </div>
    <div style="padding:16px;">
        <div id="users-map"></div>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;align-items:center;">
            <div style="flex:1;min-width:200px;">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search name, phone, email…" value="{{ request('search') }}">
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
var __usersMapData = {{ Illuminate\Support\Js::from($mappableUsers->map(function($u) {
    $hasGps = $u->latitude && $u->longitude;
    return [
        'id'      => $u->id,
        'name'    => $u->name,
        'phone'   => $u->phone ?? '',
        'role'    => ucwords(str_replace('_', ' ', $u->role?->name ?? 'User')),
        'lat'     => (float) ($hasGps ? $u->latitude  : $u->district?->latitude),
        'lng'     => (float) ($hasGps ? $u->longitude : $u->district?->longitude),
        'url'     => route('admin.users.show', $u->id),
        'updated' => $hasGps ? (optional($u->location_updated_at)->diffForHumans() ?? 'Unknown') : ('District: ' . ($u->district?->name ?? '—')),
        'hasGps'  => $hasGps,
    ];
})->values()) }};

function initUsersMap() {
    var defaultCenter = { lat: 2.0469, lng: 45.3182 };
    var map = new google.maps.Map(document.getElementById('users-map'), {
        zoom: 12,
        center: defaultCenter,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true,
        styles: [
            { featureType: 'poi', stylers: [{ visibility: 'off' }] }
        ]
    });

    var infoWindow = new google.maps.InfoWindow();
    var bounds    = new google.maps.LatLngBounds();
    var hasPoints = false;

    __usersMapData.forEach(function(u) {
        var pos = { lat: u.lat, lng: u.lng };
        var marker = new google.maps.Marker({
            position: pos,
            map: map,
            title: u.name,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 10,
                fillColor: u.hasGps ? '#FF8A00' : '#3949AB',
                fillOpacity: 1,
                strokeColor: '#07003B',
                strokeWeight: 3,
            }
        });

        marker.addListener('click', function() {
            infoWindow.setContent(
                '<div class="gm-popup">' +
                '<div class="name">' + u.name + '</div>' +
                '<div class="phone">' + u.phone + '</div>' +
                '<span class="role">' + u.role + '</span>' +
                '<div class="ts">' + u.updated + '</div>' +
                '<a href="' + u.url + '">View Profile</a>' +
                '</div>'
            );
            infoWindow.open(map, marker);
        });

        bounds.extend(pos);
        hasPoints = true;
    });

    if (hasPoints) {
        if (__usersMapData.length === 1) {
            map.setCenter({ lat: __usersMapData[0].lat, lng: __usersMapData[0].lng });
            map.setZoom(15);
        } else {
            map.fitBounds(bounds);
        }
    }
}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA9J4TSypPZv3cr8Zlabn0BSDICD_Ibp-A&callback=initUsersMap" async defer></script>

@endsection
