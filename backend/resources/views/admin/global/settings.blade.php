@extends('admin.layouts.app')
@section('title', 'Global Store Settings')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">⚙️ Global Store Settings</h1>
        <p class="page-subtitle">Payments, currencies, shipping, and store configuration</p>
    </div>
</div>

@if(session('success'))
<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:13px">✅ {{ session('success') }}</div>
@endif

{{-- ═══════════════════════════════════════════════════════════
     GLOBAL STORE MASTER TOGGLE — Real-time via Reverb
     Marka la click gareeyo si toos ah app-ka ayuu u fal-gashaa
     ═══════════════════════════════════════════════════════════ --}}
@php $storeEnabled = \App\Models\Global\GlobalSetting::getBool('global_store_enabled', true); @endphp
<div id="storeToggleCard" style="
    background: linear-gradient(135deg, {{ $storeEnabled ? '#065f46' : '#7f1d1d' }}, {{ $storeEnabled ? '#047857' : '#991b1b' }});
    border-radius: 16px; padding: 22px 28px; margin-bottom: 24px;
    display: flex; align-items: center; gap: 20px;
    box-shadow: 0 4px 24px {{ $storeEnabled ? 'rgba(5,150,105,.35)' : 'rgba(220,38,38,.35)' }};
    transition: all .4s ease;
">
    <div style="width:56px;height:56px;background:rgba(255,255,255,.15);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i id="storeToggleIcon" class="fas {{ $storeEnabled ? 'fa-store' : 'fa-store-slash' }}" style="font-size:24px;color:#fff"></i>
    </div>
    <div style="flex:1">
        <div style="font-size:16px;font-weight:800;color:#fff">Global Store</div>
        <div id="storeToggleStatus" style="font-size:13px;color:rgba(255,255,255,.75);margin-top:2px">
            {{ $storeEnabled ? '🟢 Open — customers can browse and purchase' : '🔴 Closed — store is hidden from customers' }}
        </div>
        <div id="storeToggleFeedback" style="font-size:11px;color:rgba(255,255,255,.5);margin-top:4px;display:none">
            ⚡ Broadcast-ku wuxuu u socdaa app-ka…
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:12px;flex-shrink:0">
        <span id="storeToggleBadge" style="
            font-size:11px;font-weight:800;padding:4px 12px;border-radius:20px;
            background:rgba(255,255,255,.2);color:#fff;text-transform:uppercase;letter-spacing:.05em;
        ">{{ $storeEnabled ? 'OPEN' : 'CLOSED' }}</span>
        <label style="position:relative;display:inline-block;width:56px;height:30px;cursor:pointer">
            <input type="checkbox" id="storeToggleInput" {{ $storeEnabled ? 'checked' : '' }}
                style="opacity:0;width:0;height:0" onchange="toggleGlobalStore(this.checked)">
            <span id="storeToggleSlider" style="
                position:absolute;inset:0;border-radius:30px;transition:.3s;
                background:{{ $storeEnabled ? '#fff' : 'rgba(255,255,255,.3)' }};
            "></span>
            <span id="storeToggleThumb" style="
                position:absolute;top:3px;left:{{ $storeEnabled ? '29px' : '3px' }};
                width:24px;height:24px;border-radius:50%;transition:.3s;
                background:{{ $storeEnabled ? '#059669' : '#991b1b' }};
                box-shadow:0 2px 6px rgba(0,0,0,.3);
            "></span>
        </label>
    </div>
</div>

<script>
function toggleGlobalStore(enabled) {
    const card    = document.getElementById('storeToggleCard');
    const icon    = document.getElementById('storeToggleIcon');
    const status  = document.getElementById('storeToggleStatus');
    const badge   = document.getElementById('storeToggleBadge');
    const slider  = document.getElementById('storeToggleSlider');
    const thumb   = document.getElementById('storeToggleThumb');
    const fb      = document.getElementById('storeToggleFeedback');

    // Optimistic UI update
    if (enabled) {
        card.style.background   = 'linear-gradient(135deg,#065f46,#047857)';
        card.style.boxShadow    = '0 4px 24px rgba(5,150,105,.35)';
        icon.className          = 'fas fa-store';
        status.textContent      = '🟢 Open — customers can browse and purchase';
        badge.textContent       = 'OPEN';
        slider.style.background = '#fff';
        thumb.style.left        = '29px';
        thumb.style.background  = '#059669';
    } else {
        card.style.background   = 'linear-gradient(135deg,#7f1d1d,#991b1b)';
        card.style.boxShadow    = '0 4px 24px rgba(220,38,38,.35)';
        icon.className          = 'fas fa-store-slash';
        status.textContent      = '🔴 Closed — store is hidden from customers';
        badge.textContent       = 'CLOSED';
        slider.style.background = 'rgba(255,255,255,.3)';
        thumb.style.left        = '3px';
        thumb.style.background  = '#991b1b';
    }

    fb.style.display = 'block';

    fetch('{{ route('admin.global.settings.toggle-store') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ enabled }),
    })
    .then(r => r.json())
    .then(d => {
        fb.textContent = d.success
            ? '✅ App-ka wuu helay — real-time updated'
            : '❌ Khalad dhacay';
        setTimeout(() => { fb.style.display = 'none'; }, 3000);
    })
    .catch(() => {
        fb.textContent = '❌ Server connection failed';
        setTimeout(() => { fb.style.display = 'none'; }, 3000);
    });
}
</script>

<form method="POST" action="{{ route('admin.global.settings.update') }}">
@csrf @method('PUT')

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

