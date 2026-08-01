@extends('admin.layouts.app')
@section('title', 'eMarry — All Interests')

@push('styles')
<style>
:root { --accent:#FF8A00; --green:#16A34A; --rose:#E11D48; --amber:#D97706; --radius:14px; --shadow:0 2px 12px rgba(0,0,0,.07); }
.em-page { padding:28px 32px; background:#F8F9FC; min-height:100vh; }
.back-link { display:inline-flex; align-items:center; gap:6px; color:#8A8A9A; font-size:13px; font-weight:600; text-decoration:none; margin-bottom:20px; }
.back-link:hover { color:var(--accent); }
.em-title { font-size:22px; font-weight:900; color:#1a1a2e; margin-bottom:20px; }
.em-filter { background:#fff; border-radius:var(--radius); padding:14px 18px; box-shadow:var(--shadow); margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
.em-filter a { padding:7px 16px; border-radius:20px; font-size:13px; font-weight:700; text-decoration:none; }
.em-filter a.active { background:var(--accent); color:#fff; }
.em-filter a:not(.active) { background:#F5F5F7; color:#555; }
.em-table-wrap { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }
.em-table { width:100%; border-collapse:collapse; }
.em-table thead th { background:#FAFAFA; font-size:11px; font-weight:800; color:#8A8A9A; text-transform:uppercase; letter-spacing:.5px; padding:12px 16px; border-bottom:1.5px solid #F0F0F0; text-align:left; }
.em-table tbody tr { border-bottom:1px solid #F5F5F7; }
.em-table tbody tr:hover { background:#FAFBFF; }
.em-table td { padding:13px 16px; font-size:13.5px; vertical-align:middle; }
.badge-pending  { background:#FFF7ED; color:#C2410C; border:1px solid #FED7AA; border-radius:20px; padding:3px 12px; font-size:12px; font-weight:700; }
.badge-accepted { background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; border-radius:20px; padding:3px 12px; font-size:12px; font-weight:700; }
.badge-rejected { background:#FEF2F2; color:#7F1D1D; border:1px solid #FECACA; border-radius:20px; padding:3px 12px; font-size:12px; font-weight:700; }
.em-pagination { padding:14px 20px; display:flex; align-items:center; justify-content:space-between; border-top:1px solid #F0F0F0; font-size:13px; color:#8A8A9A; }
</style>
@endpush

@section('content')
<div class="em-page">
  <a href="{{ route('admin.emarry.index') }}" class="back-link">← Back to eMarry</a>
  <div class="em-title">💌 All Interests</div>

  <div class="em-filter">
    <span style="font-size:13px;font-weight:700;color:#8A8A9A;">Filter:</span>
    <a href="{{ route('admin.emarry.interests') }}" class="{{ !request('status') ? 'active' : '' }}">All</a>
    <a href="{{ route('admin.emarry.interests', ['status'=>'pending']) }}" class="{{ request('status')==='pending' ? 'active' : '' }}">⏳ Pending</a>
    <a href="{{ route('admin.emarry.interests', ['status'=>'accepted']) }}" class="{{ request('status')==='accepted' ? 'active' : '' }}">💍 Matched</a>
    <a href="{{ route('admin.emarry.interests', ['status'=>'rejected']) }}" class="{{ request('status')==='rejected' ? 'active' : '' }}">✗ Declined</a>
  </div>

  <div class="em-table-wrap">
    @if($interests->isEmpty())
      <div style="text-align:center;padding:50px;color:#8A8A9A;font-size:15px;font-weight:600;">No interests found.</div>
    @else
    <table class="em-table">
      <thead>
        <tr>
          <th>From (Sender)</th>
          <th>To (Receiver)</th>
          <th>Message</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        @foreach($interests as $i)
        <tr>
          <td style="font-weight:700;">{{ $i->sender_name }}</td>
          <td style="font-weight:700;">{{ $i->receiver_name }}</td>
          <td style="color:#8A8A9A;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            {{ $i->message ?: '—' }}
          </td>
          <td>
            @if($i->status==='accepted')
              <span class="badge-accepted">💍 Matched</span>
            @elseif($i->status==='rejected')
              <span class="badge-rejected">Declined</span>
            @else
              <span class="badge-pending">Pending</span>
            @endif
          </td>
          <td style="color:#8A8A9A;">{{ \Carbon\Carbon::parse($i->created_at)->format('d M Y H:i') }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    <div class="em-pagination">
      <span>{{ $interests->total() }} interests</span>
      {{ $interests->links() }}
    </div>
    @endif
  </div>
</div>
@endsection
