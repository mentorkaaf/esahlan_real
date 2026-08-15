@extends('hr.layouts.app')
@section('title', 'Monthly Attendance — ' . $employee->full_name)
@section('heading', 'Monthly Attendance — ' . $employee->full_name)

@section('content')
<div class="flex items-center justify-between mb-6">
    <form method="GET" class="flex gap-3 items-center">
        <input type="month" name="month" value="{{ $month }}"
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]"
               onchange="this.form.submit()">
    </form>
    <a href="{{ route('hr.attendance.daily', ['date' => now()->format('Y-m-d')]) }}"
       class="text-sm text-[#1B1444] hover:underline">← Back to daily sheet</a>
</div>

{{-- Legend --}}
<div class="flex flex-wrap gap-3 mb-5 text-xs">
    @foreach(['present'=>['green','Present'],'late'=>['yellow','Late'],'absent'=>['red','Absent'],'leave'=>['blue','Leave'],'holiday'=>['purple','Holiday'],'weekend'=>['gray','Weekend']] as $s=>[$c,$l])
    <div class="flex items-center gap-1.5">
        <div class="w-3 h-3 rounded bg-{{ $c }}-400"></div>
        <span class="text-gray-500">{{ $l }}</span>
    </div>
    @endforeach
</div>

{{-- Calendar heat grid --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="grid grid-cols-7 gap-1 mb-2 text-center">
        @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow)
        <div class="text-xs font-semibold text-gray-400 py-1">{{ $dow }}</div>
        @endforeach
    </div>

    {{-- Blank cells for first day offset --}}
    <div class="grid grid-cols-7 gap-1" id="cal-grid">
        @php $firstDow = $start->dayOfWeek; @endphp
        @for($i = 0; $i < $firstDow; $i++)
        <div></div>
        @endfor

        @foreach($days as $dateStr => $att)
        @php
            $d = \Carbon\Carbon::parse($dateStr);
            $color = match($att?->status ?? ($d->isWeekend() ? 'weekend' : 'absent')) {
                'present'  => 'bg-green-100 border-green-300 text-green-700',
                'late'     => 'bg-yellow-100 border-yellow-300 text-yellow-700',
                'absent'   => 'bg-red-100 border-red-300 text-red-700',
                'half_day' => 'bg-orange-100 border-orange-300 text-orange-700',
                'leave'    => 'bg-blue-100 border-blue-300 text-blue-700',
                'holiday'  => 'bg-purple-100 border-purple-300 text-purple-700',
                'weekend'  => 'bg-gray-50 border-gray-200 text-gray-400',
                default    => 'bg-gray-50 border-gray-200 text-gray-400',
            };
            $label = match($att?->status ?? ($d->isWeekend() ? 'weekend' : '')) {
                'present'  => 'P',
                'late'     => 'L',
                'absent'   => 'A',
                'half_day' => '½',
                'leave'    => '🏖',
                'holiday'  => '★',
                'weekend'  => '',
                default    => '',
            };
        @endphp
        <div title="{{ $dateStr }}{{ $att ? ' · ' . ucfirst($att->status) . ($att->late_minutes ? ' (' . $att->late_minutes . 'm late)' : '') : '' }}"
             class="aspect-square border rounded-lg flex flex-col items-center justify-center {{ $color }} cursor-default transition-opacity hover:opacity-80">
            <div class="text-xs font-bold leading-tight">{{ $d->day }}</div>
            <div class="text-xs mt-0.5">{{ $label }}</div>
        </div>
        @endforeach
    </div>
</div>

{{-- Monthly summary --}}
@php
    $attCol = collect(array_values($days))->filter();
    $stats = [
        'Present'  => $attCol->where('status','present')->count(),
        'Late'     => $attCol->where('status','late')->count(),
        'Absent'   => $attCol->where('status','absent')->count(),
        'On Leave' => $attCol->where('status','leave')->count(),
        'Overtime (min)' => $attCol->sum('overtime_minutes'),
    ];
@endphp
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mt-5">
    @foreach($stats as $label => $val)
    <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
        <div class="text-2xl font-bold text-gray-900">{{ $val }}</div>
        <div class="text-xs text-gray-500 mt-1">{{ $label }}</div>
    </div>
    @endforeach
</div>
@endsection
