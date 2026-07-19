@extends('admin.layouts.app')
@section('title', 'Transactions')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">ePay Transactions</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li><a href="{{ route('admin.finance.index') }}">Finance</a></li>
            <li>Transactions</li>
        </ul>
    </div>
    <a href="{{ route('admin.finance.index') }}" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Finance
    </a>
</div>

<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;width:100%;">
            <select name="type" class="form-control" style="width:160px;" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="credit" {{ request('type')==='credit'?'selected':'' }}>Credit</option>
                <option value="debit"  {{ request('type')==='debit' ?'selected':'' }}>Debit</option>
            </select>
            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" style="width:160px;">
            <span style="font-size:12px;color:var(--text-muted);">to</span>
            <input type="date" name="date_to"   class="form-control" value="{{ request('date_to') }}"   style="width:160px;">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            @if(request()->hasAny(['type','date_from','date_to']))
            <a href="{{ route('admin.finance.transactions') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:var(--info);">
                <i class="fas fa-exchange-alt"></i>
            </div>
            Transactions
            <span class="badge badge-info" style="margin-left:4px;">{{ $transactions->total() }}</span>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>ePay Owner</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th>Reference</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                <tr>
                    <td style="font-size:12px;color:var(--text-muted);">{{ $tx->id }}</td>
                    <td>
                        @php $owner = $tx->wallet?->owner; @endphp
                        @if($owner)
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="avatar avatar-sm avatar-orange">{{ strtoupper(substr($owner->name??'U',0,1)) }}</div>
                            <div>
                                <div style="font-weight:700;font-size:13px;">{{ $owner->name ?? '—' }}</div>
                                <div style="font-size:11px;color:var(--text-muted);">{{ class_basename($tx->wallet->owner_type ?? '') }}</div>
                            </div>
                        </div>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $tx->type==='credit'?'badge-success':'badge-danger' }} badge-dot">
                            {{ ucfirst($tx->type) }}
                        </span>
                    </td>
                    <td>
                        <span style="font-weight:800;font-size:14px;color:{{ $tx->type==='credit'?'var(--success)':'var(--danger)' }};">
                            {{ $tx->type==='credit'?'+':'-' }}${{ number_format($tx->amount,2) }}
                        </span>
                    </td>
                    <td style="font-size:12.5px;max-width:220px;">{{ $tx->description ?? '—' }}</td>
                    <td style="font-size:12px;color:var(--text-muted);">{{ $tx->reference_id ?? '—' }}</td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
                        {{ \Carbon\Carbon::parse($tx->created_at)->format('d M Y H:i') }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-exchange-alt"></i>
                            <h3>No transactions found</h3>
                            <p>Try adjusting your filters</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer" style="display:flex;justify-content:center;">
        {{ $transactions->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
