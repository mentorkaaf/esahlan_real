@extends('hr.layouts.app')
@section('title', 'Kanban — ' . $posting->title)
@section('heading', $posting->title . ' — Pipeline')

@section('content')
<div class="space-y-4">

    {{-- Posting header --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-4">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $posting->getStatusBadgeClass() }}">{{ ucfirst($posting->status) }}</span>
            <span class="text-sm text-gray-500">{{ $posting->department?->name ?? '' }} · {{ $posting->getTypeLabel() }}</span>
            <span class="text-sm text-gray-400">{{ $posting->hired_count }}/{{ $posting->openings }} filled · Closes {{ $posting->closes_at?->format('d M Y') ?? 'no deadline' }}</span>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('hr.recruitment.postings.edit', $posting) }}"
               class="text-xs text-gray-500 hover:text-gray-700 px-3 py-1.5 border border-gray-200 rounded-lg">Edit Posting</a>
            <a href="{{ route('careers.show', $posting) }}" target="_blank"
               class="text-xs text-brand hover:text-brand-600 px-3 py-1.5 border border-brand/30 rounded-lg">Public Page ↗</a>
        </div>
    </div>

    {{-- Kanban board --}}
    @php
    $stageLabels = ['applied'=>'Applied','screening'=>'Screening','interview'=>'Interview','offer'=>'Offer','hired'=>'Hired','rejected'=>'Rejected'];
    $stageCols   = ['applied','screening','interview','offer','hired','rejected'];
    $stageColors = ['applied'=>'bg-blue-50 border-blue-200','screening'=>'bg-purple-50 border-purple-200','interview'=>'bg-yellow-50 border-yellow-200','offer'=>'bg-orange-50 border-orange-200','hired'=>'bg-green-50 border-green-200','rejected'=>'bg-gray-50 border-gray-200'];
    @endphp

    <div class="flex gap-4 overflow-x-auto pb-4">
        @foreach($stageCols as $stage)
        @php $applicants = $byStage[$stage] ?? collect(); @endphp
        <div class="flex-shrink-0 w-64">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wide">{{ $stageLabels[$stage] }}</h3>
                <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">{{ $applicants->count() }}</span>
            </div>

            <div class="space-y-3">
                @forelse($applicants as $app)
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-3 mb-3">
                        <div class="w-8 h-8 rounded-full bg-navy flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                            {{ $app->getInitials() }}
                        </div>
                        <div class="min-w-0">
                            <div class="font-medium text-gray-900 text-sm truncate">{{ $app->name }}</div>
                            <div class="text-xs text-gray-400 truncate">{{ $app->email }}</div>
                        </div>
                    </div>

                    {{-- Rating stars --}}
                    @if($app->rating)
                    <div class="flex gap-0.5 mb-2">
                        @for($i = 1; $i <= 5; $i++)
                        <span class="{{ $i <= $app->rating ? 'text-yellow-400' : 'text-gray-200' }} text-xs">★</span>
                        @endfor
                    </div>
                    @endif

                    <div class="flex items-center justify-between text-xs text-gray-400 mb-3">
                        <span>{{ $app->stage_changed_at?->diffForHumans() ?? 'Just now' }}</span>
                        @if($app->source)
                        <span class="bg-gray-100 px-1.5 py-0.5 rounded text-xs">{{ $app->source }}</span>
                        @endif
                    </div>

                    <div class="flex flex-col gap-1">
                        <a href="{{ route('hr.recruitment.applicants.show', $app) }}"
                           class="w-full text-center text-xs text-navy hover:text-brand py-1 border border-gray-200 rounded-lg transition-colors">
                            View Profile
                        </a>

                        @if(!in_array($stage, ['hired', 'rejected']))
                        {{-- Move forward --}}
                        @php $next = $app->nextStage(); @endphp
                        @if($next)
                        <form method="POST" action="{{ route('hr.recruitment.applicants.stage', $app) }}">
                            @csrf
                            <input type="hidden" name="stage" value="{{ $next }}">
                            <button class="w-full text-xs bg-navy text-white py-1 rounded-lg hover:bg-navy-500 transition-colors"
                                    @if($next === 'hired') onclick="return confirm('Hire this applicant? This will create an employee record.')" @endif>
                                → {{ ucfirst($next) }}
                            </button>
                        </form>
                        @endif
                        {{-- Reject --}}
                        <form method="POST" action="{{ route('hr.recruitment.applicants.stage', $app) }}">
                            @csrf
                            <input type="hidden" name="stage" value="rejected">
                            <button onclick="return confirm('Reject this applicant?')"
                                    class="w-full text-xs text-red-400 hover:text-red-600 py-1 rounded-lg transition-colors">
                                Reject
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
                @empty
                <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center text-xs text-gray-400">
                    No applicants
                </div>
                @endforelse
            </div>
        </div>
        @endforeach
    </div>

</div>
@endsection
