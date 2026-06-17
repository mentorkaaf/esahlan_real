@extends('admin.layouts.app')
@section('title', 'Ads Management')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Ads Management</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li>Ads</li>
        </ul>
    </div>
    <button class="btn btn-primary" onclick="openModal('createAdModal')">
        <i class="fas fa-plus"></i> Create Ad
    </button>
</div>

@if(session('success'))
<div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> {{ session('error') }}</div>
@endif

{{-- ── Stats Row ─────────────────────────────────────────────────────── --}}
@php
    $totalAds    = $ads->count();
    $activeAds   = $ads->where('status','active')->count();
    $popupAds    = $ads->whereIn('ad_type',['popup_fullscreen','popup_modal'])->count();
    $bannerAds   = $ads->whereIn('ad_type',['banner_slider','banner_inline'])->count();
    $totalImpr   = $ads->sum('impressions');
    $totalClicks = $ads->sum('clicks');
    $ctr         = $totalImpr > 0 ? round(($totalClicks / $totalImpr) * 100, 1) : 0;
@endphp
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:20px;">
    <div class="card" style="margin-bottom:0;padding:16px 18px;">
        <div style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Total Ads</div>
        <div style="font-size:26px;font-weight:900;color:#111827;">{{ $totalAds }}</div>
    </div>
    <div class="card" style="margin-bottom:0;padding:16px 18px;">
        <div style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Active</div>
        <div style="font-size:26px;font-weight:900;color:#10b981;">{{ $activeAds }}</div>
    </div>
    <div class="card" style="margin-bottom:0;padding:16px 18px;">
        <div style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Impressions</div>
        <div style="font-size:26px;font-weight:900;color:#3b82f6;">{{ number_format($totalImpr) }}</div>
    </div>
    <div class="card" style="margin-bottom:0;padding:16px 18px;">
        <div style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Clicks</div>
        <div style="font-size:26px;font-weight:900;color:#FF8A00;">{{ number_format($totalClicks) }}</div>
    </div>
    <div class="card" style="margin-bottom:0;padding:16px 18px;">
        <div style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">CTR</div>
        <div style="font-size:26px;font-weight:900;color:#8b5cf6;">{{ $ctr }}%</div>
    </div>
</div>

