<?php $__env->startSection('title', 'eLearning Courses'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">

  <div class="d-flex justify-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold" style="font-size:22px">Courses</h2>
      <p class="text-muted mb-0">Manage and review submitted courses</p>
    </div>
  </div>

  <?php if(session('success')): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?></div>
  <?php endif; ?>

  <div class="card mb-4">
    <div class="filter-bar">
      <form method="GET" class="d-flex gap-2 flex-wrap" style="width:100%">
        <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search courses…" class="form-control" style="max-width:260px">
        <select name="filter" class="form-control" style="width:auto" onchange="this.form.submit()">
          <option value="all" <?php echo e($filter==='all' ? 'selected' : ''); ?>>All Status</option>
          <option value="pending"   <?php echo e($filter==='pending'   ? 'selected' : ''); ?>>Pending Review</option>
          <option value="published" <?php echo e($filter==='published' ? 'selected' : ''); ?>>Published</option>
          <option value="draft"     <?php echo e($filter==='draft'     ? 'selected' : ''); ?>>Draft</option>
          <option value="rejected"  <?php echo e($filter==='rejected'  ? 'selected' : ''); ?>>Rejected</option>
          <option value="archived"  <?php echo e($filter==='archived'  ? 'selected' : ''); ?>>Archived</option>
        </select>
        <button class="btn btn-primary">Search</button>
        <?php if($search || $filter!=='all'): ?>
          <a href="<?php echo e(route('admin.elearning.courses')); ?>" class="btn btn-outline">Reset</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Course</th>
            <th>Instructor</th>
            <th>Category</th>
            <th>Price</th>
            <th>Students</th>
            <th>Rating</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $courses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php if($c->thumbnail): ?>
                  <img src="<?php echo e(asset('storage/'.$c->thumbnail)); ?>" style="width:44px;height:44px;border-radius:8px;object-fit:cover">
                <?php else: ?>
                  <div class="avatar avatar-sm avatar-blue"><?php echo e(substr($c->title,0,1)); ?></div>
                <?php endif; ?>
                <div>
                  <div class="fw-bold" style="font-size:13px;max-width:180px" class="truncate"><?php echo e(Str::limit($c->title, 40)); ?></div>
                  <div class="text-muted text-sm"><?php echo e($c->level); ?></div>
                </div>
              </div>
            </td>
            <td class="text-sm"><?php echo e($c->instructor?->user?->name ?? '—'); ?></td>
            <td class="text-sm"><?php echo e($c->category?->name ?? '—'); ?></td>
            <td>
              <?php if($c->is_free): ?>
                <span class="badge badge-success">Free</span>
              <?php else: ?>
                $<?php echo e(number_format($c->price, 2)); ?>

                <?php if($c->discount_price): ?>
                  <br><span class="text-sm text-muted line-through">$<?php echo e(number_format($c->discount_price, 2)); ?></span>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td><?php echo e(number_format($c->total_students)); ?></td>
            <td><i class="fas fa-star text-warning"></i> <?php echo e(number_format($c->rating, 1)); ?></td>
            <td>
              <?php
                $sc = match($c->status) {
                  'published' => 'badge-success',
                  'pending'   => 'badge-warning',
                  'draft'     => 'badge-secondary',
                  'rejected'  => 'badge-danger',
                  'archived'  => 'badge-dark',
                  default     => 'badge-secondary',
                };
              ?>
              <span class="badge <?php echo e($sc); ?>"><?php echo e(ucfirst($c->status)); ?></span>
            </td>
            <td>
              <div class="d-flex gap-2">
                <a href="<?php echo e(route('admin.elearning.courses.show', $c->id)); ?>" class="btn btn-ghost btn-sm">
                  <i class="fas fa-eye"></i>
                </a>
                <?php if($c->status !== 'published'): ?>
                  <form method="POST" action="<?php echo e(route('admin.elearning.courses.approve', $c->id)); ?>" style="display:inline">
                    <?php echo csrf_field(); ?>
                    <button class="btn btn-success btn-sm" title="Approve"><i class="fas fa-check"></i></button>
                  </form>
                <?php endif; ?>
                <?php if($c->status !== 'rejected'): ?>
                  <form method="POST" action="<?php echo e(route('admin.elearning.courses.reject', $c->id)); ?>" style="display:inline">
                    <?php echo csrf_field(); ?>
                    <button class="btn btn-danger btn-sm" title="Reject" onclick="return confirm('Reject this course?')"><i class="fas fa-times"></i></button>
                  </form>
                <?php endif; ?>
                <form method="POST" action="<?php echo e(route('admin.elearning.courses.destroy', $c->id)); ?>" style="display:inline">
                  <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                  <button class="btn btn-sm" style="background:#7f1d1d;color:#fff;border:none" title="Delete permanently"
                    onclick="return confirm('Delete this course permanently? This cannot be undone.')">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="8">
              <div class="empty-state"><i class="fas fa-book-open"></i><h3>No courses found</h3></div>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if($courses->hasPages()): ?>
    <div class="p-4"><?php echo e($courses->links()); ?></div>
    <?php endif; ?>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/elearning/courses.blade.php ENDPATH**/ ?>