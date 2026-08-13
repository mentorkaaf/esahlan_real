<?php $__env->startSection('title', 'User Details'); ?>

<?php $__env->startPush('styles'); ?>
<style>
#user-loc-map { height:260px; border-radius:14px; overflow:hidden; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="page-header">
    <div>
        <h2 class="page-title">User: <?php echo e($user->name); ?></h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?php echo e(route('admin.users.index')); ?>">Users</a></li>
            <li class="breadcrumb-item active"><?php echo e($user->name); ?></li>
        </ol>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-warning" onclick="document.getElementById('resetPinModal').style.display='flex'">
            <i class="fas fa-key"></i> Reset PIN
        </button>
        <form action="<?php echo e(route('admin.users.status', $user->id)); ?>" method="POST">
            <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
            <?php if($user->status === 'active'): ?>
                <input type="hidden" name="status" value="banned">
                <button class="btn btn-danger"><i class="fas fa-ban"></i> Ban User</button>
            <?php else: ?>
                <input type="hidden" name="status" value="active">
                <button class="btn btn-success"><i class="fas fa-check"></i> Activate User</button>
            <?php endif; ?>
        </form>
    </div>


<div id="resetPinModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px;width:360px;max-width:90vw;">
        <h3 style="margin:0 0 6px;font-size:18px;"><i class="fas fa-key" style="color:var(--warning);margin-right:8px;"></i>Reset PIN</h3>
        <p style="font-size:13px;color:#888;margin:0 0 20px;">Set a new 4-digit PIN for <strong><?php echo e($user->name); ?></strong>. The user will be logged out of all devices.</p>
        <form action="<?php echo e(route('admin.users.reset-pin', $user->id)); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <div style="margin-bottom:16px;">
                <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px;">New PIN (4 digits)</label>
                <input type="password" name="pin" maxlength="4" pattern="\d{4}" inputmode="numeric"
                    placeholder="••••"
                    style="width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:20px;letter-spacing:8px;text-align:center;"
                    required>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="document.getElementById('resetPinModal').style.display='none'"
                    class="btn btn-outline" style="flex:1;">Cancel</button>
                <button type="submit" class="btn btn-warning" style="flex:1;"><i class="fas fa-save"></i> Save PIN</button>
            </div>
        </form>
    </div>
</div>
</div>