{{-- ── Ads Table ─────────────────────────────────────────────────────── --}}
@if($ads->count() > 0)
<div class="card">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:2px solid #f1f5f9;">
                    <th style="text-align:left;padding:12px 16px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">Ad</th>
                    <th style="text-align:left;padding:12px 16px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">Type</th>
                    <th style="text-align:left;padding:12px 16px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">Target</th>
                    <th style="text-align:left;padding:12px 16px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">Schedule</th>
                    <th style="text-align:left;padding:12px 16px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">Stats</th>
                    <th style="text-align:left;padding:12px 16px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">Status</th>
                    <th style="text-align:right;padding:12px 16px;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($ads as $ad)
            <tr style="border-bottom:1px solid #f9fafb;transition:background .1s;" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background=''">
                {{-- Ad thumbnail + title --}}
                <td style="padding:14px 16px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        @php $imgSrc = $ad->image ? asset('storage/'.$ad->image) : $ad->image_url; @endphp
                        <div style="width:56px;height:40px;border-radius:8px;overflow:hidden;background:#f1f5f9;flex-shrink:0;">
                            @if($imgSrc)
                                <img src="{{ $imgSrc }}" style="width:100%;height:100%;object-fit:cover;">
                            @else
                                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#d1d5db;">
                                    <i class="fas fa-image" style="font-size:18px;"></i>
                                </div>
                            @endif
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:13px;color:#111827;">{{ $ad->title }}</div>
                            @if($ad->description)
                            <div style="font-size:11px;color:#9ca3af;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $ad->description }}</div>
                            @endif
                        </div>
                    </div>
                </td>

                {{-- Ad type badge --}}
                <td style="padding:14px 16px;">
                    @php
                        $typeColors = [
                            'popup_fullscreen' => ['bg'=>'#fff7ed','color'=>'#ea580c','label'=>'Popup Full'],
                            'popup_modal'      => ['bg'=>'#fef3c7','color'=>'#d97706','label'=>'Popup Modal'],
                            'banner_slider'    => ['bg'=>'#eff6ff','color'=>'#2563eb','label'=>'Banner Slider'],
                            'banner_inline'    => ['bg'=>'#f0fdf4','color'=>'#16a34a','label'=>'Banner Inline'],
                            'card'             => ['bg'=>'#fdf4ff','color'=>'#9333ea','label'=>'Card'],
                        ];
                        $tc = $typeColors[$ad->ad_type] ?? ['bg'=>'#f1f5f9','color'=>'#64748b','label'=>$ad->ad_type];
                    @endphp
                    <span style="display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:{{ $tc['bg'] }};color:{{ $tc['color'] }};">
                        {{ $tc['label'] }}
                    </span>
                </td>

                {{-- Target --}}
                <td style="padding:14px 16px;">
                    <div style="font-size:12px;font-weight:600;color:#374151;">
                        @if($ad->target_module && $ad->target_module !== 'all')
                            <span style="background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:12px;font-size:11px;">{{ strtoupper($ad->target_module) }}</span>
                        @else
                            <span style="color:#9ca3af;">All Users</span>
                        @endif
                    </div>
                    @if($ad->district)
                    <div style="font-size:11px;color:#9ca3af;margin-top:3px;"><i class="fas fa-map-marker-alt" style="font-size:10px;"></i> {{ $ad->district->name }}</div>
                    @endif
                </td>

                {{-- Schedule --}}
                <td style="padding:14px 16px;">
                    <div style="font-size:11px;color:#6b7280;">
                        @if($ad->start_date || $ad->end_date)
                            @if($ad->start_date)<div><i class="fas fa-calendar-check" style="color:#10b981;margin-right:4px;"></i>{{ $ad->start_date->format('d M Y') }}</div>@endif
                            @if($ad->end_date)<div><i class="fas fa-calendar-times" style="color:#ef4444;margin-right:4px;"></i>{{ $ad->end_date->format('d M Y') }}</div>@endif
                        @else
                            <span style="color:#9ca3af;">Always</span>
                        @endif
                    </div>
                    <div style="font-size:10px;color:#d1d5db;margin-top:4px;">Every {{ $ad->display_frequency }}x · Delay {{ $ad->display_delay_seconds }}s</div>
                </td>

                {{-- Stats --}}
                <td style="padding:14px 16px;">
                    <div style="display:flex;gap:14px;">
                        <div style="text-align:center;">
                            <div style="font-size:14px;font-weight:800;color:#3b82f6;">{{ number_format($ad->impressions) }}</div>
                            <div style="font-size:10px;color:#9ca3af;">Views</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:14px;font-weight:800;color:#FF8A00;">{{ number_format($ad->clicks) }}</div>
                            <div style="font-size:10px;color:#9ca3af;">Clicks</div>
                        </div>
                        @if($ad->impressions > 0)
                        <div style="text-align:center;">
                            <div style="font-size:14px;font-weight:800;color:#8b5cf6;">{{ round(($ad->clicks/$ad->impressions)*100,1) }}%</div>
                            <div style="font-size:10px;color:#9ca3af;">CTR</div>
                        </div>
                        @endif
                    </div>
                </td>

                {{-- Status toggle --}}
                <td style="padding:14px 16px;">
                    <form action="{{ route('admin.ads.toggle', $ad->id) }}" method="POST">
                        @csrf
                        <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;">
                            <label class="toggle" style="pointer-events:none;">
                                <input type="checkbox" {{ $ad->status === 'active' ? 'checked' : '' }}>
                                <span class="toggle-slider"></span>
                            </label>
                        </button>
                    </form>
                    <div style="font-size:10px;font-weight:600;margin-top:4px;color:{{ $ad->status === 'active' ? '#10b981' : '#9ca3af' }};">
                        {{ ucfirst($ad->status) }}
                    </div>
                </td>

                {{-- Actions --}}
                <td style="padding:14px 16px;text-align:right;">
                    <div style="display:flex;gap:6px;justify-content:flex-end;">
                        <button class="btn btn-xs btn-outline"
                                onclick='openEditModal({{ json_encode($ad) }})'>
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <form action="{{ route('admin.ads.destroy', $ad->id) }}" method="POST"
                              onsubmit="return confirm('Delete this ad?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-xs" style="background:#fff5f5;color:#ef4444;border:1.5px solid #fecaca;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="card">
    <div class="empty-state">
        <i class="fas fa-ad"></i>
        <h3>No ads yet</h3>
        <p>Create your first ad to start showing promotions inside the app</p>
        <button class="btn btn-primary" style="margin-top:12px;" onclick="openModal('createAdModal')">
            <i class="fas fa-plus"></i> Create Ad
        </button>
    </div>
</div>
@endif


