@extends('admin.layouts.app')
@section('title', 'Modules')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Modules</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li>Modules</li>
        </ul>
    </div>
    <div style="font-size:13px;color:var(--text-muted);">
        {{ count($modules ?? []) }} modules configured
    </div>
</div>

@php
$moduleIcons = [
    'efood'    => ['fas fa-utensils',  '#ef4444'],
    'elaundry' => ['fas fa-tshirt',    '#3b82f6'],
    'emoving'  => ['fas fa-truck',     '#f59e0b'],
    'eparcel'  => ['fas fa-box',       '#8b5cf6'],
    'edata'    => ['fas fa-wifi',      '#06b6d4'],
    'eexchange'=> ['fas fa-exchange-alt','#10b981'],
    'ehealth'  => ['fas fa-user-md',   '#ec4899'],
    'erent'    => ['fas fa-home',      '#f97316'],
    'eshop'    => ['fas fa-shopping-bag','#6366f1'],
    'wholesale'=> ['fas fa-warehouse', '#64748b'],
    'egrocery' => ['fas fa-carrot',    '#22c55e'],
    'eticket'  => ['fas fa-plane',     '#0ea5e9'],
];
@endphp

{{-- ── Bulk Visibility Control ─────────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:20px;">
    <div class="card-header" style="display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none;" onclick="toggleBulkPanel()">
        <i class="fas fa-eye" style="color:var(--brand);"></i>
        <span style="font-weight:700;">Visibility Control</span>
        <span style="font-size:11px;color:var(--text-muted);">— manage access for all modules</span>
        <i id="bulkChevron" class="fas fa-chevron-down" style="margin-left:auto;color:var(--text-muted);transition:transform .2s;"></i>
    </div>
    <div id="bulkPanel" style="display:none;">
        @foreach($modules ?? [] as $m)
        @php
            $s = strtolower($m->slug ?? '');
            [$ic, $cl] = $moduleIcons[$s] ?? ['fas fa-th-large', $m->color ?? '#FF8A00'];
            $isPrivate = ($m->visibility ?? 'public') === 'private';
            $betaCount = $m->beta_users_count ?? 0;
        @endphp
        <div style="border-bottom:1px solid var(--border);">
            {{-- Main row --}}
            <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;flex-wrap:wrap;">
                {{-- Icon + Name --}}
                <div style="display:flex;align-items:center;gap:10px;min-width:160px;flex:1;">
                    <div style="width:34px;height:34px;border-radius:9px;background:{{ $cl }}18;color:{{ $cl }};display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;">
                        <i class="{{ $ic }}"></i>
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:13px;color:var(--text);">{{ $m->name }}</div>
                        <div style="font-size:11px;color:var(--text-muted);">
                            <span class="badge {{ $m->is_active ? 'badge-success' : 'badge-danger' }} badge-dot" style="font-size:10px;">{{ $m->is_active ? 'Active' : 'Inactive' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Visibility toggle --}}
                <div style="display:inline-flex;border-radius:20px;overflow:hidden;border:1.5px solid var(--border);flex-shrink:0;">
                    <form action="{{ route('admin.modules.visibility', $m->id) }}" method="POST" style="margin:0;">
                        @csrf <input type="hidden" name="visibility" value="public">
                        <button type="submit" style="border:none;padding:5px 14px;font-size:11px;font-weight:700;cursor:pointer;
                            {{ !$isPrivate ? 'background:#10b981;color:#fff;' : 'background:transparent;color:var(--text-muted);' }}">
                            🌐 Public
                        </button>
                    </form>
                    <form action="{{ route('admin.modules.visibility', $m->id) }}" method="POST" style="margin:0;border-left:1.5px solid var(--border);">
                        @csrf <input type="hidden" name="visibility" value="private">
                        <button type="submit" style="border:none;padding:5px 14px;font-size:11px;font-weight:700;cursor:pointer;
                            {{ $isPrivate ? 'background:#7c3aed;color:#fff;' : 'background:transparent;color:var(--text-muted);' }}">
                            🔒 Private
                        </button>
                    </form>
                </div>

                {{-- Beta users chips + add button (only when private) --}}
                <div id="bu-area-{{ $m->id }}" style="display:flex;align-items:center;gap:6px;flex:1;flex-wrap:wrap;{{ !$isPrivate ? 'opacity:.35;pointer-events:none;' : '' }}">
                    <div id="bu-chips-{{ $m->id }}" style="display:flex;gap:5px;flex-wrap:wrap;">
                        @php $buList = $isPrivate ? $m->betaUsers()->select('users.id','users.name')->get() : collect(); @endphp
                        @foreach($buList as $bu)
                        <span id="chip-{{ $m->id }}-{{ $bu->id }}"
                            style="display:inline-flex;align-items:center;gap:5px;background:#7c3aed18;border:1px solid #7c3aed40;border-radius:20px;padding:3px 10px 3px 8px;font-size:11px;font-weight:600;color:#7c3aed;">
                            <span style="width:18px;height:18px;border-radius:50%;background:#7c3aed;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:9px;font-weight:800;">{{ strtoupper(substr($bu->name,0,1)) }}</span>
                            {{ $bu->name }}
                            <button onclick="removeBetaUserInline({{ $m->id }},{{ $bu->id }},'{{ addslashes($bu->name) }}')"
                                style="background:none;border:none;cursor:pointer;color:#7c3aed;padding:0;font-size:11px;line-height:1;">×</button>
                        </span>
                        @endforeach
                    </div>

                    {{-- Inline search --}}
                    <div style="position:relative;">
                        <input id="isearch-{{ $m->id }}" type="text" placeholder="+ Add user…"
                            style="border:1.5px dashed #7c3aed60;border-radius:20px;padding:4px 12px;font-size:11px;width:130px;background:transparent;color:var(--text);outline:none;"
                            oninput="inlineSearch({{ $m->id }},this.value)"
                            onfocus="this.style.borderColor='#7c3aed';this.style.width='190px'"
                            onblur="setTimeout(()=>{document.getElementById('idd-{{ $m->id }}').style.display='none';this.style.borderColor='#7c3aed60';this.style.width='130px'},200)">
                        <div id="idd-{{ $m->id }}" style="display:none;position:absolute;top:calc(100% + 4px);left:0;min-width:220px;z-index:999;background:var(--surface);border:1px solid var(--border);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.15);overflow:hidden;"></div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;">
    @forelse($modules ?? [] as $module)
    @php
        $slug = strtolower($module->slug ?? '');
        [$mIcon, $mColor] = $moduleIcons[$slug] ?? ['fas fa-th-large', $module->color ?? '#FF8A00'];
    @endphp
    <div class="card" style="margin-bottom:0;transition:box-shadow .2s,transform .2s;" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
        {{-- Colored top strip --}}
        <div style="height:4px;background:{{ $mColor }};"></div>

        <div class="card-body" style="padding:20px;">
            <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:14px;">
                <div style="width:48px;height:48px;border-radius:13px;background:{{ $mColor }}18;color:{{ $mColor }};display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
                    <i class="{{ $mIcon }}"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <h3 style="font-size:15px;font-weight:800;color:var(--text);margin:0;">{{ $module->name }}</h3>
                        <span class="badge {{ $module->is_active?'badge-success':'badge-danger' }} badge-dot">
                            {{ $module->is_active?'Active':'Inactive' }}
                        </span>
                        @if(($module->visibility ?? 'public') === 'private')
                        <span style="font-size:10px;font-weight:700;background:#7c3aed18;color:#7c3aed;border:1px solid #7c3aed40;border-radius:20px;padding:2px 8px;letter-spacing:.5px;">
                            🔒 PRIVATE
                        </span>
                        @endif
                    </div>
                    <p style="font-size:12px;color:var(--text-muted);margin-top:4px;line-height:1.4;">{{ Str::limit($module->description??'',80) }}</p>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px;">
                <div style="background:#fafbff;border-radius:8px;padding:10px 12px;border:1px solid var(--border);">
                    <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:3px;">Commission</div>
                    <div style="font-size:16px;font-weight:800;color:var(--brand);">{{ $module->commission_value ?? 0 }}%</div>
                </div>
                <div style="background:#fafbff;border-radius:8px;padding:10px 12px;border:1px solid var(--border);">
                    <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:3px;">Sort Order</div>
                    <div style="font-size:16px;font-weight:800;color:var(--text);">{{ $module->sort_order ?? 0 }}</div>
                </div>
            </div>

            <div style="display:flex;gap:8px;">
                <form action="{{ route('admin.modules.toggle',$module->id) }}" method="POST" style="flex:1;">
                    @csrf
                    <button type="submit" class="btn btn-sm w-100 {{ $module->is_active ? '' : 'btn-success' }}"
                        style="{{ $module->is_active ? 'background:#fff5f5;color:var(--danger);border:1.5px solid #fecaca;' : '' }}">
                        @if($module->is_active)
                            <i class="fas fa-toggle-off"></i> Disable
                        @else
                            <i class="fas fa-toggle-on"></i> Enable
                        @endif
                    </button>
                </form>
                <a href="{{ route('admin.modules.show',$module->id) }}" class="btn btn-outline btn-sm">
                    <i class="fas fa-cog"></i> Configure
                </a>
            </div>
        </div>
    </div>
    @empty
    <div class="card" style="margin-bottom:0;grid-column:1/-1;">
        <div class="empty-state">
            <i class="fas fa-th-large"></i>
            <h3>No modules found</h3>
        </div>
    </div>
    @endforelse
</div>

@push('styles')
<style>.w-100{width:100%;}</style>
@endpush
@push('scripts')
<script>
const SEARCH_URL = '{{ route("admin.modules.search-users") }}';
const CSRF = '{{ csrf_token() }}';

function toggleBulkPanel() {
    const p = document.getElementById('bulkPanel');
    const c = document.getElementById('bulkChevron');
    const open = p.style.display === 'block';
    p.style.display = open ? 'none' : 'block';
    c.style.transform = open ? '' : 'rotate(180deg)';
    try { localStorage.setItem('bulkVisPanel', open ? '0' : '1'); } catch(e){}
}
try { if (localStorage.getItem('bulkVisPanel') === '1') toggleBulkPanel(); } catch(e){}

const _st = {};
function inlineSearch(moduleId, q) {
    clearTimeout(_st[moduleId]);
    const dd = document.getElementById('idd-' + moduleId);
    if (!q.trim()) { dd.style.display = 'none'; return; }
    _st[moduleId] = setTimeout(async () => {
        const res = await fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), {credentials:'same-origin'});
        const json = await res.json();
        if (!json.data?.length) {
            dd.innerHTML = '<div style="padding:10px 14px;font-size:12px;color:var(--text-muted);">No users found</div>';
            dd.style.display = 'block'; return;
        }
        dd.innerHTML = json.data.map(u =>
            `<div onclick="addBetaUserInline(${moduleId},${u.id},'${(u.name||'').replace(/'/g,"\\'")}','${(u.email||u.phone||'').replace(/'/g,"\\'")}');document.getElementById('isearch-${moduleId}').value='';"
                style="padding:8px 12px;cursor:pointer;display:flex;align-items:center;gap:8px;"
                onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
                <div style="width:28px;height:28px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;flex-shrink:0;">${(u.name||'?').charAt(0).toUpperCase()}</div>
                <div><div style="font-size:12px;font-weight:600;color:var(--text);">${u.name||'—'}</div><div style="font-size:10px;color:var(--text-muted);">${u.email||u.phone||'—'}</div></div>
            </div>`
        ).join('');
        dd.style.display = 'block';
    }, 280);
}

