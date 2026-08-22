import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/ew_provider.dart';
import '../ui/ew_theme.dart';
import '../ui/widgets/ew_widgets.dart';
import '../../data/models/ew_models.dart';

class EwHomeScreen extends ConsumerStatefulWidget {
  const EwHomeScreen({super.key});

  @override
  ConsumerState<EwHomeScreen> createState() => _EwHomeScreenState();
}

class _EwHomeScreenState extends ConsumerState<EwHomeScreen> {
  final _searchCtrl = TextEditingController();
  int _bannerIndex = 0;
  Timer? _bannerTimer;
  final _bannerPageCtrl = PageController();

  @override
  void dispose() {
    _searchCtrl.dispose();
    _bannerTimer?.cancel();
    _bannerPageCtrl.dispose();
    super.dispose();
  }

  void _startBannerTimer(int len) {
    _bannerTimer?.cancel();
    if (len <= 1) return;
    _bannerTimer = Timer.periodic(const Duration(seconds: 4), (_) {
      final next = (_bannerIndex + 1) % len;
      _bannerPageCtrl.animateToPage(next,
        duration: const Duration(milliseconds: 400), curve: Curves.easeInOut);
    });
  }

  @override
  Widget build(BuildContext context) {
    final homeAsync = ref.watch(ewHomeProvider);

    return Scaffold(
      backgroundColor: EwTheme.bg,
      body: RefreshIndicator(
        color: EwTheme.orange,
        onRefresh: () => ref.refresh(ewHomeProvider.future),
        child: CustomScrollView(
          slivers: [
            _buildAppBar(context),
            homeAsync.when(
              loading: () => SliverToBoxAdapter(child: _buildSkeletons()),
              error:   (e, _) => SliverFillRemaining(child: EwErrorRetry(error: e, onRetry: () => ref.refresh(ewHomeProvider.future))),
              data:    (home) => _buildContent(context, home),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAppBar(BuildContext context) {
    return SliverAppBar(
      pinned: true,
      elevation: 0,
      backgroundColor: EwTheme.navy,
      expandedHeight: 110,
      flexibleSpace: FlexibleSpaceBar(
        background: Container(color: EwTheme.navy),
      ),
      bottom: PreferredSize(
        preferredSize: const Size.fromHeight(56),
        child: Container(
          color: EwTheme.navy,
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
          child: Row(children: [
            Expanded(
              child: GestureDetector(
                onTap: () => context.push('/ewholesale/search'),
                child: Container(
                  height: 42,
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.12),
                    borderRadius: EwTheme.radius8,
                  ),
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  child: Row(children: [
                    const Icon(Icons.search, color: Colors.white70, size: 18),
                    const SizedBox(width: 8),
                    Text('Search products, suppliers…',
                      style: TextStyle(color: Colors.white.withOpacity(0.6), fontSize: 14)),
                  ]),
                ),
              ),
            ),
            const SizedBox(width: 10),
            GestureDetector(
              onTap: () => context.push('/ewholesale/rfq/new'),
              child: Container(
                height: 42,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                decoration: BoxDecoration(color: EwTheme.orange, borderRadius: EwTheme.radius8),
                child: const Row(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.post_add, color: Colors.white, size: 16),
                  SizedBox(width: 6),
                  Text('Post RFQ', style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600)),
                ]),
              ),
            ),
          ]),
        ),
      ),
      title: const Text('eWholesale', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700)),
      actions: [
        IconButton(
          icon: const Icon(Icons.inbox_outlined, color: Colors.white),
          onPressed: () => context.push('/ewholesale/quotes'),
        ),
        IconButton(
          icon: const Icon(Icons.shopping_cart_outlined, color: Colors.white),
          onPressed: () => context.push('/ewholesale/cart'),
        ),
      ],
    );
  }

