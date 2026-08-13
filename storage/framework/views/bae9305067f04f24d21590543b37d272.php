<?php $__env->startSection('title', 'eLearning Reports'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">

  <div class="mb-4">
    <h2 class="fw-bold" style="font-size:22px">eLearning Reports</h2>
    <p class="text-muted mb-0">Platform performance and analytics</p>
  </div>

  
  <div class="card p-4 mb-4">
    <h3 class="fw-bold mb-3" style="font-size:15px">Monthly Enrollments & Revenue (Last 12 Months)</h3>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Month</th>
            <th>Enrollments</th>
            <th>Revenue</th>
          </tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $monthlyEnrollments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td><?php echo e(DateTime::createFromFormat('!m', $row->month)->format('F')); ?> <?php echo e($row->year); ?></td>
            <td><?php echo e(number_format($row->count)); ?></td>
            <td>$<?php echo e(number_format($row->revenue, 2)); ?></td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr><td colspan="3" class="text-center text-muted py-4">No data yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="grid-2 mb-4">
    
    <div class="card p-4">
      <h3 class="fw-bold mb-3" style="font-size:15px">Top 10 Courses by Students</h3>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Course</th>
              <th>Instructor</th>
              <th>Students</th>
              <th>Rating</th>
            </tr>
          </thead>
          <tbody>
            <?php $__currentLoopData = $topCourses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td class="fw-bold text-muted"><?php echo e($i + 1); ?></td>
              <td class="text-sm fw-bold"><?php echo e(Str::limit($c->title, 35)); ?></td>
              <td class="text-sm text-muted"><?php echo e($c->instructor?->user?->name ?? '—'); ?></td>
              <td><?php echo e(number_format($c->total_students)); ?></td>
              <td><i class="fas fa-star text-warning"></i> <?php echo e(number_format($c->rating, 1)); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </tbody>
        </table>
      </div>
    </div>

    
    <div class="card p-4">
      <h3 class="fw-bold mb-3" style="font-size:15px">Top 10 Instructors by Students</h3>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Instructor</th>
              <th>Students</th>
              <th>Courses</th>
              <th>Rating</th>
            </tr>
          </thead>
          <tbody>
            <?php $__currentLoopData = $topInstructors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $inst): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td class="fw-bold text-muted"><?php echo e($i + 1); ?></td>
              <td class="text-sm fw-bold"><?php echo e($inst->user?->name ?? '—'); ?></td>
              <td><?php echo e(number_format($inst->total_students)); ?></td>
              <td><?php echo e($inst->total_courses); ?></td>
              <td><i class="fas fa-star text-warning"></i> <?php echo e(number_format($inst->rating, 1)); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  
  <div class="card p-4">
    <h3 class="fw-bold mb-3" style="font-size:15px">Courses by Category</h3>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Category</th>
            <th>Published Courses</th>
          </tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $categoryBreakdown; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td><?php echo e($cat->name); ?></td>
            <td><?php echo e($cat->courses_count); ?></td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr><td colspan="2" class="text-center text-muted py-4">No data yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/elearning/reports.blade.php ENDPATH**/ ?>