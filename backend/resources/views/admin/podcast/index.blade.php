@extends('admin.layouts.app')
@section('title', 'Podcast Management')

@section('content')
<div class="container-fluid">

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold text-primary">{{ $stats['podcasts'] }}</div>
                    <div class="text-muted small">Total Podcasts</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold" style="color:#FF8A00">{{ $stats['episodes'] }}</div>
                    <div class="text-muted small">Total Episodes</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold text-success">{{ $stats['plays'] }}</div>
                    <div class="text-muted small">Total Plays</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold text-danger">{{ $stats['live_rooms'] }}</div>
                    <div class="text-muted small">Live Rooms</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Podcasts table --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0"><i class="fas fa-podcast me-2"></i>Podcasts</h5>
            <a href="{{ route('admin.podcast.categories') }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-tags me-1"></i>Categories
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Podcast</th>
                            <th>Category</th>
                            <th>Episodes</th>
                            <th>Followers</th>
                            <th>Plays</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($podcasts as $podcast)
                        <tr>
                            <td>{{ $podcast->id }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if($podcast->cover_image)
                                    <img src="{{ asset('storage/'.$podcast->cover_image) }}"
                                         width="40" height="40" class="rounded" style="object-fit:cover">
                                    @else
                                    <div class="rounded d-flex align-items-center justify-content-center"
                                         style="width:40px;height:40px;background:#07003B">
                                        <i class="fas fa-podcast text-warning"></i>
                                    </div>
                                    @endif
                                    <div>
                                        <div class="fw-semibold">{{ $podcast->title }}</div>
                                        <small class="text-muted">{{ $podcast->user->name ?? '—' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark">{{ $podcast->category->name ?? '—' }}</span></td>
                            <td>{{ $podcast->total_episodes }}</td>
                            <td>{{ number_format($podcast->total_followers) }}</td>
                            <td>{{ number_format($podcast->total_plays) }}</td>
                            <td>
                                @if($podcast->status === 'published')
                                    <span class="badge bg-success">Published</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($podcast->status) }}</span>
                                @endif
                                @if($podcast->is_verified)
                                    <span class="badge bg-warning text-dark ms-1"><i class="fas fa-check"></i> Verified</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.podcast.show', $podcast->id) }}"
                                       class="btn btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button class="btn btn-outline-warning"
                                            onclick="toggleVerify({{ $podcast->id }})">
                                        <i class="fas fa-certificate"></i>
                                    </button>
                                    <button class="btn btn-outline-danger"
                                            onclick="deletePodcast({{ $podcast->id }})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No podcasts yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $podcasts->links() }}</div>
        </div>
    </div>

    {{-- Episodes table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-play-circle me-2"></i>Recent Episodes</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
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
                            <td>{{ $episode->id }}</td>
                            <td>
                                <div class="fw-semibold">{{ Str::limit($episode->title, 40) }}</div>
                                <small class="text-muted">Ep. {{ $episode->episode_number }}</small>
                            </td>
                            <td>{{ $episode->podcast->title ?? '—' }}</td>
                            <td>{{ $episode->duration_formatted }}</td>
                            <td>{{ number_format($episode->play_count) }}</td>
                            <td>{{ $episode->published_at?->diffForHumans() ?? '—' }}</td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-danger"
                                            onclick="deleteEpisode({{ $episode->id }})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No episodes yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function toggleVerify(id) {
    if (!confirm('Toggle verification for this podcast?')) return;
    fetch(`/admin/podcast/${id}/verify`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else alert(d.message || 'Error');
    });
}

function deletePodcast(id) {
    if (!confirm('Delete this podcast and all its episodes?')) return;
    fetch(`/admin/podcast/${id}`, {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
    }).then(() => location.reload());
}

function deleteEpisode(id) {
    if (!confirm('Delete this episode?')) return;
    fetch(`/admin/podcast/episodes/${id}`, {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
    }).then(() => location.reload());
}
</script>
@endpush
