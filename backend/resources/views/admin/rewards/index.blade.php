@extends('admin.layouts.app')
@section('title', 'Rewards Dashboard')

@push('styles')
<style>
/* ── Design tokens ─────────────────────────────────────────── */
:root {
  --rw-gold:    #F59E0B;
  --rw-purple:  #7C3AED;
  --rw-teal:    #0D9488;
  --rw-rose:    #E11D48;
  --rw-blue:    #2563EB;
  --rw-surface: #F8FAFC;
  --rw-card:    #FFFFFF;
  --rw-border:  #E2E8F0;
  --rw-text:    #0F172A;
  --rw-muted:   #64748B;
  --rw-radius:  14px;
}

/* ── Layout ─────────────────────────────────────────────────── */
.rw-wrap { background: var(--rw-surface); min-height: 100vh; padding: 28px 24px; }

/* ── Hero header ─────────────────────────────────────────────── */
.rw-hero {
  background: linear-gradient(135deg, #1E1B4B 0%, #312E81 45%, #4338CA 100%);
  border-radius: var(--rw-radius);
  padding: 32px;
  color: #fff;
  margin-bottom: 28px;
  position: relative;
  overflow: hidden;
}
.rw-hero::before {
  content: '';
  position: absolute; inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23fff' fill-opacity='0.03'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
}
.rw-hero-content { position: relative; z-index: 1; }

/* ── Stat cards ─────────────────────────────────────────────── */
.rw-stat {
  background: var(--rw-card);
  border: 1px solid var(--rw-border);
  border-radius: var(--rw-radius);
  padding: 22px 20px;
  transition: box-shadow .2s, transform .2s;
}
.rw-stat:hover { box-shadow: 0 8px 24px rgba(0,0,0,.08); transform: translateY(-2px); }
.rw-stat-icon {
  width: 48px; height: 48px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; flex-shrink: 0;
}
.rw-stat-val { font-size: 26px; font-weight: 800; line-height: 1.1; color: var(--rw-text); }
.rw-stat-lbl { font-size: 12px; color: var(--rw-muted); font-weight: 500; margin-top: 2px; }
.rw-stat-badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 20px; }

/* ── Section heading ─────────────────────────────────────────── */
.rw-section-head {
  display: flex; align-items: center; gap: 10px;
  font-size: 15px; font-weight: 700; color: var(--rw-text);
  margin-bottom: 16px;
}
.rw-section-head .rw-sh-icon {
  width: 32px; height: 32px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center; font-size: 15px;
}

/* ── Card ────────────────────────────────────────────────────── */
.rw-card {
  background: var(--rw-card);
  border: 1px solid var(--rw-border);
  border-radius: var(--rw-radius);
  overflow: hidden;
}
.rw-card-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--rw-border);
  display: flex; align-items: center; justify-content: space-between;
  background: #FAFBFF;
}

/* ── Chart container ─────────────────────────────────────────── */
.rw-chart-wrap { padding: 20px; height: 220px; }

