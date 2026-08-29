import 'dart:async';
import 'package:flutter/material.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/services/cart_sync_service.dart';
import '../../../core/services/cart_persistence_service.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/storage/local_storage.dart';
import '../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../core/theme/app_color_tokens.dart';
import '../../../core/utils/error_handler.dart';
import '../../../core/widgets/network_image_widget.dart';
import '../../payment/waafi_pay_sheet.dart';
import '../../payment/mobile_pay_sheet.dart';
import '../../payment/payment_method_section.dart';
import '../../../shared/widgets/wallet_pin_dialog.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../features/wallet/presentation/providers/wallet_provider.dart';
import '../../ads/services/ad_service.dart';
import '../../auth/data/models/district_model.dart';
import '../../auth/data/repositories/district_repository.dart';
import '../../auth/presentation/providers/auth_provider.dart';
import '../../rewards/redeem_points_bar.dart';

final _efoodDistrictsProvider = FutureProvider<List<DistrictModel>>(
  (_) => DistrictRepository().getDistricts(),
);

// ════════════════════════════════════════════════════════════════════
// CONSTANTS (theme-agnostic only)
// ════════════════════════════════════════════════════════════════════

const _primary   = AppColors.primary;
const _secondary = AppColors.secondary;

Widget _shimmer({double? w, double? h, double r = 10}) => Container(
  width: w, height: h,
  decoration: BoxDecoration(color: AppColors.shimmer, borderRadius: BorderRadius.circular(r)),
);
// _bg and _card are light-mode defaults; scaffold/header backgrounds use context.colors in build()
const _bg        = Color(0xFFF4F5FA);
const _card      = Colors.white;

// ─── SQLite returns 0/1 integers for booleans — convert safely ───────
bool _asBool(dynamic v) {
  if (v == null) return false;
  if (v is bool) return v;
  if (v is int)  return v != 0;
  if (v is String) return v == '1' || v.toLowerCase() == 'true';
  return false;
}

// ─── Parse badge color name → Color ──────────────────────────────────
Color _parseBadgeColor(dynamic colorName) {
  switch ('$colorName'.toLowerCase()) {
    case 'red':    return Colors.red;
    case 'green':  return Colors.green[700]!;
    case 'blue':   return Colors.blue[700]!;
    case 'orange': return Colors.orange[800]!;
    case 'purple': return Colors.purple[700]!;
    case 'pink':   return Colors.pink[600]!;
    case 'teal':   return Colors.teal[600]!;
    default:       return Colors.red;
  }
}

// ════════════════════════════════════════════════════════════════════
// SERVICE
// ════════════════════════════════════════════════════════════════════

final _svc = ModuleApiService.create();

// ════════════════════════════════════════════════════════════════════
// PROVIDERS
// ════════════════════════════════════════════════════════════════════

final _bannersProvider    = FutureProvider((_) => _svc.getFoodBanners());
final _catsProvider       = FutureProvider((_) => _svc.getFoodCategories());
final _favoritesProvider  = FutureProvider((_) => _svc.getFoodFavorites());
final _ordersProvider     = FutureProvider((_) => _svc.getFoodOrders());

// ── Restaurant list provider ──────────────────────────────────────────
// KEY must be a String (not Map!) so Riverpod can do proper equality checks.
// Encoding: "search|category|featured|topRated|lat|lng"
final _restaurantsProvider = FutureProvider.family<dynamic, String>((_, key) {
  final parts = key.split('|');
  final search   = parts[0].isEmpty ? null : parts[0];
  final category = parts[1].isEmpty ? null : parts[1];
  final featured = parts[2] == '1' ? true  : (parts[2] == '0' ? false : null);
  final topRated = parts[3] == '1' ? true  : (parts[3] == '0' ? false : null);
  final lat      = parts.length > 4 && parts[4].isNotEmpty ? double.tryParse(parts[4]) : null;
  final lng      = parts.length > 5 && parts[5].isNotEmpty ? double.tryParse(parts[5]) : null;
  return _svc.getRestaurants(
    search:   search,
    category: category,
    featured: featured,
    topRated: topRated,
    lat: lat,
    lng: lng,
  );
});

/// Build the string key for [_restaurantsProvider].
String _rKey({String? search, String? category, bool? featured, bool? topRated, double? lat, double? lng}) =>
    '${search ?? ""}|${category ?? ""}|${featured == null ? "" : featured ? "1" : "0"}|${topRated == null ? "" : topRated ? "1" : "0"}|${lat?.toStringAsFixed(5) ?? ""}|${lng?.toStringAsFixed(5) ?? ""}';

final _restaurantProvider = FutureProvider.family<dynamic, int>((_, id) =>
    _svc.getRestaurant(id));

// ── Menu/products provider ─────────────────────────────────────────────
// KEY: "restaurantId|categoryId|search"
final _menuProvider = FutureProvider.family<dynamic, String>((_, key) {
  final parts      = key.split('|');
  final rid        = int.parse(parts[0]);
  final categoryId = parts[1].isEmpty ? null : int.tryParse(parts[1]);
  final search     = parts[2].isEmpty ? null : parts[2];
  return _svc.getRestaurantProducts(rid, categoryId: categoryId, search: search);
});

/// Build the string key for [_menuProvider].
String _mKey(int restaurantId, {int? categoryId, String? search}) =>
    '$restaurantId|${categoryId ?? ""}|${search ?? ""}';

final _restaurantCouponsProvider   = FutureProvider.family<dynamic, int>((_, id) =>
    _svc.getRestaurantCoupons(id));

final _restaurantCampaignsProvider = FutureProvider.family<dynamic, int>((_, id) =>
    _svc.getRestaurantCampaigns(id));


final _trackProvider = FutureProvider.family<dynamic, int>((_, id) =>
    _svc.trackFoodOrder(id));

// ════════════════════════════════════════════════════════════════════
// CART STATE
// ════════════════════════════════════════════════════════════════════

class _CartItem {
  final dynamic product;
  final List<dynamic> addons;
  final String? size;
  final String? instructions;
  final double? overridePrice; // discounted base price (from active campaign)
  int qty;

  _CartItem({
    required this.product,
    this.addons = const [],
    this.size,
    this.instructions,
    this.overridePrice,
    this.qty = 1,
  });

  double get originalPrice => double.tryParse('${product['price'] ?? 0}') ?? 0;

  double get unitPrice {
    double base = overridePrice ?? originalPrice;
    double addonTotal = addons.fold(0.0, (s, a) =>
        s + (double.tryParse('${a['price'] ?? 0}') ?? 0));
    return base + addonTotal;
  }

  bool get hasDiscount => overridePrice != null && overridePrice! < originalPrice;

  double get total => unitPrice * qty;

  String get key => '${product['id']}_${size}_${addons.map((a) => a['id']).join('-')}';
}

class _CartNotifier extends StateNotifier<List<_CartItem>> {
  _CartNotifier() : super([]) {
    _loadFromDisk();
  }

  static const _module = 'efood';

  Future<void> _loadFromDisk() async {
    final saved = await CartPersistenceService.instance.load(_module);
    if (saved.isEmpty || !mounted) return;
    final items = saved.map((m) {
      final item = _CartItem(
        product: Map<String, dynamic>.from(m['product'] as Map? ?? {}),
        qty: (m['qty'] as num?)?.toInt() ?? 1,
        addons: (m['addons'] as List?)?.map((a) => Map<String, dynamic>.from(a as Map)).toList() ?? [],
        size: m['size'] as String?,
        overridePrice: (m['overridePrice'] as num?)?.toDouble(),
      );
      return item;
    }).toList();
    if (mounted) state = items;
  }

  void _saveToDisk() {
    final data = state.map((e) => {
      'product':       e.product,
      'qty':           e.qty,
      'addons':        e.addons,
      'size':          e.size,
      'overridePrice': e.overridePrice,
    }).toList();
    CartPersistenceService.instance.save(_module, data);
  }

  void _syncToBackend() {
    final items = state.map((e) => {
      'product_id':    e.product['id'],
      'product_name':  e.product['name'] ?? '',
      'product_image': e.product['image'] ?? e.product['thumbnail'] ?? '',
      'price':         e.unitPrice,
      'quantity':      e.qty,
    }).toList();
    CartSyncService.instance.syncDebounced(_module, items);
  }

  void add(_CartItem item) {
    final idx = state.indexWhere((e) => e.key == item.key);
    if (idx >= 0) {
      final updated = [...state];
      updated[idx].qty += item.qty;
      state = updated;
    } else {
      state = [...state, item];
    }
    _saveToDisk();
    _syncToBackend();
  }

  void increment(String key) {
    state = [for (final e in state) if (e.key == key)
      _CartItem(product: e.product, addons: e.addons, size: e.size, overridePrice: e.overridePrice, qty: e.qty + 1)
    else e];
    _saveToDisk();
    _syncToBackend();
  }

  void decrement(String key) {
    final updated = state.map((e) {
      if (e.key == key) {
        return _CartItem(product: e.product, addons: e.addons, size: e.size, overridePrice: e.overridePrice, qty: e.qty - 1);
      }
      return e;
    }).where((e) => e.qty > 0).toList();
    state = updated;
    _saveToDisk();
    _syncToBackend();
  }

  void remove(String key) {
    state = state.where((e) => e.key != key).toList();
    _saveToDisk();
    _syncToBackend();
  }

  void clear() {
    state = [];
    CartPersistenceService.instance.clear(_module);
    CartSyncService.instance.clearModule(_module);
  }

  double get subtotal => state.fold(0, (s, e) => s + e.total);
  int get totalItems  => state.fold(0, (s, e) => s + e.qty);
}

final _cartProvider = StateNotifierProvider<_CartNotifier, List<_CartItem>>(
    (_) => _CartNotifier());

// ════════════════════════════════════════════════════════════════════
// FAVORITES STATE
// ════════════════════════════════════════════════════════════════════

class _FavNotifier extends StateNotifier<Set<int>> {
  _FavNotifier() : super({});

  void setAll(List ids) {
    state = ids.map((e) => (e as num).toInt()).toSet();
  }

  void toggle(int id) async {
    // Optimistic update
    if (state.contains(id)) {
      state = {...state}..remove(id);
    } else {
      state = {...state, id};
    }
    // Sync with server (fire-and-forget)
    try {
      await _svc.toggleFoodFavorite(id);
    } catch (_) {}
  }

  bool has(int id) => state.contains(id);
}

final _favProvider = StateNotifierProvider<_FavNotifier, Set<int>>(
    (_) => _FavNotifier());

// ════════════════════════════════════════════════════════════════════
// BOTTOM NAV INDEX
// ════════════════════════════════════════════════════════════════════

// ════════════════════════════════════════════════════════════════════
// ROOT SCREEN
// ════════════════════════════════════════════════════════════════════

class EFoodScreen extends ConsumerWidget {
  const EFoodScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: const _HomeTab(),
    );
  }
}

// ════════════════════════════════════════════════════════════════════
// HOME TAB
// ════════════════════════════════════════════════════════════════════

class _HomeTab extends ConsumerStatefulWidget {
  const _HomeTab();
  @override
  ConsumerState<_HomeTab> createState() => _HomeTabState();
}

class _HomeTabState extends ConsumerState<_HomeTab> {
  final _searchCtrl = TextEditingController();
  String _selectedCat = '';
  double? _lat;
  double? _lng;

  @override
  void initState() {
    super.initState();
    AdService.instance.triggerModulePopups(context, 'efood');
    // Sync favorite restaurant IDs from server on startup
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      try {
        final data = await _svc.getFoodFavorites();
        final raw  = data is Map ? (data['data'] ?? []) : (data is List ? data : []);
        final list = raw is List ? raw : [];
        if (list.isNotEmpty) {
          final ids = list.map((r) => (r['id'] as num?)?.toInt()).whereType<int>().toList();
          ref.read(_favProvider.notifier).setAll(ids);
        }
      } catch (_) {}
      // Silently get last known GPS for distance display on restaurant cards.
      // Uses cached position — no permission prompt, no blocking.
      try {
        final pos = await Geolocator.getLastKnownPosition();
        if (pos != null && mounted) {
          setState(() { _lat = pos.latitude; _lng = pos.longitude; });
        }
      } catch (_) {}
    });
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return CustomScrollView(
      slivers: [
        _buildHeader(context),
        SliverToBoxAdapter(child: _buildSearch(context, l)),
        SliverToBoxAdapter(child: _BannerSlider()),
        SliverToBoxAdapter(child: _buildCategories(l)),
        SliverToBoxAdapter(child: _buildSection(l.popularRestaurants, featured: true, l: l, lat: _lat, lng: _lng)),
        SliverToBoxAdapter(child: _buildSection(l.topRated, topRated: true, l: l, lat: _lat, lng: _lng)),
        const SliverToBoxAdapter(child: _NearYouSection()),
        const SliverToBoxAdapter(child: SizedBox(height: 24)),
      ],
    );
  }

  SliverAppBar _buildHeader(BuildContext context) => SliverAppBar(
    floating: true,
    snap: true,
    backgroundColor: context.colors.cardBg,
    elevation: 0,
    automaticallyImplyLeading: false,
    expandedHeight: 60,
    flexibleSpace: FlexibleSpaceBar(
      background: Container(
        color: context.colors.cardBg,
        padding: const EdgeInsets.fromLTRB(16, 44, 16, 8),
        child: Row(
          children: [
            Expanded(
              child: Text(
                'eSahlan',
                style: TextStyle(
                  color: _primary,
                  fontWeight: FontWeight.w900,
                  fontSize: 20,
                  letterSpacing: 0.2,
                ),
              ),
            ),
            Stack(
              clipBehavior: Clip.none,
              children: [
                Container(
                  width: 38, height: 38,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: context.colors.inputFill,
                    border: Border.all(color: context.colors.borderColor),
                  ),
                  child: Icon(Icons.notifications_none_rounded, color: context.colors.navyText, size: 20),
                ),
                Positioned(
                  right: 2, top: 2,
                  child: Container(
                    width: 9, height: 9,
                    decoration: const BoxDecoration(color: _primary, shape: BoxShape.circle),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    ),
  );

  Widget _buildSearch(BuildContext context, AppL10n l) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
    child: Row(children: [
      Expanded(
        child: GestureDetector(
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const _SearchTab())),
          child: Container(
            height: 48,
            decoration: BoxDecoration(
              color: context.colors.cardBg,
              borderRadius: BorderRadius.circular(14),
              boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 10)],
            ),
            child: Row(children: [
              const SizedBox(width: 14),
              Icon(Icons.search_rounded, color: context.colors.mutedText),
              const SizedBox(width: 8),
              Text(l.searchFoodHint, style: TextStyle(color: context.colors.mutedText, fontSize: 13)),
            ]),
          ),
        ),
      ),
      const SizedBox(width: 10),
      Container(
        width: 48, height: 48,
        decoration: BoxDecoration(color: _primary, borderRadius: BorderRadius.circular(14)),
        child: Icon(Icons.tune_rounded, color: context.colors.cardBg),
      ),
    ]),
  );

  Widget _buildCategories(AppL10n l) {
    final cats = ref.watch(_catsProvider);

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Text(l.foodCategories, style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: context.colors.navyText)),
          TextButton(onPressed: () {}, child: Text(l.viewAll, style: const TextStyle(color: _primary, fontWeight: FontWeight.w600))),
        ]),
        const SizedBox(height: 10),
        cats.when(
          data: (data) {
            final raw = data is Map ? (data['data'] ?? data) : data;
            final list = (raw is List && raw.isNotEmpty) ? raw : <dynamic>[];
            if (list.isEmpty) return const SizedBox.shrink();
            final shown = list.length > 10 ? list.sublist(0, 10) : list;
            return SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: shown.asMap().entries.map<Widget>((e) {
                  final c = e.value;
                  final name = c['name'] ?? '';
                  final sel = _selectedCat == name;
                  return GestureDetector(
                    onTap: () => setState(() => _selectedCat = sel ? '' : name),
                    child: Container(
                      width: 66,
                      margin: const EdgeInsets.only(right: 8),
                      child: Column(mainAxisSize: MainAxisSize.min, children: [
                        Container(
                          width: 52, height: 52,
                          decoration: BoxDecoration(
                            color: sel ? _primary : context.colors.cardBg,
                            borderRadius: BorderRadius.circular(14),
                            boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.07), blurRadius: 8)],
                          ),
                          child: c['image'] != null
                              ? ClipRRect(borderRadius: BorderRadius.circular(14), child: NetImage(url: c['image'], fit: BoxFit.cover))
                              : const Center(child: Icon(Icons.fastfood_rounded, color: _primary, size: 24)),
                        ),
                        const SizedBox(height: 6),
                        Text(name, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: sel ? _primary : context.colors.bodyText), textAlign: TextAlign.center, maxLines: 1, overflow: TextOverflow.ellipsis),
                      ]),
                    ),
                  );
                }).toList(),
              ),
            );
          },
          loading: () => SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(children: List.generate(5, (_) => Padding(
              padding: const EdgeInsets.only(right: 8),
              child: Column(mainAxisSize: MainAxisSize.min, children: [_shimmer(w: 52, h: 52, r: 14), const SizedBox(height: 6), _shimmer(w: 40, h: 10)]),
            ))),
          ),
          error: (_, __) => const SizedBox.shrink(),
        ),
      ]),
    );
  }

  Widget _buildSection(String title, {bool featured = false, bool topRated = false, required AppL10n l, double? lat, double? lng}) {
    final restaurants = ref.watch(_restaurantsProvider(_rKey(
      featured: featured,
      topRated: topRated,
      category: _selectedCat.isEmpty ? null : _selectedCat,
      lat: lat,
      lng: lng,
    )));

    return Padding(
      padding: const EdgeInsets.fromLTRB(0, 10, 0, 0),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text(title, style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: context.colors.navyText)),
            TextButton(onPressed: () {}, child: Text(l.viewAll, style: const TextStyle(color: _primary, fontWeight: FontWeight.w600))),
          ]),
        ),
        const SizedBox(height: 12),
        restaurants.when(
          data: (data) {
            final list = data is List ? data : (data['data'] ?? []);
            if (list.isEmpty) return const SizedBox.shrink();
            final shown = list; // show all — no artificial limit
            return Column(
              children: [
                for (int i = 0; i < shown.length; i++)
                  _RestaurantCard(restaurant: shown[i], onTap: () => _openRestaurant(context, shown[i])),
              ],
            );
          },
          loading: () => Column(
            children: List.generate(3, (_) => Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
              child: _shimmer(w: double.infinity, h: 90, r: 16),
            )),
          ),
          error: (e, __) => SizedBox(
            height: 60,
            child: Center(child: Text('⚠️ $e', style: const TextStyle(fontSize: 12, color: Colors.red))),
          ),
        ),
      ]),
    );
  }

  void _openRestaurant(BuildContext context, dynamic r) {
    Navigator.push(context, MaterialPageRoute(builder: (_) => _RestaurantDetailPage(restaurant: r)));
  }
}

