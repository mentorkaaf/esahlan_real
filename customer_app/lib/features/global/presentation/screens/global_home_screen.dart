import 'dart:async';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/global_provider.dart';
import '../../data/models/global_models.dart';
import '../widgets/global_product_card.dart';
import '../widgets/global_coupon_popup.dart';
import '../../../../core/l10n/app_strings.dart';
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
    final auth       = ref.watch(globalAuthProvider);
    final categories = ref.watch(globalCategoriesProvider);
    final featured   = ref.watch(globalFeaturedProvider);
    final flash      = ref.watch(globalFlashDealsProvider);
    final newArrivals  = ref.watch(globalNewArrivalsProvider);
    final bestSellers  = ref.watch(globalBestSellersProvider);
    final cart         = ref.watch(globalCartProvider);
    final cartCount    = cart.valueOrNull?.count ?? 0;
    final width        = MediaQuery.of(context).size.width;
    final isDesktop    = kIsWeb && width >= 1024;

    if (isDesktop) {
      return const _DesktopHomeLayout();
    }

    // ── Mobile Layout ────────────────────────────────────────────────────────
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
                        color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800)),
                actions: [
                  IconButton(
                    icon: const Icon(Icons.search_rounded, color: Colors.white),
                    onPressed: () => context.push('/global/search'),
                  ),
                  Stack(children: [
                    IconButton(
                      icon: const Icon(Icons.shopping_cart_outlined, color: Colors.white),
                      onPressed: () => context.push('/global/cart'),
                    ),
                    if (cartCount > 0)
                      Positioned(
                        right: 6, top: 6,
                        child: Container(
                          padding: const EdgeInsets.all(3),
                          decoration: const BoxDecoration(
                              color: Color(0xFFF59E0B), shape: BoxShape.circle),
                          child: Text('$cartCount', style: const TextStyle(
                              fontSize: 9, fontWeight: FontWeight.w800, color: Colors.white)),
                        ),
                      ),
                  ]),
                  auth.when(
                    data: (u) => u != null
                        ? IconButton(
                            icon: CircleAvatar(
                              radius: 14,
                              backgroundColor: const Color(0xFFF59E0B),
                              child: Text(u.name[0].toUpperCase(),
                                  style: const TextStyle(
                                      fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFF1A1A2E))),
                            ),
                            onPressed: () => context.go('/global/profile'))
                        : IconButton(
                            icon: const Icon(Icons.person_outline, color: Colors.white),
                            onPressed: () => context.push('/global/auth')),
                    loading: () => const Padding(
                        padding: EdgeInsets.all(12),
                        child: SizedBox(width: 20, height: 20,
                            child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))),
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
                    Container(
                      color: const Color(0xFF1A1A2E),
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                      child: GestureDetector(
                        onTap: () => context.push('/global/products'),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
                          child: Row(children: [
                            Icon(Icons.search, color: Colors.grey.shade400, size: 20),
                            const SizedBox(width: 8),
                            Text('Search products...', style: TextStyle(color: Colors.grey.shade400, fontSize: 14)),
                          ]),
                        ),
                      ),
                    ),
                    _ApiSliderCarousel(),
                    const SizedBox(height: 20),
                    categories.when(
                      data: (cats) => cats.isEmpty ? const SizedBox() : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _SectionHeader(title: AppL10n.of(context).categories,
                              subtitle: AppL10n.of(context).tr('contentPrefs'),
                              onTap: () => context.push('/global/products')),
                          SizedBox(
                            height: 110,
                            child: ListView.builder(
                              scrollDirection: Axis.horizontal,
                              padding: const EdgeInsets.symmetric(horizontal: 16),
                              itemCount: cats.length,
                              itemBuilder: (ctx, i) => _CategoryChip(cat: cats[i]),
                            ),
                          ),
                        ],
                      ),
                      loading: () => const SizedBox(height: 110, child: Center(child: CircularProgressIndicator())),
                      error: (_, __) => const SizedBox(),
                    ),
                    const SizedBox(height: 20),
                    flash.when(
                      data: (products) => products.isEmpty ? const SizedBox() : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _SectionHeader(title: '⚡ ${AppL10n.of(context).flashDeals}',
                              onTap: () => context.push('/global/products?sort=flash')),
                          SizedBox(
                            height: 240,
                            child: ListView.builder(
                              scrollDirection: Axis.horizontal,
                              padding: const EdgeInsets.symmetric(horizontal: 16),
                              itemCount: products.length,
                              itemBuilder: (ctx, i) => Container(
                                width: 160, margin: const EdgeInsets.only(right: 12),
                                child: GlobalProductCard(product: products[i]),
                              ),
                            ),
                          ),
                        ],
                      ),
                      loading: () => _HorizontalShimmer(),
                      error: (_, __) => const SizedBox(),
                    ),
                    const SizedBox(height: 20),
                    newArrivals.when(
                      data: (products) => products.isEmpty ? const SizedBox() : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _SectionHeader(title: '✨ ${AppL10n.of(context).newArrivals}',
                              subtitle: 'Fresh items just landed',
                              onTap: () => context.push('/global/products')),
                          SizedBox(
                            height: 260,
                            child: ListView.builder(
                              scrollDirection: Axis.horizontal,
                              padding: const EdgeInsets.symmetric(horizontal: 16),
                              itemCount: products.length,
                              itemBuilder: (ctx, i) => Container(
                                width: 160, margin: const EdgeInsets.only(right: 12),
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
                    bestSellers.when(
                      data: (products) => products.isEmpty ? const SizedBox() : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _SectionHeader(title: '🔥 ${AppL10n.of(context).bestSellers}',
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
                    featured.when(
                      data: (products) => products.isEmpty ? const SizedBox() : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _SectionHeader(title: '🌟 ${AppL10n.of(context).featured}',
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
          const SmartLocationBanner(isLocalApp: false),
        ],
      ),
    );
  }
}

// ── Desktop Home Layout ───────────────────────────────────────────────────────

// _DesktopHomeLayout watches its OWN providers (no constructor params for async data)
// — avoids stale snapshot issue when parent passes AsyncValue as constructor arg.
// Also no nested Scaffold (shell already provides one).
class _DesktopHomeLayout extends ConsumerWidget {
  const _DesktopHomeLayout();

  static const _kBg = Color(0xFFF0F2F5);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l           = AppL10n.of(context);
    final categories  = ref.watch(globalCategoriesProvider);
    final featured    = ref.watch(globalFeaturedProvider);
    final flash       = ref.watch(globalFlashDealsProvider);
    final newArrivals = ref.watch(globalNewArrivalsProvider);
    final bestSellers = ref.watch(globalBestSellersProvider);

    return Container(
      color: _kBg,
      child: SingleChildScrollView(
        child: Column(
          children: [
            // ── Hero Banner ─────────────────────────────────────────────────
            const _DesktopBannerSection(),

            // ── Max-width content wrapper ────────────────────────────────────
            Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 1280),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 40),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SizedBox(height: 40),

                      // ── Categories ────────────────────────────────────────
                      categories.when(
                        data: (cats) => cats.isEmpty ? const SizedBox() : Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _DesktopSectionHeader(
                              title: l.categories,
                              subtitle: 'Browse by category',
                              onTap: () => context.push('/global/products'),
                            ),
                            const SizedBox(height: 16),
                            _DesktopCategoryGrid(cats: cats),
                          ],
                        ),
                        loading: () => const SizedBox(height: 140,
                            child: Center(child: CircularProgressIndicator(color: Color(0xFFF59E0B)))),
                        error: (_, __) => const SizedBox(),
                      ),

                      const SizedBox(height: 48),

                      // ── New Arrivals ──────────────────────────────────────
                      newArrivals.when(
                        data: (products) => products.isEmpty ? const SizedBox() : Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _DesktopSectionHeader(
                              title: '✨ ${l.newArrivals}',
                              subtitle: 'Fresh items just landed',
                              onTap: () => context.push('/global/products'),
                              badge: 'NEW',
                            ),
                            const SizedBox(height: 16),
                            _DesktopProductGrid4(products: products.take(8).toList()),
                          ],
                        ),
                        loading: () => _DesktopSectionShimmer(),
                        error: (e, _) => Center(child: Text('Error: $e')),
                      ),

                      const SizedBox(height: 48),

                      // ── Best Sellers ──────────────────────────────────────
                      bestSellers.when(
                        data: (products) => products.isEmpty ? const SizedBox() : Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _DesktopSectionHeader(
                              title: '🔥 ${l.bestSellers}',
                              subtitle: 'Most purchased by shoppers',
                              onTap: () => context.push('/global/products'),
                            ),
                            const SizedBox(height: 16),
                            _DesktopProductGrid4(products: products.take(8).toList()),
                          ],
                        ),
                        loading: () => _DesktopSectionShimmer(),
                        error: (e, _) => Center(child: Text('Error: $e')),
                      ),

                      const SizedBox(height: 48),

                      // ── Flash Deals ───────────────────────────────────────
                      flash.when(
                        data: (products) => products.isEmpty ? const SizedBox() : Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _DesktopSectionHeader(
                              title: '⚡ ${l.flashDeals}',
                              subtitle: 'Limited time offers',
                              onTap: () => context.push('/global/products'),
                              badge: 'SALE',
                            ),
                            const SizedBox(height: 16),
                            _DesktopProductGrid4(products: products.take(8).toList()),
                          ],
                        ),
                        loading: () => _DesktopSectionShimmer(),
                        error: (_, __) => const SizedBox(),
                      ),

                      const SizedBox(height: 48),

                      // ── Featured ──────────────────────────────────────────
                      featured.when(
                        data: (products) => products.isEmpty ? const SizedBox() : Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _DesktopSectionHeader(
                              title: '🌟 ${l.featured}',
                              subtitle: 'Hand-picked for you',
                              onTap: () => context.push('/global/products'),
                            ),
                            const SizedBox(height: 16),
                            _DesktopProductGrid4(products: products.take(8).toList()),
                          ],
                        ),
                        loading: () => _DesktopSectionShimmer(),
                        error: (e, _) => Center(child: Text('Error: $e')),
                      ),

                      const SizedBox(height: 64),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ── Desktop Hero Banner ───────────────────────────────────────────────────────

