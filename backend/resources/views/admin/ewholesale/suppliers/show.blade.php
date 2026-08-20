@extends('admin.layouts.app')
@section('title', 'Supplier — ' . $supplier->display_name)

@section('content')
@include('admin.ewholesale._subnav')

<div style="padding:24px">
<div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
    <a href="{{ route('admin.module-data.wholesale.suppliers') }}" style="color:#6b7280;text-decoration:none;font-size:13px">← Suppliers</a>
    <h2 style="margin:0;font-size:20px;font-weight:700;color:#1B1444">{{ $supplier->display_name }}</h2>
    <span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;
        background:{{ $supplier->verification==='gold'?'#fef3c7':($supplier->isVerified()?'#d1fae5':'#f3f4f6') }};
        color:{{ $supplier->verification==='gold'?'#92400e':($supplier->isVerified()?'#065f46':'#6b7280') }}">
        {{ strtoupper($supplier->verification) }}
    </span>
</div>

@if(session('success'))<div style="background:#d1fae5;color:#065f46;padding:10px 16px;border-radius:6px;margin-bottom:16px">{{ session('success') }}</div>@endif
@if(session('error'))<div style="background:#fee2e2;color:#991b1b;padding:10px 16px;border-radius:6px;margin-bottom:16px">{{ session('error') }}</div>@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

