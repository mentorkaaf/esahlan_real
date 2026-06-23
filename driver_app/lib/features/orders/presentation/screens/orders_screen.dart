import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:image_picker/image_picker.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:dio/dio.dart';
import 'dart:io';
import 'dart:async';
import '../../../../core/theme/driver_colors.dart';
import '../../../../core/api/api_client.dart';
import '../../../auth/presentation/providers/auth_provider.dart';

final _availableProvider = FutureProvider.autoDispose<List<dynamic>>((ref) => ref.read(authRepoProvider).availableOrders());
final _activeProvider    = FutureProvider.autoDispose<List<dynamic>>((ref) => ref.read(authRepoProvider).activeOrders());

class OrdersScreen extends ConsumerStatefulWidget {
  const OrdersScreen({super.key});
  @override
  ConsumerState<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends ConsumerState<OrdersScreen> with SingleTickerProviderStateMixin {
  late TabController _tabs;
  Timer? _pollTimer;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    _pollTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      ref.invalidate(_availableProvider);
      ref.invalidate(_activeProvider);
    });
  }

  @override
  void dispose() { _tabs.dispose(); _pollTimer?.cancel(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: DC.navy,
      appBar: AppBar(
        backgroundColor: DC.navyLight,
        title: const Text('Orders', style: TextStyle(fontWeight: FontWeight.w800)),
        bottom: TabBar(controller: _tabs, indicatorColor: DC.orange, indicatorWeight: 3, labelColor: DC.orange, unselectedLabelColor: DC.textMuted,
          tabs: const [Tab(text: 'Available'), Tab(text: 'My Deliveries')]),
      ),
      body: TabBarView(controller: _tabs, children: [
        _AvailableTab(onAccepted: () { _tabs.animateTo(1); ref.invalidate(_activeProvider); }),
        const _ActiveTab(),
      ]),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
// AVAILABLE TAB
// ══════════════════════════════════════════════════════════════════

class _AvailableTab extends ConsumerWidget {
  final VoidCallback onAccepted;
  const _AvailableTab({required this.onAccepted});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final orders = ref.watch(_availableProvider);
    return orders.when(
      loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
      error: (e, _) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
        const Icon(Icons.error_outline, color: DC.error, size: 40),
        const SizedBox(height: 8),
        Text('$e', style: const TextStyle(color: DC.textSec, fontSize: 13), textAlign: TextAlign.center),
        const SizedBox(height: 12),
        ElevatedButton(onPressed: () => ref.invalidate(_availableProvider), child: const Text('Retry')),
      ])),
      data: (list) => list.isEmpty
          ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
              Icon(Icons.inbox_rounded, size: 60, color: DC.textMuted.withValues(alpha: 0.3)),
              const SizedBox(height: 14),
              const Text('No available orders', style: TextStyle(color: DC.textMuted, fontSize: 16, fontWeight: FontWeight.w700)),
              const SizedBox(height: 4),
              const Text('New orders will appear here automatically', style: TextStyle(color: DC.textMuted, fontSize: 12)),
            ]))
          : RefreshIndicator(
              color: DC.orange,
              onRefresh: () async => ref.invalidate(_availableProvider),
              child: ListView.builder(
                padding: const EdgeInsets.all(14),
                itemCount: list.length,
                itemBuilder: (_, i) => _NewOrderCard(
                  order: list[i],
                  onAccept: () async {
                    try {
                      await ref.read(authRepoProvider).acceptOrder((list[i]['id'] as num).toInt());
                      ref.invalidate(_availableProvider);
                      onAccepted();
                      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Order accepted!'), backgroundColor: DC.success));
                    } catch (e) {
                      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: DC.error));
                    }
                  },
                ),
              ),
            ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
// NEW ORDER REQUEST CARD (matches UI reference exactly)
// ══════════════════════════════════════════════════════════════════

class _NewOrderCard extends StatefulWidget {
  final Map<String, dynamic> order;
  final VoidCallback onAccept;
  const _NewOrderCard({required this.order, required this.onAccept});

  @override
  State<_NewOrderCard> createState() => _NewOrderCardState();
}

class _NewOrderCardState extends State<_NewOrderCard> {
  bool _expanded = false;

