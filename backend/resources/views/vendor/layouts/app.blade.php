<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — eSahlan Vendor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand:       #FF8A00;
            --brand-dark:  #e07500;
            --navy:        #07003B;
            --sidebar-bg:  #0c0148;
            --sidebar-w:   260px;
            --sidebar-cw:  68px;
            --topbar-h:    64px;
            --radius:      12px;
            --radius-sm:   8px;
            --shadow-sm:   0 1px 3px rgba(0,0,0,0.07),0 1px 2px rgba(0,0,0,0.04);
            --shadow:      0 4px 16px rgba(0,0,0,0.09);
            --shadow-lg:   0 10px 40px rgba(0,0,0,0.14);
            --bg:          #f0f2f8;
            --surface:     #ffffff;
            --text:        #1a1a2e;
            --text-muted:  #7b7fa8;
            --border:      #e8eaf0;
            --success:     #10b981;
            --warning:     #f59e0b;
            --danger:      #ef4444;
            --info:        #3b82f6;
            --purple:      #8b5cf6;
        }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg); color: var(--text);
            display: flex; min-height: 100vh; font-size: 14px; line-height: 1.55;
        }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #c7cce0; border-radius: 3px; }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-w); background: var(--sidebar-bg);
            display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; bottom: 0;
            z-index: 200; overflow: hidden; transition: width .25s ease;
        }
        body.sb-collapsed .sidebar { width: var(--sidebar-cw); }
        body.sb-collapsed .brand-name,
        body.sb-collapsed .brand-sub,
        body.sb-collapsed .nav-section-label,
        body.sb-collapsed .sidebar-user-name,
        body.sb-collapsed .sidebar-user-role,
        body.sb-collapsed .nav-badge,
        body.sb-collapsed .nav-text { display: none !important; }
        body.sb-collapsed .sidebar-brand { justify-content: center; padding: 0; }
        body.sb-collapsed .sidebar-user { justify-content: center; }
        body.sb-collapsed .nav-link { justify-content: center; padding: 8px 0; margin: 1px 6px; }
        body.sb-collapsed .nav-icon { margin: 0; }
        .sidebar::after {
            content: ''; position: absolute; top: 0; right: 0; bottom: 0; width: 1px;
            background: linear-gradient(to bottom,rgba(255,138,0,.35),transparent 50%,rgba(255,138,0,.1));
            pointer-events: none;
        }
        .sidebar-brand {
            height: var(--topbar-h); display: flex; align-items: center; gap: 12px;
            padding: 0 20px; border-bottom: 1px solid rgba(255,255,255,0.06);
            text-decoration: none; flex-shrink: 0;
        }
        .brand-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg,var(--brand),#ff6200);
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            font-size: 15px; color: #fff; flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(255,138,0,0.45);
        }
        .brand-name { font-size: 16px; font-weight: 800; color: #fff; letter-spacing: -.3px; }
        .brand-sub  { font-size: 9.5px; color: rgba(255,255,255,0.35); letter-spacing: 1.8px; text-transform: uppercase; margin-top: 1px; }
        .sidebar-scroll { flex: 1; overflow-y: auto; padding: 8px 0 20px; }
        .sidebar-scroll::-webkit-scrollbar { width: 3px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); }
        .nav-section-label {
            padding: 16px 20px 5px; font-size: 9.5px; font-weight: 700;
            letter-spacing: 1.8px; text-transform: uppercase;
            color: rgba(255,255,255,0.22); user-select: none;
        }
        .nav-link {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 14px; margin: 1px 8px; border-radius: 9px;
            color: rgba(255,255,255,0.58); text-decoration: none;
            font-size: 13.5px; font-weight: 500; position: relative; cursor: pointer;
            transition: background .15s, color .15s;
            white-space: nowrap; overflow: hidden;
        }
        .nav-icon {
            width: 30px; height: 30px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; flex-shrink: 0; background: rgba(255,255,255,0.05);
            transition: background .15s, color .15s;
        }
        .nav-link:hover { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.9); }
        .nav-link:hover .nav-icon { background: rgba(255,138,0,0.2); color: var(--brand); }
        .nav-link.active { background: linear-gradient(90deg,rgba(255,138,0,0.22),rgba(255,138,0,0.06)); color: #fff; }
        .nav-link.active .nav-icon { background: rgba(255,138,0,0.25); color: var(--brand); }
        .nav-link.active::before {
            content: ''; position: absolute; left: -8px; top: 6px; bottom: 6px;
            width: 3px; background: var(--brand); border-radius: 0 3px 3px 0;
        }
        .nav-badge {
            margin-left: auto; background: var(--brand); color: #fff;
            font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 8px; flex-shrink: 0;
        }
        .sidebar-footer { padding: 12px; border-top: 1px solid rgba(255,255,255,0.06); flex-shrink: 0; }
        .sidebar-user {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 11px; border-radius: 10px; background: rgba(255,255,255,0.05);
        }
        .sidebar-avatar {
            width: 32px; height: 32px;
            background: linear-gradient(135deg,var(--brand),#ff6200);
            border-radius: 8px; font-size: 12px; font-weight: 800; color: #fff;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .sidebar-user-name { font-size: 12px; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user-role { font-size: 10px; color: rgba(255,255,255,0.32); }

        /* Main */
        .main-wrapper { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; transition: margin-left .25s ease; }
        body.sb-collapsed .main-wrapper { margin-left: var(--sidebar-cw); }

        /* Topbar */
        .topbar {
            height: var(--topbar-h); background: var(--surface); border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 14px; padding: 0 28px;
            position: sticky; top: 0; z-index: 100; box-shadow: var(--shadow-sm);
        }
        .sb-toggle-btn {
            width: 36px; height: 36px; border-radius: 9px;
            border: 1.5px solid var(--border); background: transparent;
            color: var(--text-muted); display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 14px; flex-shrink: 0; transition: all .15s;
        }
        .sb-toggle-btn:hover { background: var(--bg); color: var(--text); }
        .topbar-title { font-size: 17px; font-weight: 800; color: var(--text); flex: 1; letter-spacing: -.3px; }
        .topbar-user { display: flex; align-items: center; gap: 9px; }
        .topbar-avatar {
            width: 34px; height: 34px;
            background: linear-gradient(135deg,var(--brand),#ff6200);
            border-radius: 9px; font-size: 13px; font-weight: 800; color: #fff;
            display: flex; align-items: center; justify-content: center;
        }
        .topbar-user-name { font-size: 12px; font-weight: 700; color: var(--text); }
        .topbar-user-role { font-size: 10.5px; color: var(--text-muted); }
        .topbar-logout {
            display: flex; align-items: center; gap: 6px;
            padding: 7px 13px; background: #fff5f5;
            color: var(--danger); border: 1.5px solid #fecaca;
            border-radius: 9px; font-size: 12px; font-weight: 700;
            cursor: pointer; text-decoration: none; white-space: nowrap; transition: all .15s;
        }
        .topbar-logout:hover { background: #fee2e2; }
        .topbar-divider { width: 1px; height: 26px; background: var(--border); margin: 0 2px; }

        /* Content */
        .main-content { flex: 1; padding: 28px; }
        .page-header {
            display: flex; align-items: flex-start; justify-content: space-between;
            margin-bottom: 24px; gap: 16px; flex-wrap: wrap;
        }
        .page-title { font-size: 22px; font-weight: 800; color: var(--text); letter-spacing: -.5px; }
        .breadcrumb {
            display: flex; gap: 0; list-style: none;
            font-size: 12.5px; color: var(--text-muted); margin-top: 4px; flex-wrap: wrap;
        }
        .breadcrumb li { display: flex; align-items: center; }
        .breadcrumb li:not(:last-child)::after { content: '/'; margin: 0 6px; color: #c5c7d4; }
        .breadcrumb a { color: var(--brand); text-decoration: none; }

        /* Cards */
        .card {
            background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border);
            box-shadow: var(--shadow-sm); overflow: hidden; margin-bottom: 20px;
        }
        .card-header {
            padding: 15px 20px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            background: var(--surface);
        }
        .card-header-title { font-size: 14px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 9px; }
        .card-header-icon { width: 28px; height: 28px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 12px; }
        .card-body { padding: 20px; }
        .card-footer { padding: 14px 20px; border-top: 1px solid var(--border); background: #fafbff; }

        /* Stat cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(210px,1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card {
            background: var(--surface); border-radius: var(--radius); border: 1px solid var(--border);
            padding: 20px; display: flex; align-items: flex-start; gap: 14px;
            position: relative; overflow: hidden; box-shadow: var(--shadow-sm);
            transition: box-shadow .2s, transform .2s;
        }
        .stat-card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }
        .stat-card::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 3px; }
        .stat-card.orange::after { background: linear-gradient(90deg,var(--brand),#ffb74d); }
        .stat-card.green::after  { background: linear-gradient(90deg,var(--success),#34d399); }
        .stat-card.blue::after   { background: linear-gradient(90deg,var(--info),#60a5fa); }
        .stat-card.red::after    { background: linear-gradient(90deg,var(--danger),#f87171); }
        .stat-card.purple::after { background: linear-gradient(90deg,var(--purple),#a78bfa); }
        .stat-card.teal::after   { background: linear-gradient(90deg,#14b8a6,#2dd4bf); }
        .stat-icon-wrap {
            width: 48px; height: 48px; border-radius: 13px;
            display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;
        }
        .stat-icon-wrap.orange { background: rgba(255,138,0,0.1); color: var(--brand); }
        .stat-icon-wrap.green  { background: rgba(16,185,129,0.1); color: var(--success); }
        .stat-icon-wrap.blue   { background: rgba(59,130,246,0.1); color: var(--info); }
        .stat-icon-wrap.red    { background: rgba(239,68,68,0.1);  color: var(--danger); }
        .stat-icon-wrap.purple { background: rgba(139,92,246,0.1); color: var(--purple); }
        .stat-icon-wrap.teal   { background: rgba(20,184,166,0.1); color: #14b8a6; }
        .stat-value { font-size: 26px; font-weight: 800; color: var(--text); letter-spacing: -1px; line-height: 1.1; }
        .stat-label { font-size: 12px; color: var(--text-muted); margin-top: 4px; font-weight: 500; }

        /* Table */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 10px 16px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: var(--text-muted); background: #f7f8fc; border-bottom: 1px solid var(--border); white-space: nowrap; }
        td { padding: 13px 16px; font-size: 13px; color: var(--text); border-bottom: 1px solid var(--border); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafbff; }

        /* Badges */
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-success { background: rgba(16,185,129,0.1); color: var(--success); }
        .badge-warning { background: rgba(245,158,11,0.1); color: var(--warning); }
        .badge-danger  { background: rgba(239,68,68,0.1);  color: var(--danger); }
        .badge-info    { background: rgba(59,130,246,0.1);  color: var(--info); }
        .badge-purple  { background: rgba(139,92,246,0.1);  color: var(--purple); }
        .badge-neutral { background: rgba(123,127,168,0.1); color: var(--text-muted); }
        .badge-orange  { background: rgba(255,138,0,0.1);   color: var(--brand); }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 8px 16px; border-radius: 9px; font-size: 13px; font-weight: 600;
            cursor: pointer; border: none; text-decoration: none; transition: all .15s;
        }
        .btn-primary  { background: var(--brand); color: #fff; }
        .btn-primary:hover  { background: var(--brand-dark); }
        .btn-success  { background: var(--success); color: #fff; }
        .btn-success:hover  { background: #059669; }
        .btn-danger   { background: var(--danger); color: #fff; }
        .btn-danger:hover   { background: #dc2626; }
        .btn-outline  { background: transparent; color: var(--text); border: 1.5px solid var(--border); }
        .btn-outline:hover  { background: var(--bg); }
        .btn-sm { padding: 5px 11px; font-size: 12px; border-radius: 7px; }
        .btn-xs { padding: 3px 9px; font-size: 11px; border-radius: 6px; }

        /* Filter bar */
        .filter-bar {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            padding: 14px 20px; background: #fafbff; border-bottom: 1px solid var(--border);
        }
        .filter-input {
            padding: 7px 12px; border-radius: 8px; border: 1.5px solid var(--border);
            font-size: 13px; color: var(--text); background: var(--surface); outline: none;
            transition: border-color .2s;
        }
        .filter-input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(255,138,0,0.08); }
        .filter-select { padding: 7px 12px; border-radius: 8px; border: 1.5px solid var(--border); font-size: 13px; background: var(--surface); cursor: pointer; }

        /* Empty state */
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .empty-state i { font-size: 40px; margin-bottom: 12px; opacity: .3; }
        .empty-state p { font-size: 14px; }

        /* Pagination */
        .pagination-wrap { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; flex-wrap: wrap; gap: 10px; }
        .pagination-info { font-size: 12.5px; color: var(--text-muted); }
        .pagination { display: flex; gap: 4px; list-style: none; }
        .pagination .page-item .page-link {
            display: flex; align-items: center; justify-content: center;
            width: 32px; height: 32px; border-radius: 8px;
            border: 1.5px solid var(--border); color: var(--text-muted);
            text-decoration: none; font-size: 13px; transition: all .15s;
        }
        .pagination .page-item.active .page-link { background: var(--brand); color: #fff; border-color: var(--brand); }
        .pagination .page-item .page-link:hover { background: var(--bg); color: var(--text); }

        /* Forms */
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 12.5px; font-weight: 700; color: var(--text); margin-bottom: 6px; }
        .form-control {
            width: 100%; padding: 9px 13px; border-radius: 9px;
            border: 1.5px solid var(--border); font-size: 13.5px; color: var(--text);
            background: var(--surface); outline: none; transition: border-color .2s;
        }
        .form-control:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(255,138,0,0.08); }
        textarea.form-control { resize: vertical; min-height: 90px; }
        .form-hint { font-size: 11.5px; color: var(--text-muted); margin-top: 4px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 16px; }

        /* Toggle switch */
        .toggle-wrap { display: flex; align-items: center; gap: 10px; }
        .toggle { position: relative; width: 42px; height: 24px; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute; inset: 0; background: #cbd5e1; border-radius: 24px;
            cursor: pointer; transition: background .2s;
        }
        .toggle-slider::before {
            content: ''; position: absolute; left: 3px; top: 3px;
            width: 18px; height: 18px; background: #fff;
            border-radius: 50%; transition: transform .2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        }
        .toggle input:checked + .toggle-slider { background: var(--brand); }
        .toggle input:checked + .toggle-slider::before { transform: translateX(18px); }

        /* Alert */
        .alert { padding: 12px 16px; border-radius: 9px; font-size: 13px; margin-bottom: 16px; display: flex; align-items: center; gap: 9px; }
        .alert-success { background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2); color: #047857; }
        .alert-danger  { background: rgba(239,68,68,0.08);  border: 1px solid rgba(239,68,68,0.2); color: #b91c1c; }
        .alert-warning { background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.2); color: #92400e; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; inset: 0; z-index: 500; background: rgba(7,0,59,.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.open { display: flex; }
        .modal { background: var(--surface); border-radius: 16px; width: 100%; max-width: 500px; box-shadow: var(--shadow-lg); animation: modalIn .2s ease; overflow: hidden; }
        @keyframes modalIn { from { transform: scale(.96) translateY(8px); opacity: 0; } }
        .modal-header { padding: 20px 24px 16px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .modal-title { font-size: 16px; font-weight: 800; color: var(--text); }
        .modal-close { background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 16px; padding: 4px; border-radius: 6px; transition: color .15s; }
        .modal-close:hover { color: var(--text); }
        .modal-body { padding: 20px 24px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: flex-end; gap: 10px; background: #fafbff; }

        /* Store status chip */
        .store-status { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .store-status.open   { background: rgba(16,185,129,0.1); color: var(--success); }
        .store-status.closed { background: rgba(239,68,68,0.1); color: var(--danger); }
        .store-status-dot { width: 7px; height: 7px; border-radius: 50%; }
        .store-status.open .store-status-dot   { background: var(--success); }
        .store-status.closed .store-status-dot { background: var(--danger); }
    </style>
    @stack('styles')
</head>
<body>

{{-- Sidebar --}}
<aside class="sidebar">
    <a href="{{ route('vendor.dashboard') }}" class="sidebar-brand">
        <div class="brand-icon"><i class="fa-solid fa-store"></i></div>
        <div>
            <div class="brand-name">eSahlan</div>
            <div class="brand-sub">Vendor Panel</div>
        </div>
    </a>

    <div class="sidebar-scroll">
        {{-- Store Status --}}
        @php $vendor = auth()->user()->vendor; @endphp
        @if($vendor)
        <div style="padding: 10px 16px 4px;">
            <div class="{{ $vendor->temporarily_closed ? 'store-status closed' : 'store-status open' }}">
                <span class="store-status-dot"></span>
                {{ $vendor->temporarily_closed ? 'Store Closed' : 'Store Open' }}
            </div>
        </div>
        @endif

        <div class="nav-section-label">Main</div>
        <a href="{{ route('vendor.dashboard') }}" class="nav-link {{ request()->routeIs('vendor.dashboard') ? 'active' : '' }}">
            <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
            <span class="nav-text">Dashboard</span>
        </a>
        <a href="{{ route('vendor.orders.index') }}" class="nav-link {{ request()->routeIs('vendor.orders.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="fa-solid fa-bag-shopping"></i></span>
            <span class="nav-text">Orders</span>
            @php $pending = \App\Models\Order::where('vendor_id', $vendor?->id)->where('status','pending')->count(); @endphp
            @if($pending > 0)
            <span class="nav-badge">{{ $pending }}</span>
            @endif
        </a>

        <div class="nav-section-label">Catalog</div>
        <a href="{{ route('vendor.products.index') }}" class="nav-link {{ request()->routeIs('vendor.products.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="fa-solid fa-box"></i></span>
            <span class="nav-text">Products</span>
        </a>

        <div class="nav-section-label">Business</div>
        <a href="{{ route('vendor.store.index') }}" class="nav-link {{ request()->routeIs('vendor.store.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="fa-solid fa-store"></i></span>
            <span class="nav-text">Store Profile</span>
        </a>
        <a href="{{ route('vendor.wallet.index') }}" class="nav-link {{ request()->routeIs('vendor.wallet.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="fa-solid fa-wallet"></i></span>
            <span class="nav-text">Wallet</span>
        </a>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'V', 0, 1)) }}</div>
            <div style="flex:1;min-width:0;">
                <div class="sidebar-user-name">{{ auth()->user()->name }}</div>
                <div class="sidebar-user-role">{{ $vendor?->name }}</div>
            </div>
            <form action="{{ route('vendor.logout') }}" method="POST" style="margin:0;">
                @csrf
                <button type="submit" title="Logout"
                    style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,0.25);font-size:14px;padding:4px;border-radius:6px;transition:color .15s;"
                    onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='rgba(255,255,255,0.25)'">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </button>
            </form>
        </div>
    </div>
</aside>

{{-- Main --}}
<div class="main-wrapper">
    <header class="topbar">
        <button class="sb-toggle-btn" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">@yield('title', 'Dashboard')</div>
        @stack('topbar-actions')
        <div class="topbar-divider"></div>
        <div class="topbar-user">
            <div class="topbar-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'V', 0, 1)) }}</div>
            <div>
                <div class="topbar-user-name">{{ auth()->user()->name }}</div>
                <div class="topbar-user-role">{{ $vendor?->name ?? 'Vendor' }}</div>
            </div>
        </div>
        <form action="{{ route('vendor.logout') }}" method="POST" style="margin:0;">
            @csrf
            <button type="submit" class="topbar-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
        </form>
    </header>

    <main class="main-content">
        @if(session('success'))
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> {{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}</div>
        @endif
        @if($errors->any() && !$errors->has('phone'))
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div>{{ $errors->first() }}</div>
        </div>
        @endif

        @yield('content')
    </main>
</div>

<script>
function toggleSidebar() {
    document.body.classList.toggle('sb-collapsed');
    localStorage.setItem('vendorSbCollapsed', document.body.classList.contains('sb-collapsed') ? '1' : '0');
}
if (localStorage.getItem('vendorSbCollapsed') === '1') document.body.classList.add('sb-collapsed');

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

function postRequest(url, data = {}, confirmMsg = '') {
    if (confirmMsg && !confirm(confirmMsg)) return;
    const form = document.createElement('form');
    form.method = 'POST'; form.action = url;
    const csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = document.querySelector('meta[name=csrf-token]').content;
    form.appendChild(csrf);
    Object.entries(data).forEach(([k, v]) => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = k; input.value = v;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}
</script>
@stack('scripts')
</body>
</html>
