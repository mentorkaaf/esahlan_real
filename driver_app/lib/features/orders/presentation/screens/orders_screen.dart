import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:image_picker/image_picker.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:dio/dio.dart';
import 'dart:io';
import '../../../../core/theme/driver_colors.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/storage/local_storage.dart';
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
  @override
  void initState() { super.initState(); _tabs = TabController(length: 2, vsync: this); }
  @override
  void dispose() { _tabs.dispose(); super.dispose(); }

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
        _AvailableTab(onAccepted: () => _tabs.animateTo(1)),
        const _ActiveTab(),
      ]),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════════
// AVAILABLE ORDERS TAB
// ══════════════════════════════════════════════════════════════════════════════

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
          ? _emptyState('No available orders', 'Pull down to refresh', Icons.inbox_rounded)
          : RefreshIndicator(
              color: DC.orange,
              onRefresh: () async => ref.invalidate(_availableProvider),
              child: ListView.builder(
                padding: const EdgeInsets.all(14),
                itemCount: list.length,
                itemBuilder: (_, i) => _AvailableOrderCard(
                  order: list[i],
                  onAccept: () async {
                    try {
                      await ref.read(authRepoProvider).acceptOrder((list[i]['id'] as num).toInt());
                      ref.invalidate(_availableProvider);
                      ref.invalidate(_activeProvider);
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

// ══════════════════════════════════════════════════════════════════════════════
// ACTIVE ORDERS TAB
// ══════════════════════════════════════════════════════════════════════════════

class _ActiveTab extends ConsumerWidget {
  const _ActiveTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final orders = ref.watch(_activeProvider);
    return orders.when(
      loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
      error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: DC.error))),
      data: (list) => list.isEmpty
          ? _emptyState('No active deliveries', 'Accept orders to start delivering', Icons.check_circle_outline_rounded)
          : RefreshIndicator(
              color: DC.orange,
              onRefresh: () async => ref.invalidate(_activeProvider),
              child: ListView.builder(
                padding: const EdgeInsets.all(14),
                itemCount: list.length,
                itemBuilder: (_, i) => _ActiveOrderCard(order: list[i]),
              ),
            ),
    );
  }
}

Widget _emptyState(String title, String sub, IconData icon) => Center(
  child: Column(mainAxisSize: MainAxisSize.min, children: [
    Icon(icon, size: 60, color: DC.textMuted.withValues(alpha: 0.3)),
    const SizedBox(height: 14),
    Text(title, style: const TextStyle(color: DC.textMuted, fontSize: 16, fontWeight: FontWeight.w700)),
    const SizedBox(height: 4),
    Text(sub, style: const TextStyle(color: DC.textMuted, fontSize: 12)),
  ]),
);

// ══════════════════════════════════════════════════════════════════════════════
// AVAILABLE ORDER CARD
// ══════════════════════════════════════════════════════════════════════════════

class _AvailableOrderCard extends StatelessWidget {
  final Map<String, dynamic> order;
  final VoidCallback onAccept;
  const _AvailableOrderCard({required this.order, required this.onAccept});

  static const _moduleColors = {
    'efood': Color(0xFFFF8A00), 'eshop': Color(0xFF3B82F6), 'eparcel': Color(0xFF8B5CF6),
    'egrocery': Color(0xFF10B981), 'elaundry': Color(0xFF06B6D4), 'emoving': Color(0xFFEF4444),
  };

  @override
  Widget build(BuildContext context) {
    final vendor = order['vendor'] as Map<String, dynamic>?;
    final customer = order['customer'] as Map<String, dynamic>?;
    final module = (order['module_slug'] ?? 'order').toString();
    final fee = double.tryParse('${order['delivery_fee'] ?? 0}') ?? 0;
    final total = double.tryParse('${order['total_amount'] ?? 0}') ?? 0;
    final distance = order['distance_km'];
    final color = _moduleColors[module] ?? DC.orange;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: DC.card, borderRadius: BorderRadius.circular(18),
        border: Border.all(color: DC.border.withValues(alpha: 0.5)),
      ),
      child: Column(children: [
        // Header
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.06),
            borderRadius: const BorderRadius.vertical(top: Radius.circular(18)),
          ),
          child: Row(children: [
            Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(color: color.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(8)),
              child: Text(module.toUpperCase(), style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w800, letterSpacing: 0.5))),
            const Spacer(),
            Text('#${order['order_number'] ?? ''}', style: const TextStyle(color: DC.textSec, fontSize: 12, fontWeight: FontWeight.w600)),
          ]),
        ),

        Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Pickup
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Column(children: [
              Container(width: 10, height: 10, decoration: BoxDecoration(color: DC.orange, shape: BoxShape.circle, border: Border.all(color: Colors.white, width: 2))),
              Container(width: 2, height: 24, color: DC.border),
              Container(width: 10, height: 10, decoration: BoxDecoration(color: DC.success, shape: BoxShape.circle, border: Border.all(color: Colors.white, width: 2))),
            ]),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('PICKUP', style: TextStyle(color: DC.textMuted, fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 0.5)),
              Text(vendor?['name'] ?? 'Vendor', style: const TextStyle(color: DC.text, fontWeight: FontWeight.w700, fontSize: 14)),
              if (vendor?['address'] != null) Text(vendor!['address'], style: const TextStyle(color: DC.textMuted, fontSize: 11), maxLines: 1, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 8),
              Text('DELIVERY', style: TextStyle(color: DC.textMuted, fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 0.5)),
              Text(customer?['name'] ?? 'Customer', style: const TextStyle(color: DC.text, fontWeight: FontWeight.w600, fontSize: 13)),
              if (order['delivery_address'] is Map) Text((order['delivery_address'] as Map)['district'] ?? '', style: const TextStyle(color: DC.textMuted, fontSize: 11)),
            ])),
          ]),
          const SizedBox(height: 14),

          // Info row
          Row(children: [
            if (distance != null) _InfoPill(Icons.route_rounded, '${distance} km'),
            _InfoPill(Icons.attach_money_rounded, '\$${total.toStringAsFixed(2)}'),
            _InfoPill(Icons.delivery_dining_rounded, '\$${fee.toStringAsFixed(2)} fee'),
          ]),
          const SizedBox(height: 14),

          // Accept button
          SizedBox(width: double.infinity, height: 48, child: ElevatedButton(
            onPressed: onAccept,
            style: ElevatedButton.styleFrom(backgroundColor: DC.orange, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
            child: const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
              Icon(Icons.check_circle_rounded, size: 20),
              SizedBox(width: 8),
              Text('Accept Order', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
            ]),
          )),
        ])),
      ]),
    );
  }
}

class _InfoPill extends StatelessWidget {
  final IconData icon; final String text;
  const _InfoPill(this.icon, this.text);
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(right: 8),
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
    decoration: BoxDecoration(color: DC.surface, borderRadius: BorderRadius.circular(8)),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, color: DC.textMuted, size: 14),
      const SizedBox(width: 4),
      Text(text, style: const TextStyle(color: DC.textSec, fontSize: 12, fontWeight: FontWeight.w600)),
    ]),
  );
}

// ══════════════════════════════════════════════════════════════════════════════
// ACTIVE ORDER CARD — tap to open detail
// ══════════════════════════════════════════════════════════════════════════════

class _ActiveOrderCard extends ConsumerWidget {
  final Map<String, dynamic> order;
  const _ActiveOrderCard({required this.order});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final vendor = order['vendor'] as Map<String, dynamic>?;
    final customer = order['customer'] as Map<String, dynamic>?;
    final module = (order['module_slug'] ?? 'order').toString();
    final fee = double.tryParse('${order['delivery_fee'] ?? 0}') ?? 0;
    final status = order['status']?.toString() ?? '';
    final nextStatus = status == 'out_for_delivery' ? 'delivered' : 'out_for_delivery';
    final btnLabel = status == 'out_for_delivery' ? '✓ Mark Delivered' : 'Picked Up — Start Delivery';
    final btnColor = status == 'out_for_delivery' ? DC.success : DC.orange;

