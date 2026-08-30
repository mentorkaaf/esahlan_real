@extends('admin.layouts.app')
@section('title', 'Driver Peak Pay Bonus')

@push('styles')
<style>
.bonus-header {
    display: flex; align-items: center; gap: 14px; margin-bottom: 24px; flex-wrap: wrap;
}
.bonus-header h2 { margin: 0; font-size: 20px; font-weight: 800; }

.bonus-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 16px; padding: 28px; max-width: 700px;
}

.bonus-status-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 14px; border-radius: 100px; font-size: 12px; font-weight: 700;
}
.badge-on  { background: rgba(16,185,129,0.12); color: #10B981; border: 1px solid rgba(16,185,129,0.3); }
.badge-off { background: rgba(239,68,68,0.10);  color: #EF4444; border: 1px solid rgba(239,68,68,0.25); }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
@media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }

.form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
.form-group label { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
.form-group input, .form-group select, .form-group textarea {
    background: var(--bg); border: 1px solid var(--border); border-radius: 10px;
    padding: 10px 14px; color: var(--text); font-size: 14px; outline: none;
    transition: border-color 0.2s;
}
.form-group input:focus, .form-group textarea:focus { border-color: var(--brand); }

.day-grid { display: flex; gap: 8px; flex-wrap: wrap; }
.day-chip { display: none; }
.day-chip + label {
    padding: 6px 14px; border-radius: 100px; font-size: 12px; font-weight: 700;
    border: 1px solid var(--border); color: var(--text-muted); cursor: pointer;
    transition: all 0.15s;
}
.day-chip:checked + label {
    background: var(--brand); color: #fff; border-color: var(--brand);
}

.toggle-row { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; }
.toggle-row label { font-size: 14px; font-weight: 600; }

.toggle-switch { position: relative; width: 52px; height: 28px; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute; inset: 0; background: var(--border); border-radius: 100px; cursor: pointer;
    transition: background 0.2s;
}
.toggle-slider:before {
    content: ''; position: absolute; width: 22px; height: 22px;
    left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: transform 0.2s;
}
.toggle-switch input:checked + .toggle-slider { background: #10B981; }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(24px); }

.preview-box {
    background: linear-gradient(135deg, rgba(124,58,237,0.10) 0%, rgba(79,70,229,0.08) 100%);
    border: 1px solid rgba(124,58,237,0.25); border-radius: 14px;
    padding: 18px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 14px;
}
.preview-icon { font-size: 28px; }
.preview-text .t1 { font-weight: 800; font-size: 15px; }
.preview-text .t2 { font-size: 12px; color: var(--text-muted); margin-top: 3px; }
.preview-chip {
    margin-left: auto; background: rgba(255,255,255,0.12);
    padding: 6px 12px; border-radius: 8px; font-weight: 900; font-size: 16px;
    color: #7C3AED;
}

.btn-save {
    background: var(--brand); color: #fff; border: none; border-radius: 12px;
    padding: 12px 32px; font-size: 14px; font-weight: 700; cursor: pointer;
    transition: opacity 0.2s;
}
.btn-save:hover { opacity: 0.88; }
</style>
@endpush

@section('content')
<div class="bonus-header">
    <div style="font-size:28px">🔥</div>
    <div>
        <h2>Peak Pay Bonus Settings</h2>
        <p style="margin:0;font-size:13px;color:var(--text-muted)">
            Drivers earn an extra bonus per delivery during peak hours you define.
        </p>
    </div>
    @if($bonus)
        <span class="bonus-status-badge {{ $bonus->is_active ? 'badge-on' : 'badge-off' }}">
            <span>{{ $bonus->is_active ? '●' : '○' }}</span>
            {{ $bonus->is_active ? 'ACTIVE NOW' : 'INACTIVE' }}
        </span>
    @endif
</div>

@if(session('success'))
    <div style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);border-radius:10px;padding:12px 18px;margin-bottom:20px;color:#10B981;font-weight:600;">
        ✓ {{ session('success') }}
    </div>
@endif

{{-- Live Preview --}}
<div class="preview-box" id="previewBox">
    <div class="preview-icon">🔥</div>
    <div class="preview-text">
        <div class="t1" id="previewLabel">{{ $bonus->label ?? 'Peak Hours Bonus' }}</div>
        <div class="t2" id="previewSub">+${{ number_format($bonus->bonus_amount ?? 2, 2) }} per delivery · {{ $bonus->start_hour ?? 11 }}:00 – {{ $bonus->end_hour ?? 14 }}:00</div>
    </div>
    <div class="preview-chip" id="previewChip">+${{ number_format($bonus->bonus_amount ?? 2, 2) }}</div>
</div>

<div class="bonus-card">
    <form method="POST" action="{{ route('admin.drivers.bonus.update') }}">
        @csrf

        {{-- Active Toggle --}}
        <div class="toggle-row">
            <label class="toggle-switch">
                <input type="checkbox" name="is_active" value="1" id="isActive" {{ ($bonus?->is_active) ? 'checked' : '' }}>
                <span class="toggle-slider"></span>
            </label>
            <label for="isActive" style="cursor:pointer">Enable Peak Pay Bonus (drivers see 🔥 banner in app)</label>
        </div>

        {{-- Label & Amount --}}
        <div class="form-row">
            <div class="form-group">
                <label>Bonus Label</label>
                <input type="text" name="label" id="bonusLabel" value="{{ $bonus->label ?? 'Lunch Peak Bonus' }}" placeholder="e.g. Lunch Rush Bonus" required>
            </div>
            <div class="form-group">
                <label>Bonus Amount per Delivery ($)</label>
                <input type="number" name="bonus_amount" id="bonusAmount" value="{{ $bonus->bonus_amount ?? '2.00' }}" step="0.50" min="0" max="50" required>
            </div>
        </div>

        {{-- Hours --}}
        <div class="form-row">
            <div class="form-group">
                <label>Start Hour (0–23)</label>
                <input type="number" name="start_hour" id="startHour" value="{{ $bonus->start_hour ?? 11 }}" min="0" max="23" required>
                <small style="color:var(--text-muted);font-size:11px">e.g. 11 = 11:00 AM</small>
            </div>
            <div class="form-group">
                <label>End Hour (1–24)</label>
                <input type="number" name="end_hour" id="endHour" value="{{ $bonus->end_hour ?? 14 }}" min="1" max="24" required>
                <small style="color:var(--text-muted);font-size:11px">e.g. 14 = 2:00 PM</small>
            </div>
        </div>

        {{-- Days --}}
        <div class="form-group">
            <label>Active Days</label>
            @php $activeDays = array_map('intval', explode(',', $bonus->days_of_week ?? '1,2,3,4,5,6,7')); @endphp
            <div class="day-grid">
                @foreach(['Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4,'Fri'=>5,'Sat'=>6,'Sun'=>7] as $name => $num)
                    <input type="checkbox" class="day-chip" name="days_of_week[]" value="{{ $num }}" id="day{{ $num }}" {{ in_array($num, $activeDays) ? 'checked' : '' }}>
                    <label for="day{{ $num }}">{{ $name }}</label>
                @endforeach
            </div>
        </div>

        {{-- Description --}}
        <div class="form-group">
            <label>Description (optional, for your reference)</label>
            <textarea name="description" rows="2" style="resize:vertical">{{ $bonus->description ?? '' }}</textarea>
        </div>

        <button type="submit" class="btn-save">💾 Save Bonus Settings</button>
    </form>
</div>
@endsection

@push('scripts')
<script>
// Live preview update
const labelEl  = document.getElementById('bonusLabel');
const amountEl = document.getElementById('bonusAmount');
const startEl  = document.getElementById('startHour');
const endEl    = document.getElementById('endHour');

function fmt(h) {
    const hr = parseInt(h) || 0;
    const suffix = hr >= 12 ? 'PM' : 'AM';
    const disp = hr === 0 ? 12 : hr > 12 ? hr - 12 : hr;
    return `${disp}:00 ${suffix}`;
}

function updatePreview() {
    const amount = parseFloat(amountEl.value) || 0;
    document.getElementById('previewLabel').textContent = labelEl.value || 'Peak Hours Bonus';
    document.getElementById('previewSub').textContent =
        `+$${amount.toFixed(2)} per delivery · ${fmt(startEl.value)} – ${fmt(endEl.value)}`;
    document.getElementById('previewChip').textContent = `+$${amount.toFixed(2)}`;
}

[labelEl, amountEl, startEl, endEl].forEach(el => el.addEventListener('input', updatePreview));
</script>
@endpush
