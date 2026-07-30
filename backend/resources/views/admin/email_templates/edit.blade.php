@extends('admin.layouts.app')
@section('title', 'Edit: ' . $template->name)
@section('content')
<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('admin.email-templates.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i></a>
    <div>
      <h4 class="fw-bold mb-0"><i class="fas fa-edit text-warning me-2"></i>{{ $template->name }}</h4>
      <small class="text-muted">Changes go live instantly — no deployment needed</small>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  <div class="row g-4">
    {{-- Editor --}}
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm" style="border-radius:16px;">
        <div class="card-body p-4">
          <form method="POST" action="{{ route('admin.email-templates.update', $template) }}">
            @csrf @method('PUT')

            <div class="mb-3">
              <label class="form-label fw-bold">Subject</label>
              <input type="text" name="subject" class="form-control" value="{{ old('subject', $template->subject) }}" required>
              <div class="form-text">You can use variables like <code>@{{ name }}</code> in the subject too.</div>
            </div>

            <div class="mb-3">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <label class="form-label fw-bold mb-0">HTML Body</label>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="togglePreview()">
                    <i class="fas fa-eye me-1"></i>Live Preview
                  </button>
                </div>
              </div>
              <textarea name="body" id="body-editor" class="form-control font-monospace" rows="22" style="font-size:.8rem;resize:vertical;">{{ old('body', $template->body) }}</textarea>
            </div>

            <div class="form-check form-switch mb-4">
              <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ $template->is_active ? 'checked' : '' }}>
              <label class="form-check-label" for="is_active">Template Active</label>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-warning fw-bold px-4">
                <i class="fas fa-save me-2"></i>Save Changes
              </button>
              <a href="{{ route('admin.email-templates.preview', $template) }}" target="_blank" class="btn btn-outline-secondary">
                <i class="fas fa-external-link-alt me-1"></i>Full Preview
              </a>
            </div>
          </form>
        </div>
      </div>
    </div>

    {{-- Sidebar --}}
    <div class="col-lg-4">
      {{-- Variables --}}
      <div class="card border-0 shadow-sm mb-3" style="border-radius:16px;">
        <div class="card-body p-4">
          <h6 class="fw-bold mb-3"><i class="fas fa-code text-warning me-2"></i>Available Variables</h6>
          @foreach($template->variables ?? [] as $var)
            <div class="d-flex align-items-center justify-content-between mb-2">
              <code class="px-2 py-1 rounded" style="background:rgba(99,102,241,0.1);color:#6366f1;">{{ $var }}</code>
              <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="insertVar('{{ $var }}')">Insert</button>
            </div>
          @endforeach
          <div class="d-flex align-items-center justify-content-between mb-2">
            <code class="px-2 py-1 rounded" style="background:rgba(99,102,241,0.1);color:#6366f1;">@{{ year }}</code>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="insertVar('@{{year}}')">Insert</button>
          </div>
        </div>
      </div>

      {{-- Send Test --}}
      <div class="card border-0 shadow-sm" style="border-radius:16px;">
        <div class="card-body p-4">
          <h6 class="fw-bold mb-3"><i class="fas fa-paper-plane text-warning me-2"></i>Send Test Email</h6>
          <form method="POST" action="{{ route('admin.email-templates.send-test', $template) }}">
            @csrf
            <div class="mb-3">
              <input type="email" name="email" class="form-control" placeholder="your@email.com" value="{{ auth()->user()->email }}" required>
            </div>
            <button type="submit" class="btn btn-outline-warning w-100 fw-bold">
              <i class="fas fa-flask me-2"></i>Send Test
            </button>
          </form>
          <div class="mt-3 p-2 rounded small text-muted" style="background:rgba(0,0,0,0.03);">
            Test uses sample data: name=<em>Test User</em>, code=<em>999888</em>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Live Preview Panel --}}
  <div id="preview-panel" class="mt-4" style="display:none;">
    <div class="card border-0 shadow-sm" style="border-radius:16px;">
      <div class="card-header bg-white border-bottom fw-bold py-3">
        <i class="fas fa-eye text-warning me-2"></i>Live Preview
        <button type="button" class="btn btn-sm btn-outline-secondary float-end" onclick="togglePreview()">Close</button>
      </div>
      <div class="card-body p-0">
        <iframe id="preview-frame" style="width:100%;height:600px;border:none;border-radius:0 0 16px 16px;"></iframe>
      </div>
    </div>
  </div>
</div>

<script>
function togglePreview() {
    const panel = document.getElementById('preview-panel');
    const frame = document.getElementById('preview-frame');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        updatePreview();
        panel.scrollIntoView({behavior:'smooth'});
    } else {
        panel.style.display = 'none';
    }
}
function updatePreview() {
    const body = document.getElementById('body-editor').value;
    const frame = document.getElementById('preview-frame');
    const doc = frame.contentDocument || frame.contentWindow.document;
    doc.open(); doc.write(body); doc.close();
}
function insertVar(v) {
    const ta = document.getElementById('body-editor');
    const start = ta.selectionStart;
    ta.value = ta.value.slice(0, start) + v + ta.value.slice(ta.selectionEnd);
    ta.selectionStart = ta.selectionEnd = start + v.length;
    ta.focus();
}
document.getElementById('body-editor').addEventListener('input', function() {
    if (document.getElementById('preview-panel').style.display !== 'none') updatePreview();
});
</script>
@endsection
