<div style="margin-bottom:14px">
    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Title *</label>
    <input name="title" required value="{{ old('title', $s->title ?? '') }}" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box">
</div>
<div style="margin-bottom:14px">
    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Subtitle</label>
    <input name="subtitle" value="{{ old('subtitle', $s->subtitle ?? '') }}" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box">
</div>
<div style="margin-bottom:14px">
    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Image URL</label>
    <input name="image_url" type="url" value="{{ old('image_url', $s->image_url ?? '') }}" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box" placeholder="https://…">
</div>
<div style="margin-bottom:14px">
    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Upload Image <span style="color:#9ca3af;font-weight:400">(overrides URL)</span></label>
    <input name="image_file" type="file" accept="image/*" style="font-size:13px">
</div>
<div style="margin-bottom:14px">
    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Link URL</label>
    <input name="link_url" type="url" value="{{ old('link_url', $s->link_url ?? '') }}" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box" placeholder="https://…">
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
    <div>
        <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Background Color</label>
        <input name="bg_color" type="color" value="{{ old('bg_color', $s->bg_color ?? '#1A1A2E') }}" style="width:100%;height:38px;padding:2px;border:1px solid #d1d5db;border-radius:8px;cursor:pointer">
    </div>
    <div>
        <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Button Text</label>
        <input name="button_text" value="{{ old('button_text', $s->button_text ?? 'Shop Now') }}" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box">
    </div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
    <div>
        <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">Sort Order</label>
        <input name="sort_order" type="number" value="{{ old('sort_order', $s->sort_order ?? 0) }}" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box">
    </div>
    <div style="display:flex;align-items:flex-end;padding-bottom:2px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600;color:#374151">
            <input name="is_active" type="checkbox" value="1" {{ old('is_active', $s->is_active ?? true) ? 'checked' : '' }} style="width:16px;height:16px"> Active
        </label>
    </div>
</div>
