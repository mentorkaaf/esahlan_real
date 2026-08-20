import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../data/models/ew_models.dart';
import '../../data/repositories/ewholesale_repository.dart';
import '../providers/ew_provider.dart';
import '../ui/ew_theme.dart';
import '../ui/widgets/ew_widgets.dart';

// ── Provider ──────────────────────────────────────────────────────────────────

final _ewSearchProvider = FutureProvider.family.autoDispose<Map<String, dynamic>, String>(
  (ref, q) => q.isEmpty
      ? Future.value({'products': <EwProduct>[], 'suppliers': <EwSupplierCard>[]})
      : ref.watch(ewRepoProvider).search(q),
);

// ── Screen ────────────────────────────────────────────────────────────────────

class EwSearchScreen extends ConsumerStatefulWidget {
  final String? initialQuery;
  const EwSearchScreen({super.key, this.initialQuery});

  @override
  ConsumerState<EwSearchScreen> createState() => _EwSearchScreenState();
}

class _EwSearchScreenState extends ConsumerState<EwSearchScreen> with SingleTickerProviderStateMixin {
  late final TextEditingController _ctrl;
  late final FocusNode _focus;
  String _query = '';
  late TabController _tab;

  @override
  void initState() {
    super.initState();
    _query = widget.initialQuery ?? '';
    _ctrl  = TextEditingController(text: _query);
    _focus = FocusNode();
    _tab   = TabController(length: 2, vsync: this);
    if (_query.isEmpty) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _focus.requestFocus());
    }
  }

  @override
  void dispose() {
    _ctrl.dispose();
    _focus.dispose();
    _tab.dispose();
    super.dispose();
  }

  void _submit(String v) {
    final q = v.trim();
    if (q == _query) return;
    setState(() => _query = q);
  }

  @override
  Widget build(BuildContext context) {
    final isDark  = Theme.of(context).brightness == Brightness.dark;
    final surface = isDark ? EwTheme.surface : Colors.white;

    return Scaffold(
      backgroundColor: EwTheme.bg,
      appBar: AppBar(
        backgroundColor: EwTheme.navy,
        foregroundColor: Colors.white,
        titleSpacing: 0,
        title: TextField(
          controller: _ctrl,
          focusNode: _focus,
          textInputAction: TextInputAction.search,
          style: const TextStyle(color: Colors.white, fontSize: 15),
          cursorColor: EwTheme.orange,
          decoration: InputDecoration(
            hintText: 'Search products, suppliers…',
            hintStyle: const TextStyle(color: Colors.white54, fontSize: 15),
            border: InputBorder.none,
            suffixIcon: _query.isNotEmpty
                ? IconButton(
                    icon: const Icon(Icons.close, color: Colors.white54, size: 20),
                    onPressed: () { _ctrl.clear(); setState(() => _query = ''); },
                  )
                : null,
          ),
          onSubmitted: _submit,
          onChanged: (v) { if (v.trim().length >= 2) _submit(v); },
        ),
        bottom: TabBar(
          controller: _tab,
          labelColor: EwTheme.orange,
          unselectedLabelColor: Colors.white60,
          indicatorColor: EwTheme.orange,
          tabs: const [Tab(text: 'Products'), Tab(text: 'Suppliers')],
        ),
      ),
      body: _query.isEmpty
          ? _EmptyPrompt()
          : ref.watch(_ewSearchProvider(_query)).when(
              loading: () => const Center(child: CircularProgressIndicator(color: EwTheme.orange)),
              error:   (e, _) => EwErrorRetry(error: e, onRetry: () => ref.refresh(_ewSearchProvider(_query).future)),
              data:    (data) {
                final products  = data['products']  as List<EwProduct>;
                final suppliers = data['suppliers'] as List<EwSupplierCard>;
                return TabBarView(
                  controller: _tab,
                  children: [
                    _ProductResults(products: products, query: _query),
                    _SupplierResults(suppliers: suppliers, query: _query),
                  ],
                );
              },
            ),
    );
  }
}

// ── Empty Prompt ──────────────────────────────────────────────────────────────

class _EmptyPrompt extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Center(
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      const Icon(Icons.search, size: 64, color: Colors.grey),
      const SizedBox(height: 16),
      Text('Search for products or suppliers', style: EwTheme.bodySmall),
    ]),
  );
}

// ── Product Results ───────────────────────────────────────────────────────────

