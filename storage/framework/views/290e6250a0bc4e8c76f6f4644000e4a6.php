<?php $__env->startSection('title', 'Finance'); ?>
<?php $__env->startSection('content'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Finance & Payouts</h1>
        <ul class="breadcrumb">
            <li><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li>
            <li>Finance</li>
        </ul>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="<?php echo e(route('admin.finance.transactions')); ?>" class="btn btn-outline btn-sm"><i class="fas fa-list"></i> Transactions</a>
        <a href="<?php echo e(route('admin.finance.commissions')); ?>"  class="btn btn-outline btn-sm"><i class="fas fa-percent"></i> Commissions</a>
    </div>
</div>


<div class="stats-grid">
    <div class="stat-card orange">
        <div class="stat-icon-wrap orange"><i class="fas fa-dollar-sign"></i></div>
        <div class="stat-body">
            <div class="stat-value">$<?php echo e(number_format($stats['total_revenue'],0)); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon-wrap red"><i class="fas fa-clock"></i></div>
        <div class="stat-body">
            <div class="stat-value">$<?php echo e(number_format($stats['pending_withdrawals'],0)); ?></div>
            <div class="stat-label">Pending Withdrawals</div>
            <div class="stat-sub warn"><i class="fas fa-exclamation-circle"></i> Requires attention</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon-wrap green"><i class="fas fa-paper-plane"></i></div>
        <div class="stat-body">
            <div class="stat-value">$<?php echo e(number_format($stats['total_payouts'],0)); ?></div>
            <div class="stat-label">Total Payouts</div>
        </div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon-wrap purple"><i class="fas fa-wallet"></i></div>
        <div class="stat-body">
            <div class="stat-value">$<?php echo e(number_format($stats['wallet_balances'],0)); ?></div>
            <div class="stat-label">Wallet Balances</div>
        </div>
    </div>
</div>


<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(239,68,68,0.1);color:var(--danger);">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            Pending Withdrawal Requests
            <span class="badge badge-danger" style="margin-left:4px;"><?php echo e($pendingWithdrawals->total()); ?></span>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Owner</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Account Details</th>
                    <th>Requested</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $pendingWithdrawals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $w): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td>
                        <div style="font-weight:700;font-size:13px;"><?php echo e(class_basename($w->owner_type)); ?></div>
                        <div style="font-size:11px;color:var(--text-muted);">ID: <?php echo e($w->owner_id); ?></div>
                    </td>
                    <td><span style="font-size:15px;font-weight:800;color:var(--success);">$<?php echo e(number_format($w->amount,2)); ?></span></td>
                    <td><span class="badge badge-info"><?php echo e(ucwords(str_replace('_',' ',$w->payment_method))); ?></span></td>
                    <td>
                        <div style="font-weight:600;font-size:13px;"><?php echo e($w->account_name); ?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?php echo e($w->account_number); ?></div>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);"><?php echo e($w->created_at->format('d M Y H:i')); ?></td>
                    <td>
                        <div style="display:flex;gap:5px;">
                            <button class="btn btn-xs btn-success" onclick="openModal('approveModal<?php echo e($w->id); ?>')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <form method="POST" action="<?php echo e(route('admin.finance.withdrawals.reject',$w)); ?>">
                                <?php echo csrf_field(); ?>
                                <button class="btn btn-xs" style="background:#fff5f5;color:var(--danger);border:1.5px solid #fecaca;">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                        </div>

                        
                        <div class="modal-overlay" id="approveModal<?php echo e($w->id); ?>">
                            <div class="modal-box">
                                <div class="modal-header">
                                    <div class="modal-title">Approve Withdrawal — $<?php echo e(number_format($w->amount,2)); ?></div>
                                    <button class="modal-close" onclick="closeModal('approveModal<?php echo e($w->id); ?>')"><i class="fas fa-times"></i></button>
                                </div>
                                <form method="POST" action="<?php echo e(route('admin.finance.withdrawals.approve',$w)); ?>">
                                    <?php echo csrf_field(); ?>
                                    <div class="modal-body">
                                        <div style="background:rgba(16,185,129,0.08);border-radius:10px;padding:14px;margin-bottom:16px;">
                                            <div style="display:flex;justify-content:space-between;font-size:13px;">
                                                <span style="color:var(--text-muted);">Owner</span>
                                                <span style="font-weight:700;"><?php echo e(class_basename($w->owner_type)); ?> #<?php echo e($w->owner_id); ?></span>
                                            </div>
                                            <div style="display:flex;justify-content:space-between;font-size:13px;margin-top:6px;">
                                                <span style="color:var(--text-muted);">Amount</span>
                                                <span style="font-weight:800;color:var(--success);font-size:16px;">$<?php echo e(number_format($w->amount,2)); ?></span>
                                            </div>
                                            <div style="display:flex;justify-content:space-between;font-size:13px;margin-top:6px;">
                                                <span style="color:var(--text-muted);">Method</span>
                                                <span style="font-weight:600;"><?php echo e($w->payment_method); ?></span>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Transaction Reference <span style="color:var(--danger);">*</span></label>
                                            <input type="text" name="transaction_reference" class="form-control" required placeholder="Enter reference number…">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('approveModal<?php echo e($w->id); ?>')">Cancel</button>
                                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Confirm Approval</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-check-circle" style="color:var(--success);"></i>
                            <h3>No pending withdrawals</h3>
                            <p>All withdrawal requests have been processed</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($pendingWithdrawals->hasPages()): ?>
    <div class="card-footer" style="display:flex;justify-content:center;">
        <?php echo e($pendingWithdrawals->links()); ?>

    </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/finance/index.blade.php ENDPATH**/ ?>