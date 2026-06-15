@extends('admin.layouts.app')
@section('title', 'Landing Page Management')
@section('content')

<style>
.lp-hero {
    background: linear-gradient(135deg, #140465 0%, #2d1478 60%, #4a2080 100%);
    border-radius: 16px; padding: 28px 32px; margin-bottom: 28px;
    color: #fff; display: flex; align-items: center; gap: 20px;
}
.lp-hero-icon {
    width: 60px; height: 60px; background: rgba(255,255,255,0.15);
    border-radius: 14px; display: flex; align-items: center; justify-content: center;
    font-size: 26px; flex-shrink: 0;
}
.lp-hero h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 4px; }
.lp-hero p  { opacity: .7; font-size: .9rem; }

.lp-section {
    background: var(--surface); border-radius: 16px;
    border: 1px solid var(--border); margin-bottom: 24px; overflow: hidden;
}
.lp-section-header {
    display: flex; align-items: center; gap: 14px;
    padding: 20px 24px; border-bottom: 1px solid var(--border);
    cursor: pointer; user-select: none;
}
.lp-section-header:hover { background: var(--bg); }
.lp-section-icon {
    width: 40px; height: 40px; border-radius: 10px;
    background: linear-gradient(135deg, var(--brand), var(--brand-dark));
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1rem; flex-shrink: 0;
}
.lp-section-title { font-weight: 700; font-size: 1rem; color: var(--text); flex: 1; }
.lp-section-chevron { color: var(--text-muted); transition: transform .3s; }
.lp-section-chevron.open { transform: rotate(180deg); }
.lp-section-body { padding: 24px; display: none; }
.lp-section-body.open { display: block; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.form-grid.three { grid-template-columns: 1fr 1fr 1fr; }
.form-grid.one   { grid-template-columns: 1fr; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: .82rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .5px; }
.form-group input,
.form-group textarea {
    border: 1.5px solid var(--border); border-radius: 10px;
    padding: 10px 14px; font-size: .95rem; color: var(--text);
    background: var(--bg); transition: border-color .2s;
    font-family: inherit; width: 100%;
}
.form-group input:focus,
.form-group textarea:focus { outline: none; border-color: var(--brand); background: #fff; }
.form-group textarea { resize: vertical; min-height: 80px; }

.toggle-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 0; border-bottom: 1px solid var(--border);
}
.toggle-row:last-child { border-bottom: none; }
.toggle-info h4 { font-size: .95rem; font-weight: 600; color: var(--text); }
.toggle-info p  { font-size: .82rem; color: var(--text-muted); margin-top: 2px; }
.toggle-switch { position: relative; width: 48px; height: 26px; flex-shrink: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute; inset: 0; background: var(--border);
    border-radius: 26px; cursor: pointer; transition: .3s;
}
.toggle-slider:before {
    content: ''; position: absolute;
    width: 20px; height: 20px; left: 3px; top: 3px;
    background: #fff; border-radius: 50%; transition: .3s;
}
.toggle-switch input:checked + .toggle-slider { background: var(--brand); }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(22px); }

.preview-bar {
    background: var(--bg); border: 1px solid var(--border); border-radius: 10px;
    padding: 12px 16px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
    font-size: .88rem; color: var(--text-muted);
}
.preview-bar a { color: var(--brand); font-weight: 600; text-decoration: none; }
.preview-bar a:hover { text-decoration: underline; }

.save-bar {
    position: sticky; bottom: 0; background: var(--surface);
    border-top: 1px solid var(--border); padding: 16px 24px;
    display: flex; align-items: center; justify-content: space-between;
    z-index: 10; margin-top: 24px; border-radius: 0 0 16px 16px;
}
.btn-save {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--brand); color: #fff;
    border: none; padding: 12px 32px; border-radius: 10px;
    font-size: .95rem; font-weight: 700; cursor: pointer;
    transition: all .2s; font-family: inherit;
}
.btn-save:hover { background: var(--brand-dark); transform: translateY(-1px); }

.alert-success {
    background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46;
    border-radius: 10px; padding: 12px 18px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px; font-weight: 600;
}
</style>

<div class="lp-hero">
    <div class="lp-hero-icon"><i class="fas fa-paint-brush"></i></div>
    <div>
        <h1>Landing Page Management</h1>
        <p>Control all content shown on esahlan.com homepage in real time.</p>
    </div>
</div>

@if(session('success'))
<div class="alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
@endif

<div class="preview-bar">
    <i class="fas fa-eye"></i>
    <span>Live preview:</span>
    <a href="https://esahlan.com" target="_blank">esahlan.com <i class="fas fa-external-link-alt" style="font-size:.75rem;"></i></a>