class _DesktopBannerSection extends ConsumerStatefulWidget {
  const _DesktopBannerSection();

  @override
  ConsumerState<_DesktopBannerSection> createState() => _DesktopBannerSectionState();
}

class _DesktopBannerSectionState extends ConsumerState<_DesktopBannerSection> {
  int _index = 0;
  late final PageController _ctrl;
  Timer? _timer;

  static const _fallback = [
    GlobalSlider(id: 0, title: 'Free Shipping', subtitle: 'On all orders over \$50 worldwide', bgColor: '#1A1A2E', buttonText: 'Shop Now'),
    GlobalSlider(id: 0, title: 'Flash Deals', subtitle: 'Up to 60% off — today only', bgColor: '#0F3460', buttonText: 'See Deals'),
    GlobalSlider(id: 0, title: 'New Arrivals', subtitle: 'Fresh styles just landed', bgColor: '#16213E', buttonText: 'Explore'),
  ];

  @override
  void initState() {
    super.initState();
    _ctrl = PageController();
    _timer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (!mounted || !_ctrl.hasClients) return;
      final count = ref.read(globalSlidersProvider).valueOrNull?.length ?? _fallback.length;
      _ctrl.animateToPage(
        (_index + 1) % count,
        duration: const Duration(milliseconds: 600),
        curve: Curves.easeInOut,
      );
    });
  }

  @override
  void dispose() { _timer?.cancel(); _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final slidersAsync = ref.watch(globalSlidersProvider);
    final sliders = slidersAsync.valueOrNull?.isNotEmpty == true
        ? slidersAsync.value!
        : _fallback;

    return SizedBox(
      height: 400,
      child: Stack(
        children: [
          PageView.builder(
            controller: _ctrl,
            onPageChanged: (i) => setState(() => _index = i),
            itemCount: sliders.length,
            itemBuilder: (_, i) => _DesktopSliderCard(slider: sliders[i]),
          ),
          // Dot indicators
          Positioned(
            bottom: 20, left: 0, right: 0,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(sliders.length, (i) => GestureDetector(
                onTap: () => _ctrl.animateToPage(i,
                    duration: const Duration(milliseconds: 400), curve: Curves.easeInOut),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 250),
                  margin: const EdgeInsets.symmetric(horizontal: 4),
                  width: _index == i ? 28 : 8,
                  height: 8,
                  decoration: BoxDecoration(
                    color: _index == i ? const Color(0xFFF59E0B) : Colors.white.withValues(alpha: 0.4),
                    borderRadius: BorderRadius.circular(4),
                  ),
                ),
              )),
            ),
          ),
          // Left/right arrows
          Positioned(
            left: 20, top: 0, bottom: 0,
            child: Center(child: _ArrowButton(
              icon: Icons.chevron_left_rounded,
              onTap: () => _ctrl.previousPage(duration: const Duration(milliseconds: 400), curve: Curves.easeInOut),
            )),
          ),
          Positioned(
            right: 20, top: 0, bottom: 0,
            child: Center(child: _ArrowButton(
              icon: Icons.chevron_right_rounded,
              onTap: () => _ctrl.nextPage(duration: const Duration(milliseconds: 400), curve: Curves.easeInOut),
            )),
          ),
        ],
      ),
    );
  }
}

class _ArrowButton extends StatefulWidget {
  final IconData icon;
  final VoidCallback onTap;
  const _ArrowButton({required this.icon, required this.onTap});

  @override
  State<_ArrowButton> createState() => _ArrowButtonState();
}

class _ArrowButtonState extends State<_ArrowButton> {
  bool _hover = false;

  @override
  Widget build(BuildContext context) {
    return MouseRegion(
      onEnter: (_) => setState(() => _hover = true),
      onExit: (_)  => setState(() => _hover = false),
      child: GestureDetector(
        onTap: widget.onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 150),
          width: 44, height: 44,
          decoration: BoxDecoration(
            color: _hover ? const Color(0xFFF59E0B) : Colors.white.withValues(alpha: 0.15),
            shape: BoxShape.circle,
            border: Border.all(color: Colors.white.withValues(alpha: 0.3), width: 1),
          ),
          child: Icon(widget.icon,
            color: _hover ? const Color(0xFF1A1A2E) : Colors.white, size: 24),
        ),
      ),
    );
  }
}

class _DesktopSliderCard extends StatelessWidget {
  final GlobalSlider slider;
  const _DesktopSliderCard({required this.slider});

  @override
  Widget build(BuildContext context) {
    final bgColor = Color(slider.colorValue);
    return Container(
      decoration: BoxDecoration(
        color: bgColor,
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [bgColor, Color.lerp(bgColor, Colors.black, 0.3) ?? bgColor],
        ),
      ),
      child: Stack(
        children: [
          // Background image
          if (slider.imageUrl != null)
            Positioned.fill(
              child: Image.network(
                slider.imageUrl!,
                fit: BoxFit.cover,
                color: Colors.black.withValues(alpha: 0.35),
                colorBlendMode: BlendMode.darken,
                errorBuilder: (_, __, ___) => const SizedBox(),
              ),
            ),
          // Content
          Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 1280),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 80),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF59E0B),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: const Text('eSahlan Global', style: TextStyle(
                        color: Color(0xFF1A1A2E), fontSize: 11,
                        fontWeight: FontWeight.w800, letterSpacing: 1,
                      )),
                    ),
                    const SizedBox(height: 16),
                    Text(slider.title, style: const TextStyle(
                      color: Colors.white, fontSize: 44,
                      fontWeight: FontWeight.w900, height: 1.1,
                      letterSpacing: -1,
                    )),
                    if (slider.subtitle != null) ...[
                      const SizedBox(height: 10),
                      Text(slider.subtitle!, style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.75),
                        fontSize: 18, height: 1.4,
                      )),
                    ],
                    const SizedBox(height: 28),
                    GestureDetector(
                      onTap: () => context.go('/global/products'),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 14),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF59E0B),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(slider.buttonText, style: const TextStyle(
                              color: Color(0xFF1A1A2E), fontSize: 15,
                              fontWeight: FontWeight.w800,
                            )),
                            const SizedBox(width: 8),
                            const Icon(Icons.arrow_forward_rounded,
                              color: Color(0xFF1A1A2E), size: 18),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ── Desktop Section Header ─────────────────────────────────────────────────────

