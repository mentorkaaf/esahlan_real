@extends('admin.layouts.app')
@section('title', 'Payment Gateway Settings')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Payment Gateway Settings</h1>
        <ul class="breadcrumb"><li><span>Finance</span></li><li><a href="{{ route('admin.wallet.index') }}">ePay</a></li><li><span>Settings</span></li></ul>
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div style="max-width:640px">
    <div style="background:#fff;border-radius:14px;border:1.5px solid #f0f1f5;padding:28px">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid #f0f1f5">
            <div style="width:48px;height:48px;background:#FF8A00;border-radius:12px;display:flex;align-items:center;justify-content:center">
                <i class="fas fa-credit-card" style="color:#fff;font-size:20px"></i>
            </div>
            <div>
                <div style="font-weight:800;font-size:16px;color:#07003B">Waafi Pay Integration</div>
                <div style="font-size:12px;color:#8A8A9A">Mobile payment gateway for Somalia (EVC, eDahab, Jeep, Premier)</div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.wallet.settings.save') }}">
            @csrf
            <div style="margin-bottom:16px">
                <label style="font-size:12px;font-weight:700;color:#07003B;display:block;margin-bottom:6px">API URL</label>
                <input type="url" name="waafi_api_url" value="{{ $settings['waafi_api_url'] }}" required
                    style="width:100%;padding:11px 14px;border:1.5px solid #e0e0e0;border-radius:9px;font-size:14px;box-sizing:border-box">
                <div style="font-size:11px;color:#8A8A9A;margin-top:4px">Default: https://api.waafipay.net/asm</div>
            </div>
            <div style="margin-bottom:16px">
                <label style="font-size:12px;font-weight:700;color:#07003B;display:block;margin-bottom:6px">Merchant UID</label>
                <input type="text" name="waafi_merchant_uid" value="{{ $settings['waafi_merchant_uid'] }}" required
                    placeholder="e.g. M0910291"
                    style="width:100%;padding:11px 14px;border:1.5px solid #e0e0e0;border-radius:9px;font-size:14px;box-sizing:border-box">
            </div>
            <div style="margin-bottom:16px">
                <label style="font-size:12px;font-weight:700;color:#07003B;display:block;margin-bottom:6px">API User ID</label>
                <input type="text" name="waafi_api_user_id" value="{{ $settings['waafi_api_user_id'] }}" required
                    placeholder="e.g. 1000416"
                    style="width:100%;padding:11px 14px;border:1.5px solid #e0e0e0;border-radius:9px;font-size:14px;box-sizing:border-box">
            </div>
            <div style="margin-bottom:16px">
                <label style="font-size:12px;font-weight:700;color:#07003B;display:block;margin-bottom:6px">API Key</label>
                <input type="password" name="waafi_api_key" value="{{ $settings['waafi_api_key'] }}"
                    placeholder="Enter API key..."
                    style="width:100%;padding:11px 14px;border:1.5px solid #e0e0e0;border-radius:9px;font-size:14px;box-sizing:border-box">
                <div style="font-size:11px;color:#8A8A9A;margin-top:4px">Leave blank to keep existing key</div>
            </div>
            <div style="margin-bottom:24px">
                <label style="font-size:12px;font-weight:700;color:#07003B;display:block;margin-bottom:6px">Payment Description</label>
                <input type="text" name="waafi_description" value="{{ $settings['waafi_description'] }}"
                    placeholder="eSahlan Payment"
                    style="width:100%;padding:11px 14px;border:1.5px solid #e0e0e0;border-radius:9px;font-size:14px;box-sizing:border-box">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;padding:12px;font-weight:800">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </form>
    </div>

    {{-- Withdrawal & Limit Rules --}}
    <div style="background:#fff;border-radius:14px;border:1.5px solid #f0f1f5;padding:28px;margin-top:20px">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid #f0f1f5">
            <div style="width:48px;height:48px;background:#07003B;border-radius:12px;display:flex;align-items:center;justify-content:center">
                <i class="fas fa-sliders" style="color:#fff;font-size:18px"></i>
            </div>
            <div>
                <div style="font-weight:800;font-size:16px;color:#07003B">Withdrawal & Limit Rules</div>
                <div style="font-size:12px;color:#8A8A9A">Configure fees, limits, and wallet behaviour</div>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.wallet.settings.save') }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                <div>
                    <label style="font-size:12px;font-weight:700;color:#07003B;display:block;margin-bottom:6px">Min Withdrawal ($)</label>
                    <input type="number" name="withdrawal_min_amount" value="{{ $settings['withdrawal_min_amount'] }}" step="0.01" min="0"
                        style="width:100%;padding:11px 14px;border:1.5px solid #e0e0e0;border-radius:9px;font-size:14px;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;color:#07003B;display:block;margin-bottom:6px">Max Withdrawal ($)</label>
                    <input type="number" name="withdrawal_max_amount" value="{{ $settings['withdrawal_max_amount'] }}" step="0.01" min="0"
                        style="width:100%;padding:11px 14px;border:1.5px solid #e0e0e0;border-radius:9px;font-size:14px;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;color:#07003B;display:block;margin-bottom:6px">Withdrawal Fee % <span style="font-weight:400;color:#8A8A9A">(0 = none)</span></label>
                    <input type="number" name="withdrawal_fee_pct" value="{{ $settings['withdrawal_fee_pct'] }}" step="0.01" min="0" max="100"
                        style="width:100%;padding:11px 14px;border:1.5px solid #e0e0e0;border-radius:9px;font-size:14px;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;color:#07003B;display:block;margin-bottom:6px">Fixed Fee per Withdrawal ($)</label>
                    <input type="number" name="withdrawal_fee_fixed" value="{{ $settings['withdrawal_fee_fixed'] }}" step="0.01" min="0"
                        style="width:100%;padding:11px 14px;border:1.5px solid #e0e0e0;border-radius:9px;font-size:14px;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:700;color:#07003B;display:block;margin-bottom:6px">Daily Spend Limit ($) <span style="font-weight:400;color:#8A8A9A">(0 = unlimited)</span></label>
                    <input type="number" name="wallet_daily_limit" value="{{ $settings['wallet_daily_limit'] }}" step="0.01" min="0"
                        style="width:100%;padding:11px 14px;border:1.5px solid #e0e0e0;border-radius:9px;font-size:14px;box-sizing:border-box">
                </div>
                <div style="display:flex;align-items:center;gap:10px;padding-top:26px">
                    <input type="checkbox" name="wallet_enabled" value="1" id="walletEnabled" {{ $settings['wallet_enabled'] ? 'checked' : '' }} style="width:18px;height:18px">
                    <label for="walletEnabled" style="font-size:13px;font-weight:700;color:#07003B;cursor:pointer">ePay Wallet Enabled</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="padding:12px 28px;font-weight:800">
                <i class="fas fa-save"></i> Save Rules
            </button>
        </form>
    </div>

    <div style="background:#FFF8F0;border:1.5px solid #FFE0B2;border-radius:12px;padding:16px;margin-top:16px">
        <div style="font-weight:700;color:#E65100;margin-bottom:8px"><i class="fas fa-info-circle"></i> How to get credentials</div>
        <ol style="margin:0;padding-left:20px;font-size:13px;color:#5D4037;line-height:1.8">
            <li>Visit <strong>docs.waafipay.com</strong> to register as a merchant</li>
            <li>Obtain your <strong>Merchant UID</strong>, <strong>API User ID</strong>, and <strong>API Key</strong></li>
            <li>For testing: use sandbox credentials from Waafi Pay portal</li>
            <li>After saving, users can top up ePay and pay via Waafi Pay</li>
        </ol>
    </div>
</div>
@endsection
