@extends('admin.layouts.app')
@section('title', 'eGrocery Settings')
@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-gear" style="color:#6366f1"></i> Settings</h2>
        <ol class="breadcrumb"><li><a href="{{ route('admin.module-data.egrocery.index') }}">eGrocery</a></li><li>Settings</li></ol>
    </div>
</div>

@include('admin.egrocery._subnav')

@if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger mb-4">{{ $errors->first() }}</div>@endif

{{-- ═══════════════════════════════════════ DELIVERY ZONES ═══════════════════════════════════════ --}}
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <div class="card-header-title"><i class="fas fa-map-location-dot" style="color:#3b82f6"></i> Delivery Zones</div>
        <button onclick="openZoneModal()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Zone</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Zone Name</th>
                    <th>Districts</th>
                    <th style="text-align:right;">Delivery Fee</th>
                    <th style="text-align:right;">Min Order</th>
                    <th style="text-align:right;">Free Delivery Over</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($zones as $z)
                <tr>
                    <td style="font-weight:700;">{{ $z->name }}</td>
                    <td>
                        @php
                            $distNames = collect($z->district_ids ?? [])->map(fn($id) => $districts->firstWhere('id', $id)?->name ?? $id)->filter()->implode(', ');
                        @endphp
                        <span style="font-size:12px;color:#64748b;">{{ $distNames ?: '—' }}</span>
                    </td>
                    <td style="text-align:right;font-weight:700;">${{ number_format($z->delivery_fee, 2) }}</td>
                    <td style="text-align:right;color:#64748b;">${{ number_format($z->min_order, 2) }}</td>
                    <td style="text-align:right;color:#64748b;">{{ $z->free_over ? '$'.number_format($z->free_over, 2) : '—' }}</td>
                    <td>
                        @if($z->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <button onclick="openZoneModal({{ $z->toJson() }})" class="btn btn-sm" style="background:#eff6ff;color:#2563eb;" title="Edit"><i class="fas fa-pen"></i></button>
                            <form method="POST" action="{{ route('admin.module-data.egrocery.zone.destroy', $z->id) }}" onsubmit="return confirm('Delete this zone?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="background:#fef2f2;color:#dc2626;" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:32px;">No delivery zones yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ═══════════════════════════════════════ DELIVERY SLOTS ═══════════════════════════════════════ --}}
<div class="card">
    <div class="card-header">
        <div class="card-header-title"><i class="fas fa-clock" style="color:#10b981"></i> Delivery Time Slots</div>
        <button onclick="openSlotModal()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Slot</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Time</th>
                    <th style="text-align:center;">Capacity</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                    $slotsByOffset = $slots->groupBy('day_offset');
                @endphp
                @forelse($slots->sortBy('day_offset') as $sl)
                <tr>
                    <td style="font-weight:700;">
                        {{ $dayNames[$sl->day_offset] ?? 'Day '.$sl->day_offset }}
                    </td>
                    <td>
                        <span style="background:#f1f5f9;padding:4px 10px;border-radius:6px;font-size:13px;font-weight:600;">
                            {{ $sl->label }} · {{ \Carbon\Carbon::parse($sl->start_time)->format('H:i') }} — {{ \Carbon\Carbon::parse($sl->end_time)->format('H:i') }}
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge badge-info">{{ $sl->capacity }} orders</span>
                    </td>
                    <td>
                        @if($sl->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <button onclick="openSlotModal({{ $sl->toJson() }})" class="btn btn-sm" style="background:#eff6ff;color:#2563eb;" title="Edit"><i class="fas fa-pen"></i></button>
                            <form method="POST" action="{{ route('admin.module-data.egrocery.slot.destroy', $sl->id) }}" onsubmit="return confirm('Delete this slot?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="background:#fef2f2;color:#dc2626;" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:32px;">No delivery slots yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ═══════════════════════════ ZONE MODAL ═══════════════════════════ --}}
<div id="zoneModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:520px;max-height:90vh;overflow-y:auto;box-shadow:0 25px 60px rgba(0,0,0,.25);">
        <div style="padding:20px;border-bottom:1px solid #f1f5f9;font-weight:800;font-size:15px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:#fff;z-index:1;">
            <span id="zoneModalTitle"><i class="fas fa-map-location-dot" style="color:#3b82f6;margin-right:8px;"></i>Add Delivery Zone</span>
            <button onclick="closeZoneModal()" style="background:none;border:none;cursor:pointer;font-size:18px;color:#9ca3af;">&times;</button>
        </div>
        <form id="zoneForm" method="POST" style="padding:20px;display:flex;flex-direction:column;gap:16px;">
            @csrf
            <input type="hidden" name="_method" id="zoneMethod" value="POST">
            <div>
                <label class="form-label">Zone Name <span style="color:red">*</span></label>
                <input type="text" name="name" id="zoneName" class="form-control" required placeholder="e.g. Mogadishu Central">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label class="form-label">Delivery Fee ($) <span style="color:red">*</span></label>
                    <input type="number" name="delivery_fee" id="zoneDeliveryFee" class="form-control" step="0.01" min="0" required placeholder="2.50">
                </div>
                <div>
                    <label class="form-label">Minimum Order ($)</label>
                    <input type="number" name="min_order" id="zoneMinOrder" class="form-control" step="0.01" min="0" value="0" placeholder="5.00">
                </div>
            </div>
            <div>
                <label class="form-label">Free Delivery Over ($) <span style="color:#9ca3af;font-weight:400;">(leave blank to disable)</span></label>
                <input type="number" name="free_over" id="zoneFreeOver" class="form-control" step="0.01" min="0" placeholder="30.00">
            </div>
            <div>
                <label class="form-label">Districts</label>
                <div id="districtCheckboxes" style="display:grid;grid-template-columns:1fr 1fr;gap:6px;max-height:200px;overflow-y:auto;border:1px solid #e8edf5;border-radius:10px;padding:12px;">
                    @foreach($districts as $d)
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;padding:4px;">
                        <input type="checkbox" name="district_ids[]" value="{{ $d->id }}" class="district-cb" style="width:15px;height:15px;">
                        {{ $d->name }}
                    </label>
                    @endforeach
                    @if($districts->isEmpty())
                    <span style="color:#9ca3af;font-size:13px;grid-column:1/-1;">No districts configured.</span>
                    @endif
                </div>
            </div>
            <div>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                    <input type="checkbox" name="is_active" id="zoneActive" value="1" checked style="width:16px;height:16px;">
                    <span class="form-label" style="margin:0;">Active</span>
                </label>
            </div>
            <div style="display:flex;gap:10px;padding-top:4px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Save Zone</button>
                <button type="button" onclick="closeZoneModal()" class="btn" style="background:#f1f5f9;color:#374151;">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════ SLOT MODAL ═══════════════════════════ --}}