{{-- ═══════════════════════════════════════════════════════════════════════════
     CREATE AD MODAL
═══════════════════════════════════════════════════════════════════════════ --}}
<div id="createAdModal" class="modal-overlay">
    <div class="modal-box" style="max-width:620px;max-height:90vh;display:flex;flex-direction:column;">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-plus-circle" style="color:#FF8A00;margin-right:8px;"></i>Create New Ad</div>
            <button class="modal-close" onclick="closeModal('createAdModal')"><i class="fas fa-times"></i></button>
        </div>
        <form action="{{ route('admin.ads.store') }}" method="POST" enctype="multipart/form-data" style="overflow-y:auto;flex:1;">
            @csrf
            <div class="modal-body">
                @include('admin.ads._form', ['ad' => null, 'modules' => $modules, 'districts' => $districts, 'prefix' => 'create'])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('createAdModal')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Create Ad</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     EDIT AD MODAL
═══════════════════════════════════════════════════════════════════════════ --}}
<div id="editAdModal" class="modal-overlay">
    <div class="modal-box" style="max-width:620px;max-height:90vh;display:flex;flex-direction:column;">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-edit" style="color:#3b82f6;margin-right:8px;"></i>Edit Ad</div>
            <button class="modal-close" onclick="closeModal('editAdModal')"><i class="fas fa-times"></i></button>
        </div>
        <form id="editAdForm" method="POST" enctype="multipart/form-data" style="overflow-y:auto;flex:1;">
            @csrf @method('PUT')
            <div class="modal-body" id="editAdBody">
                {{-- Populated by JS --}}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('editAdModal')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
// ── Modal helpers ────────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

// ── Modules & districts for JS use ──────────────────────────────────────────
const _modules   = @json($modules->pluck('name','slug'));
const _districts = @json($districts->pluck('name','id'));

