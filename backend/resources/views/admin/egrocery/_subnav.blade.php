@php
$egBase  = 'admin.module-data.egrocery.';
$current = Route::currentRouteName();
$links = [
    [$egBase.'index',      'fas fa-chart-line',   'Dashboard',   '#10b981'],
    [$egBase.'categories', 'fas fa-folder-tree',  'Categories',  '#3b82f6'],
    [$egBase.'products',   'fas fa-boxes-stacked','Products',    '#FF8A00'],
    [$egBase.'inventory',  'fas fa-warehouse',    'Inventory',   '#8b5cf6'],
    [$egBase.'marketing',  'fas fa-bullhorn',      'Marketing',   '#ec4899'],
    [$egBase.'orders',     'fas fa-shopping-bag', 'Orders',      '#f59e0b'],
    [$egBase.'settings',   'fas fa-sliders',      'Settings',    '#64748b'],
];
@endphp
<div style="background:#fff;border:1px solid #e8edf5;border-radius:14px;padding:6px;display:flex;gap:4px;margin-bottom:22px;box-shadow:0 1px 4px rgba(0,0,0,.06);flex-wrap:wrap;">
    @foreach($links as [$name, $icon, $label, $color])
    @php $active = str_starts_with($current ?? '', $name) || $current === $name; @endphp
    <a href="{{ route($name) }}"
       style="display:flex;align-items:center;gap:7px;padding:8px 14px;border-radius:9px;font-size:13px;font-weight:600;text-decoration:none;white-space:nowrap;transition:all .15s;
              {{ $active ? "background:{$color};color:#fff;box-shadow:0 3px 10px {$color}44;" : 'color:#64748b;' }}">
        <i class="{{ $icon }}" style="font-size:12px;{{ $active ? '' : "color:{$color};" }}"></i>
        {{ $label }}
        @if($label === 'Orders' && ($pendingOrders ?? 0) > 0)
        <span style="background:rgba(255,255,255,0.35);color:#fff;font-size:10px;font-weight:800;padding:1px 7px;border-radius:20px;">{{ $pendingOrders }}</span>
        @endif
    </a>
    @endforeach
</div>
