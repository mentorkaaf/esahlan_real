@extends('employee.layouts.app')
@section('title', 'Wargelinta')

@push('head')
<style>
.ann-list { display:flex; flex-direction:column; gap:14px; max-width:800px; }

.ann-card {
  background:#fff; border:1.5px solid #e5e7eb; border-radius:16px;
  overflow:hidden; transition:box-shadow .2s,border-color .2s;
}
.ann-card:hover { box-shadow:0 4px 18px rgba(27,20,68,.08); border-color:#a5b4fc; }

.ann-card-inner { padding:20px 24px; }

.ann-meta {
  display:flex; align-items:center; gap:10px; flex-wrap:wrap;
  margin-bottom:12px;
}
.ann-audience {
  display:inline-flex; align-items:center; gap:5px;
  padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700;
}
.ann-all   { background:#dbeafe; color:#1e40af; }
.ann-dept  { background:#ede9fe; color:#5b21b6; }
.ann-when  { font-size:11px; color:#9ca3af; }
.ann-by    { font-size:11px; color:#9ca3af; }

.ann-title { font-size:16px; font-weight:800; color:#111827; margin-bottom:10px; line-height:1.3; }
.ann-body  { font-size:13px; color:#374151; line-height:1.65; }

.ann-expand-btn {
  display:inline-flex; align-items:center; gap:5px;
  font-size:12px; font-weight:700; color:var(--brand);
  background:none; border:none; cursor:pointer; padding:0; margin-top:8px;
}
.ann-expand-btn:hover { color:#e07000; }

.ann-full { display:none; margin-top:10px; }
.ann-full.open { display:block; }

.ann-new-dot {
  width:8px; height:8px; border-radius:50%; background:var(--brand); flex-shrink:0;
}

.empty-ann {
  text-align:center; padding:60px 20px; color:#9ca3af;
  background:#fff; border:1.5px dashed #e5e7eb; border-radius:16px;
}
</style>
@endpush

@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
  <h1 style="font-size:20px;font-weight:900;color:#111827;">
    <i class="fas fa-bullhorn" style="color:var(--brand);margin-right:8px;"></i>
    Wargelinta
  </h1>
  <span class="badge badge-blue">{{ $announcements->total() }} wargelin</span>
</div>

@if($announcements->isEmpty())
  <div class="empty-ann">
    <i class="fas fa-bullhorn" style="font-size:40px;opacity:.3;display:block;margin-bottom:12px;"></i>
    <p>Wargelin cusub ma jirto hadda.</p>
  </div>
@else

<div class="ann-list">
  @foreach($announcements as $ann)
  @php
    $isNew = $ann->published_at && $ann->published_at->gte(now()->subDays(3));
    $isLong = strlen(strip_tags($ann->body)) > 300;
    $preview = Str::limit(strip_tags($ann->body), 280);
  @endphp
  <div class="ann-card">
    <div class="ann-card-inner">

      {{-- Meta row --}}
      <div class="ann-meta">
        @if($isNew)<div class="ann-new-dot"></div>@endif
        <span class="ann-audience {{ $ann->audience === 'all' ? 'ann-all' : 'ann-dept' }}">
          <i class="{{ $ann->audience === 'all' ? 'fas fa-globe' : 'fas fa-users' }}" style="font-size:9px;"></i>
          {{ $ann->audience === 'all' ? 'Dhammaan' : ($ann->department?->name ?? 'Waaxda') }}
        </span>
        <span class="ann-when">
          <i class="fas fa-calendar-alt" style="font-size:10px;"></i>
          {{ $ann->published_at->format('d M Y') }}
          @if($isNew) <span style="color:#F7941D;font-weight:700;">(Cusub)</span> @endif
        </span>
        @if($ann->creator)
        <span class="ann-by">
          <i class="fas fa-user" style="font-size:10px;"></i>
          {{ $ann->creator->full_name ?? 'HR' }}
        </span>
        @endif
      </div>

      {{-- Title --}}
      <div class="ann-title">{{ $ann->title }}</div>

      {{-- Body --}}
      @if($isLong)
        <div class="ann-body">{{ $preview }}</div>
        <div class="ann-full" id="ann-{{ $ann->id }}">
          <div class="ann-body" style="white-space:pre-line;">{{ $ann->body }}</div>
        </div>
        <button class="ann-expand-btn" onclick="toggleAnn({{ $ann->id }},this)">
          <i class="fas fa-chevron-down" id="ann-icon-{{ $ann->id }}"></i>
          Wax badan akhri
        </button>
      @else
        <div class="ann-body" style="white-space:pre-line;">{{ $ann->body }}</div>
      @endif

    </div>
  </div>
  @endforeach
</div>

{{-- Pagination --}}
@if($announcements->hasPages())
<div style="margin-top:24px;">{{ $announcements->links() }}</div>
@endif

@endif

@endsection

@push('scripts')
<script>
function toggleAnn(id, btn) {
  const full = document.getElementById('ann-' + id);
  const icon = document.getElementById('ann-icon-' + id);
  const isOpen = full.classList.toggle('open');
  btn.innerHTML = (isOpen
    ? '<i class="fas fa-chevron-up" id="ann-icon-'+id+'"></i> Gaabiyso'
    : '<i class="fas fa-chevron-down" id="ann-icon-'+id+'"></i> Wax badan akhri');
}
</script>
@endpush
