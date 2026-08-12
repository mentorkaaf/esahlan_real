import 'dart:async';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/api/module_api_service.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../../../../core/providers/app_settings_provider.dart';
import '../../../../core/theme/app_theme.dart';
import '../providers/home_provider.dart';
import '../../data/models/home_models.dart';
import '../../data/repositories/home_repository.dart';
import '../../../../features/ads/services/ad_service.dart';
import '../../../../features/auth/presentation/providers/auth_provider.dart';
import '../../../../core/services/location_service.dart';
import '../../../../features/ads/widgets/banner_ad_strip.dart';
import '../../../../features/ads/widgets/card_ad_strip.dart';
import '../../../notifications/notification_screen.dart';
import '../../../notifications/notification_provider.dart';
import '../../../../core/l10n/app_strings.dart';

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

class _HomeScreenState extends ConsumerState<HomeScreen> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      AdService.instance.triggerAppOpenPopups(context, ref);
      if (mounted) {
        await LocationService.ensureLocationEnabled(context);
      }
      LocationService.startTracking();
      _checkSingleModule();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  void _checkSingleModule() {
    final modulesAsync = ref.read(modulesProvider);
    modulesAsync.whenData((modules) {
      if (modules.length == 1 && mounted) {
        context.go('/${modules.first.slug}');
      }
    });
    // Also listen for when modules load asynchronously
    ref.listenManual<AsyncValue<List<ModuleModel>>>(modulesProvider, (_, next) {
      next.whenData((modules) {
        if (modules.length == 1 && mounted) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted) context.go('/${modules.first.slug}');
          });
        }
      });
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      ref.invalidate(homeDataProvider);
      ref.invalidate(modulesProvider);
      ref.invalidate(homeBannersProvider);
      ref.invalidate(vendorsByModuleProvider);
      LocationService.ensureLocationEnabled(context).then((_) {
        LocationService.onResume();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final appBarBg = Theme.of(context).appBarTheme.backgroundColor ?? Colors.white;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final user = ref.watch(authStateProvider).valueOrNull;
    final isDesktop = kIsWeb && MediaQuery.sizeOf(context).width >= 900;

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
                  // Hide logo on desktop — sidebar already shows it
                  if (!isDesktop)
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
                  const _ThemeCycleButton(),
                  _NotifBell(cs: cs),
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
                      Text(
                          user?.districtName ?? 'Mogadishu',
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
                            AppL10n.of(context).searchHint,
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
                // ── Live Offers ─────────────────────────────────────────────
                const _LiveOffersSection(),
                // ── Near You ────────────────────────────────────────────────
                const _HomeNearYouSection(),
                // ── Best Sellers ────────────────────────────────────────────
                const _BestSellersSection(),
                // ── Available Rent Homes ─────────────────────────────────────
                const _RentHomesSection(),
                // ── Upcoming Flights ─────────────────────────────────────────
                const _FlightsSection(),
                // ── Promotional card ads ────────────────────────────────────
                const CardAdStrip(),
                const SizedBox(height: 100),
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
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return Padding(
    padding: const EdgeInsets.symmetric(horizontal: 16),
    child: Row(
      children: [
        Container(
          width: 4, height: 20,
          decoration: BoxDecoration(
              color: AppColors.primary, borderRadius: BorderRadius.circular(2)),
        ),
        SizedBox(width: 10),
        Text(
          l.ourServices,
          style: TextStyle(
              fontSize: 17, fontWeight: FontWeight.w800, color: context.colors.navyText),
        ),
      ],
    ),
  );
  }
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
        return LayoutBuilder(builder: (context, constraints) {
          final count = modules.length;
          final isWide = kIsWeb && constraints.maxWidth > 600;
          final cols = isWide
              ? (count >= 6 ? 6 : count >= 4 ? 5 : count >= 3 ? 4 : count)
              : (count == 1 ? 1 : count == 2 ? 2 : count == 3 ? 3 : 4);
          final ratio = isWide ? 1.05 : (count == 1 ? 3.2 : count == 2 ? 1.6 : count == 3 ? 1.1 : 0.95);
          final hPad = isWide ? 20.0 : 14.0;
          final spacing = isWide ? 12.0 : 6.0;
          return Padding(
            padding: EdgeInsets.symmetric(horizontal: hPad),
            child: GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              padding: EdgeInsets.zero,
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: cols,
                childAspectRatio: ratio,
                crossAxisSpacing: spacing,
                mainAxisSpacing: spacing,
              ),
              itemCount: count,
              itemBuilder: (_, i) => _PremiumServiceCard(module: modules[i]),
            ),
          );
        });
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
    // Web: go() stays inside the shell (sidebar visible)
    // Mobile: push() keeps native back gesture working
    if (kIsWeb) {
      context.go('/${widget.module.slug}');
    } else {
      context.push('/${widget.module.slug}');
    }
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
        child: LayoutBuilder(builder: (context, constraints) {
          final cellW = constraints.maxWidth;
          final iconSz = (cellW * 0.62).clamp(50.0, 96.0);
          final iconIconSz = (iconSz * 0.44).clamp(22.0, 42.0);
          final fontSize = cellW > 120 ? 13.0 : 11.0;
          return Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 120),
              curve: Curves.easeInOut,
              width: iconSz, height: iconSz,
              decoration: BoxDecoration(
                color: _pressed ? _kOrange : (context.isDark ? const Color(0xFF0b013d) : _kNavy),
                borderRadius: BorderRadius.circular(iconSz * 0.29),
                border: null,
                boxShadow: [
                  BoxShadow(
                    color: (_pressed ? _kOrange : (context.isDark ? const Color(0xFF0b013d) : _kNavy)).withValues(alpha: 0.35),
                    blurRadius: _pressed ? 16 : 12,
                    offset: const Offset(0, 5),
                  ),
                ],
              ),
              child: Center(
                child: Icon(style.icon, color: Colors.white, size: iconIconSz),
              ),
            ),
            const SizedBox(height: 8),
            Text(
              widget.module.name,
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: fontSize,
                fontWeight: FontWeight.w700,
                color: _pressed ? _kOrange : (context.isDark ? Colors.white : context.colors.navyText),
                height: 1.25,
              ),
            ),
          ],
        );
        }),
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
      loading: () => _FallbackHeroBanner(height: (kIsWeb && MediaQuery.sizeOf(context).width >= 900) ? 240 : 170),
      error: (err, _) {
        debugPrint('❌ homeBannersProvider error: $err');
        return _FallbackHeroBanner(height: (kIsWeb && MediaQuery.sizeOf(context).width >= 900) ? 240 : 170);
      },
      data: (banners) {
        final bannerH = (kIsWeb && MediaQuery.sizeOf(context).width >= 900) ? 240.0 : 170.0;
        final real = banners
            .where((b) => b.imageUrl != null && b.imageUrl!.isNotEmpty)
            .toList();
        debugPrint('🖼 Banners loaded: ${real.length}');
        if (real.isEmpty) return _FallbackHeroBanner(height: bannerH);
        return Column(
          children: [
            SizedBox(
              height: bannerH,
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
  final double height;
  const _FallbackHeroBanner({this.height = 170});

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      height: height,
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
                      Text(
                        l.bannerTagline,
                        style: const TextStyle(
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
                        child: Text(
                          l.exploreNow,
                          style: const TextStyle(
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

// ── Notification bell with unread badge ───────────────────────────────────────

class _NotifBell extends ConsumerWidget {
  final ColorScheme cs;
  const _NotifBell({required this.cs});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final count = ref.watch(unreadNotificationCountProvider).valueOrNull ?? 0;
    return Stack(children: [
      IconButton(
        icon: Icon(Icons.notifications_outlined, color: cs.onSurface, size: 26),
        onPressed: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const NotificationScreen()),
        ).then((_) => ref.invalidate(unreadNotificationCountProvider)),
      ),
      if (count > 0)
        Positioned(
          top: 8, right: 6,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
            decoration: BoxDecoration(
              color: AppColors.primary,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              count > 99 ? '99+' : '$count',
              style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w700),
            ),
          ),
        ),
    ]);
  }
}

// ─── Theme cycle button (system → light → dark → system) ─────────────────────

class _ThemeCycleButton extends ConsumerWidget {
  const _ThemeCycleButton();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mode = ref.watch(appSettingsProvider).themeMode;
    final cs   = Theme.of(context).colorScheme;

    final (icon, color, next, tip) = switch (mode) {
      ThemeMode.system => (
        Icons.brightness_auto_rounded,
        cs.onSurface.withValues(alpha: 0.7),
        'light',
        'Auto (system)',
      ),
      ThemeMode.light => (
        Icons.wb_sunny_rounded,
        const Color(0xFFFFB300),
        'dark',
        'Light mode',
      ),
      ThemeMode.dark => (
        Icons.nightlight_round,
        const Color(0xFF90CAF9),
        'system',
        'Dark mode',
      ),
    };

    return IconButton(
      tooltip: tip,
      onPressed: () => ref.read(appSettingsProvider.notifier).setTheme(next),
      icon: AnimatedSwitcher(
        duration: const Duration(milliseconds: 280),
        transitionBuilder: (child, anim) => ScaleTransition(
          scale: anim,
          child: FadeTransition(opacity: anim, child: child),
        ),
        child: Icon(icon, key: ValueKey(mode), color: color, size: 22),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// SHARED HELPERS
// ─────────────────────────────────────────────────────────────────────────────

final _homeRepo = HomeRepository();

Widget _sectionHeader(BuildContext context, String title, {Widget? trailing}) =>
    Padding(
      padding: const EdgeInsets.fromLTRB(16, 24, 16, 12),
      child: Row(children: [
        Container(width: 4, height: 20, decoration: BoxDecoration(color: AppColors.primary, borderRadius: BorderRadius.circular(2))),
        const SizedBox(width: 10),
        Expanded(child: Text(title, style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: context.colors.navyText))),
        if (trailing != null) trailing,
      ]),
    );

Widget _shimmerBox({double? w, double? h, double r = 12}) =>
    Container(
      width: w, height: h,
      decoration: BoxDecoration(color: AppColors.shimmer, borderRadius: BorderRadius.circular(r)),
    );

Color _badgeColor(String? c) {
  switch ('$c'.toLowerCase()) {
    case 'green':  return Colors.green[700]!;
    case 'blue':   return Colors.blue[700]!;
    case 'orange': return Colors.orange[800]!;
    case 'purple': return Colors.purple[700]!;
    default:       return Colors.red[700]!;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. LIVE OFFERS SECTION
// ─────────────────────────────────────────────────────────────────────────────

class _LiveOffersSection extends StatefulWidget {
  const _LiveOffersSection();
  @override
  State<_LiveOffersSection> createState() => _LiveOffersSectionState();
}

class _LiveOffersSectionState extends State<_LiveOffersSection> {
  List<dynamic> _offers = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await _homeRepo.getLiveOffers();
      if (mounted) setState(() { _offers = data; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_loading && _offers.isEmpty) return const SizedBox.shrink();

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      _sectionHeader(
        context, '🔥 Live Offers',
        trailing: _loading ? null : Text('${_offers.length} active', style: TextStyle(fontSize: 12, color: AppColors.primary, fontWeight: FontWeight.w600)),
      ),
      SizedBox(
        height: 170,
        child: _loading
            ? ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: 4,
                itemBuilder: (_, __) => Padding(
                  padding: const EdgeInsets.only(right: 12),
                  child: _shimmerBox(w: 150, h: 170),
                ),
              )
            : ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: _offers.length,
                itemBuilder: (_, i) => _OfferCard(offer: _offers[i]),
              ),
      ),
    ]);
  }
}

class _OfferCard extends StatelessWidget {
  final dynamic offer;
  const _OfferCard({required this.offer});

  @override
  Widget build(BuildContext context) {
    final o       = offer as Map;
    final isEfood = o['type'] == 'efood';
    final badge   = o['badge'] as String? ?? '';
    final minsLeft = o['minutes_left'] as int?;
    final urgent  = minsLeft != null && minsLeft <= 120;

    return GestureDetector(
      onTap: () {
        final dl = o['deep_link'] as String?;
        if (dl != null && dl.isNotEmpty) context.push(dl);
      },
      child: Container(
        width: 150,
        margin: const EdgeInsets.only(right: 12),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          color: Theme.of(context).cardColor,
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.07), blurRadius: 10, offset: const Offset(0, 3))],
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Image
          Stack(children: [
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
              child: NetImage(
                url: o['image'] as String? ?? '',
                width: 150, height: 95, fit: BoxFit.cover,
              ),
            ),
            // Discount badge
            Positioned(top: 8, left: 8,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: _badgeColor(o['badge_color']),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(badge, style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w900)),
              ),
            ),
            // Module tag
            Positioned(top: 8, right: 8,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: 0.55),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(isEfood ? 'eFood' : 'eShop', style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700)),
              ),
            ),
          ]),
          Padding(
            padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(o['title'] as String? ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: context.colors.navyText), maxLines: 1, overflow: TextOverflow.ellipsis),
              if (o['subtitle'] != null && o['subtitle'] != o['title']) ...[
                const SizedBox(height: 2),
                Text(o['subtitle'] as String, style: TextStyle(fontSize: 10, color: Colors.grey[500]), maxLines: 1, overflow: TextOverflow.ellipsis),
              ],
              if (minsLeft != null) ...[
                const SizedBox(height: 4),
                Row(children: [
                  Icon(urgent ? Icons.timer_rounded : Icons.access_time_rounded,
                    size: 11, color: urgent ? Colors.red : Colors.grey[500]),
                  const SizedBox(width: 3),
                  Text(
                    minsLeft <= 60 ? '${minsLeft}m left' : '${(minsLeft / 60).floor()}h left',
                    style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600,
                      color: urgent ? Colors.red : Colors.grey[500]),
                  ),
                ]),
              ],
            ]),
          ),
        ]),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. HOME NEAR YOU SECTION
// ─────────────────────────────────────────────────────────────────────────────

class _HomeNearYouSection extends ConsumerStatefulWidget {
  const _HomeNearYouSection();
  @override
  ConsumerState<_HomeNearYouSection> createState() => _HomeNearYouSectionState();
}

class _HomeNearYouSectionState extends ConsumerState<_HomeNearYouSection> {
  List<dynamic> _items = [];
  bool _loading = true;
  String? _locationLabel;

  @override
  void initState() {
    super.initState();
    _initLocation();
  }

  Future<void> _initLocation() async {
    double? lat, lng;
    int? districtId;

    // Try GPS
    try {
      final enabled = await Geolocator.isLocationServiceEnabled();
      if (enabled) {
        var perm = await Geolocator.checkPermission();
        if (perm == LocationPermission.denied) perm = await Geolocator.requestPermission();
        if (perm == LocationPermission.whileInUse || perm == LocationPermission.always) {
          final pos = await Geolocator.getCurrentPosition(
            locationSettings: const LocationSettings(accuracy: LocationAccuracy.medium, timeLimit: Duration(seconds: 8)),
          );
          lat = pos.latitude; lng = pos.longitude;
          // No label shown for GPS mode (user doesn't see tracking info)
        }
      }
    } catch (_) {}

    // Fallback: district
    if (lat == null) {
      final user = ref.read(authStateProvider).valueOrNull;
      if (user?.districtId != null) {
        districtId = user!.districtId;
        if (user.districtLat != null && user.districtLng != null) {
          lat = user.districtLat; lng = user.districtLng;
        }
        if (mounted) setState(() => _locationLabel = user.districtName);
      }
    }

    if (lat == null && districtId == null) {
      if (mounted) setState(() => _loading = false);
      return;
    }

    try {
      final data = await _homeRepo.getNearYou(lat: lat, lng: lng, districtId: districtId);
      if (mounted) setState(() { _items = data; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_loading && _items.isEmpty) return const SizedBox.shrink();

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      _sectionHeader(
        context, '📍 Near You',
        trailing: _locationLabel != null
            ? Row(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.my_location_rounded, size: 12, color: AppColors.primary),
                const SizedBox(width: 3),
                Text(_locationLabel!, style: const TextStyle(fontSize: 11, color: AppColors.primary, fontWeight: FontWeight.w600)),
              ])
            : null,
      ),
      if (_loading)
        SizedBox(
          height: 130,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            itemCount: 4,
            itemBuilder: (_, __) => Padding(padding: const EdgeInsets.only(right: 12), child: _shimmerBox(w: 130, h: 130)),
          ),
        )
      else
        SizedBox(
          height: 130,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            itemCount: _items.length,
            itemBuilder: (_, i) => _NearYouCard(item: _items[i]),
          ),
        ),
    ]);
  }
}

