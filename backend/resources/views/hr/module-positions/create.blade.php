@extends('hr.layouts.app')
@section('title', 'New Position')
@section('heading', 'New Module Position')

@section('content')
<div class="max-w-xl">
    <form method="POST" action="{{ route('hr.module-positions.store') }}" class="space-y-5">
        @csrf

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">
            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Business Module <span class="text-red-500">*</span>
                </label>
                <select name="module_id" id="pos-module" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">— Select module —</option>
                    @foreach($modules as $mod)
                    <option value="{{ $mod->id }}"
                        {{ old('module_id', $selectedModule?->id) == $mod->id ? 'selected' : '' }}>
                        {{ $mod->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div id="dept-row" class="{{ $depts->isEmpty() ? 'hidden' : '' }}">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Department (optional)</label>
                <select name="module_department_id" id="pos-dept"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">— All departments —</option>
                    @foreach($depts as $d)
                    <option value="{{ $d->id }}" {{ old('module_department_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">Optionally scope this position to a specific department.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Position Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="120"
                       id="pos-name"
                       placeholder="e.g. Delivery Driver, Operations Coordinator"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                <p id="reuse-hint" class="text-xs text-indigo-600 mt-1 hidden">
                    <i class="fas fa-link mr-1"></i>
                    This name matches an existing HR position — it will be automatically linked.
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                <textarea name="description" rows="2" maxlength="500"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]"
                          placeholder="Responsibilities and scope…">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        Level <span class="text-red-500">*</span>
                    </label>
                    <select name="level" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                        @foreach($levels as $lvl)
                        <option value="{{ $lvl }}" {{ old('level', 'mid') === $lvl ? 'selected' : '' }}>{{ ucfirst($lvl) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Link to Existing HR Position
                    <span class="text-xs text-gray-400 font-normal">(optional — auto-detected by name)</span>
                </label>
                <select name="hr_position_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">— Auto-detect or leave blank —</option>
                    @foreach($hrPositions as $hrp)
                    <option value="{{ $hrp->id }}" {{ old('hr_position_id') == $hrp->id ? 'selected' : '' }}>
                        {{ $hrp->title }}
                    </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1">
                    Linking prevents duplicate positions and lets employees hold the same role in both HR and a module.
                </p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit"
                    class="bg-[#F7941D] hover:bg-[#E07800] text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                Create Position
            </button>
            <a href="{{ route('hr.module-positions.index') }}" class="text-gray-500 hover:text-gray-700 text-sm">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
// Populate dept picker when module changes
document.getElementById('pos-module').addEventListener('change', function() {
    const moduleId = this.value;
    const deptRow = document.getElementById('dept-row');
    const deptSel = document.getElementById('pos-dept');
    if (!moduleId) { deptRow.classList.add('hidden'); return; }
    fetch(`/hr/module-departments/by-module/${moduleId}`)
        .then(r => r.json())
        .then(data => {
            deptSel.innerHTML = '<option value="">— All departments —</option>';
            data.forEach(d => deptSel.innerHTML += `<option value="${d.id}">${d.name}</option>`);
            deptRow.classList.toggle('hidden', data.length === 0);
        });
});

// HR position reuse hint on name input
const hrTitles = @json($hrPositions->pluck('title')->map(fn($t) => strtolower($t)));
document.getElementById('pos-name').addEventListener('input', function() {
    const hint = document.getElementById('reuse-hint');
    hint.classList.toggle('hidden', !hrTitles.includes(this.value.toLowerCase().trim()));
});
</script>
@endpush
@endsection
