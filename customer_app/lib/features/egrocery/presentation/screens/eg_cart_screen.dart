import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../data/models/egrocery_models.dart';
import '../../data/repositories/egrocery_repository.dart';
import '../../ui/eg_theme.dart';
import '../../ui/eg_widgets.dart';
import '../providers/egrocery_providers.dart';

class EGCartScreen extends ConsumerStatefulWidget {
  const EGCartScreen({super.key});

  @override
  ConsumerState<EGCartScreen> createState() => _EGCartScreenState();
}

class _EGCartScreenState extends ConsumerState<EGCartScreen> {
  EGCartValidateResult? _validated;
  bool _validating = false;
  Timer? _validateDebounce;
  String? _changedBanner;
  String _subPref = 'best_match';
  final _couponCtrl = TextEditingController();
  String? _couponCode;
  String? _couponError;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _validate());
  }

  void _scheduleValidate() {
    _validateDebounce?.cancel();
    _validateDebounce = Timer(const Duration(milliseconds: 600), _validate);
  }

  Future<void> _validate() async {
    final lines = ref.read(egCartProvider);
    if (lines.isEmpty) {
      setState(() { _validated = null; _validating = false; });
      return;
    }
    setState(() => _validating = true);
    try {
      final result = await EGroceryRepository().validateCart(
        lines: lines,
        coupon: _couponCode,
      );
      if (!mounted) return;
      if (result.changed) {
        ref.read(egCartProvider.notifier).applyValidation(result);
        setState(() => _changedBanner = 'Some items were updated based on current stock & prices.');
      }
      setState(() {
        _validated = result;
        _validating = false;
        _couponError = result.couponError;
      });
    } catch (_) {
      if (mounted) setState(() => _validating = false);
    }
  }

  @override
  void dispose() {
    _validateDebounce?.cancel();
    _couponCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final lines = ref.watch(egCartProvider);

    return Scaffold(
      backgroundColor: EGTheme.bg,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: Text('Cart (${lines.length})', style: const TextStyle(color: EGTheme.textDark, fontWeight: FontWeight.w800, fontSize: 17)),
        iconTheme: const IconThemeData(color: EGTheme.textDark),
        actions: [
          if (lines.isNotEmpty)
            TextButton(
              onPressed: () { ref.read(egCartProvider.notifier).clear(); setState(() { _validated = null; _changedBanner = null; }); },
              child: const Text('Clear', style: TextStyle(color: EGTheme.red, fontWeight: FontWeight.w700)),
            ),
        ],
      ),
      body: lines.isEmpty
          ? EGEmptyState(emoji: '🛒', title: 'Your cart is empty', subtitle: 'Add groceries to get started', onRetry: null)
          : Column(children: [
              // Changed banner
              if (_changedBanner != null)
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  color: EGTheme.orange.withOpacity(0.1),
                  child: Row(children: [
                    const Icon(Icons.info_outline, color: EGTheme.orange, size: 16),
                    const SizedBox(width: 8),
                    Expanded(child: Text(_changedBanner!, style: const TextStyle(fontSize: 12, color: EGTheme.orange))),
                    GestureDetector(onTap: () => setState(() => _changedBanner = null), child: const Icon(Icons.close, size: 16, color: EGTheme.orange)),
                  ]),
                ),

              Expanded(child: ListView.builder(
                padding: const EdgeInsets.all(12),
                itemCount: lines.length + 2, // +1 options card, +1 coupon card
                itemBuilder: (_, i) {
                  if (i < lines.length) {
                    return _CartLineItem(
                      line: lines[i],
                      onQtyChanged: (q) {
                        ref.read(egCartProvider.notifier).setQty(lines[i].variantId, q);
                        _scheduleValidate();
                      },
                      onRemove: () {
                        ref.read(egCartProvider.notifier).remove(lines[i].variantId);
                        _scheduleValidate();
                      },
                    );
                  }
                  if (i == lines.length) {
                    return _OptionsCard(subPref: _subPref, onSubPrefChanged: (v) => setState(() => _subPref = v));
                  }
                  // Coupon card
                  return _CouponCard(
                    controller: _couponCtrl,
                    applied: _validated?.coupon,
                    error: _couponError,
                    onApply: () {
                      setState(() { _couponCode = _couponCtrl.text.trim().isEmpty ? null : _couponCtrl.text.trim().toUpperCase(); });
                      _validate();
                    },
                    onRemove: () {
                      _couponCtrl.clear();
                      setState(() { _couponCode = null; _couponError = null; });
                      _validate();
                    },
                  );
                },
              )),

              // Totals card
              Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.08), blurRadius: 16, offset: const Offset(0, -4))],
                ),
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
                child: Column(children: [
                  if (_validating)
                    const LinearProgressIndicator(color: EGTheme.orange, minHeight: 2),
                  if (_validated != null) ...[
                    // Free-over progress
                    if (_validated!.freeOver != null) ...[
                      Row(children: [
                        Expanded(child: ClipRRect(
                          borderRadius: BorderRadius.circular(4),
                          child: LinearProgressIndicator(
                            value: _validated!.freeDeliveryProgress ?? 0,
                            color: EGTheme.green,
                            backgroundColor: EGTheme.shimmer,
                            minHeight: 6,
                          ),
                        )),
                        const SizedBox(width: 10),
                        Text(
                          (_validated!.freeDeliveryProgress ?? 0) >= 1
                              ? '✅ Free delivery!'
                              : '\$${(_validated!.freeOver! - _validated!.subtotal).clamp(0, double.infinity).toStringAsFixed(2)} away from free delivery',
                          style: const TextStyle(fontSize: 11, color: EGTheme.green, fontWeight: FontWeight.w600),
                        ),
                      ]),
                      const SizedBox(height: 12),
                    ],
                    _TotalRow('Subtotal', _validated!.subtotal),
                    if (_validated!.discount > 0) _TotalRow('Discount', -_validated!.discount, color: EGTheme.green),
                    _TotalRow('Delivery', _validated!.deliveryFee == 0 ? null : _validated!.deliveryFee, zeroLabel: 'Free'),
                    const Divider(height: 16),
                    _TotalRow('Total', _validated!.total, bold: true, size: 18),
                    const SizedBox(height: 14),
                  ],
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: _validated == null || _validating ? null : () {
                        context.push('/egrocery/checkout', extra: {
                          'validated': _validated,
                          'sub_pref': _subPref,
                          'coupon': _couponCode,
                        });
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: EGTheme.orange,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 15),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(EGTheme.rBtn)),
                      ),
                      child: Text(
                        _validated != null ? 'Checkout · \$${_validated!.total.toStringAsFixed(2)}' : 'Checkout',
                        style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
                      ),
                    ),
                  ),
                ]),
              ),
            ]),
    );
  }
}

class _CartLineItem extends StatelessWidget {
  final EGCartLine line;
  final ValueChanged<double> onQtyChanged;
  final VoidCallback onRemove;
  const _CartLineItem({required this.line, required this.onQtyChanged, required this.onRemove});

  @override
  Widget build(BuildContext context) => Dismissible(
        key: Key('cart-${line.variantId}'),
        direction: DismissDirection.endToStart,
        onDismissed: (_) => onRemove(),
        background: Container(
          alignment: Alignment.centerRight,
          padding: const EdgeInsets.only(right: 16),
          decoration: BoxDecoration(color: EGTheme.red, borderRadius: BorderRadius.circular(EGTheme.rCard)),
          child: const Icon(Icons.delete, color: Colors.white),
        ),
        child: Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(EGTheme.rCard)),
          child: Row(children: [
            if (line.image != null)
              ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: Image.network(line.image!, width: 60, height: 60, fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => Container(width: 60, height: 60, color: EGTheme.shimmer)),
              ),
            const SizedBox(width: 10),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(line.productName, style: EGTheme.productName, maxLines: 2),
              Text(line.variantLabel, style: EGTheme.caption),
              const SizedBox(height: 4),
              Text('\$${line.unitPrice.toStringAsFixed(2)}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: EGTheme.orange)),
            ])),
            Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
              Text('\$${line.lineTotal.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
              const SizedBox(height: 8),
              EGQtyStepper(qty: line.qty, onChanged: onQtyChanged),
            ]),
          ]),
        ),
      );
}

