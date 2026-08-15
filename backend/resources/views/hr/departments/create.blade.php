@extends('hr.layouts.app')
@section('title', 'Add Department')
@section('heading', 'Add Department')

@section('content')
<div class="max-w-xl">
    <form method="POST" action="{{ route('hr.departments.store') }}" class="space-y-5">
        @csrf
        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">
            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            <x-hr-input name="name" label="Department Name" required/>
            <x-hr-input name="code" label="Code (e.g. OPS, HR, FIN)" required/>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Parent Department</label>
                <select name="parent_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">None (top-level)</option>
                    @foreach($parents as $p)
                    <option value="{{ $p->id }}" {{ old('parent_id')==$p->id?'selected':'' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Department Manager</label>
                <select name="manager_employee_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">None</option>
                    @foreach($employees as $emp)
                    <option value="{{ $emp->id }}" {{ old('manager_employee_id')==$emp->id?'selected':'' }}>{{ $emp->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                <textarea name="description" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">{{ old('description') }}</textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" id="is_active" checked class="rounded border-gray-300">
                <label for="is_active" class="text-sm text-gray-700">Active</label>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="bg-[#F7941D] hover:bg-[#E07800] text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">Create</button>
            <a href="{{ route('hr.departments.index') }}" class="text-gray-500 hover:text-gray-700 text-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection
