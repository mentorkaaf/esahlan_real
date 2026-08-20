@extends('admin.layouts.app')
@section('title', 'eWholesale — Buyers & Credit')

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <h2 style="margin:0;font-size:20px;font-weight:700;color:#1B1444">Buyers & Credit</h2>
    <a href="{{ route('admin.module-data.wholesale.credit.aging') }}" style="padding:8px 16px;background:#6366f1;color:#fff;border-radius:6px;font-size:13px;text-decoration:none">📊 Credit Aging Report</a>
</div>

<form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
    <input name="search" value="{{ request('search') }}" placeholder="Business name..." style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:200px">
    <select name="kyb" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px">
        <option value="">All KYB</option>
        @foreach(['none','pending','approved','rejected'] as $k)
        <option value="{{ $k }}" @selected(request('kyb')===$k)>{{ ucfirst($k) }}</option>
        @endforeach
    </select>
    <button style="padding:8px 16px;background:#F7941D;color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer">Filter</button>
</form>

@if(session('success'))<div style="background:#d1fae5;color:#065f46;padding:10px 16px;border-radius:6px;margin-bottom:16px">{{ session('success') }}</div>@endif

<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead><tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
    <th style="padding:12px 16px;text-align:left;color:#374151;font-weight:600">Business</th>
    <th style="padding:12px 16px;text-align:left;color:#374151;font-weight:600">User</th>
    <th style="padding:12px 16px;text-align:center;color:#374151;font-weight:600">KYB</th>
    <th style="padding:12px 16px;text-align:right;color:#374151;font-weight:600">Credit Limit</th>
    <th style="padding:12px 16px;text-align:right;color:#374151;font-weight:600">Used</th>
    <th style="padding:12px 16px;text-align:center;color:#374151;font-weight:600">Credit Status</th>
    <th style="padding:12px 16px;text-align:center;color:#374151;font-weight:600">Actions</th>
</tr></thead>
<tbody>
@forelse($buyers as $b)
<tr style="border-bottom:1px solid #f3f4f6" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background=''">
    <td style="padding:12px 16px;font-weight:600;color:#1B1444">{{ $b->business_name }}</td>
    <td style="padding:12px 16px;color:#6b7280">{{ $b->user?->name }}<br><small>{{ $b->user?->phone }}</small></td>
    <td style="padding:12px 16px;text-align:center">
        @php $kc = match($b->kyb_status){ 'approved'=>['#d1fae5','#065f46'], 'pending'=>['#fffbeb','#92400e'], 'rejected'=>['#fee2e2','#991b1b'], default=>['#f3f4f6','#6b7280'] }; @endphp
        <span style="background:{{ $kc[0] }};color:{{ $kc[1] }};padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600">{{ strtoupper($b->kyb_status) }}</span>
    </td>
    <td style="padding:12px 16px;text-align:right;font-weight:600">${{ $b->creditAccount ? number_format($b->creditAccount->credit_limit,2) : '—' }}</td>
    <td style="padding:12px 16px;text-align:right;color:#ef4444">${{ $b->creditAccount ? number_format($b->creditAccount->balance_used,2) : '—' }}</td>
    <td style="padding:12px 16px;text-align:center">
        @if($b->creditAccount)
        <span style="background:{{ $b->creditAccount->status==='active'?'#d1fae5':'#fee2e2' }};color:{{ $b->creditAccount->status==='active'?'#065f46':'#991b1b' }};padding:3px 10px;border-radius:12px;font-size:11px">{{ strtoupper($b->creditAccount->status) }}</span>
        @else <span style="color:#9ca3af;font-size:12px">None</span> @endif
    </td>
    <td style="padding:12px 16px;text-align:center">
        <button onclick="togglePanel('panel-{{ $b->id }}')" style="padding:5px 12px;background:#1B1444;color:#fff;border:none;border-radius:5px;font-size:12px;cursor:pointer">Manage</button>
    </td>
