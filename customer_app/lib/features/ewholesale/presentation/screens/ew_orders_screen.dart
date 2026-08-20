import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/ew_provider.dart';
import '../ui/ew_theme.dart';
import '../ui/widgets/ew_widgets.dart';
import '../../data/models/ew_models.dart';

// ─── Orders List ──────────────────────────────────────────────────────────────

class EwOrdersScreen extends ConsumerStatefulWidget {
  const EwOrdersScreen({super.key});

  @override
  ConsumerState<EwOrdersScreen> createState() => _EwOrdersScreenState();
}

class _EwOrdersScreenState extends ConsumerState<EwOrdersScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabs;
  final _statuses = [null, 'pending_confirmation', 'processing', 'shipped', 'completed'];
  final _labels   = ['All', 'Pending', 'Processing', 'Shipped', 'Completed'];

  @override
  void initState() { super.initState(); _tabs = TabController(length: _statuses.length, vsync: this); }
  @override
  void dispose()   { _tabs.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: EwTheme.bg,
      appBar: AppBar(
        backgroundColor: EwTheme.navy,
        foregroundColor: Colors.white,
        title: const Text('My Orders', style: TextStyle(fontWeight: FontWeight.w700)),
        bottom: TabBar(
          controller: _tabs,
          isScrollable: true,
          indicatorColor: EwTheme.orange,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white60,
          tabs: _labels.map((l) => Tab(text: l)).toList(),
        ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: _statuses.map((s) => _OrdersTab(status: s)).toList(),
      ),
    );
  }
}

class _OrdersTab extends ConsumerWidget {
  final String? status;
  const _OrdersTab({this.status});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ordersAsync = ref.watch(ewOrdersProvider(status));

    return RefreshIndicator(
      color: EwTheme.orange,
      onRefresh: () => ref.refresh(ewOrdersProvider(status).future),
      child: ordersAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: EwTheme.orange)),
        error:   (e, _) => EwErrorRetry(error: e, onRetry: () => ref.refresh(ewOrdersProvider(status).future)),
        data:    (orders) => orders.isEmpty
          ? const EwEmptyState(icon: Icons.receipt_long_outlined, title: 'No orders yet',
              subtitle: 'Your wholesale orders will appear here')
          : ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: orders.length,
              itemBuilder: (_, i) => _OrderTile(order: orders[i]),
            ),
      ),
    );
  }
}

class _OrderTile extends StatelessWidget {
  final EwOrder order;
  const _OrderTile({required this.order});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(color: EwTheme.surface, borderRadius: EwTheme.radius12,
        border: Border.all(color: EwTheme.border), boxShadow: EwTheme.cardShadow),
      child: Material(color: Colors.transparent, borderRadius: EwTheme.radius12,
        child: InkWell(borderRadius: EwTheme.radius12, onTap: () => context.push('/ewholesale/order/${order.id}'),
          child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Text(order.orderNo, style: EwTheme.heading3),
              const Spacer(),
              OrderStatusChip(status: order.status),
            ]),
            const SizedBox(height: 6),
            Text(order.supplier?.displayName ?? '', style: EwTheme.bodySmall),
            const SizedBox(height: 4),
            Text('${order.items.length} item${order.items.length > 1 ? 's' : ''} · ${EwTheme.formatPrice(order.total)}', style: EwTheme.body),
            const SizedBox(height: 6),
            if (order.canPayBalance)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(color: EwTheme.orange.withOpacity(0.1), borderRadius: EwTheme.radius4),
                child: const Text('Balance payment due', style: TextStyle(color: EwTheme.orange, fontWeight: FontWeight.w600, fontSize: 12)),
              ),
          ])),
        ),
      ),
    );
  }
}

// ─── Order Detail ─────────────────────────────────────────────────────────────

class EwOrderDetailScreen extends ConsumerStatefulWidget {
  final int orderId;
  const EwOrderDetailScreen({super.key, required this.orderId});

