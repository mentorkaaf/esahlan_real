<?php $__env->startSection('title', 'eLearning Settings'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">

  <div class="mb-4">
    <h2 class="fw-bold" style="font-size:22px">eLearning Settings</h2>
    <p class="text-muted mb-0">Configure platform commission and feature settings</p>
  </div>

  <?php if(session('success')): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?></div>
  <?php endif; ?>

  <div class="card p-4" style="max-width:560px">
    <form method="POST" action="<?php echo e(route('admin.elearning.settings.update')); ?>">
      <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>

      <div class="form-group">
        <label class="form-label">Platform Commission Rate (%)</label>
        <input type="number" name="commission_rate" class="form-control" value="<?php echo e($settings['commission_rate']); ?>" min="0" max="100" step="0.5" required>
        <div class="text-muted text-xs mt-1">Percentage deducted from each sale. Instructor receives (100 - rate)%.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Max Instructor Payout Per Request ($)</label>
        <input type="number" name="max_instructor_payout" class="form-control" value="<?php echo e($settings['max_instructor_payout']); ?>" min="0" step="0.01">
      </div>

      <div class="form-group">
        <label class="form-label d-flex align-items-center gap-2">
          <input type="checkbox" name="certificate_enabled" value="1" <?php echo e($settings['certificate_enabled'] ? 'checked' : ''); ?>>
          Enable Auto-Certificates on Completion
        </label>
      </div>

      <div class="form-group">
        <label class="form-label d-flex align-items-center gap-2">
          <input type="checkbox" name="quiz_enabled" value="1" <?php echo e($settings['quiz_enabled'] ? 'checked' : ''); ?>>
          Enable Quiz Feature
        </label>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">Save Settings</button>
      </div>
    </form>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/elearning/settings.blade.php ENDPATH**/ ?>