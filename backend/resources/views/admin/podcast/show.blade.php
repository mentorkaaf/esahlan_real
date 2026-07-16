@extends('admin.layouts.app')
@section('title', 'Podcast: ' . $podcast->title)

@section('content')
<div class="container-fluid">

    {{-- Header --}}
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('admin.podcast.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
        <h4 class="mb-0 fw-bold">{{ $podcast->title }}</h4>
        @if($podcast->is_verified)
            <span class="badge bg-warning text-dark"><i class="fas fa-check me-1"></i>Verified</span>
        @endif
    </div>

    <div class="row g-4">
        {{-- Left: cover + info --}}
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    @if($podcast->cover_image)
                        <img src="{{ asset('storage/'.$podcast->cover_image) }}"
                             class="rounded mb-3" style="width:160px;height:160px;object-fit:cover">
                    @else
                        <div class="rounded d-flex align-items-center justify-content-center mx-auto mb-3"
                             style="width:160px;height:160px;background:#07003B">
                            <i class="fas fa-podcast fa-3x text-warning"></i>
                        </div>
                    @endif
                    <h5 class="fw-bold">{{ $podcast->title }}</h5>
                    <p class="text-muted small">By {{ $podcast->user->name ?? '—' }}</p>
                    <span class="badge bg-light text-dark">{{ $podcast->category->name ?? '—' }}</span>
                    <hr>
                    <div class="row text-center g-2 mt-1">
                        <div class="col-4">
                            <div class="fw-bold text-primary">{{ $podcast->total_episodes }}</div>
                            <div class="text-muted" style="font-size:10px">Episodes</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold" style="color:#FF8A00">{{ number_format($podcast->total_followers) }}</div>
                            <div class="text-muted" style="font-size:10px">Followers</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold text-success">{{ number_format($podcast->total_plays) }}</div>
                            <div class="text-muted" style="font-size:10px">Plays</div>
                        </div>
                    </div>
                    <hr>
                    <div class="d-grid gap-2 mt-2">
                        <button class="btn btn-outline-warning btn-sm" onclick="toggleVerify({{ $podcast->id }})">
                            <i class="fas fa-certificate me-1"></i>
                            {{ $podcast->is_verified ? 'Remove Verification' : 'Verify Podcast' }}
                        </button>
                        <button class="btn btn-outline-danger btn-sm" onclick="deletePodcast({{ $podcast->id }})">
                            <i class="fas fa-trash me-1"></i>Delete Podcast
                        </button>
                    </div>
                </div>
            </div>

            @if($podcast->description)
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-2">About</h6>
                    <p class="text-muted small mb-0">{{ $podcast->description }}</p>
                </div>
            </div>
            @endif
        </div>

        {{-- Right: episodes --}}
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-play-circle me-2"></i>Episodes</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Episode</th>
                                    <th>Duration</th>
                                    <th>Plays</th>
                                    <th>Published</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($episodes as $ep)
                                <tr>
                                    <td>{{ $ep->episode_number }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ Str::limit($ep->title, 50) }}</div>
                                        @if($ep->description)
                                            <small class="text-muted">{{ Str::limit($ep->description, 60) }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $ep->duration_formatted }}</td>
                                    <td>{{ number_format($ep->play_count) }}</td>
                                    <td>{{ $ep->published_at?->diffForHumans() ?? '—' }}</td>
                                    <td>
                                        <button class="btn btn-outline-danger btn-sm"
                                                onclick="deleteEpisode({{ $ep->id }})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No episodes</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3">{{ $episodes->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleVerify(id) {
    if (!confirm('Toggle verification?')) return;
    fetch(`/admin/podcast/${id}/verify`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
    }).then(() => location.reload());
}
function deletePodcast(id) {
    if (!confirm('Delete this podcast and all its episodes?')) return;
    fetch(`/admin/podcast/${id}`, {
        method: 'DELETE',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
    }).then(() => window.location = '{{ route("admin.podcast.index") }}');
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
