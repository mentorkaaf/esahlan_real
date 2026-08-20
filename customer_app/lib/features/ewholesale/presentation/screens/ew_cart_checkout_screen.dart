import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/ew_provider.dart';
import '../ui/ew_theme.dart';
import '../ui/widgets/ew_widgets.dart';
import '../../data/models/ew_models.dart';

// ─── Cart Screen ──────────────────────────────────────────────────────────────

class EwCartScreen extends ConsumerWidget {
  const EwCartScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final lines    = ref.watch(ewCartProvider);
    final validated = ref.watch(ewCartValidateProvider);

    if (lines.isEmpty) {
      return Scaffold(
        backgroundColor: EwTheme.bg,
        appBar: AppBar(backgroundColor: EwTheme.navy, foregroundColor: Colors.white, title: const Text('Cart')),
        body: EwEmptyState(
          icon: Icons.shopping_cart_outlined,
          title: 'Your cart is empty',
          subtitle: 'Add products from the catalog',
          actionLabel: 'Browse Products',
          onAction: () => context.push('/ewholesale/products'),
        ),
      );
    }

    return Scaffold(
      backgroundColor: EwTheme.bg,
      appBar: AppBar(
        backgroundColor: EwTheme.navy,
        foregroundColor: Colors.white,
        title: const Text('Cart', style: TextStyle(fontWeight: FontWeight.w700)),
        actions: [
          TextButton(
            onPressed: () { ref.read(ewCartProvider.notifier).clear(); },
            child: const Text('Clear', style: TextStyle(color: Colors.white70)),
          ),
        ],
      ),
      body: Column(children: [
        Expanded(child: validated.when(
          loading: () => const Center(child: CircularProgressIndicator(color: EwTheme.orange)),
          error:   (e, _) => EwErrorRetry(error: e, onRetry: () => ref.refresh(ewCartValidateProvider)),
          data:    (result) {
            if (result == null) return const SizedBox.shrink();
            return ListView(padding: const EdgeInsets.all(12), children: [
              // Errors
              if (result.errors.isNotEmpty) _ErrorBanner(errors: result.errors),
              // Warnings
              if (result.warnings.isNotEmpty) _WarningBanner(warnings: result.warnings),
              // Groups
              ...result.groups.map((g) => _SupplierGroup(group: g, ref: ref)),
              const SizedBox(height: 8),
              // Grand total
              Container(
                decoration: BoxDecoration(color: EwTheme.surface, borderRadius: EwTheme.radius12, border: Border.all(color: EwTheme.border)),
                padding: const EdgeInsets.all(14),
                child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  const Text('Grand Total', style: EwTheme.heading3),
                  Text(EwTheme.formatPrice(result.grandTotal), style: EwTheme.price.copyWith(fontSize: 18, color: EwTheme.orange)),
                ]),
              ),
            ]);
          },
        )),
        // ── Checkout button ─────────────────────────────────────────────────
        validated.when(
          data: (result) {
            if (result == null || result.hasErrors) return const SizedBox.shrink();
            return _CheckoutBar(groups: result.groups);
          },
          loading: () => const SizedBox.shrink(),
          error:   (_, __) => const SizedBox.shrink(),
        ),
      ]),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  final List<Map<String, dynamic>> errors;
  const _ErrorBanner({required this.errors});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: const Color(0xFFFEE2E2), borderRadius: EwTheme.radius8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Row(children: [
          Icon(Icons.error_outline, color: EwTheme.red, size: 16),
          SizedBox(width: 6),
          Text('Issues that need attention', style: TextStyle(color: EwTheme.red, fontWeight: FontWeight.w700, fontSize: 13)),
        ]),
        const SizedBox(height: 6),
        ...errors.map((e) => Text('• ${_codeToMsg(e)}', style: const TextStyle(color: EwTheme.red, fontSize: 12))),
      ]),
    );
  }

  String _codeToMsg(Map<String, dynamic> e) {
    return switch (e['code']) {
      'MOQ_NOT_MET'       => 'Minimum order not met for ${e['product_name'] ?? 'a product'}',
      'STOCK_CHANGED'     => 'Stock changed for ${e['product_name'] ?? 'a product'}',
      'PRODUCT_UNAVAILABLE' => '${e['product_name'] ?? 'A product'} is no longer available',
      'KYB_REQUIRED'      => 'Business verification required to place orders',
      _                   => e['message']?.toString() ?? e['code']?.toString() ?? 'Unknown error',
    };
  }
}