  static const _moduleLabels = {'efood': 'eFood Delivery', 'eshop': 'eShop Delivery', 'eparcel': 'eParcel Delivery', 'egrocery': 'eGrocery Delivery', 'elaundry': 'eLaundry Pickup', 'emoving': 'eMoving Service'};

  @override
  Widget build(BuildContext context) {
    final o = widget.order;
    final pickup = o['pickup'] as Map<String, dynamic>? ?? {};
    final delivery = o['delivery'] as Map<String, dynamic>? ?? {};
    final module = (o['module_slug'] ?? 'order').toString();
    final fee = double.tryParse('${o['delivery_fee'] ?? 0}') ?? 0;
    final distance = o['distance_km'];
    final estMin = o['estimated_minutes'];
    final parcel = o['parcel'] as Map<String, dynamic>?;
    final moving = o['moving'] as Map<String, dynamic>?;

    final pickupLat = double.tryParse('${pickup['lat'] ?? 0}') ?? 0;
    final pickupLng = double.tryParse('${pickup['lng'] ?? 0}') ?? 0;
    final deliveryLat = double.tryParse('${delivery['lat'] ?? 0}') ?? 0;
    final deliveryLng = double.tryParse('${delivery['lng'] ?? 0}') ?? 0;
    final hasCoords = pickupLat != 0 && deliveryLat != 0;

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: DC.card, borderRadius: BorderRadius.circular(20),
        border: Border.all(color: DC.border.withValues(alpha: 0.5)),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.2), blurRadius: 12, offset: const Offset(0, 4))],
      ),
      child: Column(children: [
        // Header — tap to expand map
        GestureDetector(
          onTap: hasCoords ? () => setState(() => _expanded = !_expanded) : null,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
            decoration: BoxDecoration(color: DC.surface, borderRadius: BorderRadius.vertical(top: const Radius.circular(20), bottom: _expanded ? Radius.zero : Radius.zero)),
            child: Row(children: [
              Container(width: 36, height: 36, decoration: BoxDecoration(color: DC.orangeDim, borderRadius: BorderRadius.circular(10)),
                child: const Icon(Icons.delivery_dining_rounded, color: DC.orange, size: 20)),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(_moduleLabels[module] ?? 'Delivery', style: const TextStyle(color: DC.text, fontWeight: FontWeight.w700, fontSize: 14)),
                Text('Order #${o['order_number'] ?? ''}', style: const TextStyle(color: DC.textMuted, fontSize: 11)),
              ])),
              Icon(_expanded ? Icons.keyboard_arrow_up_rounded : Icons.keyboard_arrow_down_rounded, color: DC.textMuted),
            ]),
          ),
        ),

        // Expandable map
        if (_expanded && hasCoords) SizedBox(height: 180, child: GoogleMap(
          initialCameraPosition: CameraPosition(target: LatLng(pickupLat, pickupLng), zoom: 13),
          markers: {
            Marker(markerId: const MarkerId('pickup'), position: LatLng(pickupLat, pickupLng),
              icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueOrange), infoWindow: InfoWindow(title: 'Pickup', snippet: pickup['name'])),
            Marker(markerId: const MarkerId('delivery'), position: LatLng(deliveryLat, deliveryLng),
              icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueGreen), infoWindow: InfoWindow(title: 'Delivery', snippet: delivery['name'])),
          },
          polylines: {Polyline(polylineId: const PolylineId('route'), points: [LatLng(pickupLat, pickupLng), LatLng(deliveryLat, deliveryLng)], color: DC.orange, width: 3)},
          myLocationEnabled: false, zoomControlsEnabled: false, mapToolbarEnabled: false,
        )),

        Padding(padding: const EdgeInsets.fromLTRB(18, 16, 18, 0), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Pickup → Delivery
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Column(children: [
              Container(width: 12, height: 12, decoration: BoxDecoration(color: DC.orange, shape: BoxShape.circle, border: Border.all(color: DC.card, width: 2))),
              Container(width: 2, height: 30, decoration: BoxDecoration(color: DC.border, borderRadius: BorderRadius.circular(1))),
              Container(width: 12, height: 12, decoration: BoxDecoration(color: DC.success, shape: BoxShape.circle, border: Border.all(color: DC.card, width: 2))),
            ]),
            const SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Pickup Location', style: TextStyle(color: DC.textMuted, fontSize: 10, fontWeight: FontWeight.w600, letterSpacing: 0.5)),
              Text(pickup['name'] ?? 'Pickup', style: const TextStyle(color: DC.text, fontWeight: FontWeight.w800, fontSize: 15)),
              if (pickup['district'] != null) Text(pickup['district'], style: const TextStyle(color: DC.textMuted, fontSize: 11)),
              if (pickup['address'] != null) Text(pickup['address'], style: const TextStyle(color: DC.textMuted, fontSize: 11), maxLines: 1, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 12),
              const Text('Drop Location', style: TextStyle(color: DC.textMuted, fontSize: 10, fontWeight: FontWeight.w600, letterSpacing: 0.5)),
              Text(delivery['name'] ?? 'Customer', style: const TextStyle(color: DC.text, fontWeight: FontWeight.w700, fontSize: 14)),
              if (delivery['district'] != null) Text(delivery['district'], style: const TextStyle(color: DC.textMuted, fontSize: 11)),
              if (delivery['phone'] != null) Text(delivery['phone'], style: const TextStyle(color: DC.textMuted, fontSize: 11)),
            ])),
          ]),

          // Parcel specific details
          if (parcel != null) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: const Color(0xFF8B5CF6).withValues(alpha: 0.06), borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFF8B5CF6).withValues(alpha: 0.2))),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Row(children: [Icon(Icons.inventory_2_rounded, color: Color(0xFF8B5CF6), size: 14), SizedBox(width: 6), Text('Parcel Details', style: TextStyle(color: Color(0xFF8B5CF6), fontWeight: FontWeight.w700, fontSize: 12))]),
                const SizedBox(height: 8),
                if (parcel['sender_name'] != null) _DetailLine('Sender', '${parcel['sender_name']} · ${parcel['sender_phone'] ?? ''}'),
                if (parcel['sender_district'] != null) _DetailLine('From', parcel['sender_district']),
                if (parcel['receiver_name'] != null) _DetailLine('Receiver', '${parcel['receiver_name']} · ${parcel['receiver_phone'] ?? ''}'),
                if (parcel['receiver_district'] != null) _DetailLine('To', parcel['receiver_district']),
                if (parcel['package_type'] != null) _DetailLine('Type', parcel['package_type']),
                if (parcel['weight'] != null) _DetailLine('Weight', '${parcel['weight']} kg'),
                if (parcel['description'] != null) _DetailLine('Note', parcel['description']),
              ]),
            ),
          ],

          // Moving specific details
          if (moving != null) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: DC.error.withValues(alpha: 0.06), borderRadius: BorderRadius.circular(10), border: Border.all(color: DC.error.withValues(alpha: 0.2))),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Row(children: [Icon(Icons.local_shipping_rounded, color: DC.error, size: 14), SizedBox(width: 6), Text('Moving Details', style: TextStyle(color: DC.error, fontWeight: FontWeight.w700, fontSize: 12))]),
                const SizedBox(height: 8),
                if (moving['customer_name'] != null) _DetailLine('Customer', '${moving['customer_name']} · ${moving['customer_phone'] ?? ''}'),
                if (moving['from_district'] != null) _DetailLine('From', '${moving['from_district']}${moving['from_address'] != null ? ' · ${moving['from_address']}' : ''}'),
                if (moving['to_district'] != null) _DetailLine('To', '${moving['to_district']}${moving['to_address'] != null ? ' · ${moving['to_address']}' : ''}'),
                if (moving['moving_type'] != null) _DetailLine('Type', moving['moving_type']),
                if (moving['description'] != null) _DetailLine('Note', moving['description']),
              ]),
            ),
          ],
        ])),

        // Stats row
        Container(
          margin: const EdgeInsets.fromLTRB(18, 14, 18, 0),
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(border: Border(top: BorderSide(color: DC.border.withValues(alpha: 0.5)))),
          child: Row(children: [
            _Stat('Distance', distance != null ? '${distance} km' : '—'),
            _Stat('Time', estMin != null ? '$estMin min' : '—'),
            _Stat('Earnings', '\$${fee.toStringAsFixed(2)}'),
          ]),
        ),

        // Decline / Accept buttons
        Padding(padding: const EdgeInsets.all(14), child: Row(children: [
          Expanded(child: SizedBox(height: 48, child: OutlinedButton(
            onPressed: () {},
            style: OutlinedButton.styleFrom(foregroundColor: DC.textSec, side: BorderSide(color: DC.border), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
            child: const Text('Decline', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
          ))),
          const SizedBox(width: 12),
          Expanded(child: SizedBox(height: 48, child: ElevatedButton(
            onPressed: widget.onAccept,
            style: ElevatedButton.styleFrom(backgroundColor: DC.orange, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
            child: const Text('Accept', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
          ))),
        ])),
      ]),
    );
  }
}

class _DetailLine extends StatelessWidget {
  final String label, value;
  const _DetailLine(this.label, this.value);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 4),
    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      SizedBox(width: 70, child: Text(label, style: const TextStyle(color: DC.textMuted, fontSize: 11))),
      Expanded(child: Text(value, style: const TextStyle(color: DC.text, fontSize: 11, fontWeight: FontWeight.w600))),
    ]),
  );
}

class _Stat extends StatelessWidget {
  final String label, value;
  const _Stat(this.label, this.value);
  @override
  Widget build(BuildContext context) => Expanded(child: Column(children: [
    Text(label, style: const TextStyle(color: DC.textMuted, fontSize: 10, fontWeight: FontWeight.w600)),
    const SizedBox(height: 4),
    Text(value, style: const TextStyle(color: DC.text, fontSize: 15, fontWeight: FontWeight.w900)),
  ]));
}

// ══════════════════════════════════════════════════════════════════
// ACTIVE TAB
// ══════════════════════════════════════════════════════════════════

class _ActiveTab extends ConsumerWidget {
  const _ActiveTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final orders = ref.watch(_activeProvider);
    return orders.when(
      loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
      error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: DC.error))),
      data: (list) => list.isEmpty
          ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
              Icon(Icons.check_circle_outline_rounded, size: 60, color: DC.textMuted.withValues(alpha: 0.3)),
              const SizedBox(height: 14),
              const Text('No active deliveries', style: TextStyle(color: DC.textMuted, fontSize: 16, fontWeight: FontWeight.w700)),
            ]))
          : RefreshIndicator(
              color: DC.orange,
              onRefresh: () async => ref.invalidate(_activeProvider),
              child: ListView.builder(
                padding: const EdgeInsets.all(14),
                itemCount: list.length,
                itemBuilder: (_, i) => GestureDetector(
                  onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => _ActiveDeliveryPage(order: list[i]))),
                  child: _ActiveCard(order: list[i]),
                ),
              ),
            ),
    );
  }
}

