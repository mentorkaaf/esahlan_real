@extends('hr.layouts.app')
@section('title', 'Import Attendance')
@section('heading', 'Import Attendance — CSV')

@section('content')
<div class="max-w-xl space-y-6">
    <div class="bg-blue-50 border border-blue-200 text-blue-700 text-sm px-4 py-3 rounded-lg">
        <p class="font-semibold mb-1">CSV Format Required</p>
        <p>Required columns (header row): <code class="bg-blue-100 px-1 rounded">employee_no, date, check_in, check_out</code></p>
        <p class="mt-1 text-xs">Date: YYYY-MM-DD &nbsp;|&nbsp; Times: HH:MM (24h, optional)</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="{{ route('hr.attendance.import.submit') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @if(session('error'))<div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">{{ session('error') }}</div>@endif
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">CSV File <span class="text-red-500">*</span></label>
                <input type="file" name="csv" accept=".csv,.txt" required
                       class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#1B1444] file:text-white hover:file:bg-[#2D2467]">
            </div>
            <button type="submit" class="bg-[#F7941D] hover:bg-[#E07800] text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
                Import
            </button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <p class="font-medium text-gray-700 mb-2 text-sm">Example CSV</p>
        <pre class="bg-gray-50 rounded p-3 text-xs text-gray-600 overflow-x-auto">employee_no,date,check_in,check_out
ESH-EMP-0001,2026-08-01,08:05,16:10
ESH-EMP-0002,2026-08-01,09:20,17:00
ESH-EMP-0003,2026-08-01,,</pre>
    </div>
</div>
@endsection
