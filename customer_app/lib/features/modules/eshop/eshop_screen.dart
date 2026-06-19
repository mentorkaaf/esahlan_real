import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../core/theme/app_color_tokens.dart';
import '../../../core/widgets/network_image_widget.dart';
import 'eshop_providers.dart';
import '../../ads/services/ad_service.dart';

// ─────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────
double _toD(dynamic v) => double.tryParse(v?.toString() ?? '0') ?? 0;
String _fmt(dynamic v) => '\$${_toD(v).toStringAsFixed(2)}';

Widget _netImg(String? url, {BoxFit fit = BoxFit.cover, Widget? placeholder}) =>
    NetImage(url: url, fit: fit);

Widget _shimmer({double? w, double? h, double r = 10}) => Container(
  width: w, height: h,
  decoration: BoxDecoration(color: AppColors.shimmer, borderRadius: BorderRadius.circular(r)),
);

Widget _starRow(dynamic rating, dynamic reviewCount) {
  final r = _toD(rating);
  final rc = int.tryParse(reviewCount?.toString() ?? '0') ?? 0;
  return Row(mainAxisSize: MainAxisSize.min, children: [
    ...List.generate(5, (i) {
      if (i < r.floor()) return const Icon(Icons.star, color: Color(0xFFFFC107), size: 13);
      if (i < r && r - i >= 0.5) return const Icon(Icons.star_half, color: Color(0xFFFFC107), size: 13);
      return const Icon(Icons.star_border, color: Color(0xFFFFC107), size: 13);
    }),
    const SizedBox(width: 4),
    Text('($rc)', style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
  ]);
}

// ─────────────────────────────────────────────────────────────────
// eShop Home Screen
// ─────────────────────────────────────────────────────────────────
class EShopScreen extends ConsumerStatefulWidget {
  const EShopScreen({super.key});
  @override
  ConsumerState<EShopScreen> createState() => _EShopScreenState();
}

class _EShopScreenState extends ConsumerState<EShopScreen> with WidgetsBindingObserver {
  final _searchCtrl = TextEditingController();
  String _search = '';
  final _bannerCtrl = PageController();
  int _bannerPage = 0;
  Timer? _bannerTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    AdService.instance.triggerModulePopups(context, 'eshop');
    _bannerTimer = Timer.periodic(const Duration(seconds: 3), (_) {
      if (_bannerCtrl.hasClients) {
        final next = (_bannerPage + 1);
        _bannerCtrl.animateToPage(next, duration: const Duration(milliseconds: 400), curve: Curves.easeInOut);
      }
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _searchCtrl.dispose();
    _bannerTimer?.cancel();
    _bannerCtrl.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      ref.invalidate(eshopHomeProvider);
      ref.invalidate(eshopCategoriesProvider);
      ref.invalidate(eshopFlashDealsProvider);
      ref.invalidate(eshopDealsOfDayProvider);
      ref.invalidate(eshopCampaignsProvider);
    }
  }

  void _refresh() {
    ref.invalidate(eshopHomeProvider);
    ref.invalidate(eshopFlashDealsProvider);
    ref.invalidate(eshopDealsOfDayProvider);
    ref.invalidate(eshopCampaignsProvider);
    ref.invalidate(eshopCategoriesProvider);
    ref.invalidate(eshopProductsProvider);
  }

  @override
  Widget build(BuildContext context) {
    final homeAsync = ref.watch(eshopHomeProvider);
    final flashAsync = ref.watch(eshopFlashDealsProvider);
    final dealsAsync = ref.watch(eshopDealsOfDayProvider);
    final campaignsAsync = ref.watch(eshopCampaignsProvider);
    final featuredAsync = ref.watch(eshopProductsProvider(const ProductsParams(featured: true, page: 1)));
    final allAsync = ref.watch(eshopProductsProvider(ProductsParams(search: _search.isEmpty ? null : _search, page: 1)));
    final cart = ref.watch(eshopCartProvider);
    final cartNotifier = ref.read(eshopCartProvider.notifier);

    final cartCount = cartNotifier.totalCount;
    final cartTotal = cartNotifier.subtotal;

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: RefreshIndicator(
        color: AppColors.primary,
        onRefresh: () async => _refresh(),
        child: CustomScrollView(slivers: [
          // ── Sticky Header ─────────────────────────────────────────
          SliverAppBar(
            pinned: true,
            floating: true,
            backgroundColor: context.colors.cardBg,
            elevation: 0,
            scrolledUnderElevation: 1,
            leading: IconButton(
              icon: Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: context.colors.navyText),
              onPressed: () => context.pop(),
            ),
            title: Text('eShop', style: TextStyle(fontWeight: FontWeight.w800, color: context.colors.navyText, fontFamily: 'Cairo')),
            actions: [
              IconButton(
                icon: Icon(Icons.favorite_border_rounded, color: context.colors.navyText),
                onPressed: () {},
              ),
              Stack(children: [
                IconButton(
                  icon: Icon(Icons.shopping_bag_outlined, color: context.colors.navyText),
                  onPressed: () => context.push('/eshop/cart'),
                ),
                if (cartCount > 0) Positioned(right: 6, top: 6, child: Container(
                  width: 16, height: 16,
                  decoration: const BoxDecoration(color: AppColors.primary, shape: BoxShape.circle),
                  child: Center(child: Text('$cartCount', style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w900))),
                )),
              ]),
            ],
            bottom: PreferredSize(
              preferredSize: const Size.fromHeight(56),
              child: Container(
                color: context.colors.cardBg,
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
                child: Row(children: [
                  Expanded(child: TextField(
                    controller: _searchCtrl,
                    onChanged: (v) => setState(() => _search = v),
                    onSubmitted: (v) => context.push('/eshop/products?search=${Uri.encodeComponent(v)}'),
                    decoration: InputDecoration(
                      hintText: 'Search products...',
                      hintStyle: TextStyle(color: context.colors.mutedText, fontSize: 13),
                      prefixIcon: Icon(Icons.search_rounded, color: context.colors.mutedText, size: 20),
                      suffixIcon: _search.isNotEmpty
                          ? IconButton(icon: Icon(Icons.close, size: 16), onPressed: () { _searchCtrl.clear(); setState(() => _search = ''); })
                          : null,
                      filled: true, fillColor: context.colors.inputFill,
                      contentPadding: const EdgeInsets.symmetric(vertical: 10),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                    ),
                  )),
                ]),
              ),
            ),
          ),

          // ── Hero Banners ──────────────────────────────────────────
          homeAsync.when(
            loading: () => SliverToBoxAdapter(child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              child: _shimmer(h: 180, r: 16),
            )),
            error: (_, __) => const SliverToBoxAdapter(child: SizedBox()),
            data: (home) {
              final banners = (home['banners'] as List?) ?? [];
              if (banners.isEmpty) return const SliverToBoxAdapter(child: SizedBox());
              final loopCount = banners.length * 1000;
              return SliverToBoxAdapter(child: Column(children: [
                const SizedBox(height: 12),
                SizedBox(height: 180, child: PageView.builder(
                  controller: _bannerCtrl,
                  itemCount: loopCount,
                  onPageChanged: (i) => setState(() => _bannerPage = i % banners.length),
                  itemBuilder: (_, i) {
                    final b = banners[i % banners.length];
                    return Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(16),
                        child: _netImg(b['image'], fit: BoxFit.cover),
                      ),
                    );
                  },
                )),
                const SizedBox(height: 8),
                Row(mainAxisAlignment: MainAxisAlignment.center, children: List.generate(banners.length, (i) => AnimatedContainer(
                  duration: const Duration(milliseconds: 300),
                  margin: const EdgeInsets.symmetric(horizontal: 3),
                  width: (_bannerPage % banners.length) == i ? 20 : 6,
                  height: 6,
                  decoration: BoxDecoration(
                    color: (_bannerPage % banners.length) == i ? AppColors.primary : AppColors.divider,
                    borderRadius: BorderRadius.circular(3),
                  ),
                ))),
              ]));
            },
          ),

          // ── Categories (auto-carousel) ────────────────────────────
          SliverToBoxAdapter(child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
            child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text('Categories', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.navyText)),
              GestureDetector(
                onTap: () => context.push('/eshop/products'),
                child: const Text('See All', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 13)),
              ),
            ]),
          )),
          homeAsync.when(
            loading: () => SliverToBoxAdapter(child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: SizedBox(height: 90, child: Row(children: List.generate(5, (_) => Padding(
                padding: const EdgeInsets.only(right: 12),
                child: _shimmer(w: 64, h: 90, r: 16),
              )))),
            )),
            error: (_, __) => const SliverToBoxAdapter(child: SizedBox()),
            data: (home) {
              final cats = (home['categories'] as List?) ?? [];
              if (cats.isEmpty) return const SliverToBoxAdapter(child: SizedBox());
              return SliverToBoxAdapter(child: _CategoriesCarousel(cats: cats));
            },
          ),

          // ── Deals of the Day ──────────────────────────────────────
          dealsAsync.when(
            loading: () => const SliverToBoxAdapter(child: SizedBox()),
            error: (_, __) => const SliverToBoxAdapter(child: SizedBox()),
            data: (deals) {
              if (deals.isEmpty) return const SliverToBoxAdapter(child: SizedBox());
              return SliverToBoxAdapter(child: _DealsOfDaySection(deals: deals, cartNotifier: cartNotifier, cart: cart));
            },
          ),

          // ── Campaigns ─────────────────────────────────────────────
          campaignsAsync.when(
            loading: () => const SliverToBoxAdapter(child: SizedBox()),
            error: (_, __) => const SliverToBoxAdapter(child: SizedBox()),
            data: (campaigns) {
              if (campaigns.isEmpty) return const SliverToBoxAdapter(child: SizedBox());
              return SliverToBoxAdapter(child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 20, 16, 12),
                    child: Text('Campaigns', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.navyText)),
                  ),
                  ...campaigns.map((c) {
                    final campaign = c as Map<String, dynamic>;
                    return Padding(
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                      child: GestureDetector(
                        onTap: () => context.push('/eshop/products'),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(16),
                          child: Stack(children: [
                            _netImg(campaign['banner'], fit: BoxFit.cover),
                            Positioned.fill(child: Container(
                              decoration: BoxDecoration(
                                gradient: LinearGradient(
                                  colors: [Colors.black.withValues(alpha: 0.5), Colors.transparent],
                                  begin: Alignment.bottomLeft,
                                  end: Alignment.topRight,
                                ),
                              ),
                            )),
                            Positioned(bottom: 16, left: 16, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text(campaign['title'] ?? '', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
                              if (campaign['discount_value'] != null)
                                Container(
                                  margin: const EdgeInsets.only(top: 6),
                                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                                  decoration: BoxDecoration(color: AppColors.primary, borderRadius: BorderRadius.circular(20)),
                                  child: Text('Up to ${campaign['discount_value']}% OFF', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
                                ),
                            ])),
                          ]),
                        ),
                      ),
                    );
                  }),
                ],
              ));
            },
          ),

          // ── Featured Products (auto-carousel) ────────────────────
          SliverToBoxAdapter(child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 10),
            child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text('Featured Products', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.navyText)),
              GestureDetector(
                onTap: () => context.push('/eshop/products?featured=1'),
                child: const Text('See All', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 13)),
              ),
            ]),
          )),
          featuredAsync.when(
            loading: () => SliverToBoxAdapter(child: SizedBox(height: 220, child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: List.generate(3, (_) => Padding(
                padding: const EdgeInsets.only(right: 12),
                child: _shimmer(w: 150, h: 220, r: 14),
              )),
            ))),
            error: (_, __) => const SliverToBoxAdapter(child: SizedBox()),
            data: (res) {
              final products = (res['data'] as List?) ?? [];
              if (products.isEmpty) return const SliverToBoxAdapter(child: SizedBox());
              return SliverToBoxAdapter(child: _FeaturedCarousel(
                products: products, cartNotifier: cartNotifier, cart: cart));
            },
          ),

          // ── Flash Deals (compact banner — tap to view) ────────────
          flashAsync.when(
            loading: () => const SliverToBoxAdapter(child: SizedBox()),
            error: (_, __) => const SliverToBoxAdapter(child: SizedBox()),
            data: (deals) {
              if (deals.isEmpty) return const SliverToBoxAdapter(child: SizedBox());
              final deal = deals.first as Map<String, dynamic>;
              final products = (deal['products'] as List?) ?? [];
              if (products.isEmpty) return const SliverToBoxAdapter(child: SizedBox());
              return SliverToBoxAdapter(child: _FlashDealBanner(
                deal: deal, products: products, cartNotifier: cartNotifier, cart: cart));
            },
          ),

          // ── All Products ──────────────────────────────────────────
          SliverToBoxAdapter(child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 12),
            child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text('All Products', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.navyText)),
              GestureDetector(
                onTap: () => context.push('/eshop/products'),
                child: const Text('See All', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 13)),
              ),
            ]),
          )),
          allAsync.when(
            loading: () => SliverPadding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              sliver: SliverGrid(
                delegate: SliverChildBuilderDelegate((_, __) => _shimmer(r: 14), childCount: 4),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2, childAspectRatio: 0.72, crossAxisSpacing: 12, mainAxisSpacing: 12),
              ),
            ),
            error: (_, __) => const SliverToBoxAdapter(child: SizedBox()),
            data: (res) {
              final products = (res['data'] as List?) ?? [];
              if (products.isEmpty) return const SliverToBoxAdapter(child: SizedBox());
              return SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                sliver: SliverGrid(
                  delegate: SliverChildBuilderDelegate(
                    (_, i) => _ProductCard(
                      product: products[i] as Map<String, dynamic>,
                      cartNotifier: cartNotifier,
                      cart: cart,
                    ),
                    childCount: products.length,
                  ),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2, childAspectRatio: 0.72, crossAxisSpacing: 12, mainAxisSpacing: 12),
                ),
              );
            },
          ),

          const SliverToBoxAdapter(child: SizedBox(height: 90)),
        ]),
      ),
      // ── Floating Cart Button ──────────────────────────────────────
      floatingActionButton: cartCount > 0 ? Padding(
        padding: const EdgeInsets.only(bottom: 0),
        child: GestureDetector(
          onTap: () => context.push('/eshop/cart'),
          child: Container(
            margin: const EdgeInsets.symmetric(horizontal: 16),
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: [AppColors.primary, Color(0xFFFF6600)]),
              borderRadius: BorderRadius.circular(30),
              boxShadow: [BoxShadow(color: AppColors.primary.withValues(alpha: 0.4), blurRadius: 16, offset: const Offset(0, 6))],
            ),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.shopping_bag_rounded, color: Colors.white, size: 20),
              const SizedBox(width: 10),
              Text('$cartCount ${cartCount == 1 ? 'item' : 'items'} • ${_fmt(cartTotal)}',
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14)),
              const SizedBox(width: 10),
              const Text('View Cart', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13)),
            ]),
          ),
        ),
      ) : null,
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
    );
  }
}

