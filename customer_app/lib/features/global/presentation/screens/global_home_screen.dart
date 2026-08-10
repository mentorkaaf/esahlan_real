import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/global_provider.dart';
import '../../data/models/global_models.dart';
import '../widgets/global_product_card.dart';
import '../widgets/global_coupon_popup.dart';
import '../../../../core/widgets/smart_location_banner.dart';

class GlobalHomeScreen extends ConsumerStatefulWidget {
  const GlobalHomeScreen({super.key});

  @override
  ConsumerState<GlobalHomeScreen> createState() => _GlobalHomeScreenState();
}

class _GlobalHomeScreenState extends ConsumerState<GlobalHomeScreen> {
  final _searchCtrl = TextEditingController();
  bool _couponPopupShown = false;

  @override
  void initState() {
    super.initState();
    // Show coupon popup shortly after page loads
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Future.delayed(const Duration(milliseconds: 1200), () {
        if (mounted && !_couponPopupShown) _checkCouponPopup();
      });
    });
  }

  void _checkCouponPopup() {
    final coupons = ref.read(globalAvailableCouponsProvider).valueOrNull;
    if (coupons != null && coupons.isNotEmpty) {
      setState(() => _couponPopupShown = true);
      showCouponPopup(context, coupons, ref);
    }
  }

  @override
  Widget build(BuildContext context) {
    // Watch coupons so popup triggers if they load after initState
    ref.listen<AsyncValue<List<GlobalCoupon>>>(globalAvailableCouponsProvider,
        (_, next) {
      next.whenData((coupons) {
        if (!_couponPopupShown && coupons.isNotEmpty && mounted) {
          setState(() => _couponPopupShown = true);
          Future.delayed(const Duration(milliseconds: 800), () {
            if (mounted) showCouponPopup(context, coupons, ref); // ignore: use_build_context_synchronously
          });
        }
      });
    });
    final auth = ref.watch(globalAuthProvider);
    final categories = ref.watch(globalCategoriesProvider);
    final featured = ref.watch(globalFeaturedProvider);
    final flash = ref.watch(globalFlashDealsProvider);
    final newArrivals = ref.watch(globalNewArrivalsProvider);
    final bestSellers = ref.watch(globalBestSellersProvider);
    final cart = ref.watch(globalCartProvider);
    final cartCount = cart.valueOrNull?.count ?? 0;

    return Scaffold(
      backgroundColor: const Color(0xFFF0F2F5),
      body: Stack(
        children: [
          CustomScrollView(
        slivers: [
          // App Bar
          SliverAppBar(
            pinned: true,
            expandedHeight: 0,
            backgroundColor: const Color(0xFF1A1A2E),
            leading: Padding(
              padding: const EdgeInsets.all(8),
              child: Image.asset('assets/images/logo.png',
                  errorBuilder: (_, __, ___) => const Icon(Icons.language,
                      color: Colors.white, size: 24)),
            ),
            title: const Text('eSahlan Global',
                style: TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w800)),
            actions: [
              // Search
              IconButton(
                icon: const Icon(Icons.search_rounded, color: Colors.white),
                onPressed: () => context.push('/global/search'),
              ),
              // Cart
              Stack(
                children: [
                  IconButton(
                    icon: const Icon(Icons.shopping_cart_outlined,
                        color: Colors.white),
                    onPressed: () => context.push('/global/cart'),
                  ),
                  if (cartCount > 0)
                    Positioned(
                      right: 6,
                      top: 6,
                      child: Container(
                        padding: const EdgeInsets.all(3),
                        decoration: const BoxDecoration(
                            color: Color(0xFFF59E0B),
                            shape: BoxShape.circle),
                        child: Text('$cartCount',
                            style: const TextStyle(
                                fontSize: 9,
                                fontWeight: FontWeight.w800,
                                color: Colors.white)),
                      ),
                    ),
                ],
              ),
              // Profile
              auth.when(
                data: (u) => u != null
                    ? IconButton(
                        icon: CircleAvatar(
                          radius: 14,
                          backgroundColor: const Color(0xFFF59E0B),
                          child: Text(u.name[0].toUpperCase(),
                              style: const TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w800,
                                  color: Color(0xFF1A1A2E))),
                        ),
                        onPressed: () => context.go('/global/profile'),
                      )
                    : IconButton(
                        icon: const Icon(Icons.person_outline,
                            color: Colors.white),
                        onPressed: () => context.push('/global/auth'),
                      ),
                loading: () => const Padding(
                    padding: EdgeInsets.all(12),
                    child: SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                            color: Colors.white, strokeWidth: 2))),
                error: (_, __) => IconButton(
                    icon: const Icon(Icons.person_outline, color: Colors.white),
                    onPressed: () => context.push('/global/auth')),
              ),
              const SizedBox(width: 4),
            ],
          ),

          SliverToBoxAdapter(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Search bar
                Container(
                  color: const Color(0xFF1A1A2E),
                  padding:
                      const EdgeInsets.fromLTRB(16, 0, 16, 16),
                  child: GestureDetector(
                    onTap: () => context.push('/global/products'),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 14, vertical: 12),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Row(children: [
                        Icon(Icons.search,
                            color: Colors.grey.shade400, size: 20),
                        const SizedBox(width: 8),
                        Text('Search products...',
                            style: TextStyle(
                                color: Colors.grey.shade400, fontSize: 14)),
                      ]),
                    ),
                  ),
                ),

                // Banner carousel — from API
                _ApiSliderCarousel(),

                const SizedBox(height: 20),

                // Categories
                categories.when(
                  data: (cats) => cats.isEmpty
                      ? const SizedBox()
                      : Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _SectionHeader(
                                title: 'Shop by Category',
                                subtitle: 'Browse all departments',
                                onTap: () =>
                                    context.push('/global/products')),
                            SizedBox(
                              height: 110,
                              child: ListView.builder(
                                scrollDirection: Axis.horizontal,
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 16),
                                itemCount: cats.length,
                                itemBuilder: (ctx, i) =>
                                    _CategoryChip(cat: cats[i]),
                              ),
                            ),
                          ],
                        ),
                  loading: () => const SizedBox(height: 110,
                      child: Center(child: CircularProgressIndicator())),
                  error: (_, __) => const SizedBox(),
                ),

                const SizedBox(height: 20),

                // Flash Deals
                flash.when(
                  data: (products) => products.isEmpty
                      ? const SizedBox()
                      : Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _SectionHeader(
                                title: '⚡ Flash Deals',
                                onTap: () => context.push(
                                    '/global/products?sort=flash')),
                            SizedBox(
                              height: 240,
                              child: ListView.builder(
                                scrollDirection: Axis.horizontal,
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 16),
                                itemCount: products.length,
                                itemBuilder: (ctx, i) => Container(
                                  width: 160,
                                  margin: const EdgeInsets.only(right: 12),
                                  child: GlobalProductCard(
                                      product: products[i]),
                                ),
                              ),
                            ),
                          ],
                        ),
                  loading: () => _HorizontalShimmer(),
                  error: (_, __) => const SizedBox(),
                ),

                const SizedBox(height: 20),

                // ── New Arrivals ───────────────────────────────────────────
                newArrivals.when(
                  data: (products) => products.isEmpty
                      ? const SizedBox()
                      : Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _SectionHeader(
                                title: '✨ New Arrivals',
                                subtitle: 'Fresh items just landed',
                                onTap: () => context.push('/global/products')),
                            SizedBox(
                              height: 260,
                              child: ListView.builder(
                                scrollDirection: Axis.horizontal,
                                padding: const EdgeInsets.symmetric(horizontal: 16),
                                itemCount: products.length,
                                itemBuilder: (ctx, i) => Container(
                                  width: 160,
                                  margin: const EdgeInsets.only(right: 12),
                                  child: GlobalProductCard(product: products[i]),
                                ),
                              ),
                            ),
                          ],
                        ),
                  loading: () => _HorizontalShimmer(),
                  error: (_, __) => const SizedBox(),
                ),

                const SizedBox(height: 24),

                // ── Best Sellers ───────────────────────────────────────────
                bestSellers.when(
                  data: (products) => products.isEmpty
                      ? const SizedBox()
                      : Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _SectionHeader(
                                title: '🔥 Best Sellers',
                                subtitle: 'Most purchased by shoppers',
                                onTap: () => context.push('/global/products')),
                            SizedBox(
                              height: 280,
                              child: ListView.builder(
                                scrollDirection: Axis.horizontal,
                                padding: const EdgeInsets.symmetric(horizontal: 16),
                                itemCount: products.length,
                                itemBuilder: (ctx, i) => SizedBox(
                                  width: 160,
                                  child: _BestSellerCard(product: products[i]),
                                ),
                              ),
                            ),
                          ],
                        ),
                  loading: () => _HorizontalShimmer(),
                  error: (_, __) => const SizedBox(),
                ),

                const SizedBox(height: 24),

                // ── Featured ───────────────────────────────────────────────
                featured.when(
                  data: (products) => products.isEmpty
                      ? const SizedBox()
                      : Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _SectionHeader(
                                title: '🌟 Featured Products',
                                subtitle: 'Hand-picked for you',
                                onTap: () => context.push('/global/products')),
                            _ProductGrid(products: products),
                          ],
                        ),
                  loading: () => _HorizontalShimmer(),
                  error: (_, __) => const SizedBox(),
                ),

                const SizedBox(height: 80),
              ],
            ),
          ),
        ],
      ),
          // Smart banner: detects if global user is now in Somalia → suggest Local
          const SmartLocationBanner(isLocalApp: false),
        ],
      ),
    );
  }
}

