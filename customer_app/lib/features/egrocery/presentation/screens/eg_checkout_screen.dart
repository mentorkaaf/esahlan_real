import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../data/models/egrocery_models.dart';
import '../../data/repositories/egrocery_repository.dart';
import '../../ui/eg_theme.dart';
import '../../ui/eg_widgets.dart';
import '../providers/egrocery_providers.dart';
import '../../../../features/payment/payment_method_section.dart';
import '../../../../features/payment/mobile_pay_sheet.dart';
import '../../../../features/payment/waafi_pay_sheet.dart';
import '../../../../shared/widgets/wallet_pin_dialog.dart';
import '../../../../features/wallet/presentation/providers/wallet_provider.dart';

class EGCheckoutScreen extends ConsumerStatefulWidget {
  final EGCartValidateResult? validated;
  final String? subPref;
  final String? coupon;
  const EGCheckoutScreen({super.key, this.validated, this.subPref, this.coupon});

  @override
  ConsumerState<EGCheckoutScreen> createState() => _EGCheckoutScreenState();
}

class _EGCheckoutScreenState extends ConsumerState<EGCheckoutScreen> {
  String _payment = 'cod';
  String? _waafiReference;
  String? _mobileProofToken;
  EGDeliverySlot? _slot;
  String? _subPref;
  bool _loading = false;
  bool _slotsLoading = true;
  List<EGDeliverySlot> _slots = [];
  final _noteCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _subPref = widget.subPref ?? 'best_match';
    _loadSlots();
  }

  Future<void> _loadSlots() async {
    try {
      final slots = await EGroceryRepository().getSlots();
      if (mounted) setState(() { _slots = slots; _slotsLoading = false; });
    } catch (_) {
      if (mounted) setState(() => _slotsLoading = false);
    }
  }

  Future<void> _placeOrder() async {
    final lines = ref.read(egCartProvider);
    if (lines.isEmpty) return;

    // ── Payment flows that require user action BEFORE placing order ───────────
    _waafiReference = null;
    _mobileProofToken = null;

    final total = widget.validated?.total ?? 0.0;

    if (_payment == 'mobile_pay') {
      final result = await showMobilePaySheet(
        context,
        amount: total,
        description: 'eGrocery Order',
      );
      if (result?.success != true) return;
      _waafiReference = result!.account != null
          ? 'mobile_pay_${result.account!.id}'
          : 'mobile_pay';
      _mobileProofToken = result.proofToken;
    } else if (_payment == 'waafi_pay') {
      final result = await showWaafiPaySheet(
        context,
        amount: total,
        type: 'order',
        description: 'eGrocery Order',
      );
      if (result?.success != true) return;
      _waafiReference = result!.reference;
    } else if (_payment == 'wallet') {
      final pinOk = await showWalletPinDialog(context);
      if (!pinOk) return;
    }

    setState(() => _loading = true);
    try {
      final order = await EGroceryRepository().placeOrder(
        paymentMethod: _payment,
        lines: lines,
        slotId: _slot?.slotId,
        scheduledDate: _slot?.date,
        substitutionPref: _subPref,
        note: _noteCtrl.text.trim().isEmpty ? null : _noteCtrl.text.trim(),
        coupon: widget.coupon,
        paymentReference: _waafiReference,
      );

      // Attach mobile pay proof asynchronously (non-blocking)
      if (_mobileProofToken != null) {
        EGroceryRepository().attachMobilePayProof(order.orderNo, _mobileProofToken!);
      }

      if (_payment == 'wallet') ref.invalidate(walletProvider);
      ref.read(egCartProvider.notifier).clear();
      if (mounted) {
        context.pushReplacement('/egrocery/order-success', extra: order);
      }
    } catch (e) {
      setState(() => _loading = false);
      final msg = e.toString();
      if (msg.contains('STOCK_CHANGED')) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('⚠️ Some items are no longer available. Cart updated.'),
            backgroundColor: EGTheme.red,
          ));
          context.pop();
        }
      } else if (msg.contains('SLOT_FULL')) {
        if (mounted) {
          setState(() => _slot = null);
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('That slot is now full. Please choose another.')));
        }
      } else {
        if (mounted) {
          // Extract actual server error message if available
          String display = msg;
          if (e is DioException && e.response?.data != null) {
            final d = e.response!.data;
            if (d is Map) {
              final code = d['code']?.toString() ?? '';
              if (code == 'MIN_ORDER_NOT_MET') {
                final minAmt = d['min_order'] ?? '';
                display = 'Minimum order is \$$minAmt. Please add more items.';
              } else if (code == 'PRICE_CHANGED') {
                display = 'Some prices changed. Please review your cart.';
              } else {
                final errors = d['errors'];
                if (errors is Map) {
                  display = errors.values.expand((v) => v is List ? v : [v]).join('\n');
                } else if (d['message'] != null) {
                  display = d['message'].toString();
                }
              }
            }
          }
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(display), backgroundColor: EGTheme.red));
        }
      }
    }
  }

  @override
  void dispose() {
    _noteCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final validated = widget.validated;

    return Scaffold(
      backgroundColor: EGTheme.bg,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: const Text('Checkout', style: TextStyle(color: EGTheme.textDark, fontWeight: FontWeight.w800, fontSize: 17)),
        iconTheme: const IconThemeData(color: EGTheme.textDark),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

          // ── Delivery Slot ─────────────────────────────────────────────────
          _SectionCard(title: '🕐 Delivery Time', child: _slotsLoading
              ? const Center(child: CircularProgressIndicator(color: EGTheme.orange))
              : _slots.isEmpty
                  ? const Text('No slots available', style: TextStyle(color: EGTheme.textGrey))
                  : Column(children: [
                      RadioListTile<String>(
                        value: 'asap',
                        groupValue: _slot == null ? 'asap' : 'slot',
                        onChanged: (_) => setState(() => _slot = null),
                        title: const Text('ASAP (as soon as possible)', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                        activeColor: EGTheme.orange,
                        contentPadding: EdgeInsets.zero,
                      ),
                      ..._slots.map((s) => RadioListTile<EGDeliverySlot?>(
                        value: s,
                        groupValue: _slot,
                        onChanged: s.isFull ? null : (v) => setState(() => _slot = v),
                        title: Text('${s.date} · ${s.label}', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14, color: s.isFull ? EGTheme.textGrey : EGTheme.textDark)),
                        subtitle: s.isFull ? const Text('Full', style: TextStyle(color: EGTheme.red, fontSize: 11)) : Text('${s.available} slots available', style: const TextStyle(fontSize: 11, color: EGTheme.textGrey)),
                        activeColor: EGTheme.orange,
                        contentPadding: EdgeInsets.zero,
                      )),
                    ])),

          const SizedBox(height: 12),

          // ── Payment Method ────────────────────────────────────────────────
          _SectionCard(
            title: '💳 Payment',
            child: PaymentMethodSection(
              selected: _payment,
              onChanged: (v) => setState(() => _payment = v),
              showCod: true,
            ),
          ),

          const SizedBox(height: 12),

          // ── Note ─────────────────────────────────────────────────────────
          _SectionCard(title: '📝 Order Note (optional)', child: TextField(
            controller: _noteCtrl,
            maxLines: 2,
            decoration: const InputDecoration(hintText: 'e.g. Ring the doorbell...', border: InputBorder.none),
          )),

          const SizedBox(height: 12),

          // ── Order Summary ─────────────────────────────────────────────────
          if (validated != null)
            _SectionCard(title: '🧾 Order Summary', child: Column(children: [
              ...ref.read(egCartProvider).map((l) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Row(children: [
                  Expanded(child: Text('${l.productName} · ${l.variantLabel}', style: const TextStyle(fontSize: 13))),
                  Text('×${l.qty.toStringAsFixed(l.qty % 1 == 0 ? 0 : 1)}', style: EGTheme.caption),
                  const SizedBox(width: 8),
                  Text('\$${l.lineTotal.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                ]),
              )),
              const Divider(height: 16),
              _TotRow('Subtotal', validated.subtotal),
              if (validated.discount > 0) _TotRow('Discount', -validated.discount, color: EGTheme.green),
              _TotRow('Delivery', validated.deliveryFee == 0 ? null : validated.deliveryFee, zeroLabel: 'Free'),
              const Divider(height: 12),
              _TotRow('Total', validated.total, bold: true, size: 16),
            ])),

          const SizedBox(height: 24),

          // ── Place Order ───────────────────────────────────────────────────
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _loading ? null : _placeOrder,
              style: ElevatedButton.styleFrom(
                backgroundColor: EGTheme.orange,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(EGTheme.rBtn)),
              ),
              child: _loading
                  ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : Text(
                      validated != null ? 'Place Order · \$${validated.total.toStringAsFixed(2)}' : 'Place Order',
                      style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
                    ),
            ),
          ),
          const SizedBox(height: 30),
        ]),
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final String title;
  final Widget child;
  const _SectionCard({required this.title, required this.child});

  @override
  Widget build(BuildContext context) => Container(
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(EGTheme.rCard)),
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: EGTheme.textDark)),
          const SizedBox(height: 10),
          child,
        ]),
      );
}

class _TotRow extends StatelessWidget {
  final String label;
  final double? amount;
  final String? zeroLabel;
  final bool bold;
  final double size;
  final Color? color;
  const _TotRow(this.label, this.amount, {this.zeroLabel, this.bold = false, this.size = 13, this.color});

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(children: [
          Text(label, style: TextStyle(fontSize: size, fontWeight: bold ? FontWeight.w800 : FontWeight.w500, color: EGTheme.textGrey)),
          const Spacer(),
          Text(
            amount == 0 && zeroLabel != null ? zeroLabel! : '\$${(amount ?? 0).toStringAsFixed(2)}',
            style: TextStyle(fontSize: size, fontWeight: bold ? FontWeight.w800 : FontWeight.w700, color: color ?? EGTheme.textDark),
          ),
        ]),
      );
}
