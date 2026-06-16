@extends('admin.layouts.app')
@section('title', 'Roles & Access')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Roles &amp; Access</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li>Roles &amp; Access</li>
        </ul>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:16px;padding:12px 16px;border-radius:10px;background:#e7f8ee;color:#0f7a43;font-weight:600;">
    <i class="fas fa-check-circle"></i> {{ session('success') }}
</div>
@endif
@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:16px;padding:12px 16px;border-radius:10px;background:#fdecec;color:#c0392b;font-weight:600;">
    <i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}
</div>
@endif

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;width:100%;align-items:center;">
            <div style="flex:1;min-width:200px;">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search name, phone, email…" value="{{ $search }}">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
            </div>
            @if($search)
            <a href="{{ route('admin.access.index') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            @endif
        </form>
    </div>

    <div style="overflow-x:auto;">
    <table class="table">
        <thead>
            <tr>
                <th>User</th>
                <th>Contact</th>
                <th>Role</th>
                <th>Assigned Modules</th>
                <th style="text-align:right;">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $u)
            @php $modIds = $u->managedModules->pluck('id')->all(); @endphp
            <tr>
                <td>
                    <div style="font-weight:700;color:#0c0148;">{{ $u->name }}</div>
                    <div style="font-size:12px;color:#8a8da3;">#{{ $u->id }}</div>
                </td>
                <td style="font-size:13px;color:#555;">
                    {{ $u->phone ?? '—' }}<br>
                    <span style="color:#8a8da3;">{{ $u->email ?? '' }}</span>
                </td>
                <td>
                    <span style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;
                        background:{{ $u->role?->slug === 'employee' ? '#fff3e0' : '#eef0ff' }};
                        color:{{ $u->role?->slug === 'employee' ? '#FF8A00' : '#140465' }};">
                        {{ $u->role?->name ?? 'No role' }}
                    </span>
                </td>
                <td>
                    @if($u->role?->slug === 'employee')
                        @forelse($u->managedModules as $m)
                            <span style="display:inline-block;margin:2px;padding:3px 9px;border-radius:14px;font-size:11px;font-weight:600;background:#f0f2f5;color:#444;">{{ $m->name }}</span>
                        @empty
                            <span style="color:#c0392b;font-size:12px;">⚠ none assigned</span>
                        @endforelse
                    @else
                        <span style="color:#bbb;font-size:12px;">—</span>
                    @endif
                </td>
                <td style="text-align:right;">
                    <button type="button" class="btn btn-outline btn-sm js-manage"
                        data-id="{{ $u->id }}"
                        data-name="{{ $u->name }}"
                        data-role="{{ $u->role_id }}"
                        data-modules="{{ implode(',', $modIds) }}">
                        <i class="fas fa-user-shield"></i> Manage
                    </button>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;color:#8a8da3;padding:30px;">No users found.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>

    <div style="padding:14px;">{{ $users->links() }}</div>
</div>

<!-- ── Manage Access modal ─────────────────────────────────────────────────── -->
<div id="accessModal" style="display:none;position:fixed;inset:0;background:rgba(7,0,59,0.55);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#fff;border-radius:16px;max-width:520px;width:100%;max-height:90vh;overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <form id="accessForm" method="POST">
      @csrf
      @method('PUT')
      <div style="padding:18px 20px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between;">
        <h3 style="margin:0;color:#0c0148;font-size:17px;font-weight:800;">Manage Access — <span id="amName"></span></h3>
        <button type="button" class="js-close" style="border:0;background:none;font-size:22px;color:#999;cursor:pointer;">&times;</button>
      </div>

      <div style="padding:20px;">
        <label style="display:block;font-weight:700;color:#0c0148;margin-bottom:6px;">Role</label>
        <select name="role_id" id="amRole" class="form-control" style="width:100%;margin-bottom:18px;">
          @foreach($roles as $r)
          <option value="{{ $r->id }}" data-slug="{{ $r->slug }}">{{ $r->name }}</option>
          @endforeach
        </select>

        <div id="amModulesWrap" style="display:none;">
          <label style="display:block;font-weight:700;color:#0c0148;margin-bottom:8px;">
            Modules this employee can manage
          </label>
          <div style="font-size:12px;color:#8a8da3;margin-bottom:10px;">
            The employee gets full management access to only the modules ticked below.
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            @foreach($modules as $m)
            <label style="display:flex;align-items:center;gap:8px;padding:9px 11px;border:1px solid #e6e6ef;border-radius:10px;cursor:pointer;font-size:13px;">
              <input type="checkbox" name="modules[]" value="{{ $m->id }}" class="am-mod" data-mod="{{ $m->id }}">
              <span style="font-weight:600;color:#333;">{{ $m->name }}</span>
            </label>
            @endforeach
          </div>
        </div>
      </div>

      <div style="padding:16px 20px;border-top:1px solid #eee;display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="btn btn-outline js-close">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var modal   = document.getElementById('accessModal');
  var form    = document.getElementById('accessForm');
  var roleSel = document.getElementById('amRole');
  var wrap    = document.getElementById('amModulesWrap');
  var nameEl  = document.getElementById('amName');
  var baseUrl = "{{ url('admin/access') }}";

  function toggleModules() {
    var slug = roleSel.options[roleSel.selectedIndex].getAttribute('data-slug');
    wrap.style.display = (slug === 'employee') ? 'block' : 'none';
  }

  function open(btn) {
    nameEl.textContent = btn.getAttribute('data-name');
    form.action = baseUrl + '/' + btn.getAttribute('data-id');
    roleSel.value = btn.getAttribute('data-role') || '';

    var assigned = (btn.getAttribute('data-modules') || '').split(',').filter(Boolean);
    document.querySelectorAll('.am-mod').forEach(function (cb) {
      cb.checked = assigned.indexOf(cb.getAttribute('data-mod')) !== -1;
    });

    toggleModules();
    modal.style.display = 'flex';
  }

  function close() { modal.style.display = 'none'; }

  document.querySelectorAll('.js-manage').forEach(function (b) {
    b.addEventListener('click', function () { open(b); });
  });
  document.querySelectorAll('.js-close').forEach(function (b) {
    b.addEventListener('click', close);
  });
  roleSel.addEventListener('change', toggleModules);
  modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
})();
</script>

@endsection
