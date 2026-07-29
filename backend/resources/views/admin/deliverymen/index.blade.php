@extends('admin.layouts.app')
@section('title', 'Deliverymen')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Deliverymen</h1>
        <ul class="breadcrumb"><li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li>Deliverymen</li></ul>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('admin.deliverymen.earnings') }}" class="btn btn-outline-success"><i class="fas fa-coins"></i> Earnings</a>
        <button class="btn btn-outline" onclick="openModal('settingsModal')"><i class="fas fa-cog"></i> Settings</button>
        <button class="btn btn-primary" onclick="openModal('createModal')"><i class="fas fa-plus"></i> Add Driver</button>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

{{-- Stats --}}
@php
    $all = $deliverymen->getCollection();
    $avail = $all->where('status','available')->count();
    $busy  = $all->where('status','busy')->count();
    $pend  = $all->where('is_approved',false)->count();
    $normal = $all->where('driver_type','normal')->count();
    $truck  = $all->where('driver_type','truck')->count();
@endphp
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:16px;">
    <div style="background:#fff;border-radius:12px;padding:14px 16px;border-left:4px solid #10B981;">
        <div style="font-size:22px;font-weight:900;">{{ $avail }}</div><div style="font-size:11px;color:#8A8A9A;">Available</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px 16px;border-left:4px solid #F59E0B;">
        <div style="font-size:22px;font-weight:900;">{{ $busy }}</div><div style="font-size:11px;color:#8A8A9A;">On Delivery</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px 16px;border-left:4px solid #FF8A00;">
        <div style="font-size:22px;font-weight:900;">{{ $pend }}</div><div style="font-size:11px;color:#8A8A9A;">Pending</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px 16px;border-left:4px solid #3B82F6;">
        <div style="font-size:22px;font-weight:900;">{{ $normal }}</div><div style="font-size:11px;color:#8A8A9A;">🏍️ Normal</div>
    </div>
    <div style="background:#fff;border-radius:12px;padding:14px 16px;border-left:4px solid #8B5CF6;">
        <div style="font-size:22px;font-weight:900;">{{ $truck }}</div><div style="font-size:11px;color:#8A8A9A;">🚛 Truck</div>
    </div>
</div>

{{-- Filters --}}
<div class="card" style="padding:12px 16px;margin-bottom:12px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <input type="text" name="search" class="form-control" style="width:200px;" value="{{ request('search') }}" placeholder="Search name or phone...">
        <select name="status" class="form-control" style="width:140px;" onchange="this.form.submit()">
            <option value="">All Status</option>
            @foreach(['pending','available','busy','offline','banned'] as $st)
            <option value="{{ $st }}" {{ request('status')===$st?'selected':'' }}>{{ ucfirst($st) }}</option>
            @endforeach
        </select>
        <select name="driver_type" class="form-control" style="width:140px;" onchange="this.form.submit()">
            <option value="">All Types</option>
            <option value="normal" {{ request('driver_type')==='normal'?'selected':'' }}>🏍️ Normal</option>
            <option value="truck" {{ request('driver_type')==='truck'?'selected':'' }}>🚛 Truck</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
    </form>
</div>

