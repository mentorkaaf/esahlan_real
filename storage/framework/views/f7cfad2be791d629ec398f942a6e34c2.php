<?php $__env->startSection('title', 'Driver Earnings'); ?>
<?php $__env->startSection('content'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Driver Earnings</h1>
        <ul class="breadcrumb">
            <li><a href="<?php echo e(route('admin.deliverymen.index')); ?>">Deliverymen</a></li>
            <li>Earnings</li>
        </ul>
    </div>
    <div style="display:flex;gap:8px;">
        <form action="<?php echo e(route('admin.deliverymen.earnings.bulk-reset')); ?>" method="POST" onsubmit="return confirm('Reset ALL driver earnings? This cannot be undone.')">
            <?php echo csrf_field(); ?>
            <button class="btn btn-danger btn-sm"><i class="fas fa-undo"></i> Reset All</button>
        </form>
        <a href="<?php echo e(route('admin.deliverymen.index')); ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>


<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
    <div style="background:#fff;border-radius:14px;padding:20px;border-left:5px solid #10B981;">
        <div style="font-size:11px;color:#8A8A9A;margin-bottom:6px;">Total Earnings</div>
        <div style="font-size:28px;font-weight:900;color:#10B981;">$<?php echo e(number_format($summary['total'], 2)); ?></div>
    </div>
    <div style="background:#fff;border-radius:14px;padding:20px;border-left:5px solid #FF8A00;">
        <div style="font-size:11px;color:#8A8A9A;margin-bottom:6px;">Today</div>
        <div style="font-size:28px;font-weight:900;color:#FF8A00;">$<?php echo e(number_format($summary['today'], 2)); ?></div>
    </div>
    <div style="background:#fff;border-radius:14px;padding:20px;border-left:5px solid #3B82F6;">
        <div style="font-size:11px;color:#8A8A9A;margin-bottom:6px;">This Month</div>
        <div style="font-size:28px;font-weight:900;color:#3B82F6;">$<?php echo e(number_format($summary['this_month'], 2)); ?></div>
    </div>
    <div style="background:#fff;border-radius:14px;padding:20px;border-left:5px solid #8B5CF6;">
        <div style="font-size:11px;color:#8A8A9A;margin-bottom:6px;">Total Deliveries</div>
        <div style="font-size:28px;font-weight:900;color:#8B5CF6;"><?php echo e(number_format($summary['total_count'])); ?></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start;">
    <div>
        
        <div class="card" style="padding:12px 16px;margin-bottom:12px;">
            <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <select name="driver_id" class="form-control" style="width:200px;">
                    <option value="">All Drivers</option>
                    <?php $__currentLoopData = $drivers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($d->id); ?>" <?php echo e(request('driver_id') == $d->id ? 'selected' : ''); ?>><?php echo e($d->user?->name ?? 'Driver #'.$d->id); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <input type="date" name="from" class="form-control" style="width:150px;" value="<?php echo e(request('from')); ?>">
                <span style="color:#8A8A9A;">to</span>
                <input type="date" name="to" class="form-control" style="width:150px;" value="<?php echo e(request('to')); ?>">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                <?php if(request()->hasAny(['driver_id','from','to'])): ?>
                <a href="<?php echo e(route('admin.deliverymen.earnings')); ?>" class="btn btn-outline btn-sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        
        <div class="card">
            <div class="card-header">
                <div class="card-header-title"><div class="card-header-icon" style="background:rgba(16,185,129,0.1);color:#10B981;"><i class="fas fa-coins"></i></div> Delivery Earnings</div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>Order</th>
                            <th>Module</th>
                            <th>Type</th>
                            <th style="text-align:right">Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $earnings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php $vEmoji = ['motorcycle'=>'🏍️','bajaj'=>'🛺','car'=>'🚗','van'=>'🚐','truck'=>'🚛','bicycle'=>'🚲'][$e->vehicle_type] ?? '🚗'; ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span><?php echo e($vEmoji); ?></span>
                                    <div>
                                        <div style="font-weight:700;font-size:13px;"><?php echo e($e->driver_name); ?></div>
                                        <div style="font-size:11px;color:#8A8A9A;"><?php echo e($e->driver_phone); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if($e->order_number): ?>
                                <a href="<?php echo e(route('admin.orders.show', $e->order_id)); ?>" style="font-weight:600;color:#FF8A00;">#<?php echo e($e->order_number); ?></a>
                                <?php else: ?>
                                <span style="color:#8A8A9A;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($e->module_slug): ?>
                                <span class="badge badge-info"><?php echo e($e->module_slug); ?></span>
                                <?php else: ?>
                                <span style="color:#8A8A9A;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $tc = ['delivery_fee'=>'badge-success','bonus'=>'badge-warning','incentive'=>'badge-info','penalty'=>'badge-danger'][$e->type] ?? 'badge-secondary'; ?>
                                <span class="badge <?php echo e($tc); ?>"><?php echo e(ucfirst(str_replace('_',' ',$e->type))); ?></span>
                            </td>
                            <td style="text-align:right;font-weight:800;color:#10B981;font-size:14px;">$<?php echo e(number_format($e->amount, 2)); ?></td>
                            <td style="font-size:12px;color:#8A8A9A;"><?php echo e(\Carbon\Carbon::parse($e->created_at)->format('d M Y, H:i')); ?></td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="6" style="text-align:center;padding:30px;color:#8A8A9A;">No earnings found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if($earnings->hasPages()): ?>
            <div style="padding:16px;display:flex;justify-content:center;"><?php echo e($earnings->links()); ?></div>
            <?php endif; ?>
        </div>
    </div>

    
    <div class="card">
        <div class="card-header">
            <div class="card-header-title"><div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:#FF8A00;"><i class="fas fa-trophy"></i></div> Top Drivers</div>
        </div>
        <div style="display:flex;flex-direction:column;">
            <?php $__empty_1 = true; $__currentLoopData = $perDriver; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $pd): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php $vEmoji = ['motorcycle'=>'🏍️','bajaj'=>'🛺','car'=>'🚗','van'=>'🚐','truck'=>'🚛','bicycle'=>'🚲'][$pd->vehicle_type] ?? '🚗'; ?>
            <div style="padding:14px 16px;border-bottom:1px solid #f0f1f5;display:flex;align-items:center;gap:12px;">
                <div style="width:28px;height:28px;border-radius:50%;background:<?php echo e($i < 3 ? '#FF8A00' : '#f0f1f5'); ?>;color:<?php echo e($i < 3 ? '#fff' : '#8A8A9A'); ?>;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;">
                    <?php echo e($i + 1); ?>

                </div>
                <div style="flex:1;">
                    <div style="font-weight:700;font-size:13px;color:#07003B;"><?php echo e($vEmoji); ?> <?php echo e($pd->name); ?></div>
                    <div style="font-size:11px;color:#8A8A9A;"><?php echo e($pd->total_deliveries); ?> deliveries</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-weight:800;color:#10B981;font-size:14px;">$<?php echo e(number_format($pd->total_earned, 2)); ?></div>
                    <form action="<?php echo e(route('admin.deliverymen.reset-earning', $pd->deliveryman_id)); ?>" method="POST" style="margin:2px 0 0;" onsubmit="return confirm('Reset earnings for <?php echo e($pd->name); ?>?')">
                        <?php echo csrf_field(); ?>
                        <button style="background:none;border:none;color:#EF4444;font-size:10px;cursor:pointer;font-weight:600;padding:0;"><i class="fas fa-undo" style="font-size:9px;"></i> Reset</button>
                    </form>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div style="padding:30px;text-align:center;color:#8A8A9A;">No data yet</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/deliverymen/earnings.blade.php ENDPATH**/ ?>