class _ActiveCard extends StatelessWidget {
  final Map<String, dynamic> order;
  const _ActiveCard({required this.order});

  @override
  Widget build(BuildContext context) {
    final vendor = order['vendor'] as Map<String, dynamic>?;
    final customer = order['customer'] as Map<String, dynamic>?;
    final module = (order['module_slug'] ?? '').toString();
    final fee = double.tryParse('${order['delivery_fee'] ?? 0}') ?? 0;
    final status = order['status']?.toString() ?? '';

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: DC.card, borderRadius: BorderRadius.circular(18),
        border: Border.all(color: DC.orange.withValues(alpha: 0.3), width: 1.5),
        boxShadow: [BoxShadow(color: DC.orange.withValues(alpha: 0.06), blurRadius: 12)],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(width: 8, height: 8, decoration: BoxDecoration(color: DC.orange, shape: BoxShape.circle,
            boxShadow: [BoxShadow(color: DC.orange.withValues(alpha: 0.5), blurRadius: 6)])),
          const SizedBox(width: 8),
          Text(module.toUpperCase(), style: const TextStyle(color: DC.orange, fontSize: 11, fontWeight: FontWeight.w800)),
          const Spacer(),
          Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(color: DC.success.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(6)),
            child: Text(status.replaceAll('_', ' '), style: const TextStyle(color: DC.success, fontSize: 10, fontWeight: FontWeight.w700))),
        ]),
        const SizedBox(height: 12),
        Text(vendor?['name'] ?? '', style: const TextStyle(color: DC.text, fontWeight: FontWeight.w700, fontSize: 15)),
        const SizedBox(height: 4),
        Row(children: [
          const Icon(Icons.person_rounded, color: DC.textMuted, size: 14),
          const SizedBox(width: 4),
          Text(customer?['name'] ?? '', style: const TextStyle(color: DC.textSec, fontSize: 13)),
          const Spacer(),
          Text('\$${fee.toStringAsFixed(2)}', style: const TextStyle(color: DC.success, fontWeight: FontWeight.w800, fontSize: 14)),
        ]),
        const SizedBox(height: 8),
        Row(children: [
          Text('#${order['order_number'] ?? ''}', style: const TextStyle(color: DC.textMuted, fontSize: 11)),
          const Spacer(),
          const Text('Tap for details →', style: TextStyle(color: DC.orange, fontSize: 11, fontWeight: FontWeight.w600)),
        ]),
      ]),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
