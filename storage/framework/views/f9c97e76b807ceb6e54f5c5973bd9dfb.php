<?php $__env->startSection('title', 'Exchange Orders'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Exchange Orders</h1>
        <ul class="breadcrumb"><li><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li><li>eExchange</li></ul>
    </div>
</div>


<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">
    <div class="stat-card blue">
        <div class="stat-icon-wrap blue"><i class="fas fa-exchange-alt"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?php echo e(number_format($stats['total'])); ?></div>
            <div class="stat-label">Total Exchanges</div>
            <div class="stat-sub up"><i class="fas fa-arrow-up"></i> <?php echo e($stats['today']); ?> today</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon-wrap green"><i class="fas fa-dollar-sign"></i></div>
        <div class="stat-body">
            <div class="stat-value">$<?php echo e(number_format($stats['volume'], 2)); ?></div>
            <div class="stat-label">Total Volume</div>
            <div class="stat-sub muted">Completed orders</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon-wrap orange"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="stat-body">
            <div class="stat-value">$<?php echo e(number_format($stats['fees'], 2)); ?></div>
            <div class="stat-label">Fees Collected</div>
            <div class="stat-sub muted">Service fee (1%)</div>
        </div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon-wrap purple"><i class="fas fa-calendar-day"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?php echo e($stats['today']); ?></div>
            <div class="stat-label">Today's Exchanges</div>
            <div class="stat-sub muted"><i class="fas fa-clock"></i> Last 24h</div>
        </div>
    </div>
</div>


<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:14px 18px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <div style="flex:1;min-width:160px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="Reference, phone, user..." class="form-control form-control-sm">
            </div>
            <div style="min-width:130px;">
                <label class="form-label">From Wallet</label>
                <select name="from_wallet" class="form-control form-control-sm">
                    <option value="">All</option>
                    <?php $__currentLoopData = ['EVC','EDAHAB','JEEP','PREMIER']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $w): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($w); ?>" <?php echo e(request('from_wallet')==$w?'selected':''); ?>><?php echo e($w); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div style="min-width:130px;">
                <label class="form-label">To Wallet</label>
                <select name="to_wallet" class="form-control form-control-sm">
                    <option value="">All</option>
                    <?php $__currentLoopData = ['EVC','EDAHAB','JEEP','PREMIER']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $w): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($w); ?>" <?php echo e(request('to_wallet')==$w?'selected':''); ?>><?php echo e($w); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div style="min-width:120px;">
                <label class="form-label">Status</label>
                <select name="status" class="form-control form-control-sm">
                    <option value="">All</option>
                    <option value="completed"  <?php echo e(request('status')=='completed'?'selected':''); ?>>Completed</option>
                    <option value="pending"    <?php echo e(request('status')=='pending'?'selected':''); ?>>Pending</option>
                    <option value="processing" <?php echo e(request('status')=='processing'?'selected':''); ?>>Processing</option>
                    <option value="failed"     <?php echo e(request('status')=='failed'?'selected':''); ?>>Failed</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
                <a href="<?php echo e(route('admin.exchange.index')); ?>" class="btn btn-outline btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>


<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:#3b82f6;"><i class="fas fa-exchange-alt"></i></div>
            Exchange Orders
        </div>
        <span style="font-size:12px;color:var(--text-muted);"><?php echo e($orders->total()); ?> records</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>User</th>
                    <th>Exchange</th>
                    <th>Sent</th>
                    <th>Fee</th>
                    <th>Received</th>
                    <th>Recipient Phone</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php
                $statusMap = [
                    'completed'  => ['badge-success','check-circle'],
                    'pending'    => ['badge-warning','clock'],
                    'processing' => ['badge-info','spinner'],
                    'failed'     => ['badge-danger','times-circle'],
                ];
                [$badge, $icon] = $statusMap[$order->status] ?? ['badge-secondary','circle'];
                $walletColors = ['EVC'=>'#e74c3c','EDAHAB'=>'#27ae60','JEEP'=>'#2980b9','PREMIER'=>'#8e44ad'];
                $fromColor = $walletColors[$order->from_wallet] ?? '#64748b';
                $toColor   = $walletColors[$order->to_wallet]   ?? '#64748b';
                ?>
                <tr>
                    <td>
                        <span style="font-size:12px;font-weight:700;color:var(--navy);font-family:monospace;"><?php echo e($order->reference); ?></span>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="avatar avatar-sm avatar-orange"><?php echo e(strtoupper(substr($order->user_name ?? 'U',0,1))); ?></div>
                            <div>
                                <div style="font-weight:600;font-size:13px;"><?php echo e($order->user_name); ?></div>
                                <div style="font-size:11px;color:var(--text-muted);"><?php echo e($order->user_phone); ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span style="font-size:11px;font-weight:800;color:<?php echo e($fromColor); ?>;background:<?php echo e($fromColor); ?>18;padding:2px 8px;border-radius:5px;"><?php echo e($order->from_wallet); ?></span>
                            <i class="fas fa-arrow-right" style="color:var(--text-muted);font-size:10px;"></i>
                            <span style="font-size:11px;font-weight:800;color:<?php echo e($toColor); ?>;background:<?php echo e($toColor); ?>18;padding:2px 8px;border-radius:5px;"><?php echo e($order->to_wallet); ?></span>
                        </div>
                    </td>
                    <td><span style="font-weight:700;">$<?php echo e(number_format($order->sent_amount,2)); ?></span></td>
                    <td><span style="color:#ef4444;font-weight:600;font-size:12px;">-$<?php echo e(number_format($order->fee_amount,2)); ?></span></td>
                    <td><span style="font-weight:800;color:#10b981;">$<?php echo e(number_format($order->converted_amount,2)); ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <i class="fas fa-phone" style="color:var(--text-muted);font-size:11px;"></i>
                            <span style="font-weight:600;font-size:13px;"><?php echo e($order->recipient_phone); ?></span>
                        </div>
                    </td>
                    <td><span class="badge <?php echo e($badge); ?>"><i class="fas fa-<?php echo e($icon); ?>"></i> <?php echo e(ucfirst($order->status)); ?></span></td>
                    <td style="color:var(--text-muted);font-size:12px;"><?php echo e(\Carbon\Carbon::parse($order->created_at)->format('d M Y H:i')); ?></td>
                    <td>
                        <a href="<?php echo e(route('admin.exchange.show', $order->id)); ?>" class="btn btn-ghost btn-xs">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="10"><div class="empty-state" style="padding:40px;"><i class="fas fa-exchange-alt"></i><p>No exchange orders yet</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($orders->hasPages()): ?>
    <div class="card-body" style="padding:14px 20px;">
        <?php echo e($orders->links()); ?>

    </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/exchange/index.blade.php ENDPATH**/ ?>