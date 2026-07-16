@extends('admin.layouts.app')
@section('title', 'Podcast Categories')

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('admin.podcast.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
        <h4 class="mb-0 fw-bold">Podcast Categories</h4>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Icon</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Color</th>
                            <th>Podcasts</th>
                            <th>Sort</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $cat)
                        <tr>
                            <td>{{ $cat->id }}</td>
                            <td>
                                <div class="rounded d-flex align-items-center justify-content-center"
                                     style="width:32px;height:32px;background:{{ $cat->color ?? '#FF8A00' }}20">
                                    <i class="fas fa-{{ $cat->icon ?? 'podcast' }}"
                                       style="color:{{ $cat->color ?? '#FF8A00' }}"></i>
                                </div>
                            </td>
                            <td><span class="fw-semibold">{{ $cat->name }}</span></td>
                            <td><code>{{ $cat->slug }}</code></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle" style="width:16px;height:16px;background:{{ $cat->color ?? '#FF8A00' }}"></div>
                                    <code>{{ $cat->color ?? '#FF8A00' }}</code>
                                </div>
                            </td>
                            <td>{{ $cat->podcast_count ?? 0 }}</td>
                            <td>{{ $cat->sort_order }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No categories found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
