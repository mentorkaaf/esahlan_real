<?php $__env->startSection('title', 'Modules'); ?>
<?php $__env->startSection('content'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Modules</h1>
        <ul class="breadcrumb">
            <li><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li>
            <li>Modules</li>
        </ul>
    </div>
    <div style="font-size:13px;color:var(--text-muted);">
        <?php echo e(count($modules ?? [])); ?> modules configured
    </div>
</div>

<?php
$moduleIcons = [
    'efood'    => ['fas fa-utensils',  '#ef4444'],
    'elaundry' => ['fas fa-tshirt',    '#3b82f6'],
    'emoving'  => ['fas fa-truck',     '#f59e0b'],
    'eparcel'  => ['fas fa-box',       '#8b5cf6'],
    'edata'    => ['fas fa-wifi',      '#06b6d4'],
    'eexchange'=> ['fas fa-exchange-alt','#10b981'],
    'ehealth'  => ['fas fa-user-md',   '#ec4899'],
    'erent'    => ['fas fa-home',      '#f97316'],
    'eshop'    => ['fas fa-shopping-bag','#6366f1'],
    'wholesale'=> ['fas fa-warehouse', '#64748b'],
    'egrocery' => ['fas fa-carrot',    '#22c55e'],
    'eticket'  => ['fas fa-plane',     '#0ea5e9'],
];
?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;">
    <?php $__empty_1 = true; $__currentLoopData = $modules ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <?php
        $slug = strtolower($module->slug ?? '');
        [$mIcon, $mColor] = $moduleIcons[$slug] ?? ['fas fa-th-large', $module->color ?? '#FF8A00'];
    ?>
    <div class="card" style="margin-bottom:0;transition:box-shadow .2s,transform .2s;" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
        
        <div style="height:4px;background:<?php echo e($mColor); ?>;"></div>

        <div class="card-body" style="padding:20px;">
            <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:14px;">
                <div style="width:48px;height:48px;border-radius:13px;background:<?php echo e($mColor); ?>18;color:<?php echo e($mColor); ?>;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
                    <i class="<?php echo e($mIcon); ?>"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <h3 style="font-size:15px;font-weight:800;color:var(--text);margin:0;"><?php echo e($module->name); ?></h3>
                        <span class="badge <?php echo e($module->is_active?'badge-success':'badge-danger'); ?> badge-dot">
                            <?php echo e($module->is_active?'Active':'Inactive'); ?>

                        </span>
                    </div>
                    <p style="font-size:12px;color:var(--text-muted);margin-top:4px;line-height:1.4;"><?php echo e(Str::limit($module->description??'',80)); ?></p>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px;">
                <div style="background:#fafbff;border-radius:8px;padding:10px 12px;border:1px solid var(--border);">
                    <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:3px;">Commission</div>
                    <div style="font-size:16px;font-weight:800;color:var(--brand);"><?php echo e($module->commission_value ?? 0); ?>%</div>
                </div>
                <div style="background:#fafbff;border-radius:8px;padding:10px 12px;border:1px solid var(--border);">
                    <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:3px;">Sort Order</div>
                    <div style="font-size:16px;font-weight:800;color:var(--text);"><?php echo e($module->sort_order ?? 0); ?></div>
                </div>
            </div>

            <div style="display:flex;gap:8px;">
                <form action="<?php echo e(route('admin.modules.toggle',$module->id)); ?>" method="POST" style="flex:1;">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn btn-sm w-100 <?php echo e($module->is_active ? '' : 'btn-success'); ?>"
                        style="<?php echo e($module->is_active ? 'background:#fff5f5;color:var(--danger);border:1.5px solid #fecaca;' : ''); ?>">
                        <?php if($module->is_active): ?>
                            <i class="fas fa-toggle-off"></i> Disable
                        <?php else: ?>
                            <i class="fas fa-toggle-on"></i> Enable
                        <?php endif; ?>
                    </button>
                </form>
                <a href="<?php echo e(route('admin.modules.show',$module->id)); ?>" class="btn btn-outline btn-sm">
                    <i class="fas fa-cog"></i> Configure
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="card" style="margin-bottom:0;grid-column:1/-1;">
        <div class="empty-state">
            <i class="fas fa-th-large"></i>
            <h3>No modules found</h3>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php $__env->startPush('styles'); ?>
<style>.w-100{width:100%;}</style>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/modules/index.blade.php ENDPATH**/ ?>