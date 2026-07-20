@extends('admin.layouts.app')
@section('title', 'Live Management')

@push('styles')
<style>
.lv-page { background:#F8F9FC; min-height:100vh; padding:28px 32px; }
.lv-card { background:#fff; border-radius:18px; padding:24px; border:1px solid #EAECF0;
  box-shadow:0 1px 3px rgba(16,24,40,.06); transition:box-shadow .2s,transform .2s; }
.lv-card:hover { box-shadow:0 4px 16px rgba(7,0,59,.09); transform:translateY(-1px); }
.lv-metric { font-size:32px; font-weight:800; line-height:1.1; letter-spacing:-1px; color:#101828; }
.lv-label  { font-size:13px; color:#667085; font-weight:500; margin-top:4px; }
.lv-badge  { font-size:11px; font-weight:700; padding:2px 10px; border-radius:100px; }
.badge-live   { background:#FEF3F2; color:#B42318; }
.badge-ended  { background:#F2F4F7; color:#667085; }
.badge-active { background:#ECFDF3; color:#027A48; }
.lv-section { background:#fff; border-radius:18px; border:1px solid #EAECF0;
  box-shadow:0 1px 3px rgba(16,24,40,.06); overflow:hidden; }
.lv-hdr { padding:18px 24px; border-bottom:1px solid #F2F4F7;
  display:flex; align-items:center; justify-content:space-between; }
.lv-hdr-title { font-size:14px; font-weight:800; color:#101828; }
.lv-table { width:100%; border-collapse:collapse; }
.lv-table th { padding:10px 20px; font-size:11px; font-weight:700; color:#667085;
  text-transform:uppercase; letter-spacing:.6px; background:#F9FAFB;
  border-bottom:1px solid #F2F4F7; text-align:left; }
.lv-table td { padding:13px 20px; border-bottom:1px solid #F9FAFB; vertical-align:middle; }
.lv-table tr:last-child td { border-bottom:none; }
.lv-table tr:hover td { background:#FAFAFA; }
.av-dot { width:8px; height:8px; background:#12B76A; border-radius:50%;
  display:inline-block; margin-right:6px; animation:pulse 1.4s ease-in-out infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
.lv-action-btn { padding:5px 12px; border-radius:8px; font-size:12px; font-weight:700;
  border:none; cursor:pointer; transition:all .15s; }
.btn-end   { background:#FEE4E2; color:#B42318; }
.btn-end:hover { background:#FCA5A5; }
.btn-detail { background:#EFF8FF; color:#1570EF; text-decoration:none; }
.btn-detail:hover { background:#D1E9FF; }
.gift-emoji { font-size:24px; }
</style>
@endpush

@section('content')
<div class="lv-page">

  {{-- ── Header ── --}}
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;">
    <div>
      <h1 style="font-size:22px;font-weight:900;color:#101828;margin:0;">🔴 Live Management</h1>
      <p style="color:#667085;font-size:13px;margin:4px 0 0;">Monitor, moderate and manage all live streams</p>
    </div>
    <div style="display:flex;gap:10px;">
      <a href="{{ route('admin.live.history') }}" style="background:#07003B;color:#fff;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:7px;">
        <i class="fas fa-history"></i> Full History
      </a>
      <a href="{{ route('admin.live.gifts') }}" style="background:#FF8A00;color:#fff;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:7px;">
        <i class="fas fa-gift"></i> Manage Gifts
      </a>
    </div>
  </div>

  @if(session('success'))
    <div style="background:#ECFDF3;border:1px solid #A9EFC5;color:#027A48;padding:12px 18px;border-radius:10px;margin-bottom:20px;font-weight:600;font-size:13px;">
      <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
  @endif

  {{-- ── Stats row ── --}}
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
    <div class="lv-card" style="border-left:4px solid #D92D20;">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
        <div style="width:40px;height:40px;background:#FEF3F2;border-radius:10px;display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-circle" style="color:#D92D20;font-size:10px;animation:pulse 1s infinite;"></i>
        </div>
        <div class="lv-label">Live Now</div>
      </div>
      <div class="lv-metric">{{ $stats['active_now'] }}</div>
      <div style="font-size:12px;color:#667085;margin-top:6px;">{{ $stats['total_viewers_now'] }} viewers watching</div>
    </div>

    <div class="lv-card" style="border-left:4px solid #FF8A00;">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
        <div style="width:40px;height:40px;background:#FFF4ED;border-radius:10px;display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-video" style="color:#FF8A00;font-size:14px;"></i>
        </div>
        <div class="lv-label">Lives This Week</div>
      </div>
      <div class="lv-metric">{{ $stats['lives_week'] }}</div>
      <div style="font-size:12px;color:#667085;margin-top:6px;">{{ $stats['lives_today'] }} today · {{ $stats['lives_total'] }} total</div>
    </div>

    <div class="lv-card" style="border-left:4px solid #7F56D9;">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
        <div style="width:40px;height:40px;background:#F4F3FF;border-radius:10px;display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-coins" style="color:#7F56D9;font-size:14px;"></i>
        </div>
        <div class="lv-label">Coins This Week</div>
      </div>
      <div class="lv-metric">{{ number_format($stats['coins_week']) }}</div>
      <div style="font-size:12px;color:#667085;margin-top:6px;">{{ number_format($stats['coins_total']) }} total coins sent</div>
    </div>

    <div class="lv-card" style="border-left:4px solid #12B76A;">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
        <div style="width:40px;height:40px;background:#ECFDF3;border-radius:10px;display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-eye" style="color:#12B76A;font-size:14px;"></i>
        </div>
        <div class="lv-label">Peak Viewers Ever</div>
      </div>
      <div class="lv-metric">{{ number_format($stats['peak_viewers_ever']) }}</div>
      <div style="font-size:12px;color:#667085;margin-top:6px;">{{ $stats['banned_hosts'] }} users banned from live</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">

    {{-- ── Active lives ── --}}
    <div class="lv-section" style="grid-column:1/-1;">
      <div class="lv-hdr">
        <div>
          <div class="lv-hdr-title"><span class="av-dot"></span>Active Live Rooms ({{ count($activeRooms) }})</div>
          <div style="font-size:12px;color:#667085;margin-top:2px;">Real-time — refresh to update viewer counts</div>
        </div>
        <a href="{{ route('admin.live.history') }}?status=live" style="font-size:12px;color:#1570EF;font-weight:600;text-decoration:none;">View All</a>
      </div>
      @if($activeRooms->isEmpty())
        <div style="padding:40px;text-align:center;color:#667085;font-size:13px;">
          <i class="fas fa-signal" style="font-size:28px;opacity:.3;display:block;margin-bottom:10px;"></i>
          No live streams right now
        </div>
      @else
        <table class="lv-table">
          <thead><tr>
            <th>Host</th><th>Title</th><th>Viewers</th><th>Peak</th><th>Duration</th><th>Actions</th>
          </tr></thead>
          <tbody>
          @foreach($activeRooms as $room)
            @php
              $host = $room->host;
              $dur  = Carbon\Carbon::parse($room->created_at)->diffForHumans(null, true);
            @endphp
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#07003B,#FF8A00);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:13px;flex-shrink:0;">
                    {{ strtoupper(substr($host?->name ?? '?', 0, 1)) }}
                  </div>
                  <div>
                    <div style="font-weight:700;font-size:13px;color:#101828;">{{ $host?->name }}</div>
                    <div style="font-size:11px;color:#667085;">@{{ $host?->communityProfile?->username ?? '-' }}</div>
                  </div>
                </div>
              </td>
              <td style="font-size:13px;color:#101828;max-width:180px;">
                <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;">{{ $room->title }}</div>
              </td>
              <td>
                <span style="font-weight:800;color:#D92D20;font-size:14px;">{{ $room->viewer_count }}</span>
                <i class="fas fa-eye" style="color:#D92D20;font-size:11px;margin-left:3px;"></i>
              </td>
              <td style="color:#667085;font-size:13px;">{{ $room->peak_viewers }}</td>
              <td style="color:#667085;font-size:12px;">{{ $dur }}</td>
              <td>
                <div style="display:flex;gap:6px;">
                  <a href="{{ route('admin.live.detail', $room->id) }}" class="lv-action-btn btn-detail">
                    <i class="fas fa-eye"></i> Detail
                  </a>
                  <form method="POST" action="{{ route('admin.live.force-end', $room->id) }}"
                        onsubmit="return confirm('Force-end this live stream?')">
                    @csrf
                    <button class="lv-action-btn btn-end" type="submit">
                      <i class="fas fa-stop"></i> Force End
                    </button>
                  </form>
                  <form method="POST" action="{{ route('admin.live.users.toggle-ban', $host?->id) }}"
                        onsubmit="return confirm('{{ $host?->banned_from_live ? 'Unban' : 'Ban' }} this user from live?')">
                    @csrf
                    <button class="lv-action-btn" type="submit"
                      style="background:{{ $host?->banned_from_live ? '#ECFDF3' : '#FFF4ED' }};color:{{ $host?->banned_from_live ? '#027A48' : '#B54708' }}">
                      <i class="fas fa-{{ $host?->banned_from_live ? 'unlock' : 'ban' }}"></i>
                      {{ $host?->banned_from_live ? 'Unban' : 'Ban' }}
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      @endif
    </div>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px;">

    {{-- ── Top hosts ── --}}
    <div class="lv-section">
      <div class="lv-hdr">
        <div class="lv-hdr-title">🏆 Top Hosts (All Time)</div>
        <a href="{{ route('admin.live.history') }}" style="font-size:12px;color:#1570EF;font-weight:600;text-decoration:none;">Full History</a>
      </div>
      <table class="lv-table">
        <thead><tr>
          <th>#</th><th>Host</th><th>Lives</th><th>Total Views</th><th>Live Ban</th>
        </tr></thead>
        <tbody>
        @foreach($topHosts as $i => $h)
          @php $host = $h->host; @endphp
          <tr>
            <td style="color:#667085;font-size:13px;font-weight:700;">{{ $i+1 }}</td>
            <td>
              <div style="display:flex;align-items:center;gap:9px;">
                <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#07003B,#FF8A00);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:12px;">
                  {{ strtoupper(substr($host?->name ?? '?', 0, 1)) }}
                </div>
                <div>
                  <div style="font-weight:700;font-size:13px;">{{ $host?->name }}</div>
                  <div style="font-size:11px;color:#667085;">@{{ $host?->communityProfile?->username ?? '-' }}</div>
                </div>
              </div>
            </td>
            <td style="font-size:13px;color:#101828;font-weight:600;">{{ $h->total_lives }}</td>
            <td style="font-size:13px;font-weight:700;color:#7F56D9;">{{ number_format($h->total_views) }}</td>
            <td>
              <form method="POST" action="{{ route('admin.live.users.toggle-ban', $host?->id) }}">
                @csrf
                <button class="lv-action-btn" type="submit" title="{{ $host?->banned_from_live ? 'Unban' : 'Ban' }}"
                  style="background:{{ $host?->banned_from_live ? '#ECFDF3' : '#FFF4ED' }};color:{{ $host?->banned_from_live ? '#027A48' : '#B54708' }}">
                  <i class="fas fa-{{ $host?->banned_from_live ? 'check' : 'ban' }}"></i>
                  {{ $host?->banned_from_live ? 'Banned' : 'Active' }}
                </button>
              </form>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>

    {{-- ── Top gifts ── --}}
    <div class="lv-section">
      <div class="lv-hdr">
        <div class="lv-hdr-title">🎁 Most Sent Gifts</div>
        <a href="{{ route('admin.live.gifts') }}" style="font-size:12px;color:#FF8A00;font-weight:600;text-decoration:none;">Manage</a>
      </div>
      <div style="padding:8px 0;">
        @foreach($topGifts as $gift)
        <div style="display:flex;align-items:center;gap:14px;padding:13px 20px;border-bottom:1px solid #F9FAFB;">
          <div style="font-size:26px;width:40px;text-align:center;">{{ $gift->emoji }}</div>
          <div style="flex:1;">
            <div style="font-size:13px;font-weight:700;color:#101828;">{{ $gift->name }}</div>
            <div style="font-size:11px;color:#667085;">{{ $gift->coins }} coins each</div>
          </div>
          <div style="text-align:right;">
            <div style="font-size:15px;font-weight:800;color:#7F56D9;">{{ number_format($gift->total_sent ?? 0) }}</div>
            <div style="font-size:10px;color:#667085;">sent</div>
          </div>
        </div>
        @endforeach
        @if($topGifts->isEmpty())
          <div style="padding:30px;text-align:center;color:#667085;font-size:13px;">No gift data yet</div>
        @endif
      </div>
    </div>
  </div>

  {{-- ── Recent ended lives ── --}}
  <div class="lv-section">
    <div class="lv-hdr">
      <div class="lv-hdr-title">Recent Ended Lives</div>
      <a href="{{ route('admin.live.history') }}?status=ended" style="font-size:12px;color:#1570EF;font-weight:600;text-decoration:none;">View All</a>
    </div>
    <table class="lv-table">
      <thead><tr>
        <th>Host</th><th>Title</th><th>Peak Viewers</th><th>Duration</th><th>Ended</th><th>Actions</th>
      </tr></thead>
      <tbody>
      @foreach($recentEnded as $room)
        @php
          $host = $room->host;
          $durSecs = $room->ended_at && $room->created_at ? $room->created_at->diffInSeconds($room->ended_at) : 0;
          $durStr  = $durSecs >= 3600 ? floor($durSecs/3600).'h '.floor(($durSecs%3600)/60).'m' : floor($durSecs/60).'m '.($durSecs%60).'s';
        @endphp
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:9px;">
              <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#07003B,#FF8A00);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:12px;">
                {{ strtoupper(substr($host?->name ?? '?', 0, 1)) }}
              </div>
              <div>
                <div style="font-weight:700;font-size:13px;">{{ $host?->name }}</div>
                <div style="font-size:11px;color:#667085;">@{{ $host?->communityProfile?->username ?? '-' }}</div>
              </div>
            </div>
          </td>
          <td style="font-size:13px;color:#101828;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $room->title }}</td>
          <td style="font-weight:700;color:#7F56D9;">{{ $room->peak_viewers }}</td>
          <td style="font-size:12px;color:#667085;">{{ $durStr }}</td>
          <td style="font-size:12px;color:#667085;">{{ $room->ended_at?->diffForHumans() ?? '-' }}</td>
          <td>
            <a href="{{ route('admin.live.detail', $room->id) }}" class="lv-action-btn btn-detail">
              <i class="fas fa-eye"></i> Detail
            </a>
          </td>
        </tr>
      @endforeach
      @if($recentEnded->isEmpty())
        <tr><td colspan="6" style="text-align:center;color:#667085;padding:30px;">No ended lives yet</td></tr>
      @endif
      </tbody>
    </table>
  </div>

</div>
@endsection
