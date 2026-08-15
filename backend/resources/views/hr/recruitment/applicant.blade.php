@extends('hr.layouts.app')
@section('title', $applicant->name . ' — Applicant')
@section('heading', 'Applicant Profile')

@section('content')
<div class="max-w-3xl space-y-6">

    {{-- Back --}}
    <a href="{{ route('hr.recruitment.postings.show', $applicant->jobPosting) }}"
       class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
        ← Back to {{ $applicant->jobPosting->title }} pipeline
    </a>

    {{-- Header --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-full bg-navy flex items-center justify-center text-white font-bold text-xl">
                    {{ $applicant->getInitials() }}
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">{{ $applicant->name }}</h2>
                    <div class="text-sm text-gray-500">{{ $applicant->email }}</div>
                    @if($applicant->phone)
                    <div class="text-sm text-gray-400">{{ $applicant->phone }}</div>
                    @endif
                    <div class="flex items-center gap-2 mt-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $applicant->getStageBadgeClass() }}">
                            {{ ucfirst($applicant->stage) }}
                        </span>
                        @if($applicant->source)
                        <span class="text-xs text-gray-400">via {{ $applicant->source }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-2 text-right">
                @if($applicant->cv_path)
                <a href="{{ route('hr.recruitment.applicants.cv', $applicant) }}"
                   class="inline-flex items-center gap-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs px-3 py-1.5 rounded-lg transition-colors">
                    ↓ Download CV
                </a>
                @endif
                <div class="text-xs text-gray-400">Applied {{ $applicant->created_at->format('d M Y') }}</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-6">

        {{-- Notes & Rating --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Notes & Rating</h3>
            <form method="POST" action="{{ route('hr.recruitment.applicants.update', $applicant) }}" class="space-y-4">
                @csrf @method('PATCH')
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Rating (1–5)</label>
                    <div class="flex gap-2">
                        @for($i = 1; $i <= 5; $i++)
                        <label class="cursor-pointer">
                            <input type="radio" name="rating" value="{{ $i }}" {{ $applicant->rating == $i ? 'checked' : '' }} class="sr-only peer">
                            <span class="text-2xl peer-checked:text-yellow-400 text-gray-200 hover:text-yellow-300 transition-colors">★</span>
                        </label>
                        @endfor
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Notes</label>
                    <textarea name="notes" rows="5"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">{{ $applicant->notes }}</textarea>
                </div>
                <button type="submit" class="bg-navy text-white text-xs px-4 py-2 rounded-lg hover:bg-navy-500 transition-colors">Save</button>
            </form>
        </div>

        {{-- Stage actions --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Move Stage</h3>
            @foreach(\App\Models\HR\HrApplicant::STAGES as $s)
            @if($s !== $applicant->stage)
            <form method="POST" action="{{ route('hr.recruitment.applicants.stage', $applicant) }}">
                @csrf
                <input type="hidden" name="stage" value="{{ $s }}">
                <button class="w-full text-left text-sm px-3 py-2 rounded-lg border transition-colors
                    @if($s === 'hired') border-green-300 text-green-700 hover:bg-green-50
                    @elseif($s === 'rejected') border-red-200 text-red-500 hover:bg-red-50
                    @else border-gray-200 text-gray-700 hover:bg-gray-50 @endif"
                    @if($s === 'hired') onclick="return confirm('Hire this applicant? An employee record will be created.')" @endif>
                    @if($s === 'hired') ✓ Hire @elseif($s === 'rejected') ✗ Reject @else → {{ ucfirst($s) }} @endif
                </button>
            </form>
            @endif
            @endforeach
        </div>

    </div>

    {{-- Interviews --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-700">Interviews</h3>
        </div>

        {{-- Schedule form --}}
        <details class="mb-4">
            <summary class="cursor-pointer text-xs text-brand font-medium">+ Schedule Interview</summary>
            <form method="POST" action="{{ route('hr.recruitment.applicants.interview', $applicant) }}" class="mt-3 space-y-3 pl-3 border-l-2 border-brand/30">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Interviewer</label>
                        <select name="interviewer_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand/30">
                            <option value="">— Unassigned —</option>
                            @foreach($staff as $s)
                            <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->role_label }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Mode</label>
                        <select name="mode"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand/30">
                            <option value="in_person">In Person</option>
                            <option value="video">Video Call</option>
                            <option value="phone">Phone</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Date & Time *</label>
                        <input type="datetime-local" name="scheduled_at" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand/30">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Location / Link</label>
                        <input type="text" name="location_or_link"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand/30">
                    </div>
                </div>
                <button type="submit" class="bg-brand text-white text-xs px-4 py-2 rounded-lg">Schedule</button>
            </form>
        </details>

        @if($applicant->interviews->isEmpty())
        <div class="text-center text-xs text-gray-400 py-6">No interviews scheduled yet.</div>
        @else
        <div class="space-y-3">
            @foreach($applicant->interviews as $iv)
            <div class="border border-gray-100 rounded-xl p-4">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <div class="text-sm font-medium text-gray-900">{{ $iv->scheduled_at->format('D, d M Y H:i') }}</div>
                        <div class="text-xs text-gray-500">{{ ucfirst(str_replace('_', ' ', $iv->mode)) }} · {{ $iv->interviewer?->name ?? 'Unassigned' }}</div>
                        @if($iv->location_or_link)
                        <div class="text-xs text-gray-400">{{ $iv->location_or_link }}</div>
                        @endif
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $iv->getStatusBadgeClass() }}">
                        {{ ucfirst($iv->status) }}
                    </span>
                </div>

                @if($iv->status === 'scheduled')
                <details class="mt-2">
                    <summary class="text-xs text-brand cursor-pointer">Enter Feedback</summary>
                    <form method="POST" action="{{ route('hr.recruitment.interviews.feedback', $iv) }}" class="mt-2 space-y-2">
                        @csrf
                        <textarea name="feedback" rows="3" placeholder="Feedback notes..."
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand/30">{{ $iv->feedback }}</textarea>
                        <select name="result" required
                                class="border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand/30">
                            <option value="passed">Passed</option>
                            <option value="on_hold">On Hold</option>
                            <option value="failed">Failed</option>
                        </select>
                        <button type="submit" class="bg-navy text-white text-xs px-3 py-1.5 rounded-lg">Save Feedback</button>
                    </form>
                </details>
                @elseif($iv->feedback)
                <div class="mt-2 text-xs text-gray-600 bg-gray-50 rounded-lg px-3 py-2">
                    <strong>Result:</strong> {{ ucfirst($iv->result) }}<br>
                    {{ $iv->feedback }}
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>

</div>
@endsection
