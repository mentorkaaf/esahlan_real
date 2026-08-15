@extends('hr.layouts.app')
@section('title', $position->title)
@section('heading', $position->title)
@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-xl">
    <dl class="grid grid-cols-2 gap-4 text-sm mb-4">
        <div><dt class="text-gray-400">Department</dt><dd>{{ $position->department?->name }}</dd></div>
        <div><dt class="text-gray-400">Grade</dt><dd>{{ $position->grade ?? '—' }}</dd></div>
        <div><dt class="text-gray-400">Salary Range</dt><dd>${{ number_format($position->min_salary,0) }} – ${{ number_format($position->max_salary,0) }}</dd></div>
        <div><dt class="text-gray-400">Employees</dt><dd>{{ $position->employees->count() }}</dd></div>
    </dl>
    <a href="{{ route('hr.positions.edit', $position) }}" class="text-xs bg-[#1B1444] text-white px-4 py-2 rounded-lg hover:bg-[#2D2467] transition-colors">Edit</a>
</div>
@endsection
