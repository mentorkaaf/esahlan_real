import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../data/models/egrocery_models.dart';
import '../../data/repositories/egrocery_repository.dart';
import '../../ui/eg_theme.dart';
import '../../ui/eg_widgets.dart';
import '../providers/egrocery_providers.dart';

// ════════════════════════════════════════════════════════════════
// Orders list
// ════════════════════════════════════════════════════════════════

class EGOrdersScreen extends ConsumerWidget {
  const EGOrdersScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ordersAsync = ref.watch(egOrdersProvider);

    return Scaffold(
      backgroundColor: EGTheme.bg,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: const Text('My Orders', style: TextStyle(color: EGTheme.textDark, fontWeight: FontWeight.w800, fontSize: 17)),
        iconTheme: const IconThemeData(color: EGTheme.textDark),
      ),
      body: ordersAsync.when(
        loading: () => ListView.builder(
          padding: const EdgeInsets.all(12),
          itemCount: 4,
          itemBuilder: (_, __) => Padding(padding: const EdgeInsets.only(bottom: 10), child: EGShimmerBox(width: double.infinity, height: 100, radius: EGTheme.rCard)),
        ),
        error: (e, _) => EGEmptyState(emoji: '😕', title: 'Failed to load', subtitle: e.toString(), onRetry: () => ref.invalidate(egOrdersProvider)),
        data: (orders) => orders.isEmpty
            ? const EGEmptyState(emoji: '📦', title: 'No orders yet', subtitle: 'Your grocery orders will appear here')
            : RefreshIndicator(
                color: EGTheme.orange,
                onRefresh: () => ref.refresh(egOrdersProvider.future),
                child: ListView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: orders.length,
                  itemBuilder: (_, i) => _OrderCard(order: orders[i]),
                ),
              ),
      ),
    );
  }
}

class _OrderCard extends ConsumerWidget {
  final EGOrder order;
  const _OrderCard({required this.order});

  static const _statusColors = {
    'pending':          Color(0xFFF59E0B),
    'confirmed':        Color(0xFF3B82F6),
    'picking':          Color(0xFF8B5CF6),
    'ready':            Color(0xFF14B8A6),
    'out_for_delivery': Color(0xFFFF8A00),
    'delivered':        Color(0xFF22C55E),
    'cancelled':        Color(0xFFEF4444),
  };

  static const _statusEmoji = {
    'pending': '⏳', 'confirmed': '✅', 'picking': '🛒',
    'ready': '📦', 'out_for_delivery': '🚚', 'delivered': '🎉', 'cancelled': '❌',
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final color = _statusColors[order.status] ?? EGTheme.textGrey;
    final emoji = _statusEmoji[order.status] ?? '📋';

    return GestureDetector(
      onTap: () => context.push('/egrocery/orders/${order.id}'),
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(EGTheme.rCard)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Header
          Padding(
            padding: const EdgeInsets.all(14),
            child: Row(children: [
              Text(emoji, style: const TextStyle(fontSize: 22)),
              const SizedBox(width: 10),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(order.orderNo, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: EGTheme.textDark)),
                Text('${order.items.length} item${order.items.length == 1 ? '' : 's'} · \$${order.total.toStringAsFixed(2)}', style: EGTheme.caption),
              ])),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(color: color.withOpacity(0.12), borderRadius: BorderRadius.circular(20)),
                child: Text(order.status.replaceAll('_', ' ').toUpperCase(),
                    style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w800, letterSpacing: 0.5)),
              ),
            ]),
          ),
          // Footer
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
            child: Row(children: [
              Text(order.createdAt != null ? _formatDate(order.createdAt!) : '', style: EGTheme.caption),
              const Spacer(),
              if (order.status == 'delivered')
                TextButton(
                  onPressed: () async {
                    final lines = await EGroceryRepository().reorder(order.id);
                    for (final l in lines) {
                      ref.read(egCartProvider.notifier).addOrIncrement(
                        EGVariant(id: l.variantId, label: l.variantLabel, stockQty: 99, inStock: true, isDefault: true, effectivePrice: l.unitPrice),
                        EGProduct(id: 0, name: l.productName, slug: '', variants: []),
                      );
                    }
                    if (context.mounted) context.push('/egrocery/cart');
                  },
                  style: TextButton.styleFrom(padding: EdgeInsets.zero, minimumSize: const Size(0, 0)),
                  child: const Text('Reorder', style: TextStyle(color: EGTheme.orange, fontWeight: FontWeight.w700, fontSize: 12)),
                ),
              TextButton(
                onPressed: () => context.push('/egrocery/orders/${order.id}'),
                style: TextButton.styleFrom(padding: EdgeInsets.zero, minimumSize: const Size(0, 0)),
                child: const Text('View Details →', style: TextStyle(color: EGTheme.orange, fontWeight: FontWeight.w700, fontSize: 12)),
              ),
            ]),
          ),
        ]),
      ),
    );
  }

  String _formatDate(String iso) {
    try {
      final d = DateTime.parse(iso).toLocal();
      return '${d.day}/${d.month}/${d.year} ${d.hour}:${d.minute.toString().padLeft(2, '0')}';
    } catch (_) {
      return iso;
    }
  }
}