// ── API-driven slider carousel (3:1 ratio + auto-slide) ──────────────────────
class _ApiSliderCarousel extends ConsumerStatefulWidget {
  const _ApiSliderCarousel();
  @override
  ConsumerState<_ApiSliderCarousel> createState() => _ApiSliderCarouselState();
}

class _ApiSliderCarouselState extends ConsumerState<_ApiSliderCarousel> {
  int _index = 0;
  late final PageController _ctrl;
  Timer? _timer;

  // Fallback sliders shown if API fails / no sliders in DB
  static const _fallback = [
    GlobalSlider(id: 0, title: 'Free Shipping', subtitle: 'On orders over \$50', bgColor: '#1A1A2E', buttonText: 'Shop Now'),
    GlobalSlider(id: 0, title: 'Flash Deals', subtitle: 'Up to 60% off today', bgColor: '#0F3460', buttonText: 'See Deals'),
    GlobalSlider(id: 0, title: 'New Arrivals', subtitle: 'Fresh styles just landed', bgColor: '#16213E', buttonText: 'Explore'),
  ];

  @override
  void initState() {
    super.initState();
    _ctrl = PageController();
    _startAutoSlide();
  }

  void _startAutoSlide() {
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 4), (_) {
      if (!mounted || !_ctrl.hasClients) return;
      final sliders = ref.read(globalSlidersProvider).valueOrNull;
      final count = (sliders != null && sliders.isNotEmpty) ? sliders.length : _fallback.length;
      final next = (_index + 1) % count;
      _ctrl.animateToPage(
        next,
        duration: const Duration(milliseconds: 500),
        curve: Curves.easeInOut,
      );
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final slidersAsync = ref.watch(globalSlidersProvider);
    final sliders = slidersAsync.valueOrNull?.isNotEmpty == true
        ? slidersAsync.value!
        : _fallback;

    return Column(children: [
      // 3:1 aspect ratio
      AspectRatio(
        aspectRatio: 3 / 1,
        child: PageView.builder(
          controller: _ctrl,
          onPageChanged: (i) => setState(() => _index = i),
          itemCount: sliders.length,
          itemBuilder: (_, i) => _SliderCard(slider: sliders[i]),
        ),
      ),
      const SizedBox(height: 8),
      Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: List.generate(sliders.length, (i) => AnimatedContainer(
          duration: const Duration(milliseconds: 250),
          margin: const EdgeInsets.symmetric(horizontal: 3),
          width: _index == i ? 20 : 6,
          height: 6,
          decoration: BoxDecoration(
            color: _index == i ? const Color(0xFFF59E0B) : Colors.grey.shade300,
            borderRadius: BorderRadius.circular(3),
          ),
        )),
      ),
    ]);
  }
}

