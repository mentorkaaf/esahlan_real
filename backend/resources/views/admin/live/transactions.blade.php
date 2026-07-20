@extends('admin.layouts.app')
@section('title', 'Coin Transactions')

@push('styles')
<style>
.lv-page { background:#F8F9FC; min-height:100vh; padding:28px 32px; }
.lv-section { background:#fff; border-radius:18px; border:1px solid #EAECF0; box-shadow:0 1px 3px rgba(16,24,40,.06); overflow:hidden; }
.lv-hdr { padding:18px 24px; border-bottom:1px solid #F2F4F7; display:flex; align-items:center; justify-content:space-between; }
.lv-table { width:100%; border-collapse:collapse; }
.lv-table th { padding:10px 20px; font-size:11px; font-weight:700; color:#667085; text-transform:uppercase; background:#F9FAFB; border-bottom:1px solid #F2F4F7; text-align:left; }
.lv-table td { padding:13px 20px; border-bottom:1px solid #F9FAFB; font-size:13px; vertical-align:middle; }
.lv-table tr:last-child td { border-bottom:none; }
.filter-input { border:1px solid #D0D5DD; border-radius:8px; padding:8px 12px; font-size:13px; outline:none; }
.filter-input:focus { border-color:#FF8A00; }
.btn-filter { background:#FF8A00; color:#fff; border:none; border-radius:8px; padding:8px 16px; font-size:13px; font-weight:700; cursor:pointer; }
.lv-card { background:#fff; border-radius:16px; padding:20px 24px; border:1px solid #EAECF0; text-align:center; }
</style>
@endpush

@section('content')
<div class="lv-page">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
    <a href="{{ route('admin.live.index') }}" style="color:#667085;font-size:20px;"><i class="fas fa-arrow-left"></i></a>
    <div>
      <h1 style="font-size:22px;font-weight:900;color:#101828;margin:0;">ðŸª™ Coin Transactions</h1>
      <p style="color:#667085;font-size:13px;margin:3px 0 0;">All gift transactions during live streams</p>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:22px;">
    <div class="lv-card">
      <div style="font-size:30px;font-weight:800;color:#7F56D9;">{{ number_format($totalCoins) }}</div>
      <div style="font-size:12px;color:#667085;margin-top:4px;">Total Coins Sent</div>
    </div>
    <div class="lv-card">
      <div style="font-size:30px;font-weight:800;color:#FF8A00;">{{ number_format($totalGifts) }}</div>
      <div style="font-size:12px;color:#667085;margin-top:4px;">Total Gifts Sent</div>
    </div>
    <div class="lv-card">
      <div style="font-size:30px;font-weight:800;color:#12B76A;">{{ number_format($transactions->total()) }}</div>
      <div style="font-size:12px;color:#667085;margin-top:4px;">Transactions (filtered)</div>
    </div>
  </div>

  <form method="GET" style="background:#fff;border-radius:14px;border:1px solid #EAECF0;padding:16px 20px;margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
    <div style="flex:1;min-width:180px;">
      <label style="font-size:11px;font-weight:700;color:#667085;display:block;margin-bottom:4px;">SEARCH SENDER/RECEIVER</label>
      <input class="filter-input" style="width:100%;" name="search" value="{{ request('search') }}" placeholder="User name...">
    </div>
    <div>
      <label style="font-size:11px;font-weight:700;color:#667085;display:block;margin-bottom:4px;">FROM</label>
      <input class="filter-input" type="date" name="from" value="{{ request('from') }}">
    </div>
    <div>
      <label style="font-size:11px;font-weight:700;color:#667085;display:block;margin-bottom:4px;">TO</label>
      <input class="filter-input" type="date" name="to" value="{{ request('to') }}">
    </div>
    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
    <a href="{{ route('admin.live.transactions') }}" style="color:#667085;font-size:13px;font-weight:600;padding:8px 12px;text-decoration:none;">Reset</a>
  </form>

  <div class="lv-section">
    <table class="lv-table">
      <thead><tr>
        <th>Sender</th><th>Receiver / Room</th><th>Gift</th><th>Qty</th><th>Coins</th><th>Date</th>
      </tr></thead>
      <tbody>
      @foreach($transactions as $t)
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:9px;">
              <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#07003B,#FF8A00);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:800;">
                {{ strtoupper(substr($t->sender?->name ?? '?', 0, 1)) }}
              </div>
              <div>
                <div style="font-weight:700;">{{ $t->sender?->name }}</div>
                <div style="font-size:11px;color:#667085;">&#64;{{ $t->sender?->communityProfile?->username ?? '-' }}</div>
              </div>
            </div>
          </td>
          <td>
            <div style="font-weight:700;">{{ $t->receiver?->name }}</div>
            @if($t->liveRoom)
              <a href="{{ route('admin.live.detail', $t->live_room_id) }}" style="font-size:11px;color:#1570EF;">
                Room: {{ Str::limit($t->liveRoom?->title, 25) }}
              </a>
            @endif
          </td>
          <td>
            <span style="font-size:20px;">{{ $t->gift?->emoji }}</span>
            <span style="margin-left:6px;font-weight:600;">{{ $t->gift?->name }}</span>
          </td>
          <td style="font-weight:800;color:#101828;">Ã—{{ $t->quantity }}</td>
          <td style="font-weight:800;color:#7F56D9;font-size:15px;">{{ $t->coins_spent }} ðŸª™</td>
          <td style="color:#667085;font-size:12px;">{{ $t->created_at->format('M d, H:i') }}</td>
        </tr>
      @endforeach
      @if($transactions->isEmpty())
        <tr><td colspan="6" style="text-align:center;color:#667085;padding:40px;">No transactions found</td></tr>
      @endif
      </tbody>
    </table>
    <div style="padding:16px 20px;">{{ $transactions->links() }}</div>
  </div>
</div>
@endsection

