@extends('admin.layouts.app')
@section('title', 'eGrocery Marketing')
@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-bullhorn" style="color:#ec4899"></i> Marketing</h2>
        <ol class="breadcrumb"><li><a href="{{ route('admin.module-data.egrocery.index') }}">eGrocery</a></li><li>Marketing</li></ol>
    </div>
</div>

@include('admin.egrocery._subnav')

@if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif

{{-- ═══ BANNERS ═══ --}}
<div class="card" style="margin-bottom:18px;">
    <div class="card-header">
        <div class="card-header-title"><i class="fas fa-image" style="color:#ec4899"></i> Banners</div>
        <button onclick="document.getElementById('addBannerModal').style.display='flex'" class="btn btn-sm" style="background:#fdf2f8;color:#be185d;"><i class="fas fa-plus"></i> Add Banner</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Preview</th><th>Title</th><th>Placement</th><th>Link</th><th>Schedule</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($banners as $b)
                <tr>
                    <td><img src="{{ asset('storage/'.$b->image) }}" style="width:80px;height:42px;object-fit:cover;border-radius:6px;" onerror="this.style.opacity=0.3"></td>
                    <td style="font-weight:600;">{{ $b->title ?: '—' }}</td>
                    <td><span class="badge badge-purple">{{ str_replace('_',' ',ucfirst($b->placement)) }}</span></td>
                    <td style="font-size:12px;color:#64748b;">{{ $b->link_type ? $b->link_type.': '.$b->link_value : '—' }}</td>
                    <td style="font-size:12px;color:#9ca3af;">
                        @if($b->starts_at || $b->ends_at){{ optional($b->starts_at)->format('M j') }} – {{ optional($b->ends_at)->format('M j') }}@else Always @endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.module-data.egrocery.banner.toggle', $b->id) }}">
                            @csrf
                            <button class="badge {{ $b->is_active ? 'badge-success' : 'badge-secondary' }}" style="border:none;cursor:pointer;">{{ $b->is_active ? 'Active' : 'Inactive' }}</button>
                        </form>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <button onclick="editBanner({{ $b->toJson() }})" class="btn btn-sm" style="background:#f1f5f9;color:#374151;"><i class="fas fa-pen"></i></button>
                            <form method="POST" action="{{ route('admin.module-data.egrocery.banner.destroy',$b->id) }}" onsubmit="return confirm('Delete banner?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:24px;">No banners yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ═══ SECTIONS ═══ --}}
