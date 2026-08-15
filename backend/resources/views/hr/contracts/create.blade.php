@extends('hr.layouts.app')
@section('title', 'Add Contract')
@section('heading', 'Add Contract — ' . $employee->full_name)
@section('content')
<div class="max-w-xl">
    <form method="POST" action="{{ route('hr.contracts.store', $employee) }}" enctype="multipart/form-data" class="space-y-5">
        @csrf
        @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            <x-hr-input name="type" label="Contract Type" required placeholder="e.g. Permanent, Fixed-Term"/>
            <div class="grid grid-cols-2 gap-4">
                <x-hr-input name="start_date" label="Start Date" type="date" required/>
                <x-hr-input name="end_date" label="End Date" type="date"/>
            </div>
            <x-hr-input name="salary" label="Salary (USD)" type="number" step="0.01" required/>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Upload Contract (PDF)</label>
                <input type="file" name="file" accept=".pdf,.doc,.docx" class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#1B1444] file:text-white hover:file:bg-[#2D2467]">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
                <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]"></textarea>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <button type="submit" class="bg-[#F7941D] hover:bg-[#E07800] text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">Save Contract</button>
            <a href="{{ route('hr.employees.show', $employee) }}" class="text-gray-500 hover:text-gray-700 text-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection
