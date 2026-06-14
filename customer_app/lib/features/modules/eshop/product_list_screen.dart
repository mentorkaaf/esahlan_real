import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/network_image_widget.dart';
import 'eshop_providers.dart';

// ─────────────────────────────────────────────────────────────────
// Helpers (duplicated here for file independence)
// ─────────────────────────────────────────────────────────────────
double _toD(dynamic v) => double.tryParse(v?.toString() ?? '0') ?? 0;
String _fmt(dynamic v) => '\$${_toD(v).toStringAsFixed(2)}';

Widget _netImg(String? url, {BoxFit fit = BoxFit.cover}) {
  if (url == null || url.isEmpty) {
    return Container(color: AppColors.surface, child: const Icon(Icons.image_outlined, color: AppColors.divider, size: 40));
  }
  return Image.network(fixImgUrl(url), fit: fit,
    errorBuilder: (_, __, ___) => Container(color: AppColors.surface, child: const Icon(Icons.image_outlined, color: AppColors.divider, size: 40)));
}

Widget _shimmer({double? w, double? h, double r = 10}) => Container(
  width: w, height: h,
  decoration: BoxDecoration(color: AppColors.shimmer, borderRadius: BorderRadius.circular(r)),
);

Widget _starRow(dynamic rating) {
  final r = _toD(rating);
  return Row(mainAxisSize: MainAxisSize.min, children: List.generate(5, (i) {
    if (i < r.floor()) return const Icon(Icons.star, color: Color(0xFFFFC107), size: 12);
    if (i < r && r - i >= 0.5) return const Icon(Icons.star_half, color: Color(0xFFFFC107), size: 12);
    return const Icon(Icons.star_border, color: Color(0xFFFFC107), size: 12);
  }));
}

// ─────────────────────────────────────────────────────────────────
// Product List Screen
// ─────────────────────────────────────────────────────────────────
class ProductListScreen extends ConsumerStatefulWidget {
  final int? categoryId;
  final String? categoryName;
  final bool featured;

  const ProductListScreen({super.key, this.categoryId, this.categoryName, this.featured = false});

  @override
  ConsumerState<ProductListScreen> createState() => _ProductListScreenState();
}

class _ProductListScreenState extends ConsumerState<ProductListScreen> {
  int? _activeCategoryId;
  String _sort = 'newest';
  String _search = '';
  int _page = 1;
  final List<Map<String, dynamic>> _allProducts = [];
  bool _loadingMore = false;
  bool _hasMore = true;
  final _searchCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();

  @override
  void initState() {
    super.initState();
    _activeCategoryId = widget.categoryId;
    _scrollCtrl.addListener(_onScroll);
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollCtrl.position.pixels >= _scrollCtrl.position.maxScrollExtent - 200 && !_loadingMore && _hasMore) {
      _loadMore();
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || !_hasMore) return;
    setState(() { _loadingMore = true; _page++; });
    try {
      final res = await ref.read(eshopProductsProvider(ProductsParams(
        categoryId: _activeCategoryId,
        search: _search.isEmpty ? null : _search,
        sort: _sort,
        page: _page,
        featured: widget.featured ? true : null,
      )).future);
      final newProducts = (res['data'] as List?) ?? [];
      final meta = res['meta'] as Map?;
      setState(() {
        _allProducts.addAll(newProducts.cast<Map<String, dynamic>>());
        _hasMore = _page < (meta?['last_page'] as int? ?? 1);
        _loadingMore = false;
      });
    } catch (_) {
      setState(() { _loadingMore = false; _page--; });
    }
  }

  void _resetAndReload() {
    setState(() { _page = 1; _allProducts.clear(); _hasMore = true; });
  }

  void _showSortSheet() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _SortSheet(
        current: _sort,
        onSelect: (s) { setState(() { _sort = s; _resetAndReload(); }); },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final params = ProductsParams(
      categoryId: _activeCategoryId,
      search: _search.isEmpty ? null : _search,
      sort: _sort,
      page: 1,
      featured: widget.featured ? true : null,
    );
    final productsAsync = ref.watch(eshopProductsProvider(params));
    final categoriesAsync = ref.watch(eshopCategoriesProvider);
    final cart = ref.watch(eshopCartProvider);
    final cartNotifier = ref.read(eshopCartProvider.notifier);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20),
          onPressed: () => context.pop(),
        ),
        title: Text(widget.categoryName ?? 'All Products', style: const TextStyle(fontWeight: FontWeight.w800, fontFamily: 'Cairo')),
        actions: [
          IconButton(icon: const Icon(Icons.sort_rounded), onPressed: _showSortSheet),
          Stack(children: [
            IconButton(icon: const Icon(Icons.shopping_bag_outlined), onPressed: () => context.push('/eshop/cart')),
            if (cartNotifier.totalCount > 0) Positioned(right: 6, top: 6, child: Container(
              width: 16, height: 16,
              decoration: const BoxDecoration(color: AppColors.primary, shape: BoxShape.circle),
              child: Center(child: Text('${cartNotifier.totalCount}', style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w900))),
            )),
          ]),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(56),
          child: Container(
            color: Colors.white,
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
            child: TextField(
              controller: _searchCtrl,
              onChanged: (v) { setState(() => _search = v); _resetAndReload(); },
              decoration: InputDecoration(
                hintText: 'Search products...',
                hintStyle: const TextStyle(color: AppColors.textGrey, fontSize: 13),
                prefixIcon: const Icon(Icons.search_rounded, color: AppColors.textGrey, size: 20),
                suffixIcon: _search.isNotEmpty
                    ? IconButton(icon: const Icon(Icons.close, size: 16), onPressed: () { _searchCtrl.clear(); setState(() { _search = ''; _resetAndReload(); }); })
                    : null,
                filled: true, fillColor: AppColors.surface,
                contentPadding: const EdgeInsets.symmetric(vertical: 10),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
              ),
            ),
          ),
        ),
      ),
      body: Column(children: [
        // Category Filter Bar
        categoriesAsync.when(
          loading: () => const SizedBox(),
          error: (_, __) => const SizedBox(),
          data: (cats) => SizedBox(height: 50, child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            itemCount: cats.length + 1,
            itemBuilder: (_, i) {
              if (i == 0) return _catChip(null, 'All');
              final cat = cats[i - 1] as Map<String, dynamic>;
              return _catChip(cat['id'] as int?, cat['name'] as String? ?? '');
            },
          )),
        ),
        // Products grid
        Expanded(child: productsAsync.when(
          loading: () => GridView.builder(
            padding: const EdgeInsets.all(16),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2, childAspectRatio: 0.72, crossAxisSpacing: 12, mainAxisSpacing: 12),
            itemCount: 6,
            itemBuilder: (_, __) => _shimmer(r: 14),
          ),
          error: (e, _) => const Center(child: Text('Error loading products', style: TextStyle(color: AppColors.textGrey))),
          data: (res) {
            final products = (res['data'] as List?) ?? [];
            final meta = res['meta'] as Map?;
            final _ = meta?['total'] as int? ?? products.length;

            // Merge with lazy-loaded pages
            final displayList = _allProducts.isEmpty ? products.cast<Map<String, dynamic>>() : _allProducts;

            if (displayList.isEmpty) {
              return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                Container(
                  width: 100, height: 100,
                  decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(50)),
                  child: const Icon(Icons.search_off_rounded, size: 50, color: AppColors.textGrey),
                ),
                const SizedBox(height: 16),
                const Text('No products found', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: AppColors.secondary)),
                const SizedBox(height: 8),
                const Text('Try a different category or search term', style: TextStyle(color: AppColors.textGrey, fontSize: 13)),
              ]));
            }

            // Populate _allProducts on first load
            if (_allProducts.isEmpty && products.isNotEmpty) {
              WidgetsBinding.instance.addPostFrameCallback((_) {
                setState(() {
                  _allProducts.addAll(products.cast<Map<String, dynamic>>());
                  _hasMore = _page < (meta?['last_page'] as int? ?? 1);
                });
              });
            }

            return GridView.builder(
              controller: _scrollCtrl,
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 90),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2, childAspectRatio: 0.72, crossAxisSpacing: 12, mainAxisSpacing: 12),
              itemCount: displayList.length + (_loadingMore ? 2 : (_hasMore ? 1 : 0)),
              itemBuilder: (_, i) {
                if (i == displayList.length) {
                  if (_loadingMore) return _shimmer(r: 14);
                  return Center(child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: TextButton(
                      onPressed: _loadMore,
                      child: const Text('Load More', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700)),
                    ),
                  ));
                }
                if (i >= displayList.length) return _shimmer(r: 14);
                final p = displayList[i];
                return _ProductGridCard(product: p, cartNotifier: cartNotifier, cart: cart);
              },
            );
          },
        )),
      ]),
      floatingActionButton: cartNotifier.totalCount > 0 ? GestureDetector(
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
            Text('${cartNotifier.totalCount} items • ${_fmt(cartNotifier.subtotal)}',
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14)),
          ]),
        ),
      ) : null,
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
    );
  }

  Widget _catChip(int? id, String name) {
    final selected = _activeCategoryId == id;
    return GestureDetector(
      onTap: () { setState(() { _activeCategoryId = id; _resetAndReload(); }); },
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        margin: const EdgeInsets.only(right: 8),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        decoration: BoxDecoration(
          color: selected ? AppColors.primary : Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: selected ? AppColors.primary : AppColors.divider),
        ),
        child: Text(name, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: selected ? Colors.white : AppColors.secondary)),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────
// Grid Card
// ─────────────────────────────────────────────────────────────────
class _ProductGridCard extends ConsumerWidget {
  final Map<String, dynamic> product;
  final EShopCartNotifier cartNotifier;
  final List<CartItem> cart;

  const _ProductGridCard({required this.product, required this.cartNotifier, required this.cart});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = product;
    final qty = cartNotifier.qtyFor(p['id'] as int);
    final hasDiscount = p['sale_price'] != null && _toD(p['sale_price']) < _toD(p['price']);
    final inWishlist = ref.watch(eshopWishlistProvider).contains(p['id']);

    return GestureDetector(
      onTap: () => context.push('/eshop/products/${p['id']}'),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
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
                decoration: BoxDecoration(color: Colors.white, shape: BoxShape.circle,
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 4)]),
                child: Icon(inWishlist ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                  size: 15, color: inWishlist ? Colors.red : AppColors.textGrey),
              ),
            )),
          ])),
          Padding(padding: const EdgeInsets.all(10), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(p['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: AppColors.secondary), maxLines: 2, overflow: TextOverflow.ellipsis),
            const SizedBox(height: 2),
            _starRow(p['rating']),
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
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────
// Sort Bottom Sheet
// ─────────────────────────────────────────────────────────────────
class _SortSheet extends StatelessWidget {
  final String current;
  final ValueChanged<String> onSelect;

  const _SortSheet({required this.current, required this.onSelect});

  @override
  Widget build(BuildContext context) {
    final options = [
      ('newest', 'Newest First', Icons.new_releases_outlined),
      ('price_asc', 'Price: Low to High', Icons.arrow_upward_rounded),
      ('price_desc', 'Price: High to Low', Icons.arrow_downward_rounded),
      ('popular', 'Most Popular', Icons.local_fire_department_outlined),
    ];
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 32),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2))),
        const SizedBox(height: 20),
        const Text('Sort By', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: AppColors.secondary)),
        const SizedBox(height: 16),
        ...options.map((o) => ListTile(
          leading: Icon(o.$3, color: current == o.$1 ? AppColors.primary : AppColors.textGrey),
          title: Text(o.$2, style: TextStyle(fontWeight: current == o.$1 ? FontWeight.w700 : FontWeight.w500, color: AppColors.secondary)),
          trailing: current == o.$1 ? const Icon(Icons.check_circle_rounded, color: AppColors.primary) : null,
          onTap: () { Navigator.pop(context); onSelect(o.$1); },
        )),
      ]),
    );
  }
}
