@extends('admin.layouts.app')
@section('title', 'Cart Notification Templates')

@push('styles')
<style>
.module-card { background:#fff;border-radius:14px;border:1px solid #e5e7eb;margin-bottom:24px;overflow:hidden; }
.module-header { padding:14px 20px;display:flex;align-items:center;gap:10px;border-bottom:1px solid #f3f4f6; }
.module-emoji { font-size:22px; }
.module-name { font-weight:800;font-size:15px;color:#111; }
.stage-row { padding:16px 20px;border-bottom:1px solid #f9fafb;display:grid;grid-template-columns:90px 1fr 1fr 80px 50px;gap:12px;align-items:start; }
.stage-row:last-child { border-bottom:none; }
.stage-badge { display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap; }
.badge-30min { background:#fef9c3;color:#92400e; }
.badge-2h    { background:#ffedd5;color:#c2410c; }
.badge-24h   { background:#fee2e2;color:#991b1b; }
.tmpl-input { width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:8px 10px;font-size:12px;font-family:inherit;resize:vertical; }
.tmpl-input:focus { outline:none;border-color:var(--brand); }
.save-btn { background:var(--brand);color:#fff;border:none;border-radius:8px;padding:8px 14px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap; }
.save-btn:hover { opacity:.85; }
.toggle-wrap { display:flex;align-items:center;justify-content:center;padding-top:6px; }
.toggle { width:38px;height:22px;background:#e5e7eb;border-radius:11px;position:relative;cursor:pointer;transition:background .2s;border:none; }
.toggle.on { background:var(--brand); }
.toggle::after { content:'';position:absolute;width:18px;height:18px;background:#fff;border-radius:50%;top:2px;left:2px;transition:left .2s; }
.toggle.on::after { left:18px; }
.var-hint { font-size:10px;color:#aaa;margin-top:4px; }
.col-header { font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.5px;padding:8px 20px;background:#f9fafb;border-bottom:1px solid #f3f4f6;display:grid;grid-template-columns:90px 1fr 1fr 80px 50px;gap:12px; }
</style>
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Cart Notification Templates</h1>
        <ul class="breadcrumb"><li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li>Notifications</li><li>Cart Templates</li></ul>
    </div>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:#15803d;font-weight:600;font-size:13px;">
    <i class="fa-solid fa-check-circle" style="margin-right:6px;"></i>{{ session('success') }}
</div>
@endif

<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#1e40af;">
    <strong>Variables:</strong>
    <code>@{{product_name}}</code> — First item name &nbsp;|&nbsp;
    <code>@{{extra_items}}</code> — e.g. " + 2 more" &nbsp;|&nbsp;
    <code>@{{total}}</code> — Cart total price
</div>

@php
$modules = [
    'efood'    => ['label' => 'eFood',    'emoji' => '🍕'],
    'eshop'    => ['label' => 'eShop',    'emoji' => '🛍️'],
    'egrocery' => ['label' => 'eGrocery', 'emoji' => '🥦'],
    'elaundry' => ['label' => 'eLaundry', 'emoji' => '👕'],
    'eparcel'  => ['label' => 'eParcel',  'emoji' => '📦'],
    'emoving'  => ['label' => 'eMoving',  'emoji' => '🚛'],
    'erent'    => ['label' => 'eRent',    'emoji' => '🏠'],
];
$stages = ['30min' => '30 Min', '2h' => '2 Hours', '24h' => '24 Hours'];
$stageBadge = ['30min' => 'badge-30min', '2h' => 'badge-2h', '24h' => 'badge-24h'];
@endphp

@foreach($modules as $moduleKey => $moduleInfo)
@php $moduleTpls = $templates->where('module', $moduleKey)->keyBy('stage'); @endphp
<div class="module-card">
    <div class="module-header">
        <span class="module-emoji">{{ $moduleInfo['emoji'] }}</span>
        <span class="module-name">{{ $moduleInfo['label'] }}</span>
    </div>
    <div class="col-header">
        <div>Stage</div>
        <div>Title</div>
        <div>Body Message</div>
        <div>Action</div>
        <div>Active</div>
    </div>
    @foreach($stages as $stageKey => $stageLabel)
    @php $tpl = $moduleTpls[$stageKey] ?? null; @endphp
    <form method="POST" action="{{ route('admin.notifications.cart-templates.update') }}" class="stage-row">
        @csrf
        <input type="hidden" name="module" value="{{ $moduleKey }}">
        <input type="hidden" name="stage" value="{{ $stageKey }}">
        <div>
            <span class="stage-badge {{ $stageBadge[$stageKey] }}">{{ $stageLabel }}</span>
        </div>
        <div>
            <input type="text" name="title" class="tmpl-input" value="{{ $tpl?->title ?? '' }}" placeholder="Notification title..." required>
        </div>
        <div>
            <textarea name="body" class="tmpl-input" rows="2" placeholder="Notification body..." required>{{ $tpl?->body ?? '' }}</textarea>
            <div class="var-hint">Use: @{{product_name}} @{{extra_items}} @{{total}}</div>
        </div>
        <div>
            <button type="submit" class="save-btn">Save</button>
        </div>
        <div class="toggle-wrap">
            <button type="button"
                    class="toggle {{ ($tpl?->is_active ?? true) ? 'on' : '' }}"
                    onclick="toggleActive(this, '{{ $moduleKey }}', '{{ $stageKey }}')"
                    title="{{ ($tpl?->is_active ?? true) ? 'Active' : 'Inactive' }}">
            </button>
        </div>
    </form>
    @endforeach
</div>
@endforeach

@push('scripts')
<script>
function toggleActive(btn, module, stage) {
    btn.classList.toggle('on');
    const isActive = btn.classList.contains('on');
    fetch('{{ route('admin.notifications.cart-templates.toggle') }}', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
        body: JSON.stringify({module, stage, is_active: isActive}),
    });
}
</script>
@endpush

@endsection
