<?php $__env->startSection('title', 'eLearning Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">

  
  <div class="d-flex justify-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold" style="font-size:22px">eLearning Dashboard</h2>
      <p class="text-muted mb-0">Overview of courses, instructors and student activity</p>
    </div>
    <a href="<?php echo e(route('admin.elearning.reports')); ?>" class="btn btn-outline">
      <i class="fas fa-chart-line"></i> View Reports
    </a>
  </div>

  <?php if(session('success')): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?></div>
  <?php endif; ?>

  
  <div class="grid-4 mb-4">
    <div class="stat-card orange">
      <div class="stat-icon-wrap orange"><i class="fas fa-chalkboard-teacher"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?php echo e(number_format($stats['total_instructors'])); ?></div>
        <div class="stat-label">Active Instructors</div>
        <?php if($stats['pending_instructors'] > 0): ?>
          <div class="stat-sub warn"><i class="fas fa-clock"></i> <?php echo e($stats['pending_instructors']); ?> pending</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="stat-card blue">
      <div class="stat-icon-wrap blue"><i class="fas fa-book-open"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?php echo e(number_format($stats['total_courses'])); ?></div>
        <div class="stat-label">Published Courses</div>
        <?php if($stats['pending_courses'] > 0): ?>
          <div class="stat-sub warn"><i class="fas fa-clock"></i> <?php echo e($stats['pending_courses']); ?> pending review</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon-wrap green"><i class="fas fa-user-graduate"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?php echo e(number_format($stats['total_students'])); ?></div>
        <div class="stat-label">Total Students</div>
        <div class="stat-sub muted"><i class="fas fa-ticket-alt"></i> <?php echo e(number_format($stats['total_enrollments'])); ?> enrollments</div>
      </div>
    </div>
    <div class="stat-card purple">
      <div class="stat-icon-wrap purple"><i class="fas fa-dollar-sign"></i></div>
      <div class="stat-body">
        <div class="stat-value">$<?php echo e(number_format($stats['total_revenue'], 2)); ?></div>
        <div class="stat-label">Total Revenue</div>
        <?php if($stats['pending_withdrawals'] > 0): ?>
          <div class="stat-sub warn"><i class="fas fa-money-bill"></i> <?php echo e($stats['pending_withdrawals']); ?> pending withdrawals</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="grid-2">
    
    <div class="card p-4">
      <div class="d-flex justify-between align-items-center mb-3">
        <h3 style="font-size:15px;font-weight:700">Recent Enrollments</h3>
        <a href="<?php echo e(route('admin.elearning.students')); ?>" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Student</th>
              <th>Course</th>
              <th>Amount</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $recentEnrollments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
              <td><?php echo e($e->user?->name ?? '—'); ?></td>
              <td class="truncate" style="max-width:160px"><?php echo e($e->course?->title ?? '—'); ?></td>
              <td>$<?php echo e(number_format($e->amount_paid, 2)); ?></td>
              <td class="text-muted text-sm"><?php echo e($e->created_at->diffForHumans()); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No enrollments yet</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    
    <div class="card p-4">
      <div class="d-flex justify-between align-items-center mb-3">
        <h3 style="font-size:15px;font-weight:700">Top Courses by Students</h3>
        <a href="<?php echo e(route('admin.elearning.courses')); ?>" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <?php $__empty_1 = true; $__currentLoopData = $topCourses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div class="d-flex align-items-center gap-3 mb-3 pb-3" style="border-bottom:1px solid var(--border)">
        <?php if($c->thumbnail): ?>
          <img src="<?php echo e(asset('storage/'.$c->thumbnail)); ?>" style="width:48px;height:48px;border-radius:8px;object-fit:cover">
        <?php else: ?>
          <div class="avatar avatar-md avatar-orange"><?php echo e(substr($c->title,0,1)); ?></div>
        <?php endif; ?>
        <div class="flex-1 min-width-0">
          <div class="fw-bold truncate" style="font-size:13px"><?php echo e($c->title); ?></div>
          <div class="text-muted text-sm"><?php echo e($c->instructor?->user?->name ?? '—'); ?></div>
        </div>
        <div class="text-right">
          <div class="fw-bold text-sm"><?php echo e(number_format($c->total_students)); ?></div>
          <div class="text-muted" style="font-size:11px">students</div>
        </div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="empty-state"><i class="fas fa-book-open"></i><p>No courses yet</p></div>
      <?php endif; ?>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/elearning/dashboard.blade.php ENDPATH**/ ?>