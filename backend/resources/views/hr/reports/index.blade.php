@extends('hr.layouts.app')
@section('title', 'HR Reports')

@section('content')
<h1 class="text-2xl font-bold text-gray-900 mb-6">HR Reports</h1>

{{-- Report selector --}}
<div class="flex gap-2 mb-6 flex-wrap">
    @php
    $reports = [
        'headcount'   => ['📊','Headcount'],
        'attendance'  => ['📅','Attendance'],
        'leave'       => ['🌴','Leave Utilization'],
        'payroll'     => ['💰','Payroll Trend'],
        'turnover'    => ['🔄','Turnover'],
        'recruitment' => ['🎯','Recruitment Funnel'],
    ];
    @endphp
    @foreach($reports as $key => [$icon,$label])
        <a href="{{ route('hr.reports.index', ['report' => $key]) }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition
                  {{ $report === $key ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' }}">
            {{ $icon }} {{ $label }}
        </a>
    @endforeach
</div>

{{-- Report content --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-center justify-between mb-5">
        <h2 class="text-lg font-semibold text-gray-900">
            {{ $reports[$report][0] }} {{ $reports[$report][1] }}
        </h2>
        <a href="{{ request()->fullUrlWithQuery(['csv' => '1']) }}"
           class="text-sm text-blue-600 hover:underline">⬇ Export CSV</a>
    </div>

    @if($report === 'headcount')
        @include('hr.reports._headcount', $data)
    @elseif($report === 'attendance')
        @include('hr.reports._attendance', $data)
    @elseif($report === 'leave')
        @include('hr.reports._leave', $data)
    @elseif($report === 'payroll')
        @include('hr.reports._payroll', $data)
    @elseif($report === 'turnover')
        @include('hr.reports._turnover', $data)
    @elseif($report === 'recruitment')
        @include('hr.reports._recruitment', $data)
    @endif
</div>
@endsection
