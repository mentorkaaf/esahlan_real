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
    final c = context.dc;
    return Scaffold(
      backgroundColor: c.navy,
      appBar: AppBar(
        backgroundColor: c.navyLight,
        title: Text('Orders', style: TextStyle(fontWeight: FontWeight.w800, color: c.text)),
        bottom: TabBar(controller: _tabs, indicatorColor: DC.orange, indicatorWeight: 3, labelColor: DC.orange, unselectedLabelColor: c.textMuted,
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
    final c = context.dc;
    final orders = ref.watch(_availableProvider);
    return orders.when(
      loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
      error: (e, _) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
        const Icon(Icons.error_outline, color: DC.error, size: 40),
        const SizedBox(height: 8),
        Text('$e', style: TextStyle(color: c.textSec, fontSize: 13), textAlign: TextAlign.center),
        const SizedBox(height: 12),
        ElevatedButton(onPressed: () => ref.invalidate(_availableProvider), child: const Text('Retry')),
      ])),
      data: (list) => list.isEmpty
          ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
              Icon(Icons.inbox_rounded, size: 60, color: c.textMuted.withValues(alpha: 0.3)),
              const SizedBox(height: 14),
              Text('No available orders', style: TextStyle(color: c.textMuted, fontSize: 16, fontWeight: FontWeight.w700)),
              const SizedBox(height: 4),
              Text('New orders will appear here automatically', style: TextStyle(color: c.textMuted, fontSize: 12)),
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
                  onDecline: () async {
                    try {
                      await ref.read(authRepoProvider).rejectOrder((list[i]['id'] as num).toInt());
                      ref.invalidate(_availableProvider);
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
// NEW ORDER REQUEST CARD
// ══════════════════════════════════════════════════════════════════

class _NewOrderCard extends StatelessWidget {
  final Map<String, dynamic> order;
  final VoidCallback onAccept;
  final VoidCallback onDecline;
  const _NewOrderCard({required this.order, required this.onAccept, required this.onDecline});

  static const _moduleLabels = {'efood': 'eFood Delivery', 'eshop': 'eShop Delivery', 'eparcel': 'eParcel Delivery', 'egrocery': 'eGrocery Delivery', 'elaundry': 'eLaundry Pickup', 'emoving': 'eMoving Service'};

  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    final o = order;
    final pickup = o['pickup'] as Map<String, dynamic>? ?? {};
    final delivery = o['delivery'] as Map<String, dynamic>? ?? {};
    final module = (o['module_slug'] ?? 'order').toString();
    final fee = double.tryParse('${o['delivery_fee'] ?? 0}') ?? 0;
    final distance = o['distance_km'];
    final driverToPickup = o['driver_to_pickup_km'];
    final estMin = o['estimated_minutes'];
    final parcel = o['parcel'] as Map<String, dynamic>?;
    final moving = o['moving'] as Map<String, dynamic>?;
    final isParcel = module == 'eparcel';
    final isMoving = module == 'emoving';
    final pickupLabel = const {'efood': 'You → Restaurant', 'eparcel': 'You → Sender', 'emoving': 'You → Pickup', 'elaundry': 'You → Laundry'}[module] ?? 'You → Store';
    final dropoffLabel = const {'eparcel': 'Sender → Receiver', 'emoving': 'Pickup → Dropoff', 'efood': 'Restaurant → Customer'}[module] ?? 'Store → Customer';

    final pickupLat = double.tryParse('${pickup['lat'] ?? 0}') ?? 0;
    final pickupLng = double.tryParse('${pickup['lng'] ?? 0}') ?? 0;
    final deliveryLat = double.tryParse('${delivery['lat'] ?? 0}') ?? 0;
    final deliveryLng = double.tryParse('${delivery['lng'] ?? 0}') ?? 0;
    final hasCoords = pickupLat != 0 && deliveryLat != 0;

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: c.card, borderRadius: BorderRadius.circular(20),
        border: Border.all(color: c.border.withValues(alpha: 0.5)),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.2), blurRadius: 12, offset: const Offset(0, 4))],
      ),
      child: Column(children: [
        // Header
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
          decoration: BoxDecoration(color: c.surface, borderRadius: const BorderRadius.vertical(top: Radius.circular(20))),
          child: Row(children: [
            Container(width: 36, height: 36, decoration: BoxDecoration(color: DC.orangeDim, borderRadius: BorderRadius.circular(10)),
              child: const Icon(Icons.delivery_dining_rounded, color: DC.orange, size: 20)),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(_moduleLabels[module] ?? 'Delivery', style: TextStyle(color: c.text, fontWeight: FontWeight.w700, fontSize: 14)),
              Text('Order #${o['order_number'] ?? ''}', style: TextStyle(color: c.textMuted, fontSize: 11)),
            ])),
          ]),
        ),

        // MAP
        if (hasCoords) SizedBox(height: 160, child: GoogleMap(
          initialCameraPosition: CameraPosition(
            target: LatLng((pickupLat + deliveryLat) / 2, (pickupLng + deliveryLng) / 2), zoom: 12),
          markers: {
            Marker(markerId: const MarkerId('pickup'), position: LatLng(pickupLat, pickupLng),
              icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueOrange),
              infoWindow: InfoWindow(title: pickup['district'] ?? 'Pickup')),
            Marker(markerId: const MarkerId('delivery'), position: LatLng(deliveryLat, deliveryLng),
              icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueGreen),
              infoWindow: InfoWindow(title: delivery['district'] ?? 'Delivery')),
          },
          polylines: {Polyline(polylineId: const PolylineId('route'),
            points: [LatLng(pickupLat, pickupLng), LatLng(deliveryLat, deliveryLng)],
            color: DC.orange, width: 3, patterns: [PatternItem.dash(20), PatternItem.gap(10)])},
          myLocationEnabled: false, zoomControlsEnabled: true, mapToolbarEnabled: false,
        )),

        Padding(padding: const EdgeInsets.fromLTRB(18, 14, 18, 0), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

          // ═══ eParcel ═══
          if (isParcel) ...[
            _PersonCard(
              icon: Icons.person_outline_rounded, color: DC.orange, label: 'SENDER',
              name: parcel?['sender_name'] ?? pickup['name'] ?? '—',
              phone: parcel?['sender_phone'] ?? pickup['phone'],
              district: parcel?['sender_district'] ?? pickup['district'],
            ),
            const SizedBox(height: 10),
            _PersonCard(
              icon: Icons.person_rounded, color: DC.success, label: 'RECEIVER',
              name: parcel?['receiver_name'] ?? delivery['name'] ?? '—',
              phone: parcel?['receiver_phone'] ?? delivery['phone'],
              district: parcel?['receiver_district'] ?? delivery['district'],
            ),
            if (parcel?['package_type'] != null || parcel?['weight'] != null || parcel?['description'] != null) ...[
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(color: const Color(0xFF8B5CF6).withValues(alpha: 0.06), borderRadius: BorderRadius.circular(10)),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('📦 Package Info', style: TextStyle(color: Color(0xFF8B5CF6), fontWeight: FontWeight.w700, fontSize: 11)),
                  const SizedBox(height: 6),
                  if (parcel?['package_type'] != null) _DetailLine(parcel!['package_type'].toString(), 'Type'),
                  if (parcel?['weight'] != null) _DetailLine('${parcel!['weight']} kg', 'Weight'),
                  if (parcel?['description'] != null) _DetailLine(parcel!['description'].toString(), 'Note'),
                ]),
              ),
            ],
          ]

          // ═══ eMoving ═══
          else if (isMoving) ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: DC.error.withValues(alpha: 0.06), borderRadius: BorderRadius.circular(12), border: Border.all(color: DC.error.withValues(alpha: 0.15))),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Row(children: [Icon(Icons.local_shipping_rounded, color: DC.error, size: 16), SizedBox(width: 8), Text('Moving Details', style: TextStyle(color: DC.error, fontWeight: FontWeight.w800, fontSize: 13))]),
                const SizedBox(height: 10),
                _DetailLine('${moving?['customer_name'] ?? delivery['name']} · ${moving?['customer_phone'] ?? delivery['phone'] ?? ''}', 'Customer'),
                Divider(color: c.divider, height: 14),
                _DetailLine(moving?['from_district'] ?? pickup['district'] ?? '—', 'From'),
                if (moving?['from_address'] != null) _DetailLine(moving!['from_address'].toString(), 'Address'),
                Divider(color: c.divider, height: 14),
                _DetailLine(moving?['to_district'] ?? delivery['district'] ?? '—', 'To'),
                if (moving?['to_address'] != null) _DetailLine(moving!['to_address'].toString(), 'Address'),
                if (moving?['moving_type'] != null) ...[Divider(color: c.divider, height: 14), _DetailLine('${moving!['moving_type']}${moving['room_count'] != null ? ' · ${moving['room_count']} rooms' : ''}', 'Type')],
                if (moving?['scheduled_date'] != null) _DetailLine(moving!['scheduled_date'].toString(), 'Date'),
                if (moving?['distance_price'] != null) ...[
                  Divider(color: c.divider, height: 14),
                  _DetailLine('\$${moving!['distance_price']}', 'Your Earning', valueColor: DC.success),
                ],
                if (moving?['packages'] is List && (moving!['packages'] as List).isNotEmpty) ...[
                  Divider(color: c.divider, height: 14),
                  Text('Packages:', style: TextStyle(color: c.textMuted, fontSize: 11, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 4),
                  ...(moving?['packages'] as List).map((p) => Padding(
                    padding: const EdgeInsets.only(bottom: 2),
                    child: Text('• ${p is Map ? (p['name'] ?? p.toString()) : p}', style: TextStyle(color: c.textSec, fontSize: 11)),
                  )),
                ],
                if (moving?['description'] != null) ...[Divider(color: c.divider, height: 14), _DetailLine(moving!['description'].toString(), 'Note')],
              ]),
            ),
          ]

          // ═══ eFood/eShop/eLaundry/eGrocery ═══
          else ...[
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Column(children: [
                Container(width: 12, height: 12, decoration: BoxDecoration(color: DC.orange, shape: BoxShape.circle, border: Border.all(color: c.card, width: 2))),
                Container(width: 2, height: 28, color: c.border),
                Container(width: 12, height: 12, decoration: BoxDecoration(color: DC.success, shape: BoxShape.circle, border: Border.all(color: c.card, width: 2))),
              ]),
              const SizedBox(width: 14),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(pickup['name'] ?? 'Vendor', style: TextStyle(color: c.text, fontWeight: FontWeight.w800, fontSize: 15)),
                if (pickup['district'] != null) Text(pickup['district'].toString(), style: TextStyle(color: c.textMuted, fontSize: 11)),
                const SizedBox(height: 10),
                Text(delivery['name'] ?? 'Customer', style: TextStyle(color: c.text, fontWeight: FontWeight.w700, fontSize: 14)),
                if (delivery['district'] != null) Text(delivery['district'].toString(), style: TextStyle(color: c.textMuted, fontSize: 11)),
              ])),
            ]),
          ],
        ])),

        // Distance row
        Container(
          margin: const EdgeInsets.fromLTRB(18, 14, 18, 0),
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(border: Border(top: BorderSide(color: c.border.withValues(alpha: 0.5)))),
          child: Column(children: [
            Row(children: [
              _DistanceStat(icon: Icons.two_wheeler_rounded, color: DC.orange, label: pickupLabel, value: driverToPickup != null ? '$driverToPickup km' : '—'),
              Container(width: 1, height: 32, color: c.border.withValues(alpha: 0.4)),
              _DistanceStat(icon: Icons.place_rounded, color: DC.success, label: dropoffLabel, value: distance != null ? '$distance km' : '—'),
            ]),
            const SizedBox(height: 10),
            Row(children: [
              _Stat('Time', estMin != null ? '$estMin min' : '—'),
              _Stat('Earnings', '\$${fee.toStringAsFixed(2)}'),
            ]),
          ]),
        ),

        // Decline / Accept
        Padding(padding: const EdgeInsets.all(14), child: Row(children: [
          Expanded(child: SizedBox(height: 48, child: OutlinedButton(
            onPressed: onDecline,
            style: OutlinedButton.styleFrom(foregroundColor: c.textSec, side: BorderSide(color: c.border), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
            child: const Text('Decline', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
          ))),
          const SizedBox(width: 12),
          Expanded(child: SizedBox(height: 48, child: ElevatedButton(
            onPressed: onAccept,
            style: ElevatedButton.styleFrom(backgroundColor: DC.orange, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
            child: const Text('Accept', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
          ))),
        ])),
      ]),
    );
  }
}

class _PersonCard extends StatelessWidget {
  final IconData icon; final Color color; final String label, name; final String? phone, district;
  const _PersonCard({required this.icon, required this.color, required this.label, required this.name, this.phone, this.district});
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.06), borderRadius: BorderRadius.circular(12), border: Border.all(color: color.withValues(alpha: 0.15))),
      child: Row(children: [
        Container(width: 40, height: 40, decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
          child: Icon(icon, color: color, size: 20)),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w800, letterSpacing: 1)),
          Text(name, style: TextStyle(color: c.text, fontWeight: FontWeight.w700, fontSize: 14)),
          if (phone != null) Text(phone!, style: TextStyle(color: c.textMuted, fontSize: 11)),
          if (district != null) Text('📍 $district', style: TextStyle(color: c.textSec, fontSize: 11)),
        ])),
      ]),
    );
  }
}

class _DetailLine extends StatelessWidget {
  final String value, label;
  final Color? valueColor;
  const _DetailLine(this.value, this.label, {this.valueColor});
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        SizedBox(width: 80, child: Text(label, style: TextStyle(color: c.textMuted, fontSize: 11))),
        Expanded(child: Text(value, style: TextStyle(color: valueColor ?? c.text, fontSize: 11, fontWeight: FontWeight.w600))),
      ]),
    );
  }
}

