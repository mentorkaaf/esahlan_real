import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/global_provider.dart';
import '../../data/models/global_models.dart';
import 'global_write_review_screen.dart';
import 'global_shell.dart' show GlobalDesktopFooter;
import '../../../../core/l10n/app_strings.dart';

class GlobalProductDetailScreen extends ConsumerStatefulWidget {
  final int productId;
  const GlobalProductDetailScreen({super.key, required this.productId});

  @override
  ConsumerState<GlobalProductDetailScreen> createState() =>
      _GlobalProductDetailScreenState();
}

class _GlobalProductDetailScreenState
    extends ConsumerState<GlobalProductDetailScreen> {
  final _pageCtrl   = PageController();
  int    _imgIndex  = 0;
  int    _qty       = 1;
  String? _selectedVariant;
  bool   _addingToCart = false;
  bool   _buyingNow    = false;

  // ── Cart action ───────────────────────────────────────────────────────────

  Future<bool> _doAddToCart(GlobalProduct p) async {
    final auth = ref.read(globalAuthProvider).valueOrNull;
    if (auth == null) {
      context.push('/global/auth');
      return false;
    }
    try {
      await ref
          .read(globalCartProvider.notifier)
          .add(p.id, _qty, variant: _selectedVariant);
      return true;
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(e.toString()),
          backgroundColor: Colors.red.shade700,
        ));
      }
      return false;
    }
  }

  Future<void> _onAddToCart(GlobalProduct p) async {
    setState(() => _addingToCart = true);
    final ok = await _doAddToCart(p);
    if (mounted) {
      setState(() => _addingToCart = false);
      if (ok) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('✓  ${AppL10n.of(context).addedToCart}'),
            backgroundColor: Colors.green.shade700,
            behavior: SnackBarBehavior.floating,
            duration: const Duration(seconds: 2),
            action: SnackBarAction(
              label: AppL10n.of(context).viewCart,
              textColor: Colors.white,
              onPressed: () => context.go('/global/cart'),
            ),
          ),
        );
      }
    }
  }

  Future<void> _onBuyNow(GlobalProduct p) async {
    setState(() => _buyingNow = true);
    final ok = await _doAddToCart(p);
    if (mounted) {
      setState(() => _buyingNow = false);
      if (ok) context.push('/global/checkout');
    }
  }

  // ── Build ─────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final productAsync =
        ref.watch(globalProductDetailProvider(widget.productId));

    return productAsync.when(
      loading: () => Scaffold(
        appBar: AppBar(
          backgroundColor: Colors.white,
          foregroundColor: const Color(0xFF1A1A2E),
          elevation: 0,
        ),
        body: const Center(child: CircularProgressIndicator()),
      ),
      error: (e, _) => Scaffold(
        appBar: AppBar(
          backgroundColor: Colors.white,
          foregroundColor: const Color(0xFF1A1A2E),
          elevation: 0,
        ),
        body: Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.error_outline, size: 48, color: Colors.grey),
            const SizedBox(height: 12),
            Text(e.toString(),
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.grey)),
            const SizedBox(height: 16),
            ElevatedButton.icon(
              onPressed: () => ref
                  .invalidate(globalProductDetailProvider(widget.productId)),
              icon: const Icon(Icons.refresh),
              label: const Text('Retry'),
            ),
          ]),
        ),
      ),
      data: (p) => _buildScaffold(p),
    );
  }

  Widget _buildScaffold(GlobalProduct p) {
    final l = AppL10n.of(context);
    final images = p.images.isNotEmpty
        ? p.images
        : (p.thumbnail != null ? [p.thumbnail!] : <String>[]);

    // Route to desktop layout on web ≥ 1024 px
    if (kIsWeb && MediaQuery.of(context).size.width >= 1024) {
      return _buildDesktopScaffold(p, images, l);
    }

    return Scaffold(
      backgroundColor: Colors.white,

      // ── App bar ──────────────────────────────────────────────────────────
      appBar: AppBar(
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF1A1A2E),
        elevation: 0,
        scrolledUnderElevation: 1,
        leadingWidth: 48,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20),
          onPressed: () => context.pop(),
        ),
        actions: [
          // Share
          IconButton(
            icon: const Icon(Icons.share_outlined, size: 22),
            onPressed: () {},
          ),
          // Cart with badge
          Consumer(builder: (_, ref, __) {
            final count =
                ref.watch(globalCartProvider).valueOrNull?.count ?? 0;
            return Stack(children: [
              IconButton(
                icon: const Icon(Icons.shopping_cart_outlined, size: 22),
                onPressed: () => context.go('/global/cart'),
              ),
              if (count > 0)
                Positioned(
                  right: 6,
                  top: 6,
                  child: Container(
                    padding: const EdgeInsets.all(3),
                    decoration: const BoxDecoration(
                        color: Color(0xFFF59E0B), shape: BoxShape.circle),
                    child: Text('$count',
                        style: const TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.w800,
                            color: Colors.white)),
                  ),
                ),
            ]);
          }),
          const SizedBox(width: 4),
        ],
      ),

      // ── CTA buttons — always visible ─────────────────────────────────────
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
          decoration: BoxDecoration(
            color: Colors.white,
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.08),
                blurRadius: 16,
                offset: const Offset(0, -4),
              ),
            ],
          ),
          child: p.inStock
              ? Row(children: [
                  // Add to Cart
                  Expanded(
                    child: SizedBox(
                      height: 52,
                      child: OutlinedButton.icon(
                        onPressed: _addingToCart
                            ? null
                            : () => _onAddToCart(p),
                        icon: _addingToCart
                            ? const SizedBox(
                                width: 16,
                                height: 16,
                                child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Color(0xFF1A1A2E)))
                            : const Icon(Icons.shopping_cart_outlined,
                                size: 18, color: Color(0xFF1A1A2E)),
                        label: Text(l.addToCart,
                            style: TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: 14,
                                color: Color(0xFF1A1A2E))),
                        style: OutlinedButton.styleFrom(
                          side: const BorderSide(
                              color: Color(0xFF1A1A2E), width: 1.5),
                          shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12)),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  // Buy Now
                  Expanded(
                    child: SizedBox(
                      height: 52,
                      child: ElevatedButton.icon(
                        onPressed:
                            _buyingNow ? null : () => _onBuyNow(p),
                        icon: _buyingNow
                            ? const SizedBox(
                                width: 16,
                                height: 16,
                                child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Color(0xFF1A1A2E)))
                            : const Icon(Icons.bolt_rounded,
                                size: 18, color: Color(0xFF1A1A2E)),
                        label: Text(l.buyNow,
                            style: TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 14,
                                color: Color(0xFF1A1A2E))),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFFF59E0B),
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12)),
                        ),
                      ),
                    ),
                  ),
                ])
              : SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton(
                    onPressed: null,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.grey.shade300,
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12)),
                    ),
                    child: Text(l.outOfStock,
                        style: TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 15,
                            color: Colors.grey)),
                  ),
                ),
        ),
      ),

      // ── Body ─────────────────────────────────────────────────────────────
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ── Image gallery ─────────────────────────────────────────────
            _ImageGallery(images: images),

            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // ── Category + name ──────────────────────────────────────
                  if (p.category != null) ...[
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
                    const SizedBox(height: 8),
                  ],
                  Text(p.name,
                      style: const TextStyle(
                          fontSize: 22, fontWeight: FontWeight.w800,
                          color: Color(0xFF1A1A2E))),
                  const SizedBox(height: 10),

                  // ── Rating ────────────────────────────────────────────────
                  if (p.rating > 0) ...[
                    Row(children: [
                      ...List.generate(5, (i) => Icon(
                          i < p.rating.round()
                              ? Icons.star_rounded
                              : Icons.star_outline_rounded,
                          size: 16,
                          color: const Color(0xFFF59E0B))),
                      const SizedBox(width: 6),
                      Text('${p.rating.toStringAsFixed(1)}',
                          style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 13)),
                      const SizedBox(width: 4),
                      Text('(${p.reviewsCount} reviews)',
                          style: TextStyle(
                              color: Colors.grey.shade500,
                              fontSize: 12)),
                    ]),
                    const SizedBox(height: 12),
                  ],

                  // ── Price block ───────────────────────────────────────────
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF8F9FA),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Row(children: [
                      Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                        Text(p.formattedPrice,
                            style: const TextStyle(
                                fontSize: 30,
                                fontWeight: FontWeight.w900,
                                color: Color(0xFF1A1A2E))),
                        if (p.formattedComparePrice != null)
                          Text(p.formattedComparePrice!,
                              style: TextStyle(
                                  fontSize: 14,
                                  color: Colors.grey.shade400,
                                  decoration: TextDecoration.lineThrough)),
                      ]),
                      const Spacer(),
                      if ((p.discountPct ?? 0) > 0)
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 12, vertical: 6),
                          decoration: BoxDecoration(
                            color: Colors.red.shade600,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text('${p.discountPct ?? 0}% OFF',
                              style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w800,
                                  fontSize: 13)),
                        ),
                    ]),
                  ),
                  const SizedBox(height: 14),

                  // ── Stock status ──────────────────────────────────────────
                  Row(children: [
                    Container(
                      width: 8, height: 8,
                      decoration: BoxDecoration(
                        color: p.inStock
                            ? Colors.green.shade500
                            : Colors.red.shade500,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                        p.inStock
                            ? p.stock != null && p.stock! < 10
                                ? 'Only ${p.stock} left!'
                                : l.inStock
                            : l.outOfStock,
                        style: TextStyle(
                            fontWeight: FontWeight.w600,
                            fontSize: 13,
                            color: p.inStock
                                ? Colors.green.shade700
                                : Colors.red.shade700)),
                  ]),
                  const SizedBox(height: 20),

                  // ── Variants ──────────────────────────────────────────────
                  if (p.variants.isNotEmpty) ...[
                    Text(l.chooseOption,
                        style: TextStyle(
                            fontSize: 14, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8, runSpacing: 8,
                      children: p.variants.map((v) {
                        final label = v is Map
                            ? (v['name'] ?? v.toString())
                            : v.toString();
                        final selected = _selectedVariant == label;
                        return GestureDetector(
                          onTap: () =>
                              setState(() => _selectedVariant = label),
                          child: AnimatedContainer(
                            duration: const Duration(milliseconds: 150),
                            padding: const EdgeInsets.symmetric(
                                horizontal: 16, vertical: 9),
                            decoration: BoxDecoration(
                              color: selected
                                  ? const Color(0xFF1A1A2E)
                                  : Colors.white,
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(
                                  color: selected
                                      ? const Color(0xFF1A1A2E)
                                      : Colors.grey.shade300,
                                  width: selected ? 2 : 1),
                            ),
                            child: Text(label,
                                style: TextStyle(
                                    fontSize: 13,
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

                  // ── Quantity ──────────────────────────────────────────────
                  Row(children: [
                    Text(l.quantity,
                        style: TextStyle(
                            fontSize: 15, fontWeight: FontWeight.w700)),
                    const Spacer(),
                    _QtySelector(
                      qty: _qty,
                      onDec: () { if (_qty > 1) setState(() => _qty--); },
                      onInc: () => setState(() => _qty++),
                    ),
                  ]),

                  const SizedBox(height: 24),
                  const Divider(height: 1, color: Color(0xFFF0F2F5)),
                  const SizedBox(height: 20),

                  // ── Description ───────────────────────────────────────────
                  if (p.description != null &&
                      p.description!.isNotEmpty) ...[
                    Text(l.description,
                        style: TextStyle(
                            fontSize: 15, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 10),
                    Text(p.description!,
                        style: TextStyle(
                            fontSize: 14,
                            color: Colors.grey.shade700,
                            height: 1.65)),
                    const SizedBox(height: 20),
                  ],

                  // ── Reviews ───────────────────────────────────────────────
                  _ReviewsSection(productId: p.id),
                  const SizedBox(height: 20),
                  const Divider(height: 1, color: Color(0xFFF0F2F5)),
                  const SizedBox(height: 20),

                  // ── Shipping note ─────────────────────────────────────────
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF0FDF4),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(children: [
                      const Icon(Icons.local_shipping_outlined,
                          color: Color(0xFF166534), size: 20),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                          const Text('International Shipping',
                              style: TextStyle(
                                  fontWeight: FontWeight.w700,
                                  fontSize: 13,
                                  color: Color(0xFF166534))),
                          Text('Delivered worldwide · Stripe & PayPal',
                              style: TextStyle(
                                  fontSize: 11,
                                  color: Colors.green.shade700)),
                        ]),
                      ),
                    ]),
                  ),

                  // ── Tags ──────────────────────────────────────────────────
                  if (p.tags.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    Wrap(
                      spacing: 6, runSpacing: 6,
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
                  ],

                  const SizedBox(height: 20),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ── Desktop 2-column layout ──────────────────────────────────────────────────

  static const _kNavy   = Color(0xFF1A1A2E);
  static const _kOrange = Color(0xFFF59E0B);
  static const _kBg     = Color(0xFFF0F2F5);

  Widget _buildDesktopScaffold(GlobalProduct p, List<String> images, AppL10n l) {
    return Scaffold(
      backgroundColor: _kBg,
      body: SingleChildScrollView(
        child: Column(
          children: [
            // ── Breadcrumb / back row ────────────────────────────────────────
            Container(
              color: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 40, vertical: 12),
              child: Row(
                children: [
                  TextButton.icon(
                    onPressed: () => context.pop(),
                    icon: const Icon(Icons.arrow_back_ios_new_rounded,
                        size: 14, color: Color(0xFF6B7280)),
                    label: const Text('Back to Products',
                        style: TextStyle(color: Color(0xFF6B7280), fontSize: 13)),
                    style: TextButton.styleFrom(padding: EdgeInsets.zero),
                  ),
                  const SizedBox(width: 8),
                  Icon(Icons.chevron_right, size: 16, color: Colors.grey.shade400),
                  const SizedBox(width: 8),
                  if (p.category != null)
                    Text(p.category!,
                        style: TextStyle(
                            color: Colors.grey.shade500, fontSize: 13)),
                  if (p.category != null) ...[
                    const SizedBox(width: 8),
                    Icon(Icons.chevron_right,
                        size: 16, color: Colors.grey.shade400),
                    const SizedBox(width: 8),
                  ],
                  Flexible(
                    child: Text(p.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            color: _kNavy,
                            fontSize: 13,
                            fontWeight: FontWeight.w600)),
                  ),
                ],
              ),
            ),

            // ── Main product section ─────────────────────────────────────────
            Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 1280),
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 40, vertical: 32),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Left: image gallery (sticky-ish via alignment)
                      Expanded(
                        flex: 5,
                        child: _DesktopImageGallery(
                            images: images, pageCtrl: _pageCtrl),
                      ),
                      const SizedBox(width: 40),
                      // Right: all product info + CTA
                      Expanded(
                        flex: 5,
                        child: _buildDesktopInfo(p, l),
                      ),
                    ],
                  ),
                ),
              ),
            ),

            // ── Reviews (full width) ─────────────────────────────────────────
            Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 1280),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(40, 0, 40, 40),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Divider(height: 1, color: Color(0xFFE5E7EB)),
                      const SizedBox(height: 32),
                      _ReviewsSection(productId: p.id),
                      const SizedBox(height: 40),
                    ],
                  ),
                ),
              ),
            ),

            // ── Footer ────────────────────────────────────────────────────────
            const GlobalDesktopFooter(),
          ],
        ),
      ),
    );
  }

  Widget _buildDesktopInfo(GlobalProduct p, AppL10n l) {
    return Container(
      padding: const EdgeInsets.all(28),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
              color: Colors.black.withValues(alpha: 0.06),
              blurRadius: 20,
              offset: const Offset(0, 4)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Category chip
          if (p.category != null) ...[
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
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
            const SizedBox(height: 12),
          ],

          // Name
          Text(p.name,
              style: const TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.w800,
                  color: _kNavy,
                  height: 1.2)),
          const SizedBox(height: 12),

          // Rating
          if (p.rating > 0) ...[
            Row(children: [
              ...List.generate(5,
                  (i) => Icon(
                      i < p.rating.round()
                          ? Icons.star_rounded
                          : Icons.star_outline_rounded,
                      size: 18,
                      color: _kOrange)),
              const SizedBox(width: 8),
              Text('${p.rating.toStringAsFixed(1)}',
                  style: const TextStyle(
                      fontWeight: FontWeight.w700, fontSize: 14)),
              const SizedBox(width: 6),
              Text('(${p.reviewsCount} reviews)',
                  style: TextStyle(
                      color: Colors.grey.shade500, fontSize: 13)),
            ]),
            const SizedBox(height: 16),
          ],

          // Price block
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFFFFF8ED), Color(0xFFFFF3D6)],
              ),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFF59E0B).withValues(alpha: 0.3)),
            ),
            child: Row(children: [
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(p.formattedPrice,
                    style: const TextStyle(
                        fontSize: 36,
                        fontWeight: FontWeight.w900,
                        color: _kNavy)),
                if (p.formattedComparePrice != null)
                  Text(p.formattedComparePrice!,
                      style: TextStyle(
                          fontSize: 15,
                          color: Colors.grey.shade400,
                          decoration: TextDecoration.lineThrough)),
              ]),
              const Spacer(),
              if ((p.discountPct ?? 0) > 0)
                Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 14, vertical: 8),
                  decoration: BoxDecoration(
                    color: Colors.red.shade600,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text('${p.discountPct ?? 0}% OFF',
                      style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                          fontSize: 15)),
                ),
            ]),
          ),
          const SizedBox(height: 16),

          // Stock status
          Row(children: [
            Container(
              width: 10, height: 10,
              decoration: BoxDecoration(
                color: p.inStock
                    ? Colors.green.shade500
                    : Colors.red.shade500,
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: (p.inStock
                            ? Colors.green
                            : Colors.red)
                        .withValues(alpha: 0.4),
                    blurRadius: 6,
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Text(
                p.inStock
                    ? p.stock != null && p.stock! < 10
                        ? 'Only ${p.stock} left!'
                        : l.inStock
                    : l.outOfStock,
                style: TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                    color: p.inStock
                        ? Colors.green.shade700
                        : Colors.red.shade700)),
          ]),
          const SizedBox(height: 20),

          // Variants
          if (p.variants.isNotEmpty) ...[
            Text(l.chooseOption,
                style: const TextStyle(
                    fontSize: 14, fontWeight: FontWeight.w700)),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8, runSpacing: 8,
              children: p.variants.map((v) {
                final label = v is Map
                    ? (v['name'] ?? v.toString())
                    : v.toString();
                final selected = _selectedVariant == label;
                return GestureDetector(
                  onTap: () => setState(() => _selectedVariant = label),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 150),
                    padding: const EdgeInsets.symmetric(
                        horizontal: 18, vertical: 10),
                    decoration: BoxDecoration(
                      color: selected ? _kNavy : Colors.white,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(
                          color: selected ? _kNavy : Colors.grey.shade300,
                          width: selected ? 2 : 1),
                    ),
                    child: Text(label,
                        style: TextStyle(
                            fontSize: 13,
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

          // Quantity
          Row(children: [
            Text(l.quantity,
                style: const TextStyle(
                    fontSize: 15, fontWeight: FontWeight.w700)),
            const Spacer(),
            _QtySelector(
              qty: _qty,
              onDec: () { if (_qty > 1) setState(() => _qty--); },
              onInc: () => setState(() => _qty++),
            ),
          ]),
          const SizedBox(height: 24),

          // CTA Buttons — inline (no bottomNavigationBar on desktop)
          if (p.inStock) ...[
            Row(children: [
              // Add to Cart
              Expanded(
                child: SizedBox(
                  height: 52,
                  child: OutlinedButton.icon(
                    onPressed: _addingToCart ? null : () => _onAddToCart(p),
                    icon: _addingToCart
                        ? const SizedBox(
                            width: 16, height: 16,
                            child: CircularProgressIndicator(
                                strokeWidth: 2, color: _kNavy))
                        : const Icon(Icons.shopping_cart_outlined,
                            size: 18, color: _kNavy),
                    label: Text(l.addToCart,
                        style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 14,
                            color: _kNavy)),
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: _kNavy, width: 1.5),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              // Buy Now
              Expanded(
                child: SizedBox(
                  height: 52,
                  child: ElevatedButton.icon(
                    onPressed: _buyingNow ? null : () => _onBuyNow(p),
                    icon: _buyingNow
                        ? const SizedBox(
                            width: 16, height: 16,
                            child: CircularProgressIndicator(
                                strokeWidth: 2, color: _kNavy))
                        : const Icon(Icons.bolt_rounded,
                            size: 18, color: _kNavy),
                    label: Text(l.buyNow,
                        style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 14,
                            color: _kNavy)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _kOrange,
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
              ),
            ]),
          ] else ...[
            SizedBox(
              width: double.infinity,
              height: 52,
              child: ElevatedButton(
                onPressed: null,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.grey.shade300,
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12)),
                ),
                child: Text(l.outOfStock,
                    style: TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                        color: Colors.grey)),
              ),
            ),
          ],
          const SizedBox(height: 20),
          const Divider(height: 1, color: Color(0xFFF0F2F5)),
          const SizedBox(height: 16),

          // Description
          if (p.description != null && p.description!.isNotEmpty) ...[
            Text(l.description,
                style: const TextStyle(
                    fontSize: 15, fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            Text(p.description!,
                style: TextStyle(
                    fontSize: 14,
                    color: Colors.grey.shade700,
                    height: 1.65)),
            const SizedBox(height: 20),
          ],

          // Shipping note
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFF0FDF4),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Row(children: [
              const Icon(Icons.local_shipping_outlined,
                  color: Color(0xFF166534), size: 20),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                  const Text('International Shipping',
                      style: TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 13,
                          color: Color(0xFF166534))),
                  Text('Delivered worldwide · Stripe & PayPal',
                      style: TextStyle(
                          fontSize: 11,
                          color: Colors.green.shade700)),
                ]),
              ),
            ]),
          ),

          // Tags
          if (p.tags.isNotEmpty) ...[
            const SizedBox(height: 16),
            Wrap(
              spacing: 6, runSpacing: 6,
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
          ],
          const SizedBox(height: 8),
        ],
      ),
    );
  }
}

