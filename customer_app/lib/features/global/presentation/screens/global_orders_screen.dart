import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/global_provider.dart';
import '../../data/models/global_models.dart';

class GlobalOrdersScreen extends ConsumerWidget {
  const GlobalOrdersScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(globalAuthProvider).valueOrNull;
    if (auth == null) {
      return Scaffold(
        appBar: AppBar(
            title: const Text('My Orders'),
            backgroundColor: const Color(0xFF1A1A2E),
            foregroundColor: Colors.white),
        body: Center(
          child: ElevatedButton(
            onPressed: () => context.push('/global/auth'),
            child: const Text('Sign In to View Orders'),
          ),
        ),
      );
    }

    final ordersAsync = ref.watch(globalOrdersProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF0F2F5),
      appBar: AppBar(
        title: const Text('My Orders',
            style: TextStyle(fontWeight: FontWeight.w700)),
        backgroundColor: const Color(0xFF1A1A2E),
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.shopping_bag_outlined),
            onPressed: () => context.go('/global'),
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () async {
              await ref.read(globalAuthProvider.notifier).logout();
              if (context.mounted) context.go('/global');
            },
          ),
        ],
      ),
      body: ordersAsync.when(
        data: (orders) {
          if (orders.isEmpty) {
            return Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.receipt_long_outlined,
                    size: 64, color: Colors.grey),
                const SizedBox(height: 16),
                const Text('No orders yet',
                    style: TextStyle(fontSize: 18, color: Colors.grey)),
                const SizedBox(height: 8),
                const Text('Start shopping to see your orders here',
                    style: TextStyle(color: Colors.grey, fontSize: 13)),
                const SizedBox(height: 24),
                ElevatedButton.icon(
                  onPressed: () => context.go('/global'),
                  icon: const Icon(Icons.shopping_bag_outlined),
                  label: const Text('Shop Now'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF1A1A2E),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12)),
                  ),
                ),
              ]),
            );
          }

          return ListView.builder(
            padding: const EdgeInsets.all(12),
            itemCount: orders.length,
            itemBuilder: (ctx, i) => _OrderCard(order: orders[i]),
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.error_outline, size: 48, color: Colors.grey),
            const SizedBox(height: 12),
            Text(e.toString(),
                style: const TextStyle(color: Colors.grey)),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: () => ref.invalidate(globalOrdersProvider),
              child: const Text('Retry'),
            ),
          ]),
        ),
      ),
    );
  }
}

