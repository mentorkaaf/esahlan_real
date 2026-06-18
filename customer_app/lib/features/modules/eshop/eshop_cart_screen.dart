import 'package:flutter/material.dart';
import '../../../core/theme/theme_x.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/network_image_widget.dart';
import '../../../shared/widgets/app_button.dart';
import 'eshop_providers.dart';

class EShopCartScreen extends ConsumerStatefulWidget {
  const EShopCartScreen({super.key});
  @override
  ConsumerState<EShopCartScreen> createState() => _EShopCartScreenState();
}

class _EShopCartScreenState extends ConsumerState<EShopCartScreen> {
  final _couponCtrl = TextEditingController();
  bool _validatingCoupon = false;

  static const double _deliveryFee = 2.00;

  @override
  void dispose() { _couponCtrl.dispose(); super.dispose(); }

  Future<void> _applyCoupon(List<CartItem> cart) async {
    final code = _couponCtrl.text.trim();
    if (code.isEmpty) return;
    setState(() => _validatingCoupon = true);
    final subtotal = cart.fold(0.0, (s, c) => s + c.lineTotal);
    await ref.read(eshopCouponProvider.notifier).validate(code, subtotal);
    setState(() => _validatingCoupon = false);
  }

  @override
  Widget build(BuildContext context) {
    final cart = ref.watch(eshopCartProvider);
    final cartNotifier = ref.read(eshopCartProvider.notifier);
    final coupon = ref.watch(eshopCouponProvider);

    final subtotal = cart.fold(0.0, (s, c) => s + c.lineTotal);
    final discount = coupon.calculateDiscount(subtotal);
    final total = subtotal - discount + _deliveryFee;

    return Scaffold(
      
      appBar: AppBar(
        
        elevation: 0,
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20), onPressed: () => context.pop()),
        title: Row(mainAxisSize: MainAxisSize.min, children: [
          const Text('My Cart', style: TextStyle(fontWeight: FontWeight.w800, fontFamily: 'Cairo')),
          if (cart.isNotEmpty) ...[
            const SizedBox(width: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(color: AppColors.primary, borderRadius: BorderRadius.circular(12)),
              child: Text('${cart.length}', style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
            ),
          ],
        ]),
        actions: [
          if (cart.isNotEmpty) TextButton(
            onPressed: () { cartNotifier.clear(); ref.read(eshopCouponProvider.notifier).clear(); },
            child: const Text('Clear All', style: TextStyle(color: AppColors.error, fontWeight: FontWeight.w700)),
          ),
        ],
      ),
      body: cart.isEmpty
          ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
              Container(width: 120, height: 120,
                decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(60)),
                child: const Icon(Icons.shopping_bag_outlined, size: 60, color: AppColors.textGrey),
              ),
              const SizedBox(height: 20),
              Text('Your cart is empty', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: context.colors.navyText)),
              const SizedBox(height: 8),
              const Text('Add products to get started', style: TextStyle(color: AppColors.textGrey, fontSize: 14)),
              const SizedBox(height: 24),
              AppButton(label: 'Browse Products', width: 180, onPressed: () => context.go('/eshop')),
            ]))
          : ListView(padding: const EdgeInsets.fromLTRB(16, 16, 16, 120), children: [
              // Cart Items
              ...cart.asMap().entries.map((entry) {
                final item = entry.value;
                final p = item.product;
                return Dismissible(
                  key: Key('${item.productId}_${item.variantId}'),
                  direction: DismissDirection.endToStart,
                  background: Container(
                    alignment: Alignment.centerRight,
                    padding: const EdgeInsets.only(right: 20),
                    margin: const EdgeInsets.only(bottom: 12),
                    decoration: BoxDecoration(color: AppColors.error, borderRadius: BorderRadius.circular(14)),
                    child: const Icon(Icons.delete_outline_rounded, color: Colors.white, size: 28),
                  ),
                  onDismissed: (_) => cartNotifier.removeItem(item.productId, variantId: item.variantId),
                  child: Container(
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)],
                    ),
                    child: Row(children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(10),
                        child: p['thumbnail'] != null
                            ? Image.network(fixImgUrl(p['thumbnail']), width: 72, height: 72, fit: BoxFit.cover,
                                errorBuilder: (_, __, ___) => Container(width: 72, height: 72, color: AppColors.surface, child: const Icon(Icons.image_outlined, color: AppColors.divider)))
                            : Container(width: 72, height: 72, color: AppColors.surface, child: const Icon(Icons.image_outlined, color: AppColors.divider)),
                      ),
                      const SizedBox(width: 12),
                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(p['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText), maxLines: 2, overflow: TextOverflow.ellipsis),
                        if (item.variant != null) Text(item.variant!['name'] ?? '', style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
                        const SizedBox(height: 8),
                        Row(children: [
                          Text('\$${item.effectivePrice.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: AppColors.primary)),
                          const Spacer(),
                          _QtyControl(
                            qty: item.qty,
                            onMinus: () => cartNotifier.updateQty(item.productId, item.qty - 1, variantId: item.variantId),
                            onPlus: () => cartNotifier.updateQty(item.productId, item.qty + 1, variantId: item.variantId),
                          ),
                        ]),
                      ])),
                    ]),
                  ),
                );
              }),

              const SizedBox(height: 8),

              // Coupon
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)],
                ),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Coupon Code', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: context.colors.navyText)),
                  const SizedBox(height: 12),
                  Row(children: [
                    Expanded(child: TextField(
                      controller: _couponCtrl,
                      textCapitalization: TextCapitalization.characters,
                      decoration: InputDecoration(
                        hintText: 'Enter coupon code',
                        filled: true, fillColor: context.colors.cardBg,
                        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
                        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
                        suffixIcon: coupon.isValid ? const Icon(Icons.check_circle_rounded, color: AppColors.success) : null,
                      ),
                    )),
                    const SizedBox(width: 10),
                    AppButton(
                      label: 'Apply',
                      width: 80, height: 46,
                      isLoading: _validatingCoupon,
                      onPressed: () => _applyCoupon(cart),
                    ),
                  ]),
                  if (coupon.message != null) Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(coupon.message!, style: TextStyle(
                      fontSize: 12, fontWeight: FontWeight.w600,
                      color: coupon.isValid ? AppColors.success : AppColors.error,
                    )),
                  ),
                ]),
              ),

              const SizedBox(height: 12),

              // Order Summary
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)],
                ),
                child: Column(children: [
                  const Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                    Text('Order Summary', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText)),
                  ]),
                  const SizedBox(height: 16),
                  _summaryRow('Subtotal', '\$${subtotal.toStringAsFixed(2)}'),
                  if (discount > 0) _summaryRow('Discount', '-\$${discount.toStringAsFixed(2)}', valueColor: AppColors.success),
                  _summaryRow('Delivery Fee', '\$${_deliveryFee.toStringAsFixed(2)}'),
                  const Divider(height: 20),
                  Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                    Text('Total', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16, color: context.colors.navyText)),
                    Text('\$${total.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 20, color: AppColors.primary)),
                  ]),
                ]),
              ),
            ]),
      bottomNavigationBar: cart.isNotEmpty ? Container(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 12, offset: const Offset(0, -2))],
        ),
        child: AppButton(
          label: 'Proceed to Checkout  •  \$${total.toStringAsFixed(2)}',
          onPressed: () => context.push('/eshop/checkout'),
        ),
      ) : null,
    );
  }

  Widget _summaryRow(String label, String value, {Color? valueColor}) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: const TextStyle(color: AppColors.textGrey, fontSize: 14)),
      Text(value, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: valueColor ?? AppColors.secondary)),
    ]),
  );
}

// ─────────────────────────────────────────────────────────────────
// Qty Control Widget
// ─────────────────────────────────────────────────────────────────
class _QtyControl extends StatelessWidget {
  final int qty;
  final VoidCallback onMinus;
  final VoidCallback onPlus;
  const _QtyControl({required this.qty, required this.onMinus, required this.onPlus});

  @override
  Widget build(BuildContext context) => Row(children: [
    _btn(Icons.remove_rounded, onMinus, qty > 1),
    SizedBox(width: 32, child: Text('$qty', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.navyText))),
    _btn(Icons.add_rounded, onPlus, true),
  ]);

  Widget _btn(IconData icon, VoidCallback fn, bool active) => GestureDetector(
    onTap: active ? fn : null,
    child: Container(
      width: 28, height: 28,
      decoration: BoxDecoration(
        color: active ? AppColors.primary : AppColors.surface,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Icon(icon, size: 14, color: active ? Colors.white : AppColors.textGrey),
    ),
  );
}
