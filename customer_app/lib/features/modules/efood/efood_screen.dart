import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/utils/error_handler.dart';
import '../../../core/widgets/network_image_widget.dart';
import '../../payment/waafi_pay_sheet.dart';
import '../../../shared/widgets/wallet_pin_dialog.dart';
import '../../../features/wallet/presentation/providers/wallet_provider.dart';
import '../../ads/services/ad_service.dart';

// ════════════════════════════════════════════════════════════════════
// CONSTANTS
// ════════════════════════════════════════════════════════════════════

const _primary   = Color(0xFFFF8A00);
const _secondary = Color(0xFF07003B);
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
// Encoding: "search|category|featured|topRated"
final _restaurantsProvider = FutureProvider.family<dynamic, String>((_, key) {
  final parts = key.split('|');
  final search   = parts[0].isEmpty ? null : parts[0];
  final category = parts[1].isEmpty ? null : parts[1];
  final featured = parts[2] == '1' ? true  : (parts[2] == '0' ? false : null);
  final topRated = parts[3] == '1' ? true  : (parts[3] == '0' ? false : null);
  return _svc.getRestaurants(
    search:   search,
    category: category,
    featured: featured,
    topRated: topRated,
  );
});

/// Build the string key for [_restaurantsProvider].
String _rKey({String? search, String? category, bool? featured, bool? topRated}) =>
    '${search ?? ""}|${category ?? ""}|${featured == null ? "" : featured ? "1" : "0"}|${topRated == null ? "" : topRated ? "1" : "0"}';

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
  _CartNotifier() : super([]);

  void add(_CartItem item) {
    final idx = state.indexWhere((e) => e.key == item.key);
    if (idx >= 0) {
      final updated = [...state];
      updated[idx].qty += item.qty;
      state = updated;
    } else {
      state = [...state, item];
    }
  }

  void increment(String key) {
    state = [for (final e in state) if (e.key == key)
      _CartItem(product: e.product, addons: e.addons, size: e.size, overridePrice: e.overridePrice, qty: e.qty + 1)
    else e];
  }

  void decrement(String key) {
    final updated = state.map((e) {
      if (e.key == key) {
        return _CartItem(product: e.product, addons: e.addons, size: e.size, overridePrice: e.overridePrice, qty: e.qty - 1);
      }
      return e;
    }).where((e) => e.qty > 0).toList();
    state = updated;
  }

  void remove(String key) => state = state.where((e) => e.key != key).toList();

  void clear() => state = [];

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

final _navIndexProvider = StateProvider<int>((_) => 0);

// ════════════════════════════════════════════════════════════════════
// ROOT SCREEN
// ════════════════════════════════════════════════════════════════════

class EFoodScreen extends ConsumerWidget {
  const EFoodScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final idx = ref.watch(_navIndexProvider);
    final cart = ref.watch(_cartProvider);

    return Scaffold(
      backgroundColor: _bg,
      body: IndexedStack(
        index: idx,
        children: const [
          _HomeTab(),
          _SearchTab(),
          _OrdersTab(),
          _FavoritesTab(),
          _ProfileTab(),
        ],
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: _card,
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.08), blurRadius: 16, offset: const Offset(0, -4))],
        ),
        child: BottomNavigationBar(
          currentIndex: idx,
          onTap: (i) => ref.read(_navIndexProvider.notifier).state = i,
          type: BottomNavigationBarType.fixed,
          backgroundColor: _card,
          selectedItemColor: _primary,
          unselectedItemColor: Colors.grey[400],
          selectedLabelStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 11),
          unselectedLabelStyle: const TextStyle(fontSize: 11),
          elevation: 0,
          items: [
            const BottomNavigationBarItem(icon: Icon(Icons.home_rounded), label: 'Home'),
            const BottomNavigationBarItem(icon: Icon(Icons.search_rounded), label: 'Search'),
            BottomNavigationBarItem(
              icon: Stack(
                clipBehavior: Clip.none,
                children: [
                  const Icon(Icons.receipt_long_rounded),
                  if (cart.isNotEmpty)
                    Positioned(
                      right: -6, top: -4,
                      child: Container(
                        width: 16, height: 16,
                        decoration: const BoxDecoration(color: _primary, shape: BoxShape.circle),
                        child: Center(child: Text('${cart.length}', style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold))),
                      ),
                    ),
                ],
              ),
              label: 'Orders',
            ),
            const BottomNavigationBarItem(icon: Icon(Icons.favorite_rounded), label: 'Favorites'),
            const BottomNavigationBarItem(icon: Icon(Icons.person_rounded), label: 'Profile'),
          ],
        ),
      ),
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
    });
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      slivers: [
        _buildHeader(context),
        SliverToBoxAdapter(child: _buildSearch(context)),
        SliverToBoxAdapter(child: _BannerSlider()),
        SliverToBoxAdapter(child: _buildCategories()),
        SliverToBoxAdapter(child: _buildSection('Popular Restaurants', featured: true)),
        SliverToBoxAdapter(child: _buildSection('Top Rated', topRated: true)),
        SliverToBoxAdapter(child: _buildSection('Near You')),
        const SliverToBoxAdapter(child: SizedBox(height: 24)),
      ],
    );
  }

  SliverAppBar _buildHeader(BuildContext context) => SliverAppBar(
    floating: true,
    snap: true,
    backgroundColor: _card,
    elevation: 0,
    automaticallyImplyLeading: false,
    expandedHeight: 80,
    flexibleSpace: FlexibleSpaceBar(
      background: Container(
        color: _card,
        padding: const EdgeInsets.fromLTRB(16, 44, 16, 8),
        child: Row(
          children: [
            Container(
              width: 40, height: 40,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: _primary.withValues(alpha: 0.15),
              ),
              child: const Icon(Icons.person_rounded, color: _primary, size: 22),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Row(children: [
                    Text('eSahlan', style: TextStyle(color: _primary, fontWeight: FontWeight.w800, fontSize: 15)),
                    const SizedBox(width: 4),
                    Text('Sahlan', style: TextStyle(color: _secondary, fontWeight: FontWeight.w800, fontSize: 15)),
                  ]),
                  Row(children: [
                    const Icon(Icons.location_on_rounded, color: _primary, size: 13),
                    const SizedBox(width: 2),
                    const Text('Mogadishu', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w500, color: Colors.black87)),
                    const Icon(Icons.keyboard_arrow_down_rounded, size: 16, color: Colors.grey),
                  ]),
                ],
              ),
            ),
            Stack(
              clipBehavior: Clip.none,
              children: [
                Container(
                  width: 40, height: 40,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: _bg,
                    border: Border.all(color: Colors.grey.shade200),
                  ),
                  child: const Icon(Icons.notifications_none_rounded, color: _secondary),
                ),
                Positioned(
                  right: 2, top: 2,
                  child: Container(
                    width: 10, height: 10,
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

  Widget _buildSearch(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
    child: Row(children: [
      Expanded(
        child: GestureDetector(
          onTap: () => ref.read(_navIndexProvider.notifier).state = 1,
          child: Container(
            height: 48,
            decoration: BoxDecoration(
              color: _card,
              borderRadius: BorderRadius.circular(14),
              boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 10)],
            ),
            child: Row(children: [
              const SizedBox(width: 14),
              Icon(Icons.search_rounded, color: Colors.grey[400]),
              const SizedBox(width: 8),
              Text('Search for food or restaurants...', style: TextStyle(color: Colors.grey[400], fontSize: 13)),
            ]),
          ),
        ),
      ),
      const SizedBox(width: 10),
      Container(
        width: 48, height: 48,
        decoration: BoxDecoration(color: _primary, borderRadius: BorderRadius.circular(14)),
        child: const Icon(Icons.tune_rounded, color: Colors.white),
      ),
    ]),
  );

  Widget _buildCategories() {
    final cats = ref.watch(_catsProvider);

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          const Text('Food Categories', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: _secondary)),
          TextButton(onPressed: () {}, child: const Text('View all', style: TextStyle(color: _primary, fontWeight: FontWeight.w600))),
        ]),
        const SizedBox(height: 12),
        cats.when(
          data: (data) {
            final raw = data is Map ? (data['data'] ?? data) : data;
            final list = (raw is List && raw.isNotEmpty) ? raw : <dynamic>[];
            if (list.isEmpty) return const SizedBox.shrink();
            return GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 5,
                mainAxisSpacing: 12,
                crossAxisSpacing: 8,
                childAspectRatio: 0.75,
              ),
              itemCount: list.length > 10 ? 10 : list.length,
              itemBuilder: (_, i) {
                final c = list[i];
                final name = c['name'] ?? '';
                final sel = _selectedCat == name;
                return GestureDetector(
                  onTap: () => setState(() => _selectedCat = sel ? '' : name),
                  child: Column(children: [
                    Container(
                      width: 52, height: 52,
                      decoration: BoxDecoration(
                        color: sel ? _primary : _card,
                        borderRadius: BorderRadius.circular(14),
                        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.07), blurRadius: 8)],
                      ),
                      child: c['image'] != null
                          ? ClipRRect(borderRadius: BorderRadius.circular(14), child: Image.network(fixImgUrl(c['image']), fit: BoxFit.cover))
                          : const Center(child: Icon(Icons.fastfood_rounded, color: _primary, size: 24)),
                    ),
                    const SizedBox(height: 6),
                    Text(name, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: sel ? _primary : Colors.black87), textAlign: TextAlign.center, maxLines: 1, overflow: TextOverflow.ellipsis),
                  ]),
                );
              },
            );
          },
          loading: () => const SizedBox(height: 100, child: Center(child: CircularProgressIndicator(color: _primary))),
          error: (_, __) => const SizedBox.shrink(),
        ),
      ]),
    );
  }

  Widget _buildSection(String title, {bool featured = false, bool topRated = false}) {
    final restaurants = ref.watch(_restaurantsProvider(_rKey(
      featured: featured,
      topRated: topRated,
      category: _selectedCat.isEmpty ? null : _selectedCat,
    )));

    return Padding(
      padding: const EdgeInsets.fromLTRB(0, 24, 0, 0),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text(title, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: _secondary)),
            TextButton(onPressed: () {}, child: const Text('View all', style: TextStyle(color: _primary, fontWeight: FontWeight.w600))),
          ]),
        ),
        const SizedBox(height: 12),
        restaurants.when(
          data: (data) {
            final list = data is List ? data : (data['data'] ?? []);
            if (list.isEmpty) return const SizedBox.shrink();
            return SizedBox(
              height: 220,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: list.length,
                itemBuilder: (_, i) => _RestaurantCard(restaurant: list[i], onTap: () => _openRestaurant(context, list[i])),
              ),
            );
          },
          loading: () => const SizedBox(height: 220, child: Center(child: CircularProgressIndicator(color: _primary))),
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
    final r = restaurant;
    final rid  = (r['id'] as num?)?.toInt() ?? 0;
    final isFav = ref.watch(_favProvider).contains(rid);
    // SQLite returns 0/1 integers — convert to bool safely
    final isOpen = _asBool(r['is_open'] ?? r['is_active'] ?? 1);

    // Fetch active campaign directly for this restaurant
    final campaignAsync = ref.watch(_restaurantCampaignsProvider(rid));
    final campaigns = campaignAsync.asData?.value;
    final campaignList = (campaigns is Map ? campaigns['data'] : null) as List?;
    final activeCampaign = (campaignList != null && campaignList.isNotEmpty) ? campaignList.first : null;

    // Build badge label from campaign
    String? badgeLabel;
    String? badgeColorStr;
    if (activeCampaign != null) {
      final type  = activeCampaign['discount_type'] ?? 'percentage';
      final value = double.tryParse('${activeCampaign['discount_value'] ?? 0}') ?? 0;
      badgeLabel    = type == 'percentage' ? '-${value.toInt()}%' : '-\$${value.toStringAsFixed(0)}';
      badgeColorStr = activeCampaign['badge_color'];
    }

    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 190,
        margin: const EdgeInsets.only(right: 14),
        decoration: BoxDecoration(
          color: _card,
          borderRadius: BorderRadius.circular(18),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.07), blurRadius: 12, offset: const Offset(0, 4))],
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Stack(children: [
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(18)),
              child: _NetImg(url: r['cover_image'], width: double.infinity, height: 110, radius: 0,
                  fallback: Container(height: 110, color: _primary.withValues(alpha: 0.15), child: const Center(child: Text('🍽️', style: TextStyle(fontSize: 40))))),
            ),
            if (!isOpen)
              ClipRRect(
                borderRadius: const BorderRadius.vertical(top: Radius.circular(18)),
                child: Container(height: 110, color: Colors.black.withValues(alpha: 0.45), child: const Center(child: Text('CLOSED', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)))),
              ),
            Positioned(top: 8, right: 8,
              child: GestureDetector(
                onTap: () => ref.read(_favProvider.notifier).toggle(rid),
                child: Container(
                  width: 32, height: 32,
                  decoration: BoxDecoration(color: _card, shape: BoxShape.circle, boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 6)]),
                  child: Icon(isFav ? Icons.favorite_rounded : Icons.favorite_border_rounded, color: isFav ? Colors.red : Colors.grey[400], size: 18),
                ),
              ),
            ),
          ]),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Expanded(
                  child: Text(r['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: _secondary), maxLines: 1, overflow: TextOverflow.ellipsis),
                ),
                // Campaign badge — top-right of info section
                if (badgeLabel != null) ...[
                  const SizedBox(width: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: _parseBadgeColor(badgeColorStr),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(mainAxisSize: MainAxisSize.min, children: [
                      const Text('🔥', style: TextStyle(fontSize: 11)),
                      const SizedBox(width: 3),
                      Text(badgeLabel, style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w900)),
                    ]),
                  ),
                ],
              ]),
              const SizedBox(height: 3),
              Text(r['cuisine_type'] ?? r['categories'] ?? 'Restaurant', style: TextStyle(fontSize: 11, color: Colors.grey[500]), maxLines: 1, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 6),
              Row(children: [
                if (r['rating'] != null) ...[
                  const Icon(Icons.star_rounded, color: Colors.amber, size: 14),
                  const SizedBox(width: 2),
                  Text('${r['rating']}', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600)),
                  const SizedBox(width: 8),
                ],
                if (r['delivery_time'] != null) ...[
                  Icon(Icons.access_time_rounded, color: Colors.grey[400], size: 12),
                  const SizedBox(width: 2),
                  Text('${r['delivery_time']} min', style: TextStyle(fontSize: 11, color: Colors.grey[500])),
                ],
              ]),
              const SizedBox(height: 3),
              Builder(builder: (_) {
                final fee = r['delivery_fee'];
                final feeNum = fee == null ? null : double.tryParse('$fee');
                final isFree = feeNum == null || feeNum == 0;
                return Text(
                  isFree ? 'Free delivery' : '\$${feeNum.toStringAsFixed(2)} delivery',
                  style: TextStyle(fontSize: 11, color: isFree ? Colors.green[600] : Colors.grey[500], fontWeight: FontWeight.w500),
                );
              }),
            ]),
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
    final results = ref.watch(_restaurantsProvider(_rKey(search: _query.isEmpty ? null : _query)));

    return SafeArea(child: Column(children: [
      Container(
        color: _card, padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
        child: TextField(
          controller: _ctrl,
          onChanged: (v) => setState(() => _query = v),
          decoration: InputDecoration(
            hintText: 'Search restaurants, food...',
            hintStyle: TextStyle(color: Colors.grey[400], fontSize: 14),
            prefixIcon: const Icon(Icons.search_rounded, color: _primary),
            suffixIcon: _query.isNotEmpty ? IconButton(icon: const Icon(Icons.clear), onPressed: () { _ctrl.clear(); setState(() => _query = ''); }) : null,
            filled: true, fillColor: _bg,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
            contentPadding: const EdgeInsets.symmetric(vertical: 14),
          ),
        ),
      ),
      Expanded(child: results.when(
        data: (data) {
          final list = data is List ? data : (data['data'] ?? []);
          if (_query.isEmpty) return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [const Text('🔍', style: TextStyle(fontSize: 60)), const SizedBox(height: 16), Text('Search for your favourite food', style: TextStyle(color: Colors.grey[500], fontSize: 15))]));
          if (list.isEmpty) return Center(child: Text('No results for "$_query"', style: TextStyle(color: Colors.grey[500])));
          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: list.length,
            itemBuilder: (_, i) => _RestaurantListTile(restaurant: list[i], onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => _RestaurantDetailPage(restaurant: list[i])))),
          );
        },
        loading: () => const Center(child: CircularProgressIndicator(color: _primary)),
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
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        decoration: BoxDecoration(color: _card, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 10)]),
        child: Row(children: [
          _NetImg(
            url: r['cover_image'],
            width: 90, height: 80, radius: 16,
            fallback: Container(width: 90, height: 80, color: _primary.withValues(alpha: 0.1), child: const Center(child: Icon(Icons.restaurant_rounded, color: _primary, size: 30))),
          ),
          const SizedBox(width: 12),
          Expanded(child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(r['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: _secondary)),
              const SizedBox(height: 3),
              Text(r['cuisine_type'] ?? 'Restaurant', style: TextStyle(fontSize: 12, color: Colors.grey[500])),
              const SizedBox(height: 6),
              Row(children: [
                if (r['rating'] != null) ...[
                  const Icon(Icons.star_rounded, color: Colors.amber, size: 14),
                  Text(' ${r['rating']}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                ],
                if (r['delivery_time'] != null)
                  Text('${r['rating'] != null ? '  •  ' : ''}${r['delivery_time']} min', style: TextStyle(fontSize: 12, color: Colors.grey[500])),
              ]),
            ]),
          )),
          const Padding(padding: EdgeInsets.only(right: 12), child: Icon(Icons.arrow_forward_ios_rounded, size: 14, color: Colors.grey)),
        ]),
      ),
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
    return Image.network(
      fixImgUrl(u),
      fit: BoxFit.cover,
      errorBuilder: (_, __, ___) => Container(
        color: _primary.withValues(alpha: 0.2),
        child: const Center(child: Text('🍽️', style: TextStyle(fontSize: 80))),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final r = widget.restaurant;
    final id = r['id'] as int;
    final cart = ref.watch(_cartProvider);

    return Scaffold(
      backgroundColor: _bg,
      body: NestedScrollView(
        headerSliverBuilder: (_, __) => [
          SliverAppBar(
            expandedHeight: 240,
            pinned: true,
            backgroundColor: _secondary,
            leading: GestureDetector(
              onTap: () => Navigator.pop(context),
              child: Container(margin: const EdgeInsets.all(8), decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.9), shape: BoxShape.circle), child: const Icon(Icons.arrow_back_rounded, color: _secondary)),
            ),
            actions: [
              Container(margin: const EdgeInsets.all(8), decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.9), shape: BoxShape.circle),
                child: IconButton(icon: const Icon(Icons.share_rounded, color: _secondary, size: 20), onPressed: () {})),
            ],
            flexibleSpace: FlexibleSpaceBar(
              background: Stack(fit: StackFit.expand, children: [
                _buildCoverImage(r['cover_image']),
                Container(decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Colors.transparent, Colors.black.withValues(alpha: 0.6)]))),
              ]),
            ),
          ),
          SliverToBoxAdapter(child: Container(
            color: _card,
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              // ── Restaurant name, logo, rating ─────────────────────
              Row(children: [
                _NetImg(url: r['logo'], width: 56, height: 56, radius: 12, fallback: const Icon(Icons.restaurant_rounded, color: _primary, size: 28)),
                const SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(r['name'] ?? '', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: _secondary)),
                  Text(r['cuisine_type'] ?? r['description'] ?? 'Restaurant', style: TextStyle(fontSize: 13, color: Colors.grey[500])),
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
                              const Text('Open Now', style: TextStyle(color: Colors.green, fontWeight: FontWeight.w700, fontSize: 12)),
                            ]),
                          ),
                        ])
                      : Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(color: Colors.red.withValues(alpha: 0.07), borderRadius: BorderRadius.circular(10), border: Border.all(color: Colors.red.shade200)),
                          child: Row(children: [
                            const Icon(Icons.store_outlined, color: Colors.red, size: 18),
                            const SizedBox(width: 8),
                            const Expanded(
                              child: Text('This restaurant is currently closed. You cannot place an order at this time.',
                                style: TextStyle(color: Colors.red, fontSize: 12, fontWeight: FontWeight.w600)),
                            ),
                          ]),
                        ),
                );
              }),
              // ── Delivery info chips ────────────────────────────────
              Row(children: [
                if (r['delivery_time'] != null) _InfoChip(icon: Icons.access_time_rounded, text: '${r['delivery_time']} min'),
                if (r['delivery_time'] != null) const SizedBox(width: 12),
                _InfoChip(icon: Icons.delivery_dining_rounded, text: () { final f = double.tryParse('${r['delivery_fee'] ?? 0}'); return (f == null || f == 0) ? 'Free delivery' : '\$${f.toStringAsFixed(2)} delivery'; }()),
                const SizedBox(width: 12),
                _InfoChip(icon: Icons.shopping_bag_outlined, text: '\$${double.tryParse('${r['minimum_order'] ?? r['min_order'] ?? 0}')?.toStringAsFixed(2) ?? '0.00'} Min. order'),
              ]),

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

              const SizedBox(height: 14),
              // ── Search inside restaurant ───────────────────────────
              Container(
                height: 42,
                decoration: BoxDecoration(color: _bg, borderRadius: BorderRadius.circular(12)),
                child: TextField(
                  controller: _searchCtrl,
                  onChanged: (v) => setState(() => _search = v),
                  decoration: InputDecoration(
                    hintText: 'Search menu items...',
                    hintStyle: TextStyle(color: Colors.grey[400], fontSize: 13),
                    prefixIcon: Icon(Icons.search_rounded, color: Colors.grey[400], size: 20),
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
                tabs: const [Tab(text: 'Menu'), Tab(text: 'Reviews'), Tab(text: 'Info')],
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
            ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
              content: Text('This restaurant is currently closed. Cannot place order.'),
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
          const SizedBox(height: 14),
          const Text('Menu Categories', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: _secondary)),
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
                        color: sel ? _primary : _bg,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: sel ? _primary : Colors.grey.shade300),
                      ),
                      alignment: Alignment.center,
                      child: Text('All', style: TextStyle(
                        fontSize: 12, fontWeight: FontWeight.w600,
                        color: sel ? Colors.white : Colors.grey[600],
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
                      color: sel ? _primary : _bg,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: sel ? _primary : Colors.grey.shade300),
                    ),
                    alignment: Alignment.center,
                    child: Text(cat['name'] ?? '', style: TextStyle(
                      fontSize: 12, fontWeight: FontWeight.w600,
                      color: sel ? Colors.white : Colors.grey[600],
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
            const SizedBox(width: 6),
            const Text('Offers & Coupons', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: _secondary)),
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
                        minOrder > 0 ? 'Min order \$${minOrder.toStringAsFixed(2)}' : 'No minimum',
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
      builder: (_) => Dialog(
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
            Text(title, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: _secondary)),
            if (desc.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(desc, style: TextStyle(fontSize: 13, color: Colors.grey[500]), textAlign: TextAlign.center),
            ],
            const SizedBox(height: 14),
            // Coupon code box
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              decoration: BoxDecoration(
                color: _bg,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: _primary.withValues(alpha: 0.3), style: BorderStyle.solid),
              ),
              child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Text(code, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: _secondary, letterSpacing: 2)),
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
              Text('Min order: \$${minOrder.toStringAsFixed(2)}', style: TextStyle(fontSize: 12, color: Colors.grey[500])),
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
                child: const Text('Got it!', style: TextStyle(fontWeight: FontWeight.w700)),
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
      child: Image.network(
        fixImgUrl(u),
        width: width == double.infinity ? null : width,
        height: height,
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) => SizedBox(width: width == double.infinity ? null : width, height: height, child: fallback),
        loadingBuilder: (_, child, progress) => progress == null
            ? child
            : SizedBox(width: width == double.infinity ? null : width, height: height,
                child: Center(child: CircularProgressIndicator(strokeWidth: 2, color: _primary.withValues(alpha: 0.4)))),
      ),
    );
  }
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
      loading: () => const Center(child: CircularProgressIndicator(color: _primary)),
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
          decoration: BoxDecoration(color: _card, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 10)]),
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
                  Expanded(child: Text(p['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: _secondary), maxLines: 1, overflow: TextOverflow.ellipsis)),
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
                  return Text('\$${origPrice.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: _secondary));
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
    decoration: BoxDecoration(color: _card, borderRadius: BorderRadius.circular(14)),
    child: Row(children: [
      Container(width: 38, height: 38, decoration: BoxDecoration(color: _primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)), child: Icon(icon, color: _primary, size: 20)),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: TextStyle(fontSize: 11, color: Colors.grey[500])),
        Text(value, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: _secondary)),
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
      backgroundColor: _bg,
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
                  ? Image.network(fixImgUrl(url), fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => Container(color: _primary.withValues(alpha: 0.2),
                          child: const Center(child: Text('🍕', style: TextStyle(fontSize: 100)))))
                  : Container(color: _primary.withValues(alpha: 0.2),
                      child: const Center(child: Text('🍕', style: TextStyle(fontSize: 100))));
            }(),
          ),
        ),
        SliverToBoxAdapter(child: Container(
          decoration: const BoxDecoration(color: _bg, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
          padding: const EdgeInsets.all(20),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(p['name'] ?? '', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: _secondary)),
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
              const Text('Size', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: _secondary)),
              const SizedBox(height: 12),
              Wrap(spacing: 10, children: variants.map<Widget>((v) {
                final name = '${v['name'] ?? v['value'] ?? ''}';
                final sel  = _size == name;
                return GestureDetector(
                  onTap: () => setState(() => _size = name),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                    decoration: BoxDecoration(
                      color: sel ? _primary : _card,
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
              const Text('Addons', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: _secondary)),
              const SizedBox(height: 8),
            ],
            ...addons.map((a) => Container(
              margin: const EdgeInsets.only(bottom: 8),
              decoration: BoxDecoration(color: _card, borderRadius: BorderRadius.circular(12), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 6)]),
              child: CheckboxListTile(
                value: _selectedAddons.contains(a),
                onChanged: (v) => setState(() { v! ? _selectedAddons.add(a) : _selectedAddons.remove(a); }),
                activeColor: _primary,
                title: Text(a['name'] ?? '', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500)),
                secondary: Text('+\$${a['price'] ?? '0.00'}', style: const TextStyle(color: Colors.grey, fontWeight: FontWeight.w600)),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                controlAffinity: ListTileControlAffinity.leading,
              ),
            )),

            const SizedBox(height: 24),
            const Text('Special Instructions', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: _secondary)),
            const SizedBox(height: 10),
            Container(
              decoration: BoxDecoration(color: _card, borderRadius: BorderRadius.circular(14), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 6)]),
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
        color: _card,
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
        child: Row(children: [
          Container(
            decoration: BoxDecoration(color: _bg, borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.grey.shade200)),
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
  final _promoCtrl = TextEditingController();
  double _discount = 0;
  bool _promoApplied = false;

  @override
  void dispose() { _promoCtrl.dispose(); super.dispose(); }

  void _applyPromo() async {
    if (_promoCtrl.text.isEmpty) return;
    // call API to apply coupon
    setState(() { _discount = 2.0; _promoApplied = true; });
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Promo code applied! -\$2.00'), backgroundColor: Colors.green, behavior: SnackBarBehavior.floating));
  }

  @override
  Widget build(BuildContext context) {
    final cart = ref.watch(_cartProvider);
    final notifier = ref.read(_cartProvider.notifier);
    final subtotal = notifier.subtotal;
    const deliveryFee = 1.99;
    const tax = 0.90;
    final total = subtotal + deliveryFee + tax - _discount;

    return Scaffold(
      backgroundColor: _bg,
      appBar: AppBar(
        backgroundColor: _card,
        elevation: 0,
        leading: IconButton(icon: const Icon(Icons.arrow_back_rounded, color: _secondary), onPressed: () => Navigator.pop(context)),
        title: const Text('Your Cart', style: TextStyle(color: _secondary, fontWeight: FontWeight.w700, fontSize: 18)),
        actions: [
          if (cart.isNotEmpty)
            TextButton(onPressed: () => ref.read(_cartProvider.notifier).clear(), child: const Text('Clear', style: TextStyle(color: Colors.red))),
        ],
      ),
      body: cart.isEmpty
          ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
              const Text('🛒', style: TextStyle(fontSize: 60)),
              const SizedBox(height: 16),
              const Text('Your cart is empty', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600, color: _secondary)),
              const SizedBox(height: 8),
              Text('Add items to get started', style: TextStyle(color: Colors.grey[500])),
            ]))
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                ...cart.map((item) => _CartItemTile(item: item)),
                const SizedBox(height: 16),
                // Promo code
                Container(
                  decoration: BoxDecoration(color: _card, borderRadius: BorderRadius.circular(14), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)]),
                  child: Row(children: [
                    Expanded(child: TextField(
                      controller: _promoCtrl,
                      decoration: const InputDecoration(
                        hintText: 'Enter promo code',
                        hintStyle: TextStyle(color: Colors.grey, fontSize: 13),
                        border: InputBorder.none,
                        contentPadding: EdgeInsets.fromLTRB(14, 14, 0, 14),
                        prefixIcon: Icon(Icons.local_offer_rounded, color: Colors.grey, size: 18),
                      ),
                    )),
                    GestureDetector(
                      onTap: _applyPromo,
                      child: Container(
                        margin: const EdgeInsets.all(6),
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                        decoration: BoxDecoration(color: _promoApplied ? Colors.green : _primary, borderRadius: BorderRadius.circular(10)),
                        child: Text(_promoApplied ? 'Applied ✓' : 'Apply', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13)),
                      ),
                    ),
                  ]),
                ),
                const SizedBox(height: 16),
                // Price breakdown
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(color: _card, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)]),
                  child: Column(children: [
                    _PriceRow('Subtotal', '\$${subtotal.toStringAsFixed(2)}'),
                    _PriceRow('Delivery Fee', '\$${deliveryFee.toStringAsFixed(2)}'),
                    _PriceRow('Tax', '\$${tax.toStringAsFixed(2)}'),
                    if (_discount > 0) _PriceRow('Discount', '-\$${_discount.toStringAsFixed(2)}', color: Colors.green),
                    const Divider(height: 20),
                    Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                      const Text('Total', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: _secondary)),
                      Text('\$${total.toStringAsFixed(2)}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: _primary)),
                    ]),
                  ]),
                ),
                const SizedBox(height: 100),
              ],
            ),
      bottomNavigationBar: cart.isEmpty ? null : Container(
        color: _card,
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        child: GestureDetector(
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => _CheckoutPage(
            subtotal: subtotal, deliveryFee: deliveryFee, tax: tax, discount: _discount,
          ))),
          child: Container(
            height: 56,
            decoration: BoxDecoration(color: _primary, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: _primary.withValues(alpha: 0.4), blurRadius: 14, offset: const Offset(0, 6))]),
            child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
              const Text('Checkout', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
              const SizedBox(width: 8),
              Text('\$${(subtotal + deliveryFee + tax - _discount).toStringAsFixed(2)}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 16)),
            ]),
          ),
        ),
      ),
    );
  }
}

