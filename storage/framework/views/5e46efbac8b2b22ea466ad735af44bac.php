<?php $__env->startSection('title', 'Vendor: ' . $vendor->name); ?>
<?php $__env->startSection('content'); ?>
<div class="page-header">
    <div>
        <h2 class="page-title"><?php echo e($vendor->name); ?></h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?php echo e(route('admin.vendors.index')); ?>">Vendors</a></li>
            <li class="breadcrumb-item active"><?php echo e($vendor->name); ?></li>
        </ol>
    </div>
    <div class="d-flex gap-2">
        <?php if(!$vendor->is_approved): ?>
        <form action="<?php echo e(route('admin.vendors.approve', $vendor->id)); ?>" method="POST" style="display:inline;">
            <?php echo csrf_field(); ?>
            <button class="btn btn-success"><i class="fas fa-check"></i> Approve</button>
        </form>
        <form action="<?php echo e(route('admin.vendors.reject', $vendor->id)); ?>" method="POST" style="display:inline;">
            <?php echo csrf_field(); ?>
            <button class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
        </form>
        <?php endif; ?>
        <form action="<?php echo e(route('admin.vendors.toggle-featured', $vendor->id)); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <button class="btn <?php echo e($vendor->is_featured ? 'btn-secondary' : 'btn-primary'); ?>">
                <i class="fas fa-star"></i> <?php echo e($vendor->is_featured ? 'Remove Featured' : 'Make Featured'); ?>

            </button>
        </form>
    </div>
</div>

<div class="grid-2">
    <div>
        <div class="card">
            <div class="card-header"><span>Vendor Info</span>
                <span class="badge <?php echo e($vendor->is_approved ? 'badge-success' : 'badge-warning'); ?>">
                    <?php echo e($vendor->is_approved ? 'Approved' : 'Pending'); ?>

                </span>
            </div>
            <div class="card-body">
                <table>
                    <tr><td class="text-muted" style="width:130px;padding:8px 12px 8px 0;">Name</td><td class="fw-bold"><?php echo e($vendor->name); ?></td></tr>
                    <tr><td class="text-muted">Owner</td><td><?php echo e($vendor->user?->name ?? '—'); ?></td></tr>
                    <tr><td class="text-muted">Phone</td><td><?php echo e($vendor->user?->phone ?? $vendor->phone ?? '—'); ?></td></tr>
                    <tr><td class="text-muted">Email</td><td><?php echo e($vendor->user?->email ?? $vendor->email ?? '—'); ?></td></tr>
                    <tr><td class="text-muted">Module</td><td><?php echo e($vendor->module?->name ?? strtoupper($vendor->module_slug ?? '—')); ?></td></tr>
                    <tr><td class="text-muted">District</td><td><?php echo e($vendor->district?->name ?? '—'); ?></td></tr>
                    <tr><td class="text-muted">Rating</td><td><?php echo e($vendor->rating ?? '—'); ?> ⭐</td></tr>
                    <tr><td class="text-muted">Featured</td><td><?php echo e($vendor->is_featured ? 'Yes' : 'No'); ?></td></tr>
                    <tr><td class="text-muted">Status</td>
                        <td><span class="badge <?php echo e($vendor->is_active ? 'badge-success' : 'badge-danger'); ?>"><?php echo e($vendor->is_active ? 'Active' : 'Inactive'); ?></span></td>
                    </tr>
                    <tr><td class="text-muted">Joined</td><td><?php echo e($vendor->created_at->format('d M Y')); ?></td></tr>
                    <tr>
                        <td class="text-muted" style="vertical-align:top;padding-top:10px;">Business License</td>
                        <td style="padding:8px 0;">
                            <?php if($vendor->business_license): ?>
                                <?php $ext = strtolower(pathinfo($vendor->business_license, PATHINFO_EXTENSION)); ?>
                                <?php if(in_array($ext, ['jpg','jpeg','png','webp'])): ?>
                                    <a href="<?php echo e(asset('storage/' . $vendor->business_license)); ?>" target="_blank">
                                        <img src="<?php echo e(asset('storage/' . $vendor->business_license)); ?>"
                                             alt="Business License"
                                             style="max-width:220px;max-height:160px;border-radius:8px;border:1.5px solid #e8eaf0;cursor:pointer;">
                                    </a>
                                    <div style="margin-top:6px;">
                                        <a href="<?php echo e(asset('storage/' . $vendor->business_license)); ?>" target="_blank"
                                           style="font-size:12px;color:#FF8A00;font-weight:600;text-decoration:none;">
                                            <i class="fas fa-external-link-alt"></i> View Full Image
                                        </a>
                                    </div>
                                <?php elseif($ext === 'pdf'): ?>
                                    <a href="<?php echo e(asset('storage/' . $vendor->business_license)); ?>" target="_blank"
                                       style="display:inline-flex;align-items:center;gap:8px;padding:10px 16px;background:#fff5f5;border:1.5px solid #fecaca;border-radius:9px;color:#b91c1c;font-weight:700;font-size:13px;text-decoration:none;">
                                        <i class="fas fa-file-pdf" style="font-size:18px;"></i> View PDF Document
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#9ca3af;font-style:italic;">No document uploaded</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header">Recent Orders</div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Order #</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $vendor->orders ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><a href="<?php echo e(route('admin.orders.show', $order->id)); ?>" class="text-primary"><?php echo e($order->order_number); ?></a></td>
                            <td>$<?php echo e(number_format($order->total_amount, 2)); ?></td>
                            <td><span class="badge badge-secondary"><?php echo e(ucfirst(str_replace('_',' ',$order->status))); ?></span></td>
                            <td><?php echo e($order->created_at->format('d M')); ?></td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="4" style="text-align:center;padding:20px;color:#888;">No orders yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/vendors/show.blade.php ENDPATH**/ ?>