// ════════════════════════════════════════════════════════════════════
// BANNER SLIDER
// ════════════════════════════════════════════════════════════════════

class _BannerSlider extends ConsumerStatefulWidget {
  @override
  ConsumerState<_BannerSlider> createState() => _BannerSliderState();
}

class _BannerSliderState extends ConsumerState<_BannerSlider> {
  final _ctrl = PageController();
  int _page = 0;
  Timer? _timer;

  int _bannerCount = 1;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 4), (_) {
      if (!mounted || !_ctrl.hasClients || _bannerCount <= 1) return;
      final current = _ctrl.page?.round() ?? 0;
      final next = (current + 1) % _bannerCount;
      _ctrl.animateToPage(next, duration: const Duration(milliseconds: 400), curve: Curves.easeInOut);
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
    final banners = ref.watch(_bannersProvider);

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
      child: banners.when(
        data: (data) {
          final raw = data is Map ? (data['data'] ?? data) : data;
          final list = (raw is List ? raw : [])
              .where((b) {
                final img = b['image_url'] ?? b['image'];
                return img != null && img.toString().isNotEmpty;
              })
              .toList();
          if (list.isEmpty) return const SizedBox.shrink();
          _bannerCount = list.length;
          return _buildSlider(list);
        },
        loading: () => const SizedBox.shrink(),
        error: (_, __) => const SizedBox.shrink(),
      ),
    );
  }

  Widget _buildSlider(List list) => Column(children: [
    SizedBox(
      height: 160,
      child: PageView.builder(
        controller: _ctrl,
        onPageChanged: (p) => setState(() => _page = p),
        itemCount: list.length,
        itemBuilder: (_, i) {
          final b = list[i];
          return Container(
            margin: const EdgeInsets.symmetric(horizontal: 2),
            decoration: BoxDecoration(borderRadius: BorderRadius.circular(18)),
            clipBehavior: Clip.antiAlias,
            child: _NetImg(
                url: b['image_url'] ?? b['image'],
                width: double.infinity, height: 160, radius: 0,
                fallback: Container(
                  height: 160,
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      colors: [Color(0xFF07003B), Color(0xFF1A0066)],
                      begin: Alignment.topLeft, end: Alignment.bottomRight,
                    ),
                  ),
                  child: b['title'] != null && '${b['title']}'.isNotEmpty
                      ? Center(child: Text('${b['title']}', style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700)))
                      : const Center(child: Icon(Icons.image_outlined, color: Colors.white30, size: 48)),
                )),
          );
        },
      ),
    ),
    const SizedBox(height: 10),
    Row(mainAxisAlignment: MainAxisAlignment.center, children: List.generate(list.length, (i) => AnimatedContainer(
      duration: const Duration(milliseconds: 300),
      margin: const EdgeInsets.symmetric(horizontal: 3),
      width: _page == i ? 20 : 6, height: 6,
      decoration: BoxDecoration(
        color: _page == i ? _primary : Colors.grey[300],
        borderRadius: BorderRadius.circular(3),
      ),
    ))),
  ]);

}

// ════════════════════════════════════════════════════════════════════
// RESTAURANT CARD
// ════════════════════════════════════════════════════════════════════

class _RestaurantCard extends ConsumerWidget {
  final dynamic restaurant;
  final VoidCallback onTap;

  const _RestaurantCard({required this.restaurant, required this.onTap});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final r    = restaurant;
    final rid  = (r['id'] as num?)?.toInt() ?? 0;
    final isFav = ref.watch(_favProvider).contains(rid);
    final isOpen = _asBool(r['is_open'] ?? r['is_active'] ?? 1);

    // Active campaign badge
    final campaignAsync = ref.watch(_restaurantCampaignsProvider(rid));
    final campaignList  = (campaignAsync.asData?.value is Map
        ? (campaignAsync.asData!.value['data'] as List?)
        : null);
    final activeCampaign = (campaignList != null && campaignList.isNotEmpty) ? campaignList.first : null;

    String? badgeLabel;
    String? badgeColorStr;
    if (activeCampaign != null) {
      final type  = activeCampaign['discount_type'] ?? 'percentage';
      final value = double.tryParse('${activeCampaign['discount_value'] ?? 0}') ?? 0;
      badgeLabel    = type == 'percentage' ? '-${value.toInt()}%' : '-\$${value.toStringAsFixed(0)}';
      badgeColorStr = activeCampaign['badge_color'];
    }

    // Distance string
    final distRaw = r['distance'] ?? r['distance_km'];
    final distStr = distRaw != null
        ? '${double.tryParse('$distRaw')?.toStringAsFixed(1) ?? distRaw} km'
        : null;

    // Delivery info
    final fee    = r['delivery_fee'];
    final feeNum = fee == null ? null : double.tryParse('$fee');
    final isFree = feeNum == null || feeNum == 0;

    // Logo: prefer logo, fall back to cover_image
    final logoUrl = (r['logo'] ?? r['cover_image']) as String?;

    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 10),
        decoration: BoxDecoration(
          color: context.colors.cardBg,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 10, offset: const Offset(0, 3))],
        ),
        child: Row(children: [
          // ── Left: logo ──────────────────────────────────────────────
          Stack(children: [
            ClipRRect(
              borderRadius: const BorderRadius.horizontal(left: Radius.circular(16)),
              child: _NetImg(
                url: logoUrl,
                width: 88, height: 88, radius: 0,
                fallback: Container(
                  width: 88, height: 88,
                  color: _primary.withValues(alpha: 0.12),
                  child: const Center(child: Text('🍽️', style: TextStyle(fontSize: 32))),
                ),
              ),
            ),
            // Closed overlay
            if (!isOpen)
              ClipRRect(
                borderRadius: const BorderRadius.horizontal(left: Radius.circular(16)),
                child: Container(
                  width: 88, height: 88,
                  color: Colors.black.withValues(alpha: 0.50),
                  child: Center(
                    child: Text(AppL10n.of(context).closed,
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 11)),
                  ),
                ),
              ),
          ]),

          // ── Right: info ─────────────────────────────────────────────
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 10, 10, 10),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
                // Name + badge
                Row(children: [
                  Expanded(
                    child: Text(r['name'] ?? '',
                      style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.navyText),
                      maxLines: 1, overflow: TextOverflow.ellipsis),
                  ),
                  if (badgeLabel != null) ...[
                    const SizedBox(width: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                      decoration: BoxDecoration(
                        color: _parseBadgeColor(badgeColorStr),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(mainAxisSize: MainAxisSize.min, children: [
                        const Text('🔥', style: TextStyle(fontSize: 10)),
                        const SizedBox(width: 2),
                        Text(badgeLabel, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w900)),
                      ]),
                    ),
                  ],
                ]),
                const SizedBox(height: 3),
                // Type / cuisine
                Text(r['cuisine_type'] ?? r['vendor_type'] ?? r['categories'] ?? '',
                  style: const TextStyle(fontSize: 13, color: Color(0xFF444444), fontWeight: FontWeight.w500),
                  maxLines: 1, overflow: TextOverflow.ellipsis),
                const SizedBox(height: 6),
                // Distance + delivery time
                Row(children: [
                  if (distStr != null) ...[
                    Icon(Icons.near_me_rounded, size: 13, color: Colors.grey[600]),
                    const SizedBox(width: 3),
                    Text(distStr, style: const TextStyle(fontSize: 12, color: Color(0xFF555555), fontWeight: FontWeight.w600)),
                    const SizedBox(width: 10),
                  ],
                  if (r['delivery_time'] != null) ...[
                    Icon(Icons.access_time_rounded, size: 13, color: Colors.grey[600]),
                    const SizedBox(width: 3),
                    Text('${r['delivery_time']} min', style: const TextStyle(fontSize: 12, color: Color(0xFF555555), fontWeight: FontWeight.w500)),
                  ],
                ]),
                const SizedBox(height: 4),
                // Fav button
                Align(
                  alignment: Alignment.centerRight,
                  child: GestureDetector(
                    onTap: () => ref.read(_favProvider.notifier).toggle(rid),
                    child: Icon(
                      isFav ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                      color: isFav ? Colors.red : Colors.grey[400],
                      size: 18,
                    ),
                  ),
                ),
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}

// ════════════════════════════════════════════════════════════════════
// SEARCH TAB
// ════════════════════════════════════════════════════════════════════

class _SearchTab extends ConsumerStatefulWidget {
  const _SearchTab();
  @override
  ConsumerState<_SearchTab> createState() => _SearchTabState();
}

class _SearchTabState extends ConsumerState<_SearchTab> {
  final _ctrl = TextEditingController();
  String _query = '';

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final results = ref.watch(_restaurantsProvider(_rKey(search: _query.isEmpty ? null : _query)));

    return SafeArea(child: Column(children: [
      Container(
        color: context.colors.cardBg, padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
        child: TextField(
          controller: _ctrl,
          onChanged: (v) => setState(() => _query = v),
          decoration: InputDecoration(
            hintText: l.searchRestaurants,
            hintStyle: TextStyle(color: context.colors.mutedText, fontSize: 14),
            prefixIcon: Icon(Icons.search_rounded, color: _primary),
            suffixIcon: _query.isNotEmpty ? IconButton(icon: const Icon(Icons.clear), onPressed: () { _ctrl.clear(); setState(() => _query = ''); }) : null,
            filled: true, fillColor: context.colors.inputFill,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
            contentPadding: const EdgeInsets.symmetric(vertical: 14),
          ),
        ),
      ),
      Expanded(child: results.when(
        data: (data) {
          final list = data is List ? data : (data['data'] ?? []);
          if (_query.isEmpty) return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [const Text('🔍', style: TextStyle(fontSize: 60)), const SizedBox(height: 16), Text(l.searchFavFood, style: TextStyle(color: Colors.grey[500], fontSize: 15))]));
          if (list.isEmpty) return Center(child: Text('${l.noResults} "$_query"', style: TextStyle(color: Colors.grey[500])));
          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: list.length,
            itemBuilder: (_, i) => _RestaurantListTile(restaurant: list[i], onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => _RestaurantDetailPage(restaurant: list[i])))),
          );
        },
        loading: () => ListView.builder(padding: const EdgeInsets.all(16), itemCount: 5, itemBuilder: (_, __) => Padding(padding: const EdgeInsets.only(bottom: 12), child: _shimmer(h: 70, r: 14))),
        error: (_, __) => const SizedBox.shrink(),
      )),
    ]));
  }
}

class _RestaurantListTile extends StatelessWidget {
  final dynamic restaurant;
  final VoidCallback onTap;
  const _RestaurantListTile({required this.restaurant, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final r = restaurant;
    return GestureDetector(
      onTap: onTap,
      child: Builder(builder: (context) => Container(
        margin: const EdgeInsets.only(bottom: 12),
        decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 10)]),
        child: Row(children: [
          _NetImg(
            url: r['cover_image'],
            width: 90, height: 80, radius: 16,
            fallback: Container(width: 90, height: 80, color: _primary.withValues(alpha: 0.1), child: const Center(child: Icon(Icons.restaurant_rounded, color: _primary, size: 30))),
          ),
          SizedBox(width: 12),
          Expanded(child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(r['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.navyText)),
              const SizedBox(height: 3),
              Text(r['cuisine_type'] ?? r['vendor_type'] ?? r['categories'] ?? '', style: const TextStyle(fontSize: 13, color: Color(0xFF444444), fontWeight: FontWeight.w500)),
              const SizedBox(height: 6),
              Row(children: [
                if (r['rating'] != null) ...[
                  const Icon(Icons.star_rounded, color: Colors.amber, size: 14),
                  Text(' ${r['rating']}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFF333333))),
                ],
                if (r['delivery_time'] != null)
                  Text('${r['rating'] != null ? '  •  ' : ''}${r['delivery_time']} min', style: const TextStyle(fontSize: 13, color: Color(0xFF555555), fontWeight: FontWeight.w500)),
              ]),
            ]),
          )),
          const Padding(padding: EdgeInsets.only(right: 12), child: Icon(Icons.arrow_forward_ios_rounded, size: 14, color: Colors.grey)),
        ]),
      )),
    );
  }
}

