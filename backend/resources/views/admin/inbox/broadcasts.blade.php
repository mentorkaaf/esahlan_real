@extends('admin.layouts.app')
@section('title', 'Marketing Broadcasts')
@section('content')

<style>
.br-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; }
.br-head h1 { margin:0; font-size:20px; font-weight:800; color:#1a1d2e; }
.br-btn-primary { background:#07003B; color:#fff; border:none; border-radius:10px; padding:10px 18px; font-size:13px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:7px; }
.br-btn-primary:hover { background:#140465; }

.br-grid { display:grid; grid-template-columns:420px 1fr; gap:18px; align-items:start; }

.br-card { background:#fff; border:1px solid #e8ecf2; border-radius:14px; overflow:hidden; }
.br-card-head { padding:14px 18px; border-bottom:1px solid #f0f2f6; background:#fafbfd; font-size:13px; font-weight:800; color:#1a1d2e; }
.br-card-body { padding:18px; }
.br-label { font-size:12px; font-weight:700; color:#374151; margin-bottom:6px; display:block; }
.br-input { width:100%; padding:8px 12px; border:1.5px solid #e2e8f0; border-radius:9px; font-size:13px; color:#1a1d2e; box-sizing:border-box; }
.br-input:focus { outline:none; border-color:#07003B; }
textarea.br-input { resize:vertical; min-height:80px; }
.br-mb { margin-bottom:14px; }
.br-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.br-select { width:100%; padding:8px 12px; border:1.5px solid #e2e8f0; border-radius:9px; font-size:13px; color:#1a1d2e; box-sizing:border-box; }
.br-select:focus { outline:none; border-color:#07003B; }

/* Broadcast list */
.br-item { border:1px solid #e8ecf2; border-radius:12px; padding:14px 16px; margin-bottom:12px; display:flex; align-items:flex-start; gap:14px; }
.br-item:last-child { margin-bottom:0; }
.br-item-img { width:52px; height:52px; border-radius:9px; object-fit:cover; background:#f1f5f9; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:20px; }
.br-item-title { font-size:13px; font-weight:800; color:#1a1d2e; margin-bottom:3px; }
.br-item-body { font-size:12px; color:#64748b; margin-bottom:6px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:280px; }
.br-item-meta { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.br-pill { display:inline-flex; align-items:center; padding:2px 8px; border-radius:6px; font-size:10px; font-weight:700; }
.br-pill.draft   { background:#f1f5f9; color:#64748b; }
.br-pill.sending { background:#fffbeb; color:#d97706; }
.br-pill.sent    { background:#f0fdf4; color:#16a34a; }
.br-pill.module  { background:#ede9fe; color:#7c3aed; }
.br-item-actions { margin-left:auto; display:flex; gap:8px; align-items:center; flex-shrink:0; }
.br-send-btn { background:#07003B; color:#fff; border:none; border-radius:8px; padding:7px 14px; font-size:12px; font-weight:700; cursor:pointer; }
.br-send-btn:hover { background:#140465; }
.br-stat { font-size:11px; color:#94a3b8; font-weight:600; }
.br-sent-count { font-size:11px; font-weight:700; color:#16a34a; }
.br-empty { text-align:center; padding:50px 20px; color:#94a3b8; }
.br-empty i { font-size:36px; margin-bottom:10px; display:block; }
</style>

<div class="br-head">
    <h1><i class="fas fa-bullhorn" style="color:#FF8A00;margin-right:8px"></i>Marketing Broadcasts</h1>
</div>

<div class="br-grid">

    {{-- Create form --}}
    <div class="br-card">
        <div class="br-card-head">New Broadcast</div>
        <div class="br-card-body">
            <form method="POST" action="{{ route('admin.inbox.broadcasts.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="br-mb">
                    <label class="br-label">Title *</label>
                    <input type="text" name="title" class="br-input" required placeholder="e.g. New Feature Available!">
                </div>
                <div class="br-mb">
                    <label class="br-label">Message Body *</label>
                    <textarea name="body" class="br-input" required placeholder="Write your message…"></textarea>
                </div>
                <div class="br-row br-mb">
                    <div>
                        <label class="br-label">Module (optional)</label>
                        <select name="module" class="br-select">
                            <option value="">All</option>
                            <option value="efood">eFood</option>
                            <option value="egrocery">eGrocery</option>
                            <option value="eshop">eShop</option>
                            <option value="epay">ePay / Wallet</option>
                            <option value="crypto">Crypto</option>
                            <option value="delivery">Delivery</option>
                            <option value="elearning">eLearning</option>
                            <option value="erent">eRent</option>
                            <option value="community">eSpace</option>
                            <option value="general">General</option>
                        </select>
                    </div>
                    <div>
                        <label class="br-label">Target</label>
                        <select name="target" class="br-select">
                            <option value="all">All Users</option>
                            <option value="active_30d">Active (30d)</option>
                        </select>
                    </div>
                </div>
                <div class="br-row br-mb">
                    <div>
                        <label class="br-label">CTA Button Label</label>
                        <input type="text" name="cta_label" class="br-input" placeholder="e.g. Shop Now">
                    </div>
                    <div>
                        <label class="br-label">CTA Route</label>
                        <select name="cta_route" class="br-select">
                            <option value="">None</option>
                            <option value="/home">Home</option>
                            <option value="/wallet">ePay</option>
                            <option value="/community">eSpace</option>
                            <option value="/orders">Orders</option>
                        </select>
                    </div>
                </div>
                <div class="br-mb">
                    <label class="br-label">Image (optional)</label>
                    <input type="file" name="image" class="br-input" accept="image/*">
                </div>
                <button type="submit" class="br-btn-primary" style="width:100%;justify-content:center">
                    <i class="fas fa-save"></i> Create Draft
                </button>
            </form>
        </div>
    </div>

    {{-- Broadcasts list --}}
    <div class="br-card">
        <div class="br-card-head">All Broadcasts ({{ $broadcasts->total() }})</div>
        <div class="br-card-body">
            @forelse($broadcasts as $b)
            <div class="br-item">
                <div class="br-item-img">
                    @if($b->image_url)
                        <img src="{{ $b->image_url }}" style="width:52px;height:52px;border-radius:9px;object-fit:cover">
                    @else
                        <i class="fas fa-bullhorn"></i>
                    @endif
                </div>
                <div style="flex:1;min-width:0">
                    <div class="br-item-title">{{ $b->title }}</div>
                    <div class="br-item-body">{{ $b->body }}</div>
                    <div class="br-item-meta">
                        <span class="br-pill {{ $b->status }}">{{ ucfirst($b->status) }}</span>
                        @if($b->module)
                            <span class="br-pill module">{{ $b->module }}</span>
                        @endif
                        @if($b->status === 'sent')
                            <span class="br-sent-count"><i class="fas fa-check-circle"></i> {{ number_format($b->sent_count) }} sent</span>
                        @endif
                        @if($b->sent_at)
                            <span class="br-stat">{{ $b->sent_at->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>
                <div class="br-item-actions">
                    @if($b->status === 'draft')
                    <form method="POST" action="{{ route('admin.inbox.broadcasts.send', $b->uuid) }}"
                          onsubmit="return confirm('Send to {{ $b->target === 'all' ? 'all users' : 'active users' }}?')">
                        @csrf
                        <button type="submit" class="br-send-btn"><i class="fas fa-paper-plane"></i> Send</button>
                    </form>
                    @elseif($b->status === 'sent')
                    <a href="{{ route('admin.inbox.broadcasts.stats', $b->uuid) }}" style="font-size:12px;font-weight:600;color:#07003B;text-decoration:none">
                        <i class="fas fa-chart-bar"></i> Stats
                    </a>
                    @endif
                </div>
            </div>
            @empty
            <div class="br-empty">
                <i class="fas fa-bullhorn"></i>
                No broadcasts yet — create one on the left
            </div>
            @endforelse

            {{ $broadcasts->links() }}
        </div>
    </div>

</div>
@endsection
