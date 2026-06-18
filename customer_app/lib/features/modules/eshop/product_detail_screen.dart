import 'dart:async';
import '../../../core/theme/theme_x.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/network_image_widget.dart';
import '../../../shared/widgets/app_button.dart';
import 'eshop_providers.dart';

double _toD(dynamic v) => double.tryParse(v?.toString() ?? '0') ?? 0;

// ─────────────────────────────────────────────────────────────────
// Product Detail Screen
// ─────────────────────────────────────────────────────────────────
class ProductDetailScreen extends ConsumerStatefulWidget {
  final int productId;
  const ProductDetailScreen({super.key, required this.productId});

  @override
  ConsumerState<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends ConsumerState<ProductDetailScreen> {
  int _imageIndex = 0;
  int? _selectedVariantId;
  int _qty = 1;
  bool _descExpanded = false;
  final _pageCtrl = PageController();
  Timer? _sliderTimer;
  List<String> _cachedImages = [];

  void _startAutoSlide() {
    _sliderTimer?.cancel();
    if (_cachedImages.length <= 1) return;
    _sliderTimer = Timer.periodic(const Duration(seconds: 3), (_) {
      if (!mounted || _cachedImages.isEmpty) return;
      final next = (_imageIndex + 1) % _cachedImages.length;
      _pageCtrl.animateToPage(next, duration: const Duration(milliseconds: 500), curve: Curves.easeInOut);
    });
  }

  @override
  void dispose() {
    _sliderTimer?.cancel();
    _pageCtrl.dispose();
    super.dispose();
  }

  double _effectivePrice(Map<String, dynamic> product) {
    if (_selectedVariantId != null) {
      final variants = (product['variants'] as List?) ?? [];
      final v = variants.cast<Map<String, dynamic>>().where((v) => v['id'] == _selectedVariantId).firstOrNull;
      if (v != null && v['price'] != null) return _toD(v['price']);
    }
    final sale = product['sale_price'];
    if (sale != null && _toD(sale) > 0) return _toD(sale);
    return _toD(product['price']);
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(eshopProductProvider(widget.productId));
    final cartNotifier = ref.read(eshopCartProvider.notifier);
    ref.watch(eshopCartProvider);
    final inWishlist = ref.watch(eshopWishlistProvider).contains(widget.productId);

    return Scaffold(
      
      body: async.when(
        loading: () => const Scaffold(body: Center(child: CircularProgressIndicator(color: AppColors.primary))),
        error: (e, _) => Scaffold(
          appBar: AppBar(leading: BackButton(onPressed: () => context.pop())),
          body: Center(child: Text('Error: $e')),
        ),
        data: (product) {
          if (product.isEmpty) {
            return Scaffold(
              appBar: AppBar(leading: BackButton(onPressed: () => context.pop())),
              body: const Center(child: Text('Product not found')),
            );
          }

          final images = (product['images'] as List?) ?? [];
          final galleryUrls = images.map((img) => img['image'] as String?).whereType<String>().toList();
          // Always show thumbnail first, then unique gallery images
          final thumb = product['thumbnail'] as String?;
          final allImages = <String>[
            if (thumb != null && thumb.isNotEmpty) thumb,
            ...galleryUrls.where((u) => u != thumb),
          ];
          // Start auto-slider when images list changes
          if (_cachedImages.length != allImages.length) {
            _cachedImages = allImages;
            WidgetsBinding.instance.addPostFrameCallback((_) => _startAutoSlide());
          }
          final variants = (product['variants'] as List?) ?? [];
          // is_active may come as bool true or int 1 depending on DB driver
          final activeVariants = variants.cast<Map<String, dynamic>>()
              .where((v) => v['is_active'] == true || v['is_active'] == 1).toList();
          final price = _effectivePrice(product);
          final origPrice = _toD(product['price'] ?? price);
          final salePrice = product['sale_price'] != null ? _toD(product['sale_price']) : null;
          final hasDiscount = salePrice != null && salePrice < origPrice;
          final discountPct = hasDiscount ? ((origPrice - salePrice) / origPrice * 100).round() : 0;
          final stock = product['stock_quantity'] as int? ?? 0;
          final description = product['description'] as String? ?? '';
          final cartQty = cartNotifier.qtyFor(widget.productId, variantId: _selectedVariantId);

          return CustomScrollView(slivers: [
            SliverAppBar(
              expandedHeight: 320,
              pinned: true,
              
              leading: Container(
                margin: const EdgeInsets.all(8),
                decoration: BoxDecoration(color: context.colors.cardBg, shape: BoxShape.circle,
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 8)]),
                child: IconButton(icon: Icon(Icons.arrow_back_ios_new_rounded, size: 18, color: context.colors.navyText), onPressed: () => context.pop()),
              ),
              actions: [
                Container(
                  margin: const EdgeInsets.all(8),
                  decoration: BoxDecoration(color: context.colors.cardBg, shape: BoxShape.circle,
                    boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 8)]),
                  child: IconButton(
                    icon: Icon(inWishlist ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                      size: 18, color: inWishlist ? Colors.red : AppColors.secondary),
                    onPressed: () {
                      final wl = ref.read(eshopWishlistProvider);
                      ref.read(eshopWishlistProvider.notifier).state = inWishlist
                          ? (Set<int>.from(wl)..remove(widget.productId))
                          : (Set<int>.from(wl)..add(widget.productId));
                    },
                  ),
                ),
                Container(
                  margin: const EdgeInsets.only(right: 8, top: 8, bottom: 8),
                  decoration: BoxDecoration(color: context.colors.cardBg, shape: BoxShape.circle,
                    boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 8)]),
                  child: IconButton(icon: Icon(Icons.share_outlined, size: 18, color: context.colors.navyText), onPressed: () {}),
                ),
              ],
              flexibleSpace: FlexibleSpaceBar(
                background: Stack(children: [
                  allImages.isNotEmpty
                      ? PageView.builder(
                          controller: _pageCtrl,
                          itemCount: allImages.length,
                          onPageChanged: (i) {
                            setState(() => _imageIndex = i);
                            // Restart timer after manual swipe
                            _startAutoSlide();
                          },
                          itemBuilder: (_, i) => Container(
                            color: context.colors.cardBg,
                            child: Image.network(fixImgUrl(allImages[i]), fit: BoxFit.contain,
                              loadingBuilder: (_, child, progress) => progress == null ? child
                                  : Center(child: CircularProgressIndicator(
                                      value: progress.expectedTotalBytes != null
                                          ? progress.cumulativeBytesLoaded / progress.expectedTotalBytes!
                                          : null,
                                      color: AppColors.primary, strokeWidth: 2)),
                              errorBuilder: (_, __, ___) => Container(color: AppColors.surface,
                                child: const Icon(Icons.image_outlined, size: 80, color: AppColors.divider))),
                          ),
                        )
                      : Container(color: AppColors.surface, child: const Icon(Icons.image_outlined, size: 80, color: AppColors.divider)),
                  // Image counter badge (top right)
                  if (allImages.length > 1) Positioned(top: 12, right: 12,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(20)),
                      child: Text('${_imageIndex + 1}/${allImages.length}',
                        style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
                    ),
                  ),
                  // Dots indicator (bottom)
                  if (allImages.length > 1) Positioned(bottom: 12, left: 0, right: 0,
                    child: Row(mainAxisAlignment: MainAxisAlignment.center, children: List.generate(allImages.length, (i) => AnimatedContainer(
                      duration: const Duration(milliseconds: 300),
                      margin: const EdgeInsets.symmetric(horizontal: 3),
                      width: _imageIndex == i ? 20 : 6,
                      height: 6,
                      decoration: BoxDecoration(
                        color: _imageIndex == i ? AppColors.primary : Colors.white.withValues(alpha: 0.6),
                        borderRadius: BorderRadius.circular(3),
                        boxShadow: [BoxShadow(color: Colors.black26, blurRadius: 2)],
                      ),
                    )))),
                ]),
              ),
            ),

