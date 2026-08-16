@extends('hr.layouts.app')
@section('title', 'Edit Position')
@section('heading', 'Edit Position — ' . $modulePosition->name)

@section('content')
<div class="max-w-xl">
    <form method="POST" action="{{ route('hr.module-positions.update', $modulePosition) }}" class="space-y-5">
        @csrf @method('PUT')

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">
            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">

            {{-- Module (read-only on edit) --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Module</label>
                <div class="flex items-center gap-2 px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg">
                    <div class="w-5 h-5 rounded flex items-center justify-center"
                         style="background-color: {{ $modulePosition->module?->color ?? '#1B1444' }}20;">
                        <i class="{{ $modulePosition->module?->icon ?? 'fas fa-cube' }} text-xs"
                           style="color: {{ $modulePosition->module?->color ?? '#1B1444' }};"></i>
                    </div>
                    <span class="text-sm text-gray-700">{{ $modulePosition->module?->name }}</span>
                </div>
                <p class="text-xs text-gray-400 mt-1">Module cannot be changed after creation.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Department (optional)</label>
                <select name="module_department_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">— All departments —</option>
                    @foreach($depts as $d)
                    <option value="{{ $d->id }}"
                        {{ old('module_department_id', $modulePosition->module_department_id) == $d->id ? 'selected' : '' }}>
                        {{ $d->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Position Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', $modulePosition->name) }}"
                       required maxlength="120"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                <textarea name="description" rows="2" maxlength="500"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">{{ old('description', $modulePosition->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Level <span class="text-red-500">*</span></label>
                    <select name="level" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                        @foreach($levels as $lvl)
                        <option value="{{ $lvl }}"
                            {{ old('level', $modulePosition->level) === $lvl ? 'selected' : '' }}>
                            {{ ucfirst($lvl) }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Sort Order</label>
                    <input type="number" name="sort_order" min="0"
                           value="{{ old('sort_order', $modulePosition->sort_order) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    HR Position Link
                    @if($modulePosition->hrPosition)
                    <span class="text-xs text-indigo-600 font-normal">(currently: {{ $modulePosition->hrPosition->title }})</span>
                    @endif
                </label>
                <select name="hr_position_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">— None / Auto-detect —</option>
                    @foreach($hrPositions as $hrp)
                    <option value="{{ $hrp->id }}"
                        {{ old('hr_position_id', $modulePosition->hr_position_id) == $hrp->id ? 'selected' : '' }}>
                        {{ $hrp->title }}
                    </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit"
                    class="bg-[#F7941D] hover:bg-[#E07800] text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                Save Changes
            </button>
            <a href="{{ route('hr.module-positions.show', $modulePosition) }}"
               class="text-gray-500 hover:text-gray-700 text-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection
