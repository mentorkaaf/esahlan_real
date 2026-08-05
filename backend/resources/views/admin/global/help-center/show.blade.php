@extends('admin.layouts.app')
@section('title', 'Ticket '.$ticket->ticket_number)

@section('content')
<div class="page-header">
    <div><h1 class="page-title">Ticket {{ $ticket->ticket_number }}</h1><p class="page-subtitle">{{ $ticket->subject }}</p></div>
    <div style="display:flex;gap:10px;align-items:center">
        <a href="{{ route('admin.global.help-center.index') }}" style="color:#6b7280;text-decoration:none;font-size:13px">← Back</a>
        @if($ticket->status !== 'closed')
        <form method="POST" action="{{ route('admin.global.help-center.close', $ticket->id) }}">
            @csrf
            <button type="submit" style="padding:8px 16px;background:#fee2e2;color:#dc2626;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer">Close Ticket</button>
        </form>
        @endif
    </div>
</div>

@if(session('success'))<div style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">✅ {{ session('success') }}</div>@endif

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start">

{{-- Conversation --}}
<div style="display:flex;flex-direction:column;gap:14px">
    {{-- Original message --}}
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:20px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
            <div>
                <div style="font-size:14px;font-weight:700;color:#111">{{ $ticket->name }}</div>
                <div style="font-size:12px;color:#6b7280">{{ $ticket->email }}</div>
            </div>
            <div style="font-size:11px;color:#9ca3af">{{ \Carbon\Carbon::parse($ticket->created_at)->format('M d, Y g:i A') }}</div>
        </div>
        <div style="font-size:13px;color:#374151;line-height:1.7;background:#f9fafb;border-radius:8px;padding:14px">{{ $ticket->message }}</div>
    </div>

    {{-- Replies --}}
    @foreach($replies as $reply)
    <div style="background:#fff;border-radius:12px;border:1px solid {{ $reply->is_admin ? '#ddd6fe' : '#e5e7eb' }};padding:20px;{{ $reply->is_admin ? 'border-left:3px solid #7c3aed' : '' }}">
        <div style="display:flex;justify-content:space-between;margin-bottom:10px">
            <div style="font-size:13px;font-weight:700;color:{{ $reply->is_admin ? '#7c3aed' : '#111' }}">
                {{ $reply->is_admin ? ('👤 ' . ($reply->admin_name ?? 'Admin')) : '💬 Customer' }}
            </div>
            <div style="font-size:11px;color:#9ca3af">{{ \Carbon\Carbon::parse($reply->created_at)->format('M d, Y g:i A') }}</div>
        </div>
        <div style="font-size:13px;color:#374151;line-height:1.7">{{ $reply->message }}</div>
    </div>
    @endforeach

    {{-- Reply Form --}}
    @if($ticket->status !== 'closed')
    <div style="background:#fff;border-radius:12px;border:2px solid #7c3aed;padding:20px">
        <h3 style="font-size:13px;font-weight:700;color:#7c3aed;margin-bottom:12px">💬 Reply to Customer</h3>
        <form method="POST" action="{{ route('admin.global.help-center.reply', $ticket->id) }}">
            @csrf
            <textarea name="message" rows="5" required placeholder="Type your reply..." style="width:100%;padding:12px;border:1px solid #ddd6fe;border-radius:8px;font-size:13px;resize:vertical;margin-bottom:10px"></textarea>
            <button type="submit" style="padding:10px 24px;background:#7c3aed;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">Send Reply</button>
        </form>
    </div>
    @else
    <div style="background:#f9fafb;border-radius:12px;border:1px solid #e5e7eb;padding:16px;text-align:center;color:#9ca3af;font-size:13px">This ticket is closed</div>
    @endif
</div>

{{-- Info Panel --}}
<div style="display:flex;flex-direction:column;gap:14px">
    <div style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;padding:20px">
        <h3 style="font-size:13px;font-weight:700;color:#111;margin-bottom:14px">Ticket Details</h3>
        @php
        $pC=['low'=>'#6b7280','medium'=>'#f59e0b','high'=>'#ef4444','urgent'=>'#dc2626'];
        $sC=['open'=>'#3b82f6','in_progress'=>'#f59e0b','resolved'=>'#10b981','closed'=>'#9ca3af'];
        @endphp
        @foreach([['Status',str_replace('_',' ',ucfirst($ticket->status)),$sC[$ticket->status]??'#9ca3af'],['Priority',ucfirst($ticket->priority),$pC[$ticket->priority]??'#9ca3af'],['Category',ucfirst($ticket->category),'#374151']] as [$l,$v,$c])
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f3f4f6">
            <span style="font-size:12px;color:#6b7280">{{ $l }}</span>
            <span style="font-size:12px;font-weight:700;color:{{ $c }}">{{ $v }}</span>
        </div>
        @endforeach
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f3f4f6">
            <span style="font-size:12px;color:#6b7280">Opened</span>
            <span style="font-size:12px;color:#374151">{{ \Carbon\Carbon::parse($ticket->created_at)->diffForHumans() }}</span>
        </div>
    </div>
</div>

</div>
@endsection