class GlobalOrderDetailScreen extends ConsumerWidget {
  final int orderId;
  const GlobalOrderDetailScreen({super.key, required this.orderId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final orderAsync = ref.watch(globalOrderDetailProvider(orderId));

    return Scaffold(
      backgroundColor: const Color(0xFFF0F2F5),
      appBar: AppBar(
        title: const Text('Order Details',
            style: TextStyle(fontWeight: FontWeight.w700)),
        backgroundColor: const Color(0xFF1A1A2E),
        foregroundColor: Colors.white,
      ),
      body: orderAsync.when(
        data: (order) => SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
            // Header
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12)),
              child: Column(children: [
                Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                  Text(order.orderNumber,
                      style: const TextStyle(
                          fontWeight: FontWeight.w800, fontSize: 16)),
                  _StatusBadge(status: order.status),
                ]),
                const SizedBox(height: 8),
                Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                  Text(order.createdAt,
                      style: TextStyle(
                          color: Colors.grey.shade500, fontSize: 12)),
                  Text(
                      '\$${order.total.toStringAsFixed(2)} ${order.currency.toUpperCase()}',
                      style: const TextStyle(
                          fontWeight: FontWeight.w800, fontSize: 16)),
                ]),
              ]),
            ),

            const SizedBox(height: 12),

            // Items
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12)),
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                const Text('Items',
                    style: TextStyle(
                        fontWeight: FontWeight.w800, fontSize: 14)),
                const SizedBox(height: 12),
                ...order.items.map((item) => Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: Row(children: [
                        if (item.thumbnail != null)
                          ClipRRect(
                            borderRadius: BorderRadius.circular(6),
                            child: Image.network(item.thumbnail!,
                                width: 48,
                                height: 48,
                                fit: BoxFit.cover,
                                errorBuilder: (_, __, ___) =>
                                    Container(
                                        width: 48,
                                        height: 48,
                                        color: Colors.grey.shade100)),
                          ),
                        if (item.thumbnail != null)
                          const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                            Text(item.name,
                                style: const TextStyle(
                                    fontWeight: FontWeight.w600,
                                    fontSize: 13)),
                            if (item.variant != null)
                              Text(item.variant!,
                                  style: TextStyle(
                                      color: Colors.grey.shade500,
                                      fontSize: 11)),
                            Text('×${item.quantity}',
                                style: TextStyle(
                                    color: Colors.grey.shade500,
                                    fontSize: 12)),
                          ]),
                        ),
                        Text('\$${item.total.toStringAsFixed(2)}',
                            style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: 13)),
                      ]),
                    )),
              ]),
            ),

            const SizedBox(height: 12),

            // ── Status timeline ──────────────────────────────────────────
            _OrderTimeline(status: order.status),

            const SizedBox(height: 12),

            // Shipping info
            if (order.shippingAddress != null)
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(12)),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                  const Text('Shipping Address',
                      style: TextStyle(
                          fontWeight: FontWeight.w800, fontSize: 14)),
                  const SizedBox(height: 8),
                  Text(order.shippingAddress!,
                      style: TextStyle(
                          color: Colors.grey.shade600, fontSize: 13)),
                  if (order.trackingNumber != null) ...[
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.blue.shade50,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(children: [
                        Icon(Icons.local_shipping_outlined,
                            size: 18, color: Colors.blue.shade700),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                            Text(
                                '${order.shippingCarrier ?? 'Carrier'} Tracking',
                                style: TextStyle(
                                    color: Colors.blue.shade800,
                                    fontWeight: FontWeight.w700,
                                    fontSize: 12)),
                            Text(order.trackingNumber!,
                                style: TextStyle(
                                    color: Colors.blue.shade600,
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600)),
                          ]),
                        ),
                      ]),
                    ),
                  ],
                ]),
              ),
          ]),
        ),
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(e.toString())),
      ),
    );
  }
}

// ── Order status timeline ─────────────────────────────────────────────────────

class _OrderTimeline extends StatelessWidget {
  final String status;
  const _OrderTimeline({required this.status});

  static const _steps = [
    (id: 'pending',    label: 'Order Placed',   icon: Icons.check_circle_outline_rounded),
    (id: 'processing', label: 'Processing',      icon: Icons.inventory_2_outlined),
    (id: 'shipped',    label: 'Shipped',          icon: Icons.local_shipping_outlined),
    (id: 'delivered',  label: 'Delivered',        icon: Icons.home_outlined),
  ];

  int _currentStep() {
    switch (status) {
      case 'pending':    return 0;
      case 'paid':       return 0;
      case 'processing': return 1;
      case 'shipped':    return 2;
      case 'delivered':  return 3;
      case 'cancelled':  return -1;
      default:           return 0;
    }
  }