{{-- Stripe --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;background:linear-gradient(135deg,#635bff08,#fff);display:flex;align-items:center;gap:10px">
        <div style="width:36px;height:36px;background:#635bff;border-radius:8px;display:flex;align-items:center;justify-content:center">
            <i class="fas fa-credit-card" style="color:#fff"></i>
        </div>
        <div>
            <div style="font-size:14px;font-weight:700;color:#111">Stripe</div>
            <div style="font-size:11px;color:#9ca3af">Credit/Debit card payments</div>
        </div>
        <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
            <label style="position:relative;display:inline-block;width:40px;height:22px">
                <input type="checkbox" name="global_stripe_enabled" value="1" {{ $settings->get('global_stripe_enabled')=='1'?'checked':'' }} style="opacity:0;width:0;height:0" onchange="document.getElementById('stripeBadge').textContent=this.checked?'Enabled':'Disabled';document.getElementById('stripeBadge').style.background=this.checked?'#d1fae5':'#fee2e2';document.getElementById('stripeBadge').style.color=this.checked?'#065f46':'#991b1b'">
                <span style="position:absolute;cursor:pointer;inset:0;background:#e5e7eb;border-radius:22px;transition:.2s" class="toggle-stripe"></span>
            </label>
            <span id="stripeBadge" style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;{{ $settings->get('global_stripe_enabled')=='1'?'background:#d1fae5;color:#065f46':'background:#fee2e2;color:#991b1b' }}">
                {{ $settings->get('global_stripe_enabled')=='1'?'Enabled':'Disabled' }}
            </span>
        </div>
    </div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:12px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="global_stripe_test_mode" value="1" {{ $settings->get('global_stripe_test_mode','1')=='1'?'checked':'' }} style="width:15px;height:15px;accent-color:#635bff">
            <span style="font-size:13px;color:#374151;font-weight:600">Test Mode (sandbox)</span>
            <span style="font-size:10px;color:#f59e0b;background:#fffbeb;padding:1px 6px;border-radius:6px;font-weight:700">SAFE TO ENABLE</span>
        </label>
        @foreach([
            ['global_stripe_pk_live','Live Publishable Key','pk_live_…'],
            ['global_stripe_sk_live','Live Secret Key','sk_live_…'],
            ['global_stripe_pk_test','Test Publishable Key','pk_test_…'],
            ['global_stripe_sk_test','Test Secret Key','sk_test_…'],
            ['global_stripe_webhook_secret','Webhook Secret','whsec_…'],
        ] as [$k,$l,$p])
        <div>
            <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">{{ $l }}</label>
            <input name="{{ $k }}" value="{{ $settings->get($k,'') }}" placeholder="{{ $p }}" style="width:100%;padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;font-family:monospace" {{ str_contains($k,'sk_')||str_contains($k,'webhook')?'type="password"':'type="text"' }}>
        </div>
        @endforeach
    </div>
</div>

{{-- PayPal --}}
<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;background:linear-gradient(135deg,#0070ba08,#fff);display:flex;align-items:center;gap:10px">
        <div style="width:36px;height:36px;background:#003087;border-radius:8px;display:flex;align-items:center;justify-content:center">
            <i class="fab fa-paypal" style="color:#009cde;font-size:18px"></i>
        </div>
        <div>
            <div style="font-size:14px;font-weight:700;color:#111">PayPal</div>
            <div style="font-size:11px;color:#9ca3af">PayPal & Pay Later</div>
        </div>
        <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
            <label style="position:relative;display:inline-block;width:40px;height:22px">
                <input type="checkbox" name="global_paypal_enabled" value="1" {{ $settings->get('global_paypal_enabled')=='1'?'checked':'' }} style="opacity:0;width:0;height:0" onchange="document.getElementById('paypalBadge').textContent=this.checked?'Enabled':'Disabled'">
                <span style="position:absolute;cursor:pointer;inset:0;background:#e5e7eb;border-radius:22px;transition:.2s"></span>
            </label>
            <span id="paypalBadge" style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;{{ $settings->get('global_paypal_enabled')=='1'?'background:#d1fae5;color:#065f46':'background:#fee2e2;color:#991b1b' }}">
                {{ $settings->get('global_paypal_enabled')=='1'?'Enabled':'Disabled' }}
            </span>
        </div>
    </div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:12px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="global_paypal_test_mode" value="1" {{ $settings->get('global_paypal_test_mode','1')=='1'?'checked':'' }} style="width:15px;height:15px;accent-color:#003087">
            <span style="font-size:13px;color:#374151;font-weight:600">Test Mode (sandbox)</span>
        </label>
        @foreach([
            ['global_paypal_client_id_live','Live Client ID',''],
            ['global_paypal_secret_live','Live Secret',''],
            ['global_paypal_client_id_test','Sandbox Client ID',''],
            ['global_paypal_secret_test','Sandbox Secret',''],
        ] as [$k,$l,$p])
        <div>
            <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">{{ $l }}</label>
            <input name="{{ $k }}" value="{{ $settings->get($k,'') }}" placeholder="{{ $p ?: $l }}" style="width:100%;padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;font-family:monospace" {{ str_contains($k,'secret')?'type="password"':'type="text"' }}>
        </div>
        @endforeach
    </div>
</div>

</div>

{{-- Store Settings + Feature Flags --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;align-items:start">

<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px">
    <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">🏪 Store Config</h3>
    <div style="display:flex;flex-direction:column;gap:12px">
        @foreach([
            ['global_store_name','Store Name','eSahlan Global'],
            ['global_store_email','Store Email','global@esahlan.com'],
            ['global_default_currency','Default Currency','USD'],
            ['global_supported_currencies','Supported Currencies (comma)','USD,EUR,GBP'],
            ['global_tax_rate','Default Tax Rate (%)','0'],
            ['global_free_shipping_over','Free Shipping Over ($)','50'],
        ] as [$k,$l,$pl])
        <div>
            <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">{{ $l }}</label>
            <input name="{{ $k }}" value="{{ $settings->get($k,'') }}" placeholder="{{ $pl }}" style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
        </div>
        @endforeach
    </div>
</div>

<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:24px">
    <h3 style="font-size:14px;font-weight:700;color:#111;margin-bottom:16px">🚦 Feature Flags</h3>
    @foreach([
        ['global_maintenance_mode','🔧 Maintenance Mode','Take the store offline for maintenance'],
        ['global_reviews_enabled','⭐ Product Reviews','Allow customers to leave product reviews'],
        ['global_coupons_enabled','🎟 Coupons','Enable coupon/discount code system'],
        ['global_wishlist_enabled','❤️ Wishlist','Allow customers to save wishlist items'],
    ] as [$k,$l,$desc])
    <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:12px 0;border-bottom:1px solid #f3f4f6">
        <div>
            <div style="font-size:13px;font-weight:600;color:#111">{{ $l }}</div>
            <div style="font-size:11px;color:#9ca3af;margin-top:2px">{{ $desc }}</div>
        </div>
        <label style="position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0;margin-left:16px">
            <input type="checkbox" name="{{ $k }}" value="1" {{ $settings->get($k)=='1'?'checked':'' }} style="opacity:0;width:0;height:0">
            <span style="position:absolute;cursor:pointer;inset:0;background:#e5e7eb;border-radius:24px;transition:.25s;top:0;left:0;right:0;bottom:0"></span>
        </label>
    </div>
    @endforeach
</div>

</div>

<div style="margin-top:20px;display:flex;justify-content:flex-end">
    <button type="submit" style="padding:12px 32px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer">
        💾 Save All Settings
    </button>
</div>
</form>

{{-- Shipping Zones --}}
<div style="margin-top:28px">
    <h2 style="font-size:16px;font-weight:700;color:#111;margin-bottom:16px">🚚 Shipping Zones</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px">
        @foreach($shippingZones as $zone)
        <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:20px">
            <form method="POST" action="{{ route('admin.global.settings.shipping.update', $zone) }}">
                @csrf @method('PUT')
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
                    <h3 style="font-size:14px;font-weight:700;color:#111">{{ $zone->name }}</h3>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                        <input type="checkbox" name="is_active" value="1" {{ $zone->is_active?'checked':'' }} style="accent-color:#6366f1">
                        <span style="font-size:11px;color:#6b7280">Active</span>
                    </label>
                </div>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <div>
                        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Zone Name</label>
                        <input name="name" value="{{ $zone->name }}" style="width:100%;padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
                    </div>
                    <div>
                        <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Countries (ISO, comma separated or *)</label>
                        <input name="countries" value="{{ is_array($zone->countries) ? implode(',',$zone->countries) : $zone->countries }}" style="width:100%;padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;font-family:monospace">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <div>
                            <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Flat Rate ($)</label>
                            <input name="flat_rate" type="number" step="0.01" value="{{ $zone->flat_rate }}" style="width:100%;padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Free over ($)</label>
                            <input name="free_shipping_over" type="number" step="0.01" value="{{ $zone->free_shipping_over }}" style="width:100%;padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Min days</label>
                            <input name="estimated_days_min" type="number" value="{{ $zone->estimated_days_min }}" style="width:100%;padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Max days</label>
                            <input name="estimated_days_max" type="number" value="{{ $zone->estimated_days_max }}" style="width:100%;padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px">
                        </div>
                    </div>
                    <button type="submit" style="padding:9px;background:#f3f4f6;color:#374151;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer">Update Zone</button>
                </div>
            </form>
        </div>
        @endforeach
    </div>
</div>

<style>
input[type="checkbox"]:checked + span { background: #6366f1; }
input[type="checkbox"]:checked + span::before { transform: translateX(18px); }
span.toggle-stripe::before, label span:last-of-type::before {
    content:''; position:absolute; height:16px; width:16px; left:3px; bottom:3px;
    background:#fff; border-radius:50%; transition:.2s;
}
</style>
@endsection