class _WarningBanner extends StatelessWidget {
  final List<Map<String, dynamic>> warnings;
  const _WarningBanner({required this.warnings});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: const Color(0xFFFEF3C7), borderRadius: EwTheme.radius8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Row(children: [
          Icon(Icons.warning_amber_outlined, color: EwTheme.amber, size: 16),
          SizedBox(width: 6),
          Text('Note', style: TextStyle(color: EwTheme.amber, fontWeight: FontWeight.w700, fontSize: 13)),
        ]),
        const SizedBox(height: 6),
        ...warnings.map((w) => Text('• ${w['message'] ?? ''}', style: const TextStyle(color: EwTheme.amber, fontSize: 12))),
      ]),
    );
  }
}

class _SupplierGroup extends StatelessWidget {
  final EwCartGroup group;
  final WidgetRef ref;
  const _SupplierGroup({required this.group, required this.ref});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(color: EwTheme.surface, borderRadius: EwTheme.radius12, border: Border.all(color: EwTheme.border), boxShadow: EwTheme.cardShadow),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Supplier header
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: const BoxDecoration(
            color: EwTheme.navy,
            borderRadius: BorderRadius.vertical(top: Radius.circular(12)),
          ),
          child: Row(children: [
            SupplierBadge(verification: group.supplierVerification),
            const SizedBox(width: 8),
            Text(group.supplierName, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 13)),
          ]),
        ),
        // Lines
        ...group.lines.map((l) => _LineRow(line: l, ref: ref)),
        // Subtotals
        Container(
          padding: const EdgeInsets.fromLTRB(14, 8, 14, 12),
          decoration: const BoxDecoration(
            color: Color(0xFFF9FAFB),
            borderRadius: BorderRadius.vertical(bottom: Radius.circular(12)),
            border: Border(top: BorderSide(color: EwTheme.border)),
          ),
          child: Column(children: [
            _subtotalRow('Subtotal',     group.subtotal),
            _subtotalRow('Delivery',     group.deliveryFee),
            _subtotalRow('Platform fee', group.platformFee),
            const Divider(height: 16, color: EwTheme.border),
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              const Text('Group Total', style: EwTheme.heading3),
              Text(EwTheme.formatPrice(group.total), style: EwTheme.priceSmall.copyWith(color: EwTheme.orange)),
            ]),
          ]),
        ),
      ]),
    );
  }

  Widget _subtotalRow(String label, double v) => v == 0 ? const SizedBox.shrink() : Padding(
    padding: const EdgeInsets.only(bottom: 3),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: EwTheme.bodySmall),
      Text(EwTheme.formatPrice(v), style: EwTheme.bodySmall.copyWith(fontWeight: FontWeight.w600)),
    ]),
  );
}

class _LineRow extends StatelessWidget {
  final EwCartLine line;
  final WidgetRef ref;
  const _LineRow({required this.line, required this.ref});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: EwTheme.border))),
      child: Row(children: [
        if (line.productImage != null)
          ClipRRect(
            borderRadius: EwTheme.radius4,
            child: Image.network(line.productImage!, width: 44, height: 44, fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => Container(width: 44, height: 44, color: EwTheme.bg)),
          )
        else
          Container(width: 44, height: 44, decoration: BoxDecoration(color: EwTheme.bg, borderRadius: EwTheme.radius4),
            child: const Icon(Icons.inventory_2_outlined, color: EwTheme.textMuted, size: 20)),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(line.productName, style: EwTheme.heading3.copyWith(fontSize: 13), maxLines: 1, overflow: TextOverflow.ellipsis),
          Text('${EwTheme.formatPrice(line.unitPrice)} × ${line.qty.toInt()} ${line.unit}', style: EwTheme.bodySmall),
        ])),
        Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
          Text(EwTheme.formatPrice(line.subtotal), style: EwTheme.priceSmall),
          GestureDetector(
            onTap: () => ref.read(ewCartProvider.notifier).remove(line.productId, line.variantId),
            child: const Text('Remove', style: TextStyle(fontSize: 11, color: EwTheme.red, fontWeight: FontWeight.w500)),
          ),
        ]),
      ]),
    );
  }
}

class _CheckoutBar extends StatelessWidget {
  final List<EwCartGroup> groups;
  const _CheckoutBar({required this.groups});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: EwTheme.surface,
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.1), blurRadius: 12, offset: const Offset(0, -3))],
      ),
      padding: EdgeInsets.fromLTRB(16, 12, 16, MediaQuery.of(context).padding.bottom + 12),
      child: ElevatedButton(
        style: EwTheme.primaryButton,
        onPressed: () => context.push('/ewholesale/checkout', extra: groups),
        child: Text('Checkout · ${groups.length} supplier${groups.length > 1 ? 's' : ''}'),
      ),
    );
  }
}

// ─── Checkout Screen ──────────────────────────────────────────────────────────

class EwCheckoutScreen extends ConsumerStatefulWidget {
  final List<EwCartGroup> groups;
  const EwCheckoutScreen({super.key, required this.groups});

  @override
  ConsumerState<EwCheckoutScreen> createState() => _EwCheckoutScreenState();
}

class _EwCheckoutScreenState extends ConsumerState<EwCheckoutScreen> {
  final Map<int, String> _plans = {}; // supplierId → plan
  String _paymentMethod = 'waafi'; // global payment method
  bool _loading = false;

  static const _payMethods = [
    ('waafi',  'Waafi Pay',    Icons.phone_android),
    ('evc',    'EVC Plus',     Icons.phone_android),
    ('zaad',   'Zaad',         Icons.phone_android),
    ('wallet', 'eSahlan Wallet', Icons.account_balance_wallet_outlined),
  ];

  @override
  void initState() {
    super.initState();
    for (final g in widget.groups) {
      _plans[g.supplierId] = g.availablePaymentPlans.first;
    }
  }

  @override
  Widget build(BuildContext context) {
    final buyerAsync = ref.watch(ewBuyerProfileProvider);

    return Scaffold(
      backgroundColor: EwTheme.bg,
      appBar: AppBar(
        backgroundColor: EwTheme.navy,
        foregroundColor: Colors.white,
        title: const Text('Checkout', style: TextStyle(fontWeight: FontWeight.w700)),
      ),
      body: Column(children: [
        Expanded(child: ListView(padding: const EdgeInsets.all(16), children: [
          // KYB warning
          buyerAsync.when(
            loading: () => const SizedBox.shrink(),
            error:   (_, __) => const SizedBox.shrink(),
            data:    (b) => b.isApproved ? const SizedBox.shrink() : _KybWarningBanner(),
          ),
          // Group checkout panels
          ...widget.groups.map((g) => _GroupCheckoutPanel(
            group: g,
            selectedPlan: _plans[g.supplierId] ?? 'prepaid',
            buyerAsync: buyerAsync,
            onPlanChanged: (p) => setState(() => _plans[g.supplierId] = p),
          )),
          const SizedBox(height: 16),
          // Payment method selector
          _buildPaymentMethodCard(),
          const SizedBox(height: 80),
        ])),
        // Place Order bar
        _buildPlaceOrderBar(context, buyerAsync),
      ]),
    );
  }

