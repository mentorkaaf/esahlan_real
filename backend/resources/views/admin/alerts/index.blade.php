@extends('admin.layouts.app')
@section('title', 'Email Alert Settings')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">📧 Email Alert Settings</h1>
        <ul class="breadcrumb"><li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li>Alert Settings</li></ul>
    </div>
    <div style="display:flex;gap:10px;">
        <form action="{{ route('admin.alerts.test') }}" method="POST" style="margin:0;">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm">
                <i class="fas fa-paper-plane"></i> Send Test Email
            </button>
        </form>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:16px;padding:12px 16px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;color:#065f46;font-weight:600;">
    <i class="fas fa-check-circle"></i> {{ session('success') }}
</div>
@endif

{{-- Stats row --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px;">
    <div class="card" style="padding:20px;text-align:center;">
        <div style="font-size:28px;font-weight:800;color:var(--brand);">{{ $stats['total_sent'] }}</div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Total Alerts Sent</div>
    </div>
    <div class="card" style="padding:20px;text-align:center;">
        <div style="font-size:28px;font-weight:800;color:var(--success);">{{ $stats['today'] }}</div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Sent Today</div>
    </div>
    <div class="card" style="padding:20px;text-align:center;">
        <div style="font-size:28px;font-weight:800;color:{{ $stats['total_failed'] > 0 ? 'var(--danger)' : 'var(--text-muted)' }};">{{ $stats['total_failed'] }}</div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Failed</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start;">

{{-- Alert toggles --}}
<div>
@php
  $categoryMeta = [
    'orders'   => ['icon' => '🛒', 'label' => 'Orders',          'color' => '#FF8A00'],
    'vendors'  => ['icon' => '🏪', 'label' => 'Vendors & Products', 'color' => '#7C3AED'],
    'users'    => ['icon' => '👤', 'label' => 'Users & Agents',   'color' => '#059669'],
    'security' => ['icon' => '🔐', 'label' => 'Security',         'color' => '#DC2626'],
    'server'   => ['icon' => '🖥️', 'label' => 'Server Health',    'color' => '#1D4ED8'],
  ];
@endphp

@foreach($categoryMeta as $catKey => $meta)
@if(isset($settings[$catKey]) && $settings[$catKey]->count())
<div class="card" style="margin-bottom:16px;">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:{{ $meta['color'] }}20;color:{{ $meta['color'] }};">
                <span style="font-size:18px;">{{ $meta['icon'] }}</span>
            </div>
            {{ $meta['label'] }} Alerts
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        @foreach($settings[$catKey] as $s)
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--border);">
            <div style="flex:1;margin-right:16px;">
                <div style="font-weight:700;font-size:14px;color:var(--text);">{{ $s->label }}</div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">{{ $s->description }}</div>
            </div>
            <label class="toggle" style="flex-shrink:0;">
                <input type="checkbox" class="alert-toggle" data-key="{{ $s->key }}"
                    {{ $s->is_enabled ? 'checked' : '' }}>
                <span class="toggle-slider"></span>
            </label>
        </div>
        @endforeach
    </div>
</div>
@endif
@endforeach
</div>

{{-- Sidebar: email config + recent logs --}}
<div>
    {{-- Email config --}}
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header">
            <div class="card-header-title">
                <div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);"><i class="fas fa-envelope"></i></div>
                Alert Recipients
            </div>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.alerts.save-email') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label">Admin Email(s)</label>
                    <input type="text" name="admin_alert_email" class="form-control"
                        value="{{ $adminEmail }}"
                        placeholder="admin@esahlan.com, ceo@esahlan.com">
                    <small style="color:var(--text-muted);font-size:11px;margin-top:4px;display:block;">
                        Separate multiple emails with commas.
                    </small>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="fas fa-save"></i> Save Email
                </button>
            </form>
        </div>
    </div>

    {{-- Recent logs --}}
    <div class="card">
        <div class="card-header" style="justify-content:space-between;">
            <div class="card-header-title">
                <div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);"><i class="fas fa-history"></i></div>
                Recent Alert Log
            </div>
            <form action="{{ route('admin.alerts.clear-logs') }}" method="POST" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline" onclick="return confirm('Clear all logs?')" style="font-size:11px;">
                    Clear
                </button>
            </form>
        </div>
        <div style="max-height:400px;overflow-y:auto;">
            @forelse($logs as $log)
            <div style="padding:10px 16px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;gap:10px;">
                <span style="font-size:18px;flex-shrink:0;">
                    @switch($log->alert_key)
                        @case('new_order') 🛒 @break
                        @case('new_vendor') 🏪 @break
                        @case('new_agent') 🚴 @break
                        @case('new_product') 📦 @break
                        @case('low_stock') ⚠️ @break
                        @case('failed_login_spike') 🚨 @break
                        @case('admin_login') 🔐 @break
                        @case('high_cpu') 🔥 @break
                        @case('storage_critical') 🆘 @break
                        @case('storage_warning') 💿 @break
                        @case('queue_failed') ⚙️ @break
                        @default 🔔
                    @endswitch
                </span>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:12px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        {{ $log->subject }}
                    </div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:1px;">
                        {{ \Carbon\Carbon::parse($log->sent_at)->diffForHumans() }}
                        &nbsp;·&nbsp;
                        <span style="color:{{ $log->status === 'sent' ? 'var(--success)' : 'var(--danger)' }};">
                            {{ $log->status }}
                        </span>
                    </div>
                </div>
            </div>
            @empty
            <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;">
                No alerts sent yet.
            </div>
            @endforelse
        </div>
    </div>
</div>

</div>

@push('scripts')
<script>
document.querySelectorAll('.alert-toggle').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
        const key = this.dataset.key;
        const enabled = this.checked;
        fetch('{{ route("admin.alerts.toggle", "__KEY__") }}'.replace('__KEY__', key), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ enabled })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                this.checked = !enabled; // revert on failure
                alert('Failed to update setting.');
            }
        })
        .catch(() => {
            this.checked = !enabled;
            alert('Network error.');
        });
    });
});
</script>
@endpush

@endsection