class _NearYouCard extends StatelessWidget {
  final dynamic item;
  const _NearYouCard({required this.item});

  @override
  Widget build(BuildContext context) {
    final m       = item as Map;
    final type    = m['type'] as String? ?? '';
    final dist    = m['distance_km'];
    final distStr = dist != null ? '${dist} km' : null;
    final modTag  = m['module'] as String? ?? '';

    // Module tag color
    final tagColor = switch(type) {
      'efood'  => const Color(0xFFFF6B35),
      'eshop'  => const Color(0xFF2196F3),
      'erent'  => const Color(0xFF4CAF50),
      _        => AppColors.primary,
    };

    return GestureDetector(
      onTap: () {
        final dl = m['deep_link'] as String?;
        if (dl != null && dl.isNotEmpty) context.push(dl);
      },
      child: Container(
        width: 120,
        margin: const EdgeInsets.only(right: 12),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          color: Theme.of(context).cardColor,
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 8, offset: const Offset(0, 2))],
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Stack(children: [
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
              child: NetImage(
                url: m['image'] as String? ?? '',
                width: 120, height: 72, fit: BoxFit.cover,
              ),
            ),
            Positioned(top: 6, right: 6,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                decoration: BoxDecoration(color: tagColor, borderRadius: BorderRadius.circular(5)),
                child: Text(modTag, style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.w800)),
              ),
            ),
          ]),
          Padding(
            padding: const EdgeInsets.fromLTRB(8, 6, 8, 6),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(m['title'] as String? ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 11, color: context.colors.navyText), maxLines: 1, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 2),
              if (distStr != null)
                Row(children: [
                  Icon(Icons.near_me_rounded, size: 10, color: Colors.grey[400]),
                  const SizedBox(width: 2),
                  Text(distStr, style: TextStyle(fontSize: 10, color: Colors.grey[500])),
                ])
              else
                Text(m['subtitle'] as String? ?? '', style: TextStyle(fontSize: 10, color: Colors.grey[500]), maxLines: 1, overflow: TextOverflow.ellipsis),
            ]),
          ),
        ]),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. BEST SELLERS SECTION