  @override
  ConsumerState<EwOrderDetailScreen> createState() => _EwOrderDetailScreenState();
}

class _EwOrderDetailScreenState extends ConsumerState<EwOrderDetailScreen> {
  bool _shipmentsExpanded = true;
  bool _loading           = false;

  @override
  Widget build(BuildContext context) {
    final orderAsync = ref.watch(ewOrderDetailProvider(widget.orderId));

    return Scaffold(
      backgroundColor: EwTheme.bg,
      appBar: AppBar(
        backgroundColor: EwTheme.navy,
        foregroundColor: Colors.white,
        title: const Text('Order Detail', style: TextStyle(fontWeight: FontWeight.w700)),
        actions: [
          orderAsync.valueOrNull?.status == 'completed'
            ? TextButton(
                onPressed: () => _reorder(context, orderAsync.value!),
                child: const Text('Reorder', style: TextStyle(color: Colors.white)),
              )
            : const SizedBox.shrink(),
        ],
      ),
      body: orderAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: EwTheme.orange)),
        error:   (e, _) => EwErrorRetry(error: e, onRetry: () => ref.refresh(ewOrderDetailProvider(widget.orderId))),
        data:    (order) => _buildContent(context, order),
      ),
    );
  }

  Widget _buildContent(BuildContext context, EwOrder order) {
    return Stack(children: [
      ListView(padding: const EdgeInsets.fromLTRB(16, 16, 16, 120), children: [
        // ── Status + progress bar ─────────────────────────────────────────────
        _buildStatusCard(order),
        const SizedBox(height: 12),
        // ── Payment progress ──────────────────────────────────────────────────
        if (order.depositPercent > 0 && order.paymentPlan == 'deposit') _buildPaymentProgress(order),
        // ── Items ─────────────────────────────────────────────────────────────
        _buildItemsCard(order),
        const SizedBox(height: 12),
        // ── Shipments accordion ───────────────────────────────────────────────
        if (order.shipments.isNotEmpty) _buildShipments(order),
        // ── Timeline ─────────────────────────────────────────────────────────
        _buildTimeline(order),
      ]),
      // ── Actions ──────────────────────────────────────────────────────────────
      Positioned(bottom: 0, left: 0, right: 0, child: _buildActions(context, order)),
    ]);
  }

  Widget _buildStatusCard(EwOrder order) {
    return Container(
      decoration: BoxDecoration(color: EwTheme.surface, borderRadius: EwTheme.radius12,
        border: Border.all(color: EwTheme.border), boxShadow: EwTheme.cardShadow),
      padding: const EdgeInsets.all(14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text(order.orderNo, style: EwTheme.heading2)),
          OrderStatusChip(status: order.status),
        ]),
        const SizedBox(height: 4),
        Text(order.supplier?.displayName ?? '', style: EwTheme.bodySmall),
        const SizedBox(height: 12),
        // Linear progress through states
        _OrderProgress(status: order.status),
        const SizedBox(height: 10),
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Text('Total: ${EwTheme.formatPrice(order.total)}', style: EwTheme.priceSmall),
          Text('Placed: ${_fmtDate(order.createdAt)}', style: EwTheme.bodySmall),
        ]),
      ]),
    );
  }

  Widget _buildPaymentProgress(EwOrder order) {
    final pct      = order.depositPercent / 100;
    final deposit  = order.total * pct;
    final balance  = order.total * (1 - pct);
    final paid     = order.payments.fold<double>(0, (s, p) => s + p.amount);
    final progress = paid / order.total;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: EwTheme.surface, borderRadius: EwTheme.radius12,
        border: Border.all(color: EwTheme.border)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Payment Progress', style: EwTheme.heading3),
        const SizedBox(height: 10),
        ClipRRect(
          borderRadius: EwTheme.radius4,
          child: LinearProgressIndicator(
            value: progress.clamp(0, 1),
            minHeight: 8,
            backgroundColor: EwTheme.bg,
            valueColor: const AlwaysStoppedAnimation<Color>(EwTheme.green),
          ),
        ),
        const SizedBox(height: 8),
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Text('Deposit (${order.depositPercent}%): ${EwTheme.formatPrice(deposit)}', style: EwTheme.bodySmall),
          Text('Balance: ${EwTheme.formatPrice(balance)}', style: EwTheme.bodySmall.copyWith(color: order.canPayBalance ? EwTheme.orange : EwTheme.textMuted)),
        ]),
      ]),
    );
  }

  Widget _buildItemsCard(EwOrder order) {
    return Container(
      decoration: BoxDecoration(color: EwTheme.surface, borderRadius: EwTheme.radius12,
        border: Border.all(color: EwTheme.border)),
      child: Column(children: [
        Padding(padding: const EdgeInsets.fromLTRB(14, 12, 14, 0), child: EwSectionHeader(title: 'Items (${order.items.length})')),
        ...order.items.asMap().entries.map((e) {
          final i   = e.key;
          final itm = e.value;
          return Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(border: Border(top: BorderSide(color: i == 0 ? Colors.transparent : EwTheme.border))),
            child: Row(children: [
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(itm.nameSnapshot, style: EwTheme.heading3.copyWith(fontSize: 13)),
                Text('${itm.qty.toInt()} ${itm.unit} × ${EwTheme.formatPrice(itm.unitPrice)}', style: EwTheme.bodySmall),
              ])),
              Text(EwTheme.formatPrice(itm.subtotal), style: EwTheme.priceSmall),
            ]),
          );
        }),
        const SizedBox(height: 12),
      ]),
    );
  }

  Widget _buildShipments(EwOrder order) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(color: EwTheme.surface, borderRadius: EwTheme.radius12,
        border: Border.all(color: EwTheme.border)),
      child: Column(children: [
        GestureDetector(
          onTap: () => setState(() => _shipmentsExpanded = !_shipmentsExpanded),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(children: [
              const Icon(Icons.local_shipping_outlined, color: EwTheme.navy, size: 20),
              const SizedBox(width: 8),
              Expanded(child: Text('Shipments (${order.shipments.length})', style: EwTheme.heading3)),
              Icon(_shipmentsExpanded ? Icons.expand_less : Icons.expand_more, color: EwTheme.textMuted),
            ]),
          ),
        ),
        if (_shipmentsExpanded)
          ...order.shipments.map((s) => Container(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 12),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Divider(height: 0, color: EwTheme.border),
              const SizedBox(height: 10),
              Row(children: [
                Expanded(child: Text('Tracking: ${s.trackingNo}', style: EwTheme.body.copyWith(fontWeight: FontWeight.w600))),
                Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2), decoration: BoxDecoration(color: EwTheme.green.withOpacity(0.1), borderRadius: EwTheme.radius4),
                  child: Text(s.status, style: const TextStyle(color: EwTheme.green, fontSize: 11, fontWeight: FontWeight.w600))),
              ]),
              const SizedBox(height: 4),
              if (s.carrier != null) Text('Carrier: ${s.carrier}', style: EwTheme.bodySmall),
              if (s.estimatedDelivery != null) Text('ETA: ${_fmtDate(s.estimatedDelivery!)}', style: EwTheme.bodySmall),
            ]),
          )),
      ]),
    );
  }

  Widget _buildTimeline(EwOrder order) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: EwTheme.surface, borderRadius: EwTheme.radius12,
        border: Border.all(color: EwTheme.border)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Order Timeline', style: EwTheme.heading3),
        const SizedBox(height: 12),
        ...order.statusTimeline.asMap().entries.map((e) {
          final i  = e.key;
          final ev = e.value;
          final isLast = i == order.statusTimeline.length - 1;
          return IntrinsicHeight(child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Column(children: [
              Container(width: 12, height: 12, margin: const EdgeInsets.only(top: 2),
                decoration: BoxDecoration(color: i == 0 ? EwTheme.orange : EwTheme.green, shape: BoxShape.circle)),
              if (!isLast) Expanded(child: Container(width: 2, color: EwTheme.border)),
            ]),
            const SizedBox(width: 12),
            Expanded(child: Padding(padding: const EdgeInsets.only(bottom: 14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(ev['event']?.toString() ?? '', style: EwTheme.body.copyWith(fontWeight: FontWeight.w600)),
              if (ev['note'] != null) Text(ev['note'].toString(), style: EwTheme.bodySmall),
              Text(ev['created_at']?.toString() ?? '', style: EwTheme.bodySmall.copyWith(fontSize: 11)),
            ]))),
          ]));
        }),
      ]),
    );
  }

  Widget _buildActions(BuildContext context, EwOrder order) {
    final actions = <Widget>[];

    if (order.canPayBalance) {
      actions.add(ElevatedButton(
        style: EwTheme.primaryButton.copyWith(minimumSize: const WidgetStatePropertyAll(Size(0, 44))),
        onPressed: _loading ? null : () => _payBalance(context, order),
        child: _loading
          ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
          : const Text('Pay Balance'),
      ));
    }

    if (order.canCancel) {
      actions.add(OutlinedButton(
        style: OutlinedButton.styleFrom(foregroundColor: EwTheme.red, side: const BorderSide(color: EwTheme.red),
          minimumSize: const Size(0, 44), shape: const RoundedRectangleBorder(borderRadius: EwTheme.radius8)),
        onPressed: _loading ? null : () => _cancel(context, order),
        child: const Text('Cancel'),
      ));
    }

    if (order.canDispute) {
      actions.add(OutlinedButton(
        style: EwTheme.secondaryButton.copyWith(minimumSize: const WidgetStatePropertyAll(Size(0, 44))),
        onPressed: () => _showDisputeSheet(context, order),
        child: const Text('Open Dispute'),
      ));
    }

    if (order.canReview) {
      actions.add(OutlinedButton(
        style: EwTheme.secondaryButton.copyWith(minimumSize: const WidgetStatePropertyAll(Size(0, 44))),
        onPressed: () => _showReviewSheet(context, order),
        child: const Text('Write Review'),
      ));
    }

    if (actions.isEmpty) return const SizedBox.shrink();

    return Container(
      decoration: BoxDecoration(color: EwTheme.surface,
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.08), blurRadius: 8, offset: const Offset(0, -2))]),
      padding: EdgeInsets.fromLTRB(16, 12, 16, MediaQuery.of(context).padding.bottom + 12),
      child: Wrap(spacing: 10, runSpacing: 8, children: actions),
    );
  }

  Future<void> _payBalance(BuildContext context, EwOrder order) async {
    setState(() => _loading = true);
    try {
      await ref.read(ewRepoProvider).payOrderBalance(order.id);
      ref.invalidate(ewOrderDetailProvider(widget.orderId));
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Balance paid!'), backgroundColor: EwTheme.green));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: EwTheme.red));
    } finally { if (mounted) setState(() => _loading = false); }
  }

  Future<void> _cancel(BuildContext context, EwOrder order) async {
    final confirmed = await showDialog<bool>(context: context, builder: (_) => AlertDialog(
      title: const Text('Cancel Order?'),
      content: const Text('This action cannot be undone. The supplier will be notified.'),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Keep Order')),
        ElevatedButton(onPressed: () => Navigator.pop(context, true), style: ElevatedButton.styleFrom(backgroundColor: EwTheme.red), child: const Text('Cancel Order')),
      ],
    ));
    if (confirmed != true) return;
    try {
      await ref.read(ewRepoProvider).cancelOrder(order.id);
      ref.invalidate(ewOrderDetailProvider(widget.orderId));
      ref.invalidate(ewOrdersProvider(null));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: EwTheme.red));
    }
  }

  void _showDisputeSheet(BuildContext context, EwOrder order) {
    final ctrl = TextEditingController();
    showModalBottomSheet(context: context, isScrollControlled: true, builder: (ctx) {
      return Padding(
        padding: EdgeInsets.fromLTRB(16, 20, 16, MediaQuery.of(ctx).viewInsets.bottom + 24),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Open Dispute', style: EwTheme.heading2),
          const SizedBox(height: 12),
          TextField(controller: ctrl, maxLines: 4, decoration: InputDecoration(hintText: 'Describe the issue…', border: OutlineInputBorder(borderRadius: EwTheme.radius8))),
          const SizedBox(height: 14),
          SizedBox(width: double.infinity, child: ElevatedButton(
            style: EwTheme.primaryButton,
            onPressed: () async {
              if (ctrl.text.trim().isEmpty) return;
              await ref.read(ewRepoProvider).openDispute(order.id, ctrl.text.trim());
              ref.invalidate(ewOrderDetailProvider(widget.orderId));
              if (ctx.mounted) Navigator.pop(ctx);
            },
            child: const Text('Submit Dispute'),
          )),
        ]),
      );
    });
  }

  void _showReviewSheet(BuildContext context, EwOrder order) {
    int rating = 5;
    final ctrl = TextEditingController();
    showModalBottomSheet(context: context, isScrollControlled: true, builder: (ctx) {
      return StatefulBuilder(builder: (ctx, setLocalState) => Padding(
        padding: EdgeInsets.fromLTRB(16, 20, 16, MediaQuery.of(ctx).viewInsets.bottom + 24),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Review Supplier', style: EwTheme.heading2),
          const SizedBox(height: 12),
          Row(children: List.generate(5, (i) => GestureDetector(
            onTap: () => setLocalState(() => rating = i + 1),
            child: Icon(i < rating ? Icons.star : Icons.star_border, color: EwTheme.amber, size: 32),
          ))),
          const SizedBox(height: 12),
          TextField(controller: ctrl, maxLines: 3, decoration: InputDecoration(hintText: 'Your experience…', border: OutlineInputBorder(borderRadius: EwTheme.radius8))),
          const SizedBox(height: 14),
          SizedBox(width: double.infinity, child: ElevatedButton(
            style: EwTheme.primaryButton,
            onPressed: () async {
              await ref.read(ewRepoProvider).submitReview(order.id, rating: rating, comment: ctrl.text.trim());
              ref.invalidate(ewOrderDetailProvider(widget.orderId));
              if (ctx.mounted) Navigator.pop(ctx);
            },
            child: const Text('Submit Review'),
          )),
        ]),
      ));
    });
  }

  Future<void> _reorder(BuildContext context, EwOrder order) async {
    for (final item in order.items) {
      ref.read(ewCartProvider.notifier).add(EwCartLine(
        productId:         item.productId,
        productName:       item.nameSnapshot,
        variantId:         null,
        variantAttributes: const {},
        supplierId:        order.supplier?.id ?? 0,
        qty:               item.qty,
        unit:              item.unit,
        unitPrice:         item.unitPrice,
        moq:               1,
      ));
    }
    if (mounted) context.push('/ewholesale/cart');
  }

  String _fmtDate(DateTime d) => '${d.day}/${d.month}/${d.year}';
}

// ─── Order Progress Widget ────────────────────────────────────────────────────

class _OrderProgress extends StatelessWidget {
  final String status;
  const _OrderProgress({required this.status});

  static const _states = [
    'pending_confirmation', 'confirmed', 'awaiting_payment',
    'processing', 'ready', 'shipped', 'delivered', 'completed',
  ];

  @override
  Widget build(BuildContext context) {
    final idx = _states.indexOf(status).clamp(0, _states.length - 1);
    final progress = (idx + 1) / _states.length;

    return Column(children: [
      ClipRRect(
        borderRadius: EwTheme.radius4,
        child: LinearProgressIndicator(
          value: progress,
          minHeight: 6,
          backgroundColor: EwTheme.bg,
          valueColor: const AlwaysStoppedAnimation<Color>(EwTheme.orange),
        ),
      ),
      const SizedBox(height: 4),
      Align(alignment: Alignment.centerRight, child: Text('Step ${idx + 1} of ${_states.length}', style: EwTheme.bodySmall.copyWith(fontSize: 10))),
    ]);
  }
}

// ─── Saved Lists Screen ───────────────────────────────────────────────────────

class EwSavedListsScreen extends ConsumerWidget {
  const EwSavedListsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final listsAsync = ref.watch(ewSavedListsProvider);

