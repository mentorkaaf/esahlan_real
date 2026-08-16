@extends('hr.layouts.app')
@section('title', $employee->full_name)
@section('heading', $employee->full_name)

@section('content')
<div class="max-w-5xl space-y-6">

{{-- ══════════════════════════════════════════════════════════════════════════
     HEADER CARD
     ══════════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-start gap-5">
        {{-- Avatar --}}
        @if($employee->photo)
        <img src="{{ Storage::url($employee->photo) }}" class="w-20 h-20 rounded-xl object-cover flex-shrink-0">
        @else
        <div class="w-20 h-20 rounded-xl bg-[#1B1444] flex items-center justify-center text-white text-2xl font-bold flex-shrink-0">
            {{ $employee->initials }}
        </div>
        @endif

        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
                <h2 class="text-xl font-bold text-gray-900">{{ $employee->full_name }}</h2>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    {{ $employee->status_color === 'green'  ? 'bg-green-100 text-green-700'   :
                       ($employee->status_color === 'yellow'? 'bg-yellow-100 text-yellow-700' :
                       ($employee->status_color === 'red'   ? 'bg-red-100 text-red-700'       : 'bg-gray-100 text-gray-700')) }}">
                    {{ ucfirst($employee->status) }}
                </span>
                @if($primaryAssignment)
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-600 border border-indigo-100">
                    <i class="fas fa-cube text-[10px]"></i>
                    {{ $primaryAssignment->module?->name }}
                </span>
                @endif
            </div>
            <div class="text-gray-500 text-sm mt-1">
                {{ $employee->position?->title }}
                @if($employee->department) · {{ $employee->department->name }} @endif
            </div>
            <div class="text-gray-400 text-xs mt-0.5">{{ $employee->employee_no }}</div>

            {{-- Quick stats --}}
            <div class="flex gap-5 mt-4 flex-wrap text-xs text-gray-400">
                <span><i class="fas fa-layer-group mr-1"></i>{{ $activeAssignments->count() }} active module{{ $activeAssignments->count() === 1 ? '' : 's' }}</span>
                <span><i class="fas fa-shield-alt mr-1"></i>{{ $allPermissions->flatten()->count() }} permissions</span>
                <span><i class="fas fa-history mr-1"></i>{{ $pastAssignments->count() }} past assignment{{ $pastAssignments->count() === 1 ? '' : 's' }}</span>
            </div>

            {{-- Actions --}}
            <div class="flex gap-2 mt-4 flex-wrap">
                <a href="{{ route('hr.employees.edit', $employee) }}"
                   class="bg-[#1B1444] text-white text-xs px-4 py-2 rounded-lg hover:bg-[#2D2467] transition-colors">Edit Profile</a>
                <a href="{{ route('hr.workforce.wizard', ['employee_id' => $employee->id]) }}"
                   class="bg-[#F7941D] text-white text-xs px-4 py-2 rounded-lg hover:bg-[#E07800] transition-colors flex items-center gap-1.5">
                    <i class="fas fa-magic text-[10px]"></i> Assign to Module
                </a>
                <a href="{{ route('hr.employees.permissions', $employee) }}"
                   class="border border-gray-200 text-gray-600 text-xs px-4 py-2 rounded-lg hover:border-gray-400 transition-colors flex items-center gap-1.5">
                    <i class="fas fa-shield-alt text-[10px]"></i> View Permissions
                </a>
                <a href="{{ route('hr.workforce.employee', $employee) }}"
                   class="border border-gray-200 text-gray-600 text-xs px-4 py-2 rounded-lg hover:border-gray-400 transition-colors">History</a>
                @if(Auth::guard('hr')->user()->isManager() && $employee->status !== 'terminated')
                <button onclick="document.getElementById('terminate-modal').classList.remove('hidden')"
                        class="bg-red-50 text-red-600 text-xs px-4 py-2 rounded-lg hover:bg-red-100 transition-colors">Terminate</button>
                @endif
            </div>
        </div>
    </div>
</div>

@if(session('success'))
<div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">{{ session('success') }}</div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════
     MY WORKSPACES — shown when employee has 2+ active assignments
     ══════════════════════════════════════════════════════════════════════════ --}}
