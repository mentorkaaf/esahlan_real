@extends('hr.layouts.app')
@section('title', 'Module Performance Analytics')

@section('content')
<div class="space-y-6">

  {{-- Header --}}
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">📊 Module Performance Analytics</h1>
      <p class="text-sm text-gray-500 mt-0.5">Cross-module performance — who's excelling where</p>
    </div>
    <form method="GET" class="flex items-center gap-2">
      <select name="period" onchange="this.form.submit()"
        class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#F7941D] focus:border-transparent">
        @foreach($periods as $val => $label)
          <option value="{{ $val }}" {{ $period === $val ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
    </form>
  </div>

  {{-- Module averages bar --}}
  @if($moduleAverages->count())
  <div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Module Team Averages</h2>
    <div class="space-y-3">
      @foreach($moduleAverages as $row)
      @php $avg = $row['average']; $mod = $row['module']; @endphp
      <div class="flex items-center gap-3">
        <div class="w-28 text-right">
          <span class="text-xs font-medium text-gray-600">{{ $mod->name }}</span>
        </div>
        <div class="flex-1 bg-gray-100 rounded-full h-5 overflow-hidden">
          <div class="h-5 rounded-full flex items-center justify-end pr-2 transition-all"
            style="width:{{ $avg }}%; background:{{ $mod->color ?? '#F7941D' }}">
            <span class="text-white text-xs font-bold">{{ $avg }}%</span>
          </div>
        </div>
        <div class="w-16 text-xs text-gray-400">{{ $row['count'] }} emp</div>
      </div>
      @endforeach
    </div>
  </div>
  @endif

  {{-- Global Top 5 --}}
  @if($globalTop->count())
  <div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">🏆 Top Performers — {{ $periods[$period] ?? $period }}</h2>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
      @foreach($globalTop as $i => $row)
      @php $emp = $row['employee']; $overall = $row['overall']; @endphp
      <div class="relative text-center p-4 rounded-xl border-2 {{ $i === 0 ? 'border-yellow-400 bg-yellow-50' : 'border-gray-100 bg-gray-50' }}">
        @if($i === 0)<div class="absolute -top-2 left-1/2 -translate-x-1/2 text-base">🥇</div>@endif
        @if($i === 1)<div class="absolute -top-2 left-1/2 -translate-x-1/2 text-base">🥈</div>@endif
        @if($i === 2)<div class="absolute -top-2 left-1/2 -translate-x-1/2 text-base">🥉</div>@endif
        <div class="w-12 h-12 rounded-full bg-[#1B1444] text-white flex items-center justify-center text-lg font-bold mx-auto mb-2">
          {{ strtoupper(substr($emp->first_name,0,1).substr($emp->last_name,0,1)) }}
        </div>
        <div class="text-sm font-semibold text-gray-800 leading-tight">{{ $emp->full_name }}</div>
        <div class="text-2xl font-black mt-1" style="color:#F7941D">{{ $overall }}%</div>
        <div class="text-xs text-gray-400 mt-0.5">{{ count($row['moduleScores']) }} modules</div>
        <a href="{{ route('hr.employees.show', $emp) }}"
          class="mt-2 text-xs text-[#F7941D] hover:underline block">View Profile →</a>
      </div>
      @endforeach
    </div>
  </div>
  @endif

  {{-- Employee Multi-Module Table (the "Ahmed Hassan" view) --}}
  <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Employee × Module Scores</h2>
      <span class="text-xs text-gray-400">{{ $employeeScores->count() }} employees with data</span>
    </div>

    @if($employeeScores->isEmpty())
      <div class="py-16 text-center text-gray-400">
        <i class="fas fa-chart-bar text-3xl mb-3 block opacity-30"></i>
        No performance data recorded for {{ $periods[$period] ?? $period }}.<br>
        <span class="text-sm">Go to a module workforce page → record metrics.</span>
      </div>
    @else
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
          <tr>
            <th class="text-left px-6 py-3 font-medium">Employee</th>
            @foreach($modules as $mod)
            <th class="text-center px-2 py-3 font-medium" title="{{ $mod->name }}">
              <span class="inline-block px-2 py-0.5 rounded text-white text-xs" style="background:{{ $mod->color ?? '#888' }}">
                {{ Str::limit($mod->name, 8, '') }}
              </span>
            </th>
            @endforeach
            <th class="text-center px-4 py-3 font-medium">Overall</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @foreach($employeeScores as $row)
          @php $emp = $row['employee']; @endphp
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-6 py-3">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-[#1B1444] text-white text-xs font-bold flex items-center justify-center flex-shrink-0">
                  {{ strtoupper(substr($emp->first_name,0,1).substr($emp->last_name,0,1)) }}
                </div>
                <div>
                  <a href="{{ route('hr.employees.show', $emp) }}"
                    class="font-medium text-gray-800 hover:text-[#F7941D]">{{ $emp->full_name }}</a>
                  <div class="text-xs text-gray-400">{{ $emp->employee_id }}</div>
                </div>
              </div>
            </td>
            @foreach($modules as $mod)
            @php $score = $row['moduleScores'][$mod->slug] ?? null; @endphp
            <td class="text-center px-2 py-3">
              @if($score !== null)
                @php
                  $bg = $score >= 90 ? 'bg-green-100 text-green-700'
                      : ($score >= 75 ? 'bg-blue-100 text-blue-700'
                      : ($score >= 60 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'));
                @endphp
                <a href="{{ route('hr.performance.employee', [$mod->slug, $emp]) }}"
                  class="inline-block px-2 py-0.5 rounded text-xs font-bold {{ $bg }} hover:opacity-80">
                  {{ $score }}%
                </a>
              @else
                <span class="text-gray-200">—</span>
              @endif
            </td>
            @endforeach
            <td class="text-center px-4 py-3">
              @if($row['overall'] !== null)
                <span class="text-base font-black" style="color:#F7941D">{{ $row['overall'] }}%</span>
              @else
                <span class="text-gray-200">—</span>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

  {{-- Top performer per module --}}
  @if(count($topPerModule))
  <div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">⭐ Best in Each Module</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      @foreach($modules as $mod)
      @php $top = $topPerModule[$mod->slug] ?? null; @endphp
      @if($top)
      <div class="rounded-lg p-3 border" style="border-color:{{ $mod->color ?? '#ccc' }}20; background:{{ $mod->color ?? '#ccc' }}08">
        <div class="text-xs font-semibold mb-2" style="color:{{ $mod->color ?? '#888' }}">{{ $mod->name }}</div>
        <div class="font-medium text-gray-800 text-sm truncate">
          {{ $top->employee->full_name ?? 'Unknown' }}
        </div>
        <div class="text-lg font-black mt-0.5" style="color:{{ $mod->color ?? '#F7941D' }}">
          {{ round($top->avg_score, 1) }}%
        </div>
      </div>
      @endif
      @endforeach
    </div>
  </div>
  @endif

</div>
@endsection
