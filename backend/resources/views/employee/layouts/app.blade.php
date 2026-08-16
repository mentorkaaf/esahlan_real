<!DOCTYPE html>
<html lang="so" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'My Portal') — eSahlan Staff</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --brand:       #F7941D;
      --brand-dark:  #e07800;
      --brand-light: #fff5e6;
      --navy:        #0f0b2e;
      --navy2:       #1a1550;
      --navy3:       #231d6a;
      --surface:     #f0f2f8;
      --white:       #ffffff;
      --border:      #e2e5f0;
      --border-soft: #eef0f7;
      --text:        #0f172a;
      --text2:       #374151;
      --muted:       #6b7280;
      --muted2:      #9ca3af;
      --green:       #059669;
      --green-bg:    #d1fae5;
      --red:         #dc2626;
      --red-bg:      #fee2e2;
      --yellow:      #d97706;
      --yellow-bg:   #fef3c7;
      --blue:        #2563eb;
      --blue-bg:     #dbeafe;
      --purple:      #7c3aed;
      --purple-bg:   #ede9fe;
      --sidebar-w:   240px;
      --topbar-h:    60px;
      --radius:      14px;
      --radius-lg:   20px;
      --shadow-sm:   0 1px 4px rgba(15,11,46,.06);
      --shadow:      0 4px 16px rgba(15,11,46,.1);
      --shadow-lg:   0 12px 40px rgba(15,11,46,.18);
    }

    html, body { height: 100%; }
    body {
      font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
      background: var(--surface);
      color: var(--text);
      display: flex;
      flex-direction: column;
      font-size: 14px;
      line-height: 1.5;
    }

    /* ═══════════════════════════════════════════════════════════
       TOPBAR
    ═══════════════════════════════════════════════════════════ */
    .topbar {
      background: var(--navy);
      color: #fff;
      height: var(--topbar-h);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 24px;
      position: sticky;
      top: 0;
      z-index: 200;
      box-shadow: 0 2px 20px rgba(0,0,0,.35);
      flex-shrink: 0;
    }

    .topbar-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
    }
    .topbar-logo {
      width: 38px;
      height: 38px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--brand), var(--brand-dark));
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 900;
      font-size: 18px;
      color: #fff;
      box-shadow: 0 0 0 2px rgba(247,148,29,.3);
      letter-spacing: -1px;
    }
    .topbar-name {
      font-weight: 800;
      font-size: 16px;
      color: #fff;
      letter-spacing: -.3px;
    }
    .topbar-name b { color: var(--brand); }
    .topbar-tag {
      font-size: 9px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .12em;
      color: rgba(255,255,255,.35);
      display: block;
      margin-top: -2px;
    }

    .topbar-right { display: flex; align-items: center; gap: 12px; }

    /* Bell */
    .bell-wrap { position: relative; }
    .bell-btn {
      background: rgba(255,255,255,.07);
      border: 1px solid rgba(255,255,255,.12);
      color: rgba(255,255,255,.75);
      width: 38px;
      height: 38px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      position: relative;
      transition: all .15s;
    }
    .bell-btn:hover { background: rgba(255,255,255,.15); color: #fff; border-color: rgba(255,255,255,.25); }
    .bell-badge {
      position: absolute;
      top: -5px; right: -5px;
      background: #ef4444;
      color: #fff;
      font-size: 9px;
      font-weight: 800;
      min-width: 17px;
      height: 17px;
      border-radius: 99px;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 0 4px;
      border: 2px solid var(--navy);
    }
    .bell-badge.show { display: flex; }

    .notif-dropdown {
      position: absolute;
      right: 0;
      top: calc(100% + 12px);
      width: 360px;
      background: #fff;
      border-radius: 18px;
      box-shadow: var(--shadow-lg), 0 0 0 1px rgba(15,11,46,.06);
      z-index: 999;
      display: none;
      overflow: hidden;
    }
    .notif-dropdown.open { display: block; }
    .notif-header {
      padding: 16px 18px;
      border-bottom: 1px solid var(--border-soft);
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: linear-gradient(to right, #fafbff, #f8f9ff);
    }
    .notif-header-title { font-size: 13px; font-weight: 800; color: var(--navy); }
    .notif-read-all {
      font-size: 11px;
      font-weight: 700;
      color: var(--brand);
      background: none;
      border: none;
      cursor: pointer;
      padding: 4px 10px;
      border-radius: 6px;
      transition: background .15s;
    }
    .notif-read-all:hover { background: var(--brand-light); }
    .notif-list { max-height: 360px; overflow-y: auto; }
    .notif-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 14px 18px;
      border-bottom: 1px solid var(--border-soft);
      cursor: pointer;
      transition: background .1s;
    }
    .notif-item:last-child { border-bottom: none; }
    .notif-item:hover { background: #f8f9ff; }
    .notif-item.unread { background: #fffbf4; }
    .notif-item.unread:hover { background: #fff5e6; }
    .notif-icon {
      width: 34px;
      height: 34px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      flex-shrink: 0;
    }
    .notif-title { font-size: 12px; font-weight: 700; color: var(--text); line-height: 1.3; }
    .notif-body  { font-size: 11px; color: var(--muted); margin-top: 2px; line-height: 1.4; }
    .notif-time  { font-size: 10px; color: var(--muted2); margin-top: 4px; }
    .notif-empty { padding: 36px; text-align: center; }
    .notif-empty i { font-size: 28px; opacity: .2; display: block; margin-bottom: 10px; color: var(--navy); }
    .notif-empty p { font-size: 12px; color: var(--muted2); }

    /* Employee chip in topbar */
    .topbar-emp {
      display: flex;
      align-items: center;
      gap: 10px;
      background: rgba(255,255,255,.07);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 12px;
      padding: 5px 12px 5px 6px;
    }
    .topbar-avatar {
      width: 30px;
      height: 30px;
      border-radius: 9px;
      background: linear-gradient(135deg, var(--brand), var(--brand-dark));
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 11px;
      flex-shrink: 0;
    }
    .topbar-emp-name { font-size: 12px; font-weight: 700; color: #fff; line-height: 1.2; }
    .topbar-emp-no   { font-size: 10px; color: rgba(255,255,255,.45); }

    .logout-btn {
      background: rgba(255,255,255,.07);
      border: 1px solid rgba(255,255,255,.12);
      color: rgba(255,255,255,.7);
      padding: 7px 14px;
      border-radius: 10px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all .15s;
    }
    .logout-btn:hover { background: rgba(220,38,38,.25); border-color: rgba(220,38,38,.4); color: #fca5a5; }

    /* ═══════════════════════════════════════════════════════════
       TOAST
    ═══════════════════════════════════════════════════════════ */
    .toast-stack {
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 10px;
      pointer-events: none;
    }
    .toast {
      background: #fff;
      border-radius: 16px;
      box-shadow: var(--shadow-lg);
      border: 1px solid var(--border-soft);
      padding: 14px 16px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
      min-width: 300px;
      max-width: 360px;
      pointer-events: all;
      animation: toastIn .3s cubic-bezier(.34,1.56,.64,1);
    }
    .toast-icon {
      width: 34px;
      height: 34px;
      border-radius: 10px;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
    }
    .toast-title { font-size: 12px; font-weight: 800; color: var(--text); }
    .toast-body  { font-size: 11px; color: var(--muted); margin-top: 2px; line-height: 1.4; }
    .toast-close {
      margin-left: auto;
      background: none;
      border: none;
      cursor: pointer;
      color: var(--muted2);
      font-size: 14px;
      padding: 0 0 0 8px;
      flex-shrink: 0;
      transition: color .1s;
    }
    .toast-close:hover { color: var(--text); }
    @keyframes toastIn {
      from { opacity: 0; transform: translateY(16px) scale(.95); }
      to   { opacity: 1; transform: none; }
    }

    /* ═══════════════════════════════════════════════════════════
       SUSPENDED BANNER
    ═══════════════════════════════════════════════════════════ */
    .suspended-banner {
      background: linear-gradient(90deg, #fef3c7, #fffbeb);
      border-bottom: 1px solid #fde68a;
      padding: 10px 24px;
      font-size: 13px;
      color: #78350f;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* ═══════════════════════════════════════════════════════════
       LAYOUT SHELL
    ═══════════════════════════════════════════════════════════ */
    .layout {
      display: flex;
      flex: 1;
      overflow: hidden;
      height: calc(100vh - var(--topbar-h));
    }

    /* ═══════════════════════════════════════════════════════════
       SIDEBAR
    ═══════════════════════════════════════════════════════════ */
    .sidebar {
      width: var(--sidebar-w);
      flex-shrink: 0;
      background: var(--navy);
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      scrollbar-width: thin;
      scrollbar-color: rgba(255,255,255,.1) transparent;
    }
    .sidebar::-webkit-scrollbar { width: 4px; }
    .sidebar::-webkit-scrollbar-track { background: transparent; }
    .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 4px; }

    @media (max-width: 768px) {
      .sidebar { display: none; }
      .layout { flex-direction: column; }
      .main { padding: 16px; }
    }

    /* Sidebar top: employee card */
    .sidebar-empcard {
      margin: 16px 14px 12px;
      padding: 14px;
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 14px;
    }
    .sidebar-emp-avatar {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--brand), var(--brand-dark));
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 15px;
      margin-bottom: 10px;
    }
    .sidebar-emp-name {
      font-size: 13px;
      font-weight: 700;
      color: #fff;
      line-height: 1.2;
    }
    .sidebar-emp-pos {
      font-size: 11px;
      color: rgba(255,255,255,.45);
      margin-top: 2px;
    }
    .sidebar-emp-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
    .sidebar-pill {
      font-size: 9px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      padding: 2px 8px;
      border-radius: 20px;
    }
    .sidebar-pill-green  { background: rgba(5,150,105,.2);  color: #34d399; }
    .sidebar-pill-yellow { background: rgba(217,119,6,.2);  color: #fbbf24; }
    .sidebar-pill-blue   { background: rgba(37,99,235,.2);  color: #60a5fa; }
    .sidebar-pill-gray   { background: rgba(255,255,255,.1); color: rgba(255,255,255,.5); }

    /* Sidebar nav */
    .sidebar-group { padding: 8px 14px 4px; }
    .sidebar-group-label {
      font-size: 9px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .14em;
      color: rgba(255,255,255,.25);
      padding: 0 8px;
      margin-bottom: 4px;
    }

    .nav-link {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 12px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 500;
      color: rgba(255,255,255,.55);
      text-decoration: none;
      transition: all .15s;
      margin-bottom: 2px;
      position: relative;
    }
    .nav-link-icon {
      width: 30px;
      height: 30px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      flex-shrink: 0;
      background: rgba(255,255,255,.06);
      transition: all .15s;
    }
    .nav-link:hover {
      background: rgba(255,255,255,.06);
      color: rgba(255,255,255,.9);
    }
    .nav-link:hover .nav-link-icon {
      background: rgba(255,255,255,.1);
    }
    .nav-link.active {
      background: rgba(247,148,29,.12);
      color: var(--brand);
    }
    .nav-link.active .nav-link-icon {
      background: rgba(247,148,29,.2);
      color: var(--brand);
    }
    .nav-link-badge {
      margin-left: auto;
      font-size: 9px;
      font-weight: 800;
      padding: 2px 7px;
      border-radius: 20px;
      flex-shrink: 0;
    }
    .nav-link-badge-red    { background: rgba(220,38,38,.2);  color: #f87171; }
    .nav-link-badge-blue   { background: rgba(37,99,235,.2);  color: #60a5fa; }
    .nav-link-badge-orange { background: rgba(247,148,29,.2); color: var(--brand); }

    /* Workspace chips in sidebar */
    .sidebar-ws-wrap { padding: 4px 14px 12px; }
    .sidebar-ws-chip {
      display: flex;
      align-items: center;
      gap: 9px;
      padding: 8px 12px;
      border-radius: 10px;
      font-size: 12px;
      font-weight: 600;
      color: rgba(255,255,255,.55);
      text-decoration: none;
      transition: all .15s;
      margin-bottom: 2px;
    }
    .sidebar-ws-chip:hover   { background: rgba(255,255,255,.06); color: rgba(255,255,255,.85); }
    .sidebar-ws-chip.current {
      background: rgba(247,148,29,.1);
      color: var(--brand);
      border: 1px solid rgba(247,148,29,.2);
    }
    .sidebar-ws-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      flex-shrink: 0;
    }

    .sidebar-divider {
      height: 1px;
      background: rgba(255,255,255,.06);
      margin: 4px 14px 8px;
    }

    /* ═══════════════════════════════════════════════════════════
       MAIN CONTENT
    ═══════════════════════════════════════════════════════════ */
    .main {
      flex: 1;
      overflow-y: auto;
      padding: 28px 32px;
      background: var(--surface);
    }

    /* ═══════════════════════════════════════════════════════════
       FLASH MESSAGES
    ═══════════════════════════════════════════════════════════ */
    .flash {
      padding: 13px 18px;
      border-radius: 12px;
      font-size: 13px;
      font-weight: 500;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      box-shadow: var(--shadow-sm);
    }
    .flash i { font-size: 15px; flex-shrink: 0; }
    .flash-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
    .flash-error   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .flash-warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }

    /* ═══════════════════════════════════════════════════════════
       CARDS
    ═══════════════════════════════════════════════════════════ */
    .card {
      background: var(--white);
      border-radius: var(--radius);
      border: 1px solid var(--border);
      box-shadow: var(--shadow-sm);
    }
    .card-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--border-soft);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .card-title {
      font-size: 11px;
      font-weight: 800;
      color: var(--navy2);
      text-transform: uppercase;
      letter-spacing: .08em;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .card-title i {
      color: var(--brand);
    }
    .card-body { padding: 20px; }

    /* KPI card */
    .kpi-card {
      background: var(--white);
      border-radius: var(--radius);
      border: 1px solid var(--border);
      box-shadow: var(--shadow-sm);
      padding: 20px;
      position: relative;
      overflow: hidden;
      transition: box-shadow .2s, transform .2s;
    }
    .kpi-card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }
    .kpi-icon-wrap {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      margin-bottom: 14px;
    }
    .kpi-label {
      font-size: 10px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .1em;
      color: var(--muted);
      margin-bottom: 6px;
    }
    .kpi-value {
      font-size: 30px;
      font-weight: 900;
      color: var(--text);
      line-height: 1;
    }
    .kpi-sub {
      font-size: 12px;
      color: var(--muted);
      margin-top: 5px;
    }
    .kpi-accent {
      position: absolute;
      bottom: 0;
      right: 0;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      opacity: .06;
      transform: translate(20%, 20%);
    }

    /* ═══════════════════════════════════════════════════════════
       BADGES
    ═══════════════════════════════════════════════════════════ */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 3px 9px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
    }
    .badge-green  { background: var(--green-bg);  color: #065f46; }
    .badge-red    { background: var(--red-bg);    color: #7f1d1d; }
    .badge-yellow { background: var(--yellow-bg); color: #78350f; }
    .badge-blue   { background: var(--blue-bg);   color: #1e3a8a; }
    .badge-gray   { background: #f3f4f6;          color: #374151; }
    .badge-orange { background: var(--brand-light); color: #9a3412; }
    .badge-purple { background: var(--purple-bg); color: #4c1d95; }

    /* ═══════════════════════════════════════════════════════════
       BUTTONS
    ═══════════════════════════════════════════════════════════ */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 9px 18px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      border: none;
      text-decoration: none;
      transition: all .15s;
      white-space: nowrap;
    }
    .btn-primary {
      background: linear-gradient(135deg, var(--brand), var(--brand-dark));
      color: #fff;
      box-shadow: 0 2px 8px rgba(247,148,29,.35);
    }
    .btn-primary:hover {
      background: linear-gradient(135deg, #e07800, #c96500);
      box-shadow: 0 4px 16px rgba(247,148,29,.45);
      transform: translateY(-1px);
    }
    .btn-outline {
      background: transparent;
      color: var(--text2);
      border: 1.5px solid var(--border);
    }
    .btn-outline:hover { border-color: var(--brand); color: var(--brand); background: var(--brand-light); }
    .btn-ghost {
      background: transparent;
      color: var(--muted);
      border: none;
    }
    .btn-ghost:hover { background: #f3f4f6; color: var(--text); }
    .btn-sm { padding: 6px 13px; font-size: 12px; border-radius: 8px; }
    .btn-xs { padding: 4px 10px; font-size: 11px; border-radius: 7px; }
    .btn-danger { background: var(--red-bg); color: var(--red); border: 1.5px solid #fca5a5; }
    .btn-danger:hover { background: #dc2626; color: #fff; border-color: #dc2626; }

    /* ═══════════════════════════════════════════════════════════
       TABLE
    ═══════════════════════════════════════════════════════════ */
    .table-wrap { overflow-x: auto; border-radius: var(--radius); }
    table.data-table { width: 100%; border-collapse: collapse; }
    table.data-table thead th {
      font-size: 10px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: var(--muted);
      padding: 12px 16px;
      background: #f8f9fc;
      border-bottom: 1px solid var(--border);
      text-align: left;
      white-space: nowrap;
    }
    table.data-table tbody td {
      padding: 13px 16px;
      font-size: 13px;
      color: var(--text2);
      border-bottom: 1px solid var(--border-soft);
    }
    table.data-table tbody tr:last-child td { border-bottom: none; }
    table.data-table tbody tr:hover td { background: #fafbff; }

    /* ═══════════════════════════════════════════════════════════
       PAGE HEADER HELPER
    ═══════════════════════════════════════════════════════════ */
    .page-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 24px;
    }
    .page-title {
      font-size: 20px;
      font-weight: 900;
      color: var(--navy);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .page-title-icon {
      width: 38px;
      height: 38px;
      border-radius: 11px;
      background: linear-gradient(135deg, var(--brand), var(--brand-dark));
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 16px;
      flex-shrink: 0;
    }
    .page-sub { font-size: 13px; color: var(--muted); margin-top: 3px; }

    /* ═══════════════════════════════════════════════════════════
       FORM ELEMENTS
    ═══════════════════════════════════════════════════════════ */
    .form-label {
      display: block;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .07em;
      color: var(--muted);
      margin-bottom: 6px;
    }
    .form-input {
      width: 100%;
      padding: 10px 14px;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      font-size: 13px;
      color: var(--text);
      background: var(--white);
      transition: border-color .15s, box-shadow .15s;
    }
    .form-input:focus {
      outline: none;
      border-color: var(--brand);
      box-shadow: 0 0 0 3px rgba(247,148,29,.12);
    }
    .form-select {
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath fill='%236b7280' d='M7 7l3 3 3-3z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 10px center;
      background-size: 16px;
      padding-right: 36px;
    }
    textarea.form-input { resize: vertical; min-height: 90px; }

    /* ═══════════════════════════════════════════════════════════
       EMPTY STATE
    ═══════════════════════════════════════════════════════════ */
    .empty-state {
      text-align: center;
      padding: 56px 32px;
    }
    .empty-state-icon {
      font-size: 40px;
      opacity: .2;
      display: block;
      margin-bottom: 14px;
      color: var(--navy2);
    }
    .empty-state-title { font-size: 15px; font-weight: 700; color: var(--text2); }
    .empty-state-sub   { font-size: 13px; color: var(--muted); margin-top: 6px; }

    @yield('extra-styles')
  </style>
  @stack('head')
</head>
<body>
@php $emp = auth('employee')->user(); @endphp

{{-- Topbar --}}
<header class="topbar">
  <a href="{{ route('employee.dashboard') }}" class="topbar-brand">
    <div class="topbar-logo">e</div>
    <div>
      <div class="topbar-name">e<b>Sahlan</b></div>
      <span class="topbar-tag">Staff Portal</span>
    </div>
  </a>

  <div class="topbar-right">
    {{-- Bell --}}
    <div class="bell-wrap" id="bellWrap">
      <button class="bell-btn" id="bellBtn" onclick="toggleNotifDropdown()" aria-label="Notifications">
        <i class="fas fa-bell" style="font-size:14px;"></i>
        <span class="bell-badge" id="bellBadge"></span>
      </button>
      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-header">
          <span class="notif-header-title">🔔 Xayeysiisyada</span>
          <button class="notif-read-all" onclick="markAllRead()">Dhammaan akhri</button>
        </div>
        <div class="notif-list" id="notifList">
          <div class="notif-empty">
            <i class="fas fa-bell-slash"></i>
            <p>Weli wax la'aan</p>
          </div>
        </div>
      </div>
    </div>

    <div class="topbar-emp">
      <div class="topbar-avatar">
        {{ strtoupper(substr($emp->first_name,0,1).substr($emp->last_name,0,1)) }}
      </div>
      <div>
        <div class="topbar-emp-name">{{ $emp->first_name }}</div>
        <div class="topbar-emp-no">{{ $emp->employee_no }}</div>
      </div>
    </div>

    <form action="{{ route('employee.logout') }}" method="POST">
      @csrf
      <button type="submit" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Bax
      </button>
    </form>
  </div>
</header>

@if($emp->status === 'suspended')
<div class="suspended-banner">
  <i class="fas fa-exclamation-triangle" style="color:#d97706;"></i>
  <strong>Akoon-kaagu waa la dhigay hakad.</strong>
  Waxaad arki kartaa macluumaadkaaga laakiin waxyaabaha aad samayn karto ayaa xaddidnaa. Xiriir HR wixii faahfaahin ah.
</div>
@endif

<div class="layout">
  {{-- ── SIDEBAR ─────────────────────────────────────────────── --}}
  <nav class="sidebar">

    {{-- Employee card --}}
    <div class="sidebar-empcard">
      <div class="sidebar-emp-avatar">
        {{ strtoupper(substr($emp->first_name,0,1).substr($emp->last_name,0,1)) }}
      </div>
      <div class="sidebar-emp-name">{{ $emp->full_name }}</div>
      <div class="sidebar-emp-pos">
        {{ $emp->position?->title ?? '—' }} · {{ $emp->department?->name ?? '—' }}
      </div>
      <div class="sidebar-emp-badges">
        @php
          $pillClass = match($emp->status) {
            'active'    => 'sidebar-pill-green',
            'suspended' => 'sidebar-pill-yellow',
            'probation' => 'sidebar-pill-blue',
            default     => 'sidebar-pill-gray'
          };
        @endphp
        <span class="sidebar-pill {{ $pillClass }}">{{ ucfirst($emp->status) }}</span>
        @if($emp->employment_type)
          <span class="sidebar-pill sidebar-pill-gray">{{ ucfirst(str_replace('_',' ',$emp->employment_type)) }}</span>
        @endif
      </div>
    </div>

    {{-- My Workspaces --}}
    @php
      $myAssignments = $emp->workforceAssignments()->where('status','active')->with('module')->get();
      $currentSlug = request()->route('slug') ?? '';
    @endphp
    @if($myAssignments->isNotEmpty())
      <div class="sidebar-group">
        <div class="sidebar-group-label">Workspaces-kayga</div>
      </div>
      <div class="sidebar-ws-wrap">
        @foreach($myAssignments as $a)
          @php $mod = $a->module; if(!$mod) continue; @endphp
          <a href="{{ route('employee.workspace', $mod->slug) }}"
             class="sidebar-ws-chip {{ $currentSlug === $mod->slug ? 'current' : '' }}">
            <div class="sidebar-ws-dot" style="background:{{ $mod->color ?? 'var(--brand)' }};"></div>
            <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $mod->name }}</span>
            @if($a->assignment_type === 'primary')
              <span style="font-size:8px;opacity:.5;flex-shrink:0;">P</span>
            @endif
          </a>
        @endforeach
      </div>
      <div class="sidebar-divider"></div>
    @endif

    {{-- Portal nav --}}
    <div class="sidebar-group">
      <div class="sidebar-group-label">Portal</div>
      <a href="{{ route('employee.dashboard') }}"
         class="nav-link {{ request()->routeIs('employee.dashboard') ? 'active' : '' }}">
        <div class="nav-link-icon"><i class="fas fa-home"></i></div>
        Dashboard
      </a>
      <a href="{{ route('employee.performance') }}"
         class="nav-link {{ request()->routeIs('employee.performance') ? 'active' : '' }}">
        <div class="nav-link-icon"><i class="fas fa-chart-line"></i></div>
        Performance
      </a>
      <a href="{{ route('employee.attendance') }}"
         class="nav-link {{ request()->routeIs('employee.attendance') ? 'active' : '' }}">
        <div class="nav-link-icon"><i class="fas fa-calendar-check"></i></div>
        Attendance
      </a>
      <a href="{{ route('employee.leaves') }}"
         class="nav-link {{ request()->routeIs('employee.leaves*') ? 'active' : '' }}">
        <div class="nav-link-icon"><i class="fas fa-umbrella-beach"></i></div>
        Leave
        @php $pendingLeaveCount = \App\Models\HR\HrLeaveRequest::where('employee_id',$emp->id)->where('status','pending')->count(); @endphp
        @if($pendingLeaveCount > 0)
          <span class="nav-link-badge nav-link-badge-red">{{ $pendingLeaveCount }}</span>
        @endif
      </a>
    </div>

    <div class="sidebar-divider"></div>

    <div class="sidebar-group">
      <div class="sidebar-group-label">Self-Service</div>
      <a href="{{ route('employee.payslips') }}"
         class="nav-link {{ request()->routeIs('employee.payslips*') ? 'active' : '' }}">
        <div class="nav-link-icon"><i class="fas fa-file-invoice-dollar"></i></div>
        Payslips
      </a>
      <a href="{{ route('employee.documents') }}"
         class="nav-link {{ request()->routeIs('employee.documents*') ? 'active' : '' }}">
        <div class="nav-link-icon"><i class="fas fa-folder-open"></i></div>
        Documents
      </a>
      <a href="{{ route('employee.announcements') }}"
         class="nav-link {{ request()->routeIs('employee.announcements*') ? 'active' : '' }}">
        <div class="nav-link-icon"><i class="fas fa-bullhorn"></i></div>
        Announcements
        @php
          $unreadAnnCount = \App\Models\HR\HrAnnouncement::query()
            ->where(fn($q) => $q->where('audience','all')
              ->orWhere(fn($q2) => $q2->where('audience','department')->where('department_id',$emp->department_id)))
            ->whereNotNull('published_at')->where('published_at','<=',now())
            ->where('created_at','>=',now()->subDays(3))->count();
        @endphp
        @if($unreadAnnCount)
          <span class="nav-link-badge nav-link-badge-orange">{{ $unreadAnnCount }}</span>
        @endif
      </a>
    </div>

    <div class="sidebar-divider"></div>

    <div class="sidebar-group" style="margin-bottom:16px;">
      <div class="sidebar-group-label">Account</div>
      <a href="{{ route('employee.profile') }}"
         class="nav-link {{ request()->routeIs('employee.profile*') ? 'active' : '' }}">
        <div class="nav-link-icon"><i class="fas fa-user-circle"></i></div>
        Profile
      </a>
    </div>

  </nav>

  {{-- ── MAIN ────────────────────────────────────────────────── --}}
  <main class="main">
    @if(session('success'))
      <div class="flash flash-success">
        <i class="fas fa-check-circle"></i>
        {{ session('success') }}
      </div>
    @endif
    @if(session('error') || $errors->any())
      <div class="flash flash-error">
        <i class="fas fa-exclamation-circle"></i>
        {{ session('error') ?? $errors->first() }}
      </div>
    @endif

    @yield('content')
  </main>
</div>

{{-- Toast stack --}}
<div class="toast-stack" id="toastStack"></div>

@yield('scripts')
@stack('scripts')

<script>
// ── Notifications ──────────────────────────────────────────────────────
const NOTIF_URL     = '{{ route("employee.notifications") }}';
const MARK_READ_URL = '{{ route("employee.notifications.read-all") }}';
const CSRF          = '{{ csrf_token() }}';

let notifOpen   = false;
let notifLoaded = false;

function toggleNotifDropdown() {
  const drop = document.getElementById('notifDropdown');
  notifOpen = !notifOpen;
  drop.classList.toggle('open', notifOpen);
  if (notifOpen && !notifLoaded) { fetchNotifications(); notifLoaded = true; }
}

document.addEventListener('click', function(e) {
  if (notifOpen && !document.getElementById('bellWrap').contains(e.target)) {
    notifOpen = false;
    document.getElementById('notifDropdown').classList.remove('open');
  }
});

function fetchNotifications() {
  fetch(NOTIF_URL, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } })
    .then(r => r.json())
    .then(data => { renderNotifications(data.notifications, data.unread); setBadge(data.unread); })
    .catch(() => {});
}

function renderNotifications(items, unread) {
  const list = document.getElementById('notifList');
  if (!items || !items.length) {
    list.innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash"></i><p>Weli wax la\'aan</p></div>';
    return;
  }
  list.innerHTML = items.map(n => `
    <div class="notif-item ${n.read ? '' : 'unread'}"
         onclick="onNotifClick(${n.id}, '${escapeJs(n.url || '')}')">
      <div class="notif-icon" style="background:${lighten(n.color)};color:${n.color};">
        <i class="${escapeJs(n.icon || 'fas fa-bell')}"></i>
      </div>
      <div style="flex:1;min-width:0;">
        <div class="notif-title">${escapeHtml(n.title)}</div>
        ${n.body ? `<div class="notif-body">${escapeHtml(n.body)}</div>` : ''}
        <div class="notif-time">${escapeHtml(n.time)}</div>
      </div>
      ${!n.read ? '<div style="width:6px;height:6px;border-radius:50%;background:var(--brand);flex-shrink:0;margin-top:4px;"></div>' : ''}
    </div>
  `).join('');
}

function onNotifClick(id, url) {
  fetch(`/employee/notifications/${id}/read`, {
    method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
  }).catch(() => {});
  if (url) window.location.href = url;
}

function markAllRead() {
  fetch(MARK_READ_URL, {
    method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
  }).then(() => {
    setBadge(0);
    document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
  }).catch(() => {});
}

function setBadge(count) {
  const badge = document.getElementById('bellBadge');
  if (count > 0) {
    badge.textContent = count > 99 ? '99+' : count;
    badge.classList.add('show');
  } else {
    badge.classList.remove('show');
  }
}

function pollBadge() {
  fetch('{{ route("employee.notifications.count") }}', { headers: { 'Accept': 'application/json' } })
    .then(r => r.json()).then(d => setBadge(d.count)).catch(() => {});
}
pollBadge();
setInterval(pollBadge, 60000);

// ── Toast ──────────────────────────────────────────────────────────────
function showToast(notif) {
  const stack = document.getElementById('toastStack');
  const id    = 'toast-' + Date.now();
  const div   = document.createElement('div');
  div.className = 'toast';
  div.id = id;
  div.innerHTML = `
    <div class="toast-icon" style="background:${lighten(notif.color)};color:${notif.color};">
      <i class="${escapeJs(notif.icon || 'fas fa-bell')}"></i>
    </div>
    <div style="flex:1;">
      <div class="toast-title">${escapeHtml(notif.title)}</div>
      ${notif.body ? `<div class="toast-body">${escapeHtml(notif.body)}</div>` : ''}
    </div>
    <button class="toast-close" onclick="removeToast('${id}')">&times;</button>
  `;
  if (notif.url) {
    div.style.cursor = 'pointer';
    div.onclick = (e) => { if (!e.target.classList.contains('toast-close')) window.location.href = notif.url; };
  }
  stack.appendChild(div);
  setTimeout(() => removeToast(id), 6000);
  setBadge((parseInt(document.getElementById('bellBadge').textContent) || 0) + 1);
  notifLoaded = false;
}

function removeToast(id) {
  const el = document.getElementById(id);
  if (el) { el.style.animation = 'none'; el.style.opacity = '0'; el.style.transform = 'translateY(8px)'; setTimeout(() => el.remove(), 200); }
}

// ── Reverb Echo ────────────────────────────────────────────────────────
@php
  $reverbKey    = config('broadcasting.connections.reverb.key');
  $reverbHost   = config('broadcasting.connections.reverb.options.host', '127.0.0.1');
  $reverbPort   = config('broadcasting.connections.reverb.options.port', 8080);
  $reverbScheme = config('broadcasting.connections.reverb.options.scheme', 'http');
  $empId        = auth('employee')->id();
@endphp
if (typeof Echo !== 'undefined' && {{ $empId ?? 0 }} > 0) {
  try {
    const echo = new Echo({
      broadcaster: 'reverb',
      key: '{{ $reverbKey }}',
      wsHost: '{{ $reverbHost }}',
      wsPort: {{ $reverbPort }},
      wssPort: {{ $reverbPort }},
      forceTLS: {{ $reverbScheme === 'https' ? 'true' : 'false' }},
      enabledTransports: ['ws', 'wss'],
      authEndpoint: '/employee/broadcasting/auth',
      auth: { headers: { 'X-CSRF-TOKEN': CSRF } },
    });
    echo.private('employee.{{ $empId }}').listen('.employee.notification', showToast);
  } catch(e) {
    console.warn('[eSahlan] Realtime:', e.message);
  }
}

// ── Utils ──────────────────────────────────────────────────────────────
function escapeHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escapeJs(s) { return String(s).replace(/'/g,"\\'").replace(/\n/g,''); }
function lighten(hex) {
  const r=parseInt(hex.slice(1,3),16), g=parseInt(hex.slice(3,5),16), b=parseInt(hex.slice(5,7),16);
  return `rgba(${r},${g},${b},.15)`;
}
</script>
</body>
</html>