// ════════════════════════════════════════════════════════════════
// Order Detail
// ════════════════════════════════════════════════════════════════

class EGOrderDetailScreen extends ConsumerWidget {
  final int orderId;
  const EGOrderDetailScreen({super.key, required this.orderId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final orderAsync = ref.watch(egOrderDetailProvider(orderId));

    return Scaffold(
      backgroundColor: EGTheme.bg,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: const Text('Order Details', style: TextStyle(color: EGTheme.textDark, fontWeight: FontWeight.w800, fontSize: 17)),
        iconTheme: const IconThemeData(color: EGTheme.textDark),
      ),
      body: orderAsync.when(
        loading: () => Center(child: CircularProgressIndicator(color: EGTheme.orange)),
        error: (e, _) => EGEmptyState(emoji: '😕', title: 'Error', subtitle: e.toString(), onRetry: () => ref.invalidate(egOrderDetailProvider(orderId))),
        data: (order) => _EGOrderDetailBody(order: order),
      ),
    );
  }
}

class _EGOrderDetailBody extends ConsumerStatefulWidget {
  final EGOrder order;
  const _EGOrderDetailBody({required this.order});

  @override
  ConsumerState<_EGOrderDetailBody> createState() => _EGOrderDetailBodyState();
}

class _EGOrderDetailBodyState extends ConsumerState<_EGOrderDetailBody> {
  bool _cancelling = false;

