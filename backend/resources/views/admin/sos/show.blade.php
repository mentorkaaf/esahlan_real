@extends('admin.layouts.app')
@section('title', 'SOS Alert #' . $alert->id)

@push('css')
<style>
.detail-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 12px; padding: 20px 24px; margin-bottom: 16px;
}
.detail-row {
    display: flex; gap: 12px; padding: 10px 0;
    border-bottom: 1px solid var(--border); align-items: flex-start;
}
.detail-row:last-child { border-bottom: none; }
.detail-label { min-width: 130px; font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; padding-top: 2px; }
.detail-val { font-size: 14px; font-weight: 500; flex: 1; }
.badge-active { background: rgba(255,45,85,.12); color: #FF2D55; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
.badge-resolved { background: rgba(52,199,89,.12); color: #34C759; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
.badge-pending { background: rgba(255,149,0,.12); color: #FF9500; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
.map-embed { width: 100%; height: 320px; border: none; border-radius: 10px; margin-top: 12px; }
.action-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
</style>
@endpush

@section('content')

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-exclamation-triangle" style="color:#FF2D55"></i> SOS Alert #{{ $alert->id }}</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.sos.index') }}">SOS Alerts</a></li>
            <li class="breadcrumb-item active">Alert #{{ $alert->id }}</li>
        </ol>
    </div>
    <a href="{{ route('admin.sos.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Back to SOS Center
    </a>
</div>

{{-- Action bar --}}
<div class="action-bar">
    @if($alert->driver_phone)
    <a href="tel:{{ $alert->driver_phone }}" class="btn btn-success">
        <i class="fas fa-phone"></i> Call Driver
    </a>
    @endif

    @if(in_array($alert->status, ['active', 'pending']))
    <button class="btn btn-danger" onclick="resolveNow()">
        <i class="fas fa-check"></i> Mark Resolved
    </button>
    @endif

    @if($alert->latitude && $alert->longitude)
    <a href="https://maps.google.com/?q={{ $alert->latitude }},{{ $alert->longitude }}" target="_blank" class="btn btn-primary">
        <i class="fas fa-map-marker-alt"></i> Open in Google Maps
    </a>
    @endif
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">

    {{-- Driver Info --}}
    <div class="detail-card">
        <h5 style="margin:0 0 16px;font-weight:700;font-size:15px;"><i class="fas fa-user" style="color:var(--brand)"></i> Driver Info</h5>

        <div class="detail-row">
            <span class="detail-label">Name</span>
            <span class="detail-val">{{ $alert->driver_name ?? 'Driver #' . $alert->deliveryman_id }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Phone</span>
            <span class="detail-val">
                {{ $alert->driver_phone ?? '—' }}
                @if($alert->driver_phone)
                <a href="tel:{{ $alert->driver_phone }}" style="margin-left:8px;font-size:12px;"><i class="fas fa-phone"></i></a>
                @endif
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Driver ID</span>
            <span class="detail-val">#{{ $alert->deliveryman_id }}</span>
        </div>
    </div>

    {{-- Alert Info --}}
    <div class="detail-card">
        <h5 style="margin:0 0 16px;font-weight:700;font-size:15px;"><i class="fas fa-bell" style="color:#FF2D55"></i> Alert Details</h5>

        <div class="detail-row">
            <span class="detail-label">Status</span>
            <span class="detail-val">
                @if($alert->status === 'active')
                    <span class="badge-active">ACTIVE</span>
                @elseif($alert->status === 'pending')
                    <span class="badge-pending">PENDING</span>
                @else
                    <span class="badge-resolved">RESOLVED</span>
                @endif
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Alert Time</span>
            <span class="detail-val">{{ \Carbon\Carbon::parse($alert->created_at)->format('d M Y, H:i:s') }} ({{ \Carbon\Carbon::parse($alert->created_at)->diffForHumans() }})</span>
        </div>
        @if($alert->order_number)
        <div class="detail-row">
            <span class="detail-label">Order</span>
            <span class="detail-val">#{{ $alert->order_number }}</span>
        </div>
        @endif
        @if($alert->message)
        <div class="detail-row">
            <span class="detail-label">Message</span>
            <span class="detail-val" style="font-style:italic;">"{{ $alert->message }}"</span>
        </div>
        @endif
        @if($alert->resolved_by_name)
        <div class="detail-row">
            <span class="detail-label">Resolved By</span>
            <span class="detail-val" style="color:#34C759;">{{ $alert->resolved_by_name }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Resolved At</span>
            <span class="detail-val">{{ $alert->resolved_at ? \Carbon\Carbon::parse($alert->resolved_at)->format('d M Y, H:i:s') : '—' }}</span>
        </div>
        @endif
        @if($alert->admin_notes)
        <div class="detail-row">
            <span class="detail-label">Admin Notes</span>
            <span class="detail-val">{{ $alert->admin_notes }}</span>
        </div>
        @endif
    </div>
</div>

{{-- Location Map --}}
@if($alert->latitude && $alert->longitude)
<div class="detail-card">
    <h5 style="margin:0 0 4px;font-weight:700;font-size:15px;"><i class="fas fa-map-marker-alt" style="color:#FF2D55"></i> Driver Location at Alert Time</h5>
    <div style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">{{ $alert->latitude }}, {{ $alert->longitude }}</div>
    <iframe class="map-embed"
        src="https://maps.google.com/maps?q={{ $alert->latitude }},{{ $alert->longitude }}&z=16&output=embed"
        allowfullscreen loading="lazy">
    </iframe>
</div>
@else
<div class="detail-card" style="text-align:center;color:var(--text-muted);padding:30px;">
    <i class="fas fa-map-marker-slash" style="font-size:28px;opacity:.4;"></i>
    <div style="margin-top:8px;font-size:13px;">No location data available for this alert.</div>
</div>
@endif

{{-- Resolve modal (inline) --}}
@if(in_array($alert->status, ['active', 'pending']))
<div id="resolveForm" style="display:none;" class="detail-card">
    <h5 style="margin:0 0 12px;font-weight:700;">Resolve Alert</h5>
    <textarea id="resolveNotes" placeholder="Admin notes (optional)..." rows="3"
        style="width:100%;border:1px solid var(--border);border-radius:8px;padding:10px;background:var(--bg);color:var(--text);font-size:13px;resize:vertical;"></textarea>
    <div style="display:flex;gap:8px;margin-top:12px;">
        <button onclick="confirmResolve()" class="btn btn-success"><i class="fas fa-check"></i> Confirm Resolve</button>
        <button onclick="document.getElementById('resolveForm').style.display='none'" class="btn btn-outline-secondary">Cancel</button>
    </div>
</div>
@endif

@endsection

@push('js')
<script>
const CSRF = '{{ csrf_token() }}';
const ALERT_ID = {{ $alert->id }};

function resolveNow() {
    document.getElementById('resolveForm').style.display = 'block';
    document.getElementById('resolveForm').scrollIntoView({behavior:'smooth'});
}

function confirmResolve() {
    const notes = document.getElementById('resolveNotes')?.value || '';
    fetch('/admin/sos/' + ALERT_ID + '/resolve', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': CSRF},
        body: JSON.stringify({notes})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = '{{ route("admin.sos.index") }}';
        }
    })
    .catch(() => { alert('Error. Please try again.'); });
}
</script>
@endpush