class _CartItemTile extends ConsumerWidget {
  final _CartItem item;
  const _CartItemTile({required this.item});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = item.product;
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: _card, borderRadius: BorderRadius.circular(14), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)]),
      child: Row(children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(10),
          child: p['image'] != null
              ? Image.network(fixImgUrl(p['image']), width: 70, height: 70, fit: BoxFit.cover)
              : Container(width: 70, height: 70, color: _primary.withValues(alpha: 0.1), child: const Center(child: Text('🍕', style: TextStyle(fontSize: 30)))),
        ),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(p['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: _secondary)),
          if (item.size != null) Text(item.size!, style: TextStyle(fontSize: 12, color: Colors.grey[500])),
          const SizedBox(height: 4),
          Row(children: [
            Text('\$${item.unitPrice.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: _primary)),
            if (item.hasDiscount) ...[
              const SizedBox(width: 6),
              Text('\$${(item.originalPrice + item.addons.fold(0.0, (s, a) => s + (double.tryParse('${a['price'] ?? 0}') ?? 0))).toStringAsFixed(2)}',
                  style: const TextStyle(fontSize: 11, color: Colors.grey,
                      decoration: TextDecoration.lineThrough, decorationColor: Colors.grey)),
            ],
          ]),
        ])),
        Row(children: [
          GestureDetector(
            onTap: () => ref.read(_cartProvider.notifier).decrement(item.key),
            child: Container(width: 28, height: 28, decoration: BoxDecoration(color: _bg, borderRadius: BorderRadius.circular(8), border: Border.all(color: Colors.grey.shade200)), child: const Icon(Icons.remove_rounded, size: 16)),
          ),
          Padding(padding: const EdgeInsets.symmetric(horizontal: 10), child: Text('${item.qty}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15))),
          GestureDetector(
            onTap: () => ref.read(_cartProvider.notifier).increment(item.key),
            child: Container(width: 28, height: 28, decoration: BoxDecoration(color: _primary, borderRadius: BorderRadius.circular(8)), child: const Icon(Icons.add_rounded, size: 16, color: Colors.white)),
          ),
        ]),
      ]),
    );
  }
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
      Text(value, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: color ?? _secondary)),
    ]),
  );
}