// ACTIVE DELIVERY PAGE (matches reference — map + details + actions)
// ══════════════════════════════════════════════════════════════════

class _ActiveDeliveryPage extends ConsumerStatefulWidget {
  final Map<String, dynamic> order;
  const _ActiveDeliveryPage({required this.order});
  @override
  ConsumerState<_ActiveDeliveryPage> createState() => _ActiveDeliveryState();
}

class _ActiveDeliveryState extends ConsumerState<_ActiveDeliveryPage> {
  bool _loading = false;

  @override
  Widget build(BuildContext context) {
    final o = widget.order;
    final vendor = o['vendor'] as Map<String, dynamic>?;
    final customer = o['customer'] as Map<String, dynamic>?;
    final module = (o['module_slug'] ?? '').toString();
    final fee = double.tryParse('${o['delivery_fee'] ?? 0}') ?? 0;
    final distance = o['distance_km'];
    final status = o['status']?.toString() ?? '';
    final vLat = double.tryParse('${vendor?['lat'] ?? 0}') ?? 0;
    final vLng = double.tryParse('${vendor?['lng'] ?? 0}') ?? 0;
    final addr = o['delivery_address'];
    final district = addr is Map ? (addr['district'] ?? addr['city'] ?? '') : '';
    final hasLoc = vLat != 0 && vLng != 0;

    final isPickup = status != 'out_for_delivery';
    final actionLabel = isPickup ? 'Arrived at Pickup' : '✓ Complete Delivery';
    final nextStatus = isPickup ? 'out_for_delivery' : 'delivered';

    return Scaffold(
      backgroundColor: DC.navy,
      appBar: AppBar(
        backgroundColor: DC.navyLight,
        title: const Text('Active Delivery', style: TextStyle(fontWeight: FontWeight.w800)),
        actions: [
          if (customer?['phone'] != null) IconButton(
            icon: const Icon(Icons.phone_rounded, color: DC.success),
            onPressed: () => launchUrl(Uri.parse('tel:${customer!['phone']}')),
          ),
          if (vendor?['phone'] != null) IconButton(
            icon: const Icon(Icons.store_rounded, color: DC.orange),
            onPressed: () => launchUrl(Uri.parse('tel:${vendor!['phone']}')),
          ),
        ],
      ),
      body: Column(children: [
        // Map
        if (hasLoc) SizedBox(height: 240, child: GoogleMap(
          initialCameraPosition: CameraPosition(target: LatLng(vLat, vLng), zoom: 14),
          markers: {
            Marker(markerId: const MarkerId('pickup'), position: LatLng(vLat, vLng),
              icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueOrange),
              infoWindow: InfoWindow(title: 'Pickup', snippet: vendor?['name'])),
          },
          myLocationEnabled: true, myLocationButtonEnabled: true,
          zoomControlsEnabled: false, mapToolbarEnabled: false,
        )),

        // Details
        Expanded(child: SingleChildScrollView(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Pickup card
          _LocationCard(
            icon: Icons.store_rounded, color: DC.orange, label: 'Pickup',
            title: vendor?['name'] ?? '—', subtitle: vendor?['address'] ?? '',
            trailing: isPickup ? Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(color: DC.orange.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(6)),
              child: const Text('Arrived', style: TextStyle(color: DC.orange, fontSize: 10, fontWeight: FontWeight.w700)),
            ) : null,
          ),
          const SizedBox(height: 12),

          // Dropoff card
          _LocationCard(
            icon: Icons.location_on_rounded, color: DC.success, label: 'Drop Off',
            title: customer?['name'] ?? '—', subtitle: district.toString(),
            trailing: distance != null ? Text('${distance} km away', style: const TextStyle(color: DC.textMuted, fontSize: 11)) : null,
          ),
          const SizedBox(height: 16),

          // Order info
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(14), border: Border.all(color: DC.border.withValues(alpha: 0.3))),
            child: Row(children: [
              Text('Order #${o['order_number'] ?? ''}', style: const TextStyle(color: DC.textSec, fontSize: 13, fontWeight: FontWeight.w600)),
              const Spacer(),
              Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: DC.orangeDim, borderRadius: BorderRadius.circular(6)),
                child: Text(module, style: const TextStyle(color: DC.orange, fontSize: 11, fontWeight: FontWeight.w700))),
            ]),
          ),
          const SizedBox(height: 12),

