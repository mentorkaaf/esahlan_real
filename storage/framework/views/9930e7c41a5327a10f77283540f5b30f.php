<?php $__env->startSection('title', 'eLaundry — Items Management'); ?>
<?php $__env->startSection('content'); ?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-tshirt" style="color:var(--primary)"></i> eLaundry Items</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo e(route('admin.dashboard')); ?>">Dashboard</a></li>
            <li class="breadcrumb-item active">eLaundry Items</li>
        </ol>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')">
        <i class="fas fa-plus"></i> Add Item
    </button>
</div>

<?php if(session('success')): ?>
    <div class="alert alert-success mb-3"><?php echo e(session('success')); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span>Laundry Items (<?php echo e($items->count()); ?>)</span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Image</th>
                    <th>Item Name</th>
                    <th>Normal Price</th>
                    <th>Normal (days)</th>
                    <th>Express Price</th>
                    <th>Express (hours)</th>
                    <th>Sort</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($item->id); ?></td>
                    <td>
                        <?php if($item->image): ?>
                            <img src="<?php echo e($item->image); ?>" alt="<?php echo e($item->name); ?>" style="width:44px;height:44px;object-fit:cover;border-radius:8px;">
                        <?php else: ?>
                            <div style="width:44px;height:44px;background:#f0f0f5;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-tshirt" style="color:#aaa;font-size:18px;"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo e($item->name); ?></strong></td>
                    <td><strong class="text-success">$<?php echo e(number_format($item->normal_price, 2)); ?></strong></td>
                    <td><?php echo e($item->normal_days); ?> day<?php echo e($item->normal_days > 1 ? 's' : ''); ?></td>
                    <td><strong class="text-danger">$<?php echo e(number_format($item->express_price, 2)); ?></strong></td>
                    <td><?php echo e($item->express_hours); ?>h</td>
                    <td><?php echo e($item->sort_order); ?></td>
                    <td>
                        <span class="badge <?php echo e($item->is_active ? 'badge-success' : 'badge-danger'); ?>">
                            <?php echo e($item->is_active ? 'Active' : 'Inactive'); ?>

                        </span>
                    </td>
                    <td class="d-flex gap-2">
                        <button class="btn btn-sm btn-secondary" onclick="openEdit(
                            <?php echo e($item->id); ?>,
                            '<?php echo e(addslashes($item->name)); ?>',
                            <?php echo e($item->normal_price); ?>,
                            <?php echo e($item->express_price); ?>,
                            <?php echo e($item->normal_days); ?>,
                            <?php echo e($item->express_hours); ?>,
                            <?php echo e($item->sort_order ?? 0); ?>,
                            <?php echo e($item->is_active ? 1 : 0); ?>,
                            '<?php echo e($item->image ?? ''); ?>'
                        )">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form action="<?php echo e(route('admin.module-data.laundry.destroy', $item->id)); ?>" method="POST" onsubmit="return confirm('Delete this item?')">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="10" style="text-align:center;padding:30px;color:#888;">No items yet. Add your first laundry item.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Add Laundry Item</h3>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <form action="<?php echo e(route('admin.module-data.laundry.store')); ?>" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            
            <div class="form-group">
                <label class="form-label">Item Image</label>
                <div id="addPreviewWrap" style="display:none;margin-bottom:8px;">
                    <img id="addPreview" src="" style="width:80px;height:80px;object-fit:cover;border-radius:10px;border:2px solid #e0e0e0;">
                </div>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:10px 14px;border:1.5px dashed #ccc;border-radius:10px;background:#fafafa;">
                    <i class="fas fa-camera" style="color:#999;font-size:18px;"></i>
                    <span id="addFileName" style="color:#888;font-size:13px;">Choose image (JPG/PNG, max 5MB)</span>
                    <input type="file" name="image_file" accept="image/*" style="display:none;" onchange="previewImg(this,'addPreview','addPreviewWrap','addFileName')">
                </label>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Item Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Shirt, Trouser">
                </div>
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
            </div>

            <div style="background:#e8f5e9;border-radius:10px;padding:14px;margin-bottom:14px;">
                <div style="font-weight:700;font-size:13px;color:#2e7d32;margin-bottom:10px;"><i class="fas fa-leaf"></i> Normal Wash</div>
                <div class="grid-2">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Price ($) *</label>
                        <input type="number" name="normal_price" class="form-control" step="0.01" required placeholder="1.00">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Duration (days) *</label>
                        <input type="number" name="normal_days" class="form-control" required value="2" min="1">
                    </div>
                </div>
            </div>

            <div style="background:#fce4ec;border-radius:10px;padding:14px;margin-bottom:14px;">
                <div style="font-weight:700;font-size:13px;color:#c62828;margin-bottom:10px;"><i class="fas fa-bolt"></i> Express Wash</div>
                <div class="grid-2">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Price ($) *</label>
                        <input type="number" name="express_price" class="form-control" step="0.01" required placeholder="2.00">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Duration (hours) *</label>
                        <input type="number" name="express_hours" class="form-control" required value="24" min="1">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="is_active" value="1" checked> Active
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Add Item</button>
        </form>
    </div>
