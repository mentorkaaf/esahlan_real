@extends('admin.layouts.app')
@section('title', 'Exchange Order — ' . $order->reference)

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Exchange Detail</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('admin.exchange.index') }}">eExchange</a></li>
            <li>{{ $order->reference }}</li>
        </ul>
    </div>
    <a href="{{ route('admin.exchange.index') }}" class="btn btn-outline">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
</div>

@php
$statusMap = [
    'completed'  => ['#10b981','check-circle','Completed'],
    'pending'    => ['#f59e0b','clock','Pending'],
    'processing' => ['#3b82f6','spinner','Processing'],
    'failed'     => ['#ef4444','times-circle','Failed'],
];
[$statusColor, $statusIcon, $statusLabel] = $statusMap[$order->status] ?? ['#64748b','circle','Unknown'];

$walletMeta = [
    'EVC'     => ['EVC Plus',   '#e74c3c', 'account_balance_wallet'],
    'EDAHAB'  => ['eDahab',     '#27ae60', 'payments'],
    'JEEP'    => ['Jeep Money', '#2980b9', 'credit_card'],
    'PREMIER' => ['Premier',    '#8e44ad', 'stars'],
];
$fromMeta = $walletMeta[$order->from_wallet] ?? [$order->from_wallet, '#64748b', 'wallet'];
$toMeta   = $walletMeta[$order->to_wallet]   ?? [$order->to_wallet,   '#64748b', 'wallet'];
@endphp

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

    {{-- Main Detail Card --}}
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div class="card-header-title">
                <div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:#3b82f6;">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                Exchange Details
            </div>
            <span style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:{{ $statusColor }};">
                <i class="fas fa-{{ $statusIcon }}"></i> {{ $statusLabel }}
            </span>
        </div>
        <div class="card-body" style="padding:24px;">

            {{-- Reference badge --}}
            <div style="background:var(--surface);border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <div style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Reference</div>
                    <div style="font-size:20px;font-weight:800;color:var(--navy);font-family:monospace;margin-top:2px;">{{ $order->reference }}</div>
                </div>
                <div style="font-size:12px;color:var(--text-muted);">{{ \Carbon\Carbon::parse($order->created_at)->timezone('Africa/Mogadishu')->format('d M Y, H:i:s') }}</div>
            </div>

            {{-- Exchange flow visual --}}
            <div style="display:flex;align-items:center;gap:0;margin-bottom:24px;">
                {{-- FROM --}}
                <div style="flex:1;background:{{ $fromMeta[1] }}12;border:2px solid {{ $fromMeta[1] }}30;border-radius:14px;padding:18px;text-align:center;">
                    <div style="width:48px;height:48px;border-radius:13px;background:{{ $fromMeta[1] }}20;color:{{ $fromMeta[1] }};display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-size:22px;">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div style="font-size:11px;color:{{ $fromMeta[1] }};font-weight:700;text-transform:uppercase;letter-spacing:.5px;">From</div>
                    <div style="font-size:16px;font-weight:800;color:{{ $fromMeta[1] }};margin:3px 0;">{{ $fromMeta[0] }}</div>
                    <div style="font-size:22px;font-weight:900;color:var(--navy);">${{ number_format($order->sent_amount, 2) }}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">Sent Amount</div>
                </div>

                {{-- Arrow --}}
                <div style="padding:0 12px;flex-shrink:0;">
                    <div style="width:40px;height:40px;border-radius:50%;background:var(--brand);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(255,138,0,0.3);">
                        <i class="fas fa-arrow-right" style="color:#fff;font-size:14px;"></i>
                    </div>
                </div>

                {{-- TO --}}
                <div style="flex:1;background:{{ $toMeta[1] }}12;border:2px solid {{ $toMeta[1] }}30;border-radius:14px;padding:18px;text-align:center;">
                    <div style="width:48px;height:48px;border-radius:13px;background:{{ $toMeta[1] }}20;color:{{ $toMeta[1] }};display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-size:22px;">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div style="font-size:11px;color:{{ $toMeta[1] }};font-weight:700;text-transform:uppercase;letter-spacing:.5px;">To</div>
                    <div style="font-size:16px;font-weight:800;color:{{ $toMeta[1] }};margin:3px 0;">{{ $toMeta[0] }}</div>
                    <div style="font-size:22px;font-weight:900;color:#10b981;">${{ number_format($order->converted_amount, 2) }}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">Received Amount</div>
                </div>
            </div>

            {{-- Recipient phone highlighted --}}
            <div style="background:linear-gradient(135deg,#0c0148,#1a0570);border-radius:14px;padding:18px 20px;margin-bottom:20px;display:flex;align-items:center;gap:16px;">
                <div style="width:50px;height:50px;background:rgba(255,255,255,0.12);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;">📱</div>
                <div>
                    <div style="font-size:11px;color:rgba(255,255,255,0.6);font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Recipient Phone Number</div>
                    <div style="font-size:24px;font-weight:800;color:#fff;margin-top:2px;">{{ $order->recipient_phone }}</div>
                    <div style="font-size:12px;color:rgba(255,255,255,0.5);margin-top:2px;">{{ $toMeta[0] }} wallet</div>
                </div>
                <div style="margin-left:auto;">
                    <span style="background:rgba(16,185,129,0.25);color:#4ade80;border-radius:20px;padding:4px 12px;font-size:11px;font-weight:700;border:1px solid rgba(16,185,129,0.3);">
                        <i class="fas fa-check-circle"></i> Sent
                    </span>
                </div>
            </div>

            {{-- Financial breakdown --}}
            <div style="border:1.5px solid var(--border);border-radius:12px;overflow:hidden;">
                <div style="background:var(--surface);padding:12px 16px;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">
                    Financial Breakdown
                </div>
                @php
                $rows = [
                    ['Sent Amount',      '$'.number_format($order->sent_amount,2),      'var(--text)'],
                    ['Service Fee (1%)', '-$'.number_format($order->fee_amount,2),       '#ef4444'],
                    ['Exchange Rate',    '×'.$order->rate,                              'var(--text)'],
                    ['Received Amount',  '$'.number_format($order->converted_amount,2), '#10b981'],
                ];
                @endphp
                @foreach($rows as [$label, $value, $color])
                <div style="display:flex;justify-content:space-between;align-items:center;padding:13px 16px;border-top:1px solid var(--border);{{ $loop->last ? 'background:rgba(16,185,129,0.04);' : '' }}">
                    <span style="font-size:13px;font-weight:600;color:var(--text-muted);">{{ $label }}</span>
                    <span style="font-size:{{ $loop->last ? '16' : '13' }}px;font-weight:{{ $loop->last ? '800' : '700' }};color:{{ $color }};">{{ $value }}</span>
                </div>
                @endforeach
            </div>

            @if($order->note)
            <div style="margin-top:16px;padding:12px 16px;background:rgba(245,158,11,0.08);border:1.5px solid rgba(245,158,11,0.2);border-radius:10px;">
                <div style="font-size:11px;font-weight:700;color:#f59e0b;margin-bottom:4px;text-transform:uppercase;">Note</div>
                <div style="font-size:13px;color:var(--text);">{{ $order->note }}</div>
            </div>
            @endif
        </div>
    </div>

    {{-- Right sidebar --}}
    <div style="display:flex;flex-direction:column;gap:16px;">

        {{-- User info --}}
        <div class="card" style="margin-bottom:0;">
            <div class="card-header">
                <div class="card-header-title">
                    <div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);"><i class="fas fa-user"></i></div>
                    Customer
                </div>
            </div>
            <div class="card-body" style="padding:18px;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
                    <div class="avatar avatar-orange" style="width:48px;height:48px;font-size:18px;">{{ strtoupper(substr($order->user_name,0,1)) }}</div>
                    <div>
                        <div style="font-weight:700;font-size:15px;color:var(--navy);">{{ $order->user_name }}</div>
                        <div style="font-size:12px;color:var(--text-muted);">ID #{{ $order->user_id }}</div>
                    </div>
                </div>
                @if($order->user_phone)
                <div style="display:flex;align-items:center;gap:8px;padding:9px 12px;background:var(--surface);border-radius:8px;margin-bottom:8px;">
                    <i class="fas fa-phone" style="color:var(--text-muted);font-size:12px;"></i>
                    <span style="font-size:13px;font-weight:600;">{{ $order->user_phone }}</span>
                </div>
                @endif
                @if($order->user_email)
                <div style="display:flex;align-items:center;gap:8px;padding:9px 12px;background:var(--surface);border-radius:8px;">
                    <i class="fas fa-envelope" style="color:var(--text-muted);font-size:12px;"></i>
                    <span style="font-size:13px;font-weight:600;">{{ $order->user_email }}</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Order summary --}}
        <div class="card" style="margin-bottom:0;">
            <div class="card-header">
                <div class="card-header-title">
                    <div class="card-header-icon" style="background:rgba(16,185,129,0.1);color:#10b981;"><i class="fas fa-info-circle"></i></div>
                    Summary
                </div>
            </div>
            <div class="card-body" style="padding:14px 18px;">
                @php
                $meta = [
                    ['Order ID',    '#'.$order->id],
                    ['Reference',   $order->reference],
                    ['Status',      ucfirst($order->status)],
                    ['Created',     \Carbon\Carbon::parse($order->created_at)->timezone('Africa/Mogadishu')->format('d M Y')],
                    ['Time',        \Carbon\Carbon::parse($order->created_at)->timezone('Africa/Mogadishu')->format('H:i:s')],
                    ['Ago',         \Carbon\Carbon::parse($order->created_at)->timezone('Africa/Mogadishu')->diffForHumans()],
                ];
                @endphp
                @foreach($meta as [$k, $v])
                <div style="display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border);">
                    <span style="font-size:12px;color:var(--text-muted);font-weight:600;">{{ $k }}</span>
                    <span style="font-size:12px;color:var(--navy);font-weight:700;">{{ $v }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Exchange pair --}}
        <div class="card" style="margin-bottom:0;">
            <div class="card-header">
                <div class="card-header-title">
                    <div class="card-header-icon" style="background:rgba(139,92,246,0.1);color:#8b5cf6;"><i class="fas fa-arrows-alt-h"></i></div>
                    Exchange Pair
                </div>
            </div>
            <div class="card-body" style="padding:18px;text-align:center;">
                <div style="display:flex;align-items:center;justify-content:center;gap:14px;">
                    <div style="text-align:center;">
                        <div style="width:50px;height:50px;border-radius:14px;background:{{ $fromMeta[1] }}18;color:{{ $fromMeta[1] }};display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto 6px;"><i class="fas fa-wallet"></i></div>
                        <div style="font-size:13px;font-weight:800;color:{{ $fromMeta[1] }};">{{ $order->from_wallet }}</div>
                        <div style="font-size:10px;color:var(--text-muted);">{{ $fromMeta[0] }}</div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
                        <i class="fas fa-arrow-right" style="color:var(--brand);font-size:18px;"></i>
                        <span style="font-size:10px;color:var(--text-muted);font-weight:600;">Rate: {{ $order->rate }}</span>
                    </div>
                    <div style="text-align:center;">
                        <div style="width:50px;height:50px;border-radius:14px;background:{{ $toMeta[1] }}18;color:{{ $toMeta[1] }};display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto 6px;"><i class="fas fa-wallet"></i></div>
                        <div style="font-size:13px;font-weight:800;color:{{ $toMeta[1] }};">{{ $order->to_wallet }}</div>
                        <div style="font-size:10px;color:var(--text-muted);">{{ $toMeta[0] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
