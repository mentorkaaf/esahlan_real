<?php $__env->startSection('title', 'Content Moderation'); ?>
<?php $__env->startSection('content'); ?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-shield-alt" style="color:#FF8A00"></i> Content Moderation</h2>
        <ol class="breadcrumb"><li><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li><li><a href="<?php echo e(route('admin.community.index')); ?>">Community</a></li><li>Moderation</li></ol>
    </div>
</div>

<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>


<div class="card" style="margin-bottom:20px;">
    <div style="padding:16px;border-bottom:1px solid #f0f1f5;">
        <h3 style="font-weight:800;font-size:16px;margin:0;"><i class="fas fa-cog"></i> Moderation Settings</h3>
    </div>
    <form action="<?php echo e(route('admin.community.moderation.update')); ?>" method="POST" style="padding:20px;">
        <?php echo csrf_field(); ?>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;">
            <label style="display:flex;align-items:center;gap:8px;padding:12px;background:#f9fafb;border-radius:10px;cursor:pointer;">
                <input type="checkbox" name="enabled" value="1" <?php echo e($settings['enabled'] ? 'checked' : ''); ?>>
                <div><strong>Enable Moderation</strong><br><small style="color:#8A8A9A;">Turn on/off all content checks</small></div>
            </label>
            <label style="display:flex;align-items:center;gap:8px;padding:12px;background:#f9fafb;border-radius:10px;cursor:pointer;">
                <input type="checkbox" name="keyword_filter" value="1" <?php echo e($settings['keyword_filter'] ? 'checked' : ''); ?>>
                <div><strong>Keyword Filter</strong><br><small style="color:#8A8A9A;">Block posts with banned words</small></div>
            </label>
            <label style="display:flex;align-items:center;gap:8px;padding:12px;background:#f9fafb;border-radius:10px;cursor:pointer;">
                <input type="checkbox" name="image_scan" value="1" <?php echo e($settings['image_scan'] ? 'checked' : ''); ?>>
                <div><strong>Image Scan</strong><br><small style="color:#8A8A9A;">Skin-tone analysis (may flag normal photos)</small></div>
            </label>
            <label style="display:flex;align-items:center;gap:8px;padding:12px;background:#f9fafb;border-radius:10px;cursor:pointer;">
                <input type="checkbox" name="auto_block" value="1" <?php echo e($settings['auto_block'] ? 'checked' : ''); ?>>
                <div><strong>Auto Block</strong><br><small style="color:#8A8A9A;">Block immediately (vs flag for review)</small></div>
            </label>
            <label style="display:flex;align-items:center;gap:8px;padding:12px;background:#f9fafb;border-radius:10px;cursor:pointer;">
                <input type="checkbox" name="review_all_media" value="1" <?php echo e($settings['review_all_media'] ? 'checked' : ''); ?>>
                <div><strong>Review All Media</strong><br><small style="color:#8A8A9A;">Flag all image/video posts for review</small></div>
            </label>
        </div>

        <?php if($settings['image_scan']): ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
            <div class="form-group">
                <label class="form-label">Block Threshold (0.0 - 1.0)</label>
                <input type="number" name="block_threshold" value="<?php echo e($settings['block_threshold']); ?>" step="0.05" min="0" max="1" class="form-control">
                <small style="color:#8A8A9A;">Score above this = auto-block/review</small>
            </div>
            <div class="form-group">
                <label class="form-label">Review Threshold (0.0 - 1.0)</label>
                <input type="number" name="review_threshold" value="<?php echo e($settings['review_threshold']); ?>" step="0.05" min="0" max="1" class="form-control">
                <small style="color:#8A8A9A;">Score above this = flag for review</small>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-group" style="margin-bottom:20px;">
            <label class="form-label">Blocked Keywords <small style="color:#8A8A9A;">(comma-separated)</small></label>
            <textarea name="keywords" class="form-control" rows="3" style="font-size:13px;"><?php echo e($keywords); ?></textarea>
            <small style="color:#8A8A9A;">Posts containing these words will be blocked or flagged</small>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
    </form>
</div>


<div class="card">
    <div style="padding:16px;border-bottom:1px solid #f0f1f5;">
        <h3 style="font-weight:800;font-size:16px;margin:0;"><i class="fas fa-flag" style="color:#F59E0B"></i> Flagged Posts (<?php echo e($flaggedPosts->total()); ?>)</h3>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Post</th><th>Author</th><th>Reason</th><th>Score</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $flaggedPosts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $report): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php $post = $report->reportable; ?>
            <tr>
                <td style="max-width:300px;">
                    <?php if($post): ?>
                        <div style="font-weight:600;"><?php echo e(Str::limit($post->content, 80) ?: '(media only)'); ?></div>
                        <?php if($post->media->count() > 0): ?>
                            <small style="color:#8A8A9A;"><?php echo e($post->media->count()); ?> media file(s)</small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:#8A8A9A;">Post deleted</span>
                    <?php endif; ?>
                </td>
                <td><?php echo e($post?->user?->name ?? '—'); ?></td>
                <td><span class="badge badge-warning"><?php echo e($report->description); ?></span></td>
                <td style="font-weight:700;"><?php echo e(number_format(floatval(preg_replace('/.*score: ([\d.]+).*/', '$1', $report->description)), 2)); ?></td>
                <td style="font-size:12px;color:#8A8A9A;"><?php echo e($report->created_at->diffForHumans()); ?></td>
                <td style="display:flex;gap:4px;">
                    <?php if($post): ?>
                    <form action="<?php echo e(route('admin.community.moderation.approve', $report->id)); ?>" method="POST">
                        <?php echo csrf_field(); ?>
                        <button class="btn btn-sm btn-success" title="Approve"><i class="fas fa-check"></i> Approve</button>
                    </form>
                    <form action="<?php echo e(route('admin.community.moderation.reject', $report->id)); ?>" method="POST" onsubmit="return confirm('Delete this post?')">
                        <?php echo csrf_field(); ?>
                        <button class="btn btn-sm btn-danger" title="Remove"><i class="fas fa-trash"></i> Remove</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="6" style="text-align:center;padding:30px;color:#8A8A9A;">No flagged posts</td></tr>
            <?php endif; ?>
        </tbody>
    </table></div>
    <?php if($flaggedPosts->hasPages()): ?><div style="padding:16px;display:flex;justify-content:center;"><?php echo e($flaggedPosts->links()); ?></div><?php endif; ?>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/community/moderation.blade.php ENDPATH**/ ?>