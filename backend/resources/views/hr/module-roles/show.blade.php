@extends('hr.layouts.app')
@section('title', $moduleRole->name)
@section('heading', $moduleRole->name)

@section('content')
<div class="max-w-4xl">

    {{-- Header row --}}
    <div class="flex items-center gap-4 mb-6">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                 style="background-color: {{ $moduleRole->module?->color ?? '#1B1444' }}20;">
                <i class="{{ $moduleRole->module?->icon ?? 'fas fa-cube' }} text-sm"
                   style="color: {{ $moduleRole->module?->color ?? '#1B1444' }};"></i>
            </div>
            <span class="text-sm text-gray-500">{{ $moduleRole->module?->name }}</span>
        </div>
        <span class="text-gray-300">/</span>
        <span class="font-semibold text-gray-800">{{ $moduleRole->name }}</span>
        <span class="text-xs px-2.5 py-0.5 rounded-full border
            {{ $moduleRole->status === 'active'
                ? 'bg-green-50 text-green-700 border-green-200'
                : 'bg-gray-50 text-gray-500 border-gray-200' }}">
            {{ $moduleRole->status_label }}
        </span>
        <div class="ml-auto flex gap-2">
            <a href="{{ route('hr.module-roles.edit', $moduleRole) }}"
               class="bg-[#1B1444] text-white text-sm px-4 py-2 rounded-lg hover:bg-opacity-90 transition">
                Edit Role
            </a>
            <a href="{{ route('hr.module-roles.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700 px-4 py-2">← Back</a>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg mb-4">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-center">
            <div class="text-2xl font-bold text-[#1B1444]">{{ $moduleRole->permissions->count() }}</div>
            <div class="text-xs text-gray-500 mt-1">Permissions</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-center">
            <div class="text-2xl font-bold text-[#1B1444]">{{ $moduleRole->workforceAssignments->count() }}</div>
            <div class="text-xs text-gray-500 mt-1">Employees Using</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-center">
            <div class="text-2xl font-bold text-[#F7941D]">{{ count($groups) }}</div>
            <div class="text-xs text-gray-500 mt-1">Permission Groups</div>
        </div>
    </div>

    {{-- Permission matrix --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-800">Permission Matrix</h2>
            <span class="text-xs text-gray-400">{{ $moduleRole->permissions->count() }} permissions granted</span>
        </div>

        <div class="divide-y divide-gray-50">
            @foreach($groups as $groupKey => $groupLabel)
            @php $perms = $permsByGroup->get($groupKey, collect()) @endphp
            <div class="px-6 py-4">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-2 h-2 rounded-full {{ $perms->isNotEmpty() ? 'bg-green-400' : 'bg-gray-200' }}"></div>
                    <span class="text-sm font-medium text-gray-700">{{ $groupLabel }}</span>
                    @if($perms->isNotEmpty())
                    <span class="text-xs text-green-600 bg-green-50 px-2 py-0.5 rounded-full">
                        {{ $perms->count() }} granted
                    </span>
                    @else
                    <span class="text-xs text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full">no access</span>
                    @endif
                </div>
                @if($perms->isNotEmpty())
                <div class="flex flex-wrap gap-2 ml-5">
                    @foreach($perms as $perm)
                    <span class="text-xs bg-indigo-50 text-indigo-700 border border-indigo-100 px-2.5 py-1 rounded-full font-mono">
                        {{ last(explode('.', $perm->slug)) }}
                    </span>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- Employees with this role --}}
    @if($moduleRole->workforceAssignments->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Employees With This Role</h2>
        </div>
        <ul class="divide-y divide-gray-50">
            @foreach($moduleRole->workforceAssignments as $wa)
            <li class="px-6 py-3 flex items-center gap-3">
                <div class="w-7 h-7 rounded-full bg-[#1B1444] text-white text-xs flex items-center justify-center font-bold">
                    {{ strtoupper(substr($wa->employee?->first_name ?? '?', 0, 1)) }}
                </div>
                <span class="text-sm text-gray-800">{{ $wa->employee?->full_name ?? '—' }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full border
                    {{ $wa->status === 'active' ? 'bg-green-50 text-green-600 border-green-200' : 'bg-gray-50 text-gray-400 border-gray-200' }}">
                    {{ $wa->status }}
                </span>
                <span class="ml-auto text-xs text-gray-400">
                    Assigned {{ $wa->assigned_at?->diffForHumans() }}
                </span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

</div>
@endsection
