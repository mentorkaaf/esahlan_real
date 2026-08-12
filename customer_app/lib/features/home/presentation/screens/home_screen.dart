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
  List<dynamic> _products = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await _homeRepo.getBestSellers(limit: 10);
      if (mounted) setState(() { _products = data; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_loading && _products.isEmpty) return const SizedBox.shrink();

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      _sectionHeader(context, '🏆 Best Sellers'),
      SizedBox(
        height: 185,
        child: _loading
            ? ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: 5,
                itemBuilder: (_, __) => Padding(padding: const EdgeInsets.only(right: 12), child: _shimmerBox(w: 130, h: 185)),
              )
            : ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: _products.length,
                itemBuilder: (_, i) => _BestSellerCard(product: _products[i], rank: i + 1),
              ),
      ),
    ]);
  }
}

class _BestSellerCard extends StatelessWidget {
  final dynamic product;
  final int rank;
  const _BestSellerCard({required this.product, required this.rank});

  @override
  Widget build(BuildContext context) {
    final p        = product as Map;
    final price    = (p['price'] as num?)?.toDouble() ?? 0;
    final salePrice = (p['sale_price'] as num?)?.toDouble();
    final hasDiscount = salePrice != null && salePrice < price;
    final orders   = (p['total_orders'] as num?)?.toInt() ?? 0;

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


