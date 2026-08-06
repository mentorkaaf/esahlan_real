import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/global_provider.dart';
import '../../data/models/global_models.dart';
import '../widgets/global_product_card.dart';

class GlobalSearchScreen extends ConsumerStatefulWidget {
  const GlobalSearchScreen({super.key});

  @override
  ConsumerState<GlobalSearchScreen> createState() => _GlobalSearchScreenState();
}

class _GlobalSearchScreenState extends ConsumerState<GlobalSearchScreen> {
  final _ctrl = TextEditingController();
  final _focus = FocusNode();

  String _query = '';
  int? _selCategory;
  String _sort = 'newest';

  static const _sorts = [
    ('newest',    'Newest'),
    ('popular',   'Popular'),
    ('price_asc', 'Price ↑'),
    ('price_desc','Price ↓'),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _focus.requestFocus());
  }

  @override
  void dispose() {
    _ctrl.dispose();
    _focus.dispose();
    super.dispose();
  }

  void _search(String q) {
    if (q.trim() == _query) return;
    setState(() => _query = q.trim());
  }

  @override
  Widget build(BuildContext context) {
    final categoriesAsync = ref.watch(globalCategoriesProvider);

    final params = GlobalProductsParams(
      q: _query.isEmpty ? null : _query,
      categoryId: _selCategory,
      sort: _sort,
    );
    final resultsAsync = _query.isNotEmpty || _selCategory != null
        ? ref.watch(globalProductsProvider(params))
        : null;

    return Scaffold(
      backgroundColor: const Color(0xFFF0F2F5),
      appBar: AppBar(
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF1A1A2E),
        elevation: 0,
        titleSpacing: 0,
        leadingWidth: 48,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20),
          onPressed: () => context.pop(),
        ),
        title: TextField(
          controller: _ctrl,
          focusNode: _focus,
          onSubmitted: _search,
          onChanged: (v) {
            if (v.isEmpty) setState(() => _query = '');
          },
          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500),
          decoration: InputDecoration(
            hintText: 'Search products...',
            hintStyle: TextStyle(color: Colors.grey.shade400, fontSize: 15),
            border: InputBorder.none,
            suffixIcon: _ctrl.text.isNotEmpty
                ? IconButton(
                    icon: const Icon(Icons.clear_rounded, size: 18),
                    onPressed: () {
                      _ctrl.clear();
                      setState(() => _query = '');
                    },
                  )
                : null,
          ),
          textInputAction: TextInputAction.search,
        ),
        actions: [
          TextButton(
            onPressed: () => _search(_ctrl.text),
            child: const Text('Search',
                style: TextStyle(
                    color: Color(0xFFF59E0B), fontWeight: FontWeight.w800)),
          ),
        ],
      ),
      body: Column(
        children: [
          // ── Filter bar ──────────────────────────────────────────────────
          Container(
            color: Colors.white,
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Categories
                categoriesAsync.when(
                  data: (cats) => SizedBox(
                    height: 36,
                    child: ListView(
                      scrollDirection: Axis.horizontal,
                      children: [
                        _Chip(
                          label: 'All',
                          selected: _selCategory == null,
                          onTap: () => setState(() => _selCategory = null),
                        ),
                        ...cats.map((c) => _Chip(
                              label: c.name,
                              selected: _selCategory == c.id,
                              onTap: () => setState(() => _selCategory == c.id
                                  ? _selCategory = null
                                  : _selCategory = c.id),
                            )),
                      ],
                    ),
                  ),
                  loading: () => const SizedBox(height: 36),
                  error: (_, __) => const SizedBox(height: 36),
                ),
                const SizedBox(height: 8),
                // Sort
                SizedBox(
                  height: 32,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    children: _sorts.map((s) => _SortChip(
                          label: s.$2,
                          selected: _sort == s.$1,
                          onTap: () => setState(() => _sort = s.$1),
                        )).toList(),
                  ),
                ),
              ],
            ),
          ),

          // ── Results ─────────────────────────────────────────────────────
          Expanded(
            child: resultsAsync == null
                ? _EmptySearch()
                : resultsAsync.when(
                    data: (products) => products.isEmpty
                        ? _NoResults(query: _query)
                        : GridView.builder(
                            padding: const EdgeInsets.all(12),
                            gridDelegate:
                                const SliverGridDelegateWithFixedCrossAxisCount(
                              crossAxisCount: 2,
                              childAspectRatio: 0.72,
                              crossAxisSpacing: 10,
                              mainAxisSpacing: 10,
                            ),
                            itemCount: products.length,
                            itemBuilder: (_, i) =>
                                GlobalProductCard(product: products[i]),
                          ),
                    loading: () =>
                        const Center(child: CircularProgressIndicator()),
                    error: (e, _) => Center(
                      child: Text(e.toString(),
                          style: const TextStyle(color: Colors.grey)),
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;
  const _Chip(
      {required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        margin: const EdgeInsets.only(right: 8),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        decoration: BoxDecoration(
          color:
              selected ? const Color(0xFF1A1A2E) : const Color(0xFFF0F2F5),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w700,
            color: selected ? Colors.white : Colors.grey.shade600,
          ),
        ),
      ),
    );
  }
}

class _SortChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;
  const _SortChip(
      {required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        margin: const EdgeInsets.only(right: 6),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
        decoration: BoxDecoration(
          color: selected
              ? const Color(0xFFF59E0B)
              : const Color(0xFFF0F2F5),
          borderRadius: BorderRadius.circular(20),
          border: selected
              ? null
              : Border.all(color: Colors.grey.shade200),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w700,
            color: selected ? Colors.white : Colors.grey.shade600,
          ),
        ),
      ),
    );
  }
}

class _EmptySearch extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text('🔍', style: TextStyle(fontSize: 48)),
          const SizedBox(height: 16),
          const Text('Search for anything',
              style: TextStyle(
                  fontSize: 16, fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          Text('Type a product name, category, or keyword',
              style: TextStyle(color: Colors.grey.shade500, fontSize: 13)),
        ],
      ),
    );
  }
}

class _NoResults extends StatelessWidget {
  final String query;
  const _NoResults({required this.query});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text('🤔', style: TextStyle(fontSize: 48)),
          const SizedBox(height: 16),
          Text('No results for "$query"',
              style: const TextStyle(
                  fontSize: 16, fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          Text('Try different keywords or browse categories',
              style: TextStyle(color: Colors.grey.shade500, fontSize: 13)),
        ],
      ),
    );
  }
}