</tr>
{{-- Inline management panel --}}
<tr id="panel-{{ $b->id }}" style="display:none;background:#f9fafb">
    <td colspan="7" style="padding:16px 24px">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">

            {{-- KYB --}}
            @if(in_array($b->kyb_status, ['pending','none']))
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px">
                <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:10px">KYB Decision</div>
                <form method="POST" action="{{ route('admin.module-data.wholesale.buyers.kyb', $b) }}">@csrf
                    <textarea name="note" placeholder="Note (optional)" style="width:100%;border:1px solid #d1d5db;border-radius:5px;font-size:12px;padding:6px;margin-bottom:8px;box-sizing:border-box;height:50px"></textarea>
                    <div style="display:flex;gap:8px">
                        <button name="action" value="approve" style="flex:1;padding:7px;background:#10b981;color:#fff;border:none;border-radius:5px;font-size:12px;cursor:pointer">✅ Approve</button>
                        <button name="action" value="reject" style="flex:1;padding:7px;background:#ef4444;color:#fff;border:none;border-radius:5px;font-size:12px;cursor:pointer">❌ Reject</button>
                    </div>
                </form>
            </div>
            @endif

            {{-- Credit Account --}}
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px">
                <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:10px">Credit Account</div>
                <form method="POST" action="{{ route('admin.module-data.wholesale.buyers.credit.store', $b) }}">@csrf
                    <input name="credit_limit" value="{{ $b->creditAccount?->credit_limit }}" type="number" step="0.01" placeholder="Credit limit $" style="width:100%;padding:6px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;margin-bottom:6px;box-sizing:border-box">
                    <select name="term" style="width:100%;padding:6px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;margin-bottom:8px">
                        @foreach(['net7','net15','net30'] as $t)
                        <option value="{{ $t }}" @selected(($b->creditAccount?->term??'')===$t)>{{ strtoupper($t) }}</option>
                        @endforeach
                    </select>
                    <button style="width:100%;padding:7px;background:#6366f1;color:#fff;border:none;border-radius:5px;font-size:12px;cursor:pointer">Save Credit</button>
                </form>
                @if($b->creditAccount)
                <div style="display:flex;gap:6px;margin-top:8px">
                    <form method="POST" action="{{ route('admin.module-data.wholesale.buyers.credit.freeze', $b) }}" style="flex:1">@csrf
                        <button style="width:100%;padding:6px;background:{{ $b->creditAccount->status==='frozen'?'#10b981':'#f59e0b' }};color:#fff;border:none;border-radius:5px;font-size:11px;cursor:pointer">{{ $b->creditAccount->status==='frozen'?'Unfreeze':'Freeze' }}</button>
                    </form>
                    <a href="{{ route('admin.module-data.wholesale.buyers.credit.ledger', $b) }}" style="flex:1;padding:6px;background:#f3f4f6;color:#374151;border-radius:5px;font-size:11px;text-decoration:none;display:block;text-align:center">Ledger</a>
                </div>
                @endif
            </div>

            {{-- Adjust Credit --}}
            @if($b->creditAccount)
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px">
                <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:10px">Adjust Credit (+ or -)</div>
                <form method="POST" action="{{ route('admin.module-data.wholesale.buyers.credit.adjust', $b) }}">@csrf
                    <input name="amount" type="number" step="0.01" placeholder="Amount (negative = reduce)" style="width:100%;padding:6px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;margin-bottom:6px;box-sizing:border-box">
                    <input name="note" placeholder="Reason" style="width:100%;padding:6px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;margin-bottom:8px;box-sizing:border-box">
                    <button style="width:100%;padding:7px;background:#8b5cf6;color:#fff;border:none;border-radius:5px;font-size:12px;cursor:pointer">Adjust</button>
                </form>
            </div>
            @endif
        </div>
    </td>
</tr>
@empty
<tr><td colspan="7" style="padding:32px;text-align:center;color:#9ca3af">No buyers found.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
<div style="margin-top:16px">{{ $buyers->links() }}</div>
</div>

<script>
function togglePanel(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? '' : 'none';
}
</script>
@endsection
