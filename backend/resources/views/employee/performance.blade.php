@extends('employee.layouts.app')
@section('title', 'Performance-kayga')

@section('content')
<div style="display:flex;flex-direction:column;gap:24px;">

  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
      <h1 style="font-size:20px;font-weight:800;color:#111827;">📊 Performance-kayga</h1>
      <p style="font-size:13px;color:#6b7280;margin-top:4px;">Natiijahaaga module kasta — dhamaan xilliyada</p>
    </div>
    <form method="GET" style="display:flex;align-items:center;gap:8px;">
      <select name="period" onchange="this.form.submit()"
        style="font-size:13px;border:1.5px solid #e5e7eb;border-radius:10px;padding:8px 14px;background:#fff;cursor:pointer;">
        @foreach($periods as $val => $label)
          <option value="{{ $val }}" {{ $period === $val ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
    </form>
  </div>

  {{-- Overall score cards per module --}}
  @if(count($moduleDetails))
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;">
    @foreach($moduleDetails as $detail)
    @php
      $mod  = $detail['module'];
      $comp = $detail['composite'];
      $clr  = $comp>=90?'#16a34a':($comp>=75?'#2563eb':($comp>=60?'#d97706':'#dc2626'));
      $grade= $comp>=95?'A+':($comp>=90?'A':($comp>=85?'B+':($comp>=80?'B':($comp>=75?'C+':($comp>=70?'C':($comp>=60?'D':'F'))))));
    @endphp
    <div class="card" style="padding:20px;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <div style="display:flex;align-items:center;gap:10px;">
          <div style="width:36px;height:36px;border-radius:10px;background:{{ $mod->color ?? '#1B1444' }};display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-layer-group" style="color:#fff;font-size:14px;"></i>
          </div>
          <div>
            <div style="font-weight:700;font-size:14px;color:#111827;">{{ $mod->name }}</div>
            <div style="font-size:11px;color:#6b7280;">{{ $periods[$period] ?? $period }}</div>
          </div>
        </div>
        <div style="text-align:right;">
          @if($comp > 0)
            <div style="font-size:28px;font-weight:900;color:{{ $clr }}">{{ $comp }}%</div>
            <div style="font-size:16px;font-weight:800;color:{{ $clr }}">{{ $grade }}</div>
          @else
            <div style="font-size:24px;font-weight:900;color:#d1d5db;">—</div>
            <div style="font-size:12px;color:#9ca3af;">Data la'aan</div>
          @endif
        </div>
      </div>

      {{-- Progress bar --}}
      @if($comp > 0)
      <div style="background:#f3f4f6;border-radius:99px;height:8px;overflow:hidden;">
        <div style="height:8px;border-radius:99px;width:{{ $comp }}%;background:{{ $clr }};transition:width .4s;"></div>
      </div>
      @endif

      {{-- Per-metric mini list --}}
      @if($detail['metrics']->count())
      <div style="margin-top:14px;display:flex;flex-direction:column;gap:6px;">
        @foreach($detail['metrics'] as $m)
        @php $s = round($m->score ?? 0, 1); $mc = $s>=80?'#16a34a':($s>=60?'#d97706':'#dc2626'); @endphp
        <div style="display:flex;align-items:center;justify-content:space-between;font-size:12px;">
          <span style="color:#6b7280;">{{ $m->metric?->name ?? '—' }}</span>
          <span style="font-weight:700;color:{{ $mc }}">{{ $s }}%</span>
        </div>
        @endforeach
      </div>
      @endif
    </div>
    @endforeach
  </div>
  @else
  <div class="card" style="padding:48px;text-align:center;color:#9ca3af;">
    <i class="fas fa-chart-bar" style="font-size:32px;display:block;margin-bottom:12px;opacity:.3;"></i>
    <strong style="color:#6b7280;">{{ $periods[$period] ?? $period }} — xog la'aan</strong><br>
    <span style="font-size:13px;">HR ayaa metrics-ka keena marka shaqada la qaado.</span>
  </div>
  @endif

  {{-- 6-month trend --}}
  @if(count($trend))
  <div class="card">
    <div class="card-header">
      <span class="card-title">📈 Isbeddelka 6 Bilood</span>
    </div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:18px;">
      @foreach($trend as $slug => $trendData)
      @php
        $modData = collect($moduleDetails)->firstWhere('module.slug', $slug);
        $modName = $modData ? $modData['module']->name : strtoupper($slug);
        $modColor= $modData ? ($modData['module']->color ?? '#F7941D') : '#F7941D';
      @endphp
      <div>
        <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">{{ $modName }}</div>
        <div style="display:flex;align-items:flex-end;gap:4px;height:48px;">
          @foreach($trendData as $p => $score)
          @php $h = $score ? (int)(($score/100)*48) : 3; @endphp
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;">
            <div style="font-size:9px;color:#9ca3af;">{{ $score ? $score.'%' : '—' }}</div>
            <div style="width:100%;border-radius:4px 4px 0 0;background:{{ $score ? $modColor : '#e5e7eb' }};height:{{ $h }}px;opacity:{{ $score ? '.85' : '.3' }};transition:height .3s;"></div>
            <div style="font-size:9px;color:#9ca3af;">{{ \Carbon\Carbon::createFromFormat('Y-m',$p)->format('M') }}</div>
          </div>
          @endforeach
        </div>
      </div>
      @endforeach
    </div>
  </div>
  @endif

</div>
@endsection
