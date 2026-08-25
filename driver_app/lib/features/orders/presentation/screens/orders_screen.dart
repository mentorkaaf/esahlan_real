import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:image_picker/image_picker.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:geolocator/geolocator.dart';
import 'package:dio/dio.dart';
import 'package:audio_waveforms/audio_waveforms.dart' hide PlayerState;
import 'package:audioplayers/audioplayers.dart';
import 'package:path_provider/path_provider.dart';
import 'dart:io';
import 'dart:async';
import '../../../../core/services/realtime_service.dart';
import '../../../../core/theme/driver_colors.dart';
import '../../../../core/api/api_client.dart';
import '../../../auth/presentation/providers/auth_provider.dart';

const _driverMapsKey = 'AIzaSyA9J4TSypPZv3cr8Zlabn0BSDICD_Ibp-A';

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
    // formatOrder returns pickup/delivery keys
    final pickup   = (order['pickup']   ?? order['vendor'])   as Map<String, dynamic>?;
    final delivery = (order['delivery'] ?? order['customer']) as Map<String, dynamic>?;
    final module = (order['module_slug'] ?? '').toString();
    final fee = double.tryParse('${order['delivery_fee'] ?? 0}') ?? 0;
    final status = order['status']?.toString() ?? '';
    final orderId = (order['id'] as num).toInt();
    final customerPhone = delivery?['phone']?.toString();

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
        Text(pickup?['name'] ?? '—', style: TextStyle(color: c.text, fontWeight: FontWeight.w700, fontSize: 15)),
        const SizedBox(height: 4),
        Row(children: [
          Icon(Icons.person_rounded, color: c.textMuted, size: 14),
          const SizedBox(width: 4),
          Text(delivery?['name'] ?? '—', style: TextStyle(color: c.textSec, fontSize: 13)),
          const Spacer(),
          Text('\$${fee.toStringAsFixed(2)}', style: const TextStyle(color: DC.success, fontWeight: FontWeight.w800, fontSize: 14)),
        ]),
        const SizedBox(height: 10),
        Row(children: [
          Text('#${order['order_number'] ?? ''}', style: TextStyle(color: c.textMuted, fontSize: 11)),
          const Spacer(),
          // Chat button
          GestureDetector(
            onTap: () => showModalBottomSheet(
              context: context,
              isScrollControlled: true,
              backgroundColor: Colors.transparent,
              builder: (_) => _CustomerChatSheet(customer: delivery, orderId: orderId),
            ),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
              decoration: BoxDecoration(
                color: DC.success.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: DC.success.withValues(alpha: 0.3)),
              ),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.chat_bubble_rounded, color: DC.success, size: 12),
                const SizedBox(width: 4),
                const Text('Chat', style: TextStyle(color: DC.success, fontSize: 11, fontWeight: FontWeight.w700)),
              ]),
            ),
          ),
          const SizedBox(width: 8),
          if (customerPhone != null) GestureDetector(
            onTap: () => launchUrl(Uri.parse('tel:$customerPhone')),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
              decoration: BoxDecoration(
                color: DC.orange.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: DC.orange.withValues(alpha: 0.3)),
              ),
              child: const Row(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.phone_rounded, color: DC.orange, size: 12),
                SizedBox(width: 4),
                Text('Call', style: TextStyle(color: DC.orange, fontSize: 11, fontWeight: FontWeight.w700)),
              ]),
            ),
          ),
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
  LatLng? _customerLoc;

  late final int _orderId;
  late final String _realtimeChannel;

  @override
  void initState() {
    super.initState();
    _orderId = (widget.order['id'] as num).toInt();
    _realtimeChannel = 'private-order-chat.$_orderId';
    _subscribeCustomerLocation();
  }

  void _subscribeCustomerLocation() {
    RealtimeService.instance.listen(_realtimeChannel, 'customer_location', _onCustomerLocation);
  }

  void _onCustomerLocation(dynamic data) {
    if (!mounted) return;
    final m = data is Map ? data : <String, dynamic>{};
    final lat = double.tryParse('${m['lat'] ?? ''}');
    final lng = double.tryParse('${m['lng'] ?? ''}');
    if (lat != null && lng != null) {
      setState(() => _customerLoc = LatLng(lat, lng));
    }
  }

  @override
  void dispose() {
    RealtimeService.instance.removeListener(_realtimeChannel, 'customer_location', _onCustomerLocation);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    final o = widget.order;
    // API returns customer info under 'delivery' key (not 'customer')
    final vendor   = (o['vendor']   ?? o['pickup'])   as Map<String, dynamic>?;
    final customer = (o['delivery'] ?? o['customer']) as Map<String, dynamic>?;
    final module = (o['module_slug'] ?? '').toString();
    final distance = o['distance_km'];
    final status = o['status']?.toString() ?? '';
    final vLat = double.tryParse('${vendor?['lat'] ?? 0}') ?? 0;
    final vLng = double.tryParse('${vendor?['lng'] ?? 0}') ?? 0;
    final addr = o['delivery_address'];
    final district = customer?['district']?.toString() ?? (addr is Map ? (addr['district'] ?? addr['city'] ?? '') : '');
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
              builder: (_) => _CustomerChatSheet(
                customer: customer,
                orderId: (o['id'] as num).toInt(),
              ),
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
        // Map (shows pickup + customer location when shared)
        if (hasLoc) SizedBox(height: 240, child: GoogleMap(
          initialCameraPosition: CameraPosition(target: LatLng(vLat, vLng), zoom: 14),
          markers: {
            Marker(markerId: const MarkerId('pickup'), position: LatLng(vLat, vLng),
              icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueOrange),
              infoWindow: InfoWindow(title: 'Pickup', snippet: vendor?['name'])),
            if (_customerLoc != null)
              Marker(
                markerId: const MarkerId('customer'),
                position: _customerLoc!,
                icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueGreen),
                infoWindow: InfoWindow(title: customer?['name'] ?? 'Customer', snippet: 'Shared location'),
              ),
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
// CUSTOMER CHAT SHEET (driver → customer, real-time via Reverb)
// ══════════════════════════════════════════════════════════════════

class _CustomerChatSheet extends StatefulWidget {
  final Map<String, dynamic>? customer;
  final int orderId;
  const _CustomerChatSheet({required this.customer, required this.orderId});
  @override
  State<_CustomerChatSheet> createState() => _CustomerChatSheetState();
}

class _CustomerChatSheetState extends State<_CustomerChatSheet> {
  final _ctrl = TextEditingController();
  final _scrollCtrl = ScrollController();
  final List<_DriverChatMsg> _msgs = [];
  bool _loading = true;
  bool _sending = false;

  // Voice recording
  final _recorder = RecorderController();
  bool _recording = false;
  int _recordSeconds = 0;
  Timer? _recordTimer;
  String? _pendingVoicePath;

  String get _channel => 'private-order-chat.${widget.orderId}';

  @override
  void initState() {
    super.initState();
    _loadHistory();
    _subscribeRealtime();
  }

  Future<void> _loadHistory() async {
    try {
      final resp = await ApiClient.instance.get('/delivery/orders/${widget.orderId}/chat');
      final data = resp.data;
      final List msgs = (data is Map ? data['data'] : null) ?? [];
      if (mounted) {
        setState(() {
          _msgs.clear();
          for (final m in msgs) {
            if (m is Map) _msgs.add(_DriverChatMsg.fromMap(m));
          }
          _loading = false;
        });
        _scrollToBottom();
      }
    } catch (e) {
      debugPrint('[DriverChat] load failed: $e');
      if (mounted) setState(() => _loading = false);
    }
  }

  void _subscribeRealtime() {
    RealtimeService.instance.listen(_channel, 'new_message', _onRealtime);
  }

  void _onRealtime(dynamic data) {
    if (!mounted) return;
    final m = data is Map ? data : <String, dynamic>{};
    setState(() => _msgs.add(_DriverChatMsg.fromMap(m)));
    _scrollToBottom();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollCtrl.hasClients) {
        _scrollCtrl.animateTo(_scrollCtrl.position.maxScrollExtent,
            duration: const Duration(milliseconds: 250), curve: Curves.easeOut);
      }
    });
  }

  Future<void> _sendText() async {
    final text = _ctrl.text.trim();
    if (text.isEmpty || _sending) return;
    _ctrl.clear();
    setState(() {
      _msgs.add(_DriverChatMsg(text: text, isMe: true, type: _DriverMsgType.text));
      _sending = true;
    });
    _scrollToBottom();
    try {
      await ApiClient.instance.post('/delivery/orders/${widget.orderId}/chat',
          data: {'message': text, 'message_type': 'text'});
    } catch (_) {}
    if (mounted) setState(() => _sending = false);
  }

  Future<void> _requestLocation() async {
    if (_sending) return;
    setState(() => _sending = true);
    try {
      await ApiClient.instance.post('/delivery/orders/${widget.orderId}/chat',
          data: {'message_type': 'location_request'});
      if (mounted) {
        setState(() => _msgs.add(_DriverChatMsg(
            text: '📍 Location requested', isMe: true,
            type: _DriverMsgType.locationRequest)));
        _scrollToBottom();
      }
    } catch (_) {}
    if (mounted) setState(() => _sending = false);
  }

  // ── Voice recording ────────────────────────────────────────────────────────

  Future<void> _startRecording() async {
    if (_recording) return;
    final hasPermission = await _recorder.checkPermission();
    if (!hasPermission) { _showSnack('Microphone permission required'); return; }
    final dir = await getTemporaryDirectory();
    final path = '${dir.path}/voice_${DateTime.now().millisecondsSinceEpoch}.m4a';
    await _recorder.record(
      path: path,
      sampleRate: 44100,
      bitRate: 128000,
    );
    setState(() { _recording = true; _recordSeconds = 0; });
    _recordTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() => _recordSeconds++);
    });
  }

  Future<void> _stopRecording({bool cancel = false}) async {
    _recordTimer?.cancel();
    final path = await _recorder.stop();
    if (!mounted) return;
    if (cancel || path == null) {
      setState(() { _recording = false; _pendingVoicePath = null; }); return;
    }
    setState(() { _recording = false; _pendingVoicePath = path; });
  }

  Future<void> _sendVoice() async {
    final path = _pendingVoicePath;
    if (path == null || _sending) return;
    setState(() { _pendingVoicePath = null; _sending = true; });
    setState(() => _msgs.add(_DriverChatMsg(text: '🎵 Voice message',
        isMe: true, type: _DriverMsgType.voice, voiceUrl: path)));
    _scrollToBottom();
    try {
      final form = FormData.fromMap({
        'message_type': 'voice',
        'voice': await MultipartFile.fromFile(path, filename: 'voice.m4a'),
      });
      await ApiClient.instance.post(
        '/delivery/orders/${widget.orderId}/chat',
        data: form,
        options: Options(
          contentType: 'multipart/form-data',
          sendTimeout: const Duration(seconds: 60),
        ),
      );
    } catch (e) {
      debugPrint('[DriverChat] voice send failed: $e');
      if (mounted) _showSnack('Failed to send voice message');
    }
    if (mounted) setState(() => _sending = false);
  }

  void _showSnack(String msg) => ScaffoldMessenger.of(context)
      .showSnackBar(SnackBar(content: Text(msg), duration: const Duration(seconds: 2)));

  @override
  void dispose() {
    _recordTimer?.cancel();
    _recorder.dispose();
    RealtimeService.instance.removeListener(_channel, 'new_message', _onRealtime);
    _ctrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    final name = (widget.customer?['name'] ?? 'Customer') as String;
    final phone = widget.customer?['phone']?.toString();

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
              decoration: BoxDecoration(
                  color: DC.success.withValues(alpha: 0.15), shape: BoxShape.circle),
              child: Center(child: Text(name.isNotEmpty ? name[0].toUpperCase() : 'C',
                style: const TextStyle(color: DC.success, fontWeight: FontWeight.w900, fontSize: 16)))),
            const SizedBox(width: 10),
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(name, style: TextStyle(fontWeight: FontWeight.w800, color: c.text)),
              Text('Customer', style: TextStyle(color: c.textMuted, fontSize: 11)),
            ]),
            const Spacer(),
            // Request location button
            IconButton(
              icon: const Icon(Icons.location_searching_rounded, color: DC.success),
              tooltip: 'Request location',
              onPressed: _sending ? null : _requestLocation,
            ),
            if (phone != null)
              IconButton(icon: const Icon(Icons.phone_rounded, color: DC.success),
                onPressed: () => launchUrl(Uri.parse('tel:$phone'))),
          ])),
          Divider(color: c.border),
          Expanded(child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _msgs.isEmpty
            ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.chat_bubble_outline_rounded, size: 48,
                    color: c.textMuted.withValues(alpha: 0.3)),
                const SizedBox(height: 8),
                Text('Send a message to your customer',
                    style: TextStyle(color: c.textMuted)),
              ]))
            : ListView.builder(
                controller: _scrollCtrl,
                padding: const EdgeInsets.all(16),
                itemCount: _msgs.length,
                itemBuilder: (_, i) => _buildMessage(_msgs[i], c),
              )),
          // Voice pending preview
          if (_pendingVoicePath != null)
            _DriverVoicePendingBar(
              path: _pendingVoicePath!,
              onSend: _sendVoice,
              onCancel: () => setState(() => _pendingVoicePath = null),
              c: context.dc,
            ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
              child: _recording
                ? _DriverRecordingBar(
                    seconds: _recordSeconds,
                    onStop: () => _stopRecording(),
                    onCancel: () => _stopRecording(cancel: true),
                    c: context.dc,
                  )
                : Row(children: [
                    Expanded(child: TextField(
                      controller: _ctrl,
                      style: TextStyle(color: c.text),
                      decoration: InputDecoration(
                        hintText: 'Message customer...',
                        hintStyle: TextStyle(color: c.textMuted),
                        filled: true,
                        fillColor: c.card,
                        border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(24),
                            borderSide: BorderSide.none),
                        contentPadding: const EdgeInsets.symmetric(
                            horizontal: 16, vertical: 10),
                      ),
                    )),
                    const SizedBox(width: 8),
                    // Mic or Send
                    ValueListenableBuilder(
                      valueListenable: _ctrl,
                      builder: (_, v, __) => v.text.isEmpty
                        ? GestureDetector(
                            onLongPressStart: (_) => _startRecording(),
                            onLongPressEnd: (_) => _stopRecording(),
                            child: Container(width: 44, height: 44,
                              decoration: const BoxDecoration(
                                  color: DC.orange, shape: BoxShape.circle),
                              child: const Icon(Icons.mic_rounded,
                                  color: Colors.white, size: 22)),
                          )
                        : GestureDetector(
                            onTap: _sendText,
                            child: Container(width: 44, height: 44,
                              decoration: const BoxDecoration(
                                  color: DC.orange, shape: BoxShape.circle),
                              child: const Icon(Icons.send_rounded,
                                  color: Colors.white, size: 20)),
                          ),
                    ),
                  ]),
            ),
          ),
        ]),
      ),
    );
  }

  Widget _buildMessage(_DriverChatMsg m, AppColors c) {
    switch (m.type) {
      case _DriverMsgType.voice:
        return Align(
          alignment: m.isMe ? Alignment.centerRight : Alignment.centerLeft,
          child: _DriverVoiceBubble(
            url: m.voiceUrl!,
            isMe: m.isMe,
            isLocal: !m.voiceUrl!.startsWith('http'),
            c: c,
          ),
        );
      case _DriverMsgType.location:
        return Align(
          alignment: m.isMe ? Alignment.centerRight : Alignment.centerLeft,
          child: _DriverLocationBubble(
            lat: m.lat!, lng: m.lng!, isMe: m.isMe, c: c),
        );
      case _DriverMsgType.locationRequest:
        return Align(
          alignment: m.isMe ? Alignment.centerRight : Alignment.centerLeft,
          child: Container(
            margin: const EdgeInsets.only(bottom: 8),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: DC.success.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: DC.success.withValues(alpha: 0.35)),
            ),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.location_searching_rounded, color: DC.success, size: 16),
              const SizedBox(width: 6),
              Text(m.isMe ? '📍 Location requested' : '📍 Customer shared location',
                  style: const TextStyle(color: DC.success, fontSize: 13,
                      fontWeight: FontWeight.w600)),
            ]),
          ),
        );
      default:
        return Align(
          alignment: m.isMe ? Alignment.centerRight : Alignment.centerLeft,
          child: Container(
            margin: const EdgeInsets.only(bottom: 8),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            constraints: const BoxConstraints(maxWidth: 260),
            decoration: BoxDecoration(
              color: m.isMe ? DC.orange : c.card,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Text(m.text,
                style: TextStyle(color: m.isMe ? Colors.white : c.text, fontSize: 14)),
          ),
        );
    }
  }
}

