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

{{-- Stats Row --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:24px;">
    <div class="card" style="margin:0;padding:20px 24px;display:flex;align-items:center;gap:16px;">
        <div style="width:48px;height:48px;border-radius:14px;background:rgba(59,130,246,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-paper-plane" style="color:var(--info);font-size:18px;"></i>
        </div>
        <div>
            <div style="font-size:28px;font-weight:900;color:var(--text);">{{ $totalSent }}</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">Total Sent</div>
        </div>
    </div>
    <div class="card" style="margin:0;padding:20px 24px;display:flex;align-items:center;gap:16px;">
        <div style="width:48px;height:48px;border-radius:14px;background:rgba(16,185,129,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-calendar-day" style="color:var(--success);font-size:18px;"></i>
        </div>
        <div>
            <div style="font-size:28px;font-weight:900;color:var(--text);">{{ $sentToday }}</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">Sent Today</div>
        </div>
    </div>
    <div class="card" style="margin:0;padding:20px 24px;display:flex;align-items:center;gap:16px;">
        <div style="width:48px;height:48px;border-radius:14px;background:rgba(249,115,22,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-mobile-alt" style="color:var(--brand);font-size:18px;"></i>
        </div>
        <div>
            <div style="font-size:28px;font-weight:900;color:var(--text);">{{ $activeDevices }}</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">Active Devices</div>
        </div>
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
                Send Push Notification
            </div>
        </div>
        <div class="card-body">
            @if(session('success'))
            <div class="alert alert-success" style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);color:var(--success);padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13px;">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
            </div>
            @endif

            <form method="POST" action="{{ route('admin.notifications.send') }}" enctype="multipart/form-data">
                @csrf

                {{-- Target Audience --}}
                <div class="form-group">
                    <label class="form-label">Target Audience</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;" id="targetGrid">
                        @foreach([
                            ['all','fa-globe','All Users'],
                            ['customers','fa-user','Customers'],
                            ['vendors','fa-store','Vendors'],
                            ['deliverymen','fa-motorcycle','Deliverymen'],
                            ['specific','fa-crosshairs','Specific User'],
                        ] as [$val,$icon,$label])
                        <label style="cursor:pointer;">
                            <input type="radio" name="target_type" value="{{ $val }}" {{ $val==='all'?'checked':'' }} style="display:none;" onchange="onTargetChange('{{ $val }}')">
                            <div class="target-btn {{ $val==='all'?'target-active':'' }}" data-val="{{ $val }}"
                                 style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;border:1.5px solid var(--border);background:var(--bg);font-size:13px;font-weight:600;color:var(--text-muted);transition:all 0.2s;">
                                <i class="fas {{ $icon }}" style="font-size:14px;"></i> {{ $label }}
                            </div>
                        </label>
                        @endforeach
                    </div>
                    <input type="hidden" name="target_type" id="hiddenTarget" value="all">
                </div>

                <div class="form-group" id="specificUserField" style="display:none;">
                    <label class="form-label">User ID or Name</label>
                    <input type="text" name="target_id" id="targetIdInput" class="form-control" placeholder="Enter user ID">
                </div>

                <div class="form-group">
                    <label class="form-label">Notification Title <span style="color:var(--danger);">*</span></label>
                    <input type="text" name="title" id="inpTitle" class="form-control" required placeholder="e.g. New Offer Available!">
                </div>

                <div class="form-group">
                    <label class="form-label">Message <span style="color:var(--danger);">*</span></label>
                    <textarea name="body" id="inpBody" class="form-control" rows="3" required placeholder="Write your notification message here…"></textarea>
                </div>

                {{-- Banner Image --}}
                <div class="form-group">
                    <label class="form-label">Banner Image <span style="color:var(--text-muted);font-weight:400;">(optional — shown inside notification)</span></label>
                    <div id="dropZone" onclick="document.getElementById('bannerFile').click()"
                         style="border:2px dashed var(--border);border-radius:12px;padding:28px;text-align:center;cursor:pointer;background:var(--bg);transition:border-color 0.2s;">
                        <i class="fas fa-image" style="font-size:24px;color:var(--text-muted);display:block;margin-bottom:8px;"></i>
                        <div style="font-size:13px;color:var(--text-muted);">Click or drag to upload banner image</div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">jpg, png — max 2MB</div>
                        <img id="bannerPreview" src="" style="display:none;max-height:100px;margin-top:12px;border-radius:8px;">
                    </div>
                    <input type="file" id="bannerFile" name="banner_image" accept="image/*" style="display:none;" onchange="previewBanner(this)">
                </div>

                {{-- Open Screen --}}
                <div class="form-group">
                    <label class="form-label">Open Screen When Tapped <span style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
                        @foreach([
                            ['','fa-ban','None'],
                            ['home','fa-home','Home'],
                            ['wallet','fa-wallet','Wallet'],
                            ['orders','fa-box','Orders'],
                            ['efood','fa-utensils','eFood'],
                            ['eshop','fa-shopping-bag','eShop'],
                            ['egrocery','fa-carrot','eGrocery'],
                            ['eparcel','fa-shipping-fast','eParcel'],
                            ['community','fa-users','Community'],
                        ] as [$val,$icon,$label])
                        <label style="cursor:pointer;">
                            <input type="radio" name="deep_link" value="{{ $val }}" {{ $val===''?'checked':'' }} style="display:none;" onchange="onDeepLink(this)">
                            <div class="deep-btn {{ $val===''?'deep-active':'' }}" data-val="{{ $val }}"
                                 style="display:flex;flex-direction:column;align-items:center;gap:6px;padding:10px 8px;border-radius:10px;border:1.5px solid var(--border);background:var(--bg);font-size:11px;font-weight:600;color:var(--text-muted);text-align:center;transition:all 0.2s;cursor:pointer;">
                                <i class="fas {{ $icon }}" style="font-size:16px;"></i> {{ $label }}
                            </div>
                        </label>
                        @endforeach
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
        <div class="card-header" style="justify-content:space-between;align-items:center;">
            <div class="card-header-title">
                <div class="card-header-icon" style="background:rgba(139,92,246,0.1);color:var(--purple);">
                    <i class="fas fa-history"></i>
                </div>
                Notification History
            </div>
        </div>

        {{-- Bulk action bar --}}
        <div id="bulkBar" style="display:none;background:rgba(249,115,22,0.08);border-bottom:1px solid var(--border);padding:10px 20px;display:flex;align-items:center;justify-content:space-between;">
            <span id="selectedCount" style="font-size:13px;font-weight:700;color:var(--brand);"></span>
            <div style="display:flex;gap:8px;">
                <button onclick="deleteSelected()" class="btn btn-sm" style="background:rgba(239,68,68,0.1);color:var(--danger);border:1px solid rgba(239,68,68,0.3);font-size:12px;padding:5px 14px;">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
                <button onclick="clearSelection()" class="btn btn-sm" style="background:var(--bg);border:1px solid var(--border);font-size:12px;padding:5px 14px;color:var(--text-muted);">Cancel</button>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="checkAll" onchange="toggleAll(this)" style="cursor:pointer;accent-color:var(--brand);"></th>
                        <th>Notification</th>
                        <th>Target</th>
                        <th>Devices</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr id="row-{{ $log->id }}">
                        <td>
                            <input type="checkbox" class="rowCheck" value="{{ $log->id }}" onchange="updateBulkBar()" style="cursor:pointer;accent-color:var(--brand);">
                        </td>
                        <td>
                            <div style="font-weight:700;font-size:13px;">{{ $log->title }}</div>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ Str::limit($log->body, 50) }}</div>
                            @if($log->deep_link)
                            <span style="display:inline-flex;align-items:center;gap:4px;background:rgba(59,130,246,0.1);color:var(--info);border-radius:5px;padding:2px 8px;font-size:10px;font-weight:700;margin-top:4px;">
                                <i class="fas fa-link" style="font-size:9px;"></i> /{{ $log->deep_link }}
                            </span>
                            @endif
                        </td>
                        <td>
                            @php $targets=['all'=>'All Users','customers'=>'Customers','vendors'=>'Vendors','deliverymen'=>'Deliverymen','specific'=>'Specific']; @endphp
                            <div><span class="badge badge-info">{{ $targets[$log->target_type] ?? ucfirst($log->target_type) }}</span></div>
                            @if($log->target_type === 'specific' && $log->sentBy)
                            <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">{{ $log->sentBy->name }}</div>
                            @endif
                        </td>
                        <td style="font-weight:700;font-size:14px;">{{ $log->sent_count ?? 1 }}</td>
                        <td>
                            <div style="font-size:12px;font-weight:600;">{{ $log->created_at->format('d M, H:i') }}</div>
                            @if($log->sentBy)
                            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $log->sentBy->name }}</div>
                            @endif
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;justify-content:flex-end;">
                                <form method="POST" action="{{ route('admin.notifications.resend', $log) }}" style="display:inline;" onsubmit="return confirm('Resend this notification?')">
                                    @csrf
                                    <button type="submit" title="Resend" style="background:rgba(249,115,22,0.1);color:var(--brand);border:1px solid rgba(249,115,22,0.3);padding:5px 10px;border-radius:7px;font-size:12px;cursor:pointer;">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.notifications.delete', $log) }}" style="display:inline;" onsubmit="return confirm('Delete this notification?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Delete" style="background:rgba(239,68,68,0.1);color:var(--danger);border:1px solid rgba(239,68,68,0.3);padding:5px 10px;border-radius:7px;font-size:12px;cursor:pointer;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state" style="padding:40px;">
                                <i class="fas fa-bell-slash"></i>
                                <h3>No notifications sent</h3>
                                <p>Send your first push notification using the form</p>
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
<style>
.target-active {
    border-color: var(--brand) !important;
    background: rgba(249,115,22,0.08) !important;
    color: var(--brand) !important;
}
.deep-active {
    border-color: var(--brand) !important;
    background: rgba(249,115,22,0.08) !important;
    color: var(--brand) !important;
}
</style>
<script>
// Target audience selection
function onTargetChange(val) {
    document.querySelectorAll('.target-btn').forEach(b => b.classList.remove('target-active'));
    document.querySelector('.target-btn[data-val="'+val+'"]').classList.add('target-active');
    document.getElementById('hiddenTarget').value = val;
    document.getElementById('specificUserField').style.display = val === 'specific' ? 'block' : 'none';
}
document.querySelectorAll('input[name="target_type"]').forEach(r => {
    r.addEventListener('change', () => onTargetChange(r.value));
});
// Remove hidden duplicate — use JS to submit correct value
document.querySelector('form').addEventListener('submit', function() {
    const checked = document.querySelector('input[name="target_type"]:checked');
    if (checked) document.getElementById('hiddenTarget').value = checked.value;
});

