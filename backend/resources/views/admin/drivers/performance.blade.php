@extends('admin.layouts.app')
@section('title', 'Driver Performance')

@push('styles')
<style>
/* ── Driver Performance Enterprise Design ────────────────────── */
:root {
    --dp-diamond: #8B5CF6;
    --dp-gold:    #D97706;
    --dp-silver:  #6B7280;
    --dp-bronze:  #92400E;
    --dp-green:   #34C759;
    --dp-orange:  #FF9500;
    --dp-red:     #FF3B30;
    --dp-blue:    #007AFF;
}

/* Command bar */
.dp-header {
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    margin-bottom: 20px;
}
.dp-header h2 { margin: 0; font-size: 18px; font-weight: 800; }

/* Stats strip */
.dp-stats {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px;
}
.dp-stat {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 12px; padding: 16px 18px; position: relative; overflow: hidden;
    transition: transform .15s;
}
.dp-stat:hover { transform: translateY(-2px); }
.dp-stat::after {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    border-radius: 12px 12px 0 0;
}
.dp-stat.s-total::after   { background: var(--dp-blue); }
.dp-stat.s-online::after  { background: var(--dp-green); }
.dp-stat.s-diamond::after { background: var(--dp-diamond); }
.dp-stat.s-gold::after    { background: var(--dp-gold); }
.dp-stat-val { font-size: 30px; font-weight: 900; line-height: 1; }
.dp-stat-lbl { font-size: 11px; color: var(--text-muted); margin-top: 4px; text-transform: uppercase; letter-spacing: .05em; }
.dp-stat-icon { position: absolute; bottom: 10px; right: 14px; font-size: 24px; opacity: .1; }

/* Filter bar */
.dp-filters {
    display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 20px;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 12px; padding: 12px 16px;
}
.dp-search {
    flex: 1; min-width: 180px; padding: 8px 12px;
    border: 1px solid var(--border); border-radius: 8px;
    background: var(--bg); color: var(--text); font-size: 13px;
    transition: border-color .15s;
}
.dp-search:focus { outline: none; border-color: var(--brand); }
.dp-select {
    padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px;
    background: var(--bg); color: var(--text); font-size: 13px; cursor: pointer;
}
.dp-count { margin-left: auto; font-size: 12px; color: var(--text-muted); font-weight: 600; white-space: nowrap; }