  Future<void> _cancel(BuildContext ctx) async {
    final confirm = await showDialog<bool>(
      context: ctx,
      builder: (_) => AlertDialog(
        title: const Text('Cancel Order?'),
        content: const Text('Are you sure you want to cancel this order?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('No')),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Yes, cancel', style: TextStyle(color: EGTheme.red))),
        ],
      ),
    );
    if (confirm != true) return;
    setState(() => _cancelling = true);
    try {
      await EGroceryRepository().cancelOrder(widget.order.id);
      if (ctx.mounted) {
        ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content: Text('Order cancelled')));
        ctx.pop();
      }
    } catch (e) {
      setState(() => _cancelling = false);
      if (ctx.mounted) ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('Error: $e'), backgroundColor: EGTheme.red));
    }
  }

  @override
  Widget build(BuildContext context) {
    final order = widget.order;
    final canCancel = ['pending', 'confirmed'].contains(order.status);

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

        // Status card
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            gradient: LinearGradient(colors: [EGTheme.orange.withOpacity(0.08), EGTheme.orange.withOpacity(0.04)]),
            borderRadius: BorderRadius.circular(EGTheme.rCard),
            border: Border.all(color: EGTheme.orange.withOpacity(0.2)),
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(order.orderNo, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: EGTheme.textDark)),
            const SizedBox(height: 4),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(color: EGTheme.orange, borderRadius: BorderRadius.circular(20)),
              child: Text(order.status.replaceAll('_', ' ').toUpperCase(),
                  style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800)),
            ),
          ]),
        ),

        const SizedBox(height: 12),

        // Items
        Container(
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(EGTheme.rCard)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Padding(padding: EdgeInsets.all(14), child: Text('Items', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14))),
            ...order.items.map((item) => ListTile(
              title: Text(item.productName, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
              subtitle: Text('${item.variantLabel} · ×${item.qty.toStringAsFixed(item.qty % 1 == 0 ? 0 : 1)}', style: EGTheme.caption),
              trailing: Text('\$${item.lineTotal.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w700)),
              contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 2),
            )),
            const Divider(height: 1),
            Padding(
              padding: const EdgeInsets.all(14),
              child: Column(children: [
                _TRow('Subtotal', order.subtotal),
                if (order.discount > 0) _TRow('Discount', -order.discount, color: EGTheme.green),
                _TRow('Delivery', order.deliveryFee),
                const Divider(height: 14),
                _TRow('Total', order.total, bold: true),
              ]),
            ),
          ]),
        ),

        const SizedBox(height: 12),

        // Meta
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(EGTheme.rCard)),
          child: Column(children: [
            _MetaRow('Payment', order.paymentMethod.toUpperCase()),
            _MetaRow('Status', order.paymentStatus),
            if (order.customerNote != null) _MetaRow('Note', order.customerNote!),
            if (order.cancelledReason != null) _MetaRow('Cancellation', order.cancelledReason!),
          ]),
        ),

        if (canCancel) ...[
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton(
              onPressed: _cancelling ? null : () => _cancel(context),
              style: OutlinedButton.styleFrom(
                foregroundColor: EGTheme.red,
                side: const BorderSide(color: EGTheme.red),
                padding: const EdgeInsets.symmetric(vertical: 13),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(EGTheme.rBtn)),
              ),
              child: _cancelling
                  ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2, color: EGTheme.red))
                  : const Text('Cancel Order', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
          ),
        ],

        const SizedBox(height: 30),
      ]),
    );
  }
}

class _TRow extends StatelessWidget {
  final String label;
  final double amount;
  final bool bold;
  final Color? color;
  const _TRow(this.label, this.amount, {this.bold = false, this.color});

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(children: [
          Text(label, style: TextStyle(fontSize: 13, color: EGTheme.textGrey, fontWeight: bold ? FontWeight.w800 : FontWeight.w400)),
          const Spacer(),
          Text('\$${amount.toStringAsFixed(2)}', style: TextStyle(fontSize: 13, fontWeight: bold ? FontWeight.w800 : FontWeight.w700, color: color ?? EGTheme.textDark)),
        ]),
      );
}

class _MetaRow extends StatelessWidget {
  final String label;
  final String value;
  const _MetaRow(this.label, this.value);

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 5),
        child: Row(children: [
          Text(label, style: const TextStyle(fontSize: 13, color: EGTheme.textGrey)),
          const Spacer(),
          Text(value, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: EGTheme.textDark)),
        ]),
      );
}

// ════════════════════════════════════════════════════════════════
// Order Success
// ════════════════════════════════════════════════════════════════

class EGOrderSuccessScreen extends StatelessWidget {
  final EGOrder order;
  const EGOrderSuccessScreen({super.key, required this.order});

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: EGTheme.bg,
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              const Text('🎉', style: TextStyle(fontSize: 72)),
              const SizedBox(height: 16),
              const Text('Order Placed!', style: TextStyle(fontSize: 26, fontWeight: FontWeight.w900, color: EGTheme.textDark)),
              const SizedBox(height: 8),
              Text(order.orderNo, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: EGTheme.orange)),
              const SizedBox(height: 8),
              Text('Total: \$${order.total.toStringAsFixed(2)}', style: const TextStyle(fontSize: 16, color: EGTheme.textGrey)),
              const SizedBox(height: 32),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () => context.push('/egrocery/orders/${order.id}'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: EGTheme.orange,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 15),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(EGTheme.rBtn)),
                  ),
                  child: const Text('Track Order', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                ),
              ),
              const SizedBox(height: 12),
              TextButton(
                onPressed: () => context.go('/egrocery'),
                child: const Text('Continue Shopping', style: TextStyle(color: EGTheme.orange, fontWeight: FontWeight.w700)),
              ),
            ]),
          ),
        ),
      );
}
