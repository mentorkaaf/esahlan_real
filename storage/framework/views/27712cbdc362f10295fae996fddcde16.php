<?php $__env->startSection('title', 'Community Ads & Business Pages'); ?>
<?php $__env->startSection('content'); ?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-bullhorn" style="color:#FF8A00"></i> Community Ads</h2>
        <ol class="breadcrumb"><li><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li><li>Community Ads</li></ol>
    </div>
</div>

<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:10px;margin-bottom:20px;">
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #FF8A00;"><div style="font-size:22px;font-weight:900;"><?php echo e($stats['total_ads']); ?></div><div style="font-size:11px;color:#8A8A9A;">Total Ads</div></div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #10B981;"><div style="font-size:22px;font-weight:900;"><?php echo e($stats['active_ads']); ?></div><div style="font-size:11px;color:#8A8A9A;">Active</div></div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #F59E0B;"><div style="font-size:22px;font-weight:900;"><?php echo e($stats['pending_ads']); ?></div><div style="font-size:11px;color:#8A8A9A;">Pending</div></div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #3B82F6;"><div style="font-size:22px;font-weight:900;">$<?php echo e(number_format($stats['total_revenue'], 2)); ?></div><div style="font-size:11px;color:#8A8A9A;">Revenue</div></div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #8B5CF6;"><div style="font-size:22px;font-weight:900;"><?php echo e(number_format($stats['total_clicks'])); ?></div><div style="font-size:11px;color:#8A8A9A;">Clicks</div></div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #EC4899;"><div style="font-size:22px;font-weight:900;"><?php echo e(number_format($stats['total_impressions'])); ?></div><div style="font-size:11px;color:#8A8A9A;">Impressions</div></div>
    <div style="background:#fff;border-radius:12px;padding:14px;border-left:4px solid #14B8A6;"><div style="font-size:22px;font-weight:900;"><?php echo e($stats['total_pages']); ?></div><div style="font-size:11px;color:#8A8A9A;">Biz Pages</div></div>
</div>

<?php $tab = request('tab', 'ads'); ?>
<div style="display:flex;gap:0;margin-bottom:16px;border-bottom:2px solid #f0f1f5;">
    <?php $__currentLoopData = ['ads'=>'Ads','settings'=>'Ad Settings','pricing'=>'Ad Pricing','pages'=>'Business Pages']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k=>$v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="?tab=<?php echo e($k); ?>" style="padding:10px 20px;font-weight:700;font-size:13px;color:<?php echo e($tab===$k?'#FF8A00':'#8A8A9A'); ?>;border-bottom:<?php echo e($tab===$k?'3px solid #FF8A00':'none'); ?>;text-decoration:none;"><?php echo e($v); ?></a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<?php if($tab === 'ads'): ?>
<form action="<?php echo e(route('admin.community-ads.bulk-delete')); ?>" method="POST" id="bulkForm" style="display:none;"><?php echo csrf_field(); ?><div id="bulkIds"></div></form>
<div style="display:flex;justify-content:flex-end;margin-bottom:12px;gap:8px;">
    <button type="button" class="btn btn-danger" onclick="submitBulkDelete()" style="display:none;" id="bulkDeleteBtn"><i class="fas fa-trash"></i> Delete Selected</button>
