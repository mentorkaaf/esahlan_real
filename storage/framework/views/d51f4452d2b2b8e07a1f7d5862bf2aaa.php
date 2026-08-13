<?php $__env->startSection('title', 'Exchange Order — ' . $order->reference); ?>

<?php $__env->startSection('content'); ?>
<div class="page-header">
    <div>
        <h1 class="page-title">Exchange Detail</h1>
        <ul class="breadcrumb">
            <li><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li>
            <li><a href="<?php echo e(route('admin.exchange.index')); ?>">eExchange</a></li>
            <li><?php echo e($order->reference); ?></li>
        </ul>
    </div>
    <a href="<?php echo e(route('admin.exchange.index')); ?>" class="btn btn-outline">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
</div>

<?php
$statusMap = [
    'completed'  => ['#10b981','check-circle','Completed'],
    'pending'    => ['#f59e0b','clock','Pending'],
    'processing' => ['#3b82f6','spinner','Processing'],
    'failed'     => ['#ef4444','times-circle','Failed'],
];
[$statusColor, $statusIcon, $statusLabel] = $statusMap[$order->status] ?? ['#64748b','circle','Unknown'];

$walletMeta = [
    'EVC'     => ['EVC Plus',   '#e74c3c', 'account_balance_wallet'],
    'EDAHAB'  => ['eDahab',     '#27ae60', 'payments'],
    'JEEP'    => ['Jeep Money', '#2980b9', 'credit_card'],
    'PREMIER' => ['Premier',    '#8e44ad', 'stars'],
];
$fromMeta = $walletMeta[$order->from_wallet] ?? [$order->from_wallet, '#64748b', 'wallet'];
$toMeta   = $walletMeta[$order->to_wallet]   ?? [$order->to_wallet,   '#64748b', 'wallet'];
?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

    
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div class="card-header-title">
                <div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:#3b82f6;">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                Exchange Details
            </div>
            <span style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:<?php echo e($statusColor); ?>;">
                <i class="fas fa-<?php echo e($statusIcon); ?>"></i> <?php echo e($statusLabel); ?>

            </span>
        </div>
        <div class="card-body" style="padding:24px;">

            
            <div style="background:var(--surface);border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <div style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Reference</div>
                    <div style="font-size:20px;font-weight:800;color:var(--navy);font-family:monospace;margin-top:2px;"><?php echo e($order->reference); ?></div>
                </div>
                <div style="font-size:12px;color:var(--text-muted);"><?php echo e(\Carbon\Carbon::parse($order->created_at)->timezone('Africa/Mogadishu')->format('d M Y, H:i:s')); ?></div>
            </div>

            
            <div style="display:flex;align-items:center;gap:0;margin-bottom:24px;">
                
                <div style="flex:1;background:<?php echo e($fromMeta[1]); ?>12;border:2px solid <?php echo e($fromMeta[1]); ?>30;border-radius:14px;padding:18px;text-align:center;">
                    <div style="width:48px;height:48px;border-radius:13px;background:<?php echo e($fromMeta[1]); ?>20;color:<?php echo e($fromMeta[1]); ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-size:22px;">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div style="font-size:11px;color:<?php echo e($fromMeta[1]); ?>;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">From</div>
                    <div style="font-size:16px;font-weight:800;color:<?php echo e($fromMeta[1]); ?>;margin:3px 0;"><?php echo e($fromMeta[0]); ?></div>
                    <div style="font-size:22px;font-weight:900;color:var(--navy);">$<?php echo e(number_format($order->sent_amount, 2)); ?></div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">Sent Amount</div>
                </div>

                
                <div style="padding:0 12px;flex-shrink:0;">
                    <div style="width:40px;height:40px;border-radius:50%;background:var(--brand);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(255,138,0,0.3);">
                        <i class="fas fa-arrow-right" style="color:#fff;font-size:14px;"></i>
                    </div>
                </div>

                
                <div style="flex:1;background:<?php echo e($toMeta[1]); ?>12;border:2px solid <?php echo e($toMeta[1]); ?>30;border-radius:14px;padding:18px;text-align:center;">
                    <div style="width:48px;height:48px;border-radius:13px;background:<?php echo e($toMeta[1]); ?>20;color:<?php echo e($toMeta[1]); ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-size:22px;">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div style="font-size:11px;color:<?php echo e($toMeta[1]); ?>;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">To</div>
                    <div style="font-size:16px;font-weight:800;color:<?php echo e($toMeta[1]); ?>;margin:3px 0;"><?php echo e($toMeta[0]); ?></div>
                    <div style="font-size:22px;font-weight:900;color:#10b981;">$<?php echo e(number_format($order->converted_amount, 2)); ?></div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">Received Amount</div>
                </div>
            </div>

            
            <div style="background:linear-gradient(135deg,#0c0148,#1a0570);border-radius:14px;padding:18px 20px;margin-bottom:20px;display:flex;align-items:center;gap:16px;">
                <div style="width:50px;height:50px;background:rgba(255,255,255,0.12);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;">📱</div>
                <div>
                    <div style="font-size:11px;color:rgba(255,255,255,0.6);font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Recipient Phone Number</div>
                    <div style="font-size:24px;font-weight:800;color:#fff;margin-top:2px;"><?php echo e($order->recipient_phone); ?></div>
                    <div style="font-size:12px;color:rgba(255,255,255,0.5);margin-top:2px;"><?php echo e($toMeta[0]); ?> wallet</div>
                </div>
                <div style="margin-left:auto;">
                    <span style="background:rgba(16,185,129,0.25);color:#4ade80;border-radius:20px;padding:4px 12px;font-size:11px;font-weight:700;border:1px solid rgba(16,185,129,0.3);">
                        <i class="fas fa-check-circle"></i> Sent
                    </span>
                </div>
            </div>

            
            <div style="border:1.5px solid var(--border);border-radius:12px;overflow:hidden;">
                <div style="background:var(--surface);padding:12px 16px;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">
                    Financial Breakdown
                </div>
                <?php
                $rows = [
                    ['Sent Amount',      '$'.number_format($order->sent_amount,2),      'var(--text)'],
                    ['Service Fee (1%)', '-$'.number_format($order->fee_amount,2),       '#ef4444'],
                    ['Exchange Rate',    '×'.$order->rate,                              'var(--text)'],
                    ['Received Amount',  '$'.number_format($order->converted_amount,2), '#10b981'],
                ];
                ?>
                <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value, $color]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:13px 16px;border-top:1px solid var(--border);<?php echo e($loop->last ? 'background:rgba(16,185,129,0.04);' : ''); ?>">
                    <span style="font-size:13px;font-weight:600;color:var(--text-muted);"><?php echo e($label); ?></span>
                    <span style="font-size:<?php echo e($loop->last ? '16' : '13'); ?>px;font-weight:<?php echo e($loop->last ? '800' : '700'); ?>;color:<?php echo e($color); ?>;"><?php echo e($value); ?></span>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <?php if($order->note): ?>
            <div style="margin-top:16px;padding:12px 16px;background:rgba(245,158,11,0.08);border:1.5px solid rgba(245,158,11,0.2);border-radius:10px;">
                <div style="font-size:11px;font-weight:700;color:#f59e0b;margin-bottom:4px;text-transform:uppercase;">Note</div>
                <div style="font-size:13px;color:var(--text);"><?php echo e($order->note); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    
    <div style="display:flex;flex-direction:column;gap:16px;">

        
        <div class="card" style="margin-bottom:0;">
            <div class="card-header">
                <div class="card-header-title">
                    <div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);"><i class="fas fa-user"></i></div>
                    Customer
                </div>
            </div>
            <div class="card-body" style="padding:18px;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
                    <div class="avatar avatar-orange" style="width:48px;height:48px;font-size:18px;"><?php echo e(strtoupper(substr($order->user_name,0,1))); ?></div>
                    <div>
                        <div style="font-weight:700;font-size:15px;color:var(--navy);"><?php echo e($order->user_name); ?></div>
                        <div style="font-size:12px;color:var(--text-muted);">ID #<?php echo e($order->user_id); ?></div>
                    </div>
                </div>
                <?php if($order->user_phone): ?>
                <div style="display:flex;align-items:center;gap:8px;padding:9px 12px;background:var(--surface);border-radius:8px;margin-bottom:8px;">
                    <i class="fas fa-phone" style="color:var(--text-muted);font-size:12px;"></i>
                    <span style="font-size:13px;font-weight:600;"><?php echo e($order->user_phone); ?></span>
                </div>
                <?php endif; ?>
                <?php if($order->user_email): ?>
                <div style="display:flex;align-items:center;gap:8px;padding:9px 12px;background:var(--surface);border-radius:8px;">
                    <i class="fas fa-envelope" style="color:var(--text-muted);font-size:12px;"></i>
                    <span style="font-size:13px;font-weight:600;"><?php echo e($order->user_email); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        
        <div class="card" style="margin-bottom:0;">
            <div class="card-header">
                <div class="card-header-title">
                    <div class="card-header-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="fas fa-info-circle"></i></div>
                    Summary
                </div>
            </div>
            <div class="card-body" style="padding:14px 18px;">
                <?php
                $meta = [
                    ['Order ID',    '#'.$order->id],
                    ['Reference',   $order->reference],
                    ['Status',      ucfirst($order->status)],
                    ['Created',     \Carbon\Carbon::parse($order->created_at)->timezone('Africa/Mogadishu')->format('d M Y')],
                    ['Time',        \Carbon\Carbon::parse($order->created_at)->timezone('Africa/Mogadishu')->format('H:i:s')],
                    ['Ago',         \Carbon\Carbon::parse($order->created_at)->timezone('Africa/Mogadishu')->diffForHumans()],
                ];
                ?>
                <?php $__currentLoopData = $meta; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$k, $v]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div style="display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border);">
                    <span style="font-size:12px;color:var(--text-muted);font-weight:600;"><?php echo e($k); ?></span>
                    <span style="font-size:12px;color:var(--navy);font-weight:700;"><?php echo e($v); ?></span>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>

        
        <div class="card" style="margin-bottom:0;">
            <div class="card-header">
                <div class="card-header-title">
                    <div class="card-header-icon" style="background:rgba(139,92,246,0.1);color:#8b5cf6;"><i class="fas fa-arrows-alt-h"></i></div>
                    Exchange Pair
                </div>
            </div>
            <div class="card-body" style="padding:18px;text-align:center;">
                <div style="display:flex;align-items:center;justify-content:center;gap:14px;">
                    <div style="text-align:center;">
                        <div style="width:50px;height:50px;border-radius:14px;background:<?php echo e($fromMeta[1]); ?>18;color:<?php echo e($fromMeta[1]); ?>;display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto 6px;"><i class="fas fa-wallet"></i></div>
                        <div style="font-size:13px;font-weight:800;color:<?php echo e($fromMeta[1]); ?>;"><?php echo e($order->from_wallet); ?></div>
                        <div style="font-size:10px;color:var(--text-muted);"><?php echo e($fromMeta[0]); ?></div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
                        <i class="fas fa-arrow-right" style="color:var(--brand);font-size:18px;"></i>
                        <span style="font-size:10px;color:var(--text-muted);font-weight:600;">Rate: <?php echo e($order->rate); ?></span>
                    </div>
                    <div style="text-align:center;">
                        <div style="width:50px;height:50px;border-radius:14px;background:<?php echo e($toMeta[1]); ?>18;color:<?php echo e($toMeta[1]); ?>;display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto 6px;"><i class="fas fa-wallet"></i></div>
                        <div style="font-size:13px;font-weight:800;color:<?php echo e($toMeta[1]); ?>;"><?php echo e($order->to_wallet); ?></div>
                        <div style="font-size:10px;color:var(--text-muted);"><?php echo e($toMeta[0]); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/exchange/show.blade.php ENDPATH**/ ?>