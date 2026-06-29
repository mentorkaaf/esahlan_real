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
            <div class="nav-icon"><i class="fas fa-wallet"></i></div> Wallet
        </a>
        <a href="{{ route('admin.dispatch') }}" class="nav-link {{ request()->routeIs('admin.dispatch') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-map-marked-alt"></i></div> Dispatch
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
        <a href="{{ route('admin.notifications.index') }}" class="nav-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-bell"></i></div> Notifications
        </a>

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
            <a href="{{ route('admin.community-ads.index') }}" class="nav-link {{ request()->routeIs('admin.community-ads.*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-bullhorn"></i></div> Ads & Pages
            </a>
        </div>
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
</body>
</html>