</div>
    <div class="card">
        <div class="table-wrap"><table>
            <thead><tr>
                <th><input type="checkbox" id="selectAll" onchange="document.querySelectorAll('.ad-check').forEach(c=>{c.checked=this.checked});document.getElementById('bulkDeleteBtn').style.display=this.checked?'block':'none'"></th>
                <th>ID</th><th>Media</th><th>Title</th><th>Advertiser</th><th>Page</th><th>Type</th><th>Placement</th><th>Budget</th><th>Spent</th><th>Clicks</th><th>Impr.</th><th>Status</th><th>Target</th><th>Actions</th>
            </tr></thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $ads; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ad): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php $sc = ['active'=>'badge-success','pending'=>'badge-warning','paused'=>'badge-secondary','completed'=>'badge-info','rejected'=>'badge-danger'][$ad->status] ?? 'badge-secondary'; ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?php echo e($ad->id); ?>" class="ad-check" onchange="document.getElementById('bulkDeleteBtn').style.display=document.querySelectorAll('.ad-check:checked').length?'block':'none'"></td>
                    <td style="font-weight:700;">#<?php echo e($ad->id); ?></td>
                    <td>
                        <?php if($ad->ad_type === 'video'): ?>
                            <div onclick="openMediaModal('<?php echo e(cdn_url($ad->media_url)); ?>', 'video')" style="width:50px;height:50px;border-radius:8px;background:#1a1b2e;display:flex;align-items:center;justify-content:center;cursor:pointer;"><i class="fas fa-play" style="color:#fff;font-size:16px;"></i></div>
                        <?php else: ?>
                            <img src="<?php echo e(cdn_url($ad->media_url)); ?>" style="width:50px;height:50px;border-radius:8px;object-fit:cover;cursor:pointer;" onclick="openMediaModal('<?php echo e(cdn_url($ad->media_url)); ?>', 'image')" onerror="this.style.display='none'">
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo e($ad->title); ?></strong><br><small style="color:#8A8A9A;"><?php echo e(Str::limit($ad->description, 40)); ?></small></td>
                    <td><?php echo e($ad->user?->name ?? '—'); ?></td>
                    <td><?php echo e($ad->page?->name ?? '—'); ?></td>
                    <td><span class="badge badge-info"><?php echo e($ad->ad_type); ?></span></td>
                    <td><?php echo e($ad->placement); ?></td>
                    <td style="font-weight:700;">$<?php echo e(number_format($ad->budget, 2)); ?></td>
                    <td>$<?php echo e(number_format($ad->spent, 2)); ?></td>
                    <td><?php echo e(number_format($ad->clicks)); ?></td>
                    <td><?php echo e(number_format($ad->impressions)); ?></td>
                    <td><span class="badge <?php echo e($sc); ?>"><?php echo e(ucfirst($ad->status)); ?></span></td>
                    <td style="max-width:180px;">
                        <?php $interests = is_array($ad->target_interests) ? $ad->target_interests : (is_string($ad->target_interests) ? json_decode($ad->target_interests, true) : []); ?>
                        <?php if($ad->target_country || $ad->target_city || $ad->target_gender || $ad->target_min_age): ?>
                        <div style="font-size:11px;line-height:1.6;">
                            <?php if($ad->target_country): ?><span style="background:#E3F2FD;color:#1565C0;padding:1px 5px;border-radius:4px;font-weight:600;"><?php echo e($ad->target_country); ?></span><?php endif; ?>
                            <?php if($ad->target_city): ?><span style="background:#F3E5F5;color:#7B1FA2;padding:1px 5px;border-radius:4px;font-weight:600;"><?php echo e($ad->target_city); ?></span><?php endif; ?>
                            <?php if($ad->target_gender && $ad->target_gender !== 'all'): ?><span style="background:#FCE4EC;color:#C62828;padding:1px 5px;border-radius:4px;font-weight:600;"><?php echo e(ucfirst($ad->target_gender)); ?></span><?php endif; ?>
                            <?php if($ad->target_min_age || $ad->target_max_age): ?><span style="background:#FFF3E0;color:#E65100;padding:1px 5px;border-radius:4px;font-weight:600;"><?php echo e($ad->target_min_age ?? '?'); ?>-<?php echo e($ad->target_max_age ?? '?'); ?>y</span><?php endif; ?>
                            <?php if(!empty($interests)): ?><?php $__currentLoopData = array_slice($interests, 0, 2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $int): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span style="background:#E8F5E9;color:#2E7D32;padding:1px 5px;border-radius:4px;font-weight:600;"><?php echo e($int); ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?> <?php if(count($interests)>2): ?><span style="color:#8A8A9A;">+<?php echo e(count($interests)-2); ?></span><?php endif; ?> <?php endif; ?>
                        </div>
                        <?php else: ?> <span style="color:#8A8A9A;font-size:11px;">No targeting</span><?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <form action="<?php echo e(route('admin.community-ads.status', $ad->id)); ?>" method="POST" style="display:flex;gap:4px;"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                                <select name="status" class="form-control" style="width:100px;font-size:11px;"><?php $__currentLoopData = ['pending','active','paused','completed','rejected']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($s); ?>" <?php echo e($ad->status===$s?'selected':''); ?>><?php echo e(ucfirst($s)); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-check"></i></button>
                            </form>
                            <form action="<?php echo e(route('admin.community-ads.delete', $ad->id)); ?>" method="POST" onsubmit="return confirm('Delete?')"><?php echo csrf_field(); ?><button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button></form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="15" style="text-align:center;padding:30px;color:#8A8A9A;">No ads yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table></div>
        <?php if($ads->hasPages()): ?><div style="padding:16px;display:flex;justify-content:center;"><?php echo e($ads->withQueryString()->links()); ?></div><?php endif; ?>
    </div>
