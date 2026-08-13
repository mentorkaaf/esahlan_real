<?php $__env->startSection('title', 'Wallet Management'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Wallet Management</h1>
        <ul class="breadcrumb"><li><span>Finance</span></li><li><span>Wallets</span></li></ul>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="<?php echo e(route('admin.wallet.transactions')); ?>" class="btn btn-outline-primary"><i class="fas fa-list"></i> All Transactions</a>
        <a href="<?php echo e(route('admin.wallet.withdrawals')); ?>" class="btn btn-outline-warning"><i class="fas fa-money-bill-wave"></i> Withdrawals</a>
        <button class="btn btn-danger" onclick="document.getElementById('bulkResetModal').style.display='flex'"><i class="fas fa-undo"></i> Reset All Wallets</button>
        <a href="<?php echo e(route('admin.wallet.settings')); ?>" class="btn btn-outline-secondary"><i class="fas fa-cog"></i> Payment Settings</a>
    </div>
</div>

<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>
<?php if(session('error')): ?><div class="alert alert-danger"><?php echo e(session('error')); ?></div><?php endif; ?>


<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
    <div class="stat-card" style="background:#fff;border:1.5px solid #f0f1f5;border-radius:14px;padding:20px">
        <div style="font-size:12px;color:#8A8A9A;margin-bottom:6px">Total Balance (All Users)</div>
        <div style="font-size:26px;font-weight:900;color:#07003B">$<?php echo e(number_format($stats['total_balance'],2)); ?></div>
    </div>
    <div class="stat-card" style="background:#fff;border:1.5px solid #f0f1f5;border-radius:14px;padding:20px">
        <div style="font-size:12px;color:#8A8A9A;margin-bottom:6px">Total Topups (Waafi Pay)</div>
        <div style="font-size:26px;font-weight:900;color:#27AE60">$<?php echo e(number_format($stats['total_topups'],2)); ?></div>
    </div>
    <div class="stat-card" style="background:#fff;border:1.5px solid #f0f1f5;border-radius:14px;padding:20px">
        <div style="font-size:12px;color:#8A8A9A;margin-bottom:6px">Pending Withdrawals</div>
        <div style="font-size:26px;font-weight:900;color:#E74C3C">$<?php echo e(number_format($stats['pending_withdrawals'],2)); ?></div>
    </div>
    <div class="stat-card" style="background:#fff;border:1.5px solid #f0f1f5;border-radius:14px;padding:20px">
        <div style="font-size:12px;color:#8A8A9A;margin-bottom:6px">Users with Wallets</div>
        <div style="font-size:26px;font-weight:900;color:#1565C0"><?php echo e(number_format($stats['user_count'])); ?></div>
    </div>
</div>


<div style="background:#fff;border-radius:14px;border:1.5px solid #f0f1f5;padding:20px;margin-bottom:24px">
    <h3 style="margin:0 0 16px;font-size:16px;font-weight:800;color:#07003B"><i class="fas fa-plus-circle" style="color:#FF8A00;margin-right:8px"></i>Manual Wallet Credit</h3>
    <form method="POST" action="<?php echo e(route('admin.wallet.credit')); ?>" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <?php echo csrf_field(); ?>
        <div style="flex:2;min-width:200px">
            <label style="font-size:12px;font-weight:600;color:#8A8A9A;display:block;margin-bottom:4px">User</label>
            <select name="user_id" required style="width:100%;padding:10px;border:1.5px solid #f0f1f5;border-radius:8px;font-size:14px">
                <option value="">Select user...</option>
                <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if($u->wallet_id): ?>
                    <option value="<?php echo e($u->id); ?>"><?php echo e($u->name); ?> (<?php echo e($u->email); ?>) — $<?php echo e(number_format($u->balance,2)); ?></option>
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <div style="flex:1;min-width:120px">
            <label style="font-size:12px;font-weight:600;color:#8A8A9A;display:block;margin-bottom:4px">Amount ($)</label>
            <input type="number" name="amount" min="0.01" step="0.01" required placeholder="0.00" style="width:100%;padding:10px;border:1.5px solid #f0f1f5;border-radius:8px;font-size:14px">
        </div>
        <div style="flex:2;min-width:200px">
            <label style="font-size:12px;font-weight:600;color:#8A8A9A;display:block;margin-bottom:4px">Note / Reason</label>
            <input type="text" name="note" required placeholder="e.g. Refund for order #123" style="width:100%;padding:10px;border:1.5px solid #f0f1f5;border-radius:8px;font-size:14px">
        </div>
        <button type="submit" class="btn btn-primary" style="height:42px;padding:0 20px;white-space:nowrap"><i class="fas fa-plus"></i> Add Credit</button>
    </form>
</div>


<div style="background:#fff;border-radius:14px;border:1.5px solid #f0f1f5;overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #f0f1f5;font-weight:800;font-size:15px;color:#07003B">
        User Wallets <span style="font-size:12px;font-weight:400;color:#8A8A9A;margin-left:8px"><?php echo e($users->total()); ?> users</span>
    </div>
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="background:#f8f9fa;font-size:12px;color:#8A8A9A;font-weight:700;text-transform:uppercase">
                <th style="padding:12px 16px;text-align:left">User</th>
                <th style="padding:12px 16px;text-align:right">Balance</th>
                <th style="padding:12px 16px;text-align:right">Total Earned</th>
                <th style="padding:12px 16px;text-align:right">Withdrawn</th>
                <th style="padding:12px 16px;text-align:center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr style="border-top:1px solid #f0f1f5;font-size:13px">
                <td style="padding:12px 16px">
                    <div style="font-weight:700;color:#07003B"><?php echo e($u->name); ?></div>
                    <div style="font-size:11px;color:#8A8A9A"><?php echo e($u->email); ?> · <?php echo e($u->phone); ?></div>
                </td>
                <td style="padding:12px 16px;text-align:right;font-weight:800;color:<?php echo e($u->balance > 0 ? '#27AE60' : '#8A8A9A'); ?>">$<?php echo e(number_format($u->balance ?? 0, 2)); ?></td>
                <td style="padding:12px 16px;text-align:right;color:#1565C0">$<?php echo e(number_format($u->total_earned ?? 0, 2)); ?></td>
                <td style="padding:12px 16px;text-align:right;color:#E74C3C">$<?php echo e(number_format($u->total_withdrawn ?? 0, 2)); ?></td>
                <td style="padding:12px 16px;text-align:center">
                    <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap">
                        <?php if($u->wallet_id): ?>
                        <a href="<?php echo e(route('admin.wallet.transactions', ['user_id' => $u->id])); ?>"
                           class="btn btn-sm btn-outline-primary" style="font-size:11px;padding:4px 10px" title="View Transactions">
                            <i class="fas fa-list"></i>
                        </a>
                        <?php if($u->balance > 0): ?>
                        <button class="btn btn-sm btn-outline-danger" style="font-size:11px;padding:4px 10px" title="Reset Wallet to $0"
                                onclick="openResetWallet(<?php echo e($u->id); ?>, '<?php echo e(addslashes($u->name)); ?>', <?php echo e(number_format($u->balance, 2, '.', '')); ?>)">
                            <i class="fas fa-undo"></i>
                        </button>
                        <?php endif; ?>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-warning" style="font-size:11px;padding:4px 10px" title="Reset PIN"
                                onclick="openResetPin(<?php echo e($u->id); ?>, '<?php echo e(addslashes($u->name)); ?>')">
                            <i class="fas fa-key"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <div style="padding:16px"><?php echo e($users->links()); ?></div>
</div>


<div id="bulkResetModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:28px;max-width:440px;width:90%;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;">
            <div style="width:44px;height:44px;background:#fce4ec;border-radius:12px;display:flex;align-items:center;justify-content:center">
                <i class="fas fa-exclamation-triangle" style="color:#E74C3C;font-size:20px"></i>
            </div>
            <div>
                <div style="font-weight:800;font-size:16px;color:#07003B">Reset All Wallets</div>
                <div style="font-size:12px;color:#8A8A9A">This will set ALL user balances to $0.00</div>
            </div>
        </div>
        <div style="background:#fce4ec;border-radius:10px;padding:14px;margin-bottom:18px;font-size:13px;color:#c62828;">
            <i class="fas fa-warning" style="margin-right:6px"></i>
            <strong>Warning:</strong> This action cannot be undone. All user wallet balances will be debited to zero. Transaction records will be kept.
        </div>
        <form action="<?php echo e(route('admin.wallet.bulk-reset')); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="document.getElementById('bulkResetModal').style.display='none'"
                        style="flex:1;padding:12px;border:1.5px solid #e0e0e0;border-radius:10px;background:#fff;font-weight:700;cursor:pointer">Cancel</button>
                <button type="submit"
                        style="flex:1;padding:12px;border:none;border-radius:10px;background:#E74C3C;color:#fff;font-weight:700;cursor:pointer">Reset All Wallets</button>
            </div>
        </form>
    </div>
</div>


<div id="resetWalletModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:28px;max-width:420px;width:90%;">
        <div style="font-weight:800;font-size:16px;color:#07003B;margin-bottom:6px">Reset Wallet</div>
        <div id="resetWalletMsg" style="font-size:13px;color:#8A8A9A;margin-bottom:18px"></div>
        <form id="resetWalletForm" method="POST">
            <?php echo csrf_field(); ?>
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="document.getElementById('resetWalletModal').style.display='none'"
                        style="flex:1;padding:12px;border:1.5px solid #e0e0e0;border-radius:10px;background:#fff;font-weight:700;cursor:pointer">Cancel</button>
                <button type="submit"
                        style="flex:1;padding:12px;border:none;border-radius:10px;background:#E74C3C;color:#fff;font-weight:700;cursor:pointer">Reset to $0.00</button>
            </div>
        </form>
    </div>
</div>


<div id="resetPinModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:28px;max-width:420px;width:90%;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;">
            <div style="width:44px;height:44px;background:#fff3e0;border-radius:12px;display:flex;align-items:center;justify-content:center">
                <i class="fas fa-key" style="color:#FF8A00;font-size:18px"></i>
            </div>
            <div>
                <div style="font-weight:800;font-size:16px;color:#07003B">Reset User PIN</div>
                <div id="resetPinUser" style="font-size:12px;color:#8A8A9A"></div>
            </div>
        </div>
        <form id="resetPinForm" method="POST">
            <?php echo csrf_field(); ?>
            <div style="margin-bottom:16px">
                <label style="font-size:12px;font-weight:600;color:#8A8A9A;display:block;margin-bottom:6px">New 4-digit PIN</label>
                <input type="text" name="pin" required pattern="\d{4}" maxlength="4" placeholder="e.g. 1234"
                       style="width:100%;padding:12px;border:1.5px solid #f0f1f5;border-radius:10px;font-size:18px;font-weight:800;letter-spacing:8px;text-align:center;">
            </div>
            <div style="background:#fff3e0;border-radius:10px;padding:12px;margin-bottom:16px;font-size:12px;color:#e65100;">
                <i class="fas fa-info-circle" style="margin-right:6px"></i>
                User will be logged out of all devices and must log in with the new PIN.
            </div>
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="document.getElementById('resetPinModal').style.display='none'"
                        style="flex:1;padding:12px;border:1.5px solid #e0e0e0;border-radius:10px;background:#fff;font-weight:700;cursor:pointer">Cancel</button>
                <button type="submit"
                        style="flex:1;padding:12px;border:none;border-radius:10px;background:#FF8A00;color:#fff;font-weight:700;cursor:pointer">Set New PIN</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResetWallet(userId, name, balance) {
    document.getElementById('resetWalletForm').action = '/admin/wallet/reset/' + userId;
    document.getElementById('resetWalletMsg').textContent = 'Reset ' + name + '\'s wallet ($' + balance + ') to $0.00?';
    document.getElementById('resetWalletModal').style.display = 'flex';
}
function openResetPin(userId, name) {
    document.getElementById('resetPinForm').action = '/admin/wallet/reset-pin/' + userId;
    document.getElementById('resetPinUser').textContent = name;
    document.getElementById('resetPinModal').style.display = 'flex';
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/wallet/index.blade.php ENDPATH**/ ?>