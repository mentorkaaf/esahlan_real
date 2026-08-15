@extends('admin.layouts.app')
@section('title', 'HR Discipline Overview')

@section('content')
<h1 class="text-2xl font-bold text-gray-900 mb-6">Disciplinary Cases</h1>

{{-- KPI strip --}}
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-red-50 border border-red-100 rounded-xl p-4 text-center">
        <p class="text-3xl font-bold text-red-700">{{ $stats['open'] }}</p>
        <p class="text-sm text-red-600 mt-1">Open</p>
    </div>
    <div class="bg-yellow-50 border border-yellow-100 rounded-xl p-4 text-center">
        <p class="text-3xl font-bold text-yellow-700">{{ $stats['investigating'] }}</p>
        <p class="text-sm text-yellow-600 mt-1">Under Investigation</p>
    </div>
    <div class="bg-gray-50 border border-gray-100 rounded-xl p-4 text-center">
        <p class="text-3xl font-bold text-gray-700">{{ $stats['closed'] }}</p>
        <p class="text-sm text-gray-500 mt-1">Closed</p>
    </div>
</div>

{{-- Filter --}}
<form method="GET" class="flex gap-3 mb-5">
    <select name="status" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
        <option value="">All statuses</option>
        @foreach(['open','investigating','closed'] as $s)
            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    <button type="submit" class="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm">Filter</button>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Employee</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Title</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Severity</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Opened</th>
                @if(Auth::user()?->role === 'super_admin')
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Actions</th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
        @forelse($cases as $case)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium">
                    {{ $case->employee->full_name }}
                    <div class="text-xs text-gray-400">{{ $case->employee->department?->name }}</div>
                </td>
                <td class="px-4 py-3 text-gray-700">{{ $case->title }}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $case->getSeverityBadgeClass() }}">{{ ucfirst($case->severity) }}</span>
                </td>
                <td class="px-4 py-3">
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $case->getStatusBadgeClass() }}">{{ ucfirst($case->status) }}</span>
                </td>
                <td class="px-4 py-3 text-gray-500">{{ $case->created_at->format('d M Y') }}</td>
                @if(Auth::user()?->role === 'super_admin')
                <td class="px-4 py-3">
                    @if($case->status !== 'closed')
                    <button onclick="openForceClose({{ $case->id }})"
                            class="text-xs bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1 rounded-lg transition">
                        Force Close
                    </button>
                    @endif
                </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No cases found.</td></tr>
        @endforelse
        </tbody>
    </table>
    @if($cases->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $cases->links() }}</div>
    @endif
</div>

{{-- Force-close modal --}}
@if(Auth::user()?->role === 'super_admin')
<div id="forceCloseModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl">
        <h3 class="text-lg font-bold text-gray-900 mb-4">⚠ Admin: Force-Close Case</h3>
        <form id="forceCloseForm" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Outcome</label>
                    <select name="outcome" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach(['verbal_warning'=>'Verbal Warning','written_warning'=>'Written Warning','suspension'=>'Suspension','termination'=>'Termination','dismissed'=>'Case Dismissed'] as $val=>$label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason (required)</label>
                    <textarea name="reason" required rows="3" maxlength="500"
                              placeholder="State reason for admin override…"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                </div>
            </div>
            <div class="flex gap-3 mt-5">
                <button type="submit" class="bg-red-600 text-white px-5 py-2 rounded-lg text-sm font-medium">Force Close</button>
                <button type="button" onclick="closeModal()" class="px-5 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script>
function openForceClose(caseId) {
    document.getElementById('forceCloseForm').action = '/admin/hr/discipline/' + caseId + '/force-close';
    document.getElementById('forceCloseModal').classList.remove('hidden');
}
function closeModal() {
    document.getElementById('forceCloseModal').classList.add('hidden');
}
</script>
@endif
@endsection
