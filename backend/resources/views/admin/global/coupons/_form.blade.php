@php $e = $editing ?? false; @endphp
<div style="display:grid;gap:12px">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:5px">COUPON NAME *</label>
            <input name="name" required placeholder="e.g. New User Deal" value="{{ old('name') }}"
                style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;box-sizing:border-box">
        </div>
        <div>
            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:5px">LABEL (badge)</label>
            <input name="label" placeholder="e.g. New User" value="{{ old('label') }}"
                style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;box-sizing:border-box">
        </div>
    </div>
    <div>
        <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:5px">DESCRIPTION</label>
        <input name="description" placeholder="Short description shown to users" value="{{ old('description') }}"
            style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;box-sizing:border-box">
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:5px">TYPE *</label>
            <select name="type" required style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;box-sizing:border-box">
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed ($)</option>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:5px">VALUE *</label>
            <input name="value" type="number" step="0.01" min="0.01" required placeholder="e.g. 30" value="{{ old('value') }}"
                style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;box-sizing:border-box">
        </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:5px">MIN ORDER ($)</label>
            <input name="minimum_order" type="number" step="0.01" placeholder="0" value="{{ old('minimum_order', 0) }}"
                style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;box-sizing:border-box">
        </div>
        <div>
            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:5px">MAX DISCOUNT ($)</label>
            <input name="maximum_discount" type="number" step="0.01" placeholder="No cap" value="{{ old('maximum_discount') }}"
                style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;box-sizing:border-box">
        </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:5px">USAGE LIMIT (total)</label>
            <input name="usage_limit" type="number" min="1" placeholder="Unlimited" value="{{ old('usage_limit') }}"
                style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;box-sizing:border-box">
        </div>
        <div>
            <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:5px">PER USER LIMIT</label>
            <input name="per_user_limit" type="number" min="1" placeholder="1" value="{{ old('per_user_limit', 1) }}"
                style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;box-sizing:border-box">
        </div>
    </div>
    <div>
        <label style="display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:5px">EXPIRES AT</label>
        <input name="expires_at" type="date" value="{{ old('expires_at') }}"
            style="width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;box-sizing:border-box">
    </div>
    <div style="display:flex;gap:20px;padding:12px;background:#f9fafb;border-radius:10px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600;color:#374151">
            <input type="checkbox" name="is_new_user_only" value="1" {{ old('is_new_user_only') ? 'checked' : '' }}
                style="width:16px;height:16px;accent-color:#1A1A2E">
            🆕 New Users Only
        </label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600;color:#374151">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', !$e) ? 'checked' : '' }}
                style="width:16px;height:16px;accent-color:#059669">
            ✅ Active
        </label>
    </div>
</div>