<?php endif; ?>

<?php if($tab === 'settings'): ?>
<?php $adSettings = json_decode(\DB::table('settings')->where('key','ad_display_settings')->value('value') ?? '{}', true) ?? []; ?>
<div class="card" style="padding:20px;">
    <h3 style="font-weight:800;font-size:16px;margin:0 0 20px;"><i class="fas fa-sliders-h" style="color:#FF8A00"></i> Ad Display Settings</h3>
    <form action="<?php echo e(route('admin.community-ads.save-settings')); ?>" method="POST">
        <?php echo csrf_field(); ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
            
            <div style="background:#f9fafb;border-radius:12px;padding:16px;">
                <h4 style="font-weight:700;font-size:14px;margin:0 0 12px;color:#1A1B2E;"><i class="fas fa-newspaper" style="color:#FF8A00"></i> Feed Video Ads</h4>
                <div class="form-group" style="margin-bottom:10px;">
                    <label><input type="checkbox" name="feed_ads_enabled" value="1" <?php echo e(($adSettings['feed_ads_enabled'] ?? true) ? 'checked' : ''); ?>> <strong>Enable feed ads</strong></label>
                </div>
                <div class="form-group" style="margin-bottom:10px;">
                    <label class="form-label" style="font-size:12px;">Show ad every N posts</label>
                    <input type="number" name="feed_ad_frequency" value="<?php echo e($adSettings['feed_ad_frequency'] ?? 5); ?>" min="2" max="20" class="form-control" style="width:80px;">
                    <small style="color:#8A8A9A;">Posts between each ad (default: 5)</small>
                </div>
                <div class="form-group" style="margin-bottom:10px;">
                    <label class="form-label" style="font-size:12px;">Max ads per page</label>
                    <input type="number" name="feed_max_ads" value="<?php echo e($adSettings['feed_max_ads'] ?? 3); ?>" min="1" max="10" class="form-control" style="width:80px;">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size:12px;">Placements</label><br>
                    <label><input type="checkbox" name="feed_placements[]" value="feed" <?php echo e(in_array('feed', $adSettings['feed_placements'] ?? ['feed']) ? 'checked' : ''); ?>> Feed</label>
                    <label style="margin-left:10px;"><input type="checkbox" name="feed_placements[]" value="explore" <?php echo e(in_array('explore', $adSettings['feed_placements'] ?? []) ? 'checked' : ''); ?>> Explore/Trending</label>
                </div>
            </div>

            
            <div style="background:#f9fafb;border-radius:12px;padding:16px;">
                <h4 style="font-weight:700;font-size:14px;margin:0 0 12px;color:#1A1B2E;"><i class="fas fa-film" style="color:#FF8A00"></i> Video Overlay Ads (Skip Ads)</h4>
                <div class="form-group" style="margin-bottom:10px;">
                    <label><input type="checkbox" name="overlay_ads_enabled" value="1" <?php echo e(($adSettings['overlay_ads_enabled'] ?? true) ? 'checked' : ''); ?>> <strong>Enable overlay ads</strong></label>
                </div>
                <div class="form-group" style="margin-bottom:10px;">
                    <label class="form-label" style="font-size:12px;">Skip countdown (seconds)</label>
                    <input type="number" name="overlay_skip_seconds" value="<?php echo e($adSettings['overlay_skip_seconds'] ?? 10); ?>" min="3" max="30" class="form-control" style="width:80px;">
                </div>
                <div class="form-group" style="margin-bottom:10px;">
                    <label class="form-label" style="font-size:12px;">Max overlays per video</label>
                    <input type="number" name="overlay_max_per_video" value="<?php echo e($adSettings['overlay_max_per_video'] ?? 3); ?>" min="1" max="6" class="form-control" style="width:80px;">
                    <small style="color:#8A8A9A;">How many ads show in one video</small>
                </div>
                <div class="form-group" style="margin-bottom:10px;">
                    <label class="form-label" style="font-size:12px;">Show overlay ads in</label><br>
                    <label><input type="checkbox" name="overlay_placements[]" value="feed" <?php echo e(in_array('feed', $adSettings['overlay_placements'] ?? ['feed','reels']) ? 'checked' : ''); ?>> Feed videos</label>
                    <label style="margin-left:10px;"><input type="checkbox" name="overlay_placements[]" value="reels" <?php echo e(in_array('reels', $adSettings['overlay_placements'] ?? ['feed','reels']) ? 'checked' : ''); ?>> Reels</label>
                    <label style="margin-left:10px;"><input type="checkbox" name="overlay_placements[]" value="fullscreen" <?php echo e(in_array('fullscreen', $adSettings['overlay_placements'] ?? []) ? 'checked' : ''); ?>> Fullscreen player</label>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size:12px;">Min video length for overlay (seconds)</label>
                    <input type="number" name="overlay_min_video_length" value="<?php echo e($adSettings['overlay_min_video_length'] ?? 15); ?>" min="5" max="120" class="form-control" style="width:80px;">
                    <small style="color:#8A8A9A;">Videos shorter than this won't show overlay ads</small>
                </div>
            </div>
        </div>

        
        <div style="background:#f9fafb;border-radius:12px;padding:16px;margin-bottom:20px;">
            <h4 style="font-weight:700;font-size:14px;margin:0 0 12px;color:#1A1B2E;"><i class="fas fa-clock" style="color:#FF8A00"></i> Overlay Frequency by Video Length</h4>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
                <div><label class="form-label" style="font-size:12px;">Short (&lt;30s)</label><input type="number" name="freq_short" value="<?php echo e($adSettings['freq_short'] ?? 1); ?>" min="0" max="2" class="form-control"><small style="color:#8A8A9A;">ads</small></div>
                <div><label class="form-label" style="font-size:12px;">Medium (30s-2m)</label><input type="number" name="freq_medium" value="<?php echo e($adSettings['freq_medium'] ?? 2); ?>" min="0" max="4" class="form-control"><small style="color:#8A8A9A;">ads</small></div>
                <div><label class="form-label" style="font-size:12px;">Long (2-5m)</label><input type="number" name="freq_long" value="<?php echo e($adSettings['freq_long'] ?? 3); ?>" min="0" max="6" class="form-control"><small style="color:#8A8A9A;">ads</small></div>
                <div><label class="form-label" style="font-size:12px;">Very long (5m+)</label><input type="number" name="freq_very_long" value="<?php echo e($adSettings['freq_very_long'] ?? 6); ?>" min="0" max="10" class="form-control"><small style="color:#8A8A9A;">max ads</small></div>
            </div>
        </div>

        
        <div style="background:#f9fafb;border-radius:12px;padding:16px;margin-bottom:20px;">
            <h4 style="font-weight:700;font-size:14px;margin:0 0 12px;color:#1A1B2E;"><i class="fas fa-compress" style="color:#FF8A00"></i> Video Compression</h4>
            <div class="form-group" style="margin-bottom:10px;">
                <label><input type="checkbox" name="video_compress_enabled" value="1" <?php echo e(($adSettings['video_compress_enabled'] ?? true) ? 'checked' : ''); ?>> <strong>Enable video compression</strong></label>
                <br><small style="color:#8A8A9A;">Compress videos before upload (reduces file size, faster streaming)</small>
            </div>
            <div class="form-group" style="margin-bottom:10px;">
                <label class="form-label" style="font-size:12px;">Compression quality</label>
                <select name="video_compress_quality" class="form-control" style="width:200px;">
                    <option value="low" <?php echo e(($adSettings['video_compress_quality'] ?? 'default') === 'low' ? 'selected' : ''); ?>>Low (smallest file, lower quality)</option>
                    <option value="medium" <?php echo e(($adSettings['video_compress_quality'] ?? 'default') === 'medium' ? 'selected' : ''); ?>>Medium (balanced)</option>
                    <option value="default" <?php echo e(($adSettings['video_compress_quality'] ?? 'default') === 'default' ? 'selected' : ''); ?>>Default (good quality)</option>
                    <option value="high" <?php echo e(($adSettings['video_compress_quality'] ?? 'default') === 'high' ? 'selected' : ''); ?>>High (best quality, larger file)</option>
                </select>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="video_compress_ads" value="1" <?php echo e(($adSettings['video_compress_ads'] ?? true) ? 'checked' : ''); ?>> <strong>Compress video ads too</strong></label>
                <br><small style="color:#8A8A9A;">Apply compression to video ads for faster playback</small>
            </div>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Ad Settings</button>
    </form>
