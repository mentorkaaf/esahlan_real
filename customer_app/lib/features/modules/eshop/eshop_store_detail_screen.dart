import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../core/widgets/network_image_widget.dart';
import 'eshop_providers.dart';

double _toD(dynamic v) => double.tryParse(v?.toString() ?? '0') ?? 0;
int _toI(dynamic v) => int.tryParse(v?.toString() ?? '0') ?? 0;
Map<String, dynamic> _asMap(dynamic v) {
  if (v is Map<String, dynamic>) return v;
  if (v is Map) return Map<String, dynamic>.from(v);
  return {};
}
List<Map<String, dynamic>> _asList(dynamic v) {
  if (v is! List) return [];
  return v.map(_asMap).toList();
}

Widget _star(double r, int count) => Row(mainAxisSize: MainAxisSize.min, children: [
  ...List.generate(5, (i) {
    if (i < r.floor()) return const Icon(Icons.star, color: Color(0xFFFFC107), size: 14);
    if (i < r && r - i >= 0.5) return const Icon(Icons.star_half, color: Color(0xFFFFC107), size: 14);
    return const Icon(Icons.star_border, color: Color(0xFFFFC107), size: 14);
  }),
  const SizedBox(width: 4),
  Text('$count reviews', style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
]);

class EShopStoreDetailScreen extends ConsumerStatefulWidget {
  final int storeId;
  const EShopStoreDetailScreen({super.key, required this.storeId});
  @override
  ConsumerState<EShopStoreDetailScreen> createState() => _State();
}

class _State extends ConsumerState<EShopStoreDetailScreen> with SingleTickerProviderStateMixin {
  int? _selectedCategory;
  late TabController _tabCtrl;

  @override
  void initState() {
    super.initState();
    _tabCtrl = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final storeAsync = ref.watch(eshopStoreDetailProvider(widget.storeId));
    return storeAsync.when(
      loading: () => const Scaffold(body: Center(child: CircularProgressIndicator())),
      error: (e, _) => Scaffold(
        appBar: AppBar(title: const Text('Store')),
        body: Center(child: Text(e.toString())),
      ),
      data: (store) => _buildScreen(context, store),
    );
  }

  Widget _buildScreen(BuildContext context, Map<String, dynamic> store) {
    final vendor = _asMap(store['vendor']);
    final categories = _asList(store['categories']);
    final products = _asList(store['products']);
    final reviewStats = _asMap(store['review_stats']);
    final reviews = _asList(store['reviews']);

    final name = vendor['name']?.toString() ?? 'Store';
    final rating = _toD(vendor['rating']);
    final reviewCount = _toI(vendor['review_count']);
    final isOpen = vendor['is_open'] == true || vendor['is_open'] == 1;
    final deliveryTime = vendor['delivery_time']?.toString();
    final deliveryFee = _toD(vendor['delivery_fee']);
    final minOrder = _toD(vendor['minimum_order']);

    // Filter products by selected category
    final filteredProducts = _selectedCategory == null
        ? products
        : products.where((p) => _toI(p['category_id']) == _selectedCategory).toList();

    return Scaffold(
      body: NestedScrollView(
        headerSliverBuilder: (ctx, _) => [
          SliverAppBar(
            expandedHeight: 220,
            pinned: true,
            backgroundColor: context.surface,
            foregroundColor: context.onSurface,
            flexibleSpace: FlexibleSpaceBar(
              background: Stack(
                fit: StackFit.expand,
                children: [
                  if (vendor['cover_image'] != null)
                    NetImage(url: vendor['cover_image']?.toString(), fit: BoxFit.cover)
                  else
                    Container(
                      decoration: const BoxDecoration(
                        gradient: LinearGradient(
                          colors: [Color(0xFFFF8A00), Color(0xFFFF4E00)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                      ),
                    ),
                  Container(color: Colors.black.withOpacity(0.3)),
                  Positioned(
                    bottom: 16,
                    left: 16,
                    right: 16,
                    child: Row(
                      children: [
                        Container(
                          width: 60,
                          height: 60,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.white, width: 2.5),
                            color: Colors.white,
                          ),
                          child: ClipOval(
                            child: vendor['logo'] != null
                                ? NetImage(url: vendor['logo']?.toString(), fit: BoxFit.cover)
                                : const Icon(Icons.store, size: 32, color: Colors.grey),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(name,
                                  style: const TextStyle(
                                      color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18)),
                              const SizedBox(height: 4),
                              Row(children: [
                                Container(
                                  width: 8,
                                  height: 8,
                                  decoration: BoxDecoration(
                                    shape: BoxShape.circle,
                                    color: isOpen ? Colors.greenAccent : Colors.grey,
                                  ),
                                ),
                                const SizedBox(width: 5),
                                Text(
                                  isOpen ? 'Open Now' : 'Closed',
                                  style: TextStyle(
                                    color: isOpen ? Colors.greenAccent : Colors.grey[300],
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                                if (deliveryTime != null) ...[
                                  const SizedBox(width: 10),
                                  Text('· $deliveryTime',
                                      style: const TextStyle(color: Colors.white70, fontSize: 12)),
                                ],
                              ]),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          // Info bar
          SliverToBoxAdapter(
            child: Container(
              color: context.surface,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _star(rating, reviewCount),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      if (deliveryFee > 0)
                        _infoChip(Icons.delivery_dining, 'Delivery \$${deliveryFee.toStringAsFixed(2)}'),
                      if (deliveryFee == 0) _infoChip(Icons.delivery_dining, 'Free Delivery'),
                      if (minOrder > 0) ...[
                        const SizedBox(width: 8),
                        _infoChip(Icons.shopping_bag_outlined, 'Min \$${minOrder.toStringAsFixed(2)}'),
                      ],
                    ],
                  ),
                ],
              ),
            ),
          ),
          // Category filter
          if (categories.isNotEmpty)
            SliverToBoxAdapter(
              child: Container(
                height: 44,
                color: context.surface,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                  itemCount: categories.length + 1,
                  separatorBuilder: (_, __) => const SizedBox(width: 8),
                  itemBuilder: (_, i) {
                    if (i == 0) {
                      return _catChip('All', _selectedCategory == null, () {
                        setState(() => _selectedCategory = null);
                      });
                    }
                    final cat = categories[i - 1];
                    final catId = _toI(cat['id']);
                    return _catChip(cat['name']?.toString() ?? '', _selectedCategory == catId, () {
                      setState(() => _selectedCategory = catId);
                    });
                  },
                ),
              ),
            ),
          SliverToBoxAdapter(
            child: TabBar(
              controller: _tabCtrl,
              labelColor: const Color(0xFFFF8A00),
              unselectedLabelColor: AppColors.textGrey,
              indicatorColor: const Color(0xFFFF8A00),
              tabs: const [Tab(text: 'Products'), Tab(text: 'Reviews')],
            ),
          ),
        ],
        body: TabBarView(
          controller: _tabCtrl,
          children: [
            _ProductsGrid(products: filteredProducts),
            _ReviewsTab(reviewStats: reviewStats, reviews: reviews),
          ],
        ),
      ),
    );
  }

  Widget _infoChip(IconData icon, String label) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
    decoration: BoxDecoration(
      color: const Color(0xFFFF8A00).withOpacity(0.1),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, size: 13, color: const Color(0xFFFF8A00)),
      const SizedBox(width: 4),
      Text(label, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFFFF8A00))),
    ]),
  );

  Widget _catChip(String label, bool selected, VoidCallback onTap) => GestureDetector(
    onTap: onTap,
    child: AnimatedContainer(
      duration: const Duration(milliseconds: 180),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
      decoration: BoxDecoration(
        color: selected ? const Color(0xFFFF8A00) : Colors.grey[100],
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: selected ? Colors.white : Colors.grey[700],
        ),
      ),
    ),
  );
}

class _ProductsGrid extends ConsumerWidget {
  final List<Map<String, dynamic>> products;
  const _ProductsGrid({required this.products});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (products.isEmpty) {
      return const Center(child: Text('No products available', style: TextStyle(color: AppColors.textGrey)));
    }
    final cart = ref.watch(eshopCartProvider);
    return GridView.builder(
      padding: const EdgeInsets.all(12),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        childAspectRatio: 0.72,
      ),
      itemCount: products.length,
      itemBuilder: (_, i) => _ProductCard(product: products[i], cart: cart),
    );
  }
}

class _ProductCard extends ConsumerWidget {
  final Map<String, dynamic> product;
  final List cart;
  const _ProductCard({required this.product, required this.cart});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final id = _toI(product['id']);
    final name = product['name']?.toString() ?? '';
    final price = _toD(product['price']);
    final salePrice = product['sale_price'] != null ? _toD(product['sale_price']) : null;
    final displayPrice = salePrice ?? price;
    final inCart = cart.any((c) => c.productId == id);

    return GestureDetector(
      onTap: () => context.push('/eshop/products/$id'),
      child: Container(
        decoration: BoxDecoration(
          color: context.surface,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 8, offset: const Offset(0, 2))],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
              child: AspectRatio(
                aspectRatio: 1.1,
                child: NetImage(url: product['thumbnail']?.toString(), fit: BoxFit.cover),
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name, maxLines: 2, overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                    const Spacer(),
                    Row(
                      children: [
                        Text('\$${displayPrice.toStringAsFixed(2)}',
                            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: Color(0xFFFF8A00))),
                        if (salePrice != null && price > salePrice) ...[
                          const SizedBox(width: 4),
                          Text('\$${price.toStringAsFixed(2)}',
                              style: const TextStyle(fontSize: 10, color: AppColors.textGrey,
                                  decoration: TextDecoration.lineThrough)),
                        ],
                        const Spacer(),
                        GestureDetector(
                          onTap: () {
                            ref.read(eshopCartProvider.notifier).add(product, 1);
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(content: Text('$name added to cart'), duration: const Duration(seconds: 1)),
                            );
                          },
                          child: Container(
                            width: 28,
                            height: 28,
                            decoration: BoxDecoration(
                              color: inCart ? const Color(0xFFFF8A00) : const Color(0xFFFF8A00).withOpacity(0.1),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Icon(
                              inCart ? Icons.check : Icons.add,
                              size: 16,
                              color: inCart ? Colors.white : const Color(0xFFFF8A00),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ReviewsTab extends StatelessWidget {
  final Map<String, dynamic> reviewStats;
  final List<Map<String, dynamic>> reviews;
  const _ReviewsTab({required this.reviewStats, required this.reviews});

  @override
  Widget build(BuildContext context) {
    final avg = _toD(reviewStats['average']);
    final total = _toI(reviewStats['total']);
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (total > 0) ...[
          _RatingSummary(avg: avg, total: total, stats: reviewStats),
          const SizedBox(height: 16),
        ],
        if (reviews.isEmpty)
          const Center(child: Padding(
            padding: EdgeInsets.symmetric(vertical: 40),
            child: Text('No reviews yet', style: TextStyle(color: AppColors.textGrey)),
          ))
        else
          ...reviews.map((r) => _ReviewCard(review: r)),
      ],
    );
  }
}

class _RatingSummary extends StatelessWidget {
  final double avg;
  final int total;
  final Map<String, dynamic> stats;
  const _RatingSummary({required this.avg, required this.total, required this.stats});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.surface,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 8)],
      ),
      child: Row(
        children: [
          Column(children: [
            Text(avg.toStringAsFixed(1),
                style: const TextStyle(fontSize: 40, fontWeight: FontWeight.w900, color: Color(0xFFFF8A00))),
            _star(avg, total),
          ]),
          const SizedBox(width: 20),
          Expanded(
            child: Column(
              children: List.generate(5, (i) {
                final star = 5 - i;
                final count = _toI(stats['star_$star']);
                final pct = total > 0 ? count / total : 0.0;
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 2),
                  child: Row(children: [
                    Text('$star', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600)),
                    const Icon(Icons.star, size: 11, color: Color(0xFFFFC107)),
                    const SizedBox(width: 6),
                    Expanded(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(4),
                        child: LinearProgressIndicator(
                          value: pct.toDouble(),
                          minHeight: 7,
                          backgroundColor: Colors.grey[200],
                          color: const Color(0xFFFFC107),
                        ),
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text('$count', style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
                  ]),
                );
              }),
            ),
          ),
        ],
      ),
    );
  }
}

class _ReviewCard extends StatelessWidget {
  final Map<String, dynamic> review;
  const _ReviewCard({required this.review});

  @override
  Widget build(BuildContext context) {
    final user = _asMap(review['user']);
    final name = user['name']?.toString() ?? 'User';
    final rating = _toI(review['rating']);
    final comment = review['comment']?.toString() ?? '';
    final date = review['created_at']?.toString() ?? '';

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: context.surface,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 6)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            CircleAvatar(
              radius: 16,
              backgroundColor: Colors.grey[200],
              child: Text(name.isNotEmpty ? name[0].toUpperCase() : '?',
                  style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
            ),
            const SizedBox(width: 10),
            Expanded(child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                Row(children: List.generate(5, (i) => Icon(
                  i < rating ? Icons.star : Icons.star_border,
                  size: 12, color: const Color(0xFFFFC107),
                ))),
              ],
            )),
            Text(date.length > 10 ? date.substring(0, 10) : date,
                style: const TextStyle(fontSize: 10, color: AppColors.textGrey)),
          ]),
          if (comment.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(comment, style: const TextStyle(fontSize: 13, height: 1.4)),
          ],
        ],
      ),
    );
  }
}