// ─────────────────────────────────────────────────────────────────
// Categories Auto-Carousel
// ─────────────────────────────────────────────────────────────────
class _CategoriesCarousel extends StatefulWidget {
  final List<dynamic> cats;
  const _CategoriesCarousel({required this.cats});
  @override
  State<_CategoriesCarousel> createState() => _CategoriesCarouselState();
}

class _CategoriesCarouselState extends State<_CategoriesCarousel> {
  final _ctrl = ScrollController();
  Timer? _timer;
  bool _fwd = true;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(milliseconds: 2200), (_) => _scroll());
  }

  void _scroll() {
    if (!_ctrl.hasClients) return;
    final max = _ctrl.position.maxScrollExtent;
    final cur = _ctrl.offset;
    const step = 84.0;
    if (_fwd) {
      final next = (cur + step).clamp(0.0, max);
      if (next >= max) _fwd = false;
      _ctrl.animateTo(next, duration: const Duration(milliseconds: 500), curve: Curves.easeInOut);
    } else {
      final next = (cur - step).clamp(0.0, max);
      if (next <= 0) _fwd = true;
      _ctrl.animateTo(next, duration: const Duration(milliseconds: 500), curve: Curves.easeInOut);
    }
  }

  @override
  void dispose() { _timer?.cancel(); _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return SizedBox(height: 92, child: ListView.builder(
      controller: _ctrl,
      scrollDirection: Axis.horizontal,
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: widget.cats.length,
      itemBuilder: (_, i) {
        final cat = widget.cats[i] as Map<String, dynamic>;
        return GestureDetector(
          onTap: () => context.push(
            '/eshop/products?category_id=${cat['id']}&category_name=${Uri.encodeComponent(cat['name'] ?? '')}'),
          child: Container(
            margin: const EdgeInsets.only(right: 10),
            width: 74,
            child: Column(children: [
              Container(
                width: 60, height: 60,
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.surface,
                  borderRadius: BorderRadius.circular(18),
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.07), blurRadius: 10, offset: const Offset(0,3))],
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(18),
                  child: _netImg(cat['image'], placeholder: Container(
                    color: AppColors.surface,
                    child: const Icon(Icons.category_outlined, color: AppColors.primary, size: 26),
                  )),
                ),
              ),
              SizedBox(height: 6),
              Text(cat['name'] ?? '', textAlign: TextAlign.center, maxLines: 1, overflow: TextOverflow.ellipsis,
                style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: context.colors.navyText)),
            ]),
          ),
        );
      },
    ));
  }
}

