<?php $__env->startSection('title', 'eLearning Reviews'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">

  <div class="d-flex justify-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold" style="font-size:22px">Course Reviews</h2>
      <p class="text-muted mb-0">Monitor and moderate student reviews</p>
    </div>
  </div>

  <?php if(session('success')): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="filter-bar">
      <form method="GET" class="d-flex gap-2">
        <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search by course…" class="form-control" style="max-width:260px">
        <button class="btn btn-primary">Search</button>
        <?php if($search): ?> <a href="<?php echo e(route('admin.elearning.reviews')); ?>" class="btn btn-outline">Reset</a> <?php endif; ?>
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Student</th>
            <th>Course</th>
            <th>Rating</th>
            <th>Comment</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $reviews; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar avatar-sm avatar-orange"><?php echo e(substr($r->user?->name ?? '?', 0, 1)); ?></div>
                <div class="fw-bold text-sm"><?php echo e($r->user?->name); ?></div>
              </div>
            </td>
            <td class="text-sm"><?php echo e(Str::limit($r->course?->title ?? '—', 40)); ?></td>
            <td>
              <?php for($i = 1; $i <= 5; $i++): ?>
                <i class="fas fa-star <?php echo e($i <= $r->rating ? 'text-warning' : 'text-muted'); ?>" style="font-size:11px"></i>
              <?php endfor; ?>
              <span class="text-sm fw-bold"><?php echo e($r->rating); ?>/5</span>
            </td>
            <td class="text-sm text-muted" style="max-width:200px"><?php echo e(Str::limit($r->comment ?? '—', 80)); ?></td>
            <td class="text-sm text-muted"><?php echo e($r->created_at->format('d M Y')); ?></td>
            <td>
              <form method="POST" action="<?php echo e(route('admin.elearning.reviews.destroy', $r->id)); ?>" style="display:inline">
                <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                <button class="btn btn-danger btn-sm" onclick="return confirm('Delete this review?')">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="6">
              <div class="empty-state"><i class="fas fa-star"></i><h3>No reviews yet</h3></div>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($reviews->hasPages()): ?>
    <div class="p-4"><?php echo e($reviews->links()); ?></div>
    <?php endif; ?>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/elearning/reviews.blade.php ENDPATH**/ ?>