  @override
  Widget build(BuildContext context) {
    final cur = _currentStep();
    if (cur == -1) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.red.shade50,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(children: [
          Icon(Icons.cancel_outlined, color: Colors.red.shade600),
          const SizedBox(width: 10),
          Text('Order Cancelled',
              style: TextStyle(
                  color: Colors.red.shade800,
                  fontWeight: FontWeight.w700)),
        ]),
      );
    }

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
          color: Colors.white, borderRadius: BorderRadius.circular(12)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Order Status',
              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
          const SizedBox(height: 16),
          Row(
            children: List.generate(_steps.length * 2 - 1, (i) {
              if (i.isOdd) {
                // connector line
                final stepIdx = i ~/ 2;
                final done = stepIdx < cur;
                return Expanded(
                  child: Container(
                    height: 2,
                    color: done
                        ? const Color(0xFF10B981)
                        : Colors.grey.shade200,
                  ),
                );
              }
              final stepIdx = i ~/ 2;
              final done    = stepIdx <= cur;
              final active  = stepIdx == cur;
              final step    = _steps[stepIdx];
              return Column(
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: done
                          ? const Color(0xFF10B981)
                          : Colors.grey.shade100,
                      border: active
                          ? Border.all(
                              color: const Color(0xFF10B981), width: 2)
                          : null,
                    ),
                    child: Icon(
                      step.icon,
                      size: 18,
                      color: done ? Colors.white : Colors.grey.shade400,
                    ),
                  ),
                  const SizedBox(height: 6),
                  SizedBox(
                    width: 60,
                    child: Text(
                      step.label,
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 9,
                        fontWeight: active || done
                            ? FontWeight.w700
                            : FontWeight.w400,
                        color: done
                            ? const Color(0xFF065F46)
                            : Colors.grey.shade400,
                      ),
                    ),
                  ),
                ],
              );
            }),
          ),
        ],
      ),
    );
  }
}

class _OrderCard extends ConsumerWidget {
  final GlobalOrder order;
  const _OrderCard({required this.order});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return GestureDetector(
      onTap: () => context.push('/global/order/${order.id}'),
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          boxShadow: [
            BoxShadow(
                color: Colors.black.withOpacity(0.04),
                blurRadius: 6,
                offset: const Offset(0, 2))
          ],
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(children: [
            // Thumbnail
            if (order.thumbnail != null)
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: Image.network(order.thumbnail!,
                    width: 56,
                    height: 56,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => _ImgPlaceholder()),
              )
            else
              _ImgPlaceholder(),
            const SizedBox(width: 12),

            // Info
            Expanded(
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                  Text(order.orderNumber,
                      style: const TextStyle(
                          fontWeight: FontWeight.w700, fontSize: 13)),
                  _StatusBadge(status: order.status),
                ]),
                const SizedBox(height: 4),
                Text('${order.itemsCount} item(s) · ${order.createdAt}',
                    style: TextStyle(
                        color: Colors.grey.shade500, fontSize: 11)),
                const SizedBox(height: 4),
                Text('\$${order.total.toStringAsFixed(2)}',
                    style: const TextStyle(
                        fontWeight: FontWeight.w800, fontSize: 15)),
              ]),
            ),
            const Icon(Icons.chevron_right, color: Colors.grey),
          ]),
        ),
      ),
    );
  }
}

class _ImgPlaceholder extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      width: 56,
      height: 56,
      decoration: BoxDecoration(
          color: Colors.grey.shade100,
          borderRadius: BorderRadius.circular(8)),
      child: const Icon(Icons.shopping_bag_outlined,
          color: Colors.grey, size: 24),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final String status;
  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    final configs = {
      'pending': (Colors.orange.shade100, Colors.orange.shade800),
      'paid': (Colors.blue.shade100, Colors.blue.shade800),
      'processing': (Colors.purple.shade100, Colors.purple.shade800),
      'shipped': (Colors.indigo.shade100, Colors.indigo.shade800),
      'delivered': (Colors.green.shade100, Colors.green.shade800),
      'cancelled': (Colors.red.shade100, Colors.red.shade800),
      'refunded': (Colors.grey.shade200, Colors.grey.shade700),
    };
    final cfg = configs[status] ??
        (Colors.grey.shade100, Colors.grey.shade700);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
          color: cfg.$1, borderRadius: BorderRadius.circular(6)),
      child: Text(status.toUpperCase(),
          style: TextStyle(
              fontSize: 9, fontWeight: FontWeight.w800, color: cfg.$2)),
    );
  }
}
