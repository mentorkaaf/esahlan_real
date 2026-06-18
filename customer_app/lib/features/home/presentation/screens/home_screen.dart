import 'dart:async';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/api/module_api_service.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../../../../core/providers/theme_provider.dart';
import '../../../../core/theme/app_theme.dart';
import '../providers/home_provider.dart';
import '../../data/models/home_models.dart';
import '../../../../features/ads/services/ad_service.dart';
import '../../../../features/ads/widgets/banner_ad_strip.dart';
import '../../../../features/ads/widgets/card_ad_strip.dart';

// ── eSahlan Brand Gradient — Navy dominant, subtle orange touch at corner ──────
// All service cards share ONE unified gradient: deep navy → very faint orange
// Orange appears only as a slight warm glow at bottom-right (≈15% presence).
// stops: [0.0, 0.72, 1.0]  →  navy takes 72%, soft transition, hint of orange

// ── Icon config per module slug (gradient is shared — only icon changes) ──────
class _ModuleStyle {
  final IconData icon;
  const _ModuleStyle(this.icon);
}

const Map<String, _ModuleStyle> _moduleStyles = {
  'efood':      _ModuleStyle(Icons.restaurant_rounded),
  'eshop':      _ModuleStyle(Icons.storefront_rounded),
  'eticket':    _ModuleStyle(Icons.flight_rounded),
  'ehealth':    _ModuleStyle(Icons.medical_services_rounded),
  'edata':      _ModuleStyle(Icons.wifi_rounded),
  'eparcel':    _ModuleStyle(Icons.inventory_2_rounded),
  'erent':      _ModuleStyle(Icons.home_rounded),
  'emoving':    _ModuleStyle(Icons.local_shipping_rounded),
  'ewholesale': _ModuleStyle(Icons.warehouse_rounded),
  'egrocery':   _ModuleStyle(Icons.shopping_basket_rounded),
  'eexchange':  _ModuleStyle(Icons.currency_exchange_rounded),
  'elaundry':   _ModuleStyle(Icons.local_laundry_service_rounded),
  'elearning':  _ModuleStyle(Icons.school_rounded),
};

_ModuleStyle _styleFor(String slug) =>
    _moduleStyles[slug] ?? const _ModuleStyle(Icons.apps_rounded);

// Brand color — default state
const _kNavy = Color(0xFF140465);
// Orange — shown when card is pressed
const _kOrange = Color(0xFFFF8A00);

