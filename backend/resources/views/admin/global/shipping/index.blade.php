@extends('admin.layouts.app')
@section('title', 'Shipping Management')

@section('content')
<div class="page-header">
    <div><h1 class="page-title">🚚 Shipping Management</h1><p class="page-subtitle">Zones, rates, and fulfillment tracking</p></div>
</div>

@if(session('success'))<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>@endif

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px">
    @foreach([['Pending Shipment',$stats['pending'],'#f59e0b','fa-box'],['In Transit',$stats['shipped'],'#3b82f6','fa-truck'],['Delivered',$stats['delivered'],'#10b981','fa-check-circle'],['Zones',$stats['zones'],'#6366f1','fa-map']] as [$l,$v,$c,$i])
    <div style="background:#fff;border-radius:10px;padding:16px;border:1px solid #e5e7eb;display:flex;align-items:center;gap:12px">
        <div style="width:38px;height:38px;border-radius:9px;background:{{ $c }}18;display:flex;align-items:center;justify-content:center"><i class="fas {{ $i }}" style="color:{{ $c }}"></i></div>
        <div><div style="font-size:20px;font-weight:800;color:#111">{{ $v }}</div><div style="font-size:11px;color:#6b7280">{{ $l }}</div></div>
    </div>
    @endforeach
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

{{-- Pending Shipment --}}
<div style="background:#fff;border-radius:12px;border:1px solid #fde68a;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #fde68a;background:#fffbeb;display:flex;justify-content:space-between">
        <h3 style="font-size:14px;font-weight:700;color:#92400e">📦 Awaiting Shipment ({{ $pendingShipment->count() }})</h3>
    </div>
    @forelse($pendingShipment as $o)
    <div style="padding:12px 18px;border-top:1px solid #f3f4f6">
        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
            <a href="{{ route('admin.global.orders.show',$o) }}" style="font-size:12px;font-weight:700;color:#6366f1;text-decoration:none">{{ $o->order_number }}</a>
            <span style="font-size:12px;font-weight:700;color:#111">${{ number_format($o->total,2) }}</span>
        </div>
        <div style="font-size:12px;color:#374151">{{ $o->user?->name ?? $o->shipping_name }} · {{ $o->shipping_city }}, {{ $o->shipping_country }}</div>
        <div style="font-size:11px;color:#9ca3af">{{ $o->created_at->format('M d, Y') }} · {{ $o->items->count() }} item(s)</div>
    </div>
    @empty
    <div style="padding:24px;text-align:center;color:#9ca3af;font-size:13px">No orders pending shipment</div>
    @endforelse
</div>

{{-- In Transit --}}
<div style="background:#fff;border-radius:12px;border:1px solid #bfdbfe;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #bfdbfe;background:#eff6ff">
        <h3 style="font-size:14px;font-weight:700;color:#1d4ed8">🚛 In Transit ({{ $inTransit->count() }})</h3>
    </div>
    @forelse($inTransit as $o)
    <div style="padding:12px 18px;border-top:1px solid #f3f4f6">
        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
            <a href="{{ route('admin.global.orders.show',$o) }}" style="font-size:12px;font-weight:700;color:#6366f1;text-decoration:none">{{ $o->order_number }}</a>
            @if($o->tracking_url)<a href="{{ $o->tracking_url }}" target="_blank" style="font-size:11px;color:#3b82f6;text-decoration:none">Track →</a>@endif
        </div>
        <div style="font-size:12px;color:#374151">{{ $o->user?->name ?? $o->shipping_name }} · {{ $o->shipping_country }}</div>
        @if($o->tracking_number)<div style="font-size:11px;color:#9ca3af;font-family:monospace">{{ $o->shipping_carrier }}: {{ $o->tracking_number }}</div>@endif
        <div style="font-size:11px;color:#9ca3af">Shipped {{ $o->shipped_at ? \Carbon\Carbon::parse($o->shipped_at)->diffForHumans() : '—' }}</div>
    </div>
    @empty
    <div style="padding:24px;text-align:center;color:#9ca3af;font-size:13px">No orders in transit</div>
    @endforelse
</div>

</div>

{{-- Shipping Zones --}}
<div style="margin-top:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <h2 style="font-size:16px;font-weight:700;color:#111">🗺️ Shipping Zones</h2>
        <button onclick="document.getElementById('newZoneForm').classList.toggle('hidden')" style="padding:8px 16px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer">+ Add Zone</button>
    </div>

    {{-- Add Zone Form --}}
    <div id="newZoneForm" class="hidden" style="background:#fff;border-radius:12px;border:2px solid #6366f1;padding:20px;margin-bottom:16px">
        <h3 style="font-size:13px;font-weight:700;color:#6366f1;margin-bottom:14px">New Shipping Zone</h3>
        <form method="POST" action="{{ route('admin.global.shipping.zones.store') }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px">
                <div><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Zone Name</label><input name="name" required placeholder="Europe" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"></div>
                <div><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Countries (ISO, comma or *)</label><input name="countries" required placeholder="DE,FR,IT,ES" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"></div>
                <div><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Flat Rate ($)</label><input name="flat_rate" type="number" step="0.01" required style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"></div>
                <div><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Free over ($)</label><input name="free_shipping_over" type="number" step="0.01" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"></div>
                <div><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Min days</label><input name="estimated_days_min" type="number" value="3" required style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"></div>
                <div><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Max days</label><input name="estimated_days_max" type="number" value="7" required style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"></div>
            </div>
            <button type="submit" style="padding:9px 20px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">Create Zone</button>
            <button type="button" onclick="document.getElementById('newZoneForm').classList.add('hidden')" style="padding:9px 16px;background:#f3f4f6;color:#374151;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;margin-left:8px">Cancel</button>
        </form>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px">
        @foreach($zones as $zone)
        <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
            <div style="padding:14px 18px;background:#f9fafb;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center">
                <h3 style="font-size:14px;font-weight:700;color:#111">{{ $zone->name }}</h3>
                <div style="display:flex;gap:8px;align-items:center">
                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;{{ $zone->is_active ? 'color:#065f46;background:#d1fae5' : 'color:#991b1b;background:#fee2e2' }}">{{ $zone->is_active ? 'ACTIVE' : 'INACTIVE' }}</span>
                    <form method="POST" action="{{ route('admin.global.shipping.zones.destroy', $zone) }}" onsubmit="return confirm('Delete this zone?')">
                        @csrf @method('DELETE')
                        <button type="submit" style="padding:3px 8px;background:#fee2e2;color:#dc2626;border:none;border-radius:5px;font-size:10px;font-weight:700;cursor:pointer">✕</button>
                    </form>
                </div>
            </div>
            <div style="padding:14px 18px;font-size:12px;color:#374151">
                <div style="margin-bottom:6px"><strong>Countries:</strong> {{ is_array($zone->countries) ? implode(', ',$zone->countries) : $zone->countries }}</div>
                <div style="margin-bottom:6px"><strong>Rate:</strong> ${{ $zone->flat_rate }} · Free over ${{ $zone->free_shipping_over ?: '∞' }}</div>
                <div><strong>Delivery:</strong> {{ $zone->estimated_days_min }}–{{ $zone->estimated_days_max }} days</div>
            </div>
        </div>
        @endforeach
    </div>
</div>

<style>.hidden{display:none!important}</style>
@endsection