// ── Desktop Image Gallery ─────────────────────────────────────────────────────

class _DesktopImageGallery extends StatefulWidget {
  final List<String> images;
  final PageController pageCtrl;
  const _DesktopImageGallery(
      {required this.images, required this.pageCtrl});

  @override
  State<_DesktopImageGallery> createState() => _DesktopImageGalleryState();
}

class _DesktopImageGalleryState extends State<_DesktopImageGallery> {
  int _idx = 0;

  @override
  Widget build(BuildContext context) {
    final images = widget.images;
    if (images.isEmpty) {
      return Container(
        height: 480,
        decoration: BoxDecoration(
          color: Colors.grey.shade100,
          borderRadius: BorderRadius.circular(16),
        ),
        child: const Center(
            child: Icon(Icons.image_outlined, size: 80, color: Colors.grey)),
      );
    }

    return Column(
      children: [
        // Large main image
        Container(
          height: 480,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                  color: Colors.black.withValues(alpha: 0.08),
                  blurRadius: 20,
                  offset: const Offset(0, 4)),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: Stack(
              children: [
                PageView.builder(
                  controller: widget.pageCtrl,
                  onPageChanged: (i) => setState(() => _idx = i),
                  itemCount: images.length,
                  itemBuilder: (_, i) => Image.network(
                    images[i],
                    fit: BoxFit.contain,
                    loadingBuilder: (_, child, progress) => progress == null
                        ? child
                        : Container(
                            color: Colors.grey.shade50,
                            child: const Center(
                                child: CircularProgressIndicator(
                                    color: Color(0xFFF59E0B)))),
                    errorBuilder: (_, __, ___) => Container(
                        color: Colors.grey.shade100,
                        child: const Icon(Icons.image_outlined,
                            size: 60, color: Colors.grey)),
                  ),
                ),
                // Prev / Next arrows
                if (images.length > 1) ...[
                  Positioned(
                    left: 12,
                    top: 0, bottom: 0,
                    child: Center(
                      child: _GalleryArrow(
                        icon: Icons.chevron_left_rounded,
                        onTap: _idx > 0
                            ? () => widget.pageCtrl.previousPage(
                                duration: const Duration(milliseconds: 300),
                                curve: Curves.easeInOut)
                            : null,
                      ),
                    ),
                  ),
                  Positioned(
                    right: 12,
                    top: 0, bottom: 0,
                    child: Center(
                      child: _GalleryArrow(
                        icon: Icons.chevron_right_rounded,
                        onTap: _idx < images.length - 1
                            ? () => widget.pageCtrl.nextPage(
                                duration: const Duration(milliseconds: 300),
                                curve: Curves.easeInOut)
                            : null,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
        // Thumbnail strip
        if (images.length > 1) ...[
          const SizedBox(height: 12),
          SizedBox(
            height: 72,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              itemCount: images.length,
              itemBuilder: (_, i) {
                final sel = _idx == i;
                return GestureDetector(
                  onTap: () => widget.pageCtrl.animateToPage(i,
                      duration: const Duration(milliseconds: 300),
                      curve: Curves.easeInOut),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 150),
                    width: 72, height: 72,
                    margin: const EdgeInsets.only(right: 8),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: sel
                            ? const Color(0xFFF59E0B)
                            : Colors.transparent,
                        width: 2,
                      ),
                      boxShadow: sel
                          ? [
                              BoxShadow(
                                color: const Color(0xFFF59E0B)
                                    .withValues(alpha: 0.35),
                                blurRadius: 8,
                              )
                            ]
                          : null,
                    ),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: Image.network(
                        images[i],
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => Container(
                            color: Colors.grey.shade100,
                            child: const Icon(Icons.image_outlined,
                                size: 24, color: Colors.grey)),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ],
    );
  }
}

class _GalleryArrow extends StatefulWidget {
  final IconData icon;
  final VoidCallback? onTap;
  const _GalleryArrow({required this.icon, this.onTap});

  @override
  State<_GalleryArrow> createState() => _GalleryArrowState();
}

class _GalleryArrowState extends State<_GalleryArrow> {
  bool _hovered = false;

  @override
  Widget build(BuildContext context) {
    return MouseRegion(
      onEnter: (_) => setState(() => _hovered = true),
      onExit: (_) => setState(() => _hovered = false),
      child: GestureDetector(
        onTap: widget.onTap,
        child: AnimatedOpacity(
          duration: const Duration(milliseconds: 150),
          opacity: widget.onTap == null ? 0.3 : (_hovered ? 1.0 : 0.7),
          child: Container(
            width: 40, height: 40,
            decoration: BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(
                    color: Colors.black.withValues(alpha: 0.15),
                    blurRadius: 8,
                    offset: const Offset(0, 2)),
              ],
            ),
            child: Icon(widget.icon, size: 24, color: const Color(0xFF1A1A2E)),
          ),
        ),
      ),
    );
  }
}

// ── Image gallery ─────────────────────────────────────────────────────────────

class _ImageGallery extends StatefulWidget {
  final List<String> images;
  const _ImageGallery({required this.images});

  @override
  State<_ImageGallery> createState() => _ImageGalleryState();
}

class _ImageGalleryState extends State<_ImageGallery> {
  final _ctrl = PageController();
  int _idx = 0;

  @override
  Widget build(BuildContext context) {
    if (widget.images.isEmpty) {
      return Container(
        height: 300,
        color: Colors.grey.shade100,
        child: const Center(
            child: Icon(Icons.image_outlined, size: 60, color: Colors.grey)),
      );
    }

    return Column(children: [
      SizedBox(
        height: 300,
        child: PageView.builder(
          controller: _ctrl,
          onPageChanged: (i) => setState(() => _idx = i),
          itemCount: widget.images.length,
          itemBuilder: (_, i) => Image.network(
            widget.images[i],
            fit: BoxFit.contain,
            loadingBuilder: (_, child, progress) => progress == null
                ? child
                : Container(
                    color: Colors.grey.shade50,
                    child: const Center(child: CircularProgressIndicator())),
            errorBuilder: (_, __, ___) => Container(
                color: Colors.grey.shade100,
                child: const Icon(Icons.image_outlined,
                    size: 60, color: Colors.grey)),
          ),
        ),
      ),
      if (widget.images.length > 1) ...[
        const SizedBox(height: 10),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(
            widget.images.length,
            (i) => GestureDetector(
              onTap: () => _ctrl.animateToPage(i,
                  duration: const Duration(milliseconds: 250),
                  curve: Curves.easeOut),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                margin: const EdgeInsets.symmetric(horizontal: 3),
                width: _idx == i ? 22 : 6,
                height: 6,
                decoration: BoxDecoration(
                  color: _idx == i
                      ? const Color(0xFF1A1A2E)
                      : Colors.grey.shade300,
                  borderRadius: BorderRadius.circular(3),
                ),
              ),
            ),
          ),
        ),
        const SizedBox(height: 8),
      ],
      // Thumbnail strip for multi-image
      if (widget.images.length > 1)
        SizedBox(
          height: 64,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            itemCount: widget.images.length,
            itemBuilder: (_, i) => GestureDetector(
              onTap: () => _ctrl.animateToPage(i,
                  duration: const Duration(milliseconds: 250),
                  curve: Curves.easeOut),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                margin: const EdgeInsets.only(right: 8),
                width: 58,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(
                    color: _idx == i
                        ? const Color(0xFF1A1A2E)
                        : Colors.grey.shade200,
                    width: _idx == i ? 2 : 1,
                  ),
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(7),
                  child: Image.network(widget.images[i],
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => Container(
                          color: Colors.grey.shade100)),
                ),
              ),
            ),
          ),
        ),
    ]);
  }
}

// ── Quantity selector ─────────────────────────────────────────────────────────

class _QtySelector extends StatelessWidget {
  final int qty;
  final VoidCallback onDec;
  final VoidCallback onInc;
  const _QtySelector(
      {required this.qty, required this.onDec, required this.onInc});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFFF0F2F5),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(children: [
        _QBtn(icon: Icons.remove_rounded, onTap: onDec, enabled: qty > 1),
        SizedBox(
          width: 40,
          child: Text('$qty',
              textAlign: TextAlign.center,
              style: const TextStyle(
                  fontSize: 16, fontWeight: FontWeight.w800)),
        ),
        _QBtn(icon: Icons.add_rounded, onTap: onInc, enabled: true),
      ]),
    );
  }
}