// ── Driver chat message model ─────────────────────────────────────────────────

enum _DriverMsgType { text, voice, location, locationRequest }

class _DriverChatMsg {
  final String text;
  final bool isMe;
  final _DriverMsgType type;
  final String? voiceUrl;
  final double? lat;
  final double? lng;

  const _DriverChatMsg({
    required this.text,
    required this.isMe,
    this.type = _DriverMsgType.text,
    this.voiceUrl,
    this.lat,
    this.lng,
  });

  factory _DriverChatMsg.fromMap(Map m) {
    final senderType = (m['sender_type'] ?? '') as String;
    final rawType = (m['message_type'] ?? 'text') as String;
    _DriverMsgType type;
    switch (rawType) {
      case 'voice':            type = _DriverMsgType.voice; break;
      case 'location':         type = _DriverMsgType.location; break;
      case 'location_request': type = _DriverMsgType.locationRequest; break;
      default:                 type = _DriverMsgType.text;
    }
    return _DriverChatMsg(
      text:     (m['message'] ?? '').toString(),
      isMe:     senderType == 'driver',
      type:     type,
      voiceUrl: m['voice_url'] as String?,
      lat:      m['lat'] != null ? double.tryParse('${m['lat']}') : null,
      lng:      m['lng'] != null ? double.tryParse('${m['lng']}') : null,
    );
  }
}

