import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/theme/vc.dart';
import 'wholesale_repository.dart';

// ── Providers ─────────────────────────────────────────────────────────────────

final _meProvider = FutureProvider.autoDispose<Map<String, dynamic>?>((ref) =>
    WholesaleRepository.instance.me());

final _dashProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) =>
    WholesaleRepository.instance.dashboard());

final _productsProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) =>
    WholesaleRepository.instance.products());

final _ordersProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) =>
    WholesaleRepository.instance.orders());

final _rfqsProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) =>
    WholesaleRepository.instance.rfqs());

final _inquiriesProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) =>
    WholesaleRepository.instance.inquiries());

// ── Main Wholesale Screen ─────────────────────────────────────────────────────

class WholesaleScreen extends ConsumerStatefulWidget {
  const WholesaleScreen({super.key});
  @override
  ConsumerState<WholesaleScreen> createState() => _WholesaleScreenState();
}

class _WholesaleScreenState extends ConsumerState<WholesaleScreen> with SingleTickerProviderStateMixin {
  late TabController _tab;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 4, vsync: this);
  }

  @override
  void dispose() { _tab.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final meAsync = ref.watch(_meProvider);
    final isDark  = Theme.of(context).brightness == Brightness.dark;
    final bg      = isDark ? VC.navy : VC.lightBg;
    final surface = isDark ? VC.navyCard : VC.lightSurface;

    return meAsync.when(
      loading: () => const Scaffold(body: Center(child: CircularProgressIndicator(color: VC.orange))),
      error:   (e, _) => _NotRegisteredView(),
      data: (me) {
        if (me == null) return _NotRegisteredView();
        final isPending = me['verification'] == 'pending';

        return Scaffold(
          backgroundColor: bg,
          appBar: AppBar(
            backgroundColor: isDark ? VC.navyLight : Colors.white,
            elevation: 0,
            title: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Wholesale Portal', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: VC.orange)),
              Text(me['display_name'] ?? '', style: TextStyle(fontSize: 11, color: isDark ? VC.textMuted : Colors.grey.shade600)),
            ]),
            actions: [
              _VerificationBadge(status: me['verification'] ?? 'pending'),
              const SizedBox(width: 12),
            ],
            bottom: TabBar(
              controller: _tab,
              labelColor: VC.orange,
              unselectedLabelColor: isDark ? VC.textMuted : Colors.grey.shade500,
              indicatorColor: VC.orange,
              indicatorSize: TabBarIndicatorSize.label,
              labelStyle: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
              tabs: const [
                Tab(icon: Icon(Icons.dashboard_rounded, size: 18), text: 'Dashboard'),
                Tab(icon: Icon(Icons.inventory_2_rounded, size: 18), text: 'Products'),
                Tab(icon: Icon(Icons.receipt_long_rounded, size: 18), text: 'Orders'),
                Tab(icon: Icon(Icons.request_quote_rounded, size: 18), text: 'RFQs'),
              ],
            ),
          ),
          body: isPending
              ? _PendingApprovalBanner(child: _buildTabs(surface, isDark))
              : _buildTabs(surface, isDark),
        );
      },
    );
  }

  Widget _buildTabs(Color surface, bool isDark) {
    return TabBarView(controller: _tab, children: [
      _DashboardTab(surface: surface, isDark: isDark),
      _ProductsTab(surface: surface, isDark: isDark),
      _OrdersTab(surface: surface, isDark: isDark),
      _RfqsTab(surface: surface, isDark: isDark),
    ]);
  }
}

// ── Not Registered ─────────────────────────────────────────────────────────────

class _NotRegisteredView extends ConsumerStatefulWidget {
  @override
  ConsumerState<_NotRegisteredView> createState() => _NotRegisteredViewState();
}

class _NotRegisteredViewState extends ConsumerState<_NotRegisteredView> {
  final _nameCtrl = TextEditingController();
  final _aboutCtrl = TextEditingController();
  final _addrCtrl  = TextEditingController();
  bool _loading = false;
  String? _error;