class _QBtn extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  final bool enabled;
  const _QBtn(
      {required this.icon, required this.onTap, required this.enabled});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: enabled ? onTap : null,
      child: Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(
          color: enabled ? Colors.white : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
          boxShadow: enabled
              ? [
                  BoxShadow(
                      color: Colors.black.withValues(alpha: 0.06),
                      blurRadius: 4)
                ]
              : null,
        ),
        child: Icon(icon,
            size: 18,
            color: enabled
                ? const Color(0xFF1A1A2E)
                : Colors.grey.shade400),
      ),
    );
  }
}

// ── Reviews Section ───────────────────────────────────────────────────────────

class _ReviewsSection extends ConsumerWidget {
  final int productId;
  const _ReviewsSection({required this.productId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppL10n.of(context);
    final async = ref.watch(globalReviewsProvider(productId));
    final auth = ref.watch(globalAuthProvider).valueOrNull;

    return async.when(
      loading: () => const SizedBox(
        height: 60,
        child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
      ),
      error: (_, __) => const SizedBox.shrink(),
      data: (data) {
        final stats = data.stats;
        final reviews = data.reviews;

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header — review button is on the Order Details page (delivered orders only)
            Text(l.customerReviews,
                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
            const SizedBox(height: 12),

            if (stats.total == 0)
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8F9FA),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Column(children: [
                  Text(l.noReviewsYet,
                      style: TextStyle(
                          fontWeight: FontWeight.w700, fontSize: 14)),
                  const SizedBox(height: 4),
                  Text(l.beFirstToReview,
                      style: TextStyle(
                          color: Colors.grey.shade500, fontSize: 12)),
                ]),
              )
            else ...[
              // Rating summary
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8F9FA),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Row(children: [
                  Column(children: [
                    Text(stats.average.toStringAsFixed(1),
                        style: const TextStyle(
                            fontSize: 36,
                            fontWeight: FontWeight.w900,
                            color: Color(0xFF1A1A2E))),
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: List.generate(5, (i) => Icon(
                          i < stats.average.round()
                              ? Icons.star_rounded
                              : Icons.star_outline_rounded,
                          size: 14,
                          color: const Color(0xFFF59E0B))),
                    ),
                    Text('${stats.total} reviews',
                        style: TextStyle(
                            fontSize: 11, color: Colors.grey.shade500)),
                  ]),
                  const SizedBox(width: 20),
                  Expanded(
                    child: Column(
                      children: [5, 4, 3, 2, 1].map((r) {
                        final count = stats.distribution[r] ?? 0;
                        final pct = stats.total > 0
                            ? count / stats.total
                            : 0.0;
                        return Padding(
                          padding: const EdgeInsets.symmetric(vertical: 2),
                          child: Row(children: [
                            Text('$r',
                                style: const TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w600)),
                            const SizedBox(width: 4),
                            const Icon(Icons.star_rounded,
                                size: 11, color: Color(0xFFF59E0B)),
                            const SizedBox(width: 6),
                            Expanded(
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(4),
                                child: LinearProgressIndicator(
                                  value: pct,
                                  minHeight: 6,
                                  backgroundColor: Colors.grey.shade200,
                                  color: const Color(0xFFF59E0B),
                                ),
                              ),
                            ),
                            const SizedBox(width: 6),
                            SizedBox(
                              width: 20,
                              child: Text('$count',
                                  style: TextStyle(
                                      fontSize: 10,
                                      color: Colors.grey.shade500)),
                            ),
                          ]),
                        );
                      }).toList(),
                    ),
                  ),
                ]),
              ),
              const SizedBox(height: 12),

              // Review list (max 5 shown)
              ...reviews.take(5).map((r) => _ReviewCard(review: r)),
            ],
          ],
        );
      },
    );
  }
}

