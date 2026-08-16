@extends('employee.layouts.app')
@section('title', 'Dokumigeygii')

@push('head')
<style>
.doc-type-section { margin-bottom:28px; }
.doc-type-header {
  display:flex; align-items:center; gap:10px;
  margin-bottom:12px;
}
.doc-type-icon {
  width:32px; height:32px; border-radius:9px;
  display:flex; align-items:center; justify-content:center; font-size:14px;
}
.doc-type-title { font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:#374151; }
.doc-type-count { font-size:11px; color:#9ca3af; margin-left:4px; }

.doc-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:12px; }
.doc-card {
  background:#fff; border:1.5px solid #e5e7eb; border-radius:14px;
  padding:16px 18px; display:flex; align-items:center; gap:14px;
  transition:box-shadow .2s,border-color .2s;
}
.doc-card:hover { box-shadow:0 4px 16px rgba(27,20,68,.08); border-color:#c4b5fd; }
.doc-icon {
  width:44px; height:44px; border-radius:12px; flex-shrink:0;
  display:flex; align-items:center; justify-content:center; font-size:18px;
}
.doc-name  { font-size:13px; font-weight:700; color:#111827; }
.doc-meta  { font-size:11px; color:#6b7280; margin-top:3px; }
.doc-expiry-warn { font-size:11px; color:#d97706; margin-top:3px; }
.doc-expiry-ok   { font-size:11px; color:#059669; margin-top:3px; }

.doc-view-btn {
  margin-left:auto; flex-shrink:0;
  background:#f3f4f6; color:#374151; border:none;
  border-radius:8px; padding:6px 12px; font-size:11px; font-weight:700;
  cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:5px;
  transition:background .15s;
}
.doc-view-btn:hover { background:#e5e7eb; }

.empty-docs {
  text-align:center; padding:60px 20px; color:#9ca3af;
  background:#fff; border:1.5px dashed #e5e7eb; border-radius:16px;
}
</style>
@endpush

@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
  <h1 style="font-size:20px;font-weight:900;color:#111827;">
    <i class="fas fa-folder-open" style="color:var(--brand);margin-right:8px;"></i>
    Dokumigeygii
  </h1>
  <span class="badge badge-blue">{{ $documents->flatten()->count() }} dokumin</span>
</div>

@php
$typeConfig = [
  'contract'    => ['label'=>'Heshiisyada',    'icon'=>'fas fa-file-contract',   'bg'=>'#dbeafe','ic'=>'#2563eb'],
  'id'          => ['label'=>'Aqoonsiga',       'icon'=>'fas fa-id-card',          'bg'=>'#ede9fe','ic'=>'#7c3aed'],
  'certificate' => ['label'=>'Shahaadooyinka',  'icon'=>'fas fa-certificate',      'bg'=>'#d1fae5','ic'=>'#059669'],
  'cv'          => ['label'=>'CV / Resume',     'icon'=>'fas fa-user-tie',         'bg'=>'#fce7f3','ic'=>'#9d174d'],
  'photo'       => ['label'=>'Sawirada',        'icon'=>'fas fa-camera',           'bg'=>'#fff7ed','ic'=>'#c2410c'],
  'other'       => ['label'=>'Kuwa kale',       'icon'=>'fas fa-file-alt',         'bg'=>'#f3f4f6','ic'=>'#6b7280'],
];
@endphp

@if($documents->isEmpty())
  <div class="empty-docs">
    <i class="fas fa-folder-open" style="font-size:40px;opacity:.3;display:block;margin-bottom:12px;"></i>
    <p>Wali dokumin kuma jiro akoon-kaaga.</p>
    <p style="margin-top:6px;font-size:12px;">HR-ga la xiriir si dokumiyo lagu daro.</p>
  </div>
@else
  @foreach($typeConfig as $typeKey => $cfg)
    @if($documents->has($typeKey))
    <div class="doc-type-section">
      <div class="doc-type-header">
        <div class="doc-type-icon" style="background:{{ $cfg['bg'] }};">
          <i class="{{ $cfg['icon'] }}" style="color:{{ $cfg['ic'] }};"></i>
        </div>
        <span class="doc-type-title">{{ $cfg['label'] }}</span>
        <span class="doc-type-count">({{ $documents[$typeKey]->count() }})</span>
      </div>
      <div class="doc-grid">
        @foreach($documents[$typeKey] as $doc)
        @php
          $isExpiring = $doc->expires_at && $doc->expires_at->diffInDays(now()) <= 30 && $doc->expires_at->isFuture();
          $isExpired  = $doc->expires_at && $doc->expires_at->isPast();
        @endphp
        <div class="doc-card">
          <div class="doc-icon" style="background:{{ $cfg['bg'] }};">
            <i class="{{ $cfg['icon'] }}" style="color:{{ $cfg['ic'] }};"></i>
          </div>
          <div style="flex:1;min-width:0;">
            <div class="doc-name" title="{{ $doc->title }}">{{ Str::limit($doc->title,30) }}</div>
            <div class="doc-meta">Lagu daray: {{ $doc->created_at->format('d M Y') }}</div>
            @if($doc->expires_at)
              @if($isExpired)
                <div class="doc-expiry-warn"><i class="fas fa-exclamation-triangle"></i> Xilligii dhacay {{ $doc->expires_at->format('d M Y') }}</div>
              @elseif($isExpiring)
                <div class="doc-expiry-warn"><i class="fas fa-clock"></i> Dhici doona {{ $doc->expires_at->diffForHumans() }}</div>
              @else
                <div class="doc-expiry-ok"><i class="fas fa-shield-alt"></i> Valid until {{ $doc->expires_at->format('d M Y') }}</div>
              @endif
            @endif
          </div>
          <a href="{{ Storage::url($doc->file_path) }}" target="_blank" class="doc-view-btn">
            <i class="fas fa-external-link-alt" style="font-size:10px;"></i> Arag
          </a>
        </div>
        @endforeach
      </div>
    </div>
    @endif
  @endforeach
@endif

@endsection