class _Stat extends StatelessWidget {
  final String label, value;
  const _Stat(this.label, this.value);
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Expanded(child: Column(children: [
      Text(label, style: TextStyle(color: c.textMuted, fontSize: 10, fontWeight: FontWeight.w600)),
      const SizedBox(height: 4),
      Text(value, style: TextStyle(color: c.text, fontSize: 15, fontWeight: FontWeight.w900)),
    ]));
  }
}

class _DistanceStat extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String label, value;
  const _DistanceStat({required this.icon, required this.color, required this.label, required this.value});
  @override
  Widget build(BuildContext context) => Expanded(child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
    Icon(icon, color: color, size: 16),
    const SizedBox(width: 6),
    Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: TextStyle(color: color.withValues(alpha: 0.8), fontSize: 9, fontWeight: FontWeight.w700, letterSpacing: 0.3)),
      Text(value, style: TextStyle(color: color, fontSize: 15, fontWeight: FontWeight.w900)),
    ]),
  ]));
}

// ══════════════════════════════════════════════════════════════════
// ACTIVE TAB
// ══════════════════════════════════════════════════════════════════

class _ActiveTab extends ConsumerWidget {
  const _ActiveTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final c = context.dc;
    final orders = ref.watch(_activeProvider);
    return orders.when(
      loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
      error: (e, _) => Center(child: Text('$e', style: TextStyle(color: DC.error))),
      data: (list) => list.isEmpty
          ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
              Icon(Icons.check_circle_outline_rounded, size: 60, color: c.textMuted.withValues(alpha: 0.3)),
              const SizedBox(height: 14),
              Text('No active deliveries', style: TextStyle(color: c.textMuted, fontSize: 16, fontWeight: FontWeight.w700)),
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
    final c = context.dc;
    final vendor = order['vendor'] as Map<String, dynamic>?;
    final customer = order['customer'] as Map<String, dynamic>?;
    final module = (order['module_slug'] ?? '').toString();
    final fee = double.tryParse('${order['delivery_fee'] ?? 0}') ?? 0;
    final status = order['status']?.toString() ?? '';

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: c.card, borderRadius: BorderRadius.circular(18),
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
        Text(vendor?['name'] ?? '', style: TextStyle(color: c.text, fontWeight: FontWeight.w700, fontSize: 15)),
        const SizedBox(height: 4),
        Row(children: [
          Icon(Icons.person_rounded, color: c.textMuted, size: 14),
          const SizedBox(width: 4),
          Text(customer?['name'] ?? '', style: TextStyle(color: c.textSec, fontSize: 13)),
          const Spacer(),
          Text('\$${fee.toStringAsFixed(2)}', style: const TextStyle(color: DC.success, fontWeight: FontWeight.w800, fontSize: 14)),
        ]),
        const SizedBox(height: 8),
        Row(children: [
          Text('#${order['order_number'] ?? ''}', style: TextStyle(color: c.textMuted, fontSize: 11)),
          const Spacer(),
          const Text('Tap for details →', style: TextStyle(color: DC.orange, fontSize: 11, fontWeight: FontWeight.w600)),
        ]),
      ]),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
