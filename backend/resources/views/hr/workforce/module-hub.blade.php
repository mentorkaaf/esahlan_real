@extends('hr.layouts.app')
@section('title', $module->name . ' Workforce')
@section('heading', $module->name . ' — Workforce')
@php use App\Models\HR\WorkforceAssignment; @endphp

@php
$tabDefs = [
    'overview'    => ['icon'=>'fas fa-home',          'label'=>'Overview'],
    'employees'   => ['icon'=>'fas fa-users',          'label'=>'Employees',   'count'=>$stats['active']],
    'departments' => ['icon'=>'fas fa-building',       'label'=>'Departments', 'count'=>$stats['departments']],
    'positions'   => ['icon'=>'fas fa-briefcase',      'label'=>'Positions',   'count'=>$stats['positions']],
    'roles'       => ['icon'=>'fas fa-shield-alt',     'label'=>'Roles',       'count'=>$stats['roles']],
    'assignments' => ['icon'=>'fas fa-clipboard-list', 'label'=>'Assignments'],
    'org-chart'   => ['icon'=>'fas fa-sitemap',        'label'=>'Org Chart'],
    'reports'     => ['icon'=>'fas fa-chart-bar',      'label'=>'Reports'],
    'performance' => ['icon'=>'fas fa-trophy',         'label'=>'Performance'],
];
$mc = $module->color ?? '#1B1444';
$typeColors = [
    'primary'       => ['pill'=>'bg-indigo-50 text-indigo-700 border-indigo-200', 'dot'=>'bg-indigo-500'],
    'secondary'     => ['pill'=>'bg-blue-50 text-blue-700 border-blue-200',       'dot'=>'bg-blue-500'],
    'temporary'     => ['pill'=>'bg-orange-50 text-orange-700 border-orange-200', 'dot'=>'bg-orange-500'],
    'acting'        => ['pill'=>'bg-purple-50 text-purple-700 border-purple-200', 'dot'=>'bg-purple-500'],
    'project_based' => ['pill'=>'bg-teal-50 text-teal-700 border-teal-200',       'dot'=>'bg-teal-500'],
];
@endphp

@section('content')
<div class="space-y-5">

{{-- ── Module header ──────────────────────────────────────────────────────── --}}
<div class="rounded-xl overflow-hidden shadow-sm border border-gray-100">
    <div class="px-6 py-5 flex items-center gap-5"
         style="background: linear-gradient(135deg, {{ $mc }} 0%, {{ $mc }}CC 100%);">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 bg-white/15">
            <i class="{{ $module->icon ?? 'fas fa-cube' }} text-white text-2xl"></i>
        </div>
        <div class="flex-1 min-w-0">
            <h2 class="text-white text-xl font-bold">{{ $module->name }}</h2>
            <p class="text-white/60 text-sm mt-0.5">Module Workforce Management</p>
        </div>
        {{-- Quick stats --}}
        <div class="hidden md:flex items-center gap-6">
            @foreach([
                [$stats['active'],      'Active Staff',   'fas fa-users'],
                [$stats['departments'], 'Departments',    'fas fa-building'],
                [$stats['positions'],   'Positions',      'fas fa-briefcase'],
                [$stats['roles'],       'Roles',          'fas fa-shield-alt'],
            ] as [$val,$lbl,$ico])
            <div class="text-center">
                <div class="text-white text-xl font-bold tabular-nums">{{ $val }}</div>
                <div class="text-white/50 text-xs flex items-center gap-1 justify-center">
                    <i class="{{ $ico }} text-[9px]"></i> {{ $lbl }}
                </div>
            </div>
            @endforeach
        </div>
        {{-- eExchange link --}}
        @if($module->slug === 'eexchange')
        <a href="{{ route('admin.eexchange.index') ?? '#' }}"
           class="hidden lg:flex items-center gap-1.5 bg-white/15 hover:bg-white/25 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors"
           title="Go to eExchange business module">
            <i class="fas fa-exchange-alt text-[10px]"></i> eExchange Module →
        </a>
        @endif
        {{-- Wizard button --}}
        <a href="{{ route('hr.workforce.wizard', ['module_id' => $module->id]) }}"
           class="flex-shrink-0 flex items-center gap-2 bg-white/15 hover:bg-white/25 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors border border-white/20">
            <i class="fas fa-magic text-xs"></i> Assign
        </a>
    </div>

    {{-- Tab bar --}}
    <div class="bg-white border-t border-gray-100 flex overflow-x-auto">
        @foreach($tabDefs as $key => $tdef)
        @php
        $active = $tab === $key;
        $tabHref = $key === 'performance'
            ? route('hr.performance.module', $module->slug)
            : route('hr.workforce.hub.' . $key, $module->slug);
        @endphp
        <a href="{{ $tabHref }}"
           class="flex items-center gap-1.5 px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors flex-shrink-0
                  {{ $active
                     ? 'border-[#1B1444] text-[#1B1444]'
                     : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            <i class="{{ $tdef['icon'] }} text-xs"></i>
            {{ $tdef['label'] }}
            @if(isset($tdef['count']) && $tdef['count'] > 0)
            <span class="text-[10px] {{ $active ? 'bg-[#1B1444] text-white' : 'bg-gray-100 text-gray-500' }} px-1.5 py-0.5 rounded-full tabular-nums">
                {{ $tdef['count'] }}
            </span>
            @endif
        </a>
        @endforeach
    </div>
</div>

{{-- Flash --}}
@if(session('success'))
<div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-xl flex items-center gap-2">
    <i class="fas fa-check-circle"></i> {{ session('success') }}
</div>
@endif
@if($errors->any())
<div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-xl">
    @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════════════════
     TAB: OVERVIEW
     ═══════════════════════════════════════════════════════════════════════════ --}}