// ════════════════════════════════════════════════════════════════════
// NEAR YOU SECTION — real-time GPS + district fallback
// ════════════════════════════════════════════════════════════════════

/// Location source used by _NearYouSection.
enum _LocationSource { gps, district, none }

class _NearYouSection extends ConsumerStatefulWidget {
  const _NearYouSection();

  @override
  ConsumerState<_NearYouSection> createState() => _NearYouSectionState();
}

class _NearYouSectionState extends ConsumerState<_NearYouSection> {
  // Current GPS position (null until obtained)
  Position? _position;
  _LocationSource _source = _LocationSource.none;
  bool _locationLoading = true;
  String? _locationLabel;

  // Last API result
  List<dynamic> _restaurants = [];
  bool _loading = true;
  bool _hasError = false;

  @override
  void initState() {
    super.initState();
    _initLocation();
  }

  Future<void> _initLocation() async {
    // ── 1. Try GPS ─────────────────────────────────────────────────────────────
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (serviceEnabled) {
        LocationPermission perm = await Geolocator.checkPermission();
        if (perm == LocationPermission.denied) {
          perm = await Geolocator.requestPermission();
        }
        if (perm == LocationPermission.whileInUse || perm == LocationPermission.always) {
          // Get precise position for accurate 1km radius filtering
          final pos = await Geolocator.getCurrentPosition(
            locationSettings: const LocationSettings(
              accuracy: LocationAccuracy.high,
              timeLimit: Duration(seconds: 10),
            ),
          );
          if (mounted) {
            setState(() {
              _position = pos;
              _source = _LocationSource.gps;
              _locationLabel = null; // no label for GPS mode
              _locationLoading = false;
            });
          }
          await _fetchNearby(lat: pos.latitude, lng: pos.longitude, radius: 1.0);

          // Keep updating position (50m filter for better accuracy)
          Geolocator.getPositionStream(
            locationSettings: const LocationSettings(
              accuracy: LocationAccuracy.high,
              distanceFilter: 50, // update every 50m moved
            ),
          ).listen((pos) async {
            if (!mounted) return;
            setState(() => _position = pos);
            await _fetchNearby(lat: pos.latitude, lng: pos.longitude, radius: 1.0);
          });
          return;
        }
      }
    } catch (_) {}

    // ── 2. Fallback: user's registered district ─────────────────────────────
    try {
      final user = ref.read(authStateProvider).valueOrNull;
      if (user?.districtId != null) {
        if (mounted) {
          setState(() {
            _source = _LocationSource.district;
            _locationLabel = user!.districtName ?? 'Your District';
            _locationLoading = false;
          });
        }
        // If district has coordinates, use GPS-style distance calc
        if (user!.districtLat != null && user.districtLng != null) {
          await _fetchNearby(lat: user.districtLat!, lng: user.districtLng!);
        } else {
          await _fetchNearby(districtId: user.districtId!);
        }
        return;
      }
    } catch (_) {}

    // ── 3. No location at all ───────────────────────────────────────────────
    if (mounted) {
      setState(() {
        _source = _LocationSource.none;
        _locationLoading = false;
        _loading = false;
      });
    }
  }

  Future<void> _fetchNearby({double? lat, double? lng, int? districtId, double radius = 1.0}) async {
    if (!mounted) return;
    setState(() { _loading = true; _hasError = false; });
    try {
      final data = await _svc.getNearbyRestaurants(
        lat: lat, lng: lng,
        radius: radius,
        nearDistrictId: districtId,
      );
      final list = data is List ? data : ((data as Map?)?['data'] ?? []);
      if (mounted) setState(() { _restaurants = list is List ? list : []; _loading = false; });
    } catch (_) {
      if (mounted) setState(() { _hasError = true; _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);

    return Padding(
      padding: const EdgeInsets.fromLTRB(0, 10, 0, 0),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // ── Section header ──────────────────────────────────────────────────
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(children: [
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(l.nearYou,
                  style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: context.colors.navyText)),
                if (_source == _LocationSource.gps)
                  Row(children: [
                    Icon(Icons.my_location_rounded, size: 11, color: _primary),
                    const SizedBox(width: 3),
                    Text('Near you', style: TextStyle(fontSize: 11, color: _primary, fontWeight: FontWeight.w600)),
                  ])
                else if (_source == _LocationSource.district && _locationLabel != null)
                  Row(children: [
                    Icon(Icons.location_city_rounded, size: 11, color: Colors.grey[500]),
                    const SizedBox(width: 3),
                    Text(_locationLabel!, style: TextStyle(fontSize: 11, color: Colors.grey[500])),
                  ]),
              ]),
            ),
            TextButton(onPressed: () {}, child: Text(l.viewAll, style: const TextStyle(color: _primary, fontWeight: FontWeight.w600))),
          ]),
        ),
        const SizedBox(height: 12),

        // ── Body ───────────────────────────────────────────────────────────
        if (_locationLoading || _loading)
          Column(children: List.generate(3, (_) => Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
            child: _shimmer(w: double.infinity, h: 90, r: 16),
          )))
        else if (_source == _LocationSource.none)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            child: Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: context.colors.cardBg,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: context.colors.borderColor),
              ),
              child: Row(children: [
                Icon(Icons.location_off_rounded, color: Colors.grey[400], size: 28),
                const SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Location not available', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: context.colors.navyText)),
                  const SizedBox(height: 2),
                  Text('Enable location or register a district to see nearby restaurants.',
                    style: TextStyle(fontSize: 11, color: Colors.grey[500])),
                ])),
              ]),
            ),
          )
        else if (_hasError)
          Center(child: Padding(
            padding: const EdgeInsets.all(16),
            child: Text('⚠️ Could not load nearby restaurants', style: TextStyle(fontSize: 12, color: Colors.grey[500])),
          ))
        else if (_restaurants.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Text('No restaurants found near your location.',
              style: TextStyle(fontSize: 12, color: Colors.grey[500])),
          )
        else
          Column(children: [
            for (final r in (_restaurants.length > 5 ? _restaurants.sublist(0, 5) : _restaurants))
              _RestaurantCard(
                restaurant: r,
                onTap: () => Navigator.push(context, MaterialPageRoute(
                  builder: (_) => _RestaurantDetailPage(restaurant: r),
                )),
              ),
          ]),
      ]),
    );
  }
}

// ════════════════════════════════════════════════════════════════════
// PUBLIC ROUTE ENTRY — used by GoRouter /efood/restaurant/:id
// ════════════════════════════════════════════════════════════════════

/// Public screen that loads restaurant by ID and shows the detail page.
/// Opened from deep links, home-screen cards, and notifications.
class EFoodRestaurantDetailScreen extends ConsumerWidget {
  final int vendorId;
  const EFoodRestaurantDetailScreen({super.key, required this.vendorId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(_restaurantProvider(vendorId));
    return async.when(
      loading: () => const Scaffold(
        body: Center(child: CircularProgressIndicator(color: _primary)),
      ),
      error: (e, _) => Scaffold(
        appBar: AppBar(backgroundColor: const Color(0xFF07003B), foregroundColor: Colors.white),
        body: Center(child: Text('Failed to load restaurant', style: TextStyle(color: Colors.grey[600]))),
      ),
      data: (response) {
        // API returns {'success': true, 'data': {...vendor...}}
        // Extract the inner vendor object before passing to detail page
        final restaurant = (response is Map && response['data'] != null)
            ? response['data']
            : response;
        return _RestaurantDetailPage(restaurant: restaurant);
      },
    );
  }
}

// ════════════════════════════════════════════════════════════════════
// RESTAURANT DETAIL PAGE
// ════════════════════════════════════════════════════════════════════

class _RestaurantDetailPage extends ConsumerStatefulWidget {
  final dynamic restaurant;
  const _RestaurantDetailPage({required this.restaurant});

  @override
  ConsumerState<_RestaurantDetailPage> createState() => _RestaurantDetailPageState();
}

class _RestaurantDetailPageState extends ConsumerState<_RestaurantDetailPage> with SingleTickerProviderStateMixin {
  late TabController _tabs;
  final _searchCtrl = TextEditingController();
  int? _selectedCatId;
  String _search = '';

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tabs.dispose();
    _searchCtrl.dispose();
    super.dispose();
  }

  /// Fills entire parent space — for use inside StackFit.expand
  Widget _buildCoverImage(dynamic url) {
    final u = url?.toString().trim() ?? '';
    final valid = u.isNotEmpty && (u.startsWith('http://') || u.startsWith('https://'));
    if (!valid) {
      return Container(
        color: _primary.withValues(alpha: 0.2),
        child: const Center(child: Text('🍽️', style: TextStyle(fontSize: 80))),
      );
    }
    return NetImage(url: u, fit: BoxFit.cover);
  }

  @override
  Widget build(BuildContext context) {
    final r = widget.restaurant;
    final id = r['id'] as int;
    final cart = ref.watch(_cartProvider);

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: NestedScrollView(
        headerSliverBuilder: (_, __) => [
          SliverAppBar(
            expandedHeight: 240,
            pinned: true,
            backgroundColor: const Color(0xFF07003B),
            leading: GestureDetector(
              onTap: () => Navigator.pop(context),
              child: Container(margin: const EdgeInsets.all(8), decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.9), shape: BoxShape.circle), child: const Icon(Icons.arrow_back_rounded, color: Color(0xFF07003B))),
            ),
            actions: [
              Container(margin: const EdgeInsets.all(8), decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.9), shape: BoxShape.circle),
                child: IconButton(icon: const Icon(Icons.share_rounded, color: Color(0xFF07003B), size: 20), onPressed: () {})),
            ],
            flexibleSpace: FlexibleSpaceBar(
              background: Stack(fit: StackFit.expand, children: [
                _buildCoverImage(r['cover_image']),
                Container(decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Colors.transparent, Colors.black.withValues(alpha: 0.6)]))),
              ]),
            ),
          ),
          SliverToBoxAdapter(child: Container(
            color: context.colors.cardBg,
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              // ── Restaurant name, logo, rating ─────────────────────
              Row(children: [
                _NetImg(url: r['logo'], width: 56, height: 56, radius: 12, fallback: const Icon(Icons.restaurant_rounded, color: _primary, size: 28)),
                SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(r['name'] ?? '', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: context.colors.navyText)),
                  Text(r['cuisine_type'] ?? r['vendor_type'] ?? r['description'] ?? '', style: const TextStyle(fontSize: 14, color: Color(0xFF555555), fontWeight: FontWeight.w500)),
                ])),
                if (r['rating'] != null)
                  Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                    Row(children: [const Icon(Icons.star_rounded, color: Colors.amber, size: 18), Text(' ${r['rating']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14))]),
                    if (r['reviews_count'] != null)
                      Text('(${r['reviews_count']}+)', style: TextStyle(fontSize: 11, color: Colors.grey[500])),
                  ]),
              ]),
              const SizedBox(height: 14),
              // ── Open/Closed status chip ───────────────────────────
              Builder(builder: (_) {
                final isOpen = _asBool(r['is_open'] ?? r['is_active'] ?? 1);
                return Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: isOpen
                      ? Row(children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                            decoration: BoxDecoration(color: Colors.green.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8), border: Border.all(color: Colors.green.shade300)),
                            child: Row(mainAxisSize: MainAxisSize.min, children: [
                              Container(width: 8, height: 8, decoration: const BoxDecoration(color: Colors.green, shape: BoxShape.circle)),
                              const SizedBox(width: 6),
                              Text(AppL10n.of(context).openNow, style: const TextStyle(color: Colors.green, fontWeight: FontWeight.w700, fontSize: 12)),
                            ]),
                          ),
                        ])
                      : Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(color: Colors.red.withValues(alpha: 0.07), borderRadius: BorderRadius.circular(10), border: Border.all(color: Colors.red.shade200)),
                          child: Row(children: [
                            const Icon(Icons.store_outlined, color: Colors.red, size: 18),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(AppL10n.of(context).restaurantClosed,
                                style: const TextStyle(color: Colors.red, fontSize: 12, fontWeight: FontWeight.w600)),
                            ),
                          ]),
                        ),
                );
              }),
              // ── Delivery info cards ────────────────────────────────
              _DeliveryInfoRow(restaurant: r),

              // ── Active Discount Campaigns ──────────────────────────
              _CampaignsBanner(restaurantId: id),

              // ── Offers & Coupons strip ─────────────────────────────
              _CouponsStrip(restaurantId: id),

              // ── Categories filter chips ────────────────────────────
              _CategoriesFilter(
                restaurantId: id,
                selectedCatId: _selectedCatId,
                onSelect: (catId) => setState(() => _selectedCatId = catId),
              ),

              SizedBox(height: 14),
              // ── Search inside restaurant ───────────────────────────
              Container(
                height: 42,
                decoration: BoxDecoration(color: context.colors.inputFill, borderRadius: BorderRadius.circular(12)),
                child: TextField(
                  controller: _searchCtrl,
                  onChanged: (v) => setState(() => _search = v),
                  decoration: InputDecoration(
                    hintText: AppL10n.of(context).searchMenuHint,
                    hintStyle: TextStyle(color: context.colors.mutedText, fontSize: 13),
                    prefixIcon: Icon(Icons.search_rounded, color: context.colors.mutedText, size: 20),
                    suffixIcon: const Icon(Icons.tune_rounded, color: _primary, size: 20),
                    border: InputBorder.none, contentPadding: const EdgeInsets.symmetric(vertical: 11),
                  ),
                ),
              ),
              const SizedBox(height: 4),
              TabBar(
                controller: _tabs,
                labelColor: _primary,
                unselectedLabelColor: Colors.grey[500],
                labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
                indicatorColor: _primary,
                indicatorWeight: 3,
                tabs: [Tab(text: AppL10n.of(context).menuTab), Tab(text: AppL10n.of(context).reviews), Tab(text: AppL10n.of(context).infoTab)],
              ),
            ]),
          )),
        ],
        body: TabBarView(
          controller: _tabs,
          children: [
            _MenuTab(restaurantId: id, search: _search, selectedCatId: _selectedCatId, restaurantIsOpen: _asBool(r['is_open'] ?? r['is_active'] ?? 1)),
            _ReviewsTab(restaurant: r),
            _InfoTab(restaurant: r),
          ],
        ),
      ),
      bottomNavigationBar: cart.isEmpty ? null : _CartBar(
        onTap: () {
          final isOpen = _asBool(r['is_open'] ?? r['is_active'] ?? 1);
          if (!isOpen) {
            ScaffoldMessenger.of(context).showSnackBar(SnackBar(
              content: Text(AppL10n.of(context).cannotOrder),
              backgroundColor: Colors.red,
              behavior: SnackBarBehavior.floating,
            ));
            return;
          }
          Navigator.push(context, MaterialPageRoute(builder: (_) => const _CartPage()));
        },
      ),
    );
  }
}

// ════════════════════════════════════════════════════════════════════
// CAMPAIGNS BANNER (restaurant detail page)
// ════════════════════════════════════════════════════════════════════

class _CampaignsBanner extends ConsumerStatefulWidget {
  final int restaurantId;
  const _CampaignsBanner({required this.restaurantId});
  @override
  ConsumerState<_CampaignsBanner> createState() => _CampaignsBannerState();
}