class _SliderCard extends StatelessWidget {
  final GlobalSlider slider;
  const _SliderCard({required this.slider});

  @override
  Widget build(BuildContext context) {
    final hasImage = slider.imageUrl != null && slider.imageUrl!.isNotEmpty;

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      decoration: BoxDecoration(
        color: Color(slider.colorValue),
        borderRadius: BorderRadius.circular(16),
        image: hasImage
            ? DecorationImage(
                image: NetworkImage(slider.imageUrl!),
                fit: BoxFit.cover,
                colorFilter: ColorFilter.mode(
                  Colors.black.withValues(alpha: 0.35),
                  BlendMode.darken,
                ),
              )
            : null,
      ),
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(slider.title,
                    style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        height: 1.1),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis),
                if (slider.subtitle != null && slider.subtitle!.isNotEmpty) ...[
                  const SizedBox(height: 5),
                  Text(slider.subtitle!,
                      style: const TextStyle(color: Colors.white70, fontSize: 12),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis),
                ],
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF59E0B),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(slider.buttonText,
                      style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF1A1A2E))),
                ),
              ],
            ),
          ),
          if (!hasImage)
            const Text('🛍', style: TextStyle(fontSize: 48)),
        ],
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  final String title;
  final String? subtitle;
  final VoidCallback? onTap;
  const _SectionHeader({required this.title, this.subtitle, this.onTap});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title,
                style: const TextStyle(
                    fontSize: 17, fontWeight: FontWeight.w800,
                    color: Color(0xFF111827))),
            if (subtitle != null)
              Text(subtitle!,
                  style: const TextStyle(
                      fontSize: 12, color: Color(0xFF6B7280))),
          ]),
        ),
        if (onTap != null)
          GestureDetector(
            onTap: onTap,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
              decoration: BoxDecoration(
                color: const Color(0xFFF59E0B).withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(20),
              ),
              child: const Text('See all →',
                  style: TextStyle(
                      fontSize: 11,
                      color: Color(0xFFD97706),
                      fontWeight: FontWeight.w700)),
            ),
          ),
      ]),
    );
  }
}

