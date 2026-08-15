@extends('hr.layouts.app')
@section('title', 'Announcements')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Announcements</h1>
    <a href="{{ route('hr.announcements.create') }}"
       class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
        ＋ New Announcement
    </a>
</div>

@if(session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">{{ session('success') }}</div>
@endif

<div class="space-y-4">
@forelse($announcements as $ann)
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-1">
                    @if($ann->isPublished())
                        <span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>
                        <span class="text-xs text-green-600 font-medium">Published {{ $ann->published_at->format('d M Y H:i') }}</span>
                    @else
                        <span class="w-2 h-2 rounded-full bg-gray-300 inline-block"></span>
                        <span class="text-xs text-gray-400 font-medium">Draft</span>
                    @endif
                    <span class="text-xs text-gray-400">&bull; {{ $ann->audience === 'all' ? 'All Employees' : 'Dept: ' . ($ann->department?->name ?? '—') }}</span>
                </div>
                <h2 class="text-base font-semibold text-gray-900">{{ $ann->title }}</h2>
                <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ Str::limit(strip_tags($ann->body), 200) }}</p>
                <p class="text-xs text-gray-400 mt-2">By {{ $ann->creator?->first_name }} {{ $ann->creator?->last_name }} &bull; {{ $ann->created_at->format('d M Y') }}</p>
            </div>
            <div class="flex gap-2 flex-shrink-0">
                @if(!$ann->isPublished())
                <form method="POST" action="{{ route('hr.announcements.publish', $ann) }}">
                    @csrf
                    <button class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1 rounded-lg transition font-medium">
                        📢 Publish
                    </button>
                </form>
                <form method="POST" action="{{ route('hr.announcements.destroy', $ann) }}">
                    @csrf @method('DELETE')
                    <button onclick="return confirm('Delete this announcement?')"
                            class="text-xs bg-red-50 hover:bg-red-100 text-red-600 px-3 py-1 rounded-lg transition">
                        Delete
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
@empty
    <div class="bg-white rounded-xl border border-gray-100 p-12 text-center text-gray-400">
        <p class="text-lg mb-2">📢</p>
        <p>No announcements yet. Create one to notify your team.</p>
    </div>
@endforelse

@if($announcements->hasPages())
    <div>{{ $announcements->links() }}</div>
@endif
</div>
@endsection