</div>
<?php endif; ?>

<?php if($tab === 'pricing'): ?>
<div class="card"><div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Type</th><th>Placement</th><th>Per Click ($)</th><th>Per Impression ($)</th><th>Per 1000 ($)</th><th>Min Budget ($)</th><th>Active</th><th>Actions</th></tr></thead>
    <tbody>
        <?php $__currentLoopData = $pricing; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr><form action="<?php echo e(route('admin.community-ads.pricing', $p->id)); ?>" method="POST"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
            <td style="font-weight:700;"><?php echo e($p->name); ?></td><td><span class="badge badge-info"><?php echo e($p->ad_type); ?></span></td><td><?php echo e($p->placement); ?></td>
            <td><input type="number" name="cost_per_click" value="<?php echo e($p->cost_per_click); ?>" step="0.01" class="form-control" style="width:90px;"></td>
            <td><input type="number" name="cost_per_impression" value="<?php echo e($p->cost_per_impression); ?>" step="0.0001" class="form-control" style="width:90px;"></td>
            <td><input type="number" name="cost_per_1000" value="<?php echo e($p->cost_per_1000); ?>" step="0.01" class="form-control" style="width:90px;"></td>
            <td><input type="number" name="min_budget" value="<?php echo e($p->min_budget); ?>" step="0.01" class="form-control" style="width:90px;"></td>
            <td><label><input type="checkbox" name="is_active" value="1" <?php echo e($p->is_active ? 'checked' : ''); ?>> On</label></td>
            <td><button class="btn btn-sm btn-primary"><i class="fas fa-save"></i></button></td>
        </form></tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
