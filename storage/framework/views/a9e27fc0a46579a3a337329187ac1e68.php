<?php $__env->startSection('title', 'Module: ' . $module->name); ?>
<?php $__env->startSection('content'); ?>
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="<?php echo e($module->icon); ?>"></i> <?php echo e($module->name); ?></h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?php echo e(route('admin.modules.index')); ?>">Modules</a></li>
            <li class="breadcrumb-item active"><?php echo e($module->name); ?></li>
        </ol>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">Module Settings</div>
        <div class="card-body">
            <form action="<?php echo e(route('admin.modules.update', $module->id)); ?>" method="POST">
                <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                <div class="form-group">
                    <label class="form-label">Commission Type</label>
                    <select name="commission_type" class="form-control">
                        <option value="percentage" <?php echo e($module->commission_type === 'percentage' ? 'selected' : ''); ?>>Percentage (%)</option>
                        <option value="fixed" <?php echo e($module->commission_type === 'fixed' ? 'selected' : ''); ?>>Fixed ($)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Commission Value</label>
                    <input type="number" name="commission_value" class="form-control" value="<?php echo e($module->commission_value); ?>" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?php echo e($module->description); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Module Info</div>
        <div class="card-body">
            <table>
                <tr><td class="text-muted" style="width:130px;padding:8px 12px 8px 0;">Slug</td><td><code><?php echo e($module->slug); ?></code></td></tr>
                <tr><td class="text-muted">Status</td><td><span class="badge <?php echo e($module->is_active ? 'badge-success' : 'badge-danger'); ?>"><?php echo e($module->is_active ? 'Active' : 'Inactive'); ?></span></td></tr>
                <tr><td class="text-muted">Commission</td><td><strong><?php echo e($module->commission_value); ?><?php echo e($module->commission_type === 'percentage' ? '%' : '$'); ?></strong></td></tr>
                <tr><td class="text-muted">Sort Order</td><td><?php echo e($module->sort_order); ?></td></tr>
            </table>
            <div class="mt-3">
                <form action="<?php echo e(route('admin.modules.toggle', $module->id)); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <button class="btn <?php echo e($module->is_active ? 'btn-danger' : 'btn-success'); ?>">
                        <?php echo e($module->is_active ? 'Disable Module' : 'Enable Module'); ?>

                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/modules/show.blade.php ENDPATH**/ ?>