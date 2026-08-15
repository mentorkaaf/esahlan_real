@extends('hr.layouts.app')
@section('title', 'Add Position')
@section('heading', 'Add Position')
@section('content')
<div class="max-w-xl">
    <form method="POST" action="{{ route('hr.positions.store') }}" class="space-y-5">
        @csrf
        @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Department <span class="text-red-500">*</span></label>
                <select name="department_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
                    <option value="">Select</option>
                    @foreach($departments as $d)<option value="{{ $d->id }}" {{ old('department_id')==$d->id?'selected':'' }}>{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <x-hr-input name="title" label="Position Title" required/>
            <x-hr-input name="grade" label="Grade (e.g. L1, M2)"/>
            <div class="grid grid-cols-2 gap-4">
                <x-hr-input name="min_salary" label="Min Salary (USD)" type="number" step="0.01"/>
                <x-hr-input name="max_salary" label="Max Salary (USD)" type="number" step="0.01"/>
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
            <a href="{{ route('hr.positions.index') }}" class="text-gray-500 hover:text-gray-700 text-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection
