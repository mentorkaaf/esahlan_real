@extends('hr.layouts.app')
@section('title', 'Leave Request')
@section('heading', 'Leave Request — ' . $leave->employee?->full_name)

@section('content')
<div class="max-w-xl space-y-5">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-gray-400">Employee</dt><dd class="font-medium">{{ $leave->employee?->full_name }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-400">Leave Type</dt><dd>{{ $leave->leaveType?->name }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-400">Period</dt><dd>{{ $leave->start_date?->format('d M Y') }} – {{ $leave->end_date?->format('d M Y') }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-400">Working Days</dt><dd class="font-bold">{{ $leave->working_days }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-400">Status</dt>
                <dd><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $leave->status_badge_class }}">{{ ucfirst($leave->status) }}</span></dd></div>
            @if($leave->reason)
            <div><dt class="text-gray-400 mb-1">Reason</dt><dd class="text-gray-700">{{ $leave->reason }}</dd></div>
            @endif
            @if($leave->decision_note)
            <div><dt class="text-gray-400 mb-1">Decision Note</dt><dd class="text-gray-700">{{ $leave->decision_note }}</dd></div>
            @endif
            @if($leave->decider)
            <div class="flex justify-between"><dt class="text-gray-400">Decided By</dt><dd>{{ $leave->decider?->name }} · {{ $leave->decided_at?->format('d M Y') }}</dd></div>
            @endif
        </dl>

        @if($leave->status === 'pending')
        <div class="mt-5 flex gap-3">
            <form method="POST" action="{{ route('hr.leaves.approve', $leave) }}" class="flex-1">
                @csrf
                <button type="submit" class="w-full bg-green-600 text-white py-2.5 rounded-lg text-sm font-semibold hover:bg-green-700">Approve</button>
            </form>
            <button onclick="document.getElementById('reject-modal').classList.remove('hidden')"
                    class="flex-1 bg-red-50 text-red-600 py-2.5 rounded-lg text-sm font-semibold hover:bg-red-100">Reject</button>
        </div>
        @endif
    </div>

    <a href="{{ route('hr.leaves.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back to list</a>
</div>

<div id="reject-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl p-6 max-w-sm w-full">
        <h3 class="font-bold text-gray-900 mb-3">Reject Leave Request</h3>
        <form method="POST" action="{{ route('hr.leaves.reject', $leave) }}">
            @csrf
            <textarea name="decision_note" required rows="3" placeholder="Reason for rejection (required)"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 mb-4"></textarea>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-red-600 text-white py-2.5 rounded-lg text-sm font-semibold hover:bg-red-700">Reject</button>
                <button type="button" onclick="document.getElementById('reject-modal').classList.add('hidden')"
                        class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm hover:bg-gray-50">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection
