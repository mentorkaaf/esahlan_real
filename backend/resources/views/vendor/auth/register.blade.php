<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Registration — eSahlan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --brand: #FF8A00; --brand-dark: #e07500; --navy: #07003B;
            --bg: #f0f2f8; --surface: #ffffff; --text: #1a1a2e;
            --text-muted: #7b7fa8; --border: #e8eaf0; --danger: #ef4444;
            --success: #10b981; --radius: 14px;
        }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, var(--navy) 0%, #1a0874 50%, #0c0148 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 30px 20px;
        }
        .register-card {
            background: #fff; border-radius: 24px; padding: 40px;
            width: 100%; max-width: 640px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.35);
        }
        .register-logo { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
        .logo-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--brand), #ff6200);
            border-radius: 14px; display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: #fff; box-shadow: 0 6px 18px rgba(255,138,0,0.4);
        }
        .logo-text h1 { font-size: 22px; font-weight: 800; color: var(--text); }
        .logo-text p  { font-size: 11px; color: var(--text-muted); letter-spacing: 1.5px; text-transform: uppercase; margin-top: 1px; }

        /* Steps */
        .steps { display: flex; align-items: center; margin-bottom: 32px; }
        .step { display: flex; align-items: center; gap: 8px; flex: 1; position: relative; }
        .step:not(:last-child)::after {
            content: ''; position: absolute; left: 32px; top: 15px;
            width: calc(100% - 32px); height: 2px; background: var(--border); z-index: 0;
        }
        .step.done::after, .step.active::after { background: var(--brand); }
        .step-num {
            width: 30px; height: 30px; border-radius: 50%; background: var(--border); color: var(--text-muted);
            display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;
            position: relative; z-index: 1; flex-shrink: 0; transition: all .2s;
        }
        .step.active .step-num { background: var(--brand); color: #fff; box-shadow: 0 4px 12px rgba(255,138,0,0.35); }
        .step.done .step-num   { background: var(--success); color: #fff; }
        .step-label { font-size: 12px; font-weight: 700; color: var(--text-muted); }
        .step.active .step-label { color: var(--brand); }
        .step.done .step-label   { color: var(--success); }

        /* Module cards */
        .module-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 14px; margin-top: 8px; }
        .module-card {
            border: 2px solid var(--border); border-radius: 14px; padding: 20px 16px;
            cursor: pointer; text-align: center; transition: all .2s; position: relative; background: #fafbff;
        }
        .module-card:hover { border-color: var(--brand); background: #fff; transform: translateY(-1px); }
        .module-card input[type=radio] { position: absolute; opacity: 0; width: 0; height: 0; }
        .module-card.selected { border-color: var(--brand); background: #fff8f0; }
        .module-card .check {
            position: absolute; top: 10px; right: 10px; width: 20px; height: 20px; border-radius: 50%;
            background: var(--border); display: flex; align-items: center; justify-content: center;
            font-size: 9px; color: transparent; transition: all .2s;
        }
        .module-card.selected .check { background: var(--brand); color: #fff; }
        .module-icon {
            width: 54px; height: 54px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; margin: 0 auto 12px; transition: all .2s;
        }
        .module-card.selected .module-icon { transform: scale(1.1); }
        .module-name { font-size: 15px; font-weight: 800; color: var(--text); margin-bottom: 4px; }
        .module-desc { font-size: 11.5px; color: var(--text-muted); line-height: 1.4; }

        /* Form */
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 12.5px; font-weight: 700; color: var(--text); margin-bottom: 7px; }
        .input-wrap { position: relative; }
        .input-wrap > i:first-child { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; z-index: 1; }
        .form-control {
            width: 100%; padding: 11px 13px 11px 38px; border-radius: 10px;
            border: 1.5px solid var(--border); font-size: 14px; color: var(--text);
            background: #fafbff; outline: none; transition: all .2s;
        }
        .form-control.no-icon { padding-left: 13px; }
        .form-control:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(255,138,0,0.1); background: #fff; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-hint { font-size: 11.5px; color: var(--text-muted); margin-top: 4px; }

        /* Password toggle */
        .pw-toggle {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: var(--text-muted);
            font-size: 13px; padding: 4px; transition: color .15s;
        }
        .pw-toggle:hover { color: var(--brand); }
        .form-control.has-toggle { padding-right: 40px; }

        /* Password strength */
        .pw-strength { margin-top: 6px; }
        .pw-strength-bar { height: 4px; border-radius: 4px; background: var(--border); overflow: hidden; }
        .pw-strength-fill { height: 100%; border-radius: 4px; transition: width .3s, background .3s; width: 0; }
        .pw-strength-text { font-size: 11px; color: var(--text-muted); margin-top: 3px; }

        /* File upload */
        .file-upload-area {
            border: 2px dashed var(--border); border-radius: 12px; padding: 24px 16px;
            text-align: center; cursor: pointer; transition: all .2s; background: #fafbff;
            position: relative;
        }
        .file-upload-area:hover { border-color: var(--brand); background: #fff8f0; }
        .file-upload-area.has-file { border-color: var(--success); background: rgba(16,185,129,0.04); }
        .file-upload-area input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .file-upload-icon { font-size: 28px; color: var(--text-muted); margin-bottom: 8px; }
        .file-upload-area.has-file .file-upload-icon { color: var(--success); }
        .file-upload-label { font-size: 13px; font-weight: 700; color: var(--text-muted); }
        .file-upload-area.has-file .file-upload-label { color: var(--success); }
        .file-upload-hint { font-size: 11.5px; color: var(--text-muted); margin-top: 4px; }
        .file-name-preview { font-size: 12px; color: var(--text-muted); margin-top: 6px; display: none; }

        /* Buttons */
        .btn-primary {
            width: 100%; padding: 14px; border-radius: 12px;
            background: linear-gradient(135deg, var(--brand), #ff6200);
            color: #fff; font-size: 15px; font-weight: 800; border: none;
            cursor: pointer; letter-spacing: .3px; box-shadow: 0 6px 20px rgba(255,138,0,0.35); transition: all .2s;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 25px rgba(255,138,0,0.45); }
        .btn-outline {
            width: 100%; padding: 12px; border-radius: 12px; background: transparent;
            color: var(--text-muted); font-size: 14px; font-weight: 600;
            border: 1.5px solid var(--border); cursor: pointer; transition: all .2s;
        }
        .btn-outline:hover { border-color: var(--text-muted); color: var(--text); }

        /* Error */
        .error-msg { display: flex; align-items: center; gap: 7px; background: rgba(239,68,68,0.07); border: 1px solid rgba(239,68,68,0.2); color: #b91c1c; padding: 10px 14px; border-radius: 9px; font-size: 13px; margin-bottom: 16px; }
        .field-error { font-size: 11.5px; color: var(--danger); margin-top: 4px; }

        .login-link { text-align: center; margin-top: 18px; font-size: 13px; color: var(--text-muted); }
        .login-link a { color: var(--brand); font-weight: 700; text-decoration: none; }

        .step-panel { display: none; }
        .step-panel.active { display: block; }
        .step-title { font-size: 19px; font-weight: 800; color: var(--text); margin-bottom: 4px; }
        .step-sub { font-size: 13px; color: var(--text-muted); margin-bottom: 24px; }

        .particles { position: fixed; inset: 0; pointer-events: none; overflow: hidden; z-index: 0; }
        .particle {
            position: absolute; border-radius: 50%; background: rgba(255,138,0,.15);
            animation: float var(--dur,8s) var(--delay,0s) infinite ease-in-out;
        }
        @keyframes float {
            0%,100% { transform: translate(0,0); opacity: 0; }
            20% { opacity: 1; }
            to { transform: translate(var(--tx,20px),var(--ty,-60px)); opacity: 0; }
        }
    </style>
</head>
<body>
<div class="particles" aria-hidden="true">
    @for($i=0;$i<15;$i++)
    <div class="particle" style="
        left:{{ rand(0,100) }}%;top:{{ rand(20,100) }}%;
        --dur:{{ rand(6,14) }}s;--delay:{{ rand(0,8) }}s;
        --tx:{{ rand(-30,30) }}px;--ty:{{ rand(-80,-20) }}px;
        width:{{ rand(4,12) }}px;height:{{ rand(4,12) }}px;
    "></div>
    @endfor
</div>

<div class="register-card" style="position:relative;z-index:1;">
    <div class="register-logo">
        <div class="logo-icon"><i class="fa-solid fa-store"></i></div>
        <div class="logo-text">
            <h1>eSahlan</h1>
            <p>Vendor Registration</p>
        </div>
    </div>

    {{-- Steps --}}
    <div class="steps">
        <div class="step done" id="step-ind-1">
            <div class="step-num"><i class="fa-solid fa-check"></i></div>
            <span class="step-label">Module</span>
        </div>
        <div class="step" id="step-ind-2">
            <div class="step-num">2</div>
            <span class="step-label">Personal</span>
        </div>
        <div class="step" id="step-ind-3">
            <div class="step-num">3</div>
            <span class="step-label">Store</span>
        </div>
    </div>

    @if($errors->any())
    <div class="error-msg"><i class="fa-solid fa-circle-exclamation"></i> {{ $errors->first() }}</div>
    @endif

    <form action="{{ route('vendor.register.post') }}" method="POST" enctype="multipart/form-data" id="reg-form">
        @csrf

        {{-- Step 1: Choose Module --}}
        <div class="step-panel active" id="panel-1">
            <div class="step-title">Choose your module</div>
            <div class="step-sub">What type of business will you be running on eSahlan?</div>
            <div class="module-grid">
                @foreach($modules as $mod)
                @php
                $icons = [
                    'efood'      => ['fa-utensils',      '#ef4444', 'rgba(239,68,68,0.1)',   'Online Food Delivery'],
                    'eshop'      => ['fa-bag-shopping',  '#3b82f6', 'rgba(59,130,246,0.1)',  'E-commerce Store'],
                    'egrocery'   => ['fa-cart-flatbed',  '#10b981', 'rgba(16,185,129,0.1)',  'Grocery & Supermarket'],
                    'ewholesale' => ['fa-boxes-stacked', '#8b5cf6', 'rgba(139,92,246,0.1)', 'Wholesale & Bulk Supply'],
                ];
                $cfg = $icons[$mod->slug] ?? ['fa-store', '#FF8A00', 'rgba(255,138,0,0.1)', ''];
                @endphp
                <label class="module-card {{ old('module_id') == $mod->id ? 'selected' : '' }}" onclick="selectModule(this)">
                    <input type="radio" name="module_id" value="{{ $mod->id }}" {{ old('module_id') == $mod->id ? 'checked' : '' }}>
                    <div class="check"><i class="fa-solid fa-check"></i></div>
                    <div class="module-icon" style="background:{{ $cfg[2] }};color:{{ $cfg[1] }};">
                        <i class="fa-solid {{ $cfg[0] }}"></i>
                    </div>
                    <div class="module-name">{{ $mod->name }}</div>
                    <div class="module-desc">{{ $cfg[3] }}</div>
                </label>
                @endforeach
            </div>
            @error('module_id')
            <div class="field-error" style="margin-top:8px;"><i class="fa-solid fa-circle-exclamation"></i> {{ $message }}</div>
            @enderror
            <div style="margin-top:20px;">
                <button type="button" class="btn-primary" onclick="nextStep(1)">Continue <i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </div>

        {{-- Step 2: Personal Info --}}
        <div class="step-panel" id="panel-2">
            <div class="step-title">Personal information</div>
            <div class="step-sub">Your account details for logging in to the vendor panel</div>

            <div class="form-group">
                <label class="form-label">Full Name <span style="color:var(--danger);">*</span></label>
                <div class="input-wrap">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. Ahmed Mohamed" required>
                </div>
                @error('name')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">Email Address <span style="color:var(--danger);">*</span></label>
                <div class="input-wrap">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="e.g. ahmed@example.com" required>
                </div>
                @error('email')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">Phone Number <span style="color:var(--danger);">*</span></label>
                <div class="input-wrap" style="display:flex;align-items:stretch;">
                    <span style="display:flex;align-items:center;padding:0 12px;background:#f0f2f8;border:1.5px solid var(--border);border-right:none;border-radius:10px 0 0 10px;font-size:14px;font-weight:700;color:var(--text);white-space:nowrap;">+252</span>
                    <input type="text" name="phone_local" id="phone-local" class="form-control no-icon" value="{{ old('phone_local') }}" placeholder="612345678" maxlength="9" inputmode="numeric" oninput="this.value=this.value.replace(/\D/,'')" style="border-radius:0 10px 10px 0;" required>
                    <input type="hidden" name="phone_full" id="phone-full">
                </div>
                <div class="form-hint">Enter local number without country code (e.g. 612345678)</div>
                @error('phone')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password <span style="color:var(--danger);">*</span></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="password" id="password" class="form-control has-toggle" placeholder="Min 8 characters" oninput="checkStrength(this.value)" required>
                        <button type="button" class="pw-toggle" onclick="togglePw('password',this)"><i class="fa-solid fa-eye"></i></button>
                    </div>
                    <div class="pw-strength">
                        <div class="pw-strength-bar"><div class="pw-strength-fill" id="pw-fill"></div></div>
                        <div class="pw-strength-text" id="pw-text"></div>
                    </div>
                    @error('password')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password <span style="color:var(--danger);">*</span></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control has-toggle" placeholder="Re-enter password" required>
                        <button type="button" class="pw-toggle" onclick="togglePw('password_confirmation',this)"><i class="fa-solid fa-eye"></i></button>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:4px;">
                <button type="button" class="btn-outline" style="width:auto;padding:12px 20px;" onclick="prevStep(2)"><i class="fa-solid fa-arrow-left"></i> Back</button>
                <button type="button" class="btn-primary" onclick="nextStep(2)">Continue <i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </div>

        {{-- Step 3: Store Info --}}
        <div class="step-panel" id="panel-3">
            <div class="step-title">Store information</div>
            <div class="step-sub">Tell customers about your store</div>

            <div class="form-group">
                <label class="form-label">Store Name <span style="color:var(--danger);">*</span></label>
                <div class="input-wrap">
                    <i class="fa-solid fa-store"></i>
                    <input type="text" name="store_name" class="form-control" value="{{ old('store_name') }}" placeholder="e.g. Ahmed's Restaurant" required>
                </div>
                @error('store_name')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">District <span style="color:var(--danger);">*</span></label>
                <div class="input-wrap">
                    <i class="fa-solid fa-location-dot"></i>
                    <select name="district_id" class="form-control" required>
                        <option value="">— Select your district —</option>
                        @foreach($districts as $d)
                        <option value="{{ $d->id }}" {{ old('district_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                @error('district_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">Store Description <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                <textarea name="store_description" class="form-control no-icon" rows="3" placeholder="Describe your store briefly...">{{ old('store_description') }}</textarea>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Business License / Government Permit <span style="color:var(--danger);">*</span>
                </label>
                <div class="file-upload-area" id="license-area">
                    <input type="file" name="business_license" id="license-input" accept=".jpg,.jpeg,.png,.pdf" onchange="handleFileSelect(this)">
                    <div class="file-upload-icon"><i class="fa-solid fa-file-arrow-up"></i></div>
                    <div class="file-upload-label">Click to upload or drag & drop</div>
                    <div class="file-upload-hint">JPG, PNG or PDF — max 5MB</div>
                    <div class="file-name-preview" id="file-name-preview"></div>
                </div>
                @error('business_license')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            {{-- Summary --}}
            <div style="background:#f8faff;border:1.5px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:20px;">
                <div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px;">Summary</div>
                <div style="display:flex;flex-direction:column;gap:6px;font-size:13.5px;">
                    <div><span style="color:var(--text-muted);">Module:</span> <strong id="sum-module">—</strong></div>
                    <div><span style="color:var(--text-muted);">Name:</span> <strong id="sum-name">—</strong></div>
                    <div><span style="color:var(--text-muted);">Phone:</span> <strong id="sum-phone">—</strong></div>
                </div>
            </div>

            <div style="background:rgba(255,138,0,0.06);border:1.5px solid rgba(255,138,0,0.2);border-radius:10px;padding:12px 14px;margin-bottom:20px;font-size:12.5px;color:#92400e;">
                <i class="fa-solid fa-clock" style="margin-right:6px;"></i>
                Your registration will be reviewed by our admin team. You'll be able to start selling within <strong>24 hours</strong> of approval.
            </div>

            <div style="display:flex;gap:10px;">
                <button type="button" class="btn-outline" style="width:auto;padding:12px 20px;" onclick="prevStep(3)"><i class="fa-solid fa-arrow-left"></i> Back</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit Application</button>
            </div>
        </div>
    </form>

    <div class="login-link">
        Already have an account? <a href="{{ route('vendor.login') }}">Sign in</a>
    </div>
</div>

<script>
const moduleNames = {
    @foreach($modules as $mod)
    '{{ $mod->id }}': '{{ $mod->name }}',
    @endforeach
};

function selectModule(card) {
    document.querySelectorAll('.module-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    card.querySelector('input').checked = true;
}

function togglePw(id, btn) {
    const input = document.getElementById(id);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.querySelector('i').className = isText ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
}

function checkStrength(val) {
    const fill = document.getElementById('pw-fill');
    const text = document.getElementById('pw-text');
    let score = 0;
    if (val.length >= 8)  score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        { pct: '0%',   color: '',        label: '' },
        { pct: '25%',  color: '#ef4444', label: 'Weak' },
        { pct: '50%',  color: '#f59e0b', label: 'Fair' },
        { pct: '75%',  color: '#3b82f6', label: 'Good' },
        { pct: '100%', color: '#10b981', label: 'Strong' },
    ];
    const l = levels[score];
    fill.style.width = l.pct;
    fill.style.background = l.color;
    text.textContent = l.label ? 'Password strength: ' + l.label : '';
    text.style.color = l.color;
}

function handleFileSelect(input) {
    const area    = document.getElementById('license-area');
    const preview = document.getElementById('file-name-preview');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        area.classList.add('has-file');
        preview.style.display = 'block';
        preview.textContent = '📎 ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
    } else {
        area.classList.remove('has-file');
        preview.style.display = 'none';
    }
}

function nextStep(from) {
    if (from === 1) {
        if (!document.querySelector('input[name=module_id]:checked')) {
            alert('Please select a module to continue.'); return;
        }
    }
    if (from === 2) {
        const name  = document.querySelector('input[name=name]').value.trim();
        const email = document.querySelector('input[name=email]').value.trim();
        const local = document.getElementById('phone-local').value.trim();
        const pw    = document.getElementById('password').value;
        const pw2   = document.getElementById('password_confirmation').value;
        if (!name)  { alert('Please enter your full name.'); return; }
        if (!email) { alert('Please enter your email address.'); return; }
        if (!local || local.length < 7) { alert('Please enter a valid local phone number.'); return; }
        if (pw.length < 8) { alert('Password must be at least 8 characters.'); return; }
        if (pw !== pw2) { alert('Passwords do not match.'); return; }

        document.getElementById('phone-full').value = '+252' + local;

        const modId = document.querySelector('input[name=module_id]:checked')?.value;
        document.getElementById('sum-module').textContent = moduleNames[modId] || '—';
        document.getElementById('sum-name').textContent   = name;
        document.getElementById('sum-phone').textContent  = fullPhone;
    }
    goStep(from + 1);
}

function prevStep(from) { goStep(from - 1); }

function goStep(n) {
    document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('panel-' + n).classList.add('active');
    for (let i = 1; i <= 3; i++) {
        const ind = document.getElementById('step-ind-' + i);
        ind.classList.remove('active', 'done');
        if (i < n) ind.classList.add('done');
        else if (i === n) ind.classList.add('active');
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.addEventListener('DOMContentLoaded', () => {
    @if($errors->any())
    goStep({{ $errors->hasAny(['module_id']) ? 1 : ($errors->hasAny(['name','email','phone','password']) ? 2 : 3) }});
    @endif
    const checked = document.querySelector('input[name=module_id]:checked');
    if (checked) checked.closest('.module-card').classList.add('selected');

    // Intercept form submit to validate license and build phone
    document.getElementById('reg-form').addEventListener('submit', function(e) {
        const license = document.getElementById('license-input');
        if (!license.files || license.files.length === 0) {
            e.preventDefault();
            alert('Please upload your Business License / Government Permit.');
            return;
        }
        // Ensure full phone is set as "phone" field
        const local = document.getElementById('phone-local').value.trim();
        document.getElementById('phone-full').value = '+252' + local;
    });
});
</script>
</body>
</html>
