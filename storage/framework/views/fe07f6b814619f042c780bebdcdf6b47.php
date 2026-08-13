<?php $__env->startSection('title', 'Vendors'); ?>
<?php $__env->startSection('content'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Vendors</h1>
        <ul class="breadcrumb">
            <li><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li>
            <li>Vendors</li>
        </ul>
    </div>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;align-items:center;">
            <div style="flex:1;min-width:200px;">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search vendors…" value="<?php echo e(request('search')); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <select name="module_id" class="form-control" style="width:170px;">
                <option value="">All Modules</option>
                <?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($module->id); ?>" <?php echo e(request('module_id')==$module->id?'selected':''); ?>><?php echo e($module->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <select name="status" class="form-control" style="width:140px;">
                <option value="">All Status</option>
                <option value="active"   <?php echo e(request('status')==='active'   ?'selected':''); ?>>Active</option>
                <option value="inactive" <?php echo e(request('status')==='inactive' ?'selected':''); ?>>Inactive</option>
            </select>
            <?php if(request()->hasAny(['search','module_id','status'])): ?>
            <a href="<?php echo e(route('admin.vendors.index')); ?>" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(139,92,246,0.1);color:var(--purple);">
                <i class="fas fa-store"></i>
            </div>
            Vendors
            <span class="badge badge-purple" style="margin-left:4px;"><?php echo e($vendors->total()); ?></span>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Vendor</th>
                    <th>Module</th>
                    <th>District</th>
                    <th>Rating</th>
                    <th>Status</th>
                    <th>Featured</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $vendors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vendor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <?php if(!empty($vendor->logo_url) && !str_contains(($vendor->logo_url??''),'null')): ?>
                                <img src="<?php echo e($vendor->logo_url); ?>" style="width:38px;height:38px;border-radius:9px;object-fit:cover;flex-shrink:0;border:1px solid var(--border);">
                            <?php else: ?>
                                <div class="avatar avatar-sm avatar-purple"><?php echo e(strtoupper(substr($vendor->name,0,1))); ?></div>
                            <?php endif; ?>
                            <div>
                                <div style="font-weight:700;font-size:13px;"><?php echo e($vendor->name); ?></div>
                                <div style="font-size:11px;color:var(--text-muted);"><?php echo e($vendor->phone); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-dark"><?php echo e($vendor->module?->name ?? '—'); ?></span>
                    </td>
                    <td style="font-size:12.5px;color:var(--text-muted);"><?php echo e($vendor->district?->name ?? '—'); ?></td>
                    <td>
                        <span style="color:#f59e0b;font-size:13px;">★</span>
                        <span style="font-weight:700;font-size:13px;"><?php echo e(number_format($vendor->rating??0,1)); ?></span>
                        <span style="font-size:11px;color:var(--text-muted);">(<?php echo e($vendor->total_reviews??0); ?>)</span>
                    </td>
                    <td>
                        <span class="badge <?php echo e($vendor->is_active?'badge-success':'badge-danger'); ?> badge-dot">
                            <?php echo e($vendor->is_active?'Active':'Inactive'); ?>

                        </span>
                    </td>
                    <td>
                        <form method="POST" action="<?php echo e(route('admin.vendors.toggle-featured',$vendor)); ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-xs <?php echo e($vendor->is_featured?'btn-primary':'btn-outline'); ?>" title="Toggle Featured">
                                <i class="fas fa-star"></i>
                            </button>
                        </form>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);"><?php echo e($vendor->created_at->format('d M Y')); ?></td>
                    <td>
                        <div style="display:flex;gap:5px;">
                            <a href="<?php echo e(route('admin.vendors.show',$vendor)); ?>" class="btn btn-outline btn-xs"><i class="fas fa-eye"></i> View</a>
                            <?php if(!$vendor->is_active): ?>
                            <form method="POST" action="<?php echo e(route('admin.vendors.approve',$vendor)); ?>">
                                <?php echo csrf_field(); ?>
                                <button class="btn btn-xs btn-success"><i class="fas fa-check"></i> Approve</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state"><i class="fas fa-store"></i><h3>No vendors found</h3><p>Try adjusting your filters</p></div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($vendors->hasPages()): ?>
    <div class="card-footer" style="display:flex;justify-content:center;">
        <?php echo e($vendors->withQueryString()->links()); ?>

    </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/vendors/index.blade.php ENDPATH**/ ?>