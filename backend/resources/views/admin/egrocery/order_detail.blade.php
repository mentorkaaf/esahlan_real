@extends('admin.layouts.app')
@section('title', 'Order '.$order->order_no)
@section('content')
@php
$statusColors = [
    'pending'          => ['badge-warning',  '🕐 Pending'],
    'confirmed'        => ['badge-info',      '✅ Confirmed'],
    'picking'          => ['badge-purple',    '🛒 Picking'],
    'ready'            => ['badge-teal',      '📦 Ready'],
    'out_for_delivery' => ['badge-orange',    '🚚 Delivering'],
    'delivered'        => ['badge-success',   '🎉 Delivered'],
    'cancelled'        => ['badge-danger',    '❌ Cancelled'],
];
[$badgeCls, $statusLabel] = $statusColors[$order->status] ?? ['badge-secondary', $order->status];
@endphp
<div class="page-header">
    <div>
        <h2 class="page-title">Order {{ $order->order_no }}</h2>
        <ol class="breadcrumb"><li><a href="{{ route('admin.module-data.egrocery.orders') }}">Orders</a></li><li>{{ $order->order_no }}</li></ol>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <span class="badge {{ $badgeCls }}" style="font-size:13px;padding:6px 14px;">{{ $statusLabel }}</span>
        <a href="{{ route('admin.module-data.egrocery.order.print', $order->id) }}" target="_blank" class="btn" style="background:#f1f5f9;color:#374151;"><i class="fas fa-print"></i> Print Slip</a>
    </div>
</div>

@include('admin.egrocery._subnav')

@if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger mb-4">{{ session('error') }}</div>@endif