@if($tab === 'overview')
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    @foreach([
        ['fas fa-users',     $stats['active'],      'Active Staff',   'bg-blue-50 text-blue-500',   route('hr.workforce.hub.employees',   $module->slug)],
        ['fas fa-building',  $stats['departments'], 'Departments',    'bg-orange-50 text-orange-500',route('hr.workforce.hub.departments', $module->slug)],
        ['fas fa-briefcase', $stats['positions'],   'Positions',      'bg-green-50 text-green-500',  route('hr.workforce.hub.positions',   $module->slug)],
        ['fas fa-shield-alt',$stats['roles'],       'Roles',          'bg-indigo-50 text-indigo-500',route('hr.workforce.hub.roles',       $module->slug)],
    ] as [$ico,$val,$lbl,$cls,$href])
    <a href="{{ $href }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 hover:shadow-md hover:border-gray-200 transition-all group">
        <div class="w-10 h-10 rounded-xl {{ $cls }} flex items-center justify-center mb-3">
            <i class="{{ $ico }} text-lg"></i>
        </div>
        <div class="text-3xl font-bold text-gray-900 tabular-nums group-hover:text-[#1B1444] transition-colors">{{ $val }}</div>
        <div class="text-sm text-gray-500 mt-0.5">{{ $lbl }}</div>
    </a>
    @endforeach
</div>

