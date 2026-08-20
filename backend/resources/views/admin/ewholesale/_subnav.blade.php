{{-- eWholesale sub-navigation --}}
@php
    $nav = [
        ['label'=>'Dashboard',   'route'=>'admin.module-data.wholesale.dashboard',  'icon'=>'📊'],
        ['label'=>'Suppliers',   'route'=>'admin.module-data.wholesale.suppliers',  'icon'=>'🏭'],
        ['label'=>'Buyers',      'route'=>'admin.module-data.wholesale.buyers',     'icon'=>'🏢'],
        ['label'=>'Catalog',     'route'=>'admin.module-data.wholesale.products',   'icon'=>'📦'],
        ['label'=>'RFQ Center',  'route'=>'admin.module-data.wholesale.rfq',        'icon'=>'📋'],
        ['label'=>'Orders',      'route'=>'admin.module-data.wholesale.orders',     'icon'=>'🛒'],
        ['label'=>'Reports',     'route'=>'admin.module-data.wholesale.reports',    'icon'=>'📈'],
        ['label'=>'Settings',    'route'=>'admin.module-data.wholesale.settings',   'icon'=>'⚙️'],
    ];
    $current = request()->route()->getName();
@endphp
<style>
.ew-subnav { display:flex; flex-wrap:wrap; gap:4px; padding:12px 20px; background:#1B1444; border-bottom:1px solid rgba(255,255,255,.1); }
.ew-subnav a { display:flex; align-items:center; gap:6px; padding:7px 14px; border-radius:6px; font-size:13px; font-weight:500; color:rgba(255,255,255,.75); text-decoration:none; transition:all .15s; }
.ew-subnav a:hover { background:rgba(255,255,255,.08); color:#fff; }
.ew-subnav a.active { background:#F7941D; color:#fff; }
</style>
<nav class="ew-subnav">
    @foreach($nav as $item)
        @php $active = str_starts_with($current, $item['route']) @endphp
        <a href="{{ route($item['route']) }}" class="{{ $active ? 'active' : '' }}">
            <span>{{ $item['icon'] }}</span> {{ $item['label'] }}
        </a>
    @endforeach
</nav>