    return Scaffold(
      backgroundColor: EwTheme.bg,
      appBar: AppBar(
        backgroundColor: EwTheme.navy,
        foregroundColor: Colors.white,
        title: const Text('Saved Lists', style: TextStyle(fontWeight: FontWeight.w700)),
        actions: [
          IconButton(
            icon: const Icon(Icons.add),
            onPressed: () => _createList(context, ref),
          ),
        ],
      ),
      body: RefreshIndicator(
        color: EwTheme.orange,
        onRefresh: () => ref.refresh(ewSavedListsProvider.future),
        child: listsAsync.when(
          loading: () => const Center(child: CircularProgressIndicator(color: EwTheme.orange)),
          error:   (e, _) => EwErrorRetry(error: e, onRetry: () => ref.refresh(ewSavedListsProvider.future)),
          data: (lists) => lists.isEmpty
            ? EwEmptyState(icon: Icons.bookmark_border, title: 'No saved lists', subtitle: 'Create lists to save products for quick reordering',
                actionLabel: 'Create List', onAction: () => _createList(context, ref))
            : ListView.builder(
                padding: const EdgeInsets.all(12),
                itemCount: lists.length,
                itemBuilder: (_, i) => _SavedListTile(list: lists[i]),
              ),
        ),
      ),
    );
  }

  void _createList(BuildContext context, WidgetRef ref) {
    final ctrl = TextEditingController();
    showDialog(context: context, builder: (_) => AlertDialog(
      title: const Text('New List'),
      content: TextField(controller: ctrl, decoration: const InputDecoration(hintText: 'List name')),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
        ElevatedButton(
          onPressed: () async {
            if (ctrl.text.trim().isEmpty) return;
            await ref.read(ewRepoProvider).createSavedList(ctrl.text.trim());
            ref.invalidate(ewSavedListsProvider);
            if (context.mounted) Navigator.pop(context);
          },
          child: const Text('Create'),
        ),
      ],
    ));
  }
}

class _SavedListTile extends StatelessWidget {
  final EwSavedList list;
  const _SavedListTile({required this.list});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(color: EwTheme.surface, borderRadius: EwTheme.radius12,
        border: Border.all(color: EwTheme.border), boxShadow: EwTheme.cardShadow),
      child: Material(color: Colors.transparent, borderRadius: EwTheme.radius12,
        child: InkWell(borderRadius: EwTheme.radius12, onTap: () => context.push('/ewholesale/lists/${list.id}'),
          child: Padding(padding: const EdgeInsets.all(14), child: Row(children: [
            Container(width: 44, height: 44, decoration: BoxDecoration(color: EwTheme.navy.withOpacity(0.06), borderRadius: EwTheme.radius8),
              child: const Icon(Icons.bookmark_border, color: EwTheme.navy, size: 22)),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(list.name, style: EwTheme.heading3),
              Text('${list.itemCount} item${list.itemCount != 1 ? 's' : ''}', style: EwTheme.bodySmall),
            ])),
            const Icon(Icons.chevron_right, color: EwTheme.textMuted),
          ])),
        ),
      ),
    );
  }
}
