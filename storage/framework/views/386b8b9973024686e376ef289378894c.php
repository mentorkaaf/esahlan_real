<?php $__env->startSection('title', 'Community Management'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid py-4">

  
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0 fw-bold text-dark">Community Management</h2>
      <p class="text-muted mb-0">Manage posts, users, groups and reports</p>
    </div>
  </div>

  
  <div class="row g-3 mb-4">
    <?php
      $stats = [
        ['label'=>'Total Members','value'=> $stats['profiles'] ?? 0,'icon'=>'fa-users','color'=>'primary'],
        ['label'=>'Total Posts','value'=> $stats['posts'] ?? 0,'icon'=>'fa-file-alt','color'=>'success'],
        ['label'=>'Total Groups','value'=> $stats['groups'] ?? 0,'icon'=>'fa-layer-group','color'=>'info'],
        ['label'=>'Pending Reports','value'=> $stats['reports'] ?? 0,'icon'=>'fa-flag','color'=>'danger'],
        ['label'=>'Stories Today','value'=> $stats['stories_today'] ?? 0,'icon'=>'fa-circle-notch','color'=>'warning'],
        ['label'=>'Messages Today','value'=> $stats['messages_today'] ?? 0,'icon'=>'fa-comments','color'=>'secondary'],
      ];
    ?>
    <?php $__currentLoopData = $stats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="col-xl-2 col-md-4 col-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-circle d-flex align-items-center justify-content-center bg-<?php echo e($s['color']); ?> bg-opacity-10" style="width:48px;height:48px;flex-shrink:0">
            <i class="fas <?php echo e($s['icon']); ?> text-<?php echo e($s['color']); ?> fs-5"></i>
          </div>
          <div>
            <div class="fs-4 fw-bold text-dark lh-1"><?php echo e(number_format($s['value'])); ?></div>
            <div class="text-muted small"><?php echo e($s['label']); ?></div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>

  <div class="row g-4">
    
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3">
          <h6 class="fw-bold mb-0">Recent Posts</h6>
          <a href="<?php echo e(route('admin.community.posts')); ?>" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>User</th>
                  <th>Content</th>
                  <th>Type</th>
                  <th>Reactions</th>
                  <th>Date</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $recentPosts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $post): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <?php if($post->user?->profile_photo_path): ?>
                        <img src="<?php echo e($post->user->profile_photo_path); ?>" class="rounded-circle" width="32" height="32" style="object-fit:cover">
                      <?php else: ?>
                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:32px;height:32px;font-size:14px;font-weight:700;color:#140465">
                          <?php echo e(strtoupper(substr($post->user?->name ?? '?', 0, 1))); ?>

                        </div>
                      <?php endif; ?>
                      <span class="fw-semibold small"><?php echo e($post->user?->name ?? 'Unknown'); ?></span>
                    </div>
                  </td>
                  <td class="small text-muted" style="max-width:200px">
                    <span class="text-truncate d-block"><?php echo e(Str::limit($post->content, 60)); ?></span>
                  </td>
                  <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?php echo e($post->type); ?></span></td>
                  <td><span class="fw-semibold"><?php echo e($post->likes_count); ?></span></td>
                  <td class="small text-muted"><?php echo e($post->created_at->diffForHumans()); ?></td>
                  <td>
                    <form method="POST" action="<?php echo e(route('admin.community.posts.delete', $post->id)); ?>" onsubmit="return confirm('Delete this post?')">
                      <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                      <button class="btn btn-sm btn-outline-danger py-0">Delete</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No posts yet</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3">
          <h6 class="fw-bold mb-0 text-danger">
            <i class="fas fa-flag me-1"></i> Pending Reports
            <?php if(($stats['reports'] ?? 0) > 0): ?>
              <span class="badge bg-danger ms-1"><?php echo e($stats['reports']); ?></span>
            <?php endif; ?>
          </h6>
          <a href="<?php echo e(route('admin.community.reports')); ?>" class="btn btn-sm btn-outline-danger">View All</a>
        </div>
        <div class="list-group list-group-flush">
          <?php $__empty_1 = true; $__currentLoopData = $pendingReports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $report): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <div class="list-group-item border-0 py-2 px-3">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="small fw-semibold"><?php echo e($report->reporter?->name ?? 'User'); ?></div>
                <div class="small text-muted"><?php echo e(ucfirst($report->reason)); ?> · <?php echo e($report->reportable_type); ?></div>
              </div>
              <span class="badge bg-warning text-dark small"><?php echo e($report->created_at->diffForHumans()); ?></span>
            </div>
          </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <div class="list-group-item border-0 text-center text-muted py-4">
            <i class="fas fa-check-circle text-success fs-4 d-block mb-2"></i>
            No pending reports
          </div>
          <?php endif; ?>
        </div>
      </div>

      
      <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white border-0 py-3">
          <h6 class="fw-bold mb-0">Quick Actions</h6>
        </div>
        <div class="list-group list-group-flush">
          <a href="<?php echo e(route('admin.community.posts')); ?>" class="list-group-item list-group-item-action border-0 py-2">
            <i class="fas fa-file-alt me-2 text-primary"></i> Manage Posts
          </a>
          <a href="<?php echo e(route('admin.community.groups')); ?>" class="list-group-item list-group-item-action border-0 py-2">
            <i class="fas fa-layer-group me-2 text-info"></i> Manage Groups
          </a>
          <a href="<?php echo e(route('admin.community.users')); ?>" class="list-group-item list-group-item-action border-0 py-2">
            <i class="fas fa-user-shield me-2 text-success"></i> Verify Users
          </a>
          <a href="<?php echo e(route('admin.community.reports')); ?>" class="list-group-item list-group-item-action border-0 py-2">
            <i class="fas fa-flag me-2 text-danger"></i> Review Reports
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/community/index.blade.php ENDPATH**/ ?>