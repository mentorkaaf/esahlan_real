@extends('hr.layouts.app')
@section('title', $moduleDepartment->name)
@section('heading', $moduleDepartment->name)

@section('content')
<div class="space-y-6">

    {{-- Header card --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <div class="flex items-start gap-5">
            {{-- Module icon --}}
            <div class="w-14 h-14 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background-color: {{ $moduleDepartment->module?->color ?? '#1B1444' }}20;">
                <i class="{{ $moduleDepartment->module?->icon ?? 'fas fa-sitemap' }} text-2xl"
                   style="color: {{ $moduleDepartment->module?->color ?? '#1B1444' }};"></i>
            </div>
            <div class="flex-1">
                <div class="flex items-center flex-wrap gap-3 mb-1">
                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">
                        {{ $moduleDepartment->module?->name }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        {{ $moduleDepartment->status === 'active'   ? 'bg-green-100 text-green-700'  :
                           ($moduleDepartment->status === 'inactive' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500') }}">
                        {{ $moduleDepartment->status_label }}
                    </span>
                </div>
                <h2 class="text-xl font-bold text-gray-900">{{ $moduleDepartment->name }}</h2>
                @if($moduleDepartment->description)
                <p class="text-sm text-gray-500 mt-1">{{ $moduleDepartment->description }}</p>
                @endif
            </div>
            <div class="flex gap-2 flex-shrink-0">
                <a href="{{ route('hr.module-departments.edit', $moduleDepartment) }}"
                   class="bg-[#1B1444] text-white text-xs px-4 py-2 rounded-lg hover:bg-[#2D2467] transition-colors">Edit</a>
                <a href="{{ route('hr.module-departments.index') }}"
                   class="text-xs text-gray-400 hover:text-gray-600 px-3 py-2">← All Departments</a>
            </div>
        </div>

        {{-- Stats row --}}
        <div class="grid grid-cols-3 gap-4 mt-5 pt-5 border-t border-gray-100">
            <div>
                <div class="text-2xl font-bold text-gray-800">{{ $assignments->where('status','active')->count() }}</div>
                <div class="text-xs text-gray-400 mt-0.5">Active employees</div>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-800">{{ $positions->count() }}</div>
                <div class="text-xs text-gray-400 mt-0.5">Positions represented</div>
            </div>
            <div>
                <div class="text-sm font-semibold text-gray-700">{{ $moduleDepartment->manager?->full_name ?? 'Unassigned' }}</div>
                <div class="text-xs text-gray-400 mt-0.5">Department manager</div>
            </div>
        </div>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">
        @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Employees in this dept --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800 text-sm uppercase tracking-wider">Employees</h3>
                    <span class="text-xs text-gray-400">{{ $assignments->where('status','active')->count() }} active</span>
                </div>

                @forelse($assignments as $a)
                <div class="px-6 py-3.5 border-b border-gray-50 last:border-0 flex items-center gap-4">
                    <div class="w-9 h-9 rounded-xl bg-[#1B1444] flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                        {{ $a->employee?->initials }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-sm text-gray-800">{{ $a->employee?->full_name }}</div>
                        <div class="text-xs text-gray-400">
                            {{ $a->employee?->position?->title ?? '—' }}
                            · {{ ucfirst($a->role_in_module ?? 'staff') }}
                        </div>
                    </div>
                    <span class="text-xs
                        {{ $a->status === 'active' ? 'text-green-600' :
                           ($a->status === 'suspended' ? 'text-yellow-600' : 'text-gray-400') }}
                        font-medium">
                        {{ ucfirst($a->status) }}
                    </span>
                    @if(Auth::guard('hr')->user()->isManager() || Auth::guard('hr')->user()->isOfficer())
                    <form method="POST"
                          action="{{ route('hr.module-departments.remove-employee', $a) }}"
                          onsubmit="return confirm('Remove from this department?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-300 hover:text-red-500">Remove</button>
                    </form>
                    @endif
                </div>
                @empty
                <div class="px-6 py-10 text-center text-gray-400 text-sm">
                    No employees in this department yet.
                </div>
                @endforelse
            </div>

            {{-- Positions represented --}}
            @if($positions->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm uppercase tracking-wider mb-3">Positions</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($positions as $pos)
                    <span class="bg-gray-100 text-gray-600 text-xs px-3 py-1 rounded-full">{{ $pos->title }}</span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Side panel --}}
        <div class="space-y-4">

            {{-- Assign manager --}}
            @if(Auth::guard('hr')->user()->isManager())
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm uppercase tracking-wider mb-3">Assign Manager</h3>
                <form method="POST" action="{{ route('hr.module-departments.assign-manager', $moduleDepartment) }}" class="space-y-3">
                    @csrf
                    <select name="manager_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                        <option value="">— No manager —</option>
                        @foreach($activeEmployees as $emp)
                        <option value="{{ $emp->id }}" {{ $moduleDepartment->manager_id === $emp->id ? 'selected' : '' }}>
                            {{ $emp->full_name }}
                        </option>
                        @endforeach
                    </select>
                    <button type="submit"
                            class="w-full bg-[#1B1444] hover:bg-[#2D2467] text-white text-sm py-2 rounded-lg font-medium transition-colors">
                        Update Manager
                    </button>
                </form>
            </div>
            @endif

            {{-- Add employee to dept --}}
            @if($availableEmployees->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm uppercase tracking-wider mb-3">Add Employee</h3>
                <form method="POST" action="{{ route('hr.module-departments.add-employee', $moduleDepartment) }}" class="space-y-3">
                    @csrf
                    <select name="assignment_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                        <option value="">— Select employee —</option>
                        @foreach($availableEmployees as $emp)
                        <option value="{{ WorkforceAssignment::where('employee_id',$emp->id)->where('module_id',$moduleDepartment->module_id)->where('status','active')->value('id') }}">
                            {{ $emp->full_name }}
                        </option>
                        @endforeach
                    </select>
                    <button type="submit"
                            class="w-full bg-[#F7941D] hover:bg-[#E07800] text-white text-sm py-2 rounded-lg font-medium transition-colors">
                        Add to Department
                    </button>
                </form>
            </div>
            @endif

            {{-- Status controls --}}
            @if(Auth::guard('hr')->user()->isManager() && !$moduleDepartment->isArchived())
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 text-sm uppercase tracking-wider mb-3">Status</h3>
                <div class="space-y-2">
                    @if($moduleDepartment->isActive())
                    <form method="POST" action="{{ route('hr.module-departments.status', $moduleDepartment) }}">
                        @csrf <input type="hidden" name="status" value="inactive">
                        <button class="w-full text-sm border border-yellow-300 text-yellow-700 py-2 rounded-lg hover:bg-yellow-50 transition-colors">
                            Deactivate Department
                        </button>
                    </form>
                    @else
                    <form method="POST" action="{{ route('hr.module-departments.status', $moduleDepartment) }}">
                        @csrf <input type="hidden" name="status" value="active">
                        <button class="w-full text-sm border border-green-300 text-green-700 py-2 rounded-lg hover:bg-green-50 transition-colors">
                            Activate Department
                        </button>
                    </form>
                    @endif
                    <form method="POST" action="{{ route('hr.module-departments.status', $moduleDepartment) }}"
                          onsubmit="return confirm('Archive this department? This cannot be undone.')">
                        @csrf <input type="hidden" name="status" value="archived">
                        <button class="w-full text-sm border border-red-200 text-red-500 py-2 rounded-lg hover:bg-red-50 transition-colors">
                            Archive Department
                        </button>
                    </form>
                </div>
            </div>
            @endif

        </div>
    </div>

</div>
@endsection
