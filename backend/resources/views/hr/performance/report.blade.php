@extends('hr.layouts.app')
@section('title', 'Report — ' . $cycle->name)
@section('heading', $cycle->name . ' — Performance Report')

@section('content')
<div class="space-y-6">

    <div class="flex items-center justify-between">
        <a href="{{ route('hr.performance.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Cycles</a>
        <span class="text-xs text-gray-400">{{ $ranking->count() }} submitted reviews</span>
    </div>

    {{-- Department averages --}}
    @if($deptAverages->count())
    <div class="grid grid-cols-3 gap-4">
        @foreach($deptAverages as $dept => $avg)
        <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
            <div class="text-xs text-gray-400 mb-1">{{ $dept }}</div>
            <div class="text-2xl font-bold font-tabular {{ $avg >= 4 ? 'text-green-600' : ($avg >= 3 ? 'text-yellow-600' : 'text-orange-600') }}">
                {{ number_format($avg, 2) }}
            </div>
            <div class="text-xs text-gray-400 mt-0.5">avg score</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Ranking table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Employee Ranking</h2>
        </div>
        @if($ranking->isEmpty())
        <div class="py-16 text-center text-gray-400 text-sm">No submitted reviews for this cycle.</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                <tr>
                    <th class="px-6 py-3 text-center w-12">#</th>
                    <th class="px-6 py-3 text-left">Employee</th>
                    <th class="px-6 py-3 text-left">Department</th>
                    <th class="px-6 py-3 text-center">Score</th>
                    <th class="px-6 py-3 text-center">Bar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($ranking as $idx => $review)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-center font-bold {{ $idx === 0 ? 'text-yellow-500 text-lg' : 'text-gray-400' }}">
                        {{ $idx + 1 }}
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-navy flex items-center justify-center text-white text-xs font-bold">
                                {{ $review->employee->initials }}
                            </div>
                            <div class="font-medium text-gray-900">{{ $review->employee->full_name }}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-500">{{ $review->employee->department?->name ?? '—' }}</td>
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-bold font-tabular {{ $review->getScoreBadgeClass() }}">
                            {{ number_format($review->overall_score, 2) }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full {{ $review->overall_score >= 4 ? 'bg-green-400' : ($review->overall_score >= 3 ? 'bg-yellow-400' : 'bg-orange-400') }}"
                                 style="width: {{ ($review->overall_score / 5) * 100 }}%"></div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

</div>
@endsection