  Future<void> _submit() async {
    if (_nameCtrl.text.trim().isEmpty) return;
    setState(() { _loading = true; _error = null; });
    try {
      await WholesaleRepository.instance.register({
        'display_name': _nameCtrl.text.trim(),
        'about': _aboutCtrl.text.trim(),
        'warehouse_address': _addrCtrl.text.trim(),
      });
      ref.invalidate(_meProvider);
    } catch (e) {
      setState(() { _error = e.toString(); });
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Scaffold(
      backgroundColor: isDark ? VC.navy : VC.lightBg,
      appBar: AppBar(
        backgroundColor: isDark ? VC.navyLight : Colors.white,
        title: const Text('Wholesale Portal', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: VC.orange)),
        elevation: 0,
      ),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        const SizedBox(height: 20),
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(color: VC.orangeDim, borderRadius: BorderRadius.circular(12),
            border: Border.all(color: VC.orange.withValues(alpha: 0.3))),
          child: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Become a Wholesale Supplier', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: VC.orange)),
            SizedBox(height: 8),
            Text('Register your business to list products, receive bulk orders, and respond to buyer RFQs. Admin will review and verify your profile.',
              style: TextStyle(fontSize: 13, color: Colors.black87, height: 1.5)),
          ]),
        ),
        const SizedBox(height: 24),
        if (_error != null) ...[
          Container(padding: const EdgeInsets.all(12), margin: const EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(color: VC.redDim, borderRadius: BorderRadius.circular(8)),
            child: Text(_error!, style: const TextStyle(color: VC.red, fontSize: 13))),
        ],
        _field(ctrl: _nameCtrl, label: 'Business / Store Name *', hint: 'e.g. Banadir Import & Export'),
        const SizedBox(height: 14),
        _field(ctrl: _aboutCtrl, label: 'About your Business', hint: 'Products you sell, expertise...', maxLines: 3),
        const SizedBox(height: 14),
        _field(ctrl: _addrCtrl, label: 'Warehouse / Address', hint: 'e.g. Hamarweyne, Mogadishu'),
        const SizedBox(height: 28),
        SizedBox(
          width: double.infinity,
          height: 52,
          child: ElevatedButton(
            onPressed: _loading ? null : _submit,
            style: ElevatedButton.styleFrom(backgroundColor: VC.orange, foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
            child: _loading
              ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
              : const Text('Register as Supplier', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
          ),
        ),
      ]),
    );
  }

  Widget _field({required TextEditingController ctrl, required String label, String? hint, int maxLines = 1}) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: isDark ? Colors.white70 : Colors.grey.shade700)),
      const SizedBox(height: 6),
      TextField(
        controller: ctrl, maxLines: maxLines,
        style: TextStyle(fontSize: 14, color: isDark ? Colors.white : Colors.black87),
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: TextStyle(color: isDark ? Colors.white30 : Colors.grey.shade400),
          filled: true,
          fillColor: isDark ? VC.navyCard : Colors.white,
          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide(color: isDark ? VC.border : Colors.grey.shade300)),
          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide(color: isDark ? VC.border : Colors.grey.shade300)),
          focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: VC.orange)),
        ),
      ),
    ]);
  }
}

// ── Pending Banner ─────────────────────────────────────────────────────────────

class _PendingApprovalBanner extends StatelessWidget {
  final Widget child;
  const _PendingApprovalBanner({required this.child});
  @override
  Widget build(BuildContext context) => Column(children: [
    Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      color: VC.amber.withValues(alpha: 0.15),
      child: Row(children: [
        const Icon(Icons.hourglass_top_rounded, color: VC.amber, size: 16),
        const SizedBox(width: 8),
        const Expanded(child: Text('Your supplier application is under review. Admin will verify your account shortly.',
          style: TextStyle(color: VC.amber, fontSize: 12, fontWeight: FontWeight.w600))),
      ]),
    ),
    Expanded(child: child),
  ]);
}

// ── Dashboard Tab ──────────────────────────────────────────────────────────────

