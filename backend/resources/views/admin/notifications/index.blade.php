@extends('admin.layouts.app')
@section('title', 'Push Notifications')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Push Notifications</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li>Notifications</li>
        </ul>
    </div>
</div>

<div class="grid-2" style="align-items:start;">

    {{-- Send Form --}}
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div class="card-header-title">
                <div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);">
                    <i class="fas fa-paper-plane"></i>
                </div>
                Send Notification
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.notifications.send') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">Target Audience</label>
                    <select name="target_type" class="form-control" id="targetType" onchange="toggleSpecific(this.value)">
                        <option value="all">🌐 All Users</option>
                        <option value="customers">👤 Customers Only</option>
                        <option value="vendors">🏪 Vendors Only</option>
                        <option value="deliverymen">🏍️ Deliverymen Only</option>
                        <option value="specific">🎯 Specific User</option>
                    </select>
                </div>
                <div class="form-group" id="specificUserField" style="display:none;">
                    <label class="form-label">User ID</label>
                    <input type="text" name="target_id" class="form-control" placeholder="Enter user ID">
                </div>
                <div class="form-group">
                    <label class="form-label">Notification Title <span style="color:var(--danger);">*</span></label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. New Offer Available!">
                </div>
                <div class="form-group">
                    <label class="form-label">Message Body <span style="color:var(--danger);">*</span></label>
                    <textarea name="body" class="form-control" rows="4" required placeholder="Write your notification message here…"></textarea>
                </div>

                {{-- Preview card --}}
                <div style="background:var(--bg);border-radius:10px;padding:14px;border:1px solid var(--border);margin-bottom:16px;">
                    <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:8px;">Preview</div>
                    <div style="display:flex;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,var(--brand),#ff6200);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-infinity" style="color:#fff;font-size:14px;"></i>
                        </div>
                        <div>
                            <div id="previewTitle" style="font-weight:700;font-size:13px;color:var(--text);">Notification Title</div>
                            <div id="previewBody" style="font-size:12px;color:var(--text-muted);margin-top:2px;">Message body…</div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="fas fa-paper-plane"></i> Send Notification
                </button>
            </form>
        </div>
    </div>

    {{-- Notification History --}}
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div class="card-header-title">
                <div class="card-header-icon" style="background:rgba(139,92,246,0.1);color:var(--purple);">
                    <i class="fas fa-history"></i>
                </div>
                Notification History
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Notification</th>
                        <th>Target</th>
                        <th>Sent By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td>
                            <div style="font-weight:700;font-size:13px;">{{ $log->title }}</div>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ Str::limit($log->body,60) }}</div>
                        </td>
                        <td>
                            @php
                                $targets = ['all'=>'All Users','customers'=>'Customers','vendors'=>'Vendors','deliverymen'=>'Deliverymen','specific'=>'Specific'];
                            @endphp
                            <span class="badge badge-info">{{ $targets[$log->target_type] ?? ucfirst($log->target_type) }}</span>
                        </td>
                        <td style="font-size:13px;">{{ $log->sentBy?->name ?? 'System' }}</td>
                        <td style="font-size:12px;color:var(--text-muted);">{{ $log->created_at->format('d M, H:i') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4">
                            <div class="empty-state" style="padding:40px;">
                                <i class="fas fa-bell-slash"></i>
                                <h3>No notifications sent</h3>
                                <p>Send your first push notification</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
        <div class="card-footer" style="display:flex;justify-content:center;">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function toggleSpecific(val) {
    document.getElementById('specificUserField').style.display = val === 'specific' ? 'block' : 'none';
}
// Live preview
document.querySelector('[name="title"]').addEventListener('input', e => {
    document.getElementById('previewTitle').textContent = e.target.value || 'Notification Title';
});
document.querySelector('[name="body"]').addEventListener('input', e => {
    document.getElementById('previewBody').textContent = e.target.value || 'Message body…';
});
</script>
@endpush
@endsection
