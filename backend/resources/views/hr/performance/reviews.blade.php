@extends('hr.layouts.app')
@section('title', 'Reviews — ' . $cycle->name)
@section('heading', $cycle->name . ' — Reviews')

@section('content')
<div class="space-y-6">

    <div class="flex items-center justify-between">
        <a href="{{ route('hr.performance.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Cycles</a>
        <a href="{{ route('hr.performance.report', $cycle) }}" class="text-xs text-brand font-medium hover:text-brand-600">View Report →</a>
    </div>

    @foreach($employees as $emp)
    @php
        $empGoals  = $emp->goals;
        $review    = $emp->reviews->first();
        $isSubmitted = $review && $review->status === 'submitted';
    @endphp

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-navy flex items-center justify-center text-white text-xs font-bold">
                    {{ $emp->initials }}
                </div>
                <div>
                    <div class="font-semibold text-gray-900 text-sm">{{ $emp->full_name }}</div>
                    <div class="text-xs text-gray-400">{{ $empGoals->count() }} goal(s) · total weight {{ $empGoals->sum('weight') }}%</div>
                </div>
            </div>
            @if($review)
            <div class="flex items-center gap-3">
                @if($review->overall_score)
                <span class="text-lg font-bold {{ $review->getScoreBadgeClass() }} px-2 py-1 rounded-lg font-tabular">
                    {{ number_format($review->overall_score, 2) }}/5.00
                </span>
                @endif
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $isSubmitted ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                    {{ $isSubmitted ? 'Submitted' : 'Draft' }}
                </span>
            </div>
            @endif
        </div>

        @if($empGoals->isEmpty())
        <div class="px-6 py-4 text-xs text-gray-400">No goals set for this employee. Add goals first.</div>
        @else
        @if($cycle->status !== 'closed' || ($review && !$isSubmitted))
        <form method="POST" action="{{ route('hr.performance.reviews.store', [$cycle, $emp]) }}" class="p-6 space-y-5">
            @csrf

            {{-- Reviewer --}}
            <div class="flex items-center gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Reviewer</label>
                    <select name="reviewer_id"
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30">
                        <option value="">— Self (me) —</option>
                        @foreach($staff as $s)
                        <option value="{{ $s->id }}" {{ ($review?->reviewer_id == $s->id) ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Goal scores --}}
            <div>
                <h4 class="text-xs font-semibold text-gray-600 uppercase mb-3">Goal Scores (1–5)</h4>
                <div class="space-y-3">
                    @foreach($empGoals as $goal)
                    @php $prevScore = $review?->scores[$goal->id] ?? null; @endphp
                    <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-800">{{ $goal->title }}</div>
                            <div class="text-xs text-gray-400">Weight: {{ $goal->weight }}% · Target: {{ $goal->target_value ?? '—' }}</div>
                        </div>
                        <input type="number" name="score_{{ $goal->id }}" value="{{ old("score_{$goal->id}", $prevScore) }}"
                               min="1" max="5" step="0.5" placeholder="—"
                               class="w-20 border border-gray-300 rounded-lg px-3 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-brand/30">
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Competency scores --}}
            <div>
                <h4 class="text-xs font-semibold text-gray-600 uppercase mb-3">Competency Scores (1–5)</h4>
                <div class="grid grid-cols-2 gap-3">
                    @foreach(['communication'=>'Communication','teamwork'=>'Teamwork','punctuality'=>'Punctuality','initiative'=>'Initiative'] as $key=>$label)
                    @php $prev = $review?->competency_scores[$key] ?? null; @endphp
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-sm text-gray-700">{{ $label }}</span>
                        <input type="number" name="competency_{{ $key }}" value="{{ old("competency_$key", $prev) }}"
                               min="1" max="5" step="0.5" placeholder="—"
                               class="w-20 border border-gray-300 rounded-lg px-3 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-brand/30">
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Notes --}}
            <div>
                <label class="block text-xs text-gray-500 mb-1">Overall Notes</label>
                <textarea name="notes" rows="3"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30">{{ old('notes', $review?->notes) }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" name="submit" value="0"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm px-4 py-2 rounded-lg transition-colors">
                    Save Draft
                </button>
                <button type="submit" name="submit" value="1"
                        class="bg-navy hover:bg-navy-500 text-white text-sm px-4 py-2 rounded-lg transition-colors"
                        onclick="return confirm('Submit this review? It will be finalized.')">
                    Submit Review
                </button>
            </div>
        </form>
        @else
        <div class="p-6 text-sm text-gray-500">
            Review submitted. <a href="{{ route('hr.performance.report', $cycle) }}" class="text-brand underline">View report →</a>
        </div>
        @endif
        @endif
    </div>
    @endforeach

</div>
@endsection
