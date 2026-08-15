@extends('hr.layouts.app')
@section('title', 'Commissions')
@section('heading', 'Commissions')

@section('content')
<div class="space-y-6">

    {{-- Period filter + add --}}
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <form method="GET" class="flex items-center gap-2">
            <label class="text-sm text-gray-600">Period:</label>
            <input type="month" name="period" value="{{ $period }}"
                   class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
            <button type="submit"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm px-3 py-1.5 rounded-lg transition-colors">
                Filter
            </button>
        </form>
        <a href="{{ route('hr.commissions.create') }}"
           class="inline-flex items-center gap-2 bg-brand hover:bg-brand-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Record Commission
        </a>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">{{ $period }}</h2>
        </div>

        @if($commissions->isEmpty())
        <div class="py-16 text-center text-gray-400 text-sm">No commissions for {{ $period }}.</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-6 py-3 text-left">Employee</th>
                        <th class="px-6 py-3 text-left">Type</th>
                        <th class="px-6 py-3 text-right">Target</th>
                        <th class="px-6 py-3 text-right">Achieved</th>
                        <th class="px-6 py-3 text-right">Rate</th>
                        <th class="px-6 py-3 text-right">Amount</th>
                        <th class="px-6 py-3 text-left">Status</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($commissions as $c)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ $c->employee->full_name }}</div>
                            <div class="text-xs text-gray-400">{{ $c->employee->department?->name ?? '' }}</div>
                        </td>
                        <td class="px-6 py-4 text-gray-600">{{ $c->type_label }}</td>
                        <td class="px-6 py-4 text-right font-tabular">{{ $c->target }}</td>
                        <td class="px-6 py-4 text-right font-tabular">{{ $c->achieved }}</td>
                        <td class="px-6 py-4 text-right font-tabular">${{ number_format($c->rate, 2) }}</td>
                        <td class="px-6 py-4 text-right font-tabular font-semibold">${{ number_format($c->amount, 2) }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $c->status_badge_class }}">
                                {{ ucfirst($c->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            @if($c->status === 'pending' && Auth::guard('hr')->user()->isManager())
                            <form method="POST" action="{{ route('hr.commissions.approve', $c) }}" class="inline">
                                @csrf
                                <button class="text-green-600 hover:text-green-800 text-xs font-medium">Approve</button>
                            </form>
                            <button onclick="showRejectModal({{ $c->id }})" class="text-red-400 hover:text-red-600 text-xs font-medium">Reject</button>
                            @endif
                            @if(!in_array($c->status, ['included']) && Auth::guard('hr')->user()->isManager())
                            <form method="POST" action="{{ route('hr.commissions.destroy', $c) }}" class="inline">
                                @csrf @method('DELETE')
                                <button onclick="return confirm('Delete?')" class="text-gray-300 hover:text-red-400 text-xs">✕</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>

{{-- Reject Modal --}}
<div id="rejectModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Reject Commission</h3>
        <form method="POST" id="rejectForm">
            @csrf
            <div class="mb-4">
                <label class="block text-sm text-gray-700 mb-1">Reason *</label>
                <textarea name="note" rows="3" required
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 focus:border-red-400"></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="bg-red-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">Reject</button>
                <button type="button" onclick="closeRejectModal()" class="text-gray-500 text-sm px-4 py-2">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function showRejectModal(id) {
    document.getElementById('rejectForm').action = '/hr/commissions/' + id + '/reject';
    document.getElementById('rejectModal').classList.remove('hidden');
}
function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}
</script>
@endpush