  SliverToBoxAdapter _buildContent(BuildContext context, EwHomePayload home) {
    if (home.banners.isNotEmpty) _startBannerTimer(home.banners.length);
    return SliverToBoxAdapter(
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // ── Banners ────────────────────────────────────────────────────────
        if (home.banners.isNotEmpty) ...[
          const SizedBox(height: 12),
          _buildBannerSlider(home.banners),
        ],

        // ── Category Grid ──────────────────────────────────────────────────
        if (home.categories.isNotEmpty) ...[
          const SizedBox(height: 20),
          _paddedSection(child: const EwSectionHeader(title: 'Categories')),
          const SizedBox(height: 12),
          _buildCategoryGrid(context, home.categories),
        ],

        // ── Top Deals ──────────────────────────────────────────────────────
        if (home.topDeals.isNotEmpty) ...[
          const SizedBox(height: 20),
          _paddedSection(child: EwSectionHeader(
            title: '🔥 Top Deals',
            actionLabel: 'See all',
            onAction: () => context.push('/ewholesale/products?sort=deals'),
          )),
          const SizedBox(height: 12),
          _buildProductRail(context, home.topDeals, showCountdown: true),
        ],

        // ── Verified Suppliers ─────────────────────────────────────────────
        if (home.verifiedSuppliers.isNotEmpty) ...[
          const SizedBox(height: 20),
          _paddedSection(child: EwSectionHeader(
            title: '✓ Verified Suppliers',
            actionLabel: 'See all',
            onAction: () => context.push('/ewholesale/products?verified=1'),
          )),
          const SizedBox(height: 12),
          _buildSupplierRail(context, home.verifiedSuppliers),
        ],

        // ── Best Sellers ───────────────────────────────────────────────────
        if (home.bestSellers.isNotEmpty) ...[
          const SizedBox(height: 20),
          _paddedSection(child: EwSectionHeader(
            title: 'Best Sellers',
            actionLabel: 'See all',
            onAction: () => context.push('/ewholesale/products?sort=best_selling'),
          )),
          const SizedBox(height: 12),
          _buildProductRail(context, home.bestSellers),
        ],

        // ── New Arrivals ───────────────────────────────────────────────────
        if (home.newArrivals.isNotEmpty) ...[
          const SizedBox(height: 20),
          _paddedSection(child: EwSectionHeader(
            title: 'New Arrivals',
            actionLabel: 'See all',
            onAction: () => context.push('/ewholesale/products?sort=newest'),
          )),
          const SizedBox(height: 12),
          _buildProductRail(context, home.newArrivals),
        ],

        // ── Open RFQs teaser ───────────────────────────────────────────────
        if (home.openRfqCount > 0) ...[
          const SizedBox(height: 20),
          _buildRfqTeaser(context, home.openRfqCount),
        ],

        const SizedBox(height: 40),
      ]),
    );
  }

  Widget _buildBannerSlider(List<EwBanner> banners) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: SizedBox(
        height: 160,
        child: Stack(children: [
        PageView.builder(
          controller: _bannerPageCtrl,
          itemCount: banners.length,
          onPageChanged: (i) => setState(() => _bannerIndex = i),
          itemBuilder: (_, i) {
            final b = banners[i];
            final hasImage = b.image != null && b.image!.isNotEmpty;
            final bgColor = EwTheme.navy; // always navy if no image
            return ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Stack(fit: StackFit.expand, children: [
                // Background
                Container(color: bgColor),
                // Full image (if present)
                if (hasImage)
                  Image.network(b.image!, fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => const SizedBox()),
                // Dark scrim over image so text is readable
                if (hasImage)
                  Container(
                    decoration: const BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.centerLeft,
                        end: Alignment.centerRight,
                        colors: [Color(0xCC1B1444), Color(0x441B1444)],
                      ),
                    ),
                  ),
                // Text & CTA
                Positioned(
                  left: 20, right: 20, top: 0, bottom: 0,
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
                    Text(b.title,
                      style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800),
                      maxLines: 2, overflow: TextOverflow.ellipsis),
                    if (b.subtitle != null) ...[
                      const SizedBox(height: 4),
                      Text(b.subtitle!,
                        style: const TextStyle(color: Colors.white70, fontSize: 13),
                        maxLines: 1, overflow: TextOverflow.ellipsis),
                    ],
                    if (b.ctaLabel != null) ...[
                      const SizedBox(height: 12),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                        decoration: BoxDecoration(
                          color: EwTheme.orange,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(b.ctaLabel!, style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
                      ),
                    ],
                  ]),
                ),
              ]),
            );
          },
        ),
        if (banners.length > 1)
          Positioned(
            bottom: 8, left: 0, right: 0,
            child: Row(mainAxisAlignment: MainAxisAlignment.center, children: banners.asMap().entries.map((e) {
              return AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                margin: const EdgeInsets.symmetric(horizontal: 3),
                width: _bannerIndex == e.key ? 20 : 6,
                height: 6,
                decoration: BoxDecoration(
                  color: _bannerIndex == e.key ? EwTheme.orange : Colors.white.withOpacity(0.4),
                  borderRadius: EwTheme.radius4,
                ),
              );
            }).toList()),
          ),
      ]),
      ),
    );
  }

  Widget _buildCategoryGrid(BuildContext context, List<EwCategory> categories) {
    final visible = categories.take(8).toList();
    return SizedBox(
      height: 88,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: visible.length,
        itemBuilder: (_, i) {
          final cat = visible[i];
          return GestureDetector(
            onTap: () => context.push('/ewholesale/products?category=${cat.id}'),
            child: Container(
              width: 72,
              margin: const EdgeInsets.only(right: 10),
              child: Column(children: [
                Container(
                  width: 52, height: 52,
                  decoration: BoxDecoration(
                    color: EwTheme.navy.withOpacity(0.06),
                    borderRadius: EwTheme.radius8,
                  ),
                  child: cat.image != null
                    ? ClipRRect(borderRadius: EwTheme.radius8,
                        child: Image.network(cat.image!, fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => _catIcon(cat)))
                    : _catIcon(cat),
                ),
                const SizedBox(height: 5),
                Text(cat.name, style: EwTheme.bodySmall.copyWith(fontSize: 11),
                  maxLines: 2, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center),
              ]),
            ),
          );
        },
      ),
    );
  }

  Widget _catIcon(EwCategory cat) => Center(
    child: Text(cat.icon ?? '📦', style: const TextStyle(fontSize: 22)),
  );

  Widget _buildProductRail(BuildContext context, List<EwProduct> products, {bool showCountdown = false}) {
    return SizedBox(
      height: showCountdown ? 240 : 220,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: products.length,
        itemBuilder: (_, i) {
          final p = products[i];
          return SizedBox(
            width: 160,
            child: Padding(
              padding: const EdgeInsets.only(right: 12),
              child: Column(children: [
                Expanded(child: WholesaleProductCard(
                  product: p,
                  onTap: () { if (p.slug.isNotEmpty) context.push('/ewholesale/product/${p.slug}'); },
                )),
                if (showCountdown && p.dealEndsAt != null)
                  _CountdownChip(endsAt: p.dealEndsAt!),
              ]),
            ),
          );
        },
      ),
    );
  }

  Widget _buildSupplierRail(BuildContext context, List<EwSupplierCard> suppliers) {
    return SizedBox(
      height: 100,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: suppliers.length,
        itemBuilder: (_, i) {
          final s = suppliers[i];
          return GestureDetector(
            onTap: () => context.push('/ewholesale/supplier/${s.id}'),
            child: Container(
              width: 100,
              margin: const EdgeInsets.only(right: 10),
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: EwTheme.surface,
                borderRadius: EwTheme.radius12,
                border: Border.all(color: EwTheme.border),
                boxShadow: EwTheme.cardShadow,
              ),
              child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                CircleAvatar(
                  radius: 22,
                  backgroundColor: EwTheme.navy.withOpacity(0.08),
                  backgroundImage: s.logo != null ? NetworkImage(s.logo!) : null,
                  child: s.logo == null
                    ? Text(s.displayName.isNotEmpty ? s.displayName[0] : '?',
                        style: const TextStyle(color: EwTheme.navy, fontWeight: FontWeight.w700))
                    : null,
                ),
                const SizedBox(height: 5),
                Text(s.displayName, style: EwTheme.bodySmall.copyWith(fontSize: 11),
                  maxLines: 1, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center),
                const SizedBox(height: 3),
                SupplierBadge(verification: s.verification, compact: true),
              ]),
            ),
          );
        },
      ),
    );
  }

  Widget _buildRfqTeaser(BuildContext context, int count) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: GestureDetector(
        onTap: () => context.push('/ewholesale/rfq/new'),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [EwTheme.navy, Color(0xFF2D1B69)],
            ),
            borderRadius: EwTheme.radius12,
          ),
          child: Row(children: [
            const Icon(Icons.request_quote_outlined, color: EwTheme.orange, size: 32),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('$count Open RFQs', style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700)),
              const Text('Post your own and get quotes from suppliers', style: TextStyle(color: Colors.white70, fontSize: 12)),
            ])),
            const Icon(Icons.arrow_forward_ios, color: Colors.white54, size: 14),
          ]),
        ),
      ),
    );
  }

  Widget _buildSkeletons() {
    return Column(children: [
      const SizedBox(height: 12),
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: const ShimmerBox.fill(height: 160, borderRadius: BorderRadius.all(Radius.circular(12))),
      ),
      const SizedBox(height: 20),
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Row(children: List.generate(4, (_) => Expanded(child: Padding(
          padding: const EdgeInsets.only(right: 10),
          child: const ShimmerBox.fill(height: 70),
        )))),
      ),
      const SizedBox(height: 20),
      SizedBox(
        height: 200,
        child: ListView.builder(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          itemCount: 4,
          itemBuilder: (_, __) => Padding(
            padding: const EdgeInsets.only(right: 12),
            child: ShimmerBox(width: 160, height: 200),
          ),
        ),
      ),
    ]);
  }

  Widget _paddedSection({required Widget child}) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 16),
    child: child,
  );
}

