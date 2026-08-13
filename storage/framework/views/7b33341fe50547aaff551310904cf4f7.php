<?php $__env->startSection('title', 'Instructor Withdrawals'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">

  <div class="d-flex justify-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold" style="font-size:22px">Instructor Withdrawals</h2>
      <p class="text-muted mb-0">Manage payout requests from instructors</p>
    </div>
  </div>

  <?php if(session('success')): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="filter-bar">
      <form method="GET" class="d-flex gap-2">
        <select name="filter" class="form-control" style="width:auto" onchange="this.form.submit()">
          <option value="all"      <?php echo e($filter==='all'      ? 'selected' : ''); ?>>All Status</option>
          <option value="pending"  <?php echo e($filter==='pending'  ? 'selected' : ''); ?>>Pending</option>
          <option value="approved" <?php echo e($filter==='approved' ? 'selected' : ''); ?>>Approved</option>
          <option value="rejected" <?php echo e($filter==='rejected' ? 'selected' : ''); ?>>Rejected</option>
          <option value="paid"     <?php echo e($filter==='paid'     ? 'selected' : ''); ?>>Paid</option>
        </select>
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Instructor</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Requested</th>
            <th>Processed</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $withdrawals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $w): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td>
              <div class="fw-bold text-sm"><?php echo e($w->instructor?->user?->name ?? '—'); ?></div>
              <div class="text-muted text-xs"><?php echo e($w->instructor?->user?->email); ?></div>
            </td>
            <td class="fw-bold">$<?php echo e(number_format($w->amount, 2)); ?></td>
            <td>
              <?php $bc = match($w->status){
                'pending'=>'badge-warning','approved'=>'badge-info',
                'paid'=>'badge-success','rejected'=>'badge-danger',default=>'badge-secondary'};
              ?>
              <span class="badge <?php echo e($bc); ?>"><?php echo e(ucfirst($w->status)); ?></span>
            </td>
            <td class="text-sm text-muted"><?php echo e($w->created_at->format('d M Y')); ?></td>
            <td class="text-sm text-muted"><?php echo e($w->processed_at?->format('d M Y') ?? '—'); ?></td>
            <td>
              <?php if($w->status === 'pending'): ?>
              <div class="d-flex gap-2">
                <form method="POST" action="<?php echo e(route('admin.elearning.withdrawals.approve', $w->id)); ?>" style="display:inline">
                  <?php echo csrf_field(); ?> <button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</button>
                </form>
                <form method="POST" action="<?php echo e(route('admin.elearning.withdrawals.reject', $w->id)); ?>" style="display:inline">
                  <?php echo csrf_field(); ?> <button class="btn btn-danger btn-sm" onclick="return confirm('Reject this withdrawal?')"><i class="fas fa-times"></i> Reject</button>
                </form>
              </div>
              <?php else: ?>
              <span class="text-muted text-sm">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="6">
              <div class="empty-state"><i class="fas fa-money-bill-wave"></i><h3>No withdrawal requests</h3></div>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($withdrawals->hasPages()): ?>
    <div class="p-4"><?php echo e($withdrawals->links()); ?></div>
    <?php endif; ?>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/elearning/withdrawals.blade.php ENDPATH**/ ?>