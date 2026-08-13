@extends('admin.layouts.app')
@section('title', 'eFood — Full Management')
@section('content')

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- PAGE HEADER --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div class="page-header">
    <div>
        <h2 class="page-title">
            <i class="fas fa-utensils" style="color:var(--primary)"></i>
            eFood Management
        </h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">eFood</li>
        </ol>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- STATS CARDS --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div class="grid-4" style="margin-bottom:20px;">
    <div class="card" style="padding:18px;text-align:center;">
        <div style="font-size:28px;font-weight:800;color:var(--primary)">{{ $stats['total_restaurants'] }}</div>
        <div style="font-size:12px;color:#888;margin-top:4px;"><i class="fas fa-store"></i> Total Restaurants</div>
    </div>
    <div class="card" style="padding:18px;text-align:center;">
        <div style="font-size:28px;font-weight:800;color:#22c55e">{{ $stats['active_restaurants'] }}</div>
        <div style="font-size:12px;color:#888;margin-top:4px;"><i class="fas fa-check-circle"></i> Active Restaurants</div>
    </div>
    <div class="card" style="padding:18px;text-align:center;">
        <div style="font-size:28px;font-weight:800;color:#3b82f6">{{ $stats['total_orders'] }}</div>
        <div style="font-size:12px;color:#888;margin-top:4px;"><i class="fas fa-receipt"></i> Total Orders</div>
    </div>
    <div class="card" style="padding:18px;text-align:center;">
        <div style="font-size:28px;font-weight:800;color:var(--primary)">${{ number_format($stats['today_revenue'],2) }}</div>
        <div style="font-size:12px;color:#888;margin-top:4px;"><i class="fas fa-dollar-sign"></i> Today Revenue</div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- TAB NAVIGATION --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div style="display:flex;gap:0;border-bottom:2px solid #eee;margin-bottom:20px;flex-wrap:wrap;">
    @foreach([
        ['restaurants','fa-store','Restaurants'],
        ['categories','fa-th-large','Categories'],
        ['items','fa-hamburger','Food Items'],
        ['addons','fa-plus-circle','Addons'],
        ['banners','fa-images','Banners'],
        ['coupons','fa-tag','Offers & Coupons'],
        ['campaigns','fa-fire','Discount Campaigns'],
        ['orders','fa-receipt','Orders'],
    ] as [$key,$icon,$label])
    <button onclick="showTab('{{ $key }}')" id="tab-{{ $key }}"
        class="tab-btn {{ $key === 'restaurants' ? 'active' : '' }}"
        style="padding:10px 16px;border:none;background:none;font-weight:600;cursor:pointer;font-size:13px;
               {{ $key === 'restaurants' ? 'border-bottom:3px solid var(--primary);color:var(--primary)' : 'color:#888' }}">
        <i class="fas {{ $icon }}"></i> {{ $label }}
    </button>
    @endforeach
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- TAB: RESTAURANTS --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="section-restaurants">
    <div class="page-header" style="margin-bottom:12px;">
        <span class="text-muted" style="font-size:13px;">
            Manage all restaurants — add, edit, toggle open/closed status.
        </span>
        <button class="btn btn-primary btn-sm" onclick="openModal('addRestaurantModal')">
            <i class="fas fa-plus"></i> Add Restaurant
        </button>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Restaurant</th>
                        <th>Type / Cuisine</th>
                        <th>District</th>
                        <th>Delivery</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th>Open</th>
                        <th>Featured</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($restaurants as $r)
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                @if($r->logo)
                                    <img src="{{ $r->logo }}" style="width:40px;height:40px;border-radius:8px;object-fit:cover;" onerror="this.style.display='none'">
                                @else
                                    <div style="width:40px;height:40px;border-radius:8px;background:#FF8A0015;display:flex;align-items:center;justify-content:center;font-size:20px;">🍽️</div>
                                @endif
                                <div>
                                    <strong style="display:block;">{{ $r->name }}</strong>
                                    @if($r->parent_id)
                                        <span class="badge" style="background:#e0e7ff;color:#4338ca;font-size:10px;">Branch of {{ $r->parent?->name ?? '#'.$r->parent_id }}</span>
                                    @else
                                        <small class="text-muted">{{ $r->email ?? $r->phone ?? '—' }}</small>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td><span class="badge badge-info">{{ $r->vendor_type ?? 'Restaurant' }}</span></td>
                        <td>{{ $r->district?->name ?? '—' }}</td>
                        <td>
                            <small>{{ $r->delivery_time ?? '30' }} min</small><br>
                            <small class="text-muted">Min: ${{ number_format($r->minimum_order ?? 0,2) }}</small>
                        </td>
                        <td>
                            @if($r->rating)
                                <span style="color:#f59e0b;font-weight:700;">★ {{ number_format($r->rating,1) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $r->status === 'active' ? 'badge-success' : 'badge-danger' }}">
                                {{ ucfirst($r->status) }}
                            </span>
                        </td>
                        <td>
                            <button onclick="toggleOpen({{ $r->id }}, this)"
                                class="btn btn-sm {{ $r->is_open ? 'btn-success' : 'btn-secondary' }}"
                                style="min-width:58px;">
                                {{ $r->is_open ? 'Open' : 'Closed' }}
                            </button>
                        </td>
                        <td>
                            @if($r->is_featured)
                                <span class="badge badge-warning"><i class="fas fa-star"></i> Featured</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="d-flex gap-2">
                            <button class="btn btn-sm btn-secondary"
                                onclick="openEditRestaurant({{ json_encode($r) }})">
                                <i class="fas fa-edit"></i>
                            </button>
                            @if(!$r->parent_id)
                            <button class="btn btn-sm btn-primary" onclick="openBranchModal({{ $r->id }}, '{{ addslashes($r->name) }}')" title="Create Branch">
                                <i class="fas fa-code-branch"></i>
                            </button>
                            @endif
                            <form action="{{ route('admin.module-data.efood.restaurant.destroy', $r->id) }}" method="POST" onsubmit="return confirm('Delete {{ addslashes($r->name) }}?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" style="text-align:center;padding:40px;color:#888;">
                        <div style="font-size:40px;margin-bottom:12px;">🍽️</div>
                        No restaurants added yet. Add your first restaurant!
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($restaurants->hasPages())
        <div class="card-body">{{ $restaurants->links() }}</div>
        @endif
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- TAB: CATEGORIES --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="section-categories" style="display:none;">
    <div class="page-header" style="margin-bottom:12px;">
        <span class="text-muted" style="font-size:13px;">
            Global food categories shown on the app home page (Pizza, Burger, Chicken, etc.)
        </span>
        <div style="display:flex;gap:8px;align-items:center;">
            {{-- Bulk delete toolbar (hidden until selection) --}}
            <div id="cat-bulk-bar" style="display:none;align-items:center;gap:8px;background:#fff1f1;border:1px solid #fca5a5;padding:6px 12px;border-radius:8px;">
                <span id="cat-bulk-count" style="font-size:13px;font-weight:600;color:#dc2626;">0 selected</span>
                <button class="btn btn-danger btn-sm" onclick="catBulkDelete()">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
                <button class="btn btn-sm btn-outline" onclick="catClearSelection()" style="font-size:12px;">Cancel</button>
            </div>
            <button class="btn btn-primary btn-sm" onclick="openModal('addCategoryModal')">
                <i class="fas fa-plus"></i> Add Category
            </button>
        </div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table id="cat-table">
                <thead>
                    <tr>
                        <th style="width:36px;">
                            <input type="checkbox" id="cat-check-all" onchange="catToggleAll(this.checked)"
                                style="width:16px;height:16px;cursor:pointer;" title="Select all">
                        </th>
                        <th>Icon/Image</th><th>Category Name</th><th>Sort</th><th>Status</th><th>Scope</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $c)
                    <tr>
                        <td>
                            <input type="checkbox" class="cat-check" value="{{ $c->id }}" onchange="catUpdateBar()"
                                style="width:16px;height:16px;cursor:pointer;">
                        </td>
                        <td>
                            @if($c->image)
                                <img src="{{ $c->image }}" style="width:44px;height:44px;border-radius:10px;object-fit:cover;" onerror="this.style.display='none'">
                            @else
                                <div style="width:44px;height:44px;border-radius:10px;background:#FF8A0015;display:flex;align-items:center;justify-content:center;font-size:22px;">🍽️</div>
                            @endif
                        </td>
                        <td><strong>{{ $c->name }}</strong></td>
                        <td>{{ $c->sort_order ?? 0 }}</td>
                        <td><span class="badge {{ $c->is_active ? 'badge-success' : 'badge-danger' }}">{{ $c->is_active ? 'Active' : 'Off' }}</span></td>
                        <td>
                            @if($c->vendor_id)
                                <span class="badge badge-info">{{ $c->vendor?->name ?? 'Vendor #'.$c->vendor_id }}</span>
                            @else
                                <span class="badge badge-secondary">Global</span>
                            @endif
                        </td>
                        <td class="d-flex gap-2">
                            <button class="btn btn-sm btn-secondary" onclick="openEditCategory({{ json_encode($c) }})"><i class="fas fa-edit"></i></button>
                            @if(!$c->vendor_id)
                            <button class="btn btn-sm btn-primary" onclick="openAssignCategory({{ $c->id }}, '{{ addslashes($c->name) }}')" title="Assign to restaurants"><i class="fas fa-share-alt"></i></button>
                            @endif
                            <form action="{{ route('admin.module-data.efood.category.destroy', $c->id) }}" method="POST" onsubmit="return confirm('Delete?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="text-align:center;padding:30px;color:#888;">No categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
function catUpdateBar() {
    const checked = document.querySelectorAll('.cat-check:checked');
    const bar = document.getElementById('cat-bulk-bar');
    const countEl = document.getElementById('cat-bulk-count');
    const allCb = document.getElementById('cat-check-all');
    const total = document.querySelectorAll('.cat-check').length;

    bar.style.display = checked.length > 0 ? 'flex' : 'none';
    countEl.textContent = checked.length + ' selected';
    allCb.indeterminate = checked.length > 0 && checked.length < total;
    allCb.checked = checked.length === total && total > 0;
}

function catToggleAll(checked) {
    document.querySelectorAll('.cat-check').forEach(cb => cb.checked = checked);
    catUpdateBar();
}

function catClearSelection() {
    document.querySelectorAll('.cat-check').forEach(cb => cb.checked = false);
    document.getElementById('cat-check-all').checked = false;
    catUpdateBar();
}

