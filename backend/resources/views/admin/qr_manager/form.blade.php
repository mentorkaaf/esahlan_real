@extends('admin.layouts.app')
@section('title', $qr ? 'Edit QR Code' : 'Create QR Code')
@section('content')
<style>
.form-card{background:#fff;border-radius:16px;border:1.5px solid #e2e8f0;padding:28px 32px;max-width:720px;}
.form-title{font-size:20px;font-weight:800;color:#0f172a;margin-bottom:24px;display:flex;align-items:center;gap:10px;}
.form-group{margin-bottom:18px;}
.form-label{display:block;font-size:13px;font-weight:700;color:#374151;margin-bottom:6px;}
.form-label span{color:#ef4444;}
.form-control{width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;color:#0f172a;outline:none;transition:border-color .15s;background:#fff;}
.form-control:focus{border-color:#FF8A00;}
.form-hint{font-size:12px;color:#94a3b8;margin-top:4px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.type-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;}
.type-opt{display:none;}
.type-lbl{display:flex;flex-direction:column;align-items:center;gap:4px;padding:14px 8px;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;font-size:12px;font-weight:700;color:#64748b;transition:all .15s;text-align:center;}
.type-lbl .icon{font-size:22px;}
.type-opt:checked + .type-lbl{border-color:var(--accent,#FF8A00);background:rgba(255,138,0,.06);color:#FF8A00;}

.fields-section{border:1.5px solid #e2e8f0;border-radius:12px;padding:16px;margin-bottom:18px;}
.fields-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;}
.fields-title{font-size:13px;font-weight:700;color:#374151;}
.field-row{display:grid;grid-template-columns:1fr 1.5fr auto;gap:10px;margin-bottom:10px;align-items:center;}
.field-row input{padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;width:100%;}
.field-row input:focus{border-color:#FF8A00;}
.btn-remove-field{background:#fee2e2;color:#ef4444;border:none;border-radius:8px;padding:8px 12px;cursor:pointer;font-size:14px;}

.color-preview{display:inline-block;width:32px;height:32px;border-radius:8px;border:2px solid #e2e8f0;vertical-align:middle;margin-left:8px;}

.form-actions{display:flex;gap:12px;margin-top:24px;}
.btn{display:inline-flex;align-items:center;gap:7px;padding:11px 22px;border-radius:10px;font-size:14px;font-weight:700;text-decoration:none;border:none;cursor:pointer;}
.btn-primary{background:#FF8A00;color:#fff;}
.btn-primary:hover{background:#e07800;color:#fff;}
.btn-ghost{background:#f8fafc;color:#374151;border:1.5px solid #e2e8f0;}
.btn-ghost:hover{background:#f1f5f9;}
</style>

<div class="form-card">
    <div class="form-title">
        {{ $qr ? '✏️ Edit QR Code' : '➕ Create QR Code' }}
    </div>

    <form action="{{ $qr ? route('admin.qr-manager.update', $qr) : route('admin.qr-manager.store') }}"
          method="POST">
        @csrf
        @if($qr) @method('PUT') @endif

        {{-- Type --}}
        <div class="form-group">
            <label class="form-label">Type <span>*</span></label>
            <div class="type-grid">
                @foreach($types as $key => $t)
                <div>
                    <input type="radio" name="type" id="type_{{ $key }}" value="{{ $key }}"
                           class="type-opt"
                           {{ old('type', $qr?->type ?? 'custom') === $key ? 'checked' : '' }}>
                    <label for="type_{{ $key }}" class="type-lbl" style="--accent:{{ $t['color'] }}">
                        <span class="icon">{{ $t['icon'] }}</span>
                        {{ $t['label'] }}
                    </label>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Title --}}
        <div class="form-group">
            <label class="form-label" for="title">Admin Title <span>*</span></label>
            <input type="text" name="title" id="title" class="form-control"
                   value="{{ old('title', $qr?->title) }}"
                   placeholder="e.g. ILIGBEYLE Vendor Card" required>
            <div class="form-hint">Used in admin list only — not shown to public</div>
        </div>

        {{-- Headline + Color --}}
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="headline">Headline (Public Page)</label>
                <input type="text" name="headline" id="headline" class="form-control"
                       value="{{ old('headline', $qr?->headline) }}"
                       placeholder="e.g. ILIGBEYLE Restaurant">
            </div>
            <div class="form-group">
                <label class="form-label" for="color">Accent Color</label>
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="color" name="color" id="color" class="form-control"
                           value="{{ old('color', $qr?->color ?? '#FF8A00') }}"
                           style="width:56px;height:44px;padding:4px;cursor:pointer;">
                    <input type="text" id="color_hex" class="form-control"
                           value="{{ old('color', $qr?->color ?? '#FF8A00') }}"
                           style="font-family:monospace;" placeholder="#FF8A00"
                           oninput="document.getElementById('color').value=this.value">
                </div>
            </div>
        </div>

        {{-- Description --}}
        <div class="form-group">
            <label class="form-label" for="description">Description</label>
            <textarea name="description" id="description" class="form-control" rows="3"
                      placeholder="Short description shown on the public scan page...">{{ old('description', $qr?->description) }}</textarea>
        </div>

        {{-- Logo URL --}}
        <div class="form-group">
            <label class="form-label" for="logo_url">Logo / Image URL</label>
            <input type="url" name="logo_url" id="logo_url" class="form-control"
                   value="{{ old('logo_url', $qr?->logo_url) }}"
                   placeholder="https://...">
            <div class="form-hint">Image shown at top of public page (vendor logo, product photo, etc.)</div>
        </div>

        {{-- Key-Value Fields --}}
        <div class="fields-section">
            <div class="fields-header">
                <div class="fields-title">📋 Info Fields (shown on public page)</div>
                <button type="button" class="btn btn-ghost" style="padding:6px 12px;font-size:12px;" onclick="addField()">
                    + Add Field
                </button>
            </div>
            <div id="fields-container">
                @php $fields = old('fields', $qr?->fields ?? []); @endphp
                @foreach($fields as $i => $field)
                <div class="field-row">
                    <input type="text" name="fields[{{ $i }}][label]" value="{{ $field['label'] }}" placeholder="Label (e.g. Phone)">
                    <input type="text" name="fields[{{ $i }}][value]" value="{{ $field['value'] }}" placeholder="Value (e.g. +252...)">
                    <button type="button" class="btn-remove-field" onclick="this.parentElement.remove()">✕</button>
                </div>
                @endforeach
                @if(empty($fields))
                <div class="field-row">
                    <input type="text" name="fields[0][label]" placeholder="Label (e.g. Phone)">
                    <input type="text" name="fields[0][value]" placeholder="Value (e.g. +252...)">
                    <button type="button" class="btn-remove-field" onclick="this.parentElement.remove()">✕</button>
                </div>
                @endif
            </div>
        </div>

        {{-- CTA Button --}}
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="cta_label">Button Label</label>
                <input type="text" name="cta_label" id="cta_label" class="form-control"
                       value="{{ old('cta_label', $qr?->cta_label) }}"
                       placeholder="e.g. Order Now / Download App">
            </div>
            <div class="form-group">
                <label class="form-label" for="cta_url">Button URL</label>
                <input type="url" name="cta_url" id="cta_url" class="form-control"
                       value="{{ old('cta_url', $qr?->cta_url) }}"
                       placeholder="https://...">
            </div>
        </div>

        {{-- Active --}}
        <div class="form-group" style="display:flex;align-items:center;gap:10px;">
            <input type="checkbox" name="is_active" id="is_active" value="1"
                   {{ old('is_active', $qr?->is_active ?? true) ? 'checked' : '' }}
                   style="width:18px;height:18px;accent-color:#FF8A00;">
            <label for="is_active" style="font-size:14px;font-weight:600;color:#374151;margin:0;">
                Active (QR can be scanned)
            </label>
        </div>

        @if($errors->any())
        <div style="background:#fff5f5;border:1px solid #fecaca;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:#ef4444;font-size:13px;">
            <ul style="margin:0;padding-left:16px;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
        @endif

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> {{ $qr ? 'Save Changes' : 'Create QR Code' }}
            </button>
            <a href="{{ route('admin.qr-manager.index') }}" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<script>
let fieldIdx = {{ count($fields ?? []) }};
function addField() {
    const c = document.getElementById('fields-container');
    const row = document.createElement('div');
    row.className = 'field-row';
    row.innerHTML = `
        <input type="text" name="fields[${fieldIdx}][label]" placeholder="Label (e.g. Phone)">
        <input type="text" name="fields[${fieldIdx}][value]" placeholder="Value (e.g. +252...)">
        <button type="button" class="btn-remove-field" onclick="this.parentElement.remove()">✕</button>`;
    c.appendChild(row);
    fieldIdx++;
}
// Sync color picker ↔ hex input
document.getElementById('color').addEventListener('input', function() {
    document.getElementById('color_hex').value = this.value;
});
</script>
@endsection