@if($activeAssignments->count() >= 1)
@php
$wsColors = [
    'primary'       => ['ring'=>'ring-[#1B1444]',  'bg'=>'bg-[#1B1444]',    'pill'=>'bg-indigo-50 text-indigo-700 border-indigo-200'],
    'secondary'     => ['ring'=>'ring-blue-400',    'bg'=>'bg-blue-500',     'pill'=>'bg-blue-50 text-blue-700 border-blue-200'],
    'temporary'     => ['ring'=>'ring-orange-400',  'bg'=>'bg-orange-500',   'pill'=>'bg-orange-50 text-orange-700 border-orange-200'],
    'acting'        => ['ring'=>'ring-purple-400',  'bg'=>'bg-purple-500',   'pill'=>'bg-purple-50 text-purple-700 border-purple-200'],
    'project_based' => ['ring'=>'ring-teal-400',    'bg'=>'bg-teal-600',     'pill'=>'bg-teal-50 text-teal-700 border-teal-200'],
];
$controls = [
    ['icon'=>'fas fa-compass',    'label'=>'Navigation',   'desc'=>'Sidebar menus and quick links'],
    ['icon'=>'fas fa-tachometer-alt','label'=>'Dashboard', 'desc'=>'KPIs, charts, and data widgets'],
    ['icon'=>'fas fa-database',   'label'=>'Module Data',  'desc'=>'Orders, inventory, records'],
    ['icon'=>'fas fa-shield-alt', 'label'=>'Permissions',  'desc'=>'Access rights and capabilities'],
    ['icon'=>'fas fa-chart-bar',  'label'=>'Reports',      'desc'=>'Analytics and exports'],
];
@endphp

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    {{-- Header --}}
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-[#1B1444] to-[#2D2467] flex items-center justify-center">
                <i class="fas fa-layer-group text-white text-xs"></i>
            </div>
            <div>
                <h3 class="font-semibold text-gray-800 text-sm">My Workspaces</h3>
                <p class="text-xs text-gray-400">{{ $activeAssignments->count() }} active assignment{{ $activeAssignments->count() === 1 ? '' : 's' }} · Active workspace controls navigation, dashboard, data, permissions &amp; reports</p>
            </div>
        </div>
        @if($resolvedWorkspace)
        <div class="flex items-center gap-2">
            <div class="flex items-center gap-1.5 bg-green-50 border border-green-200 text-green-700 text-xs px-3 py-1.5 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                Active: <span class="font-semibold ml-1">{{ $resolvedWorkspace->module?->name }}</span>
            </div>
        </div>
        @endif
    </div>

    @if(session('success') && str_contains(session('success'), 'workspace'))
    <div class="mx-6 mt-4 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2.5 rounded-lg flex items-center gap-2">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Workspace grid --}}
    <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($activeAssignments->sortByDesc(fn($a) => $a->assignment_type === 'primary') as $ws)
        @php
            $isActive = $resolvedWorkspace && $resolvedWorkspace->id === $ws->id;
            $colors   = $wsColors[$ws->assignment_type] ?? $wsColors['secondary'];
            $mod      = $ws->module;
        @endphp
        <div class="relative rounded-xl border-2 transition-all {{ $isActive ? $colors['ring'].' shadow-md' : 'border-gray-100 hover:border-gray-200' }} overflow-hidden">

            {{-- Active badge --}}
            @if($isActive)
            <div class="absolute top-3 right-3 z-10">
                <span class="flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full
                             {{ $colors['pill'] }} border">
                    <i class="fas fa-check-circle text-[9px]"></i> ACTIVE
                </span>
            </div>
            @endif

            {{-- Card body --}}
            <div class="p-4">
                {{-- Module icon + name --}}
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                         style="background-color: {{ $mod?->color ?? '#1B1444' }}20;">
                        <i class="{{ $mod?->icon ?? 'fas fa-cube' }} text-lg"
                           style="color: {{ $mod?->color ?? '#1B1444' }};"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="font-semibold text-gray-800 text-sm truncate">{{ $mod?->name }}</div>
                        <span class="inline-flex items-center text-[10px] border px-1.5 py-0.5 rounded {{ $colors['pill'] }}">
                            {{ $ws->assignment_type_label }}
                        </span>
                    </div>
                </div>

                {{-- Assignment details --}}
                <div class="space-y-1 mb-3">
                    @if($ws->moduleRole)
                    <div class="flex items-center gap-1.5 text-xs text-gray-500">
                        <i class="fas fa-shield-alt text-indigo-300 text-[10px] w-3"></i>
                        <span>{{ $ws->moduleRole->name }}</span>
                    </div>
                    @endif
                    @if($ws->moduleDepartment)
                    <div class="flex items-center gap-1.5 text-xs text-gray-500">
                        <i class="fas fa-building text-gray-300 text-[10px] w-3"></i>
                        <span>{{ $ws->moduleDepartment->name }}</span>
                    </div>
                    @endif
                    @if($ws->modulePosition)
                    <div class="flex items-center gap-1.5 text-xs text-gray-500">
                        <i class="fas fa-briefcase text-gray-300 text-[10px] w-3"></i>
                        <span>{{ $ws->modulePosition->name }}</span>
                    </div>
                    @endif
                    <div class="flex items-center gap-1.5 text-xs text-gray-500">
                        <i class="fas fa-lock text-gray-300 text-[10px] w-3"></i>
                        <span>{{ $ws->access_level_label }}</span>
                    </div>
                    @if($ws->planned_end_date)
                    <div class="flex items-center gap-1.5 text-xs {{ $ws->isExpired() ? 'text-red-500' : 'text-gray-400' }}">
                        <i class="fas fa-clock text-[10px] w-3"></i>
                        <span>{{ $ws->isExpired() ? 'Expired' : 'Until ' . $ws->planned_end_date->format('d M Y') }}</span>
                    </div>
                    @endif
                </div>

                {{-- What this workspace controls --}}
                @if($isActive)
                <div class="border-t border-gray-100 pt-2.5 mt-2.5">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Controls</p>
                    <div class="flex flex-wrap gap-1">
                        @foreach($controls as $ctrl)
                        <span class="flex items-center gap-1 text-[10px] bg-gray-50 border border-gray-200 text-gray-600 px-1.5 py-0.5 rounded">
                            <i class="{{ $ctrl['icon'] }} text-[9px] text-[#1B1444]"></i>
                            {{ $ctrl['label'] }}
                        </span>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Switch button --}}
                @if(!$isActive && Auth::guard('hr')->user()->isManager())
                <div class="border-t border-gray-100 pt-2.5 mt-2.5">
                    <form method="POST" action="{{ route('hr.employees.workspace.switch', $employee) }}">
                        @csrf
                        <input type="hidden" name="assignment_id" value="{{ $ws->id }}">
                        <button type="submit"
                                class="w-full text-xs font-semibold py-1.5 px-3 rounded-lg border border-gray-200
                                       text-gray-600 hover:text-[#1B1444] hover:border-[#1B1444] transition-colors
                                       flex items-center justify-center gap-1.5">
                            <i class="fas fa-exchange-alt text-[10px]"></i>
                            Set as Active Workspace
                        </button>
                    </form>
                </div>
                @elseif($isActive)
                <div class="border-t border-gray-100 pt-2.5 mt-2.5 text-center">
                    <span class="text-[10px] text-gray-400 flex items-center justify-center gap-1">
                        <i class="fas fa-check-circle text-green-500"></i>
                        Currently active workspace
                    </span>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Controls explanation footer --}}
    <div class="border-t border-gray-50 px-6 py-3 bg-gray-50/50">
        <div class="flex flex-wrap items-center gap-4">
            <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider flex-shrink-0">Active workspace controls:</p>
            <div class="flex flex-wrap gap-3">
                @foreach($controls as $ctrl)
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                    <i class="{{ $ctrl['icon'] }} text-[#1B1444] text-[11px]"></i>
                    <div>
                        <span class="font-medium">{{ $ctrl['label'] }}</span>
                        <span class="text-gray-400 hidden sm:inline"> — {{ $ctrl['desc'] }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════
     BUSINESS SUITE WORKSPACE
     ══════════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i class="fas fa-briefcase text-[#1B1444] text-sm"></i>
            <h3 class="font-semibold text-gray-800">Business Suite Workspace</h3>
        </div>
        <a href="{{ route('hr.workforce.wizard', ['employee_id' => $employee->id]) }}"
           class="text-xs bg-[#F7941D] text-white px-3 py-1.5 rounded-lg hover:bg-[#E07800] transition-colors flex items-center gap-1.5">
            <i class="fas fa-plus text-[10px]"></i> New Assignment
        </a>
    </div>

    @if($activeAssignments->isEmpty())
    <div class="px-6 py-10 text-center">
        <i class="fas fa-layer-group text-4xl text-gray-200 mb-3 block"></i>
        <p class="text-sm text-gray-400 mb-3">{{ $employee->first_name }} is not assigned to any module yet.</p>
        <a href="{{ route('hr.workforce.wizard', ['employee_id' => $employee->id]) }}"
           class="inline-flex items-center gap-1.5 bg-[#F7941D] text-white text-sm px-5 py-2.5 rounded-lg hover:bg-[#E07800] transition-colors">
            <i class="fas fa-magic text-xs"></i> Start Assignment Wizard
        </a>
    </div>
    @else

    {{-- ── PRIMARY ASSIGNMENT ────────────────────────────────────────────── --}}
    @if($primaryAssignment)
    @php $pa = $primaryAssignment; $pm = $pa->module; @endphp
    <div class="m-6 rounded-xl border-2 border-[#1B1444] overflow-hidden">
        {{-- Header --}}
        <div class="px-5 py-4 flex items-center gap-4"
             style="background: linear-gradient(135deg, #1B1444 0%, #2D2467 100%);">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background-color: {{ $pm?->color ?? '#F7941D' }}30;">
                <i class="{{ $pm?->icon ?? 'fas fa-cube' }} text-2xl"
                   style="color: {{ $pm?->color ?? '#F7941D' }};"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-white font-bold text-base">{{ $pm?->name }}</span>
                    <span class="text-[10px] bg-white/10 text-white border border-white/20 px-2 py-0.5 rounded-full">Primary</span>
                    <span class="text-[10px] bg-white/10 text-white border border-white/20 px-2 py-0.5 rounded-full">{{ $pa->access_level_label }}</span>
                </div>
                @if($pa->moduleRole)
                <div class="text-white/60 text-xs mt-0.5 flex items-center gap-1">
                    <i class="fas fa-shield-alt text-[10px]"></i>
                    {{ $pa->moduleRole->name }}
                </div>
                @endif
            </div>
            <div class="text-right flex-shrink-0">
                <div class="text-white/50 text-xs">Since</div>
                <div class="text-white text-sm font-semibold">{{ ($pa->start_date ?? $pa->assigned_at)->format('d M Y') }}</div>
                @if($pa->planned_end_date)
                <div class="text-white/50 text-xs mt-0.5">Until {{ $pa->planned_end_date->format('d M Y') }}</div>
                @endif
            </div>
        </div>

        {{-- Details grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-y divide-gray-100 bg-gray-50/50">
            <div class="px-4 py-3">
                <div class="text-xs text-gray-400 mb-0.5">Department</div>
                <div class="text-sm font-semibold text-gray-800">{{ $pa->moduleDepartment?->name ?? '—' }}</div>
            </div>
            <div class="px-4 py-3">
                <div class="text-xs text-gray-400 mb-0.5">Position</div>
                <div class="text-sm font-semibold text-gray-800">{{ $pa->modulePosition?->name ?? '—' }}</div>
            </div>
            <div class="px-4 py-3">
                <div class="text-xs text-gray-400 mb-0.5">Role Label</div>
                <div class="text-sm font-semibold text-gray-800 capitalize">{{ $pa->role_in_module ?? '—' }}</div>
            </div>
            <div class="px-4 py-3">
                <div class="text-xs text-gray-400 mb-0.5">Reports To</div>
                <div class="text-sm font-semibold text-gray-800">{{ $pa->reportingManager?->full_name ?? '—' }}</div>
            </div>
        </div>

        {{-- Permissions for primary role --}}
        @if($pa->moduleRole && $pa->moduleRole->permissions->isNotEmpty())
        @php $permsByGroup = $pa->moduleRole->permissions->groupBy('group'); @endphp
        <div class="px-5 py-4 border-t border-gray-100">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Permissions from {{ $pa->moduleRole->name }}</p>
            <div class="space-y-2">
                @foreach($permsByGroup as $group => $perms)
                <div class="flex items-start gap-3">
                    <span class="text-xs text-gray-400 w-24 flex-shrink-0 pt-0.5">{{ ucfirst(str_replace('_',' ',$group)) }}</span>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($perms as $p)
                        <span class="text-[11px] bg-indigo-50 text-indigo-700 border border-indigo-100 px-2 py-0.5 rounded font-mono">
                            {{ last(explode('.', $p->slug)) }}
                        </span>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Actions --}}
        <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 flex items-center gap-3">
            <a href="{{ route('hr.workforce.module', $pm) }}"
               class="text-xs text-[#1B1444] hover:text-[#F7941D] font-medium transition-colors">
                View Module Workforce →
            </a>
            @if(Auth::guard('hr')->user()->isManager())
            <div class="ml-auto flex gap-2">
                <form method="POST" action="{{ route('hr.workforce.suspend', $pa) }}"
                      onsubmit="return confirm('Suspend this assignment?')">
                    @csrf
                    <input type="hidden" name="reason" value="Suspended from employee profile">
                    <button class="text-xs text-yellow-600 hover:text-yellow-800">Suspend</button>
                </form>
                <form method="POST" action="{{ route('hr.workforce.destroy', $pa) }}"
                      onsubmit="return confirm('End the primary assignment for {{ addslashes($employee->first_name) }}?')">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-400 hover:text-red-600">End Assignment</button>
                </form>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ── SECONDARY / OTHER ACTIVE ASSIGNMENTS ─────────────────────────── --}}
    @if($secondaryAssignments->isNotEmpty())
    <div class="px-6 pb-6">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Additional Assignments</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($secondaryAssignments as $sa)
            @php $sm = $sa->module; @endphp
            @php
            $typeColors = [
                'secondary'     => 'border-blue-200 bg-blue-50/30',
                'temporary'     => 'border-orange-200 bg-orange-50/30',
                'acting'        => 'border-purple-200 bg-purple-50/30',
                'project_based' => 'border-teal-200 bg-teal-50/30',
            ];
            $typePillColors = [
                'secondary'     => 'bg-blue-50 text-blue-600 border-blue-100',
                'temporary'     => 'bg-orange-50 text-orange-600 border-orange-100',
                'acting'        => 'bg-purple-50 text-purple-600 border-purple-100',
                'project_based' => 'bg-teal-50 text-teal-600 border-teal-100',
            ];
            $borderClass = $typeColors[$sa->assignment_type] ?? 'border-gray-100';
            $pillClass   = $typePillColors[$sa->assignment_type] ?? 'bg-gray-50 text-gray-500 border-gray-200';
            @endphp
            <div class="rounded-xl border {{ $borderClass }} overflow-hidden">
                {{-- Card header --}}
                <div class="px-4 py-3 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                         style="background-color: {{ $sm?->color ?? '#1B1444' }}20;">
                        <i class="{{ $sm?->icon ?? 'fas fa-cube' }} text-base"
                           style="color: {{ $sm?->color ?? '#1B1444' }};"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-gray-800 text-sm">{{ $sm?->name }}</span>
                            <span class="text-[10px] border px-1.5 py-0.5 rounded {{ $pillClass }}">
                                {{ $sa->assignment_type_label }}
                            </span>
                            @if($sa->planned_end_date && $sa->isExpired())
                            <span class="text-[10px] bg-red-50 text-red-500 border border-red-100 px-1.5 py-0.5 rounded">Expired</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-400 mt-0.5">
                            @if($sa->moduleDepartment) {{ $sa->moduleDepartment->name }} @endif
                            @if($sa->modulePosition) · {{ $sa->modulePosition->name }} @endif
                        </div>
                    </div>
                </div>

                {{-- Details row --}}
                <div class="border-t border-gray-100/80 px-4 py-2 flex flex-wrap gap-x-4 gap-y-1 bg-white/60">
                    @if($sa->moduleRole)
                    <span class="text-xs text-gray-500 flex items-center gap-1">
                        <i class="fas fa-shield-alt text-indigo-300 text-[10px]"></i>
                        {{ $sa->moduleRole->name }}
                    </span>
                    @endif
                    <span class="text-xs text-gray-400 flex items-center gap-1">
                        <i class="fas fa-lock text-[10px]"></i>{{ $sa->access_level_label }}
                    </span>
                    @if($sa->start_date)
                    <span class="text-xs text-gray-400">From {{ $sa->start_date->format('d M Y') }}</span>
                    @endif
                    @if($sa->planned_end_date)
                    <span class="text-xs text-gray-400">→ {{ $sa->planned_end_date->format('d M Y') }}</span>
                    @endif
                </div>

                {{-- Permissions chips --}}
                @if($sa->moduleRole && $sa->moduleRole->permissions->count() > 0)
                <div class="border-t border-gray-100/80 px-4 py-2.5 bg-white/60 flex flex-wrap gap-1.5">
                    @foreach($sa->moduleRole->permissions->take(8) as $p)
                    <span class="text-[10px] bg-indigo-50 text-indigo-600 border border-indigo-100 px-1.5 py-0.5 rounded font-mono">
                        {{ last(explode('.', $p->slug)) }}
                    </span>
                    @endforeach
                    @if($sa->moduleRole->permissions->count() > 8)
                    <span class="text-[10px] text-gray-400">+{{ $sa->moduleRole->permissions->count() - 8 }} more</span>
                    @endif
                </div>
                @endif

                {{-- Actions --}}
                @if(Auth::guard('hr')->user()->isManager())
                <div class="border-t border-gray-100/80 px-4 py-2 flex items-center gap-3 bg-white/60">
                    <a href="{{ route('hr.workforce.module', $sm) }}" class="text-xs text-gray-400 hover:text-[#1B1444]">View Module</a>
                    <div class="ml-auto flex gap-2">
                        <form method="POST" action="{{ route('hr.workforce.suspend', $sa) }}"
                              onsubmit="return confirm('Suspend?')">
                            @csrf
                            <input type="hidden" name="reason" value="Suspended from employee profile">
                            <button class="text-xs text-yellow-500 hover:text-yellow-700">Suspend</button>
                        </form>
                        <form method="POST" action="{{ route('hr.workforce.destroy', $sa) }}"
                              onsubmit="return confirm('End this assignment?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-400 hover:text-red-600">End</button>
                        </form>
                    </div>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @endif {{-- end activeAssignments isEmpty check --}}
