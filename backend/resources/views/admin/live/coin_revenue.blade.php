@extends('admin.layouts.app')
@section('title', 'Coin Revenue')

@push('styles')
<style>
.lv-page  { background:#F8F9FC; min-height:100vh; padding:28px 32px; }
.lv-card  { background:#fff; border-radius:18px; padding:22px 24px; border:1px solid #EAECF0; box-shadow:0 1px 3px rgba(16,24,40,.06); }
.lv-section { background:#fff; border-radius:18px; border:1px solid #EAECF0; box-shadow:0 1px 3px rgba(16,24,40,.06); overflow:hidden; }
.lv-hdr { padding:16px 22px; border-bottom:1px solid #F2F4F7; display:flex; align-items:center; justify-content:space-between; }
.lv-hdr-title { font-size:14px; font-weight:800; color:#101828; }
.lv-table { width:100%; border-collapse:collapse; }
.lv-table th { padding:10px 20px; font-size:11px; font-weight:700; color:#667085; text-transform:uppercase; letter-spacing:.6px; background:#F9FAFB; border-bottom:1px solid #F2F4F7; text-align:left; }
.lv-table td { padding:12px 20px; border-bottom:1px solid #F9FAFB; vertical-align:middle; font-size:13px; }
.lv-table tr:last-child td { border-bottom:none; }
.lv-table tr:hover td { background:#FAFAFA; }
.metric { font-size:28px; font-weight:900; letter-spacing:-1px; color:#101828; }
.metric-label { font-size:12px; color:#667085; margin-top:4px; }
</style>
@endpush

@section('content')
<div class="lv-page">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
    <div>
      <h1 style="font-size:22px;font-weight:900;color:#101828;margin:0;">🪙 Coin Revenue</h1>
      <p style="color:#667085;font-size:13px;margin:4px 0 0;">Coin purchases and gift spending analytics</p>
    </div>
    {{-- Date filter --}}
    <form method="GET" style="display:flex;gap:10px;align-items:center;">
      <input type="date" name="from" value="{{ $from->format('Y-m-d') }}"
        style="padding:7px 12px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px;">
      <span style="color:#667085;">to</span>
      <input type="date" name="to" value="{{ $to->format('Y-m-d') }}"
        style="padding:7px 12px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px;">
      <button type="submit" style="padding:7px 16px;background:#07003B;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
        Apply
      </button>
    </form>
  </div>

  {{-- Summary cards --}}
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
    <div class="lv-card" style="border-left:4px solid #12B76A;">
      <div class="metric-label">Total Revenue</div>
      <div class="metric" style="color:#027A48;">${{ number_format($summary['total_usd'], 2) }}</div>
      <div style="font-size:12px;color:#667085;margin-top:6px;">{{ number_format($summary['total_purchases']) }} purchases</div>
    </div>
    <div class="lv-card" style="border-left:4px solid #7F56D9;">
      <div class="metric-label">Coins Sold</div>
      <div class="metric" style="color:#7F56D9;">{{ number_format($summary['total_coins_sold']) }}</div>
      <div style="font-size:12px;color:#667085;margin-top:6px;">coins purchased</div>
    </div>
    <div class="lv-card" style="border-left:4px solid #FF8A00;">
      <div class="metric-label">Coins in Gifts</div>
      <div class="metric" style="color:#FF8A00;">{{ number_format($summary['coins_in_gifts']) }}</div>
      <div style="font-size:12px;color:#667085;margin-top:6px;">sent as gifts</div>
    </div>
    <div class="lv-card" style="border-left:4px solid #1570EF;">
      <div class="metric-label">Coin Circulation</div>
      @php
        $circ = $summary['total_coins_sold'] > 0
          ? round(($summary['coins_in_gifts'] / $summary['total_coins_sold']) * 100)
          : 0;
      @endphp
      <div class="metric" style="color:#1570EF;">{{ $circ }}%</div>
      <div style="font-size:12px;color:#667085;margin-top:6px;">of purchased coins spent</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;">

    {{-- Daily chart (simple bar representation) --}}
    <div class="lv-section">
      <div class="lv-hdr">
        <div class="lv-hdr-title">📈 Daily Revenue</div>
      </div>
      <div style="padding:20px;">
        @if($daily->isEmpty())
          <div style="text-align:center;color:#667085;padding:30px;">No data for this period</div>
        @else
          @php $maxRev = $daily->max('revenue') ?: 1; @endphp
          <div style="display:flex;align-items:flex-end;gap:4px;height:140px;overflow-x:auto;">
            @foreach($daily as $d)
              @php $h = max(4, ($d->revenue / $maxRev) * 130); @endphp
              <div style="flex:1;min-width:28px;display:flex;flex-direction:column;align-items:center;gap:4px;">
                <div title="${{ number_format($d->revenue,2) }}"
                  style="width:100%;height:{{ $h }}px;background:linear-gradient(180deg,#7F56D9,#1570EF);border-radius:4px 4px 0 0;cursor:pointer;transition:.2s;"
                  onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                </div>
                <div style="font-size:9px;color:#667085;white-space:nowrap;transform:rotate(-40deg);transform-origin:top left;margin-top:4px;">
                  {{ \Carbon\Carbon::parse($d->date)->format('M d') }}
                </div>
              </div>
            @endforeach
          </div>
          <div style="margin-top:30px;overflow-x:auto;">
            <table class="lv-table">
              <thead><tr><th>Date</th><th>Purchases</th><th>Revenue</th><th>Coins Sold</th></tr></thead>
              <tbody>
              @foreach($daily->sortByDesc('date')->take(7) as $d)
                <tr>
                  <td style="color:#344054;font-weight:600;">{{ \Carbon\Carbon::parse($d->date)->format('M d, Y') }}</td>
                  <td>{{ number_format($d->purchases) }}</td>
                  <td style="color:#027A48;font-weight:700;">${{ number_format($d->revenue, 2) }}</td>
                  <td style="color:#7F56D9;font-weight:700;">{{ number_format($d->coins) }}</td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>

    {{-- By payment method --}}
    <div class="lv-section">
      <div class="lv-hdr">
        <div class="lv-hdr-title">💳 By Payment Method</div>
      </div>
      <div style="padding:16px 0;">
        @forelse($byMethod as $m)
          @php $icon = match($m->payment_method) { 'waafipay'=>'🏦', 'epay'=>'💳', default=>'💰' }; @endphp
          <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 22px;border-bottom:1px solid #F9FAFB;">
            <div>
              <div style="font-weight:700;color:#101828;font-size:13px;">{{ $icon }} {{ ucfirst($m->payment_method) }}</div>
              <div style="font-size:11px;color:#667085;">{{ number_format($m->cnt) }} transactions</div>
            </div>
            <div style="text-align:right;">
              <div style="font-size:15px;font-weight:800;color:#027A48;">${{ number_format($m->total, 2) }}</div>
            </div>
          </div>
        @empty
          <div style="padding:30px;text-align:center;color:#667085;">No purchase data</div>
        @endforelse
      </div>
    </div>
  </div>

  {{-- Top buyers --}}
  <div class="lv-section">
    <div class="lv-hdr">
      <div class="lv-hdr-title">🏆 Top Coin Buyers</div>
    </div>
    <table class="lv-table">
      <thead><tr>
        <th>#</th><th>User</th><th>Purchases</th><th>Total Spent</th><th>Coins Bought</th>
      </tr></thead>
      <tbody>
      @forelse($topBuyers as $i => $b)
        <tr>
          <td style="font-weight:700;color:#667085;">{{ $i+1 }}</td>
          <td>
            <div style="display:flex;align-items:center;gap:9px;">
              <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#07003B,#FF8A00);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:11px;">
                {{ strtoupper(substr($b->user?->name ?? '?', 0, 1)) }}
              </div>
              <div>
                <div style="font-weight:700;font-size:13px;color:#101828;">{{ $b->user?->name }}</div>
                <div style="font-size:11px;color:#667085;">&#64;{{ $b->user?->communityProfile?->username ?? '-' }}</div>
              </div>
            </div>
          </td>
          <td style="color:#344054;font-weight:600;">{{ number_format($b->purchases) }}</td>
          <td style="font-weight:800;color:#027A48;font-size:14px;">${{ number_format($b->total_spent, 2) }}</td>
          <td style="font-weight:700;color:#7F56D9;">{{ number_format($b->total_coins) }} 🪙</td>
        </tr>
      @empty
        <tr><td colspan="5" style="text-align:center;color:#667085;padding:40px;">No buyers yet</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

</div>
@endsection
