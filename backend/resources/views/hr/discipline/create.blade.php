@extends('hr.layouts.app')
@section('title', 'Open Disciplinary Case')

@section('content')
<div class="max-w-2xl">
<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('hr.discipline.index') }}" class="text-gray-400 hover:text-gray-600">← Cases</a>
    <h1 class="text-2xl font-bold text-gray-900">Open Disciplinary Case</h1>
</div>

@if($errors->any())
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
        <ul class="list-disc pl-4 space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('hr.discipline.store') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
    @csrf

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Employee <span class="text-red-500">*</span></label>
        <select name="employee_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 focus:border-transparent">
            <option value="">Select employee…</option>
            @foreach($employees as $emp)
                <option value="{{ $emp->id }}" @selected(old('employee_id') == $emp->id)>
                    {{ $emp->full_name }} — {{ $emp->department?->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
            <select name="category" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 focus:border-transparent">
                @foreach(['attendance','conduct','performance','other'] as $cat)
                    <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ ucfirst($cat) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Severity <span class="text-red-500">*</span></label>
            <select name="severity" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 focus:border-transparent">
                @foreach(['minor','moderate','major'] as $sev)
                    <option value="{{ $sev }}" @selected(old('severity') === $sev)>{{ ucfirst($sev) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
        <input type="text" name="title" value="{{ old('title') }}" required maxlength="150"
               placeholder="Brief title of the issue"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 focus:border-transparent">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Description <span class="text-red-500">*</span></label>
        <textarea name="description" rows="5" required maxlength="3000"
                  placeholder="Describe the incident, dates, witnesses…"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 focus:border-transparent">{{ old('description') }}</textarea>
    </div>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">
            Open Case
        </button>
        <a href="{{ route('hr.discipline.index') }}" class="px-5 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 transition">Cancel</a>
    </div>
</form>
</div>
@endsection
