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

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
            <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:5px">
                ICON (emoji)
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
