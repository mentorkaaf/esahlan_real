@extends('admin.layouts.app')
@section('title', 'eExchange — Rates Management')
@section('content')

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-exchange-alt" style="color:var(--primary)"></i> eExchange Rates</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">eExchange</li>
        </ol>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')">
        <i class="fas fa-plus"></i> Add Rate
    </button>
</div>

{{-- Info banner --}}
<div class="alert alert-warning">
    <i class="fas fa-info-circle"></i>
    <strong>Fee Policy:</strong> A service fee (default 1%) is charged on every exchange. Set fee per pair below. Rates are applied as: <code>received = sent × rate × (1 - fee%/100)</code>
</div>

<div class="grid-2">
    {{-- Rates table --}}
    <div class="card">
        <div class="card-header">Exchange Rate Matrix</div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>From</th>
                        <th>To</th>
                        <th>Rate</th>
                        <th>Fee %</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rates as $rate)
                    <tr>
                        <td>
                            <span class="badge badge-info">{{ strtoupper($rate->from_wallet) }}</span>
                        </td>
                        <td>
                            <span class="badge badge-success">{{ strtoupper($rate->to_wallet) }}</span>
                        </td>
                        <td><strong>{{ $rate->rate }}</strong></td>
                        <td>{{ $rate->fee_percentage ?? 1 }}%</td>
                        <td>
                            <form action="{{ route('admin.module-data.exchange.destroy', $rate->id) }}" method="POST" onsubmit="return confirm('Delete?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;padding:30px;color:#888;">No rates configured yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Quick add form --}}
    <div class="card">
        <div class="card-header">Add / Update Rate</div>
        <div class="card-body">
            <form action="{{ route('admin.module-data.exchange.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label">From Wallet *</label>
                    <select name="from_wallet" class="form-control" required>
                        @foreach($wallets as $w)
                            <option value="{{ $w }}">{{ strtoupper($w) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">To Wallet *</label>
                    <select name="to_wallet" class="form-control" required>
                        @foreach($wallets as $w)
                            <option value="{{ $w }}">{{ strtoupper($w) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Exchange Rate *</label>
                    <input type="number" name="rate" class="form-control" step="0.0001" required placeholder="e.g. 1.0000 for 1:1">
                    <small class="text-muted">How many units of TO_WALLET you get for 1 unit of FROM_WALLET</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Service Fee (%)</label>
                    <input type="number" name="fee_percentage" class="form-control" step="0.01" value="1.00" min="0" max="100">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <i class="fas fa-save"></i> Save Rate
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Full matrix quick-fill --}}
<div class="card">
    <div class="card-header">Wallet Overview</div>
    <div class="card-body">
        <p class="text-muted" style="font-size:13px;margin-bottom:12px;">Supported wallets: EVC Plus, eDahab, Jeep Money, Premier. Each pair needs a separate rate entry (and its reverse if needed).</p>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
            @foreach(['evc' => ['EVC Plus','#E74C3C'], 'edahab' => ['eDahab','#27AE60'], 'jeep' => ['Jeep Money','#2980B9'], 'premier' => ['Premier','#8E44AD']] as $key => $info)
            <div style="border:2px solid {{ $info[1] }};border-radius:12px;padding:16px;text-align:center;">
                <div style="font-size:20px;font-weight:900;color:{{ $info[1] }};">{{ strtoupper($key) }}</div>
                <div style="font-size:13px;font-weight:600;color:#333;margin-top:4px;">{{ $info[0] }}</div>
                @php $rateCount = $rates->where('from_wallet', $key)->count() + $rates->where('to_wallet', $key)->count(); @endphp
                <div style="font-size:11px;color:#888;margin-top:4px;">{{ $rateCount }} rate{{ $rateCount != 1 ? 's' : '' }}</div>
            </div>
            @endforeach
        </div>
    </div>
</div>

@endsection