// ─────────────────────────────────────────────────────────────────────────────

class _BestSellersSection extends StatefulWidget {
  const _BestSellersSection();
  @override
  State<_BestSellersSection> createState() => _BestSellersSectionState();
}

class _BestSellersSectionState extends State<_BestSellersSection> {
  late final Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _homeRepo.getBestSellers(limit: 10).catchError((_) => <dynamic>[]);
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<dynamic>>(
      future: _future,
      builder: (context, snap) {
        final products = snap.data ?? [];
        final loading = snap.connectionState != ConnectionState.done;

        if (!loading && products.isEmpty) return const SizedBox.shrink();

        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          _sectionHeader(context, '🏆 Best Sellers'),
          SizedBox(
            height: 185,
            child: loading
                ? ListView.builder(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: 5,
                    itemBuilder: (_, __) => Padding(padding: const EdgeInsets.only(right: 12), child: _shimmerBox(w: 130, h: 185)),
                  )
                : ListView.builder(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: products.length,
                    itemBuilder: (context, i) {
                      try {
                        return _BestSellerCard(product: products[i], rank: i + 1);
                      } catch (_) {
                        return const SizedBox(width: 130);
                      }
                    },
                  ),
          ),
        ]);
      },
    );
  }
}

class _BestSellerCard extends StatelessWidget {
  final dynamic product;
  final int rank;
  const _BestSellerCard({required this.product, required this.rank});

