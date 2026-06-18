import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shimmer/shimmer.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/utils/error_handler.dart';
import '../providers/order_provider.dart';
import '../../data/models/order_model.dart';

class OrdersScreen extends ConsumerStatefulWidget {
  const OrdersScreen({super.key});

  @override
  ConsumerState<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends ConsumerState<OrdersScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabs;
  final _statusFilters = [null, 'pending', 'preparing', 'delivered', 'cancelled'];
  final _tabLabels    = ['All', 'Pending', 'Preparing', 'Delivered', 'Cancelled'];

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: _tabLabels.length, vsync: this);
  }

  @override
  void dispose() { _tabs.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
            appBar: AppBar(
        title: const Text('My Orders', style: TextStyle(fontWeight: FontWeight.w800)),
        
        foregroundColor: AppColors.textDark,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        bottom: TabBar(
          controller: _tabs,
          isScrollable: true,
          tabAlignment: TabAlignment.start,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.textGrey,
          indicatorColor: AppColors.primary,
          indicatorSize: TabBarIndicatorSize.label,
          labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
          tabs: _tabLabels.map((l) => Tab(text: l)).toList(),
        ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: _statusFilters.map((s) => _OrdersList(status: s)).toList(),
      ),
    );
  }
}

class _OrdersList extends ConsumerWidget {
  final String? status;
  const _OrdersList({this.status});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ordersAsync = ref.watch(ordersProvider(status));

    return ordersAsync.when(
      loading: () => ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: 4,
        separatorBuilder: (_, __) => SizedBox(height: 12),
        itemBuilder: (_, __) => Shimmer.fromColors(
          baseColor: Colors.grey.shade200,
          highlightColor: Colors.grey.shade100,
          child: Container(height: 110, decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(14))),
        ),
      ),
      error: (e, _) => Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.receipt_long_outlined, size: 56, color: AppColors.textLight),
            const SizedBox(height: 12),
            Text(AppErrorHandler.message(e), style: const TextStyle(color: AppColors.textGrey)),
          ],
        ),
      ),
      data: (orders) {
        if (orders.isEmpty) {
          return const Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('📦', style: TextStyle(fontSize: 56)),
                SizedBox(height: 16),
                Text('No orders yet', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.textDark)),
                SizedBox(height: 6),
                Text('Your orders will appear here', style: TextStyle(color: AppColors.textGrey)),
              ],
            ),
          );
        }
        return RefreshIndicator(
          color: AppColors.primary,
          onRefresh: () async => ref.refresh(ordersProvider(status)),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: orders.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (_, i) => _OrderCard(order: orders[i]),
          ),
        );
      },
    );
  }
}

class _OrderCard extends StatelessWidget {
  final OrderModel order;
  const _OrderCard({required this.order});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => context.push('/orders/${order.id}'),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: context.colors.cardBg,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 8)],
        ),
        child: Column(
          children: [
            Row(
              children: [
                Container(
                  width: 44, height: 44,
                  decoration: BoxDecoration(
                    color: order.statusColor.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    _statusIcon(order.status),
                    color: order.statusColor, size: 22,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(order.orderNumber,
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: AppColors.textDark)),
                      Text(order.vendorName ?? 'Order',
                        style: const TextStyle(fontSize: 13, color: AppColors.textGrey)),
                    ],
                  ),
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: order.statusColor.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(order.statusLabel,
                        style: TextStyle(color: order.statusColor, fontSize: 12, fontWeight: FontWeight.w700)),
                    ),
                    const SizedBox(height: 4),
                    Text('\$${order.totalAmount.toStringAsFixed(2)}',
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: AppColors.textDark)),
                  ],
                ),
              ],
            ),
            if (order.items.isNotEmpty) ...[
              const Divider(height: 20, color: AppColors.divider),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      order.items.map((i) => i.productName).take(2).join(', ') +
                          (order.items.length > 2 ? ' +${order.items.length - 2} more' : ''),
                      style: const TextStyle(fontSize: 12, color: AppColors.textGrey),
                      maxLines: 1, overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  if (order.isActive)
                    GestureDetector(
                      onTap: () => context.push('/orders/${order.id}/tracking'),
                      child: const Text('Track', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 13)),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  IconData _statusIcon(String status) {
    switch (status) {
      case 'delivered': return Icons.check_circle_outline_rounded;
      case 'cancelled': return Icons.cancel_outlined;
      case 'preparing': return Icons.restaurant_outlined;
      case 'picked_up': return Icons.delivery_dining_rounded;
      default: return Icons.receipt_long_outlined;
    }
  }
}
