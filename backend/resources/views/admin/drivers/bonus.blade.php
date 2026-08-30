@extends('admin.layouts.app')
@section('title', 'Driver Peak Pay Bonus')

@push('styles')
<style>
/* ── Peak Pay Bonus Settings ──────────────────────────────────── */
.pb-page { max-width: 760px; }

/* Header */
.pb-header {
    display: flex; align-items: center; gap: 14px; margin-bottom: 28px; flex-wrap: wrap;
}
.pb-header h2 { margin: 0; font-size: 20px; font-weight: 800; color: var(--dm-text-1); }
.pb-header p  { margin: 4px 0 0; font-size: 13px; color: var(--dm-text-3); }
.pb-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 14px; border-radius: 100px; font-size: 11px; font-weight: 800; letter-spacing: 0.5px;
}
.pb-badge-on  { background: rgba(16,185,129,0.12); color: #10B981; border: 1px solid rgba(16,185,129,0.25); }
.pb-badge-off { background: rgba(239,68,68,0.10);  color: #EF4444; border: 1px solid rgba(239,68,68,0.20); }

/* Alert */
.pb-alert-success {
    background: rgba(16,185,129,0.10); border: 1px solid rgba(16,185,129,0.25);
    border-radius: 10px; padding: 12px 18px; margin-bottom: 20px;
    color: #10B981; font-weight: 600; font-size: 13px;
}

/* Preview Banner */
.pb-preview {
    display: flex; align-items: center; gap: 16px;
    background: linear-gradient(135deg, rgba(124,58,237,0.10), rgba(79,70,229,0.07));
    border: 1px solid rgba(124,58,237,0.22); border-radius: 14px;
    padding: 18px 22px; margin-bottom: 24px;
}
.pb-preview-icon { font-size: 30px; line-height: 1; }
.pb-preview-label { font-weight: 800; font-size: 14px; color: var(--dm-text-1); }
.pb-preview-sub   { font-size: 12px; color: var(--dm-text-3); margin-top: 3px; }
.pb-preview-chip  {
    margin-left: auto; background: rgba(124,58,237,0.12);
    border: 1px solid rgba(124,58,237,0.25); border-radius: 8px;
    padding: 7px 14px; font-weight: 900; font-size: 16px; color: #7C3AED; white-space: nowrap;
}

/* Card */
.pb-card {
    background: var(--dm-bg-card); border: 1px solid var(--dm-border);
    border-radius: 16px; padding: 28px 28px 24px;
}

/* Toggle row */
.pb-toggle-row {
    display: flex; align-items: center; gap: 14px; margin-bottom: 28px;
    background: var(--dm-bg-subtle); border: 1px solid var(--dm-border);
    border-radius: 12px; padding: 14px 18px;
}
.pb-toggle-label { font-size: 14px; font-weight: 600; color: var(--dm-text-1); cursor: pointer; }
.pb-toggle-sub   { font-size: 12px; color: var(--dm-text-3); margin-top: 2px; }

/* Custom toggle switch */
.pb-switch { position: relative; width: 54px; height: 30px; flex-shrink: 0; }
.pb-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
.pb-slider {
    position: absolute; inset: 0; background: var(--dm-border);
    border-radius: 100px; cursor: pointer; transition: background 0.25s;
    border: 1px solid rgba(0,0,0,0.1);
}
.pb-slider::before {
    content: ''; position: absolute;
    width: 22px; height: 22px; left: 3px; top: 3px;
    background: #fff; border-radius: 50%;
    transition: transform 0.25s;
    box-shadow: 0 1px 4px rgba(0,0,0,0.25);
}
.pb-switch input:checked + .pb-slider { background: #10B981; border-color: #10B981; }
.pb-switch input:checked + .pb-slider::before { transform: translateX(24px); }

/* Section label */
.pb-section { font-size: 11px; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.8px; color: var(--dm-text-3); margin-bottom: 10px; }

/* Two-col grid */
.pb-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
@media (max-width: 600px) { .pb-grid2 { grid-template-columns: 1fr; } }

/* Field */
.pb-field { display: flex; flex-direction: column; gap: 6px; }
.pb-field > label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.5px; color: var(--dm-text-3);
}
.pb-field small { font-size: 11px; color: var(--dm-text-3); margin-top: 2px; }

/* Day pills */
.pb-days { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
.pb-day-input { display: none !important; }
.pb-day-label {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 52px; padding: 7px 14px; border-radius: 100px; cursor: pointer;
    font-size: 12px; font-weight: 700; user-select: none;
    border: 1.5px solid var(--dm-border); color: var(--dm-text-2);
    background: var(--dm-bg-page);
    transition: all 0.15s;
}
.pb-day-label:hover { border-color: #FF8A00; color: #FF8A00; }
.pb-day-input:checked + .pb-day-label {
    background: #FF8A00; color: #fff; border-color: #FF8A00;
}

/* Divider */
.pb-divider { height: 1px; background: var(--dm-border); margin: 20px 0; }

/* Save button */
.pb-save-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: #FF8A00; color: #fff; border: none; border-radius: 12px;
    padding: 12px 32px; font-size: 14px; font-weight: 700; cursor: pointer;
    box-shadow: 0 4px 14px rgba(255,138,0,0.35);
    transition: background 0.2s, box-shadow 0.2s, transform 0.1s;
}
.pb-save-btn:hover  { background: #e07500; box-shadow: 0 6px 18px rgba(255,138,0,0.4); }
.pb-save-btn:active { transform: scale(0.98); }
.pb-save-btn svg    { width: 16px; height: 16px; flex-shrink: 0; }

/* Dark mode overrides */
body.dark-mode .pb-day-label { background: var(--dm-bg-subtle); }
body.dark-mode .pb-slider    { background: #2a2d3e; border-color: #3a3d4e; }
</style>
@endpush

@section('content')
<div class="pb-page">

    {{-- Header --}}
    <div class="pb-header">
        <span style="font-size:32px;line-height:1">🔥</span>
        <div>
            <h2>Peak Pay Bonus Settings</h2>
            <p>Drivers earn an extra bonus per delivery during peak hours you define.</p>
        </div>
        @if($bonus)
            <span class="pb-badge {{ $bonus->is_active ? 'pb-badge-on' : 'pb-badge-off' }}">
                {{ $bonus->is_active ? '● ACTIVE' : '○ INACTIVE' }}
            </span>
        @endif
    </div>

    {{-- Success flash --}}
    @if(session('success'))
        <div class="pb-alert-success">✓ {{ session('success') }}</div>
    @endif

    {{-- Live Preview (what drivers see in the app) --}}
    <div class="pb-preview" id="previewBox">
        <div class="pb-preview-icon">🔥</div>
        <div>
            <div class="pb-preview-label" id="previewLabel">{{ $bonus->label ?? 'Peak Hours Bonus' }}</div>
            <div class="pb-preview-sub" id="previewSub">
                +${{ number_format($bonus->bonus_amount ?? 2, 2) }} per delivery
                · {{ $bonus->start_hour ?? 11 }}:00 – {{ $bonus->end_hour ?? 14 }}:00
            </div>
        </div>
        <div class="pb-preview-chip" id="previewChip">+${{ number_format($bonus->bonus_amount ?? 2, 2) }}</div>
    </div>

    {{-- Form Card --}}
    <div class="pb-card">
        <form method="POST" action="{{ route('admin.drivers.bonus.update') }}">
            @csrf

            {{-- Enable Toggle --}}
            <div class="pb-toggle-row">
                <label class="pb-switch">
                    <input type="checkbox" name="is_active" value="1" id="isActive"
                        {{ ($bonus?->is_active) ? 'checked' : '' }}>
                    <span class="pb-slider"></span>
                </label>
                <div>
                    <div class="pb-toggle-label" for="isActive">Enable Peak Pay Bonus</div>
                    <div class="pb-toggle-sub">When ON, drivers see a 🔥 banner in their app during active hours.</div>
                </div>
            </div>

            {{-- Label & Amount --}}
            <div class="pb-section">Bonus Details</div>
            <div class="pb-grid2">
                <div class="pb-field">
                    <label>Bonus Label</label>
                    <input type="text" class="form-control" name="label" id="bonusLabel"
                        value="{{ $bonus->label ?? 'Lunch Peak Bonus' }}"
                        placeholder="e.g. Lunch Rush Bonus" required>
                </div>
                <div class="pb-field">
                    <label>Bonus Amount per Delivery ($)</label>
                    <input type="number" class="form-control" name="bonus_amount" id="bonusAmount"
                        value="{{ $bonus->bonus_amount ?? '2.00' }}" step="0.50" min="0" max="50" required>
                </div>
            </div>

            {{-- Hours --}}
            <div class="pb-section">Active Window</div>
            <div class="pb-grid2" style="margin-bottom:24px;">
                <div class="pb-field">
                    <label>Start Hour (0–23)</label>
                    <input type="number" class="form-control" name="start_hour" id="startHour"
                        value="{{ $bonus->start_hour ?? 11 }}" min="0" max="23" required>
                    <small>e.g. 11 = 11:00 AM</small>
                </div>
                <div class="pb-field">
                    <label>End Hour (1–24)</label>
                    <input type="number" class="form-control" name="end_hour" id="endHour"
                        value="{{ $bonus->end_hour ?? 14 }}" min="1" max="24" required>
                    <small>e.g. 14 = 2:00 PM</small>
                </div>
            </div>

            {{-- Active Days --}}
            <div class="pb-section">Active Days</div>
            @php $activeDays = array_map('intval', explode(',', $bonus->days_of_week ?? '1,2,3,4,5,6,7')); @endphp
            <div class="pb-days">
                @foreach(['Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4,'Fri'=>5,'Sat'=>6,'Sun'=>7] as $dayName => $dayNum)
                    <input type="checkbox" class="pb-day-input"
                        name="days_of_week[]" value="{{ $dayNum }}"
                        id="pb_day{{ $dayNum }}"
                        {{ in_array($dayNum, $activeDays) ? 'checked' : '' }}>
                    <label class="pb-day-label" for="pb_day{{ $dayNum }}">{{ $dayName }}</label>
                @endforeach
            </div>

            {{-- Description --}}
            <div class="pb-divider"></div>
            <div class="pb-field" style="margin-bottom:24px;">
                <label>Description (optional, for your reference)</label>
                <textarea class="form-control" name="description" rows="2">{{ $bonus->description ?? '' }}</textarea>
            </div>

            {{-- Save --}}
            <button type="submit" class="pb-save-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                </svg>
                Save Bonus Settings
            </button>
        </form>
    </div>

</div>
@endsection

@push('scripts')
<script>
const labelEl  = document.getElementById('bonusLabel');
const amountEl = document.getElementById('bonusAmount');
const startEl  = document.getElementById('startHour');
const endEl    = document.getElementById('endHour');

function fmtHr(h) {
    const hr = parseInt(h) || 0;
    const suffix = hr >= 12 ? 'PM' : 'AM';
    const disp   = hr === 0 ? 12 : hr > 12 ? hr - 12 : hr;
    return `${disp}:00 ${suffix}`;
}

function updatePreview() {
    const amt = parseFloat(amountEl.value) || 0;
    document.getElementById('previewLabel').textContent = labelEl.value || 'Peak Hours Bonus';
    document.getElementById('previewSub').textContent   =
        `+$${amt.toFixed(2)} per delivery · ${fmtHr(startEl.value)} – ${fmtHr(endEl.value)}`;
    document.getElementById('previewChip').textContent  = `+$${amt.toFixed(2)}`;
}

[labelEl, amountEl, startEl, endEl].forEach(el => el && el.addEventListener('input', updatePreview));
</script>
@endpush
