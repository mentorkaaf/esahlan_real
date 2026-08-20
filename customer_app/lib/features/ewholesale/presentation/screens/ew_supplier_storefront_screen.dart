import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/ew_provider.dart';
import '../ui/ew_theme.dart';
import '../ui/widgets/ew_widgets.dart';
import '../../data/models/ew_models.dart';

class EwSupplierStorefrontScreen extends ConsumerWidget {
  final int supplierId;
  const EwSupplierStorefrontScreen({super.key, required this.supplierId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final supplierAsync = ref.watch(ewSupplierProvider(supplierId));

    return Scaffold(
      backgroundColor: EwTheme.bg,
      body: supplierAsync.when(
        loading: () => _buildSkeleton(),
        error:   (e, _) => Scaffold(
          appBar: AppBar(backgroundColor: EwTheme.navy, foregroundColor: Colors.white),
          body: EwErrorRetry(error: e, onRetry: () => ref.refresh(ewSupplierProvider(supplierId))),
        ),
        data:    (supplier) => _buildContent(context, ref, supplier),
      ),
    );
  }

  Widget _buildContent(BuildContext context, WidgetRef ref, EwSupplierCard supplier) {
    return CustomScrollView(slivers: [
      // ── Banner / header ──────────────────────────────────────────────────
      SliverAppBar(
        pinned: true,
        expandedHeight: supplier.banner != null ? 180 : 100,
        backgroundColor: EwTheme.navy,
        foregroundColor: Colors.white,
        flexibleSpace: FlexibleSpaceBar(
          background: supplier.banner != null
            ? Stack(fit: StackFit.expand, children: [
                Image.network(supplier.banner!, fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => Container(color: EwTheme.navy)),
                Container(color: EwTheme.navy.withOpacity(0.5)),
              ])
            : Container(color: EwTheme.navy),
        ),
      ),

      // ── Profile card ─────────────────────────────────────────────────────
      SliverToBoxAdapter(child: Container(
        color: EwTheme.surface,
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            CircleAvatar(
              radius: 32,
              backgroundColor: EwTheme.navy.withOpacity(0.08),
              backgroundImage: supplier.logo != null ? NetworkImage(supplier.logo!) : null,
              child: supplier.logo == null
                ? Text(supplier.displayName.isNotEmpty ? supplier.displayName[0] : '?',
                    style: const TextStyle(color: EwTheme.navy, fontSize: 22, fontWeight: FontWeight.w700))
                : null,
            ),
            const SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(supplier.displayName, style: EwTheme.heading2),
              const SizedBox(height: 4),
              Row(children: [
                SupplierBadge(verification: supplier.verification),
                const SizedBox(width: 8),
                Icon(Icons.star, size: 14, color: EwTheme.amber),
                Text(' ${supplier.rating.toStringAsFixed(1)}', style: EwTheme.bodySmall.copyWith(fontWeight: FontWeight.w600)),
              ]),
            ])),
          ]),
          if (supplier.about != null) ...[
            const SizedBox(height: 12),
            Text(supplier.about!, style: EwTheme.body),
          ],
          const SizedBox(height: 16),
          // Metrics row
          Row(children: [
            _metric('${supplier.totalOrders}', 'Orders'),
            _divider(),
            _metric('${supplier.responseRate.toInt()}%', 'Response Rate'),
            _divider(),
            _metric('${supplier.onTimeDeliveryRate.toInt()}%', 'On-time'),
          ]),
        ]),
      )),

      // ── Products ─────────────────────────────────────────────────────────
      const SliverToBoxAdapter(child: SizedBox(height: 12)),
      SliverToBoxAdapter(child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: EwSectionHeader(
          title: 'Products',
          actionLabel: 'All products',
          onAction: () => context.push('/ewholesale/products?supplier=$supplierId'),
        ),
      )),
      const SliverToBoxAdapter(child: SizedBox(height: 12)),
      _SupplierProductGrid(supplierId: supplierId),
      const SliverToBoxAdapter(child: SizedBox(height: 40)),
    ]);
  }

  Widget _buildSkeleton() {
    return Scaffold(
      body: Column(children: [
        const ShimmerBox.fill(height: 180, borderRadius: BorderRadius.zero),
        const SizedBox(height: 16),
        Padding(padding: const EdgeInsets.symmetric(horizontal: 16), child: Column(children: [
          Row(children: [const ShimmerBox(width: 64, height: 64), const SizedBox(width: 14), Expanded(child: Column(children: [
            const ShimmerBox.fill(height: 18), const SizedBox(height: 6), const ShimmerBox.fill(height: 14),
          ]))]),
        ])),
      ]),
    );
  }

  Widget _metric(String value, String label) => Expanded(child: Column(children: [
    Text(value, style: EwTheme.heading2),
    Text(label, style: EwTheme.bodySmall),
  ]));

  Widget _divider() => Container(width: 1, height: 32, color: EwTheme.border, margin: const EdgeInsets.symmetric(horizontal: 8));
}

class _SupplierProductGrid extends ConsumerWidget {
  final int supplierId;
  const _SupplierProductGrid({required this.supplierId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // Use product listing provider filtered by supplier
    final productsAsync = ref.watch(FutureProvider.autoDispose<List<EwProduct>>((r) async {
      final result = await r.read(ewRepoProvider).getProducts(supplierId: supplierId.toString());
      return result.products.take(6).toList();
    }));

    return productsAsync.when(
      loading: () => SliverToBoxAdapter(child: SizedBox(height: 200, child: const Center(child: CircularProgressIndicator(color: EwTheme.orange)))),
      error: (e, _) => const SliverToBoxAdapter(child: SizedBox.shrink()),
      data: (products) => SliverPadding(
        padding: const EdgeInsets.symmetric(horizontal: 12),
        sliver: SliverGrid(
          delegate: SliverChildBuilderDelegate(
            (ctx, i) => WholesaleProductCard(
              product: products[i],
              onTap: () => ctx.push('/ewholesale/product/${products[i].slug}'),
            ),
            childCount: products.length,
          ),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2, childAspectRatio: 0.72,
            crossAxisSpacing: 10, mainAxisSpacing: 10,
          ),
        ),
      ),
    );
  }
}