async function addBetaUserInline(moduleId, userId, name, email) {
    document.getElementById('idd-' + moduleId).style.display = 'none';
    if (document.getElementById(`chip-${moduleId}-${userId}`)) return;
    const res = await fetch(`/admin/modules/${moduleId}/beta-users`, {
        method:'POST', credentials:'same-origin',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body: JSON.stringify({user_id: userId})
    });
    if (!res.ok) return;
    const chip = document.createElement('span');
    chip.id = `chip-${moduleId}-${userId}`;
    chip.style.cssText = 'display:inline-flex;align-items:center;gap:5px;background:#7c3aed18;border:1px solid #7c3aed40;border-radius:20px;padding:3px 10px 3px 8px;font-size:11px;font-weight:600;color:#7c3aed;';
    chip.innerHTML = `<span style="width:18px;height:18px;border-radius:50%;background:#7c3aed;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:9px;font-weight:800;">${name.charAt(0).toUpperCase()}</span>${name}<button onclick="removeBetaUserInline(${moduleId},${userId},'${name.replace(/'/g,"\\'")}');this.closest('span').remove();" style="background:none;border:none;cursor:pointer;color:#7c3aed;padding:0;font-size:11px;line-height:1;">×</button>`;
    document.getElementById('bu-chips-' + moduleId).appendChild(chip);
}

async function removeBetaUserInline(moduleId, userId, name) {
    const res = await fetch(`/admin/modules/${moduleId}/beta-users/${userId}`, {
        method:'DELETE', credentials:'same-origin',
        headers:{'X-CSRF-TOKEN':CSRF}
    });
    if (res.ok) { const c = document.getElementById(`chip-${moduleId}-${userId}`); if(c) c.remove(); }
}
</script>
@endpush
@endsection
