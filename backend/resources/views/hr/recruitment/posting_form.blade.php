@extends('hr.layouts.app')
@section('title', isset($posting) ? 'Edit Posting' : 'New Job Posting')
@section('heading', isset($posting) ? 'Edit: ' . $posting->title : 'New Job Posting')

@section('content')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form method="POST"
              action="{{ isset($posting) ? route('hr.recruitment.postings.update', $posting) : route('hr.recruitment.postings.store') }}"
              class="space-y-5">
            @csrf
            @if(isset($posting)) @method('PUT') @endif

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Job Title *</label>
                    <input type="text" name="title" value="{{ old('title', $posting->title ?? '') }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand @error('title') border-red-400 @enderror">
                    @error('title')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Department</label>
                    <select name="department_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                        <option value="">— Any —</option>
                        @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ old('department_id', $posting->department_id ?? '') == $dept->id ? 'selected' : '' }}>
                            {{ $dept->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Position</label>
                    <select name="position_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                        <option value="">— Any —</option>
                        @foreach($positions as $pos)
                        <option value="{{ $pos->id }}" {{ old('position_id', $posting->position_id ?? '') == $pos->id ? 'selected' : '' }}>
                            {{ $pos->title }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Type *</label>
                    <select name="type" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                        @foreach(['full_time'=>'Full-time','part_time'=>'Part-time','contract'=>'Contract'] as $val=>$label)
                        <option value="{{ $val }}" {{ old('type', $posting->type ?? 'full_time') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Openings *</label>
                    <input type="number" name="openings" value="{{ old('openings', $posting->openings ?? 1) }}" required min="1"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Status *</label>
                    <select name="status" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                        @foreach(['draft','open','paused','closed'] as $s)
                        <option value="{{ $s }}" {{ old('status', $posting->status ?? 'draft') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Location</label>
                    <input type="text" name="location" value="{{ old('location', $posting->location ?? 'Mogadishu, Somalia') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Closes On</label>
                    <input type="date" name="closes_at" value="{{ old('closes_at', $posting->closes_at?->format('Y-m-d') ?? '') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="5"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">{{ old('description', $posting->description ?? '') }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Requirements</label>
                <textarea name="requirements" rows="4" placeholder="• 3+ years experience&#10;• Fluent Somali &amp; English"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand">{{ old('requirements', $posting->requirements ?? '') }}</textarea>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-brand hover:bg-brand-600 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                    {{ isset($posting) ? 'Save Changes' : 'Create Posting' }}
                </button>
                <a href="{{ route('hr.recruitment.postings.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