// ════════════════════════════════════════════════════════════════════
// CHECKOUT PAGE
// ════════════════════════════════════════════════════════════════════

class _CheckoutPage extends ConsumerStatefulWidget {
  final double subtotal, deliveryFee, tax, discount;
  const _CheckoutPage({required this.subtotal, required this.deliveryFee, required this.tax, required this.discount});

  @override
  ConsumerState<_CheckoutPage> createState() => _CheckoutPageState();
}

class _CheckoutPageState extends ConsumerState<_CheckoutPage> {
  String _payment = 'waafi';
  bool _placing = false;

  double get _total => widget.subtotal + widget.deliveryFee + widget.tax - widget.discount;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _bg,
      appBar: AppBar(
        backgroundColor: _card,
        elevation: 0,
        leading: IconButton(icon: const Icon(Icons.arrow_back_rounded, color: _secondary), onPressed: () => Navigator.pop(context)),
        title: const Text('Checkout', style: TextStyle(color: _secondary, fontWeight: FontWeight.w700, fontSize: 18)),
        centerTitle: true,
      ),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        // Delivery Address
        const Text('Delivery Address', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: _secondary)),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: _card, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)]),
          child: Row(children: [
            Container(width: 40, height: 40, decoration: BoxDecoration(color: _primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)), child: const Icon(Icons.home_rounded, color: _primary)),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                const Text('Home', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: _secondary)),
                const SizedBox(width: 8),
                Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2), decoration: BoxDecoration(color: _primary, borderRadius: BorderRadius.circular(6)), child: const Text('Default', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w600))),
              ]),
              const SizedBox(height: 3),
              Text('Mogadishu, Somalia', style: TextStyle(fontSize: 12, color: Colors.grey[500], height: 1.5)),
            ])),
            TextButton(onPressed: () {}, child: const Text('Change', style: TextStyle(color: _primary, fontWeight: FontWeight.w600))),
          ]),
        ),

        const SizedBox(height: 24),
        const Text('Payment Method', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: _secondary)),
        const SizedBox(height: 12),
        ...[
          ('waafi',  '📱', 'Waafi Pay',  'EVC / eDahab / Jeep / Premier'),
          ('wallet', '👛', 'Wallet',     'Pay from your balance'),
        ].map((m) => _PaymentOption(
          id: m.$1, icon: m.$2, label: m.$3, subtitle: m.$4,
          selected: _payment == m.$1,
          onTap: () => setState(() => _payment = m.$1),
        )),

        const SizedBox(height: 24),
        const Text('Order Summary', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: _secondary)),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: _card, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)]),
          child: Column(children: [
            _PriceRow('${ref.read(_cartProvider.notifier).totalItems} Items', '\$${widget.subtotal.toStringAsFixed(2)}'),
            _PriceRow('Delivery Fee', '\$${widget.deliveryFee.toStringAsFixed(2)}'),
            _PriceRow('Tax', '\$${widget.tax.toStringAsFixed(2)}'),
            if (widget.discount > 0) _PriceRow('Discount', '-\$${widget.discount.toStringAsFixed(2)}', color: Colors.green),
            const Divider(height: 20),
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              const Text('Total', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: _secondary)),
              Text('\$${_total.toStringAsFixed(2)}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: _primary)),
            ]),
          ]),
        ),
        const SizedBox(height: 100),
      ]),
      bottomNavigationBar: Container(
        color: _card,
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        child: GestureDetector(
          onTap: _placing ? null : _placeOrder,
          child: Container(
            height: 56,
            decoration: BoxDecoration(color: _primary, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: _primary.withValues(alpha: 0.4), blurRadius: 14, offset: const Offset(0, 6))]),
            child: _placing
                ? const Center(child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    const Text('Place Order', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
                    const SizedBox(width: 8),
                    Text('\$${_total.toStringAsFixed(2)}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 16)),
                  ]),
          ),
        ),
      ),
    );
  }

  String? _waafiReference;

  String get _apiPayment => _payment == 'wallet' ? 'wallet' : 'waafi_pay';

  Future<void> _placeOrder() async {
    if (_payment == 'waafi') {
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
        'delivery_address': {
          'city':    'Mogadishu',
          'country': 'Somalia',
        },
      });

      ref.read(_cartProvider.notifier).clear();
      if (_payment == 'wallet') ref.invalidate(walletProvider);
      if (mounted) {
        final data    = result['data'] ?? result;
        final orderId = data['order_id'] ?? data['id'] ?? 1;
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
            const Text('Order Failed',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17, color: _secondary),
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
        color: _card,
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
          Text(label, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: _secondary)),
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
      backgroundColor: _bg,
      appBar: AppBar(
        backgroundColor: _card,
        elevation: 0,
        leading: IconButton(icon: const Icon(Icons.arrow_back_rounded, color: _secondary), onPressed: () => Navigator.pop(context)),
        title: const Text('Track Order', style: TextStyle(color: _secondary, fontWeight: FontWeight.w700, fontSize: 18)),
        centerTitle: true,
      ),
      body: track.when(
        data: (data) => _buildTracking(context, data),
        loading: () => _buildTracking(context, null),
        error: (_, __) => _buildTracking(context, null),
      ),
    );
  }

  Widget _buildTracking(BuildContext context, dynamic data) {
    final status = data?['status'] ?? 'on_the_way';
    final eta = data?['eta'] ?? '20-25 min';
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
            Text('Order #ES${orderId.toString().padLeft(6, '0')}', style: const TextStyle(color: Colors.white70, fontSize: 13)),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
              child: const Text('Help', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
            ),
          ]),
          const SizedBox(height: 8),
          const Text('Estimated Delivery', style: TextStyle(color: Colors.white60, fontSize: 12)),
          Text(eta, style: const TextStyle(color: _primary, fontSize: 32, fontWeight: FontWeight.w900)),
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

      // Map placeholder
      Container(
        height: 200,
        decoration: BoxDecoration(
          color: const Color(0xFFE8F5E9),
          borderRadius: BorderRadius.circular(20),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 12)],
        ),
        child: Stack(children: [
          // Map background pattern
          ClipRRect(
            borderRadius: BorderRadius.circular(20),
            child: Container(
              color: const Color(0xFFDCEDC8),
              child: GridView.builder(
                physics: const NeverScrollableScrollPhysics(),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 8),
                itemCount: 80,
                itemBuilder: (_, i) => Container(
                  margin: const EdgeInsets.all(1),
                  decoration: BoxDecoration(
                    color: i % 7 == 0 ? const Color(0xFFBBDEFB).withValues(alpha: 0.4) : Colors.transparent,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
            ),
          ),
          // Route line
          Center(child: CustomPaint(size: const Size(200, 120), painter: _RoutePainter())),
          // Markers
          Positioned(top: 40, left: 60, child: const Icon(Icons.location_pin, color: _primary, size: 32)),
          Positioned(bottom: 40, right: 60, child: const Icon(Icons.home_rounded, color: _secondary, size: 32)),
        ]),
      ),

      const SizedBox(height: 16),

      // Rider card
      Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: _card, borderRadius: BorderRadius.circular(18), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 12)]),
        child: Row(children: [
          CircleAvatar(
            radius: 28,
            backgroundColor: _primary.withValues(alpha: 0.1),
            child: const Icon(Icons.person_rounded, color: _primary, size: 30),
          ),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(rider?['name'] ?? 'Abdi Hassan', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: _secondary)),
            const Text('Your Rider', style: TextStyle(fontSize: 12, color: Colors.grey)),
            Row(children: [const Icon(Icons.star_rounded, color: Colors.amber, size: 14), Text(' ${rider?['rating'] ?? '4.8'}', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 12))]),
          ])),
          Row(children: [
            _RiderBtn(icon: Icons.phone_rounded, onTap: () {}),
            const SizedBox(width: 8),
            _RiderBtn(icon: Icons.chat_rounded, onTap: () {}),
          ]),
        ]),
      ),

      const SizedBox(height: 16),

      // Arriving + Live Location
      Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: _card, borderRadius: BorderRadius.circular(18)),
        child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Arriving in', style: TextStyle(color: Colors.grey, fontSize: 13)),
            Text(data?['arriving_in'] ?? '8 min', style: const TextStyle(color: _primary, fontSize: 20, fontWeight: FontWeight.w800)),
          ]),
          GestureDetector(
            onTap: () {},
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
              decoration: BoxDecoration(
                border: Border.all(color: _primary, width: 2),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Text('Live Location', style: TextStyle(color: _primary, fontWeight: FontWeight.w700, fontSize: 13)),
            ),
          ),
        ]),
      ),
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

