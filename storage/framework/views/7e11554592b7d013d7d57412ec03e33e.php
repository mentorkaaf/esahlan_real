<?php $__env->startSection('title', 'Transactions'); ?>
<?php $__env->startSection('content'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Wallet Transactions</h1>
        <ul class="breadcrumb">
            <li><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li>
            <li><a href="<?php echo e(route('admin.finance.index')); ?>">Finance</a></li>
            <li>Transactions</li>
        </ul>
    </div>
    <a href="<?php echo e(route('admin.finance.index')); ?>" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Finance
    </a>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;width:100%;">
            <select name="type" class="form-control" style="width:160px;" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="credit" <?php echo e(request('type')==='credit'?'selected':''); ?>>Credit</option>
                <option value="debit"  <?php echo e(request('type')==='debit' ?'selected':''); ?>>Debit</option>
            </select>
            <input type="date" name="date_from" class="form-control" value="<?php echo e(request('date_from')); ?>" style="width:160px;">
            <span style="font-size:12px;color:var(--text-muted);">to</span>
            <input type="date" name="date_to"   class="form-control" value="<?php echo e(request('date_to')); ?>"   style="width:160px;">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <?php if(request()->hasAny(['type','date_from','date_to'])): ?>
            <a href="<?php echo e(route('admin.finance.transactions')); ?>" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);">
                <i class="fas fa-exchange-alt"></i>
            </div>
            Transactions
            <span class="badge badge-info" style="margin-left:4px;"><?php echo e($transactions->total()); ?></span>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Wallet Owner</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th>Reference</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td style="font-size:12px;color:var(--text-muted);"><?php echo e($tx->id); ?></td>
                    <td>
                        <?php $owner = $tx->wallet?->owner; ?>
                        <?php if($owner): ?>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="avatar avatar-sm avatar-orange"><?php echo e(strtoupper(substr($owner->name??'U',0,1))); ?></div>
                            <div>
                                <div style="font-weight:700;font-size:13px;"><?php echo e($owner->name ?? '—'); ?></div>
                                <div style="font-size:11px;color:var(--text-muted);"><?php echo e(class_basename($tx->wallet->owner_type ?? '')); ?></div>
                            </div>
                        </div>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?php echo e($tx->type==='credit'?'badge-success':'badge-danger'); ?> badge-dot">
                            <?php echo e(ucfirst($tx->type)); ?>

                        </span>
                    </td>
                    <td>
                        <span style="font-weight:800;font-size:14px;color:<?php echo e($tx->type==='credit'?'var(--success)':'var(--danger)'); ?>;">
                            <?php echo e($tx->type==='credit'?'+':'-'); ?>$<?php echo e(number_format($tx->amount,2)); ?>

                        </span>
                    </td>
                    <td style="font-size:12.5px;max-width:220px;"><?php echo e($tx->description ?? '—'); ?></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?php echo e($tx->reference_id ?? '—'); ?></td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
                        <?php echo e(\Carbon\Carbon::parse($tx->created_at)->format('d M Y H:i')); ?>

                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-exchange-alt"></i>
                            <h3>No transactions found</h3>
                            <p>Try adjusting your filters</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($transactions->hasPages()): ?>
    <div class="card-footer" style="display:flex;justify-content:center;">
        <?php echo e($transactions->withQueryString()->links()); ?>

    </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/finance/transactions.blade.php ENDPATH**/ ?>