  @override
  Widget build(BuildContext context) {
    final p        = product as Map;
    // PHP PDO returns DECIMAL as strings — parse safely
    final price    = double.tryParse('${p['price'] ?? 0}') ?? 0.0;
    final salePrice = p['sale_price'] != null ? double.tryParse('${p['sale_price']}') : null;
    final hasDiscount = salePrice != null && salePrice > 0 && salePrice < price;
    final orders   = int.tryParse('${p['total_orders'] ?? 0}') ?? 0;

    // Rank colors: gold, silver, bronze, rest
    final rankColor = switch(rank) {
      1 => const Color(0xFFFFD700),
      2 => const Color(0xFFC0C0C0),
      3 => const Color(0xFFCD7F32),
      _ => Colors.grey[300]!,
    };

    return GestureDetector(
      onTap: () {
        final dl = p['deep_link'] as String?;
        if (dl != null && dl.isNotEmpty) context.push(dl);
      },
      child: Container(
        width: 130,
        margin: const EdgeInsets.only(right: 12),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          color: Theme.of(context).cardColor,
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.07), blurRadius: 10, offset: const Offset(0, 3))],
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Stack(children: [
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
              child: NetImage(
                url: p['thumbnail'] as String? ?? '',
                width: 130, height: 100, fit: BoxFit.cover,
              ),
            ),
            // Rank badge
            Positioned(top: 8, left: 8,
              child: Container(
                width: 28, height: 28,
                decoration: BoxDecoration(color: rankColor, shape: BoxShape.circle,
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.2), blurRadius: 4)]),
                child: Center(child: Text('#$rank', style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w900))),
              ),
            ),
            // Sale badge
            if (hasDiscount)
              Positioned(top: 8, right: 8,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                  decoration: BoxDecoration(color: Colors.red[700], borderRadius: BorderRadius.circular(6)),
                  child: Text(
                    '-${((price - salePrice!) / price * 100).toInt()}%',
                    style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w900),
                  ),
                ),
              ),
          ]),
          Padding(
            padding: const EdgeInsets.fromLTRB(9, 8, 9, 8),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(p['name'] as String? ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: context.colors.navyText), maxLines: 2, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 4),
              Text(p['vendor_name'] as String? ?? '', style: TextStyle(fontSize: 10, color: Colors.grey[500]), maxLines: 1, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 6),
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  if (hasDiscount)
                    Text('\$${salePrice!.toStringAsFixed(2)}',
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w900, color: AppColors.primary)),
                  if (hasDiscount)
                    Text('\$${price.toStringAsFixed(2)}',
                      style: TextStyle(fontSize: 10, color: Colors.grey[400], decoration: TextDecoration.lineThrough))
                  else
                    Text('\$${price.toStringAsFixed(2)}',
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w900, color: AppColors.primary)),
                ]),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                  decoration: BoxDecoration(color: AppColors.primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    Icon(Icons.shopping_bag_rounded, size: 9, color: AppColors.primary),
                    const SizedBox(width: 2),
                    Text('$orders', style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: AppColors.primary)),
                  ]),
                ),
              ]),
            ]),
          ),
        ]),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// RENT HOMES SECTION
// ─────────────────────────────────────────────────────────────────────────────

class _RentHomesSection extends StatefulWidget {
  const _RentHomesSection();
  @override
  State<_RentHomesSection> createState() => _RentHomesSectionState();
}

class _RentHomesSectionState extends State<_RentHomesSection> {
  late final Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _homeRepo.getRentHomes(limit: 8).catchError((_) => <dynamic>[]);
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<dynamic>>(
      future: _future,
      builder: (context, snap) {
        final homes = snap.data ?? [];
        final loading = snap.connectionState != ConnectionState.done;
        if (!loading && homes.isEmpty) return const SizedBox.shrink();

        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          _sectionHeader(context, '🏠 Available Homes',
              trailing: GestureDetector(
                onTap: () => context.push('/erent'),
                child: Text('See all', style: TextStyle(fontSize: 13, color: AppColors.primary, fontWeight: FontWeight.w700)),
              )),
          SizedBox(
            height: 240,
            child: loading
                ? ListView.builder(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: 4,
                    itemBuilder: (_, __) => Padding(
                        padding: const EdgeInsets.only(right: 14),
                        child: _shimmerBox(w: 200, h: 240)))
                : ListView.builder(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: homes.length,
                    itemBuilder: (context, i) {
                      try { return _RentHomeCard(home: homes[i]); }
                      catch (_) { return const SizedBox(width: 200); }
                    }),
          ),
        ]);
      },
    );
  }
}

class _RentHomeCard extends StatefulWidget {
  final dynamic home;
  const _RentHomeCard({required this.home});
  @override
  State<_RentHomeCard> createState() => _RentHomeCardState();
}

class _RentHomeCardState extends State<_RentHomeCard> {
  final PageController _pc = PageController();
  int _page = 0;
  late final List<String> _images;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    final h = widget.home as Map;
    final raw = h['images'];
    _images = raw is List && raw.isNotEmpty
        ? raw.whereType<String>().toList()
        : (h['thumbnail'] != null ? [h['thumbnail'] as String] : []);

    if (_images.length > 1) {
      _timer = Timer.periodic(const Duration(seconds: 3), (_) {
        if (!mounted) return;
        final next = (_page + 1) % _images.length;
        _pc.animateToPage(next, duration: const Duration(milliseconds: 500), curve: Curves.easeInOut);
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _pc.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final h      = widget.home as Map;
    final title  = h['title'] as String? ?? 'Property';
    final type   = (h['type'] as String? ?? 'apartment');
    final dist   = h['district_name'] as String? ?? '';
    final beds   = int.tryParse('${h['bedrooms'] ?? 0}') ?? 0;
    final baths  = int.tryParse('${h['bathrooms'] ?? 0}') ?? 0;
    final rent   = double.tryParse('${h['monthly_rent'] ?? 0}') ?? 0.0;
    final dl     = h['deep_link'] as String? ?? '/erent';

    return GestureDetector(
      onTap: () => context.push(dl),
      child: Container(
        width: 200,
        margin: const EdgeInsets.only(right: 14),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(18),
          color: Theme.of(context).cardColor,
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.08), blurRadius: 12, offset: const Offset(0, 4))],
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // ── Sliding images ──────────────────────────────────────────────
          ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(18)),
            child: Stack(children: [
              SizedBox(
                height: 140, width: 200,
                child: _images.isEmpty
                    ? Container(color: Colors.grey[200], child: const Icon(Icons.home_outlined, size: 48, color: Colors.grey))
                    : PageView.builder(
                        controller: _pc,
                        itemCount: _images.length,
                        onPageChanged: (i) => setState(() => _page = i),
                        itemBuilder: (_, i) => NetImage(
                          url: _images[i], width: 200, height: 140, fit: BoxFit.cover,
                        ),
                      ),
              ),
              // Type badge
              Positioned(top: 10, left: 10,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFF07003B).withValues(alpha: 0.82),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    type[0].toUpperCase() + type.substring(1),
                    style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800),
                  ),
                ),
              ),
              // Page dots (only if multiple images)
              if (_images.length > 1)
                Positioned(bottom: 8, left: 0, right: 0,
                  child: Row(mainAxisAlignment: MainAxisAlignment.center,
                    children: List.generate(_images.length, (i) => AnimatedContainer(
                      duration: const Duration(milliseconds: 300),
                      width: _page == i ? 16 : 6, height: 5,
                      margin: const EdgeInsets.symmetric(horizontal: 2),
                      decoration: BoxDecoration(
                        color: _page == i ? AppColors.primary : Colors.white.withValues(alpha: 0.7),
                        borderRadius: BorderRadius.circular(3),
                      ),
                    )),
                  ),
                ),
            ]),
          ),
          // ── Info ────────────────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(title, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: context.colors.navyText),
                  maxLines: 1, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 4),
              Row(children: [
                Icon(Icons.location_on, size: 11, color: Colors.grey[500]),
                const SizedBox(width: 2),
                Expanded(child: Text(dist, style: TextStyle(fontSize: 11, color: Colors.grey[500]),
                    maxLines: 1, overflow: TextOverflow.ellipsis)),
              ]),
              const SizedBox(height: 8),
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                // Beds + baths
                Row(children: [
                  Icon(Icons.bed_outlined, size: 13, color: Colors.grey[600]),
                  const SizedBox(width: 3),
                  Text('$beds', style: TextStyle(fontSize: 11, color: Colors.grey[600], fontWeight: FontWeight.w600)),
                  const SizedBox(width: 8),
                  Icon(Icons.bathtub_outlined, size: 13, color: Colors.grey[600]),
                  const SizedBox(width: 3),
                  Text('$baths', style: TextStyle(fontSize: 11, color: Colors.grey[600], fontWeight: FontWeight.w600)),
                ]),
                // Price
                Text('\$${rent.toStringAsFixed(0)}/mo',
                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w900, color: AppColors.primary)),
              ]),
            ]),
          ),
        ]),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// UPCOMING FLIGHTS SECTION