class _DesktopSectionHeader extends StatelessWidget {
  final String title;
  final String? subtitle;
  final String? badge;
  final VoidCallback? onTap;

  const _DesktopSectionHeader({
    required this.title,
    this.subtitle,
    this.badge,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(children: [
              Text(title, style: const TextStyle(
                fontSize: 22, fontWeight: FontWeight.w800,
                color: Color(0xFF1A1A2E), letterSpacing: -0.3,
              )),
              if (badge != null) ...[
                const SizedBox(width: 10),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF59E0B),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Text(badge!, style: const TextStyle(
                    color: Color(0xFF1A1A2E), fontSize: 10,
                    fontWeight: FontWeight.w800, letterSpacing: 1,
                  )),
                ),
              ],
            ]),
            if (subtitle != null)
              Padding(
                padding: const EdgeInsets.only(top: 3),
                child: Text(subtitle!, style: TextStyle(
                  fontSize: 14, color: Colors.grey.shade500,
                )),
              ),
          ],
        ),
        const Spacer(),
        if (onTap != null)
          GestureDetector(
            onTap: onTap,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              decoration: BoxDecoration(
                border: Border.all(color: const Color(0xFF1A1A2E).withValues(alpha: 0.2), width: 1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Row(children: [
                Text('View all', style: TextStyle(
                  fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF1A1A2E),
                )),
                SizedBox(width: 4),
                Icon(Icons.arrow_forward_rounded, size: 14, color: Color(0xFF1A1A2E)),
              ]),
            ),
          ),
      ],
    );
  }
}

// ── Desktop Category Grid ─────────────────────────────────────────────────────

class _DesktopCategoryGrid extends StatelessWidget {
  final List<GlobalCategory> cats;
  const _DesktopCategoryGrid({required this.cats});

  @override
  Widget build(BuildContext context) {
    final display = cats.take(8).toList();
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 8,
        crossAxisSpacing: 12,
        mainAxisSpacing: 0,
        childAspectRatio: 0.75,
      ),
      itemCount: display.length,
      itemBuilder: (ctx, i) => _DesktopCategoryItem(cat: display[i]),
    );
  }
}

class _DesktopCategoryItem extends StatefulWidget {
  final GlobalCategory cat;
  const _DesktopCategoryItem({required this.cat});

  @override
  State<_DesktopCategoryItem> createState() => _DesktopCategoryItemState();
}

class _DesktopCategoryItemState extends State<_DesktopCategoryItem> {
  bool _hover = false;

  @override
  Widget build(BuildContext context) {
    return MouseRegion(
      onEnter: (_) => setState(() => _hover = true),
      onExit: (_)  => setState(() => _hover = false),
      child: GestureDetector(
        onTap: () => context.push('/global/products'),
        child: Column(
          children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 150),
              width: 80, height: 80,
              decoration: BoxDecoration(
                color: _hover ? const Color(0xFFF59E0B).withValues(alpha: 0.1) : Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: _hover ? const Color(0xFFF59E0B) : Colors.grey.shade200,
                  width: 1.5,
                ),
                boxShadow: _hover ? [BoxShadow(
                  color: const Color(0xFFF59E0B).withValues(alpha: 0.2),
                  blurRadius: 12, offset: const Offset(0, 4),
                )] : [BoxShadow(
                  color: Colors.black.withValues(alpha: 0.05),
                  blurRadius: 8,
                )],
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(14),
                child: widget.cat.image != null
                  ? Image.network(widget.cat.image!, fit: BoxFit.cover, width: 80, height: 80,
                      errorBuilder: (_, __, ___) => _catFallback())
                  : _catFallback(),
              ),
            ),
            const SizedBox(height: 8),
            Text(widget.cat.name,
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 12,
                fontWeight: _hover ? FontWeight.w700 : FontWeight.w500,
                color: _hover ? const Color(0xFF1A1A2E) : Colors.grey.shade700,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _catFallback() => Center(child: Icon(Icons.category_outlined,
    size: 32, color: Colors.grey.shade400));
}

// ── Desktop Product Row (horizontal scroll on desktop) ────────────────────────

// ── Desktop Product Card (fixed height — no Expanded dependency) ──────────────

class _DesktopProductCard extends StatefulWidget {
  final GlobalProduct product;
  const _DesktopProductCard({required this.product});

  @override
  State<_DesktopProductCard> createState() => _DesktopProductCardState();
}

class _DesktopProductCardState extends State<_DesktopProductCard> {
  bool _hover = false;

  @override
  Widget build(BuildContext context) {
    final p = widget.product;
    return MouseRegion(
      onEnter: (_) => setState(() => _hover = true),
      onExit: (_)  => setState(() => _hover = false),
      child: GestureDetector(
        onTap: () => context.push('/global/product/${p.id}'),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 150),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            boxShadow: _hover
              ? [BoxShadow(color: Colors.black.withValues(alpha: 0.12), blurRadius: 16, offset: const Offset(0, 6))]
              : [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8, offset: const Offset(0, 2))],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Image — fixed height 200px
              SizedBox(
                height: 200,
                child: Stack(
                  children: [
                    ClipRRect(
                      borderRadius: const BorderRadius.vertical(top: Radius.circular(12)),
                      child: p.thumbnail != null
                        ? Image.network(p.thumbnail!,
                            width: double.infinity, height: 200,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) => _DesktopImgPlaceholder())
                        : _DesktopImgPlaceholder(),
                    ),
                    if (p.discountPct != null && p.discountPct! > 0)
                      Positioned(
                        top: 10, left: 10,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: Colors.red.shade600,
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text('-${p.discountPct}%', style: const TextStyle(
                            color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800,
                          )),
                        ),
                      ),
                    if (p.isBestseller)
                      Positioned(
                        top: 10, right: 10,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF59E0B),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: const Text('BEST', style: TextStyle(
                            color: Color(0xFF1A1A2E), fontSize: 10, fontWeight: FontWeight.w800,
                          )),
                        ),
                      ),
                  ],
                ),
              ),
              // Info
              Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(p.name,
                      maxLines: 2, overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, height: 1.3)),
                    const SizedBox(height: 6),
                    if (p.rating > 0)
                      Row(children: [
                        const Icon(Icons.star_rounded, size: 14, color: Color(0xFFF59E0B)),
                        const SizedBox(width: 3),
                        Text(p.rating.toStringAsFixed(1),
                          style: const TextStyle(fontSize: 12, color: Colors.grey)),
                        const SizedBox(width: 4),
                        Text('(${p.reviewsCount})',
                          style: const TextStyle(fontSize: 11, color: Colors.grey)),
                      ]),
                    const SizedBox(height: 8),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(p.formattedPrice, style: const TextStyle(
                          fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF1A1A2E),
                        )),
                        if (p.formattedComparePrice != null) ...[
                          const SizedBox(width: 6),
                          Text(p.formattedComparePrice!, style: TextStyle(
                            fontSize: 12, color: Colors.grey.shade400,
                            decoration: TextDecoration.lineThrough,
                          )),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DesktopImgPlaceholder extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity, height: 200,
    color: Colors.grey.shade100,
    child: Icon(Icons.image_outlined, color: Colors.grey.shade400, size: 48),
  );
}