function catBulkDelete() {
    const ids = [...document.querySelectorAll('.cat-check:checked')].map(cb => cb.value);
    if (!ids.length) return;
    if (!confirm('Delete ' + ids.length + ' categories? This cannot be undone.')) return;

    fetch('{{ route("admin.module-data.efood.category.bulk-destroy") }}', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ ids })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Remove deleted rows from DOM
            ids.forEach(id => {
                const cb = document.querySelector('.cat-check[value="' + id + '"]');
                if (cb) cb.closest('tr').remove();
            });
            catClearSelection();
        } else {
            alert('Error deleting categories.');
        }
    })
    .catch(() => alert('Network error. Please try again.'));
}
</script>
@endpush

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- TAB: FOOD ITEMS --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="section-items" style="display:none;">
    <div class="page-header" style="margin-bottom:12px;">
        <span class="text-muted" style="font-size:13px;">
            Manage all food items / menu products across all restaurants.
        </span>
        <button class="btn btn-primary btn-sm" onclick="openModal('addItemModal')">
            <i class="fas fa-plus"></i> Add Food Item
        </button>
    </div>

    {{-- Filter by restaurant --}}
    <div class="card" style="padding:12px 16px;margin-bottom:12px;">
        <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="tab" value="items">
            <select name="restaurant_filter" class="form-control" style="width:220px;" onchange="this.form.submit()">
                <option value="">All Restaurants</option>
                @foreach($allRestaurants as $ar)
                    <option value="{{ $ar->id }}" {{ request('restaurant_filter') == $ar->id ? 'selected' : '' }}>{{ $ar->name }}</option>
                @endforeach
            </select>
            <input type="text" name="search" class="form-control" style="width:200px;" placeholder="Search items..." value="{{ request('search') }}">
            <button class="btn btn-secondary btn-sm" type="submit"><i class="fas fa-filter"></i> Filter</button>
        </form>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Image</th><th>Name</th><th>Restaurant</th><th>Category</th><th>Price</th><th>Compare</th><th>Featured</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($foodItems as $item)
                    <tr>
                        <td>
                            @php $img = $item->thumbnail ?? $item->image ?? null; @endphp
                            @if($img)
                                <img src="{{ $img }}" style="width:50px;height:50px;border-radius:10px;object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <div style="display:none;width:50px;height:50px;border-radius:10px;background:#FF8A0015;align-items:center;justify-content:center;font-size:24px;">🍕</div>
                            @else
                                <div style="width:50px;height:50px;border-radius:10px;background:#FF8A0015;display:flex;align-items:center;justify-content:center;font-size:24px;">🍕</div>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $item->name }}</strong>
                            @if($item->description)
                            <br><small class="text-muted" style="max-width:200px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $item->description }}</small>
                            @endif
                        </td>
                        <td>{{ $item->vendor?->name ?? '—' }}</td>
                        <td>{{ $item->category?->name ?? '—' }}</td>
                        <td><strong class="text-success">${{ number_format($item->price,2) }}</strong></td>
                        <td>{{ $item->compare_price ? '$'.number_format($item->compare_price,2) : '—' }}</td>
                        <td>{{ $item->is_featured ? '⭐' : '—' }}</td>
                        <td><span class="badge {{ $item->is_active ? 'badge-success' : 'badge-danger' }}">{{ $item->is_active ? 'Active' : 'Off' }}</span></td>
                        <td class="d-flex gap-2">
                            <button class="btn btn-sm btn-secondary" onclick="openEditItem({{ json_encode($item) }})"><i class="fas fa-edit"></i></button>
                            <form action="{{ route('admin.module-data.efood.item.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Delete?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" style="text-align:center;padding:30px;color:#888;">
                        No food items yet. Add your first menu item!
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($foodItems->hasPages())
        <div class="card-body">{{ $foodItems->links() }}</div>
        @endif
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- TAB: ADDONS --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="section-addons" style="display:none;">
    <div class="page-header" style="margin-bottom:12px;">
        <span class="text-muted" style="font-size:13px;">
            Extras customers can add to food orders (Extra Cheese, Sauce, etc.)
        </span>
        <button class="btn btn-primary btn-sm" onclick="openModal('addAddonModal')">
            <i class="fas fa-plus"></i> Add Addon
        </button>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>#</th><th>Addon Name</th><th>Restaurant</th><th>Price</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($addons as $a)
                    <tr>
                        <td>{{ $a->id }}</td>
                        <td><strong>{{ $a->name }}</strong></td>
                        <td>{{ $a->vendor?->name ?? '—' }}</td>
                        <td><strong class="text-success">+${{ number_format($a->price,2) }}</strong></td>
                        <td><span class="badge {{ $a->is_active ? 'badge-success' : 'badge-danger' }}">{{ $a->is_active ? 'Active' : 'Off' }}</span></td>
                        <td class="d-flex gap-2">
                            <button class="btn btn-sm btn-secondary" onclick="openEditAddon({{ json_encode($a) }})"><i class="fas fa-edit"></i></button>
                            <form action="{{ route('admin.module-data.efood.addon.destroy', $a->id) }}" method="POST" onsubmit="return confirm('Delete?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="text-align:center;padding:30px;color:#888;">No addons yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- TAB: BANNERS --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="section-banners" style="display:none;">
    <div class="page-header" style="margin-bottom:12px;">
        <span class="text-muted" style="font-size:13px;">
            Promotional banners shown on the eFood home page slider.
        </span>
        <button class="btn btn-primary btn-sm" onclick="openModal('addBannerModal')">
            <i class="fas fa-plus"></i> Add Banner
        </button>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Preview</th><th>Title</th><th>Subtitle</th><th>Sort</th><th>Expires</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($allBanners as $b)
                    <tr>
                        <td>
                            @if($b->image)
                                <img src="{{ $b->image }}" style="width:90px;height:50px;border-radius:8px;object-fit:cover;">
                            @else
                                <div style="width:90px;height:50px;border-radius:8px;background:#07003B;display:flex;align-items:center;justify-content:center;color:#FF8A00;font-size:18px;">🍔</div>
                            @endif
                        </td>
                        <td><strong>{{ $b->title }}</strong></td>
                        <td class="text-muted" style="font-size:12px;">{{ $b->subtitle ?? '—' }}</td>
                        <td>{{ $b->sort_order }}</td>
                        <td style="font-size:12px;">{{ $b->ends_at ? \Carbon\Carbon::parse($b->ends_at)->format('d M Y') : 'No expiry' }}</td>
                        <td><span class="badge {{ $b->is_active ? 'badge-success' : 'badge-danger' }}">{{ $b->is_active ? 'Active' : 'Off' }}</span></td>
                        <td class="d-flex gap-2">
                            <button class="btn btn-sm btn-secondary" onclick="openEditBanner({{ json_encode($b) }})"><i class="fas fa-edit"></i></button>
                            <form action="{{ route('admin.module-data.efood.banner.destroy', $b->id) }}" method="POST" onsubmit="return confirm('Delete banner?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="text-align:center;padding:30px;color:#888;">No banners yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- TAB: COUPONS --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="section-coupons" style="display:none;">
    <div class="page-header" style="margin-bottom:12px;">
        <span class="text-muted" style="font-size:13px;">
            Discount coupons and promotional offers for eFood.
        </span>
        <button class="btn btn-primary btn-sm" onclick="openModal('addCouponModal')">
            <i class="fas fa-plus"></i> Create Coupon
        </button>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Code</th><th>Title</th><th>Type</th><th>Value</th><th>Min Order</th><th>Used</th><th>Restaurant</th><th>Expires</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($coupons as $c)
                    <tr>
                        <td>
                            <code style="background:#FF8A0015;color:#FF8A00;padding:3px 8px;border-radius:6px;font-weight:700;">
                                {{ $c->code }}
                            </code>
                        </td>
                        <td><strong>{{ $c->title }}</strong></td>
                        <td><span class="badge {{ $c->type === 'percentage' ? 'badge-info' : 'badge-warning' }}">{{ ucfirst($c->type) }}</span></td>
                        <td><strong class="text-success">{{ $c->type === 'percentage' ? $c->value.'%' : '$'.number_format($c->value,2) }}</strong></td>
                        <td>${{ number_format($c->min_order_amount ?? 0,2) }}</td>
                        <td>{{ $c->used_count ?? 0 }}{{ $c->usage_limit ? '/'.$c->usage_limit : '' }}</td>
                        <td>{{ $c->vendor?->name ?? 'All' }}</td>
                        <td style="font-size:12px;">{{ $c->ends_at ? \Carbon\Carbon::parse($c->ends_at)->format('d M Y') : '—' }}</td>
                        <td><span class="badge {{ $c->is_active ? 'badge-success' : 'badge-danger' }}">{{ $c->is_active ? 'Active' : 'Off' }}</span></td>
                        <td class="d-flex gap-2">
                            <button class="btn btn-sm btn-secondary" onclick="openEditCoupon({{ json_encode($c) }})"><i class="fas fa-edit"></i></button>
                            <form action="{{ route('admin.module-data.efood.coupon.destroy', $c->id) }}" method="POST" onsubmit="return confirm('Delete coupon?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="10" style="text-align:center;padding:30px;color:#888;">No coupons yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- TAB: DISCOUNT CAMPAIGNS --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="section-campaigns" style="display:none;">
    <div class="page-header" style="margin-bottom:12px;">
        <span class="text-muted" style="font-size:13px;">
            Restaurant discount campaigns — time-limited promotions shown in the customer app.
        </span>
        <button class="btn btn-primary btn-sm" onclick="openModal('addCampaignModal')">
            <i class="fas fa-plus"></i> New Campaign
        </button>
    </div>

    {{-- Campaign stats --}}
    @php
        $now = now();
        $activeCamps    = $campaigns->filter(fn($c) => $c->is_active && $c->starts_at <= $now && $c->ends_at >= $now);
        $scheduledCamps = $campaigns->filter(fn($c) => $c->is_active && $c->starts_at > $now);
        $expiredCamps   = $campaigns->filter(fn($c) => $c->ends_at < $now);
    @endphp
    <div class="grid-4" style="margin-bottom:16px;">
        <div class="card" style="padding:16px;text-align:center;border-top:3px solid #22c55e;">
            <div style="font-size:26px;font-weight:800;color:#22c55e;">{{ $activeCamps->count() }}</div>
            <div style="font-size:11px;color:#888;margin-top:4px;"><i class="fas fa-fire"></i> Active Campaigns</div>
        </div>
        <div class="card" style="padding:16px;text-align:center;border-top:3px solid #3b82f6;">
            <div style="font-size:26px;font-weight:800;color:#3b82f6;">{{ $scheduledCamps->count() }}</div>
            <div style="font-size:11px;color:#888;margin-top:4px;"><i class="fas fa-clock"></i> Scheduled</div>
        </div>
        <div class="card" style="padding:16px;text-align:center;border-top:3px solid #9ca3af;">
            <div style="font-size:26px;font-weight:800;color:#9ca3af;">{{ $expiredCamps->count() }}</div>
            <div style="font-size:11px;color:#888;margin-top:4px;"><i class="fas fa-history"></i> Expired</div>
        </div>
        <div class="card" style="padding:16px;text-align:center;border-top:3px solid var(--primary);">
            <div style="font-size:26px;font-weight:800;color:var(--primary);">{{ $campaigns->count() }}</div>
            <div style="font-size:11px;color:#888;margin-top:4px;"><i class="fas fa-list"></i> Total</div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Restaurant</th>
                        <th>Campaign Name</th>
                        <th>Discount</th>
                        <th>Badge</th>
                        <th>Period</th>
                        <th>Apply To</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $camp)
                    @php
                        $status = $camp->status;
                        $statusColor = match($status) {
                            'active'    => '#22c55e',
                            'scheduled' => '#3b82f6',
                            'expired'   => '#9ca3af',
                            default     => '#ef4444',
                        };
                        $badgeColors = [
                            'red'    => '#ef4444', 'green'  => '#22c55e',
                            'blue'   => '#3b82f6', 'orange' => '#FF8A00',
                            'purple' => '#8b5cf6', 'yellow' => '#f59e0b',
                        ];
                        $bc = $badgeColors[$camp->badge_color] ?? '#ef4444';
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $camp->vendor?->name ?? '—' }}</strong>
                        </td>
                        <td>
                            <strong>{{ $camp->name }}</strong>
                            @if($camp->description)
                                <br><small class="text-muted">{{ Str::limit($camp->description, 50) }}</small>
                            @endif
                        </td>
                        <td>
                            <strong class="text-success">
                                {{ $camp->discount_type === 'percentage'
                                    ? $camp->discount_value . '%'
                                    : '$' . number_format($camp->discount_value, 2) }}
                                OFF
                            </strong>
                        </td>
                        <td>
                            <span style="background:{{ $bc }};color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">
                                {{ $camp->badge_text }}
                            </span>
                        </td>
                        <td style="font-size:12px;">
                            {{ $camp->starts_at->format('d M Y H:i') }}<br>
                            <span class="text-muted">→ {{ $camp->ends_at->format('d M Y H:i') }}</span>
                        </td>
                        <td>
                            @if($camp->apply_to_all)
                                <span class="badge badge-info">All Items</span>
                            @else
                                <span class="badge badge-warning">Selected</span>
                            @endif
                        </td>
                        <td>
                            <span style="background:{{ $statusColor }};color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;text-transform:capitalize;">
                                {{ $status }}
                            </span>
                        </td>
                        <td class="d-flex gap-2">
                            <button class="btn btn-sm btn-secondary" onclick="openEditCampaign({{ json_encode($camp) }})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form action="{{ route('admin.module-data.efood.campaign.destroy', $camp->id) }}" method="POST" onsubmit="return confirm('Delete this campaign?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:40px;color:#888;">
                            <i class="fas fa-fire" style="font-size:32px;color:#ddd;display:block;margin-bottom:10px;"></i>
                            No campaigns yet. Click "New Campaign" to create one.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════ --}}
{{-- TAB: ORDERS --}}
{{-- ═══════════════════════════════════════════════════════════════ --}}
<div id="section-orders" style="display:none;">
    <div class="page-header" style="margin-bottom:12px;">
        <span class="text-muted" style="font-size:13px;">
            All food orders — track, update status, manage delivery.
        </span>
    </div>

    {{-- Order stats bar --}}
    <div class="grid-4" style="margin-bottom:16px;">
        @foreach([
            ['pending',    '#f59e0b', 'Pending',    'fa-clock'],
            ['confirmed',  '#3b82f6', 'Confirmed',  'fa-check'],
            ['on_the_way', '#FF8A00', 'On The Way', 'fa-motorcycle'],
            ['delivered',  '#22c55e', 'Delivered',  'fa-home'],
        ] as [$st,$col,$label,$ico])
        <div class="card" style="padding:14px;text-align:center;border-top:3px solid {{ $col }};">
            <div style="font-size:22px;font-weight:800;color:{{ $col }};">
                {{ $orders->where('status', $st)->count() }}
            </div>
            <div style="font-size:11px;color:#888;"><i class="fas {{ $ico }}"></i> {{ $label }}</div>
        </div>
        @endforeach
    </div>

    <div class="card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Order #</th><th>Customer</th><th>Restaurant</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($orders as $o)
                    <tr>
                        <td><strong style="color:var(--primary)">{{ $o->order_number }}</strong></td>
                        <td>
                            <strong>{{ $o->user?->name ?? 'Guest' }}</strong><br>
                            <small class="text-muted">{{ $o->user?->phone ?? '' }}</small>
                        </td>
                        <td>{{ $o->vendor?->name ?? '—' }}</td>
                        <td>
                            @php $itemCount = is_array($o->items) ? count($o->items) : ($o->items_count ?? '—'); @endphp
                            {{ $itemCount }} items
                        </td>
                        <td><strong>${{ number_format($o->total_amount ?? $o->grand_total ?? 0, 2) }}</strong></td>
                        <td>
                            @php $pm = $o->payment_method ?? 'cod'; $pmLabel = ucwords(str_replace('_',' ', $pm)); @endphp
                            <span class="badge {{ $o->payment_status === 'paid' ? 'badge-success' : ($pm === 'mobile_pay' ? 'badge-info' : 'badge-warning') }}">
                                {{ $pmLabel }}
                            </span>
                            @if($pm === 'mobile_pay')
                            <a href="{{ route('admin.orders.show', $o->id) }}" title="View proof" style="font-size:10px;color:#3949AB;display:block;margin-top:2px;"><i class="fas fa-image"></i> Proof</a>
                            @endif
                        </td>
                        <td>
                            @php
                                $stColors = ['pending'=>'badge-warning','confirmed'=>'badge-info','preparing'=>'badge-info','on_the_way'=>'badge-warning','delivered'=>'badge-success','cancelled'=>'badge-danger'];
                            @endphp
                            <span class="badge {{ $stColors[$o->status] ?? 'badge-secondary' }}">
                                {{ ucfirst(str_replace('_',' ',$o->status)) }}
                            </span>
                        </td>
                        <td style="font-size:12px;">{{ $o->created_at->format('d M Y H:i') }}</td>
                        <td>
                            <form action="{{ route('admin.module-data.efood.order.status', $o->id) }}" method="POST" style="display:flex;gap:4px;">
                                @csrf @method('PATCH')
                                <select name="status" class="form-control" style="padding:4px 8px;font-size:12px;width:120px;" onchange="this.form.submit()">
                                    @foreach(['pending','confirmed','preparing','on_the_way','delivered','cancelled'] as $st)
                                    <option value="{{ $st }}" {{ $o->status === $st ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_',' ',$st)) }}
                                    </option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" style="text-align:center;padding:40px;color:#888;">
                        <div style="font-size:40px;margin-bottom:12px;">📦</div>
                        No food orders yet.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
        <div class="card-body">{{ $orders->links() }}</div>
        @endif
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════════════
     MODALS
     ══════════════════════════════════════════════════════════════════ --}}

