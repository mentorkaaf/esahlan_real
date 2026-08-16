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

            {{-- Role in module --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Role in Module</label>
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
@endsection
