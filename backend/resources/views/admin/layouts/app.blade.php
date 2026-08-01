<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — eSahlan Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        corePlugins: { preflight: false },
        theme: {
            extend: {
                colors: {
                    brand: '#FF8A00',
                    'brand-dark': '#e07500',
                    navy: '#07003B',
                    sidebar: '#0c0148',
                }
            }
        }
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
        /* decorative orb behind sidebar */
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
        body.sb-collapsed .toggle-arrow, body.sb-collapsed .nav-text { display: none !important; }
        body.sb-collapsed .sidebar-brand { justify-content: center; padding: 0; }
        body.sb-collapsed .sidebar-user  { justify-content: center; }
        body.sb-collapsed .nav-link { justify-content: center; padding: 9px 0; margin: 1px 6px; }
        body.sb-collapsed .nav-icon { margin: 0; }
        body.sb-collapsed .sidebar-logout-btn { margin: 0 auto; display: block; }
        /* right edge glow line */
        .sidebar::after {
            content: ''; position: absolute; top: 0; right: 0; bottom: 0; width: 1px;
            background: linear-gradient(to bottom, rgba(255,138,0,.5) 0%, rgba(255,138,0,.1) 40%, transparent 80%);
            pointer-events: none;
        }

        .sidebar-brand {
            height: 68px; display: flex; align-items: center; gap: 13px;
            padding: 0 20px; border-bottom: 1px solid rgba(255,255,255,0.07);
            text-decoration: none; flex-shrink: 0;
        }
        .brand-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #FF8A00, #ff4e00);
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 17px; color: #fff; flex-shrink: 0;
            box-shadow: 0 6px 20px rgba(255,138,0,0.55), 0 0 0 1px rgba(255,138,0,0.2);
        }
        .brand-name { font-size: 17px; font-weight: 900; color: #fff; letter-spacing: -.5px; }
        .brand-sub  { font-size: 9px; color: rgba(255,138,0,0.65); letter-spacing: 2.5px; text-transform: uppercase; margin-top: 2px; font-weight: 700; }

        .sidebar-scroll { flex: 1; overflow-y: auto; padding: 8px 0 20px; }
        .sidebar-scroll::-webkit-scrollbar { width: 3px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }

        .nav-section-label {
            padding: 16px 20px 5px; font-size: 9px; font-weight: 800;
            letter-spacing: 2.5px; text-transform: uppercase;
            color: rgba(255,138,0,0.4); user-select: none;
        }
        .nav-link {
            display: flex; align-items: center; gap: 11px;
            padding: 9px 15px; margin: 2px 10px; border-radius: 11px;
            color: rgba(255,255,255,0.5); text-decoration: none;
            font-size: 13.5px; font-weight: 500; position: relative;
            cursor: pointer; transition: all .18s; white-space: nowrap; overflow: hidden;
        }
        .nav-icon {
            width: 32px; height: 32px; border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; flex-shrink: 0; background: rgba(255,255,255,0.05);
            transition: all .18s;
        }
        .nav-link:hover { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.95); }
        .nav-link:hover .nav-icon { background: rgba(255,138,0,0.22); color: #FF8A00; transform: scale(1.05); }
        .nav-link.active {
            background: linear-gradient(90deg, rgba(255,138,0,0.25) 0%, rgba(255,138,0,0.08) 100%);
            color: #fff; box-shadow: inset 0 1px 0 rgba(255,255,255,0.05);
        }
        .nav-link.active .nav-icon { background: rgba(255,138,0,0.3); color: #FF8A00; }
        .nav-link.active::before {
            content: ''; position: absolute; left: 0; top: 8px; bottom: 8px;
            width: 3px; background: linear-gradient(to bottom, #FF8A00, #ff6200);
            border-radius: 0 3px 3px 0;
        }
        .nav-badge {
            margin-left: auto; background: #FF8A00; color: #fff;
            font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 20px; flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(255,138,0,0.4);
        }

        .nav-submenu { overflow: hidden; max-height: 0; transition: max-height .3s ease; }
        .nav-submenu.open { max-height: 700px; }
        .nav-submenu .nav-link { padding: 7px 15px; margin-left: 14px; font-size: 13px; }
        .nav-submenu .nav-icon { width: 26px; height: 26px; font-size: 11px; }
        .toggle-arrow { margin-left: auto; font-size: 9px; color: rgba(255,255,255,0.2); transition: transform .25s; flex-shrink: 0; }
        .nav-toggle-btn.open .toggle-arrow { transform: rotate(90deg); }

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
        .topbar {
            height: 64px; background: #fff; border-bottom: 1px solid #eef0f6;
            display: flex; align-items: center; gap: 12px; padding: 0 28px;
            position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .sb-toggle-btn {
            width: 36px; height: 36px; border-radius: 9px;
            border: 1.5px solid #eef0f6; background: transparent;
            color: #aab0c4; display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 14px; flex-shrink: 0; transition: all .15s;
        }
        .sb-toggle-btn:hover { background: #f5f7ff; color: #374151; border-color: #d1d9f0; }
        .topbar-title { font-size: 17px; font-weight: 800; color: #111827; flex: 1; letter-spacing: -.4px; }
        .topbar-search {
            display: flex; align-items: center; gap: 8px;
            background: #f5f7ff; border: 1.5px solid #eef0f6;
            border-radius: 11px; padding: 7px 14px; width: 230px;
            transition: border-color .2s, width .25s, box-shadow .2s;
        }
        .topbar-search:focus-within { border-color: #FF8A00; width: 270px; box-shadow: 0 0 0 3px rgba(255,138,0,0.1); background: #fff; }
        .topbar-search i { color: #aab0c4; font-size: 12px; }
        .topbar-search input { border: none; background: transparent; outline: none; font-size: 13px; color: #374151; width: 100%; }
        .topbar-search input::placeholder { color: #aab0c4; }
        .topbar-btn {
            width: 36px; height: 36px; border-radius: 9px;
            border: 1.5px solid #eef0f6; background: transparent;
            color: #aab0c4; display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 13px; text-decoration: none; position: relative; transition: all .15s;
        }
        .topbar-btn:hover { background: #f5f7ff; color: #FF8A00; border-color: rgba(255,138,0,0.3); }
        .notif-dot { position: absolute; top: 7px; right: 7px; width: 7px; height: 7px; background: #ef4444; border-radius: 50%; border: 2px solid #fff; }
        .topbar-divider { width: 1px; height: 28px; background: #eef0f6; margin: 0 4px; }
        .topbar-user { display: flex; align-items: center; gap: 9px; }
        .topbar-avatar { width: 36px; height: 36px; background: linear-gradient(135deg,#FF8A00,#ff5f00); border-radius: 10px; font-size: 13px; font-weight: 900; color: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 3px 10px rgba(255,138,0,0.35); }
        .topbar-user-name { font-size: 12.5px; font-weight: 700; color: #111827; }
        .topbar-user-role { font-size: 10.5px; color: #aab0c4; }
        .topbar-logout {
            display: flex; align-items: center; gap: 6px; padding: 7px 14px;
            background: linear-gradient(135deg, #fff5f5, #fff); color: #ef4444;
            border: 1.5px solid #fecaca; border-radius: 10px; font-size: 12px; font-weight: 700;
            cursor: pointer; text-decoration: none; white-space: nowrap; transition: all .15s;
        }
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
        .stat-body { flex: 1; min-width: 0; }
        .stat-value { font-size: 26px; font-weight: 800; color: #111827; letter-spacing: -1px; line-height: 1; margin-bottom: 4px; }
        .stat-label { font-size: 12px; color: #9ca3af; font-weight: 500; }
        .stat-sub   { font-size: 11px; margin-top: 7px; display: flex; align-items: center; gap: 4px; font-weight: 600; }
        .stat-sub.up   { color: #10b981; } .stat-sub.warn { color: #f59e0b; } .stat-sub.muted { color: #9ca3af; }

        /* ── Tables ── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        thead th { padding: 11px 16px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #9ca3af; background: #fafbff; border-bottom: 1.5px solid #f1f5f9; white-space: nowrap; }
        tbody td { padding: 13px 16px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #111827; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr { transition: background .1s; }
        tbody tr:hover td { background: #fafbff; }

        /* ── Badges ── */
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .badge-success  { background: rgba(16,185,129,0.1);  color: #059669; }
        .badge-warning  { background: rgba(245,158,11,0.1);  color: #d97706; }
        .badge-danger   { background: rgba(239,68,68,0.1);   color: #dc2626; }
        .badge-info     { background: rgba(59,130,246,0.1);  color: #2563eb; }
        .badge-purple   { background: rgba(139,92,246,0.1);  color: #7c3aed; }
        .badge-teal     { background: rgba(20,184,166,0.1);  color: #0d9488; }
        .badge-secondary{ background: #f1f5f9;               color: #64748b; }
        .badge-orange   { background: rgba(255,138,0,0.1);   color: #c05800; }
        .badge-dark     { background: rgba(7,0,59,0.08);     color: #07003B; }
        .badge-neutral  { background: #f1f5f9;               color: #64748b; }
        .badge-dot::before { content: ''; display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        /* ── Buttons ── */
        .btn { display: inline-flex; align-items: center; gap: 7px; padding: 8px 16px; border-radius: 9px; border: none; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; transition: all .15s; white-space: nowrap; line-height: 1.2; font-family: inherit; }
        .btn:hover { transform: translateY(-1px); }
        .btn:active { transform: none; }
        .btn-primary  { background: #FF8A00; color: #fff; box-shadow: 0 2px 8px rgba(255,138,0,.3); }
        .btn-primary:hover  { background: #e07500; }
        .btn-navy     { background: #07003B; color: #fff; }
        .btn-success  { background: #10b981; color: #fff; }
        .btn-danger   { background: #ef4444; color: #fff; }
        .btn-info     { background: #3b82f6; color: #fff; }
        .btn-outline  { background: transparent; border: 1.5px solid #e8edf5; color: #374151; }
        .btn-outline:hover  { border-color: #d1d5db; background: #f8fafc; }
        .btn-ghost    { background: transparent; color: #9ca3af; }
        .btn-ghost:hover    { background: #f8fafc; color: #374151; }
        .btn-secondary { background: #07003B; color: #fff; }
        .btn-sm  { padding: 5px 11px; font-size: 12px; }
        .btn-xs  { padding: 3px 8px; font-size: 11px; border-radius: 6px; }
        .btn-icon{ width: 34px; height: 34px; padding: 0; justify-content: center; border-radius: 8px; }

        /* ── Forms ── */
        .form-group  { margin-bottom: 16px; }
        .form-label  { display: block; margin-bottom: 6px; font-size: 12.5px; font-weight: 600; color: #4b5563; }
        .form-control { width: 100%; padding: 9px 13px; border: 1.5px solid #e8edf5; border-radius: 9px; font-size: 13.5px; color: #111827; background: #fff; outline: none; transition: border-color .2s, box-shadow .2s; font-family: inherit; }
        .form-control:focus { border-color: #FF8A00; box-shadow: 0 0 0 3px rgba(255,138,0,.1); }
        select.form-control { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; background-size: 16px; padding-right: 36px; }
        textarea.form-control { resize: vertical; min-height: 80px; }
        .input-group { display: flex; }
        .input-group .form-control { border-radius: 9px 0 0 9px; border-right: none; }
        .input-group .btn { border-radius: 0 9px 9px 0; }
        .form-hint { font-size: 11.5px; color: #9ca3af; margin-top: 4px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 16px; }

        /* ── Alerts ── */
        .alert { display: flex; align-items: flex-start; gap: 10px; padding: 13px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 13.5px; border-left: 4px solid; }
        .alert i { margin-top: 1px; flex-shrink: 0; }
        .alert-success { background: rgba(16,185,129,.08); color: #065f46; border-color: #10b981; }
        .alert-danger  { background: rgba(239,68,68,.08);  color: #991b1b; border-color: #ef4444; }
        .alert-warning { background: rgba(245,158,11,.08); color: #92400e; border-color: #f59e0b; }
        .alert-info    { background: rgba(59,130,246,.08); color: #1e3a5f; border-color: #3b82f6; }

        /* ── Modal ── */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); backdrop-filter: blur(3px); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.open { display: flex; }
        .modal-box { background: #fff; border-radius: 18px; width: 100%; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.18); animation: modalIn .2s ease; }
        @keyframes modalIn { from { opacity:0; transform:scale(.95) translateY(10px); } to { opacity:1; transform:none; } }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 16px; border-bottom: 1px solid #f1f5f9; }
        .modal-title  { font-size: 16px; font-weight: 700; color: #111827; }
        .modal-close  { width: 30px; height: 30px; border: none; background: #f1f5f9; border-radius: 7px; color: #9ca3af; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; transition: all .15s; }
        .modal-close:hover { background: #fee2e2; color: #ef4444; }
        .modal-body   { padding: 20px 24px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid #f1f5f9; display: flex; gap: 10px; justify-content: flex-end; }

        /* ── Grid Utilities ── */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 20px; }
        .grid-4 { display: grid; grid-template-columns: repeat(4,1fr); gap: 20px; }
        @media (max-width:900px) { .grid-2,.grid-3,.grid-4 { grid-template-columns:1fr; } }

        /* ── Utilities ── */
        .d-flex   { display: flex; } .align-items-center { align-items: center; } .justify-between { justify-content: space-between; }
        .gap-2  { gap: 8px; } .gap-3 { gap: 12px; } .gap-4 { gap: 16px; }
        .mt-2 { margin-top: 8px; } .mt-3 { margin-top: 12px; } .mt-4 { margin-top: 16px; }
        .mb-2 { margin-bottom: 8px; } .mb-3 { margin-bottom: 12px; } .mb-4 { margin-bottom: 16px; }
        .fw-bold { font-weight: 700; } .fw-medium { font-weight: 500; }
        .text-sm { font-size: 12px; } .text-xs { font-size: 11px; }
        .text-muted   { color: #9ca3af; } .text-success { color: #10b981; } .text-danger { color: #ef4444; }
        .text-warning { color: #f59e0b; } .text-primary { color: #FF8A00; } .text-secondary { color: #07003B; }
        .text-right { text-align: right; } .text-center { text-align: center; }
        .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* ── Avatar ── */
        .avatar { display: inline-flex; align-items: center; justify-content: center; border-radius: 9px; font-weight: 800; color: #fff; flex-shrink: 0; }
        .avatar-sm { width: 30px; height: 30px; font-size: 11px; border-radius: 7px; }
        .avatar-md { width: 38px; height: 38px; font-size: 14px; border-radius: 10px; }
        .avatar-lg { width: 52px; height: 52px; font-size: 20px; border-radius: 13px; }
        .avatar-orange { background: linear-gradient(135deg,#FF8A00,#ff5f00); }
        .avatar-blue   { background: linear-gradient(135deg,#3b82f6,#1d4ed8); }
        .avatar-green  { background: linear-gradient(135deg,#10b981,#059669); }
        .avatar-purple { background: linear-gradient(135deg,#8b5cf6,#6d28d9); }

        /* ── Info table ── */
        .info-table { width: 100%; }
        .info-table tr td { padding: 9px 0; vertical-align: top; border: none; }
        .info-table tr td:first-child { color: #9ca3af; font-size: 12px; width: 150px; font-weight: 600; padding-right: 12px; }
        .info-table tr td:last-child  { color: #111827; font-size: 13px; font-weight: 600; }
        .info-table tr { border-bottom: 1px solid #f1f5f9; }
        .info-table tr:last-child { border-bottom: none; }

        /* ── Pagination ── */
        .pagination { display: flex; gap: 4px; list-style: none; flex-wrap: wrap; }
        .pagination li a, .pagination li span { display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; color: #9ca3af; background: #fff; border: 1.5px solid #e8edf5; transition: all .15s; }
        .pagination li.active span, .pagination li a:hover { background: #FF8A00; color: #fff; border-color: #FF8A00; }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 60px 20px; color: #9ca3af; }
        .empty-state i { font-size: 48px; margin-bottom: 16px; opacity: .2; display: block; }
        .empty-state h3 { font-size: 16px; font-weight: 700; color: #374151; margin-bottom: 6px; }
        .empty-state p { font-size: 13px; }

        /* ── Filter bar ── */
        .filter-bar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; padding: 14px 20px; border-bottom: 1px solid #f1f5f9; background: #fafbff; }
        .filter-bar .form-control { width: auto; min-width: 140px; }
        .filter-input { padding: 7px 12px; border-radius: 8px; border: 1.5px solid #e8edf5; font-size: 13px; color: #374151; background: #fff; outline: none; transition: border-color .2s; }
        .filter-input:focus { border-color: #FF8A00; box-shadow: 0 0 0 3px rgba(255,138,0,0.08); }
        .filter-select { padding: 7px 12px; border-radius: 8px; border: 1.5px solid #e8edf5; font-size: 13px; background: #fff; cursor: pointer; outline: none; }

        /* ── Toggle ── */
        .toggle-wrap { display: flex; align-items: center; gap: 10px; }
        .toggle { position: relative; width: 42px; height: 24px; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; inset: 0; background: #cbd5e1; border-radius: 24px; cursor: pointer; transition: background .2s; }
        .toggle-slider::before { content: ''; position: absolute; left: 3px; top: 3px; width: 18px; height: 18px; background: #fff; border-radius: 50%; transition: transform .2s; box-shadow: 0 1px 3px rgba(0,0,0,0.15); }
        .toggle input:checked + .toggle-slider { background: #FF8A00; }
        .toggle input:checked + .toggle-slider::before { transform: translateX(18px); }

        /* ── Store status ── */
        .store-status { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .store-status.open   { background: rgba(16,185,129,0.1); color: #059669; }
        .store-status.closed { background: rgba(239,68,68,0.1);  color: #dc2626; }
        .store-status-dot { width: 7px; height: 7px; border-radius: 50%; }
        .store-status.open .store-status-dot   { background: #10b981; }
        .store-status.closed .store-status-dot { background: #ef4444; }

        /* Laravel pagination override */
        nav[role="navigation"] > div:first-child { display: none; }
        nav .flex { display: flex; gap: 4px; flex-wrap: wrap; justify-content: center; align-items: center; }
        nav .flex span[aria-current="page"] span, nav .flex button { display: flex; align-items: center; justify-content: center; min-width: 34px; height: 34px; padding: 0 10px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; border: 1.5px solid #e8edf5; background: #fff; color: #9ca3af; cursor: pointer; transition: all .15s; font-family: inherit; }
        nav .flex span[aria-current="page"] span { background: #FF8A00; color: #fff; border-color: #FF8A00; }
        nav .flex a { display: flex; align-items: center; justify-content: center; min-width: 34px; height: 34px; padding: 0 10px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; border: 1.5px solid #e8edf5; background: #fff; color: #9ca3af; transition: all .15s; }
        nav .flex a:hover { background: #FF8A00; color: #fff; border-color: #FF8A00; }
        nav .flex span[aria-disabled="true"] span { opacity: .4; cursor: not-allowed; }
        .px-4 { padding-left: 16px; padding-right: 16px; }
        .py-3 { padding-top: 12px; padding-bottom: 12px; }
    </style>
    @stack('styles')

    {{-- ═══ DARK MODE — must come LAST so it wins over every page's @push('styles') ═══ --}}
    <style id="admin-dark-mode">
    /* ─── Light/Dark tokens (default = light) ─────────────────────────────── */
    :root {
      --dm-bg-page   : #f1f5f9;
      --dm-bg-card   : #ffffff;
      --dm-bg-subtle : #fafbff;
      --dm-bg-input  : #ffffff;
      --dm-border    : #e8edf5;
      --dm-border-sub: #f1f5f9;
      --dm-text-1    : #111827;
      --dm-text-2    : #374151;
      --dm-text-3    : #9ca3af;
      --dm-topbar-bg : #ffffff;
      --dm-topbar-bdr: #eef0f6;
      --dm-modal-bg  : #ffffff;
      --dm-filter-bg : #fafbff;
      --dm-scrollbar : #c7cce0;
    }

    /* ─── Dark tokens ─────────────────────────────────────────────────────── */
    body.dark-mode {
      --dm-bg-page   : #0f1117;
      --dm-bg-card   : #1a1d2e;
      --dm-bg-subtle : #1e2235;
      --dm-bg-input  : #252836;
      --dm-border    : #2a2d3e;
      --dm-border-sub: #252836;
      --dm-text-1    : #e2e8f0;
      --dm-text-2    : #94a3b8;
      --dm-text-3    : #566073;
      --dm-topbar-bg : #13162a;
      --dm-topbar-bdr: #1e2235;
      --dm-modal-bg  : #1a1d2e;
      --dm-filter-bg : #13162a;
      --dm-scrollbar : #2a2d3e;
    }

    /* ─── Global surface tokens applied ──────────────────────────────────── */
    body            { background:var(--dm-bg-page) !important; color:var(--dm-text-1); }
    ::-webkit-scrollbar-thumb { background:var(--dm-scrollbar) !important; }

    /* ─── Topbar ─────────────────────────────────────────────────────────── */
    body.dark-mode .topbar            { background:var(--dm-topbar-bg); border-color:var(--dm-topbar-bdr); box-shadow:0 2px 12px rgba(0,0,0,.3); }
    body.dark-mode .topbar-title      { color:var(--dm-text-1); }
    body.dark-mode .topbar-user-name  { color:var(--dm-text-1); }
    body.dark-mode .topbar-user-role  { color:var(--dm-text-3); }
    body.dark-mode .topbar-divider    { background:var(--dm-border); }
    body.dark-mode .topbar-search     { background:var(--dm-bg-subtle); border-color:var(--dm-border); }
    body.dark-mode .topbar-search:focus-within { background:var(--dm-bg-card); }
    body.dark-mode .topbar-search input  { color:var(--dm-text-1); }
    body.dark-mode .topbar-btn        { border-color:var(--dm-border); color:var(--dm-text-3); }
    body.dark-mode .topbar-btn:hover  { background:var(--dm-bg-subtle); color:#FF8A00; border-color:rgba(255,138,0,.3); }
    body.dark-mode .sb-toggle-btn     { border-color:var(--dm-border); color:var(--dm-text-3); }
    body.dark-mode .sb-toggle-btn:hover { background:var(--dm-bg-subtle); color:var(--dm-text-1); }
    body.dark-mode .topbar-logout     { background:var(--dm-bg-subtle); border-color:#3f1a1a; color:#f87171; }
    body.dark-mode .topbar-logout:hover { background:#2d1a1a; }
    body.dark-mode .notif-dot         { border-color:var(--dm-topbar-bg); }

    /* ─── Page structure ──────────────────────────────────────────────────── */
    body.dark-mode .page-title        { color:var(--dm-text-1); }
    body.dark-mode .breadcrumb        { color:var(--dm-text-3); }
    body.dark-mode .breadcrumb li::after { color:var(--dm-border); }

    /* ─── Page wrappers ──────────────────────────────────────────────────── */
    body.dark-mode .main-content           { background:var(--dm-bg-page) !important; }
    body.dark-mode [class*="-page"]:not(body) { background:var(--dm-bg-page) !important; }

    /* ─── Cards (global + page-specific patterns) ────────────────────────── */
    body.dark-mode .card,
    body.dark-mode .card-header,
    body.dark-mode .stat-card,
    body.dark-mode .modal-box,
    /* dashboard cards */
    body.dark-mode .kpi-card,
    body.dark-mode .kpi,
    body.dark-mode .kpi-mini,
    body.dark-mode .dash-card,
    /* wallet / ep-pay cards */
    body.dark-mode .ep-card,
    body.dark-mode .ep-modal-box,
    /* dispatch / map */
    body.dark-mode .map-stat,
    body.dark-mode .driver-panel,
    body.dark-mode .dp-item,
    /* inbox / chat / support */
    body.dark-mode .chat-wrap,
    body.dark-mode .chat-input-row,
    body.dark-mode .st-card,
    body.dark-mode .st-link-card,
    /* community / posts / emarry / etc page cards */
    body.dark-mode [class*="cm-card"],
    body.dark-mode [class*="-section"]:not(.nav-section-label),
    body.dark-mode [class*="-table-wrap"],
    body.dark-mode [class*="-box"]:not(.brand-icon):not(.stat-icon-wrap):not(.card-header-icon),
    body.dark-mode [class*="ps-stat"],
    body.dark-mode [class*="em-stat"],
    body.dark-mode [class*="em-table-wrap"],
    body.dark-mode [class*="em-modal"] > div,
    body.dark-mode [class*="ec-card"],
    body.dark-mode [class*="ls-card"],
    body.dark-mode [class*="ts-card"],
    body.dark-mode [class*="ag-card"],
    body.dark-mode [class*="sec-notif-panel"],
    body.dark-mode [class*="-panel"]:not(.sec-notif-list) { background:#1a1d2e !important; border-color:#2a2d3e !important; }

    body.dark-mode .card-footer,
    body.dark-mode [class*="-filter"],
    body.dark-mode [class*="filter-bar"],
    body.dark-mode .chat-sidebar,
    body.dark-mode .chat-messages         { background:#13162a !important; }

    /* ─── Dashboard text ──────────────────────────────────────────────────── */
    body.dark-mode .kpi-val,
    body.dark-mode .kpi-label,
    body.dark-mode .kpi-sub,
    body.dark-mode .dash-card-title,
    body.dark-mode .comm-stat-val,
    body.dark-mode .comm-stat-label,
    body.dark-mode .vendor-rank            { color:var(--dm-text-1) !important; }
    body.dark-mode .dash-card-header       { border-color:#2a2d3e !important; }

    /* ─── EP (wallet) buttons / tabs ──────────────────────────────────────── */
    body.dark-mode .ep-btn,
    body.dark-mode .ep-btn-outline         { background:#1e2235 !important; border-color:#2a2d3e !important; color:var(--dm-text-2) !important; }
    body.dark-mode .ep-tab.active          { background:#1a1d2e !important; color:var(--dm-text-1) !important; box-shadow:0 1px 3px rgba(0,0,0,.4); }

    /* ─── Chat / Inbox ────────────────────────────────────────────────────── */
    body.dark-mode .msg-bubble.user        { background:#1e2235 !important; color:var(--dm-text-1) !important; }
    body.dark-mode .typing-dots            { background:#1e2235 !important; }
    body.dark-mode .sb-select             { background:var(--dm-bg-input) !important; border-color:#2a2d3e !important; color:var(--dm-text-1) !important; }
    body.dark-mode .chat-sidebar           { border-color:#2a2d3e !important; }
    body.dark-mode .chat-wrap              { border-color:#2a2d3e !important; }

    body.dark-mode .card-header-title { color:var(--dm-text-1); }
    body.dark-mode .stat-value        { color:var(--dm-text-1) !important; }
    body.dark-mode .stat-label        { color:var(--dm-text-3) !important; }
    body.dark-mode .stat-sub          { opacity:.85; }

    /* ─── Tables ──────────────────────────────────────────────────────────── */
    body.dark-mode thead th,
    body.dark-mode [class*="-table"] thead th { background:#13162a !important; color:var(--dm-text-3) !important; border-color:var(--dm-border) !important; }
    body.dark-mode tbody td,
    body.dark-mode [class*="-table"] tbody td { color:var(--dm-text-1) !important; border-color:var(--dm-border-sub) !important; }
    body.dark-mode tbody tr:hover td,
    body.dark-mode [class*="-table"] tbody tr:hover { background:#1e2235 !important; }

    /* ─── Forms ───────────────────────────────────────────────────────────── */
    body.dark-mode .form-control,
    body.dark-mode .filter-input,
    body.dark-mode .filter-select,
    body.dark-mode [class*="-input"],
    body.dark-mode [class*="form-control"] { background:var(--dm-bg-input) !important; border-color:var(--dm-border) !important; color:var(--dm-text-1) !important; }
    body.dark-mode .form-label         { color:var(--dm-text-2) !important; }
    body.dark-mode .form-hint          { color:var(--dm-text-3); }
    body.dark-mode select.form-control { background-color:var(--dm-bg-input) !important; }
    body.dark-mode textarea            { background:var(--dm-bg-input) !important; border-color:var(--dm-border) !important; color:var(--dm-text-1) !important; }

    /* ─── Modals ──────────────────────────────────────────────────────────── */
    body.dark-mode .modal-overlay      { background:rgba(0,0,0,.65); }
    body.dark-mode .modal-box          { background:#1a1d2e !important; border-color:#2a2d3e; }
    body.dark-mode .modal-header       { border-color:#2a2d3e; }
    body.dark-mode .modal-title        { color:var(--dm-text-1); }
    body.dark-mode .modal-close        { background:#252836; color:#64748b; }
    body.dark-mode .modal-close:hover  { background:#3f1a1a; color:#f87171; }
    body.dark-mode .modal-footer       { border-color:#2a2d3e; background:#13162a; }

    /* ─── Buttons ─────────────────────────────────────────────────────────── */
    body.dark-mode .btn-outline        { border-color:#2a2d3e; color:#94a3b8; background:transparent; }
    body.dark-mode .btn-outline:hover  { background:#252836; border-color:#374151; color:#e2e8f0; }
    body.dark-mode .btn-ghost          { color:#64748b; }
    body.dark-mode .btn-ghost:hover    { background:#252836; color:#94a3b8; }

    /* ─── Alerts ──────────────────────────────────────────────────────────── */
    body.dark-mode .alert-success { background:rgba(16,185,129,.12); color:#6ee7b7; border-color:#065f46; }
    body.dark-mode .alert-danger  { background:rgba(239,68,68,.12);  color:#fca5a5; border-color:#7f1d1d; }
    body.dark-mode .alert-warning { background:rgba(245,158,11,.12); color:#fcd34d; border-color:#78350f; }
    body.dark-mode .alert-info    { background:rgba(59,130,246,.12); color:#93c5fd; border-color:#1e3a5f; }

    /* ─── Badges ──────────────────────────────────────────────────────────── */
    body.dark-mode .badge-secondary,
    body.dark-mode .badge-neutral  { background:#252836; color:#94a3b8; }
    body.dark-mode .badge-dark     { background:rgba(255,255,255,.06); color:#94a3b8; }

    /* ─── Info table ──────────────────────────────────────────────────────── */
    body.dark-mode .info-table tr              { border-color:#2a2d3e; }
    body.dark-mode .info-table tr td:first-child { color:#566073; }
    body.dark-mode .info-table tr td:last-child  { color:#e2e8f0; }

    /* ─── Pagination ──────────────────────────────────────────────────────── */
    body.dark-mode .pagination li a,
    body.dark-mode .pagination li span,
    body.dark-mode nav .flex a,
    body.dark-mode nav .flex button { background:#1e2235 !important; border-color:#2a2d3e !important; color:#64748b !important; }
    body.dark-mode .pagination li.active span,
    body.dark-mode nav .flex span[aria-current="page"] span { background:#FF8A00 !important; color:#fff !important; border-color:#FF8A00 !important; }

    /* ─── Toggle switch ───────────────────────────────────────────────────── */
    body.dark-mode .toggle-slider { background:#2a2d3e; }

    /* ─── Misc text nodes that use hardcoded colors ───────────────────────── */
    body.dark-mode .text-muted   { color:#566073 !important; }
    body.dark-mode .empty-state h3 { color:#e2e8f0; }
    body.dark-mode .empty-state   { color:#566073; }

    /* ─── Security notification panel ────────────────────────────────────── */
    body.dark-mode #secNotifPanel { background:#1a1d2e; border-color:#2a2d3e; }
    body.dark-mode .sec-panel-head { border-color:#2a2d3e; }
    body.dark-mode .sec-panel-title { color:#e2e8f0; }
    body.dark-mode .sec-notif-item  { border-color:#1e2235; }
    body.dark-mode .sec-notif-item:hover { background:#1e2235; }
    body.dark-mode .sec-notif-list::-webkit-scrollbar-thumb { background:#2a2d3e; }

    /* ─── Dark mode toggle button in topbar ───────────────────────────────── */
    #adminThemeToggle { position:relative; }
    #adminThemeToggle .theme-icon { transition:transform .3s, opacity .2s; }
    body.dark-mode #adminThemeToggle { color:#fcd34d !important; border-color:rgba(252,211,77,.25) !important; }
    body.dark-mode #adminThemeToggle:hover { background:rgba(252,211,77,.1) !important; }
    </style>
</head>
<body>

{{-- ═══ SIDEBAR ═══ --}}
<nav class="sidebar">
    <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-infinity"></i></div>
        <div>
            <div class="brand-name">eSahlan</div>
            <div class="brand-sub">Admin Panel</div>
        </div>
    </a>

    <div class="sidebar-scroll">
        @php $u = auth()->user(); @endphp

        <div class="nav-section-label">Main</div>
        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-chart-pie"></i></div> Dashboard
        </a>

        @if($u->isFullAdmin())
        <div class="nav-section-label">People</div>
        <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-users"></i></div> Users
        </a>
        <a href="{{ route('admin.vendors.index') }}" class="nav-link {{ request()->routeIs('admin.vendors.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-store"></i></div> Vendors
        </a>
        <a href="{{ route('admin.deliverymen.index') }}" class="nav-link {{ request()->routeIs('admin.deliverymen.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-motorcycle"></i></div> Deliverymen
        </a>

        <div class="nav-section-label">Operations</div>
        <a href="{{ route('admin.orders.index') }}" class="nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-shopping-bag"></i></div>
            Orders
            @php try { $__pOrd = \App\Models\Order::where('status','pending')->count(); } catch(\Exception $e){ $__pOrd=0; } @endphp
            @if($__pOrd > 0)<span class="nav-badge">{{ $__pOrd }}</span>@endif
        </a>
        <a href="{{ route('admin.wallet.index') }}" class="nav-link {{ request()->routeIs('admin.wallet.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-wallet"></i></div> ePay
        </a>
        <a href="{{ route('admin.mobile-pay.index') }}" class="nav-link {{ request()->routeIs('admin.mobile-pay.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-mobile-alt"></i></div> Mobile Pay
        </a>
        <a href="{{ route('admin.crypto.dashboard') }}" class="nav-link {{ request()->routeIs('admin.crypto.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fab fa-bitcoin"></i></div> Crypto Exchange
            @php try { $__pendingWd = \App\Models\CryptoWithdrawal::where('status','pending')->count(); $__openDsp = \App\Models\P2pDispute::where('status','open')->count(); $__cx = $__pendingWd + $__openDsp; } catch(\Exception $e){ $__cx=0; } @endphp
            @if($__cx > 0)<span class="nav-badge">{{ $__cx }}</span>@endif
        </a>
        <a href="{{ route('admin.dispatch') }}" class="nav-link {{ request()->routeIs('admin.dispatch') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-map-marked-alt"></i></div> Dispatch
        </a>
        <a href="{{ route('admin.dispatch.map') }}" class="nav-link {{ request()->routeIs('admin.dispatch.map') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-satellite-dish"></i></div> Live Tracking
        </a>
        <a href="{{ route('admin.rewards.index') }}" class="nav-link {{ request()->routeIs('admin.rewards.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-star"></i></div> Rewards
        </a>
        <a href="{{ route('admin.affiliates.index') }}" class="nav-link {{ request()->routeIs('admin.affiliates.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-handshake"></i></div> Affiliates
        </a>
        <a href="{{ route('admin.payment-settings.index') }}" class="nav-link {{ request()->routeIs('admin.payment-settings.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-credit-card"></i></div> Payments
        </a>
        <a href="{{ route('admin.modules.index') }}" class="nav-link {{ request()->routeIs('admin.modules.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-th-large"></i></div> Modules
        </a>
        @endif

        <div class="nav-section-label">Module Data</div>
        <div class="nav-link nav-toggle-btn {{ request()->is('admin/module-data*') ? 'open active' : '' }}"
             onclick="toggleNav(this,'moduleDataNav')">
            <div class="nav-icon"><i class="fas fa-database"></i></div>
            Module Data
            <i class="fas fa-chevron-right toggle-arrow"></i>
        </div>
        <div class="nav-submenu {{ request()->is('admin/module-data*') ? 'open' : '' }}" id="moduleDataNav">
            @if($u->canManageModule('efood'))
            <a href="{{ route('admin.module-data.efood.index') }}" class="nav-link {{ request()->routeIs('admin.module-data.efood*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-utensils"></i></div> eFood
            </a>
            @endif
            @if($u->canManageModule('eshop'))
            <a href="{{ route('admin.eshop.index') }}" class="nav-link {{ request()->routeIs('admin.eshop*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-shopping-bag"></i></div> eShop
            </a>
            @endif
            @if($u->canManageModule('elaundry'))
            <a href="{{ route('admin.module-data.laundry') }}" class="nav-link {{ request()->routeIs('admin.module-data.laundry*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-tshirt"></i></div> eLaundry
            </a>
            @endif
            @if($u->canManageModule('emoving'))
            <a href="{{ route('admin.module-data.moving') }}" class="nav-link {{ request()->routeIs('admin.module-data.moving*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-truck-moving"></i></div> eMoving
            </a>
            @endif
            @if($u->canManageModule('eparcel'))
            <a href="{{ route('admin.module-data.parcel') }}" class="nav-link {{ request()->routeIs('admin.module-data.parcel*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-box"></i></div> eParcel
            </a>
            @endif
            @if($u->canManageModule('edata'))
            <a href="{{ route('admin.module-data.data') }}" class="nav-link {{ request()->routeIs('admin.module-data.data*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-wifi"></i></div> eData
            </a>
            @endif
            @if($u->canManageModule('eexchange'))
            <a href="{{ route('admin.module-data.exchange') }}" class="nav-link {{ request()->routeIs('admin.module-data.exchange*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-exchange-alt"></i></div> eExchange
            </a>
            @endif
            @if($u->canManageModule('ehealth'))
            <a href="{{ route('admin.module-data.health') }}" class="nav-link {{ request()->routeIs('admin.module-data.health*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-user-md"></i></div> eHealth
            </a>
            @endif
            @if($u->canManageModule('erent'))
            <a href="{{ route('admin.module-data.rent') }}" class="nav-link {{ request()->routeIs('admin.module-data.rent*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-home"></i></div> eRent
            </a>
            @endif
            @if($u->canManageModule('ewholesale'))
            <a href="{{ route('admin.module-data.wholesale') }}" class="nav-link {{ request()->routeIs('admin.module-data.wholesale*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-warehouse"></i></div> Wholesale
            </a>
            @endif
            @if($u->canManageModule('egrocery'))
            <a href="{{ route('admin.module-data.egrocery.index') }}" class="nav-link {{ request()->routeIs('admin.module-data.egrocery*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-carrot"></i></div> eGrocery
            </a>
            @endif
            @if($u->canManageModule('eticket'))
            <a href="{{ route('admin.module-data.ticket') }}" class="nav-link {{ request()->routeIs('admin.module-data.ticket*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-plane"></i></div> eTicket
            </a>
            @endif
        </div>

        @if($u->isFullAdmin())
        <div class="nav-section-label">Finance</div>
        <a href="{{ route('admin.finance.index') }}" class="nav-link {{ request()->routeIs('admin.finance.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-wallet"></i></div> Finance
        </a>
        <a href="{{ route('admin.reports.sales') }}" class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-chart-bar"></i></div> Reports
        </a>

        <div class="nav-section-label">Marketing</div>
        <a href="{{ route('admin.banners.index') }}" class="nav-link {{ request()->routeIs('admin.banners.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-image"></i></div> Banners
        </a>
        <a href="{{ route('admin.ads.index') }}" class="nav-link {{ request()->routeIs('admin.ads.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-ad"></i></div> Ads Manager
        </a>
        <a href="{{ route('admin.notifications.index') }}" class="nav-link {{ request()->routeIs('admin.notifications.index') || request()->routeIs('admin.notifications.send') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-bell"></i></div> Notifications
        </a>
        <a href="{{ route('admin.notifications.cart-templates') }}" class="nav-link {{ request()->routeIs('admin.notifications.cart-templates*') ? 'active' : '' }}" style="padding-left:36px;font-size:12px;">
            <div class="nav-icon"><i class="fas fa-shopping-cart"></i></div> Cart Reminders
        </a>

        <div class="nav-section-label">Inbox</div>
        <div class="nav-link nav-toggle-btn {{ request()->is('admin/inbox*') ? 'open active' : '' }}"
             onclick="toggleNav(this,'inboxNav')">
            <div class="nav-icon"><i class="fas fa-headset"></i></div>
            Support &amp; Inbox
            <i class="fas fa-chevron-right toggle-arrow"></i>
        </div>
        <div class="nav-submenu {{ request()->is('admin/inbox*') ? 'open' : '' }}" id="inboxNav">
            <a href="{{ route('admin.inbox.conversations') }}" class="nav-link {{ request()->routeIs('admin.inbox.conversations') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-comments"></i></div> Support Tickets
            </a>
            <a href="{{ route('admin.inbox.broadcasts.index') }}" class="nav-link {{ request()->routeIs('admin.inbox.broadcasts.*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-bullhorn"></i></div> Broadcasts
            </a>
            <a href="{{ route('admin.inbox.stats') }}" class="nav-link {{ request()->routeIs('admin.inbox.stats') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-chart-bar"></i></div> Stats
            </a>
        </div>

        <div class="nav-section-label">Community</div>
        <div class="nav-link nav-toggle-btn {{ request()->is('admin/community*') ? 'open active' : '' }}"
             onclick="this.classList.toggle('open');this.nextElementSibling.classList.toggle('open')">
            <div class="nav-icon"><i class="fas fa-users"></i></div>
            Community
            <i class="fas fa-chevron-right toggle-arrow"></i>
        </div>
        <div class="nav-submenu {{ request()->is('admin/community*') ? 'open' : '' }}">
            <a href="{{ route('admin.community.index') }}" class="nav-link {{ request()->routeIs('admin.community.index') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-tachometer-alt"></i></div> Dashboard
            </a>
            <a href="{{ route('admin.community.posts') }}" class="nav-link {{ request()->routeIs('admin.community.posts') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-file-alt"></i></div> Posts
            </a>
            <a href="{{ route('admin.community.reports') }}" class="nav-link {{ request()->routeIs('admin.community.reports') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-flag"></i></div> Reports
            </a>
            <a href="{{ route('admin.community.groups') }}" class="nav-link {{ request()->routeIs('admin.community.groups') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-layer-group"></i></div> Groups
            </a>
            <a href="{{ route('admin.community.users') }}" class="nav-link {{ request()->routeIs('admin.community.users') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-user-shield"></i></div> Users
            </a>
            <a href="{{ route('admin.community.moderation') }}" class="nav-link {{ request()->routeIs('admin.community.moderation*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-shield-alt"></i></div> Moderation
            </a>
            <a href="{{ route("admin.community.engagement") }}" class="nav-link {{ request()->routeIs("admin.community.engagement*") ? "active" : "" }}">
                <div class="nav-icon"><i class="fas fa-magic"></i></div> Engagement
            </a>
            <a href="{{ route('admin.community.algorithm') }}" class="nav-link {{ request()->routeIs('admin.community.algorithm*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-brain"></i></div> Algorithm
            </a>
            <a href="{{ route('admin.community-ads.index') }}" class="nav-link {{ request()->routeIs('admin.community-ads.*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-bullhorn"></i></div> Ads & Pages
            </a>
            <a href="{{ route('admin.podcast.index') }}" class="nav-link {{ request()->routeIs('admin.podcast.*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-podcast"></i></div> Podcasts
            </a>
            <a href="{{ route('admin.emarry.index') }}" class="nav-link {{ request()->routeIs('admin.emarry.*') ? 'active' : '' }}">
                <div class="nav-icon">💍</div> eMarry
                @php $emPending = \Illuminate\Support\Facades\DB::table('emarry_profiles')->where('status','pending')->count(); @endphp
                @if($emPending > 0)
                  <span style="margin-left:auto;background:#E11D48;color:#fff;border-radius:20px;padding:1px 8px;font-size:11px;font-weight:800;">{{ $emPending }}</span>
                @endif
            </a>
        </div>

        {{-- Live Management --}}
        <div class="nav-link nav-toggle-btn {{ request()->is('admin/live*') ? 'open active' : '' }}"
             onclick="this.classList.toggle('open');this.nextElementSibling.classList.toggle('open')">
            <div class="nav-icon"><i class="fas fa-video"></i></div>
            Live
            <i class="fas fa-chevron-right toggle-arrow"></i>
            @php $activeLiveCount = \Illuminate\Support\Facades\DB::table('live_rooms')->where('status','live')->count(); @endphp
            @if($activeLiveCount > 0)<span class="nav-badge" style="background:#D92D20;">{{ $activeLiveCount }}</span>@endif
        </div>
        <div class="nav-submenu {{ request()->is('admin/live*') ? 'open' : '' }}">
            <a href="{{ route('admin.live.index') }}" class="nav-link {{ request()->routeIs('admin.live.index') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-tachometer-alt"></i></div> Dashboard
            </a>
            <a href="{{ route('admin.live.history') }}" class="nav-link {{ request()->routeIs('admin.live.history') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-history"></i></div> History
            </a>
            <a href="{{ route('admin.live.gifts') }}" class="nav-link {{ request()->routeIs('admin.live.gifts*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-gift"></i></div> Gifts
            </a>
            <a href="{{ route('admin.live.transactions') }}" class="nav-link {{ request()->routeIs('admin.live.transactions') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-coins"></i></div> Transactions
            </a>
            <a href="{{ route('admin.live.banned') }}" class="nav-link {{ request()->routeIs('admin.live.banned') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-ban"></i></div> Banned Users
            </a>
            <a href="{{ route('admin.live.reports') }}" class="nav-link {{ request()->routeIs('admin.live.reports*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-flag"></i></div> Reports
                @php $pendingReports = \App\Models\LiveRoomReport::where('status','pending')->count(); @endphp
                @if($pendingReports > 0)<span class="nav-badge" style="background:#D92D20;">{{ $pendingReports }}</span>@endif
            </a>
            <a href="{{ route('admin.live.coin-revenue') }}" class="nav-link {{ request()->routeIs('admin.live.coin-revenue') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-chart-line"></i></div> Coin Revenue
            </a>
        </div>
        @endif

        @if($u->canManageModule('community') || $u->role === 'super_admin' || $u->role === 'admin')
        <div class="nav-section-label">Trust & Safety</div>
        <a href="{{ route('admin.trust-safety.dashboard') }}" class="nav-link {{ request()->routeIs('admin.trust-safety.dashboard') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-shield-halved"></i></div> Dashboard
        </a>
        <a href="{{ route('admin.trust-safety.queue') }}" class="nav-link {{ request()->routeIs('admin.trust-safety.queue') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-inbox"></i></div> Review Queue
            @php $tsQueueCount = \Illuminate\Support\Facades\DB::table('community_posts')->where('moderation_status','pending')->count(); @endphp
            @if($tsQueueCount > 0)<span class="nav-badge">{{ $tsQueueCount }}</span>@endif
        </a>
        <a href="{{ route('admin.trust-safety.reports') }}" class="nav-link {{ request()->routeIs('admin.trust-safety.reports') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-flag"></i></div> Reports
            @php $tsReportCount = \Illuminate\Support\Facades\DB::table('community_reports')->where('status','pending')->count(); @endphp
            @if($tsReportCount > 0)<span class="nav-badge">{{ $tsReportCount }}</span>@endif
        </a>
        <a href="{{ route('admin.trust-safety.strikes') }}" class="nav-link {{ request()->routeIs('admin.trust-safety.strikes') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-gavel"></i></div> Strikes
        </a>
        <a href="{{ route('admin.trust-safety.appeals') }}" class="nav-link {{ request()->routeIs('admin.trust-safety.appeals') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-scale-balanced"></i></div> Appeals
        </a>
        <a href="{{ route('admin.trust-safety.settings') }}" class="nav-link {{ request()->routeIs('admin.trust-safety.settings') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-sliders"></i></div> TS Settings
        </a>
        @endif

        @if($u->canManageModule('elearning'))
        <div class="nav-section-label">eLearning</div>
        <div class="nav-link nav-toggle-btn {{ request()->is('admin/elearning*') ? 'open active' : '' }}"
             onclick="this.classList.toggle('open');this.nextElementSibling.classList.toggle('open')">
            <div class="nav-icon"><i class="fas fa-graduation-cap"></i></div>
            eLearning
            <i class="fas fa-chevron-right toggle-arrow"></i>
        </div>
        <div class="nav-submenu {{ request()->is('admin/elearning*') ? 'open' : '' }}">
            <a href="{{ route('admin.elearning.dashboard') }}" class="nav-link {{ request()->routeIs('admin.elearning.dashboard') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-tachometer-alt"></i></div> Dashboard
            </a>
            <a href="{{ route('admin.elearning.instructors') }}" class="nav-link {{ request()->routeIs('admin.elearning.instructors*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-chalkboard-teacher"></i></div> Instructors
                @php try { $__pInst = \App\Models\ELearningInstructor::where('verification_status','pending')->count(); } catch(\Exception $e){ $__pInst=0; } @endphp
                @if($__pInst > 0)<span class="nav-badge">{{ $__pInst }}</span>@endif
            </a>
            <a href="{{ route('admin.elearning.courses') }}" class="nav-link {{ request()->routeIs('admin.elearning.courses*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-book-open"></i></div> Courses
                @php try { $__pCrs = \App\Models\ELearningCourse::where('status','pending')->count(); } catch(\Exception $e){ $__pCrs=0; } @endphp
                @if($__pCrs > 0)<span class="nav-badge">{{ $__pCrs }}</span>@endif
            </a>
            <a href="{{ route('admin.elearning.categories') }}" class="nav-link {{ request()->routeIs('admin.elearning.categories*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-tags"></i></div> Categories
            </a>
            <a href="{{ route('admin.elearning.students') }}" class="nav-link {{ request()->routeIs('admin.elearning.students*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-user-graduate"></i></div> Students
            </a>
            <a href="{{ route('admin.elearning.certificates') }}" class="nav-link {{ request()->routeIs('admin.elearning.certificates*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-certificate"></i></div> Certificates
            </a>
            <a href="{{ route('admin.elearning.withdrawals') }}" class="nav-link {{ request()->routeIs('admin.elearning.withdrawals*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-money-bill-wave"></i></div> Withdrawals
                @php try { $__pWdr = \App\Models\ELearningWithdrawal::where('status','pending')->count(); } catch(\Exception $e){ $__pWdr=0; } @endphp
                @if($__pWdr > 0)<span class="nav-badge">{{ $__pWdr }}</span>@endif
            </a>
            <a href="{{ route('admin.elearning.reviews') }}" class="nav-link {{ request()->routeIs('admin.elearning.reviews*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-star"></i></div> Reviews
            </a>
            <a href="{{ route('admin.elearning.settings') }}" class="nav-link {{ request()->routeIs('admin.elearning.settings*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-sliders-h"></i></div> Settings
            </a>
            <a href="{{ route('admin.elearning.reports') }}" class="nav-link {{ request()->routeIs('admin.elearning.reports*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-chart-line"></i></div> Reports
            </a>
        </div>
        @endif

        @can('platform.audit.view')
        <div class="nav-section-label">Security</div>
        <a href="{{ route('admin.security.soc') }}" class="nav-link {{ request()->routeIs('admin.security.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-shield-halved"></i></div> SOC Dashboard
        </a>
        @endcan

        @if($u->isFullAdmin())
        <div class="nav-section-label">System</div>
        @if(in_array($u->role?->slug, ['super_admin','admin']))
        <a href="{{ route('admin.access.index') }}" class="nav-link {{ request()->routeIs('admin.access.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-user-shield"></i></div> Roles &amp; Access
        </a>
        @endif
        <a href="{{ route('admin.landing.index') }}" class="nav-link {{ request()->routeIs('admin.landing.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-paint-brush"></i></div> Landing Page
        </a>
        <a href="{{ route('admin.email-templates.index') }}" class="nav-link {{ request()->is('admin/email-templates*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-envelope-open-text"></i></div> Email Templates
        </a>
        <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-cog"></i></div> Settings
        </a>
        @endif
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A',0,1)) }}</div>
            <div style="flex:1;min-width:0;">
                <div class="sidebar-user-name">{{ auth()->user()->name ?? 'Admin' }}</div>
                <div class="sidebar-user-role">{{ auth()->user()->role?->name ?? 'Administrator' }}</div>
            </div>
            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button type="submit" class="sidebar-logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </form>
        </div>
    </div>
</nav>

{{-- ═══ MAIN ═══ --}}
<div class="main-wrapper">
    <header class="topbar">
        <button class="sb-toggle-btn" id="sbToggle" title="Toggle sidebar"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">@yield('title', 'Dashboard')</div>

        <div class="topbar-search">
            <i class="fas fa-search"></i>
            <input type="text" id="globalSearch" placeholder="Search orders, users…">
        </div>

        <div style="display:flex;align-items:center;gap:8px;">
            <a href="{{ route('admin.orders.index') }}?status=pending" class="topbar-btn" title="Pending Orders">
                <i class="fas fa-shopping-bag"></i>
                @if(isset($__pOrd) && $__pOrd > 0)<span class="notif-dot"></span>@endif
            </a>
            <a href="{{ route('admin.notifications.index') }}" class="topbar-btn" title="Notifications">
                <i class="fas fa-bell"></i>
            </a>
            <button id="adminThemeToggle" class="topbar-btn" title="Toggle dark mode" style="border:1.5px solid #eef0f6;">
                <i class="fas fa-moon theme-icon"></i>
            </button>
            <div class="topbar-divider"></div>
            <div class="topbar-user">
                <div class="topbar-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A',0,1)) }}</div>
                <div>
                    <div class="topbar-user-name">{{ auth()->user()->name ?? 'Admin' }}</div>
                    <div class="topbar-user-role">{{ auth()->user()->role?->name ?? 'Administrator' }}</div>
                </div>
            </div>
            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button type="submit" class="topbar-logout"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </form>
        </div>
    </header>

    <main class="main-content">
        @if(session('success'))
            <div class="alert alert-success"><i class="fas fa-check-circle"></i><div>{{ session('success') }}</div></div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><div>{{ session('error') }}</div></div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <div>@foreach($errors->all() as $err)<div>• {{ $err }}</div>@endforeach</div>
            </div>
        @endif
        @yield('content')
    </main>
</div>

<script>
/* ── Dark mode ── */
const THEME_KEY = 'admin_theme';

/* Override any element that has an inline light background via style="" attribute */
const LIGHT_BG_RE = /(?:^|;)\s*background(?:-color)?\s*:\s*(?:#fff(?:fff)?|white|#f(?:[0-9a-f]{5}|[0-9a-f]{2})|rgba?\(\s*25[0-9]\s*,\s*25[0-9]\s*,\s*25[0-9])/i;
function applyDarkInlineStyles() {
    document.querySelectorAll('.main-content [style]').forEach(function(el) {
        var s = el.getAttribute('style') || '';
        if (LIGHT_BG_RE.test(s)) {
            if (!el._dmOrigStyle) el._dmOrigStyle = s;
            el.style.setProperty('background-color', '#1a1d2e', 'important');
            el.style.setProperty('border-color', '#2a2d3e', 'important');
        }
    });
}
function removeDarkInlineStyles() {
    document.querySelectorAll('.main-content [style]').forEach(function(el) {
        if (el._dmOrigStyle !== undefined) {
            el.setAttribute('style', el._dmOrigStyle);
            delete el._dmOrigStyle;
        }
    });
}

function applyTheme(dark) {
    document.body.classList.toggle('dark-mode', dark);
    const btn  = document.getElementById('adminThemeToggle');
    const icon = btn?.querySelector('.theme-icon');
    if (icon) { icon.className = dark ? 'fas fa-sun theme-icon' : 'fas fa-moon theme-icon'; }
    if (btn)  { btn.title = dark ? 'Switch to light mode' : 'Switch to dark mode'; }
}
// Apply immediately (before paint) to avoid flash
(function(){ applyTheme(localStorage.getItem(THEME_KEY) === 'dark'); })();
// After DOM ready: handle inline styles
document.addEventListener('DOMContentLoaded', function() {
    if (document.body.classList.contains('dark-mode')) applyDarkInlineStyles();
});
document.getElementById('adminThemeToggle')?.addEventListener('click', function() {
    const dark = !document.body.classList.contains('dark-mode');
    applyTheme(dark);
    localStorage.setItem(THEME_KEY, dark ? 'dark' : 'light');
    if (dark) applyDarkInlineStyles(); else removeDarkInlineStyles();
});

/* ── Sidebar ── */
const SB_KEY = 'sb_collapsed';
function applySidebar(collapsed) { document.body.classList.toggle('sb-collapsed', collapsed); }
applySidebar(localStorage.getItem(SB_KEY) === '1');
document.getElementById('sbToggle')?.addEventListener('click', () => {
    const next = !document.body.classList.contains('sb-collapsed');
    applySidebar(next);
    localStorage.setItem(SB_KEY, next ? '1' : '0');
});
document.querySelectorAll('.nav-link').forEach(link => {
    link.childNodes.forEach(node => {
        if (node.nodeType === 3 && node.textContent.trim()) {
            const span = document.createElement('span');
            span.className = 'nav-text';
            span.textContent = node.textContent;
            node.replaceWith(span);
        }
    });
});
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
document.addEventListener('click', e => { if (e.target.classList.contains('modal-overlay')) closeModal(e.target.id); });
function toggleNav(btn, menuId) { btn.classList.toggle('open'); document.getElementById(menuId)?.classList.toggle('open'); }
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
async function postRequest(url, data = {}) {
    const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: JSON.stringify(data) });
    return r.json();
}
document.getElementById('globalSearch')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') { const q = e.target.value.trim(); if (q) window.location.href = '{{ route("admin.orders.index") }}?search=' + encodeURIComponent(q); }
});
</script>
@stack('scripts')
@include('partials.order-notifier')

{{-- ══ Global Security Alert System ══════════════════════════════════════ --}}
<style>
/* Notification panel */
#secNotifBtn{position:relative;cursor:pointer;background:none;border:none;padding:6px 8px;border-radius:8px;color:#64748b;transition:.15s;display:flex;align-items:center}
#secNotifBtn:hover{background:rgba(255,138,0,.1);color:#FF8A00}
#secNotifDot{position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:#ff4757;display:none;box-shadow:0 0 0 2px #fff;animation:secPulse 1.4s infinite}
#secNotifDot.show{display:block}
@keyframes secPulse{0%,100%{box-shadow:0 0 0 2px #fff,0 0 0 4px rgba(255,71,87,0)}50%{box-shadow:0 0 0 2px #fff,0 0 0 6px rgba(255,71,87,.35)}}
#secNotifCount{position:absolute;top:2px;right:2px;min-width:16px;height:16px;border-radius:8px;background:#ff4757;color:#fff;font-size:9px;font-weight:700;display:none;align-items:center;justify-content:center;padding:0 3px;line-height:1}
#secNotifCount.show{display:flex}

/* Dropdown panel */
#secNotifPanel{position:fixed;top:54px;right:16px;width:380px;max-height:520px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.15);z-index:9999;display:none;flex-direction:column;overflow:hidden;animation:secPanelIn .2s ease}
#secNotifPanel.open{display:flex}
@keyframes secPanelIn{from{opacity:0;transform:translateY(-8px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
.sec-panel-head{padding:14px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px;flex-shrink:0}
.sec-panel-title{font-size:13px;font-weight:700;color:#1a202c}
.sec-panel-sub{font-size:11px;color:#94a3b8;margin-left:auto}
.sec-clear-btn{font-size:11px;color:#FF8A00;background:none;border:none;cursor:pointer;font-weight:600;padding:0}
.sec-notif-list{overflow-y:auto;flex:1}
.sec-notif-list::-webkit-scrollbar{width:3px}
.sec-notif-list::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:2px}
.sec-notif-item{display:flex;align-items:flex-start;gap:10px;padding:12px 18px;border-bottom:1px solid #f8fafc;transition:.15s;cursor:default}
.sec-notif-item:hover{background:#f8fafc}
.sec-notif-item.unread{background:#fff8f0}
.sec-notif-item.unread::before{content:'';position:absolute;left:8px;width:4px;height:4px;border-radius:50%;background:#FF8A00;top:50%;transform:translateY(-50%)}
.sec-notif-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.sni-critical{background:rgba(255,71,87,.12);color:#e53e3e}
.sni-warn{background:rgba(255,138,0,.12);color:#e87200}
.sni-info{background:rgba(77,159,255,.12);color:#2b7de9}
.sni-ok{background:rgba(0,168,98,.12);color:#00a862}
.sec-notif-body{flex:1;min-width:0}
.sec-notif-event{font-size:12px;font-weight:600;color:#1a202c;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sec-notif-meta{font-size:11px;color:#94a3b8;margin-top:2px}
.sec-notif-time{font-size:10px;color:#cbd5e1;margin-top:3px}
.sec-notif-empty{padding:32px;text-align:center;color:#94a3b8;font-size:13px}
.sec-panel-foot{padding:10px 18px;border-top:1px solid #f1f5f9;flex-shrink:0;text-align:center}
.sec-panel-foot a{font-size:12px;font-weight:600;color:#FF8A00;text-decoration:none}

/* Toast alerts */
#secToastStack{position:fixed;bottom:24px;left:24px;display:flex;flex-direction:column-reverse;gap:10px;z-index:99999;pointer-events:none}
.sec-toast{display:flex;align-items:flex-start;gap:12px;padding:14px 18px;border-radius:14px;min-width:320px;max-width:400px;pointer-events:all;box-shadow:0 8px 32px rgba(0,0,0,.18);border:1px solid;animation:secToastIn .3s cubic-bezier(.34,1.56,.64,1);cursor:pointer;transition:.2s}
.sec-toast:hover{transform:translateX(4px)}
@keyframes secToastIn{from{opacity:0;transform:translateX(-24px)}to{opacity:1;transform:translateX(0)}}
.sec-toast.out{animation:secToastOut .25s ease forwards}
@keyframes secToastOut{to{opacity:0;transform:translateX(-20px);max-height:0;padding:0;margin:0}}
.sec-toast.t-critical{background:#fff5f5;border-color:#fed7d7}
.sec-toast.t-warn{background:#fffbeb;border-color:#fde68a}
.sec-toast.t-info{background:#eff6ff;border-color:#bfdbfe}
.sec-toast.t-ok{background:#f0fdf4;border-color:#bbf7d0}
.sec-toast-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.sec-toast.t-critical .sec-toast-icon{background:rgba(229,62,62,.15);color:#e53e3e}
.sec-toast.t-warn .sec-toast-icon{background:rgba(232,114,0,.15);color:#e87200}
.sec-toast.t-info .sec-toast-icon{background:rgba(43,125,233,.15);color:#2b7de9}
.sec-toast.t-ok .sec-toast-icon{background:rgba(0,168,98,.15);color:#00a862}
.sec-toast-body{flex:1;min-width:0}
.sec-toast-title{font-size:12px;font-weight:700;margin-bottom:3px}
.sec-toast.t-critical .sec-toast-title{color:#c53030}
.sec-toast.t-warn .sec-toast-title{color:#b45309}
.sec-toast.t-info .sec-toast-title{color:#1d4ed8}
.sec-toast.t-ok .sec-toast-title{color:#166534}
.sec-toast-msg{font-size:11px;color:#64748b;line-height:1.45;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.sec-toast-close{font-size:14px;color:#94a3b8;background:none;border:none;cursor:pointer;padding:0;line-height:1;flex-shrink:0;margin-top:1px}
.sec-toast-close:hover{color:#475569}
.sec-toast-bar{position:absolute;bottom:0;left:0;height:3px;border-radius:0 0 0 14px;animation:secBar 7s linear forwards}
.sec-toast{position:relative;overflow:hidden}
@keyframes secBar{from{width:100%}to{width:0}}
.sec-toast.t-critical .sec-toast-bar{background:#e53e3e}
.sec-toast.t-warn .sec-toast-bar{background:#e87200}
.sec-toast.t-info .sec-toast-bar{background:#2b7de9}
.sec-toast.t-ok .sec-toast-bar{background:#00a862}
</style>

<!-- Notification button (inject into topbar) -->
<script>
(function injectSecBtn() {
    const topRight = document.querySelector('.topbar-right, .flex.items-center.gap-3, .flex.gap-3');
    if (!topRight) return;
    const btn = document.createElement('button');
    btn.id = 'secNotifBtn';
    btn.title = 'Security Alerts';
    btn.innerHTML = '<i class="fas fa-shield-halved" style="font-size:16px"></i><span id="secNotifDot"></span><span id="secNotifCount"></span>';
    btn.onclick = toggleSecPanel;
    topRight.prepend(btn);
})();
</script>

<!-- Notification panel -->
<div id="secNotifPanel">
    <div class="sec-panel-head">
        <i class="fas fa-shield-halved" style="color:#FF8A00;font-size:14px"></i>
        <span class="sec-panel-title">Security Alerts</span>
        <span class="sec-panel-sub" id="secPanelSub">Live · 15s</span>
        <button class="sec-clear-btn" onclick="clearSecNotifs()">Clear all</button>
    </div>
    <div class="sec-notif-list" id="secNotifList">
        <div class="sec-notif-empty" id="secEmptyMsg"><i class="fas fa-shield-check" style="font-size:28px;color:#00a862;display:block;margin-bottom:8px"></i>No active alerts</div>
    </div>
    <div class="sec-panel-foot">
        <a href="{{ route('admin.security.soc') }}"><i class="fas fa-arrow-right" style="margin-right:4px"></i>Open Security Center</a>
    </div>
</div>

<!-- Toast stack -->
<div id="secToastStack"></div>

<script>
'use strict';
// ── Security Notification System ────────────────────────────────────────────
const SEC_ALERTS_URL = '{{ route("admin.security.live-events") }}';
const SEC_ICONS = {
    'admin.login.failed':       { icon:'fa-user-lock',      cls:'sni-warn',     tc:'t-warn',    label:'Admin Login Failed' },
    'admin.login.blocked':      { icon:'fa-ban',            cls:'sni-critical', tc:'t-critical',label:'Admin Login Blocked' },
    'admin.route.probe':        { icon:'fa-magnifying-glass',cls:'sni-warn',    tc:'t-warn',    label:'Admin Panel Probe' },
    'admin.route.blocked':      { icon:'fa-shield-xmark',   cls:'sni-critical', tc:'t-critical',label:'Admin Panel Blocked' },
    'admin.privilege.escalation':{ icon:'fa-user-lock',     cls:'sni-critical', tc:'t-critical',label:'Privilege Escalation' },
    'admin.access.denied':      { icon:'fa-lock',           cls:'sni-warn',     tc:'t-warn',    label:'Access Denied' },
    'login.blocked':            { icon:'fa-ban',            cls:'sni-critical', tc:'t-critical',label:'App Login Blocked' },
    'login.failed':             { icon:'fa-key',            cls:'sni-warn',     tc:'t-warn',    label:'Login Failed' },
    'upload.rejected':          { icon:'fa-virus',          cls:'sni-critical', tc:'t-critical',label:'Malware Upload' },
    'admin.login.success':      { icon:'fa-shield-check',   cls:'sni-ok',       tc:'t-ok',      label:'Admin Login' },
};
const SHOW_TOAST_FOR = ['admin.login.failed','admin.login.blocked','admin.route.probe','admin.route.blocked',
                        'admin.privilege.escalation','admin.access.denied','login.blocked','upload.rejected'];

let _secNotifs      = JSON.parse(localStorage.getItem('sec_notifs') || '[]');
let _secLastId      = localStorage.getItem('sec_last_id') || 0;
let _secPanelOpen   = false;
let _secUnread      = 0;

function toggleSecPanel() {
    _secPanelOpen = !_secPanelOpen;
    document.getElementById('secNotifPanel').classList.toggle('open', _secPanelOpen);
    if (_secPanelOpen) { _secUnread = 0; updateSecBadge(); renderSecPanel(); }
    document.addEventListener('click', outsideSecPanel, { once: true });
}
function outsideSecPanel(e) {
    const panel = document.getElementById('secNotifPanel');
    const btn   = document.getElementById('secNotifBtn');
    if (!panel?.contains(e.target) && !btn?.contains(e.target)) {
        _secPanelOpen = false;
        panel?.classList.remove('open');
    }
}
function clearSecNotifs() {
    _secNotifs = []; _secUnread = 0;
    localStorage.setItem('sec_notifs', '[]');
    updateSecBadge(); renderSecPanel();
}
function updateSecBadge() {
    const dot   = document.getElementById('secNotifDot');
    const count = document.getElementById('secNotifCount');
    if (!dot || !count) return;
    if (_secUnread > 0) {
        dot.classList.add('show');
        count.textContent = _secUnread > 99 ? '99+' : _secUnread;
        count.classList.add('show');
    } else {
        dot.classList.remove('show');
        count.classList.remove('show');
    }
}
function renderSecPanel() {
    const list  = document.getElementById('secNotifList');
    const empty = document.getElementById('secEmptyMsg');
    if (!list) return;
    if (_secNotifs.length === 0) { if(empty) empty.style.display=''; list.innerHTML=''; list.appendChild(empty); return; }
    if (empty) empty.style.display = 'none';
    list.innerHTML = _secNotifs.slice(0, 50).map(n => {
        const m = SEC_ICONS[n.event] || { icon:'fa-circle-dot', cls:'sni-info', label: n.event };
        const sev = n.severity === 'critical' ? 'critical' : n.severity === 'warn' ? 'warn' : 'info';
        return `<div class="sec-notif-item ${n.read ? '' : 'unread'}" style="position:relative">
            <div class="sec-notif-icon ${m.cls}"><i class="fas ${m.icon}"></i></div>
            <div class="sec-notif-body">
                <div class="sec-notif-event">${m.label || n.event}</div>
                <div class="sec-notif-meta">${n.ip || ''} ${n.identifier ? '· ' + n.identifier : ''}</div>
                <div class="sec-notif-time">${n.time_ago || ''}</div>
            </div>
            <span style="font-size:9px;font-weight:700;padding:2px 6px;border-radius:5px;${sev==='critical'?'background:rgba(229,62,62,.15);color:#c53030':sev==='warn'?'background:rgba(232,114,0,.12);color:#b45309':'background:rgba(43,125,233,.12);color:#1d4ed8'}">${sev.toUpperCase()}</span>
        </div>`;
    }).join('');
    // Mark all as read
    _secNotifs.forEach(n => n.read = true);
    localStorage.setItem('sec_notifs', JSON.stringify(_secNotifs));
}

function showSecToast(notif) {
    const m    = SEC_ICONS[notif.event] || { icon:'fa-circle-dot', tc:'t-info', label: notif.event };
    const tc   = m.tc || 't-info';
    const stack = document.getElementById('secToastStack');
    if (!stack) return;

    const el = document.createElement('div');
    el.className = `sec-toast ${tc}`;
    el.innerHTML = `
        <div class="sec-toast-icon"><i class="fas ${m.icon}"></i></div>
        <div class="sec-toast-body">
            <div class="sec-toast-title">${m.label || notif.event}</div>
            <div class="sec-toast-msg">${notif.ip || ''}${notif.identifier ? ' · ' + notif.identifier : ''}${notif.path ? ' → ' + notif.path : ''}</div>
        </div>
        <button class="sec-toast-close" onclick="dismissSecToast(this.parentElement)">×</button>
        <div class="sec-toast-bar"></div>`;
    el.onclick = e => { if (!e.target.classList.contains('sec-toast-close')) window.location.href = '{{ route("admin.security.soc") }}'; };
    stack.prepend(el);

    // Auto-dismiss after 7s
    setTimeout(() => dismissSecToast(el), 7000);

    // Limit stack to 5 visible toasts
    while (stack.children.length > 5) stack.removeChild(stack.lastChild);
}

function dismissSecToast(el) {
    if (!el || el.classList.contains('out')) return;
    el.classList.add('out');
    setTimeout(() => el.remove(), 280);
}

// Audio — real security alarm sounds
let _secAudioUnlocked = false;
document.addEventListener('click', () => { _secAudioUnlocked = true; }, { once: true });

function secBeep(sev) {
    if (!_secAudioUnlocked) return;
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return;
    try {
        const ctx = new AC();
        ctx.resume().then(() => {
            if (sev === 'critical') {
                // Emergency siren: rapid wail up-down × 3, harsh sawtooth
                for (let i = 0; i < 3; i++) {
                    const osc  = ctx.createOscillator();
                    const gain = ctx.createGain();
                    const dist = ctx.createWaveShaper();
                    // Distortion curve for harsh, cutting sound
                    const curve = new Float32Array(256);
                    for (let j = 0; j < 256; j++) {
                        const x = (j * 2) / 256 - 1;
                        curve[j] = (Math.PI + 400) * x / (Math.PI + 400 * Math.abs(x));
                    }
                    dist.curve = curve;
                    osc.connect(dist); dist.connect(gain); gain.connect(ctx.destination);
                    osc.type = 'sawtooth';
                    const t = ctx.currentTime + i * 0.38;
                    // Sweep: 400Hz → 1200Hz → 400Hz in 0.35s (siren wail)
                    osc.frequency.setValueAtTime(400, t);
                    osc.frequency.linearRampToValueAtTime(1200, t + 0.17);
                    osc.frequency.linearRampToValueAtTime(400,  t + 0.34);
                    gain.gain.setValueAtTime(0, t);
                    gain.gain.linearRampToValueAtTime(0.9, t + 0.02);
                    gain.gain.setValueAtTime(0.9, t + 0.30);
                    gain.gain.linearRampToValueAtTime(0, t + 0.36);
                    osc.start(t);
                    osc.stop(t + 0.38);
                    if (i === 2) osc.onended = () => { try { ctx.close(); } catch(e){} };
                }
            } else {
                // Warning: two sharp descending pulses — like an alarm panel
                [[900, 600], [900, 600]].forEach(([hi, lo], i) => {
                    const osc  = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.type = 'square';
                    const t = ctx.currentTime + i * 0.32;
                    osc.frequency.setValueAtTime(hi, t);
                    osc.frequency.linearRampToValueAtTime(lo, t + 0.18);
                    gain.gain.setValueAtTime(0, t);
                    gain.gain.linearRampToValueAtTime(0.85, t + 0.01);
                    gain.gain.setValueAtTime(0.85, t + 0.15);
                    gain.gain.linearRampToValueAtTime(0, t + 0.28);
                    osc.start(t);
                    osc.stop(t + 0.30);
                    if (i === 1) osc.onended = () => { try { ctx.close(); } catch(e){} };
                });
            }
        });
    } catch(e) {}
}

// ── Polling ──────────────────────────────────────────────────────────────────
async function fetchSecAlerts() {
    try {
        const url  = SEC_ALERTS_URL + '?after=' + encodeURIComponent(_secLastId);
        const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!resp.ok) return;
        const data = await resp.json();
        const events = data.events || [];
        if (events.length === 0) return;

        // Track highest ID seen
        const maxId = Math.max(...events.map(e => e.id));
        if (maxId > _secLastId) { _secLastId = maxId; localStorage.setItem('sec_last_id', maxId); }

        let newCritical = 0;
        events.forEach(ev => {
            // Prepend to local store
            _secNotifs.unshift({ ...ev, read: _secPanelOpen });
            if (!_secPanelOpen) _secUnread++;
            if (SHOW_TOAST_FOR.includes(ev.event)) {
                showSecToast(ev);
                if (ev.severity === 'critical' || ev.severity === 'crit') newCritical++;
            }
        });

        // Trim to 200 stored
        if (_secNotifs.length > 200) _secNotifs = _secNotifs.slice(0, 200);
        localStorage.setItem('sec_notifs', JSON.stringify(_secNotifs));

        if (newCritical > 0) secBeep('critical');
        else if (events.length > 0) secBeep('warn');

        updateSecBadge();
        if (_secPanelOpen) renderSecPanel();
        document.getElementById('secPanelSub').textContent = 'Updated ' + new Date().toLocaleTimeString();
    } catch(e) {}
}

// Initialize from stored notifs
updateSecBadge();
renderSecPanel();

// On first load, set last_id from server so we only get NEW events going forward
(async function initLastId() {
    if (_secLastId > 0) { fetchSecAlerts(); return; }
    try {
        const resp = await fetch(SEC_ALERTS_URL + '?after=0&limit=1', { headers: { 'Accept':'application/json' } });
        const data = await resp.json();
        const events = data.events || [];
        if (events.length > 0) {
            _secLastId = Math.max(...events.map(e => e.id));
            localStorage.setItem('sec_last_id', _secLastId);
        }
    } catch(e) {}
})();

setInterval(fetchSecAlerts, 15000);
</script>
</body>
</html>