          // Module-specific details
          if (o['parcel'] != null) _ParcelDetails(data: o['parcel'] as Map<String, dynamic>),
          if (o['moving'] != null) _MovingDetails(data: o['moving'] as Map<String, dynamic>),
          if (o['laundry'] != null) _LaundryDetails(data: o['laundry'] as Map<String, dynamic>),

          // Stats
          Container(
            padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 14),
            decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(14), border: Border.all(color: DC.border.withValues(alpha: 0.3))),
            child: Row(children: [
              _MiniInfo(Icons.timer_rounded, distance != null ? '${(distance * 3).toInt()} min' : '—', 'ETA'),
              _MiniInfo(Icons.route_rounded, distance != null ? '$distance km' : '—', 'Distance'),
              _MiniInfo(Icons.access_time_rounded, '--:--', 'Arrival'),
            ]),
          ),
        ]))),

        // Bottom action
        Container(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
          decoration: BoxDecoration(color: DC.navyLight, border: Border(top: BorderSide(color: DC.border))),
          child: Column(children: [
            // Main action button
            SizedBox(width: double.infinity, height: 52, child: ElevatedButton(
              onPressed: _loading ? null : () => _handleAction(nextStatus),
              style: ElevatedButton.styleFrom(
                backgroundColor: isPickup ? DC.orange : DC.success,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              child: _loading
                  ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                  : Text(actionLabel, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
            )),
            if (!isPickup) ...[
              const SizedBox(height: 8),
              // Navigate button
              SizedBox(width: double.infinity, height: 44, child: OutlinedButton.icon(
                onPressed: hasLoc ? () => launchUrl(Uri.parse('https://www.google.com/maps/dir/?api=1&destination=$vLat,$vLng&travelmode=driving')) : null,
                icon: const Icon(Icons.navigation_rounded, size: 18),
                label: const Text('Start Navigation'),
                style: OutlinedButton.styleFrom(foregroundColor: DC.orange, side: const BorderSide(color: DC.orange)),
              )),
            ],
          ]),
        ),
      ]),
    );
  }

  Future<void> _handleAction(String nextStatus) async {
    if (nextStatus == 'delivered') {
      Navigator.push(context, MaterialPageRoute(builder: (_) => _PhotoConfirmPage(order: widget.order)));
      return;
    }
    setState(() => _loading = true);
    try {
      await ref.read(authRepoProvider).updateOrderStatus((widget.order['id'] as num).toInt(), nextStatus);
      ref.invalidate(_activeProvider);
      if (mounted) { Navigator.pop(context); }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: DC.error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }
}

class _LocationCard extends StatelessWidget {
  final IconData icon; final Color color; final String label, title, subtitle; final Widget? trailing;
  const _LocationCard({required this.icon, required this.color, required this.label, required this.title, required this.subtitle, this.trailing});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(14), border: Border.all(color: DC.border.withValues(alpha: 0.3))),
    child: Row(children: [
      Container(width: 40, height: 40, decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
        child: Icon(icon, color: color, size: 20)),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 0.5)),
        Text(title, style: const TextStyle(color: DC.text, fontWeight: FontWeight.w700, fontSize: 14)),
        if (subtitle.isNotEmpty) Text(subtitle, style: const TextStyle(color: DC.textMuted, fontSize: 11), maxLines: 1, overflow: TextOverflow.ellipsis),
      ])),
      if (trailing != null) trailing!,
    ]),
  );
}

