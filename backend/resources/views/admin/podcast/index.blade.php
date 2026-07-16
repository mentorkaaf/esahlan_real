@extends('admin.layouts.app')
@section('title', 'Podcast Management')

@section('content')
<div class="p-6 space-y-6">

  {{-- ── Header ─────────────────────────────────────────────────────────── --}}
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-black text-navy flex items-center gap-2">
        <span class="w-9 h-9 rounded-xl flex items-center justify-content-center"
              style="background:linear-gradient(135deg,#07003B,#FF8A00)">
          <i class="fas fa-podcast text-white text-sm"></i>
        </span>
        Podcast Management
      </h1>
      <p class="text-slate-400 text-xs mt-1">Monitor, verify and moderate all podcast content</p>
    </div>
    <div class="flex gap-2">
      <a href="{{ route('admin.podcast.categories') }}"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold text-white"
         style="background:#07003B">
        <i class="fas fa-tags text-xs"></i> Categories
      </a>
    </div>
  </div>

  {{-- ── Stat Cards ─────────────────────────────────────────────────────── --}}
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

    {{-- Podcasts --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 relative overflow-hidden group hover:-translate-y-0.5 transition-transform">
      <div class="absolute top-0 right-0 w-24 h-24 rounded-full opacity-5 -mr-6 -mt-6"
           style="background:#07003B"></div>
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Podcasts</p>
          <p class="text-3xl font-black text-navy mt-1">{{ number_format($stats['podcasts']) }}</p>
          <p class="text-xs text-slate-400 mt-1 flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block"></span> Active shows
          </p>
        </div>
        <div class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0"
             style="background:#07003B10">
          <i class="fas fa-podcast text-navy"></i>
        </div>
      </div>
    </div>

    {{-- Episodes --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 relative overflow-hidden group hover:-translate-y-0.5 transition-transform">
      <div class="absolute top-0 right-0 w-24 h-24 rounded-full opacity-5 -mr-6 -mt-6"
           style="background:#FF8A00"></div>
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Episodes</p>
          <p class="text-3xl font-black mt-1" style="color:#FF8A00">{{ number_format($stats['episodes']) }}</p>
          <p class="text-xs text-slate-400 mt-1 flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full inline-block" style="background:#FF8A00"></span> Published
          </p>
        </div>
        <div class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0"
             style="background:#FF8A0015">
          <i class="fas fa-play-circle" style="color:#FF8A00"></i>
        </div>
      </div>
    </div>

    {{-- Plays --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 relative overflow-hidden group hover:-translate-y-0.5 transition-transform">
      <div class="absolute top-0 right-0 w-24 h-24 rounded-full opacity-5 -mr-6 -mt-6"
           style="background:#10b981"></div>
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Plays</p>
          <p class="text-3xl font-black text-emerald-500 mt-1">{{ number_format($stats['plays']) }}</p>
          <p class="text-xs text-slate-400 mt-1 flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block"></span> All time
          </p>
        </div>
        <div class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 bg-emerald-50">
          <i class="fas fa-headphones text-emerald-500"></i>
        </div>
      </div>
    </div>

    {{-- Live --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 relative overflow-hidden group hover:-translate-y-0.5 transition-transform">
      <div class="absolute top-0 right-0 w-24 h-24 rounded-full opacity-5 -mr-6 -mt-6"
           style="background:#ef4444"></div>
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Live Rooms</p>
          <p class="text-3xl font-black text-red-500 mt-1">{{ $stats['live_rooms'] }}</p>
          <p class="text-xs text-slate-400 mt-1 flex items-center gap-1">
            @if($stats['live_rooms'] > 0)
              <span class="w-1.5 h-1.5 rounded-full bg-red-400 inline-block animate-pulse"></span> On air now
            @else
              <span class="w-1.5 h-1.5 rounded-full bg-slate-300 inline-block"></span> No live rooms
            @endif
          </p>
        </div>
        <div class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 bg-red-50">
          <i class="fas fa-broadcast-tower text-red-500"></i>
        </div>
      </div>
    </div>
  </div>

  {{-- ── All Podcasts ────────────────────────────────────────────────────── --}}
  <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

    {{-- Table header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:#FF8A0015">
          <i class="fas fa-podcast text-sm" style="color:#FF8A00"></i>
        </div>
        <h2 class="font-black text-navy text-sm">All Podcasts</h2>
      </div>
      <span class="text-xs font-bold px-3 py-1 rounded-full"
            style="background:#07003B;color:#fff">
        {{ $podcasts->total() }} total
      </span>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 text-slate-400 text-xs uppercase tracking-wider">
            <th class="text-left px-6 py-3 font-semibold">Podcast</th>
            <th class="text-left px-4 py-3 font-semibold">Category</th>
            <th class="text-center px-4 py-3 font-semibold">Episodes</th>
            <th class="text-center px-4 py-3 font-semibold">Followers</th>
            <th class="text-center px-4 py-3 font-semibold">Plays</th>
            <th class="text-left px-4 py-3 font-semibold">Popularity</th>
            <th class="text-left px-4 py-3 font-semibold">Status</th>
            <th class="text-center px-4 py-3 font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($podcasts as $podcast)
          @php
            $maxPlays = $podcasts->max('total_plays') ?: 1;
            $pct = min(100, round(($podcast->total_plays / $maxPlays) * 100));
          @endphp
          <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-3">
              <div class="flex items-center gap-3">
                {{-- Cover --}}
                @if($podcast->cover_image)
                  <img src="{{ asset('storage/'.$podcast->cover_image) }}"
                       class="w-11 h-11 rounded-xl object-cover shadow-sm" alt="">
                @else
                  <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0"
                       style="background:linear-gradient(135deg,#07003B,#1a0080)">
                    <i class="fas fa-podcast text-orange-400 text-sm"></i>
                  </div>
                @endif
                <div>
                  <p class="font-bold text-navy text-sm leading-tight">
                    {{ Str::limit($podcast->title, 28) }}
                    @if($podcast->is_verified)
                      <i class="fas fa-certificate text-xs ml-1" style="color:#FF8A00"></i>
                    @endif
                  </p>
                  <p class="text-xs text-slate-400 mt-0.5">{{ $podcast->user->name ?? '—' }}</p>
                </div>
              </div>
            </td>
            <td class="px-4 py-3">
              <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-lg"
                    style="background:#07003B10;color:#07003B">
                {{ $podcast->category->name ?? '—' }}
              </span>
            </td>
            <td class="px-4 py-3 text-center font-bold text-navy">{{ $podcast->total_episodes }}</td>
            <td class="px-4 py-3 text-center text-slate-600 font-medium">
              {{ number_format($podcast->total_followers) }}
            </td>
            <td class="px-4 py-3 text-center font-bold" style="color:#FF8A00">
              {{ number_format($podcast->total_plays) }}
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <div class="flex-1 bg-slate-100 rounded-full h-1.5 max-w-[70px]">
                  <div class="h-1.5 rounded-full"
                       style="width:{{ $pct }}%;background:linear-gradient(90deg,#07003B,#FF8A00)">
                  </div>
                </div>
                <span class="text-xs text-slate-400">{{ $pct }}%</span>
              </div>
            </td>
            <td class="px-4 py-3">
              <div class="flex flex-col gap-1">
                @if($podcast->status === 'published')
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-600">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Published
                  </span>
                @else
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-500">
                    {{ ucfirst($podcast->status) }}
                  </span>
                @endif
                @if($podcast->is_verified)
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold"
                        style="background:#FFF3E0;color:#FF8A00">
                    <i class="fas fa-certificate" style="font-size:8px"></i> Verified
                  </span>
                @endif
              </div>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-center gap-1.5">
                <a href="{{ route('admin.podcast.show', $podcast->id) }}"
                   title="View"
                   class="w-8 h-8 rounded-lg border flex items-center justify-center text-navy border-slate-200 hover:border-navy hover:bg-navy hover:text-white transition-all text-xs">
                  <i class="fas fa-eye"></i>
                </a>
                <button onclick="toggleVerify({{ $podcast->id }})" title="Toggle verify"
                        class="w-8 h-8 rounded-lg border flex items-center justify-center transition-all text-xs
                               {{ $podcast->is_verified ? 'border-orange-300 text-orange-500 bg-orange-50' : 'border-slate-200 text-slate-400 hover:border-orange-300 hover:text-orange-400' }}">
                  <i class="fas fa-certificate"></i>
                </button>
                <button onclick="deletePodcast({{ $podcast->id }})" title="Delete"
                        class="w-8 h-8 rounded-lg border border-red-200 flex items-center justify-center text-red-400 hover:bg-red-500 hover:text-white hover:border-red-500 transition-all text-xs">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="8">
              <div class="flex flex-col items-center justify-center py-16 text-slate-300">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-4"
                     style="background:#07003B08">
                  <i class="fas fa-podcast text-3xl text-slate-200"></i>
                </div>
                <p class="font-semibold text-slate-400">No podcasts yet</p>
                <p class="text-xs text-slate-300 mt-1">Podcasts will appear here once creators upload them</p>
              </div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($podcasts->hasPages())
    <div class="px-6 py-4 border-t border-slate-100">
      {{ $podcasts->links() }}
    </div>
    @endif
  </div>

  {{-- ── Recent Episodes ─────────────────────────────────────────────────── --}}
  <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-orange-50">
          <i class="fas fa-play-circle text-sm" style="color:#FF8A00"></i>
        </div>
        <h2 class="font-black text-navy text-sm">Recent Episodes</h2>
      </div>
      <span class="text-xs font-bold px-3 py-1 rounded-full" style="background:#FFF3E0;color:#FF8A00">
        Last 20
      </span>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 text-slate-400 text-xs uppercase tracking-wider">
            <th class="text-left px-6 py-3 font-semibold">Episode</th>
            <th class="text-left px-4 py-3 font-semibold">Podcast</th>
            <th class="text-center px-4 py-3 font-semibold">Duration</th>
            <th class="text-center px-4 py-3 font-semibold">Plays</th>
            <th class="text-left px-4 py-3 font-semibold">Published</th>
            <th class="text-center px-4 py-3 font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          @forelse($episodes as $ep)
          <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-3">
              <p class="font-bold text-navy text-sm leading-tight">
                {{ Str::limit($ep->title, 48) }}
              </p>
              <p class="text-xs text-slate-400 mt-0.5">Ep. {{ $ep->episode_number }}</p>
            </td>
            <td class="px-4 py-3">
              <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-lg"
                    style="background:#07003B10;color:#07003B">
                {{ Str::limit($ep->podcast->title ?? '—', 22) }}
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex items-center gap-1 text-xs text-slate-500">
                <i class="fas fa-clock text-slate-300"></i>
                {{ $ep->duration_formatted }}
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex items-center gap-1 text-xs font-bold" style="color:#FF8A00">
                <i class="fas fa-headphones text-xs"></i>
                {{ number_format($ep->play_count) }}
              </span>
            </td>
            <td class="px-4 py-3 text-xs text-slate-400">
              {{ $ep->published_at?->diffForHumans() ?? '—' }}
            </td>
            <td class="px-4 py-3">
              <div class="flex justify-center">
                <button onclick="deleteEpisode({{ $ep->id }})"
                        class="w-8 h-8 rounded-lg border border-red-200 flex items-center justify-center text-red-400 hover:bg-red-500 hover:text-white hover:border-red-500 transition-all text-xs">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6">
              <div class="flex flex-col items-center justify-center py-14 text-slate-300">
                <i class="fas fa-play-circle text-4xl mb-3"></i>
                <p class="font-semibold text-slate-400">No episodes yet</p>
              </div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

  </div>

</div>
@endsection

@push('scripts')
<script>
const CSRF = '{{ csrf_token() }}';
function toggleVerify(id) {
    fetch(`/admin/podcast/${id}/verify`, {
        method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}
    }).then(() => location.reload());
}
function deletePodcast(id) {
    if (!confirm('Delete this podcast and all its episodes?')) return;
    fetch(`/admin/podcast/${id}`, {
        method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}
    }).then(() => location.reload());
}
function deleteEpisode(id) {
    if (!confirm('Delete this episode?')) return;
    fetch(`/admin/podcast/episodes/${id}`, {
        method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}
    }).then(() => location.reload());
}
</script>
@endpush
