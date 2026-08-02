@extends('admin.layouts.app')
@section('title', 'Platform Analytics')

@push('styles')
<style>
:root {
    --brand: #FF8A00;
    --navy: #07003B;
    --surface: #fff;
    --bg: #f1f5f9;
    --border: #e2e8f0;
    --text: #1a1a2e;
    --text-muted: #64748b;
}

.analytics-wrap { padding: 24px; max-width: 1400px; }

/* ── Page Header ── */
.an-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px; gap: 16px; flex-wrap: wrap;
}
.an-title { font-size: 22px; font-weight: 800; color: var(--text); }
.an-subtitle { font-size: 13px; color: var(--text-muted); margin-top: 2px; }
.an-refresh-btn {
    display: flex; align-items: center; gap: 7px;
    background: var(--brand); color: #fff; border: none;
    border-radius: 10px; padding: 9px 18px; font-size: 13px;
    font-weight: 700; cursor: pointer; transition: opacity .2s;
}
.an-refresh-btn:hover { opacity: .88; }

/* ── Section labels ── */
.an-section { margin-bottom: 28px; }
.an-section-title {
    font-size: 13px; font-weight: 800; text-transform: uppercase;
    letter-spacing: .08em; color: var(--text-muted);
    margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
}
.an-section-title::after {
    content: ''; flex: 1; height: 1px; background: var(--border);
}

/* ── Stat grid ── */
.stat-grid-4 { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; }
.stat-grid-3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; }
.stat-grid-2 { display: grid; grid-template-columns: repeat(2,1fr); gap: 14px; }
@media(max-width:1024px){.stat-grid-4{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.stat-grid-4,.stat-grid-3,.stat-grid-2{grid-template-columns:1fr}}

/* ── Stat Card ── */
.stat-card {
    background: var(--surface); border-radius: 16px;
    border: 1.5px solid var(--border); padding: 20px;
    display: flex; flex-direction: column; gap: 4px;
    position: relative; overflow: hidden;
    transition: transform .18s, box-shadow .18s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,0,0,.08); }
