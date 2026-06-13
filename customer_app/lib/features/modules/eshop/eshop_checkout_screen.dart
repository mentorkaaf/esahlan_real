import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/wallet_pin_dialog.dart';
import '../../payment/waafi_pay_sheet.dart';
import '../../wallet/presentation/providers/wallet_provider.dart';
import 'eshop_providers.dart';

class EShopCheckoutScreen extends ConsumerStatefulWidget {
  const EShopCheckoutScreen({super.key});
  @override
  ConsumerState<EShopCheckoutScreen> createState() => _EShopCheckoutScreenState();
}

class _EShopCheckoutScreenState extends ConsumerState<EShopCheckoutScreen> {
  final _nameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _streetCtrl = TextEditingController();
  final _cityCtrl = TextEditingController();
  String _paymentMethod = 'wallet';
  String? _waafiReference;
  bool _placing = false;

  static const double _deliveryFee = 2.00;
  final _svc = ModuleApiService.create();

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _streetCtrl.dispose();
    _cityCtrl.dispose();
    super.dispose();
  }

  bool get _addressFilled => _nameCtrl.text.trim().isNotEmpty && _streetCtrl.text.trim().isNotEmpty && _cityCtrl.text.trim().isNotEmpty;

  Future<void> _placeOrder() async {
    if (!_addressFilled) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please complete delivery address'), backgroundColor: AppColors.error, behavior: SnackBarBehavior.floating));
      return;
    }

    // For Waafi Pay, show payment sheet first
    if (_paymentMethod == 'waafi_pay') {
      final cart   = ref.read(eshopCartProvider);
      final coupon = ref.read(eshopCouponProvider);
      final subtotal = cart.fold(0.0, (s, c) => s + c.lineTotal);
      final discount = coupon.calculateDiscount(subtotal);
      final total = subtotal - discount + _deliveryFee;

      final result = await showWaafiPaySheet(
        context,
        amount: total,
        type: 'order',
        description: 'eSahlan Shop Order',
        prefillPhone: _phoneCtrl.text.trim(),
      );
      if (result?.success != true) return;
      _waafiReference = result!.reference;
    }

    if (_paymentMethod == 'wallet') {
      final pinOk = await showWalletPinDialog(context);
      if (!pinOk) return;
    }

    setState(() => _placing = true);
    try {
      final cart = ref.read(eshopCartProvider);
      final coupon = ref.read(eshopCouponProvider);
      final items = cart.map((c) => {
        'product_id': c.productId,
        'quantity': c.qty,
        if (c.variantId != null) 'variant_id': c.variantId,
      }).toList();

      final body = <String, dynamic>{
        'items': items,
        'delivery_address': {
          'name': _nameCtrl.text.trim(),
          'phone': _phoneCtrl.text.trim(),
          'street': _streetCtrl.text.trim(),
          'city': _cityCtrl.text.trim(),
        },
        'payment_method': _paymentMethod,
        if (coupon.isValid && coupon.code != null) 'coupon_code': coupon.code,
        if (_waafiReference != null) 'payment_reference': _waafiReference,
      };

      await _svc.placeShopOrderV2(body);

      ref.read(eshopCartProvider.notifier).clear();
      ref.read(eshopCouponProvider.notifier).clear();
      if (_paymentMethod == 'wallet') ref.invalidate(walletProvider);

      if (mounted) {
        _showSuccessDialog();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Failed to place order: $e'), backgroundColor: AppColors.error, behavior: SnackBarBehavior.floating));
      }
    } finally {
      if (mounted) setState(() => _placing = false);
    }
  }

  void _showSuccessDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogCtx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(
            width: 80, height: 80,
            decoration: BoxDecoration(color: AppColors.success.withValues(alpha: 0.1), shape: BoxShape.circle),
            child: const Icon(Icons.check_circle_rounded, size: 50, color: AppColors.success),
          ),
          const SizedBox(height: 20),
          const Text('Order Placed!', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 22, color: AppColors.secondary)),
          const SizedBox(height: 8),
          const Text('Your order has been placed successfully. You will receive a confirmation shortly.',
            textAlign: TextAlign.center, style: TextStyle(color: AppColors.textGrey, fontSize: 13, height: 1.5)),
          const SizedBox(height: 24),
          AppButton(label: 'Continue Shopping', onPressed: () {
            Navigator.of(dialogCtx).pop();
            context.go('/eshop');
          }),
          const SizedBox(height: 8),
          AppButton(label: 'View Orders', outlined: true, onPressed: () {
            Navigator.of(dialogCtx).pop();
            context.go('/orders');
          }),
        ]),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cart = ref.watch(eshopCartProvider);
    final coupon = ref.watch(eshopCouponProvider);
    final subtotal = cart.fold(0.0, (s, c) => s + c.lineTotal);
    final discount = coupon.calculateDiscount(subtotal);
    final total = subtotal - discount + _deliveryFee;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20), onPressed: () => context.pop()),
        title: const Text('Checkout', style: TextStyle(fontWeight: FontWeight.w800, fontFamily: 'Cairo')),
      ),
      body: ListView(padding: const EdgeInsets.fromLTRB(16, 16, 16, 100), children: [
        // ── Delivery Address ──────────────────────────────────────
        _section('Delivery Address', Icons.location_on_outlined, children: [
          _field(_nameCtrl, 'Full Name', Icons.person_outline_rounded),
          const SizedBox(height: 12),
          _field(_phoneCtrl, 'Phone Number', Icons.phone_outlined, keyboardType: TextInputType.phone),
          const SizedBox(height: 12),
          _field(_streetCtrl, 'Street Address', Icons.home_outlined),
          const SizedBox(height: 12),
          _field(_cityCtrl, 'City', Icons.location_city_outlined),
        ]),

        const SizedBox(height: 16),

        // ── Order Items ───────────────────────────────────────────
        _section('Order Summary', Icons.receipt_long_outlined, children: [
          ...cart.map((item) => Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Row(children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: item.product['thumbnail'] != null
                    ? Image.network(item.product['thumbnail'], width: 50, height: 50, fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => Container(width: 50, height: 50, color: AppColors.surface))
                    : Container(width: 50, height: 50, color: AppColors.surface),
              ),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(item.product['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.secondary), maxLines: 1, overflow: TextOverflow.ellipsis),
                if (item.variant != null) Container(
                  margin: const EdgeInsets.only(top: 3),
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withValues(alpha: 0.08),
                    borderRadius: BorderRadius.circular(5),
                    border: Border.all(color: AppColors.primary.withValues(alpha: 0.2)),
                  ),
                  child: Text(item.variant!['name'] ?? '', style: const TextStyle(fontSize: 11, color: AppColors.primary, fontWeight: FontWeight.w600)),
                ),
                const SizedBox(height: 3),
                Text('\$${item.effectivePrice.toStringAsFixed(2)} × ${item.qty}', style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
              ])),
              Text('\$${item.lineTotal.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: AppColors.primary)),
            ]),
          )),
          const Divider(height: 8),
          const SizedBox(height: 8),
          _row('Subtotal', '\$${subtotal.toStringAsFixed(2)}'),
          if (coupon.isValid && discount > 0) ...[
            _row('Coupon (${coupon.code})', '-\$${discount.toStringAsFixed(2)}', valueColor: AppColors.success),
          ],
          _row('Delivery Fee', '\$${_deliveryFee.toStringAsFixed(2)}'),
          const Divider(height: 16),
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            const Text('Total', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16, color: AppColors.secondary)),
            Text('\$${total.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 20, color: AppColors.primary)),
          ]),
        ]),

        const SizedBox(height: 16),

        // ── Payment Method ────────────────────────────────────────
        _section('Payment Method', Icons.payment_outlined, children: [
          _paymentOption('wallet',    'Wallet',           Icons.account_balance_wallet_outlined,   'Pay from your wallet balance'),
          const SizedBox(height: 10),
          _paymentOption('waafi_pay', 'Waafi Pay',        Icons.phone_android_rounded,             'EVC / eDahab / Jeep / Premier'),
        ]),
      ]),
      bottomNavigationBar: Container(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 12, offset: const Offset(0, -2))],
        ),
        child: AppButton(
          label: 'Place Order  •  \$${total.toStringAsFixed(2)}',
          isLoading: _placing,
          onPressed: cart.isEmpty ? null : _placeOrder,
        ),
      ),
    );
  }

  Widget _section(String title, IconData icon, {required List<Widget> children}) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)],
    ),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Icon(icon, size: 18, color: AppColors.primary),
        const SizedBox(width: 8),
        Text(title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.secondary)),
      ]),
      const SizedBox(height: 16),
      ...children,
    ]),
  );

  Widget _field(TextEditingController ctrl, String hint, IconData icon, {TextInputType? keyboardType}) => TextField(
    controller: ctrl,
    keyboardType: keyboardType,
    onChanged: (_) => setState(() {}),
    decoration: InputDecoration(
      hintText: hint,
      prefixIcon: Icon(icon, size: 18, color: AppColors.textGrey),
      filled: true, fillColor: AppColors.surface,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.primary, width: 2)),
    ),
  );

  Widget _paymentOption(String value, String label, IconData icon, String subtitle) {
    final selected = _paymentMethod == value;
    return GestureDetector(
      onTap: () => setState(() => _paymentMethod = value),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: selected ? AppColors.primary.withValues(alpha: 0.05) : AppColors.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: selected ? AppColors.primary : AppColors.divider, width: selected ? 2 : 1),
        ),
        child: Row(children: [
          Container(
            width: 44, height: 44,
            decoration: BoxDecoration(
              color: selected ? AppColors.primary : Colors.white,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, size: 22, color: selected ? Colors.white : AppColors.textGrey),
          ),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(label, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: selected ? AppColors.primary : AppColors.secondary)),
            Text(subtitle, style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
          ])),
          if (selected) const Icon(Icons.check_circle_rounded, color: AppColors.primary),
        ]),
      ),
    );
  }

  Widget _row(String label, String value, {Color? valueColor}) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: const TextStyle(color: AppColors.textGrey, fontSize: 13)),
      Text(value, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: valueColor ?? AppColors.secondary)),
    ]),
  );
}
