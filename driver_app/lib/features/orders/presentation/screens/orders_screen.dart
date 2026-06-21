import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../../core/theme/driver_colors.dart';
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
        title: const Text('Orders'),
        bottom: TabBar(controller: _tabs, indicatorColor: DC.orange, labelColor: DC.orange, unselectedLabelColor: DC.textMuted,
          tabs: const [Tab(text: 'Available'), Tab(text: 'Active')]),
      ),
      body: TabBarView(controller: _tabs, children: [
        _AvailableTab(),
        _ActiveTab(),
      ]),
    );
  }
}

class _AvailableTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final orders = ref.watch(_availableProvider);
    return orders.when(
      loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
      error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: DC.error))),
      data: (list) => list.isEmpty
          ? const Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
              Icon(Icons.delivery_dining_rounded, size: 60, color: DC.textMuted),
              SizedBox(height: 12),
              Text('No available orders', style: TextStyle(color: DC.textMuted, fontSize: 15)),
            ]))
          : RefreshIndicator(
              color: DC.orange,
              onRefresh: () async => ref.invalidate(_availableProvider),
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: list.length,
                itemBuilder: (_, i) => _OrderCard(order: list[i], showAccept: true, onAccept: () async {
                  try {
                    await ref.read(authRepoProvider).acceptOrder((list[i]['id'] as num).toInt());
                    ref.invalidate(_availableProvider);
                    ref.invalidate(_activeProvider);
                    if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Order accepted!'), backgroundColor: DC.success));
                  } catch (e) {
                    if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: DC.error));
                  }
                }),
              ),
            ),
    );
  }
}

class _ActiveTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final orders = ref.watch(_activeProvider);
    return orders.when(
      loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
      error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: DC.error))),
      data: (list) => list.isEmpty
          ? const Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
              Icon(Icons.check_circle_outline_rounded, size: 60, color: DC.textMuted),
              SizedBox(height: 12),
              Text('No active deliveries', style: TextStyle(color: DC.textMuted, fontSize: 15)),
            ]))
          : RefreshIndicator(
              color: DC.orange,
              onRefresh: () async => ref.invalidate(_activeProvider),
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: list.length,
                itemBuilder: (_, i) {
                  final o = list[i];
                  final nextStatus = _nextStatus(o['status']);
                  return _OrderCard(order: o, showAccept: false, statusAction: nextStatus, onStatusUpdate: () async {
                    if (nextStatus == null) return;
                    try {
                      await ref.read(authRepoProvider).updateOrderStatus((o['id'] as num).toInt(), nextStatus);
                      ref.invalidate(_activeProvider);
                      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Status → ${_statusLabel(nextStatus)}'), backgroundColor: DC.success));
                    } catch (e) {
                      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: DC.error));
                    }
                  });
                },
              ),
            ),
    );
  }

  String? _nextStatus(String? status) => switch (status) {
    'ready_for_pickup' || 'confirmed' || 'preparing' => 'out_for_delivery',
    'out_for_delivery' => 'delivered',
    _ => null,
  };

  String _statusLabel(String s) => switch (s) {
    'out_for_delivery' => 'Picked Up & Delivering',
    'delivered' => 'Delivered',
    _ => s,
  };
}

class _OrderCard extends StatelessWidget {
  final Map<String, dynamic> order;
  final bool showAccept;
  final String? statusAction;
  final VoidCallback? onAccept;
  final VoidCallback? onStatusUpdate;

  const _OrderCard({required this.order, this.showAccept = false, this.statusAction, this.onAccept, this.onStatusUpdate});

  @override
  Widget build(BuildContext context) {
    final vendor = order['vendor'] as Map<String, dynamic>?;
    final customer = order['customer'] as Map<String, dynamic>?;
    final module = (order['module_slug'] ?? 'order').toString();
    final fee = double.tryParse('${order['delivery_fee'] ?? 0}') ?? 0;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(16), border: Border.all(color: DC.border)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(color: DC.orangeDim, borderRadius: BorderRadius.circular(6)),
            child: Text(module.toUpperCase(), style: const TextStyle(color: DC.orange, fontSize: 10, fontWeight: FontWeight.w800))),
          const Spacer(),
          Text('#${order['order_number'] ?? ''}', style: const TextStyle(color: DC.textSec, fontSize: 12, fontWeight: FontWeight.w600)),
        ]),
        const SizedBox(height: 12),
        if (vendor != null) Row(children: [
          const Icon(Icons.store_rounded, color: DC.orange, size: 16),
          const SizedBox(width: 6),
          Expanded(child: Text(vendor['name'] ?? '', style: const TextStyle(color: DC.text, fontWeight: FontWeight.w700, fontSize: 14))),
        ]),
        const SizedBox(height: 4),
        if (customer != null) Row(children: [
          const Icon(Icons.person_rounded, color: DC.textMuted, size: 16),
          const SizedBox(width: 6),
          Text(customer['name'] ?? '', style: const TextStyle(color: DC.textSec, fontSize: 13)),
          const Spacer(),
          if (customer['phone'] != null)
            GestureDetector(
              onTap: () => launchUrl(Uri.parse('tel:${customer['phone']}')),
              child: Container(padding: const EdgeInsets.all(6), decoration: BoxDecoration(color: DC.success.withValues(alpha: 0.15), shape: BoxShape.circle),
                child: const Icon(Icons.phone, color: DC.success, size: 16)),
            ),
        ]),
        if (order['distance_km'] != null) ...[
          const SizedBox(height: 4),
          Row(children: [
            const Icon(Icons.route_rounded, color: DC.textMuted, size: 16),
            const SizedBox(width: 6),
            Text('${order['distance_km']} km', style: const TextStyle(color: DC.textSec, fontSize: 13)),
          ]),
        ],
        const SizedBox(height: 14),

        // Actions row
        Row(children: [
          Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration: BoxDecoration(color: DC.success.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(8)),
            child: Text('\$${fee.toStringAsFixed(2)}', style: const TextStyle(color: DC.success, fontWeight: FontWeight.w800, fontSize: 14))),
          const SizedBox(width: 8),

          // Navigate button for active orders
          if (!showAccept && vendor != null && vendor['lat'] != null)
            GestureDetector(
              onTap: () {
                final lat = vendor['lat'];
                final lng = vendor['lng'];
                launchUrl(Uri.parse('https://www.google.com/maps/dir/?api=1&destination=$lat,$lng&travelmode=driving'));
              },
              child: Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(color: const Color(0xFF3B82F6).withValues(alpha: 0.15), borderRadius: BorderRadius.circular(8)),
                child: const Row(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.navigation_rounded, color: Color(0xFF3B82F6), size: 14),
                  SizedBox(width: 4),
                  Text('Navigate', style: TextStyle(color: Color(0xFF3B82F6), fontWeight: FontWeight.w700, fontSize: 12)),
                ])),
            ),

          const Spacer(),
          if (showAccept) ElevatedButton(onPressed: onAccept, style: ElevatedButton.styleFrom(minimumSize: const Size(120, 40)),
            child: const Text('Accept')),
          if (statusAction != null) ElevatedButton(
            onPressed: onStatusUpdate,
            style: ElevatedButton.styleFrom(
              minimumSize: const Size(140, 40),
              backgroundColor: statusAction == 'delivered' ? DC.success : DC.orange,
            ),
            child: Text(_btnLabel(statusAction!)),
          ),
        ]),
      ]),
    );
  }

  String _btnLabel(String s) => switch (s) {
    'out_for_delivery' => 'Picked Up — Start Delivery',
    'delivered' => '✓ Mark Delivered',
    _ => s,
  };
}
