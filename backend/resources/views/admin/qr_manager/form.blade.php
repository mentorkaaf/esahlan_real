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

        {{-- Logo Upload --}}
        <div class="form-group">
            <label class="form-label">Logo / Image</label>
            <input type="hidden" name="logo_url" id="logo_url" value="{{ old('logo_url', $qr?->logo_url) }}">

            <div id="upload-zone"
                 onclick="document.getElementById('logo_file').click()"
                 style="border:2px dashed #e2e8f0;border-radius:12px;padding:20px;text-align:center;cursor:pointer;transition:all .15s;background:#fafafa;position:relative;">
                {{-- Preview --}}
                <div id="img-preview" style="{{ old('logo_url', $qr?->logo_url) ? '' : 'display:none;' }}">
                    <img id="preview-img"
                         src="{{ old('logo_url', $qr?->logo_url) }}"
                         style="max-height:120px;max-width:100%;border-radius:10px;object-fit:cover;margin-bottom:8px;">
                    <div style="font-size:12px;color:#64748b;" id="preview-url">{{ old('logo_url', $qr?->logo_url) ? Str::limit(old('logo_url', $qr?->logo_url), 60) : '' }}</div>
                </div>
                {{-- Placeholder --}}
                <div id="upload-placeholder" style="{{ old('logo_url', $qr?->logo_url) ? 'display:none;' : '' }}">
                    <div style="font-size:32px;margin-bottom:8px;">🖼️</div>
                    <div style="font-size:14px;font-weight:700;color:#374151;">Click to upload image</div>
                    <div style="font-size:12px;color:#94a3b8;margin-top:4px;">PNG, JPG, WEBP — max 4MB</div>
                </div>
                {{-- Uploading spinner --}}
                <div id="upload-spinner" style="display:none;">
                    <div style="font-size:14px;color:#FF8A00;font-weight:700;">⏳ Uploading...</div>
                </div>
                {{-- Change overlay --}}
                <div id="change-btn" onclick="event.stopPropagation();document.getElementById('logo_file').click()"
                     style="{{ old('logo_url', $qr?->logo_url) ? '' : 'display:none;' }}position:absolute;top:8px;right:8px;background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;padding:4px 10px;font-size:12px;font-weight:700;color:#374151;cursor:pointer;">
                    ✏️ Change
                </div>
            </div>
            <input type="file" id="logo_file" accept="image/*" style="display:none;" onchange="uploadLogo(this)">

            {{-- Or paste URL --}}
            <div style="margin-top:10px;display:flex;align-items:center;gap:8px;">
                <div style="flex:1;height:1px;background:#e2e8f0;"></div>
                <span style="font-size:12px;color:#94a3b8;white-space:nowrap;">or paste URL</span>
                <div style="flex:1;height:1px;background:#e2e8f0;"></div>
            </div>
            <input type="url" id="logo_url_text" class="form-control" style="margin-top:8px;"
                   value="{{ old('logo_url', $qr?->logo_url) }}"
                   placeholder="https://..."
                   oninput="setLogoUrl(this.value)">
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
// ── Logo Upload ────────────────────────────────────────────────────────────
function setLogoUrl(url) {
    document.getElementById('logo_url').value = url;
    if (url) {
        document.getElementById('preview-img').src = url;
        document.getElementById('preview-url').textContent = url.length > 60 ? url.substring(0,60)+'...' : url;
        document.getElementById('img-preview').style.display = '';
        document.getElementById('upload-placeholder').style.display = 'none';
        document.getElementById('change-btn').style.display = '';
    } else {
        document.getElementById('img-preview').style.display = 'none';
        document.getElementById('upload-placeholder').style.display = '';
        document.getElementById('change-btn').style.display = 'none';
    }
}

function uploadLogo(input) {
    if (!input.files[0]) return;
    const form = new FormData();
    form.append('image', input.files[0]);
    form.append('_token', '{{ csrf_token() }}');

    document.getElementById('upload-placeholder').style.display = 'none';
    document.getElementById('img-preview').style.display = 'none';
    document.getElementById('upload-spinner').style.display = '';

    fetch('{{ route("admin.qr-manager.upload-logo") }}', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            document.getElementById('upload-spinner').style.display = 'none';
            if (data.url) {
                setLogoUrl(data.url);
                document.getElementById('logo_url_text').value = data.url;
            } else {
                alert('Upload failed');
                document.getElementById('upload-placeholder').style.display = '';
            }
        })
        .catch(() => {
            document.getElementById('upload-spinner').style.display = 'none';
            document.getElementById('upload-placeholder').style.display = '';
            alert('Upload error, please try again.');
        });
}

// Drag & drop support
const zone = document.getElementById('upload-zone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor='#FF8A00'; zone.style.background='#fff7ed'; });
zone.addEventListener('dragleave', () => { zone.style.borderColor='#e2e8f0'; zone.style.background='#fafafa'; });
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.style.borderColor='#e2e8f0'; zone.style.background='#fafafa';
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        const dt = new DataTransfer();
        dt.items.add(file);
        const inp = document.getElementById('logo_file');
        inp.files = dt.files;
        uploadLogo(inp);
    }
});

// ── Fields ─────────────────────────────────────────────────────────────────
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