{{-- Table --}}
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Driver</th>
                    <th>Phone</th>
                    <th>Type</th>
                    <th>Vehicle</th>
                    <th>Status</th>
                    <th>Docs</th>
                    <th>Rating</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deliverymen as $dm)
                @php
                    $sc = ['available'=>'badge-success','busy'=>'badge-warning','offline'=>'badge-secondary','pending'=>'badge-warning','banned'=>'badge-danger'][$dm->status] ?? 'badge-secondary';
                    $vehicleEmoji = ['motorcycle'=>'🏍️','bajaj'=>'🛺','car'=>'🚗','van'=>'🚐','truck'=>'🚛','bicycle'=>'🚲','pickup'=>'🚛'][$dm->vehicle_type] ?? '🚗';
                @endphp
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="avatar avatar-sm avatar-green">{{ strtoupper(substr($dm->user->name??'D',0,1)) }}</div>
                            <div>
                                <span style="font-weight:700;font-size:13px;">{{ $dm->user->name ?? 'N/A' }}</span>
                                @if(!$dm->is_approved)
                                <span style="display:block;font-size:10px;color:#FF8A00;font-weight:600;">⏳ Pending Approval</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td style="font-size:13px;color:#8A8A9A;">{{ $dm->user->phone ?? '—' }}</td>
                    <td>
                        @if($dm->driver_type === 'truck')
                        <span class="badge" style="background:#f3e8ff;color:#7c3aed;">🚛 Truck</span>
                        @else
                        <span class="badge" style="background:#e0f2fe;color:#0284c7;">🏍️ Normal</span>
                        @endif
                    </td>
                    <td><span class="badge badge-info">{{ $vehicleEmoji }} {{ ucfirst($dm->vehicle_type ?? '—') }}</span></td>
                    <td><span class="badge {{ $sc }}">{{ ucfirst($dm->status) }}</span></td>
                    <td>
                        <a href="{{ route('admin.deliverymen.show', $dm->id) }}" style="font-size:11px;color:#3B82F6;font-weight:600;">
                            📄 View
                        </a>
                    </td>
                    <td>
                        <span style="color:#f59e0b;">★</span>
                        <span style="font-weight:700;">{{ number_format($dm->rating ?? 5, 1) }}</span>
                    </td>
                    <td style="font-size:12px;color:#8A8A9A;">{{ $dm->created_at->format('d M Y') }}</td>
                    <td>
                        <div style="display:flex;gap:4px;flex-wrap:wrap;">
                            @if(!$dm->is_approved)
                            <form action="{{ route('admin.deliverymen.approve', $dm->id) }}" method="POST" style="margin:0;">
                                @csrf
                                <button class="btn btn-xs btn-success"><i class="fas fa-check"></i></button>
                            </form>
                            <form action="{{ route('admin.deliverymen.reject', $dm->id) }}" method="POST" style="margin:0;">
                                @csrf
                                <button class="btn btn-xs btn-warning"><i class="fas fa-times"></i></button>
                            </form>
                            @endif
                            <button class="btn btn-xs btn-primary" title="Change Password"
                                onclick="openPasswordModal({{ $dm->id }}, '{{ addslashes($dm->user->name) }}')">
                                <i class="fas fa-key"></i>
                            </button>
                            <form action="{{ route('admin.deliverymen.toggle-block', $dm->id) }}" method="POST" style="margin:0;">
                                @csrf
                                <button class="btn btn-xs {{ $dm->user->status === 'banned' ? 'btn-outline' : 'btn-danger' }}" title="{{ $dm->user->status === 'banned' ? 'Unblock' : 'Block' }}">
                                    <i class="fas {{ $dm->user->status === 'banned' ? 'fa-unlock' : 'fa-ban' }}"></i>
                                </button>
                            </form>
                            <form action="{{ route('admin.deliverymen.destroy', $dm->id) }}" method="POST" style="margin:0;" onsubmit="return confirm('Delete this driver permanently?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-xs btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9"><div class="empty-state"><i class="fas fa-motorcycle"></i><h3>No drivers found</h3></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($deliverymen->hasPages())
    <div style="padding:16px;display:flex;justify-content:center;">{{ $deliverymen->withQueryString()->links() }}</div>
    @endif
</div>

{{-- Create Driver Modal --}}
<div class="modal-overlay" id="createModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Add New Driver</h3>
            <button class="modal-close" onclick="closeModal('createModal')">✕</button>
        </div>
        <form action="{{ route('admin.deliverymen.store') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="Driver name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone *</label>
                        <input type="text" name="phone" class="form-control" required placeholder="+252...">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="text" name="password" class="form-control" required placeholder="Min 4 characters">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Plate Number</label>
                        <input type="text" name="plate_number" class="form-control" placeholder="MG-1234">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Driver Type *</label>
                        <select name="driver_type" class="form-control" required>
                            <option value="normal">🏍️ Normal Delivery</option>
                            <option value="truck">🚛 Truck / Moving</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Vehicle Type *</label>
                        <select name="vehicle_type" class="form-control" required>
                            <option value="motorcycle">🏍️ Motorcycle</option>
                            <option value="bajaj">🛺 Bajaj</option>
                            <option value="car">🚗 Car</option>
                            <option value="truck">🚛 Truck</option>
                            <option value="van">🚐 Van</option>
                            <option value="bicycle">🚲 Bicycle</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px">
                        <input type="checkbox" name="auto_approve" value="1"> Auto-approve this driver
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Driver</button>
            </div>
        </form>
    </div>
