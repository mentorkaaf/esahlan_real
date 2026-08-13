<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — eSahlan Vendor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        corePlugins: { preflight: false },
        theme: { extend: { colors: { brand: '#FF8A00', 'brand-dark': '#e07500', navy: '#07003B', sidebar: '#0c0148' } } }
    }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background: #f1f5f9; color: #1a1a2e; display: flex; min-height: 100vh; font-size: 14px; line-height: 1.55; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #c7cce0; border-radius: 3px; }

        /* ── Sidebar ── */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #0a0040 0%, #0c0148 40%, #0f0260 100%);
            display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; bottom: 0;
            z-index: 200; overflow: hidden; transition: width .25s ease;
        }
        .sidebar::before {
            content: ''; position: absolute; top: -60px; left: -60px;
            width: 220px; height: 220px; border-radius: 50%;
            background: radial-gradient(circle, rgba(255,138,0,0.15) 0%, transparent 70%);
            pointer-events: none;
        }
        body.sb-collapsed .sidebar { width: 68px; }
        body.sb-collapsed .main-wrapper { margin-left: 68px; }
        body.sb-collapsed .brand-name, body.sb-collapsed .brand-sub,
        body.sb-collapsed .nav-section-label, body.sb-collapsed .sidebar-user-name,
        body.sb-collapsed .sidebar-user-role, body.sb-collapsed .nav-badge,
        body.sb-collapsed .nav-text { display: none !important; }
        body.sb-collapsed .sidebar-brand { justify-content: center; padding: 0; }
        body.sb-collapsed .sidebar-user  { justify-content: center; }
        body.sb-collapsed .nav-link { justify-content: center; padding: 9px 0; margin: 1px 6px; }
        body.sb-collapsed .nav-icon { margin: 0; }
        body.sb-collapsed .sidebar-logout-btn { margin: 0 auto; display: block; }
        .sidebar::after {
            content: ''; position: absolute; top: 0; right: 0; bottom: 0; width: 1px;
            background: linear-gradient(to bottom, rgba(255,138,0,.5) 0%, rgba(255,138,0,.1) 40%, transparent 80%);
            pointer-events: none;
        }

        .sidebar-brand { height: 68px; display: flex; align-items: center; gap: 13px; padding: 0 20px; border-bottom: 1px solid rgba(255,255,255,0.07); text-decoration: none; flex-shrink: 0; }
        .brand-icon { width: 40px; height: 40px; background: linear-gradient(135deg,#FF8A00,#ff4e00); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 17px; color: #fff; flex-shrink: 0; box-shadow: 0 6px 20px rgba(255,138,0,0.55), 0 0 0 1px rgba(255,138,0,0.2); }
        .brand-name { font-size: 17px; font-weight: 900; color: #fff; letter-spacing: -.5px; }
        .brand-sub  { font-size: 9px; color: rgba(255,138,0,0.65); letter-spacing: 2.5px; text-transform: uppercase; margin-top: 2px; font-weight: 700; }

        .sidebar-scroll { flex: 1; overflow-y: auto; padding: 8px 0 20px; }
        .sidebar-scroll::-webkit-scrollbar { width: 3px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }

        .nav-section-label { padding: 16px 20px 5px; font-size: 9px; font-weight: 800; letter-spacing: 2.5px; text-transform: uppercase; color: rgba(255,138,0,0.4); user-select: none; }
        .nav-link { display: flex; align-items: center; gap: 11px; padding: 9px 15px; margin: 2px 10px; border-radius: 11px; color: rgba(255,255,255,0.5); text-decoration: none; font-size: 13.5px; font-weight: 500; position: relative; cursor: pointer; transition: all .18s; white-space: nowrap; overflow: hidden; }
        .nav-icon { width: 32px; height: 32px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; background: rgba(255,255,255,0.05); transition: all .18s; }
        .nav-link:hover { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.95); }
        .nav-link:hover .nav-icon { background: rgba(255,138,0,0.22); color: #FF8A00; transform: scale(1.05); }
        .nav-link.active { background: linear-gradient(90deg, rgba(255,138,0,0.25) 0%, rgba(255,138,0,0.08) 100%); color: #fff; box-shadow: inset 0 1px 0 rgba(255,255,255,0.05); }
        .nav-link.active .nav-icon { background: rgba(255,138,0,0.3); color: #FF8A00; }
        .nav-link.active::before { content: ''; position: absolute; left: 0; top: 8px; bottom: 8px; width: 3px; background: linear-gradient(to bottom,#FF8A00,#ff6200); border-radius: 0 3px 3px 0; }
        .nav-badge { margin-left: auto; background: #FF8A00; color: #fff; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 20px; flex-shrink: 0; box-shadow: 0 2px 8px rgba(255,138,0,0.4); }

        .sidebar-footer { padding: 12px; border-top: 1px solid rgba(255,255,255,0.07); flex-shrink: 0; background: rgba(0,0,0,0.15); }
        .sidebar-user { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.07); }
        .sidebar-avatar { width: 34px; height: 34px; background: linear-gradient(135deg,#FF8A00,#ff5f00); border-radius: 9px; font-size: 13px; font-weight: 900; color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 3px 10px rgba(255,138,0,0.4); }
        .sidebar-user-name { font-size: 12px; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user-role { font-size: 10px; color: rgba(255,138,0,0.6); font-weight: 600; }
        .sidebar-logout-btn { background: none; border: none; cursor: pointer; color: rgba(255,255,255,0.2); font-size: 15px; padding: 5px; border-radius: 7px; flex-shrink: 0; transition: color .15s, background .15s; }
        .sidebar-logout-btn:hover { color: #ef4444; background: rgba(239,68,68,0.15); }

        /* ── Main ── */
        .main-wrapper { margin-left: 260px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; transition: margin-left .25s ease; }

        /* ── Topbar ── */
        .topbar { height: 64px; background: #fff; border-bottom: 1px solid #eef0f6; display: flex; align-items: center; gap: 12px; padding: 0 28px; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .sb-toggle-btn { width: 36px; height: 36px; border-radius: 9px; border: 1.5px solid #eef0f6; background: transparent; color: #aab0c4; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 14px; flex-shrink: 0; transition: all .15s; }
        .sb-toggle-btn:hover { background: #f5f7ff; color: #374151; border-color: #d1d9f0; }
        .topbar-title { font-size: 17px; font-weight: 800; color: #111827; flex: 1; letter-spacing: -.4px; }
        .topbar-divider { width: 1px; height: 28px; background: #eef0f6; margin: 0 4px; }
        .topbar-user { display: flex; align-items: center; gap: 9px; }
        .topbar-avatar { width: 36px; height: 36px; background: linear-gradient(135deg,#FF8A00,#ff5f00); border-radius: 10px; font-size: 13px; font-weight: 900; color: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 10px rgba(255,138,0,0.35); }
        .topbar-user-name { font-size: 12.5px; font-weight: 700; color: #111827; }
        .topbar-user-role { font-size: 10.5px; color: #aab0c4; }
        .topbar-logout { display: flex; align-items: center; gap: 6px; padding: 7px 14px; background: linear-gradient(135deg,#fff5f5,#fff); color: #ef4444; border: 1.5px solid #fecaca; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; white-space: nowrap; transition: all .15s; }
        .topbar-logout:hover { background: #fee2e2; box-shadow: 0 2px 8px rgba(239,68,68,0.15); }

        /* ── Content ── */
        .main-content { flex: 1; padding: 28px; }

        /* ── Page Header ── */
        .page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; gap: 16px; flex-wrap: wrap; }
        .page-title { font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -.5px; }
        .breadcrumb { display: flex; gap: 0; list-style: none; font-size: 12.5px; color: #9ca3af; margin-top: 4px; flex-wrap: wrap; }
        .breadcrumb li { display: flex; align-items: center; }
        .breadcrumb li:not(:last-child)::after { content: '/'; margin: 0 6px; color: #d1d5db; }
        .breadcrumb a { color: #FF8A00; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        /* ── Cards ── */
        .card { background: #fff; border-radius: 14px; border: 1px solid #e8edf5; box-shadow: 0 1px 4px rgba(0,0,0,0.06); overflow: hidden; margin-bottom: 20px; }
        .card-header { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; background: #fff; }
        .card-header-title { font-size: 14px; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 9px; }
        .card-header-icon { width: 28px; height: 28px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 12px; }
        .card-body { padding: 20px; }
        .card-footer { padding: 14px 20px; border-top: 1px solid #f1f5f9; background: #fafbff; }

        /* ── Stat Cards ── */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(210px,1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #fff; border-radius: 14px; border: 1px solid #e8edf5; padding: 20px; display: flex; align-items: flex-start; gap: 14px; position: relative; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.06); transition: box-shadow .2s, transform .2s; }
        .stat-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .stat-card::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 3px; }
        .stat-card.orange::after { background: linear-gradient(90deg,#FF8A00,#ffb74d); }
        .stat-card.green::after  { background: linear-gradient(90deg,#10b981,#34d399); }
        .stat-card.blue::after   { background: linear-gradient(90deg,#3b82f6,#60a5fa); }
        .stat-card.red::after    { background: linear-gradient(90deg,#ef4444,#f87171); }
        .stat-card.purple::after { background: linear-gradient(90deg,#8b5cf6,#a78bfa); }
        .stat-card.teal::after   { background: linear-gradient(90deg,#14b8a6,#2dd4bf); }
        .stat-icon-wrap { width: 48px; height: 48px; border-radius: 13px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .stat-icon-wrap.orange { background: rgba(255,138,0,0.1);  color: #FF8A00; }
        .stat-icon-wrap.green  { background: rgba(16,185,129,0.1); color: #10b981; }
        .stat-icon-wrap.blue   { background: rgba(59,130,246,0.1); color: #3b82f6; }
        .stat-icon-wrap.red    { background: rgba(239,68,68,0.1);  color: #ef4444; }
        .stat-icon-wrap.purple { background: rgba(139,92,246,0.1); color: #8b5cf6; }
        .stat-icon-wrap.teal   { background: rgba(20,184,166,0.1); color: #14b8a6; }
        .stat-value { font-size: 26px; font-weight: 800; color: #111827; letter-spacing: -1px; line-height: 1.1; }
        .stat-label { font-size: 12px; color: #9ca3af; margin-top: 4px; font-weight: 500; }

        /* ── Tables ── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 11px 16px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #9ca3af; background: #fafbff; border-bottom: 1.5px solid #f1f5f9; white-space: nowrap; }
        td { padding: 13px 16px; font-size: 13px; color: #111827; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafbff; }

        /* ── Badges ── */
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-success { background: rgba(16,185,129,0.1); color: #059669; }
        .badge-warning { background: rgba(245,158,11,0.1); color: #d97706; }
        .badge-danger  { background: rgba(239,68,68,0.1);  color: #dc2626; }
        .badge-info    { background: rgba(59,130,246,0.1); color: #2563eb; }
        .badge-purple  { background: rgba(139,92,246,0.1); color: #7c3aed; }
        .badge-neutral { background: #f1f5f9;              color: #64748b; }
        .badge-orange  { background: rgba(255,138,0,0.1);  color: #c05800; }

        /* ── Buttons ── */
        .btn { display: inline-flex; align-items: center; gap: 7px; padding: 8px 16px; border-radius: 9px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all .15s; }
        .btn:hover { transform: translateY(-1px); }
        .btn:active { transform: none; }
        .btn-primary { background: #FF8A00; color: #fff; box-shadow: 0 2px 8px rgba(255,138,0,.3); }
        .btn-primary:hover { background: #e07500; }
        .btn-success { background: #10b981; color: #fff; }
        .btn-success:hover { background: #059669; }
        .btn-danger  { background: #ef4444; color: #fff; }
        .btn-danger:hover  { background: #dc2626; }
        .btn-outline { background: transparent; color: #374151; border: 1.5px solid #e8edf5; }
        .btn-outline:hover { background: #f8fafc; border-color: #d1d5db; }
        .btn-sm { padding: 5px 11px; font-size: 12px; border-radius: 7px; }
        .btn-xs { padding: 3px 9px; font-size: 11px; border-radius: 6px; }

        /* ── Filter bar ── */
        .filter-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 14px 20px; background: #fafbff; border-bottom: 1px solid #f1f5f9; }
        .filter-input { padding: 7px 12px; border-radius: 8px; border: 1.5px solid #e8edf5; font-size: 13px; color: #374151; background: #fff; outline: none; transition: border-color .2s; }
        .filter-input:focus { border-color: #FF8A00; box-shadow: 0 0 0 3px rgba(255,138,0,0.08); }
        .filter-select { padding: 7px 12px; border-radius: 8px; border: 1.5px solid #e8edf5; font-size: 13px; background: #fff; cursor: pointer; outline: none; }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 60px 20px; color: #9ca3af; }
        .empty-state i { font-size: 40px; margin-bottom: 12px; opacity: .2; display: block; }
        .empty-state p { font-size: 14px; }

        /* ── Pagination ── */
        .pagination-wrap { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; flex-wrap: wrap; gap: 10px; }
        .pagination-info { font-size: 12.5px; color: #9ca3af; }
        .pagination { display: flex; gap: 4px; list-style: none; }
        .pagination .page-item .page-link { display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; border: 1.5px solid #e8edf5; color: #9ca3af; text-decoration: none; font-size: 13px; transition: all .15s; }
        .pagination .page-item.active .page-link { background: #FF8A00; color: #fff; border-color: #FF8A00; }
        .pagination .page-item .page-link:hover { background: #f8fafc; color: #374151; }

        /* ── Forms ── */
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 12.5px; font-weight: 700; color: #4b5563; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 9px 13px; border-radius: 9px; border: 1.5px solid #e8edf5; font-size: 13.5px; color: #111827; background: #fff; outline: none; transition: border-color .2s; }
        .form-control:focus { border-color: #FF8A00; box-shadow: 0 0 0 3px rgba(255,138,0,0.08); }
        textarea.form-control { resize: vertical; min-height: 90px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .badge-success { background: rgba(16,185,129,0.1); color: #059669; }
        .badge-danger  { background: rgba(239,68,68,0.1);  color: #dc2626; }
        .badge-warning { background: rgba(245,158,11,0.1); color: #d97706; }
        .badge-info    { background: rgba(59,130,246,0.1);  color: #2563eb; }
        .badge-neutral { background: #f1f5f9; color: #64748b; }
        .badge-purple  { background: rgba(139,92,246,0.1); color: #7c3aed; }
        .badge-orange  { background: rgba(255,138,0,0.1);  color: #FF8A00; }
        .form-hint { font-size: 11.5px; color: #9ca3af; margin-top: 4px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 16px; }

        /* ── Toggle ── */
        .toggle-wrap { display: flex; align-items: center; gap: 10px; }
        .toggle { position: relative; width: 42px; height: 24px; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; inset: 0; background: #cbd5e1; border-radius: 24px; cursor: pointer; transition: background .2s; }
        .toggle-slider::before { content: ''; position: absolute; left: 3px; top: 3px; width: 18px; height: 18px; background: #fff; border-radius: 50%; transition: transform .2s; box-shadow: 0 1px 3px rgba(0,0,0,0.15); }
        .toggle input:checked + .toggle-slider { background: #FF8A00; }
        .toggle input:checked + .toggle-slider::before { transform: translateX(18px); }

        /* ── Alert ── */
        .alert { padding: 13px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; display: flex; align-items: center; gap: 9px; border-left: 4px solid; }
        .alert-success { background: rgba(16,185,129,0.08); border-color: #10b981; color: #065f46; }
        .alert-danger  { background: rgba(239,68,68,0.08);  border-color: #ef4444; color: #991b1b; }
        .alert-warning { background: rgba(245,158,11,0.08); border-color: #f59e0b; color: #92400e; }

        /* ── Modal ── */
        .modal-overlay { display: none; position: fixed; inset: 0; z-index: 500; background: rgba(7,0,59,.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.open { display: flex; }
        .modal, .modal-box { background: #fff; border-radius: 18px; width: 100%; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.18); animation: modalIn .2s ease; overflow: hidden; max-height: 90vh; overflow-y: auto; }
        @keyframes modalIn { from { transform: scale(.96) translateY(8px); opacity: 0; } }
        .modal-header { padding: 20px 24px 16px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
        .modal-title { font-size: 16px; font-weight: 800; color: #111827; }
        .modal-close { background: #f1f5f9; border: none; cursor: pointer; color: #9ca3af; font-size: 16px; padding: 6px; border-radius: 7px; transition: all .15s; }
        .modal-close:hover { background: #fee2e2; color: #ef4444; }
        .modal-body { padding: 20px 24px; }
        .modal-box > form, .modal-box > div:not(.modal-header) { padding: 0 24px; }
        .modal-box > form > .form-group:first-child { padding-top: 20px; }
        .modal-box > form > button[type="submit"] { margin: 16px 0 24px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: flex-end; gap: 10px; background: #fafbff; }

        /* ── Store status ── */
        .store-status { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .store-status.open   { background: rgba(16,185,129,0.1); color: #059669; }
        .store-status.closed { background: rgba(239,68,68,0.1);  color: #dc2626; }
        .store-status-dot { width: 7px; height: 7px; border-radius: 50%; }
        .store-status.open .store-status-dot   { background: #10b981; }
        .store-status.closed .store-status-dot { background: #ef4444; }
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
        @php
            $user = auth()->user();
            $allVendors = \App\Models\Vendor::where('user_id', $user->id)->orderBy('name')->get();
            $activeVendorId = session('active_vendor_id', $user->vendor?->id);
            $vendor = $allVendors->firstWhere('id', $activeVendorId) ?? $user->vendor;
        @endphp
        @if($allVendors->count() > 1)
        <div style="padding:8px 12px 0;">
            <form action="{{ route('vendor.switch-branch') }}" method="POST" style="margin:0;">
                @csrf
                <select name="vendor_id" onchange="this.form.submit()" style="width:100%;padding:7px 10px;border-radius:8px;border:1px solid rgba(255,255,255,0.15);background:rgba(255,255,255,0.08);color:#fff;font-size:12px;font-weight:600;">
                    @foreach($allVendors as $v)
                    <option value="{{ $v->id }}" {{ $v->id == $vendor?->id ? 'selected' : '' }} style="color:#000;">
                        {{ $v->name }}{{ $v->parent_id ? ' (Branch)' : '' }}
                    </option>
                    @endforeach
                </select>
            </form>
        </div>
        @endif
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
        <a href="{{ route('vendor.earnings') }}" class="nav-link {{ request()->routeIs('vendor.earnings') ? 'active' : '' }}">
            <span class="nav-icon"><i class="fa-solid fa-coins"></i></span>
            <span class="nav-text">Earnings</span>
        </a>

        <div class="nav-section-label">Catalog</div>
        <a href="{{ route('vendor.categories.index') }}" class="nav-link {{ request()->routeIs('vendor.categories.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="fa-solid fa-layer-group"></i></span>
            <span class="nav-text">Categories</span>
        </a>
        <a href="{{ route('vendor.products.index') }}" class="nav-link {{ request()->routeIs('vendor.products.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="fa-solid fa-box"></i></span>
            <span class="nav-text">Products</span>
        </a>
        <a href="{{ route('vendor.addons.index') }}" class="nav-link {{ request()->routeIs('vendor.addons.*') ? 'active' : '' }}">
            <span class="nav-icon"><i class="fa-solid fa-puzzle-piece"></i></span>
            <span class="nav-text">Addons</span>
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
                <button type="submit" class="sidebar-logout-btn" title="Logout">
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
