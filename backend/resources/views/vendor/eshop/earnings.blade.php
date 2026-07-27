@extends('vendor.layouts.app')
@section('title', 'Earnings')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Earnings & Payouts</h1>
        <ul class="breadcrumb"><li><a href="{{ route('vendor.eshop.dashboard') }}">Dashboard</a></li><li>Earnings</li></ul>
    </div>
</div>

@if(session('success'))
<div style="background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:#15803d;font-weight:600;font-size:13px;">
    <i class="fa-solid fa-check-circle" style="margin-right:6px;"></i>{{ session('success') }}
</div>
@endif
@if($errors->any())
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:#dc2626;font-size:13px;">
    <i class="fa-solid fa-circle-exclamation" style="margin-right:6px;"></i>{{ $errors->first() }}
</div>
@endif

{{-- Summary Cards --}}
<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card green">
        <div class="stat-icon-wrap green"><i class="fa-solid fa-check-circle"></i></div>
        <div>
            <div class="stat-value">${{ number_format($summary->paid ?? 0, 2) }}</div>
            <div class="stat-label">Total Settled</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon-wrap orange"><i class="fa-solid fa-wallet"></i></div>
        <div>
            <div class="stat-value">${{ number_format($availableBalance, 2) }}</div>
            <div class="stat-label">Available to Withdraw</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon-wrap red"><i class="fa-solid fa-percentage"></i></div>
        <div>
            <div class="stat-value">${{ number_format($summary->total_commission ?? 0, 2) }}</div>
            <div class="stat-label">Total Commission Paid</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start;">

{{-- Commission History --}}
<div class="card">
    <div class="card-header"><h3 class="card-title">Commission History</h3></div>
    <div class="table-responsive">
    <table class="data-table">
        <thead><tr><th>Order #</th><th>Order Total</th><th>Commission</th><th>Your Earning</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        @forelse($commissions as $c)
        <tr>
            <td><code style="font-weight:700">{{ $c->order_number }}</code></td>
            <td>${{ number_format($c->order_amount, 2) }}</td>
            <td style="color:#ef4444;font-weight:700">-${{ number_format($c->commission_amount ?? 0, 2) }}</td>
            <td style="color:#22c55e;font-weight:800">${{ number_format($c->vendor_earning ?? 0, 2) }}</td>
            <td>
                <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;
                    background:{{ $c->status==='settled'?'#dcfce7':'#fef9c3' }};
                    color:{{ $c->status==='settled'?'#16a34a':'#92400e' }}">
                    {{ ucfirst($c->status) }}
                </span>
            </td>
            <td style="font-size:12px;color:#888">{{ \Carbon\Carbon::parse($c->order_date ?? $c->created_at)->format('d M Y') }}</td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:#aaa;padding:40px">No earnings yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div style="padding:12px 16px">{{ $commissions->links() }}</div>
</div>

{{-- Right Column --}}
<div style="display:flex;flex-direction:column;gap:16px;">

{{-- Withdrawal History --}}
<div class="card">
    <div class="card-header"><h3 class="card-title">Withdrawal History</h3></div>
    <div style="padding:0 16px 12px;">
        @forelse($withdrawals as $w)
        <div style="padding:12px 0;border-bottom:1px solid #f0f0f0;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                <div>
                    <div style="font-weight:800;font-size:15px;color:var(--navy)">${{ number_format($w->amount, 2) }}</div>
                    <div style="font-size:11px;color:#aaa;margin-top:2px;">
                        {{ ucwords(str_replace('_',' ',$w->method ?? 'bank')) }} · {{ $w->account_number }}
                    </div>
                    <div style="font-size:11px;color:#bbb;">{{ \Carbon\Carbon::parse($w->created_at)->format('d M Y, H:i') }}</div>
                    @if($w->transaction_reference)
                    <div style="font-size:11px;color:#22c55e;margin-top:2px;">Ref: {{ $w->transaction_reference }}</div>
                    @endif
                    @if($w->admin_note)
                    <div style="font-size:11px;color:#888;margin-top:2px;">Note: {{ $w->admin_note }}</div>
                    @endif
                </div>
                <span style="white-space:nowrap;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;
                    background:{{ $w->status==='processed'?'#dcfce7':($w->status==='rejected'?'#fee2e2':($w->status==='approved'?'#dbeafe':'#fef9c3')) }};
                    color:{{ $w->status==='processed'?'#16a34a':($w->status==='rejected'?'#991b1b':($w->status==='approved'?'#1d4ed8':'#92400e')) }}">
                    {{ $w->status === 'processed' ? 'Paid' : ucfirst($w->status) }}
                </span>
            </div>
        </div>
        @empty
        <div style="text-align:center;color:#aaa;padding:32px 0;font-size:13px;">No withdrawal requests yet.</div>
        @endforelse
    </div>
