@extends('hr.layouts.app')
@section('title', 'Departments')
@section('heading', 'Departments')

@section('content')
<div class="flex justify-end mb-6">
    <a href="{{ route('hr.departments.create') }}"
       class="bg-[#F7941D] hover:bg-[#E07800] text-white px-4 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Department
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Code</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Parent</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Manager</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Employees</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Active</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($departments as $dept)
            <tr class="hover:bg-gray-50/50">
                <td class="px-6 py-4 font-medium text-gray-900">{{ $dept->name }}</td>
                <td class="px-4 py-4 text-gray-500 font-mono text-xs">{{ $dept->code }}</td>
                <td class="px-4 py-4 text-gray-500">{{ $dept->parent?->name ?? '—' }}</td>
                <td class="px-4 py-4 text-gray-500">{{ $dept->manager?->full_name ?? '—' }}</td>
                <td class="px-4 py-4 text-center text-gray-700">{{ $dept->employees_count }}</td>
                <td class="px-4 py-4 text-center">
                    <span class="inline-block w-2 h-2 rounded-full {{ $dept->is_active ? 'bg-green-400' : 'bg-gray-300' }}"></span>
                </td>
                <td class="px-4 py-4 text-right">
                    <a href="{{ route('hr.departments.edit', $dept) }}" class="text-[#1B1444] hover:underline text-xs font-medium mr-3">Edit</a>
                    <form method="POST" action="{{ route('hr.departments.destroy', $dept) }}" class="inline"
                          onsubmit="return confirm('Delete this department?')">
                        @csrf @method('DELETE')
                        <button class="text-red-400 hover:text-red-600 text-xs">Delete</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-6 py-12 text-center text-gray-400">No departments yet</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
