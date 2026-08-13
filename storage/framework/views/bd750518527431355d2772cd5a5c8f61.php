<?php $__env->startSection('title', 'eExchange — Rates Management'); ?>
<?php $__env->startSection('content'); ?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-exchange-alt" style="color:var(--primary)"></i> eExchange Rates</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li>
            <li class="breadcrumb-item active">eExchange</li>
        </ol>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')">
        <i class="fas fa-plus"></i> Add Rate
    </button>
</div>


<div class="alert alert-warning">
    <i class="fas fa-info-circle"></i>
    <strong>Fee Policy:</strong> A service fee (default 1%) is charged on every exchange. Set fee per pair below. Rates are applied as: <code>received = sent × rate × (1 - fee%/100)</code>
</div>

<div class="grid-2">
    
    <div class="card">
        <div class="card-header">Exchange Rate Matrix</div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>From</th>
                        <th>To</th>
                        <th>Rate</th>
                        <th>Fee %</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $rates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td>
                            <span class="badge badge-info"><?php echo e(strtoupper($rate->from_wallet)); ?></span>
                        </td>
                        <td>
                            <span class="badge badge-success"><?php echo e(strtoupper($rate->to_wallet)); ?></span>
                        </td>
                        <td><strong><?php echo e($rate->rate); ?></strong></td>
                        <td><?php echo e($rate->fee_percentage ?? 1); ?>%</td>
                        <td>
                            <form action="<?php echo e(route('admin.module-data.exchange.destroy', $rate->id)); ?>" method="POST" onsubmit="return confirm('Delete?')">
                                <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="5" style="text-align:center;padding:30px;color:#888;">No rates configured yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    
    <div class="card">
        <div class="card-header">Add / Update Rate</div>
        <div class="card-body">
            <form action="<?php echo e(route('admin.module-data.exchange.store')); ?>" method="POST">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label class="form-label">From Wallet *</label>
                    <select name="from_wallet" class="form-control" required>
                        <?php $__currentLoopData = $wallets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $w): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($w); ?>"><?php echo e(strtoupper($w)); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">To Wallet *</label>
                    <select name="to_wallet" class="form-control" required>
                        <?php $__currentLoopData = $wallets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $w): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($w); ?>"><?php echo e(strtoupper($w)); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Exchange Rate *</label>
                    <input type="number" name="rate" class="form-control" step="0.0001" required placeholder="e.g. 1.0000 for 1:1">
                    <small class="text-muted">How many units of TO_WALLET you get for 1 unit of FROM_WALLET</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Service Fee (%)</label>
                    <input type="number" name="fee_percentage" class="form-control" step="0.01" value="1.00" min="0" max="100">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="fas fa-save"></i> Save Rate
                </button>
            </form>
        </div>
    </div>
</div>


<div class="card">
    <div class="card-header">Wallet Overview</div>
    <div class="card-body">
        <p class="text-muted" style="font-size:13px;margin-bottom:12px;">Supported wallets: EVC Plus, eDahab, Jeep Money, Premier. Each pair needs a separate rate entry (and its reverse if needed).</p>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
            <?php $__currentLoopData = ['evc' => ['EVC Plus','#E74C3C'], 'edahab' => ['eDahab','#27AE60'], 'jeep' => ['Jeep Money','#2980B9'], 'premier' => ['Premier','#8E44AD']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $info): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div style="border:2px solid <?php echo e($info[1]); ?>;border-radius:12px;padding:16px;text-align:center;">
                <div style="font-size:20px;font-weight:900;color:<?php echo e($info[1]); ?>;"><?php echo e(strtoupper($key)); ?></div>
                <div style="font-size:13px;font-weight:600;color:#333;margin-top:4px;"><?php echo e($info[0]); ?></div>
                <?php $rateCount = $rates->where('from_wallet', $key)->count() + $rates->where('to_wallet', $key)->count(); ?>
                <div style="font-size:11px;color:#888;margin-top:4px;"><?php echo e($rateCount); ?> rate<?php echo e($rateCount != 1 ? 's' : ''); ?></div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/module-data/exchange.blade.php ENDPATH**/ ?>