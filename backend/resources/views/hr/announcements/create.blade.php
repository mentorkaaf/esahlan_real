@extends('hr.layouts.app')
@section('title', 'New Announcement')

@section('content')
<div class="max-w-2xl">
<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('hr.announcements.index') }}" class="text-gray-400 hover:text-gray-600">← Announcements</a>
    <h1 class="text-2xl font-bold text-gray-900">New Announcement</h1>
</div>

@if($errors->any())
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
        <ul class="list-disc pl-4 space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('hr.announcements.store') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
    @csrf

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
        <input type="text" name="title" value="{{ old('title') }}" required maxlength="200"
               placeholder="Announcement title"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Message <span class="text-red-500">*</span></label>
        <textarea name="body" rows="6" required maxlength="5000"
                  placeholder="Write your announcement message…"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent">{{ old('body') }}</textarea>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Audience <span class="text-red-500">*</span></label>
        <select name="audience" id="audienceSelect" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent">
            <option value="all" @selected(old('audience','all') === 'all')>All Employees</option>
            <option value="department" @selected(old('audience') === 'department')>Specific Department</option>
        </select>
    </div>

    <div id="deptField" class="{{ old('audience') === 'department' ? '' : 'hidden' }}">
        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
        <select name="department_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Select department…</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}" @selected(old('department_id') == $dept->id)>{{ $dept->name }}</option>
            @endforeach
        </select>
    </div>

    <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
        <input type="checkbox" name="publish_now" value="1" @checked(old('publish_now'))
               class="w-4 h-4 text-blue-600 rounded border-gray-300">
        Publish immediately and send push notifications
    </label>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition">
            Create Announcement
        </button>
        <a href="{{ route('hr.announcements.index') }}" class="px-5 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 transition">Cancel</a>
    </div>
</form>
</div>

<script>
document.getElementById('audienceSelect').addEventListener('change', function() {
    document.getElementById('deptField').classList.toggle('hidden', this.value !== 'department');
});
</script>
@endsection
