@extends('hr.layouts.app')
@section('title', 'Leave Requests')
@section('heading', 'Leave Requests')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <form method="GET" class="flex gap-3 flex-wrap">
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]" onchange="this.form.submit()">
            <option value="">All Status</option>
            @foreach(['pending','approved','rejected','cancelled'] as $s)
            <option value="{{ $s }}" {{ request('status')==$s?'selected':'' }}>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <select name="employee_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]" onchange="this.form.submit()">
            <option value="">All Employees</option>
            @foreach($employees as $emp)
            <option value="{{ $emp->id }}" {{ request('employee_id')==$emp->id?'selected':'' }}>{{ $emp->full_name }}</option>
            @endforeach
        </select>
    </form>
    <div class="flex gap-3">
        <a href="{{ route('hr.leaves.calendar') }}" class="border border-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-50 transition-colors">📅 Calendar</a>
        <a href="{{ route('hr.leaves.balances') }}" class="border border-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-50 transition-colors">📊 Balances</a>
        <a href="{{ route('hr.leaves.create') }}" class="bg-[#F7941D] hover:bg-[#E07800] text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">+ New Request</a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Employee</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Leave Type</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Period</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Days</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Decided By</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($requests as $req)
            <tr class="hover:bg-gray-50/50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900">{{ $req->employee?->full_name }}</div>
                    <div class="text-gray-400 text-xs">{{ $req->employee?->department?->name }}</div>
                </td>
                <td class="px-4 py-4 text-gray-700">{{ $req->leaveType?->name }}</td>
                <td class="px-4 py-4 text-gray-600 text-xs">
                    {{ $req->start_date?->format('d M') }} – {{ $req->end_date?->format('d M Y') }}
                </td>
                <td class="px-4 py-4 text-center font-semibold text-gray-700">{{ $req->working_days }}</td>
                <td class="px-4 py-4 text-center">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $req->status_badge_class }}">
                        {{ ucfirst($req->status) }}
                    </span>
                </td>
                <td class="px-4 py-4 text-gray-500 text-xs">
                    {{ $req->decider?->name ?? '—' }}
                    @if($req->decided_at) <div class="text-gray-300">{{ $req->decided_at->format('d M') }}</div> @endif
                </td>
                <td class="px-4 py-4 text-right">
                    @if($req->status === 'pending')
                    <button onclick="openDecide({{ $req->id }}, 'approve')"
                            class="text-xs bg-green-600 text-white px-2.5 py-1 rounded-md hover:bg-green-700 mr-1">✓</button>
                    <button onclick="openDecide({{ $req->id }}, 'reject')"
                            class="text-xs bg-red-500 text-white px-2.5 py-1 rounded-md hover:bg-red-600">✗</button>
                    @endif
                    <a href="{{ route('hr.leaves.show', $req) }}" class="text-xs text-[#1B1444] hover:underline ml-1">View</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-6 py-12 text-center text-gray-400">No leave requests found</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $requests->links() }}</div>

{{-- Decision modal --}}
<div id="decide-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl p-6 max-w-sm w-full">
        <h3 id="decide-title" class="font-bold text-gray-900 mb-4">Approve Request</h3>
        <form id="decide-form" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Note <span id="decide-required-note" class="text-red-500 hidden">*</span></label>
                <textarea name="decision_note" id="decide-note" rows="3"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]"
                          placeholder="Optional note..."></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" id="decide-btn" class="flex-1 py-2.5 rounded-lg text-sm font-semibold text-white bg-green-600 hover:bg-green-700">Approve</button>
                <button type="button" onclick="document.getElementById('decide-modal').classList.add('hidden')"
                        class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm hover:bg-gray-50">Cancel</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openDecide(id, action) {
    const modal = document.getElementById('decide-modal');
    const form  = document.getElementById('decide-form');
    const isApprove = action === 'approve';

    document.getElementById('decide-title').textContent = isApprove ? 'Approve Request' : 'Reject Request';
    document.getElementById('decide-btn').textContent   = isApprove ? 'Approve' : 'Reject';
    document.getElementById('decide-btn').className     = 'flex-1 py-2.5 rounded-lg text-sm font-semibold text-white ' +
        (isApprove ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700');
    document.getElementById('decide-required-note').classList.toggle('hidden', isApprove);

    const base = isApprove
        ? '{{ url('hr/leaves') }}/' + id + '/approve'
        : '{{ url('hr/leaves') }}/' + id + '/reject';
    form.action = base;
    document.getElementById('decide-note').value = '';
    modal.classList.remove('hidden');
}
</script>
@endpush
@endsection