class _ProductResults extends StatelessWidget {
  final List<EwProduct> products;
  final String query;
  const _ProductResults({required this.products, required this.query});

  @override
  Widget build(BuildContext context) {
    if (products.isEmpty) {
      return const EwEmptyState(icon: Icons.search_off, title: 'No products found',
        subtitle: 'Try a different keyword or browse by category');
    }
    return ListView.separated(
      padding: const EdgeInsets.all(12),
      itemCount: products.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (ctx, i) {
        final p = products[i];
        return GestureDetector(
          onTap: () => ctx.push('/ewholesale/product/${p.slug}'),
          child: Container(
            decoration: BoxDecoration(
              color: EwTheme.surface,
              borderRadius: EwTheme.radius12,
              border: Border.all(color: EwTheme.border),
            ),
            child: Row(children: [
              // Image
              ClipRRect(
                borderRadius: const BorderRadius.horizontal(left: Radius.circular(12)),
                child: p.images.isNotEmpty
                    ? Image.network(p.images.first, width: 80, height: 80, fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => _placeholder())
                    : _placeholder(),
              ),
              const SizedBox(width: 12),
              Expanded(child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 4),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(p.name, style: EwTheme.body.copyWith(fontWeight: FontWeight.w700), maxLines: 2, overflow: TextOverflow.ellipsis),
                  if (p.supplier != null)
                    Text(p.supplier!.displayName, style: EwTheme.bodySmall),
                  const SizedBox(height: 4),
                  Text(
                    'From ${EwTheme.formatPrice(p.minPrice)} / ${p.unit}',
                    style: EwTheme.price,
                  ),
                  Text('MOQ: ${p.moq.toInt()} ${p.unit}', style: EwTheme.bodySmall),
                ]),
              )),
              const Padding(padding: EdgeInsets.only(right: 12),
                child: Icon(Icons.chevron_right, color: Colors.grey)),
            ]),
          ),
        );
      },
    );
  }

  Widget _placeholder() => Container(width: 80, height: 80,
    color: EwTheme.border, child: const Icon(Icons.inventory_2_outlined, color: Colors.grey));
}

// ── Supplier Results ──────────────────────────────────────────────────────────

class _SupplierResults extends StatelessWidget {
  final List<EwSupplierCard> suppliers;
  final String query;
  const _SupplierResults({required this.suppliers, required this.query});

  @override
  Widget build(BuildContext context) {
    if (suppliers.isEmpty) {
      return const EwEmptyState(icon: Icons.storefront_outlined, title: 'No suppliers found',
        subtitle: 'Try a different keyword');
    }
    return ListView.separated(
      padding: const EdgeInsets.all(12),
      itemCount: suppliers.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (ctx, i) {
        final s = suppliers[i];
        return GestureDetector(
          onTap: () => ctx.push('/ewholesale/supplier/${s.id}'),
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: EwTheme.surface,
              borderRadius: EwTheme.radius12,
              border: Border.all(color: EwTheme.border),
            ),
            child: Row(children: [
              CircleAvatar(
                radius: 24,
                backgroundColor: EwTheme.orange.withValues(alpha: 0.15),
                child: Text(s.displayName.isNotEmpty ? s.displayName[0].toUpperCase() : '?',
                  style: const TextStyle(color: EwTheme.orange, fontWeight: FontWeight.w800, fontSize: 18)),
              ),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(s.displayName, style: EwTheme.body.copyWith(fontWeight: FontWeight.w700)),
                Row(children: [
                  if (s.isGold) ...[
                    const Icon(Icons.star, color: Color(0xFFF59E0B), size: 14),
                    const SizedBox(width: 4),
                    Text('Gold Supplier', style: EwTheme.bodySmall.copyWith(color: const Color(0xFFF59E0B))),
                  ] else if (s.isVerified) ...[
                    const Icon(Icons.verified, color: Color(0xFF10B981), size: 14),
                    const SizedBox(width: 4),
                    Text('Verified', style: EwTheme.bodySmall.copyWith(color: const Color(0xFF10B981))),
                  ],
                ]),
                if (s.rating > 0)
                  Text('★ ${s.rating.toStringAsFixed(1)}', style: EwTheme.bodySmall),
              ])),
              const Icon(Icons.chevron_right, color: Colors.grey),
            ]),
          ),
        );
      },
    );
  }
}
