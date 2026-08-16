@extends('hr.layouts.app')
@section('title', 'Module Positions')
@section('heading', 'Module Positions')

@section('content')
<div class="space-y-5">

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('hr.module-positions.index') }}"
          class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4 flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-36">
            <label class="block text-xs font-medium text-gray-500 mb-1">Module</label>
            <select name="module_id" id="filter-module"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                <option value="">All Modules</option>
                @foreach($modules as $mod)
                <option value="{{ $mod->id }}" {{ request('module_id') == $mod->id ? 'selected' : '' }}>{{ $mod->name }}</option>
                @endforeach
            </select>
        </div>

        @if($depts->isNotEmpty())
        <div class="w-44">
            <label class="block text-xs font-medium text-gray-500 mb-1">Department</label>
            <select name="dept_id"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                <option value="">All Departments</option>
                @foreach($depts as $d)
                <option value="{{ $d->id }}" {{ request('dept_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <div class="w-36">
            <label class="block text-xs font-medium text-gray-500 mb-1">Level</label>
            <select name="level"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                <option value="">All Levels</option>
                @foreach(['junior','mid','senior','lead','manager'] as $lvl)
                <option value="{{ $lvl }}" {{ $level === $lvl ? 'selected' : '' }}>{{ ucfirst($lvl) }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-36">
            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
            <select name="status"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                <option value="active"   {{ $status === 'active'   ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                <option value="archived" {{ $status === 'archived' ? 'selected' : '' }}>Archived</option>
                <option value="all"      {{ $status === 'all'      ? 'selected' : '' }}>All</option>
            </select>
        </div>

        <button type="submit"
                class="bg-[#1B1444] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2D2467] transition-colors">
            Filter
        </button>
        <div class="ml-auto">
            <a href="{{ route('hr.module-positions.create', $selectedModule ? ['module_id' => $selectedModule->id] : []) }}"
               class="bg-[#F7941D] hover:bg-[#E07800] text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                + New Position
            </a>
        </div>
    </form>

    {{-- Context breadcrumb --}}
    @if($selectedModule || $selectedDept)
    <div class="flex items-center gap-2 text-sm">
        @if($selectedModule)
        <div class="flex items-center gap-1.5">
            <div class="w-5 h-5 rounded flex items-center justify-center"
                 style="background-color: {{ $selectedModule->color ?? '#1B1444' }}20;">
                <i class="{{ $selectedModule->icon ?? 'fas fa-cube' }} text-xs"
                   style="color: {{ $selectedModule->color ?? '#1B1444' }};"></i>
            </div>
            <span class="font-medium text-gray-700">{{ $selectedModule->name }}</span>
        </div>
        @endif
        @if($selectedDept)
        <span class="text-gray-400">/</span>
        <span class="text-gray-600">{{ $selectedDept->name }}</span>
        @endif
        <a href="{{ route('hr.module-positions.index') }}" class="text-xs text-gray-400 hover:text-gray-600 ml-1">× Clear</a>
    </div>
    @endif

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        @if($positions->isEmpty())
        <div class="px-6 py-16 text-center">
            <div class="text-gray-300 text-4xl mb-3"><i class="fas fa-id-badge"></i></div>
            <p class="text-gray-400 text-sm">No positions found. Run the seeder or create a new position.</p>
            <a href="{{ route('hr.module-positions.create') }}"
               class="mt-4 inline-block text-sm text-[#F7941D] hover:underline">Create a position →</a>
        </div>
        @else

        {{-- Stats summary --}}
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 flex items-center gap-6 text-xs text-gray-500">
            <span><strong class="text-gray-700">{{ $positions->count() }}</strong> positions</span>
            <span><strong class="text-gray-700">{{ $positions->where('active_assignments_count','>',0)->count() }}</strong> with active staff</span>
            @if($positions->where('hr_position_id','!=',null)->count())
            <span class="text-indigo-600 font-medium">
                <i class="fas fa-link text-indigo-400 mr-1"></i>
                {{ $positions->where('hr_position_id','!=',null)->count() }} linked to HR positions
            </span>
            @endif
        </div>

        <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[700px]">
            <thead>
                <tr class="bg-[#1B1444] text-white">
                    <th class="px-5 py-3 text-left font-medium text-xs uppercase tracking-wider">Position</th>
                    <th class="px-5 py-3 text-left font-medium text-xs uppercase tracking-wider">Module</th>
                    <th class="px-5 py-3 text-left font-medium text-xs uppercase tracking-wider">Department</th>
                    <th class="px-5 py-3 text-center font-medium text-xs uppercase tracking-wider">Level</th>
                    <th class="px-5 py-3 text-center font-medium text-xs uppercase tracking-wider">Filled</th>
                    <th class="px-5 py-3 text-center font-medium text-xs uppercase tracking-wider">Status</th>
                    <th class="px-5 py-3 text-right font-medium text-xs uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($positions as $pos)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('hr.module-positions.show', $pos) }}"
                               class="font-semibold text-gray-900 hover:text-[#1B1444]">{{ $pos->name }}</a>
                            @if($pos->hr_position_id)
                            <span title="Linked to existing HR position"
                                  class="inline-flex items-center text-indigo-500 text-xs">
                                <i class="fas fa-link text-xs"></i>
                            </span>
                            @endif
                        </div>
                        @if($pos->description)
                        <div class="text-xs text-gray-400 mt-0.5 max-w-xs truncate">{{ $pos->description }}</div>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-1.5">
                            <div class="w-5 h-5 rounded flex items-center justify-center"
                                 style="background-color: {{ $pos->module?->color ?? '#1B1444' }}20;">
                                <i class="{{ $pos->module?->icon ?? 'fas fa-cube' }} text-xs"
                                   style="color: {{ $pos->module?->color ?? '#1B1444' }};"></i>
                            </div>
                            <span class="text-gray-600 text-xs">{{ $pos->module?->name }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-gray-500">
                        {{ $pos->moduleDepartment?->name ?? '—' }}
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        @php
                            $levelColors = ['junior'=>'blue','mid'=>'indigo','senior'=>'purple','lead'=>'orange','manager'=>'red'];
                            $lc = $levelColors[$pos->level] ?? 'gray';
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                            bg-{{ $lc }}-100 text-{{ $lc }}-700">
                            {{ ucfirst($pos->level) }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        <span class="text-lg font-bold {{ $pos->active_assignments_count > 0 ? 'text-gray-700' : 'text-gray-300' }}">
                            {{ $pos->active_assignments_count }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            {{ $pos->status === 'active' ? 'bg-green-100 text-green-700' :
                               ($pos->status === 'inactive' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500') }}">
                            {{ $pos->status_label }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('hr.module-positions.show', $pos) }}"
                               class="text-xs text-[#1B1444] hover:underline">View</a>
                            <a href="{{ route('hr.module-positions.edit', $pos) }}"
                               class="text-xs text-gray-500 hover:text-gray-700">Edit</a>
                            @if(!$pos->isArchived() && Auth::guard('hr')->user()->isManager())
                            <form method="POST" action="{{ route('hr.module-positions.status', $pos) }}">
                                @csrf
                                @if($pos->isActive())
                                    <input type="hidden" name="status" value="inactive">
                                    <button class="text-xs text-yellow-500 hover:text-yellow-700">Deactivate</button>
                                @else
                                    <input type="hidden" name="status" value="active">
                                    <button class="text-xs text-green-500 hover:text-green-700">Activate</button>
                                @endif
                            </form>
                            <form method="POST" action="{{ route('hr.module-positions.status', $pos) }}"
                                  onsubmit="return confirm('Archive {{ $pos->name }}?')">
                                @csrf
                                <input type="hidden" name="status" value="archived">
                                <button class="text-xs text-red-400 hover:text-red-600">Archive</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

</div>
@endsection
