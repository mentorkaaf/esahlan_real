@extends('hr.layouts.app')
@section('title', 'Assign Employee')
@section('heading', 'Assign Employee to Module')

@section('content')
<div class="max-w-xl">
    <form method="POST" action="{{ route('hr.workforce.store') }}" class="space-y-5">
        @csrf

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">
            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">

            {{-- Employee --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Employee <span class="text-red-500">*</span>
                </label>
                <select name="employee_id" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">— Select employee —</option>
                    @foreach($employees as $emp)
                    <option value="{{ $emp->id }}"
                        {{ (old('employee_id', $selectedEmployee?->id) == $emp->id) ? 'selected' : '' }}>
                        {{ $emp->full_name }}
                        @if($emp->position) ({{ $emp->position->title }}) @endif
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Module --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Business Module <span class="text-red-500">*</span>
                </label>
                <select name="module_id" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">— Select module —</option>
                    @foreach($modules as $mod)
                    <option value="{{ $mod->id }}"
                        {{ (old('module_id', $selectedModule?->id) == $mod->id) ? 'selected' : '' }}>
                        {{ $mod->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Department --}}
            <div id="dept-picker" class="{{ $moduleDepartments->isEmpty() ? 'hidden' : '' }}">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Department</label>
                <select name="module_department_id" id="dept-select"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">— No specific department —</option>
                    @foreach($moduleDepartments as $dept)
                    <option value="{{ $dept->id }}" {{ old('module_department_id') == $dept->id ? 'selected' : '' }}>
                        {{ $dept->name }}
                    </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Which department within this module will this employee join?</p>
            </div>

            {{-- Position in module --}}
            <div id="pos-picker" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Position</label>
                <select name="module_position_id" id="pos-select"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">— No specific position —</option>
                </select>
            </div>

            {{-- Module Role (permissions) --}}
            <div id="module-role-picker" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Module Role
                    <span class="text-xs text-gray-400 font-normal ml-1">(determines permissions)</span>
                </label>
                <select name="module_role_id" id="module-role-select"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">— No specific role —</option>
                </select>
                <p class="text-xs text-gray-400 mt-1">
                    The selected role's permissions will be granted to the employee's user account automatically.
                </p>
            </div>

            {{-- Role in module --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Role in Module
                    <span class="text-xs text-gray-400 font-normal ml-1">(label only)</span>
                </label>
                <select name="role_in_module"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="staff"      {{ old('role_in_module') === 'staff'      ? 'selected' : '' }}>Staff (default)</option>
                    <option value="supervisor" {{ old('role_in_module') === 'supervisor' ? 'selected' : '' }}>Supervisor</option>
                    <option value="agent"      {{ old('role_in_module') === 'agent'      ? 'selected' : '' }}>Agent</option>
                    <option value="operator"   {{ old('role_in_module') === 'operator'   ? 'selected' : '' }}>Operator</option>
                    <option value="driver"     {{ old('role_in_module') === 'driver'     ? 'selected' : '' }}>Driver</option>
                    <option value="dispatcher" {{ old('role_in_module') === 'dispatcher' ? 'selected' : '' }}>Dispatcher</option>
                    <option value="support"    {{ old('role_in_module') === 'support'    ? 'selected' : '' }}>Support</option>
                </select>
                <p class="text-xs text-gray-400 mt-1">
                    Selecting <strong>Agent</strong> for <strong>eRent</strong> will automatically grant the Rent Agent role to this employee's user account.
                </p>
            </div>

            {{-- Notes --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
                <textarea name="notes" rows="2"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]"
                          placeholder="Optional: reason for assignment, scope, etc.">{{ old('notes') }}</textarea>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit"
                    class="bg-[#F7941D] hover:bg-[#E07800] text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                Assign Employee
            </button>
            <a href="{{ route('hr.workforce.index') }}" class="text-gray-500 hover:text-gray-700 text-sm">Cancel</a>
        </div>
    </form>
</div>
@push('scripts')
<script>
function loadPositions(moduleId, deptId) {
    const posPicker = document.getElementById('pos-picker');
    const posSelect = document.getElementById('pos-select');
    if (!moduleId) { posPicker.classList.add('hidden'); return; }
    const url = `/hr/module-positions/by-module/${moduleId}` + (deptId ? `?dept_id=${deptId}` : '');
    fetch(url).then(r => r.json()).then(data => {
        posSelect.innerHTML = '<option value="">— No specific position —</option>';
        data.forEach(p => posSelect.innerHTML += `<option value="${p.id}">${p.name} (${p.level})</option>`);
        posPicker.classList.toggle('hidden', data.length === 0);
    }).catch(() => posPicker.classList.add('hidden'));
}

// When module changes, fetch its departments, positions, AND module roles
document.querySelector('[name="module_id"]').addEventListener('change', function() {
    const moduleId = this.value;
    const deptPicker = document.getElementById('dept-picker');
    const deptSelect = document.getElementById('dept-select');
    const roleSelect = document.getElementById('module-role-select');
    const rolePicker = document.getElementById('module-role-picker');

    if (!moduleId) {
        deptPicker.classList.add('hidden');
        deptSelect.innerHTML = '<option value="">— No specific department —</option>';
        document.getElementById('pos-picker').classList.add('hidden');
        rolePicker.classList.add('hidden');
        roleSelect.innerHTML = '<option value="">— No specific role —</option>';
        return;
    }

    fetch(`/hr/module-departments/by-module/${moduleId}`)
        .then(r => r.json())
        .then(data => {
            deptSelect.innerHTML = '<option value="">— No specific department —</option>';
            data.forEach(d => deptSelect.innerHTML += `<option value="${d.id}">${d.name}</option>`);
            deptPicker.classList.toggle('hidden', data.length === 0);
        })
        .catch(() => deptPicker.classList.add('hidden'));

    loadPositions(moduleId, null);

    // Load module roles
    fetch(`/hr/module-roles/by-module/${moduleId}`)
        .then(r => r.json())
        .then(roles => {
            roleSelect.innerHTML = '<option value="">— No specific role —</option>';
            roles.forEach(r => {
                const opt = document.createElement('option');
                opt.value = r.id;
                opt.textContent = r.name + (r.is_default ? ' ★' : '');
                if (r.is_default && roles.length > 0) opt.selected = false;
                roleSelect.appendChild(opt);
            });
            rolePicker.classList.toggle('hidden', roles.length === 0);
        })
        .catch(() => rolePicker.classList.add('hidden'));
});

// When dept changes, refine position list
document.getElementById('dept-select')?.addEventListener('change', function() {
    const moduleId = document.querySelector('[name="module_id"]').value;
    loadPositions(moduleId, this.value);
});
</script>
@endpush
@endsection
