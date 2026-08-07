import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/global_provider.dart';
import '../../data/models/global_models.dart';
import '../widgets/global_product_card.dart';
import '../../../../core/widgets/smart_location_banner.dart';

class GlobalHomeScreen extends ConsumerStatefulWidget {
  const GlobalHomeScreen({super.key});

  @override
  ConsumerState<GlobalHomeScreen> createState() => _GlobalHomeScreenState();
}

class _GlobalHomeScreenState extends ConsumerState<GlobalHomeScreen> {
  final _searchCtrl = TextEditingController();


  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(globalAuthProvider);
    final categories = ref.watch(globalCategoriesProvider);
    final featured = ref.watch(globalFeaturedProvider);
    final flash = ref.watch(globalFlashDealsProvider);
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
                                onTap: () =>
                                    context.push('/global/products')),
                            SizedBox(
                              height: 90,
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
                  loading: () => const SizedBox(height: 90,
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
                                itemBuilder: (ctx, i) => SizedBox(
                                  width: 160,
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

                // Featured
                featured.when(
                  data: (products) => products.isEmpty
                      ? const SizedBox()
                      : Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _SectionHeader(
                                title: '🌟 Featured Products',
                                onTap: () =>
                                    context.push('/global/products')),
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
  final VoidCallback? onTap;
  const _SectionHeader({required this.title, this.onTap});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Text(title,
            style: const TextStyle(
                fontSize: 16, fontWeight: FontWeight.w800)),
        if (onTap != null)
          GestureDetector(
            onTap: onTap,
            child: const Text('See all →',
                style: TextStyle(
                    fontSize: 12,
                    color: Color(0xFFF59E0B),
                    fontWeight: FontWeight.w600)),
          ),
      ]),
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
        margin: const EdgeInsets.only(right: 10),
        width: 72,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                  color: Color(color), borderRadius: BorderRadius.circular(14)),
              child: cat.icon != null
                  ? Center(
                      child:
                          Text(cat.icon!, style: const TextStyle(fontSize: 24)))
                  : cat.image != null
                      ? ClipRRect(
                          borderRadius: BorderRadius.circular(14),
                          child: Image.network(cat.image!,
                              fit: BoxFit.cover,
                              errorBuilder: (_, __, ___) => const Icon(
                                  Icons.category,
                                  color: Colors.grey)))
                      : const Icon(Icons.category, color: Colors.grey),
            ),
            const SizedBox(height: 5),
            Text(cat.name,
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                    fontSize: 10, fontWeight: FontWeight.w600)),
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
