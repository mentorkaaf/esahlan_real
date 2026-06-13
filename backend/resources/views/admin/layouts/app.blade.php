<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — eSahlan Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ── Reset & Base ─────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand:       #FF8A00;
            --brand-dark:  #e07500;
            --navy:        #07003B;
            --sidebar-bg:  #0c0148;
            --sidebar-w:   260px;
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
            background: var(--bg);
            color: var(--text);
            display: flex;
            min-height: 100vh;
            font-size: 14px;
            line-height: 1.55;
        }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #c7cce0; border-radius: 3px; }

        /* ── Sidebar ────────────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--sidebar-bg);
            display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; bottom: 0;
            z-index: 200; overflow: hidden;
        }
        .sidebar::after {
            content: '';
            position: absolute; top: 0; right: 0; bottom: 0;
            width: 1px;
            background: linear-gradient(to bottom,rgba(255,138,0,.35),transparent 50%,rgba(255,138,0,.1));
            pointer-events: none;
        }
        /* Brand */
        .sidebar-brand {
            height: var(--topbar-h);
            display: flex; align-items: center; gap: 12px;
            padding: 0 20px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            text-decoration: none; flex-shrink: 0;
        }
        .brand-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg,var(--brand),#ff6200);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; color: #fff; flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(255,138,0,0.45);
        }
        .brand-name { font-size: 16px; font-weight: 800; color: #fff; letter-spacing: -.3px; }
        .brand-sub  { font-size: 9.5px; color: rgba(255,255,255,0.35); letter-spacing: 1.8px; text-transform: uppercase; margin-top: 1px; }

        /* Nav scroll */
        .sidebar-scroll { flex: 1; overflow-y: auto; padding: 8px 0 20px; }
        .sidebar-scroll::-webkit-scrollbar { width: 3px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); }

        .nav-section-label {
            padding: 16px 20px 5px;
            font-size: 9.5px; font-weight: 700;
            letter-spacing: 1.8px; text-transform: uppercase;
            color: rgba(255,255,255,0.22);
            user-select: none;
        }
        .nav-link {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 14px;
            margin: 1px 8px;
            border-radius: 9px;
            color: rgba(255,255,255,0.58);
            text-decoration: none; font-size: 13.5px; font-weight: 500;
            position: relative; cursor: pointer;
            transition: background .15s, color .15s;
            white-space: nowrap; overflow: hidden;
        }
        .nav-icon {
            width: 30px; height: 30px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; flex-shrink: 0;
            background: rgba(255,255,255,0.05);
            transition: background .15s, color .15s;
        }
        .nav-link:hover { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.9); }
        .nav-link:hover .nav-icon { background: rgba(255,138,0,0.2); color: var(--brand); }
        .nav-link.active {
            background: linear-gradient(90deg,rgba(255,138,0,0.22),rgba(255,138,0,0.06));
            color: #fff;
        }
        .nav-link.active .nav-icon { background: rgba(255,138,0,0.25); color: var(--brand); }
        .nav-link.active::before {
            content: ''; position: absolute; left: -8px; top: 6px; bottom: 6px;
            width: 3px; background: var(--brand); border-radius: 0 3px 3px 0;
        }
        .nav-badge {
            margin-left: auto; background: var(--brand); color: #fff;
            font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 8px;
            flex-shrink: 0;
        }
        /* Submenu */
        .nav-submenu { overflow: hidden; max-height: 0; transition: max-height .3s ease; }
        .nav-submenu.open { max-height: 700px; }
        .nav-submenu .nav-link { padding: 7px 14px; margin-left: 16px; font-size: 13px; }
        .nav-submenu .nav-icon { width: 26px; height: 26px; font-size: 11px; }
        .toggle-arrow { margin-left: auto; font-size: 9px; color: rgba(255,255,255,0.28); transition: transform .25s; flex-shrink: 0; }
        .nav-toggle-btn.open .toggle-arrow { transform: rotate(90deg); }

        /* Sidebar footer */
        .sidebar-footer { padding: 12px; border-top: 1px solid rgba(255,255,255,0.06); flex-shrink: 0; }
        .sidebar-user {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 11px; border-radius: 10px;
            background: rgba(255,255,255,0.05);
        }
        .sidebar-avatar {
            width: 32px; height: 32px;
            background: linear-gradient(135deg,var(--brand),#ff6200);
            border-radius: 8px; font-size: 12px; font-weight: 800; color: #fff;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .sidebar-user-name { font-size: 12px; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user-role { font-size: 10px; color: rgba(255,255,255,0.32); }
        .sidebar-logout-btn {
            background: none; border: none; cursor: pointer;
            color: rgba(255,255,255,0.25); font-size: 14px;
            padding: 4px; border-radius: 6px; flex-shrink: 0;
            transition: color .15s, background .15s;
        }
        .sidebar-logout-btn:hover { color: var(--danger); background: rgba(239,68,68,0.1); }

        /* ── Main Wrapper ──────────────────────────────────────── */
        .main-wrapper { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

        /* ── Topbar ────────────────────────────────────────────── */
        .topbar {
            height: var(--topbar-h);
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 14px;
            padding: 0 28px;
            position: sticky; top: 0; z-index: 100;
            box-shadow: var(--shadow-sm);
        }
        .topbar-title { font-size: 17px; font-weight: 800; color: var(--text); flex: 1; letter-spacing: -.3px; }
        .topbar-search {
            display: flex; align-items: center; gap: 8px;
            background: var(--bg); border: 1.5px solid var(--border);
            border-radius: 10px; padding: 7px 13px; width: 230px;
            transition: border-color .2s, width .25s;
        }
        .topbar-search:focus-within { border-color: var(--brand); width: 270px; box-shadow: 0 0 0 3px rgba(255,138,0,0.08); }
        .topbar-search i { color: var(--text-muted); font-size: 12px; }
        .topbar-search input { border: none; background: transparent; outline: none; font-size: 13px; color: var(--text); width: 100%; }
        .topbar-search input::placeholder { color: var(--text-muted); }
        .topbar-btn {
            width: 36px; height: 36px; border-radius: 9px;
            border: 1.5px solid var(--border); background: transparent;
            color: var(--text-muted); display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 13px; text-decoration: none;
            position: relative; transition: all .15s;
        }
        .topbar-btn:hover { background: var(--bg); color: var(--text); }
        .notif-dot {
            position: absolute; top: 7px; right: 7px;
            width: 7px; height: 7px; background: var(--danger);
            border-radius: 50%; border: 2px solid var(--surface);
        }
        .topbar-divider { width: 1px; height: 26px; background: var(--border); margin: 0 2px; }
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
            cursor: pointer; text-decoration: none; white-space: nowrap;
            transition: all .15s;
        }
        .topbar-logout:hover { background: #fee2e2; }

        /* ── Content ───────────────────────────────────────────── */
        .main-content { flex: 1; padding: 28px; }

        /* ── Page Header ───────────────────────────────────────── */
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
        .breadcrumb a:hover { text-decoration: underline; }

        /* ── Cards ─────────────────────────────────────────────── */
        .card {
            background: var(--surface);
            border-radius: var(--radius); border: 1px solid var(--border);
            box-shadow: var(--shadow-sm); overflow: hidden; margin-bottom: 20px;
        }
        .card-header {
            padding: 15px 20px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            background: var(--surface);
        }
        .card-header-title {
            font-size: 14px; font-weight: 700; color: var(--text);
            display: flex; align-items: center; gap: 9px;
        }
        .card-header-icon {
            width: 28px; height: 28px; border-radius: 7px;
            display: flex; align-items: center; justify-content: center; font-size: 12px;
        }
        .card-body { padding: 20px; }
        .card-footer { padding: 14px 20px; border-top: 1px solid var(--border); background: #fafbff; }

        /* ── Stat Cards ─────────────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit,minmax(210px,1fr));
            gap: 16px; margin-bottom: 24px;
        }
        .stat-card {
            background: var(--surface); border-radius: var(--radius);
            border: 1px solid var(--border); padding: 20px;
            display: flex; align-items: flex-start; gap: 14px;
            position: relative; overflow: hidden;
            box-shadow: var(--shadow-sm);
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
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .stat-icon-wrap.orange { background: rgba(255,138,0,0.1);  color: var(--brand); }
        .stat-icon-wrap.green  { background: rgba(16,185,129,0.1); color: var(--success); }
        .stat-icon-wrap.blue   { background: rgba(59,130,246,0.1); color: var(--info); }
        .stat-icon-wrap.red    { background: rgba(239,68,68,0.1);  color: var(--danger); }
        .stat-icon-wrap.purple { background: rgba(139,92,246,0.1); color: var(--purple); }
        .stat-icon-wrap.teal   { background: rgba(20,184,166,0.1); color: #14b8a6; }
        .stat-body { flex: 1; min-width: 0; }
        .stat-value { font-size: 26px; font-weight: 800; color: var(--text); letter-spacing: -1px; line-height: 1; margin-bottom: 4px; }
        .stat-label { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .stat-sub   { font-size: 11px; margin-top: 7px; display: flex; align-items: center; gap: 4px; font-weight: 600; }
        .stat-sub.up   { color: var(--success); }
        .stat-sub.warn { color: var(--warning); }
        .stat-sub.muted{ color: var(--text-muted); }

        /* ── Tables ─────────────────────────────────────────────── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        thead th {
            padding: 11px 16px; text-align: left;
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .6px; color: var(--text-muted);
            background: #fafbff; border-bottom: 1.5px solid var(--border);
            white-space: nowrap;
        }
        tbody td { padding: 13px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; color: var(--text); }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr { transition: background .1s; }
        tbody tr:hover td { background: #fafbff; }

        /* ── Badges ─────────────────────────────────────────────── */
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 20px;
            font-size: 11px; font-weight: 600; white-space: nowrap;
        }
        .badge-success  { background: rgba(16,185,129,0.1);  color: #059669; }
        .badge-warning  { background: rgba(245,158,11,0.1);  color: #d97706; }
        .badge-danger   { background: rgba(239,68,68,0.1);   color: #dc2626; }
        .badge-info     { background: rgba(59,130,246,0.1);  color: #2563eb; }
        .badge-purple   { background: rgba(139,92,246,0.1);  color: #7c3aed; }
        .badge-teal     { background: rgba(20,184,166,0.1);  color: #0d9488; }
        .badge-secondary{ background: #f1f3fa;               color: #64748b; }
        .badge-orange   { background: rgba(255,138,0,0.1);   color: #c05800; }
        .badge-dark     { background: rgba(7,0,59,0.08);     color: var(--navy); }
        .badge-dot::before { content: ''; display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        /* ── Buttons ─────────────────────────────────────────────── */
        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 8px 16px; border-radius: var(--radius-sm); border: none;
            cursor: pointer; font-size: 13px; font-weight: 600;
            text-decoration: none; transition: all .15s; white-space: nowrap; line-height: 1.2;
            font-family: inherit;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn:active { transform: none; }
        .btn-primary { background: var(--brand); color: #fff; box-shadow: 0 2px 8px rgba(255,138,0,.3); }
        .btn-primary:hover { background: var(--brand-dark); }
        .btn-navy   { background: var(--navy); color: #fff; box-shadow: 0 2px 8px rgba(7,0,59,.2); }
        .btn-success{ background: var(--success); color: #fff; }
        .btn-danger { background: var(--danger);  color: #fff; }
        .btn-info   { background: var(--info);    color: #fff; }
        .btn-outline{ background: transparent; border: 1.5px solid var(--border); color: var(--text); }
        .btn-outline:hover { border-color: #aaa; background: var(--bg); }
        .btn-ghost  { background: transparent; color: var(--text-muted); }
        .btn-ghost:hover { background: var(--bg); color: var(--text); }
        .btn-secondary { background: var(--navy); color: #fff; }
        .btn-sm  { padding: 5px 11px; font-size: 12px; }
        .btn-xs  { padding: 3px 8px;  font-size: 11px; border-radius: 6px; }
        .btn-icon{ width: 34px; height: 34px; padding: 0; justify-content: center; border-radius: 8px; }

        /* ── Forms ──────────────────────────────────────────────── */
        .form-group  { margin-bottom: 16px; }
        .form-label  { display: block; margin-bottom: 6px; font-size: 12.5px; font-weight: 600; color: #4b5563; }
        .form-control {
            width: 100%; padding: 9px 13px;
            border: 1.5px solid var(--border); border-radius: var(--radius-sm);
            font-size: 13.5px; color: var(--text); background: var(--surface);
            outline: none; transition: border-color .2s, box-shadow .2s; font-family: inherit;
        }
        .form-control:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(255,138,0,.1); }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%237b7fa8' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 10px center; background-size: 16px; padding-right: 36px;
        }
        textarea.form-control { resize: vertical; min-height: 80px; }
        .input-group { display: flex; }
        .input-group .form-control { border-radius: var(--radius-sm) 0 0 var(--radius-sm); border-right: none; }
        .input-group .btn { border-radius: 0 var(--radius-sm) var(--radius-sm) 0; }

        /* ── Alerts ─────────────────────────────────────────────── */
        .alert {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 13px 16px; border-radius: var(--radius-sm);
            margin-bottom: 16px; font-size: 13.5px; border-left: 4px solid;
        }
        .alert i { margin-top: 1px; flex-shrink: 0; }
        .alert-success { background: rgba(16,185,129,.08); color: #065f46; border-color: var(--success); }
        .alert-danger  { background: rgba(239,68,68,.08);  color: #991b1b; border-color: var(--danger); }
        .alert-warning { background: rgba(245,158,11,.08); color: #92400e; border-color: var(--warning); }
        .alert-info    { background: rgba(59,130,246,.08); color: #1e3a5f; border-color: var(--info); }

        /* ── Modal ──────────────────────────────────────────────── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.45); backdrop-filter: blur(3px);
            z-index: 1000; align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: var(--surface); border-radius: 16px;
            width: 100%; max-width: 500px;
            box-shadow: var(--shadow-lg); animation: modalIn .2s ease;
        }
        @keyframes modalIn { from { opacity:0; transform:scale(.95) translateY(10px); } to { opacity:1; transform:none; } }
        .modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 24px 16px; border-bottom: 1px solid var(--border);
        }
        .modal-title { font-size: 16px; font-weight: 700; color: var(--text); }
        .modal-close {
            width: 30px; height: 30px; border: none; background: var(--bg);
            border-radius: 7px; color: var(--text-muted); cursor: pointer;
            font-size: 14px; display: flex; align-items: center; justify-content: center;
            transition: all .15s;
        }
        .modal-close:hover { background: #fee2e2; color: var(--danger); }
        .modal-body { padding: 20px 24px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; gap: 10px; justify-content: flex-end; }

        /* ── Grid Utilities ─────────────────────────────────────── */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 20px; }
        .grid-4 { display: grid; grid-template-columns: repeat(4,1fr); gap: 20px; }
        @media (max-width:900px) { .grid-2,.grid-3,.grid-4 { grid-template-columns:1fr; } }

        /* ── Utilities ──────────────────────────────────────────── */
        .d-flex   { display: flex; }
        .align-items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-2  { gap: 8px; } .gap-3 { gap: 12px; } .gap-4 { gap: 16px; }
        .mt-2 { margin-top: 8px; } .mt-3 { margin-top: 12px; } .mt-4 { margin-top: 16px; }
        .mb-2 { margin-bottom: 8px; } .mb-3 { margin-bottom: 12px; } .mb-4 { margin-bottom: 16px; }
        .fw-bold { font-weight: 700; } .fw-medium { font-weight: 500; }
        .text-sm { font-size: 12px; } .text-xs { font-size: 11px; }
        .text-muted   { color: var(--text-muted); }
        .text-success { color: var(--success); }
        .text-danger  { color: var(--danger); }
        .text-warning { color: var(--warning); }
        .text-primary { color: var(--brand); }
        .text-secondary { color: var(--navy); }
        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* ── Avatar ─────────────────────────────────────────────── */
        .avatar {
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 9px; font-weight: 800; color: #fff; flex-shrink: 0;
        }
        .avatar-sm { width: 30px; height: 30px; font-size: 11px; border-radius: 7px; }
        .avatar-md { width: 38px; height: 38px; font-size: 14px; border-radius: 10px; }
        .avatar-lg { width: 52px; height: 52px; font-size: 20px; border-radius: 13px; }
        .avatar-orange { background: linear-gradient(135deg,var(--brand),#ff6200); }
        .avatar-blue   { background: linear-gradient(135deg,var(--info),#1d4ed8); }
        .avatar-green  { background: linear-gradient(135deg,var(--success),#059669); }
        .avatar-purple { background: linear-gradient(135deg,var(--purple),#6d28d9); }

        /* ── Info table ─────────────────────────────────────────── */
        .info-table { width: 100%; }
        .info-table tr td { padding: 9px 0; vertical-align: top; border: none; }
        .info-table tr td:first-child { color: var(--text-muted); font-size: 12px; width: 150px; font-weight: 600; padding-right: 12px; }
        .info-table tr td:last-child  { color: var(--text); font-size: 13px; font-weight: 600; }
        .info-table tr { border-bottom: 1px solid var(--border); }
        .info-table tr:last-child { border-bottom: none; }

        /* ── Pagination ─────────────────────────────────────────── */
        .pagination { display: flex; gap: 4px; list-style: none; flex-wrap: wrap; }
        .pagination li a, .pagination li span {
            display: flex; align-items: center; justify-content: center;
            width: 34px; height: 34px; border-radius: 8px;
            font-size: 13px; font-weight: 600; text-decoration: none;
            color: var(--text-muted); background: var(--surface); border: 1.5px solid var(--border);
            transition: all .15s;
        }
        .pagination li.active span, .pagination li a:hover { background: var(--brand); color: #fff; border-color: var(--brand); }

        /* ── Empty state ────────────────────────────────────────── */
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .empty-state i { font-size: 48px; margin-bottom: 16px; opacity: .25; display: block; }
        .empty-state h3 { font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 6px; }
        .empty-state p { font-size: 13px; }

        /* ── Filter bar ─────────────────────────────────────────── */
        .filter-bar {
            display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
            padding: 14px 20px; border-bottom: 1px solid var(--border); background: #fafbff;
        }
        .filter-bar .form-control { width: auto; min-width: 140px; }
    </style>
    {{-- Override Laravel default pagination to match our design --}}
    <style>
        /* Laravel pagination override */
        nav[role="navigation"] > div:first-child { display:none; }
        nav .flex { display:flex; gap:4px; flex-wrap:wrap; justify-content:center; align-items:center; }
        nav .flex span[aria-current="page"] span,
        nav .flex button {
            display:flex; align-items:center; justify-content:center;
            min-width:34px; height:34px; padding:0 10px;
            border-radius:8px; font-size:13px; font-weight:600;
            text-decoration:none; border:1.5px solid var(--border);
            background:var(--surface); color:var(--text-muted);
            cursor:pointer; transition:all .15s; font-family:inherit;
        }
        nav .flex span[aria-current="page"] span {
            background:var(--brand); color:#fff; border-color:var(--brand);
        }
        nav .flex a {
            display:flex; align-items:center; justify-content:center;
            min-width:34px; height:34px; padding:0 10px;
            border-radius:8px; font-size:13px; font-weight:600;
            text-decoration:none; border:1.5px solid var(--border);
            background:var(--surface); color:var(--text-muted);
            transition:all .15s;
        }
        nav .flex a:hover { background:var(--brand); color:#fff; border-color:var(--brand); }
        nav .flex span[aria-disabled="true"] span {
            opacity:.4; cursor:not-allowed;
        }
        .px-4 { padding-left:16px; padding-right:16px; }
        .py-3 { padding-top:12px; padding-bottom:12px; }
    </style>
    @stack('styles')
</head>
<body>

{{-- ═══════════════════════════════════════════ SIDEBAR ══════════════════════════ --}}
<nav class="sidebar">
    <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-infinity"></i></div>
        <div>
            <div class="brand-name">eSahlan</div>
            <div class="brand-sub">Admin Panel</div>
        </div>
    </a>

    <div class="sidebar-scroll">
        {{-- Main --}}
        <div class="nav-section-label">Main</div>
        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-chart-pie"></i></div> Dashboard
        </a>

        {{-- People --}}
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

        {{-- Operations --}}
        <div class="nav-section-label">Operations</div>
        <a href="{{ route('admin.orders.index') }}" class="nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-shopping-bag"></i></div>
            Orders
            @php try { $__pOrd = \App\Models\Order::where('status','pending')->count(); } catch(\Exception $e){ $__pOrd=0; } @endphp
            @if($__pOrd > 0)<span class="nav-badge">{{ $__pOrd }}</span>@endif
        </a>
        <a href="{{ route('admin.dispatch') }}" class="nav-link {{ request()->routeIs('admin.dispatch') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-map-marked-alt"></i></div> Dispatch
        </a>
        <a href="{{ route('admin.modules.index') }}" class="nav-link {{ request()->routeIs('admin.modules.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-th-large"></i></div> Modules
        </a>

        {{-- Module Data (collapsible) --}}
        <div class="nav-section-label">Module Data</div>
        <div class="nav-link nav-toggle-btn {{ request()->is('admin/module-data*') ? 'open active' : '' }}"
             onclick="toggleNav(this,'moduleDataNav')">
            <div class="nav-icon"><i class="fas fa-database"></i></div>
            Module Data
            <i class="fas fa-chevron-right toggle-arrow"></i>
        </div>
        <div class="nav-submenu {{ request()->is('admin/module-data*') ? 'open' : '' }}" id="moduleDataNav">
            <a href="{{ route('admin.module-data.efood.index') }}" class="nav-link {{ request()->routeIs('admin.module-data.efood*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-utensils"></i></div> eFood
            </a>
            <a href="{{ route('admin.eshop.index') }}" class="nav-link {{ request()->routeIs('admin.eshop*') ? 'active' : '' }}" style="{{ request()->routeIs('admin.eshop*') ? 'background:rgba(255,138,0,0.15);color:#FF8A00;' : '' }}">
                <div class="nav-icon"><i class="fas fa-shopping-bag"></i></div> eShop
            </a>
            <a href="{{ route('admin.module-data.laundry') }}" class="nav-link {{ request()->routeIs('admin.module-data.laundry*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-tshirt"></i></div> eLaundry
            </a>
            <a href="{{ route('admin.module-data.moving') }}" class="nav-link {{ request()->routeIs('admin.module-data.moving*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-truck-moving"></i></div> eMoving
            </a>
            <a href="{{ route('admin.module-data.parcel') }}" class="nav-link {{ request()->routeIs('admin.module-data.parcel*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-box"></i></div> eParcel
            </a>
            <a href="{{ route('admin.module-data.data') }}" class="nav-link {{ request()->routeIs('admin.module-data.data*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-wifi"></i></div> eData
            </a>
            <a href="{{ route('admin.module-data.exchange') }}" class="nav-link {{ request()->routeIs('admin.module-data.exchange*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-exchange-alt"></i></div> eExchange
            </a>
            <a href="{{ route('admin.module-data.health') }}" class="nav-link {{ request()->routeIs('admin.module-data.health*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-user-md"></i></div> eHealth
            </a>
            <a href="{{ route('admin.module-data.rent') }}" class="nav-link {{ request()->routeIs('admin.module-data.rent*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-home"></i></div> eRent
            </a>

            <a href="{{ route('admin.module-data.wholesale') }}" class="nav-link {{ request()->routeIs('admin.module-data.wholesale*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-warehouse"></i></div> Wholesale
            </a>
            <a href="{{ route('admin.module-data.grocery') }}" class="nav-link {{ request()->routeIs('admin.module-data.grocery*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-carrot"></i></div> eGrocery
            </a>
            <a href="{{ route('admin.module-data.ticket') }}" class="nav-link {{ request()->routeIs('admin.module-data.ticket*') ? 'active' : '' }}">
                <div class="nav-icon"><i class="fas fa-plane"></i></div> eTicket
            </a>
        </div>

        {{-- Finance --}}
        <div class="nav-section-label">Finance</div>
        <a href="{{ route('admin.finance.index') }}" class="nav-link {{ request()->routeIs('admin.finance.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-chart-line"></i></div> Finance
        </a>
        <a href="{{ route('admin.wallet.index') }}" class="nav-link {{ request()->routeIs('admin.wallet.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-wallet"></i></div> Wallet
        </a>
        <a href="{{ route('admin.reports.sales') }}" class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-chart-bar"></i></div> Reports
        </a>

        {{-- Community --}}
        <div class="nav-section-label">Community</div>
        <a href="{{ route('admin.community.index') }}" class="nav-link {{ request()->routeIs('admin.community.index') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-users"></i></div> Overview
        </a>
        <a href="{{ route('admin.community.posts') }}" class="nav-link {{ request()->routeIs('admin.community.posts*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-th-large"></i></div> Posts
        </a>
        <a href="{{ route('admin.community.groups') }}" class="nav-link {{ request()->routeIs('admin.community.groups*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-layer-group"></i></div> Groups
        </a>
        <a href="{{ route('admin.community.reports') }}" class="nav-link {{ request()->routeIs('admin.community.reports*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-flag"></i></div> Reports
        </a>

        {{-- Marketing --}}
        <div class="nav-section-label">Marketing</div>
        <a href="{{ route('admin.banners.index') }}" class="nav-link {{ request()->routeIs('admin.banners.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-image"></i></div> Banners
        </a>
        <a href="{{ route('admin.notifications.index') }}" class="nav-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-bell"></i></div> Notifications
        </a>
        <a href="{{ route('admin.landing.index') }}" class="nav-link {{ request()->routeIs('admin.landing.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-globe"></i></div> Landing Page
        </a>

        {{-- System --}}
        <div class="nav-section-label">System</div>
        <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
            <div class="nav-icon"><i class="fas fa-cog"></i></div> Settings
        </a>
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

{{-- ═══════════════════════════════════════════ MAIN ════════════════════════════ --}}
<div class="main-wrapper">
    <header class="topbar">
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
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
document.addEventListener('click', e => { if (e.target.classList.contains('modal-overlay')) closeModal(e.target.id); });

function toggleNav(btn, menuId) {
    btn.classList.toggle('open');
    document.getElementById(menuId)?.classList.toggle('open');
}

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
async function postRequest(url, data = {}) {
    const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify(data),
    });
    return r.json();
}

document.getElementById('globalSearch')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
        const q = e.target.value.trim();
        if (q) window.location.href = '{{ route("admin.orders.index") }}?search=' + encodeURIComponent(q);
    }
});
</script>
@stack('scripts')
</body>
</html>
