@extends('admin.layouts.app')
@section('title', 'QR Code: ' . $qr->title)
@section('content')
<style>
.qs-wrap{display:grid;grid-template-columns:340px 1fr;gap:24px;align-items:start;}
.qs-card{background:#fff;border-radius:16px;border:1.5px solid #e2e8f0;overflow:hidden;}
.qs-qr-panel{padding:28px;text-align:center;}
.qs-qr-svg{display:inline-block;background:#fff;border:2px solid #e2e8f0;border-radius:16px;padding:16px;margin-bottom:16px;}
.qs-qr-svg svg{display:block;width:200px;height:200px;}
.qs-url{font-size:12px;font-family:monospace;color:#64748b;word-break:break-all;background:#f8fafc;border-radius:8px;padding:8px 12px;margin-bottom:16px;}
.qs-actions{display:flex;flex-direction:column;gap:8px;}
.qs-btn{display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;border-radius:10px;font-size:14px;font-weight:700;text-decoration:none;cursor:pointer;border:none;transition:all .15s;}
.qs-btn-primary{background:#FF8A00;color:#fff;}
.qs-btn-primary:hover{background:#e07800;color:#fff;}
.qs-btn-ghost{background:#f8fafc;color:#374151;border:1.5px solid #e2e8f0;}
.qs-btn-ghost:hover{background:#f1f5f9;}
.qs-btn-danger{background:#fff5f5;color:#ef4444;border:1.5px solid #fecaca;}

.qs-info{padding:24px;}
.qs-info-title{font-size:20px;font-weight:800;color:#0f172a;margin-bottom:6px;}
.qs-badge{display:inline-flex;align-items:center;gap:5px;border-radius:99px;padding:4px 12px;font-size:12px;font-weight:700;margin-bottom:16px;}
.qs-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f1f5f9;font-size:14px;}
.qs-row:last-child{border-bottom:none;}
.qs-key{color:#64748b;font-weight:500;}
.qs-val{color:#0f172a;font-weight:600;text-align:right;max-width:60%;}
.qs-stat-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:16px 0;}
.qs-stat{background:#f8fafc;border-radius:12px;padding:14px;text-align:center;}
.qs-stat-num{font-size:24px;font-weight:800;color:#0f172a;}
.qs-stat-label{font-size:12px;color:#64748b;margin-top:2px;}
.qs-preview-link{display:inline-flex;align-items:center;gap:6px;color:#FF8A00;font-size:13px;font-weight:700;text-decoration:none;margin-top:12px;}
.qs-preview-link:hover{text-decoration:underline;}
@media(max-width:700px){.qs-wrap{grid-template-columns:1fr;}}
</style>

@if(session('success'))
<div style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;border-radius:10px;padding:12px 16px;margin-bottom:18px;font-weight:600;">
    ✅ {{ session('success') }}
</div>
@endif

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
    <a href="{{ route('admin.qr-manager.index') }}" style="color:#64748b;text-decoration:none;font-size:14px;">
        ← Back to QR Codes
    </a>
    <span style="color:#cbd5e1;">|</span>
    <a href="{{ route('admin.qr-manager.edit', $qr) }}"
       style="display:inline-flex;align-items:center;gap:6px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:9px;padding:7px 14px;font-size:13px;font-weight:700;color:#374151;text-decoration:none;">
        <i class="fas fa-edit"></i> Edit
    </a>
    <form action="{{ route('admin.qr-manager.destroy', $qr) }}" method="POST"
          onsubmit="return confirm('Delete this QR code permanently?')">
        @csrf @method('DELETE')
        <button style="display:inline-flex;align-items:center;gap:6px;background:#fff5f5;border:1.5px solid #fecaca;border-radius:9px;padding:7px 14px;font-size:13px;font-weight:700;color:#ef4444;cursor:pointer;">
            <i class="fas fa-trash"></i> Delete
        </button>
    </form>
</div>

<div class="qs-wrap">
    {{-- QR Panel --}}
    <div class="qs-card">
        <div style="height:5px;background:{{ $qr->color }};"></div>
        <div class="qs-qr-panel">
            <div class="qs-qr-svg">{!! $svg !!}</div>
            <div class="qs-url">{{ $qr->publicUrl() }}</div>
            <div class="qs-actions">
                <a href="{{ route('admin.qr-manager.download', $qr) }}" class="qs-btn qs-btn-primary">
                    <i class="fas fa-download"></i> Download PNG (600px)
                </a>
                <a href="{{ $qr->publicUrl() }}" target="_blank" class="qs-btn qs-btn-ghost">
                    <i class="fas fa-external-link-alt"></i> Preview Public Page
                </a>
                <button onclick="copyUrl()" class="qs-btn qs-btn-ghost">
                    <i class="fas fa-copy"></i> Copy URL
                </button>
            </div>
        </div>
    </div>

    {{-- Info Panel --}}
    <div class="qs-card">
        <div class="qs-info">
            @php $typeInfo = $types[$qr->type] ?? $types['custom']; @endphp
            <div class="qs-info-title">{{ $qr->title }}</div>
            <div class="qs-badge" style="background:{{ $qr->color }}22;color:{{ $qr->color }};">
                {{ $typeInfo['icon'] }} {{ $typeInfo['label'] }}
                @if(!$qr->is_active)
                <span style="background:#fef3c7;color:#92400e;border-radius:99px;padding:2px 8px;font-size:11px;margin-left:6px;">Inactive</span>
                @endif
            </div>

            <div class="qs-stat-row">
                <div class="qs-stat">
                    <div class="qs-stat-num" style="color:{{ $qr->color }};">{{ number_format($qr->scan_count) }}</div>
                    <div class="qs-stat-label">Total Scans</div>
                </div>
                <div class="qs-stat">
                    <div class="qs-stat-num">{{ $qr->created_at->format('d M') }}</div>
                    <div class="qs-stat-label">Created</div>
                </div>
            </div>

            @if($qr->headline)
            <div class="qs-row"><span class="qs-key">Headline</span><span class="qs-val">{{ $qr->headline }}</span></div>
            @endif
            @if($qr->description)
            <div class="qs-row"><span class="qs-key">Description</span><span class="qs-val">{{ Str::limit($qr->description, 80) }}</span></div>
            @endif
            @if($qr->cta_label)
            <div class="qs-row"><span class="qs-key">Button</span><span class="qs-val">{{ $qr->cta_label }}</span></div>
            @endif
            @if($qr->fields)
            @foreach($qr->fields as $field)
            <div class="qs-row">
                <span class="qs-key">{{ $field['label'] }}</span>
                <span class="qs-val">{{ $field['value'] }}</span>
            </div>
            @endforeach
            @endif
            <div class="qs-row"><span class="qs-key">Token</span><span class="qs-val" style="font-family:monospace;">{{ $qr->token }}</span></div>
            <div class="qs-row"><span class="qs-key">Created by</span><span class="qs-val">{{ $qr->creator?->name ?? '—' }}</span></div>
        </div>
    </div>
</div>

<script>
function copyUrl() {
    navigator.clipboard.writeText('{{ $qr->publicUrl() }}');
    alert('URL copied!');
}
</script>
@endsection