class _MiniInfo extends StatelessWidget {
  final IconData icon; final String value, label;
  const _MiniInfo(this.icon, this.value, this.label);
  @override
  Widget build(BuildContext context) => Expanded(child: Column(children: [
    Icon(icon, color: DC.textMuted, size: 16),
    const SizedBox(height: 4),
    Text(value, style: const TextStyle(color: DC.text, fontWeight: FontWeight.w800, fontSize: 14)),
    Text(label, style: const TextStyle(color: DC.textMuted, fontSize: 10)),
  ]));
}

// ══════════════════════════════════════════════════════════════════
// MODULE-SPECIFIC DETAIL WIDGETS
// ══════════════════════════════════════════════════════════════════

class _ParcelDetails extends StatelessWidget {
  final Map<String, dynamic> data;
  const _ParcelDetails({required this.data});
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 12),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(14), border: Border.all(color: const Color(0xFF8B5CF6).withValues(alpha: 0.3))),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        const Icon(Icons.inventory_2_rounded, color: Color(0xFF8B5CF6), size: 18),
        const SizedBox(width: 8),
        const Text('Parcel Details', style: TextStyle(color: Color(0xFF8B5CF6), fontWeight: FontWeight.w700, fontSize: 14)),
      ]),
      const SizedBox(height: 12),
      if (data['sender_name'] != null) _DetailRow('Sender', data['sender_name']),
      if (data['sender_phone'] != null) _DetailRow('Sender Phone', data['sender_phone']),
      if (data['sender_address'] != null) _DetailRow('Pickup Address', data['sender_address']),
      if (data['receiver_name'] != null) _DetailRow('Receiver', data['receiver_name']),
      if (data['receiver_phone'] != null) _DetailRow('Receiver Phone', data['receiver_phone']),
      if (data['receiver_address'] != null) _DetailRow('Delivery Address', data['receiver_address']),
      if (data['package_type'] != null) _DetailRow('Package Type', data['package_type']),
      if (data['weight'] != null) _DetailRow('Weight', '${data['weight']} kg'),
      if (data['description'] != null) _DetailRow('Description', data['description']),
    ]),
  );
}

class _MovingDetails extends StatelessWidget {
  final Map<String, dynamic> data;
  const _MovingDetails({required this.data});
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 12),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(14), border: Border.all(color: DC.error.withValues(alpha: 0.3))),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        const Icon(Icons.local_shipping_rounded, color: DC.error, size: 18),
        const SizedBox(width: 8),
        const Text('Moving Details', style: TextStyle(color: DC.error, fontWeight: FontWeight.w700, fontSize: 14)),
      ]),
      const SizedBox(height: 12),
      if (data['from_address'] != null) _DetailRow('From', data['from_address']),
      if (data['to_address'] != null) _DetailRow('To', data['to_address']),
      if (data['moving_type'] != null) _DetailRow('Type', data['moving_type']),
      if (data['description'] != null) _DetailRow('Notes', data['description']),
      if (data['packages'] is List) ...[
        const SizedBox(height: 6),
        const Text('Items:', style: TextStyle(color: DC.textMuted, fontSize: 12, fontWeight: FontWeight.w600)),
        ...(data['packages'] as List).map((p) => Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Text('• ${p is Map ? (p['name'] ?? p.toString()) : p}', style: const TextStyle(color: DC.textSec, fontSize: 12)),
        )),
      ],
    ]),
  );
}

class _LaundryDetails extends StatelessWidget {
  final Map<String, dynamic> data;
  const _LaundryDetails({required this.data});
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 12),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(14), border: Border.all(color: const Color(0xFF06B6D4).withValues(alpha: 0.3))),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        const Icon(Icons.local_laundry_service_rounded, color: Color(0xFF06B6D4), size: 18),
        const SizedBox(width: 8),
        const Text('Laundry Details', style: TextStyle(color: Color(0xFF06B6D4), fontWeight: FontWeight.w700, fontSize: 14)),
      ]),
      const SizedBox(height: 12),
      if (data['service_type'] != null) _DetailRow('Service', data['service_type'] == 'express' ? '⚡ Express' : '🌿 Normal'),
      if (data['eta'] != null) _DetailRow('ETA', data['eta']),
      if (data['district'] != null) _DetailRow('District', data['district']),
      if (data['items'] is List) ...[
        const SizedBox(height: 6),
        ...(data['items'] as List).map((item) => Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text(item is Map ? '${item['name']} × ${item['qty']}' : '$item', style: const TextStyle(color: DC.textSec, fontSize: 12)),
            if (item is Map && item['sub'] != null) Text('\$${item['sub']}', style: const TextStyle(color: DC.success, fontSize: 12, fontWeight: FontWeight.w600)),
          ]),
        )),
      ],
    ]),
  );
}

