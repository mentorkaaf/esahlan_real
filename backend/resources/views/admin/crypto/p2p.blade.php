@extends('admin.layouts.app')
@section('title', 'P2P Marketplace')

@push('styles')
<style>
.cx{background:#F4F6FB;min-height:100vh;padding:24px 28px}
.cx-card{background:#fff;border-radius:16px;border:1px solid #EEF0F6;overflow:hidden;margin-bottom:20px}
.cx-hdr{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #F1F5F9}
.cx-title{font-size:14px;font-weight:800;color:#0F172A}
.cx-table{width:100%;border-collapse:collapse;font-size:13px}
.cx-table th{padding:9px 16px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#94A3B8;background:#F8FAFC;border-bottom:1px solid #F1F5F9;text-align:left}
.cx-table td{padding:11px 16px;border-bottom:1px solid #F8FAFC;vertical-align:middle}
.pill{padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;display:inline-block}
.pill-buy{background:#DCFCE7;color:#15803D}
.pill-sell{background:#FEE2E2;color:#B91C1C}
.pill-pending{background:#FEF3C7;color:#B45309}
.pill-paid{background:#DBEAFE;color:#1D4ED8}
.pill-completed{background:#DCFCE7;color:#15803D}
.pill-cancelled{background:#F1F5F9;color:#64748B}
.pill-disputed{background:#FEE2E2;color:#B91C1C}
.pill-released{background:#DCFCE7;color:#15803D}
.filter-bar{background:#fff;border-radius:12px;border:1px solid #EEF0F6;padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.tab-btn{padding:7px 18px;border-radius:8px;border:1px solid #EEF0F6;background:#fff;font-size:12px;font-weight:700;cursor:pointer;color:#64748B;text-decoration:none;display:inline-block}
.tab-btn.active{background:#3B82F6;color:#fff;border-color:#3B82F6}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center}
.modal.open{display:flex}
.modal-box{background:#fff;border-radius:20px;padding:28px;width:520px;max-height:90vh;overflow-y:auto}
</style>
@endpush

@section('content')
<div class="cx">
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
  <div>
    <div style="font-size:12px;color:#94A3B8;margin-bottom:2px">Finance / Exchange</div>
    <h1 style="font-size:22px;font-weight:900;color:#0F172A;margin:0">P2P Marketplace</h1>
  </div>
  @if($disputeCount > 0)
  <div style="background:#FEE2E2;border:1px solid #FECACA;border-radius:10px;padding:8px 16px;font-size:13px;font-weight:700;color:#B91C1C">
    <i class="fas fa-exclamation-circle"></i> {{ $disputeCount }} open dispute(s)
  </div>
  @endif
</div>

@if(session('success'))<div class="alert alert-success mb-3">{{ session('success') }}</div>@endif

{{-- Tabs --}}
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <a href="{{ route('admin.crypto.p2p') }}" class="tab-btn {{ !request('tab')||request('tab')=='orders'?'active':'' }}">Orders</a>
  <a href="{{ route('admin.crypto.p2p',['tab'=>'ads']) }}" class="tab-btn {{ request('tab')=='ads'?'active':'' }}">Ads</a>
  <a href="{{ route('admin.crypto.p2p',['tab'=>'disputes']) }}" class="tab-btn {{ request('tab')=='disputes'?'active':'' }}">
    Disputes {{ $disputeCount>0?"({$disputeCount})":'' }}
  </a>
  <a href="{{ route('admin.crypto.p2p',['tab'=>'escrow']) }}" class="tab-btn {{ request('tab')=='escrow'?'active':'' }}">Escrow</a>
</div>

@php $tab = request('tab','orders'); @endphp

{{-- ORDERS TAB --}}
@if($tab === 'orders')
<form method="GET" class="filter-bar">
  <input type="hidden" name="tab" value="orders">
  <div>
    <label style="font-size:10px;font-weight:700;color:#64748B;display:block;margin-bottom:4px">STATUS</label>
    <select name="status" class="form-control form-control-sm">
      <option value="">All</option>
      @foreach(['pending','paid','completed','cancelled','disputed'] as $s)
      <option value="{{ $s }}" {{ request('status')==$s?'selected':'' }}>{{ ucfirst($s) }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label style="font-size:10px;font-weight:700;color:#64748B;display:block;margin-bottom:4px">SEARCH</label>
    <input type="text" name="q" value="{{ request('q') }}" placeholder="UUID / User" class="form-control form-control-sm" style="width:200px">
  </div>
  <button type="submit" class="btn btn-sm btn-primary" style="align-self:flex-end">Filter</button>
</form>

<div class="cx-card">
  <div style="overflow-x:auto">
    <table class="cx-table">
      <thead>
        <tr><th>UUID</th><th>Ad / Coin</th><th>Buyer</th><th>Seller</th>
          <th style="text-align:right">Qty</th><th style="text-align:right">Total (USD)</th>
          <th style="text-align:center">Status</th><th style="text-align:right">Date</th><th></th></tr>
      </thead>
      <tbody>
        @foreach($orders as $o)
        <tr>
          <td style="font-size:10px;font-family:monospace;color:#94A3B8">{{ substr($o->uuid,0,10) }}</td>
          <td style="font-weight:700">{{ $o->ad->coin->symbol??'-' }}<div style="font-size:10px;color:#94A3B8">{{ $o->ad->type??'' }} ad</div></td>
          <td>{{ $o->buyer->name??'-' }}</td>
          <td>{{ $o->seller->name??'-' }}</td>
          <td style="text-align:right;font-variant-numeric:tabular-nums">{{ number_format($o->crypto_amount??0,6) }}</td>
          <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700">${{ number_format($o->total_usd??0,2) }}</td>
          <td style="text-align:center"><span class="pill pill-{{ $o->status }}">{{ strtoupper($o->status) }}</span></td>
          <td style="text-align:right;font-size:11px;color:#94A3B8">{{ $o->created_at->format('M d H:i') }}</td>
          <td>
            @if($o->status === 'disputed')
            <div style="display:flex;gap:4px">
              <form method="POST" action="{{ route('admin.crypto.p2p.resolve',$o->id) }}" style="display:inline">
                @csrf @method('PATCH')
                <input type="hidden" name="winner" value="buyer">
                <button type="submit" class="btn btn-xs btn-primary" style="font-size:10px;padding:2px 8px" onclick="return confirm('Release to buyer?')">→ Buyer</button>
              </form>
              <form method="POST" action="{{ route('admin.crypto.p2p.resolve',$o->id) }}" style="display:inline">
                @csrf @method('PATCH')
                <input type="hidden" name="winner" value="seller">
                <button type="submit" class="btn btn-xs btn-warning" style="font-size:10px;padding:2px 8px" onclick="return confirm('Refund to seller?')">→ Seller</button>
              </form>
            </div>
            @endif
          </td>
        </tr>
        @endforeach
        @if($orders->isEmpty())<tr><td colspan="9" style="text-align:center;padding:40px;color:#94A3B8">No orders</td></tr>@endif
      </tbody>
    </table>
  </div>
  <div style="padding:16px 20px;border-top:1px solid #F1F5F9">{{ $orders->withQueryString()->links() }}</div>
</div>

{{-- ADS TAB --}}
@elseif($tab === 'ads')
<div class="cx-card">
  <div style="overflow-x:auto">
    <table class="cx-table">
      <thead>
        <tr><th>User</th><th>Coin</th><th style="text-align:center">Type</th>
          <th style="text-align:right">Price</th><th style="text-align:right">Qty</th>
          <th style="text-align:right">Min/Max</th><th>Methods</th>
          <th style="text-align:center">Status</th><th></th></tr>
      </thead>
      <tbody>
        @foreach($ads as $ad)
        <tr>
          <td style="font-weight:600">{{ $ad->user->name??'-' }}</td>
          <td style="font-weight:800">{{ $ad->coin->symbol??'-' }}</td>
          <td style="text-align:center"><span class="pill pill-{{ $ad->type }}">{{ strtoupper($ad->type) }}</span></td>
          <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700">${{ number_format($ad->price_usd,2) }}</td>
          <td style="text-align:right;font-variant-numeric:tabular-nums">{{ number_format($ad->amount??0,6) }}</td>
          <td style="text-align:right;font-size:11px">{{ $ad->min_amount }} – {{ $ad->max_amount }}</td>
          <td style="font-size:11px">{{ implode(', ', json_decode($ad->payment_methods??'[]',true)??[]) }}</td>
          <td style="text-align:center">
            <span class="pill" style="background:{{ $ad->status==='active'?'#DCFCE7':'#FEE2E2' }};color:{{ $ad->status==='active'?'#15803D':'#B91C1C' }}">
              {{ $ad->status==='active'?'Active':'Off' }}
            </span>
          </td>
          <td>
            @if($ad->status === 'active')
            <form method="POST" action="{{ route('admin.crypto.p2p.disableAd',$ad->id) }}">@csrf @method('PATCH')
              <button type="submit" class="btn btn-xs btn-danger" style="font-size:10px;padding:2px 8px">Disable</button>
            </form>
            @endif
          </td>
        </tr>
        @endforeach
        @if($ads->isEmpty())<tr><td colspan="9" style="text-align:center;padding:40px;color:#94A3B8">No ads</td></tr>@endif
      </tbody>
    </table>
  </div>
  <div style="padding:16px 20px;border-top:1px solid #F1F5F9">{{ $ads->withQueryString()->links() }}</div>
</div>

{{-- DISPUTES TAB --}}
@elseif($tab === 'disputes')
<div class="cx-card">
  <div style="overflow-x:auto">
    <table class="cx-table">
      <thead>
        <tr><th>Order</th><th>Opener</th><th>Reason</th>
          <th style="text-align:right">Amount</th><th>Date</th><th></th></tr>
      </thead>
      <tbody>
        @foreach($disputes as $d)
        <tr>
          <td style="font-size:10px;font-family:monospace">{{ substr($d->order->uuid??'',0,10) }}<div style="font-weight:700;font-size:11px">{{ $d->order->ad->coin->symbol??'-' }}</div></td>
          <td>{{ $d->opener->name??'-' }}</td>
          <td style="max-width:240px;font-size:12px">{{ $d->reason }}</td>
          <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700">${{ number_format($d->order->total_usd??0,2) }}</td>
          <td style="font-size:11px;color:#94A3B8">{{ $d->created_at->format('M d H:i') }}</td>
          <td>
            <div style="display:flex;gap:4px">
              <form method="POST" action="{{ route('admin.crypto.p2p.resolve',$d->order_id) }}">@csrf @method('PATCH')
                <input type="hidden" name="winner" value="buyer">
                <button type="submit" class="btn btn-xs btn-primary" style="font-size:10px;padding:2px 8px" onclick="return confirm('Release crypto to buyer?')">Buyer Wins</button>
              </form>
              <form method="POST" action="{{ route('admin.crypto.p2p.resolve',$d->order_id) }}">@csrf @method('PATCH')
                <input type="hidden" name="winner" value="seller">
                <button type="submit" class="btn btn-xs btn-warning" style="font-size:10px;padding:2px 8px" onclick="return confirm('Return crypto to seller?')">Seller Wins</button>
              </form>
            </div>
          </td>
        </tr>
        @endforeach
        @if($disputes->isEmpty())<tr><td colspan="6" style="text-align:center;padding:40px;color:#94A3B8">No open disputes</td></tr>@endif
      </tbody>
    </table>
  </div>
</div>

{{-- ESCROW TAB --}}
@elseif($tab === 'escrow')
<div class="cx-card">
  <div class="cx-hdr"><div class="cx-title">Active Escrow Locks</div><div style="font-size:12px;color:#94A3B8">Crypto locked for active P2P orders</div></div>
  <div style="overflow-x:auto">
    <table class="cx-table">
      <thead>
        <tr><th>Order</th><th>Seller</th><th>Coin</th>
          <th style="text-align:right">Locked Amount</th><th style="text-align:center">Status</th><th>Date</th></tr>
      </thead>
      <tbody>
        @foreach($escrows as $e)
        <tr>
          <td style="font-size:10px;font-family:monospace">{{ substr($e->order->uuid??'',0,10) }}</td>
          <td>{{ $e->order->seller->name??'-' }}</td>
          <td style="font-weight:800">{{ $e->order->ad->coin->symbol??'-' }}</td>
          <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700">{{ number_format($e->amount,8) }}</td>
          <td style="text-align:center"><span class="pill pill-{{ $e->status }}">{{ strtoupper($e->status) }}</span></td>
          <td style="font-size:11px;color:#94A3B8">{{ $e->created_at->format('M d H:i') }}</td>
        </tr>
        @endforeach
        @if($escrows->isEmpty())<tr><td colspan="6" style="text-align:center;padding:40px;color:#94A3B8">No active escrow</td></tr>@endif
      </tbody>
    </table>
  </div>
</div>
@endif

</div>
@endsection
