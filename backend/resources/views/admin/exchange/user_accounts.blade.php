@extends('admin.layouts.app')
@section('title', 'User — {{ $user->name }}')
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between">
    <div>
        <h1 class="page-title">👤 {{ $user->name }}</h1>
        <p class="page-subtitle">{{ $user->phone }} · Exchange account details</p>
    </div>
    <a href="{{ route('admin.exchange.users') }}" style="padding:9px 16px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:9px;font-weight:700;font-size:13px;color:#374151;text-decoration:none">← Back to Users</a>
</div>

{{-- Fraud indicators --}}
@php $isSuspect = ($fraud['total_volume'] >= 2000 || $fraud['orders_week'] >= 30 || $fraud['unique_phones'] >= 10); @endphp
@if($isSuspect)
<div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:10px;padding:12px 18px;margin-bottom:18px;display:flex;align-items:center;gap:10px">
    <i class="fas fa-exclamation-triangle" style="color:#d97706;font-size:18px"></i>
    <div>
        <strong style="color:#92400e;font-size:13px">⚠️ Fraud Risk Indicators Detected</strong>
        <p style="color:#92400e;font-size:12px;margin:2px 0 0">High volume or unusual patterns detected for this user. Review transactions carefully.</p>
    </div>
</div>
@endif

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px">
    @php
    $indicators = [
        ['label'=>'Orders Today',    'value'=>$fraud['orders_today'],                    'color'=>$fraud['orders_today']>=10?'#ef4444':'#6366f1'],
        ['label'=>'Orders This Week','value'=>$fraud['orders_week'],                     'color'=>$fraud['orders_week']>=30?'#ef4444':'#10b981'],
        ['label'=>'Total Volume',    'value'=>'$'.number_format($fraud['total_volume'],2),'color'=>$fraud['total_volume']>=2000?'#ef4444':'#3b82f6'],
        ['label'=>'Unique Recipients','value'=>$fraud['unique_phones'],                  'color'=>$fraud['unique_phones']>=10?'#ef4444':'#f59e0b'],
    ];
    @endphp
    @foreach($indicators as $ind)
    <div style="background:#fff;border-radius:11px;padding:14px 16px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb">
        <div style="font-size:22px;font-weight:800;color:{{ $ind['color'] }}">{{ $ind['value'] }}</div>
        <div style="font-size:11px;color:#6b7280;margin-top:3px">{{ $ind['label'] }}</div>
    </div>
    @endforeach
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:18px">
    {{-- Saved Accounts --}}
    <div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6">
            <h3 style="font-size:13px;font-weight:700;color:#111;margin:0">Saved Wallet Accounts</h3>
        </div>
        <div style="padding:10px">
            @php
            $walletColors = ['evc'=>'#E74C3C','edahab'=>'#27AE60','jeep'=>'#2980B9','premier'=>'#8E44AD','ebesa'=>'#D35400','usdt'=>'#26A17B'];
            @endphp
            @forelse($accounts as $acc)
            @php $color = $walletColors[$acc->wallet_type] ?? '#6b7280'; @endphp
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-radius:9px;margin-bottom:6px;background:#f9fafb;border:1px solid #e5e7eb">
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="width:34px;height:34px;border-radius:9px;background:{{ $color }}20;display:flex;align-items:center;justify-content:center">
                        <i class="fas fa-wallet" style="color:{{ $color }};font-size:14px"></i>
                    </div>
                    <div>
                        <div style="font-size:12px;font-weight:800;color:#111">{{ strtoupper($acc->wallet_type) }}</div>
                        <div style="font-size:11px;color:#6b7280">{{ $acc->phone_number }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.exchange.account.destroy', $acc->id) }}" onsubmit="return confirm('Delete this account?')">
                    @csrf @method('DELETE')
                    <button type="submit" style="padding:4px 10px;background:#fef2f2;color:#ef4444;border:1px solid #fecaca;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer">Remove</button>
                </form>
            </div>
            @empty
            <p style="text-align:center;color:#9ca3af;font-size:12px;padding:20px">No saved accounts</p>
            @endforelse
        </div>
    </div>

    {{-- Transaction History --}}
    <div style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.07);border:1px solid #e5e7eb;overflow:hidden">
        <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6">
            <h3 style="font-size:13px;font-weight:700;color:#111;margin:0">Transaction History (last 50)</h3>
        </div>
        <div style="overflow-x:auto;max-height:500px;overflow-y:auto">
        <table style="width:100%;border-collapse:collapse">
            <thead style="position:sticky;top:0;background:#f9fafb;z-index:1">
                <tr>
                    <th style="padding:9px 14px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Reference</th>
                    <th style="padding:9px 14px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Pair</th>
                    <th style="padding:9px 14px;text-align:right;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Amount</th>
                    <th style="padding:9px 14px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Recipient</th>
                    <th style="padding:9px 14px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Status</th>
                    <th style="padding:9px 14px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase">Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                @php
                $sc = ['pending'=>['#f59e0b','#fffbeb'],'processing'=>['#6366f1','#eef2ff'],'completed'=>['#10b981','#ecfdf5'],'failed'=>['#ef4444','#fef2f2']];
                $c = $sc[$o->status] ?? ['#9ca3af','#f9fafb'];
                @endphp
                <tr style="border-top:1px solid #f3f4f6{{ $o->sent_amount>=500?';background:#fff7ed':'' }}">
                    <td style="padding:8px 14px"><a href="{{ route('admin.exchange.show', $o->id) }}" style="font-size:11px;font-weight:700;color:#6366f1;text-decoration:none">{{ $o->reference }}</a></td>
                    <td style="padding:8px 14px;text-align:center"><span style="font-size:10px;font-weight:800;color:#374151;background:#f3f4f6;padding:2px 7px;border-radius:5px">{{ $o->from_wallet }} → {{ $o->to_wallet }}</span></td>
                    <td style="padding:8px 14px;text-align:right;font-size:12px;font-weight:800;color:{{ $o->sent_amount>=500?'#ef4444':'#111' }}">${{ number_format($o->sent_amount,2) }}</td>
                    <td style="padding:8px 14px;font-size:11px;color:#374151">{{ $o->recipient_phone }}</td>
                    <td style="padding:8px 14px;text-align:center"><span style="padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;color:{{ $c[0] }};background:{{ $c[1] }}">{{ strtoupper($o->status) }}</span></td>
                    <td style="padding:8px 14px;font-size:10px;color:#9ca3af;white-space:nowrap">{{ \Carbon\Carbon::parse($o->created_at)->format('d M y H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="6" style="padding:24px;text-align:center;color:#9ca3af;font-size:12px">No orders yet</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
