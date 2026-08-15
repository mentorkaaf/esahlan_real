@extends('hr.layouts.app')
@section('title', 'Performance Cycles')
@section('heading', 'Performance Cycles')

@section('content')
<div class="space-y-6">

    {{-- New cycle form --}}
    @if(Auth::guard('hr')->user()->isManager())
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Create New Cycle</h2>
        <form method="POST" action="{{ route('hr.performance.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs text-gray-500 mb-1">Name *</label>
                <input type="text" name="name" placeholder="Q2 2026 Review" required
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand w-48">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Period Start *</label>
                <input type="date" name="period_start" required
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Period End *</label>
                <input type="date" name="period_end" required
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Status *</label>
                <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                    <option value="draft">Draft</option>
                    <option value="active">Active</option>
                </select>
            </div>
            <button type="submit" class="bg-brand text-white text-sm px-4 py-2 rounded-lg hover:bg-brand-600 transition-colors">Create</button>
        </form>
    </div>
    @endif

    {{-- Cycles list --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">All Cycles</h2>
        </div>
        @if($cycles->isEmpty())
        <div class="py-16 text-center text-gray-400 text-sm">No performance cycles yet.</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                <tr>
                    <th class="px-6 py-3 text-left">Name</th>
                    <th class="px-6 py-3 text-left">Period</th>
                    <th class="px-6 py-3 text-center">Goals</th>
                    <th class="px-6 py-3 text-center">Reviews</th>
                    <th class="px-6 py-3 text-left">Status</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($cycles as $cycle)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 font-medium text-gray-900">{{ $cycle->name }}</td>
                    <td class="px-6 py-4 text-gray-500 text-xs">
                        {{ $cycle->period_start->format('d M Y') }} – {{ $cycle->period_end->format('d M Y') }}
                    </td>
                    <td class="px-6 py-4 text-center font-tabular">{{ $cycle->goals_count }}</td>
                    <td class="px-6 py-4 text-center font-tabular">{{ $cycle->reviews_count }}</td>
                    <td class="px-6 py-4">
                        @if(Auth::guard('hr')->user()->isManager())
                        <form method="POST" action="{{ route('hr.performance.update', $cycle) }}" class="inline">
                            @csrf @method('PATCH')
                            <select name="status" onchange="this.form.submit()"
                                    class="border border-gray-200 rounded-lg px-2 py-1 text-xs focus:outline-none {{ $cycle->getStatusBadgeClass() }}">
                                @foreach(['draft','active','closed'] as $s)
                                <option value="{{ $s }}" {{ $cycle->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                                @endforeach
                            </select>
                        </form>
                        @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $cycle->getStatusBadgeClass() }}">{{ ucfirst($cycle->status) }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right space-x-3">
                        <a href="{{ route('hr.performance.goals', $cycle) }}" class="text-xs text-gray-600 hover:text-navy font-medium">Goals</a>
                        <a href="{{ route('hr.performance.reviews', $cycle) }}" class="text-xs text-navy hover:text-brand font-medium">Reviews</a>
                        @if($cycle->status === 'closed')
                        <a href="{{ route('hr.performance.report', $cycle) }}" class="text-xs text-brand hover:text-brand-600 font-medium">Report</a>
                        @endif
                        @if(Auth::guard('hr')->user()->isManager())
                        <form method="POST" action="{{ route('hr.performance.destroy', $cycle) }}" class="inline">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('Delete cycle?')" class="text-xs text-red-300 hover:text-red-500">Delete</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

</div>
@endsection
