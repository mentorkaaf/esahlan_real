<?php $__env->startSection('title', 'Course: ' . $course->title); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">

  <div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?php echo e(route('admin.elearning.courses')); ?>" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    <h2 class="fw-bold mb-0" style="font-size:20px">Course Detail</h2>
  </div>

  <?php if(session('success')): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?></div>
  <?php endif; ?>

  <div class="grid-2 mb-4">
    
    <div class="card p-4">
      <?php if($course->thumbnail): ?>
        <img src="<?php echo e(asset('storage/'.$course->thumbnail)); ?>" style="width:100%;height:180px;object-fit:cover;border-radius:10px;margin-bottom:16px">
      <?php endif; ?>
      <h3 class="fw-bold mb-1"><?php echo e($course->title); ?></h3>
      <?php if($course->subtitle): ?>
        <p class="text-muted text-sm mb-3"><?php echo e($course->subtitle); ?></p>
      <?php endif; ?>

      <table class="info-table mb-4">
        <tr><td>Instructor</td><td><?php echo e($course->instructor?->user?->name ?? '—'); ?></td></tr>
        <tr><td>Category</td><td><?php echo e($course->category?->name ?? '—'); ?></td></tr>
        <tr><td>Level</td><td><?php echo e(ucfirst($course->level)); ?></td></tr>
        <tr><td>Language</td><td><?php echo e(strtoupper($course->language)); ?></td></tr>
        <tr><td>Price</td><td><?php echo e($course->is_free ? 'Free' : '$'.number_format($course->price,2)); ?></td></tr>
        <?php if($course->discount_price): ?>
        <tr><td>Discount</td><td>$<?php echo e(number_format($course->discount_price,2)); ?></td></tr>
        <?php endif; ?>
        <tr><td>Duration</td><td><?php echo e($course->duration_hours); ?> hrs</td></tr>
        <tr><td>Sections</td><td><?php echo e($course->total_sections); ?></td></tr>
        <tr><td>Lessons</td><td><?php echo e($course->total_lessons); ?></td></tr>
        <tr><td>Students</td><td><?php echo e(number_format($course->total_students)); ?></td></tr>
        <tr><td>Rating</td><td><i class="fas fa-star text-warning"></i> <?php echo e(number_format($course->rating,1)); ?> (<?php echo e($course->total_reviews); ?> reviews)</td></tr>
        <tr><td>Commission</td><td><?php echo e($course->commission_rate); ?>%</td></tr>
        <tr><td>Status</td><td>
          <?php $sc = match($course->status){
            'published'=>'badge-success','pending'=>'badge-warning',
            'draft'=>'badge-secondary','rejected'=>'badge-danger',default=>'badge-secondary'};
          ?>
          <span class="badge <?php echo e($sc); ?>"><?php echo e(ucfirst($course->status)); ?></span>
        </td></tr>
      </table>

      <div class="d-flex gap-2 flex-wrap">
        <?php if($course->status !== 'published'): ?>
          <form method="POST" action="<?php echo e(route('admin.elearning.courses.approve', $course->id)); ?>">
            <?php echo csrf_field(); ?> <button class="btn btn-success">Approve & Publish</button>
          </form>
        <?php endif; ?>
        <?php if($course->status !== 'rejected'): ?>
          <form method="POST" action="<?php echo e(route('admin.elearning.courses.reject', $course->id)); ?>">
            <?php echo csrf_field(); ?> <button class="btn btn-danger" onclick="return confirm('Reject this course?')">Reject</button>
          </form>
        <?php endif; ?>
        <form method="POST" action="<?php echo e(route('admin.elearning.courses.destroy', $course->id)); ?>">
          <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
          <button class="btn" style="background:#7f1d1d;color:#fff;border:none"
            onclick="return confirm('Delete this course permanently? This cannot be undone.')">
            <i class="fas fa-trash me-1"></i> Delete Course
          </button>
        </form>
      </div>
    </div>

    
    <div class="card p-4">
      <h3 class="fw-bold mb-3" style="font-size:15px">Curriculum (<?php echo e($course->sections->count()); ?> sections)</h3>
      <?php $__empty_1 = true; $__currentLoopData = $course->sections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div class="mb-3">
        <div class="fw-bold text-sm mb-2" style="color:var(--navy)">
          <i class="fas fa-folder text-warning"></i> <?php echo e($section->title); ?>

        </div>
        <?php $__currentLoopData = $section->lessons; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lesson): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="d-flex align-items-center gap-2 mb-1 ps-3">
          <?php $ic = match($lesson->type){
            'video'=>'fas fa-play-circle text-info',
            'pdf'=>'fas fa-file-pdf text-danger',
            'quiz'=>'fas fa-question-circle text-purple',
            'assignment'=>'fas fa-tasks text-warning',
            'live'=>'fas fa-video text-success',
            default=>'fas fa-file text-muted'};
          ?>
          <i class="<?php echo e($ic); ?>" style="font-size:12px"></i>
          <span class="text-sm"><?php echo e($lesson->title); ?></span>
          <?php if($lesson->is_free_preview): ?>
            <span class="badge badge-teal" style="font-size:9px">Preview</span>
          <?php endif; ?>
          <?php if($lesson->video_duration_seconds > 0): ?>
            <span class="text-muted text-sm"><?php echo e(gmdate('i:s', $lesson->video_duration_seconds)); ?></span>
          <?php endif; ?>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="empty-state"><i class="fas fa-list"></i><p>No sections added yet</p></div>
      <?php endif; ?>

      <?php if($course->description): ?>
      <div class="mt-4 pt-3" style="border-top:1px solid var(--border)">
        <div class="fw-bold mb-2 text-sm">Description</div>
        <p class="text-sm" style="color:var(--text-muted)"><?php echo e(Str::limit($course->description, 400)); ?></p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/elearning/courses_show.blade.php ENDPATH**/ ?>