class _CampaignsBannerState extends ConsumerState<_CampaignsBanner> {
  int _page = 0;

  // Badge colour map (matches admin badge_color values)
  static const _colours = {
    'red':    Color(0xFFEF4444),
    'orange': Color(0xFFFF8A00),
    'green':  Color(0xFF22C55E),
    'blue':   Color(0xFF3B82F6),
    'purple': Color(0xFF8B5CF6),
    'yellow': Color(0xFFF59E0B),
  };

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(_restaurantCampaignsProvider(widget.restaurantId));

    return async.when(
      data: (data) {
        final list = data is Map ? (data['data'] ?? []) : (data is List ? data : []);
        if (list is! List || list.isEmpty) return const SizedBox.shrink();

        return Column(children: [
          const SizedBox(height: 14),
          // Header row
          Row(children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(color: _primary, borderRadius: BorderRadius.circular(6)),
              child: const Text('🔥 LIVE DEALS', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800, letterSpacing: 0.5)),
            ),
          ]),
          const SizedBox(height: 8),
          // Swipeable campaign cards
          SizedBox(
            height: 100,
            child: PageView.builder(
              onPageChanged: (p) => setState(() => _page = p),
              itemCount: list.length,
              itemBuilder: (_, i) {
                final c = list[i];
                final type    = c['discount_type'] ?? 'percentage';
                final value   = double.tryParse('${c['discount_value'] ?? 0}') ?? 0;
                final discount = type == 'percentage'
                    ? '${value.toStringAsFixed(value % 1 == 0 ? 0 : 1)}% OFF'
                    : '\$${value.toStringAsFixed(2)} OFF';
                final colour = _colours[c['badge_color'] ?? 'orange'] ?? _primary;

                return Container(
                  margin: const EdgeInsets.only(right: 4),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [colour, Color.lerp(colour, Colors.black, 0.25)!],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: [BoxShadow(color: colour.withValues(alpha: 0.35), blurRadius: 12, offset: const Offset(0, 4))],
                  ),
                  padding: const EdgeInsets.all(14),
                  child: Row(children: [
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.25), borderRadius: BorderRadius.circular(6)),
                        child: Text(c['badge_text'] ?? 'Special Offer', style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700)),
                      ),
                      const SizedBox(height: 6),
                      Text(discount, style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w900, height: 1)),
                      Text(c['name'] ?? '', style: const TextStyle(color: Colors.white70, fontSize: 11), maxLines: 1, overflow: TextOverflow.ellipsis),
                    ])),
                    const SizedBox(width: 10),
                    Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Container(
                        width: 52, height: 52,
                        decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.2), shape: BoxShape.circle),
                        child: const Icon(Icons.local_fire_department_rounded, color: Colors.white, size: 28),
                      ),
                    ]),
                  ]),
                );
              },
            ),
          ),
          if (list.length > 1) ...[
            const SizedBox(height: 6),
            Row(mainAxisAlignment: MainAxisAlignment.center, children: List.generate(list.length, (i) =>
              AnimatedContainer(
                duration: const Duration(milliseconds: 250),
                margin: const EdgeInsets.symmetric(horizontal: 3),
                width: _page == i ? 16 : 5, height: 5,
                decoration: BoxDecoration(
                  color: _page == i ? _primary : Colors.grey[300],
                  borderRadius: BorderRadius.circular(3),
                ),
              ),
            )),
          ],
        ]);
      },
      loading: () => const SizedBox.shrink(),
      error: (_, __) => const SizedBox.shrink(),
    );
  }
}

// ════════════════════════════════════════════════════════════════════
// CATEGORIES FILTER (restaurant detail page)
// ════════════════════════════════════════════════════════════════════

class _CategoriesFilter extends ConsumerWidget {
  final int restaurantId;
  final int? selectedCatId;
  final ValueChanged<int?> onSelect;

  const _CategoriesFilter({
    required this.restaurantId,
    required this.selectedCatId,
    required this.onSelect,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(_restaurantProvider(restaurantId));

    return detail.when(
      data: (data) {
        final raw = data is Map ? data['data'] ?? data : data;
        final cats = raw is Map ? (raw['categories'] ?? []) : [];
        final list = cats is List ? cats : [];
        if (list.isEmpty) return const SizedBox.shrink();

        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          SizedBox(height: 14),
          Text(AppL10n.of(context).menuCategories, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: context.colors.navyText)),
          const SizedBox(height: 8),
          SizedBox(
            height: 36,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              itemCount: list.length + 1, // +1 for "All" chip
              itemBuilder: (_, i) {
                if (i == 0) {
                  // "All" chip
                  final sel = selectedCatId == null;
                  return GestureDetector(
                    onTap: () => onSelect(null),
                    child: Container(
                      margin: const EdgeInsets.only(right: 8),
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      decoration: BoxDecoration(
                        color: sel ? _primary : context.colors.inputFill,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: sel ? _primary : context.colors.borderColor),
                      ),
                      alignment: Alignment.center,
                      child: Text('All', style: TextStyle(
                        fontSize: 12, fontWeight: FontWeight.w600,
                        color: sel ? Colors.white : context.colors.mutedText,
                      )),
                    ),
                  );
                }
                final cat = list[i - 1];
                final catId = cat['id'] as int?;
                final sel = selectedCatId == catId;
                return GestureDetector(
                  onTap: () => onSelect(sel ? null : catId),
                  child: Container(
                    margin: const EdgeInsets.only(right: 8),
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    decoration: BoxDecoration(
                      color: sel ? _primary : context.colors.inputFill,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: sel ? _primary : context.colors.borderColor),
                    ),
                    alignment: Alignment.center,
                    child: Text(cat['name'] ?? '', style: TextStyle(
                      fontSize: 12, fontWeight: FontWeight.w600,
                      color: sel ? Colors.white : context.colors.mutedText,
                    )),
                  ),
                );
              },
            ),
          ),
        ]);
      },
      loading: () => const SizedBox.shrink(),
      error: (_, __) => const SizedBox.shrink(),
    );
  }
}

// ════════════════════════════════════════════════════════════════════
// COUPONS STRIP (restaurant detail page)
// ════════════════════════════════════════════════════════════════════

class _CouponsStrip extends ConsumerWidget {
  final int restaurantId;
  const _CouponsStrip({required this.restaurantId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final coupons = ref.watch(_restaurantCouponsProvider(restaurantId));

    return coupons.when(
      data: (data) {
        final list = data is Map ? (data['data'] ?? []) : (data is List ? data : []);
        if (list is! List || list.isEmpty) return const SizedBox.shrink();

        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const SizedBox(height: 14),
          Row(children: [
            const Icon(Icons.local_offer_rounded, color: _primary, size: 16),
            SizedBox(width: 6),
            Text(AppL10n.of(context).offersAndCoupons, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: context.colors.navyText)),
          ]),
          const SizedBox(height: 8),
          SizedBox(
            height: 78,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              itemCount: list.length,
              itemBuilder: (_, i) {
                final c = list[i];
                final type  = c['type'] ?? 'percentage';
                final value = double.tryParse('${c['value'] ?? 0}') ?? 0;
                final discount = type == 'percentage'
                    ? '${value.toStringAsFixed(0)}% OFF'
                    : '\$${value.toStringAsFixed(2)} OFF';
                final minOrder = double.tryParse('${c['min_order_amount'] ?? 0}') ?? 0;

                return GestureDetector(
                  onTap: () => _showCouponDialog(context, c),
                  child: Container(
                    width: 180,
                    margin: const EdgeInsets.only(right: 10),
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFFFF8A00), Color(0xFFFF6B00)],
                      ),
                      borderRadius: BorderRadius.circular(12),
                      boxShadow: [BoxShadow(color: _primary.withValues(alpha: 0.25), blurRadius: 8, offset: const Offset(0, 3))],
                    ),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.25), borderRadius: BorderRadius.circular(6)),
                          child: Text(c['code'] ?? '', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800, letterSpacing: 0.5)),
                        ),
                        const Spacer(),
                        const Icon(Icons.copy_rounded, color: Colors.white70, size: 14),
                      ]),
                      const SizedBox(height: 4),
                      Text(discount, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900, height: 1)),
                      Text(
                        minOrder > 0 ? '${AppL10n.of(context).minOrderLabel} \$${minOrder.toStringAsFixed(2)}' : AppL10n.of(context).noMinimum,
                        style: const TextStyle(color: Colors.white70, fontSize: 10),
                      ),
                    ]),
                  ),
                );
              },
            ),
          ),
        ]);
      },
      loading: () => const SizedBox.shrink(),
      error: (_, __) => const SizedBox.shrink(),
    );
  }

  void _showCouponDialog(BuildContext context, dynamic c) {
    final code     = c['code'] ?? '';
    final title    = c['title'] ?? '';
    final desc     = c['description'] ?? '';
    final type     = c['type'] ?? 'percentage';
    final value    = double.tryParse('${c['value'] ?? 0}') ?? 0;
    final discount = type == 'percentage' ? '${value.toStringAsFixed(0)}% OFF' : '\$${value.toStringAsFixed(2)} OFF';
    final minOrder = double.tryParse('${c['min_order_amount'] ?? 0}') ?? 0;
    final maxDisc  = double.tryParse('${c['max_discount'] ?? 0}') ?? 0;
    final endsAt   = c['ends_at'];

    showDialog(
      context: context,
      builder: (ctx2) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(
              width: 56, height: 56,
              decoration: BoxDecoration(color: _primary.withValues(alpha: 0.1), shape: BoxShape.circle),
              child: const Icon(Icons.local_offer_rounded, color: _primary, size: 28),
            ),
            const SizedBox(height: 12),
            Text(discount, style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w900, color: _primary)),
            Text(title, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: ctx2.colors.navyText)),
            if (desc.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(desc, style: TextStyle(fontSize: 13, color: ctx2.colors.mutedText), textAlign: TextAlign.center),
            ],
            const SizedBox(height: 14),
            // Coupon code box
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              decoration: BoxDecoration(
                color: ctx2.colors.inputFill,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: _primary.withValues(alpha: 0.3), style: BorderStyle.solid),
              ),
              child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Text(code, style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: ctx2.colors.navyText, letterSpacing: 2)),
                GestureDetector(
                  onTap: () {
                    Navigator.pop(context);
                    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                      content: Text('Code "$code" copied!'),
                      duration: const Duration(seconds: 2),
                      backgroundColor: _secondary,
                      behavior: SnackBarBehavior.floating,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ));
                  },
                  child: const Icon(Icons.copy_rounded, color: _primary, size: 20),
                ),
              ]),
            ),
            const SizedBox(height: 10),
            if (minOrder > 0)
              Text('${AppL10n.of(context).minOrderLabel}: \$${minOrder.toStringAsFixed(2)}', style: TextStyle(fontSize: 12, color: Colors.grey[500])),
            if (maxDisc > 0)
              Text('Max discount: \$${maxDisc.toStringAsFixed(2)}', style: TextStyle(fontSize: 12, color: Colors.grey[500])),
            if (endsAt != null)
              Text('Valid until: $endsAt', style: TextStyle(fontSize: 12, color: Colors.grey[500])),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () => Navigator.pop(context),
                style: ElevatedButton.styleFrom(backgroundColor: _secondary, foregroundColor: Colors.white, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                child: Text(AppL10n.of(context).gotIt, style: const TextStyle(fontWeight: FontWeight.w700)),
              ),
            ),
          ]),
        ),
      ),
    );
  }
}

// ─── Safe network image — never throws, shows fallback on error/null ─
class _NetImg extends StatelessWidget {
  final dynamic url;
  final double width;
  final double height;
  final double radius;
  final Widget fallback;

  const _NetImg({required this.url, required this.width, required this.height, required this.radius, required this.fallback});

  @override
  Widget build(BuildContext context) {
    final u = url?.toString().trim() ?? '';
    if (u.isEmpty || (!u.startsWith('http://') && !u.startsWith('https://'))) {
      return ClipRRect(borderRadius: BorderRadius.circular(radius), child: SizedBox(width: width == double.infinity ? null : width, height: height, child: fallback));
    }
    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: NetImage(url: u,
        width: width == double.infinity ? null : width,
        height: height,
        fit: BoxFit.cover,
      ),
    );
  }
}

class _DeliveryInfoRow extends ConsumerWidget {
  final dynamic restaurant;
  const _DeliveryInfoRow({required this.restaurant});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final r = restaurant;
    final id = (r['id'] as num).toInt();
    final detail = ref.watch(_restaurantProvider(id));
    final full = detail.asData?.value;
    final data = (full is Map && full['data'] is Map) ? full['data'] : r;

    final vendorLat = double.tryParse('${data['latitude'] ?? ''}');
    final vendorLng = double.tryParse('${data['longitude'] ?? ''}');
    final deliveryTime = data['delivery_time'] ?? data['estimated_delivery_time'] ?? r['delivery_time'];

    return FutureBuilder<double?>(
      future: _calcDistance(vendorLat, vendorLng),
      builder: (context, snap) {
        final distKm = snap.data;
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Row(children: [
            Expanded(child: _DeliveryInfoCard(
              icon: Icons.near_me_rounded,
              label: distKm != null ? '${distKm.toStringAsFixed(1)} km' : '...',
              subtitle: AppL10n.of(context).distance,
              color: const Color(0xFF3B82F6),
            )),
            const SizedBox(width: 10),
            Expanded(child: _DeliveryInfoCard(
              icon: Icons.access_time_rounded,
              label: deliveryTime != null ? '$deliveryTime min' : '30-45 min',
              subtitle: AppL10n.of(context).deliveryTime,
              color: const Color(0xFFFF8A00),
            )),
            const SizedBox(width: 10),
            Expanded(child: _DeliveryInfoCard(
              icon: Icons.delivery_dining_rounded,
              label: 'eSahlan',
              subtitle: AppL10n.of(context).deliveryBy,
              color: const Color(0xFF10B981),
            )),
          ]),
        );
      },
    );
  }

  static Future<double?> _calcDistance(double? vendorLat, double? vendorLng) async {
    if (vendorLat == null || vendorLng == null) return null;
    try {
      double? userLat, userLng;

      final perm = await Geolocator.checkPermission();
      if (perm != LocationPermission.denied && perm != LocationPermission.deniedForever) {
        final last = await Geolocator.getLastKnownPosition();
        if (last != null) {
          userLat = last.latitude;
          userLng = last.longitude;
        }
      }

      userLat ??= await LocalStorage.getDouble('saved_lat');
      userLng ??= await LocalStorage.getDouble('saved_lng');
      if (userLat == null || userLng == null) return null;

      final meters = Geolocator.distanceBetween(userLat, userLng, vendorLat, vendorLng);
      return meters / 1000;
    } catch (_) {
      return null;
    }
  }
}

class _DeliveryInfoCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final String subtitle;
  final Color color;
  const _DeliveryInfoCard({required this.icon, required this.label, required this.subtitle, required this.color});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: color.withValues(alpha: 0.15)),
    ),
    child: Column(children: [
      Icon(icon, color: color, size: 20),
      const SizedBox(height: 6),
      Text(label, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: context.colors.navyText), textAlign: TextAlign.center),
      const SizedBox(height: 2),
      Text(subtitle, style: TextStyle(fontSize: 10, color: Colors.grey[500]), textAlign: TextAlign.center),
    ]),
  );
}

