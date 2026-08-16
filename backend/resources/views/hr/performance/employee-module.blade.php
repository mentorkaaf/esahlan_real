@extends('hr.layouts.app')
@section('title', $employee->full_name . ' — ' . $module->name . ' Performance')

@section('content')
<div class="space-y-6">

  {{-- Breadcrumb --}}
  <div class="flex items-center gap-2 text-sm text-gray-400">
    <a href="{{ route('hr.performance.analytics') }}" class="hover:text-[#F7941D]">Analytics</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <a href="{{ route('hr.performance.module', $module->slug) }}" class="hover:text-[#F7941D]">{{ $module->name }}</a>
    <i class="fas fa-chevron-right text-xs"></i>
    <span class="text-gray-600">{{ $employee->full_name }}</span>
  </div>

  {{-- Profile header --}}
  <div class="bg-white rounded-xl border border-gray-200 p-6">
    <div class="flex flex-col md:flex-row md:items-center gap-6">
      {{-- Avatar --}}
      <div class="w-16 h-16 rounded-2xl text-white text-2xl font-black flex items-center justify-center flex-shrink-0"
           style="background:{{ $module->color ?? '#1B1444' }}">
        {{ strtoupper(substr($employee->first_name,0,1).substr($employee->last_name,0,1)) }}
      </div>
      {{-- Info --}}
      <div class="flex-1">
        <div class="flex items-start justify-between">
          <div>
            <h1 class="text-xl font-bold text-gray-900">{{ $employee->full_name }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $employee->employee_id }} · {{ $employee->position ?? 'N/A' }}</p>
          </div>
          <a href="{{ route('hr.employees.show', $employee) }}"
            class="text-sm text-gray-400 hover:text-[#F7941D] border border-gray-200 rounded-lg px-3 py-1.5">
            <i class="fas fa-user text-xs mr-1"></i> Profile
          </a>
        </div>
        {{-- Cross-module scores (Ahmed Hassan effect) --}}
        @if(count($allModuleScores))
        <div class="mt-3 flex flex-wrap gap-2">
          @foreach($allModuleScores as $slug => $score)
          @php
            $isCurrent = $slug === $module->slug;
            $color = $score >= 90 ? '#16a34a' : ($score >= 75 ? '#2563eb' : ($score >= 60 ? '#d97706' : '#dc2626'));
          @endphp
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border
            {{ $isCurrent ? 'border-[#F7941D] bg-orange-50' : 'border-gray-100 bg-gray-50 text-gray-500' }}">
            <span style="color:{{ $color }}; font-weight:800">{{ $score }}%</span>
            <span>{{ Str::upper($slug) }}</span>
            @if($isCurrent)<i class="fas fa-star text-[#F7941D] text-xs"></i>@endif
          </span>
          @endforeach
        </div>
        @endif
      </div>
      {{-- Composite score --}}
      <div class="text-center p-4 bg-gray-50 rounded-xl min-w-[100px]">
        <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">{{ $module->name }}</div>
        @if($composite > 0)
        @php $clr = $composite>=90?'#16a34a':($composite>=75?'#2563eb':($composite>=60?'#d97706':'#dc2626')); @endphp
        <div class="text-4xl font-black" style="color:{{ $clr }}">{{ $composite }}%</div>
        <div class="text-xs text-gray-400 mt-0.5">{{ $periods[$period] ?? $period }}</div>
        @else
        <div class="text-2xl text-gray-200 font-black">—</div>
        <div class="text-xs text-gray-300">no data</div>
        @endif
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Metric entry form --}}
    <div class="lg:col-span-2">
      <div class="bg-white rounded-xl border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-700">Record Metrics</h2>
          <form method="GET" class="flex items-center gap-2">
            <select name="period" onchange="this.form.submit()"
              class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-[#F7941D]">
              @foreach($periods as $val => $label)
                <option value="{{ $val }}" {{ $period === $val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </form>
        </div>

        @if(session('success'))
          <div class="mx-6 mt-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
          </div>
        @endif

        <form method="POST" action="{{ route('hr.performance.record', [$module->slug, $employee]) }}" class="p-6 space-y-4">
          @csrf
          <input type="hidden" name="period" value="{{ $period }}">

          @foreach($metrics as $metric)
          @php $ex = $existing[$metric->id] ?? null; @endphp
          <div class="p-4 rounded-lg border border-gray-100 bg-gray-50">
            <div class="flex items-start justify-between mb-3">
              <div>
                <div class="font-medium text-gray-800 text-sm">{{ $metric->name }}</div>
                <div class="text-xs text-gray-400 mt-0.5">{{ $metric->description }}</div>
              </div>
              <div class="text-right text-xs text-gray-400">
                Target: <strong>{{ $metric->target_value }}{{ $metric->unit_label }}</strong>
                @if(!$metric->higher_is_better)
                  <span class="text-orange-500 ml-1">(lower = better)</span>
                @endif
              </div>
            </div>
            <div class="flex gap-3 items-center">
              <div class="flex-1">
                <label class="text-xs text-gray-500 block mb-1">Actual value ({{ $metric->unit }})</label>
                <input type="number" step="0.01" min="0"
                  name="metrics[{{ $metric->id }}][value]"
                  value="{{ $ex?->actual_value ?? '' }}"
                  placeholder="e.g. {{ $metric->target_value }}"
                  class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#F7941D] focus:border-transparent">
              </div>
              <div class="flex-1">
                <label class="text-xs text-gray-500 block mb-1">Notes (optional)</label>
                <input type="text"
                  name="metrics[{{ $metric->id }}][notes]"
                  value="{{ $ex?->notes ?? '' }}"
                  placeholder="Optional note..."
                  class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#F7941D] focus:border-transparent">
              </div>
              @if($ex)
              <div class="text-center">
                <div class="text-xs text-gray-400 mb-1">Score</div>
                @php $s = round($ex->score ?? 0, 1); @endphp
                <span class="font-black text-sm {{ $s>=80?'text-green-600':($s>=60?'text-yellow-600':'text-red-500') }}">
                  {{ $s }}%
                </span>
              </div>
              @endif
            </div>
          </div>
          @endforeach

          <button type="submit"
            class="w-full bg-[#F7941D] text-white py-3 rounded-xl font-semibold hover:bg-orange-600 transition-colors">
            <i class="fas fa-save mr-2"></i>
            Save All Metrics for {{ $periods[$period] ?? $period }}
          </button>
        </form>
      </div>
    </div>

    {{-- Sidebar: history + links --}}
    <div class="space-y-4">
      {{-- Performance history --}}
      @if($history->count())
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">History</h2>
        <div class="space-y-2">
          @foreach($history as $h)
          @php $avg = round($h->avg_score, 1); @endphp
          <div class="flex items-center gap-3">
            <div class="text-xs text-gray-400 w-16">{{ \Carbon\Carbon::createFromFormat('Y-m',$h->period)->format('M Y') }}</div>
            <div class="flex-1 bg-gray-100 rounded-full h-3 overflow-hidden">
              <div class="h-3 rounded-full" style="width:{{ $avg }}%; background:{{ $module->color ?? '#F7941D' }}"></div>
            </div>
            <div class="text-xs font-bold {{ $avg>=80?'text-green-600':($avg>=60?'text-yellow-600':'text-red-500') }}">
              {{ $avg }}%
            </div>
          </div>
          @endforeach
        </div>
      </div>
      @endif

      {{-- HR performance cycles link --}}
      @if($performanceCycles)
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">HR Performance Cycles</h2>
        <div class="space-y-2">
          @foreach($performanceCycles as $cycle)
          <div class="text-sm text-gray-600 py-1 border-b border-gray-50 last:border-0">
            <i class="fas fa-calendar text-[#F7941D] text-xs mr-1"></i>
            {{ $cycle->name ?? $cycle->title ?? 'Cycle' }}
          </div>
          @endforeach
        </div>
        <a href="{{ route('hr.performance.index') }}"
          class="mt-3 text-xs text-[#F7941D] hover:underline block">
          View full performance records →
        </a>
      </div>
      @endif

      {{-- Other modules --}}
      @if(count($allModuleScores) > 1)
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Performance in Other Modules</h2>
        <div class="space-y-2">
          @foreach($allModuleScores as $slug => $score)
          @if($slug !== $module->slug)
          @php $c = $score>=90?'text-green-600':($score>=75?'text-blue-600':($score>=60?'text-yellow-600':'text-red-500')); @endphp
          <div class="flex items-center justify-between text-sm">
            <span class="text-gray-600">{{ Str::upper($slug) }}</span>
            <span class="font-bold {{ $c }}">{{ $score }}%</span>
          </div>
          @endif
          @endforeach
        </div>
      </div>
      @endif
    </div>
  </div>

</div>
@endsection