// ─────────────────────────────────────────────────────────────────────────────

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  @override
  void initState() {
    super.initState();
    // Trigger popup ads after the home screen is fully rendered
    WidgetsBinding.instance.addPostFrameCallback((_) {
      AdService.instance.triggerAppOpenPopups(context, ref);
    });
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final appBarBg = Theme.of(context).appBarTheme.backgroundColor ?? Colors.white;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: CustomScrollView(
        slivers: [
          // ── AppBar ────────────────────────────────────────────────────────
          SliverAppBar(
            pinned: true,
            backgroundColor: appBarBg,
            elevation: 0,
            shadowColor: Colors.black12,
            surfaceTintColor: Colors.transparent,
            titleSpacing: 0,
            title: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                children: [
                  RichText(
                    text: TextSpan(children: [
                      const TextSpan(
                        text: 'e-',
                        style: TextStyle(
                          color: AppColors.primary, fontSize: 22,
                          fontWeight: FontWeight.w900, fontFamily: 'Cairo',
                        ),
                      ),
                      TextSpan(
                        text: 'Sahlan',
                        style: TextStyle(
                          color: cs.onSurface, fontSize: 22,
                          fontWeight: FontWeight.w900, fontFamily: 'Cairo',
                        ),
                      ),
                    ]),
                  ),
                  const Spacer(),
                  // ── Dark/light mode toggle ──────────────────────────────
                  IconButton(
                    icon: AnimatedSwitcher(
                      duration: const Duration(milliseconds: 300),
                      transitionBuilder: (child, anim) =>
                          RotationTransition(turns: anim, child: child),
                      child: Icon(
                        isDark ? Icons.wb_sunny_rounded : Icons.nightlight_round,
                        key: ValueKey(isDark),
                        color: isDark ? const Color(0xFFFFD54F) : cs.onSurface,
                        size: 22,
                      ),
                    ),
                    onPressed: () =>
                        ref.read(themeModeProvider.notifier).toggle(),
                    tooltip: isDark ? 'Light mode' : 'Dark mode',
                  ),
                  Stack(
                    children: [
                      IconButton(
                        icon: Icon(Icons.notifications_outlined,
                            color: cs.onSurface, size: 26),
                        onPressed: () {},
                      ),
                      Positioned(
                        top: 8, right: 8,
                        child: Container(
                          width: 8, height: 8,
                          decoration: const BoxDecoration(
                              color: AppColors.primary, shape: BoxShape.circle),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            bottom: PreferredSize(
              preferredSize: const Size.fromHeight(96),
              child: Container(
                color: appBarBg,
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                child: Column(
                  children: [
                    Row(children: [
                      const Icon(Icons.location_on, color: AppColors.primary, size: 18),
                      const SizedBox(width: 4),
                      Text('Hodan, Mogadishu',
                          style: TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 14,
                              color: cs.onSurface)),
                      const SizedBox(width: 2),
                      Icon(Icons.keyboard_arrow_down_rounded,
                          color: cs.onSurface, size: 18),
                    ]),
                    const SizedBox(height: 10),
                    Container(
                      height: 46,
                      decoration: BoxDecoration(
                        color: Theme.of(context).scaffoldBackgroundColor,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(
                          color: isDark
                              ? const Color(0xFF2A2B48)
                              : AppColors.divider,
                        ),
                      ),
                      child: Row(children: [
                        const SizedBox(width: 14),
                        const Icon(Icons.search_rounded, color: AppColors.textGrey, size: 20),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            'Search services, restaurants, houses...',
                            style: TextStyle(
                              color: isDark
                                  ? const Color(0xFF5A5A7A)
                                  : AppColors.textLight,
                              fontSize: 13,
                            ),
                          ),
                        ),
                        const Icon(Icons.mic_outlined, color: AppColors.textGrey, size: 20),
                        const SizedBox(width: 14),
                      ]),
                    ),
                  ],
                ),
              ),
            ),
          ),

          // ── Body ─────────────────────────────────────────────────────────
          SliverToBoxAdapter(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SizedBox(height: 8),
                const _DynamicBannerSlider(),
                const SizedBox(height: 12),
                // ── Promotional banner ads (global / home targeted) ─────────
                const BannerAdStrip(),
                const _ServicesSectionHeader(),
                const SizedBox(height: 8),
                const _ApiDrivenServicesGrid(),
                // ── Promotional card ads ────────────────────────────────────
                const CardAdStrip(),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Section Header ───────────────────────────────────────────────────────────

class _ServicesSectionHeader extends StatelessWidget {
  const _ServicesSectionHeader();

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 16),
    child: Row(
      children: [
        Container(
          width: 4, height: 20,
          decoration: BoxDecoration(
              color: AppColors.primary, borderRadius: BorderRadius.circular(2)),
        ),
        const SizedBox(width: 10),
        const Text(
          'Our Services',
          style: TextStyle(
              fontSize: 17, fontWeight: FontWeight.w800, color: context.colors.navyText),
        ),
      ],
    ),
  );
}

// ─── API-driven Services Grid ─────────────────────────────────────────────────
// Watches modulesProvider → only shows modules where is_active=true (admin-controlled)

class _ApiDrivenServicesGrid extends ConsumerWidget {
  const _ApiDrivenServicesGrid();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final modulesAsync = ref.watch(modulesProvider);

    return modulesAsync.when(
      loading: () => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14),
        child: GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          padding: EdgeInsets.zero,
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 4,
            childAspectRatio: 0.95,
            crossAxisSpacing: 6,
            mainAxisSpacing: 6,
          ),
          itemCount: 8,
          itemBuilder: (_, __) => const _SkeletonServiceCard(),
        ),
      ),
      error: (_, __) => const SizedBox.shrink(),
      data: (modules) {
        if (modules.isEmpty) return const SizedBox.shrink();
        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14),
          child: GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            padding: EdgeInsets.zero,
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 4,
              childAspectRatio: 0.95,
              crossAxisSpacing: 6,
              mainAxisSpacing: 6,
            ),
            itemCount: modules.length,
            itemBuilder: (_, i) => _PremiumServiceCard(module: modules[i]),
          ),
        );
      },
    );
  }
}

// ─── Skeleton loading card ────────────────────────────────────────────────────

