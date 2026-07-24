@extends('admin.layouts.app')
@section('title', 'Payment Settings')

@push('styles')
<style>
.pm-page-header { background:linear-gradient(135deg,#07003B 0%,#1a0e6e 100%); border-radius:16px; padding:24px 28px; margin-bottom:24px; color:#fff; display:flex; justify-content:space-between; align-items:center; }
.pm-page-header h1 { font-size:22px; font-weight:900; margin:0 0 4px; }
.pm-page-header p  { margin:0; font-size:13px; opacity:.65; }

/* Method toggle cards */
.pm-methods-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:28px; }
@media(max-width:900px){ .pm-methods-grid{ grid-template-columns:repeat(2,1fr); } }
.pm-method-card { background:#fff; border-radius:14px; padding:20px; box-shadow:0 2px 12px rgba(0,0,0,.07); display:flex; flex-direction:column; gap:14px; border:2px solid transparent; transition:border .2s; }
.pm-method-card.enabled  { border-color:var(--accent); }
.pm-method-card.disabled { opacity:.7; }
.pm-method-icon { width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; color:#fff; }
.pm-method-name { font-size:15px; font-weight:800; color:#1a1a2e; }
.pm-method-sub  { font-size:11px; color:#aaa; margin-top:2px; }
.pm-status-pill { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:800; }
.pm-status-pill.on  { background:#E8F5E9; color:#2E7D32; }
.pm-status-pill.off { background:#FFEBEE; color:#C62828; }

/* Toggle switch */
.pm-toggle { position:relative; display:inline-block; width:48px; height:26px; }
.pm-toggle input { opacity:0; width:0; height:0; }
.pm-slider { position:absolute; cursor:pointer; inset:0; background:#ddd; border-radius:26px; transition:.25s; }
.pm-slider:before { position:absolute; content:""; height:20px; width:20px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.25s; box-shadow:0 1px 4px rgba(0,0,0,.2); }
input:checked + .pm-slider { background:#4CAF50; }
input:checked + .pm-slider:before { transform:translateX(22px); }

/* Config sections */
.pm-config { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,.07); overflow:hidden; margin-bottom:20px; }
.pm-config-header { padding:14px 20px; display:flex; align-items:center; gap:10px; border-bottom:1px solid #F0F1F5; }
.pm-config-header .icon { width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:14px; color:#fff; flex-shrink:0; }
.pm-config-header .title { font-weight:700; font-size:14px; color:#1a1a2e; }
.pm-config-header .badge-disabled { background:#FFEBEE; color:#C62828; font-size:10px; font-weight:800; padding:2px 8px; border-radius:10px; margin-left:6px; }
.pm-config-body { padding:20px; }
.pm-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
@media(max-width:700px){ .pm-form-grid{ grid-template-columns:1fr; } }
.pm-form-group { display:flex; flex-direction:column; gap:5px; }
.pm-form-group label { font-size:12px; font-weight:700; color:#555; text-transform:uppercase; letter-spacing:.4px; }
.pm-form-group input { padding:9px 12px; border:1.5px solid #E0E0E0; border-radius:9px; font-size:13px; outline:none; transition:border .2s; }
.pm-form-group input:focus { border-color:#3949AB; }
.pm-form-group input.secret { font-family:monospace; letter-spacing:2px; }
.pm-save-btn { background:#FF8A00; color:#fff; border:none; padding:9px 22px; border-radius:9px; font-size:13px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px; }
.pm-save-btn:hover { background:#E57C00; }

/* Mobile Pay accounts table */
.mp-table { width:100%; border-collapse:collapse; }
.mp-table th { padding:9px 14px; font-size:10px; text-transform:uppercase; letter-spacing:.6px; color:#999; background:#FAFBFF; font-weight:700; border-bottom:1px solid #F0F1F5; }
.mp-table td { padding:12px 14px; border-bottom:1px solid #F8F9FC; font-size:13px; vertical-align:middle; }
.mp-table tr:last-child td { border-bottom:none; }
.mp-badge-active   { background:#E8F5E9; color:#2E7D32; padding:2px 9px; border-radius:5px; font-size:11px; font-weight:800; }
.mp-badge-inactive { background:#FFEBEE; color:#C62828; padding:2px 9px; border-radius:5px; font-size:11px; font-weight:800; }

/* Disabled overlay */
.pm-disabled-overlay { background:#FFF8F8; border:1.5px dashed #FFCDD2; border-radius:10px; padding:14px; text-align:center; color:#C62828; font-size:13px; font-weight:600; }
</style>
@endpush

@section('content')

{{-- Page Header --}}
<div class="pm-page-header">
    <div>
        <h1><i class="fas fa-credit-card" style="margin-right:10px;color:#FF8A00;"></i>Payment Settings</h1>
        <p>Enable / disable payment methods and configure their settings</p>
    </div>
    <div style="font-size:12px;opacity:.5;text-align:right;">
        Changes take effect immediately<br>across all modules
    </div>
</div>

@if(session('success'))
<div style="background:#E8F5E9;border-left:4px solid #4CAF50;border-radius:10px;padding:12px 16px;margin-bottom:20px;color:#2E7D32;font-weight:600;">
    <i class="fas fa-check-circle" style="margin-right:6px;"></i>{{ session('success') }}
</div>
@endif

{{-- ① Method Toggle Cards ─────────────────────────────── --}}
@php
$methodDefs = [
    'cod'    => ['label'=>'Cash on Delivery', 'sub'=>'Customer pays at door',         'icon'=>'fa-money-bill-wave', 'color'=>'#FF8A00'],
    'waafi'  => ['label'=>'Waafi Pay',         'sub'=>'Online mobile payment gateway', 'icon'=>'fa-credit-card',     'color'=>'#7B1FA2'],
    'wallet' => ['label'=>'ePay Wallet',        'sub'=>'In-app balance top-up',        'icon'=>'fa-wallet',          'color'=>'#1565C0'],
    'mobile' => ['label'=>'Mobile Pay',         'sub'=>'USSD / EVC / Zaad transfers',  'icon'=>'fa-mobile-alt',      'color'=>'#2E7D32'],
];
@endphp

<div class="pm-methods-grid">
    @foreach($methodDefs as $key => $def)
    @php $isOn = $statuses[$key] ?? true; @endphp
    <div class="pm-method-card {{ $isOn ? 'enabled' : 'disabled' }}" id="card-{{ $key }}" style="--accent:{{ $def['color'] }};">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div class="pm-method-icon" style="background:{{ $def['color'] }};">
                <i class="fas {{ $def['icon'] }}"></i>
            </div>
            <label class="pm-toggle" title="{{ $isOn ? 'Disable' : 'Enable' }} {{ $def['label'] }}">
                <input type="checkbox" {{ $isOn ? 'checked' : '' }}
                    onchange="toggleMethod('{{ $key }}', this)">
                <span class="pm-slider"></span>
            </label>
        </div>
        <div>
            <div class="pm-method-name">{{ $def['label'] }}</div>
            <div class="pm-method-sub">{{ $def['sub'] }}</div>
        </div>
        <div>
            <span class="pm-status-pill {{ $isOn ? 'on' : 'off' }}" id="pill-{{ $key }}">
                {{ $isOn ? '● Active' : '● Disabled' }}
            </span>
        </div>
    </div>
    @endforeach
</div>

{{-- ② Waafi Pay Config ───────────────────────────────────── --}}
<div class="pm-config" id="section-waafi">
    <div class="pm-config-header">
        <div class="icon" style="background:#7B1FA2;"><i class="fas fa-credit-card"></i></div>
        <div class="title">Waafi Pay Configuration</div>
        @if(!($statuses['waafi'] ?? true))
        <span class="badge-disabled">DISABLED</span>
        @endif
    </div>
    <div class="pm-config-body">
        @if(!($statuses['waafi'] ?? true))
        <div class="pm-disabled-overlay"><i class="fas fa-ban" style="margin-right:6px;"></i>Waafi Pay is currently disabled. Enable it above to configure.</div>
        @else
        <form method="POST" action="{{ route('admin.payment-settings.waafi') }}">
            @csrf
            <div class="pm-form-grid" style="margin-bottom:16px;">
                <div class="pm-form-group">
                    <label>Merchant UID</label>
                    <input type="text" name="merchant_uid" value="{{ $waafiConfig['merchant_uid'] }}" placeholder="M0910000..." class="secret">
                </div>
                <div class="pm-form-group">
                    <label>API User ID</label>
                    <input type="text" name="api_user_id" value="{{ $waafiConfig['api_user_id'] }}" placeholder="API_userId">
                </div>
                <div class="pm-form-group">
                    <label>API Key (Password)</label>
                    <input type="password" name="api_key" value="{{ $waafiConfig['api_key'] }}" placeholder="••••••••" class="secret">
                </div>
                <div class="pm-form-group">
                    <label>API Endpoint URL</label>
                    <input type="url" name="api_url" value="{{ $waafiConfig['api_url'] }}" placeholder="https://api.waafipay.net/asm">
                </div>
                <div class="pm-form-group" style="grid-column:1/-1;">
                    <label>Payment Description</label>
                    <input type="text" name="description" value="{{ $waafiConfig['description'] }}" placeholder="eSahlan Payment">
                </div>
            </div>
            <button type="submit" class="pm-save-btn"><i class="fas fa-save"></i> Save Waafi Pay Config</button>
        </form>
        @endif
    </div>
</div>

{{-- ③ ePay Wallet Config ──────────────────────────────────── --}}
<div class="pm-config" id="section-wallet">
    <div class="pm-config-header">
        <div class="icon" style="background:#1565C0;"><i class="fas fa-wallet"></i></div>
        <div class="title">ePay Wallet Configuration</div>
        @if(!($statuses['wallet'] ?? true))
        <span class="badge-disabled">DISABLED</span>
        @endif
    </div>
    <div class="pm-config-body">
        @if(!($statuses['wallet'] ?? true))
        <div class="pm-disabled-overlay"><i class="fas fa-ban" style="margin-right:6px;"></i>ePay Wallet is currently disabled. Enable it above to configure.</div>
        @else
        <form method="POST" action="{{ route('admin.payment-settings.wallet') }}">
            @csrf
            <div class="pm-form-grid" style="margin-bottom:16px;">
                <div class="pm-form-group">
                    <label>Minimum Top-up Amount ($)</label>
                    <input type="number" name="min_topup" value="{{ $walletConfig['min_topup'] }}" min="0.01" step="0.01" placeholder="0.10">
                </div>
                <div class="pm-form-group">
                    <label>Maximum Top-up Amount ($)</label>
                    <input type="number" name="max_topup" value="{{ $walletConfig['max_topup'] }}" min="1" step="1" placeholder="1000">
                </div>
            </div>
            <button type="submit" class="pm-save-btn"><i class="fas fa-save"></i> Save Wallet Config</button>
        </form>
        @endif
    </div>
</div>

{{-- ④ Mobile Pay Accounts ────────────────────────────────── --}}
<div class="pm-config" id="section-mobile">
    <div class="pm-config-header">
        <div class="icon" style="background:#2E7D32;"><i class="fas fa-mobile-alt"></i></div>
        <div class="title">Mobile Pay Accounts</div>
        @if(!($statuses['mobile'] ?? true))
        <span class="badge-disabled">DISABLED</span>
        @endif
        <button onclick="openModal('addMobileModal')" class="pm-save-btn" style="margin-left:auto;padding:6px 14px;font-size:12px;">
            <i class="fas fa-plus"></i> Add Account
        </button>
    </div>
    <div class="pm-config-body" style="padding:0;">
        @if(!($statuses['mobile'] ?? true))
        <div style="padding:16px;"><div class="pm-disabled-overlay"><i class="fas fa-ban" style="margin-right:6px;"></i>Mobile Pay is currently disabled. Enable it above.</div></div>
        @elseif($mobileAccounts->isEmpty())
        <div style="padding:32px;text-align:center;color:#ccc;"><i class="fas fa-mobile-alt" style="font-size:32px;display:block;margin-bottom:10px;"></i>No accounts yet. Add one above.</div>
        @else
        <table class="mp-table">
            <thead>
                <tr><th>Account</th><th>Number</th><th>USSD Template</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @foreach($mobileAccounts as $acc)
                <tr>
                    <td>
                        <span style="font-size:20px;margin-right:6px;">{{ $acc->icon ?? '📱' }}</span>
                        <strong>{{ $acc->name }}</strong>
                    </td>
                    <td style="font-family:monospace;color:#555;">{{ $acc->account_number }}</td>
                    <td style="font-family:monospace;font-size:11px;color:#888;">{{ $acc->ussd_template }}</td>
                    <td>
                        <span class="{{ $acc->is_active ? 'mp-badge-active' : 'mp-badge-inactive' }}">
                            {{ $acc->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <form method="POST" action="{{ route('admin.payment-settings.mobile.toggle', $acc) }}" style="margin:0;">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn-icon" title="{{ $acc->is_active ? 'Disable' : 'Enable' }}"
                                    style="background:{{ $acc->is_active ? '#FFF3E0' : '#E8F5E9' }};color:{{ $acc->is_active ? '#E65100' : '#2E7D32' }};border:none;width:30px;height:30px;border-radius:8px;cursor:pointer;">
                                    <i class="fas {{ $acc->is_active ? 'fa-pause' : 'fa-play' }}"></i>
                                </button>
                            </form>
                            <button onclick='openEditMobile({{ json_encode($acc) }})' class="btn-icon edit" style="width:30px;height:30px;border-radius:8px;" title="Edit">
                                <i class="fas fa-pen"></i>
                            </button>
                            <form method="POST" action="{{ route('admin.payment-settings.mobile.destroy', $acc) }}" style="margin:0;" onsubmit="return confirm('Delete this account?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-icon" style="background:#FFEBEE;color:#C62828;border:none;width:30px;height:30px;border-radius:8px;cursor:pointer;" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

{{-- ── Add Mobile Account Modal ── --}}
<div class="modal-overlay" id="addMobileModal" style="display:none;">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-mobile-alt" style="color:#2E7D32;margin-right:8px;"></i>Add Mobile Pay Account</h3>
            <button class="modal-close" onclick="closeModal('addMobileModal')">✕</button>
        </div>
        <form method="POST" action="{{ route('admin.payment-settings.mobile.store') }}">
            @csrf
            <div class="modal-body" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="pm-form-group" style="grid-column:1/-1;">
                    <label>Provider Name</label>
                    <input type="text" name="name" required placeholder="e.g. EVC Plus">
                </div>
                <div class="pm-form-group">
                    <label>Account Number</label>
                    <input type="text" name="account_number" required placeholder="6141234567">
                </div>
                <div class="pm-form-group">
                    <label>Icon (emoji)</label>
                    <input type="text" name="icon" maxlength="5" placeholder="📱">
                </div>
                <div class="pm-form-group" style="grid-column:1/-1;">
                    <label>USSD Template</label>
                    <input type="text" name="ussd_template" required placeholder="*712*614{account}*{amount}#">
                    <span style="font-size:11px;color:#aaa;margin-top:3px;">Use <code>{amount}</code> as placeholder. For decimals use <code>*</code> as decimal point (EVC format).</span>
                </div>
                <div class="pm-form-group" style="grid-column:1/-1;">
                    <label>Instructions (optional)</label>
                    <input type="text" name="instructions" placeholder="Dial the USSD code then enter your PIN">
                </div>
                <div class="pm-form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" value="0" min="0">
                </div>
            </div>
            <div style="padding:16px;border-top:1px solid #F0F1F5;display:flex;gap:10px;">
                <button type="button" onclick="closeModal('addMobileModal')" style="flex:1;padding:10px;border:1.5px solid #e0e0e0;border-radius:10px;background:#fff;font-weight:600;cursor:pointer;">Cancel</button>
                <button type="submit" class="pm-save-btn" style="flex:1;justify-content:center;padding:10px;">Add Account</button>
            </div>
        </form>
    </div>
</div>

{{-- ── Edit Mobile Account Modal ── --}}
<div class="modal-overlay" id="editMobileModal" style="display:none;">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-pen" style="color:#1565C0;margin-right:8px;"></i>Edit Account</h3>
            <button class="modal-close" onclick="closeModal('editMobileModal')">✕</button>
        </div>
        <form method="POST" id="editMobileForm">
            @csrf @method('PATCH')
            <div class="modal-body" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div class="pm-form-group" style="grid-column:1/-1;">
                    <label>Provider Name</label>
                    <input type="text" name="name" id="em_name" required>
                </div>
                <div class="pm-form-group">
                    <label>Account Number</label>
                    <input type="text" name="account_number" id="em_number" required>
                </div>
                <div class="pm-form-group">
                    <label>Icon (emoji)</label>
                    <input type="text" name="icon" id="em_icon" maxlength="5">
                </div>
                <div class="pm-form-group" style="grid-column:1/-1;">
                    <label>USSD Template</label>
                    <input type="text" name="ussd_template" id="em_ussd" required>
                </div>
                <div class="pm-form-group" style="grid-column:1/-1;">
                    <label>Instructions</label>
                    <input type="text" name="instructions" id="em_instr">
                </div>
                <div class="pm-form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" id="em_sort" min="0">
                </div>
            </div>
            <div style="padding:16px;border-top:1px solid #F0F1F5;display:flex;gap:10px;">
                <button type="button" onclick="closeModal('editMobileModal')" style="flex:1;padding:10px;border:1.5px solid #e0e0e0;border-radius:10px;background:#fff;font-weight:600;cursor:pointer;">Cancel</button>
                <button type="submit" class="pm-save-btn" style="flex:1;justify-content:center;padding:10px;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
// Toggle payment method via AJAX
function toggleMethod(key, checkbox) {
    fetch(`/admin/payment-settings/toggle/${key}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
        body: JSON.stringify({})
    })
    .then(r => r.json())
    .then(data => {
        const card = document.getElementById('card-' + key);
        const pill = document.getElementById('pill-' + key);
        if (data.enabled) {
            card.classList.add('enabled');
            card.classList.remove('disabled');
            pill.className = 'pm-status-pill on';
            pill.textContent = '● Active';
        } else {
            card.classList.remove('enabled');
            card.classList.add('disabled');
            pill.className = 'pm-status-pill off';
            pill.textContent = '● Disabled';
        }
        // Reload to refresh config sections
        setTimeout(() => location.reload(), 400);
    })
    .catch(() => { checkbox.checked = !checkbox.checked; alert('Error. Try again.'); });
}

function openEditMobile(acc) {
    document.getElementById('editMobileForm').action = `/admin/payment-settings/mobile/${acc.id}`;
    document.getElementById('em_name').value   = acc.name || '';
    document.getElementById('em_number').value = acc.account_number || '';
    document.getElementById('em_icon').value   = acc.icon || '';
    document.getElementById('em_ussd').value   = acc.ussd_template || '';
    document.getElementById('em_instr').value  = acc.instructions || '';
    document.getElementById('em_sort').value   = acc.sort_order || 0;
    openModal('editMobileModal');
}
</script>
@endpush

@endsection