    return GestureDetector(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => _OrderDetailPage(order: order))),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: DC.card, borderRadius: BorderRadius.circular(18),
          border: Border.all(color: DC.orange.withValues(alpha: 0.3), width: 1.5),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Container(width: 10, height: 10, decoration: BoxDecoration(color: DC.orange, shape: BoxShape.circle, boxShadow: [BoxShadow(color: DC.orange.withValues(alpha: 0.5), blurRadius: 6)])),
            const SizedBox(width: 8),
            Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3), decoration: BoxDecoration(color: DC.orangeDim, borderRadius: BorderRadius.circular(6)),
              child: Text(module.toUpperCase(), style: const TextStyle(color: DC.orange, fontSize: 10, fontWeight: FontWeight.w800))),
            const Spacer(),
            Text('#${order['order_number'] ?? ''}', style: const TextStyle(color: DC.textSec, fontSize: 12, fontWeight: FontWeight.w600)),
          ]),
          const SizedBox(height: 12),

          // Vendor
          Row(children: [
            const Icon(Icons.store_rounded, color: DC.orange, size: 16),
            const SizedBox(width: 6),
            Expanded(child: Text(vendor?['name'] ?? '', style: const TextStyle(color: DC.text, fontWeight: FontWeight.w700, fontSize: 14))),
          ]),
          const SizedBox(height: 4),
          // Customer
          Row(children: [
            const Icon(Icons.person_rounded, color: DC.textMuted, size: 16),
            const SizedBox(width: 6),
            Text(customer?['name'] ?? '', style: const TextStyle(color: DC.textSec, fontSize: 13)),
            const Spacer(),
            if (customer?['phone'] != null)
              GestureDetector(
                onTap: () => launchUrl(Uri.parse('tel:${customer!['phone']}')),
                child: Container(padding: const EdgeInsets.all(6), decoration: BoxDecoration(color: DC.success.withValues(alpha: 0.15), shape: BoxShape.circle),
                  child: const Icon(Icons.phone, color: DC.success, size: 16)),
              ),
          ]),
          const SizedBox(height: 12),

          // Fee + Navigate
          Row(children: [
            Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
              decoration: BoxDecoration(color: DC.success.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(8)),
              child: Text('\$${fee.toStringAsFixed(2)} fee', style: const TextStyle(color: DC.success, fontWeight: FontWeight.w800, fontSize: 13))),
            const SizedBox(width: 8),
            if (vendor?['lat'] != null) GestureDetector(
              onTap: () => launchUrl(Uri.parse('https://www.google.com/maps/dir/?api=1&destination=${vendor!['lat']},${vendor!['lng']}&travelmode=driving')),
              child: Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(color: const Color(0xFF3B82F6).withValues(alpha: 0.12), borderRadius: BorderRadius.circular(8)),
                child: const Row(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.navigation_rounded, color: Color(0xFF3B82F6), size: 14),
                  SizedBox(width: 4),
                  Text('Navigate', style: TextStyle(color: Color(0xFF3B82F6), fontWeight: FontWeight.w700, fontSize: 12)),
                ])),
            ),
            const Spacer(),
            const Icon(Icons.chevron_right_rounded, color: DC.textMuted, size: 20),
          ]),
          const SizedBox(height: 12),

          // Status action button
          SizedBox(width: double.infinity, height: 46, child: ElevatedButton(
            onPressed: () async {
              // For delivered, require photo
              if (nextStatus == 'delivered') {
                Navigator.push(context, MaterialPageRoute(builder: (_) => _DeliverConfirmPage(order: order)));
                return;
              }
              try {
                await ref.read(authRepoProvider).updateOrderStatus((order['id'] as num).toInt(), nextStatus);
                ref.invalidate(_activeProvider);
                if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(btnLabel), backgroundColor: DC.success));
              } catch (e) {
                if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: DC.error));
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: btnColor, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
            child: Text(btnLabel, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
          )),
        ]),
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════════
// ORDER DETAIL PAGE — full screen with map
// ══════════════════════════════════════════════════════════════════════════════

class _OrderDetailPage extends StatelessWidget {
  final Map<String, dynamic> order;
  const _OrderDetailPage({required this.order});

