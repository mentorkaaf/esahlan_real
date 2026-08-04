@extends('admin.layouts.app')
@section('title', 'Legal Pages')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Legal Pages</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li>Legal Pages</li>
        </ul>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
@endif

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;">
    @foreach($pages as $page)
    <div class="card" style="margin-bottom:0;">
        <div class="card-body" style="padding:20px;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                <div style="width:42px;height:42px;border-radius:12px;background:{{ $page->slug === 'privacy-policy' ? '#e8f0fe' : ($page->slug === 'terms' ? '#fce8e6' : '#e6f4ea') }};display:flex;align-items:center;justify-content:center;">
                    <i class="fas {{ $page->slug === 'privacy-policy' ? 'fa-shield-alt' : ($page->slug === 'terms' ? 'fa-file-contract' : 'fa-info-circle') }}"
                       style="color:{{ $page->slug === 'privacy-policy' ? '#1a73e8' : ($page->slug === 'terms' ? '#d93025' : '#1e8e3e') }};font-size:18px;"></i>
                </div>
                <div>
                    <div style="font-weight:700;font-size:15px;">{{ $page->title_en }}</div>
                    <div style="font-size:12px;color:#888;font-family:monospace;">{{ $page->slug }}</div>
                </div>
                <div style="margin-left:auto;">
                    <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;
                        background:{{ $page->is_published ? '#e6f4ea' : '#fce8e6' }};
                        color:{{ $page->is_published ? '#1e8e3e' : '#d93025' }};">
                        {{ $page->is_published ? 'Published' : 'Hidden' }}
                    </span>
                </div>
            </div>
            <div style="display:flex;gap:8px;margin-top:4px;">
                <a href="{{ route('admin.legal-pages.edit', $page) }}" class="btn btn-primary btn-sm" style="flex:1;text-align:center;">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="{{ url('/privacy-policy') }}" target="_blank" class="btn btn-light btn-sm" style="flex:1;text-align:center;" title="View public page">
                    <i class="fas fa-external-link-alt"></i> View
                </a>
            </div>
        </div>
    </div>
    @endforeach
</div>

@endsection
