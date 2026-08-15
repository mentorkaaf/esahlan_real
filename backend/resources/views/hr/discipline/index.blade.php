@extends('hr.layouts.app')
@section('title', 'Disciplinary Cases')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Disciplinary Cases</h1>
    <a href="{{ route('hr.discipline.create') }}"
       class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
        ＋ Open New Case
    </a>
</div>

@if(session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">{{ session('error') }}</div>
@endif

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Employee</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Title</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Category</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Severity</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Opened</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
        @forelse($cases as $case)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium">
                    {{ $case->employee->full_name }}
                    <div class="text-xs text-gray-400">{{ $case->employee->department?->name }}</div>
                </td>
                <td class="px-4 py-3 text-gray-700">{{ $case->title }}</td>
                <td class="px-4 py-3 capitalize text-gray-600">{{ $case->category }}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $case->getSeverityBadgeClass() }}">
                        {{ ucfirst($case->severity) }}
                    </span>
                </td>
                <td class="px-4 py-3">
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $case->getStatusBadgeClass() }}">
                        {{ ucfirst($case->status) }}
                    </span>
                </td>
                <td class="px-4 py-3 text-gray-500">{{ $case->created_at->format('d M Y') }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('hr.discipline.show', $case) }}" class="text-blue-600 hover:underline">View</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No disciplinary cases yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    @if($cases->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $cases->links() }}</div>
    @endif
</div>
@endsection
