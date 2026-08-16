@extends('hr.layouts.app')
@section('title', $module->name . ' — Workforce')
@section('heading', $module->name . ' · Workforce')

@section('content')
<div class="space-y-6">

    {{-- Module header --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-center gap-5">
        <div class="w-14 h-14 rounded-xl flex items-center justify-center flex-shrink-0"
             style="background-color: {{ $module->color ?? '#1B1444' }}20;">
            <i class="{{ $module->icon ?? 'fas fa-cube' }} text-2xl"
               style="color: {{ $module->color ?? '#1B1444' }};"></i>
        </div>
        <div class="flex-1">
            <h2 class="text-lg font-bold text-gray-900">{{ $module->name }}</h2>
            <p class="text-sm text-gray-400">
                {{ $assignments->where('status','active')->count() }} active ·
                {{ $assignments->where('status','suspended')->count() }} suspended
            </p>
        </div>
        {{-- Wizard button --}}
        <a href="{{ route('hr.workforce.wizard', ['module_id' => $module->id]) }}"
           class="bg-[#F7941D] hover:bg-[#E07800] text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
            <i class="fas fa-magic text-xs"></i> Assign Employee
        </a>
        <a href="{{ route('hr.workforce.index') }}" class="text-sm text-gray-400 hover:text-gray-600">← All Modules</a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">
        @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
    </div>
    @endif

    {{-- Assignments list --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-sm uppercase tracking-wider">Assigned Employees</h3>
            <span class="text-xs text-gray-400">{{ $assignments->count() }} total</span>
        </div>

        @forelse($assignments as $a)
        <div class="px-6 py-4 border-b border-gray-50 last:border-0">
            <div class="flex items-center gap-4">
                {{-- Avatar --}}
                <div class="w-10 h-10 rounded-xl bg-[#1B1444] flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                    {{ $a->employee?->initials ?? '?' }}
                </div>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-gray-800 text-sm">{{ $a->employee?->full_name }}</span>
                        {{-- Assignment type badge --}}
                        @php
                        $typeColors = [
                            'primary'       => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                            'secondary'     => 'bg-blue-50 text-blue-700 border-blue-100',
                            'temporary'     => 'bg-orange-50 text-orange-700 border-orange-100',
                            'acting'        => 'bg-purple-50 text-purple-700 border-purple-100',
                            'project_based' => 'bg-teal-50 text-teal-700 border-teal-100',
                        ];
                        $lvlColors = [
                            'read_only' => 'bg-gray-50 text-gray-500 border-gray-200',
                            'standard'  => 'bg-blue-50 text-blue-600 border-blue-100',
                            'elevated'  => 'bg-orange-50 text-orange-600 border-orange-100',
                            'admin'     => 'bg-red-50 text-red-600 border-red-100',
                        ];
                        @endphp
                        <span class="text-[10px] border px-1.5 py-0.5 rounded {{ $typeColors[$a->assignment_type ?? 'primary'] ?? 'bg-gray-50 text-gray-500 border-gray-200' }}">
                            {{ $a->assignment_type_label }}
                        </span>
                        <span class="text-[10px] border px-1.5 py-0.5 rounded {{ $lvlColors[$a->access_level ?? 'standard'] ?? 'bg-gray-50 text-gray-500 border-gray-200' }}">
                            {{ $a->access_level_label }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5 flex flex-wrap gap-x-2">
                        @if($a->moduleDepartment) <span>{{ $a->moduleDepartment->name }}</span> @endif
                        @if($a->modulePosition) <span>· {{ $a->modulePosition->name }}</span> @endif
                        @if($a->moduleRole) <span>· <i class="fas fa-shield-alt text-indigo-300"></i> {{ $a->moduleRole->name }}</span> @endif
                        @if($a->reportingManager) <span>· Reports to {{ $a->reportingManager->full_name }}</span> @endif
                    </div>
                    {{-- Dates --}}
                    @if($a->start_date || $a->planned_end_date)
                    <div class="text-xs text-gray-300 mt-0.5">
                        @if($a->start_date) From {{ $a->start_date->format('d M Y') }} @endif
                        @if($a->planned_end_date)
                            → {{ $a->planned_end_date->format('d M Y') }}
                            @if($a->isExpired()) <span class="text-red-400 font-medium">· Expired</span> @endif
                        @else
                            → Open-ended
                        @endif
                    </div>
                    @endif
                </div>

                {{-- Role label --}}
                <div class="text-xs text-gray-500 font-medium w-20 text-center hidden sm:block">
                    {{ $a->role_in_module ? ucfirst($a->role_in_module) : '—' }}
                </div>

                {{-- Status pill --}}
                <div class="w-20 text-center flex-shrink-0">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        {{ $a->status === 'active'    ? 'bg-green-100 text-green-700' :
                           ($a->status === 'suspended' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500') }}">
                        {{ ucfirst($a->status) }}
                    </span>
                </div>

                {{-- Actions --}}
                @if(Auth::guard('hr')->user()->isManager())
                <div class="flex items-center gap-2 flex-shrink-0">
                    @if($a->isActive())
                    <form method="POST" action="{{ route('hr.workforce.suspend', $a) }}"
                          onsubmit="return confirm('Suspend this assignment?')">
                        @csrf
                        <input type="hidden" name="reason" value="Manual suspension">
                        <button class="text-xs text-yellow-600 hover:text-yellow-800 font-medium">Suspend</button>
                    </form>
                    @elseif($a->status === 'suspended')
                    <form method="POST" action="{{ route('hr.workforce.reactivate', $a) }}">
                        @csrf
                        <button class="text-xs text-green-600 hover:text-green-800 font-medium">Reactivate</button>
                    </form>
                    @endif

                    @if($a->status !== 'ended')
                    <form method="POST" action="{{ route('hr.workforce.destroy', $a) }}"
                          onsubmit="return confirm('End this assignment?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-400 hover:text-red-600 font-medium">End</button>
                    </form>
                    @endif
                </div>
                @endif
            </div>
        </div>
        @empty
        <div class="px-6 py-12 text-center">
            <div class="text-gray-300 text-4xl mb-3"><i class="fas fa-users-slash"></i></div>
            <p class="text-gray-400 text-sm">No employees assigned to {{ $module->name }} yet.</p>
            <a href="{{ route('hr.workforce.wizard', ['module_id' => $module->id]) }}"
               class="mt-3 inline-block text-sm text-[#F7941D] hover:underline">
                <i class="fas fa-magic mr-1"></i> Assign the first employee →
            </a>
        </div>
        @endforelse
    </div>

    {{-- Available to assign (quick picker) --}}
    @if($availableEmployees->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-sm uppercase tracking-wider">Available to Assign</h3>
            <span class="text-xs text-gray-400">{{ $availableEmployees->count() }} employees</span>
        </div>
        <div class="divide-y divide-gray-50 max-h-72 overflow-y-auto">
            @foreach($availableEmployees as $emp)
            <div class="px-6 py-3 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-xs font-bold">
                        {{ $emp->initials ?? strtoupper(substr($emp->first_name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="text-sm text-gray-800">{{ $emp->full_name }}</div>
                        <div class="text-xs text-gray-400">{{ $emp->position?->title }}</div>
                    </div>
                </div>
                <a href="{{ route('hr.workforce.wizard', ['employee_id' => $emp->id, 'module_id' => $module->id]) }}"
                   class="text-xs text-[#1B1444] hover:text-[#F7941D] font-medium transition-colors flex items-center gap-1">
                    <i class="fas fa-magic text-xs"></i> Assign
                </a>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