</table></div></div>
<?php endif; ?>

<?php if($tab === 'pages'): ?>
<div class="card"><div class="table-wrap"><table>
    <thead><tr><th>Avatar</th><th>Page</th><th>Owner</th><th>Category</th><th>Followers</th><th>Verified</th><th>Active</th><th>Created</th></tr></thead>
    <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $pages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
            <td><?php if($p->avatar): ?><img src="<?php echo e(cdn_url($p->avatar)); ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;"><?php else: ?><div style="width:40px;height:40px;border-radius:50%;background:#f0f1f5;display:flex;align-items:center;justify-content:center;"><i class="fas fa-store" style="color:#8A8A9A;"></i></div><?php endif; ?></td>
            <td><strong><?php echo e($p->name); ?></strong><br><small style="color:#8A8A9A;"><?php echo e($p->slug); ?></small></td>
            <td><?php echo e($p->user?->name ?? '—'); ?></td><td><?php echo e($p->category ?? '—'); ?></td>
            <td style="font-weight:700;"><?php echo e(number_format($p->followers_count)); ?></td>
            <td><span class="badge <?php echo e($p->is_verified ? 'badge-success' : 'badge-secondary'); ?>"><?php echo e($p->is_verified ? 'Yes' : 'No'); ?></span></td>
            <td><span class="badge <?php echo e($p->is_active ? 'badge-success' : 'badge-danger'); ?>"><?php echo e($p->is_active ? 'Active' : 'Off'); ?></span></td>
            <td style="font-size:12px;color:#8A8A9A;"><?php echo e($p->created_at->format('d M Y')); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="8" style="text-align:center;padding:30px;color:#8A8A9A;">No business pages yet</td></tr>
        <?php endif; ?>
    </tbody>
</table></div></div>
<?php endif; ?>


<div id="mediaModal" class="modal-overlay" style="display:none;z-index:9999;" onclick="if(event.target===this)closeMediaModal()">
    <div style="position:relative;max-width:800px;margin:auto;padding-top:60px;">
        <button onclick="closeMediaModal()" style="position:absolute;top:20px;right:0;background:rgba(0,0,0,0.5);color:#fff;border:none;border-radius:50%;width:36px;height:36px;font-size:18px;cursor:pointer;">&times;</button>
        <div id="mediaContent"></div>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
function submitBulkDelete() {
    var checked = document.querySelectorAll('.ad-check:checked');
    if (!checked.length) { alert('Select ads to delete'); return; }
    if (!confirm('Delete ' + checked.length + ' selected ads?')) return;
    var container = document.getElementById('bulkIds');
    container.innerHTML = '';
    checked.forEach(function(c) {
        var input = document.createElement('input');
        input.type = 'hidden'; input.name = 'ids[]'; input.value = c.value;
        container.appendChild(input);
    });
    document.getElementById('bulkForm').submit();
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

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/community-ads/index.blade.php ENDPATH**/ ?>