<div class="card">
    <div class="card-header">
        <div class="card-header-title"><i class="fas fa-layout" style="color:#3b82f6"></i> Sections & Flash Deals</div>
        <button onclick="document.getElementById('addSectionModal').style.display='flex'" class="btn btn-sm" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-plus"></i> Add Section</button>
    </div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:14px;">
        @forelse($sections as $s)
        <div style="border:1px solid #e8edf5;border-radius:12px;overflow:hidden;{{ $s->is_active ? '' : 'opacity:.6;' }}">
            <div style="background:#fafbff;padding:14px 18px;display:flex;align-items:center;gap:12px;">
                <div style="flex:1;">
                    <div style="font-weight:700;font-size:14px;">{{ $s->title }}</div>
                    <div style="font-size:12px;color:#9ca3af;margin-top:2px;">
                        <span class="badge badge-info" style="font-size:10px;">{{ str_replace('_',' ',ucfirst($s->type)) }}</span>
                        <span class="badge badge-secondary" style="font-size:10px;margin-left:4px;">{{ $s->layout }}</span>
                        @if($s->ends_at)<span style="margin-left:8px;color:#f59e0b;"><i class="fas fa-clock"></i> ends {{ $s->ends_at->format('M j') }}</span>@endif
                    </div>
                </div>
                <div style="display:flex;gap:6px;align-items:center;">
                    <form method="POST" action="{{ route('admin.module-data.egrocery.section.toggle',$s->id) }}">
                        @csrf
                        <button class="badge {{ $s->is_active ? 'badge-success' : 'badge-secondary' }}" style="border:none;cursor:pointer;padding:5px 12px;">{{ $s->is_active ? 'Active' : 'Inactive' }}</button>
                    </form>
                    <button onclick="editSection({{ $s->toJson() }})" class="btn btn-sm" style="background:#f1f5f9;color:#374151;"><i class="fas fa-pen"></i></button>
                    <form method="POST" action="{{ route('admin.module-data.egrocery.section.destroy',$s->id) }}" onsubmit="return confirm('Delete section?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>

            @if($s->type === 'flash_deal')
            {{-- Flash Deal Editor --}}
            <div style="padding:12px 18px;border-top:1px solid #f1f5f9;">
                <div style="font-size:12px;font-weight:700;color:#8b5cf6;margin-bottom:10px;"><i class="fas fa-bolt"></i> Flash Deals in this section</div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                    @foreach($s->flashDeals as $fd)
                    <div style="background:#f5f3ff;border:1px solid #e9d5ff;border-radius:8px;padding:8px 12px;display:flex;align-items:center;gap:10px;">
                        <div>
                            <div style="font-size:12px;font-weight:700;">{{ $fd->variant?->product?->name }} — {{ $fd->variant?->label }}</div>
                            <div style="font-size:11px;color:#7c3aed;">
                                Deal: ${{ number_format($fd->deal_price, 2) }}
                                @if($fd->qty_limit) · Limit: {{ $fd->qty_sold }}/{{ $fd->qty_limit }} sold @endif
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.module-data.egrocery.flash.destroy',$fd->id) }}" onsubmit="return confirm('Remove flash deal?')">
                            @csrf @method('DELETE')
                            <button style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:14px;">&times;</button>
                        </form>
                    </div>
                    @endforeach
                </div>
                {{-- Add flash deal --}}
                <form method="POST" action="{{ route('admin.module-data.egrocery.flash.store') }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    @csrf
                    <input type="hidden" name="section_id" value="{{ $s->id }}">
                    <div>
                        <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:4px;">Variant</label>
                        <div style="position:relative;">
                            <input type="text" id="fdSearch_{{ $s->id }}" placeholder="Search variant..." class="form-control" style="width:220px;" autocomplete="off">
                            <div id="fdResults_{{ $s->id }}" style="position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e8edf5;border-radius:8px;display:none;z-index:10;max-height:180px;overflow-y:auto;box-shadow:0 8px 20px rgba(0,0,0,.1);"></div>
                            <input type="hidden" name="variant_id" id="fdVarId_{{ $s->id }}">
                        </div>
                    </div>
                    <div>
                        <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:4px;">Deal Price ($)</label>
                        <input type="number" name="deal_price" class="form-control" style="width:100px;" step="0.01" min="0" required>
                    </div>
                    <div>
                        <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:4px;">Qty Limit</label>
                        <input type="number" name="qty_limit" class="form-control" style="width:80px;" min="1">
                    </div>
                    <button type="submit" class="btn btn-sm" style="background:#8b5cf6;color:#fff;"><i class="fas fa-bolt"></i> Add Deal</button>
                </form>
            </div>
            @else
            {{-- Product Picker --}}
            <div style="padding:12px 18px;border-top:1px solid #f1f5f9;">
                <div style="font-size:12px;font-weight:700;color:#64748b;margin-bottom:8px;"><i class="fas fa-boxes-stacked"></i> Products ({{ $s->products->count() }})</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                    @foreach($s->products as $sp)
                    <span style="background:#eff6ff;color:#2563eb;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">{{ $sp->name }}</span>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('admin.module-data.egrocery.section.products',$s->id) }}" style="display:flex;gap:8px;align-items:flex-end;">
                    @csrf
                    <div>
                        <input type="text" id="spSearch_{{ $s->id }}" placeholder="Add product..." class="form-control" style="width:220px;" autocomplete="off">
                        <div id="spResults_{{ $s->id }}" style="position:absolute;background:#fff;border:1px solid #e8edf5;border-radius:8px;display:none;z-index:10;min-width:220px;max-height:180px;overflow-y:auto;box-shadow:0 8px 20px rgba(0,0,0,.1);"></div>
                    </div>
                    <div id="spIds_{{ $s->id }}" style="display:flex;flex-wrap:wrap;gap:4px;">
                        @foreach($s->products as $sp)
                        <input type="hidden" name="product_ids[]" value="{{ $sp->id }}" data-name="{{ $sp->name }}" class="section-pid-{{ $s->id }}">
                        @endforeach
                    </div>
                    <button type="submit" class="btn btn-sm" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-save"></i> Save Products</button>
                </form>
            </div>
            @endif
        </div>
        @empty
        <div style="text-align:center;color:#9ca3af;padding:32px;">No sections yet. Create one above.</div>
        @endforelse
    </div>
</div>

