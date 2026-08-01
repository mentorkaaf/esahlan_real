@extends('admin.layouts.app')
@section('title', 'eMarry Profile — ' . $profile->name)

@push('styles')
<style>
:root { --accent:#FF8A00; --rose:#E11D48; --green:#16A34A; --radius:14px; --shadow:0 2px 12px rgba(0,0,0,.07); }
.em-page { padding:28px 32px; background:#F8F9FC; min-height:100vh; }
.card-box { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); padding:24px 28px; margin-bottom:20px; }
.back-link { display:inline-flex; align-items:center; gap:6px; color:#8A8A9A; font-size:13px; font-weight:600; text-decoration:none; margin-bottom:20px; }
.back-link:hover { color:var(--accent); }
.profile-header { display:flex; align-items:center; gap:18px; flex-wrap:wrap; }
.profile-avatar { width:72px; height:72px; border-radius:50%; object-fit:cover; border:3px solid #f0f0f0; }
.profile-avatar-initials { width:72px; height:72px; border-radius:50%; background:linear-gradient(135deg,#FF8A00,#FF4B00); color:#fff; font-size:28px; font-weight:900; display:flex; align-items:center; justify-content:center; }
.profile-name { font-size:20px; font-weight:900; color:#1a1a2e; }
.profile-sub  { font-size:13px; color:#8A8A9A; }
.badge-pending  { background:#FFF7ED; color:#C2410C; border:1px solid #FED7AA; border-radius:20px; padding:3px 12px; font-size:12px; font-weight:700; }
.badge-approved { background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; border-radius:20px; padding:3px 12px; font-size:12px; font-weight:700; }
.badge-rejected { background:#FEF2F2; color:#7F1D1D; border:1px solid #FECACA; border-radius:20px; padding:3px 12px; font-size:12px; font-weight:700; }
.info-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:14px; margin-top:20px; }
.info-item label { font-size:11px; font-weight:700; color:#8A8A9A; text-transform:uppercase; letter-spacing:.5px; }
.info-item .val  { font-size:14px; font-weight:700; color:#1a1a2e; margin-top:3px; }
.photos-row { display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
.photos-row img { width:100px; height:100px; border-radius:12px; object-fit:cover; border:2px solid #eee; cursor:pointer; transition:.15s; }
.photos-row img:hover { transform:scale(1.04); }
.section-title { font-size:15px; font-weight:800; color:#1a1a2e; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
.interest-table { width:100%; border-collapse:collapse; }
.interest-table th { background:#FAFAFA; font-size:11px; font-weight:800; color:#8A8A9A; text-transform:uppercase; padding:10px 14px; border-bottom:1.5px solid #F0F0F0; text-align:left; }
.interest-table td { padding:10px 14px; font-size:13px; border-bottom:1px solid #F5F5F7; }
.actions-row { display:flex; gap:10px; flex-wrap:wrap; }
.btn-approve { background:#ECFDF5; color:#065F46; border:1.5px solid #A7F3D0; border-radius:8px; padding:9px 20px; font-size:13px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-reject  { background:#FEF2F2; color:#7F1D1D; border:1.5px solid #FECACA; border-radius:8px; padding:9px 20px; font-size:13px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
</style>
@endpush

@section('content')
<div class="em-page">
  <a href="{{ route('admin.emarry.index') }}" class="back-link">← Back to eMarry</a>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  {{-- Profile card --}}
  <div class="card-box">
    <div class="profile-header">
      @if($profile->avatar)
        <img src="{{ $profile->avatar }}" class="profile-avatar" alt="">
      @else
        <div class="profile-avatar-initials">{{ strtoupper(substr($profile->name,0,1)) }}</div>
      @endif
      <div>
        <div class="profile-name">{{ $profile->name }}</div>
        <div class="profile-sub">{{ $profile->email }} @if($profile->phone) · {{ $profile->phone }}@endif</div>
        <div style="margin-top:8px;">
          <span class="badge-{{ $profile->status }}">{{ ucfirst($profile->status) }}</span>
          @if($profile->is_visible)
            <span style="margin-left:8px;font-size:12px;color:#065F46;font-weight:700;">👁 Visible</span>
          @endif
        </div>
      </div>
      <div style="margin-left:auto;display:flex;gap:10px;flex-wrap:wrap;">
        @if($profile->status !== 'approved')
        <form action="{{ route('admin.emarry.approve', $profile->id) }}" method="POST">
          @csrf
          <button type="submit" class="btn-approve"><i class="fas fa-check"></i> Approve</button>
        </form>
        @endif
        @if($profile->status !== 'rejected')
        <form action="{{ route('admin.emarry.reject', $profile->id) }}" method="POST">
          @csrf
          <input type="hidden" name="reason" value="">
          <button type="submit" class="btn-reject" onclick="return confirm('Reject this profile?')">
            <i class="fas fa-times"></i> Reject
          </button>
        </form>
        @endif
      </div>
    </div>

    <div class="info-grid">
      <div class="info-item"><label>Gender</label><div class="val">{{ ucfirst($profile->gender) }}</div></div>
      <div class="info-item"><label>Looking For</label><div class="val">{{ ucfirst($profile->looking_for) }}</div></div>
      <div class="info-item"><label>Age</label><div class="val">{{ $profile->age }}</div></div>
      <div class="info-item"><label>City</label><div class="val">{{ $profile->city ?: '—' }}</div></div>
      <div class="info-item"><label>Education</label><div class="val">{{ ucfirst($profile->education ?: '—') }}</div></div>
      <div class="info-item"><label>Occupation</label><div class="val">{{ $profile->occupation ?: '—' }}</div></div>
      <div class="info-item"><label>Marital Status</label><div class="val">{{ ucfirst($profile->marital_status ?: '—') }}</div></div>
      <div class="info-item"><label>Has Children</label><div class="val">{{ $profile->has_children ? 'Yes' : 'No' }}</div></div>
      <div class="info-item"><label>Fee Paid</label><div class="val">${{ number_format($profile->fee_paid,2) }}</div></div>
      <div class="info-item"><label>Submitted</label><div class="val">{{ \Carbon\Carbon::parse($profile->created_at)->format('d M Y') }}</div></div>
    </div>

    @if($profile->bio)
    <div style="margin-top:18px;">
      <div style="font-size:11px;font-weight:700;color:#8A8A9A;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Bio</div>
      <div style="font-size:14px;color:#444;line-height:1.6;">{{ $profile->bio }}</div>
    </div>
    @endif

    @php $photos = json_decode($profile->photos ?? '[]', true) ?? []; @endphp
    @if(count($photos))
    <div style="margin-top:18px;">
      <div style="font-size:11px;font-weight:700;color:#8A8A9A;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Photos ({{ count($photos) }})</div>
      <div class="photos-row">
        @foreach($photos as $photo)
          <a href="{{ $photo }}" target="_blank"><img src="{{ $photo }}" alt=""></a>
        @endforeach
      </div>
    </div>
    @endif
  </div>

  {{-- Interests sent --}}
  <div class="card-box">
    <div class="section-title">💌 Interests Sent ({{ $interests_sent->count() }})</div>
    @if($interests_sent->isEmpty())
      <p style="color:#8A8A9A;font-size:13px;">No interests sent yet.</p>
    @else
    <table class="interest-table">
      <thead><tr><th>To</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        @foreach($interests_sent as $i)
        <tr>
          <td>{{ $i->receiver_name }}</td>
          <td><span class="badge-{{ $i->status }}">{{ ucfirst($i->status) }}</span></td>
          <td style="color:#8A8A9A;">{{ \Carbon\Carbon::parse($i->created_at)->format('d M Y') }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
  </div>

  {{-- Interests received --}}
  <div class="card-box">
    <div class="section-title">💌 Interests Received ({{ $interests_received->count() }})</div>
    @if($interests_received->isEmpty())
      <p style="color:#8A8A9A;font-size:13px;">No interests received yet.</p>
    @else
    <table class="interest-table">
      <thead><tr><th>From</th><th>Message</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        @foreach($interests_received as $i)
        <tr>
          <td>{{ $i->sender_name }}</td>
          <td style="color:#8A8A9A;max-width:200px;">{{ $i->message ?: '—' }}</td>
          <td><span class="badge-{{ $i->status }}">{{ ucfirst($i->status) }}</span></td>
          <td style="color:#8A8A9A;">{{ \Carbon\Carbon::parse($i->created_at)->format('d M Y') }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
  </div>

</div>
@endsection
