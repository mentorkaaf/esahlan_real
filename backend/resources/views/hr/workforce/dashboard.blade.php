@extends('hr.layouts.app')
@section('title', 'Workforce Dashboard')
@section('heading', 'Workforce Dashboard')

@section('content')
@php
$typeColors = [
    'primary'       => ['bg'=>'bg-indigo-500',  'light'=>'bg-indigo-50 text-indigo-700', 'label'=>'Primary'],
    'secondary'     => ['bg'=>'bg-blue-500',    'light'=>'bg-blue-50 text-blue-700',    'label'=>'Secondary'],
    'temporary'     => ['bg'=>'bg-orange-500',  'light'=>'bg-orange-50 text-orange-700','label'=>'Temporary'],
    'acting'        => ['bg'=>'bg-purple-500',  'light'=>'bg-purple-50 text-purple-700','label'=>'Acting'],
    'project_based' => ['bg'=>'bg-teal-500',    'light'=>'bg-teal-50 text-teal-700',   'label'=>'Project'],
];
$accessColors = [
    'read_only' => 'bg-gray-400',
    'standard'  => 'bg-blue-400',
    'elevated'  => 'bg-orange-400',
    'admin'     => 'bg-red-400',
];
$totalAssignments = max(array_sum($typeBreakdown), 1);
$totalAccess      = max(array_sum($accessBreakdown), 1);
$maxModuleCount   = max($moduleDistribution->max('total') ?? 1, 1);
$maxDeptCount     = max($deptDistribution->max('total') ?? 1, 1);
$maxPosCount      = max($posDistribution->max('total') ?? 1, 1);
@endphp

<div class="space-y-6">