            SliverToBoxAdapter(child: Container(
              color: context.colors.cardBg,
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Padding(padding: const EdgeInsets.fromLTRB(16, 20, 16, 0), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  // Category + Brand row
                  Row(children: [
                    if (product['category_name'] != null) Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(8)),
                      child: Text(product['category_name'], style: const TextStyle(fontSize: 11, color: AppColors.textGrey, fontWeight: FontWeight.w600)),
                    ),
                    if (product['brand'] != null) ...[
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(color: AppColors.primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
                        child: Text(product['brand'], style: const TextStyle(fontSize: 11, color: AppColors.primary, fontWeight: FontWeight.w700)),
                      ),
                    ],
                  ]),
                  SizedBox(height: 10),

                  // Name
                  Text(product['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 22, color: context.colors.navyText, height: 1.2)),
                  const SizedBox(height: 10),

                  // Rating
                  Row(children: [
                    ...List.generate(5, (i) {
                      final r = _toD(product['rating']);
                      if (i < r.floor()) return const Icon(Icons.star, color: Color(0xFFFFC107), size: 16);
                      if (i < r && r - i >= 0.5) return const Icon(Icons.star_half, color: Color(0xFFFFC107), size: 16);
                      return const Icon(Icons.star_border, color: Color(0xFFFFC107), size: 16);
                    }),
                    SizedBox(width: 6),
                    Text('${product['rating'] ?? 0}', style: TextStyle(fontWeight: FontWeight.w700, color: context.colors.navyText, fontSize: 14)),
                    Text(' (${product['total_reviews'] ?? 0} reviews)', style: const TextStyle(color: AppColors.textGrey, fontSize: 13)),
                  ]),
                  const SizedBox(height: 14),

                  // Price row
                  Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                    Text('\$${price.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 28, color: AppColors.primary)),
                    if (hasDiscount) ...[
                      const SizedBox(width: 8),
                      Padding(padding: const EdgeInsets.only(bottom: 3),
                        child: Text('\$${origPrice.toStringAsFixed(2)}', style: const TextStyle(decoration: TextDecoration.lineThrough, color: AppColors.textGrey, fontSize: 16))),
                    ],
                    if (hasDiscount) ...[
                      const SizedBox(width: 8),
                      Padding(padding: const EdgeInsets.only(bottom: 3), child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(color: AppColors.error, borderRadius: BorderRadius.circular(6)),
                        child: Text('-$discountPct%', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12)),
                      )),
                    ],
                  ]),
                  const SizedBox(height: 8),