{{-- ─── ADD RESTAURANT ─────────────────────────────────────────────── --}}
<div class="modal-overlay" id="addRestaurantModal">
    <div class="modal-box" style="max-width:680px;max-height:90vh;overflow-y:auto;padding:0;border-radius:16px;">

        {{-- Header --}}
        <div style="background:linear-gradient(135deg,#07003B 0%,#1a0874 100%);padding:22px 24px;border-radius:16px 16px 0 0;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h3 style="color:#fff;font-size:17px;font-weight:800;margin:0;display:flex;align-items:center;gap:10px;">
                    <span style="width:36px;height:36px;background:rgba(255,138,0,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-store" style="color:#FF8A00;font-size:15px;"></i>
                    </span>
                    Add New Restaurant
                </h3>
                <p style="color:rgba(255,255,255,.55);font-size:12px;margin:4px 0 0 46px;">Fill in the details to register a new restaurant</p>
            </div>
            <button onclick="closeModal('addRestaurantModal')" style="background:rgba(255,255,255,.1);border:none;width:34px;height:34px;border-radius:8px;color:#fff;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;">✕</button>
        </div>

        <form action="{{ route('admin.module-data.efood.restaurant.store') }}" method="POST" enctype="multipart/form-data" style="padding:24px;display:flex;flex-direction:column;gap:0;">
            @csrf

            {{-- Section: Basic Info --}}
            <div style="margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                    <span style="width:4px;height:18px;background:#FF8A00;border-radius:2px;display:block;"></span>
                    <span style="font-size:12px;font-weight:800;color:#07003B;text-transform:uppercase;letter-spacing:.6px;">Basic Information</span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Restaurant Name <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Pizza House">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Cuisine / Type</label>
                        <input type="text" name="vendor_type" class="form-control" placeholder="e.g. Italian, Fast Food">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">District</label>
                        <select name="district_id" class="form-control">
                            <option value="">Select district</option>
                            @foreach($districts as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active">✅ Active</option>
                            <option value="inactive">⛔ Inactive</option>
                            <option value="pending">⏳ Pending</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="+252 61 XXXXXXX">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="info@restaurant.com">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Delivery Time</label>
                        <input type="text" name="delivery_time" class="form-control" placeholder="30-40 min">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Minimum Order ($)</label>
                        <input type="number" name="minimum_order" class="form-control" step="0.01" placeholder="10.00">
                    </div>
                </div>
                <div class="form-group" style="margin-top:14px;margin-bottom:0;">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Brief description of the restaurant..."></textarea>
                </div>
            </div>

            {{-- Divider --}}
            <div style="height:1px;background:linear-gradient(90deg,#e2e8f0,transparent);margin-bottom:20px;"></div>

            {{-- Section: Images --}}
            <div style="margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                    <span style="width:4px;height:18px;background:#FF8A00;border-radius:2px;display:block;"></span>
                    <span style="font-size:12px;font-weight:800;color:#07003B;text-transform:uppercase;letter-spacing:.6px;">Images</span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Logo</label>
                        <div onclick="document.getElementById('addR_logoFile').click()" style="border:2px dashed #FF8A00;border-radius:12px;padding:16px 10px;text-align:center;cursor:pointer;background:linear-gradient(135deg,#fffbf5,#fff9f0);min-height:100px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:6px;transition:all .2s;" onmouseenter="this.style.background='#fff5e6'" onmouseleave="this.style.background='linear-gradient(135deg,#fffbf5,#fff9f0)'">
                            <img id="addR_logoPreview" src="" style="display:none;max-height:70px;border-radius:8px;object-fit:cover;">
                            <div id="addR_logoPlaceholder">
                                <i class="fas fa-store" style="font-size:24px;color:#FF8A00;margin-bottom:6px;display:block;"></i>
                                <span style="font-size:11px;color:#FF8A00;font-weight:700;">Upload Logo</span>
                            </div>
                        </div>
                        <input type="file" id="addR_logoFile" name="logo_file" accept="image/*" style="display:none" onchange="previewImage(this,'addR_logoPreview','addR_logoPlaceholder')">
                        <input type="text" name="logo" class="form-control" placeholder="Or paste URL..." style="margin-top:6px;font-size:12px;" oninput="previewFromUrl(this.value,'addR_logoPreview','addR_logoPlaceholder')">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Cover Image</label>
                        <div onclick="document.getElementById('addR_coverFile').click()" style="border:2px dashed #07003B;border-radius:12px;padding:16px 10px;text-align:center;cursor:pointer;background:linear-gradient(135deg,#f7f7ff,#f0f0ff);min-height:100px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:6px;transition:all .2s;" onmouseenter="this.style.background='#ebebff'" onmouseleave="this.style.background='linear-gradient(135deg,#f7f7ff,#f0f0ff)'">
                            <img id="addR_coverPreview" src="" style="display:none;max-height:70px;border-radius:8px;object-fit:cover;">
                            <div id="addR_coverPlaceholder">
                                <i class="fas fa-image" style="font-size:24px;color:#07003B;margin-bottom:6px;display:block;"></i>
                                <span style="font-size:11px;color:#07003B;font-weight:700;">Upload Cover</span>
                            </div>
                        </div>
                        <input type="file" id="addR_coverFile" name="cover_file" accept="image/*" style="display:none" onchange="previewImage(this,'addR_coverPreview','addR_coverPlaceholder')">
                        <input type="text" name="cover_image" class="form-control" placeholder="Or paste URL..." style="margin-top:6px;font-size:12px;" oninput="previewFromUrl(this.value,'addR_coverPreview','addR_coverPlaceholder')">
                    </div>
                </div>
            </div>

            {{-- Divider --}}
            <div style="height:1px;background:linear-gradient(90deg,#e2e8f0,transparent);margin-bottom:20px;"></div>

            {{-- Section: Location --}}
            <div style="margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                    <span style="width:4px;height:18px;background:#FF8A00;border-radius:2px;display:block;"></span>
                    <span style="font-size:12px;font-weight:800;color:#07003B;text-transform:uppercase;letter-spacing:.6px;">Store Location</span>
                </div>
                {{-- Search bar --}}
                <div style="position:relative;margin-bottom:10px;">
                    <i class="fas fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:13px;z-index:1;"></i>
                    <input type="text" id="addR_mapSearch" class="form-control" placeholder="Search for location..." style="padding-left:36px;">
                </div>
                {{-- Map --}}
                <div style="position:relative;border-radius:12px;overflow:hidden;border:1.5px solid #e2e8f0;box-shadow:0 2px 12px rgba(0,0,0,.07);">
                    <div id="adminAddMap" style="width:100%;height:240px;"></div>
                    {{-- Center pin --}}
                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-100%);pointer-events:none;z-index:5;">
                        <i class="fas fa-location-dot" style="font-size:32px;color:#FF8A00;filter:drop-shadow(0 2px 6px rgba(255,138,0,.5));"></i>
                    </div>
                    {{-- My location --}}
                    <button type="button" onclick="adminAddMyLocation()" title="Use my location"
                        style="position:absolute;top:10px;right:10px;z-index:5;width:36px;height:36px;border-radius:10px;background:#fff;border:1.5px solid #e2e8f0;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.15);">
                        <i class="fas fa-location-crosshairs" style="color:#FF8A00;font-size:14px;"></i>
                    </button>
                </div>
                {{-- Selected location display --}}
                <div id="addR_addrBox" style="display:none;margin-top:8px;padding:10px 14px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #bbf7d0;border-radius:10px;font-size:12.5px;color:#166534;font-weight:600;display:none;align-items:center;gap:8px;">
                    <i class="fas fa-circle-check" style="color:#16a34a;font-size:15px;"></i>
                    <span id="addR_addrText"></span>
                </div>
                <input type="hidden" name="latitude"  id="addR_lat">
                <input type="hidden" name="longitude" id="addR_lng">
                <div class="form-group" style="margin-top:10px;margin-bottom:0;">
                    <label class="form-label">Address <small style="font-weight:400;color:#94a3b8;">(auto-filled from map)</small></label>
                    <input type="text" name="address" id="addR_address" class="form-control" placeholder="Full street address">
                </div>
            </div>

            {{-- Divider --}}
            <div style="height:1px;background:linear-gradient(90deg,#e2e8f0,transparent);margin-bottom:20px;"></div>

            {{-- Section: Working Hours --}}
            <div style="margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                    <span style="width:4px;height:18px;background:#FF8A00;border-radius:2px;display:block;"></span>
                    <span style="font-size:12px;font-weight:800;color:#07003B;text-transform:uppercase;letter-spacing:.6px;">Working Hours</span>
                    <span style="font-size:11px;color:#94a3b8;font-weight:400;">(uncheck = closed)</span>
                </div>
                @php $dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; @endphp
                <div style="border:1.5px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                    @foreach($dayNames as $di => $dn)
                    <div style="display:flex;align-items:center;gap:12px;padding:9px 14px;background:{{ $di % 2 === 0 ? '#f8fafc' : '#fff' }};border-bottom:{{ $di < 6 ? '1px solid #f0f2f6' : 'none' }};">
                        <input type="checkbox" name="wh_open[]" value="{{ $di }}" id="addR_wh_{{ $di }}"
                            {{ in_array($di, [0,1,2,3,4]) ? 'checked' : '' }}
                            style="width:16px;height:16px;cursor:pointer;accent-color:#FF8A00;">
                        <label for="addR_wh_{{ $di }}" style="min-width:82px;font-size:13px;font-weight:600;color:#374151;cursor:pointer;">{{ $dn }}</label>
                        <input type="time" name="wh_open_time[{{ $di }}]" value="08:00" class="form-control" style="width:110px;font-size:12px;padding:5px 8px;">
                        <span style="color:#94a3b8;font-size:11px;font-weight:600;">TO</span>
                        <input type="time" name="wh_close_time[{{ $di }}]" value="22:00" class="form-control" style="width:110px;font-size:12px;padding:5px 8px;">
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Footer options + Submit --}}
            <div style="display:flex;align-items:center;justify-content:space-between;padding-top:4px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600;color:#374151;">
                    <input type="checkbox" name="is_featured" value="1" style="width:16px;height:16px;accent-color:#FF8A00;">
                    <i class="fas fa-star" style="color:#f59e0b;"></i> Mark as Featured
                </label>
                <div style="display:flex;gap:10px;">
                    <button type="button" onclick="closeModal('addRestaurantModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="min-width:160px;">
                        <i class="fas fa-plus"></i> Add Restaurant
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ─── EDIT RESTAURANT ─────────────────────────────────────────────── --}}
<div class="modal-overlay" id="editRestaurantModal">
    <div class="modal-box" style="max-width:680px;max-height:90vh;overflow-y:auto;padding:0;border-radius:16px;">

        {{-- Header --}}
        <div style="background:linear-gradient(135deg,#1a0874 0%,#FF8A00 100%);padding:22px 24px;border-radius:16px 16px 0 0;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10;">
            <div>
                <h3 style="color:#fff;font-size:17px;font-weight:800;margin:0;display:flex;align-items:center;gap:10px;">
                    <span style="width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-pen-to-square" style="color:#fff;font-size:15px;"></i>
                    </span>
                    Edit Restaurant
                </h3>
                <p style="color:rgba(255,255,255,.7);font-size:12px;margin:4px 0 0 46px;">Update the restaurant details below</p>
            </div>
            <button onclick="closeModal('editRestaurantModal')" style="background:rgba(255,255,255,.15);border:none;width:34px;height:34px;border-radius:8px;color:#fff;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;">✕</button>
        </div>

        <form id="editRestaurantForm" method="POST" enctype="multipart/form-data" style="padding:24px;display:flex;flex-direction:column;gap:0;">
            @csrf @method('PATCH')

            {{-- Section: Basic Info --}}
            <div style="margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                    <span style="width:4px;height:18px;background:#FF8A00;border-radius:2px;display:block;"></span>
                    <span style="font-size:12px;font-weight:800;color:#07003B;text-transform:uppercase;letter-spacing:.6px;">Basic Information</span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Restaurant Name <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="name" id="er_name" class="form-control" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Cuisine / Type</label>
                        <input type="text" name="vendor_type" id="er_type" class="form-control">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">District</label>
                        <select name="district_id" id="er_district" class="form-control">
                            <option value="">Select district</option>
                            @foreach($districts as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Status</label>
                        <select name="status" id="er_status" class="form-control">
                            <option value="active">✅ Active</option>
                            <option value="inactive">⛔ Inactive</option>
                            <option value="pending">⏳ Pending</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="er_phone" class="form-control">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="er_email" class="form-control">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Delivery Time</label>
                        <input type="text" name="delivery_time" id="er_delivery_time" class="form-control">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Minimum Order ($)</label>
                        <input type="number" name="minimum_order" id="er_min_order" class="form-control" step="0.01">
                    </div>
                </div>
                <div class="form-group" style="margin-top:14px;margin-bottom:0;">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="er_desc" class="form-control" rows="2"></textarea>
                </div>
            </div>

            {{-- Owner Credentials --}}
            <div style="margin-top:18px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                    <span style="width:4px;height:18px;background:#E74C3C;border-radius:2px;display:block;"></span>
                    <span style="font-size:12px;font-weight:800;color:#07003B;text-transform:uppercase;letter-spacing:.6px;">Owner Account</span>
                    <span style="font-size:11px;color:#94a3b8;">(login credentials)</span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Owner Phone</label>
                        <input type="text" name="owner_phone" id="er_owner_phone" class="form-control" placeholder="Login phone">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Owner Email</label>
                        <input type="email" name="owner_email" id="er_owner_email" class="form-control" placeholder="Login email">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">New Password</label>
                        <input type="text" name="owner_password" id="er_owner_pass" class="form-control" placeholder="Leave empty to keep">
                    </div>
                </div>
            </div>

            {{-- Divider --}}
            <div style="height:1px;background:linear-gradient(90deg,#e2e8f0,transparent);margin-bottom:20px;"></div>

            {{-- Section: Images --}}
            <div style="margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                    <span style="width:4px;height:18px;background:#FF8A00;border-radius:2px;display:block;"></span>
                    <span style="font-size:12px;font-weight:800;color:#07003B;text-transform:uppercase;letter-spacing:.6px;">Images</span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Logo</label>
                        <div onclick="document.getElementById('editR_logoFile').click()" style="border:2px dashed #FF8A00;border-radius:12px;padding:16px 10px;text-align:center;cursor:pointer;background:linear-gradient(135deg,#fffbf5,#fff9f0);min-height:100px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:6px;">
                            <img id="editR_logoPreview" src="" style="display:none;max-height:70px;border-radius:8px;object-fit:cover;">
                            <div id="editR_logoPlaceholder">
                                <i class="fas fa-store" style="font-size:24px;color:#FF8A00;margin-bottom:6px;display:block;"></i>
                                <span style="font-size:11px;color:#FF8A00;font-weight:700;">Change Logo</span>
                            </div>
                        </div>
                        <input type="file" id="editR_logoFile" name="logo_file" accept="image/*" style="display:none" onchange="previewImage(this,'editR_logoPreview','editR_logoPlaceholder')">
                        <input type="text" name="logo" id="er_logo" class="form-control" placeholder="Or paste URL..." style="margin-top:6px;font-size:12px;" oninput="previewFromUrl(this.value,'editR_logoPreview','editR_logoPlaceholder')">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Cover Image</label>
                        <div onclick="document.getElementById('editR_coverFile').click()" style="border:2px dashed #07003B;border-radius:12px;padding:16px 10px;text-align:center;cursor:pointer;background:linear-gradient(135deg,#f7f7ff,#f0f0ff);min-height:100px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:6px;">
                            <img id="editR_coverPreview" src="" style="display:none;max-height:70px;border-radius:8px;object-fit:cover;">
                            <div id="editR_coverPlaceholder">
                                <i class="fas fa-image" style="font-size:24px;color:#07003B;margin-bottom:6px;display:block;"></i>
                                <span style="font-size:11px;color:#07003B;font-weight:700;">Change Cover</span>
                            </div>
                        </div>
                        <input type="file" id="editR_coverFile" name="cover_file" accept="image/*" style="display:none" onchange="previewImage(this,'editR_coverPreview','editR_coverPlaceholder')">
                        <input type="text" name="cover_image" id="er_cover" class="form-control" placeholder="Or paste URL..." style="margin-top:6px;font-size:12px;" oninput="previewFromUrl(this.value,'editR_coverPreview','editR_coverPlaceholder')">
                    </div>
                </div>
            </div>

            {{-- Divider --}}
            <div style="height:1px;background:linear-gradient(90deg,#e2e8f0,transparent);margin-bottom:20px;"></div>

            {{-- Section: Location --}}
            <div style="margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                    <span style="width:4px;height:18px;background:#FF8A00;border-radius:2px;display:block;"></span>
                    <span style="font-size:12px;font-weight:800;color:#07003B;text-transform:uppercase;letter-spacing:.6px;">Store Location</span>
                </div>
                <div style="position:relative;margin-bottom:10px;">
                    <i class="fas fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:13px;z-index:1;"></i>
                    <input type="text" id="editR_mapSearch" class="form-control" placeholder="Search for location..." style="padding-left:36px;">
                </div>
                <div style="position:relative;border-radius:12px;overflow:hidden;border:1.5px solid #e2e8f0;box-shadow:0 2px 12px rgba(0,0,0,.07);">
                    <div id="adminEditMap" style="width:100%;height:240px;"></div>
                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-100%);pointer-events:none;z-index:5;">
                        <i class="fas fa-location-dot" style="font-size:32px;color:#FF8A00;filter:drop-shadow(0 2px 6px rgba(255,138,0,.5));"></i>
                    </div>
                    <button type="button" onclick="adminEditMyLocation()" title="Use my location"
                        style="position:absolute;top:10px;right:10px;z-index:5;width:36px;height:36px;border-radius:10px;background:#fff;border:1.5px solid #e2e8f0;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.15);">
                        <i class="fas fa-location-crosshairs" style="color:#FF8A00;font-size:14px;"></i>
                    </button>
                </div>
                <div id="editR_addrBox" style="display:none;margin-top:8px;padding:10px 14px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #bbf7d0;border-radius:10px;font-size:12.5px;color:#166534;font-weight:600;align-items:center;gap:8px;">
                    <i class="fas fa-circle-check" style="color:#16a34a;font-size:15px;"></i>
                    <span id="editR_addrText"></span>
                </div>
                <input type="hidden" name="latitude"  id="er_lat">
                <input type="hidden" name="longitude" id="er_lng">
                <div class="form-group" style="margin-top:10px;margin-bottom:0;">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" id="er_address" class="form-control">
                </div>
            </div>

            {{-- Divider --}}
            <div style="height:1px;background:linear-gradient(90deg,#e2e8f0,transparent);margin-bottom:20px;"></div>

            {{-- Section: Working Hours --}}
            <div style="margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                    <span style="width:4px;height:18px;background:#FF8A00;border-radius:2px;display:block;"></span>
                    <span style="font-size:12px;font-weight:800;color:#07003B;text-transform:uppercase;letter-spacing:.6px;">Working Hours</span>
                    <span style="font-size:11px;color:#94a3b8;font-weight:400;">(uncheck = closed)</span>
                </div>
                <div style="border:1.5px solid #e2e8f0;border-radius:12px;overflow:hidden;" id="editWhGrid">
                    @foreach($dayNames as $di => $dn)
                    <div style="display:flex;align-items:center;gap:12px;padding:9px 14px;background:{{ $di % 2 === 0 ? '#f8fafc' : '#fff' }};border-bottom:{{ $di < 6 ? '1px solid #f0f2f6' : 'none' }};">
                        <input type="checkbox" name="wh_open[]" value="{{ $di }}" id="er_wh_{{ $di }}"
                            style="width:16px;height:16px;cursor:pointer;accent-color:#FF8A00;">
                        <label for="er_wh_{{ $di }}" style="min-width:82px;font-size:13px;font-weight:600;color:#374151;cursor:pointer;">{{ $dn }}</label>
                        <input type="time" name="wh_open_time[{{ $di }}]" id="er_wh_open_{{ $di }}" value="08:00" class="form-control" style="width:110px;font-size:12px;padding:5px 8px;">
                        <span style="color:#94a3b8;font-size:11px;font-weight:600;">TO</span>
                        <input type="time" name="wh_close_time[{{ $di }}]" id="er_wh_close_{{ $di }}" value="22:00" class="form-control" style="width:110px;font-size:12px;padding:5px 8px;">
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Footer --}}
            <div style="display:flex;align-items:center;justify-content:space-between;padding-top:4px;">
                <div style="display:flex;gap:16px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600;color:#374151;">
                        <input type="checkbox" name="is_featured" id="er_featured" value="1" style="width:16px;height:16px;accent-color:#f59e0b;">
                        <i class="fas fa-star" style="color:#f59e0b;"></i> Featured
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600;color:#374151;">
                        <input type="checkbox" name="is_open" id="er_open" value="1" style="width:16px;height:16px;accent-color:#22c55e;">
                        <i class="fas fa-door-open" style="color:#22c55e;"></i> Currently Open
                    </label>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="button" onclick="closeModal('editRestaurantModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="min-width:160px;">
                        <i class="fas fa-floppy-disk"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ─── ADD CATEGORY ────────────────────────────────────────────────── --}}
