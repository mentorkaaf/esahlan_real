@extends('admin.layouts.app')
@section('title', 'Landing Page — Section Visibility')
@section('content')
<style>
/* ═══════════════════════════════════════════
   LANDING SECTIONS — VISIBILITY MANAGER
═══════════════════════════════════════════ */
.lsm-root{max-width:1100px;margin:0 auto;padding:0 4px 60px}

/* ── Page header ── */
.lsm-header{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:32px;flex-wrap:wrap}
.lsm-header-left h1{font-size:1.6rem;font-weight:900;color:var(--text);letter-spacing:-.035em;margin-bottom:5px}
.lsm-header-left p{font-size:.84rem;color:var(--text-muted);line-height:1.5}
.lsm-breadcrumb{display:flex;align-items:center;gap:6px;font-size:.74rem;color:var(--text-muted);margin-bottom:8px}
.lsm-breadcrumb i{font-size:.5rem}
.btn-live{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;padding:10px 20px;border-radius:11px;font-size:.84rem;font-weight:700;text-decoration:none;transition:all .2s;box-shadow:0 3px 12px rgba(16,185,129,.3)}
.btn-live:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(16,185,129,.4);color:#fff}

/* ── Stats row ── */
.lsm-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:28px}
.lsm-stat{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 20px;position:relative;overflow:hidden}
.lsm-stat::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--sc,var(--brand))}
.lsm-stat-num{font-size:1.6rem;font-weight:900;color:var(--sc,var(--brand));letter-spacing:-.04em;line-height:1}
.lsm-stat-lbl{font-size:.72rem;font-weight:600;color:var(--text-muted);margin-top:5px;text-transform:uppercase;letter-spacing:.8px}

/* ── Section group card ── */
.lsm-group{background:var(--surface);border:1px solid var(--border);border-radius:18px;overflow:hidden;margin-bottom:20px;transition:box-shadow .2s}
.lsm-group:hover{box-shadow:0 4px 24px rgba(0,0,0,.08)}
.lsm-group-header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;background:var(--bg);border-bottom:1px solid var(--border);cursor:pointer;user-select:none}
.lsm-group-header-left{display:flex;align-items:center;gap:14px}
.lsm-group-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:.95rem;color:#fff;flex-shrink:0}
.lsm-group-title{font-size:.95rem;font-weight:800;color:var(--text)}
.lsm-group-meta{font-size:.73rem;color:var(--text-muted);margin-top:2px}
.lsm-group-header-right{display:flex;align-items:center;gap:16px}
.lsm-chevron{font-size:.72rem;color:var(--text-muted);transition:transform .25s}
.lsm-group.open .lsm-chevron{transform:rotate(180deg)}

/* ── Master toggle for a group ── */
.lsm-master-toggle{display:flex;align-items:center;gap:10px}
.lsm-master-label{font-size:.78rem;font-weight:700;color:var(--text)}

/* ── Children list ── */
.lsm-children{display:none;padding:0 22px 22px;animation:slideDown .2s ease}
.lsm-group.open .lsm-children{display:block}
@keyframes slideDown{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}

.lsm-children-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;margin-top:16px}

