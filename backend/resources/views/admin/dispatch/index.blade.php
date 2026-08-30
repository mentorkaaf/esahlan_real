@extends('admin.layouts.app')
@section('title', 'Dispatch Center')

@section('content')
<div class="page-header">
 <div>
 <h2 class="page-title"><i class="fas fa-broadcast-tower" style="color:var(--primary)"></i> Dispatch Center</h2>
 <ol class="breadcrumb">
 <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
 <li class="breadcrumb-item active">Dispatch</li>
 </ol>
 </div>
 <div style="display:flex;gap:8px;align-items:center;">
 <span id="lastUpdate" style="font-size:11px;color:#8A8A9A;"></span>
 <button class="btn btn-primary btn-sm" onclick="loadData()"><i class="fas fa-sync-alt"></i> Refresh</button>
 </div>
</div>

{{-- Stats Strip --}}
<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:10px;margin-bottom:16px;">
 <div style="background:#fff;border-radius:12px;padding:12px 14px;border-left:4px solid #F59E0B;">
 <div style="font-size:22px;font-weight:900;color:#07003B;">{{ $stats['pending'] }}</div>
 <div style="font-size:11px;color:#8A8A9A;">Pending</div>
 </div>
 <div style="background:#fff;border-radius:12px;padding:12px 14px;border-left:4px solid #3B82F6;">
 <div style="font-size:22px;font-weight:900;color:#07003B;">{{ $stats['confirmed'] }}</div>
 <div style="font-size:11px;color:#8A8A9A;">Confirmed</div>
 </div>
 <div style="background:#fff;border-radius:12px;padding:12px 14px;border-left:4px solid #FF8A00;">
 <div style="font-size:22px;font-weight:900;color:#07003B;">{{ $stats['ready'] }}</div>
 <div style="font-size:11px;color:#8A8A9A;">Ready</div>
 </div>
 <div style="background:#fff;border-radius:12px;padding:12px 14px;border-left:4px solid #8B5CF6;">
 <div style="font-size:22px;font-weight:900;color:#07003B;">{{ $stats['delivering'] }}</div>
 <div style="font-size:11px;color:#8A8A9A;">Delivering</div>
 </div>
 <div style="background:#fff;border-radius:12px;padding:12px 14px;border-left:4px solid #EF4444;">
 <div style="font-size:22px;font-weight:900;color:#07003B;">{{ $stats['unassigned'] }}</div>
 <div style="font-size:11px;color:#8A8A9A;">Unassigned</div>
 </div>
 <div style="background:#fff;border-radius:12px;padding:12px 14px;border-left:4px solid #10B981;">
 <div style="font-size:22px;font-weight:900;color:#07003B;">{{ $stats['drivers_online'] }}</div>
 <div style="font-size:11px;color:#8A8A9A;">Drivers Online</div>
 </div>
 <div style="background:#fff;border-radius:12px;padding:12px 14px;border-left:4px solid #F59E0B;">
 <div style="font-size:22px;font-weight:900;color:#07003B;">{{ $stats['drivers_busy'] }}</div>
 <div style="font-size:11px;color:#8A8A9A;">Drivers Busy</div>
 </div>
</div>

{{-- Map + Panel --}}
<div style="display:grid;grid-template-columns:1fr 380px;gap:16px;align-items:start;">
 {{-- Map --}}
 <div style="background:#fff;border-radius:16px;overflow:hidden;border:1.5px solid #f0f1f5;">
 <div style="padding:14px 18px;border-bottom:1px solid #f0f1f5;display:flex;align-items:center;gap:12px;">
 <span style="font-weight:800;font-size:15px;color:#07003B;">Live Map</span>
 <span style="display:flex;align-items:center;gap:4px;font-size:11px;color:#8A8A9A;"><span style="width:8px;height:8px;border-radius:50%;background:#F59E0B;"></span> Orders</span>
 <span style="display:flex;align-items:center;gap:4px;font-size:11px;color:#8A8A9A;"><span style="width:8px;height:8px;border-radius:50%;background:#10B981;"></span> Drivers</span>
 <span style="display:flex;align-items:center;gap:4px;font-size:11px;color:#8A8A9A;"><span style="width:8px;height:8px;border-radius:50%;background:#FF8A00;"></span> Vendors</span>
 </div>
 <div id="dispatch-map" style="height:520px;"></div>
 </div>

 {{-- Side Panel --}}
 <div style="background:#fff;border-radius:16px;border:1.5px solid #f0f1f5;max-height:590px;display:flex;flex-direction:column;">
 <div style="padding:10px 14px;border-bottom:1px solid #f0f1f5;display:flex;gap:0;">
 <button class="dispatch-tab active" data-tab="orders" onclick="switchTab('orders',this)">Orders <span id="ordersCount" style="background:#FF8A00;color:#fff;border-radius:10px;padding:1px 7px;font-size:10px;margin-left:4px;">0</span></button>
 <button class="dispatch-tab" data-tab="drivers" onclick="switchTab('drivers',this)">Drivers <span id="driversCount" style="background:#10B981;color:#fff;border-radius:10px;padding:1px 7px;font-size:10px;margin-left:4px;">0</span></button>
 </div>
 <div id="panelContent" style="flex:1;overflow-y:auto;padding:10px;"></div>
 </div>
</div>

{{-- Assign Modal --}}
<div class="modal-overlay" id="assignModal">
 <div class="modal-box" style="max-width:420px;">
 <div class="modal-header">
 <h3 class="modal-title">Assign Driver</h3>
 <button class="modal-close" onclick="closeModal('assignModal')"></button>
 </div>
 <div class="modal-body">
 <div style="background:#f8f9fa;border-radius:10px;padding:12px;margin-bottom:14px;">
 <div style="font-size:12px;color:#8A8A9A;">Order</div>
 <div style="font-weight:800;color:#07003B;" id="assignOrderNum"></div>
 </div>
 <div class="form-group">
 <label class="form-label">Select Driver</label>
 <select id="assignDriverSelect" class="form-control"></select>
 </div>
 </div>
 <div style="padding:16px;border-top:1px solid #f0f1f5;display:flex;gap:10px;">
 <button onclick="closeModal('assignModal')" style="flex:1;padding:10px;border:1.5px solid #e0e0e0;border-radius:10px;background:#fff;font-weight:600;cursor:pointer;">Cancel</button>
 <button onclick="confirmAssign()" style="flex:1;padding:10px;border:none;border-radius:10px;background:#FF8A00;color:#fff;font-weight:700;cursor:pointer;">Assign Driver</button>
 </div>
 </div>
</div>

<style>
.dispatch-tab { background:none;border:none;padding:10px 16px;font-size:13px;font-weight:600;color:#8A8A9A;cursor:pointer;border-bottom:2px solid transparent; }
.dispatch-tab.active { color:#FF8A00;border-bottom-color:#FF8A00; }
.d-order { padding:12px;border-radius:12px;border:1.5px solid #f0f1f5;margin-bottom:8px;cursor:pointer;transition:all .15s; }
.d-order:hover { border-color:#FF8A00;background:#fff9f0; }
.d-driver { padding:12px;border-radius:12px;border:1.5px solid #f0f1f5;margin-bottom:8px; }
</style>

@push('scripts')
<script>
var map, ordersData=[], driversData=[], markers=[], currentTab='orders', assignOrderId=null;
var vehicleEmoji = {motorcycle:'Motorcycle',bajaj:'Bajaj',car:'Car',van:'Van',truck:'Truck',bicycle:'Bicycle',pickup:'Pickup'};
var statusColor = {pending:'#F59E0B',confirmed:'#3B82F6',preparing:'#8B5CF6',ready_for_pickup:'#FF8A00',out_for_delivery:'#10B981'};

// Strip emoji / mojibake characters from DB strings
function se(str) {
    if (!str) return '';
    try {
        return str.replace(/[^\x00-\x7FÀ-ɏ؀-ۿ]/gu, '').trim();
    } catch(e) {
        return str.replace(/[^\x00-\x7F]/g, '').trim();
    }
}

function initDispatchMap() {
 map = new google.maps.Map(document.getElementById('dispatch-map'), {
 center:{lat:2.0469,lng:45.3182}, zoom:13,
 mapTypeControl:true,
 mapTypeControlOptions:{style:google.maps.MapTypeControlStyle.HORIZONTAL_BAR,position:google.maps.ControlPosition.TOP_RIGHT,mapTypeIds:['roadmap','satellite','hybrid']},
 streetViewControl:false,
 styles:[{featureType:'poi',stylers:[{visibility:'off'}]}],
 });
 loadData();
 setInterval(loadData, 20000);
}

function clearMarkers() { markers.forEach(function(m){m.setMap(null)}); markers=[]; }

async function loadData() {
 try {
 var [oRes, dRes] = await Promise.all([
 fetch('/admin/dispatch/orders').then(function(r){return r.json()}),
 fetch('/admin/dispatch/deliverymen/available').then(function(r){return r.json()})
 ]);
 ordersData = oRes.data || [];
 driversData = dRes.data || [];
 document.getElementById('ordersCount').textContent = ordersData.length;
 document.getElementById('driversCount').textContent = driversData.length;
 document.getElementById('lastUpdate').textContent = 'Updated ' + new Date().toLocaleTimeString();
 renderPanel();
 plotMarkers();
 } catch(e) {}
}

function plotMarkers() {
 clearMarkers();
 var iw = new google.maps.InfoWindow();

 // Vendor markers (orange store icon)
 var vendorsSeen = {};
 ordersData.forEach(function(o) {
 if (o.vendor_lat && o.vendor_lng && !vendorsSeen[o.vendor_name]) {
 vendorsSeen[o.vendor_name] = true;
 var m = new google.maps.Marker({
 position:{lat:o.vendor_lat,lng:o.vendor_lng}, map:map,
 icon:{path:google.maps.SymbolPath.CIRCLE,scale:12,fillColor:'#FF8A00',fillOpacity:1,strokeColor:'#fff',strokeWeight:3},
 title:se(o.vendor_name),
 });
 m.addListener('click',function(){
 iw.setContent('<div style="padding:4px"><b style="color:#FF8A00">'+se(o.vendor_name)+'</b></div>');
 iw.open(map,m);
 });
 markers.push(m);
 }
 });

 // Customer markers (blue person)
 ordersData.forEach(function(o) {
 if (o.customer_lat && o.customer_lng) {
 var m = new google.maps.Marker({
 position:{lat:o.customer_lat,lng:o.customer_lng}, map:map,
 icon:{path:google.maps.SymbolPath.CIRCLE,scale:8,fillColor:'#3B82F6',fillOpacity:1,strokeColor:'#fff',strokeWeight:2},
 title:se(o.customer_name),
 });
 m.addListener('click',function(){
 iw.setContent('<div style="padding:4px"><b>'+se(o.customer_name)+'</b><br><small>#'+o.order_number+'</small></div>');
 iw.open(map,m);
 });
 markers.push(m);
 }
 });

 // Driver markers (bike icon — circular badge, no pin)
 function makeBikeIcon(color) {
     var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 44 44">'
         + '<circle cx="22" cy="22" r="20" fill="' + color + '" stroke="#ffffff" stroke-width="2.5"/>'
         + '<path fill="#ffffff" transform="translate(6,6) scale(1.33)" d="M19 8c-.6 0-1.1.1-1.6.3L15.5 6H17V4h-3l-1.5-2H9L7.5 4H5v1.5L3.3 7.7C2.5 8.1 2 9 2 10c0 1.7 1.3 3 3 3s3-1.3 3-3c0-.4-.1-.8-.2-1.1L9.2 8h5.6l1 1.3C15.3 9.8 15 10.4 15 11c0 1.7 1.3 3 3 3s3-1.3 3-3-1.3-3-2-3zm-14 3.5c-.8 0-1.5-.7-1.5-1.5S4.2 8.5 5 8.5c.6 0 1.1.3 1.3.8L5.5 10H5v1h.5c-.2.3-.3.5-.5.5zm13 0c-.8 0-1.5-.7-1.5-1.5s.7-1.5 1.5-1.5 1.5.7 1.5 1.5-.7 1.5-1.5 1.5z"/>'
         + '</svg>';
     return {
         url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
         scaledSize: new google.maps.Size(44, 44),
         anchor: new google.maps.Point(22, 22),
     };
 }
 driversData.forEach(function(d) {
 if (!d.latitude || !d.longitude) return;
 var color = d.status === 'available' ? '#10B981' : '#F59E0B';
 var m = new google.maps.Marker({
 position:{lat:d.latitude,lng:d.longitude}, map:map,
 icon: makeBikeIcon(color),
 title:se(d.name)+' ('+se(d.vehicle_type)+')',
 });
 m.addListener('click',function(){
 iw.setContent(
 '<div style="padding:6px;min-width:160px;">'+
 '<div style="font-weight:800;font-size:14px;">'+se(d.name)+'</div>'+
 '<div style="font-size:12px;color:#666;">'+d.phone+'</div>'+
 '<div style="margin-top:4px;">'+
 '<span style="padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;background:'+color+'20;color:'+color+';">'+d.status+'</span>'+
 ' <span style="color:#f59e0b;"> '+d.rating+'</span>'+
 '</div>'+
 '<div style="font-size:11px;color:#888;margin-top:4px;">'+emoji+' '+d.vehicle_type+' '+d.total_deliveries+' trips</div>'+
 (d.last_seen ? '<div style="font-size:10px;color:#aaa;margin-top:2px;">Last seen: '+d.last_seen+'</div>' : '')+
 '</div>'
 );
 iw.open(map,m);
 });
 markers.push(m);
 });
}

function switchTab(tab, btn) {
 currentTab = tab;
 document.querySelectorAll('.dispatch-tab').forEach(function(t){t.classList.remove('active')});
 btn.classList.add('active');
 renderPanel();
}

function renderPanel() {
 var panel = document.getElementById('panelContent');
 if (currentTab === 'orders') {
 if (!ordersData.length) { panel.innerHTML = '<div style="text-align:center;padding:40px;color:#8A8A9A;"><i class="fas fa-inbox" style="font-size:30px;display:block;margin-bottom:10px;opacity:0.3;"></i>No active orders</div>'; return; }
 panel.innerHTML = ordersData.map(function(o) {
 var sc = statusColor[o.status] || '#888';
 var hasDriver = !!o.driver_name;
 return '<div class="d-order" onclick="focusOrder('+o.id+')">'+
 '<div style="display:flex;justify-content:space-between;align-items:center;">'+
 '<span style="font-weight:800;font-size:13px;color:#07003B;">#'+o.order_number+'</span>'+
 '<span style="padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;background:'+sc+'15;color:'+sc+';">'+o.status.replace(/_/g,' ')+'</span>'+
 '</div>'+
 '<div style="font-size:12px;color:#666;margin-top:6px;">'+
 '<span style="color:#FF8A00;"><i class="fas fa-store"></i></span> '+se(o.vendor_name||'')+' <span style="color:#3B82F6;"><i class="fas fa-user"></i></span> '+se(o.customer_name||'')+
 '</div>'+
 '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">'+
 '<span style="font-weight:700;color:#10B981;font-size:13px;">$'+o.total.toFixed(2)+'</span>'+
 (hasDriver
 ? '<span style="font-size:11px;padding:3px 8px;background:#e8f5e9;color:#2e7d32;border-radius:6px;font-weight:600;">'+se(o.driver_name)+'</span>'
 : '<button onclick="event.stopPropagation();openAssign('+o.id+',\''+o.order_number+'\')" style="padding:4px 12px;background:#FF8A00;color:#fff;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;">Assign</button>')+
 '</div>'+
 '<div style="font-size:10px;color:#aaa;margin-top:4px;">'+o.placed_at+'</div>'+
 '</div>';
 }).join('');
 } else {
 if (!driversData.length) { panel.innerHTML = '<div style="text-align:center;padding:40px;color:#8A8A9A;"><i class="fas fa-motorcycle" style="font-size:30px;display:block;margin-bottom:10px;opacity:0.3;"></i>No drivers online</div>'; return; }
 panel.innerHTML = driversData.map(function(d) {
 var emoji = vehicleEmoji[d.vehicle_type] || '—';
 var color = d.status === 'available' ? '#10B981' : '#F59E0B';
 return '<div class="d-driver">'+
 '<div style="display:flex;align-items:center;gap:10px;">'+
 '<div style="width:40px;height:40px;border-radius:10px;background:'+color+'15;display:flex;align-items:center;justify-content:center;font-size:20px;">'+emoji+'</div>'+
 '<div style="flex:1;">'+
 '<div style="font-weight:700;font-size:13px;color:#07003B;">'+se(d.name)+'</div>'+
 '<div style="font-size:11px;color:#8A8A9A;">'+d.phone+'</div>'+
 '</div>'+
 '<div style="text-align:right;">'+
 '<span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;background:'+color+'15;color:'+color+';">'+d.status+'</span>'+
 '<div style="font-size:11px;color:#f59e0b;margin-top:2px;"> '+d.rating+' '+d.total_deliveries+' trips</div>'+
 '</div>'+
 '</div>'+
 (d.last_seen ? '<div style="font-size:10px;color:#aaa;margin-top:6px;"> Last seen: '+d.last_seen+'</div>' : '')+
 '</div>';
 }).join('');
 }
}

function focusOrder(id) {
 var o = ordersData.find(function(x){return x.id===id});
 if (o && o.vendor_lat && o.vendor_lng) map.panTo({lat:o.vendor_lat,lng:o.vendor_lng});
 if (o) map.setZoom(15);
}

function openAssign(orderId, orderNum) {
 assignOrderId = orderId;
 document.getElementById('assignOrderNum').textContent = '#' + orderNum;
 var sel = document.getElementById('assignDriverSelect');
 var available = driversData.filter(function(d){return d.status==='available'});
 sel.innerHTML = available.length
 ? available.map(function(d){
 var emoji = vehicleEmoji[d.vehicle_type]||'';
 return '<option value="'+d.id+'">'+se(d.name)+' '+d.phone+' ('+d.rating+')</option>';
 }).join('')
 : '<option value="">No available drivers</option>';
 openModal('assignModal');
}

async function confirmAssign() {
 var dmId = document.getElementById('assignDriverSelect').value;
 if (!assignOrderId || !dmId) return;
 try {
 var res = await fetch('/admin/dispatch/assign', {
 method:'POST',
 headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},
 body:JSON.stringify({order_id:assignOrderId,deliveryman_id:parseInt(dmId)})
 }).then(function(r){return r.json()});
 if (res.success) {
 closeModal('assignModal');
 loadData();
 }
 } catch(e) {}
}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC1pxwcaFZxDXwqDpxK_gDfPAdpFM8bTnc&callback=initDispatchMap" async defer></script>
@endpush
@endsection
