@extends('hr.layouts.app')
@section('title', 'Permissions — ' . $employee->full_name)
@section('heading', $employee->full_name . ' — Module Permissions')

@section('content')
<div class="max-w-3xl">

    {{-- Back link --}}
    <div class="mb-5">
        <a href="{{ route('hr.employees.show', $employee) }}"
           class="text-sm text-gray-500 hover:text-gray-700">
            ← Back to employee profile
        </a>
    </div>

    @if($assignments->isEmpty())
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-10 text-center text-gray-400">
        <i class="fas fa-shield-alt text-4xl mb-3 block opacity-25"></i>
        <p class="text-sm">This employee has no active module assignments with roles.</p>
        <a href="{{ route('hr.workforce.create', ['employee_id' => $employee->id]) }}"
           class="mt-3 inline-block text-indigo-500 hover:underline text-sm">Assign to a module</a>
    </div>
    @else
    <p class="text-sm text-gray-500 mb-5">
        Showing permissions inherited from active module role assignments.
    </p>

    @foreach($assignments as $assignment)
    @php
        $module     = $assignment->module;
        $moduleRole = $assignment->moduleRole;
        $perms      = $moduleRole ? $moduleRole->permissions->groupBy('group') : collect();
    @endphp
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-6">
        {{-- Module header --}}
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                 style="background-color: {{ $module?->color ?? '#1B1444' }}20;">
                <i class="{{ $module?->icon ?? 'fas fa-cube' }} text-sm"
                   style="color: {{ $module?->color ?? '#1B1444' }};"></i>
            </div>
            <div>
                <div class="font-semibold text-gray-800 text-sm">{{ $module?->name ?? '—' }}</div>
                <div class="text-xs text-gray-500">
                    @if($moduleRole)
                    Role: <strong>{{ $moduleRole->name }}</strong> ·
                    @else
                    No module role assigned ·
                    @endif
                    Assigned {{ $assignment->assigned_at?->diffForHumans() }}
                </div>
            </div>
            <span class="ml-auto text-xs px-2.5 py-0.5 rounded-full border
                {{ $assignment->status === 'active'
                    ? 'bg-green-50 text-green-600 border-green-200'
                    : 'bg-yellow-50 text-yellow-600 border-yellow-200' }}">
                {{ $assignment->status }}
            </span>
        </div>

        @if(!$moduleRole)
        <div class="px-6 py-4 text-sm text-gray-400 italic">No role assigned — no permissions.</div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($groups as $groupKey => $groupLabel)
            @php $groupPerms = $perms->get($groupKey, collect()) @endphp
            <div class="px-6 py-3 flex items-start gap-4">
                <div class="w-28 flex-shrink-0 text-xs font-medium text-gray-600 pt-0.5">{{ $groupLabel }}</div>
                @if($groupPerms->isEmpty())
                <span class="text-xs text-gray-300">—</span>
                @else
                <div class="flex flex-wrap gap-1.5">
                    @foreach($groupPerms as $perm)
                    <span class="text-[11px] bg-indigo-50 text-indigo-700 border border-indigo-100 px-2 py-0.5 rounded font-mono">
                        {{ last(explode('.', $perm->slug)) }}
                    </span>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
    @endforeach
    @endif

</div>
@endsection