// ACTIVE DELIVERY PAGE
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
    final c = context.dc;
    final o = widget.order;
    final vendor = o['vendor'] as Map<String, dynamic>?;
    final customer = o['customer'] as Map<String, dynamic>?;
    final module = (o['module_slug'] ?? '').toString();
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
      backgroundColor: c.navy,
      appBar: AppBar(
        backgroundColor: c.navyLight,
        title: Text('Active Delivery', style: TextStyle(fontWeight: FontWeight.w800, color: c.text)),
        actions: [
          if (customer != null) IconButton(
            icon: const Icon(Icons.chat_bubble_rounded, color: DC.success),
            tooltip: 'Chat with customer',
            onPressed: () => showModalBottomSheet(
              context: context,
              isScrollControlled: true,
              backgroundColor: Colors.transparent,
              builder: (_) => _CustomerChatSheet(customer: customer!),
            ),
          ),
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
          _LocationCard(icon: Icons.store_rounded, color: DC.orange, label: 'Pickup',
            title: vendor?['name'] ?? '—', subtitle: vendor?['address'] ?? '',
            trailing: isPickup ? Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(color: DC.orange.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(6)),
              child: const Text('Arrived', style: TextStyle(color: DC.orange, fontSize: 10, fontWeight: FontWeight.w700)),
            ) : null,
          ),
          const SizedBox(height: 12),

          _LocationCard(icon: Icons.location_on_rounded, color: DC.success, label: 'Drop Off',
            title: customer?['name'] ?? '—', subtitle: district.toString(),
            trailing: distance != null ? Text('$distance km away', style: TextStyle(color: c.textMuted, fontSize: 11)) : null,
          ),
          const SizedBox(height: 16),

          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(color: c.card, borderRadius: BorderRadius.circular(14), border: Border.all(color: c.border.withValues(alpha: 0.3))),
            child: Row(children: [
              Text('Order #${o['order_number'] ?? ''}', style: TextStyle(color: c.textSec, fontSize: 13, fontWeight: FontWeight.w600)),
              const Spacer(),
              Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: DC.orangeDim, borderRadius: BorderRadius.circular(6)),
                child: Text(module, style: const TextStyle(color: DC.orange, fontSize: 11, fontWeight: FontWeight.w700))),
            ]),
          ),
          const SizedBox(height: 12),

          if (o['parcel'] != null) _ParcelDetails(data: o['parcel'] as Map<String, dynamic>),
          if (o['moving'] != null) _MovingDetails(data: o['moving'] as Map<String, dynamic>),
          if (o['laundry'] != null) _LaundryDetails(data: o['laundry'] as Map<String, dynamic>),

          Container(
            padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 14),
            decoration: BoxDecoration(color: c.card, borderRadius: BorderRadius.circular(14), border: Border.all(color: c.border.withValues(alpha: 0.3))),
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
          decoration: BoxDecoration(color: c.navyLight, border: Border(top: BorderSide(color: c.border))),
          child: Column(children: [
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
  Widget build(BuildContext context) {
    final c = context.dc;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: c.card, borderRadius: BorderRadius.circular(14), border: Border.all(color: c.border.withValues(alpha: 0.3))),
      child: Row(children: [
        Container(width: 40, height: 40, decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
          child: Icon(icon, color: color, size: 20)),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 0.5)),
          Text(title, style: TextStyle(color: c.text, fontWeight: FontWeight.w700, fontSize: 14)),
          if (subtitle.isNotEmpty) Text(subtitle, style: TextStyle(color: c.textMuted, fontSize: 11), maxLines: 1, overflow: TextOverflow.ellipsis),
        ])),
        if (trailing != null) trailing!,
      ]),
    );
  }
}