class _InfoChip extends StatelessWidget {
  final IconData icon;
  final String text;
  const _InfoChip({required this.icon, required this.text});
  @override
  Widget build(BuildContext context) => Row(children: [
    Icon(icon, size: 14, color: Colors.grey[500]),
    const SizedBox(width: 4),
    Text(text, style: TextStyle(fontSize: 12, color: Colors.grey[600])),
  ]);
}

// ════════════════════════════════════════════════════════════════════
// MENU TAB
// ════════════════════════════════════════════════════════════════════

class _MenuTab extends ConsumerWidget {
  final int restaurantId;
  final String? search;
  final int? selectedCatId;
  final bool restaurantIsOpen;
  const _MenuTab({required this.restaurantId, this.search, this.selectedCatId, this.restaurantIsOpen = true});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final menu      = ref.watch(_menuProvider(_mKey(restaurantId, categoryId: selectedCatId, search: search?.isEmpty ?? true ? null : search)));
    final campaigns = ref.watch(_restaurantCampaignsProvider(restaurantId));

    // Extract first active campaign that applies to all items
    dynamic activeCampaign;
    campaigns.whenData((data) {
      final list = data is Map ? (data['data'] ?? []) : (data is List ? data : []);
      if (list is List && list.isNotEmpty) {
        activeCampaign = list.firstWhere(
          (c) => c['apply_to_all'] == true || c['apply_to_all'] == 1,
          orElse: () => null,
        );
      }
    });

    return menu.when(
      data: (data) {
        List items = [];
        if (data is Map) {
          final raw = data['data'] ?? data['items'] ?? data['products'] ?? [];
          items = raw is List ? raw : [];
        } else if (data is List) {
          items = data;
        }

        if (items.isEmpty) {
          return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            const Text('🍽️', style: TextStyle(fontSize: 60)),
            const SizedBox(height: 16),
            Text('No items yet', style: TextStyle(color: Colors.grey[500], fontSize: 15)),
          ]));
        }

        return ListView(
          padding: const EdgeInsets.only(bottom: 100),
          children: [
            // Restaurant closed warning strip
            if (!restaurantIsOpen)
              Container(
                margin: const EdgeInsets.fromLTRB(16, 12, 16, 4),
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: Colors.red.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.red.shade200)),
                child: const Row(children: [
                  Icon(Icons.store_outlined, color: Colors.red, size: 18),
                  SizedBox(width: 8),
                  Expanded(child: Text('Restaurant is closed — ordering is disabled', style: TextStyle(color: Colors.red, fontSize: 12, fontWeight: FontWeight.w600))),
                ]),
              ),
            const SizedBox(height: 8),
            ...items.map((item) => _MenuItemCard(
              item: item,
              campaign: activeCampaign,
              restaurantIsOpen: restaurantIsOpen,
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => _FoodItemDetailPage(item: item, campaign: activeCampaign))),
            )),
          ],
        );
      },
      loading: () => ListView.builder(padding: const EdgeInsets.all(16), itemCount: 6, itemBuilder: (_, __) => Padding(padding: const EdgeInsets.only(bottom: 12), child: _shimmer(h: 80, r: 14))),
      error: (e, _) => Center(child: Text('Error loading menu', style: TextStyle(color: Colors.grey[500]))),
    );
  }
}

class _MenuItemCard extends ConsumerWidget {
  final dynamic item;
  final dynamic campaign;
  final VoidCallback onTap;
  final bool restaurantIsOpen;
  const _MenuItemCard({required this.item, this.campaign, required this.onTap, this.restaurantIsOpen = true});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = item;

    // Time-based availability — API returns is_time_available flag
    final isTimeAvailable = _asBool(p['is_time_available'] ?? true);
    // Item can only be ordered if restaurant is open AND item time window is active
    final canOrder = restaurantIsOpen && isTimeAvailable;
    final availFrom  = p['available_from']  as String?;
    final availUntil = p['available_until'] as String?;
    final hasTimeWindow = availFrom != null || availUntil != null;

    return Opacity(
      opacity: canOrder ? 1.0 : 0.55,
      child: GestureDetector(
        onTap: canOrder ? onTap : null,
        child: Container(
          margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
          decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 10)]),
          child: Row(children: [
            Stack(
              children: [
                ClipRRect(
                  borderRadius: const BorderRadius.horizontal(left: Radius.circular(16)),
                  child: _NetImg(
                    url: p['thumbnail'] ?? p['image'],
                    width: 90, height: 90, radius: 0,
                    fallback: Container(width: 90, height: 90, color: _primary.withValues(alpha: 0.1), child: const Center(child: Text('🍕', style: TextStyle(fontSize: 36)))),
                  ),
                ),
                if (!isTimeAvailable)
                  ClipRRect(
                    borderRadius: const BorderRadius.horizontal(left: Radius.circular(16)),
                    child: Container(
                      width: 90, height: 90,
                      color: Colors.black.withValues(alpha: 0.45),
                      child: const Center(
                        child: Text('⏰', style: TextStyle(fontSize: 22)),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(width: 12),
            Expanded(child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Expanded(child: Text(p['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: context.colors.navyText), maxLines: 1, overflow: TextOverflow.ellipsis)),
                  if (campaign != null && canOrder) _CampaignBadge(campaign: campaign),
                ]),
                const SizedBox(height: 4),
                // Time window badge
                if (hasTimeWindow && !isTimeAvailable) ...[
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                    decoration: BoxDecoration(color: Colors.grey[200], borderRadius: BorderRadius.circular(6)),
                    child: Text(
                      'Not available now${availFrom != null && availUntil != null ? ' (${availFrom.substring(0, 5)} – ${availUntil.substring(0, 5)})' : ''}',
                      style: TextStyle(fontSize: 10, color: Colors.grey[600], fontWeight: FontWeight.w600),
                    ),
                  ),
                  const SizedBox(height: 4),
                ],
                if (hasTimeWindow && isTimeAvailable) ...[
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                    decoration: BoxDecoration(color: Colors.green.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
                    child: Text(
                      '⏰ Available ${availFrom != null ? availFrom.substring(0, 5) : ''} – ${availUntil != null ? availUntil.substring(0, 5) : ''}',
                      style: const TextStyle(fontSize: 10, color: Colors.green, fontWeight: FontWeight.w600),
                    ),
                  ),
                  const SizedBox(height: 4),
                ],
                Text(p['description'] ?? '', style: TextStyle(fontSize: 12, color: Colors.grey[500]), maxLines: 2, overflow: TextOverflow.ellipsis),
                const SizedBox(height: 8),
                Builder(builder: (_) {
                  final origPrice = double.tryParse('${p['price'] ?? 0}') ?? 0;
                  if (campaign != null && canOrder) {
                    final type  = campaign['discount_type'] ?? 'percentage';
                    final value = double.tryParse('${campaign['discount_value'] ?? 0}') ?? 0;
                    final discounted = type == 'percentage'
                        ? origPrice * (1 - value / 100)
                        : (origPrice - value).clamp(0, double.infinity);
                    return Row(children: [
                      Text('\$${discounted.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: _primary)),
                      const SizedBox(width: 6),
                      Text('\$${origPrice.toStringAsFixed(2)}', style: TextStyle(fontSize: 12, color: Colors.grey[400], decoration: TextDecoration.lineThrough)),
                    ]);
                  }
                  return Text('\$${origPrice.toStringAsFixed(2)}', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText));
                }),
              ]),
            )),
            Padding(
              padding: const EdgeInsets.only(right: 12),
              child: canOrder
                  ? GestureDetector(
                      onTap: () {
                        final origPrice = double.tryParse('${p['price'] ?? 0}') ?? 0;
                        double? discounted;
                        if (campaign != null) {
                          final type  = campaign['discount_type'] ?? 'percentage';
                          final value = double.tryParse('${campaign['discount_value'] ?? 0}') ?? 0;
                          discounted = type == 'percentage'
                              ? origPrice * (1 - value / 100)
                              : (origPrice - value).clamp(0, double.infinity).toDouble();
                        }
                        ref.read(_cartProvider.notifier).add(_CartItem(
                          product: p,
                          overridePrice: discounted,
                        ));
                        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                          content: Text('${p['name']} added to cart'),
                          duration: const Duration(seconds: 1),
                          backgroundColor: _secondary,
                          behavior: SnackBarBehavior.floating,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ));
                      },
                      child: Container(
                        width: 36, height: 36,
                        decoration: BoxDecoration(color: _primary, borderRadius: BorderRadius.circular(10)),
                        child: const Icon(Icons.add_rounded, color: Colors.white, size: 22),
                      ),
                    )
                  : Container(
                      width: 36, height: 36,
                      decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(10)),
                      child: const Icon(Icons.block_rounded, color: Colors.grey, size: 20),
                    ),
            ),
          ]),
        ),
      ),
    );
  }
}

// Small badge shown on menu item cards when a campaign is active
class _CampaignBadge extends StatelessWidget {
  final dynamic campaign;
  const _CampaignBadge({required this.campaign});

  static const _colours = {
    'red':    Color(0xFFEF4444), 'orange': Color(0xFFFF8A00),
    'green':  Color(0xFF22C55E), 'blue':   Color(0xFF3B82F6),
    'purple': Color(0xFF8B5CF6), 'yellow': Color(0xFFF59E0B),
  };

  @override
  Widget build(BuildContext context) {
    final type  = campaign['discount_type'] ?? 'percentage';
    final value = double.tryParse('${campaign['discount_value'] ?? 0}') ?? 0;
    final label = type == 'percentage'
        ? '-${value.toStringAsFixed(0)}%'
        : '-\$${value.toStringAsFixed(2)}';
    final colour = _colours[campaign['badge_color'] ?? 'orange'] ?? _primary;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(color: colour, borderRadius: BorderRadius.circular(6)),
      child: Text(label, style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800)),
    );
  }
}

// ════════════════════════════════════════════════════════════════════
// REVIEWS TAB
// ════════════════════════════════════════════════════════════════════

class _ReviewsTab extends StatelessWidget {
  final dynamic restaurant;
  const _ReviewsTab({required this.restaurant});
  @override
  Widget build(BuildContext context) {
    return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
      Icon(Icons.rate_review_outlined, size: 60, color: Colors.grey[300]),
      const SizedBox(height: 16),
      Text('No reviews yet', style: TextStyle(color: Colors.grey[500], fontSize: 15)),
    ]));
  }
}

// ════════════════════════════════════════════════════════════════════
// INFO TAB
// ════════════════════════════════════════════════════════════════════

class _InfoTab extends StatelessWidget {
  final dynamic restaurant;
  const _InfoTab({required this.restaurant});
  @override
  Widget build(BuildContext context) {
    final r = restaurant;
    return ListView(padding: const EdgeInsets.all(16), children: [
      if (r['address'] != null || r['location'] != null)
        _InfoRow(icon: Icons.location_on_rounded, label: 'Address', value: r['address'] ?? r['location']),
      if (r['open_time'] != null || r['close_time'] != null)
        _InfoRow(icon: Icons.access_time_rounded, label: 'Working Hours', value: '${r['open_time'] ?? '—'} – ${r['close_time'] ?? '—'}'),
      if (r['phone'] != null)
        _InfoRow(icon: Icons.phone_rounded, label: 'Phone', value: r['phone']),
      _InfoRow(icon: Icons.delivery_dining_rounded, label: 'Delivery Fee', value: () { final f = double.tryParse('${r['delivery_fee'] ?? 0}'); return (f == null || f == 0) ? 'Free' : '\$${f.toStringAsFixed(2)}'; }()),
      if (r['min_order'] != null)
        _InfoRow(icon: Icons.shopping_bag_outlined, label: 'Minimum Order', value: '\$${r['min_order']}'),
    ]);
  }
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String label, value;
  const _InfoRow({required this.icon, required this.label, required this.value});
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 10),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(14)),
    child: Row(children: [
      Container(width: 38, height: 38, decoration: BoxDecoration(color: _primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)), child: Icon(icon, color: _primary, size: 20)),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: TextStyle(fontSize: 11, color: Colors.grey[500])),
        Text(value, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: context.colors.navyText)),
      ])),
    ]),
  );
}

// ════════════════════════════════════════════════════════════════════
// CART BAR (floating bottom)
// ════════════════════════════════════════════════════════════════════

class _CartBar extends ConsumerWidget {
  final VoidCallback onTap;
  const _CartBar({required this.onTap});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    ref.watch(_cartProvider);
    final notifier = ref.read(_cartProvider.notifier);
    final count = notifier.totalItems;
    final total = notifier.subtotal;

    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
        decoration: BoxDecoration(color: _secondary, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: _secondary.withValues(alpha: 0.4), blurRadius: 16, offset: const Offset(0, 6))]),
        child: Row(children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(color: _primary, borderRadius: BorderRadius.circular(8)),
            child: Text('$count', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13)),
          ),
          const SizedBox(width: 12),
          const Icon(Icons.shopping_cart_rounded, color: Colors.white, size: 20),
          const SizedBox(width: 8),
          const Text('View Cart', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 15)),
          const Spacer(),
          Text('\$${total.toStringAsFixed(2)}', style: const TextStyle(color: _primary, fontWeight: FontWeight.w800, fontSize: 16)),
        ]),
      ),
    );
  }
}

// ════════════════════════════════════════════════════════════════════
// FOOD ITEM DETAIL PAGE
// ════════════════════════════════════════════════════════════════════

class _FoodItemDetailPage extends ConsumerStatefulWidget {
  final dynamic item;
  final dynamic campaign; // active campaign for the restaurant (nullable)
  const _FoodItemDetailPage({required this.item, this.campaign});

  @override
  ConsumerState<_FoodItemDetailPage> createState() => _FoodItemDetailPageState();
}

class _FoodItemDetailPageState extends ConsumerState<_FoodItemDetailPage> {
  String? _size;
  final Set<dynamic> _selectedAddons = {};
  final _instructCtrl = TextEditingController();
  int _qty = 1;

  @override
  void dispose() { _instructCtrl.dispose(); super.dispose(); }

  /// Returns discounted base price if a campaign is active, else original price.
  double get _effectiveBasePrice {
    final orig = double.tryParse('${widget.item['price'] ?? 0}') ?? 0;
    final c = widget.campaign;
    if (c == null) return orig;
    final type  = c['discount_type'] ?? 'percentage';
    final value = double.tryParse('${c['discount_value'] ?? 0}') ?? 0;
    return type == 'percentage'
        ? orig * (1 - value / 100)
        : (orig - value).clamp(0, double.infinity).toDouble();
  }

  double get _totalPrice {
    final addonsTotal = _selectedAddons.fold(0.0, (s, a) => s + (double.tryParse('${a['price'] ?? 0}') ?? 0));
    return (_effectiveBasePrice + addonsTotal) * _qty;
  }

