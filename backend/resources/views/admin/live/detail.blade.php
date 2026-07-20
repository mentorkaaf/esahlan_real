@extends('admin.layouts.app')
@section('title', 'Live Room — ' . $room->title)

@push('styles')
<style>
.lv-page { background:#F8F9FC; min-height:100vh; padding:28px 32px; }
.lv-card { background:#fff; border-radius:18px; padding:24px; border:1px solid #EAECF0; box-shadow:0 1px 3px rgba(16,24,40,.06); }
.lv-section { background:#fff; border-radius:18px; border:1px solid #EAECF0; box-shadow:0 1px 3px rgba(16,24,40,.06); overflow:hidden; margin-bottom:20px; }
.lv-hdr { padding:16px 22px; border-bottom:1px solid #F2F4F7; font-size:14px; font-weight:800; color:#101828; }
.lv-table { width:100%; border-collapse:collapse; }
.lv-table th { padding:9px 18px; font-size:11px; font-weight:700; color:#667085; text-transform:uppercase; background:#F9FAFB; border-bottom:1px solid #F2F4F7; text-align:left; }
.lv-table td { padding:12px 18px; border-bottom:1px solid #F9FAFB; font-size:13px; vertical-align:middle; }
.lv-table tr:last-child td { border-bottom:none; }
.status-live  { background:#FEF3F2; color:#B42318; font-size:11px; font-weight:700; padding:3px 10px; border-radius:100px; }
.status-ended { background:#F2F4F7; color:#667085; font-size:11px; font-weight:700; padding:3px 10px; border-radius:100px; }
.lv-action-btn { padding:6px 14px; border-radius:8px; font-size:12px; font-weight:700; border:none; cursor:pointer; }
.btn-end { background:#FEE4E2; color:#B42318; }
</style>
@endpush

@section('content')
<div class="lv-page">
  @php
    $durSecs = $room->ended_at && $room->created_at ? $room->created_at->diffInSeconds($room->ended_at) : 0;
    $durStr  = $durSecs > 0 ? (floor($durSecs/60).'m '.($durSecs%60).'s') : 'Ongoing';
    $host    = $room->host;
  @endphp

  <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
    <a href="{{ route('admin.live.index') }}" style="color:#667085;font-size:20px;"><i class="fas fa-arrow-left"></i></a>
    <div style="flex:1;">
      <h1 style="font-size:20px;font-weight:900;color:#101828;margin:0;">{{ $room->title }}</h1>
      <div style="color:#667085;font-size:13px;margin-top:3px;">
        Room #{{ $room->id }} · Started {{ $room->created_at->format('M d Y, H:i') }}
      </div>
    </div>
    <div>
      @if($room->status === 'live')
        <span class="status-live"><i class="fas fa-circle" style="font-size:9px;"></i> LIVE</span>
        <form method="POST" action="{{ route('admin.live.force-end', $room->id) }}" style="display:inline;margin-left:8px;"
              onsubmit="return confirm('Force-end this live?')">
          @csrf
          <button class="lv-action-btn btn-end"><i class="fas fa-stop"></i> Force End</button>
        </form>
      @else
        <span class="status-ended">Ended</span>
      @endif
    </div>
  </div>

  @if(session('success'))
    <div style="background:#ECFDF3;border:1px solid #A9EFC5;color:#027A48;padding:12px 18px;border-radius:10px;margin-bottom:16px;font-weight:600;font-size:13px;">
      <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
  @endif

  <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px;margin-bottom:22px;">
    <div class="lv-card" style="text-align:center;">
      <div style="font-size:28px;font-weight:800;color:#D92D20;">{{ $room->peak_viewers }}</div>
      <div style="font-size:12px;color:#667085;margin-top:4px;">Peak Viewers</div>
    </div>
    <div class="lv-card" style="text-align:center;">
      <div style="font-size:28px;font-weight:800;color:#FF8A00;">{{ $room->viewer_count }}</div>
      <div style="font-size:12px;color:#667085;margin-top:4px;">Current / Total Viewers</div>
    </div>
    <div class="lv-card" style="text-align:center;">
      <div style="font-size:28px;font-weight:800;color:#7F56D9;">{{ $giftStats->sum('coins') }}</div>
      <div style="font-size:12px;color:#667085;margin-top:4px;">Coins Received</div>
    </div>
    <div class="lv-card" style="text-align:center;">
      <div style="font-size:28px;font-weight:800;color:#12B76A;">{{ $durStr }}</div>
      <div style="font-size:12px;color:#667085;margin-top:4px;">Duration</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

    {{-- Host card + ban --}}
    <div class="lv-card">
      <div style="font-size:13px;font-weight:800;color:#101828;margin-bottom:14px;">Host</div>
      <div style="display:flex;align-items:center;gap:14px;">
        <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#07003B,#FF8A00);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:20px;">
          {{ strtoupper(substr($host?->name ?? '?', 0, 1)) }}
        </div>
        <div style="flex:1;">
          <div style="font-size:16px;font-weight:800;color:#101828;">{{ $host?->name }}</div>
          <div style="font-size:12px;color:#667085;margin-top:2px;">@{{ $host?->communityProfile?->username ?? '-' }}</div>
          <div style="font-size:12px;color:#667085;">{{ $host?->email }}</div>
        </div>
      </div>
      <div style="margin-top:16px;padding-top:14px;border-top:1px solid #F2F4F7;display:flex;gap:10px;">
        <form method="POST" action="{{ route('admin.live.users.toggle-ban', $host?->id) }}">
          @csrf
          <button class="lv-action-btn" style="background:{{ $host?->banned_from_live ? '#ECFDF3' : '#FFF4ED' }};color:{{ $host?->banned_from_live ? '#027A48' : '#B54708' }}">
            <i class="fas fa-{{ $host?->banned_from_live ? 'unlock' : 'ban' }}"></i>
            {{ $host?->banned_from_live ? 'Unban from Live' : 'Ban from Live' }}
          </button>
        </form>
      </div>
    </div>

    {{-- Gift breakdown --}}
    <div class="lv-card">
      <div style="font-size:13px;font-weight:800;color:#101828;margin-bottom:14px;">Gift Breakdown</div>
      @if($giftStats->isEmpty())
        <div style="color:#667085;font-size:13px;text-align:center;padding:20px;">No gifts sent</div>
      @else
        @foreach($giftStats as $gs)
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
            <span style="font-size:24px;">{{ $gs->gift?->emoji }}</span>
            <div style="flex:1;">
              <div style="font-size:13px;font-weight:700;">{{ $gs->gift?->name }}</div>
              <div style="font-size:11px;color:#667085;">{{ $gs->qty }} sent</div>
            </div>
            <div style="font-size:14px;font-weight:800;color:#7F56D9;">{{ number_format($gs->coins) }} 🪙</div>
          </div>
        @endforeach
      @endif
    </div>

    {{-- Viewers --}}
    <div class="lv-section" style="margin-bottom:0;">
      <div class="lv-hdr">Viewers ({{ $viewers->total() }})</div>
      <table class="lv-table">
        <thead><tr><th>User</th><th>Joined</th><th>Left</th></tr></thead>
        <tbody>
        @foreach($viewers as $v)
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#07003B,#FF8A00);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:800;">
                  {{ strtoupper(substr($v->user?->name ?? '?', 0, 1)) }}
                </div>
                {{ $v->user?->name }}
              </div>
            </td>
            <td style="color:#667085;">{{ $v->joined_at?->format('H:i:s') }}</td>
            <td style="color:#667085;">{{ $v->left_at?->format('H:i:s') ?? '—' }}</td>
          </tr>
        @endforeach
        @if($viewers->isEmpty())
          <tr><td colspan="3" style="text-align:center;color:#667085;padding:24px;">No viewer data</td></tr>
        @endif
        </tbody>
      </table>
      <div style="padding:12px 18px;">{{ $viewers->links() }}</div>
    </div>

    {{-- Gift transactions --}}
    <div class="lv-section" style="margin-bottom:0;">
      <div class="lv-hdr">Gift Transactions ({{ $transactions->total() }})</div>
      <table class="lv-table">
        <thead><tr><th>Sender</th><th>Gift</th><th>Qty</th><th>Coins</th><th>Time</th></tr></thead>
        <tbody>
        @foreach($transactions as $t)
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#07003B,#FF8A00);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:800;">
                  {{ strtoupper(substr($t->sender?->name ?? '?', 0, 1)) }}
                </div>
                {{ $t->sender?->name }}
              </div>
            </td>
            <td><span style="font-size:18px;">{{ $t->gift?->emoji }}</span> {{ $t->gift?->name }}</td>
            <td style="font-weight:700;">×{{ $t->quantity }}</td>
            <td style="font-weight:700;color:#7F56D9;">{{ $t->coins_spent }}</td>
            <td style="color:#667085;font-size:12px;">{{ $t->created_at->format('H:i:s') }}</td>
          </tr>
        @endforeach
        @if($transactions->isEmpty())
          <tr><td colspan="5" style="text-align:center;color:#667085;padding:24px;">No transactions</td></tr>
        @endif
        </tbody>
      </table>
      <div style="padding:12px 18px;">{{ $transactions->links() }}</div>
    </div>
  </div>
</div>
@endsection