class _DashboardTab extends ConsumerWidget {
  final Color surface; final bool isDark;
  const _DashboardTab({required this.surface, required this.isDark});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashAsync = ref.watch(_dashProvider);
    return RefreshIndicator(
      color: VC.orange,
      onRefresh: () => ref.refresh(_dashProvider.future),
      child: dashAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: VC.orange)),
        error:   (e, _) => Center(child: Text('Error: $e', style: const TextStyle(color: VC.red))),
        data: (d) => ListView(padding: const EdgeInsets.all(16), children: [
          // KPI grid
          GridView.count(
            crossAxisCount: 2, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(),
            crossAxisSpacing: 12, mainAxisSpacing: 12, childAspectRatio: 1.6,
            children: [
              _kpiCard('Pending Orders',     '${d['pendingOrders'] ?? 0}',    Icons.pending_actions_rounded, VC.amber,  surface, isDark),
              _kpiCard('Active Orders',      '${d['activeOrders'] ?? 0}',     Icons.local_shipping_rounded,  VC.blue,   surface, isDark),
              _kpiCard('GMV (30 days)',      '\$${_fmt(d['gmv30'])}',          Icons.trending_up_rounded,     VC.green,  surface, isDark),
              _kpiCard('Open Inquiries',     '${d['openInquiries'] ?? 0}',    Icons.chat_bubble_outline_rounded, VC.purple, surface, isDark),
              _kpiCard('Total Products',     '${d['productsCount'] ?? 0}',    Icons.inventory_2_rounded,     VC.orange, surface, isDark),
              _kpiCard('Total GMV',          '\$${_fmt(d['gmvTotal'])}',       Icons.account_balance_wallet_rounded, VC.green, surface, isDark),
            ],
          ),

          const SizedBox(height: 24),

          // Recent orders
          if ((d['recentOrders'] as List? ?? []).isNotEmpty) ...[
            _sectionHeader('Recent Orders', isDark),
            const SizedBox(height: 10),
            ...((d['recentOrders'] as List).take(5).map((o) => _miniOrderRow(o, surface, isDark))),
          ],
        ]),
      ),
    );
  }

  String _fmt(dynamic v) {
    if (v == null) return '0';
    final n = (v is num) ? v.toDouble() : double.tryParse(v.toString()) ?? 0;
    if (n >= 1000) return '${(n / 1000).toStringAsFixed(1)}K';
    return n.toStringAsFixed(0);
  }

  Widget _kpiCard(String label, String val, IconData icon, Color color, Color surface, bool isDark) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: surface, borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.2))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Icon(icon, color: color, size: 18),
          const Spacer(),
        ]),
        const Spacer(),
        Text(val, style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: color)),
        Text(label, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: isDark ? Colors.white54 : Colors.grey.shade600)),
      ]),
    );
  }

  Widget _sectionHeader(String title, bool isDark) => Text(title,
    style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: isDark ? Colors.white : Colors.black87));

  Widget _miniOrderRow(Map o, Color surface, bool isDark) => Container(
    margin: const EdgeInsets.only(bottom: 8),
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
    decoration: BoxDecoration(color: surface, borderRadius: BorderRadius.circular(8)),
    child: Row(children: [
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(o['order_no'] ?? '#—', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, fontFamily: 'monospace')),
        Text(o['buyer_name'] ?? '—', style: TextStyle(fontSize: 11, color: isDark ? Colors.white54 : Colors.grey.shade600)),
      ])),
      Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
        Text('\$${o['total'] ?? 0}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: VC.green)),
        _statusBadge(o['status'] ?? ''),
      ]),
    ]),
  );

  Widget _statusBadge(String status) {
    final colors = {
      'pending_confirmation': [VC.amberDim, VC.amber],
      'processing':           [VC.blueDim,  VC.blue],
      'shipped':              [VC.greenDim, VC.green],
      'completed':            [VC.greenDim, VC.green],
      'cancelled':            [VC.redDim,   VC.red],
    };
    final c = colors[status] ?? [VC.blueDim, VC.blue];
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(color: c[0] as Color, borderRadius: BorderRadius.circular(6)),
      child: Text(status.replaceAll('_', ' '), style: TextStyle(fontSize: 9, fontWeight: FontWeight.w700, color: c[1] as Color)),
    );
  }
}

// ── Products Tab ───────────────────────────────────────────────────────────────

class _ProductsTab extends ConsumerStatefulWidget {
  final Color surface; final bool isDark;
  const _ProductsTab({required this.surface, required this.isDark});
  @override
  ConsumerState<_ProductsTab> createState() => _ProductsTabState();
}

class _ProductsTabState extends ConsumerState<_ProductsTab> {
  @override
  Widget build(BuildContext context) {
    final productsAsync = ref.watch(_productsProvider);
    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: VC.orange,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add),
        label: const Text('Add Product', style: TextStyle(fontWeight: FontWeight.w700)),
        onPressed: () => _showAddProduct(context),
      ),
      body: RefreshIndicator(
        color: VC.orange,
        onRefresh: () => ref.refresh(_productsProvider.future),
        child: productsAsync.when(
          loading: () => const Center(child: CircularProgressIndicator(color: VC.orange)),
          error:   (e, _) => Center(child: Text('Error: $e')),
          data: (products) => products.isEmpty
            ? _emptyState('No products yet', 'Add your first wholesale product', Icons.inventory_2_outlined)
            : ListView.builder(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 80),
                itemCount: products.length,
                itemBuilder: (_, i) => _ProductCard(product: products[i], surface: widget.surface, isDark: widget.isDark,
                  onToggle: () async {
                    await WholesaleRepository.instance.toggleProduct(products[i]['id']);
                    ref.invalidate(_productsProvider);
                  }),
              ),
        ),
      ),
    );
  }

  void _showAddProduct(BuildContext context) {
    showModalBottomSheet(
      context: context, isScrollControlled: true, useSafeArea: true,
      backgroundColor: widget.isDark ? VC.navyCard : Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _AddProductSheet(onSaved: () => ref.invalidate(_productsProvider)),
    );
  }
}

class _ProductCard extends StatelessWidget {
  final Map<String, dynamic> product;
  final Color surface; final bool isDark;
  final VoidCallback onToggle;
  const _ProductCard({required this.product, required this.surface, required this.isDark, required this.onToggle});

  @override
  Widget build(BuildContext context) {
    final isActive = product['status'] == 'active';
    final isPending = product['status'] == 'pending_review';
    final statusColor = isActive ? VC.green : (isPending ? VC.amber : VC.red);

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: surface, borderRadius: BorderRadius.circular(12),
        border: Border.all(color: isDark ? VC.border : Colors.grey.shade200)),
      child: Row(children: [
        // Status dot
        Container(width: 8, height: 8, decoration: BoxDecoration(color: statusColor, shape: BoxShape.circle)),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(product['name'] ?? '—', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700,
            color: isDark ? Colors.white : Colors.black87), maxLines: 1, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 3),
          Row(children: [
            Text('\$${product['min_price'] ?? 0}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: VC.green)),
            Text('  ·  MOQ: ${product['moq'] ?? 1} ${product['unit'] ?? 'pc'}',
              style: TextStyle(fontSize: 11, color: isDark ? Colors.white54 : Colors.grey.shade600)),
          ]),
          Text(product['category_name'] ?? '—', style: TextStyle(fontSize: 11, color: isDark ? Colors.white38 : Colors.grey.shade500)),
        ])),
        const SizedBox(width: 8),
        Column(children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(color: statusColor.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(6)),
            child: Text((product['status'] ?? '').replaceAll('_', ' '),
              style: TextStyle(fontSize: 9, fontWeight: FontWeight.w700, color: statusColor))),
          if (!isPending) ...[
            const SizedBox(height: 6),
            GestureDetector(
              onTap: onToggle,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: isDark ? VC.border : Colors.grey.shade100, borderRadius: BorderRadius.circular(6)),
                child: Text(isActive ? 'Pause' : 'Activate',
                  style: TextStyle(fontSize: 9, fontWeight: FontWeight.w700, color: isDark ? Colors.white70 : Colors.grey.shade700)),
              ),
            ),
          ],
        ]),
      ]),
    );
  }
}

// ── Orders Tab ─────────────────────────────────────────────────────────────────

class _OrdersTab extends ConsumerWidget {
  final Color surface; final bool isDark;
  const _OrdersTab({required this.surface, required this.isDark});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ordersAsync = ref.watch(_ordersProvider);
    return RefreshIndicator(
      color: VC.orange,
      onRefresh: () => ref.refresh(_ordersProvider.future),
      child: ordersAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: VC.orange)),
        error:   (e, _) => Center(child: Text('Error: $e')),
        data: (orders) => orders.isEmpty
          ? _emptyState('No orders yet', 'Wholesale orders will appear here', Icons.receipt_long_outlined)
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: orders.length,
              itemBuilder: (_, i) => _OrderCard(
                order: orders[i], surface: surface, isDark: isDark,
                onAction: (status) async {
                  try {
                    if (status == 'confirm') {
                      await WholesaleRepository.instance.confirmOrder(orders[i]['id']);
                    } else {
                      await WholesaleRepository.instance.updateOrderStatus(orders[i]['id'], status);
                    }
                    ref.invalidate(_ordersProvider);
                    ref.invalidate(_dashProvider);
                  } catch (e) {
                    if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e'), backgroundColor: VC.red));
                  }
                },
              ),
            ),
      ),
    );
  }
}

