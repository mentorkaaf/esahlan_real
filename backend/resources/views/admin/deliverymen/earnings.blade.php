@extends('admin.layouts.app')
@section('title', 'Driver Earnings')
@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">Driver Earnings</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.deliverymen.index') }}">Deliverymen</a></li>
            <li>Earnings</li>
        </ul>
    </div>
    <div style="display:flex;gap:8px;">
        <form action="{{ route('admin.deliverymen.earnings.bulk-reset') }}" method="POST" onsubmit="return confirm('Reset ALL driver earnings? This cannot be undone.')">
            @csrf
            <button class="btn btn-danger btn-sm"><i class="fas fa-undo"></i> Reset All</button>
        </form>
        <a href="{{ route('admin.deliverymen.index') }}" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

{{-- Commission Config Banner --}}
<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;background:#3b82f6;border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-percentage" style="color:#fff;font-size:14px;"></i>
        </div>
        <div>
            <div style="font-weight:800;font-size:13px;color:#1e40af;">Delivery Fee Commission Rate: <span style="color:#ff6b35;">{{ $summary['commission_rate'] }}%</span></div>
            <div style="font-size:11px;color:#6b7280;">Admin keeps {{ $summary['commission_rate'] }}% of each delivery fee. Total collected: <strong>${{ number_format($summary['total_commission'], 2) }}</strong></div>
        </div>
    </div>
    <a href="{{ route('admin.settings.index') }}#tab-delivery" class="btn btn-primary btn-sm" style="background:#3b82f6;border:none;">
        <i class="fas fa-cog"></i> Change Rate
    </a>
</div>

