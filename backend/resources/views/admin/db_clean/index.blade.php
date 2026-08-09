@extends('admin.layouts.app')
@section('title', 'Clean Database')

@push('css_or_js')
<style>
.dc-page { max-width: 1100px; margin: 0 auto; padding: 28px 16px; }
.dc-header { display:flex; align-items:center; gap:14px; margin-bottom:28px; }
.dc-icon { width:48px; height:48px; background:linear-gradient(135deg,#ef4444,#dc2626); border-radius:14px;
    display:flex; align-items:center; justify-content:center; font-size:22px; }
.dc-title { font-size:22px; font-weight:800; color:#1a1a2e; margin:0; }
.dc-subtitle { font-size:13px; color:#6b7280; margin:0; }

.dc-warning { background:#fef2f2; border:1.5px solid #fecaca; border-radius:12px; padding:14px 18px;
    display:flex; gap:12px; align-items:flex-start; margin-bottom:24px; }
.dc-warning-icon { font-size:18px; margin-top:2px; }
.dc-warning b { color:#dc2626; display:block; margin-bottom:2px; }
.dc-warning span { color:#7f1d1d; font-size:13px; }

.dc-summary-bar { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px 20px;
    display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
.dc-summary-count { font-size:28px; font-weight:900; color:#ef4444; }
.dc-summary-label { font-size:12px; color:#6b7280; }
.dc-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }

.dc-btn-select { background:#f3f4f6; border:1px solid #d1d5db; color:#374151; padding:8px 16px;
    border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; transition:.2s; }
.dc-btn-select:hover { background:#e5e7eb; }
.dc-btn-clean { background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; padding:10px 22px;
    border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; border:none;
    transition:.2s; box-shadow:0 2px 8px rgba(239,68,68,.3); }
.dc-btn-clean:hover { transform:translateY(-1px); box-shadow:0 4px 16px rgba(239,68,68,.4); }
.dc-btn-clean:disabled { opacity:.5; cursor:not-allowed; transform:none; }

.dc-group { margin-bottom:20px; }
.dc-group-header { display:flex; align-items:center; gap:10px; padding:10px 16px;
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px 10px 0 0;
    font-weight:700; font-size:13px; color:#374151; cursor:pointer; user-select:none; }
.dc-group-check { accent-color:#ef4444; width:16px; height:16px; }
.dc-group-label { flex:1; }
.dc-group-badge { background:#e5e7eb; color:#6b7280; border-radius:20px; padding:2px 10px; font-size:11px; font-weight:700; }
.dc-group-toggle { color:#9ca3af; font-size:12px; }

.dc-table-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(240px,1fr)); gap:1px;
    background:#e2e8f0; border:1px solid #e2e8f0; border-top:none; border-radius:0 0 10px 10px; overflow:hidden; }
.dc-table-item { background:#fff; padding:12px 16px; display:flex; align-items:center; gap:10px;
    cursor:pointer; transition:.15s; }
.dc-table-item:hover { background:#fef2f2; }
.dc-table-item input[type=checkbox] { accent-color:#ef4444; width:15px; height:15px; cursor:pointer; flex-shrink:0; }
.dc-table-name { flex:1; font-size:13px; color:#374151; font-weight:500; }
.dc-table-count { font-size:12px; font-weight:700; background:#f3f4f6; color:#6b7280;
    border-radius:20px; padding:2px 10px; min-width:36px; text-align:center; }
.dc-table-count.has-rows { background:#fef2f2; color:#dc2626; }

/* Log table */
.dc-log-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; margin-top:28px; overflow:hidden; }
.dc-log-header { padding:16px 20px; border-bottom:1px solid #e5e7eb; font-weight:700; color:#1a1a2e;
    display:flex; align-items:center; gap:8px; }
.dc-log-table { width:100%; border-collapse:collapse; font-size:13px; }
.dc-log-table th { padding:10px 16px; background:#f8fafc; color:#6b7280; font-weight:600;
    text-align:left; border-bottom:1px solid #e5e7eb; }
.dc-log-table td { padding:10px 16px; border-bottom:1px solid #f1f5f9; color:#374151; }
.dc-log-table tr:last-child td { border-bottom:none; }
.dc-badge-ok  { background:#dcfce7; color:#166534; border-radius:20px; padding:2px 10px; font-size:11px; font-weight:700; }
.dc-badge-partial { background:#fef9c3; color:#854d0e; border-radius:20px; padding:2px 10px; font-size:11px; font-weight:700; }

/* Confirm modal */
.dc-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5);
    z-index:9999; align-items:center; justify-content:center; }
.dc-overlay.active { display:flex; }
.dc-modal { background:#fff; border-radius:16px; padding:28px; max-width:480px; width:calc(100% - 32px);
    box-shadow:0 20px 60px rgba(0,0,0,.2); }
.dc-modal-icon { width:56px; height:56px; background:#fef2f2; border-radius:50%; display:flex;
    align-items:center; justify-content:center; font-size:26px; margin:0 auto 16px; }
.dc-modal h3 { text-align:center; font-size:18px; font-weight:800; color:#1a1a2e; margin:0 0 8px; }
.dc-modal p { text-align:center; color:#6b7280; font-size:14px; margin:0 0 20px; }
.dc-modal-list { background:#fef2f2; border-radius:10px; padding:12px 16px; margin-bottom:20px;
    max-height:200px; overflow-y:auto; }
.dc-modal-list div { display:flex; justify-content:space-between; padding:4px 0;
    font-size:13px; color:#374151; border-bottom:1px solid #fecaca; }
.dc-modal-list div:last-child { border-bottom:none; }
.dc-modal-total { font-weight:800; color:#dc2626; font-size:15px; text-align:center; margin-bottom:20px; }
.dc-modal-btns { display:flex; gap:10px; }
.dc-modal-btns button { flex:1; padding:12px; border-radius:10px; font-size:14px; font-weight:700;
    cursor:pointer; border:none; transition:.2s; }
.dc-modal-cancel { background:#f3f4f6; color:#374151; }
.dc-modal-cancel:hover { background:#e5e7eb; }
.dc-modal-confirm { background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff;
    box-shadow:0 2px 8px rgba(239,68,68,.3); }
.dc-modal-confirm:hover { transform:translateY(-1px); }

@media(max-width:640px) {
    .dc-table-grid { grid-template-columns:1fr 1fr; }
    .dc-summary-bar { flex-direction:column; align-items:flex-start; }
}
</style>
@endpush

@section('content')
<div class="dc-page">

    {{-- Header --}}
    <div class="dc-header">
        <div class="dc-icon">🗑️</div>
        <div>
            <h1 class="dc-title">Clean Database</h1>
            <p class="dc-subtitle">Select tables to truncate. This action cannot be undone.</p>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="dc-warning" style="background:#f0fdf4;border-color:#bbf7d0;margin-bottom:20px;">
        <span class="dc-warning-icon">✅</span>
        <div><b style="color:#166534;">Done!</b><span style="color:#14532d;">{{ session('success') }}</span></div>
    </div>
    @endif
    @if(session('error'))
    <div class="dc-warning">
        <span class="dc-warning-icon">❌</span>
        <div><b>Error</b><span>{{ session('error') }}</span></div>
    </div>
    @endif

    {{-- Warning --}}
    <div class="dc-warning">
        <span class="dc-warning-icon">⚠️</span>
        <div>
            <b>Careful — this permanently deletes data!</b>
            <span>Critical tables (users, vendors, products, settings) are protected and cannot be selected. Only data tables like orders, carts, and notifications can be cleaned.</span>
        </div>
    </div>

    {{-- Summary bar --}}
    <div class="dc-summary-bar">
        <div>
            <div class="dc-summary-count" id="selectedCount">0</div>
            <div class="dc-summary-label">tables selected</div>
        </div>
        <div>
            <div class="dc-summary-count" id="selectedRows">0</div>
            <div class="dc-summary-label">total rows to delete</div>
        </div>
        <div class="dc-actions">
            <button class="dc-btn-select" onclick="selectAll()">Select All</button>
            <button class="dc-btn-select" onclick="deselectAll()">Deselect All</button>
            <button class="dc-btn-clean" id="cleanBtn" onclick="openConfirm()" disabled>
                🗑️ Clean Selected
            </button>
        </div>
    </div>

    {{-- Table groups --}}
    <form id="cleanForm" action="{{ route('admin.db-clean.run') }}" method="POST">
        @csrf
        @foreach($groups as $groupName => $items)
        <div class="dc-group">
            <div class="dc-group-header" onclick="toggleGroup(this)">
                <input type="checkbox" class="dc-group-check group-master"
                    data-group="{{ Str::slug($groupName) }}"
                    onclick="event.stopPropagation(); toggleGroupCheck(this)">
                <span class="dc-group-label">{{ $groupName }}</span>
                <span class="dc-group-badge">{{ count($items) }} tables</span>
                <span class="dc-group-toggle">▼</span>
            </div>
            <div class="dc-table-grid" id="group-{{ Str::slug($groupName) }}">
                @foreach($items as $item)
                <label class="dc-table-item">
                    <input type="checkbox" name="tables[]" value="{{ $item['table'] }}"
                        data-count="{{ $item['count'] }}"
                        data-group="{{ Str::slug($groupName) }}"
                        class="table-check"
                        onchange="updateSummary()">
                    <span class="dc-table-name">{{ $item['table'] }}</span>
                    <span class="dc-table-count {{ $item['count'] > 0 ? 'has-rows' : '' }}">
                        {{ number_format($item['count']) }}
                    </span>
                </label>
                @endforeach
            </div>
        </div>
        @endforeach
    </form>

    {{-- History log --}}
    <div class="dc-log-card">
        <div class="dc-log-header">📋 Recent Clean History</div>
        @if($logs->isEmpty())
        <div style="padding:24px;text-align:center;color:#9ca3af;font-size:13px;">No clean history yet.</div>
        @else
        <table class="dc-log-table">
            <thead>
                <tr>
                    <th>Admin</th>
                    <th>Tables Cleaned</th>
                    <th>Rows Deleted</th>
                    <th>Status</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
                @foreach($logs as $log)
                <tr>
                    <td>{{ $log->admin?->name ?? 'System' }}</td>
                    <td>
                        @foreach($log->tables_cleaned as $t)
                        <span style="background:#f3f4f6;border-radius:4px;padding:1px 6px;font-size:11px;margin:1px;display:inline-block;">{{ $t }}</span>
                        @endforeach
                    </td>
                    <td style="font-weight:700;color:#dc2626;">
                        {{ number_format(array_sum($log->rows_deleted)) }}
                    </td>
                    <td>
                        @if($log->status === 'completed')
                        <span class="dc-badge-ok">✅ Done</span>
                        @else
                        <span class="dc-badge-partial">⚠️ Partial</span>
                        @endif
                    </td>
                    <td>{{ $log->created_at->diffForHumans() }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

{{-- Confirm Modal --}}
<div class="dc-overlay" id="confirmOverlay">
    <div class="dc-modal">
        <div class="dc-modal-icon">⚠️</div>
        <h3>Are you sure?</h3>
        <p>This will permanently delete all rows from the selected tables. This cannot be undone!</p>
        <div class="dc-modal-list" id="modalList"></div>
        <div class="dc-modal-total" id="modalTotal"></div>
        <div class="dc-modal-btns">
            <button class="dc-modal-cancel" onclick="closeConfirm()">Cancel</button>
            <button class="dc-modal-confirm" onclick="submitClean()">Yes, Clean Now</button>
        </div>
    </div>
</div>

@push('script')
<script>
// Row counts from PHP (table → count)
const rowCounts = {
    @foreach($groups as $items)
    @foreach($items as $item)
    "{{ $item['table'] }}": {{ $item['count'] }},
    @endforeach
    @endforeach
};

function getChecked() {
    return [...document.querySelectorAll('.table-check:checked')];
}

function updateSummary() {
    const checked = getChecked();
    const count   = checked.length;
    const rows    = checked.reduce((s, el) => s + (rowCounts[el.value] || 0), 0);
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('selectedRows').textContent  = rows.toLocaleString();
    document.getElementById('cleanBtn').disabled = count === 0;
    // update group master checkboxes
    document.querySelectorAll('.group-master').forEach(master => {
        const group = master.dataset.group;
        const all   = [...document.querySelectorAll(`.table-check[data-group="${group}"]`)];
        const chk   = all.filter(x => x.checked);
        master.checked       = chk.length === all.length;
        master.indeterminate = chk.length > 0 && chk.length < all.length;
    });
}

function toggleGroupCheck(master) {
    const group = master.dataset.group;
    document.querySelectorAll(`.table-check[data-group="${group}"]`).forEach(el => {
        el.checked = master.checked;
    });
    updateSummary();
}

function toggleGroup(header) {
    const grid = header.nextElementSibling;
    const arrow = header.querySelector('.dc-group-toggle');
    if (grid.style.display === 'none') {
        grid.style.display = '';
        arrow.textContent = '▼';
    } else {
        grid.style.display = 'none';
        arrow.textContent = '▶';
    }
}

function selectAll() {
    document.querySelectorAll('.table-check').forEach(el => el.checked = true);
    updateSummary();
}

function deselectAll() {
    document.querySelectorAll('.table-check').forEach(el => el.checked = false);
    updateSummary();
}

function openConfirm() {
    const checked = getChecked();
    if (!checked.length) return;
    let html = '';
    let total = 0;
    checked.forEach(el => {
        const count = rowCounts[el.value] || 0;
        total += count;
        html += `<div><span>${el.value}</span><span style="font-weight:700;color:#dc2626;">${count.toLocaleString()} rows</span></div>`;
    });
    document.getElementById('modalList').innerHTML  = html;
    document.getElementById('modalTotal').textContent = `Total: ${total.toLocaleString()} rows will be deleted`;
    document.getElementById('confirmOverlay').classList.add('active');
}

function closeConfirm() {
    document.getElementById('confirmOverlay').classList.remove('active');
}

function submitClean() {
    closeConfirm();
    document.getElementById('cleanBtn').disabled = true;
    document.getElementById('cleanBtn').textContent = '⏳ Cleaning...';
    document.getElementById('cleanForm').submit();
}

// Close overlay on outside click
document.getElementById('confirmOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeConfirm();
});
</script>
@endpush
@endsection
