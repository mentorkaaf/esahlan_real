@extends('hr.layouts.app')
@section('title', 'New Assignment Wizard')
@section('heading', 'New Module Assignment')

@section('content')

{{-- ── Wizard shell ─────────────────────────────────────────────────────────── --}}
<form id="wiz-form" method="POST" action="{{ route('hr.workforce.store') }}" novalidate>
@csrf

{{-- ── Progress bar ─────────────────────────────────────────────────────────── --}}
<div class="mb-8">
    {{-- Step labels --}}
    <div class="flex items-center justify-between text-xs text-gray-400 mb-2 px-1" id="step-labels">
        @php
        $stepLabels = [
            1 => 'Employee', 2 => 'Module', 3 => 'Department',
            4 => 'Position', 5 => 'Role', 6 => 'Type & Level',
            7 => 'Dates', 8 => 'Manager', 9 => 'Notes', 10 => 'Confirm',
        ];
        @endphp
        @foreach($stepLabels as $n => $label)
        <div class="flex flex-col items-center gap-1 cursor-pointer step-tab" data-step="{{ $n }}">
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold step-circle
                        transition-all duration-200 {{ $n === 1 ? 'bg-[#1B1444] text-white' : 'bg-gray-100 text-gray-400' }}"
                 id="circle-{{ $n }}">{{ $n }}</div>
            <span class="hidden sm:block step-label-text {{ $n === 1 ? 'text-gray-700 font-medium' : 'text-gray-400' }}"
                  id="label-{{ $n }}">{{ $label }}</span>
        </div>
        @endforeach
    </div>
    {{-- Progress line --}}
    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden mt-1">
        <div id="progress-bar" class="h-full bg-[#F7941D] rounded-full transition-all duration-300" style="width:10%"></div>
    </div>
</div>