<div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;align-items:start;">
    {{-- Left: Items + Actions --}}
    <div style="display:flex;flex-direction:column;gap:18px;">

        {{-- Order Items --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-title"><i class="fas fa-boxes-stacked" style="color:#FF8A00"></i> Items ({{ $order->items->count() }})</div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Variant</th>
                            <th>Qty Ordered</th>
                            <th>Picked Qty</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                            <th>Substitution</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                        <tr>
                            <td style="font-weight:600;">{{ $item->name_snapshot ?? $item->variant?->product?->name }}</td>
                            <td style="color:#64748b;">{{ $item->unit_label_snapshot ?? $item->variant?->label }}</td>
                            <td style="text-align:center;font-weight:700;">{{ $item->qty }}</td>
                            <td style="text-align:center;">
                                @if(in_array($order->status, ['picking','ready','out_for_delivery','delivered']))
                                <form method="POST" action="{{ route('admin.module-data.egrocery.order.picked', $order->id) }}" style="display:flex;align-items:center;gap:6px;">
                                    @csrf
                                    <input type="hidden" name="item_id" value="{{ $item->id }}">
                                    <input type="number" name="picked_qty" value="{{ $item->picked_qty ?? $item->qty }}" style="width:60px;padding:4px 8px;border:1px solid #e8edf5;border-radius:6px;font-size:13px;" min="0" step="0.001">
                                    <button type="submit" style="background:#f0fdf4;color:#16a34a;border:none;cursor:pointer;padding:4px 8px;border-radius:6px;font-size:11px;">✓</button>
                                </form>
                                @else
                                <span style="color:#9ca3af;">{{ $item->picked_qty ?? '—' }}</span>
                                @endif
                            </td>
                            <td>${{ number_format($item->unit_price_snapshot, 2) }}</td>
                            <td style="font-weight:700;">${{ number_format($item->line_total, 2) }}</td>
                            <td>
                                @if($item->substitution_status && $item->substitution_status !== 'none')
                                <span class="badge {{ $item->substitution_status === 'proposed' ? 'badge-warning' : ($item->substitution_status === 'accepted' ? 'badge-success' : 'badge-danger') }}">
                                    {{ ucfirst($item->substitution_status) }}{{ $item->substitutionVariant ? ': '.$item->substitutionVariant->label : '' }}
                                </span>
                                @elseif(in_array($order->status, ['picking','confirmed']))
                                <button onclick="proposeSub({{ $item->id }})" class="btn btn-sm" style="background:#fef3c7;color:#d97706;font-size:11px;"><i class="fas fa-rotate"></i> Propose Sub</button>
                                @else
                                <span style="color:#d1d5db;">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background:#fafbff;">
                            <td colspan="5" style="padding:12px 16px;text-align:right;font-size:13px;color:#64748b;">Subtotal</td>
                            <td style="padding:12px 16px;font-weight:700;">${{ number_format($order->subtotal, 2) }}</td>
                            <td></td>
                        </tr>
                        @if($order->discount > 0)
                        <tr>
                            <td colspan="5" style="padding:6px 16px;text-align:right;font-size:13px;color:#64748b;">Discount</td>
                            <td style="padding:6px 16px;color:#10b981;font-weight:700;">-${{ number_format($order->discount, 2) }}</td>
                            <td></td>
                        </tr>
                        @endif
                        <tr>
                            <td colspan="5" style="padding:6px 16px;text-align:right;font-size:13px;color:#64748b;">Delivery Fee</td>
                            <td style="padding:6px 16px;font-weight:700;">${{ number_format($order->delivery_fee, 2) }}</td>
                            <td></td>
                        </tr>
                        <tr style="background:#fffbeb;">
                            <td colspan="5" style="padding:12px 16px;text-align:right;font-weight:800;font-size:15px;">TOTAL</td>
                            <td style="padding:12px 16px;font-weight:900;font-size:15px;color:#111827;">${{ number_format($order->total, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Status Actions --}}
        @if($transitions)
        <div class="card">
            <div class="card-header"><div class="card-header-title"><i class="fas fa-circle-arrow-right" style="color:#10b981"></i> Status Actions</div></div>
            <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;">
                @foreach($transitions as $nextStatus)
                @php
                    $btnStyles = ['confirmed'=>'background:#eff6ff;color:#2563eb','picking'=>'background:#f5f3ff;color:#7c3aed',
                                  'ready'=>'background:#f0fdfa;color:#0d9488','out_for_delivery'=>'background:#fff8f0;color:#c05800',
                                  'delivered'=>'background:#f0fdf4;color:#16a34a','cancelled'=>'background:#fef2f2;color:#dc2626'];
                    $btnLabels = ['confirmed'=>'✅ Confirm Order','picking'=>'🛒 Start Picking','ready'=>'📦 Mark Ready',
                                  'out_for_delivery'=>'🚚 Send for Delivery','delivered'=>'🎉 Mark Delivered','cancelled'=>'❌ Cancel Order'];
                @endphp
                <form method="POST" action="{{ route('admin.module-data.egrocery.order.status', $order->id) }}"
                      onsubmit="return '{{ $nextStatus }}' !== 'cancelled' || confirm('Cancel this order?')">
                    @csrf
                    <input type="hidden" name="status" value="{{ $nextStatus }}">
                    <button type="submit" style="{{ $btnStyles[$nextStatus] ?? 'background:#f1f5f9;color:#374151' }};border:1.5px solid #e8edf5;border-radius:10px;padding:10px 18px;font-size:13px;font-weight:700;cursor:pointer;">
                        {{ $btnLabels[$nextStatus] ?? ucfirst($nextStatus) }}
                    </button>
                </form>
                @endforeach
            </div>
        </div>
        @endif

    </div>

    {{-- Right: Customer + Driver + Notes --}}
    <div style="display:flex;flex-direction:column;gap:18px;">

        {{-- Customer --}}
        <div class="card">
            <div class="card-header"><div class="card-header-title"><i class="fas fa-user" style="color:#3b82f6"></i> Customer</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
                <div>
                    <div style="font-weight:700;font-size:15px;">{{ $order->user?->name }}</div>
                    <div style="font-size:13px;color:#64748b;margin-top:3px;"><i class="fas fa-phone" style="width:14px;"></i> {{ $order->user?->phone }}</div>
                    <div style="font-size:13px;color:#64748b;margin-top:2px;"><i class="fas fa-envelope" style="width:14px;"></i> {{ $order->user?->email ?? '—' }}</div>
                </div>
                @if($order->customer_note)
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 12px;font-size:13px;">
                    <div style="font-size:11px;font-weight:700;color:#d97706;margin-bottom:4px;"><i class="fas fa-note-sticky"></i> Customer Note</div>
                    {{ $order->customer_note }}
                </div>
                @endif
            </div>
        </div>

        {{-- Order Meta --}}
        <div class="card">
            <div class="card-header"><div class="card-header-title"><i class="fas fa-info-circle" style="color:#64748b"></i> Order Info</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px;font-size:13px;">
                <div style="display:flex;justify-content:space-between;"><span style="color:#9ca3af;">Payment</span><span style="font-weight:600;">{{ strtoupper($order->payment_method ?? '—') }}</span></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#9ca3af;">Payment Status</span>
                    <span class="badge {{ $order->payment_status === 'paid' ? 'badge-success' : 'badge-warning' }}">{{ ucfirst($order->payment_status ?? 'pending') }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#9ca3af;">Delivery Slot</span><span style="font-weight:600;">{{ $order->deliverySlot ? $order->deliverySlot->label ?? $order->deliverySlot->start_time : '—' }}</span></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#9ca3af;">Substitution Pref</span><span style="font-weight:600;">{{ ucfirst($order->substitution_pref ?? 'contact') }}</span></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#9ca3af;">Confirmed At</span><span style="font-weight:600;">{{ $order->confirmed_at ? $order->confirmed_at->format('M j H:i') : '—' }}</span></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#9ca3af;">Delivered At</span><span style="font-weight:600;">{{ $order->delivered_at ? $order->delivered_at->format('M j H:i') : '—' }}</span></div>
                @if($order->cancelled_reason)
                <div style="margin-top:6px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:8px 12px;color:#dc2626;font-size:12px;"><i class="fas fa-ban"></i> {{ $order->cancelled_reason }}</div>
                @endif
            </div>
        </div>

        {{-- Driver Assignment --}}
        @if(in_array($order->status, ['confirmed','picking','ready','out_for_delivery']))
        <div class="card">
            <div class="card-header"><div class="card-header-title"><i class="fas fa-truck" style="color:#f59e0b"></i> Driver Assignment</div></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.module-data.egrocery.order.driver', $order->id) }}" style="display:flex;flex-direction:column;gap:10px;">
                    @csrf
                    <select name="driver_id" class="form-control" required>
                        <option value="">— Select Driver —</option>
                        @foreach($drivers as $d)
                        <option value="{{ $d->id }}" {{ $order->driver_id === $d->id ? 'selected' : '' }}>{{ $d->name }} ({{ $d->phone }})</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn" style="background:#fff8f0;color:#c05800;border:1.5px solid #fed7aa;">
                        <i class="fas fa-user-check"></i> {{ $order->driver_id ? 'Reassign Driver' : 'Assign Driver' }}
                    </button>
                </form>
            </div>
        </div>
        @endif

    </div>
</div>

{{-- Substitution Proposal Modal --}}
<div id="subModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:440px;box-shadow:0 25px 60px rgba(0,0,0,.2);">
        <div style="padding:18px 20px;border-bottom:1px solid #f1f5f9;font-weight:800;display:flex;justify-content:space-between;align-items:center;">
            <span><i class="fas fa-rotate" style="color:#d97706;margin-right:8px;"></i>Propose Substitution</span>
            <button onclick="document.getElementById('subModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:18px;color:#9ca3af;">&times;</button>
        </div>
        <form id="subForm" method="POST" action="{{ route('admin.module-data.egrocery.order.substitute', $order->id) }}" style="padding:20px;display:flex;flex-direction:column;gap:14px;">
            @csrf
            <input type="hidden" name="item_id" id="subItemId">
            <div>
                <label class="form-label">Search Replacement Variant</label>
                <input type="text" id="subSearch" class="form-control" placeholder="Type product name..." autocomplete="off">
                <div id="subResults" style="background:#fff;border:1px solid #e8edf5;border-radius:8px;margin-top:4px;display:none;max-height:180px;overflow-y:auto;box-shadow:0 8px 20px rgba(0,0,0,.1);"></div>
                <input type="hidden" name="variant_id" id="subVarId" required>
                <div id="subSelected" style="margin-top:8px;font-size:13px;color:#64748b;display:none;background:#f1f5f9;padding:8px 12px;border-radius:8px;"></div>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">Propose & Notify Customer</button>
                <button type="button" onclick="document.getElementById('subModal').style.display='none'" class="btn" style="background:#f1f5f9;color:#374151;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
const csrfToken    = document.querySelector('meta[name="csrf-token"]').content;
const varSearchUrl = '{{ route("admin.module-data.egrocery.inventory.variant-search") }}';
let subTimer;

function proposeSub(itemId) {
    document.getElementById('subItemId').value = itemId;
    document.getElementById('subVarId').value  = '';
    document.getElementById('subSearch').value = '';
    document.getElementById('subSelected').style.display = 'none';
    document.getElementById('subResults').style.display  = 'none';
    document.getElementById('subModal').style.display = 'flex';
}

document.getElementById('subSearch').addEventListener('input', function() {
    clearTimeout(subTimer);
    const q = this.value.trim();
    if (q.length < 2) { document.getElementById('subResults').style.display='none'; return; }
    subTimer = setTimeout(() => {
        fetch(varSearchUrl + '?q=' + encodeURIComponent(q), { headers:{'Accept':'application/json'} })
            .then(r=>r.json()).then(data => {
                const box = document.getElementById('subResults');
                if (!data.length) { box.style.display='none'; return; }
                box.innerHTML = data.map(v =>
                    `<div onclick="selectSubVariant(${v.id},'${v.label.replace(/'/g,"\\'")}',${v.stock})"
                         style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;font-size:13px;"
                         onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background=''"
                    >${v.label} <span style="float:right;color:#9ca3af;">Stock: ${v.stock}</span></div>`
                ).join('');
                box.style.display='block';
            });
    }, 300);
});

function selectSubVariant(id, label, stock) {
    document.getElementById('subVarId').value = id;
    document.getElementById('subSearch').value = '';
    document.getElementById('subResults').style.display = 'none';
    const sel = document.getElementById('subSelected');
    sel.textContent = '✓ ' + label + ' (stock: ' + stock + ')';
    sel.style.display = 'block';
}
</script>
@endsection