{{-- Add Banner Modal --}}
<div id="addBannerModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:480px;max-height:90vh;overflow-y:auto;box-shadow:0 25px 60px rgba(0,0,0,.2);">
        <div style="padding:20px;border-bottom:1px solid #f1f5f9;font-weight:800;display:flex;justify-content:space-between;align-items:center;">
            <span><i class="fas fa-image" style="color:#ec4899;margin-right:8px;"></i>Add Banner</span>
            <button onclick="document.getElementById('addBannerModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:18px;color:#9ca3af;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.module-data.egrocery.banner.store') }}" enctype="multipart/form-data" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
            @csrf
            <div><label class="form-label">Title (optional)</label><input type="text" name="title" class="form-control"></div>
            <div><label class="form-label">Image *</label><input type="file" name="image" class="form-control" accept="image/*" required></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label class="form-label">Placement *</label>
                    <select name="placement" class="form-control" required>
                        <option value="home_top">Home Top</option>
                        <option value="home_mid">Home Middle</option>
                        <option value="category">Category</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Link Type</label>
                    <select name="link_type" class="form-control">
                        <option value="">None</option>
                        <option value="product">Product</option>
                        <option value="category">Category</option>
                        <option value="section">Section</option>
                        <option value="url">URL</option>
                    </select>
                </div>
            </div>
            <div><label class="form-label">Link Value</label><input type="text" name="link_value" class="form-control" placeholder="slug, id, or URL"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div><label class="form-label">Starts At</label><input type="date" name="starts_at" class="form-control"></div>
                <div><label class="form-label">Ends At</label><input type="date" name="ends_at" class="form-control"></div>
            </div>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="is_active" value="1" checked style="width:16px;height:16px;accent-color:#10b981;">
                <span style="font-size:13px;">Active</span>
            </label>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Add Banner</button>
                <button type="button" onclick="document.getElementById('addBannerModal').style.display='none'" class="btn" style="background:#f1f5f9;color:#374151;">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- Add Section Modal --}}
<div id="addSectionModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:480px;box-shadow:0 25px 60px rgba(0,0,0,.2);">
        <div style="padding:20px;border-bottom:1px solid #f1f5f9;font-weight:800;display:flex;justify-content:space-between;align-items:center;">
            <span><i class="fas fa-layout" style="color:#3b82f6;margin-right:8px;"></i>Add Section</span>
            <button onclick="document.getElementById('addSectionModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:18px;color:#9ca3af;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.module-data.egrocery.section.store') }}" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div><label class="form-label">Title (EN) *</label><input type="text" name="title" class="form-control" required></div>
                <div><label class="form-label">Title (SO)</label><input type="text" name="title_so" class="form-control"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label class="form-label">Type *</label>
                    <select name="type" class="form-control" required>
                        <option value="featured">Featured</option>
                        <option value="best_sellers">Best Sellers</option>
                        <option value="new_arrivals">New Arrivals</option>
                        <option value="flash_deal">Flash Deal</option>
                        <option value="category_promo">Category Promo</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Layout</label>
                    <select name="layout" class="form-control">
                        <option value="grid">Grid</option>
                        <option value="horizontal_scroll">Horizontal Scroll</option>
                        <option value="hero">Hero</option>
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div><label class="form-label">Sort</label><input type="number" name="sort_order" class="form-control" value="0"></div>
                <div><label class="form-label">Starts At</label><input type="date" name="starts_at" class="form-control"></div>
                <div><label class="form-label">Ends At</label><input type="date" name="ends_at" class="form-control"></div>
            </div>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="is_active" value="1" checked style="width:16px;height:16px;accent-color:#10b981;">
                <span style="font-size:13px;">Active</span>
            </label>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Create Section</button>
                <button type="button" onclick="document.getElementById('addSectionModal').style.display='none'" class="btn" style="background:#f1f5f9;color:#374151;">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Section Modal --}}
<div id="editSectionModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:480px;box-shadow:0 25px 60px rgba(0,0,0,.2);">
        <div style="padding:20px;border-bottom:1px solid #f1f5f9;font-weight:800;display:flex;justify-content:space-between;align-items:center;">
            <span><i class="fas fa-pen" style="color:#FF8A00;margin-right:8px;"></i>Edit Section</span>
            <button onclick="document.getElementById('editSectionModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:18px;color:#9ca3af;">&times;</button>
        </div>
        <form id="editSectionForm" method="POST" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
            @csrf
            <div><label class="form-label">Title (EN) *</label><input type="text" name="title" id="esTitle" class="form-control" required></div>
            <div><label class="form-label">Title (SO)</label><input type="text" name="title_so" id="esTitleSo" class="form-control"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div><label class="form-label">Sort</label><input type="number" name="sort_order" id="esSort" class="form-control"></div>
                <div><label class="form-label">Starts At</label><input type="date" name="starts_at" id="esStart" class="form-control"></div>
                <div><label class="form-label">Ends At</label><input type="date" name="ends_at" id="esEnd" class="form-control"></div>
            </div>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="is_active" value="1" id="esActive" style="width:16px;height:16px;accent-color:#10b981;">
                <span style="font-size:13px;">Active</span>
            </label>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Save</button>
                <button type="button" onclick="document.getElementById('editSectionModal').style.display='none'" class="btn" style="background:#f1f5f9;color:#374151;">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Banner Modal --}}
