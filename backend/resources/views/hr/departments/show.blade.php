@extends('hr.layouts.app')
@section('title', $department->name)
@section('heading', $department->name)
@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <p class="text-gray-500 text-sm mb-4">{{ $department->description }}</p>
    <dl class="grid grid-cols-2 gap-4 text-sm mb-6">
        <div><dt class="text-gray-400">Code</dt><dd class="font-mono text-gray-700">{{ $department->code }}</dd></div>
        <div><dt class="text-gray-400">Parent</dt><dd>{{ $department->parent?->name ?? '—' }}</dd></div>
        <div><dt class="text-gray-400">Manager</dt><dd>{{ $department->manager?->full_name ?? '—' }}</dd></div>
    </dl>
    <h3 class="font-semibold text-gray-700 mb-3">Employees ({{ $department->employees->count() }})</h3>
    <div class="space-y-2">
        @foreach($department->employees as $emp)
        <div class="flex items-center justify-between py-2 border-b border-gray-50">
            <div class="font-medium text-sm text-gray-800">{{ $emp->full_name }}</div>
            <div class="text-xs text-gray-400">{{ $emp->position?->title }}</div>
        </div>
        @endforeach
    </div>
    <div class="mt-4">
        <a href="{{ route('hr.departments.edit', $department) }}" class="text-xs bg-[#1B1444] text-white px-4 py-2 rounded-lg hover:bg-[#2D2467] transition-colors">Edit</a>
    </div>
</div>
@endsection