// Best Seller card — shows sold count badge
class _BestSellerCard extends StatelessWidget {
  final GlobalProduct product;
  const _BestSellerCard({required this.product});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => context.push('/global/product/${product.id}'),
      child: Container(
        margin: const EdgeInsets.only(right: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.06),
              blurRadius: 8, offset: const Offset(0, 2)),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Image with 🔥 badge
            Stack(
              children: [
                ClipRRect(
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                  child: product.thumbnail != null
                      ? Image.network(product.thumbnail!,
                            height: 150, width: double.infinity,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) => Container(
                                height: 150,
                                color: const Color(0xFFF3F4F6),
                                child: const Icon(Icons.image_outlined,
                                    color: Colors.grey)))
                      : Container(height: 150, color: const Color(0xFFF3F4F6),
                            child: const Icon(Icons.image_outlined, color: Colors.grey)),
                ),
                // Sold count / bestseller badge — always show for bestsellers
                if (product.isBestseller || product.soldCount > 0)
                  Positioned(
                    bottom: 8, left: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                      decoration: BoxDecoration(
                        color: const Color(0xFF1A1A2E),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Row(children: [
                        const Icon(Icons.local_fire_department_rounded,
                            color: Color(0xFFF59E0B), size: 11),
                        const SizedBox(width: 3),
                        Text(product.soldCount > 0
                                ? '${product.soldCount} sold'
                                : 'Best Seller',
                            style: const TextStyle(
                                color: Colors.white, fontSize: 10,
                                fontWeight: FontWeight.w700)),
                      ]),
                    ),
                  ),
                // Discount badge
                if (product.discountPct != null && product.discountPct! > 0)
                  Positioned(
                    top: 8, right: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                      decoration: BoxDecoration(
                        color: const Color(0xFFEF4444),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text('-${product.discountPct}%',
                          style: const TextStyle(
                              color: Colors.white, fontSize: 10,
                              fontWeight: FontWeight.w800)),
                    ),
                  ),
              ],
            ),
            // Info
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 8, 10, 10),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(product.name,
                    maxLines: 2, overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        fontSize: 12, fontWeight: FontWeight.w600,
                        color: Color(0xFF111827))),
                const SizedBox(height: 6),
                Row(children: [
                  Text('\$${product.price.toStringAsFixed(2)}',
                      style: const TextStyle(
                          fontSize: 14, fontWeight: FontWeight.w800,
                          color: Color(0xFF1A1A2E))),
                  if (product.comparePrice != null) ...[
                    const SizedBox(width: 6),
                    Text('\$${product.comparePrice!.toStringAsFixed(2)}',
                        style: const TextStyle(
                            fontSize: 11, color: Color(0xFF9CA3AF),
                            decoration: TextDecoration.lineThrough)),
                  ],
                ]),
                if (product.rating > 0) ...[
                  const SizedBox(height: 4),
                  Row(children: [
                    const Icon(Icons.star_rounded,
                        color: Color(0xFFF59E0B), size: 12),
                    const SizedBox(width: 2),
                    Text(product.rating.toStringAsFixed(1),
                        style: const TextStyle(
                            fontSize: 11, fontWeight: FontWeight.w600,
                            color: Color(0xFF374151))),
                  ]),
                ],
              ]),
            ),
          ],
        ),
      ),
    );
  }
}

