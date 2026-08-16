@extends('hr.layouts.app')
@section('title', $module->name . ' Performance')

@section('content')
<div class="space-y-6">

  {{-- Header --}}
  <div class="flex items-center justify-between">
    <div class="flex items-center gap-4">
      <a href="{{ route('hr.performance.analytics') }}" class="text-gray-400 hover:text-gray-600">
        <i class="fas fa-arrow-left"></i>
      </a>
      <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white text-lg"
           style="background:{{ $module->color ?? '#1B1444' }}">
        <i class="{{ $module->icon ?? 'fas fa-layer-group' }}"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $module->name }} Performance</h1>
        <p class="text-sm text-gray-500">{{ $employees->count() }} active employees · {{ $metrics->count() }} metrics</p>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <form method="GET" class="flex items-center gap-2">
        <select name="period" onchange="this.form.submit()"
          class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#F7941D]">
          @foreach($periods as $val => $label)
            <option value="{{ $val }}" {{ $period === $val ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </form>
    </div>
  </div>

  {{-- Trend bar (last 6 months) --}}
  @if(array_filter($trend))
  <div class="bg-white rounded-xl border border-gray-200 p-5">
    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Team Average Trend (6 months)</h2>
    <div class="flex items-end gap-2 h-16">
      @foreach($trend as $p => $avg)
      @php $h = $avg ? (int)(($avg/100)*64) : 4; @endphp
      <div class="flex-1 flex flex-col items-center gap-1">
        <div class="text-xs text-gray-500">{{ $avg ? $avg.'%' : '—' }}</div>
        <div class="w-full rounded-t-sm transition-all"
          style="height:{{ $h }}px; background:{{ $avg ? ($module->color ?? '#F7941D') : '#e5e7eb' }}; opacity:{{ $avg ? '0.85' : '0.3' }}">
        </div>
        <div class="text-xs text-gray-400">{{ \Carbon\Carbon::createFromFormat('Y-m',$p)->format('M') }}</div>
      </div>
      @endforeach
    </div>
  </div>
  @endif

  {{-- Metrics team averages --}}
  @if($metrics->count())
  <div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-4">Metric Team Averages — {{ $periods[$period] ?? $period }}</h2>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
      @foreach($metrics as $metric)
      @php $avg = $teamAvg[$metric->id] ?? null; $avgScore = $avg ? round($avg['avg_score'],1) : null; @endphp
      <div class="bg-gray-50 rounded-lg p-4 text-center">
        <div class="text-xs text-gray-500 mb-1">{{ $metric->name }}</div>
        @if($avgScore !== null)
          <div class="text-2xl font-black {{ $avgScore >= 80 ? 'text-green-600' : ($avgScore >= 60 ? 'text-yellow-600' : 'text-red-500') }}">
            {{ $avgScore }}%
          </div>
          <div class="text-xs text-gray-400 mt-1">
            {{ $avg['min_score'] }}–{{ $avg['max_score'] }} range
          </div>
          <div class="text-xs text-gray-300">{{ $avg['employee_count'] }} recorded</div>
        @else
          <div class="text-2xl text-gray-200 font-black">—</div>
          <div class="text-xs text-gray-300">no data</div>
        @endif
      </div>
      @endforeach
    </div>
  </div>
  @endif

  {{-- Leaderboard --}}
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
      <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Employee Scores — {{ $periods[$period] ?? $period }}</h2>
    </div>
    @if($employees->isEmpty())
      <div class="py-12 text-center text-gray-300">
        <i class="fas fa-users text-3xl block mb-3"></i>
        No active employees in {{ $module->name }}
      </div>
    @else
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
        <tr>
          <th class="text-left px-6 py-3 font-medium">#</th>
          <th class="text-left px-4 py-3 font-medium">Employee</th>
          @foreach($metrics as $metric)
          <th class="text-center px-2 py-3 font-medium text-xs">{{ Str::limit($metric->name,12) }}</th>
          @endforeach
          <th class="text-center px-4 py-3 font-medium">Score</th>
          <th class="text-right px-6 py-3 font-medium">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        @php $rank = 1; @endphp
        @foreach($compositeScores as $empId => $composite)
        @php $emp = $employees->firstWhere('id', $empId); if(!$emp) continue; @endphp
        <tr class="hover:bg-gray-50 transition-colors">
          <td class="px-6 py-3 text-gray-400 font-mono text-xs">
            {{ $rank <= 3 ? ['🥇','🥈','🥉'][$rank-1] : $rank }}
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-full bg-[#1B1444] text-white text-xs font-bold flex items-center justify-center">
                {{ strtoupper(substr($emp->first_name,0,1).substr($emp->last_name,0,1)) }}
              </div>
              <div>
                <div class="font-medium text-gray-800">{{ $emp->full_name }}</div>
                <div class="text-xs text-gray-400">{{ $emp->employee_no }}</div>
              </div>
            </div>
          </td>
          @foreach($metrics as $metric)
          @php $m = ($measurements[$empId] ?? collect())->firstWhere('metric_id', $metric->id); @endphp
          <td class="text-center px-2 py-3 text-xs">
            @if($m)
              @php $sc = round($m->score ?? 0, 1); @endphp
              <div class="{{ $sc>=80?'text-green-600':($sc>=60?'text-yellow-600':'text-red-500') }} font-semibold">
                {{ $sc }}%
              </div>
              <div class="text-gray-300">{{ $m->actual_value }}{{ $metric->unit_label }}</div>
            @else
              <span class="text-gray-200">—</span>
            @endif
          </td>
          @endforeach
          <td class="text-center px-4 py-3">
            @if($composite > 0)
            @php
              $color = $composite >= 90 ? '#16a34a' : ($composite >= 75 ? '#2563eb' : ($composite >= 60 ? '#d97706' : '#dc2626'));
            @endphp
            <span class="text-base font-black" style="color:{{ $color }}">{{ $composite }}%</span>
            @else
            <span class="text-gray-200 text-sm">no data</span>
            @endif
          </td>
          <td class="text-right px-6 py-3">
            <a href="{{ route('hr.performance.employee', [$module->slug, $emp]) }}"
              class="text-xs bg-[#F7941D] text-white px-3 py-1.5 rounded-lg hover:bg-orange-600 transition-colors">
              Record / View
            </a>
          </td>
        </tr>
        @php $rank++; @endphp
        @endforeach
        {{-- employees without scores --}}
        @foreach($employees as $emp)
        @if(!isset($compositeScores[$emp->id]))
        <tr class="hover:bg-gray-50 transition-colors opacity-50">
          <td class="px-6 py-3 text-gray-300 text-xs">—</td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-400 text-xs font-bold flex items-center justify-center">
                {{ strtoupper(substr($emp->first_name,0,1).substr($emp->last_name,0,1)) }}
              </div>
              <div class="font-medium text-gray-500">{{ $emp->full_name }}</div>
            </div>
          </td>
          @foreach($metrics as $m)
          <td class="text-center px-2 py-3"><span class="text-gray-200">—</span></td>
          @endforeach
          <td class="text-center px-4 py-3"><span class="text-gray-200 text-sm">no data</span></td>
          <td class="text-right px-6 py-3">
            <a href="{{ route('hr.performance.employee', [$module->slug, $emp]) }}"
              class="text-xs border border-gray-200 text-gray-500 px-3 py-1.5 rounded-lg hover:border-[#F7941D] hover:text-[#F7941D]">
              Record
            </a>
          </td>
        </tr>
        @endif
        @endforeach
      </tbody>
    </table>
    @endif
  </div>

</div>
@endsection