<div class="grid-2">
    <div>
        <div class="card">
            <div class="card-header"><span><i class="fas fa-user" style="color:var(--primary);margin-right:8px;"></i>Profile</span></div>
            <div class="card-body">
                <table>
                    <tr><td class="text-muted" style="width:130px;padding:8px 12px 8px 0;">Name</td><td class="fw-bold"><?php echo e($user->name); ?></td></tr>
                    <tr><td class="text-muted">Phone</td><td><?php echo e($user->phone ?? '—'); ?></td></tr>
                    <tr><td class="text-muted">Email</td><td><?php echo e($user->email ?? '—'); ?></td></tr>
                    <tr><td class="text-muted">Role</td><td><span class="badge badge-info"><?php echo e($user->role?->name ?? '—'); ?></span></td></tr>
                    <tr><td class="text-muted">Status</td>
                        <td><span class="badge <?php echo e($user->status === 'active' ? 'badge-success' : 'badge-danger'); ?>"><?php echo e(ucfirst($user->status)); ?></span></td>
                    </tr>
                    <tr><td class="text-muted">Language</td><td><?php echo e(strtoupper($user->preferred_language ?? 'en')); ?></td></tr>
                    <tr><td class="text-muted">Referral Code</td><td><code><?php echo e($user->referral_code ?? '—'); ?></code></td></tr>
                    <tr><td class="text-muted">Phone Verified</td>
                        <td><?php echo e($user->phone_verified_at ? $user->phone_verified_at->format('d M Y') : '—'); ?></td>
                    </tr>
                    <tr><td class="text-muted">Joined</td><td><?php echo e($user->created_at->format('d M Y H:i')); ?></td></tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span><i class="fas fa-wallet" style="color:var(--primary);margin-right:8px;"></i>Wallet</span></div>
            <div class="card-body">
                <div style="text-align:center;padding:20px;">
                    <div style="font-size:36px;font-weight:700;color:var(--primary);">
                        $<?php echo e(number_format($user->wallet?->balance ?? 0, 2)); ?>

                    </div>
                    <div class="text-muted">Current Balance (<?php echo e($user->wallet?->currency ?? 'USD'); ?>)</div>
                </div>
            </div>
        </div>
    </div>

    <div>
        
        <?php
            $mapLat = $user->latitude  ?? $user->district?->latitude;
            $mapLng = $user->longitude ?? $user->district?->longitude;
            $hasGps = (bool)$user->latitude;
        ?>
        <?php if($mapLat && $mapLng): ?>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <?php if($hasGps): ?>
                    <span><i class="fas fa-map-marker-alt" style="color:#FF8A00;margin-right:8px;"></i>Live Location</span>
                    <?php if($user->location_updated_at): ?>
                    <span style="font-size:12px;color:#999;margin-left:8px;"><?php echo e($user->location_updated_at->diffForHumans()); ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span><i class="fas fa-map-marker-alt" style="color:#3949AB;margin-right:8px;"></i>District Location — <?php echo e($user->district?->name ?? '—'); ?></span>
                    <span style="font-size:11px;color:#bbb;margin-left:8px;">No GPS yet</span>
                <?php endif; ?>
            </div>
            <div style="padding:12px;">
                <div id="user-loc-map"></div>
                <?php if($hasGps): ?>
                <div style="margin-top:8px;font-size:12px;color:#888;text-align:center;">
                    <i class="fas fa-crosshairs" style="margin-right:4px;color:#FF8A00;"></i>
                    <?php echo e(number_format($user->latitude, 6)); ?>, <?php echo e(number_format($user->longitude, 6)); ?>

                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><span><i class="fas fa-shopping-bag" style="color:var(--primary);margin-right:8px;"></i>Recent Orders</span></div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Order #</th><th>Module</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $user->orders ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><a href="<?php echo e(route('admin.orders.show', $order->id)); ?>" class="text-primary"><?php echo e($order->order_number); ?></a></td>
                            <td><?php echo e(strtoupper($order->module_slug ?? '—')); ?></td>
                            <td>$<?php echo e(number_format($order->total_amount, 2)); ?></td>
                            <td>
                                <?php $sc = ['delivered'=>'success','pending'=>'warning','cancelled'=>'danger'][$order->status] ?? 'secondary' ?>
                                <span class="badge badge-<?php echo e($sc); ?>"><?php echo e(ucfirst(str_replace('_',' ',$order->status))); ?></span>
                            </td>
                            <td><?php echo e($order->created_at->format('d M')); ?></td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="5" style="text-align:center;padding:20px;color:#888;">No orders yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php if(isset($mapLat) && $mapLat): ?>
<script>
function initUserLocMap() {
    var hasGps  = <?php echo e($hasGps ? 'true' : 'false'); ?>;
    var pos     = { lat: <?php echo e($mapLat); ?>, lng: <?php echo e($mapLng); ?> };
    var map = new google.maps.Map(document.getElementById('user-loc-map'), {
        zoom: hasGps ? 15 : 13,
        center: pos,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false,
    });
    var marker = new google.maps.Marker({
        position: pos,
        map: map,
        title: '<?php echo e(addslashes($user->name)); ?>',
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 11,
            fillColor: hasGps ? '#FF8A00' : '#3949AB',
            fillOpacity: 1,
            strokeColor: '#07003B',
            strokeWeight: 3,
        }
    });
    var label = hasGps
        ? '<strong><?php echo e(addslashes($user->name)); ?></strong><br><span style="font-size:12px;color:#888;"><?php echo e(addslashes($user->phone ?? '')); ?></span>'
        : '<strong><?php echo e(addslashes($user->name)); ?></strong><br><span style="font-size:12px;color:#3949AB;">District: <?php echo e(addslashes($user->district?->name ?? '')); ?></span>';
    var iw = new google.maps.InfoWindow({ content: '<div style="padding:6px 4px;">' + label + '</div>' });
    iw.open(map, marker);
}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA9J4TSypPZv3cr8Zlabn0BSDICD_Ibp-A&callback=initUserLocMap" async defer></script>
<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/users/show.blade.php ENDPATH**/ ?>