import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../ui/eg_theme.dart';
import '../../ui/eg_widgets.dart';
import '../providers/egrocery_providers.dart';

class EGProductListScreen extends ConsumerStatefulWidget {
  final int? categoryId;
  final String? title;
  const EGProductListScreen({super.key, this.categoryId, this.title});

  @override
  ConsumerState<EGProductListScreen> createState() => _EGProductListScreenState();
}

class _EGProductListScreenState extends ConsumerState<EGProductListScreen> {
  final _scroll = ScrollController();
  bool _gridView = true;
  String? _sort;
  int? _subCatId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
    _scroll.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  void _load({bool reset = true}) {
    final filter = EGProductFilter(
      categoryId: _subCatId ?? widget.categoryId,
      sort: _sort,
    );
    if (reset) {
      ref.read(egProductListProvider.notifier).load(filter);
    }
  }

  void _onScroll() {
    if (_scroll.position.pixels >= _scroll.position.maxScrollExtent - 200) {
      ref.read(egProductListProvider.notifier).loadMore();
    }
  }

  @override
  Widget build(BuildContext context) {
    final stateAsync = ref.watch(egProductListProvider);

    return Scaffold(
      backgroundColor: EGTheme.bg,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: Text(widget.title ?? 'Products', style: const TextStyle(color: EGTheme.textDark, fontWeight: FontWeight.w800, fontSize: 17)),
        iconTheme: const IconThemeData(color: EGTheme.textDark),
        actions: [
          IconButton(
            onPressed: () => setState(() => _gridView = !_gridView),
            icon: Icon(_gridView ? Icons.view_list : Icons.grid_view, color: EGTheme.textDark),
          ),
          PopupMenuButton<String>(
            icon: const Icon(Icons.sort, color: EGTheme.textDark),
            onSelected: (v) { setState(() => _sort = v); _load(); },
            itemBuilder: (_) => const [
              PopupMenuItem(value: 'popular', child: Text('Most Popular')),
              PopupMenuItem(value: 'newest', child: Text('Newest')),
              PopupMenuItem(value: 'price_asc', child: Text('Price: Low → High')),
              PopupMenuItem(value: 'price_desc', child: Text('Price: High → Low')),
              PopupMenuItem(value: 'rating', child: Text('Best Rating')),
            ],
          ),
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: EGCartBadge(onTap: () => context.push('/egrocery/cart')),
          ),
        ],
      ),
      body: stateAsync.when(
        loading: () => _buildSkeleton(),
        error: (e, _) => EGEmptyState(emoji: '😕', title: 'Error', subtitle: e.toString(), onRetry: _load),
        data: (state) {
          if (state.isLoading) return _buildSkeleton();

          // Sub-category chips
          final catDetailAsync = widget.categoryId != null
              ? ref.watch(egCategoryDetailProvider(widget.categoryId!))
              : null;

          return Column(children: [
            // Sub-category chips
            if (catDetailAsync != null)
              catDetailAsync.when(
                loading: () => const SizedBox(),
                error: (_, __) => const SizedBox(),
                data: (cat) => cat.children.isEmpty ? const SizedBox() : SizedBox(
                  height: 44,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    children: [
                      _Chip(label: 'All', selected: _subCatId == null, onTap: () { setState(() => _subCatId = null); _load(); }),
                      ...cat.children.map((c) => _Chip(
                        label: c.name,
                        selected: _subCatId == c.id,
                        onTap: () { setState(() => _subCatId = c.id); _load(); },
                      )),
                    ],
                  ),
                ),
              ),

            // Product grid/list
            Expanded(
              child: state.products.isEmpty
                  ? EGEmptyState(emoji: '🛒', title: 'No products found', onRetry: _load)
                  : _gridView
                      ? GridView.builder(
                          controller: _scroll,
                          padding: const EdgeInsets.all(12),
                          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            crossAxisSpacing: 12,
                            mainAxisSpacing: 12,
                            childAspectRatio: 0.72,
                          ),
                          itemCount: state.products.length + (state.isLoadingMore ? 2 : 0),
                          itemBuilder: (_, i) {
                            if (i >= state.products.length) return EGShimmerBox(width: 160, height: 220, radius: EGTheme.rCard);
                            final p = state.products[i];
                            return EGProductCard(
                              product: p,
                              width: double.infinity,
                              onTap: () => context.push('/egrocery/product/${p.slug}'),
                            );
                          },
                        )
                      : ListView.builder(
                          controller: _scroll,
                          padding: const EdgeInsets.all(12),
                          itemCount: state.products.length + (state.isLoadingMore ? 1 : 0),
                          itemBuilder: (_, i) {
                            if (i >= state.products.length) return const Padding(padding: EdgeInsets.all(8), child: Center(child: CircularProgressIndicator(color: EGTheme.orange)));
                            final p = state.products[i];
                            return _ListTileCard(
                              product: p,
                              onTap: () => context.push('/egrocery/product/${p.slug}'),
                            );
                          },
                        ),
            ),
          ]);
        },
      ),
    );
  }

  Widget _buildSkeleton() => GridView.builder(
        padding: const EdgeInsets.all(12),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, crossAxisSpacing: 12, mainAxisSpacing: 12, childAspectRatio: 0.72),
        itemCount: 6,
        itemBuilder: (_, __) => EGShimmerBox(width: 160, height: 220, radius: EGTheme.rCard),
      );
}

class _Chip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;
  const _Chip({required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
        onTap: onTap,
        child: Container(
          margin: const EdgeInsets.only(right: 8),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
          decoration: BoxDecoration(
            color: selected ? EGTheme.orange : EGTheme.shimmer.withOpacity(0.5),
            borderRadius: BorderRadius.circular(EGTheme.rChip),
          ),
          child: Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: selected ? Colors.white : EGTheme.textDark)),
        ),
      );
}

class _ListTileCard extends ConsumerWidget {
  final dynamic product;
  final VoidCallback onTap;
  const _ListTileCard({required this.product, required this.onTap});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final v = product.defaultVariant;
    if (v == null) return const SizedBox();
    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(EGTheme.rCard)),
        child: Row(children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: Image.network(product.image ?? '', width: 72, height: 72, fit: BoxFit.cover, errorBuilder: (_, __, ___) => Container(width: 72, height: 72, color: EGTheme.shimmer)),
          ),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(product.name, style: EGTheme.productName, maxLines: 2),
            const SizedBox(height: 4),
            Text(v.label, style: EGTheme.caption),
            const SizedBox(height: 6),
            EGPricePair(variant: v),
          ])),
          EGQtyStepper(
            qty: ref.watch(egCartProvider).firstWhere((l) => l.variantId == v.id, orElse: () => EGCartLine(variantId: -1, qty: 0)).qty,
            onChanged: (q) {
              if (q == 0) ref.read(egCartProvider.notifier).remove(v.id);
              else ref.read(egCartProvider.notifier).setQty(v.id, q);
            },
          ),
        ]),
      ),
    );
  }
}
