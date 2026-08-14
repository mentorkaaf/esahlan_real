@extends('admin.layouts.app')
@section('title', 'eData — Data Management')
@section('content')

<style>
:root { --dat:#1565C0; --dat-light:rgba(21,101,192,.1); }
.dat-hero {
    background: linear-gradient(135deg, #07003B 0%, #0D47A1 60%, #1565C0 100%);
    border-radius:16px; padding:28px 32px; margin-bottom:24px;
    color:#fff; display:flex; align-items:center; justify-content:space-between;
    position:relative; overflow:hidden;
}
.dat-hero::before { content:''; position:absolute; right:-60px; top:-60px; width:240px; height:240px; border-radius:50%; background:rgba(255,255,255,.05); pointer-events:none; }
.dat-hero::after  { content:''; position:absolute; left:-30px; bottom:-50px; width:160px; height:160px; border-radius:50%; background:rgba(255,138,0,.1); pointer-events:none; }
.dat-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; margin-right:6px; margin-top:6px; background:rgba(255,138,0,.2); border:1px solid rgba(255,138,0,.4); color:#fff; }
.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
.stat-card  { background:#fff; border-radius:14px; padding:20px 22px; box-shadow:0 1px 8px rgba(0,0,0,.06); display:flex; align-items:center; gap:16px; border:1px solid #f0f1f5; }
.stat-icon  { width:50px; height:50px; border-radius:13px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.stat-icon.blue   { background:rgba(21,101,192,.1); color:#1565C0; }
.stat-icon.orange { background:rgba(255,138,0,.1); color:#FF8A00; }
.stat-icon.green  { background:rgba(16,185,129,.1); color:#10b981; }
.stat-icon.navy   { background:rgba(7,0,59,.1); color:#07003B; }
.stat-num { font-size:26px; font-weight:800; color:#1A1A2E; line-height:1; }
.stat-lbl { font-size:12px; color:#8A8A9A; margin-top:2px; }
.module-tabs { display:flex; gap:4px; background:#f4f5fa; border-radius:12px; padding:4px; margin-bottom:24px; }
.module-tab  { padding:9px 18px; border-radius:9px; border:none; background:transparent; cursor:pointer; font-size:13px; font-weight:600; color:#8A8A9A; transition:all .2s; display:flex; align-items:center; gap:7px; font-family:inherit; }
.module-tab.active { background:#fff; color:#1565C0; box-shadow:0 1px 6px rgba(0,0,0,.08); }
.module-tab:hover:not(.active) { color:#1A1A2E; background:rgba(255,255,255,.6); }
.tab-pane { display:none; } .tab-pane.active { display:block; }
.sc { background:#fff; border-radius:14px; box-shadow:0 1px 8px rgba(0,0,0,.06); overflow:hidden; margin-bottom:20px; border:1px solid #f0f1f5; }
.sc-head { padding:18px 22px; border-bottom:1px solid #f0f1f5; display:flex; align-items:center; justify-content:space-between; }
.sc-title { font-size:15px; font-weight:700; color:#1A1A2E; display:flex; align-items:center; gap:8px; }
.dtbl { width:100%; border-collapse:collapse; }
.dtbl th { padding:11px 16px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#8A8A9A; border-bottom:1px solid #f0f1f5; background:#fafbff; white-space:nowrap; }
.dtbl td { padding:12px 16px; font-size:13px; color:#1A1A2E; border-bottom:1px solid #f0f1f5; vertical-align:middle; }
.dtbl tr:last-child td { border-bottom:none; }
.dtbl tr:hover td { background:rgba(21,101,192,.02); }
.dtbl .empty-row td { text-align:center; padding:40px; color:#8A8A9A; }
.provider-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; padding:20px; }
.provider-card { border:1.5px solid #f0f1f5; border-radius:16px; padding:20px; transition:all .2s; }
.provider-card:hover { border-color:#1565C0; box-shadow:0 4px 20px rgba(21,101,192,.1); }
.provider-logo-wrap { width:56px; height:56px; border-radius:14px; overflow:hidden; background:#f4f5fa; display:flex; align-items:center; justify-content:center; }
.bundle-row { border-bottom:1px solid #f0f1f5; padding:16px 20px; }
.bundle-row:last-child { border-bottom:none; }
.bundle-benefit { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:20px; font-size:11px; background:#f4f5fa; color:#1A1A2E; margin:2px; }
.badge-xs { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-active   { background:rgba(16,185,129,.1); color:#10b981; }
.badge-inactive { background:rgba(239,68,68,.1); color:#ef4444; }
.badge-daily    { background:rgba(59,130,246,.1); color:#3b82f6; }
.badge-weekly   { background:rgba(139,92,246,.1); color:#7c3aed; }
.badge-monthly  { background:rgba(16,185,129,.1); color:#10b981; }
.badge-unlimited{ background:rgba(245,158,11,.1); color:#f59e0b; }
.price-tag { font-size:15px; font-weight:800; color:#1565C0; }
.form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; }
.fgroup label { font-size:12px; font-weight:600; color:#1A1A2E; margin-bottom:5px; display:block; }
.fgroup input, .fgroup select, .fgroup textarea { width:100%; padding:9px 12px; border:1.5px solid #EEEEEE; border-radius:10px; font-size:13px; color:#1A1A2E; background:#fff; font-family:inherit; transition:border-color .15s; }
.fgroup input:focus, .fgroup select:focus, .fgroup textarea:focus { outline:none; border-color:#1565C0; }
.fgroup textarea { resize:vertical; min-height:70px; }
.btn-icon { width:32px; height:32px; border-radius:8px; border:none; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; font-size:13px; transition:all .15s; }
.btn-icon.edit  { background:rgba(59,130,246,.1); color:#3b82f6; }
.btn-icon.del   { background:rgba(239,68,68,.1); color:#ef4444; }
.btn-icon:hover { transform:scale(1.08); }
.btn-add { display:inline-flex; align-items:center; gap:7px; padding:9px 18px; background:#1565C0; color:#fff; border:none; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer; font-family:inherit; transition:opacity .15s; }
.btn-add:hover { opacity:.9; }
.btn-outline { display:inline-flex; align-items:center; gap:7px; padding:8px 16px; background:transparent; color:#1565C0; border:1.5px solid #1565C0; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer; font-family:inherit; transition:all .15s; }
.btn-outline:hover { background:#1565C0; color:#fff; }
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; display:none; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal-box { background:#fff; border-radius:18px; width:min(560px,95vw); max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.18); }
.modal-head { padding:22px 26px; border-bottom:1px solid #f0f1f5; display:flex; align-items:center; justify-content:space-between; }
.modal-head h3 { font-size:17px; font-weight:800; color:#1A1A2E; margin:0; }
.modal-close { width:32px; height:32px; border-radius:8px; border:none; background:#f4f5fa; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; }
.modal-body { padding:24px 26px; }
.alert-success { background:rgba(16,185,129,.08); border:1px solid rgba(16,185,129,.3); color:#065f46; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13px; font-weight:600; }
.alert-error   { background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.3); color:#7f1d1d; padding:12px 16px; border-radius:10px; margin-bottom:16px; font-size:13px; font-weight:600; }
</style>

@if(session('success'))
<div class="alert-success"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="alert-error"><i class="fas fa-exclamation-circle me-2"></i>{{ $errors->first() }}</div>
@endif

<div class="dat-hero">
    <div style="position:relative;z-index:1">
        <h2 style="font-size:22px;font-weight:800;margin:0 0 4px"><i class="fas fa-wifi me-2"></i>eData — Data Management</h2>
        <p style="margin:0;opacity:.8;font-size:13px">Manage telecom providers, data packages and internet bundles</p>
        <div style="margin-top:8px">
            @foreach($providers->take(4) as $prov)
            <span class="dat-badge"><i class="fas fa-signal me-1"></i>{{ $prov->name }}</span>
            @endforeach
        </div>
    </div>
    <div style="font-size:56px;opacity:.18;position:relative;z-index:1"><i class="fas fa-satellite-dish"></i></div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-broadcast-tower"></i></div>
        <div><div class="stat-num">{{ $providers->count() }}</div><div class="stat-lbl">Providers</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-box-open"></i></div>
        <div><div class="stat-num">{{ $packages->count() }}</div><div class="stat-lbl">Data Packages</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-layer-group"></i></div>
        <div><div class="stat-num">{{ $bundles->count() }}</div><div class="stat-lbl">Bundles</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon navy"><i class="fas fa-check-circle"></i></div>
        <div><div class="stat-num">{{ $providers->where('is_active', true)->count() }}</div><div class="stat-lbl">Active Providers</div></div>
    </div>
</div>

<div class="module-tabs">
    <button class="module-tab active" onclick="switchTab('providers',this)"><i class="fas fa-broadcast-tower"></i> Providers</button>
    <button class="module-tab" onclick="switchTab('packages',this)"><i class="fas fa-box-open"></i> Packages</button>
    <button class="module-tab" onclick="switchTab('bundles',this)"><i class="fas fa-layer-group"></i> Bundles</button>
    <button class="module-tab" onclick="switchTab('add-provider',this)"><i class="fas fa-plus-circle"></i> Add Provider</button>
    <button class="module-tab" onclick="switchTab('user-phones',this)"><i class="fas fa-mobile-alt"></i> User Phones</button>
</div>

{{-- PROVIDERS --}}
<div id="tab-providers" class="tab-pane active">
    <div class="sc">
        <div class="sc-head">
            <div class="sc-title"><i class="fas fa-broadcast-tower" style="color:#1565C0"></i> Telecom Providers</div>
        </div>
        <div class="provider-grid">
            @forelse($providers as $prov)
            <div class="provider-card">
                <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:14px">
                    <div class="provider-logo-wrap">
                        @if($prov->logo)
                            <img src="{{ asset('storage/'.$prov->logo) }}" style="width:100%;height:100%;object-fit:contain" onerror="this.style.display='none'">
                        @else
                            <i class="fas fa-signal" style="font-size:22px;color:#8A8A9A"></i>
                        @endif
                    </div>
                    <div style="flex:1">
                        <div style="font-size:16px;font-weight:800;color:#1A1A2E;margin-bottom:4px">{{ $prov->name }}</div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <span class="badge-xs {{ $prov->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $prov->is_active ? 'Active' : 'Inactive' }}</span>
                            @php
                                $pkgCount = $packages->where('provider_id', $prov->id)->count();
                                $bndCount = $bundles->where('provider_id', $prov->id)->count();
                            @endphp
                            <span style="background:#f4f5fa;padding:2px 8px;border-radius:20px;font-size:11px;color:#8A8A9A">{{ $pkgCount }} pkgs</span>
                            <span style="background:#f4f5fa;padding:2px 8px;border-radius:20px;font-size:11px;color:#8A8A9A">{{ $bndCount }} bundles</span>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:6px">
                    <button class="btn-icon edit" style="flex:1;width:auto;border-radius:10px" onclick="openEditProvider({{ json_encode($prov) }})"><i class="fas fa-pen"></i>&nbsp;Edit</button>
                    <button class="btn-icon" style="background:var(--dat-light);color:#1565C0;flex:1;width:auto;border-radius:10px" onclick="openAddPackage({{ $prov->id }})"><i class="fas fa-plus"></i>&nbsp;Package</button>
                    <button class="btn-icon" style="background:rgba(16,185,129,.1);color:#10b981;flex:1;width:auto;border-radius:10px" onclick="openAddBundle({{ $prov->id }})"><i class="fas fa-plus"></i>&nbsp;Bundle</button>
                    <form method="POST" action="{{ route('admin.module-data.data.provider.destroy', $prov->id) }}" onsubmit="return confirm('Delete provider?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-icon del"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
            @empty
            <div style="text-align:center;padding:40px;color:#8A8A9A;grid-column:1/-1">
                <i class="fas fa-broadcast-tower" style="font-size:36px;color:#EEEEEE;display:block;margin-bottom:10px"></i>No providers yet
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- PACKAGES --}}
<div id="tab-packages" class="tab-pane">
    <div class="sc">
        <div class="sc-head">
            <div class="sc-title"><i class="fas fa-box-open" style="color:#1565C0"></i> Data Packages</div>
            <div style="display:flex;gap:10px;align-items:center">
                <input type="text" placeholder="Search…" oninput="filterPkg(this.value)" style="padding:7px 12px;border:1.5px solid #EEEEEE;border-radius:10px;font-size:13px;width:180px">
                <button class="btn-add" onclick="document.getElementById('addPackageModal').classList.add('open')"><i class="fas fa-plus"></i> Add Package</button>
            </div>
        </div>
        <table class="dtbl" id="pkgTable">
            <thead>
                <tr>
                    <th>Package</th><th>Provider</th><th>Category</th><th>Data</th><th>Validity</th><th>Price</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($packages as $pkg)
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            @if($pkg->image)
                                <img src="{{ asset('storage/'.$pkg->image) }}" style="width:56px;height:40px;border-radius:8px;object-fit:cover">
                            @else
                                <div style="width:56px;height:40px;border-radius:8px;background:#f4f5fa;display:flex;align-items:center;justify-content:center;color:#8A8A9A"><i class="fas fa-wifi"></i></div>
                            @endif
                            <div>
                                <div style="font-weight:700">{{ $pkg->name }}</div>
                                @if($pkg->description)<div style="font-size:11px;color:#8A8A9A">{{ Str::limit($pkg->description, 35) }}</div>@endif
                            </div>
                        </div>
                    </td>
                    <td>{{ $pkg->provider?->name ?? '—' }}</td>
                    <td><span class="badge-xs badge-{{ $pkg->category ?? 'monthly' }}">{{ ucfirst($pkg->category ?? 'monthly') }}</span></td>
                    <td>{{ $pkg->data_amount ?: '—' }}</td>
                    <td>{{ $pkg->validity_days ?? '—' }}d</td>
                    <td><span class="price-tag">${{ number_format($pkg->price ?? 0, 2) }}</span></td>
                    <td><span class="badge-xs {{ $pkg->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $pkg->is_active ? 'Active' : 'Off' }}</span></td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <button class="btn-icon edit" onclick="openEditPackage({{ json_encode($pkg) }})"><i class="fas fa-pen"></i></button>
                            <form method="POST" action="{{ route('admin.module-data.data.package.destroy', $pkg->id) }}" onsubmit="return confirm('Delete?')" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-icon del"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="empty-row"><td colspan="8"><i class="fas fa-box-open" style="font-size:32px;color:#EEEEEE;display:block;margin-bottom:10px"></i>No packages yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- BUNDLES --}}
<div id="tab-bundles" class="tab-pane">
    <div class="sc">
        <div class="sc-head">
            <div class="sc-title"><i class="fas fa-layer-group" style="color:#1565C0"></i> Internet Bundles</div>
            <div style="display:flex;gap:10px;align-items:center">
                <input type="text" placeholder="Search bundles…" oninput="filterBundles(this.value)" style="padding:7px 12px;border:1.5px solid #EEEEEE;border-radius:10px;font-size:13px;width:180px">
                <button class="btn-add" onclick="openBulkModal()"><i class="fas fa-plus"></i> Add Bundle</button>
            </div>
        </div>
        <div id="bundleList">
            @forelse($bundles as $bnd)
            <div class="bundle-row" data-search="{{ strtolower($bnd->name.' '.$bnd->provider_name) }}">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:16px">
                    <div style="display:flex;align-items:center;gap:14px">
                        <div style="width:48px;height:48px;border-radius:12px;background:var(--dat-light);display:flex;align-items:center;justify-content:center;color:#1565C0;font-size:18px;flex-shrink:0"><i class="fas fa-layer-group"></i></div>
                        <div>
                            <div style="font-size:15px;font-weight:800;color:#1A1A2E">{{ $bnd->name }}</div>
                            <div style="font-size:12px;color:#8A8A9A">{{ $bnd->provider_name }}</div>
                            <div style="display:flex;gap:4px;margin-top:6px;flex-wrap:wrap">
                                @if($bnd->data_gb ?? null) <span class="bundle-benefit"><i class="fas fa-wifi" style="color:#1565C0;font-size:9px"></i>{{ $bnd->data_gb }}GB</span>@endif
                                @if($bnd->minutes ?? null) <span class="bundle-benefit"><i class="fas fa-phone" style="color:#10b981;font-size:9px"></i>{{ $bnd->minutes }}min</span>@endif
                                @if($bnd->sms ?? null)     <span class="bundle-benefit"><i class="fas fa-sms" style="color:#f59e0b;font-size:9px"></i>{{ $bnd->sms }}SMS</span>@endif
                                @if($bnd->validity_days ?? null)<span class="bundle-benefit"><i class="fas fa-clock" style="font-size:9px"></i>{{ $bnd->validity_days }}d</span>@endif
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;flex-shrink:0">
                        <div style="text-align:right">
                            <div class="price-tag">${{ number_format($bnd->price, 2) }}</div>
                            <span class="badge-xs {{ $bnd->is_active ? 'badge-active' : 'badge-inactive' }}" style="margin-top:4px;display:inline-block">{{ $bnd->is_active ? 'Active' : 'Off' }}</span>
                        </div>
                        <div style="display:flex;gap:6px">
                            <button class="btn-icon edit" onclick="openEditBundle({{ json_encode($bnd) }})"><i class="fas fa-pen"></i></button>
                            <form method="POST" action="{{ route('admin.module-data.data.bundle.destroy', $bnd->id) }}" onsubmit="return confirm('Delete?')" style="display:inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-icon del"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div style="text-align:center;padding:40px;color:#8A8A9A"><i class="fas fa-layer-group" style="font-size:36px;color:#EEEEEE;display:block;margin-bottom:10px"></i>No bundles yet</div>
            @endforelse
        </div>
    </div>
</div>

{{-- ADD PROVIDER --}}
<div id="tab-add-provider" class="tab-pane">
    <div class="sc">
        <div class="sc-head"><div class="sc-title"><i class="fas fa-plus-circle" style="color:#1565C0"></i> Add Provider</div></div>
        <div style="padding:24px">
            <form method="POST" action="{{ route('admin.module-data.data.provider.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-grid-3" style="margin-bottom:14px">
                    <div class="fgroup"><label>Provider Name *</label><input type="text" name="name" required placeholder="e.g. Hormuud Telecom"></div>
                    <div class="fgroup"><label>Brand Color</label><input type="color" name="color" value="#1565C0" style="height:42px;padding:4px"></div>
                    <div class="fgroup"><label>Sort Order</label><input type="number" name="sort_order" value="0" min="0"></div>
                </div>
                <div class="fgroup" style="margin-bottom:20px"><label>Provider Logo</label><input type="file" name="logo_file" accept="image/*"></div>
                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="reset" class="btn-outline">Reset</button>
                    <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Add Provider</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODALS --}}

<div class="modal-overlay" id="editProviderModal">
    <div class="modal-box">
        <div class="modal-head"><h3>Edit Provider</h3><button class="modal-close" onclick="document.getElementById('editProviderModal').classList.remove('open')"><i class="fas fa-times"></i></button></div>
        <div class="modal-body">
            <form id="editProviderForm" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Name</label><input type="text" name="name" id="eprov_name" required></div>
                    <div class="fgroup"><label>Color</label><input type="color" name="color" id="eprov_color" style="height:42px;padding:4px"></div>
                </div>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Sort Order</label><input type="number" name="sort_order" id="eprov_sort" min="0"></div>
                    <div class="fgroup"><label>Status</label><select name="is_active" id="eprov_active"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                </div>
                <div class="fgroup" style="margin-bottom:14px"><label>New Logo</label><input type="file" name="logo_file" accept="image/*"></div>
                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="button" class="btn-outline" onclick="document.getElementById('editProviderModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="addPackageModal">
    <div class="modal-box">
        <div class="modal-head"><h3>Add Data Package</h3><button class="modal-close" onclick="document.getElementById('addPackageModal').classList.remove('open')"><i class="fas fa-times"></i></button></div>
        <div class="modal-body">
            <form method="POST" action="{{ route('admin.module-data.data.package.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="fgroup" style="margin-bottom:14px">
                    <label>Package Name *</label>
                    <input type="text" name="name" id="ap_name" required placeholder="e.g. Daily Pack, Weekly Bundle…">
                </div>
                <div class="fgroup" style="margin-bottom:14px">
                    <label>Provider *</label>
                    <select name="provider_id" id="ap_provider" required>
                        <option value="">Select provider</option>
                        @foreach($providers as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                    </select>
                </div>
                <div class="fgroup" style="margin-bottom:20px">
                    <label>Package Image <span style="color:#8A8A9A;font-weight:400">(shown as card in app)</span></label>
                    <input type="file" name="image" accept="image/*">
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="button" class="btn-outline" onclick="document.getElementById('addPackageModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-plus"></i> Add Package</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editPackageModal">
    <div class="modal-box">
        <div class="modal-head"><h3>Edit Package</h3><button class="modal-close" onclick="document.getElementById('editPackageModal').classList.remove('open')"><i class="fas fa-times"></i></button></div>
        <div class="modal-body">
            <form id="editPackageForm" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="fgroup" style="margin-bottom:14px">
                    <label>Package Name *</label>
                    <input type="text" name="name" id="epkg_name" required>
                </div>
                <div class="fgroup" style="margin-bottom:14px">
                    <label>Provider *</label>
                    <select name="provider_id" id="epkg_provider">
                        @foreach($providers as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                    </select>
                </div>
                <div class="fgroup" style="margin-bottom:14px">
                    <label>Status</label>
                    <select name="is_active" id="epkg_active"><option value="1">Active</option><option value="0">Inactive</option></select>
                </div>
                <div class="fgroup" style="margin-bottom:20px">
                    <label>New Image <span style="color:#8A8A9A;font-weight:400">(leave empty to keep current)</span></label>
                    <input type="file" name="image" accept="image/*">
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="button" class="btn-outline" onclick="document.getElementById('editPackageModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Bulk Add Bundles Modal ───────────────────────────────────────── --}}
<style>
.bulk-modal-box{background:#fff;border-radius:18px;width:min(1020px,97vw);max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);}
.bulk-hdr{display:flex;align-items:flex-start;justify-content:space-between;padding:20px 26px 0;}
.bulk-header-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:16px 26px 14px;border-bottom:1px solid #f0f1f5;background:#fafbff;}
.bulk-header-grid label{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;display:block;margin-bottom:5px;}
.bulk-header-grid select{width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:9px;font-size:13px;background:#fff;}
.bulk-table-wrap{padding:16px 26px;}
.bulk-table{width:100%;border-collapse:collapse;font-size:13px;}
.bulk-table th{text-align:left;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;padding:8px;border-bottom:2px solid #f0f1f5;white-space:nowrap;}
.bulk-table td{padding:4px 3px;vertical-align:middle;}
.bulk-table tr+tr td{border-top:1px solid #f9fafb;}
.bulk-table input{width:100%;padding:7px 8px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:12px;background:#fff;transition:.15s;box-sizing:border-box;}
.bulk-table input:focus{border-color:#3B82F6;outline:none;background:#f0f7ff;}
.bulk-row-num{color:#9ca3af;font-size:11px;font-weight:700;text-align:center;width:28px;}
.bulk-remove-btn{width:28px;height:28px;border:none;background:#fee2e2;color:#dc2626;border-radius:7px;cursor:pointer;font-size:15px;line-height:1;display:flex;align-items:center;justify-content:center;transition:.15s;flex-shrink:0;}
.bulk-remove-btn:hover{background:#fca5a5;}
.bulk-add-row-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#eff6ff;color:#3B82F6;border:1.5px dashed #93c5fd;border-radius:9px;font-size:12px;font-weight:700;cursor:pointer;transition:.15s;margin-top:10px;}
.bulk-add-row-btn:hover{background:#dbeafe;border-color:#3B82F6;}
.bulk-footer{display:flex;align-items:center;justify-content:space-between;padding:14px 26px;border-top:1px solid #f0f1f5;background:#fafbff;border-radius:0 0 18px 18px;position:sticky;bottom:0;}
.btn-save-bulk{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;background:linear-gradient(135deg,#3B82F6,#1d4ed8);color:#fff;border:none;border-radius:10px;font-size:13px;font-weight:800;cursor:pointer;transition:.15s;}
.btn-save-bulk:hover{filter:brightness(1.1);}
</style>
<div class="modal-overlay" id="addBundleModal">
    <div class="bulk-modal-box">
        <div class="bulk-hdr">
            <div>
                <div style="font-size:17px;font-weight:900;color:#1A1A2E;">📦 Add Bundles</div>
                <div style="font-size:12px;color:#9ca3af;margin-top:3px;">Add one or many bundles at once — one row per bundle</div>
            </div>
            <button class="modal-close" onclick="closeBulkModal()"><i class="fas fa-times"></i></button>
        </div>

        <div class="bulk-header-grid">
            <div>
                <label>Provider *</label>
                <select id="bulk_provider" onchange="bulkFilterPkgs(this.value)">
                    <option value="">— Select Provider —</option>
                    @foreach($providers as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>Package <span style="font-weight:400;font-size:10px;">(shared for all rows, optional)</span></label>
                <select id="bulk_package">
                    <option value="">— None —</option>
                    @foreach($packages as $p)
                    <option value="{{ $p->id }}" data-provider="{{ $p->provider_id }}">{{ $p->name }} ({{ $p->provider?->name }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="bulk-table-wrap">
            <form id="bulkBundleForm" method="POST" action="{{ route('admin.module-data.data.bundle.store-bulk') }}">
                @csrf
                <input type="hidden" name="provider_id" id="bulk_prov_h">
                <input type="hidden" name="package_id"  id="bulk_pkg_h">
                <table class="bulk-table">
                    <thead>
                        <tr>
                            <th style="width:28px">#</th>
                            <th style="min-width:155px">Bundle Name *</th>
                            <th style="width:110px">Data</th>
                            <th style="width:68px">Min</th>
                            <th style="width:58px">SMS</th>
                            <th style="width:88px">Price ($) *</th>
                            <th style="width:72px">Days *</th>
                            <th style="width:85px">Badge</th>
                            <th style="min-width:120px">Description</th>
                            <th style="width:32px"></th>
                        </tr>
                    </thead>
                    <tbody id="bulk_tbody"></tbody>
                </table>
                <button type="button" class="bulk-add-row-btn" onclick="addBulkRow()">
                    <i class="fas fa-plus"></i> Add Row
                </button>
                <div style="font-size:11px;color:#9ca3af;margin-top:7px;">
                    <i class="fas fa-keyboard"></i>&nbsp; Tab moves between cells &mdash; Enter on the last cell adds a new row automatically
                </div>
            </form>
        </div>

        <div class="bulk-footer">
            <div style="font-size:12px;font-weight:700;color:#6b7280;">
                Bundles: <span id="bulk_cnt" style="color:#3B82F6;font-size:15px;font-weight:900;">0</span>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="button" class="btn-outline" onclick="closeBulkModal()">Cancel</button>
                <button type="button" class="btn-save-bulk" onclick="submitBulk()">
                    <i class="fas fa-cloud-upload-alt"></i> Save All Bundles
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    let idx = 0;
    window.addBulkRow = function(focus) {
        const i = idx++;
        const tb = document.getElementById('bulk_tbody');
        const num = tb.children.length + 1;
        const tr = document.createElement('tr');
        tr.id = 'br_' + i;
        tr.innerHTML =
            '<td class="bulk-row-num">' + num + '</td>' +
            '<td><input type="text"   name="bundles['+i+'][name]"         placeholder="e.g. Anfac 1GB"></td>' +
            '<td><div style="display:flex;gap:2px;">' +
                '<input type="number" name="bundles['+i+'][data_amount]" placeholder="e.g. 1" min="0" step="0.1" style="width:52px;min-width:0;">' +
                '<select name="bundles['+i+'][data_unit]" style="width:50px;padding:7px 3px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:11px;font-weight:700;background:#fff;">' +
                    '<option value="GB">GB</option><option value="MB">MB</option><option value="Mbps">Mbps</option>' +
                '</select>' +
            '</div></td>' +
            '<td><input type="number" name="bundles['+i+'][minutes]"       placeholder="—" min="0"></td>' +
            '<td><input type="number" name="bundles['+i+'][sms]"           placeholder="—" min="0"></td>' +
            '<td><input type="number" name="bundles['+i+'][price]"         placeholder="0.00" min="0" step="0.01"></td>' +
            '<td><input type="number" name="bundles['+i+'][validity_days]" value="30" min="1"></td>' +
            '<td><input type="text"   name="bundles['+i+'][badge_label]"   placeholder="Popular"></td>' +
            '<td><input type="text"   name="bundles['+i+'][description]"   placeholder="Optional"></td>' +
            '<td><button type="button" class="bulk-remove-btn" onclick="removeBulkRow(\'br_'+i+'\')">×</button></td>';
        const inputs = tr.querySelectorAll('input');
        inputs[inputs.length-1].addEventListener('keydown', function(e){ if(e.key==='Enter'){e.preventDefault();addBulkRow(true);} });
        tb.appendChild(tr);
        updateNums();
        if(focus!==false) tr.querySelector('input').focus();
    };
    window.removeBulkRow = function(id) {
        const el = document.getElementById(id); if(el) el.remove(); updateNums();
    };
    function updateNums(){
        const rows=document.querySelectorAll('#bulk_tbody tr');
        rows.forEach(function(tr,i){ const td=tr.querySelector('td');if(td)td.textContent=i+1; });
        document.getElementById('bulk_cnt').textContent=rows.length;
    }
    window.bulkFilterPkgs = function(pid){
        const sel=document.getElementById('bulk_package');
        Array.from(sel.options).forEach(function(o){ if(!o.value)return; o.style.display=(!pid||o.dataset.provider==pid)?'':'none'; });
        sel.value='';
    };
    window.submitBulk = function(){
        const pid=document.getElementById('bulk_provider').value;
        if(!pid){alert('Please select a Provider.');document.getElementById('bulk_provider').focus();return;}
        const rows=document.querySelectorAll('#bulk_tbody tr');
        if(!rows.length){alert('Add at least one bundle row.');return;}
        let ok=true;
        rows.forEach(function(tr){
            const n=tr.querySelector('input[name$="[name]"]');
            const p=tr.querySelector('input[name$="[price]"]');
            if(!n||!n.value.trim()){if(n){n.style.borderColor='#ef4444';n.style.background='#fff5f5';}ok=false;}
            if(!p||!p.value){if(p){p.style.borderColor='#ef4444';p.style.background='#fff5f5';}ok=false;}
        });
        if(!ok){alert('Please fill Name and Price in every row.');return;}
        document.getElementById('bulk_prov_h').value=pid;
        document.getElementById('bulk_pkg_h').value=document.getElementById('bulk_package').value;
        document.getElementById('bulkBundleForm').submit();
    };
    window.closeBulkModal = function(){
        document.getElementById('addBundleModal').classList.remove('open');
    };
    window.openBulkModal = function(){
        document.getElementById('addBundleModal').classList.add('open');
        const tb=document.getElementById('bulk_tbody');
        if(!tb.children.length){addBulkRow(false);addBulkRow(false);addBulkRow(false);}
    };
    document.getElementById('addBundleModal').addEventListener('click',function(e){if(e.target===this)closeBulkModal();});
})();
</script>

<div class="modal-overlay" id="editBundleModal">
    <div class="modal-box">
        <div class="modal-head"><h3>Edit Bundle</h3><button class="modal-close" onclick="document.getElementById('editBundleModal').classList.remove('open')"><i class="fas fa-times"></i></button></div>
        <div class="modal-body">
            <form id="editBundleForm" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Name</label><input type="text" name="name" id="eb_name" required></div>
                    <div class="fgroup"><label>Provider</label><select name="provider_id" id="eb_provider">@foreach($providers as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
                </div>
                <div class="fgroup" style="margin-bottom:14px">
                    <label>Package</label>
                    <select name="package_id" id="eb_package"><option value="">— None —</option>@foreach($packages as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->provider?->name }})</option>@endforeach</select>
                </div>
                <div class="form-grid-3" style="margin-bottom:14px">
                    <div class="fgroup"><label>Data (GB)</label><input type="number" name="data_gb" id="eb_data" min="0" step="0.1"></div>
                    <div class="fgroup"><label>Minutes</label><input type="number" name="minutes" id="eb_minutes" min="0"></div>
                    <div class="fgroup"><label>SMS</label><input type="number" name="sms" id="eb_sms" min="0"></div>
                </div>
                <div class="form-grid-2" style="margin-bottom:14px">
                    <div class="fgroup"><label>Price ($)</label><input type="number" name="price" id="eb_price" min="0" step="0.01"></div>
                    <div class="fgroup"><label>Validity (days)</label><input type="number" name="validity_days" id="eb_validity" min="1"></div>
                </div>
                <div class="form-grid-3" style="margin-bottom:14px">
                    <div class="fgroup" style="grid-column:1/3"><label>Description</label><textarea name="description" id="eb_desc"></textarea></div>
                    <div class="fgroup"><label>Status</label><select name="is_active" id="eb_active"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                </div>
                <div class="fgroup" style="margin-bottom:14px"><label>New Image <span style="color:#8A8A9A;font-weight:400">(leave empty to keep)</span></label><input type="file" name="image" accept="image/*"></div>
                <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid #f0f1f5">
                    <button type="button" class="btn-outline" onclick="document.getElementById('editBundleModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-add"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- USER PHONES --}}
<div id="tab-user-phones" class="tab-pane">
<style>
.up-search { display:flex; gap:10px; align-items:center; margin-bottom:18px; }
.up-search input { flex:1; padding:9px 14px; border:1.5px solid #EEEEEE; border-radius:10px; font-size:13px; font-family:inherit; }
.up-search input:focus { outline:none; border-color:#1565C0; }
.provider-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; color:#fff; }
.user-avatar { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; color:#fff; flex-shrink:0; }
.phone-chip { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:8px; font-size:12px; font-weight:600; background:#f4f5fa; color:#1A1A2E; }
.phone-chip i { font-size:10px; color:#8A8A9A; }
.btn-email { padding:5px 12px; border-radius:8px; border:none; background:rgba(21,101,192,.1); color:#1565C0; font-size:12px; font-weight:700; cursor:pointer; transition:all .15s; font-family:inherit; display:inline-flex; align-items:center; gap:5px; }
.btn-email:hover { background:#1565C0; color:#fff; }
</style>
<div class="sc">
    <div class="sc-head">
        <div class="sc-title"><i class="fas fa-mobile-alt" style="color:#1565C0"></i> User Saved Phones
            <span style="background:rgba(21,101,192,.1);color:#1565C0;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;margin-left:8px;">{{ $userPhones->count() }}</span>
        </div>
        <div style="font-size:12px;color:#8A8A9A;">Userska eData checkout-ka loo soo save gareeyay</div>
    </div>
    <div style="padding:16px 20px 0;">
        <div class="up-search">
            <input type="text" id="upSearch" placeholder="🔍  Search by name, email, phone, or provider..." oninput="filterUserPhones(this.value)">
        </div>
    </div>
    <div style="overflow-x:auto;">
    <table class="dtbl" id="upTable">
        <thead>
            <tr>
                <th>User</th>
                <th>Provider</th>
                <th style="width:150px">💳 Payment Phone</th>
                <th style="width:150px">📶 Data Phone</th>
                <th>Saved On</th>
                <th style="width:90px">Action</th>
            </tr>
        </thead>
        <tbody>
        @forelse($userPhones as $row)
            @php
                $initials = strtoupper(substr($row->user_name ?? 'U', 0, 1) . (strpos($row->user_name ?? '', ' ') !== false ? substr(strstr($row->user_name, ' '), 1, 1) : ''));
                $avatarColors = ['#1565C0','#7c3aed','#10b981','#f59e0b','#ef4444','#06b6d4'];
                $avatarColor  = $avatarColors[crc32($row->user_name ?? '') % count($avatarColors)];
                $provColor    = $row->provider_color ?? '#1565C0';
            @endphp
            <tr data-search="{{ strtolower(($row->user_name ?? '') . ' ' . ($row->user_email ?? '') . ' ' . ($row->payment_phone ?? '') . ' ' . ($row->data_phone ?? '') . ' ' . ($row->provider_name ?? '')) }}">
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="user-avatar" style="background:{{ $avatarColor }}">{{ $initials }}</div>
                        <div>
                            <div style="font-weight:700;font-size:13px;">{{ $row->user_name ?? '—' }}</div>
                            <div style="font-size:11px;color:#8A8A9A;">{{ $row->user_email ?? '' }}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="provider-pill" style="background:{{ $provColor }}">{{ $row->provider_name ?? '—' }}</span>
                </td>
                <td>
                    <span class="phone-chip"><i class="fas fa-credit-card"></i> {{ $row->payment_phone ?? '—' }}</span>
                </td>
                <td>
                    <span class="phone-chip"><i class="fas fa-sim-card"></i> {{ $row->data_phone ?? '—' }}</span>
                </td>
                <td style="color:#8A8A9A;font-size:12px;">{{ \Carbon\Carbon::parse($row->updated_at)->diffForHumans() }}</td>
                <td>
                    @if($row->user_email)
                    <form method="POST" action="{{ route('admin.edata.phone.email', $row->id) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn-email" onclick="return confirm('Send email to {{ addslashes($row->user_name ?? $row->user_email) }}?')">
                            <i class="fas fa-envelope"></i> Email
                        </button>
                    </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr class="empty-row"><td colspan="6"><i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;color:#d1d5db"></i>Weli xog la soo keydifin</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
</div>

<script>
function switchTab(name, el) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.module-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    el.classList.add('active');
}
function filterPkg(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#pkgTable tbody tr:not(.empty-row)').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
function filterBundles(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#bundleList .bundle-row').forEach(r => {
        r.style.display = (r.dataset.search || '').includes(q) ? '' : 'none';
    });
}
function openAddPackage(provId) {
    document.getElementById('ap_provider').value = provId;
    document.getElementById('addPackageModal').classList.add('open');
}
function openAddBundle(provId) {
    openBulkModal();
    // Pre-select the provider
    const sel = document.getElementById('bulk_provider');
    if (sel) { sel.value = provId; bulkFilterPkgs(provId); }
}
function openEditProvider(prov) {
    document.getElementById('editProviderForm').action = `/admin/module-data/data/providers/${prov.id}`;
    document.getElementById('eprov_name').value   = prov.name || '';
    document.getElementById('eprov_color').value  = prov.color || '#1565C0';
    document.getElementById('eprov_sort').value   = prov.sort_order || 0;
    document.getElementById('eprov_active').value = prov.is_active ? '1' : '0';
    document.getElementById('editProviderModal').classList.add('open');
}
function openEditPackage(pkg) {
    document.getElementById('editPackageForm').action = `/admin/module-data/data/packages/${pkg.id}`;
    document.getElementById('epkg_name').value     = pkg.name || '';
    document.getElementById('epkg_provider').value = pkg.provider_id || '';
    document.getElementById('epkg_active').value   = pkg.is_active ? '1' : '0';
    document.getElementById('editPackageModal').classList.add('open');
}
function openEditBundle(bnd) {
    document.getElementById('editBundleForm').action = `/admin/module-data/data/bundles/${bnd.id}`;
    document.getElementById('eb_name').value     = bnd.name || '';
    document.getElementById('eb_provider').value = bnd.provider_id || '';
    document.getElementById('eb_package').value  = bnd.package_id || '';
    document.getElementById('eb_data').value     = bnd.data_gb || 0;
    document.getElementById('eb_minutes').value  = bnd.minutes || 0;
    document.getElementById('eb_sms').value      = bnd.sms || 0;
    document.getElementById('eb_price').value    = bnd.price || 0;
    document.getElementById('eb_validity').value = bnd.validity_days || 30;
    document.getElementById('eb_desc').value     = bnd.description || '';
    document.getElementById('eb_active').value   = bnd.is_active ? '1' : '0';
    document.getElementById('editBundleModal').classList.add('open');
}
function filterPackagesByProvider(providerId, targetId) {
    const sel = document.getElementById(targetId);
    if (!sel) return;
    sel.querySelectorAll('option').forEach(opt => {
        if (!opt.value) return; // keep "-- None --"
        opt.style.display = (!providerId || opt.dataset.provider == providerId) ? '' : 'none';
    });
    sel.value = '';
}
function filterUserPhones(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#upTable tbody tr:not(.empty-row)').forEach(r => {
        r.style.display = (r.dataset.search || '').includes(q) ? '' : 'none';
    });
}
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); });
});
</script>

@endsection
