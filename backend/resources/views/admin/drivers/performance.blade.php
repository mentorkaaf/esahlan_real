@extends('admin.layouts.app')
@section('title', 'Driver Performance')

@push('css')
<style>
.perf-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
.driver-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    position: relative;
    overflow: hidden;
    transition: box-shadow .2s;
}
.driver-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.1); }
.driver-card .tier-stripe {
    position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
}
.driver-card .avatar {
    width: 46px; height: 46px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; font-weight: 800; flex-shrink: 0;
    border: 2px solid var(--border);
    object-fit: cover;
}
.tier-diamond .tier-stripe { background: linear-gradient(180deg, #8B5CF6, #6D28D9); }
.tier-gold    .tier-stripe { background: linear-gradient(180deg, #F59E0B, #D97706); }
.tier-silver  .tier-stripe { background: linear-gradient(180deg, #9CA3AF, #6B7280); }
.tier-bronze  .tier-stripe { background: linear-gradient(180deg, #92400E, #78350F); }

.tier-badge-card {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 20px;
    font-size: 10px; font-weight: 700; text-transform: uppercase;
}
.tb-diamond { background: rgba(139,92,246,.15); color: #8B5CF6; }
.tb-gold    { background: rgba(245,158,11,.15); color: #D97706; }
.tb-silver  { background: rgba(156,163,175,.2); color: #6B7280; }
.tb-bronze  { background: rgba(146,64,14,.1); color: #92400E; }

.online-dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
}
.online-dot.on  { background: #34C759; box-shadow: 0 0 0 2px rgba(52,199,89,.2); }
.online-dot.off { background: #8E8E93; }

.stat-mini { text-align: center; }
.stat-mini .val { font-size: 18px; font-weight: 800; }
.stat-mini .lbl { font-size: 10px; color: var(--text-muted); margin-top: 2px; }

.star-row { color: #F59E0B; font-size: 12px; }
.progress-bar-wrap { background: var(--border); border-radius: 4px; height: 5px; margin-top: 4px; }
.progress-bar-fill { height: 5px; border-radius: 4px; background: var(--brand); }

.summary-strip {
    display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; margin-bottom: 20px;
}
.sbox {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 10px; padding: 14px 18px; text-align: center;
}
.sbox .val { font-size: 26px; font-weight: 800; }
.sbox .lbl { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

.filter-row {
    display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 18px;
}
.filter-row input, .filter-row select {
    padding: 7px 12px; border: 1px solid var(--border);
    border-radius: 8px; background: var(--surface); color: var(--text);
    font-size: 13px;
}
</style>
@endpush

@section('content')

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-chart-line" style="color:var(--primary)"></i> Driver Performance</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">Driver Performance</li>
        </ol>
    </div>
    <div>
        <button class="btn btn-primary btn-sm" onclick="loadData()"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>
</div>

{{-- Summary Strip --}}
<div class="summary-strip">
    <div class="sbox">
        <div class="val" id="sTotal">—</div>
        <div class="lbl">Total Drivers</div>
    </div>
    <div class="sbox">
        <div class="val" id="sOnline" style="color:#34C759">—</div>
        <div class="lbl">Online Now</div>
    </div>
    <div class="sbox">
        <div class="val" id="sDiamond" style="color:#8B5CF6">—</div>
        <div class="lbl">Diamond Tier</div>
    </div>
    <div class="sbox">
        <div class="val" id="sGold" style="color:#D97706">—</div>
        <div class="lbl">Gold Tier</div>
    </div>
</div>

{{-- Filters --}}
<div class="filter-row">
    <input id="searchInput" type="text" placeholder="Search name or phone..." oninput="loadData()" style="min-width:200px;">
    <select id="tierFilter" onchange="loadData()">
        <option value="">All Tiers</option>
        <option value="diamond">💎 Diamond</option>
        <option value="gold">🥇 Gold</option>
        <option value="silver">🥈 Silver</option>
        <option value="bronze">🥉 Bronze</option>
    </select>
    <select id="statusFilter" onchange="loadData()">
        <option value="">All Statuses</option>
        <option value="online">Online</option>
        <option value="offline">Offline</option>
    </select>
    <span id="resultCount" style="font-size:12px;color:var(--text-muted);margin-left:auto;"></span>
</div>

{{-- Cards Grid --}}
<div class="perf-grid" id="driverGrid">
    <div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text-muted);">
        <i class="fas fa-spinner fa-spin" style="font-size:28px;"></i>
        <div style="margin-top:10px;">Loading driver data...</div>
    </div>
</div>
@endsection

@push('js')
<script>
const TIER_ICONS = { diamond: '💎', gold: '🥇', silver: '🥈', bronze: '🥉' };
const TIER_COLORS = {
    diamond: ['tb-diamond','#8B5CF6'],
    gold: ['tb-gold','#D97706'],
    silver: ['tb-silver','#6B7280'],
    bronze: ['tb-bronze','#92400E'],
};

function renderStars(rating) {
    let s = '';
    for (let i = 1; i <= 5; i++) {
        if (rating >= i) s += '★';
        else if (rating >= i - 0.5) s += '½';
        else s += '☆';
    }
    return s;
}

function avatarInitials(name) {
    if (!name) return '?';
    return name.split(' ').slice(0,2).map(w => w[0]).join('').toUpperCase();
}

function avatarColor(name) {
    const colors = ['#FF6B6B','#4ECDC4','#45B7D1','#96CEB4','#FFEAA7','#DDA0DD','#98D8C8'];
    let h = 0;
    for (let c of (name||'X')) h = (h*31 + c.charCodeAt(0)) & 0xFFFFFF;
    return colors[Math.abs(h) % colors.length];
}

function renderCard(d) {
    const [tbClass, tierColor] = TIER_COLORS[d.tier] || TIER_COLORS.bronze;
    const starHtml = renderStars(d.avg_rating);
    const initials = avatarInitials(d.name);
    const color = avatarColor(d.name);
    const completionColor = d.completion_rate >= 90 ? '#34C759' : d.completion_rate >= 70 ? '#FF9500' : '#FF3B30';

    return `<div class="driver-card tier-${d.tier}">
        <div class="tier-stripe"></div>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;padding-left:8px;">
            <div class="avatar" style="background:${color};color:#fff;">${initials}</div>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:700;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${d.name}</div>
                <div style="font-size:11px;color:var(--text-muted);">${d.phone || '—'}</div>
                <div style="display:flex;align-items:center;gap:6px;margin-top:3px;">
                    <div class="online-dot ${d.is_online ? 'on' : 'off'}"></div>
                    <span style="font-size:10px;color:var(--text-muted);">${d.is_online ? 'Online' : 'Offline'}</span>
                    <span class="tier-badge-card ${tbClass}">${TIER_ICONS[d.tier]} ${d.tier}</span>
                </div>
            </div>
        </div>

        {{-- Rating --}}
        <div style="display:flex;align-items:center;gap:8px;padding-left:8px;margin-bottom:12px;">
            <span class="star-row">${starHtml}</span>
            <span style="font-size:13px;font-weight:700;">${d.avg_rating.toFixed(1)}</span>
        </div>

        {{-- Stats Row --}}
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;padding-left:8px;">
            <div class="stat-mini">
                <div class="val">${d.total_delivered}</div>
                <div class="lbl">Total</div>
            </div>
            <div class="stat-mini">
                <div class="val" style="color:#007AFF;">${d.today_orders}</div>
                <div class="lbl">Today</div>
            </div>
            <div class="stat-mini">
                <div class="val" style="color:#34C759;">${d.week_orders}</div>
                <div class="lbl">Week</div>
            </div>
        </div>

        {{-- Completion Rate --}}
        <div style="padding-left:8px;">
            <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:2px;">
                <span style="color:var(--text-muted);">Completion Rate</span>
                <span style="color:${completionColor};font-weight:700;">${d.completion_rate}%</span>
            </div>
            <div class="progress-bar-wrap">
                <div class="progress-bar-fill" style="width:${d.completion_rate}%;background:${completionColor};"></div>
            </div>
        </div>

        {{-- Earnings --}}
        <div style="display:flex;justify-content:space-between;margin-top:10px;padding-left:8px;font-size:12px;">
            <span style="color:var(--text-muted);">Today earned</span>
            <span style="font-weight:700;color:var(--brand);">${d.today_earned.toLocaleString()} USD</span>
        </div>
    </div>`;
}

function loadData() {
    const search = document.getElementById('searchInput').value;
    const tier   = document.getElementById('tierFilter').value;
    const status = document.getElementById('statusFilter').value;
    const url = '{{ route("admin.drivers.performance.data") }}?search=' + encodeURIComponent(search) + '&tier=' + tier + '&status=' + status;

    fetch(url)
        .then(r => r.json())
        .then(res => {
            const grid = document.getElementById('driverGrid');
            if (!res.data || res.data.length === 0) {
                grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text-muted);"><i class="fas fa-users" style="font-size:40px;margin-bottom:12px;display:block;opacity:.3;"></i><div>No drivers found.</div></div>';
                return;
            }
            grid.innerHTML = res.data.map(renderCard).join('');
            document.getElementById('resultCount').textContent = res.data.length + ' drivers';
            document.getElementById('sTotal').textContent   = res.summary.total;
            document.getElementById('sOnline').textContent  = res.summary.online;
            document.getElementById('sDiamond').textContent = res.summary.diamond;
            document.getElementById('sGold').textContent    = res.summary.gold;
        })
        .catch(() => {
            document.getElementById('driverGrid').innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted);">Error loading data. <a href="#" onclick="loadData()">Retry</a></div>';
        });
}

loadData();
</script>
@endpush