class _SkeletonServiceCard extends StatelessWidget {
  const _SkeletonServiceCard();

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 62, height: 62,
          decoration: BoxDecoration(
              color: AppColors.shimmer, borderRadius: BorderRadius.circular(18)),
        ),
        const SizedBox(height: 8),
        Container(
          width: 48, height: 10,
          decoration: BoxDecoration(
              color: AppColors.shimmer, borderRadius: BorderRadius.circular(5)),
        ),
        const SizedBox(height: 4),
        Container(
          width: 32, height: 10,
          decoration: BoxDecoration(
              color: AppColors.shimmer.withValues(alpha: 0.5),
              borderRadius: BorderRadius.circular(5)),
        ),
      ],
    );
  }
}

// ─── Premium Service Card ─────────────────────────────────────────────────────

class _PremiumServiceCard extends ConsumerStatefulWidget {
  final ModuleModel module;
  const _PremiumServiceCard({required this.module});

  @override
  ConsumerState<_PremiumServiceCard> createState() => _PremiumServiceCardState();
}

final _modSvc = ModuleApiService.create();

class _PremiumServiceCardState extends ConsumerState<_PremiumServiceCard>
    with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<double> _scale;
  bool _pressed = false;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 120));
    _scale = Tween(begin: 1.0, end: 0.90)
        .animate(CurvedAnimation(parent: _ctrl, curve: Curves.easeInOut));
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  void _prefetch(String slug) {
    // Fire-and-forget: warm up cache before navigation starts
    switch (slug) {
      case 'efood':      _modSvc.getRestaurants().ignore(); _modSvc.getFoodBanners().ignore(); _modSvc.getFoodCategories().ignore(); break;
      case 'erent':      _modSvc.getProperties().ignore(); _modSvc.getRentDistricts().ignore(); break;
      case 'egrocery':   _modSvc.getGroceryCategories().ignore(); _modSvc.getGroceryProducts().ignore(); break;
      case 'eticket':    _modSvc.getTicketHome().ignore(); _modSvc.getFlightCities().ignore(); break;
      case 'edata':      _modSvc.getDataAll().ignore(); _modSvc.getDataProviders().ignore(); break;
      case 'eshop':      _modSvc.getShopHome().ignore(); _modSvc.getShopBanners().ignore(); break;
      case 'ehealth':    _modSvc.getHealthCategories().ignore(); _modSvc.getDoctors().ignore(); break;
      case 'ewholesale': _modSvc.getWholesaleCategories().ignore(); break;
      case 'eexchange':  _modSvc.getExchangeRates().ignore(); break;
      case 'elaundry':   _modSvc.getLaundryItems().ignore(); break;
      case 'emoving':    _modSvc.getMovingMoveTypes().ignore(); _modSvc.getMovingDistricts().ignore(); break;
      case 'eparcel':    _modSvc.getParcelTypes().ignore(); _modSvc.getParcelDistricts().ignore(); break;
      default: break;
    }
  }

  void _onDown(_) {
    setState(() => _pressed = true);
    _ctrl.forward();
    _prefetch(widget.module.slug);
  }

  void _onUp(_) {
    setState(() => _pressed = false);
    _ctrl.reverse();
    context.push('/${widget.module.slug}');
  }

  void _onCancel() {
    setState(() => _pressed = false);
    _ctrl.reverse();
  }

  @override
  Widget build(BuildContext context) {
    final style = _styleFor(widget.module.slug);

    return GestureDetector(
      onTapDown: _onDown,
      onTapUp: _onUp,
      onTapCancel: _onCancel,
      child: ScaleTransition(
        scale: _scale,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 120),
              curve: Curves.easeInOut,
              width: 62, height: 62,
              decoration: BoxDecoration(
                color: _pressed ? _kOrange : _kNavy,
                borderRadius: BorderRadius.circular(18),
                boxShadow: [
                  BoxShadow(
                    color: (_pressed ? _kOrange : _kNavy).withValues(alpha: 0.35),
                    blurRadius: _pressed ? 16 : 12,
                    offset: const Offset(0, 5),
                  ),
                ],
              ),
              child: Center(
                child: Icon(style.icon, color: Colors.white, size: 28),
              ),
            ),
            const SizedBox(height: 8),
            Text(
              widget.module.name,
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                color: _pressed ? _kOrange : const Color(0xFF1A1A2E),
                height: 1.25,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Dynamic Banner Slider ────────────────────────────────────────────────────

class _DynamicBannerSlider extends ConsumerStatefulWidget {
  const _DynamicBannerSlider();

  @override
  ConsumerState<_DynamicBannerSlider> createState() =>
      _DynamicBannerSliderState();
}

class _DynamicBannerSliderState extends ConsumerState<_DynamicBannerSlider> {
  final PageController _ctrl = PageController();
  Timer? _timer;
  int _current = 0;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 4), (_) {
      if (!mounted) return;
      final banners = ref.read(homeBannersProvider).valueOrNull;
      if (banners != null && banners.length > 1) {
        _current = (_current + 1) % banners.length;
        _ctrl.animateToPage(_current,
            duration: const Duration(milliseconds: 500),
            curve: Curves.easeInOut);
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Use public endpoint — works without auth, no dependency on homeDataProvider
    final bannersAsync = ref.watch(homeBannersProvider);

    return bannersAsync.when(
      loading: () => _FallbackHeroBanner(),
      error: (err, _) {
        // Show error in debug mode so developer can see what went wrong
        debugPrint('❌ homeBannersProvider error: $err');
        return _FallbackHeroBanner();
      },
      data: (banners) {
        final real = banners
            .where((b) => b.imageUrl != null && b.imageUrl!.isNotEmpty)
            .toList();
        debugPrint('🖼 Banners loaded: ${real.length}');
        if (real.isEmpty) return _FallbackHeroBanner();

        return Column(
          children: [
            SizedBox(
              height: 170,
              child: PageView.builder(
                controller: _ctrl,
                itemCount: real.length,
                onPageChanged: (i) => setState(() => _current = i),
                itemBuilder: (_, i) => _BannerCard(banner: real[i]),
              ),
            ),
            if (real.length > 1) ...[
              const SizedBox(height: 10),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(
                  real.length,
                  (i) => AnimatedContainer(
                    duration: const Duration(milliseconds: 300),
                    margin: const EdgeInsets.symmetric(horizontal: 3),
                    width: _current == i ? 20 : 6,
                    height: 6,
                    decoration: BoxDecoration(
                      color: _current == i
                          ? AppColors.primary
                          : AppColors.primary.withValues(alpha: 0.25),
                      borderRadius: BorderRadius.circular(3),
                    ),
                  ),
                ),
              ),
            ],
          ],
        );
      },
    );
  }
}

class _BannerCard extends StatelessWidget {
  final BannerModel banner;
  const _BannerCard({required this.banner});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => _handleTap(context),
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 16),
        decoration: BoxDecoration(borderRadius: BorderRadius.circular(20)),
        clipBehavior: Clip.antiAlias,
        child: NetImage(
          url: banner.imageUrl,
          fit: BoxFit.cover,
          width: double.infinity,
          placeholder: Container(
            color: const Color(0xFFE8E8F0),
            child: const Center(child: CircularProgressIndicator(strokeWidth: 2)),
          ),
          errorWidget: _FallbackHeroBanner(),
        ),
      ),
    );
  }

  void _handleTap(BuildContext context) {
    final type = banner.actionType;
    final url = banner.actionUrl;
    if (type == null || type == 'none' || url == null || url.isEmpty) return;
    if (type == 'module') {
      context.push('/$url');
    } else if (type == 'vendor') {
      context.push('/vendor/$url');
    }
  }
}

class _FallbackHeroBanner extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      height: 170,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF07003B), Color(0xFF1A0066)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Stack(
        children: [
          Positioned(
            top: -20, right: -20,
            child: Container(
              width: 140, height: 140,
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.12),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Positioned(
            bottom: -30, right: 60,
            child: Container(
              width: 90, height: 90,
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.08),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(22),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Text(
                        'Everything you need\nis now in one App',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          height: 1.3,
                        ),
                      ),
                      const SizedBox(height: 6),
                      RichText(
                        text: const TextSpan(children: [
                          TextSpan(
                            text: 'e-',
                            style: TextStyle(
                              color: AppColors.primary,
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                              fontFamily: 'Cairo',
                            ),
                          ),
                          TextSpan(
                            text: 'Sahlan',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                              fontFamily: 'Cairo',
                            ),
                          ),
                        ]),
                      ),
                      const SizedBox(height: 14),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 18, vertical: 9),
                        decoration: BoxDecoration(
                          color: AppColors.primary,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Text(
                          'Explore Now',
                          style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                              fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  width: 85, height: 110,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.07),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Center(
                      child: Text('🛵', style: TextStyle(fontSize: 50))),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
