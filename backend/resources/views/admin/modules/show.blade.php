@extends('admin.layouts.app')
@section('title', 'Module: ' . $module->name)
@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="{{ $module->icon }}"></i> {{ $module->name }}</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.modules.index') }}">Modules</a></li>
            <li class="breadcrumb-item active">{{ $module->name }}</li>
        </ol>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">Module Settings</div>
        <div class="card-body">
            <form action="{{ route('admin.modules.update', $module->id) }}" method="POST">
                @csrf @method('PATCH')
                <div class="form-group">
                    <label class="form-label">Commission Type</label>
                    <select name="commission_type" class="form-control">
                        <option value="percentage" {{ $module->commission_type === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                        <option value="fixed" {{ $module->commission_type === 'fixed' ? 'selected' : '' }}>Fixed ($)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Commission Value</label>
                    <input type="number" name="commission_value" class="form-control" value="{{ $module->commission_value }}" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ $module->description }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Module Info</div>
        <div class="card-body">
            <table>
                <tr><td class="text-muted" style="width:130px;padding:8px 12px 8px 0;">Slug</td><td><code>{{ $module->slug }}</code></td></tr>
                <tr><td class="text-muted">Status</td><td><span class="badge {{ $module->is_active ? 'badge-success' : 'badge-danger' }}">{{ $module->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
                <tr><td class="text-muted">Commission</td><td><strong>{{ $module->commission_value }}{{ $module->commission_type === 'percentage' ? '%' : '$' }}</strong></td></tr>
                <tr><td class="text-muted">Sort Order</td><td>{{ $module->sort_order }}</td></tr>
            </table>
            <div class="mt-3">
                <form action="{{ route('admin.modules.toggle', $module->id) }}" method="POST">
                    @csrf
                    <button class="btn {{ $module->is_active ? 'btn-danger' : 'btn-success' }}">
                        {{ $module->is_active ? 'Disable Module' : 'Enable Module' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