<div class="modal-overlay" id="addCategoryModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Add Food Category</h3>
            <button class="modal-close" onclick="closeModal('addCategoryModal')">✕</button>
        </div>
        <form action="{{ route('admin.module-data.efood.category.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="form-label">Category Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Pizza, Burger, Chicken">
            </div>
            <div class="form-group">
                <label class="form-label">Category Icon / Image</label>
                <div onclick="document.getElementById('addCat_imgFile').click()" style="border:2px dashed #FF8A00;border-radius:10px;padding:10px;text-align:center;cursor:pointer;background:#fff9f2;min-height:80px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:6px;">
                    <img id="addCat_imgPreview" src="" style="display:none;max-height:70px;border-radius:8px;object-fit:cover;">
                    <span id="addCat_imgPlaceholder" style="color:#FF8A00;font-size:12px;">🍽️ Upload Icon/Image</span>
                </div>
                <input type="file" id="addCat_imgFile" name="image_file" accept="image/*" style="display:none" onchange="previewImage(this,'addCat_imgPreview','addCat_imgPlaceholder')">
                <input type="text" name="image" class="form-control" placeholder="Or paste image URL..." style="margin-top:6px;font-size:12px;" oninput="previewFromUrl(this.value,'addCat_imgPreview','addCat_imgPlaceholder')">
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
                <div class="form-group" style="padding-top:28px;">
                    <label style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" name="is_active" value="1" checked> Active
                    </label>
                </div>
            </div>

            {{-- ── Restaurant Assignment (optional) ──────────────────── --}}
            <div style="border:1px solid #e9ecef;border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:0;font-size:13px;font-weight:600;">
                    <input type="checkbox" id="cat_assign_toggle" onchange="catToggleAssign(this.checked)"
                           style="width:15px;height:15px;accent-color:#FF8A00;">
                    Assign to restaurants? <span style="font-weight:400;color:#888;">(optional — leave unchecked for global category)</span>
                </label>
                <div id="cat_assign_panel" style="display:none;margin-top:10px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                        <span style="font-size:12px;color:#555;" id="cat_assign_count">0 selected</span>
                        <span style="display:flex;gap:6px;">
                            <button type="button" onclick="catSelectAll()" style="font-size:11px;padding:2px 8px;background:#FF8A00;color:#fff;border:none;border-radius:4px;cursor:pointer;">All</button>
                            <button type="button" onclick="catClearAll()" style="font-size:11px;padding:2px 8px;background:#6c757d;color:#fff;border:none;border-radius:4px;cursor:pointer;">Clear</button>
                        </span>
                    </div>
                    <input type="text" placeholder="🔍 Search restaurants..." oninput="catFilterRest(this.value)"
                           style="width:100%;padding:6px 10px;border:1px solid #dee2e6;border-radius:6px;margin-bottom:6px;font-size:12px;box-sizing:border-box;">
                    <div id="cat_rest_list" style="max-height:160px;overflow-y:auto;border:1px solid #dee2e6;border-radius:8px;padding:4px;">
                        @foreach($allRestaurants as $ar)
                        <label style="display:flex;align-items:center;gap:8px;padding:5px 10px;border-radius:6px;cursor:pointer;font-size:13px;"
                               onmouseover="this.style.background='#fff3e0'" onmouseout="this.style.background=''">
                            <input type="checkbox" name="vendor_ids[]" value="{{ $ar->id }}"
                                   onchange="catUpdateCount()"
                                   style="width:14px;height:14px;accent-color:#FF8A00;cursor:pointer;">
                            {{ $ar->name }}
                        </label>
                        @endforeach
                    </div>
                    <p style="font-size:11px;color:#888;margin:6px 0 0;">
                        One category copy will be created per selected restaurant.
                    </p>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;">Add Category</button>
        </form>
    </div>
</div>

{{-- ─── EDIT CATEGORY ───────────────────────────────────────────────── --}}
<div class="modal-overlay" id="editCategoryModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Edit Category</h3>
            <button class="modal-close" onclick="closeModal('editCategoryModal')">✕</button>
        </div>
        <form id="editCategoryForm" method="POST" enctype="multipart/form-data">
            @csrf @method('PATCH')
            <div class="form-group">
                <label class="form-label">Category Name *</label>
                <input type="text" name="name" id="ec_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Category Icon / Image</label>
                <div onclick="document.getElementById('editCat_imgFile').click()" style="border:2px dashed #FF8A00;border-radius:10px;padding:10px;text-align:center;cursor:pointer;background:#fff9f2;min-height:80px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:6px;">
                    <img id="editCat_imgPreview" src="" style="display:none;max-height:70px;border-radius:8px;object-fit:cover;">
                    <span id="editCat_imgPlaceholder" style="color:#FF8A00;font-size:12px;">🍽️ Click to change image</span>
                </div>
                <input type="file" id="editCat_imgFile" name="image_file" accept="image/*" style="display:none" onchange="previewImage(this,'editCat_imgPreview','editCat_imgPlaceholder')">
                <input type="text" name="image" id="ec_image" class="form-control" placeholder="Or paste image URL..." style="margin-top:6px;font-size:12px;" oninput="previewFromUrl(this.value,'editCat_imgPreview','editCat_imgPlaceholder')">
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" id="ec_sort" class="form-control">
                </div>
                <div class="form-group" style="padding-top:28px;">
                    <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" id="ec_active" value="1"> Active</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Save Changes</button>
        </form>
    </div>
</div>