</div>

<form method="POST" action="{{ route('admin.landing.update') }}">
@csrf
@method('PUT')

{{-- ── Hero Section ─────────────────────────────────────── --}}
<div class="lp-section">
    <div class="lp-section-header" onclick="toggleSection(this)">
        <div class="lp-section-icon"><i class="fas fa-star"></i></div>
        <span class="lp-section-title">Hero Section</span>
        <i class="fas fa-chevron-down lp-section-chevron open"></i>
    </div>
    <div class="lp-section-body open">
        <div class="form-grid">
            <div class="form-group">
                <label>Badge Text</label>
                <input type="text" name="landing_hero_badge" value="{{ $settings['landing_hero_badge'] ?: 'Smart Services Platform' }}">
            </div>
            <div class="form-group" style="grid-column: span 2;">
                <label>Hero Title (use \n for new line)</label>
                <input type="text" name="landing_hero_title" value="{{ $settings['landing_hero_title'] ?: 'All Services in One Place' }}">
            </div>
            <div class="form-group" style="grid-column: span 2;">
                <label>Hero Subtitle</label>
                <textarea name="landing_hero_subtitle">{{ $settings['landing_hero_subtitle'] ?: 'eSahlan is an all-in-one platform combining delivery, shopping, and daily services in a single easy-to-use app — for individuals and businesses alike.' }}</textarea>
            </div>
            <div class="form-group">
                <label>Button 1 Text</label>
                <input type="text" name="landing_hero_btn1" value="{{ $settings['landing_hero_btn1'] ?: 'Explore Services' }}">
            </div>
            <div class="form-group">
                <label>Button 2 Text</label>
                <input type="text" name="landing_hero_btn2" value="{{ $settings['landing_hero_btn2'] ?: 'Learn More' }}">
            </div>
        </div>
    </div>
</div>

{{-- ── Stats Section ────────────────────────────────────── --}}
<div class="lp-section">
    <div class="lp-section-header" onclick="toggleSection(this)">
        <div class="lp-section-icon"><i class="fas fa-chart-bar"></i></div>
        <span class="lp-section-title">Stats Bar</span>
        <i class="fas fa-chevron-down lp-section-chevron"></i>
    </div>
    <div class="lp-section-body">
        <div class="form-grid">
            <div class="form-group">
                <label>Stat 1 — Number</label>
                <input type="text" name="landing_stat1_num" value="{{ $settings['landing_stat1_num'] ?: '12+' }}">
            </div>
            <div class="form-group">
                <label>Stat 1 — Label</label>
                <input type="text" name="landing_stat1_label" value="{{ $settings['landing_stat1_label'] ?: 'Integrated Services' }}">
            </div>
            <div class="form-group">
                <label>Stat 2 — Number</label>
                <input type="text" name="landing_stat2_num" value="{{ $settings['landing_stat2_num'] ?: '24/7' }}">
            </div>
            <div class="form-group">
                <label>Stat 2 — Label</label>
                <input type="text" name="landing_stat2_label" value="{{ $settings['landing_stat2_label'] ?: 'Continuous Support' }}">
            </div>
            <div class="form-group">
                <label>Stat 3 — Number</label>
                <input type="text" name="landing_stat3_num" value="{{ $settings['landing_stat3_num'] ?: '100%' }}">
            </div>
            <div class="form-group">
                <label>Stat 3 — Label</label>
                <input type="text" name="landing_stat3_label" value="{{ $settings['landing_stat3_label'] ?: 'Safe & Reliable' }}">
            </div>
            <div class="form-group">
                <label>Stat 4 — Number</label>
                <input type="text" name="landing_stat4_num" value="{{ $settings['landing_stat4_num'] ?: '∞' }}">
            </div>
            <div class="form-group">
                <label>Stat 4 — Label</label>
                <input type="text" name="landing_stat4_label" value="{{ $settings['landing_stat4_label'] ?: 'Unlimited Potential' }}">
            </div>
        </div>
    </div>
</div>

