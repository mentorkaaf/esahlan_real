@extends('hr.layouts.app')
@section('title', 'Module Departments')
@section('heading', 'Module Departments')

@section('content')
<div class="space-y-5">

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('hr.module-departments.index') }}"
          class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4 flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-40">
            <label class="block text-xs font-medium text-gray-500 mb-1">Module</label>
            <select name="module_id"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                <option value="">All Modules</option>
                @foreach($modules as $mod)
                <option value="{{ $mod->id }}" {{ request('module_id') == $mod->id ? 'selected' : '' }}>
                    {{ $mod->name }}
                </option>
                @endforeach
            </select>
        </div>
        <div class="w-40">
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
            <a href="{{ route('hr.module-departments.create', $selectedModule ? ['module_id' => $selectedModule->id] : []) }}"
               class="bg-[#F7941D] hover:bg-[#E07800] text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                + New Department
            </a>
        </div>
    </form>

    {{-- Results header --}}
    @if($selectedModule)
    <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
             style="background-color: {{ $selectedModule->color ?? '#1B1444' }}20;">
            <i class="{{ $selectedModule->icon ?? 'fas fa-cube' }} text-sm"
               style="color: {{ $selectedModule->color ?? '#1B1444' }};"></i>
        </div>
        <h2 class="text-sm font-semibold text-gray-700">{{ $selectedModule->name }} departments</h2>
        <a href="{{ route('hr.module-departments.index') }}" class="text-xs text-gray-400 hover:text-gray-600">× Clear filter</a>
    </div>
    @endif

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        @if($departments->isEmpty())
        <div class="px-6 py-16 text-center">
            <div class="text-gray-300 text-4xl mb-3"><i class="fas fa-sitemap"></i></div>
            <p class="text-gray-400 text-sm">No departments found. Run the seeder or create a new one.</p>
            <a href="{{ route('hr.module-departments.create') }}"
               class="mt-4 inline-block text-sm text-[#F7941D] hover:underline">Create the first department →</a>
        </div>
        @else
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-[#1B1444] text-white">
                    <th class="px-5 py-3 text-left font-medium text-xs uppercase tracking-wider">Department</th>
                    <th class="px-5 py-3 text-left font-medium text-xs uppercase tracking-wider">Module</th>
                    <th class="px-5 py-3 text-left font-medium text-xs uppercase tracking-wider">Manager</th>
                    <th class="px-5 py-3 text-center font-medium text-xs uppercase tracking-wider">Employees</th>
                    <th class="px-5 py-3 text-center font-medium text-xs uppercase tracking-wider">Status</th>
                    <th class="px-5 py-3 text-right font-medium text-xs uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($departments as $dept)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3.5">
                        <a href="{{ route('hr.module-departments.show', $dept) }}"
                           class="font-semibold text-gray-900 hover:text-[#1B1444]">{{ $dept->name }}</a>
                        @if($dept->description)
                        <div class="text-xs text-gray-400 mt-0.5 max-w-xs truncate">{{ $dept->description }}</div>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded flex items-center justify-center"
                                 style="background-color: {{ $dept->module?->color ?? '#1B1444' }}20;">
                                <i class="{{ $dept->module?->icon ?? 'fas fa-cube' }} text-xs"
                                   style="color: {{ $dept->module?->color ?? '#1B1444' }};"></i>
                            </div>
                            <span class="text-gray-600">{{ $dept->module?->name }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-gray-600">
                        {{ $dept->manager?->full_name ?? '—' }}
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        <span class="text-lg font-bold text-gray-700">{{ $dept->active_assignments_count }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            {{ $dept->status === 'active'   ? 'bg-green-100 text-green-700'  :
                               ($dept->status === 'inactive' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500') }}">
                            {{ $dept->status_label }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('hr.module-departments.show', $dept) }}"
                               class="text-xs text-[#1B1444] hover:underline">View</a>
                            <a href="{{ route('hr.module-departments.edit', $dept) }}"
                               class="text-xs text-gray-500 hover:text-gray-700">Edit</a>
                            @if(!$dept->isArchived() && Auth::guard('hr')->user()->isManager())
                            <form method="POST" action="{{ route('hr.module-departments.status', $dept) }}">
                                @csrf
                                @if($dept->isActive())
                                <input type="hidden" name="status" value="inactive">
                                <button class="text-xs text-yellow-500 hover:text-yellow-700">Deactivate</button>
                                @else
                                <input type="hidden" name="status" value="active">
                                <button class="text-xs text-green-500 hover:text-green-700">Activate</button>
                                @endif
                            </form>
                            <form method="POST" action="{{ route('hr.module-departments.status', $dept) }}"
                                  onsubmit="return confirm('Archive {{ $dept->name }}? This cannot be undone.')">
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
        @endif
    </div>

</div>
@endsection