// Deep link selection
function onDeepLink(el) {
    document.querySelectorAll('.deep-btn').forEach(b => b.classList.remove('deep-active'));
    document.querySelector('.deep-btn[data-val="'+el.value+'"]').classList.add('deep-active');
}
document.querySelectorAll('input[name="deep_link"]').forEach(r => {
    r.addEventListener('change', () => onDeepLink(r));
});

// Banner image preview
function previewBanner(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('bannerPreview');
        img.src = e.target.result;
        img.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

// Bulk selection
function toggleAll(cb) {
    document.querySelectorAll('.rowCheck').forEach(c => c.checked = cb.checked);
    updateBulkBar();
}
function updateBulkBar() {
    const checked = document.querySelectorAll('.rowCheck:checked');
    const bar = document.getElementById('bulkBar');
    if (checked.length > 0) {
        bar.style.display = 'flex';
        document.getElementById('selectedCount').textContent = checked.length + ' selected';
    } else {
        bar.style.display = 'none';
        document.getElementById('checkAll').checked = false;
    }
}
function clearSelection() {
    document.querySelectorAll('.rowCheck').forEach(c => c.checked = false);
    document.getElementById('checkAll').checked = false;
    updateBulkBar();
}
function deleteSelected() {
    if (!confirm('Delete selected notifications?')) return;
    const ids = [...document.querySelectorAll('.rowCheck:checked')].map(c => c.value);
    ids.forEach(id => {
        const row = document.getElementById('row-' + id);
        if (row) {
            // Submit individual delete forms
            const form = row.querySelector('form[action*="DELETE"], form[method="POST"]:last-of-type');
            // Use fetch for bulk delete
        }
    });
    // Simple approach: remove rows visually and submit one by one via fetch
    Promise.all(ids.map(id => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
        return fetch(`/admin/notifications/${id}`, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body: `_token=${token}&_method=DELETE`
        }).then(() => {
            const row = document.getElementById('row-'+id);
            if (row) row.remove();
        });
    })).then(() => { clearSelection(); });
}
</script>
@endpush
@endsection
