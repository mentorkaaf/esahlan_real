import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/network_image_widget.dart';
import 'eshop_providers.dart';

double _toD(dynamic v) => double.tryParse(v?.toString() ?? '0') ?? 0;
Map<String, dynamic> _asMap(dynamic v) {
  if (v is Map<String, dynamic>) return v;
  if (v is Map) return Map<String, dynamic>.from(v);
  return {};
}
List<Map<String, dynamic>> _asList(dynamic v) {
  if (v is! List) return [];
  return v.map(_asMap).toList();
}

class EShopPopularScreen extends ConsumerWidget {
  const EShopPopularScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final popularAsync = ref.watch(eshopPopularProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Popular Products'),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        foregroundColor: Theme.of(context).colorScheme.onSurface,
        elevation: 0,
      ),
      body: popularAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(e.toString())),
        data: (products) {
          if (products.isEmpty) {
            return const Center(
              child: Text('No popular products yet', style: TextStyle(color: AppColors.textGrey)),
            );
          }
          final cart = ref.watch(eshopCartProvider);
          return GridView.builder(
            padding: const EdgeInsets.all(12),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              childAspectRatio: 0.72,
            ),
            itemCount: products.length,
            itemBuilder: (_, i) => _PopularCard(product: products[i], cart: cart, rank: i + 1),
          );
        },
      ),
    );
  }
}

class _PopularCard extends ConsumerWidget {
  final Map<String, dynamic> product;
  final List cart;
  final int rank;
  const _PopularCard({required this.product, required this.cart, required this.rank});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final id = int.tryParse(product['id']?.toString() ?? '0') ?? 0;
    final name = product['name']?.toString() ?? '';
    final price = _toD(product['price']);
    final salePrice = product['sale_price'] != null ? _toD(product['sale_price']) : null;
    final displayPrice = salePrice ?? price;
    final rating = _toD(product['rating'] ?? product['average_rating']);
    final salesCount = int.tryParse(product['sales_count']?.toString() ?? '0') ?? 0;
    final vendor = _asMap(product['vendor']);
    final vendorName = vendor['name']?.toString() ?? '';
    final inCart = cart.any((c) => c.productId == id);

    return GestureDetector(
      onTap: () => context.push('/eshop/products/$id'),
      child: Container(
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 8, offset: const Offset(0, 2))],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                ClipRRect(
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
                  child: AspectRatio(
                    aspectRatio: 1.1,
                    child: NetImage(url: product['thumbnail']?.toString(), fit: BoxFit.cover),
                  ),
                ),
                if (rank <= 3)
                  Positioned(
                    top: 6,
                    left: 6,
                    child: Container(
                      width: 26,
                      height: 26,
                      decoration: BoxDecoration(
                        color: rank == 1 ? const Color(0xFFFFD700)
                            : rank == 2 ? const Color(0xFFC0C0C0)
                            : const Color(0xFFCD7F32),
                        shape: BoxShape.circle,
                      ),
                      child: Center(
                        child: Text('#$rank',
                            style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w900, color: Colors.white)),
                      ),
                    ),
                  ),
              ],
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name, maxLines: 2, overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                    if (vendorName.isNotEmpty)
                      Text(vendorName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 10, color: AppColors.textGrey)),
                    if (rating > 0)
                      Row(children: [
                        const Icon(Icons.star, size: 11, color: Color(0xFFFFC107)),
                        const SizedBox(width: 2),
                        Text(rating.toStringAsFixed(1),
                            style: const TextStyle(fontSize: 10, color: AppColors.textGrey)),
                        if (salesCount > 0) ...[
                          const SizedBox(width: 6),
                          Text('$salesCount sold',
                              style: const TextStyle(fontSize: 10, color: AppColors.textGrey)),
                        ],
                      ]),
                    const Spacer(),
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('\$${displayPrice.toStringAsFixed(2)}',
                                  style: const TextStyle(
                                      fontSize: 13, fontWeight: FontWeight.w800, color: Color(0xFFFF8A00))),
                              if (salePrice != null && price > salePrice)
                                Text('\$${price.toStringAsFixed(2)}',
                                    style: const TextStyle(
                                        fontSize: 10, color: AppColors.textGrey,
                                        decoration: TextDecoration.lineThrough)),
                            ],
                          ),
                        ),
                        GestureDetector(
                          onTap: () {
                            ref.read(eshopCartProvider.notifier).addItem(product);
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(content: Text('$name added'), duration: const Duration(seconds: 1)),
                            );
                          },
                          child: Container(
                            width: 28,
                            height: 28,
                            decoration: BoxDecoration(
                              color: inCart ? const Color(0xFFFF8A00) : const Color(0xFFFF8A00).withOpacity(0.1),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Icon(
                              inCart ? Icons.check : Icons.add,
                              size: 16,
                              color: inCart ? Colors.white : const Color(0xFFFF8A00),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
