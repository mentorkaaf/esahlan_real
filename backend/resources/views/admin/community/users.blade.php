@extends('admin.layouts.app')
@section('title', 'Community Users')
@section('content')
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0 fw-bold">Community Users</h2>
      <p class="text-muted mb-0">Verify users and manage community profiles</p>
    </div>
    <a href="{{ route('admin.community.index') }}" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i> Back
    </a>
  </div>

  {{-- Filter --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
      <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-5">
          <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search users...">
        </div>
        <div class="col-md-3">
          <select name="verified" class="form-select">
            <option value="">All Users</option>
            <option value="1" @selected(request('verified')==='1')>Verified Only</option>
            <option value="0" @selected(request('verified')==='0')>Unverified Only</option>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>User</th>
              <th>Username</th>
              <th>Bio</th>
              <th>Followers</th>
              <th>Posts</th>
              <th>Verified</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($users as $profile)
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  @if($profile->user?->profile_photo_path)
                    <img src="{{ $profile->user->profile_photo_path }}" class="rounded-circle" width="38" height="38" style="object-fit:cover">
                  @else
                    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:38px;height:38px;font-weight:700;color:#140465">
                      {{ strtoupper(substr($profile->user?->name ?? '?', 0, 1)) }}
                    </div>
                  @endif
                  <div>
                    <div class="fw-semibold small">{{ $profile->user?->name }}</div>
                    <div class="text-muted" style="font-size:11px">{{ $profile->user?->email }}</div>
                  </div>
                </div>
              </td>
              <td class="small text-muted">{{ $profile->username ? '@'.$profile->username : '—' }}</td>
              <td class="small text-muted" style="max-width:160px">
                <span class="text-truncate d-block">{{ Str::limit($profile->bio, 50) ?? '—' }}</span>
              </td>
              <td class="fw-semibold">{{ number_format($profile->followers_count) }}</td>
              <td class="fw-semibold">{{ number_format($profile->posts_count) }}</td>
              <td>
                @if($profile->is_verified)
                  <span class="badge bg-primary"><i class="fas fa-check me-1"></i>Verified</span>
                @else
                  <span class="badge bg-light text-muted border">Unverified</span>
                @endif
              </td>
              <td class="small text-muted">{{ $profile->created_at->format('d M Y') }}</td>
              <td>
                <form method="POST" action="{{ route('admin.community.users.verify', $profile->id) }}">
                  @csrf
                  <button class="btn btn-sm {{ $profile->is_verified ? 'btn-outline-secondary' : 'btn-outline-primary' }} py-0">
                    {{ $profile->is_verified ? 'Unverify' : 'Verify' }}
                  </button>
                </form>
              </td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center text-muted py-5">No community users found</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($users->hasPages())
    <div class="card-footer bg-white border-0">{{ $users->withQueryString()->links() }}</div>
    @endif
  </div>
</div>
@endsection
