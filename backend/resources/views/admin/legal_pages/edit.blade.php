@extends('admin.layouts.app')
@section('title', 'Edit: ' . $page->title_en)
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Edit Legal Page</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('admin.legal-pages.index') }}">Legal Pages</a></li>
            <li>{{ $page->title_en }}</li>
        </ul>
    </div>
</div>

<form action="{{ route('admin.legal-pages.update', $page) }}" method="POST">
    @csrf @method('PUT')

    {{-- Tab switcher --}}
    <div style="display:flex;gap:8px;margin-bottom:16px;">
        <button type="button" onclick="showTab('en')" id="tab-en"
            style="padding:8px 20px;border-radius:8px;border:2px solid #1a73e8;background:#1a73e8;color:#fff;font-weight:700;cursor:pointer;">
            🇬🇧 English
        </button>
        <button type="button" onclick="showTab('so')" id="tab-so"
            style="padding:8px 20px;border-radius:8px;border:2px solid #e0e0e0;background:#fff;color:#333;font-weight:600;cursor:pointer;">
            🇸🇴 Somali
        </button>
    </div>

    {{-- English tab --}}
    <div id="pane-en">
        <div class="card" style="margin-bottom:16px;">
            <div class="card-body" style="padding:20px;">
                <div style="margin-bottom:14px;">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Title (English)</label>
                    <input type="text" name="title_en" value="{{ old('title_en', $page->title_en) }}"
                        class="form-control" required>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Content (English) — HTML supported</label>
                    <textarea name="content_en" rows="24" class="form-control"
                        style="font-family:monospace;font-size:13px;" required>{{ old('content_en', $page->content_en) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Somali tab --}}
    <div id="pane-so" style="display:none;">
        <div class="card" style="margin-bottom:16px;">
            <div class="card-body" style="padding:20px;">
                <div style="margin-bottom:14px;">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Cinwaanka (Af-Soomaali)</label>
                    <input type="text" name="title_so" value="{{ old('title_so', $page->title_so) }}"
                        class="form-control" required>
                </div>
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Waxa ku jira (Af-Soomaali) — HTML la taageeraa</label>
                    <textarea name="content_so" rows="24" class="form-control"
                        style="font-family:monospace;font-size:13px;" required>{{ old('content_so', $page->content_so) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Published toggle --}}
    <div class="card" style="margin-bottom:16px;">
        <div class="card-body" style="padding:16px 20px;display:flex;align-items:center;gap:12px;">
            <input type="checkbox" name="is_published" id="is_published" value="1"
                {{ $page->is_published ? 'checked' : '' }} style="width:18px;height:18px;">
            <label for="is_published" style="font-weight:600;margin:0;cursor:pointer;">
                Published — visible in app
            </label>
        </div>
    </div>

    @if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:16px;">
        @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Changes
        </button>
        <a href="{{ route('admin.legal-pages.index') }}" class="btn btn-light">Cancel</a>
    </div>
</form>

<script>
function showTab(lang) {
    document.getElementById('pane-en').style.display = lang === 'en' ? '' : 'none';
    document.getElementById('pane-so').style.display = lang === 'so' ? '' : 'none';
    document.getElementById('tab-en').style.background = lang === 'en' ? '#1a73e8' : '#fff';
    document.getElementById('tab-en').style.color      = lang === 'en' ? '#fff' : '#333';
    document.getElementById('tab-en').style.borderColor= lang === 'en' ? '#1a73e8' : '#e0e0e0';
    document.getElementById('tab-so').style.background = lang === 'so' ? '#1a73e8' : '#fff';
    document.getElementById('tab-so').style.color      = lang === 'so' ? '#fff' : '#333';
    document.getElementById('tab-so').style.borderColor= lang === 'so' ? '#1a73e8' : '#e0e0e0';
}
</script>
@endsection
