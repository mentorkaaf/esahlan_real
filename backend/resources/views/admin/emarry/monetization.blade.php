@extends('admin.layouts.app')
@section('title', 'eMarry Monetization')

@push('styles')
<style>
:root{--brand:#FF8A00;--green:#10b981;--blue:#3b82f6;--purple:#8b5cf6;--red:#ef4444;--amber:#f59e0b}
.em-wrap{padding:20px 24px;font-family:'Segoe UI',system-ui,sans-serif}

/* Header */
.em-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;gap:12px;flex-wrap:wrap}
.em-title{font-size:20px;font-weight:900;color:#0f172a;display:flex;align-items:center;gap:8px}
.em-title span.e{color:var(--brand)}

/* Tabs */
.em-tabs{display:flex;gap:4px;background:#f1f5f9;border-radius:10px;padding:4px;margin-bottom:20px;width:fit-content}
.em-tab{padding:7px 16px;border-radius:7px;font-size:12.5px;font-weight:700;color:#64748b;text-decoration:none;transition:all .15s}
.em-tab.active{background:#fff;color:#0f172a;box-shadow:0 1px 3px rgba(0,0,0,.12)}

/* KPI cards */
.kpi-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:10px;margin-bottom:20px}
.kpi{background:#fff;border-radius:12px;border:1px solid #e4e9f0;padding:14px 16px}
.kpi-val{font-size:22px;font-weight:900;color:#0f172a;font-variant-numeric:tabular-nums}
.kpi-lbl{font-size:11px;color:#6b7280;margin-top:2px}
.kpi-icon{font-size:20px;margin-bottom:6px}

/* Table */
.tbl-wrap{background:#fff;border-radius:12px;border:1px solid #e4e9f0;overflow:hidden;margin-bottom:20px}
.tbl-hdr{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #f1f5f9}
.tbl-title{font-size:13.5px;font-weight:800;color:#0f172a}
table{width:100%;border-collapse:collapse;font-size:12.5px}
th{background:#f8fafc;padding:8px 12px;text-align:left;font-weight:700;color:#6b7280;font-size:11.5px;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap}
td{padding:10px 12px;border-top:1px solid #f1f5f9;color:#374151;vertical-align:middle}
tr:hover td{background:#fafafa}

/* Badges */
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:10.5px;font-weight:700}
.badge-green{background:rgba(16,185,129,.1);color:#059669}
.badge-amber{background:rgba(245,158,11,.1);color:#d97706}
.badge-red{background:rgba(239,68,68,.1);color:#dc2626}
.badge-blue{background:rgba(59,130,246,.1);color:#2563eb}
.badge-purple{background:rgba(139,92,246,.1);color:#7c3aed}
.badge-gray{background:#f1f5f9;color:#64748b}
.badge-orange{background:rgba(255,138,0,.1);color:var(--brand)}

/* Avatar */
.av{width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0}
.av-initials{width:32px;height:32px;border-radius:50%;background:var(--brand);display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;flex-shrink:0}
.user-cell{display:flex;align-items:center;gap:8px}
.user-name{font-weight:600;color:#0f172a;font-size:12.5px}
.user-email{font-size:11px;color:#9ca3af}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:7px;font-size:11.5px;font-weight:700;cursor:pointer;border:none;text-decoration:none;transition:opacity .15s}
.btn:hover{opacity:.85}
.btn-green{background:#10b981;color:#fff}
.btn-red{background:#ef4444;color:#fff}
.btn-gray{background:#f1f5f9;color:#374151}
.btn-orange{background:var(--brand);color:#fff}
.btn-sm{padding:4px 8px;font-size:11px}

/* Chart */
.chart-bar-wrap{display:flex;align-items:flex-end;gap:3px;height:60px;margin-top:8px}
.chart-bar{flex:1;border-radius:3px 3px 0 0;background:rgba(255,138,0,.2);transition:height .3s;position:relative}
.chart-bar:hover{background:rgba(255,138,0,.5)}

/* Alert */
.alert{padding:10px 14px;border-radius:8px;font-size:12.5px;margin-bottom:14px}
.alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626}

/* Screenshot preview */
.ss-img{width:60px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #e4e9f0;cursor:pointer}
.ss-img:hover{opacity:.8}

/* Grant credits form */
.gc-form{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap}
.gc-form input{padding:7px 10px;border:1px solid #e4e9f0;border-radius:8px;font-size:12.5px;outline:none}
.gc-form input:focus{border-color:var(--brand)}

/* Revenue mini chart */
.rev-chart{display:flex;align-items:flex-end;gap:2px;height:50px;margin-top:6px}
.rev-bar{flex:1;border-radius:2px 2px 0 0;background:var(--brand);opacity:.7;min-width:3px}
.rev-bar:hover{opacity:1}

/* Pagination */
.pag{display:flex;justify-content:flex-end;padding:12px 16px;border-top:1px solid #f1f5f9}
.pag a,.pag span{padding:4px 10px;border-radius:6px;font-size:12px;margin:0 1px;font-weight:600;text-decoration:none;color:#374151;border:1px solid #e4e9f0}
.pag .active-page{background:var(--brand);color:#fff;border-color:var(--brand)}
</style>
@endpush

@section('content')
<div class="em-wrap">

{{-- Header --}}
<div class="em-hdr">
    <div class="em-title">
        <span>💍</span>
        <span><span class="e">e</span>Marry Monetization</span>
    </div>
    <a href="{{ route('admin.emarry.index') }}" class="btn btn-gray">← Back</a>
</div>

{{-- Alerts --}}
@if(session('success'))
<div class="alert alert-success">✓ {{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-error">✗ {{ session('error') }}</div>
@endif

{{-- KPI Strip --}}
<div class="kpi-row">
    <div class="kpi">
        <div class="kpi-icon">💎</div>
        <div class="kpi-val">{{ number_format($stats['active_subs']) }}</div>
        <div class="kpi-lbl">Active Subscribers</div>
    </div>
    <div class="kpi">
        <div class="kpi-icon">⭐</div>
        <div class="kpi-val">{{ number_format($stats['premium_users']) }}</div>
        <div class="kpi-lbl">Premium Users</div>
    </div>
    <div class="kpi">
        <div class="kpi-icon">🥇</div>
        <div class="kpi-val">{{ number_format($stats['gold_users']) }}</div>
        <div class="kpi-lbl">Gold Users</div>
    </div>
    <div class="kpi">
        <div class="kpi-icon">💰</div>
        <div class="kpi-val">${{ number_format($stats['revenue_month'], 2) }}</div>
        <div class="kpi-lbl">Revenue This Month</div>
    </div>
    <div class="kpi">
        <div class="kpi-icon">📊</div>
        <div class="kpi-val">${{ number_format($stats['revenue_total'], 2) }}</div>
        <div class="kpi-lbl">Total Revenue</div>
    </div>
    <div class="kpi">
        <div class="kpi-icon">⚡</div>
        <div class="kpi-val">{{ number_format($stats['credits_sold']) }}</div>
        <div class="kpi-lbl">Credits Sold</div>
    </div>
    <div class="kpi">
        <div class="kpi-icon">🔥</div>
        <div class="kpi-val">{{ number_format($stats['credits_used']) }}</div>
        <div class="kpi-lbl">Credits Used</div>
    </div>
    @if($stats['pending_mp'] > 0)
    <div class="kpi" style="border-color:#f59e0b;background:#fffbeb">
        <div class="kpi-icon">⏳</div>
        <div class="kpi-val" style="color:#d97706">{{ $stats['pending_mp'] }}</div>
        <div class="kpi-lbl" style="color:#d97706">Pending Mobile Pay</div>
    </div>
    @endif
</div>

{{-- Revenue Chart --}}
@if($revenueChart->count() > 0)
@php $maxRev = $revenueChart->max('rev') ?: 1; @endphp
<div class="tbl-wrap" style="padding:16px;margin-bottom:20px">
    <div style="font-size:13px;font-weight:800;color:#0f172a;margin-bottom:10px">Revenue — Last 30 Days</div>
    <div class="rev-chart">
        @foreach($revenueChart as $r)
        <div class="rev-bar" style="height:{{ max(4, round($r->rev/$maxRev*50)) }}px" title="{{ $r->date }}: ${{ number_format($r->rev,2) }}"></div>
        @endforeach
    </div>
    <div style="display:flex;justify-content:space-between;font-size:10px;color:#9ca3af;margin-top:3px">
        <span>{{ $revenueChart->first()->date ?? '' }}</span>
        <span>{{ $revenueChart->last()->date ?? '' }}</span>
    </div>
</div>
@endif

{{-- Tabs --}}
<div class="em-tabs">
    <a href="{{ request()->fullUrlWithQuery(['tab'=>'subscriptions']) }}"
       class="em-tab {{ $tab==='subscriptions'||$tab==='overview'?'active':'' }}">📋 Subscriptions</a>
    <a href="{{ request()->fullUrlWithQuery(['tab'=>'mobile_pay']) }}"
       class="em-tab {{ $tab==='mobile_pay'?'active':'' }}">
        📱 Mobile Pay
        @if($stats['pending_mp']>0)
        <span style="background:#ef4444;color:#fff;border-radius:10px;padding:0 5px;font-size:10px;margin-left:4px">{{ $stats['pending_mp'] }}</span>
        @endif
    </a>
    <a href="{{ request()->fullUrlWithQuery(['tab'=>'credits']) }}"
       class="em-tab {{ $tab==='credits'?'active':'' }}">⚡ Credits</a>
    <a href="{{ request()->fullUrlWithQuery(['tab'=>'grant']) }}"
       class="em-tab {{ $tab==='grant'?'active':'' }}">🎁 Grant Credits</a>
</div>

{{-- ══ SUBSCRIPTIONS TAB ══ --}}
@if($tab==='subscriptions'||$tab==='overview')
<div class="tbl-wrap">
    <div class="tbl-hdr">
        <div class="tbl-title">Active & Past Subscriptions</div>
        <div style="display:flex;gap:8px">
            <a href="{{ request()->fullUrlWithQuery(['plan'=>'premium','tab'=>'subscriptions']) }}" class="btn btn-sm btn-gray">Premium</a>
            <a href="{{ request()->fullUrlWithQuery(['plan'=>'gold','tab'=>'subscriptions']) }}" class="btn btn-sm btn-gray" style="background:#fef3c7;color:#d97706">Gold</a>
            <a href="{{ request()->fullUrlWithQuery(['plan'=>null,'status'=>null,'tab'=>'subscriptions']) }}" class="btn btn-sm btn-gray">All</a>
        </div>
    </div>
    <div style="overflow-x:auto">
    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>User</th>
            <th>Plan</th>
            <th>Method</th>
            <th>Amount</th>
            <th>Started</th>
            <th>Expires</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse($subscriptions as $s)
        <tr>
            <td style="color:#9ca3af">{{ $s->id }}</td>
            <td>
                <div class="user-cell">
                    <div class="av-initials">{{ strtoupper(substr($s->name??'?',0,1)) }}</div>
                    <div>
                        <div class="user-name">{{ $s->name }}</div>
                        <div class="user-email">{{ $s->email }}</div>
                    </div>
                </div>
            </td>
            <td>
                @if($s->plan==='gold')
                <span class="badge badge-amber">🥇 Gold</span>
                @else
                <span class="badge badge-orange">⭐ Premium</span>
                @endif
            </td>
            <td>
                @if($s->payment_method==='waafi_pay')
                <span class="badge badge-blue">💳 WaafiPay</span>
                @elseif($s->payment_method==='epay')
                <span class="badge badge-purple">🏦 ePay</span>
                @else
                <span class="badge badge-gray">📱 Mobile Pay</span>
                @endif
            </td>
            <td style="font-weight:700;color:#10b981">${{ number_format($s->amount,2) }}</td>
            <td style="color:#6b7280;font-size:11.5px">{{ \Carbon\Carbon::parse($s->starts_at)->format('d M Y') }}</td>
            <td style="font-size:11.5px">
                @if($s->status==='active')
                    <span style="color:{{ \Carbon\Carbon::parse($s->expires_at)->isPast()?'#ef4444':'#374151' }}">
                        {{ \Carbon\Carbon::parse($s->expires_at)->format('d M Y') }}
                    </span>
                @else
                    <span style="color:#9ca3af">—</span>
                @endif
            </td>
            <td>
                @if($s->status==='active' && !\Carbon\Carbon::parse($s->expires_at)->isPast())
                <span class="badge badge-green">Active</span>
                @elseif($s->status==='cancelled')
                <span class="badge badge-red">Cancelled</span>
                @else
                <span class="badge badge-gray">Expired</span>
                @endif
            </td>
            <td>
                @if($s->status==='active' && !\Carbon\Carbon::parse($s->expires_at)->isPast())
                <form method="POST" action="{{ route('admin.emarry.monetization.subscription.cancel', $s->id) }}"
                      onsubmit="return confirm('Cancel this subscription?')">
                    @csrf
                    <button class="btn btn-sm btn-red">Cancel</button>
                </form>
                @else
                <span style="color:#9ca3af;font-size:11px">—</span>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="9" style="text-align:center;padding:30px;color:#9ca3af">No subscriptions found</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    @if($subscriptions->hasPages())
    <div class="pag">
        {!! $subscriptions->appends(request()->query())->links('pagination::simple-bootstrap-4') !!}
    </div>
    @endif
</div>
@endif

{{-- ══ MOBILE PAY TAB ══ --}}
@if($tab==='mobile_pay')
<div class="tbl-wrap">
    <div class="tbl-hdr">
        <div class="tbl-title">Mobile Pay Requests</div>
        <div style="display:flex;gap:6px">
            <a href="{{ request()->fullUrlWithQuery(['mp_status'=>'pending','tab'=>'mobile_pay']) }}" class="btn btn-sm {{ request('mp_status','pending')==='pending'?'btn-orange':'btn-gray' }}">⏳ Pending</a>
            <a href="{{ request()->fullUrlWithQuery(['mp_status'=>'approved','tab'=>'mobile_pay']) }}" class="btn btn-sm {{ request('mp_status')==='approved'?'btn-green':'btn-gray' }}">✓ Approved</a>
            <a href="{{ request()->fullUrlWithQuery(['mp_status'=>'rejected','tab'=>'mobile_pay']) }}" class="btn btn-sm {{ request('mp_status')==='rejected'?'btn-red':'btn-gray' }}">✗ Rejected</a>
        </div>
    </div>
    <div style="overflow-x:auto">
    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>User</th>
            <th>Item</th>
            <th>Amount</th>
            <th>Sender Phone</th>
            <th>Screenshot</th>
            <th>Submitted</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse($mobilePayRequests as $r)
        <tr>
            <td style="color:#9ca3af">{{ $r->id }}</td>
            <td>
                <div class="user-cell">
                    <div class="av-initials">{{ strtoupper(substr($r->name??'?',0,1)) }}</div>
                    <div>
                        <div class="user-name">{{ $r->name }}</div>
                        <div class="user-email">{{ $r->phone }}</div>
                    </div>
                </div>
            </td>
            <td>
                @if($r->item_type==='subscription')
                <span class="badge badge-orange">{{ ucfirst($r->item_key) }} Plan</span>
                @else
                <span class="badge badge-purple">⚡ {{ ucfirst($r->item_key) }} Credits</span>
                @endif
            </td>
            <td style="font-weight:700;color:#10b981">${{ number_format($r->amount,2) }}</td>
            <td style="font-size:11.5px;color:#6b7280">{{ $r->sender_phone ?: '—' }}</td>
            <td>
                @if($r->screenshot_url)
                <a href="{{ $r->screenshot_url }}" target="_blank">
                    <img src="{{ $r->screenshot_url }}" class="ss-img" alt="Screenshot">
                </a>
                @else
                <span style="color:#9ca3af;font-size:11px">Not uploaded</span>
                @endif
            </td>
            <td style="color:#6b7280;font-size:11.5px">
                {{ \Carbon\Carbon::parse($r->created_at)->format('d M, H:i') }}
            </td>
            <td>
                @if($r->status==='pending')
                <span class="badge badge-amber">⏳ Pending</span>
                @elseif($r->status==='approved')
                <span class="badge badge-green">✓ Approved</span>
                @else
                <span class="badge badge-red">✗ Rejected</span>
                @endif
            </td>
            <td>
                @if($r->status==='pending')
                <div style="display:flex;flex-direction:column;gap:5px">
                    <form method="POST" action="{{ route('admin.emarry.monetization.mobile-pay.approve', $r->id) }}">
                        @csrf
                        <button class="btn btn-sm btn-green" style="width:100%">✓ Approve</button>
                    </form>
                    <form method="POST" action="{{ route('admin.emarry.monetization.mobile-pay.reject', $r->id) }}"
                          onsubmit="return confirm('Reject this payment?')">
                        @csrf
                        <button class="btn btn-sm btn-red" style="width:100%">✗ Reject</button>
                    </form>
                </div>
                @else
                <span style="font-size:11px;color:#9ca3af">
                    {{ $r->reviewed_at ? 'By '.$r->admin_name.' · '.\Carbon\Carbon::parse($r->reviewed_at)->format('d M') : '—' }}
                </span>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="9" style="text-align:center;padding:30px;color:#9ca3af">
            @if(request('mp_status','pending')==='pending')
            ✅ No pending mobile pay requests
            @else
            No requests found
            @endif
        </td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    @if($mobilePayRequests->hasPages())
    <div class="pag">
        {!! $mobilePayRequests->appends(request()->query())->links('pagination::simple-bootstrap-4') !!}
    </div>
    @endif
</div>
@endif

{{-- ══ CREDITS TAB ══ --}}
@if($tab==='credits')
<div class="tbl-wrap">
    <div class="tbl-hdr">
        <div class="tbl-title">Credit Transactions Ledger</div>
        <div style="display:flex;gap:8px;font-size:12px;color:#6b7280;align-items:center">
            <span>🟢 = Added &nbsp; 🔴 = Used</span>
        </div>
    </div>
    <div style="overflow-x:auto">
    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>User</th>
            <th>Type</th>
            <th>Credits</th>
            <th>Paid</th>
            <th>Method</th>
            <th>Ref</th>
            <th>Date</th>
        </tr>
        </thead>
        <tbody>
        @forelse($creditTxns as $t)
        <tr>
            <td style="color:#9ca3af">{{ $t->id }}</td>
            <td>
                <div class="user-cell">
                    <div class="av-initials">{{ strtoupper(substr($t->name??'?',0,1)) }}</div>
                    <div>
                        <div class="user-name">{{ $t->name }}</div>
                        <div class="user-email">{{ $t->email }}</div>
                    </div>
                </div>
            </td>
            <td>
                @php $typeMap=['purchase'=>['badge-green','🛍️ Purchase'],'super_like'=>['badge-red','⭐ Super Like'],'boost'=>['badge-purple','🚀 Boost'],'undo'=>['badge-blue','↩ Undo'],'admin_grant'=>['badge-amber','🎁 Admin Grant']]; $tm=$typeMap[$t->type]??['badge-gray',$t->type]; @endphp
                <span class="badge {{ $tm[0] }}">{{ $tm[1] }}</span>
            </td>
            <td>
                <span style="font-size:14px;font-weight:800;color:{{ $t->amount>0?'#10b981':'#ef4444' }}">
                    {{ $t->amount>0?'+':'' }}{{ $t->amount }}
                </span>
            </td>
            <td style="font-weight:600">{{ $t->paid_amount?'$'.number_format($t->paid_amount,2):'—' }}</td>
            <td>
                @if($t->payment_method==='waafi_pay')
                <span class="badge badge-blue" style="font-size:10px">WaafiPay</span>
                @elseif($t->payment_method==='epay')
                <span class="badge badge-purple" style="font-size:10px">ePay</span>
                @elseif($t->payment_method==='mobile_pay')
                <span class="badge badge-gray" style="font-size:10px">Mobile Pay</span>
                @else
                <span style="color:#9ca3af">—</span>
                @endif
            </td>
            <td style="font-size:10.5px;color:#9ca3af;max-width:100px;overflow:hidden;text-overflow:ellipsis">{{ $t->payment_reference ?? '—' }}</td>
            <td style="color:#6b7280;font-size:11.5px;white-space:nowrap">{{ \Carbon\Carbon::parse($t->created_at)->format('d M, H:i') }}</td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;padding:30px;color:#9ca3af">No credit transactions</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    @if($creditTxns->hasPages())
    <div class="pag">
        {!! $creditTxns->appends(request()->query())->links('pagination::simple-bootstrap-4') !!}
    </div>
    @endif
</div>
@endif

{{-- ══ GRANT CREDITS TAB ══ --}}
@if($tab==='grant')
<div class="tbl-wrap" style="padding:20px">
    <div class="tbl-title" style="margin-bottom:16px">🎁 Grant Credits to User</div>
    <p style="font-size:12.5px;color:#6b7280;margin-bottom:16px">
        Manually grant credits to any user (e.g. compensation, promotion, testing).
        This will be recorded in the credit ledger as an admin grant.
    </p>
    <form method="POST" action="{{ route('admin.emarry.monetization.credits.grant') }}" class="gc-form">
        @csrf
        <div>
            <label style="font-size:11.5px;font-weight:700;color:#374151;display:block;margin-bottom:4px">User ID</label>
            <input type="number" name="user_id" placeholder="e.g. 1027" min="1" required style="width:130px">
        </div>
        <div>
            <label style="font-size:11.5px;font-weight:700;color:#374151;display:block;margin-bottom:4px">Credits to Grant</label>
            <input type="number" name="credits" placeholder="e.g. 10" min="1" max="1000" required style="width:130px">
        </div>
        <button type="submit" class="btn btn-orange">Grant Credits</button>
    </form>
</div>
@endif

{{-- Quick nav to main eMarry section --}}
<div style="margin-top:12px;display:flex;gap:8px">
    <a href="{{ route('admin.emarry.index') }}" class="btn btn-gray btn-sm">👥 Profiles</a>
    <a href="{{ route('admin.emarry.interests') }}" class="btn btn-gray btn-sm">💞 Interests</a>
    <a href="{{ route('admin.emarry.monetization') }}?tab=mobile_pay" class="btn btn-sm {{ $stats['pending_mp']>0?'btn-orange':'btn-gray' }}">
        📱 Mobile Pay @if($stats['pending_mp']>0)({{ $stats['pending_mp'] }} pending)@endif
    </a>
</div>

</div>
@endsection