                  // SKU / Unit row
                  if (product['sku'] != null || product['unit'] != null) Row(children: [
                    if (product['sku'] != null) Text('SKU: ${product['sku']}', style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
                    if (product['sku'] != null && product['unit'] != null) const Text(' • ', style: TextStyle(color: AppColors.textGrey)),
                    if (product['unit'] != null) Text('Unit: ${product['unit']}', style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
                  ]),
                  const SizedBox(height: 8),

                  // Stock indicator
                  Row(children: [
                    Container(
                      width: 8, height: 8,
                      decoration: BoxDecoration(
                        color: stock > 0 ? AppColors.success : AppColors.error,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      stock > 0 ? 'In Stock ($stock left)' : 'Out of Stock',
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: stock > 0 ? AppColors.success : AppColors.error),
                    ),
                  ]),

                  const SizedBox(height: 20),
                  const Divider(height: 1),
                  SizedBox(height: 20),
                ])),

                // Variants
                if (activeVariants.isNotEmpty) Padding(padding: const EdgeInsets.fromLTRB(16, 0, 16, 20), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Variants', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText)),
                  const SizedBox(height: 12),
                  Wrap(spacing: 8, runSpacing: 8, children: activeVariants.map((v) {
                    final selected = _selectedVariantId == v['id'];
                    return GestureDetector(
                      onTap: () => setState(() => _selectedVariantId = selected ? null : v['id'] as int?),
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                        decoration: BoxDecoration(
                          color: selected ? AppColors.primary : context.colors.cardBg,
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: selected ? AppColors.primary : AppColors.divider, width: selected ? 2 : 1),
                          boxShadow: selected ? [BoxShadow(color: AppColors.primary.withValues(alpha: 0.3), blurRadius: 8)] : [],
                        ),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.center, children: [
                          Text(v['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: selected ? Colors.white : context.colors.navyText)),
                          if (v['price'] != null) Text('\$${_toD(v['price']).toStringAsFixed(2)}',
                            style: TextStyle(fontWeight: FontWeight.w600, fontSize: 11, color: selected ? Colors.white.withValues(alpha: 0.8) : AppColors.primary)),
                          if (v['attributes'] != null && (v['attributes'] as Map).isNotEmpty)
                            Padding(padding: const EdgeInsets.only(top: 4),
                              child: Text(
                                (v['attributes'] as Map).entries.map((e) => '${e.key}: ${e.value}').join(' · '),
                                style: TextStyle(fontSize: 10, color: selected ? Colors.white.withValues(alpha: 0.7) : AppColors.textGrey),
                              )),
                        ]),
                      ),
                    );
                  }).toList()),
                  const Divider(height: 32),
                ])),

                // Quantity selector
                Padding(padding: const EdgeInsets.fromLTRB(16, 0, 16, 20), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Quantity', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText)),
                  SizedBox(height: 12),
                  Row(children: [
                    _qtyBtn(Icons.remove_rounded, () { if (_qty > 1) setState(() => _qty--); }, _qty > 1),
                    SizedBox(width: 60, child: Text('$_qty', textAlign: TextAlign.center,
                      style: TextStyle(fontWeight: FontWeight.w900, fontSize: 22, color: context.colors.navyText))),
                    _qtyBtn(Icons.add_rounded, () => setState(() => _qty++), true),
                    const Spacer(),
                    if (cartQty > 0) Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(color: AppColors.success.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
                      child: Text('$cartQty in cart', style: const TextStyle(color: AppColors.success, fontWeight: FontWeight.w700, fontSize: 12)),
                    ),
                  ]),
                  const Divider(height: 32),
                ])),

                // Description
                if (description.isNotEmpty) Padding(padding: const EdgeInsets.fromLTRB(16, 0, 16, 24), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Description', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText)),
                  const SizedBox(height: 10),
                  AnimatedSize(
                    duration: const Duration(milliseconds: 300),
                    child: Text(description,
                      maxLines: _descExpanded ? null : 3,
                      overflow: _descExpanded ? TextOverflow.visible : TextOverflow.ellipsis,
                      style: const TextStyle(color: AppColors.textGrey, fontSize: 14, height: 1.7)),
                  ),
                  if (description.length > 120) GestureDetector(
                    onTap: () => setState(() => _descExpanded = !_descExpanded),
                    child: Padding(padding: const EdgeInsets.only(top: 6), child: Text(
                      _descExpanded ? 'Show less' : 'Read more',
                      style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 13),
                    )),
                  ),
                ])),

                const SizedBox(height: 100),
              ]),
            )),
          ]);
        },
      ),
      bottomNavigationBar: async.hasValue && async.value!.isNotEmpty ? Container(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        decoration: BoxDecoration(
          color: context.colors.cardBg,
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 12, offset: const Offset(0, -2))],
        ),
        child: Row(children: [
          Expanded(child: AppButton(
            label: 'Add to Cart',
            outlined: true,
            onPressed: () {
              cartNotifier.addItem(async.value!, qty: _qty, variantId: _selectedVariantId,
                variant: _selectedVariantId != null
                    ? (async.value!['variants'] as List?)?.cast<Map<String, dynamic>>().where((v) => v['id'] == _selectedVariantId).firstOrNull
                    : null);
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: const Text('Added to cart'), backgroundColor: AppColors.success, behavior: SnackBarBehavior.floating,
                  action: SnackBarAction(label: 'View Cart', textColor: Colors.white, onPressed: () => context.push('/eshop/cart')),
                ),
              );
            },
          )),
          const SizedBox(width: 12),
          Expanded(child: AppButton(
            label: 'Buy Now',
            onPressed: () {
              cartNotifier.addItem(async.value!, qty: _qty, variantId: _selectedVariantId);
              context.push('/eshop/checkout');
            },
          )),
        ]),
      ) : null,
    );
  }

  Widget _qtyBtn(IconData icon, VoidCallback onTap, bool active) => GestureDetector(
    onTap: active ? onTap : null,
    child: AnimatedContainer(
      duration: const Duration(milliseconds: 150),
      width: 38, height: 38,
      decoration: BoxDecoration(
        color: active ? AppColors.primary : AppColors.surface,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Icon(icon, color: active ? Colors.white : AppColors.textGrey, size: 20),
    ),
  );
}