class _MiniInfo extends StatelessWidget {
  final IconData icon; final String value, label;
  const _MiniInfo(this.icon, this.value, this.label);
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Expanded(child: Column(children: [
      Icon(icon, color: c.textMuted, size: 16),
      const SizedBox(height: 4),
      Text(value, style: TextStyle(color: c.text, fontWeight: FontWeight.w800, fontSize: 14)),
      Text(label, style: TextStyle(color: c.textMuted, fontSize: 10)),
    ]));
  }
}

// ══════════════════════════════════════════════════════════════════
// MODULE-SPECIFIC DETAIL WIDGETS
// ══════════════════════════════════════════════════════════════════

class _ParcelDetails extends StatelessWidget {
  final Map<String, dynamic> data;
  const _ParcelDetails({required this.data});
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: c.card, borderRadius: BorderRadius.circular(14), border: Border.all(color: const Color(0xFF8B5CF6).withValues(alpha: 0.3))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Row(children: [
          Icon(Icons.inventory_2_rounded, color: Color(0xFF8B5CF6), size: 18),
          SizedBox(width: 8),
          Text('Parcel Details', style: TextStyle(color: Color(0xFF8B5CF6), fontWeight: FontWeight.w700, fontSize: 14)),
        ]),
        const SizedBox(height: 12),
        if (data['sender_name'] != null) _DetailRow('Sender', data['sender_name'].toString()),
        if (data['sender_phone'] != null) _DetailRow('Sender Phone', data['sender_phone'].toString()),
        if (data['sender_address'] != null) _DetailRow('Pickup Address', data['sender_address'].toString()),
        if (data['receiver_name'] != null) _DetailRow('Receiver', data['receiver_name'].toString()),
        if (data['receiver_phone'] != null) _DetailRow('Receiver Phone', data['receiver_phone'].toString()),
        if (data['receiver_address'] != null) _DetailRow('Delivery Address', data['receiver_address'].toString()),
        if (data['package_type'] != null) _DetailRow('Package Type', data['package_type'].toString()),
        if (data['weight'] != null) _DetailRow('Weight', '${data['weight']} kg'),
        if (data['description'] != null) _DetailRow('Description', data['description'].toString()),
      ]),
    );
  }
}