/* ── Tier pills ──────────────────────────────────────────────── */
.tier-pill {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 16px; border-radius: 12px;
  border: 1.5px solid;
}
.tier-bar-wrap { height: 8px; background: #F1F5F9; border-radius: 4px; overflow: hidden; margin-top: 4px; }
.tier-bar { height: 100%; border-radius: 4px; transition: width .6s ease; }

/* ── Table ───────────────────────────────────────────────────── */
.rw-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.rw-table th { padding: 10px 14px; font-weight: 600; font-size: 11px; text-transform: uppercase;
               letter-spacing: .5px; color: var(--rw-muted); background: #F8FAFC;
               border-bottom: 1px solid var(--rw-border); }
.rw-table td { padding: 11px 14px; border-bottom: 1px solid #F1F5F9; color: var(--rw-text); vertical-align: middle; }
.rw-table tr:last-child td { border-bottom: none; }
.rw-table tr:hover td { background: #FAFBFF; }

/* ── Form elements ───────────────────────────────────────────── */
.rw-input { border: 1.5px solid var(--rw-border); border-radius: 8px; padding: 8px 12px;
            font-size: 14px; width: 100%; transition: border-color .15s; outline: none; }
.rw-input:focus { border-color: #6366F1; box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
.rw-label { font-size: 12px; font-weight: 600; color: var(--rw-muted); text-transform: uppercase;
            letter-spacing: .4px; margin-bottom: 6px; display: block; }
.rw-hint  { font-size: 11px; color: #94A3B8; margin-top: 4px; }

/* ── Toggle ──────────────────────────────────────────────────── */
.rw-toggle { position: relative; display: inline-flex; align-items: center; gap: 10px; cursor: pointer; }
.rw-toggle input { display: none; }
.rw-toggle-track {
  width: 44px; height: 24px; background: #CBD5E1; border-radius: 12px;
  transition: background .2s; flex-shrink: 0;
}
.rw-toggle input:checked ~ .rw-toggle-track { background: #6366F1; }
.rw-toggle-thumb {
  position: absolute; left: 3px; width: 18px; height: 18px;
  background: #fff; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,.2);
  transition: left .2s;
}
.rw-toggle input:checked ~ .rw-toggle-track .rw-toggle-thumb { left: 23px; }
.rw-toggle-label { font-size: 14px; font-weight: 600; color: var(--rw-text); }

/* ── Mult badge ──────────────────────────────────────────────── */
.mult-badge {
  display: inline-block; font-size: 11px; font-weight: 700;
  padding: 2px 7px; border-radius: 6px; margin-left: 6px;
  background: #EEF2FF; color: #4F46E5;
}

/* ── Module card ─────────────────────────────────────────────── */
.mod-card {
  border: 1.5px solid var(--rw-border); border-radius: 10px; padding: 14px;
  transition: border-color .2s;
}
.mod-card:hover { border-color: #A5B4FC; }

/* ── Tier benefit row ────────────────────────────────────────── */
.tier-row { display: flex; align-items: center; gap: 12px; padding: 14px 0;
            border-bottom: 1px solid var(--rw-border); }
.tier-row:last-child { border-bottom: none; }
.tier-avatar { width: 42px; height: 42px; border-radius: 10px; display: flex;
               align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }

/* ── Txn dot ─────────────────────────────────────────────────── */
.txn-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

/* ── Save bar ────────────────────────────────────────────────── */
.rw-save-bar {
  position: sticky; bottom: 0; background: rgba(255,255,255,.95);
  backdrop-filter: blur(8px); border-top: 1px solid var(--rw-border);
  padding: 14px 24px; margin: 0 -24px;
  display: flex; align-items: center; gap: 12px; z-index: 100;
}
.rw-btn-primary {
  background: linear-gradient(135deg, #6366F1, #4F46E5);
  color: #fff; border: none; padding: 10px 28px;
  border-radius: 10px; font-weight: 700; font-size: 14px;
  cursor: pointer; transition: opacity .15s;
}
.rw-btn-primary:hover { opacity: .9; }
.rw-btn-ghost {
  background: transparent; border: 1.5px solid var(--rw-border);
  padding: 10px 20px; border-radius: 10px; font-size: 14px;
  color: var(--rw-muted); cursor: pointer; font-weight: 600;
  text-decoration: none; display: inline-flex; align-items: center;
  transition: border-color .15s;
}
.rw-btn-ghost:hover { border-color: #A5B4FC; color: #4F46E5; }

/* ── Tabs ────────────────────────────────────────────────────── */
.rw-tabs { display: flex; gap: 4px; background: #F1F5F9; padding: 4px; border-radius: 10px; }
.rw-tab {
  flex: 1; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 600;
  cursor: pointer; text-align: center; color: var(--rw-muted);
  transition: all .2s; border: none; background: transparent;
}
.rw-tab.active { background: #fff; color: var(--rw-text); box-shadow: 0 1px 4px rgba(0,0,0,.1); }
.rw-tab-panel { display: none; }
.rw-tab-panel.active { display: block; }

/* ── Badge chip ──────────────────────────────────────────────── */
.badge-chip {
  display: flex; align-items: center; gap: 8px; padding: 8px 12px;
  background: #F8FAFC; border: 1px solid var(--rw-border); border-radius: 8px;
}

/* ── Progress ring ───────────────────────────────────────────── */
.prog-ring { transform: rotate(-90deg); }

/* ── Alert ───────────────────────────────────────────────────── */
.rw-alert {
  padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 500;
  display: flex; align-items: center; gap: 10px; margin-bottom: 20px;
}
.rw-alert.success { background: #F0FDF4; border: 1px solid #BBF7D0; color: #166534; }
</style>
@endpush

@section('content')
<div class="rw-wrap">

{{-- ── HERO ───────────────────────────────────────────────────── --}}
<div class="rw-hero">
  <div class="rw-hero-content d-flex align-items-start justify-content-between flex-wrap gap-3">
    <div>
      <div style="font-size:13px;color:rgba(255,255,255,.6);font-weight:500;letter-spacing:.5px;text-transform:uppercase;margin-bottom:6px;">Loyalty &amp; Rewards</div>
      <h2 style="font-size:28px;font-weight:800;margin:0;letter-spacing:-.5px;">Rewards Dashboard</h2>
      <p style="color:rgba(255,255,255,.65);margin:8px 0 0;font-size:14px;">Full visibility into your points economy, tiers, and engagement</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('admin.rewards.ledger') }}" class="btn btn-sm" style="background:rgba(255,255,255,.12);color:#fff;border:1.5px solid rgba(255,255,255,.25);border-radius:8px;font-weight:600;">
        📋 Full Ledger
      </a>
      <div style="background:rgba(255,255,255,.12);border:1.5px solid rgba(255,255,255,.25);border-radius:8px;padding:4px 12px;display:flex;align-items:center;gap:8px;">
        <span style="font-size:12px;color:rgba(255,255,255,.7);">Rewards</span>
        @if(($settings['reward_enabled'] ?? '1') == '1')
          <span style="background:#22C55E;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;">ACTIVE</span>
        @else
          <span style="background:#EF4444;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;">PAUSED</span>
        @endif
      </div>
    </div>
  </div>

  {{-- Mini KPI row inside hero --}}
  <div class="d-flex gap-4 flex-wrap mt-4" style="padding-top:16px;border-top:1px solid rgba(255,255,255,.12);">
    @php
      $heroStats = [
        ['Pts This Month', number_format($earnedThisMonth), $earnedGrowth >= 0 ? '↑ '.$earnedGrowth.'%' : '↓ '.abs($earnedGrowth).'%', $earnedGrowth >= 0 ? '#4ADE80' : '#F87171'],
        ['Active Streaks', number_format($activeStreaks), 'users', '#A78BFA'],
        ['Badges Awarded', number_format($totalBadgesAwarded), 'all time', '#FCD34D'],
        ['Referrals', number_format($totalReferrals), number_format($rewardedReferrals).' rewarded', '#67E8F9'],
      ];
    @endphp
    @foreach($heroStats as [$lbl, $val, $sub, $subColor])
    <div>
      <div style="font-size:22px;font-weight:800;color:#fff;">{{ $val }}</div>
      <div style="font-size:12px;color:rgba(255,255,255,.6);">{{ $lbl }}</div>
      <div style="font-size:11px;font-weight:600;color:{{ $subColor }};">{{ $sub }}</div>
    </div>
    @endforeach
  </div>
</div>

{{-- Alert --}}
@if(session('success'))
<div class="rw-alert success">✓ {{ session('success') }}</div>
@endif

{{-- ── ANALYTICS ROW ──────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
  {{-- KPI cards --}}
  @php
    $kpis = [
      ['icon'=>'👥','bg'=>'#EEF2FF','ic'=>'#6366F1','val'=>number_format($totalUsers),'lbl'=>'Users with Points','sub'=>'Active earners'],
      ['icon'=>'⭐','bg'=>'#FFFBEB','ic'=>'#F59E0B','val'=>number_format($totalPts),'lbl'=>'Points Balance','sub'=>'In circulation'],
      ['icon'=>'📈','bg'=>'#F0FDF4','ic'=>'#22C55E','val'=>number_format($totalEarned),'lbl'=>'Total Earned','sub'=>'All time'],
      ['icon'=>'💸','bg'=>'#FFF1F2','ic'=>'#E11D48','val'=>number_format($totalRedeemed),'lbl'=>'Total Redeemed','sub'=>'All time'],
    ];
  @endphp
  @foreach($kpis as $k)
  <div class="col-6 col-xl-3">
    <div class="rw-stat">
      <div class="d-flex align-items-center gap-3 mb-2">
        <div class="rw-stat-icon" style="background:{{ $k['bg'] }};color:{{ $k['ic'] }};">{{ $k['icon'] }}</div>
        <div>
          <div class="rw-stat-val">{{ $k['val'] }}</div>
          <div class="rw-stat-lbl">{{ $k['lbl'] }}</div>
        </div>
      </div>
      <div style="font-size:11px;color:{{ $k['ic'] }};font-weight:600;">{{ $k['sub'] }}</div>
    </div>
  </div>
  @endforeach
</div>

{{-- ── CHARTS ROW ─────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
  {{-- Points earned chart --}}
  <div class="col-lg-8">
    <div class="rw-card h-100">
      <div class="rw-card-header">
        <div class="rw-section-head mb-0">
          <div class="rw-sh-icon" style="background:#EEF2FF;">📈</div>
          Points Earned — Last 30 Days
        </div>
        <span style="font-size:12px;color:var(--rw-muted);">Daily totals</span>
      </div>
      <div class="rw-chart-wrap">
        <canvas id="chartPts"></canvas>
      </div>
    </div>
  </div>

  {{-- Tier donut --}}
  <div class="col-lg-4">
    <div class="rw-card h-100">
      <div class="rw-card-header">
        <div class="rw-section-head mb-0">
          <div class="rw-sh-icon" style="background:#FFFBEB;">🏆</div>
          Tier Distribution
        </div>
      </div>
      <div style="padding:16px;">
        <div style="position:relative;height:160px;display:flex;align-items:center;justify-content:center;">
          <canvas id="chartTier" width="160" height="160"></canvas>
          <div style="position:absolute;text-align:center;">
            @php $totalTierUsers = array_sum($tierStats); @endphp
            <div style="font-size:22px;font-weight:800;">{{ number_format($totalTierUsers) }}</div>
            <div style="font-size:11px;color:var(--rw-muted);">Total Users</div>
          </div>
        </div>
        @php
          $tierDef = ['bronze'=>['🥉','#CD7F32'],'silver'=>['🥈','#A0A0A0'],'gold'=>['🥇','#F59E0B'],'platinum'=>['💎','#38BDF8']];
        @endphp
        <div class="mt-3 d-flex flex-column gap-2">
          @foreach($tierDef as $t => [$emoji, $color])
          @php $cnt = $tierStats[$t] ?? 0; $pct = $totalTierUsers > 0 ? round($cnt/$totalTierUsers*100) : 0; @endphp
          <div class="d-flex align-items-center gap-2">
            <div style="width:10px;height:10px;border-radius:3px;background:{{ $color }};flex-shrink:0;"></div>
            <div style="font-size:12px;flex:1;">{{ $emoji }} {{ ucfirst($t) }}</div>
            <div style="font-size:12px;font-weight:700;">{{ number_format($cnt) }}</div>
            <div style="font-size:11px;color:var(--rw-muted);width:32px;text-align:right;">{{ $pct }}%</div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ── ANALYTICS DETAIL ROW ───────────────────────────────────── --}}
<div class="row g-3 mb-4">
  {{-- Top Earners --}}
  <div class="col-lg-6">
    <div class="rw-card">
      <div class="rw-card-header">
        <div class="rw-section-head mb-0">
          <div class="rw-sh-icon" style="background:#F0FDF4;">🏅</div>
          Top Earners
        </div>
        <span style="font-size:11px;color:var(--rw-muted);">By lifetime pts</span>
      </div>
      <div style="overflow-x:auto;">
        <table class="rw-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Customer</th>
              <th>Tier</th>
              <th>Balance</th>
              <th>Total Earned</th>
            </tr>
          </thead>
          <tbody>
            @foreach($topEarners as $i => $u)
            @php
              $tierColors = ['bronze'=>'#CD7F32','silver'=>'#A0A0A0','gold'=>'#F59E0B','platinum'=>'#38BDF8'];
              $tierEmojis = ['bronze'=>'🥉','silver'=>'🥈','gold'=>'🥇','platinum'=>'💎'];
              $tc = $tierColors[$u->tier ?? 'bronze'] ?? '#CD7F32';
              $te = $tierEmojis[$u->tier ?? 'bronze'] ?? '🥉';
              $rank = $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : ($i+1)));
            @endphp
            <tr>
              <td style="font-weight:700;font-size:14px;">{{ $rank }}</td>
              <td>
                <div style="font-weight:600;font-size:13px;">{{ $u->name }}</div>
                <div style="font-size:11px;color:var(--rw-muted);">{{ $u->phone }}</div>
              </td>
              <td>
                <span style="background:{{ $tc }}22;color:{{ $tc }};font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;">{{ $te }} {{ ucfirst($u->tier) }}</span>
              </td>
              <td style="font-weight:600;">⭐ {{ number_format($u->points_balance) }}</td>
              <td style="color:var(--rw-muted);font-size:12px;">{{ number_format($u->total_points_earned ?? 0) }}</td>
            </tr>
            @endforeach
            @if($topEarners->isEmpty())
            <tr><td colspan="5" style="text-align:center;color:var(--rw-muted);padding:24px;">No data yet</td></tr>
            @endif
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Recent Transactions --}}
  <div class="col-lg-6">
    <div class="rw-card">
      <div class="rw-card-header">
        <div class="rw-section-head mb-0">
          <div class="rw-sh-icon" style="background:#FFF7ED;">⚡</div>
          Recent Transactions
        </div>
        <a href="{{ route('admin.rewards.ledger') }}" style="font-size:12px;color:#6366F1;font-weight:600;text-decoration:none;">View all →</a>
      </div>
      <div style="overflow-x:auto;">
        <table class="rw-table">
          <thead>
            <tr><th>Customer</th><th>Points</th><th>Description</th><th>Time</th></tr>
          </thead>
          <tbody>
            @foreach($recentTxns as $t)
            @php $isEarn = $t->type === 'earned'; @endphp
            <tr>
              <td>
                <div style="font-weight:600;font-size:13px;">{{ $t->name }}</div>
                <div style="font-size:11px;color:var(--rw-muted);">{{ $t->phone }}</div>
              </td>
              <td>
                <span style="font-weight:700;color:{{ $isEarn ? '#22C55E' : '#E11D48' }};font-size:13px;">
                  {{ $isEarn ? '+' : '-' }}{{ number_format(abs($t->points)) }}
                </span>
              </td>
              <td style="font-size:12px;color:var(--rw-muted);max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                {{ $t->note ?: ucfirst($t->reference_type ?? 'reward') }}
              </td>
              <td style="font-size:11px;color:var(--rw-muted);white-space:nowrap;">
                {{ \Carbon\Carbon::parse($t->created_at)->diffForHumans() }}
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

{{-- ── GAMIFICATION STATS ──────────────────────────────────────── --}}
<div class="row g-3 mb-4">
  {{-- Streak stats --}}
  <div class="col-md-4">
    <div class="rw-card h-100">
      <div class="rw-card-header">
        <div class="rw-section-head mb-0">
          <div class="rw-sh-icon" style="background:#FFF7ED;">🔥</div>
          Streaks
        </div>
      </div>
      <div style="padding:16px;display:flex;flex-direction:column;gap:12px;">
        @foreach([['Active Streaks',$activeStreaks,'#F97316'],['Longest Streak',$maxStreak.' days','#EF4444'],['Avg Current Streak',$avgStreak.' days','#FB923C']] as [$lbl,$val,$color])
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#FFF7ED;border-radius:10px;">
          <div style="font-size:13px;color:#92400E;font-weight:500;">{{ $lbl }}</div>
          <div style="font-size:18px;font-weight:800;color:{{ $color }};">{{ $val }}</div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Badge stats --}}
  <div class="col-md-4">
    <div class="rw-card h-100">
      <div class="rw-card-header">
        <div class="rw-section-head mb-0">
          <div class="rw-sh-icon" style="background:#FEF9C3;">🏅</div>
          Top Badges
        </div>
        <span style="font-size:11px;color:var(--rw-muted);">{{ number_format($totalBadgesAwarded) }} total awarded</span>
      </div>
      <div style="padding:12px;display:flex;flex-direction:column;gap:8px;">
        @foreach($badgeBreakdown as $b)
        <div class="badge-chip">
          <span style="font-size:16px;">{{ $b->icon }}</span>
          <span style="font-size:12px;font-weight:600;flex:1;">{{ $b->name }}</span>
          <span style="font-size:12px;font-weight:700;color:#6366F1;">{{ number_format($b->cnt) }}</span>
        </div>
        @endforeach
        @if($badgeBreakdown->isEmpty())
          <div style="text-align:center;color:var(--rw-muted);padding:20px;font-size:13px;">No badges awarded yet</div>
        @endif
      </div>
    </div>
  </div>

  {{-- Module breakdown --}}
  <div class="col-md-4">
    <div class="rw-card h-100">
      <div class="rw-card-header">
        <div class="rw-section-head mb-0">
          <div class="rw-sh-icon" style="background:#F0F9FF;">📦</div>
          Points by Module
        </div>
        <span style="font-size:11px;color:var(--rw-muted);">Last 30 days</span>
      </div>
      <div style="padding:12px;display:flex;flex-direction:column;gap:10px;">
        @php
          $moduleMax = $moduleBreakdown->max('pts') ?: 1;
          $modColors = ['#6366F1','#F59E0B','#22C55E','#E11D48','#38BDF8','#A78BFA','#F97316','#10B981'];
        @endphp
        @foreach($moduleBreakdown->take(8) as $i => $m)
        @php $pct = round($m->pts / $moduleMax * 100); @endphp
        <div>
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span style="font-size:12px;font-weight:600;text-transform:capitalize;">{{ $m->module }}</span>
            <span style="font-size:12px;color:var(--rw-muted);">{{ number_format($m->pts) }} pts</span>
          </div>
          <div class="tier-bar-wrap"><div class="tier-bar" style="width:{{ $pct }}%;background:{{ $modColors[$i % count($modColors)] }};"></div></div>
        </div>
        @endforeach
        @if($moduleBreakdown->isEmpty())
          <div style="text-align:center;color:var(--rw-muted);padding:20px;font-size:13px;">No data yet</div>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- ── SETTINGS FORM ───────────────────────────────────────────── --}}
<form method="POST" action="{{ route('admin.rewards.update') }}">
@csrf @method('PATCH')

{{-- Tab nav --}}
<div class="rw-card mb-4">
  <div class="rw-card-header">
    <div class="rw-tabs" id="settingsTabs">
      <button type="button" class="rw-tab active" data-tab="general">⚙️ General</button>
      <button type="button" class="rw-tab" data-tab="modules">📦 Modules</button>
      <button type="button" class="rw-tab" data-tab="tiers">🏆 Tiers</button>
      <button type="button" class="rw-tab" data-tab="referral">🔗 Referral</button>
    </div>
  </div>

  {{-- ── GENERAL TAB ─────────────────────────────────────────── --}}
  <div class="rw-tab-panel active" id="tab-general" style="padding:24px;">

    {{-- Master toggle --}}
    <div style="background:linear-gradient(135deg,#F8FAFF,#EEF2FF);border:1.5px solid #C7D2FE;border-radius:12px;padding:18px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
      <div>
        <div style="font-weight:700;font-size:15px;color:#1E1B4B;">Rewards System</div>
        <div style="font-size:13px;color:#6366F1;margin-top:2px;">Controls all points earning and redemption</div>
      </div>
      <label class="rw-toggle">
        <input type="checkbox" name="reward_enabled" value="1" {{ ($settings['reward_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
        <div class="rw-toggle-track"><div class="rw-toggle-thumb"></div></div>
        <span class="rw-toggle-label">Rewards Enabled</span>
      </label>
    </div>

    <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--rw-muted);margin-bottom:14px;">Points Economy</div>
    <div class="row g-4">
      <div class="col-md-4">
        <label class="rw-label">Points per $1 Spent</label>
        <input type="number" name="points_per_dollar" class="rw-input" value="{{ $settings['points_per_dollar'] ?? 10 }}" min="1" max="1000">
        <div class="rw-hint">10 pts per $1 = 1× rate</div>
      </div>
      <div class="col-md-4">
        <label class="rw-label">Points Needed for $1 Discount</label>
        <input type="number" name="points_to_dollar" class="rw-input" value="{{ $settings['points_to_dollar'] ?? 100 }}" min="1" max="10000">
        <div class="rw-hint">100 pts = $1 off order</div>
      </div>
      <div class="col-md-4">
        <label class="rw-label">Points Expiry (Days)</label>
        <input type="number" name="points_expire_days" class="rw-input" value="{{ $settings['points_expire_days'] ?? 365 }}" min="30" max="3650">
        <div class="rw-hint">0 = never expire</div>
      </div>
      <div class="col-md-4">
        <label class="rw-label">Min Order to Earn ($)</label>
        <input type="number" name="min_order_for_points" class="rw-input" step="0.01" value="{{ $settings['min_order_for_points'] ?? 2 }}" min="0">
        <div class="rw-hint">Orders below this earn no pts</div>
      </div>
      <div class="col-md-4">
        <label class="rw-label">Max Redeem per Order (%)</label>
        <input type="number" name="max_redeem_percent" class="rw-input" value="{{ $settings['max_redeem_percent'] ?? 50 }}" min="1" max="100">
        <div class="rw-hint">Max % of order paid with pts</div>
      </div>

      {{-- Live calculator --}}
      <div class="col-md-4">
        <div style="background:#F8FAFC;border:1.5px dashed #C7D2FE;border-radius:10px;padding:14px;">
          <div style="font-size:11px;font-weight:700;color:#6366F1;text-transform:uppercase;margin-bottom:8px;">💡 Live Calculator</div>
          <div style="font-size:12px;color:var(--rw-muted);">$10 order earns <strong id="calc-earn" style="color:#22C55E;">—</strong> pts</div>
          <div style="font-size:12px;color:var(--rw-muted);">1000 pts = <strong id="calc-redeem" style="color:#6366F1;">—</strong> discount</div>
        </div>
      </div>
    </div>
  </div>

  {{-- ── MODULES TAB ─────────────────────────────────────────── --}}
  <div class="rw-tab-panel" id="tab-modules" style="padding:24px;">
    <p style="font-size:13px;color:var(--rw-muted);margin-bottom:20px;">
      Set earn multiplier per module. <strong>Value ÷ 10 = multiplier</strong> — e.g. 10 = 1×, 20 = 2×, 5 = 0.5×
    </p>
    <div class="row g-3">
      @php
        $modules = [
          ['efood','eFood','🍔'],['egrocery','eGrocery','🛒'],['eshop','eShop','🛍️'],
          ['eparcel','eParcel','📦'],['emoving','eMoving','🚚'],['erent','eRent','🏠'],
          ['eticket','eTicket','✈️'],['elearning','eLearning','📚'],['eexchange','eExchange','💱'],
          ['elaundry','eLaundry','👕'],['edata','eData','📡'],['ehealth','eHealth','🏥'],
        ];
      @endphp
      @foreach($modules as [$slug, $label, $emoji])
      @php $val = $settings["pts_mult_{$slug}"] ?? 10; @endphp
      <div class="col-6 col-md-4 col-lg-3">
        <div class="mod-card">
          <div style="font-size:18px;margin-bottom:6px;">{{ $emoji }}</div>
          <label class="rw-label">{{ $label }}</label>
          <div style="display:flex;align-items:center;gap:8px;">
            <input type="number" name="pts_mult_{{ $slug }}" class="rw-input mult-input" data-target="mult-lbl-{{ $slug }}" value="{{ $val }}" min="1" max="100" style="flex:1;">
            <span class="mult-badge" id="mult-lbl-{{ $slug }}">{{ number_format($val/10, 1) }}×</span>
          </div>
          <div class="rw-hint">{{ $val >= 10 ? '+'.(($val/10-1)*100).'% bonus' : (100-$val/10*100).'% reduced' }}</div>
        </div>
      </div>
      @endforeach
    </div>
  </div>

  {{-- ── TIERS TAB ───────────────────────────────────────────── --}}
  <div class="rw-tab-panel" id="tab-tiers" style="padding:24px;">

    {{-- Tier progress visual --}}
    <div style="background:linear-gradient(135deg,#FFFBEB,#FEF3C7);border:1.5px solid #FDE68A;border-radius:12px;padding:16px;margin-bottom:24px;">
      <div style="font-size:12px;font-weight:700;color:#92400E;margin-bottom:12px;">📊 CURRENT USER DISTRIBUTION</div>
      <div class="d-flex gap-2" style="height:40px;border-radius:8px;overflow:hidden;">
        @php
          $totalU = max(array_sum($tierStats), 1);
          $tierPcts = ['bronze'=>round(($tierStats['bronze']??0)/$totalU*100),'silver'=>round(($tierStats['silver']??0)/$totalU*100),'gold'=>round(($tierStats['gold']??0)/$totalU*100),'platinum'=>round(($tierStats['platinum']??0)/$totalU*100)];
          $tierBg = ['bronze'=>'#CD7F32','silver'=>'#A0A0A0','gold'=>'#F59E0B','platinum'=>'#38BDF8'];
        @endphp
        @foreach($tierPcts as $t => $pct)
          @if($pct > 0)
          <div style="background:{{ $tierBg[$t] }};flex:{{ $pct }};display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;min-width:{{ $pct > 5 ? 'auto' : '24px' }};">
            {{ $pct > 5 ? ucfirst($t).' '.$pct.'%' : '' }}
          </div>
          @endif
        @endforeach
      </div>
      <div class="d-flex gap-3 mt-2 flex-wrap">
        @foreach($tierBg as $t => $bg)
        <div style="display:flex;align-items:center;gap:5px;font-size:11px;">
          <div style="width:10px;height:10px;border-radius:3px;background:{{ $bg }};"></div>
          <span style="color:#92400E;font-weight:600;">{{ ucfirst($t) }}: {{ number_format($tierStats[$t]??0) }}</span>
        </div>
        @endforeach
      </div>
    </div>

    {{-- Thresholds --}}
    <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--rw-muted);margin-bottom:14px;">Tier Thresholds (Lifetime Points Earned)</div>
    <div style="font-size:12px;color:var(--rw-muted);background:#F8FAFC;border:1px solid var(--rw-border);border-radius:8px;padding:10px 14px;margin-bottom:16px;">
      ℹ️ Tiers are based on <strong>total points ever earned</strong> — not current balance. Spending points never demotes a user.
    </div>
    <div class="row g-3 mb-4">
      @foreach([['silver','🥈','#A0A0A0',1000],['gold','🥇','#F59E0B',5000],['platinum','💎','#38BDF8',20000]] as [$t,$em,$col,$def])
      <div class="col-md-4">
        <div style="border:2px solid {{ $col }}44;border-radius:12px;padding:16px;">
          <div style="font-size:20px;margin-bottom:6px;">{{ $em }}</div>
          <label class="rw-label">{{ ucfirst($t) }} Threshold</label>
          <input type="number" name="tier_{{ $t }}_pts" class="rw-input" value="{{ $settings['tier_'.$t.'_pts'] ?? $def }}" min="1">
          <div class="rw-hint" style="color:{{ $col }};">≥ this many lifetime pts</div>
        </div>
      </div>
      @endforeach
    </div>

    {{-- Per-tier benefits --}}
    <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--rw-muted);margin-bottom:14px;">Per-Tier Benefits</div>
    <div style="border:1.5px solid var(--rw-border);border-radius:12px;overflow:hidden;">
      @foreach([
        ['bronze','🥉 Bronze','#CD7F32','#FDF8F4',0,0,true],
        ['silver','🥈 Silver','#A0A0A0','#F8F8F8',10,5,false],
        ['gold','🥇 Gold','#F59E0B','#FFFDF0',25,10,false],
        ['platinum','💎 Platinum','#38BDF8','#F0FBFF',50,20,false],
      ] as [$key,$label,$color,$bg,$defBonus,$defRedeem,$readonly])
      <div class="tier-row" style="padding:16px 20px;background:{{ $bg }};">
        <div class="tier-avatar" style="background:{{ $color }}22;">{{ explode(' ',$label)[0] }}</div>
        <div style="flex:1;">
          <div style="font-weight:700;font-size:14px;color:{{ $color }};">{{ $label }}</div>
          @if($key === 'bronze')
          <div style="font-size:11px;color:var(--rw-muted);">Base tier — no bonuses</div>
          @else
          <div style="font-size:11px;color:var(--rw-muted);">{{ $settings["tier_{$key}_bonus"] ?? $defBonus }}% earn bonus · +{{ $settings["tier_{$key}_redeem_extra"] ?? $defRedeem }}% redeem cap</div>
          @endif
        </div>
        <div class="d-flex gap-3">
          <div>
            <label class="rw-label" style="font-size:10px;">Earn Bonus %</label>
            <div style="display:flex;align-items:center;gap:4px;">
              <input type="number" name="tier_{{ $key }}_bonus" class="rw-input" style="width:70px;text-align:center;"
                value="{{ $settings["tier_{$key}_bonus"] ?? $defBonus }}" min="0" max="200" {{ $readonly ? 'readonly style="opacity:.5"' : '' }}>
              <span style="font-size:12px;color:var(--rw-muted);">%</span>
            </div>
          </div>
          <div>
            <label class="rw-label" style="font-size:10px;">Extra Redeem Cap %</label>
            <div style="display:flex;align-items:center;gap:4px;">
              <input type="number" name="tier_{{ $key }}_redeem_extra" class="rw-input" style="width:70px;text-align:center;"
                value="{{ $settings["tier_{$key}_redeem_extra"] ?? $defRedeem }}" min="0" max="50" {{ $readonly ? 'readonly style="opacity:.5"' : '' }}>
              <span style="font-size:12px;color:var(--rw-muted);">%</span>
            </div>
          </div>
        </div>
      </div>
      @endforeach
    </div>
  </div>

  {{-- ── REFERRAL TAB ─────────────────────────────────────────── --}}
  <div class="rw-tab-panel" id="tab-referral" style="padding:24px;">

    {{-- Referral stats mini cards --}}
    <div class="row g-3 mb-4">
      @foreach([
        ['Total Referrals', number_format($totalReferrals), '🔗', '#6366F1','#EEF2FF'],
        ['Rewarded', number_format($rewardedReferrals), '✅', '#22C55E','#F0FDF4'],
        ['Pending', number_format($totalReferrals - $rewardedReferrals), '⏳', '#F59E0B','#FFFBEB'],
        ['Success Rate', ($totalReferrals > 0 ? round($rewardedReferrals/$totalReferrals*100) : 0).'%', '📊', '#0D9488','#F0FDFA'],
      ] as [$lbl,$val,$icon,$col,$bg])
      <div class="col-6 col-md-3">
        <div style="background:{{ $bg }};border:1.5px solid {{ $col }}33;border-radius:12px;padding:16px;text-align:center;">
          <div style="font-size:24px;">{{ $icon }}</div>
          <div style="font-size:22px;font-weight:800;color:{{ $col }};">{{ $val }}</div>
          <div style="font-size:12px;color:{{ $col }};font-weight:600;opacity:.8;">{{ $lbl }}</div>
        </div>
      </div>
      @endforeach
    </div>

    {{-- Toggle --}}
    <div style="background:#F8FAFF;border:1.5px solid #C7D2FE;border-radius:12px;padding:16px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
      <div>
        <div style="font-weight:700;">Referral Rewards</div>
        <div style="font-size:12px;color:var(--rw-muted);">2-level referral commission system</div>
      </div>
      <label class="rw-toggle">
        <input type="checkbox" name="referral_enabled" value="1" {{ ($settings['referral_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
        <div class="rw-toggle-track"><div class="rw-toggle-thumb"></div></div>
        <span class="rw-toggle-label">Enabled</span>
      </label>
    </div>

    {{-- How it works visual --}}
    <div style="background:#F8FAFC;border-radius:12px;padding:16px;margin-bottom:20px;">
      <div style="font-size:12px;font-weight:700;color:var(--rw-muted);text-transform:uppercase;margin-bottom:12px;">How it works</div>
      <div class="d-flex align-items-center gap-0 flex-wrap">
        <div style="text-align:center;padding:8px 12px;">
          <div style="font-size:18px;">👤</div>
          <div style="font-size:11px;color:#6366F1;font-weight:700;">Referrer</div>
        </div>
        <div style="font-size:20px;color:#C7D2FE;">→</div>
        <div style="text-align:center;padding:8px 12px;background:#EEF2FF;border-radius:8px;">
          <div style="font-size:11px;font-weight:700;color:#6366F1;">Friend's 1st Order</div>
          <div style="font-size:10px;color:var(--rw-muted);">triggers reward</div>
        </div>
        <div style="font-size:20px;color:#C7D2FE;">→</div>
        <div style="text-align:center;padding:8px 12px;">
          <div style="font-size:15px;font-weight:800;color:#22C55E;">+500 pts</div>
          <div style="font-size:10px;color:var(--rw-muted);">base reward</div>
        </div>
        <div style="font-size:16px;color:#C7D2FE;">+</div>
        <div style="text-align:center;padding:8px 12px;">
          <div style="font-size:15px;font-weight:800;color:#F59E0B;">+5%</div>
          <div style="font-size:10px;color:var(--rw-muted);">L1 commission</div>
        </div>
        <div style="font-size:20px;color:#C7D2FE;">→</div>
        <div style="text-align:center;padding:8px 12px;">
          <div style="font-size:13px;font-weight:700;color:#A78BFA;">+2% L2</div>
          <div style="font-size:10px;color:var(--rw-muted);">to grand-referrer</div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-md-3">
        <label class="rw-label">Base Reward (pts)</label>
        <input type="number" name="referral_reward_pts" class="rw-input" value="{{ $settings['referral_reward_pts'] ?? 500 }}" min="0" max="10000">
        <div class="rw-hint">Fixed pts on friend's first order</div>
      </div>
      <div class="col-md-3">
        <label class="rw-label">Level 1 Commission (%)</label>
        <input type="number" name="referral_commission_pct" class="rw-input" value="{{ $settings['referral_commission_pct'] ?? 5 }}" min="0" max="50">
        <div class="rw-hint">% of friend's order → pts</div>
      </div>
      <div class="col-md-3">
        <label class="rw-label">Level 2 Commission (%)</label>
        <input type="number" name="referral_l2_pct" class="rw-input" value="{{ $settings['referral_l2_pct'] ?? 2 }}" min="0" max="20">
        <div class="rw-hint">% to the person who referred the referrer</div>
      </div>
      <div class="col-md-3">
        <label class="rw-label">Min Order to Trigger ($)</label>
        <input type="number" name="referral_min_order" class="rw-input" value="{{ $settings['referral_min_order'] ?? 5 }}" min="0">
        <div class="rw-hint">Friend's order must be ≥ this</div>
      </div>
    </div>
  </div>

</div>{{-- end rw-card settings --}}

{{-- Sticky save bar --}}
<div class="rw-save-bar">
  <button type="submit" class="rw-btn-primary">💾 Save All Settings</button>
  <a href="{{ route('admin.dashboard') }}" class="rw-btn-ghost">Cancel</a>
  <span style="font-size:12px;color:var(--rw-muted);margin-left:auto;">Last saved: {{ now()->format('M d, Y H:i') }}</span>
</div>

</form>
</div>{{-- rw-wrap --}}
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Chart.js defaults ─────────────────────────────────────────
Chart.defaults.font.family = "'Inter','system-ui',sans-serif";
Chart.defaults.color = '#64748B';

// ── Points earned area chart ──────────────────────────────────
const ctxPts = document.getElementById('chartPts').getContext('2d');
const gradPts = ctxPts.createLinearGradient(0,0,0,200);
gradPts.addColorStop(0,'rgba(99,102,241,.25)');
gradPts.addColorStop(1,'rgba(99,102,241,.0)');

new Chart(ctxPts, {
  type: 'line',
  data: {
    labels: @json($chartLabels),
    datasets: [{
      label: 'Points Earned',
      data: @json($chartPts),
      borderColor: '#6366F1',
      backgroundColor: gradPts,
      borderWidth: 2.5,
      fill: true,
      tension: 0.4,
      pointRadius: 3,
      pointBackgroundColor: '#6366F1',
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => '⭐ '+ctx.parsed.y.toLocaleString()+' pts' }}},
    scales: {
      x: { grid: { color: '#F1F5F9' }, ticks: { maxRotation: 0, maxTicksLimit: 6 }},
      y: { grid: { color: '#F1F5F9' }, ticks: { callback: v => v >= 1000 ? (v/1000).toFixed(1)+'K' : v }},
    },
  }
});

// ── Tier donut chart ──────────────────────────────────────────
const ctxTier = document.getElementById('chartTier').getContext('2d');
new Chart(ctxTier, {
  type: 'doughnut',
  data: {
    labels: ['Bronze','Silver','Gold','Platinum'],
    datasets: [{
      data: [
        {{ $tierStats['bronze'] ?? 0 }},
        {{ $tierStats['silver'] ?? 0 }},
        {{ $tierStats['gold'] ?? 0 }},
        {{ $tierStats['platinum'] ?? 0 }},
      ],
      backgroundColor: ['#CD7F32','#A0A0A0','#F59E0B','#38BDF8'],
      borderWidth: 0,
      hoverOffset: 6,
    }]
  },
  options: {
    cutout: '70%',
    responsive: false,
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ctx.label+': '+ctx.parsed.toLocaleString() }}},
  }
});

// ── Tab switching ─────────────────────────────────────────────
document.querySelectorAll('#settingsTabs .rw-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.rw-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.rw-tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
  });
});

// ── Mult label updater ────────────────────────────────────────
document.querySelectorAll('.mult-input').forEach(input => {
  const update = () => {
    const lbl = document.getElementById(input.dataset.target);
    if (!lbl) return;
    const mult = parseFloat(input.value) / 10;
    lbl.textContent = mult.toFixed(1) + '×';
    lbl.style.background = mult > 1 ? '#DCFCE7' : mult < 1 ? '#FEE2E2' : '#EEF2FF';
    lbl.style.color = mult > 1 ? '#166534' : mult < 1 ? '#991B1B' : '#4F46E5';
  };
  input.addEventListener('input', update);
  update();
});

// ── Live calculator ───────────────────────────────────────────
const calcEarn   = document.getElementById('calc-earn');
const calcRedeem = document.getElementById('calc-redeem');
const ppdInput   = document.querySelector('[name="points_per_dollar"]');
const p2dInput   = document.querySelector('[name="points_to_dollar"]');

const updateCalc = () => {
  const ppd = parseFloat(ppdInput?.value) || 10;
  const p2d = parseFloat(p2dInput?.value) || 100;
  if (calcEarn)   calcEarn.textContent   = (10 * ppd).toLocaleString();
  if (calcRedeem) calcRedeem.textContent = '$' + (1000 / p2d).toFixed(2);
};
ppdInput?.addEventListener('input', updateCalc);
p2dInput?.addEventListener('input', updateCalc);
updateCalc();
</script>
@endpush
