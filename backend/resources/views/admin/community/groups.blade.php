@extends('admin.layouts.app')
@section('title', 'Community Groups')
@section('content')
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0 fw-bold">Community Groups</h2>
      <p class="text-muted mb-0">Manage all community groups</p>
    </div>
    <a href="{{ route('admin.community.index') }}" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i> Back
    </a>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Group</th>
              <th>Owner</th>
              <th>Category</th>
              <th>Privacy</th>
              <th>Members</th>
              <th>Posts</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($groups as $group)
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  @if($group->cover_photo)
                    <img src="{{ $group->cover_photo }}" class="rounded" width="40" height="32" style="object-fit:cover">
                  @else
                    <div class="rounded bg-info bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:32px">
                      <i class="fas fa-layer-group text-info"></i>
                    </div>
                  @endif
                  <div>
                    <div class="fw-semibold small">{{ $group->name }}</div>
                    <div class="text-muted" style="font-size:11px">{{ '@'.$group->slug }}</div>
                  </div>
                </div>
              </td>
              <td class="small">{{ $group->owner?->name ?? '—' }}</td>
              <td class="small">{{ $group->category ?? '—' }}</td>
              <td>
                @php $pc = ['public'=>'success','private'=>'warning','secret'=>'danger'][$group->privacy ?? 'public'] @endphp
                <span class="badge bg-{{ $pc }} bg-opacity-15 text-{{ $pc }}">{{ ucfirst($group->privacy) }}</span>
              </td>
              <td class="fw-semibold">{{ number_format($group->members_count) }}</td>
              <td class="fw-semibold">{{ number_format($group->posts_count) }}</td>
              <td class="small text-muted">{{ $group->created_at->format('d M Y') }}</td>
              <td>
                <form method="POST" action="{{ route('admin.community.groups.delete', $group->id) }}" onsubmit="return confirm('Delete this group?')">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger py-0"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center text-muted py-5">No groups yet</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if($groups->hasPages())
    <div class="card-footer bg-white border-0">{{ $groups->withQueryString()->links() }}</div>
    @endif
  </div>
</div>
@endsection
