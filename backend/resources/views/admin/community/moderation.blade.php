@extends('admin.layouts.app')
@section('title', 'Content Moderation')
@section('content')

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-shield-alt" style="color:#FF8A00"></i> Content Moderation</h2>
        <ol class="breadcrumb"><li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li><a href="{{ route('admin.community.index') }}">Community</a></li><li>Moderation</li></ol>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

{{-- Settings --}}
<div class="card" style="margin-bottom:20px;">
    <div style="padding:16px;border-bottom:1px solid #f0f1f5;">
        <h3 style="font-weight:800;font-size:16px;margin:0;"><i class="fas fa-cog"></i> Moderation Settings</h3>
    </div>
    <form action="{{ route('admin.community.moderation.update') }}" method="POST" style="padding:20px;">
        @csrf

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;">
            <label style="display:flex;align-items:center;gap:8px;padding:12px;background:#f9fafb;border-radius:10px;cursor:pointer;">
                <input type="checkbox" name="enabled" value="1" {{ $settings['enabled'] ? 'checked' : '' }}>
                <div><strong>Enable Moderation</strong><br><small style="color:#8A8A9A;">Turn on/off all content checks</small></div>
            </label>
            <label style="display:flex;align-items:center;gap:8px;padding:12px;background:#f9fafb;border-radius:10px;cursor:pointer;">
                <input type="checkbox" name="keyword_filter" value="1" {{ $settings['keyword_filter'] ? 'checked' : '' }}>
                <div><strong>Keyword Filter</strong><br><small style="color:#8A8A9A;">Block posts with banned words</small></div>
            </label>
            <label style="display:flex;align-items:center;gap:8px;padding:12px;background:#f9fafb;border-radius:10px;cursor:pointer;">
                <input type="checkbox" name="image_scan" value="1" {{ $settings['image_scan'] ? 'checked' : '' }}>
                <div><strong>Image Scan</strong><br><small style="color:#8A8A9A;">AI nudity detection (Sightengine or local fallback)</small></div>
            </label>
            <label style="display:flex;align-items:center;gap:8px;padding:12px;background:#f9fafb;border-radius:10px;cursor:pointer;">
                <input type="checkbox" name="auto_block" value="1" {{ $settings['auto_block'] ? 'checked' : '' }}>
                <div><strong>Auto Block</strong><br><small style="color:#8A8A9A;">Block immediately (vs flag for review)</small></div>
            </label>
            <label style="display:flex;align-items:center;gap:8px;padding:12px;background:#f9fafb;border-radius:10px;cursor:pointer;">
                <input type="checkbox" name="review_all_media" value="1" {{ $settings['review_all_media'] ? 'checked' : '' }}>
                <div><strong>Review All Media</strong><br><small style="color:#8A8A9A;">Flag all image/video posts for review</small></div>
            </label>
        </div>

        @if($settings['image_scan'])
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
            <div class="form-group">
                <label class="form-label">Block Threshold (0.0 - 1.0)</label>
                <input type="number" name="block_threshold" value="{{ $settings['block_threshold'] }}" step="0.05" min="0" max="1" class="form-control">
                <small style="color:#8A8A9A;">Score above this = auto-block/review</small>
            </div>
            <div class="form-group">
                <label class="form-label">Review Threshold (0.0 - 1.0)</label>
                <input type="number" name="review_threshold" value="{{ $settings['review_threshold'] }}" step="0.05" min="0" max="1" class="form-control">
                <small style="color:#8A8A9A;">Score above this = flag for review</small>
            </div>
        </div>
        @endif

        {{-- Sightengine API --}}
        <div style="background:#F8F9FF;border:1px solid #E5E7EB;border-radius:10px;padding:16px;margin-bottom:20px;">
            <div style="font-weight:700;font-size:14px;margin-bottom:4px;">🤖 Sightengine API <small style="font-weight:400;color:#8A8A9A;">(recommended — AI nudity detection, 2,000 free/month)</small></div>
            <small style="color:#8A8A9A;">Get free API keys at <strong>sightengine.com</strong> → Dashboard → API Credentials. Leave blank to use local skin-tone analysis.</small>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                <div class="form-group">
                    <label class="form-label">API User</label>
                    <input type="text" name="sightengine_user" value="{{ $settings['sightengine_user'] ?? '' }}" class="form-control" placeholder="e.g. 123456789">
                </div>
                <div class="form-group">
                    <label class="form-label">API Secret</label>
                    <input type="password" name="sightengine_secret" value="{{ $settings['sightengine_secret'] ?? '' }}" class="form-control" placeholder="••••••••">
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-bottom:20px;">
            <label class="form-label">Blocked Keywords <small style="color:#8A8A9A;">(comma-separated)</small></label>
            <textarea name="keywords" class="form-control" rows="3" style="font-size:13px;">{{ $keywords }}</textarea>
            <small style="color:#8A8A9A;">Posts containing these words will be blocked or flagged</small>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
    </form>
</div>

{{-- Flagged Posts --}}
<div class="card">
    <div style="padding:16px;border-bottom:1px solid #f0f1f5;">
        <h3 style="font-weight:800;font-size:16px;margin:0;"><i class="fas fa-flag" style="color:#F59E0B"></i> Flagged Posts ({{ $flaggedPosts->total() }})</h3>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Post</th><th>Author</th><th>Reason</th><th>Score</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($flaggedPosts as $report)
            @php $post = $report->reportable; @endphp
            <tr>
                <td style="max-width:300px;">
                    @if($post)
                        <div style="font-weight:600;">{{ Str::limit($post->content, 80) ?: '(media only)' }}</div>
                        @if($post->media->count() > 0)
                            <small style="color:#8A8A9A;">{{ $post->media->count() }} media file(s)</small>
                        @endif
                    @else
                        <span style="color:#8A8A9A;">Post deleted</span>
                    @endif
                </td>
                <td>{{ $post?->user?->name ?? '—' }}</td>
                <td><span class="badge badge-warning">{{ $report->description }}</span></td>
                <td style="font-weight:700;">{{ number_format(floatval(preg_replace('/.*score: ([\d.]+).*/', '$1', $report->description)), 2) }}</td>
                <td style="font-size:12px;color:#8A8A9A;">{{ $report->created_at->diffForHumans() }}</td>
                <td style="display:flex;gap:4px;">
                    @if($post)
                    <form action="{{ route('admin.community.moderation.approve', $report->id) }}" method="POST">
                        @csrf
                        <button class="btn btn-sm btn-success" title="Approve"><i class="fas fa-check"></i> Approve</button>
                    </form>
                    <form action="{{ route('admin.community.moderation.reject', $report->id) }}" method="POST" onsubmit="return confirm('Delete this post?')">
                        @csrf
                        <button class="btn btn-sm btn-danger" title="Remove"><i class="fas fa-trash"></i> Remove</button>
                    </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;padding:30px;color:#8A8A9A;">No flagged posts</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @if($flaggedPosts->hasPages())<div style="padding:16px;display:flex;justify-content:center;">{{ $flaggedPosts->links() }}</div>@endif
</div>

@endsection