</div>
{{-- Settings Modal --}}
<div class="modal-overlay" id="settingsModal">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-cog" style="color:#FF8A00;margin-right:8px;"></i> Delivery Settings</h3>
            <button class="modal-close" onclick="closeModal('settingsModal')">✕</button>
        </div>
        <form action="{{ route('admin.deliverymen.settings') }}" method="POST">
            @csrf
            <div class="modal-body" style="padding:0;">

                @php
                    $currentMax    = (int) \App\Helpers\AppSettings::get('max_orders_per_driver', 5);
                    $currentRadius = (float) \App\Helpers\AppSettings::get('driver_notification_radius_km', 2);
                @endphp

                {{-- ── Section: Notification Radius ───────────────────────── --}}
                <div style="padding:20px 24px 0;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                        <div style="width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#FF8A00,#ffb347);display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-broadcast-tower" style="color:#fff;font-size:15px;"></i>
                        </div>
                        <div>
                            <div style="font-weight:800;font-size:14px;color:#07003B;">Order Notification Radius</div>
                            <div style="font-size:11px;color:#8A8A9A;">Drivers within this distance from pickup receive alerts</div>
                        </div>
                    </div>

                    {{-- Visual radius map indicator --}}
                    <div style="position:relative;background:#f0f6ff;border-radius:14px;padding:18px 16px 12px;margin:12px 0 16px;border:1px solid #dbeafe;text-align:center;">
                        <div style="display:flex;justify-content:center;align-items:center;gap:0;margin-bottom:10px;" id="radiusVisual">
                            {{-- rings rendered by JS --}}
                        </div>
                        <div style="font-size:12px;color:#3B82F6;font-weight:700;" id="radiusLabel">
                            Radius: <span id="radiusDisplay">{{ $currentRadius }}</span> km
                        </div>
                        <div style="font-size:11px;color:#8A8A9A;margin-top:2px;" id="radiusDesc"></div>
                    </div>

                    {{-- Slider --}}
                    <div style="margin-bottom:16px;">
                        <input type="range" id="radiusSlider" name="driver_notification_radius_km"
                            min="0.5" max="20" step="0.5" value="{{ $currentRadius }}"
                            style="width:100%;accent-color:#FF8A00;cursor:pointer;height:6px;">
                        <div style="display:flex;justify-content:space-between;font-size:10px;color:#8A8A9A;margin-top:4px;">
                            <span>0.5 km</span><span>5 km</span><span>10 km</span><span>15 km</span><span>20 km</span>
                        </div>
                    </div>

                    {{-- Quick presets --}}
                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:4px;">
                        @foreach([1 => '1 km — Very tight', 2 => '2 km — Recommended', 5 => '5 km — Wide', 10 => '10 km — City-wide'] as $val => $label)
                        <button type="button" onclick="setRadius({{ $val }})"
                            class="radius-preset {{ $currentRadius == $val ? 'active' : '' }}"
                            data-val="{{ $val }}"
                            style="padding:6px 12px;border-radius:20px;font-size:11px;font-weight:700;cursor:pointer;border:1.5px solid {{ $currentRadius == $val ? '#FF8A00' : '#e5e7eb' }};
                                background:{{ $currentRadius == $val ? '#fff5e6' : '#fff' }};color:{{ $currentRadius == $val ? '#FF8A00' : '#6b7280' }};">
                            {{ $label }}
                        </button>
                        @endforeach
                    </div>
                </div>

                <div style="height:1px;background:#f3f4f6;margin:20px 0;"></div>

                {{-- ── Section: Max Orders ─────────────────────────────────── --}}
                <div style="padding:0 24px 20px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                        <div style="width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#818cf8);display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-layer-group" style="color:#fff;font-size:14px;"></i>
                        </div>
                        <div>
                            <div style="font-weight:800;font-size:14px;color:#07003B;">Max Orders Per Driver</div>
                            <div style="font-size:11px;color:#8A8A9A;">Active orders one driver can hold simultaneously</div>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        @foreach([1, 3, 5, 10, 15, 20] as $v)
                        <label style="display:flex;align-items:center;gap:4px;padding:8px 16px;border-radius:10px;cursor:pointer;font-weight:800;font-size:14px;transition:all .15s;
                            {{ $currentMax == $v ? 'background:#6366f1;color:#fff;box-shadow:0 2px 8px rgba(99,102,241,.35);' : 'background:#f0f1f5;color:#07003B;' }}">
                            <input type="radio" name="max_orders_per_driver" value="{{ $v }}" {{ $currentMax == $v ? 'checked' : '' }} style="display:none;">
                            {{ $v }}
                        </label>
                        @endforeach
                    </div>
                    <div style="margin-top:12px;display:flex;align-items:center;gap:8px;">
                        <span style="font-size:12px;color:#8A8A9A;">Custom:</span>
                        <input type="number" name="max_orders_custom" class="form-control" min="1" max="50" placeholder="e.g. 7"
                            style="width:100px;height:34px;font-size:13px;padding:4px 10px;">
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="border-top:1px solid #f3f4f6;">
                <button type="button" class="btn btn-outline" onclick="closeModal('settingsModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
            </div>
        </form>

        <script>
        (function(){
            const slider = document.getElementById('radiusSlider');
            const display = document.getElementById('radiusDisplay');
            const desc = document.getElementById('radiusDesc');
            const visual = document.getElementById('radiusVisual');

            const descriptions = {
                0.5: 'Ultra-tight — only drivers at the door',
                1:   'Very tight — 1-2 blocks away',
                2:   'Recommended — nearby neighbourhood',
                3:   'Moderate — larger coverage area',
                5:   'Wide — covers most of a district',
                10:  'City-wide — large urban coverage',
                20:  'Region-wide — all drivers in city',
            };

            function getDesc(v) {
                const keys = Object.keys(descriptions).map(Number).sort((a,b)=>a-b);
                for (let k of keys) { if (v <= k) return descriptions[k]; }
                return 'Very wide coverage';
            }

            function renderRings(v) {
                const maxR = 20;
                const rings = 4;
                let html = '<div style="position:relative;width:120px;height:120px;">';
                for (let i = rings; i >= 1; i--) {
                    const pct = (i / rings);
                    const sz = 24 + pct * 88;
                    const active = v / maxR >= (i / rings) * 0.6;
                    html += `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
                        width:${sz}px;height:${sz}px;border-radius:50%;
                        border:2px solid ${active ? '#FF8A00' : '#cbd5e1'};
                        background:${active ? 'rgba(255,138,0,0.06)' : 'transparent'};
                        transition:all .3s;"></div>`;
                }
                // Center dot
                html += '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:14px;height:14px;border-radius:50%;background:#FF8A00;box-shadow:0 0 0 4px rgba(255,138,0,.25);"></div>';
                html += '</div>';
                visual.innerHTML = html;
            }

            function update(v) {
                display.textContent = v;
                desc.textContent = getDesc(parseFloat(v));
                renderRings(parseFloat(v));
                document.querySelectorAll('.radius-preset').forEach(btn => {
                    const bv = parseFloat(btn.dataset.val);
                    const active = Math.abs(bv - parseFloat(v)) < 0.01;
                    btn.style.borderColor = active ? '#FF8A00' : '#e5e7eb';
                    btn.style.background  = active ? '#fff5e6' : '#fff';
                    btn.style.color       = active ? '#FF8A00' : '#6b7280';
                });
            }

            window.setRadius = function(v) {
                slider.value = v;
                update(v);
            };

            slider.addEventListener('input', () => update(slider.value));
            update(slider.value);
        })();
        </script>
    </div>