class _OrderCard extends StatelessWidget {
  final Map<String, dynamic> order;
  final Color surface; final bool isDark;
  final Future<void> Function(String) onAction;
  const _OrderCard({required this.order, required this.surface, required this.isDark, required this.onAction});

  @override
  Widget build(BuildContext context) {
    final status = order['status'] ?? '';
    final canConfirm = status == 'pending_confirmation';
    final canShip    = status == 'processing';
    final canDeliver = status == 'shipped';

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: surface, borderRadius: BorderRadius.circular(12),
        border: Border.all(color: isDark ? VC.border : Colors.grey.shade200)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Text(order['order_no'] ?? '#—', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800, fontFamily: 'monospace')),
          const Spacer(),
          Text('\$${order['total'] ?? 0}', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: VC.green)),
        ]),
        const SizedBox(height: 4),
        Row(children: [
          Icon(Icons.person_outline, size: 13, color: isDark ? Colors.white54 : Colors.grey.shade500),
          const SizedBox(width: 4),
          Text(order['buyer_name'] ?? '—', style: TextStyle(fontSize: 12, color: isDark ? Colors.white54 : Colors.grey.shade600)),
          const Spacer(),
          _statusBadge(status),
        ]),
        if (canConfirm || canShip || canDeliver) ...[
          const SizedBox(height: 10),
          const Divider(height: 1),
          const SizedBox(height: 10),
          Row(children: [
            if (canConfirm) _actionBtn('Confirm Order', VC.green, () => onAction('confirm')),
            if (canShip)    _actionBtn('Mark Shipped',  VC.blue,  () => onAction('shipped')),
            if (canDeliver) _actionBtn('Mark Delivered', VC.green, () => onAction('delivered')),
          ]),
        ],
      ]),
    );
  }

  Widget _actionBtn(String label, Color color, VoidCallback onTap) => Expanded(child: Padding(
    padding: const EdgeInsets.only(right: 8),
    child: ElevatedButton(
      onPressed: onTap,
      style: ElevatedButton.styleFrom(backgroundColor: color, foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(vertical: 8),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))),
      child: Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
    ),
  ));

  Widget _statusBadge(String status) {
    final color = {
      'pending_confirmation': VC.amber,
      'processing': VC.blue,
      'shipped': VC.purple,
      'delivered': VC.green,
      'completed': VC.green,
      'cancelled': VC.red,
    }[status] ?? VC.blue;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(6)),
      child: Text(status.replaceAll('_', ' '), style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: color)),
    );
  }
}

// ── RFQs Tab ───────────────────────────────────────────────────────────────────

class _RfqsTab extends ConsumerWidget {
  final Color surface; final bool isDark;
  const _RfqsTab({required this.surface, required this.isDark});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final rfqsAsync      = ref.watch(_rfqsProvider);
    final inquiriesAsync = ref.watch(_inquiriesProvider);