// ─────────────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────────────────────
// FLIGHTS SECTION — 3-column grid carousel (3 per page, swipe for more)
// ─────────────────────────────────────────────────────────────────────────────

class _FlightsSection extends StatefulWidget {
  const _FlightsSection();
  @override
  State<_FlightsSection> createState() => _FlightsSectionState();
}

class _FlightsSectionState extends State<_FlightsSection> {
  late final Future<List<dynamic>> _future;
  final PageController _pc = PageController();
  int _page = 0;

  @override
  void initState() {
    super.initState();
    _future = _homeRepo.getUpcomingFlights(limit: 9).catchError((_) => <dynamic>[]);
  }

  @override
  void dispose() {
    _pc.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<dynamic>>(
      future: _future,
      builder: (context, snap) {
        final flights = snap.data ?? [];
        final loading = snap.connectionState != ConnectionState.done;
        if (!loading && flights.isEmpty) return const SizedBox.shrink();

        // Group into pages of 3
        final pages = <List<dynamic>>[];
        for (var i = 0; i < (loading ? 1 : flights.length); i += 3) {
          pages.add(loading ? [] : flights.sublist(i, (i + 3).clamp(0, flights.length)));
        }
        final pageCount = loading ? 1 : pages.length;

        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          _sectionHeader(context, '✈️ Upcoming Flights',
              trailing: GestureDetector(
                onTap: () => context.push('/eticket'),
                child: Text('Book now', style: TextStyle(fontSize: 13, color: AppColors.primary, fontWeight: FontWeight.w700)),
              )),
          // ── Carousel ────────────────────────────────────────────────────
          SizedBox(
            height: loading ? 310 : (pages.first.length == 1 ? 110 : pages.first.length == 2 ? 210 : 310),
            child: loading
                ? Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Column(children: List.generate(3, (_) => Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _shimmerBox(w: double.infinity, h: 90)))))
                : PageView.builder(
                    controller: _pc,
                    itemCount: pageCount,
                    onPageChanged: (i) => setState(() => _page = i),
                    itemBuilder: (_, pi) {
                      final group = pages[pi];
                      return Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Column(
                          children: group.map((f) {
                            try { return _FlightCard(flight: f); }
                            catch (_) { return const SizedBox.shrink(); }
                          }).toList(),
                        ),
                      );
                    },
                  ),
          ),
          // ── Page dots (only if multiple pages) ──────────────────────────
          if (!loading && pageCount > 1) ...[
            const SizedBox(height: 8),
            Row(mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(pageCount, (i) => AnimatedContainer(
                duration: const Duration(milliseconds: 300),
                width: _page == i ? 20 : 6, height: 6,
                margin: const EdgeInsets.symmetric(horizontal: 2),
                decoration: BoxDecoration(
                  color: _page == i ? AppColors.primary : Colors.grey[300]!,
                  borderRadius: BorderRadius.circular(3),
                ),
              )),
            ),
            const SizedBox(height: 4),
          ],
        ]);
      },
    );
  }
}

class _FlightCard extends StatelessWidget {
  final dynamic flight;
  const _FlightCard({required this.flight});