class _MovingDetails extends StatelessWidget {
  final Map<String, dynamic> data;
  const _MovingDetails({required this.data});
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: c.card, borderRadius: BorderRadius.circular(14), border: Border.all(color: DC.error.withValues(alpha: 0.3))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Row(children: [
          Icon(Icons.local_shipping_rounded, color: DC.error, size: 18),
          SizedBox(width: 8),
          Text('Moving Details', style: TextStyle(color: DC.error, fontWeight: FontWeight.w700, fontSize: 14)),
        ]),
        const SizedBox(height: 12),
        if (data['from_address'] != null) _DetailRow('From', data['from_address'].toString()),
        if (data['to_address'] != null) _DetailRow('To', data['to_address'].toString()),
        if (data['moving_type'] != null) _DetailRow('Type', data['moving_type'].toString()),
        if (data['description'] != null) _DetailRow('Notes', data['description'].toString()),
        if (data['packages'] is List) ...[
          const SizedBox(height: 6),
          Text('Items:', style: TextStyle(color: c.textMuted, fontSize: 12, fontWeight: FontWeight.w600)),
          ...(data['packages'] as List).map((p) => Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text('• ${p is Map ? (p['name'] ?? p.toString()) : p}', style: TextStyle(color: c.textSec, fontSize: 12)),
          )),
        ],
      ]),
    );
  }
}