// ─────────────────────────────────────────────────────────────────
// Featured Products Auto-Carousel
// ─────────────────────────────────────────────────────────────────
class _FeaturedCarousel extends StatefulWidget {
  final List<dynamic> products;
  final EShopCartNotifier cartNotifier;
  final List<CartItem> cart;
  const _FeaturedCarousel({required this.products, required this.cartNotifier, required this.cart});
  @override
  State<_FeaturedCarousel> createState() => _FeaturedCarouselState();
}

class _FeaturedCarouselState extends State<_FeaturedCarousel> {
  final _ctrl = ScrollController();
  Timer? _timer;
  bool _fwd = true;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(milliseconds: 2600), (_) => _scroll());
  }

  void _scroll() {
    if (!_ctrl.hasClients) return;
    final max = _ctrl.position.maxScrollExtent;
    final cur = _ctrl.offset;
    const step = 167.0; // card 155 + spacing 12
    if (_fwd) {
      final next = (cur + step).clamp(0.0, max);
      if (next >= max) _fwd = false;
      _ctrl.animateTo(next, duration: const Duration(milliseconds: 600), curve: Curves.easeInOut);
    } else {
      final next = (cur - step).clamp(0.0, max);
      if (next <= 0) _fwd = true;
      _ctrl.animateTo(next, duration: const Duration(milliseconds: 600), curve: Curves.easeInOut);
    }
  }

  @override
  void dispose() { _timer?.cancel(); _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return SizedBox(height: 228, child: ListView.builder(
      controller: _ctrl,
      scrollDirection: Axis.horizontal,
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: widget.products.length,
      itemBuilder: (_, i) => _ProductCard(
        product: widget.products[i] as Map<String, dynamic>,
        cartNotifier: widget.cartNotifier,
        cart: widget.cart,
        horizontal: true,
      ),
    ));
  }
}

// ─────────────────────────────────────────────────────────────────
// Flash Deal Compact Banner (tap → bottom sheet)
// ─────────────────────────────────────────────────────────────────
class _FlashDealBanner extends StatefulWidget {
  final Map<String, dynamic> deal;
  final List<dynamic> products;
  final EShopCartNotifier cartNotifier;
  final List<CartItem> cart;
  const _FlashDealBanner({required this.deal, required this.products, required this.cartNotifier, required this.cart});
  @override
  State<_FlashDealBanner> createState() => _FlashDealBannerState();
}

class _FlashDealBannerState extends State<_FlashDealBanner> {
  Timer? _timer;
  Duration _remaining = Duration.zero;

  @override
  void initState() {
    super.initState();
    _calcRemaining();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => _calcRemaining());
  }

  void _calcRemaining() {
    final end = DateTime.tryParse(widget.deal['ends_at'] ?? '');
    if (end == null) return;
    final now = DateTime.now();
    if (mounted) setState(() => _remaining = end.isAfter(now) ? end.difference(now) : Duration.zero);
  }

  @override
  void dispose() { _timer?.cancel(); super.dispose(); }

  String _pad(int n) => n.toString().padLeft(2, '0');

  void _openSheet(BuildContext ctx) {
    showModalBottomSheet(
      context: ctx,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (sheetCtx) => _FlashDealSheet(
        deal: widget.deal,
        products: widget.products,
        cartNotifier: widget.cartNotifier,
        onTap: (id) { Navigator.of(sheetCtx).pop(); ctx.push('/eshop/products/$id'); },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final h = _pad(_remaining.inHours);
    final m = _pad(_remaining.inMinutes.remainder(60));
    final s = _pad(_remaining.inSeconds.remainder(60));

    return GestureDetector(
      onTap: () => _openSheet(context),
      child: Container(
        margin: const EdgeInsets.fromLTRB(16, 14, 16, 0),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          gradient: const LinearGradient(colors: [Color(0xFFFF4500), Color(0xFFFF8A00)],
              begin: Alignment.centerLeft, end: Alignment.centerRight),
          boxShadow: [BoxShadow(
            color: const Color(0xFFFF8A00).withValues(alpha: 0.32),
            blurRadius: 16, offset: const Offset(0, 5),
          )],
        ),
        child: Row(children: [
          // ⚡ icon circle
          Container(
            width: 38, height: 38,
            decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.22), shape: BoxShape.circle),
            child: const Icon(Icons.bolt_rounded, color: Colors.white, size: 20),
          ),
          const SizedBox(width: 12),
          // Label
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Flash Deals', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 14, letterSpacing: 0.2)),
            Text(widget.deal['title'] ?? 'Limited offers',
              style: TextStyle(color: Colors.white.withValues(alpha: 0.8), fontSize: 11)),
          ])),
          // Countdown chip
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            decoration: BoxDecoration(
              color: Colors.black.withValues(alpha: 0.22),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text('$h : $m : $s',
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 13,
                fontFeatures: [FontFeature.tabularFigures()])),
          ),
          const SizedBox(width: 8),
          const Icon(Icons.chevron_right_rounded, color: Colors.white, size: 20),
        ]),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────
// Flash Deal Bottom Sheet
// ─────────────────────────────────────────────────────────────────
class _FlashDealSheet extends StatelessWidget {
  final Map<String, dynamic> deal;
  final List<dynamic> products;
  final EShopCartNotifier cartNotifier;
  final void Function(int id) onTap;

  const _FlashDealSheet({required this.deal, required this.products,
    required this.cartNotifier, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: MediaQuery.of(context).size.height * 0.76,
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(children: [
        // Handle bar
        Container(margin: const EdgeInsets.symmetric(vertical: 12),
          width: 40, height: 4,
          decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2))),
        // Header
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
          child: Row(children: [
            Container(
              width: 36, height: 36,
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [Color(0xFFFF4500), Color(0xFFFF8A00)]),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(Icons.bolt_rounded, color: Colors.white, size: 20),
            ),
            SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Flash Deals', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 17, color: context.colors.navyText)),
              Text(deal['title'] ?? '', style: TextStyle(fontSize: 12, color: context.colors.mutedText)),
            ])),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
              decoration: BoxDecoration(
                color: const Color(0xFFFF4500).withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text('${products.length} Items',
                style: const TextStyle(color: Color(0xFFFF4500), fontWeight: FontWeight.w700, fontSize: 12)),
            ),
          ]),
        ),
        const Divider(height: 1),
        Expanded(child: GridView.builder(
          padding: const EdgeInsets.all(16),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2, childAspectRatio: 0.74,
            crossAxisSpacing: 12, mainAxisSpacing: 12),
          itemCount: products.length,
          itemBuilder: (_, i) {
            final p = products[i] as Map<String, dynamic>;
            final qty = cartNotifier.qtyFor(p['id'] as int);
            final hasDisc = p['sale_price'] != null && _toD(p['sale_price']) < _toD(p['price']);
            return GestureDetector(
              onTap: () => onTap(p['id'] as int),
              child: Container(
                decoration: BoxDecoration(
                  color: context.colors.cardBg,
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 8)],
                ),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Expanded(child: Stack(children: [
                    ClipRRect(
                      borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
                      child: SizedBox.expand(child: _netImg(p['thumbnail'])),
                    ),
                    if (hasDisc) Positioned(top: 7, left: 7, child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                      decoration: BoxDecoration(color: const Color(0xFFFF4500), borderRadius: BorderRadius.circular(6)),
                      child: Text(
                        '-${((_toD(p['price']) - _toD(p['sale_price'])) / _toD(p['price']) * 100).round()}%',
                        style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 9)),
                    )),
                  ])),
                  Padding(padding: const EdgeInsets.fromLTRB(10, 8, 10, 10), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(p['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: context.colors.navyText),
                      maxLines: 1, overflow: TextOverflow.ellipsis),
                    const SizedBox(height: 5),
                    Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                      Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(_fmt(p['sale_price'] ?? p['price']),
                          style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 13, color: Color(0xFFFF4500))),
                        if (hasDisc) Text(_fmt(p['price']),
                          style: const TextStyle(decoration: TextDecoration.lineThrough, color: AppColors.textGrey, fontSize: 10)),
                      ]),
                      GestureDetector(
                        onTap: () => cartNotifier.addItem(p),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          padding: const EdgeInsets.all(6),
                          decoration: BoxDecoration(
                            color: qty > 0 ? const Color(0xFFFF4500) : const Color(0xFFFF4500).withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Icon(qty > 0 ? Icons.check_rounded : Icons.add_rounded,
                            size: 14, color: qty > 0 ? Colors.white : const Color(0xFFFF4500)),
                        ),
                      ),
                    ]),
                  ])),
                ]),
              ),
            );
          },
        )),
      ]),
    );
  }
}

// ─────────────────────────────────────────────────────────────────
// Flash Deal Section (LEGACY — kept for reference, not used)
// ─────────────────────────────────────────────────────────────────
class _FlashDealSection extends StatefulWidget {
  final Map<String, dynamic> deal;
  final List<dynamic> products;
  final EShopCartNotifier cartNotifier;
  final List<CartItem> cart;

  const _FlashDealSection({required this.deal, required this.products, required this.cartNotifier, required this.cart});

  @override
  State<_FlashDealSection> createState() => _FlashDealSectionState();
}

class _FlashDealSectionState extends State<_FlashDealSection> {
  Timer? _timer;
  Duration _remaining = Duration.zero;

  @override
  void initState() {
    super.initState();
    _calcRemaining();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => _calcRemaining());
  }

  void _calcRemaining() {
    final endsAt = widget.deal['ends_at'];
    if (endsAt == null) return;
    final end = DateTime.tryParse(endsAt);
    if (end == null) return;
    final now = DateTime.now();
    setState(() => _remaining = end.isAfter(now) ? end.difference(now) : Duration.zero);
  }

  @override
  void dispose() { _timer?.cancel(); super.dispose(); }

  String _twoDigits(int n) => n.toString().padLeft(2, '0');

  Widget _timeBox(String val, String label) => Column(children: [
    Container(
      width: 44, height: 44,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.white.withValues(alpha: 0.3)),
      ),
      child: Center(child: Text(val,
        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 18, height: 1))),
    ),
    const SizedBox(height: 3),
    Text(label, style: const TextStyle(color: Colors.white70, fontSize: 9, fontWeight: FontWeight.w600)),
  ]);

  @override
  Widget build(BuildContext context) {
    final h = _twoDigits(_remaining.inHours);
    final m = _twoDigits(_remaining.inMinutes.remainder(60));
    final s = _twoDigits(_remaining.inSeconds.remainder(60));
    final dealName = widget.deal['title'] as String? ?? 'Flash Deals';

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        gradient: const LinearGradient(
          colors: [Color(0xFFFF4500), Color(0xFFFF8A00)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: [BoxShadow(
          color: const Color(0xFFFF8A00).withValues(alpha: 0.35),
          blurRadius: 24, offset: const Offset(0, 8),
        )],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // ── Header ───────────────────────────────────
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 10),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.22),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: const Row(mainAxisSize: MainAxisSize.min, children: [
                    Icon(Icons.bolt_rounded, color: Colors.white, size: 13),
                    SizedBox(width: 3),
                    Text('FLASH DEALS', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 10, letterSpacing: 0.8)),
                  ]),
                ),
              ]),
              const SizedBox(height: 6),
              Text(dealName,
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 17, height: 1.2)),
              const SizedBox(height: 3),
              const Text('Hurry! Offer ends soon', style: TextStyle(color: Colors.white70, fontSize: 12)),
            ])),
            const SizedBox(width: 12),
            // Countdown
            Column(children: [
              Row(mainAxisSize: MainAxisSize.min, children: [
                _timeBox(h, 'HRS'),
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Text(' : ', style: TextStyle(color: Colors.white.withValues(alpha: 0.7), fontWeight: FontWeight.w900, fontSize: 18)),
                ),
                _timeBox(m, 'MIN'),
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Text(' : ', style: TextStyle(color: Colors.white.withValues(alpha: 0.7), fontWeight: FontWeight.w900, fontSize: 18)),
                ),
                _timeBox(s, 'SEC'),
              ]),
            ]),
          ]),
        ),

        // ── Product cards ────────────────────────────
        SizedBox(height: 168, child: ListView.builder(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 0),
          itemCount: widget.products.length,
          itemBuilder: (_, i) {
            final p = widget.products[i] as Map<String, dynamic>;
            final qty = widget.cartNotifier.qtyFor(p['id'] as int);
            final hasDisc = p['sale_price'] != null && _toD(p['sale_price']) < _toD(p['price']);
            return GestureDetector(
              onTap: () => context.push('/eshop/products/${p['id']}'),
              child: Container(
                width: 120,
                margin: const EdgeInsets.only(right: 12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 10)],
                ),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  // Image
                  Expanded(child: Stack(children: [
                    ClipRRect(
                      borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
                      child: SizedBox.expand(child: _netImg(p['thumbnail'])),
                    ),
                    if (hasDisc) Positioned(top: 7, left: 7, child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                      decoration: BoxDecoration(color: const Color(0xFFFF4500), borderRadius: BorderRadius.circular(8)),
                      child: Text(
                        '-${((_toD(p['price']) - _toD(p['sale_price'])) / _toD(p['price']) * 100).round()}%',
                        style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 10)),
                    )),
                  ])),
                  // Info
                  Padding(padding: const EdgeInsets.fromLTRB(10, 8, 10, 10), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(p['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 11, color: context.colors.navyText), maxLines: 1, overflow: TextOverflow.ellipsis),
                    const SizedBox(height: 5),
                    Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                      Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(_fmt(p['sale_price'] ?? p['price']),
                          style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 13, color: Color(0xFFFF4500))),
                        if (hasDisc) Text(_fmt(p['price']),
                          style: const TextStyle(decoration: TextDecoration.lineThrough, color: AppColors.textGrey, fontSize: 10)),
                      ]),
                      GestureDetector(
                        onTap: () => widget.cartNotifier.addItem(p),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          padding: const EdgeInsets.all(5),
                          decoration: BoxDecoration(
                            color: qty > 0 ? const Color(0xFFFF4500) : const Color(0xFFFF4500).withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Icon(qty > 0 ? Icons.shopping_bag_rounded : Icons.add_rounded,
                            size: 14, color: qty > 0 ? Colors.white : const Color(0xFFFF4500)),
                        ),
                      ),
                    ]),
                  ])),
                ]),
              ),
            );
          },
        )),
        const SizedBox(height: 12),
      ]),
    );
  }
}

// ─────────────────────────────────────────────────────────────────
// Deals of Day Section
// ─────────────────────────────────────────────────────────────────
class _DealsOfDaySection extends StatelessWidget {
  final List<dynamic> deals;
  final EShopCartNotifier cartNotifier;
  final List<CartItem> cart;

  const _DealsOfDaySection({required this.deals, required this.cartNotifier, required this.cart});

  @override
  Widget build(BuildContext context) {
    return Builder(builder: (context) => Container(
      color: context.colors.inputFill,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 20, 16, 12),
          child: Text('Deals of the Day', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: context.colors.navyText)),
        ),
        SizedBox(height: 220, child: ListView.builder(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          itemCount: deals.length,
          itemBuilder: (_, i) {
            final item = deals[i] as Map<String, dynamic>;
            final p = item['product'] as Map<String, dynamic>? ?? {};
            final badge = item['badge'] as String?;
            return GestureDetector(
              onTap: () => context.push('/eshop/products/${p['id']}'),
              child: Container(
                width: 155,
                margin: const EdgeInsets.only(right: 12),
                decoration: BoxDecoration(
                  color: context.colors.cardBg,
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 8)],
                ),
                child: Stack(children: [
                  Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Expanded(child: ClipRRect(
                      borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
                      child: _netImg(p['thumbnail']),
                    )),
                    Padding(padding: const EdgeInsets.all(10), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(p['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: context.colors.navyText), maxLines: 1, overflow: TextOverflow.ellipsis),
                      const SizedBox(height: 4),
                      Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                        Text(_fmt(p['sale_price'] ?? p['price']), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14, color: AppColors.primary)),
                        GestureDetector(
                          onTap: () => cartNotifier.addItem(p),
                          child: Container(
                            padding: const EdgeInsets.all(5),
                            decoration: BoxDecoration(color: AppColors.primary, borderRadius: BorderRadius.circular(8)),
                            child: const Icon(Icons.add_rounded, size: 14, color: Colors.white),
                          ),
                        ),
                      ]),
                    ])),
                  ]),
                  if (badge != null) Positioned(top: 8, left: 8, child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(color: const Color(0xFF7C3AED), borderRadius: BorderRadius.circular(6)),
                    child: Text(badge, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 10)),
                  )),
                ]),
              ),
            );
          },
        )),
        const SizedBox(height: 4),
      ]),
    ));
  }
}

// ─────────────────────────────────────────────────────────────────
// Product Card (reusable)
// ─────────────────────────────────────────────────────────────────
class _ProductCard extends ConsumerWidget {
  final Map<String, dynamic> product;
  final EShopCartNotifier cartNotifier;
  final List<CartItem> cart;
  final bool horizontal;

  const _ProductCard({
    required this.product,
    required this.cartNotifier,
    required this.cart,
    this.horizontal = false,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = product;
    final inWishlist = ref.watch(eshopWishlistProvider).contains(p['id']);
    final qty = cartNotifier.qtyFor(p['id'] as int);
    final hasDiscount = p['sale_price'] != null && _toD(p['sale_price']) < _toD(p['price']);

    Widget card = Container(
      width: horizontal ? 155 : null,
      margin: horizontal ? const EdgeInsets.only(right: 12) : EdgeInsets.zero,
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 8)],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Expanded(child: Stack(children: [
          ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
            child: SizedBox.expand(child: _netImg(p['thumbnail'])),
          ),
          if (hasDiscount) Positioned(top: 8, left: 8, child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
            decoration: BoxDecoration(color: AppColors.error, borderRadius: BorderRadius.circular(6)),
            child: Text('-${((_toD(p['price']) - _toD(p['sale_price'])) / _toD(p['price']) * 100).round()}%',
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 10)),
          )),
          Positioned(top: 8, right: 8, child: GestureDetector(
            onTap: () {
              final wl = ref.read(eshopWishlistProvider);
              ref.read(eshopWishlistProvider.notifier).state =
                inWishlist ? (Set<int>.from(wl)..remove(p['id'])) : (Set<int>.from(wl)..add(p['id'] as int));
            },
            child: Container(
              padding: const EdgeInsets.all(5),
              decoration: BoxDecoration(color: context.colors.cardBg, shape: BoxShape.circle,
                boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 4)]),
              child: Icon(inWishlist ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                size: 15, color: inWishlist ? Colors.red : context.colors.mutedText),
            ),
          )),
        ])),
        Padding(padding: const EdgeInsets.all(10), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(p['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: context.colors.navyText), maxLines: 2, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 2),
          _starRow(p['rating'], p['total_reviews']),
          const SizedBox(height: 4),
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(_fmt(p['sale_price'] ?? p['price']), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14, color: AppColors.primary)),
              if (hasDiscount) Text(_fmt(p['price']), style: const TextStyle(decoration: TextDecoration.lineThrough, color: AppColors.textGrey, fontSize: 10)),
            ]),
            GestureDetector(
              onTap: () => cartNotifier.addItem(p),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                padding: const EdgeInsets.all(5),
                decoration: BoxDecoration(
                  color: qty > 0 ? AppColors.primary : AppColors.surface,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(qty > 0 ? Icons.shopping_bag_rounded : Icons.add_shopping_cart_rounded,
                  size: 16, color: qty > 0 ? Colors.white : AppColors.primary),
              ),
            ),
          ]),
        ])),
      ]),
    );

    return GestureDetector(
      onTap: () => context.push('/eshop/products/${p['id']}'),
      child: card,
    );
  }
}
