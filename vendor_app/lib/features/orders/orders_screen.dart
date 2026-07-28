import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:timeago/timeago.dart' as timeago;
import '../../core/services/vendor_repository.dart';
import '../../core/theme/vc.dart';
import 'order_detail_sheet.dart';

final _ordersProvider = FutureProvider.autoDispose.family<Map<String, dynamic>, String>((ref, status) =>
  VendorRepository.instance.orders(status: status == 'all' ? null : status));

class OrdersScreen extends ConsumerStatefulWidget {
  const OrdersScreen({super.key});
  @override
  ConsumerState<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends ConsumerState<OrdersScreen> with SingleTickerProviderStateMixin {
  late TabController _tab;
  final _tabs = ['pending', 'confirmed', 'ready_for_pickup', 'all'];
  final _tabLabels = ['Pending', 'In Progress', 'Ready', 'All'];

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: _tabs.length, vsync: this);
  }

  @override
  void dispose() { _tab.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Orders'),
        bottom: TabBar(
          controller: _tab,
          isScrollable: true,
          indicatorColor: VC.orange,
          labelColor: VC.orange,
          unselectedLabelColor: context.vcTextSec,
          labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
          tabs: _tabLabels.map((l) => Tab(text: l)).toList(),
        ),
      ),
      body: TabBarView(
        controller: _tab,
        children: _tabs.map((status) => _OrderList(status: status)).toList(),
      ),
    );
  }
}

class _OrderList extends ConsumerWidget {
  final String status;
  const _OrderList({required this.status});

  static String _fmt(dynamic v) => (double.tryParse('${v ?? 0}') ?? 0).toStringAsFixed(2);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_ordersProvider(status));
    return async.when(
      loading: () => const Center(child: CircularProgressIndicator(color: VC.orange)),
      error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: VC.red))),
      data: (res) {
        final list = (res['data'] as List?) ?? [];
        if (list.isEmpty) return _empty(context);
        return RefreshIndicator(
          color: VC.orange,
          onRefresh: () async => ref.invalidate(_ordersProvider(status)),
          child: ListView.builder(
            padding: const EdgeInsets.all(14),
            itemCount: list.length,
            itemBuilder: (_, i) => _OrderCard(order: list[i], onTap: () {
              showOrderDetail(context, list[i]['id']);
              Future.delayed(const Duration(milliseconds: 400), () => ref.invalidate(_ordersProvider(status)));
            }),
          ),
        );
      },
    );
  }

  Widget _empty(BuildContext context) => Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
    Icon(Icons.receipt_long_rounded, color: context.vcTextMute, size: 56),
    const SizedBox(height: 12),
    Text('No ${status == 'all' ? '' : status} orders', style: TextStyle(color: context.vcTextMute, fontSize: 15, fontWeight: FontWeight.w600)),
  ]));
}

class _OrderCard extends StatelessWidget {
  final dynamic order;
  final VoidCallback onTap;
  const _OrderCard({required this.order, required this.onTap});

  static String _fmt(dynamic v) => (double.tryParse('${v ?? 0}') ?? 0).toStringAsFixed(2);