  @override
  Widget build(BuildContext context) {
    final vendor = order['vendor'] as Map<String, dynamic>?;
    final customer = order['customer'] as Map<String, dynamic>?;
    final module = (order['module_slug'] ?? 'order').toString();
    final fee = double.tryParse('${order['delivery_fee'] ?? 0}') ?? 0;
    final total = double.tryParse('${order['total_amount'] ?? 0}') ?? 0;
    final vLat = double.tryParse('${vendor?['lat'] ?? ''}');
    final vLng = double.tryParse('${vendor?['lng'] ?? ''}');
    final addr = order['delivery_address'];
    final district = addr is Map ? (addr['district'] ?? addr['city'] ?? '') : '';

    final hasVendorLoc = vLat != null && vLng != null && vLat != 0;

    return Scaffold(
      backgroundColor: DC.navy,
      appBar: AppBar(backgroundColor: DC.navyLight, title: Text('#${order['order_number'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w800))),
      body: ListView(children: [
        // Map
        if (hasVendorLoc) SizedBox(height: 220, child: GoogleMap(
          initialCameraPosition: CameraPosition(target: LatLng(vLat!, vLng!), zoom: 14),
          markers: {
            Marker(markerId: const MarkerId('vendor'), position: LatLng(vLat, vLng!),
              infoWindow: InfoWindow(title: vendor?['name'] ?? 'Pickup')),
          },
          myLocationEnabled: true, myLocationButtonEnabled: false,
          zoomControlsEnabled: false, mapToolbarEnabled: false,
        )),

        Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Module + Status
          Row(children: [
            Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(color: DC.orangeDim, borderRadius: BorderRadius.circular(8)),
              child: Text(module.toUpperCase(), style: const TextStyle(color: DC.orange, fontSize: 11, fontWeight: FontWeight.w800))),
            const SizedBox(width: 8),
            Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(color: DC.success.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(8)),
              child: Text(order['status']?.toString().replaceAll('_', ' ').toUpperCase() ?? '', style: const TextStyle(color: DC.success, fontSize: 10, fontWeight: FontWeight.w700))),
          ]),
          const SizedBox(height: 20),

          // Pickup section
          _DetailSection(icon: Icons.store_rounded, color: DC.orange, title: 'Pickup Location', children: [
            _DetailRow('Restaurant', vendor?['name'] ?? '—'),
            _DetailRow('Address', vendor?['address'] ?? '—'),
            if (vendor?['phone'] != null) _DetailRow('Phone', vendor!['phone']),
          ]),
          const SizedBox(height: 14),

          // Delivery section
          _DetailSection(icon: Icons.location_on_rounded, color: DC.success, title: 'Delivery Location', children: [
            _DetailRow('Customer', customer?['name'] ?? '—'),
            if (customer?['phone'] != null) _DetailRow('Phone', customer!['phone']),
            _DetailRow('District', district.toString()),
          ]),
          const SizedBox(height: 14),

          // Pricing section
          _DetailSection(icon: Icons.receipt_rounded, color: const Color(0xFF3B82F6), title: 'Order Details', children: [
            _DetailRow('Order Total', '\$${total.toStringAsFixed(2)}'),
            _DetailRow('Delivery Fee', '\$${fee.toStringAsFixed(2)}', valueColor: DC.success),
            if (order['items_count'] != null) _DetailRow('Items', '${order['items_count']}'),
            _DetailRow('Placed', order['placed_at'] ?? order['created_at'] ?? '—'),
          ]),
          const SizedBox(height: 20),

          // Navigate button
          if (hasVendorLoc) SizedBox(width: double.infinity, height: 52, child: ElevatedButton.icon(
            onPressed: () => launchUrl(Uri.parse('https://www.google.com/maps/dir/?api=1&destination=$vLat,$vLng&travelmode=driving')),
            icon: const Icon(Icons.navigation_rounded),
            label: const Text('Navigate to Pickup', style: TextStyle(fontWeight: FontWeight.w800)),
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF3B82F6), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
          )),

          // Call buttons
          const SizedBox(height: 12),
          Row(children: [
            if (vendor?['phone'] != null) Expanded(child: OutlinedButton.icon(
              onPressed: () => launchUrl(Uri.parse('tel:${vendor!['phone']}')),
              icon: const Icon(Icons.store_rounded, size: 18),
              label: const Text('Call Vendor'),
              style: OutlinedButton.styleFrom(foregroundColor: DC.orange, side: const BorderSide(color: DC.orange), padding: const EdgeInsets.symmetric(vertical: 12)),
            )),
            if (vendor?['phone'] != null && customer?['phone'] != null) const SizedBox(width: 10),
            if (customer?['phone'] != null) Expanded(child: OutlinedButton.icon(
              onPressed: () => launchUrl(Uri.parse('tel:${customer!['phone']}')),
              icon: const Icon(Icons.person_rounded, size: 18),
              label: const Text('Call Customer'),
              style: OutlinedButton.styleFrom(foregroundColor: DC.success, side: const BorderSide(color: DC.success), padding: const EdgeInsets.symmetric(vertical: 12)),
            )),
          ]),
        ])),
      ]),
    );
  }
}

