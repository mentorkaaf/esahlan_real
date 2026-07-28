import 'dart:async';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/services/cart_sync_service.dart';
import '../../../core/services/cart_persistence_service.dart';

extension _CacheFor on Ref {
  void cacheFor(Duration d) {
    final link = keepAlive();
    final t = Timer(d, link.close);
    onDispose(t.cancel);
  }
}

double _toD(dynamic v) => double.tryParse(v?.toString() ?? '0') ?? 0;
int _toId(dynamic v) => int.tryParse(v?.toString() ?? '0') ?? 0;

// ─────────────────────────────────────────────────────────────────
// Cart model
// ─────────────────────────────────────────────────────────────────
class CartItem {
  final int productId;
  final Map<String, dynamic> product;
  final int qty;
  final int? variantId;
  final Map<String, dynamic>? variant;

  const CartItem({
    required this.productId,
    required this.product,
    required this.qty,
    this.variantId,
    this.variant,
  });

  CartItem copyWith({int? qty, int? variantId, Map<String, dynamic>? variant}) => CartItem(
        productId: productId,
        product: product,
        qty: qty ?? this.qty,
        variantId: variantId ?? this.variantId,
        variant: variant ?? this.variant,
      );

  double get effectivePrice {
    if (variant != null && variant!['price'] != null) {
      return _toD(variant!['price']);
    }
    final sale = product['sale_price'];
    if (sale != null && _toD(sale) > 0) return _toD(sale);
    return _toD(product['price']);
  }

  double get lineTotal => effectivePrice * qty;
}

// ─────────────────────────────────────────────────────────────────
// Cart notifier
// ─────────────────────────────────────────────────────────────────
class EShopCartNotifier extends StateNotifier<List<CartItem>> {
  EShopCartNotifier() : super([]) {
    _loadFromDisk();
  }

  static const _module = 'eshop';

  Future<void> _loadFromDisk() async {
    final saved = await CartPersistenceService.instance.load(_module);
    if (saved.isEmpty || !mounted) return;
    final items = saved.map((m) => CartItem(
      productId: (m['productId'] as num).toInt(),
      product:   Map<String, dynamic>.from(m['product'] as Map? ?? {}),
      qty:       (m['qty'] as num?)?.toInt() ?? 1,
      variantId: m['variantId'] != null ? (m['variantId'] as num).toInt() : null,
      variant:   m['variant'] != null ? Map<String, dynamic>.from(m['variant'] as Map) : null,
    )).toList();
    if (mounted) state = items;
  }

  void _saveToDisk() {
    final data = state.map((c) => {
      'productId': c.productId,
      'product':   c.product,
      'qty':       c.qty,
      'variantId': c.variantId,
      'variant':   c.variant,
    }).toList();
    CartPersistenceService.instance.save(_module, data);
  }

  void _sync() {
    final items = state.map((c) => {
      'product_id':    c.productId,
      'product_name':  c.product['name'] ?? '',
      'product_image': c.product['thumbnail'] ?? c.product['image'] ?? '',
      'price':         c.effectivePrice,
      'quantity':      c.qty,
    }).toList();
    CartSyncService.instance.syncDebounced(_module, items);
  }

  void addItem(Map<String, dynamic> product, {int qty = 1, int? variantId, Map<String, dynamic>? variant}) {
    final id = _toId(product['id']);
    final idx = state.indexWhere((c) => c.productId == id && c.variantId == variantId);
    if (idx >= 0) {
      final updated = List<CartItem>.from(state);
      updated[idx] = updated[idx].copyWith(qty: updated[idx].qty + qty);
      state = updated;
    } else {
      state = [...state, CartItem(productId: id, product: product, qty: qty, variantId: variantId, variant: variant)];
    }
    _saveToDisk();
    _sync();
  }

  void removeItem(int productId, {int? variantId}) {
    state = state.where((c) => !(c.productId == productId && c.variantId == variantId)).toList();
    _saveToDisk();
    _sync();
  }

  void updateQty(int productId, int qty, {int? variantId}) {
    if (qty <= 0) {
      removeItem(productId, variantId: variantId);
      return;
    }
    state = state.map((c) => c.productId == productId && c.variantId == variantId ? c.copyWith(qty: qty) : c).toList();
    _saveToDisk();
    _sync();
  }

  void clear() {
    state = [];
    CartPersistenceService.instance.clear(_module);
    CartSyncService.instance.clearModule(_module);
  }

  int qtyFor(int productId, {int? variantId}) {
    try {
      return state.firstWhere((c) => c.productId == productId && c.variantId == variantId).qty;
    } catch (_) {
      return 0;
    }
  }

  bool contains(int productId, {int? variantId}) => state.any((c) => c.productId == productId && c.variantId == variantId);

  int get totalCount => state.fold(0, (s, c) => s + c.qty);
  double get subtotal => state.fold(0.0, (s, c) => s + c.lineTotal);
}

final eshopCartProvider = StateNotifierProvider<EShopCartNotifier, List<CartItem>>((_) => EShopCartNotifier());

// ─────────────────────────────────────────────────────────────────
// Wishlist
// ─────────────────────────────────────────────────────────────────
final eshopWishlistProvider = StateProvider<Set<int>>((_) => {});

// ─────────────────────────────────────────────────────────────────
// API service singleton
// ─────────────────────────────────────────────────────────────────
final _svc = ModuleApiService.create();

// ─────────────────────────────────────────────────────────────────
// Home data
// ─────────────────────────────────────────────────────────────────
Map<String, dynamic> _safeMap(dynamic v) {
  if (v is Map<String, dynamic>) return v;
  if (v is Map) return Map<String, dynamic>.from(v);
  return {};
}

