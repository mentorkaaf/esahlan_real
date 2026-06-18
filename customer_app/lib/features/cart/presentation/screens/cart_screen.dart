import 'package:flutter/material.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/utils/error_handler.dart';
import '../../../../shared/widgets/app_button.dart';
import 'package:dio/dio.dart';

// Cart item model
class CartItem {
  final int id;
  final int productId;
  final String productName;
  final double price;
  int quantity;
  final String? imageUrl;

  CartItem({
    required this.id,
    required this.productId,
    required this.productName,
    required this.price,
    required this.quantity,
    this.imageUrl,
  });

  factory CartItem.fromJson(Map<String, dynamic> j) => CartItem(
    id: j['id'],
    productId: j['product_id'],
    productName: j['product']?['name'] ?? 'Item',
    price: (j['price'] as num?)?.toDouble() ?? 0,
    quantity: j['quantity'] ?? 1,
    imageUrl: j['product']?['image_url'],
  );

  double get total => price * quantity;
}

final cartProvider = FutureProvider<List<CartItem>>((ref) async {
  try {
    final res = await ApiClient.instance.get('/cart');
    final list = res.data['data'] as List? ?? [];
    return list.map((e) => CartItem.fromJson(e)).toList();
  } on DioException catch (e) {
    throw ApiException.fromDio(e);
  }
});

class CartScreen extends ConsumerWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cartAsync = ref.watch(cartProvider);

    return Scaffold(
      backgroundColor: context.colors.scaffoldBg,
      appBar: AppBar(
        title: const Text('My Cart', style: TextStyle(fontWeight: FontWeight.w800)),
        
        foregroundColor: context.colors.navyText,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20),
          onPressed: () => context.pop(),
        ),
      ),
      body: cartAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: AppColors.primary)),
        error: (e, _) => Center(child: Text(AppErrorHandler.message(e))),
        data: (items) {
          if (items.isEmpty) {
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text('🛒', style: TextStyle(fontSize: 64)),
                  const SizedBox(height: 16),
                  Text('Your cart is empty', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: context.colors.navyText)),
                  const SizedBox(height: 8),
                  const Text('Add items from a vendor', style: TextStyle(color: AppColors.textGrey)),
                  const SizedBox(height: 24),
                  AppButton(label: 'Browse Services', onPressed: () => context.go('/home'), width: 200),
                ],
              ),
            );
          }

          final subtotal  = items.fold(0.0, (s, i) => s + i.total);
          final delivery  = 1.0;
          final total     = subtotal + delivery;

          return Column(
            children: [
              Expanded(
                child: ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (_, i) => _CartItemCard(item: items[i], onChanged: () => ref.refresh(cartProvider)),
                ),
              ),
              // Summary
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: context.colors.cardBg,
                  boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.08), blurRadius: 16, offset: const Offset(0, -4))],
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
                ),
                child: Column(
                  children: [
                    _SummaryRow(label: 'Subtotal', value: '\$${subtotal.toStringAsFixed(2)}'),
                    const SizedBox(height: 6),
                    _SummaryRow(label: 'Delivery Fee', value: '\$${delivery.toStringAsFixed(2)}'),
                    const Divider(height: 20, color: AppColors.divider),
                    _SummaryRow(label: 'Total', value: '\$${total.toStringAsFixed(2)}', bold: true),
                    const SizedBox(height: 16),
                    AppButton(label: 'Proceed to Checkout', onPressed: () {}),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _CartItemCard extends StatelessWidget {
  final CartItem item;
  final VoidCallback onChanged;
  const _CartItemCard({required this.item, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: context.colors.cardBg, borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 6)],
      ),
      child: Row(
        children: [
          Container(
            width: 60, height: 60,
            decoration: BoxDecoration(color: context.colors.surfaceBg, borderRadius: BorderRadius.circular(10)),
            child: const Icon(Icons.fastfood_outlined, color: AppColors.textLight, size: 28),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item.productName,
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: context.colors.navyText)),
                const SizedBox(height: 4),
                Text('\$${item.price.toStringAsFixed(2)} each',
                  style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text('\$${item.total.toStringAsFixed(2)}',
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: AppColors.primary)),
              const SizedBox(height: 6),
              // Quantity controls
              Row(
                children: [
                  _QtyBtn(icon: Icons.remove_rounded, onTap: () {}),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 10),
                    child: Text('${item.quantity}',
                      style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: context.colors.navyText)),
                  ),
                  _QtyBtn(icon: Icons.add_rounded, onTap: () {}),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _QtyBtn extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  const _QtyBtn({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 28, height: 28,
        decoration: BoxDecoration(
          color: AppColors.primary.withOpacity(0.1),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Icon(icon, size: 16, color: AppColors.primary),
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  final String label;
  final String value;
  final bool bold;
  const _SummaryRow({required this.label, required this.value, this.bold = false});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: TextStyle(
          color: bold ? context.colors.navyText : AppColors.textGrey,
          fontWeight: bold ? FontWeight.w700 : FontWeight.w400,
          fontSize: bold ? 16 : 14,
        )),
        Text(value, style: TextStyle(
          color: context.colors.navyText,
          fontWeight: bold ? FontWeight.w900 : FontWeight.w600,
          fontSize: bold ? 18 : 14,
        )),
      ],
    );
  }
}
