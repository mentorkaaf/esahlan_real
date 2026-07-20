@extends('admin.layouts.app')
@section('title', 'Live Reports')

@push('styles')
<style>
.lv-page { background:#F8F9FC; min-height:100vh; padding:28px 32px; }
.lv-section { background:#fff; border-radius:18px; border:1px solid #EAECF0; box-shadow:0 1px 3px rgba(16,24,40,.06); overflow:hidden; }
.lv-hdr { padding:18px 24px; border-bottom:1px solid #F2F4F7; display:flex; align-items:center; justify-content:space-between; }
.lv-hdr-title { font-size:14px; font-weight:800; color:#101828; }
.lv-table { width:100%; border-collapse:collapse; }
.lv-table th { padding:10px 20px; font-size:11px; font-weight:700; color:#667085; text-transform:uppercase; letter-spacing:.6px; background:#F9FAFB; border-bottom:1px solid #F2F4F7; text-align:left; }
.lv-table td { padding:13px 20px; border-bottom:1px solid #F9FAFB; vertical-align:middle; font-size:13px; }
.lv-table tr:last-child td { border-bottom:none; }
.lv-table tr:hover td { background:#FAFAFA; }
.badge { display:inline-block; padding:2px 9px; border-radius:100px; font-size:11px; font-weight:700; }
.badge-pending  { background:#FEF3F2; color:#B42318; }
.badge-reviewed { background:#ECFDF3; color:#027A48; }
.badge-dismissed{ background:#F2F4F7; color:#667085; }
.badge-spam     { background:#FFF4ED; color:#B54708; }
.badge-nudity   { background:#FEF3F2; color:#B42318; }
.badge-hate     { background:#FDF2FA; color:#C11574; }
.badge-violence { background:#FEF3F2; color:#912018; }
.badge-other    { background:#F2F4F7; color:#344054; }
</style>
@endpush

@section('content')
<div class="lv-page">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
    <div>
      <h1 style="font-size:22px;font-weight:900;color:#101828;margin:0;">🚩 Live Reports</h1>
      <p style="color:#667085;font-size:13px;margin:4px 0 0;">Reports submitted by viewers — {{ $pending }} pending review</p>
    </div>
  </div>

  @if(session('success'))
    <div style="background:#ECFDF3;border:1px solid #A9EFC5;color:#027A48;padding:12px 18px;border-radius:10px;margin-bottom:20px;font-weight:600;font-size:13px;">
      <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
  @endif

  {{-- Filters --}}
  <form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <select name="status" style="padding:8px 12px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px;color:#344054;" onchange="this.form.submit()">
      <option value="">All Status</option>
      <option value="pending"   {{ request('status')=='pending'   ? 'selected':'' }}>Pending</option>
      <option value="reviewed"  {{ request('status')=='reviewed'  ? 'selected':'' }}>Reviewed</option>
      <option value="dismissed" {{ request('status')=='dismissed' ? 'selected':'' }}>Dismissed</option>
    </select>
    <select name="reason" style="padding:8px 12px;border:1px solid #D0D5DD;border-radius:8px;font-size:13px;color:#344054;" onchange="this.form.submit()">
      <option value="">All Reasons</option>
      <option value="spam"        {{ request('reason')=='spam'        ? 'selected':'' }}>Spam</option>
      <option value="nudity"      {{ request('reason')=='nudity'      ? 'selected':'' }}>Nudity</option>
      <option value="hate_speech" {{ request('reason')=='hate_speech' ? 'selected':'' }}>Hate Speech</option>
      <option value="violence"    {{ request('reason')=='violence'    ? 'selected':'' }}>Violence</option>
      <option value="other"       {{ request('reason')=='other'       ? 'selected':'' }}>Other</option>
    </select>
  </form>

  <div class="lv-section">
    <div class="lv-hdr">
      <div class="lv-hdr-title">Reports ({{ $reports->total() }})</div>
    </div>
    <table class="lv-table">
      <thead><tr>
        <th>Reporter</th><th>Live Room</th><th>Host</th><th>Reason</th><th>Status</th><th>Date</th><th>Action</th>
      </tr></thead>
      <tbody>
      @forelse($reports as $report)
        @php
          $reasonClass = match($report->reason) {
            'nudity'     => 'badge-nudity',
            'hate_speech'=> 'badge-hate',
            'violence'   => 'badge-violence',
            'spam'       => 'badge-spam',
            default      => 'badge-other',
          };
          $reasonLabel = match($report->reason) {
            'hate_speech' => 'Hate Speech',
            default       => ucfirst($report->reason),
          };
        @endphp
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#07003B,#FF8A00);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:11px;">
                {{ strtoupper(substr($report->reporter?->name ?? '?', 0, 1)) }}
              </div>
              <div>
                <div style="font-weight:600;color:#101828;">{{ $report->reporter?->name }}</div>
                <div style="font-size:11px;color:#667085;">&#64;{{ $report->reporter?->communityProfile?->username ?? '-' }}</div>
              </div>
            </div>
          </td>
          <td>
            @if($report->room)
              <a href="{{ route('admin.live.detail', $report->room->id) }}" style="color:#1570EF;font-weight:600;text-decoration:none;">
                {{ Str::limit($report->room->title, 30) }}
              </a>
            @else
              <span style="color:#667085;">Deleted</span>
            @endif
          </td>
          <td style="color:#101828;font-weight:600;">{{ $report->room?->host?->name ?? '-' }}</td>
          <td><span class="badge {{ $reasonClass }}">{{ $reasonLabel }}</span></td>
          <td>
            <span class="badge badge-{{ $report->status }}">{{ ucfirst($report->status) }}</span>
          </td>
          <td style="color:#667085;white-space:nowrap;">{{ $report->created_at->diffForHumans() }}</td>
          <td>
            @if($report->status === 'pending')
              <div style="display:flex;gap:6px;">
                <form method="POST" action="{{ route('admin.live.reports.review', $report->id) }}">
                  @csrf
                  <input type="hidden" name="status" value="reviewed">
                  <button type="submit" style="padding:4px 10px;background:#ECFDF3;color:#027A48;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;">
                    <i class="fas fa-check"></i> Reviewed
                  </button>
                </form>
                <form method="POST" action="{{ route('admin.live.reports.review', $report->id) }}">
                  @csrf
                  <input type="hidden" name="status" value="dismissed">
                  <button type="submit" style="padding:4px 10px;background:#F2F4F7;color:#667085;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;">
                    <i class="fas fa-times"></i> Dismiss
                  </button>
                </form>
              </div>
            @else
              <span style="color:#667085;font-size:12px;">Done</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7" style="text-align:center;color:#667085;padding:40px;">No reports yet</td></tr>
      @endforelse
      </tbody>
    </table>
    <div style="padding:16px 20px;">
      {{ $reports->withQueryString()->links() }}
    </div>
  </div>

</div>
@endsection