</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     COMBINED PERMISSIONS MATRIX
     ══════════════════════════════════════════════════════════════════════════ --}}
@if($allPermissions->isNotEmpty())
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i class="fas fa-shield-alt text-indigo-400 text-sm"></i>
            <h3 class="font-semibold text-gray-800">All Active Permissions</h3>
            <span class="text-xs bg-indigo-50 text-indigo-600 border border-indigo-100 px-2 py-0.5 rounded-full">
                {{ $allPermissions->flatten()->count() }} total
            </span>
        </div>
        <a href="{{ route('hr.employees.permissions', $employee) }}"
           class="text-xs text-indigo-500 hover:underline">View detail →</a>
    </div>

    @php
    $groups = \App\Models\ModuleRole::permissionGroups();
    @endphp

    <div class="divide-y divide-gray-50">
        @foreach($groups as $groupKey => $groupLabel)
        @php $perms = $allPermissions->get($groupKey, collect()) @endphp
        @if($perms->isNotEmpty())
        <div class="px-6 py-3 flex items-start gap-4">
            <div class="w-28 flex-shrink-0 text-xs font-medium text-gray-500 pt-0.5">{{ $groupLabel }}</div>
            <div class="flex flex-wrap gap-1.5">
                @foreach($perms as $p)
                <span class="text-[11px] bg-indigo-50 text-indigo-700 border border-indigo-100 px-2 py-0.5 rounded font-mono">
                    {{ last(explode('.', $p->slug)) }}
                </span>
                @endforeach
            </div>
        </div>
        @endif
        @endforeach
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════
     ASSIGNMENT HISTORY
     ══════════════════════════════════════════════════════════════════════════ --}}