/* ── Item card ── */
.lsm-item{background:var(--bg);border:1.5px solid var(--border);border-radius:13px;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;transition:all .2s;position:relative;overflow:hidden}
.lsm-item::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--ic,var(--brand));border-radius:3px 0 0 3px;opacity:0;transition:opacity .2s}
.lsm-item.active::before{opacity:1}
.lsm-item.disabled{opacity:.45}
.lsm-item-left{display:flex;align-items:center;gap:10px;min-width:0}
.lsm-item-dot{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.7rem;color:#fff;flex-shrink:0}
.lsm-item-name{font-size:.87rem;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lsm-item-type{font-size:.67rem;color:var(--text-muted);margin-top:1px}

/* ── Toggle switch ── */
.ts{position:relative;width:44px;height:24px;flex-shrink:0}
.ts input{opacity:0;width:0;height:0;position:absolute}
.ts-sl{position:absolute;inset:0;background:var(--border);border-radius:24px;cursor:pointer;transition:.25s}
.ts-sl::before{content:'';position:absolute;width:18px;height:18px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.25s;box-shadow:0 2px 4px rgba(0,0,0,.2)}
.ts input:checked+.ts-sl{background:var(--ic,var(--brand))}
.ts input:checked+.ts-sl::before{transform:translateX(20px)}
.ts input:disabled+.ts-sl{opacity:.4;cursor:not-allowed}

/* ── Disabled stripe ── */
.lsm-item.disabled .lsm-item-name{text-decoration:line-through;color:var(--text-muted)}

/* ── Realtime pill ── */
.rt-pill{display:inline-flex;align-items:center;gap:6px;background:color-mix(in srgb,#10b981 10%,transparent);border:1px solid color-mix(in srgb,#10b981 25%,transparent);padding:5px 12px;border-radius:50px;font-size:.72rem;font-weight:700;color:#10b981}
.rt-dot{width:6px;height:6px;border-radius:50%;background:#10b981;animation:rtPulse 2s infinite}
@keyframes rtPulse{0%,100%{opacity:1}50%{opacity:.3}}

/* ── Toast ── */
.lsm-toast{position:fixed;top:24px;right:24px;z-index:9999;padding:13px 20px;border-radius:12px;font-weight:700;font-size:.85rem;color:#fff;display:flex;align-items:center;gap:10px;box-shadow:0 8px 30px rgba(0,0,0,.2);animation:toastIn .3s ease;pointer-events:none}
@keyframes toastIn{from{transform:translateX(20px);opacity:0}to{transform:translateX(0);opacity:1}}

/* ── Info banner ── */
.lsm-info{display:flex;align-items:flex-start;gap:12px;background:color-mix(in srgb,var(--brand) 6%,transparent);border:1px solid color-mix(in srgb,var(--brand) 20%,transparent);border-radius:13px;padding:14px 18px;margin-bottom:24px;font-size:.82rem;color:var(--text-muted);line-height:1.5}
.lsm-info i{color:var(--brand);flex-shrink:0;margin-top:1px}

@media(max-width:760px){
    .lsm-stats{grid-template-columns:repeat(2,1fr)}
    .lsm-children-grid{grid-template-columns:1fr}
}
</style>

@if(session('success'))
<div style="display:flex;align-items:center;gap:10px;background:#d1fae5;border:1px solid #86efac;color:#14532d;border-radius:12px;padding:13px 18px;margin-bottom:20px;font-weight:700;font-size:.87rem">
    <i class="fas fa-check-circle"></i> {{ session('success') }}
</div>
@endif

<div class="lsm-root">

    {{-- Header --}}
    <div class="lsm-header">
        <div class="lsm-header-left">
            <div class="lsm-breadcrumb">
                <span>Admin</span><i class="fas fa-chevron-right"></i>
                <a href="{{ route('admin.landing.index') }}" style="color:inherit;text-decoration:none">Landing Page</a>
                <i class="fas fa-chevron-right"></i>
                <span style="color:var(--text);font-weight:700">Section Visibility</span>
            </div>
            <h1>Section Visibility</h1>
            <p>Toggle which sections and services appear on <strong>esahlan.com</strong>. Changes take effect in real-time — no deploy needed.</p>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <div class="rt-pill"><div class="rt-dot"></div> Realtime via WebSocket</div>
            <a href="https://esahlan.com" target="_blank" class="btn-live"><i class="fas fa-external-link-alt"></i> View Live</a>
        </div>
    </div>

    {{-- Stats ──────────────────────────────────────────────────────────────── --}}
    @php
        $totalSections = $sections->count();
        $enabledSections = $sections->where('is_enabled', true)->count();
        $totalItems = $sections->flatMap(fn($s) => $s->children_list)->count();
        $enabledItems = $sections->flatMap(fn($s) => $s->children_list)->where('is_enabled', true)->count();
    @endphp
    <div class="lsm-stats">
        <div class="lsm-stat" style="--sc:#f97316">
            <div class="lsm-stat-num">{{ $enabledSections }}/{{ $totalSections }}</div>
            <div class="lsm-stat-lbl">Sections Active</div>
        </div>
        <div class="lsm-stat" style="--sc:#10b981">
            <div class="lsm-stat-num">{{ $enabledItems }}/{{ $totalItems }}</div>
            <div class="lsm-stat-lbl">Items Active</div>
        </div>
        <div class="lsm-stat" style="--sc:#6366f1">
            <div class="lsm-stat-num">{{ $totalItems }}</div>
            <div class="lsm-stat-lbl">Total Items</div>
        </div>
        <div class="lsm-stat" style="--sc:#0ea5e9">
            <div class="lsm-stat-num" id="last-change">—</div>
            <div class="lsm-stat-lbl">Last Changed</div>
        </div>
    </div>

    {{-- Info banner --}}
    <div class="lsm-info">
        <i class="fas fa-bolt"></i>
        <div>Every toggle sends an instant WebSocket broadcast to the landing page. If the landing page uses JavaScript to read the API, changes are visible within seconds. The API endpoint is: <code style="background:rgba(0,0,0,.07);padding:2px 6px;border-radius:5px;font-size:.8rem">GET /api/v1/landing/sections</code></div>
    </div>

    {{-- Section groups ─────────────────────────────────────────────────────── --}}
    @php
    $groupColors = [
        'hero'       => ['#f97316','#ea580c','fas fa-star'],
        'services'   => ['#0ea5e9','#0284c7','fas fa-th-large'],
        'espace'     => ['#ec4899','#be185d','fas fa-play-circle'],
        'why_esahlan'=> ['#10b981','#059669','fas fa-check-circle'],
        'cta'        => ['#6366f1','#4f46e5','fas fa-rocket'],
    ];
    $serviceColors = [
        'efood'=>'#f97316','eshop'=>'#0ea5e9','ewholesale'=>'#8b5cf6',
        'egrocery'=>'#10b981','eparcel'=>'#f59e0b','elaundry'=>'#06b6d4',
        'emoving'=>'#ef4444','ehealth'=>'#ec4899','erent'=>'#6366f1',
        'eticket'=>'#84cc16','eexchange'=>'#14b8a6','edata'=>'#a78bfa',
    ];
    $espaceColors = [
        'feed_reels'=>'#ec4899','live_streaming'=>'#ef4444','podcasts'=>'#f59e0b',
        'premium_content'=>'#8b5cf6','business_advertising'=>'#f97316',
        'direct_messages'=>'#0ea5e9','stories'=>'#10b981',
        'hashtag_discovery'=>'#06b6d4','follow_connect'=>'#6366f1',
    ];
    $itemColors = array_merge($serviceColors, $espaceColors);
    @endphp

    @foreach($sections as $section)
    @php
        $gc = $groupColors[$section->slug] ?? ['#64748b','#475569','fas fa-layer-group'];
        $childCount = $section->children_list->count();
        $childEnabled = $section->children_list->where('is_enabled', true)->count();
    @endphp
    <div class="lsm-group {{ $loop->first ? 'open' : '' }}" id="group-{{ $section->slug }}">

        {{-- Group header --}}
        <div class="lsm-group-header" onclick="toggleGroup('{{ $section->slug }}')">
            <div class="lsm-group-header-left">
                <div class="lsm-group-icon" style="background:linear-gradient(135deg,{{ $gc[0] }},{{ $gc[1] }})">
                    <i class="{{ $gc[2] }}"></i>
                </div>
                <div>
                    <div class="lsm-group-title">{{ $section->label }}</div>
                    <div class="lsm-group-meta">
                        @if($childCount > 0)
                            {{ $childEnabled }}/{{ $childCount }} items enabled
                        @else
                            Top-level section
                        @endif
                        · <span class="{{ $section->is_enabled ? 'text-success' : 'text-danger' }}" style="font-weight:700;color:{{ $section->is_enabled ? '#10b981' : '#ef4444' }}">{{ $section->is_enabled ? 'Visible' : 'Hidden' }}</span>
                    </div>
                </div>
            </div>
            <div class="lsm-group-header-right">
                {{-- Master section toggle --}}
                <div class="lsm-master-toggle" onclick="event.stopPropagation()">
                    <span class="lsm-master-label">Section</span>
                    <label class="ts" style="--ic:{{ $gc[0] }}">
                        <input type="checkbox"
                               {{ $section->is_enabled ? 'checked' : '' }}
                               onchange="toggleSlug('{{ $section->slug }}', this)">
                        <span class="ts-sl"></span>
                    </label>
                </div>
                <i class="fas fa-chevron-down lsm-chevron"></i>
            </div>
        </div>

        {{-- Children --}}
        @if($childCount > 0)
        <div class="lsm-children">
            <div class="lsm-children-grid">
                @foreach($section->children_list as $child)
                @php $cc = $itemColors[$child->slug] ?? '#64748b'; @endphp
                <div class="lsm-item {{ $child->is_enabled ? 'active' : 'disabled' }}" id="item-{{ $child->slug }}" style="--ic:{{ $cc }}">
                    <div class="lsm-item-left">
                        <div class="lsm-item-dot" style="background:linear-gradient(135deg,{{ $cc }},{{ $cc }}cc)">
                            <i class="{{ $child->icon ?? 'fas fa-circle' }}"></i>
                        </div>
                        <div>
                            <div class="lsm-item-name">{{ $child->label }}</div>
                            <div class="lsm-item-type">{{ ucfirst($child->type) }}</div>
                        </div>
                    </div>
                    <label class="ts" style="--ic:{{ $cc }}">
                        <input type="checkbox"
                               {{ $child->is_enabled ? 'checked' : '' }}
                               onchange="toggleSlug('{{ $child->slug }}', this)">
                        <span class="ts-sl"></span>
                    </label>
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>
    @endforeach

</div>{{-- /lsm-root --}}

<script>
function toggleGroup(slug) {
    var el = document.getElementById('group-' + slug);
    el.classList.toggle('open');
}

function toggleSlug(slug, checkbox) {
    var url = '/admin/landing-sections/' + slug + '/toggle';
    var token = document.querySelector('meta[name="csrf-token"]');

    // Optimistic UI update
    var item = document.getElementById('item-' + slug);
    if (item) {
        if (checkbox.checked) {
            item.classList.add('active');
            item.classList.remove('disabled');
        } else {
            item.classList.remove('active');
            item.classList.add('disabled');
        }
    }

    // Update "last changed"
    document.getElementById('last-change').textContent = 'just now';

    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': token ? token.content : '',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        showToast(
            (data.is_enabled ? '✅ ' : '🔴 ') + slug + ' ' + (data.is_enabled ? 'enabled' : 'disabled'),
            data.is_enabled ? '#10b981' : '#ef4444'
        );
        // Reload page stats after short delay
        setTimeout(function() { updateStats(); }, 600);
    })
    .catch(function(e) {
        // Revert on error
        checkbox.checked = !checkbox.checked;
        if (item) {
            item.classList.toggle('active');
            item.classList.toggle('disabled');
        }
        showToast('❌ Failed to update — try again', '#ef4444');
    });
}

function updateStats() {
    // Count from DOM
    var allSections = document.querySelectorAll('.lsm-group');
    var enabledSections = document.querySelectorAll('.lsm-group .lsm-master-toggle input:checked');
    var allItems = document.querySelectorAll('.lsm-item');
    var enabledItems = document.querySelectorAll('.lsm-item.active');
    // Update if stat elements exist (simple DOM approach, no AJAX needed)
}

function showToast(msg, color) {
    var t = document.createElement('div');
    t.className = 'lsm-toast';
    t.style.background = color;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function() {
        t.style.opacity = '0';
        t.style.transition = 'opacity .4s';
        setTimeout(function() { t.remove(); }, 400);
    }, 3000);
}

// Auto-open first group
document.addEventListener('DOMContentLoaded', function() {
    var first = document.querySelector('.lsm-group');
    if (first) first.classList.add('open');
});
</script>
@endsection
