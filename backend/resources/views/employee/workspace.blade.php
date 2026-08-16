@extends('employee.layouts.app')
@section('title', ($module->name ?? ucfirst($slug)) . ' — Workspace')

@push('head')
<style>
/* ── Status Tabs ──────────────────────────────────────────── */
.status-tabs { display:flex; gap:6px; flex-wrap:wrap; }
.status-tab {
  padding:7px 18px; border-radius:100px; font-size:12px; font-weight:700;
  border:1.5px solid #e5e7eb; background:#fff; color:#6b7280; cursor:pointer;
  text-decoration:none; transition:all .15s;
}
.status-tab:hover  { border-color:#1B1444; color:#1B1444; }
.status-tab.active { background:#1B1444; color:#fff; border-color:#1B1444; }
.status-tab .count {
  display:inline-flex; align-items:center; justify-content:center;
  background:rgba(255,255,255,.25); color:inherit;
  min-width:18px; height:18px; border-radius:20px; font-size:10px;
  margin-left:5px; padding:0 5px;
}
.status-tab:not(.active) .count { background:#f3f4f6; color:#374151; }

/* ── Work item cards ──────────────────────────────────────── */
.work-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:16px; }

.work-card {
  background:#fff; border:1.5px solid #e5e7eb; border-radius:16px;
  overflow:hidden; transition:box-shadow .2s, border-color .2s;
}
.work-card:hover { box-shadow:0 6px 24px rgba(27,20,68,.1); border-color:#c4b5fd; }

.work-card-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:14px 16px 10px; border-bottom:1px solid #f3f4f6; gap:10px;
}
.work-card-ref { font-size:11px; font-weight:800; color:#6b7280; font-family:monospace; }
.work-card-time { font-size:11px; color:#9ca3af; }

.work-card-body { padding:14px 16px; display:flex; flex-direction:column; gap:10px; }

.work-card-customer {
  display:flex; align-items:center; gap:10px;
}
.avatar-sm {
  width:32px; height:32px; border-radius:10px; background:var(--brand);
  display:flex; align-items:center; justify-content:center;
  font-size:12px; font-weight:800; color:#fff; flex-shrink:0;
}
.customer-name  { font-size:14px; font-weight:700; color:#111827; }
.customer-sub   { font-size:11px; color:#6b7280; }

.work-card-amount {
  font-size:18px; font-weight:900; color:#111827;
}
.work-card-amount span { font-size:12px; color:#6b7280; font-weight:500; }

.item-pills { display:flex; flex-wrap:wrap; gap:5px; }
.item-pill {
  background:#f3f4f6; border-radius:6px; padding:3px 8px;
  font-size:11px; color:#374151;
}
.item-pill strong { color:#111827; }

.work-card-footer {
  padding:10px 16px 14px; display:flex; gap:8px; flex-wrap:wrap;
  border-top:1px solid #f9fafb;
}

/* Status badge variants */
.badge-status {
  display:inline-flex; align-items:center; gap:4px;
  padding:3px 10px; border-radius:100px; font-size:11px; font-weight:700;
}
.s-pending    { background:#fef3c7; color:#92400e; }
.s-confirmed  { background:#dbeafe; color:#1e40af; }
.s-preparing,.s-picking,.s-collected,.s-processing,.s-picking {
  background:#fce7f3; color:#9d174d;
}
.s-ready,.s-packed,.s-packing,.s-drying,.s-washing,.s-in_progress {
  background:#ede9fe; color:#5b21b6;
}
.s-dispatched,.s-picked_up,.s-in_transit,.s-active {
  background:#e0e7ff; color:#3730a3;
}
.s-delivered,.s-completed,.s-resolved { background:#d1fae5; color:#065f46; }
.s-cancelled,.s-failed,.s-closed      { background:#fee2e2; color:#991b1b; }
.s-open                                { background:#fff7ed; color:#9a3412; }

/* Action buttons */
.btn-action {
  display:inline-flex; align-items:center; gap:6px;
  padding:7px 14px; border-radius:10px; font-size:12px; font-weight:700;
  border:none; cursor:pointer; transition:opacity .15s; text-decoration:none;
}
.btn-action:hover { opacity:.85; }
.btn-blue   { background:#2563eb; color:#fff; }
.btn-yellow { background:#d97706; color:#fff; }
.btn-green  { background:#059669; color:#fff; }
.btn-indigo { background:#4f46e5; color:#fff; }
.btn-red    { background:#dc2626; color:#fff; }
.btn-gray   { background:#6b7280; color:#fff; }

/* Note modal */
.note-modal-bg {
  display:none; position:fixed; inset:0; background:rgba(0,0,0,.5);
  z-index:9999; align-items:center; justify-content:center;
}
.note-modal-bg.open { display:flex; }
.note-modal {
  background:#fff; border-radius:20px; padding:28px; width:min(420px,94vw);
  box-shadow:0 20px 60px rgba(0,0,0,.2);
}
.note-modal h3 { font-size:16px; font-weight:800; color:#111827; margin-bottom:16px; }
.note-modal textarea {
  width:100%; border:1.5px solid #e5e7eb; border-radius:10px;
  padding:10px 13px; font-size:13px; resize:vertical; min-height:80px;
}
.note-modal-actions { display:flex; gap:8px; margin-top:14px; justify-content:flex-end; }

/* Stats row */
.stat-chips { display:flex; gap:10px; flex-wrap:wrap; }
.stat-chip {
  display:flex; align-items:center; gap:8px;
  background:#fff; border:1.5px solid #e5e7eb; border-radius:12px;
  padding:10px 16px;
}
.stat-chip-icon {
  width:32px; height:32px; border-radius:9px;
  display:flex; align-items:center; justify-content:center; font-size:14px;
  flex-shrink:0;
}
.stat-chip-val { font-size:20px; font-weight:900; color:#111827; line-height:1; }
.stat-chip-label { font-size:11px; color:#6b7280; }

/* Empty state */
.empty-state {
  text-align:center; padding:60px 20px; color:#9ca3af;
}
.empty-state i { font-size:40px; margin-bottom:12px; opacity:.4; }
.empty-state p { font-size:14px; }

/* Search */
.search-wrap { position:relative; }
.search-wrap input {
  padding:9px 13px 9px 38px; border:1.5px solid #e5e7eb;
  border-radius:10px; font-size:13px; width:220px; background:#fff;
}
.search-wrap i {
  position:absolute; left:13px; top:50%; transform:translateY(-50%);
  color:#9ca3af; font-size:13px;
}

/* Module color stripe */
.module-stripe {
  height:4px; border-radius:4px 4px 0 0;
}

/* Notes card */
.notes-text {
  font-size:12px; color:#6b7280; font-style:italic;
  background:#f9fafb; border-radius:8px; padding:8px 10px;
}

/* Appointment / ticket / exchange extras */
.extra-row {
  display:flex; gap:6px; flex-wrap:wrap; align-items:center;
}
.extra-pill {
  background:#f3f4f6; border-radius:6px; padding:3px 9px;
  font-size:11px; color:#374151;
}
.priority-high   { background:#fee2e2; color:#b91c1c; }
.priority-medium { background:#fef3c7; color:#92400e; }
.priority-low    { background:#d1fae5; color:#065f46; }
</style>
@endpush

@section('content')
@php
  $slug      = $module->slug ?? $assignment->module?->slug;
  $color     = $module->color ?? '#1B1444';
  $modName   = $module->name ?? ucfirst($slug);
  $icon      = $module->icon ?? 'fas fa-layer-group';
@endphp

{{-- ── Page Header ─────────────────────────────────────────────────── --}}
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
  <div style="display:flex;align-items:center;gap:14px;">
    <div style="width:44px;height:44px;border-radius:14px;background:{{ $color }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="{{ $icon }}" style="color:#fff;font-size:18px;"></i>
    </div>
    <div>
      <h1 style="font-size:20px;font-weight:900;color:#111827;line-height:1;">{{ $modName }}</h1>
      <div style="font-size:12px;color:#6b7280;margin-top:2px;">
        {{ $assignment->modulePosition?->name ?? $assignment->moduleDepartment?->name ?? 'Xildhibaanka' }}
        · <span style="font-weight:700;color:#1B1444;">{{ ucfirst($assignment->assignment_type) }}</span>
      </div>
    </div>
  </div>

  {{-- Performance score --}}
  @if($composite)
  <div style="display:flex;align-items:center;gap:8px;background:#fff;border:1.5px solid #e5e7eb;border-radius:12px;padding:10px 16px;">
    <div style="font-size:22px;font-weight:900;color:{{ $composite >= 80 ? '#059669' : ($composite >= 60 ? '#d97706' : '#dc2626') }};">{{ $composite }}<span style="font-size:13px;font-weight:500;color:#9ca3af;">%</span></div>
    <div style="font-size:11px;color:#6b7280;line-height:1.3;">Waxqabad<br>{{ now()->format('M Y') }}</div>
  </div>
  @endif
</div>

{{-- ── Stats Chips ──────────────────────────────────────────────────── --}}
<div class="stat-chips" style="margin-bottom:20px;">
  @php
    $statDefs = [
      'active' => ['icon'=>'fas fa-spinner','bg'=>'#ede9fe','ic'=>'#7c3aed','label'=>'Active'],
      'done'   => ['icon'=>'fas fa-check-circle','bg'=>'#d1fae5','ic'=>'#059669','label'=>'Done'],
      'today'  => ['icon'=>'fas fa-calendar-day','bg'=>'#dbeafe','ic'=>'#2563eb','label'=>'Today'],
      'total'  => ['icon'=>'fas fa-database','bg'=>'#f3f4f6','ic'=>'#374151','label'=>'Total'],
      'open'   => ['icon'=>'fas fa-envelope-open','bg'=>'#fff7ed','ic'=>'#c2410c','label'=>'Open'],
    ];
  @endphp
  @foreach($stats as $key => $val)
    @if(isset($statDefs[$key]))
    <div class="stat-chip">
      <div class="stat-chip-icon" style="background:{{ $statDefs[$key]['bg'] }};">
        <i class="{{ $statDefs[$key]['icon'] }}" style="color:{{ $statDefs[$key]['ic'] }};"></i>
      </div>
      <div>
        <div class="stat-chip-val">{{ $val }}</div>
        <div class="stat-chip-label">{{ $statDefs[$key]['label'] }}</div>
      </div>
    </div>
    @endif
  @endforeach
</div>

{{-- ── Filter Bar ───────────────────────────────────────────────────── --}}
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
  <div class="status-tabs">
    @foreach([
      ['active','Active', $stats['active'] ?? ($stats['open'] ?? '')],
      ['done',  'Done',   $stats['done'] ?? ($stats['resolved'] ?? '')],
      ['all',   'All',    $stats['total'] ?? ''],
    ] as [$val,$label,$cnt])
    <a href="{{ route('employee.workspace', $slug) }}?status={{ $val }}&search={{ urlencode($search) }}"
       class="status-tab {{ $statusFilter === $val ? 'active' : '' }}">
      {{ $label }}
      @if($cnt !== '') <span class="count">{{ $cnt }}</span> @endif
    </a>
    @endforeach
  </div>

  <div class="search-wrap">
    <form method="GET" action="{{ route('employee.workspace', $slug) }}">
      <input type="hidden" name="status" value="{{ $statusFilter }}">
      <i class="fas fa-search"></i>
      <input type="text" name="search" value="{{ $search }}" placeholder="Raadi…">
    </form>
  </div>
</div>

{{-- ── Work Cards Grid ──────────────────────────────────────────────── --}}
@if($workData->isEmpty())
  <div class="empty-state">
    <i class="fas fa-inbox"></i>
    <p>Wax shaqo ah lama helin — filter-ka bedel ama markii dambe soo eeg.</p>
  </div>
@else
<div class="work-grid">
  @foreach($workData as $item)
  @php
    $st         = $item['status'] ?? 'pending';
    $stClass    = 's-' . str_replace('-','_', $st);
    $stLabel    = ucwords(str_replace('_',' ',$st));
    $myTrans    = $transitions[$st] ?? [];
    $initials   = strtoupper(substr($item['customer'] ?? '?', 0, 2));
    $ts         = $item['placed_at'] ? \Carbon\Carbon::parse($item['placed_at'])->diffForHumans() : '';
  @endphp

  <div class="work-card">
    {{-- Module color stripe --}}
    <div class="module-stripe" style="background:{{ $color }};"></div>

    {{-- Card header --}}
    <div class="work-card-header">
      <span class="work-card-ref">{{ $item['ref'] ?? ('#'.$item['id']) }}</span>
      <div style="display:flex;align-items:center;gap:8px;">
        <span class="badge-status {{ $stClass }}">
          <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span>
          {{ $stLabel }}
        </span>
        <span class="work-card-time">{{ $ts }}</span>
      </div>
    </div>

    {{-- Card body --}}
    <div class="work-card-body">

      {{-- Customer --}}
      <div class="work-card-customer">
        <div class="avatar-sm" style="background:{{ $color }};">{{ $initials }}</div>
        <div>
          <div class="customer-name">{{ $item['customer'] }}</div>
          <div class="customer-sub">{{ $item['vendor'] }}</div>
        </div>
      </div>

      {{-- Amount --}}
      @if($item['amount'])
      <div class="work-card-amount">
        ${{ number_format($item['amount'], 2) }}
        <span>{{ $item['type'] === 'exchange' ? 'wadarta' : 'adeegga' }}</span>
      </div>
      @endif

      {{-- Order items --}}
      @if(!empty($item['items']) && count($item['items']))
      <div class="item-pills">
        @foreach(array_slice($item['items'], 0, 4) as $oi)
        <span class="item-pill"><strong>{{ $oi['qty'] }}×</strong> {{ $oi['name'] }}</span>
        @endforeach
        @if(count($item['items']) > 4)
        <span class="item-pill">+{{ count($item['items'])-4 }} kale</span>
        @endif
      </div>
      @endif

      {{-- Extra info (eHealth: scheduled, eTicket: subject/priority, eExchange: rate) --}}
      @if(!empty($item['extra']))
      <div class="extra-row">
        @if($item['type'] === 'appointment' && ($item['extra']['scheduled'] ?? null))
        <span class="extra-pill"><i class="fas fa-calendar-alt" style="font-size:10px;"></i> {{ \Carbon\Carbon::parse($item['extra']['scheduled'])->format('d M Y H:i') }}</span>
        @if($item['extra']['type'] ?? null)<span class="extra-pill">{{ $item['extra']['type'] }}</span>@endif
        @endif
        @if($item['type'] === 'ticket')
        <span class="extra-pill {{ 'priority-'.($item['extra']['priority'] ?? 'medium') }}"><i class="fas fa-flag" style="font-size:10px;"></i> {{ ucfirst($item['extra']['priority'] ?? 'medium') }}</span>
        @if($item['extra']['subject'] ?? null)
        <span class="extra-pill" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $item['extra']['subject'] }}</span>
        @endif
        @endif
        @if($item['type'] === 'exchange' && ($item['extra']['rate'] ?? null))
        <span class="extra-pill">Rate: {{ $item['extra']['rate'] }}</span>
        <span class="extra-pill">Converted: {{ number_format($item['extra']['converted'] ?? 0, 2) }}</span>
        @endif
      </div>
      @endif

      {{-- Address --}}
      @if($item['address'] ?? null)
      <div style="font-size:11px;color:#6b7280;display:flex;align-items:center;gap:5px;">
        <i class="fas fa-map-marker-alt" style="color:#F7941D;font-size:11px;"></i>
        {{ Str::limit($item['address'], 60) }}
      </div>
      @endif

      {{-- Notes / message --}}
      @if($item['notes'] ?? null)
      <div class="notes-text">{{ Str::limit($item['notes'], 80) }}</div>
      @endif

    </div>{{-- /body --}}

    {{-- Action buttons --}}
    @if(count($myTrans))
    <div class="work-card-footer">
      @foreach($myTrans as $tr)
      <button
        class="btn-action btn-{{ $tr['color'] }}"
        onclick="openNoteModal('{{ $slug }}','{{ $item['id'] }}','{{ $tr['status'] }}','{{ addslashes($tr['label']) }}')"
      >
        <i class="fas fa-arrow-right" style="font-size:10px;"></i>
        {{ $tr['label'] }}
      </button>
      @endforeach
    </div>
    @endif

  </div>
  @endforeach
</div>
@endif

{{-- ── Note / Confirm Modal ─────────────────────────────────────────── --}}
<div class="note-modal-bg" id="noteModalBg">
  <div class="note-modal">
    <h3 id="noteModalTitle">Xaqiiji Ficilka</h3>
    <p id="noteModalSub" style="font-size:12px;color:#6b7280;margin-bottom:14px;"></p>
    <form id="noteForm" method="POST">
      @csrf
      <input type="hidden" name="status" id="noteStatus">
      <textarea name="note" id="noteText" placeholder="Faallo (ikhtiyaari)…"></textarea>
      <div class="note-modal-actions">
        <button type="button" class="btn-action btn-gray" onclick="closeNoteModal()">Ka noqo</button>
        <button type="submit" class="btn-action btn-green" id="noteSubmit">Xaqiiji</button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
function openNoteModal(slug, id, status, label) {
  document.getElementById('noteModalTitle').textContent = label + ' — Xaqiiji';
  document.getElementById('noteModalSub').textContent   = 'Item #' + id + '  →  ' + label;
  document.getElementById('noteStatus').value = status;
  document.getElementById('noteText').value   = '';
  document.getElementById('noteForm').action  =
    '/employee/workspace/' + slug + '/status/' + id;
  document.getElementById('noteModalBg').classList.add('open');
}
function closeNoteModal() {
  document.getElementById('noteModalBg').classList.remove('open');
}
document.getElementById('noteModalBg').addEventListener('click', function(e){
  if(e.target === this) closeNoteModal();
});
</script>
@endpush