<div id="slotModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:420px;box-shadow:0 25px 60px rgba(0,0,0,.25);">
        <div style="padding:20px;border-bottom:1px solid #f1f5f9;font-weight:800;font-size:15px;display:flex;justify-content:space-between;align-items:center;">
            <span id="slotModalTitle"><i class="fas fa-clock" style="color:#10b981;margin-right:8px;"></i>Add Delivery Slot</span>
            <button onclick="closeSlotModal()" style="background:none;border:none;cursor:pointer;font-size:18px;color:#9ca3af;">&times;</button>
        </div>
        <form id="slotForm" method="POST" style="padding:20px;display:flex;flex-direction:column;gap:16px;">
            @csrf
            <input type="hidden" name="_method" id="slotMethod" value="POST">
            <div>
                <label class="form-label">Label <span style="color:red">*</span></label>
                <input type="text" name="label" id="slotLabel" class="form-control" required placeholder="Morning, Afternoon, Evening…">
            </div>
            <div>
                <label class="form-label">Day of Week <span style="color:red">*</span></label>
                <select name="day_offset" id="slotDay" class="form-control" required>
                    @foreach(['0'=>'Sunday','1'=>'Monday','2'=>'Tuesday','3'=>'Wednesday','4'=>'Thursday','5'=>'Friday','6'=>'Saturday'] as $offset => $dayName)
                    <option value="{{ $offset }}">{{ $dayName }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label class="form-label">Start Time <span style="color:red">*</span></label>
                    <input type="time" name="start_time" id="slotStart" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">End Time <span style="color:red">*</span></label>
                    <input type="time" name="end_time" id="slotEnd" class="form-control" required>
                </div>
            </div>
            <div>
                <label class="form-label">Max Orders (Capacity) <span style="color:red">*</span></label>
                <input type="number" name="capacity" id="slotCapacity" class="form-control" min="1" required placeholder="20">
            </div>
            <div>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                    <input type="checkbox" name="is_active" id="slotActive" value="1" checked style="width:16px;height:16px;">
                    <span class="form-label" style="margin:0;">Active</span>
                </label>
            </div>
            <div style="display:flex;gap:10px;padding-top:4px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Save Slot</button>
                <button type="button" onclick="closeSlotModal()" class="btn" style="background:#f1f5f9;color:#374151;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
const zoneStoreUrl = '{{ route("admin.module-data.egrocery.zone.store") }}';
const slotStoreUrl = '{{ route("admin.module-data.egrocery.slot.store") }}';

function openZoneModal(zone) {
    const form = document.getElementById('zoneForm');
    if (zone) {
        document.getElementById('zoneModalTitle').innerHTML = '<i class="fas fa-pen" style="color:#3b82f6;margin-right:8px;"></i>Edit Zone';
        form.action = zoneStoreUrl.replace('/store', '/' + zone.id);
        document.getElementById('zoneMethod').value = 'PUT';
        document.getElementById('zoneName').value = zone.name;
        document.getElementById('zoneDeliveryFee').value = zone.delivery_fee;
        document.getElementById('zoneMinOrder').value = zone.min_order;
        document.getElementById('zoneFreeOver').value = zone.free_over || '';
        document.getElementById('zoneActive').checked = !!zone.is_active;
        const ids = zone.district_ids || [];
        document.querySelectorAll('.district-cb').forEach(cb => cb.checked = ids.includes(parseInt(cb.value)));
    } else {
        document.getElementById('zoneModalTitle').innerHTML = '<i class="fas fa-map-location-dot" style="color:#3b82f6;margin-right:8px;"></i>Add Zone';
        form.action = zoneStoreUrl;
        document.getElementById('zoneMethod').value = 'POST';
        form.reset();
        document.getElementById('zoneActive').checked = true;
        document.querySelectorAll('.district-cb').forEach(cb => cb.checked = false);
    }
    document.getElementById('zoneModal').style.display = 'flex';
}
function closeZoneModal() { document.getElementById('zoneModal').style.display = 'none'; }

function openSlotModal(slot) {
    const form = document.getElementById('slotForm');
    if (slot) {
        document.getElementById('slotModalTitle').innerHTML = '<i class="fas fa-pen" style="color:#10b981;margin-right:8px;"></i>Edit Slot';
        form.action = slotStoreUrl.replace('/store', '/' + slot.id);
        document.getElementById('slotMethod').value = 'PUT';
        document.getElementById('slotLabel').value = slot.label || '';
        document.getElementById('slotDay').value = slot.day_offset ?? 1;
        document.getElementById('slotStart').value = slot.start_time ? slot.start_time.substring(0,5) : '';
        document.getElementById('slotEnd').value = slot.end_time ? slot.end_time.substring(0,5) : '';
        document.getElementById('slotCapacity').value = slot.capacity;
        document.getElementById('slotActive').checked = !!slot.is_active;
    } else {
        document.getElementById('slotModalTitle').innerHTML = '<i class="fas fa-clock" style="color:#10b981;margin-right:8px;"></i>Add Slot';
        form.action = slotStoreUrl;
        document.getElementById('slotMethod').value = 'POST';
        form.reset();
        document.getElementById('slotActive').checked = true;
    }
    document.getElementById('slotModal').style.display = 'flex';
}
function closeSlotModal() { document.getElementById('slotModal').style.display = 'none'; }
</script>
@endsection
