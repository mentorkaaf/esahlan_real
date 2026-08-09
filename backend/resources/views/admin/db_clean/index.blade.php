@extends('admin.layouts.app')
@section('title', 'Clean Database')

@push('styles')
<style>
/* ── Page layout ─────────────────────────────────────────── */
.dbc-wrap { max-width:1080px; margin:0 auto; padding:28px 20px 60px; }

/* ── Page hero ───────────────────────────────────────────── */
.dbc-hero {
    background:linear-gradient(135deg,#1a1a2e 0%,#16213e 60%,#0f3460 100%);
    border-radius:20px; padding:28px 32px; margin-bottom:24px;
    display:flex; align-items:center; gap:20px; position:relative; overflow:hidden;
}
.dbc-hero::before {
    content:''; position:absolute; right:-40px; top:-40px;
    width:220px; height:220px; border-radius:50%;
    background:rgba(239,68,68,.08); pointer-events:none;
}
.dbc-hero-icon {
    width:60px; height:60px; flex-shrink:0;
    background:linear-gradient(135deg,#ef4444,#b91c1c);
    border-radius:16px; display:flex; align-items:center; justify-content:center;
    font-size:26px; box-shadow:0 8px 24px rgba(239,68,68,.35);
}
.dbc-hero-text h1 { color:#fff; font-size:22px; font-weight:800; margin:0 0 4px; }
.dbc-hero-text p  { color:#94a3b8; font-size:13px; margin:0; }
.dbc-hero-stats { margin-left:auto; display:flex; gap:24px; }
.dbc-hero-stat  { text-align:center; }
.dbc-hero-stat .val { font-size:26px; font-weight:900; color:#f87171; }
.dbc-hero-stat .lbl { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:.5px; }

/* ── Alert bar ───────────────────────────────────────────── */
.dbc-alert {
    display:flex; gap:12px; align-items:flex-start;
    background:#fff7f7; border:1.5px solid #fecaca; border-radius:14px;
    padding:14px 18px; margin-bottom:24px;
}
.dbc-alert-icon { font-size:20px; flex-shrink:0; margin-top:1px; }
.dbc-alert strong { display:block; color:#dc2626; font-size:13px; margin-bottom:2px; }
.dbc-alert span   { color:#7f1d1d; font-size:12.5px; line-height:1.5; }

/* ── Top action bar ──────────────────────────────────────── */
.dbc-topbar {
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:12px; margin-bottom:20px;
}
.dbc-sel-btns { display:flex; gap:8px; }
.dbc-sel-btn {
    background:#fff; border:1.5px solid #e2e8f0; color:#475569;
    padding:8px 16px; border-radius:10px; font-size:12.5px; font-weight:600;
    cursor:pointer; transition:.15s; white-space:nowrap;
}
.dbc-sel-btn:hover { border-color:#94a3b8; background:#f8fafc; }
.dbc-clean-btn {
    background:linear-gradient(135deg,#ef4444,#b91c1c);
    color:#fff; padding:10px 26px; border-radius:12px; font-size:14px;
    font-weight:700; cursor:pointer; border:none; transition:.2s;
    box-shadow:0 4px 14px rgba(239,68,68,.35); display:flex; align-items:center; gap:8px;
}
.dbc-clean-btn:hover:not(:disabled) { transform:translateY(-1px); box-shadow:0 6px 20px rgba(239,68,68,.45); }
.dbc-clean-btn:disabled { opacity:.45; cursor:not-allowed; transform:none !important; box-shadow:none; }
.dbc-selected-info { font-size:13px; color:#64748b; display:flex; gap:16px; }
.dbc-selected-info b { color:#dc2626; }

/* ── Groups ──────────────────────────────────────────────── */
.dbc-group { margin-bottom:16px; border-radius:14px; overflow:hidden; border:1.5px solid #e2e8f0; }
.dbc-group-hdr {
    display:flex; align-items:center; gap:12px; padding:13px 18px;
    background:linear-gradient(90deg,#f8fafc,#fff);
    cursor:pointer; user-select:none; transition:.15s;
}
.dbc-group-hdr:hover { background:#f1f5f9; }
.dbc-group-master { width:17px; height:17px; accent-color:#ef4444; cursor:pointer; flex-shrink:0; }
.dbc-group-name { flex:1; font-weight:700; font-size:13.5px; color:#1e293b; }
.dbc-group-meta { display:flex; align-items:center; gap:8px; }
.dbc-group-pill {
    background:#e2e8f0; color:#64748b; border-radius:20px;
    padding:2px 10px; font-size:11px; font-weight:700;
}
.dbc-group-arrow { color:#94a3b8; font-size:11px; transition:.2s; }
.dbc-group-arrow.open { transform:rotate(180deg); }

/* ── Table grid ──────────────────────────────────────────── */
.dbc-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr));
    border-top:1.5px solid #e2e8f0;
}
.dbc-grid.collapsed { display:none; }
.dbc-item {
    display:flex; align-items:center; gap:10px;
    padding:12px 16px; background:#fff; cursor:pointer;
    border-right:1px solid #f1f5f9; border-bottom:1px solid #f1f5f9;
    transition:.12s;
}
.dbc-item:hover { background:#fef2f2; }
.dbc-item input[type=checkbox] { width:15px; height:15px; accent-color:#ef4444; cursor:pointer; flex-shrink:0; }
.dbc-item-name { flex:1; font-size:12.5px; color:#334155; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.dbc-item-count {
    font-size:11.5px; font-weight:700; border-radius:20px; padding:2px 9px;
    white-space:nowrap; background:#f1f5f9; color:#94a3b8;
}
.dbc-item-count.hot { background:#fef2f2; color:#dc2626; }
.dbc-item-count.warm { background:#fff7ed; color:#ea580c; }

/* ── Log card ────────────────────────────────────────────── */
.dbc-log {
    margin-top:28px; background:#fff; border:1.5px solid #e2e8f0;
    border-radius:14px; overflow:hidden;
}
.dbc-log-hdr {
    padding:15px 20px; border-bottom:1.5px solid #f1f5f9;
    font-weight:800; font-size:14px; color:#1e293b;
    display:flex; align-items:center; gap:8px;
}
.dbc-log-table { width:100%; border-collapse:collapse; font-size:12.5px; }
.dbc-log-table th { padding:10px 16px; background:#f8fafc; color:#64748b; font-weight:600; text-align:left; border-bottom:1px solid #e2e8f0; }
.dbc-log-table td { padding:10px 16px; color:#374151; border-bottom:1px solid #f8fafc; vertical-align:top; }
.dbc-log-table tr:last-child td { border-bottom:none; }
.dbc-tag { display:inline-block; background:#f1f5f9; color:#475569; border-radius:5px; padding:1px 7px; font-size:11px; margin:1px; }
.dbc-badge-ok  { background:#dcfce7; color:#166534; border-radius:20px; padding:3px 10px; font-size:11px; font-weight:700; }
.dbc-badge-partial { background:#fef9c3; color:#854d0e; border-radius:20px; padding:3px 10px; font-size:11px; font-weight:700; }
.dbc-empty { padding:32px; text-align:center; color:#94a3b8; font-size:13px; }

/* ── Modal overlay ───────────────────────────────────────── */
#dbcOverlay {
    display:none; position:fixed; inset:0; z-index:99999;
    background:rgba(15,23,42,.6); backdrop-filter:blur(4px);
    align-items:center; justify-content:center;
}
#dbcOverlay.show { display:flex; }
.dbc-modal {
    background:#fff; border-radius:20px; padding:32px;
    max-width:500px; width:calc(100% - 32px);
    box-shadow:0 24px 80px rgba(0,0,0,.25);
    animation: dbcPop .2s ease;
}
@keyframes dbcPop { from { transform:scale(.94); opacity:0; } to { transform:scale(1); opacity:1; } }
.dbc-modal-top { text-align:center; margin-bottom:20px; }
.dbc-modal-ico {
    width:64px; height:64px; border-radius:50%;
    background:linear-gradient(135deg,#fef2f2,#fee2e2);
    display:flex; align-items:center; justify-content:center;
    font-size:28px; margin:0 auto 14px;
    box-shadow:0 4px 16px rgba(239,68,68,.2);
}
.dbc-modal-top h2 { font-size:19px; font-weight:800; color:#1e293b; margin:0 0 6px; }
.dbc-modal-top p  { font-size:13px; color:#64748b; margin:0; }
.dbc-modal-list {
    background:#fff7f7; border:1.5px solid #fecaca; border-radius:12px;
    padding:12px 16px; margin-bottom:16px; max-height:220px; overflow-y:auto;
}
.dbc-modal-row {
    display:flex; justify-content:space-between; align-items:center;
    padding:6px 0; border-bottom:1px solid #fecaca; font-size:13px;
}
.dbc-modal-row:last-child { border-bottom:none; }
.dbc-modal-row .tname { color:#374151; font-weight:500; }
.dbc-modal-row .trows { color:#dc2626; font-weight:800; }
.dbc-modal-total {
    text-align:center; font-size:15px; font-weight:800;
    color:#dc2626; margin-bottom:20px;
    background:#fef2f2; border-radius:10px; padding:10px;
}
.dbc-modal-btns { display:flex; gap:10px; }
.dbc-modal-btns button {
    flex:1; padding:13px; border-radius:12px; font-size:14px;
    font-weight:700; cursor:pointer; border:none; transition:.2s;
}
.dbc-modal-cancel { background:#f1f5f9; color:#475569; }
.dbc-modal-cancel:hover { background:#e2e8f0; }
.dbc-modal-confirm {
    background:linear-gradient(135deg,#ef4444,#b91c1c); color:#fff;
    box-shadow:0 4px 14px rgba(239,68,68,.3);
}
.dbc-modal-confirm:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(239,68,68,.45); }

@media(max-width:640px) {
    .dbc-grid { grid-template-columns:1fr 1fr; }
    .dbc-hero-stats { display:none; }
    .dbc-topbar { flex-direction:column; align-items:stretch; }
}
</style>
@endpush

@section('content')
<div class="dbc-wrap">

    {{-- Hero --}}
    <div class="dbc-hero">
        <div class="dbc-hero-icon">🗑️</div>
        <div class="dbc-hero-text">
            <h1>Clean Database</h1>
            <p>Safely truncate data tables. Critical tables are always protected.</p>
        </div>
        <div class="dbc-hero-stats">
            <div class="dbc-hero-stat">
                <div class="val" id="heroSelected">0</div>
                <div class="lbl">Selected</div>
            </div>
            <div class="dbc-hero-stat">
                <div class="val" id="heroRows">0</div>
                <div class="lbl">Rows</div>
            </div>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="dbc-alert" style="background:#f0fdf4;border-color:#86efac;">
        <span class="dbc-alert-icon">✅</span>
        <div><strong style="color:#166534;">Done!</strong><span style="color:#14532d;">{{ session('success') }}</span></div>
    </div>
    @endif
    @if(session('error'))
    <div class="dbc-alert">
        <span class="dbc-alert-icon">❌</span>
        <div><strong>Error</strong><span>{{ session('error') }}</span></div>
    </div>
    @endif

    {{-- Warning --}}
    <div class="dbc-alert">
        <span class="dbc-alert-icon">⚠️</span>
        <div>
            <strong>This permanently deletes data — cannot be undone!</strong>
            <span>Protected tables (users, vendors, products, settings, migrations) are hidden. Only disposable data tables are shown below.</span>
        </div>
    </div>

    {{-- Action bar --}}
    <div class="dbc-topbar">
        <div class="dbc-sel-btns">
            <button class="dbc-sel-btn" onclick="dbcSelectAll()">☑ Select All</button>
            <button class="dbc-sel-btn" onclick="dbcDeselectAll()">☐ Deselect All</button>
        </div>
        <div class="dbc-selected-info">
            <span><b id="infoCount">0</b> tables selected</span>
            <span><b id="infoRows">0</b> rows to delete</span>
        </div>
        <button class="dbc-clean-btn" id="cleanBtn" onclick="dbcOpenModal()" disabled>
            🗑️ Clean Selected
        </button>
    </div>

    {{-- Form --}}
    <form id="dbcForm" action="{{ route('admin.db-clean.run') }}" method="POST">
        @csrf

        @foreach($groups as $groupName => $items)
        @php $slug = Str::slug($groupName); @endphp
        <div class="dbc-group">
            {{-- Group header --}}
            <div class="dbc-group-hdr" onclick="dbcToggle('{{ $slug }}',this)">
                <input type="checkbox" class="dbc-group-master" data-group="{{ $slug }}"
                    onclick="event.stopPropagation();dbcGroupToggle(this)"
                    onchange="dbcUpdate()">
                <span class="dbc-group-name">{{ $groupName }}</span>
                <div class="dbc-group-meta">
                    <span class="dbc-group-pill">{{ count($items) }} tables</span>
                    <span class="dbc-group-arrow open">▼</span>
                </div>
            </div>
            {{-- Table grid --}}
            <div class="dbc-grid" id="grp-{{ $slug }}">
                @foreach($items as $item)
                <label class="dbc-item">
                    <input type="checkbox" name="tables[]" value="{{ $item['table'] }}"
                        class="dbc-chk" data-group="{{ $slug }}"
                        data-rows="{{ $item['count'] }}"
                        onchange="dbcUpdate()">
                    <span class="dbc-item-name" title="{{ $item['table'] }}">{{ $item['table'] }}</span>
                    <span class="dbc-item-count {{ $item['count'] > 1000 ? 'hot' : ($item['count'] > 100 ? 'warm' : '') }}">
                        {{ number_format($item['count']) }}
                    </span>
                </label>
                @endforeach
            </div>
        </div>
        @endforeach

    </form>

    {{-- History --}}
    <div class="dbc-log">
        <div class="dbc-log-hdr">📋 Recent Clean History</div>
        @if($logs->isEmpty())
        <div class="dbc-empty">No clean history yet</div>
        @else
        <table class="dbc-log-table">
            <thead>
                <tr>
                    <th>Admin</th>
                    <th>Tables</th>
                    <th>Rows Deleted</th>
                    <th>Status</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
                @foreach($logs as $log)
                <tr>
                    <td><b>{{ $log->admin?->name ?? 'System' }}</b></td>
                    <td>
                        @foreach($log->tables_cleaned as $t)
                        <span class="dbc-tag">{{ $t }}</span>
                        @endforeach
                    </td>
                    <td style="font-weight:800;color:#dc2626;font-size:15px;">
                        {{ number_format(array_sum($log->rows_deleted)) }}
                    </td>
                    <td>
                        @if($log->status === 'completed')
                        <span class="dbc-badge-ok">✅ Done</span>
                        @else
                        <span class="dbc-badge-partial">⚠️ Partial</span>
                        @endif
                    </td>
                    <td>{{ $log->created_at->diffForHumans() }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

</div><!-- /dbc-wrap -->

{{-- Confirm Modal (outside wrap, at body level) --}}
<div id="dbcOverlay">
    <div class="dbc-modal">
        <div class="dbc-modal-top">
            <div class="dbc-modal-ico">⚠️</div>
            <h2>Are you absolutely sure?</h2>
            <p>This will permanently delete all rows from the selected tables.</p>
        </div>
        <div class="dbc-modal-list" id="dbcModalList"></div>
        <div class="dbc-modal-total" id="dbcModalTotal"></div>
        <div class="dbc-modal-btns">
            <button class="dbc-modal-cancel" onclick="dbcCloseModal()">Cancel</button>
            <button class="dbc-modal-confirm" id="dbcConfirmBtn" onclick="dbcSubmit()">
                Yes, Clean Now
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Row counts keyed by table name
const _rowCounts = {
    @foreach($groups as $items)
    @foreach($items as $item)
    "{{ $item['table'] }}": {{ $item['count'] }},
    @endforeach
    @endforeach
};

function dbcGetChecked() {
    return [...document.querySelectorAll('.dbc-chk:checked')];
}

function dbcUpdate() {
    const checked = dbcGetChecked();
    const count   = checked.length;
    const rows    = checked.reduce((s, el) => s + (parseInt(el.dataset.rows) || 0), 0);

    document.getElementById('infoCount').textContent   = count;
    document.getElementById('infoRows').textContent    = rows.toLocaleString();
    document.getElementById('heroSelected').textContent = count;
    document.getElementById('heroRows').textContent    = rows.toLocaleString();
    document.getElementById('cleanBtn').disabled       = count === 0;

    // Sync group master checkboxes
    document.querySelectorAll('.dbc-group-master').forEach(m => {
        const g   = m.dataset.group;
        const all = [...document.querySelectorAll(`.dbc-chk[data-group="${g}"]`)];
        const chk = all.filter(x => x.checked).length;
        m.checked       = chk === all.length;
        m.indeterminate = chk > 0 && chk < all.length;
    });
}

function dbcGroupToggle(master) {
    const g = master.dataset.group;
    document.querySelectorAll(`.dbc-chk[data-group="${g}"]`).forEach(el => {
        el.checked = master.checked;
    });
    dbcUpdate();
}

function dbcToggle(slug, hdr) {
    const grid  = document.getElementById('grp-' + slug);
    const arrow = hdr.querySelector('.dbc-group-arrow');
    if (grid.classList.contains('collapsed')) {
        grid.classList.remove('collapsed');
        arrow.classList.add('open');
    } else {
        grid.classList.add('collapsed');
        arrow.classList.remove('open');
    }
}

function dbcSelectAll() {
    document.querySelectorAll('.dbc-chk').forEach(el => el.checked = true);
    dbcUpdate();
}

function dbcDeselectAll() {
    document.querySelectorAll('.dbc-chk').forEach(el => el.checked = false);
    dbcUpdate();
}

function dbcOpenModal() {
    const checked = dbcGetChecked();
    if (!checked.length) return;

    let html = '', total = 0;
    checked.forEach(el => {
        const r = parseInt(el.dataset.rows) || 0;
        total  += r;
        html   += `<div class="dbc-modal-row">
            <span class="tname">${el.value}</span>
            <span class="trows">${r.toLocaleString()} rows</span>
        </div>`;
    });

    document.getElementById('dbcModalList').innerHTML = html;
    document.getElementById('dbcModalTotal').textContent =
        `Total: ${total.toLocaleString()} rows will be permanently deleted`;
    document.getElementById('dbcOverlay').classList.add('show');
}

function dbcCloseModal() {
    document.getElementById('dbcOverlay').classList.remove('show');
}

function dbcSubmit() {
    const btn = document.getElementById('dbcConfirmBtn');
    btn.disabled     = true;
    btn.textContent  = '⏳ Cleaning...';
    document.getElementById('cleanBtn').disabled = true;
    document.getElementById('dbcForm').submit();
}

// Close on outside click
document.getElementById('dbcOverlay').addEventListener('click', function(e) {
    if (e.target === this) dbcCloseModal();
});
</script>
@endpush
