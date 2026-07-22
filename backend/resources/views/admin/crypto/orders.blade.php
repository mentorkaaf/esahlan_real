@extends('admin.layouts.app')
@section('title', 'Buy/Sell Orders')

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
.pill-completed{background:#DCFCE7;color:#15803D}
.pill-pending{background:#FEF3C7;color:#B45309}
.pill-failed{background:#FEE2E2;color:#B91C1C}
.pill-processing{background:#DBEAFE;color:#1D4ED8}
.filter-bar{background:#fff;border-radius:12px;border:1px solid #EEF0F6;padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
</style>
@endpush

@section('content')
<div class="cx">
<div style="margin-bottom:20px">
  <div style="font-size:12px;color:#94A3B8;margin-bottom:2px">Finance / Exchange</div>
  <h1 style="font-size:22px;font-weight:900;color:#0F172A;margin:0">Buy / Sell Orders</h1>
</div>

<form method="GET" action="{{ route('admin.crypto.orders') }}" class="filter-bar">
  <div>
    <label style="font-size:10px;font-weight:700;color:#64748B;display:block;margin-bottom:4px">COIN</label>
    <select name="coin_id" class="form-control form-control-sm">
      <option value="">All Coins</option>
      @foreach($coins as $c)<option value="{{ $c->id }}" {{ request('coin_id')==$c->id?'selected':'' }}>{{ $c->symbol }}</option>@endforeach
    </select>
  </div>
  <div>
    <label style="font-size:10px;font-weight:700;color:#64748B;display:block;margin-bottom:4px">SIDE</label>
    <select name="side" class="form-control form-control-sm">
      <option value="">All</option>
      <option value="buy" {{ request('side')=='buy'?'selected':'' }}>Buy</option>
      <option value="sell" {{ request('side')=='sell'?'selected':'' }}>Sell</option>
    </select>
  </div>
  <div>
    <label style="font-size:10px;font-weight:700;color:#64748B;display:block;margin-bottom:4px">STATUS</label>
    <select name="status" class="form-control form-control-sm">
      <option value="">All</option>
      <option value="completed" {{ request('status')=='completed'?'selected':'' }}>Completed</option>
      <option value="pending" {{ request('status')=='pending'?'selected':'' }}>Pending</option>
      <option value="failed" {{ request('status')=='failed'?'selected':'' }}>Failed</option>
    </select>
  </div>
  <div>
    <label style="font-size:10px;font-weight:700;color:#64748B;display:block;margin-bottom:4px">FROM</label>
    <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
  </div>
  <div>
    <label style="font-size:10px;font-weight:700;color:#64748B;display:block;margin-bottom:4px">TO</label>
    <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
  </div>
  <div>
    <label style="font-size:10px;font-weight:700;color:#64748B;display:block;margin-bottom:4px">SEARCH</label>
    <input type="text" name="q" value="{{ request('q') }}" placeholder="User / UUID" class="form-control form-control-sm" style="width:160px">
  </div>
  <button type="submit" class="btn btn-sm btn-primary" style="align-self:flex-end">Filter</button>
  <a href="{{ route('admin.crypto.orders') }}" class="btn btn-sm btn-light" style="align-self:flex-end">Clear</a>
</form>

{{-- Summary --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px">
  @foreach([['Total','$'.number_format($summary->total_usd??0,2),'#0F172A'],['Buy Vol','$'.number_format($summary->buy_usd??0,2),'#15803D'],['Sell Vol','$'.number_format($summary->sell_usd??0,2),'#EF4444'],['Fees Earned','$'.number_format($summary->fees??0,2),'#7C3AED']] as [$l,$v,$c])
  <div style="background:#fff;border-radius:12px;border:1px solid #EEF0F6;padding:14px 18px">
    <div style="font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px">{{ $l }}</div>
    <div style="font-size:20px;font-weight:900;color:{{ $c }}">{{ $v }}</div>
  </div>
  @endforeach
</div>

<div class="cx-card">
  <div style="overflow-x:auto">
    <table class="cx-table">
      <thead>
        <tr>
          <th>UUID</th><th>User</th><th>Coin</th><th style="text-align:center">Side</th>
          <th style="text-align:right">Qty</th><th style="text-align:right">Price</th>
          <th style="text-align:right">Total USD</th><th style="text-align:right">Fee</th>
          <th>Method</th><th style="text-align:center">Status</th><th style="text-align:right">Date</th>
        </tr>
      </thead>
      <tbody>
        @foreach($orders as $o)
        <tr>
          <td style="font-size:10px;color:#94A3B8;font-family:monospace">{{ substr($o->uuid,0,12) }}</td>
          <td style="font-weight:600">{{ $o->user->name ?? '-' }}<div style="font-size:10px;color:#94A3B8">{{ $o->user->phone ?? '' }}</div></td>
          <td style="font-weight:800">{{ $o->coin->symbol ?? '-' }}</td>
          <td style="text-align:center"><span class="pill pill-{{ $o->side }}">{{ strtoupper($o->side) }}</span></td>
          <td style="text-align:right;font-variant-numeric:tabular-nums">{{ rtrim(rtrim(number_format($o->quantity,8),'0'),'.') }}</td>
          <td style="text-align:right;font-variant-numeric:tabular-nums">${{ number_format($o->price_usd,2) }}</td>
          <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700">${{ number_format($o->total_usd,2) }}</td>
          <td style="text-align:right;font-size:11px;color:#94A3B8">${{ number_format($o->fee_usd??0,2) }}</td>
          <td style="font-size:11px">{{ $o->payment_method ?? '-' }}</td>
          <td style="text-align:center"><span class="pill pill-{{ $o->status }}">{{ strtoupper($o->status) }}</span></td>
          <td style="text-align:right;font-size:11px;color:#94A3B8">{{ $o->created_at->format('M d H:i') }}</td>
        </tr>
        @endforeach
        @if($orders->isEmpty())
        <tr><td colspan="11" style="text-align:center;padding:40px;color:#94A3B8">No orders found</td></tr>
        @endif
      </tbody>
    </table>
  </div>
  <div style="padding:16px 20px;border-top:1px solid #F1F5F9">
    {{ $orders->withQueryString()->links() }}
  </div>
</div>
</div>
@endsection
