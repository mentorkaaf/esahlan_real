@extends('hr.layouts.app')
@section('title', 'Edit Document')
@section('heading', 'Edit Document')
@section('content')
<div class="max-w-xl">
    <form method="POST" action="{{ route('hr.documents.update', $document) }}" class="space-y-5">
        @csrf @method('PUT')
        @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            <x-hr-input name="title" label="Document Title" required :value="$document->title"/>
            <x-hr-input name="expires_at" label="Expiry Date" type="date" :value="$document->expires_at?->format('Y-m-d')"/>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
                <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#1B1444]">{{ old('notes', $document->notes) }}</textarea>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <button type="submit" class="bg-[#F7941D] hover:bg-[#E07800] text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">Save</button>
            <a href="{{ route('hr.employees.show', $document->employee) }}" class="text-gray-500 hover:text-gray-700 text-sm">Cancel</a>
        </div>
    </form>
</div>
@endsection
