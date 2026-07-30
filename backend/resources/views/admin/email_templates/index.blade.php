@extends('admin.layouts.app')
@section('title', 'Email Templates')
@section('content')
<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="fw-bold mb-1"><i class="fas fa-envelope-open-text text-warning me-2"></i>Email Templates</h4>
      <p class="text-muted mb-0 small">Manage all system emails — changes go live instantly</p>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  <div class="row g-3">
    @foreach($templates as $tpl)
    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100" style="border-radius:16px;overflow:hidden;">
        <div class="card-body p-4">
          <div class="d-flex align-items-start justify-content-between mb-3">
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:rgba(255,138,0,0.12);">
                <i class="fas fa-envelope" style="color:#FF8A00;font-size:1.2rem;"></i>
              </div>
              <div>
                <h6 class="fw-bold mb-1">{{ $tpl->name }}</h6>
                <code class="small text-muted">{{ $tpl->key }}</code>
              </div>
            </div>
            <span class="badge {{ $tpl->is_active ? 'bg-success' : 'bg-secondary' }}">
              {{ $tpl->is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>

          <p class="small text-muted mb-1"><i class="fas fa-tag me-1"></i><strong>Subject:</strong> {{ $tpl->subject }}</p>

          @if($tpl->variables)
          <div class="mt-2 mb-3">
            @foreach($tpl->variables as $var)
              <span class="badge me-1" style="background:rgba(99,102,241,0.12);color:#6366f1;font-size:.7rem;">{{ $var }}</span>
            @endforeach
          </div>
          @endif

          <div class="d-flex gap-2 mt-3">
            <a href="{{ route('admin.email-templates.edit', $tpl) }}" class="btn btn-sm btn-warning fw-bold">
              <i class="fas fa-edit me-1"></i>Edit
            </a>
            <a href="{{ route('admin.email-templates.preview', $tpl) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
              <i class="fas fa-eye me-1"></i>Preview
            </a>
          </div>
        </div>
        <div class="card-footer bg-transparent border-top px-4 py-2">
          <small class="text-muted">Updated {{ $tpl->updated_at->diffForHumans() }}</small>
        </div>
      </div>
    </div>
    @endforeach
  </div>
</div>
@endsection
