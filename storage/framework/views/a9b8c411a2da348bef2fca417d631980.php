<?php $__env->startSection('title', 'Wallet Transactions'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Wallet Transactions</h1>
        <ul class="breadcrumb">
            <li><a href="<?php echo e(route('admin.wallet.index')); ?>">Wallets</a></li>
            <?php if($filterUser): ?><li><a href="<?php echo e(route('admin.wallet.transactions')); ?>">All Transactions</a></li><li><span><?php echo e($filterUser->name); ?></span></li>
            <?php else: ?><li><span>Transactions</span></li><?php endif; ?>
        </ul>
    </div>
    <form method="GET" style="display:flex;gap:8px;align-items:center">
        <?php if(request('user_id')): ?><input type="hidden" name="user_id" value="<?php echo e(request('user_id')); ?>"><?php endif; ?>
        <input name="search" value="<?php echo e(request('search')); ?>" placeholder="Search user or note..." style="padding:8px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:13px;width:220px">
        <select name="type" style="padding:8px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:13px">
            <option value="">All types</option>
            <option value="credit" <?php if(request('type')=='credit'): echo 'selected'; endif; ?>>Credit</option>
            <option value="debit" <?php if(request('type')=='debit'): echo 'selected'; endif; ?>>Debit</option>
        </select>
        <button class="btn btn-primary" style="padding:8px 16px">Filter</button>
        <?php if(request('user_id')): ?>
        <a href="<?php echo e(route('admin.wallet.transactions')); ?>" class="btn btn-outline-secondary" style="padding:8px 16px;white-space:nowrap">Clear Filter</a>
        <?php endif; ?>
    </form>
</div>

<?php if($filterUser): ?>
<div style="background:#EFF6FF;border:1.5px solid #BFDBFE;border-radius:10px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px">
    <i class="fas fa-user-circle" style="color:#1565C0;font-size:18px"></i>
    <div>
        <div style="font-weight:800;color:#1565C0;font-size:14px"><?php echo e($filterUser->name); ?></div>
        <div style="font-size:12px;color:#555"><?php echo e($filterUser->email); ?> · <?php echo e($filterUser->phone ?? ''); ?> — Showing all transactions for this user</div>
    </div>
</div>
<?php endif; ?>

<div style="background:#fff;border-radius:14px;border:1.5px solid #f0f1f5;overflow:hidden">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
            <tr style="background:#f8f9fa;font-size:11px;color:#8A8A9A;font-weight:700;text-transform:uppercase">
                <th style="padding:12px 16px;text-align:left">User</th>
                <th style="padding:12px 16px;text-align:left">Note</th>
                <th style="padding:12px 16px;text-align:center">Type</th>
                <th style="padding:12px 16px;text-align:right">Amount</th>
                <th style="padding:12px 16px;text-align:right">Balance After</th>
                <th style="padding:12px 16px;text-align:right">Date</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr style="border-top:1px solid #f0f1f5">
                <td style="padding:12px 16px">
                    <div style="font-weight:700;color:#07003B"><?php echo e($tx->user_name); ?></div>
                    <div style="font-size:11px;color:#8A8A9A"><?php echo e($tx->user_email); ?></div>
                </td>
                <td style="padding:12px 16px;color:#555;max-width:240px"><?php echo e($tx->note); ?></td>
                <td style="padding:12px 16px;text-align:center">
                    <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;
                        background:<?php echo e($tx->type=='credit' ? '#E8F5E9' : '#FFEBEE'); ?>;
                        color:<?php echo e($tx->type=='credit' ? '#27AE60' : '#E74C3C'); ?>">
                        <?php echo e(strtoupper($tx->type)); ?>

                    </span>
                </td>
                <td style="padding:12px 16px;text-align:right;font-weight:800;color:<?php echo e($tx->type=='credit' ? '#27AE60' : '#E74C3C'); ?>">
                    <?php echo e($tx->type=='credit' ? '+' : '-'); ?>$<?php echo e(number_format($tx->amount, 2)); ?>

                </td>
                <td style="padding:12px 16px;text-align:right;color:#1565C0;font-weight:700">$<?php echo e(number_format($tx->balance_after ?? 0, 2)); ?></td>
                <td style="padding:12px 16px;text-align:right;color:#8A8A9A"><?php echo e(\Carbon\Carbon::parse($tx->created_at)->format('M d, Y H:i')); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <div style="padding:16px"><?php echo e($transactions->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/wallet/transactions.blade.php ENDPATH**/ ?>