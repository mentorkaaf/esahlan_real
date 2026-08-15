@extends('hr.layouts.app')
@section('title', 'Employees')
@section('heading', 'Employees')

@section('content')
<div class="flex items-center justify-between mb-6">
    <form method="GET" class="flex gap-3 flex-1 max-w-2xl">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, number, email..."
               class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
        <select name="department_id" class="border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
            <option value="">All Departments</option>
            @foreach($departments as $dept)
            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
            @endforeach
        </select>
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">
            <option value="">All Status</option>
            @foreach(['active','probation','suspended','terminated','resigned'] as $s)
            <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <button type="submit" class="bg-[#1B1444] text-white px-4 py-2.5 rounded-lg text-sm hover:bg-[#2D2467] transition-colors">Filter</button>
    </form>
    <a href="{{ route('hr.employees.create') }}"
       class="ml-4 bg-[#F7941D] hover:bg-[#E07800] text-white px-4 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add Employee
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Employee</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Dept / Position</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Hired</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($employees as $emp)
            <tr class="hover:bg-gray-50/50">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        @if($emp->photo)
                        <img src="{{ Storage::url($emp->photo) }}" class="w-9 h-9 rounded-full object-cover">
                        @else
                        <div class="w-9 h-9 rounded-full bg-[#1B1444] flex items-center justify-center text-white text-xs font-bold">
                            {{ $emp->initials }}
                        </div>
                        @endif
                        <div>
                            <div class="font-medium text-gray-900">{{ $emp->full_name }}</div>
                            <div class="text-gray-400 text-xs">{{ $emp->employee_no }}</div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-4">
                    <div class="text-gray-700">{{ $emp->department?->name }}</div>
                    <div class="text-gray-400 text-xs">{{ $emp->position?->title }}</div>
                </td>
                <td class="px-4 py-4 text-gray-500 capitalize">{{ str_replace('_', ' ', $emp->employment_type) }}</td>
                <td class="px-4 py-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        {{ $emp->status_color === 'green' ? 'bg-green-100 text-green-700' :
                           ($emp->status_color === 'yellow' ? 'bg-yellow-100 text-yellow-700' :
                           ($emp->status_color === 'red' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')) }}">
                        {{ ucfirst($emp->status) }}
                    </span>
                </td>
                <td class="px-4 py-4 text-gray-500">{{ $emp->hire_date?->format('d M Y') }}</td>
                <td class="px-4 py-4 text-right">
                    <a href="{{ route('hr.employees.show', $emp) }}" class="text-[#1B1444] hover:underline text-xs font-medium">View</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-6 py-12 text-center text-gray-400">No employees found</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $employees->links() }}
</div>
@endsection
