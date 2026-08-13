<?php $__env->startSection('title', 'Withdrawal Requests'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Withdrawal Requests</h1>
        <ul class="breadcrumb"><li><a href="<?php echo e(route('admin.wallet.index')); ?>">Wallets</a></li><li><span>Withdrawals</span></li></ul>
    </div>
</div>

<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>
<?php if(session('error')): ?><div class="alert alert-danger"><?php echo e(session('error')); ?></div><?php endif; ?>

<div style="background:#fff;border-radius:14px;border:1.5px solid #f0f1f5;overflow:hidden">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
            <tr style="background:#f8f9fa;font-size:11px;color:#8A8A9A;font-weight:700;text-transform:uppercase">
                <th style="padding:12px 16px;text-align:left">User</th>
                <th style="padding:12px 16px;text-align:right">Amount</th>
                <th style="padding:12px 16px;text-align:left">Method</th>
                <th style="padding:12px 16px;text-align:left">Account</th>
                <th style="padding:12px 16px;text-align:center">Status</th>
                <th style="padding:12px 16px;text-align:center">Date</th>
                <th style="padding:12px 16px;text-align:center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $req): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr style="border-top:1px solid #f0f1f5">
                <td style="padding:12px 16px">
                    <div style="font-weight:700;color:#07003B"><?php echo e($req->user_name); ?></div>
                    <div style="font-size:11px;color:#8A8A9A"><?php echo e($req->user_phone); ?></div>
                </td>
                <td style="padding:12px 16px;text-align:right;font-weight:800;color:#E74C3C">$<?php echo e(number_format($req->amount, 2)); ?></td>
                <td style="padding:12px 16px"><?php echo e(strtoupper($req->payment_method)); ?></td>
                <td style="padding:12px 16px">
                    <div><?php echo e($req->account_name); ?></div>
                    <div style="font-size:11px;color:#8A8A9A"><?php echo e($req->account_number); ?></div>
                </td>
                <td style="padding:12px 16px;text-align:center">
                    <?php $colors = ['pending'=>['#FFF8E1','#F57F17'],'approved'=>['#E8F5E9','#27AE60'],'rejected'=>['#FFEBEE','#E74C3C'],'processed'=>['#E3F2FD','#1565C0']] ?>
                    <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:<?php echo e($colors[$req->status][0] ?? '#f0f0f0'); ?>;color:<?php echo e($colors[$req->status][1] ?? '#555'); ?>">
                        <?php echo e(strtoupper($req->status)); ?>

                    </span>
                </td>
                <td style="padding:12px 16px;text-align:center;color:#8A8A9A"><?php echo e(\Carbon\Carbon::parse($req->created_at)->format('M d')); ?></td>
                <td style="padding:12px 16px;text-align:center">
                    <?php if($req->status === 'pending'): ?>
                    <div style="display:flex;gap:6px;justify-content:center">
                        <form method="POST" action="<?php echo e(route('admin.wallet.withdrawal.approve', $req->id)); ?>">
                            <?php echo csrf_field(); ?>
                            <button class="btn btn-sm btn-success" onclick="return confirm('Approve this withdrawal?')"><i class="fas fa-check"></i></button>
                        </form>
                        <form method="POST" action="<?php echo e(route('admin.wallet.withdrawal.reject', $req->id)); ?>">
                            <?php echo csrf_field(); ?>
                            <button class="btn btn-sm btn-danger" onclick="return confirm('Reject and refund this withdrawal?')"><i class="fas fa-times"></i></button>
                        </form>
                    </div>
                    <?php else: ?> —
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <div style="padding:16px"><?php echo e($requests->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/wallet/withdrawals.blade.php ENDPATH**/ ?>