{{-- Summary Cards --}}
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px;">
    <div style="background:#fff;border-radius:14px;padding:20px;border-left:5px solid #10B981;">
        <div style="font-size:11px;color:#8A8A9A;margin-bottom:6px;">Total Paid to Drivers</div>
        <div style="font-size:24px;font-weight:900;color:#10B981;">${{ number_format($summary['total'], 2) }}</div>
    </div>
    <div style="background:#fff;border-radius:14px;padding:20px;border-left:5px solid #FF8A00;">
        <div style="font-size:11px;color:#8A8A9A;margin-bottom:6px;">Today</div>
        <div style="font-size:24px;font-weight:900;color:#FF8A00;">${{ number_format($summary['today'], 2) }}</div>
    </div>
    <div style="background:#fff;border-radius:14px;padding:20px;border-left:5px solid #3B82F6;">
        <div style="font-size:11px;color:#8A8A9A;margin-bottom:6px;">This Month</div>
        <div style="font-size:24px;font-weight:900;color:#3B82F6;">${{ number_format($summary['this_month'], 2) }}</div>
    </div>
    <div style="background:#fff;border-radius:14px;padding:20px;border-left:5px solid #8B5CF6;">
        <div style="font-size:11px;color:#8A8A9A;margin-bottom:6px;">Total Deliveries</div>
        <div style="font-size:24px;font-weight:900;color:#8B5CF6;">{{ number_format($summary['total_count']) }}</div>
    </div>
    <div style="background:#fff;border-radius:14px;padding:20px;border-left:5px solid #ef4444;">
        <div style="font-size:11px;color:#8A8A9A;margin-bottom:6px;">Platform Commission</div>
        <div style="font-size:24px;font-weight:900;color:#ef4444;">${{ number_format($summary['total_commission'], 2) }}</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start;">
    <div>
        {{-- Filters --}}
        <div class="card" style="padding:12px 16px;margin-bottom:12px;">
            <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <select name="driver_id" class="form-control" style="width:200px;">
                    <option value="">All Drivers</option>
                    @foreach($drivers as $d)
                    <option value="{{ $d->id }}" {{ request('driver_id') == $d->id ? 'selected' : '' }}>{{ $d->user?->name ?? 'Driver #'.$d->id }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" class="form-control" style="width:150px;" value="{{ request('from') }}">
                <span style="color:#8A8A9A;">to</span>
                <input type="date" name="to" class="form-control" style="width:150px;" value="{{ request('to') }}">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                @if(request()->hasAny(['driver_id','from','to']))
                <a href="{{ route('admin.deliverymen.earnings') }}" class="btn btn-outline btn-sm">Clear</a>
                @endif
            </form>
        </div>

        {{-- Earnings Table --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-title"><div class="card-header-icon" style="background:rgba(16,185,129,0.1);color:#10B981;"><i class="fas fa-coins"></i></div> Delivery Earnings</div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>Order</th>
                            <th>Module</th>
                            <th>Type</th>
                            <th style="text-align:right;color:#6b7280;">Original Fee</th>
                            <th style="text-align:right;color:#ef4444;">Commission Deducted</th>
                            <th style="text-align:right;color:#10B981;">Driver Received</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($earnings as $e)
                        @php
                            $vEmoji = ['motorcycle'=>'🏍️','bajaj'=>'🛺','car'=>'🚗','van'=>'🚐','truck'=>'🚛','bicycle'=>'🚲'][$e->vehicle_type] ?? '🚗';
                            $originalFee = (float) ($e->original_delivery_fee ?? 0);
                            $deducted    = (float) ($e->commission_deducted ?? 0);
                        @endphp
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span>{{ $vEmoji }}</span>
                                    <div>
                                        <div style="font-weight:700;font-size:13px;">{{ $e->driver_name }}</div>
                                        <div style="font-size:11px;color:#8A8A9A;">{{ $e->driver_phone }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($e->order_number)
                                <a href="{{ route('admin.orders.show', $e->order_id) }}" style="font-weight:600;color:#FF8A00;">#{{ $e->order_number }}</a>
                                @else
                                <span style="color:#8A8A9A;">—</span>
                                @endif
                            </td>
                            <td>
                                @if($e->module_slug)
                                <span class="badge badge-info">{{ $e->module_slug }}</span>
                                @else
                                <span style="color:#8A8A9A;">—</span>
                                @endif
                            </td>
                            <td>
                                @php $tc = ['delivery_fee'=>'badge-success','bonus'=>'badge-warning','incentive'=>'badge-info','penalty'=>'badge-danger'][$e->type] ?? 'badge-secondary'; @endphp
                                <span class="badge {{ $tc }}">{{ ucfirst(str_replace('_',' ',$e->type)) }}</span>
                            </td>
                            <td style="text-align:right;color:#6b7280;font-size:13px;">
                                {{ $originalFee > 0 ? '$'.number_format($originalFee,2) : '—' }}
                            </td>
                            <td style="text-align:right;">
                                @if($deducted > 0)
                                <span style="font-weight:700;color:#ef4444;">-${{ number_format($deducted,2) }}</span>
                                @else
                                <span style="color:#d1d5db;">$0.00</span>
                                @endif
                            </td>
                            <td style="text-align:right;font-weight:800;color:#10B981;font-size:14px;">${{ number_format($e->amount, 2) }}</td>
                            <td style="font-size:12px;color:#8A8A9A;">{{ \Carbon\Carbon::parse($e->created_at)->format('d M Y, H:i') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="8" style="text-align:center;padding:30px;color:#8A8A9A;">No earnings found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($earnings->hasPages())
            <div style="padding:16px;display:flex;justify-content:center;">{{ $earnings->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Per Driver Summary --}}
    <div class="card">
        <div class="card-header">
            <div class="card-header-title"><div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:#FF8A00;"><i class="fas fa-trophy"></i></div> Top Drivers</div>
        </div>
        <div style="display:flex;flex-direction:column;">
            @forelse($perDriver as $i => $pd)
            @php $vEmoji = ['motorcycle'=>'🏍️','bajaj'=>'🛺','car'=>'🚗','van'=>'🚐','truck'=>'🚛','bicycle'=>'🚲'][$pd->vehicle_type] ?? '🚗'; @endphp
            <div style="padding:14px 16px;border-bottom:1px solid #f0f1f5;display:flex;align-items:center;gap:12px;">
                <div style="width:28px;height:28px;border-radius:50%;background:{{ $i < 3 ? '#FF8A00' : '#f0f1f5' }};color:{{ $i < 3 ? '#fff' : '#8A8A9A' }};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;">
                    {{ $i + 1 }}
                </div>
                <div style="flex:1;">
                    <div style="font-weight:700;font-size:13px;color:#07003B;">{{ $vEmoji }} {{ $pd->name }}</div>
                    <div style="font-size:11px;color:#8A8A9A;">{{ $pd->total_deliveries }} deliveries</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-weight:800;color:#10B981;font-size:14px;">${{ number_format($pd->total_earned, 2) }}</div>
                    @if(($pd->total_commission_deducted ?? 0) > 0)
                    <div style="font-size:10px;color:#ef4444;font-weight:600;">-${{ number_format($pd->total_commission_deducted, 2) }} fee</div>
                    @endif
                    <form action="{{ route('admin.deliverymen.reset-earning', $pd->deliveryman_id) }}" method="POST" style="margin:2px 0 0;" onsubmit="return confirm('Reset earnings for {{ $pd->name }}?')">
                        @csrf
                        <button style="background:none;border:none;color:#EF4444;font-size:10px;cursor:pointer;font-weight:600;padding:0;"><i class="fas fa-undo" style="font-size:9px;"></i> Reset</button>
                    </form>
                </div>
            </div>
            @empty
            <div style="padding:30px;text-align:center;color:#8A8A9A;">No data yet</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