class _DetailSection extends StatelessWidget {
  final IconData icon; final Color color; final String title; final List<Widget> children;
  const _DetailSection({required this.icon, required this.color, required this.title, required this.children});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(16), border: Border.all(color: DC.border.withValues(alpha: 0.3))),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Icon(icon, color: color, size: 18), const SizedBox(width: 8),
        Text(title, style: TextStyle(color: color, fontWeight: FontWeight.w700, fontSize: 14)),
      ]),
      const SizedBox(height: 12),
      ...children,
    ]),
  );
}

class _DetailRow extends StatelessWidget {
  final String label, value; final Color? valueColor;
  const _DetailRow(this.label, this.value, {this.valueColor});
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: const TextStyle(color: DC.textMuted, fontSize: 13)),
      Flexible(child: Text(value, style: TextStyle(color: valueColor ?? DC.text, fontWeight: FontWeight.w600, fontSize: 13), textAlign: TextAlign.end)),
    ]),
  );
}

// ══════════════════════════════════════════════════════════════════════════════
// DELIVERY CONFIRMATION PAGE — photo required
// ══════════════════════════════════════════════════════════════════════════════

class _DeliverConfirmPage extends ConsumerStatefulWidget {
  final Map<String, dynamic> order;
  const _DeliverConfirmPage({required this.order});
  @override
  ConsumerState<_DeliverConfirmPage> createState() => _DeliverConfirmState();
}

class _DeliverConfirmState extends ConsumerState<_DeliverConfirmPage> {
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
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: DC.error));
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: DC.navy,
      appBar: AppBar(backgroundColor: DC.navyLight, title: const Text('Confirm Delivery', style: TextStyle(fontWeight: FontWeight.w800))),
      body: Padding(padding: const EdgeInsets.all(24), child: Column(children: [
        const Icon(Icons.camera_alt_rounded, color: DC.orange, size: 48),
        const SizedBox(height: 16),
        const Text('Take a delivery photo', style: TextStyle(color: DC.text, fontSize: 20, fontWeight: FontWeight.w800)),
        const SizedBox(height: 6),
        const Text('Photo confirms successful delivery to admin', style: TextStyle(color: DC.textSec, fontSize: 13)),
        const SizedBox(height: 24),

        // Photo preview
        GestureDetector(
          onTap: _pickPhoto,
          child: Container(
            width: double.infinity, height: 200,
            decoration: BoxDecoration(
              color: DC.surface, borderRadius: BorderRadius.circular(18),
              border: Border.all(color: _photo != null ? DC.success : DC.border, width: 2),
            ),
            child: _photo != null
                ? ClipRRect(borderRadius: BorderRadius.circular(16), child: Image.file(_photo!, fit: BoxFit.cover))
                : Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Icon(Icons.add_a_photo_rounded, color: DC.textMuted, size: 40),
                    const SizedBox(height: 8),
                    const Text('Tap to take photo', style: TextStyle(color: DC.textMuted)),
                  ]),
          ),
        ),
        const Spacer(),

        // Confirm button
        SizedBox(width: double.infinity, height: 56, child: ElevatedButton(
          onPressed: _loading ? null : _confirm,
          style: ElevatedButton.styleFrom(backgroundColor: DC.success, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16))),
          child: _loading
              ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
              : const Text('Complete Delivery', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
        )),
        const SizedBox(height: 12),
        TextButton(
          onPressed: _loading ? null : _confirm,
          child: const Text('Skip photo', style: TextStyle(color: DC.textMuted)),
        ),
      ])),
    );
  }
}
