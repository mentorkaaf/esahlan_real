<?php $__env->startSection('title', 'Community Reports'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0 fw-bold text-danger"><i class="fas fa-flag me-2"></i>Reports</h2>
      <p class="text-muted mb-0">Review and action reported content</p>
    </div>
    <a href="<?php echo e(route('admin.community.index')); ?>" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i> Back
    </a>
  </div>

  
  <ul class="nav nav-pills mb-3">
    <?php $__currentLoopData = ['pending'=>'Pending','reviewed'=>'Reviewed','dismissed'=>'Dismissed']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k=>$v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <li class="nav-item">
      <a class="nav-link <?php if(request('status',$k==='pending'?'pending':null)===$k): ?> active <?php endif; ?>"
         href="<?php echo e(request()->fullUrlWithQuery(['status'=>$k])); ?>">
        <?php echo e($v); ?>

        <?php if($k==='pending' && ($counts['pending']??0) > 0): ?>
          <span class="badge bg-danger ms-1"><?php echo e($counts['pending']); ?></span>
        <?php endif; ?>
      </a>
    </li>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </ul>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Reporter</th>
              <th>Content Type</th>
              <th>Reason</th>
              <th>Description</th>
              <th>Reported</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $reports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $report): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
              <td>
                <div class="fw-semibold small"><?php echo e($report->reporter?->name ?? 'User'); ?></div>
                <div class="text-muted" style="font-size:11px"><?php echo e($report->reporter?->email); ?></div>
              </td>
              <td>
                <span class="badge bg-secondary bg-opacity-15 text-secondary">
                  <?php echo e(class_basename($report->reportable_type)); ?> #<?php echo e($report->reportable_id); ?>

                </span>
              </td>
              <td>
                <span class="badge bg-danger bg-opacity-10 text-danger"><?php echo e(ucfirst($report->reason)); ?></span>
              </td>
              <td class="small text-muted" style="max-width:180px">
                <span class="text-truncate d-block"><?php echo e($report->description ?? '—'); ?></span>
              </td>
              <td class="small text-muted"><?php echo e($report->created_at->diffForHumans()); ?></td>
              <td>
                <?php $sc = ['pending'=>'warning','reviewed'=>'success','dismissed'=>'secondary'][$report->status ?? 'pending'] ?>
                <span class="badge bg-<?php echo e($sc); ?>"><?php echo e(ucfirst($report->status ?? 'pending')); ?></span>
              </td>
              <td>
                <div class="d-flex gap-1">
                  <form method="POST" action="<?php echo e(route('admin.community.reports.action', $report->id)); ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="reviewed">
                    <button class="btn btn-sm btn-outline-success py-0" title="Mark Reviewed">✓</button>
                  </form>
                  <form method="POST" action="<?php echo e(route('admin.community.reports.action', $report->id)); ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="dismissed">
                    <button class="btn btn-sm btn-outline-secondary py-0" title="Dismiss">✗</button>
                  </form>
                  <?php if($report->reportable_type === 'post'): ?>
                  <form method="POST" action="<?php echo e(route('admin.community.posts.delete', $report->reportable_id)); ?>" onsubmit="return confirm('Delete reported content?')">
                    <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                    <button class="btn btn-sm btn-outline-danger py-0" title="Delete Content"><i class="fas fa-trash" style="font-size:11px"></i></button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-5">
                <i class="fas fa-check-circle text-success fs-3 d-block mb-2"></i>
                No reports found
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if($reports->hasPages()): ?>
    <div class="card-footer bg-white border-0"><?php echo e($reports->withQueryString()->links()); ?></div>
    <?php endif; ?>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/community/reports.blade.php ENDPATH**/ ?>