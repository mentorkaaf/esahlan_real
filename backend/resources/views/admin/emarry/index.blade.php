@extends('admin.layouts.app')
@section('title', 'eMarry Management')

@push('styles')
<style>
:root {
  --accent:#FF8A00; --accent2:#FF6B00; --rose:#E11D48;
  --green:#16A34A; --amber:#D97706; --blue:#2563EB;
  --radius:14px; --shadow:0 2px 12px rgba(0,0,0,.07);
}

.em-page { padding:28px 32px; background:#F8F9FC; min-height:100vh; }
.em-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.em-title { font-size:22px; font-weight:900; color:#1a1a2e; display:flex; align-items:center; gap:10px; }
.em-title span { font-size:26px; }

/* Stats */
.em-stats { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:24px; }
@media(max-width:900px){ .em-stats{ grid-template-columns:repeat(3,1fr); } }
.em-stat { background:#fff; border-radius:var(--radius); padding:18px 20px; box-shadow:var(--shadow); border-top:3px solid transparent; }
.em-stat.orange  { border-color:var(--accent); }
.em-stat.rose    { border-color:var(--rose); }
.em-stat.green   { border-color:var(--green); }
.em-stat.amber   { border-color:var(--amber); }
.em-stat.blue    { border-color:var(--blue); }
.em-stat .sv { font-size:26px; font-weight:900; color:#1a1a2e; line-height:1.1; }
.em-stat .sl { font-size:11px; font-weight:700; color:#8A8A9A; text-transform:uppercase; letter-spacing:.6px; margin-top:4px; }

/* Tab bar */
.em-tabs { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
.em-tab { padding:9px 20px; border-radius:30px; font-size:13px; font-weight:700; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:.15s; }
.em-tab.active { background:var(--accent); color:#fff; }
.em-tab:not(.active) { background:#fff; color:#555; box-shadow:var(--shadow); }
.em-tab:not(.active):hover { background:#FFF3E0; color:var(--accent); }

/* Alert */
.em-alert { border-radius:12px; padding:12px 18px; margin-bottom:20px; font-size:14px; font-weight:600; }
.em-alert.success { background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; }
.em-alert.error   { background:#FEF2F2; color:#7F1D1D; border:1px solid #FECACA; }

/* Table */
.em-table-wrap { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }
.em-table { width:100%; border-collapse:collapse; }
.em-table thead th { background:#FAFAFA; font-size:11px; font-weight:800; color:#8A8A9A; text-transform:uppercase; letter-spacing:.6px; padding:13px 16px; border-bottom:1.5px solid #F0F0F0; text-align:left; white-space:nowrap; }
.em-table tbody tr { border-bottom:1px solid #F5F5F7; transition:.15s; }
.em-table tbody tr:hover { background:#FAFBFF; }
.em-table td { padding:13px 16px; font-size:13.5px; vertical-align:middle; }

/* Profile row */
.em-avatar { width:40px; height:40px; border-radius:50%; object-fit:cover; border:2px solid #f0f0f0; }
.em-avatar-initials { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,#FF8A00,#FF4B00); color:#fff; font-weight:800; font-size:16px; display:flex; align-items:center; justify-content:center; }
.em-name { font-weight:700; color:#1a1a2e; font-size:14px; }
.em-sub  { font-size:12px; color:#8A8A9A; }

/* Badges */
.badge-pending  { background:#FFF7ED; color:#C2410C; border:1px solid #FED7AA; border-radius:20px; padding:3px 12px; font-size:12px; font-weight:700; white-space:nowrap; }
.badge-approved { background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; border-radius:20px; padding:3px 12px; font-size:12px; font-weight:700; white-space:nowrap; }
.badge-rejected { background:#FEF2F2; color:#7F1D1D; border:1px solid #FECACA; border-radius:20px; padding:3px 12px; font-size:12px; font-weight:700; white-space:nowrap; }

/* Action buttons */
.btn-approve { background:#ECFDF5; color:#065F46; border:1.5px solid #A7F3D0; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap; display:inline-flex; align-items:center; gap:5px; }
.btn-approve:hover { background:#D1FAE5; }
.btn-reject  { background:#FEF2F2; color:#7F1D1D; border:1.5px solid #FECACA; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:700; cursor:pointer; white-space:nowrap; display:inline-flex; align-items:center; gap:5px; }
.btn-reject:hover { background:#FEE2E2; }
.btn-view    { background:#EFF6FF; color:#1D4ED8; border:1.5px solid #BFDBFE; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:700; cursor:pointer; text-decoration:none; white-space:nowrap; display:inline-flex; align-items:center; gap:5px; }
.btn-view:hover { background:#DBEAFE; }
.btn-del     { background:#FFF1F2; color:#9F1239; border:1.5px solid #FECDD3; border-radius:8px; padding:6px 10px; font-size:12px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px; }
.btn-del:hover { background:#FFE4E6; }

/* Photos */
.em-photos { display:flex; gap:4px; }
.em-photo  { width:36px; height:36px; border-radius:8px; object-fit:cover; border:1.5px solid #eee; }

/* Reject modal */
.em-modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9998; align-items:center; justify-content:center; }
.em-modal-backdrop.active { display:flex; }
.em-modal { background:#fff; border-radius:20px; padding:28px 32px; width:100%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,.2); }
.em-modal h3 { font-size:17px; font-weight:900; color:#1a1a2e; margin:0 0 6px; }
.em-modal p  { font-size:13px; color:#8A8A9A; margin:0 0 16px; }
.em-modal textarea { width:100%; border:1.5px solid #E5E7EB; border-radius:10px; padding:10px 14px; font-size:13px; resize:vertical; min-height:90px; box-sizing:border-box; }
.em-modal textarea:focus { outline:none; border-color:var(--accent); }
.em-modal-actions { display:flex; gap:10px; margin-top:16px; }
.em-modal-actions .btn-cancel { flex:1; background:#f5f5f5; color:#555; border:none; border-radius:10px; padding:11px; font-weight:700; cursor:pointer; font-size:14px; }
.em-modal-actions .btn-submit { flex:2; background:var(--rose); color:#fff; border:none; border-radius:10px; padding:11px; font-weight:800; cursor:pointer; font-size:14px; }

/* Empty */
.em-empty { text-align:center; padding:60px 20px; color:#8A8A9A; }
.em-empty .em-empty-icon { font-size:48px; margin-bottom:12px; }
.em-empty p { font-size:15px; font-weight:600; }

/* Pagination */
.em-pagination { padding:16px 20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; border-top:1px solid #F0F0F0; font-size:13px; color:#8A8A9A; }
</style>
@endpush

@section('content')
<div class="em-page">

  <div class="em-header">
    <div class="em-title"><span>💍</span> eMarry Management</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="{{ route('admin.emarry.interests') }}" class="btn-view">
        <i class="fas fa-heart"></i> View All Interests
      </a>
      <a href="{{ route('admin.emarry.monetization') }}" class="btn-view" style="background:linear-gradient(135deg,#FF8A00,#ff6b00);color:#fff;border-color:transparent">
        <i class="fas fa-dollar-sign"></i> 💰 Monetization
        @php $pendingMp = \Illuminate\Support\Facades\DB::table('emarry_mobile_pay_requests')->where('status','pending')->count(); @endphp
        @if($pendingMp > 0)
        <span style="background:#ef4444;color:#fff;border-radius:10px;padding:1px 6px;font-size:10px;margin-left:4px">{{ $pendingMp }}</span>
        @endif
      </a>
    </div>
  </div>

  {{-- Alerts --}}
  @if(session('success'))<div class="em-alert success">✓ {{ session('success') }}</div>@endif
  @if(session('error'))<div class="em-alert error">✗ {{ session('error') }}</div>@endif

  {{-- Stats --}}
  <div class="em-stats">
    <div class="em-stat orange">
      <div class="sv">{{ $stats['pending'] }}</div>
      <div class="sl">⏳ Pending Review</div>
    </div>
    <div class="em-stat green">
      <div class="sv">{{ $stats['approved'] }}</div>
      <div class="sl">✓ Approved</div>
    </div>
    <div class="em-stat rose">
      <div class="sv">{{ $stats['rejected'] }}</div>
      <div class="sl">✗ Rejected</div>
    </div>
    <div class="em-stat blue">
      <div class="sv">{{ $stats['interests'] }}</div>
      <div class="sl">💌 Total Interests</div>
    </div>
    <div class="em-stat amber">
      <div class="sv">{{ $stats['matched'] }}</div>
      <div class="sl">💍 Matched Pairs</div>
    </div>
  </div>

  {{-- Status tabs --}}
  <div class="em-tabs">
    <a href="{{ route('admin.emarry.index', ['status'=>'pending']) }}"
       class="em-tab {{ $status==='pending' ? 'active' : '' }}">
      ⏳ Pending <strong>({{ $stats['pending'] }})</strong>
    </a>
    <a href="{{ route('admin.emarry.index', ['status'=>'approved']) }}"
       class="em-tab {{ $status==='approved' ? 'active' : '' }}">
      ✓ Approved
    </a>
    <a href="{{ route('admin.emarry.index', ['status'=>'rejected']) }}"
       class="em-tab {{ $status==='rejected' ? 'active' : '' }}">
      ✗ Rejected
    </a>
    <a href="{{ route('admin.emarry.index', ['status'=>'all']) }}"
       class="em-tab {{ $status==='all' ? 'active' : '' }}">
      All
    </a>
  </div>

  {{-- Table --}}
  <div class="em-table-wrap">
    @if($profiles->isEmpty())
      <div class="em-empty">
        <div class="em-empty-icon">💍</div>
        <p>No profiles with status: <strong>{{ $status }}</strong></p>
      </div>
    @else
    <table class="em-table">
      <thead>
        <tr>
          <th>User</th>
          <th>Profile</th>
          <th>Photos</th>
          <th>Status</th>
          <th>Submitted</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($profiles as $p)
        @php $photos = json_decode($p->photos ?? '[]', true) ?? []; @endphp
        <tr>
          {{-- User --}}
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              @if($p->avatar)
                <img src="{{ $p->avatar }}" class="em-avatar" alt="">
              @else
                <div class="em-avatar-initials">{{ strtoupper(substr($p->name,0,1)) }}</div>
              @endif
              <div>
                <div class="em-name">{{ $p->name }}</div>
                <div class="em-sub">{{ $p->email }}</div>
                @if($p->phone)<div class="em-sub">{{ $p->phone }}</div>@endif
              </div>
            </div>
          </td>
          {{-- Profile info --}}
          <td>
            <div style="display:flex;flex-direction:column;gap:2px;">
              <div style="font-weight:700;font-size:13px;">
                {{ ucfirst($p->gender) }}, {{ $p->age }}y
                @if($p->city) · {{ $p->city }}@endif
              </div>
              <div class="em-sub">Looking for: {{ $p->looking_for }}</div>
              @if($p->occupation)<div class="em-sub">{{ $p->occupation }}</div>@endif
              @if($p->bio)
                <div class="em-sub" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $p->bio }}">{{ $p->bio }}</div>
              @endif
            </div>
          </td>
          {{-- Photos --}}
          <td>
            <div class="em-photos">
              @forelse(array_slice($photos,0,3) as $photo)
                <a href="{{ $photo }}" target="_blank">
                  <img src="{{ $photo }}" class="em-photo" alt="">
                </a>
              @empty
                <span class="em-sub">No photos</span>
              @endforelse
              @if(count($photos)>3)
                <div style="width:36px;height:36px;border-radius:8px;background:#F3F4F6;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#6B7280;">
                  +{{ count($photos)-3 }}
                </div>
              @endif
            </div>
          </td>
          {{-- Status --}}
          <td>
            <span class="badge-{{ $p->status }}">{{ ucfirst($p->status) }}</span>
          </td>
          {{-- Date --}}
          <td>
            <div class="em-sub">{{ \Carbon\Carbon::parse($p->created_at)->format('d M Y') }}</div>
            @if($p->approved_at)
              <div class="em-sub" style="color:var(--green);">Approved {{ \Carbon\Carbon::parse($p->approved_at)->format('d M') }}</div>
            @endif
          </td>
          {{-- Actions --}}
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <a href="{{ route('admin.emarry.detail', $p->id) }}" class="btn-view">
                <i class="fas fa-eye"></i> View
              </a>
              @if($p->status !== 'approved')
              <form action="{{ route('admin.emarry.approve', $p->id) }}" method="POST" style="display:inline;">
                @csrf
                <button type="submit" class="btn-approve">
                  <i class="fas fa-check"></i> Approve
                </button>
              </form>
              @endif
              @if($p->status !== 'rejected')
              <button onclick="openReject({{ $p->id }})" class="btn-reject">
                <i class="fas fa-times"></i> Reject
              </button>
              @endif
              <form action="{{ route('admin.emarry.delete', $p->id) }}" method="POST" style="display:inline;"
                    onsubmit="return confirm('Delete this profile permanently?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn-del"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    <div class="em-pagination">
      <span>{{ $profiles->total() }} profiles</span>
      {{ $profiles->links() }}
    </div>
    @endif
  </div>

</div>

{{-- Reject modal --}}
<div class="em-modal-backdrop" id="rejectModal">
  <div class="em-modal">
    <h3>✗ Reject Profile</h3>
    <p>Optionally provide a reason that will be sent to the user.</p>
    <form id="rejectForm" method="POST">
      @csrf
      <textarea name="reason" placeholder="Reason (optional, max 500 chars)…" maxlength="500"></textarea>
      <div class="em-modal-actions">
        <button type="button" class="btn-cancel" onclick="closeReject()">Cancel</button>
        <button type="submit" class="btn-submit">Reject Profile</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
function openReject(id) {
  document.getElementById('rejectForm').action = `/admin/emarry/${id}/reject`;
  document.getElementById('rejectModal').classList.add('active');
}
function closeReject() {
  document.getElementById('rejectModal').classList.remove('active');
}
document.getElementById('rejectModal').addEventListener('click', function(e) {
  if (e.target === this) closeReject();
});
</script>
@endpush
@endsection
