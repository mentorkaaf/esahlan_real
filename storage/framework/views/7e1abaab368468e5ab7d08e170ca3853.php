<?php $__env->startSection('title', 'Community Users'); ?>
<?php $__env->startSection('content'); ?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-user-shield" style="color:#FF8A00"></i> Community Users</h2>
        <ol class="breadcrumb"><li><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li><li><a href="<?php echo e(route('admin.community.index')); ?>">Community</a></li><li>Users</li></ol>
    </div>
</div>

<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>

<div class="card" style="margin-bottom:16px;padding:12px 16px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;">
        <input type="text" name="search" value="<?php echo e(request('search')); ?>" class="form-control" placeholder="Search users..." style="flex:2">
        <select name="verified" class="form-control" style="flex:1"><option value="">All</option><option value="1" <?php if(request('verified')==='1'): echo 'selected'; endif; ?>>Verified</option><option value="0" <?php if(request('verified')==='0'): echo 'selected'; endif; ?>>Unverified</option></select>
        <button class="btn btn-primary"><i class="fas fa-search"></i></button>
    </form>
</div>

<div class="card">
    <div class="table-wrap"><table>
        <thead><tr>
            <th>User</th>
            <th>Username</th>
            <th>Country</th>
            <th>City</th>
            <th>Gender</th>
            <th>Age</th>
            <th>Interests</th>
            <th>Followers</th>
            <th>Posts</th>
            <th>Verified</th>
            <th>Onboarded</th>
            <th>Actions</th>
        </tr></thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $profile): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
                $age = $profile->date_of_birth ? \Carbon\Carbon::parse($profile->date_of_birth)->age : null;
                $interests = is_array($profile->interests) ? $profile->interests : (is_string($profile->interests) ? json_decode($profile->interests, true) : []);
            ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:36px;height:36px;border-radius:50%;background:#f0f2f5;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#FF8A00;"><?php echo e(strtoupper(substr($profile->user?->name ?? '?', 0, 1))); ?></div>
                        <div>
                            <div style="font-weight:700;font-size:13px;"><?php echo e($profile->user?->name ?? '—'); ?></div>
                            <div style="font-size:11px;color:#8A8A9A;"><?php echo e($profile->user?->phone ?? ''); ?></div>
                        </div>
                    </div>
                </td>
                <td style="color:#8A8A9A;"><?php echo e($profile->username ? '@'.$profile->username : '—'); ?></td>
                <td style="font-weight:600;"><?php echo e($profile->country ?? '—'); ?></td>
                <td><?php echo e($profile->city ?? '—'); ?></td>
                <td>
                    <?php if($profile->gender === 'male'): ?><span class="badge badge-info">Male</span>
                    <?php elseif($profile->gender === 'female'): ?><span class="badge badge-warning" style="background:#EC4899;color:#fff;">Female</span>
                    <?php else: ?> <span style="color:#8A8A9A;">—</span><?php endif; ?>
                </td>
                <td style="font-weight:600;"><?php echo e($age ?? '—'); ?></td>
                <td style="max-width:200px;">
                    <?php if(!empty($interests)): ?>
                        <?php $__currentLoopData = array_slice($interests, 0, 3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $int): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <span style="display:inline-block;background:#FFF3E0;color:#FF8A00;font-size:10px;font-weight:700;padding:2px 6px;border-radius:10px;margin:1px;"><?php echo e($int); ?></span>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php if(count($interests) > 3): ?><span style="font-size:10px;color:#8A8A9A;">+<?php echo e(count($interests) - 3); ?></span><?php endif; ?>
                    <?php else: ?> <span style="color:#8A8A9A;">—</span><?php endif; ?>
                </td>
                <td style="font-weight:700;"><?php echo e($profile->followers_count); ?></td>
                <td><?php echo e($profile->posts_count); ?></td>
                <td><span class="badge <?php echo e($profile->is_verified ? 'badge-success' : 'badge-secondary'); ?>"><?php echo e($profile->is_verified ? 'Yes' : 'No'); ?></span></td>
                <td><span class="badge <?php echo e($profile->onboarding_completed ? 'badge-success' : 'badge-danger'); ?>"><?php echo e($profile->onboarding_completed ? 'Yes' : 'No'); ?></span></td>
                <td>
                    <form action="<?php echo e(route('admin.community.users.verify', $profile->id)); ?>" method="POST" style="display:inline;"><?php echo csrf_field(); ?>
                        <button class="btn btn-sm <?php echo e($profile->is_verified ? 'btn-warning' : 'btn-success'); ?>">
                            <i class="fas <?php echo e($profile->is_verified ? 'fa-times' : 'fa-check'); ?>"></i> <?php echo e($profile->is_verified ? 'Unverify' : 'Verify'); ?>

                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="12" style="text-align:center;padding:30px;color:#8A8A9A;">No users found</td></tr>
            <?php endif; ?>
        </tbody>
    </table></div>
    <?php if($users->hasPages()): ?><div style="padding:16px;display:flex;justify-content:center;"><?php echo e($users->withQueryString()->links()); ?></div><?php endif; ?>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/community/users.blade.php ENDPATH**/ ?>