{{-- ── CTA / Register Section ───────────────────────────── --}}
<div class="lp-section">
    <div class="lp-section-header" onclick="toggleSection(this)">
        <div class="lp-section-icon"><i class="fas fa-users"></i></div>
        <span class="lp-section-title">Registration CTA Section</span>
        <i class="fas fa-chevron-down lp-section-chevron"></i>
    </div>
    <div class="lp-section-body">
        <div class="form-grid form-grid one" style="margin-bottom:20px;">
            <div class="form-group">
                <label>Section Title</label>
                <input type="text" name="landing_cta_title" value="{{ $settings['landing_cta_title'] ?: 'Join the eSahlan Network' }}">
            </div>
            <div class="form-group" style="grid-column:span 2;">
                <label>Section Subtitle</label>
                <textarea name="landing_cta_subtitle">{{ $settings['landing_cta_subtitle'] ?: 'Are you a vendor, driver, or property agent? Register now and start earning.' }}</textarea>
            </div>
        </div>

        {{-- Vendor --}}
        <div class="toggle-row">
            <div class="toggle-info">
                <h4><i class="fas fa-store" style="color:var(--brand);margin-left:6px;"></i> Vendor Card</h4>
                <p>Show/hide the vendor registration button</p>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="landing_show_vendor" value="1" {{ ($settings['landing_show_vendor'] ?? '1') == '1' ? 'checked' : '' }}>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <div class="form-grid" style="margin:12px 0 8px;">
            <div class="form-group">
                <label>Vendor Button Title</label>
                <input type="text" name="landing_vendor_label" value="{{ $settings['landing_vendor_label'] ?: 'Vendor' }}">
            </div>
            <div class="form-group">
                <label>Vendor Button Subtitle</label>
                <input type="text" name="landing_vendor_sub" value="{{ $settings['landing_vendor_sub'] ?: 'List your store & start selling' }}">
            </div>
        </div>
        <div class="form-grid one" style="margin:0 0 20px;">
            <div class="form-group">
                <label>Vendor Registration URL</label>
                <input type="url" name="landing_vendor_url" value="{{ $settings['landing_vendor_url'] ?? '' }}" placeholder="https://esahlan.com/vendor/register">
            </div>
        </div>

        {{-- Driver --}}
        <div class="toggle-row">
            <div class="toggle-info">
                <h4><i class="fas fa-motorcycle" style="color:var(--brand);margin-left:6px;"></i> Delivery Driver Card</h4>
                <p>Show/hide the driver registration button</p>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="landing_show_driver" value="1" {{ ($settings['landing_show_driver'] ?? '1') == '1' ? 'checked' : '' }}>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <div class="form-grid" style="margin:12px 0 8px;">
            <div class="form-group">
                <label>Driver Button Title</label>
                <input type="text" name="landing_driver_label" value="{{ $settings['landing_driver_label'] ?: 'Delivery Driver' }}">
            </div>
            <div class="form-group">
                <label>Driver Button Subtitle</label>
                <input type="text" name="landing_driver_sub" value="{{ $settings['landing_driver_sub'] ?: 'Deliver orders & earn daily' }}">
            </div>
        </div>
        <div class="form-grid one" style="margin:0 0 20px;">
            <div class="form-group">
                <label>Driver Registration URL</label>
                <input type="url" name="landing_driver_url" value="{{ $settings['landing_driver_url'] ?? '' }}" placeholder="https://esahlan.com/driver/register">
            </div>
        </div>

        {{-- Agent --}}
        <div class="toggle-row">
            <div class="toggle-info">
                <h4><i class="fas fa-building" style="color:var(--brand);margin-left:6px;"></i> Property Agent Card</h4>
                <p>Show/hide the eRent agent registration button</p>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="landing_show_agent" value="1" {{ ($settings['landing_show_agent'] ?? '1') == '1' ? 'checked' : '' }}>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <div class="form-grid" style="margin:12px 0 8px;">
            <div class="form-group">
                <label>Agent Button Title</label>
                <input type="text" name="landing_agent_label" value="{{ $settings['landing_agent_label'] ?: 'Property Agent' }}">
            </div>
            <div class="form-group">
                <label>Agent Button Subtitle</label>
                <input type="text" name="landing_agent_sub" value="{{ $settings['landing_agent_sub'] ?: 'List properties on eRent' }}">
            </div>
        </div>
        <div class="form-grid one" style="margin:0 0 0;">
            <div class="form-group">
                <label>Agent Registration URL</label>
                <input type="url" name="landing_agent_url" value="{{ $settings['landing_agent_url'] ?? '' }}" placeholder="https://esahlan.com/agent/register">
            </div>
        </div>
    </div>
</div>

{{-- Save Bar --}}
<div class="save-bar">
    <span style="font-size:.88rem;color:var(--text-muted);">Changes are applied immediately after saving.</span>
    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
</div>

</form>

<script>
function toggleSection(header) {
    const body = header.nextElementSibling;
    const chevron = header.querySelector('.lp-section-chevron');
    body.classList.toggle('open');
    chevron.classList.toggle('open');
}
</script>
@endsection
