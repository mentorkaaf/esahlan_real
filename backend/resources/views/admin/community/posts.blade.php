@extends('admin.layouts.app')
@section('title', 'Community Posts')
@push('styles')
<style>
/* ── Reset & Variables ──────────────────────────────────────── */
:root {
  --accent: #FF8A00;
  --accent2: #FF6B00;
  --success: #22C55E;
  --danger: #EF4444;
  --info: #3B82F6;
  --purple: #8B5CF6;
  --pink: #EC4899;
  --teal: #14B8A6;
  --radius: 14px;
  --shadow: 0 2px 12px rgba(0,0,0,.07);
}

/* ── Stat Cards ─────────────────────────────────────────────── */
.ps-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; margin-bottom:22px; }
.ps-stat {
  background:#fff; border-radius:var(--radius); padding:16px 18px;
  box-shadow:var(--shadow); display:flex; flex-direction:column; gap:4px;
  border-top:3px solid transparent; position:relative; overflow:hidden;
}
.ps-stat::after { content:''; position:absolute; right:-10px; top:-10px; width:60px; height:60px; border-radius:50%; opacity:.07; }
.ps-stat.orange  { border-color:var(--accent);  } .ps-stat.orange::after  { background:var(--accent); }
.ps-stat.blue    { border-color:var(--info);    } .ps-stat.blue::after    { background:var(--info);   }
.ps-stat.green   { border-color:var(--success); } .ps-stat.green::after   { background:var(--success);}
.ps-stat.purple  { border-color:var(--purple);  } .ps-stat.purple::after  { background:var(--purple); }
.ps-stat.pink    { border-color:var(--pink);    } .ps-stat.pink::after    { background:var(--pink);   }
.ps-stat.teal    { border-color:var(--teal);    } .ps-stat.teal::after    { background:var(--teal);   }
.ps-stat .s-icon { font-size:22px; margin-bottom:2px; }
.ps-stat .s-val  { font-size:22px; font-weight:800; color:#1a1a2e; line-height:1; }
.ps-stat .s-lbl  { font-size:11px; color:#8A8A9A; font-weight:600; letter-spacing:.5px; text-transform:uppercase; }

/* ── Filter Bar ─────────────────────────────────────────────── */
.ps-filter {
  background:#fff; border-radius:var(--radius); padding:18px 20px;
  box-shadow:var(--shadow); margin-bottom:20px;
  display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;
}
.ps-filter .f-group { display:flex; flex-direction:column; gap:4px; }
.ps-filter label { font-size:11px; font-weight:700; color:#8A8A9A; letter-spacing:.5px; text-transform:uppercase; }
.ps-filter .form-control { border-radius:10px; font-size:13px; border:1.5px solid #eee; padding:8px 12px; min-width:140px; }
.ps-filter .form-control:focus { border-color:var(--accent); outline:none; box-shadow:0 0 0 3px rgba(255,138,0,.1); }
.btn-filter { background:var(--accent); color:#fff; border:none; border-radius:10px; padding:9px 20px; font-size:13px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px; }
.btn-filter:hover { background:var(--accent2); }
.btn-reset { background:#f5f5f5; color:#555; border:none; border-radius:10px; padding:9px 16px; font-size:13px; font-weight:600; cursor:pointer; }

/* ── Type Pill Tabs ─────────────────────────────────────────── */
.type-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px; }
.type-tab {
  padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700;
  cursor:pointer; border:2px solid transparent; transition:all .15s;
  text-decoration:none; color:#555; background:#f5f5f7;
}
.type-tab:hover, .type-tab.active { background:var(--accent); color:#fff; border-color:var(--accent); }
.type-tab .cnt { background:rgba(255,255,255,.3); border-radius:10px; padding:1px 6px; font-size:10px; margin-left:4px; }
.type-tab.active .cnt { background:rgba(0,0,0,.15); }

/* ── Posts Grid / Cards ─────────────────────────────────────── */
.ps-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:16px; }

.ps-card {
  background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
  overflow:hidden; display:flex; flex-direction:column; position:relative;
  border:1.5px solid #f0f0f5; transition:box-shadow .15s, transform .1s;
}
.ps-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.11); transform:translateY(-1px); }

/* Media preview area */
.ps-media {
  position:relative; background:#0d0d1a;
  aspect-ratio:16/9; overflow:hidden; cursor:pointer; flex-shrink:0;
}
.ps-media img { width:100%; height:100%; object-fit:cover; }
.ps-media .play-btn {
  position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
  background:rgba(0,0,0,.35);
}
.ps-media .play-btn i { font-size:32px; color:#fff; filter:drop-shadow(0 2px 8px rgba(0,0,0,.5)); }
.ps-media .type-badge {
  position:absolute; top:8px; left:8px;
  padding:3px 9px; border-radius:20px; font-size:10px; font-weight:800;
  letter-spacing:.4px; text-transform:uppercase; backdrop-filter:blur(6px);
}
.ps-media .media-count {
  position:absolute; top:8px; right:8px;
  background:rgba(0,0,0,.55); color:#fff; border-radius:8px;
  padding:2px 7px; font-size:11px; font-weight:700;
}
.ps-media .video-status {
  position:absolute; bottom:8px; left:8px;
  padding:3px 9px; border-radius:20px; font-size:10px; font-weight:700;
  background:rgba(0,0,0,.6); color:#fff; display:flex; align-items:center; gap:4px;
}
.ps-media .video-status.ready { color:#22C55E; }
.ps-media .video-status.pending { color:#FBBF24; }
.ps-media-text {
  aspect-ratio:16/9; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
  display:flex; align-items:center; justify-content:center; padding:20px; cursor:default;
}
.ps-media-text p { color:#fff; font-size:14px; line-height:1.5; text-align:center; margin:0; max-height:100%; overflow:hidden; display:-webkit-box; -webkit-line-clamp:4; -webkit-box-orient:vertical; }
.ps-media-poll {
  aspect-ratio:16/9; background:linear-gradient(135deg,#f093fb 0%,#f5576c 100%);
  display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px;
}
.ps-media-poll i { font-size:36px; color:#fff; opacity:.8; }
.ps-media-poll span { color:#fff; font-size:13px; font-weight:700; }
.ps-media-share {
  aspect-ratio:16/9; background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%);
  display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px;
}
.ps-media-share i { font-size:36px; color:#fff; opacity:.8; }
.ps-media-share span { color:#fff; font-size:13px; font-weight:700; }

/* Author row */
.ps-author { display:flex; align-items:center; gap:10px; padding:12px 14px 0; }
.ps-avatar {
  width:38px; height:38px; border-radius:50%; display:flex; align-items:center;
  justify-content:center; font-weight:800; font-size:15px; color:#fff; flex-shrink:0;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
}
.ps-avatar img { width:100%; height:100%; border-radius:50%; object-fit:cover; }
.ps-author-info { flex:1; min-width:0; }
.ps-author-name { font-size:13px; font-weight:700; color:#1a1a2e; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.ps-author-meta { font-size:11px; color:#8A8A9A; display:flex; gap:6px; align-items:center; }
.ps-id-badge { background:#f5f5f7; border-radius:6px; padding:1px 6px; font-size:10px; font-weight:700; color:#8A8A9A; }

/* Content */
.ps-content { padding:10px 14px 0; }
.ps-caption { font-size:13px; color:#333; line-height:1.5; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.ps-hashtags { margin-top:5px; display:flex; flex-wrap:wrap; gap:4px; }
.ps-hashtag { font-size:11px; color:var(--accent); font-weight:600; }

/* Metrics row */
.ps-metrics { display:flex; gap:0; padding:12px 14px 0; border-top:1px solid #f5f5f7; margin-top:10px; }
.ps-metric { flex:1; text-align:center; }
.ps-metric .m-val { font-size:15px; font-weight:800; color:#1a1a2e; }
.ps-metric .m-lbl { font-size:10px; color:#8A8A9A; font-weight:600; letter-spacing:.4px; text-transform:uppercase; }
.ps-metric + .ps-metric { border-left:1px solid #f5f5f7; }

/* Footer actions */
.ps-footer {
  display:flex; align-items:center; justify-content:space-between;
  padding:10px 14px; margin-top:10px; background:#fafafa; border-top:1px solid #f0f0f5;
}
.ps-date { font-size:11px; color:#8A8A9A; font-weight:600; }
.ps-actions { display:flex; gap:6px; }
.btn-icon { width:32px; height:32px; border-radius:8px; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:13px; transition:all .15s; }
.btn-view  { background:#EFF6FF; color:var(--info); }   .btn-view:hover  { background:var(--info); color:#fff; }
.btn-del   { background:#FEF2F2; color:var(--danger); } .btn-del:hover   { background:var(--danger); color:#fff; }
.ps-check-wrap { position:relative; }
.ps-check-wrap input[type=checkbox] { width:16px; height:16px; cursor:pointer; accent-color:var(--accent); }

/* Privacy badge inline */
.priv-badge { font-size:10px; font-weight:700; letter-spacing:.3px; padding:2px 7px; border-radius:10px; }
.priv-public  { background:#DCFCE7; color:#166534; }
.priv-friends { background:#DBEAFE; color:#1D4ED8; }
.priv-private { background:#F3F4F6; color:#6B7280; }

/* Type colours */
.tb-text   { background:#FFF7ED; color:#C2410C; }
.tb-image  { background:#F0FDF4; color:#15803D; }
.tb-video  { background:#EFF6FF; color:#1D4ED8; }
.tb-reel   { background:#FAF5FF; color:#7C3AED; }
.tb-poll   { background:#FDF2F8; color:#BE185D; }
.tb-share  { background:#F0FDFA; color:#0F766E; }

/* ── Bulk toolbar ───────────────────────────────────────────── */
.bulk-bar {
  display:none; align-items:center; gap:10px; flex-wrap:wrap;
  background:#FFF7ED; border:1.5px solid var(--accent); border-radius:var(--radius);
  padding:10px 16px; margin-bottom:16px;
}
.bulk-bar.visible { display:flex; }
.bulk-bar span { font-weight:700; font-size:13px; color:var(--accent); flex:1; }

/* ── Privacy toggle button ──────────────────────────────────── */
.btn-priv-pub  { background:#DCFCE7; color:#166534; }
.btn-priv-pub:hover  { background:#166534; color:#fff; }
.btn-priv-priv { background:#F3F4F6; color:#6B7280; }
.btn-priv-priv:hover { background:#6B7280; color:#fff; }

/* ── Post Detail Modal ──────────────────────────────────────── */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:9999; display:flex; align-items:center; justify-content:center; padding:20px; }
.pd-modal { background:#fff; border-radius:20px; width:100%; max-width:720px; max-height:92vh; overflow-y:auto; display:flex; flex-direction:column; }
.pd-modal-head { display:flex; align-items:center; gap:12px; padding:18px 20px; border-bottom:1px solid #f0f0f5; position:sticky; top:0; background:#fff; z-index:2; }
.pd-modal-head h3 { flex:1; margin:0; font-size:17px; font-weight:800; }
.pd-close { background:#f5f5f7; border:none; border-radius:50%; width:32px; height:32px; cursor:pointer; font-size:18px; display:flex; align-items:center; justify-content:center; color:#555; }
.pd-body { padding:20px; display:grid; gap:16px; }
.pd-media-full { border-radius:14px; overflow:hidden; background:#0d0d1a; }
.pd-media-full img, .pd-media-full video { width:100%; max-height:350px; object-fit:contain; display:block; }
.pd-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.pd-info-item { background:#fafafa; border-radius:12px; padding:12px 14px; }
.pd-info-item .pi-lbl { font-size:10px; color:#8A8A9A; font-weight:700; letter-spacing:.5px; text-transform:uppercase; margin-bottom:3px; }
.pd-info-item .pi-val { font-size:14px; font-weight:700; color:#1a1a2e; }
.pd-metrics-big { display:grid; grid-template-columns:repeat(5,1fr); gap:8px; }
.pd-metric-big { background:#fafafa; border-radius:12px; padding:12px; text-align:center; }
.pd-metric-big .mb-val { font-size:20px; font-weight:800; color:#1a1a2e; }
.pd-metric-big .mb-lbl { font-size:10px; color:#8A8A9A; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
.pd-content-full { background:#fafafa; border-radius:12px; padding:14px; font-size:14px; color:#333; line-height:1.6; white-space:pre-wrap; }
.pd-media-strip { display:flex; gap:8px; flex-wrap:wrap; }
.pd-media-strip .ms-item { width:80px; height:80px; border-radius:10px; overflow:hidden; cursor:pointer; position:relative; background:#0d0d1a; }
.pd-media-strip .ms-item img { width:100%; height:100%; object-fit:cover; }
.pd-media-strip .ms-item .ms-play { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,.4); }
.pd-media-strip .ms-item .ms-play i { color:#fff; font-size:18px; }
.pd-section-title { font-size:11px; font-weight:800; color:#8A8A9A; letter-spacing:.6px; text-transform:uppercase; margin-bottom:6px; }

/* ── Empty state ────────────────────────────────────────────── */
.ps-empty { grid-column:1/-1; text-align:center; padding:60px 20px; }
.ps-empty i { font-size:52px; color:#ddd; display:block; margin-bottom:12px; }
.ps-empty p { color:#8A8A9A; font-size:15px; }

/* ── Pagination ─────────────────────────────────────────────── */
.ps-pagination { display:flex; justify-content:center; padding:20px 0; }

/* ── Responsive ─────────────────────────────────────────────── */
@media(max-width:640px){ .ps-grid { grid-template-columns:1fr; } .pd-info-grid,.pd-metrics-big { grid-template-columns:1fr 1fr; } }
</style>
@endpush

@section('content')

{{-- Page Header --}}
<div class="page-header">
  <div>
    <h2 class="page-title"><i class="fas fa-layer-group" style="color:#FF8A00"></i> Community Posts</h2>
    <ol class="breadcrumb"><li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li><a href="{{ route('admin.community.index') }}">Community</a></li><li>Posts</li></ol>
  </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

{{-- ── Stat Cards ─────────────────────────────────────────────── --}}
<div class="ps-stats">
  <div class="ps-stat orange">
    <span class="s-icon">📝</span>
    <span class="s-val">{{ number_format($typeCounts->sum()) }}</span>
    <span class="s-lbl">Total Posts</span>
  </div>
  <div class="ps-stat blue">
    <span class="s-icon">🎬</span>
    <span class="s-val">{{ number_format($typeCounts->get('video', 0) + $typeCounts->get('reel', 0)) }}</span>
    <span class="s-lbl">Videos + Reels</span>
  </div>
  <div class="ps-stat green">
    <span class="s-icon">🖼️</span>
    <span class="s-val">{{ number_format($typeCounts->get('image', 0)) }}</span>
    <span class="s-lbl">Images</span>
  </div>
  <div class="ps-stat purple">
    <span class="s-icon">👁️</span>
    <span class="s-val">{{ number_format($totalViews) }}</span>
    <span class="s-lbl">Total Views</span>
  </div>
  <div class="ps-stat pink">
    <span class="s-icon">🗳️</span>
    <span class="s-val">{{ number_format($typeCounts->get('poll', 0)) }}</span>
    <span class="s-lbl">Polls</span>
  </div>
  <div class="ps-stat teal">
    <span class="s-icon">💬</span>
    <span class="s-val">{{ number_format($typeCounts->get('text', 0)) }}</span>
    <span class="s-lbl">Text Posts</span>
  </div>
</div>

{{-- ── Filter Bar ─────────────────────────────────────────────── --}}
<div class="ps-filter">
  <form method="GET" style="display:contents;">
    <div class="f-group" style="flex:2;min-width:180px;">
      <label>🔍 Search</label>
      <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search by content or author…">
    </div>
    <div class="f-group">
      <label>📋 Type</label>
      <select name="type" class="form-control">
        <option value="">All Types</option>
        @foreach(['text','image','video','reel','poll','share'] as $t)
          <option value="{{ $t }}" @selected(request('type')===$t)>{{ ucfirst($t) }}</option>
        @endforeach
      </select>
    </div>
    <div class="f-group">
      <label>🔒 Privacy</label>
      <select name="privacy" class="form-control">
        <option value="">All</option>
        <option value="public"  @selected(request('privacy')==='public')>Public</option>
        <option value="friends" @selected(request('privacy')==='friends')>Friends</option>
        <option value="private" @selected(request('privacy')==='private')>Private</option>
      </select>
    </div>
    <div class="f-group">
      <label>⚠️ Status</label>
      <select name="status" class="form-control">
        <option value="">All</option>
        <option value="reported" @selected(request('status')==='reported')>Reported</option>
      </select>
    </div>
    <div style="display:flex;gap:8px;align-self:flex-end;">
      <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
      <a href="{{ route('admin.community.posts') }}" class="btn-reset">Reset</a>
    </div>
  </form>
</div>

{{-- ── Select All row ──────────────────────────────────────────── --}}
<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
  <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:700;color:#555;user-select:none;">
    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" style="width:16px;height:16px;accent-color:var(--accent);cursor:pointer;">
    Select All
  </label>
  <span style="font-size:12px;color:#8A8A9A;">{{ $posts->count() }} posts on this page</span>
</div>

{{-- ── Type Quick Tabs ─────────────────────────────────────────── --}}
<div class="type-tabs">
  <a href="{{ route('admin.community.posts') }}" class="type-tab {{ !request('type') ? 'active' : '' }}">
    All <span class="cnt">{{ number_format($typeCounts->sum()) }}</span>
  </a>
  @foreach(['video'=>'🎬','reel'=>'📱','image'=>'🖼️','text'=>'💬','poll'=>'🗳️','share'=>'🔗'] as $t => $icon)
  <a href="{{ route('admin.community.posts', array_merge(request()->query(), ['type'=>$t])) }}"
     class="type-tab {{ request('type')===$t ? 'active' : '' }}">
    {{ $icon }} {{ ucfirst($t) }} <span class="cnt">{{ number_format($typeCounts->get($t, 0)) }}</span>
  </a>
  @endforeach
</div>

{{-- ── Bulk Actions Bar ────────────────────────────────────────── --}}
<div class="bulk-bar" id="bulkBar">
  <span id="bulkCount">0 selected</span>
  <button type="button" onclick="submitBulkPrivacy('private')" class="btn-filter" style="background:#6B7280;">
    <i class="fas fa-lock"></i> Set Private
  </button>
  <button type="button" onclick="submitBulkPrivacy('public')" class="btn-filter" style="background:var(--success);">
    <i class="fas fa-globe"></i> Set Public
  </button>
  <button type="button" onclick="submitBulkDelete()" class="btn-filter" style="background:var(--danger);">
    <i class="fas fa-trash"></i> Delete Selected
  </button>
  <button type="button" onclick="clearAll()" class="btn-reset">Cancel</button>
</div>

<form action="{{ route('admin.community.posts.bulk-delete') }}" method="POST" id="bulkDeleteForm">
  @csrf
  <div id="bulkDeleteIds"></div>
</form>
<form action="{{ route('admin.community.posts.bulk-privacy') }}" method="POST" id="bulkPrivacyForm">
  @csrf
  <input type="hidden" name="privacy" id="bulkPrivacyValue" value="private">
  <div id="bulkPrivacyIds"></div>
</form>

{{-- ── Posts Grid ──────────────────────────────────────────────── --}}
<div class="ps-grid">
  @forelse($posts as $post)
  @php
    $firstMedia = $post->media->first();
    $typeClass  = 'tb-' . $post->type;
    $typeIcons  = ['text'=>'fas fa-align-left','image'=>'fas fa-image','video'=>'fas fa-video','reel'=>'fab fa-tiktok','poll'=>'fas fa-poll','share'=>'fas fa-share-alt'];
    $typeIcon   = $typeIcons[$post->type] ?? 'fas fa-file';
    $gradients  = ['text'=>'135deg,#667eea,#764ba2','poll'=>'135deg,#f093fb,#f5576c','share'=>'135deg,#4facfe,#00f2fe'];
    $colors     = ['video'=>['#1E3A5F','#60A5FA'],'reel'=>['#2D1B69','#A78BFA'],'image'=>['#0F2D1A','#4ADE80']];
  @endphp

  <div class="ps-card" id="card-{{ $post->id }}">

    {{-- Checkbox corner --}}
    <div style="position:absolute;top:8px;right:8px;z-index:5;" class="ps-check-abs">
      <input type="checkbox" class="post-check" value="{{ $post->id }}" onchange="onCheck()" style="width:18px;height:18px;accent-color:var(--accent);cursor:pointer;">
    </div>

    {{-- ── Media Preview ───────────────────────────────── --}}
    @if(in_array($post->type, ['video','reel']) && $firstMedia)
      <div class="ps-media" onclick="openModal({{ $post->id }})">
        @if($firstMedia->thumbnail)
          <img src="{{ cdn_url($firstMedia->thumbnail) }}" alt="thumb" onerror="this.style.display='none'">
        @elseif($firstMedia->url)
          <img src="{{ cdn_url($firstMedia->url) }}" alt="thumb" onerror="this.style.display='none'">
        @endif
        <div class="play-btn"><i class="fas fa-play-circle"></i></div>
        <span class="type-badge {{ $typeClass }}"><i class="{{ $typeIcon }}"></i> {{ $post->type }}</span>
        @if($post->media->count() > 1)<span class="media-count">+{{ $post->media->count() - 1 }}</span>@endif
        <span class="video-status {{ $post->video_ready ? 'ready' : 'pending' }}">
          <i class="fas {{ $post->video_ready ? 'fa-check-circle' : 'fa-clock' }}"></i>
          {{ $post->video_ready ? 'Ready' : 'Processing' }}
        </span>
      </div>

    @elseif($post->type === 'image' && $firstMedia)
      <div class="ps-media" onclick="openModal({{ $post->id }})">
        <img src="{{ cdn_url($firstMedia->url) }}" alt="" onerror="this.parentElement.style.background='#f5f5f7'">
        <span class="type-badge {{ $typeClass }}"><i class="{{ $typeIcon }}"></i> Image</span>
        @if($post->media->count() > 1)<span class="media-count">{{ $post->media->count() }} photos</span>@endif
      </div>

    @elseif($post->type === 'poll')
      <div class="ps-media-poll">
        <i class="fas fa-poll"></i>
        <span>Poll · {{ is_array($post->poll_options) ? count($post->poll_options) : 0 }} options</span>
      </div>

    @elseif($post->type === 'share')
      <div class="ps-media-share">
        <i class="fas fa-share-alt"></i>
        <span>Shared Post</span>
      </div>

    @else
      {{-- text post --}}
      <div class="ps-media-text">
        <p>{{ Str::limit($post->content ?? '(no caption)', 140) }}</p>
      </div>
    @endif

    {{-- ── Author Row ──────────────────────────────────── --}}
    <div class="ps-author">
      <div class="ps-avatar">{{ strtoupper(substr($post->user?->name ?? '?', 0, 1)) }}</div>
      <div class="ps-author-info">
        <div class="ps-author-name">{{ $post->user?->name ?? '—' }}</div>
        <div class="ps-author-meta">
          <span class="ps-id-badge">#{{ $post->id }}</span>
          <span class="priv-badge priv-{{ $post->privacy }}">{{ $post->privacy }}</span>
          @if($post->reports_count > 0)
            <span style="color:var(--danger);font-weight:700;font-size:10px;"><i class="fas fa-flag"></i> {{ $post->reports_count }}</span>
          @endif
        </div>
      </div>
    </div>

    {{-- ── Caption (non-text posts) ────────────────────── --}}
    @if($post->type !== 'text' && $post->content)
    <div class="ps-content">
      <div class="ps-caption">{{ $post->content }}</div>
    </div>
    @endif

    {{-- ── Metrics ─────────────────────────────────────── --}}
    <div class="ps-metrics">
      <div class="ps-metric">
        <div class="m-val">{{ number_format($post->likes_count) }}</div>
        <div class="m-lbl">❤️ Likes</div>
      </div>
      <div class="ps-metric">
        <div class="m-val">{{ number_format($post->comments_count) }}</div>
        <div class="m-lbl">💬 Cmts</div>
      </div>
      <div class="ps-metric">
        <div class="m-val">{{ number_format($post->views_count) }}</div>
        <div class="m-lbl">👁️ Views</div>
      </div>
      <div class="ps-metric">
        <div class="m-val">{{ number_format($post->shares_count) }}</div>
        <div class="m-lbl">🔗 Shares</div>
      </div>
      <div class="ps-metric">
        <div class="m-val">{{ number_format($post->saves_count) }}</div>
        <div class="m-lbl">🔖 Saves</div>
      </div>
    </div>

    {{-- ── Footer ──────────────────────────────────────── --}}
    <div class="ps-footer">
      <div class="ps-date">
        <i class="fas fa-calendar-alt" style="opacity:.5;margin-right:3px;"></i>
        {{ $post->created_at->format('d M Y · H:i') }}
      </div>
      <div class="ps-actions">
        <button class="btn-icon btn-view" title="View Details" onclick="openModal({{ $post->id }})">
          <i class="fas fa-eye"></i>
        </button>
        <form method="POST" action="{{ route('admin.community.posts.toggle-privacy', $post->id) }}" style="display:contents;">
          @csrf
          <button type="submit"
            class="btn-icon {{ $post->privacy === 'private' ? 'btn-priv-pub' : 'btn-priv-priv' }}"
            title="{{ $post->privacy === 'private' ? 'Make Public' : 'Make Private' }}">
            <i class="fas {{ $post->privacy === 'private' ? 'fa-lock-open' : 'fa-lock' }}"></i>
          </button>
        </form>
        <form method="POST" action="{{ route('admin.community.posts.delete', $post->id) }}" onsubmit="return confirm('Delete this post?')" style="display:contents;">
          @csrf @method('DELETE')
          <button type="submit" class="btn-icon btn-del" title="Delete"><i class="fas fa-trash"></i></button>
        </form>
      </div>
    </div>
  </div>

  {{-- ── Post Detail Modal (hidden) ──────────────────── --}}
  <div id="modal-{{ $post->id }}" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeModal({{ $post->id }})">
    <div class="pd-modal">
      <div class="pd-modal-head">
        <div class="ps-avatar" style="width:40px;height:40px;">{{ strtoupper(substr($post->user?->name ?? '?', 0, 1)) }}</div>
        <h3>{{ $post->user?->name ?? '—' }}</h3>
        <span class="priv-badge priv-{{ $post->privacy }}">{{ $post->privacy }}</span>
        <button class="pd-close" onclick="closeModal({{ $post->id }})">&times;</button>
      </div>
      <div class="pd-body">

        {{-- Media --}}
        @if($firstMedia)
        <div class="pd-media-full">
          @if($firstMedia->type === 'video' || in_array($post->type, ['video','reel']))
            <video src="{{ cdn_url($firstMedia->url) }}" controls preload="metadata" style="background:#000;"></video>
          @else
            <img src="{{ cdn_url($firstMedia->url) }}" alt="">
          @endif
        </div>
        @if($post->media->count() > 1)
        <div>
          <div class="pd-section-title">All Media ({{ $post->media->count() }})</div>
          <div class="pd-media-strip">
            @foreach($post->media as $m)
            <div class="ms-item" onclick="swapMedia({{ $post->id }}, '{{ cdn_url($m->url) }}', '{{ $m->type }}')">
              @if($m->type === 'video')
                <div class="ms-play"><i class="fas fa-play"></i></div>
              @else
                <img src="{{ cdn_url($m->url) }}" alt="" onerror="this.style.display='none'">
              @endif
            </div>
            @endforeach
          </div>
        </div>
        @endif
        @endif

        {{-- Metrics big --}}
        <div>
          <div class="pd-section-title">Engagement</div>
          <div class="pd-metrics-big">
            <div class="pd-metric-big"><div class="mb-val">{{ number_format($post->likes_count) }}</div><div class="mb-lbl">❤️ Likes</div></div>
            <div class="pd-metric-big"><div class="mb-val">{{ number_format($post->comments_count) }}</div><div class="mb-lbl">💬 Comments</div></div>
            <div class="pd-metric-big"><div class="mb-val">{{ number_format($post->views_count) }}</div><div class="mb-lbl">👁️ Views</div></div>
            <div class="pd-metric-big"><div class="mb-val">{{ number_format($post->shares_count) }}</div><div class="mb-lbl">🔗 Shares</div></div>
            <div class="pd-metric-big"><div class="mb-val">{{ number_format($post->saves_count) }}</div><div class="mb-lbl">🔖 Saves</div></div>
          </div>
        </div>

        {{-- Info grid --}}
        <div>
          <div class="pd-section-title">Post Details</div>
          <div class="pd-info-grid">
            <div class="pd-info-item"><div class="pi-lbl">Post ID</div><div class="pi-val">#{{ $post->id }}</div></div>
            <div class="pd-info-item"><div class="pi-lbl">Type</div><div class="pi-val"><i class="{{ $typeIcon }}"></i> {{ ucfirst($post->type) }}</div></div>
            <div class="pd-info-item"><div class="pi-lbl">Privacy</div><div class="pi-val">{{ ucfirst($post->privacy) }}</div></div>
            <div class="pd-info-item"><div class="pi-lbl">Media Files</div><div class="pi-val">{{ $post->media->count() }}</div></div>
            @if(in_array($post->type, ['video','reel']))
            <div class="pd-info-item"><div class="pi-lbl">Video Status</div><div class="pi-val" style="color:{{ $post->video_ready ? 'var(--success)' : '#FBBF24' }}">{{ $post->video_ready ? '✓ Ready' : '⏳ Processing' }}</div></div>
            @endif
            @if($post->reports_count > 0)
            <div class="pd-info-item"><div class="pi-lbl">Reports</div><div class="pi-val" style="color:var(--danger)">⚠️ {{ $post->reports_count }}</div></div>
            @endif
            <div class="pd-info-item" style="grid-column:1/-1;"><div class="pi-lbl">Posted</div><div class="pi-val" style="font-size:13px;">{{ $post->created_at->format('d M Y · H:i:s') }} ({{ $post->created_at->diffForHumans() }})</div></div>
          </div>
        </div>

        {{-- Caption --}}
        @if($post->content)
        <div>
          <div class="pd-section-title">Caption</div>
          <div class="pd-content-full">{{ $post->content }}</div>
        </div>
        @endif

        {{-- Poll options --}}
        @if($post->type === 'poll' && $post->poll_options)
        <div>
          <div class="pd-section-title">Poll Options</div>
          @foreach((array)$post->poll_options as $opt)
          <div style="background:#fafafa;border-radius:10px;padding:10px 14px;margin-bottom:6px;font-size:13px;font-weight:600;">
            <i class="fas fa-circle" style="font-size:8px;color:var(--accent);margin-right:6px;"></i>{{ is_array($opt) ? ($opt['text'] ?? json_encode($opt)) : $opt }}
          </div>
          @endforeach
        </div>
        @endif

        {{-- Delete action --}}
        <div style="padding-top:4px;">
          <form method="POST" action="{{ route('admin.community.posts.delete', $post->id) }}" onsubmit="return confirm('Permanently delete this post?')">
            @csrf @method('DELETE')
            <button type="submit" style="width:100%;padding:12px;background:var(--danger);color:#fff;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;">
              <i class="fas fa-trash"></i> Delete This Post
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  @empty
  <div class="ps-empty">
    <i class="fas fa-layer-group"></i>
    <p>No posts found matching your filters.</p>
  </div>
  @endforelse
</div>

{{-- Pagination --}}
@if($posts->hasPages())
<div class="ps-pagination">{{ $posts->withQueryString()->links() }}</div>
@endif

@push('scripts')
<script>
// ── Modal ──────────────────────────────────────────────────────
function openModal(id) {
  document.getElementById('modal-' + id).style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  var m = document.getElementById('modal-' + id);
  m.style.display = 'none';
  document.body.style.overflow = '';
  // stop video
  var v = m.querySelector('video');
  if (v) { v.pause(); v.currentTime = 0; }
}
function swapMedia(postId, url, type) {
  var wrap = document.querySelector('#modal-' + postId + ' .pd-media-full');
  if (!wrap) return;
  if (type === 'video') {
    wrap.innerHTML = '<video src="'+url+'" controls autoplay preload="metadata" style="width:100%;max-height:350px;object-fit:contain;background:#000;display:block;"></video>';
  } else {
    wrap.innerHTML = '<img src="'+url+'" style="width:100%;max-height:350px;object-fit:contain;display:block;">';
  }
}

// ── Bulk select ────────────────────────────────────────────────
function onCheck() {
  var checked = document.querySelectorAll('.post-check:checked');
  var bar = document.getElementById('bulkBar');
  document.getElementById('bulkCount').textContent = checked.length + ' selected';
  bar.classList.toggle('visible', checked.length > 0);
}
function toggleSelectAll(master) {
  document.querySelectorAll('.post-check').forEach(c => c.checked = master.checked);
  onCheck();
}
function clearAll() {
  document.querySelectorAll('.post-check').forEach(c => c.checked = false);
  var sa = document.getElementById('selectAll');
  if (sa) sa.checked = false;
  document.getElementById('bulkBar').classList.remove('visible');
}
function _getCheckedIds() {
  return Array.from(document.querySelectorAll('.post-check:checked')).map(c => c.value);
}
function _fillIds(container, ids) {
  container.innerHTML = '';
  ids.forEach(id => {
    var inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
    container.appendChild(inp);
  });
}
function submitBulkDelete() {
  var ids = _getCheckedIds();
  if (!ids.length) return;
  if (!confirm('Delete ' + ids.length + ' selected posts permanently?')) return;
  _fillIds(document.getElementById('bulkDeleteIds'), ids);
  document.getElementById('bulkDeleteForm').submit();
}
function submitBulkPrivacy(privacy) {
  var ids = _getCheckedIds();
  if (!ids.length) return;
  var label = privacy === 'private' ? 'PRIVATE (hide from feed)' : 'PUBLIC (show in feed)';
  if (!confirm('Set ' + ids.length + ' posts to ' + label + '?')) return;
  document.getElementById('bulkPrivacyValue').value = privacy;
  _fillIds(document.getElementById('bulkPrivacyIds'), ids);
  document.getElementById('bulkPrivacyForm').submit();
}

// ── Escape key closes modal ────────────────────────────────────
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay[style*="flex"]').forEach(m => {
    m.style.display = 'none';
    document.body.style.overflow = '';
    var v = m.querySelector('video');
    if (v) { v.pause(); v.currentTime = 0; }
  });
});
</script>
@endpush
@endsection