class _LaundryDetails extends StatelessWidget {
  final Map<String, dynamic> data;
  const _LaundryDetails({required this.data});
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: c.card, borderRadius: BorderRadius.circular(14), border: Border.all(color: const Color(0xFF06B6D4).withValues(alpha: 0.3))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Row(children: [
          Icon(Icons.local_laundry_service_rounded, color: Color(0xFF06B6D4), size: 18),
          SizedBox(width: 8),
          Text('Laundry Details', style: TextStyle(color: Color(0xFF06B6D4), fontWeight: FontWeight.w700, fontSize: 14)),
        ]),
        const SizedBox(height: 12),
        if (data['service_type'] != null) _DetailRow('Service', data['service_type'] == 'express' ? '⚡ Express' : '🌿 Normal'),
        if (data['eta'] != null) _DetailRow('ETA', data['eta'].toString()),
        if (data['district'] != null) _DetailRow('District', data['district'].toString()),
        if (data['items'] is List) ...[
          const SizedBox(height: 6),
          ...(data['items'] as List).map((item) => Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text(item is Map ? '${item['name']} × ${item['qty']}' : '$item', style: TextStyle(color: c.textSec, fontSize: 12)),
              if (item is Map && item['sub'] != null) Text('\$${item['sub']}', style: const TextStyle(color: DC.success, fontSize: 12, fontWeight: FontWeight.w600)),
            ]),
          )),
        ],
      ]),
    );
  }
}

