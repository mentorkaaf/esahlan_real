@extends('admin.layouts.app')
@section('title', 'Engagement Generator')
@section('content')

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-magic" style="color:#8B5CF6"></i> Engagement Generator</h2>
        <ol class="breadcrumb"><li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li><a href="{{ route('admin.community.index') }}">Community</a></li><li>Engagement</li></ol>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row" style="gap:16px;">
    {{-- Generate Form --}}
    <div class="col-lg-5">
        <div class="card" style="padding:24px;">
            <h5 style="font-weight:700;margin-bottom:20px;"><i class="fas fa-bolt" style="color:#FF8A00"></i> Generate Engagement</h5>
            <form method="POST" action="{{ route('admin.community.engagement.generate') }}">
                @csrf
                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label" style="font-weight:600;">Post ID</label>
                    <input type="number" name="post_id" class="form-control" required placeholder="Enter post ID">
                </div>
                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label" style="font-weight:600;"><i class="fas fa-thumbs-up" style="color:#1877F2"></i> Likes</label>
                    <input type="range" name="likes" min="0" max="500" value="20" class="form-range" id="likesRange" oninput="document.getElementById('likesVal').textContent=this.value">
                    <span id="likesVal" style="font-weight:800;color:#1877F2;">20</span>
                </div>
                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label" style="font-weight:600;"><i class="fas fa-eye" style="color:#10B981"></i> Views</label>
                    <input type="range" name="views" min="0" max="10000" value="500" step="50" class="form-range" id="viewsRange" oninput="document.getElementById('viewsVal').textContent=this.value">
                    <span id="viewsVal" style="font-weight:800;color:#10B981;">500</span>
                </div>
                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label" style="font-weight:600;"><i class="fas fa-comment" style="color:#FF8A00"></i> Comments (Somali)</label>
                    <input type="range" name="comments" min="0" max="100" value="10" class="form-range" id="commentsRange" oninput="document.getElementById('commentsVal').textContent=this.value">
                    <span id="commentsVal" style="font-weight:800;color:#FF8A00;">10</span>
                </div>
                <button type="submit" class="btn btn-primary" style="background:#8B5CF6;border:none;width:100%;padding:12px;font-weight:700;font-size:15px;">
                    <i class="fas fa-magic"></i> Generate Engagement
                </button>
            </form>
        </div>

        {{-- Bot Users --}}
        <div class="card" style="padding:24px;margin-top:16px;">
            <h5 style="font-weight:700;margin-bottom:16px;"><i class="fas fa-users" style="color:#FF8A00"></i> Bot Users ({{ $botCount }})</h5>
            <p style="color:#6B7280;font-size:13px;">Auto-created Somali bot accounts with names and avatars.</p>
            @foreach($bots->take(10) as $bot)
            <div style="display:flex;align-items:center;gap:10px;padding:6px 0;">
                <img src="{{ $bot->avatar }}" style="width:32px;height:32px;border-radius:50%;background:#f0f2f5;">
                <span style="font-weight:600;font-size:13px;">{{ $bot->name }}</span>
            </div>
            @endforeach
            @if($botCount > 10)<p style="color:#9CA3AF;font-size:12px;margin-top:8px;">+ {{ $botCount - 10 }} more...</p>@endif
        </div>
    </div>

    {{-- Recent Posts --}}
    <div class="col-lg-6">
        <div class="card" style="padding:24px;">
            <h5 style="font-weight:700;margin-bottom:16px;"><i class="fas fa-list" style="color:#FF8A00"></i> Recent Posts</h5>
            <table style="width:100%;">
                <thead><tr style="background:#f8f9fa;">
                    <th style="padding:8px;font-size:12px;">ID</th>
                    <th style="padding:8px;font-size:12px;">Author</th>
                    <th style="padding:8px;font-size:12px;">Type</th>
                    <th style="padding:8px;font-size:12px;"><i class="fas fa-heart" style="color:#E41E3F"></i></th>
                    <th style="padding:8px;font-size:12px;"><i class="fas fa-comment" style="color:#FF8A00"></i></th>
                    <th style="padding:8px;font-size:12px;"><i class="fas fa-eye" style="color:#10B981"></i></th>
                    <th style="padding:8px;font-size:12px;">Action</th>
                </tr></thead>
                <tbody>
                @foreach($posts as $post)
                <tr style="border-bottom:1px solid #f0f2f5;">
                    <td style="padding:8px;font-weight:700;">{{ $post->id }}</td>
                    <td style="padding:8px;font-size:13px;">{{ $post->user?->name ?? '—' }}</td>
                    <td style="padding:8px;"><span style="background:#f0f2f5;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">{{ $post->type }}</span></td>
                    <td style="padding:8px;font-weight:600;">{{ $post->likes_count }}</td>
                    <td style="padding:8px;font-weight:600;">{{ $post->comments_count }}</td>
                    <td style="padding:8px;font-weight:600;">{{ $post->views_count }}</td>
                    <td style="padding:8px;">
                        <button onclick="document.querySelector('[name=post_id]').value={{ $post->id }};window.scrollTo(0,0)" class="btn btn-sm" style="background:#8B5CF6;color:#fff;font-size:11px;padding:3px 10px;">
                            <i class="fas fa-magic"></i> Generate
                        </button>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
