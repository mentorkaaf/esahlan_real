@extends('admin.layouts.app')
@section('title', 'Module: ' . $module->name)
@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="{{ $module->icon }}"></i> {{ $module->name }}</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.modules.index') }}">Modules</a></li>
            <li class="breadcrumb-item active">{{ $module->name }}</li>
        </ol>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">Module Settings</div>
        <div class="card-body">
            <form action="{{ route('admin.modules.update', $module->id) }}" method="POST">
                @csrf @method('PATCH')
                <div class="form-group">
                    <label class="form-label">Commission Type</label>
                    <select name="commission_type" class="form-control">
                        <option value="percentage" {{ $module->commission_type === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                        <option value="fixed" {{ $module->commission_type === 'fixed' ? 'selected' : '' }}>Fixed ($)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Commission Value</label>
                    <input type="number" name="commission_value" class="form-control" value="{{ $module->commission_value }}" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ $module->description }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Module Info</div>
        <div class="card-body">
            <table>
                <tr><td class="text-muted" style="width:130px;padding:8px 12px 8px 0;">Slug</td><td><code>{{ $module->slug }}</code></td></tr>
                <tr><td class="text-muted">Status</td><td><span class="badge {{ $module->is_active ? 'badge-success' : 'badge-danger' }}">{{ $module->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
                <tr><td class="text-muted">Commission</td><td><strong>{{ $module->commission_value }}{{ $module->commission_type === 'percentage' ? '%' : '$' }}</strong></td></tr>
                <tr><td class="text-muted">Sort Order</td><td>{{ $module->sort_order }}</td></tr>
            </table>
            <div class="mt-3">
                <form action="{{ route('admin.modules.toggle', $module->id) }}" method="POST">
                    @csrf
                    <button class="btn {{ $module->is_active ? 'btn-danger' : 'btn-success' }}">
                        {{ $module->is_active ? 'Disable Module' : 'Enable Module' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ── Visibility Control ────────────────────────────────────────────── --}}
<div class="card" style="margin-top:20px;">
    <div class="card-header" style="display:flex;align-items:center;gap:10px;">
        <i class="fas fa-eye" style="color:var(--brand);"></i>
        Visibility Control
        <span style="font-size:11px;color:var(--text-muted);margin-left:auto;">Controls who sees this module in the app</span>
    </div>
    <div class="card-body">

        {{-- Current status + toggle --}}
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;padding:16px;border-radius:12px;background:{{ $module->visibility === 'private' ? '#7c3aed12' : '#10b98112' }};border:1.5px solid {{ $module->visibility === 'private' ? '#7c3aed40' : '#10b98140' }};">
            <div style="font-size:28px;">{{ $module->visibility === 'private' ? '🔒' : '🌐' }}</div>
            <div style="flex:1;">
                <div style="font-weight:800;font-size:15px;color:var(--text);">
                    {{ $module->visibility === 'private' ? 'Private (Beta)' : 'Public (All Users)' }}
                </div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
                    {{ $module->visibility === 'private'
                        ? 'Only users added below can see this module in the app'
                        : 'All customers can see and use this module' }}
                </div>
            </div>
            <div style="display:flex;gap:8px;">
                <form action="{{ route('admin.modules.visibility', $module->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="visibility" value="public">
                    <button type="submit" class="btn btn-sm {{ $module->visibility === 'public' ? 'btn-success' : 'btn-outline' }}">
                        🌐 Public
                    </button>
                </form>
                <form action="{{ route('admin.modules.visibility', $module->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="visibility" value="private">
                    <button type="submit" class="btn btn-sm {{ $module->visibility === 'private' ? 'btn-primary' : 'btn-outline' }}"
                        style="{{ $module->visibility === 'private' ? 'background:#7c3aed;border-color:#7c3aed;' : '' }}">
                        🔒 Private
                    </button>
                </form>
            </div>
        </div>

        {{-- Beta users section (shown only when private) --}}
        <div id="betaSection" style="{{ $module->visibility !== 'private' ? 'opacity:.4;pointer-events:none;' : '' }}">
            <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px;">
                Beta Users <span style="font-weight:400;color:var(--text-muted);font-size:12px;">— users who can see this module while it's private</span>
            </div>

            {{-- Search + add --}}
            <div style="display:flex;gap:8px;margin-bottom:14px;">
                <div style="flex:1;position:relative;">
                    <input id="userSearch" type="text" placeholder="Search by name, email or phone..."
                        class="form-control" style="padding-right:36px;"
                        oninput="searchUsers(this.value)">
                    <i class="fas fa-search" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"></i>
                    <div id="userDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:999;background:var(--surface);border:1px solid var(--border);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);margin-top:4px;max-height:240px;overflow-y:auto;"></div>
                </div>
            </div>

            {{-- Current beta users list --}}
            <div id="betaUsersList">
                @php $betaUsers = $module->betaUsers()->select('users.id','users.name','users.email','users.phone')->get(); @endphp
                @forelse($betaUsers as $u)
                <div class="beta-user-row" id="bur-{{ $u->id }}" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;background:var(--bg);border:1px solid var(--border);margin-bottom:6px;">
                    <div style="width:36px;height:36px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0;">
                        {{ strtoupper(substr($u->name,0,1)) }}
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;color:var(--text);">{{ $u->name }}</div>
                        <div style="font-size:11px;color:var(--text-muted);">{{ $u->email ?? $u->phone ?? '—' }}</div>
                    </div>
                    <button onclick="removeBetaUser({{ $u->id }},'{{ addslashes($u->name) }}')"
                        style="background:none;border:none;cursor:pointer;color:var(--danger);font-size:14px;padding:4px 8px;" title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                @empty
                <div id="emptyBeta" style="text-align:center;padding:24px;color:var(--text-muted);font-size:13px;">
                    <i class="fas fa-user-slash" style="font-size:24px;margin-bottom:8px;display:block;"></i>
                    No beta users yet. Add users above.
                </div>
                @endforelse
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
const MODULE_ID = {{ $module->id }};
const SEARCH_URL = '{{ route("admin.modules.search-users") }}';
const ADD_URL    = '{{ route("admin.modules.beta-users.add", $module->id) }}';
const REMOVE_URL = '{{ url("admin/modules/".$module->id."/beta-users") }}';
const CSRF       = '{{ csrf_token() }}';

let searchTimeout;
function searchUsers(q) {
    clearTimeout(searchTimeout);
    if (!q.trim()) { document.getElementById('userDropdown').style.display='none'; return; }
    searchTimeout = setTimeout(async () => {
        const res = await fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), {credentials:'same-origin'});
        const json = await res.json();
        const dd = document.getElementById('userDropdown');
        if (!json.data?.length) { dd.innerHTML='<div style="padding:12px 16px;color:var(--text-muted);font-size:13px;">No users found</div>'; dd.style.display='block'; return; }
        dd.innerHTML = json.data.map(u => `
            <div onclick="addBetaUser(${u.id},'${(u.name||'').replace(/'/g,"\\'")}','${(u.email||u.phone||'').replace(/'/g,"\\'")}');"
                style="padding:10px 14px;cursor:pointer;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border);"
                onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
                <div style="width:32px;height:32px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;">${u.name?u.name.charAt(0).toUpperCase():'?'}</div>
                <div><div style="font-size:13px;font-weight:600;color:var(--text);">${u.name||'—'}</div><div style="font-size:11px;color:var(--text-muted);">${u.email||u.phone||'—'}</div></div>
            </div>`).join('');
        dd.style.display = 'block';
    }, 300);
}

async function addBetaUser(id, name, email) {
    document.getElementById('userDropdown').style.display = 'none';
    document.getElementById('userSearch').value = '';
    if (document.getElementById('bur-' + id)) return; // already added
    const res = await fetch(ADD_URL, {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},credentials:'same-origin',body:JSON.stringify({user_id:id})});
    if (!res.ok) return alert('Failed to add user');
    const empty = document.getElementById('emptyBeta');
    if (empty) empty.remove();
    const row = document.createElement('div');
    row.className = 'beta-user-row'; row.id = 'bur-' + id;
    row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;background:var(--bg);border:1px solid var(--border);margin-bottom:6px;';
    row.innerHTML = `
        <div style="width:36px;height:36px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0;">${name.charAt(0).toUpperCase()}</div>
        <div style="flex:1;min-width:0;"><div style="font-weight:600;font-size:13px;color:var(--text);">${name}</div><div style="font-size:11px;color:var(--text-muted);">${email}</div></div>
        <button onclick="removeBetaUser(${id},'${name.replace(/'/g,"\\'")}')" style="background:none;border:none;cursor:pointer;color:var(--danger);font-size:14px;padding:4px 8px;"><i class="fas fa-times"></i></button>`;
    document.getElementById('betaUsersList').appendChild(row);
}

async function removeBetaUser(id, name) {
    if (!confirm('Remove ' + name + ' from beta access?')) return;
    const res = await fetch(REMOVE_URL + '/' + id, {method:'DELETE',headers:{'X-CSRF-TOKEN':CSRF},credentials:'same-origin'});
    if (!res.ok) return alert('Failed to remove user');
    const row = document.getElementById('bur-' + id);
    if (row) row.remove();
    if (!document.querySelector('.beta-user-row')) {
        document.getElementById('betaUsersList').innerHTML = '<div id="emptyBeta" style="text-align:center;padding:24px;color:var(--text-muted);font-size:13px;"><i class="fas fa-user-slash" style="font-size:24px;margin-bottom:8px;display:block;"></i>No beta users yet.</div>';
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#userSearch') && !e.target.closest('#userDropdown')) {
        document.getElementById('userDropdown').style.display = 'none';
    }
});
</script>
@endpush
@endsection
