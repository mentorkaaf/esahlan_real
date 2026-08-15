@extends('admin.layouts.app')
@section('title', 'HR Leave Requests')

@section('content')
<div class="p-6">
    <h2 class="text-xl font-bold text-gray-800 mb-6">HR Leave Requests</h2>

    {{-- Pending --}}
    <div class="bg-white rounded-xl border border-gray-200 mb-6">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
            <h3 class="font-semibold text-gray-800">Pending Approval ({{ $pending->count() }})</h3>
            @if($pending->count())<span class="bg-yellow-100 text-yellow-700 text-xs font-bold px-2 py-0.5 rounded-full">{{ $pending->count() }}</span>@endif
        </div>
        <div class="divide-y divide-gray-50">
            @forelse($pending as $req)
            <div class="px-5 py-4 flex items-center justify-between">
                <div>
                    <div class="font-medium text-gray-900 text-sm">{{ $req->employee?->full_name }}</div>
                    <div class="text-xs text-gray-400">{{ $req->leaveType?->name }} · {{ $req->start_date?->format('d M') }} – {{ $req->end_date?->format('d M Y') }} ({{ $req->working_days }}d)</div>
                </div>
                <span class="text-xs text-gray-400">{{ $req->created_at?->diffForHumans() }}</span>
            </div>
            @empty
            <div class="px-5 py-6 text-center text-gray-400 text-sm">No pending requests</div>
            @endforelse
        </div>
    </div>

    {{-- Recent decisions --}}
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Recent Decisions</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Employee</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Period</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Decided By</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Note</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($recent as $req)
                <tr>
                    <td class="px-5 py-3 font-medium text-gray-900">{{ $req->employee?->full_name }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $req->leaveType?->name }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $req->start_date?->format('d M') }} – {{ $req->end_date?->format('d M') }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $req->status_badge_class }}">{{ ucfirst($req->status) }}</span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $req->decider?->name }}</td>
                    <td class="px-4 py-3 text-gray-400 text-xs max-w-xs truncate">{{ $req->decision_note ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-gray-400">No decisions yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
