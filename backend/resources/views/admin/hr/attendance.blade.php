@extends('admin.layouts.app')
@section('title', "HR Attendance — $date")

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-gray-800">HR Attendance Overview</h2>
        <form method="GET" class="flex gap-3">
            <input type="date" name="date" value="{{ $date }}"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                   onchange="this.form.submit()">
        </form>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @foreach(['present'=>['green','Present'],'late'=>['yellow','Late'],'absent'=>['red','Absent'],'leave'=>['blue','On Leave']] as $k=>[$c,$l])
        <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
            <div class="w-3 h-3 rounded-full bg-{{ $c }}-400"></div>
            <div>
                <div class="text-2xl font-bold text-gray-900">{{ $summary[$k] }}</div>
                <div class="text-xs text-gray-500">{{ $l }}</div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Absentees --}}
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Unmarked / Absent ({{ $absentees->count() }})</h3>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($absentees as $emp)
                <div class="px-5 py-3 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center text-red-600 text-xs font-bold">{{ $emp->initials }}</div>
                    <div>
                        <div class="font-medium text-sm text-gray-800">{{ $emp->full_name }}</div>
                        <div class="text-xs text-gray-400">{{ $emp->department?->name }}</div>
                    </div>
                </div>
                @empty
                <div class="px-5 py-6 text-center text-gray-400 text-sm">Everyone accounted for ✓</div>
                @endforelse
            </div>
        </div>

        {{-- Today's records --}}
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">All Records for {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</h3>
            </div>
            <div class="divide-y divide-gray-50 max-h-96 overflow-y-auto">
                @forelse($records as $att)
                <div class="px-5 py-3 flex items-center justify-between">
                    <div class="font-medium text-sm text-gray-800">{{ $att->employee?->full_name }}</div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-gray-400">{{ $att->check_in ?? '—' }}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $att->status_badge_class }}">{{ ucfirst($att->status) }}</span>
                    </div>
                </div>
                @empty
                <div class="px-5 py-6 text-center text-gray-400 text-sm">No attendance records for this date</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
