<?php $__env->startSection('title', $deliveryman->user?->name ?? 'Driver Details'); ?>
<?php $__env->startSection('content'); ?>
<?php $vehicleEmoji = ['motorcycle'=>'🏍️','bajaj'=>'🛺','car'=>'🚗','van'=>'🚐','truck'=>'🚛','bicycle'=>'🚲','pickup'=>'🚛'][$deliveryman->vehicle_type] ?? '🚗'; ?>

<div class="page-header">
    <div>
        <h2 class="page-title"><?php echo e($deliveryman->user?->name ?? 'Driver'); ?></h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo e(route('admin.deliverymen.index')); ?>">Deliverymen</a></li>
            <li class="breadcrumb-item active"><?php echo e($deliveryman->user?->name); ?></li>
        </ol>
    </div>
    <div style="display:flex;gap:8px;">
        <?php if(!$deliveryman->is_approved): ?>
        <form action="<?php echo e(route('admin.deliverymen.approve', $deliveryman->id)); ?>" method="POST" style="margin:0;"><?php echo csrf_field(); ?>
            <button class="btn btn-success"><i class="fas fa-check"></i> Approve</button>
        </form>
        <?php endif; ?>
        <form action="<?php echo e(route('admin.deliverymen.toggle-block', $deliveryman->id)); ?>" method="POST" style="margin:0;"><?php echo csrf_field(); ?>
            <button class="btn <?php echo e($deliveryman->user?->status === 'banned' ? 'btn-outline' : 'btn-danger'); ?>">
                <i class="fas <?php echo e($deliveryman->user?->status === 'banned' ? 'fa-unlock' : 'fa-ban'); ?>"></i>
                <?php echo e($deliveryman->user?->status === 'banned' ? 'Unblock' : 'Block'); ?>

            </button>
        </form>
        <a href="<?php echo e(route('admin.deliverymen.index')); ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    
    <div class="card">
        <div class="card-header"><div class="card-header-title"><div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:#FF8A00;"><i class="fas fa-user"></i></div> Profile</div></div>
        <div class="card-body">
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
                <div style="width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#FF8A00,#FF6B00);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;color:#fff;">
                    <?php echo e(strtoupper(substr($deliveryman->user?->name ?? 'D', 0, 1))); ?>

                </div>
                <div>
                    <div style="font-size:18px;font-weight:800;color:#07003B;"><?php echo e($deliveryman->user?->name); ?></div>
                    <div style="font-size:13px;color:#8A8A9A;"><?php echo e($deliveryman->user?->phone); ?></div>
                    <?php if($deliveryman->driver_type === 'truck'): ?>
                    <span class="badge" style="background:#f3e8ff;color:#7c3aed;margin-top:4px;">🚛 Truck Driver</span>
                    <?php else: ?>
                    <span class="badge" style="background:#e0f2fe;color:#0284c7;margin-top:4px;">🏍️ Normal Driver</span>
                    <?php endif; ?>
                </div>
            </div>
            <table style="width:100%;">
                <tr><td style="padding:8px 0;color:#8A8A9A;width:130px;">Vehicle</td><td style="font-weight:600;"><?php echo e($vehicleEmoji); ?> <?php echo e(ucfirst($deliveryman->vehicle_type)); ?></td></tr>
                <tr><td style="padding:8px 0;color:#8A8A9A;">Plate</td><td style="font-weight:600;"><?php echo e($deliveryman->vehicle_plate ?? '—'); ?></td></tr>
                <tr><td style="padding:8px 0;color:#8A8A9A;">Status</td><td>
                    <?php $sc = ['available'=>'badge-success','busy'=>'badge-warning','offline'=>'badge-secondary','pending'=>'badge-warning','banned'=>'badge-danger'][$deliveryman->status] ?? 'badge-secondary'; ?>
                    <span class="badge <?php echo e($sc); ?>"><?php echo e(ucfirst($deliveryman->status)); ?></span>
                </td></tr>
                <tr><td style="padding:8px 0;color:#8A8A9A;">Approved</td><td><?php echo e($deliveryman->is_approved ? '✅ Yes' : '❌ No'); ?></td></tr>
                <tr><td style="padding:8px 0;color:#8A8A9A;">Rating</td><td><span style="color:#f59e0b;">★</span> <?php echo e(number_format($deliveryman->rating ?? 5, 1)); ?> · <?php echo e($deliveryman->total_deliveries); ?> trips</td></tr>
                <tr><td style="padding:8px 0;color:#8A8A9A;">Online</td><td><?php echo e($deliveryman->is_online ? '🟢 Yes' : '⚫ No'); ?></td></tr>
                <tr><td style="padding:8px 0;color:#8A8A9A;">Joined</td><td><?php echo e($deliveryman->created_at->format('d M Y, H:i')); ?></td></tr>
            </table>
        </div>
    </div>

    
    <div class="card">
        <div class="card-header"><div class="card-header-title"><div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:#3B82F6;"><i class="fas fa-folder"></i></div> Documents</div></div>
        <div class="card-body">
            <?php $__empty_1 = true; $__currentLoopData = $deliveryman->documents ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div style="display:flex;align-items:center;gap:12px;padding:12px;border:1.5px solid #f0f1f5;border-radius:12px;margin-bottom:10px;">
                <?php $docUrl = url('/api/v1/img/' . $doc->file_path); ?>
                <a href="<?php echo e($docUrl); ?>" target="_blank" style="flex-shrink:0;">
                    <img src="<?php echo e($docUrl); ?>" style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:1px solid #e0e0e0;" onerror="this.style.display='none'">
                </a>
                <div style="flex:1;">
                    <div style="font-weight:700;font-size:13px;color:#07003B;"><?php echo e(ucwords(str_replace('_',' ',$doc->type))); ?></div>
                    <div style="font-size:11px;color:#8A8A9A;">Uploaded <?php echo e($doc->created_at->diffForHumans()); ?></div>
                    <?php $dsc = ['pending'=>'badge-warning','approved'=>'badge-success','rejected'=>'badge-danger'][$doc->status] ?? 'badge-secondary'; ?>
                    <span class="badge <?php echo e($dsc); ?>" style="margin-top:4px;"><?php echo e(ucfirst($doc->status)); ?></span>
                </div>
                <?php if($doc->status === 'pending'): ?>
                <div style="display:flex;gap:4px;flex-direction:column;">
                    <form action="<?php echo e(route('admin.deliverymen.document.approve', $doc->id)); ?>" method="POST" style="margin:0;"><?php echo csrf_field(); ?>
                        <button class="btn btn-xs btn-success" style="width:100%;"><i class="fas fa-check"></i> Approve</button>
                    </form>
                    <form action="<?php echo e(route('admin.deliverymen.document.reject', $doc->id)); ?>" method="POST" style="margin:0;"><?php echo csrf_field(); ?>
                        <button class="btn btn-xs btn-danger" style="width:100%;"><i class="fas fa-times"></i> Reject</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div style="text-align:center;padding:30px;color:#8A8A9A;">
                <i class="fas fa-file-alt" style="font-size:28px;display:block;margin-bottom:8px;opacity:0.3;"></i>
                No documents uploaded yet
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<div class="card" style="margin-top:16px;">
    <div class="card-header"><div class="card-header-title"><div class="card-header-icon" style="background:rgba(16,185,129,0.1);color:#10B981;"><i class="fas fa-receipt"></i></div> Recent Deliveries</div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Order #</th><th>Module</th><th>Total</th><th>Delivery Fee</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $deliveryman->orders ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><a href="<?php echo e(route('admin.orders.show', $order->id)); ?>" style="font-weight:700;color:#FF8A00;">#<?php echo e($order->order_number); ?></a></td>
                    <td><span class="badge badge-info"><?php echo e($order->module_slug); ?></span></td>
                    <td style="font-weight:700;">$<?php echo e(number_format($order->total_amount, 2)); ?></td>
                    <td>$<?php echo e(number_format($order->delivery_fee, 2)); ?></td>
                    <td>
                        <?php $osc = ['delivered'=>'badge-success','pending'=>'badge-warning','cancelled'=>'badge-danger'][$order->status] ?? 'badge-secondary'; ?>
                        <span class="badge <?php echo e($osc); ?>"><?php echo e(ucfirst($order->status)); ?></span>
                    </td>
                    <td style="font-size:12px;color:#8A8A9A;"><?php echo e($order->created_at->format('d M Y')); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="6" style="text-align:center;padding:20px;color:#8A8A9A;">No deliveries yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/deliverymen/show.blade.php ENDPATH**/ ?>