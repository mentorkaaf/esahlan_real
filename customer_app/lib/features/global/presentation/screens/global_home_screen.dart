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

  static const _banners = [
    {'title': 'Free Shipping', 'sub': 'On orders over \$50 to Europe & USA', 'color': 0xFF1A1A2E, 'icon': '🚚'},
    {'title': 'Flash Deals', 'sub': 'Up to 60% off today only', 'color': 0xFFB45309, 'icon': '⚡'},
    {'title': 'New Arrivals', 'sub': 'Fresh styles just landed', 'color': 0xFF065F46, 'icon': '✨'},
  ];

  int _bannerIndex = 0;

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
                        onPressed: () => context.push('/global/orders'),
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

                // Banner carousel
                _BannerCarousel(banners: _banners),

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

class _BannerCarousel extends StatefulWidget {
  final List<Map<String, dynamic>> banners;
  const _BannerCarousel({required this.banners});

  @override
  State<_BannerCarousel> createState() => _BannerCarouselState();
}

class _BannerCarouselState extends State<_BannerCarousel> {
  int _index = 0;
  final PageController _ctrl = PageController();

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      SizedBox(
        height: 140,
        child: PageView.builder(
          controller: _ctrl,
          onPageChanged: (i) => setState(() => _index = i),
          itemCount: widget.banners.length,
          itemBuilder: (_, i) {
            final b = widget.banners[i];
            return Container(
              margin: const EdgeInsets.fromLTRB(16, 16, 16, 8),
              decoration: BoxDecoration(
                color: Color(b['color'] as int),
                borderRadius: BorderRadius.circular(16),
              ),
              padding: const EdgeInsets.all(20),
              child: Row(children: [
                Expanded(
                  child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(b['title'] as String,
                            style: const TextStyle(
                                color: Colors.white,
                                fontSize: 20,
                                fontWeight: FontWeight.w800)),
                        const SizedBox(height: 6),
                        Text(b['sub'] as String,
                            style: const TextStyle(
                                color: Colors.white70, fontSize: 12)),
                        const SizedBox(height: 12),
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 14, vertical: 7),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF59E0B),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: const Text('Shop Now',
                              style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFF1A1A2E))),
                        ),
                      ]),
                ),
                Text(b['icon'] as String,
                    style: const TextStyle(fontSize: 56)),
              ]),
            );
          },
        ),
      ),
      Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: List.generate(
            widget.banners.length,
            (i) => AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  margin: const EdgeInsets.symmetric(horizontal: 3),
                  width: _index == i ? 20 : 6,
                  height: 6,
                  decoration: BoxDecoration(
                    color: _index == i
                        ? const Color(0xFFF59E0B)
                        : Colors.grey.shade300,
                    borderRadius: BorderRadius.circular(3),
                  ),
                )),
      ),
    ]);
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
