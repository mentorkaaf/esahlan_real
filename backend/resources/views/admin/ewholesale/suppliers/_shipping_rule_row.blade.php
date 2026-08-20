<div style="display:flex;gap:10px;align-items:center;margin-bottom:8px;flex-wrap:wrap">
    <select name="rules[{{ $idx }}][basis]" style="padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:12px">
        @foreach(['per_carton','per_kg','per_cbm','flat_by_zone'] as $b)
        <option value="{{ $b }}" @selected(($rule?->basis??'')===$b)>{{ $b }}</option>
        @endforeach
    </select>
    <input name="rules[{{ $idx }}][rate]" value="{{ $rule?->rate ?? '' }}" placeholder="Rate $" type="number" step="0.01" style="width:80px;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:12px">
    <input name="rules[{{ $idx }}][free_over]" value="{{ $rule?->free_over ?? '' }}" placeholder="Free over $" type="number" step="0.01" style="width:90px;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:12px">
    <input name="rules[{{ $idx }}][zone]" value="{{ $rule?->zone ?? '' }}" placeholder="Zone (optional)" style="width:120px;padding:7px;border:1px solid #d1d5db;border-radius:5px;font-size:12px">
    <label style="font-size:12px;display:flex;align-items:center;gap:4px">
        <input type="checkbox" name="rules[{{ $idx }}][is_active]" value="1" @checked($rule?->is_active ?? true)> Active
    </label>
</div>