</div>

{{-- Request Payout Form --}}
<div class="card" style="padding:20px;">
    <h4 style="font-weight:800;font-size:15px;margin-bottom:4px;color:var(--navy)">
        <i class="fa-solid fa-hand-holding-dollar" style="color:#FF8A00;margin-right:6px;"></i>Request Payout
    </h4>
    <p style="font-size:12px;color:#888;margin-bottom:16px;">
        Available balance:
        <strong style="color:{{ $availableBalance > 0 ? '#22c55e' : '#aaa' }};font-size:15px;">
            ${{ number_format($availableBalance, 2) }}
        </strong>
    </p>

    @if($availableBalance > 0)
    <form method="POST" action="{{ route('vendor.eshop.withdraw') }}" id="withdrawForm">
        @csrf
        <div style="margin-bottom:12px;">
            <label style="font-size:12px;font-weight:700;color:var(--navy);display:block;margin-bottom:4px;">Amount ($)</label>
            <input type="number" name="amount" step="0.01" min="1" max="{{ $availableBalance }}"
                   value="{{ old('amount') }}" placeholder="0.00"
                   style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;font-weight:700;"
                   required>
            <div style="font-size:11px;color:#aaa;margin-top:3px;">Max: ${{ number_format($availableBalance, 2) }}</div>
        </div>

        <div style="margin-bottom:12px;">
            <label style="font-size:12px;font-weight:700;color:var(--navy);display:block;margin-bottom:4px;">Payment Method</label>
            <select name="method" style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;" required>
                <option value="">-- Select method --</option>
                <option value="evc_plus" {{ old('method')==='evc_plus'?'selected':'' }}>EVC Plus (Hormuud)</option>
                <option value="zaad" {{ old('method')==='zaad'?'selected':'' }}>Zaad (Telesom)</option>
                <option value="bank_transfer" {{ old('method')==='bank_transfer'?'selected':'' }}>Bank Transfer</option>
            </select>
        </div>

        <div style="margin-bottom:12px;">
            <label style="font-size:12px;font-weight:700;color:var(--navy);display:block;margin-bottom:4px;">Account Number / Phone</label>
            <input type="text" name="account_number" value="{{ old('account_number') }}"
                   placeholder="e.g. 0615xxxxxx"
                   style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;"
                   required>
        </div>

        <div style="margin-bottom:12px;">
            <label style="font-size:12px;font-weight:700;color:var(--navy);display:block;margin-bottom:4px;">Account Holder Name</label>
            <input type="text" name="account_name" value="{{ old('account_name', $vendor->name) }}"
                   placeholder="Full name on account"
                   style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;"
                   required>
        </div>

        <div style="margin-bottom:16px;">
            <label style="font-size:12px;font-weight:700;color:var(--navy);display:block;margin-bottom:4px;">Note (optional)</label>
            <textarea name="note" rows="2" placeholder="Any additional information..."
                      style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13px;resize:none;">{{ old('note') }}</textarea>
        </div>

        <button type="submit"
                style="width:100%;background:linear-gradient(135deg,#FF8A00,#FF4E00);color:#fff;border:none;padding:11px;border-radius:10px;font-weight:800;font-size:14px;cursor:pointer;">
            <i class="fa-solid fa-paper-plane" style="margin-right:6px;"></i>Submit Withdrawal Request
        </button>
    </form>
    @else
    <div style="text-align:center;padding:20px 0;color:#aaa;">
        <i class="fa-solid fa-lock" style="font-size:28px;margin-bottom:8px;display:block;"></i>
        <div style="font-size:13px;">No available balance to withdraw.</div>
        <div style="font-size:11px;margin-top:4px;">Earnings become available after orders are delivered & settled.</div>
    </div>
    @endif
</div>

</div>{{-- /right column --}}
</div>
@endsection