class _CategoryChip extends ConsumerWidget {
  final GlobalCategory cat;
  const _CategoryChip({required this.cat});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final colors = [
      0xFFEDE9FE, 0xFFFCE7F3, 0xFFECFDF5, 0xFFFFF7ED,
      0xFFEFF6FF, 0xFFFFF1F2,
    ];
    final color = colors[cat.id % colors.length];

    return GestureDetector(
      onTap: () => context.push('/global/products?category_id=${cat.id}'),
      child: Container(
        margin: const EdgeInsets.only(right: 12),
        width: 80,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 68,
              height: 68,
              decoration: BoxDecoration(
                color: Color(color),
                borderRadius: BorderRadius.circular(18),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.08),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(18),
                child: cat.image != null
                    ? Image.network(
                        cat.image!,
                        width: 68, height: 68,
                        fit: BoxFit.cover,
                        alignment: Alignment.center,
                        errorBuilder: (_, __, ___) => Container(
                          width: 68, height: 68,
                          color: Color(color),
                          child: Center(
                            child: cat.icon != null
                                ? Text(cat.icon!, style: const TextStyle(fontSize: 28))
                                : const Icon(Icons.category, color: Colors.grey, size: 28),
                          ),
                        ),
                      )
                    : Container(
                        width: 68, height: 68,
                        color: Color(color),
                        child: Center(
                          child: cat.icon != null
                              ? Text(cat.icon!, style: const TextStyle(fontSize: 28))
                              : const Icon(Icons.category, color: Colors.grey, size: 28),
                        ),
                      ),
              ),
            ),
            const SizedBox(height: 7),
            Text(cat.name,
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                    fontSize: 10, fontWeight: FontWeight.w700,
                    color: Color(0xFF1F2937))),
          ],
        ),
      ),
    );
  }
}

class _ProductGrid extends StatelessWidget {
  final List<GlobalProduct> products;
  const _ProductGrid({required this.products});

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 0.72,
      ),
      itemCount: products.length,
      itemBuilder: (ctx, i) => GlobalProductCard(product: products[i]),
    );
  }
}

class _HorizontalShimmer extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 240,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: 4,
        itemBuilder: (_, __) => Container(
          width: 160,
          margin: const EdgeInsets.only(right: 12),
          decoration: BoxDecoration(
              color: Colors.grey.shade200,
              borderRadius: BorderRadius.circular(12)),
        ),
      ),
    );
  }
}