</div>
{{-- Change Password Modal --}}
<div class="modal-overlay" id="passwordModal">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-key" style="color:#ff6b35;margin-right:8px;"></i> Change Password</h3>
            <button class="modal-close" onclick="closeModal('passwordModal')">✕</button>
        </div>
        <form id="passwordForm" method="POST" action="">
            @csrf
            <div class="modal-body">
                <p id="pwd-driver-name" style="font-size:13px;color:#6b7280;margin-bottom:16px;"></p>
                <div class="form-group">
                    <label class="form-label">New Password *</label>
                    <div style="position:relative;">
                        <input type="text" name="password" id="pwd-input" class="form-control" required
                               placeholder="Enter new password (min 4 chars)" minlength="4"
                               style="padding-right:40px;">
                        <button type="button" onclick="generatePwd()" title="Generate random password"
                                style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#ff6b35;font-size:14px;">
                            <i class="fas fa-dice"></i>
                        </button>
                    </div>
                    <p style="font-size:11px;color:#9ca3af;margin-top:4px;">Click 🎲 to auto-generate a strong password</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('passwordModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Password</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openPasswordModal(driverId, driverName) {
    document.getElementById('pwd-driver-name').textContent = 'Driver: ' + driverName;
    document.getElementById('passwordForm').action = '/admin/deliverymen/' + driverId + '/change-password';
    document.getElementById('pwd-input').value = '';
    openModal('passwordModal');
}

function generatePwd() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    var pwd = '';
    for (var i = 0; i < 8; i++) pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    document.getElementById('pwd-input').value = pwd;
}
</script>
@endpush

@endsection