</div>


<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Edit Laundry Item</h3>
            <button class="modal-close" onclick="closeModal('editModal')">✕</button>
        </div>
        <form id="editForm" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>

            
            <div class="form-group">
                <label class="form-label">Item Image</label>
                <div id="editPreviewWrap" style="margin-bottom:8px;">
                    <img id="editPreview" src="" style="width:80px;height:80px;object-fit:cover;border-radius:10px;border:2px solid #e0e0e0;">
                </div>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:10px 14px;border:1.5px dashed #ccc;border-radius:10px;background:#fafafa;">
                    <i class="fas fa-camera" style="color:#999;font-size:18px;"></i>
                    <span id="editFileName" style="color:#888;font-size:13px;">Change image (optional)</span>
                    <input type="file" name="image_file" id="editImageFile" accept="image/*" style="display:none;" onchange="previewImg(this,'editPreview','editPreviewWrap','editFileName')">
                </label>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Item Name *</label>
                    <input type="text" name="name" id="editName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" id="editSort" class="form-control">
                </div>
            </div>

            <div style="background:#e8f5e9;border-radius:10px;padding:14px;margin-bottom:14px;">
                <div style="font-weight:700;font-size:13px;color:#2e7d32;margin-bottom:10px;"><i class="fas fa-leaf"></i> Normal Wash</div>
                <div class="grid-2">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Price ($) *</label>
                        <input type="number" name="normal_price" id="editNormal" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Duration (days) *</label>
                        <input type="number" name="normal_days" id="editNormalDays" class="form-control" required min="1">
                    </div>
                </div>
            </div>

            <div style="background:#fce4ec;border-radius:10px;padding:14px;margin-bottom:14px;">
                <div style="font-weight:700;font-size:13px;color:#c62828;margin-bottom:10px;"><i class="fas fa-bolt"></i> Express Wash</div>
                <div class="grid-2">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Price ($) *</label>
                        <input type="number" name="express_price" id="editExpress" class="form-control" step="0.01" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Duration (hours) *</label>
                        <input type="number" name="express_hours" id="editExpressHours" class="form-control" required min="1">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="is_active" id="editActive" value="1"> Active
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Save Changes</button>
        </form>
    </div>
</div>

<script>
function previewImg(input, previewId, wrapId, nameId) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(previewId).src = e.target.result;
            document.getElementById(wrapId).style.display = 'block';
            document.getElementById(nameId).textContent = input.files[0].name;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function openEdit(id, name, normalPrice, expressPrice, normalDays, expressHours, sort, isActive, imageUrl) {
    document.getElementById('editForm').action = `/admin/module-data/laundry/items/${id}`;
    document.getElementById('editName').value         = name;
    document.getElementById('editNormal').value       = normalPrice;
    document.getElementById('editExpress').value      = expressPrice;
    document.getElementById('editNormalDays').value   = normalDays;
    document.getElementById('editExpressHours').value = expressHours;
    document.getElementById('editSort').value         = sort;
    document.getElementById('editActive').checked     = isActive == 1;

    var preview = document.getElementById('editPreview');
    var wrap    = document.getElementById('editPreviewWrap');
    if (imageUrl) {
        preview.src = imageUrl;
        wrap.style.display = 'block';
    } else {
        preview.src = '';
        wrap.style.display = 'none';
    }
    // Reset file input
    document.getElementById('editImageFile').value = '';
    document.getElementById('editFileName').textContent = 'Change image (optional)';

    openModal('editModal');
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/module-data/laundry.blade.php ENDPATH**/ ?>