{{-- Info card --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444;border-bottom:1px solid #f3f4f6;padding-bottom:10px">Profile</h3>
    @foreach([
        ['Vendor', $supplier->vendor?->name ?? '—'],
        ['About', $supplier->about ?? '—'],
        ['Warehouse', $supplier->warehouse_address ?? '—'],
        ['Rating', $supplier->rating ? '★ '.$supplier->rating : '—'],
        ['Response Rate', $supplier->response_rate ? $supplier->response_rate.'%' : '—'],
        ['Total Orders', number_format($supplier->total_orders)],
        ['Platform GMV', '$'.number_format($gmv,2)],
        ['Active', $supplier->is_active ? 'Yes' : 'No'],
    ] as [$k,$v])
    <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:13px;border-bottom:1px solid #f9fafb">
        <span style="color:#6b7280">{{ $k }}</span>
        <span style="color:#1B1444;font-weight:500">{{ $v }}</span>
    </div>
    @endforeach
</div>

{{-- Actions --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444;border-bottom:1px solid #f3f4f6;padding-bottom:10px">Actions</h3>
    <div style="display:flex;flex-direction:column;gap:10px">
        @foreach([
            ['verify',  '✅ Verify Supplier',  '#10b981'],
            ['gold',    '🥇 Set Gold Supplier', '#f59e0b'],
            ['suspend', '🚫 Suspend',           '#ef4444'],
        ] as [$act,$label,$color])
        <form method="POST" action="{{ route('admin.module-data.wholesale.suppliers.action', $supplier) }}">
            @csrf <input type="hidden" name="action" value="{{ $act }}">
            <button style="width:100%;padding:10px;background:{{ $color }};color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;text-align:left">{{ $label }}</button>
        </form>
        @endforeach
    </div>
</div>
</div>

{{-- Products --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-bottom:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444">Products ({{ $supplier->products->count() }})</h3>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead><tr style="border-bottom:2px solid #f3f4f6">
            <th style="padding:8px;text-align:left;color:#6b7280;font-weight:500">Name</th>
            <th style="padding:8px;text-align:left;color:#6b7280;font-weight:500">Category</th>
            <th style="padding:8px;text-align:right;color:#6b7280;font-weight:500">Price Range</th>
            <th style="padding:8px;text-align:center;color:#6b7280;font-weight:500">Status</th>
        </tr></thead>
        <tbody>
        @foreach($supplier->products as $p)
        <tr style="border-bottom:1px solid #f9fafb">
            <td style="padding:8px"><a href="{{ route('admin.module-data.wholesale.products.show', $p) }}" style="color:#1B1444;font-weight:500;text-decoration:none">{{ $p->name }}</a></td>
            <td style="padding:8px;color:#6b7280">{{ $p->category?->name }}</td>
            <td style="padding:8px;text-align:right;color:#10b981">${{ $p->min_price }} – ${{ $p->max_price }}</td>
            <td style="padding:8px;text-align:center">
                <span style="padding:2px 8px;border-radius:10px;font-size:11px;
                    background:{{ $p->status==='active'?'#d1fae5':($p->status==='pending_review'?'#fffbeb':'#f3f4f6') }};
                    color:{{ $p->status==='active'?'#065f46':($p->status==='pending_review'?'#92400e':'#6b7280') }}">
                    {{ $p->status }}
                </span>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>

{{-- Recent Orders --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-bottom:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444">Recent Orders</h3>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead><tr style="border-bottom:2px solid #f3f4f6">
            <th style="padding:8px;text-align:left;color:#6b7280;font-weight:500">Order #</th>
            <th style="padding:8px;text-align:left;color:#6b7280;font-weight:500">Buyer</th>
            <th style="padding:8px;text-align:right;color:#6b7280;font-weight:500">Total</th>
            <th style="padding:8px;text-align:center;color:#6b7280;font-weight:500">Status</th>
        </tr></thead>
        <tbody>
        @forelse($orders as $o)
        <tr style="border-bottom:1px solid #f9fafb">
            <td style="padding:8px"><a href="{{ route('admin.module-data.wholesale.orders.show', $o) }}" style="color:#1B1444;text-decoration:none;font-family:monospace">{{ $o->order_no }}</a></td>
            <td style="padding:8px;color:#6b7280">{{ $o->buyer?->user?->name ?? '—' }}</td>
            <td style="padding:8px;text-align:right;font-weight:600">${{ number_format($o->total,2) }}</td>
            <td style="padding:8px;text-align:center"><span style="font-size:11px;padding:2px 8px;border-radius:10px;background:#f3f4f6;color:#374151">{{ $o->status }}</span></td>
        </tr>
        @empty
        <tr><td colspan="4" style="padding:16px;text-align:center;color:#9ca3af">No orders yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{-- Shipping rules editor --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-bottom:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444">Shipping Rules</h3>
    <form method="POST" action="{{ route('admin.module-data.wholesale.suppliers.shipping', $supplier) }}" id="shippingForm">
        @csrf
        <div id="rulesList">
            @forelse($supplier->shippingRules as $idx => $rule)
            @include('admin.ewholesale.suppliers._shipping_rule_row', ['rule'=>$rule, 'idx'=>$idx])
            @empty
            @include('admin.ewholesale.suppliers._shipping_rule_row', ['rule'=>null, 'idx'=>0])
            @endforelse
        </div>
        <button type="button" onclick="addRule()" style="padding:6px 14px;background:#f3f4f6;color:#374151;border:1px solid #d1d5db;border-radius:5px;font-size:13px;cursor:pointer;margin-top:8px">+ Add Rule</button>
        <button type="submit" style="padding:6px 16px;background:#F7941D;color:#fff;border:none;border-radius:5px;font-size:13px;cursor:pointer;margin-top:8px;margin-left:8px">Save Rules</button>
    </form>
    <script>
    let ruleIdx = {{ $supplier->shippingRules->count() }};
    function addRule() {
        fetch('/admin/module-data/wholesale/suppliers/{{ $supplier->id }}/shipping?_method=GET')
        .catch();
        const tpl = document.getElementById('ruleTemplate').innerHTML.replace(/\{IDX\}/g, ruleIdx++);
        document.getElementById('rulesList').insertAdjacentHTML('beforeend', tpl);
    }
    </script>
    <template id="ruleTemplate">
        @include('admin.ewholesale.suppliers._shipping_rule_row', ['rule'=>null, 'idx'=>'{IDX}'])
    </template>
</div>

{{-- Activity log --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:14px;font-weight:600;color:#1B1444">Activity Log</h3>
    @forelse($logs as $log)
    <div style="display:flex;gap:12px;padding:8px 0;border-bottom:1px solid #f9fafb;font-size:12px">
        <span style="color:#9ca3af;white-space:nowrap">{{ $log->created_at->format('M d H:i') }}</span>
        <span style="color:#6b7280;font-weight:500">{{ $log->action }}</span>
        @if($log->after) <span style="color:#374151">{{ json_encode($log->after) }}</span> @endif
    </div>
    @empty
    <p style="color:#9ca3af;font-size:13px;margin:0">No activity recorded.</p>
    @endforelse
</div>

{{-- Phase 5: Supplier Scorecard --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin-top:20px">
    <h3 style="margin:0 0 16px;font-size:15px;font-weight:700;color:#1B1444">Performance Scorecard</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px" id="scorecardGrid">
        <div style="text-align:center;padding:12px;background:#f9fafb;border-radius:8px">
            <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em">Rating</div>
            <div style="font-size:24px;font-weight:800;color:#f59e0b;margin-top:4px">{{ $supplier->rating ?? '—' }}★</div>
        </div>
        @foreach([
            ['Response Rate',       ($supplier->response_rate ?? 0).'%',         $supplier->response_rate >= 80 ? '#10b981' : '#ef4444'],
            ['Avg Response',        ($supplier->response_time_avg ?? 0).' min',  '#6b7280'],
            ['On-time Delivery',    ($supplier->on_time_delivery_rate ?? 0).'%',  ($supplier->on_time_delivery_rate ?? 0) >= 90 ? '#10b981' : '#f59e0b'],
            ['Dispute Rate',        ($supplier->dispute_rate ?? 0).'%',           ($supplier->dispute_rate ?? 0) <= 2 ? '#10b981' : '#ef4444'],
            ['Cancellation Rate',   ($supplier->cancellation_rate ?? 0).'%',      ($supplier->cancellation_rate ?? 0) <= 5 ? '#10b981' : '#ef4444'],
        ] as [$label,$val,$color])
        <div style="text-align:center;padding:12px;background:#f9fafb;border-radius:8px">
            <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em">{{ $label }}</div>
            <div style="font-size:20px;font-weight:800;color:{{ $color }};margin-top:4px">{{ $val }}</div>
        </div>
        @endforeach
    </div>
    <p style="margin:10px 0 0;font-size:11px;color:#9ca3af">Updated nightly by <code>ewholesale:supplier-stats</code> command.</p>
</div>

</div>
@endsection
