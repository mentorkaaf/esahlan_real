import '../../../../core/api/api_client.dart';
import '../models/egrocery_models.dart';

class EGroceryRepository {
  static const _base = '/egrocery';

  // ── Home ───────────────────────────────────────────────────────────────────

  Future<EGHomePayload> getHome() async {
    final r = await ApiClient.instance.get('$_base/home');
    return EGHomePayload.fromJson(r.data['data']);
  }

  // ── Catalog ────────────────────────────────────────────────────────────────

  Future<List<EGCategory>> getCategories() async {
    final r = await ApiClient.instance.get('$_base/categories');
    return _list(r.data['data'], EGCategory.fromJson);
  }

  Future<EGCategory> getCategory(int id) async {
    final r = await ApiClient.instance.get('$_base/categories/$id');
    return EGCategory.fromJson(r.data['data']);
  }

  Future<({List<EGProduct> products, int total, int lastPage})> getProducts({
    int? categoryId,
    int? brandId,
    double? minPrice,
    double? maxPrice,
    bool? discounted,
    bool? inStock,
    String? search,
    String? sort,
    int page = 1,
  }) async {
    final r = await ApiClient.instance.get('$_base/products', queryParameters: {
      if (categoryId != null) 'category': categoryId,
      if (brandId != null) 'brand': brandId,
      if (minPrice != null) 'min_price': minPrice,
      if (maxPrice != null) 'max_price': maxPrice,
      if (discounted == true) 'discounted': 1,
      if (inStock == true) 'in_stock': 1,
      if (search != null && search.isNotEmpty) 'search': search,
      if (sort != null) 'sort': sort,
      'page': page,
    });
    return (
      products: _list(r.data['data'], EGProduct.fromJson),
      total: (r.data['meta']?['total'] ?? 0) as int,
      lastPage: (r.data['meta']?['last_page'] ?? 1) as int,
    );
  }

  Future<EGProduct> getProduct(String slug) async {
    final r = await ApiClient.instance.get('$_base/products/$slug');
    return EGProduct.fromJson(r.data['data']);
  }

  Future<List<dynamic>> suggest(String q) async {
    final r = await ApiClient.instance.get('$_base/search/suggest', queryParameters: {'q': q});
    return r.data['data'] as List? ?? [];
  }

  // ── Favorites ──────────────────────────────────────────────────────────────

  Future<List<EGProduct>> getFavorites() async {
    final r = await ApiClient.instance.get('$_base/favorites');
    return _list(r.data['data'], EGProduct.fromJson);
  }

  Future<void> addFavorite(int productId) async {
    await ApiClient.instance.post('$_base/favorites', data: {'product_id': productId});
  }

  Future<void> removeFavorite(int productId) async {
    await ApiClient.instance.delete('$_base/favorites/$productId');
  }

  // ── Shopping Lists ─────────────────────────────────────────────────────────

  Future<List<dynamic>> getLists() async {
    final r = await ApiClient.instance.get('$_base/lists');
    return r.data['data'] as List? ?? [];
  }

  Future<Map<String, dynamic>> getList(int id) async {
    final r = await ApiClient.instance.get('$_base/lists/$id');
    return Map<String, dynamic>.from(r.data['data']);
  }

  Future<void> createList(String name) async {
    await ApiClient.instance.post('$_base/lists', data: {'name': name});
  }

  Future<void> addToList(int listId, {required int productId, int? variantId, double qty = 1}) async {
    await ApiClient.instance.post('$_base/lists/$listId/items', data: {
      'product_id': productId,
      if (variantId != null) 'variant_id': variantId,
      'qty': qty,
    });
  }

  Future<void> updateListItem(int listId, int itemId, {double? qty, bool? checked}) async {
    await ApiClient.instance.patch('$_base/lists/$listId/items/$itemId', data: {
      if (qty != null) 'qty': qty,
      if (checked != null) 'checked': checked,
    });
  }

  Future<void> deleteListItem(int listId, int itemId) async {
    await ApiClient.instance.delete('$_base/lists/$listId/items/$itemId');
  }

