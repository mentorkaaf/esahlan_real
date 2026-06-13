@extends('admin.layouts.app')
@section('title', 'Orders')
@section('content')

<style>
.bulk-bar {
    display: none; align-items: center; gap: 12px; flex-wrap: wrap;
    background: var(--navy); color: #fff;
    padding: 10px 20px; border-radius: 10px; margin-bottom: 16px;
    box-shadow: 0 4px 16px rgba(7,0,59,0.25);
    position: sticky; top: 72px; z-index: 50;
    animation: slideDown .2s ease;
}
.bulk-bar.visible { display: flex; }
@keyframes slideDown { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:none; } }
.bulk-count { font-weight: 700; font-size: 13px; white-space: nowrap; }
.bulk-count span { background: var(--brand); color: #fff; padding: 2px 8px; border-radius: 20px; margin-right: 4px; }
.bulk-sep { width: 1px; height: 22px; background: rgba(255,255,255,0.15); }
.bulk-status-sel {
    padding: 7px 12px; border-radius: 8px; border: 1.5px solid rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.08); color: #fff; font-size: 13px;
    outline: none; cursor: pointer; font-family: inherit;
}
.bulk-status-sel option { background: var(--navy); color: #fff; }
.btn-bulk-apply {
    padding: 7px 14px; border-radius: 8px; border: none;
    background: var(--brand); color: #fff; font-size: 13px; font-weight: 700;
    cursor: pointer; display: flex; align-items: center; gap: 6px;
    transition: background .15s;
}
.btn-bulk-apply:hover { background: var(--brand-dark); }
.btn-bulk-delete {
    padding: 7px 14px; border-radius: 8px; border: 1.5px solid rgba(239,68,68,0.5);
    background: rgba(239,68,68,0.12); color: #fca5a5; font-size: 13px; font-weight: 700;
    cursor: pointer; display: flex; align-items: center; gap: 6px;
    transition: all .15s;
}
.btn-bulk-delete:hover { background: rgba(239,68,68,0.25); border-color: rgba(239,68,68,0.8); }
.btn-bulk-cancel {
    margin-left: auto; padding: 5px 12px; border-radius: 8px;
    border: 1.5px solid rgba(255,255,255,0.15); background: transparent;
    color: rgba(255,255,255,0.5); font-size: 12px; cursor: pointer;
    transition: all .15s;
}
.btn-bulk-cancel:hover { color: #fff; border-color: rgba(255,255,255,0.4); }

/* Row checkbox styling */
.row-cb { width: 16px; height: 16px; cursor: pointer; accent-color: var(--brand); }
th.cb-col, td.cb-col { width: 44px; padding-left: 16px !important; }
tbody tr.selected td { background: rgba(255,138,0,0.04); }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">Orders</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li>Orders</li>
        </ul>
    </div>
    <div style="font-size:13px;color:var(--text-muted);">
        <i class="fas fa-sync-alt" style="color:var(--brand);margin-right:6px;"></i>
        Updated {{ \Carbon\Carbon::now(\App\Helpers\AppSettings::timezone())->format('H:i') }}
    </div>
</div>

{{-- Bulk Action Bar --}}
<div class="bulk-bar" id="bulkBar">
    <div class="bulk-count"><span id="bulkCount">0</span> selected</div>
    <div class="bulk-sep"></div>
    <select class="bulk-status-sel" id="bulkStatusSel">
        <option value="">— Change status to —</option>
        <option value="pending">Pending</option>
        <option value="confirmed">Confirmed</option>
        <option value="preparing">Preparing</option>
        <option value="ready_for_pickup">Ready for Pickup</option>
        <option value="out_for_delivery">Out for Delivery</option>
        <option value="delivered">Delivered</option>
        <option value="cancelled">Cancelled</option>
    </select>
    <button class="btn-bulk-apply" id="bulkApplyBtn" onclick="bulkApply()">
        <i class="fas fa-check"></i> Apply
    </button>
    <div class="bulk-sep"></div>
    <button class="btn-bulk-delete" onclick="bulkDelete()">
        <i class="fas fa-trash"></i> Delete
    </button>
    <button class="btn-bulk-cancel" onclick="clearSelection()">
        <i class="fas fa-times"></i> Cancel
    </button>
</div>

{{-- Status Tabs --}}
@php
$statuses = [
    ''          => ['All', 'secondary'],
    'pending'   => ['Pending', 'warning'],
    'confirmed' => ['Confirmed', 'info'],
    'preparing' => ['Preparing', 'info'],
    'ready'     => ['Ready', 'teal'],
    'picked_up' => ['Picked Up', 'purple'],
    'delivered' => ['Delivered', 'success'],
    'cancelled' => ['Cancelled', 'danger'],
];
@endphp
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px;">
    @foreach($statuses as $st => [$label, $color])
    <a href="{{ route('admin.orders.index', ['status' => $st] + request()->except('status','page')) }}"
       style="padding:7px 16px;border-radius:20px;font-size:12.5px;font-weight:600;text-decoration:none;border:1.5px solid;transition:all .15s;
              {{ request('status') === $st
                 ? 'background:var(--brand);color:#fff;border-color:var(--brand);'
                 : 'background:#fff;color:var(--text-muted);border-color:var(--border);' }}">
        {{ $label }}
    </a>
    @endforeach
</div>

{{-- Filter Bar --}}
<div class="card">
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;width:100%;">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <div style="flex:1;min-width:200px;">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search order number, customer…" value="{{ request('search') }}">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" style="width:160px;">
            <span style="color:var(--text-muted);font-size:12px;">to</span>
            <input type="date" name="date_to"   class="form-control" value="{{ request('date_to') }}"   style="width:160px;">
            @if(request()->hasAny(['search','date_from','date_to']))
            <a href="{{ route('admin.orders.index',['status'=>request('status')]) }}" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
            @endif
        </form>
    </div>
</div>

{{-- Hidden bulk form --}}
<form id="bulkForm" method="POST" action="{{ route('admin.orders.bulk') }}">
    @csrf
    <input type="hidden" name="action" id="bulkAction">
    <input type="hidden" name="status" id="bulkStatusInput">
</form>

{{-- Orders Table --}}
<div class="card">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(255,138,0,0.1);color:var(--brand);">
                <i class="fas fa-shopping-bag"></i>
            </div>
            Orders
            <span class="badge badge-orange" style="margin-left:4px;">{{ $orders->total() }}</span>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th class="cb-col">
                        <input type="checkbox" class="row-cb" id="selectAll" title="Select all">
                    </th>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Module / Vendor</th>
                    <th>Deliveryman</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                @php
                    $smap = [
                        'pending'         => 'badge-warning',
                        'confirmed'       => 'badge-info',
                        'preparing'       => 'badge-info',
                        'ready'           => 'badge-teal',
                        'ready_for_pickup'=> 'badge-teal',
                        'picked_up'       => 'badge-purple',
                        'out_for_delivery'=> 'badge-purple',
                        'delivered'       => 'badge-success',
                        'cancelled'       => 'badge-danger',
                        'failed'          => 'badge-danger',
                    ];
                    $bc = $smap[$order->status] ?? 'badge-secondary';
                    $total = $order->total_amount ?? $order->total ?? 0;
                @endphp
                <tr data-id="{{ $order->id }}">
                    <td class="cb-col">
                        <input type="checkbox" class="row-cb order-cb" value="{{ $order->id }}">
                    </td>
                    <td>
                        <a href="{{ route('admin.orders.show',$order) }}" style="font-weight:700;color:var(--navy);text-decoration:none;font-size:13px;">
                            {{ $order->order_number }}
                        </a>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="avatar avatar-sm avatar-orange">{{ strtoupper(substr($order->user?->name ?? 'U',0,1)) }}</div>
                            <div>
                                <div style="font-weight:600;font-size:13px;line-height:1.2;">{{ $order->user?->name ?? '—' }}</div>
                                <div style="font-size:11px;color:var(--text-muted);">{{ $order->user?->phone ?? '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($order->module_slug)
                            <span class="badge badge-dark" style="margin-bottom:3px;">{{ strtoupper($order->module_slug) }}</span><br>
                        @endif
                        <span style="font-size:12px;color:var(--text-muted);">{{ $order->vendor?->name ?? '' }}</span>
                    </td>
                    <td>
                        @if($order->deliveryman?->user?->name)
                            <div style="display:flex;align-items:center;gap:7px;">
                                <div class="avatar avatar-sm avatar-green">{{ strtoupper(substr($order->deliveryman->user->name,0,1)) }}</div>
                                <span style="font-size:12.5px;font-weight:600;">{{ $order->deliveryman->user->name }}</span>
                            </div>
                        @else
                            <span class="badge badge-secondary">Unassigned</span>
                        @endif
                    </td>
                    <td><span style="font-weight:700;color:var(--text);">${{ number_format($total,2) }}</span></td>
                    <td>
                        @php $ps = $order->payment_status ?? 'pending'; @endphp
                        <span class="badge {{ $ps==='paid'?'badge-success':($ps==='refunded'?'badge-info':'badge-warning') }}">
                            {{ ucfirst($ps) }}
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ $bc }} badge-dot">
                            {{ ucwords(str_replace('_',' ',$order->status)) }}
                        </span>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
                        {{ $order->created_at->setTimezone(\App\Helpers\AppSettings::timezone())->format('d M') }}<br>
                        <span style="font-size:11px;">{{ $order->created_at->setTimezone(\App\Helpers\AppSettings::timezone())->format('H:i') }}</span>
                    </td>
                    <td>
                        <a href="{{ route('admin.orders.show',$order) }}" class="btn btn-outline btn-xs">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <i class="fas fa-shopping-bag"></i>
                            <h3>No orders found</h3>
                            <p>Try adjusting your filters</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
    <div class="card-footer" style="display:flex;justify-content:center;">
        {{ $orders->withQueryString()->links() }}
    </div>
    @endif
</div>

{{-- Exchange Orders Section --}}
@if(isset($exchangeOrders) && $exchangeOrders->count())
<div class="card" style="margin-top:24px;">
    <div class="card-header">
        <div class="card-header-title">
            <div class="card-header-icon" style="background:rgba(59,130,246,0.1);color:#3b82f6;">
                <i class="fas fa-exchange-alt"></i>
            </div>
            Exchange Orders
            <span class="badge badge-info" style="margin-left:4px;">{{ $exchangeOrders->count() }}</span>
        </div>
        <a href="{{ route('admin.exchange.index') }}" class="btn btn-outline btn-sm">
            View All <i class="fas fa-arrow-right" style="margin-left:4px;"></i>
        </a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Exchange</th>
                    <th>Sent</th>
                    <th>Fee</th>
                    <th>Received</th>
                    <th>Recipient Phone</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($exchangeOrders as $ex)
                @php
                    $exStatus = [
                        'pending'    => 'badge-warning',
                        'processing' => 'badge-info',
                        'completed'  => 'badge-success',
                        'failed'     => 'badge-danger',
                    ][$ex->status] ?? 'badge-secondary';
                    $walletColors = ['EVC'=>'#e74c3c','EDAHAB'=>'#27ae60','JEEP'=>'#2980b9','PREMIER'=>'#8e44ad'];
                    $fc = $walletColors[$ex->from_wallet] ?? '#64748b';
                    $tc = $walletColors[$ex->to_wallet]   ?? '#64748b';
                @endphp
                <tr>
                    <td style="font-weight:700;font-size:13px;font-family:monospace;color:var(--navy);">
                        {{ $ex->reference }}
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="avatar avatar-sm avatar-orange">{{ strtoupper(substr($ex->user_name,0,1)) }}</div>
                            <div>
                                <div style="font-weight:600;font-size:13px;">{{ $ex->user_name }}</div>
                                <div style="font-size:11px;color:var(--text-muted);">{{ $ex->user_phone }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span style="background:{{ $fc }}18;color:{{ $fc }};border:1px solid {{ $fc }}30;border-radius:6px;padding:3px 8px;font-size:11px;font-weight:700;">{{ $ex->from_wallet }}</span>
                            <i class="fas fa-arrow-right" style="color:var(--text-muted);font-size:10px;"></i>
                            <span style="background:{{ $tc }}18;color:{{ $tc }};border:1px solid {{ $tc }}30;border-radius:6px;padding:3px 8px;font-size:11px;font-weight:700;">{{ $ex->to_wallet }}</span>
                        </div>
                    </td>
                    <td style="font-weight:700;">${{ number_format($ex->sent_amount,2) }}</td>
                    <td style="color:#ef4444;font-weight:600;">-${{ number_format($ex->fee_amount,2) }}</td>
                    <td style="color:#10b981;font-weight:700;">${{ number_format($ex->converted_amount,2) }}</td>
                    <td>
                        <span style="display:flex;align-items:center;gap:5px;font-size:12.5px;font-weight:600;">
                            <i class="fas fa-phone" style="color:var(--text-muted);font-size:10px;"></i>
                            {{ $ex->recipient_phone }}
                        </span>
                    </td>
                    <td><span class="badge {{ $exStatus }}">{{ ucfirst($ex->status) }}</span></td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
                        {{ \Carbon\Carbon::parse($ex->created_at)->format('d M') }}<br>
                        <span style="font-size:11px;">{{ \Carbon\Carbon::parse($ex->created_at)->format('H:i') }}</span>
                    </td>
                    <td>
                        <a href="{{ route('admin.exchange.show', $ex->id) }}" class="btn btn-outline btn-xs">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@push('scripts')
<script>
const selectAll = document.getElementById('selectAll');
const bulkBar   = document.getElementById('bulkBar');
const bulkCount = document.getElementById('bulkCount');

function getChecked() {
    return [...document.querySelectorAll('.order-cb:checked')];
}

function updateBar() {
    const checked = getChecked();
    bulkCount.textContent = checked.length;
    bulkBar.classList.toggle('visible', checked.length > 0);
    // highlight rows
    document.querySelectorAll('.order-cb').forEach(cb => {
        cb.closest('tr').classList.toggle('selected', cb.checked);
    });
    // sync select-all state
    const all = document.querySelectorAll('.order-cb');
    selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
    selectAll.checked = checked.length === all.length && all.length > 0;
}

selectAll.addEventListener('change', () => {
    document.querySelectorAll('.order-cb').forEach(cb => cb.checked = selectAll.checked);
    updateBar();
});

document.querySelectorAll('.order-cb').forEach(cb => {
    cb.addEventListener('change', updateBar);
});

function clearSelection() {
    document.querySelectorAll('.order-cb').forEach(cb => cb.checked = false);
    selectAll.checked = false;
    updateBar();
}

function getIds() {
    return getChecked().map(cb => cb.value);
}

function bulkApply() {
    const status = document.getElementById('bulkStatusSel').value;
    if (!status) { alert('Please select a status to apply.'); return; }
    const ids = getIds();
    if (!ids.length) return;
    if (!confirm(`Update ${ids.length} order(s) to "${status}"?`)) return;
    submitBulk('status', status, ids);
}

function bulkDelete() {
    const ids = getIds();
    if (!ids.length) return;
    if (!confirm(`Permanently delete ${ids.length} order(s)? This cannot be undone.`)) return;
    submitBulk('delete', '', ids);
}

function submitBulk(action, status, ids) {
    const form = document.getElementById('bulkForm');
    document.getElementById('bulkAction').value = action;
    document.getElementById('bulkStatusInput').value = status;
    // Remove old id inputs
    form.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
    ids.forEach(id => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
        form.appendChild(inp);
    });
    form.submit();
}
</script>
@endpush
@endsection