{{-- ─── ADD FOOD ITEM ───────────────────────────────────────────────── --}}
<div class="modal-overlay" id="addItemModal">
    <div class="modal-box" style="max-width:600px;max-height:85vh;overflow-y:auto;">
        <div class="modal-header">
            <h3 class="modal-title">Add Food Item</h3>
            <button class="modal-close" onclick="closeModal('addItemModal')">✕</button>
        </div>
        <form action="{{ route('admin.module-data.efood.item.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="form-label">Restaurant *</label>
                <select name="vendor_id" class="form-control" required>
                    <option value="">Select restaurant</option>
                    @foreach($allRestaurants as $ar)
                        <option value="{{ $ar->id }}">{{ $ar->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-control">
                    <option value="">No category</option>
                    @php $globalCats = $categories->whereNull('vendor_id'); $vendorCats = $categories->whereNotNull('vendor_id')->groupBy('vendor_id'); @endphp
                    @if($globalCats->isNotEmpty())
                    <optgroup label="— Global Categories —">
                        @foreach($globalCats as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </optgroup>
                    @endif
                    @foreach($vendorCats as $vid => $cats)
                    <optgroup label="{{ $cats->first()->vendor?->name ?? 'Restaurant #'.$vid }}">
                        @foreach($cats as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Item Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Pepperoni Pizza">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Ingredients, description..."></textarea>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Price ($) *</label>
                    <input type="number" name="price" class="form-control" step="0.01" required placeholder="9.99">
                </div>
                <div class="form-group">
                    <label class="form-label">Compare Price ($)</label>
                    <input type="number" name="compare_price" class="form-control" step="0.01" placeholder="14.99">
                </div>
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Item Image</label>
                <div class="image-upload-box" id="addItemImgBox" onclick="document.getElementById('addItemImgFile').click()" style="border:2px dashed #FF8A00;border-radius:12px;padding:16px;text-align:center;cursor:pointer;background:#fff9f2;min-height:110px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:8px;">
                    <img id="addItemImgPreview" src="" alt="" style="display:none;max-height:90px;border-radius:8px;object-fit:cover;">
                    <span id="addItemImgPlaceholder" style="color:#FF8A00;font-size:13px;">📷 Click to upload image</span>
                </div>
                <input type="file" id="addItemImgFile" name="image_file" accept="image/*" style="display:none" onchange="previewImage(this,'addItemImgPreview','addItemImgPlaceholder')">
                <input type="text" name="image" id="addItemImgUrl" class="form-control" placeholder="Or paste image URL..." style="margin-top:8px;" oninput="previewFromUrl(this.value,'addItemImgPreview','addItemImgPlaceholder')">
            </div>
            {{-- Time availability window --}}
            <div style="background:#f0f4ff;border-radius:10px;padding:14px 16px;margin-bottom:12px;border:1px solid #d0d8f8;">
                <div style="font-size:13px;font-weight:700;color:#140465;margin-bottom:8px;">
                    <i class="fas fa-clock"></i> Time Availability Window
                    <small style="font-weight:400;color:#64748b;margin-left:6px;">Optional — leave empty for all-day availability</small>
                </div>
                <div class="grid-2">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Available From</label>
                        <input type="time" name="available_from" class="form-control" placeholder="08:00">
                        <small class="text-muted">e.g. 08:00 for breakfast start</small>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Available Until</label>
                        <input type="time" name="available_until" class="form-control" placeholder="11:00">
                        <small class="text-muted">e.g. 11:00 for breakfast end</small>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:20px;margin-bottom:16px;">
                <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_featured" value="1"> Featured</label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Add Food Item</button>
        </form>
    </div>
</div>

{{-- ─── EDIT FOOD ITEM ──────────────────────────────────────────────── --}}
<div class="modal-overlay" id="editItemModal">
    <div class="modal-box" style="max-width:600px;max-height:85vh;overflow-y:auto;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Food Item</h3>
            <button class="modal-close" onclick="closeModal('editItemModal')">✕</button>
        </div>
        <form id="editItemForm" method="POST" enctype="multipart/form-data">
            @csrf @method('PATCH')
            <div class="form-group">
                <label class="form-label">Restaurant *</label>
                <select name="vendor_id" id="ei_vendor" class="form-control" required>
                    <option value="">Select restaurant</option>
                    @foreach($allRestaurants as $ar)
                        <option value="{{ $ar->id }}">{{ $ar->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Category</label>
                <select name="category_id" id="ei_cat" class="form-control">
                    <option value="">No category</option>
                    @if($globalCats->isNotEmpty())
                    <optgroup label="— Global Categories —">
                        @foreach($globalCats as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </optgroup>
                    @endif
                    @foreach($vendorCats as $vid => $cats)
                    <optgroup label="{{ $cats->first()->vendor?->name ?? 'Restaurant #'.$vid }}">
                        @foreach($cats as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Item Name *</label>
                <input type="text" name="name" id="ei_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="ei_desc" class="form-control" rows="2"></textarea>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Price ($) *</label>
                    <input type="number" name="price" id="ei_price" class="form-control" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Compare Price ($)</label>
                    <input type="number" name="compare_price" id="ei_compare" class="form-control" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" id="ei_sort" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Item Image</label>
                <div class="image-upload-box" onclick="document.getElementById('editItemImgFile').click()" style="border:2px dashed #FF8A00;border-radius:12px;padding:16px;text-align:center;cursor:pointer;background:#fff9f2;min-height:110px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:8px;">
                    <img id="editItemImgPreview" src="" alt="" style="max-height:90px;border-radius:8px;object-fit:cover;">
                    <span id="editItemImgPlaceholder" style="color:#FF8A00;font-size:13px;display:none;">📷 Click to change image</span>
                </div>
                <input type="file" id="editItemImgFile" name="image_file" accept="image/*" style="display:none" onchange="previewImage(this,'editItemImgPreview','editItemImgPlaceholder')">
                <input type="text" name="image" id="ei_image" class="form-control" placeholder="Or paste image URL..." style="margin-top:8px;" oninput="previewFromUrl(this.value,'editItemImgPreview','editItemImgPlaceholder')">
            </div>
            {{-- Time availability window --}}
            <div style="background:#f0f4ff;border-radius:10px;padding:14px 16px;margin-bottom:12px;border:1px solid #d0d8f8;">
                <div style="font-size:13px;font-weight:700;color:#140465;margin-bottom:8px;">
                    <i class="fas fa-clock"></i> Time Availability Window
                    <small style="font-weight:400;color:#64748b;margin-left:6px;">Leave empty for all-day</small>
                </div>
                <div class="grid-2">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Available From</label>
                        <input type="time" name="available_from" id="ei_avail_from" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Available Until</label>
                        <input type="time" name="available_until" id="ei_avail_until" class="form-control">
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:20px;margin-bottom:16px;">
                <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" id="ei_active" value="1"> Active</label>
                <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_featured" id="ei_featured" value="1"> Featured</label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Save Changes</button>
        </form>
    </div>
</div>

{{-- ─── ADD ADDON ───────────────────────────────────────────────────── --}}
<div class="modal-overlay" id="addAddonModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Add Addon</h3>
            <button class="modal-close" onclick="closeModal('addAddonModal')">✕</button>
        </div>
        <form action="{{ route('admin.module-data.efood.addon.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label class="form-label">Restaurant *</label>
                <select name="vendor_id" class="form-control" required>
                    <option value="">Select restaurant</option>
                    @foreach($allRestaurants as $ar)
                        <option value="{{ $ar->id }}">{{ $ar->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Addon Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Extra Cheese">
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Price ($) *</label>
                    <input type="number" name="price" class="form-control" step="0.01" required placeholder="1.00">
                </div>
                <div class="form-group" style="padding-top:28px;">
                    <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Add Addon</button>
        </form>
    </div>
</div>

{{-- ─── EDIT ADDON ──────────────────────────────────────────────────── --}}
<div class="modal-overlay" id="editAddonModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Edit Addon</h3>
            <button class="modal-close" onclick="closeModal('editAddonModal')">✕</button>
        </div>
        <form id="editAddonForm" method="POST">
            @csrf @method('PATCH')
            <div class="form-group">
                <label class="form-label">Restaurant *</label>
                <select name="vendor_id" id="ea_vendor" class="form-control" required>
                    @foreach($allRestaurants as $ar)
                        <option value="{{ $ar->id }}">{{ $ar->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Addon Name *</label>
                <input type="text" name="name" id="ea_name" class="form-control" required>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Price ($) *</label>
                    <input type="number" name="price" id="ea_price" class="form-control" step="0.01" required>
                </div>
                <div class="form-group" style="padding-top:28px;">
                    <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" id="ea_active" value="1"> Active</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Save Changes</button>
        </form>
    </div>
</div>

{{-- ─── ADD BANNER ──────────────────────────────────────────────────── --}}
<div class="modal-overlay" id="addBannerModal">
    <div class="modal-box" style="max-width:580px;">
        <div class="modal-header">
            <h3 class="modal-title">Add Promo Banner</h3>
            <button class="modal-close" onclick="closeModal('addBannerModal')">✕</button>
        </div>
        <form action="{{ route('admin.module-data.efood.banner.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="form-label">Banner Title *</label>
                <input type="text" name="title" class="form-control" required placeholder="e.g. Super Delicious FOOD">
            </div>
            <div class="form-group">
                <label class="form-label">Subtitle / Offer Text</label>
                <input type="text" name="subtitle" class="form-control" placeholder="e.g. Get up to 40% off">
            </div>
            <div class="form-group">
                <label class="form-label">Banner Image *</label>
                <div onclick="document.getElementById('addBanner_imgFile').click()" style="border:2px dashed #07003B;border-radius:10px;padding:10px;text-align:center;cursor:pointer;background:#f7f7ff;min-height:90px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:6px;">
                    <img id="addBanner_imgPreview" src="" style="display:none;max-height:80px;border-radius:8px;object-fit:cover;width:100%;">
                    <span id="addBanner_imgPlaceholder" style="color:#07003B;font-size:12px;">🖼️ Upload Banner Image</span>
                </div>
                <input type="file" id="addBanner_imgFile" name="image_file" accept="image/*" style="display:none" onchange="previewImage(this,'addBanner_imgPreview','addBanner_imgPlaceholder')">
                <input type="text" name="image" class="form-control" placeholder="Or paste image URL..." style="margin-top:6px;font-size:12px;" oninput="previewFromUrl(this.value,'addBanner_imgPreview','addBanner_imgPlaceholder')">
            </div>
            <div class="form-group">
                <label class="form-label">Action URL (optional)</label>
                <input type="text" name="action_url" class="form-control" placeholder="https://...">
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
                <div class="form-group" style="padding-top:28px;">
                    <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                </div>
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="starts_at" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" name="ends_at" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Add Banner</button>
        </form>
    </div>
</div>

{{-- ─── EDIT BANNER ─────────────────────────────────────────────────── --}}
<div class="modal-overlay" id="editBannerModal">
    <div class="modal-box" style="max-width:580px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Banner</h3>
            <button class="modal-close" onclick="closeModal('editBannerModal')">✕</button>
        </div>
        <form id="editBannerForm" method="POST" enctype="multipart/form-data">
            @csrf @method('PATCH')
            <div class="form-group">
                <label class="form-label">Banner Title *</label>
                <input type="text" name="title" id="eb_title" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Subtitle</label>
                <input type="text" name="subtitle" id="eb_subtitle" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Banner Image</label>
                <div onclick="document.getElementById('editBanner_imgFile').click()" style="border:2px dashed #07003B;border-radius:10px;padding:10px;text-align:center;cursor:pointer;background:#f7f7ff;min-height:90px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:6px;">
                    <img id="editBanner_imgPreview" src="" style="display:none;max-height:80px;border-radius:8px;object-fit:cover;width:100%;">
                    <span id="editBanner_imgPlaceholder" style="color:#07003B;font-size:12px;">🖼️ Click to change image</span>
                </div>
                <input type="file" id="editBanner_imgFile" name="image_file" accept="image/*" style="display:none" onchange="previewImage(this,'editBanner_imgPreview','editBanner_imgPlaceholder')">
                <input type="text" name="image" id="eb_image" class="form-control" placeholder="Or paste image URL..." style="margin-top:6px;font-size:12px;" oninput="previewFromUrl(this.value,'editBanner_imgPreview','editBanner_imgPlaceholder')">
            </div>
            <div class="form-group">
                <label class="form-label">Action URL</label>
                <input type="text" name="action_url" id="eb_action" class="form-control">
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" id="eb_sort" class="form-control">
                </div>
                <div class="form-group" style="padding-top:28px;">
                    <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" id="eb_active" value="1"> Active</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Save Changes</button>
        </form>
    </div>
</div>

{{-- ─── ADD COUPON ──────────────────────────────────────────────────── --}}
<div class="modal-overlay" id="addCouponModal">
    <div class="modal-box" style="max-width:580px;max-height:85vh;overflow-y:auto;">
        <div class="modal-header">
            <h3 class="modal-title">Create Offer / Coupon</h3>
            <button class="modal-close" onclick="closeModal('addCouponModal')">✕</button>
        </div>
        <form action="{{ route('admin.module-data.efood.coupon.store') }}" method="POST">
            @csrf
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Coupon Code *</label>
                    <input type="text" name="code" class="form-control" required placeholder="e.g. FOOD20" style="text-transform:uppercase;">
                </div>
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. 20% off your order">
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Type *</label>
                    <select name="type" class="form-control" required>
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount ($)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Value *</label>
                    <input type="number" name="value" class="form-control" step="0.01" required placeholder="20">
                </div>
                <div class="form-group">
                    <label class="form-label">Min Order Amount ($)</label>
                    <input type="number" name="min_order_amount" class="form-control" step="0.01" placeholder="15.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Max Discount ($)</label>
                    <input type="number" name="max_discount" class="form-control" step="0.01" placeholder="10.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Usage Limit</label>
                    <input type="number" name="usage_limit" class="form-control" placeholder="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Restaurant (optional)</label>
                    <select name="vendor_id" class="form-control">
                        <option value="">All Restaurants</option>
                        @foreach($allRestaurants as $ar)
                            <option value="{{ $ar->id }}">{{ $ar->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="starts_at" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" name="ends_at" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Optional description..."></textarea>
            </div>
            <label style="display:flex;align-items:center;gap:6px;margin-bottom:16px;">
                <input type="checkbox" name="is_active" value="1" checked> Active
            </label>
            <button type="submit" class="btn btn-primary" style="width:100%;">Create Coupon</button>
        </form>
    </div>
</div>

{{-- ─── EDIT COUPON ─────────────────────────────────────────────────── --}}
<div class="modal-overlay" id="editCouponModal">
    <div class="modal-box" style="max-width:580px;max-height:85vh;overflow-y:auto;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Coupon</h3>
            <button class="modal-close" onclick="closeModal('editCouponModal')">✕</button>
        </div>
        <form id="editCouponForm" method="POST">
            @csrf @method('PATCH')
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" id="ecp_title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Type *</label>
                    <select name="type" id="ecp_type" class="form-control" required>
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount ($)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Value *</label>
                    <input type="number" name="value" id="ecp_value" class="form-control" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Min Order ($)</label>
                    <input type="number" name="min_order_amount" id="ecp_min" class="form-control" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label">Max Discount ($)</label>
                    <input type="number" name="max_discount" id="ecp_max" class="form-control" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label">Usage Limit</label>
                    <input type="number" name="usage_limit" id="ecp_limit" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Restaurant</label>
                    <select name="vendor_id" id="ecp_vendor" class="form-control">
                        <option value="">All Restaurants</option>
                        @foreach($allRestaurants as $ar)
                            <option value="{{ $ar->id }}">{{ $ar->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" name="ends_at" id="ecp_ends" class="form-control">
                </div>
            </div>
            <label style="display:flex;align-items:center;gap:6px;margin-bottom:16px;">
                <input type="checkbox" name="is_active" id="ecp_active" value="1"> Active
            </label>
            <button type="submit" class="btn btn-primary" style="width:100%;">Save Coupon</button>
        </form>
    </div>
</div>

{{-- ─── ADD CAMPAIGN ─────────────────────────────────────────────────── --}}
<div class="modal-overlay" id="addCampaignModal">
    <div class="modal-box" style="max-width:660px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header" style="background:linear-gradient(135deg,#FF8A00,#FF6B00);border-radius:12px 12px 0 0;padding:18px 20px;">
            <div>
                <h3 class="modal-title" style="color:#fff;margin:0;"><i class="fas fa-fire"></i> Create New Campaign</h3>
                <p style="color:rgba(255,255,255,0.8);font-size:12px;margin:2px 0 0;">Time-limited discount shown in the customer app</p>
            </div>
            <button class="modal-close" onclick="closeModal('addCampaignModal')" style="color:#fff;">✕</button>
        </div>
        <form action="{{ route('admin.module-data.efood.campaign.store') }}" method="POST">
            @csrf
            <div style="padding:20px;">

                {{-- ── Restaurants multi-select ─────────────────────────────── --}}
                <div class="form-group">
                    <label class="form-label" style="display:flex;align-items:center;justify-content:space-between;">
                        <span>Restaurants * <span id="cmp_sel_count" style="font-weight:400;color:#888;">(0 selected)</span></span>
                        <span style="display:flex;gap:8px;">
                            <button type="button" onclick="cmpSelectAll()" style="font-size:11px;padding:2px 8px;background:#FF8A00;color:#fff;border:none;border-radius:4px;cursor:pointer;">All</button>
                            <button type="button" onclick="cmpClearAll()" style="font-size:11px;padding:2px 8px;background:#6c757d;color:#fff;border:none;border-radius:4px;cursor:pointer;">Clear</button>
                        </span>
                    </label>
                    {{-- Search box --}}
                    <input type="text" id="cmp_rest_search" placeholder="🔍 Search restaurants..." oninput="cmpFilterRest()"
                        style="width:100%;padding:7px 10px;border:1px solid #dee2e6;border-radius:6px;margin-bottom:6px;font-size:13px;box-sizing:border-box;">
                    {{-- Checkbox list --}}
                    {{-- Restaurant rows — each has a checkbox + optional per-vendor discount input --}}
                    <div id="cmp_rest_list" style="max-height:200px;overflow-y:auto;border:1px solid #dee2e6;border-radius:8px;padding:6px 4px;">
                        @foreach($allRestaurants as $ar)
                        <div id="cmp_r_{{ $ar->id }}" style="display:flex;align-items:center;gap:8px;padding:5px 10px;border-radius:6px;transition:background .15s;"
                             onmouseover="this.style.background='#fff3e0'" onmouseout="this.style.background=''">
                            {{-- Checkbox --}}
                            <input type="checkbox" name="vendor_ids[]" value="{{ $ar->id }}"
                                   id="cmp_cb_{{ $ar->id }}"
                                   onchange="cmpUpdateCount(); cmpSyncPerVendorRow({{ $ar->id }})"
                                   style="width:16px;height:16px;accent-color:#FF8A00;cursor:pointer;flex-shrink:0;">
                            {{-- Name --}}
                            <label for="cmp_cb_{{ $ar->id }}" style="flex:1;font-size:13px;font-weight:500;cursor:pointer;margin:0;">{{ $ar->name }}</label>
                            {{-- Per-vendor discount input (hidden by default, shown in "per restaurant" mode) --}}
                            <div id="cmp_pv_{{ $ar->id }}" style="display:none;align-items:center;gap:4px;">
                                <input type="number" name="vendor_discount[{{ $ar->id }}]"
                                       id="cmp_pvv_{{ $ar->id }}"
                                       step="0.01" min="0" placeholder="%" disabled
                                       style="width:80px;padding:4px 8px;border:1px solid #FF8A00;border-radius:6px;font-size:12px;text-align:center;">
                                <span id="cmp_pv_unit_{{ $ar->id }}" style="font-size:11px;color:#888;">%</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Campaign Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g., Summer Sale">
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Brief description"></textarea>
                </div>

                {{-- ── Discount section ──────────────────────────────────────── --}}
                <div style="background:#fff8f0;border:1px solid #ffe0b2;border-radius:10px;padding:14px 16px;margin-bottom:14px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                        <span style="font-size:13px;font-weight:700;color:#333;">💰 Discount Settings</span>
                        {{-- Per-restaurant toggle --}}
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;color:#555;">
                            <input type="checkbox" id="cmp_per_vendor_toggle" onchange="cmpTogglePerVendor(this.checked)"
                                   style="width:14px;height:14px;accent-color:#FF8A00;">
                            Different discount per restaurant
                        </label>
                    </div>
                    <div class="grid-2">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" style="font-size:12px;">Discount Type *</label>
                            <select name="discount_type" id="cmp_dtype" class="form-control" required onchange="cmpUpdateUnits()">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount ($)</option>
                            </select>
                        </div>
                        <div class="form-group" id="cmp_global_val_wrap" style="margin-bottom:0;">
                            <label class="form-label" style="font-size:12px;">
                                Discount Value * <span id="cmp_global_unit" style="color:#FF8A00;font-weight:700;">%</span>
                                <span style="font-size:10px;color:#888;" id="cmp_global_hint"> (applied to all selected)</span>
                            </label>
                            <input type="number" name="discount_value" id="cmp_discount_value"
                                   class="form-control" step="0.01" min="0" value="0">
                        </div>
                    </div>
                    {{-- Per-vendor summary (shown when toggle on) --}}
                    <div id="cmp_per_vendor_hint" style="display:none;margin-top:8px;padding:8px 10px;background:#fff3e0;border-radius:6px;font-size:11px;color:#e65100;">
                        ⚡ Enter each restaurant's discount value in the list above. Leave blank to use the global value.
                    </div>
                </div>

                {{-- ── Category section ─────────────────────────────────────── --}}
                <div style="background:#f0f4ff;border:1px solid #c5d2ff;border-radius:10px;padding:14px 16px;margin-bottom:14px;">
                    <div style="font-size:13px;font-weight:700;color:#333;margin-bottom:10px;">🏷️ Apply Discount To</div>
                    <div style="display:flex;gap:16px;margin-bottom:10px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
                            <input type="radio" name="cmp_scope" value="all" checked onchange="cmpToggleScope('all')"
                                   style="accent-color:#FF8A00;">
                            All menu items
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
                            <input type="radio" name="cmp_scope" value="category" onchange="cmpToggleScope('category')"
                                   style="accent-color:#FF8A00;">
                            Specific category only
                        </label>
                    </div>
                    {{-- Category picker (hidden by default) --}}
                    <div id="cmp_cat_picker" style="display:none;">
                        <input type="text" id="cmp_cat_search" placeholder="🔍 Search categories..."
                               oninput="cmpFilterCats()"
                               style="width:100%;padding:6px 10px;border:1px solid #c5d2ff;border-radius:6px;margin-bottom:6px;font-size:12px;box-sizing:border-box;">
                        <div id="cmp_cat_list" style="max-height:150px;overflow-y:auto;border:1px solid #c5d2ff;border-radius:8px;padding:4px;">
                            <label style="display:flex;align-items:center;gap:8px;padding:5px 10px;border-radius:6px;cursor:pointer;">
                                <input type="radio" name="category_id" value="" checked style="accent-color:#FF8A00;">
                                <span style="font-size:13px;color:#888;font-style:italic;">— No category filter (all items) —</span>
                            </label>
                            @foreach($categories as $cat)
                            <label class="cmp-cat-row" data-vendor="{{ $cat->vendor_id ?? '' }}"
                                   style="display:flex;align-items:center;gap:8px;padding:5px 10px;border-radius:6px;cursor:pointer;transition:background .15s;"
                                   onmouseover="this.style.background='#eef1ff'" onmouseout="this.style.background=''">
                                <input type="radio" name="category_id" value="{{ $cat->id }}" style="accent-color:#FF8A00;">
                                <span style="font-size:13px;font-weight:500;">{{ $cat->name }}</span>
                                @if($cat->vendor)
                                <span style="font-size:10px;color:#888;margin-left:auto;">{{ $cat->vendor->name }}</span>
                                @endif
                            </label>
                            @endforeach
                        </div>
                        <p style="font-size:11px;color:#666;margin:6px 0 0;">Only categories from selected restaurants are shown.</p>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="starts_at_date" id="add_starts_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Start Time (GMT+3) *</label>
                        <input type="time" name="starts_at_time" id="add_starts_time" class="form-control" required value="00:00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date *</label>
                        <input type="date" name="ends_at_date" id="add_ends_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Time (GMT+3) *</label>
                        <input type="time" name="ends_at_time" id="add_ends_time" class="form-control" required value="23:59">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Badge Text</label>
                        <input type="text" name="badge_text" class="form-control" value="Special Offer" placeholder="Special Offer">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Badge Color</label>
                        <select name="badge_color" class="form-control">
                            <option value="red">🔴 Red</option>
                            <option value="orange" selected>🟠 Orange</option>
                            <option value="green">🟢 Green</option>
                            <option value="blue">🔵 Blue</option>
                            <option value="purple">🟣 Purple</option>
                            <option value="yellow">🟡 Yellow</option>
                        </select>
                    </div>
                </div>
                <div style="display:flex;gap:20px;margin-bottom:16px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" checked> Campaign is active
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label">Internal Notes</label>
                    <textarea name="internal_notes" class="form-control" rows="2" placeholder="Admin notes only"></textarea>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:8px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addCampaignModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg,#FF8A00,#FF6B00);border:none;">
                        <i class="fas fa-fire"></i> Create Campaign
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ─── EDIT CAMPAIGN ────────────────────────────────────────────────── --}}
<div class="modal-overlay" id="editCampaignModal">
    <div class="modal-box" style="max-width:660px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header" style="background:linear-gradient(135deg,#07003B,#1a0070);border-radius:12px 12px 0 0;padding:18px 20px;">
            <div>
                <h3 class="modal-title" style="color:#fff;margin:0;"><i class="fas fa-edit"></i> Edit Campaign</h3>
            </div>
            <button class="modal-close" onclick="closeModal('editCampaignModal')" style="color:#fff;">✕</button>
        </div>
        <form id="editCampaignForm" method="POST">
            @csrf @method('PATCH')
            <div style="padding:20px;">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Restaurant *</label>
                        <select name="vendor_id" id="ecmp_vendor" class="form-control" required>
                            @foreach($allRestaurants as $ar)
                                <option value="{{ $ar->id }}">{{ $ar->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Campaign Name *</label>
                        <input type="text" name="name" id="ecmp_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="ecmp_desc" class="form-control" rows="2"></textarea>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Discount Type *</label>
                        <select name="discount_type" id="ecmp_type" class="form-control" required>
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed">Fixed Amount ($)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Discount Value *</label>
                        <input type="number" name="discount_value" id="ecmp_value" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="starts_at_date" id="ecmp_starts_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Start Time (GMT+3) *</label>
                        <input type="time" name="starts_at_time" id="ecmp_starts_time" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date *</label>
                        <input type="date" name="ends_at_date" id="ecmp_ends_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Time (GMT+3) *</label>
                        <input type="time" name="ends_at_time" id="ecmp_ends_time" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Badge Text</label>
                        <input type="text" name="badge_text" id="ecmp_badge_text" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Badge Color</label>
                        <select name="badge_color" id="ecmp_badge_color" class="form-control">
                            <option value="red">🔴 Red</option>
                            <option value="orange">🟠 Orange</option>
                            <option value="green">🟢 Green</option>
                            <option value="blue">🔵 Blue</option>
                            <option value="purple">🟣 Purple</option>
                            <option value="yellow">🟡 Yellow</option>
                        </select>
                    </div>
                </div>
                <div style="display:flex;gap:20px;margin-bottom:16px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="apply_to_all" id="ecmp_all" value="1"> Apply to all menu items
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_active" id="ecmp_active" value="1"> Campaign is active
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label">Internal Notes</label>
                    <textarea name="internal_notes" id="ecmp_notes" class="form-control" rows="2"></textarea>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;padding-top:8px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editCampaignModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Campaign</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════
     JAVASCRIPT
     ══════════════════════════════════════════════════════════════════ --}}

{{-- ─── CREATE BRANCH ───────────────────────────────────────────────── --}}
<div class="modal-overlay" id="branchModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Create Branch</h3>
            <button class="modal-close" onclick="closeModal('branchModal')">✕</button>
        </div>
        <p id="branchLabel" style="font-size:13px;color:#666;margin-bottom:14px;"></p>
        <form id="branchForm" method="POST">
            @csrf
            <div class="form-group">
                <label class="form-label">Branch Name *</label>
                <input type="text" name="branch_name" class="form-control" required placeholder="e.g. Qoobeey - Hodan Branch">
            </div>
            <div class="form-group">
                <label class="form-label">District</label>
                <select name="district_id" class="form-control">
                    <option value="">Select district</option>
                    @foreach($districts as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" placeholder="Branch address">
            </div>
            <div class="form-group">
                <label class="form-label">Location <small style="color:#888">(search or click map)</small></label>
                <input type="text" id="branchSearchBox" class="form-control" placeholder="Search location..." style="margin-bottom:8px;">
                <div id="branchMap" style="height:220px;border-radius:12px;border:1.5px solid #e2e8f0;margin-bottom:8px;"></div>
                <input type="hidden" name="latitude" id="branch_lat">
                <input type="hidden" name="longitude" id="branch_lng">
                <div id="branchLatLng" style="font-size:11px;color:#888;"></div>
            </div>
            <div style="background:#e8f5e9;border-radius:10px;padding:12px;margin-bottom:14px;font-size:12px;color:#2e7d32;">
                <i class="fas fa-info-circle" style="margin-right:6px;"></i>
                Menu, categories, addons, schedule, and branding will be copied. Same owner login.
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Create Branch</button>
        </form>
    </div>
</div>

{{-- ─── ASSIGN CATEGORY TO RESTAURANTS ──────────────────────────────── --}}
<div class="modal-overlay" id="assignCategoryModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Assign Category to Restaurants</h3>
            <button class="modal-close" onclick="closeModal('assignCategoryModal')">✕</button>
        </div>
        <p id="assignCatLabel" style="font-size:13px;color:#666;margin-bottom:14px;"></p>
        <form id="assignCatForm" method="POST">
            @csrf
            <div style="max-height:300px;overflow-y:auto;border:1.5px solid #e2e8f0;border-radius:10px;margin-bottom:16px;">
                @foreach($allRestaurants as $ar)
                <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid #f0f2f6;cursor:pointer;">
                    <input type="checkbox" name="vendor_ids[]" value="{{ $ar->id }}" style="width:16px;height:16px;accent-color:#FF8A00;">
                    <span style="font-weight:600;font-size:13px;">{{ $ar->name }}</span>
                </label>
                @endforeach
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Assign Category</button>
        </form>
    </div>
</div>

@push('scripts')
<script>
// ─── Tab Switcher ─────────────────────────────────────────────────
const TABS = ['restaurants','categories','items','addons','banners','coupons','campaigns','orders'];

function showTab(tab) {
    TABS.forEach(t => {
        document.getElementById('section-' + t).style.display = (t === tab) ? 'block' : 'none';
        const btn = document.getElementById('tab-' + t);
        if (t === tab) {
            btn.style.borderBottom = '3px solid var(--primary)';
            btn.style.color = 'var(--primary)';
        } else {
            btn.style.borderBottom = 'none';
            btn.style.color = '#888';
        }
    });
    history.replaceState(null, '', '?tab=' + tab);
}

// Auto-open tab from URL param
(function() {
    const p = new URLSearchParams(window.location.search).get('tab');
    if (p && TABS.includes(p)) showTab(p);
})();

// ─── Create Branch ───────────────────────────────────────────────
var branchMap, branchMarker;
function openBranchModal(parentId, parentName) {
    document.getElementById('branchForm').action = '/admin/module-data/efood/restaurants/' + parentId + '/branch';
    document.getElementById('branchLabel').textContent = 'Create a new branch for "' + parentName + '". Menu and settings will be copied.';
    document.getElementById('branch_lat').value = '';
    document.getElementById('branch_lng').value = '';
    document.getElementById('branchLatLng').textContent = '';
    openModal('branchModal');
    setTimeout(function() {
        var center = { lat: 2.0469, lng: 45.3182 };
        function setBranchLatLng(lat, lng) {
            document.getElementById('branch_lat').value = lat.toFixed(8);
            document.getElementById('branch_lng').value = lng.toFixed(8);
            document.getElementById('branchLatLng').textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
        }
        function placeBranchMarker(pos) {
            if (branchMarker) branchMarker.setPosition(pos);
            else {
                branchMarker = new google.maps.Marker({ position: pos, map: branchMap, draggable: true });
                branchMarker.addListener('dragend', function(ev) { setBranchLatLng(ev.latLng.lat(), ev.latLng.lng()); });
            }
            setBranchLatLng(pos.lat(), pos.lng());
        }
        if (!branchMap) {
            branchMap = new google.maps.Map(document.getElementById('branchMap'), {
                zoom: 13, center: center, mapTypeControl: false, streetViewControl: false,
            });
            branchMap.addListener('click', function(e) { placeBranchMarker(e.latLng); });
            // Search box
            var input = document.getElementById('branchSearchBox');
            var searchBox = new google.maps.places.SearchBox(input);
            branchMap.addListener('bounds_changed', function() { searchBox.setBounds(branchMap.getBounds()); });
            searchBox.addListener('places_changed', function() {
                var places = searchBox.getPlaces();
                if (!places || !places.length) return;
                var place = places[0];
                if (!place.geometry || !place.geometry.location) return;
                branchMap.setCenter(place.geometry.location);
                branchMap.setZoom(16);
                placeBranchMarker(place.geometry.location);
                if (place.formatted_address) document.querySelector('[name="address"]').value = place.formatted_address;
            });
        } else {
            google.maps.event.trigger(branchMap, 'resize');
            branchMap.setCenter(center);
            if (branchMarker) { branchMarker.setMap(null); branchMarker = null; }
        }
        document.getElementById('branchSearchBox').value = '';
    }, 300);
}

// ─── Assign Category ─────────────────────────────────────────────
function openAssignCategory(catId, catName) {
    document.getElementById('assignCatForm').action = '/admin/module-data/efood/categories/' + catId + '/assign';
    document.getElementById('assignCatLabel').textContent = 'Assign "' + catName + '" to selected restaurants. A copy of this category will be created for each restaurant.';
    openModal('assignCategoryModal');
}

// ─── Toggle Open/Closed ───────────────────────────────────────────
async function toggleOpen(id, btn) {
    const res = await fetch(`/admin/module-data/efood/restaurants/${id}/toggle`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json'},
    });
    const data = await res.json();
    btn.textContent = data.is_open ? 'Open' : 'Closed';
    btn.className = 'btn btn-sm ' + (data.is_open ? 'btn-success' : 'btn-secondary');
}

// ─── Restaurant ───────────────────────────────────────────────────
function openEditRestaurant(r) {
    document.getElementById('editRestaurantForm').action = `/admin/module-data/efood/restaurants/${r.id}`;
    document.getElementById('er_name').value          = r.name || '';
    document.getElementById('er_type').value          = r.vendor_type || '';
    document.getElementById('er_district').value      = r.district_id || '';
    document.getElementById('er_status').value        = r.status || 'active';
    document.getElementById('er_phone').value         = r.phone || '';
    document.getElementById('er_email').value         = r.email || '';
    document.getElementById('er_delivery_time').value = r.delivery_time || '';
    document.getElementById('er_min_order').value     = r.minimum_order || '';
    document.getElementById('er_logo').value          = r.logo || '';
    document.getElementById('er_cover').value         = r.cover_image || '';
    document.getElementById('er_address').value       = r.address || '';
    document.getElementById('er_desc').value          = r.description || '';
    document.getElementById('er_featured').checked    = r.is_featured == 1;
    document.getElementById('er_open').checked        = r.is_open == 1;
    // Show existing images
    if (r.logo) { previewFromUrl(r.logo, 'editR_logoPreview', 'editR_logoPlaceholder'); }
    else { document.getElementById('editR_logoPreview').style.display='none'; document.getElementById('editR_logoPlaceholder').style.display='block'; }
    if (r.cover_image) { previewFromUrl(r.cover_image, 'editR_coverPreview', 'editR_coverPlaceholder'); }
    else { document.getElementById('editR_coverPreview').style.display='none'; document.getElementById('editR_coverPlaceholder').style.display='block'; }

    // Populate working hours
    let wh = [];
    if (r.working_hours) {
        try { wh = typeof r.working_hours === 'string' ? JSON.parse(r.working_hours) : r.working_hours; } catch(e) {}
    }
    for (let d = 0; d <= 6; d++) {
        const entry = wh.find(x => x.day === d);
        const cbx = document.getElementById('er_wh_' + d);
        const openInp  = document.getElementById('er_wh_open_'  + d);
        const closeInp = document.getElementById('er_wh_close_' + d);
        if (cbx)       cbx.checked     = entry ? !entry.is_closed : (d >= 1 && d <= 5);
        if (openInp)   openInp.value   = entry?.open  || '08:00';
        if (closeInp)  closeInp.value  = entry?.close || '22:00';
    }

    // Map: populate lat/lng hidden fields and recenter
    const lat = parseFloat(r.latitude);
    const lng = parseFloat(r.longitude);
    document.getElementById('er_lat').value = r.latitude || '';
    document.getElementById('er_lng').value = r.longitude || '';
    // Owner credentials
    document.getElementById('er_owner_phone').value = r.user?.phone || '';
    document.getElementById('er_owner_email').value = r.user?.email || '';
    document.getElementById('er_owner_pass').value  = '';
    openModal('editRestaurantModal');

    // Init or recenter edit map after modal is visible
    setTimeout(() => {
        const center = (!isNaN(lat) && !isNaN(lng)) ? {lat, lng} : {lat:2.0469, lng:45.3182};
        if (!adminEditMapObj) {
            initAdminEditMap(center);
        } else {
            adminEditMapObj.setCenter(center);
            adminEditMapObj.setZoom(!isNaN(lat) ? 16 : 13);
        }
        if (r.address) {
            document.getElementById('editR_mapSearch').value = r.address;
            setAdminLocation('edit', lat||2.0469, lng||45.3182, r.address);
        }
    }, 150);
}

// ─── Category ─────────────────────────────────────────────────────
function openEditCategory(c) {
    document.getElementById('editCategoryForm').action = `/admin/module-data/efood/categories/${c.id}`;
    document.getElementById('ec_name').value   = c.name || '';
    document.getElementById('ec_image').value  = c.image || '';
    document.getElementById('ec_sort').value   = c.sort_order || 0;
    document.getElementById('ec_active').checked = c.is_active == 1;
    if (c.image) previewFromUrl(c.image, 'editCat_imgPreview', 'editCat_imgPlaceholder');
    else { document.getElementById('editCat_imgPreview').style.display='none'; document.getElementById('editCat_imgPlaceholder').style.display='block'; }
    openModal('editCategoryModal');
}

// ─── Food Item ────────────────────────────────────────────────────
function openEditItem(p) {
    document.getElementById('editItemForm').action     = `/admin/module-data/efood/items/${p.id}`;
    document.getElementById('ei_vendor').value         = p.vendor_id || '';
    document.getElementById('ei_cat').value            = p.category_id || '';
    document.getElementById('ei_name').value           = p.name || '';
    document.getElementById('ei_desc').value           = p.description || '';
    document.getElementById('ei_price').value          = p.price || '';
    document.getElementById('ei_compare').value        = p.compare_price || '';
    document.getElementById('ei_sort').value           = p.sort_order || 0;
    document.getElementById('ei_image').value          = p.image || '';
    document.getElementById('ei_active').checked       = p.is_active == 1;
    document.getElementById('ei_featured').checked     = p.is_featured == 1;
    document.getElementById('ei_avail_from').value     = p.available_from  ? p.available_from.substring(0,5)  : '';
    document.getElementById('ei_avail_until').value    = p.available_until ? p.available_until.substring(0,5) : '';
    // Show existing image preview
    const url = p.thumbnail || p.image || '';
    const prev = document.getElementById('editItemImgPreview');
    const ph   = document.getElementById('editItemImgPlaceholder');
    if (url) { prev.src = url; prev.style.display = 'block'; ph.style.display = 'none'; }
    else      { prev.src = ''; prev.style.display = 'none';  ph.style.display = 'block'; }
    openModal('editItemModal');
}

// ─── Addon ────────────────────────────────────────────────────────
function openEditAddon(a) {
    document.getElementById('editAddonForm').action = `/admin/module-data/efood/addons/${a.id}`;
    document.getElementById('ea_vendor').value      = a.vendor_id || '';
    document.getElementById('ea_name').value        = a.name || '';
    document.getElementById('ea_price').value       = a.price || '';
    document.getElementById('ea_active').checked    = a.is_active == 1;
    openModal('editAddonModal');
}

// ─── Banner ───────────────────────────────────────────────────────
function openEditBanner(b) {
    document.getElementById('editBannerForm').action = `/admin/module-data/efood/banners/${b.id}`;
    document.getElementById('eb_title').value    = b.title || '';
    document.getElementById('eb_subtitle').value = b.subtitle || '';
    document.getElementById('eb_image').value    = b.image || '';
    document.getElementById('eb_action').value   = b.action_url || '';
    document.getElementById('eb_sort').value     = b.sort_order || 0;
    document.getElementById('eb_active').checked = b.is_active == 1;
    if (b.image) previewFromUrl(b.image, 'editBanner_imgPreview', 'editBanner_imgPlaceholder');
    else { document.getElementById('editBanner_imgPreview').style.display='none'; document.getElementById('editBanner_imgPlaceholder').style.display='block'; }
    openModal('editBannerModal');
}

// ─── Coupon ───────────────────────────────────────────────────────
function openEditCoupon(c) {
    document.getElementById('editCouponForm').action = `/admin/module-data/efood/coupons/${c.id}`;
    document.getElementById('ecp_title').value  = c.title || '';
    document.getElementById('ecp_type').value   = c.type || 'percentage';
    document.getElementById('ecp_value').value  = c.value || '';
    document.getElementById('ecp_min').value    = c.min_order_amount || '';
    document.getElementById('ecp_max').value    = c.max_discount || '';
    document.getElementById('ecp_limit').value  = c.usage_limit || '';
    document.getElementById('ecp_vendor').value = c.vendor_id || '';
    document.getElementById('ecp_ends').value   = c.ends_at ? c.ends_at.substring(0,10) : '';
    document.getElementById('ecp_active').checked = c.is_active == 1;
    openModal('editCouponModal');
}

// ─── Discount Campaign ───────────────────────────────────────────────────
function openEditCampaign(c) {
    document.getElementById('editCampaignForm').action = `/admin/module-data/efood/campaigns/${c.id}`;
    document.getElementById('ecmp_vendor').value    = c.vendor_id || '';
    document.getElementById('ecmp_name').value      = c.name || '';
    document.getElementById('ecmp_desc').value      = c.description || '';
    document.getElementById('ecmp_type').value      = c.discount_type || 'percentage';
    document.getElementById('ecmp_value').value     = c.discount_value || '';
    // starts_at: split into date + time
    if (c.starts_at) {
        const sd = c.starts_at.substring(0, 10);
        const st = c.starts_at.length > 10 ? c.starts_at.substring(11, 16) : '00:00';
        document.getElementById('ecmp_starts_date').value = sd;
        document.getElementById('ecmp_starts_time').value = st;
    }
    if (c.ends_at) {
        const ed = c.ends_at.substring(0, 10);
        const et = c.ends_at.length > 10 ? c.ends_at.substring(11, 16) : '23:59';
        document.getElementById('ecmp_ends_date').value = ed;
        document.getElementById('ecmp_ends_time').value = et;
    }
    document.getElementById('ecmp_badge_text').value   = c.badge_text || 'Special Offer';
    document.getElementById('ecmp_badge_color').value  = c.badge_color || 'orange';
    document.getElementById('ecmp_all').checked        = c.apply_to_all == 1;
    document.getElementById('ecmp_active').checked     = c.is_active == 1;
    document.getElementById('ecmp_notes').value        = c.internal_notes || '';
    openModal('editCampaignModal');
}

// ─── Image upload helpers ─────────────────────────────────────────────────
function previewImage(input, previewId, placeholderId) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const prev = document.getElementById(previewId);
        const ph   = document.getElementById(placeholderId);
        prev.src = e.target.result;
        prev.style.display = 'block';
        if (ph) ph.style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function previewFromUrl(url, previewId, placeholderId) {
    const prev = document.getElementById(previewId);
    const ph   = document.getElementById(placeholderId);
    if (url && url.startsWith('http')) {
        prev.src = url;
        prev.style.display = 'block';
        if (ph) ph.style.display = 'none';
    } else {
        prev.style.display = 'none';
        if (ph) ph.style.display = 'block';
    }
}

// ─── Admin Google Maps ────────────────────────────────────────────
let adminAddMapObj = null, adminAddGeocoder = null, adminAddSearchBox = null;
let adminEditMapObj = null, adminEditGeocoder = null, adminEditSearchBox = null;

function initAdminMaps() {
    adminAddGeocoder  = new google.maps.Geocoder();
    adminEditGeocoder = new google.maps.Geocoder();
}

function initAdminAddMap() {
    if (adminAddMapObj) { google.maps.event.trigger(adminAddMapObj,'resize'); return; }
    const center = {lat:2.0469, lng:45.3182};
    adminAddMapObj = new google.maps.Map(document.getElementById('adminAddMap'), {
        center, zoom:13, mapTypeControl:false, streetViewControl:false, fullscreenControl:false,
    });
    adminAddSearchBox = new google.maps.places.SearchBox(document.getElementById('addR_mapSearch'));
    adminAddMapObj.addListener('bounds_changed', () => adminAddSearchBox.setBounds(adminAddMapObj.getBounds()));
    adminAddSearchBox.addListener('places_changed', () => {
        const p = adminAddSearchBox.getPlaces();
        if (!p || !p.length) return;
        const loc = p[0].geometry.location;
        adminAddMapObj.setCenter(loc); adminAddMapObj.setZoom(16);
        setAdminLocation('add', loc.lat(), loc.lng(), p[0].formatted_address || '');
    });
    adminAddMapObj.addListener('dragend', () => {
        const c = adminAddMapObj.getCenter();
        adminReverseGeocode('add', c.lat(), c.lng());
    });
    adminAddMapObj.addListener('click', e => {
        adminAddMapObj.setCenter(e.latLng);
        adminReverseGeocode('add', e.latLng.lat(), e.latLng.lng());
    });
}

function initAdminEditMap(center) {
    adminEditMapObj = new google.maps.Map(document.getElementById('adminEditMap'), {
        center, zoom: center.lat===2.0469 ? 13 : 16,
        mapTypeControl:false, streetViewControl:false, fullscreenControl:false,
    });
    adminEditSearchBox = new google.maps.places.SearchBox(document.getElementById('editR_mapSearch'));
    adminEditMapObj.addListener('bounds_changed', () => adminEditSearchBox.setBounds(adminEditMapObj.getBounds()));
    adminEditSearchBox.addListener('places_changed', () => {
        const p = adminEditSearchBox.getPlaces();
        if (!p || !p.length) return;
        const loc = p[0].geometry.location;
        adminEditMapObj.setCenter(loc); adminEditMapObj.setZoom(16);
        setAdminLocation('edit', loc.lat(), loc.lng(), p[0].formatted_address || '');
    });
    adminEditMapObj.addListener('dragend', () => {
        const c = adminEditMapObj.getCenter();
        adminReverseGeocode('edit', c.lat(), c.lng());
    });
    adminEditMapObj.addListener('click', e => {
        adminEditMapObj.setCenter(e.latLng);
        adminReverseGeocode('edit', e.latLng.lat(), e.latLng.lng());
    });
}

function adminReverseGeocode(mode, lat, lng) {
    const gc = mode === 'add' ? adminAddGeocoder : adminEditGeocoder;
    if (!gc) return;
    gc.geocode({location:{lat,lng}}, (results, status) => {
        const addr = (status === 'OK' && results[0]) ? results[0].formatted_address : `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
        setAdminLocation(mode, lat, lng, addr);
    });
}

function setAdminLocation(mode, lat, lng, address) {
    if (mode === 'add') {
        document.getElementById('addR_lat').value     = lat;
        document.getElementById('addR_lng').value     = lng;
        document.getElementById('addR_address').value = address;
        document.getElementById('addR_mapSearch').value = address;
        const box = document.getElementById('addR_addrBox');
        box.style.display = 'flex';
        document.getElementById('addR_addrText').textContent = address;
    } else {
        document.getElementById('er_lat').value     = lat;
        document.getElementById('er_lng').value     = lng;
        document.getElementById('er_address').value = address;
        document.getElementById('editR_mapSearch').value = address;
        const box = document.getElementById('editR_addrBox');
        box.style.display = 'flex';
        document.getElementById('editR_addrText').textContent = address;
    }
}

function adminAddMyLocation() {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(pos => {
        const lat = pos.coords.latitude, lng = pos.coords.longitude;
        if (adminAddMapObj) { adminAddMapObj.setCenter({lat,lng}); adminAddMapObj.setZoom(17); }
        adminReverseGeocode('add', lat, lng);
    }, null, {enableHighAccuracy:true, timeout:8000});
}

function adminEditMyLocation() {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(pos => {
        const lat = pos.coords.latitude, lng = pos.coords.longitude;
        if (adminEditMapObj) { adminEditMapObj.setCenter({lat,lng}); adminEditMapObj.setZoom(17); }
        adminReverseGeocode('edit', lat, lng);
    }, null, {enableHighAccuracy:true, timeout:8000});
}

// Init Add map when Add modal opens
const _origOpenModal = window.openModal;
window.openModal = function(id) {
    _origOpenModal && _origOpenModal(id);
    if (id === 'addRestaurantModal') {
        setTimeout(() => {
            if (!adminAddMapObj) initAdminAddMap();
            else google.maps.event.trigger(adminAddMapObj,'resize');
        }, 150);
    }
};

// Reset add item image picker when modal closes
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', () => {
            // Reset add item image
            const prev = document.getElementById('addItemImgPreview');
            const ph   = document.getElementById('addItemImgPlaceholder');
            if (prev) { prev.src = ''; prev.style.display = 'none'; }
            if (ph)   ph.style.display = 'block';
            const fileInput = document.getElementById('addItemImgFile');
            if (fileInput) fileInput.value = '';
        });
    });
});

// ─── Category Assignment ─────────────────────────────────────────────────────
function catToggleAssign(on) {
    document.getElementById('cat_assign_panel').style.display = on ? 'block' : 'none';
    if (!on) catClearAll();
}
function catUpdateCount() {
    const n = document.querySelectorAll('#cat_rest_list input:checked').length;
    document.getElementById('cat_assign_count').textContent = n + ' selected';
}
function catSelectAll() {
    document.querySelectorAll('#cat_rest_list input').forEach(cb => cb.checked = true);
    catUpdateCount();
}
function catClearAll() {
    document.querySelectorAll('#cat_rest_list input').forEach(cb => cb.checked = false);
    catUpdateCount();
}
function catFilterRest(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#cat_rest_list label').forEach(l => {
        l.style.display = l.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ─── Campaign Multi-Restaurant + Per-Vendor Discount + Category ──────────────

// Track per-vendor mode
let _cmpPerVendor = false;

function cmpUpdateCount() {
    const checked = document.querySelectorAll('#cmp_rest_list input[type=checkbox]:checked').length;
    document.getElementById('cmp_sel_count').textContent = '(' + checked + ' selected)';
    // Re-filter categories whenever restaurant selection changes
    cmpFilterCatsBySelected();
}

function cmpSyncPerVendorRow(vendorId) {
    // Show/hide per-vendor discount input based on checkbox + per-vendor mode
    const cb  = document.getElementById('cmp_cb_' + vendorId);
    const div = document.getElementById('cmp_pv_' + vendorId);
    const inp = document.getElementById('cmp_pvv_' + vendorId);
    if (!div || !cb) return;
    const show = _cmpPerVendor && cb.checked;
    div.style.display = show ? 'flex' : 'none';
    if (inp) inp.disabled = !show;
}

function cmpTogglePerVendor(on) {
    _cmpPerVendor = on;
    // Show/hide hint
    document.getElementById('cmp_per_vendor_hint').style.display = on ? 'block' : 'none';
    document.getElementById('cmp_global_hint').textContent = on ? ' (default fallback)' : ' (applied to all selected)';
    // Sync each vendor row
    document.querySelectorAll('#cmp_rest_list input[type=checkbox]').forEach(cb => {
        cmpSyncPerVendorRow(cb.value);
    });
    // Update unit labels
    cmpUpdateUnits();
}

function cmpUpdateUnits() {
    const type = document.getElementById('cmp_dtype')?.value ?? 'percentage';
    const unit = type === 'percentage' ? '%' : '$';
    const globalUnit = document.getElementById('cmp_global_unit');
    if (globalUnit) globalUnit.textContent = unit;
    // Per-vendor unit labels
    document.querySelectorAll('[id^="cmp_pv_unit_"]').forEach(el => el.textContent = unit);
}

function cmpSelectAll() {
    document.querySelectorAll('#cmp_rest_list input[type=checkbox]').forEach(cb => {
        const row = cb.closest('div');
        if (row && row.style.display !== 'none') {
            cb.checked = true;
            cmpSyncPerVendorRow(cb.value);
        }
    });
    cmpUpdateCount();
}

function cmpClearAll() {
    document.querySelectorAll('#cmp_rest_list input[type=checkbox]').forEach(cb => {
        cb.checked = false;
        cmpSyncPerVendorRow(cb.value);
    });
    cmpUpdateCount();
}

function cmpFilterRest() {
    const q = document.getElementById('cmp_rest_search').value.toLowerCase();
    document.querySelectorAll('#cmp_rest_list > div').forEach(row => {
        const name = row.querySelector('label')?.textContent.toLowerCase() ?? '';
        row.style.display = name.includes(q) ? '' : 'none';
    });
}

// ── Category scope toggle ─────────────────────────────────────────────────────
function cmpToggleScope(val) {
    const picker = document.getElementById('cmp_cat_picker');
    if (picker) picker.style.display = val === 'category' ? 'block' : 'none';
    // When "all" selected, clear category selection
    if (val === 'all') {
        const firstRadio = document.querySelector('#cmp_cat_list input[type=radio][value=""]');
        if (firstRadio) firstRadio.checked = true;
    }
}

function cmpFilterCats() {
    const q = document.getElementById('cmp_cat_search')?.value.toLowerCase() ?? '';
    document.querySelectorAll('.cmp-cat-row').forEach(row => {
        const name = row.querySelector('span')?.textContent.toLowerCase() ?? '';
        const visible = row.style.display !== 'none' || true; // respect vendor filter too
        row.style.display = name.includes(q) ? '' : 'none';
    });
}

// Filter category rows by selected restaurants (show only matching vendor categories + global)
function cmpFilterCatsBySelected() {
    const selectedVendors = new Set(
        [...document.querySelectorAll('#cmp_rest_list input[type=checkbox]:checked')].map(cb => cb.value)
    );
    document.querySelectorAll('.cmp-cat-row').forEach(row => {
        const vid = row.dataset.vendor;
        // Show if: no vendor (global) OR vendor is selected
        row.style.display = (!vid || selectedVendors.has(vid)) ? '' : 'none';
    });
}

// Validate before submit
document.querySelector('form[action*="campaign.store"], form[action*="/campaigns"]')
    ?.addEventListener('submit', function(e) {
        const checked = this.querySelectorAll('input[name="vendor_ids[]"]:checked').length;
        if (checked === 0) {
            e.preventDefault();
            alert('Please select at least one restaurant.');
        }
    });
</script>
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}&libraries=places&callback=initAdminMaps" async defer></script>
@endpush
@endsection
