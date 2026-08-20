import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/ew_provider.dart';
import '../ui/ew_theme.dart';
import '../ui/widgets/ew_widgets.dart';

class EwListingScreen extends ConsumerStatefulWidget {
  final int? categoryId;
  final String? initialSearch;
  final String? initialSort;
  final bool verifiedOnly;

  const EwListingScreen({super.key, this.categoryId, this.initialSearch,
    this.initialSort, this.verifiedOnly = false});

  @override
  ConsumerState<EwListingScreen> createState() => _EwListingScreenState();
}

class _EwListingScreenState extends ConsumerState<EwListingScreen> {
  final _scroll = ScrollController();
  final _searchCtrl = TextEditingController();
  bool _showFilter = false;
  String _sort = 'newest';
  bool _verifiedOnly = false;
  bool _goldOnly = false;
  double _maxMoq = 10000;
  final _moqOptions = [100.0, 500.0, 1000.0, 5000.0, 10000.0];

  @override
  void initState() {
    super.initState();
    _sort         = widget.initialSort ?? 'newest';
    _verifiedOnly = widget.verifiedOnly;
    _searchCtrl.text = widget.initialSearch ?? '';
    _scroll.addListener(_onScroll);

    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(ewProductListProvider.notifier).applyFilter(EwProductFilter(
        categoryId:   widget.categoryId,
        sort:         _sort,
        verifiedOnly: _verifiedOnly,
        search:       widget.initialSearch,
      ));
    });
  }

  @override
  void dispose() { _scroll.dispose(); _searchCtrl.dispose(); super.dispose(); }

  void _onScroll() {
    if (_scroll.position.pixels >= _scroll.position.maxScrollExtent - 200) {
      ref.read(ewProductListProvider.notifier).loadMore();
    }
  }

  void _applyFilters() {
    ref.read(ewProductListProvider.notifier).applyFilter(EwProductFilter(
      categoryId:   widget.categoryId,
      sort:         _sort,
      verifiedOnly: _verifiedOnly,
      goldOnly:     _goldOnly,
      maxMoq:       _maxMoq < 10000 ? _maxMoq : null,
      search:       _searchCtrl.text.trim().isEmpty ? null : _searchCtrl.text.trim(),
    ));
    setState(() => _showFilter = false);
  }

  @override
  Widget build(BuildContext context) {
    final listAsync = ref.watch(ewProductListProvider);

    return Scaffold(
      backgroundColor: EwTheme.bg,
      appBar: AppBar(
        backgroundColor: EwTheme.navy,
        foregroundColor: Colors.white,
        title: _SearchBar(
          controller: _searchCtrl,
          onSubmit: (_) => _applyFilters(),
        ),
        actions: [
          IconButton(
            icon: Stack(children: [
              const Icon(Icons.tune, color: Colors.white),
              if (_verifiedOnly || _goldOnly || _maxMoq < 10000)
                Positioned(right: 0, top: 0, child: CircleAvatar(
                  radius: 4, backgroundColor: EwTheme.orange)),
            ]),
            onPressed: () => setState(() => _showFilter = !_showFilter),
          ),
        ],
      ),
      body: Column(children: [
        // ── Sort chips ──────────────────────────────────────────────────────
        _SortChips(selected: _sort, onSelect: (s) { setState(() => _sort = s); _applyFilters(); }),
        // ── Filter sheet (inline) ───────────────────────────────────────────
        if (_showFilter) _buildFilterPanel(),
        // ── Grid ────────────────────────────────────────────────────────────
        Expanded(child: listAsync.when(
          loading: () => _buildSkeleton(),
          error: (e, _) => EwErrorRetry(error: e, onRetry: () =>
            ref.read(ewProductListProvider.notifier).applyFilter(const EwProductFilter())),
          data: (state) {
            if (state.products.isEmpty && !state.isLoading) {
              return EwEmptyState(
                icon: Icons.inventory_2_outlined,
                title: 'No products found',
                subtitle: 'Try adjusting your search or filters',
                actionLabel: 'Clear filters',
                onAction: () {
                  setState(() { _verifiedOnly = false; _goldOnly = false; _maxMoq = 10000; _searchCtrl.clear(); });
                  _applyFilters();
                },
              );
            }
            return GridView.builder(
              controller: _scroll,
              padding: const EdgeInsets.all(12),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                childAspectRatio: 0.72,
                crossAxisSpacing: 10,
                mainAxisSpacing: 10,
              ),
              itemCount: state.products.length + (state.isLoadingMore ? 2 : 0),
              itemBuilder: (_, i) {
                if (i >= state.products.length) return const ShimmerBox.fill(height: 220);
                final p = state.products[i];
                return WholesaleProductCard(
                  product: p,
                  onTap: () => context.push('/ewholesale/product/${p.slug}'),
                );
              },
            );
          },
        )),
      ]),
    );
  }

  Widget _buildFilterPanel() {
    return Container(
      color: EwTheme.surface,
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Filter', style: EwTheme.heading3),
        const SizedBox(height: 12),
        // Supplier verification
        Text('Supplier', style: EwTheme.label),
        const SizedBox(height: 8),
        Row(children: [
          _FilterChip('Verified', _verifiedOnly, () => setState(() { _verifiedOnly = !_verifiedOnly; if (_verifiedOnly) _goldOnly = false; })),
          const SizedBox(width: 8),
          _FilterChip('⭐ Gold only', _goldOnly, () => setState(() { _goldOnly = !_goldOnly; if (_goldOnly) _verifiedOnly = false; })),
        ]),
        const SizedBox(height: 12),
        // Max MOQ slider
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Text('Max MOQ', style: EwTheme.label),
          Text(_maxMoq >= 10000 ? 'Any' : '≤ ${_maxMoq.toInt()} units',
            style: EwTheme.bodySmall.copyWith(color: EwTheme.navy, fontWeight: FontWeight.w600)),
        ]),
        Slider(
          value: _maxMoq,
          min: 100, max: 10000, divisions: 99,
          activeColor: EwTheme.navy,
          onChanged: (v) => setState(() => _maxMoq = v),
        ),
        const SizedBox(height: 8),
        Row(mainAxisAlignment: MainAxisAlignment.end, children: [
          TextButton(onPressed: () => setState(() { _verifiedOnly = false; _goldOnly = false; _maxMoq = 10000; }), child: const Text('Reset')),
          const SizedBox(width: 8),
          ElevatedButton(onPressed: _applyFilters, style: EwTheme.primaryButton.copyWith(minimumSize: const WidgetStatePropertyAll(Size(100, 36))), child: const Text('Apply')),
        ]),
      ]),
    );
  }

  Widget _buildSkeleton() => GridView.builder(
    padding: const EdgeInsets.all(12),
    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
      crossAxisCount: 2, childAspectRatio: 0.72, crossAxisSpacing: 10, mainAxisSpacing: 10),
    itemCount: 6,
    itemBuilder: (_, __) => const ShimmerBox.fill(height: 220),
  );
}

class _SearchBar extends StatelessWidget {
  final TextEditingController controller;
  final ValueChanged<String> onSubmit;

  const _SearchBar({required this.controller, required this.onSubmit});

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      onSubmitted: onSubmit,
      style: const TextStyle(color: Colors.white, fontSize: 14),
      decoration: InputDecoration(
        hintText: 'Search products…',
        hintStyle: TextStyle(color: Colors.white.withOpacity(0.5), fontSize: 14),
        border: InputBorder.none,
        isDense: true,
        prefixIcon: const Icon(Icons.search, color: Colors.white54, size: 18),
      ),
    );
  }
}

class _SortChips extends StatelessWidget {
  final String selected;
  final ValueChanged<String> onSelect;

  const _SortChips({required this.selected, required this.onSelect});

  @override
  Widget build(BuildContext context) {
    const opts = [
      ('newest',      'Newest'),
      ('lowest_price','Lowest Price'),
      ('lowest_moq',  'Lowest MOQ'),
      ('best_selling','Best Selling'),
    ];
    return Container(
      color: EwTheme.surface,
      height: 40,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        children: opts.map((o) {
          final isSelected = o.$1 == selected;
          return GestureDetector(
            onTap: () => onSelect(o.$1),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 150),
              margin: const EdgeInsets.only(right: 8, top: 6, bottom: 6),
              padding: const EdgeInsets.symmetric(horizontal: 12),
              decoration: BoxDecoration(
                color: isSelected ? EwTheme.navy : EwTheme.bg,
                borderRadius: EwTheme.radius16,
                border: Border.all(color: isSelected ? EwTheme.navy : EwTheme.border),
              ),
              child: Center(child: Text(o.$2, style: TextStyle(
                fontSize: 12, fontWeight: FontWeight.w600,
                color: isSelected ? Colors.white : EwTheme.textSecondary,
              ))),
            ),
          );
        }).toList(),
      ),
    );
  }
}

Widget _FilterChip(String label, bool selected, VoidCallback onTap) {
  return GestureDetector(
    onTap: onTap,
    child: AnimatedContainer(
      duration: const Duration(milliseconds: 150),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: selected ? EwTheme.navy : EwTheme.bg,
        borderRadius: EwTheme.radius16,
        border: Border.all(color: selected ? EwTheme.navy : EwTheme.border),
      ),
      child: Text(label, style: TextStyle(
        fontSize: 12, fontWeight: FontWeight.w600,
        color: selected ? Colors.white : EwTheme.textSecondary,
      )),
    ),
  );
}
