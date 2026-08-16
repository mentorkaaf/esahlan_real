@extends('hr.layouts.app')
@section('title', 'New Module Role')
@section('heading', 'Create Module Role')

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('hr.module-roles.store') }}" class="space-y-5">
        @csrf

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">
            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">

            {{-- Module --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Module <span class="text-red-500">*</span>
                </label>
                <select name="module_id" id="module-select" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">— Select module —</option>
                    @foreach($modules as $mod)
                    <option value="{{ $mod->id }}"
                            data-slug="{{ $mod->slug }}"
                        {{ old('module_id', $selectedModule?->id) == $mod->id ? 'selected' : '' }}>
                        {{ $mod->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Name --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Role Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name') }}"
                       required maxlength="120" placeholder="e.g. Delivery Coordinator"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
            </div>

            {{-- Description --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                <textarea name="description" rows="2" maxlength="500"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]"
                          placeholder="Describe what this role allows.">{{ old('description') }}</textarea>
            </div>

            {{-- Options --}}
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" name="is_default" value="1"
                           {{ old('is_default') ? 'checked' : '' }}
                           class="rounded border-gray-300 text-[#1B1444]">
                    Show first in pickers
                </label>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" min="0" value="{{ old('sort_order', 0) }}"
                           class="w-20 border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none">
                </div>
            </div>
        </div>

        {{-- Permission matrix (loaded by JS after module selected) --}}
        <div id="perm-section" class="{{ $availablePermissions->isEmpty() ? 'hidden' : '' }}">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Permissions</h3>
                    <div class="flex gap-3">
                        <button type="button" onclick="toggleAll(true)"
                                class="text-xs text-indigo-600 hover:underline">Select all</button>
                        <button type="button" onclick="toggleAll(false)"
                                class="text-xs text-gray-400 hover:underline">Clear all</button>
                    </div>
                </div>
                <div id="perm-matrix" class="divide-y divide-gray-50">
                    @foreach($groups as $groupKey => $groupLabel)
                    @php $perms = $availablePermissions->get($groupKey, collect()) @endphp
                    <div class="px-6 py-4" data-group="{{ $groupKey }}">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-sm font-medium text-gray-700">{{ $groupLabel }}</span>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            @foreach($perms as $perm)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="permissions[]"
                                       value="{{ $perm->id }}"
                                       {{ in_array($perm->id, old('permissions', [])) ? 'checked' : '' }}
                                       class="perm-check rounded border-gray-300 text-[#1B1444]">
                                <span class="text-xs text-gray-700">{{ last(explode('.', $perm->slug)) }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit"
                    class="bg-[#F7941D] hover:bg-[#E07800] text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                Create Role
            </button>
            <a href="{{ route('hr.module-roles.index') }}" class="text-gray-500 hover:text-gray-700 text-sm">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
function toggleAll(state) {
    document.querySelectorAll('.perm-check').forEach(cb => cb.checked = state);
}

document.getElementById('module-select').addEventListener('change', function () {
    const moduleId = this.value;
    const permSection = document.getElementById('perm-section');
    const matrix = document.getElementById('perm-matrix');
    if (!moduleId) { permSection.classList.add('hidden'); return; }

    const groupOrder = @json($groups);
    fetch(`/hr/module-roles/by-module/${moduleId}/permissions`)
        .then(r => r.json())
        .then(data => {
            matrix.innerHTML = '';
            Object.entries(groupOrder).forEach(([gk, gl]) => {
                const perms = data[gk] || [];
                if (perms.length) renderGroup(gk, gl, perms);
            });
            permSection.classList.remove('hidden');
        });
});

function renderGroup(groupKey, groupLabel, perms) {
    if (!perms.length) return;
    const matrix = document.getElementById('perm-matrix');
    const div = document.createElement('div');
    div.className = 'px-6 py-4 border-t border-gray-50';
    div.innerHTML = `
        <div class="flex items-center gap-2 mb-3">
            <span class="text-sm font-medium text-gray-700">${groupLabel}</span>
        </div>
        <div class="flex flex-wrap gap-3">
            ${perms.map(p => `
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="permissions[]" value="${p.id}" class="perm-check rounded border-gray-300">
                    <span class="text-xs text-gray-700">${p.slug.split('.').pop()}</span>
                </label>`).join('')}
        </div>`;
    matrix.appendChild(div);
}
</script>
@endpush
@endsection