    return DefaultTabController(
      length: 2,
      child: Column(children: [
        Container(
          color: isDark ? VC.navyLight : Colors.white,
          child: const TabBar(
            labelColor: VC.orange, indicatorColor: VC.orange,
            tabs: [Tab(text: 'Open RFQs'), Tab(text: 'Inquiries')],
          ),
        ),
        Expanded(child: TabBarView(children: [
          // RFQs
          RefreshIndicator(
            color: VC.orange,
            onRefresh: () => ref.refresh(_rfqsProvider.future),
            child: rfqsAsync.when(
              loading: () => const Center(child: CircularProgressIndicator(color: VC.orange)),
              error:   (e, _) => Center(child: Text('Error: $e')),
              data: (rfqs) => rfqs.isEmpty
                ? _emptyState('No open RFQs', 'Buyer RFQs matching your category will appear here', Icons.request_quote_outlined)
                : ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: rfqs.length,
                    itemBuilder: (_, i) => _RfqCard(
                      rfq: rfqs[i], surface: surface, isDark: isDark,
                      onQuote: (data) async {
                        await WholesaleRepository.instance.submitRfqQuote(rfqs[i]['id'], data);
                        ref.invalidate(_rfqsProvider);
                      },
                    ),
                  ),
            ),
          ),
          // Inquiries
          RefreshIndicator(
            color: VC.orange,
            onRefresh: () => ref.refresh(_inquiriesProvider.future),
            child: inquiriesAsync.when(
              loading: () => const Center(child: CircularProgressIndicator(color: VC.orange)),
              error:   (e, _) => Center(child: Text('Error: $e')),
              data: (inquiries) => inquiries.isEmpty
                ? _emptyState('No inquiries', 'Product inquiries from buyers will appear here', Icons.chat_bubble_outline_rounded)
                : ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: inquiries.length,
                    itemBuilder: (_, i) => _InquiryCard(
                      inquiry: inquiries[i], surface: surface, isDark: isDark,
                      onQuote: (data) async {
                        await WholesaleRepository.instance.sendQuote(inquiries[i]['id'], data);
                        ref.invalidate(_inquiriesProvider);
                      },
                    ),
                  ),
            ),
          ),
        ])),
      ]),
    );
  }
}

class _RfqCard extends StatelessWidget {
  final Map<String, dynamic> rfq;
  final Color surface; final bool isDark;
  final Future<void> Function(Map<String, dynamic>) onQuote;
  const _RfqCard({required this.rfq, required this.surface, required this.isDark, required this.onQuote});

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 10),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(color: surface, borderRadius: BorderRadius.circular(12),
      border: Border.all(color: isDark ? VC.border : Colors.grey.shade200)),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Expanded(child: Text(rfq['title'] ?? '—', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700,
          color: isDark ? Colors.white : Colors.black87), maxLines: 2, overflow: TextOverflow.ellipsis)),
        _chip(rfq['status'] ?? 'open', VC.blue),
      ]),
      const SizedBox(height: 6),
      Text('${rfq['qty'] ?? 0} ${rfq['unit'] ?? 'pc'}  ·  ${rfq['category_name'] ?? '—'}',
        style: TextStyle(fontSize: 12, color: isDark ? Colors.white54 : Colors.grey.shade600)),
      if (rfq['target_price'] != null)
        Text('Target: \$${rfq['target_price']}', style: const TextStyle(fontSize: 12, color: VC.green, fontWeight: FontWeight.w600)),
      const SizedBox(height: 10),
      SizedBox(
        width: double.infinity,
        child: OutlinedButton.icon(
          icon: const Icon(Icons.send_rounded, size: 15),
          label: const Text('Submit Quote', style: TextStyle(fontWeight: FontWeight.w700)),
          style: OutlinedButton.styleFrom(foregroundColor: VC.orange, side: const BorderSide(color: VC.orange),
            padding: const EdgeInsets.symmetric(vertical: 8), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))),
          onPressed: () => _showQuoteSheet(context),
        ),
      ),
    ]),
  );

  void _showQuoteSheet(BuildContext context) => showModalBottomSheet(
    context: context, isScrollControlled: true, useSafeArea: true,
    backgroundColor: isDark ? VC.navyCard : Colors.white,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
    builder: (_) => _QuoteSheet(label: 'Submit Quote for RFQ', onSubmit: onQuote),
  );

  Widget _chip(String text, Color color) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
    decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
    child: Text(text, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: color)),
  );
}

class _InquiryCard extends StatelessWidget {
  final Map<String, dynamic> inquiry;
  final Color surface; final bool isDark;
  final Future<void> Function(Map<String, dynamic>) onQuote;
  const _InquiryCard({required this.inquiry, required this.surface, required this.isDark, required this.onQuote});

