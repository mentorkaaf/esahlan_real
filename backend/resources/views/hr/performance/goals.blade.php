@extends('hr.layouts.app')
@section('title', 'Goals — ' . $cycle->name)
@section('heading', $cycle->name . ' — Goals')

@section('content')
<div class="space-y-6">

    <div class="flex items-center justify-between">
        <a href="{{ route('hr.performance.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Cycles</a>
        <div class="flex gap-2">
            @if($cycle->status === 'active' && Auth::guard('hr')->user()->isManager())
            <form method="POST" action="{{ route('hr.performance.import_commissions', $cycle) }}">
                @csrf
                <button class="inline-flex items-center gap-1 text-xs text-purple-700 bg-purple-50 hover:bg-purple-100 px-3 py-2 rounded-lg border border-purple-200 transition-colors">
                    ↓ Import from Commissions
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- Add goal form --}}
    @if(Auth::guard('hr')->user()->isManager() && $cycle->status !== 'closed')
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Add Goal</h3>
        <form method="POST" action="{{ route('hr.performance.goals.store', $cycle) }}" class="flex flex-wrap gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs text-gray-500 mb-1">Employee *</label>
                <select name="employee_id" required
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 w-48">
                    <option value="">— Select —</option>
                    @foreach($employees as $emp)
                    <option value="{{ $emp->id }}">{{ $emp->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Goal Title *</label>
                <input type="text" name="title" required placeholder="e.g. Achieve 50 customer sign-ups"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 w-64">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Weight (%) *</label>
                <input type="number" name="weight" required min="0" max="100" step="0.01" placeholder="30"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 w-24">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Target</label>
                <input type="text" name="target_value" placeholder="e.g. 50"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 w-24">
            </div>
            <button type="submit" class="bg-brand text-white text-sm px-4 py-2 rounded-lg hover:bg-brand-600 transition-colors">
                Add Goal
            </button>
        </form>
        @error('error')<p class="mt-2 text-xs text-red-500">{{ $message }}</p>@enderror
    </div>
    @endif

    {{-- Goals by employee --}}
    @foreach($employees as $emp)
    @php $empGoals = $emp->goals; @endphp
    @if($empGoals->count() || $cycle->status !== 'closed')
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-3 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
            <div class="font-medium text-sm text-gray-900">{{ $emp->full_name }}</div>
            @php $totalW = $empGoals->sum('weight'); @endphp
            <span class="text-xs {{ $totalW > 100 ? 'text-red-500' : ($totalW == 100 ? 'text-green-600' : 'text-gray-400') }}">
                {{ $totalW }}% / 100%
            </span>
        </div>
        @if($empGoals->isEmpty())
        <div class="px-6 py-4 text-xs text-gray-400">No goals set.</div>
        @else
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-50">
                @foreach($empGoals as $goal)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-800">{{ $goal->title }}</td>
                    <td class="px-6 py-3 text-gray-400 text-xs">Target: {{ $goal->target_value ?? '—' }}</td>
                    <td class="px-6 py-3 text-gray-400 text-xs">Achieved: {{ $goal->achieved_value ?? '—' }}</td>
                    <td class="px-6 py-3 text-right font-tabular text-gray-600">{{ $goal->weight }}%</td>
                    @if(Auth::guard('hr')->user()->isManager() && $cycle->status !== 'closed')
                    <td class="px-6 py-3 text-right">
                        <form method="POST" action="{{ route('hr.performance.goals.destroy', $goal) }}" class="inline">
                            @csrf @method('DELETE')
                            <button onclick="return confirm('Delete goal?')" class="text-red-300 hover:text-red-500 text-xs">✕</button>
                        </form>
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
    @endif
    @endforeach

</div>
@endsection
