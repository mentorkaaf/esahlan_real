@extends('hr.layouts.app')
@section('title', 'Module Roles')
@section('heading', 'Module Roles & Permissions')

@section('content')

{{-- Filter bar --}}
<div class="flex flex-wrap items-center gap-3 mb-6">
    <form method="GET" action="{{ route('hr.module-roles.index') }}" class="flex gap-2">
        <select name="module_id" onchange="this.form.submit()"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
            <option value="">All Modules</option>
            @foreach($modules as $mod)
            <option value="{{ $mod->id }}" {{ request('module_id') == $mod->id ? 'selected' : '' }}>
                {{ $mod->name }}
            </option>
            @endforeach
        </select>
    </form>

    <a href="{{ route('hr.module-roles.create') }}"
       class="ml-auto bg-[#F7941D] hover:bg-[#E07800] text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
        + New Role
    </a>
</div>

@if(session('success'))
<div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg mb-4">{{ session('success') }}</div>
@endif

@forelse($roles as $moduleId => $moduleRoles)
    @php $mod = $moduleRoles->first()->module @endphp
    <div class="mb-8">
        {{-- Module header --}}
        <div class="flex items-center gap-2 mb-3">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center"
                 style="background-color: {{ $mod?->color ?? '#1B1444' }}20;">
                <i class="{{ $mod?->icon ?? 'fas fa-cube' }} text-xs"
                   style="color: {{ $mod?->color ?? '#1B1444' }};"></i>
            </div>
            <h3 class="font-semibold text-gray-800">{{ $mod?->name ?? 'Unknown Module' }}</h3>
            <span class="text-xs text-gray-400">({{ $moduleRoles->count() }} roles)</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($moduleRoles->sortBy('sort_order') as $role)
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex flex-col gap-3">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-800 text-sm">{{ $role->name }}</span>
                            @if($role->is_default)
                            <span class="text-[10px] bg-indigo-50 text-indigo-600 border border-indigo-100 px-1.5 py-0.5 rounded">Default</span>
                            @endif
                            @if($role->is_system)
                            <span class="text-[10px] bg-gray-50 text-gray-500 border border-gray-200 px-1.5 py-0.5 rounded">System</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ $role->description }}</p>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full border
                        {{ $role->status === 'active'
                            ? 'bg-green-50 text-green-700 border-green-200'
                            : 'bg-gray-50 text-gray-500 border-gray-200' }}">
                        {{ $role->status_label }}
                    </span>
                </div>

                <div class="flex items-center gap-4 text-xs text-gray-500">
                    <span><i class="fas fa-shield-alt mr-1"></i>{{ $role->permissions_count ?? '—' }} perms</span>
                    <span><i class="fas fa-users mr-1"></i>{{ $role->employees_count }} employees</span>
                </div>

                <div class="flex items-center gap-2 pt-1 border-t border-gray-50">
                    <a href="{{ route('hr.module-roles.show', $role) }}"
                       class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">View</a>
                    <span class="text-gray-300">·</span>
                    <a href="{{ route('hr.module-roles.edit', $role) }}"
                       class="text-xs text-gray-500 hover:text-gray-700">Edit</a>
                    @if(!$role->is_system)
                    <span class="text-gray-300">·</span>
                    <form method="POST" action="{{ route('hr.module-roles.toggle-status', $role) }}"
                          class="inline" onsubmit="return confirm('Change role status?')">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-xs text-gray-400 hover:text-red-500">
                            {{ $role->status === 'active' ? 'Archive' : 'Restore' }}
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
@empty
<div class="text-center py-12 text-gray-400">
    <i class="fas fa-shield-alt text-4xl mb-3 block opacity-30"></i>
    No module roles found. <a href="{{ route('hr.module-roles.create') }}" class="text-indigo-500 hover:underline">Create one</a>.
</div>
@endforelse

@endsection
