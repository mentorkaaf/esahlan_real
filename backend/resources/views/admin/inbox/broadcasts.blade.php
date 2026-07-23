@extends('admin.layouts.app')
@section('title', 'Marketing Broadcasts')
@section('content')

<style>
.bc-grid { display:grid; grid-template-columns:400px 1fr; gap:20px; align-items:start; }

/* ── Create form card ── */
.bc-form-card { background:#fff; border:1px solid #e8ecf2; border-radius:16px; overflow:hidden; }
.bc-form-head { background:linear-gradient(135deg,#07003B,#1a0070); padding:18px 20px; }
.bc-form-head-title { font-size:15px; font-weight:900; color:#fff; }
.bc-form-head-sub   { font-size:11px; color:rgba(255,255,255,.5); margin-top:3px; }
.bc-form-body { padding:20px; display:flex; flex-direction:column; gap:14px; }

.form-group label { display:block; font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:.6px; margin-bottom:5px; }
.form-input, .form-textarea, .form-select { width:100%; padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:13px; font-family:inherit; outline:none; transition:border-color .15s; box-sizing:border-box; }
.form-input:focus, .form-textarea:focus, .form-select:focus { border-color:#07003B; }
.form-textarea { resize:vertical; min-height:80px; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }

/* Target audience tabs */
.target-tabs { display:flex; gap:6px; }
.target-tab { flex:1; padding:8px; border-radius:8px; border:1.5px solid #e2e8f0; font-size:12px; font-weight:700; cursor:pointer; text-align:center; transition:all .15s; background:#fff; color:#64748b; }
.target-tab.active { background:#07003B; color:#fff; border-color:#07003B; }

/* User search for specific send */
.user-search-wrap { position:relative; }
.user-search-results { position:absolute; top:100%; left:0; right:0; background:#fff; border:1.5px solid #e2e8f0; border-top:none; border-radius:0 0 10px 10px; max-height:200px; overflow-y:auto; z-index:100; display:none; box-shadow:0 4px 16px rgba(0,0,0,.1); }
.user-search-results.show { display:block; }
.user-result { padding:9px 12px; cursor:pointer; font-size:13px; border-bottom:1px solid #f0f2f6; }
.user-result:last-child { border-bottom:none; }
.user-result:hover { background:#f8fafc; }
.user-result-name { font-weight:700; color:#1a1d2e; }
.user-result-sub  { font-size:11px; color:#94a3b8; }
.selected-users { display:flex; flex-wrap:wrap; gap:5px; min-height:0; }
.selected-user-chip { display:flex; align-items:center; gap:5px; background:#eff6ff; color:#2563eb; border-radius:20px; padding:4px 10px; font-size:12px; font-weight:700; }
.selected-user-chip button { background:none; border:none; cursor:pointer; color:#2563eb; font-size:12px; line-height:1; padding:0; }

/* Submit button */
.bc-submit { width:100%; padding:11px; border-radius:10px; background:linear-gradient(135deg,#07003B,#1a0070); color:#fff; font-size:13px; font-weight:800; border:none; cursor:pointer; transition:opacity .15s; }
.bc-submit:hover { opacity:.9; }

/* ── Broadcast list ── */
.bc-list-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.bc-list-title { font-size:16px; font-weight:900; color:#1a1d2e; }
.bc-list { display:flex; flex-direction:column; gap:10px; }
.bc-item { background:#fff; border:1px solid #e8ecf2; border-radius:14px; padding:16px 18px; }
.bc-item-head { display:flex; align-items:flex-start; gap:12px; }
.bc-item-icon { width:42px; height:42px; border-radius:12px; background:linear-gradient(135deg,#FF8A00,#e65c00); color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.bc-item-title { font-size:14px; font-weight:800; color:#1a1d2e; }
.bc-item-body  { font-size:12px; color:#64748b; margin-top:2px; line-height:1.4; }
.bc-item-meta  { display:flex; align-items:center; gap:8px; margin-top:8px; flex-wrap:wrap; }
.bc-badge { display:inline-flex; align-items:center; padding:2px 9px; border-radius:20px; font-size:10px; font-weight:800; }
.bc-badge.draft   { background:#f1f5f9; color:#64748b; }
.bc-badge.sending { background:#fffbeb; color:#d97706; }
.bc-badge.sent    { background:#f0fdf4; color:#16a34a; }
.bc-badge.mod     { background:#f5f3ff; color:#7c3aed; }
.bc-actions { margin-left:auto; display:flex; gap:6px; align-items:center; }
.bc-btn { padding:5px 12px; border-radius:8px; font-size:11px; font-weight:700; border:1.5px solid; cursor:pointer; }
.bc-btn-send  { border-color:#16a34a; color:#16a34a; background:#f0fdf4; }
.bc-btn-send:hover  { background:#16a34a; color:#fff; }
.bc-btn-stats { border-color:#2563eb; color:#2563eb; background:#eff6ff; }
.bc-btn-stats:hover { background:#2563eb; color:#fff; }
.bc-stats-row { display:flex; gap:14px; margin-top:10px; padding-top:10px; border-top:1px solid #f0f2f6; }
.bc-stat { text-align:center; }
.bc-stat-val { font-size:16px; font-weight:900; color:#1a1d2e; }
.bc-stat-lbl { font-size:10px; color:#94a3b8; margin-top:1px; }

.empty-bc { text-align:center; padding:40px 20px; background:#fff; border:1px solid #e8ecf2; border-radius:16px; }
.empty-bc i { font-size:36px; color:#cbd5e1; margin-bottom:10px; }
.empty-bc p { color:#94a3b8; font-size:13px; }

@media(max-width:900px) { .bc-grid { grid-template-columns:1fr; } }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <div>
        <div style="font-size:20px;font-weight:900;color:#1a1d2e">Marketing Broadcasts</div>
        <div style="font-size:12px;color:#94a3b8;margin-top:2px">Send targeted push notifications to your users</div>
    </div>
</div>

<div class="bc-grid">
    {{-- Create form --}}
    <div>
        <div class="bc-form-card">
            <div class="bc-form-head">
                <div class="bc-form-head-title"><i class="fas fa-paper-plane" style="margin-right:7px"></i>New Broadcast</div>
                <div class="bc-form-head-sub">Create and schedule a push notification campaign</div>
            </div>
            <div class="bc-form-body">
                <form method="POST" action="{{ route('admin.inbox.broadcasts.store') }}" enctype="multipart/form-data" id="bcForm">
                    @csrf

                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" class="form-input" placeholder="Notification title" required maxlength="100">
                    </div>

                    <div class="form-group">
                        <label>Message Body</label>
                        <textarea name="body" class="form-textarea" placeholder="Write your broadcast message…" required maxlength="500"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Module / CTA Route</label>
                            <select name="cta_route" class="form-select">
                                <option value="">No CTA</option>
                                <optgroup label="Community">
                                    <option value="/community">Community Feed</option>
                                    <option value="/community/reels">Reels</option>
                                    <option value="/community/chat">Messages</option>
                                    <option value="/community/stories">Stories</option>
                                    <option value="/community/explore">Explore</option>
                                </optgroup>
                                <optgroup label="E-Commerce">
                                    <option value="/efood">eFood — Order Food</option>
                                    <option value="/egrocery">eGrocery — Groceries</option>
                                    <option value="/eshop">eShop — Online Store</option>
                                    <option value="/eparcel">eParcel — Parcel Delivery</option>
                                    <option value="/emoving">eMoving — Moving Service</option>
                                </optgroup>
                                <optgroup label="Finance">
                                    <option value="/eexchange">eExchange — Crypto/FX</option>
                                    <option value="/wallet">Wallet</option>
                                    <option value="/transactions">Transaction History</option>
                                </optgroup>
                                <optgroup label="Learning &amp; Rentals">
                                    <option value="/elearning">eLearning — Courses</option>
                                    <option value="/erent">eRent — Rentals</option>
                                </optgroup>
                                <optgroup label="Account &amp; Support">
                                    <option value="/profile">My Profile</option>
                                    <option value="/chat">Support &amp; Inbox</option>
                                    <option value="/notifications">Notifications</option>
                                    <option value="/settings">Settings</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>CTA Button Label</label>
                            <input type="text" name="cta_label" class="form-input" placeholder="e.g. Shop Now">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Module Tag</label>
                            <select name="module" class="form-select">
                                <option value="">None</option>
                                <option value="community">Community</option>
                                <option value="efood">eFood</option>
                                <option value="egrocery">eGrocery</option>
                                <option value="eshop">eShop</option>
                                <option value="eparcel">eParcel</option>
                                <option value="emoving">eMoving</option>
                                <option value="eexchange">eExchange</option>
                                <option value="elearning">eLearning</option>
                                <option value="erent">eRent</option>
                                <option value="wallet">Wallet</option>
                                <option value="general">General</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Banner Image (optional)</label>
                            <input type="file" name="image" class="form-input" accept="image/*" style="padding:6px 10px">
                        </div>
                    </div>

                    {{-- Target audience --}}
                    <div class="form-group">
                        <label>Target Audience</label>
                        <div class="target-tabs">
                            <button type="button" class="target-tab active" onclick="setTarget('all', this)">All Users</button>
                            <button type="button" class="target-tab" onclick="setTarget('active_30d', this)">Active (30d)</button>
                            <button type="button" class="target-tab" onclick="setTarget('specific', this)">Specific Users</button>
                        </div>
                        <input type="hidden" name="target" id="targetInput" value="all">
                    </div>

                    {{-- Specific user search --}}
                    <div class="form-group" id="specificUserSection" style="display:none">
                        <label>Search Users</label>
                        <div class="user-search-wrap">
                            <input type="text" id="userSearchInput" class="form-input" placeholder="Search by name, phone, or email…" oninput="searchUsers(this.value)" autocomplete="off">
                            <div class="user-search-results" id="userSearchResults"></div>
                        </div>
                        <div class="selected-users" id="selectedUsers" style="margin-top:8px"></div>
                        <div id="selectedUserIds"></div>
                    </div>

                    <button type="submit" class="bc-submit">
                        <i class="fas fa-paper-plane" style="margin-right:6px"></i>Save as Draft
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Broadcast list --}}
    <div>
        <div class="bc-list-head">
            <div class="bc-list-title">Recent Broadcasts</div>
        </div>
        @if($broadcasts->isEmpty())
        <div class="empty-bc">
            <i class="fas fa-broadcast-tower"></i>
            <p>No broadcasts yet. Create your first one.</p>
        </div>
        @else
        <div class="bc-list">
            @foreach($broadcasts as $bc)
            <div class="bc-item">
                <div class="bc-item-head">
                    <div class="bc-item-icon"><i class="fas fa-bullhorn"></i></div>
                    <div style="flex:1;min-width:0">
                        <div class="bc-item-title">{{ $bc->title }}</div>
                        <div class="bc-item-body">{{ Str::limit($bc->body, 80) }}</div>
                        <div class="bc-item-meta">
                            <span class="bc-badge {{ $bc->status }}">{{ ucfirst($bc->status) }}</span>
                            @if($bc->module)<span class="bc-badge mod">{{ $bc->module }}</span>@endif
                            <span style="font-size:11px;color:#94a3b8">{{ $bc->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    <div class="bc-actions">
                        @if($bc->status === 'draft')
                        <form method="POST" action="{{ route('admin.inbox.broadcasts.send', $bc->uuid) }}" onsubmit="return confirm('Send this broadcast to all target users?')">
                            @csrf
                            <button type="submit" class="bc-btn bc-btn-send"><i class="fas fa-paper-plane"></i> Send</button>
                        </form>
                        @endif
                        @if($bc->status === 'sent')
                        <a href="{{ route('admin.inbox.broadcasts.stats', $bc->uuid) }}" class="bc-btn bc-btn-stats"><i class="fas fa-chart-bar"></i> Stats</a>
                        @endif
                    </div>
                </div>
                @if($bc->status === 'sent')
                <div class="bc-stats-row">
                    <div class="bc-stat"><div class="bc-stat-val">{{ $bc->broadcastUsers()->count() }}</div><div class="bc-stat-lbl">Sent</div></div>
                    <div class="bc-stat"><div class="bc-stat-val">{{ $bc->broadcastUsers()->where('is_read', true)->count() }}</div><div class="bc-stat-lbl">Read</div></div>
                    <div class="bc-stat"><div class="bc-stat-val">{{ $bc->broadcastUsers()->where('cta_clicked', true)->count() }}</div><div class="bc-stat-lbl">CTA Clicks</div></div>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @if($broadcasts->hasPages())
        <div style="margin-top:12px">{{ $broadcasts->links() }}</div>
        @endif
        @endif
    </div>
</div>

<script>
function setTarget(val, btn) {
    document.querySelectorAll('.target-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('targetInput').value = val;
    document.getElementById('specificUserSection').style.display = val === 'specific' ? 'block' : 'none';
}

let selectedUsers = {};
let searchTimer;

function searchUsers(q) {
    clearTimeout(searchTimer);
    const results = document.getElementById('userSearchResults');
    if (!q || q.length < 2) { results.classList.remove('show'); return; }
    searchTimer = setTimeout(async () => {
        try {
            const r = await fetch(`{{ route("admin.inbox.users.search") }}?q=${encodeURIComponent(q)}`, {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const users = await r.json();
            results.innerHTML = users.length ? users.map(u => `
                <div class="user-result" onclick="selectUser(${u.id}, '${escHtml(u.name)}', '${escHtml(u.phone ?? u.email ?? '')}')">
                    <div class="user-result-name">${escHtml(u.name)}</div>
                    <div class="user-result-sub">${escHtml(u.phone ?? '')} ${escHtml(u.email ?? '')}</div>
                </div>
            `).join('') : '<div class="user-result" style="color:#94a3b8">No users found</div>';
            results.classList.add('show');
        } catch(_) {}
    }, 300);
}

function selectUser(id, name, sub) {
    if (selectedUsers[id]) return;
    selectedUsers[id] = name;
    document.getElementById('userSearchResults').classList.remove('show');
    document.getElementById('userSearchInput').value = '';
    renderSelected();
}

function removeUser(id) {
    delete selectedUsers[id];
    renderSelected();
}

function renderSelected() {
    const wrap = document.getElementById('selectedUsers');
    const hidden = document.getElementById('selectedUserIds');
    wrap.innerHTML = Object.entries(selectedUsers).map(([id, name]) =>
        `<span class="selected-user-chip">${escHtml(name)}<button type="button" onclick="removeUser(${id})">×</button></span>`
    ).join('');
    hidden.innerHTML = Object.keys(selectedUsers).map(id =>
        `<input type="hidden" name="user_ids[]" value="${id}">`
    ).join('');
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('click', e => {
    if (!e.target.closest('.user-search-wrap')) {
        document.getElementById('userSearchResults').classList.remove('show');
    }
});
</script>
@endsection