{{-- ── Header row ──────────────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between">
    <p class="text-sm text-gray-500">Real-time workforce data across all Business Suite modules.</p>
    <div class="flex items-center gap-2">
        <a href="{{ route('hr.workforce.org-chart') }}"
           class="flex items-center gap-2 bg-[#1B1444] hover:bg-[#2D2467] text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
            <i class="fas fa-sitemap text-xs"></i> Org Chart
        </a>
        <a href="{{ route('hr.workforce.wizard') }}"
           class="flex items-center gap-2 bg-[#F7941D] hover:bg-[#E07800] text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
            <i class="fas fa-magic text-xs"></i> New Assignment
        </a>
    </div>
</div>

{{-- ── KPI Cards ────────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    @php
    $kpis = [
        ['icon'=>'fas fa-users',         'value'=>$totalActive,  'label'=>'Active Assignments', 'sub'=>'Across all modules',       'color'=>'text-indigo-500', 'bg'=>'bg-indigo-50'],
        ['icon'=>'fas fa-star',           'value'=>$totalPrimary, 'label'=>'Primary Assignments','sub'=>'One per employee',         'color'=>'text-[#1B1444]', 'bg'=>'bg-slate-50'],
        ['icon'=>'fas fa-cubes',          'value'=>$totalModules, 'label'=>'Modules Active',    'sub'=>'With assigned staff',       'color'=>'text-[#F7941D]', 'bg'=>'bg-orange-50'],
        ['icon'=>'fas fa-user-plus',      'value'=>$newThisWeek, 'label'=>'New This Week',       'sub'=>'Assignments in last 7 days','color'=>'text-green-500', 'bg'=>'bg-green-50'],
    ];
    @endphp
    @foreach($kpis as $kpi)
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-start justify-between mb-3">
            <div class="w-10 h-10 rounded-xl {{ $kpi['bg'] }} flex items-center justify-center">
                <i class="{{ $kpi['icon'] }} {{ $kpi['color'] }} text-lg"></i>
            </div>
        </div>
        <div class="text-3xl font-bold text-gray-900 tabular-nums">{{ number_format($kpi['value']) }}</div>
        <div class="text-sm font-semibold text-gray-700 mt-0.5">{{ $kpi['label'] }}</div>
        <div class="text-xs text-gray-400">{{ $kpi['sub'] }}</div>
    </div>
    @endforeach
</div>

{{-- ── Alerts: expiring / expired ─────────────────────────────────────────── --}}
@if($expiredCount > 0 || $expiringSoon > 0)
<div class="flex flex-wrap gap-3">
    @if($expiredCount > 0)
    <div class="flex items-center gap-2.5 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2.5 rounded-xl">
        <i class="fas fa-exclamation-circle"></i>
        <span><strong>{{ $expiredCount }}</strong> assignment{{ $expiredCount > 1 ? 's' : '' }} expired — needs attention</span>
    </div>
    @endif
    @if($expiringSoon > 0)
    <div class="flex items-center gap-2.5 bg-orange-50 border border-orange-200 text-orange-700 text-sm px-4 py-2.5 rounded-xl">
        <i class="fas fa-clock"></i>
        <span><strong>{{ $expiringSoon }}</strong> assignment{{ $expiringSoon > 1 ? 's' : '' }} expiring within 14 days</span>
    </div>
    @endif
</div>
@endif

{{-- ── Row 1: Module Distribution + Assignment Types ────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    {{-- Module Distribution (2/3 width) --}}
    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-sm flex items-center gap-2">
                <i class="fas fa-cubes text-[#1B1444] text-xs"></i> Module Distribution
            </h3>
            <span class="text-xs text-gray-400">{{ $moduleDistribution->count() }} modules</span>
        </div>

        @if($moduleDistribution->isEmpty())
        <div class="px-6 py-10 text-center text-gray-400 text-sm">No assignments yet</div>
        @else
        <div class="px-6 py-4 space-y-3">
            @foreach($moduleDistribution as $mod)
            @php $pct = round(($mod->total / $maxModuleCount) * 100); @endphp
            <div>
                <div class="flex items-center justify-between mb-1">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-md flex items-center justify-center flex-shrink-0"
                             style="background-color: {{ $mod->color ?? '#1B1444' }}20;">
                            <i class="{{ $mod->icon ?? 'fas fa-cube' }} text-[10px]"
                               style="color: {{ $mod->color ?? '#1B1444' }};"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-700">{{ $mod->name }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <span class="text-[10px] bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded">{{ $mod->primary_count }} primary</span>
                        <span class="font-semibold tabular-nums">{{ $mod->total }}</span>
                    </div>
                </div>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all"
                         style="width: {{ $pct }}%; background-color: {{ $mod->color ?? '#1B1444' }};"></div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Assignment Type Breakdown (1/3 width) --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800 text-sm flex items-center gap-2">
                <i class="fas fa-tags text-[#F7941D] text-xs"></i> Assignment Types
            </h3>
        </div>

        {{-- Donut (CSS conic-gradient) --}}
        @php
        $segments = [];
        $offset   = 0;
        $typeOrder = ['primary','secondary','temporary','acting','project_based'];
        $typeHex   = ['primary'=>'#4F46E5','secondary'=>'#3B82F6','temporary'=>'#F97316','acting'=>'#A855F7','project_based'=>'#14B8A6'];
        foreach($typeOrder as $t) {
            $count = $typeBreakdown[$t] ?? 0;
            if ($count > 0) {
                $deg  = round(($count / $totalAssignments) * 360);
                $segments[] = ['type'=>$t,'count'=>$count,'deg'=>$deg,'offset'=>$offset,'color'=>$typeHex[$t]];
                $offset += $deg;
            }
        }
        $gradientStops = '';
        $cur = 0;
        foreach($segments as $s) {
            $gradientStops .= "{$s['color']} {$cur}deg " . ($cur + $s['deg']) . "deg, ";
            $cur += $s['deg'];
        }
        $gradientStops = rtrim($gradientStops, ', ');
        @endphp

        <div class="px-6 py-5">
            @if($totalAssignments > 0 && $gradientStops)
            <div class="flex justify-center mb-5">
                <div class="relative w-32 h-32">
                    <div class="w-32 h-32 rounded-full"
                         style="background: conic-gradient({{ $gradientStops }});"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-20 h-20 rounded-full bg-white flex flex-col items-center justify-center shadow-inner">
                            <span class="text-2xl font-bold text-gray-800 tabular-nums">{{ $totalAssignments }}</span>
                            <span class="text-[10px] text-gray-400">total</span>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            <div class="space-y-2">
                @foreach($typeOrder as $t)
                @php $count = $typeBreakdown[$t] ?? 0; if(!$count) continue; @endphp
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                             style="background-color: {{ $typeHex[$t] }};"></div>
                        <span class="text-xs text-gray-600">{{ $typeColors[$t]['label'] }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-16 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full"
                                 style="width: {{ round(($count/$totalAssignments)*100) }}%; background-color: {{ $typeHex[$t] }};"></div>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 tabular-nums w-6 text-right">{{ $count }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- ── Row 2: Dept Distribution + Position Distribution + Access Level ─── --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    {{-- Department Distribution --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-sm flex items-center gap-2">
                <i class="fas fa-building text-blue-400 text-xs"></i> Department Distribution
            </h3>
        </div>
        @if($deptDistribution->isEmpty())
        <div class="px-6 py-8 text-center text-gray-400 text-xs">No department data</div>
        @else
        <div class="px-5 py-4 space-y-2.5">
            @foreach($deptDistribution as $dept)
            @php $pct = round(($dept->total / $maxDeptCount) * 100); @endphp
            <div>
                <div class="flex justify-between mb-0.5">
                    <span class="text-xs text-gray-700 truncate max-w-[65%]">{{ $dept->name }}</span>
                    <span class="text-xs font-semibold text-gray-600 tabular-nums">{{ $dept->total }}</span>
                </div>
                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-400 rounded-full" style="width: {{ $pct }}%;"></div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Position Distribution --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800 text-sm flex items-center gap-2">
                <i class="fas fa-briefcase text-orange-400 text-xs"></i> Position Distribution
            </h3>
        </div>
        @if($posDistribution->isEmpty())
        <div class="px-6 py-8 text-center text-gray-400 text-xs">No position data</div>
        @else
        <div class="px-5 py-4 space-y-2.5">
            @foreach($posDistribution as $pos)
            @php $pct = round(($pos->total / $maxPosCount) * 100); @endphp
            <div>
                <div class="flex justify-between mb-0.5">
                    <span class="text-xs text-gray-700 truncate max-w-[65%]">{{ $pos->name }}</span>
                    <span class="text-xs font-semibold text-gray-600 tabular-nums">{{ $pos->total }}</span>
                </div>
                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-orange-400 rounded-full" style="width: {{ $pct }}%;"></div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Access Level Breakdown --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800 text-sm flex items-center gap-2">
                <i class="fas fa-lock text-purple-400 text-xs"></i> Access Levels
            </h3>
        </div>
        @php
        $accessOrder  = ['admin','elevated','standard','read_only'];
        $accessLabels = ['admin'=>'Admin','elevated'=>'Elevated','standard'=>'Standard','read_only'=>'Read Only'];
        $accessHex    = ['admin'=>'#EF4444','elevated'=>'#F97316','standard'=>'#3B82F6','read_only'=>'#9CA3AF'];
        @endphp
        <div class="px-5 py-4 space-y-4">
            @foreach($accessOrder as $lvl)
            @php $count = $accessBreakdown[$lvl] ?? 0; @endphp
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background-color: {{ $accessHex[$lvl] }}15;">
                    <i class="fas fa-lock text-xs" style="color: {{ $accessHex[$lvl] }};"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between mb-1">
                        <span class="text-xs font-medium text-gray-700">{{ $accessLabels[$lvl] }}</span>
                        <span class="text-xs font-bold tabular-nums" style="color: {{ $accessHex[$lvl] }};">{{ $count }}</span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full" style="width: {{ $totalAccess > 0 ? round(($count/$totalAccess)*100) : 0 }}%; background-color: {{ $accessHex[$lvl] }};"></div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ── Row 3: Active Assignments List + Temporary / Expiring ───────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    {{-- Active Assignments --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-sm flex items-center gap-2">
                <i class="fas fa-check-circle text-green-400 text-xs"></i> Active Assignments
            </h3>
            <a href="{{ route('hr.workforce.index') }}" class="text-xs text-gray-400 hover:text-gray-600">View all →</a>
        </div>
        <div class="divide-y divide-gray-50 max-h-96 overflow-y-auto">
            @forelse($activeAssignments as $a)
            @php $tc = $typeColors[$a->assignment_type] ?? $typeColors['primary']; @endphp
            <div class="px-5 py-3 flex items-center gap-3 hover:bg-gray-50/60 transition-colors">
                {{-- Avatar --}}
                <div class="w-9 h-9 rounded-xl bg-[#1B1444] flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                    {{ $a->employee?->initials ?? '?' }}
                </div>
                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <a href="{{ route('hr.employees.show', $a->employee_id) }}"
                           class="text-sm font-semibold text-gray-800 hover:text-[#1B1444] truncate">
                            {{ $a->employee?->full_name }}
                        </a>
                        <span class="text-[10px] {{ $tc['light'] }} border border-current/20 px-1.5 py-0.5 rounded">
                            {{ $a->assignment_type_label }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-400 truncate">
                        {{ $a->module?->name }}
                        @if($a->moduleDepartment) · {{ $a->moduleDepartment->name }} @endif
                        @if($a->modulePosition) · {{ $a->modulePosition->name }} @endif
                    </div>
                </div>
                <div class="text-xs text-gray-300 flex-shrink-0">{{ $a->assigned_at->diffForHumans() }}</div>
            </div>
            @empty
            <div class="px-6 py-10 text-center text-gray-400 text-sm">No active assignments</div>
            @endforelse
        </div>
    </div>

    {{-- Temporary / Expiring Assignments --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-sm flex items-center gap-2">
                <i class="fas fa-hourglass-half text-orange-400 text-xs"></i> Temporary Assignments
            </h3>
            <span class="text-xs text-gray-400">{{ $temporaryAssignments->count() }} total</span>
        </div>
        <div class="divide-y divide-gray-50 max-h-96 overflow-y-auto">
            @forelse($temporaryAssignments as $a)
            @php
            $expired     = $a->isExpired();
            $expiringSoon= !$expired && $a->planned_end_date && $a->planned_end_date->lte(now()->addDays(14));
            $daysLeft    = $a->planned_end_date ? now()->diffInDays($a->planned_end_date, false) : null;
            @endphp
            <div class="px-5 py-3 flex items-center gap-3 {{ $expired ? 'bg-red-50/40' : ($expiringSoon ? 'bg-orange-50/40' : 'hover:bg-gray-50/60') }} transition-colors">
                <div class="w-9 h-9 rounded-xl {{ $expired ? 'bg-red-500' : ($expiringSoon ? 'bg-orange-400' : 'bg-[#1B1444]') }} flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                    {{ $a->employee?->initials ?? '?' }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <a href="{{ route('hr.employees.show', $a->employee_id) }}"
                           class="text-sm font-semibold text-gray-800 hover:text-[#1B1444] truncate">
                            {{ $a->employee?->full_name }}
                        </a>
                        @if($expired)
                        <span class="text-[10px] bg-red-100 text-red-600 border border-red-200 px-1.5 py-0.5 rounded font-semibold">EXPIRED</span>
                        @elseif($expiringSoon)
                        <span class="text-[10px] bg-orange-100 text-orange-600 border border-orange-200 px-1.5 py-0.5 rounded">{{ $daysLeft }}d left</span>
                        @endif
                    </div>
                    <div class="text-xs text-gray-400 truncate">
                        {{ $a->module?->name }} · {{ $a->assignment_type_label }}
                        @if($a->planned_end_date) · Until {{ $a->planned_end_date->format('d M Y') }} @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="px-6 py-10 text-center">
                <i class="fas fa-check-circle text-green-300 text-2xl mb-2 block"></i>
                <p class="text-gray-400 text-sm">No temporary assignments</p>
            </div>
            @endforelse
        </div>
    </div>
</div>

</div>
@endsection
