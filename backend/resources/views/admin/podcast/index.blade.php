@extends('admin.layouts.app')
@section('title', 'Podcast Management')

@push('styles')
<style>
.pod-stat-card {
    background: #fff;
    border-radius: 16px;
    padding: 22px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 12px rgba(7,0,59,.07);
    border: 1px solid #f0f0f8;
    transition: transform .15s, box-shadow .15s;
}
.pod-stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(7,0,59,.12); }
.pod-stat-icon {
    width: 52px; height: 52px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; flex-shrink: 0;
}
.pod-stat-val  { font-size: 26px; font-weight: 800; color: #07003B; line-height: 1; }
.pod-stat-lbl  { font-size: 12px; color: #888; margin-top: 3px; }
.pod-stat-chg  { font-size: 11px; font-weight: 600; margin-top: 4px; }

.pod-table th  { font-size: 11px; text-transform: uppercase; letter-spacing: .6px;
                 color: #999; font-weight: 700; border-bottom: 2px solid #f3f3f3; padding: 10px 14px; }
.pod-table td  { padding: 12px 14px; vertical-align: middle; border-bottom: 1px solid #f8f8f8; }
.pod-table tr:last-child td { border-bottom: none; }
.pod-table tr:hover td { background: #fafbff; }

.pod-cover { width: 44px; height: 44px; border-radius: 10px; object-fit: cover; }
.pod-cover-ph { width: 44px; height: 44px; border-radius: 10px;
                background: linear-gradient(135deg,#07003B,#1a0080);
                display:flex; align-items:center; justify-content:center; }

.status-pill  { display:inline-flex; align-items:center; gap:5px;
                padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.pill-pub     { background:#e8f8f0; color:#1a9c4e; }
.pill-draft   { background:#f3f3f3; color:#888; }
.pill-verified{ background:#fff7e6; color:#FF8A00; }

.pod-section-card { background:#fff; border-radius:16px; border:1px solid #f0f0f8;
                    box-shadow: 0 2px 12px rgba(7,0,59,.06); overflow:hidden; }
.pod-section-hdr  { padding:16px 20px; border-bottom:1px solid #f3f3f3;
                    display:flex; align-items:center; justify-content:space-between; }
.pod-section-hdr h6 { margin:0; font-weight:800; color:#07003B; font-size:14px; }

.action-btn { width:30px; height:30px; border-radius:8px; border:1.5px solid;
              display:inline-flex; align-items:center; justify-content:center;
              cursor:pointer; transition:all .15s; font-size:12px; }
.action-btn:hover { transform:scale(1.1); }

.empty-state { padding:40px 20px; text-align:center; color:#bbb; }
.empty-state i { font-size:36px; margin-bottom:10px; }
.empty-state p { margin:0; font-size:13px; }

.bar-wrap { background:#f3f3f3; border-radius:4px; height:5px; width:80px; overflow:hidden; }
.bar-fill  { height:100%; border-radius:4px; background: linear-gradient(90deg,#07003B,#FF8A00); }
</style>
@endpush

@section('content')
<div class="container-fluid" style="max-width:1400px">

    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-800 mb-1" style="color:#07003B;font-weight:800">
                <i class="fas fa-podcast me-2" style="color:#FF8A00"></i>Podcast Management
            </h4>
            <p class="text-muted mb-0" style="font-size:13px">
                Manage podcasts, episodes, categories and live rooms
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.podcast.categories') }}"
               class="btn btn-sm fw-bold"
               style="background:#07003B;color:#fff;border-radius:10px;padding:8px 16px">
                <i class="fas fa-tags me-1"></i> Categories
            </a>
        </div>
    </div>

    {{-- Stats Row --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="pod-stat-card">
                <div class="pod-stat-icon" style="background:#e8eaff">
                    <i class="fas fa-podcast" style="color:#07003B"></i>
                </div>
                <div>
                    <div class="pod-stat-val">{{ number_format($stats['podcasts']) }}</div>
                    <div class="pod-stat-lbl">Total Podcasts</div>
                    <div class="pod-stat-chg" style="color:#07003B">
                        <i class="fas fa-circle" style="font-size:7px"></i> Active shows
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="pod-stat-card">
                <div class="pod-stat-icon" style="background:#fff3e0">
                    <i class="fas fa-play-circle" style="color:#FF8A00"></i>
                </div>
                <div>
                    <div class="pod-stat-val" style="color:#FF8A00">{{ number_format($stats['episodes']) }}</div>
                    <div class="pod-stat-lbl">Total Episodes</div>
                    <div class="pod-stat-chg" style="color:#FF8A00">
                        <i class="fas fa-circle" style="font-size:7px"></i> Published
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="pod-stat-card">
                <div class="pod-stat-icon" style="background:#e8f8f0">
                    <i class="fas fa-headphones" style="color:#1a9c4e"></i>
                </div>
                <div>
                    <div class="pod-stat-val" style="color:#1a9c4e">{{ number_format($stats['plays']) }}</div>
                    <div class="pod-stat-lbl">Total Plays</div>
                    <div class="pod-stat-chg" style="color:#1a9c4e">
                        <i class="fas fa-circle" style="font-size:7px"></i> All time
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="pod-stat-card">
                <div class="pod-stat-icon" style="background:#fde8e8">
                    <i class="fas fa-broadcast-tower" style="color:#e53e3e"></i>
                </div>
                <div>
                    <div class="pod-stat-val" style="color:#e53e3e">{{ $stats['live_rooms'] }}</div>
                    <div class="pod-stat-lbl">Live Rooms</div>
                    <div class="pod-stat-chg" style="color:#e53e3e">
                        <i class="fas fa-circle fa-beat" style="font-size:7px"></i> Right now
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Podcasts Table --}}
    <div class="pod-section-card mb-4">
        <div class="pod-section-hdr">
            <h6><i class="fas fa-podcast me-2" style="color:#FF8A00"></i>All Podcasts</h6>
            <span class="badge" style="background:#07003B;color:#fff;border-radius:8px;font-size:11px;padding:5px 10px">
                {{ $podcasts->total() }} total
            </span>
        </div>
        <div class="table-responsive">
            <table class="pod-table" style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:#fafbff">
                        <th>#</th>
                        <th>Podcast</th>
                        <th>Category</th>
                        <th>Episodes</th>
                        <th>Followers</th>
                        <th>Plays</th>
                        <th>Popularity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($podcasts as $podcast)
                    @php
                        $maxPlays = $podcasts->max('total_plays') ?: 1;
                        $pct = min(100, round(($podcast->total_plays / $maxPlays) * 100));
                    @endphp
                    <tr>
                        <td style="color:#bbb;font-size:12px">{{ $podcast->id }}</td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                @if($podcast->cover_image)
                                    <img src="{{ asset('storage/'.$podcast->cover_image) }}"
                                         class="pod-cover" alt="">
                                @else
                                    <div class="pod-cover-ph">
                                        <i class="fas fa-podcast" style="color:#FF8A00;font-size:16px"></i>
                                    </div>
                                @endif
                                <div>
                                    <div style="font-weight:700;color:#07003B;font-size:13px">
                                        {{ Str::limit($podcast->title, 30) }}
                                    </div>
                                    <div style="font-size:11px;color:#888">
                                        by {{ $podcast->user->name ?? '—' }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="background:#f0f0f8;color:#07003B;padding:3px 10px;
                                         border-radius:6px;font-size:11px;font-weight:600">
                                {{ $podcast->category->name ?? '—' }}
                            </span>
                        </td>
                        <td style="font-weight:700;color:#07003B">{{ $podcast->total_episodes }}</td>
                        <td style="font-weight:600;color:#555">{{ number_format($podcast->total_followers) }}</td>
                        <td style="font-weight:600;color:#555">{{ number_format($podcast->total_plays) }}</td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <div class="bar-wrap">
                                    <div class="bar-fill" style="width:{{ $pct }}%"></div>
                                </div>
                                <span style="font-size:11px;color:#888">{{ $pct }}%</span>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex;flex-direction:column;gap:4px">
                                @if($podcast->status === 'published')
                                    <span class="status-pill pill-pub">
                                        <i class="fas fa-check-circle" style="font-size:9px"></i> Published
                                    </span>
                                @else
                                    <span class="status-pill pill-draft">
                                        {{ ucfirst($podcast->status) }}
                                    </span>
                                @endif
                                @if($podcast->is_verified)
                                    <span class="status-pill pill-verified">
                                        <i class="fas fa-certificate" style="font-size:9px"></i> Verified
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px">
                                <a href="{{ route('admin.podcast.show', $podcast->id) }}"
                                   class="action-btn" style="border-color:#e0e5ff;color:#07003B"
                                   title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button onclick="toggleVerify({{ $podcast->id }}, this)"
                                        class="action-btn"
                                        style="border-color:{{ $podcast->is_verified ? '#FF8A00' : '#e0e0e0' }};
                                               color:{{ $podcast->is_verified ? '#FF8A00' : '#aaa' }}"
                                        title="{{ $podcast->is_verified ? 'Remove verify' : 'Verify' }}">
                                    <i class="fas fa-certificate"></i>
                                </button>
                                <button onclick="deletePodcast({{ $podcast->id }})"
                                        class="action-btn" style="border-color:#ffe0e0;color:#e53e3e"
                                        title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <i class="fas fa-podcast"></i>
                                <p>No podcasts yet</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($podcasts->hasPages())
        <div style="padding:14px 20px;border-top:1px solid #f3f3f3">
            {{ $podcasts->links() }}
        </div>
        @endif
    </div>

    {{-- Recent Episodes --}}
    <div class="pod-section-card">
        <div class="pod-section-hdr">
            <h6><i class="fas fa-play-circle me-2" style="color:#FF8A00"></i>Recent Episodes</h6>
            <span class="badge" style="background:#fff3e0;color:#FF8A00;border-radius:8px;font-size:11px;padding:5px 10px">
                Last 20
            </span>
        </div>
        <div class="table-responsive">
            <table class="pod-table" style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="background:#fafbff">
                        <th>#</th>
                        <th>Episode</th>
                        <th>Podcast</th>
                        <th>Duration</th>
                        <th>Plays</th>
                        <th>Published</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($episodes as $episode)
                    <tr>
                        <td style="color:#bbb;font-size:12px">{{ $episode->id }}</td>
                        <td>
                            <div style="font-weight:700;color:#07003B;font-size:13px">
                                {{ Str::limit($episode->title, 45) }}
                            </div>
                            <div style="font-size:11px;color:#aaa">Ep. {{ $episode->episode_number }}</div>
                        </td>
                        <td>
                            <span style="background:#f0f0f8;color:#07003B;padding:3px 10px;
                                         border-radius:6px;font-size:11px;font-weight:600">
                                {{ Str::limit($episode->podcast->title ?? '—', 22) }}
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:5px;color:#555;font-size:12px">
                                <i class="fas fa-clock" style="color:#ddd;font-size:10px"></i>
                                {{ $episode->duration_formatted }}
                            </div>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:5px;font-weight:600;color:#07003B;font-size:13px">
                                <i class="fas fa-headphones" style="color:#FF8A00;font-size:10px"></i>
                                {{ number_format($episode->play_count) }}
                            </div>
                        </td>
                        <td style="color:#888;font-size:12px">
                            {{ $episode->published_at?->diffForHumans() ?? '—' }}
                        </td>
                        <td>
                            <button onclick="deleteEpisode({{ $episode->id }})"
                                    class="action-btn" style="border-color:#ffe0e0;color:#e53e3e"
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="fas fa-play-circle"></i>
                                <p>No episodes yet</p>
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

function toggleVerify(id, btn) {
    fetch(`/admin/podcast/${id}/verify`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'}
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
    });
}

function deletePodcast(id) {
    if (!confirm('Delete this podcast and all its episodes?')) return;
    fetch(`/admin/podcast/${id}`, {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'}
    }).then(() => location.reload());
}

function deleteEpisode(id) {
    if (!confirm('Delete this episode?')) return;
    fetch(`/admin/podcast/episodes/${id}`, {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json'}
    }).then(() => location.reload());
}
</script>
@endpush
