@extends('hr.layouts.app')
@section('title', 'Disciplinary Case #' . $case->id)

@section('content')
<div class="max-w-4xl space-y-6">

<div class="flex items-center gap-3 mb-2">
    <a href="{{ route('hr.discipline.index') }}" class="text-gray-400 hover:text-gray-600 text-sm">← Cases</a>
</div>

@if(session('success'))
    <div class="p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">{{ session('error') }}</div>
@endif

{{-- Case header --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ $case->title }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                Case #{{ $case->id }} &bull; Opened {{ $case->created_at->format('d M Y') }}
                by {{ $case->openedBy?->first_name }} {{ $case->openedBy?->last_name }}
            </p>
        </div>
        <div class="flex gap-2">
            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $case->getSeverityBadgeClass() }}">{{ ucfirst($case->severity) }}</span>
            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $case->getStatusBadgeClass() }}">{{ ucfirst($case->status) }}</span>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4 mt-5 pt-5 border-t border-gray-100 text-sm">
        <div>
            <p class="text-gray-400 text-xs uppercase tracking-wide mb-1">Employee</p>
            <p class="font-medium">{{ $case->employee->full_name }}</p>
            <p class="text-gray-500 text-xs">{{ $case->employee->department?->name }}</p>
        </div>
        <div>
            <p class="text-gray-400 text-xs uppercase tracking-wide mb-1">Category</p>
            <p class="font-medium capitalize">{{ $case->category }}</p>
        </div>
        <div>
            <p class="text-gray-400 text-xs uppercase tracking-wide mb-1">Outcome</p>
            <p class="font-medium">{{ $case->outcome ? $case->getOutcomeLabel() : '—' }}</p>
            @if($case->closed_at)
                <p class="text-xs text-gray-400">{{ $case->closed_at->format('d M Y') }}</p>
            @endif
        </div>
    </div>

    <div class="mt-4 p-4 bg-gray-50 rounded-lg text-sm text-gray-700 whitespace-pre-wrap">{{ $case->description }}</div>
</div>

{{-- Investigation notes --}}
@if($case->status !== 'closed')
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h2 class="text-base font-semibold text-gray-900 mb-4">Investigation Notes</h2>
    <form method="POST" action="{{ route('hr.discipline.investigate', $case) }}" class="space-y-3">
        @csrf
        <textarea name="investigation_notes" rows="5" maxlength="5000"
                  placeholder="Document investigation findings, interviews conducted, evidence gathered…"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent">{{ old('investigation_notes', $case->investigation_notes) }}</textarea>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            Save Notes
        </button>
    </form>
</div>

{{-- Close case --}}
<div class="bg-white rounded-xl shadow-sm border border-red-100 p-6">
    <h2 class="text-base font-semibold text-gray-900 mb-4">Close Case</h2>
    <form method="POST" action="{{ route('hr.discipline.close', $case) }}" class="space-y-4">
        @csrf
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Outcome <span class="text-red-500">*</span></label>
                <select name="outcome" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Select outcome…</option>
                    @foreach(['verbal_warning'=>'Verbal Warning','written_warning'=>'Written Warning','suspension'=>'Suspension','termination'=>'Termination','dismissed'=>'Case Dismissed'] as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Closing Note</label>
                <input type="text" name="close_note" maxlength="2000" placeholder="Optional note"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>
        <p class="text-xs text-amber-600 bg-amber-50 px-3 py-2 rounded-lg">
            ⚠ Termination outcome will immediately set the employee status to terminated and end all active contracts.
            This action is audited and cannot be undone from the HR panel.
        </p>
        <button type="submit"
                onclick="return confirm('Close this case? This action is permanent and will be audited.')"
                class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">
            Close Case
        </button>
    </form>
</div>
@else
    @if($case->investigation_notes)
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-3">Investigation Notes</h2>
        <div class="text-sm text-gray-700 whitespace-pre-wrap">{{ $case->investigation_notes }}</div>
    </div>
    @endif
@endif

{{-- Warnings --}}
@if($case->warnings->count())
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h2 class="text-base font-semibold text-gray-900 mb-4">Warning Letters</h2>
    <div class="space-y-3">
    @foreach($case->warnings as $warning)
        <div class="flex items-center justify-between p-3 bg-amber-50 rounded-lg border border-amber-100">
            <div>
                <p class="font-medium text-sm">{{ $warning->title }}</p>
                <p class="text-xs text-gray-500">
                    {{ ucfirst($warning->type) }} warning &bull;
                    Issued {{ $warning->created_at->format('d M Y') }} by {{ $warning->issuedBy?->first_name }}
                    @if($warning->acknowledged_at)
                        &bull; <span class="text-green-600">Acknowledged {{ $warning->acknowledged_at->format('d M Y') }}</span>
                    @else
                        &bull; <span class="text-amber-600">Pending acknowledgment</span>
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if(!$warning->acknowledged_at)
                <form method="POST" action="{{ route('hr.discipline.warning.acknowledge', $warning) }}">
                    @csrf
                    <button class="text-xs bg-green-100 hover:bg-green-200 text-green-700 px-3 py-1 rounded-lg transition">
                        Mark Acknowledged
                    </button>
                </form>
                @endif
                @if($warning->pdf_path)
                <a href="{{ route('hr.discipline.warning.pdf', $warning) }}"
                   class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1 rounded-lg transition">
                    Download PDF
                </a>
                @endif
            </div>
        </div>
    @endforeach
    </div>
</div>
@endif

{{-- Audit trail --}}
@if($auditLogs->count())
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h2 class="text-base font-semibold text-gray-900 mb-4">Case Audit Trail</h2>
    <div class="space-y-2">
    @foreach($auditLogs as $log)
        <div class="flex items-start gap-3 text-sm">
            <div class="w-2 h-2 rounded-full bg-gray-300 mt-2 flex-shrink-0"></div>
            <div>
                <span class="font-medium text-gray-700">{{ $log->actor_name }}</span>
                <span class="text-gray-500"> — {{ str_replace('.',' ', $log->action) }}</span>
                <span class="text-xs text-gray-400 ml-2">{{ $log->created_at->format('d M Y H:i') }}</span>
            </div>
        </div>
    @endforeach
    </div>
</div>
@endif

</div>
@endsection
