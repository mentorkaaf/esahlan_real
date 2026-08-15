@extends('hr.layouts.app')
@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
{{-- Stats cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    @php
    $cards = [
        ['label' => 'Active Employees',     'value' => $stats['total_employees'],     'color' => 'bg-blue-500',   'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ['label' => 'On Probation',         'value' => $stats['on_probation'],        'color' => 'bg-yellow-500', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Departments',          'value' => $stats['departments'],         'color' => 'bg-navy-500',   'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
        ['label' => 'Expiring Contracts',   'value' => $stats['expiring_contracts'],  'color' => 'bg-red-500',    'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    ];
    @endphp

    @foreach($cards as $card)
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-3">
            <div class="{{ $card['color'] }} w-10 h-10 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $card['icon'] }}"/>
                </svg>
            </div>
        </div>
        <div class="text-3xl font-bold text-gray-900">{{ $card['value'] }}</div>
        <div class="text-sm text-gray-500 mt-1">{{ $card['label'] }}</div>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Recent hires --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Recent Hires</h2>
        </div>
        <div class="divide-y divide-gray-50">
            @forelse($newHires as $emp)
            <div class="px-6 py-4 flex items-center gap-4">
                <div class="w-9 h-9 rounded-full bg-[#1B1444] flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                    {{ $emp->initials }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-sm text-gray-900 truncate">{{ $emp->full_name }}</div>
                    <div class="text-xs text-gray-400">{{ $emp->position?->title }} · {{ $emp->department?->name }}</div>
                </div>
                <div class="text-xs text-gray-400 flex-shrink-0">{{ $emp->hire_date?->format('d M Y') }}</div>
            </div>
            @empty
            <div class="px-6 py-8 text-center text-gray-400 text-sm">No employees yet</div>
            @endforelse
        </div>
    </div>

    {{-- Audit log --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-800">Recent Activity</h2>
            @if(Auth::guard('hr')->user()->isManager())
            <a href="{{ route('hr.audit.index') }}" class="text-xs text-[#1B1444] hover:underline">View all</a>
            @endif
        </div>
        <div class="divide-y divide-gray-50">
            @forelse($recentAudit as $log)
            <div class="px-6 py-3 flex items-start gap-3">
                <div class="w-1.5 h-1.5 rounded-full bg-[#F7941D] mt-2 flex-shrink-0"></div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm text-gray-700">
                        <span class="font-medium">{{ $log->actor_name }}</span>
                        <span class="text-gray-400 mx-1">·</span>
                        <span class="text-gray-500">{{ $log->action_label }}</span>
                    </div>
                    <div class="text-xs text-gray-400">{{ $log->created_at?->diffForHumans() }}</div>
                </div>
            </div>
            @empty
            <div class="px-6 py-8 text-center text-gray-400 text-sm">No activity yet</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