// ── Driver: Voice bubble ──────────────────────────────────────────────────────

class _DriverVoiceBubble extends StatefulWidget {
  final String url;
  final bool isMe;
  final bool isLocal;
  final AppColors c;
  const _DriverVoiceBubble({required this.url, required this.isMe,
      required this.isLocal, required this.c});
  @override
  State<_DriverVoiceBubble> createState() => _DriverVoiceBubbleState();
}

class _DriverVoiceBubbleState extends State<_DriverVoiceBubble> {
  final _player = AudioPlayer();
  bool _playing = false;
  Duration _position = Duration.zero;
  Duration _duration = Duration.zero;

  @override
  void initState() {
    super.initState();
    _player.onPlayerStateChanged.listen((s) {
      if (mounted) setState(() => _playing = s == PlayerState.playing);
    });
    _player.onPositionChanged.listen((p) {
      if (mounted) setState(() => _position = p);
    });
    _player.onDurationChanged.listen((d) {
      if (mounted) setState(() => _duration = d);
    });
    _player.onPlayerComplete.listen((_) {
      if (mounted) setState(() { _playing = false; _position = Duration.zero; });
    });
  }

  String _fmt(Duration d) {
    final m = d.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = d.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  Future<void> _toggle() async {
    if (_playing) {
      await _player.pause();
    } else {
      widget.isLocal
          ? await _player.play(DeviceFileSource(widget.url))
          : await _player.play(UrlSource(widget.url));
    }
  }

  @override
  void dispose() { _player.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final c = widget.c;
    final bgColor = widget.isMe ? DC.orange : c.card;
    final textColor = widget.isMe ? Colors.white : c.text;
    final accent = widget.isMe ? Colors.white70 : DC.orange;
    final total = _duration.inMilliseconds > 0 ? _duration.inMilliseconds.toDouble() : 1.0;
    final pos = _position.inMilliseconds.toDouble().clamp(0.0, total);

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(color: bgColor, borderRadius: BorderRadius.circular(16)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        GestureDetector(
          onTap: _toggle,
          child: Icon(_playing ? Icons.pause_circle_filled : Icons.play_circle_filled,
              color: accent, size: 36),
        ),
        const SizedBox(width: 8),
        SizedBox(
          width: 110,
          child: SliderTheme(
            data: SliderThemeData(
              trackHeight: 2,
              thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 5),
              overlayShape: SliderComponentShape.noOverlay,
              activeTrackColor: accent,
              inactiveTrackColor: accent.withValues(alpha: 0.25),
              thumbColor: accent,
            ),
            child: Slider(
              value: pos,
              min: 0,
              max: total,
              onChanged: (v) => _player.seek(Duration(milliseconds: v.toInt())),
            ),
          ),
        ),
        const SizedBox(width: 4),
        Text(_fmt(_playing ? _position : _duration),
            style: TextStyle(color: textColor, fontSize: 11)),
      ]),
    );
  }
}

// ── Driver: Location bubble ───────────────────────────────────────────────────

class _DriverLocationBubble extends StatelessWidget {
  final double lat, lng;
  final bool isMe;
  final AppColors c;
  const _DriverLocationBubble({required this.lat, required this.lng,
      required this.isMe, required this.c});

  @override
  Widget build(BuildContext context) {
    final bgColor = isMe ? DC.orange : c.navyLight;
    final label   = isMe ? 'My location' : "Customer's location";
    final subLabel = isMe ? 'Tap to view on map' : 'Tap to navigate to customer';

    return GestureDetector(
      onTap: () {
        // Driver side: open full in-app map showing customer + driver positions
        Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => _CustomerLocationMapScreen(
            customerLat: lat,
            customerLng: lng,
            title: isMe ? 'My Location' : "Customer's Location",
          ),
        ));
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        width: 240,
        decoration: BoxDecoration(
          color: bgColor,
          borderRadius: BorderRadius.circular(16),
          border: isMe ? null : Border.all(color: DC.success.withValues(alpha: 0.3)),
        ),
        clipBehavior: Clip.hardEdge,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Mini map preview via GoogleMap (non-interactive)
          SizedBox(
            height: 130,
            child: GoogleMap(
              initialCameraPosition: CameraPosition(
                target: LatLng(lat, lng),
                zoom: 15,
              ),
              markers: {
                Marker(
                  markerId: const MarkerId('customer'),
                  position: LatLng(lat, lng),
                  icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueRed),
                ),
              },
              myLocationButtonEnabled: false,
              myLocationEnabled: false,
              zoomControlsEnabled: false,
              scrollGesturesEnabled: false,
              zoomGesturesEnabled: false,
              tiltGesturesEnabled: false,
              rotateGesturesEnabled: false,
              liteModeEnabled: true,  // Lightweight static-like rendering
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(10, 8, 10, 10),
            child: Row(children: [
              Icon(Icons.location_on_rounded, size: 16,
                  color: isMe ? Colors.white70 : DC.success),
              const SizedBox(width: 6),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(label, style: TextStyle(
                    fontSize: 13, fontWeight: FontWeight.w700,
                    color: isMe ? Colors.white : c.text)),
                Text(subLabel, style: TextStyle(
                    fontSize: 11, color: isMe ? Colors.white60 : c.textMuted)),
              ])),
              Icon(Icons.chevron_right_rounded, size: 18,
                  color: isMe ? Colors.white60 : c.textMuted),
            ]),
          ),
        ]),
      ),
    );
  }
}

