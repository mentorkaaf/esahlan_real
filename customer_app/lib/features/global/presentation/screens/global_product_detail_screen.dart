import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/global_provider.dart';
import '../../data/models/global_models.dart';
import '../widgets/global_product_card.dart';

class GlobalProductDetailScreen extends ConsumerStatefulWidget {
  final int productId;
  const GlobalProductDetailScreen({super.key, required this.productId});

  @override
  ConsumerState<GlobalProductDetailScreen> createState() =>
      _GlobalProductDetailScreenState();
}

class _GlobalProductDetailScreenState
    extends ConsumerState<GlobalProductDetailScreen> {
  int _imgIndex = 0;
  int _qty = 1;
  String? _selectedVariant;
  bool _addingToCart = false;

  Future<void> _addToCart(GlobalProduct p) async {
    final auth = ref.read(globalAuthProvider).valueOrNull;
    if (auth == null) {
      context.push('/global/auth');
      return;
    }

    setState(() => _addingToCart = true);
    try {
      await ref
          .read(globalCartProvider.notifier)
          .add(p.id, _qty, variant: _selectedVariant);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: const Text('Added to cart ✓'),
            backgroundColor: Colors.green.shade700,
            behavior: SnackBarBehavior.floating,
            action: SnackBarAction(
                label: 'View Cart',
                textColor: Colors.white,
                onPressed: () => context.push('/global/cart')),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
              content: Text(e.toString()),
              backgroundColor: Colors.red.shade700),
        );
      }
    } finally {
      if (mounted) setState(() => _addingToCart = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final productAsync =
        ref.watch(globalProductDetailProvider(widget.productId));

    return Scaffold(
      backgroundColor: Colors.white,
      body: productAsync.when(
        data: (product) => _buildContent(product),
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.grey),
              const SizedBox(height: 12),
              Text(e.toString()),
              TextButton(
                  onPressed: () => ref.invalidate(
                      globalProductDetailProvider(widget.productId)),
                  child: const Text('Retry')),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildContent(GlobalProduct p) {
    final images = p.images.isNotEmpty
        ? p.images
        : (p.thumbnail != null ? [p.thumbnail!] : <String>[]);

    return Stack(
      children: [
        CustomScrollView(
          slivers: [
            // Image gallery
            SliverAppBar(
              expandedHeight: 320,
              pinned: true,
              backgroundColor: Colors.white,
              leading: GestureDetector(
                onTap: () => context.pop(),
                child: Container(
                  margin: const EdgeInsets.all(8),
                  decoration: const BoxDecoration(
                      color: Colors.white, shape: BoxShape.circle),
                  child: const Icon(Icons.arrow_back,
                      color: Color(0xFF1A1A2E)),
                ),
              ),
              actions: [
                Container(
                  margin: const EdgeInsets.all(8),
                  decoration: const BoxDecoration(
                      color: Colors.white, shape: BoxShape.circle),
                  child: IconButton(
                    icon: const Icon(Icons.shopping_cart_outlined,
                        color: Color(0xFF1A1A2E), size: 20),
                    onPressed: () => context.push('/global/cart'),
                  ),
                ),
              ],
              flexibleSpace: FlexibleSpaceBar(
                background: images.isNotEmpty
                    ? PageView.builder(
                        onPageChanged: (i) =>
                            setState(() => _imgIndex = i),
                        itemCount: images.length,
                        itemBuilder: (_, i) => Image.network(
                          images[i],
                          fit: BoxFit.contain,
                          errorBuilder: (_, __, ___) => Container(
                              color: Colors.grey.shade100,
                              child: const Icon(Icons.image_outlined,
                                  size: 60, color: Colors.grey)),
                        ),
                      )
                    : Container(
                        color: Colors.grey.shade100,
                        child: const Icon(Icons.image_outlined,
                            size: 60, color: Colors.grey)),
              ),
            ),

            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 120),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                  // Image dots
                  if (images.length > 1)
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: List.generate(
                          images.length,
                          (i) => AnimatedContainer(
                                duration: const Duration(milliseconds: 200),
                                margin:
                                    const EdgeInsets.symmetric(horizontal: 3),
                                width: _imgIndex == i ? 20 : 6,
                                height: 6,
                                decoration: BoxDecoration(
                                  color: _imgIndex == i
                                      ? const Color(0xFF1A1A2E)
                                      : Colors.grey.shade300,
                                  borderRadius: BorderRadius.circular(3),
                                ),
                              )),
                    ),
                  const SizedBox(height: 16),

                  // Category badge
                  if (p.category != null)
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: const Color(0xFFEDE9FE),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(p.category!,
                          style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                              color: Color(0xFF5B21B6))),
                    ),
                  const SizedBox(height: 10),

                  // Name
                  Text(p.name,
                      style: const TextStyle(
                          fontSize: 20, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 10),

                  // Rating
                  if (p.rating > 0)
                    Row(children: [
                      ...List.generate(5, (i) {
                        final filled = i < p.rating.round();
                        return Icon(
                            filled ? Icons.star : Icons.star_border,
                            size: 16,
                            color: const Color(0xFFF59E0B));
                      }),
                      const SizedBox(width: 6),
                      Text('${p.rating.toStringAsFixed(1)} (${p.reviewsCount} reviews)',
                          style: TextStyle(
                              color: Colors.grey.shade600, fontSize: 12)),
                    ]),
                  const SizedBox(height: 14),

                  // Price
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(p.formattedPrice,
                          style: const TextStyle(
                              fontSize: 28,
                              fontWeight: FontWeight.w900,
                              color: Color(0xFF1A1A2E))),
                      if (p.formattedComparePrice != null) ...[
                        const SizedBox(width: 8),
                        Text(p.formattedComparePrice!,
                            style: TextStyle(
                                fontSize: 16,
                                color: Colors.grey.shade400,
                                decoration: TextDecoration.lineThrough)),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: Colors.red.shade50,
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text('${p.discountPct}% OFF',
                              style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                  color: Colors.red.shade700)),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 8),

                  // Stock
                  Row(children: [
                    Container(
                      width: 8,
                      height: 8,
                      decoration: BoxDecoration(
                        color: p.inStock
                            ? Colors.green.shade500
                            : Colors.red.shade500,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(p.inStock ? 'In Stock' : 'Out of Stock',
                        style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: p.inStock
                                ? Colors.green.shade700
                                : Colors.red.shade700)),
                  ]),
                  const SizedBox(height: 20),

                  // Variants
                  if (p.variants.isNotEmpty) ...[
                    const Text('Options',
                        style: TextStyle(
                            fontSize: 14, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: p.variants.map((v) {
                        final label = v is Map
                            ? (v['name'] ?? v.toString())
                            : v.toString();
                        final selected = _selectedVariant == label;
                        return GestureDetector(
                          onTap: () =>
                              setState(() => _selectedVariant = label),
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 14, vertical: 8),
                            decoration: BoxDecoration(
                              color: selected
                                  ? const Color(0xFF1A1A2E)
                                  : Colors.white,
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(
                                  color: selected
                                      ? const Color(0xFF1A1A2E)
                                      : Colors.grey.shade300),
                            ),
                            child: Text(label,
                                style: TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                    color: selected
                                        ? Colors.white
                                        : Colors.grey.shade700)),
                          ),
                        );
                      }).toList(),
                    ),
                    const SizedBox(height: 20),
                  ],

                  // Qty selector
                  Row(children: [
                    const Text('Quantity:',
                        style: TextStyle(
                            fontSize: 14, fontWeight: FontWeight.w600)),
                    const Spacer(),
                    _QtyButton(
                        icon: Icons.remove,
                        onTap: () {
                          if (_qty > 1) setState(() => _qty--);
                        }),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Text('$_qty',
                          style: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w800)),
                    ),
                    _QtyButton(
                        icon: Icons.add,
                        onTap: () => setState(() => _qty++)),
                  ]),
                  const SizedBox(height: 24),

                  const Divider(),
                  const SizedBox(height: 16),

                  // Description
                  if (p.description != null && p.description!.isNotEmpty) ...[
                    const Text('Description',
                        style: TextStyle(
                            fontSize: 15, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 8),
                    Text(p.description!,
                        style: TextStyle(
                            fontSize: 13,
                            color: Colors.grey.shade700,
                            height: 1.6)),
                    const SizedBox(height: 16),
                  ],

                  // Tags
                  if (p.tags.isNotEmpty) ...[
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: p.tags
                          .map((t) => Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 10, vertical: 4),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF0F2F5),
                                  borderRadius: BorderRadius.circular(20),
                                ),
                                child: Text('#$t',
                                    style: TextStyle(
                                        fontSize: 11,
                                        color: Colors.grey.shade600)),
                              ))
                          .toList(),
                    ),
                    const SizedBox(height: 16),
                  ],
                ]),
              ),
            ),
          ],
        ),

        // Bottom CTA
        Positioned(
          bottom: 0,
          left: 0,
          right: 0,
          child: Container(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
            decoration: BoxDecoration(
              color: Colors.white,
              boxShadow: [
                BoxShadow(
                    color: Colors.black.withOpacity(0.08),
                    blurRadius: 12,
                    offset: const Offset(0, -4))
              ],
            ),
            child: Row(children: [
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed:
                        (_addingToCart || !p.inStock) ? null : () => _addToCart(p),
                    icon: _addingToCart
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                                color: Colors.white, strokeWidth: 2))
                        : const Icon(Icons.shopping_cart_outlined, size: 18),
                    label: Text(
                        p.inStock ? 'Add to Cart' : 'Out of Stock',
                        style: const TextStyle(
                            fontWeight: FontWeight.w700, fontSize: 14)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF1A1A2E),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                ElevatedButton(
                  onPressed: p.inStock
                      ? () async {
                          await _addToCart(p);
                          if (mounted) context.push('/global/checkout');
                        }
                      : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFF59E0B),
                    foregroundColor: const Color(0xFF1A1A2E),
                    padding: const EdgeInsets.symmetric(
                        vertical: 14, horizontal: 20),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12)),
                  ),
                  child: const Text('Buy Now',
                      style: TextStyle(fontWeight: FontWeight.w800)),
                ),
              ]),
          ),
        ),
      ],
    );
  }
}

class _QtyButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;

  const _QtyButton({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: const Color(0xFFF0F2F5),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Icon(icon, size: 16, color: const Color(0xFF1A1A2E)),
      ),
    );
  }
}
