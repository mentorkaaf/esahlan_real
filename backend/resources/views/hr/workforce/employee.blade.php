@extends('hr.layouts.app')
@section('title', $employee->full_name . ' — Workforce')
@section('heading', $employee->full_name . ' · Module Assignments')

@section('content')
<div class="max-w-2xl space-y-6">

    <a href="{{ route('hr.employees.show', $employee) }}"
       class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-gray-600">
        ← Back to employee
    </a>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-sm uppercase tracking-wider">Assignment History</h3>
            <a href="{{ route('hr.workforce.create', ['employee_id' => $employee->id]) }}"
               class="text-xs bg-[#F7941D] text-white px-3 py-1.5 rounded-lg hover:bg-[#E07800] transition-colors">
                + Assign to Module
            </a>
        </div>

        @forelse($assignments as $a)
        <div class="px-6 py-4 border-b border-gray-50 last:border-0 flex items-center gap-4">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background-color: {{ $a->module?->color ?? '#1B1444' }}20;">
                <i class="{{ $a->module?->icon ?? 'fas fa-cube' }}"
                   style="color: {{ $a->module?->color ?? '#1B1444' }};"></i>
            </div>
            <div class="flex-1">
                <div class="font-medium text-sm text-gray-800">{{ $a->module?->name }}</div>
                <div class="text-xs text-gray-400">
                    {{ ucfirst($a->role_in_module ?? 'staff') }}
                    · Assigned {{ $a->assigned_at->format('d M Y') }}
                    @if($a->ended_at) — Ended {{ $a->ended_at->format('d M Y') }} @endif
                </div>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                {{ $a->status === 'active' ? 'bg-green-100 text-green-700' :
                   ($a->status === 'suspended' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500') }}">
                {{ ucfirst($a->status) }}
            </span>
        </div>
        @empty
        <div class="px-6 py-12 text-center text-gray-400 text-sm">
            No module assignments yet.
        </div>
        @endforelse
    </div>

</div>
@endsection
