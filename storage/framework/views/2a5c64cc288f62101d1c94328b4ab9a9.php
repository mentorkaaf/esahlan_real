<?php $__env->startSection('title', 'eLearning Certificates'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">

  <div class="d-flex justify-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold" style="font-size:22px">Certificates</h2>
      <p class="text-muted mb-0">Issued completion certificates</p>
    </div>
  </div>

  <?php if(session('success')): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Certificate #</th>
            <th>Student</th>
            <th>Course</th>
            <th>Issued</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $certs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cert): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td>
              <span class="badge badge-purple"><?php echo e($cert->certificate_number); ?></span>
            </td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar avatar-sm avatar-green"><?php echo e(substr($cert->user?->name ?? '?', 0, 1)); ?></div>
                <div>
                  <div class="fw-bold text-sm"><?php echo e($cert->user?->name); ?></div>
                  <div class="text-muted text-xs"><?php echo e($cert->user?->email); ?></div>
                </div>
              </div>
            </td>
            <td class="text-sm"><?php echo e(Str::limit($cert->course?->title ?? '—', 50)); ?></td>
            <td class="text-sm text-muted"><?php echo e($cert->issued_at?->format('d M Y')); ?></td>
            <td>
              <form method="POST" action="<?php echo e(route('admin.elearning.certificates.revoke', $cert->id)); ?>" style="display:inline">
                <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                <button class="btn btn-danger btn-sm" onclick="return confirm('Revoke this certificate?')">
                  <i class="fas fa-ban"></i> Revoke
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="5">
              <div class="empty-state"><i class="fas fa-certificate"></i><h3>No certificates yet</h3></div>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($certs->hasPages()): ?>
    <div class="p-4"><?php echo e($certs->links()); ?></div>
    <?php endif; ?>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/elearning/certificates.blade.php ENDPATH**/ ?>