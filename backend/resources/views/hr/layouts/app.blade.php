<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'HR Panel') — eSahlan HR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy:  { DEFAULT: '#1B1444', 50: '#EEF0FF', 100: '#D5D9FF', 500: '#2D2467', 700: '#150F32', 900: '#0A0720' },
                        brand: { DEFAULT: '#F7941D', 50: '#FFF4E5', 100: '#FFE5B8', 500: '#F7941D', 600: '#E07800', 700: '#C06600' },
                    }
                }
            }
        }
    </script>
    <style>
        /* ── Design tokens ───────────────────────────────────────────────── */
        :root {
            --sb-bg:           #1B1444;
            --sb-deeper:       #130E35;
            --sb-border:       rgba(255,255,255,0.07);
            --sb-text:         rgba(255,255,255,0.78);
            --sb-text-dim:     rgba(255,255,255,0.30);
            --sb-hover:        rgba(255,255,255,0.06);
            --sb-active-bg:    rgba(247,148,29,0.13);
            --sb-active-rail:  #F7941D;
            --brand:           #F7941D;
            --brand-glow:      rgba(247,148,29,0.18);
            --topbar-h:        64px;
            --sb-w:            256px;
            --surface:         #F2F5F9;
            --card:            #FFFFFF;
            --border:          #E3E8F0;
            --text:            #111827;
            --text-2:          #374151;
            --text-muted:      #6B7280;
            --text-faint:      #9CA3AF;
            --radius:          10px;
            --shadow-sm:       0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md:       0 4px 16px rgba(0,0,0,0.10), 0 2px 4px rgba(0,0,0,0.05);
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            margin: 0; padding: 0;
            height: 100%;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--surface);
            color: var(--text);
            display: flex;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ SIDEBAR ━━━━━━━━━━━━━━━ */
        .sidebar {
            width: var(--sb-w);
            min-height: 100vh;
            height: 100%;
            background: var(--sb-bg);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: fixed;
            top: 0; left: 0;
            z-index: 50;
            /* Subtle inner glow at the bottom for depth */
            box-shadow: inset 0 -120px 80px -60px rgba(0,0,0,0.25);
        }

        /* Logo row */
        .sb-logo {
            height: var(--topbar-h);
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 0 18px;
            border-bottom: 1px solid var(--sb-border);
            flex-shrink: 0;
        }

        .sb-logo-mark {
            width: 34px; height: 34px;
            background: var(--brand);
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; font-weight: 800; color: #fff;
            letter-spacing: -0.5px;
            flex-shrink: 0;
            box-shadow: 0 2px 10px rgba(247,148,29,0.40), 0 0 0 1px rgba(247,148,29,0.20);
        }

        .sb-wordmark { display: flex; flex-direction: column; gap: 1px; }
        .sb-wordmark-name  { font-size: 14px; font-weight: 700; color: #fff; letter-spacing: -0.3px; line-height: 1; }
        .sb-wordmark-badge {
            display: inline-flex; align-items: center;
            font-size: 9.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
            color: rgba(247,148,29,0.90);
            background: rgba(247,148,29,0.12);
            border: 1px solid rgba(247,148,29,0.22);
            border-radius: 4px;
            padding: 1px 5px;
            line-height: 1.5;
            width: fit-content;
            margin-top: 2px;
        }

        /* Scrollable nav body */
        .sb-nav {
            flex: 1;
            overflow-y: auto;
            padding: 14px 10px 10px;
            scrollbar-width: none;
        }
        .sb-nav::-webkit-scrollbar { display: none; }

        /* Section header */
        .sb-group {
            margin-top: 18px;
            margin-bottom: 2px;
        }
        .sb-group:first-child { margin-top: 0; }
        .sb-group-head {
            display: flex; align-items: center; gap: 8px;
            padding: 0 8px 6px;
        }
        .sb-group-label {
            font-size: 9.5px; font-weight: 700;
            letter-spacing: 0.10em; text-transform: uppercase;
            color: var(--sb-text-dim);
            white-space: nowrap;
            flex-shrink: 0;
        }
        .sb-group-rule {
            flex: 1; height: 1px;
            background: var(--sb-border);
        }

        /* Nav link */
        .sb-link {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 10px 8px 11px;
            border-radius: 8px;
            font-size: 13px; font-weight: 500;
            color: var(--sb-text);
            text-decoration: none;
            border-left: 2.5px solid transparent;
            transition: background 0.14s ease, color 0.14s ease, border-color 0.14s ease;
            margin-bottom: 1px;
        }
        .sb-link:hover {
            background: var(--sb-hover);
            color: #fff;
        }
        .sb-link:hover .sb-ico { opacity: 0.9; }
        .sb-link.active {
            background: var(--sb-active-bg);
            border-left-color: var(--sb-active-rail);
            color: #fff;
            font-weight: 600;
        }
        .sb-link.active .sb-ico {
            opacity: 1;
            color: var(--brand);
        }

        .sb-ico {
            width: 16px; height: 16px;
            flex-shrink: 0; opacity: 0.55;
            transition: opacity 0.14s, color 0.14s;
        }

        .sb-lbl { flex: 1; }

        .sb-pill {
            font-size: 10px; font-weight: 700;
            background: rgba(247,148,29,0.16);
            color: var(--brand);
            padding: 1.5px 6px;
            border-radius: 20px;
        }
        .sb-link.active .sb-pill { background: rgba(247,148,29,0.22); }

        /* ── Footer / user area ─────────────────────────────────────────── */
        .sb-foot {
            padding: 10px;
            border-top: 1px solid var(--sb-border);
            background: var(--sb-deeper);
            flex-shrink: 0;
        }

        .sb-user-chip {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 10px;
            border-radius: 8px;
            background: rgba(255,255,255,0.05);
            margin-bottom: 6px;
        }

        .sb-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, #F7941D 0%, #B85F00 100%);
            display: flex; align-items: center; justify-content: center;
            font-size: 11.5px; font-weight: 800; color: #fff;
            flex-shrink: 0;
            letter-spacing: 0.02em;
        }

        .sb-user-name { font-size: 12.5px; font-weight: 600; color: #fff; line-height: 1.2; }
        .sb-user-role { font-size: 10px; color: var(--sb-text-dim); line-height: 1.3; margin-top: 1px; }

        .sb-signout {
            display: flex; align-items: center; gap: 8px;
            padding: 7px 10px; border-radius: 7px;
            font-size: 12.5px; font-weight: 500;
            color: rgba(255,100,100,0.65);
            background: none; border: none; width: 100%;
            text-align: left; cursor: pointer; font-family: inherit;
            transition: background 0.13s, color 0.13s;
        }
        .sb-signout:hover { background: rgba(255,70,70,0.10); color: rgba(255,130,130,0.90); }
        .sb-signout svg { width: 14px; height: 14px; flex-shrink: 0; }

        /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ MAIN WRAP ━━━━━━━━━━━━━ */
        .main-wrap {
            flex: 1;
            margin-left: var(--sb-w);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            min-width: 0;
        }

        /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ TOPBAR ━━━━━━━━━━━━━━━ */
        .topbar {
            height: var(--topbar-h);
            background: var(--card);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 0 28px;
            position: sticky;
            top: 0; z-index: 40;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            flex-shrink: 0;
        }

        .topbar-page {
            flex: 1;
            min-width: 0;
        }
        .topbar-title {
            font-size: 15px; font-weight: 700;
            color: var(--text);
            line-height: 1;
            letter-spacing: -0.25px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .topbar-crumb {
            font-size: 11px; color: var(--text-faint);
            margin-top: 3px; line-height: 1;
        }
        .topbar-crumb em { font-style: normal; color: var(--brand); font-weight: 500; }

        /* Search */
        .topbar-search { position: relative; flex-shrink: 0; }
        .topbar-search input {
            width: 250px; height: 36px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0 68px 0 36px;
            font-size: 13px;
            color: var(--text);
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
            font-family: inherit;
        }
        .topbar-search input::placeholder { color: var(--text-faint); }
        .topbar-search input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px var(--brand-glow);
            background: #fff;
        }
        .search-ico {
            position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
            color: var(--text-faint); width: 15px; height: 15px; pointer-events: none;
        }
        .search-kbd {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            font-size: 10px; color: var(--text-faint);
            background: var(--border); border: 1px solid #D1D9E6;
            padding: 1.5px 5px; border-radius: 4px;
            pointer-events: none; font-family: inherit; letter-spacing: 0.01em;
        }

        /* Icon button */
        .tb-btn {
            width: 36px; height: 36px; border-radius: 8px;
            border: none; background: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted); position: relative;
            transition: background 0.13s, color 0.13s;
        }
        .tb-btn:hover { background: var(--surface); color: var(--text); }
        .tb-btn svg { width: 18px; height: 18px; }

        .notif-dot {
            position: absolute; top: 8px; right: 8px;
            width: 7px; height: 7px;
            background: var(--brand); border-radius: 50%;
            border: 2px solid var(--card);
        }

        .tb-divider { width: 1px; height: 22px; background: var(--border); margin: 0 4px; }

        /* User dropdown trigger */
        .tb-user-btn {
            display: flex; align-items: center; gap: 8px;
            padding: 4px 8px 4px 4px; border-radius: 8px;
            cursor: pointer; border: none; background: none;
            font-family: inherit; position: relative;
            transition: background 0.13s;
        }
        .tb-user-btn:hover { background: var(--surface); }
        .tb-user-av {
            width: 30px; height: 30px; border-radius: 50%;
            background: linear-gradient(135deg, #F7941D 0%, #B85F00 100%);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 800; color: #fff;
            flex-shrink: 0;
        }
        .tb-user-name { font-size: 13px; font-weight: 600; color: var(--text); line-height: 1.1; }
        .tb-user-role { font-size: 10.5px; color: var(--text-muted); line-height: 1.1; }
        .tb-chevron { width: 14px; height: 14px; color: var(--text-faint); flex-shrink: 0; }

        /* Dropdown */
        .tb-dropdown {
            position: absolute; top: calc(100% + 8px); right: 0;
            width: 210px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            z-index: 100;
            display: none;
        }
        .tb-dropdown.open { display: block; animation: ddIn 0.15s ease; }
        @keyframes ddIn {
            from { opacity:0; transform: translateY(-6px); }
            to   { opacity:1; transform: translateY(0); }
        }
        .tb-dd-head {
            padding: 12px 14px 10px;
            border-bottom: 1px solid var(--border);
        }
        .tb-dd-head-name { font-size: 13px; font-weight: 600; color: var(--text); }
        .tb-dd-head-role { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
        .tb-dd-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 14px; font-size: 13px; color: var(--text-2);
            text-decoration: none; cursor: pointer;
            background: none; border: none; width: 100%; text-align: left;
            font-family: inherit;
            transition: background 0.12s;
        }
        .tb-dd-item:hover { background: var(--surface); }
        .tb-dd-item svg { width: 14px; height: 14px; color: var(--text-muted); flex-shrink: 0; }
        .tb-dd-item.danger { color: #DC2626; }
        .tb-dd-item.danger svg { color: #DC2626; }
        .tb-dd-sep { height: 1px; background: var(--border); }

        /* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ CONTENT ━━━━━━━━━━━━━━━ */
        .content-body {
            flex: 1;
            padding: 28px 32px;
            overflow-x: hidden;
            animation: pageIn 0.18s ease;
        }
        @keyframes pageIn {
            from { opacity:0; transform: translateY(5px); }
            to   { opacity:1; transform: translateY(0); }
        }

        /* Flash banners */
        .flash {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 13px 16px; border-radius: var(--radius);
            margin-bottom: 22px; border: 1px solid transparent;
            font-size: 13.5px; line-height: 1.5;
            animation: pageIn 0.2s ease;
        }
        .flash.success { background:#F0FDF4; border-color:#BBF7D0; color:#15803D; }
        .flash.error   { background:#FFF1F2; border-color:#FECDD3; color:#B91C1C; }
        .flash svg { width:18px; height:18px; flex-shrink:0; margin-top:1px; }
        .flash-close {
            margin-left:auto; background:none; border:none;
            cursor:pointer; opacity:0.5; padding:0; color:inherit; line-height:1;
        }
        .flash-close:hover { opacity:1; }

        /* Scrollbar */
        ::-webkit-scrollbar { width:5px; height:5px; }
        ::-webkit-scrollbar-track { background:transparent; }
        ::-webkit-scrollbar-thumb { background:#CBD5E1; border-radius:10px; }
        ::-webkit-scrollbar-thumb:hover { background:#94A3B8; }

        [x-cloak] { display:none !important; }
    </style>
    @stack('styles')
</head>
<body>

{{-- ═══════════════════════════════════════════════ SIDEBAR ═══════════════ --}}
<aside class="sidebar">

    {{-- Brand --}}
    <div class="sb-logo">
        <div class="sb-logo-mark">e</div>
        <div class="sb-wordmark">
            <div class="sb-wordmark-name">eSahlan</div>
            <div class="sb-wordmark-badge">HR Panel</div>
        </div>
    </div>

    {{-- Nav --}}
    <nav class="sb-nav">
        @php $route = request()->route()->getName(); @endphp

        {{-- ── CORE ─────────────────────────────── --}}
        <div class="sb-group">
            <div class="sb-group-head">
                <span class="sb-group-label">Core</span>
                <span class="sb-group-rule"></span>
            </div>

            <a href="{{ route('hr.dashboard') }}"
               class="sb-link {{ str_starts_with($route, 'hr.dashboard') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor"/>
                    <rect x="14" y="3" width="7" height="4" rx="1.5" stroke="currentColor"/>
                    <rect x="14" y="11" width="7" height="10" rx="1.5" stroke="currentColor"/>
                    <rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor"/>
                </svg>
                <span class="sb-lbl">Dashboard</span>
            </a>

            <a href="{{ route('hr.employees.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.employees') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="sb-lbl">Employees</span>
            </a>

            <a href="{{ route('hr.attendance.daily') }}"
               class="sb-link {{ str_starts_with($route, 'hr.attendance') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="sb-lbl">Attendance</span>
            </a>

            <a href="{{ route('hr.leaves.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.leaves') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                <span class="sb-lbl">Leaves</span>
            </a>
        </div>

        {{-- ── TALENT ────────────────────────────── --}}
        <div class="sb-group">
            <div class="sb-group-head">
                <span class="sb-group-label">Talent</span>
                <span class="sb-group-rule"></span>
            </div>

            @if(Auth::guard('hr')->user()->isRecruiter())
            <a href="{{ route('hr.recruitment.postings.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.recruitment') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
                <span class="sb-lbl">Recruitment</span>
            </a>
            @endif

            <a href="{{ route('hr.performance.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.performance') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <span class="sb-lbl">Performance</span>
            </a>

            <a href="{{ route('hr.discipline.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.discipline') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span class="sb-lbl">Discipline</span>
            </a>
        </div>

        {{-- ── COMMUNICATIONS ────────────────────── --}}
        <div class="sb-group">
            <div class="sb-group-head">
                <span class="sb-group-label">Communications</span>
                <span class="sb-group-rule"></span>
            </div>

            <a href="{{ route('hr.announcements.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.announcements') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                </svg>
                <span class="sb-lbl">Announcements</span>
            </a>

            <a href="{{ route('hr.reports.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.reports') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="sb-lbl">Reports</span>
            </a>
        </div>

        {{-- ── WORKFORCE (manager / officer) ───────── --}}
        @if(Auth::guard('hr')->user()->isManager() || Auth::guard('hr')->user()->isOfficer())
        <div class="sb-group">
            <div class="sb-group-head">
                <span class="sb-group-label">Workforce</span>
                <span class="sb-group-rule"></span>
            </div>

            <a href="{{ route('hr.workforce.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.workforce') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
                <span class="sb-lbl">Module Assignments</span>
            </a>

            <a href="{{ route('hr.module-departments.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.module-departments') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <span class="sb-lbl">Module Departments</span>
            </a>

            <a href="{{ route('hr.module-positions.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.module-positions') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2"/>
                </svg>
                <span class="sb-lbl">Module Positions</span>
            </a>

            <a href="{{ route('hr.module-roles.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.module-roles') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span class="sb-lbl">Module Roles</span>
            </a>
        </div>
        @endif

        {{-- ── ADMINISTRATION (role-gated) ──────── --}}
        @if(Auth::guard('hr')->user()->isManager() || Auth::guard('hr')->user()->isPayroll())
        <div class="sb-group">
            <div class="sb-group-head">
                <span class="sb-group-label">Administration</span>
                <span class="sb-group-rule"></span>
            </div>

            @if(Auth::guard('hr')->user()->isPayroll())
            <a href="{{ route('hr.payroll.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.payroll') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="sb-lbl">Payroll</span>
            </a>
            @endif

            @if(Auth::guard('hr')->user()->isManager())
            <a href="{{ route('hr.commissions.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.commissions') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                <span class="sb-lbl">Commissions</span>
            </a>

            <a href="{{ route('hr.components.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.components') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="sb-lbl">Salary Components</span>
            </a>

            <a href="{{ route('hr.departments.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.departments') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <span class="sb-lbl">Departments</span>
            </a>

            <a href="{{ route('hr.positions.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.positions') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span class="sb-lbl">Positions</span>
            </a>

            <a href="{{ route('hr.audit.index') }}"
               class="sb-link {{ str_starts_with($route, 'hr.audit') ? 'active' : '' }}">
                <svg class="sb-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                <span class="sb-lbl">Audit Log</span>
            </a>
            @endif
        </div>
        @endif

    </nav>

    {{-- User footer --}}
    <div class="sb-foot">
        <div class="sb-user-chip">
            <div class="sb-avatar">{{ strtoupper(substr(Auth::guard('hr')->user()->name, 0, 2)) }}</div>
            <div>
                <div class="sb-user-name">{{ Auth::guard('hr')->user()->name }}</div>
                <div class="sb-user-role">{{ Auth::guard('hr')->user()->role_label }}</div>
            </div>
        </div>
        <form method="POST" action="{{ route('hr.logout') }}">
            @csrf
            <button type="submit" class="sb-signout">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Sign out
            </button>
        </form>
    </div>

</aside>

{{-- ═══════════════════════════════════════════════ MAIN ═══════════════════ --}}
<div class="main-wrap">

    {{-- Topbar --}}
    <header class="topbar">

        <div class="topbar-page">
            <div class="topbar-title">@yield('heading', 'Dashboard')</div>
            <div class="topbar-crumb">eSahlan HR &rsaquo; <em>@yield('heading', 'Dashboard')</em></div>
        </div>

        <div class="topbar-search">
            <svg class="search-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" id="qs-input" placeholder="Quick search…" autocomplete="off">
            <span class="search-kbd">⌘K</span>
        </div>

        <div style="display:flex;align-items:center;gap:4px;">

            {{-- Notification bell --}}
            <button class="tb-btn" title="Notifications">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span class="notif-dot"></span>
            </button>

            <div class="tb-divider"></div>

            {{-- User menu --}}
            <div style="position:relative;">
                <button class="tb-user-btn" id="ub" onclick="toggleDd()">
                    <div class="tb-user-av">{{ strtoupper(substr(Auth::guard('hr')->user()->name, 0, 2)) }}</div>
                    <div>
                        <div class="tb-user-name">{{ Auth::guard('hr')->user()->name }}</div>
                        <div class="tb-user-role">{{ Auth::guard('hr')->user()->role_label }}</div>
                    </div>
                    <svg class="tb-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div class="tb-dropdown" id="ud">
                    <div class="tb-dd-head">
                        <div class="tb-dd-head-name">{{ Auth::guard('hr')->user()->name }}</div>
                        <div class="tb-dd-head-role">{{ Auth::guard('hr')->user()->role_label }}</div>
                    </div>
                    <div class="tb-dd-sep"></div>
                    <button class="tb-dd-item danger" onclick="document.getElementById('sf').submit()">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Sign out
                    </button>
                </div>
            </div>
        </div>

    </header>

    {{-- Hidden sign-out form for dropdown --}}
    <form id="sf" method="POST" action="{{ route('hr.logout') }}" style="display:none;">@csrf</form>

    {{-- Content --}}
    <main class="content-body">

        @if(session('success'))
        <div class="flash success" id="fl-ok">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ session('success') }}</span>
            <button class="flash-close" onclick="this.parentElement.remove()">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        @endif

        @if(session('error'))
        <div class="flash error" id="fl-err">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ session('error') }}</span>
            <button class="flash-close" onclick="this.parentElement.remove()">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        @endif

        @yield('content')

    </main>

</div>

<script>
    // User dropdown
    function toggleDd() {
        document.getElementById('ud').classList.toggle('open');
    }
    document.addEventListener('click', function(e) {
        const btn = document.getElementById('ub');
        const dd  = document.getElementById('ud');
        if (dd && !dd.contains(e.target) && btn && !btn.contains(e.target)) {
            dd.classList.remove('open');
        }
    });

    // ⌘K / Ctrl+K → focus search
    document.addEventListener('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            document.getElementById('qs-input')?.focus();
        }
        if (e.key === 'Escape') {
            document.getElementById('ud')?.classList.remove('open');
            document.getElementById('qs-input')?.blur();
        }
    });

    // Auto-dismiss flash after 5 s
    ['fl-ok', 'fl-err'].forEach(function(id) {
        const el = document.getElementById(id);
        if (!el) return;
        setTimeout(function() {
            el.style.transition = 'opacity 0.4s';
            el.style.opacity = '0';
            setTimeout(function() { el.remove(); }, 420);
        }, 5000);
    });
</script>
@stack('scripts')
</body>
</html>
