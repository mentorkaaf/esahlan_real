@extends('admin.layouts.app')
@section('title', 'Cart Notification Templates')

@push('styles')
<style>
.mod-card { background:#fff;border-radius:14px;border:1px solid #e5e7eb;margin-bottom:28px;overflow:hidden; }
.mod-header { padding:14px 20px;display:flex;align-items:center;gap:10px;border-bottom:2px solid #f3f4f6;background:#fafafa; }
.mod-emoji { font-size:22px; }
.mod-name { font-weight:800;font-size:16px;color:#111; }
.stage-block { padding:18px 20px;border-bottom:1px solid #f3f4f6; }
.stage-block:last-child { border-bottom:none; }
.stage-top { display:flex;align-items:center;justify-content:space-between;margin-bottom:12px; }
.stage-badge { display:inline-block;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700; }
.badge-30min { background:#fef9c3;color:#92400e; }
.badge-2h    { background:#ffedd5;color:#c2410c; }
.badge-24h   { background:#fee2e2;color:#991b1b; }
.stage-actions { display:flex;align-items:center;gap:12px; }
.tmpl-fields { display:grid;grid-template-columns:1fr 2fr;gap:12px;margin-bottom:10px; }
.tmpl-input { width:100%;border:1.5px solid #e5e7eb;border-radius:8px;padding:9px 12px;font-size:13px;font-family:inherit;box-sizing:border-box;transition:border-color .15s; }
.tmpl-input:focus { outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(255,107,53,.08); }
textarea.tmpl-input { resize:vertical;min-height:60px; }
.save-btn { background:#ff6b35;color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;white-space:nowrap; }
.save-btn:hover { background:#e85a24; }
.toggle { width:46px;height:26px;background:#d1d5db;border-radius:13px;position:relative;cursor:pointer;border:none;transition:background .2s;flex-shrink:0;appearance:none;-webkit-appearance:none; }
.toggle.on { background:#ff6b35; }
.toggle::after { content:'';position:absolute;width:22px;height:22px;background:#fff;border-radius:50%;top:2px;left:2px;transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.25); }
.toggle.on::after { left:22px; }
.toggle-label { font-size:12px;font-weight:600;color:#6b7280; }
.var-hint { font-size:11px;color:#9ca3af;margin-top:6px; }
.var-hint code { background:#f3f4f6;padding:1px 5px;border-radius:4px;font-size:10px; }
</style>
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Cart Notification Templates</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li>Notifications</li>
            <li>Cart Templates</li>
        </ul>
    </div>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:#15803d;font-weight:600;font-size:13px;">
    <i class="fa-solid fa-check-circle" style="margin-right:6px;"></i>{{ session('success') }}
</div>
@endif

<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 18px;margin-bottom:24px;font-size:13px;color:#1e40af;">
    <strong>Available Variables:</strong>&nbsp;
    <code style="background:#dbeafe;padding:2px 7px;border-radius:5px;font-size:12px;">@{{product_name}}</code> First item name &nbsp;·&nbsp;
    <code style="background:#dbeafe;padding:2px 7px;border-radius:5px;font-size:12px;">@{{extra_items}}</code> e.g. " + 2 more" &nbsp;·&nbsp;
    <code style="background:#dbeafe;padding:2px 7px;border-radius:5px;font-size:12px;">@{{total}}</code> Cart total price
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
<div class="mod-card">
    <div class="mod-header">
        <span class="mod-emoji">{{ $moduleInfo['emoji'] }}</span>
        <span class="mod-name">{{ $moduleInfo['label'] }}</span>
    </div>

    @foreach($stages as $stageKey => $stageLabel)
    @php $tpl = $moduleTpls[$stageKey] ?? null; $isActive = $tpl?->is_active ?? true; @endphp
    <div class="stage-block">
        <div class="stage-top">
            <span class="stage-badge {{ $stageBadge[$stageKey] }}">{{ $stageLabel }}</span>
            <div class="stage-actions">
                <span class="toggle-label" id="lbl-{{ $moduleKey }}-{{ $stageKey }}">{{ $isActive ? 'Active' : 'Inactive' }}</span>
                <button type="button"
                        id="tog-{{ $moduleKey }}-{{ $stageKey }}"
                        class="toggle {{ $isActive ? 'on' : '' }}"
                        onclick="toggleActive(this,'{{ $moduleKey }}','{{ $stageKey }}')"
                        title="Toggle active">
                </button>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.notifications.cart-templates.update') }}">
            @csrf
            <input type="hidden" name="module" value="{{ $moduleKey }}">
            <input type="hidden" name="stage"  value="{{ $stageKey }}">

            <div class="tmpl-fields">
                <div>
                    <label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:5px;">Title</label>
                    <input type="text" name="title" class="tmpl-input"
                           value="{{ $tpl?->title ?? '' }}"
                           placeholder="Notification title..." required>
                </div>
                <div>
                    <label style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:5px;">Body Message</label>
                    <textarea name="body" class="tmpl-input" rows="2"
                              placeholder="Notification body message..." required>{{ $tpl?->body ?? '' }}</textarea>
                </div>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div class="var-hint">
                    Variables: <code>@{{product_name}}</code> <code>@{{extra_items}}</code> <code>@{{total}}</code>
                </div>
                <button type="submit" class="save-btn">
                    <i class="fa-solid fa-floppy-disk"></i> Save
                </button>
            </div>
        </form>
    </div>
    @endforeach
</div>
@endforeach

@push('scripts')
<script>
function toggleActive(btn, module, stage) {
    btn.classList.toggle('on');
    const isActive = btn.classList.contains('on');
    const lbl = document.getElementById('lbl-' + module + '-' + stage);
    if (lbl) lbl.textContent = isActive ? 'Active' : 'Inactive';

    fetch('{{ route('admin.notifications.cart-templates.toggle') }}', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
        body: JSON.stringify({module, stage, is_active: isActive}),
    });
}
</script>
@endpush

@endsection