@if($errors->any())
<div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg mb-5">
    @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════════════════
     STEP 1 — EMPLOYEE
     ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="wiz-step" data-step="1">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-5">
        <h2 class="text-base font-semibold text-gray-800 mb-1">Select Employee</h2>
        <p class="text-sm text-gray-400 mb-5">Choose an existing active employee. No new employee records will be created.</p>

        {{-- Search --}}
        <input type="text" id="emp-search" placeholder="Search by name, email or department…"
               class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm mb-4
                      focus:outline-none focus:ring-2 focus:ring-[#1B1444]">

        <input type="hidden" name="employee_id" id="emp-id-input" value="{{ old('employee_id', $selectedEmployee?->id) }}">

        <div id="emp-list" class="space-y-2 max-h-[380px] overflow-y-auto pr-1">
            @foreach($employees as $emp)
            <div class="emp-row flex items-center gap-3 p-3 rounded-lg border border-gray-100
                        cursor-pointer hover:border-[#1B1444] hover:bg-[#1B1444]05 transition-all
                        {{ old('employee_id', $selectedEmployee?->id) == $emp->id ? 'selected-emp border-[#1B1444] bg-indigo-50' : '' }}"
                 data-id="{{ $emp->id }}"
                 data-name="{{ $emp->full_name }}"
                 data-search="{{ strtolower($emp->full_name . ' ' . $emp->email . ' ' . $emp->department?->name) }}">
                <div class="w-9 h-9 rounded-lg bg-[#1B1444] text-white text-sm font-bold flex items-center justify-center flex-shrink-0">
                    {{ $emp->initials ?? strtoupper(substr($emp->first_name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-gray-800 text-sm">{{ $emp->full_name }}</div>
                    <div class="text-xs text-gray-400 truncate">
                        {{ $emp->position?->title ?? '—' }}
                        @if($emp->department) · {{ $emp->department->name }} @endif
                    </div>
                </div>
                @php
                    $hasPrimary = $emp->workforceAssignments()
                        ->where('status','active')
                        ->where('assignment_type','primary')
                        ->exists();
                    $activeCount = $emp->workforceAssignments()->where('status','active')->count();
                @endphp
                <div class="flex flex-col items-end gap-1 flex-shrink-0">
                    @if($hasPrimary)
                    <span class="text-[10px] bg-indigo-50 text-indigo-600 border border-indigo-100 px-1.5 py-0.5 rounded">Has Primary</span>
                    @endif
                    @if($activeCount > 0)
                    <span class="text-[10px] text-gray-400">{{ $activeCount }} active</span>
                    @endif
                </div>
                <div class="check-icon hidden text-[#1B1444]">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Selected employee summary --}}
        <div id="emp-selected-bar" class="hidden mt-4 bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3 flex items-center gap-3">
            <i class="fas fa-user-check text-indigo-500"></i>
            <span class="text-sm font-medium text-indigo-700" id="emp-selected-name"></span>
            <button type="button" onclick="clearEmployee()"
                    class="ml-auto text-xs text-indigo-400 hover:text-indigo-600">Change</button>
        </div>
    </div>
    <div class="flex justify-end">
        <button type="button" onclick="goStep(2)" id="btn-step1-next"
                class="wiz-next bg-[#1B1444] text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-opacity-90 disabled:opacity-40 disabled:cursor-not-allowed"
                {{ old('employee_id', $selectedEmployee?->id) ? '' : 'disabled' }}>
            Next: Select Module →
        </button>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     STEP 2 — MODULE
     ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="wiz-step hidden" data-step="2">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-5">
        <h2 class="text-base font-semibold text-gray-800 mb-1">Select Business Module</h2>
        <p class="text-sm text-gray-400 mb-5">Which module is this employee being assigned to?</p>

        <input type="hidden" name="module_id" id="mod-id-input" value="{{ old('module_id', $selectedModule?->id) }}">

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            @foreach($modules as $mod)
            <div class="mod-card flex flex-col items-center gap-2 p-4 rounded-xl border-2 cursor-pointer
                        transition-all hover:border-[#1B1444]
                        {{ old('module_id', $selectedModule?->id) == $mod->id ? 'border-[#1B1444] bg-indigo-50' : 'border-gray-100' }}"
                 data-id="{{ $mod->id }}" data-name="{{ $mod->name }}">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                     style="background-color: {{ $mod->color ?? '#1B1444' }}20;">
                    <i class="{{ $mod->icon ?? 'fas fa-cube' }} text-lg"
                       style="color: {{ $mod->color ?? '#1B1444' }};"></i>
                </div>
                <span class="text-xs font-semibold text-gray-700 text-center">{{ $mod->name }}</span>
            </div>
            @endforeach
        </div>
    </div>
    <div class="flex gap-3 justify-between">
        <button type="button" onclick="goStep(1)" class="text-gray-500 hover:text-gray-700 text-sm px-4 py-2">← Back</button>
        <button type="button" onclick="goStep(3)" id="btn-step2-next"
                class="wiz-next bg-[#1B1444] text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-opacity-90 disabled:opacity-40 disabled:cursor-not-allowed"
                {{ old('module_id', $selectedModule?->id) ? '' : 'disabled' }}>
            Next: Department →
        </button>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     STEP 3 — DEPARTMENT
     ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="wiz-step hidden" data-step="3">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-5">
        <h2 class="text-base font-semibold text-gray-800 mb-1">Department <span class="text-gray-400 font-normal text-sm">(optional)</span></h2>
        <p class="text-sm text-gray-400 mb-5">Which department within this module will the employee join?</p>

        <input type="hidden" name="module_department_id" id="dept-id-input" value="{{ old('module_department_id') }}">

        <div id="dept-loading" class="hidden text-sm text-gray-400 text-center py-6">
            <i class="fas fa-spinner fa-spin mr-2"></i>Loading departments…
        </div>
        <div id="dept-list" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {{-- Populated via JS --}}
        </div>
        <div class="mt-4 pt-4 border-t border-gray-50">
            <button type="button"
                    onclick="skipDept()"
                    class="text-sm text-gray-400 hover:text-gray-600 flex items-center gap-2">
                <i class="fas fa-forward text-xs"></i> Skip — no specific department
            </button>
        </div>
    </div>
    <div class="flex gap-3 justify-between">
        <button type="button" onclick="goStep(2)" class="text-gray-500 hover:text-gray-700 text-sm px-4 py-2">← Back</button>
        <button type="button" onclick="goStep(4)" class="bg-[#1B1444] text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-opacity-90">
            Next: Position →
        </button>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     STEP 4 — POSITION
     ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="wiz-step hidden" data-step="4">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-5">
        <h2 class="text-base font-semibold text-gray-800 mb-1">Position <span class="text-gray-400 font-normal text-sm">(optional)</span></h2>
        <p class="text-sm text-gray-400 mb-5">Which position within this module will the employee hold?</p>

        <input type="hidden" name="module_position_id" id="pos-id-input" value="{{ old('module_position_id') }}">

        <div id="pos-loading" class="hidden text-sm text-gray-400 text-center py-6">
            <i class="fas fa-spinner fa-spin mr-2"></i>Loading positions…
        </div>
        <div id="pos-list" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {{-- Populated via JS --}}
        </div>
        <div class="mt-4 pt-4 border-t border-gray-50">
            <button type="button" onclick="skipPos()"
                    class="text-sm text-gray-400 hover:text-gray-600 flex items-center gap-2">
                <i class="fas fa-forward text-xs"></i> Skip — no specific position
            </button>
        </div>
    </div>
    <div class="flex gap-3 justify-between">
        <button type="button" onclick="goStep(3)" class="text-gray-500 hover:text-gray-700 text-sm px-4 py-2">← Back</button>
        <button type="button" onclick="goStep(5)" class="bg-[#1B1444] text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-opacity-90">
            Next: Role & Permissions →
        </button>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     STEP 5 — ROLE & PERMISSIONS
     ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="wiz-step hidden" data-step="5">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-5">
        <h2 class="text-base font-semibold text-gray-800 mb-1">Module Role & Permissions</h2>
        <p class="text-sm text-gray-400 mb-5">Select the module role that defines what this employee can access.</p>

        <input type="hidden" name="module_role_id" id="role-id-input" value="{{ old('module_role_id') }}">

        <div id="role-loading" class="hidden text-sm text-gray-400 text-center py-6">
            <i class="fas fa-spinner fa-spin mr-2"></i>Loading roles…
        </div>
        <div id="role-list" class="space-y-2 mb-5">
            {{-- Populated via JS --}}
        </div>

        {{-- Permission preview --}}
        <div id="perm-preview" class="hidden bg-gray-50 border border-gray-100 rounded-xl p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Permissions Preview</p>
            <div id="perm-preview-body" class="flex flex-wrap gap-1.5">
                {{-- Populated via JS --}}
            </div>
        </div>

        <div class="mt-4 pt-4 border-t border-gray-50">
            {{-- Role-in-module label (operational label, separate from system role) --}}
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Role Label <span class="text-xs text-gray-400 font-normal">(how this role is listed internally)</span>
            </label>
            <select name="role_in_module"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                <option value="staff"      {{ old('role_in_module') === 'staff'      ? 'selected' : '' }}>Staff</option>
                <option value="supervisor" {{ old('role_in_module') === 'supervisor' ? 'selected' : '' }}>Supervisor</option>
                <option value="agent"      {{ old('role_in_module') === 'agent'      ? 'selected' : '' }}>Agent</option>
                <option value="operator"   {{ old('role_in_module') === 'operator'   ? 'selected' : '' }}>Operator</option>
                <option value="driver"     {{ old('role_in_module') === 'driver'     ? 'selected' : '' }}>Driver</option>
                <option value="dispatcher" {{ old('role_in_module') === 'dispatcher' ? 'selected' : '' }}>Dispatcher</option>
                <option value="support"    {{ old('role_in_module') === 'support'    ? 'selected' : '' }}>Support</option>
                <option value="manager"    {{ old('role_in_module') === 'manager'    ? 'selected' : '' }}>Manager</option>
                <option value="coordinator"{{ old('role_in_module') === 'coordinator'? 'selected' : '' }}>Coordinator</option>
            </select>
            <p class="text-xs text-gray-400 mt-1">
                Selecting <strong>Agent</strong> for <strong>eRent</strong> automatically upgrades the user's system role.
            </p>
        </div>
    </div>
    <div class="flex gap-3 justify-between">
        <button type="button" onclick="goStep(4)" class="text-gray-500 hover:text-gray-700 text-sm px-4 py-2">← Back</button>
        <button type="button" onclick="goStep(6)" class="bg-[#1B1444] text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-opacity-90">
            Next: Type & Access →
        </button>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     STEP 6 — ASSIGNMENT TYPE & ACCESS LEVEL
     ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="wiz-step hidden" data-step="6">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-5 space-y-6">
        <div>
            <h2 class="text-base font-semibold text-gray-800 mb-1">Assignment Type</h2>
            <p class="text-sm text-gray-400 mb-4">What kind of assignment is this?</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3" id="type-grid">
                @php
                $typeConfig = [
                    'primary'       => ['icon' => 'fas fa-star',         'color' => 'indigo',  'desc' => 'Main module assignment. Only one primary per employee.'],
                    'secondary'     => ['icon' => 'fas fa-layer-group',   'color' => 'blue',    'desc' => 'Supporting role in addition to primary assignment.'],
                    'temporary'     => ['icon' => 'fas fa-hourglass-half','color' => 'orange',  'desc' => 'Time-limited assignment with a set end date.'],
                    'acting'        => ['icon' => 'fas fa-user-shield',   'color' => 'purple',  'desc' => 'Temporarily filling another role, e.g. covering for a manager.'],
                    'project_based' => ['icon' => 'fas fa-project-diagram','color' => 'teal',   'desc' => 'Assigned for a specific project or task. Ends with the project.'],
                ];
                @endphp
                @foreach($assignmentTypes as $typeKey => $typeLabel)
                @php $cfg = $typeConfig[$typeKey] @endphp
                <div class="type-card flex flex-col gap-2 p-4 rounded-xl border-2 cursor-pointer
                            transition-all hover:border-[#1B1444]
                            {{ old('assignment_type', 'primary') === $typeKey ? 'border-[#1B1444] bg-indigo-50' : 'border-gray-100' }}"
                     data-type="{{ $typeKey }}" data-label="{{ $typeLabel }}">
                    <div class="flex items-center gap-2">
                        <i class="{{ $cfg['icon'] }} text-[#1B1444]"></i>
                        <span class="font-semibold text-gray-800 text-sm">{{ $typeLabel }}</span>
                    </div>
                    <p class="text-xs text-gray-400">{{ $cfg['desc'] }}</p>
                </div>
                @endforeach
            </div>
            <input type="hidden" name="assignment_type" id="assignment-type-input" value="{{ old('assignment_type', 'primary') }}">
        </div>

        <div class="border-t border-gray-50 pt-6">
            <h2 class="text-base font-semibold text-gray-800 mb-1">Access Level</h2>
            <p class="text-sm text-gray-400 mb-4">What level of system access does this employee need?</p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" id="level-grid">
                @php
                $levelConfig = [
                    'read_only' => ['icon' => 'fas fa-eye',        'color' => 'gray',   'desc' => 'View data only. Cannot create or modify.'],
                    'standard'  => ['icon' => 'fas fa-user',        'color' => 'blue',   'desc' => 'Standard operational access for day-to-day work.'],
                    'elevated'  => ['icon' => 'fas fa-user-cog',    'color' => 'orange', 'desc' => 'Advanced access including settings and reports.'],
                    'admin'     => ['icon' => 'fas fa-user-shield',  'color' => 'red',    'desc' => 'Full module administration access.'],
                ];
                @endphp
                @foreach($accessLevels as $lvlKey => $lvlLabel)
                @php $cfg = $levelConfig[$lvlKey] @endphp
                <div class="level-card flex flex-col gap-2 p-4 rounded-xl border-2 cursor-pointer
                            transition-all hover:border-[#1B1444]
                            {{ old('access_level', 'standard') === $lvlKey ? 'border-[#1B1444] bg-indigo-50' : 'border-gray-100' }}"
                     data-level="{{ $lvlKey }}" data-label="{{ $lvlLabel }}">
                    <i class="{{ $cfg['icon'] }} text-[#1B1444] text-lg"></i>
                    <span class="font-semibold text-gray-800 text-sm">{{ $lvlLabel }}</span>
                    <p class="text-xs text-gray-400">{{ $cfg['desc'] }}</p>
                </div>
                @endforeach
            </div>
            <input type="hidden" name="access_level" id="access-level-input" value="{{ old('access_level', 'standard') }}">
        </div>
    </div>
    <div class="flex gap-3 justify-between">
        <button type="button" onclick="goStep(5)" class="text-gray-500 hover:text-gray-700 text-sm px-4 py-2">← Back</button>
        <button type="button" onclick="goStep(7)" class="bg-[#1B1444] text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-opacity-90">
            Next: Dates →
        </button>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     STEP 7 — DATES
     ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="wiz-step hidden" data-step="7">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-5 space-y-5">
        <div>
            <h2 class="text-base font-semibold text-gray-800 mb-1">Assignment Dates</h2>
            <p class="text-sm text-gray-400 mb-5">When does this assignment start and (optionally) end?</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Start Date <span class="text-red-400">*</span>
                </label>
                <input type="date" name="start_date" id="start-date-input"
                       value="{{ old('start_date', now()->format('Y-m-d')) }}"
                       required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                <p class="text-xs text-gray-400 mt-1">When this assignment becomes effective.</p>
            </div>

            <div id="end-date-wrap">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Planned End Date
                    <span class="text-xs text-gray-400 font-normal ml-1" id="end-date-hint">(recommended for temporary/project)</span>
                </label>
                <input type="date" name="planned_end_date" id="end-date-input"
                       value="{{ old('planned_end_date') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm
                              focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                <p class="text-xs text-gray-400 mt-1">Leave blank for open-ended assignments.</p>
            </div>
        </div>

        {{-- Quick date presets --}}
        <div class="border-t border-gray-50 pt-4">
            <p class="text-xs text-gray-400 mb-2">Quick end-date presets:</p>
            <div class="flex flex-wrap gap-2">
                @foreach([
                    ['label' => '1 Month',   'days' => 30],
                    ['label' => '3 Months',  'days' => 90],
                    ['label' => '6 Months',  'days' => 180],
                    ['label' => '1 Year',    'days' => 365],
                    ['label' => 'Open',      'days' => 0],
                ] as $preset)
                <button type="button"
                        onclick="setEndPreset({{ $preset['days'] }})"
                        class="text-xs border border-gray-200 text-gray-500 px-3 py-1.5 rounded-lg hover:border-[#1B1444] hover:text-[#1B1444] transition-colors">
                    {{ $preset['label'] }}
                </button>
                @endforeach
            </div>
        </div>
    </div>
    <div class="flex gap-3 justify-between">
        <button type="button" onclick="goStep(6)" class="text-gray-500 hover:text-gray-700 text-sm px-4 py-2">← Back</button>
        <button type="button" onclick="if(validateDates()) goStep(8)"
                class="bg-[#1B1444] text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-opacity-90">
            Next: Reporting Manager →
        </button>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     STEP 8 — REPORTING MANAGER
     ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="wiz-step hidden" data-step="8">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-5">
        <h2 class="text-base font-semibold text-gray-800 mb-1">Reporting Manager <span class="text-gray-400 font-normal text-sm">(optional)</span></h2>
        <p class="text-sm text-gray-400 mb-5">Who does this employee report to for this module assignment?</p>

        <input type="hidden" name="reporting_manager_id" id="mgr-id-input" value="{{ old('reporting_manager_id') }}">

        <input type="text" id="mgr-search" placeholder="Search managers by name…"
               class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm mb-4
                      focus:outline-none focus:ring-2 focus:ring-[#1B1444]">

        <div id="mgr-list" class="space-y-2 max-h-72 overflow-y-auto pr-1">
            @foreach($managers as $mgr)
            <div class="mgr-row flex items-center gap-3 p-3 rounded-lg border border-gray-100
                        cursor-pointer hover:border-[#1B1444] transition-all"
                 data-id="{{ $mgr->id }}"
                 data-name="{{ $mgr->full_name }}"
                 data-search="{{ strtolower($mgr->full_name . ' ' . $mgr->position?->title) }}"
                 id="mgr-{{ $mgr->id }}">
                <div class="w-8 h-8 rounded-lg bg-gray-200 text-gray-600 text-sm font-bold flex items-center justify-center flex-shrink-0">
                    {{ strtoupper(substr($mgr->first_name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-gray-800 text-sm">{{ $mgr->full_name }}</div>
                    <div class="text-xs text-gray-400 truncate">{{ $mgr->position?->title ?? '—' }}</div>
                </div>
                <div class="check-icon hidden text-[#1B1444]">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mt-4 pt-4 border-t border-gray-50">
            <button type="button" onclick="skipManager()"
                    class="text-sm text-gray-400 hover:text-gray-600 flex items-center gap-2">
                <i class="fas fa-forward text-xs"></i> Skip — no reporting manager
            </button>
        </div>
    </div>
    <div class="flex gap-3 justify-between">
        <button type="button" onclick="goStep(7)" class="text-gray-500 hover:text-gray-700 text-sm px-4 py-2">← Back</button>
        <button type="button" onclick="goStep(9)" class="bg-[#1B1444] text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-opacity-90">
            Next: Notes →
        </button>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     STEP 9 — NOTES
     ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="wiz-step hidden" data-step="9">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-5">
        <h2 class="text-base font-semibold text-gray-800 mb-1">Notes <span class="text-gray-400 font-normal text-sm">(optional)</span></h2>
        <p class="text-sm text-gray-400 mb-5">Any additional context for this assignment?</p>
        <textarea name="notes" rows="5"
                  placeholder="e.g. Covering for leave until Q3. Scope limited to order management."
                  maxlength="1000"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm
                         focus:outline-none focus:ring-2 focus:ring-[#1B1444]">{{ old('notes') }}</textarea>
        <p class="text-xs text-gray-400 mt-1.5">Max 1000 characters.</p>
    </div>
    <div class="flex gap-3 justify-between">
        <button type="button" onclick="goStep(8)" class="text-gray-500 hover:text-gray-700 text-sm px-4 py-2">← Back</button>
        <button type="button" onclick="buildConfirmation(); goStep(10)"
                class="bg-[#1B1444] text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-opacity-90">
            Review Assignment →
        </button>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     STEP 10 — CONFIRMATION
     ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="wiz-step hidden" data-step="10">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-5">
        <div class="bg-[#1B1444] px-6 py-5">
            <h2 class="text-base font-semibold text-white mb-0.5">Confirm Assignment</h2>
            <p class="text-sm text-white text-opacity-60">Please review all details before confirming.</p>
        </div>

        <div id="confirm-body" class="divide-y divide-gray-50">
            {{-- Built by JS --}}
        </div>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 mb-5 flex gap-3">
        <i class="fas fa-info-circle text-amber-500 mt-0.5"></i>
        <div class="text-sm text-amber-700">
            <p class="font-semibold mb-0.5">Before submitting</p>
            <p>Submitting will grant permissions to the employee's linked user account immediately. This action is logged in the audit trail.</p>
        </div>
    </div>

    <div class="flex gap-3 justify-between">
        <button type="button" onclick="goStep(9)" class="text-gray-500 hover:text-gray-700 text-sm px-4 py-2">← Edit</button>
        <button type="submit"
                class="bg-[#F7941D] hover:bg-[#E07800] text-white px-8 py-3 rounded-lg text-sm font-bold transition-colors flex items-center gap-2">
            <i class="fas fa-check-circle"></i>
            Confirm & Create Assignment
        </button>
    </div>
</div>

</form>

@push('scripts')
<script>
// ── State ─────────────────────────────────────────────────────────────────────
const state = {
    step: 1,
    employeeId:   '{{ old('employee_id', $selectedEmployee?->id) }}',
    employeeName: '{{ $selectedEmployee?->full_name ?? '' }}',
    moduleId:     '{{ old('module_id', $selectedModule?->id) }}',
    moduleName:   '{{ $selectedModule?->name ?? '' }}',
    deptId:       '{{ old('module_department_id') }}',
    deptName:     '',
    posId:        '{{ old('module_position_id') }}',
    posName:      '',
    roleId:       '{{ old('module_role_id') }}',
    roleName:     '',
    roleLabel:    '',
    assignType:   '{{ old('assignment_type', 'primary') }}',
    assignTypeLabel: '{{ $assignmentTypes[old('assignment_type', 'primary')] ?? 'Primary' }}',
    accessLevel:     '{{ old('access_level', 'standard') }}',
    accessLevelLabel:'{{ $accessLevels[old('access_level', 'standard')] ?? 'Standard' }}',
    startDate:    '{{ old('start_date', now()->format('Y-m-d')) }}',
    endDate:      '{{ old('planned_end_date') }}',
    managerId:    '{{ old('reporting_manager_id') }}',
    managerName:  '',
    notes:        '',
};

// ── Navigation ────────────────────────────────────────────────────────────────
function goStep(n) {
    // Validate current step
    if (!validateStep(state.step)) return;

    // Load data for next step if needed
    if (n === 3) loadDepts();
    if (n === 4) loadPositions();
    if (n === 5) loadRoles();

    // Hide all, show target
    document.querySelectorAll('.wiz-step').forEach(el => el.classList.add('hidden'));
    document.querySelector(`.wiz-step[data-step="${n}"]`).classList.remove('hidden');

    state.step = n;
    updateProgress(n);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateProgress(n) {
    const pct = Math.round((n / 10) * 100);
    document.getElementById('progress-bar').style.width = pct + '%';

    for (let i = 1; i <= 10; i++) {
        const circle = document.getElementById(`circle-${i}`);
        const label  = document.getElementById(`label-${i}`);
        if (i < n) {
            circle.className = 'w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold step-circle transition-all duration-200 bg-green-500 text-white';
            circle.innerHTML = '✓';
        } else if (i === n) {
            circle.className = 'w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold step-circle transition-all duration-200 bg-[#1B1444] text-white';
            circle.innerHTML = i;
            if (label) { label.classList.add('text-gray-700', 'font-medium'); label.classList.remove('text-gray-400'); }
        } else {
            circle.className = 'w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold step-circle transition-all duration-200 bg-gray-100 text-gray-400';
            circle.innerHTML = i;
            if (label) { label.classList.remove('text-gray-700', 'font-medium'); label.classList.add('text-gray-400'); }
        }
    }
}

function validateStep(n) {
    if (n === 1 && !state.employeeId) {
        alert('Please select an employee first.');
        return false;
    }
    if (n === 2 && !state.moduleId) {
        alert('Please select a module.');
        return false;
    }
    if (n === 7) return validateDates();
    return true;
}

function validateDates() {
    const start = document.getElementById('start-date-input').value;
    const end   = document.getElementById('end-date-input').value;
    if (!start) { alert('Please set a start date.'); return false; }
    if (end && end < start) { alert('Planned end date must be on or after the start date.'); return false; }
    state.startDate = start;
    state.endDate   = end;
    return true;
}

// ── Step 1: Employee ──────────────────────────────────────────────────────────
document.querySelectorAll('.emp-row').forEach(row => {
    row.addEventListener('click', () => selectEmployee(row.dataset.id, row.dataset.name));
});

document.getElementById('emp-search').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.emp-row').forEach(row => {
        row.style.display = row.dataset.search.includes(q) ? '' : 'none';
    });
});

function selectEmployee(id, name) {
    // Deselect all
    document.querySelectorAll('.emp-row').forEach(r => {
        r.classList.remove('selected-emp', 'border-[#1B1444]', 'bg-indigo-50');
        r.querySelector('.check-icon')?.classList.add('hidden');
    });
    // Select
    const row = document.querySelector(`.emp-row[data-id="${id}"]`);
    if (row) {
        row.classList.add('selected-emp', 'border-[#1B1444]', 'bg-indigo-50');
        row.querySelector('.check-icon')?.classList.remove('hidden');
    }
    state.employeeId   = id;
    state.employeeName = name;
    document.getElementById('emp-id-input').value = id;
    document.getElementById('emp-selected-name').textContent = name;
    document.getElementById('emp-selected-bar').classList.remove('hidden');
    document.getElementById('btn-step1-next').disabled = false;
}

function clearEmployee() {
    state.employeeId = '';
    state.employeeName = '';
    document.getElementById('emp-id-input').value = '';
    document.getElementById('emp-selected-bar').classList.add('hidden');
    document.getElementById('btn-step1-next').disabled = true;
    document.querySelectorAll('.emp-row').forEach(r => {
        r.classList.remove('selected-emp','border-[#1B1444]','bg-indigo-50');
        r.querySelector('.check-icon')?.classList.add('hidden');
    });
}

// ── Step 2: Module ────────────────────────────────────────────────────────────
document.querySelectorAll('.mod-card').forEach(card => {
    card.addEventListener('click', () => {
        document.querySelectorAll('.mod-card').forEach(c => {
            c.classList.remove('border-[#1B1444]','bg-indigo-50');
            c.classList.add('border-gray-100');
        });
        card.classList.add('border-[#1B1444]','bg-indigo-50');
        card.classList.remove('border-gray-100');
        state.moduleId   = card.dataset.id;
        state.moduleName = card.dataset.name;
        document.getElementById('mod-id-input').value = card.dataset.id;
        document.getElementById('btn-step2-next').disabled = false;
        // Reset downstream
        state.deptId = ''; state.deptName = '';
        state.posId  = ''; state.posName  = '';
        state.roleId = ''; state.roleName = '';
        document.getElementById('dept-id-input').value = '';
        document.getElementById('pos-id-input').value  = '';
        document.getElementById('role-id-input').value = '';
    });
});

// ── Step 3: Departments ───────────────────────────────────────────────────────
function loadDepts() {
    if (!state.moduleId) return;
    const list = document.getElementById('dept-list');
    list.innerHTML = '';
    document.getElementById('dept-loading').classList.remove('hidden');
    fetch(`/hr/module-departments/by-module/${state.moduleId}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('dept-loading').classList.add('hidden');
            if (!data.length) {
                list.innerHTML = '<p class="col-span-2 text-sm text-gray-400 text-center py-4">No departments defined for this module.</p>';
                return;
            }
            data.forEach(d => {
                const div = document.createElement('div');
                div.className = 'dept-card flex items-center gap-3 p-3 rounded-xl border-2 border-gray-100 cursor-pointer hover:border-[#1B1444] transition-all';
                div.dataset.id = d.id;
                div.dataset.name = d.name;
                div.innerHTML = `
                    <div class="w-8 h-8 rounded-lg bg-[#1B1444]15 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-building text-[#1B1444] text-sm"></i>
                    </div>
                    <span class="font-semibold text-gray-800 text-sm">${d.name}</span>
                    <div class="check-icon hidden text-[#1B1444] ml-auto">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    </div>`;
                div.addEventListener('click', () => selectDept(d.id, d.name, div));
                list.appendChild(div);
            });
            // Restore selection
            if (state.deptId) {
                const existing = list.querySelector(`[data-id="${state.deptId}"]`);
                if (existing) selectDept(state.deptId, state.deptName, existing);
            }
        })
        .catch(() => { document.getElementById('dept-loading').classList.add('hidden'); });
}

function selectDept(id, name, el) {
    document.querySelectorAll('.dept-card').forEach(c => {
        c.classList.remove('border-[#1B1444]','bg-indigo-50');
        c.querySelector('.check-icon')?.classList.add('hidden');
    });
    el.classList.add('border-[#1B1444]','bg-indigo-50');
    el.querySelector('.check-icon')?.classList.remove('hidden');
    state.deptId = id;
    state.deptName = name;
    document.getElementById('dept-id-input').value = id;
}

function skipDept() {
    state.deptId = ''; state.deptName = 'None';
    document.getElementById('dept-id-input').value = '';
    goStep(4);
}

// ── Step 4: Positions ─────────────────────────────────────────────────────────
function loadPositions() {
    if (!state.moduleId) return;
    const list = document.getElementById('pos-list');
    list.innerHTML = '';
    document.getElementById('pos-loading').classList.remove('hidden');
    const url = `/hr/module-positions/by-module/${state.moduleId}` + (state.deptId ? `?dept_id=${state.deptId}` : '');
    fetch(url)
        .then(r => r.json())
        .then(data => {
            document.getElementById('pos-loading').classList.add('hidden');
            if (!data.length) {
                list.innerHTML = '<p class="col-span-2 text-sm text-gray-400 text-center py-4">No positions defined for this module.</p>';
                return;
            }
            const levelColors = { junior:'gray', mid:'blue', senior:'indigo', lead:'purple', manager:'orange' };
            data.forEach(p => {
                const div = document.createElement('div');
                div.className = 'pos-card flex items-start gap-3 p-3 rounded-xl border-2 border-gray-100 cursor-pointer hover:border-[#1B1444] transition-all';
                div.dataset.id = p.id;
                div.dataset.name = p.name;
                div.innerHTML = `
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <i class="fas fa-briefcase text-indigo-500 text-sm"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-gray-800 text-sm">${p.name}</div>
                        <span class="text-[10px] text-gray-500 capitalize">${p.level || ''}</span>
                    </div>
                    <div class="check-icon hidden text-[#1B1444]">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    </div>`;
                div.addEventListener('click', () => selectPos(p.id, p.name, div));
                list.appendChild(div);
            });
            if (state.posId) {
                const existing = list.querySelector(`[data-id="${state.posId}"]`);
                if (existing) selectPos(state.posId, state.posName, existing);
            }
        })
        .catch(() => { document.getElementById('pos-loading').classList.add('hidden'); });
}

function selectPos(id, name, el) {
    document.querySelectorAll('.pos-card').forEach(c => {
        c.classList.remove('border-[#1B1444]','bg-indigo-50');
        c.querySelector('.check-icon')?.classList.add('hidden');
    });
    el.classList.add('border-[#1B1444]','bg-indigo-50');
    el.querySelector('.check-icon')?.classList.remove('hidden');
    state.posId = id; state.posName = name;
    document.getElementById('pos-id-input').value = id;
}

function skipPos() {
    state.posId = ''; state.posName = 'None';
    document.getElementById('pos-id-input').value = '';
    goStep(5);
}

// ── Step 5: Roles ─────────────────────────────────────────────────────────────
function loadRoles() {
    if (!state.moduleId) return;
    const list = document.getElementById('role-list');
    list.innerHTML = '';
    document.getElementById('role-loading').classList.remove('hidden');
    fetch(`/hr/module-roles/by-module/${state.moduleId}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('role-loading').classList.add('hidden');
            if (!data.length) {
                list.innerHTML = '<p class="text-sm text-gray-400 text-center py-4">No roles defined for this module.</p>';
                return;
            }
            data.forEach(r => {
                const div = document.createElement('div');
                div.className = 'role-card flex items-start gap-3 p-4 rounded-xl border-2 border-gray-100 cursor-pointer hover:border-[#1B1444] transition-all';
                div.dataset.id   = r.id;
                div.dataset.name = r.name;
                div.innerHTML = `
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-shield-alt text-indigo-500"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-800 text-sm">${r.name}</span>
                            ${r.is_default ? '<span class="text-[10px] bg-indigo-50 text-indigo-600 border border-indigo-100 px-1.5 py-0.5 rounded">Default</span>' : ''}
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5">${r.description || ''}</p>
                    </div>
                    <div class="check-icon hidden text-[#1B1444]">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    </div>`;
                div.addEventListener('click', () => selectRole(r.id, r.name, div));
                list.appendChild(div);
            });
            if (state.roleId) {
                const existing = list.querySelector(`[data-id="${state.roleId}"]`);
                if (existing) selectRole(state.roleId, state.roleName, existing);
            }
        })
        .catch(() => { document.getElementById('role-loading').classList.add('hidden'); });
}

function selectRole(id, name, el) {
    document.querySelectorAll('.role-card').forEach(c => {
        c.classList.remove('border-[#1B1444]','bg-indigo-50');
        c.querySelector('.check-icon')?.classList.add('hidden');
    });
    el.classList.add('border-[#1B1444]','bg-indigo-50');
    el.querySelector('.check-icon')?.classList.remove('hidden');
    state.roleId   = id;
    state.roleName = name;
    document.getElementById('role-id-input').value = id;

    // Load permission preview
    fetch(`/hr/module-roles/by-module/${state.moduleId}/permissions`)
        .then(r => r.json())
        .then(data => {
            // Flatten all permissions to find those associated with this role
            // Simpler: fetch permissions for this module, show what this role grants
            fetch(`/hr/module-roles/${id}/permissions-preview`)
                .then(r2 => r2.json())
                .then(slugs => {
                    const preview = document.getElementById('perm-preview');
                    const body    = document.getElementById('perm-preview-body');
                    if (!slugs.length) { preview.classList.add('hidden'); return; }
                    body.innerHTML = slugs.map(s =>
                        `<span class="text-[11px] bg-indigo-50 text-indigo-700 border border-indigo-100 px-2 py-0.5 rounded font-mono">${s.split('.').pop()}</span>`
                    ).join('');
                    preview.classList.remove('hidden');
                }).catch(() => {});
        }).catch(() => {});
}

// ── Step 6: Type & Level ──────────────────────────────────────────────────────
document.querySelectorAll('.type-card').forEach(card => {
    card.addEventListener('click', () => {
        document.querySelectorAll('.type-card').forEach(c => {
            c.classList.remove('border-[#1B1444]','bg-indigo-50');
            c.classList.add('border-gray-100');
        });
        card.classList.add('border-[#1B1444]','bg-indigo-50');
        card.classList.remove('border-gray-100');
        state.assignType      = card.dataset.type;
        state.assignTypeLabel = card.dataset.label;
        document.getElementById('assignment-type-input').value = card.dataset.type;
        // If temporary or project, suggest end date
        const hint = document.getElementById('end-date-hint');
        if (['temporary','project_based','acting'].includes(card.dataset.type)) {
            hint.textContent = '(recommended for this type)';
            hint.classList.add('text-orange-500');
        } else {
            hint.textContent = '(optional)';
            hint.classList.remove('text-orange-500');
        }
    });
});

document.querySelectorAll('.level-card').forEach(card => {
    card.addEventListener('click', () => {
        document.querySelectorAll('.level-card').forEach(c => {
            c.classList.remove('border-[#1B1444]','bg-indigo-50');
            c.classList.add('border-gray-100');
        });
        card.classList.add('border-[#1B1444]','bg-indigo-50');
        card.classList.remove('border-gray-100');
        state.accessLevel      = card.dataset.level;
        state.accessLevelLabel = card.dataset.label;
        document.getElementById('access-level-input').value = card.dataset.level;
    });
});

// ── Step 7: Dates ─────────────────────────────────────────────────────────────
function setEndPreset(days) {
    const endInput = document.getElementById('end-date-input');
    if (days === 0) { endInput.value = ''; state.endDate = ''; return; }
    const d = new Date();
    d.setDate(d.getDate() + days);
    const iso = d.toISOString().split('T')[0];
    endInput.value = iso;
    state.endDate  = iso;
}

// ── Step 8: Manager ───────────────────────────────────────────────────────────
document.querySelectorAll('.mgr-row').forEach(row => {
    row.addEventListener('click', () => selectManager(row.dataset.id, row.dataset.name, row));
});

document.getElementById('mgr-search').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.mgr-row').forEach(row => {
        row.style.display = row.dataset.search.includes(q) ? '' : 'none';
        // Hide selected employee from manager list
        if (row.dataset.id === state.employeeId) row.style.display = 'none';
    });
});

function selectManager(id, name, el) {
    if (id === state.employeeId) { alert('An employee cannot report to themselves.'); return; }
    document.querySelectorAll('.mgr-row').forEach(r => {
        r.classList.remove('border-[#1B1444]','bg-indigo-50');
        r.querySelector('.check-icon')?.classList.add('hidden');
    });
    el.classList.add('border-[#1B1444]','bg-indigo-50');
    el.querySelector('.check-icon')?.classList.remove('hidden');
    state.managerId   = id;
    state.managerName = name;
    document.getElementById('mgr-id-input').value = id;
}

function skipManager() {
    state.managerId = ''; state.managerName = 'None';
    document.getElementById('mgr-id-input').value = '';
    document.querySelectorAll('.mgr-row').forEach(r => {
        r.classList.remove('border-[#1B1444]','bg-indigo-50');
        r.querySelector('.check-icon')?.classList.add('hidden');
    });
    goStep(9);
}

// Hide selected employee from manager rows on step load
function filterManagerList() {
    document.querySelectorAll('.mgr-row').forEach(r => {
        if (r.dataset.id === state.employeeId) r.style.display = 'none';
    });
}

// ── Step 10: Confirmation ─────────────────────────────────────────────────────
function buildConfirmation() {
    state.notes = document.querySelector('[name="notes"]').value;
    const roleLabel = document.querySelector('[name="role_in_module"]')?.value || 'staff';

    const rows = [
        { label: 'Employee',          value: state.employeeName || '—',      icon: 'fas fa-user' },
        { label: 'Module',            value: state.moduleName   || '—',      icon: 'fas fa-cube' },
        { label: 'Department',        value: state.deptName     || 'Not specified', icon: 'fas fa-building' },
        { label: 'Position',          value: state.posName      || 'Not specified', icon: 'fas fa-briefcase' },
        { label: 'Module Role',       value: state.roleName     || 'Not specified', icon: 'fas fa-shield-alt' },
        { label: 'Role Label',        value: roleLabel,                       icon: 'fas fa-tag' },
        { label: 'Assignment Type',   value: state.assignTypeLabel,           icon: 'fas fa-star' },
        { label: 'Access Level',      value: state.accessLevelLabel,          icon: 'fas fa-lock' },
        { label: 'Start Date',        value: state.startDate    || 'Today',   icon: 'fas fa-calendar-alt' },
        { label: 'Planned End Date',  value: state.endDate      || 'Open-ended', icon: 'fas fa-calendar-times' },
        { label: 'Reporting Manager', value: state.managerName  || 'Not specified', icon: 'fas fa-user-tie' },
        { label: 'Notes',             value: state.notes        || '—',       icon: 'fas fa-sticky-note' },
    ];

    const body = document.getElementById('confirm-body');
    body.innerHTML = rows.map(r => `
        <div class="px-6 py-3.5 flex items-start gap-4">
            <div class="w-6 flex-shrink-0 text-center mt-0.5">
                <i class="${r.icon} text-gray-300 text-xs"></i>
            </div>
            <div class="w-36 flex-shrink-0 text-xs font-medium text-gray-500">${r.label}</div>
            <div class="flex-1 text-sm text-gray-800 font-semibold">${r.value}</div>
        </div>`).join('');
}

// ── Permission preview endpoint ────────────────────────────────────────────────
// Add a route to support the permissions preview in step 5

// ── Init ──────────────────────────────────────────────────────────────────────
// If there's a pre-selected employee (old input / query param), show its bar
if (state.employeeId && state.employeeName) {
    const bar = document.getElementById('emp-selected-bar');
    document.getElementById('emp-selected-name').textContent = state.employeeName;
    bar?.classList.remove('hidden');
}

// Step tabs allow clicking back to a completed step
document.querySelectorAll('.step-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const n = parseInt(tab.dataset.step);
        if (n < state.step) goStep(n); // only allow going back
    });
});

// Auto-hide the selected employee from manager list
document.querySelector('[data-step="8"]').addEventListener('mouseenter', filterManagerList);

// Init progress
updateProgress({{ old('employee_id') ? 10 : 1 }});
</script>
@endpush
@endsection