  // ── Cart ───────────────────────────────────────────────────────────────────

  Future<EGCartValidateResult> validateCart({
    required List<EGCartLine> lines,
    String? coupon,
    int? addressId,
  }) async {
    final r = await ApiClient.instance.post('$_base/cart/validate', data: {
      'lines': lines.map((l) => l.toJson()).toList(),
      if (coupon != null) 'coupon': coupon,
      if (addressId != null) 'address_id': addressId,
    });
    return EGCartValidateResult.fromJson(r.data['data']);
  }

  // ── Checkout ───────────────────────────────────────────────────────────────

  Future<List<EGDeliverySlot>> getSlots() async {
    final r = await ApiClient.instance.get('$_base/checkout/slots');
    return _list(r.data['data'], EGDeliverySlot.fromJson);
  }

  Future<EGOrder> placeOrder({
    required String paymentMethod,
    required List<EGCartLine> lines,
    int? addressId,
    int? slotId,
    String? scheduledDate,
    String? substitutionPref,
    String? note,
    String? coupon,
    String? paymentReference,
  }) async {
    final r = await ApiClient.instance.post('$_base/orders', data: {
      'payment_method': paymentMethod,
      'lines': lines.map((l) => l.toJson()).toList(),
      if (addressId != null) 'address_id': addressId,
      if (slotId != null) 'slot_id': slotId,
      if (scheduledDate != null) 'scheduled_date': scheduledDate,
      if (substitutionPref != null) 'substitution_pref': substitutionPref,
      if (note != null) 'note': note,
      if (coupon != null) 'coupon': coupon,
      if (paymentReference != null) 'payment_reference': paymentReference,
    });
    return EGOrder.fromJson(r.data['data']);
  }

  /// Attach a mobile-pay proof token to an already-placed order (fire & forget).
  Future<void> attachMobilePayProof(String orderNo, String proofToken) async {
    try {
      await ApiClient.instance.post(
        '$_base/orders/$orderNo/mobile-pay-proof',
        data: {'proof_token': proofToken},
      );
    } catch (_) {}
  }

  // ── Orders ─────────────────────────────────────────────────────────────────

  Future<({List<EGOrder> orders, int lastPage})> getOrders({int page = 1}) async {
    final r = await ApiClient.instance.get('$_base/orders', queryParameters: {'page': page});
    return (
      orders: _list(r.data['data'], EGOrder.fromJson),
      lastPage: (r.data['meta']?['last_page'] ?? 1) as int,
    );
  }

  Future<EGOrder> getOrder(int id) async {
    final r = await ApiClient.instance.get('$_base/orders/$id');
    return EGOrder.fromJson(r.data['data']);
  }

  Future<void> cancelOrder(int id, {String? reason}) async {
    await ApiClient.instance.post('$_base/orders/$id/cancel', data: {
      if (reason != null) 'reason': reason,
    });
  }

  Future<List<EGCartLine>> reorder(int id) async {
    final r = await ApiClient.instance.post('$_base/orders/$id/reorder', data: {});
    final lines = (r.data['data']['lines'] as List? ?? []).map((l) => EGCartLine(
      variantId: l['variant_id'],
      qty: (l['qty'] as num).toDouble(),
      productName: l['product_name'] ?? '',
      variantLabel: l['variant_label'] ?? '',
      unitPrice: (l['unit_price'] as num?)?.toDouble() ?? 0,
    )).toList();
    return lines;
  }

  Future<void> submitReview(int productId, {required int rating, String? comment}) async {
    await ApiClient.instance.post('$_base/products/$productId/reviews', data: {
      'rating': rating,
      if (comment != null && comment.isNotEmpty) 'comment': comment,
    });
  }
}

// ─── helper ─────────────────────────────────────────────────────────────────

List<T> _list<T>(dynamic raw, T Function(Map<String, dynamic>) fromJson) {
  if (raw == null) return [];
  if (raw is List) return raw.map((e) => fromJson(Map<String, dynamic>.from(e))).toList();
  return [];
}
