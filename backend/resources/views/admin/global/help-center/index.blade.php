@extends('admin.layouts.app')
@section('title', 'Help Center')

@section('content')
<div class="page-header">
    <div><h1 class="page-title">🎧 Help Center</h1><p class="page-subtitle">Customer support tickets for Global Store</p></div>
</div>

@if(session('success'))<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>@endif

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px">
    @foreach([['Open Tickets',$stats['open'],'#ef4444','fa-envelope-open'],['In Progress',$stats['in_progress'],'#f59e0b','fa-spinner'],['Urgent',$stats['urgent'],'#dc2626','fa-fire'],['Resolved',$stats['resolved'],'#10b981','fa-check-circle']] as [$l,$v,$c,$i])
    <div style="background:#fff;border-radius:10px;padding:16px;border:1px solid #e5e7eb;display:flex;align-items:center;gap:12px">
        <div style="width:38px;height:38px;border-radius:9px;background:{{ $c }}18;display:flex;align-items:center;justify-content:center"><i class="fas {{ $i }}" style="color:{{ $c }}"></i></div>
        <div><div style="font-size:20px;font-weight:800;color:#111">{{ $v }}</div><div style="font-size:11px;color:#6b7280">{{ $l }}</div></div>
    </div>
    @endforeach
</div>

{{-- Filters --}}
<form method="GET" style="background:#fff;border-radius:10px;padding:13px 18px;margin-bottom:14px;border:1px solid #e5e7eb;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:2;min-width:150px"><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">Search</label><input name="search" value="{{ request('search') }}" placeholder="Ticket# or email" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"></div>
    @foreach([['status'=>['open'=>'Open','in_progress'=>'In Progress','resolved'=>'Resolved','closed'=>'Closed']],['priority'=>['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent']],['category'=>['order'=>'Order','payment'=>'Payment','shipping'=>'Shipping','product'=>'Product','refund'=>'Refund','other'=>'Other']]] as $sel)
    @foreach($sel as $name => $opts)
    <div style="flex:1;min-width:110px"><label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px">{{ ucfirst($name) }}</label>
        <select name="{{ $name }}" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px"><option value="">All</option>
            @foreach($opts as $v => $l)<option value="{{ $v }}" {{ request($name)===$v?'selected':'' }}>{{ $l }}</option>@endforeach
        </select></div>
    @endforeach
    @endforeach
    <button type="submit" style="padding:8px 18px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Filter</button>
</form>

<div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden">
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">
                <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Ticket</th>
                <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Subject</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Category</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Priority</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Status</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Date</th>
                <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tickets as $t)
            @php
            $pColors=['low'=>['#6b7280','#f3f4f6'],'medium'=>['#f59e0b','#fffbeb'],'high'=>['#ef4444','#fef2f2'],'urgent'=>['#dc2626','#fee2e2']];
            $sColors=['open'=>['#3b82f6','#eff6ff'],'in_progress'=>['#f59e0b','#fffbeb'],'resolved'=>['#10b981','#ecfdf5'],'closed'=>['#9ca3af','#f3f4f6']];
            $pc=$pColors[$t->priority]??['#9ca3af','#f3f4f6'];$sc=$sColors[$t->status]??['#9ca3af','#f3f4f6'];
            @endphp
            <tr style="border-top:1px solid #f3f4f6">
                <td style="padding:10px 16px">
                    <a href="{{ route('admin.global.help-center.show',$t->id) }}" style="font-size:12px;font-weight:700;color:#6366f1;text-decoration:none">{{ $t->ticket_number }}</a>
                    <div style="font-size:11px;color:#9ca3af">{{ $t->name }} · {{ $t->email }}</div>
                </td>
                <td style="padding:10px 16px;font-size:12px;color:#111;max-width:220px"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $t->subject }}</div></td>
                <td style="padding:10px 16px;text-align:center"><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;color:#374151;background:#f3f4f6;text-transform:capitalize">{{ $t->category }}</span></td>
                <td style="padding:10px 16px;text-align:center"><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;color:{{ $pc[0] }};background:{{ $pc[1] }};text-transform:uppercase">{{ $t->priority }}</span></td>
                <td style="padding:10px 16px;text-align:center"><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;color:{{ $sc[0] }};background:{{ $sc[1] }};text-transform:capitalize">{{ str_replace('_',' ',$t->status) }}</span></td>
                <td style="padding:10px 16px;text-align:center;font-size:11px;color:#9ca3af">{{ \Carbon\Carbon::parse($t->created_at)->format('M d') }}</td>
                <td style="padding:10px 16px;text-align:right">
                    <a href="{{ route('admin.global.help-center.show',$t->id) }}" style="padding:5px 12px;background:#6366f1;color:#fff;border-radius:6px;font-size:11px;font-weight:600;text-decoration:none">Reply</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="padding:40px;text-align:center;color:#9ca3af"><div style="font-size:36px;margin-bottom:10px">🎧</div><div>No tickets yet</div></td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
    @if($tickets->hasPages())<div style="padding:14px 18px;border-top:1px solid #f3f4f6">{{ $tickets->links() }}</div>@endif
</div>
@endsection
