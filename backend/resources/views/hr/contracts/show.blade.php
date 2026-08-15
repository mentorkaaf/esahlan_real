@extends('hr.layouts.app')
@section('title', 'Contract')
@section('heading', 'Contract Details')
@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 max-w-lg">
    <dl class="space-y-3 text-sm">
        <div class="flex justify-between"><dt class="text-gray-400">Employee</dt><dd>{{ $contract->employee?->full_name }}</dd></div>
        <div class="flex justify-between"><dt class="text-gray-400">Type</dt><dd>{{ $contract->type }}</dd></div>
        <div class="flex justify-between"><dt class="text-gray-400">Period</dt><dd>{{ $contract->start_date?->format('d M Y') }} — {{ $contract->end_date?->format('d M Y') ?? 'Ongoing' }}</dd></div>
        <div class="flex justify-between"><dt class="text-gray-400">Salary</dt><dd>${{ number_format($contract->salary, 2) }}</dd></div>
        <div class="flex justify-between"><dt class="text-gray-400">Status</dt><dd class="capitalize">{{ $contract->status }}</dd></div>
    </dl>
    @if($contract->file_path)
    <div class="mt-4">
        <a href="{{ Storage::url($contract->file_path) }}" target="_blank"
           class="text-xs text-[#1B1444] hover:underline flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Download Contract
        </a>
    </div>
    @endif
    <div class="mt-4">
        <a href="{{ route('hr.employees.show', $contract->employee) }}" class="text-sm text-gray-500 hover:text-gray-700">← Back to Employee</a>
    </div>
</div>
@endsection
