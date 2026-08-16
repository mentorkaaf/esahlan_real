@extends('hr.layouts.app')
@section('title', 'Edit Role — ' . $moduleRole->name)
@section('heading', 'Edit Module Role')

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('hr.module-roles.update', $moduleRole) }}" class="space-y-5">
        @csrf @method('PUT')

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">
            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">

            {{-- Module (read-only) --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Module</label>
                <div class="flex items-center gap-2 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg">
                    <div class="w-5 h-5 rounded flex items-center justify-center"
                         style="background-color: {{ $moduleRole->module?->color ?? '#1B1444' }}20;">
                        <i class="{{ $moduleRole->module?->icon ?? 'fas fa-cube' }} text-xs"
                           style="color: {{ $moduleRole->module?->color ?? '#1B1444' }};"></i>
                    </div>
                    <span class="text-sm text-gray-700">{{ $moduleRole->module?->name }}</span>
                </div>
                <p class="text-xs text-gray-400 mt-1">Module cannot be changed after creation.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Role Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', $moduleRole->name) }}"
                       required maxlength="120"
                       @if($moduleRole->is_system) readonly class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2.5 text-sm text-gray-500"
                       @else class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]" @endif>
                @if($moduleRole->is_system)
                <p class="text-xs text-gray-400 mt-1">System role names cannot be changed.</p>
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                <textarea name="description" rows="2" maxlength="500"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">{{ old('description', $moduleRole->description) }}</textarea>
            </div>

            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" name="is_default" value="1"
                           {{ old('is_default', $moduleRole->is_default) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-[#1B1444]">
                    Show first in pickers
                </label>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" min="0"
                           value="{{ old('sort_order', $moduleRole->sort_order) }}"
                           class="w-20 border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Status</label>
                    <select name="status"
                            class="border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none"
                            @if($moduleRole->is_system) disabled @endif>
                        <option value="active"   {{ old('status', $moduleRole->status) === 'active'   ? 'selected' : '' }}>Active</option>
                        <option value="archived" {{ old('status', $moduleRole->status) === 'archived' ? 'selected' : '' }}>Archived</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Permission matrix --}}
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
            <div class="divide-y divide-gray-50">
                @foreach($groups as $groupKey => $groupLabel)
                @php $perms = $availablePermissions->get($groupKey, collect()) @endphp
                <div class="px-6 py-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-sm font-medium text-gray-700">{{ $groupLabel }}</span>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @foreach($perms as $perm)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox"
                                   name="permissions[]"
                                   value="{{ $perm->id }}"
                                   {{ isset($grantedIds[$perm->id]) || in_array($perm->id, old('permissions', array_keys($grantedIds))) ? 'checked' : '' }}
                                   class="perm-check rounded border-gray-300 text-[#1B1444]">
                            <span class="text-xs text-gray-700">{{ last(explode('.', $perm->slug)) }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit"
                    class="bg-[#F7941D] hover:bg-[#E07800] text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                Save Changes
            </button>
            <a href="{{ route('hr.module-roles.show', $moduleRole) }}"
               class="text-gray-500 hover:text-gray-700 text-sm">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
function toggleAll(state) {
    document.querySelectorAll('.perm-check').forEach(cb => cb.checked = state);
}
</script>
@endpush
@endsection
