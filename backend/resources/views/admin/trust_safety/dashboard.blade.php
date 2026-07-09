@extends('admin.layouts.app')
@section('title', 'Trust & Safety Dashboard')

@section('content')
<style>
.ts-kpi { background:#fff; border-radius:14px; padding:20px 22px; border:1px solid #eef0f6; display:flex; align-items:center; gap:16px; }
.ts-kpi-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.ts-kpi-val { font-size:26px; font-weight:800; color:#111827; line-height:1; }
.ts-kpi-label { font-size:12px; color:#6b7280; font-weight:600; margin-bottom:4px; }
.ts-kpi-delta { font-size:11px; font-weight:700; margin-top:4px; }
.ts-kpi-delta.up { color:#10b981; } .ts-kpi-delta.down { color:#ef4444; }
.ts-card { background:#fff; border-radius:14px; border:1px solid #eef0f6; padding:20px 22px; }
.ts-card-title { font-size:15px; font-weight:800; color:#111827; margin-bottom:16px; display:flex; align-items:center; justify-content:space-between; }
.ts-card-title a { font-size:12px; font-weight:600; color:#FF8A00; text-decoration:none; }
.badge-risk { display:inline-block; padding:2px 8px; border-radius:20px; font-size:10px; font-weight:800; }
.badge-high { background:#fee2e2; color:#dc2626; }
.badge-medium { background:#fef3c7; color:#d97706; }
.badge-low { background:#dcfce7; color:#16a34a; }
.badge-critical { background:#7f1d1d; color:#fff; }
.queue-item { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid #f3f4f6; }
.queue-item:last-child { border-bottom:none; }
.queue-thumb { width:52px; height:52px; border-radius:8px; object-fit:cover; background:#f3f4f6; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:18px; flex-shrink:0; overflow:hidden; }
.score-bar { height:6px; border-radius:3px; background:#f3f4f6; margin-top:4px; }
.score-fill { height:100%; border-radius:3px; }
.risk-alert { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid #f3f4f6; }
.risk-alert:last-child { border-bottom:none; }
.risk-cnt { font-size:13px; font-weight:800; color:#111827; min-width:36px; text-align:right; }
.mod-row td { padding:9px 8px; font-size:13px; border-bottom:1px solid #f3f4f6; }
.mod-row:last-child td { border-bottom:none; }
.trust-score-num { font-size:56px; font-weight:900; color:#111827; line-height:1; }
.sub-score { text-align:center; }
.sub-score-val { font-size:22px; font-weight:800; color:#111827; }
.sub-score-label { font-size:11px; color:#6b7280; font-weight:600; margin-bottom:6px; }
.sub-score-bar { height:5px; border-radius:3px; background:#f3f4f6; margin-top:6px; }
</style>

<div class="page-header" style="margin-bottom:24px;">
    <div>
        <h1 style="font-size:22px;font-weight:900;color:#111827;">Trust & Safety Dashboard</h1>
        <p style="font-size:13px;color:#6b7280;margin-top:2px;">Overview of platform safety and moderation</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <span style="font-size:12px;color:#6b7280;">Last 7 days</span>
        <button onclick="location.reload()" style="background:#fff;border:1.5px solid #eef0f6;border-radius:9px;padding:7px 14px;font-size:12px;font-weight:700;color:#374151;cursor:pointer;">
            <i class="fas fa-sync-alt" style="margin-right:5px;"></i>Refresh
        </button>
    </div>
</div>

{{-- KPI Cards --}}
<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:14px;margin-bottom:24px;">

    <div class="ts-kpi">
        <div class="ts-kpi-icon" style="background:#eff6ff;color:#3b82f6;"><i class="fas fa-flag"></i></div>
        <div>
            <div class="ts-kpi-label">Total Reports</div>
            <div class="ts-kpi-val">{{ number_format($totalReports) }}</div>
            <div class="ts-kpi-delta {{ $reportsDelta >= 0 ? 'up' : 'down' }}">
                {{ $reportsDelta >= 0 ? '↑' : '↓' }} {{ abs($reportsDelta) }}% vs last 7 days
            </div>
        </div>
    </div>

    <div class="ts-kpi">
        <div class="ts-kpi-icon" style="background:#fff7ed;color:#f97316;"><i class="fas fa-hourglass-half"></i></div>
        <div>
            <div class="ts-kpi-label">Pending Review</div>
            <div class="ts-kpi-val">{{ number_format($pendingReview) }}</div>
            <div class="ts-kpi-delta {{ $pendingDelta >= 0 ? 'up' : 'down' }}">
                {{ $pendingDelta >= 0 ? '↑' : '↓' }} {{ abs($pendingDelta) }}% Requires attention
            </div>
        </div>
    </div>

    <div class="ts-kpi">
        <div class="ts-kpi-icon" style="background:#fef2f2;color:#ef4444;"><i class="fas fa-triangle-exclamation"></i></div>
        <div>
            <div class="ts-kpi-label">High Risk Content</div>
            <div class="ts-kpi-val">{{ number_format($highRisk) }}</div>
            <div class="ts-kpi-delta {{ $highRiskDelta <= 0 ? 'up' : 'down' }}">
                {{ $highRiskDelta <= 0 ? '↓' : '↑' }} {{ abs($highRiskDelta) }}% High priority
            </div>
        </div>
    </div>

    <div class="ts-kpi">
        <div class="ts-kpi-icon" style="background:#f0fdf4;color:#22c55e;"><i class="fas fa-robot"></i></div>
        <div>
            <div class="ts-kpi-label">Auto Removed</div>
            <div class="ts-kpi-val">{{ number_format($autoRemoved) }}</div>
            <div class="ts-kpi-delta {{ $autoRemovedDelta >= 0 ? 'up' : 'down' }}">
                {{ $autoRemovedDelta >= 0 ? '↑' : '↓' }} {{ abs($autoRemovedDelta) }}% By AI system
            </div>
        </div>
    </div>

    <div class="ts-kpi">
        <div class="ts-kpi-icon" style="background:#faf5ff;color:#a855f7;"><i class="fas fa-scale-balanced"></i></div>
        <div>
            <div class="ts-kpi-label">Appeals Pending</div>
            <div class="ts-kpi-val">{{ number_format($appealsPending) }}</div>
            <div class="ts-kpi-delta {{ $appealsDelta <= 0 ? 'up' : 'down' }}">
                {{ $appealsDelta <= 0 ? '↓' : '↑' }} {{ abs($appealsDelta) }}% Waiting review
            </div>
        </div>
    </div>

    <div class="ts-kpi">
        <div class="ts-kpi-icon" style="background:#f0fdfa;color:#14b8a6;"><i class="fas fa-users"></i></div>
        <div>
            <div class="ts-kpi-label">Online Moderators</div>
            <div class="ts-kpi-val">{{ \Illuminate\Support\Facades\DB::table('ts_moderator_actions')->where('created_at','>=',now()->subHour())->distinct('moderator_id')->count() }}</div>
            <div class="ts-kpi-delta up"><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#22c55e;margin-right:3px;"></span>Active now</div>
        </div>
    </div>

    <div class="ts-kpi">
        <div class="ts-kpi-icon" style="background:#eff6ff;color:#6366f1;"><i class="fas fa-brain"></i></div>
        <div>
            <div class="ts-kpi-label">AI Accuracy</div>
            <div class="ts-kpi-val">{{ $aiAccuracy > 0 ? $aiAccuracy : '—' }}{{ $aiAccuracy > 0 ? '%' : '' }}</div>
            <div class="ts-kpi-delta up">This week</div>
        </div>
    </div>

</div>

{{-- Row 2: Chart + Violations + Queue --}}
<div style="display:grid;grid-template-columns:1fr 1fr 340px;gap:16px;margin-bottom:16px;">

    {{-- Reports Overview Chart --}}
    <div class="ts-card">
        <div class="ts-card-title">
            Reports Overview
            <a href="{{ route('admin.trust-safety.reports') }}">View all →</a>
        </div>
        <canvas id="reportsChart" height="160"></canvas>
    </div>

    {{-- Violations by Category --}}
    <div class="ts-card">
        <div class="ts-card-title">
            Violations by Category
            <a href="{{ route('admin.trust-safety.reports') }}">View all categories →</a>
        </div>
        <div style="display:flex;gap:20px;align-items:center;">
            <div style="position:relative;flex-shrink:0;">
                <canvas id="catChart" width="160" height="160"></canvas>
                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
                    <div style="font-size:20px;font-weight:900;color:#111827;">{{ number_format($totalCatTotal) }}</div>
                    <div style="font-size:10px;color:#6b7280;font-weight:600;">Total</div>
                </div>
            </div>
            <div style="flex:1;">
                @php
                $catColors = ['#6366f1','#f97316','#ef4444','#3b82f6','#a855f7','#f59e0b','#14b8a6','#64748b'];
                @endphp
                @foreach($byCategory as $i => $cat)
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:7px;">
                    <span style="width:10px;height:10px;border-radius:50%;background:{{ $catColors[$i % count($catColors)] }};flex-shrink:0;"></span>
                    <span style="font-size:12px;color:#374151;flex:1;">{{ $cat['label'] }}</span>
                    <span style="font-size:12px;font-weight:700;color:#111827;">{{ number_format($cat['value']) }}</span>
                    <span style="font-size:11px;color:#9ca3af;">({{ $cat['pct'] }}%)</span>
                </div>
                @endforeach
                @if(empty($byCategory))
                <div style="color:#9ca3af;font-size:13px;text-align:center;padding:20px 0;">No reports yet</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Moderation Queue --}}
    <div class="ts-card">
        <div class="ts-card-title">
            Moderation Queue
            <a href="{{ route('admin.trust-safety.queue') }}">View all</a>
        </div>
        @forelse($queue as $item)
        <div class="queue-item">
            <div class="queue-thumb">
                @if($item->media_url)
                    <img src="{{ $item->media_url }}" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">
                @else
                    <i class="fas fa-file-alt"></i>
                @endif
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:12px;font-weight:700;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    {{ Str::limit($item->content ?? 'Media post', 30) }}
                </div>
                <div style="font-size:11px;color:#6b7280;">By: {{ $item->author }}</div>
                @php $sc = $item->moderation_score ?? 0; $risk = $sc >= 0.9 ? 'critical' : ($sc >= 0.7 ? 'high' : ($sc >= 0.4 ? 'medium' : 'low')); @endphp
                <span class="badge-risk badge-{{ $risk }}" style="margin-top:3px;">
                    {{ ucfirst($risk) }} Risk
                </span>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;flex-shrink:0;">
                <form method="POST" action="{{ route('admin.trust-safety.queue.moderate', $item->id) }}">
                    @csrf
                    <input type="hidden" name="action" value="approve">
                    <button style="background:#dcfce7;color:#16a34a;border:none;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700;cursor:pointer;">✓</button>
                </form>
                <form method="POST" action="{{ route('admin.trust-safety.queue.moderate', $item->id) }}">
                    @csrf
                    <input type="hidden" name="action" value="reject">
                    <button style="background:#fee2e2;color:#dc2626;border:none;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700;cursor:pointer;">✕</button>
                </form>
            </div>
        </div>
        @empty
        <div style="text-align:center;padding:30px 0;color:#9ca3af;">
            <i class="fas fa-check-circle" style="font-size:28px;color:#22c55e;margin-bottom:8px;display:block;"></i>
            Queue is clear!
        </div>
        @endforelse
        @if(count($queue) > 0)
        <a href="{{ route('admin.trust-safety.queue') }}" style="display:block;text-align:center;margin-top:12px;background:#f3f4f6;border-radius:8px;padding:8px;font-size:12px;font-weight:700;color:#374151;text-decoration:none;">
            Go to Queue ({{ $pendingReview }}) →
        </a>
        @endif
    </div>

</div>

{{-- Row 3: Risk Alerts + Moderator Activity + Live Alerts --}}
<div style="display:grid;grid-template-columns:1fr 1fr 340px;gap:16px;margin-bottom:16px;">

    {{-- Trending Risk Alerts --}}
    <div class="ts-card">
        <div class="ts-card-title">
            Trending Risk Alerts
            <a href="{{ route('admin.trust-safety.reports') }}">View all</a>
        </div>
        @forelse($riskAlerts as $alert)
        <div class="risk-alert">
            <div style="width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
                background:{{ $alert['level'] === 'critical' ? '#7f1d1d' : ($alert['level'] === 'high' ? '#fee2e2' : ($alert['level'] === 'medium' ? '#fef3c7' : '#f0fdf4')) }};
                color:{{ $alert['level'] === 'critical' ? '#fff' : ($alert['level'] === 'high' ? '#dc2626' : ($alert['level'] === 'medium' ? '#d97706' : '#16a34a')) }};">
                <i class="fas fa-{{ $alert['level'] === 'critical' ? 'skull' : ($alert['level'] === 'high' ? 'fire' : ($alert['level'] === 'medium' ? 'exclamation' : 'info')) }}"></i>
            </div>
            <div style="flex:1;">
                <div style="font-size:13px;font-weight:700;color:#111827;">{{ $alert['title'] }}</div>
                <div style="font-size:11px;color:#6b7280;">{{ $alert['description'] }}</div>
            </div>
            <div class="risk-cnt">{{ number_format($alert['count']) }}</div>
        </div>
        @empty
        <div style="text-align:center;padding:20px 0;color:#9ca3af;font-size:13px;">No active risk alerts</div>
        @endforelse
    </div>

    {{-- Moderator Activity --}}
    <div class="ts-card">
        <div class="ts-card-title">
            Moderator Activity
            <a href="#">View all moderators →</a>
        </div>
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="font-size:11px;color:#9ca3af;font-weight:700;border-bottom:1px solid #f3f4f6;">
                    <td style="padding:0 8px 8px;">Moderator</td>
                    <td style="padding:0 8px 8px;text-align:center;">Reviewed</td>
                    <td style="padding:0 8px 8px;text-align:center;">Resolved</td>
                    <td style="padding:0 8px 8px;text-align:center;">Accuracy</td>
                    <td style="padding:0 8px 8px;text-align:center;">Status</td>
                </tr>
            </thead>
            <tbody>
                @forelse($moderatorActivity as $mod)
                <tr class="mod-row">
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#FF8A00,#ff5f00);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:800;flex-shrink:0;">
                                {{ strtoupper(substr($mod->name,0,1)) }}
                            </div>
                            <span style="font-weight:600;color:#111827;">{{ $mod->name }}</span>
                        </div>
                    </td>
                    <td style="text-align:center;font-weight:700;">{{ $mod->reviewed }}</td>
                    <td style="text-align:center;font-weight:700;">{{ $mod->resolved }}</td>
                    <td style="text-align:center;font-weight:700;color:#10b981;">{{ $mod->accuracy }}%</td>
                    <td style="text-align:center;">
                        <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;color:#10b981;font-weight:700;">
                            <span style="width:6px;height:6px;border-radius:50%;background:#22c55e;"></span>Online
                        </span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;padding:20px;color:#9ca3af;font-size:13px;">No moderator activity yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Live Stream / Recent Reports --}}
    <div class="ts-card">
        <div class="ts-card-title">
            Recent Reports
            <a href="{{ route('admin.trust-safety.reports') }}">View all</a>
        </div>
        @forelse($recentReports->take(5) as $r)
        <div style="display:flex;gap:10px;align-items:flex-start;padding:9px 0;border-bottom:1px solid #f3f4f6;">
            <div style="width:36px;height:36px;border-radius:8px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:14px;flex-shrink:0;">
                <i class="fas fa-flag"></i>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:12px;font-weight:700;color:#111827;">{{ ucfirst(str_replace('_',' ',$r->reason)) }}</div>
                <div style="font-size:11px;color:#6b7280;">By: {{ $r->reporter_name }}</div>
                <div style="font-size:10px;color:#9ca3af;">{{ \Carbon\Carbon::parse($r->created_at)->diffForHumans() }}</div>
            </div>
            @php $rs = $r->status ?? 'pending'; @endphp
            <span class="badge-risk badge-{{ $rs === 'pending' ? 'high' : ($rs === 'resolved' ? 'low' : 'medium') }}" style="flex-shrink:0;">
                {{ ucfirst($rs) }}
            </span>
        </div>
        @empty
        <div style="text-align:center;padding:20px 0;color:#9ca3af;font-size:13px;">No reports yet</div>
        @endforelse
    </div>

</div>

{{-- Row 4: Trust & Safety Score --}}
<div class="ts-card">
    <div class="ts-card-title">Trust & Safety Score</div>
    <div style="display:grid;grid-template-columns:auto 1fr 1fr 1fr 1fr auto;gap:32px;align-items:center;">

        <div style="display:flex;align-items:center;gap:16px;">
            <div style="width:60px;height:60px;border-radius:16px;background:linear-gradient(135deg,#22c55e,#16a34a);display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-shield-halved" style="font-size:28px;color:#fff;"></i>
            </div>
            <div>
                <div class="trust-score-num">{{ $overallScore }}</div>
                <div style="font-size:10px;font-weight:700;color:#6b7280;">/ 100</div>
                <div style="margin-top:4px;">
                    @if($overallScore >= 85)
                        <span style="background:#dcfce7;color:#16a34a;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:800;">Excellent</span>
                    @elseif($overallScore >= 70)
                        <span style="background:#fef3c7;color:#d97706;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:800;">Good</span>
                    @else
                        <span style="background:#fee2e2;color:#dc2626;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:800;">Needs Attention</span>
                    @endif
                </div>
            </div>
        </div>

        @foreach([
            ['label'=>'Content Safety','val'=>$contentSafety,'color'=>'#3b82f6'],
            ['label'=>'User Protection','val'=>$userProtection,'color'=>'#a855f7'],
            ['label'=>'Platform Integrity','val'=>$platformIntegrity,'color'=>'#f97316'],
            ['label'=>'Community Health','val'=>$communityHealth,'color'=>'#22c55e'],
        ] as $sub)
        <div class="sub-score">
            <div class="sub-score-label">{{ $sub['label'] }}</div>
            <div class="sub-score-val">{{ $sub['val'] }}<span style="font-size:14px;color:#9ca3af;">/100</span></div>
            <div class="sub-score-bar">
                <div class="score-fill" style="width:{{ $sub['val'] }}%;background:{{ $sub['color'] }};"></div>
            </div>
        </div>
        @endforeach

        {{-- Mini trend sparkline --}}
        <div>
            <div style="font-size:11px;color:#6b7280;font-weight:600;margin-bottom:8px;">Score Trend</div>
            <canvas id="trendChart" width="140" height="50"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const chartData = @json($chartData);
const catData   = @json($byCategory);
const catColors = ['#6366f1','#f97316','#ef4444','#3b82f6','#a855f7','#f59e0b','#14b8a6','#64748b'];

// Reports Overview
new Chart(document.getElementById('reportsChart'), {
    type: 'line',
    data: {
        labels: chartData.map(d => d.label),
        datasets: [
            { label:'Reports',  data: chartData.map(d=>d.reports),  borderColor:'#22c55e', backgroundColor:'rgba(34,197,94,0.08)', tension:.4, fill:true, pointRadius:3 },
            { label:'Resolved', data: chartData.map(d=>d.resolved), borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,0.05)', tension:.4, fill:true, pointRadius:3 },
            { label:'Pending',  data: chartData.map(d=>d.pending),  borderColor:'#f97316', backgroundColor:'rgba(249,115,22,0.05)', tension:.4, fill:true, pointRadius:3 },
        ]
    },
    options: { responsive:true, plugins:{ legend:{ position:'top', labels:{ boxWidth:10, font:{size:11} } } }, scales:{ y:{ beginAtZero:true, grid:{ color:'#f3f4f6' } }, x:{ grid:{ display:false } } } }
});

// Category donut
if (catData.length > 0) {
    new Chart(document.getElementById('catChart'), {
        type: 'doughnut',
        data: {
            labels: catData.map(d=>d.label),
            datasets:[{ data: catData.map(d=>d.value), backgroundColor: catColors, borderWidth:2, borderColor:'#fff' }]
        },
        options: { cutout:'72%', plugins:{ legend:{ display:false } }, responsive:false }
    });
}

// Trend sparkline (use overall score over days, static placeholder trend)
new Chart(document.getElementById('trendChart'), {
    type:'line',
    data:{
        labels: chartData.map(d=>d.label),
        datasets:[{ data: chartData.map((_,i)=> Math.max(70, 85 + Math.sin(i)*5) ), borderColor:'#22c55e', borderWidth:2, pointRadius:0, fill:false, tension:.5 }]
    },
    options:{ responsive:false, plugins:{legend:{display:false}}, scales:{ x:{display:false}, y:{display:false} } }
});

// Auto-refresh every 60s
setTimeout(()=>location.reload(), 60000);
</script>
@endsection
