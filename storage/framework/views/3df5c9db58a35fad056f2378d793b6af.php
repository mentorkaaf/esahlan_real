<?php $__env->startSection('title', 'Roles & Access'); ?>
<?php $__env->startSection('content'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Roles &amp; Access</h1>
        <ul class="breadcrumb">
            <li><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li>
            <li>Roles &amp; Access</li>
        </ul>
    </div>
</div>

<?php if(session('success')): ?>
<div class="alert alert-success" style="margin-bottom:16px;padding:12px 16px;border-radius:10px;background:#e7f8ee;color:#0f7a43;font-weight:600;">
    <i class="fas fa-check-circle"></i> <?php echo e(session('success')); ?>

</div>
<?php endif; ?>
<?php if($errors->any()): ?>
<div class="alert alert-danger" style="margin-bottom:16px;padding:12px 16px;border-radius:10px;background:#fdecec;color:#c0392b;font-weight:600;">
    <i class="fas fa-exclamation-circle"></i> <?php echo e($errors->first()); ?>

</div>
<?php endif; ?>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;width:100%;align-items:center;">
            <div style="flex:1;min-width:200px;">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search name, phone, email…" value="<?php echo e($search); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <?php if($search): ?>
            <a href="<?php echo e(route('admin.access.index')); ?>" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="overflow-x:auto;">
    <table class="table">
        <thead>
            <tr>
                <th>User</th>
                <th>Contact</th>
                <th>Role</th>
                <th>Assigned Modules</th>
                <th style="text-align:right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php $modIds = $u->managedModules->pluck('id')->all(); ?>
            <tr>
                <td>
                    <div style="font-weight:700;color:#0c0148;"><?php echo e($u->name); ?></div>
                    <div style="font-size:12px;color:#8a8da3;">#<?php echo e($u->id); ?></div>
                </td>
                <td style="font-size:13px;color:#555;">
                    <?php echo e($u->phone ?? '—'); ?><br>
                    <span style="color:#8a8da3;"><?php echo e($u->email ?? ''); ?></span>
                </td>
                <td>
                    <span style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;
                        background:<?php echo e($u->role?->slug === 'employee' ? '#fff3e0' : '#eef0ff'); ?>;
                        color:<?php echo e($u->role?->slug === 'employee' ? '#FF8A00' : '#140465'); ?>;">
                        <?php echo e($u->role?->name ?? 'No role'); ?>

                    </span>
                </td>
                <td>
                    <?php if($u->role?->slug === 'employee'): ?>
                        <?php $__empty_2 = true; $__currentLoopData = $u->managedModules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                            <span style="display:inline-block;margin:2px;padding:3px 9px;border-radius:14px;font-size:11px;font-weight:600;background:#f0f2f5;color:#444;"><?php echo e($m->name); ?></span>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                            <span style="color:#c0392b;font-size:12px;">⚠ none assigned</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:#bbb;font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;">
                    <button type="button" class="btn btn-outline btn-sm js-manage"
                        data-id="<?php echo e($u->id); ?>"
                        data-name="<?php echo e($u->name); ?>"
                        data-role="<?php echo e($u->role_id); ?>"
                        data-modules="<?php echo e(implode(',', $modIds)); ?>">
                        <i class="fas fa-user-shield"></i> Manage
                    </button>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="5" style="text-align:center;color:#8a8da3;padding:30px;">No users found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <div style="padding:14px;"><?php echo e($users->links()); ?></div>
</div>

<!-- ── Manage Access modal ─────────────────────────────────────────────────── -->
<div id="accessModal" style="display:none;position:fixed;inset:0;background:rgba(7,0,59,0.55);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:#fff;border-radius:16px;max-width:520px;width:100%;max-height:90vh;overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <form id="accessForm" method="POST">
      <?php echo csrf_field(); ?>
      <?php echo method_field('PUT'); ?>
      <div style="padding:18px 20px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between;">
        <h3 style="margin:0;color:#0c0148;font-size:17px;font-weight:800;">Manage Access — <span id="amName"></span></h3>
        <button type="button" class="js-close" style="border:0;background:none;font-size:22px;color:#999;cursor:pointer;">&times;</button>
      </div>

      <div style="padding:20px;">
        <label style="display:block;font-weight:700;color:#0c0148;margin-bottom:6px;">Role</label>
        <select name="role_id" id="amRole" class="form-control" style="width:100%;margin-bottom:18px;">
          <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($r->id); ?>" data-slug="<?php echo e($r->slug); ?>"><?php echo e($r->name); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>

        <div id="amModulesWrap" style="display:none;">
          <label style="display:block;font-weight:700;color:#0c0148;margin-bottom:8px;">
            Modules this employee can manage
          </label>
          <div style="font-size:12px;color:#8a8da3;margin-bottom:10px;">
            The employee gets full management access to only the modules ticked below.
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <label style="display:flex;align-items:center;gap:8px;padding:9px 11px;border:1px solid #e6e6ef;border-radius:10px;cursor:pointer;font-size:13px;">
              <input type="checkbox" name="modules[]" value="<?php echo e($m->id); ?>" class="am-mod" data-mod="<?php echo e($m->id); ?>">
              <span style="font-weight:600;color:#333;"><?php echo e($m->name); ?></span>
            </label>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>
        </div>
      </div>

      <div style="padding:16px 20px;border-top:1px solid #eee;display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="btn btn-outline js-close">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var modal   = document.getElementById('accessModal');
  var form    = document.getElementById('accessForm');
  var roleSel = document.getElementById('amRole');
  var wrap    = document.getElementById('amModulesWrap');
  var nameEl  = document.getElementById('amName');
  var baseUrl = "<?php echo e(url('admin/access')); ?>";

  function toggleModules() {
    var slug = roleSel.options[roleSel.selectedIndex].getAttribute('data-slug');
    wrap.style.display = (slug === 'employee') ? 'block' : 'none';
  }

  function open(btn) {
    nameEl.textContent = btn.getAttribute('data-name');
    form.action = baseUrl + '/' + btn.getAttribute('data-id');
    roleSel.value = btn.getAttribute('data-role') || '';

    var assigned = (btn.getAttribute('data-modules') || '').split(',').filter(Boolean);
    document.querySelectorAll('.am-mod').forEach(function (cb) {
      cb.checked = assigned.indexOf(cb.getAttribute('data-mod')) !== -1;
    });

    toggleModules();
    modal.style.display = 'flex';
  }

  function close() { modal.style.display = 'none'; }

  document.querySelectorAll('.js-manage').forEach(function (b) {
    b.addEventListener('click', function () { open(b); });
  });
  document.querySelectorAll('.js-close').forEach(function (b) {
    b.addEventListener('click', close);
  });
  roleSel.addEventListener('change', toggleModules);
  modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
})();
</script>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/access/index.blade.php ENDPATH**/ ?>