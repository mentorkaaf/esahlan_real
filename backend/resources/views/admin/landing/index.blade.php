@extends('admin.layouts.app')
@section('title', 'Landing Page Management')
@section('content')
<style>
*{box-sizing:border-box}

/* ════════════════════════════════
   PAGE SHELL
═══════════════════════════════════ */
.lpm-root{display:flex;gap:0;min-height:calc(100vh - 120px);background:var(--bg)}

/* ════════════════════════════════
   SIDEBAR
═══════════════════════════════════ */
.lpm-nav{width:240px;flex-shrink:0;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.lpm-nav-brand{padding:20px 18px 14px;border-bottom:1px solid var(--border)}
.lpm-nav-brand-title{font-size:.72rem;font-weight:900;letter-spacing:2.5px;text-transform:uppercase;color:var(--text-muted)}
.lpm-nav-brand-sub{font-size:.75rem;color:var(--text-muted);margin-top:3px;opacity:.6}
.lpm-nav-list{padding:10px 10px;flex:1}
.lpm-nav-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;cursor:pointer;transition:all .15s;text-decoration:none;margin-bottom:2px;position:relative;overflow:hidden}
.lpm-nav-item::before{content:'';position:absolute;inset:0;background:linear-gradient(90deg,var(--item-color,var(--brand)),transparent);opacity:0;transition:opacity .2s;border-radius:10px}
.lpm-nav-item:hover::before{opacity:.08}
.lpm-nav-item.active::before{opacity:.12}
.lpm-nav-item.active{background:color-mix(in srgb,var(--item-color,var(--brand)) 10%,transparent)}
.lpm-nav-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.72rem;color:#fff;flex-shrink:0;position:relative;z-index:1;transition:transform .15s}
.lpm-nav-item:hover .lpm-nav-icon{transform:scale(1.1)}
.lpm-nav-text{position:relative;z-index:1}
.lpm-nav-name{font-size:.84rem;font-weight:600;color:var(--text);line-height:1.2;display:block}
.lpm-nav-desc{font-size:.68rem;color:var(--text-muted);display:block;line-height:1.3}
.lpm-nav-item.active .lpm-nav-name{color:var(--item-color,var(--brand))}
.lpm-nav-sep{height:1px;background:var(--border);margin:10px 4px}
.lpm-nav-foot{padding:14px 16px;border-top:1px solid var(--border)}
.btn-preview-sm{display:flex;align-items:center;justify-content:center;gap:8px;background:linear-gradient(135deg,#0e0560,#5b21b6);color:#fff;padding:10px 14px;border-radius:10px;font-size:.82rem;font-weight:700;text-decoration:none;transition:all .2s}
.btn-preview-sm:hover{opacity:.88;transform:translateY(-1px)}

/* ════════════════════════════════
   MAIN CONTENT
═══════════════════════════════════ */
.lpm-content{flex:1;min-width:0;padding:28px 32px}
.lpm-topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:28px}
.lpm-topbar-left h1{font-size:1.5rem;font-weight:900;color:var(--text);letter-spacing:-.03em;margin-bottom:3px}
.lpm-topbar-left p{font-size:.83rem;color:var(--text-muted)}
.lpm-breadcrumb{display:flex;align-items:center;gap:6px;font-size:.76rem;color:var(--text-muted);margin-bottom:6px}
.lpm-breadcrumb i{font-size:.55rem}

/* ════════════════════════════════
   SECTION PANEL
═══════════════════════════════════ */
.lpm-panel{display:none;animation:panelIn .2s ease}
.lpm-panel.active{display:block}
@keyframes panelIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

/* Panel header strip */
.lpm-panel-hero{border-radius:18px;padding:28px 32px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;gap:20px;position:relative;overflow:hidden;color:#fff}
.lpm-panel-hero::after{content:'';position:absolute;right:-40px;top:-60px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.06);pointer-events:none}
.lpm-panel-hero-left{display:flex;align-items:center;gap:18px;position:relative}
.lpm-panel-hero-icon{width:56px;height:56px;background:rgba(255,255,255,.18);border:1.5px solid rgba(255,255,255,.25);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
.lpm-panel-hero h2{font-size:1.3rem;font-weight:900;margin-bottom:4px;letter-spacing:-.025em}
.lpm-panel-hero p{opacity:.65;font-size:.84rem;line-height:1.4}
.lpm-panel-hero-badge{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);padding:6px 14px;border-radius:50px;font-size:.74rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;white-space:nowrap;display:flex;align-items:center;gap:6px}
.lpm-panel-hero-badge i{font-size:.5rem}

/* ════════════════════════════════
   FIELD ZONES
═══════════════════════════════════ */
.lpm-zone{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-bottom:16px}
.lpm-zone-head{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:var(--bg);border-bottom:1px solid var(--border)}
.lpm-zone-head-left{display:flex;align-items:center;gap:10px}
.lpm-zone-pip{width:6px;height:6px;border-radius:50%;flex-shrink:0}
.lpm-zone-title{font-size:.78rem;font-weight:800;letter-spacing:.8px;text-transform:uppercase;color:var(--text)}
.lpm-zone-chip{font-size:.63rem;font-weight:800;padding:3px 9px;border-radius:50px;letter-spacing:.6px;text-transform:uppercase;display:flex;align-items:center;gap:5px}
.chip-live{background:#dcfce7;color:#166534}
.chip-img{background:#eff6ff;color:#1e40af}
.chip-url{background:#fef3c7;color:#92400e}
.lpm-zone-body{padding:24px 20px;display:grid;gap:20px;background:var(--surface)}

/* ════════════════════════════════
   FIELD
═══════════════════════════════════ */
.lf{display:flex;flex-direction:column}
.lf-label{font-size:.68rem;font-weight:900;letter-spacing:1.4px;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px;display:flex;align-items:center;gap:6px}
.lf-label-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.lf-hint{font-size:.73rem;color:var(--text-muted);margin-top:7px;line-height:1.45;padding-left:2px}
/* Input wrapper */
.lf-input-wrap{position:relative;display:flex;align-items:stretch}
.lf-prefix{position:absolute;left:0;top:0;bottom:0;width:44px;display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:2}
.lf-prefix i{font-size:.8rem;color:#94a3b8;transition:color .18s}
.lf-input-wrap:focus-within .lf-prefix i{color:var(--accent,#6366f1)}
/* THE INPUT — hardcoded so admin panel vars can't override */
.lfi{
    width:100%;
    border:2px solid #e2e8f0;
    border-radius:12px;
    padding:12px 16px 12px 44px;
    font-size:.92rem;
    color:#1e293b;
    background:#f8fafc;
    font-family:inherit;
    transition:border-color .2s,box-shadow .2s,background .2s;
    line-height:1.5;
    -webkit-appearance:none;
    appearance:none;
}
.lfi::placeholder{color:#94a3b8}
.lfi:hover{border-color:#cbd5e1;background:#fff}
.lfi:focus{
    outline:none;
    border-color:var(--accent,#6366f1);
    background:#fff;
    box-shadow:0 0 0 4px color-mix(in srgb,var(--accent,#6366f1) 12%,transparent);
    color:#0f172a;
}
textarea.lfi{padding-top:12px;resize:vertical;min-height:90px;line-height:1.65}
/* Dark mode override */
@media (prefers-color-scheme:dark){
    .lfi{background:#1e293b;border-color:#334155;color:#f1f5f9}
    .lfi:hover{background:#243044;border-color:#475569}
    .lfi:focus{background:#1e293b;color:#f8fafc}
    .lfi::placeholder{color:#64748b}
}

/* Grid helpers */
.g2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
.g1{display:grid;grid-template-columns:1fr;gap:14px}
.gfull{grid-column:1/-1}

/* ════════════════════════════════
   STAT CARDS
═══════════════════════════════════ */
.stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.stat-card{background:var(--bg);border:2px solid var(--border);border-radius:14px;padding:18px 20px;transition:border-color .2s;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--sc-color,var(--brand))}
.stat-card:focus-within{border-color:var(--sc-color,var(--brand))}
.stat-card-header{display:flex;align-items:center;gap:8px;margin-bottom:16px}
.stat-card-num{font-size:.65rem;font-weight:900;letter-spacing:2px;text-transform:uppercase;color:var(--sc-color,var(--brand))}
.stat-card-body{display:grid;gap:12px}

/* ════════════════════════════════
   TOGGLE
═══════════════════════════════════ */
.lf-toggle-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 16px;background:var(--bg);border-radius:10px;border:1.5px solid var(--border)}
.lf-toggle-info{display:flex;flex-direction:column;gap:2px}
.lf-toggle-name{font-size:.85rem;font-weight:700;color:var(--text)}
.lf-toggle-sub{font-size:.74rem;color:var(--text-muted)}
.ts{position:relative;width:46px;height:25px;flex-shrink:0}
.ts input{opacity:0;width:0;height:0;position:absolute}
.ts-sl{position:absolute;inset:0;background:var(--border);border-radius:25px;cursor:pointer;transition:.25s}
.ts-sl::before{content:'';position:absolute;width:19px;height:19px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.25s;box-shadow:0 2px 4px rgba(0,0,0,.18)}
.ts input:checked+.ts-sl{background:var(--brand)}
.ts input:checked+.ts-sl::before{transform:translateX(21px)}

/* ════════════════════════════════
   IMAGE UPLOAD BLOCK
═══════════════════════════════════ */
.img-upload-block{border-radius:14px;overflow:hidden;margin-top:10px;background:#f8fafc;border:2px dashed #e2e8f0;transition:border-color .2s}
.img-upload-block:hover{border-color:#94a3b8}
/* Drop zone */
.img-drop-zone{min-height:160px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:20px;cursor:pointer;transition:background .2s;position:relative}
.img-drop-zone:hover{background:#f1f5f9}
.img-drop-zone.has-img{padding:0;min-height:auto}
.img-drop-zone img{max-height:180px;max-width:100%;object-fit:contain;padding:16px;display:block}
.img-drop-icon{width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#14b8a6,#0f766e);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem;flex-shrink:0}
.img-drop-text{text-align:center}
.img-drop-text strong{display:block;font-size:.88rem;font-weight:700;color:#334155;margin-bottom:3px}
.img-drop-text span{font-size:.75rem;color:#64748b}
.img-drop-input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
/* Toolbar below preview */
.img-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 14px;border-top:1.5px dashed #e2e8f0;background:#fff}
.img-toolbar-name{font-size:.76rem;color:#64748b;font-weight:500;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.img-toolbar-actions{display:flex;gap:8px;flex-shrink:0}
.img-btn{display:inline-flex;align-items:center;gap:6px;font-size:.76rem;font-weight:700;padding:6px 12px;border-radius:8px;cursor:pointer;border:none;font-family:inherit;transition:all .15s}
.img-btn-change{background:#f1f5f9;color:#475569}
.img-btn-change:hover{background:#e2e8f0}
.img-btn-remove{background:#fee2e2;color:#dc2626}
.img-btn-remove:hover{background:#fecaca}
/* URL tab */
.img-url-tab{padding:12px 14px;border-top:1px solid #e2e8f0;background:#fff}
.img-url-toggle{display:flex;align-items:center;gap:7px;font-size:.74rem;font-weight:700;color:#64748b;cursor:pointer;user-select:none;margin-bottom:0}
.img-url-toggle i{font-size:.7rem;transition:transform .2s}
.img-url-body{display:none;margin-top:10px}
.img-url-body.open{display:block}
@media (prefers-color-scheme:dark){
    .img-upload-block,.img-drop-zone{background:#1e293b;border-color:#334155}
    .img-drop-zone:hover{background:#243044}
    .img-drop-text strong{color:#f1f5f9}
    .img-toolbar,.img-url-tab{background:#1e293b;border-color:#334155}
}

/* ════════════════════════════════
   INFO NOTE
═══════════════════════════════════ */
.lpm-note{display:flex;align-items:flex-start;gap:10px;padding:13px 16px;background:color-mix(in srgb,var(--brand) 5%,transparent);border:1px solid color-mix(in srgb,var(--brand) 18%,transparent);border-radius:11px;font-size:.8rem;color:var(--text-muted);line-height:1.5}
.lpm-note i{color:var(--brand);margin-top:1px;flex-shrink:0}

/* ════════════════════════════════
   SECTION DIVIDER
═══════════════════════════════════ */
.lpm-div{display:flex;align-items:center;gap:14px;margin:4px 0}
.lpm-div span{font-size:.64rem;font-weight:900;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);white-space:nowrap}
.lpm-div::before,.lpm-div::after{content:'';flex:1;height:1px;background:var(--border)}

/* ════════════════════════════════
   SAVE BAR
═══════════════════════════════════ */
.lpm-savebar{display:flex;align-items:center;justify-content:space-between;gap:16px;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 20px;margin-top:20px;position:sticky;bottom:24px;z-index:20;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.lpm-savebar-left{display:flex;align-items:center;gap:10px}
.lpm-savebar-dot{width:8px;height:8px;border-radius:50%;background:#22c55e;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.85)}}
.lpm-savebar-text{font-size:.81rem;color:var(--text-muted)}
.lpm-savebar-text strong{color:var(--text);font-weight:700}
.btn-save{display:inline-flex;align-items:center;gap:9px;background:linear-gradient(135deg,#FF8A00,#e67300);color:#fff;border:none;padding:12px 30px;border-radius:11px;font-size:.91rem;font-weight:800;cursor:pointer;transition:all .2s;font-family:inherit;letter-spacing:-.01em;box-shadow:0 3px 12px rgba(255,138,0,.4)}
.btn-save:hover{background:linear-gradient(135deg,#ff9a1a,#FF8A00);transform:translateY(-2px);box-shadow:0 6px 20px rgba(255,138,0,.5)}
.btn-save:active{transform:translateY(0);box-shadow:0 2px 8px rgba(255,138,0,.3)}

/* ════════════════════════════════
   ALERT
═══════════════════════════════════ */
.lpm-alert{display:flex;align-items:center;gap:10px;background:#d1fae5;border:1px solid #86efac;color:#14532d;border-radius:12px;padding:13px 18px;margin-bottom:22px;font-weight:700;font-size:.87rem}
</style>

@if(session('success'))
<div class="lpm-alert"><i class="fas fa-check-circle" style="font-size:1rem"></i> {{ session('success') }}</div>
@endif

<form id="lpm-form" method="POST" action="{{ route('admin.landing.update') }}" enctype="multipart/form-data">
@csrf
@method('PUT')

<div class="lpm-root">

{{-- ═══════════════ SIDEBAR ═══════════════ --}}
<nav class="lpm-nav">
    <div class="lpm-nav-brand">
        <div class="lpm-nav-brand-title">Landing Page</div>
        <div class="lpm-nav-brand-sub">esahlan.com · 8 sections</div>
    </div>

    <div class="lpm-nav-list">
        <a class="lpm-nav-item active" style="--item-color:#f97316" onclick="nav('hero',this)">
            <div class="lpm-nav-icon" style="background:linear-gradient(135deg,#f97316,#ea580c)"><i class="fas fa-star"></i></div>
            <div class="lpm-nav-text"><span class="lpm-nav-name">Hero</span><span class="lpm-nav-desc">Title, subtitle, buttons</span></div>
        </a>
        <a class="lpm-nav-item" style="--item-color:#8b5cf6" onclick="nav('stats',this)">
            <div class="lpm-nav-icon" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9)"><i class="fas fa-chart-bar"></i></div>
            <div class="lpm-nav-text"><span class="lpm-nav-name">Stats Bar</span><span class="lpm-nav-desc">4 trust numbers</span></div>
        </a>
        <a class="lpm-nav-item" style="--item-color:#0ea5e9" onclick="nav('how',this)">
            <div class="lpm-nav-icon" style="background:linear-gradient(135deg,#06b6d4,#0284c7)"><i class="fas fa-list-ol"></i></div>
            <div class="lpm-nav-text"><span class="lpm-nav-name">How It Works</span><span class="lpm-nav-desc">3-step process</span></div>
        </a>
        <a class="lpm-nav-item" style="--item-color:#10b981" onclick="nav('why',this)">
            <div class="lpm-nav-icon" style="background:linear-gradient(135deg,#10b981,#059669)"><i class="fas fa-check-circle"></i></div>
            <div class="lpm-nav-text"><span class="lpm-nav-name">Why eSahlan</span><span class="lpm-nav-desc">Key differentiators</span></div>
        </a>
        <a class="lpm-nav-item" style="--item-color:#6366f1" onclick="nav('logos',this)">
            <div class="lpm-nav-icon" style="background:linear-gradient(135deg,#6366f1,#4338ca)"><i class="fas fa-image"></i></div>
            <div class="lpm-nav-text"><span class="lpm-nav-name">Logos</span><span class="lpm-nav-desc">Nav, hero & footer</span></div>
        </a>
    <div class="lpm-nav-sep"></div>
        <a class="lpm-nav-item" style="--item-color:#ec4899" onclick="nav('espace',this)">
            <div class="lpm-nav-icon" style="background:linear-gradient(135deg,#ec4899,#be185d)"><i class="fas fa-users"></i></div>
            <div class="lpm-nav-text"><span class="lpm-nav-name">eSpace</span><span class="lpm-nav-desc">Community section</span></div>
        </a>
        <a class="lpm-nav-item" style="--item-color:#f59e0b" onclick="nav('join',this)">
            <div class="lpm-nav-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706)"><i class="fas fa-handshake"></i></div>
            <div class="lpm-nav-text"><span class="lpm-nav-name">Join Network</span><span class="lpm-nav-desc">Driver, vendor, agent</span></div>
        </a>
        <a class="lpm-nav-item" style="--item-color:#6366f1" onclick="nav('cta',this)">
            <div class="lpm-nav-icon" style="background:linear-gradient(135deg,#6366f1,#4f46e5)"><i class="fas fa-rocket"></i></div>
            <div class="lpm-nav-text"><span class="lpm-nav-name">Registration CTA</span><span class="lpm-nav-desc">Sign-up cards</span></div>
        </a>
        <a class="lpm-nav-item" style="--item-color:#14b8a6" onclick="nav('download',this)">
            <div class="lpm-nav-icon" style="background:linear-gradient(135deg,#14b8a6,#0f766e)"><i class="fas fa-mobile-alt"></i></div>
            <div class="lpm-nav-text"><span class="lpm-nav-name">Download App</span><span class="lpm-nav-desc">Store links + mockups</span></div>
        </a>
    </div>

    <div class="lpm-nav-foot">
        <a href="https://esahlan.com" target="_blank" class="btn-preview-sm"><i class="fas fa-external-link-alt"></i> View Live Page</a>
    </div>
</nav>

{{-- ═══════════════ MAIN ═══════════════ --}}
<div class="lpm-content">

    {{-- Top bar --}}
    <div class="lpm-topbar">
        <div class="lpm-topbar-left">
            <div class="lpm-breadcrumb"><span>Admin</span><i class="fas fa-chevron-right"></i><span>Landing Page</span><i class="fas fa-chevron-right"></i><span id="bc-active" style="color:var(--text);font-weight:700">Hero</span></div>
            <h1 id="panel-title">Hero Section</h1>
            <p id="panel-desc">Control the headline, subtitle, and action buttons at the top of the page.</p>
        </div>
    </div>

    {{-- ══════ LOGOS ══════ --}}
    <div class="lpm-panel" id="pan-logos">
        <div class="lpm-panel-hero" style="background:linear-gradient(135deg,#4f46e5 0%,#4338ca 60%,#3730a3 100%)">
            <div class="lpm-panel-hero-left">
                <div class="lpm-panel-hero-icon"><i class="fas fa-image"></i></div>
                <div><h2>Brand Logos</h2><p>Upload the eSahlan logo for the navbar, hero section, and footer — replaces the text mark instantly</p></div>
            </div>
            <div class="lpm-panel-hero-badge"><i class="fas fa-sync-alt"></i> 3 Slots</div>
        </div>

        <div class="lpm-zone">
            <div class="lpm-zone-head">
                <div class="lpm-zone-head-left"><div class="lpm-zone-pip" style="background:#6366f1"></div><span class="lpm-zone-title">Logo Upload Slots</span></div>
                <span class="lpm-zone-chip chip-img"><i class="fas fa-upload"></i> PNG / SVG / WebP</span>
            </div>
            <div class="lpm-zone-body g3">
                @php
                    $logoSlots = [
                        'logo_nav'    => ['Navbar Logo',  'Shown top-left in the navigation bar. Recommended: white/transparent PNG, height ~40px', '#4f46e5', 'fa-bars'],
                        'logo_hero'   => ['Hero Logo',    'Replaces the large eSahlan wordmark in the hero section. Recommended: transparent PNG',   '#6366f1', 'fa-star'],
                        'logo_footer' => ['Footer Logo',  'Shown in the footer brand area. Recommended: white/light PNG on dark background',          '#4338ca', 'fa-layer-group'],
                    ];
                @endphp
                @foreach($logoSlots as $lk => $lmeta)
                @php
                    $lCur   = $settings['landing_'.$lk] ?? '';
                    $lLbl   = $lmeta[0];
                    $lHint  = $lmeta[1];
                    $lColor = $lmeta[2];
                    $lIcon  = $lmeta[3];
                @endphp
                <div>
                    <div class="lf-label" style="margin-bottom:10px"><div class="lf-label-dot" style="background:{{ $lColor }}"></div>{{ $lLbl }}</div>
                    <div class="img-upload-block" id="block-{{ $lk }}">
                        <div class="img-drop-zone {{ $lCur ? 'has-img' : '' }}" id="zone-{{ $lk }}" onclick="document.getElementById('file-{{ $lk }}').click()">
                            @if($lCur)
                                <img src="{{ $lCur }}" alt="{{ $lLbl }}" id="preview-{{ $lk }}" style="background:#1e1b4b;padding:14px">
                            @else
                                <div id="placeholder-{{ $lk }}">
                                    <div class="img-drop-icon" style="background:linear-gradient(135deg,{{ $lColor }},#4338ca)"><i class="fas {{ $lIcon }}"></i></div>
                                    <div class="img-drop-text">
                                        <strong>Upload {{ $lLbl }}</strong>
                                        <span>PNG, SVG, WebP recommended</span>
                                    </div>
                                </div>
                            @endif
                            <input type="file" id="file-{{ $lk }}" name="landing_{{ $lk }}_file" accept="image/*" class="img-drop-input" onchange="handleUpload('{{ $lk }}', this)" onclick="event.stopPropagation()">
                        </div>
                        <div class="img-toolbar" id="toolbar-{{ $lk }}" style="{{ $lCur ? '' : 'display:none' }}">
                            <span class="img-toolbar-name" id="fname-{{ $lk }}">{{ $lCur ? basename($lCur) : '' }}</span>
                            <div class="img-toolbar-actions">
                                <button type="button" class="img-btn img-btn-change" onclick="document.getElementById('file-{{ $lk }}').click()"><i class="fas fa-exchange-alt"></i> Change</button>
                                <button type="button" class="img-btn img-btn-remove" onclick="removeImg('{{ $lk }}')"><i class="fas fa-trash"></i> Remove</button>
                            </div>
                        </div>
                        <div class="img-url-tab">
                            <div class="img-url-toggle" onclick="toggleUrl('{{ $lk }}', this)">
                                <i class="fas fa-link"></i> Or paste image URL
                                <i class="fas fa-chevron-down" id="chevron-{{ $lk }}" style="margin-left:auto"></i>
                            </div>
                            <div class="img-url-body" id="urlbody-{{ $lk }}">
                                <div class="lf-input-wrap" style="margin-top:8px">
                                    <div class="lf-prefix"><i class="fas fa-link"></i></div>
                                    <input type="url" name="landing_{{ $lk }}" id="url-{{ $lk }}" class="lfi" value="{{ $lCur }}" placeholder="https://cdn.esahlan.com/logo.png" oninput="urlPreview('{{ $lk }}', this.value)">
                                </div>
                                <div class="lf-hint">{{ $lHint }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @include('admin.landing._savebar')
    </div>

    {{-- ══════ HERO ══════ --}}
    <div class="lpm-panel active" id="pan-hero">
        <div class="lpm-panel-hero" style="background:linear-gradient(135deg,#f97316 0%,#ea580c 60%,#c2410c 100%)">
            <div class="lpm-panel-hero-left">
                <div class="lpm-panel-hero-icon"><i class="fas fa-star"></i></div>
                <div><h2>Hero Section</h2><p>The first impression — badge, headline, subtitle, and two action buttons</p></div>
            </div>
            <div class="lpm-panel-hero-badge"><i class="fas fa-circle"></i> Live on esahlan.com</div>
        </div>

        <div class="lpm-zone">
            <div class="lpm-zone-head">
                <div class="lpm-zone-head-left"><div class="lpm-zone-pip" style="background:#f97316"></div><span class="lpm-zone-title">Badge &amp; Headline</span></div>
                <span class="lpm-zone-chip chip-live"><i class="fas fa-wifi"></i> Live</span>
            </div>
            <div class="lpm-zone-body g2">
                <div class="lf" style="--accent:#f97316">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#f97316"></div>Badge Text</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-tag"></i></div><input type="text" name="landing_hero_badge" class="lfi" value="{{ $settings['landing_hero_badge'] ?: 'Smart Services Platform' }}" placeholder="e.g. Smart Services Platform"></div>
                    <div class="lf-hint">Small label shown above the main headline on the page.</div>
                </div>
                <div class="lf" style="--accent:#f97316">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#ea580c"></div>Hero Title</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-heading"></i></div><input type="text" name="landing_hero_title" class="lfi" value="{{ $settings['landing_hero_title'] ?: 'All Services in One Place' }}" placeholder="Main headline"></div>
                </div>
            </div>
        </div>

        <div class="lpm-zone">
            <div class="lpm-zone-head">
                <div class="lpm-zone-head-left"><div class="lpm-zone-pip" style="background:#fb923c"></div><span class="lpm-zone-title">Subtitle &amp; Buttons</span></div>
            </div>
            <div class="lpm-zone-body g1">
                <div class="lf" style="--accent:#f97316">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#fb923c"></div>Hero Subtitle</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-align-left"></i></div><textarea name="landing_hero_subtitle" class="lfi">{{ $settings['landing_hero_subtitle'] ?: 'eSahlan is an all-in-one platform combining delivery, shopping, and daily services in a single easy-to-use app.' }}</textarea></div>
                </div>
                <div class="g2">
                    <div class="lf" style="--accent:#f97316">
                        <div class="lf-label"><div class="lf-label-dot" style="background:#f97316"></div>Primary Button</div>
                        <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-mouse-pointer"></i></div><input type="text" name="landing_hero_btn1" class="lfi" value="{{ $settings['landing_hero_btn1'] ?: 'Explore Services' }}"></div>
                    </div>
                    <div class="lf" style="--accent:#9ca3af">
                        <div class="lf-label"><div class="lf-label-dot" style="background:#9ca3af"></div>Secondary Button</div>
                        <div class="lf-input-wrap"><div class="lf-prefix"><i class="far fa-hand-pointer"></i></div><input type="text" name="landing_hero_btn2" class="lfi" value="{{ $settings['landing_hero_btn2'] ?: 'Learn More' }}"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Hero Image upload --}}
        @php $heroImg = $settings['landing_hero_image'] ?? ''; @endphp
        <div class="lpm-zone">
            <div class="lpm-zone-head">
                <div class="lpm-zone-head-left"><div class="lpm-zone-pip" style="background:#f97316"></div><span class="lpm-zone-title">Hero Shape Card Image</span></div>
                <span class="lpm-zone-chip chip-img"><i class="fas fa-upload"></i> PNG / JPG / WebP</span>
            </div>
            <div class="lpm-zone-body">
                <div class="lf-hint" style="margin-bottom:14px">Sawirka ku muuqda Shape Card-ka bidix ku yaal hero section-ka. Recommended: portrait PNG, min 600×750px.</div>
                <div class="img-upload-block" id="block-hero_image">
                    <div class="img-drop-zone {{ $heroImg ? 'has-img' : '' }}" id="zone-hero_image" onclick="document.getElementById('file-hero_image').click()">
                        @if($heroImg)
                            <img src="{{ $heroImg }}" alt="Hero Image" id="preview-hero_image">
                        @else
                            <div id="placeholder-hero_image">
                                <div class="img-drop-icon" style="background:linear-gradient(135deg,#f97316,#ea580c)"><i class="fas fa-mobile-alt"></i></div>
                                <div class="img-drop-text"><strong>Upload Hero Image</strong><span>PNG, JPG, WebP — portrait recommended</span></div>
                            </div>
                        @endif
                        <input type="file" id="file-hero_image" name="landing_hero_image_file" accept="image/*" class="img-drop-input" onchange="handleUpload('hero_image', this)" onclick="event.stopPropagation()">
                    </div>
                    <div class="img-toolbar" id="toolbar-hero_image" style="{{ $heroImg ? '' : 'display:none' }}">
                        <span class="img-toolbar-name" id="fname-hero_image">{{ $heroImg ? basename($heroImg) : '' }}</span>
                        <div class="img-toolbar-actions">
                            <button type="button" class="img-btn img-btn-change" onclick="document.getElementById('file-hero_image').click()"><i class="fas fa-exchange-alt"></i> Change</button>
                            <button type="button" class="img-btn img-btn-remove" onclick="removeImg('hero_image')"><i class="fas fa-trash"></i> Remove</button>
                        </div>
                    </div>
                    <div class="img-url-tab">
                        <div class="img-url-toggle" onclick="toggleUrl('hero_image', this)">
                            <i class="fas fa-link"></i> Or paste image URL
                            <i class="fas fa-chevron-down" id="chevron-hero_image" style="margin-left:auto"></i>
                        </div>
                        <div class="img-url-body" id="urlbody-hero_image">
                            <div class="lf-input-wrap" style="margin-top:8px">
                                <div class="lf-prefix"><i class="fas fa-link"></i></div>
                                <input type="url" name="landing_hero_image" id="url-hero_image" class="lfi" value="{{ $heroImg }}" placeholder="https://cdn.esahlan.com/hero.png" oninput="urlPreview('hero_image', this.value)">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('admin.landing._savebar')
    </div>

    {{-- ══════ STATS ══════ --}}
    <div class="lpm-panel" id="pan-stats">
        <div class="lpm-panel-hero" style="background:linear-gradient(135deg,#8b5cf6 0%,#6d28d9 60%,#4c1d95 100%)">
            <div class="lpm-panel-hero-left">
                <div class="lpm-panel-hero-icon"><i class="fas fa-chart-bar"></i></div>
                <div><h2>Stats Bar</h2><p>4 key numbers displayed beneath the hero — trust signals that matter</p></div>
            </div>
            <div class="lpm-panel-hero-badge"><i class="fas fa-hashtag"></i> 4 Numbers</div>
        </div>

        <div class="stat-grid">
            @foreach([
                [1,'#8b5cf6','12+','Integrated Services'],
                [2,'#7c3aed','24/7','Continuous Support'],
                [3,'#6d28d9','100%','Safe & Reliable'],
                [4,'#5b21b6','∞','Unlimited Potential'],
            ] as [$i,$c,$dn,$dl])
            <div class="stat-card" style="--sc-color:{{ $c }}">
                <div class="stat-card-header"><span class="stat-card-num" style="color:{{ $c }}">STAT {{ $i }}</span></div>
                <div class="stat-card-body">
                    <div class="lf" style="--accent:{{ $c }}">
                        <div class="lf-label"><div class="lf-label-dot" style="background:{{ $c }}"></div>Value / Number</div>
                        <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-hashtag"></i></div><input type="text" name="landing_stat{{ $i }}_num" class="lfi" value="{{ $settings['landing_stat'.$i.'_num'] ?: $dn }}" placeholder="{{ $dn }}"></div>
                    </div>
                    <div class="lf" style="--accent:#9ca3af">
                        <div class="lf-label"><div class="lf-label-dot" style="background:#9ca3af"></div>Label</div>
                        <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-font"></i></div><input type="text" name="landing_stat{{ $i }}_label" class="lfi" value="{{ $settings['landing_stat'.$i.'_label'] ?: $dl }}" placeholder="{{ $dl }}"></div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @include('admin.landing._savebar')
    </div>

    {{-- ══════ HOW IT WORKS ══════ --}}
    <div class="lpm-panel" id="pan-how">
        <div class="lpm-panel-hero" style="background:linear-gradient(135deg,#0ea5e9 0%,#0284c7 60%,#0369a1 100%)">
            <div class="lpm-panel-hero-left">
                <div class="lpm-panel-hero-icon"><i class="fas fa-list-ol"></i></div>
                <div><h2>How It Works</h2><p>3-step process section that follows the services grid</p></div>
            </div>
            <div class="lpm-panel-hero-badge"><i class="fas fa-pencil-alt"></i> Heading only</div>
        </div>
        <div class="lpm-zone">
            <div class="lpm-zone-head">
                <div class="lpm-zone-head-left"><div class="lpm-zone-pip" style="background:#0ea5e9"></div><span class="lpm-zone-title">Section Heading</span></div>
                <span class="lpm-zone-chip chip-live"><i class="fas fa-wifi"></i> Live</span>
            </div>
            <div class="lpm-zone-body g1">
                <div class="lf" style="--accent:#0ea5e9">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#0ea5e9"></div>Title</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-heading"></i></div><input type="text" name="landing_how_title" class="lfi" value="{{ $settings['landing_how_title'] ?: 'Order in three steps' }}"></div>
                </div>
                <div class="lf" style="--accent:#0284c7">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#0284c7"></div>Subtitle</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-align-left"></i></div><textarea name="landing_how_subtitle" class="lfi" style="min-height:72px">{{ $settings['landing_how_subtitle'] ?: 'From opening the app to receiving your order — we keep it simple.' }}</textarea></div>
                </div>
            </div>
        </div>
        <div class="lpm-note"><i class="fas fa-lock"></i> The 3 step cards are part of the design template. Only the section heading above is editable.</div>
        @include('admin.landing._savebar')
    </div>

    {{-- ══════ WHY ESAHLAN ══════ --}}
    <div class="lpm-panel" id="pan-why">
        <div class="lpm-panel-hero" style="background:linear-gradient(135deg,#10b981 0%,#059669 60%,#047857 100%)">
            <div class="lpm-panel-hero-left">
                <div class="lpm-panel-hero-icon"><i class="fas fa-check-circle"></i></div>
                <div><h2>Why eSahlan</h2><p>Key differentiators — heading, subtitle, and 3 feature highlights</p></div>
            </div>
            <div class="lpm-panel-hero-badge"><i class="fas fa-layer-group"></i> 3 Features</div>
        </div>
        <div class="lpm-zone">
            <div class="lpm-zone-head">
                <div class="lpm-zone-head-left"><div class="lpm-zone-pip" style="background:#10b981"></div><span class="lpm-zone-title">Section Heading</span></div>
            </div>
            <div class="lpm-zone-body g1">
                <div class="lf" style="--accent:#10b981">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#10b981"></div>Title</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-heading"></i></div><input type="text" name="landing_why_title" class="lfi" value="{{ $settings['landing_why_title'] ?: 'A Smart Platform Built for Efficiency' }}"></div>
                </div>
                <div class="lf" style="--accent:#059669">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#059669"></div>Subtitle</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-align-left"></i></div><textarea name="landing_why_subtitle" class="lfi" style="min-height:72px">{{ $settings['landing_why_subtitle'] ?: 'An integrated system connecting customers, providers, and drivers.' }}</textarea></div>
                </div>
            </div>
        </div>
        <div class="lpm-zone" style="margin-top:0">
            <div class="lpm-zone-head">
                <div class="lpm-zone-head-left"><div class="lpm-zone-pip" style="background:#34d399"></div><span class="lpm-zone-title">Feature Highlights</span></div>
                <span class="lpm-zone-chip" style="background:#dcfce7;color:#166534">3 Items</span>
            </div>
            <div class="lpm-zone-body g1">
                @foreach([
                    [1,'#10b981','fa-layer-group','Feature 1','12 services, one account — food, flights, health, currency, and more'],
                    [2,'#059669','fa-motorcycle','Feature 2','Real drivers, real fast — motorcycles, cars, and trucks across Somalia'],
                    [3,'#047857','fa-users','Feature 3','A social community built in — reels, stories, and chat inside the app'],
                ] as [$fi,$fc,$fic,$flbl,$fdef])
                <div class="lf" style="--accent:{{ $fc }}">
                    <div class="lf-label"><div class="lf-label-dot" style="background:{{ $fc }}"></div>{{ $flbl }}</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas {{ $fic }}"></i></div><input type="text" name="landing_why_feat{{ $fi }}" class="lfi" value="{{ $settings['landing_why_feat'.$fi] ?: $fdef }}"></div>
                </div>
                @endforeach
            </div>
        </div>
        @include('admin.landing._savebar')
    </div>

    {{-- ══════ ESPACE ══════ --}}
    <div class="lpm-panel" id="pan-espace">
        <div class="lpm-panel-hero" style="background:linear-gradient(135deg,#ec4899 0%,#be185d 60%,#9d174d 100%)">
            <div class="lpm-panel-hero-left">
                <div class="lpm-panel-hero-icon"><i class="fas fa-users"></i></div>
                <div><h2>eSpace Community</h2><p>Social platform section with 9 feature cards — Feed, Live, Podcasts, Ads, and more</p></div>
            </div>
            <div class="lpm-panel-hero-badge"><i class="fas fa-th"></i> 9 Cards</div>
        </div>
        <div class="lpm-zone">
            <div class="lpm-zone-head">
                <div class="lpm-zone-head-left"><div class="lpm-zone-pip" style="background:#ec4899"></div><span class="lpm-zone-title">Section Heading</span></div>
                <span class="lpm-zone-chip chip-live"><i class="fas fa-wifi"></i> Live</span>
            </div>
            <div class="lpm-zone-body g1">
                <div class="lf" style="--accent:#ec4899">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#ec4899"></div>Title</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-heading"></i></div><input type="text" name="landing_espace_title" class="lfi" value="{{ $settings['landing_espace_title'] ?: 'More than an app — a space to create & connect' }}"></div>
                </div>
                <div class="lf" style="--accent:#be185d">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#be185d"></div>Subtitle</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-align-left"></i></div><textarea name="landing_espace_subtitle" class="lfi">{{ $settings['landing_espace_subtitle'] ?: 'Watch reels, go live, sell your content, and advertise your business — all inside the same platform you order from.' }}</textarea></div>
                </div>
            </div>
        </div>
        <div class="lpm-note"><i class="fas fa-lock"></i> The 9 feature cards (Feed, Live, Podcasts, Premium Content, Business Ads, DMs, Stories, Hashtags, Follow) are built into the design template.</div>
        @include('admin.landing._savebar')
    </div>

    {{-- ══════ JOIN NETWORK ══════ --}}
    <div class="lpm-panel" id="pan-join">
        <div class="lpm-panel-hero" style="background:linear-gradient(135deg,#f59e0b 0%,#d97706 60%,#b45309 100%)">
            <div class="lpm-panel-hero-left">
                <div class="lpm-panel-hero-icon"><i class="fas fa-handshake"></i></div>
                <div><h2>Join the Network</h2><p>Role cards for Drivers, Vendors, and Property Agents with their sign-up links</p></div>
            </div>
            <div class="lpm-panel-hero-badge"><i class="fas fa-link"></i> 3 Card Links</div>
        </div>
        <div class="lpm-zone">
            <div class="lpm-zone-head">
                <div class="lpm-zone-head-left"><div class="lpm-zone-pip" style="background:#f59e0b"></div><span class="lpm-zone-title">Section Heading</span></div>
            </div>
            <div class="lpm-zone-body g1">
                <div class="lf" style="--accent:#f59e0b">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#f59e0b"></div>Title</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-heading"></i></div><input type="text" name="landing_join_title" class="lfi" value="{{ $settings['landing_join_title'] ?: 'Built for everyone in the ecosystem' }}"></div>
                </div>
                <div class="lf" style="--accent:#d97706">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#d97706"></div>Subtitle</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-align-left"></i></div><textarea name="landing_join_subtitle" class="lfi" style="min-height:72px">{{ $settings['landing_join_subtitle'] ?: 'Whether you drive, sell, or list — eSahlan has a role for you.' }}</textarea></div>
                </div>
            </div>
        </div>
        <div class="lpm-zone" style="margin-top:0">
            <div class="lpm-zone-head">
                <div class="lpm-zone-head-left"><div class="lpm-zone-pip" style="background:#fbbf24"></div><span class="lpm-zone-title">Card Registration Links</span></div>
                <span class="lpm-zone-chip chip-url"><i class="fas fa-link"></i> URLs</span>
            </div>
            <div class="lpm-zone-body g3">
                @foreach([
                    ['driver','fa-motorcycle','#10b981','Driver Apply URL'],
                    ['vendor','fa-store','#f97316','Vendor Register URL'],
                    ['agent','fa-building','#6366f1','Property Agent URL'],
                ] as [$k,$ic,$c,$lbl])
                <div class="lf" style="--accent:{{ $c }}">
                    <div class="lf-label"><div class="lf-label-dot" style="background:{{ $c }}"></div>{{ $lbl }}</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas {{ $ic }}"></i></div><input type="url" name="landing_{{ $k }}_url" class="lfi" value="{{ $settings['landing_'.$k.'_url']??'' }}" placeholder="https://…"></div>
                </div>
                @endforeach
            </div>
        </div>
        @include('admin.landing._savebar')
    </div>

    {{-- ══════ REGISTRATION CTA ══════ --}}
    <div class="lpm-panel" id="pan-cta">
        <div class="lpm-panel-hero" style="background:linear-gradient(135deg,#6366f1 0%,#4f46e5 60%,#4338ca 100%)">
            <div class="lpm-panel-hero-left">
                <div class="lpm-panel-hero-icon"><i class="fas fa-rocket"></i></div>
                <div><h2>Registration CTA</h2><p>Main call-to-action with Vendor, Driver, and Agent sign-up cards</p></div>
            </div>
            <div class="lpm-panel-hero-badge"><i class="fas fa-toggle-on"></i> Toggle visibility</div>
        </div>

        <div class="lpm-zone">
            <div class="lpm-zone-head">
                <div class="lpm-zone-head-left"><div class="lpm-zone-pip" style="background:#6366f1"></div><span class="lpm-zone-title">Section Heading</span></div>
            </div>
            <div class="lpm-zone-body g1">
                <div class="lf" style="--accent:#6366f1">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#6366f1"></div>Title</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-heading"></i></div><input type="text" name="landing_cta_title" class="lfi" value="{{ $settings['landing_cta_title'] ?: 'Join the eSahlan Network' }}"></div>
                </div>
                <div class="lf" style="--accent:#4f46e5">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#4f46e5"></div>Subtitle</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-align-left"></i></div><textarea name="landing_cta_subtitle" class="lfi" style="min-height:72px">{{ $settings['landing_cta_subtitle'] ?: 'Are you a vendor, driver, or property agent? Register now and start earning.' }}</textarea></div>
                </div>
            </div>
        </div>

        @foreach([
            ['vendor','fa-store','#f97316','Vendor Card','Vendor','List your store & start selling','landing_show_vendor'],
            ['driver','fa-motorcycle','#10b981','Driver Card','Delivery Driver','Deliver orders & earn daily','landing_show_driver'],
            ['agent','fa-building','#6366f1','Property Agent Card','Property Agent','List properties on eRent','landing_show_agent'],
        ] as [$k,$ic,$c,$cl,$dt,$ds,$sk])
        <div class="lpm-zone" style="margin-top:0">
            <div class="lpm-zone-head">
                <div class="lpm-zone-head-left">
                    <div class="lpm-zone-pip" style="background:{{ $c }}"></div>
                    <span class="lpm-zone-title">{{ $cl }}</span>
                </div>
                <label class="ts"><input type="checkbox" name="{{ $sk }}" value="1" {{ ($settings[$sk]??'1')=='1'?'checked':'' }}><span class="ts-sl"></span></label>
            </div>
            <div class="lpm-zone-body g3">
                <div class="lf" style="--accent:{{ $c }}">
                    <div class="lf-label"><div class="lf-label-dot" style="background:{{ $c }}"></div>Card Title</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-font"></i></div><input type="text" name="landing_{{ $k }}_label" class="lfi" value="{{ $settings['landing_'.$k.'_label'] ?: $dt }}"></div>
                </div>
                <div class="lf" style="--accent:#9ca3af">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#9ca3af"></div>Subtitle</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-align-left"></i></div><input type="text" name="landing_{{ $k }}_sub" class="lfi" value="{{ $settings['landing_'.$k.'_sub'] ?: $ds }}"></div>
                </div>
                <div class="lf" style="--accent:#6b7280">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#6b7280"></div>Register URL</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-link"></i></div><input type="url" name="landing_{{ $k }}_url" class="lfi" value="{{ $settings['landing_'.$k.'_url']??'' }}" placeholder="https://…"></div>
                </div>
            </div>
        </div>
        @endforeach
        @include('admin.landing._savebar')
    </div>

    {{-- ══════ DOWNLOAD APP ══════ --}}
    <div class="lpm-panel" id="pan-download">
        <div class="lpm-panel-hero" style="background:linear-gradient(135deg,#14b8a6 0%,#0f766e 60%,#0d5c56 100%)">
            <div class="lpm-panel-hero-left">
                <div class="lpm-panel-hero-icon"><i class="fas fa-mobile-alt"></i></div>
                <div><h2>Download App Section</h2><p>Heading, subtitle, store links, and phone mockup images on left &amp; right</p></div>
            </div>
            <div class="lpm-panel-hero-badge"><i class="fas fa-image"></i> 2 Mockup Slots</div>
        </div>

        <div class="lpm-zone">
            <div class="lpm-zone-head">
                <div class="lpm-zone-head-left"><div class="lpm-zone-pip" style="background:#14b8a6"></div><span class="lpm-zone-title">Heading &amp; Subtitle</span></div>
            </div>
            <div class="lpm-zone-body g1">
                <div class="lf" style="--accent:#14b8a6">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#14b8a6"></div>Heading</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-heading"></i></div><input type="text" name="landing_dl_heading" class="lfi" value="{{ $settings['landing_dl_heading'] ?: 'everything in one app.' }}"></div>
                    <div class="lf-hint">The first letter is automatically highlighted in orange on the live page.</div>
                </div>
                <div class="lf" style="--accent:#0f766e">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#0f766e"></div>Subtitle</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fas fa-align-left"></i></div><textarea name="landing_dl_subtitle" class="lfi">{{ $settings['landing_dl_subtitle'] ?: "Join Somalia's fastest-growing platform. Download eSahlan today." }}</textarea></div>
                </div>
            </div>
        </div>

        <div class="lpm-zone" style="margin-top:0">
            <div class="lpm-zone-head">
                <div class="lpm-zone-head-left"><div class="lpm-zone-pip" style="background:#22c55e"></div><span class="lpm-zone-title">App Store Links</span></div>
                <span class="lpm-zone-chip chip-url"><i class="fas fa-link"></i> URLs</span>
            </div>
            <div class="lpm-zone-body g2">
                <div class="lf" style="--accent:#16a34a">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#16a34a"></div>Google Play URL</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fab fa-google-play"></i></div><input type="url" name="landing_gplay_url" class="lfi" value="{{ $settings['landing_gplay_url']??'' }}" placeholder="https://play.google.com/store/apps/…"></div>
                </div>
                <div class="lf" style="--accent:#64748b">
                    <div class="lf-label"><div class="lf-label-dot" style="background:#64748b"></div>App Store URL</div>
                    <div class="lf-input-wrap"><div class="lf-prefix"><i class="fab fa-apple"></i></div><input type="url" name="landing_appstore_url" class="lfi" value="{{ $settings['landing_appstore_url']??'' }}" placeholder="https://apps.apple.com/app/…"></div>
                </div>
            </div>
        </div>

        <div class="lpm-zone" style="margin-top:0">
            <div class="lpm-zone-head">
                <div class="lpm-zone-head-left"><div class="lpm-zone-pip" style="background:#14b8a6"></div><span class="lpm-zone-title">Phone Mockup Images</span></div>
                <span class="lpm-zone-chip chip-img"><i class="fas fa-upload"></i> Upload or URL</span>
            </div>
            <div class="lpm-zone-body g2">
                @foreach([
                    ['dl_image_left','Left Mockup','Shown on the left side of the download section'],
                    ['dl_image_right','Right Mockup','Shown on the right side of the download section'],
                ] as [$k,$lbl,$hint])
                @php $currentImg = $settings['landing_'.$k] ?? ''; @endphp
                <div>
                    <div class="lf-label" style="margin-bottom:10px"><div class="lf-label-dot" style="background:#14b8a6"></div>{{ $lbl }}</div>

                    <div class="img-upload-block" id="block-{{ $k }}">
                        {{-- Drop / Preview zone --}}
                        <div class="img-drop-zone {{ $currentImg ? 'has-img' : '' }}" id="zone-{{ $k }}" onclick="document.getElementById('file-{{ $k }}').click()">
                            @if($currentImg)
                                <img src="{{ $currentImg }}" alt="{{ $lbl }}" id="preview-{{ $k }}">
                            @else
                                <div id="placeholder-{{ $k }}">
                                    <div class="img-drop-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                    <div class="img-drop-text">
                                        <strong>Click to upload image</strong>
                                        <span>PNG, JPG, WebP — transparent PNG recommended</span>
                                    </div>
                                </div>
                            @endif
                            <input type="file" id="file-{{ $k }}" name="landing_{{ $k }}_file" accept="image/*" class="img-drop-input" onchange="handleUpload('{{ $k }}', this)" onclick="event.stopPropagation()">
                        </div>

                        {{-- Toolbar (shown when image exists) --}}
                        <div class="img-toolbar" id="toolbar-{{ $k }}" style="{{ $currentImg ? '' : 'display:none' }}">
                            <span class="img-toolbar-name" id="fname-{{ $k }}">{{ $currentImg ? basename($currentImg) : '' }}</span>
                            <div class="img-toolbar-actions">
                                <button type="button" class="img-btn img-btn-change" onclick="document.getElementById('file-{{ $k }}').click()"><i class="fas fa-exchange-alt"></i> Change</button>
                                <button type="button" class="img-btn img-btn-remove" onclick="removeImg('{{ $k }}')"><i class="fas fa-trash"></i> Remove</button>
                            </div>
                        </div>

                        {{-- URL fallback tab --}}
                        <div class="img-url-tab">
                            <div class="img-url-toggle" onclick="toggleUrl('{{ $k }}', this)">
                                <i class="fas fa-link"></i> Or paste an image URL
                                <i class="fas fa-chevron-down" id="chevron-{{ $k }}" style="margin-left:auto"></i>
                            </div>
                            <div class="img-url-body" id="urlbody-{{ $k }}">
                                <div class="lf-input-wrap" style="margin-top:2px">
                                    <div class="lf-prefix"><i class="fas fa-link"></i></div>
                                    <input type="url" name="landing_{{ $k }}" id="url-{{ $k }}" class="lfi" value="{{ $currentImg }}" placeholder="https://cdn.esahlan.com/…" oninput="urlPreview('{{ $k }}', this.value)">
                                </div>
                                <div class="lf-hint">{{ $hint }}. Leave blank to use the uploaded file above.</div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        @include('admin.landing._savebar')
    </div>

</div>{{-- /lpm-content --}}
</div>{{-- /lpm-root --}}
</form>

<script>
var panelMeta={
    logos:   {name:'Brand Logos',    desc:'Upload the eSahlan logo for the navbar, hero section, and footer.'},
    hero:    {name:'Hero',          desc:'Control the headline, subtitle, and action buttons at the top of the page.'},
    stats:   {name:'Stats Bar',     desc:'Edit the 4 key numbers shown beneath the hero section.'},
    how:     {name:'How It Works',  desc:'Section heading for the 3-step ordering process.'},
    why:     {name:'Why eSahlan',   desc:'Key differentiators — heading, subtitle, and feature highlights.'},
    espace:  {name:'eSpace',        desc:'Community section heading for the 9-card social feature grid.'},
    join:    {name:'Join Network',  desc:'Role cards for Drivers, Vendors, and Property Agents.'},
    cta:     {name:'Registration CTA', desc:'Main call-to-action with sign-up cards and visibility toggles.'},
    download:{name:'Download App',  desc:'App store links, heading, and phone mockup image slots.'},
};
function handleUpload(key, input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    var reader = new FileReader();
    reader.onload = function(e) {
        setImg(key, e.target.result, file.name);
    };
    reader.readAsDataURL(file);
}
function setImg(key, src, name) {
    var zone = document.getElementById('zone-'+key);
    var toolbar = document.getElementById('toolbar-'+key);
    var ph = document.getElementById('placeholder-'+key);
    var prev = document.getElementById('preview-'+key);
    // show image
    zone.classList.add('has-img');
    if (ph) ph.style.display = 'none';
    if (prev) { prev.src = src; }
    else {
        var img = document.createElement('img');
        img.id = 'preview-'+key;
        img.src = src;
        img.alt = key;
        zone.insertBefore(img, zone.querySelector('.img-drop-input'));
    }
    // show toolbar
    toolbar.style.display = 'flex';
    document.getElementById('fname-'+key).textContent = name || '';
    // clear URL field so file takes priority
    var urlField = document.getElementById('url-'+key);
    if (urlField) urlField.value = '';
}
function removeImg(key) {
    var zone = document.getElementById('zone-'+key);
    var toolbar = document.getElementById('toolbar-'+key);
    var prev = document.getElementById('preview-'+key);
    var ph = document.getElementById('placeholder-'+key);
    var fileInput = document.getElementById('file-'+key);
    zone.classList.remove('has-img');
    if (prev) prev.remove();
    if (ph) ph.style.display = '';
    toolbar.style.display = 'none';
    fileInput.value = '';
    var urlField = document.getElementById('url-'+key);
    if (urlField) urlField.value = '';
}
function urlPreview(key, url) {
    if (!url) return;
    var prev = document.getElementById('preview-'+key);
    var ph = document.getElementById('placeholder-'+key);
    var zone = document.getElementById('zone-'+key);
    var toolbar = document.getElementById('toolbar-'+key);
    if (prev) { prev.src = url; }
    else {
        var img = document.createElement('img');
        img.id = 'preview-'+key;
        img.src = url;
        img.alt = key;
        zone.insertBefore(img, zone.querySelector('.img-drop-input'));
    }
    zone.classList.add('has-img');
    if (ph) ph.style.display = 'none';
    toolbar.style.display = 'flex';
    document.getElementById('fname-'+key).textContent = url.split('/').pop();
}
function toggleUrl(key, el) {
    var body = document.getElementById('urlbody-'+key);
    var chev = document.getElementById('chevron-'+key);
    body.classList.toggle('open');
    chev.style.transform = body.classList.contains('open') ? 'rotate(180deg)' : '';
}

// ── AJAX save ──────────────────────────────────────────────────────────────
var _activePanel = 'hero';
function nav(id,el){
    _activePanel = id;
    document.querySelectorAll('.lpm-panel').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.lpm-nav-item').forEach(n=>n.classList.remove('active'));
    document.getElementById('pan-'+id).classList.add('active');
    el.classList.add('active');
    var m=panelMeta[id]||{};
    document.getElementById('bc-active').textContent=m.name||id;
    document.getElementById('panel-title').textContent=m.name||id;
    document.getElementById('panel-desc').textContent=m.desc||'';
}
function doSave(btn){
    var form = document.getElementById('lpm-form');
    if(!form){ alert('Form not found'); return; }
    var origHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
    btn.disabled = true;
    var fd = new FormData(form);
    fetch(form.action, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
    .then(function(r){ return r.ok ? r.text() : Promise.reject(r.status); })
    .then(function(){
        btn.innerHTML = '<i class="fas fa-check"></i> Saved!';
        btn.style.background = 'linear-gradient(135deg,#22c55e,#16a34a)';
        showToast('Changes saved — live on esahlan.com', 'success');
        setTimeout(function(){ btn.innerHTML=origHtml; btn.style.background=''; btn.disabled=false; }, 2500);
    })
    .catch(function(status){
        btn.innerHTML = origHtml; btn.disabled = false;
        showToast('Save failed ('+status+'). Try again.', 'error');
    });
}
function showToast(msg, type){
    var t = document.createElement('div');
    t.style.cssText = 'position:fixed;top:24px;right:24px;z-index:99999;background:'+(type==='success'?'#22c55e':'#ef4444')+';color:#fff;padding:14px 22px;border-radius:12px;font-weight:700;font-size:.88rem;box-shadow:0 8px 30px rgba(0,0,0,.2);display:flex;align-items:center;gap:10px;animation:toastIn .3s ease';
    t.innerHTML = '<i class="fas '+(type==='success'?'fa-check-circle':'fa-exclamation-circle')+'"></i> '+msg;
    document.body.appendChild(t);
    setTimeout(function(){ t.style.opacity='0'; t.style.transition='opacity .4s'; setTimeout(function(){t.remove()},400); }, 3500);
}
</script>
<style>@keyframes toastIn{from{transform:translateY(-12px);opacity:0}to{transform:translateY(0);opacity:1}}</style>
@endsection