class _ReviewCard extends StatelessWidget {
  final GlobalReview review;
  const _ReviewCard({required this.review});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF8F9FA),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            // Avatar circle — image if available, else initial
            Container(
              width: 36,
              height: 36,
              decoration: const BoxDecoration(
                color: Color(0xFF1A1A2E),
                shape: BoxShape.circle,
              ),
              clipBehavior: Clip.hardEdge,
              child: review.userAvatar != null
                  ? Image.network(
                      review.userAvatar!,
                      width: 36, height: 36,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => Center(
                        child: Text(
                          review.userName.isNotEmpty
                              ? review.userName[0].toUpperCase()
                              : '?',
                          style: const TextStyle(
                              color: Colors.white, fontSize: 15,
                              fontWeight: FontWeight.w800),
                        ),
                      ),
                    )
                  : Center(
                      child: Text(
                        review.userName.isNotEmpty
                            ? review.userName[0].toUpperCase()
                            : '?',
                        style: const TextStyle(
                            color: Colors.white, fontSize: 15,
                            fontWeight: FontWeight.w800),
                      ),
                    ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(review.userName,
                      style: const TextStyle(
                          fontWeight: FontWeight.w700, fontSize: 13)),
                  Row(children: [
                    ...List.generate(
                        5,
                        (i) => Icon(
                              i < review.rating
                                  ? Icons.star_rounded
                                  : Icons.star_outline_rounded,
                              size: 12,
                              color: const Color(0xFFF59E0B),
                            )),
                    const SizedBox(width: 6),
                    if (review.userCountry != null)
                      Text('  · ${review.userCountry}',
                          style: TextStyle(
                              fontSize: 10,
                              color: Colors.grey.shade500)),
                  ]),
                ],
              ),
            ),
          ]),
          if (review.title != null && review.title!.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(review.title!,
                style: const TextStyle(
                    fontWeight: FontWeight.w700, fontSize: 13)),
          ],
          if (review.body != null && review.body!.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(review.body!,
                style: TextStyle(
                    fontSize: 13,
                    color: Colors.grey.shade700,
                    height: 1.5)),
          ],
          // ── Review images uploaded by the user ──────────────────────────
          if (review.images.isNotEmpty) ...[
            const SizedBox(height: 10),
            SizedBox(
              height: 90,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                itemCount: review.images.length,
                itemBuilder: (ctx, i) => GestureDetector(
                  onTap: () => _showImageFullscreen(ctx, review.images, i),
                  child: Container(
                    margin: const EdgeInsets.only(right: 8),
                    width: 90,
                    height: 90,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: const Color(0xFFE5E7EB)),
                    ),
                    clipBehavior: Clip.hardEdge,
                    child: Image.network(
                      review.images[i],
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => const Center(
                        child: Icon(Icons.image_not_supported_outlined,
                            color: Colors.grey)),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

void _showImageFullscreen(
    BuildContext context, List<String> images, int initialIndex) {
  showDialog(
    context: context,
    builder: (_) => Dialog(
      backgroundColor: Colors.black,
      insetPadding: EdgeInsets.zero,
      child: Stack(
        children: [
          PageView.builder(
            controller: PageController(initialPage: initialIndex),
            itemCount: images.length,
            itemBuilder: (_, i) => InteractiveViewer(
              child: Center(
                child: Image.network(images[i], fit: BoxFit.contain),
              ),
            ),
          ),
          Positioned(
            top: 16, right: 16,
            child: GestureDetector(
              onTap: () => Navigator.of(context).pop(),
              child: Container(
                width: 36, height: 36,
                decoration: BoxDecoration(
                  color: Colors.black54,
                  borderRadius: BorderRadius.circular(18),
                ),
                child: const Icon(Icons.close, color: Colors.white, size: 20),
              ),
            ),
          ),
        ],
      ),
    ),
  );
}
