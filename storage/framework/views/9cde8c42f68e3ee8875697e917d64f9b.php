<?php $__env->startSection('title', 'Instructor: ' . ($instructor->user?->name ?? '')); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">

  <div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?php echo e(route('admin.elearning.instructors')); ?>" class="btn btn-outline btn-sm">
      <i class="fas fa-arrow-left"></i> Back
    </a>
    <h2 class="fw-bold mb-0" style="font-size:20px">Instructor Profile</h2>
  </div>

  <?php if(session('success')): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?></div>
  <?php endif; ?>

  <div class="grid-2 mb-4">
    <div class="card p-4">
      <div class="d-flex align-items-center gap-4 mb-4">
        <?php if($instructor->profile_photo): ?>
          <img src="<?php echo e(asset('storage/'.$instructor->profile_photo)); ?>" style="width:72px;height:72px;border-radius:14px;object-fit:cover">
        <?php else: ?>
          <div class="avatar avatar-lg avatar-purple"><?php echo e(substr($instructor->user?->name ?? '?', 0, 1)); ?></div>
        <?php endif; ?>
        <div>
          <h3 class="fw-bold mb-1"><?php echo e($instructor->user?->name); ?></h3>
          <div class="text-muted text-sm"><?php echo e($instructor->user?->email); ?></div>
          <div class="mt-2">
            <?php if($instructor->verification_status === 'approved'): ?>
              <span class="badge badge-success"><i class="fas fa-check"></i> Approved</span>
            <?php elseif($instructor->verification_status === 'pending'): ?>
              <span class="badge badge-warning"><i class="fas fa-clock"></i> Pending</span>
            <?php else: ?>
              <span class="badge badge-danger">Rejected</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <table class="info-table">
        <tr><td>Expertise</td><td><?php echo e($instructor->expertise ?? '—'); ?></td></tr>
        <tr><td>Experience</td><td><?php echo e($instructor->experience_years); ?> years</td></tr>
        <tr><td>Total Courses</td><td><?php echo e($instructor->total_courses); ?></td></tr>
        <tr><td>Total Students</td><td><?php echo e(number_format($instructor->total_students)); ?></td></tr>
        <tr><td>Rating</td><td><i class="fas fa-star text-warning"></i> <?php echo e(number_format($instructor->rating, 1)); ?></td></tr>
        <tr><td>Total Earnings</td><td>$<?php echo e(number_format($instructor->total_earnings, 2)); ?></td></tr>
        <tr><td>Member Since</td><td><?php echo e($instructor->created_at->format('d M Y')); ?></td></tr>
      </table>

      <?php if($instructor->bio): ?>
      <div class="mt-4">
        <div class="fw-bold mb-2 text-sm">Bio</div>
        <p class="text-sm" style="color:var(--text-muted)"><?php echo e($instructor->bio); ?></p>
      </div>
      <?php endif; ?>

      <div class="d-flex gap-2 mt-4">
        <?php if($instructor->verification_status !== 'approved'): ?>
          <form method="POST" action="<?php echo e(route('admin.elearning.instructors.approve', $instructor->id)); ?>">
            <?php echo csrf_field(); ?>
            <button class="btn btn-success">Approve Instructor</button>
          </form>
        <?php endif; ?>
        <?php if($instructor->verification_status !== 'rejected'): ?>
          <form method="POST" action="<?php echo e(route('admin.elearning.instructors.reject', $instructor->id)); ?>">
            <?php echo csrf_field(); ?>
            <button class="btn btn-danger" onclick="return confirm('Reject this instructor?')">Reject</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="card p-4">
      <h3 class="fw-bold mb-3" style="font-size:15px">Their Courses</h3>
      <?php $__empty_1 = true; $__currentLoopData = $instructor->courses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div class="d-flex align-items-center gap-3 mb-3 pb-3" style="border-bottom:1px solid var(--border)">
        <?php if($c->thumbnail): ?>
          <img src="<?php echo e(asset('storage/'.$c->thumbnail)); ?>" style="width:48px;height:48px;border-radius:8px;object-fit:cover">
        <?php else: ?>
          <div class="avatar avatar-md avatar-blue"><?php echo e(substr($c->title,0,1)); ?></div>
        <?php endif; ?>
        <div class="flex-1 min-width-0">
          <div class="fw-bold truncate text-sm"><?php echo e($c->title); ?></div>
          <div class="text-muted text-sm"><?php echo e($c->total_students); ?> students · $<?php echo e(number_format($c->price, 2)); ?></div>
        </div>
        <div>
          <?php if($c->status === 'published'): ?>
            <span class="badge badge-success">Published</span>
          <?php elseif($c->status === 'pending'): ?>
            <span class="badge badge-warning">Pending</span>
          <?php else: ?>
            <span class="badge badge-secondary"><?php echo e(ucfirst($c->status)); ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="empty-state"><i class="fas fa-book-open"></i><p>No courses yet</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/elearning/instructors_show.blade.php ENDPATH**/ ?>