// ── Full-screen customer location map (DoorDash-style) ─────────────────────────

class _CustomerLocationMapScreen extends StatefulWidget {
  final double customerLat, customerLng;
  final String title;
  const _CustomerLocationMapScreen({
    required this.customerLat,
    required this.customerLng,
    required this.title,
  });

  @override
  State<_CustomerLocationMapScreen> createState() => _CustomerLocationMapScreenState();
}

class _CustomerLocationMapScreenState extends State<_CustomerLocationMapScreen> {
  GoogleMapController? _mapCtrl;
  LatLng? _driverPos;
  Set<Marker> _markers = {};
  Set<Polyline> _polylines = {};

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    // Customer marker
    final customerLatLng = LatLng(widget.customerLat, widget.customerLng);
    final markers = <Marker>{
      Marker(
        markerId: const MarkerId('customer'),
        position: customerLatLng,
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueRed),
        infoWindow: const InfoWindow(title: 'Customer'),
      ),
    };

    // Get driver's current location
    try {
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
      );
      final driverLatLng = LatLng(pos.latitude, pos.longitude);
      markers.add(Marker(
        markerId: const MarkerId('driver'),
        position: driverLatLng,
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueBlue),
        infoWindow: const InfoWindow(title: 'You'),
      ));

      // Draw route line between driver and customer
      final polylines = <Polyline>{
        Polyline(
          polylineId: const PolylineId('route'),
          points: [driverLatLng, customerLatLng],
          color: const Color(0xFF1A73E8),
          width: 4,
          patterns: [PatternItem.dash(20), PatternItem.gap(10)],
        ),
      };

      if (mounted) {
        setState(() {
          _driverPos = driverLatLng;
          _markers = markers;
          _polylines = polylines;
        });

        // Fit camera to show both markers
        final bounds = LatLngBounds(
          southwest: LatLng(
            pos.latitude < widget.customerLat ? pos.latitude : widget.customerLat,
            pos.longitude < widget.customerLng ? pos.longitude : widget.customerLng,
          ),
          northeast: LatLng(
            pos.latitude > widget.customerLat ? pos.latitude : widget.customerLat,
            pos.longitude > widget.customerLng ? pos.longitude : widget.customerLng,
          ),
        );
        await Future.delayed(const Duration(milliseconds: 500));
        _mapCtrl?.animateCamera(CameraUpdate.newLatLngBounds(bounds, 80));
      }
    } catch (_) {
      // GPS unavailable — just show customer marker
      if (mounted) setState(() { _markers = markers; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Scaffold(
      backgroundColor: c.navy,
      appBar: AppBar(
        backgroundColor: c.navyLight,
        title: Text(widget.title,
            style: TextStyle(color: c.text, fontWeight: FontWeight.w700)),
        iconTheme: IconThemeData(color: c.text),
        actions: [
          // Open in Google Maps for navigation
          IconButton(
            icon: const Icon(Icons.navigation_rounded, color: DC.orange),
            tooltip: 'Navigate',
            onPressed: () async {
              final uri = Uri.parse(
                'https://www.google.com/maps/dir/?api=1'
                '&destination=${widget.customerLat},${widget.customerLng}'
                '&travelmode=driving',
              );
              if (await canLaunchUrl(uri)) launchUrl(uri, mode: LaunchMode.externalApplication);
            },
          ),
        ],
      ),
      body: Stack(children: [
        GoogleMap(
          onMapCreated: (ctrl) => _mapCtrl = ctrl,
          initialCameraPosition: CameraPosition(
            target: LatLng(widget.customerLat, widget.customerLng),
            zoom: 14,
          ),
          markers: _markers,
          polylines: _polylines,
          myLocationEnabled: true,
          myLocationButtonEnabled: true,
          zoomControlsEnabled: true,
          mapType: MapType.normal,
        ),
        // Bottom info bar
        Positioned(
          left: 0, right: 0, bottom: 0,
          child: Container(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
            decoration: BoxDecoration(
              color: c.navyLight,
              borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
              boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.3), blurRadius: 12)],
            ),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Container(width: 40, height: 4,
                  decoration: BoxDecoration(color: c.border, borderRadius: BorderRadius.circular(2))),
              const SizedBox(height: 14),
              Row(children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: DC.success.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.location_on_rounded, color: DC.success, size: 22),
                ),
                const SizedBox(width: 14),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text("Customer's Location",
                      style: TextStyle(color: c.text,
                          fontWeight: FontWeight.w700, fontSize: 15)),
                  const SizedBox(height: 2),
                  Text('${widget.customerLat.toStringAsFixed(5)}, ${widget.customerLng.toStringAsFixed(5)}',
                      style: TextStyle(color: c.textMuted, fontSize: 12)),
                ])),
                if (_driverPos != null) ...[
                  const SizedBox(width: 12),
                  Column(children: [
                    Text(_distanceText(), style: TextStyle(
                        color: DC.orange, fontWeight: FontWeight.w800, fontSize: 16)),
                    Text('away', style: TextStyle(color: c.textMuted, fontSize: 11)),
                  ]),
                ],
              ]),
              const SizedBox(height: 14),
              SizedBox(width: double.infinity,
                child: ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: DC.orange,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  icon: const Icon(Icons.navigation_rounded, color: Colors.white),
                  label: const Text('Start Navigation',
                      style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 15)),
                  onPressed: () async {
                    final uri = Uri.parse(
                      'https://www.google.com/maps/dir/?api=1'
                      '&destination=${widget.customerLat},${widget.customerLng}'
                      '&travelmode=driving',
                    );
                    if (await canLaunchUrl(uri)) launchUrl(uri, mode: LaunchMode.externalApplication);
                  },
                ),
              ),
            ]),
          ),
        ),
      ]),
    );
  }

  String _distanceText() {
    if (_driverPos == null) return '';
    final dist = Geolocator.distanceBetween(
      _driverPos!.latitude, _driverPos!.longitude,
      widget.customerLat, widget.customerLng,
    );
    if (dist < 1000) return '${dist.toStringAsFixed(0)}m';
    return '${(dist / 1000).toStringAsFixed(1)}km';
  }

  @override
  void dispose() {
    _mapCtrl?.dispose();
    super.dispose();
  }
}

