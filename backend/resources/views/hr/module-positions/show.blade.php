@extends('hr.layouts.app')
@section('title', $modulePosition->name)
@section('heading', $modulePosition->name)

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <div class="flex items-start gap-5">
            <div class="w-14 h-14 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background-color: {{ $modulePosition->module?->color ?? '#1B1444' }}20;">
                <i class="{{ $modulePosition->module?->icon ?? 'fas fa-id-badge' }} text-2xl"
                   style="color: {{ $modulePosition->module?->color ?? '#1B1444' }};"></i>
            </div>
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <span class="text-xs text-gray-400 uppercase tracking-wider font-medium">
                        {{ $modulePosition->module?->name }}
                        @if($modulePosition->moduleDepartment) · {{ $modulePosition->moduleDepartment->name }} @endif
                    </span>
                    @php
                        $lc = ['junior'=>'blue','mid'=>'indigo','senior'=>'purple','lead'=>'orange','manager'=>'red'][$modulePosition->level] ?? 'gray';
                    @endphp
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $lc }}-100 text-{{ $lc }}-700">
                        {{ ucfirst($modulePosition->level) }}
                    </span>
                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium
                        {{ $modulePosition->status === 'active' ? 'bg-green-100 text-green-700' :
                           ($modulePosition->status === 'inactive' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500') }}">
                        {{ $modulePosition->status_label }}
                    </span>
                    @if($modulePosition->hrPosition)
                    <span class="inline-flex items-center gap-1 text-xs text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">
                        <i class="fas fa-link text-xs"></i>
                        HR: {{ $modulePosition->hrPosition->title }}
                    </span>
                    @endif
                </div>
                <h2 class="text-xl font-bold text-gray-900">{{ $modulePosition->name }}</h2>
                @if($modulePosition->description)
                <p class="text-sm text-gray-500 mt-1">{{ $modulePosition->description }}</p>
                @endif
            </div>
            <div class="flex gap-2 flex-shrink-0">
                <a href="{{ route('hr.module-positions.edit', $modulePosition) }}"
                   class="bg-[#1B1444] text-white text-xs px-4 py-2 rounded-lg hover:bg-[#2D2467] transition-colors">Edit</a>
                <a href="{{ route('hr.module-positions.index') }}"
                   class="text-xs text-gray-400 hover:text-gray-600 px-3 py-2">← All Positions</a>
            </div>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-3 gap-4 mt-5 pt-5 border-t border-gray-100">
            <div>
                <div class="text-2xl font-bold text-gray-800">{{ $assignments->where('status','active')->count() }}</div>
                <div class="text-xs text-gray-400 mt-0.5">Currently filled</div>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-800">{{ $assignments->count() }}</div>
                <div class="text-xs text-gray-400 mt-0.5">Total assignments (all time)</div>
            </div>
            <div>
                <div class="text-sm font-semibold text-gray-700">
                    {{ $modulePosition->hrPosition ? 'Linked (' . $modulePosition->hrPosition->title . ')' : 'Standalone' }}
                </div>
                <div class="text-xs text-gray-400 mt-0.5">HR position link</div>
            </div>
        </div>
    </div>

    {{-- Employees holding this position --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 text-sm uppercase tracking-wider">Employees in this Position</h3>
            <span class="text-xs text-gray-400">{{ $assignments->where('status','active')->count() }} active</span>
        </div>

        @forelse($assignments as $a)
        <div class="px-6 py-3.5 border-b border-gray-50 last:border-0 flex items-center gap-4">
            <div class="w-9 h-9 rounded-xl bg-[#1B1444] flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                {{ $a->employee?->initials }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-medium text-sm text-gray-800">{{ $a->employee?->full_name }}</div>
                <div class="text-xs text-gray-400">
                    {{ $a->employee?->department?->name }}
                    @if($a->moduleDepartment) · {{ $a->moduleDepartment->name }} @endif
                    · Since {{ $a->assigned_at->format('d M Y') }}
                </div>
            </div>
            <span class="text-xs font-medium
                {{ $a->status === 'active' ? 'text-green-600' : ($a->status === 'suspended' ? 'text-yellow-600' : 'text-gray-400') }}">
                {{ ucfirst($a->status) }}
            </span>
        </div>
        @empty
        <div class="px-6 py-10 text-center text-gray-400 text-sm">
            No employees currently hold this position.
        </div>
        @endforelse
    </div>

    {{-- Status controls --}}
    @if(Auth::guard('hr')->user()->isManager() && !$modulePosition->isArchived())
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 max-w-sm">
        <h3 class="font-semibold text-gray-800 text-sm uppercase tracking-wider mb-3">Status</h3>
        <div class="flex gap-3">
            @if($modulePosition->isActive())
            <form method="POST" action="{{ route('hr.module-positions.status', $modulePosition) }}">
                @csrf <input type="hidden" name="status" value="inactive">
                <button class="text-sm border border-yellow-300 text-yellow-700 px-4 py-2 rounded-lg hover:bg-yellow-50 transition-colors">
                    Deactivate
                </button>
            </form>
            @else
            <form method="POST" action="{{ route('hr.module-positions.status', $modulePosition) }}">
                @csrf <input type="hidden" name="status" value="active">
                <button class="text-sm border border-green-300 text-green-700 px-4 py-2 rounded-lg hover:bg-green-50 transition-colors">
                    Activate
                </button>
            </form>
            @endif
            <form method="POST" action="{{ route('hr.module-positions.status', $modulePosition) }}"
                  onsubmit="return confirm('Archive this position?')">
                @csrf <input type="hidden" name="status" value="archived">
                <button class="text-sm border border-red-200 text-red-500 px-4 py-2 rounded-lg hover:bg-red-50 transition-colors">
                    Archive
                </button>
            </form>
        </div>
    </div>
    @endif

</div>
@endsection