.stat-card-icon {
    width: 40px; height: 40px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; margin-bottom: 10px; flex-shrink: 0;
}
.stat-card-val {
    font-size: 28px; font-weight: 900; color: var(--text);
    font-variant-numeric: tabular-nums; line-height: 1;
}
.stat-card-label { font-size: 12px; color: var(--text-muted); font-weight: 600; margin-top: 2px; }
.stat-card-sub { font-size: 11px; color: var(--text-muted); margin-top: 6px; }
.badge-up { color: #16a34a; font-weight: 700; }
.badge-neutral { color: var(--text-muted); font-weight: 600; }

/* ── Chart Card ── */
.chart-card {
    background: var(--surface); border-radius: 16px;
    border: 1.5px solid var(--border); padding: 22px;
}
.chart-card-title { font-size: 14px; font-weight: 800; color: var(--text); margin-bottom: 4px; }
.chart-card-sub { font-size: 12px; color: var(--text-muted); margin-bottom: 18px; }
.chart-container { position: relative; }

/* ── Module Revenue bars ── */
.module-bar-list { display: flex; flex-direction: column; gap: 12px; }
.module-bar-item {}
.module-bar-header { display: flex; justify-content: space-between; margin-bottom: 5px; }
.module-bar-name { font-size: 12px; font-weight: 700; color: var(--text); }
.module-bar-val { font-size: 12px; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.module-bar-track { height: 8px; background: var(--bg); border-radius: 99px; overflow: hidden; }
.module-bar-fill { height: 100%; border-radius: 99px; background: var(--brand); transition: width .6s ease; }

/* ── Funnel ── */
.funnel-list { display: flex; flex-direction: column; gap: 8px; }
.funnel-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 14px; border-radius: 10px;
    background: var(--bg); font-size: 13px;
}
.funnel-status { font-weight: 700; text-transform: capitalize; }
.funnel-count { font-weight: 800; font-variant-numeric: tabular-nums; }
.status-pending  { color: #f59e0b; }
.status-accepted,.status-delivered,.status-approved { color: #16a34a; }
.status-cancelled,.status-rejected { color: #ef4444; }
.status-processing { color: #3b82f6; }

/* ── Top vendors table ── */
.vendor-table { width: 100%; border-collapse: collapse; }
.vendor-table th {
    text-align: left; font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .06em;
    color: var(--text-muted); padding: 0 12px 10px; border-bottom: 1.5px solid var(--border);
}
.vendor-table td { padding: 10px 12px; font-size: 13px; border-bottom: 1px solid var(--border); }
.vendor-table tr:last-child td { border-bottom: none; }
.vendor-rank {
    width: 24px; height: 24px; border-radius: 50%;
    background: var(--bg); display: inline-flex; align-items: center;
    justify-content: center; font-size: 11px; font-weight: 800; color: var(--text-muted);
}
.vendor-rank.gold { background: #fef3c7; color: #d97706; }
.vendor-rank.silver { background: #f1f5f9; color: #64748b; }
.vendor-rank.bronze { background: #fef2ee; color: #c2410c; }
.rev-amount { font-weight: 800; color: var(--text); font-variant-numeric: tabular-nums; }

/* ── Engagement pills ── */
.eng-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; }
.eng-pill {
    background: var(--bg); border-radius: 12px; padding: 14px;
    text-align: center;
}
.eng-pill-val { font-size: 22px; font-weight: 900; color: var(--text); font-variant-numeric: tabular-nums; }
.eng-pill-label { font-size: 11px; color: var(--text-muted); font-weight: 600; margin-top: 3px; text-transform: uppercase; letter-spacing: .05em; }

/* ── Retention circle ── */
.retention-ring {
    display: flex; align-items: center; gap: 24px;
    padding: 20px 0;
}
.ring-wrap { position: relative; width: 120px; height: 120px; flex-shrink: 0; }
.ring-wrap svg { transform: rotate(-90deg); }
.ring-pct {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
    font-size: 22px; font-weight: 900; color: var(--text);
}
.retention-desc { font-size: 13px; color: var(--text-muted); line-height: 1.6; }
.retention-desc strong { color: var(--text); }
</style>
@endpush

@section('content')
<div class="analytics-wrap">

    {{-- Header --}}
    <div class="an-header">
        <div>
            <div class="an-title">📊 Platform Analytics</div>
            <div class="an-subtitle">Real-time business intelligence — updated on page load</div>
        </div>
        <button class="an-refresh-btn" onclick="location.reload()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>

    {{-- ── USER METRICS ── --}}
    <div class="an-section">
        <div class="an-section-title"><i class="fas fa-users"></i> User Metrics</div>
        <div class="stat-grid-4">
            <div class="stat-card">
                <div class="stat-card-icon" style="background:#fff7ed;color:#FF8A00">👥</div>
                <div class="stat-card-val">{{ number_format($totalUsers) }}</div>
                <div class="stat-card-label">Total Registered Users</div>
                <div class="stat-card-sub badge-up">+{{ number_format($newLast30) }} this month</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:#f0fdf4;color:#16a34a">📅</div>
                <div class="stat-card-val">{{ number_format($dau) }}</div>
                <div class="stat-card-label">DAU (Daily Active Users)</div>
                <div class="stat-card-sub badge-neutral">{{ $totalUsers > 0 ? round($dau/$totalUsers*100,1) : 0 }}% of total</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:#eff6ff;color:#3b82f6">📆</div>
                <div class="stat-card-val">{{ number_format($wau) }}</div>
                <div class="stat-card-label">WAU (Weekly Active Users)</div>
                <div class="stat-card-sub badge-neutral">Last 7 days</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:#faf5ff;color:#8b5cf6">🗓️</div>
                <div class="stat-card-val">{{ number_format($mau) }}</div>
                <div class="stat-card-label">MAU (Monthly Active Users)</div>
                <div class="stat-card-sub badge-neutral">Last 30 days</div>
            </div>
        </div>
    </div>

    {{-- ── REVENUE ── --}}
    <div class="an-section">
        <div class="an-section-title"><i class="fas fa-dollar-sign"></i> Revenue</div>
        <div class="stat-grid-4">
            <div class="stat-card">
                <div class="stat-card-icon" style="background:#fff7ed;color:#FF8A00">💵</div>
                <div class="stat-card-val">${{ number_format($revenueToday, 0) }}</div>
                <div class="stat-card-label">Revenue Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:#f0fdf4;color:#16a34a">📈</div>
                <div class="stat-card-val">${{ number_format($revenueMonth, 0) }}</div>
                <div class="stat-card-label">Revenue This Month</div>
                <div class="stat-card-sub badge-neutral">Commission: ${{ number_format($commissionMonth, 0) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:#eff6ff;color:#3b82f6">📊</div>
                <div class="stat-card-val">${{ number_format($revenueWeek, 0) }}</div>
                <div class="stat-card-label">Revenue Last 7 Days</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background:#faf5ff;color:#8b5cf6">🏆</div>
                <div class="stat-card-val">${{ number_format($revenueTotal, 0) }}</div>
                <div class="stat-card-label">Total Revenue (All Time)</div>
                <div class="stat-card-sub badge-up">Commission: ${{ number_format($commissionTotal, 0) }}</div>
            </div>
        </div>
    </div>

    {{-- ── CHARTS ROW 1 ── --}}
    <div class="an-section">
        <div class="an-section-title"><i class="fas fa-chart-line"></i> Trends (Last 30 Days)</div>
        <div class="stat-grid-2">
            {{-- Daily Revenue Chart --}}
            <div class="chart-card">
                <div class="chart-card-title">Daily Revenue</div>
                <div class="chart-card-sub">Orders completed · last 30 days</div>
                <div class="chart-container" style="height:200px">
                    <canvas id="dailyRevenueChart"></canvas>
                </div>
            </div>
            {{-- User Growth Chart --}}
            <div class="chart-card">
                <div class="chart-card-title">New User Signups</div>
                <div class="chart-card-sub">Registrations · last 30 days</div>
                <div class="chart-container" style="height:200px">
                    <canvas id="userGrowthChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- ── MONTHLY REVENUE (12 months) ── --}}
    <div class="an-section">
        <div class="an-section-title"><i class="fas fa-calendar-alt"></i> Monthly Revenue (Last 12 Months)</div>
        <div class="chart-card">
            <div class="chart-card-title">Monthly Revenue & Orders</div>
            <div class="chart-card-sub">Delivered orders only</div>
            <div class="chart-container" style="height:220px">
                <canvas id="monthlyRevenueChart"></canvas>
            </div>
        </div>
    </div>

    {{-- ── MODULE REVENUE + FUNNEL ── --}}
    <div class="an-section">
        <div class="an-section-title"><i class="fas fa-th-large"></i> Module Performance</div>
        <div class="stat-grid-2">
            {{-- Module Revenue --}}
            <div class="chart-card">
                <div class="chart-card-title">Revenue by Module</div>
                <div class="chart-card-sub">All-time delivered orders breakdown</div>
                <div class="module-bar-list" id="moduleBarList">
                    @php
                        $maxRev = $revenueByModule->max('revenue') ?: 1;
                    @endphp
                    @forelse($revenueByModule as $mod)
                    <div class="module-bar-item">
                        <div class="module-bar-header">
                            <span class="module-bar-name">{{ $mod['module'] }}</span>
                            <span class="module-bar-val">${{ number_format($mod['revenue'], 0) }} · {{ number_format($mod['orders']) }} orders</span>
                        </div>
                        <div class="module-bar-track">
                            <div class="module-bar-fill" style="width:{{ round($mod['revenue']/$maxRev*100) }}%"></div>
                        </div>
                    </div>
                    @empty
                    <div style="color:var(--text-muted);font-size:13px;text-align:center;padding:20px">No order data yet</div>
                    @endforelse
                </div>
            </div>

            {{-- Order Funnel --}}
            <div class="chart-card">
                <div class="chart-card-title">Order Status Funnel</div>
                <div class="chart-card-sub">All-time order status distribution</div>
                <div class="funnel-list">
                    @foreach($orderFunnel as $status => $count)
                    <div class="funnel-item">
                        <span class="funnel-status status-{{ $status }}">{{ ucfirst($status) }}</span>
                        <span class="funnel-count">{{ number_format($count) }}</span>
                    </div>
                    @endforeach
                    @if($orderFunnel->isEmpty())
                    <div style="color:var(--text-muted);font-size:13px;text-align:center;padding:20px">No orders yet</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── COMMUNITY ENGAGEMENT ── --}}
    <div class="an-section">
        <div class="an-section-title"><i class="fas fa-heart"></i> Community Engagement</div>
        <div class="chart-card">
            <div class="chart-card-title">eSocial Platform Stats</div>
            <div class="chart-card-sub">All-time community activity</div>
            <div class="eng-grid" style="margin-bottom:20px">
                <div class="eng-pill">
                    <div class="eng-pill-val">{{ number_format($totalPosts) }}</div>
                    <div class="eng-pill-label">Total Posts</div>
                </div>
                <div class="eng-pill">
                    <div class="eng-pill-val">{{ number_format($totalStories) }}</div>
                    <div class="eng-pill-label">Stories</div>
                </div>
                <div class="eng-pill">
                    <div class="eng-pill-val">{{ number_format($totalReactions) }}</div>
                    <div class="eng-pill-label">Reactions</div>
                </div>
                <div class="eng-pill">
                    <div class="eng-pill-val">{{ number_format($totalMessages) }}</div>
                    <div class="eng-pill-label">Messages Sent</div>
                </div>
                <div class="eng-pill">
                    <div class="eng-pill-val">{{ number_format($totalFollows) }}</div>
                    <div class="eng-pill-label">Follows</div>
                </div>
                <div class="eng-pill">
                    <div class="eng-pill-val">{{ number_format($postsThisWeek) }}</div>
                    <div class="eng-pill-label">Posts This Week</div>
                </div>
            </div>
            <div class="chart-container" style="height:160px">
                <canvas id="communityChart"></canvas>
            </div>
        </div>
    </div>

    {{-- ── RETENTION + TOP VENDORS ── --}}
    <div class="an-section">
        <div class="an-section-title"><i class="fas fa-redo"></i> Retention & Top Performers</div>
        <div class="stat-grid-2">

            {{-- Retention --}}
            <div class="chart-card">
                <div class="chart-card-title">Customer Retention Rate</div>
                <div class="chart-card-sub">Users who placed more than one order</div>
                <div class="retention-ring">
                    <div class="ring-wrap">
                        <svg width="120" height="120" viewBox="0 0 120 120">
                            <circle cx="60" cy="60" r="50" fill="none" stroke="#e2e8f0" stroke-width="12"/>
                            <circle cx="60" cy="60" r="50" fill="none" stroke="#FF8A00" stroke-width="12"
                                stroke-dasharray="{{ round($retentionRate * 3.14159) }} 314.159"
                                stroke-linecap="round"/>
                        </svg>
                        <div class="ring-pct">{{ $retentionRate }}%</div>
                    </div>
                    <div class="retention-desc">
                        <strong>{{ number_format($repeatCustomers) }} repeat customers</strong> out of
                        {{ number_format($totalOrderingUsers) }} total ordering users.<br><br>
                        A retention rate above 30% indicates strong product-market fit.
                    </div>
                </div>

                {{-- eMarry + Live mini stats --}}
                <div style="border-top:1px solid var(--border);margin-top:16px;padding-top:16px">
                    <div style="display:flex;gap:16px;flex-wrap:wrap">
                        <div class="eng-pill" style="flex:1;min-width:100px">
                            <div class="eng-pill-val">{{ number_format($emarryProfiles) }}</div>
                            <div class="eng-pill-label">eMarry Profiles</div>
                        </div>
                        <div class="eng-pill" style="flex:1;min-width:100px">
                            <div class="eng-pill-val">{{ number_format($emarryMatches) }}</div>
                            <div class="eng-pill-label">Total Matches</div>
                        </div>
                        <div class="eng-pill" style="flex:1;min-width:100px">
                            <div class="eng-pill-val">{{ number_format($liveRoomsTotal) }}</div>
                            <div class="eng-pill-label">Live Rooms</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Top Vendors --}}
            <div class="chart-card">
                <div class="chart-card-title">Top Vendors by Revenue</div>
                <div class="chart-card-sub">All-time delivered orders</div>
                <table class="vendor-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Vendor</th>
                            <th>Orders</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topVendors as $i => $v)
                        <tr>
                            <td>
                                <span class="vendor-rank {{ $i==0?'gold':($i==1?'silver':($i==2?'bronze':'')) }}">
                                    {{ $i+1 }}
                                </span>
                            </td>
                            <td style="font-weight:700">{{ $v->name }}</td>
                            <td>{{ number_format($v->orders) }}</td>
                            <td class="rev-amount">${{ number_format($v->revenue, 0) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:20px">No vendor data yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const brand  = '#FF8A00';
const brandBg= 'rgba(255,138,0,0.12)';
const blue   = '#3b82f6';
const blueBg = 'rgba(59,130,246,0.12)';
const green  = '#16a34a';
const greenBg= 'rgba(22,163,74,0.12)';

const gridColor = 'rgba(0,0,0,0.05)';
const textColor = '#64748b';

Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
Chart.defaults.font.size   = 11;
Chart.defaults.color       = textColor;

const baseOpts = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
    scales: {
        x: { grid: { color: gridColor }, ticks: { maxTicksLimit: 10 } },
        y: { grid: { color: gridColor }, beginAtZero: true }
    }
};

// ── Daily Revenue ──────────────────────────────────────────────────
const dailyData = @json($dailyRevenue);
new Chart(document.getElementById('dailyRevenueChart'), {
    type: 'line',
    data: {
        labels: dailyData.map(d => d.date.slice(5)),
        datasets: [{
            label: 'Revenue ($)',
            data: dailyData.map(d => d.revenue),
            borderColor: brand, backgroundColor: brandBg,
            borderWidth: 2.5, fill: true, tension: .4, pointRadius: 2
        }]
    },
    options: { ...baseOpts }
});

// ── User Growth ────────────────────────────────────────────────────
const growthData = @json($userGrowth);
new Chart(document.getElementById('userGrowthChart'), {
    type: 'bar',
    data: {
        labels: growthData.map(d => d.date.slice(5)),
        datasets: [{
            label: 'New Users',
            data: growthData.map(d => d.new_users),
            backgroundColor: brandBg, borderColor: brand,
            borderWidth: 1.5, borderRadius: 5
        }]
    },
    options: { ...baseOpts }
});

// ── Monthly Revenue ────────────────────────────────────────────────
const monthlyData = @json($monthlyRevenue);
new Chart(document.getElementById('monthlyRevenueChart'), {
    type: 'bar',
    data: {
        labels: monthlyData.map(d => d.month),
        datasets: [
            {
                label: 'Revenue ($)',
                data: monthlyData.map(d => d.revenue),
                backgroundColor: brand, borderRadius: 6, yAxisID: 'y'
            },
            {
                label: 'Orders',
                data: monthlyData.map(d => d.orders),
                type: 'line',
                borderColor: blue, backgroundColor: blueBg,
                borderWidth: 2, fill: false, tension: .4, pointRadius: 3,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        ...baseOpts,
        plugins: { legend: { display: true, position: 'top' }, tooltip: { mode: 'index', intersect: false } },
        scales: {
            x: { grid: { color: gridColor } },
            y:  { grid: { color: gridColor }, beginAtZero: true, position: 'left' },
            y1: { grid: { display: false }, beginAtZero: true, position: 'right' }
        }
    }
});

// ── Community Posts ────────────────────────────────────────────────
const commData = @json($communityActivity);
new Chart(document.getElementById('communityChart'), {
    type: 'line',
    data: {
        labels: commData.map(d => d.date.slice(5)),
        datasets: [{
            label: 'Posts',
            data: commData.map(d => d.posts),
            borderColor: green, backgroundColor: greenBg,
            borderWidth: 2, fill: true, tension: .4, pointRadius: 2
        }]
    },
    options: { ...baseOpts }
});
</script>
@endpush
