<?php $__env->startSection('title', 'eLearning Students'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">

  <div class="d-flex justify-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold" style="font-size:22px">Students</h2>
      <p class="text-muted mb-0">All course enrollments</p>
    </div>
  </div>

  <div class="card">
    <div class="filter-bar">
      <form method="GET" class="d-flex gap-2 flex-wrap">
        <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search student…" class="form-control" style="max-width:260px">
        <button class="btn btn-primary">Search</button>
        <?php if($search): ?> <a href="<?php echo e(route('admin.elearning.students')); ?>" class="btn btn-outline">Reset</a> <?php endif; ?>
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Student</th>
            <th>Course</th>
            <th>Amount Paid</th>
            <th>Status</th>
            <th>Enrolled</th>
            <th>Completed</th>
          </tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $enrollments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar avatar-sm avatar-blue"><?php echo e(substr($e->user?->name ?? '?', 0, 1)); ?></div>
                <div>
                  <div class="fw-bold text-sm"><?php echo e($e->user?->name); ?></div>
                  <div class="text-muted text-xs"><?php echo e($e->user?->email); ?></div>
                </div>
              </div>
            </td>
            <td class="text-sm"><?php echo e(Str::limit($e->course?->title ?? '—', 45)); ?></td>
            <td>$<?php echo e(number_format($e->amount_paid, 2)); ?></td>
            <td>
              <?php if($e->status === 'completed'): ?>
                <span class="badge badge-success">Completed</span>
              <?php elseif($e->status === 'active'): ?>
                <span class="badge badge-info">Active</span>
              <?php else: ?>
                <span class="badge badge-danger"><?php echo e(ucfirst($e->status)); ?></span>
              <?php endif; ?>
            </td>
            <td class="text-sm text-muted"><?php echo e($e->created_at->format('d M Y')); ?></td>
            <td class="text-sm text-muted"><?php echo e($e->completed_at?->format('d M Y') ?? '—'); ?></td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="6">
              <div class="empty-state"><i class="fas fa-user-graduate"></i><h3>No students yet</h3></div>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if($enrollments->hasPages()): ?>
    <div class="p-4"><?php echo e($enrollments->links()); ?></div>
    <?php endif; ?>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/elearning/students.blade.php ENDPATH**/ ?>