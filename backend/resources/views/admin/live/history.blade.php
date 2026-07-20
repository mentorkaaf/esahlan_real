@extends('admin.layouts.app')
@section('title', 'Live History')

@push('styles')
<style>
.lv-page { background:#F8F9FC; min-height:100vh; padding:28px 32px; }
.lv-section { background:#fff; border-radius:18px; border:1px solid #EAECF0; box-shadow:0 1px 3px rgba(16,24,40,.06); overflow:hidden; }
.lv-hdr { padding:18px 24px; border-bottom:1px solid #F2F4F7; display:flex; align-items:center; justify-content:space-between; }
.lv-table { width:100%; border-collapse:collapse; }
.lv-table th { padding:10px 20px; font-size:11px; font-weight:700; color:#667085; text-transform:uppercase; letter-spacing:.6px; background:#F9FAFB; border-bottom:1px solid #F2F4F7; text-align:left; }
.lv-table td { padding:13px 20px; border-bottom:1px solid #F9FAFB; vertical-align:middle; }
.lv-table tr:last-child td { border-bottom:none; }
.lv-table tr:hover td { background:#FAFAFA; }
.filter-input { border:1px solid #D0D5DD; border-radius:8px; padding:8px 12px; font-size:13px; outline:none; }
.filter-input:focus { border-color:#FF8A00; box-shadow:0 0 0 3px rgba(255,138,0,.1); }
.btn-filter { background:#FF8A00; color:#fff; border:none; border-radius:8px; padding:8px 16px; font-size:13px; font-weight:700; cursor:pointer; }
.status-live   { background:#FEF3F2; color:#B42318; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; }
.status-ended  { background:#F2F4F7; color:#667085; font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; }
.av-dot { width:7px; height:7px; background:#12B76A; border-radius:50%; display:inline-block; margin-right:4px; }
.lv-action-btn { padding:5px 12px; border-radius:8px; font-size:12px; font-weight:700; border:none; cursor:pointer; transition:all .15s; }
.btn-end   { background:#FEE4E2; color:#B42318; }
.btn-detail { background:#EFF8FF; color:#1570EF; text-decoration:none; }
</style>
@endpush

@section('content')
<div class="lv-page">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
    <a href="{{ route('admin.live.index') }}" style="color:#667085;font-size:20px;"><i class="fas fa-arrow-left"></i></a>
    <div>
      <h1 style="font-size:22px;font-weight:900;color:#101828;margin:0;">Live History</h1>
      <p style="color:#667085;font-size:13px;margin:3px 0 0;">All live rooms â€” active and ended</p>
    </div>
  </div>

  @if(session('success'))
    <div style="background:#ECFDF3;border:1px solid #A9EFC5;color:#027A48;padding:12px 18px;border-radius:10px;margin-bottom:16px;font-weight:600;font-size:13px;">
      <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
  @endif

  {{-- Filters --}}
  <form method="GET" style="background:#fff;border-radius:14px;border:1px solid #EAECF0;padding:16px 20px;margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
    <div style="flex:1;min-width:180px;">
      <label style="font-size:11px;font-weight:700;color:#667085;display:block;margin-bottom:4px;">SEARCH</label>
      <input class="filter-input" style="width:100%;" name="search" value="{{ request('search') }}" placeholder="Host name or title...">
    </div>
    <div>
      <label style="font-size:11px;font-weight:700;color:#667085;display:block;margin-bottom:4px;">STATUS</label>
      <select class="filter-input" name="status">
        <option value="">All</option>
        <option value="live" {{ request('status')=='live'?'selected':'' }}>Live</option>
        <option value="ended" {{ request('status')=='ended'?'selected':'' }}>Ended</option>
      </select>
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
    <a href="{{ route('admin.live.history') }}" style="color:#667085;font-size:13px;font-weight:600;padding:8px 12px;text-decoration:none;">Reset</a>
  </form>

  <div class="lv-section">
    <div class="lv-hdr">
      <div style="font-size:14px;font-weight:800;color:#101828;">{{ $rooms->total() }} rooms</div>
    </div>
    <table class="lv-table">
      <thead><tr>
        <th>Host</th><th>Title</th><th>Status</th><th>Peak</th><th>Duration</th><th>Started</th><th>Actions</th>
      </tr></thead>
      <tbody>
      @foreach($rooms as $room)
        @php
          $host    = $room->host;
          $durSecs = $room->ended_at && $room->created_at ? $room->created_at->diffInSeconds($room->ended_at) : 0;
          $durStr  = $durSecs > 0 ? (floor($durSecs/60).'m '.($durSecs%60).'s') : 'â€”';
        @endphp
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:9px;">
              <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#07003B,#FF8A00);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:12px;flex-shrink:0;">
                {{ strtoupper(substr($host?->name ?? '?', 0, 1)) }}
              </div>
              <div>
                <div style="font-weight:700;font-size:13px;">{{ $host?->name }}</div>
                <div style="font-size:11px;color:#667085;">&#64;{{ $host?->communityProfile?->username ?? '-' }}</div>
              </div>
            </div>
          </td>
          <td style="font-size:13px;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $room->title }}</td>
          <td>
            @if($room->status === 'live')
              <span class="status-live"><span class="av-dot"></span>LIVE</span>
            @else
              <span class="status-ended">Ended</span>
            @endif
          </td>
          <td style="font-weight:700;color:#7F56D9;">{{ $room->peak_viewers }}</td>
          <td style="font-size:12px;color:#667085;">{{ $durStr }}</td>
          <td style="font-size:12px;color:#667085;">{{ $room->created_at->format('M d, H:i') }}</td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <a href="{{ route('admin.live.detail', $room->id) }}" class="lv-action-btn btn-detail">
                <i class="fas fa-eye"></i>
              </a>
              @if($room->status === 'live')
                <form method="POST" action="{{ route('admin.live.force-end', $room->id) }}"
                      onsubmit="return confirm('Force-end this live?')">
                  @csrf
                  <button class="lv-action-btn btn-end" type="submit"><i class="fas fa-stop"></i></button>
                </form>
              @endif
              <form method="POST" action="{{ route('admin.live.users.toggle-ban', $host?->id) }}">
                @csrf
                <button class="lv-action-btn" type="submit" title="{{ $host?->banned_from_live ? 'Unban' : 'Ban' }}"
                  style="background:{{ $host?->banned_from_live ? '#ECFDF3' : '#FFF4ED' }};color:{{ $host?->banned_from_live ? '#027A48' : '#B54708' }}">
                  <i class="fas fa-{{ $host?->banned_from_live ? 'check' : 'ban' }}"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
      @endforeach
      @if($rooms->isEmpty())
        <tr><td colspan="7" style="text-align:center;color:#667085;padding:40px;">No live rooms found</td></tr>
      @endif
      </tbody>
    </table>
    <div style="padding:16px 20px;">{{ $rooms->links() }}</div>
  </div>
</div>
@endsection