/* Tier filter chips */
.tier-chips { display: flex; gap: 6px; }
.tier-chip {
    padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700;
    border: 1px solid var(--border); background: var(--surface); cursor: pointer;
    transition: all .15s; text-transform: uppercase; letter-spacing: .04em;
}
.tier-chip[data-tier="diamond"].on { background: var(--dp-diamond); color: #fff; border-color: var(--dp-diamond); }
.tier-chip[data-tier="gold"].on    { background: var(--dp-gold);    color: #fff; border-color: var(--dp-gold); }
.tier-chip[data-tier="silver"].on  { background: var(--dp-silver);  color: #fff; border-color: var(--dp-silver); }
.tier-chip[data-tier="bronze"].on  { background: var(--dp-bronze);  color: #fff; border-color: var(--dp-bronze); }
.tier-chip.on:not([data-tier]) { background: var(--brand); color: #fff; border-color: var(--brand); }

/* Drivers grid */
.dp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 14px; }

/* Driver card */
.driver-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 14px; overflow: hidden; position: relative;
    transition: box-shadow .2s, transform .15s;
}
.driver-card:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,0,0,.12); }

.dc-tier-bar { height: 4px; width: 100%; }
.dc-tier-bar.diamond { background: linear-gradient(90deg, #7C3AED, #8B5CF6, #A78BFA); }
.dc-tier-bar.gold    { background: linear-gradient(90deg, #B45309, #D97706, #F59E0B); }
.dc-tier-bar.silver  { background: linear-gradient(90deg, #4B5563, #6B7280, #9CA3AF); }
.dc-tier-bar.bronze  { background: linear-gradient(90deg, #78350F, #92400E, #B45309); }

.dc-body { padding: 16px; }

/* Avatar row */
.dc-avatar-row { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.dc-avatar {
    width: 48px; height: 48px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; font-weight: 800; color: #fff; position: relative;
}
.dc-online-badge {
    position: absolute; bottom: 0; right: 0;
    width: 13px; height: 13px; border-radius: 50%;
    border: 2px solid var(--surface);
}
.dc-name { font-weight: 800; font-size: 15px; line-height: 1.2; }
.dc-phone { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
.dc-tier-lbl {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 800;
    text-transform: uppercase; letter-spacing: .05em; margin-top: 4px;
}

/* Rating */
.dc-rating-row {
    display: flex; align-items: center; gap: 8px; margin-bottom: 14px;
    padding: 8px 12px; border-radius: 8px; background: rgba(0,0,0,.04);
}
.dc-stars { color: #F59E0B; font-size: 13px; letter-spacing: 1px; }
.dc-rating-val { font-size: 18px; font-weight: 900; }
.dc-vehicle { margin-left: auto; font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: capitalize; }

/* Stats grid */
.dc-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 14px; }
.dc-stat-cell {
    text-align: center; padding: 8px 4px;
    background: rgba(0,0,0,.03); border-radius: 8px;
}
.dc-stat-cell .v { font-size: 20px; font-weight: 900; }
.dc-stat-cell .l { font-size: 10px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; margin-top: 2px; }

/* Completion bar */
.dc-completion { margin-bottom: 12px; }
.dc-completion-label { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 4px; }
.dc-bar-bg { background: var(--border); border-radius: 4px; height: 6px; overflow: hidden; }
.dc-bar-fill { height: 6px; border-radius: 4px; transition: width .6s ease; }

/* Earnings row */
.dc-earnings {
    display: flex; justify-content: space-between; align-items: center;
    padding-top: 10px; border-top: 1px solid var(--border); font-size: 12px;
}
.dc-earned-val { font-weight: 800; font-size: 14px; color: var(--brand); }

/* Empty state */
.dp-empty {
    grid-column: 1 / -1; text-align: center; padding: 80px 20px; color: var(--text-muted);
}
.dp-loading {
    grid-column: 1 / -1; text-align: center; padding: 80px 20px; color: var(--text-muted);
}

@media (max-width: 640px) {
    .dp-stats { grid-template-columns: repeat(2,1fr); }
    .dp-filters { flex-direction: column; align-items: stretch; }
    .tier-chips { flex-wrap: wrap; }
    .dp-count { margin-left: 0; }
}
</style>
@endpush

@section('content')

{{-- Header --}}
<div class="dp-header">
    <h2><i class="fas fa-chart-line" style="color:var(--brand);margin-right:8px;"></i>Driver Performance</h2>
    <div style="margin-left:auto;display:flex;gap:8px;">
        <button class="btn btn-primary btn-sm" onclick="loadData()">
            <i class="fas fa-sync-alt" id="refreshIcon"></i> Refresh
        </button>
    </div>
</div>

{{-- Stats strip --}}
<div class="dp-stats">
    <div class="dp-stat s-total">
        <div class="dp-stat-val" id="sTotal" style="color:var(--dp-blue);">—</div>
        <div class="dp-stat-lbl">Total Drivers</div>
        <i class="fas fa-users dp-stat-icon"></i>
    </div>
    <div class="dp-stat s-online">
        <div class="dp-stat-val" id="sOnline" style="color:var(--dp-green);">—</div>
        <div class="dp-stat-lbl">Online Now</div>
        <i class="fas fa-circle dp-stat-icon"></i>
    </div>
    <div class="dp-stat s-diamond">
        <div class="dp-stat-val" id="sDiamond" style="color:var(--dp-diamond);">—</div>
        <div class="dp-stat-lbl">Diamond Tier</div>
        <i class="fas fa-gem dp-stat-icon"></i>
    </div>
    <div class="dp-stat s-gold">
        <div class="dp-stat-val" id="sGold" style="color:var(--dp-gold);">—</div>
        <div class="dp-stat-lbl">Gold Tier</div>
        <i class="fas fa-medal dp-stat-icon"></i>
    </div>
</div>

{{-- Filters --}}
<div class="dp-filters">
    <input id="perfSearch" class="dp-search" type="text" placeholder="🔍  Search name or phone..."
        oninput="debounceLoad()">
    <div class="tier-chips">
        <button class="tier-chip on" data-tier="" onclick="setTier('',this)">All</button>
        <button class="tier-chip" data-tier="diamond" onclick="setTier('diamond',this)">💎 Diamond</button>
        <button class="tier-chip" data-tier="gold"    onclick="setTier('gold',this)">🥇 Gold</button>
        <button class="tier-chip" data-tier="silver"  onclick="setTier('silver',this)">🥈 Silver</button>
        <button class="tier-chip" data-tier="bronze"  onclick="setTier('bronze',this)">🥉 Bronze</button>
    </div>
    <select id="perfStatus" class="dp-select" onchange="loadData()">
        <option value="">All Statuses</option>
        <option value="online">🟢 Online</option>
        <option value="offline">⚫ Offline</option>
    </select>
    <span id="dpCount" class="dp-count"></span>
</div>

{{-- Cards grid --}}
<div class="dp-grid" id="driverGrid">
    <div class="dp-loading">
        <i class="fas fa-spinner fa-spin" style="font-size:28px;margin-bottom:12px;display:block;"></i>
        Loading drivers...
    </div>
</div>

@endsection

@push('scripts')
<script>
(function(){
'use strict';
var DATA_URL = '{{ route("admin.drivers.performance.data") }}';
var currentTier = '';
var debTimer;

var TIER_COLORS = {
    diamond: { bg: 'rgba(139,92,246,.18)', color: '#8B5CF6', icon: '💎' },
    gold:    { bg: 'rgba(217,119,6,.18)',  color: '#D97706', icon: '🥇' },
    silver:  { bg: 'rgba(107,114,128,.18)', color: '#6B7280', icon: '🥈' },
    bronze:  { bg: 'rgba(146,64,14,.15)',  color: '#92400E', icon: '🥉' }
};

var AVATAR_PALETTE = [
    '#6366F1','#8B5CF6','#EC4899','#EF4444','#F59E0B',
    '#10B981','#06B6D4','#3B82F6','#F97316'
];

function avatarColor(name) {
    var h = 0, s = name || 'X';
    for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) & 0xFFFFFF;
    return AVATAR_PALETTE[Math.abs(h) % AVATAR_PALETTE.length];
}

function initials(name) {
    return (name || '?').split(' ').slice(0,2).map(function(w){ return w.charAt(0); }).join('').toUpperCase();
}

function stars(r) {
    var out = '';
    for (var i = 1; i <= 5; i++) {
        out += r >= i ? '★' : r >= i - 0.5 ? '⯨' : '☆';
    }
    return out;
}

function crColor(cr) {
    return cr >= 90 ? 'var(--dp-green)' : cr >= 70 ? 'var(--dp-orange)' : 'var(--dp-red)';
}

function vehicleIcon(v) {
    var icons = { motorcycle:'🏍️', car:'🚗', van:'🚐', truck:'🚛', bicycle:'🚲', bajaj:'🛺', pickup:'🛻' };
    return (icons[v] || '🚗') + ' ' + (v || 'motorcycle');
}

function renderCard(d) {
    var tier   = d.tier || 'bronze';
    var tc     = TIER_COLORS[tier] || TIER_COLORS.bronze;
    var color  = avatarColor(d.name);
    var ini    = initials(d.name);
    var rating = parseFloat(d.avg_rating || 0).toFixed(1);
    var cr     = d.completion_rate || 0;
    var crc    = crColor(cr);
    var isOn   = !!d.is_online;
    var name   = d.name || 'Driver #' + d.id;

    var html = '<div class="driver-card">';
    html += '<div class="dc-tier-bar ' + tier + '"></div>';
    html += '<div class="dc-body">';

    // Avatar row
    html += '<div class="dc-avatar-row">';
    html += '<div class="dc-avatar" style="background:' + color + ';">';
    html += ini;
    html += '<div class="dc-online-badge" style="background:' + (isOn ? 'var(--dp-green)' : '#8E8E93') + ';"></div>';
    html += '</div>';
    html += '<div style="flex:1;min-width:0;">';
    html += '<div class="dc-name" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + name + '</div>';
    html += '<div class="dc-phone">' + (d.phone || '—') + '</div>';
    html += '<span class="dc-tier-lbl" style="background:' + tc.bg + ';color:' + tc.color + ';">' + tc.icon + ' ' + tier.charAt(0).toUpperCase() + tier.slice(1) + '</span>';
    html += '</div>';
    html += '<div style="font-size:10px;color:' + (isOn ? 'var(--dp-green)' : 'var(--text-muted)') + ';font-weight:700;text-align:center;flex-shrink:0;">';
    html += '<div style="font-size:18px;">' + (isOn ? '🟢' : '⚫') + '</div>';
    html += isOn ? 'Online' : 'Offline';
    html += '</div>';
    html += '</div>'; // dc-avatar-row

    // Rating
    html += '<div class="dc-rating-row">';
    html += '<span class="dc-stars">' + stars(parseFloat(rating)) + '</span>';
    html += '<span class="dc-rating-val">' + rating + '</span>';
    html += '<span style="font-size:11px;color:var(--text-muted);">/5.0</span>';
    html += '<span class="dc-vehicle">' + vehicleIcon(d.vehicle_type) + '</span>';
    html += '</div>';

    // Stats
    html += '<div class="dc-stats">';
    html += '<div class="dc-stat-cell"><div class="v">' + (d.total_delivered || 0) + '</div><div class="l">Total</div></div>';
    html += '<div class="dc-stat-cell"><div class="v" style="color:var(--dp-blue);">' + (d.today_orders || 0) + '</div><div class="l">Today</div></div>';
    html += '<div class="dc-stat-cell"><div class="v" style="color:var(--dp-green);">' + (d.week_orders || 0) + '</div><div class="l">Week</div></div>';
    html += '</div>';

    // Completion rate
    html += '<div class="dc-completion">';
    html += '<div class="dc-completion-label">';
    html += '<span style="color:var(--text-muted);font-size:11px;">Completion Rate</span>';
    html += '<span style="color:' + crc + ';font-weight:800;font-size:12px;">' + cr + '%</span>';
    html += '</div>';
    html += '<div class="dc-bar-bg"><div class="dc-bar-fill" style="width:' + cr + '%;background:' + crc + ';"></div></div>';
    html += '</div>';

    // Earnings
    html += '<div class="dc-earnings">';
    html += '<span style="color:var(--text-muted);">Today earned</span>';
    html += '<span class="dc-earned-val">$' + parseFloat(d.today_earned || 0).toFixed(2) + '</span>';
    html += '</div>';

    html += '</div>'; // dc-body
    html += '</div>'; // driver-card
    return html;
}

window.loadData = function() {
    var search = (document.getElementById('perfSearch') || {}).value || '';
    var status = (document.getElementById('perfStatus') || {}).value || '';
    var url    = DATA_URL + '?search=' + encodeURIComponent(search)
               + '&tier='   + encodeURIComponent(currentTier)
               + '&status=' + encodeURIComponent(status);
    var grid   = document.getElementById('driverGrid');
    var icon   = document.getElementById('refreshIcon');
    if (icon) { icon.className = 'fas fa-spinner fa-spin'; }

    fetch(url, { credentials: 'same-origin' })
        .then(function(r){
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function(res){
            if (icon) icon.className = 'fas fa-sync-alt';
            var data = res.data || [];
            var s    = res.summary || {};
            // Update stats
            var el;
            el = document.getElementById('sTotal');   if (el) el.textContent = s.total   || 0;
            el = document.getElementById('sOnline');  if (el) el.textContent = s.online  || 0;
            el = document.getElementById('sDiamond'); if (el) el.textContent = s.diamond || 0;
            el = document.getElementById('sGold');    if (el) el.textContent = s.gold    || 0;
            el = document.getElementById('dpCount');
            if (el) el.textContent = data.length + ' driver' + (data.length !== 1 ? 's' : '');

            if (!data.length) {
                grid.innerHTML =
                    '<div class="dp-empty">' +
                    '<i class="fas fa-users" style="font-size:48px;opacity:.2;display:block;margin-bottom:16px;"></i>' +
                    '<div style="font-size:16px;font-weight:700;">No drivers found</div>' +
                    '<div style="font-size:13px;margin-top:4px;opacity:.6;">Try adjusting your filters.</div>' +
                    '</div>';
            } else {
                var html = '';
                for (var i = 0; i < data.length; i++) html += renderCard(data[i]);
                grid.innerHTML = html;
            }
        })
        .catch(function(err){
            if (icon) icon.className = 'fas fa-sync-alt';
            grid.innerHTML =
                '<div class="dp-empty">' +
                '<i class="fas fa-exclamation-circle" style="font-size:40px;opacity:.4;display:block;margin-bottom:12px;color:var(--dp-red);"></i>' +
                '<div style="font-size:15px;font-weight:700;color:var(--dp-red);">Failed to load</div>' +
                '<div style="font-size:13px;margin-top:4px;opacity:.7;">' + err.message + '</div>' +
                '<button onclick="loadData()" class="btn btn-primary btn-sm" style="margin-top:16px;">Retry</button>' +
                '</div>';
        });
};

window.setTier = function(tier, el) {
    currentTier = tier;
    document.querySelectorAll('.tier-chip').forEach(function(c){ c.classList.remove('on'); });
    el.classList.add('on');
    loadData();
};

window.debounceLoad = function() {
    clearTimeout(debTimer);
    debTimer = setTimeout(loadData, 350);
};

// Auto-load on ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadData);
} else {
    loadData();
}

})();
</script>
@endpush
