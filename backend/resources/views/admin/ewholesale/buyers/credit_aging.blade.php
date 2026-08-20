@extends('admin.layouts.app')
@section('title', 'Credit Aging Report')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<h2 style="margin:0 0 20px;font-size:20px;font-weight:700;color:#1B1444">Credit Aging Report</h2>

<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead><tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
    <th style="padding:12px 16px;text-align:left;color:#374151;font-weight:600">Buyer</th>
    <th style="padding:12px 16px;text-align:right;color:#10b981;font-weight:600">Current</th>
    <th style="padding:12px 16px;text-align:right;color:#f59e0b;font-weight:600">1–30 days</th>
    <th style="padding:12px 16px;text-align:right;color:#ef4444;font-weight:600">31–60 days</th>
    <th style="padding:12px 16px;text-align:right;color:#7f1d1d;font-weight:600">60+ days</th>
    <th style="padding:12px 16px;text-align:right;font-weight:600">Total</th>
    <th style="padding:12px 16px;text-align:center;font-weight:600">Action</th>
</tr></thead>
<tbody>
@forelse($rows as $row)
@php
    $total = $row['current'] + $row['30'] + $row['60'] + $row['60+'];
    $hasOverdue = ($row['30'] + $row['60'] + $row['60+']) > 0;
    $account = $row['account'];
@endphp
<tr style="border-bottom:1px solid #f9fafb{{ $hasOverdue ? ';background:#fffbeb' : '' }}" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='{{ $hasOverdue ? '#fffbeb' : '' }}'">
    <td style="padding:12px 16px">
        <div style="font-weight:600;color:#1B1444">{{ $account?->buyer?->business_name ?? '—' }}</div>
        <div style="font-size:11px;color:#9ca3af">{{ $account?->buyer?->user?->name }}</div>
        @if($account?->is_frozen)
            <span style="font-size:10px;padding:1px 6px;border-radius:9999px;background:#fee2e2;color:#991b1b;font-weight:600">🔒 FROZEN</span>
        @endif
    </td>
    <td style="padding:12px 16px;text-align:right;color:#10b981">${{ number_format($row['current'],2) }}</td>
    <td style="padding:12px 16px;text-align:right;color:#f59e0b">${{ number_format($row['30'],2) }}</td>
    <td style="padding:12px 16px;text-align:right;color:#ef4444">${{ number_format($row['60'],2) }}</td>
    <td style="padding:12px 16px;text-align:right;color:#7f1d1d;font-weight:700">${{ number_format($row['60+'],2) }}</td>
    <td style="padding:12px 16px;text-align:right;font-weight:700">${{ number_format($total,2) }}</td>
    <td style="padding:12px 16px;text-align:center">
        @if($hasOverdue && $account)
        <form method="POST" action="{{ route('admin.module-data.wholesale.credit.remind', $account) }}" style="display:inline">
            @csrf
            <button type="submit"
                onclick="return confirm('Send FCM reminder to {{ addslashes($account->buyer?->user?->name ?? 'this buyer') }}?')"
                style="padding:5px 12px;background:#f59e0b;color:#fff;border:none;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap">
                📲 Remind
            </button>
        </form>
        @else
        <span style="color:#d1d5db;font-size:12px">—</span>
        @endif
    </td>
</tr>
@empty
<tr><td colspan="7" style="padding:24px;text-align:center;color:#9ca3af">No outstanding credit.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
</div>
@endsection
