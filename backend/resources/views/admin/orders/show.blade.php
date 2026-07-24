@extends('admin.layouts.app')
@section('title', 'Order #' . $order->order_number)

@push('styles')
<style>
/* ── Reset & Base ─────────────────────────────── */
.od-wrap { display:grid; grid-template-columns:1fr 340px; gap:20px; align-items:start; }
@media(max-width:960px){ .od-wrap{ grid-template-columns:1fr; } }

/* ── Module Hero Banners ──────────────────────── */
.mod-hero { border-radius:14px; overflow:hidden; margin-bottom:20px; box-shadow:0 4px 20px rgba(0,0,0,.10); }

/* Boarding pass */
.bp-card { background:linear-gradient(135deg,#07003B 0%,#1a0e6e 60%,#0d2f8a 100%); color:#fff; padding:28px 28px 0; }
.bp-route { display:flex; align-items:center; gap:0; justify-content:space-between; margin-bottom:20px; }
.bp-city { text-align:center; }
.bp-code { font-size:44px; font-weight:900; letter-spacing:-1px; }
.bp-name { font-size:12px; opacity:.7; margin-top:2px; }
.bp-mid  { flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; padding:0 16px; }
.bp-plane-line { width:100%; display:flex; align-items:center; gap:0; }
.bp-line { flex:1; height:1px; background:rgba(255,255,255,.3); }
.bp-plane-icon { font-size:20px; color:#FF8A00; transform:rotate(90deg); }
.bp-times { display:flex; justify-content:space-between; background:rgba(255,255,255,.07); border-radius:10px; padding:12px 16px; margin-bottom:0; }
.bp-time-block { text-align:center; }
.bp-time-val { font-size:22px; font-weight:800; }
.bp-time-lbl { font-size:10px; opacity:.6; text-transform:uppercase; letter-spacing:.5px; margin-top:2px; }
.bp-tear { height:20px; background:linear-gradient(135deg,#07003B 0%,#1a0e6e 60%,#0d2f8a 100%); position:relative; margin-top:16px; }
.bp-tear::before { content:''; position:absolute; bottom:0; left:-10px; right:-10px; height:20px;
    background:radial-gradient(circle at 0 100%, transparent 12px, white 13px) 0 0 / 26px 20px,
               radial-gradient(circle at 100% 100%, transparent 12px, white 13px) 100% 0 / 26px 20px;
    background-repeat:repeat-x; }
.bp-bottom { background:#fff; padding:16px 28px 20px; }
.bp-pax-list { display:flex; flex-wrap:wrap; gap:8px; }
.bp-pax-item { background:#F0F4FF; border-radius:8px; padding:8px 12px; font-size:13px; }
.bp-pax-name { font-weight:700; color:#07003B; }
.bp-pax-meta { font-size:11px; color:#888; margin-top:2px; }
.bp-badges { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
.bp-badge { background:#EEF2FF; color:#4338CA; border-radius:20px; padding:4px 12px; font-size:12px; font-weight:700; }
.bp-badge.orange { background:#FFF3E0; color:#E65100; }

/* Moving card */
.mv-card { background:linear-gradient(135deg,#0d47a1,#1565c0); color:#fff; padding:24px; }
.mv-route { display:flex; align-items:center; gap:12px; margin-bottom:20px; }
.mv-loc { flex:1; }
.mv-loc-label { font-size:10px; opacity:.7; text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px; }
.mv-loc-val { font-size:18px; font-weight:800; }
.mv-arrow { font-size:24px; color:#FF8A00; }
.mv-details-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
.mv-detail-item { background:rgba(255,255,255,.1); border-radius:10px; padding:12px; text-align:center; }
.mv-detail-val { font-size:16px; font-weight:800; }
.mv-detail-lbl { font-size:10px; opacity:.7; margin-top:4px; }
.mv-bottom { background:#fff; padding:16px 24px; }
.mv-extra { display:flex; flex-wrap:wrap; gap:6px; }
.mv-extra span { background:#E3F2FD; color:#1565C0; border-radius:6px; padding:4px 10px; font-size:12px; font-weight:600; }

/* eData card */
.dt-card { background:linear-gradient(135deg,#1B5E20,#2E7D32); color:#fff; padding:24px 28px; display:flex; align-items:center; gap:24px; }
.dt-icon-wrap { width:70px; height:70px; background:rgba(255,255,255,.15); border-radius:16px; display:flex; align-items:center; justify-content:center; font-size:32px; flex-shrink:0; }
.dt-info { flex:1; }
.dt-item-name { font-size:22px; font-weight:900; margin-bottom:4px; }
.dt-phone { font-size:14px; opacity:.8; }
.dt-badges { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
.dt-badge { background:rgba(255,255,255,.2); border-radius:20px; padding:4px 12px; font-size:12px; font-weight:700; }
.dt-price { text-align:right; }
.dt-price-val { font-size:32px; font-weight:900; color:#A5D6A7; }
.dt-price-lbl { font-size:11px; opacity:.7; margin-top:2px; }

/* eFood card */
.fd-card { background:linear-gradient(135deg,#BF360C,#E64A19); color:#fff; padding:20px 24px; }
.fd-vendor-row { display:flex; align-items:center; gap:14px; margin-bottom:16px; }
.fd-vendor-icon { width:48px; height:48px; background:rgba(255,255,255,.2); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:22px; }
.fd-vendor-name { font-size:18px; font-weight:800; }
.fd-vendor-sub { font-size:12px; opacity:.7; }
.fd-items-table { background:rgba(255,255,255,.08); border-radius:10px; overflow:hidden; }
.fd-item-row { display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border-bottom:1px solid rgba(255,255,255,.08); }
.fd-item-row:last-child { border-bottom:none; }
.fd-item-name { font-weight:600; font-size:14px; }
.fd-item-qty  { background:rgba(255,255,255,.2); border-radius:6px; padding:2px 8px; font-size:12px; font-weight:700; margin-left:8px; }
.fd-item-price { font-weight:800; font-size:14px; }

/* eShop card */
.sp-card { background:linear-gradient(135deg,#4A148C,#6A1B9A); color:#fff; padding:20px 24px; }

/* Generic items */
.items-modern { background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.07); margin-bottom:20px; }
.items-header { padding:16px 20px; border-bottom:1px solid #F0F1F5; display:flex; align-items:center; gap:10px; }
.items-header-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; }
.items-header-title { font-weight:700; font-size:15px; color:#1a1a2e; }
.items-table { width:100%; }
.items-table th { padding:10px 16px; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#999; background:#FAFBFF; font-weight:700; border-bottom:1px solid #F0F1F5; }
.items-table td { padding:12px 16px; border-bottom:1px solid #F8F9FC; font-size:14px; }
.items-table tr:last-child td { border-bottom:none; }
.items-total-row { padding:16px 20px; border-top:2px solid #F0F1F5; }
.items-total-table { margin-left:auto; width:260px; }
.items-total-table td { padding:5px 0; font-size:13px; color:#666; }
.items-total-table .total-row td { font-size:16px; font-weight:800; color:#FF8A00; padding-top:10px; border-top:2px solid #F0F1F5; }

/* ── Section Cards ─────────────────────────────── */
.od-card { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,.07); overflow:hidden; margin-bottom:20px; }
.od-card-header { padding:14px 20px; border-bottom:1px solid #F0F1F5; display:flex; align-items:center; gap:10px; }
.od-card-header .icon { width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:14px; color:#fff; flex-shrink:0; }
.od-card-header .title { font-weight:700; font-size:14px; color:#1a1a2e; }
.od-card-body { padding:20px; }

/* ── Order Info Table ──────────────────────────── */
.od-info-table { width:100%; }
.od-info-table tr td:first-child { padding:10px 16px; color:#888; font-size:13px; width:110px; font-weight:500; vertical-align:middle; }
.od-info-table tr td:last-child { padding:10px 16px; font-size:14px; vertical-align:middle; }
.od-info-table tr:nth-child(even) { background:#FAFBFF; }
.od-info-total { font-size:24px !important; font-weight:900 !important; color:#FF8A00 !important; }

/* ── Module Badge ──────────────────────────────── */
.mod-badge { display:inline-flex; align-items:center; gap:6px; border-radius:8px; padding:4px 10px; font-size:12px; font-weight:800; letter-spacing:.5px; }

/* ── Customer Card ─────────────────────────────── */
.cust-avatar { width:50px; height:50px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:900; flex-shrink:0; }

/* ── Status Timeline ───────────────────────────── */
.timeline { padding:8px 20px 4px; }
.tl-item { display:flex; gap:14px; padding-bottom:16px; position:relative; }
.tl-item:not(:last-child)::before { content:''; position:absolute; left:9px; top:20px; bottom:0; width:2px; background:#F0F1F5; }
.tl-dot { width:20px; height:20px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px; font-size:8px; color:#fff; }
.tl-content { flex:1; }
.tl-status { font-weight:700; font-size:13px; color:#1a1a2e; }
.tl-note { font-size:12px; color:#888; margin-top:2px; }
.tl-time { font-size:11px; color:#bbb; margin-top:3px; }

/* ── Update Status Form ────────────────────────── */
.status-form { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.status-form select, .status-form input { flex:1; min-width:120px; }
.status-form .btn { flex-shrink:0; }

/* ── Page Header ───────────────────────────────── */
.od-page-header { background:linear-gradient(135deg,#07003B 0%,#140066 100%); border-radius:16px; padding:24px 28px; margin-bottom:24px; color:#fff; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
.od-page-header-left h1 { font-size:24px; font-weight:900; margin:0 0 6px; }
.od-page-header-left .breadcrumb { margin:0; padding:0; background:transparent; }
.od-page-header-left .breadcrumb-item a { color:rgba(255,255,255,.6); font-size:13px; }
.od-page-header-left .breadcrumb-item.active { color:rgba(255,255,255,.9); font-size:13px; }
.od-page-header-left .breadcrumb-item+.breadcrumb-item::before { color:rgba(255,255,255,.4); }
.od-status-badge { display:flex; align-items:center; gap:8px; }

/* ── Status colors ─────────────────────────────── */
.s-pending        { background:#FFF3E0; color:#E65100; }
.s-confirmed      { background:#E3F2FD; color:#1565C0; }
.s-preparing      { background:#F3E5F5; color:#6A1B9A; }
.s-out_for_delivery{ background:#E0F2F1; color:#00695C; }
.s-delivered      { background:#E8F5E9; color:#2E7D32; }
.s-cancelled      { background:#FFEBEE; color:#C62828; }
.s-refunded       { background:#FFF8E1; color:#F57F17; }
.s-failed         { background:#FFEBEE; color:#C62828; }
.s-ready_for_pickup{ background:#E8EAF6; color:#3949AB; }

.pay-pending { background:#FFF3E0;color:#E65100; }
.pay-paid    { background:#E8F5E9;color:#2E7D32; }
.pay-unpaid  { background:#FFEBEE;color:#C62828; }
.pay-refunded{ background:#FFF8E1;color:#F57F17; }

/* ── Assign Deliveryman ────────────────────────── */
.assign-form { display:flex; gap:10px; }
.assign-form select { flex:1; }
</style>
@endpush

@php
/* ── Parse note JSON ── */
$note = [];
if ($order->note) {
    try { $note = is_string($order->note) ? json_decode($order->note, true) : (array)$order->note; } catch(\Throwable $e) {}
}

$slug = strtolower($order->module_slug ?? '');

/* ── Module meta ── */
$modConfigs = [
    'eticket'  => ['label'=>'eTicket',  'color'=>'#07003B','bg'=>'#EEF2FF','icon'=>'fa-plane',        'iconBg'=>'#3F51B5'],
    'emoving'  => ['label'=>'eMoving',  'color'=>'#0d47a1','bg'=>'#E3F2FD','icon'=>'fa-truck-moving', 'iconBg'=>'#1565C0'],
    'edata'    => ['label'=>'eData',    'color'=>'#1B5E20','bg'=>'#E8F5E9','icon'=>'fa-sim-card',     'iconBg'=>'#2E7D32'],
    'efood'    => ['label'=>'eFood',    'color'=>'#BF360C','bg'=>'#FBE9E7','icon'=>'fa-utensils',     'iconBg'=>'#E64A19'],
    'eshop'    => ['label'=>'eShop',    'color'=>'#4A148C','bg'=>'#F3E5F5','icon'=>'fa-shopping-bag', 'iconBg'=>'#6A1B9A'],
    'eparcel'  => ['label'=>'eParcel',  'color'=>'#E65100','bg'=>'#FFF3E0','icon'=>'fa-box',          'iconBg'=>'#FF8A00'],
    'ehealth'  => ['label'=>'eHealth',  'color'=>'#880E4F','bg'=>'#FCE4EC','icon'=>'fa-heartbeat',    'iconBg'=>'#AD1457'],
    'elaundry' => ['label'=>'eLaundry', 'color'=>'#006064','bg'=>'#E0F7FA','icon'=>'fa-tshirt',       'iconBg'=>'#00838F'],
    'erent'    => ['label'=>'eRent',    'color'=>'#33691E','bg'=>'#F1F8E9','icon'=>'fa-car',          'iconBg'=>'#558B2F'],
    'eexchange'=> ['label'=>'eExchange','color'=>'#4E342E','bg'=>'#EFEBE9','icon'=>'fa-exchange-alt', 'iconBg'=>'#6D4C41'],
    'egrocery' => ['label'=>'eGrocery', 'color'=>'#1A237E','bg'=>'#E8EAF6','icon'=>'fa-apple-alt',   'iconBg'=>'#3949AB'],
    'ewholesale'=>['label'=>'eWholesale','color'=>'#263238','bg'=>'#ECEFF1','icon'=>'fa-warehouse',   'iconBg'=>'#546E7A'],
];
$mod = $modConfigs[$slug] ?? ['label'=>strtoupper($slug),'color'=>'#546e7a','bg'=>'#ECEFF1','icon'=>'fa-circle','iconBg'=>'#546e7a'];

/* ── Status color map ── */
$scMap = [
    'pending'=>'#FF8A00','confirmed'=>'#1565C0','preparing'=>'#6A1B9A',
    'out_for_delivery'=>'#00695C','delivered'=>'#2E7D32','cancelled'=>'#C62828',
    'refunded'=>'#F57F17','failed'=>'#C62828','ready_for_pickup'=>'#3949AB',
];
$scColor = $scMap[$order->status] ?? '#546e7a';

/* ── eParcel ── */
$isParcel = $slug === 'eparcel';
$pickupDistrict = $deliveryDistrict = null;
if ($isParcel) {
    $pickupId = $note['pickup']['district_id'] ?? null;
    if ($pickupId) $pickupDistrict = \Illuminate\Support\Facades\DB::table('districts')->find($pickupId)?->name;
    $delivAddr = $order->delivery_address;
    if (is_string($delivAddr)) try { $delivAddr = json_decode($delivAddr, true); } catch(\Throwable $e) {}
    $delivId = $delivAddr['district_id'] ?? null;
    if ($delivId) $deliveryDistrict = \Illuminate\Support\Facades\DB::table('districts')->find($delivId)?->name;
}

/* ── Timezone helper ── */
$tz = \App\Helpers\AppSettings::timezone();
@endphp

@section('content')

{{-- ══════════════ PAGE HEADER ══════════════ --}}
<div class="od-page-header">
    <div class="od-page-header-left">
        <h1><i class="fas {{ $mod['icon'] }}" style="margin-right:10px;color:#FF8A00;"></i>Order #{{ $order->order_number }}</h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.orders.index') }}">Orders</a></li>
            <li class="breadcrumb-item active">#{{ $order->order_number }}</li>
        </ol>
    </div>
    <div class="od-status-badge">
        <span style="background:{{ $scColor }}22;color:{{ $scColor }};padding:8px 20px;border-radius:30px;font-weight:800;font-size:14px;border:2px solid {{ $scColor }}44;">
            <i class="fas fa-circle" style="font-size:8px;margin-right:6px;"></i>
            {{ ucfirst(str_replace('_',' ',$order->status)) }}
        </span>
    </div>
</div>

<div class="od-wrap">
{{-- ═══════════════════════ LEFT ═══════════════════════ --}}
<div>

{{-- ══════ ETICKET: Boarding Pass ══════ --}}
@if($slug === 'eticket')
<div class="mod-hero">
    <div class="bp-card">
        {{-- Badges row --}}
        <div class="bp-badges" style="margin-bottom:16px;">
            <span class="bp-badge"><i class="fas fa-plane" style="margin-right:4px;"></i>{{ $note['flight_number'] ?? '—' }}</span>
            <span class="bp-badge orange"><i class="fas fa-chair" style="margin-right:4px;"></i>{{ ucfirst($note['seat_class'] ?? 'Economy') }}</span>
            <span class="bp-badge"><i class="fas fa-users" style="margin-right:4px;"></i>{{ count($note['passengers'] ?? []) }} Pax</span>
            @if(!empty($note['airline']))<span class="bp-badge">{{ $note['airline'] }}</span>@endif
        </div>

        {{-- Route --}}
        <div class="bp-route">
            <div class="bp-city">
                <div class="bp-code">{{ $note['from_code'] ?? '—' }}</div>
                <div class="bp-name">{{ $note['from'] ?? '—' }}</div>
            </div>
            <div class="bp-mid">
                <div class="bp-plane-line">
                    <div class="bp-line"></div>
                    <i class="fas fa-plane bp-plane-icon"></i>
                    <div class="bp-line"></div>
                </div>
            </div>
            <div class="bp-city" style="text-align:right;">
                <div class="bp-code">{{ $note['to_code'] ?? '—' }}</div>
                <div class="bp-name">{{ $note['to'] ?? '—' }}</div>
            </div>
        </div>

        {{-- Times --}}
        <div class="bp-times" style="margin-bottom:20px;">
            <div class="bp-time-block">
                <div class="bp-time-val">{{ $note['departure'] ? \Carbon\Carbon::parse($note['departure'])->setTimezone($tz)->format('H:i') : '—' }}</div>
                <div class="bp-time-lbl">Departure</div>
                <div style="font-size:11px;opacity:.5;margin-top:2px;">{{ $note['departure'] ? \Carbon\Carbon::parse($note['departure'])->setTimezone($tz)->format('d M Y') : '' }}</div>
            </div>
            <div style="text-align:center;opacity:.5;">
                <i class="fas fa-clock" style="font-size:20px;"></i>
            </div>
            <div class="bp-time-block" style="text-align:right;">
                <div class="bp-time-val">{{ $note['arrival'] ? \Carbon\Carbon::parse($note['arrival'])->setTimezone($tz)->format('H:i') : '—' }}</div>
                <div class="bp-time-lbl">Arrival</div>
                <div style="font-size:11px;opacity:.5;margin-top:2px;">{{ $note['arrival'] ? \Carbon\Carbon::parse($note['arrival'])->setTimezone($tz)->format('d M Y') : '' }}</div>
            </div>
        </div>

        <div class="bp-tear"></div>
    </div>

    {{-- Passengers list --}}
    <div class="bp-bottom">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.6px;color:#888;font-weight:700;margin-bottom:10px;">Passengers</div>
        <div class="bp-pax-list">
            @forelse($note['passengers'] ?? [] as $pax)
            <div class="bp-pax-item">
                <div class="bp-pax-name">{{ $pax['title'] ?? '' }} {{ $pax['full_name'] ?? $pax['name'] ?? '—' }}</div>
                <div class="bp-pax-meta">
                    <span style="text-transform:capitalize;">{{ $pax['type'] ?? 'adult' }}</span>
                    @if(!empty($pax['passport_number'])) · {{ $pax['passport_number'] }}@endif
                    @if(!empty($pax['nationality'])) · {{ $pax['nationality'] }}@endif
                </div>
            </div>
            @empty
            <div style="color:#aaa;font-size:13px;">No passenger data</div>
            @endforelse
        </div>
        @if(!empty($note['price_per_pax']))
        <div style="margin-top:14px;padding-top:14px;border-top:1px dashed #E0E0E0;display:flex;justify-content:space-between;align-items:center;">
            <div style="font-size:13px;color:#888;">${{ number_format($note['price_per_pax'],2) }} × {{ count($note['passengers'] ?? []) }} pax</div>
            <div style="font-size:20px;font-weight:900;color:#FF8A00;">${{ number_format($order->total_amount,2) }}</div>
        </div>
        @endif
    </div>
</div>

{{-- ══════ EMOVING: Moving Card ══════ --}}
@elseif($slug === 'emoving')
<div class="mod-hero">
    <div class="mv-card">
        <div class="mv-route">
            <div class="mv-loc">
                <div class="mv-loc-label">📍 From</div>
                <div class="mv-loc-val">{{ $note['from_district'] ?? $note['pickup_address'] ?? '—' }}</div>
                @if(!empty($note['pickup_address']) && !empty($note['from_district']))
                <div style="font-size:12px;opacity:.6;margin-top:2px;">{{ $note['pickup_address'] }}</div>
                @endif
            </div>
            <div class="mv-arrow"><i class="fas fa-arrow-right"></i></div>
            <div class="mv-loc" style="text-align:right;">
                <div class="mv-loc-label">🎯 To</div>
                <div class="mv-loc-val">{{ $note['to_district'] ?? $note['delivery_address'] ?? '—' }}</div>
                @if(!empty($note['delivery_address']) && !empty($note['to_district']))
                <div style="font-size:12px;opacity:.6;margin-top:2px;">{{ $note['delivery_address'] }}</div>
                @endif
            </div>
        </div>
        <div class="mv-details-grid">
            <div class="mv-detail-item">
                <div class="mv-detail-val">{{ ucfirst($note['move_type'] ?? '—') }}</div>
                <div class="mv-detail-lbl">Move Type</div>
            </div>
            <div class="mv-detail-item">
                <div class="mv-detail-val">{{ $note['room_count'] ?? '—' }}</div>
                <div class="mv-detail-lbl">Rooms</div>
            </div>
            <div class="mv-detail-item">
                <div class="mv-detail-val">{{ $note['package'] ?? '—' }}</div>
                <div class="mv-detail-lbl">Package</div>
            </div>
        </div>
        @if(!empty($note['scheduled_date']))
        <div style="background:rgba(255,255,255,.1);border-radius:8px;padding:10px 14px;margin-top:12px;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-calendar-alt" style="color:#FF8A00;"></i>
            <div>
                <div style="font-size:10px;opacity:.6;text-transform:uppercase;letter-spacing:.5px;">Scheduled Date</div>
                <div style="font-weight:700;">{{ \Carbon\Carbon::parse($note['scheduled_date'])->format('d M Y') }}</div>
            </div>
        </div>
        @endif
    </div>
    @if(!empty($note['extra_services']))
    <div class="mv-bottom">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.6px;color:#888;font-weight:700;margin-bottom:8px;">Extra Services</div>
        <div class="mv-extra">
            @foreach(is_array($note['extra_services']) ? $note['extra_services'] : [$note['extra_services']] as $svc)
            <span><i class="fas fa-check-circle" style="margin-right:4px;color:#1565C0;"></i>{{ $svc }}</span>
            @endforeach
        </div>
    </div>
    @endif
    @if(!empty($note['user_note']))
    <div style="background:#FFF8F0;padding:14px 20px;border-top:1px solid #FFE0B2;">
        <div style="font-size:11px;text-transform:uppercase;color:#E65100;font-weight:700;margin-bottom:4px;"><i class="fas fa-comment-alt" style="margin-right:4px;"></i>Customer Note</div>
        <div style="font-size:13px;color:#555;">{{ $note['user_note'] }}</div>
    </div>
    @endif
    {{-- Price breakdown --}}
    <div style="background:#fff;padding:16px 20px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;color:#666;">
            <span>Base Price</span><span>${{ number_format($note['base_price'] ?? $order->subtotal, 2) }}</span>
        </div>
        @if(!empty($note['extra_cost']) && $note['extra_cost'] > 0)
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;color:#666;">
            <span>Extras</span><span>+${{ number_format($note['extra_cost'], 2) }}</span>
        </div>
        @endif
        <div style="display:flex;justify-content:space-between;padding-top:10px;border-top:2px solid #F0F1F5;font-size:18px;font-weight:900;color:#FF8A00;">
            <span>Total</span><span>${{ number_format($order->total_amount, 2) }}</span>
        </div>
    </div>
</div>

{{-- ══════ EDATA: Data Bundle Card ══════ --}}
@elseif($slug === 'edata')
<div class="mod-hero">
    <div class="dt-card">
        <div class="dt-icon-wrap">
            @if(strtolower($note['type'] ?? '') === 'airtime')
                📞
            @else
                📶
            @endif
        </div>
        <div class="dt-info">
            <div class="dt-item-name">{{ $note['item_name'] ?? 'Data Bundle' }}</div>
            <div class="dt-phone"><i class="fas fa-sim-card" style="margin-right:6px;"></i>{{ $note['phone_number'] ?? '—' }}</div>
            <div class="dt-badges">
                @if(!empty($note['type']))<span class="dt-badge">{{ ucfirst($note['type']) }}</span>@endif
                @if(!empty($note['data_amount']))<span class="dt-badge"><i class="fas fa-wifi" style="margin-right:4px;"></i>{{ $note['data_amount'] }}</span>@endif
                @if(!empty($note['validity_days']))<span class="dt-badge"><i class="fas fa-clock" style="margin-right:4px;"></i>{{ $note['validity_days'] }} days</span>@endif
            </div>
        </div>
        <div class="dt-price">
            <div class="dt-price-val">${{ number_format($order->total_amount, 2) }}</div>
            <div class="dt-price-lbl">Total Paid</div>
        </div>
    </div>
</div>

{{-- ══════ EFOOD: Food Order Card ══════ --}}
@elseif($slug === 'efood')
<div class="mod-hero">
    <div class="fd-card">
        @if($order->vendor)
        <div class="fd-vendor-row">
            <div class="fd-vendor-icon">🍽️</div>
            <div>
                <div class="fd-vendor-name">{{ $order->vendor->name }}</div>
                <div class="fd-vendor-sub">{{ $order->vendor->phone ?? 'Restaurant' }}</div>
            </div>
        </div>
        @endif
        <div class="fd-items-table">
            @forelse($order->items as $item)
            @php
                $meta = $item->meta ? (is_string($item->meta) ? json_decode($item->meta, true) : (array)$item->meta) : [];
            @endphp
            <div class="fd-item-row">
                <div>
                    <span class="fd-item-name">{{ $item->name ?? $item->product?->name ?? '—' }}</span>
                    <span class="fd-item-qty">×{{ $item->quantity }}</span>
                    @if(!empty($meta['variant_name']))
                    <div style="font-size:11px;opacity:.7;margin-top:2px;">{{ $meta['variant_name'] }}</div>
                    @endif
                </div>
                <div class="fd-item-price">${{ number_format($item->price * $item->quantity, 2) }}</div>
            </div>
            @empty
            <div style="padding:16px;text-align:center;opacity:.6;">No items</div>
            @endforelse
        </div>
    </div>
    <div style="background:#fff;padding:14px 20px;">
        @php
            $commission = (float)($order->commission ?? 0);
            $vendorEarn = (float)$order->subtotal - $commission;
        @endphp
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#666;margin-bottom:4px;"><span>Subtotal</span><span>${{ number_format($order->subtotal,2) }}</span></div>
        @if($order->delivery_fee > 0)
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#666;margin-bottom:4px;"><span>Delivery Fee</span><span>+${{ number_format($order->delivery_fee,2) }}</span></div>
        @endif
        @if(($order->discount_amount ?? 0) > 0)
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#c62828;margin-bottom:4px;"><span>Discount</span><span>-${{ number_format($order->discount_amount,2) }}</span></div>
        @endif
        <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:900;color:#FF8A00;padding-top:10px;border-top:2px solid #F0F1F5;margin-bottom:10px;"><span>Total (Customer Paid)</span><span>${{ number_format($order->total_amount,2) }}</span></div>

        <div style="background:#f8f9fa;border-radius:10px;padding:12px;margin-top:4px;">
            <div style="font-size:11px;font-weight:700;color:#8A8A9A;text-transform:uppercase;margin-bottom:8px;">Revenue Breakdown</div>
            <div style="display:flex;justify-content:space-between;font-size:13px;color:#1565C0;margin-bottom:4px;font-weight:600;"><span>Admin Commission</span><span>${{ number_format($commission, 2) }}</span></div>
            <div style="display:flex;justify-content:space-between;font-size:13px;color:#2e7d32;margin-bottom:4px;font-weight:600;"><span>Vendor Earning</span><span>${{ number_format($vendorEarn, 2) }}</span></div>
            <div style="display:flex;justify-content:space-between;font-size:13px;color:#666;"><span>Delivery Revenue</span><span>${{ number_format($order->delivery_fee, 2) }}</span></div>
        </div>
    </div>
</div>

{{-- ══════ EPARCEL ══════ --}}
@elseif($isParcel)
<div class="mod-hero" style="border:none;box-shadow:none;">
    <div style="background:linear-gradient(135deg,#E65100,#FF8A00);color:#fff;padding:24px 28px 0;border-radius:14px 14px 0 0;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <div>
                <div style="font-size:11px;opacity:.7;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Parcel Delivery</div>
                <div style="font-size:20px;font-weight:900;">{{ $pickupDistrict ?? '—' }} <span style="opacity:.5;font-size:14px;">→</span> {{ $deliveryDistrict ?? '—' }}</div>
            </div>
            <div style="font-size:28px;font-weight:900;color:#FFF3E0;">${{ number_format($order->total_amount,2) }}</div>
        </div>
        <div style="background:rgba(255,255,255,.1);border-radius:10px;overflow:hidden;margin-bottom:20px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;">
                <div style="padding:16px;border-right:1px solid rgba(255,255,255,.15);">
                    <div style="font-size:10px;opacity:.6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">📦 Sender</div>
                    <div style="font-weight:700;">{{ $note['pickup']['name'] ?? $order->user?->name ?? '—' }}</div>
                    <div style="font-size:12px;opacity:.7;margin-top:3px;">{{ $note['pickup']['phone'] ?? $order->user?->phone ?? '—' }}</div>
                    <div style="font-size:11px;opacity:.5;margin-top:2px;">{{ $pickupDistrict ?? '—' }}</div>
                </div>
                <div style="padding:16px;">
                    <div style="font-size:10px;opacity:.6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">🎯 Recipient</div>
                    <div style="font-weight:700;">{{ $note['recipient'] ?? '—' }}</div>
                    <div style="font-size:12px;opacity:.7;margin-top:3px;">{{ $note['recipient_phone'] ?? '—' }}</div>
                    <div style="font-size:11px;opacity:.5;margin-top:2px;">{{ $deliveryDistrict ?? '—' }}</div>
                </div>
            </div>
        </div>
        @if(!empty($note['description']))
        <div style="background:rgba(255,255,255,.1);border-radius:8px;padding:12px 16px;margin-bottom:20px;">
            <div style="font-size:10px;opacity:.6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">📝 Contents</div>
            <div style="font-size:13px;">{{ $note['description'] }}</div>
        </div>
        @endif
        <div style="height:16px;background:linear-gradient(135deg,#E65100,#FF8A00);position:relative;">
            <div style="position:absolute;bottom:0;left:-8px;right:-8px;height:16px;
                background:radial-gradient(circle at 0 100%,transparent 10px,white 11px) 0 0/24px 16px,
                           radial-gradient(circle at 100% 100%,transparent 10px,white 11px) 100% 0/24px 16px;
                background-repeat:repeat-x;"></div>
        </div>
    </div>
    <div style="background:#fff;padding:12px 20px;border-radius:0 0 14px 14px;border-top:none;"></div>
</div>

{{-- ══════ ERENT: Rental Property Card ══════ --}}
@elseif($slug === 'erent')
@php
    // ── Live booking data from DB (always up-to-date) ──
    $liveBooking = \Illuminate\Support\Facades\DB::table('property_bookings')
        ->where('order_id', $order->id)->first();

    $bookingType    = $liveBooking->booking_type  ?? ($note['booking_type']   ?? 'full_rent');
    $liveRemaining  = (float)($liveBooking->amount_remaining ?? $note['amount_remaining'] ?? 0);
    $livePaid       = (float)($liveBooking->amount_paid      ?? $note['amount_paid']      ?? $order->total_amount);
    $liveStatus     = $liveBooking->status ?? $order->status;
    $isFullyPaid    = $liveRemaining <= 0;

    // If was carbuun but remaining = 0 → show as fully completed
    $isCarbuun      = $bookingType === 'carbuun';
    $showAsFullPaid = $isCarbuun && $isFullyPaid;

    // Colors: green when fully paid, orange when carbuun pending
    $accentColor = $showAsFullPaid ? '#1B5E20' : ($isCarbuun ? '#FF8A00' : '#1B5E20');
    $accentLight = $showAsFullPaid ? '#F1F8E9'  : ($isCarbuun ? '#FFF3E0' : '#F1F8E9');
    $accentMid   = $showAsFullPaid ? '#2E7D32'  : ($isCarbuun ? '#E65100' : '#2E7D32');

    $monthlyRent = (float)($note['monthly_rent'] ?? 0);
    $totalValue  = (float)($note['total_value']  ?? 0);
    $deposit     = (float)($note['deposit']      ?? 0);
    $brokerage   = (float)($note['brokerage_fee'] ?? 0);
    $moveInDate  = $note['move_in_date'] ?? null;
@endphp
<div class="mod-hero">
    <div style="background:linear-gradient(135deg,{{ $accentColor }},{{ $accentMid }});color:#fff;padding:24px 28px 0;border-radius:14px 14px 0 0;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px;">
            <div>
                <div style="font-size:10px;opacity:.7;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;">
                    🏠 @if($showAsFullPaid) Full Rent — Fully Paid
                        @elseif($isCarbuun) Carbuun (30% Deposit)
                        @else Full Rent @endif
                </div>
                <div style="font-size:20px;font-weight:900;line-height:1.2;">{{ $note['property_title'] ?? '—' }}</div>
                @if(!empty($note['property_type']))
                <div style="font-size:12px;opacity:.75;margin-top:4px;text-transform:capitalize;">{{ $note['property_type'] }}</div>
                @endif
            </div>
            <div style="text-align:right;">
                <div style="font-size:28px;font-weight:900;">${{ number_format($livePaid, 2) }}</div>
                <div style="font-size:11px;opacity:.7;margin-top:2px;">
                    {{ $showAsFullPaid ? 'Total Paid (100%)' : 'Amount Paid' }}
                </div>
            </div>
        </div>

        {{-- Stats row --}}
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;background:rgba(255,255,255,.12);border-radius:12px;overflow:hidden;margin-bottom:20px;">
            <div style="padding:14px 16px;border-right:1px solid rgba(255,255,255,.15);">
                <div style="font-size:10px;opacity:.6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">📅 Move-in</div>
                <div style="font-size:14px;font-weight:800;">{{ $moveInDate ? \Carbon\Carbon::parse($moveInDate)->format('d M Y') : '—' }}</div>
            </div>
            <div style="padding:14px 16px;border-right:1px solid rgba(255,255,255,.15);">
                <div style="font-size:10px;opacity:.6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">💰 Monthly Rent</div>
                <div style="font-size:14px;font-weight:800;">${{ number_format($monthlyRent, 2) }}</div>
            </div>
            <div style="padding:14px 16px;">
                <div style="font-size:10px;opacity:.6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">🏦 Total Value</div>
                <div style="font-size:14px;font-weight:800;">${{ number_format($totalValue, 2) }}</div>
            </div>
        </div>

        {{-- Payment status bar --}}
        @if($isCarbuun)
        <div style="background:rgba(255,255,255,.15);border-radius:10px;padding:12px 16px;margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <div>
                    <div style="font-size:11px;opacity:.7;margin-bottom:3px;">
                        {{ $showAsFullPaid ? 'Full Payment Complete' : 'Remaining Balance (70%)' }}
                    </div>
                    @if($showAsFullPaid)
                    <div style="font-size:18px;font-weight:900;">$0.00</div>
                    @else
                    <div style="font-size:18px;font-weight:900;">${{ number_format($liveRemaining, 2) }}</div>
                    @endif
                </div>
                <div style="background:{{ $showAsFullPaid ? 'rgba(76,175,80,.4)' : 'rgba(255,255,255,.2)' }};border-radius:8px;padding:6px 14px;font-size:13px;font-weight:800;">
                    {{ $showAsFullPaid ? '✅ Fully Paid' : '⏳ Deposit Only' }}
                </div>
            </div>
            {{-- Progress bar --}}
            @php
                $paidPct = $totalValue > 0 ? min(100, round($livePaid / $totalValue * 100)) : ($showAsFullPaid ? 100 : 30);
            @endphp
            <div style="background:rgba(255,255,255,.2);border-radius:20px;height:8px;overflow:hidden;">
                <div style="background:#fff;height:100%;border-radius:20px;width:{{ $paidPct }}%;transition:width .4s;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;opacity:.7;margin-top:5px;">
                <span>{{ $paidPct }}% paid</span>
                <span>${{ number_format($livePaid, 2) }} / ${{ number_format($totalValue, 2) }}</span>
            </div>
        </div>
        @endif

        {{-- Tear line --}}
        <div style="height:18px;background:linear-gradient(135deg,{{ $accentColor }},{{ $accentMid }});position:relative;">
            <div style="position:absolute;bottom:0;left:-8px;right:-8px;height:18px;
                background:radial-gradient(circle at 0 100%,transparent 10px,white 11px) 0 0/24px 18px,
                           radial-gradient(circle at 100% 100%,transparent 10px,white 11px) 100% 0/24px 18px;
                background-repeat:repeat-x;"></div>
        </div>
    </div>

    {{-- Bottom section --}}
    <div style="background:#fff;padding:14px 20px 18px;border-radius:0 0 14px 14px;">
        <div style="display:flex;justify-content:space-between;font-size:12px;color:#666;margin-bottom:6px;">
            <span>🔑 Deposit</span>
            <span style="font-weight:700;">${{ number_format($deposit, 2) }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:#666;margin-bottom:6px;">
            <span>🤝 Brokerage Fee</span>
            <span style="font-weight:700;">${{ number_format($brokerage, 2) }}</span>
        </div>
        @if(!empty($note['note']))
        <div style="margin-top:10px;padding:10px 14px;background:#F9F9F9;border-radius:8px;font-size:12px;color:#555;border-left:3px solid {{ $accentColor }};">
            <span style="font-weight:700;color:{{ $accentColor }};">📝 Note:</span> {{ $note['note'] }}
        </div>
        @endif
        <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid #F0F0F5;padding-top:10px;">
            <span style="font-size:13px;color:#888;">Booking Status</span>
            <span style="font-size:13px;font-weight:800;color:{{ $accentColor }};background:{{ $accentLight }};padding:3px 12px;border-radius:20px;">
                @if($showAsFullPaid) ✅ Full Rent — Complete
                @elseif($isCarbuun) 🕐 Carbuun — Awaiting Balance
                @else ✅ Full Rent @endif
            </span>
        </div>
    </div>
</div>

{{-- ══════ ESHOP / Default: Items Table ══════ --}}
@else
<div class="items-modern">
    <div class="items-header">
        <div class="items-header-icon" style="background:{{ $mod['iconBg'] }}22;">
            <i class="fas {{ $mod['icon'] }}" style="color:{{ $mod['iconBg'] }};"></i>
        </div>
        <div class="items-header-title">Order Items</div>
    </div>
    <table class="items-table" style="width:100%;">
        <thead>
            <tr><th>Product</th><th>Variant</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr>
        </thead>
        <tbody>
            @forelse($order->items as $item)
            @php
                $meta = $item->meta ? (is_string($item->meta) ? json_decode($item->meta, true) : (array)$item->meta) : [];
            @endphp
            <tr>
                <td style="font-weight:600;color:#1a1a2e;">{{ $item->name ?? $item->product?->name ?? '—' }}</td>
                <td>
                    @if(!empty($meta['variant_name']))
                    <span style="background:#EEF2FF;color:#3949AB;padding:3px 9px;border-radius:6px;font-size:12px;font-weight:700;">{{ $meta['variant_name'] }}</span>
                    @else<span style="color:#ccc;">—</span>@endif
                </td>
                <td><span style="background:#F5F5F5;border-radius:6px;padding:2px 8px;font-weight:700;">{{ $item->quantity }}</span></td>
                <td>${{ number_format($item->price,2) }}</td>
                <td style="font-weight:700;color:#1a1a2e;">${{ number_format($item->price * $item->quantity,2) }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;padding:28px;color:#aaa;"><i class="fas fa-box-open" style="font-size:24px;display:block;margin-bottom:8px;"></i>No items</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="items-total-row">
        <table class="items-total-table">
            <tr><td>Subtotal</td><td style="text-align:right;">${{ number_format($order->subtotal,2) }}</td></tr>
            <tr><td>Delivery Fee</td><td style="text-align:right;">${{ number_format($order->delivery_fee,2) }}</td></tr>
            @if(($order->discount_amount ?? 0) > 0)
            <tr><td>Discount</td><td style="text-align:right;color:#c62828;">-${{ number_format($order->discount_amount,2) }}</td></tr>
            @endif
            <tr class="total-row"><td style="font-weight:800;">Total</td><td style="text-align:right;font-weight:900;font-size:18px;color:#FF8A00;">${{ number_format($order->total_amount,2) }}</td></tr>
        </table>
    </div>
</div>
@endif

{{-- ── Update Status ── --}}
<div class="od-card">
    <div class="od-card-header">
        <div class="icon" style="background:#FF8A00;"><i class="fas fa-edit"></i></div>
        <div class="title">Update Order Status</div>
    </div>
    <div class="od-card-body">
        <form action="{{ route('admin.orders.status', $order->id) }}" method="POST" class="status-form">
            @csrf @method('PATCH')
            <select name="status" class="form-control" style="max-width:200px;">
                @foreach(['pending','confirmed','preparing','ready_for_pickup','out_for_delivery','delivered','cancelled','refunded','failed'] as $s)
                <option value="{{ $s }}" {{ $order->status === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                @endforeach
            </select>
            <input type="text" name="note" class="form-control" placeholder="Optional note..." style="flex:1;">
            <button type="submit" class="btn btn-primary" style="white-space:nowrap;"><i class="fas fa-save" style="margin-right:6px;"></i>Update</button>
        </form>
    </div>
</div>

{{-- ── Assign Deliveryman ── --}}
@if(in_array($order->status, ['confirmed','preparing','ready_for_pickup']) && !$order->deliveryman_id)
<div class="od-card">
    <div class="od-card-header">
        <div class="icon" style="background:#00695C;"><i class="fas fa-motorcycle"></i></div>
        <div class="title">Assign Deliveryman</div>
    </div>
    <div class="od-card-body">
        <form action="{{ route('admin.orders.assign', $order->id) }}" method="POST" class="assign-form">
            @csrf
            <select name="deliveryman_id" class="form-control">
                <option value="">— Select Deliveryman —</option>
                @foreach($deliverymen as $dm)
                <option value="{{ $dm->id }}">{{ $dm->user?->name }} ({{ ucfirst($dm->vehicle_type) }})</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-success"><i class="fas fa-check" style="margin-right:6px;"></i>Assign</button>
        </form>
    </div>
</div>
@endif

</div>{{-- /left --}}

{{-- ═══════════════════════ RIGHT ═══════════════════════ --}}
<div>

{{-- ── Order Info ── --}}
<div class="od-card">
    <div class="od-card-header">
        <div class="icon" style="background:{{ $mod['iconBg'] }};"><i class="fas fa-receipt"></i></div>
        <div class="title">Order Info</div>
    </div>
    <table class="od-info-table">
        <tr>
            <td>Order #</td>
            <td><span style="font-weight:800;font-size:14px;color:#1a1a2e;letter-spacing:.5px;">{{ $order->order_number }}</span></td>
        </tr>
        <tr>
            <td>Module</td>
            <td>
                <span class="mod-badge" style="background:{{ $mod['bg'] }};color:{{ $mod['color'] }};">
                    <i class="fas {{ $mod['icon'] }}" style="font-size:11px;"></i>
                    {{ $mod['label'] }}
                </span>
            </td>
        </tr>
        <tr>
            <td>Status</td>
            <td>
                <span style="background:{{ $scColor }}18;color:{{ $scColor }};padding:3px 10px;border-radius:6px;font-weight:700;font-size:12px;">
                    {{ ucfirst(str_replace('_',' ',$order->status)) }}
                </span>
            </td>
        </tr>
        <tr>
            <td>Payment</td>
            <td>
                <span style="text-transform:capitalize;font-size:13px;">{{ ucfirst($order->payment_method ?? '—') }}</span>
                @php $ps = strtolower($order->payment_status ?? 'pending'); @endphp
                <span class="pay-{{ $ps }}" style="margin-left:6px;padding:2px 8px;border-radius:5px;font-size:11px;font-weight:800;">{{ strtoupper($ps) }}</span>
            </td>
        </tr>
        @if($order->payment_method === 'mobile_pay')
        @php
            $proofToken = data_get($order->meta, 'proof_token');
            $proof = $proofToken ? \Illuminate\Support\Facades\Cache::get('mobile_pay_proof:' . $proofToken) : null;
        @endphp
        <tr>
            <td>Mobile Pay Proof</td>
            <td>
                @if($proof)
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <div style="font-size:13px;">
                            <i class="fas fa-phone" style="color:#2E7D32;margin-right:6px;"></i>
                            <strong>{{ $proof['phone'] }}</strong>
                        </div>
                        <div style="font-size:11px;color:#888;">Amount: ${{ number_format($proof['amount'],2) }} &nbsp;·&nbsp; {{ \Carbon\Carbon::parse($proof['created_at'])->setTimezone($tz)->format('d M Y · H:i') }}</div>
                        <a href="{{ $proof['image_url'] }}" target="_blank" style="display:inline-block;">
                            <img src="{{ $proof['image_url'] }}" alt="Payment Proof"
                                 style="max-width:220px;max-height:300px;border-radius:10px;border:2px solid #4CAF50;cursor:zoom-in;object-fit:cover;">
                        </a>
                        <div style="font-size:11px;color:#4CAF50;"><i class="fas fa-check-circle"></i> Proof submitted</div>
                    </div>
                @else
                    <span style="color:#888;font-size:12px;">No proof submitted yet</span>
                @endif
            </td>
        </tr>
        @endif
        <tr>
            <td>Placed At</td>
            <td style="font-size:13px;">{{ ($order->placed_at ?? $order->created_at)?->setTimezone($tz)->format('d M Y · H:i') }}</td>
        </tr>
        @if($order->delivered_at)
        <tr>
            <td>Delivered</td>
            <td style="font-size:13px;color:#2E7D32;font-weight:600;"><i class="fas fa-check-circle" style="margin-right:4px;"></i>{{ $order->delivered_at->setTimezone($tz)->format('d M Y · H:i') }}</td>
        </tr>
        @endif
        <tr>
            <td>Total</td>
            <td><span class="od-info-total">${{ number_format($order->total_amount,2) }}</span></td>
        </tr>
    </table>
</div>

{{-- ── Customer ── --}}
<div class="od-card">
    <div class="od-card-header">
        <div class="icon" style="background:#3949AB;"><i class="fas fa-user"></i></div>
        <div class="title">Customer</div>
    </div>
    <div class="od-card-body">
        <div style="display:flex;align-items:center;gap:14px;">
            <div class="cust-avatar" style="background:#EEF2FF;color:#3949AB;">
                {{ strtoupper(substr($order->user?->name ?? 'U',0,1)) }}
            </div>
            <div>
                <div style="font-weight:800;font-size:15px;color:#1a1a2e;">{{ $order->user?->name ?? '—' }}</div>
                <div style="color:#888;font-size:13px;margin-top:3px;"><i class="fas fa-phone" style="font-size:10px;margin-right:5px;color:#888;"></i>{{ $order->user?->phone ?? '—' }}</div>
                @if($order->user?->email)
                <div style="color:#aaa;font-size:12px;margin-top:2px;"><i class="fas fa-envelope" style="font-size:10px;margin-right:5px;"></i>{{ $order->user->email }}</div>
                @endif
                @if($order->user)
                <a href="{{ route('admin.users.show', $order->user->id) }}" style="display:inline-block;margin-top:8px;font-size:12px;color:#3949AB;font-weight:700;"><i class="fas fa-eye" style="margin-right:4px;"></i>View Profile</a>
                @endif
            </div>
        </div>
        @php
            $custLat   = $order->user?->latitude  ?? $order->user?->district?->latitude;
            $custLng   = $order->user?->longitude ?? $order->user?->district?->longitude;
            $custGps   = (bool)$order->user?->latitude;
        @endphp
        @if($custLat && $custLng)
        <div style="margin-top:14px;border-top:1px solid #F0F1F5;padding-top:12px;">
            <div style="font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px;">
                <i class="fas fa-map-marker-alt" style="color:{{ $custGps ? '#FF8A00' : '#3949AB' }};margin-right:4px;"></i>
                @if($custGps) Customer Location
                @else District: {{ $order->user?->district?->name ?? '—' }}
                @endif
            </div>
            <div id="order-cust-map" style="height:200px;border-radius:12px;overflow:hidden;"></div>
            @if($custGps && $order->user->location_updated_at)
            <div style="font-size:11px;color:#bbb;text-align:center;margin-top:6px;"><i class="fas fa-clock" style="margin-right:3px;"></i>{{ $order->user->location_updated_at->diffForHumans() }}</div>
            @endif
        </div>
        @endif
    </div>
</div>

{{-- ── Vendor (if applicable) ── --}}
@if($order->vendor)
<div class="od-card">
    <div class="od-card-header">
        <div class="icon" style="background:#E64A19;"><i class="fas fa-store"></i></div>
        <div class="title">Vendor / Restaurant</div>
    </div>
    <div class="od-card-body">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:44px;height:44px;background:#FBE9E7;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;">🏪</div>
            <div>
                <div style="font-weight:800;font-size:15px;color:#1a1a2e;">{{ $order->vendor->name }}</div>
                @if($order->vendor->phone)<div style="color:#888;font-size:13px;margin-top:3px;"><i class="fas fa-phone" style="font-size:10px;margin-right:5px;"></i>{{ $order->vendor->phone }}</div>@endif
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Deliveryman ── --}}
@if($order->deliveryman)
<div class="od-card">
    <div class="od-card-header">
        <div class="icon" style="background:#00695C;"><i class="fas fa-motorcycle"></i></div>
        <div class="title">Deliveryman</div>
    </div>
    <div class="od-card-body">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:44px;height:44px;background:#E0F2F1;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:900;color:#00695C;">
                {{ strtoupper(substr($order->deliveryman->user?->name ?? 'D',0,1)) }}
            </div>
            <div style="flex:1;">
                <div style="font-weight:800;font-size:15px;color:#1a1a2e;">{{ $order->deliveryman->user?->name ?? '—' }}</div>
                <div style="margin-top:4px;display:flex;gap:8px;flex-wrap:wrap;">
                    @php $vEmoji = ['motorcycle'=>'🏍️','bajaj'=>'🛺','car'=>'🚗','van'=>'🚐','truck'=>'🚛','bicycle'=>'🚲'][$order->deliveryman->vehicle_type] ?? '🚗'; @endphp
                    <span style="background:#E0F2F1;color:#00695C;padding:2px 8px;border-radius:5px;font-size:12px;font-weight:700;">{{ $vEmoji }} {{ ucfirst($order->deliveryman->vehicle_type) }}</span>
                    @if($order->deliveryman->user?->phone)
                    <span style="background:#F5F5F5;color:#666;padding:2px 8px;border-radius:5px;font-size:12px;"><i class="fas fa-phone" style="font-size:9px;margin-right:3px;"></i>{{ $order->deliveryman->user->phone }}</span>
                    @endif
                </div>
            </div>
        </div>

        @if(in_array($order->status, ['confirmed','preparing','ready_for_pickup','out_for_delivery']))
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            {{-- Unassign --}}
            <form action="{{ route('admin.orders.unassign-driver', $order->id) }}" method="POST" style="margin:0;" onsubmit="return confirm('Remove driver from this order?')">
                @csrf
                <button class="btn btn-sm" style="background:#fff5f5;color:#ef4444;border:1.5px solid #fecaca;font-size:12px;">
                    <i class="fas fa-user-minus"></i> Remove Driver
                </button>
            </form>
            {{-- Reassign --}}
            <button class="btn btn-sm btn-primary" style="font-size:12px;" onclick="openModal('reassignModal')">
                <i class="fas fa-exchange-alt"></i> Change Driver
            </button>
        </div>
        @endif
    </div>
</div>
@endif

{{-- Reassign Modal --}}
@if($order->deliveryman && in_array($order->status, ['confirmed','preparing','ready_for_pickup','out_for_delivery']))
<div class="modal-overlay" id="reassignModal">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-header">
            <h3 class="modal-title">Reassign Driver</h3>
            <button class="modal-close" onclick="closeModal('reassignModal')">✕</button>
        </div>
        <form action="{{ route('admin.orders.reassign-driver', $order->id) }}" method="POST">
            @csrf
            <div class="modal-body">
                <div style="background:#fff3e0;border-radius:10px;padding:12px;margin-bottom:14px;font-size:12px;color:#e65100;">
                    <i class="fas fa-info-circle" style="margin-right:6px;"></i>
                    Current driver <strong>{{ $order->deliveryman->user?->name }}</strong> will be removed and made available again.
                </div>
                <div class="form-group">
                    <label class="form-label">Select New Driver</label>
                    <select name="deliveryman_id" class="form-control" required>
                        <option value="">— Choose driver —</option>
                        @foreach($deliverymen as $dm)
                        @if($dm->id !== $order->deliveryman_id)
                        @php $emoji = ['motorcycle'=>'🏍️','bajaj'=>'🛺','car'=>'🚗','van'=>'🚐','truck'=>'🚛','bicycle'=>'🚲'][$dm->vehicle_type] ?? '🚗'; @endphp
                        <option value="{{ $dm->id }}">{{ $emoji }} {{ $dm->user?->name }} — {{ $dm->user?->phone }} (★{{ number_format($dm->rating ?? 5, 1) }})</option>
                        @endif
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="padding:16px;border-top:1px solid #f0f1f5;display:flex;gap:10px;">
                <button type="button" onclick="closeModal('reassignModal')" style="flex:1;padding:10px;border:1.5px solid #e0e0e0;border-radius:10px;background:#fff;font-weight:600;cursor:pointer;">Cancel</button>
                <button type="submit" style="flex:1;padding:10px;border:none;border-radius:10px;background:#FF8A00;color:#fff;font-weight:700;cursor:pointer;">Reassign</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ── Status History Timeline ── --}}
<div class="od-card">
    <div class="od-card-header">
        <div class="icon" style="background:#5C6BC0;"><i class="fas fa-history"></i></div>
        <div class="title">Status Timeline</div>
    </div>
    @php
    $tlColors = ['pending'=>'#FF8A00','confirmed'=>'#1565C0','preparing'=>'#6A1B9A','out_for_delivery'=>'#00695C','delivered'=>'#2E7D32','cancelled'=>'#C62828','refunded'=>'#F57F17','failed'=>'#C62828','ready_for_pickup'=>'#3949AB'];
    $tlIcons  = ['pending'=>'fa-clock','confirmed'=>'fa-check','preparing'=>'fa-cog','out_for_delivery'=>'fa-truck','delivered'=>'fa-check-double','cancelled'=>'fa-times','refunded'=>'fa-undo','failed'=>'fa-exclamation','ready_for_pickup'=>'fa-box-check'];
    @endphp
    <div class="timeline">
        @forelse($order->statusHistory as $h)
        @php
            $hc = $tlColors[$h->status] ?? '#888';
            $hi = $tlIcons[$h->status] ?? 'fa-circle';
        @endphp
        <div class="tl-item">
            <div class="tl-dot" style="background:{{ $hc }};"><i class="fas {{ $hi }}" style="font-size:8px;"></i></div>
            <div class="tl-content">
                <div class="tl-status" style="color:{{ $hc }};">{{ ucfirst(str_replace('_',' ',$h->status)) }}</div>
                @if($h->note)<div class="tl-note">{{ $h->note }}</div>@endif
                <div class="tl-time">{{ $h->created_at?->setTimezone($tz)->format('d M Y · H:i') ?? '—' }}</div>
            </div>
        </div>
        @empty
        <div style="padding:20px;text-align:center;color:#ccc;"><i class="fas fa-history" style="font-size:24px;display:block;margin-bottom:8px;"></i>No history yet</div>
        @endforelse
    </div>
</div>

</div>{{-- /right --}}
</div>{{-- /od-wrap --}}

@if(isset($custLat) && $custLat)
<script>
function initOrderCustMap() {
    var hasGps = {{ $custGps ? 'true' : 'false' }};
    var pos    = { lat: {{ $custLat }}, lng: {{ $custLng }} };
    var map = new google.maps.Map(document.getElementById('order-cust-map'), {
        zoom: hasGps ? 15 : 13,
        center: pos,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false,
        zoomControl: true,
    });
    var marker = new google.maps.Marker({
        position: pos,
        map: map,
        title: '{{ addslashes($order->user?->name ?? '') }}',
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 10,
            fillColor: hasGps ? '#FF8A00' : '#3949AB',
            fillOpacity: 1,
            strokeColor: '#07003B',
            strokeWeight: 3,
        }
    });
    var label = hasGps
        ? '<strong>{{ addslashes($order->user?->name ?? '') }}</strong><br><span style="font-size:12px;color:#888;">{{ addslashes($order->user?->phone ?? '') }}</span>'
        : '<strong>{{ addslashes($order->user?->name ?? '') }}</strong><br><span style="font-size:12px;color:#3949AB;">District: {{ addslashes($order->user?->district?->name ?? '') }}</span>';
    var iw = new google.maps.InfoWindow({ content: '<div style="padding:4px 2px;">' + label + '</div>' });
    iw.open(map, marker);
}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA9J4TSypPZv3cr8Zlabn0BSDICD_Ibp-A&callback=initOrderCustMap" async defer></script>
@endif

@endsection
