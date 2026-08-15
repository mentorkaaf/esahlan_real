@extends('hr.layouts.app')
@section('title', 'Positions')
@section('heading', 'Positions')

@section('content')
<div class="flex justify-end mb-6">
    <a href="{{ route('hr.positions.create') }}"
       class="bg-[#F7941D] hover:bg-[#E07800] text-white px-4 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Position
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Title</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Department</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Grade</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Salary Range</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Employees</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($positions as $pos)
            <tr class="hover:bg-gray-50/50">
                <td class="px-6 py-4 font-medium text-gray-900">{{ $pos->title }}</td>
                <td class="px-4 py-4 text-gray-500">{{ $pos->department?->name }}</td>
                <td class="px-4 py-4 text-gray-500">{{ $pos->grade ?? '—' }}</td>
                <td class="px-4 py-4 text-gray-500">
                    @if($pos->min_salary || $pos->max_salary)
                        ${{ number_format($pos->min_salary, 0) }} – ${{ number_format($pos->max_salary, 0) }}
                    @else —
                    @endif
                </td>
                <td class="px-4 py-4 text-center text-gray-700">{{ $pos->employees_count }}</td>
                <td class="px-4 py-4 text-right">
                    <a href="{{ route('hr.positions.edit', $pos) }}" class="text-[#1B1444] hover:underline text-xs font-medium mr-3">Edit</a>
                    <form method="POST" action="{{ route('hr.positions.destroy', $pos) }}" class="inline"
                          onsubmit="return confirm('Delete this position?')">
                        @csrf @method('DELETE')
                        <button class="text-red-400 hover:text-red-600 text-xs">Delete</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-6 py-12 text-center text-gray-400">No positions yet</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
