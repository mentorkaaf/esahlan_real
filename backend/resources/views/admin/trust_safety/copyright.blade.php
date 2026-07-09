@extends('admin.layouts.app')
@section('title','Copyright Claims')
@section('content')
<div style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;">
    <div>
        <h1 style="font-size:20px;font-weight:900;color:#111827;">Copyright Claims</h1>
        <p style="font-size:13px;color:#6b7280;">DMCA & copyright infringement reports</p>
    </div>
    <a href="{{ route('admin.trust-safety.dashboard') }}" style="font-size:12px;color:#FF8A00;text-decoration:none;">← T&S Dashboard</a>
</div>

@if(session('success'))
<div style="background:#dcfce7;color:#16a34a;padding:10px 16px;border-radius:10px;margin-bottom:16px;font-weight:700;">✓ {{ session('success') }}</div>
@endif

{{-- Stats --}}
<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:20px;">
    @foreach([
        ['Total','total','#6b7280','#f9fafb'],
        ['Pending','pending','#d97706','#fef3c7'],
        ['Under Review','under_review','#3b82f6','#eff6ff'],
        ['Upheld','upheld','#dc2626','#fee2e2'],
        ['Dismissed','dismissed','#16a34a','#dcfce7'],
        ['Counter Notice','counter','#7c3aed','#f5f3ff'],
    ] as [$label,$key,$color,$bg])
    <div style="background:{{ $bg }};border-radius:12px;padding:14px;text-align:center;">
        <div style="font-size:24px;font-weight:900;color:{{ $color }};">{{ $stats[$key] }}</div>
        <div style="font-size:11px;color:#6b7280;font-weight:700;">{{ $label }}</div>
    </div>
    @endforeach
</div>

{{-- Filter --}}
<form method="GET" style="margin-bottom:16px;display:flex;gap:10px;">
    <select name="status" style="border:1px solid #eef0f6;border-radius:8px;padding:8px 12px;font-size:13px;">
        <option value="">All Statuses</option>
        @foreach(['pending','under_review','upheld','dismissed','counter_notice'] as $s)
        <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
        @endforeach
    </select>
    <button type="submit" style="background:#FF8A00;color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:13px;font-weight:700;cursor:pointer;">Filter</button>
</form>

{{-- Table --}}
<div style="background:#fff;border:1px solid #eef0f6;border-radius:14px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;">
        <thead style="background:#f9fafb;">
            <tr style="font-size:11px;color:#9ca3af;font-weight:700;text-align:left;">
                <th style="padding:12px 16px;">#</th>
                <th style="padding:12px 16px;">Claimant</th>
                <th style="padding:12px 16px;">Content</th>
                <th style="padding:12px 16px;">Description</th>
                <th style="padding:12px 16px;">Status</th>
                <th style="padding:12px 16px;">Date</th>
                <th style="padding:12px 16px;">Action</th>
            </tr>
        </thead>
        <tbody>
        @forelse($claims as $c)
        @php
            $sc=$c->status;
            $sColor=match($sc){'pending'=>'#d97706','under_review'=>'#3b82f6','upheld'=>'#dc2626','dismissed'=>'#16a34a','counter_notice'=>'#7c3aed',default=>'#6b7280'};
            $sBg=match($sc){'pending'=>'#fef3c7','under_review'=>'#eff6ff','upheld'=>'#fee2e2','dismissed'=>'#dcfce7','counter_notice'=>'#f5f3ff',default=>'#f9fafb'};
        @endphp
        <tr style="border-top:1px solid #f3f4f6;font-size:13px;">
            <td style="padding:12px 16px;color:#9ca3af;">{{ $c->id }}</td>
            <td style="padding:12px 16px;">
                <div style="font-weight:700;color:#111827;">{{ $c->claimant_name }}</div>
                <div style="font-size:11px;color:#9ca3af;">{{ $c->claimant_email }}</div>
            </td>
            <td style="padding:12px 16px;">
                <span style="background:#f3f4f6;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:700;color:#374151;">
                    {{ ucfirst($c->reported_type) }} #{{ $c->reported_id }}
                </span>
            </td>
            <td style="padding:12px 16px;max-width:200px;">
                <div style="color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;">
                    {{ Str::limit($c->work_description, 60) }}
                </div>
            </td>
            <td style="padding:12px 16px;">
                <span style="background:{{ $sBg }};color:{{ $sColor }};padding:3px 10px;border-radius:20px;font-size:10px;font-weight:800;">
                    {{ ucfirst(str_replace('_',' ',$sc)) }}
                </span>
            </td>
            <td style="padding:12px 16px;color:#9ca3af;font-size:12px;">{{ \Carbon\Carbon::parse($c->created_at)->format('M j, Y') }}</td>
            <td style="padding:12px 16px;">
                <a href="{{ route('admin.copyright.show', $c->id) }}"
                   style="background:#111827;color:#fff;padding:5px 12px;border-radius:7px;font-size:11px;font-weight:700;text-decoration:none;">
                    Review
                </a>
            </td>
        </tr>
        @empty
        <tr><td colspan="7" style="padding:40px;text-align:center;color:#9ca3af;">No copyright claims found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:16px;">{{ $claims->links() }}</div>
@endsection
