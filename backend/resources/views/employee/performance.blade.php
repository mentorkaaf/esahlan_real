@extends('employee.layouts.app')
@section('title', 'Performance-kayga')

@push('head')
<style>
/* ── Tabs ──────────────────────────────────────────────────── */
.perf-tabs {
  display:flex; gap:4px; background:#f3f4f6; border-radius:14px; padding:4px;
  flex-wrap:wrap;
}
.perf-tab {
  flex:1; min-width:120px; text-align:center; padding:9px 16px;
  border-radius:10px; font-size:12px; font-weight:700; cursor:pointer;
  border:none; background:transparent; color:#6b7280;
  text-decoration:none; display:inline-block; transition:all .15s;
}
.perf-tab.active { background:#fff; color:#1B1444; box-shadow:0 1px 6px rgba(0,0,0,.1); }
.perf-tab:hover:not(.active) { color:#1B1444; }

/* ── Overview ──────────────────────────────────────────────── */
.score-hero {
  background:linear-gradient(135deg,#1B1444 0%,#2d1e7a 100%);
  border-radius:20px; padding:28px 32px; color:#fff;
  display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:20px;
}
.score-num {
  font-size:56px; font-weight:900; line-height:1;
  color:#fff;
}
.score-grade {
  font-size:20px; font-weight:800; margin-top:4px;
}

.ring-wrap { position:relative; width:110px; height:110px; flex-shrink:0; }
.ring-wrap svg { width:110px; height:110px; transform:rotate(-90deg); }
.ring-bg  { fill:none; stroke:rgba(255,255,255,.15); stroke-width:10; }
.ring-bar { fill:none; stroke:#F7941D; stroke-width:10; stroke-linecap:round;
            transition:stroke-dashoffset .6s ease; }
.ring-label {
  position:absolute; inset:0; display:flex; flex-direction:column;
  align-items:center; justify-content:center; text-align:center;
}

/* ── Module cards ──────────────────────────────────────────── */
.mod-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
.mod-card { background:#fff; border:1.5px solid #e5e7eb; border-radius:16px; overflow:hidden; }
.mod-card-head { padding:16px 18px 12px; display:flex; align-items:center; justify-content:space-between; }
.mod-card-body { padding:0 18px 16px; }

.metric-row { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
.metric-label { font-size:11px; color:#6b7280; flex:1; }
.metric-bar-wrap { flex:2; height:6px; background:#f3f4f6; border-radius:99px; overflow:hidden; position:relative; }
.metric-bar { height:6px; border-radius:99px; transition:width .4s; }
.metric-team { height:6px; width:2px; background:rgba(0,0,0,.3); position:absolute; top:0; border-radius:1px; }
.metric-score { font-size:11px; font-weight:700; min-width:34px; text-align:right; }

/* ── Trend chart ───────────────────────────────────────────── */
.sparkline-wrap { background:#fff; border:1.5px solid #e5e7eb; border-radius:16px; padding:20px; }
.sparkline-row  { display:flex; align-items:flex-end; gap:6px; height:64px; margin:10px 0 4px; }
.spark-bar-col  { flex:1; display:flex; flex-direction:column; align-items:center; gap:2px; }
.spark-bar      { width:100%; border-radius:4px 4px 0 0; transition:height .4s; }
.spark-val      { font-size:9px; color:#9ca3af; }
.spark-month    { font-size:9px; color:#9ca3af; }

/* ── Goals ─────────────────────────────────────────────────── */
.goal-card {
  background:#fff; border:1.5px solid #e5e7eb; border-radius:14px;
  padding:18px 20px; margin-bottom:12px;
}
.goal-title  { font-size:14px; font-weight:700; color:#111827; margin-bottom:6px; }
.goal-desc   { font-size:12px; color:#6b7280; margin-bottom:12px; }
.goal-weight { font-size:11px; font-weight:700; color:#6b7280; }
.goal-progress-wrap { height:8px; background:#f3f4f6; border-radius:99px; overflow:hidden; margin-bottom:6px; }
.goal-progress-bar  { height:8px; border-radius:99px; transition:width .5s; }
.goal-vals { display:flex; justify-content:space-between; font-size:11px; color:#9ca3af; }

.status-pill {
  display:inline-flex; align-items:center; gap:4px;
  padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700;
}
.sp-pending    { background:#fef3c7; color:#92400e; }
.sp-in_progress{ background:#dbeafe; color:#1e40af; }
.sp-completed  { background:#d1fae5; color:#065f46; }

/* ── Reviews ───────────────────────────────────────────────── */
.review-card {
  background:#fff; border:1.5px solid #e5e7eb; border-radius:16px;
  overflow:hidden; margin-bottom:14px;
}
.review-header { padding:16px 20px 12px; display:flex; align-items:center; gap:14px; border-bottom:1px solid #f3f4f6; }
.review-body   { padding:16px 20px; }
.review-score-circle {
  width:56px; height:56px; border-radius:50%; flex-shrink:0;
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  font-weight:900; font-size:18px; border:3px solid;
}
.competency-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:10px; margin-top:12px; }
.comp-item { }
.comp-name  { font-size:11px; color:#6b7280; margin-bottom:4px; }
.comp-stars { display:flex; gap:2px; }
.star-on    { color:#F7941D; font-size:12px; }
.star-off   { color:#e5e7eb; font-size:12px; }

/* ── Commissions ───────────────────────────────────────────── */
.comm-period-block { margin-bottom:20px; }
.comm-period-head  {
  font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.07em;
  color:#374151; margin-bottom:8px; display:flex; align-items:center; gap:8px;
}
.comm-table { width:100%; border-collapse:collapse; background:#fff;
              border:1.5px solid #e5e7eb; border-radius:14px; overflow:hidden; }
.comm-table th { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.07em;
                 color:#9ca3af; padding:10px 14px; text-align:left; background:#f9fafb;
                 border-bottom:1px solid #e5e7eb; }
.comm-table th:last-child { text-align:right; }
.comm-table td { padding:11px 14px; font-size:12px; color:#374151; border-bottom:1px solid #f9fafb; }
.comm-table td:last-child { text-align:right; font-weight:700; }
.comm-table tr:last-child td { border-bottom:none; }
.comm-total-row td { font-weight:800; font-size:13px; color:#111827; background:#f9fafb;
                     border-top:2px solid #e5e7eb; }

.comm-status { display:inline-flex; align-items:center; gap:4px; padding:2px 9px;
               border-radius:20px; font-size:10px; font-weight:700; }
.cs-pending  { background:#fef3c7; color:#92400e; }
.cs-approved { background:#d1fae5; color:#065f46; }
.cs-included { background:#dbeafe; color:#1e40af; }
.cs-rejected { background:#fee2e2; color:#991b1b; }

/* ── Progress bars ─────────────────────────────────────────── */
.prog-bar { height:8px; background:#f3f4f6; border-radius:99px; overflow:hidden; }
.prog-fill { height:8px; border-radius:99px; }

/* ── Period picker ─────────────────────────────────────────── */
select.period-select {
  font-size:13px; border:1.5px solid #e5e7eb; border-radius:10px;
  padding:8px 14px; background:#fff; cursor:pointer; outline:none;
}

.empty-tab {
  text-align:center; padding:60px 20px; color:#9ca3af;
  background:#fff; border:1.5px dashed #e5e7eb; border-radius:16px;
}
.empty-tab i { font-size:36px; opacity:.3; display:block; margin-bottom:12px; }
</style>
@endpush

@section('content')
@php
  $grade = $overallScore>=95?'A+':($overallScore>=90?'A':($overallScore>=85?'B+':($overallScore>=80?'B':($overallScore>=75?'C+':($overallScore>=70?'C':($overallScore>=60?'D':'F'))))));
  $gradeColor = $overallScore>=80?'#6ee7b7':($overallScore>=65?'#fcd34d':'#fca5a5');
  $circumference = 2 * M_PI * 45; // r=45
  $dashOffset = $circumference - ($circumference * $overallScore / 100);

  $goalsDone = $goals->where('status','completed')->count();
  $goalsTotal = $goals->count();
  $goalWeight = $goals->where('status','completed')->sum('weight');
@endphp

{{-- ── Header ────────────────────────────────────────────────── --}}
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:20px;">
  <h1 style="font-size:20px;font-weight:900;color:#111827;">
    <i class="fas fa-chart-line" style="color:var(--brand);margin-right:8px;"></i>
    Performance-kayga
  </h1>
  <form method="GET">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <select name="period" class="period-select" onchange="this.form.submit()">
      @foreach($periods as $val => $label)
        <option value="{{ $val }}" {{ $period === $val ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
  </form>
</div>

{{-- ── Score Hero ─────────────────────────────────────────────── --}}
<div class="score-hero" style="margin-bottom:20px;">
  <div>
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.5);margin-bottom:6px;">
      Waxqaadka Guud — {{ $periods[$period] ?? $period }}
    </div>
    <div class="score-num">{{ $overallScore > 0 ? $overallScore.'%' : '—' }}</div>
    @if($overallScore > 0)
    <div class="score-grade" style="color:{{ $gradeColor }};">{{ $grade }}</div>
    @else
    <div style="font-size:13px;color:rgba(255,255,255,.4);margin-top:6px;">Xog la'aan</div>
    @endif
    <div style="margin-top:12px;display:flex;gap:20px;flex-wrap:wrap;">
      <div><div style="font-size:11px;color:rgba(255,255,255,.4);">Modules</div><div style="font-size:15px;font-weight:800;color:#fff;">{{ count($moduleDetails) }}</div></div>
      @if($goalsTotal > 0)
      <div><div style="font-size:11px;color:rgba(255,255,255,.4);">Goals Done</div><div style="font-size:15px;font-weight:800;color:#fff;">{{ $goalsDone }}/{{ $goalsTotal }}</div></div>
      @endif
      @if($commissionTotal > 0)
      <div><div style="font-size:11px;color:rgba(255,255,255,.4);">Commissions</div><div style="font-size:15px;font-weight:800;color:#6ee7b7;">${{ number_format($commissionTotal,2) }}</div></div>
      @endif
    </div>
  </div>

  {{-- Donut ring --}}
  @if($overallScore > 0)
  <div class="ring-wrap">
    <svg viewBox="0 0 110 110">
      <circle class="ring-bg"  cx="55" cy="55" r="45"/>
      <circle class="ring-bar" cx="55" cy="55" r="45"
        stroke-dasharray="{{ $circumference }}"
        stroke-dashoffset="{{ $dashOffset }}"
      />
    </svg>
    <div class="ring-label">
      <div style="font-size:20px;font-weight:900;color:#fff;">{{ $overallScore }}%</div>
      <div style="font-size:10px;color:rgba(255,255,255,.5);">Overall</div>
    </div>
  </div>
  @endif
</div>

{{-- ── Tabs ────────────────────────────────────────────────────── --}}
<div class="perf-tabs" style="margin-bottom:24px;">
  @foreach([
    ['overview',     'fas fa-chart-pie',     'Overview'],
    ['goals',        'fas fa-bullseye',       'Goals'.($goalsTotal ? ' ('.$goalsTotal.')' : '')],
    ['reviews',      'fas fa-star',           'Reviews'.($reviews->count() ? ' ('.$reviews->count().')' : '')],
    ['commissions',  'fas fa-coins',          'Commissions'.($commissions->count() ? ' ('.$commissions->count().')' : '')],
  ] as [$t,$ic,$lbl])
  <a href="?period={{ $period }}&tab={{ $t }}" class="perf-tab {{ $tab === $t ? 'active' : '' }}">
    <i class="{{ $ic }}" style="margin-right:5px;font-size:11px;"></i>{{ $lbl }}
  </a>
  @endforeach
</div>

{{-- ══ TAB: OVERVIEW ══════════════════════════════════════════════ --}}
@if($tab === 'overview')

  @if(empty($moduleDetails))
    <div class="empty-tab">
      <i class="fas fa-chart-bar"></i>
      <p>{{ $periods[$period] ?? $period }} — xog la'aan.</p>
      <p style="font-size:12px;margin-top:6px;">HR ayaa metrics-ka keena marka shaqada la qaado.</p>
    </div>
  @else

  {{-- Module score cards --}}
  <div class="mod-grid" style="margin-bottom:24px;">
    @foreach($moduleDetails as $detail)
    @php
      $mod  = $detail['module'];
      $comp = $detail['composite'];
      $color = $mod->color ?? '#1B1444';
      $sc = $comp>=80?'#059669':($comp>=65?'#d97706':'#dc2626');
      $grade2 = $comp>=95?'A+':($comp>=90?'A':($comp>=85?'B+':($comp>=80?'B':($comp>=75?'C+':($comp>=70?'C':($comp>=60?'D':'F'))))));
    @endphp
    <div class="mod-card">
      <div class="mod-card-head">
        <div style="display:flex;align-items:center;gap:10px;">
          <div style="width:36px;height:36px;border-radius:10px;background:{{ $color }};display:flex;align-items:center;justify-content:center;">
            <i class="{{ $mod->icon ?? 'fas fa-layer-group' }}" style="color:#fff;font-size:14px;"></i>
          </div>
          <div>
            <div style="font-weight:700;font-size:13px;color:#111827;">{{ $mod->name }}</div>
            <div style="font-size:10px;color:#9ca3af;">{{ $periods[$period] ?? $period }}</div>
          </div>
        </div>
        <div style="text-align:right;">
          @if($comp > 0)
            <div style="font-size:26px;font-weight:900;color:{{ $sc }};">{{ $comp }}%</div>
            <div style="font-size:14px;font-weight:800;color:{{ $sc }};">{{ $grade2 }}</div>
          @else
            <div style="font-size:24px;font-weight:700;color:#d1d5db;">—</div>
            <div style="font-size:11px;color:#9ca3af;">La'aan</div>
          @endif
        </div>
      </div>

      @if($comp > 0)
      <div class="mod-card-body">
        {{-- Overall bar --}}
        <div class="prog-bar" style="margin-bottom:12px;">
          <div class="prog-fill" style="width:{{ $comp }}%;background:{{ $sc }};"></div>
        </div>

        {{-- Per-metric rows with team avg --}}
        @foreach($detail['metrics'] as $m)
        @php
          $ms    = round($m->score ?? 0, 1);
          $mc    = $ms>=80?'#059669':($ms>=60?'#d97706':'#dc2626');
          $teamRow = $detail['teamAvg'][$m->metric_id] ?? null;
          $teamPct = $teamRow ? round($teamRow['avg_score'] ?? 0, 1) : null;
        @endphp
        <div class="metric-row">
          <span class="metric-label">{{ $m->metric?->name ?? '—' }}</span>
          <div class="metric-bar-wrap">
            <div class="metric-bar" style="width:{{ $ms }}%;background:{{ $mc }};"></div>
            @if($teamPct !== null)
            <div class="metric-team" style="left:{{ $teamPct }}%;"></div>
            @endif
          </div>
          <span class="metric-score" style="color:{{ $mc }};">{{ $ms }}%
            @if($teamPct !== null)
            <span style="font-size:9px;color:#9ca3af;font-weight:400;"> / {{ $teamPct }}%</span>
            @endif
          </span>
        </div>
        @endforeach
      </div>
      @endif
    </div>
    @endforeach
  </div>

  {{-- Trend sparkline --}}
  @if(count($trend))
  <div class="sparkline-wrap">
    <div style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:#6b7280;margin-bottom:16px;">
      <i class="fas fa-chart-area" style="color:var(--brand);margin-right:6px;"></i>
      Isbeddelka 12 Bilood
    </div>
    @foreach($trend as $slug => $trendData)
    @php
      $modData  = collect($moduleDetails)->firstWhere('module.slug', $slug);
      $modName  = $modData ? $modData['module']->name : strtoupper($slug);
      $modColor = $modData ? ($modData['module']->color ?? '#F7941D') : '#F7941D';
      $vals     = array_values($trendData);
      $maxVal   = max(array_merge([1], $vals));
    @endphp
    <div style="margin-bottom:20px;">
      <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:6px;display:flex;align-items:center;gap:8px;">
        <span style="width:10px;height:10px;border-radius:3px;background:{{ $modColor }};display:inline-block;"></span>
        {{ $modName }}
      </div>
      <div class="sparkline-row">
        @foreach($trendData as $p => $score)
        @php $h = $score ? max(4, (int)(($score/$maxVal)*60)) : 4; @endphp
        <div class="spark-bar-col">
          <div class="spark-val">{{ $score ? $score.'%' : '—' }}</div>
          <div class="spark-bar" style="height:{{ $h }}px;background:{{ $score ? $modColor : '#e5e7eb' }};opacity:{{ $score ? '.85' : '.3' }};"></div>
          <div class="spark-month">{{ \Carbon\Carbon::createFromFormat('Y-m',$p)->format('M') }}</div>
        </div>
        @endforeach
      </div>
    </div>
    @endforeach
  </div>
  @endif

  @endif

{{-- ══ TAB: GOALS ═════════════════════════════════════════════════ --}}
@elseif($tab === 'goals')

@if(!$activeCycle)
  <div class="empty-tab">
    <i class="fas fa-bullseye"></i>
    <p>Active performance cycle la'aan.</p>
    <p style="font-size:12px;margin-top:6px;">HR-ga la xiriir si cycle la bilaabo.</p>
  </div>
@elseif($goals->isEmpty())
  <div class="empty-tab">
    <i class="fas fa-bullseye"></i>
    <p>Goals wali lagugu darinwaayo cycle-kan.</p>
    <p style="font-size:12px;margin-top:6px;">Cycle: <strong>{{ $activeCycle->name }}</strong></p>
  </div>
@else

  {{-- Cycle info + progress --}}
  <div style="background:#fff;border:1.5px solid #e5e7eb;border-radius:16px;padding:18px 20px;margin-bottom:20px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
    <div style="flex:1;">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#9ca3af;">Active Cycle</div>
      <div style="font-size:16px;font-weight:800;color:#111827;margin-top:3px;">{{ $activeCycle->name }}</div>
      <div style="font-size:12px;color:#6b7280;margin-top:3px;">
        {{ \Carbon\Carbon::parse($activeCycle->period_start)->format('d M Y') }}
        → {{ \Carbon\Carbon::parse($activeCycle->period_end)->format('d M Y') }}
      </div>
    </div>
    <div style="text-align:center;">
      <div style="font-size:28px;font-weight:900;color:{{ $goalsDone === $goalsTotal && $goalsTotal > 0 ? '#059669' : '#1B1444' }};">{{ $goalsDone }}/{{ $goalsTotal }}</div>
      <div style="font-size:11px;color:#6b7280;">Goals Done</div>
    </div>
    <div style="min-width:180px;">
      <div style="font-size:11px;color:#6b7280;margin-bottom:5px;">Weight Completed</div>
      <div class="prog-bar">
        <div class="prog-fill" style="width:{{ $goalWeight }}%;background:#059669;"></div>
      </div>
      <div style="font-size:11px;color:#374151;margin-top:4px;font-weight:700;">{{ number_format($goalWeight,1) }}%</div>
    </div>
  </div>

  {{-- Goal cards --}}
  @foreach($goals as $goal)
  @php
    $statusClass = 'sp-' . $goal->status;
    $statusLabel = ['pending'=>'Sugaya','in_progress'=>'Socda','completed'=>'Dhammaatay'][$goal->status] ?? $goal->status;
    $achievedNum = is_numeric($goal->achieved_value) ? (float)$goal->achieved_value : null;
    $targetNum   = is_numeric($goal->target_value) ? (float)$goal->target_value : null;
    $pct = ($achievedNum !== null && $targetNum > 0) ? min(100, round($achievedNum/$targetNum*100)) : null;
    $barColor = $goal->status === 'completed' ? '#059669' : ($goal->status === 'in_progress' ? '#2563eb' : '#e5e7eb');
  @endphp
  <div class="goal-card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:8px;">
      <div class="goal-title">{{ $goal->title }}</div>
      <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
        <span class="status-pill {{ $statusClass }}">{{ $statusLabel }}</span>
        <span style="font-size:11px;font-weight:700;background:#f3f4f6;border-radius:20px;padding:2px 10px;color:#374151;">
          {{ number_format($goal->weight,1) }}%
        </span>
      </div>
    </div>
    @if($goal->description)
    <div class="goal-desc">{{ $goal->description }}</div>
    @endif

    @if($targetNum !== null)
    <div class="goal-progress-wrap">
      <div class="goal-progress-bar" style="width:{{ $pct ?? 0 }}%;background:{{ $barColor }};"></div>
    </div>
    <div class="goal-vals">
      <span>Gaadhay: <strong>{{ $goal->achieved_value ?? '—' }}</strong></span>
      <span>{{ $pct !== null ? $pct.'%' : '' }}</span>
      <span>Hadafka: <strong>{{ $goal->target_value }}</strong></span>
    </div>
    @elseif($goal->status === 'completed')
    <div style="font-size:12px;color:#059669;margin-top:4px;"><i class="fas fa-check-circle"></i> Dhammaatay</div>
    @endif
  </div>
  @endforeach

@endif

{{-- ══ TAB: REVIEWS ═══════════════════════════════════════════════ --}}
@elseif($tab === 'reviews')

@if($reviews->isEmpty())
  <div class="empty-tab">
    <i class="fas fa-star"></i>
    <p>Wali review la submit garaysanwaayo.</p>
    <p style="font-size:12px;margin-top:6px;">HR-ka ayaa reviews-ka keena marka performance cycle la dhammeeyaa.</p>
  </div>
@else
  @foreach($reviews as $review)
  @php
    $os = (float)($review->overall_score ?? 0);
    $osColor = $os >= 4 ? '#059669' : ($os >= 3 ? '#d97706' : '#dc2626');
    $osPct = round($os / 5 * 100);
  @endphp
  <div class="review-card">
    <div class="review-header">
      <div class="review-score-circle" style="color:{{ $osColor }};border-color:{{ $osColor }};">
        <div>{{ $os > 0 ? number_format($os,1) : '—' }}</div>
        <div style="font-size:9px;font-weight:500;color:#9ca3af;">/5</div>
      </div>
      <div style="flex:1;">
        <div style="font-size:14px;font-weight:800;color:#111827;">{{ $review->cycle?->name ?? 'Review' }}</div>
        <div style="font-size:12px;color:#6b7280;margin-top:2px;">
          {{ $review->cycle ? \Carbon\Carbon::parse($review->cycle->period_start)->format('M Y').' — '.\Carbon\Carbon::parse($review->cycle->period_end)->format('M Y') : '' }}
        </div>
        <div style="font-size:11px;color:#9ca3af;margin-top:3px;">
          <i class="fas fa-user" style="font-size:9px;"></i>
          Reviewer: {{ $review->reviewer?->full_name ?? 'HR' }}
          @if($review->submitted_at)
          · Submitted: {{ $review->submitted_at->format('d M Y') }}
          @endif
        </div>
      </div>
      @if($os > 0)
      <div style="min-width:100px;">
        <div style="font-size:10px;color:#9ca3af;margin-bottom:4px;">Overall</div>
        <div class="prog-bar">
          <div class="prog-fill" style="width:{{ $osPct }}%;background:{{ $osColor }};"></div>
        </div>
        <div style="font-size:10px;color:{{ $osColor }};font-weight:700;margin-top:3px;">{{ $osPct }}%</div>
      </div>
      @endif
    </div>

    <div class="review-body">
      {{-- Competency scores --}}
      @php $comps = $review->competency_scores ?? []; @endphp
      @if(!empty($comps))
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#9ca3af;margin-bottom:10px;">Competency-yada</div>
      <div class="competency-grid">
        @foreach($comps as $compName => $compScore)
        <div class="comp-item">
          <div class="comp-name">{{ ucwords(str_replace('_',' ',$compName)) }}</div>
          <div class="comp-stars">
            @for($s=1;$s<=5;$s++)
            <i class="{{ $s <= $compScore ? 'fas fa-star star-on' : 'far fa-star star-off' }}"></i>
            @endfor
            <span style="font-size:10px;color:#6b7280;margin-left:4px;">{{ $compScore }}/5</span>
          </div>
        </div>
        @endforeach
      </div>
      @endif

      {{-- Notes --}}
      @if($review->notes)
      <div style="margin-top:14px;background:#f9fafb;border-left:3px solid var(--brand);border-radius:0 8px 8px 0;padding:10px 14px;font-size:12px;color:#374151;font-style:italic;">
        "{{ $review->notes }}"
      </div>
      @endif
    </div>
  </div>
  @endforeach
@endif

{{-- ══ TAB: COMMISSIONS ═══════════════════════════════════════════ --}}
@elseif($tab === 'commissions')

@if($commissions->isEmpty())
  <div class="empty-tab">
    <i class="fas fa-coins"></i>
    <p>Wali commission xog la'aan (6 bilood ugu dambeeyay).</p>
  </div>
@else

  {{-- Summary bar --}}
  <div style="background:#fff;border:1.5px solid #e5e7eb;border-radius:16px;padding:18px 22px;margin-bottom:20px;display:flex;gap:28px;flex-wrap:wrap;align-items:center;">
    <div>
      <div style="font-size:11px;color:#9ca3af;font-weight:700;text-transform:uppercase;letter-spacing:.07em;">Approved / Included</div>
      <div style="font-size:26px;font-weight:900;color:#059669;margin-top:3px;">${{ number_format($commissionTotal,2) }}</div>
    </div>
    <div>
      <div style="font-size:11px;color:#9ca3af;font-weight:700;text-transform:uppercase;letter-spacing:.07em;">Pending</div>
      <div style="font-size:20px;font-weight:800;color:#d97706;margin-top:3px;">${{ number_format($commissions->where('status','pending')->sum('amount'),2) }}</div>
    </div>
    <div>
      <div style="font-size:11px;color:#9ca3af;font-weight:700;text-transform:uppercase;letter-spacing:.07em;">Records</div>
      <div style="font-size:20px;font-weight:800;color:#374151;margin-top:3px;">{{ $commissions->count() }}</div>
    </div>
  </div>

  @foreach($commissionByPeriod as $per => $rows)
  @php $periodTotal = $rows->whereIn('status',['approved','included'])->sum('amount'); @endphp
  <div class="comm-period-block">
    <div class="comm-period-head">
      <i class="fas fa-calendar-alt" style="color:var(--brand);font-size:11px;"></i>
      {{ \Carbon\Carbon::createFromFormat('Y-m', $per)->format('F Y') }}
      @if($periodTotal > 0)
      <span style="font-size:11px;color:#059669;font-weight:700;margin-left:auto;">${{ number_format($periodTotal,2) }}</span>
      @endif
    </div>
    <table class="comm-table">
      <thead>
        <tr>
          <th>Nooca</th><th>Sharaxaad</th>
          <th>Hadaf</th><th>Gaadhay</th>
          <th>Rate</th><th>Xaalad</th><th>Lacagta</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $c)
        <tr>
          <td style="font-weight:600;">{{ $c->type_label }}</td>
          <td>{{ $c->description ?? '—' }}</td>
          <td>{{ $c->target }}</td>
          <td>
            {{ $c->achieved }}
            @if($c->target > 0)
            <span style="font-size:10px;color:#9ca3af;">({{ round($c->achieved/$c->target*100) }}%)</span>
            @endif
          </td>
          <td style="font-family:monospace;font-size:11px;">{{ $c->rate }}</td>
          <td>
            <span class="comm-status cs-{{ $c->status }}">
              {{ ['pending'=>'Sugaya','approved'=>'OK','included'=>'Payslip','rejected'=>'Diiday'][$c->status] ?? $c->status }}
            </span>
          </td>
          <td style="color:{{ in_array($c->status,['approved','included']) ? '#059669' : '#374151' }};">
            ${{ number_format($c->amount,2) }}
          </td>
        </tr>
        @endforeach
        @if($rows->count() > 1)
        <tr class="comm-total-row">
          <td colspan="6">Urursan</td>
          <td>${{ number_format($rows->sum('amount'),2) }}</td>
        </tr>
        @endif
      </tbody>
    </table>
  </div>
  @endforeach

@endif

@endif
{{-- ══ END TABS ══════════════════════════════════════════════════ --}}

@endsection