<div id="editBannerModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:480px;max-height:90vh;overflow-y:auto;box-shadow:0 25px 60px rgba(0,0,0,.2);">
        <div style="padding:20px;border-bottom:1px solid #f1f5f9;font-weight:800;display:flex;justify-content:space-between;align-items:center;">
            <span><i class="fas fa-pen" style="color:#FF8A00;margin-right:8px;"></i>Edit Banner</span>
            <button onclick="document.getElementById('editBannerModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:18px;color:#9ca3af;">&times;</button>
        </div>
        <form id="editBannerForm" method="POST" enctype="multipart/form-data" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
            @csrf
            <div><label class="form-label">Title</label><input type="text" name="title" id="ebTitle" class="form-control"></div>
            <div><label class="form-label">New Image (optional)</label><input type="file" name="image" class="form-control" accept="image/*"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div><label class="form-label">Placement *</label>
                    <select name="placement" id="ebPlacement" class="form-control" required>
                        <option value="home_top">Home Top</option><option value="home_mid">Home Middle</option><option value="category">Category</option>
                    </select>
                </div>
                <div><label class="form-label">Link Type</label>
                    <select name="link_type" id="ebLinkType" class="form-control">
                        <option value="">None</option><option value="product">Product</option><option value="category">Category</option><option value="section">Section</option><option value="url">URL</option>
                    </select>
                </div>
            </div>
            <div><label class="form-label">Link Value</label><input type="text" name="link_value" id="ebLinkValue" class="form-control"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div><label class="form-label">Starts At</label><input type="date" name="starts_at" id="ebStart" class="form-control"></div>
                <div><label class="form-label">Ends At</label><input type="date" name="ends_at" id="ebEnd" class="form-control"></div>
            </div>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="is_active" value="1" id="ebActive" style="width:16px;height:16px;accent-color:#10b981;"><span style="font-size:13px;">Active</span>
            </label>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Save</button>
                <button type="button" onclick="document.getElementById('editBannerModal').style.display='none'" class="btn" style="background:#f1f5f9;color:#374151;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
const varSearchUrl = '{{ route("admin.module-data.egrocery.marketing.variant-search") }}';
const prodSearchUrl= '{{ route("admin.module-data.egrocery.marketing.product-search") }}';
const csrfToken    = document.querySelector('meta[name="csrf-token"]').content;
const sectionBase  = '{{ url("admin/module-data/egrocery/marketing/sections") }}';

// Flash deal variant search
document.querySelectorAll('[id^="fdSearch_"]').forEach(input => {
    const sid = input.id.replace('fdSearch_','');
    let timer;
    input.addEventListener('input', function() {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 2) { document.getElementById('fdResults_'+sid).style.display='none'; return; }
        timer = setTimeout(() => {
            fetch(varSearchUrl + '?q=' + encodeURIComponent(q))
                .then(r=>r.json()).then(data => {
                    const box = document.getElementById('fdResults_'+sid);
                    if (!data.length) { box.style.display='none'; return; }
                    box.innerHTML = data.map(v =>
                        `<div onclick="selectFdVariant('${sid}','${v.id}','${v.text.replace(/'/g,"\\'")}', this.closest('[id^=fdResults]'))"
                             style="padding:9px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;font-size:13px;"
                             onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background=''"
                        >${v.text} <span style="color:#10b981;float:right;">$${parseFloat(v.price).toFixed(2)}</span></div>`
                    ).join('');
                    box.style.display='block';
                });
        }, 300);
    });
});

function selectFdVariant(sid, id, text, box) {
    document.getElementById('fdVarId_'+sid).value = id;
    document.getElementById('fdSearch_'+sid).value = text;
    if (box) box.style.display='none';
}

function editSection(s) {
    document.getElementById('esTitle').value   = s.title;
    document.getElementById('esTitleSo').value = s.title_so || '';
    document.getElementById('esSort').value    = s.sort_order || 0;
    document.getElementById('esStart').value   = (s.starts_at || '').substring(0,10);
    document.getElementById('esEnd').value     = (s.ends_at || '').substring(0,10);
    document.getElementById('esActive').checked = !!s.is_active;
    document.getElementById('editSectionForm').action = sectionBase + '/' + s.id;
    document.getElementById('editSectionModal').style.display = 'flex';
}

function editBanner(b) {
    document.getElementById('ebTitle').value        = b.title || '';
    document.getElementById('ebPlacement').value    = b.placement;
    document.getElementById('ebLinkType').value     = b.link_type || '';
    document.getElementById('ebLinkValue').value    = b.link_value || '';
    document.getElementById('ebStart').value        = (b.starts_at||'').substring(0,10);
    document.getElementById('ebEnd').value          = (b.ends_at||'').substring(0,10);
    document.getElementById('ebActive').checked     = !!b.is_active;
    const bannerBase = '{{ url("admin/module-data/egrocery/marketing/banners") }}';
    document.getElementById('editBannerForm').action = bannerBase + '/' + b.id;
    document.getElementById('editBannerModal').style.display = 'flex';
}
</script>
@endsection