List<dynamic> _safeList(dynamic v) {
  if (v is List) return v;
  return [];
}

final eshopHomeProvider = FutureProvider<Map<String, dynamic>>((ref) async {
  ref.cacheFor(const Duration(seconds: 30));
  final res = await _svc.getShopHome();
  final map = _safeMap(res);
  return _safeMap(map['data']);
});

// ─────────────────────────────────────────────────────────────────
// Categories
// ─────────────────────────────────────────────────────────────────
final eshopCategoriesProvider = FutureProvider<List<dynamic>>((ref) async {
  ref.cacheFor(const Duration(seconds: 30));
  final res = await _svc.getShopCategories();
  final map = _safeMap(res);
  return _safeList(map['data']);
});

// ─────────────────────────────────────────────────────────────────
// Products (parameterised)
// ─────────────────────────────────────────────────────────────────
class ProductsParams {
  final int? categoryId;
  final String? search;
  final String? sort;
  final int page;
  final bool? featured;

  const ProductsParams({this.categoryId, this.search, this.sort, this.page = 1, this.featured});

  @override
  bool operator ==(Object other) =>
      other is ProductsParams &&
      other.categoryId == categoryId &&
      other.search == search &&
      other.sort == sort &&
      other.page == page &&
      other.featured == featured;

  @override
  int get hashCode => Object.hash(categoryId, search, sort, page, featured);
}

final eshopProductsProvider = FutureProvider.family<Map<String, dynamic>, ProductsParams>((_, p) async {
  final res = await _svc.getShopProducts(
    categoryId: p.categoryId,
    search: p.search,
    sort: p.sort,
    page: p.page,
    featured: p.featured,
  );
  return _safeMap(res);
});

// ─────────────────────────────────────────────────────────────────
// Flash deals
// ─────────────────────────────────────────────────────────────────
final eshopFlashDealsProvider = FutureProvider<List<dynamic>>((ref) async {
  ref.cacheFor(const Duration(seconds: 30));
  final res = await _svc.getShopFlashDeals();
  return _safeList(_safeMap(res)['data']);
});

// ─────────────────────────────────────────────────────────────────
// Deals of day
// ─────────────────────────────────────────────────────────────────
final eshopDealsOfDayProvider = FutureProvider<List<dynamic>>((ref) async {
  ref.cacheFor(const Duration(seconds: 30));
  final res = await _svc.getShopDealsOfDay();
  return _safeList(_safeMap(res)['data']);
});

// ─────────────────────────────────────────────────────────────────
// Campaigns
// ─────────────────────────────────────────────────────────────────
final eshopCampaignsProvider = FutureProvider<List<dynamic>>((ref) async {
  ref.cacheFor(const Duration(seconds: 30));
  final res = await _svc.getShopCampaigns();
  return _safeList(_safeMap(res)['data']);
});

// ─────────────────────────────────────────────────────────────────
// Single product
// ─────────────────────────────────────────────────────────────────
final eshopProductProvider = FutureProvider.family<Map<String, dynamic>, int>((_, id) async {
  final res = await _svc.getShopProduct(id);
  return _safeMap(_safeMap(res)['data']);
});

// ─────────────────────────────────────────────────────────────────
// Coupon state
// ─────────────────────────────────────────────────────────────────
class CouponState {
  final bool isValid;
  final String? code;
  final String? discountType;
  final double discountValue;
  final String? message;

  const CouponState({this.isValid = false, this.code, this.discountType, this.discountValue = 0, this.message});

  double calculateDiscount(double subtotal) {
    if (!isValid) return 0;
    if (discountType == 'percentage') return subtotal * discountValue / 100;
    return discountValue;
  }
}

class CouponNotifier extends StateNotifier<CouponState> {
  CouponNotifier() : super(const CouponState());

  Future<void> validate(String code, double orderAmount) async {
    try {
      final res = await _svc.validateShopCoupon(code: code, orderAmount: orderAmount);
      state = CouponState(
        isValid: res['valid'] == true,
        code: code,
        discountType: res['discount_type'],
        discountValue: _toD(res['discount_value']),
        message: res['message'],
      );
    } catch (_) {
      state = const CouponState(isValid: false, message: 'Invalid coupon code');
    }
  }

  void clear() => state = const CouponState();
}

final eshopCouponProvider = StateNotifierProvider<CouponNotifier, CouponState>((_) => CouponNotifier());

// ─────────────────────────────────────────────────────────────────
// Stores list
// ─────────────────────────────────────────────────────────────────
final eshopStoresProvider = FutureProvider<List<dynamic>>((ref) async {
  ref.cacheFor(const Duration(seconds: 60));
  final res = await _svc.getShopStores();
  return _safeList(_safeMap(res)['data']);
});

// ─────────────────────────────────────────────────────────────────
// Store detail (by id)
// ─────────────────────────────────────────────────────────────────
final eshopStoreDetailProvider = FutureProvider.family<Map<String, dynamic>, int>((_, id) async {
  final res = await _svc.getShopStoreDetail(id);
  return _safeMap(_safeMap(res)['data']);
});

// ─────────────────────────────────────────────────────────────────
// Popular products
// ─────────────────────────────────────────────────────────────────
final eshopPopularProvider = FutureProvider<List<dynamic>>((ref) async {
  ref.cacheFor(const Duration(seconds: 60));
  final res = await _svc.getShopPopular();
  return _safeList(_safeMap(res)['data']);
});

// ─────────────────────────────────────────────────────────────────
// Product reviews (by product id)
// ─────────────────────────────────────────────────────────────────
final eshopProductReviewsProvider = FutureProvider.family<Map<String, dynamic>, int>((_, id) async {
  final res = await _svc.getShopProductReviews(id);
  return _safeMap(res);
});
