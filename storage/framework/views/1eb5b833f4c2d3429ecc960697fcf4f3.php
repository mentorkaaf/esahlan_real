<?php $__env->startSection('title', 'Order Notification Templates'); ?>
<?php $__env->startSection('content'); ?>

<style>
.tpl-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:22px; flex-wrap:wrap; gap:12px; }
.tpl-title  { font-size:20px; font-weight:900; color:#1a1d2e; }
.tpl-sub    { font-size:13px; color:#94a3b8; margin-top:3px; }

/* Module tabs */
.mod-tabs   { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:22px; }
.mod-tab    { padding:7px 15px; border-radius:20px; font-size:12px; font-weight:700; border:1.5px solid #e2e8f0; color:#64748b; background:#fff; cursor:pointer; text-decoration:none; transition:all .15s; }
.mod-tab:hover   { border-color:#140465; color:#140465; }
.mod-tab.active  { background:#140465; color:#fff; border-color:#140465; }
.mod-tab.global  { border-color:#ff8a00; color:#ff8a00; }
.mod-tab.global.active { background:#ff8a00; color:#fff; border-color:#ff8a00; }

/* Status cards */
.status-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
@media(max-width:860px) { .status-grid { grid-template-columns:1fr; } }

.status-card { background:#fff; border:1.5px solid #e8ecf2; border-radius:14px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.03); }
.status-card-head { display:flex; align-items:center; gap:10px; padding:13px 18px; border-bottom:1px solid #f0f2f6; }
.status-badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:800; white-space:nowrap; }
.status-card-body { padding:16px 18px; }
.tpl-label { font-size:11px; font-weight:700; color:#64748b; margin-bottom:5px; display:flex; align-items:center; justify-content:space-between; }
.tpl-input { width:100%; padding:8px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:13px; color:#1a1d2e; box-sizing:border-box; transition:border-color .2s; }
.tpl-input:focus { outline:none; border-color:#140465; box-shadow:0 0 0 3px rgba(20,4,101,.06); }
textarea.tpl-input { resize:vertical; min-height:60px; }
.tpl-mb { margin-bottom:10px; }
.placeholder-hint { font-size:10px; color:#94a3b8; margin-top:4px; }
.inherited-tag { font-size:10px; background:rgba(245,158,11,.1); color:#b45309; border-radius:4px; padding:1px 6px; font-weight:700; }

.save-bar { position:sticky; bottom:0; background:#fff; border-top:1px solid #e8ecf2; padding:14px 0; display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:24px; box-shadow:0 -4px 16px rgba(0,0,0,.06); }
.btn-save { padding:11px 28px; border-radius:10px; background:#140465; color:#fff; border:none; font-size:14px; font-weight:700; cursor:pointer; transition:opacity .2s; }
.btn-save:hover { opacity:.87; }
.btn-reset { padding:11px 20px; border-radius:10px; background:#fff; border:1.5px solid #e2e8f0; color:#64748b; font-size:13px; font-weight:700; cursor:pointer; transition:all .15s; text-decoration:none; display:inline-flex; align-items:center; gap:7px; }
.btn-reset:hover { border-color:#ef4444; color:#ef4444; }
</style>

<div class="tpl-header">
    <div>
        <div class="tpl-title">Order Notification Templates</div>
        <div class="tpl-sub">Customize the push notification messages sent to customers when order status changes.</div>
    </div>
    <a href="<?php echo e(route('admin.notifications.index')); ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Notifications</a>
</div>

<?php if(session('success')): ?>
<div style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.3);border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:#065f46;display:flex;align-items:center;gap:10px;">
    <i class="fas fa-check-circle" style="color:#10b981;"></i> <?php echo e(session('success')); ?>

</div>
<?php endif; ?>


<div style="display:flex;gap:0;margin-bottom:16px;border-bottom:2px solid #e2e8f0;">
    <a href="<?php echo e(route('admin.notifications.templates', ['module' => $moduleSlug, 'target' => 'customer'])); ?>"
       style="padding:10px 24px;font-size:14px;font-weight:700;border-bottom:3px solid <?php echo e($target === 'customer' ? '#FF8A00' : 'transparent'); ?>;color:<?php echo e($target === 'customer' ? '#FF8A00' : '#64748b'); ?>;text-decoration:none;margin-bottom:-2px;">
        <i class="fas fa-user"></i> Customer Notifications
    </a>
    <a href="<?php echo e(route('admin.notifications.templates', ['module' => $moduleSlug, 'target' => 'driver'])); ?>"
       style="padding:10px 24px;font-size:14px;font-weight:700;border-bottom:3px solid <?php echo e($target === 'driver' ? '#10B981' : 'transparent'); ?>;color:<?php echo e($target === 'driver' ? '#10B981' : '#64748b'); ?>;text-decoration:none;margin-bottom:-2px;">
        <i class="fas fa-motorcycle"></i> Driver Notifications
    </a>
</div>


<div class="mod-tabs">
    <a href="<?php echo e(route('admin.notifications.templates', ['target' => $target])); ?>"
       class="mod-tab global <?php echo e($moduleSlug === null ? 'active' : ''); ?>">
        <i class="fas fa-globe"></i> Global Default
    </a>
    <?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mod): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route('admin.notifications.templates', ['module' => $mod->slug, 'target' => $target])); ?>"
       class="mod-tab <?php echo e($moduleSlug === $mod->slug ? 'active' : ''); ?>">
        <?php echo e($mod->name); ?>

    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<?php
$statusMeta = [
    'pending'          => ['Order Received',    'badge-warning',  'fas fa-clock'],
    'confirmed'        => ['Confirmed',          'badge-info',     'fas fa-check'],
    'preparing'        => ['Preparing',          'badge-info',     'fas fa-fire'],
    'ready_for_pickup' => ['Ready for Pickup',   'badge-primary',  'fas fa-box'],
    'out_for_delivery' => ['Out for Delivery',   'badge-primary',  'fas fa-motorcycle'],
    'delivered'        => ['Delivered',          'badge-success',  'fas fa-house'],
    'cancelled'        => ['Cancelled',          'badge-danger',   'fas fa-times'],
    'refunded'         => ['Refunded',           'badge-warning',  'fas fa-undo'],
    'failed'           => ['Failed',             'badge-danger',   'fas fa-exclamation'],
];
?>

<form method="POST" action="<?php echo e(route('admin.notifications.templates.save')); ?>">
<?php echo csrf_field(); ?>
<input type="hidden" name="module_slug" value="<?php echo e($moduleSlug); ?>">
<input type="hidden" name="target" value="<?php echo e($target); ?>">

<div class="status-grid">
<?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
    $meta      = $statusMeta[$status] ?? [$status, 'badge-neutral', 'fas fa-bell'];
    $tpl       = $existing->get($status);
    $global    = $globals->get($status);
    $isGlobal  = ($moduleSlug === null);
    // For module-specific: if no override, show global value as placeholder
    $titleVal  = $tpl?->title ?? '';
    $bodyVal   = $tpl?->body ?? '';
    $inherited = (!$isGlobal && !$tpl && $global);
?>
<div class="status-card">
    <div class="status-card-head">
        <i class="<?php echo e($meta[2]); ?>" style="font-size:14px;color:#64748b;width:18px;text-align:center;"></i>
        <span class="status-badge <?php echo e($meta[1]); ?>"><?php echo e($meta[0]); ?></span>
        <?php if($inherited): ?>
        <span class="inherited-tag">Using Global</span>
        <?php elseif($tpl && !$isGlobal): ?>
        <span style="font-size:10px;background:rgba(20,4,101,.08);color:#140465;border-radius:4px;padding:1px 6px;font-weight:700;">Custom</span>
        <?php endif; ?>
    </div>
    <div class="status-card-body">
        <div class="tpl-mb">
            <div class="tpl-label">
                Title
                <?php if($inherited): ?><span style="font-size:10px;color:#94a3b8;">leave blank to use global</span><?php endif; ?>
            </div>
            <input type="text" name="title_<?php echo e($status); ?>" class="tpl-input"
                   value="<?php echo e($titleVal); ?>"
                   placeholder="<?php echo e($inherited ? $global->title : 'Notification title…'); ?>">
        </div>
        <div class="tpl-mb">
            <div class="tpl-label">Message Body</div>
            <textarea name="body_<?php echo e($status); ?>" class="tpl-input"
                      placeholder="<?php echo e($inherited ? $global->body : 'Notification body…'); ?>"><?php echo e($bodyVal); ?></textarea>
        </div>
        <div class="placeholder-hint"><i class="fas fa-info-circle"></i> Use <code>{order_number}</code> to insert the order number.</div>
    </div>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<div class="save-bar">
    <div style="font-size:13px;color:#94a3b8;">
        <?php if($moduleSlug): ?>
            Editing templates for <strong style="color:#140465;"><?php echo e(ucfirst($moduleSlug)); ?></strong>.
            Leave fields blank to fall back to the Global default.
        <?php else: ?>
            Editing <strong style="color:#ff8a00;">Global default</strong> templates — applied to all modules without a custom override.
        <?php endif; ?>
    </div>
    <div style="display:flex;gap:10px;">
        <?php if($moduleSlug): ?>
        <a href="<?php echo e(route('admin.notifications.templates', ['module' => $moduleSlug])); ?>"
           class="btn-reset" onclick="return confirm('Reset all <?php echo e(ucfirst($moduleSlug)); ?> overrides to global defaults?') || (event.preventDefault(), false)">
            <i class="fas fa-rotate-left"></i> Clear Overrides
        </a>
        <?php endif; ?>
        <button type="submit" class="btn-save"><i class="fas fa-floppy-disk"></i> Save Templates</button>
    </div>
</div>

</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/notifications/templates.blade.php ENDPATH**/ ?>