// ── Build form HTML ──────────────────────────────────────────────────────────
function buildFormHTML(ad) {
    const v = (k, d='') => ad ? (ad[k] ?? d) : d;
    const chk = (k, def=false) => (ad ? !!ad[k] : def) ? 'checked' : '';
    const sel = (opt, cur) => opt === cur ? 'selected' : '';

    // module options
    let modOpts = '<option value="">All Users (no filter)</option>';
    modOpts += '<option value="all" ' + sel('all', v('target_module')) + '>All Modules</option>';
    Object.entries(_modules).forEach(([slug, name]) => {
        modOpts += `<option value="${slug}" ${sel(slug, v('target_module'))}>${name} (${slug})</option>`;
    });

    // district options
    let distOpts = '<option value="">No district filter</option>';
    Object.entries(_districts).forEach(([id, name]) => {
        distOpts += `<option value="${id}" ${sel(id, String(v('target_district_id','')))}>${name}</option>`;
    });

    return `
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Title <span style="color:var(--danger)">*</span></label>
            <input type="text" name="title" class="form-control" value="${escHtml(v('title'))}" required placeholder="e.g. Summer Sale Promo">
        </div>
        <div class="form-group">
            <label class="form-label">Ad Type <span style="color:var(--danger)">*</span></label>
            <select name="ad_type" class="form-control" required>
                <option value="popup_modal"      ${sel('popup_modal',      v('ad_type','popup_modal'))}>💬 Popup — Modal (center dialog)</option>
                <option value="popup_fullscreen"  ${sel('popup_fullscreen', v('ad_type'))}>📱 Popup — Full Screen</option>
                <option value="banner_slider"     ${sel('banner_slider',    v('ad_type'))}>🎠 Banner — Slider (home/module)</option>
                <option value="banner_inline"     ${sel('banner_inline',    v('ad_type'))}>📌 Banner — Inline Card</option>
                <option value="card"              ${sel('card',             v('ad_type'))}>🃏 Promotional Card</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2" placeholder="Short promo copy shown below the title">${escHtml(v('description'))}</textarea>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Upload Image</label>
            <input type="file" name="image" class="form-control" accept="image/*">
            <div class="form-hint">JPG/PNG, max 4 MB. Replaces current image.</div>
        </div>
        <div class="form-group">
            <label class="form-label">Or Image URL</label>
            <input type="text" name="image_url" class="form-control" value="${escHtml(v('image_url'))}" placeholder="https://cdn.example.com/ad.jpg">
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Video URL (optional)</label>
        <input type="text" name="video_url" class="form-control" value="${escHtml(v('video_url'))}" placeholder="https://...mp4 or YouTube link">
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Target Module</label>
            <select name="target_module" class="form-control">${modOpts}</select>
            <div class="form-hint">Leave empty to show to all users.</div>
        </div>
        <div class="form-group">
            <label class="form-label">Target District</label>
            <select name="target_district_id" class="form-control">${distOpts}</select>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Status <span style="color:var(--danger)">*</span></label>
            <select name="status" class="form-control" required>
                <option value="inactive"  ${sel('inactive',  v('status','inactive'))}>⚪ Inactive</option>
                <option value="active"    ${sel('active',    v('status'))}>🟢 Active</option>
                <option value="scheduled" ${sel('scheduled', v('status'))}>🕐 Scheduled</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" value="${v('sort_order', 0)}" min="0">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Start Date</label>
            <input type="datetime-local" name="start_date" class="form-control"
                   value="${v('start_date','')?.replace?.(' ','T')?.slice?.(0,16) ?? ''}">
        </div>
        <div class="form-group">
            <label class="form-label">End Date</label>
            <input type="datetime-local" name="end_date" class="form-control"
                   value="${v('end_date','')?.replace?.(' ','T')?.slice?.(0,16) ?? ''}">
        </div>
    </div>

    <div style="background:#f8fafc;border-radius:12px;padding:16px;margin-bottom:16px;">
        <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px;">Click Action</div>
        <div class="form-row">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Action Type</label>
                <select name="action_type" class="form-control" required>
                    <option value="none"    ${sel('none',    v('action_type','none'))}>None</option>
                    <option value="module"  ${sel('module',  v('action_type'))}>Open Module</option>
                    <option value="vendor"  ${sel('vendor',  v('action_type'))}>Open Vendor</option>
                    <option value="product" ${sel('product', v('action_type'))}>Open Product</option>
                    <option value="url"     ${sel('url',     v('action_type'))}>External URL</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Action Value</label>
                <input type="text" name="action_value" class="form-control" value="${escHtml(v('action_value'))}"
                       placeholder="e.g. efood | 42 | https://...">
                <div class="form-hint">Module: slug (efood) · Vendor/Product: ID · URL: full URL</div>
            </div>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Button Text</label>
            <input type="text" name="button_text" class="form-control" value="${escHtml(v('button_text','Learn More'))}" placeholder="Learn More">
        </div>
        <div class="form-group">
            <label class="form-label">Button Color</label>
            <input type="color" name="button_color" class="form-control" value="${v('button_color','#FF8A00')}" style="padding:4px;height:42px;">
        </div>
    </div>

    <div style="background:#f8fafc;border-radius:12px;padding:16px;margin-bottom:16px;">
        <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px;">Display Behavior</div>
        <div class="form-row">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Show Every N Opens</label>
                <input type="number" name="display_frequency" class="form-control" value="${v('display_frequency', 1)}" min="1" max="100">
                <div class="form-hint">1 = every app open. 3 = once every 3 opens.</div>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Delay (seconds)</label>
                <input type="number" name="display_delay_seconds" class="form-control" value="${v('display_delay_seconds', 2)}" min="0" max="300">
                <div class="form-hint">Wait N seconds after screen loads.</div>
            </div>
        </div>
        <div style="display:flex;gap:20px;margin-top:14px;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600;">
                <input type="checkbox" name="show_on_app_open" value="1" ${chk('show_on_app_open', true)}>
                Show on App Open
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600;">
                <input type="checkbox" name="show_after_login" value="1" ${chk('show_after_login', false)}>
                Show After Login
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600;">
                <input type="checkbox" name="allow_dont_show_today" value="1" ${chk('allow_dont_show_today', true)}>
                Allow "Don't Show Today"
            </label>
        </div>
    </div>
    `;
}

function escHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Populate edit modal ──────────────────────────────────────────────────────
function openEditModal(ad) {
    document.getElementById('editAdForm').action = `/admin/ads/${ad.id}`;
    document.getElementById('editAdBody').innerHTML = buildFormHTML(ad);
    openModal('editAdModal');
}

// ── Populate create modal body on open ─────────────────────────────────────
document.getElementById('createAdModal').addEventListener('click', function(e) {
    // Only build once
    const body = this.querySelector('.modal-body');
    if (body && !body.dataset.built) {
        body.innerHTML = buildFormHTML(null);
        body.dataset.built = '1';
    }
});

// build create form immediately
(function(){
    const body = document.querySelector('#createAdModal .modal-body');
    if (body) { body.innerHTML = buildFormHTML(null); body.dataset.built = '1'; }
})();
</script>
@endpush
@endsection
