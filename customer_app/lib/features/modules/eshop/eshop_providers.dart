import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/api/module_api_service.dart';

double _toD(dynamic v) => double.tryParse(v?.toString() ?? '0') ?? 0;

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
  EShopCartNotifier() : super([]);

  void addItem(Map<String, dynamic> product, {int qty = 1, int? variantId, Map<String, dynamic>? variant}) {
    final idx = state.indexWhere((c) => c.productId == product['id'] && c.variantId == variantId);
    if (idx >= 0) {
      final updated = List<CartItem>.from(state);
      updated[idx] = updated[idx].copyWith(qty: updated[idx].qty + qty);
      state = updated;
    } else {
      state = [...state, CartItem(productId: product['id'], product: product, qty: qty, variantId: variantId, variant: variant)];
    }
  }

  void removeItem(int productId, {int? variantId}) {
    state = state.where((c) => !(c.productId == productId && c.variantId == variantId)).toList();
  }

  void updateQty(int productId, int qty, {int? variantId}) {
    if (qty <= 0) {
      removeItem(productId, variantId: variantId);
      return;
    }
    state = state.map((c) => c.productId == productId && c.variantId == variantId ? c.copyWith(qty: qty) : c).toList();
  }

  void clear() => state = [];

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
final eshopHomeProvider = FutureProvider<Map<String, dynamic>>((_) async {
  final res = await _svc.getShopHome();
  return (res['data'] as Map<String, dynamic>?) ?? {};
});

// ─────────────────────────────────────────────────────────────────
// Categories
// ─────────────────────────────────────────────────────────────────
final eshopCategoriesProvider = FutureProvider<List<dynamic>>((_) async {
  final res = await _svc.getShopCategories();
  return (res['data'] as List?) ?? [];
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
  return res as Map<String, dynamic>;
});

// ─────────────────────────────────────────────────────────────────
// Flash deals
// ─────────────────────────────────────────────────────────────────
final eshopFlashDealsProvider = FutureProvider<List<dynamic>>((_) async {
  final res = await _svc.getShopFlashDeals();
  return (res['data'] as List?) ?? [];
});

// ─────────────────────────────────────────────────────────────────
// Deals of day
// ─────────────────────────────────────────────────────────────────
final eshopDealsOfDayProvider = FutureProvider<List<dynamic>>((_) async {
  final res = await _svc.getShopDealsOfDay();
  return (res['data'] as List?) ?? [];
});

// ─────────────────────────────────────────────────────────────────
// Campaigns
// ─────────────────────────────────────────────────────────────────
final eshopCampaignsProvider = FutureProvider<List<dynamic>>((_) async {
  final res = await _svc.getShopCampaigns();
  return (res['data'] as List?) ?? [];
});

// ─────────────────────────────────────────────────────────────────
// Single product
// ─────────────────────────────────────────────────────────────────
final eshopProductProvider = FutureProvider.family<Map<String, dynamic>, int>((_, id) async {
  final res = await _svc.getShopProduct(id);
  return (res['data'] as Map<String, dynamic>?) ?? {};
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