@if($pastAssignments->isNotEmpty())
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Assignment History</h3>
    </div>

    {{-- Timeline --}}
    <div class="px-6 py-4 space-y-0">
        @foreach($pastAssignments->sortByDesc('ended_at') as $ha)
        <div class="flex gap-4 pb-6 last:pb-0 relative">
            {{-- Timeline line --}}
            <div class="flex flex-col items-center flex-shrink-0">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center
                            {{ $ha->status === 'ended' ? 'bg-gray-100' : 'bg-yellow-50' }}">
                    <i class="{{ $ha->module?->icon ?? 'fas fa-cube' }} text-sm
                               {{ $ha->status === 'ended' ? 'text-gray-400' : 'text-yellow-500' }}"></i>
                </div>
                <div class="w-px flex-1 bg-gray-100 mt-2 last:hidden min-h-[24px]"></div>
            </div>

            {{-- Content --}}
            <div class="flex-1 min-w-0 pt-1">
                <div class="flex items-start justify-between gap-2 flex-wrap">
                    <div>
                        <span class="font-semibold text-gray-800 text-sm">{{ $ha->module?->name ?? '—' }}</span>
                        <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                            <span class="text-[10px] border px-1.5 py-0.5 rounded
                                {{ $ha->status === 'ended' ? 'bg-gray-50 text-gray-400 border-gray-200' : 'bg-yellow-50 text-yellow-600 border-yellow-200' }}">
                                {{ ucfirst($ha->status) }}
                            </span>
                            <span class="text-[10px] bg-gray-50 text-gray-500 border border-gray-200 px-1.5 py-0.5 rounded">
                                {{ $ha->assignment_type_label }}
                            </span>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <div class="text-xs text-gray-400">
                            {{ ($ha->start_date ?? $ha->assigned_at)->format('d M Y') }}
                            → {{ $ha->ended_at?->format('d M Y') ?? $ha->planned_end_date?->format('d M Y') ?? 'Present' }}
                        </div>
                        @php
                            $from = $ha->start_date ?? $ha->assigned_at;
                            $to   = $ha->ended_at ?? now();
                            $dur  = $from->diffInDays($to);
                        @endphp
                        <div class="text-xs text-gray-300">{{ $dur }} day{{ $dur === 1 ? '' : 's' }}</div>
                    </div>
                </div>

                {{-- Details --}}
                <div class="text-xs text-gray-400 mt-1.5 flex flex-wrap gap-x-3 gap-y-1">
                    @if($ha->moduleDepartment) <span><i class="fas fa-building text-[10px] mr-1"></i>{{ $ha->moduleDepartment->name }}</span> @endif
                    @if($ha->modulePosition)   <span><i class="fas fa-briefcase text-[10px] mr-1"></i>{{ $ha->modulePosition->name }}</span> @endif
                    @if($ha->moduleRole)       <span><i class="fas fa-shield-alt text-[10px] mr-1"></i>{{ $ha->moduleRole->name }}</span> @endif
                    @if($ha->reportingManager) <span><i class="fas fa-user-tie text-[10px] mr-1"></i>Reported to {{ $ha->reportingManager->full_name }}</span> @endif
                    @if($ha->access_level)     <span><i class="fas fa-lock text-[10px] mr-1"></i>{{ $ha->access_level_label }}</span> @endif
                </div>

                @if($ha->notes)
                <p class="text-xs text-gray-400 mt-1.5 italic line-clamp-1">"{{ $ha->notes }}"</p>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════
     PERSONAL & EMPLOYMENT DETAILS
     ══════════════════════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wider">Personal</h3>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-gray-400">Gender</dt><dd class="text-gray-700 capitalize">{{ $employee->gender }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-400">Date of Birth</dt><dd class="text-gray-700">{{ $employee->dob?->format('d M Y') ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-400">Phone</dt><dd class="text-gray-700">{{ $employee->phone }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-400">Email</dt><dd class="text-gray-700">{{ $employee->email ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-400">District</dt><dd class="text-gray-700">{{ $employee->district ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-400">Address</dt><dd class="text-gray-700">{{ $employee->address ?? '—' }}</dd></div>
        </dl>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-700 mb-4 text-sm uppercase tracking-wider">Employment</h3>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-gray-400">Type</dt><dd class="text-gray-700 capitalize">{{ str_replace('_',' ',$employee->employment_type) }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-400">Hire Date</dt><dd class="text-gray-700">{{ $employee->hire_date?->format('d M Y') }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-400">Probation End</dt><dd class="text-gray-700">{{ $employee->probation_end?->format('d M Y') ?? '—' }}</dd></div>
            @if(Auth::guard('hr')->user()->canDo('view_salary'))
            <div class="flex justify-between"><dt class="text-gray-400">Base Salary</dt><dd class="text-gray-700">${{ number_format($employee->base_salary, 2) }}</dd></div>
            @endif
        </dl>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     CONTRACTS
     ══════════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-800">Contracts</h3>
        <a href="{{ route('hr.employees.contracts.create', $employee) }}"
           class="text-xs bg-[#F7941D] text-white px-3 py-1.5 rounded-lg hover:bg-[#E07800] transition-colors">+ Add</a>
    </div>
    <div class="divide-y divide-gray-50">
        @forelse($employee->contracts as $contract)
        <div class="px-6 py-4 flex items-center justify-between">
            <div>
                <div class="font-medium text-sm text-gray-800">{{ $contract->type }}</div>
                <div class="text-xs text-gray-400">{{ $contract->start_date?->format('d M Y') }} — {{ $contract->end_date?->format('d M Y') ?? 'Ongoing' }}</div>
            </div>
            <div class="text-right">
                @if(Auth::guard('hr')->user()->canDo('view_salary'))
                <div class="text-sm font-semibold text-gray-700">${{ number_format($contract->salary, 2) }}</div>
                @endif
                <span class="text-xs {{ $contract->status === 'active' ? 'text-green-600' : 'text-gray-400' }}">{{ ucfirst($contract->status) }}</span>
            </div>
        </div>
        @empty
        <div class="px-6 py-6 text-center text-gray-400 text-sm">No contracts</div>
        @endforelse
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════════
     DOCUMENTS
     ══════════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-800">Documents</h3>
        <a href="{{ route('hr.employees.documents.create', $employee) }}"
           class="text-xs bg-[#F7941D] text-white px-3 py-1.5 rounded-lg hover:bg-[#E07800] transition-colors">+ Upload</a>
    </div>
    <div class="divide-y divide-gray-50">
        @forelse($employee->documents as $doc)
        <div class="px-6 py-4 flex items-center justify-between">
            <div>
                <div class="font-medium text-sm text-gray-800">{{ $doc->title }}</div>
                <div class="text-xs text-gray-400">{{ $doc->type_label }}{{ $doc->expires_at ? ' · Expires ' . $doc->expires_at->format('d M Y') : '' }}</div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('hr.documents.show', $doc) }}" target="_blank"
                   class="text-xs text-[#1B1444] hover:underline">View</a>
                <form method="POST" action="{{ route('hr.documents.destroy', $doc) }}"
                      onsubmit="return confirm('Delete this document?')">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-400 hover:text-red-600">Delete</button>
                </form>
            </div>
        </div>
        @empty
        <div class="px-6 py-6 text-center text-gray-400 text-sm">No documents</div>
        @endforelse
    </div>
</div>

</div>{{-- end max-w-5xl --}}

{{-- ── Terminate modal ─────────────────────────────────────────────────────── --}}
@if(Auth::guard('hr')->user()->isManager())
<div id="terminate-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl p-6 max-w-sm w-full">
        <h3 class="font-bold text-gray-900 mb-2">Terminate Employee</h3>
        <p class="text-sm text-gray-500 mb-4">This action cannot be undone. Please provide a reason.</p>
        <form method="POST" action="{{ route('hr.employees.terminate', $employee) }}">
            @csrf
            <textarea name="reason" required rows="3" placeholder="Reason for termination…"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 mb-4"></textarea>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-red-600 text-white py-2.5 rounded-lg text-sm font-semibold hover:bg-red-700 transition-colors">Terminate</button>
                <button type="button" onclick="document.getElementById('terminate-modal').classList.add('hidden')"
                        class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm hover:bg-gray-50 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
