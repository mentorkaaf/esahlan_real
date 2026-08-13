<?php $__env->startSection('title', 'Community Posts'); ?>
<?php $__env->startSection('content'); ?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-file-alt" style="color:#FF8A00"></i> Community Posts</h2>
        <ol class="breadcrumb"><li><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li><li><a href="<?php echo e(route('admin.community.index')); ?>">Community</a></li><li>Posts</li></ol>
    </div>
</div>

<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>


<div class="card" style="margin-bottom:16px;padding:16px;">
    <form method="GET" style="display:flex;gap:10px;align-items:end;">
        <div style="flex:2"><label class="form-label" style="font-size:12px;">Search</label><input type="text" name="search" value="<?php echo e(request('search')); ?>" class="form-control" placeholder="Search posts..."></div>
        <div style="flex:1"><label class="form-label" style="font-size:12px;">Type</label><select name="type" class="form-control"><option value="">All</option><?php $__currentLoopData = ['text','image','video','reel','poll','share']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($t); ?>" <?php if(request('type')===$t): echo 'selected'; endif; ?>><?php echo e(ucfirst($t)); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
        <div><button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button></div>
    </form>
</div>

<form action="<?php echo e(route('admin.community.posts.bulk-delete')); ?>" method="POST" id="bulkPostsForm" style="display:none;"><?php echo csrf_field(); ?><div id="bulkPostIds"></div></form>
<div style="display:flex;justify-content:flex-end;margin-bottom:10px;">
    <button type="button" class="btn btn-danger" onclick="submitBulkDeletePosts()" style="display:none;" id="bulkPostDeleteBtn"><i class="fas fa-trash"></i> Delete Selected</button>
</div>
    <div class="card">
        <div class="table-wrap"><table>
            <thead><tr>
                <th><input type="checkbox" id="selectAllPosts" onchange="document.querySelectorAll('.post-check').forEach(c=>{c.checked=this.checked});document.getElementById('bulkPostDeleteBtn').style.display=this.checked?'block':'none'"></th>
                <th>#</th><th>Author</th><th>Media</th><th>Content</th><th>Type</th><th>Privacy</th><th>Likes</th><th>Comments</th><th>Views</th><th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $posts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $post): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?php echo e($post->id); ?>" class="post-check" onchange="document.getElementById('bulkPostDeleteBtn').style.display=document.querySelectorAll('.post-check:checked').length?'block':'none'"></td>
                    <td style="font-weight:700;"><?php echo e($post->id); ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:32px;height:32px;border-radius:50%;background:#f0f2f5;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#FF8A00;"><?php echo e(strtoupper(substr($post->user?->name ?? '?', 0, 1))); ?></div>
                            <div><div style="font-weight:600;font-size:13px;"><?php echo e($post->user?->name ?? '—'); ?></div></div>
                        </div>
                    </td>
                    <td>
                        <?php if($post->media->count() > 0): ?>
                            <?php $m = $post->media->first(); ?>
                            <?php if($m->type === 'video'): ?>
                                <div onclick="openMediaModal('<?php echo e(cdn_url($m->url)); ?>', 'video')" style="width:50px;height:50px;border-radius:8px;background:#1a1b2e;display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;">
                                    <i class="fas fa-play" style="color:#fff;font-size:14px;"></i>
                                    <?php if($post->media->count() > 1): ?><span style="position:absolute;top:2px;right:2px;background:#FF8A00;color:#fff;border-radius:4px;font-size:9px;padding:1px 4px;">+<?php echo e($post->media->count() - 1); ?></span><?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div style="position:relative;cursor:pointer;" onclick="openMediaModal('<?php echo e(cdn_url($m->url)); ?>', 'image')">
                                    <img src="<?php echo e(cdn_url($m->url)); ?>" style="width:50px;height:50px;border-radius:8px;object-fit:cover;" onerror="this.style.display='none'">
                                    <?php if($post->media->count() > 1): ?><span style="position:absolute;top:2px;right:2px;background:#FF8A00;color:#fff;border-radius:4px;font-size:9px;padding:1px 4px;">+<?php echo e($post->media->count() - 1); ?></span><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:#ccc;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="max-width:250px;"><div style="font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo e($post->content ?? '—'); ?></div></td>
                    <td><span class="badge badge-info"><?php echo e($post->type); ?></span></td>
                    <td><span class="badge <?php echo e($post->privacy === 'public' ? 'badge-success' : 'badge-secondary'); ?>"><?php echo e($post->privacy); ?></span></td>
                    <td style="font-weight:700;"><?php echo e($post->likes_count); ?></td>
                    <td><?php echo e($post->comments_count); ?></td>
                    <td><?php echo e($post->views_count); ?></td>
                    <td style="font-size:12px;color:#8A8A9A;"><?php echo e($post->created_at->format('d M Y')); ?></td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <?php if($post->media->count() > 0): ?>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="openMediaModal('<?php echo e(cdn_url($post->media->first()->url)); ?>', '<?php echo e($post->media->first()->type); ?>')"><i class="fas fa-eye"></i></button>
                            <?php endif; ?>
                            <form method="POST" action="<?php echo e(route('admin.community.posts.delete', $post->id)); ?>" onsubmit="return confirm('Delete?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="12" style="text-align:center;padding:30px;color:#8A8A9A;">No posts found</td></tr>
                <?php endif; ?>
            </tbody>
        </table></div>
        <?php if($posts->hasPages()): ?><div style="padding:16px;display:flex;justify-content:center;"><?php echo e($posts->withQueryString()->links()); ?></div><?php endif; ?>
    </div>


<div id="mediaModal" class="modal-overlay" style="display:none;z-index:9999;" onclick="if(event.target===this)closeMediaModal()">
    <div style="position:relative;max-width:800px;margin:auto;padding-top:60px;">
        <button onclick="closeMediaModal()" style="position:absolute;top:20px;right:0;background:rgba(0,0,0,0.5);color:#fff;border:none;border-radius:50%;width:36px;height:36px;font-size:18px;cursor:pointer;">&times;</button>
        <div id="mediaContent"></div>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
function submitBulkDeletePosts() {
    var checked = document.querySelectorAll('.post-check:checked');
    if (!checked.length) { alert('Select posts to delete'); return; }
    if (!confirm('Delete ' + checked.length + ' selected posts?')) return;
    var container = document.getElementById('bulkPostIds');
    container.innerHTML = '';
    checked.forEach(function(c) {
        var input = document.createElement('input');
        input.type = 'hidden'; input.name = 'ids[]'; input.value = c.value;
        container.appendChild(input);
    });
    document.getElementById('bulkPostsForm').submit();
}
function openMediaModal(url, type) {
    var el = document.getElementById('mediaContent');
    if (type === 'video') {
        el.innerHTML = '<video src="'+url+'" controls autoplay style="width:100%;max-height:80vh;border-radius:12px;background:#000;"></video>';
    } else {
        el.innerHTML = '<img src="'+url+'" style="width:100%;max-height:80vh;border-radius:12px;object-fit:contain;background:#000;">';
    }
    document.getElementById('mediaModal').style.display = 'flex';
}
function closeMediaModal() {
    document.getElementById('mediaModal').style.display = 'none';
    document.getElementById('mediaContent').innerHTML = '';
}
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/community/posts.blade.php ENDPATH**/ ?>