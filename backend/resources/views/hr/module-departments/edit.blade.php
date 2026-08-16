@extends('hr.layouts.app')
@section('title', 'Edit ' . $moduleDepartment->name)
@section('heading', 'Edit Department — ' . $moduleDepartment->name)

@section('content')
<div class="max-w-xl">
    <form method="POST" action="{{ route('hr.module-departments.update', $moduleDepartment) }}" class="space-y-5">
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
                         style="background-color: {{ $moduleDepartment->module?->color ?? '#1B1444' }}20;">
                        <i class="{{ $moduleDepartment->module?->icon ?? 'fas fa-cube' }} text-xs"
                           style="color: {{ $moduleDepartment->module?->color ?? '#1B1444' }};"></i>
                    </div>
                    <span class="text-sm text-gray-700">{{ $moduleDepartment->module?->name }}</span>
                </div>
                <p class="text-xs text-gray-400 mt-1">Module cannot be changed after creation.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Department Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', $moduleDepartment->name) }}"
                       required maxlength="120"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                <textarea name="description" rows="2" maxlength="500"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">{{ old('description', $moduleDepartment->description) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Department Manager</label>
                <select name="manager_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">— No manager —</option>
                    @foreach($employees as $emp)
                    <option value="{{ $emp->id }}"
                        {{ old('manager_id', $moduleDepartment->manager_id) == $emp->id ? 'selected' : '' }}>
                        {{ $emp->full_name }}
                        @if($emp->position) ({{ $emp->position->title }}) @endif
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="w-32">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Sort Order</label>
                <input type="number" name="sort_order"
                       value="{{ old('sort_order', $moduleDepartment->sort_order) }}" min="0"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit"
                    class="bg-[#F7941D] hover:bg-[#E07800] text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                Save Changes
            </button>
            <a href="{{ route('hr.module-departments.show', $moduleDepartment) }}"
               class="text-gray-500 hover:text-gray-700 text-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection
