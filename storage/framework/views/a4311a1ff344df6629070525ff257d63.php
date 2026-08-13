<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eSahlan Staff — Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { min-height: 100vh; background: #f0f2f5; font-family: 'Segoe UI', system-ui, sans-serif; color: #1a1a2e; }
        .topbar {
            background: #0c0148; color: #fff; padding: 0 24px; height: 64px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .topbar .brand { display: flex; align-items: center; gap: 12px; font-weight: 800; font-size: 18px; }
        .topbar .brand .logo {
            width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg,#FF8A00,#ff6200);
            display: flex; align-items: center; justify-content: center; color:#fff;
        }
        .topbar .brand span b { color: #FF8A00; }
        .topbar .user { display: flex; align-items: center; gap: 14px; font-size: 13px; }
        .topbar .avatar {
            width: 34px; height: 34px; border-radius: 50%; background: #FF8A00; color:#fff;
            display:flex; align-items:center; justify-content:center; font-weight:700;
        }
        .logout-btn {
            background: rgba(255,255,255,0.08); color: #fff; border: 1px solid rgba(255,255,255,0.18);
            padding: 8px 16px; border-radius: 9px; font-size: 13px; font-weight: 600; cursor: pointer;
            display: inline-flex; align-items: center; gap: 7px;
        }
        .logout-btn:hover { background: rgba(239,68,68,0.25); border-color: transparent; }
        .container { max-width: 1080px; margin: 0 auto; padding: 32px 24px; }
        .welcome h2 { font-size: 22px; font-weight: 800; color: #07003B; }
        .welcome p { color: #6b7280; margin-top: 4px; font-size: 14px; }
        .section-label { margin: 28px 0 14px; font-size: 13px; font-weight: 800; color: #8a8da3; text-transform: uppercase; letter-spacing: .5px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 18px; }
        .mod-card {
            background: #fff; border-radius: 16px; padding: 24px; text-decoration: none; color: inherit;
            box-shadow: 0 4px 18px rgba(7,0,59,0.06); border: 2px solid transparent; transition: all .25s;
            display: flex; flex-direction: column; gap: 14px;
        }
        .mod-card:hover { border-color: #FF8A00; box-shadow: 0 14px 34px rgba(7,0,59,0.12); }
        .mod-icon {
            width: 58px; height: 58px; border-radius: 15px;
            background: linear-gradient(135deg,#140465,#0c0148); color: #fff;
            display: flex; align-items: center; justify-content: center; font-size: 24px;
        }
        .mod-card:hover .mod-icon { background: linear-gradient(135deg,#FF8A00,#ff6200); }
        .mod-name { font-size: 17px; font-weight: 800; color: #07003B; }
        .mod-go { font-size: 12.5px; color: #FF8A00; font-weight: 700; display:flex; align-items:center; gap:6px; }
        .mod-actions { display:flex; gap:8px; margin-top:4px; flex-wrap:wrap; }
        .mod-btn {
            flex:1; padding:8px 10px; border-radius:9px; font-size:12px; font-weight:700; cursor:pointer;
            border:none; display:inline-flex; align-items:center; justify-content:center; gap:6px;
            text-decoration:none; transition:all .2s;
        }
        .mod-btn-primary { background:linear-gradient(135deg,#140465,#0c0148); color:#fff; }
        .mod-btn-primary:hover { background:linear-gradient(135deg,#FF8A00,#ff6200); color:#fff; }
        .mod-btn-outline { background:#fff; color:#140465; border:2px solid #e5e7eb; }
        .mod-btn-outline:hover { border-color:#FF8A00; color:#FF8A00; }
        .empty { background:#fff; border-radius:16px; padding:40px; text-align:center; color:#9ca3af; }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="brand">
            <div class="logo"><i class="fas fa-user-tie"></i></div>
            <span>e<b>Sahlan</b> Staff</span>
        </div>
        <div class="user">
            <div class="avatar"><?php echo e(strtoupper(substr($user->name ?? 'E', 0, 1))); ?></div>
            <div>
                <div style="font-weight:700;"><?php echo e($user->name); ?></div>
                <div style="font-size:11px;opacity:.6;">Employee</div>
            </div>
            <form method="POST" action="<?php echo e(route('employee.logout')); ?>">
                <?php echo csrf_field(); ?>
                <button class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h2>Welcome, <?php echo e($user->name); ?> 👋</h2>
            <p>Manage the modules assigned to you below.</p>
        </div>

        <div class="section-label">Your Modules</div>

        <?php if($modules->isEmpty()): ?>
            <div class="empty">
                <i class="fas fa-folder-open" style="font-size:38px;color:#d1d5db;"></i>
                <p style="margin-top:12px;">No modules have been assigned to you yet.</p>
            </div>
        <?php else: ?>
            <div class="grid">
                <?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="mod-card">
                    <div class="mod-icon"><i class="fas <?php echo e($m['icon']); ?>"></i></div>
                    <div class="mod-name"><?php echo e($m['name']); ?></div>
                    <div class="mod-actions">
                        <a class="mod-btn mod-btn-primary" href="<?php echo e(route('admin.orders.index', ['module' => $m['slug']])); ?>">
                            <i class="fas fa-receipt"></i> View Orders
                        </a>
                    </div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>
    </div>
<?php echo $__env->make('partials.order-notifier', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</body>
</html>
<?php /**PATH /var/www/esahlan/backend/resources/views/employee/dashboard.blade.php ENDPATH**/ ?>