class _DetailRow extends StatelessWidget {
  final String label, value; final Color? valueColor;
  const _DetailRow(this.label, this.value, {this.valueColor});
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      SizedBox(width: 110, child: Text(label, style: const TextStyle(color: DC.textMuted, fontSize: 12))),
      Expanded(child: Text(value, style: TextStyle(color: valueColor ?? DC.text, fontWeight: FontWeight.w600, fontSize: 12))),
    ]),
  );
}

// ══════════════════════════════════════════════════════════════════
// PHOTO CONFIRMATION PAGE
// ══════════════════════════════════════════════════════════════════

class _PhotoConfirmPage extends ConsumerStatefulWidget {
  final Map<String, dynamic> order;
  const _PhotoConfirmPage({required this.order});
  @override
  ConsumerState<_PhotoConfirmPage> createState() => _PhotoConfirmState();
}

class _PhotoConfirmState extends ConsumerState<_PhotoConfirmPage> {
  File? _photo;
  bool _loading = false;

  Future<void> _pickPhoto() async {
    final picked = await ImagePicker().pickImage(source: ImageSource.camera, imageQuality: 70);
    if (picked != null) setState(() => _photo = File(picked.path));
  }

  Future<void> _confirm() async {
    setState(() => _loading = true);
    try {
      final orderId = (widget.order['id'] as num).toInt();
      final formData = FormData.fromMap({
        'status': 'delivered',
        if (_photo != null) 'delivery_photo': await MultipartFile.fromFile(_photo!.path, filename: 'delivery.jpg'),
      });
      await ApiClient.instance.post('/delivery/orders/$orderId/status', data: formData,
        options: Options(contentType: 'multipart/form-data'));
      ref.invalidate(_activeProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Delivery completed! 🎉'), backgroundColor: DC.success));
        Navigator.popUntil(context, (route) => route.isFirst);
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: DC.error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: DC.navy,
      appBar: AppBar(backgroundColor: DC.navyLight, title: const Text('Confirm Delivery', style: TextStyle(fontWeight: FontWeight.w800))),
      body: Padding(padding: const EdgeInsets.all(24), child: Column(children: [
        const SizedBox(height: 20),
        Container(width: 80, height: 80, decoration: BoxDecoration(color: DC.orangeDim, shape: BoxShape.circle),
          child: const Icon(Icons.camera_alt_rounded, color: DC.orange, size: 36)),
        const SizedBox(height: 20),
        const Text('Take a delivery photo', style: TextStyle(color: DC.text, fontSize: 22, fontWeight: FontWeight.w800)),
        const SizedBox(height: 6),
        const Text('Photo confirms successful delivery', style: TextStyle(color: DC.textSec, fontSize: 13)),
        const SizedBox(height: 28),
        GestureDetector(
          onTap: _pickPhoto,
          child: Container(
            width: double.infinity, height: 200,
            decoration: BoxDecoration(
              color: DC.surface, borderRadius: BorderRadius.circular(18),
              border: Border.all(color: _photo != null ? DC.success : DC.border, width: 2)),
            child: _photo != null
                ? ClipRRect(borderRadius: BorderRadius.circular(16), child: Image.file(_photo!, fit: BoxFit.cover))
                : Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Icon(Icons.add_a_photo_rounded, color: DC.textMuted.withValues(alpha: 0.5), size: 44),
                    const SizedBox(height: 8),
                    const Text('Tap to take photo', style: TextStyle(color: DC.textMuted, fontSize: 13)),
                  ]),
          ),
        ),
        const Spacer(),
        SizedBox(width: double.infinity, height: 56, child: ElevatedButton(
          onPressed: _loading ? null : _confirm,
          style: ElevatedButton.styleFrom(backgroundColor: DC.success, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16))),
          child: _loading
              ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
              : const Text('Complete Delivery', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
        )),
        const SizedBox(height: 10),
        TextButton(onPressed: _loading ? null : _confirm, child: const Text('Skip photo', style: TextStyle(color: DC.textMuted))),
      ])),
    );
  }
}
