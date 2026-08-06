import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/global_provider.dart';
import '../widgets/global_product_card.dart';

class GlobalProductsScreen extends ConsumerStatefulWidget {
  final int? categoryId;
  final String? query;

  const GlobalProductsScreen({super.key, this.categoryId, this.query});

  @override
  ConsumerState<GlobalProductsScreen> createState() =>
      _GlobalProductsScreenState();
}

class _GlobalProductsScreenState extends ConsumerState<GlobalProductsScreen> {
  late String? _sort;
  late int? _categoryId;
  final _searchCtrl = TextEditingController();
  String? _searchQ;
  final _scrollCtrl = ScrollController();

  @override
  void initState() {
    super.initState();
    _categoryId = widget.categoryId;
    _searchQ = widget.query;
    _scrollCtrl.addListener(_onScroll);
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  GlobalProductsParams get _params => GlobalProductsParams(
        q: _searchQ,
        categoryId: _categoryId,
        sort: _sort,
      );

  void _onScroll() {
    if (_scrollCtrl.position.pixels >=
        _scrollCtrl.position.maxScrollExtent - 200) {
      ref.read(globalProductsProvider(_params).notifier).loadMore();
    }
  }

  @override
  Widget build(BuildContext context) {
    final productsAsync = ref.watch(globalProductsProvider(_params));
    final categories = ref.watch(globalCategoriesProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF0F2F5),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1A1A2E),
        title: _categoryId != null
            ? categories.when(
                data: (cats) {
                  final cat = cats.where((c) => c.id == _categoryId).firstOrNull;
                  return Text(cat?.name ?? 'Products',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700));
                },
                loading: () =>
                    const Text('Products', style: TextStyle(color: Colors.white)),
                error: (_, __) =>
                    const Text('Products', style: TextStyle(color: Colors.white)),
              )
            : const Text('Global Store',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
        actions: [
          IconButton(
            icon: const Icon(Icons.shopping_cart_outlined, color: Colors.white),
            onPressed: () => context.push('/global/cart'),
          ),
        ],
      ),
      body: Column(
        children: [
          // Search + Filter bar
          Container(
            color: Colors.white,
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
            child: Row(children: [
              Expanded(
                child: TextField(
                  controller: _searchCtrl,
                  onSubmitted: (v) =>
                      setState(() => _searchQ = v.isEmpty ? null : v),
                  decoration: InputDecoration(
                    hintText: 'Search products...',
                    prefixIcon: const Icon(Icons.search, size: 18),
                    suffixIcon: _searchQ != null
                        ? IconButton(
                            icon: const Icon(Icons.clear, size: 18),
                            onPressed: () {
                              _searchCtrl.clear();
                              setState(() => _searchQ = null);
                            })
                        : null,
                    filled: true,
                    fillColor: const Color(0xFFF0F2F5),
                    contentPadding: EdgeInsets.zero,
                    border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(10),
                        borderSide: BorderSide.none),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              _FilterButton(
                sort: _sort,
                onChanged: (s) => setState(() => _sort = s),
              ),
            ]),
          ),

          // Category horizontal scroll
          categories.when(
            data: (cats) => cats.isEmpty
                ? const SizedBox()
                : SizedBox(
                    height: 42,
                    child: ListView.builder(
                      scrollDirection: Axis.horizontal,
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      itemCount: cats.length + 1,
                      itemBuilder: (_, i) {
                        if (i == 0) {
                          return _CatChip(
                            label: 'All',
                            selected: _categoryId == null,
                            onTap: () => setState(() => _categoryId = null),
                          );
                        }
                        final cat = cats[i - 1];
                        return _CatChip(
                          label: cat.name,
                          selected: _categoryId == cat.id,
                          onTap: () =>
                              setState(() => _categoryId = cat.id),
                        );
                      },
                    ),
                  ),
            loading: () => const SizedBox(),
            error: (_, __) => const SizedBox(),
          ),

          // Product grid
          Expanded(
            child: productsAsync.when(
              data: (products) {
                if (products.isEmpty) {
                  return const Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.search_off,
                            size: 60, color: Colors.grey),
                        SizedBox(height: 12),
                        Text('No products found',
                            style: TextStyle(
                                color: Colors.grey, fontSize: 14)),
                      ],
                    ),
                  );
                }

                final notifier =
                    ref.read(globalProductsProvider(_params).notifier);

                return GridView.builder(
                  controller: _scrollCtrl,
                  padding: const EdgeInsets.fromLTRB(12, 12, 12, 80),
                  gridDelegate:
                      const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: 10,
                    crossAxisSpacing: 10,
                    childAspectRatio: 0.7,
                  ),
                  itemCount:
                      products.length + (notifier.hasMore ? 1 : 0),
                  itemBuilder: (_, i) {
                    if (i >= products.length) {
                      return const Center(
                          child: Padding(
                        padding: EdgeInsets.all(16),
                        child: CircularProgressIndicator(),
                      ));
                    }
                    return GlobalProductCard(product: products[i]);
                  },
                );
              },
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => Center(
                  child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.error_outline,
                      size: 48, color: Colors.grey),
                  const SizedBox(height: 12),
                  Text(e.toString(),
                      style: const TextStyle(color: Colors.grey)),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: () =>
                        ref.invalidate(globalProductsProvider(_params)),
                    child: const Text('Retry'),
                  ),
                ],
              )),
            ),
          ),
        ],
      ),
    );
  }
}

class _CatChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _CatChip(
      {required this.label,
      required this.selected,
      required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(right: 8, top: 6, bottom: 6),
        padding: const EdgeInsets.symmetric(horizontal: 14),
        decoration: BoxDecoration(
          color: selected ? const Color(0xFF1A1A2E) : Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
              color: selected
                  ? const Color(0xFF1A1A2E)
                  : Colors.grey.shade300),
        ),
        child: Center(
          child: Text(label,
              style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color:
                      selected ? Colors.white : Colors.grey.shade700)),
        ),
      ),
    );
  }
}

class _FilterButton extends StatelessWidget {
  final String? sort;
  final ValueChanged<String?> onChanged;

  const _FilterButton({this.sort, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => showModalBottomSheet(
        context: context,
        builder: (_) => _SortSheet(current: sort, onChanged: onChanged),
      ),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: sort != null
              ? const Color(0xFF1A1A2E)
              : const Color(0xFFF0F2F5),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(children: [
          Icon(Icons.tune,
              size: 16,
              color: sort != null ? Colors.white : Colors.grey.shade600),
          const SizedBox(width: 4),
          Text('Sort',
              style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color:
                      sort != null ? Colors.white : Colors.grey.shade600)),
        ]),
      ),
    );
  }
}

class _SortSheet extends StatelessWidget {
  final String? current;
  final ValueChanged<String?> onChanged;

  const _SortSheet({this.current, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    final options = [
      (null, 'Newest First'),
      ('popular', 'Most Popular'),
      ('price_asc', 'Price: Low to High'),
      ('price_desc', 'Price: High to Low'),
    ];

    return SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Padding(
            padding: EdgeInsets.all(16),
            child: Text('Sort By',
                style:
                    TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          ),
          const Divider(height: 1),
          ...options.map((o) => ListTile(
                title: Text(o.$2),
                trailing: current == o.$1
                    ? const Icon(Icons.check,
                        color: Color(0xFF1A1A2E))
                    : null,
                onTap: () {
                  onChanged(o.$1);
                  Navigator.pop(context);
                },
              )),
          const SizedBox(height: 8),
        ],
      ),
    );
  }
}
