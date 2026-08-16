@extends('hr.layouts.app')
@section('title', 'Workforce Audit Log')

@section('content')
<div class="space-y-6">

  {{-- Header --}}
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">🔐 Workforce Audit Log</h1>
      <p class="text-sm text-gray-500 mt-0.5">Every assignment, access change, permission grant, and revocation</p>
    </div>
    <a href="{{ route('hr.audit.index') }}"
      class="text-sm text-gray-400 hover:text-gray-700 border border-gray-200 rounded-lg px-3 py-1.5">
      <i class="fas fa-list text-xs mr-1"></i> All Audit Logs
    </a>
  </div>

  {{-- Stats row --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    @foreach([
      ['Total Events', $stats['total'],     'fas fa-history',     'text-gray-600',   'bg-gray-50'],
      ['Today',        $stats['today'],      'fas fa-calendar-day','text-blue-600',   'bg-blue-50'],
      ['This Week',    $stats['this_week'],  'fas fa-calendar-week','text-indigo-600','bg-indigo-50'],
      ['High Severity',$stats['high'],       'fas fa-exclamation-triangle','text-red-600','bg-red-50'],
    ] as [$label, $val, $icon, $tc, $bg])
    <div class="rounded-xl border border-gray-200 p-4 {{ $bg }}">
      <div class="flex items-center gap-2 mb-1">
        <i class="{{ $icon }} {{ $tc }} text-xs"></i>
        <span class="text-xs text-gray-500">{{ $label }}</span>
      </div>
      <div class="text-2xl font-black {{ $tc }}">{{ number_format($val) }}</div>
    </div>
    @endforeach
  </div>

  {{-- Filters --}}
  <div class="bg-white rounded-xl border border-gray-200 p-5">
    <form method="GET" class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div>
        <label class="text-xs text-gray-500 block mb-1">Employee</label>
        <select name="employee_id"
          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#F7941D]">
          <option value="">All employees</option>
          @foreach($employees as $emp)
          <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>
            {{ $emp->full_name }} ({{ $emp->employee_no }})
          </option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="text-xs text-gray-500 block mb-1">Module</label>
        <select name="module_id"
          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#F7941D]">
          <option value="">All modules</option>
          @foreach($modules as $mod)
          <option value="{{ $mod->id }}" {{ request('module_id') == $mod->id ? 'selected' : '' }}>
            {{ $mod->name }}
          </option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="text-xs text-gray-500 block mb-1">Event type</label>
        <select name="action"
          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#F7941D]">
          <option value="">All events</option>
          @foreach($actions as $act)
          <option value="{{ $act }}" {{ request('action') === $act ? 'selected' : '' }}>
            {{ ucfirst(str_replace(['.','_'],' ',$act)) }}
          </option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="text-xs text-gray-500 block mb-1">Severity</label>
        <select name="severity"
          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#F7941D]">
          <option value="">All severities</option>
          <option value="high"   {{ request('severity') === 'high'   ? 'selected' : '' }}>🔴 High</option>
          <option value="medium" {{ request('severity') === 'medium' ? 'selected' : '' }}>🟡 Medium</option>
          <option value="low"    {{ request('severity') === 'low'    ? 'selected' : '' }}>⚪ Low</option>
        </select>
      </div>
      <div>
        <label class="text-xs text-gray-500 block mb-1">Actor</label>
        <input type="text" name="actor_name" value="{{ request('actor_name') }}"
          placeholder="Search by actor name..."
          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#F7941D]">
      </div>
      <div>
        <label class="text-xs text-gray-500 block mb-1">Date from</label>
        <input type="date" name="date_from" value="{{ request('date_from') }}"
          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#F7941D]">
      </div>
      <div>
        <label class="text-xs text-gray-500 block mb-1">Date to</label>
        <input type="date" name="date_to" value="{{ request('date_to') }}"
          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#F7941D]">
      </div>
      <div class="flex items-end gap-2">
        <button type="submit"
          class="flex-1 bg-[#F7941D] text-white py-2 rounded-lg text-sm font-semibold hover:bg-orange-600 transition-colors">
          <i class="fas fa-search mr-1 text-xs"></i> Filter
        </button>
        <a href="{{ route('hr.audit.workforce') }}"
          class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-500 hover:bg-gray-50">
          Clear
        </a>
      </div>
    </form>
  </div>

  {{-- Results count --}}
  <div class="flex items-center justify-between">
    <p class="text-sm text-gray-500">
      {{ number_format($logs->total()) }} event{{ $logs->total() !== 1 ? 's' : '' }} found
    </p>
    <span class="text-xs text-gray-400">Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span>
  </div>

  {{-- Log entries --}}
  <div class="space-y-2">
    @forelse($logs as $log)
    @php
      $severity = $log->severity;
      $border = $severity === 'high' ? 'border-l-red-400' : ($severity === 'medium' ? 'border-l-yellow-400' : 'border-l-gray-200');
      $dot    = $severity === 'high' ? 'bg-red-400' : ($severity === 'medium' ? 'bg-yellow-400' : 'bg-gray-300');
    @endphp
    <div class="bg-white rounded-xl border border-gray-200 border-l-4 {{ $border }} p-4">
      <div class="flex items-start justify-between gap-4">
        <div class="flex items-start gap-3 min-w-0">
          {{-- Severity dot --}}
          <div class="mt-1.5 w-2 h-2 rounded-full flex-shrink-0 {{ $dot }}"></div>
          <div class="min-w-0">
            {{-- Action label --}}
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-semibold text-gray-800 text-sm">{{ $log->action_label }}</span>
              <code class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded font-mono">{{ $log->action }}</code>
            </div>

            {{-- Context: employee + module --}}
            <div class="flex items-center gap-3 mt-1 flex-wrap text-xs text-gray-500">
              @if($log->employee)
              <span>
                <i class="fas fa-user text-[#F7941D] mr-0.5"></i>
                <a href="{{ route('hr.employees.show', $log->employee) }}"
                  class="hover:text-[#F7941D] font-medium">
                  {{ $log->employee->full_name }}
                </a>
                <span class="text-gray-300 ml-1">{{ $log->employee->employee_no }}</span>
              </span>
              @endif
              @if($log->module)
              <span>
                <i class="fas fa-th-large mr-0.5" style="color:{{ $log->module->color ?? '#888' }}"></i>
                {{ $log->module->name }}
              </span>
              @endif
              <span><i class="fas fa-user-shield mr-0.5"></i>{{ $log->actor_name ?? '—' }}</span>
              <span><i class="fas fa-map-marker-alt mr-0.5"></i>{{ $log->ip ?? '—' }}</span>
            </div>

            {{-- Before/after diff --}}
            @if($log->before || $log->after)
            <div class="mt-2 flex gap-3 flex-wrap">
              @if($log->before)
              <div class="text-xs bg-red-50 border border-red-100 rounded px-2 py-1 max-w-xs">
                <div class="text-red-400 font-semibold mb-0.5">Before</div>
                @foreach($log->before as $k => $v)
                <div><span class="text-gray-400">{{ $k }}:</span> <span class="text-red-700">{{ is_array($v) ? json_encode($v) : ($v ?? 'null') }}</span></div>
                @endforeach
              </div>
              @endif
              @if($log->after)
              <div class="text-xs bg-green-50 border border-green-100 rounded px-2 py-1 max-w-xs">
                <div class="text-green-500 font-semibold mb-0.5">After</div>
                @foreach($log->after as $k => $v)
                <div><span class="text-gray-400">{{ $k }}:</span> <span class="text-green-700">{{ is_array($v) ? json_encode($v) : ($v ?? 'null') }}</span></div>
                @endforeach
              </div>
              @endif
            </div>
            @endif
          </div>
        </div>

        {{-- Timestamp --}}
        <div class="text-right flex-shrink-0">
          <div class="text-xs text-gray-400">{{ $log->created_at->format('d M Y') }}</div>
          <div class="text-xs text-gray-300">{{ $log->created_at->format('H:i:s') }}</div>
          <div class="text-xs text-gray-200 mt-0.5" title="{{ $log->created_at }}">
            {{ $log->created_at->diffForHumans() }}
          </div>
        </div>
      </div>
    </div>
    @empty
    <div class="bg-white rounded-xl border border-gray-200 py-16 text-center text-gray-400">
      <i class="fas fa-shield-alt text-3xl block mb-3 opacity-20"></i>
      No workforce audit events match your filters.
    </div>
    @endforelse
  </div>

  {{-- Pagination --}}
  @if($logs->hasPages())
  <div class="flex justify-center">
    {{ $logs->links() }}
  </div>
  @endif

</div>
@endsection
