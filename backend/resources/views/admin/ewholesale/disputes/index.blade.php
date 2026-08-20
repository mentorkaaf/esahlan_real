@extends('admin.layouts.app')
@section('title', 'eWholesale — Disputes')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <h2 style="margin:0;font-size:20px;font-weight:700;color:#1B1444">Disputes (SLA View)</h2>
    <form method="GET">
        <select name="status" onchange="this.form.submit()" style="padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:12px">
            <option value="">All Statuses</option>
            @foreach(['open','under_review','resolved','closed'] as $s)
            <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
            @endforeach
        </select>
    </form>
</div>

<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead>
<tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
    <th style="padding:10px 14px;text-align:left;font-weight:600">Order</th>
    <th style="padding:10px 14px;text-align:left;font-weight:600">Buyer</th>
    <th style="padding:10px 14px;text-align:left;font-weight:600">Supplier</th>
    <th style="padding:10px 14px;text-align:left;font-weight:600">Reason</th>
    <th style="padding:10px 14px;text-align:center;font-weight:600">Status</th>
    <th style="padding:10px 14px;text-align:center;font-weight:600">SLA</th>
    <th style="padding:10px 14px;text-align:right;font-weight:600">Actions</th>
</tr>
</thead>
<tbody>
@forelse($disputes as $dispute)
@php
    $slaHours = $dispute->slaHoursRemaining();
    $slaBreached = $dispute->isSlaBreached();
    $rowBg = $slaBreached ? 'background:#fff1f2' : ($dispute->escalated_at ? 'background:#fffbeb' : '');
@endphp
<tr style="border-bottom:1px solid #f3f4f6;{{ $rowBg }}">
    <td style="padding:10px 14px">
        <a href="{{ route('admin.module-data.wholesale.orders.show', $dispute->order_id) }}"
           style="color:#1B1444;font-weight:600;text-decoration:none">
            {{ $dispute->order->order_no ?? '#'.$dispute->order_id }}
        </a>
    </td>
    <td style="padding:10px 14px">{{ $dispute->order->buyer->user->name ?? '—' }}</td>
    <td style="padding:10px 14px">{{ $dispute->order->supplier->display_name ?? '—' }}</td>
    <td style="padding:10px 14px;max-width:180px">
        <div style="font-weight:500">{{ ucfirst($dispute->reason) }}</div>
        <div style="font-size:11px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ Str::limit($dispute->description,60) }}</div>
    </td>
    <td style="padding:10px 14px;text-align:center">
        @php $statusColors = ['open'=>'#fef3c7:#92400e','under_review'=>'#dbeafe:#1d4ed8','resolved'=>'#dcfce7:#166534','closed'=>'#f3f4f6:#374151']; @endphp
        @php [$bg,$fg] = explode(':', $statusColors[$dispute->status] ?? '#f3f4f6:#374151'); @endphp
        <span style="padding:3px 8px;border-radius:9999px;font-size:11px;font-weight:600;background:{{ $bg }};color:{{ $fg }}">
            {{ ucfirst(str_replace('_',' ',$dispute->status)) }}
        </span>
        @if($dispute->escalated_at)
        <div style="font-size:10px;color:#d97706;margin-top:2px">⚡ Escalated</div>
        @endif
    </td>
    <td style="padding:10px 14px;text-align:center">
        @if($dispute->sla_deadline)
            @if($slaBreached)
                <span style="color:#ef4444;font-weight:700;font-size:12px">⚠ BREACHED</span>
                <div style="font-size:10px;color:#ef4444">{{ abs($slaHours) }}h ago</div>
            @elseif($slaHours !== null && $slaHours <= 12)
                <span style="color:#f59e0b;font-weight:700;font-size:12px">⏱ {{ $slaHours }}h left</span>
            @else
                <span style="color:#6b7280;font-size:12px">{{ $slaHours }}h left</span>
                <div style="font-size:10px;color:#9ca3af">{{ $dispute->sla_deadline->format('M d, H:i') }}</div>
            @endif
        @else
            <span style="color:#9ca3af;font-size:12px">—</span>
        @endif
    </td>
    <td style="padding:10px 14px;text-align:right">
        <div style="display:flex;gap:6px;justify-content:flex-end">
            @if($dispute->status === 'open' && !$dispute->escalated_at)
            <form method="POST" action="{{ route('admin.module-data.wholesale.disputes.escalate', $dispute) }}">
                @csrf
                <button style="padding:5px 10px;border:1px solid #f59e0b;color:#f59e0b;background:transparent;border-radius:5px;font-size:12px;cursor:pointer">Escalate</button>
            </form>
            @endif
            @if(in_array($dispute->status, ['open','under_review']))
            <button onclick="resolveDispute({{ $dispute->id }})" style="padding:5px 10px;border:1px solid #10b981;color:#10b981;background:transparent;border-radius:5px;font-size:12px;cursor:pointer">Resolve</button>
            @endif
        </div>
    </td>
</tr>
@empty
<tr><td colspan="7" style="padding:32px;text-align:center;color:#6b7280">No disputes found.</td></tr>
@endforelse
</tbody>
</table>
</div>

<div style="margin-top:16px">{{ $disputes->links() }}</div>
</div>

{{-- Resolve modal --}}
<div id="resolveModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:10px;padding:24px;width:420px;max-width:90vw">
        <h3 style="margin:0 0 12px;font-size:16px;color:#111">Resolve Dispute</h3>
        <form id="resolveForm" method="POST">
            @csrf
            <textarea name="resolution_note" placeholder="Resolution summary (required)" required rows="3"
                style="width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:6px;padding:8px;font-size:13px"></textarea>
            <div style="display:flex;gap:8px;margin-top:12px;justify-content:flex-end">
                <button type="button" onclick="document.getElementById('resolveModal').style.display='none'" style="padding:8px 14px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer">Cancel</button>
                <button type="submit" style="padding:8px 14px;background:#10b981;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer">Resolve</button>
            </div>
        </form>
    </div>
</div>
<script>
function resolveDispute(id) {
    document.getElementById('resolveForm').action = `/admin/module-data/wholesale/disputes/${id}/resolve`;
    document.getElementById('resolveModal').style.display = 'flex';
}
</script>
@endsection
