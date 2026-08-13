<?php $__env->startSection('title', 'eShop Management'); ?>
<?php $__env->startSection('content'); ?>

<style>
.es-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:16px; margin-bottom:24px; }
.es-stat  { background:#fff; border-radius:14px; padding:20px 18px; box-shadow:0 2px 10px rgba(0,0,0,.06); display:flex; flex-direction:column; gap:6px; }
.es-stat .val { font-size:28px; font-weight:800; }
.es-stat .lbl { font-size:12px; color:#888; display:flex; align-items:center; gap:6px; }
.tab-row { display:flex; gap:0; border-bottom:2px solid #eee; margin-bottom:22px; flex-wrap:wrap; overflow-x:auto; }
.tab-btn { padding:10px 16px; border:none; background:none; font-weight:600; cursor:pointer; font-size:13px; color:#888; white-space:nowrap; border-bottom:3px solid transparent; transition:.2s; }
.tab-btn.active { color:var(--brand); border-bottom-color:var(--brand); }
.tab-pane { display:none; }
.tab-pane.active { display:block; }
.es-table { width:100%; border-collapse:collapse; font-size:13px; }
.es-table th { background:#f7f8fc; padding:10px 14px; font-weight:700; text-align:left; color:#555; border-bottom:2px solid #eee; }
.es-table td { padding:10px 14px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
.es-table tr:hover td { background:#fafbff; }
.badge-active   { background:#dcfce7; color:#16a34a; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-inactive { background:#fee2e2; color:#dc2626; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-featured { background:#fef9c3; color:#b45309; padding:3px 8px; border-radius:20px; font-size:10px; font-weight:700; }
.badge-pct  { background:#ede9fe; color:#7c3aed; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-fixed{ background:#dbeafe; color:#1d4ed8; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; }
.btn-xs  { padding:4px 10px; border-radius:7px; font-size:11px; font-weight:600; border:none; cursor:pointer; transition:.15s; }
.btn-edit  { background:#eff6ff; color:#2563eb; }
.btn-del   { background:#fef2f2; color:#dc2626; }
.btn-tog   { background:#f0fdf4; color:#16a34a; }
.btn-warn  { background:#fffbeb; color:#d97706; }
.btn-primary { background:var(--brand); color:#fff; }
.btn-xs:hover { opacity:.85; transform:scale(1.04); }
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; display:none; align-items:center; justify-content:center; }
.modal-backdrop.open { display:flex; }
.modal-box { background:#fff; border-radius:18px; padding:28px; width:90%; max-width:560px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2); }
.modal-box h3 { font-size:17px; font-weight:800; color:var(--navy); margin-bottom:20px; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.form-row.full { grid-template-columns:1fr; }
.form-group { display:flex; flex-direction:column; gap:5px; }
.form-group label { font-size:12px; font-weight:700; color:#555; }
.form-control { border:1.5px solid #e5e7eb; border-radius:8px; padding:9px 12px; font-size:13px; outline:none; width:100%; transition:.2s; }
.form-control:focus { border-color:var(--brand); box-shadow:0 0 0 3px rgba(255,138,0,.12); }
select.form-control { background:#fff; }
.form-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:20px; }
.btn-cancel { background:#f3f4f6; color:#374151; padding:9px 20px; border-radius:9px; border:none; font-weight:600; cursor:pointer; }
.btn-save   { background:var(--brand); color:#fff; padding:9px 24px; border-radius:9px; border:none; font-weight:700; cursor:pointer; }
.btn-save:hover { background:var(--brand-dark); }
.product-thumb { width:46px; height:46px; border-radius:8px; object-fit:cover; background:#f3f4f6; }
.empty-state { text-align:center; padding:48px 20px; color:#aaa; }
.empty-state i { font-size:40px; margin-bottom:12px; }
.section-bar { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
.section-bar h4 { font-size:15px; font-weight:800; color:var(--navy); }
.attr-values { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
.attr-val-chip { background:#f3f4f6; border-radius:20px; padding:3px 10px; font-size:12px; display:flex; align-items:center; gap:5px; }
.color-swatch { width:14px; height:14px; border-radius:50%; border:1px solid #ddd; display:inline-block; }
.countdown { font-family:monospace; font-weight:700; font-size:13px; color:#dc2626; }
.order-status { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.status-pending    { background:#fef9c3; color:#92400e; }
.status-confirmed  { background:#dbeafe; color:#1e40af; }
.status-processing { background:#ede9fe; color:#6d28d9; }
.status-shipped    { background:#cffafe; color:#0e7490; }
.status-delivered  { background:#dcfce7; color:#15803d; }
.status-cancelled  { background:#fee2e2; color:#991b1b; }
.pagination-wrap { display:flex; justify-content:flex-end; padding:16px 0 0; }
.alert-success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; padding:12px 18px; border-radius:10px; margin-bottom:18px; font-weight:600; }
.alert-error   { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; padding:12px 18px; border-radius:10px; margin-bottom:18px; font-weight:600; }
</style>


<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-shopping-bag" style="color:var(--brand)"></i> eShop Management</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li>
            <li class="breadcrumb-item active">eShop</li>
        </ol>
    </div>
</div>

<?php if(session('success')): ?><div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?></div><?php endif; ?>
<?php if(session('error')): ?>  <div class="alert-error">  <i class="fas fa-exclamation-circle"></i> <?php echo e(session('error')); ?></div><?php endif; ?>


<div class="es-stats">
    <div class="es-stat">
        <span class="val" style="color:var(--brand)"><?php echo e(number_format($stats['total_products'])); ?></span>
        <span class="lbl"><i class="fas fa-box"></i> Total Products</span>
    </div>
    <div class="es-stat">
        <span class="val" style="color:#22c55e"><?php echo e(number_format($stats['active_products'])); ?></span>
        <span class="lbl"><i class="fas fa-check-circle"></i> Active Products</span>
    </div>
    <div class="es-stat">
        <span class="val" style="color:#3b82f6"><?php echo e(number_format($stats['total_orders'])); ?></span>
        <span class="lbl"><i class="fas fa-receipt"></i> Total Orders</span>
    </div>
    <div class="es-stat">
        <span class="val" style="color:var(--brand)">$<?php echo e(number_format($stats['today_revenue'],2)); ?></span>
        <span class="lbl"><i class="fas fa-dollar-sign"></i> Today Revenue</span>
    </div>
    <div class="es-stat">
        <span class="val" style="color:#8b5cf6"><?php echo e($stats['active_coupons']); ?></span>
        <span class="lbl"><i class="fas fa-tag"></i> Active Coupons</span>
    </div>
    <div class="es-stat">
        <span class="val" style="color:#ef4444"><?php echo e($stats['flash_deals']); ?></span>
        <span class="lbl"><i class="fas fa-bolt"></i> Live Flash Deals</span>
    </div>
</div>


<div class="card" style="padding:0;overflow:hidden;">
<div class="tab-row" style="padding:0 20px;">
    <?php $__currentLoopData = [
        ['products',   'fa-box',        'Products'],
        ['categories', 'fa-th-large',   'Categories'],
        ['attributes', 'fa-sliders-h',  'Attributes'],
        ['units',      'fa-balance-scale','Units'],
        ['flash',      'fa-bolt',       'Flash Deals'],
        ['deals',      'fa-fire',       'Deals of Day'],
        ['coupons',    'fa-tag',        'Coupons'],
        ['campaigns',  'fa-bullhorn',   'Campaigns'],
        ['orders',     'fa-receipt',    'Orders'],
    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$key,$icon,$lbl]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <button class="tab-btn <?php echo e($key==='products'?'active':''); ?>" id="tab-<?php echo e($key); ?>" onclick="showTab('<?php echo e($key); ?>')">
        <i class="fas <?php echo e($icon); ?>"></i> <?php echo e($lbl); ?>

    </button>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<div style="padding:20px;">


<div class="tab-pane active" id="pane-products">
    <div class="section-bar">
        <h4><i class="fas fa-box" style="color:var(--brand)"></i> Products (<?php echo e($products->total()); ?>)</h4>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <form method="GET" style="display:flex;gap:6px;align-items:center;">
                <input type="hidden" name="tab" value="products">
                <input name="search" value="<?php echo e(request('search')); ?>" placeholder="Search products…" class="form-control" style="width:200px;">
                <select name="category_id" class="form-control" style="width:150px;">
                    <option value="">All Categories</option>
                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($cat->id); ?>" <?php echo e(request('category_id')==$cat->id?'selected':''); ?>><?php echo e($cat->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <select name="status" class="form-control" style="width:120px;">
                    <option value="">All Status</option>
                    <option value="active" <?php echo e(request('status')=='active'?'selected':''); ?>>Active</option>
                    <option value="inactive" <?php echo e(request('status')=='inactive'?'selected':''); ?>>Inactive</option>
                </select>
                <button class="btn-xs btn-primary" type="submit">Filter</button>
            </form>
            <button class="btn-xs btn-primary" onclick="openModal('modal-add-product')" style="padding:8px 14px;">
                <i class="fas fa-plus"></i> Add Product
            </button>
        </div>
    </div>

    <?php if($products->isEmpty()): ?>
    <div class="empty-state"><i class="fas fa-box-open"></i><br>No products yet. Add your first product!</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="es-table">
        <thead><tr>
            <th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Featured</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <?php if($p->thumbnail): ?>
                    <img src="<?php echo e($p->thumbnail); ?>" class="product-thumb" onerror="this.style.display='none'">
                    <?php else: ?>
                    <div class="product-thumb" style="display:flex;align-items:center;justify-content:center;"><i class="fas fa-image" style="color:#ddd;font-size:18px;"></i></div>
                    <?php endif; ?>
                    <div>
                        <div style="font-weight:700;color:var(--navy)"><?php echo e(Str::limit($p->name,40)); ?></div>
                        <?php if($p->sku): ?><div style="font-size:11px;color:#aaa;">SKU: <?php echo e($p->sku); ?></div><?php endif; ?>
                    </div>
                </div>
            </td>
            <td><?php echo e($p->category?->name ?? '—'); ?></td>
            <td>
                <div style="font-weight:800;color:var(--brand)">$<?php echo e(number_format($p->price,2)); ?></div>
                <?php if($p->sale_price): ?><div style="font-size:11px;text-decoration:line-through;color:#aaa">$<?php echo e(number_format($p->sale_price,2)); ?></div><?php endif; ?>
            </td>
            <td>
                <?php if($p->track_inventory): ?>
                <span style="font-weight:700;color:<?php echo e($p->stock_quantity>10?'#16a34a':($p->stock_quantity>0?'#d97706':'#dc2626')); ?>">
                    <?php echo e($p->stock_quantity); ?>

                </span>
                <?php else: ?>
                <span style="color:#aaa">∞</span>
                <?php endif; ?>
            </td>
            <td>
                <span class="<?php echo e($p->is_available?'badge-active':'badge-inactive'); ?>">
                    <?php echo e($p->is_available?'Active':'Inactive'); ?>

                </span>
            </td>
            <td>
                <?php if($p->is_featured): ?><span class="badge-featured">⭐ Featured</span><?php else: ?><span style="color:#ccc;font-size:12px;">—</span><?php endif; ?>
            </td>
            <td>
                <div style="display:flex;gap:5px;flex-wrap:wrap;">
                    <?php
                        $pVariants = \App\Models\ProductVariant::where('product_id',$p->id)->where('is_active',true)->get()->map(fn($v)=>[
                            'id'=>$v->id,'name'=>$v->name,'price'=>$v->price,'stock_quantity'=>$v->stock_quantity,'sku'=>$v->sku,
                            'attributes_raw'=> $v->attributes ? collect(json_decode($v->attributes,true)??[])->map(fn($val,$k)=>"$k:$val")->join(', ') : ''
                        ])->values()->toArray();
                        $pGallery = \Illuminate\Support\Facades\DB::table('product_images')->where('product_id',$p->id)->orderBy('sort_order')->get()->map(fn($img)=>['id'=>$img->id,'url'=>str_starts_with($img->image,'http') ? $img->image : url('/api/img/'.$img->image)])->toArray();
                    ?>
                    <button class="btn-xs btn-edit" onclick="editProduct(<?php echo e($p->id); ?>, <?php echo e(json_encode([
                        'name'=>$p->name,'price'=>$p->price,'sale_price'=>$p->sale_price,
                        'category_id'=>$p->category_id,'description'=>$p->description,
                        'sku'=>$p->sku,'barcode'=>$p->barcode ?? '','brand'=>$p->brand ?? '',
                        'unit'=>$p->unit,'stock_quantity'=>$p->stock_quantity,
                        'min_qty'=>$p->min_qty,'is_available'=>$p->is_available,'is_featured'=>$p->is_featured,
                        'thumbnail'=>$p->thumbnail,'variants'=>$pVariants,'gallery'=>$pGallery
                    ])); ?>)">Edit</button>
                    <form method="POST" action="<?php echo e(route('admin.eshop.product.duplicate', $p->id)); ?>" style="display:inline">
                        <?php echo csrf_field(); ?> <button class="btn-xs" style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;" title="Duplicate"><i class="fas fa-copy"></i></button>
                    </form>
                    <form method="POST" action="<?php echo e(route('admin.eshop.product.toggle', $p->id)); ?>" style="display:inline">
                        <?php echo csrf_field(); ?> <button class="btn-xs btn-tog"><?php echo e($p->is_available?'Deactivate':'Activate'); ?></button>
                    </form>
                    <form method="POST" action="<?php echo e(route('admin.eshop.product.feature', $p->id)); ?>" style="display:inline">
                        <?php echo csrf_field(); ?> <button class="btn-xs btn-warn"><?php echo e($p->is_featured?'Unfeature':'Feature'); ?></button>
                    </form>
                    <form method="POST" action="<?php echo e(route('admin.eshop.product.destroy', $p->id)); ?>" style="display:inline" onsubmit="return confirm('Delete this product?')">
                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?> <button class="btn-xs btn-del">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    </div>
    <div class="pagination-wrap"><?php echo e($products->appends(request()->query())->links()); ?></div>
    <?php endif; ?>
</div>


<div class="tab-pane" id="pane-categories">
    <div class="section-bar">
        <h4><i class="fas fa-th-large" style="color:var(--brand)"></i> Categories (<?php echo e($categories->count()); ?>)</h4>
        <button class="btn-xs btn-primary" onclick="openModal('modal-add-cat')"><i class="fas fa-plus"></i> Add Category</button>
    </div>
    <?php if($categories->isEmpty()): ?>
    <div class="empty-state"><i class="fas fa-th-large"></i><br>No categories yet.</div>
    <?php else: ?>
    <table class="es-table">
        <thead><tr><th>Image</th><th>Name</th><th>Parent</th><th>Sort</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td>
                <?php if($cat->image): ?>
                <img src="<?php echo e($cat->image); ?>" style="width:40px;height:40px;border-radius:8px;object-fit:cover;" onerror="this.style.display='none'">
                <?php else: ?>
                <div style="width:40px;height:40px;border-radius:8px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;"><i class="fas fa-image" style="color:#ddd;"></i></div>
                <?php endif; ?>
            </td>
            <td style="font-weight:700"><?php echo e($cat->name); ?></td>
            <td><?php echo e($cat->parent?->name ?? '—'); ?></td>
            <td><?php echo e($cat->sort_order); ?></td>
            <td><span class="<?php echo e($cat->is_active?'badge-active':'badge-inactive'); ?>"><?php echo e($cat->is_active?'Active':'Inactive'); ?></span></td>
            <td>
                <div style="display:flex;gap:5px;">
                    <button class="btn-xs btn-edit" onclick="editCat(<?php echo e($cat->id); ?>, <?php echo e(json_encode(['name'=>$cat->name,'parent_id'=>$cat->parent_id,'sort_order'=>$cat->sort_order,'is_active'=>$cat->is_active])); ?>)">Edit</button>
                    <form method="POST" action="<?php echo e(route('admin.eshop.category.destroy', $cat->id)); ?>" style="display:inline" onsubmit="return confirm('Delete?')">
                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?> <button class="btn-xs btn-del">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>


<div class="tab-pane" id="pane-attributes">
    <div class="section-bar">
        <h4><i class="fas fa-sliders-h" style="color:var(--brand)"></i> Product Attributes</h4>
        <button class="btn-xs btn-primary" onclick="openModal('modal-add-attr')"><i class="fas fa-plus"></i> Add Attribute</button>
    </div>
    <?php if($attributes->isEmpty()): ?>
    <div class="empty-state"><i class="fas fa-sliders-h"></i><br>No attributes yet.</div>
    <?php else: ?>
    <table class="es-table">
        <thead><tr><th>Name</th><th>Type</th><th>Values</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php $__currentLoopData = $attributes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td style="font-weight:700"><?php echo e($attr->name); ?></td>
            <td><span class="badge-pct"><?php echo e(ucfirst($attr->type)); ?></span></td>
            <td>
                <div class="attr-values">
                    <?php $__currentLoopData = $attr->values; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <span class="attr-val-chip">
                        <?php if($attr->type === 'color' && $val->color_code): ?>
                        <span class="color-swatch" style="background:<?php echo e($val->color_code); ?>"></span>
                        <?php endif; ?>
                        <?php echo e($val->value); ?>

                        <form method="POST" action="<?php echo e(route('admin.eshop.attribute.value.destroy', $val->id)); ?>" style="display:inline" onsubmit="return confirm('Remove value?')">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?> <button style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:11px;padding:0;">✕</button>
                        </form>
                    </span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <button class="btn-xs btn-tog" style="font-size:10px;padding:2px 8px;" onclick="openAddValue(<?php echo e($attr->id); ?>, '<?php echo e($attr->type); ?>', '<?php echo e($attr->name); ?>')">+ Value</button>
                </div>
            </td>
            <td><span class="<?php echo e($attr->is_active?'badge-active':'badge-inactive'); ?>"><?php echo e($attr->is_active?'On':'Off'); ?></span></td>
            <td>
                <div style="display:flex;gap:5px;">
                    <button class="btn-xs btn-edit" onclick="editAttr(<?php echo e($attr->id); ?>, <?php echo e(json_encode(['name'=>$attr->name,'type'=>$attr->type])); ?>)">Edit</button>
                    <form method="POST" action="<?php echo e(route('admin.eshop.attribute.destroy', $attr->id)); ?>" style="display:inline" onsubmit="return confirm('Delete attribute and all its values?')">
                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?> <button class="btn-xs btn-del">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>


<div class="tab-pane" id="pane-units">
    <div class="section-bar">
        <h4><i class="fas fa-balance-scale" style="color:var(--brand)"></i> Product Units</h4>
        <button class="btn-xs btn-primary" onclick="openModal('modal-add-unit')"><i class="fas fa-plus"></i> Add Unit</button>
    </div>
    <?php if($units->isEmpty()): ?>
    <div class="empty-state"><i class="fas fa-balance-scale"></i><br>No units yet. Add units like "kg", "piece", "liter".</div>
    <?php else: ?>
    <table class="es-table" style="max-width:500px;">
        <thead><tr><th>Name</th><th>Symbol</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php $__currentLoopData = $units; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td style="font-weight:700"><?php echo e($u->name); ?></td>
            <td><code style="background:#f3f4f6;padding:2px 8px;border-radius:5px;"><?php echo e($u->symbol); ?></code></td>
            <td><span class="<?php echo e($u->is_active?'badge-active':'badge-inactive'); ?>"><?php echo e($u->is_active?'Active':'Inactive'); ?></span></td>
            <td>
                <div style="display:flex;gap:5px;">
                    <button class="btn-xs btn-edit" onclick="editUnit(<?php echo e($u->id); ?>, <?php echo e(json_encode(['name'=>$u->name,'symbol'=>$u->symbol])); ?>)">Edit</button>
                    <form method="POST" action="<?php echo e(route('admin.eshop.unit.destroy', $u->id)); ?>" style="display:inline" onsubmit="return confirm('Delete unit?')">
                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?> <button class="btn-xs btn-del">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>


<div class="tab-pane" id="pane-flash">
    <div class="section-bar">
        <h4><i class="fas fa-bolt" style="color:#ef4444"></i> Flash Deals</h4>
        <button class="btn-xs btn-primary" onclick="openModal('modal-add-flash')"><i class="fas fa-plus"></i> New Flash Deal</button>
    </div>
    <?php if($flashDeals->isEmpty()): ?>
    <div class="empty-state"><i class="fas fa-bolt"></i><br>No flash deals yet.</div>
    <?php else: ?>
    <table class="es-table">
        <thead><tr><th>Title</th><th>Discount</th><th>Period</th><th>Products</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php $__currentLoopData = $flashDeals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fd): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td>
                <div style="font-weight:700"><?php echo e($fd->title); ?></div>
                <?php if($fd->subtitle): ?><div style="font-size:11px;color:#aaa"><?php echo e($fd->subtitle); ?></div><?php endif; ?>
            </td>
            <td>
                <span class="<?php echo e($fd->discount_type==='percentage'?'badge-pct':'badge-fixed'); ?>">
                    <?php echo e($fd->discount_type==='percentage' ? $fd->discount_value.'%' : '$'.number_format($fd->discount_value,2)); ?> OFF
                </span>
            </td>
            <td>
                <div style="font-size:12px;"><?php echo e($fd->starts_at->format('d M H:i')); ?> → <?php echo e($fd->ends_at->format('d M H:i')); ?></div>
                <?php if($fd->is_active && $fd->ends_at > now()): ?>
                <div class="countdown" data-ends="<?php echo e($fd->ends_at->toIso8601String()); ?>">Live</div>
                <?php elseif($fd->ends_at <= now()): ?>
                <div style="font-size:11px;color:#dc2626;font-weight:600;">Expired</div>
                <?php endif; ?>
            </td>
            <td><span style="font-weight:700"><?php echo e($fd->products_count); ?></span></td>
            <td>
                <form method="POST" action="<?php echo e(route('admin.eshop.flash-deal.toggle', $fd->id)); ?>" style="display:inline">
                    <?php echo csrf_field(); ?> <button class="btn-xs <?php echo e($fd->is_active?'btn-tog':'btn-edit'); ?>"><?php echo e($fd->is_active?'Active':'Inactive'); ?></button>
                </form>
            </td>
            <td>
                <div style="display:flex;gap:5px;flex-wrap:wrap;">
                    <button class="btn-xs btn-edit" onclick="editFlashDeal(<?php echo e($fd->id); ?>, <?php echo e(json_encode(['title'=>$fd->title,'subtitle'=>$fd->subtitle,'discount_type'=>$fd->discount_type,'discount_value'=>$fd->discount_value,'starts_at'=>$fd->starts_at->format('Y-m-d\TH:i'),'ends_at'=>$fd->ends_at->format('Y-m-d\TH:i')])); ?>)">Edit</button>
                    <button class="btn-xs btn-warn" onclick="openAddProductToEntity('flash_deal', <?php echo e($fd->id); ?>, '<?php echo e($fd->title); ?>')">+ Products</button>
                    <form method="POST" action="<?php echo e(route('admin.eshop.flash-deal.destroy', $fd->id)); ?>" style="display:inline" onsubmit="return confirm('Delete flash deal?')">
                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?> <button class="btn-xs btn-del">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>


<div class="tab-pane" id="pane-deals">
    <div class="section-bar">
        <h4><i class="fas fa-fire" style="color:#f97316"></i> Deals of the Day (<?php echo e($dealsOfDay->count()); ?>)</h4>
        <button class="btn-xs btn-primary" onclick="openModal('modal-add-deal')"><i class="fas fa-plus"></i> Add Deal</button>
    </div>
    <?php if($dealsOfDay->isEmpty()): ?>
    <div class="empty-state"><i class="fas fa-fire"></i><br>No deals of the day.</div>
    <?php else: ?>
    <table class="es-table">
        <thead><tr><th>Product</th><th>Badge</th><th>Discount</th><th>Ends At</th><th>Sort</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php $__currentLoopData = $dealsOfDay; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <?php if($d->product?->thumbnail): ?>
                    <img src="<?php echo e($d->product->thumbnail); ?>" style="width:40px;height:40px;border-radius:8px;object-fit:cover;">
                    <?php endif; ?>
                    <div>
                        <div style="font-weight:700"><?php echo e($d->product?->name ?? 'Unknown'); ?></div>
                        <div style="font-size:11px;color:#aaa">$<?php echo e(number_format($d->product?->price ?? 0,2)); ?></div>
                    </div>
                </div>
            </td>
            <td><?php echo e($d->badge ?: '—'); ?></td>
            <td>
                <?php if($d->discount_value > 0): ?>
                    <span class="<?php echo e($d->discount_type==='percentage'?'badge-pct':'badge-fixed'); ?>">
                        <?php echo e($d->discount_type==='percentage' ? $d->discount_value.'%' : '$'.number_format($d->discount_value,2)); ?> OFF
                    </span>
                <?php else: ?>
                    <span style="color:#aaa">No discount</span>
                <?php endif; ?>
            </td>
            <td style="font-size:12px"><?php echo e($d->ends_at ? $d->ends_at->format('M d, H:i') : '—'); ?></td>
            <td><?php echo e($d->sort_order); ?></td>
            <td><span class="<?php echo e($d->is_active?'badge-active':'badge-inactive'); ?>"><?php echo e($d->is_active?'Active':'Inactive'); ?></span></td>
            <td>
                <form method="POST" action="<?php echo e(route('admin.eshop.deal-of-day.destroy', $d->id)); ?>" style="display:inline" onsubmit="return confirm('Remove this deal?')">
                    <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?> <button class="btn-xs btn-del">Remove</button>
                </form>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>


<div class="tab-pane" id="pane-coupons">
    <div class="section-bar">
        <h4><i class="fas fa-tag" style="color:#8b5cf6"></i> Coupons (<?php echo e($coupons->count()); ?>)</h4>
        <button class="btn-xs btn-primary" onclick="openModal('modal-add-coupon')"><i class="fas fa-plus"></i> Create Coupon</button>
    </div>
    <?php if($coupons->isEmpty()): ?>
    <div class="empty-state"><i class="fas fa-tag"></i><br>No coupons yet.</div>
    <?php else: ?>
    <table class="es-table">
        <thead><tr><th>Code</th><th>Discount</th><th>Min Order</th><th>Usage</th><th>Expires</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php $__currentLoopData = $coupons; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td>
                <code style="background:#f3f4f6;padding:3px 10px;border-radius:6px;font-weight:700;font-size:13px;"><?php echo e($c->code); ?></code>
                <?php if($c->title): ?><div style="font-size:11px;color:#666;margin-top:3px;"><?php echo e($c->title); ?></div><?php endif; ?>
            </td>
            <td>
                <span class="<?php echo e($c->type==='percentage'?'badge-pct':'badge-fixed'); ?>">
                    <?php echo e($c->type==='percentage' ? $c->value.'%' : '$'.number_format($c->value,2)); ?> OFF
                </span>
            </td>
            <td>$<?php echo e(number_format($c->min_order_amount ?? 0, 2)); ?></td>
            <td>
                <span style="font-weight:700"><?php echo e($c->used_count); ?></span>
                <?php if($c->usage_limit): ?> / <?php echo e($c->usage_limit); ?> <?php else: ?> / ∞ <?php endif; ?>
            </td>
            <td><?php echo e($c->ends_at ? $c->ends_at->format('d M Y') : '∞'); ?></td>
            <td>
                <form method="POST" action="<?php echo e(route('admin.eshop.coupon.toggle', $c->id)); ?>" style="display:inline">
                    <?php echo csrf_field(); ?> <button class="btn-xs <?php echo e($c->is_active?'btn-tog':'btn-edit'); ?>"><?php echo e($c->is_active?'Active':'Inactive'); ?></button>
                </form>
            </td>
            <td>
                <div style="display:flex;gap:5px;">
                    <button class="btn-xs btn-edit" onclick="editCoupon(<?php echo e($c->id); ?>, <?php echo e(json_encode(['code'=>$c->code,'title'=>$c->title,'description'=>$c->description,'type'=>$c->type,'value'=>$c->value,'min_order_amount'=>$c->min_order_amount,'max_discount'=>$c->max_discount,'usage_limit'=>$c->usage_limit,'starts_at'=>$c->starts_at?->format('Y-m-d'),'ends_at'=>$c->ends_at?->format('Y-m-d')])); ?>)">Edit</button>
                    <form method="POST" action="<?php echo e(route('admin.eshop.coupon.destroy', $c->id)); ?>" style="display:inline" onsubmit="return confirm('Delete coupon?')">
                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?> <button class="btn-xs btn-del">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>


<div class="tab-pane" id="pane-campaigns">
    <div class="section-bar">
        <h4><i class="fas fa-bullhorn" style="color:#3b82f6"></i> Campaign Offers</h4>
        <button class="btn-xs btn-primary" onclick="openModal('modal-add-campaign')"><i class="fas fa-plus"></i> New Campaign</button>
    </div>
    <?php if($campaigns->isEmpty()): ?>
    <div class="empty-state"><i class="fas fa-bullhorn"></i><br>No campaigns yet.</div>
    <?php else: ?>
    <table class="es-table">
        <thead><tr><th>Campaign</th><th>Discount</th><th>Period</th><th>Products</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php $__currentLoopData = $campaigns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $camp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <?php if($camp->banner): ?>
                    <img src="<?php echo e($camp->banner); ?>" style="width:60px;height:36px;border-radius:6px;object-fit:cover;">
                    <?php endif; ?>
                    <div>
                        <div style="font-weight:700"><?php echo e($camp->title); ?></div>
                        <?php if($camp->badge): ?><span style="background:#fef9c3;color:#92400e;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;"><?php echo e($camp->badge); ?></span><?php endif; ?>
                    </div>
                </div>
            </td>
            <td>
                <span class="<?php echo e($camp->discount_type==='percentage'?'badge-pct':'badge-fixed'); ?>">
                    <?php echo e($camp->discount_type==='percentage' ? $camp->discount_value.'%' : '$'.number_format($camp->discount_value,2)); ?> OFF
                </span>
            </td>
            <td style="font-size:12px;">
                <?php echo e($camp->starts_at?->format('d M') ?? '—'); ?> → <?php echo e($camp->ends_at?->format('d M') ?? '—'); ?>

            </td>
            <td><span style="font-weight:700"><?php echo e($camp->products_count); ?></span></td>
            <td>
                <form method="POST" action="<?php echo e(route('admin.eshop.campaign.toggle', $camp->id)); ?>" style="display:inline">
                    <?php echo csrf_field(); ?> <button class="btn-xs <?php echo e($camp->is_active?'btn-tog':'btn-edit'); ?>"><?php echo e($camp->is_active?'Active':'Inactive'); ?></button>
                </form>
            </td>
            <td>
                <div style="display:flex;gap:5px;flex-wrap:wrap;">
                    <button class="btn-xs btn-edit" onclick="editCampaign(<?php echo e($camp->id); ?>, <?php echo e(json_encode(['title'=>$camp->title,'subtitle'=>$camp->subtitle,'badge'=>$camp->badge,'discount_type'=>$camp->discount_type,'discount_value'=>$camp->discount_value,'starts_at'=>$camp->starts_at?->format('Y-m-d'),'ends_at'=>$camp->ends_at?->format('Y-m-d'),'sort_order'=>$camp->sort_order])); ?>)">Edit</button>
                    <button class="btn-xs btn-warn" onclick="openAddProductToEntity('campaign', <?php echo e($camp->id); ?>, '<?php echo e($camp->title); ?>')">+ Products</button>
                    <form method="POST" action="<?php echo e(route('admin.eshop.campaign.destroy', $camp->id)); ?>" style="display:inline" onsubmit="return confirm('Delete campaign?')">
                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?> <button class="btn-xs btn-del">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>


<div class="tab-pane" id="pane-orders">
    <div class="section-bar">
        <h4><i class="fas fa-receipt" style="color:#3b82f6"></i> Shop Orders (<?php echo e($orders->total()); ?>)</h4>
    </div>
    <?php if($orders->isEmpty()): ?>
    <div class="empty-state"><i class="fas fa-receipt"></i><br>No orders yet.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="es-table">
        <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td><code style="font-weight:700;font-size:12px;"><?php echo e($o->order_number); ?></code></td>
            <td>
                <div style="font-weight:600"><?php echo e($o->customer_name ?? '—'); ?></div>
                <div style="font-size:11px;color:#aaa"><?php echo e($o->customer_phone ?? ''); ?></div>
            </td>
            <td style="font-weight:800;color:var(--brand)">$<?php echo e(number_format($o->total_amount,2)); ?></td>
            <td><span style="background:#f3f4f6;padding:3px 8px;border-radius:6px;font-size:11px;"><?php echo e(ucfirst($o->payment_method ?? 'cod')); ?></span></td>
            <td><span class="order-status status-<?php echo e($o->status); ?>"><?php echo e(ucfirst($o->status)); ?></span></td>
            <td style="font-size:12px;color:#666;"><?php echo e(\Carbon\Carbon::parse($o->created_at)->format('d M Y H:i')); ?></td>
            <td>
                <form method="POST" action="<?php echo e(route('admin.eshop.order.status', $o->id)); ?>" style="display:flex;gap:6px;align-items:center;">
                    <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                    <select name="status" class="form-control" style="width:120px;padding:5px 8px;font-size:12px;">
                        <?php $__currentLoopData = ['pending','confirmed','processing','shipped','delivered','cancelled','refunded']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($s); ?>" <?php echo e($o->status===$s?'selected':''); ?>><?php echo e(ucfirst($s)); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <button class="btn-xs btn-primary" type="submit">Update</button>
                </form>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    </div>
    <div class="pagination-wrap"><?php echo e($orders->links()); ?></div>
    <?php endif; ?>
</div>

</div>
</div>




<div class="modal-backdrop" id="modal-add-product">
<div class="modal-box">
    <h3><i class="fas fa-plus-circle" style="color:var(--brand)"></i> Add Product</h3>
    <form method="POST" action="<?php echo e(route('admin.eshop.product.store')); ?>" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <div class="form-row">
            <div class="form-group full"><label>Product Name *</label><input name="name" class="form-control" required placeholder="e.g. Nike Air Max 2024"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Price *</label><input name="price" type="number" step="0.01" class="form-control" required placeholder="0.00"></div>
            <div class="form-group"><label>Sale Price</label><input name="sale_price" type="number" step="0.01" class="form-control" placeholder="0.00"></div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="form-control">
                    <option value="">— Select Category —</option>
                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($cat->id); ?>"><?php echo e($cat->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="form-group">
                <label>Unit</label>
                <select name="unit" class="form-control">
                    <option value="">— None —</option>
                    <?php $__currentLoopData = $units; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($u->symbol); ?>"><?php echo e($u->name); ?> (<?php echo e($u->symbol); ?>)</option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <option value="piece">Piece</option>
                    <option value="kg">Kilogram</option>
                    <option value="g">Gram</option>
                    <option value="liter">Liter</option>
                    <option value="box">Box</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>SKU</label><input name="sku" class="form-control" placeholder="e.g. NIKE-AM-2024"></div>
            <div class="form-group"><label>Brand</label><input name="brand" class="form-control" placeholder="e.g. Nike"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Stock Quantity</label><input name="stock_quantity" type="number" class="form-control" value="0"></div>
            <div class="form-group"><label>Min Order Qty</label><input name="min_qty" type="number" class="form-control" value="1"></div>
        </div>
        <div class="form-row full">
            <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3" placeholder="Product description..."></textarea></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Thumbnail Image</label><input name="image_file" type="file" accept="image/*" class="form-control"></div>
            <div class="form-group"><label>Tags (comma separated)</label><input name="tags" class="form-control" placeholder="summer, sale, new"></div>
        </div>
        <div class="form-row full">
            <div class="form-group">
                <label>Gallery Images <small style="color:#aaa">(multiple)</small></label>
                <input name="gallery_files[]" type="file" accept="image/*" multiple class="form-control">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label><input type="checkbox" name="is_available" value="1" checked> Active</label></div>
            <div class="form-group"><label><input type="checkbox" name="is_featured" value="1"> Featured Product</label></div>
        </div>

        
        <div style="margin:14px 0 6px;font-weight:700;font-size:13px;color:#374151;">
            <i class="fas fa-tags"></i> Variants / Attributes
            <small style="color:#aaa;font-weight:400">(optional – for different sizes, colors, etc.)</small>
        </div>
        <div id="add-variants-list"></div>
        <button type="button" class="btn-xs" style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;margin-bottom:12px" onclick="addVariantRow('add-variants-list')">
            <i class="fas fa-plus"></i> Add Variant
        </button>

        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-add-product')">Cancel</button>
            <button type="submit" class="btn-save"><i class="fas fa-save"></i> Add Product</button>
        </div>
    </form>
</div></div>


<div class="modal-backdrop" id="modal-edit-product">
<div class="modal-box">
    <h3><i class="fas fa-edit" style="color:var(--brand)"></i> Edit Product</h3>
    <form method="POST" id="form-edit-product" enctype="multipart/form-data">
        <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
        <div class="form-row">
            <div class="form-group full"><label>Product Name *</label><input name="name" id="ep-name" class="form-control" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Price *</label><input name="price" id="ep-price" type="number" step="0.01" class="form-control" required></div>
            <div class="form-group"><label>Sale Price</label><input name="sale_price" id="ep-sale_price" type="number" step="0.01" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" id="ep-category_id" class="form-control">
                    <option value="">— Select Category —</option>
                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($cat->id); ?>"><?php echo e($cat->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="form-group">
                <label>Unit</label>
                <select name="unit" id="ep-unit" class="form-control">
                    <option value="">— None —</option>
                    <?php $__currentLoopData = $units; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($u->symbol); ?>"><?php echo e($u->name); ?> (<?php echo e($u->symbol); ?>)</option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <option value="piece">Piece</option>
                    <option value="kg">Kilogram</option>
                    <option value="g">Gram</option>
                    <option value="liter">Liter</option>
                    <option value="box">Box</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>SKU</label><input name="sku" id="ep-sku" class="form-control"></div>
            <div class="form-group"><label>Brand</label><input name="brand" id="ep-brand" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Stock Quantity</label><input name="stock_quantity" id="ep-stock" type="number" class="form-control"></div>
            <div class="form-group"><label>Min Order Qty</label><input name="min_qty" id="ep-min_qty" type="number" class="form-control"></div>
        </div>
        <div class="form-row full">
            <div class="form-group"><label>Description</label><textarea name="description" id="ep-description" class="form-control" rows="3"></textarea></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>New Thumbnail (leave blank to keep)</label><input name="image_file" type="file" accept="image/*" class="form-control"></div>
            <div class="form-group"><label>Tags</label><input name="tags" id="ep-tags" class="form-control"></div>
        </div>
        <div class="form-row full">
            <div class="form-group">
                <label>Add Gallery Images <small style="color:#aaa">(multiple, added to existing)</small></label>
                <input name="gallery_files[]" type="file" accept="image/*" multiple class="form-control">
            </div>
        </div>
        <div id="ep-gallery-preview" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px"></div>
        <div class="form-row">
            <div class="form-group"><label><input type="checkbox" name="is_available" id="ep-is_available" value="1"> Active</label></div>
            <div class="form-group"><label><input type="checkbox" name="is_featured" id="ep-is_featured" value="1"> Featured</label></div>
        </div>

        
        <div style="margin:14px 0 6px;font-weight:700;font-size:13px;color:#374151;">
            <i class="fas fa-tags"></i> Variants / Attributes
        </div>
        <div id="edit-variants-list"></div>
        <button type="button" class="btn-xs" style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;margin-bottom:12px" onclick="addVariantRow('edit-variants-list')">
            <i class="fas fa-plus"></i> Add Variant
        </button>

        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-product')">Cancel</button>
            <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </form>
</div></div>


<div class="modal-backdrop" id="modal-add-cat">
<div class="modal-box">
    <h3><i class="fas fa-plus-circle" style="color:var(--brand)"></i> Add Category</h3>
    <form method="POST" action="<?php echo e(route('admin.eshop.category.store')); ?>" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <div class="form-row full"><div class="form-group"><label>Name *</label><input name="name" class="form-control" required placeholder="e.g. Electronics"></div></div>
        <div class="form-row">
            <div class="form-group">
                <label>Parent Category</label>
                <select name="parent_id" class="form-control">
                    <option value="">— Top Level —</option>
                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($cat->id); ?>"><?php echo e($cat->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="form-group"><label>Sort Order</label><input name="sort_order" type="number" class="form-control" value="0"></div>
        </div>
        <div class="form-row full"><div class="form-group"><label>Image</label><input name="image_file" type="file" accept="image/*" class="form-control"></div></div>
        <div class="form-row full"><div class="form-group"><label><input type="checkbox" name="is_active" value="1" checked> Active</label></div></div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-add-cat')">Cancel</button>
            <button type="submit" class="btn-save">Add Category</button>
        </div>
    </form>
</div></div>


<div class="modal-backdrop" id="modal-edit-cat">
<div class="modal-box">
    <h3>Edit Category</h3>
    <form method="POST" id="form-edit-cat" enctype="multipart/form-data">
        <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
        <div class="form-row full"><div class="form-group"><label>Name *</label><input name="name" id="ec-name" class="form-control" required></div></div>
        <div class="form-row">
            <div class="form-group">
                <label>Parent Category</label>
                <select name="parent_id" id="ec-parent_id" class="form-control">
                    <option value="">— Top Level —</option>
                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($cat->id); ?>"><?php echo e($cat->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="form-group"><label>Sort Order</label><input name="sort_order" id="ec-sort" type="number" class="form-control"></div>
        </div>
        <div class="form-row full"><div class="form-group"><label>New Image</label><input name="image_file" type="file" accept="image/*" class="form-control"></div></div>
        <div class="form-row full"><div class="form-group"><label><input type="checkbox" name="is_active" id="ec-is_active" value="1"> Active</label></div></div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-cat')">Cancel</button>
            <button type="submit" class="btn-save">Save</button>
        </div>
    </form>
</div></div>


<div class="modal-backdrop" id="modal-add-attr">
<div class="modal-box">
    <h3>Add Attribute</h3>
    <form method="POST" action="<?php echo e(route('admin.eshop.attribute.store')); ?>">
        <?php echo csrf_field(); ?>
        <div class="form-row full"><div class="form-group"><label>Name *</label><input name="name" class="form-control" required placeholder="e.g. Color, Size, Material"></div></div>
        <div class="form-row">
            <div class="form-group">
                <label>Type</label>
                <select name="type" class="form-control">
                    <option value="select">Select (dropdown)</option>
                    <option value="color">Color (with color swatch)</option>
                    <option value="text">Text (free entry)</option>
                </select>
            </div>
            <div class="form-group"><label>Sort Order</label><input name="sort_order" type="number" class="form-control" value="0"></div>
        </div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-add-attr')">Cancel</button>
            <button type="submit" class="btn-save">Add Attribute</button>
        </div>
    </form>
</div></div>


<div class="modal-backdrop" id="modal-edit-attr">
<div class="modal-box">
    <h3>Edit Attribute</h3>
    <form method="POST" id="form-edit-attr">
        <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
        <div class="form-row full"><div class="form-group"><label>Name *</label><input name="name" id="ea-name" class="form-control" required></div></div>
        <div class="form-row full"><div class="form-group">
            <label>Type</label>
            <select name="type" id="ea-type" class="form-control">
                <option value="select">Select</option>
                <option value="color">Color</option>
                <option value="text">Text</option>
            </select>
        </div></div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-attr')">Cancel</button>
            <button type="submit" class="btn-save">Save</button>
        </div>
    </form>
</div></div>


<div class="modal-backdrop" id="modal-add-value">
<div class="modal-box" style="max-width:400px;">
    <h3 id="modal-add-value-title">Add Value</h3>
    <form method="POST" id="form-add-value">
        <?php echo csrf_field(); ?>
        <div class="form-group" style="margin-bottom:14px;"><label>Value *</label><input name="value" id="av-value" class="form-control" required placeholder="e.g. Red, XL, Cotton"></div>
        <div class="form-group" id="av-color-wrap" style="margin-bottom:14px;display:none;">
            <label>Color Code</label><input name="color_code" id="av-color" type="color" class="form-control" style="height:40px;padding:3px;" value="#FF8A00">
        </div>
        <div class="form-group" style="margin-bottom:14px;"><label>Sort Order</label><input name="sort_order" class="form-control" type="number" value="0"></div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-add-value')">Cancel</button>
            <button type="submit" class="btn-save">Add Value</button>
        </div>
    </form>
</div></div>


<div class="modal-backdrop" id="modal-add-unit">
<div class="modal-box" style="max-width:400px;">
    <h3>Add Unit</h3>
    <form method="POST" action="<?php echo e(route('admin.eshop.unit.store')); ?>">
        <?php echo csrf_field(); ?>
        <div class="form-group" style="margin-bottom:14px;"><label>Name *</label><input name="name" class="form-control" required placeholder="e.g. Kilogram"></div>
        <div class="form-group" style="margin-bottom:14px;"><label>Symbol *</label><input name="symbol" class="form-control" required placeholder="e.g. kg"></div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-add-unit')">Cancel</button>
            <button type="submit" class="btn-save">Add Unit</button>
        </div>
    </form>
</div></div>


<div class="modal-backdrop" id="modal-edit-unit">
<div class="modal-box" style="max-width:400px;">
    <h3>Edit Unit</h3>
    <form method="POST" id="form-edit-unit">
        <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
        <div class="form-group" style="margin-bottom:14px;"><label>Name *</label><input name="name" id="eu-name" class="form-control" required></div>
        <div class="form-group" style="margin-bottom:14px;"><label>Symbol *</label><input name="symbol" id="eu-symbol" class="form-control" required></div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-unit')">Cancel</button>
            <button type="submit" class="btn-save">Save</button>
        </div>
    </form>
</div></div>


<div class="modal-backdrop" id="modal-add-flash">
<div class="modal-box">
    <h3>⚡ Create Flash Deal</h3>
    <form method="POST" action="<?php echo e(route('admin.eshop.flash-deal.store')); ?>" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <div class="form-row full"><div class="form-group"><label>Title *</label><input name="title" class="form-control" required placeholder="e.g. Weekend Flash Sale"></div></div>
        <div class="form-row full"><div class="form-group"><label>Subtitle</label><input name="subtitle" class="form-control" placeholder="e.g. Up to 50% off selected items"></div></div>
        <div class="form-row">
            <div class="form-group">
                <label>Discount Type *</label>
                <select name="discount_type" class="form-control">
                    <option value="percentage">Percentage (%)</option>
                    <option value="fixed">Fixed Amount ($)</option>
                </select>
            </div>
            <div class="form-group"><label>Discount Value *</label><input name="discount_value" type="number" step="0.01" class="form-control" required placeholder="e.g. 20"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Starts At *</label><input name="starts_at" type="datetime-local" class="form-control" required></div>
            <div class="form-group"><label>Ends At *</label><input name="ends_at" type="datetime-local" class="form-control" required></div>
        </div>
        <div class="form-row full"><div class="form-group"><label>Banner Image</label><input name="banner_file" type="file" accept="image/*" class="form-control"></div></div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-add-flash')">Cancel</button>
            <button type="submit" class="btn-save">Create Flash Deal</button>
        </div>
    </form>
</div></div>


<div class="modal-backdrop" id="modal-edit-flash">
<div class="modal-box">
    <h3>Edit Flash Deal</h3>
    <form method="POST" id="form-edit-flash" enctype="multipart/form-data">
        <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
        <div class="form-row full"><div class="form-group"><label>Title *</label><input name="title" id="efd-title" class="form-control" required></div></div>
        <div class="form-row full"><div class="form-group"><label>Subtitle</label><input name="subtitle" id="efd-subtitle" class="form-control"></div></div>
        <div class="form-row">
            <div class="form-group"><label>Discount Type</label>
                <select name="discount_type" id="efd-discount_type" class="form-control">
                    <option value="percentage">Percentage (%)</option>
                    <option value="fixed">Fixed Amount ($)</option>
                </select>
            </div>
            <div class="form-group"><label>Discount Value</label><input name="discount_value" id="efd-discount_value" type="number" step="0.01" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Starts At</label><input name="starts_at" id="efd-starts_at" type="datetime-local" class="form-control"></div>
            <div class="form-group"><label>Ends At</label><input name="ends_at" id="efd-ends_at" type="datetime-local" class="form-control"></div>
        </div>
        <div class="form-row full"><div class="form-group"><label>New Banner</label><input name="banner_file" type="file" accept="image/*" class="form-control"></div></div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-flash')">Cancel</button>
            <button type="submit" class="btn-save">Save Changes</button>
        </div>
    </form>
</div></div>


<div class="modal-backdrop" id="modal-add-deal">
<div class="modal-box" style="max-width:440px;">
    <h3>🔥 Add Deal of the Day</h3>
    <form method="POST" action="<?php echo e(route('admin.eshop.deal-of-day.store')); ?>">
        <?php echo csrf_field(); ?>
        <div class="form-group" style="margin-bottom:14px;">
            <label>Select Product *</label>
            <select name="product_id" class="form-control" required>
                <option value="">— Select a Product —</option>
                <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($p->id); ?>"><?php echo e($p->name); ?> ($<?php echo e(number_format($p->price,2)); ?>)</option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:14px;"><label>Badge Label</label><input name="badge" class="form-control" placeholder="e.g. 🔥 Hot Deal, ⚡ Flash, 🌟 Best Seller"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
            <div class="form-group" style="margin:0">
                <label>Discount Type</label>
                <select name="discount_type" class="form-control">
                    <option value="percentage">Percentage (%)</option>
                    <option value="fixed">Fixed Amount ($)</option>
                </select>
            </div>
            <div class="form-group" style="margin:0">
                <label>Discount Value</label>
                <input name="discount_value" type="number" step="0.01" min="0" class="form-control" value="0" placeholder="e.g. 20">
            </div>
        </div>
        <div class="form-group" style="margin-bottom:14px;"><label>Deal Ends At (optional)</label><input name="ends_at" type="datetime-local" class="form-control"></div>
        <div class="form-group" style="margin-bottom:14px;"><label>Sort Order</label><input name="sort_order" type="number" class="form-control" value="0"></div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-add-deal')">Cancel</button>
            <button type="submit" class="btn-save">Add Deal</button>
        </div>
    </form>
</div></div>


<div class="modal-backdrop" id="modal-add-coupon">
<div class="modal-box">
    <h3>🏷️ Create Coupon</h3>
    <form method="POST" action="<?php echo e(route('admin.eshop.coupon.store')); ?>">
        <?php echo csrf_field(); ?>
        <div class="form-row">
            <div class="form-group"><label>Coupon Code *</label><input name="code" class="form-control" required placeholder="e.g. SAVE20" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()"></div>
            <div class="form-group"><label>Title</label><input name="title" class="form-control" placeholder="e.g. 20% Off"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Type *</label>
                <select name="type" class="form-control">
                    <option value="percentage">Percentage (%)</option>
                    <option value="fixed">Fixed Amount ($)</option>
                </select>
            </div>
            <div class="form-group"><label>Value *</label><input name="value" type="number" step="0.01" class="form-control" required placeholder="e.g. 20"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Min Order Amount</label><input name="min_order_amount" type="number" step="0.01" class="form-control" placeholder="0.00"></div>
            <div class="form-group"><label>Max Discount ($)</label><input name="max_discount" type="number" step="0.01" class="form-control" placeholder="No limit"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Usage Limit</label><input name="usage_limit" type="number" class="form-control" placeholder="Unlimited"></div>
            <div class="form-group"><label>Expires At</label><input name="ends_at" type="date" class="form-control"></div>
        </div>
        <div class="form-row full"><div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="2" placeholder="Internal notes..."></textarea></div></div>
        <div class="form-row full"><div class="form-group"><label><input type="checkbox" name="is_active" value="1" checked> Active immediately</label></div></div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-add-coupon')">Cancel</button>
            <button type="submit" class="btn-save">Create Coupon</button>
        </div>
    </form>
</div></div>


<div class="modal-backdrop" id="modal-edit-coupon">
<div class="modal-box">
    <h3>Edit Coupon</h3>
    <form method="POST" id="form-edit-coupon">
        <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
        <div class="form-row">
            <div class="form-group"><label>Code *</label><input name="code" id="ecoup-code" class="form-control" required></div>
            <div class="form-group"><label>Title</label><input name="title" id="ecoup-title" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Type</label>
                <select name="type" id="ecoup-type" class="form-control">
                    <option value="percentage">Percentage (%)</option>
                    <option value="fixed">Fixed Amount ($)</option>
                </select>
            </div>
            <div class="form-group"><label>Value</label><input name="value" id="ecoup-value" type="number" step="0.01" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Min Order</label><input name="min_order_amount" id="ecoup-min" type="number" step="0.01" class="form-control"></div>
            <div class="form-group"><label>Max Discount</label><input name="max_discount" id="ecoup-max" type="number" step="0.01" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Usage Limit</label><input name="usage_limit" id="ecoup-limit" type="number" class="form-control"></div>
            <div class="form-group"><label>Expires At</label><input name="ends_at" id="ecoup-ends" type="date" class="form-control"></div>
        </div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-coupon')">Cancel</button>
            <button type="submit" class="btn-save">Save</button>
        </div>
    </form>
</div></div>


<div class="modal-backdrop" id="modal-add-campaign">
<div class="modal-box">
    <h3>📣 New Campaign</h3>
    <form method="POST" action="<?php echo e(route('admin.eshop.campaign.store')); ?>" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <div class="form-row full"><div class="form-group"><label>Title *</label><input name="title" class="form-control" required placeholder="e.g. Summer Sale 2026"></div></div>
        <div class="form-row">
            <div class="form-group"><label>Subtitle</label><input name="subtitle" class="form-control" placeholder="Short tagline"></div>
            <div class="form-group"><label>Badge Label</label><input name="badge" class="form-control" placeholder="e.g. 🔥 Limited Time"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Discount Type</label>
                <select name="discount_type" class="form-control">
                    <option value="percentage">Percentage (%)</option>
                    <option value="fixed">Fixed Amount ($)</option>
                </select>
            </div>
            <div class="form-group"><label>Discount Value</label><input name="discount_value" type="number" step="0.01" class="form-control" value="0"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Starts At</label><input name="starts_at" type="date" class="form-control"></div>
            <div class="form-group"><label>Ends At</label><input name="ends_at" type="date" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Banner Image</label><input name="banner_file" type="file" accept="image/*" class="form-control"></div>
            <div class="form-group"><label>Sort Order</label><input name="sort_order" type="number" class="form-control" value="0"></div>
        </div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-add-campaign')">Cancel</button>
            <button type="submit" class="btn-save">Create Campaign</button>
        </div>
    </form>
</div></div>


<div class="modal-backdrop" id="modal-edit-campaign">
<div class="modal-box">
    <h3>Edit Campaign</h3>
    <form method="POST" id="form-edit-campaign" enctype="multipart/form-data">
        <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
        <div class="form-row full"><div class="form-group"><label>Title *</label><input name="title" id="ecamp-title" class="form-control" required></div></div>
        <div class="form-row">
            <div class="form-group"><label>Subtitle</label><input name="subtitle" id="ecamp-subtitle" class="form-control"></div>
            <div class="form-group"><label>Badge</label><input name="badge" id="ecamp-badge" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Discount Type</label>
                <select name="discount_type" id="ecamp-discount_type" class="form-control">
                    <option value="percentage">Percentage (%)</option>
                    <option value="fixed">Fixed Amount ($)</option>
                </select>
            </div>
            <div class="form-group"><label>Discount Value</label><input name="discount_value" id="ecamp-discount_value" type="number" step="0.01" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Starts At</label><input name="starts_at" id="ecamp-starts_at" type="date" class="form-control"></div>
            <div class="form-group"><label>Ends At</label><input name="ends_at" id="ecamp-ends_at" type="date" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>New Banner</label><input name="banner_file" type="file" accept="image/*" class="form-control"></div>
            <div class="form-group"><label>Sort Order</label><input name="sort_order" id="ecamp-sort" type="number" class="form-control"></div>
        </div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-campaign')">Cancel</button>
            <button type="submit" class="btn-save">Save Changes</button>
        </div>
    </form>
</div></div>


<div class="modal-backdrop" id="modal-add-product-entity">
<div class="modal-box" style="max-width:440px;">
    <h3 id="modal-entity-title">Add Products</h3>
    <form method="POST" id="form-add-product-entity">
        <?php echo csrf_field(); ?>
        <div class="form-group" style="margin-bottom:14px;">
            <label>Select Product *</label>
            <select name="product_id" class="form-control" required>
                <option value="">— Select a Product —</option>
                <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($p->id); ?>"><?php echo e($p->name); ?> ($<?php echo e(number_format($p->price,2)); ?>)</option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <div id="override-price-wrap" style="display:none;margin-bottom:14px;">
            <div class="form-group"><label>Override Price (optional)</label><input name="override_price" type="number" step="0.01" class="form-control" placeholder="Leave blank to use deal discount"></div>
        </div>
        <div class="form-actions">
            <button type="button" class="btn-cancel" onclick="closeModal('modal-add-product-entity')">Cancel</button>
            <button type="submit" class="btn-save">Add to Deal</button>
        </div>
    </form>
</div></div>


<script>
// Tab system
const TAB_KEY = 'eshop_tab';
function showTab(name) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('active');
        b.style.borderBottomColor = 'transparent';
        b.style.color = '#888';
    });
    document.getElementById('pane-'+name).classList.add('active');
    const btn = document.getElementById('tab-'+name);
    btn.classList.add('active');
    btn.style.borderBottomColor = 'var(--brand)';
    btn.style.color = 'var(--brand)';
    sessionStorage.setItem(TAB_KEY, name);
}
// Restore tab from URL or sessionStorage
const urlTab = new URLSearchParams(location.search).get('tab');
const storedTab = sessionStorage.getItem(TAB_KEY);
const initTab = urlTab || storedTab || 'products';
if (['products','categories','attributes','units','flash','deals','coupons','campaigns','orders'].includes(initTab)) {
    showTab(initTab);
}

// Modal helpers
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-backdrop').forEach(m => {
    m.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
});

// Variant row builder
function addVariantRow(containerId, existing) {
    const container = document.getElementById(containerId);
    const idx = container.querySelectorAll('.variant-row').length;
    const row = document.createElement('div');
    row.className = 'variant-row';
    row.style = 'border:1px solid #e5e7eb;border-radius:8px;padding:10px;margin-bottom:8px;background:#fafafa;position:relative';
    row.innerHTML = `
        <button type="button" onclick="this.closest('.variant-row').remove()" style="position:absolute;top:6px;right:8px;background:none;border:none;color:#ef4444;cursor:pointer;font-size:15px">×</button>
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:8px;margin-bottom:8px">
            <div><label style="font-size:12px;font-weight:600">Variant Name *</label>
                <input name="variants[${idx}][name]" class="form-control" placeholder="e.g. Red / XL" value="${existing?.name||''}" required></div>
            <div><label style="font-size:12px;font-weight:600">Price *</label>
                <input name="variants[${idx}][price]" type="number" step="0.01" class="form-control" placeholder="0.00" value="${existing?.price||''}" required></div>
            <div><label style="font-size:12px;font-weight:600">Stock</label>
                <input name="variants[${idx}][stock_quantity]" type="number" class="form-control" placeholder="0" value="${existing?.stock_quantity||0}"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <div><label style="font-size:12px;font-weight:600">SKU</label>
                <input name="variants[${idx}][sku]" class="form-control" placeholder="optional" value="${existing?.sku||''}"></div>
            <div><label style="font-size:12px;font-weight:600">Attributes (e.g. Color:Red, Size:XL)</label>
                <input name="variants[${idx}][attributes_raw]" class="form-control" placeholder="Color:Red, Size:XL" value="${existing?.attributes_raw||''}"></div>
        </div>
        ${existing?.id ? `<input type="hidden" name="variants[${idx}][id]" value="${existing.id}">` : ''}
    `;
    container.appendChild(row);
}

// Product edit
function editProduct(id, data) {
    document.getElementById('form-edit-product').action = '/admin/eshop/products/'+id;
    const fields = ['name','price','sale_price','category_id','description','sku','barcode','brand','unit','stock_quantity','min_qty'];
    fields.forEach(f => {
        const el = document.getElementById('ep-'+f);
        if (el) el.value = data[f] ?? '';
    });
    document.getElementById('ep-is_available').checked = !!data.is_available;
    document.getElementById('ep-is_featured').checked = !!data.is_featured;
    // Clear and reload variants
    const varList = document.getElementById('edit-variants-list');
    varList.innerHTML = '';
    if (data.variants && data.variants.length) {
        data.variants.forEach(v => addVariantRow('edit-variants-list', {
            id: v.id, name: v.name, price: v.price, stock_quantity: v.stock_quantity,
            sku: v.sku, attributes_raw: v.attributes_raw || ''
        }));
    }
    // Gallery preview
    const preview = document.getElementById('ep-gallery-preview');
    preview.innerHTML = '';
    if (data.gallery && data.gallery.length) {
        data.gallery.forEach(img => {
            preview.innerHTML += `<div style="position:relative">
                <img src="${img.url}" style="width:60px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb">
                <a href="/admin/eshop/product-images/${img.id}/delete" onclick="return confirm('Delete image?')" style="position:absolute;top:-4px;right:-4px;background:#ef4444;color:#fff;border-radius:50%;width:16px;height:16px;font-size:10px;display:flex;align-items:center;justify-content:center;text-decoration:none">×</a>
            </div>`;
        });
    }
    openModal('modal-edit-product');
}

// Category edit
function editCat(id, data) {
    document.getElementById('form-edit-cat').action = '/admin/eshop/categories/'+id;
    document.getElementById('ec-name').value = data.name;
    document.getElementById('ec-parent_id').value = data.parent_id ?? '';
    document.getElementById('ec-sort').value = data.sort_order ?? 0;
    document.getElementById('ec-is_active').checked = !!data.is_active;
    openModal('modal-edit-cat');
}

// Attribute edit
function editAttr(id, data) {
    document.getElementById('form-edit-attr').action = '/admin/eshop/attributes/'+id;
    document.getElementById('ea-name').value = data.name;
    document.getElementById('ea-type').value = data.type;
    openModal('modal-edit-attr');
}

// Add attribute value
function openAddValue(attrId, attrType, attrName) {
    document.getElementById('form-add-value').action = '/admin/eshop/attributes/'+attrId+'/values';
    document.getElementById('modal-add-value-title').textContent = 'Add Value to: '+attrName;
    document.getElementById('av-color-wrap').style.display = attrType==='color' ? 'block' : 'none';
    document.getElementById('av-value').value = '';
    openModal('modal-add-value');
}

// Unit edit
function editUnit(id, data) {
    document.getElementById('form-edit-unit').action = '/admin/eshop/units/'+id;
    document.getElementById('eu-name').value = data.name;
    document.getElementById('eu-symbol').value = data.symbol;
    openModal('modal-edit-unit');
}

// Flash Deal edit
function editFlashDeal(id, data) {
    document.getElementById('form-edit-flash').action = '/admin/eshop/flash-deals/'+id;
    document.getElementById('efd-title').value = data.title;
    document.getElementById('efd-subtitle').value = data.subtitle ?? '';
    document.getElementById('efd-discount_type').value = data.discount_type;
    document.getElementById('efd-discount_value').value = data.discount_value;
    document.getElementById('efd-starts_at').value = data.starts_at;
    document.getElementById('efd-ends_at').value = data.ends_at;
    openModal('modal-edit-flash');
}

// Coupon edit
function editCoupon(id, data) {
    document.getElementById('form-edit-coupon').action = '/admin/eshop/coupons/'+id;
    document.getElementById('ecoup-code').value = data.code;
    document.getElementById('ecoup-title').value = data.title ?? '';
    document.getElementById('ecoup-type').value = data.type;
    document.getElementById('ecoup-value').value = data.value;
    document.getElementById('ecoup-min').value = data.min_order_amount ?? '';
    document.getElementById('ecoup-max').value = data.max_discount ?? '';
    document.getElementById('ecoup-limit').value = data.usage_limit ?? '';
    document.getElementById('ecoup-ends').value = data.ends_at ?? '';
    openModal('modal-edit-coupon');
}

// Campaign edit
function editCampaign(id, data) {
    document.getElementById('form-edit-campaign').action = '/admin/eshop/campaigns/'+id;
    document.getElementById('ecamp-title').value = data.title;
    document.getElementById('ecamp-subtitle').value = data.subtitle ?? '';
    document.getElementById('ecamp-badge').value = data.badge ?? '';
    document.getElementById('ecamp-discount_type').value = data.discount_type;
    document.getElementById('ecamp-discount_value').value = data.discount_value;
    document.getElementById('ecamp-starts_at').value = data.starts_at ?? '';
    document.getElementById('ecamp-ends_at').value = data.ends_at ?? '';
    document.getElementById('ecamp-sort').value = data.sort_order ?? 0;
    openModal('modal-edit-campaign');
}

// Add product to flash deal or campaign
function openAddProductToEntity(type, id, name) {
    document.getElementById('modal-entity-title').textContent = 'Add Product to: ' + name;
    const isFlash = type === 'flash_deal';
    document.getElementById('override-price-wrap').style.display = isFlash ? 'block' : 'none';
    const action = isFlash
        ? '/admin/eshop/flash-deals/'+id+'/products'
        : '/admin/eshop/campaigns/'+id+'/products';
    document.getElementById('form-add-product-entity').action = action;
    openModal('modal-add-product-entity');
}

// Flash deal countdown timers
function updateCountdowns() {
    document.querySelectorAll('.countdown').forEach(el => {
        const ends = new Date(el.dataset.ends);
        const now = new Date();
        const diff = ends - now;
        if (diff <= 0) { el.textContent = 'Expired'; el.style.color = '#aaa'; return; }
        const h = Math.floor(diff/3600000);
        const m = Math.floor((diff%3600000)/60000);
        const s = Math.floor((diff%60000)/1000);
        el.textContent = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    });
}
setInterval(updateCountdowns, 1000);
updateCountdowns();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/eshop/index.blade.php ENDPATH**/ ?>