import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../data/models/egrocery_models.dart';
import '../../ui/eg_theme.dart';
import '../../ui/eg_widgets.dart';
import '../providers/egrocery_providers.dart';
import '../../../../core/widgets/network_image_widget.dart';

class EGProductDetailScreen extends ConsumerStatefulWidget {
  final String slug;
  const EGProductDetailScreen({super.key, required this.slug});

  @override
  ConsumerState<EGProductDetailScreen> createState() => _EGProductDetailScreenState();
}

class _EGProductDetailScreenState extends ConsumerState<EGProductDetailScreen> {
  int _selectedVariantIdx = 0;
  int _imageIdx = 0;
  final _pageCtrl = PageController();

  @override
  void dispose() {
    _pageCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final productAsync = ref.watch(egProductDetailProvider(widget.slug));

    return Scaffold(
      backgroundColor: EGTheme.bg,
      body: productAsync.when(
        loading: () => _DetailSkeleton(),
        error: (e, _) => Scaffold(
          appBar: AppBar(title: const Text('Product'), iconTheme: const IconThemeData(color: EGTheme.textDark)),
          body: EGEmptyState(emoji: '😕', title: 'Error loading product', subtitle: e.toString(),
              onRetry: () => ref.invalidate(egProductDetailProvider(widget.slug))),
        ),
        data: (product) {
          final allImages = product.images.isNotEmpty ? product.images : [product.image ?? ''];
          final selectedVariant = product.variants.isNotEmpty ? product.variants[_selectedVariantIdx] : null;

          return Stack(children: [
            CustomScrollView(slivers: [
              // ── Image gallery ──────────────────────────────────────────────
              SliverAppBar(
                expandedHeight: 300,
                pinned: true,
                backgroundColor: Colors.white,
                iconTheme: const IconThemeData(color: EGTheme.textDark),
                actions: [
                  IconButton(
                    icon: const Icon(Icons.share),
                    onPressed: () {},
                  ),
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: EGCartBadge(onTap: () => context.push('/egrocery/cart')),
                  ),
                ],
                flexibleSpace: FlexibleSpaceBar(
                  background: Stack(children: [
                    PageView.builder(
                      controller: _pageCtrl,
                      onPageChanged: (i) => setState(() => _imageIdx = i),
                      itemCount: allImages.length,
                      itemBuilder: (_, i) => NetImage(
                        url: allImages[i],
                        fit: BoxFit.cover,
                        errorWidget: Container(color: EGTheme.shimmer, child: const Center(child: Icon(Icons.image, size: 48, color: EGTheme.textGrey))),
                      ),
                    ),
                    // Dot indicator
                    if (allImages.length > 1)
                      Positioned(
                        bottom: 12,
                        left: 0,
                        right: 0,
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: List.generate(allImages.length, (i) => AnimatedContainer(
                            duration: const Duration(milliseconds: 250),
                            margin: const EdgeInsets.symmetric(horizontal: 3),
                            width: i == _imageIdx ? 18 : 6,
                            height: 6,
                            decoration: BoxDecoration(
                              color: i == _imageIdx ? EGTheme.orange : Colors.white.withOpacity(0.7),
                              borderRadius: BorderRadius.circular(3),
                            ),
                          )),
                        ),
                      ),
                  ]),
                ),
              ),

              SliverToBoxAdapter(child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 120),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

                  // Name + brand
                  Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Expanded(child: Text(product.name, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: EGTheme.textDark))),
                    if (product.avgRating > 0)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(color: EGTheme.orange.withOpacity(0.12), borderRadius: BorderRadius.circular(8)),
                        child: Row(mainAxisSize: MainAxisSize.min, children: [
                          const Icon(Icons.star, color: EGTheme.orange, size: 14),
                          const SizedBox(width: 3),
                          Text(product.avgRating.toStringAsFixed(1), style: const TextStyle(color: EGTheme.orange, fontWeight: FontWeight.w800, fontSize: 13)),
                        ]),
                      ),
                  ]),
                  if (product.brand != null || product.category != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(
                        [if (product.brand != null) product.brand!, if (product.category != null) product.category!.name].join(' · '),
                        style: EGTheme.caption,
                      ),
                    ),

                  const SizedBox(height: 16),

                  // ── Variant chips ────────────────────────────────────────
                  if (product.variants.length > 1) ...[
                    const Text('Select Size', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: EGTheme.textDark)),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: product.variants.asMap().entries.map((e) {
                        final i = e.key;
                        final v = e.value;
                        final sel = i == _selectedVariantIdx;
                        return GestureDetector(
                          onTap: () => setState(() => _selectedVariantIdx = i),
                          child: AnimatedContainer(
                            duration: const Duration(milliseconds: 180),
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                            decoration: BoxDecoration(
                              color: sel ? EGTheme.orange : Colors.white,
                              borderRadius: BorderRadius.circular(EGTheme.rChip),
                              border: Border.all(color: sel ? EGTheme.orange : EGTheme.shimmer, width: 1.5),
                            ),
                            child: Column(children: [
                              Text(v.label, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: sel ? Colors.white : EGTheme.textDark)),
                              Text('\$${v.effectivePrice.toStringAsFixed(2)}', style: TextStyle(fontSize: 11, color: sel ? Colors.white.withOpacity(0.9) : EGTheme.textGrey)),
                            ]),
                          ),
                        );
                      }).toList(),
                    ),
                    const SizedBox(height: 16),
                  ],

                  // Description
                  if (product.description != null && product.description!.isNotEmpty) ...[
                    const Text('Description', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: EGTheme.textDark)),
                    const SizedBox(height: 8),
                    Text(product.description!, style: const TextStyle(fontSize: 14, color: EGTheme.textGrey, height: 1.6)),
                    const SizedBox(height: 16),
                  ],

                  // Reviews
                  if (product.reviews.isNotEmpty) ...[
                    Row(children: [
                      const Text('Reviews', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: EGTheme.textDark)),
                      const SizedBox(width: 8),
                      Text('(${product.reviewsCount ?? product.reviews.length})', style: EGTheme.caption),
                    ]),
                    const SizedBox(height: 10),
                    ...product.reviews.map((r) => _ReviewTile(review: r)),
                    const SizedBox(height: 16),
                  ],

                  // Related
                  if (product.related.isNotEmpty) ...[
                    const Text('You may also like', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: EGTheme.textDark)),
                    const SizedBox(height: 10),
                    SizedBox(
                      height: 230,
                      child: ListView.builder(
                        scrollDirection: Axis.horizontal,
                        itemCount: product.related.length,
                        itemBuilder: (_, i) => Padding(
                          padding: const EdgeInsets.only(right: 12),
                          child: EGProductCard(
                            product: product.related[i],
                            onTap: () => context.push('/egrocery/product/${product.related[i].slug}'),
                          ),
                        ),
                      ),
                    ),
                  ],
                ]),
              )),
            ]),

            // ── Sticky bottom bar ────────────────────────────────────────────
            if (selectedVariant != null)
              Positioned(
                bottom: 0,
                left: 0,
                right: 0,
                child: _StickyBar(product: product, variant: selectedVariant, isWeightBased: product.isWeightBased),
              ),
          ]);
        },
      ),
    );
  }
}

