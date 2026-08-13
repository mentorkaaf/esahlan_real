<?php $__env->startSection('title', 'eLearning Instructors'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">

  <div class="d-flex justify-between align-items-center mb-4">
    <div>
      <h2 class="fw-bold" style="font-size:22px">Instructors</h2>
      <p class="text-muted mb-0">Manage instructor accounts and verifications</p>
    </div>
  </div>

  <?php if(session('success')): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?></div>
  <?php endif; ?>

  
  <div class="card mb-4">
    <div class="filter-bar">
      <form method="GET" class="d-flex gap-2 flex-wrap" style="width:100%">
        <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search by name or email…" class="form-control" style="max-width:260px">
        <select name="filter" class="form-control" style="width:auto" onchange="this.form.submit()">
          <option value="all" <?php echo e($filter==='all' ? 'selected' : ''); ?>>All Status</option>
          <option value="pending"  <?php echo e($filter==='pending'  ? 'selected' : ''); ?>>Pending</option>
          <option value="approved" <?php echo e($filter==='approved' ? 'selected' : ''); ?>>Approved</option>
          <option value="rejected" <?php echo e($filter==='rejected' ? 'selected' : ''); ?>>Rejected</option>
        </select>
        <button class="btn btn-primary">Search</button>
        <?php if($search || $filter!=='all'): ?>
          <a href="<?php echo e(route('admin.elearning.instructors')); ?>" class="btn btn-outline">Reset</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Instructor</th>
            <th>Expertise</th>
            <th>Courses</th>
            <th>Students</th>
            <th>Rating</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $instructors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $inst): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php if($inst->profile_photo): ?>
                  <img src="<?php echo e(asset('storage/'.$inst->profile_photo)); ?>" style="width:36px;height:36px;border-radius:9px;object-fit:cover">
                <?php else: ?>
                  <div class="avatar avatar-sm avatar-purple"><?php echo e(substr($inst->user?->name ?? '?', 0, 1)); ?></div>
                <?php endif; ?>
                <div>
                  <div class="fw-bold" style="font-size:13px"><?php echo e($inst->user?->name); ?></div>
                  <div class="text-muted text-sm"><?php echo e($inst->user?->email); ?></div>
                </div>
              </div>
            </td>
            <td class="text-sm"><?php echo e($inst->expertise ?? '—'); ?></td>
            <td><?php echo e($inst->total_courses); ?></td>
            <td><?php echo e(number_format($inst->total_students)); ?></td>
            <td>
              <span class="text-warning"><i class="fas fa-star"></i></span>
              <?php echo e(number_format($inst->rating, 1)); ?>

            </td>
            <td>
              <?php if($inst->verification_status === 'approved'): ?>
                <span class="badge badge-success"><i class="fas fa-check"></i> Approved</span>
              <?php elseif($inst->verification_status === 'pending'): ?>
                <span class="badge badge-warning"><i class="fas fa-clock"></i> Pending</span>
              <?php else: ?>
                <span class="badge badge-danger"><i class="fas fa-times"></i> Rejected</span>
              <?php endif; ?>
            </td>
            <td class="text-sm text-muted"><?php echo e($inst->created_at->format('d M Y')); ?></td>
            <td>
              <div class="d-flex gap-2">
                <a href="<?php echo e(route('admin.elearning.instructors.show', $inst->id)); ?>" class="btn btn-ghost btn-sm">
                  <i class="fas fa-eye"></i>
                </a>
                <?php if($inst->verification_status !== 'approved'): ?>
                  <form method="POST" action="<?php echo e(route('admin.elearning.instructors.approve', $inst->id)); ?>" style="display:inline">
                    <?php echo csrf_field(); ?>
                    <button class="btn btn-success btn-sm" onclick="return confirm('Approve this instructor?')">
                      <i class="fas fa-check"></i>
                    </button>
                  </form>
                <?php endif; ?>
                <?php if($inst->verification_status !== 'rejected'): ?>
                  <form method="POST" action="<?php echo e(route('admin.elearning.instructors.reject', $inst->id)); ?>" style="display:inline">
                    <?php echo csrf_field(); ?>
                    <button class="btn btn-danger btn-sm" onclick="return confirm('Reject this instructor?')">
                      <i class="fas fa-times"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="8">
              <div class="empty-state"><i class="fas fa-chalkboard-teacher"></i><h3>No instructors found</h3><p>No instructors match your filters.</p></div>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if($instructors->hasPages()): ?>
    <div class="p-4"><?php echo e($instructors->links()); ?></div>
    <?php endif; ?>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/elearning/instructors.blade.php ENDPATH**/ ?>