  @override
  Widget build(BuildContext context) {
    final alreadyQuoted = inquiry['status'] == 'quoted';
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: surface, borderRadius: BorderRadius.circular(12),
        border: Border.all(color: isDark ? VC.border : Colors.grey.shade200)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text(inquiry['product_name'] ?? '—', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700,
            color: isDark ? Colors.white : Colors.black87))),
          _chip(inquiry['status'] ?? 'open', alreadyQuoted ? VC.green : VC.amber),
        ]),
        const SizedBox(height: 4),
        Text('Qty: ${inquiry['qty'] ?? 0}  ·  ${inquiry['message'] ?? ''}',
          style: TextStyle(fontSize: 12, color: isDark ? Colors.white54 : Colors.grey.shade600), maxLines: 2, overflow: TextOverflow.ellipsis),
        if (!alreadyQuoted) ...[
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              icon: const Icon(Icons.reply_rounded, size: 15),
              label: const Text('Send Quote', style: TextStyle(fontWeight: FontWeight.w700)),
              style: OutlinedButton.styleFrom(foregroundColor: VC.orange, side: const BorderSide(color: VC.orange),
                padding: const EdgeInsets.symmetric(vertical: 8), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))),
              onPressed: () => _showQuoteSheet(context),
            ),
          ),
        ],
      ]),
    );
  }

  void _showQuoteSheet(BuildContext context) => showModalBottomSheet(
    context: context, isScrollControlled: true, useSafeArea: true,
    backgroundColor: isDark ? VC.navyCard : Colors.white,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
    builder: (_) => _QuoteSheet(label: 'Send Quote', onSubmit: onQuote),
  );

  Widget _chip(String text, Color color) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
    decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
    child: Text(text, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: color)),
  );
}

// ── Quote Sheet ────────────────────────────────────────────────────────────────

class _QuoteSheet extends StatefulWidget {
  final String label;
  final Future<void> Function(Map<String, dynamic>) onSubmit;
  const _QuoteSheet({required this.label, required this.onSubmit});
  @override
  State<_QuoteSheet> createState() => _QuoteSheetState();
}

class _QuoteSheetState extends State<_QuoteSheet> {
  final _priceCtrl   = TextEditingController();
  final _leadCtrl    = TextEditingController();
  final _noteCtrl    = TextEditingController();
  bool _loading = false;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.of(context).viewInsets.bottom + 20),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(widget.label, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
        const SizedBox(height: 16),
        Row(children: [
          Expanded(child: _input(_priceCtrl, 'Unit Price (\$) *', TextInputType.number, isDark)),
          const SizedBox(width: 12),
          Expanded(child: _input(_leadCtrl, 'Lead Time (days)', TextInputType.number, isDark)),
        ]),
        const SizedBox(height: 12),
        _input(_noteCtrl, 'Note / Terms', TextInputType.text, isDark, maxLines: 2),
        const SizedBox(height: 20),
        SizedBox(
          width: double.infinity, height: 48,
          child: ElevatedButton(
            onPressed: _loading ? null : _submit,
            style: ElevatedButton.styleFrom(backgroundColor: VC.orange, foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
            child: _loading
              ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
              : Text('Send', style: const TextStyle(fontWeight: FontWeight.w700)),
          ),
        ),
      ]),
    );
  }

  Widget _input(TextEditingController ctrl, String label, TextInputType keyboardType, bool isDark, {int maxLines = 1}) =>
    TextField(
      controller: ctrl, keyboardType: keyboardType, maxLines: maxLines,
      style: TextStyle(fontSize: 14, color: isDark ? Colors.white : Colors.black87),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: TextStyle(fontSize: 12, color: isDark ? Colors.white54 : Colors.grey.shade600),
        filled: true, fillColor: isDark ? VC.navyCard : Colors.grey.shade50,
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide(color: isDark ? VC.border : Colors.grey.shade300)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide(color: isDark ? VC.border : Colors.grey.shade300)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: VC.orange)),
      ),
    );

  Future<void> _submit() async {
    if (_priceCtrl.text.isEmpty) return;
    setState(() => _loading = true);
    try {
      await widget.onSubmit({
        'unit_price': double.tryParse(_priceCtrl.text) ?? 0,
        if (_leadCtrl.text.isNotEmpty) 'lead_time_days': int.tryParse(_leadCtrl.text),
        if (_noteCtrl.text.isNotEmpty) 'note': _noteCtrl.text,
      });
      if (mounted) Navigator.pop(context);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e'), backgroundColor: VC.red));
    } finally {
      setState(() => _loading = false);
    }
  }
}

// ── Add Product Sheet ─────────────────────────────────────────────────────────

class _AddProductSheet extends StatefulWidget {
  final VoidCallback onSaved;
  const _AddProductSheet({required this.onSaved});
  @override
  State<_AddProductSheet> createState() => _AddProductSheetState();
}