class _DetailRow extends StatelessWidget {
  final String label, value; final Color? valueColor;
  const _DetailRow(this.label, this.value, {this.valueColor});
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        SizedBox(width: 110, child: Text(label, style: TextStyle(color: c.textMuted, fontSize: 12))),
        Expanded(child: Text(value, style: TextStyle(color: valueColor ?? c.text, fontWeight: FontWeight.w600, fontSize: 12))),
      ]),
    );
  }
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
    final c = context.dc;
    return Scaffold(
      backgroundColor: c.navy,
      appBar: AppBar(backgroundColor: c.navyLight, title: Text('Confirm Delivery', style: TextStyle(fontWeight: FontWeight.w800, color: c.text))),
      body: Padding(padding: const EdgeInsets.all(24), child: Column(children: [
        const SizedBox(height: 20),
        Container(width: 80, height: 80, decoration: BoxDecoration(color: DC.orangeDim, shape: BoxShape.circle),
          child: const Icon(Icons.camera_alt_rounded, color: DC.orange, size: 36)),
        const SizedBox(height: 20),
        Text('Take a delivery photo', style: TextStyle(color: c.text, fontSize: 22, fontWeight: FontWeight.w800)),
        const SizedBox(height: 6),
        Text('Photo confirms successful delivery', style: TextStyle(color: c.textSec, fontSize: 13)),
        const SizedBox(height: 28),
        GestureDetector(
          onTap: _pickPhoto,
          child: Container(
            width: double.infinity, height: 200,
            decoration: BoxDecoration(
              color: c.surface, borderRadius: BorderRadius.circular(18),
              border: Border.all(color: _photo != null ? DC.success : c.border, width: 2)),
            child: _photo != null
                ? ClipRRect(borderRadius: BorderRadius.circular(16), child: Image.file(_photo!, fit: BoxFit.cover))
                : Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Icon(Icons.add_a_photo_rounded, color: c.textMuted.withValues(alpha: 0.5), size: 44),
                    const SizedBox(height: 8),
                    Text('Tap to take photo', style: TextStyle(color: c.textMuted, fontSize: 13)),
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
        TextButton(onPressed: _loading ? null : _confirm, child: Text('Skip photo', style: TextStyle(color: c.textMuted))),
      ])),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
// CUSTOMER CHAT SHEET (driver → customer)
// ══════════════════════════════════════════════════════════════════

class _CustomerChatSheet extends StatefulWidget {
  final Map<String, dynamic> customer;
  const _CustomerChatSheet({required this.customer});
  @override
  State<_CustomerChatSheet> createState() => _CustomerChatSheetState();
}

class _CustomerChatSheetState extends State<_CustomerChatSheet> {
  final _ctrl = TextEditingController();
  final List<({String text, bool isMe})> _msgs = [];

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  void _send() {
    final text = _ctrl.text.trim();
    if (text.isEmpty) return;
    setState(() => _msgs.add((text: text, isMe: true)));
    _ctrl.clear();
    // TODO: wire to order chat API endpoint
  }

  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    final name = widget.customer['name'] ?? 'Customer';
    final phone = widget.customer['phone']?.toString();

    return DraggableScrollableSheet(
      initialChildSize: 0.75,
      maxChildSize: 0.95,
      minChildSize: 0.5,
      builder: (_, sc) => Container(
        decoration: BoxDecoration(
          color: c.navy,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(children: [
          Container(margin: const EdgeInsets.only(top: 10), width: 36, height: 4,
            decoration: BoxDecoration(color: c.border, borderRadius: BorderRadius.circular(2))),
          Padding(padding: const EdgeInsets.fromLTRB(16, 14, 8, 0), child: Row(children: [
            Container(width: 38, height: 38,
              decoration: BoxDecoration(color: DC.success.withValues(alpha: 0.15), shape: BoxShape.circle),
              child: Center(child: Text(name.isNotEmpty ? name[0].toUpperCase() : 'C',
                style: const TextStyle(color: DC.success, fontWeight: FontWeight.w900, fontSize: 16)))),
            const SizedBox(width: 10),
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(name, style: TextStyle(fontWeight: FontWeight.w800, color: c.text)),
              Text('Customer', style: TextStyle(color: c.textMuted, fontSize: 11)),
            ]),
            const Spacer(),
            if (phone != null)
              IconButton(icon: const Icon(Icons.phone_rounded, color: DC.success),
                onPressed: () => launchUrl(Uri.parse('tel:$phone'))),
          ])),
          Divider(color: c.border),
          Expanded(child: _msgs.isEmpty
            ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.chat_bubble_outline_rounded, size: 48, color: c.textMuted.withValues(alpha: 0.3)),
                const SizedBox(height: 8),
                Text('Send a message to your customer', style: TextStyle(color: c.textMuted)),
              ]))
            : ListView.builder(
                controller: sc,
                padding: const EdgeInsets.all(16),
                itemCount: _msgs.length,
                itemBuilder: (_, i) {
                  final m = _msgs[i];
                  return Align(
                    alignment: m.isMe ? Alignment.centerRight : Alignment.centerLeft,
                    child: Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      decoration: BoxDecoration(
                        color: m.isMe ? DC.orange : c.card,
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: Text(m.text, style: TextStyle(color: m.isMe ? Colors.white : c.text, fontSize: 14)),
                    ),
                  );
                },
              )),
          SafeArea(
            top: false,
            child: Padding(padding: const EdgeInsets.fromLTRB(12, 8, 12, 8), child: Row(children: [
              Expanded(child: TextField(
                controller: _ctrl,
                style: TextStyle(color: c.text),
                decoration: InputDecoration(
                  hintText: 'Message customer...',
                  hintStyle: TextStyle(color: c.textMuted),
                  filled: true,
                  fillColor: c.card,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide.none),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                ),
              )),
              const SizedBox(width: 8),
              GestureDetector(
                onTap: _send,
                child: Container(width: 44, height: 44,
                  decoration: const BoxDecoration(color: DC.orange, shape: BoxShape.circle),
                  child: const Icon(Icons.send_rounded, color: Colors.white, size: 20)),
              ),
            ])),
          ),
        ]),
      ),
    );
  }
}