  @override
  Widget build(BuildContext context) {
    final p      = widget.item;
    final addons   = (p['addons']   as List?)?.cast<dynamic>() ?? <dynamic>[];
    final variants = (p['variants'] as List?)?.cast<dynamic>() ?? <dynamic>[];

    return Scaffold(
      backgroundColor: context.colors.scaffoldBg,
      body: CustomScrollView(slivers: [
        SliverAppBar(
          expandedHeight: 280,
          pinned: true,
          backgroundColor: _secondary,
          leading: GestureDetector(
            onTap: () => Navigator.pop(context),
            child: Container(margin: const EdgeInsets.all(8), decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.9), shape: BoxShape.circle), child: const Icon(Icons.arrow_back_rounded, color: _secondary)),
          ),
          actions: [
            Container(margin: const EdgeInsets.all(8), decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.9), shape: BoxShape.circle),
              child: IconButton(icon: const Icon(Icons.favorite_border_rounded, color: _secondary, size: 20), onPressed: () {})),
          ],
          flexibleSpace: FlexibleSpaceBar(
            background: () {
              final url = (p['thumbnail'] ?? p['image'])?.toString().trim() ?? '';
              final valid = url.isNotEmpty && (url.startsWith('http://') || url.startsWith('https://'));
              return valid
                  ? NetImage(url: url, fit: BoxFit.cover,
                      errorWidget: Container(color: _primary.withValues(alpha: 0.2),
                          child: const Center(child: Text('🍕', style: TextStyle(fontSize: 100)))))
                  : Container(color: _primary.withValues(alpha: 0.2),
                      child: const Center(child: Text('🍕', style: TextStyle(fontSize: 100))));
            }(),
          ),
        ),
        SliverToBoxAdapter(child: Container(
          decoration: BoxDecoration(color: context.colors.surfaceBg, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
          padding: const EdgeInsets.all(20),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(p['name'] ?? '', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: context.colors.navyText)),
                const SizedBox(height: 6),
                Text(p['description'] ?? '', style: TextStyle(fontSize: 14, color: Colors.grey[500], height: 1.4)),
              ])),
              const SizedBox(width: 12),
              Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                if (p['rating'] != null)
                  Row(children: [const Icon(Icons.star_rounded, color: Colors.amber, size: 16), Text(' ${p['rating']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13))]),
                const SizedBox(height: 4),
                // Show discounted price if campaign active
                if (widget.campaign != null) ...[
                  Text('\$${_effectiveBasePrice.toStringAsFixed(2)}',
                      style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: _primary)),
                  Text('\$${double.tryParse('${p['price'] ?? 0}')?.toStringAsFixed(2) ?? '0.00'}',
                      style: const TextStyle(fontSize: 13, color: Colors.grey,
                          decoration: TextDecoration.lineThrough, decorationColor: Colors.grey)),
                ] else
                  Text('\$${double.tryParse('${p['price'] ?? 0}')?.toStringAsFixed(2) ?? '0.00'}',
                      style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: _primary)),
              ]),
            ]),

            // ── Variants / Sizes (only if product has real variants) ──────
            if (variants.isNotEmpty) ...[
              const SizedBox(height: 24),
              Text('Size', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: context.colors.navyText)),
              const SizedBox(height: 12),
              Wrap(spacing: 10, children: variants.map<Widget>((v) {
                final name = '${v['name'] ?? v['value'] ?? ''}';
                final sel  = _size == name;
                return GestureDetector(
                  onTap: () => setState(() => _size = name),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                    decoration: BoxDecoration(
                      color: sel ? _primary : context.colors.cardBg,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: sel ? _primary : Colors.grey.shade200),
                      boxShadow: sel ? [BoxShadow(color: _primary.withValues(alpha: 0.3), blurRadius: 8)] : [],
                    ),
                    child: Text(name, style: TextStyle(color: sel ? Colors.white : Colors.grey[600], fontWeight: FontWeight.w600, fontSize: 13)),
                  ),
                );
              }).toList()),
            ],

            // ── Addons (only if product has real addons) ──────────────────
            if (addons.isNotEmpty) ...[
              const SizedBox(height: 24),
              Text('Addons', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: context.colors.navyText)),
              const SizedBox(height: 8),
            ],
            ...addons.map((a) {
              final addonImg = a['image'] as String?;
              return GestureDetector(
                onTap: () => setState(() { _selectedAddons.contains(a) ? _selectedAddons.remove(a) : _selectedAddons.add(a); }),
                child: Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: BoxDecoration(
                    color: context.colors.cardBg,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: _selectedAddons.contains(a) ? _primary : Colors.transparent, width: 1.5),
                    boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 6)],
                  ),
                  child: Row(children: [
                    Container(
                      width: 22, height: 22,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(color: _selectedAddons.contains(a) ? _primary : Colors.grey.shade400, width: 2),
                        color: _selectedAddons.contains(a) ? _primary : Colors.transparent,
                      ),
                      child: _selectedAddons.contains(a) ? const Icon(Icons.check, size: 14, color: Colors.white) : null,
                    ),
                    const SizedBox(width: 10),
                    if (addonImg != null && addonImg.isNotEmpty) ...[
                      ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: _NetImg(url: addonImg, width: 40, height: 40, radius: 8,
                            fallback: const SizedBox(width: 40, height: 40)),
                      ),
                      const SizedBox(width: 10),
                    ],
                    Expanded(child: Text(a['name'] ?? '', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500))),
                    Text('+\$${a['price'] ?? '0.00'}', style: TextStyle(color: _primary, fontWeight: FontWeight.w700, fontSize: 13)),
                  ]),
                ),
              );
            }),

            const SizedBox(height: 24),
            Text('Special Instructions', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: context.colors.navyText)),
            const SizedBox(height: 10),
            Container(
              decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(14), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 6)]),
              child: TextField(
                controller: _instructCtrl,
                maxLines: 3,
                decoration: InputDecoration(
                  hintText: 'Write your instructions...',
                  hintStyle: TextStyle(color: Colors.grey[400], fontSize: 13),
                  border: InputBorder.none,
                  contentPadding: const EdgeInsets.all(14),
                ),
              ),
            ),
            const SizedBox(height: 100),
          ]),
        )),
      ]),
      bottomNavigationBar: Container(
        color: context.colors.cardBg,
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
        child: Row(children: [
          Container(
            decoration: BoxDecoration(color: context.colors.surfaceBg, borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.grey.shade200)),
            child: Row(children: [
              IconButton(icon: const Icon(Icons.remove_rounded, size: 20), onPressed: () { if (_qty > 1) setState(() => _qty--); }),
              Text('$_qty', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
              IconButton(icon: const Icon(Icons.add_rounded, size: 20, color: _primary), onPressed: () => setState(() => _qty++)),
            ]),
          ),
          const SizedBox(width: 12),
          Expanded(child: GestureDetector(
            onTap: () {
              // Use discounted price if a campaign is active
              final discounted = widget.campaign != null ? _effectiveBasePrice : null;
              ref.read(_cartProvider.notifier).add(_CartItem(
                product: widget.item,
                addons: _selectedAddons.toList(),
                size: _size,
                instructions: _instructCtrl.text,
                overridePrice: discounted,
                qty: _qty,
              ));
              Navigator.pop(context);
              ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                content: Text('${widget.item['name']} added to cart'),
                backgroundColor: _secondary,
                behavior: SnackBarBehavior.floating,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ));
            },
            child: Container(
              height: 52,
              decoration: BoxDecoration(color: _primary, borderRadius: BorderRadius.circular(14), boxShadow: [BoxShadow(color: _primary.withValues(alpha: 0.4), blurRadius: 12, offset: const Offset(0, 6))]),
              child: Center(child: Text('Add to Cart  \$${_totalPrice.toStringAsFixed(2)}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15))),
            ),
          )),
        ]),
      ),
    );
  }

}

// ════════════════════════════════════════════════════════════════════
// CART PAGE
// ════════════════════════════════════════════════════════════════════

class _CartPage extends ConsumerStatefulWidget {
  const _CartPage();

  @override
  ConsumerState<_CartPage> createState() => _CartPageState();
}

class _CartPageState extends ConsumerState<_CartPage> {
  final _couponCtrl = TextEditingController();
  bool _validatingCoupon = false;
  double _discount = 0;
  bool _couponValid = false;
  String? _couponMessage;
  double _deliveryFee = AppConstants.foodDeliveryFee;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _fetchDeliveryFee());
  }

  Future<void> _fetchDeliveryFee() async {
    final cart = ref.read(_cartProvider);
    if (cart.isEmpty) return;
    final user = ref.read(authStateProvider).valueOrNull;
    // Fallback to Hamarweyne (4) if user has no district — same as backend default
    final districtId = user?.districtId ?? 4;
    final vendorId = (cart.first.product['vendor_id'] as num?)?.toInt();
    if (vendorId == null) return;
    try {
      final r = await _svc.getEFoodDeliveryFee(vendorId: vendorId, districtId: districtId);
      final fee = (r['data']?['delivery_fee'] as num?)?.toDouble()
          ?? double.tryParse('${r?['delivery_fee'] ?? ''}');
      if (mounted && fee != null) setState(() => _deliveryFee = fee);
    } catch (e) {
    }
  }

  Future<void> _applyCoupon() async {
    final code = _couponCtrl.text.trim();
    if (code.isEmpty) return;
    setState(() => _validatingCoupon = true);
    // TODO: wire to real coupon API
    await Future.delayed(const Duration(milliseconds: 400));
    if (mounted) setState(() {
      _validatingCoupon = false;
      _couponValid = false;
      _discount = 0;
      _couponMessage = 'Invalid or expired coupon code';
    });
  }

  @override
  void dispose() { _couponCtrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final cart = ref.watch(_cartProvider);
    final notifier = ref.read(_cartProvider.notifier);
    final subtotal = notifier.subtotal;
    final total = subtotal + _deliveryFee - _discount;

    return Scaffold(
      backgroundColor: context.colors.scaffoldBg,
      appBar: AppBar(
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20),
          onPressed: () => Navigator.pop(context),
        ),
        title: Row(mainAxisSize: MainAxisSize.min, children: [
          Text(l.myCart, style: const TextStyle(fontWeight: FontWeight.w800, fontFamily: 'Cairo')),
          if (cart.isNotEmpty) ...[
            const SizedBox(width: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(color: _primary, borderRadius: BorderRadius.circular(12)),
              child: Text('${cart.length}', style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
            ),
          ],
        ]),
        actions: [
          if (cart.isNotEmpty) TextButton(
            onPressed: () => ref.read(_cartProvider.notifier).clear(),
            child: Text(l.clearAll, style: const TextStyle(color: AppColors.error, fontWeight: FontWeight.w700)),
          ),
        ],
      ),
      body: cart.isEmpty
          ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
              Container(
                width: 120, height: 120,
                decoration: BoxDecoration(color: context.colors.surfaceBg, borderRadius: BorderRadius.circular(60)),
                child: const Icon(Icons.shopping_bag_outlined, size: 60, color: AppColors.textGrey),
              ),
              const SizedBox(height: 20),
              Text(l.emptyCart, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: context.colors.navyText)),
              const SizedBox(height: 8),
              Text(l.addProductsToStart, style: const TextStyle(color: AppColors.textGrey, fontSize: 14)),
              const SizedBox(height: 24),
              AppButton(label: l.browseProducts, width: 180, onPressed: () => Navigator.pop(context)),
            ]))
          : ListView(padding: const EdgeInsets.fromLTRB(16, 16, 16, 120), children: [
              // Cart items — swipe to delete
              ...cart.map((item) => Dismissible(
                key: Key(item.key),
                direction: DismissDirection.endToStart,
                background: Container(
                  alignment: Alignment.centerRight,
                  padding: const EdgeInsets.only(right: 20),
                  margin: const EdgeInsets.only(bottom: 12),
                  decoration: BoxDecoration(color: AppColors.error, borderRadius: BorderRadius.circular(14)),
                  child: const Icon(Icons.delete_outline_rounded, color: Colors.white, size: 28),
                ),
                onDismissed: (_) => ref.read(_cartProvider.notifier).remove(item.key),
                child: _CartItemTile(item: item),
              )),

              const SizedBox(height: 8),

              // Coupon
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: context.colors.cardBg,
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)],
                ),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(l.couponCode, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: context.colors.navyText)),
                  const SizedBox(height: 12),
                  Row(children: [
                    Expanded(child: TextField(
                      controller: _couponCtrl,
                      textCapitalization: TextCapitalization.characters,
                      decoration: InputDecoration(
                        hintText: l.enterCouponCode,
                        filled: true, fillColor: context.colors.scaffoldBg,
                        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
                        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
                        suffixIcon: _couponValid ? const Icon(Icons.check_circle_rounded, color: AppColors.success) : null,
                      ),
                    )),
                    const SizedBox(width: 10),
                    AppButton(
                      label: l.apply,
                      width: 80, height: 46,
                      isLoading: _validatingCoupon,
                      onPressed: _applyCoupon,
                    ),
                  ]),
                  if (_couponMessage != null) Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(_couponMessage!, style: TextStyle(
                      fontSize: 12, fontWeight: FontWeight.w600,
                      color: _couponValid ? AppColors.success : AppColors.error,
                    )),
                  ),
                ]),
              ),

              const SizedBox(height: 12),

              // Order Summary
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: context.colors.cardBg,
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)],
                ),
                child: Column(children: [
                  Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                    Text(l.orderSummaryLabel, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText)),
                  ]),
                  const SizedBox(height: 16),
                  _summaryRow(context, l.subtotal, '\$${subtotal.toStringAsFixed(2)}'),
                  if (_discount > 0) _summaryRow(context, l.discount, '-\$${_discount.toStringAsFixed(2)}', valueColor: AppColors.success),
                  _summaryRow(context, l.deliveryFee, '\$${_deliveryFee.toStringAsFixed(2)}'),
                  const Divider(height: 20),
                  Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                    Text(l.total, style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16, color: context.colors.navyText)),
                    Text('\$${total.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 20, color: _primary)),
                  ]),
                ]),
              ),
            ]),
      bottomNavigationBar: cart.isEmpty ? null : Container(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        decoration: BoxDecoration(
          color: context.colors.cardBg,
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 12, offset: const Offset(0, -2))],
        ),
        child: AppButton(
          label: '${l.proceedCheckout}  \$${total.toStringAsFixed(2)}',
          onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => _CheckoutPage(
            subtotal: subtotal, deliveryFee: _deliveryFee, discount: _discount,
          ))),
        ),
      ),
    );
  }

  Widget _summaryRow(BuildContext context, String label, String value, {Color? valueColor}) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: const TextStyle(color: AppColors.textGrey, fontSize: 14)),
      Text(value, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: valueColor ?? context.colors.navyText)),
    ]),
  );
}

class _CartItemTile extends ConsumerWidget {
  final _CartItem item;
  const _CartItemTile({super.key, required this.item});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = item.product;
    final imageUrl = p['image'] ?? p['thumbnail'];
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)],
      ),
      child: Row(children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(10),
          child: imageUrl != null
              ? NetImage(url: imageUrl, width: 72, height: 72, fit: BoxFit.cover,
                  errorWidget: Container(width: 72, height: 72, color: context.colors.surfaceBg, child: const Icon(Icons.restaurant_menu_rounded, color: AppColors.divider)))
              : Container(width: 72, height: 72, color: context.colors.surfaceBg, child: const Icon(Icons.restaurant_menu_rounded, color: AppColors.divider)),
        ),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(p['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText), maxLines: 2, overflow: TextOverflow.ellipsis),
          if (item.size != null) Text(item.size!, style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
          const SizedBox(height: 8),
          Row(children: [
            Text('\$${item.unitPrice.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: _primary)),
            if (item.hasDiscount) ...[
              const SizedBox(width: 6),
              Text('\$${item.originalPrice.toStringAsFixed(2)}',
                  style: const TextStyle(fontSize: 11, color: AppColors.textGrey,
                      decoration: TextDecoration.lineThrough, decorationColor: AppColors.textGrey)),
            ],
            const Spacer(),
            _FoodQtyControl(
              qty: item.qty,
              onMinus: () => ref.read(_cartProvider.notifier).decrement(item.key),
              onPlus:  () => ref.read(_cartProvider.notifier).increment(item.key),
            ),
          ]),
        ])),
      ]),
    );
  }
}