// ── Driver: Voice pending bar ─────────────────────────────────────────────────

class _DriverVoicePendingBar extends StatelessWidget {
  final String path;
  final VoidCallback onSend;
  final VoidCallback onCancel;
  final AppColors c;
  const _DriverVoicePendingBar({required this.path, required this.onSend,
      required this.onCancel, required this.c});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      color: DC.orange.withValues(alpha: 0.08),
      child: Row(children: [
        const Icon(Icons.mic_rounded, color: DC.orange, size: 20),
        const SizedBox(width: 8),
        Expanded(child: _DriverVoiceBubble(url: path, isMe: true, isLocal: true, c: c)),
        const SizedBox(width: 8),
        GestureDetector(onTap: onCancel,
            child: const Icon(Icons.close, color: Colors.red, size: 22)),
        const SizedBox(width: 12),
        GestureDetector(
          onTap: onSend,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
            decoration: BoxDecoration(color: DC.orange, borderRadius: BorderRadius.circular(20)),
            child: const Text('Send', style: TextStyle(color: Colors.white,
                fontWeight: FontWeight.w700, fontSize: 13)),
          ),
        ),
      ]),
    );
  }
}

// ── Driver: Recording bar ─────────────────────────────────────────────────────

class _DriverRecordingBar extends StatelessWidget {
  final int seconds;
  final VoidCallback onStop;
  final VoidCallback onCancel;
  final AppColors c;
  const _DriverRecordingBar({required this.seconds, required this.onStop,
      required this.onCancel, required this.c});

  @override
  Widget build(BuildContext context) {
    final m = (seconds ~/ 60).toString().padLeft(2, '0');
    final s = (seconds % 60).toString().padLeft(2, '0');
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.red.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(30),
      ),
      child: Row(children: [
        GestureDetector(onTap: onCancel,
            child: const Icon(Icons.delete_outline_rounded, color: Colors.red, size: 22)),
        const SizedBox(width: 8),
        const Icon(Icons.fiber_manual_record, color: Colors.red, size: 14),
        const SizedBox(width: 6),
        Text('$m:$s', style: const TextStyle(
            color: Colors.red, fontWeight: FontWeight.w700, fontSize: 15)),
        const Spacer(),
        GestureDetector(
          onTap: onStop,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
            decoration: BoxDecoration(color: Colors.red, borderRadius: BorderRadius.circular(20)),
            child: const Text('Stop & Preview',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
          ),
        ),
      ]),
    );
  }
}