class _AddProductSheetState extends State<_AddProductSheet> {
  final _nameCtrl  = TextEditingController();
  final _descCtrl  = TextEditingController();
  final _unitCtrl  = TextEditingController(text: 'piece');
  final _moqCtrl   = TextEditingController(text: '1');
  final _priceCtrl = TextEditingController();
  bool _loading = false;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.of(context).viewInsets.bottom + 20),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Add New Product', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
        const SizedBox(height: 16),
        _input(_nameCtrl, 'Product Name *', TextInputType.text, isDark),
        const SizedBox(height: 10),
        _input(_descCtrl, 'Description', TextInputType.multiline, isDark, maxLines: 2),
        const SizedBox(height: 10),
        Row(children: [
          Expanded(child: _input(_unitCtrl, 'Unit', TextInputType.text, isDark)),
          const SizedBox(width: 10),
          Expanded(child: _input(_moqCtrl, 'Min Order Qty', TextInputType.number, isDark)),
          const SizedBox(width: 10),
          Expanded(child: _input(_priceCtrl, 'Base Price (\$)', TextInputType.number, isDark)),
        ]),
        const SizedBox(height: 6),
        Text('Product will be submitted for admin review before going live.',
          style: TextStyle(fontSize: 11, color: VC.amber)),
        const SizedBox(height: 16),
        SizedBox(
          width: double.infinity, height: 48,
          child: ElevatedButton(
            onPressed: _loading ? null : _submit,
            style: ElevatedButton.styleFrom(backgroundColor: VC.orange, foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
            child: _loading
              ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
              : const Text('Submit for Review', style: TextStyle(fontWeight: FontWeight.w700)),
          ),
        ),
      ]),
    );
  }

  Widget _input(TextEditingController ctrl, String label, TextInputType keyboardType, bool isDark, {int maxLines = 1}) =>
    TextField(
      controller: ctrl, keyboardType: keyboardType, maxLines: maxLines,
      style: TextStyle(fontSize: 14, color: isDark ? Colors.white : Colors.black87),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: TextStyle(fontSize: 12, color: isDark ? Colors.white54 : Colors.grey.shade600),
        filled: true, fillColor: isDark ? VC.navyCard : Colors.grey.shade50,
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide(color: isDark ? VC.border : Colors.grey.shade300)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide(color: isDark ? VC.border : Colors.grey.shade300)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: VC.orange)),
      ),
    );

  Future<void> _submit() async {
    if (_nameCtrl.text.trim().isEmpty || _priceCtrl.text.isEmpty) return;
    setState(() => _loading = true);
    try {
      await WholesaleRepository.instance.createProduct({
        'name':        _nameCtrl.text.trim(),
        'description': _descCtrl.text.trim(),
        'unit':        _unitCtrl.text.trim(),
        'moq':         double.tryParse(_moqCtrl.text) ?? 1,
        'price_tiers': [{'min_qty': 1, 'unit_price': double.tryParse(_priceCtrl.text) ?? 0}],
      });
      if (mounted) { Navigator.pop(context); widget.onSaved(); }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e'), backgroundColor: VC.red));
    } finally {
      setState(() => _loading = false);
    }
  }
}

// ── Verification Badge ─────────────────────────────────────────────────────────

class _VerificationBadge extends StatelessWidget {
  final String status;
  const _VerificationBadge({required this.status});
  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (status) {
      'gold'     => ('★ GOLD',     VC.amber),
      'verified' => ('✓ VERIFIED', VC.green),
      'pending'  => ('⏳ PENDING',  VC.amber),
      _          => ('UNVERIFIED', Colors.grey),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(6)),
      child: Text(label, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: color)),
    );
  }
}

// ── Helpers ─────────────────────────────────────────────────────────────────────

Widget _emptyState(String title, String subtitle, IconData icon) => Center(
  child: Padding(
    padding: const EdgeInsets.all(32),
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, size: 56, color: Colors.grey.shade400),
      const SizedBox(height: 16),
      Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
      const SizedBox(height: 6),
      Text(subtitle, textAlign: TextAlign.center, style: TextStyle(fontSize: 13, color: Colors.grey.shade500, height: 1.4)),
    ]),
  ),
);
