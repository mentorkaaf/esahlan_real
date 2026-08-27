@extends('admin.layouts.app')
@section('title', 'Driver Performance')

@push('css')
<style>
.perf-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:14px; }
.driver-card {
    background:var(--surface); border:1px solid var(--border);
    border-radius:12px; padding:16px; position:relative;
    overflow:hidden; transition:box-shadow .2s;
}
.driver-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.12); }
.tier-stripe { position:absolute; left:0; top:0; bottom:0; width:4px; }
.tier-diamond .tier-stripe { background:linear-gradient(180deg,#8B5CF6,#6D28D9); }
.tier-gold    .tier-stripe { background:linear-gradient(180deg,#F59E0B,#D97706); }
.tier-silver  .tier-stripe { background:linear-gradient(180deg,#9CA3AF,#6B7280); }
.tier-bronze  .tier-stripe { background:linear-gradient(180deg,#92400E,#78350F); }
.d-avatar {
    width:46px; height:46px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:18px; font-weight:800; flex-shrink:0;
    border:2px solid var(--border);
}
.online-dot { width:8px; height:8px; border-radius:50%; }
.progress-wrap { background:var(--border); border-radius:4px; height:5px; margin-top:4px; }
.progress-fill { height:5px; border-radius:4px; }
.tier-lbl {
    display:inline-flex; align-items:center; gap:4px;
    padding:2px 8px; border-radius:20px;
    font-size:10px; font-weight:700; text-transform:uppercase;
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

{{-- Stats Strip --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 18px;text-align:center;">
        <div id="sTotal" style="font-size:26px;font-weight:800;">—</div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">Total Drivers</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 18px;text-align:center;">
        <div id="sOnline" style="font-size:26px;font-weight:800;color:#34C759;">—</div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">Online Now</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 18px;text-align:center;">
        <div id="sDiamond" style="font-size:26px;font-weight:800;color:#8B5CF6;">—</div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">Diamond Tier</div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 18px;text-align:center;">
        <div id="sGold" style="font-size:26px;font-weight:800;color:#D97706;">—</div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">Gold Tier</div>
    </div>
</div>

{{-- Filters --}}
<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:18px;">
    <input id="perfSearch" type="text" placeholder="Search name or phone..."
        oninput="loadData()"
        style="min-width:200px;padding:7px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-size:13px;">
    <select id="perfTier" onchange="loadData()"
        style="padding:7px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-size:13px;">
        <option value="">All Tiers</option>
        <option value="diamond">Diamond</option>
        <option value="gold">Gold</option>
        <option value="silver">Silver</option>
        <option value="bronze">Bronze</option>
    </select>
    <select id="perfStatus" onchange="loadData()"
        style="padding:7px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-size:13px;">
        <option value="">All Statuses</option>
        <option value="online">Online</option>
        <option value="offline">Offline</option>
    </select>
    <span id="perfCount" style="font-size:12px;color:var(--text-muted);margin-left:auto;"></span>
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
(function() {
    var DATA_URL = '{{ route("admin.drivers.performance.data") }}';

    var TIER_COLORS = {
        diamond: { bg: 'rgba(139,92,246,.15)', color: '#8B5CF6', icon: 'gem' },
        gold:    { bg: 'rgba(245,158,11,.15)', color: '#D97706', icon: 'medal' },
        silver:  { bg: 'rgba(156,163,175,.2)', color: '#6B7280', icon: 'award' },
        bronze:  { bg: 'rgba(146,64,14,.1)',   color: '#92400E', icon: 'award' }
    };
    var TIER_LABELS = { diamond: 'Diamond', gold: 'Gold', silver: 'Silver', bronze: 'Bronze' };
    var AVATAR_COLORS = ['#FF6B6B','#4ECDC4','#45B7D1','#96CEB4','#DDA0DD','#98D8C8','#FFB347'];

    function avatarColor(name) {
        var h = 0;
        for (var i = 0; i < (name || 'X').length; i++) h = (h * 31 + (name || 'X').charCodeAt(i)) & 0xFFFFFF;
        return AVATAR_COLORS[Math.abs(h) % AVATAR_COLORS.length];
    }

    function initials(name) {
        if (!name) return '?';
        return (name || '').split(' ').slice(0,2).map(function(w){ return w.charAt(0); }).join('').toUpperCase();
    }

    function stars(r) {
        var s = '';
        for (var i = 1; i <= 5; i++) {
            s += (r >= i) ? '★' : (r >= i - 0.5) ? '½' : '☆';
        }
        return s;
    }

    function renderCard(d) {
        var tier    = d.tier || 'bronze';
        var tc      = TIER_COLORS[tier] || TIER_COLORS.bronze;
        var bg      = avatarColor(d.name);
        var ini     = initials(d.name);
        var strs    = stars(d.avg_rating || 0);
        var rating  = (d.avg_rating || 0).toFixed(1);
        var cr      = d.completion_rate || 0;
        var crColor = cr >= 90 ? '#34C759' : cr >= 70 ? '#FF9500' : '#FF3B30';
        var isOn    = d.is_online ? true : false;
        var name    = d.name || 'Driver #' + d.id;
        var phone   = d.phone || '—';
        var today   = d.today_orders || 0;
        var week    = d.week_orders || 0;
        var total   = d.total_delivered || 0;
        var earned  = (d.today_earned || 0).toFixed(2);

        var html = '<div class="driver-card tier-' + tier + '">';
        html += '<div class="tier-stripe"></div>';

        // Header
        html += '<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;padding-left:8px;">';
        html += '<div class="d-avatar" style="background:' + bg + ';color:#fff;">' + ini + '</div>';
        html += '<div style="flex:1;min-width:0;">';
        html += '<div style="font-weight:700;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + name + '</div>';
        html += '<div style="font-size:11px;color:var(--text-muted);">' + phone + '</div>';
        html += '<div style="display:flex;align-items:center;gap:6px;margin-top:3px;">';
        html += '<div class="online-dot" style="background:' + (isOn ? '#34C759' : '#8E8E93') + ';' + (isOn ? 'box-shadow:0 0 0 2px rgba(52,199,89,.2);' : '') + '"></div>';
        html += '<span style="font-size:10px;color:var(--text-muted);">' + (isOn ? 'Online' : 'Offline') + '</span>';
        html += '<span class="tier-lbl" style="background:' + tc.bg + ';color:' + tc.color + ';">' + TIER_LABELS[tier] + '</span>';
        html += '</div></div></div>';

        // Stars
        html += '<div style="display:flex;align-items:center;gap:8px;padding-left:8px;margin-bottom:12px;">';
        html += '<span style="color:#F59E0B;font-size:12px;">' + strs + '</span>';
        html += '<span style="font-size:13px;font-weight:700;">' + rating + '</span>';
        html += '</div>';

        // Stats 3 cols
        html += '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;padding-left:8px;">';
        html += '<div style="text-align:center;"><div style="font-size:18px;font-weight:800;">' + total + '</div><div style="font-size:10px;color:var(--text-muted);">Total</div></div>';
        html += '<div style="text-align:center;"><div style="font-size:18px;font-weight:800;color:#007AFF;">' + today + '</div><div style="font-size:10px;color:var(--text-muted);">Today</div></div>';
        html += '<div style="text-align:center;"><div style="font-size:18px;font-weight:800;color:#34C759;">' + week + '</div><div style="font-size:10px;color:var(--text-muted);">Week</div></div>';
        html += '</div>';

        // Completion rate
        html += '<div style="padding-left:8px;">';
        html += '<div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:2px;">';
        html += '<span style="color:var(--text-muted);">Completion Rate</span>';
        html += '<span style="color:' + crColor + ';font-weight:700;">' + cr + '%</span></div>';
        html += '<div class="progress-wrap"><div class="progress-fill" style="width:' + cr + '%;background:' + crColor + ';"></div></div>';
        html += '</div>';

        // Earnings
        html += '<div style="display:flex;justify-content:space-between;margin-top:10px;padding-left:8px;font-size:12px;">';
        html += '<span style="color:var(--text-muted);">Today earned</span>';
        html += '<span style="font-weight:700;color:var(--brand);">$' + earned + '</span>';
        html += '</div>';

        html += '</div>';
        return html;
    }

    window.loadData = function() {
        var search = (document.getElementById('perfSearch') || {}).value || '';
        var tier   = (document.getElementById('perfTier')   || {}).value || '';
        var status = (document.getElementById('perfStatus') || {}).value || '';
        var url    = DATA_URL + '?search=' + encodeURIComponent(search) + '&tier=' + encodeURIComponent(tier) + '&status=' + encodeURIComponent(status);
        var grid   = document.getElementById('driverGrid');

        fetch(url, { credentials: 'same-origin' })
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function(res) {
                var data = res.data || [];
                if (!data.length) {
                    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text-muted);"><i class="fas fa-users" style="font-size:40px;margin-bottom:12px;display:block;opacity:.3;"></i><div>No drivers found.</div></div>';
                } else {
                    var html = '';
                    for (var i = 0; i < data.length; i++) html += renderCard(data[i]);
                    grid.innerHTML = html;
                }
                document.getElementById('perfCount').textContent = data.length + ' drivers';
                var s = res.summary || {};
                document.getElementById('sTotal').textContent   = s.total   || 0;
                document.getElementById('sOnline').textContent  = s.online  || 0;
                document.getElementById('sDiamond').textContent = s.diamond || 0;
                document.getElementById('sGold').textContent    = s.gold    || 0;
            })
            .catch(function(err) {
                grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted);">Error: ' + err.message + '. <a href="#" onclick="loadData();return false;">Retry</a></div>';
            });
    };

    // Auto-load on page ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.loadData);
    } else {
        window.loadData();
    }
})();
</script>
@endpush
