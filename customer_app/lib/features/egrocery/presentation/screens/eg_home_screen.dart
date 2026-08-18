import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../data/models/egrocery_models.dart';
import '../../ui/eg_theme.dart';
import '../../ui/eg_widgets.dart';
import '../providers/egrocery_providers.dart';

class EGHomeScreen extends ConsumerWidget {
  const EGHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final homeAsync = ref.watch(egHomeProvider);

    return Scaffold(
      backgroundColor: EGTheme.bg,
      body: homeAsync.when(
        loading: () => _EGHomeSkeleton(),
        error: (e, _) => EGEmptyState(emoji: '😕', title: 'Failed to load', subtitle: e.toString(), onRetry: () => ref.invalidate(egHomeProvider)),
        data: (payload) => _EGHomeBody(payload: payload),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────

class _EGHomeBody extends ConsumerStatefulWidget {
  final EGHomePayload payload;
  const _EGHomeBody({required this.payload});

  @override
  ConsumerState<_EGHomeBody> createState() => _EGHomeBodyState();
}

class _EGHomeBodyState extends ConsumerState<_EGHomeBody> {
  final _searchCtrl = TextEditingController();
  int? _selectedCatId;

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final payload = widget.payload;
    final heroBanners = payload.banners['hero'] ?? payload.banners.values.firstOrNull ?? [];

    return RefreshIndicator(
      color: EGTheme.orange,
      onRefresh: () => ref.refresh(egHomeProvider.future),
      child: CustomScrollView(slivers: [
        // ── SliverAppBar ──────────────────────────────────────────────────────
        SliverAppBar(
          backgroundColor: Colors.white,
          elevation: 0,
          pinned: true,
          floating: true,
          title: Row(children: [
            const Icon(Icons.location_on, color: EGTheme.orange, size: 18),
            const SizedBox(width: 4),
            Text(
              payload.deliveryInfo?.zoneName ?? 'Mogadishu',
              style: const TextStyle(color: EGTheme.textDark, fontSize: 14, fontWeight: FontWeight.w700),
            ),
            const Icon(Icons.keyboard_arrow_down, color: EGTheme.textGrey, size: 18),
            const Spacer(),
            EGCartBadge(onTap: () => context.push('/egrocery/cart')),
            const SizedBox(width: 8),
          ]),
          bottom: PreferredSize(
            preferredSize: const Size.fromHeight(54),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
              child: GestureDetector(
                onTap: () => context.push('/egrocery/search'),
                child: Container(
                  height: 44,
                  decoration: BoxDecoration(color: EGTheme.shimmer.withOpacity(0.5), borderRadius: BorderRadius.circular(EGTheme.rChip)),
                  child: const Row(children: [
                    SizedBox(width: 14),
                    Icon(Icons.search, color: EGTheme.textGrey, size: 20),
                    SizedBox(width: 10),
                    Text('Search groceries...', style: TextStyle(color: EGTheme.textGrey, fontSize: 14)),
                  ]),
                ),
              ),
            ),
          ),
        ),

        SliverToBoxAdapter(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

          // ── Delivery banner ───────────────────────────────────────────────
          if (payload.deliveryInfo?.freeOver != null)
            Container(
              margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              decoration: BoxDecoration(
                color: EGTheme.green.withOpacity(0.1),
                borderRadius: BorderRadius.circular(EGTheme.rCard),
                border: Border.all(color: EGTheme.green.withOpacity(0.3)),
              ),
              child: Row(children: [
                const Icon(Icons.delivery_dining, color: EGTheme.green, size: 20),
                const SizedBox(width: 8),
                Expanded(child: Text(
                  'Free delivery on orders over \$${payload.deliveryInfo!.freeOver!.toStringAsFixed(0)}!',
                  style: const TextStyle(color: EGTheme.green, fontSize: 13, fontWeight: FontWeight.w600),
                )),
              ]),
            ),

          // ── Hero banners ──────────────────────────────────────────────────
          if (heroBanners.isNotEmpty) ...[
            const SizedBox(height: 14),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: EGBannerSlider(banners: heroBanners),
            ),
          ],

          // ── Category grid (horizontal, 2 rows via Wrap) ───────────────────
          if (payload.categories.isNotEmpty) ...[
            const EGSectionHeader(title: 'Shop by Category'),
            SizedBox(
              height: 100,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                itemCount: payload.categories.length,
                itemBuilder: (_, i) {
                  final cat = payload.categories[i];
                  return EGCategoryTile(
                    category: cat,
                    selected: _selectedCatId == cat.id,
                    onTap: () {
                      setState(() => _selectedCatId = cat.id);
                      context.push('/egrocery/products?category=${cat.id}&title=${cat.name}');
                    },
                  );
                },
              ),
            ),
          ],

          // ── Dynamic sections ──────────────────────────────────────────────
          for (final section in payload.sections) ...[
            if (section.products.isNotEmpty) _buildSection(context, section),
          ],

          // ── Buy Again ─────────────────────────────────────────────────────
          if (payload.buyAgain.isNotEmpty) ...[
            EGSectionHeader(title: '🔁 Buy Again', onSeeAll: null),
            SizedBox(
              height: 230,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                itemCount: payload.buyAgain.length,
                itemBuilder: (_, i) => Padding(
                  padding: const EdgeInsets.only(right: 12),
                  child: EGProductCard(
                    product: payload.buyAgain[i],
                    onTap: () => context.push('/egrocery/product/${payload.buyAgain[i].slug}'),
                  ),
                ),
              ),
            ),
          ],

          const SizedBox(height: 30),
        ])),
      ]),
    );
  }

  Widget _buildSection(BuildContext context, EGSection section) {
    final isFlash = section.type == 'flash_deal';
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      EGSectionHeader(
        title: isFlash ? '⚡ ${section.title}' : section.title,
        onSeeAll: () => context.push('/egrocery/products?section=${section.id}&title=${section.title}'),
      ),
      SizedBox(
        height: isFlash ? 300 : 240,
        child: ListView.builder(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 12),
          itemCount: section.products.length,
          itemBuilder: (_, i) {
            final p = section.products[i];
            return Padding(
              padding: const EdgeInsets.only(right: 12),
              child: isFlash
                  ? EGFlashDealCard(product: p)
                  : EGProductCard(
                      product: p,
                      onTap: () => context.push('/egrocery/product/${p.slug}'),
                    ),
            );
          },
        ),
      ),
    ]);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Skeleton
// ─────────────────────────────────────────────────────────────────────────────

class _EGHomeSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) => SingleChildScrollView(
        physics: const NeverScrollableScrollPhysics(),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const SizedBox(height: 100),
          // Banner
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: EGShimmerBox(width: double.infinity, height: 180, radius: EGTheme.rCard),
          ),
          const SizedBox(height: 18),
          // Category row
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(children: List.generate(5, (_) => Padding(
              padding: const EdgeInsets.only(right: 12),
              child: Column(children: [
                EGShimmerBox(width: 56, height: 56, radius: 16),
                const SizedBox(height: 6),
                EGShimmerBox(width: 48, height: 10, radius: 5),
              ]),
            ))),
          ),
          const SizedBox(height: 18),
          // Product row
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(children: List.generate(3, (_) => Padding(
              padding: const EdgeInsets.only(right: 12),
              child: EGShimmerBox(width: 160, height: 220, radius: EGTheme.rCard),
            ))),
          ),
        ]),
      );
}
