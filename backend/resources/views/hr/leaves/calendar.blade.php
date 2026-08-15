@extends('hr.layouts.app')
@section('title', 'Leave Calendar')
@section('heading', 'Leave Calendar — ' . \Carbon\Carbon::parse($month)->format('F Y'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <form method="GET" class="flex gap-3 items-center">
        <input type="month" name="month" value="{{ $month }}"
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]"
               onchange="this.form.submit()">
    </form>
    <a href="{{ route('hr.leaves.index') }}" class="text-sm text-[#1B1444] hover:underline">← All Requests</a>
</div>

@php
    $daysInMonth = $start->daysInMonth;
    $firstDow    = $start->dayOfWeek;
    // Group leaves by date
    $byDate = [];
    foreach ($leaves as $leave) {
        $cur = $leave->start_date->copy();
        while ($cur->lte($leave->end_date)) {
            if ($cur->month == $start->month && $cur->year == $start->year) {
                $byDate[$cur->format('Y-m-d')][] = $leave;
            }
            $cur->addDay();
        }
    }
@endphp

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="grid grid-cols-7 gap-2 mb-2 text-center">
        @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow)
        <div class="text-xs font-semibold text-gray-400">{{ $dow }}</div>
        @endforeach
    </div>
    <div class="grid grid-cols-7 gap-2">
        @for($i = 0; $i < $firstDow; $i++)<div></div>@endfor

        @for($day = 1; $day <= $daysInMonth; $day++)
        @php
            $dateStr = $start->format('Y-m') . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
            $dayLeaves = $byDate[$dateStr] ?? [];
            $d = \Carbon\Carbon::parse($dateStr);
            $isWeekend = $d->isWeekend();
        @endphp
        <div class="min-h-[80px] border rounded-lg p-1.5 {{ $isWeekend ? 'bg-gray-50' : 'bg-white' }} {{ $dateStr === today()->format('Y-m-d') ? 'ring-2 ring-[#F7941D]' : 'border-gray-100' }}">
            <div class="text-xs font-semibold text-gray-{{ $isWeekend ? '300' : '600' }} mb-1">{{ $day }}</div>
            @foreach($dayLeaves as $lv)
            <div class="text-xs bg-blue-100 text-blue-700 rounded px-1 py-0.5 mb-0.5 truncate" title="{{ $lv->employee?->full_name }} · {{ $lv->leaveType?->name }}">
                {{ $lv->employee?->first_name }}
            </div>
            @endforeach
        </div>
        @endfor
    </div>
</div>

@if($leaves->count())
<div class="mt-5 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
    <h3 class="font-semibold text-gray-800 mb-3 text-sm">Employees on Leave This Month</h3>
    <div class="space-y-2">
        @foreach($leaves as $lv)
        <div class="flex items-center justify-between py-2 border-b border-gray-50 text-sm">
            <div class="font-medium text-gray-800">{{ $lv->employee?->full_name }}</div>
            <div class="text-gray-500">{{ $lv->leaveType?->name }}</div>
            <div class="text-gray-400 text-xs">{{ $lv->start_date?->format('d M') }} – {{ $lv->end_date?->format('d M') }} ({{ $lv->working_days }}d)</div>
        </div>
        @endforeach
    </div>
</div>
@endif
@endsection
