@extends('hr.layouts.app')
@section('title', 'Workforce Assignments')
@section('heading', 'Workforce Assignments')

@section('content')
<div class="space-y-6">

    {{-- Header action --}}
    <div class="flex items-center justify-between gap-4">
        <div>
            <p class="text-sm text-gray-500">Assign employees to business modules and manage their access.</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <a href="{{ route('hr.workforce.dashboard') }}"
               class="border border-gray-200 text-gray-600 hover:border-[#1B1444] hover:text-[#1B1444] text-sm font-medium px-3 py-2 rounded-lg transition-colors flex items-center gap-1.5">
                <i class="fas fa-chart-bar text-xs text-indigo-400"></i> Dashboard
            </a>
            <a href="{{ route('hr.workforce.org-chart') }}"
               class="border border-gray-200 text-gray-600 hover:border-[#1B1444] hover:text-[#1B1444] text-sm font-medium px-3 py-2 rounded-lg transition-colors flex items-center gap-1.5">
                <i class="fas fa-sitemap text-xs text-blue-400"></i> Org Chart
            </a>
            @if(Auth::guard('hr')->user()->isManager() || Auth::guard('hr')->user()->canDo('workforce_assign'))
            <a href="{{ route('hr.workforce.wizard') }}"
               class="bg-[#F7941D] hover:bg-[#E07800] text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
                <i class="fas fa-magic text-xs"></i> New Assignment Wizard
            </a>
            @endif
        </div>
    </div>

    {{-- Module grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($modules as $module)
        <a href="{{ route('hr.workforce.module', $module) }}"
           class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 hover:shadow-md hover:border-gray-200 transition-all group block">
            <div class="flex items-start gap-4">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0"
                     style="background-color: {{ $module->color ?? '#1B1444' }}20;">
                    <i class="{{ $module->icon ?? 'fas fa-cube' }} text-lg"
                       style="color: {{ $module->color ?? '#1B1444' }};"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-semibold text-gray-900 text-sm group-hover:text-[#1B1444] transition-colors">
                            {{ $module->name }}
                        </h3>
                        @if(!$module->is_active)
                        <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full flex-shrink-0">Inactive</span>
                        @endif
                    </div>
                    <div class="mt-2 flex items-center gap-1.5">
                        <span class="text-2xl font-bold text-gray-800">{{ $module->total_assigned ?? 0 }}</span>
                        <span class="text-xs text-gray-400">employees assigned</span>
                    </div>
                </div>
            </div>
        </a>
        @endforeach
    </div>

    {{-- Recent assignments --}}
    @if($recentAssignments->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800 text-sm">Recent Assignments</h3>
        </div>
        <div class="divide-y divide-gray-50">
            @foreach($recentAssignments as $a)
            <div class="px-6 py-3 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-[#1B1444] flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                        {{ $a->employee?->initials }}
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-800">{{ $a->employee?->full_name }}</div>
                        <div class="text-xs text-gray-400">{{ $a->employee?->position?->title }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-gray-600 bg-gray-100 px-2.5 py-1 rounded-full">
                        {{ $a->module?->name }}
                    </span>
                    @if($a->role_in_module)
                    <span class="text-xs text-gray-400">{{ ucfirst($a->role_in_module) }}</span>
                    @endif
                    <span class="text-xs text-gray-300">{{ $a->assigned_at->diffForHumans() }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