class _StickyBar extends ConsumerStatefulWidget {
  final EGProduct product;
  final EGVariant variant;
  final bool isWeightBased;
  const _StickyBar({required this.product, required this.variant, required this.isWeightBased});

  @override
  ConsumerState<_StickyBar> createState() => _StickyBarState();
}

class _StickyBarState extends ConsumerState<_StickyBar> {
  double _qty = 1;

  @override
  Widget build(BuildContext context) {
    final cart = ref.watch(egCartProvider);
    final inCart = cart.any((l) => l.variantId == widget.variant.id);

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.08), blurRadius: 16, offset: const Offset(0, -4))],
      ),
      child: Row(children: [
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Total', style: TextStyle(fontSize: 11, color: EGTheme.textGrey)),
          Text('\$${(widget.variant.effectivePrice * _qty).toStringAsFixed(2)}',
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: EGTheme.textDark)),
        ]),
        const SizedBox(width: 16),
        EGQtyStepper(
          qty: _qty,
          isWeightBased: widget.isWeightBased,
          step: widget.isWeightBased ? 0.25 : 1,
          onChanged: (q) { if (q > 0) setState(() => _qty = q); },
        ),
        const SizedBox(width: 12),
        Expanded(
          child: ElevatedButton(
            onPressed: widget.variant.inStock
                ? () {
                    ref.read(egCartProvider.notifier).addOrIncrement(widget.variant, widget.product, qty: _qty);
                    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                      content: Text('${widget.product.name} added to cart 🛒'),
                      backgroundColor: EGTheme.orange,
                      duration: const Duration(seconds: 1),
                      behavior: SnackBarBehavior.floating,
                    ));
                  }
                : null,
            style: ElevatedButton.styleFrom(
              backgroundColor: EGTheme.orange,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(EGTheme.rBtn)),
            ),
            child: Text(inCart ? 'Update Cart' : 'Add to Cart', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
          ),
        ),
      ]),
    );
  }
}

class _ReviewTile extends StatelessWidget {
  final EGReview review;
  const _ReviewTile({required this.review});

  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            CircleAvatar(
              radius: 16,
              backgroundColor: EGTheme.orange.withOpacity(0.15),
              child: Text(review.userName?.substring(0, 1).toUpperCase() ?? '?',
                  style: const TextStyle(fontWeight: FontWeight.w800, color: EGTheme.orange)),
            ),
            const SizedBox(width: 8),
            Expanded(child: Text(review.userName ?? 'User', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13))),
            Row(children: List.generate(5, (i) => Icon(Icons.star, size: 13, color: i < review.rating ? EGTheme.orange : EGTheme.shimmer))),
          ]),
          if (review.comment != null && review.comment!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(review.comment!, style: const TextStyle(fontSize: 13, color: EGTheme.textGrey)),
          ],
          if (review.date != null) ...[
            const SizedBox(height: 4),
            Text(review.date!, style: const TextStyle(fontSize: 11, color: EGTheme.textGrey)),
          ],
        ]),
      );
}

class _DetailSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Column(children: [
        EGShimmerBox(width: double.infinity, height: 300, radius: 0),
        const SizedBox(height: 16),
        Padding(padding: const EdgeInsets.symmetric(horizontal: 16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          EGShimmerBox(width: 220, height: 24, radius: 6),
          const SizedBox(height: 10),
          EGShimmerBox(width: 120, height: 16, radius: 6),
          const SizedBox(height: 16),
          Row(children: [EGShimmerBox(width: 70, height: 36, radius: EGTheme.rChip), const SizedBox(width: 8), EGShimmerBox(width: 70, height: 36, radius: EGTheme.rChip)]),
        ])),
      ]);
}