class _FoodQtyControl extends StatelessWidget {
  final int qty;
  final VoidCallback onMinus;
  final VoidCallback onPlus;
  const _FoodQtyControl({required this.qty, required this.onMinus, required this.onPlus});

  @override
  Widget build(BuildContext context) => Row(children: [
    _btn(Icons.remove_rounded, onMinus, qty > 1, context),
    SizedBox(width: 32, child: Text('$qty', textAlign: TextAlign.center,
        style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.navyText))),
    _btn(Icons.add_rounded, onPlus, true, context),
  ]);

  Widget _btn(IconData icon, VoidCallback fn, bool active, BuildContext context) => GestureDetector(
    onTap: active ? fn : null,
    child: Container(
      width: 28, height: 28,
      decoration: BoxDecoration(
        color: active ? _primary : AppColors.surface,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Icon(icon, size: 14, color: active ? Colors.white : AppColors.textGrey),
    ),
  );
}

class _PriceRow extends StatelessWidget {
  final String label, value;
  final Color? color;
  const _PriceRow(this.label, this.value, {this.color});
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: TextStyle(fontSize: 14, color: Colors.grey[500])),
      Text(value, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: color ?? context.colors.navyText)),
    ]),
  );
}

// ════════════════════════════════════════════════════════════════════
// CHECKOUT PAGE
// ════════════════════════════════════════════════════════════════════

class _CheckoutPage extends ConsumerStatefulWidget {
  final double subtotal, deliveryFee, discount;
  const _CheckoutPage({required this.subtotal, required this.deliveryFee, required this.discount});

  @override
  ConsumerState<_CheckoutPage> createState() => _CheckoutPageState();
}

class _CheckoutPageState extends ConsumerState<_CheckoutPage> {
  String  _payment  = 'waafi';
  bool    _placing  = false;
  int?    _districtId;
  String? _districtName;
  bool    _districtInitialized = false;
  final _nameCtrl  = TextEditingController();
  final _phoneCtrl = TextEditingController();
  int    _pointsToRedeem = 0;
  double _pointsDiscount = 0.0;
  double _deliveryFee = 0.0;

  double get _total => widget.subtotal + _deliveryFee - widget.discount - _pointsDiscount;

  @override
  void initState() {
    super.initState();
    _deliveryFee = widget.deliveryFee; // start with cart's fetched value
    WidgetsBinding.instance.addPostFrameCallback((_) => _initDistrict());
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    super.dispose();
  }

  void _initDistrict() {
    if (_districtInitialized) return;
    final user = ref.read(authStateProvider).valueOrNull;
    if (user != null) {
      if (_nameCtrl.text.isEmpty)  _nameCtrl.text  = user.name;
      if (_phoneCtrl.text.isEmpty) _phoneCtrl.text = user.phone;
      // Use user's district or fallback to Hamarweyne (4) — same as backend default
      final districtId   = user.districtId ?? 4;
      final districtName = user.districtName ?? 'Hamarweyne';
      _districtId   = districtId;
      _districtName = districtName;
      _fetchZoneFee(districtId);
      _districtInitialized = true;
      setState(() {});
    }
  }

  Future<void> _fetchZoneFee(int districtId) async {
    final cart = ref.read(_cartProvider);
    final vendorId = cart.isNotEmpty ? (cart.first.product['vendor_id'] as num?)?.toInt() : null;
    if (vendorId == null) return;
    try {
      final r = await _svc.getEFoodDeliveryFee(vendorId: vendorId, districtId: districtId);
      final fee = (r['data']?['delivery_fee'] as num?)?.toDouble();
      if (mounted && fee != null) setState(() => _deliveryFee = fee);
    } catch (e) {
    }
  }

  Future<void> _pickDistrict() async {
    final districts = await ref.read(_efoodDistrictsProvider.future);
    if (!mounted) return;
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _EfoodDistrictSheet(
        districts: districts,
        selectedId: _districtId,
        onSelected: (d) {
          setState(() { _districtId = d.id; _districtName = d.name; });
          Navigator.pop(context);
          _fetchZoneFee(d.id);
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.colors.scaffoldBg,
      appBar: AppBar(
        backgroundColor: context.colors.cardBg,
        elevation: 0,
        leading: IconButton(icon: Icon(Icons.arrow_back_rounded, color: context.colors.navyText), onPressed: () => Navigator.pop(context)),
        title: Text(AppL10n.of(context).checkout, style: TextStyle(color: context.colors.navyText, fontWeight: FontWeight.w700, fontSize: 18)),
        centerTitle: true,
      ),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        // Delivery Address
        Text(AppL10n.of(context).deliveryAddress, style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: context.colors.navyText)),
        const SizedBox(height: 12),
        GestureDetector(
          onTap: _pickDistrict,
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: _districtId != null ? _primary.withValues(alpha: 0.05) : context.colors.cardBg,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: _districtId != null ? _primary.withValues(alpha: 0.4) : Colors.grey.withValues(alpha: 0.2), width: 1.5),
              boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8)],
            ),
            child: Row(children: [
              Container(
                width: 40, height: 40,
                decoration: BoxDecoration(color: _districtId != null ? _primary : Colors.grey.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(10)),
                child: Icon(Icons.location_on_rounded, color: _districtId != null ? Colors.white : Colors.grey, size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(
                  _districtName ?? 'Select delivery district',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: _districtId != null ? context.colors.navyText : Colors.grey),
                ),
                const SizedBox(height: 2),
                Text(_districtId != null ? 'Tap to change' : 'Choose where to deliver', style: TextStyle(fontSize: 11, color: Colors.grey[500])),
              ])),
              const Icon(Icons.chevron_right_rounded, color: Colors.grey),
            ]),
          ),
        ),
        const SizedBox(height: 12),
        _buildTextField(_nameCtrl,  'Full Name',    Icons.person_outline_rounded),
        const SizedBox(height: 10),
        _buildTextField(_phoneCtrl, 'Phone Number', Icons.phone_outlined, keyboardType: TextInputType.phone),

        const SizedBox(height: 24),
        RedeemPointsBar(
          orderTotal: widget.subtotal + _deliveryFee - widget.discount,
          onChanged: (pts, disc) => setState(() { _pointsToRedeem = pts; _pointsDiscount = disc; }),
        ),
        const SizedBox(height: 8),
        Text(AppL10n.of(context).paymentMethod, style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: context.colors.navyText)),
        const SizedBox(height: 12),
        PaymentMethodSection(
          selected: _apiPayment,
          onChanged: (m) => setState(() {
            if (m == 'mobile_pay') _payment = 'mobile';
            else if (m == 'waafi_pay') _payment = 'waafi';
            else _payment = m;
          }),
        ),

        const SizedBox(height: 24),
        Text('Order Summary', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: context.colors.navyText)),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)]),
          child: Column(children: [
            _PriceRow('${ref.read(_cartProvider.notifier).totalItems} Items', '\$${widget.subtotal.toStringAsFixed(2)}'),
            _PriceRow(AppL10n.of(context).deliveryFee, '\$${_deliveryFee.toStringAsFixed(2)}'),
            if (widget.discount > 0) _PriceRow('Discount', '-\$${widget.discount.toStringAsFixed(2)}', color: Colors.green),
            if (_pointsDiscount > 0) _PriceRow('Points ($_pointsToRedeem pts)', '-\$${_pointsDiscount.toStringAsFixed(2)}', color: const Color(0xFFF59E0B)),
            const Divider(height: 20),
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text('Total', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: context.colors.navyText)),
              Text('\$${_total.toStringAsFixed(2)}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: _primary)),
            ]),
          ]),
        ),
        const SizedBox(height: 100),
      ]),
      bottomNavigationBar: Container(
        color: context.colors.cardBg,
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        child: GestureDetector(
          onTap: _placing ? null : _placeOrder,
          child: Container(
            height: 56,
            decoration: BoxDecoration(color: _primary, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: _primary.withValues(alpha: 0.4), blurRadius: 14, offset: const Offset(0, 6))]),
            child: _placing
                ? const Center(child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Text(AppL10n.of(context).placeOrder, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
                    const SizedBox(width: 8),
                    Text('\$${_total.toStringAsFixed(2)}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 16)),
                  ]),
          ),
        ),
      ),
    );
  }

  Widget _buildTextField(TextEditingController ctrl, String hint, IconData icon, {TextInputType? keyboardType}) => TextField(
    controller: ctrl,
    keyboardType: keyboardType,
    decoration: InputDecoration(
      hintText: hint,
      prefixIcon: Icon(icon, size: 18, color: Colors.grey),
      filled: true,
      fillColor: context.colors.cardBg,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: Colors.grey.withValues(alpha: 0.2))),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: _primary, width: 2)),
    ),
  );

  String? _waafiReference;
  String? _mobileProofToken;

  String get _apiPayment => _payment == 'wallet' ? 'wallet' : _payment == 'mobile' ? 'mobile_pay' : 'waafi_pay';

  Future<void> _placeOrder() async {
    if (_districtId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Please select a delivery district'),
        backgroundColor: Colors.red,
        behavior: SnackBarBehavior.floating,
      ));
      return;
    }

    if (_payment == 'mobile') {
      final result = await showMobilePaySheet(context, amount: _total, description: 'eFood Order');
      if (result?.success != true) return;
      _waafiReference = result!.account != null ? 'mobile_pay_${result.account!.id}' : 'mobile_pay';
      _mobileProofToken = result.proofToken;
    } else if (_payment == 'waafi') {
      final result = await showWaafiPaySheet(
        context,
        amount: _total,
        type: 'order',
        description: 'eFood Order',
      );
      if (result?.success != true) return;
      _waafiReference = result!.reference;
    }
    if (_payment == 'wallet') {
      final pinOk = await showWalletPinDialog(context);
      if (!pinOk) return;
    }
    setState(() => _placing = true);
    try {
      final cart = ref.read(_cartProvider);

      // Build items — send both qty + quantity so backend accepts either
      final items = cart.map((e) => {
        'product_id': (e.product['id'] as num).toInt(),
        'quantity':   e.qty,
        'qty':        e.qty,
        'addons':     e.addons.map((a) => {'id': a['id']}).toList(),
      }).toList();

      // Infer vendor_id from first cart item
      final vendorId = cart.isNotEmpty ? cart.first.product['vendor_id'] : null;

      final result = await _svc.placeFoodOrder({
        if (vendorId != null) 'vendor_id': (vendorId as num).toInt(),
        'items':          items,
        'payment_method': _apiPayment,
        if (_waafiReference != null) 'payment_reference': _waafiReference,
        'district_id': _districtId,
        'delivery_address': {
          'district': _districtName ?? '',
          'city':     _districtName ?? '',
          if (_nameCtrl.text.trim().isNotEmpty)  'name':  _nameCtrl.text.trim(),
          if (_phoneCtrl.text.trim().isNotEmpty) 'phone': _phoneCtrl.text.trim(),
        },
        if (_pointsToRedeem > 0) 'points_to_redeem': _pointsToRedeem,
      });

      ref.read(_cartProvider.notifier).clear();
      if (_payment == 'wallet') ref.invalidate(walletProvider);
      if (mounted) {
        final data        = result['data'] ?? result;
        final orderId     = data['order_id'] ?? data['id'] ?? 1;
        final orderNumber = data['order_number'] as String?;
        if (_mobileProofToken != null && orderNumber != null) {
          _svc.attachMobilePayProof(orderNumber, _mobileProofToken!);
        }
        Navigator.pushReplacement(context, MaterialPageRoute(
            builder: (_) => _TrackOrderPage(orderId: (orderId as num).toInt())));
      }
    } catch (e) {
      setState(() => _placing = false);
      if (mounted) _showErrorDialog(_extractError(e));
    }
  }

  String _extractError(Object e) => AppErrorHandler.message(e);

  void _showErrorDialog(String message) {
    showDialog(
      context: context,
      builder: (_) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(
              width: 64, height: 64,
              decoration: BoxDecoration(
                  color: Colors.red.withValues(alpha: 0.1), shape: BoxShape.circle),
              child: const Icon(Icons.error_outline_rounded, color: Colors.red, size: 36),
            ),
            const SizedBox(height: 16),
            Text('Order Failed',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17, color: context.colors.navyText),
                textAlign: TextAlign.center),
            const SizedBox(height: 10),
            Text(message,
                style: TextStyle(color: Colors.grey[600], fontSize: 13, height: 1.5),
                textAlign: TextAlign.center),
            const SizedBox(height: 22),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () => Navigator.of(context).pop(),
                style: ElevatedButton.styleFrom(
                  backgroundColor: _primary,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                child: const Text('Try Again', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
              ),
            ),
          ]),
        ),
      ),
    );
  }
}

class _PaymentOption extends StatelessWidget {
  final String id, icon, label, subtitle;
  final bool selected;
  final VoidCallback onTap;
  const _PaymentOption({required this.id, required this.icon, required this.label, required this.subtitle, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: selected ? _primary : Colors.grey.shade200, width: selected ? 2 : 1),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 6)],
      ),
      child: Row(children: [
        Container(
          width: 22, height: 22,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: selected ? _primary : Colors.grey.shade400, width: 2),
            color: selected ? _primary : Colors.transparent,
          ),
          child: selected ? const Icon(Icons.check_rounded, color: Colors.white, size: 14) : null,
        ),
        Text(icon, style: const TextStyle(fontSize: 22)),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: context.colors.navyText)),
          if (subtitle.isNotEmpty) Text(subtitle, style: TextStyle(fontSize: 12, color: Colors.grey[500])),
        ])),
      ]),
    ),
  );
}

// ════════════════════════════════════════════════════════════════════
// TRACK ORDER PAGE
// ════════════════════════════════════════════════════════════════════