class _OptionsCard extends StatelessWidget {
  final String subPref;
  final ValueChanged<String> onSubPrefChanged;
  const _OptionsCard({required this.subPref, required this.onSubPrefChanged});

  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(EGTheme.rCard)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('If item unavailable:', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
          const SizedBox(height: 8),
          ...{
            'best_match': '🔄 Replace with best match',
            'call_me': '📞 Call me to decide',
            'refund': '💰 Refund the item',
          }.entries.map((e) => GestureDetector(
            onTap: () => onSubPrefChanged(e.key),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(children: [
                Icon(subPref == e.key ? Icons.radio_button_checked : Icons.radio_button_unchecked,
                    color: subPref == e.key ? EGTheme.orange : EGTheme.textGrey, size: 18),
                const SizedBox(width: 8),
                Text(e.value, style: TextStyle(fontSize: 13, color: subPref == e.key ? EGTheme.textDark : EGTheme.textGrey, fontWeight: subPref == e.key ? FontWeight.w600 : FontWeight.w400)),
              ]),
            ),
          )),
        ]),
      );
}

class _CouponCard extends StatelessWidget {
  final TextEditingController controller;
  final Map<String, dynamic>? applied;
  final String? error;
  final VoidCallback onApply;
  final VoidCallback onRemove;
  const _CouponCard({required this.controller, this.applied, this.error, required this.onApply, required this.onRemove});

  String _friendlyError(String? e) {
    if (e == null) return '';
    if (e.startsWith('COUPON_MIN_ORDER:')) return 'Min order: \$${e.split(':').last}';
    return switch (e) {
      'INVALID_COUPON'            => 'Invalid coupon code',
      'COUPON_EXPIRED'            => 'Coupon has expired',
      'COUPON_NOT_STARTED'        => 'Coupon is not active yet',
      'COUPON_LIMIT_REACHED'      => 'Coupon usage limit reached',
      'COUPON_USER_LIMIT_REACHED' => 'You have already used this coupon',
      'COUPON_CATEGORY_MISMATCH'  => 'Coupon not valid for these products',
      _                           => 'Coupon could not be applied',
    };
  }

  @override
  Widget build(BuildContext context) {
    final isApplied = applied != null;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(EGTheme.rCard)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('🎟️ Coupon Code', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
        const SizedBox(height: 8),
        if (isApplied)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: EGTheme.green.withOpacity(0.08),
              border: Border.all(color: EGTheme.green.withOpacity(0.4)),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Row(children: [
              const Icon(Icons.check_circle, color: EGTheme.green, size: 18),
              const SizedBox(width: 8),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(applied!['code'] ?? '', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: EGTheme.green)),
                Text(applied!['title'] ?? '', style: const TextStyle(fontSize: 11, color: EGTheme.textGrey)),
              ])),
              Text('-\$${(applied!['discount'] as num).toStringAsFixed(2)}',
                  style: const TextStyle(fontWeight: FontWeight.w800, color: EGTheme.green, fontSize: 14)),
              const SizedBox(width: 8),
              GestureDetector(onTap: onRemove, child: const Icon(Icons.close, size: 18, color: EGTheme.textGrey)),
            ]),
          )
        else
          Row(children: [
            Expanded(
              child: TextField(
                controller: controller,
                textCapitalization: TextCapitalization.characters,
                decoration: InputDecoration(
                  hintText: 'Enter coupon code',
                  hintStyle: const TextStyle(color: EGTheme.textGrey, fontSize: 14),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: EGTheme.shimmer)),
                  focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: EGTheme.orange)),
                  errorText: error != null ? _friendlyError(error) : null,
                  errorStyle: const TextStyle(fontSize: 11, color: EGTheme.red),
                ),
                style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, letterSpacing: 1),
                onSubmitted: (_) => onApply(),
              ),
            ),
            const SizedBox(width: 8),
            ElevatedButton(
              onPressed: onApply,
              style: ElevatedButton.styleFrom(
                backgroundColor: EGTheme.orange,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                elevation: 0,
              ),
              child: const Text('Apply', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
          ]),
      ]),
    );
  }
}

class _TotalRow extends StatelessWidget {
  final String label;
  final double? amount;
  final String? zeroLabel;
  final bool bold;
  final double size;
  final Color? color;
  const _TotalRow(this.label, this.amount, {this.zeroLabel, this.bold = false, this.size = 14, this.color});

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
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