  @override
  Widget build(BuildContext context) {
    final o = order as Map<String, dynamic>;
    final status = o['status'] as String? ?? 'pending';
    final statusColor = {
      'pending': VC.amber, 'confirmed': VC.blue,
      'ready_for_pickup': VC.green, 'delivered': VC.green,
      'cancelled': VC.red,
    }[status] ?? context.vcTextSec;
    final items = o['items'] as List? ?? [];
    final placedAt = DateTime.tryParse(o['placed_at'] ?? o['created_at'] ?? '');

    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: context.vcCard,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: status == 'pending' ? VC.amber.withValues(alpha: 0.3) : context.vcBorder.withValues(alpha: 0.4)),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Expanded(child: Row(children: [
              Text('#${o['order_number'] ?? ''}', style: TextStyle(color: context.vcText, fontWeight: FontWeight.w900, fontSize: 14)),
              const SizedBox(width: 8),
              Container(padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                decoration: BoxDecoration(color: statusColor.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(6)),
                child: Text(status.replaceAll('_', ' '), style: TextStyle(color: statusColor, fontSize: 10, fontWeight: FontWeight.w800))),
            ])),
            Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
              Text('\$${_fmt(o['subtotal'] ?? o['total_amount'])}', style: const TextStyle(color: VC.green, fontWeight: FontWeight.w900, fontSize: 16)),
              if ((double.tryParse('${o['delivery_fee'] ?? 0}') ?? 0) > 0)
                Text('+\$${_fmt(o['delivery_fee'])} delivery', style: TextStyle(color: context.vcTextMute, fontSize: 10)),
            ]),
          ]),
          const SizedBox(height: 8),
          Row(children: [
            Icon(Icons.person_rounded, color: context.vcTextMute, size: 14),
            const SizedBox(width: 4),
            Text(o['user']?['name'] ?? 'Customer', style: TextStyle(color: context.vcTextSec, fontSize: 12)),
            const Spacer(),
            if (placedAt != null)
              Text(timeago.format(placedAt), style: TextStyle(color: context.vcTextMute, fontSize: 11)),
          ]),
          if (items.isNotEmpty) ...[
            const SizedBox(height: 8),
            Divider(color: context.vcBorder, height: 1),
            const SizedBox(height: 8),
            Text('${items.length} item${items.length > 1 ? 's' : ''}: ${items.take(2).map((i) => i['product_name'] ?? i['name'] ?? '').join(', ')}${items.length > 2 ? '…' : ''}',
              style: TextStyle(color: context.vcTextSec, fontSize: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
          ],
          if (status == 'pending') ...[
            const SizedBox(height: 10),
            Row(children: [
              Expanded(child: _btn('Reject', VC.red, () => _reject(context, o['id']))),
              const SizedBox(width: 8),
              Expanded(flex: 2, child: _btn('Accept', VC.green, () => _accept(context, o['id']))),
            ]),
          ],
          if (status == 'confirmed') ...[
            const SizedBox(height: 10),
            _btn('Mark Ready for Pickup', VC.blue, () => _ready(context, o['id'])),
          ],
        ]),
      ),
    );
  }

  Widget _btn(String label, Color color, VoidCallback onTap) => GestureDetector(
    onTap: onTap,
    child: Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10), border: Border.all(color: color.withValues(alpha: 0.3))),
      child: Center(child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w800))),
    ),
  );

  Future<void> _accept(BuildContext ctx, int id) async {
    try {
      await VendorRepository.instance.acceptOrder(id);
      ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content: Text('Order accepted!'), backgroundColor: VC.green));
    } catch (e) {
      ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: VC.red));
    }
  }

  Future<void> _reject(BuildContext ctx, int id) async {
    final ctrl = TextEditingController();
    final reason = await showDialog<String>(context: ctx, builder: (c) => AlertDialog(
      backgroundColor: c.vcCard,
      title: Text('Reject Reason', style: TextStyle(color: c.vcText)),
      content: TextField(controller: ctrl, autofocus: true, style: TextStyle(color: c.vcText),
        decoration: InputDecoration(hintText: 'e.g. Out of stock', hintStyle: TextStyle(color: c.vcTextMute))),
      actions: [
        TextButton(onPressed: () => Navigator.pop(c), child: Text('Cancel', style: TextStyle(color: c.vcTextSec))),
        ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: VC.red), onPressed: () => Navigator.pop(c, ctrl.text.trim()), child: const Text('Reject', style: TextStyle(color: Colors.white))),
      ],
    ));
    if (reason == null || reason.isEmpty) return;
    try {
      await VendorRepository.instance.rejectOrder(id, reason);
      ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content: Text('Order rejected'), backgroundColor: VC.amber));
    } catch (e) {
      ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: VC.red));
    }
  }

  Future<void> _ready(BuildContext ctx, int id) async {
    try {
      await VendorRepository.instance.markReady(id);
      ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content: Text('Marked ready for pickup!'), backgroundColor: VC.green));
    } catch (e) {
      ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: VC.red));
    }
  }
}
