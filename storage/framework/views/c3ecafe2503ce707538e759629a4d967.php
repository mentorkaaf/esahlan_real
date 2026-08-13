<?php $__env->startSection('title', 'Community Groups'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0 fw-bold">Community Groups</h2>
      <p class="text-muted mb-0">Manage all community groups</p>
    </div>
    <a href="<?php echo e(route('admin.community.index')); ?>" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i> Back
    </a>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Group</th>
              <th>Owner</th>
              <th>Category</th>
              <th>Privacy</th>
              <th>Members</th>
              <th>Posts</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if($group->cover_photo): ?>
                    <img src="<?php echo e($group->cover_photo); ?>" class="rounded" width="40" height="32" style="object-fit:cover">
                  <?php else: ?>
                    <div class="rounded bg-info bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:32px">
                      <i class="fas fa-layer-group text-info"></i>
                    </div>
                  <?php endif; ?>
                  <div>
                    <div class="fw-semibold small"><?php echo e($group->name); ?></div>
                    <div class="text-muted" style="font-size:11px"><?php echo e('@'.$group->slug); ?></div>
                  </div>
                </div>
              </td>
              <td class="small"><?php echo e($group->owner?->name ?? '—'); ?></td>
              <td class="small"><?php echo e($group->category ?? '—'); ?></td>
              <td>
                <?php $pc = ['public'=>'success','private'=>'warning','secret'=>'danger'][$group->privacy ?? 'public'] ?>
                <span class="badge bg-<?php echo e($pc); ?> bg-opacity-15 text-<?php echo e($pc); ?>"><?php echo e(ucfirst($group->privacy)); ?></span>
              </td>
              <td class="fw-semibold"><?php echo e(number_format($group->members_count)); ?></td>
              <td class="fw-semibold"><?php echo e(number_format($group->posts_count)); ?></td>
              <td class="small text-muted"><?php echo e($group->created_at->format('d M Y')); ?></td>
              <td>
                <form method="POST" action="<?php echo e(route('admin.community.groups.delete', $group->id)); ?>" onsubmit="return confirm('Delete this group?')">
                  <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                  <button class="btn btn-sm btn-outline-danger py-0"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="8" class="text-center text-muted py-5">No groups yet</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if($groups->hasPages()): ?>
    <div class="card-footer bg-white border-0"><?php echo e($groups->withQueryString()->links()); ?></div>
    <?php endif; ?>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/community/groups.blade.php ENDPATH**/ ?>