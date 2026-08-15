@extends('hr.layouts.app')
@section('title', 'Job Postings')
@section('heading', 'Recruitment — Job Postings')

@section('content')
<div class="space-y-6">

    <div class="flex justify-end">
        @if(Auth::guard('hr')->user()->isRecruiter())
        <a href="{{ route('hr.recruitment.postings.create') }}"
           class="inline-flex items-center gap-2 bg-brand hover:bg-brand-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Posting
        </a>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">All Postings</h2>
        </div>
        @if($postings->isEmpty())
        <div class="py-16 text-center text-gray-400 text-sm">No job postings yet.</div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-6 py-3 text-left">Position</th>
                        <th class="px-6 py-3 text-left">Department</th>
                        <th class="px-6 py-3 text-left">Type</th>
                        <th class="px-6 py-3 text-center">Openings</th>
                        <th class="px-6 py-3 text-center">Applicants</th>
                        <th class="px-6 py-3 text-left">Status</th>
                        <th class="px-6 py-3 text-left">Closes</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($postings as $p)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $p->title }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $p->department?->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $p->getTypeLabel() }}</td>
                        <td class="px-6 py-4 text-center">
                            <span class="font-tabular">{{ $p->hired_count }}/{{ $p->openings }}</span>
                        </td>
                        <td class="px-6 py-4 text-center font-tabular">{{ $p->applicants_count }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $p->getStatusBadgeClass() }}">
                                {{ ucfirst($p->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-400 text-xs">
                            {{ $p->closes_at?->format('d M Y') ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-right space-x-3">
                            <a href="{{ route('hr.recruitment.postings.show', $p) }}" class="text-navy hover:text-brand text-xs font-medium">Kanban →</a>
                            @if(Auth::guard('hr')->user()->isRecruiter())
                            <a href="{{ route('hr.recruitment.postings.edit', $p) }}" class="text-gray-400 hover:text-gray-700 text-xs">Edit</a>
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
@endsection
