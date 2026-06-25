@extends('admin.layouts.app')
@section('title', 'Community Posts')
@section('content')

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-file-alt" style="color:#FF8A00"></i> Community Posts</h2>
        <ol class="breadcrumb"><li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li><a href="{{ route('admin.community.index') }}">Community</a></li><li>Posts</li></ol>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

{{-- Filters --}}
<div class="card" style="margin-bottom:16px;padding:16px;">
    <form method="GET" style="display:flex;gap:10px;align-items:end;">
        <div style="flex:2"><label class="form-label" style="font-size:12px;">Search</label><input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search posts..."></div>
        <div style="flex:1"><label class="form-label" style="font-size:12px;">Type</label><select name="type" class="form-control"><option value="">All</option>@foreach(['text','image','video','reel','poll','share'] as $t)<option value="{{ $t }}" @selected(request('type')===$t)>{{ ucfirst($t) }}</option>@endforeach</select></div>
        <div><button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button></div>
    </form>
</div>

<form action="{{ route('admin.community.posts.bulk-delete') }}" method="POST" id="bulkPostsForm" style="display:none;">@csrf<div id="bulkPostIds"></div></form>
<div style="display:flex;justify-content:flex-end;margin-bottom:10px;">
    <button type="button" class="btn btn-danger" onclick="submitBulkDeletePosts()" style="display:none;" id="bulkPostDeleteBtn"><i class="fas fa-trash"></i> Delete Selected</button>
</div>
    <div class="card">
        <div class="table-wrap"><table>
            <thead><tr>
                <th><input type="checkbox" id="selectAllPosts" onchange="document.querySelectorAll('.post-check').forEach(c=>{c.checked=this.checked});document.getElementById('bulkPostDeleteBtn').style.display=this.checked?'block':'none'"></th>
                <th>#</th><th>Author</th><th>Media</th><th>Content</th><th>Type</th><th>Privacy</th><th>Likes</th><th>Comments</th><th>Views</th><th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody>
                @forelse($posts as $post)
                <tr>
                    <td><input type="checkbox" name="ids[]" value="{{ $post->id }}" class="post-check" onchange="document.getElementById('bulkPostDeleteBtn').style.display=document.querySelectorAll('.post-check:checked').length?'block':'none'"></td>
                    <td style="font-weight:700;">{{ $post->id }}</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:32px;height:32px;border-radius:50%;background:#f0f2f5;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#FF8A00;">{{ strtoupper(substr($post->user?->name ?? '?', 0, 1)) }}</div>
                            <div><div style="font-weight:600;font-size:13px;">{{ $post->user?->name ?? '—' }}</div></div>
                        </div>
                    </td>
                    <td>
                        @if($post->media->count() > 0)
                            @php $m = $post->media->first(); @endphp
                            @if($m->type === 'video')
                                <div onclick="openMediaModal('{{ cdn_url($m->url) }}', 'video')" style="width:50px;height:50px;border-radius:8px;background:#1a1b2e;display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;">
                                    <i class="fas fa-play" style="color:#fff;font-size:14px;"></i>
                                    @if($post->media->count() > 1)<span style="position:absolute;top:2px;right:2px;background:#FF8A00;color:#fff;border-radius:4px;font-size:9px;padding:1px 4px;">+{{ $post->media->count() - 1 }}</span>@endif
                                </div>
                            @else
                                <div style="position:relative;cursor:pointer;" onclick="openMediaModal('{{ cdn_url($m->url) }}', 'image')">
                                    <img src="{{ cdn_url($m->url) }}" style="width:50px;height:50px;border-radius:8px;object-fit:cover;" onerror="this.style.display='none'">
                                    @if($post->media->count() > 1)<span style="position:absolute;top:2px;right:2px;background:#FF8A00;color:#fff;border-radius:4px;font-size:9px;padding:1px 4px;">+{{ $post->media->count() - 1 }}</span>@endif
                                </div>
                            @endif
                        @else
                            <span style="color:#ccc;">—</span>
                        @endif
                    </td>
                    <td style="max-width:250px;"><div style="font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $post->content ?? '—' }}</div></td>
                    <td><span class="badge badge-info">{{ $post->type }}</span></td>
                    <td><span class="badge {{ $post->privacy === 'public' ? 'badge-success' : 'badge-secondary' }}">{{ $post->privacy }}</span></td>
                    <td style="font-weight:700;">{{ $post->likes_count }}</td>
                    <td>{{ $post->comments_count }}</td>
                    <td>{{ $post->views_count }}</td>
                    <td style="font-size:12px;color:#8A8A9A;">{{ $post->created_at->format('d M Y') }}</td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            @if($post->media->count() > 0)
                            <button type="button" class="btn btn-sm btn-secondary" onclick="openMediaModal('{{ cdn_url($post->media->first()->url) }}', '{{ $post->media->first()->type }}')"><i class="fas fa-eye"></i></button>
                            @endif
                            <form method="POST" action="{{ route('admin.community.posts.delete', $post->id) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="12" style="text-align:center;padding:30px;color:#8A8A9A;">No posts found</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if($posts->hasPages())<div style="padding:16px;display:flex;justify-content:center;">{{ $posts->withQueryString()->links() }}</div>@endif
    </div>

{{-- Media Preview Modal --}}
<div id="mediaModal" class="modal-overlay" style="display:none;z-index:9999;" onclick="if(event.target===this)closeMediaModal()">
    <div style="position:relative;max-width:800px;margin:auto;padding-top:60px;">
        <button onclick="closeMediaModal()" style="position:absolute;top:20px;right:0;background:rgba(0,0,0,0.5);color:#fff;border:none;border-radius:50%;width:36px;height:36px;font-size:18px;cursor:pointer;">&times;</button>
        <div id="mediaContent"></div>
    </div>
</div>

@push('scripts')
<script>
function submitBulkDeletePosts() {
    var checked = document.querySelectorAll('.post-check:checked');
    if (!checked.length) { alert('Select posts to delete'); return; }
    if (!confirm('Delete ' + checked.length + ' selected posts?')) return;
    var container = document.getElementById('bulkPostIds');
    container.innerHTML = '';
    checked.forEach(function(c) {
        var input = document.createElement('input');
        input.type = 'hidden'; input.name = 'ids[]'; input.value = c.value;
        container.appendChild(input);
    });
    document.getElementById('bulkPostsForm').submit();
}
function openMediaModal(url, type) {
    var el = document.getElementById('mediaContent');
    if (type === 'video') {
        el.innerHTML = '<video src="'+url+'" controls autoplay style="width:100%;max-height:80vh;border-radius:12px;background:#000;"></video>';
    } else {
        el.innerHTML = '<img src="'+url+'" style="width:100%;max-height:80vh;border-radius:12px;object-fit:contain;background:#000;">';
    }
    document.getElementById('mediaModal').style.display = 'flex';
}
function closeMediaModal() {
    document.getElementById('mediaModal').style.display = 'none';
    document.getElementById('mediaContent').innerHTML = '';
}
</script>
@endpush
@endsection