  Widget _buildPaymentMethodCard() {
    return Container(
      margin: const EdgeInsets.only(bottom: 4),
      decoration: BoxDecoration(
        color: EwTheme.surface,
        borderRadius: EwTheme.radius12,
        border: Border.all(color: EwTheme.border),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Payment Method', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
        const SizedBox(height: 12),
        ..._payMethods.map((m) {
          final (id, label, icon) = m;
          return GestureDetector(
            onTap: () => setState(() => _paymentMethod = id),
            child: Container(
              margin: const EdgeInsets.only(bottom: 8),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              decoration: BoxDecoration(
                color: _paymentMethod == id ? EwTheme.orange.withOpacity(0.08) : EwTheme.bg,
                borderRadius: EwTheme.radius8,
                border: Border.all(
                  color: _paymentMethod == id ? EwTheme.orange : EwTheme.border,
                  width: _paymentMethod == id ? 1.5 : 1,
                ),
              ),
              child: Row(children: [
                Icon(icon, color: _paymentMethod == id ? EwTheme.orange : Colors.grey, size: 20),
                const SizedBox(width: 12),
                Expanded(child: Text(label,
                  style: TextStyle(
                    fontSize: 14, fontWeight: FontWeight.w600,
                    color: _paymentMethod == id ? EwTheme.orange : EwTheme.textPrimary,
                  ))),
                if (_paymentMethod == id)
                  const Icon(Icons.check_circle, color: EwTheme.orange, size: 18),
              ]),
            ),
          );
        }),
      ]),
    );
  }

  Widget _buildPlaceOrderBar(BuildContext context, AsyncValue<EwBuyerProfile> buyerAsync) {
    final kybBlocked = buyerAsync.valueOrNull?.isApproved == false && buyerAsync.valueOrNull != null;
    return Container(
      decoration: BoxDecoration(color: EwTheme.surface,
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.08), blurRadius: 8, offset: const Offset(0, -2))]),
      padding: EdgeInsets.fromLTRB(16, 12, 16, MediaQuery.of(context).padding.bottom + 12),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        if (kybBlocked)
          ElevatedButton(
            style: EwTheme.primaryButton,
            onPressed: () => context.push('/ewholesale/kyb'),
            child: const Text('Verify Business to Order'),
          )
        else
          ElevatedButton(
            style: EwTheme.primaryButton,
            onPressed: _loading ? null : () => _placeOrder(context),
            child: _loading
              ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
              : Text('Place ${widget.groups.length} Order${widget.groups.length > 1 ? 's' : ''}'),
          ),
      ]),
    );
  }

  Future<void> _placeOrder(BuildContext context) async {
    setState(() => _loading = true);
    try {
      final repo = ref.read(ewRepoProvider);
      final groupPayloads = widget.groups.map((g) => {
        'supplier_id':    g.supplierId,
        'payment_plan':   _plans[g.supplierId] ?? 'prepaid',
        'payment_method': _paymentMethod,
        'fulfillment':    'delivery',
        'lines': g.lines.map((l) => l.toJson()).toList(),
      }).toList();

      final orders = await repo.checkout(groupPayloads);
      ref.read(ewCartProvider.notifier).clear();
      ref.invalidate(ewOrdersProvider(null));

      if (mounted) {
        context.pushReplacement('/ewholesale/orders/success',
          extra: orders.map((o) => o.orderNo).toList());
      }
    } catch (e) {
      final msg = e.toString();
      if (msg.contains('KYB_REQUIRED')) {
        if (mounted) context.push('/ewholesale/kyb');
      } else if (msg.contains('CREDIT_EXCEEDED')) {
        if (mounted) _showError('Credit limit exceeded. Choose a different payment plan.');
      } else {
        if (mounted) _showError(msg);
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _showError(String msg) => ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(content: Text(msg), backgroundColor: EwTheme.red, behavior: SnackBarBehavior.floating));
}

class _GroupCheckoutPanel extends StatelessWidget {
  final EwCartGroup group;
  final String selectedPlan;
  final AsyncValue<EwBuyerProfile> buyerAsync;
  final ValueChanged<String> onPlanChanged;

  const _GroupCheckoutPanel({required this.group, required this.selectedPlan,
    required this.buyerAsync, required this.onPlanChanged});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(color: EwTheme.surface, borderRadius: EwTheme.radius12,
        border: Border.all(color: EwTheme.border), boxShadow: EwTheme.cardShadow),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Supplier header
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: const BoxDecoration(color: EwTheme.navy, borderRadius: BorderRadius.vertical(top: Radius.circular(12))),
          child: Text(group.supplierName, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
        ),
        Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            // Lines summary
            ...group.lines.map((l) => Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Expanded(child: Text('${l.qty.toInt()} × ${l.productName}', style: EwTheme.bodySmall, maxLines: 1, overflow: TextOverflow.ellipsis)),
                Text(EwTheme.formatPrice(l.subtotal), style: EwTheme.bodySmall.copyWith(fontWeight: FontWeight.w600)),
              ]),
            )),
            const Divider(height: 16),
            // Payment plan
            if (group.availablePaymentPlans.length > 1)
              PaymentPlanSelector(
                plans: group.availablePaymentPlans,
                selected: selectedPlan,
                groupTotal: group.total,
                depositPercent: 30,
                creditAvailable: buyerAsync.valueOrNull?.creditAvailable,
                onChanged: onPlanChanged,
              ),
            const Divider(height: 16),
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text('Order Total', style: EwTheme.heading3),
              Text(EwTheme.formatPrice(group.total), style: EwTheme.priceSmall.copyWith(color: EwTheme.orange)),
            ]),
          ]),
        ),
      ]),
    );
  }
}

class _KybWarningBanner extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: const Color(0xFFFEF3C7), borderRadius: EwTheme.radius8, border: Border.all(color: EwTheme.amber)),
      child: Row(children: [
        const Icon(Icons.verified_user_outlined, color: EwTheme.amber, size: 20),
        const SizedBox(width: 10),
        const Expanded(child: Text('Business verification required to place orders.\nBrowsing and inquiries remain open.',
          style: TextStyle(fontSize: 13, color: EwTheme.amber))),
      ]),
    );
  }
}

// ─── Order Success Screen ─────────────────────────────────────────────────────

class EwOrderSuccessScreen extends StatelessWidget {
  final List<String> orderNos;
  const EwOrderSuccessScreen({super.key, required this.orderNos});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: EwTheme.bg,
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.check_circle, size: 80, color: EwTheme.green),
              const SizedBox(height: 20),
              Text('Orders Placed!', style: EwTheme.heading1.copyWith(fontSize: 26)),
              const SizedBox(height: 10),
              Text('${orderNos.length} order${orderNos.length > 1 ? 's' : ''} submitted', style: EwTheme.body),
              const SizedBox(height: 16),
              ...orderNos.map((no) => Text(no, style: EwTheme.priceSmall.copyWith(color: EwTheme.orange))),
              const SizedBox(height: 28),
              ElevatedButton(
                style: EwTheme.primaryButton,
                onPressed: () => context.go('/ewholesale/orders'),
                child: const Text('View Orders'),
              ),
              const SizedBox(height: 10),
              OutlinedButton(
                style: EwTheme.secondaryButton,
                onPressed: () => context.go('/ewholesale'),
                child: const Text('Continue Shopping'),
              ),
            ]),
          ),
        ),
      ),
    );
  }
}