@if($stats['expiring'] > 0)
<div class="flex items-center gap-3 bg-orange-50 border border-orange-200 text-orange-700 text-sm px-4 py-3 rounded-xl">
    <i class="fas fa-clock flex-shrink-0"></i>
    <span><strong>{{ $stats['expiring'] }}</strong> assignment{{ $stats['expiring'] > 1 ? 's' : '' }} expiring within 14 days.</span>
    <a href="{{ route('hr.workforce.hub.assignments', $module->slug) }}" class="ml-auto text-orange-600 hover:underline text-xs font-medium">Review →</a>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    {{-- Recent assignments --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-sm">Recent Assignments</h3>
            <a href="{{ route('hr.workforce.hub.assignments', $module->slug) }}" class="text-xs text-gray-400 hover:text-gray-600">All →</a>
        </div>
        @forelse($recentAssignments as $a)
        @php $tc = $typeColors[$a->assignment_type] ?? $typeColors['primary']; @endphp
        <div class="px-5 py-3.5 border-b border-gray-50 last:border-0 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                 style="background-color: {{ $mc }};">{{ $a->employee?->initials ?? '?' }}</div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5">
                    <a href="{{ route('hr.employees.show', $a->employee_id) }}"
                       class="text-sm font-semibold text-gray-800 hover:text-[#1B1444] truncate">{{ $a->employee?->full_name }}</a>
                    <span class="text-[10px] border px-1.5 py-0.5 rounded {{ $tc['pill'] }}">{{ $a->assignment_type_label }}</span>
                </div>
                <div class="text-xs text-gray-400 truncate">
                    {{ $a->moduleDepartment?->name }} @if($a->modulePosition) · {{ $a->modulePosition->name }} @endif
                </div>
            </div>
            <div class="text-xs text-gray-300 flex-shrink-0">{{ $a->assigned_at?->diffForHumans() }}</div>
        </div>
        @empty
        <div class="px-6 py-8 text-center text-gray-400 text-sm">
            <i class="fas fa-users-slash text-2xl text-gray-200 mb-2 block"></i>
            No employees assigned yet.
            <a href="{{ route('hr.workforce.wizard', ['module_id' => $module->id]) }}" class="text-[#F7941D] block mt-1 hover:underline text-xs">Assign first employee →</a>
        </div>
        @endforelse
    </div>

    {{-- Expiring assignments --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800 text-sm flex items-center gap-2">
                <i class="fas fa-hourglass-half text-orange-400 text-xs"></i> Expiring Assignments
            </h3>
        </div>
        @forelse($expiringAssignments as $a)
        @php
            $expired = $a->isExpired();
            $daysLeft = $a->planned_end_date ? now()->diffInDays($a->planned_end_date, false) : null;
        @endphp
        <div class="px-5 py-3.5 border-b border-gray-50 last:border-0 flex items-center gap-3 {{ $expired ? 'bg-red-50/40' : '' }}">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-xs font-bold flex-shrink-0
                        {{ $expired ? 'bg-red-500' : 'bg-orange-400' }}">
                {{ $a->employee?->initials ?? '?' }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold text-gray-800 truncate">{{ $a->employee?->full_name }}</div>
                <div class="text-xs text-gray-400">
                    {{ $a->assignment_type_label }} · Until {{ $a->planned_end_date?->format('d M Y') }}
                </div>
            </div>
            @if($expired)
            <span class="text-[10px] bg-red-100 text-red-600 border border-red-200 px-1.5 py-0.5 rounded font-bold">EXPIRED</span>
            @else
            <span class="text-[10px] bg-orange-100 text-orange-600 border border-orange-200 px-1.5 py-0.5 rounded">{{ max(0,$daysLeft) }}d</span>
            @endif
        </div>
        @empty
        <div class="px-6 py-8 text-center">
            <i class="fas fa-check-circle text-green-300 text-2xl mb-2 block"></i>
            <p class="text-gray-400 text-sm">No expiring assignments</p>
        </div>
        @endforelse
    </div>
</div>

{{-- Quick links --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    @foreach([
        [route('hr.workforce.hub.org-chart',   $module->slug), 'fas fa-sitemap',        'View Org Chart',     'text-blue-500'],
        [route('hr.workforce.hub.reports',     $module->slug), 'fas fa-chart-bar',      'View Reports',       'text-green-500'],
        [route('hr.performance.module',        $module->slug), 'fas fa-trophy',          'Performance',        'text-yellow-500'],
        [route('hr.module-departments.index'), 'fas fa-building',       'All Departments',    'text-orange-500'],
        [route('hr.module-roles.index'),       'fas fa-shield-alt',     'Module Roles',       'text-indigo-500'],
    ] as [$href,$ico,$lbl,$cls])
    <a href="{{ $href }}" class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3.5 flex items-center gap-3 hover:shadow-md hover:border-gray-200 transition-all">
        <i class="{{ $ico }} {{ $cls }} text-sm"></i>
        <span class="text-sm text-gray-700 font-medium">{{ $lbl }}</span>
        <i class="fas fa-arrow-right text-gray-300 text-xs ml-auto"></i>
    </a>
    @endforeach
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     TAB: EMPLOYEES
     ═══════════════════════════════════════════════════════════════════════════ --}}
@elseif($tab === 'employees')
{{-- Filters --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-3.5 flex flex-wrap items-center gap-3">
    <form method="GET" class="flex flex-wrap items-center gap-3 w-full">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Search name or employee no…"
               class="flex-1 min-w-[180px] text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-[#1B1444]">
        <select name="dept_id" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-[#1B1444]">
            <option value="">All Departments</option>
            @foreach($departments as $d)
            <option value="{{ $d->id }}" {{ request('dept_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
            @endforeach
        </select>
        <select name="pos_id" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-[#1B1444]">
            <option value="">All Positions</option>
            @foreach($positions as $p)
            <option value="{{ $p->id }}" {{ request('pos_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
            @endforeach
        </select>
        <select name="type" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-[#1B1444]">
            <option value="">All Types</option>
            @foreach(WorkforceAssignment::assignmentTypes() as $k=>$v)
            <option value="{{ $k }}" {{ request('type') == $k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
        </select>
        <button type="submit" class="bg-[#1B1444] text-white text-sm px-4 py-1.5 rounded-lg hover:bg-[#2D2467] transition-colors">Filter</button>
        @if(request()->hasAny(['search','dept_id','pos_id','type']))
        <a href="{{ route('hr.workforce.hub.employees', $module->slug) }}" class="text-sm text-gray-400 hover:text-gray-600">Clear</a>
        @endif
    </form>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-6 py-3.5 border-b border-gray-100 flex items-center justify-between">
        <span class="text-sm font-semibold text-gray-700">{{ $assignments->total() }} employee{{ $assignments->total() !== 1 ? 's' : '' }}</span>
        <a href="{{ route('hr.workforce.wizard', ['module_id' => $module->id]) }}"
           class="text-xs bg-[#F7941D] text-white px-3 py-1.5 rounded-lg hover:bg-[#E07800] transition-colors flex items-center gap-1.5">
            <i class="fas fa-magic text-[10px]"></i> Assign Employee
        </a>
    </div>
    @forelse($assignments as $a)
    @php $tc = $typeColors[$a->assignment_type] ?? $typeColors['primary']; @endphp
    <div class="px-6 py-4 border-b border-gray-50 last:border-0 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
             style="background-color: {{ $mc }};">{{ $a->employee?->initials ?? '?' }}</div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('hr.employees.show', $a->employee_id) }}"
                   class="font-semibold text-gray-800 text-sm hover:text-[#1B1444]">{{ $a->employee?->full_name }}</a>
                <span class="text-[10px] border px-1.5 py-0.5 rounded {{ $tc['pill'] }}">{{ $a->assignment_type_label }}</span>
                <span class="text-[10px] text-gray-400">{{ $a->access_level_label }}</span>
            </div>
            <div class="text-xs text-gray-400 flex flex-wrap gap-x-2 mt-0.5">
                @if($a->moduleDepartment) <span>{{ $a->moduleDepartment->name }}</span> @endif
                @if($a->modulePosition)   <span>· {{ $a->modulePosition->name }}</span> @endif
                @if($a->moduleRole)       <span>· <i class="fas fa-shield-alt text-indigo-300 text-[9px]"></i> {{ $a->moduleRole->name }}</span> @endif
                @if($a->reportingManager) <span>· Reports to {{ $a->reportingManager->full_name }}</span> @endif
            </div>
        </div>
        <div class="text-xs text-gray-400 hidden sm:block flex-shrink-0">{{ $a->assigned_at?->format('d M Y') }}</div>
        @if($a->planned_end_date)
        <span class="text-[10px] {{ $a->isExpired() ? 'bg-red-100 text-red-600 border-red-200' : 'bg-orange-50 text-orange-600 border-orange-200' }} border px-1.5 py-0.5 rounded flex-shrink-0">
            {{ $a->isExpired() ? 'Expired' : 'Until '.$a->planned_end_date->format('d M') }}
        </span>
        @endif
        {{-- Status --}}
        <span class="text-[10px] {{ $a->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }} px-2 py-0.5 rounded-full flex-shrink-0">
            {{ ucfirst($a->status) }}
        </span>
    </div>
    @empty
    <div class="px-6 py-14 text-center">
        <i class="fas fa-users-slash text-4xl text-gray-200 mb-3 block"></i>
        <p class="text-gray-400 text-sm">No employees match the filters.</p>
    </div>
    @endforelse

    @if($assignments->hasPages())
    <div class="px-6 py-3 border-t border-gray-100">{{ $assignments->links() }}</div>
    @endif
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     TAB: DEPARTMENTS
     ═══════════════════════════════════════════════════════════════════════════ --}}
@elseif($tab === 'departments')
<div class="flex items-center justify-between">
    <p class="text-sm text-gray-500">{{ $departments->total() }} department{{ $departments->total() !== 1 ? 's' : '' }} in {{ $module->name }}</p>
    <a href="{{ route('hr.module-departments.create', ['module_id' => $module->id]) }}"
       class="text-xs bg-[#F7941D] text-white px-3 py-1.5 rounded-lg hover:bg-[#E07800] flex items-center gap-1.5">
       <i class="fas fa-plus text-[10px]"></i> Add Department
    </a>
</div>
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    @forelse($departments as $d)
    <div class="px-6 py-4 border-b border-gray-50 last:border-0 flex items-center gap-4">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
             style="background-color: {{ $mc }}20;">
            <i class="fas fa-building text-sm" style="color: {{ $mc }};"></i>
        </div>
        <div class="flex-1 min-w-0">
            <div class="font-semibold text-gray-800 text-sm">{{ $d->name }}</div>
            @if($d->description)
            <div class="text-xs text-gray-400 truncate">{{ $d->description }}</div>
            @endif
        </div>
        <div class="flex items-center gap-3">
            <div class="text-center">
                <div class="text-lg font-bold tabular-nums" style="color: {{ $mc }};">{{ $d->employee_count }}</div>
                <div class="text-[10px] text-gray-400">employees</div>
            </div>
            <span class="text-[10px] {{ $d->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400' }} px-2 py-0.5 rounded-full">
                {{ ucfirst($d->status) }}
            </span>
            <a href="{{ route('hr.module-departments.edit', $d) }}"
               class="text-xs text-gray-400 hover:text-[#1B1444]"><i class="fas fa-edit"></i></a>
        </div>
    </div>
    @empty
    <div class="px-6 py-14 text-center">
        <i class="fas fa-building text-4xl text-gray-200 mb-3 block"></i>
        <p class="text-gray-400 text-sm mb-2">No departments yet for {{ $module->name }}</p>
        <a href="{{ route('hr.module-departments.create', ['module_id' => $module->id]) }}"
           class="inline-flex items-center gap-1.5 text-sm text-[#F7941D] hover:underline">
           <i class="fas fa-plus text-[10px]"></i> Create first department
        </a>
    </div>
    @endforelse
    @if($departments->hasPages())
    <div class="px-6 py-3 border-t border-gray-100">{{ $departments->links() }}</div>
    @endif
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     TAB: POSITIONS
     ═══════════════════════════════════════════════════════════════════════════ --}}
@elseif($tab === 'positions')
<div class="flex items-center justify-between">
    <p class="text-sm text-gray-500">{{ $positions->total() }} position{{ $positions->total() !== 1 ? 's' : '' }} in {{ $module->name }}</p>
    <a href="{{ route('hr.module-positions.create', ['module_id' => $module->id]) }}"
       class="text-xs bg-[#F7941D] text-white px-3 py-1.5 rounded-lg hover:bg-[#E07800] flex items-center gap-1.5">
       <i class="fas fa-plus text-[10px]"></i> Add Position
    </a>
</div>
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    @forelse($positions as $p)
    <div class="px-6 py-4 border-b border-gray-50 last:border-0 flex items-center gap-4">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
             style="background-color: {{ $mc }}20;">
            <i class="fas fa-briefcase text-sm" style="color: {{ $mc }};"></i>
        </div>
        <div class="flex-1 min-w-0">
            <div class="font-semibold text-gray-800 text-sm">{{ $p->name }}</div>
            @if($p->description)
            <div class="text-xs text-gray-400 truncate">{{ $p->description }}</div>
            @endif
            @if(isset($p->salary_min) && $p->salary_min)
            <div class="text-xs text-gray-400">{{ number_format($p->salary_min) }} – {{ number_format($p->salary_max ?? 0) }}</div>
            @endif
        </div>
        <div class="flex items-center gap-3">
            <div class="text-center">
                <div class="text-lg font-bold tabular-nums" style="color: {{ $mc }};">{{ $p->employee_count }}</div>
                <div class="text-[10px] text-gray-400">employees</div>
            </div>
            <span class="text-[10px] {{ $p->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400' }} px-2 py-0.5 rounded-full">
                {{ ucfirst($p->status) }}
            </span>
            <a href="{{ route('hr.module-positions.edit', $p) }}"
               class="text-xs text-gray-400 hover:text-[#1B1444]"><i class="fas fa-edit"></i></a>
        </div>
    </div>
    @empty
    <div class="px-6 py-14 text-center">
        <i class="fas fa-briefcase text-4xl text-gray-200 mb-3 block"></i>
        <p class="text-gray-400 text-sm mb-2">No positions yet for {{ $module->name }}</p>
        <a href="{{ route('hr.module-positions.create', ['module_id' => $module->id]) }}"
           class="inline-flex items-center gap-1.5 text-sm text-[#F7941D] hover:underline">
           <i class="fas fa-plus text-[10px]"></i> Create first position
        </a>
    </div>
    @endforelse
    @if($positions->hasPages())
    <div class="px-6 py-3 border-t border-gray-100">{{ $positions->links() }}</div>
    @endif
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     TAB: ROLES
     ═══════════════════════════════════════════════════════════════════════════ --}}
@elseif($tab === 'roles')
<div class="flex items-center justify-between">
    <p class="text-sm text-gray-500">{{ $roles->total() }} role{{ $roles->total() !== 1 ? 's' : '' }} in {{ $module->name }}</p>
    <a href="{{ route('hr.module-roles.create', ['module_id' => $module->id]) }}"
       class="text-xs bg-[#F7941D] text-white px-3 py-1.5 rounded-lg hover:bg-[#E07800] flex items-center gap-1.5">
       <i class="fas fa-plus text-[10px]"></i> Add Role
    </a>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    @forelse($roles as $r)
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 flex items-center gap-3"
             style="background: linear-gradient(135deg, {{ $mc }}10, {{ $mc }}05);">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                 style="background-color: {{ $mc }}20;">
                <i class="fas fa-shield-alt text-sm" style="color: {{ $mc }};"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-gray-800 text-sm">{{ $r->name }}</span>
                    @if($r->is_system)
                    <span class="text-[9px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded">System</span>
                    @endif
                </div>
                <div class="text-xs text-gray-400">{{ $r->description }}</div>
            </div>
            <div class="text-right flex-shrink-0">
                <div class="text-lg font-bold tabular-nums text-gray-800">{{ $r->employee_count }}</div>
                <div class="text-[10px] text-gray-400">assigned</div>
            </div>
        </div>
        @if($r->permissions->isNotEmpty())
        <div class="px-5 py-3 border-t border-gray-100">
            <div class="flex flex-wrap gap-1">
                @foreach($r->permissions->take(8) as $p)
                <span class="text-[10px] bg-indigo-50 text-indigo-600 border border-indigo-100 px-1.5 py-0.5 rounded font-mono">
                    {{ last(explode('.',$p->slug)) }}
                </span>
                @endforeach
                @if($r->permissions->count() > 8)
                <span class="text-[10px] text-gray-400">+{{ $r->permissions->count() - 8 }} more</span>
                @endif
            </div>
        </div>
        @endif
        <div class="px-5 py-2 border-t border-gray-50 flex items-center justify-between">
            <span class="text-[10px] {{ $r->status === 'active' ? 'text-green-600' : 'text-gray-400' }}">
                <i class="fas fa-circle text-[8px] mr-1"></i>{{ ucfirst($r->status) }}
            </span>
            <a href="{{ route('hr.module-roles.edit', $r) }}" class="text-xs text-gray-400 hover:text-[#1B1444]">Edit →</a>
        </div>
    </div>
    @empty
    <div class="col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-14 text-center">
        <i class="fas fa-shield-alt text-4xl text-gray-200 mb-3 block"></i>
        <p class="text-gray-400 text-sm mb-2">No roles yet for {{ $module->name }}</p>
        <a href="{{ route('hr.module-roles.create', ['module_id' => $module->id]) }}"
           class="inline-flex items-center gap-1.5 text-sm text-[#F7941D] hover:underline">
           <i class="fas fa-plus text-[10px]"></i> Create first role
        </a>
    </div>
    @endforelse
</div>
@if(isset($roles) && $roles->hasPages())
<div class="mt-2">{{ $roles->links() }}</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════════════════
     TAB: ASSIGNMENTS
     ═══════════════════════════════════════════════════════════════════════════ --}}
@elseif($tab === 'assignments')
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    {{-- Filter bar --}}
    <div class="px-5 py-3.5 border-b border-gray-100 flex flex-wrap items-center gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <select name="status" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-[#1B1444]">
                <option value="">All Statuses</option>
                @foreach(['active'=>'Active','suspended'=>'Suspended','ended'=>'Ended'] as $k=>$v)
                <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
            <select name="type" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-[#1B1444]">
                <option value="">All Types</option>
                @foreach(WorkforceAssignment::assignmentTypes() as $k=>$v)
                <option value="{{ $k }}" {{ request('type') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
            <select name="access" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-[#1B1444]">
                <option value="">All Access Levels</option>
                @foreach(WorkforceAssignment::accessLevels() as $k=>$v)
                <option value="{{ $k }}" {{ request('access') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-[#1B1444] text-white text-sm px-4 py-1.5 rounded-lg">Filter</button>
            @if(request()->hasAny(['status','type','access']))
            <a href="{{ route('hr.workforce.hub.assignments', $module->slug) }}" class="text-sm text-gray-400">Clear</a>
            @endif
        </form>
        <div class="ml-auto">
            <span class="text-xs text-gray-400">{{ $assignments->total() }} record{{ $assignments->total() !== 1 ? 's' : '' }}</span>
        </div>
    </div>

    @forelse($assignments as $a)
    @php $tc = $typeColors[$a->assignment_type] ?? $typeColors['primary']; @endphp
    <div class="px-6 py-4 border-b border-gray-50 last:border-0 flex items-center gap-4">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
             style="background-color: {{ $a->status === 'active' ? $mc : '#9CA3AF' }};">
            {{ $a->employee?->initials ?? '?' }}
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('hr.employees.show', $a->employee_id) }}"
                   class="font-semibold text-sm text-gray-800 hover:text-[#1B1444]">{{ $a->employee?->full_name }}</a>
                <span class="text-[10px] border px-1.5 py-0.5 rounded {{ $tc['pill'] }}">{{ $a->assignment_type_label }}</span>
                <span class="text-[10px] {{ $a->status === 'active' ? 'bg-green-100 text-green-700' : ($a->status === 'suspended' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500') }} px-1.5 py-0.5 rounded-full text-[10px]">
                    {{ ucfirst($a->status) }}
                </span>
            </div>
            <div class="text-xs text-gray-400 flex flex-wrap gap-x-2 mt-0.5">
                @if($a->moduleDepartment) <span>{{ $a->moduleDepartment->name }}</span> @endif
                @if($a->modulePosition)   <span>· {{ $a->modulePosition->name }}</span> @endif
                @if($a->moduleRole)       <span>· <i class="fas fa-shield-alt text-[9px] text-indigo-300"></i> {{ $a->moduleRole->name }}</span> @endif
            </div>
        </div>
        <div class="text-right flex-shrink-0 text-xs text-gray-400">
            <div>{{ $a->assigned_at?->format('d M Y') }}</div>
            @if($a->planned_end_date)
            <div class="{{ $a->isExpired() ? 'text-red-500 font-medium' : '' }}">
                Until {{ $a->planned_end_date->format('d M Y') }}
            </div>
            @endif
        </div>
        @if(Auth::guard('hr')->user()->isManager())
        <div class="flex gap-2 flex-shrink-0">
            @if($a->isActive())
            <form method="POST" action="{{ route('hr.workforce.suspend', $a) }}">
                @csrf
                <input type="hidden" name="reason" value="Suspended from module workforce">
                <button class="text-xs text-yellow-600 hover:text-yellow-800">Suspend</button>
            </form>
            @elseif($a->status === 'suspended')
            <form method="POST" action="{{ route('hr.workforce.reactivate', $a) }}">
                @csrf
                <button class="text-xs text-green-600 hover:text-green-800">Reactivate</button>
            </form>
            @endif
            @if($a->status !== 'ended')
            <form method="POST" action="{{ route('hr.workforce.destroy', $a) }}"
                  onsubmit="return confirm('End this assignment?')">
                @csrf @method('DELETE')
                <button class="text-xs text-red-400 hover:text-red-600">End</button>
            </form>
            @endif
        </div>
        @endif
    </div>
    @empty
    <div class="px-6 py-14 text-center text-gray-400 text-sm">No assignments found.</div>
    @endforelse
    @if($assignments->hasPages())
    <div class="px-6 py-3 border-t border-gray-100">{{ $assignments->links() }}</div>
    @endif
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     TAB: ORG CHART
     ═══════════════════════════════════════════════════════════════════════════ --}}
@elseif($tab === 'org-chart')
@php $treeJson = json_encode($treeData); @endphp
<div x-data="hubOrgChart()" x-init="init()">
    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-3.5 flex flex-wrap items-center gap-3 mb-4">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <select name="dept_id" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-[#1B1444]">
                <option value="">All Departments</option>
                @foreach($departments as $d)
                <option value="{{ $d->id }}" {{ request('dept_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
            </select>
            <select name="pos_id" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-[#1B1444]">
                <option value="">All Positions</option>
                @foreach($positions as $p)
                <option value="{{ $p->id }}" {{ request('pos_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-[#1B1444] text-white text-sm px-4 py-1.5 rounded-lg">Filter</button>
            @if(request()->hasAny(['dept_id','pos_id']))
            <a href="{{ route('hr.workforce.hub.org-chart', $module->slug) }}" class="text-sm text-gray-400">Clear</a>
            @endif
        </form>
        <div class="flex-1"></div>
        <div class="flex gap-1">
            <button @click="zoom(0.1)"    class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:border-[#1B1444] text-xs transition-colors"><i class="fas fa-plus"></i></button>
            <button @click="zoom(-0.1)"   class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:border-[#1B1444] text-xs transition-colors"><i class="fas fa-minus"></i></button>
            <button @click="resetView()"  class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:border-[#1B1444] text-xs transition-colors"><i class="fas fa-compress-arrows-alt"></i></button>
            <button @click="expandAll()"  class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:border-[#1B1444] ml-1 transition-colors">Expand All</button>
            <button @click="collapseAll()" class="text-xs px-3 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:border-[#1B1444] transition-colors">Collapse</button>
        </div>
    </div>

    {{-- Canvas --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden relative" style="height: 560px;">
        <div x-show="nodeCount === 0" class="absolute inset-0 flex flex-col items-center justify-center text-center p-8">
            <i class="fas fa-sitemap text-5xl text-gray-200 mb-4 block"></i>
            <p class="text-gray-400 text-sm">No hierarchy data — assign employees with reporting managers to build the chart.</p>
            <a href="{{ route('hr.workforce.wizard', ['module_id' => $module->id]) }}" class="mt-2 text-sm text-[#F7941D] hover:underline">Assign employees →</a>
        </div>
        <div id="hub-viewport" class="absolute inset-0 overflow-hidden cursor-grab active:cursor-grabbing"
             @mousedown="startDrag($event)" @mousemove="doDrag($event)" @mouseup="endDrag()" @mouseleave="endDrag()"
             @wheel.prevent="onWheel($event)">
            <svg id="hub-svg" class="absolute top-0 left-0 pointer-events-none overflow-visible" style="width:100%;height:100%;">
                <g id="hub-connections"></g>
            </svg>
            <div id="hub-nodes" class="absolute top-0 left-0" style="transform-origin:0 0;"></div>
        </div>
    </div>
</div>
<script>
const HUB_TREE  = {!! $treeJson !!};
const HUB_COLOR = '{{ $mc }}';
function hubOrgChart() {
    return {
        tree: HUB_TREE, scale: 1, tx: 0, ty: 0, dragging: false, dragStart: {},
        collapsed: {}, nodeCount: 0,
        init() { this.render(); this.$nextTick(() => this.centerView()); },
        subtreeWidth(node, NW=192, HG=20) {
            if (this.collapsed[node.id] || !node.children?.length) return NW;
            const cw = node.children.map(c => this.subtreeWidth(c, NW, HG));
            return Math.max(NW, cw.reduce((a,b) => a+b+HG, -HG));
        },
        layoutNode(node, x, y, pos, conns, pid=null, NW=192, NH=80, HG=20, VG=64) {
            const sw = this.subtreeWidth(node, NW, HG);
            pos[node.id] = { x: x+(sw-NW)/2, y };
            if (pid !== null) conns.push({from: pid, to: node.id});
            if (!this.collapsed[node.id] && node.children?.length) {
                let cx = x;
                node.children.forEach(c => {
                    const cw = this.subtreeWidth(c, NW, HG);
                    this.layoutNode(c, cx, y+NH+VG, pos, conns, node.id, NW, NH, HG, VG);
                    cx += cw + HG;
                });
            }
        },
        render() {
            const NW=192, NH=80;
            const ni = document.getElementById('hub-nodes');
            const sg = document.getElementById('hub-connections');
            if (!ni||!sg) return;
            ni.innerHTML=''; sg.innerHTML='';
            if (!this.tree?.length) { this.nodeCount=0; return; }
            const pos={}, conns=[];
            let rx=24;
            this.tree.forEach(r => { this.layoutNode(r,rx,24,pos,conns); rx+=this.subtreeWidth(r)+20; });
            this.nodeCount = Object.keys(pos).length;
            // SVG connections
            const paths = conns.map(c => {
                const f=pos[c.from], t=pos[c.to]; if(!f||!t) return '';
                const x1=f.x+NW/2,y1=f.y+NH,x2=t.x+NW/2,y2=t.y,my=(y1+y2)/2;
                return `<path d="M${x1},${y1} C${x1},${my} ${x2},${my} ${x2},${y2}" fill="none" stroke="#E5E7EB" stroke-width="1.5"/>`;
            }).join('');
            sg.innerHTML = `<g transform="translate(${this.tx},${this.ty}) scale(${this.scale})">${paths}</g>`;
            ni.style.transform = `translate(${this.tx}px,${this.ty}px) scale(${this.scale})`;
            // Nodes
            const typeC={'primary':'#4F46E5','secondary':'#3B82F6','temporary':'#F97316','acting':'#A855F7','project_based':'#14B8A6'};
            Object.entries(pos).forEach(([id,p]) => {
                const node = this.findNode(id, this.tree);
                if (!node) return;
                const color = typeC[node.assignment_type] || HUB_COLOR;
                const hasC = node.children?.length > 0;
                const isC = !!this.collapsed[node.id];
                const d = document.createElement('div');
                d.className = 'absolute bg-white rounded-xl border-2 shadow-sm cursor-pointer hover:shadow-md transition-shadow select-none';
                d.style.cssText = `width:${NW}px;height:${NH}px;left:${p.x}px;top:${p.y}px;border-color:${color}30;`;
                d.innerHTML = `<div style="background:${color};" class="rounded-t-[10px] px-3 py-1.5 flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">${node.initials}</div>
                    <div class="flex-1 min-w-0">
                        <div class="text-white text-xs font-semibold truncate">${node.name}</div>
                        <div class="text-white/70 text-[10px] truncate">${node.type_label||''}</div>
                    </div>
                    ${hasC?`<button class="ecb w-5 h-5 bg-white/20 hover:bg-white/40 rounded flex items-center justify-center text-white text-[10px] transition-colors"><i class="fas fa-${isC?'plus':'minus'}"></i></button>`:''}
                </div>
                <div class="px-3 py-2">
                    <div class="text-[11px] text-gray-700 font-medium truncate">${node.position||node.role||'—'}</div>
                    <div class="text-[10px] text-gray-400 truncate">${node.department||''}</div>
                </div>`;
                if(hasC) d.querySelector('.ecb')?.addEventListener('click',e=>{e.stopPropagation();this.collapsed[node.id]=!this.collapsed[node.id];this.render();});
                d.addEventListener('click', e => {
                    if(e.target.closest('.ecb')) return;
                    window.open(node.employee_url,'_self');
                });
                ni.appendChild(d);
            });
        },
        findNode(id, nodes) {
            for (const n of nodes) {
                if (String(n.id)===String(id)) return n;
                const f=this.findNode(id,n.children||[]); if(f) return f;
            }
            return null;
        },
        expandAll()  { this.collapsed={}; this.render(); },
        collapseAll(){ const c=(ns)=>ns.forEach(n=>{if(n.children?.length){this.collapsed[n.id]=true;c(n.children);}});c(this.tree);this.render(); },
        zoom(d)      { this.scale=Math.min(2,Math.max(0.2,this.scale+d)); this.render(); },
        onWheel(e)   { this.zoom(e.deltaY>0?-0.08:0.08); },
        resetView()  { this.scale=1;this.tx=0;this.ty=0;this.render();this.$nextTick(()=>this.centerView()); },
        centerView() {
            const ni=document.getElementById('hub-nodes'),vp=document.getElementById('hub-viewport');
            if(!ni||!vp) return;
            const ns=ni.querySelectorAll('.absolute'); if(!ns.length) return;
            let minX=Infinity,minY=Infinity,maxX=-Infinity,maxY=-Infinity;
            ns.forEach(n=>{const l=parseFloat(n.style.left),t=parseFloat(n.style.top);minX=Math.min(minX,l);minY=Math.min(minY,t);maxX=Math.max(maxX,l+192);maxY=Math.max(maxY,t+80);});
            const tw=maxX-minX,th=maxY-minY,vw=vp.clientWidth,vh=vp.clientHeight;
            this.scale=Math.min(1,Math.min((vw-48)/Math.max(tw,1),(vh-48)/Math.max(th,1)));
            this.tx=(vw-tw*this.scale)/2-minX*this.scale; this.ty=24; this.render();
        },
        startDrag(e) { if(e.target.closest('.absolute.bg-white')) return; this.dragging=true;this.dragStart={x:e.clientX,y:e.clientY,tx:this.tx,ty:this.ty}; },
        doDrag(e)    { if(!this.dragging)return;this.tx=this.dragStart.tx+(e.clientX-this.dragStart.x);this.ty=this.dragStart.ty+(e.clientY-this.dragStart.y);const ni=document.getElementById('hub-nodes'),sg=document.getElementById('hub-connections');if(ni)ni.style.transform=`translate(${this.tx}px,${this.ty}px) scale(${this.scale})`;if(sg){const g=sg.querySelector('g');if(g)g.setAttribute('transform',`translate(${this.tx},${this.ty}) scale(${this.scale})`);} },
        endDrag()    { this.dragging=false; },
    };
}
</script>

{{-- ═══════════════════════════════════════════════════════════════════════════
     TAB: REPORTS
     ═══════════════════════════════════════════════════════════════════════════ --}}
@elseif($tab === 'reports')
@php
$typeLabels  = WorkforceAssignment::assignmentTypes();
$accessLabels= WorkforceAssignment::accessLevels();
$typeHex     = ['primary'=>'#4F46E5','secondary'=>'#3B82F6','temporary'=>'#F97316','acting'=>'#A855F7','project_based'=>'#14B8A6'];
$accessHex   = ['admin'=>'#EF4444','elevated'=>'#F97316','standard'=>'#3B82F6','read_only'=>'#9CA3AF'];
$maxDept     = max($byDept->max('n') ?? 1, 1);
$maxPos      = max($byPos->max('n')  ?? 1, 1);
$maxRole     = max($byRole->max('n') ?? 1, 1);
$maxMonth    = max(max(array_values($monthly) ?: [1]), 1);
@endphp
{{-- Summary row --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    @foreach([
        [$totalActive,   'Active',    'bg-green-50 text-green-600'],
        [$totalSuspended,'Suspended', 'bg-yellow-50 text-yellow-600'],
        [$totalEnded,    'Ended',     'bg-gray-50 text-gray-500'],
        [$totalAll,      'All Time',  'bg-blue-50 text-blue-600'],
    ] as [$v,$l,$cls])
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="text-3xl font-bold text-gray-900 tabular-nums">{{ $v }}</div>
        <div class="text-sm {{ $cls }} font-medium mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs">{{ $l }}</div>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    {{-- Assignment types --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h4 class="font-semibold text-gray-800 text-sm flex items-center gap-2"><i class="fas fa-tags text-[#F7941D] text-xs"></i> By Assignment Type</h4>
        </div>
        <div class="px-5 py-4 space-y-3">
            @foreach($typeLabels as $k=>$v)
            @php $n = $byType[$k] ?? 0; $tot = max($totalActive,1); @endphp
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full flex-shrink-0" style="background-color:{{ $typeHex[$k] ?? '#ccc' }};"></div>
                <span class="text-xs text-gray-600 w-28">{{ $v }}</span>
                <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full" style="width:{{ round(($n/$tot)*100) }}%;background-color:{{ $typeHex[$k] ?? '#ccc' }};"></div>
                </div>
                <span class="text-xs font-bold tabular-nums text-gray-700 w-6 text-right">{{ $n }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Access levels --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h4 class="font-semibold text-gray-800 text-sm flex items-center gap-2"><i class="fas fa-lock text-purple-400 text-xs"></i> By Access Level</h4>
        </div>
        <div class="px-5 py-4 space-y-3">
            @foreach($accessLabels as $k=>$v)
            @php $n = $byAccess[$k] ?? 0; $tot = max($totalActive,1); @endphp
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full flex-shrink-0" style="background-color:{{ $accessHex[$k] ?? '#ccc' }};"></div>
                <span class="text-xs text-gray-600 w-24">{{ $v }}</span>
                <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full" style="width:{{ round(($n/$tot)*100) }}%;background-color:{{ $accessHex[$k] ?? '#ccc' }};"></div>
                </div>
                <span class="text-xs font-bold tabular-nums text-gray-700 w-6 text-right">{{ $n }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Monthly activity (mini bar chart) --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h4 class="font-semibold text-gray-800 text-sm flex items-center gap-2"><i class="fas fa-calendar text-blue-400 text-xs"></i> Monthly Assignments</h4>
        </div>
        <div class="px-5 py-4">
            @if(empty($monthly))
            <div class="text-center text-gray-400 text-xs py-4">No assignment history</div>
            @else
            <div class="flex items-end gap-1.5 h-24">
                @foreach($monthly as $m => $n)
                @php $h = max(4, round(($n/$maxMonth)*100)); @endphp
                <div class="flex-1 flex flex-col items-center gap-1">
                    <div class="w-full rounded-t" style="height:{{ $h }}%;background-color:{{ $mc }};min-height:4px;"></div>
                    <div class="text-[9px] text-gray-400">{{ \Carbon\Carbon::createFromFormat('Y-m',$m)->format('M') }}</div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    {{-- By department --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h4 class="font-semibold text-gray-800 text-sm flex items-center gap-2"><i class="fas fa-building text-blue-400 text-xs"></i> By Department</h4>
        </div>
        @if($byDept->isEmpty())
        <div class="px-5 py-8 text-center text-gray-400 text-xs">No department assignments</div>
        @else
        <div class="px-5 py-4 space-y-2.5">
            @foreach($byDept as $row)
            <div>
                <div class="flex justify-between text-xs mb-0.5">
                    <span class="text-gray-700 truncate max-w-[70%]">{{ $row->name }}</span>
                    <span class="font-semibold text-gray-600 tabular-nums">{{ $row->n }}</span>
                </div>
                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full" style="width:{{ round(($row->n/$maxDept)*100) }}%;background-color:{{ $mc }};"></div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- By role + expiring --}}
    <div class="space-y-4">
        {{-- By role --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h4 class="font-semibold text-gray-800 text-sm flex items-center gap-2"><i class="fas fa-shield-alt text-indigo-400 text-xs"></i> By Role</h4>
            </div>
            @if($byRole->isEmpty())
            <div class="px-5 py-4 text-center text-gray-400 text-xs">No role assignments</div>
            @else
            <div class="px-5 py-3 space-y-2">
                @foreach($byRole as $row)
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-700 flex-1 truncate">{{ $row->name }}</span>
                    <div class="w-24 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-indigo-400" style="width:{{ round(($row->n/$maxRole)*100) }}%;"></div>
                    </div>
                    <span class="text-xs font-bold text-gray-700 w-5 tabular-nums text-right">{{ $row->n }}</span>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Expiring --}}
        @if($expiring->isNotEmpty())
        <div class="bg-orange-50 border border-orange-200 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-orange-200">
                <h4 class="font-semibold text-orange-700 text-sm flex items-center gap-2"><i class="fas fa-clock text-xs"></i> Expiring Assignments</h4>
            </div>
            <div class="divide-y divide-orange-100">
                @foreach($expiring->take(6) as $a)
                @php $expired=$a->isExpired(); @endphp
                <div class="px-5 py-2.5 flex items-center gap-3">
                    <a href="{{ route('hr.employees.show', $a->employee_id) }}"
                       class="text-sm text-orange-800 font-medium hover:underline truncate flex-1">{{ $a->employee?->full_name }}</a>
                    <span class="text-xs {{ $expired ? 'text-red-600 font-bold' : 'text-orange-600' }}">
                        {{ $expired ? 'EXPIRED' : $a->planned_end_date->format('d M Y') }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endif
{{-- ── End tabs ── --}}

</div>
@endsection
