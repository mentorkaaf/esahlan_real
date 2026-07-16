@extends('admin.layouts.app')
@section('title', 'Community Dashboard')

@section('content')
<div class="p-6 space-y-6">

  {{-- ── Header ──────────────────────────────────────────────────────────────── --}}
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-black text-navy flex items-center gap-2.5">
        <span class="w-9 h-9 rounded-xl flex items-center justify-content-center shrink-0"
              style="background:linear-gradient(135deg,#07003B,#FF8A00)">
          <i class="fas fa-users text-white text-sm"></i>
        </span>
        Community Monitor
      </h1>
      <p class="text-slate-400 text-xs mt-1">Real-time overview · {{ now()->format('D, d M Y · H:i') }}</p>
    </div>
    <div class="flex gap-2 flex-wrap">
      <a href="{{ route('admin.community.posts') }}"
         class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-white"
         style="background:#07003B">
        <i class="fas fa-file-alt text-xs"></i> Posts
      </a>
      <a href="{{ route('admin.community.reports') }}"
         class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold"
         style="background:#FF8A0015;color:#FF8A00">
        <i class="fas fa-flag text-xs"></i> Reports
        @if($stats['reports_pending'] > 0)
          <span class="bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5 leading-none font-black">
            {{ $stats['reports_pending'] }}
          </span>
        @endif
      </a>
      <a href="{{ route('admin.community.moderation') }}"
         class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold bg-slate-100 text-slate-600">
        <i class="fas fa-shield-alt text-xs"></i> Moderation
      </a>
    </div>
  </div>

  {{-- ── KPI Cards ────────────────────────────────────────────────────────────── --}}
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 hover:-translate-y-0.5 transition-transform">
      <div class="flex items-start justify-between mb-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-content-center" style="background:#07003B12">
          <i class="fas fa-users text-navy"></i>
        </div>
        <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600">
          +{{ $stats['members_today'] }} today
        </span>
      </div>
      <p class="text-3xl font-black text-navy">{{ number_format($stats['members']) }}</p>
      <p class="text-xs text-slate-400 mt-0.5 font-medium">Total Members</p>
      <div class="mt-3 pt-3 border-t border-slate-50 text-xs text-slate-400 flex items-center gap-1">
        <i class="fas fa-calendar-week"></i> +{{ $stats['members_week'] }} this week
      </div>
    </div>

    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 hover:-translate-y-0.5 transition-transform">
      <div class="flex items-start justify-between mb-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-content-center" style="background:#FF8A0012">
          <i class="fas fa-file-alt" style="color:#FF8A00"></i>
        </div>
        <span class="text-xs font-bold px-2 py-0.5 rounded-full" style="background:#FFF3E0;color:#FF8A00">
          +{{ $stats['posts_today'] }} today
        </span>
      </div>
      <p class="text-3xl font-black" style="color:#FF8A00">{{ number_format($stats['posts']) }}</p>
      <p class="text-xs text-slate-400 mt-0.5 font-medium">Total Posts</p>
      <div class="mt-3 pt-3 border-t border-slate-50 text-xs text-slate-400 flex items-center gap-1">
        <i class="fas fa-calendar-week"></i> +{{ $stats['posts_week'] }} this week
      </div>
    </div>

    <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 hover:-translate-y-0.5 transition-transform">
      <div class="flex items-start justify-between mb-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-content-center bg-purple-50">
          <i class="fas fa-heart text-purple-500"></i>
        </div>
        <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-purple-50 text-purple-600">
          {{ number_format($stats['total_comments']) }} cmts
        </span>
      </div>
      <p class="text-3xl font-black text-purple-600">{{ number_format($stats['total_reactions']) }}</p>
      <p class="text-xs text-slate-400 mt-0.5 font-medium">Total Reactions</p>
      <div class="mt-3 pt-3 border-t border-slate-50 text-xs text-slate-400 flex items-center gap-1">
        <i class="fas fa-eye"></i> {{ number_format($stats['total_views']) }} total views
      </div>
    </div>

    <div class="rounded-2xl p-5 shadow-sm border hover:-translate-y-0.5 transition-transform
                {{ $stats['reports_pending'] > 0 ? 'bg-red-50 border-red-100' : 'bg-white border-slate-100' }}">
      <div class="flex items-start justify-between mb-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-content-center
                    {{ $stats['reports_pending'] > 0 ? 'bg-red-100' : 'bg-slate-50' }}">
          <i class="fas fa-flag {{ $stats['reports_pending'] > 0 ? 'text-red-500' : 'text-slate-400' }}"></i>
        </div>
        @if($stats['reports_pending'] > 0)
          <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-600 animate-pulse">Needs action</span>
        @else
          <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600">All clear</span>
        @endif
      </div>
      <p class="text-3xl font-black {{ $stats['reports_pending'] > 0 ? 'text-red-500' : 'text-slate-300' }}">
        {{ $stats['reports_pending'] }}
      </p>
      <p class="text-xs mt-0.5 font-medium {{ $stats['reports_pending'] > 0 ? 'text-red-400' : 'text-slate-400' }}">
        Pending Reports
      </p>
      <div class="mt-3 pt-3 border-t {{ $stats['reports_pending'] > 0 ? 'border-red-100' : 'border-slate-50' }}">
        <a href="{{ route('admin.community.reports') }}"
           class="text-xs font-bold flex items-center gap-1 hover:underline
                  {{ $stats['reports_pending'] > 0 ? 'text-red-500' : 'text-slate-400' }}">
          Review reports <i class="fas fa-arrow-right text-xs"></i>
        </a>
      </div>
    </div>

  </div>

  {{-- ── Chart + Breakdown + Top Users ───────────────────────────────────────── --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    {{-- Bar chart --}}
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
      <div class="flex items-center justify-between mb-5">
        <div>
          <h3 class="font-black text-navy text-sm">Post Activity</h3>
          <p class="text-xs text-slate-400">Posts per day — last 7 days</p>
        </div>
      </div>
      @php
        $days   = collect(range(6,0))->map(fn($i) => now()->subDays($i)->format('Y-m-d'));
        $maxVal = $dailyPosts->max() ?: 1;
      @endphp
      <div class="flex items-end gap-2" style="height:120px">
        @foreach($days as $day)
          @php $cnt = $dailyPosts[$day] ?? 0; $pct = max(4, round(($cnt/$maxVal)*100)); @endphp
          <div class="flex-1 flex flex-col items-center gap-1" style="height:100%">
            <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;width:100%">
              <div style="height:{{ $pct }}%;background:linear-gradient(180deg,#FF8A00,#07003B);border-radius:6px 6px 0 0;min-height:4px;width:100%"
                   title="{{ $cnt }} posts on {{ $day }}"></div>
            </div>
            <span class="text-xs text-slate-400" style="font-size:10px">{{ \Carbon\Carbon::parse($day)->format('D') }}</span>
          </div>
        @endforeach
      </div>

      <div class="mt-5 pt-4 border-t border-slate-50 grid grid-cols-3 gap-4 text-center">
        <div>
          <p class="text-xl font-black text-navy">{{ $stats['stories_today'] }}</p>
          <p class="text-xs text-slate-400">Stories today</p>
        </div>
        <div>
          <p class="text-xl font-black" style="color:#FF8A00">{{ $stats['messages_today'] }}</p>
          <p class="text-xs text-slate-400">Messages today</p>
        </div>
        <div>
          <p class="text-xl font-black text-purple-500">{{ $stats['active_chats'] }}</p>
          <p class="text-xs text-slate-400">Active chats 24h</p>
        </div>
      </div>
    </div>

    <div class="space-y-4">
      {{-- Content breakdown --}}
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <h3 class="font-black text-navy text-sm mb-4">Content Breakdown</h3>
        @php
          $typeColors = ['video'=>'#FF8A00','image'=>'#7C3AED','text'=>'#07003B','reel'=>'#10b981'];
          $typeIcons  = ['video'=>'fa-video','image'=>'fa-image','text'=>'fa-align-left','reel'=>'fa-film'];
          $total = $postTypes->sum() ?: 1;
        @endphp
        <div class="space-y-3">
          @forelse($postTypes as $type => $cnt)
            @php $pct = round(($cnt/$total)*100); $col = $typeColors[$type] ?? '#ccc'; @endphp
            <div>
              <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                  <i class="fas {{ $typeIcons[$type] ?? 'fa-file' }} text-xs" style="color:{{ $col }}"></i>
                  {{ ucfirst($type) }}
                </span>
                <span class="text-xs font-bold text-navy">{{ $cnt }} <span class="text-slate-300">({{ $pct }}%)</span></span>
              </div>
              <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full rounded-full" style="width:{{ $pct }}%;background:{{ $col }}"></div>
              </div>
            </div>
          @empty
            <p class="text-xs text-slate-300 text-center py-2">No posts yet</p>
          @endforelse
        </div>
      </div>

      {{-- Top Creators --}}
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-black text-navy text-sm">Top Creators</h3>
          <a href="{{ route('admin.community.users') }}" class="text-xs font-bold" style="color:#FF8A00">All →</a>
        </div>
        <div class="space-y-3">
          @forelse($topUsers as $i => $profile)
          <div class="flex items-center gap-2.5">
            <span class="text-xs font-black w-4 text-slate-300">{{ $i+1 }}</span>
            <div class="w-7 h-7 rounded-full flex items-center justify-content-center text-white text-xs font-black shrink-0"
                 style="background:linear-gradient(135deg,#07003B,#FF8A00)">
              {{ strtoupper(substr($profile->user->name ?? 'U',0,1)) }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-bold text-navy truncate">{{ $profile->user->name ?? '—' }}</p>
              <p class="text-xs text-slate-400">{{ number_format($profile->followers_count) }} followers</p>
            </div>
            @if($profile->is_verified)<i class="fas fa-certificate text-xs" style="color:#FF8A00"></i>@endif
          </div>
          @empty
          <p class="text-xs text-slate-300 text-center py-3">No users yet</p>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  {{-- ── Recent Posts + Reports + Quick Actions ──────────────────────────────── --}}
  <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

    {{-- Recent Posts feed --}}
    <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-50">
        <h3 class="font-black text-navy text-sm flex items-center gap-2">
          <i class="fas fa-stream text-xs" style="color:#FF8A00"></i> Recent Posts
        </h3>
        <a href="{{ route('admin.community.posts') }}" class="text-xs font-bold" style="color:#FF8A00">View all →</a>
      </div>
      <div class="divide-y divide-slate-50">
        @forelse($recentPosts as $post)
        <div class="px-6 py-3.5 hover:bg-slate-50 transition-colors" id="post-{{ $post->id }}">
          <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-full flex items-center justify-content-center text-white text-xs font-black shrink-0"
                 style="background:linear-gradient(135deg,#07003B,#FF8A00)">
              {{ strtoupper(substr($post->user->name ?? 'U',0,1)) }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs font-bold text-navy">{{ $post->user->name ?? '—' }}</span>
                @php $col = ['video'=>'#FF8A00','image'=>'#7C3AED','reel'=>'#10b981'][$post->type] ?? '#07003B'; @endphp
                <span class="text-xs font-bold px-1.5 py-0.5 rounded"
                      style="background:{{ $col }}18;color:{{ $col }}">{{ $post->type }}</span>
                <span class="text-xs text-slate-300 ml-auto">{{ $post->created_at->diffForHumans() }}</span>
              </div>
              @if($post->content)
              <p class="text-xs text-slate-500 mt-0.5 truncate">{{ Str::limit($post->content, 70) }}</p>
              @endif
              <div class="flex items-center gap-3 mt-1.5 text-xs text-slate-400">
                <span><i class="fas fa-heart text-red-400 mr-0.5"></i>{{ $post->likes_count }}</span>
                <span><i class="fas fa-comment text-blue-400 mr-0.5"></i>{{ $post->comments_count }}</span>
                <span><i class="fas fa-eye text-slate-300 mr-0.5"></i>{{ number_format($post->views_count ?? 0) }}</span>
              </div>
            </div>
            <button onclick="deletePost({{ $post->id }})"
                    class="w-7 h-7 rounded-lg border border-red-100 flex items-center justify-content-center text-red-300 hover:bg-red-500 hover:text-white hover:border-red-500 transition-all text-xs shrink-0">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </div>
        @empty
        <div class="flex flex-col items-center py-12 text-slate-300">
          <i class="fas fa-file-alt text-3xl mb-2"></i>
          <p class="text-sm font-semibold text-slate-400">No posts yet</p>
        </div>
        @endforelse
      </div>
    </div>

    {{-- Right: Reports + Quick Actions --}}
    <div class="lg:col-span-2 space-y-4">

      <div class="bg-white rounded-2xl shadow-sm overflow-hidden border
                  {{ $stats['reports_pending'] > 0 ? 'border-red-100' : 'border-slate-100' }}">
        <div class="flex items-center justify-between px-5 py-4 border-b
                    {{ $stats['reports_pending'] > 0 ? 'border-red-50 bg-red-50' : 'border-slate-50' }}">
          <h3 class="font-black text-sm flex items-center gap-2
                     {{ $stats['reports_pending'] > 0 ? 'text-red-600' : 'text-navy' }}">
            <i class="fas fa-flag text-xs"></i> Pending Reports
            @if($stats['reports_pending'] > 0)
              <span class="w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-content-center font-black">
                {{ $stats['reports_pending'] }}
              </span>
            @endif
          </h3>
          <a href="{{ route('admin.community.reports') }}"
             class="text-xs font-bold {{ $stats['reports_pending'] > 0 ? 'text-red-500' : 'text-slate-400' }}">
            All →
          </a>
        </div>
        <div class="divide-y divide-slate-50">
          @forelse($pendingReports as $report)
          <div class="px-5 py-3 hover:bg-slate-50 transition-colors">
            <div class="flex items-center gap-2.5">
              <div class="w-7 h-7 rounded-full bg-red-50 flex items-center justify-content-center text-red-400 text-xs shrink-0">
                <i class="fas fa-flag"></i>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-navy truncate">{{ $report->reporter->name ?? 'Unknown' }}</p>
                <p class="text-xs text-slate-400 truncate">{{ Str::limit($report->reason ?? $report->type ?? '—', 32) }}</p>
              </div>
              <span class="text-xs text-slate-300 shrink-0">{{ $report->created_at->diffForHumans(null,true) }}</span>
            </div>
          </div>
          @empty
          <div class="flex flex-col items-center py-10">
            <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-content-center mb-2">
              <i class="fas fa-check text-emerald-400 text-lg"></i>
            </div>
            <p class="text-sm font-semibold text-emerald-500">All clear!</p>
            <p class="text-xs text-slate-400 mt-0.5">No pending reports</p>
          </div>
          @endforelse
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <h3 class="font-black text-navy text-sm mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 gap-2">
          @foreach([
            ['route'=>'admin.community.posts',      'icon'=>'fa-file-alt',    'label'=>'Posts',      'col'=>'#07003B'],
            ['route'=>'admin.community.users',      'icon'=>'fa-user-shield', 'label'=>'Users',      'col'=>'#7C3AED'],
            ['route'=>'admin.community.moderation', 'icon'=>'fa-shield-alt',  'label'=>'Moderation', 'col'=>'#FF8A00'],
            ['route'=>'admin.community.algorithm',  'icon'=>'fa-brain',       'label'=>'Algorithm',  'col'=>'#10b981'],
            ['route'=>'admin.community.groups',     'icon'=>'fa-layer-group', 'label'=>'Groups',     'col'=>'#3b82f6'],
            ['route'=>'admin.community-ads.index',  'icon'=>'fa-bullhorn',    'label'=>'Ads',        'col'=>'#f59e0b'],
          ] as $a)
          <a href="{{ route($a['route']) }}"
             class="flex items-center gap-2 px-3 py-2.5 rounded-xl border border-slate-100 hover:border-slate-200 hover:-translate-y-0.5 transition-all group">
            <div class="w-7 h-7 rounded-lg flex items-center justify-content-center shrink-0"
                 style="background:{{ $a['col'] }}12">
              <i class="fas {{ $a['icon'] }} text-xs" style="color:{{ $a['col'] }}"></i>
            </div>
            <span class="text-xs font-bold text-slate-600 group-hover:text-navy transition-colors">{{ $a['label'] }}</span>
          </a>
          @endforeach
        </div>
      </div>

    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
const CSRF = '{{ csrf_token() }}';
function deletePost(id) {
    if (!confirm('Permanently delete this post?')) return;
    fetch(`/admin/community/posts/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    }).then(r => {
        if (r.ok) {
            const el = document.getElementById('post-' + id);
            if (el) el.remove();
        }
    });
}
</script>
@endpush