class _CountdownChip extends StatefulWidget {
  final DateTime endsAt;
  const _CountdownChip({required this.endsAt});

  @override
  State<_CountdownChip> createState() => _CountdownChipState();
}

class _CountdownChipState extends State<_CountdownChip> {
  Timer? _t;
  Duration _remaining = Duration.zero;

  @override
  void initState() {
    super.initState();
    _update();
    _t = Timer.periodic(const Duration(seconds: 1), (_) => _update());
  }

  void _update() {
    final r = widget.endsAt.difference(DateTime.now());
    setState(() => _remaining = r.isNegative ? Duration.zero : r);
  }

  @override
  void dispose() { _t?.cancel(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final h = _remaining.inHours;
    final m = _remaining.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = _remaining.inSeconds.remainder(60).toString().padLeft(2, '0');
    final label = h > 0 ? '${h}h ${m}m left' : '${m}:${s} left';
    return Container(
      margin: const EdgeInsets.only(top: 4),
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
      decoration: BoxDecoration(
        color: EwTheme.red.withOpacity(0.1),
        borderRadius: EwTheme.radius4,
        border: Border.all(color: EwTheme.red.withOpacity(0.3)),
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(Icons.timer_outlined, size: 10, color: EwTheme.red),
        const SizedBox(width: 3),
        Text(label, style: TextStyle(fontSize: 10, color: EwTheme.red, fontWeight: FontWeight.w600)),
      ]),
    );
  }
}
