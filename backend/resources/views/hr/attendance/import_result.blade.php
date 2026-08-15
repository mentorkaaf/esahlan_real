@extends('hr.layouts.app')
@section('title', 'Import Result')
@section('heading', 'Import Result')

@section('content')
<div class="max-w-2xl space-y-5">
    <div class="bg-green-50 border border-green-200 text-green-700 px-5 py-4 rounded-xl">
        <div class="font-semibold text-lg">{{ $imported }} rows imported successfully</div>
    </div>

    @if(count($errors))
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-800 mb-3">{{ count($errors) }} row(s) with errors</h3>
        <ul class="space-y-1.5">
            @foreach($errors as $e)
            <li class="flex items-start gap-2 text-sm text-red-600">
                <span class="mt-0.5">⚠</span>
                <span>{{ $e }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

    <a href="{{ route('hr.attendance.daily') }}"
       class="inline-block bg-[#1B1444] text-white px-5 py-2.5 rounded-lg text-sm hover:bg-[#2D2467] transition-colors">
        ← Back to Daily Sheet
    </a>
</div>
@endsection