class _RoutePainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = _primary
      ..strokeWidth = 3
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;
    final path = Path()
      ..moveTo(size.width * 0.3, size.height * 0.2)
      ..cubicTo(size.width * 0.3, size.height * 0.6, size.width * 0.7, size.height * 0.4, size.width * 0.7, size.height * 0.8);
    canvas.drawPath(path, paint);
  }
  @override
  bool shouldRepaint(_) => false;
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
        color: _card,
        child: Column(children: [
          const Padding(padding: EdgeInsets.fromLTRB(16, 16, 16, 12), child: Align(alignment: Alignment.centerLeft, child: Text('My Orders', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: _secondary)))),
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
          const Text('Could not load orders', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: _secondary)),
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
        decoration: BoxDecoration(color: _card, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)]),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text('Order #${o['order_number'] ?? o['id']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: _secondary)),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(color: _statusColor(status).withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
              child: Text(status.toString().replaceAll('_', ' ').toUpperCase(), style: TextStyle(color: _statusColor(status), fontSize: 10, fontWeight: FontWeight.w700)),
            ),
          ]),
          const SizedBox(height: 8),
          Text(o['restaurant']?['name'] ?? o['restaurant_name'] ?? 'Restaurant', style: TextStyle(fontSize: 13, color: Colors.grey[600])),
          const SizedBox(height: 4),
          Text('${o['items_count'] ?? (o['items'] as List?)?.length ?? 0} items • \$${o['total'] ?? o['total_amount'] ?? '0.00'}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: _secondary)),
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
          const Text('Favorites', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: _secondary)),
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
            const Text('No favorites yet', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: _secondary)),
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
          const Text('No favorites yet', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: _secondary)),
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
        decoration: BoxDecoration(color: _card, borderRadius: BorderRadius.circular(16),
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
              Text(r['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: _secondary), maxLines: 1, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 3),
              Text(r['vendor_type'] ?? 'Restaurant', style: TextStyle(fontSize: 12, color: Colors.grey[500])),
              const SizedBox(height: 6),
              Row(children: [
                if (r['rating'] != null) ...[
                  const Icon(Icons.star_rounded, color: Colors.amber, size: 13),
                  const SizedBox(width: 2),
                  Text('${r['rating']}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                  const SizedBox(width: 8),
                ],
                if (r['delivery_time'] != null) ...[
                  Icon(Icons.access_time_rounded, color: Colors.grey[400], size: 12),
                  const SizedBox(width: 2),
                  Text('${r['delivery_time']} min', style: TextStyle(fontSize: 11, color: Colors.grey[500])),
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

class _ProfileTab extends StatelessWidget {
  const _ProfileTab();

  @override
  Widget build(BuildContext context) {
    return SafeArea(child: ListView(padding: const EdgeInsets.all(16), children: [
      const SizedBox(height: 8),
      Center(child: Column(children: [
        Container(
          width: 80, height: 80,
          decoration: const BoxDecoration(shape: BoxShape.circle, color: Color(0xFFFFE0B2)),
          child: const Icon(Icons.person_rounded, color: _primary, size: 44),
        ),
        const SizedBox(height: 12),
        const Text('Rafi Ahmed', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: _secondary)),
        Text('+252 61 XXXXXXX', style: TextStyle(fontSize: 14, color: Colors.grey[500])),
      ])),
      const SizedBox(height: 28),
      ...[
        (Icons.location_on_rounded,   'Saved Addresses'),
        (Icons.payment_rounded,        'Payment Methods'),
        (Icons.history_rounded,        'Order History'),
        (Icons.local_offer_rounded,    'Promo Codes'),
        (Icons.notifications_rounded,  'Notifications'),
        (Icons.help_outline_rounded,   'Help & Support'),
        (Icons.info_outline_rounded,   'About'),
        (Icons.logout_rounded,         'Logout'),
      ].map((item) => Container(
        margin: const EdgeInsets.only(bottom: 8),
        decoration: BoxDecoration(color: _card, borderRadius: BorderRadius.circular(14), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 6)]),
        child: ListTile(
          leading: Container(width: 38, height: 38, decoration: BoxDecoration(color: _primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)), child: Icon(item.$1, color: item.$2 == 'Logout' ? Colors.red : _primary, size: 20)),
          title: Text(item.$2, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: item.$2 == 'Logout' ? Colors.red : _secondary)),
          trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: Colors.grey),
          onTap: () {},
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
      )),
    ]));
  }
}
