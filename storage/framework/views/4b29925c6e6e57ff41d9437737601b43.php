<?php $__env->startSection('title', 'Push Notifications'); ?>
<?php $__env->startSection('content'); ?>

<style>
.nf-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:22px; }
.nf-stat { background:#fff; border:1px solid #e8ecf2; border-radius:12px; padding:16px 18px; display:flex; align-items:center; gap:14px; }
.nf-stat-icon { width:44px; height:44px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.nf-stat-val { font-size:22px; font-weight:800; color:#1a1d2e; }
.nf-stat-lbl { font-size:12px; color:#94a3b8; margin-top:1px; }

.nf-grid { display:grid; grid-template-columns:440px 1fr; gap:18px; align-items:start; }

.nf-card { background:#fff; border:1px solid #e8ecf2; border-radius:14px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.04); }
.nf-card-head { display:flex; align-items:center; gap:12px; padding:16px 20px; border-bottom:1px solid #f0f2f6; background:#fafbfd; }
.nf-card-head-icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:15px; }
.nf-card-head h3 { margin:0; font-size:14px; font-weight:800; color:#1a1d2e; }
.nf-card-body { padding:20px; }

.nf-label { font-size:12px; font-weight:700; color:#374151; margin-bottom:6px; display:block; }
.nf-input { width:100%; padding:9px 13px; border:1.5px solid #e2e8f0; border-radius:9px; font-size:13px; color:#1a1d2e; box-sizing:border-box; transition:border-color .2s; }
.nf-input:focus { outline:none; border-color:#140465; box-shadow:0 0 0 3px rgba(20,4,101,.07); }
textarea.nf-input { resize:vertical; min-height:80px; }
.nf-mb { margin-bottom:14px; }

/* Audience chips */
.aud-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:14px; }
.aud-chip { border:1.5px solid #e2e8f0; border-radius:10px; padding:9px 12px; cursor:pointer; display:flex; align-items:center; gap:8px; font-size:12px; font-weight:600; color:#64748b; transition:all .15s; background:#fff; text-align:left; }
.aud-chip:hover { border-color:#140465; color:#140465; background:rgba(20,4,101,.04); }
.aud-chip.selected { border-color:#140465; color:#140465; background:rgba(20,4,101,.08); }

/* User search */
.user-search-wrap { position:relative; }
.user-results { position:absolute; top:100%; left:0; right:0; background:#fff; border:1.5px solid #e2e8f0; border-radius:9px; box-shadow:0 8px 24px rgba(0,0,0,.1); z-index:99; display:none; max-height:200px; overflow-y:auto; }
.user-result-item { padding:10px 14px; cursor:pointer; display:flex; align-items:center; gap:10px; border-bottom:1px solid #f4f5f8; font-size:13px; }
.user-result-item:last-child { border-bottom:none; }
.user-result-item:hover { background:#f8f9fe; }
.user-result-item .uname { font-weight:700; color:#1a1d2e; }
.user-result-item .uinfo { font-size:11px; color:#94a3b8; margin-top:1px; }
.selected-user { background:rgba(20,4,101,.06); border:1.5px solid #140465; border-radius:9px; padding:10px 14px; display:flex; align-items:center; justify-content:space-between; margin-top:8px; }
.selected-user-name { font-size:13px; font-weight:700; color:#140465; }
.selected-user-info { font-size:11px; color:#94a3b8; }

/* Banner upload */
.banner-upload { border:2px dashed #e2e8f0; border-radius:10px; padding:16px; text-align:center; cursor:pointer; transition:all .2s; position:relative; background:#fafbfd; }
.banner-upload:hover { border-color:#140465; background:rgba(20,4,101,.03); }
.banner-upload.has-image { border-color:#10b981; background:rgba(16,185,129,.04); }
.banner-upload input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
.banner-preview { width:100%; max-height:100px; object-fit:cover; border-radius:7px; display:none; margin-top:8px; }

/* Deep link selector */
.deep-link-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px; }
.dl-chip { border:1.5px solid #e2e8f0; border-radius:8px; padding:7px 10px; cursor:pointer; display:flex; align-items:center; gap:6px; font-size:11px; font-weight:600; color:#64748b; transition:all .15s; background:#fff; text-align:left; }
.dl-chip:hover { border-color:#140465; color:#140465; background:rgba(20,4,101,.04); }
.dl-chip.selected { border-color:#140465; color:#140465; background:rgba(20,4,101,.08); }
.dl-chip i { font-size:13px; width:14px; text-align:center; }

/* Preview */
.notif-preview { background:#f8f9fe; border:1px solid #e8ecf2; border-radius:10px; padding:14px; margin-bottom:16px; }
.notif-preview-label { font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.8px; margin-bottom:10px; }
.notif-preview-card { display:flex; gap:10px; align-items:flex-start; }
.notif-preview-icon { width:38px; height:38px; border-radius:9px; background:linear-gradient(135deg,#140465,#ff6200); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.notif-preview-title { font-size:13px; font-weight:800; color:#1a1d2e; }
.notif-preview-body  { font-size:12px; color:#64748b; margin-top:2px; }
.notif-preview-banner { width:100%; height:70px; object-fit:cover; border-radius:7px; margin-top:10px; display:none; }
.notif-preview-deeplink { display:inline-flex; align-items:center; gap:5px; margin-top:6px; font-size:10px; font-weight:700; color:#140465; background:rgba(20,4,101,.08); padding:3px 8px; border-radius:5px; }

/* Send button */
.btn-send { width:100%; padding:12px; border-radius:10px; background:#140465; color:#fff; border:none; font-size:14px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:opacity .2s; }
.btn-send:hover { opacity:.87; }
.btn-send:disabled { opacity:.5; cursor:not-allowed; }

/* History */
.hist-row td { vertical-align:middle; }
.notif-title-cell { font-weight:700; font-size:13px; color:#1a1d2e; }
.notif-body-cell  { font-size:11px; color:#94a3b8; margin-top:2px; }
.notif-img-thumb  { width:40px; height:28px; object-fit:cover; border-radius:5px; margin-top:4px; border:1px solid #e8ecf2; }
.target-badge { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:6px; font-size:11px; font-weight:700; }
.t-all  { background:rgba(20,4,101,.08); color:#140465; }
.t-cust { background:rgba(16,185,129,.1); color:#059669; }
.t-vend { background:rgba(245,158,11,.1); color:#d97706; }
.t-delv { background:rgba(59,130,246,.1);  color:#2563eb; }
.t-spec { background:rgba(139,92,246,.1);  color:#7c3aed; }
.deeplink-badge { display:inline-flex; align-items:center; gap:4px; padding:2px 7px; border-radius:5px; font-size:10px; font-weight:700; background:rgba(20,4,101,.07); color:#140465; margin-top:3px; }
.sent-count { font-size:11px; font-weight:700; color:#64748b; }
.btn-del { background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.2); color:#ef4444; border-radius:7px; padding:5px 10px; font-size:11px; font-weight:700; cursor:pointer; transition:all .15s; white-space:nowrap; }
.btn-del:hover { background:rgba(239,68,68,.15); }
.bulk-bar { display:none; position:sticky; top:0; z-index:10; background:#140465; color:#fff; padding:10px 16px; align-items:center; gap:12px; border-radius:0 0 0 0; }
.bulk-bar.visible { display:flex; }
.bulk-bar-count { font-size:13px; font-weight:700; flex:1; }
.btn-bulk-del { background:rgba(239,68,68,.25); border:1px solid rgba(239,68,68,.5); color:#fca5a5; border-radius:7px; padding:6px 14px; font-size:12px; font-weight:700; cursor:pointer; transition:all .15s; }
.btn-bulk-del:hover { background:rgba(239,68,68,.45); color:#fff; }
.btn-bulk-cancel { background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.2); color:#fff; border-radius:7px; padding:6px 14px; font-size:12px; font-weight:700; cursor:pointer; transition:all .15s; }
.btn-bulk-cancel:hover { background:rgba(255,255,255,.2); }
.row-cb { width:15px; height:15px; cursor:pointer; accent-color:#140465; }

@media(max-width:900px) { .nf-grid { grid-template-columns:1fr; } .nf-stats { grid-template-columns:repeat(2,1fr); } .aud-grid { grid-template-columns:1fr 1fr; } .deep-link-grid { grid-template-columns:1fr 1fr; } }
</style>


<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
    <div style="font-size:20px;font-weight:900;color:#1a1d2e;">Push Notifications</div>
    <a href="<?php echo e(route('admin.notifications.templates')); ?>"
       style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:10px;background:rgba(20,4,101,.07);border:1.5px solid rgba(20,4,101,.15);color:#140465;font-size:13px;font-weight:700;text-decoration:none;"
       onmouseover="this.style.background='rgba(20,4,101,.12)'" onmouseout="this.style.background='rgba(20,4,101,.07)'">
        <i class="fas fa-pen-to-square"></i> Order Status Templates
    </a>
</div>


<div class="nf-stats">
    <div class="nf-stat">
        <div class="nf-stat-icon" style="background:rgba(20,4,101,.08);color:#140465;"><i class="fas fa-paper-plane"></i></div>
        <div><div class="nf-stat-val"><?php echo e($stats['total']); ?></div><div class="nf-stat-lbl">Total Sent</div></div>
    </div>
    <div class="nf-stat">
        <div class="nf-stat-icon" style="background:rgba(16,185,129,.1);color:#059669;"><i class="fas fa-calendar-day"></i></div>
        <div><div class="nf-stat-val"><?php echo e($stats['today']); ?></div><div class="nf-stat-lbl">Sent Today</div></div>
    </div>
    <div class="nf-stat">
        <div class="nf-stat-icon" style="background:rgba(245,158,11,.1);color:#d97706;"><i class="fas fa-mobile-alt"></i></div>
        <div><div class="nf-stat-val"><?php echo e($stats['with_token']); ?></div><div class="nf-stat-lbl">Active Devices</div></div>
    </div>
</div>

<?php if(session('success')): ?>
<div style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.3);border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:#065f46;display:flex;align-items:center;gap:10px;">
    <i class="fas fa-check-circle" style="color:#10b981;"></i> <?php echo e(session('success')); ?>

</div>
<?php endif; ?>

<div class="nf-grid">


<div class="nf-card">
    <div class="nf-card-head">
        <div class="nf-card-head-icon" style="background:rgba(20,4,101,.08);color:#140465;"><i class="fas fa-paper-plane"></i></div>
        <h3>Send Push Notification</h3>
    </div>
    <div class="nf-card-body">
        <form method="POST" action="<?php echo e(route('admin.notifications.send')); ?>" id="notifForm" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>

        
        <div class="nf-mb">
            <label class="nf-label">Target Audience</label>
            <input type="hidden" name="target_type" id="targetTypeInput" value="all">
            <div class="aud-grid">
                <button type="button" class="aud-chip selected" data-val="all" onclick="selectAudience(this)">
                    <i class="fas fa-globe"></i> All Users
                </button>
                <button type="button" class="aud-chip" data-val="customers" onclick="selectAudience(this)">
                    <i class="fas fa-user"></i> Customers
                </button>
                <button type="button" class="aud-chip" data-val="vendors" onclick="selectAudience(this)">
                    <i class="fas fa-store"></i> Vendors
                </button>
                <button type="button" class="aud-chip" data-val="deliverymen" onclick="selectAudience(this)">
                    <i class="fas fa-motorcycle"></i> Deliverymen
                </button>
                <button type="button" class="aud-chip" data-val="specific" onclick="selectAudience(this)" style="grid-column:span 2;">
                    <i class="fas fa-crosshairs"></i> Specific User
                </button>
            </div>
        </div>

        
        <div id="specificUserSection" style="display:none;" class="nf-mb">
            <label class="nf-label">Search User <span style="font-size:10px;color:#94a3b8;">(must have app installed)</span></label>
            <input type="hidden" name="target_id" id="targetIdInput">
            <div class="user-search-wrap">
                <input type="text" id="userSearchInput" class="nf-input" placeholder="Search by name, email or phone…" autocomplete="off">
                <div class="user-results" id="userResults"></div>
            </div>
            <div id="selectedUserCard" style="display:none;" class="selected-user">
                <div>
                    <div class="selected-user-name" id="selUserName"></div>
                    <div class="selected-user-info" id="selUserInfo"></div>
                </div>
                <button type="button" onclick="clearUser()" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:16px;">✕</button>
            </div>
        </div>

        
        <div class="nf-mb">
            <label class="nf-label">Notification Title <span style="color:#ef4444;">*</span></label>
            <input type="text" name="title" id="notifTitle" class="nf-input" required placeholder="e.g. New Offer Available!">
        </div>

        
        <div class="nf-mb">
            <label class="nf-label">Message <span style="color:#ef4444;">*</span></label>
            <textarea name="body" id="notifBody" class="nf-input" required placeholder="Write your notification message here…"></textarea>
        </div>

        
        <div class="nf-mb">
            <label class="nf-label">Banner Image <span style="font-size:10px;color:#94a3b8;font-weight:500;">(optional — shown inside notification)</span></label>
            <div class="banner-upload" id="bannerDrop">
                <input type="file" name="banner" id="bannerInput" accept="image/*" onchange="handleBanner(this)">
                <div id="bannerPlaceholder">
                    <i class="fas fa-image" style="font-size:22px;color:#cbd5e1;margin-bottom:6px;display:block;"></i>
                    <div style="font-size:12px;color:#94a3b8;">Click or drag to upload banner image</div>
                    <div style="font-size:10px;color:#cbd5e1;margin-top:2px;">JPG, PNG — max 2MB</div>
                </div>
                <img id="bannerPreviewImg" class="banner-preview" alt="Banner preview">
            </div>
            <div id="bannerClearBtn" style="display:none;margin-top:6px;">
                <button type="button" onclick="clearBanner()" style="font-size:11px;color:#ef4444;background:none;border:none;cursor:pointer;font-weight:700;">
                    <i class="fas fa-times"></i> Remove image
                </button>
            </div>
        </div>

        
        <div class="nf-mb">
            <label class="nf-label">Open Screen When Tapped <span style="font-size:10px;color:#94a3b8;font-weight:500;">(optional)</span></label>
            <input type="hidden" name="deep_link" id="deepLinkInput" value="">
            <div class="deep-link-grid">
                <button type="button" class="dl-chip" data-val="" onclick="selectDeepLink(this)">
                    <i class="fas fa-times-circle"></i> None
                </button>
                <button type="button" class="dl-chip" data-val="/home" onclick="selectDeepLink(this)">
                    <i class="fas fa-home"></i> Home
                </button>
                <button type="button" class="dl-chip" data-val="/wallet" onclick="selectDeepLink(this)">
                    <i class="fas fa-wallet"></i> Wallet
                </button>
                <button type="button" class="dl-chip" data-val="/orders" onclick="selectDeepLink(this)">
                    <i class="fas fa-shopping-bag"></i> Orders
                </button>
                <button type="button" class="dl-chip" data-val="/efood" onclick="selectDeepLink(this)">
                    <i class="fas fa-utensils"></i> eFood
                </button>
                <button type="button" class="dl-chip" data-val="/eshop" onclick="selectDeepLink(this)">
                    <i class="fas fa-store"></i> eShop
                </button>
                <button type="button" class="dl-chip" data-val="/egrocery" onclick="selectDeepLink(this)">
                    <i class="fas fa-shopping-basket"></i> eGrocery
                </button>
                <button type="button" class="dl-chip" data-val="/eparcel" onclick="selectDeepLink(this)">
                    <i class="fas fa-box"></i> eParcel
                </button>
                <button type="button" class="dl-chip" data-val="/erent" onclick="selectDeepLink(this)">
                    <i class="fas fa-car"></i> eRent
                </button>
                <button type="button" class="dl-chip" data-val="/ehealth" onclick="selectDeepLink(this)">
                    <i class="fas fa-heartbeat"></i> eHealth
                </button>
                <button type="button" class="dl-chip" data-val="/eticket" onclick="selectDeepLink(this)">
                    <i class="fas fa-ticket-alt"></i> eTicket
                </button>
                <button type="button" class="dl-chip" data-val="/edata" onclick="selectDeepLink(this)">
                    <i class="fas fa-sim-card"></i> eData
                </button>
            </div>
        </div>

        
        <div class="notif-preview">
            <div class="notif-preview-label">Live Preview</div>
            <div class="notif-preview-card">
                <div class="notif-preview-icon"><i class="fas fa-infinity" style="color:#fff;font-size:14px;"></i></div>
                <div style="flex:1;min-width:0;">
                    <div class="notif-preview-title" id="prevTitle">Notification Title</div>
                    <div class="notif-preview-body" id="prevBody">Message body…</div>
                    <div id="prevDeepLink" class="notif-preview-deeplink" style="display:none;">
                        <i class="fas fa-link" style="font-size:9px;"></i>
                        <span id="prevDeepLinkText"></span>
                    </div>
                </div>
            </div>
            <img id="prevBanner" class="notif-preview-banner" alt="Banner">
        </div>

        <button type="submit" class="btn-send" id="sendBtn">
            <i class="fas fa-paper-plane"></i> Send Notification
        </button>

        </form>
    </div>
</div>


<div class="nf-card">
    <div class="nf-card-head">
        <div class="nf-card-head-icon" style="background:rgba(139,92,246,.1);color:#7c3aed;"><i class="fas fa-history"></i></div>
        <h3>Notification History</h3>
    </div>
    
    <div class="bulk-bar" id="bulkBar">
        <span class="bulk-bar-count" id="bulkCount">0 selected</span>
        <button type="button" class="btn-bulk-del" onclick="bulkDelete()">
            <i class="fas fa-trash-alt"></i> Delete Selected
        </button>
        <button type="button" class="btn-bulk-cancel" onclick="clearSelection()">
            Cancel
        </button>
    </div>
    <form id="bulkDeleteForm" method="POST" action="<?php echo e(route('admin.notifications.bulk-destroy')); ?>" style="display:none;">
        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
        <div id="bulkIdsContainer"></div>
    </form>
    <div class="table-wrap" style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#fafbfd;border-bottom:2px solid #f0f2f6;">
                    <th style="padding:10px 12px;width:36px;"><input type="checkbox" id="selectAll" class="row-cb" onchange="toggleAll(this)"></th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;">Notification</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;">Target</th>
                    <th style="padding:10px 10px;text-align:center;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;">Devices</th>
                    <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;">Date</th>
                    <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;"></th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="hist-row" style="border-bottom:1px solid #f4f5f8;">
                    <td style="padding:12px 12px;"><input type="checkbox" class="row-cb notif-cb" value="<?php echo e($log->id); ?>" onchange="updateBulkBar()"></td>
                    <td style="padding:12px 16px;max-width:220px;">
                        <div class="notif-title-cell"><?php echo e($log->title); ?></div>
                        <div class="notif-body-cell"><?php echo e(Str::limit($log->body, 50)); ?></div>
                        <?php if($log->image_url): ?>
                            <img src="<?php echo e($log->image_url); ?>" class="notif-img-thumb" alt="banner">
                        <?php endif; ?>
                        <?php if($log->deep_link): ?>
                            <div class="deeplink-badge"><i class="fas fa-link" style="font-size:9px;"></i> <?php echo e($log->deep_link); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="padding:12px 16px;">
                        <?php
                            $tMap = ['all'=>['All','t-all'],'customers'=>['Customers','t-cust'],'vendors'=>['Vendors','t-vend'],'deliverymen'=>['Deliverymen','t-delv'],'specific'=>['Specific','t-spec']];
                            [$tlabel,$tcls] = $tMap[$log->target_type] ?? [ucfirst($log->target_type),'t-spec'];
                        ?>
                        <span class="target-badge <?php echo e($tcls); ?>"><?php echo e($tlabel); ?></span>
                        <?php if($log->target_type === 'specific' && $log->targetUser): ?>
                        <div style="font-size:11px;color:#94a3b8;margin-top:3px;"><?php echo e($log->targetUser->name); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="padding:12px 10px;text-align:center;">
                        <span class="sent-count"><?php echo e($log->sent_count ?? '—'); ?></span>
                    </td>
                    <td style="padding:12px 16px;font-size:12px;color:#94a3b8;white-space:nowrap;">
                        <?php echo e($log->created_at->format('d M, H:i')); ?><br>
                        <span style="font-size:11px;"><?php echo e($log->sentBy?->name ?? 'System'); ?></span>
                    </td>
                    <td style="padding:12px 12px;text-align:center;">
                        <form method="POST" action="<?php echo e(route('admin.notifications.destroy', $log->id)); ?>"
                              onsubmit="return confirm('Delete this notification?')" style="display:inline;">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="btn-del">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="6" style="padding:50px 20px;text-align:center;color:#94a3b8;">
                        <i class="fas fa-bell-slash" style="font-size:32px;margin-bottom:12px;display:block;opacity:.4;"></i>
                        No notifications sent yet
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($logs->hasPages()): ?>
    <div style="padding:14px 20px;display:flex;justify-content:center;border-top:1px solid #f0f2f6;">
        <?php echo e($logs->links()); ?>

    </div>
    <?php endif; ?>
</div>

</div>

<?php $__env->startPush('scripts'); ?>
<script>
function selectAudience(btn) {
    document.querySelectorAll('.aud-chip').forEach(c => c.classList.remove('selected'));
    btn.classList.add('selected');
    const val = btn.dataset.val;
    document.getElementById('targetTypeInput').value = val;
    document.getElementById('specificUserSection').style.display = val === 'specific' ? 'block' : 'none';
    updateSendBtn();
}

function selectDeepLink(btn) {
    document.querySelectorAll('.dl-chip').forEach(c => c.classList.remove('selected'));
    btn.classList.add('selected');
    const val = btn.dataset.val;
    document.getElementById('deepLinkInput').value = val;
    const prevDl = document.getElementById('prevDeepLink');
    if (val) {
        document.getElementById('prevDeepLinkText').textContent = val;
        prevDl.style.display = 'inline-flex';
    } else {
        prevDl.style.display = 'none';
    }
}

// Select "None" by default
document.querySelector('.dl-chip[data-val=""]').classList.add('selected');

function handleBanner(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('bannerPreviewImg');
        const prevBanner = document.getElementById('prevBanner');
        img.src = e.target.result;
        img.style.display = 'block';
        prevBanner.src = e.target.result;
        prevBanner.style.display = 'block';
        document.getElementById('bannerPlaceholder').style.display = 'none';
        document.getElementById('bannerDrop').classList.add('has-image');
        document.getElementById('bannerClearBtn').style.display = 'block';
    };
    reader.readAsDataURL(file);
}

function clearBanner() {
    document.getElementById('bannerInput').value = '';
    document.getElementById('bannerPreviewImg').style.display = 'none';
    document.getElementById('prevBanner').style.display = 'none';
    document.getElementById('bannerPlaceholder').style.display = 'block';
    document.getElementById('bannerDrop').classList.remove('has-image');
    document.getElementById('bannerClearBtn').style.display = 'none';
}

function updateSendBtn() {
    const isSpec = document.getElementById('targetTypeInput').value === 'specific';
    const hasUser = document.getElementById('targetIdInput').value !== '';
    const hasTitle = document.getElementById('notifTitle').value.trim() !== '';
    const hasBody  = document.getElementById('notifBody').value.trim() !== '';
    document.getElementById('sendBtn').disabled = !hasTitle || !hasBody || (isSpec && !hasUser);
}

document.getElementById('notifTitle').addEventListener('input', e => {
    document.getElementById('prevTitle').textContent = e.target.value || 'Notification Title';
    updateSendBtn();
});
document.getElementById('notifBody').addEventListener('input', e => {
    document.getElementById('prevBody').textContent = e.target.value || 'Message body…';
    updateSendBtn();
});

// User search
let searchTimer;
document.getElementById('userSearchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 2) { document.getElementById('userResults').style.display = 'none'; return; }
    searchTimer = setTimeout(() => searchUsers(q), 300);
});
document.getElementById('userSearchInput').addEventListener('blur', function() {
    setTimeout(() => { document.getElementById('userResults').style.display = 'none'; }, 200);
});

function searchUsers(q) {
    fetch('<?php echo e(route("admin.notifications.users.search")); ?>?q=' + encodeURIComponent(q), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(users => {
        const box = document.getElementById('userResults');
        if (!users.length) { box.style.display = 'none'; return; }
        box.innerHTML = users.map(u => `
            <div class="user-result-item" onclick="selectUser(${u.id}, '${escHtml(u.name)}', '${escHtml(u.email || u.phone || '')}')">
                <div>
                    <div class="uname">${escHtml(u.name)}</div>
                    <div class="uinfo">${escHtml(u.email || '')} ${u.phone ? '· ' + escHtml(u.phone) : ''}</div>
                </div>
            </div>`).join('');
        box.style.display = 'block';
    });
}

function selectUser(id, name, info) {
    document.getElementById('targetIdInput').value = id;
    document.getElementById('userSearchInput').value = '';
    document.getElementById('userResults').style.display = 'none';
    document.getElementById('selUserName').textContent = name;
    document.getElementById('selUserInfo').textContent = info;
    document.getElementById('selectedUserCard').style.display = 'flex';
    updateSendBtn();
}

function clearUser() {
    document.getElementById('targetIdInput').value = '';
    document.getElementById('selectedUserCard').style.display = 'none';
    updateSendBtn();
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function toggleAll(cb) {
    document.querySelectorAll('.notif-cb').forEach(c => c.checked = cb.checked);
    updateBulkBar();
}

function updateBulkBar() {
    const checked = document.querySelectorAll('.notif-cb:checked');
    const bar = document.getElementById('bulkBar');
    document.getElementById('bulkCount').textContent = checked.length + ' selected';
    bar.classList.toggle('visible', checked.length > 0);
    // Sync select-all checkbox
    const all = document.querySelectorAll('.notif-cb');
    document.getElementById('selectAll').indeterminate = checked.length > 0 && checked.length < all.length;
    document.getElementById('selectAll').checked = checked.length === all.length && all.length > 0;
}

function clearSelection() {
    document.querySelectorAll('.notif-cb, #selectAll').forEach(c => { c.checked = false; c.indeterminate = false; });
    updateBulkBar();
}

function bulkDelete() {
    const checked = document.querySelectorAll('.notif-cb:checked');
    if (!checked.length) return;
    if (!confirm('Delete ' + checked.length + ' notification(s)?')) return;
    const container = document.getElementById('bulkIdsContainer');
    container.innerHTML = '';
    checked.forEach(c => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = c.value;
        container.appendChild(inp);
    });
    document.getElementById('bulkDeleteForm').submit();
}
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/notifications/index.blade.php ENDPATH**/ ?>