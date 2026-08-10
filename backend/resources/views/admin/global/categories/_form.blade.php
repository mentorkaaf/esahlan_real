<div style="display:flex;flex-direction:column;gap:14px">
    <div>
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:5px">
            CATEGORY NAME *
        </label>
        <input name="name" required
            value="{{ old('name', isset($editing) ? '' : '') }}"
            placeholder="e.g. Electronics"
            style="width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:10px;font-size:14px">
    </div>

    {{-- Image upload --}}
    <div>
        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:5px">
            CATEGORY IMAGE
        </label>
        <div style="display:flex;align-items:center;gap:12px">
            <label style="flex:1;display:flex;align-items:center;gap:10px;padding:10px 14px;
                          border:2px dashed #d1d5db;border-radius:10px;cursor:pointer;
                          background:#f9fafb;transition:border-color .2s"
                   onmouseover="this.style.borderColor='#F59E0B'"
                   onmouseout="this.style.borderColor='#d1d5db'">
                <span style="font-size:22px">🖼</span>
                <span id="img-label-{{ isset($editing) ? 'edit' : 'add' }}"
                      style="font-size:12px;color:#6b7280;font-weight:600">
                    Click to choose image
                </span>
                <input type="file" name="image" accept="image/*" style="display:none"
                    onchange="
                        var fn = this.files[0]?.name || 'Click to choose image';
                        document.getElementById('img-label-{{ isset($editing) ? 'edit' : 'add' }}').textContent = fn;
                        if(this.files[0]) {
                            var r = new FileReader();
                            r.onload = function(e){ document.getElementById('img-preview-{{ isset($editing) ? 'edit' : 'add' }}').src = e.target.result; document.getElementById('img-preview-{{ isset($editing) ? 'edit' : 'add' }}').style.display='block'; };
                            r.readAsDataURL(this.files[0]);
                        }
                    ">
            </label>
            <img id="img-preview-{{ isset($editing) ? 'edit' : 'add' }}"
                 style="width:52px;height:52px;border-radius:10px;object-fit:cover;display:none;border:2px solid #e5e7eb">
        </div>
        <p style="font-size:11px;color:#9ca3af;margin-top:5px">JPG, PNG, WEBP · max 2MB. Leave empty to keep current image or use emoji icon.</p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
            <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:5px">
                ICON (emoji) — used if no image
            </label>
            <input name="icon" maxlength="10"
                placeholder="📱"
                style="width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:10px;font-size:18px;text-align:center">
        </div>
        <div>
            <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:5px">
                SORT ORDER
            </label>
            <input name="sort_order" type="number" min="0" value="0"
                style="width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:10px;font-size:14px">
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;
                background:#f9fafb;border-radius:10px;border:1px solid #e5e7eb">
        <input name="is_active" id="is_active_{{ isset($editing) ? 'edit' : 'add' }}"
               type="checkbox" checked value="1"
               style="width:16px;height:16px;accent-color:#1A1A2E;cursor:pointer">
        <label for="is_active_{{ isset($editing) ? 'edit' : 'add' }}"
               style="font-size:13px;font-weight:600;color:#374151;cursor:pointer">
            Active (visible in app)
        </label>
    </div>
</div>