// ── Desktop Product Grid (4 columns, fixed card height) ───────────────────────

class _DesktopProductGrid4 extends StatelessWidget {
  final List<GlobalProduct> products;
  const _DesktopProductGrid4({required this.products});

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (ctx, constraints) {
        final cardWidth = (constraints.maxWidth - 16 * 3) / 4;
        return Wrap(
          spacing: 16,
          runSpacing: 16,
          children: products.map((p) => SizedBox(
            width: cardWidth,
            child: _DesktopProductCard(product: p),
          )).toList(),
        );
      },
    );
  }
}

// ── Desktop Section Shimmer ───────────────────────────────────────────────────

class _DesktopSectionShimmer extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(width: 200, height: 26, decoration: BoxDecoration(
        color: Colors.grey.shade200, borderRadius: BorderRadius.circular(6))),
      const SizedBox(height: 16),
      Row(children: List.generate(4, (i) => Expanded(child: Container(
        margin: EdgeInsets.only(right: i < 3 ? 16 : 0),
        height: 300,
        decoration: BoxDecoration(
          color: Colors.grey.shade200,
          borderRadius: BorderRadius.circular(12),
        ),
      )))),
    ]);
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
                                ? '${product.soldCount} ${AppL10n.of(context).sold}'
                                : AppL10n.of(context).bestSellers,
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
