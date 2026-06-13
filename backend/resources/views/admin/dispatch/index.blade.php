@extends('admin.layouts.app')
@section('title', 'Dispatch Center')
@push('styles')
<style>
    #dispatch-map { height: 500px; border-radius: 12px; }
    .order-card { cursor: pointer; transition: all 0.2s; border-left: 4px solid transparent; }
    .order-card:hover { border-left-color: var(--primary); background: #fff9f0; }
    .order-card.selected { border-left-color: var(--primary); background: #fff3e0; }
    .dm-badge { background: #e8f5e9; color: #2e7d32; border-radius: 20px; padding: 2px 10px; font-size: 0.8rem; }
    .orders-panel { height: 500px; overflow-y: auto; }
</style>
@endpush
@section('content')
<div class="page-header">
    <h2 class="page-title"><i class="fas fa-broadcast-tower text-primary me-2"></i>Dispatch Center</h2>
    <div class="d-flex gap-2">
        <span class="badge bg-success" id="active-count">Loading...</span>
        <button class="btn btn-sm btn-outline-primary" id="refresh-btn"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>
</div>

<div class="row g-4">
    <!-- Map -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Live Map</h5>
                <div>
                    <span class="badge bg-warning me-1">● Pending Orders</span>
                    <span class="badge bg-success me-1">● Available Riders</span>
                    <span class="badge bg-primary">● Active Deliveries</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="dispatch-map"></div>
            </div>
        </div>
    </div>

    <!-- Orders Panel -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="dispatch-tabs">
                    <li class="nav-item"><a class="nav-link active" href="#" data-tab="orders">Active Orders</a></li>
                    <li class="nav-item"><a class="nav-link" href="#" data-tab="riders">Available Riders</a></li>
                </ul>
            </div>
            <div class="card-body p-2 orders-panel" id="dispatch-panel">
                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Deliveryman</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Order: <strong id="assign-order-num"></strong></p>
                <div class="mb-3">
                    <label class="form-label">Select Deliveryman</label>
                    <select class="form-select" id="assign-dm-select">
                        <option value="">Loading available riders...</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-assign">Assign</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if($googleMapsKey)
<script src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsKey }}&callback=initMap" async defer></script>
@else
<script>
function initMap() {
    const el = document.getElementById('dispatch-map');
    if (el) {
        el.innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:12px;color:#64748b;">'
            + '<i class="fas fa-map-marked-alt" style="font-size:40px;color:#cbd5e1;"></i>'
            + '<div style="font-weight:700;">Google Maps API Key Not Set</div>'
            + '<div style="font-size:13px;">Go to <a href="/admin/settings#section-maps" style="color:#140465;">Settings → Google Maps</a> and add your API key.</div>'
            + '</div>';
    }
}
window.addEventListener('load', initMap);
</script>
@endif
<script>
let map, ordersData = [], ridersData = [], currentTab = 'orders';
let selectedOrderId = null;
const markers = [];

function initMap() {
    map = new google.maps.Map(document.getElementById('dispatch-map'), {
        center: { lat: {{ $defaultLat }}, lng: {{ $defaultLng }} },
        zoom: {{ $defaultZoom }},
        styles: [{ featureType: 'poi', stylers: [{ visibility: 'off' }] }]
    });
    loadData();
}

function clearMarkers() {
    markers.forEach(m => m.setMap(null));
    markers.length = 0;
}

async function loadData() {
    const [ordersRes, ridersRes] = await Promise.all([
        fetch('{{ route("admin.dispatch.orders") }}').then(r => r.json()),
        fetch('{{ route("admin.dispatch.deliverymen") }}').then(r => r.json()),
    ]);
    ordersData = ordersRes.data || [];
    ridersData = ridersRes.data || [];
    document.getElementById('active-count').textContent = `${ordersData.length} Active Orders`;
    renderPanel();
    plotMapMarkers();
}

function plotMapMarkers() {
    clearMarkers();
    ordersData.forEach(order => {
        if (order.delivery_lat && order.delivery_lng) {
            const m = new google.maps.Marker({
                position: { lat: parseFloat(order.delivery_lat), lng: parseFloat(order.delivery_lng) },
                map, title: order.order_number,
                icon: { url: 'https://maps.google.com/mapfiles/ms/icons/yellow-dot.png' }
            });
            markers.push(m);
        }
    });
    ridersData.forEach(rider => {
        if (rider.latitude && rider.longitude) {
            const m = new google.maps.Marker({
                position: { lat: parseFloat(rider.latitude), lng: parseFloat(rider.longitude) },
                map, title: rider.name,
                icon: { url: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png' }
            });
            markers.push(m);
        }
    });
}

function renderPanel() {
    const panel = document.getElementById('dispatch-panel');
    if (currentTab === 'orders') {
        panel.innerHTML = ordersData.length ? ordersData.map(o => `
            <div class="order-card p-3 mb-2 rounded border" onclick="selectOrder(${o.id}, '${o.order_number}')">
                <div class="d-flex justify-content-between">
                    <strong>${o.order_number}</strong>
                    <span class="badge bg-warning">${o.status.replace(/_/g,' ')}</span>
                </div>
                <div class="text-muted small mt-1">${o.vendor} → ${o.customer}</div>
                <div class="d-flex justify-content-between mt-2">
                    <small class="text-success fw-bold">$${o.total}</small>
                    ${o.deliveryman ? `<span class="dm-badge">${o.deliveryman}</span>` : '<button class="btn btn-xs btn-primary py-0 px-2" onclick="openAssign(event,'+o.id+',\''+o.order_number+'\')">Assign</button>'}
                </div>
            </div>
        `).join('') : '<p class="text-center text-muted py-4">No active orders</p>';
    } else {
        panel.innerHTML = ridersData.length ? ridersData.map(r => `
            <div class="order-card p-3 mb-2 rounded border">
                <div class="fw-semibold">${r.name}</div>
                <div class="text-muted small">${r.phone}</div>
                <div class="d-flex justify-content-between mt-1">
                    <span class="dm-badge">Available</span>
                    <span class="text-warning">★ ${r.rating}</span>
                </div>
            </div>
        `).join('') : '<p class="text-center text-muted py-4">No available riders</p>';
    }
}

function selectOrder(id, num) { selectedOrderId = id; }

function openAssign(e, orderId, orderNum) {
    e.stopPropagation();
    selectedOrderId = orderId;
    document.getElementById('assign-order-num').textContent = orderNum;
    const sel = document.getElementById('assign-dm-select');
    sel.innerHTML = ridersData.map(r => `<option value="${r.id}">${r.name} — ${r.phone} (★${r.rating})</option>`).join('');
    new bootstrap.Modal(document.getElementById('assignModal')).show();
}

document.getElementById('confirm-assign').addEventListener('click', async () => {
    const dmId = document.getElementById('assign-dm-select').value;
    if (!selectedOrderId || !dmId) return;
    const res = await fetch('{{ route("admin.dispatch.assign") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ order_id: selectedOrderId, deliveryman_id: dmId })
    }).then(r => r.json());
    if (res.success) {
        bootstrap.Modal.getInstance(document.getElementById('assignModal')).hide();
        toastr.success('Deliveryman assigned!');
        loadData();
    }
});

document.querySelectorAll('[data-tab]').forEach(tab => {
    tab.addEventListener('click', e => {
        e.preventDefault();
        document.querySelectorAll('[data-tab]').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        currentTab = tab.dataset.tab;
        renderPanel();
    });
});

document.getElementById('refresh-btn').addEventListener('click', loadData);
setInterval(loadData, 30000); // Refresh every 30s
</script>
@endpush