class _TrackOrderPage extends ConsumerWidget {
  final int orderId;
  const _TrackOrderPage({required this.orderId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final track = ref.watch(_trackProvider(orderId));

    return Scaffold(
      backgroundColor: context.colors.scaffoldBg,
      appBar: AppBar(
        backgroundColor: context.colors.cardBg,
        elevation: 0,
        leading: IconButton(icon: Icon(Icons.arrow_back_rounded, color: context.colors.navyText), onPressed: () => Navigator.pop(context)),
        title: Text('Track Order', style: TextStyle(color: context.colors.navyText, fontWeight: FontWeight.w700, fontSize: 18)),
        centerTitle: true,
      ),
      body: track.when(
        data: (data) => _buildTracking(context, data),
        loading: () => _buildTracking(context, null),
        error: (_, __) => _buildTracking(context, null),
      ),
    );
  }

  String _statusLabel(String status) {
    switch (status) {
      case 'confirmed':  return 'Confirmed ✓';
      case 'preparing':  return 'Preparing…';
      case 'on_the_way': return 'On the Way 🛵';
      case 'delivered':  return 'Delivered ✓';
      default:           return 'Order Placed ✓';
    }
  }

  Widget _buildTracking(BuildContext context, dynamic data) {
    final status = data?['status'] ?? 'pending';
    final orderNumber = data?['order_number'] ?? '#ES${orderId.toString().padLeft(6, '0')}';
    final rider = data?['rider'];

    final steps = [
      {'label': 'Confirmed',  'icon': Icons.check_circle_rounded,    'status': 'confirmed'},
      {'label': 'Preparing',  'icon': Icons.restaurant_rounded,      'status': 'preparing'},
      {'label': 'On the Way', 'icon': Icons.delivery_dining_rounded, 'status': 'on_the_way'},
      {'label': 'Delivered',  'icon': Icons.home_rounded,            'status': 'delivered'},
    ];

    final currentIdx = steps.indexWhere((s) => s['status'] == status);

    return ListView(padding: const EdgeInsets.all(16), children: [
      // Dark order card
      Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(color: _secondary, borderRadius: BorderRadius.circular(20)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text('Order $orderNumber', style: const TextStyle(color: Colors.white70, fontSize: 13)),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
              child: const Text('Help', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
            ),
          ]),
          const SizedBox(height: 8),
          const Text('Order Status', style: TextStyle(color: Colors.white60, fontSize: 12)),
          Text(_statusLabel(status), style: const TextStyle(color: _primary, fontSize: 28, fontWeight: FontWeight.w900)),
          const SizedBox(height: 20),
          // Timeline
          Row(children: steps.asMap().entries.map((entry) {
            final i = entry.key;
            final step = entry.value;
            final isActive = i <= currentIdx;
            final isLast = i == steps.length - 1;
            return Expanded(child: Row(children: [
              Expanded(child: Column(children: [
                Container(
                  width: 40, height: 40,
                  decoration: BoxDecoration(
                    color: isActive ? _primary : Colors.white.withValues(alpha: 0.1),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(step['icon'] as IconData, color: isActive ? Colors.white : Colors.white38, size: 20),
                ),
                const SizedBox(height: 6),
                Text(step['label'] as String, style: TextStyle(color: isActive ? Colors.white : Colors.white38, fontSize: 10, fontWeight: isActive ? FontWeight.w700 : FontWeight.normal), textAlign: TextAlign.center),
              ])),
              if (!isLast) Expanded(child: Container(height: 2, color: i < currentIdx ? _primary : Colors.white.withValues(alpha: 0.2))),
            ]));
          }).toList()),
        ]),
      ),

      const SizedBox(height: 16),

      // Order confirmation info card
      if (data == null)
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(18), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 12)]),
          child: Row(children: [
            Container(width: 56, height: 56, decoration: BoxDecoration(color: _primary.withValues(alpha: 0.1), shape: BoxShape.circle), child: const Center(child: CircularProgressIndicator(color: _primary, strokeWidth: 2.5))),
            const SizedBox(width: 16),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Order Placed!', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.navyText)),
              const SizedBox(height: 4),
              const Text('Loading order details…', style: TextStyle(color: Colors.grey, fontSize: 13)),
            ])),
          ]),
        )
      else
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(18), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 12)]),
          child: Row(children: [
            Container(
              width: 56, height: 56,
              decoration: BoxDecoration(color: _primary.withValues(alpha: 0.1), shape: BoxShape.circle),
              child: const Icon(Icons.check_circle_rounded, color: _primary, size: 32),
            ),
            const SizedBox(width: 16),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Order Confirmed!', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.navyText)),
              const SizedBox(height: 4),
              Text('Your food is being prepared', style: TextStyle(color: Colors.grey[600], fontSize: 13)),
            ])),
          ]),
        ),

      // Rider card (only if rider assigned)
      if (rider != null) ...[
        const SizedBox(height: 16),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(18), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 12)]),
          child: Row(children: [
            CircleAvatar(
              radius: 28,
              backgroundColor: _primary.withValues(alpha: 0.1),
              child: const Icon(Icons.person_rounded, color: _primary, size: 30),
            ),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(rider['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: context.colors.navyText)),
              const Text('Your Rider', style: TextStyle(fontSize: 12, color: Colors.grey)),
              if (rider['rating'] != null)
                Row(children: [const Icon(Icons.star_rounded, color: Colors.amber, size: 14), Text(' ${rider['rating']}', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 12))]),
            ])),
            Row(children: [
              _RiderBtn(icon: Icons.phone_rounded, onTap: () {}),
              const SizedBox(width: 8),
              _RiderBtn(icon: Icons.chat_rounded, onTap: () {}),
            ]),
          ]),
        ),
      ],
    ]);
  }
}

class _RiderBtn extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  const _RiderBtn({required this.icon, required this.onTap});
  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(
      width: 40, height: 40,
      decoration: BoxDecoration(
        color: _primary.withValues(alpha: 0.1),
        shape: BoxShape.circle,
        border: Border.all(color: _primary.withValues(alpha: 0.3)),
      ),
      child: Icon(icon, color: _primary, size: 20),
    ),
  );
}

// ════════════════════════════════════════════════════════════════════
// ORDERS TAB
// ════════════════════════════════════════════════════════════════════

class _OrdersTab extends ConsumerStatefulWidget {
  const _OrdersTab();
  @override
  ConsumerState<_OrdersTab> createState() => _OrdersTabState();
}

class _OrdersTabState extends ConsumerState<_OrdersTab> with SingleTickerProviderStateMixin {
  late TabController _tabs;

  @override
  void initState() { super.initState(); _tabs = TabController(length: 3, vsync: this); }
  @override
  void dispose() { _tabs.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final orders = ref.watch(_ordersProvider);

    return SafeArea(child: Column(children: [
      Container(
        color: context.colors.cardBg,
        child: Column(children: [
          Padding(padding: const EdgeInsets.fromLTRB(16, 16, 16, 12), child: Align(alignment: Alignment.centerLeft, child: Text('My Orders', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: context.colors.navyText)))),
          TabBar(
            controller: _tabs,
            labelColor: _primary,
            unselectedLabelColor: Colors.grey[500],
            labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
            indicatorColor: _primary,
            indicatorWeight: 3,
            tabs: const [Tab(text: 'Active'), Tab(text: 'Completed'), Tab(text: 'Cancelled')],
          ),
        ]),
      ),
      Expanded(child: orders.when(
        data: (data) {
          final raw  = data is Map ? (data['data'] ?? []) : data;
          final list = raw is List ? List<dynamic>.from(raw) : <dynamic>[];
          return RefreshIndicator(
            color: _primary,
            onRefresh: () async => ref.invalidate(_ordersProvider),
            child: TabBarView(
              controller: _tabs,
              children: [
                _OrderList(orders: list.where((o) => ['pending','confirmed','preparing','on_the_way'].contains(o['status'])).toList(), emptyMsg: 'No active orders'),
                _OrderList(orders: list.where((o) => o['status'] == 'delivered').toList(), emptyMsg: 'No completed orders'),
                _OrderList(orders: list.where((o) => o['status'] == 'cancelled').toList(), emptyMsg: 'No cancelled orders'),
              ],
            ),
          );
        },
        loading: () => const Center(child: CircularProgressIndicator(color: _primary)),
        error: (e, __) => Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          const Text('📦', style: TextStyle(fontSize: 60)),
          const SizedBox(height: 16),
          Text('Could not load orders', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: context.colors.navyText)),
          const SizedBox(height: 8),
          TextButton(onPressed: () => ref.invalidate(_ordersProvider), child: const Text('Retry', style: TextStyle(color: _primary))),
        ])),
      )),
    ]));
  }
}

class _OrderList extends StatelessWidget {
  final List orders;
  final String emptyMsg;
  const _OrderList({required this.orders, required this.emptyMsg});

  @override
  Widget build(BuildContext context) {
    if (orders.isEmpty) return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
      const Text('📦', style: TextStyle(fontSize: 60)),
      const SizedBox(height: 16),
      Text(emptyMsg, style: TextStyle(color: Colors.grey[500], fontSize: 15)),
    ]));
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: orders.length,
      itemBuilder: (ctx, i) => _OrderCard(order: orders[i]),
    );
  }
}

class _OrderCard extends StatelessWidget {
  final dynamic order;
  const _OrderCard({required this.order});

  Color _statusColor(String? s) {
    switch (s) {
      case 'delivered': return Colors.green;
      case 'cancelled': return Colors.red;
      case 'on_the_way': return _primary;
      default: return Colors.orange;
    }
  }

  @override
  Widget build(BuildContext context) {
    final o = order;
    final status = o['status'] ?? 'pending';
    return GestureDetector(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => _TrackOrderPage(orderId: o['id']))),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)]),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text('Order #${o['order_number'] ?? o['id']}', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: context.colors.navyText)),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(color: _statusColor(status).withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
              child: Text(status.toString().replaceAll('_', ' ').toUpperCase(), style: TextStyle(color: _statusColor(status), fontSize: 10, fontWeight: FontWeight.w700)),
            ),
          ]),
          const SizedBox(height: 8),
          Text(o['restaurant']?['name'] ?? o['restaurant_name'] ?? 'Restaurant', style: TextStyle(fontSize: 13, color: Colors.grey[600])),
          const SizedBox(height: 4),
          Text('${o['items_count'] ?? (o['items'] as List?)?.length ?? 0} items • \$${o['total'] ?? o['total_amount'] ?? '0.00'}', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: context.colors.navyText)),
          const SizedBox(height: 8),
          if (status != 'delivered' && status != 'cancelled')
            Align(alignment: Alignment.centerRight, child: TextButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => _TrackOrderPage(orderId: o['id']))), child: const Text('Track Order →', style: TextStyle(color: _primary, fontWeight: FontWeight.w700, fontSize: 13)))),
        ]),
      ),
    );
  }
}

// ════════════════════════════════════════════════════════════════════
// FAVORITES TAB
// ════════════════════════════════════════════════════════════════════

class _FavoritesTab extends ConsumerWidget {
  const _FavoritesTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final favData = ref.watch(_favoritesProvider);

    return SafeArea(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Padding(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
        child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Text('Favorites', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: context.colors.navyText)),
          IconButton(icon: const Icon(Icons.refresh_rounded, color: _primary), onPressed: () => ref.invalidate(_favoritesProvider)),
        ]),
      ),
      Expanded(child: favData.when(
        data: (data) {
          final raw  = data is Map ? (data['data'] ?? []) : data;
          final list = raw is List ? raw : [];
          if (list.isEmpty) return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            const Text('❤️', style: TextStyle(fontSize: 60)),
            const SizedBox(height: 16),
            Text('No favorites yet', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: context.colors.navyText)),
            const SizedBox(height: 8),
            Text('Tap ♡ on any restaurant to save it', style: TextStyle(color: Colors.grey[500], fontSize: 13)),
          ]));
          return RefreshIndicator(
            color: _primary,
            onRefresh: () async => ref.invalidate(_favoritesProvider),
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: list.length,
              itemBuilder: (ctx, i) {
                final r = list[i];
                return _FavRestaurantTile(restaurant: r);
              },
            ),
          );
        },
        loading: () => const Center(child: CircularProgressIndicator(color: _primary)),
        error: (e, __) => Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          const Text('❤️', style: TextStyle(fontSize: 60)),
          const SizedBox(height: 16),
          Text('No favorites yet', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: context.colors.navyText)),
          const SizedBox(height: 8),
          TextButton(onPressed: () => ref.invalidate(_favoritesProvider), child: const Text('Retry', style: TextStyle(color: _primary))),
        ])),
      )),
    ]));
  }
}

class _FavRestaurantTile extends ConsumerWidget {
  final dynamic restaurant;
  const _FavRestaurantTile({required this.restaurant});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final r   = restaurant;
    final rid = (r['id'] as num?)?.toInt() ?? 0;
    final isFav = ref.watch(_favProvider).contains(rid);

    return GestureDetector(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => _RestaurantDetailPage(restaurant: r))),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(16),
            boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 10, offset: const Offset(0, 3))]),
        child: Row(children: [
          ClipRRect(
            borderRadius: const BorderRadius.horizontal(left: Radius.circular(16)),
            child: _NetImg(url: r['cover_image'] ?? r['logo'], width: 90, height: 90, radius: 0,
                fallback: Container(width: 90, height: 90, color: _primary.withValues(alpha: 0.12), child: const Center(child: Text('🍽️', style: TextStyle(fontSize: 28))))),
          ),
          Expanded(child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(r['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.navyText), maxLines: 1, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 3),
              Text(r['cuisine_type'] ?? r['vendor_type'] ?? r['categories'] ?? '', style: const TextStyle(fontSize: 13, color: Color(0xFF444444), fontWeight: FontWeight.w500)),
              const SizedBox(height: 6),
              Row(children: [
                if (r['rating'] != null) ...[
                  const Icon(Icons.star_rounded, color: Colors.amber, size: 14),
                  const SizedBox(width: 2),
                  Text('${r['rating']}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFF333333))),
                  const SizedBox(width: 8),
                ],
                if (r['delivery_time'] != null) ...[
                  Icon(Icons.access_time_rounded, color: Colors.grey[600], size: 13),
                  const SizedBox(width: 2),
                  Text('${r['delivery_time']} min', style: const TextStyle(fontSize: 13, color: Color(0xFF555555), fontWeight: FontWeight.w500)),
                ],
              ]),
            ]),
          )),
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: GestureDetector(
              onTap: () {
                ref.read(_favProvider.notifier).toggle(rid);
                ref.invalidate(_favoritesProvider);
              },
              child: Icon(isFav ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                  color: isFav ? Colors.red : Colors.grey[400], size: 24),
            ),
          ),
        ]),
      ),
    );
  }
}

// ════════════════════════════════════════════════════════════════════
// PROFILE TAB
// ════════════════════════════════════════════════════════════════════


// ── District picker bottom sheet for efood checkout ─────────────────────────

class _EfoodDistrictSheet extends StatefulWidget {
  final List<DistrictModel> districts;
  final int? selectedId;
  final void Function(DistrictModel) onSelected;
  const _EfoodDistrictSheet({required this.districts, required this.selectedId, required this.onSelected});

  @override
  State<_EfoodDistrictSheet> createState() => _EfoodDistrictSheetState();
}

class _EfoodDistrictSheetState extends State<_EfoodDistrictSheet> {
  String _search = '';

  @override
  Widget build(BuildContext context) {
    final filtered = widget.districts.where((d) => d.name.toLowerCase().contains(_search.toLowerCase())).toList();

    return Container(
      height: MediaQuery.of(context).size.height * 0.65,
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(children: [
        const SizedBox(height: 8),
        Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey.withValues(alpha: 0.3), borderRadius: BorderRadius.circular(2))),
        const SizedBox(height: 16),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(children: [
            Expanded(child: Text('Select District', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17, color: context.colors.navyText))),
            IconButton(icon: const Icon(Icons.close_rounded), onPressed: () => Navigator.pop(context)),
          ]),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
          child: TextField(
            autofocus: true,
            onChanged: (v) => setState(() => _search = v),
            decoration: InputDecoration(
              hintText: 'Search district...',
              prefixIcon: const Icon(Icons.search_rounded, size: 18, color: Colors.grey),
              filled: true,
              fillColor: context.colors.scaffoldBg,
              contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
            ),
          ),
        ),
        Expanded(
          child: ListView.builder(
            itemCount: filtered.length,
            itemBuilder: (_, i) {
              final d = filtered[i];
              final selected = d.id == widget.selectedId;
              return ListTile(
                leading: Container(
                  width: 36, height: 36,
                  decoration: BoxDecoration(
                    color: selected ? _primary : Colors.grey.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(Icons.location_on_rounded, size: 18, color: selected ? Colors.white : Colors.grey),
                ),
                title: Text(d.name, style: TextStyle(fontWeight: selected ? FontWeight.w700 : FontWeight.w500, color: selected ? _primary : context.colors.navyText)),
                trailing: selected ? const Icon(Icons.check_circle_rounded, color: _primary) : null,
                onTap: () => widget.onSelected(d),
              );
            },
          ),
        ),
      ]),
    );
  }
}