  @override
  Widget build(BuildContext context) {
    final f         = flight as Map;
    final airline   = f['airline'] as String? ?? 'Airline';
    final flightNo  = f['flight_number'] as String? ?? '';
    final fromCode  = (f['from_code'] as String? ?? '???').toUpperCase();
    final toCode    = (f['to_code']   as String? ?? '???').toUpperCase();
    final fromCity  = f['from_city'] as String? ?? '';
    final toCity    = f['to_city']   as String? ?? '';
    final depAt     = f['departure_at'] as String? ?? '';
    final duration  = f['duration'] as String?;
    final seats     = int.tryParse('${f['available_seats'] ?? 0}') ?? 0;
    // economy_price from HomeController, or fallback to seat_classes
    double price = double.tryParse('${f['economy_price'] ?? 0}') ?? 0.0;
    if (price == 0) {
      final cls = f['seat_classes'];
      if (cls is Map) price = double.tryParse(cls['economy']?.toString() ?? '0') ?? 0.0;
    }
    final accentHex = f['airline_color'] as String? ?? '#1a73e8';
    final dl        = f['deep_link'] as String? ?? '/eticket';

    Color accent = AppColors.primary;
    try {
      final hex = accentHex.replaceFirst('#', '');
      accent = Color(0xFF000000 | int.parse(hex.length == 6 ? hex : '1a73e8', radix: 16));
    } catch (_) {}

    String depTime = '', depDate = '';
    try {
      final dt = DateTime.parse(depAt).toLocal();
      depTime  = '${dt.hour.toString().padLeft(2,'0')}:${dt.minute.toString().padLeft(2,'0')}';
      final mo = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      depDate  = '${dt.day} ${mo[dt.month - 1]}';
    } catch (_) {}

    final bool lowSeats = seats <= 5 && seats > 0;

    return GestureDetector(
      onTap: () => context.push(dl),
      child: Container(
        height: 90,
        margin: const EdgeInsets.only(bottom: 10),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          color: Theme.of(context).cardColor,
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.07), blurRadius: 8, offset: const Offset(0, 2))],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(14),
          child: Row(children: [
            // ── Airline badge ──────────────────────────────────────────
            Container(
              width: 56,
              color: accent.withValues(alpha: 0.10),
              child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                Container(
                  width: 34, height: 34,
                  decoration: BoxDecoration(color: accent, shape: BoxShape.circle),
                  child: Center(child: Text(
                    airline.isNotEmpty ? airline[0].toUpperCase() : '✈',
                    style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w900),
                  )),
                ),
                const SizedBox(height: 3),
                Text(flightNo, style: TextStyle(fontSize: 7.5, fontWeight: FontWeight.w700, color: accent),
                    maxLines: 1, overflow: TextOverflow.ellipsis),
              ]),
            ),
            // ── Route ─────────────────────────────────────────────────
            Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                    Expanded(child: Text(airline,
                        style: TextStyle(fontSize: 10, color: Colors.grey[500], fontWeight: FontWeight.w600),
                        maxLines: 1, overflow: TextOverflow.ellipsis)),
                    if (depDate.isNotEmpty)
                      Text(depDate, style: TextStyle(fontSize: 10, color: Colors.grey[500])),
                  ]),
                  const SizedBox(height: 5),
                  Row(children: [
                    Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(fromCode, style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: context.colors.navyText, height: 1.0)),
                      Text(fromCity, style: TextStyle(fontSize: 9, color: Colors.grey[500]), maxLines: 1, overflow: TextOverflow.ellipsis),
                    ]),
                    Expanded(child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 6),
                      child: Column(children: [
                        if (duration != null) Text(duration, style: TextStyle(fontSize: 9, color: Colors.grey[400])),
                        Row(children: [
                          Expanded(child: Container(height: 1, color: Colors.grey[300])),
                          Padding(padding: const EdgeInsets.symmetric(horizontal: 3),
                              child: Icon(Icons.flight, size: 12, color: Colors.grey[400])),
                          Expanded(child: Container(height: 1, color: Colors.grey[300])),
                        ]),
                        if (depTime.isNotEmpty)
                          Text(depTime, style: const TextStyle(fontSize: 9, color: AppColors.primary, fontWeight: FontWeight.w700)),
                      ]),
                    )),
                    Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                      Text(toCode, style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: context.colors.navyText, height: 1.0)),
                      Text(toCity, style: TextStyle(fontSize: 9, color: Colors.grey[500]), maxLines: 1, overflow: TextOverflow.ellipsis),
                    ]),
                  ]),
                ]),
              ),
            ),
            // ── Price + seats ──────────────────────────────────────────
            Container(
              width: 68,
              decoration: BoxDecoration(border: Border(left: BorderSide(color: Colors.grey.withValues(alpha: 0.15)))),
              child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                Text('\$${price.toStringAsFixed(0)}',
                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900, color: AppColors.primary)),
                Text('per seat', style: TextStyle(fontSize: 8, color: Colors.grey[500])),
                const SizedBox(height: 4),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                  decoration: BoxDecoration(
                    color: lowSeats ? Colors.red[50] : Colors.green[50],
                    borderRadius: BorderRadius.circular(5),
                  ),
                  child: Text('$seats left', style: TextStyle(
                    fontSize: 8, fontWeight: FontWeight.w800,
                    color: lowSeats ? Colors.red[700] : Colors.green[700],
                  )),
                ),
              ]),
            ),
          ]),
        ),
      ),
    );
  }
}

